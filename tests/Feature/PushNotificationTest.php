<?php

namespace Tests\Feature;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PushNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_retrieve_vapid_public_key(): void
    {
        config(['webpush.vapid.public_key' => 'test-public-key-123']);
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/push/vapid-key');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'public_key' => 'test-public-key-123',
            ],
        ]);
    }

    public function test_authenticated_user_can_subscribe_to_push_notifications(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/push/subscribe', [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/sample-device-token-123',
            'keys' => [
                'p256dh' => 'sample-p256dh-key',
                'auth' => 'sample-auth-secret',
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $user->id,
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/sample-device-token-123',
            'p256dh' => 'sample-p256dh-key',
            'auth' => 'sample-auth-secret',
        ]);
    }

    public function test_authenticated_user_can_unsubscribe_from_push(): void
    {
        $user = User::factory()->create();

        PushSubscription::create([
            'user_id' => $user->id,
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/sample-device-token-123',
            'p256dh' => 'sample-p256dh-key',
            'auth' => 'sample-auth-secret',
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/push/unsubscribe', [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/sample-device-token-123',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertDatabaseMissing('push_subscriptions', [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/sample-device-token-123',
        ]);
    }

    public function test_test_push_endpoint_executes_safely_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/push/test');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Test push notification dispatched',
        ]);
    }
}
