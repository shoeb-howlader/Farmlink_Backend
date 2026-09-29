<?php

use App\Models\AdminNotification;
use App\Models\Broadcast;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('admin can send a broadcast to all farmers and fan out notifications', function () {
    $admin = User::factory()->admin()->create();

    $farmer1 = User::factory()->farmer()->create(['district' => 'Khulna']);
    $farmer2 = User::factory()->farmer()->create(['district' => 'Satkhira']);
    $vet = User::factory()->veterinaryDoctor()->create();

    Sanctum::actingAs($admin);

    $payload = [
        'title' => 'Monsoon Salinity Advisory',
        'message' => 'Please monitor salinity levels closely during sudden heavy rainfalls.',
        'target_audience' => 'all_farmers',
    ];

    $response = $this->postJson('/api/v1/admin/broadcasts', $payload);

    $response->assertStatus(201)
        ->assertJsonPath('data.sent_count', 2)
        ->assertJsonPath('data.title', 'Monsoon Salinity Advisory');

    $this->assertDatabaseHas('broadcasts', [
        'title' => 'Monsoon Salinity Advisory',
        'sent_count' => 2,
    ]);

    // Check individual notification rows fanned out
    expect(AdminNotification::where('type', 'broadcast')->count())->toBe(2);
    expect(AdminNotification::where('user_id', $farmer1->id)->where('type', 'broadcast')->exists())->toBeTrue();
    expect(AdminNotification::where('user_id', $farmer2->id)->where('type', 'broadcast')->exists())->toBeTrue();
    expect(AdminNotification::where('user_id', $vet->id)->where('type', 'broadcast')->exists())->toBeFalse();

    $this->assertDatabaseHas('activity_logs', [
        'action' => 'broadcast.sent',
    ]);
});

test('admin can target farmers in a specific district', function () {
    $admin = User::factory()->admin()->create();

    $farmerKhulna = User::factory()->farmer()->create(['district' => 'Khulna']);
    $farmerSatkhira = User::factory()->farmer()->create(['district' => 'Satkhira']);

    Sanctum::actingAs($admin);

    $payload = [
        'title' => 'Khulna Cyclone Warning',
        'message' => 'High tide expected along Khulna coastal belts.',
        'target_audience' => 'district_farmers',
        'target_district' => 'Khulna',
    ];

    $response = $this->postJson('/api/v1/admin/broadcasts', $payload);

    $response->assertStatus(201)
        ->assertJsonPath('data.sent_count', 1);

    expect(AdminNotification::where('user_id', $farmerKhulna->id)->where('type', 'broadcast')->exists())->toBeTrue();
    expect(AdminNotification::where('user_id', $farmerSatkhira->id)->where('type', 'broadcast')->exists())->toBeFalse();
});

test('broadcast history shows accurate sent and read counts', function () {
    $admin = User::factory()->admin()->create();
    $farmer = User::factory()->farmer()->create();

    Sanctum::actingAs($admin);

    $this->postJson('/api/v1/admin/broadcasts', [
        'title' => 'Vaccination Campaign',
        'message' => 'Free fish health checkup this Saturday.',
        'target_audience' => 'all_farmers',
    ])->assertStatus(201);

    $broadcast = Broadcast::first();

    // Check initial history: read_count is 0
    $historyRes = $this->getJson('/api/v1/admin/broadcasts');
    $historyRes->assertStatus(200)
        ->assertJsonPath('data.0.sent_count', 1)
        ->assertJsonPath('data.0.read_count', 0);

    // Farmer marks the notification as read
    $notification = AdminNotification::where('user_id', $farmer->id)->first();
    $notification->update(['read_at' => now()]);

    // Check history again: read_count is now 1
    $historyRes2 = $this->getJson('/api/v1/admin/broadcasts');
    $historyRes2->assertStatus(200)
        ->assertJsonPath('data.0.read_count', 1);
});
