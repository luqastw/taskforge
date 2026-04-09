<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AuthController extends Controller
{
    public function __construct(
        protected AuthService $authService
    ) {}

    /**
     * Register a new tenant with owner.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->register($request->validated());

            return $this->successResponse(
                [
                    'user' => $result['user'],
                    'tenant' => $result['tenant'],
                    'token' => $result['token'],
                ],
                'Registration successful',
                201
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Registration failed: '.$e->getMessage(),
                500
            );
        }
    }

    /**
     * Authenticate user and return token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login($request->validated());

        if ($result === null) {
            return $this->errorResponse(
                'Invalid credentials',
                401
            );
        }

        return $this->successResponse(
            [
                'user' => $result['user'],
                'token' => $result['token'],
            ],
            'Login successful'
        );
    }

    /**
     * Logout user from all devices.
     */
    public function logout(Request $request): Response
    {
        $this->authService->logout($request->user());

        return response()->noContent();
    }

    /**
     * Logout user from current device only.
     */
    public function logoutCurrentDevice(Request $request): Response
    {
        $this->authService->logoutCurrentDevice($request->user());

        return response()->noContent();
    }

    /**
     * Get authenticated user.
     */
    public function me(Request $request): JsonResponse
    {
        return $this->successResponse(
            $request->user()->load('tenant'),
            'User retrieved successfully'
        );
    }
}
