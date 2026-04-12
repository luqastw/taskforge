<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Comment;
use App\Models\User;

class CommentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('comment.view');
    }

    public function view(User $user, Comment $comment): bool
    {
        return $user->tenant_id === $comment->tenant_id
            && $user->hasPermissionTo('comment.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('comment.create');
    }

    public function update(User $user, Comment $comment): bool
    {
        return $user->tenant_id === $comment->tenant_id
            && $user->id === $comment->user_id
            && $user->hasPermissionTo('comment.update');
    }

    public function delete(User $user, Comment $comment): bool
    {
        if ($user->tenant_id !== $comment->tenant_id) {
            return false;
        }

        // Owner/admin can delete any comment; others only their own
        if ($user->hasRole(['owner', 'admin'])) {
            return $user->hasPermissionTo('comment.delete');
        }

        return $user->id === $comment->user_id
            && $user->hasPermissionTo('comment.delete');
    }
}
