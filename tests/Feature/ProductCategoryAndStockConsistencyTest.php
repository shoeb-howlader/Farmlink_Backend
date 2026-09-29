<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductCategoryAndStockConsistencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_category_filters_case_insensitively_and_via_slug(): void
    {
        Product::factory()->create([
            'name' => 'Antibiotic Aqua Cure',
            'category' => 'Medicine',
            'is_active' => true,
        ]);

        Product::factory()->create([
            'name' => 'High Protein Pellet Feed',
            'category' => 'Feed',
            'is_active' => true,
        ]);

        // Lowercase slug
        $response = $this->getJson('/api/v1/products?category=medicine');
        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Medicine', $response->json('data.0.category'));

        // Exact match
        $responseFeed = $this->getJson('/api/v1/products?category=Feed');
        $responseFeed->assertOk();
        $this->assertCount(1, $responseFeed->json('data'));
        $this->assertEquals('Feed', $responseFeed->json('data.0.category'));
    }

    public function test_category_filter_combined_with_search_in_stock_sort_and_page(): void
    {
        $feed1 = Product::factory()->create([
            'name' => 'Premium Floating Feed',
            'category' => 'Feed',
            'price' => 1000.00,
            'is_active' => true,
        ]);
        $feed1->variants()->delete();
        $feed1->variants()->create([
            'variant_label' => '10kg',
            'price' => 1000.00,
            'stock' => 50,
            'is_default' => true,
        ]);
        $feed1->syncAggregateStockAndPrice();

        $feed2 = Product::factory()->create([
            'name' => 'Budget Sinking Feed',
            'category' => 'Feed',
            'price' => 500.00,
            'is_active' => true,
        ]);
        $feed2->variants()->delete();
        $feed2->variants()->create([
            'variant_label' => '5kg',
            'price' => 500.00,
            'stock' => 0, // Out of stock
            'is_default' => true,
        ]);
        $feed2->syncAggregateStockAndPrice();

        // In-stock only filter for Feed
        $resInStock = $this->getJson('/api/v1/products?category=feed&in_stock=true');
        $resInStock->assertOk();
        $this->assertCount(1, $resInStock->json('data'));
        $this->assertEquals('Premium Floating Feed', $resInStock->json('data.0.name'));

        // Search combined with category
        $resSearch = $this->getJson('/api/v1/products?category=feed&search=Budget');
        $resSearch->assertOk();
        $this->assertCount(1, $resSearch->json('data'));
        $this->assertEquals('Budget Sinking Feed', $resSearch->json('data.0.name'));

        // Sort price asc with pagination
        $resSort = $this->getJson('/api/v1/products?category=feed&sort=price_asc&per_page=1&page=1');
        $resSort->assertOk();
        $this->assertCount(1, $resSort->json('data'));
        $this->assertEquals('Budget Sinking Feed', $resSort->json('data.0.name'));
    }

    public function test_product_availability_matches_its_variants(): void
    {
        $product = Product::factory()->create([
            'name' => 'Aerator Motor',
            'category' => 'Equipment',
            'is_active' => true,
        ]);
        $product->variants()->delete();

        $v1 = $product->variants()->create([
            'variant_label' => '1HP',
            'price' => 15000.00,
            'stock' => 0,
            'is_default' => true,
        ]);

        $v2 = $product->variants()->create([
            'variant_label' => '2HP',
            'price' => 25000.00,
            'stock' => 0,
            'is_default' => false,
        ]);
        $product->syncAggregateStockAndPrice();

        // When all variants are 0, product is not in stock
        $freshProduct = $product->fresh(['variants']);
        $this->assertFalse($freshProduct->is_in_stock);
        $this->assertEquals(0, $freshProduct->stock);

        // When one variant gets stock > 0, product becomes in stock
        $v1->update(['stock' => 5]);
        $product->syncAggregateStockAndPrice();
        $freshProductAfter = $product->fresh(['variants']);
        $this->assertTrue($freshProductAfter->is_in_stock);
        $this->assertEquals(5, $freshProductAfter->stock);
    }

    public function test_zero_stock_variant_cannot_be_ordered(): void
    {
        $farmer = User::factory()->farmer()->create();
        $farm = Farm::factory()->create(['user_id' => $farmer->id]);

        $product = Product::factory()->create(['name' => 'Pond Oxygenator', 'price' => 1200.00]);
        $variant = $product->variants()->first();
        $variant->update(['stock' => 0]);
        $product->syncAggregateStockAndPrice();

        Sanctum::actingAs($farmer);
        $response = $this->postJson('/api/v1/orders', [
            'farm_id' => $farm->id,
            'payment_method' => 'cash_on_delivery',
            'delivery_address' => 'Satkhira Farm #1',
            'items' => [
                [
                    'product_id' => $product->id,
                    'product_variant_id' => $variant->id,
                    'quantity' => 1,
                ],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['items']);
    }

    public function test_admin_stock_adjustment_targets_specific_variant(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $product = Product::factory()->create(['name' => 'Multi-Size Vitamin Mix']);
        $product->variants()->delete();

        $v1 = $product->variants()->create([
            'variant_label' => '250g',
            'price' => 300.00,
            'stock' => 10,
            'is_default' => true,
        ]);
        $v2 = $product->variants()->create([
            'variant_label' => '1kg',
            'price' => 1000.00,
            'stock' => 20,
            'is_default' => false,
        ]);
        $product->syncAggregateStockAndPrice();

        $response = $this->patchJson("/api/v1/admin/products/{$product->id}/stock", [
            'product_variant_id' => $v2->id,
            'stock' => 35,
            'reason' => 'Inventory count audit received batch',
        ]);

        $response->assertOk();
        $this->assertEquals(35, $v2->fresh()->stock);
        $this->assertEquals(10, $v1->fresh()->stock);
        $this->assertEquals(45, $product->fresh()->stock);
    }
}
