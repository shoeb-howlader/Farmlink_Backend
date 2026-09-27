<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DeoPortalAccessTest extends TestCase
{
    use RefreshDatabase;

    protected User $deo;
    protected User $admin;
    protected User $farmer;
    protected Farm $farm;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'data_entry_operator', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'farmer', 'guard_name' => 'web']);

        $this->deo = User::factory()->create([
            'name' => 'DEO Staff Rahim',
            'phone' => '01700999888',
            'email' => 'deo@farmlink.test',
            'district' => 'Bagerhat',
        ]);
        $this->deo->assignRole('data_entry_operator');

        $this->admin = User::factory()->create([
            'name' => 'Admin Boss',
            'phone' => '01700111222',
            'email' => 'admin@farmlink.test',
        ]);
        $this->admin->assignRole('admin');

        $this->farmer = User::factory()->create([
            'name' => 'Farmer Anis',
            'phone' => '01811223344',
            'email' => 'anis@farmlink.test',
            'district' => 'Khulna',
        ]);
        $this->farmer->assignRole('farmer');

        $this->farm = Farm::factory()->create([
            'user_id' => $this->farmer->id,
            'farm_name' => 'Anis Shrimp Hatchery',
            'district' => 'Khulna',
            'upazila' => 'Rupsha',
        ]);

        $this->product = Product::factory()->create([
            'name' => 'Quality Aqua Probiotics 500g',
            'price' => 750.00,
            'stock' => 100,
            'is_active' => true,
        ]);
    }

    public function test_deo_can_view_deo_dashboard_metrics(): void
    {
        $response = $this->actingAs($this->deo)
            ->getJson('/api/v1/deo/dashboard');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'metrics' => [
                        'farmers_registered',
                        'farms_registered',
                        'pos_sales_count',
                        'pos_sales_revenue',
                    ],
                    'recent_activity',
                    'recent_farmers',
                    'deo_info',
                ],
            ]);
    }

    public function test_farmer_cannot_view_deo_dashboard(): void
    {
        $response = $this->actingAs($this->farmer)
            ->getJson('/api/v1/deo/dashboard');

        $response->assertStatus(403);
    }

    public function test_deo_can_register_new_farmer(): void
    {
        $payload = [
            'name' => 'Hasanuzzaman Molla',
            'phone' => '01987654321',
            'district' => 'Satkhira',
            'gender' => 'male',
            'email' => 'hasan@example.com',
            'password' => 'secret123',
        ];

        $response = $this->actingAs($this->deo)
            ->postJson('/api/v1/admin/farmers', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Hasanuzzaman Molla')
            ->assertJsonPath('data.phone', '01987654321');

        $this->assertDatabaseHas('users', [
            'name' => 'Hasanuzzaman Molla',
            'phone' => '01987654321',
        ]);
    }

    public function test_deo_can_register_farm_linked_to_farmer(): void
    {
        $payload = [
            'user_id' => $this->farmer->id,
            'farm_name' => 'Sundarbans Gold Farm',
            'farm_type' => 'Shrimp/Gher',
            'total_area' => 12.5,
            'pond_count' => 4,
            'district' => 'Bagerhat',
            'upazila' => 'Mongla',
            'main_culture_type' => 'Black Tiger Shrimp',
            'farming_system' => 'Semi-intensive',
        ];

        $response = $this->actingAs($this->deo)
            ->postJson('/api/v1/admin/farms', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.farm_name', 'Sundarbans Gold Farm')
            ->assertJsonPath('data.user_id', $this->farmer->id);

        $this->assertDatabaseHas('farms', [
            'farm_name' => 'Sundarbans Gold Farm',
            'user_id' => $this->farmer->id,
        ]);
    }

    public function test_deo_can_view_farmers_and_farms_list_read_only(): void
    {
        $farmersRes = $this->actingAs($this->deo)
            ->getJson('/api/v1/admin/farmers');
        $farmersRes->assertStatus(200)
            ->assertJsonPath('success', true);

        $farmsRes = $this->actingAs($this->deo)
            ->getJson('/api/v1/admin/farms');
        $farmsRes->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_deo_can_complete_pos_assisted_sale(): void
    {
        $payload = [
            'user_id' => $this->farmer->id,
            'farm_id' => $this->farm->id,
            'payment_mode' => 'cash',
            'notes' => 'Assisted counter sale by DEO',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 2,
                    'price_at_purchase' => 750.00,
                ],
            ],
        ];

        $response = $this->actingAs($this->deo)
            ->postJson('/api/v1/admin/pos/sale', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.channel', 'admin_pos')
            ->assertJsonPath('data.status', 'confirmed');

        $this->assertDatabaseHas('orders', [
            'user_id' => $this->farmer->id,
            'channel' => 'admin_pos',
            'status' => 'confirmed',
        ]);
    }

    public function test_deo_is_forbidden_from_staff_management(): void
    {
        $this->actingAs($this->deo)
            ->getJson('/api/v1/admin/staff')
            ->assertStatus(403);

        $this->actingAs($this->deo)
            ->postJson('/api/v1/admin/staff', [
                'name' => 'New Staff Guy',
                'email' => 'newstaff@test.com',
                'phone' => '01711000111',
                'role' => 'admin',
                'password' => 'secret123',
            ])
            ->assertStatus(403);
    }

    public function test_deo_is_forbidden_from_product_mutations(): void
    {
        $this->actingAs($this->deo)
            ->postJson('/api/v1/admin/products', [
                'name' => 'Forbidden Product',
                'category' => 'Feed',
                'price' => 100,
                'stock' => 10,
            ])
            ->assertStatus(403);

        $this->actingAs($this->deo)
            ->patchJson("/api/v1/admin/products/{$this->product->id}/stock", [
                'quantity' => 50,
                'operation' => 'add',
                'reason' => 'Inventory refill',
            ])
            ->assertStatus(403);
    }

    public function test_deo_is_forbidden_from_audit_log_and_reports(): void
    {
        $this->actingAs($this->deo)
            ->getJson('/api/v1/admin/audit-log')
            ->assertStatus(403);

        $this->actingAs($this->deo)
            ->getJson('/api/v1/admin/reports/sales')
            ->assertStatus(403);
    }

    public function test_deo_is_forbidden_from_order_editing_and_status_changes(): void
    {
        $order = Order::factory()->create([
            'user_id' => $this->farmer->id,
            'status' => 'pending',
            'total' => 1000,
        ]);

        $this->actingAs($this->deo)
            ->patchJson("/api/v1/admin/orders/{$order->id}/status", [
                'status' => 'confirmed',
            ])
            ->assertStatus(403);

        $this->actingAs($this->deo)
            ->putJson("/api/v1/admin/orders/{$order->id}/items", [
                'items' => [
                    ['product_id' => $this->product->id, 'quantity' => 1, 'price' => 750],
                ],
                'reason' => 'Unauthorized DEO edit',
            ])
            ->assertStatus(403);
    }
}
