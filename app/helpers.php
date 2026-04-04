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
        $tenantId = tenant_id();

        if ($tenantId === null) {
            return null;
        }

        return Tenant::find($tenantId);
    }
}

if (! function_exists('tenant_id')) {
    /**
     * Get the current tenant ID.
     */
    function tenant_id(): ?int
    {
        // First check if tenant_id is set in the app container (for testing)
        if (app()->has('tenant_id')) {
            return app('tenant_id');
        }

        // Otherwise, get from authenticated user
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
