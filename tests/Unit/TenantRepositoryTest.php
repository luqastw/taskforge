<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Repositories\Contracts\TenantRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->repository = app(TenantRepositoryInterface::class);
});

test('repository can create a tenant', function () {
    $data = [
        'name' => 'Test Company',
        'slug' => 'test-company',
        'settings' => ['timezone' => 'UTC'],
    ];

    $tenant = $this->repository->create($data);

    expect($tenant)->toBeInstanceOf(Tenant::class)
        ->and($tenant->name)->toBe('Test Company')
        ->and($tenant->slug)->toBe('test-company');
});

test('repository can find tenant by id', function () {
    $tenant = Tenant::factory()->create();

    $found = $this->repository->find($tenant->id);

    expect($found->id)->toBe($tenant->id);
});

test('repository can find tenant by slug', function () {
    $tenant = Tenant::factory()->create(['slug' => 'unique-slug']);

    $found = $this->repository->findBySlug('unique-slug');

    expect($found)->not->toBeNull()
        ->and($found->id)->toBe($tenant->id)
        ->and($found->slug)->toBe('unique-slug');
});

test('repository returns null when slug not found', function () {
    $found = $this->repository->findBySlug('non-existent-slug');

    expect($found)->toBeNull();
});

test('repository can check if slug exists', function () {
    Tenant::factory()->create(['slug' => 'existing-slug']);

    expect($this->repository->slugExists('existing-slug'))->toBeTrue()
        ->and($this->repository->slugExists('non-existent-slug'))->toBeFalse();
});

test('repository can check slug existence excluding specific id', function () {
    $tenant1 = Tenant::factory()->create(['slug' => 'slug-1']);
    $tenant2 = Tenant::factory()->create(['slug' => 'slug-2']);

    // Check if slug-1 exists, excluding tenant1
    expect($this->repository->slugExists('slug-1', $tenant1->id))->toBeFalse()
        ->and($this->repository->slugExists('slug-1', $tenant2->id))->toBeTrue();
});

test('repository can update a tenant', function () {
    $tenant = Tenant::factory()->create(['name' => 'Old Name']);

    $updated = $this->repository->update($tenant->id, ['name' => 'New Name']);

    expect($updated)->toBeTrue();
    expect($tenant->fresh()->name)->toBe('New Name');
});

test('repository can delete a tenant', function () {
    $tenant = Tenant::factory()->create();

    $deleted = $this->repository->delete($tenant->id);

    expect($deleted)->toBeTrue();
    expect(Tenant::find($tenant->id))->toBeNull();
});

test('repository can get all tenants', function () {
    Tenant::factory()->count(3)->create();

    $tenants = $this->repository->all();

    expect($tenants)->toHaveCount(3);
});

test('repository can find tenant with owner', function () {
    $tenant = Tenant::factory()->create();

    // Create the 'owner' role
    Role::create(['name' => 'owner']);

    $owner = User::factory()->create(['tenant_id' => $tenant->id]);
    $owner->assignRole('owner');

    $found = $this->repository->findWithOwner($tenant->id);

    expect($found)->not->toBeNull()
        ->and($found->relationLoaded('users'))->toBeTrue()
        ->and($found->owner()->id)->toBe($owner->id);
});

test('repository returns null when tenant with owner not found', function () {
    $found = $this->repository->findWithOwner(999);

    expect($found)->toBeNull();
});
