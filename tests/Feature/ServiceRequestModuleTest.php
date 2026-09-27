<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ServiceRequestModuleTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $farmer;
    protected User $otherFarmer;
    protected User $vet;
    protected User $consultant;
    protected Farm $farm;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);

        $this->admin = User::factory()->create(['gender' => 'female']);
        $this->admin->assignRole('admin');

        $this->farmer = User::factory()->create(['gender' => 'male']);
        $this->farmer->assignRole('farmer');

        $this->otherFarmer = User::factory()->create(['gender' => 'female']);
        $this->otherFarmer->assignRole('farmer');

        $this->vet = User::factory()->create(['gender' => 'male', 'district' => 'Khulna']);
        $this->vet->assignRole('veterinary_doctor');

        $this->consultant = User::factory()->create(['gender' => 'female', 'district' => 'Cox\'s Bazar']);
        $this->consultant->assignRole('consultant');

        $this->farm = Farm::factory()->create([
            'user_id' => $this->farmer->id,
            'district' => 'Khulna',
        ]);
    }

    public function test_registration_requires_gender(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'name' => 'John Doe',
            'email' => 'johndoe@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => '01711111111',
            // gender omitted
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['gender']);

        // With valid gender
        $successResponse = $this->postJson('/api/v1/register', [
            'name' => 'John Doe',
            'email' => 'johndoe@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => '01711111111',
            'gender' => 'male',
        ]);

        $successResponse->assertStatus(201);
        $this->assertDatabaseHas('users', [
            'email' => 'johndoe@example.com',
            'gender' => 'male',
        ]);
    }

    public function test_admin_creating_farmer_requires_gender(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/admin/farmers', [
            'name' => 'New Farmer',
            'phone' => '01822222222',
            // gender omitted
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['gender']);
    }

    public function test_admin_creating_staff_requires_gender(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/admin/staff', [
            'name' => 'New Staff',
            'email' => 'staff@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'veterinary_doctor',
            // gender omitted
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['gender']);
    }

    public function test_farmer_can_submit_service_request(): void
    {
        $response = $this->actingAs($this->farmer, 'sanctum')->postJson("/api/v1/farms/{$this->farm->id}/service-requests", [
            'type' => 'vet',
            'description' => 'Shrimp are showing signs of black gill disease.',
            'urgency' => 'urgent',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.type', 'vet')
            ->assertJsonPath('data.urgency', 'urgent')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.farm_id', $this->farm->id)
            ->assertJsonPath('data.farmer_id', $this->farmer->id);

        $this->assertDatabaseHas('service_requests', [
            'farm_id' => $this->farm->id,
            'farmer_id' => $this->farmer->id,
            'type' => 'vet',
            'urgency' => 'urgent',
            'status' => 'pending',
        ]);
    }

    public function test_farmer_cannot_submit_service_request_for_another_farmers_farm(): void
    {
        $response = $this->actingAs($this->otherFarmer, 'sanctum')->postJson("/api/v1/farms/{$this->farm->id}/service-requests", [
            'type' => 'vet',
            'description' => 'Shrimp are lethargic.',
            'urgency' => 'normal',
        ]);

        $response->assertStatus(403);
    }

    public function test_admin_can_view_service_requests_with_urgent_first(): void
    {
        $normalReq = ServiceRequest::factory()->create([
            'farm_id' => $this->farm->id,
            'farmer_id' => $this->farmer->id,
            'urgency' => 'normal',
            'created_at' => now()->subDays(2),
        ]);

        $urgentReq = ServiceRequest::factory()->create([
            'farm_id' => $this->farm->id,
            'farmer_id' => $this->farmer->id,
            'urgency' => 'urgent',
            'created_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')->getJson('/api/v1/admin/service-requests');

        $response->assertStatus(200);
        $data = $response->json('data');

        // Urgent request should appear before normal request
        $this->assertEquals($urgentReq->id, $data[0]['id']);
        $this->assertEquals($normalReq->id, $data[1]['id']);
    }

    public function test_admin_can_assign_service_request_to_practitioner(): void
    {
        $serviceRequest = ServiceRequest::factory()->create([
            'farm_id' => $this->farm->id,
            'farmer_id' => $this->farmer->id,
            'type' => 'vet',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')->patchJson("/api/v1/admin/service-requests/{$serviceRequest->id}/assign", [
            'practitioner_id' => $this->vet->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'assigned')
            ->assertJsonPath('data.assigned_to', $this->vet->id)
            ->assertJsonPath('data.assigned_practitioner.id', $this->vet->id);

        $this->assertDatabaseHas('service_requests', [
            'id' => $serviceRequest->id,
            'assigned_to' => $this->vet->id,
            'status' => 'assigned',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'service_request.assigned',
            'subject_id' => $serviceRequest->id,
        ]);
    }

    public function test_practitioner_can_view_assigned_requests(): void
    {
        $serviceRequest = ServiceRequest::factory()->create([
            'farm_id' => $this->farm->id,
            'farmer_id' => $this->farmer->id,
            'type' => 'vet',
            'status' => 'assigned',
            'assigned_to' => $this->vet->id,
        ]);

        $response = $this->actingAs($this->vet, 'sanctum')->getJson('/api/v1/my/service-requests');

        $response->assertStatus(200)
            ->assertJsonPath('data.data.0.id', $serviceRequest->id);
    }

    public function test_logging_visit_record_completes_service_request_in_same_transaction(): void
    {
        $serviceRequest = ServiceRequest::factory()->create([
            'farm_id' => $this->farm->id,
            'farmer_id' => $this->farmer->id,
            'type' => 'vet',
            'status' => 'assigned',
            'assigned_to' => $this->vet->id,
        ]);

        $response = $this->actingAs($this->vet, 'sanctum')->postJson("/api/v1/farms/{$this->farm->id}/vet-records", [
            'visit_date' => now()->toDateString(),
            'findings' => 'Mild vibriosis detected.',
            'treatment' => 'Applied probiotic treatment and 20% water exchange.',
            'medicine_given' => 'AquaPro-B 500g',
            'service_request_id' => $serviceRequest->id,
        ]);

        $response->assertStatus(201);

        $serviceRequest->refresh();
        $this->assertEquals('completed', $serviceRequest->status);
        $this->assertNotNull($serviceRequest->completed_at);
        $this->assertNotNull($serviceRequest->fulfilled_record_id);
        $this->assertEquals(\App\Models\VetRecord::class, $serviceRequest->fulfilled_record_type);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'service_request.completed',
            'subject_id' => $serviceRequest->id,
        ]);
    }

    public function test_farmer_can_rate_and_provide_feedback_on_completed_request(): void
    {
        $serviceRequest = ServiceRequest::factory()->create([
            'farm_id' => $this->farm->id,
            'farmer_id' => $this->farmer->id,
            'type' => 'vet',
            'status' => 'completed',
            'completed_at' => now(),
            'assigned_to' => $this->vet->id,
        ]);

        $response = $this->actingAs($this->farmer, 'sanctum')->postJson("/api/v1/service-requests/{$serviceRequest->id}/feedback", [
            'rating' => 5,
            'feedback_note' => 'Excellent and timely veterinary visit!',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.rating', 5)
            ->assertJsonPath('data.feedback_note', 'Excellent and timely veterinary visit!');

        $serviceRequest->refresh();
        $this->assertEquals(5, $serviceRequest->rating);
        $this->assertEquals('Excellent and timely veterinary visit!', $serviceRequest->feedback_note);
    }

    public function test_farmer_cannot_rate_uncompleted_service_request(): void
    {
        $serviceRequest = ServiceRequest::factory()->create([
            'farm_id' => $this->farm->id,
            'farmer_id' => $this->farmer->id,
            'type' => 'vet',
            'status' => 'assigned',
            'assigned_to' => $this->vet->id,
        ]);

        $response = $this->actingAs($this->farmer, 'sanctum')->postJson("/api/v1/service-requests/{$serviceRequest->id}/feedback", [
            'rating' => 5,
        ]);

        $response->assertStatus(422);
    }

    public function test_practitioner_in_different_district_than_farm_appears_in_assignable_list_ranked_lower(): void
    {
        // Vet 1 in Satkhira
        $vetSatkhira = User::factory()->create([
            'name' => 'Dr. Satkhira Vet',
            'district' => 'Satkhira',
            'is_active' => true,
        ]);
        $vetSatkhira->assignRole('veterinary_doctor');

        // Vet 2 in Khulna
        $vetKhulna = User::factory()->create([
            'name' => 'Dr. Khulna Vet',
            'district' => 'Khulna',
            'is_active' => true,
        ]);
        $vetKhulna->assignRole('veterinary_doctor');

        // Admin queries practitioners for a farm in Satkhira
        $response = $this->actingAs($this->admin, 'sanctum')->getJson('/api/v1/admin/service-requests/practitioners?type=vet&district=Satkhira');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $practitioners = $response->json('data');
        $ids = array_column($practitioners, 'id');

        // Both vets must appear in the list (district is NOT a filter)
        $this->assertContains($vetSatkhira->id, $ids);
        $this->assertContains($vetKhulna->id, $ids);

        // Satkhira practitioner must rank before Khulna practitioner
        $satkhiraIndex = array_search($vetSatkhira->id, $ids);
        $khulnaIndex = array_search($vetKhulna->id, $ids);

        $this->assertLessThan($khulnaIndex, $satkhiraIndex, 'Practitioner in the same district must rank higher than practitioner in a different district');
    }

    public function test_assigning_service_request_dispatches_notification_to_practitioner(): void
    {
        $serviceRequest = ServiceRequest::factory()->create([
            'farm_id' => $this->farm->id,
            'farmer_id' => $this->farmer->id,
            'type' => 'vet',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')->patchJson("/api/v1/admin/service-requests/{$serviceRequest->id}/assign", [
            'practitioner_id' => $this->vet->id,
        ]);

        $response->assertStatus(200);

        // Verify notification for assigned practitioner
        $this->assertDatabaseHas('admin_notifications', [
            'user_id' => $this->vet->id,
            'type' => 'service_request.assigned_practitioner',
        ]);

        // Verify notification for farmer
        $this->assertDatabaseHas('admin_notifications', [
            'user_id' => $this->farmer->id,
            'type' => 'service_request.assigned',
        ]);
    }
}

