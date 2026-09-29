<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreOrderRequest;
use App\Http\Resources\V1\OrderResource;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class OrderController extends ApiController
{
    /**
     * Display a listing of orders for the authenticated user (or all for admin).
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Order::class);

        $user = $request->user();
        $query = Order::with(['items.product', 'items.variant', 'user', 'farm'])->latest();

        if (! $user->hasRole('admin')) {
            $query->where('user_id', $user->id);
        } elseif ($request->filled('user_id')) {
            $query->where('user_id', $request->query('user_id'));
        } else {
            $query->where('user_id', $user->id);
        }

        $orders = $query->get();

        return $this->successResponse(
            OrderResource::collection($orders),
            'Orders retrieved successfully'
        );
    }

    /**
     * Store a newly created order with items from cart payload.
     */
    public function store(StoreOrderRequest $request): JsonResponse
    {
        Gate::authorize('create', Order::class);

        $user = $request->user();
        $farmId = $request->validated('farm_id');

        if (! $user->hasRole('admin')) {
            if ($farmId) {
                $userFarm = \App\Models\Farm::where('id', $farmId)->where('user_id', $user->id)->first();
                if (! $userFarm) {
                    throw ValidationException::withMessages([
                        'farm_id' => ['The selected farm does not belong to your account.'],
                    ]);
                }
            } else {
                $farms = \App\Models\Farm::where('user_id', $user->id)->get();
                if ($farms->count() === 1) {
                    $farmId = $farms->first()->id;
                } elseif ($farms->count() > 1) {
                    throw ValidationException::withMessages([
                        'farm_id' => ['Please select which farm this order is for.'],
                    ]);
                }
            }
        }

        $order = DB::transaction(function () use ($request, $farmId) {
            $itemsPayload = $request->validated('items');
            $total = 0;
            $itemsData = [];

            foreach ($itemsPayload as $item) {
                $product = Product::findOrFail($item['product_id']);
                $variantId = $item['product_variant_id'] ?? null;
                $variant = null;
                if ($variantId) {
                    $variant = \App\Models\ProductVariant::where('product_id', $product->id)->where('id', $variantId)->first();
                }
                if (! $variant) {
                    $variant = $product->defaultVariant ?? $product->variants()->first();
                }

                if ($variant) {
                    $updated = \App\Models\ProductVariant::where('id', $variant->id)
                        ->where('stock', '>=', $item['quantity'])
                        ->decrement('stock', $item['quantity']);

                    if (! $updated) {
                        throw ValidationException::withMessages([
                            'items' => ["Insufficient stock for {$product->name} ({$variant->variant_label})"],
                        ]);
                    }

                    // Also keep parent product aggregate stock in sync
                    Product::where('id', $product->id)->decrement('stock', $item['quantity']);

                    $price = $variant->price;
                    $subtotal = $price * $item['quantity'];
                    $total += $subtotal;

                    $itemsData[] = [
                        'product_id' => $product->id,
                        'product_variant_id' => $variant->id,
                        'quantity' => $item['quantity'],
                        'price_at_purchase' => $price,
                    ];
                } else {
                    $updated = Product::where('id', $product->id)
                        ->where('stock', '>=', $item['quantity'])
                        ->decrement('stock', $item['quantity']);

                    if (! $updated) {
                        $productName = $product ? $product->name : "ID #{$item['product_id']}";
                        throw ValidationException::withMessages([
                            'items' => ["Insufficient stock for product: {$productName}"],
                        ]);
                    }

                    $subtotal = $product->price * $item['quantity'];
                    $total += $subtotal;

                    $itemsData[] = [
                        'product_id' => $product->id,
                        'product_variant_id' => null,
                        'quantity' => $item['quantity'],
                        'price_at_purchase' => $product->price,
                    ];
                }
            }

            $order = Order::create([
                'user_id' => $request->user()->id,
                'farm_id' => $farmId,
                'status' => 'pending',
                'total' => $total,
            ]);

            $order->items()->createMany($itemsData);

            return $order;
        });

        \App\Models\AdminNotification::notify(
            'order',
            'New Order Received',
            "Order #{$order->id} for ৳" . number_format($order->total ?? 0, 2) . " placed by {$request->user()->name}.",
            ['order_id' => $order->id, 'user_id' => $request->user()->id]
        );

        \App\Models\ActivityLog::log(
            'create_order',
            $order,
            ['total' => $order->total, 'items_count' => count($request->validated('items'))],
            $request->user()
        );

        foreach ($request->validated('items') as $item) {
            $prod = Product::find($item['product_id']);
            if ($prod && $prod->stock <= 5) {
                \App\Models\AdminNotification::notify(
                    'stock',
                    'Low Stock Alert',
                    "Product '{$prod->name}' is running low (Remaining stock: {$prod->stock}).",
                    ['product_id' => $prod->id, 'stock' => $prod->stock]
                );
            }
        }

        return $this->createdResponse(
            new OrderResource($order->load(['items.product', 'items.variant', 'farm'])),
            'Order placed successfully'
        );
    }

    /**
     * Display the specified order.
     */
    public function show(Order $order): JsonResponse
    {
        Gate::authorize('view', $order);

        return $this->successResponse(
            new OrderResource($order->load(['items.product', 'items.variant', 'user', 'farm'])),
            'Order retrieved successfully'
        );
    }
}
