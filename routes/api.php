<?php

declare(strict_types=1);

use App\Http\Controllers\AuthController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TenantController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/auth/register', [AuthController::class, 'register'])->name('auth.register');
Route::post('/auth/login', [AuthController::class, 'login'])->name('auth.login');
Route::post('/invitations/accept', [InvitationController::class, 'accept'])->name('invitations.accept');

// Protected routes
Route::middleware(['auth:sanctum', 'tenant'])->group(function () {
    // Auth routes
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::post('/auth/logout-current-device', [AuthController::class, 'logoutCurrentDevice'])->name('auth.logout-current-device');
    Route::get('/auth/me', [AuthController::class, 'me'])->name('auth.me');

    // Tenant routes
    Route::get('/tenant', [TenantController::class, 'show'])->name('tenant.show');
    Route::put('/tenant', [TenantController::class, 'update'])->name('tenant.update');
    Route::post('/tenant/transfer-ownership', [TenantController::class, 'transferOwnership'])
        ->middleware('can:tenant.transfer')
        ->name('tenant.transfer-ownership');

    // Invitation routes
    Route::post('/invitations', [InvitationController::class, 'invite'])->name('invitations.invite');
    Route::get('/invitations', [InvitationController::class, 'index'])->name('invitations.index');
    Route::delete('/invitations/{id}', [InvitationController::class, 'cancel'])->name('invitations.cancel');

    // Member routes
    Route::get('/members', [MemberController::class, 'index'])->name('members.index');
    Route::get('/members/{id}', [MemberController::class, 'show'])->name('members.show');
    Route::patch('/members/{id}', [MemberController::class, 'update'])->name('members.update');
    Route::delete('/members/{id}', [MemberController::class, 'destroy'])->name('members.destroy');

    // Workspace routes
    Route::apiResource('workspaces', WorkspaceController::class);

    // Project routes
    Route::apiResource('projects', ProjectController::class);

    // Task routes
    Route::apiResource('tasks', TaskController::class);
});
