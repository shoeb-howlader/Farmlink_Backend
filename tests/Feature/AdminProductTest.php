<?php

use App\Models\Product;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('admin can list, create, update, adjust stock, and toggle status of products', function () {
    $admin = User::factory()->admin()->create();
    Sanctum::actingAs($admin);

    // 1. Create Product
    $createResponse = $this->postJson('/api/v1/admin/products', [
        'name' => 'Bio Enzyme 1L',
        'category' => 'Chemicals',
        'price' => 1250.00,
        'stock' => 50,
        'description' => 'Water treatment enzyme.',
        'is_active' => true,
    ]);

    $createResponse->assertStatus(201)
        ->assertJsonPath('data.name', 'Bio Enzyme 1L')
        ->assertJsonPath('data.stock', 50)
        ->assertJsonPath('data.is_active', true);

    $productId = $createResponse->json('data.id');

    // 2. List Products
    $listResponse = $this->getJson('/api/v1/admin/products');
    $listResponse->assertStatus(200)
        ->assertJsonCount(1, 'data');

    // 3. Update Product
    $updateResponse = $this->patchJson("/api/v1/admin/products/{$productId}", [
        'name' => 'Bio Enzyme Premium 1L',
        'price' => 1400.00,
    ]);

    $updateResponse->assertStatus(200)
        ->assertJsonPath('data.name', 'Bio Enzyme Premium 1L')
        ->assertJsonPath('data.price', 1400);

    // 4. Adjust Stock with Reason
    $stockResponse = $this->patchJson("/api/v1/admin/products/{$productId}/stock", [
        'stock' => 80,
        'reason' => 'Shipment arrived from warehouse',
    ]);

    $stockResponse->assertStatus(200)
        ->assertJsonPath('data.stock', 80);

    $this->assertDatabaseHas('product_stock_adjustments', [
        'product_id' => $productId,
        'user_id' => $admin->id,
        'old_stock' => 50,
        'new_stock' => 80,
        'adjustment' => 30,
        'reason' => 'Shipment arrived from warehouse',
    ]);

    // 5. Deactivate Product
    $statusResponse = $this->patchJson("/api/v1/admin/products/{$productId}/status", [
        'is_active' => false,
    ]);

    $statusResponse->assertStatus(200)
        ->assertJsonPath('data.is_active', false);

    // 6. Assert deactivated product does NOT appear in farmer catalog
    $farmer = User::factory()->farmer()->create();
    Sanctum::actingAs($farmer);

    $catalogResponse = $this->getJson('/api/v1/products');
    $catalogResponse->assertStatus(200)
        ->assertJsonCount(0, 'data');
});

test('public endpoint returns featured products prioritizing is_featured and falling back to in_stock', function () {
    $admin = User::factory()->admin()->create();
    Sanctum::actingAs($admin);

    // Create 5 active products with stock
    $products = [];
    for ($i = 1; $i <= 5; $i++) {
        $products[] = Product::create([
            'name' => "Product {$i}",
            'category' => 'Feed',
            'price' => 100 * $i,
            'stock' => 10 * $i,
            'is_active' => true,
            'is_featured' => false,
        ]);
    }

    // Toggle product 3 to featured via admin endpoint
    $featuredResponse = $this->patchJson("/api/v1/admin/products/{$products[2]->id}/featured", [
        'is_featured' => true,
    ]);
    $featuredResponse->assertStatus(200)
        ->assertJsonPath('data.is_featured', true);

    // Call public endpoint without auth
    app('auth')->forgetGuards();
    $publicResponse = $this->getJson('/api/v1/products?featured=1');
    $publicResponse->assertStatus(200)
        ->assertJsonCount(4, 'data')
        ->assertJsonPath('data.0.id', $products[2]->id)
        ->assertJsonPath('data.0.is_featured', true);

    // Assert prices are present and not obscured
    $data = $publicResponse->json('data');
    foreach ($data as $item) {
        expect($item['price'])->toBeGreaterThan(0);
        expect($item['stock'])->toBeGreaterThan(0);
    }
});

