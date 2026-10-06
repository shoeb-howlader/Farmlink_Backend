<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\FarmLedgerEntry;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SSLCommerzPaymentTest extends TestCase
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

        $this->farmer = User::factory()->create();
        $this->farmer->assignRole('farmer');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->farm = Farm::factory()->create([
            'user_id' => $this->farmer->id,
            'farm_name' => 'Sunrise Aquaculture Pond',
        ]);

        $this->product = Product::factory()->create([
            'name' => 'Bio-Armor Aqua 500g',
            'category' => 'water_treatment',
            'price' => 500.00,
            'stock' => 20,
            'is_active' => true,
        ]);
        $this->product->variants()->delete();

        $this->variant = ProductVariant::create([
            'product_id' => $this->product->id,
            'variant_label' => '500g Container',
            'sku' => 'BIO-500G',
            'price' => 500.00,
            'compare_at_price' => 600.00,
            'stock' => 20,
            'is_default' => true,
        ]);
    }

    public function test_checkout_with_sslcommerz_creates_pending_payment_order_and_defers_stock_decrement(): void
    {
        $tranId = 'FL-TEST-' . uniqid();

        // Mock SSLCommerz session API
        Http::fake([
            'https://sandbox.sslcommerz.com/gwprocess/v4/api.php' => Http::response([
                'status' => 'SUCCESS',
                'failedreason' => '',
                'sessionkey' => 'TEST_SESSION_KEY_123',
                'GatewayPageURL' => 'https://sandbox.sslcommerz.com/gwprocess/v4/gw.php?Q=test',
            ], 200),
        ]);

        $response = $this->actingAs($this->farmer)->postJson('/api/v1/orders', [
            'farm_id' => $this->farm->id,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'product_variant_id' => $this->variant->id,
                    'quantity' => 3,
                ],
            ],
            'payment_mode' => 'sslcommerz',
            'delivery_address' => 'Pond 4, Khulna Sadar',
        ]);

        $response->assertCreated()
            ->assertJsonPath('status', 'pending_payment')
            ->assertJsonStructure(['data' => ['id', 'status', 'total'], 'gateway_url', 'tran_id']);

        $orderId = $response->json('data.id');
        $order = Order::find($orderId);

        $this->assertNotNull($order);
        $this->assertEquals('pending_payment', $order->status);
        $this->assertEquals('sslcommerz', $order->payment_mode);
        $this->assertEquals('pending', $order->payment_status);
        $this->assertNotNull($order->gateway_transaction_id);

        // Crucial requirement: Stock was NOT decremented yet! Deferred stock decrement
        $this->variant->refresh();
        $this->assertEquals(20, $this->variant->stock, 'Variant stock must remain 20 before payment verification');

        // Farm ledger entry should NOT be recorded before payment
        $ledgerEntry = FarmLedgerEntry::where('source_reference_id', $orderId)->first();
        $this->assertNull($ledgerEntry, 'Farm ledger entry must not be recorded prior to payment confirmation');
    }

    public function test_browser_redirect_does_not_mark_order_paid(): void
    {
        $order = Order::create([
            'user_id' => $this->farmer->id,
            'farm_id' => $this->farm->id,
            'status' => 'pending_payment',
            'channel' => 'self_service',
            'subtotal' => 1000.00,
            'total' => 1000.00,
            'payment_mode' => 'sslcommerz',
            'payment_status' => 'pending',
            'gateway_transaction_id' => 'FL-REDIRECT-TEST',
            'delivery_address' => 'Khulna',
        ]);

        // Browser return route simulates user arriving back on return URL
        $response = $this->post('/api/v1/payments/sslcommerz/return', [
            'status' => 'VALID',
            'tran_id' => 'FL-REDIRECT-TEST',
            'val_id' => 'VALIDATION_123',
            'amount' => '1000.00',
        ]);

        // Must redirect to confirming view and NOT mark order paid
        $response->assertRedirect();
        $this->assertStringContainsString('/orders/payment/confirming', $response->headers->get('Location'));

        $order->refresh();
        $this->assertEquals('pending_payment', $order->status, 'Browser redirect must NEVER mark order paid');
        $this->assertEquals('pending', $order->payment_status);
    }

    public function test_ipn_webhook_validates_transaction_and_decrements_stock_and_marks_paid(): void
    {
        $order = Order::create([
            'user_id' => $this->farmer->id,
            'farm_id' => $this->farm->id,
            'status' => 'pending_payment',
            'channel' => 'self_service',
            'subtotal' => 1000.00,
            'total' => 1000.00,
            'payment_mode' => 'sslcommerz',
            'payment_status' => 'pending',
            'gateway_transaction_id' => 'FL-IPN-TRAN-999',
            'delivery_address' => 'Satkhira',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_variant_id' => $this->variant->id,
            'variant_label' => '500g Container',
            'price_at_purchase' => 500.00,
            'quantity' => 2,
            'total' => 1000.00,
        ]);

        $this->assertEquals(20, $this->variant->stock);

        // Mock SSLCommerz server-to-server Validation API
        Http::fake([
            'https://sandbox.sslcommerz.com/validator/api/validationserverAPI.php*' => Http::response([
                'status' => 'VALID',
                'tran_id' => 'FL-IPN-TRAN-999',
                'val_id' => 'VAL-SECURE-999',
                'amount' => '1000.00',
                'currency' => 'BDT',
                'card_type' => 'BKASH-BKash',
                'bank_tran_id' => 'BANK_TRX_123',
            ], 200),
        ]);

        // Trigger IPN Webhook
        $response = $this->postJson('/api/v1/payments/sslcommerz/ipn', [
            'status' => 'VALID',
            'tran_id' => 'FL-IPN-TRAN-999',
            'val_id' => 'VAL-SECURE-999',
            'amount' => '1000.00',
            'currency' => 'BDT',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success');

        // Order is now paid and moved to pending fulfillment
        $order->refresh();
        $this->assertEquals('pending', $order->status);
        $this->assertEquals('paid', $order->payment_status);
        $this->assertNotNull($order->paid_at);

        // Stock was atomically decremented by 2
        $this->variant->refresh();
        $this->assertEquals(18, $this->variant->stock, 'Variant stock must be decremented upon verified IPN');

        // Farm ledger expense was recorded
        $ledgerEntry = FarmLedgerEntry::where('source_reference_id', $order->id)->first();
        $this->assertNotNull($ledgerEntry);
        $this->assertEquals('expense', $ledgerEntry->type);
        $this->assertEquals(1000.00, (float) $ledgerEntry->amount);
    }

    public function test_expired_pending_payment_orders_are_cancelled_without_altering_stock(): void
    {
        $order = Order::create([
            'user_id' => $this->farmer->id,
            'farm_id' => $this->farm->id,
            'status' => 'pending_payment',
            'channel' => 'self_service',
            'subtotal' => 500.00,
            'total' => 500.00,
            'payment_mode' => 'sslcommerz',
            'payment_status' => 'pending',
            'gateway_transaction_id' => 'FL-EXPIRED-TRAN',
            'delivery_address' => 'Bagerhat',
            'created_at' => now()->subHours(2),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_variant_id' => $this->variant->id,
            'price_at_purchase' => 500.00,
            'quantity' => 1,
            'total' => 500.00,
        ]);

        $initialStock = $this->variant->stock; // 20

        // Run cancel-expired command
        $this->artisan('payments:cancel-expired')
            ->assertSuccessful();

        $order->refresh();
        $this->assertEquals('cancelled', $order->status);

        // Stock was never decremented, so cancelling must NOT increment or alter it
        $this->variant->refresh();
        $this->assertEquals($initialStock, $this->variant->stock, 'Variant stock must remain unchanged after cancelling pending payment');
    }

    public function test_admin_cancelling_paid_sslcommerz_order_triggers_refund_api(): void
    {
        $order = Order::create([
            'user_id' => $this->farmer->id,
            'farm_id' => $this->farm->id,
            'status' => 'confirmed',
            'channel' => 'self_service',
            'subtotal' => 1000.00,
            'total' => 1000.00,
            'payment_mode' => 'sslcommerz',
            'payment_status' => 'paid',
            'gateway_transaction_id' => 'FL-REFUND-TRAN',
            'paid_at' => now()->subDay(),
            'delivery_address' => 'Khulna',
        ]);

        // Mock SSLCommerz refund API
        Http::fake([
            'https://sandbox.sslcommerz.com/validator/api/merchantTransIDvalidationAPI.php*' => Http::response([
                'status' => 'success',
                'refund_ref_id' => 'REFUND-REF-777',
                'errorReason' => '',
            ], 200),
        ]);

        // Admin cancels the order
        $response = $this->actingAs($this->admin)->patchJson("/api/v1/admin/orders/{$order->id}/status", [
            'status' => 'cancelled',
        ]);

        $response->assertOk();

        $order->refresh();
        $this->assertEquals('cancelled', $order->status);
        $this->assertEquals('completed', $order->refund_status);
        $this->assertEquals('REFUND-REF-777', $order->refund_reference);
        $this->assertEquals(1000.00, (float) $order->refund_amount);
        $this->assertNotNull($order->refunded_at);
    }

    public function test_admin_dedicated_refund_endpoint(): void
    {
        $order = Order::create([
            'user_id' => $this->farmer->id,
            'farm_id' => $this->farm->id,
            'status' => 'confirmed',
            'channel' => 'self_service',
            'subtotal' => 1000.00,
            'total' => 1000.00,
            'payment_mode' => 'sslcommerz',
            'payment_status' => 'paid',
            'gateway_transaction_id' => 'FL-MANUAL-REFUND',
            'paid_at' => now()->subDay(),
            'delivery_address' => 'Khulna',
        ]);

        Http::fake([
            'https://sandbox.sslcommerz.com/validator/api/merchantTransIDvalidationAPI.php*' => Http::response([
                'status' => 'success',
                'refund_ref_id' => 'MANUAL-REF-888',
                'errorReason' => '',
            ], 200),
        ]);

        $response = $this->actingAs($this->admin)->postJson("/api/v1/admin/orders/{$order->id}/refund", [
            'amount' => 500.00,
            'reason' => 'Partial customer compensation',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.refund_status', 'completed')
            ->assertJsonPath('data.refund_reference', 'MANUAL-REF-888');

        $order->refresh();
        $this->assertEquals('completed', $order->refund_status);
        $this->assertEquals(500.00, (float) $order->refund_amount);
    }
}
