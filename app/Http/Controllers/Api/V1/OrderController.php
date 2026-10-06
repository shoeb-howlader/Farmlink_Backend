<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreOrderRequest;
use App\Http\Resources\V1\OrderResource;
use App\Models\Order;
use App\Models\Product;
use App\Traits\SyncsLocationData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class OrderController extends ApiController
{
    use SyncsLocationData;

    /**
     * Display a listing of orders for the authenticated user (or all for admin).
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Order::class);

        $user = $request->user();
        $query = Order::with([
            'items.product',
            'items.variant',
            'user',
            'farm',
            'division',
            'districtData',
            'upazilaData',
            'unionData',
            'pourashavaData',
            'payment',
        ])->latest();

        if (! $user->hasRole('admin')) {
            $query->where('user_id', $user->id);
        } elseif ($request->filled('user_id')) {
            $query->where('user_id', $request->query('user_id'));
        } else {
            $query->where('user_id', $user->id);
        }

        if ($request->filled('farm_id')) {
            $query->where('farm_id', $request->query('farm_id'));
        }

        $perPage = max(1, min((int) ($request->query('per_page') ?? 15), 50));
        $paginated = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Orders retrieved successfully',
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

        $farm = $farmId ? \App\Models\Farm::find($farmId) : null;

        $recipientName = $request->input('recipient_name') ?: ($user->name ?? 'Farmer');
        $recipientPhone = $request->input('recipient_phone') ?: ($user->phone ?? null);
        $deliveryAddress = $request->input('delivery_address');
        $notes = $request->input('notes');

        $locationData = [
            'division_id' => $request->input('division_id'),
            'district_id' => $request->input('district_id'),
            'upazila_id' => $request->input('upazila_id'),
            'union_id' => $request->input('union_id'),
            'pourashava_id' => $request->input('pourashava_id'),
            'district' => $request->input('district'),
            'upazila' => $request->input('upazila'),
            'union' => $request->input('union'),
        ];

        // If no explicit district or district_id provided, snapshot from destination farm if available
        if (empty($locationData['district_id']) && empty($locationData['district']) && $farm) {
            $locationData['division_id'] = $farm->division_id;
            $locationData['district_id'] = $farm->district_id;
            $locationData['upazila_id'] = $farm->upazila_id;
            $locationData['union_id'] = $farm->union_id;
            $locationData['pourashava_id'] = $farm->pourashava_id;
            $locationData['district'] = $farm->district;
            $locationData['upazila'] = $farm->upazila;
            $locationData['union'] = $farm->union;

            if (empty($deliveryAddress)) {
                $deliveryAddress = $farm->address ?: $farm->farm_address;
            }
        }

        $syncedLocation = $this->syncLocationData($locationData);

        $paymentMode = $request->input('payment_mode', 'cod');
        $isOnlinePayment = ($paymentMode === 'sslcommerz');

        if ($user->is_blacklisted) {
            throw ValidationException::withMessages([
                'order' => ['Your account has been restricted from placing orders. Please contact customer support.'],
            ]);
        }

        if ($user->cod_blocked && $paymentMode === 'cod') {
            throw ValidationException::withMessages([
                'payment_mode' => ['Cash on delivery is disabled for this account due to previous delivery returns. Please complete payment securely using bKash, Nagad, or Cards.'],
            ]);
        }

        $order = DB::transaction(function () use ($request, $user, $farmId, $recipientName, $recipientPhone, $deliveryAddress, $notes, $syncedLocation, $paymentMode, $isOnlinePayment) {
            $itemsPayload = $request->validated('items');
            $subtotal = 0;
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
                    if ($variant->stock < $item['quantity']) {
                        throw ValidationException::withMessages([
                            'items' => ["Insufficient stock for {$product->name} ({$variant->variant_label})"],
                        ]);
                    }

                    // Stock decrement is deferred for online payment (SSLCommerz) until payment is confirmed by IPN
                    if (! $isOnlinePayment) {
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
                    }

                    $price = $variant->price;
                    $lineTotal = $price * $item['quantity'];
                    $subtotal += $lineTotal;

                    $itemsData[] = [
                        'product_id' => $product->id,
                        'product_variant_id' => $variant->id,
                        'quantity' => $item['quantity'],
                        'price_at_purchase' => $price,
                    ];
                } else {
                    if ($product->stock < $item['quantity']) {
                        $productName = $product ? $product->name : "ID #{$item['product_id']}";
                        throw ValidationException::withMessages([
                            'items' => ["Insufficient stock for product: {$productName}"],
                        ]);
                    }

                    if (! $isOnlinePayment) {
                        $updated = Product::where('id', $product->id)
                            ->where('stock', '>=', $item['quantity'])
                            ->decrement('stock', $item['quantity']);

                        if (! $updated) {
                            $productName = $product ? $product->name : "ID #{$item['product_id']}";
                            throw ValidationException::withMessages([
                                'items' => ["Insufficient stock for product: {$productName}"],
                            ]);
                        }
                    }

                    $lineTotal = $product->price * $item['quantity'];
                    $subtotal += $lineTotal;

                    $itemsData[] = [
                        'product_id' => $product->id,
                        'product_variant_id' => null,
                        'quantity' => $item['quantity'],
                        'price_at_purchase' => $product->price,
                    ];
                }
            }

            // 1. Delivery fee calculation
            $deliveryFee = app(\App\Services\DeliveryFeeService::class)
                ->computeDeliveryFee($subtotal, $syncedLocation['district_id'] ?? null);

            // 2. Coupon validation & discount calculation
            $couponCode = $request->input('coupon_code');
            $discountAmount = 0.00;
            $coupon = null;

            if (! empty($couponCode)) {
                $couponService = app(\App\Services\CouponService::class);
                $validation = $couponService->validate($couponCode, $request->user(), $subtotal);

                if (! $validation['valid']) {
                    throw ValidationException::withMessages([
                        'coupon_code' => [$validation['message']],
                    ]);
                }

                $discountAmount = $validation['discount'];
                $coupon = $validation['coupon'];
            }

            $total = round(max(0, $subtotal - $discountAmount + $deliveryFee), 2);

            // Automated Fraud & Risk Evaluation
            $fraudEvaluation = app(\App\Services\OrderFraudScoringService::class)->evaluate(
                $user,
                [
                    'total' => $total,
                    'subtotal' => $subtotal,
                    'payment_mode' => $paymentMode,
                    'recipient_phone' => $recipientPhone,
                ],
                $request
            );

            $initialStatus = $isOnlinePayment ? 'pending_payment' : 'pending';
            $paymentStatus = $isOnlinePayment ? 'unpaid' : 'pending';

            $order = Order::create([
                'user_id' => $user->id,
                'farm_id' => $farmId,
                'status' => $initialStatus,
                'payment_status' => $paymentStatus,
                'subtotal' => $subtotal,
                'delivery_fee' => $deliveryFee,
                'discount_amount' => $discountAmount,
                'coupon_id' => $coupon?->id,
                'total' => $total,
                'channel' => 'web',
                'payment_mode' => $paymentMode,
                'ip_address' => $request->ip() ?: '127.0.0.1',
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
                'ip_country' => $fraudEvaluation['ip_country'] ?? 'BD',
                'ip_city' => $fraudEvaluation['ip_city'] ?? null,
                'ip_region' => $fraudEvaluation['ip_region'] ?? null,
                'ip_isp' => $fraudEvaluation['ip_isp'] ?? null,
                'advance_delivery_fee' => $fraudEvaluation['advance_fee_amount'] ?? 0.00,
                'risk_score' => $fraudEvaluation['score'],
                'risk_level' => $fraudEvaluation['level'],
                'is_flagged' => $fraudEvaluation['is_flagged'],
                'flag_reasons' => $fraudEvaluation['flag_reasons'],
                'verification_status' => $fraudEvaluation['verification_status'],
                'recipient_name' => $recipientName,
                'recipient_phone' => $recipientPhone,
                'delivery_address' => $deliveryAddress,
                'notes' => $notes,
                'division_id' => $syncedLocation['division_id'] ?? null,
                'district_id' => $syncedLocation['district_id'] ?? null,
                'upazila_id' => $syncedLocation['upazila_id'] ?? null,
                'union_id' => $syncedLocation['union_id'] ?? null,
                'pourashava_id' => $syncedLocation['pourashava_id'] ?? null,
                'district' => $syncedLocation['district'] ?? null,
                'upazila' => $syncedLocation['upazila'] ?? null,
                'union' => $syncedLocation['union'] ?? null,
            ]);

            $order->items()->createMany($itemsData);

            if ($coupon && $discountAmount > 0) {
                app(\App\Services\CouponService::class)->redeem($coupon, $order, $request->user(), $discountAmount);
            }

            // Create initial Payment transaction record
            \App\Models\Payment::create([
                'order_id' => $order->id,
                'user_id' => $order->user_id,
                'method' => $paymentMode,
                'amount' => $order->total,
                'status' => $paymentStatus === 'paid' ? 'success' : 'pending',
                'gateway_transaction_id' => $order->gateway_transaction_id,
                'paid_at' => $order->paid_at,
            ]);

            // Only record farm ledger entry immediately for confirmed/standard orders; online payment records it upon validation
            if ($order->farm_id && ! $isOnlinePayment) {
                \App\Models\FarmLedgerEntry::recordOrderExpense($order);
            }

            return $order;
        });

        // 3. If online payment (SSLCommerz), initiate gateway session
        $gatewayPayload = null;
        if ($isOnlinePayment) {
            $sslService = app(\App\Services\SSLCommerzService::class);
            $sessionResult = $sslService->initiatePayment($order);

            if (! $sessionResult['success']) {
                throw ValidationException::withMessages([
                    'payment_mode' => [$sessionResult['message'] ?? 'Could not initiate online payment gateway session.'],
                ]);
            }

            // Sync generated transaction reference to payment record
            \App\Models\Payment::where('order_id', $order->id)->update([
                'gateway_transaction_id' => $sessionResult['tran_id'],
            ]);

            $gatewayPayload = [
                'gateway_url' => $sessionResult['gateway_url'],
                'tran_id' => $sessionResult['tran_id'],
            ];
        }

        \App\Models\AdminNotification::notify(
            'order',
            $isOnlinePayment ? 'New Order (Awaiting Online Payment)' : 'New Order Received',
            "Order #{$order->id} for ৳" . number_format($order->total ?? 0, 2) . " placed by {$request->user()->name}." . ($isOnlinePayment ? ' Awaiting SSLCommerz payment.' : ''),
            ['order_id' => $order->id, 'user_id' => $request->user()->id, 'status' => $order->status]
        );

        \App\Models\ActivityLog::log(
            'create_order',
            $order,
            ['total' => $order->total, 'items_count' => count($request->validated('items')), 'payment_mode' => $order->payment_mode],
            $request->user()
        );

        if (! $isOnlinePayment) {
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

            // Supplementary email channel for farmers who provided an email
            $orderingUser = $request->user();
            if ($orderingUser && $orderingUser->email) {
                try {
                    \Illuminate\Support\Facades\Mail::to($orderingUser->email)
                        ->queue(new \App\Mail\OrderConfirmationMail($order));
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning("[ORDER CONFIRMATION MAIL ERROR] Could not queue email for order #{$order->id}: " . $e->getMessage());
                }
            }
        }

        $resource = new OrderResource($order->load([
            'items.product',
            'items.variant',
            'farm',
            'division',
            'districtData',
            'upazilaData',
            'unionData',
            'pourashavaData'
        ]));

        if ($gatewayPayload) {
            return response()->json([
                'success' => true,
                'status' => $order->status,
                'message' => 'Online payment session created',
                'data' => (new OrderResource($order->load([
                    'items.product',
                    'items.variant',
                    'farm',
                    'division',
                    'districtData',
                    'upazilaData',
                    'unionData',
                    'pourashavaData'
                ])))->resolve(),
                'gateway_url' => $gatewayPayload['gateway_url'],
                'tran_id' => $gatewayPayload['tran_id'],
            ], 201);
        }

        return $this->createdResponse(
            $resource,
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
            new OrderResource($order->load([
                'items.product',
                'items.variant',
                'user',
                'farm',
                'division',
                'districtData',
                'upazilaData',
                'unionData',
                'pourashavaData'
            ])),
            'Order retrieved successfully'
        );
    }
}
