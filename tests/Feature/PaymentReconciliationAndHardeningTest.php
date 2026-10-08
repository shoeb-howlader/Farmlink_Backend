<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\SSLCommerzService;
use App\Services\SystemAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PaymentReconciliationAndHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected User $farmer;
    protected Farm $farm;
    protected Product $product;
    protected ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'farmer', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $this->farmer = User::factory()->create([
            'phone_verified_at' => now(),
            'is_active' => true,
        ]);
        $this->farmer->assignRole('farmer');

        $this->farm = Farm::factory()->create([
            'user_id' => $this->farmer->id,
        ]);

        $this->product = Product::factory()->create([
            'name' => 'Mega Feed 25kg',
            'stock' => 100,
            'is_active' => true,
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $this->product->id,
            'variant_label' => 'Standard Bag',
            'sku' => 'MEGA-25-STD',
            'price' => 1200.00,
            'stock' => 50,
            'is_active' => true,
        ]);
    }

    protected function createPendingOrder(int $quantity = 2, float $total = 2400.00, string $tranId = 'FL-TEST-TXN-001'): Order
    {
        $order = Order::create([
            'user_id' => $this->farmer->id,
            'farm_id' => $this->farm->id,
            'status' => 'pending_payment',
            'payment_status' => 'pending',
            'payment_mode' => 'sslcommerz',
            'total' => $total,
            'subtotal' => $total,
            'delivery_fee' => 0.00,
            'gateway_transaction_id' => $tranId,
            'recipient_name' => 'Test Farmer',
            'recipient_phone' => '01711223344',
            'delivery_address' => 'Mymensingh Sadar',
            'district' => 'Mymensingh',
            'created_at' => now()->subMinutes(10),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_variant_id' => $this->variant->id,
            'quantity' => $quantity,
            'price_at_purchase' => 1200.00,
        ]);

        return $order;
    }

    public function test_reconcile_command_completes_paid_order_with_missed_ipn(): void
    {
        $order = $this->createPendingOrder(2, 2400.00, 'FL-RECONCILE-PAID-001');

        $mockSsl = $this->mock(SSLCommerzService::class);
        $mockSsl->shouldReceive('queryTransaction')
            ->with('FL-RECONCILE-PAID-001')
            ->once()
            ->andReturn([
                'status' => 'VALID',
                'tran_id' => 'FL-RECONCILE-PAID-001',
                'val_id' => 'VAL-RECONCILE-12345',
                'amount' => '2400.00',
                'currency' => 'BDT',
            ]);

        $mockSsl->shouldReceive('processValidatedPayment')
            ->once()
            ->andReturnUsing(function ($data, $valId) use ($order) {
                // Execute actual logic
                $service = new SSLCommerzService();
                return $service->processValidatedPayment($data, $valId);
            });

        $initialVariantStock = $this->variant->stock;

        $this->artisan('payments:reconcile', ['--age' => 5, '--timeout' => 60])
            ->assertExitCode(0);

        $order->refresh();
        $this->variant->refresh();

        $this->assertEquals('paid', $order->payment_status);
        $this->assertEquals('pending', $order->status);
        $this->assertEquals($initialVariantStock - 2, $this->variant->stock);

        $payment = Payment::where('order_id', $order->id)->first();
        $this->assertNotNull($payment);
        $this->assertEquals('success', $payment->status);
        $this->assertEquals('FL-RECONCILE-PAID-001', $payment->gateway_transaction_id);
    }

    public function test_reconcile_command_marks_failed_order_when_gateway_reports_failed(): void
    {
        $order = $this->createPendingOrder(2, 2400.00, 'FL-RECONCILE-FAIL-001');

        $mockSsl = $this->mock(SSLCommerzService::class);
        $mockSsl->shouldReceive('queryTransaction')
            ->with('FL-RECONCILE-FAIL-001')
            ->once()
            ->andReturn([
                'status' => 'FAILED',
                'tran_id' => 'FL-RECONCILE-FAIL-001',
            ]);

        $initialVariantStock = $this->variant->stock;

        $this->artisan('payments:reconcile', ['--age' => 5, '--timeout' => 60])
            ->assertExitCode(0);

        $order->refresh();
        $this->variant->refresh();

        $this->assertEquals('payment_failed', $order->status);
        $this->assertEquals('failed', $order->payment_status);
        $this->assertEquals($initialVariantStock, $this->variant->stock); // No stock was decremented
    }

    public function test_reconcile_command_cancels_old_unpaid_order_exceeding_timeout(): void
    {
        $order = $this->createPendingOrder(1, 1200.00, 'FL-RECONCILE-OLD-001');
        // Set creation date back 70 minutes ago
        $order->update(['created_at' => now()->subMinutes(70)]);

        $mockSsl = $this->mock(SSLCommerzService::class);
        $mockSsl->shouldReceive('queryTransaction')
            ->with('FL-RECONCILE-OLD-001')
            ->once()
            ->andReturn([
                'status' => 'UNATTEMPTED',
            ]);

        $this->artisan('payments:reconcile', ['--age' => 5, '--timeout' => 60])
            ->assertExitCode(0);

        $order->refresh();
        $this->assertEquals('cancelled', $order->status);
        $this->assertEquals('failed', $order->payment_status);
    }

    public function test_reconcile_command_twice_changes_nothing_the_second_time(): void
    {
        $order = $this->createPendingOrder(1, 1200.00, 'FL-RECONCILE-IDEM-001');

        $mockSsl = $this->mock(SSLCommerzService::class);
        $mockSsl->shouldReceive('queryTransaction')
            ->with('FL-RECONCILE-IDEM-001')
            ->once()
            ->andReturn([
                'status' => 'VALID',
                'tran_id' => 'FL-RECONCILE-IDEM-001',
                'val_id' => 'VAL-IDEM-123',
                'amount' => '1200.00',
                'currency' => 'BDT',
            ]);

        $mockSsl->shouldReceive('processValidatedPayment')
            ->once()
            ->andReturnUsing(function ($data, $valId) {
                return (new SSLCommerzService())->processValidatedPayment($data, $valId);
            });

        // Run 1: Settles payment
        $this->artisan('payments:reconcile', ['--age' => 5, '--timeout' => 60])->assertExitCode(0);

        $order->refresh();
        $this->assertEquals('paid', $order->payment_status);
        $variantStockAfterFirst = $this->variant->fresh()->stock;

        // Run 2: Finds 0 pending orders, nothing changes
        $this->artisan('payments:reconcile', ['--age' => 5, '--timeout' => 60])->assertExitCode(0);

        $this->assertEquals($variantStockAfterFirst, $this->variant->fresh()->stock);
    }

    public function test_duplicate_ipn_does_not_double_decrement_stock_or_create_duplicate_payment(): void
    {
        $order = $this->createPendingOrder(2, 2400.00, 'FL-IPN-DUP-001');
        $initialStock = $this->variant->stock;

        $sslService = new SSLCommerzService();

        $valData = [
            'status' => 'VALID',
            'tran_id' => 'FL-IPN-DUP-001',
            'val_id' => 'VAL-DUP-777',
            'amount' => '2400.00',
            'currency' => 'BDT',
        ];

        // First IPN
        $res1 = $sslService->processValidatedPayment($valData, 'VAL-DUP-777');
        $this->assertTrue($res1['success']);
        $this->assertEquals($initialStock - 2, $this->variant->fresh()->stock);
        $this->assertEquals(1, Payment::where('order_id', $order->id)->count());

        // Second duplicate IPN
        $res2 = $sslService->processValidatedPayment($valData, 'VAL-DUP-777');
        $this->assertTrue($res2['success']);
        $this->assertTrue($res2['already_processed'] ?? false);

        // Stock must still be decremented only once
        $this->assertEquals($initialStock - 2, $this->variant->fresh()->stock);
        // Payment record must remain exactly 1
        $this->assertEquals(1, Payment::where('order_id', $order->id)->count());
    }

    public function test_amount_mismatch_flags_order_for_review_and_does_not_complete_payment(): void
    {
        $order = $this->createPendingOrder(2, 2400.00, 'FL-IPN-TAMPER-001');
        $initialStock = $this->variant->stock;

        $sslService = new SSLCommerzService();

        // Tampered amount (only 500 BDT paid instead of 2400)
        $valData = [
            'status' => 'VALID',
            'tran_id' => 'FL-IPN-TAMPER-001',
            'val_id' => 'VAL-TAMPER-999',
            'amount' => '500.00',
            'currency' => 'BDT',
        ];

        $result = $sslService->processValidatedPayment($valData, 'VAL-TAMPER-999');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('amount', strtolower($result['message']));

        $order->refresh();
        $this->assertEquals('paid_needs_review', $order->status);
        $this->assertEquals('pending', $order->payment_status);
        $this->assertEquals($initialStock, $this->variant->fresh()->stock); // Stock protected
    }

    public function test_unknown_transaction_id_is_rejected_safely(): void
    {
        $sslService = new SSLCommerzService();

        $valData = [
            'status' => 'VALID',
            'tran_id' => 'UNKNOWN-NONEXISTENT-TRAN-ID',
            'val_id' => 'VAL-UNKNOWN-111',
            'amount' => '100.00',
            'currency' => 'BDT',
        ];

        $result = $sslService->processValidatedPayment($valData, 'VAL-UNKNOWN-111');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('not found', strtolower($result['message']));
    }

    public function test_late_payment_with_depleted_stock_marks_needs_review_without_overselling(): void
    {
        // Order for 5 items
        $order = $this->createPendingOrder(5, 6000.00, 'FL-LATE-DEPLETED-001');

        // Variant stock was bought out while farmer was completing checkout!
        $this->variant->update(['stock' => 2]); // only 2 left, but order requires 5

        $sslService = new SSLCommerzService();

        $valData = [
            'status' => 'VALID',
            'tran_id' => 'FL-LATE-DEPLETED-001',
            'val_id' => 'VAL-LATE-555',
            'amount' => '6000.00',
            'currency' => 'BDT',
        ];

        $result = $sslService->processValidatedPayment($valData, 'VAL-LATE-555');

        $this->assertTrue($result['success']);
        $this->assertTrue($result['stock_shortage'] ?? false);
        $this->assertTrue($result['needs_review'] ?? false);

        $order->refresh();
        $this->assertEquals('paid_needs_review', $order->status);
        $this->assertEquals('paid', $order->payment_status);
        // Stock must NOT be negative! Remains at 2 without overselling
        $this->assertEquals(2, $this->variant->fresh()->stock);

        $payment = Payment::where('order_id', $order->id)->first();
        $this->assertNotNull($payment);
        $this->assertEquals('success', $payment->status);
    }
}
