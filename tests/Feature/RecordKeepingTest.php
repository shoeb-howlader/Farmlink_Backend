<?php

use App\Models\ConsultantRecord;
use App\Models\Farm;
use App\Models\User;
use App\Models\VetRecord;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

/*
|--------------------------------------------------------------------------
| VetRecord Tests
|--------------------------------------------------------------------------
*/

test('unauthenticated users cannot access vet records endpoints', function () {
    $farm = Farm::factory()->create();

    $this->postJson("/api/v1/farms/{$farm->id}/vet-records", [])->assertStatus(401);
    $this->getJson("/api/v1/farms/{$farm->id}/vet-records")->assertStatus(401);
});

test('only veterinary_doctor or admin can create a vet record', function () {
    $farmer = User::factory()->farmer()->create();
    $farm = Farm::factory()->create(['user_id' => $farmer->id]);

    $payload = [
        'visit_date' => '2026-09-15',
        'findings' => 'Gill rot observed in pond 2',
        'treatment' => 'Apply copper sulfate and disinfectant',
        'medicine_given' => 'AquaClean 1L',
        'next_follow_up' => '2026-09-25',
    ];

    // Farmer cannot create
    Sanctum::actingAs($farmer);
    $this->postJson("/api/v1/farms/{$farm->id}/vet-records", $payload)->assertStatus(403);

    // Vet doctor can create
    $vet = User::factory()->veterinaryDoctor()->create();
    Sanctum::actingAs($vet);
    $vetResponse = $this->postJson("/api/v1/farms/{$farm->id}/vet-records", $payload);
    $vetResponse->assertStatus(201)
        ->assertJsonPath('data.vet_id', $vet->id)
        ->assertJsonPath('data.farm_id', $farm->id)
        ->assertJsonPath('data.medicine_given', 'AquaClean 1L');

    $this->assertDatabaseHas('vet_records', [
        'farm_id' => $farm->id,
        'vet_id' => $vet->id,
        'findings' => 'Gill rot observed in pond 2',
        'medicine_given' => 'AquaClean 1L',
    ]);

    // Admin can create
    $admin = User::factory()->admin()->create();
    Sanctum::actingAs($admin);
    $adminResponse = $this->postJson("/api/v1/farms/{$farm->id}/vet-records", [
        'visit_date' => '2026-09-16',
        'findings' => 'Routine inspection',
        'treatment' => 'None',
    ]);
    $adminResponse->assertStatus(201)
        ->assertJsonPath('data.vet_id', $admin->id);
});

