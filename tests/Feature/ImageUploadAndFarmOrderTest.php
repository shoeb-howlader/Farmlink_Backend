<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ImageUploadAndFarmOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'farmer', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'veterinary_doctor', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'consultant', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'data_entry_operator', 'guard_name' => 'web']);
        Storage::fake('public');
    }

    public function test_user_can_upload_avatar_and_generates_thumbnail(): void
    {
        $farmer = User::factory()->create([
            'gender' => 'unspecified',
        ]);
        $farmer->assignRole('farmer');

        $file = UploadedFile::fake()->image('avatar.jpg', 300, 300);

        $response = $this->actingAs($farmer, 'sanctum')
            ->postJson('/api/v1/me/avatar', [
                'avatar' => $file,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Profile avatar updated successfully',
            ])
            ->assertJsonStructure([
                'data' => [
                    'path',
                    'thumbnail_path',
                    'avatar_url',
                    'avatar_thumbnail_url',
                ],
            ]);

        $farmer->refresh();
        $this->assertNotNull($farmer->profile_image_path);
        $this->assertNotNull($farmer->profile_image_thumbnail_path);

        Storage::disk('public')->assertExists($farmer->profile_image_path);
        Storage::disk('public')->assertExists($farmer->profile_image_thumbnail_path);
    }

    public function test_upload_rejects_oversized_and_invalid_files(): void
    {
        $farmer = User::factory()->create();
        $farmer->assignRole('farmer');

        // Oversized > 5MB (5121 KB)
        $oversized = UploadedFile::fake()->create('big.jpg', 6000, 'image/jpeg');

        $this->actingAs($farmer, 'sanctum')
            ->postJson('/api/v1/me/avatar', [
                'avatar' => $oversized,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['avatar']);

        // Invalid extension / mime
        $invalidFile = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $this->actingAs($farmer, 'sanctum')
            ->postJson('/api/v1/me/avatar', [
                'avatar' => $invalidFile,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['avatar']);
    }

    public function test_admin_can_upload_product_image_and_generates_thumbnail(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $product = Product::factory()->create();
        $file = UploadedFile::fake()->image('product.png', 400, 400);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/products/{$product->id}/image", [
                'image' => $file,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Product image uploaded successfully',
            ]);

        $product->refresh();
        $this->assertNotNull($product->image_path);
        $this->assertNotNull($product->image_thumbnail_path);

        Storage::disk('public')->assertExists($product->image_path);
        Storage::disk('public')->assertExists($product->image_thumbnail_path);
    }

    public function test_admin_can_upload_farm_image_and_generates_thumbnail(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $farm = Farm::factory()->create();
        $file = UploadedFile::fake()->image('farm.webp', 500, 500);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/farms/{$farm->id}/image", [
                'image' => $file,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Farm image uploaded successfully',
            ]);

        $farm->refresh();
        $this->assertNotNull($farm->image_path);
        $this->assertNotNull($farm->image_thumbnail_path);

        Storage::disk('public')->assertExists($farm->image_path);
        Storage::disk('public')->assertExists($farm->image_thumbnail_path);
    }

    public function test_single_farm_auto_assigned_on_order_checkout(): void
    {
        $farmer = User::factory()->create();
        $farmer->assignRole('farmer');

        $farm = Farm::factory()->create(['user_id' => $farmer->id]);
        $product = Product::factory()->create(['price' => 500, 'stock' => 20]);

        $response = $this->actingAs($farmer, 'sanctum')
            ->postJson('/api/v1/orders', [
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 2],
                ],
            ]);

        $response->assertStatus(201);
        $orderId = $response->json('data.id');

        $order = Order::find($orderId);
        $this->assertNotNull($order);
        $this->assertEquals($farm->id, $order->farm_id);
    }

    public function test_multi_farm_requires_selection_and_validates_ownership(): void
    {
        $farmer = User::factory()->create();
        $farmer->assignRole('farmer');

        $farm1 = Farm::factory()->create(['user_id' => $farmer->id, 'farm_name' => 'North Pond']);
        $farm2 = Farm::factory()->create(['user_id' => $farmer->id, 'farm_name' => 'South Pond']);

        $otherFarmer = User::factory()->create();
        $otherFarmer->assignRole('farmer');
        $otherFarm = Farm::factory()->create(['user_id' => $otherFarmer->id]);

        $product = Product::factory()->create(['price' => 100, 'stock' => 50]);

        // 1. Without farm_id -> fails because farmer has 2 farms
        $this->actingAs($farmer, 'sanctum')
            ->postJson('/api/v1/orders', [
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 1],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['farm_id']);

        // 2. With other farmer's farm -> fails
        $this->actingAs($farmer, 'sanctum')
            ->postJson('/api/v1/orders', [
                'farm_id' => $otherFarm->id,
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 1],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['farm_id']);

        // 3. With own farm1 -> succeeds
        $res = $this->actingAs($farmer, 'sanctum')
            ->postJson('/api/v1/orders', [
                'farm_id' => $farm1->id,
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 1],
                ],
            ]);

        $res->assertStatus(201);
        $this->assertEquals($farm1->id, Order::find($res->json('data.id'))->farm_id);
    }

    public function test_farms_list_orders_count_is_scoped_per_farm(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $farmer = User::factory()->create(['name' => 'Tareq Rahman']);
        $farmer->assignRole('farmer');

        $farmA = Farm::factory()->create(['user_id' => $farmer->id, 'farm_name' => 'Farm Alpha']);
        $farmB = Farm::factory()->create(['user_id' => $farmer->id, 'farm_name' => 'Farm Beta']);

        $product = Product::factory()->create(['price' => 200, 'stock' => 100]);

        // Place 2 orders for Farm A
        Order::create(['user_id' => $farmer->id, 'farm_id' => $farmA->id, 'status' => 'pending', 'total' => 400]);
        Order::create(['user_id' => $farmer->id, 'farm_id' => $farmA->id, 'status' => 'confirmed', 'total' => 200]);

        // Place 0 orders for Farm B

        // Verify GET /api/v1/admin/farms
        $farmsResponse = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/farms');

        $farmsResponse->assertStatus(200);
        $farmsData = collect($farmsResponse->json('data'));

        $alpha = $farmsData->firstWhere('id', $farmA->id);
        $beta = $farmsData->firstWhere('id', $farmB->id);

        $this->assertEquals(2, $alpha['orders_count']);
        $this->assertEquals(0, $beta['orders_count']);

        // Verify GET /api/v1/admin/farmers shows total 2 orders for farmer
        $farmersResponse = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/farmers');

        $farmersResponse->assertStatus(200);
        $farmersData = collect($farmersResponse->json('data'));
        $farmerRecord = $farmersData->firstWhere('id', $farmer->id);

        $this->assertEquals(2, $farmerRecord['total_orders']);
    }
}
