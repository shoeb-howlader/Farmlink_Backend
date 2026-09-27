<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\Order;
use App\Models\User;
use App\Models\VetRecord;
use App\Models\ConsultantRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminEnhancementsBatch2Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'farmer', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'veterinary_doctor', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'consultant', 'guard_name' => 'web']);
    }

    public function test_admin_can_view_staff_profile_with_visit_counts(): void
    {
        $admin = User::factory()->create();
        $admin->syncRoles(['admin']);

        $vet = User::factory()->create(['name' => 'Dr. Rahman']);
        $vet->syncRoles(['veterinary_doctor']);

        $farmer = User::factory()->create();
        $farmer->syncRoles(['farmer']);

        $farm = Farm::factory()->create(['user_id' => $farmer->id, 'farm_name' => 'Delta Ponds']);

        VetRecord::factory()->create([
            'farm_id' => $farm->id,
            'vet_id' => $vet->id,
            'visit_date' => now()->toDateString(),
            'findings' => 'Healthy shrimp post-larvae',
        ]);

        $response = $this->actingAs($admin, 'sanctum')->getJson("/api/v1/admin/staff/{$vet->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $vet->id,
                    'name' => 'Dr. Rahman',
                    'role' => 'veterinary_doctor',
                    'total_visits' => 1,
                    'vet_visits_count' => 1,
                    'consultant_visits_count' => 0,
                ]
            ]);
    }

    public function test_global_search_includes_farms(): void
    {
        $admin = User::factory()->create();
        $admin->syncRoles(['admin']);

        $farmer = User::factory()->create(['name' => 'Nurul Islam']);
        $farmer->syncRoles(['farmer']);

        $farm = Farm::factory()->create([
            'user_id' => $farmer->id,
            'farm_name' => 'Sundarban Tiger Prawn Farm',
            'district' => 'Satkhira',
        ]);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/search?q=Sundarban');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'farmers',
                    'farms',
                    'orders',
                    'products',
                ]
            ]);

        $this->assertNotEmpty($response->json('data.farms'));
        $this->assertEquals('Sundarban Tiger Prawn Farm', $response->json('data.farms.0.title'));
        $this->assertEquals("/admin/farms/{$farm->id}", $response->json('data.farms.0.url'));
    }

    public function test_vet_consultant_records_returns_farm_name_and_filters_by_practitioner(): void
    {
        $admin = User::factory()->create();
        $admin->syncRoles(['admin']);

        $vet1 = User::factory()->create(['name' => 'Dr. A']);
        $vet1->syncRoles(['veterinary_doctor']);

        $vet2 = User::factory()->create(['name' => 'Dr. B']);
        $vet2->syncRoles(['veterinary_doctor']);

        $farmer = User::factory()->create(['name' => 'Jamal Hossain']);
        $farmer->syncRoles(['farmer']);

        $farm = Farm::factory()->create(['user_id' => $farmer->id, 'farm_name' => 'Green Bay Ponds']);

        VetRecord::factory()->create([
            'farm_id' => $farm->id,
            'vet_id' => $vet1->id,
            'visit_date' => now()->toDateString(),
            'findings' => 'Regular checkup',
        ]);

        VetRecord::factory()->create([
            'farm_id' => $farm->id,
            'vet_id' => $vet2->id,
            'visit_date' => now()->toDateString(),
            'findings' => 'Water test',
        ]);

        $resAll = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/vet-consultant-records');
        $resAll->assertStatus(200);
        $this->assertEquals('Green Bay Ponds', $resAll->json('data.0.farm.name'));
        $this->assertEquals('Green Bay Ponds', $resAll->json('data.0.farm.farm_name'));

        // Filter by practitioner
        $resVet1 = $this->actingAs($admin, 'sanctum')->getJson("/api/v1/admin/vet-consultant-records?practitioner_id={$vet1->id}");
        $resVet1->assertStatus(200);
        $this->assertCount(1, $resVet1->json('data'));
        $this->assertEquals('Dr. A', $resVet1->json('data.0.practitioner.name'));
    }

    public function test_farmer_and_farm_aggregates_and_sorting(): void
    {
        $admin = User::factory()->create();
        $admin->syncRoles(['admin']);

        $farmer1 = User::factory()->create(['name' => 'Farmer Alpha']);
        $farmer1->syncRoles(['farmer']);
        $farm1 = Farm::factory()->create(['user_id' => $farmer1->id, 'farm_name' => 'Alpha Farm', 'total_area' => 10]);
        Order::factory()->create(['user_id' => $farmer1->id, 'farm_id' => $farm1->id, 'total' => 5000, 'status' => 'delivered']);
        Order::factory()->create(['user_id' => $farmer1->id, 'farm_id' => $farm1->id, 'total' => 3000, 'status' => 'confirmed']);

        $farmer2 = User::factory()->create(['name' => 'Farmer Beta']);
        $farmer2->syncRoles(['farmer']);
        $farm2 = Farm::factory()->create(['user_id' => $farmer2->id, 'farm_name' => 'Beta Farm', 'total_area' => 20]);
        Order::factory()->create(['user_id' => $farmer2->id, 'farm_id' => $farm2->id, 'total' => 1000, 'status' => 'delivered']);

        // Test Farmers list aggregations and sort
        $resFarmers = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/farmers?sort_by=total_purchased&sort_direction=desc');
        $resFarmers->assertStatus(200);
        $this->assertEquals('Farmer Alpha', $resFarmers->json('data.0.name'));
        $this->assertEquals(2, $resFarmers->json('data.0.total_orders'));
        $this->assertEquals(8000.0, $resFarmers->json('data.0.total_purchased'));

        // Test Farms list aggregations and sort
        $resFarms = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/farms?sort_by=orders_count&sort_direction=desc');
        $resFarms->assertStatus(200);
        $this->assertEquals('Alpha Farm', $resFarms->json('data.0.farm_name'));
        $this->assertEquals(2, $resFarms->json('data.0.orders_count'));
    }

    public function test_top_farmers_this_month(): void
    {
        $admin = User::factory()->create();
        $admin->syncRoles(['admin']);

        $farmerA = User::factory()->create(['name' => 'Top Spender']);
        $farmerA->syncRoles(['farmer']);
        Order::factory()->create(['user_id' => $farmerA->id, 'total' => 15000, 'status' => 'delivered', 'created_at' => now()]);

        $farmerB = User::factory()->create(['name' => 'Low Spender']);
        $farmerB->syncRoles(['farmer']);
        Order::factory()->create(['user_id' => $farmerB->id, 'total' => 2000, 'status' => 'confirmed', 'created_at' => now()]);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/reports/top-farmers');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertEquals('Top Spender', $response->json('data.0.name'));
        $this->assertEquals(15000.0, $response->json('data.0.total_spent'));
    }
}
