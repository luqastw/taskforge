<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\ProjectColumn;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    $this->viewer = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'is_active' => true,
    ]);
    $this->viewer->assignRole('viewer');

    $this->workspace = Workspace::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $this->project = Project::factory()->create([
        'tenant_id' => $this->tenant->id,
        'workspace_id' => $this->workspace->id,
    ]);

    // Add owner and member to workspace and project
    $this->workspace->members()->attach([$this->owner->id, $this->member->id], ['joined_at' => now()]);
    $this->project->members()->attach([$this->owner->id, $this->member->id], ['joined_at' => now()]);

    $this->column = ProjectColumn::factory()->create([
        'project_id' => $this->project->id,
    ]);

    $this->task = Task::factory()->create([
        'tenant_id' => $this->tenant->id,
        'project_id' => $this->project->id,
        'project_column_id' => $this->column->id,
        'title' => 'Test Task',
    ]);
});

// ===== INDEX TESTS =====

test('can list task assignees', function (): void {
    $this->task->assignees()->attach($this->member->id, ['assigned_at' => now()]);

    Sanctum::actingAs($this->owner);

    $response = $this->getJson("/api/tasks/{$this->task->id}/assignees");

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data');
});

test('empty assignees returns empty list', function (): void {
    Sanctum::actingAs($this->owner);

    $response = $this->getJson("/api/tasks/{$this->task->id}/assignees");

    $response->assertStatus(200)
        ->assertJsonCount(0, 'data');
});

// ===== STORE TESTS =====

test('owner can assign user to task', function (): void {
    Sanctum::actingAs($this->owner);

    $response = $this->postJson("/api/tasks/{$this->task->id}/assignees", [
        'user_id' => $this->member->id,
    ]);

    $response->assertStatus(201);

    expect($this->task->assignees()->where('user_id', $this->member->id)->exists())->toBeTrue();
});

test('member can assign user to task (has task.assign permission)', function (): void {
    Sanctum::actingAs($this->member);

    $response = $this->postJson("/api/tasks/{$this->task->id}/assignees", [
        'user_id' => $this->owner->id,
    ]);

    $response->assertStatus(201);
});

test('viewer cannot assign user to task', function (): void {
    Sanctum::actingAs($this->viewer);

    $response = $this->postJson("/api/tasks/{$this->task->id}/assignees", [
        'user_id' => $this->member->id,
    ]);

    $response->assertStatus(403);
});

test('cannot assign user who is not a project member', function (): void {
    $nonMember = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'is_active' => true,
    ]);
    $nonMember->assignRole('member');

    Sanctum::actingAs($this->owner);

    $response = $this->postJson("/api/tasks/{$this->task->id}/assignees", [
        'user_id' => $nonMember->id,
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('message', 'User must be a member of the project to be assigned');
});

test('cannot assign same user twice', function (): void {
    $this->task->assignees()->attach($this->member->id, ['assigned_at' => now()]);

    Sanctum::actingAs($this->owner);

    $response = $this->postJson("/api/tasks/{$this->task->id}/assignees", [
        'user_id' => $this->member->id,
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('message', 'User is already assigned to this task');
});

test('cannot assign user from another tenant', function (): void {
    $otherTenant = Tenant::factory()->create();
    $otherUser = User::factory()->create([
        'tenant_id' => $otherTenant->id,
    ]);

    Sanctum::actingAs($this->owner);

    $response = $this->postJson("/api/tasks/{$this->task->id}/assignees", [
        'user_id' => $otherUser->id,
    ]);

    $response->assertStatus(404);
});

// ===== BULK ASSIGN TESTS =====

test('can bulk assign users to task', function (): void {
    Sanctum::actingAs($this->owner);

    $response = $this->postJson("/api/tasks/{$this->task->id}/assignees/bulk", [
        'user_ids' => [$this->owner->id, $this->member->id],
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.assigned', 2);

    expect($this->task->assignees()->count())->toBe(2);
});

test('bulk assign skips non-project members', function (): void {
    $nonMember = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'is_active' => true,
    ]);
    $nonMember->assignRole('member');

    Sanctum::actingAs($this->owner);

    $response = $this->postJson("/api/tasks/{$this->task->id}/assignees/bulk", [
        'user_ids' => [$this->member->id, $nonMember->id],
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.assigned', 1)
        ->assertJsonPath('data.not_project_members', 1);
});

// ===== DESTROY TESTS =====

test('owner can unassign user from task', function (): void {
    $this->task->assignees()->attach($this->member->id, ['assigned_at' => now()]);

    Sanctum::actingAs($this->owner);

    $response = $this->deleteJson("/api/tasks/{$this->task->id}/assignees/{$this->member->id}");

    $response->assertStatus(200);

    expect($this->task->assignees()->where('user_id', $this->member->id)->exists())->toBeFalse();
});

test('cannot unassign user who is not assigned', function (): void {
    Sanctum::actingAs($this->owner);

    $response = $this->deleteJson("/api/tasks/{$this->task->id}/assignees/{$this->member->id}");

    $response->assertStatus(422);
});

test('viewer cannot unassign user from task', function (): void {
    $this->task->assignees()->attach($this->member->id, ['assigned_at' => now()]);

    Sanctum::actingAs($this->viewer);

    $response = $this->deleteJson("/api/tasks/{$this->task->id}/assignees/{$this->member->id}");

    $response->assertStatus(403);
});

// ===== ACTIVITY LOG TESTS =====

test('assigning user is logged', function (): void {
    Sanctum::actingAs($this->owner);

    $this->postJson("/api/tasks/{$this->task->id}/assignees", [
        'user_id' => $this->member->id,
    ]);

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'task_assignee_added',
        'subject_type' => Task::class,
        'subject_id' => $this->task->id,
    ]);
});
