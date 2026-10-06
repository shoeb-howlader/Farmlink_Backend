<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Api\V1\UpdateOrderStatusRequest;
use App\Http\Resources\V1\OrderResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class OrderController extends ApiController
{
    /**
     * Display a paginated listing of all orders, filterable by status (Admin only).
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAdmin', Order::class);

        $query = Order::with(['items.product.images', 'items.product.variants', 'items.variant', 'user.roles', 'payment'])->latest();

        // Filter by status (single, comma-separated, or workflow tab presets)
        if ($request->filled('status') && $request->query('status') !== 'all') {
            $statusInput = $request->query('status');
            if ($statusInput === 'in_progress') {
                $query->whereIn('status', ['pending', 'confirmed', 'dispatched', 'pending_payment']);
            } elseif ($statusInput === 'completed') {
                $query->where('status', 'delivered');
            } elseif ($statusInput === 'cancelled_returned') {
                $query->whereIn('status', ['cancelled', 'rejected', 'returned']);
            } elseif (str_contains($statusInput, ',')) {
                $query->whereIn('status', explode(',', $statusInput));
            } else {
                $query->where('status', $statusInput);
            }
        }

        // Filter by user_id
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->query('user_id'));
        }

        // Filter by date range (from / to)
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->query('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->query('date_to'));
        }

        // Fraud & Risk Filters
        if ($request->has('flagged')) {
            $query->where('is_flagged', $request->boolean('flagged'));
        }

        if ($request->filled('risk_level')) {
            $query->where('risk_level', $request->query('risk_level'));
        }

        if ($request->filled('verification_status')) {
            $query->where('verification_status', $request->query('verification_status'));
        }

        // Search across ID, farmer name, or phone
        if ($request->filled('search')) {
            $search = trim($request->query('search'));
            $query->where(function ($q) use ($search) {
                $q->where('id', $search)
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            });
        }

        $perPage = max(1, min((int) $request->query('per_page', 15), 100));
        $paginated = $query->paginate($perPage);

        // Attach 24h recent attempt count per farmer to give admins instant context
        $userIds = collect($paginated->items())->pluck('user_id')->filter()->unique();
        if ($userIds->isNotEmpty()) {
            $recentCounts = Order::whereIn('user_id', $userIds)
                ->where('created_at', '>=', now()->subHours(24))
                ->groupBy('user_id')
                ->selectRaw('user_id, count(*) as count')
                ->pluck('count', 'user_id');

            foreach ($paginated->items() as $item) {
                $item->customer_24h_orders_count = (int) ($recentCounts[$item->user_id] ?? 1);
            }
        }

        // Fast aggregated counts for workflow tabs
        $statusCounts = Order::select('status', \Illuminate\Support\Facades\DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->all();

        $tabCounts = [
            'in_progress' => (int) (($statusCounts['pending'] ?? 0)
                + ($statusCounts['confirmed'] ?? 0)
                + ($statusCounts['dispatched'] ?? 0)
                + ($statusCounts['pending_payment'] ?? 0)),
            'flagged' => (int) Order::where('is_flagged', true)->count(),
            'completed' => (int) ($statusCounts['delivered'] ?? 0),
            'cancelled' => (int) (($statusCounts['cancelled'] ?? 0)
                + ($statusCounts['rejected'] ?? 0)
                + ($statusCounts['returned'] ?? 0)),
            'all' => (int) array_sum($statusCounts),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Admin orders retrieved successfully',
            'data' => OrderResource::collection($paginated->items()),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'tab_counts' => $tabCounts,
            ],
        ]);
    }

    /**
     * Display the specified order with items, products, and farmer details (Admin only).
     */
    public function show(Request $request, Order $order): JsonResponse
    {
        Gate::authorize('viewAdmin', Order::class);

        if ($order->user_id) {
            $recentAttempts = Order::where('user_id', $order->user_id)
                ->where('id', '!=', $order->id)
                ->where('created_at', '>=', now()->subHours(48))
                ->with('payment')
                ->latest()
                ->limit(6)
                ->get();

            $order->setRelation('recent_attempts', $recentAttempts);
        }

        return $this->successResponse(
            new OrderResource($order->load(['items.product', 'user', 'payment'])),
            'Order details retrieved successfully'
        );
    }

    /**
     * Update the status of the specified order.
     */
    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): JsonResponse
    {
        Gate::authorize('updateStatus', $order);

        $newStatus = $request->validated('status');
        $oldStatus = $order->status;

        \Illuminate\Support\Facades\DB::transaction(function () use ($order, $newStatus, $oldStatus) {
            // Restore inventory only if the order was not pending_payment (since pending_payment deferred stock decrement)
            if (in_array($newStatus, ['cancelled', 'rejected', 'returned']) && ! in_array($oldStatus, ['cancelled', 'rejected', 'returned', 'pending_payment'])) {
                $order->loadMissing('items');
                foreach ($order->items as $item) {
                    \App\Models\Product::where('id', $item->product_id)->increment('stock', $item->quantity);

                    if ($item->product_variant_id) {
                        \App\Models\ProductVariant::where('id', $item->product_variant_id)->increment('stock', $item->quantity);
                    } else {
                        $defaultVar = \App\Models\ProductVariant::where('product_id', $item->product_id)->where('is_default', true)->first()
                            ?? \App\Models\ProductVariant::where('product_id', $item->product_id)->first();
                        if ($defaultVar) {
                            $defaultVar->increment('stock', $item->quantity);
                        }
                    }
                }

                // If order is cancelled, rejected, or returned, void corresponding farm ledger entry
                \App\Models\FarmLedgerEntry::where('source', 'system_order')
                    ->where('source_reference_id', $order->id)
                    ->whereNull('voided_at')
                    ->update([
                        'voided_at' => now(),
                        'void_reason' => "Order #{$order->invoice_number} was {$newStatus}",
                    ]);
            }

            $order->update([
                'status' => $newStatus,
            ]);

            // Auto-settle payment for Cash on Delivery / Cash orders when marked as delivered
            if ($newStatus === 'delivered' && in_array($order->payment_mode, ['cod', 'cash']) && $order->payment_status !== 'paid') {
                $order->update([
                    'payment_status' => 'paid',
                    'paid_at' => now(),
                ]);

                $payment = $order->payment ?? \App\Models\Payment::firstOrCreate(
                    ['order_id' => $order->id],
                    [
                        'user_id' => $order->user_id,
                        'method' => $order->payment_mode,
                        'amount' => $order->total,
                    ]
                );

                $paymentNotes = $payment->notes
                    ? $payment->notes . ' | Cash collected upon delivery'
                    : 'Cash collected upon delivery';

                $payment->update([
                    'status' => 'success',
                    'paid_at' => now(),
                    'notes' => $paymentNotes,
                ]);

                if ($order->farm_id) {
                    \App\Models\FarmLedgerEntry::recordOrderExpense($order);
                }

                \App\Models\ActivityLog::log(
                    'auto_settle_cod_payment',
                    $order,
                    ['order_id' => $order->id, 'amount' => $order->total, 'payment_mode' => $order->payment_mode]
                );
            }
        });

        $order->refresh();

        // Customer trust tracking on final fulfillment statuses
        if ($newStatus === 'delivered') {
            app(\App\Services\OrderFraudScoringService::class)->recordDeliverySuccess($order);
        } elseif ($newStatus === 'returned') {
            app(\App\Services\OrderFraudScoringService::class)->recordDoorstepRefusal(
                $order,
                $request->input('notes') ?: 'Delivery returned at doorstep'
            );
        }

        // Trigger SSLCommerz refund automatically if order was paid online and is now cancelled/returned
        if (in_array($newStatus, ['cancelled', 'rejected', 'returned']) && $order->payment_mode === 'sslcommerz' && $order->payment_status === 'paid' && $order->refund_status !== 'completed') {
            try {
                $refRes = app(\App\Services\SSLCommerzService::class)->initiateRefund(
                    $order,
                    (float) $order->total,
                    "Order #{$order->id} marked as {$newStatus} by admin"
                );

                $payment = $order->payment ?? \App\Models\Payment::where('order_id', $order->id)->first();
                if ($payment) {
                    \App\Models\Refund::create([
                        'payment_id' => $payment->id,
                        'order_id' => $order->id,
                        'amount' => (float) $order->total,
                        'reason' => "Order #{$order->id} marked as {$newStatus} by admin",
                        'status' => 'completed',
                        'restock_items' => true,
                        'gateway_refund_ref' => $refRes['refund_ref_id'] ?? null,
                        'gateway_response' => $refRes['response'] ?? null,
                        'initiated_by' => $request->user()?->id,
                        'completed_at' => now(),
                    ]);

                    $payment->update([
                        'status' => 'refunded',
                    ]);
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error("SSLCommerz auto-refund on status change failed: " . $e->getMessage());
            }
        }

        \App\Models\ActivityLog::log(
            'update_order_status',
            $order,
            ['old_status' => $oldStatus, 'new_status' => $newStatus]
        );

        if ($newStatus !== $oldStatus && $order->user_id) {
            $statusLabels = [
                'confirmed' => 'confirmed',
                'dispatched' => 'dispatched for delivery',
                'delivered' => 'delivered',
                'cancelled' => 'cancelled',
                'returned' => 'marked as returned',
            ];
            $statusText = $statusLabels[$newStatus] ?? $newStatus;

            \App\Models\AdminNotification::notify(
                'order.status_updated',
                "Order #{$order->id} Status: " . ucfirst($newStatus),
                "Your order #{$order->id} has been {$statusText}.",
                [
                    'order_id' => $order->id,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                ],
                $order->user_id
            );

            // Supplementary email channel for farmers who provided an email
            $customer = $order->user;
            if ($customer && $customer->email && in_array($newStatus, ['confirmed', 'dispatched', 'delivered', 'cancelled', 'returned'])) {
                try {
                    $pdfBinary = in_array($newStatus, ['confirmed', 'delivered'])
                        ? app(\App\Services\PdfDocumentService::class)->generateOrderInvoicePdf($order)
                        : null;

                    \Illuminate\Support\Facades\Mail::to($customer->email)
                        ->queue(new \App\Mail\OrderStatusChangedMail($order, $oldStatus, $newStatus, $pdfBinary));
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning("[ORDER STATUS MAIL ERROR] Could not queue email for order #{$order->id}: " . $e->getMessage());
                }
            }
        }

        return $this->successResponse(
            new OrderResource($order->load(['items.product', 'user', 'payment'])),
            'Order status updated successfully'
        );
    }

    /**
     * Edit order line items (add/remove product, change quantity) pre-dispatch (Admin only).
     */
    public function update(Request $request, Order $order): JsonResponse
    {
        Gate::authorize('update', $order);

        // Pre-dispatch only: only allow while pending or confirmed
        if (! in_array($order->status, ['pending', 'confirmed'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'status' => ["Order items can only be edited while order is in 'pending' or 'confirmed' status. Current status: {$order->status}"],
            ]);
        }

        $validated = $request->validate([
            'reason_note' => ['required', 'string', 'min:3', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $order->loadMissing('items.product');

        $oldQuantities = [];
        foreach ($order->items as $item) {
            $oldQuantities[$item->product_id] = ($oldQuantities[$item->product_id] ?? 0) + $item->quantity;
        }

        $beforeItems = $order->items->map(function ($item) {
            return [
                'product_id' => $item->product_id,
                'name' => $item->product?->name ?? 'Product #' . $item->product_id,
                'quantity' => $item->quantity,
                'price' => (float) $item->price_at_purchase,
                'total' => (float) ($item->price_at_purchase * $item->quantity),
            ];
        })->toArray();
        $beforeTotal = (float) $order->total;

        $newQuantities = [];
        foreach ($validated['items'] as $item) {
            $newQuantities[$item['product_id']] = ($newQuantities[$item['product_id']] ?? 0) + $item['quantity'];
        }

        $allProductIds = array_unique(array_merge(array_keys($oldQuantities), array_keys($newQuantities)));

        \Illuminate\Support\Facades\DB::transaction(function () use ($order, $allProductIds, $oldQuantities, $newQuantities, $validated) {
            foreach ($allProductIds as $prodId) {
                $oldQty = $oldQuantities[$prodId] ?? 0;
                $newQty = $newQuantities[$prodId] ?? 0;
                $delta = $newQty - $oldQty;

                if ($delta > 0) {
                    $product = \App\Models\Product::with('variants')->find($prodId);
                    $defaultVar = $product?->variants->firstWhere('is_default', true) ?? $product?->variants->first();

                    if ($defaultVar) {
                        $decremented = \App\Models\ProductVariant::where('id', $defaultVar->id)
                            ->where('stock', '>=', $delta)
                            ->decrement('stock', $delta);

                        if (! $decremented) {
                            $name = $product ? $product->name : "ID #{$prodId}";
                            $avail = $defaultVar->stock;
                            throw \Illuminate\Validation\ValidationException::withMessages([
                                'items' => ["Insufficient stock for product: {$name} ({$defaultVar->variant_label}). Additional requested: {$delta}, currently in stock: {$avail}"],
                            ]);
                        }
                        $product->syncAggregateStockAndPrice();
                    } else {
                        $decremented = \App\Models\Product::where('id', $prodId)
                            ->where('stock', '>=', $delta)
                            ->decrement('stock', $delta);

                        if (! $decremented) {
                            $name = $product ? $product->name : "ID #{$prodId}";
                            $avail = $product ? $product->stock : 0;
                            throw \Illuminate\Validation\ValidationException::withMessages([
                                'items' => ["Insufficient stock for product: {$name}. Additional requested: {$delta}, currently in stock: {$avail}"],
                            ]);
                        }
                    }
                } elseif ($delta < 0) {
                    $product = \App\Models\Product::with('variants')->find($prodId);
                    $defaultVar = $product?->variants->firstWhere('is_default', true) ?? $product?->variants->first();
                    if ($defaultVar) {
                        \App\Models\ProductVariant::where('id', $defaultVar->id)->increment('stock', abs($delta));
                        $product->syncAggregateStockAndPrice();
                    } else {
                        \App\Models\Product::where('id', $prodId)->increment('stock', abs($delta));
                    }
                }
            }

            // Re-create items with fresh prices
            $order->items()->delete();
            $newTotal = 0;

            foreach ($validated['items'] as $item) {
                $product = \App\Models\Product::findOrFail($item['product_id']);
                $variantId = $item['product_variant_id'] ?? null;
                $variant = $variantId ? \App\Models\ProductVariant::find($variantId) : ($product->defaultVariant ?? $product->variants()->first());
                $price = $variant ? $variant->price : $product->price;
                $subtotal = $price * $item['quantity'];
                $newTotal += $subtotal;

                $order->items()->create([
                    'product_id' => $product->id,
                    'product_variant_id' => $variant?->id,
                    'quantity' => $item['quantity'],
                    'price_at_purchase' => $price,
                ]);
            }

            $order->update(['total' => $newTotal]);

            if ($order->farm_id) {
                \App\Models\FarmLedgerEntry::where('source', 'system_order')
                    ->where('source_reference_id', $order->id)
                    ->whereNull('voided_at')
                    ->update(['amount' => $newTotal]);
            }
        });

        $afterItems = $order->fresh('items.product')->items->map(function ($item) {
            return [
                'product_id' => $item->product_id,
                'name' => $item->product?->name ?? 'Product #' . $item->product_id,
                'quantity' => $item->quantity,
                'price' => (float) $item->price_at_purchase,
                'total' => (float) ($item->price_at_purchase * $item->quantity),
            ];
        })->toArray();
        $afterTotal = (float) $order->fresh()->total;

        // Log to Activity Audit Trail
        \App\Models\ActivityLog::log(
            'order.items_updated',
            $order,
            [
                'reason_note' => $validated['reason_note'],
                'before_total' => $beforeTotal,
                'after_total' => $afterTotal,
                'before_items' => $beforeItems,
                'after_items' => $afterItems,
            ],
            $request->user()
        );

        // Notify Farmer
        if ($order->user_id) {
            \App\Models\AdminNotification::notify(
                'order.updated',
                "Order #{$order->id} Updated",
                "Your order #{$order->id} items were updated by FarmLink. New total: ৳" . number_format($afterTotal, 2) . ". Reason: " . $validated['reason_note'],
                [
                    'order_id' => $order->id,
                    'before_total' => $beforeTotal,
                    'new_total' => $afterTotal,
                    'reason_note' => $validated['reason_note'],
                ],
                $order->user_id
            );
        }

        return $this->successResponse(
            new OrderResource($order->fresh(['items.product', 'user'])),
            'Order items updated successfully'
        );
    }

    /**
     * Edit delivery charge, destination address, and recipient details for an order (Admin only).
     */
    public function updateDelivery(Request $request, Order $order): JsonResponse
    {
        Gate::authorize('update', $order);

        // Pre-dispatch check: allow editing while pending or confirmed
        if (! in_array($order->status, ['pending', 'confirmed'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'status' => ["Delivery details can only be edited while order is in 'pending' or 'confirmed' status. Current status: {$order->status}"],
            ]);
        }

        $validated = $request->validate([
            'delivery_address' => ['required', 'string', 'max:500'],
            'delivery_fee' => ['required', 'numeric', 'min:0'],
            'recipient_name' => ['nullable', 'string', 'max:100'],
            'recipient_phone' => ['nullable', 'string', 'max:20'],
            'division_id' => ['nullable', 'integer', 'exists:divisions,id'],
            'district_id' => ['nullable', 'integer', 'exists:districts,id'],
            'upazila_id' => ['nullable', 'integer', 'exists:upazilas,id'],
            'union_id' => ['nullable', 'integer', 'exists:unions,id'],
            'district' => ['nullable', 'string', 'max:100'],
            'upazila' => ['nullable', 'string', 'max:100'],
            'union' => ['nullable', 'string', 'max:100'],
            'reason_note' => ['nullable', 'string', 'max:500'],
        ]);

        $beforeFee = (float) $order->delivery_fee;
        $beforeAddress = (string) $order->delivery_address;
        $beforeTotal = (float) $order->total;

        $newFee = round((float) $validated['delivery_fee'], 2);
        $subtotal = (float) ($order->subtotal ?? $order->total);
        $discount = (float) ($order->discount_amount ?? 0);
        $newTotal = round(max(0, ($subtotal - $discount) + $newFee), 2);

        \Illuminate\Support\Facades\DB::transaction(function () use ($order, $validated, $newFee, $newTotal) {
            $updateData = [
                'delivery_address' => $validated['delivery_address'],
                'delivery_fee' => $newFee,
                'total' => $newTotal,
            ];

            if (isset($validated['recipient_name'])) {
                $updateData['recipient_name'] = $validated['recipient_name'];
            }
            if (isset($validated['recipient_phone'])) {
                $updateData['recipient_phone'] = $validated['recipient_phone'];
            }
            if (array_key_exists('division_id', $validated)) {
                $updateData['division_id'] = $validated['division_id'];
            }
            if (array_key_exists('district_id', $validated)) {
                $updateData['district_id'] = $validated['district_id'];
            }
            if (array_key_exists('upazila_id', $validated)) {
                $updateData['upazila_id'] = $validated['upazila_id'];
            }
            if (array_key_exists('union_id', $validated)) {
                $updateData['union_id'] = $validated['union_id'];
            }
            if (array_key_exists('district', $validated)) {
                $updateData['district'] = $validated['district'];
            }
            if (array_key_exists('upazila', $validated)) {
                $updateData['upazila'] = $validated['upazila'];
            }
            if (array_key_exists('union', $validated)) {
                $updateData['union'] = $validated['union'];
            }

            $order->update($updateData);

            if ($order->farm_id) {
                \App\Models\FarmLedgerEntry::where('source', 'system_order')
                    ->where('source_reference_id', $order->id)
                    ->whereNull('voided_at')
                    ->update(['amount' => $newTotal]);
            }

            if ($order->payment && $order->payment_status !== 'paid') {
                $order->payment->update(['amount' => $newTotal]);
            }
        });

        $afterTotal = (float) $order->fresh()->total;

        \App\Models\ActivityLog::log(
            'order.delivery_updated',
            $order,
            [
                'reason_note' => $validated['reason_note'] ?? 'Admin updated delivery details',
                'before_fee' => $beforeFee,
                'after_fee' => $newFee,
                'before_address' => $beforeAddress,
                'after_address' => $validated['delivery_address'],
                'before_total' => $beforeTotal,
                'after_total' => $afterTotal,
            ],
            $request->user()
        );

        if ($order->user_id) {
            \App\Models\AdminNotification::notify(
                'order.delivery_updated',
                "Order #{$order->id} Delivery Details Updated",
                "Delivery fee updated to ৳" . number_format($newFee, 2) . ". New total: ৳" . number_format($afterTotal, 2) . ".",
                [
                    'order_id' => $order->id,
                    'delivery_fee' => $newFee,
                    'total' => $afterTotal,
                ],
                $order->user_id
            );
        }

        return $this->successResponse(
            new OrderResource($order->fresh(['items.product', 'user', 'farm', 'payment', 'division', 'districtData', 'upazilaData', 'unionData'])),
            'Order delivery details updated successfully'
        );
    }

    /**
     * Trigger a refund for an order paid via SSLCommerz (Admin only).
     */
    public function refund(Request $request, Order $order): JsonResponse
    {
        Gate::authorize('updateStatus', $order);

        $validated = $request->validate([
            'amount' => ['nullable', 'numeric', 'min:1', 'max:' . $order->total],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $amount = isset($validated['amount']) ? (float) $validated['amount'] : (float) $order->total;
        $reason = $validated['reason'] ?? 'Admin triggered manual refund';
        $restockItems = (bool) $request->input('restock_items', false);

        $sslRefundRef = null;
        $sslResponse = null;

        if ($order->payment_mode === 'sslcommerz') {
            $sslService = app(\App\Services\SSLCommerzService::class);
            $result = $sslService->initiateRefund($order, $amount, $reason);

            if (! $result['success']) {
                return $this->errorResponse($result['message'], 422);
            }
            $sslRefundRef = $result['refund_ref_id'] ?? null;
            $sslResponse = $result['response'] ?? null;
        }

        $payment = $order->payment ?? \App\Models\Payment::firstOrCreate(
            ['order_id' => $order->id],
            [
                'user_id' => $order->user_id,
                'method' => $order->payment_mode ?? 'sslcommerz',
                'amount' => $order->total,
                'status' => $order->payment_status === 'paid' ? 'success' : 'pending',
                'gateway_transaction_id' => $order->gateway_transaction_id,
                'paid_at' => $order->paid_at,
            ]
        );

        $refund = \App\Models\Refund::create([
            'payment_id' => $payment->id,
            'order_id' => $order->id,
            'amount' => $amount,
            'reason' => $reason,
            'status' => 'completed',
            'restock_items' => $restockItems,
            'gateway_refund_ref' => $sslRefundRef,
            'gateway_response' => $sslResponse,
            'initiated_by' => $request->user()?->id,
            'completed_at' => now(),
        ]);

        $totalRefunded = $payment->refunds()->whereIn('status', ['completed', 'processing'])->sum('amount');
        $newPaymentStatus = (abs($totalRefunded - (float) $payment->amount) < 0.05)
            ? 'refunded'
            : 'partially_refunded';

        $payment->update([
            'status' => $newPaymentStatus,
        ]);

        $order->update([
            'refund_status' => 'completed',
            'refund_amount' => $totalRefunded,
            'refund_reference' => $sslRefundRef ?: ($order->refund_reference ?: "REF-ORD-{$refund->id}"),
            'refund_reason' => $reason,
            'refunded_at' => now(),
        ]);

        if ($restockItems && $order->items) {
            $order->loadMissing('items');
            $userId = $request->user()?->id ?? $order->user_id ?? 1;

            foreach ($order->items as $item) {
                $qty = (int) $item->quantity;
                if ($item->product_variant_id) {
                    $variant = \App\Models\ProductVariant::find($item->product_variant_id);
                    if ($variant) {
                        $oldStock = (int) $variant->stock;
                        $newStock = $oldStock + $qty;
                        $variant->update(['stock' => $newStock]);

                        $product = \App\Models\Product::find($item->product_id);
                        if ($product) {
                            $product->syncAggregateStockAndPrice();
                        }

                        \App\Models\ProductStockAdjustment::create([
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
                    $product = \App\Models\Product::find($item->product_id);
                    if ($product) {
                        $oldStock = (int) $product->stock;
                        $newStock = $oldStock + $qty;
                        $product->update(['stock' => $newStock]);

                        \App\Models\ProductStockAdjustment::create([
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

        if ($order->user_id) {
            \App\Models\AdminNotification::notify(
                'order.refund_issued',
                'Refund Processed',
                "A refund of ৳" . number_format($amount, 2) . " has been issued for your order #{$order->id}." . ($reason ? " Reason: {$reason}" : ''),
                ['order_id' => $order->id, 'amount' => $amount],
                $order->user_id
            );
        }

        \App\Models\ActivityLog::log(
            'order.refund_issued',
            $payment,
            [
                'order_id' => $order->id,
                'payment_id' => $payment->id,
                'amount' => $amount,
                'reason' => $reason,
                'restock_items' => $restockItems,
            ],
            $request->user()
        );

        return $this->successResponse(
            new OrderResource($order->fresh(['items.product', 'user'])),
            "Refund of ৳" . number_format($amount, 2) . " processed successfully."
        );
    }

    /**
     * Check live transaction status on SSLCommerz gateway and auto-reconcile if verified paid.
     */
    public function checkGatewayStatus(Request $request, Order $order): JsonResponse
    {
        Gate::authorize('viewAdmin', Order::class);

        if ($order->payment_mode !== 'sslcommerz') {
            return response()->json([
                'success' => false,
                'message' => 'This order was not initiated via SSLCommerz online gateway.',
            ], 422);
        }

        $tranId = $order->gateway_transaction_id;
        if (! $tranId) {
            return response()->json([
                'success' => false,
                'message' => 'No gateway transaction ID found for this order.',
            ], 422);
        }

        // If order is already paid, return status
        if ($order->payment_status === 'paid') {
            return response()->json([
                'success' => true,
                'is_paid' => true,
                'gateway_status' => 'VALID',
                'message' => 'Order is already marked as paid.',
                'order' => new OrderResource($order->load(['items.product', 'user', 'payment'])),
            ]);
        }

        $sslService = app(\App\Services\SSLCommerzService::class);
        $gatewayResult = $sslService->queryTransaction($tranId);

        $status = strtoupper($gatewayResult['status'] ?? '');
        $element = $gatewayResult['element'][0] ?? null;
        $elementStatus = strtoupper($element['status'] ?? '');

        if ($status === 'VALID' || in_array($elementStatus, ['VALID', 'VALIDATED'])) {
            $valData = $element ?: $gatewayResult;
            $valId = $valData['val_id'] ?? ('VAL-' . time());

            $processRes = $sslService->processValidatedPayment($valData, $valId);

            if ($processRes['success'] ?? false) {
                \App\Models\ActivityLog::log(
                    'gateway_status_reconciled',
                    $order,
                    [
                        'tran_id' => $tranId,
                        'gateway_status' => 'VALID',
                        'sub_method' => $order->fresh()->sub_method,
                        'bank_tran_id' => $order->fresh()->bank_tran_id,
                    ],
                    $request->user()?->id
                );

                return response()->json([
                    'success' => true,
                    'is_paid' => true,
                    'gateway_status' => 'VALID',
                    'message' => 'Payment verified as SUCCESSFUL with SSLCommerz! Order has been updated to Paid.',
                    'details' => [
                        'sub_method' => $order->fresh()->sub_method,
                        'bank_tran_id' => $order->fresh()->bank_tran_id,
                        'amount' => $valData['amount'] ?? $order->total,
                        'paid_at' => $order->fresh()->paid_at,
                    ],
                    'order' => new OrderResource($order->fresh()->load(['items.product', 'user', 'payment'])),
                ]);
            }
        }

        $reportedStatus = $elementStatus ?: ($status ?: 'UNATTEMPTED / NOT FOUND');

        return response()->json([
            'success' => true,
            'is_paid' => false,
            'gateway_status' => $reportedStatus,
            'message' => "Gateway reports: Transaction is '{$reportedStatus}'. No payment was captured by SSLCommerz.",
            'raw' => $gatewayResult,
            'order' => new OrderResource($order->load(['items.product', 'user', 'payment'])),
        ]);
    }

    /**
     * Mark an order as paid manually with optional MFS/Bank transaction ID and notes.
     */
    public function markAsPaid(Request $request, Order $order): JsonResponse
    {
        Gate::authorize('updateStatus', $order);

        if ($order->payment_status === 'paid') {
            return $this->errorResponse('This order is already marked as paid.', 422);
        }

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
            'bank_tran_id' => ['nullable', 'string', 'max:100'],
            'sub_method' => ['nullable', 'string', 'max:50'],
        ]);

        $payment = $order->payment ?? \App\Models\Payment::firstOrCreate(
            ['order_id' => $order->id],
            [
                'user_id' => $order->user_id,
                'method' => $order->payment_mode ?: 'sslcommerz',
                'amount' => $order->total,
            ]
        );

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

            $orderGDetails = $order->gateway_payment_details ?: [];
            if (! empty($validated['bank_tran_id'])) {
                $orderGDetails['bank_tran_id'] = $validated['bank_tran_id'];
            }
            if (! empty($validated['sub_method'])) {
                $orderGDetails['card_brand'] = strtoupper($validated['sub_method']);
            }
            $order->update([
                'gateway_payment_details' => $orderGDetails,
            ]);
        }

        $payment->markAsPaid($validated['notes'] ?? null);

        \App\Models\ActivityLog::log(
            'order.manually_marked_as_paid',
            $order,
            [
                'order_id' => $order->id,
                'payment_id' => $payment->id,
                'amount' => (float) $order->total,
                'bank_tran_id' => $validated['bank_tran_id'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ],
            $request->user()
        );

        if ($order->user_id) {
            \App\Models\AdminNotification::notify(
                'order.payment_received',
                'Payment Received',
                "Your payment of ৳" . number_format($order->total, 2) . " for Order #{$order->id} has been recorded.",
                ['order_id' => $order->id, 'payment_id' => $payment->id],
                $order->user_id
            );
        }

        return $this->successResponse(
            new OrderResource($order->fresh(['items.product', 'user', 'payment'])),
            'Order has been successfully marked as paid.'
        );
    }

    /**
     * Staff verification or fraud status update for a flagged order.
     */
    public function verifyFraudStatus(Request $request, Order $order): JsonResponse
    {
        Gate::authorize('update', $order);

        $validated = $request->validate([
            'verification_status' => 'required|in:verified_call,advance_paid,unverified,auto_trusted',
            'unflag' => 'boolean',
            'cod_blocked' => 'nullable|boolean',
            'is_blacklisted' => 'nullable|boolean',
            'notes' => 'nullable|string|max:500',
        ]);

        $fraudService = app(\App\Services\OrderFraudScoringService::class);
        $order = $fraudService->verifyOrder($order, $request->user(), $validated['verification_status']);

        if (! empty($validated['unflag'])) {
            $order->update(['is_flagged' => false]);
        }

        if (isset($validated['cod_blocked']) && $order->user) {
            $order->user->update(['cod_blocked' => (bool) $validated['cod_blocked']]);
        }

        if (isset($validated['is_blacklisted']) && $order->user) {
            $order->user->update([
                'is_blacklisted' => (bool) $validated['is_blacklisted'],
                'blacklist_reason' => $validated['notes'] ?? 'Updated during staff fraud review',
            ]);
        }

        if (! empty($validated['notes'])) {
            $order->update([
                'notes' => trim(($order->notes ? $order->notes . ' | ' : '') . "Fraud review note: {$validated['notes']}"),
            ]);
        }

        \App\Models\ActivityLog::log(
            'order.fraud_verified',
            $order,
            [
                'order_id' => $order->id,
                'verification_status' => $validated['verification_status'],
                'verified_by' => $request->user()->id,
            ],
            $request->user()
        );

        return $this->successResponse(
            new OrderResource($order->fresh(['user', 'farm', 'items.product'])),
            'Order fraud and verification status updated successfully.'
        );
    }
}
