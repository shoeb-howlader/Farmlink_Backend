<?php

namespace Tests\Feature;

use App\Models\AdminNotification;
use App\Models\Farm;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Refund;
use App\Models\User;
use App\Services\PaymentFailureEscalationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminPaymentAndRefundTest extends TestCase
{
    use RefreshDatabase;

    protected User $farmer;
    protected User $admin;
    protected Farm $farm;
    protected Product $product;
    protected ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'farmer', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $this->farmer = User::factory()->create(['name' => 'Rahim Farmer']);
        $this->farmer->assignRole('farmer');

        $this->admin = User::factory()->create(['name' => 'Admin User']);
        $this->admin->assignRole('admin');

        $this->farm = Farm::factory()->create([
            'user_id' => $this->farmer->id,
            'farm_name' => 'Sunrise Aquaculture Pond',
        ]);

        $this->product = Product::factory()->create([
            'name' => 'Bio-Oxygen Pro 1kg',
            'category' => 'water_treatment',
            'price' => 1000.00,
            'stock' => 50,
            'is_active' => true,
        ]);
        $this->product->variants()->delete();

        $this->variant = ProductVariant::create([
            'product_id' => $this->product->id,
            'variant_label' => '1kg Canister',
            'sku' => 'OXY-1KG',
            'price' => 1000.00,
            'compare_at_price' => 1200.00,
            'stock' => 50,
            'is_default' => true,
        ]);
    }

    public function test_failed_payment_attempt_does_not_alter_stock_and_farmer_can_retry(): void
    {
        $initialStock = $this->variant->stock; // 50

        // 1. Initiate checkout with SSLCommerz
        Http::fake([
            'https://sandbox.sslcommerz.com/gwprocess/v4/api.php' => Http::response([
                'status' => 'SUCCESS',
                'failedreason' => '',
                'sessionkey' => 'TEST_KEY_FAIL',
                'GatewayPageURL' => 'https://sandbox.sslcommerz.com/gw.php?Q=test',
            ], 200),
        ]);

        $response = $this->actingAs($this->farmer)->postJson('/api/v1/orders', [
            'farm_id' => $this->farm->id,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'product_variant_id' => $this->variant->id,
                    'quantity' => 2,
                ],
            ],
            'payment_mode' => 'sslcommerz',
            'delivery_address' => 'Khulna Pond Gate',
        ]);

        $response->assertCreated();
        $orderId = $response->json('data.id');
        $tranId = $response->json('tran_id');

        $this->variant->refresh();
        $this->assertEquals($initialStock, $this->variant->stock, 'Stock must not be decremented on pending_payment');

        // 2. Gateway redirect returns as failed / cancelled
        $returnResponse = $this->post('/api/v1/payments/sslcommerz/return', [
            'status' => 'fail',
            'tran_id' => $tranId,
        ]);

        $returnResponse->assertRedirect();

        $order = Order::find($orderId);
        $this->assertEquals('payment_failed', $order->status);
        $this->assertEquals('failed', $order->payment_status);

        $payment = Payment::where('order_id', $orderId)->first();
        $this->assertNotNull($payment);
        $this->assertEquals('failed', $payment->status);

        // Crucial test: Stock remains 100% untouched
        $this->variant->refresh();
        $this->assertEquals($initialStock, $this->variant->stock);

        // 3. Farmer can immediately retry checkout with COD without any issues
        $retryResponse = $this->actingAs($this->farmer)->postJson('/api/v1/orders', [
            'farm_id' => $this->farm->id,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'product_variant_id' => $this->variant->id,
                    'quantity' => 2,
                ],
            ],
            'payment_mode' => 'cod',
            'delivery_address' => 'Khulna Pond Gate',
        ]);

        $retryResponse->assertCreated();
        $retryOrderId = $retryResponse->json('data.id');
        $retryOrder = Order::find($retryOrderId);

        $this->assertEquals('pending', $retryOrder->status);
        $this->assertEquals('cod', $retryOrder->payment_mode);

        // For COD, stock is decremented normally
        $this->variant->refresh();
        $this->assertEquals($initialStock - 2, $this->variant->stock);
    }

    public function test_escalation_rule_single_failure_creates_no_notification_three_failures_create_exactly_one(): void
    {
        $escalationService = app(PaymentFailureEscalationService::class);

        // Attempt 1 fails
        Order::create([
            'user_id' => $this->farmer->id,
            'farm_id' => $this->farm->id,
            'status' => 'payment_failed',
            'channel' => 'self_service',
            'subtotal' => 1000.00,
            'total' => 1000.00,
            'payment_mode' => 'sslcommerz',
            'payment_status' => 'failed',
            'gateway_transaction_id' => 'TRAN-F1',
            'delivery_address' => 'Satkhira',
            'created_at' => now()->subHours(2),
        ]);

        $escalated1 = $escalationService->checkAndEscalate($this->farmer->id);
        $this->assertFalse($escalated1);
        $this->assertEquals(0, AdminNotification::where('type', 'payment.failure_escalation')->count());

        // Attempt 2 fails
        Order::create([
            'user_id' => $this->farmer->id,
            'farm_id' => $this->farm->id,
            'status' => 'payment_failed',
            'channel' => 'self_service',
            'subtotal' => 1000.00,
            'total' => 1000.00,
            'payment_mode' => 'sslcommerz',
            'payment_status' => 'failed',
            'gateway_transaction_id' => 'TRAN-F2',
            'delivery_address' => 'Satkhira',
            'created_at' => now()->subHour(),
        ]);

        $escalated2 = $escalationService->checkAndEscalate($this->farmer->id);
        $this->assertFalse($escalated2);
        $this->assertEquals(0, AdminNotification::where('type', 'payment.failure_escalation')->count());

        // Attempt 3 fails -> threshold reached!
        Order::create([
            'user_id' => $this->farmer->id,
            'farm_id' => $this->farm->id,
            'status' => 'payment_failed',
            'channel' => 'self_service',
            'subtotal' => 1000.00,
            'total' => 1000.00,
            'payment_mode' => 'sslcommerz',
            'payment_status' => 'failed',
            'gateway_transaction_id' => 'TRAN-F3',
            'delivery_address' => 'Satkhira',
            'created_at' => now()->subMinutes(10),
        ]);

        $escalated3 = $escalationService->checkAndEscalate($this->farmer->id);
        $this->assertTrue($escalated3);
        $this->assertEquals(1, AdminNotification::where('type', 'payment.failure_escalation')->count());

        // Attempt 4 fails within same window -> already notified, so NO duplicate notification created
        Order::create([
            'user_id' => $this->farmer->id,
            'farm_id' => $this->farm->id,
            'status' => 'payment_failed',
            'channel' => 'self_service',
            'subtotal' => 1000.00,
            'total' => 1000.00,
            'payment_mode' => 'sslcommerz',
            'payment_status' => 'failed',
            'gateway_transaction_id' => 'TRAN-F4',
            'delivery_address' => 'Satkhira',
            'created_at' => now(),
        ]);

        $escalated4 = $escalationService->checkAndEscalate($this->farmer->id);
        $this->assertFalse($escalated4);
        $this->assertEquals(1, AdminNotification::where('type', 'payment.failure_escalation')->count());
    }

    public function test_admin_can_view_payments_across_all_channels_and_filter(): void
    {
        $orderCod = Order::create([
            'user_id' => $this->farmer->id,
            'status' => 'pending',
            'channel' => 'web',
            'payment_mode' => 'cod',
            'payment_status' => 'pending',
            'total' => 1200.00,
            'delivery_address' => 'Khulna',
        ]);

        $paymentCod = Payment::create([
            'order_id' => $orderCod->id,
            'user_id' => $this->farmer->id,
            'method' => 'cod',
            'amount' => 1200.00,
            'status' => 'pending',
        ]);

        $orderSsl = Order::create([
            'user_id' => $this->farmer->id,
            'status' => 'confirmed',
            'channel' => 'web',
            'payment_mode' => 'sslcommerz',
            'payment_status' => 'paid',
            'total' => 3000.00,
            'gateway_transaction_id' => 'TRAN-SEARCH-XYZ',
            'paid_at' => now(),
            'delivery_address' => 'Khulna',
        ]);

        $paymentSsl = Payment::create([
            'order_id' => $orderSsl->id,
            'user_id' => $this->farmer->id,
            'method' => 'sslcommerz',
            'amount' => 3000.00,
            'status' => 'success',
            'gateway_transaction_id' => 'TRAN-SEARCH-XYZ',
            'paid_at' => now(),
        ]);

        // 1. List all payments
        $res = $this->actingAs($this->admin)->getJson('/api/v1/admin/payments');
        $res->assertOk()
            ->assertJsonStructure(['data', 'summary', 'meta']);

        // 2. Filter by method=sslcommerz
        $resSsl = $this->actingAs($this->admin)->getJson('/api/v1/admin/payments?method=sslcommerz');
        $resSsl->assertOk()
            ->assertJsonFragment(['gateway_transaction_id' => 'TRAN-SEARCH-XYZ']);

        // 3. Search by gateway_transaction_id
        $resSearch = $this->actingAs($this->admin)->getJson('/api/v1/admin/payments?search=TRAN-SEARCH-XYZ');
        $resSearch->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.gateway_transaction_id', 'TRAN-SEARCH-XYZ');
    }

    public function test_admin_can_mark_cod_or_credit_payment_as_paid(): void
    {
        $order = Order::create([
            'user_id' => $this->farmer->id,
            'farm_id' => $this->farm->id,
            'status' => 'confirmed',
            'channel' => 'web',
            'payment_mode' => 'cod',
            'payment_status' => 'pending',
            'total' => 1500.00,
            'delivery_address' => 'Bagerhat',
        ]);

        $payment = Payment::create([
            'order_id' => $order->id,
            'user_id' => $this->farmer->id,
            'method' => 'cod',
            'amount' => 1500.00,
            'status' => 'pending',
        ]);

        $this->assertEquals('pending', $payment->status);
        $this->assertEquals('pending', $order->payment_status);

        $res = $this->actingAs($this->admin)->postJson("/api/v1/admin/payments/{$payment->id}/mark-as-paid", [
            'notes' => 'Cash collected on pond delivery by Driver #2',
        ]);

        $res->assertOk()
            ->assertJsonPath('data.status', 'success');

        $payment->refresh();
        $this->assertEquals('success', $payment->status);
        $this->assertNotNull($payment->paid_at);

        $order->refresh();
        $this->assertEquals('paid', $order->payment_status);
        $this->assertNotNull($order->paid_at);
    }

    public function test_partial_refund_reduces_balance_and_blocks_excessive_second_refund(): void
    {
        $order = Order::create([
            'user_id' => $this->farmer->id,
            'farm_id' => $this->farm->id,
            'status' => 'confirmed',
            'channel' => 'web',
            'payment_mode' => 'cod',
            'payment_status' => 'paid',
            'total' => 2000.00,
            'delivery_address' => 'Satkhira',
        ]);

        $payment = Payment::create([
            'order_id' => $order->id,
            'user_id' => $this->farmer->id,
            'method' => 'cod',
            'amount' => 2000.00,
            'status' => 'success',
            'paid_at' => now(),
        ]);

        $this->assertEquals(2000.00, $payment->remaining_refundable_amount);

        // 1. Issue first partial refund: ৳800
        $res1 = $this->actingAs($this->admin)->postJson("/api/v1/admin/payments/{$payment->id}/refund", [
            'amount' => 800.00,
            'reason' => 'Compensation for 1 damaged bag',
            'restock_items' => false,
        ]);

        $res1->assertOk()
            ->assertJsonPath('data.status', 'partially_refunded')
            ->assertJsonPath('data.remaining_refundable_amount', 1200);

        $payment->refresh();
        $this->assertEquals('partially_refunded', $payment->status);
        $this->assertEquals(1200.00, $payment->remaining_refundable_amount);

        // 2. Attempt second refund exceeding remaining balance: ৳1500 (only 1200 left) -> Must fail with 422
        $res2 = $this->actingAs($this->admin)->postJson("/api/v1/admin/payments/{$payment->id}/refund", [
            'amount' => 1500.00,
            'reason' => 'Excessive claim',
        ]);

        $res2->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);

        // 3. Issue valid second partial refund: ৳1200 (exact remaining) -> Becomes fully refunded
        $res3 = $this->actingAs($this->admin)->postJson("/api/v1/admin/payments/{$payment->id}/refund", [
            'amount' => 1200.00,
            'reason' => 'Return balance refund',
        ]);

        $res3->assertOk()
            ->assertJsonPath('data.status', 'refunded')
            ->assertJsonPath('data.remaining_refundable_amount', 0);

        $payment->refresh();
        $this->assertEquals('refunded', $payment->status);
        $this->assertEquals(0.00, $payment->remaining_refundable_amount);
    }

    public function test_refund_restock_items_control(): void
    {
        $initialStock = $this->variant->stock; // 50

        $order = Order::create([
            'user_id' => $this->farmer->id,
            'farm_id' => $this->farm->id,
            'status' => 'confirmed',
            'channel' => 'web',
            'payment_mode' => 'cash',
            'payment_status' => 'paid',
            'total' => 2000.00,
            'delivery_address' => 'Khulna',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_variant_id' => $this->variant->id,
            'price_at_purchase' => 1000.00,
            'quantity' => 2,
            'total' => 2000.00,
        ]);

        $payment = Payment::create([
            'order_id' => $order->id,
            'user_id' => $this->farmer->id,
            'method' => 'cash',
            'amount' => 2000.00,
            'status' => 'success',
            'paid_at' => now(),
        ]);

        // Case A: Refund WITHOUT restock (restock_items = false)
        $resNoRestock = $this->actingAs($this->admin)->postJson("/api/v1/admin/payments/{$payment->id}/refund", [
            'amount' => 1000.00,
            'reason' => 'Quality dispute, items discarded on-site',
            'restock_items' => false,
        ]);

        $resNoRestock->assertOk();
        $this->variant->refresh();
        $this->assertEquals($initialStock, $this->variant->stock, 'Stock must NOT change when restock_items is false');

        // Case B: Refund WITH explicit restock (restock_items = true)
        $resRestock = $this->actingAs($this->admin)->postJson("/api/v1/admin/payments/{$payment->id}/refund", [
            'amount' => 1000.00,
            'reason' => 'Item returned in sealed condition',
            'restock_items' => true,
        ]);

        $resRestock->assertOk();
        $this->variant->refresh();
        $this->assertEquals($initialStock + 2, $this->variant->stock, 'Stock MUST be incremented when restock_items is true');
    }

    public function test_sslcommerz_sub_channel_detection_and_search(): void
    {
        $order = Order::create([
            'user_id' => $this->farmer->id,
            'subtotal' => 2500.00,
            'total' => 2500.00,
            'channel' => 'self_service',
            'payment_mode' => 'sslcommerz',
            'payment_status' => 'paid',
            'gateway_transaction_id' => 'FL-TEST-BKASH-01',
            'gateway_payment_details' => [
                'card_brand' => 'BKASH',
                'card_type' => 'BKASH-BKash',
                'card_issuer' => 'bKash Mobile Financial Services',
                'bank_tran_id' => 'BKASH-TRX-998877',
                'card_no' => '01712345678',
            ],
            'status' => 'confirmed',
        ]);

        $payment = Payment::create([
            'order_id' => $order->id,
            'user_id' => $this->farmer->id,
            'method' => 'sslcommerz',
            'amount' => 2500.00,
            'status' => 'success',
            'gateway_transaction_id' => 'FL-TEST-BKASH-01',
            'gateway_response' => [
                'card_brand' => 'BKASH',
                'card_type' => 'BKASH-BKash',
                'card_issuer' => 'bKash Mobile Financial Services',
                'bank_tran_id' => 'BKASH-TRX-998877',
                'card_no' => '01712345678',
            ],
            'paid_at' => now(),
        ]);

        $this->assertEquals('bKash', $payment->sub_method);
        $this->assertEquals('BKASH-TRX-998877', $payment->bank_tran_id);
        $this->assertEquals('bKash', $order->sub_method);

        // API resource check
        $res = $this->actingAs($this->admin)->getJson("/api/v1/admin/payments/{$payment->id}");
        $res->assertOk()
            ->assertJsonPath('data.sub_method', 'bKash')
            ->assertJsonPath('data.bank_tran_id', 'BKASH-TRX-998877')
            ->assertJsonPath('data.card_issuer', 'bKash Mobile Financial Services');

        // Search by bank transaction ID
        $searchRes = $this->actingAs($this->admin)->getJson('/api/v1/admin/payments?search=BKASH-TRX-998877');
        $searchRes->assertOk();
        $this->assertCount(1, $searchRes->json('data'));
        $this->assertEquals($payment->id, $searchRes->json('data.0.id'));

        // Filter by sub_method
        $filterRes = $this->actingAs($this->admin)->getJson('/api/v1/admin/payments?sub_method=bkash');
        $filterRes->assertOk();
        $this->assertCount(1, $filterRes->json('data'));
        $this->assertEquals('bKash', $filterRes->json('data.0.sub_method'));
    }

    public function test_cod_order_auto_settles_payment_when_status_updated_to_delivered(): void
    {
        $order = Order::create([
            'user_id' => $this->farmer->id,
            'subtotal' => 1500.00,
            'total' => 1500.00,
            'channel' => 'self_service',
            'payment_mode' => 'cod',
            'payment_status' => 'unpaid',
            'status' => 'dispatched',
        ]);

        $payment = Payment::create([
            'order_id' => $order->id,
            'user_id' => $this->farmer->id,
            'method' => 'cod',
            'amount' => 1500.00,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->admin)->patchJson("/api/v1/admin/orders/{$order->id}/status", [
            'status' => 'delivered',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'delivered')
            ->assertJsonPath('data.payment_status', 'paid');

        $order->refresh();
        $payment->refresh();

        $this->assertEquals('delivered', $order->status);
        $this->assertEquals('paid', $order->payment_status);
        $this->assertNotNull($order->paid_at);

        $this->assertEquals('success', $payment->status);
        $this->assertNotNull($payment->paid_at);
        $this->assertStringContainsString('Cash collected upon delivery', $payment->notes);
    }

    public function test_cod_order_auto_creates_and_settles_payment_if_missing_when_delivered(): void
    {
        $order = Order::create([
            'user_id' => $this->farmer->id,
            'subtotal' => 800.00,
            'total' => 800.00,
            'channel' => 'self_service',
            'payment_mode' => 'cod',
            'payment_status' => 'unpaid',
            'status' => 'confirmed',
        ]);

        $this->assertDatabaseMissing('payments', ['order_id' => $order->id]);

        $response = $this->actingAs($this->admin)->patchJson("/api/v1/admin/orders/{$order->id}/status", [
            'status' => 'delivered',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'delivered')
            ->assertJsonPath('data.payment_status', 'paid');

        $order->refresh();
        $this->assertEquals('paid', $order->payment_status);

        $payment = Payment::where('order_id', $order->id)->first();
        $this->assertNotNull($payment);
        $this->assertEquals('success', $payment->status);
        $this->assertEquals(800.00, (float) $payment->amount);
        $this->assertEquals('cod', $payment->method);
        $this->assertStringContainsString('Cash collected upon delivery', $payment->notes);
    }

    public function test_cod_order_with_farm_records_farm_ledger_expense_on_delivery(): void
    {
        $order = Order::create([
            'user_id' => $this->farmer->id,
            'farm_id' => $this->farm->id,
            'subtotal' => 1200.00,
            'total' => 1200.00,
            'channel' => 'self_service',
            'payment_mode' => 'cod',
            'payment_status' => 'unpaid',
            'status' => 'dispatched',
        ]);

        $response = $this->actingAs($this->admin)->patchJson("/api/v1/admin/orders/{$order->id}/status", [
            'status' => 'delivered',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('farm_ledger_entries', [
            'source' => 'system_order',
            'source_reference_id' => $order->id,
            'farm_id' => $this->farm->id,
        ]);
    }

    public function test_admin_order_show_loads_customer_recent_attempts(): void
    {
        $order1 = Order::create([
            'user_id' => $this->farmer->id,
            'subtotal' => 2000.00,
            'total' => 2000.00,
            'channel' => 'self_service',
            'payment_mode' => 'sslcommerz',
            'payment_status' => 'failed',
            'status' => 'cancelled',
            'created_at' => now()->subHours(2),
        ]);

        $order2 = Order::create([
            'user_id' => $this->farmer->id,
            'subtotal' => 1400.00,
            'total' => 1400.00,
            'channel' => 'self_service',
            'payment_mode' => 'sslcommerz',
            'payment_status' => 'unpaid',
            'status' => 'pending_payment',
            'created_at' => now()->subHour(),
        ]);

        $res = $this->actingAs($this->admin)->getJson("/api/v1/admin/orders/{$order2->id}");
        $res->assertOk();

        $recent = $res->json('data.recent_attempts');
        $this->assertIsArray($recent);
        $this->assertCount(1, $recent);
        $this->assertEquals($order1->id, $recent[0]['id']);
    }

    public function test_admin_order_index_attaches_customer_24h_attempt_count(): void
    {
        Order::create([
            'user_id' => $this->farmer->id,
            'subtotal' => 500.00,
            'total' => 500.00,
            'channel' => 'self_service',
            'payment_mode' => 'cod',
            'status' => 'pending',
            'created_at' => now()->subMinutes(10),
        ]);

        Order::create([
            'user_id' => $this->farmer->id,
            'subtotal' => 600.00,
            'total' => 600.00,
            'channel' => 'self_service',
            'payment_mode' => 'cod',
            'status' => 'confirmed',
            'created_at' => now()->subMinutes(5),
        ]);

        $res = $this->actingAs($this->admin)->getJson('/api/v1/admin/orders');
        $res->assertOk();

        $first = $res->json('data.0');
        $this->assertEquals(2, $first['customer_24h_orders_count']);
    }

    public function test_admin_check_gateway_status_reconciles_valid_payment(): void
    {
        $order = Order::create([
            'user_id' => $this->farmer->id,
            'subtotal' => 1400.00,
            'total' => 1400.00,
            'channel' => 'self_service',
            'payment_mode' => 'sslcommerz',
            'gateway_transaction_id' => 'FL-ORD-RECON-01',
            'payment_status' => 'unpaid',
            'status' => 'pending_payment',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_variant_id' => $this->variant->id,
            'quantity' => 1,
            'price_at_purchase' => 1400.00,
            'unit_price' => 1400.00,
        ]);

        Http::fake([
            'https://sandbox.sslcommerz.com/validator/api/merchantTransIDvalidationAPI.php*' => Http::response([
                'APIConnect' => 'DONE',
                'status' => 'VALID',
                'total_records' => 1,
                'element' => [
                    [
                        'val_id' => 'VAL-RECON-1234',
                        'status' => 'VALID',
                        'tran_id' => 'FL-ORD-RECON-01',
                        'amount' => '1400.00',
                        'currency' => 'BDT',
                        'card_brand' => 'BKASH',
                        'card_type' => 'BKASH-BKash',
                        'card_issuer' => 'bKash Mobile Financial Services',
                        'bank_tran_id' => 'BKASH-TRX-5544',
                        'card_no' => '01712345678',
                    ],
                ],
            ], 200),
        ]);

        $res = $this->actingAs($this->admin)->postJson("/api/v1/admin/orders/{$order->id}/check-gateway-status");
        $res->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('is_paid', true)
            ->assertJsonPath('gateway_status', 'VALID');

        $order->refresh();
        $this->assertEquals('paid', $order->payment_status);
        $this->assertEquals('pending', $order->status);
        $this->assertEquals('bKash', $order->sub_method);
        $this->assertEquals('BKASH-TRX-5544', $order->bank_tran_id);
    }

    public function test_admin_check_gateway_status_reports_unattempted_without_marking_paid(): void
    {
        $order = Order::create([
            'user_id' => $this->farmer->id,
            'subtotal' => 1400.00,
            'total' => 1400.00,
            'channel' => 'self_service',
            'payment_mode' => 'sslcommerz',
            'gateway_transaction_id' => 'FL-ORD-FAIL-01',
            'payment_status' => 'unpaid',
            'status' => 'pending_payment',
        ]);

        Http::fake([
            'https://sandbox.sslcommerz.com/validator/api/merchantTransIDvalidationAPI.php*' => Http::response([
                'APIConnect' => 'DONE',
                'status' => 'UNATTEMPTED',
                'total_records' => 0,
                'element' => [],
            ], 200),
        ]);

        $res = $this->actingAs($this->admin)->postJson("/api/v1/admin/orders/{$order->id}/check-gateway-status");
        $res->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('is_paid', false)
            ->assertJsonPath('gateway_status', 'UNATTEMPTED');

        $order->refresh();
        $this->assertEquals('unpaid', $order->payment_status);
        $this->assertEquals('pending_payment', $order->status);
    }

    public function test_manual_mark_as_paid_on_pending_payment_order_decrements_deferred_stock_and_advances_status(): void
    {
        $this->variant->update(['stock' => 50]);
        $this->product->update(['stock' => 50]);

        $order = Order::create([
            'user_id' => $this->farmer->id,
            'subtotal' => 2000.00,
            'total' => 2000.00,
            'channel' => 'self_service',
            'payment_mode' => 'sslcommerz',
            'gateway_transaction_id' => 'FL-ORD-MANUAL-01',
            'payment_status' => 'unpaid',
            'status' => 'pending_payment',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_variant_id' => $this->variant->id,
            'quantity' => 2,
            'price_at_purchase' => 1000.00,
            'unit_price' => 1000.00,
        ]);

        $payment = Payment::create([
            'order_id' => $order->id,
            'user_id' => $this->farmer->id,
            'method' => 'sslcommerz',
            'amount' => 2000.00,
            'status' => 'pending',
            'gateway_transaction_id' => 'FL-ORD-MANUAL-01',
        ]);

        // Manually mark as paid by admin with MFS TrxID in notes
        $res = $this->actingAs($this->admin)->postJson("/api/v1/admin/payments/{$payment->id}/mark-as-paid", [
            'notes' => 'Customer confirmed bKash TrxID 9K28X1A; verified on statement',
        ]);

        $res->assertOk();

        $order->refresh();
        $payment->refresh();
        $this->variant->refresh();
        $this->product->refresh();

        $this->assertEquals('paid', $order->payment_status);
        $this->assertEquals('pending', $order->status, 'Order status must advance from pending_payment to pending for fulfillment');
        $this->assertEquals('success', $payment->status);
        $this->assertEquals(48, $this->variant->stock, 'Variant stock must be decremented on manual payment confirmation');
        $this->assertEquals(48, $this->product->stock, 'Product stock must be decremented on manual payment confirmation');
    }

    public function test_reconciling_two_day_old_cancelled_order_revives_order_and_decrements_stock(): void
    {
        $this->variant->update(['stock' => 50]);
        $this->product->update(['stock' => 50]);

        // Order placed 2 days ago and auto-cancelled on timeout
        $order = Order::create([
            'user_id' => $this->farmer->id,
            'subtotal' => 1400.00,
            'total' => 1400.00,
            'channel' => 'self_service',
            'payment_mode' => 'sslcommerz',
            'gateway_transaction_id' => 'FL-ORD-2DAY-OLD',
            'payment_status' => 'failed',
            'status' => 'cancelled',
            'notes' => '[System: Auto-cancelled due to payment session timeout after 45 mins]',
            'created_at' => now()->subDays(2),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_variant_id' => $this->variant->id,
            'quantity' => 1,
            'price_at_purchase' => 1400.00,
            'unit_price' => 1400.00,
        ]);

        // Fake SSLCommerz returning VALID for this 2-day old transaction
        Http::fake([
            'https://sandbox.sslcommerz.com/validator/api/merchantTransIDvalidationAPI.php*' => Http::response([
                'APIConnect' => 'DONE',
                'status' => 'VALID',
                'total_records' => 1,
                'element' => [
                    [
                        'val_id' => 'VAL-2DAYS-5544',
                        'status' => 'VALID',
                        'tran_id' => 'FL-ORD-2DAY-OLD',
                        'amount' => '1400.00',
                        'currency' => 'BDT',
                        'card_brand' => 'BKASH',
                        'card_type' => 'BKASH-BKash',
                        'card_issuer' => 'bKash Mobile Financial Services',
                        'bank_tran_id' => 'BKASH-TRX-2DAY-99',
                        'card_no' => '01712345678',
                    ],
                ],
            ], 200),
        ]);

        // Admin triggers Check Live Gateway Status on this 2-day old order
        $res = $this->actingAs($this->admin)->postJson("/api/v1/admin/orders/{$order->id}/check-gateway-status");
        $res->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('is_paid', true)
            ->assertJsonPath('gateway_status', 'VALID');

        $order->refresh();
        $this->variant->refresh();

        $this->assertEquals('paid', $order->payment_status);
        $this->assertEquals('pending', $order->status, '2-day-old cancelled order must be revived to pending fulfillment');
        $this->assertEquals('bKash', $order->sub_method);
        $this->assertEquals('BKASH-TRX-2DAY-99', $order->bank_tran_id);
        $this->assertEquals(49, $this->variant->stock, 'Stock must be secured on revival');
    }

    public function test_admin_can_mark_order_as_paid_directly_via_order_endpoint(): void
    {
        $this->variant->update(['stock' => 50]);
        $this->product->update(['stock' => 50]);

        // Customer made an order 2 days ago, currently awaiting payment
        $order = Order::create([
            'user_id' => $this->farmer->id,
            'farm_id' => $this->farm->id,
            'subtotal' => 2000.00,
            'total' => 2000.00,
            'channel' => 'self_service',
            'payment_mode' => 'sslcommerz',
            'gateway_transaction_id' => 'FL-ORD-MANUAL-MARK-1',
            'payment_status' => 'pending',
            'status' => 'pending_payment',
            'created_at' => now()->subDays(2),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_variant_id' => $this->variant->id,
            'quantity' => 2,
            'price_at_purchase' => 1000.00,
            'unit_price' => 1000.00,
        ]);

        // Admin marks as paid with TrxID, sub_method and notes
        $response = $this->actingAs($this->admin)->postJson("/api/v1/admin/orders/{$order->id}/mark-as-paid", [
            'bank_tran_id' => 'BKASH-MANUAL-TRX-8899',
            'sub_method' => 'bKash',
            'notes' => 'Customer confirmed paid via bKash 2 days ago, verified on SSL portal.',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $order->refresh();
        $this->variant->refresh();
        $this->product->refresh();

        $this->assertEquals('paid', $order->payment_status);
        $this->assertEquals('pending', $order->status, 'Order status should advance to pending for fulfillment');
        $this->assertEquals('bKash', $order->sub_method);
        $this->assertEquals('BKASH-MANUAL-TRX-8899', $order->bank_tran_id);
        $this->assertEquals(48, $this->variant->stock, 'Variant stock must be decremented on mark-as-paid');
        $this->assertEquals(48, $this->product->stock, 'Product stock must be decremented on mark-as-paid');

        // Verify payment record was created and updated
        $this->assertNotNull($order->payment);
        $this->assertEquals('success', $order->payment->status);
        $this->assertEquals(2000.00, (float) $order->payment->amount);
    }
}

