<?php

namespace Tests\Feature;

use App\Services\Sms\Contracts\SmsGatewayInterface;
use App\Services\Sms\Gateways\GreenwebGateway;
use App\Services\Sms\Gateways\LogGateway;
use App\Services\Sms\Gateways\MimoSmsGateway;
use App\Services\SmsService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SmsFailoverTest extends TestCase
{
    public function test_primary_log_gateway_delivers_otp_successfully(): void
    {
        Config::set('sms.default', 'log');
        Config::set('sms.fallback', 'log');

        $sms = app(SmsService::class);
        $result = $sms->sendOtp('01712345678', '654321');

        $this->assertTrue($result);
    }

    public function test_failover_triggers_when_primary_gateway_fails(): void
    {
        // Primary is greenweb, fallback is mimosms
        Config::set('sms.default', 'greenweb');
        Config::set('sms.fallback', 'mimosms');
        Config::set('sms.gateways.greenweb.token', 'test-token');
        Config::set('sms.gateways.mimosms.api_key', 'test-api-key');

        // Mock HTTP: Greenweb fails (500 or timeout), MimoSMS succeeds
        Http::fake([
            'https://api.greenweb.com.bd/*' => Http::response('Gateway Error', 500),
            'https://api.mimosms.com/*' => Http::response(['status' => 'success', 'message_id' => 'MSG123'], 200),
        ]);

        $sms = app(SmsService::class);
        $result = $sms->sendOtp('01712345678', '998877');

        $this->assertTrue($result);

        // Verify Greenweb was called and failed
        Http::assertSent(fn ($req) => str_contains($req->url(), 'greenweb.com.bd'));
        // Verify MimoSMS was invoked as fallback
        Http::assertSent(fn ($req) => str_contains($req->url(), 'mimosms.com'));
    }

    public function test_failover_to_log_gateway_when_remote_primary_fails(): void
    {
        // Primary is greenweb, fallback is log
        Config::set('sms.default', 'greenweb');
        Config::set('sms.fallback', 'log');
        Config::set('sms.gateways.greenweb.token', 'test-token');

        Http::fake([
            'https://api.greenweb.com.bd/*' => Http::response('Server Busy', 503),
        ]);

        $sms = app(SmsService::class);
        $result = $sms->sendOtp('01712345678', '112233');

        $this->assertTrue($result);
        Http::assertSent(fn ($req) => str_contains($req->url(), 'greenweb.com.bd'));
    }

    public function test_returns_false_when_all_gateways_fail(): void
    {
        Config::set('sms.default', 'greenweb');
        Config::set('sms.fallback', 'mimosms');
        Config::set('sms.gateways.greenweb.token', 'test-token');
        Config::set('sms.gateways.mimosms.api_key', 'test-api-key');

        // Both gateways fail
        Http::fake([
            'https://api.greenweb.com.bd/*' => Http::response('Gateway Error', 500),
            'https://api.mimosms.com/*' => Http::response(['status' => 'failed', 'error' => 'Out of balance'], 402),
        ]);

        $sms = app(SmsService::class);
        $result = $sms->sendOtp('01712345678', '445566');

        $this->assertFalse($result);
    }
}
