<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderStockIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_ordering_more_than_available_stock_is_rejected_and_stock_is_unchanged(): void
    {
        $farmer = User::factory()->create();
        $farmer->syncRoles(['farmer']);

        $product = Product::factory()->create([
            'stock' => 5,
            'price' => 100,
            'is_active' => true,
        ]);

        $response = $this->actingAs($farmer, 'sanctum')->postJson('/api/v1/orders', [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 6,
                ],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items']);

        $this->assertEquals(5, $product->fresh()->stock);
        $this->assertDatabaseEmpty('orders');
        $this->assertDatabaseEmpty('order_items');
    }

    public function test_successful_order_reduces_stock_by_the_ordered_quantity(): void
    {
        $farmer = User::factory()->create();
        $farmer->syncRoles(['farmer']);

        $product1 = Product::factory()->create([
            'stock' => 10,
            'price' => 100,
            'is_active' => true,
        ]);

        $product2 = Product::factory()->create([
            'stock' => 20,
            'price' => 50,
            'is_active' => true,
        ]);

        $response = $this->actingAs($farmer, 'sanctum')->postJson('/api/v1/orders', [
            'items' => [
                ['product_id' => $product1->id, 'quantity' => 4],
                ['product_id' => $product2->id, 'quantity' => 5],
            ],
        ]);

        $response->assertStatus(201);

        $this->assertEquals(6, $product1->fresh()->stock);
        $this->assertEquals(15, $product2->fresh()->stock);
    }

    public function test_cancelling_an_order_restores_stock(): void
    {
        $admin = User::factory()->create();
        $admin->syncRoles(['admin']);

        $farmer = User::factory()->create();
        $farmer->syncRoles(['farmer']);

        $product = Product::factory()->create([
            'stock' => 10,
            'price' => 150,
            'is_active' => true,
        ]);

        // Place order for 3 items
        $orderResponse = $this->actingAs($farmer, 'sanctum')->postJson('/api/v1/orders', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 3],
            ],
        ]);

        $orderResponse->assertStatus(201);
        $orderId = $orderResponse->json('data.id');
        $this->assertEquals(7, $product->fresh()->stock);

        // Admin cancels the order
        $cancelResponse = $this->actingAs($admin, 'sanctum')->patchJson("/api/v1/admin/orders/{$orderId}/status", [
            'status' => 'cancelled',
        ]);

        $cancelResponse->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled');

        // Stock must be restored to 10
        $this->assertEquals(10, $product->fresh()->stock);

        // Cancelling again should not double-restore stock
        $secondCancel = $this->actingAs($admin, 'sanctum')->patchJson("/api/v1/admin/orders/{$orderId}/status", [
            'status' => 'cancelled',
        ]);
        $secondCancel->assertStatus(200);
        $this->assertEquals(10, $product->fresh()->stock);
    }

    public function test_confirmed_dispatched_delivered_transitions_do_not_touch_stock(): void
    {
        $admin = User::factory()->create();
        $admin->syncRoles(['admin']);

        $farmer = User::factory()->create();
        $farmer->syncRoles(['farmer']);

        $product = Product::factory()->create([
            'stock' => 20,
            'price' => 200,
            'is_active' => true,
        ]);

        $orderResponse = $this->actingAs($farmer, 'sanctum')->postJson('/api/v1/orders', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 5],
            ],
        ]);

        $orderResponse->assertStatus(201);
        $orderId = $orderResponse->json('data.id');
        $this->assertEquals(15, $product->fresh()->stock);

        // Status -> confirmed
        $this->actingAs($admin, 'sanctum')->patchJson("/api/v1/admin/orders/{$orderId}/status", [
            'status' => 'confirmed',
        ])->assertStatus(200);
        $this->assertEquals(15, $product->fresh()->stock);

        // Status -> dispatched
        $this->actingAs($admin, 'sanctum')->patchJson("/api/v1/admin/orders/{$orderId}/status", [
            'status' => 'dispatched',
        ])->assertStatus(200);
        $this->assertEquals(15, $product->fresh()->stock);

        // Status -> delivered
        $this->actingAs($admin, 'sanctum')->patchJson("/api/v1/admin/orders/{$orderId}/status", [
            'status' => 'delivered',
        ])->assertStatus(200);
        $this->assertEquals(15, $product->fresh()->stock);
    }
}
