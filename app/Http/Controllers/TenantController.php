<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Repositories\Contracts\TenantRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    public function __construct(
        protected TenantRepositoryInterface $tenantRepository
    ) {
    }

    /**
     * Get current tenant information.
     */
    public function show(Request $request): JsonResponse
    {
        $tenant = $this->tenantRepository->findWithOwner($request->user()->tenant_id);

        if (! $tenant) {
            return $this->errorResponse('Tenant not found', 404);
        }

        return $this->successResponse($tenant, 'Tenant retrieved successfully');
    }

    /**
     * Update tenant settings.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'settings' => ['sometimes', 'array'],
        ]);

        $tenant = $this->tenantRepository->update($request->user()->tenant_id, $validated);

        return $this->successResponse($tenant, 'Tenant updated successfully');
    }

    /**
     * Transfer ownership to another user.
     */
    public function transferOwnership(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $tenantId = $request->user()->tenant_id;
        $newOwnerId = $validated['user_id'];

        // Verify the new owner belongs to the same tenant
        $newOwner = \App\Models\User::withoutGlobalScope(\App\Scopes\TenantScope::class)
            ->where('id', $newOwnerId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (! $newOwner) {
            return $this->errorResponse('User does not belong to this tenant', 422);
        }

        // Prevent transferring to self
        if ($newOwnerId === $request->user()->id) {
            return $this->errorResponse('Cannot transfer ownership to yourself', 422);
        }

        try {
            // Remove owner role from current owner
            $request->user()->removeRole('owner');
            $request->user()->assignRole('member');

            // Assign owner role to new owner
            $newOwner->removeRole('member');
            $newOwner->assignRole('owner');

            return $this->successResponse(
                ['new_owner' => $newOwner],
                'Ownership transferred successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to transfer ownership: ' . $e->getMessage(), 500);
        }
    }
}
