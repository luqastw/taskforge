<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Notifications\OwnershipTransferredNotification;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->tenant = Tenant::factory()->create();
    $this->owner = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'email' => 'owner@example.com',
        'password' => bcrypt('password123'),
        'is_active' => true,
    ]);
    $this->owner->assignRole('owner');

    $this->member = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'email' => 'member@example.com',
        'password' => bcrypt('password123'),
        'is_active' => true,
    ]);
    $this->member->assignRole('member');

    Sanctum::actingAs($this->owner);
});

test('owner can transfer ownership to another user', function () {
    Notification::fake();

    $response = $this->postJson('/api/tenant/transfer-ownership', [
        'user_id' => $this->member->id,
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Ownership transferred successfully',
        ]);

    $this->member->refresh();
    $this->owner->refresh();

    // New owner should have owner role
    expect($this->member->hasRole('owner'))->toBeTrue();
    // Previous owner should now be admin (not member)
    expect($this->owner->hasRole('admin'))->toBeTrue();
    expect($this->owner->hasRole('owner'))->toBeFalse();
});

test('previous owner becomes admin after transfer', function () {
    Notification::fake();

    $response = $this->postJson('/api/tenant/transfer-ownership', [
        'user_id' => $this->member->id,
    ]);

    $response->assertStatus(200);

    $this->owner->refresh();

    expect($this->owner->hasRole('admin'))->toBeTrue()
        ->and($this->owner->hasRole('owner'))->toBeFalse()
        ->and($this->owner->hasRole('member'))->toBeFalse();
});

test('cannot transfer ownership to self', function () {
    $response = $this->postJson('/api/tenant/transfer-ownership', [
        'user_id' => $this->owner->id,
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'message' => 'Cannot transfer ownership to yourself',
        ]);
});

test('cannot transfer ownership to user from different tenant', function () {
    $otherTenant = Tenant::factory()->create();
    $otherUser = User::factory()->create([
        'tenant_id' => $otherTenant->id,
    ]);

    $response = $this->postJson('/api/tenant/transfer-ownership', [
        'user_id' => $otherUser->id,
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'message' => 'User does not belong to this tenant',
        ]);
});

test('cannot transfer ownership to inactive user', function () {
    $inactiveMember = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'email' => 'inactive@example.com',
        'is_active' => false,
    ]);
    $inactiveMember->assignRole('member');

    $response = $this->postJson('/api/tenant/transfer-ownership', [
        'user_id' => $inactiveMember->id,
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'message' => 'Cannot transfer ownership to an inactive user',
        ]);
});

test('transfer requires user_id field', function () {
    $response = $this->postJson('/api/tenant/transfer-ownership', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['user_id']);
});

test('transfer requires valid user_id', function () {
    $response = $this->postJson('/api/tenant/transfer-ownership', [
        'user_id' => 99999,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['user_id']);
});

test('non-owner cannot transfer ownership', function () {
    // Login as member (not owner)
    Sanctum::actingAs($this->member);

    $anotherMember = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'is_active' => true,
    ]);
    $anotherMember->assignRole('member');

    $response = $this->postJson('/api/tenant/transfer-ownership', [
        'user_id' => $anotherMember->id,
    ]);

    // Should be forbidden (403) because member doesn't have tenant.transfer permission
    $response->assertStatus(403);
});

test('admin cannot transfer ownership', function () {
    $admin = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'email' => 'admin@example.com',
        'is_active' => true,
    ]);
    $admin->assignRole('admin');

    Sanctum::actingAs($admin);

    $response = $this->postJson('/api/tenant/transfer-ownership', [
        'user_id' => $this->member->id,
    ]);

    // Should be forbidden (403) because admin doesn't have tenant.transfer permission
    $response->assertStatus(403);
});

test('ownership transfer sends notifications to both users', function () {
    Notification::fake();

    $response = $this->postJson('/api/tenant/transfer-ownership', [
        'user_id' => $this->member->id,
    ]);

    $response->assertStatus(200);

    // Both users should receive the notification
    Notification::assertSentTo($this->owner, OwnershipTransferredNotification::class);
    Notification::assertSentTo($this->member, OwnershipTransferredNotification::class);
});

test('ownership transfer creates activity log', function () {
    Notification::fake();

    $response = $this->postJson('/api/tenant/transfer-ownership', [
        'user_id' => $this->member->id,
    ]);

    $response->assertStatus(200);

    // Check that activity was logged
    $activity = Activity::where('log_name', 'ownership_transfer')
        ->where('subject_type', Tenant::class)
        ->where('subject_id', $this->tenant->id)
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($this->owner->id)
        ->and($activity->properties['previous_owner_id'])->toBe($this->owner->id)
        ->and($activity->properties['new_owner_id'])->toBe($this->member->id);
});

test('can get tenant information', function () {
    $response = $this->getJson('/api/tenant');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => ['id', 'name', 'slug', 'settings'],
        ]);
});

test('can update tenant information', function () {
    $response = $this->putJson('/api/tenant', [
        'name' => 'Updated Company Name',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Tenant updated successfully',
        ]);

    $this->tenant->refresh();
    expect($this->tenant->name)->toBe('Updated Company Name');
});
