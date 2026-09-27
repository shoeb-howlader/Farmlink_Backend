<?php

namespace Tests\Feature;

use App\Models\AdminNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NotificationConsistencyTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $farmer;
    protected User $vet;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'farmer', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'veterinary_doctor', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'consultant', 'guard_name' => 'web']);

        $this->admin = User::factory()->create(['email' => 'admin@test.com']);
        $this->admin->assignRole('admin');

        $this->farmer = User::factory()->create(['email' => 'farmer@test.com']);
        $this->farmer->assignRole('farmer');

        $this->vet = User::factory()->create(['email' => 'vet@test.com']);
        $this->vet->assignRole('veterinary_doctor');
    }

    public function test_admin_notifications_count_and_list_are_consistent(): void
    {
        // Seed 2 unread notifications for admin (1 system-wide, 1 direct)
        AdminNotification::notify('order', 'Admin Alert 1', 'New order received', ['order_id' => 101], null);
        AdminNotification::notify('stock', 'Admin Alert 2', 'Stock running low', ['product_id' => 5], $this->admin->id);

        // Seed 1 unread notification for farmer (must NOT leak into admin count)
        AdminNotification::notify('order.status_updated', 'Farmer Alert', 'Your order was dispatched', [], $this->farmer->id);

        // 1. Assert count endpoint returns 2
        $countResponse = $this->actingAs($this->admin)->getJson('/api/v1/admin/notifications/unread-count');
        $countResponse->assertOk()
            ->assertJsonPath('data.unread_count', 2);

        // Also check /api/v1/notifications/unread-count
        $userCountResponse = $this->actingAs($this->admin)->getJson('/api/v1/notifications/unread-count');
        $userCountResponse->assertOk()
            ->assertJsonPath('data.unread_count', 2);

        // 2. Assert list endpoint returns 2
        $listResponse = $this->actingAs($this->admin)->getJson('/api/v1/admin/notifications');
        $listResponse->assertOk()
            ->assertJsonPath('data.unread_count', 2)
            ->assertJsonCount(2, 'data.notifications');

        // Check fallback list endpoint /api/v1/notifications for admin
        $userListResponse = $this->actingAs($this->admin)->getJson('/api/v1/notifications');
        $userListResponse->assertOk()
            ->assertJsonPath('data.unread_count', 2)
            ->assertJsonCount(2, 'data.notifications');
    }

    public function test_farmer_notifications_count_and_list_are_consistent(): void
    {
        // Seed 2 unread notifications for farmer
        $n1 = AdminNotification::notify('order.confirmed', 'Farmer Order Confirmed', 'Order #101 confirmed', ['order_id' => 101], $this->farmer->id);
        $n2 = AdminNotification::notify('service_request.assigned', 'Specialist Assigned', 'Vet has been assigned', ['request_id' => 1], $this->farmer->id);

        // Seed 1 admin notification and 1 for vet (must NOT leak into farmer count)
        AdminNotification::notify('order', 'Admin System Alert', 'System wide event', [], null);
        AdminNotification::notify('service_request.assigned_practitioner', 'Vet Assignment', 'You were assigned', [], $this->vet->id);

        // 1. Assert count endpoint returns 2
        $countResponse = $this->actingAs($this->farmer)->getJson('/api/v1/notifications/unread-count');
        $countResponse->assertOk()
            ->assertJsonPath('data.unread_count', 2);

        // 2. Assert list endpoint returns 2 matching notifications
        $listResponse = $this->actingAs($this->farmer)->getJson('/api/v1/notifications');
        $listResponse->assertOk()
            ->assertJsonPath('data.unread_count', 2)
            ->assertJsonCount(2, 'data.notifications')
            ->assertJsonPath('data.notifications.0.title', 'Specialist Assigned')
            ->assertJsonPath('data.notifications.1.title', 'Farmer Order Confirmed');
    }

    public function test_practitioner_notifications_count_and_list_are_consistent(): void
    {
        // Seed 2 unread notifications for practitioner
        AdminNotification::notify('service_request.assigned_practitioner', 'New Request 1', 'Assigned to Pond A', ['sr_id' => 10], $this->vet->id);
        AdminNotification::notify('service_request.assigned_practitioner', 'New Request 2', 'Assigned to Pond B', ['sr_id' => 11], $this->vet->id);

        // Seed other users' notifications
        AdminNotification::notify('order', 'Admin Only', 'Not for vet', [], null);
        AdminNotification::notify('order.status_updated', 'Farmer Only', 'Not for vet', [], $this->farmer->id);

        // 1. Assert count endpoint returns 2
        $countResponse = $this->actingAs($this->vet)->getJson('/api/v1/notifications/unread-count');
        $countResponse->assertOk()
            ->assertJsonPath('data.unread_count', 2);

        // 2. Assert list endpoint returns 2 notifications
        $listResponse = $this->actingAs($this->vet)->getJson('/api/v1/notifications');
        $listResponse->assertOk()
            ->assertJsonPath('data.unread_count', 2)
            ->assertJsonCount(2, 'data.notifications');
    }

    public function test_marking_notifications_as_read_updates_count_and_list(): void
    {
        $n1 = AdminNotification::notify('order.status_updated', 'Order In Transit', 'Order in route', [], $this->farmer->id);
        $n2 = AdminNotification::notify('service_request.assigned', 'Specialist En Route', 'Doctor traveling to farm', [], $this->farmer->id);

        // Mark 1 as read
        $markResponse = $this->actingAs($this->farmer)->patchJson("/api/v1/notifications/{$n1->id}/read");
        $markResponse->assertOk()
            ->assertJsonPath('data.unread_count', 1);

        // Count endpoint must now return 1
        $countResponse = $this->actingAs($this->farmer)->getJson('/api/v1/notifications/unread-count');
        $countResponse->assertOk()
            ->assertJsonPath('data.unread_count', 1);

        // List endpoint unread_count must be 1, total notifications still 2
        $listResponse = $this->actingAs($this->farmer)->getJson('/api/v1/notifications');
        $listResponse->assertOk()
            ->assertJsonPath('data.unread_count', 1)
            ->assertJsonCount(2, 'data.notifications');

        // Mark all read
        $markAll = $this->actingAs($this->farmer)->postJson('/api/v1/notifications/mark-all-read');
        $markAll->assertOk()
            ->assertJsonPath('data.unread_count', 0);

        $finalCount = $this->actingAs($this->farmer)->getJson('/api/v1/notifications/unread-count');
        $finalCount->assertOk()
            ->assertJsonPath('data.unread_count', 0);
    }
}
