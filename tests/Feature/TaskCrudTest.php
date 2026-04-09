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

    $this->column = ProjectColumn::factory()->create([
        'project_id' => $this->project->id,
        'name' => 'To Do',
        'order' => 1,
    ]);

    $this->task = Task::factory()->create([
        'tenant_id' => $this->tenant->id,
        'project_id' => $this->project->id,
        'project_column_id' => $this->column->id,
        'title' => 'Test Task',
        'priority' => 'medium',
    ]);
});

// ===== INDEX TESTS =====

test('owner can list tasks', function (): void {
    Sanctum::actingAs($this->owner);

    $response = $this->getJson('/api/tasks');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'title', 'description', 'priority', 'project_id', 'project_column_id', 'order', 'deadline', 'created_at', 'updated_at'],
            ],
            'links',
            'meta',
        ]);
});

test('member can list tasks', function (): void {
    Sanctum::actingAs($this->member);

    $response = $this->getJson('/api/tasks');

    $response->assertStatus(200);
});

test('viewer can list tasks', function (): void {
    Sanctum::actingAs($this->viewer);

    $response = $this->getJson('/api/tasks');

    $response->assertStatus(200);
});

test('tasks are scoped to tenant', function (): void {
    $otherTenant = Tenant::factory()->create();
    $otherWorkspace = Workspace::factory()->create(['tenant_id' => $otherTenant->id]);
    $otherProject = Project::factory()->create([
        'tenant_id' => $otherTenant->id,
        'workspace_id' => $otherWorkspace->id,
    ]);
    $otherColumn = ProjectColumn::factory()->create(['project_id' => $otherProject->id]);
    $otherTask = Task::factory()->create([
        'tenant_id' => $otherTenant->id,
        'project_id' => $otherProject->id,
        'project_column_id' => $otherColumn->id,
    ]);

    Sanctum::actingAs($this->owner);

    $response = $this->getJson('/api/tasks');
    $taskIds = collect($response->json('data'))->pluck('id');

    expect($taskIds)->toContain($this->task->id)
        ->and($taskIds)->not->toContain($otherTask->id);
});

test('can filter tasks by project_id', function (): void {
    Sanctum::actingAs($this->owner);

    $response = $this->getJson("/api/tasks?project_id={$this->project->id}");

    $response->assertStatus(200);
    $projectIds = collect($response->json('data'))->pluck('project_id')->unique();
    expect($projectIds)->toContain($this->project->id);
});

test('can filter tasks by priority', function (): void {
    Task::factory()->create([
        'tenant_id' => $this->tenant->id,
        'project_id' => $this->project->id,
        'project_column_id' => $this->column->id,
        'priority' => 'urgent',
    ]);

    Sanctum::actingAs($this->owner);

    $response = $this->getJson('/api/tasks?priority=urgent');

    $priorities = collect($response->json('data'))->pluck('priority')->unique();
    expect($priorities->toArray())->toBe(['urgent']);
});

test('can filter tasks by column', function (): void {
    Sanctum::actingAs($this->owner);

    $response = $this->getJson("/api/tasks?project_column_id={$this->column->id}");

    $response->assertStatus(200);
    $columnIds = collect($response->json('data'))->pluck('project_column_id')->unique();
    expect($columnIds)->toContain($this->column->id);
});

// ===== SHOW TESTS =====

test('owner can view task', function (): void {
    Sanctum::actingAs($this->owner);

    $response = $this->getJson("/api/tasks/{$this->task->id}");

    $response->assertStatus(200)
        ->assertJsonPath('data.id', $this->task->id)
        ->assertJsonPath('data.title', 'Test Task');
});

test('cannot view task from other tenant', function (): void {
    $otherTenant = Tenant::factory()->create();
    $otherWorkspace = Workspace::factory()->create(['tenant_id' => $otherTenant->id]);
    $otherProject = Project::factory()->create([
        'tenant_id' => $otherTenant->id,
        'workspace_id' => $otherWorkspace->id,
    ]);
    $otherColumn = ProjectColumn::factory()->create(['project_id' => $otherProject->id]);
    $otherTask = Task::factory()->create([
        'tenant_id' => $otherTenant->id,
        'project_id' => $otherProject->id,
        'project_column_id' => $otherColumn->id,
    ]);

    Sanctum::actingAs($this->owner);

    $response = $this->getJson("/api/tasks/{$otherTask->id}");

    $response->assertStatus(404);
});

// ===== STORE TESTS =====

