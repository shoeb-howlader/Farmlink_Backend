<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\ProductVariant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('products support multiple size/weight variants and expose pricing range', function () {
    $product = Product::create([
        'name' => 'Mega Aqua Feed Grower',
        'category' => 'Feed',
        'price' => 2450.00,
        'stock' => 195,
        'description' => 'Formulated fish feed.',
        'is_active' => true,
        'is_featured' => true,
    ]);

    $v1 = $product->variants()->create([
        'variant_label' => '5kg Bag',
        'sku' => 'SKU-001-5KG',
        'price' => 550.00,
        'stock' => 30,
        'is_default' => false,
    ]);

    $v2 = $product->variants()->create([
        'variant_label' => '25kg Commercial',
        'sku' => 'SKU-001-25KG',
        'price' => 2450.00,
        'stock' => 165,
        'is_default' => true,
    ]);

    expect($product->variants()->count())->toBe(2);
    expect($product->has_multiple_variants)->toBeTrue();
    expect($product->min_price)->toEqual(550.0);
    expect($product->max_price)->toEqual(2450.0);

    // Public API returns variants, min/max price, and default variant
    $response = $this->getJson("/api/v1/products/{$product->id}");
    $response->assertStatus(200)
        ->assertJsonPath('data.has_multiple_variants', true)
        ->assertJsonPath('data.min_price', 550)
        ->assertJsonPath('data.max_price', 2450)
        ->assertJsonPath('data.default_variant.id', $v2->id)
        ->assertJsonCount(2, 'data.variants');
});

