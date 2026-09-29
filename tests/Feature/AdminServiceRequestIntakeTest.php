<?php

use App\Models\Farm;
use App\Models\ServiceRequest;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('admin can create a service request on behalf of a farmer via phone intake', function () {
    $admin = User::factory()->admin()->create();
    $farmer = User::factory()->farmer()->create();
    $farm = Farm::factory()->create(['user_id' => $farmer->id]);

    Sanctum::actingAs($admin);

    $payload = [
        'farmer_id' => $farmer->id,
        'farm_id' => $farm->id,
        'type' => 'vet',
        'description' => 'Farmer called helpline regarding sudden oxygen depletion in pond #2.',
        'urgency' => 'urgent',
        'source_channel' => 'phone_call',
    ];

    $response = $this->postJson('/api/v1/admin/service-requests', $payload);

    $response->assertStatus(201)
        ->assertJsonPath('data.farmer_id', $farmer->id)
        ->assertJsonPath('data.farm_id', $farm->id)
        ->assertJsonPath('data.source_channel', 'phone_call')
        ->assertJsonPath('data.urgency', 'urgent');

    $this->assertDatabaseHas('service_requests', [
        'farmer_id' => $farmer->id,
        'farm_id' => $farm->id,
        'source_channel' => 'phone_call',
        'urgency' => 'urgent',
    ]);

    $this->assertDatabaseHas('activity_logs', [
        'action' => 'service_request.admin_created',
    ]);

    $this->assertDatabaseHas('admin_notifications', [
        'user_id' => $farmer->id,
        'type' => 'service_request.created_by_admin',
    ]);
});

test('admin cannot create service request if farm does not belong to selected farmer', function () {
    $admin = User::factory()->admin()->create();
    $farmer1 = User::factory()->farmer()->create();
    $farmer2 = User::factory()->farmer()->create();
    $farm2 = Farm::factory()->create(['user_id' => $farmer2->id]);

    Sanctum::actingAs($admin);

    $payload = [
        'farmer_id' => $farmer1->id,
        'farm_id' => $farm2->id,
        'type' => 'vet',
        'description' => 'Test intake description.',
        'urgency' => 'normal',
        'source_channel' => 'walk_in',
    ];

    $response = $this->postJson('/api/v1/admin/service-requests', $payload);

    $response->assertStatus(422)
        ->assertJsonPath('message', 'Selected farm does not belong to the selected farmer.');
});

test('admin can filter service requests by source_channel', function () {
    $admin = User::factory()->admin()->create();
    $farmer = User::factory()->farmer()->create();
    $farm = Farm::factory()->create(['user_id' => $farmer->id]);

    ServiceRequest::factory()->create([
        'farmer_id' => $farmer->id,
        'farm_id' => $farm->id,
        'source_channel' => 'phone_call',
    ]);

    ServiceRequest::factory()->create([
        'farmer_id' => $farmer->id,
        'farm_id' => $farm->id,
        'source_channel' => 'self_service',
    ]);

    Sanctum::actingAs($admin);

    $response = $this->getJson('/api/v1/admin/service-requests?source_channel=phone_call');

    $response->assertStatus(200);
    $data = $response->json('data');
    expect(count($data))->toBe(1)
        ->and($data[0]['source_channel'])->toBe('phone_call');
});