test('owner can create task', function (): void {
    Sanctum::actingAs($this->owner);

    $response = $this->postJson('/api/tasks', [
        'project_id' => $this->project->id,
        'project_column_id' => $this->column->id,
        'title' => 'New Task',
        'description' => 'A new task description',
        'priority' => 'high',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.title', 'New Task')
        ->assertJsonPath('data.priority', 'high');

    $this->assertDatabaseHas('tasks', [
        'title' => 'New Task',
        'tenant_id' => $this->tenant->id,
        'project_id' => $this->project->id,
    ]);
});

test('member can create task', function (): void {
    Sanctum::actingAs($this->member);

    $response = $this->postJson('/api/tasks', [
        'project_id' => $this->project->id,
        'project_column_id' => $this->column->id,
        'title' => 'Member Task',
    ]);

    $response->assertStatus(201);
});

test('viewer cannot create task', function (): void {
    Sanctum::actingAs($this->viewer);

    $response = $this->postJson('/api/tasks', [
        'project_id' => $this->project->id,
        'project_column_id' => $this->column->id,
        'title' => 'Viewer Task',
    ]);

    $response->assertStatus(403);
});

test('task title is required', function (): void {
    Sanctum::actingAs($this->owner);

    $response = $this->postJson('/api/tasks', [
        'project_id' => $this->project->id,
        'project_column_id' => $this->column->id,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['title']);
});

test('can create subtask', function (): void {
    Sanctum::actingAs($this->owner);

    $response = $this->postJson('/api/tasks', [
        'project_id' => $this->project->id,
        'project_column_id' => $this->column->id,
        'parent_id' => $this->task->id,
        'title' => 'Subtask',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.parent_id', $this->task->id);
});

test('task requires valid project_id', function (): void {
    Sanctum::actingAs($this->owner);

    $response = $this->postJson('/api/tasks', [
        'project_id' => 99999,
        'project_column_id' => $this->column->id,
        'title' => 'Invalid Project Task',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['project_id']);
});

test('task requires valid column_id', function (): void {
    Sanctum::actingAs($this->owner);

    $response = $this->postJson('/api/tasks', [
        'project_id' => $this->project->id,
        'project_column_id' => 99999,
        'title' => 'Invalid Column Task',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['project_column_id']);
});

// ===== UPDATE TESTS =====

test('owner can update task', function (): void {
    Sanctum::actingAs($this->owner);

    $response = $this->putJson("/api/tasks/{$this->task->id}", [
        'title' => 'Updated Task Title',
        'priority' => 'urgent',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.title', 'Updated Task Title')
        ->assertJsonPath('data.priority', 'urgent');
});

test('member can update task', function (): void {
    Sanctum::actingAs($this->member);

    $response = $this->putJson("/api/tasks/{$this->task->id}", [
        'title' => 'Member Updated',
    ]);

    $response->assertStatus(200);
});

test('viewer cannot update task', function (): void {
    Sanctum::actingAs($this->viewer);

    $response = $this->putJson("/api/tasks/{$this->task->id}", [
        'title' => 'Viewer Update',
    ]);

    $response->assertStatus(403);
});

test('can move task to another column', function (): void {
    $newColumn = ProjectColumn::factory()->create([
        'project_id' => $this->project->id,
        'name' => 'Done',
        'order' => 2,
    ]);

    Sanctum::actingAs($this->owner);

    $response = $this->putJson("/api/tasks/{$this->task->id}", [
        'project_column_id' => $newColumn->id,
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.project_column_id', $newColumn->id);
});

test('cannot update task from other tenant', function (): void {
    $otherTenant = Tenant::factory()->create();
    $otherWorkspace = Workspace::factory()->create(['tenant_id' => $otherTenant->id]);
    $otherProject = Project::factory()->create([
        'tenant_id' => $otherTenant->id,
        'workspace_id' => $otherWorkspace->id,
    ]);
    $otherColumn = ProjectColumn::factory()->create(['project_id' => $otherProject->id]);
    $otherTask = Task::factory()->create([
        'tenant_id' => $otherTenant->id,
        'project_id' => $otherProject->id,
        'project_column_id' => $otherColumn->id,
    ]);

    Sanctum::actingAs($this->owner);

    $response = $this->putJson("/api/tasks/{$otherTask->id}", [
        'title' => 'Hacked',
    ]);

    $response->assertStatus(404);
});

// ===== DELETE TESTS =====

test('owner can delete task', function (): void {
    Sanctum::actingAs($this->owner);

    $response = $this->deleteJson("/api/tasks/{$this->task->id}");

    $response->assertStatus(204);

    $this->assertSoftDeleted('tasks', [
        'id' => $this->task->id,
    ]);
});

test('member can delete task', function (): void {
    $taskToDelete = Task::factory()->create([
        'tenant_id' => $this->tenant->id,
        'project_id' => $this->project->id,
        'project_column_id' => $this->column->id,
    ]);

    Sanctum::actingAs($this->member);

    $response = $this->deleteJson("/api/tasks/{$taskToDelete->id}");

    $response->assertStatus(204);
});

test('viewer cannot delete task', function (): void {
    Sanctum::actingAs($this->viewer);

    $response = $this->deleteJson("/api/tasks/{$this->task->id}");

    $response->assertStatus(403);
});

test('cannot delete task from other tenant', function (): void {
    $otherTenant = Tenant::factory()->create();
    $otherWorkspace = Workspace::factory()->create(['tenant_id' => $otherTenant->id]);
    $otherProject = Project::factory()->create([
        'tenant_id' => $otherTenant->id,
        'workspace_id' => $otherWorkspace->id,
    ]);
    $otherColumn = ProjectColumn::factory()->create(['project_id' => $otherProject->id]);
    $otherTask = Task::factory()->create([
        'tenant_id' => $otherTenant->id,
        'project_id' => $otherProject->id,
        'project_column_id' => $otherColumn->id,
    ]);

    Sanctum::actingAs($this->owner);

    $response = $this->deleteJson("/api/tasks/{$otherTask->id}");

    $response->assertStatus(404);
});

// ===== ACTIVITY LOG TESTS =====

test('task creation is logged', function (): void {
    Sanctum::actingAs($this->owner);

    $this->postJson('/api/tasks', [
        'project_id' => $this->project->id,
        'project_column_id' => $this->column->id,
        'title' => 'Logged Task',
    ]);

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'default',
        'description' => 'created',
        'subject_type' => Task::class,
    ]);
});

test('task update is logged', function (): void {
    Sanctum::actingAs($this->owner);

    $this->putJson("/api/tasks/{$this->task->id}", [
        'title' => 'Updated Title',
    ]);

    $this->assertDatabaseHas('activity_log', [
        'subject_id' => $this->task->id,
        'subject_type' => Task::class,
        'description' => 'updated',
    ]);
});
