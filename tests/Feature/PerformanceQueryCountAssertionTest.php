<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductReview;
use App\Models\ProductVariant;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Models\VetRecord;
use App\Models\ConsultantRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PerformanceQueryCountAssertionTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $farmer;

    protected function setUp(): void
    {
        parent::setUp();

        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'farmer', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'veterinary_doctor', 'guard_name' => 'web']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->farmer = User::factory()->create();
        $this->farmer->assignRole('farmer');
    }

    public function test_product_catalog_executes_within_strict_query_count_limit(): void
    {
        $farm = Farm::factory()->create(['user_id' => $this->farmer->id]);
        $order = Order::create([
            'user_id' => $this->farmer->id,
            'farm_id' => $farm->id,
            'status' => 'delivered',
            'channel' => 'self_service',
            'subtotal' => 500,
            'total' => 500,
        ]);

        for ($i = 1; $i <= 10; $i++) {
            $product = Product::create([
                'name' => "Aquaculture Feed #{$i}",
                'category' => 'Feed & Nutrition',
                'price' => 100 * $i,
                'stock' => 50,
                'is_active' => true,
            ]);

            ProductVariant::create([
                'product_id' => $product->id,
                'variant_label' => '25kg Bag',
                'sku' => "FEED-{$i}-25KG",
                'price' => 100 * $i,
                'stock' => 20,
            ]);

            ProductReview::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'user_id' => $this->farmer->id,
                'rating' => 5,
                'comment' => 'Great product quality',
                'is_approved' => true,
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->getJson('/api/v1/products?per_page=10');

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $response->assertOk();
        $response->assertJsonPath('meta.total', 10);

        // Strict assertion: list must not execute per-product queries (N+1 eliminated, was 180 queries)
        $this->assertLessThanOrEqual(8, count($queries), "Products catalog query count exceeded limit. Executed " . count($queries) . " queries.");
    }

    public function test_admin_orders_list_executes_within_strict_query_count_limit(): void
    {
        Sanctum::actingAs($this->admin);

        $farm = Farm::factory()->create(['user_id' => $this->farmer->id]);

        for ($i = 1; $i <= 5; $i++) {
            $product = Product::create([
                'name' => "Remedy #{$i}",
                'category' => 'Medicines',
                'price' => 200,
                'stock' => 50,
                'is_active' => true,
            ]);

            $order = Order::create([
                'user_id' => $this->farmer->id,
                'farm_id' => $farm->id,
                'invoice_number' => "INV-TEST-{$i}",
                'status' => 'pending',
                'payment_status' => 'paid',
                'channel' => 'self_service',
                'subtotal' => 400,
                'total' => 400,
                'created_at' => now()->subDays($i),
            ]);

            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => 2,
                'price_at_purchase' => 200,
                'subtotal' => 400,
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->getJson('/api/v1/admin/orders?per_page=5');

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $response->assertOk();

        // Strict assertion: orders listing must not execute per-order queries (N+1 eliminated, was 234 queries)
        $this->assertLessThanOrEqual(15, count($queries), "Admin orders list query count exceeded limit. Executed " . count($queries) . " queries.");
    }

    public function test_admin_farms_list_executes_within_strict_query_count_limit(): void
    {
        Sanctum::actingAs($this->admin);

        for ($i = 1; $i <= 6; $i++) {
            $user = User::factory()->create();
            $user->assignRole('farmer');
            Farm::factory()->create(['user_id' => $user->id, 'farm_name' => "Farm #{$i}"]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->getJson('/api/v1/admin/farms?per_page=6');

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $response->assertOk();

        // Strict assertion: farms list eagerly loads relations (was 71 queries)
        $this->assertLessThanOrEqual(10, count($queries), "Admin farms list query count exceeded limit. Executed " . count($queries) . " queries.");
    }

    public function test_admin_service_requests_queue_executes_within_strict_query_count_limit(): void
    {
        Sanctum::actingAs($this->admin);

        $vet = User::factory()->create();
        $vet->assignRole('veterinary_doctor');

        for ($i = 1; $i <= 6; $i++) {
            $farmer = User::factory()->create();
            $farmer->assignRole('farmer');
            $farm = Farm::factory()->create(['user_id' => $farmer->id]);

            ServiceRequest::create([
                'farmer_id' => $farmer->id,
                'farm_id' => $farm->id,
                'type' => 'vet',
                'status' => 'assigned',
                'assigned_to' => $vet->id,
                'assigned_role' => 'veterinary_doctor',
                'urgency' => 'normal',
                'description' => "Request #{$i}",
                'created_at' => now()->subHours($i),
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->getJson('/api/v1/admin/service-requests?per_page=6');

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $response->assertOk();

        // Strict assertion: queue uses eager loading with morphWith and single summary aggregation (was 267 queries)
        $this->assertLessThanOrEqual(15, count($queries), "Admin service requests queue query count exceeded limit. Executed " . count($queries) . " queries.");
    }
}
