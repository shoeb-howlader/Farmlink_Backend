<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductSalePricingTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'farmer', 'guard_name' => 'web']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->product = Product::factory()->create([
            'name' => 'Premium Bio-Probiotic 1kg',
            'category' => 'probiotics',
            'price' => 800.00,
            'stock' => 50,
            'is_active' => true,
        ]);
        $this->product->variants()->delete();
    }

    public function test_variant_with_future_sale_ends_at_shows_on_sale(): void
    {
        $variant = ProductVariant::create([
            'product_id' => $this->product->id,
            'variant_label' => '1kg Pack',
            'sku' => 'PROBIO-1KG',
            'price' => 700.00,
            'compare_at_price' => 1000.00,
            'sale_starts_at' => now()->subDay(),
            'sale_ends_at' => now()->addDays(7),
            'stock' => 50,
            'is_default' => true,
        ]);

        $this->assertTrue($variant->is_on_sale);
        $this->assertEquals(30, $variant->discount_percentage); // (1000 - 700) / 1000 = 30%

        // Through public API
        $response = $this->getJson("/api/v1/products/{$this->product->id}");
        $response->assertOk()
            ->assertJsonPath('data.default_variant.is_on_sale', true)
            ->assertJsonPath('data.default_variant.compare_at_price', 1000)
            ->assertJsonPath('data.default_variant.discount_percentage', 30);
    }

    public function test_variant_with_past_sale_ends_at_does_not_show_on_sale(): void
    {
        $variant = ProductVariant::create([
            'product_id' => $this->product->id,
            'variant_label' => '500g Pack',
            'sku' => 'PROBIO-500G',
            'price' => 450.00,
            'compare_at_price' => 600.00,
            'sale_starts_at' => now()->subDays(10),
            'sale_ends_at' => now()->subDay(), // Expired yesterday
            'stock' => 30,
            'is_default' => true,
        ]);

        $this->assertFalse($variant->is_on_sale);
        $this->assertNull($variant->discount_percentage);

        // Through public API
        $response = $this->getJson("/api/v1/products/{$this->product->id}");
        $response->assertOk()
            ->assertJsonPath('data.default_variant.is_on_sale', false)
            ->assertJsonPath('data.default_variant.discount_percentage', null);
    }

    public function test_variant_where_compare_at_price_less_or_equal_to_price_is_not_on_sale(): void
    {
        $variant = ProductVariant::create([
            'product_id' => $this->product->id,
            'variant_label' => 'Special Pack',
            'sku' => 'PROBIO-SPEC',
            'price' => 500.00,
            'compare_at_price' => 500.00, // Not greater than price
            'stock' => 20,
            'is_default' => true,
        ]);

        $this->assertFalse($variant->is_on_sale);
    }

    public function test_admin_can_save_variant_sale_pricing(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/v1/admin/products', [
            'name' => 'Aquaculture Mineral Mix 5kg',
            'category' => 'chemicals',
            'price' => 1200.00,
            'variants' => [
                [
                    'variant_label' => '5kg Tub',
                    'price' => 1200.00,
                    'compare_at_price' => 1500.00,
                    'sale_starts_at' => now()->subDay()->toDateString(),
                    'sale_ends_at' => now()->addMonth()->toDateString(),
                    'stock' => 15,
                    'is_default' => true,
                ],
            ],
        ]);

        $response->assertCreated();
        $variant = ProductVariant::where('sku', 'like', 'PRD-%')->first();
        $this->assertNotNull($variant);
        $this->assertEquals(1500.00, (float) $variant->compare_at_price);
        $this->assertTrue($variant->is_on_sale);
        $this->assertEquals(20, $variant->discount_percentage); // (1500 - 1200) / 1500 = 20%
    }
}
