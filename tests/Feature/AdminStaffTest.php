<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminStaffTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_non_admin_cannot_access_staff_endpoints(): void
    {
        $farmer = User::factory()->create();
        $farmer->syncRoles(['farmer']);

        $this->actingAs($farmer, 'sanctum')
            ->getJson('/api/v1/admin/staff')
            ->assertStatus(403);

        $this->actingAs($farmer, 'sanctum')
            ->postJson('/api/v1/admin/staff', [
                'name' => 'John DEO',
                'phone' => '01711999888',
                'role' => 'data_entry_operator',
            ])
            ->assertStatus(403);
    }

    public function test_admin_can_list_staff_members_and_filter_by_role(): void
    {
        $admin = User::factory()->create();
        $admin->syncRoles(['admin']);

        $deo = User::factory()->create(['name' => 'DEO Alice', 'district' => 'Bagerhat']);
        $deo->syncRoles(['data_entry_operator']);

        $vet = User::factory()->create(['name' => 'Dr. Vet Bob', 'district' => 'Khulna']);
        $vet->syncRoles(['veterinary_doctor']);

        $consultant = User::factory()->create(['name' => 'Consultant Charlie', 'district' => 'Satkhira']);
        $consultant->syncRoles(['consultant']);

        // Farmers should NOT appear in staff list
        $farmer = User::factory()->create(['name' => 'Farmer Dave']);
        $farmer->syncRoles(['farmer']);

        // 1. List all staff
        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/staff');
        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');

        // 2. Filter by veterinary_doctor
        $vetResponse = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/staff?role=veterinary_doctor');
        $vetResponse->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Dr. Vet Bob')
            ->assertJsonPath('data.0.role', 'veterinary_doctor');
    }

    public function test_admin_can_create_staff_member(): void
    {
        $admin = User::factory()->create();
        $admin->syncRoles(['admin']);

        $payload = [
            'name' => 'Tariq Islam',
            'phone' => '01812345678',
            'email' => 'tariq.deo@farmlink.com',
            'district' => 'Jessore',
            'role' => 'data_entry_operator',
            'gender' => 'male',
            'password' => 'secret123',
        ];

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/staff', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Tariq Islam')
            ->assertJsonPath('data.role', 'data_entry_operator')
            ->assertJsonPath('data.district', 'Jessore');

        $staffUser = User::where('phone', '01812345678')->first();
        $this->assertNotNull($staffUser);
        $this->assertTrue($staffUser->hasRole('data_entry_operator'));
    }

    public function test_invalid_staff_role_is_rejected(): void
    {
        $admin = User::factory()->create();
        $admin->syncRoles(['admin']);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/staff', [
            'name' => 'Hacker Guy',
            'phone' => '01912345678',
            'role' => 'super_admin_invalid',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['role']);
    }

    public function test_admin_can_update_staff_member(): void
    {
        $admin = User::factory()->create();
        $admin->syncRoles(['admin']);

        $staff = User::factory()->create(['name' => 'Original Name', 'district' => 'Khulna']);
        $staff->syncRoles(['data_entry_operator']);

        $response = $this->actingAs($admin, 'sanctum')->patchJson("/api/v1/admin/staff/{$staff->id}", [
            'name' => 'Updated Name',
            'phone' => $staff->phone,
            'role' => 'consultant',
            'gender' => 'male',
            'district' => 'Bagerhat',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.role', 'consultant')
            ->assertJsonPath('data.district', 'Bagerhat');

        $staff->refresh();
        $this->assertEquals('Updated Name', $staff->name);
        $this->assertTrue($staff->hasRole('consultant'));
    }

    public function test_admin_can_toggle_staff_status(): void
    {
        $admin = User::factory()->create();
        $admin->syncRoles(['admin']);

        $staff = User::factory()->create(['is_active' => true]);
        $staff->syncRoles(['veterinary_doctor']);

        // Deactivate
        $deactResponse = $this->actingAs($admin, 'sanctum')->patchJson("/api/v1/admin/staff/{$staff->id}/status", [
            'is_active' => false,
        ]);
        $deactResponse->assertStatus(200)
            ->assertJsonPath('data.is_active', false);

        $this->assertFalse($staff->fresh()->is_active);

        // Reactivate
        $actResponse = $this->actingAs($admin, 'sanctum')->patchJson("/api/v1/admin/staff/{$staff->id}/status", [
            'is_active' => true,
        ]);
        $actResponse->assertStatus(200)
            ->assertJsonPath('data.is_active', true);

        $this->assertTrue($staff->fresh()->is_active);
    }

    public function test_admin_cannot_deactivate_or_delete_own_account(): void
    {
        $admin = User::factory()->create();
        $admin->syncRoles(['admin']);

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/admin/staff/{$admin->id}/status", ['is_active' => false])
            ->assertStatus(422)
            ->assertJsonPath('message', 'You cannot deactivate your own administrative account.');

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/v1/admin/staff/{$admin->id}")
            ->assertStatus(422)
            ->assertJsonPath('message', 'You cannot delete your own administrative account.');
    }

    public function test_admin_cannot_delete_staff_with_attached_records(): void
    {
        $admin = User::factory()->create();
        $admin->syncRoles(['admin']);

        $vet = User::factory()->create();
        $vet->syncRoles(['veterinary_doctor']);

        $farm = \App\Models\Farm::factory()->create();

        // Create an attached vet record
        \App\Models\VetRecord::factory()->create([
            'farm_id' => $farm->id,
            'vet_id' => $vet->id,
        ]);

        $response = $this->actingAs($admin, 'sanctum')->deleteJson("/api/v1/admin/staff/{$vet->id}");

        $response->assertStatus(422)
            ->assertJsonPath('attached_records.vet_records', true);

        $this->assertDatabaseHas('users', ['id' => $vet->id]);
    }

    public function test_admin_can_delete_staff_without_records(): void
    {
        $admin = User::factory()->create();
        $admin->syncRoles(['admin']);

        $staff = User::factory()->create();
        $staff->syncRoles(['data_entry_operator']);

        $response = $this->actingAs($admin, 'sanctum')->deleteJson("/api/v1/admin/staff/{$staff->id}");
        $response->assertStatus(200);

        $this->assertDatabaseMissing('users', ['id' => $staff->id]);
    }

    public function test_deactivated_user_cannot_login(): void
    {
        $user = User::factory()->create([
            'phone' => '01700998877',
            'password' => \Illuminate\Support\Facades\Hash::make('secret123'),
            'is_active' => false,
        ]);
        $user->syncRoles(['data_entry_operator']);

        $response = $this->postJson('/api/v1/login', [
            'phone' => '01700998877',
            'password' => 'secret123',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('message', 'Your account has been deactivated. Please contact an administrator.');
    }
}
