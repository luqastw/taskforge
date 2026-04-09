<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Invitation;
use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Jobs\SendInvitationEmailJob;
use Illuminate\Support\Str;

class InvitationService
{
    public function __construct(
        protected UserRepository $userRepository
    ) {
    }

    public function invite(int $tenantId, int $invitedBy, string $email, string $role = 'member'): Invitation
    {
        return DB::transaction(function () use ($tenantId, $invitedBy, $email, $role) {
            $existingUser = $this->userRepository->findByEmail($email);
            if ($existingUser) {
                throw new \InvalidArgumentException('A user with this email already exists in the system.');
            }

            $existingInvitation = Invitation::withoutGlobalScope(\App\Scopes\TenantScope::class)
                ->where('tenant_id', $tenantId)
                ->where('email', $email)
                ->whereNull('accepted_at')
                ->first();

            if ($existingInvitation) {
                if ($existingInvitation->isValid()) {
                    throw new \InvalidArgumentException('An invitation has already been sent to this email.');
                }
                $existingInvitation->delete();
            }

            $invitation = Invitation::create([
                'tenant_id' => $tenantId,
                'email' => $email,
                'token' => Str::random(64),
                'invited_by' => $invitedBy,
                'role' => $role,
                'expires_at' => now()->addDays(7),
            ]);

            $inviter = User::find($invitedBy);
            $tenant = \App\Models\Tenant::find($tenantId);

            SendInvitationEmailJob::dispatch(
                invitation: $invitation,
                inviterName: $inviter->name,
                tenantName: $tenant->name
            );

            return $invitation;
        });
    }

    public function acceptInvitation(string $token, string $name, string $password): array
    {
        return DB::transaction(function () use ($token, $name, $password) {
            $invitation = Invitation::withoutGlobalScope(\App\Scopes\TenantScope::class)
                ->where('token', $token)
                ->firstOrFail();

            if (! $invitation->isValid()) {
                throw new \InvalidArgumentException('This invitation has expired or has already been used.');
            }

            $user = $this->userRepository->create([
                'tenant_id' => $invitation->tenant_id,
                'name' => $name,
                'email' => $invitation->email,
                'password' => Hash::make($password),
            ]);

            $user->assignRole($invitation->role);
            $invitation->update(['accepted_at' => now()]);
            $apiToken = $user->createToken('auth-token')->plainTextToken;

            return [
                'user' => $user->load('tenant'),
                'token' => $apiToken,
            ];
        });
    }

    public function getPendingInvitations(int $tenantId)
    {
        return Invitation::where('tenant_id', $tenantId)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->with('inviter')
            ->get();
    }

    public function cancelInvitation(int $invitationId, int $tenantId): bool
    {
        $invitation = Invitation::where('id', $invitationId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        return $invitation->delete();
    }
}
