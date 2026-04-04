<?php

declare(strict_types=1);

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

    $this->member = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'email' => 'member@example.com',
        'password' => bcrypt('password123'),
    ]);
    $this->member->assignRole('member');

    Sanctum::actingAs($this->owner);
});

test('owner can transfer ownership to another user', function () {
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

    expect($this->member->hasRole('owner'))->toBeTrue()
        ->and($this->owner->hasRole('member'))->toBeTrue();
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
