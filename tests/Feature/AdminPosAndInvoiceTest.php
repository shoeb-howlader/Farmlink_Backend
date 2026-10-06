<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Farm;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminPosAndInvoiceTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $deo;
    protected User $farmer;
    protected User $otherFarmer;
    protected Farm $farm1;
    protected Farm $farm2;
    protected Product $productA;
    protected Product $productB;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'data_entry_operator', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'farmer', 'guard_name' => 'web']);

        $this->admin = User::factory()->create(['gender' => 'female']);
        $this->admin->assignRole('admin');

        $this->deo = User::factory()->create(['gender' => 'male']);
        $this->deo->assignRole('data_entry_operator');

        $this->farmer = User::factory()->create(['name' => 'Abdul Karim', 'gender' => 'male']);
        $this->farmer->assignRole('farmer');

        $this->otherFarmer = User::factory()->create(['name' => 'Hasan Mahmud', 'gender' => 'male']);
        $this->otherFarmer->assignRole('farmer');

        $this->farm1 = Farm::factory()->create([
            'user_id' => $this->farmer->id,
            'farm_name' => 'Karim Aqua 1',
            'district' => 'Satkhira',
        ]);

        $this->productA = Product::factory()->create([
            'name' => 'Shrimp Feed Pro',
            'price' => 500.00,
            'stock' => 20,
            'is_active' => true,
        ]);

        $this->productB = Product::factory()->create([
            'name' => 'Water Conditioner',
            'price' => 250.00,
            'stock' => 5,
            'is_active' => true,
        ]);
    }

    public function test_invoice_numbers_are_automatically_generated_and_sequential(): void
    {
        $order1 = Order::create([
            'user_id' => $this->farmer->id,
            'total' => 1000,
            'status' => 'pending',
        ]);

        $order2 = Order::create([
            'user_id' => $this->farmer->id,
            'total' => 500,
            'status' => 'confirmed',
        ]);

        $year = date('Y');
        $this->assertStringStartsWith("INV-{$year}-", $order1->invoice_number);
        $this->assertStringStartsWith("INV-{$year}-", $order2->invoice_number);

        // Sequence must increment
        $seq1 = (int) substr($order1->invoice_number, -5);
        $seq2 = (int) substr($order2->invoice_number, -5);
        $this->assertEquals($seq1 + 1, $seq2);
    }

    public function test_farmer_can_download_own_invoice_pdf_but_not_others(): void
    {
        $order = Order::create([
            'user_id' => $this->farmer->id,
            'farm_id' => $this->farm1->id,
            'total' => 500,
            'status' => 'confirmed',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->productA->id,
            'quantity' => 1,
            'price_at_purchase' => 500.00,
        ]);

        // 1. Owner can access invoice
        $response = $this->actingAs($this->farmer)->get("/api/v1/orders/{$order->id}/invoice");
        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));

        // 2. Another farmer cannot access
        $otherResponse = $this->actingAs($this->otherFarmer)->get("/api/v1/orders/{$order->id}/invoice");
        $otherResponse->assertForbidden();
    }

    public function test_admin_and_deo_can_download_any_invoice(): void
    {
        $order = Order::create([
            'user_id' => $this->farmer->id,
            'farm_id' => $this->farm1->id,
            'total' => 500,
            'status' => 'confirmed',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->productA->id,
            'quantity' => 1,
            'price_at_purchase' => 500.00,
        ]);

        // Admin
        $adminRes = $this->actingAs($this->admin)->get("/api/v1/admin/orders/{$order->id}/invoice");
        $adminRes->assertOk();
        $this->assertStringContainsString('application/pdf', $adminRes->headers->get('content-type'));

        // DEO
        $deoRes = $this->actingAs($this->deo)->get("/api/v1/admin/orders/{$order->id}/invoice");
        $deoRes->assertOk();
        $this->assertStringContainsString('application/pdf', $deoRes->headers->get('content-type'));
    }

    public function test_admin_pos_sale_creates_order_decrements_stock_and_logs_audit(): void
    {
        $initialStock = $this->productA->stock;

        $payload = [
            'user_id' => $this->farmer->id,
            'items' => [
                [
                    'product_id' => $this->productA->id,
                    'quantity' => 3,
                    'price_at_purchase' => 450.00, // Manual discount from 500 to 450
                ],
            ],
            'payment_mode' => 'cash',
            'notes' => 'Walk-in assisted sale at depot',
        ];

        $response = $this->actingAs($this->admin)->postJson('/api/v1/admin/pos/sale', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.channel', 'admin_pos')
            ->assertJsonPath('data.payment_mode', 'cash')
            ->assertJsonPath('data.farm_id', $this->farm1->id) // Auto-assigned sole farm
            ->assertJsonPath('data.subtotal', fn ($v) => (float) $v === 1350.0) // 3 * 450
            ->assertJsonPath('data.delivery_fee', fn ($v) => (float) $v === 50.0)
            ->assertJsonPath('data.total', fn ($v) => (float) $v === 1400.0); // 1350 + 50

        $orderId = $response->json('data.id');
        $this->assertNotNull($response->json('data.invoice_number'));

        // Stock must be decremented
        $this->productA->refresh();
        $this->assertEquals($initialStock - 3, $this->productA->stock);

        // Audit log must be recorded
        $log = ActivityLog::where('action', 'order.pos_sale')->where('subject_id', $orderId)->first();
        $this->assertNotNull($log);
        $this->assertEquals($this->admin->id, $log->user_id);
        $this->assertEquals(1400.0, $log->changes['total']);
    }

    public function test_deo_can_execute_pos_sale(): void
    {
        $payload = [
            'user_id' => $this->farmer->id,
            'items' => [
                [
                    'product_id' => $this->productB->id,
                    'quantity' => 2,
                ],
            ],
            'payment_mode' => 'cod',
        ];

        $response = $this->actingAs($this->deo)->postJson('/api/v1/admin/pos/sale', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.channel', 'admin_pos')
            ->assertJsonPath('data.payment_mode', 'cod')
            ->assertJsonPath('data.subtotal', fn ($v) => (float) $v === 500.0) // 2 * 250
            ->assertJsonPath('data.delivery_fee', fn ($v) => (float) $v === 50.0)
            ->assertJsonPath('data.total', fn ($v) => (float) $v === 550.0); // 500 + 50
    }

    public function test_pos_sale_with_over_the_counter_fulfillment_marks_delivered_with_zero_delivery_fee(): void
    {
        $payload = [
            'user_id' => $this->farmer->id,
            'items' => [
                [
                    'product_id' => $this->productA->id,
                    'quantity' => 1,
                ],
            ],
            'payment_mode' => 'cash',
            'fulfillment_type' => 'over_the_counter',
            'notes' => 'Direct counter handover at cash desk',
        ];

        $response = $this->actingAs($this->admin)->postJson('/api/v1/admin/pos/sale', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'delivered')
            ->assertJsonPath('data.payment_status', 'paid')
            ->assertJsonPath('data.delivery_fee', 0)
            ->assertJsonPath('data.delivery_address', 'Over-the-counter Depot Handover');

        $this->farmer->refresh();
        $this->assertEquals(1, $this->farmer->delivered_orders_count);
    }

    public function test_pos_sale_rejects_insufficient_stock(): void
    {
        $payload = [
            'user_id' => $this->farmer->id,
            'items' => [
                [
                    'product_id' => $this->productB->id,
                    'quantity' => 10, // Stock is only 5
                ],
            ],
        ];

        $response = $this->actingAs($this->admin)->postJson('/api/v1/admin/pos/sale', $payload);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items']);
    }

    public function test_pos_sale_requires_farm_when_farmer_has_multiple_farms(): void
    {
        // Add a second farm to the farmer
        $this->farm2 = Farm::factory()->create([
            'user_id' => $this->farmer->id,
            'farm_name' => 'Karim Aqua 2',
            'district' => 'Khulna',
        ]);

        $payload = [
            'user_id' => $this->farmer->id,
            'items' => [
                [
                    'product_id' => $this->productA->id,
                    'quantity' => 1,
                ],
            ],
        ];

        // Without farm_id, must fail
        $response = $this->actingAs($this->admin)->postJson('/api/v1/admin/pos/sale', $payload);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['farm_id']);

        // With valid farm_id, must succeed
        $payload['farm_id'] = $this->farm2->id;
        $successRes = $this->actingAs($this->admin)->postJson('/api/v1/admin/pos/sale', $payload);
        $successRes->assertStatus(201)
            ->assertJsonPath('data.farm_id', $this->farm2->id);
    }

    public function test_pos_sale_allows_deliver_to_farm_with_custom_address_when_farmer_has_no_farm(): void
    {
        $farmlessFarmer = User::factory()->create([
            'phone_verified_at' => now(),
            'district' => 'Jashore',
        ]);
        $farmlessFarmer->assignRole('farmer');

        $payload = [
            'user_id' => $farmlessFarmer->id,
            'items' => [
                [
                    'product_id' => $this->productA->id,
                    'quantity' => 2, // 2 * 500 = 1000
                ],
            ],
            'payment_mode' => 'cash',
            'fulfillment_type' => 'deliver_to_farm',
            'delivery_address' => 'Pond Ghona #4, Keshabpur, Jashore',
            'delivery_fee' => 70.00,
        ];

        $response = $this->actingAs($this->admin)->postJson('/api/v1/admin/pos/sale', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.payment_status', 'paid')
            ->assertJsonPath('data.delivery_fee', fn ($v) => (float) $v === 70.0)
            ->assertJsonPath('data.delivery_address', 'Pond Ghona #4, Keshabpur, Jashore')
            ->assertJsonPath('data.total', fn ($v) => (float) $v === 1070.0);
    }
}
