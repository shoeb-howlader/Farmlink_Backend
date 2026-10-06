<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\V1\PaymentResource;
use App\Models\ActivityLog;
use App\Models\AdminNotification;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductStockAdjustment;
use App\Models\ProductVariant;
use App\Models\Refund;
use App\Services\SSLCommerzService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PaymentController extends ApiController
{
    /**
     * Display a paginated listing of payments across all channels.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Payment::with(['user', 'order.items.product', 'refunds'])->latest();

        // Status Filter
        if ($request->filled('status') && $request->query('status') !== 'all') {
            $query->where('status', $request->query('status'));
        }

        // Method Filter (sslcommerz, cash, cod, credit)
        if ($request->filled('method') && $request->query('method') !== 'all') {
            $query->where('method', $request->query('method'));
        }

        // Failed or Abandoned Quick Filter
        if ($request->query('filter') === 'failed_or_abandoned') {
            $query->failedOrAbandoned();
        }

        // Date Range
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->query('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->query('date_to'));
        }

        // Sub-Method Filter (e.g. bkash, nagad, rocket, visa)
        if ($request->filled('sub_method') && $request->query('sub_method') !== 'all') {
            $sub = trim($request->query('sub_method'));
            $query->where(function ($q) use ($sub) {
                $q->where('gateway_response->card_brand', 'like', "%{$sub}%")
                    ->orWhere('gateway_response->card_type', 'like', "%{$sub}%")
                    ->orWhere('gateway_response->card_issuer', 'like', "%{$sub}%");
            });
        }

        // Search Query (Order ID, Invoice, Farmer Name, Phone, Gateway Tran ID, Bank Tran ID, MFS/Card)
        if ($request->filled('search')) {
            $search = trim($request->query('search'));
            $query->where(function ($q) use ($search) {
                $q->where('id', $search)
                    ->orWhere('order_id', $search)
                    ->orWhere('gateway_transaction_id', 'like', "%{$search}%")
                    ->orWhere('gateway_response->bank_tran_id', 'like', "%{$search}%")
                    ->orWhere('gateway_response->card_type', 'like', "%{$search}%")
                    ->orWhere('gateway_response->card_brand', 'like', "%{$search}%")
                    ->orWhere('gateway_response->card_issuer', 'like', "%{$search}%")
                    ->orWhere('gateway_response->card_no', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    })
                    ->orWhereHas('order', function ($oq) use ($search) {
                        $oq->where('invoice_number', 'like', "%{$search}%");
                    });
            });
        }

        // Summary Aggregates for Dashboard / Top Bar
        $paymentStats = Payment::selectRaw("
            count(*) as total_count,
            count(case when status = 'success' then 1 end) as success_count,
            count(case when status = 'pending' then 1 end) as pending_count,
            count(case when status = 'failed' then 1 end) as failed_count,
            coalesce(sum(case when status = 'success' then amount else 0 end), 0) as total_volume
        ")->first();

        $summary = [
            'total_volume' => (float) ($paymentStats->total_volume ?? 0),
            'total_count' => (int) ($paymentStats->total_count ?? 0),
            'success_count' => (int) ($paymentStats->success_count ?? 0),
            'pending_count' => (int) ($paymentStats->pending_count ?? 0),
            'failed_count' => (int) ($paymentStats->failed_count ?? 0),
            'refunded_volume' => (float) Refund::where('status', 'completed')->sum('amount'),
        ];

        $perPage = max(1, min((int) $request->query('per_page', 15), 100));
        $paginated = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Payments retrieved successfully',
            'data' => PaymentResource::collection($paginated->items()),
            'summary' => $summary,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    /**
     * Display a specific payment with full order details, customer, and refund history.
     */
    public function show(Payment $payment): JsonResponse
    {
        $payment->load([
            'user',
            'order.items.product',
            'order.items.variant',
            'order.farm',
            'refunds.initiator',
        ]);

        return $this->successResponse(
            new PaymentResource($payment),
            'Payment details retrieved successfully'
        );
    }

    /**
     * Mark a pending COD/Cash/Credit payment as paid (e.g. cash collected upon delivery).
     */
    public function markAsPaid(Request $request, Payment $payment): JsonResponse
    {
        if ($payment->status === 'success') {
            return $this->errorResponse('This payment is already marked as paid.', 422);
        }

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
            'bank_tran_id' => ['nullable', 'string', 'max:100'],
            'sub_method' => ['nullable', 'string', 'max:50'],
        ]);

        if (! empty($validated['bank_tran_id']) || ! empty($validated['sub_method'])) {
            $paymentDetails = $payment->gateway_response ?: [];
            if (! empty($validated['bank_tran_id'])) {
                $paymentDetails['bank_tran_id'] = $validated['bank_tran_id'];
            }
            if (! empty($validated['sub_method'])) {
                $paymentDetails['card_brand'] = strtoupper($validated['sub_method']);
            }
            $payment->update([
                'gateway_response' => $paymentDetails,
            ]);

            if ($payment->order) {
                $orderGDetails = $payment->order->gateway_payment_details ?: [];
                if (! empty($validated['bank_tran_id'])) {
                    $orderGDetails['bank_tran_id'] = $validated['bank_tran_id'];
                }
                if (! empty($validated['sub_method'])) {
                    $orderGDetails['card_brand'] = strtoupper($validated['sub_method']);
                }
                $payment->order->update([
                    'gateway_payment_details' => $orderGDetails,
                ]);
            }
        }

        $payment->markAsPaid($validated['notes'] ?? null);

        ActivityLog::log(
            'payment.marked_as_paid',
            $payment,
            [
                'order_id' => $payment->order_id,
                'amount' => (float) $payment->amount,
                'method' => $payment->method,
                'bank_tran_id' => $validated['bank_tran_id'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ],
            $request->user()
        );

        if ($payment->order && $payment->order->user_id) {
            AdminNotification::notify(
                'order.payment_received',
                'Payment Received',
                "Your payment of ৳" . number_format($payment->amount, 2) . " for Order #{$payment->order_id} has been recorded.",
                ['order_id' => $payment->order_id, 'payment_id' => $payment->id],
                $payment->order->user_id
            );
        }

        return $this->successResponse(
            new PaymentResource($payment->fresh(['order', 'user', 'refunds'])),
            'Payment marked as paid successfully'
        );
    }

    /**
     * Issue full or partial refund with explicit restock control.
     */
    public function refund(Request $request, Payment $payment): JsonResponse
    {
        $remainingBalance = $payment->remaining_refundable_amount;

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
            'restock_items' => ['nullable', 'boolean'],
        ]);

        $amount = round((float) $validated['amount'], 2);
        $reason = trim($validated['reason']);
        $restockItems = (bool) ($validated['restock_items'] ?? false);

        if ($amount > $remainingBalance) {
            throw ValidationException::withMessages([
                'amount' => ["Refund amount (৳{$amount}) cannot exceed the remaining refundable balance of ৳" . number_format($remainingBalance, 2) . "."],
            ]);
        }

        $order = $payment->order;
        $admin = $request->user();

        // 1. Gateway refund if SSLCommerz
        $gatewayRefundRef = null;
        $gatewayResponse = null;
        $refundStatus = 'completed';

        if ($payment->method === 'sslcommerz') {
            try {
                $sslService = app(SSLCommerzService::class);
                $gwResult = $sslService->initiateRefund($order, $amount, $reason);

                if (! $gwResult['success']) {
                    return $this->errorResponse($gwResult['message'] ?? 'Gateway refund request failed', 422);
                }

                $gatewayRefundRef = $gwResult['refund_ref_id'] ?? null;
                $gatewayResponse = $gwResult['response'] ?? null;
                $refundStatus = 'completed';
            } catch (\Throwable $e) {
                Log::error("SSLCommerz refund exception: " . $e->getMessage());
                return $this->errorResponse('SSLCommerz refund gateway error: ' . $e->getMessage(), 500);
            }
        }

        // 2. Database transaction to record refund and adjust inventory if requested
        $refund = DB::transaction(function () use (
            $payment,
            $order,
            $amount,
            $reason,
            $restockItems,
            $gatewayRefundRef,
            $gatewayResponse,
            $refundStatus,
            $admin
        ) {
            $createdRefund = Refund::create([
                'payment_id' => $payment->id,
                'order_id' => $payment->order_id,
                'amount' => $amount,
                'reason' => $reason,
                'status' => $refundStatus,
                'restock_items' => $restockItems,
                'gateway_refund_ref' => $gatewayRefundRef,
                'gateway_response' => $gatewayResponse,
                'initiated_by' => $admin?->id,
                'completed_at' => now(),
            ]);

            // Update Payment Status (refunded vs partially_refunded)
            $totalRefunded = $payment->refunds()->whereIn('status', ['completed', 'processing'])->sum('amount');
            $newPaymentStatus = (abs($totalRefunded - (float) $payment->amount) < 0.05)
                ? 'refunded'
                : 'partially_refunded';

            $payment->update([
                'status' => $newPaymentStatus,
            ]);

            // Update Order Refund Status & Totals
            if ($order) {
                $order->update([
                    'refund_status' => 'completed',
                    'refund_amount' => $totalRefunded,
                    'refund_reference' => $gatewayRefundRef ?: ($order->refund_reference ?: "REF-MANUAL-{$createdRefund->id}"),
                    'refund_reason' => $reason,
                    'refunded_at' => now(),
                ]);

                // Explicit Restock Control: ONLY restock if admin explicitly checked restock_items
                if ($restockItems && $order->items) {
                    $order->loadMissing('items');
                    foreach ($order->items as $item) {
                        $qty = (int) $item->quantity;
                        $userId = $admin?->id ?? $order->user_id ?? 1;

                        if ($item->product_variant_id) {
                            $variant = ProductVariant::find($item->product_variant_id);
                            if ($variant) {
                                $oldStock = (int) $variant->stock;
                                $newStock = $oldStock + $qty;
                                $variant->update(['stock' => $newStock]);

                                $product = Product::find($item->product_id);
                                if ($product) {
                                    $product->syncAggregateStockAndPrice();
                                }

                                ProductStockAdjustment::create([
                                    'product_id' => $item->product_id,
                                    'product_variant_id' => $item->product_variant_id,
                                    'user_id' => $userId,
                                    'old_stock' => $oldStock,
                                    'new_stock' => $newStock,
                                    'adjustment' => $qty,
                                    'reason' => "Refund Restock (Order #{$order->id})",
                                ]);
                            }
                        } else {
                            $product = Product::find($item->product_id);
                            if ($product) {
                                $oldStock = (int) $product->stock;
                                $newStock = $oldStock + $qty;
                                $product->update(['stock' => $newStock]);

                                ProductStockAdjustment::create([
                                    'product_id' => $item->product_id,
                                    'product_variant_id' => null,
                                    'user_id' => $userId,
                                    'old_stock' => $oldStock,
                                    'new_stock' => $newStock,
                                    'adjustment' => $qty,
                                    'reason' => "Refund Restock (Order #{$order->id})",
                                ]);
                            }
                        }
                    }
                }
            }

            return $createdRefund;
        });

        // 3. Farmer Notification
        if ($order && $order->user_id) {
            AdminNotification::notify(
                'order.refund_issued',
                'Refund Processed',
                "A refund of ৳" . number_format($amount, 2) . " has been issued for your order #{$order->id}." . ($reason ? " Reason: {$reason}" : ''),
                [
                    'order_id' => $order->id,
                    'payment_id' => $payment->id,
                    'refund_id' => $refund->id,
                    'amount' => $amount,
                ],
                $order->user_id
            );
        }

        // 4. Audit Trail Logging
        ActivityLog::log(
            'order.refund_issued',
            $payment,
            [
                'order_id' => $payment->order_id,
                'payment_id' => $payment->id,
                'refund_id' => $refund->id,
                'amount' => $amount,
                'reason' => $reason,
                'restock_items' => $restockItems,
                'status' => $refund->status,
                'gateway_ref' => $gatewayRefundRef,
            ],
            $admin
        );

        return $this->successResponse(
            new PaymentResource($payment->fresh(['user', 'order.items.product', 'refunds.initiator'])),
            "Refund of ৳" . number_format($amount, 2) . " processed successfully."
        );
    }
}
