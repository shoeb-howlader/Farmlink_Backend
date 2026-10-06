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

    public function test_retired_farmer_approval_endpoints_return_404(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/farmer-approvals')
            ->assertStatus(404);

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/farmer-approvals/count')
            ->assertStatus(404);

        $this->actingAs($this->admin, 'sanctum')
            ->patchJson('/api/v1/admin/farmer-approvals/1/approve')
            ->assertStatus(404);

        $this->actingAs($this->admin, 'sanctum')
            ->patchJson('/api/v1/admin/farmer-approvals/1/reject')
            ->assertStatus(404);
    }

    public function test_unverified_farmer_cannot_place_order_or_submit_service_request(): void
    {
        $farmer = User::factory()->unverified()->create([
            'status' => 'pending_verification',
        ]);
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
            ->assertJsonPath('code', 'PHONE_NOT_VERIFIED');

        // Cannot submit service request
        $srRes = $this->actingAs($farmer, 'sanctum')
            ->postJson("/api/v1/farms/{$farm->id}/service-requests", [
                'type' => 'vet',
                'description' => 'Need emergency pond diagnosis',
                'urgency' => 'urgent',
            ]);
        $srRes->assertStatus(403)
            ->assertJsonPath('code', 'PHONE_NOT_VERIFIED');

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

    public function test_otp_verification_immediately_activates_farmer_without_approval_gate(): void
    {
        $farmer = User::factory()->unverified()->create([
            'phone' => '01812345678',
            'phone_otp' => '654321',
            'phone_otp_expires_at' => now()->addMinutes(10),
            'status' => 'pending_verification',
        ]);
        $farmer->syncRoles(['farmer']);

        $response = $this->actingAs($farmer, 'sanctum')
            ->postJson('/api/v1/auth/otp/verify', [
                'phone' => '01812345678',
                'otp' => '654321',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.verified', true);

        $fresh = $farmer->fresh();
        $this->assertEquals('active', $fresh->status);
        $this->assertNotNull($fresh->phone_verified_at);
        $this->assertNull($fresh->phone_otp);

        // Activity log recorded
        $log = ActivityLog::where('action', 'farmer.registered')
            ->where('subject_id', $farmer->id)
            ->first();
        $this->assertNotNull($log);

        // No pending approval notification
        $pendingNotification = AdminNotification::where('type', 'farmer.pending_approval')->first();
        $this->assertNull($pendingNotification);
    }

    public function test_farmer_who_completes_otp_can_immediately_order_and_submit_service_request(): void
    {
        // 1. Self-registering farmer completes OTP
        $farmer = User::factory()->unverified()->create([
            'phone' => '01711223344',
            'phone_otp' => '112233',
            'phone_otp_expires_at' => now()->addMinutes(10),
            'status' => 'pending_verification',
        ]);
        $farmer->syncRoles(['farmer']);

        $verifyRes = $this->actingAs($farmer, 'sanctum')
            ->postJson('/api/v1/auth/otp/verify', [
                'phone' => '01711223344',
                'otp' => '112233',
            ]);
        $verifyRes->assertOk();

        // 2. Immediately create farm
        $farmRes = $this->actingAs($farmer, 'sanctum')
            ->postJson('/api/v1/farms', [
                'farm_name' => 'Active Shrimp Farm',
                'farm_type' => 'Own',
                'total_area' => 10.0,
                'pond_count' => 3,
                'cultivation_area' => 8.0,
                'district' => 'Satkhira',
                'upazila' => 'Debhata',
                'main_culture_type' => 'Bagda',
                'farming_system' => 'Semi-Intensive',
            ]);
        $farmRes->assertStatus(201);
        $farmId = $farmRes->json('data.id');

        // 3. Immediately place an order - NO admin approval needed!
        $product = Product::factory()->create(['price' => 250, 'stock' => 50]);
        $orderRes = $this->actingAs($farmer, 'sanctum')
            ->postJson('/api/v1/orders', [
                'shipping_address' => 'Debhata, Satkhira',
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 2],
                ],
            ]);
        $orderRes->assertStatus(201)
            ->assertJsonPath('data.status', 'pending');

        // 4. Immediately submit a service request - NO admin approval needed!
        $srRes = $this->actingAs($farmer, 'sanctum')
            ->postJson("/api/v1/farms/{$farmId}/service-requests", [
                'type' => 'vet',
                'description' => 'Water quality test and pathogen screening',
                'urgency' => 'normal',
            ]);
        $srRes->assertStatus(201)
            ->assertJsonPath('data.status', 'pending');
    }

    public function test_newly_verified_farmer_appears_in_admin_farmers_directory(): void
    {
        $farmer = User::factory()->create([
            'name' => 'Newly Registered Farmer',
            'phone' => '01799887766',
            'status' => 'active',
            'phone_verified_at' => now(),
        ]);
        $farmer->syncRoles(['farmer']);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/farmers');

        $response->assertOk();
        $farmerNames = collect($response->json('data'))->pluck('name');
        $this->assertTrue($farmerNames->contains('Newly Registered Farmer'));
    }

    public function test_deo_assisted_farmer_registration_is_immediately_active(): void
    {
        $response = $this->actingAs($this->deo, 'sanctum')
            ->postJson('/api/v1/admin/farmers', [
                'name' => 'In-Person Farmer Kamal',
                'phone' => '01511223344',
                'district' => 'Bagerhat',
                'gender' => 'male',
            ]);

        $response->assertStatus(201);
        $newFarmerId = $response->json('data.id');

        $newFarmer = User::find($newFarmerId);
        $this->assertEquals('active', $newFarmer->status);
        $this->assertNotNull($newFarmer->phone_verified_at);
        $this->assertTrue($newFarmer->is_active);
    }
}
