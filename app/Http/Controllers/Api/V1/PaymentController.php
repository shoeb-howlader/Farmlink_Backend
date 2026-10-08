<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\SSLCommerzService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function __construct(
        protected SSLCommerzService $sslCommerzService
    ) {}

    /**
     * IPN (Instant Payment Notification) Webhook Endpoint.
     * Server-to-server asynchronous callback from SSLCommerz.
     */
    public function handleIpn(Request $request): JsonResponse
    {
        $payload = $request->all();
        Log::info('SSLCommerz IPN received', ['payload' => $payload]);

        $valId = $request->input('val_id');
        $tranId = $request->input('tran_id');

        // Reject missing or malformed val_id
        if (empty($valId) || ! is_string($valId) || strlen($valId) < 3 || strlen($valId) > 150) {
            Log::warning('SSLCommerz IPN rejected: missing or malformed val_id', [
                'ip' => $request->ip(),
                'val_id_type' => gettype($valId),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid or missing validation identifier.',
            ], 400);
        }

        // Validate tran_id if provided
        if ($tranId !== null && (! is_string($tranId) || strlen($tranId) > 150)) {
            Log::warning('SSLCommerz IPN rejected: malformed tran_id', [
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid transaction identifier.',
            ], 400);
        }

        try {
            // Call SSLCommerz server-to-server Validation API
            $valData = $this->sslCommerzService->validateTransaction($valId);
            $valStatus = strtoupper($valData['status'] ?? '');

            if ($valStatus !== 'VALID' && $valStatus !== 'VALIDATED') {
                Log::error('SSLCommerz IPN validation failed via Validation API', [
                    'val_id' => $valId,
                    'response' => $valData,
                ]);

                $tranId = $valData['tran_id'] ?? $request->input('tran_id');
                if ($tranId) {
                    $order = Order::where('gateway_transaction_id', $tranId)->first();
                    if ($order && in_array($order->status, ['pending_payment', 'pending'])) {
                        $order->update([
                            'status' => 'payment_failed',
                            'payment_status' => 'failed',
                        ]);

                        \App\Models\Payment::where('order_id', $order->id)->update([
                            'status' => 'failed',
                        ]);

                        app(\App\Services\PaymentFailureEscalationService::class)->checkAndEscalate($order->user_id);
                    }
                }

                return response()->json([
                    'success' => false,
                    'message' => 'Transaction could not be verified by SSLCommerz validation API.',
                    'gateway_status' => $valStatus,
                ], 422);
            }

            // Process verified transaction, decrement deferred stock, mark paid
            $result = $this->sslCommerzService->processValidatedPayment($valData, $valId);

            if (! $result['success']) {
                return response()->json($result, 422);
            }

            return response()->json([
                'success' => true,
                'status' => 'success',
                'message' => 'IPN validated and processed successfully.',
                'order_id' => $result['order']->id ?? null,
            ]);
        } catch (\Throwable $e) {
            if (app()->bound('sentry')) {
                \Sentry\captureException($e);
            }

            Log::error('SSLCommerz IPN unhandled exception: ' . $e->getMessage(), [
                'val_id' => $valId,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while processing the IPN.',
            ], 500);
        }
    }

    /**
     * Customer Browser Return Redirect.
     * SSLCommerz redirects the customer's browser back here on Success, Fail, or Cancel.
     * NOTE: We NEVER mark the order paid based solely on browser redirect!
     */
    public function handleReturn(Request $request): RedirectResponse
    {
        $status = $request->input('status', 'success');
        $tranId = $request->input('tran_id');
        $valId = $request->input('val_id');

        Log::info("SSLCommerz Browser Redirect: status={$status}, tran_id={$tranId}, val_id={$valId}");

        // If customer cancelled or payment failed
        if (in_array(strtolower($status), ['fail', 'failed', 'cancel', 'cancelled'])) {
            if ($tranId) {
                $order = Order::where('gateway_transaction_id', $tranId)->first();
                if ($order && in_array($order->status, ['pending_payment', 'pending'])) {
                    $order->update([
                        'status' => 'payment_failed',
                        'payment_status' => 'failed',
                    ]);

                    \App\Models\Payment::where('order_id', $order->id)->update([
                        'status' => 'failed',
                    ]);

                    app(\App\Services\PaymentFailureEscalationService::class)->checkAndEscalate($order->user_id);
                }
            }
        }

        // If val_id is provided and status is success, we can trigger validation eagerly
        if (! empty($valId) && in_array(strtolower($status), ['success', 'valid', 'validated'])) {
            try {
                $valData = $this->sslCommerzService->validateTransaction($valId);
                $valStatus = strtoupper($valData['status'] ?? '');
                if ($valStatus === 'VALID' || $valStatus === 'VALIDATED') {
                    $this->sslCommerzService->processValidatedPayment($valData, $valId);
                }
            } catch (\Throwable $e) {
                Log::error('Eager validation on browser return error: ' . $e->getMessage());
            }
        }

        $frontendUrl = rtrim(config('sslcommerz.frontend_url', 'http://localhost:3000'), '/');
        $redirectUrl = "{$frontendUrl}/orders/payment/confirming?status={$status}&tran_id={$tranId}";

        return redirect()->away($redirectUrl);
    }

    /**
     * Check current status of pending online payment order (polled by confirming screen).
     */
    public function checkStatus(Request $request, int $orderId): JsonResponse
    {
        $order = Order::findOrFail($orderId);

        // Security check: ensure requesting user owns the order or is admin
        if ($request->user() && $request->user()->id !== $order->user_id && ! $request->user()->hasRole('admin')) {
            abort(403, 'Unauthorized');
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $order->id,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'payment_mode' => $order->payment_mode,
                'gateway_transaction_id' => $order->gateway_transaction_id,
                'paid_at' => $order->paid_at?->toISOString(),
                'total' => (float) $order->total,
            ],
        ]);
    }
}
