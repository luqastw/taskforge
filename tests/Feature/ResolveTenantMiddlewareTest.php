<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);

    $this->tenant = Tenant::factory()->create();
    $this->user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);
    $this->user->assignRole('admin');
});

test('middleware resolves tenant for authenticated user', function (): void {
    $response = $this->actingAs($this->user)
        ->getJson('/api/workspaces');

    $response->assertStatus(200);

    // Verify tenant is bound to container
    expect(app('tenant'))->toBeInstanceOf(Tenant::class)
        ->and(app('tenant')->id)->toBe($this->tenant->id)
        ->and(app('tenant_id'))->toBe($this->tenant->id);
});

test('middleware returns 401 for unauthenticated request', function (): void {
    $response = $this->getJson('/api/workspaces');

    $response->assertStatus(401);
});

test('tenant helpers work after middleware resolves tenant', function (): void {
    $this->actingAs($this->user)
        ->getJson('/api/workspaces');

    expect(tenant())->toBeInstanceOf(Tenant::class)
        ->and(tenant()->id)->toBe($this->tenant->id)
        ->and(tenant_id())->toBe($this->tenant->id);
});

test('middleware binds tenant to app container', function (): void {
    $this->actingAs($this->user)
        ->getJson('/api/workspaces');

    // Verify both tenant and tenant_id are bound
    expect(app()->bound('tenant'))->toBeTrue()
        ->and(app()->bound('tenant_id'))->toBeTrue()
        ->and(app('tenant'))->toBeInstanceOf(Tenant::class)
        ->and(app('tenant_id'))->toBeInt();
});

test('middleware is applied to workspace routes', function (): void {
    $response = $this->actingAs($this->user)
        ->getJson('/api/workspaces');

    $response->assertStatus(200);
    expect(app('tenant_id'))->toBe($this->tenant->id);
});

test('middleware is applied to project routes', function (): void {
    $response = $this->actingAs($this->user)
        ->getJson('/api/projects');

    $response->assertStatus(200);
    expect(app('tenant_id'))->toBe($this->tenant->id);
});

test('middleware is applied to task routes', function (): void {
    $response = $this->actingAs($this->user)
        ->getJson('/api/tasks');

    $response->assertStatus(200);
    expect(app('tenant_id'))->toBe($this->tenant->id);
});

test('middleware is applied to member routes', function (): void {
    $response = $this->actingAs($this->user)
        ->getJson('/api/members');

    $response->assertStatus(200);
    expect(app('tenant_id'))->toBe($this->tenant->id);
});
