<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Project $project): bool
    {
        return $user->tenant_id === $project->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['owner', 'admin', 'member']);
    }

    public function update(User $user, Project $project): bool
    {
        return $user->tenant_id === $project->tenant_id
            && $user->hasAnyRole(['owner', 'admin']);
    }

    public function manageMembers(User $user, Project $project): bool
    {
        return $user->tenant_id === $project->tenant_id
            && $user->hasAnyRole(['owner', 'admin']);
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->tenant_id === $project->tenant_id
            && $user->hasAnyRole(['owner', 'admin']);
    }

    public function restore(User $user, Project $project): bool
    {
        return $user->tenant_id === $project->tenant_id
            && $user->hasAnyRole(['owner', 'admin']);
    }

    public function forceDelete(User $user, Project $project): bool
    {
        return $user->tenant_id === $project->tenant_id
            && $user->hasRole('owner');
    }
}
