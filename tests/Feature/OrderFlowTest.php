<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

/*
|--------------------------------------------------------------------------
| Product Catalog Tests
|--------------------------------------------------------------------------
*/

test('anyone can view the product catalog', function () {
    Product::factory()->create(['name' => 'Feed A', 'category' => 'Feed', 'price' => 100.00, 'stock' => 10]);
    Product::factory()->create(['name' => 'Medicine B', 'category' => 'Medicine', 'price' => 200.00, 'stock' => 5]);

    $response = $this->getJson('/api/v1/products');

    $response->assertStatus(200)
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                '*' => ['id', 'name', 'category', 'price', 'stock', 'description', 'created_at', 'updated_at'],
            ],
        ]);
});

test('products can be filtered by category and search term', function () {
    Product::factory()->create(['name' => 'Aqua Growth Feed', 'category' => 'Feed', 'description' => 'Nutritious feed for fish']);
    Product::factory()->create(['name' => 'Super Disinfectant', 'category' => 'Medicine', 'description' => 'Pond water cleaning agent']);

    // Filter by category
    $categoryResponse = $this->getJson('/api/v1/products?category=Feed');
    $categoryResponse->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Aqua Growth Feed');

    // Search by name
    $searchResponse = $this->getJson('/api/v1/products?search=Disinfectant');
    $searchResponse->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Super Disinfectant');
});

/*
|--------------------------------------------------------------------------
| Order Creation Tests
|--------------------------------------------------------------------------
*/

test('unauthenticated users cannot place orders or view orders', function () {
    $this->postJson('/api/v1/orders', ['items' => []])->assertStatus(401);
    $this->getJson('/api/v1/orders')->assertStatus(401);
    $this->getJson('/api/v1/orders/1')->assertStatus(401);
    $this->patchJson('/api/v1/admin/orders/1/status', ['status' => 'confirmed'])->assertStatus(401);
});

test('a farmer can create an order from a cart payload', function () {
    $farmer = User::factory()->farmer()->create();
    Sanctum::actingAs($farmer);

    $product1 = Product::factory()->create(['name' => 'Feed 25kg', 'price' => 1500.00, 'stock' => 20]);
    $product2 = Product::factory()->create(['name' => 'Aerator 2HP', 'price' => 8000.00, 'stock' => 5]);

    $payload = [
        'items' => [
            ['product_id' => $product1->id, 'quantity' => 2],
            ['product_id' => $product2->id, 'quantity' => 1],
        ],
    ];

    $response = $this->postJson('/api/v1/orders', $payload);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'id',
                'user_id',
                'status',
                'total',
                'items' => [
                    '*' => [
                        'id',
                        'order_id',
                        'product_id',
                        'quantity',
                        'price_at_purchase',
                        'total',
                        'product',
                    ],
                ],
            ],
        ])
        ->assertJson([
            'success' => true,
            'data' => [
                'user_id' => $farmer->id,
                'status' => 'pending',
                'total' => 11000.00, // 2 * 1500 + 1 * 8000
            ],
        ]);

    // Check that database records exist
    $this->assertDatabaseHas('orders', [
        'user_id' => $farmer->id,
        'status' => 'pending',
        'total' => 11000.00,
    ]);

    $this->assertDatabaseHas('order_items', [
        'product_id' => $product1->id,
        'quantity' => 2,
        'price_at_purchase' => 1500.00,
    ]);

    $this->assertDatabaseHas('order_items', [
        'product_id' => $product2->id,
        'quantity' => 1,
        'price_at_purchase' => 8000.00,
    ]);

    // Stock should be decremented
    expect($product1->fresh()->stock)->toBe(18)
        ->and($product2->fresh()->stock)->toBe(4);
});

test('order creation fails when stock is insufficient', function () {
    $farmer = User::factory()->farmer()->create();
    Sanctum::actingAs($farmer);

    $product = Product::factory()->create(['price' => 500.00, 'stock' => 2]);

    $response = $this->postJson('/api/v1/orders', [
        'items' => [
            ['product_id' => $product->id, 'quantity' => 5],
        ],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['items']);

    // Stock should remain unchanged and no order created
    expect($product->fresh()->stock)->toBe(2);
    $this->assertDatabaseCount('orders', 0);
});

test('order creation validates payload schema', function () {
    $farmer = User::factory()->farmer()->create();
    Sanctum::actingAs($farmer);

    $this->postJson('/api/v1/orders', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['items']);

    $this->postJson('/api/v1/orders', ['items' => []])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['items']);

    $this->postJson('/api/v1/orders', [
        'items' => [
            ['product_id' => 99999, 'quantity' => 0],
        ],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['items.0.product_id', 'items.0.quantity']);
});

/*
|--------------------------------------------------------------------------
| Order Viewing & Authorization Tests
|--------------------------------------------------------------------------
*/

test('farmer can only view their own orders in index', function () {
    $farmer1 = User::factory()->farmer()->create();
    $farmer2 = User::factory()->farmer()->create();

    $order1 = Order::factory()->create(['user_id' => $farmer1->id]);
    $order2 = Order::factory()->create(['user_id' => $farmer2->id]);

    Sanctum::actingAs($farmer1);

    $response = $this->getJson('/api/v1/orders');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $order1->id);
});

