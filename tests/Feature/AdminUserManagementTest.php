<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $secondAdmin;
    protected User $farmer;
    protected User $vet;
    protected User $deo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->admin = User::factory()->create([
            'name' => 'Super Admin',
            'phone' => '01710000001',
            'email' => 'admin@farmlink.com',
        ]);
        $this->admin->assignRole('admin');

        $this->secondAdmin = User::factory()->create([
            'name' => 'Secondary Admin',
            'phone' => '01710000002',
            'email' => 'admin2@farmlink.com',
        ]);
        $this->secondAdmin->assignRole('admin');

        $this->farmer = User::factory()->create([
            'name' => 'Farmer Karim',
            'phone' => '01711111111',
            'email' => 'karim@farmlink.com',
            'district' => 'Satkhira',
            'gender' => 'male',
            'is_active' => true,
        ]);
        $this->farmer->assignRole('farmer');

        $this->vet = User::factory()->create([
            'name' => 'Dr. Rafiq Vet',
            'phone' => '01722222222',
            'email' => 'rafiq.vet@farmlink.com',
            'district' => 'Khulna',
            'gender' => 'male',
            'is_active' => true,
        ]);
        $this->vet->assignRole('veterinary_doctor');

        $this->deo = User::factory()->create([
            'name' => 'DEO Sumon',
            'phone' => '01733333333',
            'email' => 'sumon.deo@farmlink.com',
            'district' => 'Bagerhat',
            'gender' => 'male',
            'is_active' => true,
        ]);
        $this->deo->assignRole('data_entry_operator');
    }

    public function test_non_admin_cannot_access_user_management_endpoints(): void
    {
        // Unauthenticated
        $this->getJson('/api/v1/admin/users')->assertUnauthorized();
        $this->patchJson("/api/v1/admin/users/{$this->farmer->id}", ['name' => 'Hacked'])->assertUnauthorized();
        $this->postJson("/api/v1/admin/users/{$this->farmer->id}/reset-password")->assertUnauthorized();

        // Farmer forbidden
        Sanctum::actingAs($this->farmer);
        $this->getJson('/api/v1/admin/users')->assertForbidden();
        $this->patchJson("/api/v1/admin/users/{$this->vet->id}", ['name' => 'Hacked'])->assertForbidden();
        $this->postJson("/api/v1/admin/users/{$this->vet->id}/reset-password")->assertForbidden();

        // DEO forbidden from admin-only users endpoints
        Sanctum::actingAs($this->deo);
        $this->getJson('/api/v1/admin/users')->assertForbidden();
        $this->patchJson("/api/v1/admin/users/{$this->farmer->id}", ['name' => 'Hacked'])->assertForbidden();
        $this->postJson("/api/v1/admin/users/{$this->farmer->id}/reset-password")->assertForbidden();
    }

    public function test_admin_can_list_users_with_search_and_role_filters(): void
    {
        Sanctum::actingAs($this->admin);

        // 1. List all users
        $response = $this->getJson('/api/v1/admin/users');
        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'name', 'phone', 'email', 'role', 'roles', 'is_active']
                ],
                'meta',
                'summary' => ['total_users', 'total_admins', 'total_farmers', 'total_vets']
            ]);

        // Total users should be 5
        $this->assertEquals(5, $response->json('meta.total'));

        // 2. Filter by role: farmer
        $farmerRes = $this->getJson('/api/v1/admin/users?role=farmer');
        $farmerRes->assertOk();
        $this->assertEquals(1, $farmerRes->json('meta.total'));
        $this->assertEquals('Farmer Karim', $farmerRes->json('data.0.name'));

        // 3. Filter by role: veterinary_doctor
        $vetRes = $this->getJson('/api/v1/admin/users?role=veterinary_doctor');
        $vetRes->assertOk();
        $this->assertEquals(1, $vetRes->json('meta.total'));
        $this->assertEquals('Dr. Rafiq Vet', $vetRes->json('data.0.name'));

        // 4. Search by phone
        $searchRes = $this->getJson('/api/v1/admin/users?search=01733333333');
        $searchRes->assertOk();
        $this->assertEquals(1, $searchRes->json('meta.total'));
        $this->assertEquals('DEO Sumon', $searchRes->json('data.0.name'));
    }

    public function test_admin_can_change_name_email_phone_of_farmer(): void
    {
        Sanctum::actingAs($this->admin);

        $payload = [
            'name' => 'Farmer Karim Updated',
            'phone' => '01711999888',
            'email' => 'karim.updated@farmlink.com',
            'district' => 'Bagerhat',
            'gender' => 'male',
        ];

        $response = $this->patchJson("/api/v1/admin/users/{$this->farmer->id}", $payload);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Farmer Karim Updated')
            ->assertJsonPath('data.phone', '01711999888')
            ->assertJsonPath('data.email', 'karim.updated@farmlink.com')
            ->assertJsonPath('data.district', 'Bagerhat');

        $this->assertDatabaseHas('users', [
            'id' => $this->farmer->id,
            'name' => 'Farmer Karim Updated',
            'phone' => '01711999888',
            'email' => 'karim.updated@farmlink.com',
            'district' => 'Bagerhat',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'update_user',
            'subject_id' => $this->farmer->id,
        ]);
    }

    public function test_admin_can_change_name_email_phone_of_staff_and_practitioners(): void
    {
        Sanctum::actingAs($this->admin);

        $payload = [
            'name' => 'Dr. Rafiq Senior Vet',
            'phone' => '01722999888',
            'email' => 'rafiq.senior@farmlink.com',
            'district' => 'Jessore',
        ];

        $response = $this->patchJson("/api/v1/admin/users/{$this->vet->id}", $payload);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Dr. Rafiq Senior Vet')
            ->assertJsonPath('data.phone', '01722999888')
            ->assertJsonPath('data.email', 'rafiq.senior@farmlink.com');

        $this->assertDatabaseHas('users', [
            'id' => $this->vet->id,
            'name' => 'Dr. Rafiq Senior Vet',
            'phone' => '01722999888',
            'email' => 'rafiq.senior@farmlink.com',
        ]);
    }

    public function test_admin_can_change_name_email_phone_of_another_admin(): void
    {
        Sanctum::actingAs($this->admin);

        $payload = [
            'name' => 'Secondary Admin Renamed',
            'phone' => '01799000002',
            'email' => 'admin2.renamed@farmlink.com',
        ];

        $response = $this->patchJson("/api/v1/admin/users/{$this->secondAdmin->id}", $payload);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Secondary Admin Renamed')
            ->assertJsonPath('data.phone', '01799000002')
            ->assertJsonPath('data.email', 'admin2.renamed@farmlink.com');

        $this->assertDatabaseHas('users', [
            'id' => $this->secondAdmin->id,
            'name' => 'Secondary Admin Renamed',
            'phone' => '01799000002',
            'email' => 'admin2.renamed@farmlink.com',
        ]);
    }

    public function test_admin_can_update_password_directly_in_update_endpoint(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->patchJson("/api/v1/admin/users/{$this->farmer->id}", [
            'password' => 'newpassword123',
        ]);

        $response->assertOk();

        $this->farmer->refresh();
        $this->assertTrue(Hash::check('newpassword123', $this->farmer->password));
    }

    public function test_admin_can_reset_password_with_specified_password_and_revoke_tokens(): void
    {
        Sanctum::actingAs($this->admin);

        // Give the farmer a Sanctum token
        $this->farmer->createToken('farmer_mobile_app');
        $this->assertCount(1, $this->farmer->tokens);

        $response = $this->postJson("/api/v1/admin/users/{$this->farmer->id}/reset-password", [
            'password' => 'resetSecret2026',
            'revoke_tokens' => true,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'user_id' => $this->farmer->id,
                    'new_password' => 'resetSecret2026',
                    'tokens_revoked' => true,
                ]
            ]);

        $this->farmer->refresh();
        $this->assertTrue(Hash::check('resetSecret2026', $this->farmer->password));
        // All active tokens must be revoked
        $this->assertCount(0, $this->farmer->tokens);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'reset_user_password',
            'subject_id' => $this->farmer->id,
        ]);
    }

    public function test_admin_can_reset_password_with_auto_generated_password(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->postJson("/api/v1/admin/users/{$this->vet->id}/reset-password", []);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $plainPassword = $response->json('data.new_password');
        $this->assertNotEmpty($plainPassword);
        $this->assertEquals(10, strlen($plainPassword));

        $this->vet->refresh();
        $this->assertTrue(Hash::check($plainPassword, $this->vet->password));
    }

    public function test_admin_cannot_deactivate_own_account_or_remove_own_admin_role(): void
    {
        Sanctum::actingAs($this->admin);

        // Attempt deactivating self
        $this->patchJson("/api/v1/admin/users/{$this->admin->id}", [
            'is_active' => false,
        ])->assertStatus(422)
            ->assertJsonPath('message', 'You cannot deactivate your own administrative account.');

        // Attempt removing admin role from self
        $this->patchJson("/api/v1/admin/users/{$this->admin->id}", [
            'role' => 'farmer',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'You cannot remove administrative privileges from your own account.');
    }

    public function test_unique_phone_and_email_rules_ignore_target_user(): void
    {
        Sanctum::actingAs($this->admin);

        // Saving same phone & email should succeed without validation errors
        $this->patchJson("/api/v1/admin/users/{$this->farmer->id}", [
            'phone' => '01711111111',
            'email' => 'karim@farmlink.com',
        ])->assertOk();

        // Taking another user's phone should fail with 422
        $this->patchJson("/api/v1/admin/users/{$this->farmer->id}", [
            'phone' => '01722222222', // belonging to $this->vet
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);

        // Taking another user's email should fail with 422
        $this->patchJson("/api/v1/admin/users/{$this->farmer->id}", [
            'email' => 'rafiq.vet@farmlink.com', // belonging to $this->vet
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }
}