test('product seeder generates 25 genuinely distinct products across 5 categories with zero duplicates', function () {
    $this->seed(\Database\Seeders\ProductSeeder::class);

    $products = Product::all();
    expect($products->count())->toBe(25);

    // Verify 100% unique names, descriptions
    $uniqueNames = $products->pluck('name')->unique();
    expect($uniqueNames->count())->toBe(25);

    $uniqueDescriptions = $products->pluck('description')->unique();
    expect($uniqueDescriptions->count())->toBe(25);

    // Verify 5 distinct categories with 5 products each
    $categories = $products->groupBy('category');
    expect($categories->keys()->all())->toEqualCanonicalizing([
        'Feed',
        'Probiotics',
        'Equipment',
        'Chemicals',
        'Medicine',
    ]);
    foreach ($categories as $categoryName => $items) {
        expect($items->count())->toBe(5);
    }

    // Verify featured items
    $featured = $products->where('is_featured', true);
    expect($featured->count())->toBe(4);

    // Verify idempotency: re-running seeder does not create duplicates
    $this->seed(\Database\Seeders\ProductSeeder::class);
    expect(Product::count())->toBe(25);
    expect(Product::distinct('name')->count('name'))->toBe(25);
});

test('public endpoint returns single product detail and 404 for inactive product', function () {
    $product = Product::create([
        'name' => 'High Grade Shrimp Feed',
        'category' => 'Feed',
        'price' => 3200.00,
        'stock' => 15,
        'description' => 'Nutrient-rich micro-pellet feed for black tiger shrimp.',
        'is_active' => true,
    ]);

    $inactiveProduct = Product::create([
        'name' => 'Discontinued Aerator',
        'category' => 'Equipment',
        'price' => 15000.00,
        'stock' => 0,
        'description' => 'Old model aerator.',
        'is_active' => false,
    ]);

    // Active product returns 200 with full details
    $response = $this->getJson("/api/v1/products/{$product->id}");
    $response->assertStatus(200)
        ->assertJsonPath('data.id', $product->id)
        ->assertJsonPath('data.name', 'High Grade Shrimp Feed')
        ->assertJsonPath('data.price', 3200)
        ->assertJsonPath('data.stock', 15)
        ->assertJsonPath('data.description', 'Nutrient-rich micro-pellet feed for black tiger shrimp.');

    // Inactive product returns 404
    $inactiveResponse = $this->getJson("/api/v1/products/{$inactiveProduct->id}");
    $inactiveResponse->assertStatus(404);
});

test('public endpoint supports sorting products by price_asc, price_desc, and name_asc', function () {
    Product::create(['name' => 'Zebra Feed', 'category' => 'Feed', 'price' => 500, 'stock' => 10, 'is_active' => true]);
    Product::create(['name' => 'Alpha Probiotic', 'category' => 'Probiotics', 'price' => 100, 'stock' => 10, 'is_active' => true]);
    Product::create(['name' => 'Beta Chemical', 'category' => 'Chemicals', 'price' => 300, 'stock' => 10, 'is_active' => true]);

    // Price Ascending
    $asc = $this->getJson('/api/v1/products?sort=price_asc')->json('data');
    expect($asc[0]['price'])->toEqual(100)
        ->and($asc[1]['price'])->toEqual(300)
        ->and($asc[2]['price'])->toEqual(500);

    // Price Descending
    $desc = $this->getJson('/api/v1/products?sort=price_desc')->json('data');
    expect($desc[0]['price'])->toEqual(500)
        ->and($desc[1]['price'])->toEqual(300)
        ->and($desc[2]['price'])->toEqual(100);

    // Name Ascending
    $nameAsc = $this->getJson('/api/v1/products?sort=name_asc')->json('data');
    expect($nameAsc[0]['name'])->toEqual('Alpha Probiotic')
        ->and($nameAsc[1]['name'])->toEqual('Beta Chemical')
        ->and($nameAsc[2]['name'])->toEqual('Zebra Feed');
});


