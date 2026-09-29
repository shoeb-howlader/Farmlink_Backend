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

        $query = Order::with(['items.product', 'user'])->latest();

        // Filter by status (pending, confirmed, dispatched, delivered, cancelled)
        if ($request->filled('status') && $request->query('status') !== 'all') {
            $query->where('status', $request->query('status'));
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

        $perPage = max(1, min((int) $request->query('per_page', 10), 100));
        $paginated = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Admin orders retrieved successfully',
            'data' => OrderResource::collection($paginated->items()),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    /**
     * Display the specified order with items, products, and farmer details (Admin only).
     */
    public function show(Request $request, Order $order): JsonResponse
    {
        Gate::authorize('viewAdmin', Order::class);

        return $this->successResponse(
            new OrderResource($order->load(['items.product', 'user'])),
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
            if (in_array($newStatus, ['cancelled', 'rejected']) && ! in_array($oldStatus, ['cancelled', 'rejected'])) {
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
            }

            $order->update([
                'status' => $newStatus,
            ]);
        });

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
        }

        return $this->successResponse(
            new OrderResource($order->load(['items.product', 'user'])),
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
}
