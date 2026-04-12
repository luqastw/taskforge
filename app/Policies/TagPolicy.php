<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tag;
use App\Models\User;

class TagPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('tag.view');
    }

    public function view(User $user, Tag $tag): bool
    {
        return $user->tenant_id === $tag->tenant_id
            && $user->hasPermissionTo('tag.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('tag.create');
    }

    public function update(User $user, Tag $tag): bool
    {
        return $user->tenant_id === $tag->tenant_id
            && $user->hasPermissionTo('tag.update');
    }

    public function delete(User $user, Tag $tag): bool
    {
        return $user->tenant_id === $tag->tenant_id
            && $user->hasPermissionTo('tag.delete');
    }
}
