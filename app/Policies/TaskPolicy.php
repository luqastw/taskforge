<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Task $task): bool
    {
        return $user->tenant_id === $task->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['owner', 'admin', 'member']);
    }

    public function update(User $user, Task $task): bool
    {
        return $user->tenant_id === $task->tenant_id
            && $user->hasAnyRole(['owner', 'admin', 'member']);
    }

    public function delete(User $user, Task $task): bool
    {
        return $user->tenant_id === $task->tenant_id
            && $user->hasAnyRole(['owner', 'admin', 'member']);
    }

    public function restore(User $user, Task $task): bool
    {
        return $user->tenant_id === $task->tenant_id
            && $user->hasAnyRole(['owner', 'admin']);
    }

    public function forceDelete(User $user, Task $task): bool
    {
        return $user->tenant_id === $task->tenant_id
            && $user->hasRole('owner');
    }
}
