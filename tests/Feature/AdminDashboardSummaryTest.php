<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\Order;
use App\Models\Product;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardSummaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    public function test_guest_cannot_access_dashboard_summary(): void
    {
        $response = $this->getJson('/api/v1/admin/dashboard/summary');
        $response->assertStatus(401);
    }

    public function test_farmer_cannot_access_dashboard_summary(): void
    {
        $farmer = User::factory()->create();
        $farmer->assignRole('farmer');

        $response = $this->actingAs($farmer, 'sanctum')->getJson('/api/v1/admin/dashboard/summary');
        $response->assertStatus(403);
    }

    public function test_admin_can_retrieve_dashboard_summary_with_all_metrics(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $farmer = User::factory()->create();
        $farmer->assignRole('farmer');

        // Seed sample orders
        Order::factory()->create([
            'user_id' => $farmer->id,
            'status' => 'confirmed',
            'total' => 5000,
        ]);
        Order::factory()->create([
            'user_id' => $farmer->id,
            'status' => 'delivered',
            'total' => 2500,
        ]);
        Order::factory()->create([
            'user_id' => $farmer->id,
            'status' => 'cancelled',
            'total' => 1000,
        ]);

        // Seed a low-stock product
        Product::factory()->create([
            'name' => 'Aquaculture Vitamin Pre-mix',
            'stock' => 2,
            'low_stock_threshold' => 10,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/dashboard/summary');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'sales' => [
                        'total_revenue',
                        'total_orders',
                        'this_month_revenue',
                        'this_month_orders',
                        'avg_order_value',
                        'status_counts',
                    ],
                    'sales_trend',
                    'alerts' => [
                        'urgent_service_requests',
                        'pending_orders',
                        'low_stock_count',
                        'pending_reschedule_requests',
                        'total_urgent_actions',
                    ],
                    'services' => [
                        'open_requests',
                        'urgent_requests',
                        'completed_this_month',
                        'avg_turnaround_hours',
                        'active_practitioners_count',
                        'category_breakdown',
                    ],
                    'critical_low_stock',
                ],
            ]);

        // Revenue should only count confirmed & delivered (5000 + 2500 = 7500), excluding cancelled
        $data = $response->json('data');
        $this->assertEquals(7500.0, $data['sales']['total_revenue']);
        $this->assertEquals(3, $data['sales']['total_orders']);
        $this->assertGreaterThanOrEqual(1, $data['alerts']['low_stock_count']);
        $this->assertNotEmpty($data['sales_trend']);
    }

    public function test_dashboard_summary_supports_date_range_filtering(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/dashboard/summary?range=7d');
        $response->assertStatus(200)
            ->assertJsonPath('data.sales.selected_range', '7d');
    }
}
