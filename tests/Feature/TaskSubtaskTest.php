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

    $this->workspace = Workspace::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $this->project = Project::factory()->create([
        'tenant_id' => $this->tenant->id,
        'workspace_id' => $this->workspace->id,
    ]);

    $this->column = ProjectColumn::factory()->create([
        'project_id' => $this->project->id,
    ]);

    $this->parentTask = Task::factory()->create([
        'tenant_id' => $this->tenant->id,
        'project_id' => $this->project->id,
        'project_column_id' => $this->column->id,
        'title' => 'Parent Task',
    ]);
});

// ===== LIST SUBTASKS =====

test('can list subtasks of a task', function (): void {
    Task::factory()->count(3)->create([
        'tenant_id' => $this->tenant->id,
        'project_id' => $this->project->id,
        'project_column_id' => $this->column->id,
        'parent_id' => $this->parentTask->id,
    ]);

    Sanctum::actingAs($this->owner);

    $response = $this->getJson("/api/tasks/{$this->parentTask->id}/subtasks");

    $response->assertStatus(200)
        ->assertJsonCount(3, 'data');
});

test('subtasks list is empty when no subtasks exist', function (): void {
    Sanctum::actingAs($this->owner);

    $response = $this->getJson("/api/tasks/{$this->parentTask->id}/subtasks");

    $response->assertStatus(200)
        ->assertJsonCount(0, 'data');
});

test('subtasks are ordered by order field', function (): void {
    Task::factory()->create([
        'tenant_id' => $this->tenant->id,
        'project_id' => $this->project->id,
        'project_column_id' => $this->column->id,
        'parent_id' => $this->parentTask->id,
        'title' => 'Third',
        'order' => 3,
    ]);
    Task::factory()->create([
        'tenant_id' => $this->tenant->id,
        'project_id' => $this->project->id,
        'project_column_id' => $this->column->id,
        'parent_id' => $this->parentTask->id,
        'title' => 'First',
        'order' => 1,
    ]);
    Task::factory()->create([
        'tenant_id' => $this->tenant->id,
        'project_id' => $this->project->id,
        'project_column_id' => $this->column->id,
        'parent_id' => $this->parentTask->id,
        'title' => 'Second',
        'order' => 2,
    ]);

    Sanctum::actingAs($this->owner);

    $response = $this->getJson("/api/tasks/{$this->parentTask->id}/subtasks");

    $titles = collect($response->json('data'))->pluck('title')->toArray();
    expect($titles)->toBe(['First', 'Second', 'Third']);
});

// ===== CREATE SUBTASK =====

test('can create subtask for a task', function (): void {
    Sanctum::actingAs($this->owner);

    $response = $this->postJson('/api/tasks', [
        'project_id' => $this->project->id,
        'project_column_id' => $this->column->id,
        'parent_id' => $this->parentTask->id,
        'title' => 'My Subtask',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.parent_id', $this->parentTask->id)
        ->assertJsonPath('data.project_id', $this->project->id);
});

test('subtask must belong to same project as parent', function (): void {
    $otherProject = Project::factory()->create([
        'tenant_id' => $this->tenant->id,
        'workspace_id' => $this->workspace->id,
    ]);
    $otherColumn = ProjectColumn::factory()->create([
        'project_id' => $otherProject->id,
    ]);

    Sanctum::actingAs($this->owner);

    $response = $this->postJson('/api/tasks', [
        'project_id' => $otherProject->id,
        'project_column_id' => $otherColumn->id,
        'parent_id' => $this->parentTask->id,
        'title' => 'Cross-project subtask',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['parent_id']);
});

// ===== FILTER BY PARENT =====

test('can filter tasks by parent_id', function (): void {
    Task::factory()->count(2)->create([
        'tenant_id' => $this->tenant->id,
        'project_id' => $this->project->id,
        'project_column_id' => $this->column->id,
        'parent_id' => $this->parentTask->id,
    ]);

    // Root-level task (no parent)
    Task::factory()->create([
        'tenant_id' => $this->tenant->id,
        'project_id' => $this->project->id,
        'project_column_id' => $this->column->id,
        'parent_id' => null,
        'title' => 'Another root task',
    ]);

    Sanctum::actingAs($this->owner);

    $response = $this->getJson("/api/tasks?parent_id={$this->parentTask->id}");

    $response->assertStatus(200)
        ->assertJsonCount(2, 'data');
});

test('subtask inherits tenant from parent', function (): void {
    Sanctum::actingAs($this->owner);

    $response = $this->postJson('/api/tasks', [
        'project_id' => $this->project->id,
        'project_column_id' => $this->column->id,
        'parent_id' => $this->parentTask->id,
        'title' => 'Subtask with tenant',
    ]);

    $response->assertStatus(201);

    $subtask = Task::find($response->json('data.id'));
    expect($subtask->tenant_id)->toBe($this->tenant->id);
});
