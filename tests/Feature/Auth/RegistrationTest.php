<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('user can register with valid data', function () {
    $response = $this->postJson('/api/auth/register', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'company_name' => 'Acme Corporation',
        'company_slug' => 'acme-corp',
        'timezone' => 'America/New_York',
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'user' => ['id', 'name', 'email', 'tenant_id'],
                'tenant' => ['id', 'name', 'slug', 'settings'],
                'token',
            ],
        ]);

    // Verify user was created
    expect(User::where('email', 'john@example.com')->exists())->toBeTrue();

    // Verify tenant was created
    expect(Tenant::where('slug', 'acme-corp')->exists())->toBeTrue();

    // Verify user has owner role
    $user = User::where('email', 'john@example.com')->first();
    expect($user->hasRole('owner'))->toBeTrue();
});

test('registration generates slug if not provided', function () {
    $response = $this->postJson('/api/auth/register', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'company_name' => 'Beta Company',
    ]);

    $response->assertStatus(201);

    $tenant = Tenant::where('name', 'Beta Company')->first();
    expect($tenant->slug)->toStartWith('beta-company');
});

test('registration fails with invalid email', function () {
    $response = $this->postJson('/api/auth/register', [
        'name' => 'John Doe',
        'email' => 'invalid-email',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'company_name' => 'Acme Corporation',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('registration fails with short password', function () {
    $response = $this->postJson('/api/auth/register', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'short',
        'password_confirmation' => 'short',
        'company_name' => 'Acme Corporation',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});

test('registration fails with mismatched password confirmation', function () {
    $response = $this->postJson('/api/auth/register', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'password123',
        'password_confirmation' => 'different123',
        'company_name' => 'Acme Corporation',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});

test('registration fails with duplicate company slug', function () {
    Tenant::factory()->create(['slug' => 'existing-slug']);

    $response = $this->postJson('/api/auth/register', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'company_name' => 'Acme Corporation',
        'company_slug' => 'existing-slug',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['company_slug']);
});

test('registration fails with invalid slug format', function () {
    $response = $this->postJson('/api/auth/register', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'company_name' => 'Acme Corporation',
        'company_slug' => 'Invalid Slug!',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['company_slug']);
});

test('registration requires all mandatory fields', function () {
    $response = $this->postJson('/api/auth/register', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'email', 'password', 'company_name']);
});
