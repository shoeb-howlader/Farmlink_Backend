<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OrderFraudAndRiskScoringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'farmer', 'guard_name' => 'web']);
    }

    public function test_order_creation_captures_client_ip_and_user_agent(): void
    {
        $farmer = User::factory()->create([
            'phone_verified_at' => now(),
            'status' => 'active',
            'created_at' => now()->subDays(10), // older account
            'delivered_orders_count' => 3,     // trusted farmer
            'refused_cod_orders_count' => 0,
        ]);
        $farmer->assignRole('farmer');

        $farm = Farm::factory()->create(['user_id' => $farmer->id]);

        $product = Product::factory()->create(['stock' => 50, 'is_active' => true]);
        $variant = $product->variants()->first();
        $variant->update(['price' => 500.00, 'stock' => 50]);

        $response = $this->actingAs($farmer)
            ->withServerVariables([
                'REMOTE_ADDR' => '103.112.55.20',
                'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) FarmLink-App',
            ])
            ->postJson('/api/v1/orders', [
                'farm_id' => $farm->id,
                'recipient_name' => 'Rafiqul Islam',
                'recipient_phone' => $farmer->phone,
                'delivery_address' => 'Pond 4, Shyamnagar, Satkhira',
                'district' => 'Satkhira',
                'payment_mode' => 'cod',
                'items' => [
                    [
                        'product_id' => $product->id,
                        'product_variant_id' => $variant->id,
                        'quantity' => 2,
                    ],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        $order = Order::first();
        $this->assertNotNull($order);
        $this->assertEquals('103.112.55.20', $order->ip_address);
        $this->assertStringContainsString('FarmLink-App', $order->user_agent);
        $this->assertEquals('low', $order->risk_level);
        $this->assertFalse($order->is_flagged);
        $this->assertEquals('auto_trusted', $order->verification_status);
    }

    public function test_new_account_bulk_cod_order_is_flagged_with_high_risk(): void
    {
        $newFarmer = User::factory()->create([
            'phone_verified_at' => now(),
            'status' => 'active',
            'created_at' => now()->subHours(2), // brand new account
            'delivered_orders_count' => 0,
            'refused_cod_orders_count' => 0,
        ]);
        $newFarmer->assignRole('farmer');

        $farm = Farm::factory()->create(['user_id' => $newFarmer->id]);

        $product = Product::factory()->create(['stock' => 100, 'is_active' => true]);
        $variant = $product->variants()->first();
        $variant->update(['price' => 6000.00, 'stock' => 100]);

        $response = $this->actingAs($newFarmer)
            ->withServerVariables([
                'REMOTE_ADDR' => '103.112.55.21',
                'HTTP_USER_AGENT' => 'Mozilla/5.0 Mobile/Android',
            ])
            ->postJson('/api/v1/orders', [
                'farm_id' => $farm->id,
                'recipient_name' => 'Unknown Buyer',
                'recipient_phone' => '01700000000',
                'delivery_address' => 'Unknown Ghat',
                'district' => 'Cox\'s Bazar',
                'payment_mode' => 'cod',
                'items' => [
                    [
                        'product_id' => $product->id,
                        'product_variant_id' => $variant->id,
                        'quantity' => 2, // Total: 12,000 BDT
                    ],
                ],
            ]);

        $response->assertStatus(201);

        $order = Order::first();
        $this->assertNotNull($order);
        $this->assertTrue($order->is_flagged);
        $this->assertGreaterThanOrEqual(35, $order->risk_score);
        $this->assertContains('new_account_bulk_cod_over_threshold', $order->flag_reasons);
        $this->assertEquals('unverified', $order->verification_status);
    }

    public function test_blocked_cod_user_cannot_place_cod_order(): void
    {
        $blockedFarmer = User::factory()->create([
            'phone_verified_at' => now(),
            'status' => 'active',
            'cod_blocked' => true,
            'refused_cod_orders_count' => 3,
        ]);
        $blockedFarmer->assignRole('farmer');

        $farm = Farm::factory()->create(['user_id' => $blockedFarmer->id]);
        $product = Product::factory()->create(['stock' => 10, 'is_active' => true]);

        // Attempt COD order -> should fail with 422
        $response = $this->actingAs($blockedFarmer)->postJson('/api/v1/orders', [
            'farm_id' => $farm->id,
            'recipient_name' => 'Serial Returner',
            'delivery_address' => 'Some Farm',
            'payment_mode' => 'cod',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['payment_mode']);

        // Attempt online payment (sslcommerz) -> should succeed
        $onlineResponse = $this->actingAs($blockedFarmer)->postJson('/api/v1/orders', [
            'farm_id' => $farm->id,
            'recipient_name' => 'Serial Returner',
            'delivery_address' => 'Some Farm',
            'payment_mode' => 'sslcommerz',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $onlineResponse->assertStatus(201);
    }

    public function test_blacklisted_user_cannot_place_any_order(): void
    {
        $blacklistedUser = User::factory()->create([
            'phone_verified_at' => now(),
            'status' => 'active',
            'is_blacklisted' => true,
            'blacklist_reason' => 'Fraudulent chargeback activity',
        ]);
        $blacklistedUser->assignRole('farmer');

        $farm = Farm::factory()->create(['user_id' => $blacklistedUser->id]);
        $product = Product::factory()->create(['stock' => 10, 'is_active' => true]);

        $response = $this->actingAs($blacklistedUser)->postJson('/api/v1/orders', [
            'farm_id' => $farm->id,
            'recipient_name' => 'Fraudster',
            'delivery_address' => 'Some Farm',
            'payment_mode' => 'sslcommerz',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['order']);
    }

    public function test_admin_can_verify_and_unflag_order(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');

        $farmer = User::factory()->create(['phone_verified_at' => now()]);
        $order = Order::factory()->create([
            'user_id' => $farmer->id,
            'is_flagged' => true,
            'risk_score' => 45,
            'risk_level' => 'medium',
            'verification_status' => 'unverified',
        ]);

        $response = $this->actingAs($admin)->postJson("/api/v1/admin/orders/{$order->id}/verify-fraud", [
            'verification_status' => 'verified_call',
            'unflag' => true,
            'notes' => 'Customer called and confirmed by manager',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.verification_status', 'verified_call')
            ->assertJsonPath('data.is_flagged', false);

        $order->refresh();
        $this->assertEquals('verified_call', $order->verification_status);
        $this->assertFalse($order->is_flagged);
        $this->assertEquals($admin->id, $order->verified_by);
        $this->assertNotNull($order->verified_at);
        $this->assertStringContainsString('Customer called and confirmed by manager', $order->notes);
    }

    public function test_delivered_status_increments_customer_delivery_success(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');

        $farmer = User::factory()->create([
            'phone_verified_at' => now(),
            'delivered_orders_count' => 1,
        ]);

        $order = Order::factory()->create([
            'user_id' => $farmer->id,
            'status' => 'dispatched',
            'payment_mode' => 'cod',
            'payment_status' => 'pending',
            'total' => 1500.00,
        ]);

        $response = $this->actingAs($admin)->patchJson("/api/v1/admin/orders/{$order->id}/status", [
            'status' => 'delivered',
        ]);

        $response->assertStatus(200);

        $farmer->refresh();
        $this->assertEquals(2, $farmer->delivered_orders_count);
    }
}
