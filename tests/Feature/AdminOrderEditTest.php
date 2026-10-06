<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\AdminNotification;
use App\Models\Farm;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminOrderEditTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $deo;
    protected User $farmer;
    protected Farm $farm;
    protected Product $productA;
    protected Product $productB;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'data_entry_operator', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'farmer', 'guard_name' => 'web']);

        $this->admin = User::factory()->create(['email' => 'admin@test.com']);
        $this->admin->assignRole('admin');

        $this->deo = User::factory()->create(['email' => 'deo@test.com']);
        $this->deo->assignRole('data_entry_operator');

        $this->farmer = User::factory()->create(['email' => 'farmer@test.com']);
        $this->farmer->assignRole('farmer');

        $this->farm = Farm::factory()->create(['user_id' => $this->farmer->id]);

        $this->productA = Product::factory()->create([
            'name' => 'Premium Feed Pellet 25kg',
            'price' => 100.00,
            'stock' => 50,
        ]);

        $this->productB = Product::factory()->create([
            'name' => 'AquaShield Water Clarifier 1L',
            'price' => 500.00,
            'stock' => 20,
        ]);
    }

    public function test_admin_can_edit_pending_order_items_and_adjust_stock_and_recalculate_total(): void
    {
        // 1. Initial Order: 2x Product A (stock 50 -> 48)
        $this->productA->decrement('stock', 2);
        $order = Order::create([
            'user_id' => $this->farmer->id,
            'farm_id' => $this->farm->id,
            'status' => 'pending',
            'total' => 200.00,
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->productA->id,
            'quantity' => 2,
            'price_at_purchase' => 100.00,
        ]);

        $this->assertEquals(48, $this->productA->fresh()->stock);
        $this->assertEquals(20, $this->productB->fresh()->stock);

        // 2. Admin edits order: increase Product A to 5, add 1x Product B
        $response = $this->actingAs($this->admin)->putJson("/api/v1/admin/orders/{$order->id}/items", [
            'reason_note' => 'Farmer requested additional feed bags and water conditioner',
            'items' => [
                ['product_id' => $this->productA->id, 'quantity' => 5],
                ['product_id' => $this->productB->id, 'quantity' => 1],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.total', 1000)
            ->assertJsonCount(2, 'data.items');

        // Verify stocks: Product A should be 50 - 5 = 45; Product B should be 20 - 1 = 19
        $this->assertEquals(45, $this->productA->fresh()->stock);
        $this->assertEquals(19, $this->productB->fresh()->stock);

        // Verify order in database
        $order->refresh();
        $this->assertEquals(1000.00, (float) $order->total);

        // Verify Activity Log
        $log = ActivityLog::where('action', 'order.items_updated')->first();
        $this->assertNotNull($log);
        $this->assertEquals(200.00, $log->changes['before_total']);
        $this->assertEquals(1000.00, $log->changes['after_total']);
        $this->assertEquals('Farmer requested additional feed bags and water conditioner', $log->changes['reason_note']);

        // Verify Farmer Notification
        $notif = AdminNotification::where('user_id', $this->farmer->id)->latest('id')->first();
        $this->assertNotNull($notif);
        $this->assertEquals("Order #{$order->id} Updated", $notif->title);
        $this->assertStringContainsString('1,000.00', $notif->message);
    }

    public function test_admin_reducing_order_quantity_restores_stock(): void
    {
        $this->productA->decrement('stock', 5);
        $order = Order::create([
            'user_id' => $this->farmer->id,
            'farm_id' => $this->farm->id,
            'status' => 'confirmed',
            'total' => 500.00,
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->productA->id,
            'quantity' => 5,
            'price_at_purchase' => 100.00,
        ]);

        $this->assertEquals(45, $this->productA->fresh()->stock);

        // Reduce from 5 to 2 -> should restore 3 to stock (45 + 3 = 48)
        $response = $this->actingAs($this->admin)->putJson("/api/v1/admin/orders/{$order->id}/items", [
            'reason_note' => 'Farmer reduced quantity due to budget constraint',
            'items' => [
                ['product_id' => $this->productA->id, 'quantity' => 2],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.total', 200);

        $this->assertEquals(48, $this->productA->fresh()->stock);
        $this->assertEquals(200.00, (float) $order->fresh()->total);
    }

    public function test_editing_fails_if_insufficient_stock(): void
    {
        // Product B has only 20 in stock
        $order = Order::create([
            'user_id' => $this->farmer->id,
            'status' => 'pending',
            'total' => 100.00,
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->productA->id,
            'quantity' => 1,
            'price_at_purchase' => 100.00,
        ]);

        // Attempting to set Product B to 25 (only 20 available)
        $response = $this->actingAs($this->admin)->putJson("/api/v1/admin/orders/{$order->id}/items", [
            'reason_note' => 'Attempting to order more than available',
            'items' => [
                ['product_id' => $this->productA->id, 'quantity' => 1],
                ['product_id' => $this->productB->id, 'quantity' => 25],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items']);

        // Stock should remain unchanged
        $this->assertEquals(20, $this->productB->fresh()->stock);
    }

    public function test_attempting_to_edit_dispatched_or_later_order_is_rejected(): void
    {
        $order = Order::create([
            'user_id' => $this->farmer->id,
            'status' => 'dispatched',
            'total' => 100.00,
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->productA->id,
            'quantity' => 1,
            'price_at_purchase' => 100.00,
        ]);

        $response = $this->actingAs($this->admin)->putJson("/api/v1/admin/orders/{$order->id}/items", [
            'reason_note' => 'Trying to edit after dispatch',
            'items' => [
                ['product_id' => $this->productA->id, 'quantity' => 2],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_deo_and_farmer_are_forbidden_from_editing_orders(): void
    {
        $order = Order::create([
            'user_id' => $this->farmer->id,
            'status' => 'pending',
            'total' => 100.00,
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->productA->id,
            'quantity' => 1,
            'price_at_purchase' => 100.00,
        ]);

        // DEO cannot edit
        $deoResponse = $this->actingAs($this->deo)->putJson("/api/v1/admin/orders/{$order->id}/items", [
            'reason_note' => 'DEO attempting edit',
            'items' => [['product_id' => $this->productA->id, 'quantity' => 2]],
        ]);
        $deoResponse->assertForbidden();

        // Farmer cannot edit
        $farmerResponse = $this->actingAs($this->farmer)->putJson("/api/v1/admin/orders/{$order->id}/items", [
            'reason_note' => 'Farmer attempting edit',
            'items' => [['product_id' => $this->productA->id, 'quantity' => 2]],
        ]);
        $farmerResponse->assertForbidden();
    }

    public function test_admin_can_update_order_delivery_address_and_fee_and_recalculates_total(): void
    {
        $order = Order::create([
            'user_id' => $this->farmer->id,
            'farm_id' => $this->farm->id,
            'status' => 'confirmed',
            'subtotal' => 500.00,
            'delivery_fee' => 50.00,
            'discount_amount' => 0.00,
            'total' => 550.00,
            'delivery_address' => 'Old Road 1',
        ]);

        $response = $this->actingAs($this->admin)->patchJson("/api/v1/admin/orders/{$order->id}/delivery", [
            'delivery_address' => 'Pond 3 Feeder Bank, Rampal, Bagerhat',
            'delivery_fee' => 120.00,
            'recipient_name' => 'Manager Kabir',
            'recipient_phone' => '01799887766',
            'district' => 'Bagerhat',
            'upazila' => 'Rampal',
            'reason_note' => 'Special boat transit surcharge added as requested by farmer',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.delivery_fee', 120)
            ->assertJsonPath('data.total', 620)
            ->assertJsonPath('data.delivery_address', 'Pond 3 Feeder Bank, Rampal, Bagerhat')
            ->assertJsonPath('data.recipient_name', 'Manager Kabir');

        $order->refresh();
        $this->assertEquals(120.00, (float) $order->delivery_fee);
        $this->assertEquals(620.00, (float) $order->total);
        $this->assertEquals('Pond 3 Feeder Bank, Rampal, Bagerhat', $order->delivery_address);

        // Verify Activity Log
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'order.delivery_updated',
            'subject_type' => Order::class,
            'subject_id' => $order->id,
        ]);
    }

    public function test_admin_cannot_update_delivery_on_dispatched_or_delivered_order(): void
    {
        $order = Order::create([
            'user_id' => $this->farmer->id,
            'status' => 'delivered',
            'subtotal' => 200.00,
            'delivery_fee' => 0.00,
            'total' => 200.00,
            'delivery_address' => 'Old Address',
        ]);

        $response = $this->actingAs($this->admin)->patchJson("/api/v1/admin/orders/{$order->id}/delivery", [
            'delivery_address' => 'New Address',
            'delivery_fee' => 50.00,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_non_admin_cannot_update_order_delivery(): void
    {
        $order = Order::create([
            'user_id' => $this->farmer->id,
            'status' => 'pending',
            'subtotal' => 200.00,
            'delivery_fee' => 0.00,
            'total' => 200.00,
            'delivery_address' => 'Old Address',
        ]);

        $this->actingAs($this->deo)->patchJson("/api/v1/admin/orders/{$order->id}/delivery", [
            'delivery_address' => 'New Address',
            'delivery_fee' => 50.00,
        ])->assertForbidden();

        $this->actingAs($this->farmer)->patchJson("/api/v1/admin/orders/{$order->id}/delivery", [
            'delivery_address' => 'New Address',
            'delivery_fee' => 50.00,
        ])->assertForbidden();
    }
}
