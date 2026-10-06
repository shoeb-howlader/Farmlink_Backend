<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\AdminNotification;
use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CancelExpiredPendingPaymentsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payments:cancel-expired {--minutes= : Override timeout minutes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Auto-cancel abandoned pending_payment orders whose payment timeout has elapsed';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $timeoutMinutes = (int) ($this->option('minutes') ?: config('sslcommerz.order_timeout_minutes', 45));
        $cutoffTime = now()->subMinutes($timeoutMinutes);

        $expiredOrders = Order::where('status', 'pending_payment')
            ->where('created_at', '<=', $cutoffTime)
            ->get();

        if ($expiredOrders->isEmpty()) {
            $this->info("No expired pending_payment orders found older than {$timeoutMinutes} minutes.");
            return Command::SUCCESS;
        }

        $count = 0;
        foreach ($expiredOrders as $order) {
            $order->update([
                'status' => 'cancelled',
                'payment_status' => 'failed',
                'notes' => trim(($order->notes ? $order->notes . "\n" : '') . "[System: Auto-cancelled due to payment session timeout after {$timeoutMinutes} mins]"),
            ]);

            \App\Models\Payment::where('order_id', $order->id)->update([
                'status' => 'failed',
            ]);

            // Notify farmer that payment was not completed
            if ($order->user_id) {
                AdminNotification::notify(
                    'order.payment_timeout',
                    "Order #{$order->id} Cancelled",
                    "Your order #{$order->id} for ৳" . number_format($order->total, 2) . " was automatically cancelled because online payment was not completed.",
                    ['order_id' => $order->id],
                    $order->user_id
                );
            }

            ActivityLog::log(
                'order_auto_cancelled_timeout',
                $order,
                ['reason' => "Auto-cancelled after {$timeoutMinutes} minutes with no completed online payment."],
                null
            );

            $count++;
        }

        $this->info("Successfully cancelled {$count} expired pending_payment orders.");
        Log::info("payments:cancel-expired cancelled {$count} orders older than {$timeoutMinutes} mins.");

        return Command::SUCCESS;
    }
}