test('vet record creation validates required fields', function () {
    $vet = User::factory()->veterinaryDoctor()->create();
    $farm = Farm::factory()->create();
    Sanctum::actingAs($vet);

    $this->postJson("/api/v1/farms/{$farm->id}/vet-records", [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['visit_date', 'findings', 'treatment']);
});

test('farmer can only view vet records for farms they own', function () {
    $farmer1 = User::factory()->farmer()->create();
    $farmer2 = User::factory()->farmer()->create();

    $farm1 = Farm::factory()->create(['user_id' => $farmer1->id]);
    $farm2 = Farm::factory()->create(['user_id' => $farmer2->id]);

    VetRecord::factory()->create(['farm_id' => $farm1->id]);
    VetRecord::factory()->create(['farm_id' => $farm2->id]);

    Sanctum::actingAs($farmer1);

    // Can view own farm records
    $this->getJson("/api/v1/farms/{$farm1->id}/vet-records")
        ->assertStatus(200)
        ->assertJsonCount(1, 'data');

    // Forbidden from viewing other farmer's farm records
    $this->getJson("/api/v1/farms/{$farm2->id}/vet-records")
        ->assertStatus(403);
});

test('vet can only view vet records they personally created unless admin', function () {
    $farmer = User::factory()->farmer()->create();
    $farm = Farm::factory()->create(['user_id' => $farmer->id]);

    $vet1 = User::factory()->veterinaryDoctor()->create();
    $vet2 = User::factory()->veterinaryDoctor()->create();
    $admin = User::factory()->admin()->create();

    // Vet 1 created 2 records on this farm
    VetRecord::factory()->create(['farm_id' => $farm->id, 'vet_id' => $vet1->id]);
    VetRecord::factory()->create(['farm_id' => $farm->id, 'vet_id' => $vet1->id]);

    // Vet 2 created 1 record on this farm
    VetRecord::factory()->create(['farm_id' => $farm->id, 'vet_id' => $vet2->id]);

    // Vet 1 views: only sees their 2 records
    Sanctum::actingAs($vet1);
    $response = $this->getJson("/api/v1/farms/{$farm->id}/vet-records");
    $response->assertStatus(200)
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.vet_id', $vet1->id)
        ->assertJsonPath('data.1.vet_id', $vet1->id);

    // Admin views: sees all 3 records
    Sanctum::actingAs($admin);
    $this->getJson("/api/v1/farms/{$farm->id}/vet-records")
        ->assertStatus(200)
        ->assertJsonCount(3, 'data');
});

/*
|--------------------------------------------------------------------------
| ConsultantRecord Tests
|--------------------------------------------------------------------------
*/

test('unauthenticated users cannot access consultant records endpoints', function () {
    $farm = Farm::factory()->create();

    $this->postJson("/api/v1/farms/{$farm->id}/consultant-records", [])->assertStatus(401);
    $this->getJson("/api/v1/farms/{$farm->id}/consultant-records")->assertStatus(401);
});

test('only consultant or admin can create a consultant record', function () {
    $farmer = User::factory()->farmer()->create();
    $farm = Farm::factory()->create(['user_id' => $farmer->id]);

    $payload = [
        'visit_date' => '2026-09-14',
        'recommendation' => 'Increase aerator runtime from 6h to 10h daily',
        'next_follow_up' => '2026-09-28',
    ];

    // Farmer cannot create
    Sanctum::actingAs($farmer);
    $this->postJson("/api/v1/farms/{$farm->id}/consultant-records", $payload)->assertStatus(403);

    // Consultant can create
    $consultant = User::factory()->consultant()->create();
    Sanctum::actingAs($consultant);
    $consultantResponse = $this->postJson("/api/v1/farms/{$farm->id}/consultant-records", $payload);
    $consultantResponse->assertStatus(201)
        ->assertJsonPath('data.consultant_id', $consultant->id)
        ->assertJsonPath('data.farm_id', $farm->id)
        ->assertJsonPath('data.recommendation', 'Increase aerator runtime from 6h to 10h daily');

    $this->assertDatabaseHas('consultant_records', [
        'farm_id' => $farm->id,
        'consultant_id' => $consultant->id,
        'recommendation' => 'Increase aerator runtime from 6h to 10h daily',
    ]);

    // Admin can create
    $admin = User::factory()->admin()->create();
    Sanctum::actingAs($admin);
    $this->postJson("/api/v1/farms/{$farm->id}/consultant-records", [
        'visit_date' => '2026-09-15',
        'recommendation' => 'Check water salinity weekly',
    ])->assertStatus(201);
});

test('consultant record creation validates required fields', function () {
    $consultant = User::factory()->consultant()->create();
    $farm = Farm::factory()->create();
    Sanctum::actingAs($consultant);

    $this->postJson("/api/v1/farms/{$farm->id}/consultant-records", [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['visit_date', 'recommendation']);
});

test('farmer can only view consultant records for farms they own', function () {
    $farmer1 = User::factory()->farmer()->create();
    $farmer2 = User::factory()->farmer()->create();

    $farm1 = Farm::factory()->create(['user_id' => $farmer1->id]);
    $farm2 = Farm::factory()->create(['user_id' => $farmer2->id]);

    ConsultantRecord::factory()->create(['farm_id' => $farm1->id]);
    ConsultantRecord::factory()->create(['farm_id' => $farm2->id]);

    Sanctum::actingAs($farmer1);

    // Can view own farm records
    $this->getJson("/api/v1/farms/{$farm1->id}/consultant-records")
        ->assertStatus(200)
        ->assertJsonCount(1, 'data');

    // Forbidden from viewing other farmer's farm records
    $this->getJson("/api/v1/farms/{$farm2->id}/consultant-records")
        ->assertStatus(403);
});

test('consultant can only view consultant records they personally created unless admin', function () {
    $farmer = User::factory()->farmer()->create();
    $farm = Farm::factory()->create(['user_id' => $farmer->id]);

    $consultant1 = User::factory()->consultant()->create();
    $consultant2 = User::factory()->consultant()->create();
    $admin = User::factory()->admin()->create();

    // Consultant 1 created 1 record
    ConsultantRecord::factory()->create(['farm_id' => $farm->id, 'consultant_id' => $consultant1->id]);

    // Consultant 2 created 2 records
    ConsultantRecord::factory()->create(['farm_id' => $farm->id, 'consultant_id' => $consultant2->id]);
    ConsultantRecord::factory()->create(['farm_id' => $farm->id, 'consultant_id' => $consultant2->id]);

    // Consultant 1 views: only sees their 1 record
    Sanctum::actingAs($consultant1);
    $response = $this->getJson("/api/v1/farms/{$farm->id}/consultant-records");
    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.consultant_id', $consultant1->id);

    // Admin views: sees all 3 records
    Sanctum::actingAs($admin);
    $this->getJson("/api/v1/farms/{$farm->id}/consultant-records")
        ->assertStatus(200)
        ->assertJsonCount(3, 'data');
});

/*
|--------------------------------------------------------------------------
| Admin Overdue Follow-ups Tests
|--------------------------------------------------------------------------
*/

test('non-admin cannot access overdue follow-ups endpoint', function () {
    $farmer = User::factory()->farmer()->create();
    $vet = User::factory()->veterinaryDoctor()->create();
    $consultant = User::factory()->consultant()->create();

    $this->getJson('/api/v1/admin/follow-ups/overdue')->assertStatus(401);

    Sanctum::actingAs($farmer);
    $this->getJson('/api/v1/admin/follow-ups/overdue')->assertStatus(403);

    Sanctum::actingAs($vet);
    $this->getJson('/api/v1/admin/follow-ups/overdue')->assertStatus(403);

    Sanctum::actingAs($consultant);
    $this->getJson('/api/v1/admin/follow-ups/overdue')->assertStatus(403);
});

test('admin can retrieve overdue follow-up farms', function () {
    $admin = User::factory()->admin()->create();

    // Farm 1: Overdue VetRecord with NO newer record logged -> SHOULD BE INCLUDED
    $farm1 = Farm::factory()->create(['farm_name' => 'Overdue Vet Farm']);
    VetRecord::factory()->create([
        'farm_id' => $farm1->id,
        'visit_date' => now()->subDays(20)->format('Y-m-d'),
        'next_follow_up' => now()->subDays(10)->format('Y-m-d'),
    ]);

    // Farm 2: Overdue ConsultantRecord with NO newer record logged -> SHOULD BE INCLUDED
    $farm2 = Farm::factory()->create(['farm_name' => 'Overdue Consultant Farm']);
    ConsultantRecord::factory()->create([
        'farm_id' => $farm2->id,
        'visit_date' => now()->subDays(25)->format('Y-m-d'),
        'next_follow_up' => now()->subDays(5)->format('Y-m-d'),
    ]);

    // Farm 3: Overdue VetRecord, BUT a newer VetRecord was logged since -> SHOULD BE EXCLUDED
    $farm3 = Farm::factory()->create(['farm_name' => 'Resolved Follow-up Farm']);
    VetRecord::factory()->create([
        'farm_id' => $farm3->id,
        'visit_date' => now()->subDays(20)->format('Y-m-d'),
        'next_follow_up' => now()->subDays(10)->format('Y-m-d'),
    ]);
    VetRecord::factory()->create([
        'farm_id' => $farm3->id,
        'visit_date' => now()->subDays(8)->format('Y-m-d'),
        'next_follow_up' => now()->addDays(10)->format('Y-m-d'), // future
    ]);

    // Farm 4: Future VetRecord follow-up -> SHOULD BE EXCLUDED
    $farm4 = Farm::factory()->create(['farm_name' => 'Future Follow-up Farm']);
    VetRecord::factory()->create([
        'farm_id' => $farm4->id,
        'visit_date' => now()->subDays(2)->format('Y-m-d'),
        'next_follow_up' => now()->addDays(12)->format('Y-m-d'),
    ]);

    // Farm 5: No follow-up date -> SHOULD BE EXCLUDED
    $farm5 = Farm::factory()->create(['farm_name' => 'No Follow-up Farm']);
    VetRecord::factory()->create([
        'farm_id' => $farm5->id,
        'visit_date' => now()->subDays(2)->format('Y-m-d'),
        'next_follow_up' => null,
    ]);

    Sanctum::actingAs($admin);

    $response = $this->getJson('/api/v1/admin/follow-ups/overdue');

    $response->assertStatus(200)
        ->assertJsonCount(2, 'data');

    $returnedFarmIds = collect($response->json('data'))->pluck('id')->all();

    expect($returnedFarmIds)->toContain($farm1->id)
        ->and($returnedFarmIds)->toContain($farm2->id)
        ->and($returnedFarmIds)->not->toContain($farm3->id)
        ->and($returnedFarmIds)->not->toContain($farm4->id)
        ->and($returnedFarmIds)->not->toContain($farm5->id);
});
