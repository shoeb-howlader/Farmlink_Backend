<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_admin_can_perform_global_search(): void
    {
        $admin = User::factory()->create();
        $admin->syncRoles(['admin']);

        $farmer = User::factory()->create(['name' => 'Abdur Rahim', 'phone' => '01711223344']);
        $farmer->syncRoles(['farmer']);

        $product = Product::factory()->create([
            'name' => 'CP Premium Shrimp Feed 35%',
            'category' => 'Feed',
            'is_active' => true,
        ]);

        $order = Order::create([
            'user_id' => $farmer->id,
            'status' => 'pending',
            'total' => 5000,
        ]);

        // Search for "Abdur"
        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/search?q=Abdur');
        $response->assertStatus(200)
            ->assertJsonPath('data.farmers.0.title', 'Abdur Rahim')
            ->assertJsonPath('data.orders.0.title', "Order #ORD-{$order->id}");

        // Search for "Shrimp"
        $prodResponse = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/search?q=Shrimp');
        $prodResponse->assertStatus(200)
            ->assertJsonPath('data.products.0.title', 'CP Premium Shrimp Feed 35%');
    }
}
