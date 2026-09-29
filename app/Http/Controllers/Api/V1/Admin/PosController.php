<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\V1\OrderResource;
use App\Models\ActivityLog;
use App\Models\AdminNotification;
use App\Models\Farm;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PosController extends ApiController
{
    /**
     * Store a Point-of-Sale (assisted sale) order for a farmer.
     * Accessible to Admin and Data Entry Operator roles.
     */
    public function store(Request $request): JsonResponse
    {
        $staff = $request->user();

        if (! $staff->hasAnyRole(['admin', 'data_entry_operator'])) {
            return $this->errorResponse('Unauthorized to process Point-of-Sale assisted sales', 403);
        }

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'farm_id' => ['nullable', 'integer', 'exists:farms,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.price_at_purchase' => ['nullable', 'numeric', 'min:0'],
            'payment_mode' => ['nullable', 'string', 'in:cash,cod,farmer_credit'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        // Verify customer is a farmer
        $farmer = User::findOrFail($validated['user_id']);
        if (! $farmer->hasRole('farmer')) {
            throw ValidationException::withMessages([
                'user_id' => ['The selected customer is not registered as a farmer.'],
            ]);
        }

        // Farm Selection Validation:
        // If farmer has > 1 farm, farm_id is required. If exactly 1 farm, auto-assign.
        $farmerFarms = Farm::where('user_id', $farmer->id)->get();
        $farmId = $validated['farm_id'] ?? null;

        if ($farmId) {
            $farm = $farmerFarms->firstWhere('id', $farmId);
            if (! $farm) {
                throw ValidationException::withMessages([
                    'farm_id' => ['The selected farm does not belong to this farmer.'],
                ]);
            }
        } elseif ($farmerFarms->count() === 1) {
            $farmId = $farmerFarms->first()->id;
        } elseif ($farmerFarms->count() > 1) {
            throw ValidationException::withMessages([
                'farm_id' => ['Please select which farm this assisted purchase is for.'],
            ]);
        }

        $paymentMode = $validated['payment_mode'] ?? 'cash';

        // Execute Transaction: stock decrement, order creation, item creation, audit log
        $order = DB::transaction(function () use ($staff, $farmer, $farmId, $validated, $paymentMode) {
            $total = 0;
            $itemsData = [];

            foreach ($validated['items'] as $item) {
                $qty = (int) $item['quantity'];
                $product = Product::with('variants')->findOrFail($item['product_id']);
                $variantId = $item['product_variant_id'] ?? null;
                $variant = $variantId
                    ? $product->variants->firstWhere('id', $variantId)
                    : ($product->defaultVariant ?? $product->variants->first());

                if ($variant) {
                    $updated = \App\Models\ProductVariant::where('id', $variant->id)
                        ->where('stock', '>=', $qty)
                        ->decrement('stock', $qty);

                    if (! $updated) {
                        throw ValidationException::withMessages([
                            'items' => ["Insufficient stock for {$product->name} ({$variant->variant_label}). Available stock: {$variant->stock}"],
                        ]);
                    }

                    $product->syncAggregateStockAndPrice();
                } else {
                    $updated = Product::where('id', $product->id)
                        ->where('stock', '>=', $qty)
                        ->decrement('stock', $qty);

                    if (! $updated) {
                        throw ValidationException::withMessages([
                            'items' => ["Insufficient stock for {$product->name}. Available stock: {$product->stock}"],
                        ]);
                    }
                }

                $unitPrice = isset($item['price_at_purchase']) && $item['price_at_purchase'] !== null && $item['price_at_purchase'] !== ''
                    ? (float) $item['price_at_purchase']
                    : (float) ($variant ? $variant->price : $product->price);

                $lineTotal = $unitPrice * $qty;
                $total += $lineTotal;

                $itemsData[] = [
                    'product_id' => $product->id,
                    'product_variant_id' => $variant?->id,
                    'quantity' => $qty,
                    'price_at_purchase' => $unitPrice,
                ];
            }

            // Create Order tagged as admin_pos
            $order = Order::create([
                'user_id' => $farmer->id,
                'farm_id' => $farmId,
                'status' => 'confirmed',
                'channel' => 'admin_pos',
                'payment_mode' => $paymentMode,
                'notes' => $validated['notes'] ?? null,
                'total' => $total,
            ]);

            $order->items()->createMany($itemsData);

            return $order;
        });

        // Audit Trail Logging
        ActivityLog::log(
            'order.pos_sale',
            $order,
            [
                'staff_id' => $staff->id,
                'staff_name' => $staff->name,
                'staff_role' => $staff->roles->first()?->name ?? 'staff',
                'farmer_id' => $farmer->id,
                'farmer_name' => $farmer->name,
                'farm_id' => $farmId,
                'total' => (float) $order->total,
                'invoice_number' => $order->invoice_number,
                'payment_mode' => $order->payment_mode,
                'item_count' => count($validated['items']),
            ],
            $staff
        );

        // Notify Farmer
        AdminNotification::notify(
            'order.assisted_sale',
            'Receipt for Assisted Order',
            "Your assisted order #{$order->invoice_number} for ৳" . number_format($order->total, 2) . " has been completed by FarmLink staff.",
            ['order_id' => $order->id, 'invoice_number' => $order->invoice_number],
            $farmer->id
        );

        return $this->successResponse(
            new OrderResource($order->load(['items.product', 'user', 'farm'])),
            'POS assisted sale completed successfully',
            201
        );
    }
}
