<?php

use App\Models\Farm;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('admin can list unique farmers with farm counts and total area', function () {
    $admin = User::factory()->admin()->create();
    $farmer1 = User::factory()->farmer()->create(['name' => 'Unique Farmer One', 'district' => 'Satkhira']);
    $farmer2 = User::factory()->farmer()->create(['name' => 'Unique Farmer Two', 'district' => 'Khulna']);

    // Farmer 1 has 2 farms
    Farm::factory()->create(['user_id' => $farmer1->id, 'total_area' => 10.5]);
    Farm::factory()->create(['user_id' => $farmer1->id, 'total_area' => 5.5]);

    // Farmer 2 has 1 farm
    Farm::factory()->create(['user_id' => $farmer2->id, 'total_area' => 20.0]);

    Sanctum::actingAs($admin);

    $response = $this->getJson('/api/v1/admin/farmers');

    $response->assertStatus(200)
        ->assertJsonCount(2, 'data');

    $farmersData = collect($response->json('data'));
    $f1 = $farmersData->firstWhere('id', $farmer1->id);
    $f2 = $farmersData->firstWhere('id', $farmer2->id);

    expect($f1['farms_count'])->toBe(2)
        ->and((float) $f1['total_area'])->toBe(16.0)
        ->and($f2['farms_count'])->toBe(1)
        ->and((float) $f2['total_area'])->toBe(20.0);
});

test('admin can create a new farmer account', function () {
    $admin = User::factory()->admin()->create();
    Sanctum::actingAs($admin);

    $payload = [
        'name' => 'New Test Farmer',
        'phone' => '01719999999',
        'district' => 'Bagerhat',
        'gender' => 'male',
        'email' => 'newfarmer@test.com',
        'password' => 'secret123',
    ];

    $response = $this->postJson('/api/v1/admin/farmers', $payload);

    $response->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.name', 'New Test Farmer')
        ->assertJsonPath('data.phone', '01719999999')
        ->assertJsonPath('data.district', 'Bagerhat');

    $createdFarmer = User::where('phone', '01719999999')->first();
    expect($createdFarmer)->not->toBeNull()
        ->and($createdFarmer->hasRole('farmer'))->toBeTrue();
});

test('admin can view farmer profile with all farms and orders', function () {
    $admin = User::factory()->admin()->create();
    $farmer = User::factory()->farmer()->create(['name' => 'Profile Farmer']);

    $farm1 = Farm::factory()->create(['user_id' => $farmer->id, 'farm_name' => 'Farm Alpha']);
    $farm2 = Farm::factory()->create(['user_id' => $farmer->id, 'farm_name' => 'Farm Beta']);
    $order = Order::factory()->create(['user_id' => $farmer->id]);

    Sanctum::actingAs($admin);

    $response = $this->getJson("/api/v1/admin/farmers/{$farmer->id}");

    $response->assertStatus(200)
        ->assertJsonPath('data.id', $farmer->id)
        ->assertJsonPath('data.name', 'Profile Farmer')
        ->assertJsonPath('data.farms_count', 2)
        ->assertJsonCount(2, 'data.farms')
        ->assertJsonCount(1, 'data.orders');
});

test('non-admin cannot access admin farmer endpoints', function () {
    $farmer = User::factory()->farmer()->create();
    Sanctum::actingAs($farmer);

    $this->getJson('/api/v1/admin/farmers')->assertStatus(403);
    $this->postJson('/api/v1/admin/farmers', ['name' => 'Test'])->assertStatus(403);
    $this->getJson("/api/v1/admin/farmers/{$farmer->id}")->assertStatus(403);
});

test('admin can search farmers by name, phone, or district', function () {
    $admin = User::factory()->admin()->create();
    $farmer1 = User::factory()->farmer()->create(['name' => 'Abdur Rahim', 'phone' => '01711111111', 'district' => 'Satkhira']);
    $farmer2 = User::factory()->farmer()->create(['name' => 'Karim Ullah', 'phone' => '01722222222', 'district' => 'Khulna']);

    Sanctum::actingAs($admin);

    // Search by name
    $resName = $this->getJson('/api/v1/admin/farmers?search=Rahim');
    $resName->assertStatus(200)->assertJsonCount(1, 'data');
    expect($resName->json('data.0.name'))->toBe('Abdur Rahim');

    // Search by phone
    $resPhone = $this->getJson('/api/v1/admin/farmers?search=01722222222');
    $resPhone->assertStatus(200)->assertJsonCount(1, 'data');
    expect($resPhone->json('data.0.name'))->toBe('Karim Ullah');

    // Search by district
    $resDistrict = $this->getJson('/api/v1/admin/farmers?search=Khulna');
    $resDistrict->assertStatus(200)->assertJsonCount(1, 'data');
    expect($resDistrict->json('data.0.name'))->toBe('Karim Ullah');
});
