<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\AdminNotification;
use App\Models\FarmLedgerEntry;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SSLCommerzService
{
    protected string $storeId;
    protected string $storePassword;
    protected bool $isSandbox;
    protected string $baseUrl;

    public function __construct()
    {
        $this->storeId = config('sslcommerz.store_id', 'farmlink_sandbox');
        $this->storePassword = config('sslcommerz.store_password', 'farmlink_sandbox_pass');
        $this->isSandbox = (bool) config('sslcommerz.is_sandbox', true);
        $this->baseUrl = $this->isSandbox
            ? config('sslcommerz.sandbox_url', 'https://sandbox.sslcommerz.com')
            : config('sslcommerz.live_url', 'https://securepay.sslcommerz.com');
    }

    /**
     * Initiate payment session with SSLCommerz.
     */
    public function initiatePayment(Order $order): array
    {
        $tranId = 'FL-ORD-' . $order->id . '-' . strtoupper(substr(uniqid(), -6));
        $backendUrl = rtrim(config('sslcommerz.backend_url', url('/')), '/');

        // Prepare callback URLs
        $successUrl = "{$backendUrl}/api/v1/payments/sslcommerz/return?status=success";
        $failUrl = "{$backendUrl}/api/v1/payments/sslcommerz/return?status=fail";
        $cancelUrl = "{$backendUrl}/api/v1/payments/sslcommerz/return?status=cancel";
        $ipnUrl = "{$backendUrl}/api/v1/payments/sslcommerz/ipn";

        $user = $order->user;
        $order->loadMissing(['items.product', 'items.variant']);

        $productNames = $order->items->map(function ($item) {
            return $item->product?->name ?? 'Supply Item';
        })->take(3)->implode(', ');

        $postData = [
            'store_id' => $this->storeId,
            'store_passwd' => $this->storePassword,
            'total_amount' => (string) round((float) $order->total, 2),
            'currency' => 'BDT',
            'tran_id' => $tranId,
            'success_url' => $successUrl,
            'fail_url' => $failUrl,
            'cancel_url' => $cancelUrl,
            'ipn_url' => $ipnUrl,

            // Customer Information
            'cus_name' => $order->recipient_name ?: ($user?->name ?? 'FarmLink Customer'),
            'cus_email' => $user?->email ?: 'farmer@farmlink.local',
            'cus_add1' => $order->delivery_address ?: ($order->district ? "{$order->district} District" : 'Bangladesh'),
            'cus_city' => $order->district ?: 'Dhaka',
            'cus_country' => 'Bangladesh',
            'cus_phone' => $order->recipient_phone ?: ($user?->phone ?? '01700000000'),

            // Order & Shipment Information
            'shipping_method' => 'NO',
            'product_name' => substr($productNames ?: 'Aquaculture Inputs', 0, 100),
            'product_category' => 'Aquaculture Supplies',
            'product_profile' => 'general',
            'value_a' => (string) $order->id,
        ];

        // Save generated transaction ID on order
        $order->update([
            'gateway_transaction_id' => $tranId,
            'payment_status' => 'pending',
        ]);

        $sessionEndpoint = $this->baseUrl . config('sslcommerz.session_endpoint', '/gwprocess/v4/api.php');

        try {
            $response = Http::asForm()->timeout(15)->post($sessionEndpoint, $postData);
            $result = $response->json();

            if (! empty($result['GatewayPageURL']) && ($result['status'] ?? '') === 'SUCCESS') {
                return [
                    'success' => true,
                    'gateway_url' => $result['GatewayPageURL'],
                    'tran_id' => $tranId,
                    'session_key' => $result['sessionkey'] ?? null,
                ];
            }

            // In sandbox/testing fallback mode if gateway rejected with credentials error
            if ($this->isSandbox && empty($result['GatewayPageURL'])) {
                // If mocked or dummy credentials in sandbox, generate test checkout URL
                $mockGatewayUrl = "{$this->baseUrl}/EasyCheckout/testbox?sessionkey=TEST_" . md5($tranId);
                return [
                    'success' => true,
                    'gateway_url' => $mockGatewayUrl,
                    'tran_id' => $tranId,
                    'mock' => true,
                    'message' => $result['failedreason'] ?? 'Sandbox Test Session',
                ];
            }

            Log::error('SSLCommerz session creation failed', ['response' => $result, 'tran_id' => $tranId]);

            return [
                'success' => false,
                'message' => $result['failedreason'] ?? 'Failed to initiate payment gateway session.',
                'tran_id' => $tranId,
            ];
        } catch (Exception $e) {
            Log::error('SSLCommerz session error: ' . $e->getMessage());

            if ($this->isSandbox) {
                // Provide sandbox simulated checkout URL so development and testing proceed smoothly
                return [
                    'success' => true,
                    'gateway_url' => "{$this->baseUrl}/EasyCheckout/testbox?sessionkey=TEST_" . md5($tranId),
                    'tran_id' => $tranId,
                    'mock' => true,
                ];
            }

            return [
                'success' => false,
                'message' => 'Connection to payment gateway failed. Please try again.',
                'tran_id' => $tranId,
            ];
        }
    }

    /**
     * Query SSLCommerz live status by merchant transaction ID (tran_id).
     */
    public function queryTransaction(string $tranId): array
    {
        $endpoint = $this->baseUrl . config('sslcommerz.query_endpoint', '/validator/api/merchantTransIDvalidationAPI.php');

        try {
            $response = Http::timeout(15)->get($endpoint, [
                'tran_id' => $tranId,
                'store_id' => $this->storeId,
                'store_passwd' => $this->storePassword,
                'format' => 'json',
            ]);

            if (! $response->successful()) {
                Log::error("SSLCommerz queryTransaction HTTP error: {$response->status()}");
                return ['status' => 'GATEWAY_ERROR', 'message' => "HTTP {$response->status()}"];
            }

            $json = $response->json();
            if (! is_array($json)) {
                return ['status' => 'NO_TRANSACTION'];
            }

            // In SSLCommerz merchantTransIDvalidationAPI, data often lives under element array
            if (! empty($json['element']) && is_array($json['element'])) {
                $primary = $json['element'][0] ?? [];
                if (is_array($primary)) {
                    return array_merge($json, $primary);
                }
            }

            return $json;
        } catch (Exception $e) {
            if (app()->bound('sentry')) {
                \Sentry\captureException($e);
            }
            Log::error("SSLCommerz queryTransaction API failed for tran_id: {$tranId} - " . $e->getMessage());
            return ['status' => 'GATEWAY_UNREACHABLE', 'error' => $e->getMessage()];
        }
    }

    /**
     * Call SSLCommerz Server Validation API to independently verify payment.
     */
    public function validateTransaction(string $valId): array
    {
        $validationEndpoint = $this->baseUrl . config('sslcommerz.validation_endpoint', '/validator/api/validationserverAPI.php');

        try {
            $response = Http::timeout(15)->get($validationEndpoint, [
                'val_id' => $valId,
                'store_id' => $this->storeId,
                'store_passwd' => $this->storePassword,
                'format' => 'json',
            ]);

            return $response->json() ?? [];
        } catch (Exception $e) {
            Log::error('SSLCommerz validation API request failed: ' . $e->getMessage());
            return ['status' => 'FAILED', 'error' => $e->getMessage()];
        }
    }

    /**
     * Process validated payment from IPN webhook or reconciliation command.
     * Enforces row locking, idempotency, tamper detection, and oversell protection.
     */
    public function processValidatedPayment(array $valData, string $rawValId): array
    {
        $tranId = $valData['tran_id'] ?? null;
        if (! $tranId) {
            return ['success' => false, 'message' => 'Transaction ID missing from validation payload.'];
        }

        // Find the pending order
        $order = Order::where('gateway_transaction_id', $tranId)->first();
        if (! $order && ! empty($valData['value_a'])) {
            $order = Order::find($valData['value_a']);
        }

        if (! $order) {
            Log::warning("SSLCommerz IPN received for non-existent order tran_id: {$tranId}");
            return ['success' => false, 'message' => "Order not found for transaction: {$tranId}"];
        }

        // Early check outside lock
        if ($order->payment_status === 'paid') {
            return ['success' => true, 'order' => $order, 'already_processed' => true, 'message' => 'Order is already marked as paid.'];
        }

        // Verify currency and amount (Tamper check)
        $gatewayAmount = (float) ($valData['amount'] ?? 0);
        $orderAmount = (float) $order->total;
        $currency = strtoupper($valData['currency'] ?? 'BDT');

        if ($currency !== 'BDT') {
            Log::critical("SSLCommerz currency tamper mismatch for Order #{$order->id}: Expected BDT, got {$currency}");
            $order->update([
                'status' => 'paid_needs_review',
                'notes' => trim(($order->notes ? $order->notes . "\n" : '') . "[Alert: Currency mismatch - expected BDT, received {$currency}]"),
            ]);
            app(\App\Services\SystemAlertService::class)->sendCriticalAlert(
                "Payment Currency Mismatch for Order #{$order->id}",
                "Expected BDT, received {$currency} from gateway. Flagged for review.",
                ['order_id' => $order->id, 'currency' => $currency, 'tran_id' => $tranId]
            );
            return ['success' => false, 'message' => 'Currency mismatch between order and gateway payment'];
        }

        if (abs($orderAmount - $gatewayAmount) > 0.05) {
            Log::critical("SSLCommerz amount tamper mismatch for Order #{$order->id}: Order total {$orderAmount}, gateway paid {$gatewayAmount}");
            $order->update([
                'status' => 'paid_needs_review',
                'notes' => trim(($order->notes ? $order->notes . "\n" : '') . "[Alert: Amount mismatch - order total ৳{$orderAmount}, paid ৳{$gatewayAmount}]"),
            ]);
            app(\App\Services\SystemAlertService::class)->sendCriticalAlert(
                "Payment Amount Tamper Mismatch for Order #{$order->id}",
                "Order total ৳{$orderAmount} does not match gateway payment amount ৳{$gatewayAmount}. Flagged for review.",
                ['order_id' => $order->id, 'order_total' => $orderAmount, 'paid_amount' => $gatewayAmount, 'tran_id' => $tranId]
            );
            return ['success' => false, 'message' => 'Paid amount does not match order total'];
        }

        // Atomic DB transaction with row lock to prevent race conditions & double decrements
        return DB::transaction(function () use ($order, $valData, $rawValId, $tranId) {
            $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->first();

            if (! $lockedOrder) {
                return ['success' => false, 'message' => 'Order not found under lock.'];
            }

            // Strict Idempotency Check inside lock
            if ($lockedOrder->payment_status === 'paid') {
                return [
                    'success' => true,
                    'order' => $lockedOrder,
                    'already_processed' => true,
                    'message' => 'Order is already marked as paid.',
                ];
            }

            $lockedOrder->loadMissing(['items.product', 'items.variant']);
            $hasStockShortage = false;

            // 1. Inspect inventory availability first (prevent silent oversell)
            foreach ($lockedOrder->items as $item) {
                if ($item->product_variant_id) {
                    $variant = ProductVariant::where('id', $item->product_variant_id)->lockForUpdate()->first();
                    if (! $variant || $variant->stock < $item->quantity) {
                        $hasStockShortage = true;
                        break;
                    }
                } else {
                    $product = Product::where('id', $item->product_id)->lockForUpdate()->first();
                    if (! $product || $product->stock < $item->quantity) {
                        $hasStockShortage = true;
                        break;
                    }
                }
            }

            // If stock depleted before late payment confirmed: mark "paid_needs_review", alert admin
            if ($hasStockShortage) {
                $lockedOrder->update([
                    'status' => 'paid_needs_review',
                    'payment_status' => 'paid',
                    'paid_at' => now(),
                    'gateway_transaction_id' => $tranId,
                    'gateway_payment_details' => $valData,
                    'notes' => trim(($lockedOrder->notes ? $lockedOrder->notes . "\n" : '') . "[Inventory Alert: Stock depleted prior to payment confirmation. Pending admin decision.]"),
                ]);

                \App\Models\Payment::updateOrCreate(
                    ['order_id' => $lockedOrder->id],
                    [
                        'user_id' => $lockedOrder->user_id,
                        'method' => 'sslcommerz',
                        'amount' => $lockedOrder->total,
                        'status' => 'success',
                        'paid_at' => now(),
                        'gateway_transaction_id' => $tranId,
                        'gateway_response' => $valData,
                        'notes' => 'Paid with stock shortage - requires admin fulfillment or refund choice',
                    ]
                );

                AdminNotification::notify(
                    'order.needs_review',
                    "Order #{$lockedOrder->id} Paid - Stock Insufficient",
                    "Order #{$lockedOrder->id} was paid online (৳" . number_format($lockedOrder->total, 2) . "), but stock was depleted. Review to fulfill or refund.",
                    ['order_id' => $lockedOrder->id, 'tran_id' => $tranId],
                    null
                );

                ActivityLog::log(
                    'order_paid_stock_depleted',
                    $lockedOrder,
                    ['reason' => 'Late payment confirmation after stock depletion. Marked paid_needs_review.'],
                    $lockedOrder->user_id
                );

                return [
                    'success' => true,
                    'order' => $lockedOrder,
                    'stock_shortage' => true,
                    'needs_review' => true,
                ];
            }

            // 2. Normal path: Decrement stock atomically
            foreach ($lockedOrder->items as $item) {
                if ($item->product_variant_id) {
                    ProductVariant::where('id', $item->product_variant_id)->decrement('stock', $item->quantity);
                    Product::where('id', $item->product_id)->decrement('stock', $item->quantity);
                } else {
                    Product::where('id', $item->product_id)->decrement('stock', $item->quantity);
                }
            }

            $lockedOrder->update([
                'status' => 'pending',
                'payment_status' => 'paid',
                'paid_at' => now(),
                'gateway_transaction_id' => $tranId,
                'gateway_payment_details' => $valData,
            ]);

            \App\Models\Payment::updateOrCreate(
                ['order_id' => $lockedOrder->id],
                [
                    'user_id' => $lockedOrder->user_id,
                    'method' => 'sslcommerz',
                    'amount' => $lockedOrder->total,
                    'status' => 'success',
                    'paid_at' => now(),
                    'gateway_transaction_id' => $tranId,
                    'gateway_response' => $valData,
                ]
            );

            if ($lockedOrder->farm_id) {
                FarmLedgerEntry::recordOrderExpense($lockedOrder);
            }

            AdminNotification::notify(
                'payment',
                'Online Payment Received',
                "Order #{$lockedOrder->id} paid ৳" . number_format($lockedOrder->total, 2) . " via SSLCommerz (Ref: {$tranId}).",
                ['order_id' => $lockedOrder->id, 'val_id' => $rawValId, 'tran_id' => $tranId],
                null
            );

            ActivityLog::log(
                'sslcommerz_payment_verified',
                $lockedOrder,
                ['tran_id' => $tranId, 'amount' => $lockedOrder->total],
                $lockedOrder->user_id
            );

            return [
                'success' => true,
                'order' => $lockedOrder,
                'stock_shortage' => false,
            ];
        });
    }

    /**
     * Trigger SSLCommerz Refund API for an order paid online.
     */
    public function initiateRefund(Order $order, ?float $amount = null, ?string $reason = null): array
    {
        if ($order->payment_mode !== 'sslcommerz') {
            return [
                'success' => false,
                'message' => 'Order was not paid via SSLCommerz.',
            ];
        }

        if ($order->refund_status === 'completed') {
            return [
                'success' => true,
                'message' => 'Refund has already been completed for this order.',
            ];
        }

        $refundAmount = $amount !== null ? round($amount, 2) : round((float) $order->total, 2);
        $bankTranId = $order->gateway_payment_details['bank_tran_id'] ?? null;
        $refeId = 'REF-' . $order->id . '-' . time();

        $refundEndpoint = $this->baseUrl . config('sslcommerz.refund_endpoint', '/validator/api/merchantTransIDvalidationAPI.php');

        $order->update([
            'refund_status' => 'processing',
            'refund_amount' => $refundAmount,
            'refund_reason' => $reason ?: 'Order cancelled/refunded by administrator',
        ]);

        try {
            $params = [
                'refund_amount' => (string) $refundAmount,
                'refund_remarks' => substr($reason ?: "Refund for Order #{$order->id}", 0, 100),
                'bank_tran_id' => $bankTranId ?: ($order->gateway_transaction_id ?? ''),
                'refe_id' => $refeId,
                'store_id' => $this->storeId,
                'store_passwd' => $this->storePassword,
                'format' => 'json',
            ];

            $response = Http::timeout(15)->get($refundEndpoint, $params);
            $res = $response->json();

            $status = strtolower($res['status'] ?? '');
            if ($status === 'success') {
                $refundRefId = $res['refund_ref_id'] ?? $refeId;
                $order->update([
                    'refund_status' => 'completed',
                    'refund_reference' => $refundRefId,
                    'refunded_at' => now(),
                ]);

                ActivityLog::log(
                    'sslcommerz_refund_completed',
                    $order,
                    [
                        'amount' => $refundAmount,
                        'refund_reference' => $refundRefId,
                        'reason' => $reason,
                    ],
                    auth()->id()
                );

                return [
                    'success' => true,
                    'refund_reference' => $refundRefId,
                    'message' => 'Refund completed successfully through SSLCommerz.',
                ];
            }

            // In sandbox simulation mode:
            if ($this->isSandbox) {
                $mockRefId = 'MOCK-REF-' . uniqid();
                $order->update([
                    'refund_status' => 'completed',
                    'refund_reference' => $mockRefId,
                    'refunded_at' => now(),
                ]);

                ActivityLog::log(
                    'sslcommerz_refund_completed',
                    $order,
                    [
                        'amount' => $refundAmount,
                        'refund_reference' => $mockRefId,
                        'reason' => $reason,
                        'simulated' => true,
                    ],
                    auth()->id()
                );

                return [
                    'success' => true,
                    'refund_reference' => $mockRefId,
                    'message' => 'Sandbox simulated refund completed successfully.',
                ];
            }

            $errorMsg = $res['errorreason'] ?? 'SSLCommerz refund request was rejected.';
            $order->update(['refund_status' => 'failed']);

            Log::error("SSLCommerz refund rejected for Order #{$order->id}", ['response' => $res]);

            return [
                'success' => false,
                'message' => $errorMsg,
            ];
        } catch (Exception $e) {
            Log::error("SSLCommerz refund exception for Order #{$order->id}: " . $e->getMessage());
            $order->update(['refund_status' => 'failed']);

            return [
                'success' => false,
                'message' => 'Payment gateway refund service encountered an error.',
            ];
        }
    }
}
