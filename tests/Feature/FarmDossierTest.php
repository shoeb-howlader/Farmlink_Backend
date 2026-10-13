<?php

use App\Models\Farm;
use App\Models\PondTelemetry;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->admin = User::factory()->admin()->create();
    $this->farmer = User::factory()->farmer()->create();
    $this->otherFarmer = User::factory()->farmer()->create();

    $this->farm = Farm::factory()->create([
        'user_id' => $this->farmer->id,
        'farm_name' => 'Bismillah Shrimp Hatchery & Farm',
        'district' => 'Bagerhat',
        'upazila' => 'Bagerhat Sadar',
        'total_area' => 150.0,
        'farm_manager' => 'Self',
        'technical_support_used' => 'Local Vet',
        'main_advice_source' => 'Feed Dealer',
    ]);

    // Seed sample telemetry records
    PondTelemetry::create([
        'farm_id' => $this->farm->id,
        'user_id' => $this->farmer->id,
        'pond_name' => 'Nursery Pond 1',
        'dissolved_oxygen' => 5.8,
        'ph' => 7.8,
        'salinity' => 14.2,
        'temperature' => 28.5,
        'recorded_at' => now()->subHours(2),
    ]);

    PondTelemetry::create([
        'farm_id' => $this->farm->id,
        'user_id' => $this->farmer->id,
        'pond_name' => 'Growout Pond 2',
        'dissolved_oxygen' => 4.2,
        'ph' => 7.6,
        'salinity' => 15.0,
        'temperature' => 29.0,
        'recorded_at' => now()->subHours(5),
    ]);
});

test('farmer can stream or download their own farm profile dossier PDF', function () {
    Sanctum::actingAs($this->farmer);

    $response = $this->get("/api/v1/farms/{$this->farm->id}/dossier");

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
    expect($response->headers->get('content-disposition'))->toContain('inline');
    expect($response->headers->get('x-dossier-farm-id'))->toBe((string) $this->farm->id);
});

test('farmer receives attachment disposition when download flag is passed', function () {
    Sanctum::actingAs($this->farmer);

    $response = $this->get("/api/v1/farms/{$this->farm->id}/dossier?download=1");

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
    expect($response->headers->get('content-disposition'))->toContain('attachment');
});

test('admin can access and download any farm dossier via both routes', function () {
    Sanctum::actingAs($this->admin);

    // General route
    $res1 = $this->get("/api/v1/farms/{$this->farm->id}/dossier");
    $res1->assertOk();
    expect($res1->headers->get('content-type'))->toContain('application/pdf');

    // Admin-specific route
    $res2 = $this->get("/api/v1/admin/farms/{$this->farm->id}/dossier");
    $res2->assertOk();
    expect($res2->headers->get('content-type'))->toContain('application/pdf');
});

test('another farmer cannot access another farmer farm dossier', function () {
    Sanctum::actingAs($this->otherFarmer);

    $response = $this->get("/api/v1/farms/{$this->farm->id}/dossier");

    $response->assertForbidden();
});

test('unauthenticated request to dossier is rejected with 401', function () {
    $response = $this->getJson("/api/v1/farms/{$this->farm->id}/dossier");

    $response->assertUnauthorized();
});

test('farm dossier renders correctly when farm has production cycles and vet records', function () {
    \App\Models\FarmCycle::create([
        'farm_id' => $this->farm->id,
        'label' => 'Cycle 2026-A',
        'start_date' => now()->subMonths(2),
        'end_date' => null,
        'created_by' => $this->farmer->id,
    ]);

    Sanctum::actingAs($this->admin);

    $response = $this->get("/api/v1/admin/farms/{$this->farm->id}/dossier?download=1");

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

