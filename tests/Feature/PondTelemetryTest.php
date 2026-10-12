<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\PondTelemetry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PondTelemetryTest extends TestCase
{
    use RefreshDatabase;

    protected User $farmer;
    protected User $otherFarmer;
    protected Farm $farm;
    protected Farm $otherFarm;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);

        $this->farmer = User::factory()->create(['status' => 'active', 'phone_verified_at' => now()]);
        $this->farmer->assignRole('farmer');

        $this->otherFarmer = User::factory()->create(['status' => 'active', 'phone_verified_at' => now()]);
        $this->otherFarmer->assignRole('farmer');

        $this->farm = Farm::factory()->create([
            'user_id' => $this->farmer->id,
            'farm_name' => 'Coastal Shrimp Pond',
        ]);

        $this->otherFarm = Farm::factory()->create([
            'user_id' => $this->otherFarmer->id,
            'farm_name' => 'Rival Prawn Hatchery',
        ]);
    }

    public function test_farmer_can_log_pond_telemetry_online(): void
    {
        $response = $this->actingAs($this->farmer)
            ->postJson("/api/v1/farms/{$this->farm->id}/telemetries", [
                'pond_name' => 'Pond 1 - Nursery',
                'dissolved_oxygen' => 6.2,
                'ph' => 7.8,
                'salinity' => 18.5,
                'temperature' => 29.0,
                'transparency' => 32.0,
                'ammonia' => 0.02,
                'weather_condition' => 'sunny',
                'notes' => 'Water color greenish brown, normal shrimp movement',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.pond_name', 'Pond 1 - Nursery')
            ->assertJsonPath('data.dissolved_oxygen', 6.2)
            ->assertJsonPath('data.health_status', 'normal');

        $this->assertDatabaseHas('pond_telemetries', [
            'farm_id' => $this->farm->id,
            'user_id' => $this->farmer->id,
            'pond_name' => 'Pond 1 - Nursery',
            'dissolved_oxygen' => 6.2,
        ]);
    }

    public function test_evaluates_critical_low_oxygen_alert(): void
    {
        $response = $this->actingAs($this->farmer)
            ->postJson("/api/v1/farms/{$this->farm->id}/telemetries", [
                'pond_name' => 'Pond 2',
                'dissolved_oxygen' => 2.8, // Critical < 3.5
                'ph' => 8.0,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.health_status', 'critical');

        $alerts = $response->json('data.alerts');
        $this->assertNotEmpty($alerts);
        $this->assertEquals('dissolved_oxygen', $alerts[0]['parameter']);
        $this->assertEquals('critical', $alerts[0]['level']);
    }

    public function test_farmer_cannot_log_telemetry_on_another_farmers_farm(): void
    {
        $response = $this->actingAs($this->farmer)
            ->postJson("/api/v1/farms/{$this->otherFarm->id}/telemetries", [
                'pond_name' => 'Unauthorized Pond',
                'dissolved_oxygen' => 5.5,
            ]);

        $response->assertStatus(403);
    }

    public function test_farmer_cannot_view_another_farmers_telemetries(): void
    {
        $response = $this->actingAs($this->farmer)
            ->getJson("/api/v1/farms/{$this->otherFarm->id}/telemetries");

        $response->assertStatus(403);
    }

    public function test_batch_sync_offline_telemetry_logs_with_idempotency(): void
    {
        $uuid1 = 'offline_uuid_001_' . uniqid();
        $uuid2 = 'offline_uuid_002_' . uniqid();

        $payload = [
            'items' => [
                [
                    'client_uuid' => $uuid1,
                    'pond_name' => 'Gher 1',
                    'recorded_at' => now()->subHours(5)->toIso8601String(),
                    'dissolved_oxygen' => 5.8,
                    'ph' => 7.9,
                    'salinity' => 15.0,
                    'notes' => 'Recorded offline at 8 AM',
                ],
                [
                    'client_uuid' => $uuid2,
                    'pond_name' => 'Gher 2',
                    'recorded_at' => now()->subHours(2)->toIso8601String(),
                    'dissolved_oxygen' => 6.1,
                    'ph' => 8.1,
                    'salinity' => 14.5,
                    'notes' => 'Recorded offline at 11 AM',
                ],
            ],
        ];

        // 1st sync attempt
        $response = $this->actingAs($this->farmer)
            ->postJson("/api/v1/farms/{$this->farm->id}/telemetries/batch", $payload);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.synced_count', 2);

        $this->assertDatabaseCount('pond_telemetries', 2);
        $this->assertDatabaseHas('pond_telemetries', [
            'client_uuid' => $uuid1,
            'sync_source' => 'offline_sync',
        ]);

        // 2nd sync attempt with same UUIDs (idempotency verification)
        $response2 = $this->actingAs($this->farmer)
            ->postJson("/api/v1/farms/{$this->farm->id}/telemetries/batch", $payload);

        $response2->assertStatus(200);
        // Database count should STILL be 2, no duplicates created!
        $this->assertDatabaseCount('pond_telemetries', 2);
    }

    public function test_summary_calculates_recent_stats(): void
    {
        // Seed 3 telemetry readings
        PondTelemetry::create([
            'farm_id' => $this->farm->id,
            'user_id' => $this->farmer->id,
            'pond_name' => 'Pond A',
            'recorded_at' => now()->subDays(1),
            'dissolved_oxygen' => 5.0,
            'ph' => 7.5,
            'salinity' => 15.0,
            'temperature' => 28.0,
        ]);

        PondTelemetry::create([
            'farm_id' => $this->farm->id,
            'user_id' => $this->farmer->id,
            'pond_name' => 'Pond A',
            'recorded_at' => now()->subHours(6),
            'dissolved_oxygen' => 7.0,
            'ph' => 8.5,
            'salinity' => 17.0,
            'temperature' => 30.0,
        ]);

        $response = $this->actingAs($this->farmer)
            ->getJson("/api/v1/farms/{$this->farm->id}/telemetries/summary");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.seven_day_stats.total_logs', 2);

        $this->assertEquals(6.0, $response->json('data.seven_day_stats.avg_do'));
        $this->assertEquals(8.0, $response->json('data.seven_day_stats.avg_ph'));
        $this->assertEquals(5.0, $response->json('data.seven_day_stats.min_do'));
        $this->assertEquals(7.0, $response->json('data.seven_day_stats.max_do'));
    }

    public function test_export_telemetries_as_json_and_csv(): void
    {
        PondTelemetry::create([
            'farm_id' => $this->farm->id,
            'user_id' => $this->farmer->id,
            'pond_name' => 'Pond A',
            'recorded_at' => now()->subDay(),
            'dissolved_oxygen' => 5.5,
            'ph' => 7.8,
            'salinity' => 15.0,
            'temperature' => 29.0,
        ]);

        // JSON export
        $responseJson = $this->actingAs($this->farmer)
            ->getJson("/api/v1/farms/{$this->farm->id}/telemetries/export");

        $responseJson->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 1);

        // CSV export
        $responseCsv = $this->actingAs($this->farmer)
            ->get("/api/v1/farms/{$this->farm->id}/telemetries/export?format=csv");

        $responseCsv->assertStatus(200);
        $this->assertStringContainsString('text/csv', $responseCsv->headers->get('Content-Type'));
        $this->assertStringContainsString('Dissolved Oxygen', $responseCsv->streamedContent());
    }

    public function test_admin_and_deo_can_log_and_view_water_quality_in_any_farm(): void
    {
        $admin = User::factory()->admin()->create();
        $deo = User::factory()->deo()->create();

        // 1. Admin logs water quality for farmer's farm
        $responseAdmin = $this->actingAs($admin)
            ->postJson("/api/v1/farms/{$this->farm->id}/telemetries", [
                'pond_name' => 'Admin Inspected Pond',
                'dissolved_oxygen' => 5.8,
                'ph' => 7.6,
                'salinity' => 17.0,
                'notes' => 'Logged during administrative field check',
            ]);

        $responseAdmin->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.pond_name', 'Admin Inspected Pond')
            ->assertJsonPath('data.dissolved_oxygen', 5.8);

        $this->assertDatabaseHas('pond_telemetries', [
            'farm_id' => $this->farm->id,
            'user_id' => $admin->id,
            'pond_name' => 'Admin Inspected Pond',
        ]);

        // 2. DEO logs water quality for farmer's farm
        $responseDeo = $this->actingAs($deo)
            ->postJson("/api/v1/farms/{$this->farm->id}/telemetries", [
                'pond_name' => 'DEO Assisted Pond',
                'dissolved_oxygen' => 6.4,
                'ph' => 8.0,
                'salinity' => 16.5,
                'notes' => 'Assisted farmer entry at local kiosk',
            ]);

        $responseDeo->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.pond_name', 'DEO Assisted Pond');

        // 3. Admin and DEO can view the farm's telemetry list
        $viewAdmin = $this->actingAs($admin)->getJson("/api/v1/farms/{$this->farm->id}/telemetries");
        $viewAdmin->assertStatus(200)
            ->assertJsonPath('meta.total', 2);

        $viewDeo = $this->actingAs($deo)->getJson("/api/v1/farms/{$this->farm->id}/telemetries");
        $viewDeo->assertStatus(200)
            ->assertJsonPath('meta.total', 2);
    }
}
