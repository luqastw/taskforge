<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Workspace;

class WorkspacePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // All authenticated users can view workspaces from their tenant
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Workspace $workspace): bool
    {
        // User can view if workspace belongs to their tenant
        return $user->tenant_id === $workspace->tenant_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Only owner and admin can create workspaces
        return $user->hasAnyRole(['owner', 'admin']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Workspace $workspace): bool
    {
        // User must be from same tenant and have owner/admin role
        return $user->tenant_id === $workspace->tenant_id
            && $user->hasAnyRole(['owner', 'admin']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Workspace $workspace): bool
    {
        // User must be from same tenant and have owner/admin role
        return $user->tenant_id === $workspace->tenant_id
            && $user->hasAnyRole(['owner', 'admin']);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Workspace $workspace): bool
    {
        // User must be from same tenant and have owner/admin role
        return $user->tenant_id === $workspace->tenant_id
            && $user->hasAnyRole(['owner', 'admin']);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Workspace $workspace): bool
    {
        // Only owner can permanently delete
        return $user->tenant_id === $workspace->tenant_id
            && $user->hasRole('owner');
    }
}
