<?php

declare(strict_types=1);

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\NotificationPreferenceController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectColumnController;
use App\Http\Controllers\ProjectMemberController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\TaskAssigneeController;
use App\Http\Controllers\TaskAttachmentController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TaskTagController;
use App\Http\Controllers\TenantController;
use App\Http\Controllers\WorkspaceController;
use App\Http\Controllers\WorkspaceMemberController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/auth/register', [AuthController::class, 'register'])->name('auth.register');
Route::post('/auth/login', [AuthController::class, 'login'])
    ->middleware('throttle:login')
    ->name('auth.login');
Route::post('/invitations/accept', [InvitationController::class, 'accept'])->name('invitations.accept');

// Protected routes
Route::middleware(['auth:sanctum', 'tenant', 'throttle:api'])->group(function () {
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

    // Workspace members routes
    Route::get('/workspaces/{workspace}/members', [WorkspaceMemberController::class, 'index'])
        ->name('workspaces.members.index');
    Route::post('/workspaces/{workspace}/members', [WorkspaceMemberController::class, 'store'])
        ->name('workspaces.members.store');
    Route::post('/workspaces/{workspace}/members/bulk', [WorkspaceMemberController::class, 'addMultiple'])
        ->name('workspaces.members.bulk');
    Route::delete('/workspaces/{workspace}/members/{user}', [WorkspaceMemberController::class, 'destroy'])
        ->name('workspaces.members.destroy');

    // Project routes
    Route::apiResource('projects', ProjectController::class);

    // Project members routes
    Route::get('/projects/{project}/members', [ProjectMemberController::class, 'index'])
        ->name('projects.members.index');
    Route::post('/projects/{project}/members', [ProjectMemberController::class, 'store'])
        ->name('projects.members.store');
    Route::post('/projects/{project}/members/bulk', [ProjectMemberController::class, 'addMultiple'])
        ->name('projects.members.bulk');
    Route::delete('/projects/{project}/members/{user}', [ProjectMemberController::class, 'destroy'])
        ->name('projects.members.destroy');

    // Project column routes
    Route::get('/projects/{project}/columns', [ProjectColumnController::class, 'index'])
        ->name('projects.columns.index');
    Route::post('/projects/{project}/columns', [ProjectColumnController::class, 'store'])
        ->name('projects.columns.store');
    Route::get('/projects/{project}/columns/{column}', [ProjectColumnController::class, 'show'])
        ->name('projects.columns.show');
    Route::put('/projects/{project}/columns/{column}', [ProjectColumnController::class, 'update'])
        ->name('projects.columns.update');
    Route::delete('/projects/{project}/columns/{column}', [ProjectColumnController::class, 'destroy'])
        ->name('projects.columns.destroy');
    Route::post('/projects/{project}/columns/reorder', [ProjectColumnController::class, 'reorder'])
        ->name('projects.columns.reorder');

    // Task routes
    Route::apiResource('tasks', TaskController::class);

    // Task subtasks
    Route::get('/tasks/{task}/subtasks', [TaskController::class, 'subtasks'])
        ->name('tasks.subtasks');

    // Task assignees routes
    Route::get('/tasks/{task}/assignees', [TaskAssigneeController::class, 'index'])
        ->name('tasks.assignees.index');
    Route::post('/tasks/{task}/assignees', [TaskAssigneeController::class, 'store'])
        ->name('tasks.assignees.store');
    Route::post('/tasks/{task}/assignees/bulk', [TaskAssigneeController::class, 'storeBulk'])
        ->name('tasks.assignees.bulk');
    Route::delete('/tasks/{task}/assignees/{user}', [TaskAssigneeController::class, 'destroy'])
        ->name('tasks.assignees.destroy');

    // Task tags routes
    Route::get('/tasks/{task}/tags', [TaskTagController::class, 'index'])
        ->name('tasks.tags.index');
    Route::post('/tasks/{task}/tags', [TaskTagController::class, 'store'])
        ->name('tasks.tags.store');
    Route::delete('/tasks/{task}/tags/{tag}', [TaskTagController::class, 'destroy'])
        ->name('tasks.tags.destroy');

    // Task comments routes
    Route::get('/tasks/{task}/comments', [CommentController::class, 'index'])
        ->name('tasks.comments.index');
    Route::post('/tasks/{task}/comments', [CommentController::class, 'store'])
        ->name('tasks.comments.store');
    Route::put('/tasks/{task}/comments/{comment}', [CommentController::class, 'update'])
        ->name('tasks.comments.update');
    Route::delete('/tasks/{task}/comments/{comment}', [CommentController::class, 'destroy'])
        ->name('tasks.comments.destroy');

    // Task attachments routes
    Route::get('/tasks/{task}/attachments', [TaskAttachmentController::class, 'index'])
        ->name('tasks.attachments.index');
    Route::post('/tasks/{task}/attachments', [TaskAttachmentController::class, 'store'])
        ->name('tasks.attachments.store');
    Route::delete('/tasks/{task}/attachments/{media}', [TaskAttachmentController::class, 'destroy'])
        ->name('tasks.attachments.destroy');

    // Tag routes
    Route::apiResource('tags', TagController::class);

    // Activity log routes
    Route::get('/tasks/{task}/activity', [ActivityLogController::class, 'taskHistory'])
        ->name('tasks.activity');
    Route::get('/projects/{project}/activity', [ActivityLogController::class, 'projectHistory'])
        ->name('projects.activity');

    // Notification routes
    Route::get('/notifications', [NotificationController::class, 'index'])
        ->name('notifications.index');
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount'])
        ->name('notifications.unread-count');
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])
        ->name('notifications.read-all');
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])
        ->name('notifications.read');
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy'])
        ->name('notifications.destroy');

    // Notification preferences routes
    Route::get('/notification-preferences', [NotificationPreferenceController::class, 'show'])
        ->name('notification-preferences.show');
    Route::put('/notification-preferences', [NotificationPreferenceController::class, 'update'])
        ->name('notification-preferences.update');
});
