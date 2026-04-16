<?php

declare(strict_types=1);

use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);

    $this->tenant = Tenant::factory()->create();
    $this->owner = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'email' => 'owner@example.com',
        'password' => bcrypt('password123'),
    ]);
    $this->owner->assignRole('owner');

    Sanctum::actingAs($this->owner);
});

test('owner can invite a user to the tenant', function () {
    $response = $this->postJson('/api/invitations', [
        'email' => 'newuser@example.com',
        'role' => 'member',
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => ['id', 'email', 'token', 'role', 'expires_at'],
        ]);

    expect(Invitation::where('email', 'newuser@example.com')->exists())->toBeTrue();
});

test('cannot invite a user that already exists in the tenant', function () {
    User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'email' => 'existing@example.com',
    ]);

    $response = $this->postJson('/api/invitations', [
        'email' => 'existing@example.com',
        'role' => 'member',
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'message' => 'A user with this email already exists in the system.',
        ]);
});

test('cannot send duplicate invitations', function () {
    $this->postJson('/api/invitations', [
        'email' => 'duplicate@example.com',
        'role' => 'member',
    ]);

    $response = $this->postJson('/api/invitations', [
        'email' => 'duplicate@example.com',
        'role' => 'member',
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'message' => 'An invitation has already been sent to this email.',
        ]);
});

test('can list pending invitations', function () {
    Invitation::create([
        'tenant_id' => $this->tenant->id,
        'email' => 'pending@example.com',
        'token' => 'test-token-1',
        'invited_by' => $this->owner->id,
        'role' => 'member',
        'expires_at' => now()->addDays(7),
    ]);

    $response = $this->getJson('/api/invitations');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [['id', 'email', 'role', 'expires_at']],
        ]);
});

test('can cancel an invitation', function () {
    $invitation = Invitation::create([
        'tenant_id' => $this->tenant->id,
        'email' => 'cancel@example.com',
        'token' => 'test-token-2',
        'invited_by' => $this->owner->id,
        'role' => 'member',
        'expires_at' => now()->addDays(7),
    ]);

    $response = $this->deleteJson('/api/invitations/' . $invitation->id);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Invitation cancelled successfully',
        ]);

    expect(Invitation::find($invitation->id))->toBeNull();
});

test('can accept an invitation and create account', function () {
    $invitation = Invitation::create([
        'tenant_id' => $this->tenant->id,
        'email' => 'accept@example.com',
        'token' => 'valid-token',
        'invited_by' => $this->owner->id,
        'role' => 'admin',
        'expires_at' => now()->addDays(7),
    ]);

    $response = $this->postJson('/api/invitations/accept', [
        'token' => 'valid-token',
        'name' => 'New User',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'user' => ['id', 'name', 'email', 'tenant_id'],
                'token',
            ],
        ]);

    $user = User::where('email', 'accept@example.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->tenant_id)->toBe($this->tenant->id)
        ->and($user->hasRole('admin'))->toBeTrue();

    $invitation->refresh();
    expect($invitation->accepted_at)->not->toBeNull();
});

test('cannot accept invitation if email was registered after invite', function () {
    $invitation = Invitation::create([
        'tenant_id' => $this->tenant->id,
        'email' => 'race@example.com',
        'token' => 'race-token',
        'invited_by' => $this->owner->id,
        'role' => 'member',
        'expires_at' => now()->addDays(7),
    ]);

    $otherTenant = Tenant::factory()->create();
    User::factory()->create([
        'tenant_id' => $otherTenant->id,
        'email' => 'race@example.com',
    ]);

    $response = $this->postJson('/api/invitations/accept', [
        'token' => 'race-token',
        'name' => 'Race User',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'message' => 'A user with this email already exists in the system.',
        ]);
});

test('cannot accept expired invitation', function () {
    $invitation = Invitation::create([
        'tenant_id' => $this->tenant->id,
        'email' => 'expired@example.com',
        'token' => 'expired-token',
        'invited_by' => $this->owner->id,
        'role' => 'member',
        'expires_at' => now()->subDays(1),
    ]);

    $response = $this->postJson('/api/invitations/accept', [
        'token' => 'expired-token',
        'name' => 'New User',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'message' => 'This invitation has expired or has already been used.',
        ]);
});

test('cannot accept already used invitation', function () {
    $invitation = Invitation::create([
        'tenant_id' => $this->tenant->id,
        'email' => 'used@example.com',
        'token' => 'used-token',
        'invited_by' => $this->owner->id,
        'role' => 'member',
        'expires_at' => now()->addDays(7),
        'accepted_at' => now(),
    ]);

    $response = $this->postJson('/api/invitations/accept', [
        'token' => 'used-token',
        'name' => 'New User',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertStatus(422);
});

test('invitation requires valid email format', function () {
    $response = $this->postJson('/api/invitations', [
        'email' => 'invalid-email',
        'role' => 'member',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('invitation role must be valid', function () {
    $response = $this->postJson('/api/invitations', [
        'email' => 'test@example.com',
        'role' => 'invalid-role',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['role']);
});