test('order line items reference specific variant and deduct variant stock', function () {
    $farmer = User::factory()->farmer()->create();
    Sanctum::actingAs($farmer);

    $product = Product::create([
        'name' => 'Bio-Aqua Probiotic',
        'category' => 'Probiotics',
        'price' => 850.00,
        'stock' => 50,
        'is_active' => true,
    ]);

    $smallVariant = $product->variants()->create([
        'variant_label' => '250g',
        'sku' => 'VAR-250G',
        'price' => 450.00,
        'stock' => 10,
        'is_default' => false,
    ]);

    $largeVariant = $product->variants()->create([
        'variant_label' => '1kg',
        'sku' => 'VAR-1KG',
        'price' => 1500.00,
        'stock' => 5,
        'is_default' => true,
    ]);

    // 1. Order 3 units of small variant (450 * 3 = 1350)
    $response = $this->postJson('/api/v1/orders', [
        'items' => [
            [
                'product_id' => $product->id,
                'product_variant_id' => $smallVariant->id,
                'quantity' => 3,
            ],
        ],
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.total', 1350)
        ->assertJsonPath('data.items.0.product_variant_id', $smallVariant->id)
        ->assertJsonPath('data.items.0.variant_label', '250g');

    expect($smallVariant->fresh()->stock)->toBe(7);
    expect($largeVariant->fresh()->stock)->toBe(5);

    // 2. Ordering more than available variant stock fails with validation error
    $failResponse = $this->postJson('/api/v1/orders', [
        'items' => [
            [
                'product_id' => $product->id,
                'product_variant_id' => $largeVariant->id,
                'quantity' => 10, // Only 5 available
            ],
        ],
    ]);

    $failResponse->assertStatus(422)
        ->assertJsonValidationErrors(['items']);
});

test('admin can manage variants, multi-image gallery, and featured toggle', function () {
    $admin = User::factory()->admin()->create();
    Sanctum::actingAs($admin);

    // 1. Create product with variants and featured toggle
    $createResponse = $this->postJson('/api/v1/admin/products', [
        'name' => 'Multi-Size Oxygen Tablets',
        'category' => 'Chemicals',
        'description' => 'Fast dissolving oxygen donor.',
        'is_featured' => true,
        'variants' => [
            ['variant_label' => '500g', 'sku' => 'OXY-500', 'price' => 400.00, 'stock' => 20, 'is_default' => true],
            ['variant_label' => '2kg', 'sku' => 'OXY-2K', 'price' => 1400.00, 'stock' => 10, 'is_default' => false],
        ],
    ]);

    $createResponse->assertStatus(201)
        ->assertJsonPath('data.is_featured', true)
        ->assertJsonCount(2, 'data.variants');

    $productId = $createResponse->json('data.id');
    $product = Product::find($productId);

    // 2. Toggle featured flag
    $unfeatureResponse = $this->patchJson("/api/v1/admin/products/{$productId}/featured", [
        'is_featured' => false,
    ]);
    $unfeatureResponse->assertStatus(200)
        ->assertJsonPath('data.is_featured', false);

    // 3. Add gallery images
    $image1 = $product->images()->create([
        'image_path' => 'products/oxy-main.jpg',
        'sort_order' => 1,
        'is_primary' => true,
    ]);
    $image2 = $product->images()->create([
        'image_path' => 'products/oxy-side.jpg',
        'sort_order' => 2,
        'is_primary' => false,
    ]);

    // Reorder images
    $reorderResponse = $this->patchJson("/api/v1/admin/products/{$productId}/images/reorder", [
        'primary_id' => $image2->id,
        'order' => [
            ['id' => $image2->id, 'sort_order' => 1],
            ['id' => $image1->id, 'sort_order' => 2],
        ],
    ]);
    $reorderResponse->assertStatus(200);
    expect($image2->fresh()->is_primary)->toBeTrue();
    expect($image1->fresh()->is_primary)->toBeFalse();

    // Delete gallery image
    $deleteResponse = $this->deleteJson("/api/v1/admin/products/{$productId}/images/{$image1->id}");
    $deleteResponse->assertStatus(200);
    expect($product->images()->count())->toBe(1);
});

test('farmer can only review products from delivered orders and cannot submit duplicates', function () {
    $farmer = User::factory()->farmer()->create();
    $otherFarmer = User::factory()->farmer()->create();

    $product = Product::create([
        'name' => 'Probiotic Flora 1kg',
        'category' => 'Probiotics',
        'price' => 1200.00,
        'stock' => 50,
        'is_active' => true,
    ]);

    $order = Order::create([
        'user_id' => $farmer->id,
        'status' => 'dispatched', // Not yet delivered!
        'total' => 1200.00,
    ]);

    $orderItem = OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'price_at_purchase' => 1200.00,
    ]);

    Sanctum::actingAs($farmer);

    // 1. Cannot review while order is not delivered
    $undeliveredResponse = $this->postJson("/api/v1/products/{$product->id}/reviews", [
        'order_id' => $order->id,
        'rating' => 5,
        'comment' => 'Great product!',
    ]);
    $undeliveredResponse->assertStatus(422)
        ->assertJsonValidationErrors(['order_id']);

    // 2. Cannot review someone else's order
    Sanctum::actingAs($otherFarmer);
    $unauthorizedResponse = $this->postJson("/api/v1/products/{$product->id}/reviews", [
        'order_id' => $order->id,
        'rating' => 5,
    ]);
    $unauthorizedResponse->assertStatus(403);

    // 3. Mark order as delivered
    $order->update(['status' => 'delivered']);
    Sanctum::actingAs($farmer);

    // 4. Submit valid review
    $reviewResponse = $this->postJson("/api/v1/products/{$product->id}/reviews", [
        'order_id' => $order->id,
        'rating' => 5,
        'comment' => 'Remarkable water clarity improvement within 48 hours.',
    ]);

    $reviewResponse->assertStatus(201)
        ->assertJsonPath('data.rating', 5)
        ->assertJsonPath('data.comment', 'Remarkable water clarity improvement within 48 hours.')
        ->assertJsonPath('data.user_name', $farmer->name);

    // 5. Duplicate review for the same order and product is rejected
    $duplicateResponse = $this->postJson("/api/v1/products/{$product->id}/reviews", [
        'order_id' => $order->id,
        'rating' => 4,
        'comment' => 'Trying to submit again',
    ]);
    $duplicateResponse->assertStatus(422)
        ->assertJsonValidationErrors(['rating']);

    // 6. Public product detail reflects average rating and review count
    $productResponse = $this->getJson("/api/v1/products/{$product->id}");
    $productResponse->assertStatus(200)
        ->assertJsonPath('data.rating_avg', 5)
        ->assertJsonPath('data.rating_count', 1);

    // 7. Public reviews endpoint lists reviews
    $reviewsListResponse = $this->getJson("/api/v1/products/{$product->id}/reviews");
    $reviewsListResponse->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.rating', 5);
});
