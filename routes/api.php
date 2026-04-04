<?php

declare(strict_types=1);

use App\Http\Controllers\AuthController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\TenantController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/auth/register', [AuthController::class, 'register'])->name('auth.register');
Route::post('/auth/login', [AuthController::class, 'login'])->name('auth.login');
Route::post('/invitations/accept', [InvitationController::class, 'accept'])->name('invitations.accept');

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth routes
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::post('/auth/logout-current-device', [AuthController::class, 'logoutCurrentDevice'])->name('auth.logout-current-device');
    Route::get('/auth/me', [AuthController::class, 'me'])->name('auth.me');

    // Tenant routes
    Route::get('/tenant', [TenantController::class, 'show'])->name('tenant.show');
    Route::put('/tenant', [TenantController::class, 'update'])->name('tenant.update');
    Route::post('/tenant/transfer-ownership', [TenantController::class, 'transferOwnership'])->name('tenant.transfer-ownership');

    // Invitation routes
    Route::post('/invitations', [InvitationController::class, 'invite'])->name('invitations.invite');
    Route::get('/invitations', [InvitationController::class, 'index'])->name('invitations.index');
    Route::delete('/invitations/{id}', [InvitationController::class, 'cancel'])->name('invitations.cancel');
});
