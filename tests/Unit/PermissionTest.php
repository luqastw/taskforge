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

    $this->viewer = User::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);
    $this->viewer->assignRole('viewer');
});

describe('Permission system', function () {
    test('owner has all permissions', function () {
        expect($this->owner->hasPermissionTo('workspace.create'))->toBeTrue();
        expect($this->owner->hasPermissionTo('workspace.delete'))->toBeTrue();
        expect($this->owner->hasPermissionTo('tenant.transfer'))->toBeTrue();
        expect($this->owner->hasPermissionTo('task.create'))->toBeTrue();
        expect($this->owner->hasPermissionTo('tag.create'))->toBeTrue();
        expect($this->owner->hasPermissionTo('comment.create'))->toBeTrue();
    });

    test('admin has most permissions except tenant management', function () {
        expect($this->admin->hasPermissionTo('workspace.create'))->toBeTrue();
        expect($this->admin->hasPermissionTo('workspace.delete'))->toBeTrue();
        expect($this->admin->hasPermissionTo('tenant.transfer'))->toBeFalse();
        expect($this->admin->hasPermissionTo('member.remove'))->toBeTrue();
        expect($this->admin->hasPermissionTo('tag.create'))->toBeTrue();
        expect($this->admin->hasPermissionTo('tag.delete'))->toBeTrue();
        expect($this->admin->hasPermissionTo('comment.delete'))->toBeTrue();
    });

    test('member has basic permissions', function () {
        expect($this->member->hasPermissionTo('workspace.view'))->toBeTrue();
        expect($this->member->hasPermissionTo('project.view'))->toBeTrue();
        expect($this->member->hasPermissionTo('project.create'))->toBeTrue();
        expect($this->member->hasPermissionTo('task.create'))->toBeTrue();
        expect($this->member->hasPermissionTo('workspace.create'))->toBeFalse();
        expect($this->member->hasPermissionTo('tag.create'))->toBeTrue();
        expect($this->member->hasPermissionTo('tag.delete'))->toBeFalse();
        expect($this->member->hasPermissionTo('comment.create'))->toBeTrue();
    });

    test('viewer has read-only permissions', function () {
        expect($this->viewer->hasPermissionTo('workspace.view'))->toBeTrue();
        expect($this->viewer->hasPermissionTo('project.view'))->toBeTrue();
        expect($this->viewer->hasPermissionTo('task.view'))->toBeTrue();
        expect($this->viewer->hasPermissionTo('task.create'))->toBeFalse();
        expect($this->viewer->hasPermissionTo('tag.view'))->toBeTrue();
        expect($this->viewer->hasPermissionTo('tag.create'))->toBeFalse();
        expect($this->viewer->hasPermissionTo('comment.view'))->toBeTrue();
        expect($this->viewer->hasPermissionTo('comment.create'))->toBeFalse();
    });

    test('roles are correctly assigned', function () {
        expect($this->owner->hasRole('owner'))->toBeTrue();
        expect($this->admin->hasRole('admin'))->toBeTrue();
        expect($this->member->hasRole('member'))->toBeTrue();
        expect($this->viewer->hasRole('viewer'))->toBeTrue();
    });

    test('can check permissions via string', function () {
        expect($this->owner->can('workspace.create'))->toBeTrue();
        expect($this->viewer->can('workspace.create'))->toBeFalse();
    });
});