test('farmer can view their own order detail but cannot view other farmers orders', function () {
    $farmer1 = User::factory()->farmer()->create();
    $farmer2 = User::factory()->farmer()->create();

    $order1 = Order::factory()->create(['user_id' => $farmer1->id]);
    $order2 = Order::factory()->create(['user_id' => $farmer2->id]);

    Sanctum::actingAs($farmer1);

    // Can view own order
    $this->getJson("/api/v1/orders/{$order1->id}")
        ->assertStatus(200)
        ->assertJsonPath('data.id', $order1->id);

    // Forbidden from viewing other farmer's order
    $this->getJson("/api/v1/orders/{$order2->id}")
        ->assertStatus(403);
});

test('non-admin user without explicit role is strictly scoped to their own orders in index', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $order1 = Order::factory()->create(['user_id' => $user1->id]);
    $order2 = Order::factory()->create(['user_id' => $user2->id]);

    Sanctum::actingAs($user1);

    $response = $this->getJson('/api/v1/orders');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $order1->id);
});

test('non-admin user cannot view other users orders by passing user_id query parameter', function () {
    $farmer1 = User::factory()->farmer()->create();
    $farmer2 = User::factory()->farmer()->create();

    $order1 = Order::factory()->create(['user_id' => $farmer1->id]);
    $order2 = Order::factory()->create(['user_id' => $farmer2->id]);

    Sanctum::actingAs($farmer1);

    $response = $this->getJson("/api/v1/orders?user_id={$farmer2->id}");

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $order1->id);
});

test('order detail endpoint returns eager loaded farmer user details', function () {
    $farmer = User::factory()->farmer()->create([
        'name' => 'Test Farmer Name',
        'phone' => '01711122233',
        'email' => 'farmer@test.com',
        'district' => 'Khulna',
    ]);
    $order = Order::factory()->create(['user_id' => $farmer->id]);

    Sanctum::actingAs($farmer);

    $response = $this->getJson("/api/v1/orders/{$order->id}");

    $response->assertStatus(200)
        ->assertJsonPath('data.id', $order->id)
        ->assertJsonPath('data.user.name', 'Test Farmer Name')
        ->assertJsonPath('data.user.phone', '01711122233')
        ->assertJsonPath('data.user.email', 'farmer@test.com')
        ->assertJsonPath('data.user.district', 'Khulna');
});


/*
|--------------------------------------------------------------------------
| Admin Order Status Tests
|--------------------------------------------------------------------------
*/

test('farmer cannot change order status', function () {
    $farmer = User::factory()->farmer()->create();
    $order = Order::factory()->create(['user_id' => $farmer->id, 'status' => 'pending']);

    Sanctum::actingAs($farmer);

    $response = $this->patchJson("/api/v1/admin/orders/{$order->id}/status", [
        'status' => 'confirmed',
    ]);

    $response->assertStatus(403);
    expect($order->fresh()->status)->toBe('pending');
});

test('admin can update order status', function () {
    $admin = User::factory()->admin()->create();
    $farmer = User::factory()->farmer()->create();
    $order = Order::factory()->create(['user_id' => $farmer->id, 'status' => 'pending']);

    Sanctum::actingAs($admin);

    $response = $this->patchJson("/api/v1/admin/orders/{$order->id}/status", [
        'status' => 'confirmed',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.id', $order->id)
        ->assertJsonPath('data.status', 'confirmed');

    expect($order->fresh()->status)->toBe('confirmed');

    // Admin updates to dispatched then delivered
    $this->patchJson("/api/v1/admin/orders/{$order->id}/status", ['status' => 'dispatched'])
        ->assertStatus(200)
        ->assertJsonPath('data.status', 'dispatched');

    $this->patchJson("/api/v1/admin/orders/{$order->id}/status", ['status' => 'delivered'])
        ->assertStatus(200)
        ->assertJsonPath('data.status', 'delivered');

    expect($order->fresh()->status)->toBe('delivered');
});

test('order status update requires valid status', function () {
    $admin = User::factory()->admin()->create();
    $order = Order::factory()->create(['status' => 'pending']);

    Sanctum::actingAs($admin);

    $this->patchJson("/api/v1/admin/orders/{$order->id}/status", [
        'status' => 'invalid_status',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['status']);
});
