<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->tenant = Tenant::factory()->create();
    $this->owner = User::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);
    $this->owner->assignRole('owner');

    $this->admin = User::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);
    $this->admin->assignRole('admin');

    $this->member = User::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);
    $this->member->assignRole('member');

    $this->guest = User::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);
    $this->guest->assignRole('guest');
});

describe('Permission system', function () {
    test('owner has all permissions', function () {
        expect($this->owner->hasPermissionTo('workspace.create'))->toBeTrue();
        expect($this->owner->hasPermissionTo('workspace.delete'))->toBeTrue();
        expect($this->owner->hasPermissionTo('tenant.transfer'))->toBeTrue();
        expect($this->owner->hasPermissionTo('task.create'))->toBeTrue();
    });

    test('admin has most permissions except tenant management', function () {
        expect($this->admin->hasPermissionTo('workspace.create'))->toBeTrue();
        expect($this->admin->hasPermissionTo('workspace.delete'))->toBeTrue();
        expect($this->admin->hasPermissionTo('tenant.transfer'))->toBeFalse();
        expect($this->admin->hasPermissionTo('member.remove'))->toBeFalse();
    });

    test('member has basic permissions', function () {
        expect($this->member->hasPermissionTo('workspace.view'))->toBeTrue();
        expect($this->member->hasPermissionTo('project.view'))->toBeTrue();
        expect($this->member->hasPermissionTo('project.create'))->toBeTrue();
        expect($this->member->hasPermissionTo('task.create'))->toBeTrue();
        expect($this->member->hasPermissionTo('workspace.create'))->toBeFalse();
    });

    test('guest has read-only permissions', function () {
        expect($this->guest->hasPermissionTo('workspace.view'))->toBeTrue();
        expect($this->guest->hasPermissionTo('project.view'))->toBeTrue();
        expect($this->guest->hasPermissionTo('task.view'))->toBeTrue();
        expect($this->guest->hasPermissionTo('task.create'))->toBeFalse();
    });

    test('roles are correctly assigned', function () {
        expect($this->owner->hasRole('owner'))->toBeTrue();
        expect($this->admin->hasRole('admin'))->toBeTrue();
        expect($this->member->hasRole('member'))->toBeTrue();
        expect($this->guest->hasRole('guest'))->toBeTrue();
    });

    test('can check permissions via string', function () {
        expect($this->owner->can('workspace.create'))->toBeTrue();
        expect($this->guest->can('workspace.create'))->toBeFalse();
    });
});
