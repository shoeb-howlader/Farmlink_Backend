<?php

namespace Tests\Feature;

use App\Http\Resources\V1\OrderResource;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\Order;
use App\Models\User;
use App\Services\RichTextSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SecurityAuditFixesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'farmer', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'data_entry_operator', 'guard_name' => 'web']);
    }

    public function test_payment_status_endpoint_rejects_unauthenticated_requests(): void
    {
        $farmer = User::factory()->create();
        $farmer->assignRole('farmer');

        $order = Order::factory()->create([
            'user_id' => $farmer->id,
            'total' => 500,
            'status' => 'pending_payment',
            'payment_status' => 'pending',
        ]);

        // Unauthenticated access must be rejected with 401
        $response = $this->getJson("/api/v1/payments/sslcommerz/status/{$order->id}");
        $response->assertStatus(401);
    }

    public function test_payment_status_endpoint_prevents_idor(): void
    {
        $victim = User::factory()->create();
        $victim->assignRole('farmer');

        $attacker = User::factory()->create();
        $attacker->assignRole('farmer');

        $victimOrder = Order::factory()->create([
            'user_id' => $victim->id,
            'total' => 1200,
            'status' => 'pending_payment',
            'payment_status' => 'pending',
        ]);

        // Attacker attempting to view victim's order status must receive 403 Forbidden
        $response = $this->actingAs($attacker, 'sanctum')
            ->getJson("/api/v1/payments/sslcommerz/status/{$victimOrder->id}");

        $response->assertStatus(403);

        // Owner can access successfully
        $ownerResponse = $this->actingAs($victim, 'sanctum')
            ->getJson("/api/v1/payments/sslcommerz/status/{$victimOrder->id}");

        $ownerResponse->assertStatus(200)
            ->assertJsonPath('data.id', $victimOrder->id);
    }

    public function test_deo_cannot_modify_farmer_credentials_or_restrictions(): void
    {
        $deo = User::factory()->create();
        $deo->assignRole('data_entry_operator');

        $farmer = User::factory()->create([
            'name' => 'Original Farmer',
            'is_blacklisted' => false,
        ]);
        $farmer->assignRole('farmer');

        // DEO attempting to alter farmer profile / password must get 403 Forbidden
        $updateResponse = $this->actingAs($deo, 'sanctum')
            ->putJson("/api/v1/admin/farmers/{$farmer->id}", [
                'name' => 'Hacked Farmer',
                'password' => 'newpassword123',
            ]);

        $updateResponse->assertStatus(403);

        // DEO attempting to ban / blacklist farmer must get 403 Forbidden
        $restrictionResponse = $this->actingAs($deo, 'sanctum')
            ->patchJson("/api/v1/admin/farmers/{$farmer->id}/restriction", [
                'is_blacklisted' => true,
                'blacklist_reason' => 'Unauthorized DEO ban',
            ]);

        $restrictionResponse->assertStatus(403);

        // Ensure farmer was not modified in database
        $farmer->refresh();
        $this->assertEquals('Original Farmer', $farmer->name);
        $this->assertFalse((bool) $farmer->is_blacklisted);
    }

    public function test_admin_can_update_farmer_and_restrictions(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $farmer = User::factory()->create([
            'name' => 'Honest Farmer',
            'is_blacklisted' => false,
        ]);
        $farmer->assignRole('farmer');

        $restrictionResponse = $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/admin/farmers/{$farmer->id}/restriction", [
                'is_blacklisted' => true,
                'blacklist_reason' => 'Admin verified fraudulent activity',
            ]);

        $restrictionResponse->assertStatus(200);

        $farmer->refresh();
        $this->assertTrue((bool) $farmer->is_blacklisted);
    }

    public function test_otp_verification_brute_force_lockout(): void
    {
        $farmer = User::factory()->create([
            'phone' => '01700000001',
            'phone_otp' => '123456',
            'phone_otp_expires_at' => now()->addMinutes(10),
            'phone_verified_at' => null,
        ]);
        $farmer->assignRole('farmer');

        Cache::forget("otp_verify_attempts_{$farmer->id}");

        // Attempt 5 incorrect guesses without route-level throttle to test controller lockout logic
        for ($i = 1; $i <= 5; $i++) {
            $response = $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class)
                ->actingAs($farmer, 'sanctum')
                ->postJson('/api/v1/auth/otp/verify', [
                    'otp' => '999999',
                ]);
            $response->assertStatus(422);
        }

        // 6th attempt must trigger controller lockout and invalidate code
        $lockoutResponse = $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class)
            ->actingAs($farmer, 'sanctum')
            ->postJson('/api/v1/auth/otp/verify', [
                'otp' => '123456', // Even with correct code now, it must be invalidated
            ]);

        $lockoutResponse->assertStatus(422)
            ->assertJsonValidationErrors(['otp']);

        $farmer->refresh();
        $this->assertNull($farmer->phone_otp);
    }

    public function test_otp_verification_route_throttle(): void
    {
        $farmer = User::factory()->create([
            'phone' => '01700000002',
            'phone_otp' => '654321',
            'phone_otp_expires_at' => now()->addMinutes(10),
            'phone_verified_at' => null,
        ]);
        $farmer->assignRole('farmer');

        // Route rate limiter allows 5 requests per minute
        for ($i = 1; $i <= 5; $i++) {
            $this->actingAs($farmer, 'sanctum')
                ->postJson('/api/v1/auth/otp/verify', ['otp' => '000000']);
        }

        // 6th request is throttled with HTTP 429
        $throttledResponse = $this->actingAs($farmer, 'sanctum')
            ->postJson('/api/v1/auth/otp/verify', ['otp' => '000000']);

        $throttledResponse->assertStatus(429);
    }

    public function test_chat_attachment_stream_security(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('chat_attachments/sample.pdf', '%PDF-1.4 test');
        Storage::disk('public')->put('secret_docs/internal.pdf', 'CONFIDENTIAL');

        // 1. Unauthenticated access rejected
        $guestResponse = $this->getJson('/api/v1/chat/attachments/stream?path=chat_attachments/sample.pdf');
        $guestResponse->assertStatus(401);

        $farmer = User::factory()->create();
        $farmer->assignRole('farmer');

        // 2. Traversal attempt rejected
        $traversalResponse = $this->actingAs($farmer, 'sanctum')
            ->getJson('/api/v1/chat/attachments/stream?path=chat_attachments/../secret_docs/internal.pdf');
        $traversalResponse->assertStatus(400);

        // 3. Path outside chat_attachments rejected
        $unauthorizedPathResponse = $this->actingAs($farmer, 'sanctum')
            ->getJson('/api/v1/chat/attachments/stream?path=secret_docs/internal.pdf');
        $unauthorizedPathResponse->assertStatus(403);
    }

    public function test_rich_text_sanitizer_neutralizes_xss_vectors(): void
    {
        $vectors = [
            '<img/src=x/onerror=alert(1)>' => '',
            '<a href="javascript:alert(1)">click</a>' => '<a>click</a>',
            '<a href=javascript:alert(1)>click</a>' => '<a>click</a>',
            '<a onmouseover="alert(1)">hover</a>' => '<a>hover</a>',
            '<a/onmouseover=alert(1)>hover</a>' => '<a>hover</a>',
            '<img src="data:image/svg+xml;base64,abc">' => '',
        ];

        foreach ($vectors as $dirty => $expected) {
            $cleaned = RichTextSanitizer::sanitize($dirty);
            $this->assertEquals($expected, $cleaned, "Failed for payload: {$dirty}");
        }

        // Verify valid tags and safe external links are preserved
        $validHtml = '<p>Healthy harvest <a href="https://farmlinkcare.com">Read More</a></p>';
        $cleanedValid = RichTextSanitizer::sanitize($validHtml);
        $this->assertStringContainsString('https://farmlinkcare.com', $cleanedValid);
        $this->assertStringContainsString('rel="noopener noreferrer"', $cleanedValid);
    }

    public function test_order_resource_hides_fraud_heuristics_from_farmers(): void
    {
        $farmer = User::factory()->create();
        $farmer->assignRole('farmer');

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $order = Order::factory()->create([
            'user_id' => $farmer->id,
            'total' => 1500,
            'risk_score' => 85,
            'risk_level' => 'high',
            'is_flagged' => true,
            'flag_reasons' => ['rapid_high_value_order'],
            'verification_status' => 'verified_call',
            'ip_address' => '192.168.1.100',
        ]);

        // Request as farmer: resolved payload must strip fraud heuristics
        $farmerRequest = Request::create("/api/v1/orders/{$order->id}", 'GET');
        $farmerRequest->setUserResolver(fn () => $farmer);
        $farmerData = (new OrderResource($order))->resolve($farmerRequest);

        $this->assertArrayNotHasKey('risk_score', $farmerData);
        $this->assertArrayNotHasKey('risk_level', $farmerData);
        $this->assertArrayNotHasKey('is_flagged', $farmerData);
        $this->assertArrayNotHasKey('flag_reasons', $farmerData);
        $this->assertArrayNotHasKey('verification_status', $farmerData);
        $this->assertArrayNotHasKey('ip_address', $farmerData);

        // Request as admin: resolved payload must include fraud heuristics
        $adminRequest = Request::create("/api/v1/orders/{$order->id}", 'GET');
        $adminRequest->setUserResolver(fn () => $admin);
        $adminData = (new OrderResource($order))->resolve($adminRequest);

        $this->assertArrayHasKey('risk_score', $adminData);
        $this->assertEquals(85, $adminData['risk_score']);
        $this->assertEquals('high', $adminData['risk_level']);
        $this->assertTrue($adminData['is_flagged']);
        $this->assertEquals('192.168.1.100', $adminData['ip_address']);
    }
}
