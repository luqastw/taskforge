<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use App\Repositories\TenantRepository;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    public function __construct(
        protected TenantRepository $tenantRepository,
        protected UserRepository $userRepository
    ) {}

    /**
     * Register a new tenant with owner.
     */
    public function register(array $data): array
    {
        return DB::transaction(function () use ($data) {
            // Create tenant
            $tenant = $this->tenantRepository->create([
                'name' => $data['company_name'],
                'slug' => $data['company_slug'] ?? null,
                'settings' => [
                    'timezone' => $data['timezone'] ?? 'UTC',
                    'date_format' => 'Y-m-d',
                    'time_format' => 'H:i:s',
                ],
            ]);

            // Create owner user
            $user = $this->userRepository->create([
                'tenant_id' => $tenant->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);

            // Assign owner role
            $user->assignRole('owner');

            // Create API token
            $token = $user->createToken('auth-token')->plainTextToken;

            return [
                'user' => $user,
                'tenant' => $tenant,
                'token' => $token,
            ];
        });
    }

    /**
     * Authenticate a user and return token.
     */
    public function login(array $credentials): ?array
    {
        // Find user by email and tenant
        $user = $this->userRepository->findByEmailAndTenant(
            $credentials['email'],
            $credentials['tenant_id']
        );

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return null;
        }

        // Create API token
        $token = $user->createToken('auth-token')->plainTextToken;

        return [
            'user' => $user->load('tenant'),
            'token' => $token,
        ];
    }

    /**
     * Logout user by revoking tokens.
     */
    public function logout(User $user): void
    {
        $user->tokens()->delete();
    }

    /**
     * Logout user from current device only.
     */
    public function logoutCurrentDevice(User $user): void
    {
        $user->currentAccessToken()->delete();
    }
}
