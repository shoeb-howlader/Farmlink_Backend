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
