<?php

declare(strict_types=1);
use App\Models\Tenant;
use App\Models\User;

if (! function_exists('tenant')) {
    /**
     * Get the current tenant.
     */
    function tenant(): ?Tenant
    {
        if (! auth()->check()) {
            return null;
        }

        return auth()->user()?->tenant;
    }
}

if (! function_exists('tenant_id')) {
    /**
     * Get the current tenant ID.
     */
    function tenant_id(): ?int
    {
        return auth()->user()?->tenant_id;
    }
}

if (! function_exists('current_user')) {
    /**
     * Get the authenticated user.
     */
    function current_user(): ?User
    {
        return auth()->user();
    }
}
