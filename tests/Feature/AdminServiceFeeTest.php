<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminServiceFeeTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $deo;
    protected User $farmer;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'data_entry_operator', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'farmer', 'guard_name' => 'web']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->deo = User::factory()->create();
        $this->deo->assignRole('data_entry_operator');

        $this->farmer = User::factory()->create();
        $this->farmer->assignRole('farmer');
    }

    public function test_admin_and_deo_can_list_services_and_diagnostic_fees(): void
    {
        Product::factory()->create([
            'name' => 'Test Water Parameter Lab',
            'category' => 'Services',
            'price' => 120.00,
            'stock' => 999999,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->deo)
            ->getJson('/api/v1/admin/settings/services');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $items = $response->json('data');
        expect(count($items))->toBeGreaterThanOrEqual(1);
        expect(collect($items)->firstWhere('name', 'Test Water Parameter Lab')['price'])->toBe('120.00');
    }

    public function test_admin_can_create_a_new_service_fee(): void
    {
        $payload = [
            'name' => 'Comprehensive Heavy Metal Lab Scan',
            'name_bn' => 'ভারী ধাতু ও টক্সিন ল্যাব পরীক্ষা',
            'price' => 350.00,
            'short_description' => 'Lead, Cadmium, Arsenic test',
            'short_description_bn' => 'সীসা, ক্যাডমিয়াম ও আর্সেনিক পরীক্ষা',
            'is_active' => true,
        ];

        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/admin/settings/services', $payload);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Comprehensive Heavy Metal Lab Scan')
            ->assertJsonPath('data.category', 'Services')
            ->assertJsonPath('data.price', '350.00');

        $this->assertDatabaseHas('products', [
            'name' => 'Comprehensive Heavy Metal Lab Scan',
            'category' => 'Services',
            'price' => 350.00,
        ]);
    }

    public function test_admin_can_update_a_service_fee(): void
    {
        $service = Product::factory()->create([
            'name' => 'Initial Soil Test',
            'category' => 'Services',
            'price' => 150.00,
        ]);

        $response = $this->actingAs($this->admin)
            ->putJson("/api/v1/admin/settings/services/{$service->id}", [
                'name' => 'Updated Soil Health Analysis',
                'price' => 220.00,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Soil Health Analysis')
            ->assertJsonPath('data.price', '220.00');

        $this->assertDatabaseHas('products', [
            'id' => $service->id,
            'name' => 'Updated Soil Health Analysis',
            'price' => 220.00,
        ]);
    }

    public function test_admin_can_toggle_active_status_of_a_service_fee(): void
    {
        $service = Product::factory()->create([
            'name' => 'Active Diagnostic Scan',
            'category' => 'Services',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->patchJson("/api/v1/admin/settings/services/{$service->id}/toggle");

        $response->assertOk()
            ->assertJsonPath('data.is_active', false);

        expect($service->fresh()->is_active)->toBeFalse();
    }

    public function test_products_endpoint_excludes_services_when_exclude_services_is_passed(): void
    {
        Product::factory()->create([
            'name' => 'Aqua Shrimp Feed 25kg',
            'category' => 'Feed',
            'price' => 2000.00,
            'stock' => 50,
        ]);

        Product::factory()->create([
            'name' => 'DO Diagnostic Test',
            'category' => 'Services',
            'price' => 100.00,
            'stock' => 999999,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/admin/products?exclude_services=1');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name')->all();

        expect($names)->toContain('Aqua Shrimp Feed 25kg');
        expect($names)->not->toContain('DO Diagnostic Test');
    }
}
