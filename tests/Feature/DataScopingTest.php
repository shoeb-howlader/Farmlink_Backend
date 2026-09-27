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

test('farmer A cannot see farmer B records on index and receives 403 on direct ID access', function () {
    // 1. Create two distinct farmers
    $farmerA = User::factory()->farmer()->create(['name' => 'Farmer A', 'email' => 'farmerA@test.com']);
    $farmerB = User::factory()->farmer()->create(['name' => 'Farmer B', 'email' => 'farmerB@test.com']);

    // 2. Create a farm for each farmer
    $farmA = Farm::factory()->create(['user_id' => $farmerA->id, 'farm_name' => 'Farmer A Aqua Farm']);
    $farmB = Farm::factory()->create(['user_id' => $farmerB->id, 'farm_name' => 'Farmer B Aqua Farm']);

    // 3. Create an order for each farmer
    $orderA = Order::factory()->create(['user_id' => $farmerA->id, 'total' => 1500.00]);
    $orderB = Order::factory()->create(['user_id' => $farmerB->id, 'total' => 3000.00]);

    // 4. Act as Farmer A
    Sanctum::actingAs($farmerA);

    // --- FARMS SCOPING ASSERTIONS ---
    // Farmer A index must return only Farmer A's farm
    $farmsResponse = $this->getJson('/api/v1/farms');
    $farmsResponse->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $farmA->id)
        ->assertJsonPath('data.0.farm_name', 'Farmer A Aqua Farm');

    // Attempting query param spoofing (?user_id=farmerB->id) must still only return Farmer A's farm
    $spoofedFarmsResponse = $this->getJson("/api/v1/farms?user_id={$farmerB->id}");
    $spoofedFarmsResponse->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $farmA->id);

    // Farmer A can access own farm detail
    $this->getJson("/api/v1/farms/{$farmA->id}")
        ->assertStatus(200)
        ->assertJsonPath('data.id', $farmA->id);

    // Farmer A receives 403 Forbidden when directly accessing Farmer B's farm
    $this->getJson("/api/v1/farms/{$farmB->id}")
        ->assertStatus(403);

    // --- ORDERS SCOPING ASSERTIONS ---
    // Farmer A index must return only Farmer A's order
    $ordersResponse = $this->getJson('/api/v1/orders');
    $ordersResponse->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $orderA->id);

    // Attempting query param spoofing (?user_id=farmerB->id) must still only return Farmer A's order
    $spoofedOrdersResponse = $this->getJson("/api/v1/orders?user_id={$farmerB->id}");
    $spoofedOrdersResponse->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $orderA->id);

    // Farmer A can access own order detail
    $this->getJson("/api/v1/orders/{$orderA->id}")
        ->assertStatus(200)
        ->assertJsonPath('data.id', $orderA->id);

    // Farmer A receives 403 Forbidden when directly accessing Farmer B's order
    $this->getJson("/api/v1/orders/{$orderB->id}")
        ->assertStatus(403);
});

test('admin calling personal endpoints without user_id only receives admin own records', function () {
    $admin = User::factory()->admin()->create(['name' => 'Admin User']);
    $farmer = User::factory()->farmer()->create(['name' => 'Test Farmer']);

    Farm::factory()->create(['user_id' => $farmer->id, 'farm_name' => 'Farmer Farm']);
    Order::factory()->create(['user_id' => $farmer->id]);

    Sanctum::actingAs($admin);

    // Admin calling personal /farms endpoint without user_id receives 0 records (admin owns no farms)
    $this->getJson('/api/v1/farms')
        ->assertStatus(200)
        ->assertJsonCount(0, 'data');

    // Admin calling personal /orders endpoint without user_id receives 0 records (admin placed no orders)
    $this->getJson('/api/v1/orders')
        ->assertStatus(200)
        ->assertJsonCount(0, 'data');

    // Admin accessing admin endpoints sees the farmer records
    $this->getJson('/api/v1/admin/farms')
        ->assertStatus(200)
        ->assertJsonPath('meta.total', 1);

    $this->getJson('/api/v1/admin/orders')
        ->assertStatus(200)
        ->assertJsonPath('meta.total', 1);
});
