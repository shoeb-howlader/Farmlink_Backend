<?php

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('a user can request a password reset OTP using a phone number', function () {
    $user = User::factory()->farmer()->create([
        'phone' => '01712345678',
        'phone_otp' => null,
        'phone_otp_sent_at' => null,
    ]);

    $response = $this->postJson('/api/v1/auth/forgot-password', [
        'login' => '01712345678',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'data' => [
                'sent' => true,
                'channel' => 'phone',
            ],
        ]);

    $user->refresh();
    expect($user->phone_otp)->not->toBeNull()
        ->and(strlen($user->phone_otp))->toBe(6)
        ->and($user->phone_otp_expires_at)->not->toBeNull();
});

test('a user can request a password reset OTP using an email address', function () {
    \Illuminate\Support\Facades\Mail::fake();

    $user = User::factory()->admin()->create([
        'email' => 'admin@farmlink.com',
        'phone_otp' => null,
    ]);

    $response = $this->postJson('/api/v1/auth/forgot-password', [
        'login' => 'admin@farmlink.com',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'data' => [
                'sent' => true,
                'channel' => 'email',
            ],
        ]);

    $user->refresh();
    expect($user->phone_otp)->not->toBeNull()
        ->and(strlen($user->phone_otp))->toBe(6);

    \Illuminate\Support\Facades\Mail::assertQueued(\App\Mail\PasswordResetMail::class, function ($mail) use ($user) {
        return $mail->hasTo('admin@farmlink.com')
            && $mail->user->id === $user->id
            && ! empty($mail->token)
            && $mail->otp === $user->phone_otp;
    });
});

test('requesting password reset for non-existent credential returns safe generic success (anti-enumeration)', function () {
    $response = $this->postJson('/api/v1/auth/forgot-password', [
        'login' => '01999999999',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'data' => [
                'sent' => true,
            ],
        ]);
});

test('requesting password reset too quickly triggers cooldown validation error', function () {
    $user = User::factory()->farmer()->create([
        'phone' => '01712345678',
        'phone_otp' => '123456',
        'phone_otp_sent_at' => now()->subSeconds(20),
    ]);

    $response = $this->postJson('/api/v1/auth/forgot-password', [
        'login' => '01712345678',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['login']);
});

test('a user can reset their password with a valid OTP', function () {
    $user = User::factory()->farmer()->create([
        'phone' => '01712345678',
        'password' => Hash::make('oldpassword123'),
        'phone_otp' => '654321',
        'phone_otp_expires_at' => now()->addMinutes(10),
    ]);

    // Give user an existing token to verify token invalidation
    $user->createToken('old_device_token');
    expect($user->tokens()->count())->toBe(1);

    $response = $this->postJson('/api/v1/auth/reset-password', [
        'login' => '01712345678',
        'otp' => '654321',
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Password has been reset successfully. You are now logged in.',
        ])
        ->assertJsonStructure([
            'data' => [
                'user',
                'token',
                'token_type',
            ],
        ]);

    $user->refresh();
    expect(Hash::check('newpassword123', $user->password))->toBeTrue()
        ->and($user->phone_otp)->toBeNull()
        ->and($user->phone_otp_expires_at)->toBeNull();

    // Verify user can now log in with the new password
    $loginResponse = $this->postJson('/api/v1/login', [
        'phone' => '01712345678',
        'password' => 'newpassword123',
    ]);
    $loginResponse->assertStatus(200);
});

test('resetting password with invalid OTP fails validation', function () {
    User::factory()->farmer()->create([
        'phone' => '01712345678',
        'phone_otp' => '654321',
        'phone_otp_expires_at' => now()->addMinutes(10),
    ]);

    $response = $this->postJson('/api/v1/auth/reset-password', [
        'login' => '01712345678',
        'otp' => '000000',
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['otp']);
});

test('resetting password with expired OTP fails validation', function () {
    User::factory()->farmer()->create([
        'phone' => '01712345678',
        'phone_otp' => '654321',
        'phone_otp_expires_at' => now()->subMinute(),
    ]);

    $response = $this->postJson('/api/v1/auth/reset-password', [
        'login' => '01712345678',
        'otp' => '654321',
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['otp']);
});

test('a user can reset password using a valid email broker token', function () {
    $user = User::factory()->admin()->create([
        'email' => 'admin@farmlink.com',
        'password' => Hash::make('oldadminpass'),
    ]);

    $user->createToken('old_session_token');
    expect($user->tokens()->count())->toBe(1);

    $token = \Illuminate\Support\Facades\Password::broker()->createToken($user);

    $response = $this->postJson('/api/v1/auth/reset-password-with-token', [
        'token' => $token,
        'email' => 'admin@farmlink.com',
        'password' => 'newadminpassword123',
        'password_confirmation' => 'newadminpassword123',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Password has been reset successfully. You are now logged in.',
        ])
        ->assertJsonStructure([
            'data' => [
                'user',
                'token',
                'token_type',
            ],
        ]);

    $user->refresh();
    expect(Hash::check('newadminpassword123', $user->password))->toBeTrue();
});

test('resetting password with invalid broker token fails validation', function () {
    $user = User::factory()->admin()->create([
        'email' => 'admin@farmlink.com',
    ]);

    $response = $this->postJson('/api/v1/auth/reset-password-with-token', [
        'token' => 'invalid-token-string',
        'email' => 'admin@farmlink.com',
        'password' => 'newadminpassword123',
        'password_confirmation' => 'newadminpassword123',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});
