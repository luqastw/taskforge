<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('project.view');
    }

    public function view(User $user, Project $project): bool
    {
        return $user->tenant_id === $project->tenant_id
            && $user->hasPermissionTo('project.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('project.create');
    }

    public function update(User $user, Project $project): bool
    {
        return $user->tenant_id === $project->tenant_id
            && $user->hasPermissionTo('project.update');
    }

    public function manageMembers(User $user, Project $project): bool
    {
        return $user->tenant_id === $project->tenant_id
            && $user->hasPermissionTo('project.update');
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->tenant_id === $project->tenant_id
            && $user->hasPermissionTo('project.delete');
    }

    public function restore(User $user, Project $project): bool
    {
        return $user->tenant_id === $project->tenant_id
            && $user->hasPermissionTo('project.update');
    }

    public function forceDelete(User $user, Project $project): bool
    {
        return $user->tenant_id === $project->tenant_id
            && $user->hasRole('owner');
    }
}
