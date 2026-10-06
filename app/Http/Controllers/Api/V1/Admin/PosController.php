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
            'payment_mode' => ['nullable', 'string', 'in:cash,cod'],
            'fulfillment_type' => ['nullable', 'string', 'in:over_the_counter,deliver_to_farm'],
            'delivery_address' => ['nullable', 'string', 'max:500'],
            'delivery_fee' => ['nullable', 'numeric', 'min:0'],
            'recipient_name' => ['nullable', 'string', 'max:100'],
            'recipient_phone' => ['nullable', 'string', 'max:20'],
            'division_id' => ['nullable', 'integer', 'exists:divisions,id'],
            'district_id' => ['nullable', 'integer', 'exists:districts,id'],
            'upazila_id' => ['nullable', 'integer', 'exists:upazilas,id'],
            'union_id' => ['nullable', 'integer', 'exists:unions,id'],
            'district' => ['nullable', 'string', 'max:100'],
            'upazila' => ['nullable', 'string', 'max:100'],
            'union' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        // Verify customer is a farmer
        $farmer = User::findOrFail($validated['user_id']);
        if (! $farmer->hasRole('farmer')) {
            throw ValidationException::withMessages([
                'user_id' => ['The selected customer is not registered as a farmer.'],
            ]);
        }

        if ($farmer->cod_blocked && ($validated['payment_mode'] ?? 'cash') === 'cod') {
            throw ValidationException::withMessages([
                'payment_mode' => ['Cash on Delivery is blocked for this customer due to past doorstep delivery returns. Please collect cash at counter.'],
            ]);
        }

        if ($farmer->is_blacklisted && ($validated['payment_mode'] ?? 'cash') === 'cod') {
            throw ValidationException::withMessages([
                'payment_mode' => ['This customer is blacklisted (' . ($farmer->blacklist_reason ?: 'fraud risk') . '). Cash on delivery consignments are prohibited.'],
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
        $fulfillmentType = $validated['fulfillment_type'] ?? 'deliver_to_farm';
        $isOverTheCounter = $fulfillmentType === 'over_the_counter';

        // Execute Transaction: stock decrement, order creation, item creation, audit log
        $order = DB::transaction(function () use ($staff, $farmer, $farmId, $validated, $paymentMode, $fulfillmentType, $isOverTheCounter) {
            $subtotal = 0;
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
                $subtotal += $lineTotal;

                $itemsData[] = [
                    'product_id' => $product->id,
                    'product_variant_id' => $variant?->id,
                    'quantity' => $qty,
                    'price_at_purchase' => $unitPrice,
                ];
            }

            $farm = $farmId ? Farm::find($farmId) : null;
            if ($isOverTheCounter) {
                $deliveryFee = 0.00;
                $deliveryAddress = 'Over-the-counter Depot Handover';
            } else {
                $deliveryFee = isset($validated['delivery_fee']) && $validated['delivery_fee'] !== null
                    ? (float) $validated['delivery_fee']
                    : app(\App\Services\DeliveryFeeService::class)->computeDeliveryFee($subtotal, $farm?->district_id ?? null);

                $deliveryAddress = ! empty($validated['delivery_address'])
                    ? $validated['delivery_address']
                    : ($farm ? "{$farm->farm_name}, {$farm->district}" : ($farmer->district ? "{$farmer->district} District" : 'Direct Farm Delivery'));
            }

            $total = round($subtotal + $deliveryFee, 2);
            $orderStatus = $isOverTheCounter ? 'delivered' : 'confirmed';

            $recipientName = $validated['recipient_name'] ?? $farmer->name;
            $recipientPhone = $validated['recipient_phone'] ?? $farmer->phone;
            $divisionId = $validated['division_id'] ?? $farm?->division_id;
            $districtId = $validated['district_id'] ?? $farm?->district_id;
            $upazilaId = $validated['upazila_id'] ?? $farm?->upazila_id;
            $unionId = $validated['union_id'] ?? $farm?->union_id;
            $district = $validated['district'] ?? ($farm?->district ?: $farmer->district);
            $upazila = $validated['upazila'] ?? $farm?->upazila;
            $union = $validated['union'] ?? $farm?->union;

            // Create Order tagged as admin_pos
            $order = Order::create([
                'user_id' => $farmer->id,
                'farm_id' => $farmId,
                'recipient_name' => $recipientName,
                'recipient_phone' => $recipientPhone,
                'division_id' => $divisionId,
                'district_id' => $districtId,
                'upazila_id' => $upazilaId,
                'union_id' => $unionId,
                'district' => $district,
                'upazila' => $upazila,
                'union' => $union,
                'status' => $orderStatus,
                'payment_status' => $paymentMode === 'cash' ? 'paid' : 'pending',
                'channel' => 'admin_pos',
                'payment_mode' => $paymentMode,
                'delivery_address' => $deliveryAddress,
                'notes' => $validated['notes'] ?? null,
                'subtotal' => $subtotal,
                'delivery_fee' => $deliveryFee,
                'discount_amount' => 0.00,
                'coupon_id' => null,
                'total' => $total,
            ]);

            $order->items()->createMany($itemsData);

            \App\Models\Payment::create([
                'order_id' => $order->id,
                'user_id' => $order->user_id,
                'method' => $paymentMode,
                'amount' => $total,
                'status' => $paymentMode === 'cash' ? 'success' : 'pending',
                'paid_at' => $paymentMode === 'cash' ? now() : null,
            ]);

            if ($order->farm_id) {
                \App\Models\FarmLedgerEntry::recordOrderExpense($order);
            }

            if ($orderStatus === 'delivered') {
                $farmer->increment('delivered_orders_count');
            }

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
                'fulfillment_type' => $fulfillmentType,
                'order_status' => $order->status,
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
