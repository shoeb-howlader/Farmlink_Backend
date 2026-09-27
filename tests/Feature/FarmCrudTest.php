<?php

use App\Models\Farm;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('unauthenticated users cannot access farm endpoints', function () {
    $this->getJson('/api/v1/farms')->assertStatus(401);
    $this->postJson('/api/v1/farms', [])->assertStatus(401);
});

test('authenticated farmer can create a farm linked to their user_id', function () {
    $farmer = User::factory()->farmer()->create(['district' => 'Satkhira']);
    Sanctum::actingAs($farmer);

    $farmPayload = [
        'farm_name' => 'Sundarbans Aqua Culture',
        'farm_type' => 'Own',
        'total_area' => 15.5,
        'pond_count' => 3,
        'cultivation_area' => 12.0,
        'district' => 'Satkhira',
        'upazila' => 'Shyamnagar',
        'union' => 'Munshiganj',
        'village' => 'Burigoalini',
        'farm_address' => 'Near River Gate',
        'gps_lat' => 22.1852,
        'gps_lng' => 89.2450,
        'aquaculture_experience_years' => 10,
        'previous_farming_experience' => 'Shrimp',
        'main_culture_type' => 'Bagda',
        'farming_system' => 'Semi-intensive',
        'main_water_source' => 'River',
        'water_exchange_facility' => 'Yes',
        'water_source_distance' => '100m',
        'available_facilities' => ['Electric', 'Aerator', 'Generator'],
        'aerator_count' => 4,
        'aerator_hp' => '2 HP',
        'aerator_hours_per_day' => 8.5,
        'farm_manager' => 'Self',
        'technical_support_used' => 'Local Vet',
        'main_advice_source' => 'Feed Dealer',
    ];

    $response = $this->postJson('/api/v1/farms', $farmPayload);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'id',
                'user_id',
                'farm_name',
                'farm_type',
                'total_area',
                'pond_count',
                'cultivation_area',
                'district',
                'upazila',
                'farm_address',
                'gps_lat',
                'gps_lng',
                'aquaculture_experience_years',
                'previous_farming_experience',
                'main_culture_type',
                'farming_system',
                'main_water_source',
                'water_exchange_facility',
                'water_source_distance',
                'available_facilities',
                'aerator_count',
                'aerator_hp',
                'aerator_hours_per_day',
                'farm_manager',
                'technical_support_used',
                'main_advice_source',
            ],
        ])
        ->assertJson([
            'success' => true,
            'data' => [
                'user_id' => $farmer->id,
                'farm_name' => 'Sundarbans Aqua Culture',
                'main_culture_type' => 'Bagda',
                'farming_system' => 'Semi-intensive',
                'aerator_count' => 4,
                'aerator_hours_per_day' => 8.5,
            ],
        ]);

    $this->assertDatabaseHas('farms', [
        'user_id' => $farmer->id,
        'farm_name' => 'Sundarbans Aqua Culture',
        'main_culture_type' => 'Bagda',
    ]);
});

test('a farmer can have multiple farms and index returns only their farms', function () {
    $farmer1 = User::factory()->farmer()->create();
    $farmer2 = User::factory()->farmer()->create();

    // Farmer 1 has 2 farms
    Farm::factory()->create(['user_id' => $farmer1->id, 'farm_name' => 'Pond 1']);
    Farm::factory()->create(['user_id' => $farmer1->id, 'farm_name' => 'Pond 2']);

    // Farmer 2 has 1 farm
    Farm::factory()->create(['user_id' => $farmer2->id, 'farm_name' => 'Other Farmer Pond']);

    Sanctum::actingAs($farmer1);

    $response = $this->getJson('/api/v1/farms');

    $response->assertStatus(200)
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.user_id', $farmer1->id)
        ->assertJsonPath('data.1.user_id', $farmer1->id);

    // Farmer with zero farms receives empty list
    $farmer3 = User::factory()->farmer()->create();
    Sanctum::actingAs($farmer3);

    $this->getJson('/api/v1/farms')
        ->assertStatus(200)
        ->assertJsonCount(0, 'data');
});

test('a farmer can view their own farm but not another farmer farm', function () {
    $farmer1 = User::factory()->farmer()->create();
    $farmer2 = User::factory()->farmer()->create();

    $farm1 = Farm::factory()->create(['user_id' => $farmer1->id]);
    $farm2 = Farm::factory()->create(['user_id' => $farmer2->id]);

    Sanctum::actingAs($farmer1);

    // Can view own farm
    $this->getJson("/api/v1/farms/{$farm1->id}")
        ->assertStatus(200)
        ->assertJsonPath('data.id', $farm1->id);

    // Forbidden from viewing other farmer's farm
    $this->getJson("/api/v1/farms/{$farm2->id}")
        ->assertStatus(403);
});

test('a farmer can update their own farm but not another farmer farm', function () {
    $farmer1 = User::factory()->farmer()->create();
    $farmer2 = User::factory()->farmer()->create();

    $farm1 = Farm::factory()->create(['user_id' => $farmer1->id, 'farm_name' => 'Old Name']);
    $farm2 = Farm::factory()->create(['user_id' => $farmer2->id, 'farm_name' => 'Other Farm']);

    Sanctum::actingAs($farmer1);

    // Update own farm
    $this->putJson("/api/v1/farms/{$farm1->id}", [
        'farm_name' => 'New Updated Name',
    ])->assertStatus(200)
        ->assertJsonPath('data.farm_name', 'New Updated Name');

    $this->assertDatabaseHas('farms', [
        'id' => $farm1->id,
        'farm_name' => 'New Updated Name',
    ]);

    // Forbidden from updating another farmer's farm
    $this->putJson("/api/v1/farms/{$farm2->id}", [
        'farm_name' => 'Hacked Name',
    ])->assertStatus(403);
});

test('a farmer can delete their own farm but not another farmer farm', function () {
    $farmer1 = User::factory()->farmer()->create();
    $farmer2 = User::factory()->farmer()->create();

    $farm1 = Farm::factory()->create(['user_id' => $farmer1->id]);
    $farm2 = Farm::factory()->create(['user_id' => $farmer2->id]);

    Sanctum::actingAs($farmer1);

    // Forbidden from deleting another farmer's farm
    $this->deleteJson("/api/v1/farms/{$farm2->id}")->assertStatus(403);

    // Can delete own farm
    $this->deleteJson("/api/v1/farms/{$farm1->id}")->assertStatus(200);

    $this->assertDatabaseMissing('farms', ['id' => $farm1->id]);
});

test('non-admin user without explicit role is strictly scoped to their own farms in index', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    Farm::factory()->create(['user_id' => $user1->id, 'farm_name' => 'User1 Farm']);
    Farm::factory()->create(['user_id' => $user2->id, 'farm_name' => 'User2 Farm']);

    Sanctum::actingAs($user1);

    $response = $this->getJson('/api/v1/farms');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.user_id', $user1->id);
});

test('non-admin user cannot view other users farms by passing user_id query parameter', function () {
    $farmer1 = User::factory()->farmer()->create();
    $farmer2 = User::factory()->farmer()->create();

    Farm::factory()->create(['user_id' => $farmer1->id, 'farm_name' => 'Farm 1']);
    Farm::factory()->create(['user_id' => $farmer2->id, 'farm_name' => 'Farm 2']);

    Sanctum::actingAs($farmer1);

    // Attempting to query with farmer2's ID should still return only farmer1's farms
    $response = $this->getJson("/api/v1/farms?user_id={$farmer2->id}");

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.user_id', $farmer1->id);
});

