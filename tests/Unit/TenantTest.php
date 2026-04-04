<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('tenant can be created with valid attributes', function () {
    $tenant = Tenant::factory()->create([
        'name' => 'Test Company',
        'slug' => 'test-company',
    ]);

    expect($tenant)->toBeInstanceOf(Tenant::class)
        ->and($tenant->name)->toBe('Test Company')
        ->and($tenant->slug)->toBe('test-company')
        ->and($tenant->settings)->toBeArray();
});

test('tenant automatically generates slug from name if not provided', function () {
    $tenant = Tenant::factory()->create([
        'name' => 'Acme Corporation',
        'slug' => null,
    ]);

    expect($tenant->slug)->toStartWith('acme-corporation');
});

test('tenant slug is unique', function () {
    Tenant::factory()->create(['slug' => 'unique-slug']);

    expect(fn () => Tenant::factory()->create(['slug' => 'unique-slug']))
        ->toThrow(QueryException::class);
});

test('tenant has default settings', function () {
    $tenant = Tenant::factory()->create();

    expect($tenant->settings)
        ->toBeArray()
        ->toHaveKey('timezone')
        ->toHaveKey('date_format')
        ->toHaveKey('time_format');
});

test('tenant can have custom settings', function () {
    $tenant = Tenant::factory()->withSettings([
        'custom_field' => 'custom_value',
        'max_users' => 50,
    ])->create();

    expect($tenant->settings)
        ->toHaveKey('custom_field')
        ->toHaveKey('max_users')
        ->and($tenant->settings['custom_field'])->toBe('custom_value')
        ->and($tenant->settings['max_users'])->toBe(50);
});

test('tenant has users relationship', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    expect($tenant->users)->toHaveCount(1)
        ->and($tenant->users->first()->id)->toBe($user->id);
});

test('tenant can identify its owner', function () {
    $tenant = Tenant::factory()->create();

    // Create the 'owner' role
    Role::create(['name' => 'owner']);

    $owner = User::factory()->create([
        'tenant_id' => $tenant->id,
    ]);
    $owner->assignRole('owner');

    $regularUser = User::factory()->create([
        'tenant_id' => $tenant->id,
    ]);

    expect($tenant->owner()->id)->toBe($owner->id)
        ->and($tenant->owner()->id)->not->toBe($regularUser->id);
});

test('tenant returns null if no owner exists', function () {
    $tenant = Tenant::factory()->create();
    User::factory()->create([
        'tenant_id' => $tenant->id,
    ]); // No owner role assigned

    expect($tenant->owner())->toBeNull();
});

test('tenant slug is converted to lowercase', function () {
    $tenant = Tenant::factory()->create([
        'name' => 'My Company',
        'slug' => 'MyCompany',
    ]);

    expect($tenant->slug)->toBe('mycompany');
});

test('tenant timestamps are automatically managed', function () {
    $tenant = Tenant::factory()->create();

    expect($tenant->created_at)->not->toBeNull()
        ->and($tenant->updated_at)->not->toBeNull();

    $originalUpdatedAt = $tenant->updated_at;
    sleep(1);
    $tenant->update(['name' => 'Updated Name']);

    expect($tenant->updated_at->greaterThan($originalUpdatedAt))->toBeTrue();
});
