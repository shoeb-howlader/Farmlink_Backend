<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\AdminNotification;
use App\Models\Order;
use App\Models\Payment;
use App\Services\SSLCommerzService;
use App\Services\SystemAlertService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReconcilePendingPaymentsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payments:reconcile {--age=3 : Minimum age in minutes to reconcile} {--timeout=60 : Session timeout in minutes to cancel abandoned orders}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reconcile pending_payment orders against SSLCommerz gateway to settle missed IPNs and abandoned sessions';

    /**
     * Execute the console command.
     */
    public function handle(SSLCommerzService $sslCommerzService, SystemAlertService $systemAlertService): int
    {
        $ageMinutes = (int) $this->option('age');
        $timeoutMinutes = (int) $this->option('timeout');

        $lock = Cache::lock('payments:reconcile:lock', 600);

        if (! $lock->get()) {
            $this->warn('Another instance of payments:reconcile is currently running. Exiting.');
            return Command::SUCCESS;
        }

        try {
            $cutoff = now()->subMinutes($ageMinutes);
            $abandonedCutoff = now()->subMinutes($timeoutMinutes);

            $pendingOrders = Order::where('status', 'pending_payment')
                ->where('created_at', '<=', $cutoff)
                ->orderBy('id')
                ->get();

            if ($pendingOrders->isEmpty()) {
                $this->info("No pending_payment orders found older than {$ageMinutes} minutes.");
                return Command::SUCCESS;
            }

            $completedCount = 0;
            $failedCount = 0;
            $cancelledCount = 0;
            $skippedCount = 0;

            foreach ($pendingOrders as $pendingOrder) {
                // Per-order database row lock to prevent race conditions with concurrent IPN callbacks
                DB::transaction(function () use (
                    $pendingOrder,
                    $sslCommerzService,
                    $systemAlertService,
                    $abandonedCutoff,
                    $timeoutMinutes,
                    &$completedCount,
                    &$failedCount,
                    &$cancelledCount,
                    &$skippedCount
                ) {
                    $order = Order::where('id', $pendingOrder->id)
                        ->where('status', 'pending_payment')
                        ->lockForUpdate()
                        ->first();

                    if (! $order) {
                        $skippedCount++;
                        return;
                    }

                    $tranId = $order->gateway_transaction_id;

                    if (! $tranId) {
                        // Order has no transaction ID generated. If older than timeout, auto-cancel.
                        if ($order->created_at <= $abandonedCutoff) {
                            $order->update([
                                'status' => 'cancelled',
                                'payment_status' => 'failed',
                                'notes' => trim(($order->notes ? $order->notes . "\n" : '') . "[System Reconcile: Cancelled abandoned order with no gateway transaction after {$timeoutMinutes} mins]"),
                            ]);
                            Payment::where('order_id', $order->id)->update(['status' => 'failed']);
                            $cancelledCount++;

                            ActivityLog::log(
                                'order_reconcile_cancelled_no_tran',
                                $order,
                                ['reason' => "Abandoned without transaction after {$timeoutMinutes} minutes"],
                                null
                            );
                        } else {
                            $skippedCount++;
                        }
                        return;
                    }

                    // Query the gateway
                    $queryResult = $sslCommerzService->queryTransaction($tranId);
                    $status = strtoupper($queryResult['status'] ?? '');

                    // 1. Gateway unreachable check
                    if ($status === 'GATEWAY_UNREACHABLE' || $status === 'GATEWAY_ERROR') {
                        $systemAlertService->sendCriticalAlert(
                            'SSLCommerz Gateway API Unreachable During Reconciliation',
                            "Could not query transaction {$tranId} for Order #{$order->id}: " . ($queryResult['error'] ?? $queryResult['message'] ?? 'Gateway connection failed'),
                            ['order_id' => $order->id, 'tran_id' => $tranId]
                        );
                        $skippedCount++;
                        return;
                    }

                    // 2. Gateway reports valid payment (IPN was missed or dropped)
                    if ($status === 'VALID' || $status === 'VALIDATED') {
                        $valId = $queryResult['val_id'] ?? $queryResult['element'][0]['val_id'] ?? $tranId;
                        $result = $sslCommerzService->processValidatedPayment($queryResult, (string) $valId);

                        if ($result['success']) {
                            $completedCount++;
                            ActivityLog::log(
                                'order_reconcile_settled_paid',
                                $order,
                                [
                                    'tran_id' => $tranId,
                                    'val_id' => $valId,
                                    'action' => 'Reconciled paid order with missed IPN',
                                ],
                                null
                            );
                        } else {
                            $skippedCount++;
                        }
                        return;
                    }

                    // 3. Gateway reports failed or cancelled by user
                    if (in_array($status, ['FAILED', 'FAIL', 'CANCELLED', 'CANCEL'])) {
                        $order->update([
                            'status' => 'payment_failed',
                            'payment_status' => 'failed',
                            'notes' => trim(($order->notes ? $order->notes . "\n" : '') . "[System Reconcile: Gateway reported payment {$status}]"),
                        ]);

                        Payment::where('order_id', $order->id)->update(['status' => 'failed']);
                        $failedCount++;

                        ActivityLog::log(
                            'order_reconcile_marked_failed',
                            $order,
                            ['tran_id' => $tranId, 'gateway_status' => $status],
                            null
                        );

                        app(\App\Services\PaymentFailureEscalationService::class)->checkAndEscalate($order->user_id);
                        return;
                    }

                    // 4. No transaction registered at gateway or unattempted
                    if ($order->created_at <= $abandonedCutoff) {
                        $order->update([
                            'status' => 'cancelled',
                            'payment_status' => 'failed',
                            'notes' => trim(($order->notes ? $order->notes . "\n" : '') . "[System Reconcile: Cancelled abandoned unpaid order after {$timeoutMinutes} mins (Gateway status: {$status})]"),
                        ]);

                        Payment::where('order_id', $order->id)->update(['status' => 'failed']);
                        $cancelledCount++;

                        AdminNotification::notify(
                            'order.payment_timeout',
                            "Order #{$order->id} Cancelled",
                            "Order #{$order->id} for ৳" . number_format($order->total, 2) . " was automatically cancelled because online payment was abandoned.",
                            ['order_id' => $order->id],
                            $order->user_id
                        );

                        ActivityLog::log(
                            'order_reconcile_auto_cancelled_abandoned',
                            $order,
                            ['tran_id' => $tranId, 'timeout_minutes' => $timeoutMinutes],
                            null
                        );
                    } else {
                        // Still within allowed checkout window
                        $skippedCount++;
                    }
                });
            }

            $summary = "Payment Reconciliation Completed: {$completedCount} paid/settled, {$failedCount} failed, {$cancelledCount} cancelled, {$skippedCount} skipped.";
            $this->info($summary);
            Log::info("[PAYMENT RECONCILE] {$summary}");

            return Command::SUCCESS;
        } finally {
            $lock->release();
        }
    }
}
