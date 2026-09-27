<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TopFarmersReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'farmer', 'guard_name' => 'web']);
    }

    public function test_top_farmers_sorted_strictly_by_spend_descending_and_excludes_zero_spend_farmers(): void
    {
        $admin = User::factory()->create();
        $admin->syncRoles(['admin']);

        // Farmer 1: Top Spender - 62,850 total spend across 2 orders
        $farmerTop = User::factory()->create(['name' => 'Top Spender Farmer']);
        $farmerTop->syncRoles(['farmer']);
        Order::factory()->create([
            'user_id' => $farmerTop->id,
            'total' => 40000.00,
            'status' => 'delivered',
            'created_at' => now(),
        ]);
        Order::factory()->create([
            'user_id' => $farmerTop->id,
            'total' => 22850.00,
            'status' => 'confirmed',
            'created_at' => now(),
        ]);

        // Farmer 2: Mid Spender - 15,000 spend in 1 order
        $farmerMid = User::factory()->create(['name' => 'Mid Spender Farmer']);
        $farmerMid->syncRoles(['farmer']);
        Order::factory()->create([
            'user_id' => $farmerMid->id,
            'total' => 15000.00,
            'status' => 'dispatched',
            'created_at' => now(),
        ]);

        // Farmer 3: Low Spender - 2,000 spend in 1 order
        $farmerLow = User::factory()->create(['name' => 'Low Spender Farmer']);
        $farmerLow->syncRoles(['farmer']);
        Order::factory()->create([
            'user_id' => $farmerLow->id,
            'total' => 2000.00,
            'status' => 'pending',
            'created_at' => now(),
        ]);

        // Farmer 4: Inactive Farmer - 0 orders (0 spend)
        $farmerZero = User::factory()->create(['name' => 'Zero Orders Farmer']);
        $farmerZero->syncRoles(['farmer']);

        // Farmer 5: Cancelled Orders Only - 5,000 cancelled order
        $farmerCancelled = User::factory()->create(['name' => 'Cancelled Orders Farmer']);
        $farmerCancelled->syncRoles(['farmer']);
        Order::factory()->create([
            'user_id' => $farmerCancelled->id,
            'total' => 5000.00,
            'status' => 'cancelled',
            'created_at' => now(),
        ]);

        // Farmer 6: Past Month Order - 80,000 spend but 2 months ago
        $farmerPast = User::factory()->create(['name' => 'Past Month Farmer']);
        $farmerPast->syncRoles(['farmer']);
        Order::factory()->create([
            'user_id' => $farmerPast->id,
            'total' => 80000.00,
            'status' => 'delivered',
            'created_at' => now()->subMonths(2),
        ]);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/reports/top-farmers');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $data = $response->json('data');

        // Must strictly only include farmers with active spend this month
        $this->assertCount(3, $data);

        // Rank #1: Top Spender Farmer (62,850.00, 2 orders)
        $this->assertEquals($farmerTop->id, $data[0]['id']);
        $this->assertEquals('Top Spender Farmer', $data[0]['name']);
        $this->assertEquals(62850.0, $data[0]['total_spent']);
        $this->assertEquals(2, $data[0]['orders_count']);

        // Rank #2: Mid Spender Farmer (15,000.00, 1 order)
        $this->assertEquals($farmerMid->id, $data[1]['id']);
        $this->assertEquals('Mid Spender Farmer', $data[1]['name']);
        $this->assertEquals(15000.0, $data[1]['total_spent']);
        $this->assertEquals(1, $data[1]['orders_count']);

        // Rank #3: Low Spender Farmer (2,000.00, 1 order)
        $this->assertEquals($farmerLow->id, $data[2]['id']);
        $this->assertEquals('Low Spender Farmer', $data[2]['name']);
        $this->assertEquals(2000.0, $data[2]['total_spent']);
        $this->assertEquals(1, $data[2]['orders_count']);

        // Verify inactive, cancelled-only, and past-month farmers are NOT in the active this-month top leaderboard
        $returnedIds = collect($data)->pluck('id')->all();
        $this->assertNotContains($farmerZero->id, $returnedIds);
        $this->assertNotContains($farmerCancelled->id, $returnedIds);
        $this->assertNotContains($farmerPast->id, $returnedIds);

        // Verify strict descending spend order
        $this->assertTrue($data[0]['total_spent'] > $data[1]['total_spent']);
        $this->assertTrue($data[1]['total_spent'] > $data[2]['total_spent']);
    }

    public function test_non_admin_cannot_view_top_farmers(): void
    {
        $farmer = User::factory()->create();
        $farmer->syncRoles(['farmer']);

        $response = $this->actingAs($farmer, 'sanctum')->getJson('/api/v1/admin/reports/top-farmers');
        $response->assertStatus(403);
    }
}
