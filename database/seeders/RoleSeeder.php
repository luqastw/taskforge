<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Create roles
        $owner = Role::create(['name' => 'owner']);
        $admin = Role::create(['name' => 'admin']);
        $member = Role::create(['name' => 'member']);
        $guest = Role::create(['name' => 'guest']);

        // Create permissions
        $permissions = [
            // Workspace permissions
            'workspace.create',
            'workspace.view',
            'workspace.update',
            'workspace.delete',

            // Project permissions
            'project.create',
            'project.view',
            'project.update',
            'project.delete',

            // Task permissions
            'task.create',
            'task.view',
            'task.update',
            'task.delete',

            // Member permissions
            'member.invite',
            'member.view',
            'member.update',
            'member.remove',

            // Tenant permissions
            'tenant.update',
            'tenant.delete',
            'tenant.transfer',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Assign permissions to roles
        // Owner has all permissions
        $owner->givePermissionTo(Permission::all());

        // Admin has most permissions except tenant management
        $admin->givePermissionTo([
            'workspace.create', 'workspace.view', 'workspace.update', 'workspace.delete',
            'project.create', 'project.view', 'project.update', 'project.delete',
            'task.create', 'task.view', 'task.update', 'task.delete',
            'member.invite', 'member.view', 'member.update',
        ]);

        // Member has basic permissions
        $member->givePermissionTo([
            'workspace.view',
            'project.view', 'project.create',
            'task.create', 'task.view', 'task.update', 'task.delete',
        ]);

        // Guest has read-only permissions
        $guest->givePermissionTo([
            'workspace.view',
            'project.view',
            'task.view',
        ]);
    }
}
