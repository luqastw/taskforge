<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Scopes\TenantScope;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('users belong to a tenant', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    expect($user->tenant_id)->toBe($tenant->id)
        ->and($user->tenant)->toBeInstanceOf(Tenant::class)
        ->and($user->tenant->id)->toBe($tenant->id);
});

test('users from different tenants are isolated', function () {
    $tenant1 = Tenant::factory()->create(['name' => 'Tenant 1']);
    $tenant2 = Tenant::factory()->create(['name' => 'Tenant 2']);

    $user1 = User::factory()->create(['tenant_id' => $tenant1->id, 'name' => 'User 1']);
    $user2 = User::factory()->create(['tenant_id' => $tenant2->id, 'name' => 'User 2']);

    // Set current tenant context to tenant1
    app()->instance('tenant_id', $tenant1->id);

    // When querying users, should only get users from tenant1
    $users = User::all();

    expect($users)->toHaveCount(1)
        ->and($users->first()->id)->toBe($user1->id)
        ->and($users->contains($user2))->toBeFalse();
});

test('email uniqueness is enforced globally', function () {
    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();

    User::factory()->create([
        'tenant_id' => $tenant1->id,
        'email' => 'john@example.com',
    ]);

    // Same email cannot exist even in a different tenant
    expect(fn () => User::factory()->create([
        'tenant_id' => $tenant2->id,
        'email' => 'john@example.com',
    ]))->toThrow(QueryException::class);
});

test('tenant scope can be bypassed with withoutGlobalScope', function () {
    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();

    User::factory()->create(['tenant_id' => $tenant1->id]);
    User::factory()->create(['tenant_id' => $tenant2->id]);

    // Set current tenant context to tenant1
    app()->instance('tenant_id', $tenant1->id);

    // With scope: only tenant1 users
    expect(User::all())->toHaveCount(1);

    // Without scope: all users
    expect(User::withoutGlobalScope(TenantScope::class)->get())
        ->toHaveCount(2);
});

test('tenant_id helper returns current tenant id', function () {
    $tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $tenant->id);

    expect(tenant_id())->toBe($tenant->id);
});

test('tenant helper returns current tenant model', function () {
    $tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $tenant->id);

    expect(tenant())->toBeInstanceOf(Tenant::class)
        ->and(tenant()->id)->toBe($tenant->id);
});

test('tenant helpers return null when no tenant is set', function () {
    expect(tenant_id())->toBeNull()
        ->and(tenant())->toBeNull();
});

test('users cannot be created without tenant_id', function () {
    expect(fn () => User::factory()->create(['tenant_id' => null]))
        ->toThrow(QueryException::class);
});

test('changing tenant context changes query results', function () {
    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();

    $user1 = User::factory()->create(['tenant_id' => $tenant1->id]);
    $user2 = User::factory()->create(['tenant_id' => $tenant2->id]);

    // Context: Tenant 1
    app()->instance('tenant_id', $tenant1->id);
    expect(User::all())->toHaveCount(1)
        ->and(User::first()->id)->toBe($user1->id);

    // Change context: Tenant 2
    app()->instance('tenant_id', $tenant2->id);
    expect(User::all())->toHaveCount(1)
        ->and(User::first()->id)->toBe($user2->id);
});
