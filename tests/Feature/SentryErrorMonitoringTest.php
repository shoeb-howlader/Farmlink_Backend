<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Services\SentryEventScrubber;
use App\Services\SSLCommerzService;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Event;
use Sentry\Event as SentryEvent;
use Sentry\EventHint;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SentryErrorMonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_sentry_scrubber_redacts_passwords_tokens_and_payment_secrets(): void
    {
        $payload = [
            'username' => 'testuser',
            'password' => 'SuperSecret123!',
            'password_confirmation' => 'SuperSecret123!',
            'otp' => '984512',
            'token' => 'plain_text_token_xyz',
            'val_id' => 'val_987654321',
            'store_passwd' => 'sslcommerz_store_pass',
            'nested' => [
                'card_number' => '4111222233334444',
                'cvv' => '123',
                'api_key' => 'secret_api_key_abc',
                'safe_field' => 'visible_value',
            ],
        ];

        $scrubbed = SentryEventScrubber::redactSensitiveData($payload);

        $this->assertEquals('[FILTERED]', $scrubbed['password']);
        $this->assertEquals('[FILTERED]', $scrubbed['password_confirmation']);
        $this->assertEquals('[FILTERED]', $scrubbed['otp']);
        $this->assertEquals('[FILTERED]', $scrubbed['token']);
        $this->assertEquals('[FILTERED]', $scrubbed['val_id']);
        $this->assertEquals('[FILTERED]', $scrubbed['store_passwd']);
        $this->assertEquals('[FILTERED]', $scrubbed['nested']['card_number']);
        $this->assertEquals('[FILTERED]', $scrubbed['nested']['cvv']);
        $this->assertEquals('[FILTERED]', $scrubbed['nested']['api_key']);
        $this->assertEquals('visible_value', $scrubbed['nested']['safe_field']);
        $this->assertEquals('testuser', $scrubbed['username']);
    }

    public function test_sentry_scrubber_attaches_only_user_id_and_role_without_pii(): void
    {
        Role::firstOrCreate(['name' => 'farmer', 'guard_name' => 'web']);

        $user = User::factory()->create([
            'name' => 'Md. Rafiqul Islam',
            'email' => 'rafiq@farmlink.com',
            'phone' => '01711223344',
        ]);
        $user->assignRole('farmer');

        $this->actingAs($user);

        $sentryEvent = SentryEvent::createEvent();
        $sentryEvent->setRequest([
            'data' => [
                'password' => 'secret_pass',
                'amount' => 500,
            ],
        ]);

        $scrubbedEvent = SentryEventScrubber::scrub($sentryEvent, new EventHint());

        $userData = $scrubbedEvent->getUser();
        $this->assertNotNull($userData);
        $this->assertEquals((string) $user->id, $userData->getId());
        $this->assertNull($userData->getEmail());
        $this->assertNull($userData->getUsername());
        $this->assertNull($userData->getIpAddress());
        $this->assertEquals('farmer', $userData->getMetadata()['role'] ?? null);

        $requestData = $scrubbedEvent->getRequest();
        $this->assertEquals('[FILTERED]', $requestData['data']['password']);
        $this->assertEquals(500, $requestData['data']['amount']);
    }

    public function test_ipn_handler_catches_unhandled_exception_and_returns_clean_response(): void
    {
        $mockSsl = $this->mock(SSLCommerzService::class);
        $mockSsl->shouldReceive('validateTransaction')
            ->andThrow(new \RuntimeException('SSLCommerz gateway socket timeout'));

        $response = $this->postJson('/api/v1/payments/sslcommerz/ipn', [
            'val_id' => 'VAL_TEST_123',
            'tran_id' => 'TXN_TEST_456',
        ]);

        // Clean failure response without leaking internal PHP trace
        $this->assertContains($response->status(), [422, 500]);
        $response->assertJson([
            'success' => false,
        ]);
    }
}
