<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Farm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminFarmFarmerEditTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $farmer;
    protected User $otherFarmer;
    protected Farm $farm;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'farmer', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'data_entry_operator', 'guard_name' => 'web']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->farmer = User::factory()->create([
            'name' => 'Original Farmer Name',
            'phone' => '01711000001',
            'district' => 'Satkhira',
            'gender' => 'male',
            'email' => 'original@farmlink.com',
            'is_active' => true,
        ]);
        $this->farmer->assignRole('farmer');

        $this->otherFarmer = User::factory()->create([
            'phone' => '01711000002',
            'email' => 'other@farmlink.com',
        ]);
        $this->otherFarmer->assignRole('farmer');

        $this->farm = Farm::factory()->create([
            'user_id' => $this->farmer->id,
            'farm_name' => 'Old Shrimp Farm',
            'district' => 'Satkhira',
            'upazila' => 'Debhata',
            'total_area' => 150.00,
            'pond_count' => 3,
            'main_culture_type' => 'Bagda',
            'farming_system' => 'Extensive',
        ]);
    }

    public function test_admin_can_update_farm_details(): void
    {
        Sanctum::actingAs($this->admin);

        $payload = [
            'farm_name' => 'Updated Mega Aqua Farm',
            'district' => 'Khulna',
            'upazila' => 'Paikgachha',
            'total_area' => 250.50,
            'pond_count' => 6,
            'main_culture_type' => 'Golda',
            'farming_system' => 'Semi-intensive',
        ];

        $response = $this->putJson("/api/v1/admin/farms/{$this->farm->id}", $payload);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Farm updated successfully',
            ])
            ->assertJsonPath('data.farm_name', 'Updated Mega Aqua Farm')
            ->assertJsonPath('data.district', 'Khulna')
            ->assertJsonPath('data.pond_count', 6);

        $this->assertDatabaseHas('farms', [
            'id' => $this->farm->id,
            'farm_name' => 'Updated Mega Aqua Farm',
            'district' => 'Khulna',
            'pond_count' => 6,
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'farm.updated',
            'subject_id' => $this->farm->id,
        ]);
    }

    public function test_non_admin_cannot_update_admin_farm(): void
    {
        Sanctum::actingAs($this->otherFarmer);

        $response = $this->putJson("/api/v1/admin/farms/{$this->farm->id}", [
            'farm_name' => 'Hacked Farm',
        ]);

        $response->assertForbidden();
    }

    public function test_admin_can_update_farmer_details(): void
    {
        Sanctum::actingAs($this->admin);

        $payload = [
            'name' => 'Updated Farmer Name',
            'phone' => '01711999999',
            'district' => 'Bagerhat',
            'gender' => 'female',
            'email' => 'updated.farmer@farmlink.com',
            'is_active' => false,
            'password' => 'newsecretpassword123',
        ];

        $response = $this->putJson("/api/v1/admin/farmers/{$this->farmer->id}", $payload);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Farmer updated successfully',
            ])
            ->assertJsonPath('data.name', 'Updated Farmer Name')
            ->assertJsonPath('data.phone', '01711999999')
            ->assertJsonPath('data.district', 'Bagerhat')
            ->assertJsonPath('data.gender', 'female')
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('users', [
            'id' => $this->farmer->id,
            'name' => 'Updated Farmer Name',
            'phone' => '01711999999',
            'district' => 'Bagerhat',
            'gender' => 'female',
            'is_active' => false,
        ]);

        $this->farmer->refresh();
        $this->assertTrue(Hash::check('newsecretpassword123', $this->farmer->password));

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'update_farmer',
            'subject_id' => $this->farmer->id,
        ]);
    }

    public function test_non_admin_cannot_update_admin_farmer(): void
    {
        Sanctum::actingAs($this->otherFarmer);

        $response = $this->putJson("/api/v1/admin/farmers/{$this->farmer->id}", [
            'name' => 'Unauthorized Change',
        ]);

        $response->assertForbidden();
    }

    public function test_admin_can_upload_farmer_avatar(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        Sanctum::actingAs($this->admin);

        $file = \Illuminate\Http\UploadedFile::fake()->image('farmer_avatar.jpg', 300, 300);

        $response = $this->postJson("/api/v1/admin/farmers/{$this->farmer->id}/avatar", [
            'image' => $file,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Farmer profile picture updated successfully',
            ])
            ->assertJsonStructure([
                'data' => ['id', 'name', 'avatar_url', 'avatar_thumbnail_url', 'profile_image_path', 'profile_image_thumbnail_path']
            ]);

        $this->farmer->refresh();
        $this->assertNotNull($this->farmer->profile_image_path);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($this->farmer->profile_image_path);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'update_farmer_avatar',
            'subject_id' => $this->farmer->id,
        ]);
    }

    public function test_admin_can_remove_farmer_avatar(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        Sanctum::actingAs($this->admin);

        // Upload first
        $file = \Illuminate\Http\UploadedFile::fake()->image('farmer_avatar.jpg', 300, 300);
        $this->postJson("/api/v1/admin/farmers/{$this->farmer->id}/avatar", [
            'image' => $file,
        ]);

        $this->farmer->refresh();
        $this->assertNotNull($this->farmer->profile_image_path);

        // Now remove
        $response = $this->deleteJson("/api/v1/admin/farmers/{$this->farmer->id}/avatar");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Farmer profile picture removed successfully',
            ]);

        $this->farmer->refresh();
        $this->assertNull($this->farmer->profile_image_path);
        $this->assertNull($this->farmer->avatar_url);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'remove_farmer_avatar',
            'subject_id' => $this->farmer->id,
        ]);
    }

    public function test_admin_can_upload_multiple_farm_gallery_photos(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        Sanctum::actingAs($this->admin);

        $file1 = \Illuminate\Http\UploadedFile::fake()->image('pond1.jpg', 600, 400);
        $file2 = \Illuminate\Http\UploadedFile::fake()->image('aerator.jpg', 600, 400);

        $response = $this->postJson("/api/v1/admin/farms/{$this->farm->id}/images", [
            'images' => [$file1, $file2],
            'caption' => 'Nursery Pond & Paddle Aerator',
            'category' => 'pond',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Farm photos uploaded successfully',
            ])
            ->assertJsonCount(2, 'data.uploaded');

        $this->assertDatabaseCount('farm_images', 2);
        $this->assertDatabaseHas('farm_images', [
            'farm_id' => $this->farm->id,
            'caption' => 'Nursery Pond & Paddle Aerator',
            'category' => 'pond',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'farm.gallery_images_uploaded',
            'subject_id' => $this->farm->id,
        ]);
    }

    public function test_admin_can_set_primary_farm_cover_photo(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        Sanctum::actingAs($this->admin);

        $img1 = \App\Models\FarmImage::create([
            'farm_id' => $this->farm->id,
            'image_path' => 'farms/img1.webp',
            'image_thumbnail_path' => 'farms/thumbnails/img1.webp',
            'image_url' => 'http://localhost/storage/farms/img1.webp',
            'caption' => 'First Photo',
            'is_primary' => true,
        ]);

        $img2 = \App\Models\FarmImage::create([
            'farm_id' => $this->farm->id,
            'image_path' => 'farms/img2.webp',
            'image_thumbnail_path' => 'farms/thumbnails/img2.webp',
            'image_url' => 'http://localhost/storage/farms/img2.webp',
            'caption' => 'Second Photo',
            'is_primary' => false,
        ]);

        $response = $this->patchJson("/api/v1/admin/farms/{$this->farm->id}/images/{$img2->id}/primary");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Cover photo updated successfully',
            ]);

        $img1->refresh();
        $img2->refresh();
        $this->assertFalse($img1->is_primary);
        $this->assertTrue($img2->is_primary);

        $this->farm->refresh();
        $this->assertEquals('farms/img2.webp', $this->farm->image_path);
    }

    public function test_admin_can_delete_farm_gallery_photo(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        Sanctum::actingAs($this->admin);

        $img = \App\Models\FarmImage::create([
            'farm_id' => $this->farm->id,
            'image_path' => 'farms/delete_me.webp',
            'image_thumbnail_path' => 'farms/thumbnails/delete_me.webp',
            'image_url' => 'http://localhost/storage/farms/delete_me.webp',
            'is_primary' => false,
        ]);

        $response = $this->deleteJson("/api/v1/admin/farms/{$this->farm->id}/images/{$img->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Farm photo deleted successfully',
            ]);

        $this->assertDatabaseMissing('farm_images', [
            'id' => $img->id,
        ]);
    }
}
