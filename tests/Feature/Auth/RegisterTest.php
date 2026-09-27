<?php

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('it successfully registers a user with farmer role and zero farms', function () {
    $payload = [
        'name' => 'Shoeb Hossain',
        'phone' => '01712345678',
        'email' => 'shoeb@example.com',
        'password' => 'secret123',
        'district' => 'Satkhira',
        'gender' => 'male',
    ];

    $response = $this->postJson('/api/v1/register', $payload);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'user' => [
                    'id',
                    'name',
                    'phone',
                    'email',
                    'district',
                    'roles',
                    'farms',
                ],
                'token',
                'token_type',
            ],
        ])
        ->assertJson([
            'success' => true,
            'message' => 'User registered successfully',
            'data' => [
                'user' => [
                    'name' => 'Shoeb Hossain',
                    'phone' => '01712345678',
                    'email' => 'shoeb@example.com',
                    'district' => 'Satkhira',
                    'roles' => ['farmer'],
                    'farms' => [],
                ],
                'token_type' => 'Bearer',
            ],
        ]);

    // Assert User exists in database with farmer role and zero farms
    $user = User::where('phone', '01712345678')->first();
    expect($user)->not->toBeNull()
        ->and($user->hasRole('farmer'))->toBeTrue()
        ->and($user->farms)->toHaveCount(0);
});

test('it registers a user with phone only (no email or password)', function () {
    $payload = [
        'name' => 'Abdul Karim',
        'phone' => '01899999999',
        'district' => 'Khulna',
        'gender' => 'male',
    ];

    $response = $this->postJson('/api/v1/register', $payload);

    $response->assertStatus(201)
        ->assertJsonPath('data.user.name', 'Abdul Karim')
        ->assertJsonPath('data.user.phone', '01899999999')
        ->assertJsonPath('data.user.email', null)
        ->assertJsonPath('data.user.roles', ['farmer'])
        ->assertJsonPath('data.user.farms', []);

    $this->assertDatabaseHas('users', [
        'phone' => '01899999999',
        'email' => null,
    ]);
});

test('it validates required fields on registration', function () {
    $response = $this->postJson('/api/v1/register', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'phone']);
});

test('it rejects duplicate phone numbers on registration', function () {
    User::factory()->create(['phone' => '01700000111']);

    $response = $this->postJson('/api/v1/register', [
        'name' => 'Duplicate User',
        'phone' => '01700000111',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['phone']);
});

test('it rejects duplicate email addresses on registration', function () {
    User::factory()->create(['email' => 'existing@example.com']);

    $response = $this->postJson('/api/v1/register', [
        'name' => 'Duplicate Email User',
        'phone' => '01700000222',
        'email' => 'existing@example.com',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('register is rate limited after too many attempts', function () {
    for ($i = 0; $i < 10; $i++) {
        $this->postJson('/api/v1/register', [
            'name' => 'Rate Limit Test',
            'phone' => '0171111'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
        ]);
    }

    $response = $this->postJson('/api/v1/register', [
        'name' => 'Rate Limit Test Exceeded',
        'phone' => '01711119999',
    ]);

    $response->assertStatus(429);
});
