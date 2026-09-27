<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Models\VetRecord;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PractitionerPortalTest extends TestCase
{
    use RefreshDatabase;

    protected User $vet;
    protected User $farmer;
    protected Farm $farm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->vet = User::factory()->create([
            'name' => 'Dr. Rafiqul Islam',
            'district' => 'Khulna',
            'is_active' => true,
        ]);
        $this->vet->assignRole('veterinary_doctor');

        $this->farmer = User::factory()->create([
            'name' => 'Abdul Karim',
            'district' => 'Satkhira',
            'is_active' => true,
        ]);
        $this->farmer->assignRole('farmer');

        $this->farm = Farm::factory()->create([
            'user_id' => $this->farmer->id,
            'district' => 'Satkhira',
            'farm_name' => 'Karim Shrimp Farm',
        ]);
    }

    public function test_practitioner_can_retrieve_dashboard_metrics(): void
    {
        // 1 Open assigned request
        ServiceRequest::factory()->create([
            'farmer_id' => $this->farmer->id,
            'farm_id' => $this->farm->id,
            'assigned_to' => $this->vet->id,
            'status' => 'assigned',
            'urgency' => 'urgent',
            'created_at' => now()->subDay(),
        ]);

        // 1 Completed request this month with rating
        ServiceRequest::factory()->create([
            'farmer_id' => $this->farmer->id,
            'farm_id' => $this->farm->id,
            'assigned_to' => $this->vet->id,
            'status' => 'completed',
            'completed_at' => now()->subHours(2),
            'rating' => 5,
            'feedback_note' => 'Outstanding medical advice!',
        ]);

        Sanctum::actingAs($this->vet);

        $response = $this->getJson('/api/v1/my/dashboard');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.metrics.open_assigned_count', 1)
            ->assertJsonPath('data.metrics.fulfilled_this_month_count', 1)
            ->assertJsonPath('data.metrics.average_rating', 5)
            ->assertJsonCount(1, 'data.pending_action_requests');
    }

    public function test_practitioner_can_retrieve_service_history_and_reviews(): void
    {
        // Vet record
        $record = VetRecord::factory()->create([
            'farm_id' => $this->farm->id,
            'vet_id' => $this->vet->id,
            'findings' => 'Bacterial infection detected',
            'treatment' => 'Administered antibiotic treatment',
            'visit_date' => now()->subDays(3)->toDateString(),
        ]);

        // Completed request with review
        ServiceRequest::factory()->create([
            'farmer_id' => $this->farmer->id,
            'farm_id' => $this->farm->id,
            'assigned_to' => $this->vet->id,
            'status' => 'completed',
            'completed_at' => now()->subDays(2),
            'rating' => 5,
            'feedback_note' => 'Great specialist service!',
        ]);

        Sanctum::actingAs($this->vet);

        $response = $this->getJson('/api/v1/my/history');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.records')
            ->assertJsonCount(1, 'data.reviews')
            ->assertJsonPath('data.records.0.findings', 'Bacterial infection detected')
            ->assertJsonPath('data.reviews.0.rating', 5);
    }

    public function test_farmer_cannot_access_practitioner_portal(): void
    {
        Sanctum::actingAs($this->farmer);

        $this->getJson('/api/v1/my/dashboard')->assertStatus(403);
        $this->getJson('/api/v1/my/history')->assertStatus(403);
    }

    public function test_practitioner_receives_sla_info_and_upcoming_follow_ups(): void
    {
        // Create an overdue urgent request assigned 30 hours ago (> 24h SLA)
        ServiceRequest::factory()->create([
            'farmer_id' => $this->farmer->id,
            'farm_id' => $this->farm->id,
            'assigned_to' => $this->vet->id,
            'status' => 'assigned',
            'urgency' => 'urgent',
            'assigned_at' => now()->subHours(30),
            'created_at' => now()->subHours(32),
        ]);

        // Create upcoming follow-ups
        VetRecord::factory()->create([
            'farm_id' => $this->farm->id,
            'vet_id' => $this->vet->id,
            'treatment' => 'Oxygen aeration checkup',
            'next_follow_up' => now()->addDays(2)->toDateString(),
        ]);
        VetRecord::factory()->create([
            'farm_id' => $this->farm->id,
            'vet_id' => $this->vet->id,
            'treatment' => 'Antibiotic course check',
            'next_follow_up' => now()->addDay()->toDateString(),
        ]);

        Sanctum::actingAs($this->vet);

        $response = $this->getJson('/api/v1/my/dashboard');

        $response->assertStatus(200)
            ->assertJsonPath('data.pending_action_requests.0.sla_threshold_hours', 24)
            ->assertJsonPath('data.pending_action_requests.0.is_overdue', true)
            ->assertJsonCount(2, 'data.upcoming_follow_ups')
            // Earliest follow-up first
            ->assertJsonPath('data.upcoming_follow_ups.0.next_follow_up', now()->addDay()->toDateString())
            ->assertJsonPath('data.upcoming_follow_ups.1.next_follow_up', now()->addDays(2)->toDateString());
    }
}
