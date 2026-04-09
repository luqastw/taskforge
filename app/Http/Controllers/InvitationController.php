<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Services\InvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvitationController extends Controller
{
    public function __construct(
        protected InvitationService $invitationService
    ) {
    }

    /**
     * Send invitation to a user.
     */
    public function invite(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'role' => ['sometimes', 'string', 'in:admin,member,viewer'],
        ]);

        try {
            $invitation = $this->invitationService->invite(
                $request->user()->tenant_id,
                $request->user()->id,
                $validated['email'],
                $validated['role'] ?? 'member'
            );

            return $this->successResponse($invitation, 'Invitation sent successfully', 201);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * Get all pending invitations for the current tenant.
     */
    public function index(Request $request): JsonResponse
    {
        $invitations = $this->invitationService->getPendingInvitations(
            $request->user()->tenant_id
        );

        return $this->successResponse($invitations, 'Invitations retrieved successfully');
    }

    /**
     * Cancel an invitation.
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        try {
            $this->invitationService->cancelInvitation($id, $request->user()->tenant_id);

            return $this->successResponse(null, 'Invitation cancelled successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Invitation not found', 404);
        }
    }

    /**
     * Accept an invitation and create account.
     */
    public function accept(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        try {
            $result = $this->invitationService->acceptInvitation(
                $validated['token'],
                $validated['name'],
                $validated['password']
            );

            return $this->successResponse($result, 'Invitation accepted successfully', 201);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Invalid invitation token', 404);
        }
    }
}
