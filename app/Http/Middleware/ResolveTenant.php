<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        if (! $user->tenant_id) {
            return response()->json([
                'success' => false,
                'message' => 'User does not belong to any tenant',
            ], 403);
        }

        $tenant = Tenant::find($user->tenant_id);

        if (! $tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant not found',
            ], 404);
        }

        // Bind tenant to container for global access
        app()->instance('tenant', $tenant);
        app()->instance('tenant_id', $tenant->id);

        return $next($request);
    }
}
