<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\PondTelemetry;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTelemetryHeatmapTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $deo;
    protected User $farmer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->admin = User::factory()->admin()->create();
        $this->deo = User::factory()->deo()->create();
        $this->farmer = User::factory()->farmer()->create();
    }

    public function test_farmer_is_forbidden_from_district_heatmap(): void
    {
        $response = $this->actingAs($this->farmer)
            ->getJson('/api/v1/admin/telemetry/district-heatmap');

        $response->assertStatus(403);
    }

    public function test_admin_and_deo_can_view_aggregated_district_heatmap(): void
    {
        // Create 2 farms in Satkhira (Shyamnagar and Kaliganj)
        $farm1 = Farm::factory()->create([
            'user_id' => $this->farmer->id,
            'farm_name' => 'Satkhira Coastal Gher 1',
            'district' => 'Satkhira',
            'upazila' => 'Shyamnagar',
        ]);

        $farm2 = Farm::factory()->create([
            'user_id' => $this->farmer->id,
            'farm_name' => 'Satkhira Coastal Gher 2',
            'district' => 'Satkhira',
            'upazila' => 'Kaliganj',
        ]);

        // Create 1 farm in Cox's Bazar
        $farm3 = Farm::factory()->create([
            'user_id' => $this->farmer->id,
            'farm_name' => 'Cox Gher 1',
            'district' => "Cox's Bazar",
            'upazila' => 'Teknaf',
        ]);

        // Seed critical hypoxia telemetry for farm1 (DO = 2.4 mg/L)
        PondTelemetry::create([
            'farm_id' => $farm1->id,
            'user_id' => $this->farmer->id,
            'pond_name' => 'Gher A',
            'recorded_at' => now()->subDay(),
            'dissolved_oxygen' => 2.4,
            'ph' => 7.8,
            'salinity' => 18.0,
        ]);

        // Seed optimal telemetry for farm3 (DO = 6.2 mg/L)
        PondTelemetry::create([
            'farm_id' => $farm3->id,
            'user_id' => $this->farmer->id,
            'pond_name' => 'Pond 1',
            'recorded_at' => now()->subHours(8),
            'dissolved_oxygen' => 6.2,
            'ph' => 8.0,
            'salinity' => 22.0,
        ]);

        // Admin request
        $responseAdmin = $this->actingAs($this->admin)
            ->getJson('/api/v1/admin/telemetry/district-heatmap?days=14');

        $responseAdmin->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.summary_kpis.monitored_districts_count', 2)
            ->assertJsonPath('data.summary_kpis.total_farms_monitored', 2)
            ->assertJsonPath('data.summary_kpis.total_hypoxia_alerts', 1);

        $districts = $responseAdmin->json('data.districts');
        $this->assertCount(2, $districts);

        // Satkhira should be marked critical due to hypoxia
        $satkhira = collect($districts)->firstWhere('district_name', 'Satkhira');
        $this->assertNotNull($satkhira);
        $this->assertEquals('critical', $satkhira['risk_level']);
        $this->assertEquals(1, $satkhira['hypoxia_count']);
        $this->assertCount(1, $satkhira['upazilas']);

        // DEO request
        $responseDeo = $this->actingAs($this->deo)
            ->getJson('/api/v1/admin/telemetry/district-heatmap');

        $responseDeo->assertStatus(200)
            ->assertJsonPath('success', true);
    }
}
