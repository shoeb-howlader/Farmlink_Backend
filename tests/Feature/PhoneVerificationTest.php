<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhoneVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_self_registration_generates_otp_and_sets_unverified(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'name' => 'Rahim Farmer',
            'phone' => '01712345678',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'gender' => 'male',
            'district' => 'Bagerhat',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.requires_phone_verification', true)
            ->assertJsonPath('data.user.phone_verified', false);

        $user = User::where('phone', '01712345678')->first();
        $this->assertNotNull($user);
        $this->assertNull($user->phone_verified_at);
        $this->assertNotNull($user->phone_otp);
        $this->assertEquals(6, strlen($user->phone_otp));
        $this->assertFalse($user->isPhoneVerified());
    }

    public function test_unverified_farmer_cannot_place_order(): void
    {
        $farmer = User::factory()->create([
            'phone_verified_at' => null,
            'phone_otp' => '123456',
        ]);
        $farmer->syncRoles(['farmer']);

        $product = Product::factory()->create([
            'price' => 500,
            'stock' => 20,
        ]);

        $response = $this->actingAs($farmer, 'sanctum')
            ->postJson('/api/v1/orders', [
                'shipping_address' => 'Village 1, Rampal',
                'payment_method' => 'cod',
                'items' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => 2,
                    ],
                ],
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('code', 'PHONE_NOT_VERIFIED');
    }

    public function test_unverified_farmer_cannot_submit_service_request(): void
    {
        $farmer = User::factory()->create([
            'phone_verified_at' => null,
            'phone_otp' => '123456',
        ]);
        $farmer->syncRoles(['farmer']);

        $farm = Farm::factory()->create(['user_id' => $farmer->id]);

        $response = $this->actingAs($farmer, 'sanctum')
            ->postJson("/api/v1/farms/{$farm->id}/service-requests", [
                'type' => 'vet_visit',
                'description' => 'Cow has slight fever and loss of appetite',
                'urgency' => 'medium',
                'preferred_date' => now()->addDays(2)->format('Y-m-d'),
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('code', 'PHONE_NOT_VERIFIED');
    }

    public function test_unverified_farmer_can_still_create_farm_and_update_profile(): void
    {
        $farmer = User::factory()->create([
            'phone_verified_at' => null,
            'phone_otp' => '123456',
        ]);
        $farmer->syncRoles(['farmer']);

        // Farm registration is allowed during unverified state
        $farmResponse = $this->actingAs($farmer, 'sanctum')
            ->postJson('/api/v1/farms', [
                'farm_name' => 'Green Valley Farm',
                'farm_type' => 'Own',
                'total_area' => 5.5,
                'pond_count' => 2,
                'cultivation_area' => 4.5,
                'district' => 'Bagerhat',
                'upazila' => 'Rampal',
                'main_culture_type' => 'Bagda',
                'farming_system' => 'Semi-intensive',
            ]);

        $farmResponse->assertStatus(201);

        // Profile update is also allowed
        $profileResponse = $this->actingAs($farmer, 'sanctum')
            ->patchJson('/api/v1/me', [
                'name' => 'Rahim Updated',
                'phone' => $farmer->phone,
                'gender' => 'male',
                'district' => 'Khulna',
            ]);

        $profileResponse->assertStatus(200);
    }

    public function test_farmer_can_verify_phone_with_valid_otp(): void
    {
        $farmer = User::factory()->create([
            'phone_verified_at' => null,
            'phone_otp' => '456789',
            'phone_otp_expires_at' => now()->addMinutes(10),
        ]);
        $farmer->syncRoles(['farmer']);

        $response = $this->actingAs($farmer, 'sanctum')
            ->postJson('/api/v1/auth/otp/verify', [
                'otp' => '456789',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.verified', true);

        $fresh = $farmer->fresh();
        $this->assertNotNull($fresh->phone_verified_at);
        $this->assertNull($fresh->phone_otp);
        $this->assertTrue($fresh->isPhoneVerified());
    }

    public function test_phone_verification_fails_with_invalid_otp(): void
    {
        $farmer = User::factory()->create([
            'phone_verified_at' => null,
            'phone_otp' => '456789',
            'phone_otp_expires_at' => now()->addMinutes(10),
        ]);
        $farmer->syncRoles(['farmer']);

        $response = $this->actingAs($farmer, 'sanctum')
            ->postJson('/api/v1/auth/otp/verify', [
                'otp' => '000000',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['otp']);

        $this->assertFalse($farmer->fresh()->isPhoneVerified());
    }

    public function test_resend_otp_works_and_enforces_60_second_rate_limiting(): void
    {
        $farmer = User::factory()->create([
            'phone_verified_at' => null,
            'phone_otp' => '111222',
            'phone_otp_sent_at' => now()->subSeconds(70),
        ]);
        $farmer->syncRoles(['farmer']);

        // First resend after cooldown should succeed
        $response = $this->actingAs($farmer, 'sanctum')
            ->postJson('/api/v1/auth/otp/resend');

        $response->assertStatus(200)
            ->assertJsonPath('data.sent', true);

        $newOtp = $farmer->fresh()->phone_otp;
        $this->assertNotNull($newOtp);

        // Immediate second resend must be throttled (HTTP 429)
        $secondResponse = $this->actingAs($farmer, 'sanctum')
            ->postJson('/api/v1/auth/otp/resend');

        $secondResponse->assertStatus(429)
            ->assertJsonPath('success', false);
    }

    public function test_verified_farmer_can_place_order(): void
    {
        $farmer = User::factory()->create([
            'phone_verified_at' => now(),
            'phone_otp' => null,
        ]);
        $farmer->syncRoles(['farmer']);

        $product = Product::factory()->create([
            'price' => 500,
            'stock' => 20,
        ]);

        $response = $this->actingAs($farmer, 'sanctum')
            ->postJson('/api/v1/orders', [
                'shipping_address' => 'Village 1, Rampal',
                'payment_method' => 'cod',
                'items' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => 2,
                    ],
                ],
            ]);

        $response->assertStatus(201);
    }

    public function test_registration_rejects_invalid_phone_formats(): void
    {
        // 10 digits
        $res1 = $this->postJson('/api/v1/register', [
            'name' => 'Invalid Phone 1',
            'phone' => '0171234567',
            'gender' => 'male',
        ]);
        $res1->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);

        // 12 digits
        $res2 = $this->postJson('/api/v1/register', [
            'name' => 'Invalid Phone 2',
            'phone' => '017123456789',
            'gender' => 'male',
        ]);
        $res2->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);

        // Invalid operator prefix
        $res3 = $this->postJson('/api/v1/register', [
            'name' => 'Invalid Phone 3',
            'phone' => '01212345678',
            'gender' => 'male',
        ]);
        $res3->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_registration_normalizes_and_accepts_valid_bangladeshi_numbers(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'name' => 'Normalized Phone Farmer',
            'phone' => '+8801912345678',
            'gender' => 'male',
            'district' => 'Khulna',
        ]);

        $response->assertStatus(201);
        $user = User::where('phone', '01912345678')->first();
        $this->assertNotNull($user);
    }
}
