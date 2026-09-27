<?php

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('a user can login with phone and password', function () {
    $user = User::factory()->farmer()->create([
        'name' => 'Shoeb Farmer',
        'phone' => '01712345678',
        'password' => Hash::make('secret123'),
    ]);

    $response = $this->postJson('/api/v1/login', [
        'phone' => '01712345678',
        'password' => 'secret123',
    ]);

    $response->assertStatus(200)
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
            'message' => 'Login successful',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => 'Shoeb Farmer',
                    'phone' => '01712345678',
                    'roles' => ['farmer'],
                ],
                'token_type' => 'Bearer',
            ],
        ]);
});

test('a user can login with email and password', function () {
    $user = User::factory()->admin()->create([
        'email' => 'admin@farmlink.com',
        'password' => Hash::make('adminpass123'),
    ]);

    $response = $this->postJson('/api/v1/login', [
        'email' => 'admin@farmlink.com',
        'password' => 'adminpass123',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.user.email', 'admin@farmlink.com')
        ->assertJsonPath('data.user.roles', ['admin']);
});

test('a user can login with generic login field using phone or email', function () {
    User::factory()->farmer()->create([
        'phone' => '01811223344',
        'password' => Hash::make('password123'),
    ]);

    $response = $this->postJson('/api/v1/login', [
        'login' => '01811223344',
        'password' => 'password123',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.user.phone', '01811223344');
});

test('it rejects login with invalid password with 401 and generic message', function () {
    User::factory()->create([
        'phone' => '01700000111',
        'password' => Hash::make('correct_password'),
    ]);

    $response = $this->postJson('/api/v1/login', [
        'phone' => '01700000111',
        'password' => 'wrong_password',
    ]);

    $response->assertStatus(401)
        ->assertJson([
            'success' => false,
            'message' => 'Invalid credentials',
        ]);
});

test('it rejects login for non-existent user with 401 and generic message', function () {
    $response = $this->postJson('/api/v1/login', [
        'phone' => '01999999999',
        'password' => 'some_password',
    ]);

    $response->assertStatus(401)
        ->assertJson([
            'success' => false,
            'message' => 'Invalid credentials',
        ]);
});

test('authenticated user can fetch their profile via GET /api/v1/me', function () {
    $user = User::factory()->farmer()->create([
        'name' => 'Current User',
        'phone' => '01755555555',
    ]);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/v1/me');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'id',
                'name',
                'phone',
                'roles',
                'permissions',
                'abilities',
                'farms',
            ],
        ])
        ->assertJson([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => 'Current User',
                'phone' => '01755555555',
                'roles' => ['farmer'],
                'permissions' => [],
                'abilities' => [],
            ],
        ]);
});

test('unauthenticated request to GET /api/v1/me returns 401', function () {
    $this->getJson('/api/v1/me')->assertStatus(401);
});

test('authenticated user can logout and revoke token', function () {
    $user = User::factory()->create([
        'password' => Hash::make('password123'),
    ]);

    $loginResponse = $this->postJson('/api/v1/login', [
        'phone' => $user->phone,
        'password' => 'password123',
    ]);

    $token = $loginResponse->json('data.token');

    // Logout with Bearer token
    $logoutResponse = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/logout');

    $logoutResponse->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Logged out successfully',
        ]);

    // Clear cached guard in test container
    $this->app['auth']->forgetGuards();

    // Subsequent call with the same token should fail
    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/me')
        ->assertStatus(401);
});

test('login is rate limited after too many attempts', function () {
    for ($i = 0; $i < 10; $i++) {
        $this->postJson('/api/v1/login', [
            'phone' => '01799998888',
            'password' => 'badpass',
        ]);
    }

    $response = $this->postJson('/api/v1/login', [
        'phone' => '01799998888',
        'password' => 'badpass',
    ]);

    $response->assertStatus(429);
});
