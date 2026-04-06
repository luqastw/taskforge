<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\User;
use App\Notifications\OwnershipTransferredNotification;
use App\Repositories\Contracts\TenantRepositoryInterface;
use App\Scopes\TenantScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TenantController extends Controller
{
    public function __construct(
        protected TenantRepositoryInterface $tenantRepository
    ) {}

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
        $previousOwner = $request->user();

        // Get tenant
        $tenant = Tenant::find($tenantId);

        if (! $tenant) {
            return $this->errorResponse('Tenant not found', 404);
        }

        // Verify the new owner belongs to the same tenant and is active
        $newOwner = User::withoutGlobalScope(TenantScope::class)
            ->where('id', $newOwnerId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (! $newOwner) {
            return $this->errorResponse('User does not belong to this tenant', 422);
        }

        // Verify the new owner is an active member
        if (! $newOwner->is_active) {
            return $this->errorResponse('Cannot transfer ownership to an inactive user', 422);
        }

        // Prevent transferring to self
        if ($newOwnerId === $previousOwner->id) {
            return $this->errorResponse('Cannot transfer ownership to yourself', 422);
        }

        try {
            DB::transaction(function () use ($previousOwner, $newOwner, $tenant) {
                // Remove owner role from current owner and make them admin
                $previousOwner->removeRole('owner');
                $previousOwner->assignRole('admin');

                // Remove any existing role from new owner and assign owner role
                $newOwner->syncRoles(['owner']);

                // Log the ownership transfer activity
                activity('ownership_transfer')
                    ->performedOn($tenant)
                    ->causedBy($previousOwner)
                    ->withProperties([
                        'previous_owner_id' => $previousOwner->id,
                        'previous_owner_name' => $previousOwner->name,
                        'previous_owner_email' => $previousOwner->email,
                        'new_owner_id' => $newOwner->id,
                        'new_owner_name' => $newOwner->name,
                        'new_owner_email' => $newOwner->email,
                        'tenant_id' => $tenant->id,
                        'tenant_name' => $tenant->name,
                    ])
                    ->log('Ownership transferred from '.$previousOwner->name.' to '.$newOwner->name);

                // Notify both users
                $previousOwner->notify(new OwnershipTransferredNotification(
                    tenant: $tenant,
                    previousOwner: $previousOwner,
                    newOwner: $newOwner,
                    isNewOwner: false
                ));

                $newOwner->notify(new OwnershipTransferredNotification(
                    tenant: $tenant,
                    previousOwner: $previousOwner,
                    newOwner: $newOwner,
                    isNewOwner: true
                ));
            });

            return $this->successResponse(
                ['new_owner' => $newOwner->fresh()],
                'Ownership transferred successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to transfer ownership: '.$e->getMessage(), 500);
        }
    }
}
