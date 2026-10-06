<?php

namespace Tests\Feature;

use App\Models\CannedResponse;
use App\Models\User;
use Database\Seeders\CannedResponseSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CannedResponseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(CannedResponseSeeder::class);
    }

    public function test_admin_receives_admin_scoped_canned_responses(): void
    {
        $admin = User::factory()->create(['name' => 'Admin User']);
        $admin->assignRole('admin');

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/chat/canned-responses');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $shortcuts = collect($response->json('data'))->pluck('shortcut')->all();
        $this->assertContains('/greeting', $shortcuts);
        $this->assertContains('/hours', $shortcuts);
        $this->assertNotContains('/medication-mix', $shortcuts);
    }

    public function test_practitioner_receives_practitioner_scoped_canned_responses(): void
    {
        $vet = User::factory()->create(['name' => 'Dr. Rahman']);
        $vet->assignRole('veterinary_doctor');

        Sanctum::actingAs($vet);

        $response = $this->getJson('/api/v1/chat/canned-responses');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $shortcuts = collect($response->json('data'))->pluck('shortcut')->all();
        $this->assertContains('/water-check', $shortcuts);
        $this->assertContains('/medication-mix', $shortcuts);
        $this->assertNotContains('/order-status', $shortcuts);
    }

    public function test_admin_can_create_and_update_canned_response(): void
    {
        $admin = User::factory()->create(['name' => 'Admin User']);
        $admin->assignRole('admin');

        Sanctum::actingAs($admin);

        $createRes = $this->postJson('/api/v1/admin/canned-responses', [
            'scope' => 'admin',
            'shortcut' => 'refund',
            'title' => 'Refund Policy',
            'content' => 'Refunds are processed within 3-5 business days upon item return.',
        ]);

        $createRes->assertStatus(201)
            ->assertJsonPath('data.shortcut', '/refund');

        $cannedId = $createRes->json('data.id');

        $updateRes = $this->patchJson("/api/v1/admin/canned-responses/{$cannedId}", [
            'title' => 'Updated Refund Policy',
        ]);

        $updateRes->assertStatus(200)
            ->assertJsonPath('data.title', 'Updated Refund Policy');

        $this->assertDatabaseHas('canned_responses', [
            'id' => $cannedId,
            'title' => 'Updated Refund Policy',
        ]);
    }
}
