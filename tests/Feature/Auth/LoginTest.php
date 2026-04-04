<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->tenant = Tenant::factory()->create();
    $this->user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'email' => 'user@example.com',
        'password' => bcrypt('password123'),
    ]);
});

test('user can login with valid credentials', function () {
    $response = $this->postJson('/api/auth/login', [
        'email' => 'user@example.com',
        'password' => 'password123',
        'tenant_id' => $this->tenant->id,
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'user' => ['id', 'name', 'email', 'tenant'],
                'token',
            ],
        ]);

    expect($response->json('data.user.email'))->toBe('user@example.com');
});

test('login fails with invalid password', function () {
    $response = $this->postJson('/api/auth/login', [
        'email' => 'user@example.com',
        'password' => 'wrong-password',
        'tenant_id' => $this->tenant->id,
    ]);

    $response->assertStatus(401)
        ->assertJson([
            'success' => false,
            'message' => 'Invalid credentials',
        ]);
});

test('login fails with non-existent email', function () {
    $response = $this->postJson('/api/auth/login', [
        'email' => 'nonexistent@example.com',
        'password' => 'password123',
        'tenant_id' => $this->tenant->id,
    ]);

    $response->assertStatus(401);
});

test('login fails with wrong tenant', function () {
    $otherTenant = Tenant::factory()->create();

    $response = $this->postJson('/api/auth/login', [
        'email' => 'user@example.com',
        'password' => 'password123',
        'tenant_id' => $otherTenant->id,
    ]);

    $response->assertStatus(401);
});

test('login requires all fields', function () {
    $response = $this->postJson('/api/auth/login', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email', 'password', 'tenant_id']);
});

test('login validates tenant existence', function () {
    $response = $this->postJson('/api/auth/login', [
        'email' => 'user@example.com',
        'password' => 'password123',
        'tenant_id' => 99999,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['tenant_id']);
});

test('authenticated user can logout', function () {
    Sanctum::actingAs($this->user);

    $response = $this->postJson('/api/auth/logout');

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Logged out successfully',
        ]);

    // Verify tokens were deleted
    expect($this->user->tokens()->count())->toBe(0);
});

test('authenticated user can logout from current device only', function () {
    Sanctum::actingAs($this->user);

    // Create additional token for another device
    $this->user->createToken('another-device');

    $response = $this->postJson('/api/auth/logout-current-device');

    $response->assertStatus(200);

    // Verify only current device token was deleted (1 token remains)
    expect($this->user->tokens()->count())->toBe(1);
});

test('authenticated user can get their profile', function () {
    Sanctum::actingAs($this->user);

    $response = $this->getJson('/api/auth/me');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => ['id', 'name', 'email', 'tenant'],
        ])
        ->assertJson([
            'data' => [
                'email' => 'user@example.com',
            ],
        ]);
});

test('unauthenticated user cannot access protected routes', function () {
    $response = $this->getJson('/api/auth/me');

    $response->assertStatus(401);
});

test('logout requires authentication', function () {
    $response = $this->postJson('/api/auth/logout');

    $response->assertStatus(401);
});
