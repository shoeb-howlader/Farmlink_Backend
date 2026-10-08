<?php

namespace Tests\Feature;

use App\Mail\CriticalSystemAlertMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnvironmentSafetyAndConfigTest extends TestCase
{
    use RefreshDatabase;
    public function test_production_safety_check_detects_app_debug_true_in_production(): void
    {
        Mail::fake();

        // Simulate production environment
        $this->app['env'] = 'production';
        config(['app.debug' => true]);
        config(['sslcommerz.is_sandbox' => false]);

        $admin = \App\Models\User::factory()->create([
            'email' => 'admin_sec_test@farmlink.com',
            'is_active' => true,
        ]);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->assignRole('admin');

        // Re-boot provider logic
        $provider = new \App\Providers\AppServiceProvider($this->app);
        $provider->boot();

        Mail::assertQueued(CriticalSystemAlertMail::class, function (CriticalSystemAlertMail $mail) {
            return str_contains($mail->title, 'Production Security Hazard');
        });
    }

    public function test_production_safety_check_detects_sslcommerz_sandbox_in_production(): void
    {
        Mail::fake();

        $this->app['env'] = 'production';
        config(['app.debug' => false]);
        config(['sslcommerz.is_sandbox' => true]);

        $admin = \App\Models\User::factory()->create([
            'email' => 'admin_pay_test@farmlink.com',
            'is_active' => true,
        ]);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->assignRole('admin');

        $provider = new \App\Providers\AppServiceProvider($this->app);
        $provider->boot();

        Mail::assertQueued(CriticalSystemAlertMail::class, function (CriticalSystemAlertMail $mail) {
            return str_contains($mail->title, 'Production Payment Hazard');
        });
    }

    public function test_cors_and_reverb_allowed_origins_are_dynamically_configured(): void
    {
        $corsOrigins = config('cors.allowed_origins');
        $this->assertIsArray($corsOrigins);
        $this->assertNotEmpty($corsOrigins);

        $reverbOrigins = config('reverb.apps.apps.0.allowed_origins');
        $this->assertIsArray($reverbOrigins);
        $this->assertNotEmpty($reverbOrigins);
    }
}
