<?php

declare(strict_types=1);

use App\Models\Comment;
use App\Models\Project;
use App\Models\ProjectColumn;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\DeadlineApproachingNotification;
use App\Notifications\MentionedInCommentNotification;
use App\Notifications\TaskAssignedNotification;
use App\Notifications\TaskStatusChangedNotification;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);

    $this->tenant = Tenant::factory()->create();
    $this->owner = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'is_active' => true,
    ]);
    $this->owner->assignRole('owner');

    $this->member = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'is_active' => true,
    ]);
    $this->member->assignRole('member');

    $workspace = Workspace::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->project = Project::factory()->create([
        'tenant_id' => $this->tenant->id,
        'workspace_id' => $workspace->id,
    ]);
    $this->project->members()->attach([$this->owner->id, $this->member->id], ['joined_at' => now()]);

    $this->column = ProjectColumn::factory()->create(['project_id' => $this->project->id, 'name' => 'To Do']);
    $this->column2 = ProjectColumn::factory()->create(['project_id' => $this->project->id, 'name' => 'In Progress']);

    $this->task = Task::factory()->create([
        'tenant_id' => $this->tenant->id,
        'project_id' => $this->project->id,
        'project_column_id' => $this->column->id,
    ]);
});

// ===== TASK ASSIGNED NOTIFICATION =====

test('assigning user to task sends notification', function (): void {
    Notification::fake();

    Sanctum::actingAs($this->owner);

    $this->postJson("/api/tasks/{$this->task->id}/assignees", [
        'user_id' => $this->member->id,
    ]);

    Notification::assertSentTo($this->member, TaskAssignedNotification::class);
});

test('bulk assigning sends notification to each user', function (): void {
    Notification::fake();

    Sanctum::actingAs($this->owner);

    $this->postJson("/api/tasks/{$this->task->id}/assignees/bulk", [
        'user_ids' => [$this->member->id],
    ]);

    Notification::assertSentTo($this->member, TaskAssignedNotification::class);
});

// ===== MENTION NOTIFICATION =====

test('mentioning user in comment sends notification', function (): void {
    Notification::fake();

    // Use a member with a simple name (no spaces) to match the @mention regex
    $mentionable = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'johndoe',
        'is_active' => true,
    ]);
    $mentionable->assignRole('member');

    Sanctum::actingAs($this->owner);

    $this->postJson("/api/tasks/{$this->task->id}/comments", [
        'content' => 'Hey @johndoe, check this out.',
    ]);

    Notification::assertSentTo($mentionable, MentionedInCommentNotification::class);
});

test('mentioning yourself does not send notification', function (): void {
    Notification::fake();

    // Ensure owner has a simple name
    $this->owner->update(['name' => 'owneruser']);

    Sanctum::actingAs($this->owner);

    $this->postJson("/api/tasks/{$this->task->id}/comments", [
        'content' => 'Note to self @owneruser',
    ]);

    Notification::assertNotSentTo($this->owner, MentionedInCommentNotification::class);
});

// ===== TASK STATUS CHANGED NOTIFICATION =====

test('changing task column notifies assignees', function (): void {
    Notification::fake();

    $this->task->assignees()->attach($this->member->id, ['assigned_at' => now()]);

    Sanctum::actingAs($this->owner);

    $this->putJson("/api/tasks/{$this->task->id}", [
        'project_column_id' => $this->column2->id,
    ]);

    Notification::assertSentTo($this->member, TaskStatusChangedNotification::class);
});

test('changing task column does not notify the user who made the change', function (): void {
    Notification::fake();

    $this->task->assignees()->attach($this->owner->id, ['assigned_at' => now()]);

    Sanctum::actingAs($this->owner);

    $this->putJson("/api/tasks/{$this->task->id}", [
        'project_column_id' => $this->column2->id,
    ]);

    Notification::assertNotSentTo($this->owner, TaskStatusChangedNotification::class);
});

// ===== NOTIFICATION ENDPOINTS =====

test('can list notifications', function (): void {
    Sanctum::actingAs($this->member);

    // Clear any existing notifications
    $this->member->notifications()->delete();

    $this->member->notify(new TaskAssignedNotification($this->task, $this->owner));

    $response = $this->getJson('/api/notifications');

    $response->assertStatus(200);
    expect($response->json('data.data'))->toHaveCount(1);
});

test('can filter unread notifications', function (): void {
    Sanctum::actingAs($this->member);

    // Clear any existing notifications
    $this->member->notifications()->delete();

    $this->member->notify(new TaskAssignedNotification($this->task, $this->owner));

    $notification = $this->member->notifications()->first();
    $notification->markAsRead();

    $response = $this->getJson('/api/notifications?unread_only=1');

    $response->assertStatus(200);
    expect($response->json('data.data'))->toHaveCount(0);
});

test('can get unread count', function (): void {
    Sanctum::actingAs($this->member);

    $this->member->notify(new TaskAssignedNotification($this->task, $this->owner));

    $response = $this->getJson('/api/notifications/unread-count');

    $response->assertStatus(200)
        ->assertJsonPath('data.unread_count', 1);
});

test('can mark notification as read', function (): void {
    Sanctum::actingAs($this->member);

    $this->member->notify(new TaskAssignedNotification($this->task, $this->owner));

    $notification = $this->member->notifications()->first();

    $response = $this->patchJson("/api/notifications/{$notification->id}/read");

    $response->assertStatus(200);
    expect($notification->fresh()->read_at)->not->toBeNull();
});

test('can mark all notifications as read', function (): void {
    Sanctum::actingAs($this->member);

    $this->member->notify(new TaskAssignedNotification($this->task, $this->owner));
    $this->member->notify(new TaskAssignedNotification($this->task, $this->owner));

    $response = $this->patchJson('/api/notifications/read-all');

    $response->assertStatus(200);
    expect($this->member->unreadNotifications()->count())->toBe(0);
});

test('can delete notification', function (): void {
    Sanctum::actingAs($this->member);

    $this->member->notify(new TaskAssignedNotification($this->task, $this->owner));

    $notification = $this->member->notifications()->first();

    $response = $this->deleteJson("/api/notifications/{$notification->id}");

    $response->assertStatus(204);
    expect($this->member->notifications()->count())->toBe(0);
});
