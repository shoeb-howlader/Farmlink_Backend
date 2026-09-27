<?php

namespace Tests\Feature;

use App\Models\AdminNotification;
use App\Models\Farm;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Models\VetRecord;
use App\Models\ConsultantRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ServiceRequestsFixAndNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $farmer;
    protected User $vet;
    protected User $consultant;
    protected Farm $farm;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'farmer', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'veterinary_doctor', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'consultant', 'guard_name' => 'web']);

        $this->admin = User::factory()->create(['email' => 'admin@test.com', 'gender' => 'female']);
        $this->admin->assignRole('admin');

        $this->farmer = User::factory()->create(['email' => 'farmer@test.com', 'gender' => 'male']);
        $this->farmer->assignRole('farmer');

        $this->vet = User::factory()->create(['name' => 'Dr. Rafiqul Islam', 'email' => 'vet@test.com', 'gender' => 'male']);
        $this->vet->assignRole('veterinary_doctor');

        $this->consultant = User::factory()->create(['name' => 'Dr. Nasreen Sultana', 'email' => 'consultant@test.com', 'gender' => 'female']);
        $this->consultant->assignRole('consultant');

        $this->farm = Farm::factory()->create([
            'user_id' => $this->farmer->id,
            'farm_name' => 'Padma Aqua Farm',
            'district' => 'Khulna',
        ]);
    }

    public function test_admin_service_requests_returns_all_statuses_even_with_district_all(): void
    {
        // 1. Create 3 service requests in different statuses
        $sr1 = ServiceRequest::create([
            'farm_id' => $this->farm->id,
            'farmer_id' => $this->farmer->id,
            'type' => 'vet',
            'urgency' => 'urgent',
            'status' => 'pending',
            'description' => 'Pending urgent vet request',
        ]);
        $sr1->created_at = now()->subHours(30);
        $sr1->save();

        $sr2 = ServiceRequest::create([
            'farm_id' => $this->farm->id,
            'farmer_id' => $this->farmer->id,
            'type' => 'consultant',
            'urgency' => 'normal',
            'status' => 'assigned',
            'assigned_to' => $this->consultant->id,
            'assigned_at' => now()->subHours(5),
            'description' => 'Assigned normal consultant request',
        ]);
        $sr2->created_at = now()->subHours(10);
        $sr2->save();

        $sr3 = ServiceRequest::create([
            'farm_id' => $this->farm->id,
            'farmer_id' => $this->farmer->id,
            'type' => 'vet',
            'urgency' => 'normal',
            'status' => 'completed',
            'assigned_to' => $this->vet->id,
            'assigned_at' => now()->subDays(2),
            'completed_at' => now()->subDay(),
            'description' => 'Completed vet request',
        ]);
        $sr3->created_at = now()->subDays(3);
        $sr3->save();

        // GET /api/v1/admin/service-requests without query params
        $response = $this->actingAs($this->admin)->getJson('/api/v1/admin/service-requests');
        $response->assertOk()
            ->assertJsonPath('meta.total', 3);

        // GET /api/v1/admin/service-requests with district=all (which previously caused 0 results)
        $responseWithAll = $this->actingAs($this->admin)->getJson('/api/v1/admin/service-requests?district=all');
        $responseWithAll->assertOk()
            ->assertJsonPath('meta.total', 3);

        // Verify overdue calculation on pending urgent request (> 24h)
        $items = $response->json('data');
        $pendingItem = collect($items)->firstWhere('id', $sr1->id);
        $this->assertTrue($pendingItem['is_overdue']);

        // Verify normal assigned request is not overdue (< 72h)
        $assignedItem = collect($items)->firstWhere('id', $sr2->id);
        $this->assertFalse($assignedItem['is_overdue']);
    }

    public function test_metrics_guards_against_negative_turnaround_and_logs_warning(): void
    {
        Log::spy();

        // Create corrupt record with completed_at < created_at
        $srCorrupt = ServiceRequest::create([
            'farm_id' => $this->farm->id,
            'farmer_id' => $this->farmer->id,
            'type' => 'vet',
            'urgency' => 'normal',
            'status' => 'completed',
            'assigned_to' => $this->vet->id,
            'assigned_at' => now()->subDays(3),
            'completed_at' => now()->subDays(2),
            'description' => 'Corrupt turnaround test',
        ]);
        $srCorrupt->created_at = now();
        $srCorrupt->save();

        // Create valid record
        $srValid = ServiceRequest::create([
            'farm_id' => $this->farm->id,
            'farmer_id' => $this->farmer->id,
            'type' => 'vet',
            'urgency' => 'normal',
            'status' => 'completed',
            'assigned_to' => $this->vet->id,
            'assigned_at' => now()->subHours(20),
            'completed_at' => now()->subHours(10),
            'description' => 'Valid turnaround test',
        ]);
        $srValid->created_at = now()->subHours(24);
        $srValid->save();

        $response = $this->actingAs($this->admin)->getJson('/api/v1/admin/service-requests/metrics');
        $response->assertOk();

        Log::shouldHaveReceived('warning')
            ->atLeast()->once();

        $avgHours = $response->json('data.avg_turnaround_hours');
        $this->assertGreaterThanOrEqual(0, $avgHours);
        $this->assertEquals(14, $avgHours);
    }

    public function test_admin_practitioner_profile_returns_details_and_history(): void
    {
        // Create a vet record
        $record = VetRecord::create([
            'farm_id' => $this->farm->id,
            'vet_id' => $this->vet->id,
            'visit_date' => now()->subDay()->toDateString(),
            'findings' => 'Water quality issue',
            'treatment' => 'Oxygen tablets',
        ]);

        // Create an assigned request
        $sr = ServiceRequest::create([
            'farm_id' => $this->farm->id,
            'farmer_id' => $this->farmer->id,
            'type' => 'vet',
            'urgency' => 'normal',
            'status' => 'assigned',
            'assigned_to' => $this->vet->id,
            'assigned_at' => now(),
            'description' => 'Needs inspection',
        ]);

        $response = $this->actingAs($this->admin)->getJson("/api/v1/admin/practitioners/{$this->vet->id}");
        $response->assertOk()
            ->assertJsonPath('data.id', $this->vet->id)
            ->assertJsonPath('data.name', 'Dr. Rafiqul Islam')
            ->assertJsonPath('data.stats.total_records_logged', 1)
            ->assertJsonPath('data.stats.open_assignments', 1);

        $this->assertNotEmpty($response->json('data.records'));
        $this->assertEquals('Padma Aqua Farm', $response->json('data.records.0.farm.farm_name'));
    }

    public function test_farmer_receives_notifications_on_order_and_service_updates(): void
    {
        $product = Product::factory()->create(['price' => 100, 'stock' => 50]);

        $order = Order::create([
            'user_id' => $this->farmer->id,
            'total_amount' => 200,
            'status' => 'pending',
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'price_at_purchase' => 100,
        ]);

        // 1. Admin confirms order -> farmer should receive notification
        $this->actingAs($this->admin)->patchJson("/api/v1/admin/orders/{$order->id}/status", [
            'status' => 'confirmed',
        ])->assertOk();

        // Check farmer notifications
        $farmerNotifs = $this->actingAs($this->farmer)->getJson('/api/v1/notifications');
        $farmerNotifs->assertOk()
            ->assertJsonPath('data.unread_count', 1);

        $notifId = $farmerNotifs->json('data.notifications.0.id');
        $this->assertStringContainsString("Order #{$order->id}", $farmerNotifs->json('data.notifications.0.title'));

        // 2. Farmer marks notification as read
        $this->actingAs($this->farmer)->patchJson("/api/v1/notifications/{$notifId}/read")
            ->assertOk();

        $farmerNotifsAfter = $this->actingAs($this->farmer)->getJson('/api/v1/notifications');
        $this->assertEquals(0, $farmerNotifsAfter->json('data.unread_count'));

        // 3. Admin assigns service request -> farmer receives notification
        $sr = ServiceRequest::create([
            'farm_id' => $this->farm->id,
            'farmer_id' => $this->farmer->id,
            'type' => 'vet',
            'urgency' => 'normal',
            'status' => 'pending',
            'description' => 'Check ponds',
        ]);

        $this->actingAs($this->admin)->patchJson("/api/v1/admin/service-requests/{$sr->id}/assign", [
            'practitioner_id' => $this->vet->id,
        ])->assertOk();

        $farmerNotifsSr = $this->actingAs($this->farmer)->getJson('/api/v1/notifications');
        $this->assertEquals(1, $farmerNotifsSr->json('data.unread_count'));
        $this->assertStringContainsString("Practitioner Assigned", $farmerNotifsSr->json('data.notifications.0.title'));
    }

    public function test_farmer_feedback_submission_and_immutability(): void
    {
        // Completed service request
        $sr = ServiceRequest::create([
            'farm_id' => $this->farm->id,
            'farmer_id' => $this->farmer->id,
            'type' => 'vet',
            'urgency' => 'normal',
            'status' => 'completed',
            'assigned_to' => $this->vet->id,
            'assigned_at' => now()->subDay(),
            'completed_at' => now()->subHours(2),
            'description' => 'Completed inspection',
        ]);

        // 1. Submit feedback successfully
        $response = $this->actingAs($this->farmer)->postJson("/api/v1/service-requests/{$sr->id}/feedback", [
            'rating' => 5,
            'feedback_note' => 'Excellent service and clear advice!',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.rating', 5)
            ->assertJsonPath('data.feedback_note', 'Excellent service and clear advice!');

        $sr->refresh();
        $this->assertEquals(5, $sr->rating);
        $this->assertEquals('Excellent service and clear advice!', $sr->feedback_note);

        // 2. Attempting to submit feedback again must fail with 422
        $duplicateResponse = $this->actingAs($this->farmer)->postJson("/api/v1/service-requests/{$sr->id}/feedback", [
            'rating' => 4,
            'feedback_note' => 'Trying to change rating',
        ]);

        $duplicateResponse->assertStatus(422)
            ->assertJsonPath('message', 'This service request has already been rated and cannot be re-rated.');

        // 3. Verify practitioner profile returns this review with farmer and farm details
        $practitionerResponse = $this->actingAs($this->admin)->getJson("/api/v1/admin/practitioners/{$this->vet->id}");
        $practitionerResponse->assertOk()
            ->assertJsonPath('data.stats.total_ratings', 1)
            ->assertJsonPath('data.stats.average_rating', 5)
            ->assertJsonCount(1, 'data.reviews')
            ->assertJsonPath('data.reviews.0.rating', 5)
            ->assertJsonPath('data.reviews.0.feedback_note', 'Excellent service and clear advice!')
            ->assertJsonPath('data.reviews.0.farmer.name', $this->farmer->name)
            ->assertJsonPath('data.reviews.0.farm.farm_name', 'Padma Aqua Farm');
    }
}
