<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminFarmerRestrictionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    public function test_admin_can_filter_farmers_by_restriction_status(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $activeFarmer = User::factory()->create(['is_active' => true, 'is_blacklisted' => false, 'cod_blocked' => false]);
        $activeFarmer->assignRole('farmer');

        $codBlockedFarmer = User::factory()->create(['is_active' => true, 'is_blacklisted' => false, 'cod_blocked' => true]);
        $codBlockedFarmer->assignRole('farmer');

        $bannedFarmer = User::factory()->create(['is_active' => true, 'is_blacklisted' => true, 'blacklist_reason' => 'Doorstep fraud']);
        $bannedFarmer->assignRole('farmer');

        $consultationBlockedFarmer = User::factory()->create(['is_active' => true, 'is_blacklisted' => false, 'cod_blocked' => false, 'consultations_blocked' => true]);
        $consultationBlockedFarmer->assignRole('farmer');

        // Test filter restriction=blacklisted
        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/farmers?restriction=blacklisted');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $this->assertEquals($bannedFarmer->id, $response->json('data.0.id'));
        $this->assertEquals(1, $response->json('summary.total_blacklisted'));

        // Test filter restriction=cod_blocked
        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/farmers?restriction=cod_blocked');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $this->assertEquals($codBlockedFarmer->id, $response->json('data.0.id'));

        // Test filter restriction=consultations_blocked
        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/farmers?restriction=consultations_blocked');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $this->assertEquals($consultationBlockedFarmer->id, $response->json('data.0.id'));
        $this->assertEquals(1, $response->json('summary.total_consultations_blocked'));

        // Test filter restriction=active
        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/farmers?restriction=active');
        $response->assertOk();
        $response->assertJsonCount(3, 'data'); // activeFarmer + codBlockedFarmer + consultationBlockedFarmer
    }

    public function test_admin_can_update_farmer_account_restrictions(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $farmer = User::factory()->create([
            'is_blacklisted' => false,
            'cod_blocked' => false,
            'consultations_blocked' => false,
            'password' => \Illuminate\Support\Facades\Hash::make('secret123'),
        ]);
        $farmer->assignRole('farmer');

        // Blacklist farmer and restrict consultations
        $response = $this->actingAs($admin, 'sanctum')->patchJson("/api/v1/admin/farmers/{$farmer->id}/restriction", [
            'is_blacklisted' => true,
            'blacklist_reason' => 'Repeated fraudulent COD orders from Fake IP',
            'cod_blocked' => true,
            'consultations_blocked' => true,
        ]);

        $response->assertOk();
        $this->assertTrue($response->json('data.is_blacklisted'));
        $this->assertTrue($response->json('data.cod_blocked'));
        $this->assertTrue($response->json('data.consultations_blocked'));
        $this->assertFalse($response->json('data.is_active')); // Synchronized Hard Ban
        $this->assertEquals('Repeated fraudulent COD orders from Fake IP', $response->json('data.blacklist_reason'));

        $farmer->refresh();
        $this->assertTrue($farmer->is_blacklisted);
        $this->assertTrue($farmer->cod_blocked);
        $this->assertTrue($farmer->consultations_blocked);
        $this->assertFalse($farmer->is_active);
        $this->assertCount(0, $farmer->tokens);

        // Attempt login as blacklisted farmer
        $loginResponse = $this->postJson('/api/v1/login', [
            'phone' => $farmer->phone,
            'password' => 'secret123',
        ]);
        $loginResponse->assertStatus(403);
        $this->assertStringContainsString('suspended by administration', $loginResponse->json('message'));
        $this->assertStringContainsString('Repeated fraudulent COD orders from Fake IP', $loginResponse->json('message'));

        // Unban farmer, keep consultations_blocked
        $response = $this->actingAs($admin, 'sanctum')->patchJson("/api/v1/admin/farmers/{$farmer->id}/restriction", [
            'is_blacklisted' => false,
            'cod_blocked' => false,
            'consultations_blocked' => true,
        ]);

        $response->assertOk();
        $this->assertFalse($response->json('data.is_blacklisted'));
        $this->assertFalse($response->json('data.cod_blocked'));
        $this->assertTrue($response->json('data.consultations_blocked'));
        $this->assertTrue($response->json('data.is_active')); // Automatically restored
        $this->assertNull($response->json('data.blacklist_reason'));

        $farmer->refresh();
        $this->assertTrue($farmer->is_active);
        $this->assertTrue($farmer->consultations_blocked);

        // Can log in again
        $reLoginResponse = $this->postJson('/api/v1/login', [
            'phone' => $farmer->phone,
            'password' => 'secret123',
        ]);
        $reLoginResponse->assertOk();
    }

    public function test_farmer_with_consultations_blocked_cannot_book_field_visit(): void
    {
        $farmer = User::factory()->create([
            'is_active' => true,
            'is_blacklisted' => false,
            'consultations_blocked' => true,
        ]);
        $farmer->assignRole('farmer');

        $farm = \App\Models\Farm::factory()->create(['user_id' => $farmer->id]);

        $response = $this->actingAs($farmer, 'sanctum')->postJson("/api/v1/farms/{$farm->id}/service-requests", [
            'type' => 'vet',
            'description' => 'Need urgent pond inspection.',
            'urgency' => 'urgent',
        ]);

        $response->assertStatus(403);
        $this->assertStringContainsString('Consultations and pond doctor visit requests are currently restricted', $response->json('message'));
    }

    public function test_admin_cannot_create_service_request_for_consultation_blocked_farmer(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $farmer = User::factory()->create([
            'is_active' => true,
            'is_blacklisted' => false,
            'consultations_blocked' => true,
        ]);
        $farmer->assignRole('farmer');

        $farm = \App\Models\Farm::factory()->create(['user_id' => $farmer->id]);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/service-requests', [
            'farmer_id' => $farmer->id,
            'farm_id' => $farm->id,
            'type' => 'vet',
            'description' => 'Farmer called phone intake.',
            'urgency' => 'normal',
            'source_channel' => 'phone_call',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Farmer has field consultations and pond visits restricted', $response->json('message'));
    }
}

