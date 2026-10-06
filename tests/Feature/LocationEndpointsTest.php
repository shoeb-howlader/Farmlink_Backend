<?php

use App\Models\District;
use App\Models\Division;
use App\Models\Farm;
use App\Models\Pourashava;
use App\Models\Union;
use App\Models\Upazila;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    // Create minimal geocode hierarchy for fast in-memory testing
    $div = Division::create(['name' => 'Dhaka', 'bn_name' => 'ঢাকা']);
    $bagerhatDiv = Division::create(['name' => 'Khulna', 'bn_name' => 'খুলনা']);

    $dist = District::create(['division_id' => $div->id, 'name' => 'Dhaka', 'bn_name' => 'ঢাকা']);
    $bagerhat = District::create(['division_id' => $bagerhatDiv->id, 'name' => 'Bagerhat', 'bn_name' => 'বাগেরহাট']);

    $up = Upazila::create(['district_id' => $dist->id, 'name' => 'Dhamrai', 'bn_name' => 'ধামরাই']);
    $bagerhatUp = Upazila::create(['district_id' => $bagerhat->id, 'name' => 'Bagerhat Sadar', 'bn_name' => 'বাগেরহাট সদর']);

    $union = Union::create(['upazila_id' => $up->id, 'name' => 'Sombhag', 'bn_name' => 'সোমভাগ']);
    $pour = Pourashava::create(['upazila_id' => $up->id, 'name' => 'Dhamrai Pourashava', 'bn_name' => 'ধামরাই পৌরসভা']);
});


test('public can fetch divisions with bn_name', function () {
    $response = $this->getJson('/api/v1/locations/divisions');

    $response->assertOk()
        ->assertJsonStructure([
            'status',
            'data' => [
                '*' => ['id', 'name', 'bn_name'],
            ],
        ]);

    $data = $response->json('data');
    expect(count($data))->toBeGreaterThanOrEqual(2);
    expect($data[0]['bn_name'])->not->toBeEmpty();
});

test('public can fetch districts filtered by division_id', function () {
    $dhakaDiv = Division::where('name', 'Dhaka')->first();

    $response = $this->getJson("/api/v1/locations/districts?division_id={$dhakaDiv->id}");

    $response->assertOk()
        ->assertJsonStructure([
            'status',
            'data' => [
                '*' => ['id', 'division_id', 'name', 'bn_name'],
            ],
        ]);

    $data = $response->json('data');
    foreach ($data as $district) {
        expect($district['division_id'])->toBe($dhakaDiv->id);
    }
});

test('public can fetch upazilas filtered by district_id', function () {
    $district = District::first();

    $response = $this->getJson("/api/v1/locations/upazilas?district_id={$district->id}");

    $response->assertOk();
    $data = $response->json('data');
    foreach ($data as $upazila) {
        expect($upazila['district_id'])->toBe($district->id);
    }
});

test('public can fetch unions and pourashavas filtered by upazila_id', function () {
    $upazila = Upazila::first();

    $unionsResp = $this->getJson("/api/v1/locations/unions?upazila_id={$upazila->id}");
    $unionsResp->assertOk();

    $pourashavasResp = $this->getJson("/api/v1/locations/pourashavas?upazila_id={$upazila->id}");
    $pourashavasResp->assertOk();
});

test('farmer can register farm using location foreign keys', function () {
    $user = User::factory()->create();
    $user->syncRoles(['farmer']);
    Sanctum::actingAs($user);

    $district = District::first();
    $upazila = Upazila::where('district_id', $district->id)->first();
    $union = Union::where('upazila_id', $upazila->id)->first();

    $payload = [
        'farm_name' => 'Automated Test Green Farm',
        'farm_type' => 'Own',
        'total_area' => 5.5,
        'main_culture_type' => 'Shrimp',
        'farming_system' => 'Semi-intensive',
        'district_id' => $district->id,
        'upazila_id' => $upazila->id,
        'union_id' => $union?->id,
    ];

    $response = $this->postJson('/api/v1/farms', $payload);

    $response->assertCreated();
    $farmData = $response->json('data');
    expect($farmData['district_id'])->toBe($district->id);
    expect($farmData['district'])->toBe($district->name);
    expect($farmData['upazila_id'])->toBe($upazila->id);
    expect($farmData['upazila'])->toBe($upazila->name);
});

test('admin can view unmatched locations report and reconcile a farm', function () {
    $admin = User::factory()->create();
    $admin->syncRoles(['admin']);
    Sanctum::actingAs($admin);

    // Create a farm with mismatched location
    $farm = Farm::factory()->create([
        'district' => 'Bagerhat',
        'upazila' => 'Koyra', // In Khulna
        'district_id' => null,
        'upazila_id' => null,
    ]);

    $reportResp = $this->getJson('/api/v1/admin/farms-unmatched-locations');
    $reportResp->assertOk()
        ->assertJsonStructure([
            'summary' => ['total_farms', 'fully_matched', 'needs_reconciliation'],
            'data',
        ]);

    // Reconcile it
    $targetDistrict = District::where('name', 'Bagerhat')->first();
    $targetUpazila = Upazila::where('district_id', $targetDistrict->id)->first();

    $patchResp = $this->patchJson("/api/v1/admin/farms/{$farm->id}/location", [
        'division_id' => $targetDistrict->division_id,
        'district_id' => $targetDistrict->id,
        'upazila_id' => $targetUpazila->id,
    ]);

    $patchResp->assertOk();
    $farm->refresh();
    expect($farm->district_id)->toBe($targetDistrict->id);
    expect($farm->upazila_id)->toBe($targetUpazila->id);
});

test('migrate farm locations artisan command runs successfully', function () {
    $this->artisan('farmlink:migrate-farm-locations --dry-run')
        ->assertExitCode(0);
});
