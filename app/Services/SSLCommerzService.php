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
        $endpoint = $this->baseUrl . config('sslcommerz.refund_endpoint', '/validator/api/merchantTransIDvalidationAPI.php');

        try {
            $response = Http::timeout(15)->get($endpoint, [
                'tran_id' => $tranId,
                'store_id' => $this->storeId,
                'store_passwd' => $this->storePassword,
                'format' => 'json',
            ]);

            $json = $response->json();
            return is_array($json) ? $json : [];
        } catch (Exception $e) {
            Log::error("SSLCommerz queryTransaction API failed for tran_id: {$tranId} - " . $e->getMessage());
            return ['status' => 'FAILED', 'error' => $e->getMessage()];
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
     * Process validated payment from IPN webhook.
     * Decrements deferred stock, marks order paid, records ledger entry.
     */
    public function processValidatedPayment(array $valData, string $rawValId): array
    {
        $tranId = $valData['tran_id'] ?? null;
        if (! $tranId) {
            return ['success' => false, 'message' => 'Transaction ID missing from validation payload.'];
        }

        // Find the pending order
        $order = Order::where('gateway_transaction_id', $tranId)->first();
        if (! $order) {
            // Also check by ID if value_a was returned
            if (! empty($valData['value_a'])) {
                $order = Order::find($valData['value_a']);
            }
        }

        if (! $order) {
            Log::warning("SSLCommerz IPN received for non-existent order tran_id: {$tranId}");
            return ['success' => false, 'message' => "Order not found for transaction: {$tranId}"];
        }

        // Idempotency: if already paid, return early
        if ($order->payment_status === 'paid') {
            return ['success' => true, 'order' => $order, 'message' => 'Order is already marked as paid.'];
        }

        // Verify currency and amount
        $gatewayAmount = (float) ($valData['amount'] ?? 0);
        $orderAmount = (float) $order->total;
        $currency = strtoupper($valData['currency'] ?? 'BDT');

        if ($currency !== 'BDT') {
            Log::error("SSLCommerz currency mismatch for Order #{$order->id}: Expected BDT, got {$currency}");
            return ['success' => false, 'message' => 'Currency mismatch'];
        }

        if (abs($orderAmount - $gatewayAmount) > 0.05) {
            Log::error("SSLCommerz amount mismatch for Order #{$order->id}: Order total {$orderAmount}, gateway paid {$gatewayAmount}");
            return ['success' => false, 'message' => 'Paid amount does not match order total'];
        }

        // Atomic DB transaction to decrement stock and activate order
        return DB::transaction(function () use ($order, $valData, $rawValId, $tranId) {
            $order->loadMissing(['items.product', 'items.variant']);
            $hasStockShortage = false;

            // Deferred Stock Decrement Logic
            foreach ($order->items as $item) {
                if ($item->product_variant_id) {
                    $variant = ProductVariant::where('id', $item->product_variant_id)->lockForUpdate()->first();
                    if ($variant) {
                        $updated = ProductVariant::where('id', $variant->id)
                            ->where('stock', '>=', $item->quantity)
                            ->decrement('stock', $item->quantity);

                        if (! $updated) {
                            $hasStockShortage = true;
                            Log::warning("Insufficient stock on post-payment decrement for Order #{$order->id}, Variant #{$variant->id}");
                        }

                        Product::where('id', $item->product_id)->decrement('stock', $item->quantity);
                    }
                } else {
                    $product = Product::where('id', $item->product_id)->lockForUpdate()->first();
                    if ($product) {
                        $updated = Product::where('id', $product->id)
                            ->where('stock', '>=', $item->quantity)
                            ->decrement('stock', $item->quantity);

                        if (! $updated) {
                            $hasStockShortage = true;
                            Log::warning("Insufficient stock on post-payment decrement for Order #{$order->id}, Product #{$product->id}");
                        }
                    }
                }
            }

            // Move order from pending_payment to normal pending/confirmed status
            $newStatus = $hasStockShortage ? 'pending' : 'pending';

            $order->update([
                'status' => $newStatus,
                'payment_status' => 'paid',
                'paid_at' => now(),
                'gateway_transaction_id' => $tranId,
                'gateway_payment_details' => $valData,
            ]);

            // Update or create associated Payment record to success
            \App\Models\Payment::updateOrCreate(
                ['order_id' => $order->id],
                [
                    'user_id' => $order->user_id,
                    'method' => 'sslcommerz',
                    'amount' => $order->total,
                    'status' => 'success',
                    'paid_at' => now(),
                    'gateway_transaction_id' => $tranId,
                    'gateway_response' => $valData,
                ]
            );

            // Record farm ledger entry now that payment is confirmed
            if ($order->farm_id) {
                FarmLedgerEntry::recordOrderExpense($order);
            }

            // Notifications
            AdminNotification::notify(
                'payment',
                'Online Payment Received',
                "Order #{$order->id} paid ৳" . number_format($order->total, 2) . " via SSLCommerz (Ref: {$tranId})." . ($hasStockShortage ? ' NOTE: Low stock flag requires inventory review!' : ''),
                [
                    'order_id' => $order->id,
                    'val_id' => $rawValId,
                    'tran_id' => $tranId,
                    'stock_warning' => $hasStockShortage,
                ]
            );

            ActivityLog::log(
                'sslcommerz_payment_verified',
                $order,
                [
                    'tran_id' => $tranId,
                    'amount' => $order->total,
                    'stock_warning' => $hasStockShortage,
                ],
                $order->user_id
            );

            return [
                'success' => true,
                'order' => $order,
                'stock_shortage' => $hasStockShortage,
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
