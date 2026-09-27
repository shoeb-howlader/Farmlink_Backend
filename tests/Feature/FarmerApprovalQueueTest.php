<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\AdminNotification;
use App\Models\Farm;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FarmerApprovalQueueTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $deo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->syncRoles(['admin']);

        $this->deo = User::factory()->create();
        $this->deo->syncRoles(['data_entry_operator']);
    }

    public function test_non_admin_cannot_access_approval_queue(): void
    {
        $farmer = User::factory()->create();
        $farmer->syncRoles(['farmer']);

        $this->actingAs($farmer, 'sanctum')
            ->getJson('/api/v1/admin/farmer-approvals')
            ->assertStatus(403);

        $this->actingAs($farmer, 'sanctum')
            ->patchJson("/api/v1/admin/farmer-approvals/{$farmer->id}/approve")
            ->assertStatus(403);
    }

    public function test_admin_can_list_pending_farmer_approvals(): void
    {
        $pending1 = User::factory()->pendingApproval()->create(['name' => 'Pending Rahim']);
        $pending1->syncRoles(['farmer']);

        $pending2 = User::factory()->pendingApproval()->create(['name' => 'Pending Karim']);
        $pending2->syncRoles(['farmer']);

        $activeFarmer = User::factory()->create(['name' => 'Active Salam', 'status' => 'active']);
        $activeFarmer->syncRoles(['farmer']);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/farmer-approvals');

        $response->assertOk()
            ->assertJsonPath('data.pending_count', 2)
            ->assertJsonCount(2, 'data.farmers');
    }

    public function test_admin_can_get_pending_approvals_count(): void
    {
        $pending = User::factory(3)->pendingApproval()->create();
        foreach ($pending as $p) {
            $p->syncRoles(['farmer']);
        }

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/farmer-approvals/count');

        $response->assertOk()
            ->assertJsonPath('data.pending_count', 3);
    }

    public function test_admin_can_approve_farmer(): void
    {
        $farmer = User::factory()->pendingApproval()->create([
            'name' => 'Jashim Farmer',
            'phone' => '01812345678',
        ]);
        $farmer->syncRoles(['farmer']);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/v1/admin/farmer-approvals/{$farmer->id}/approve");

        $response->assertOk()
            ->assertJsonPath('data.farmer.status', 'active');

        $fresh = $farmer->fresh();
        $this->assertEquals('active', $fresh->status);
        $this->assertTrue($fresh->is_active);
        $this->assertNotNull($fresh->approved_at);
        $this->assertEquals($this->admin->id, $fresh->approved_by);

        // Check ActivityLog entry
        $log = ActivityLog::where('action', 'farmer.approved')
            ->where('user_id', $this->admin->id)
            ->first();
        $this->assertNotNull($log);
        $this->assertEquals($farmer->id, $log->changes['farmer_id']);

        // Check notification dispatched to farmer
        $notification = AdminNotification::where('user_id', $farmer->id)
            ->where('type', 'farmer.approved')
            ->first();
        $this->assertNotNull($notification);
    }

    public function test_admin_can_reject_farmer_with_reason(): void
    {
        $farmer = User::factory()->pendingApproval()->create([
            'name' => 'Rejected Farmer',
            'phone' => '01899999999',
        ]);
        $farmer->syncRoles(['farmer']);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/v1/admin/farmer-approvals/{$farmer->id}/reject", [
                'reason' => 'Invalid pond coordinates provided.',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.farmer.status', 'rejected');

        $fresh = $farmer->fresh();
        $this->assertEquals('rejected', $fresh->status);
        $this->assertFalse($fresh->is_active);
        $this->assertEquals('Invalid pond coordinates provided.', $fresh->rejection_reason);
        $this->assertNotNull($fresh->rejected_at);
        $this->assertEquals($this->admin->id, $fresh->rejected_by);

        // Check ActivityLog
        $log = ActivityLog::where('action', 'farmer.rejected')
            ->where('user_id', $this->admin->id)
            ->first();
        $this->assertNotNull($log);
        $this->assertEquals('Invalid pond coordinates provided.', $log->changes['reason']);

        // Check notification to farmer
        $notification = AdminNotification::where('user_id', $farmer->id)
            ->where('type', 'farmer.rejected')
            ->first();
        $this->assertNotNull($notification);
    }

    public function test_pending_approval_farmer_cannot_place_order_or_submit_service_request(): void
    {
        $farmer = User::factory()->pendingApproval()->create();
        $farmer->syncRoles(['farmer']);

        $farm = Farm::factory()->create(['user_id' => $farmer->id]);
        $product = Product::factory()->create(['price' => 500, 'stock' => 20]);

        // Cannot place order
        $orderRes = $this->actingAs($farmer, 'sanctum')
            ->postJson('/api/v1/orders', [
                'shipping_address' => 'Village 1',
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
            ]);
        $orderRes->assertStatus(403)
            ->assertJsonPath('code', 'FARMER_PENDING_APPROVAL');

        // Cannot submit service request
        $srRes = $this->actingAs($farmer, 'sanctum')
            ->postJson("/api/v1/farms/{$farm->id}/service-requests", [
                'type' => 'vet_visit',
                'description' => 'Need emergency pond diagnosis',
                'urgency' => 'urgent',
            ]);
        $srRes->assertStatus(403)
            ->assertJsonPath('code', 'FARMER_PENDING_APPROVAL');

        // But CAN register farm
        $farmRes = $this->actingAs($farmer, 'sanctum')
            ->postJson('/api/v1/farms', [
                'farm_name' => 'Pending Farmer Farm',
                'farm_type' => 'Own',
                'total_area' => 5.0,
                'pond_count' => 2,
                'cultivation_area' => 4.0,
                'district' => 'Bagerhat',
                'upazila' => 'Rampal',
                'main_culture_type' => 'Bagda',
                'farming_system' => 'Extensive',
            ]);
        $farmRes->assertStatus(201);
    }
}
