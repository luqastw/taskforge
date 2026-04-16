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

    app()->make(\App\Services\ProjectColumnService::class)->createDefaultColumns($this->project);
});

// ===== INDEX TESTS =====

test('owner can list project columns', function (): void {
    Sanctum::actingAs($this->owner);

    $response = $this->getJson("/api/projects/{$this->project->id}/columns");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'project_id', 'name', 'color', 'order', 'task_limit', 'created_at', 'updated_at'],
            ],
        ]);
});

test('viewer can list project columns', function (): void {
    Sanctum::actingAs($this->viewer);

    $response = $this->getJson("/api/projects/{$this->project->id}/columns");

    $response->assertStatus(200);
});

test('columns are returned ordered by order field', function (): void {
    ProjectColumn::where('project_id', $this->project->id)->delete();

    ProjectColumn::factory()->create([
        'project_id' => $this->project->id,
        'name' => 'Third',
        'order' => 3,
    ]);
    ProjectColumn::factory()->create([
        'project_id' => $this->project->id,
        'name' => 'First',
        'order' => 1,
    ]);
    ProjectColumn::factory()->create([
        'project_id' => $this->project->id,
        'name' => 'Second',
        'order' => 2,
    ]);

    Sanctum::actingAs($this->owner);

    $response = $this->getJson("/api/projects/{$this->project->id}/columns");

    $names = collect($response->json('data'))->pluck('name')->toArray();
    expect($names)->toBe(['First', 'Second', 'Third']);
});

// ===== STORE TESTS =====

test('owner can create a column', function (): void {
    Sanctum::actingAs($this->owner);

    $response = $this->postJson("/api/projects/{$this->project->id}/columns", [
        'name' => 'Custom Column',
        'color' => '#FF5733',
        'task_limit' => 10,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.name', 'Custom Column')
        ->assertJsonPath('data.color', '#FF5733')
        ->assertJsonPath('data.task_limit', 10);

    $this->assertDatabaseHas('project_columns', [
        'project_id' => $this->project->id,
        'name' => 'Custom Column',
    ]);
});

test('column order auto-increments if not provided', function (): void {
    Sanctum::actingAs($this->owner);

    $maxOrder = ProjectColumn::where('project_id', $this->project->id)->max('order');

    $response = $this->postJson("/api/projects/{$this->project->id}/columns", [
        'name' => 'Auto Order Column',
    ]);

    $response->assertStatus(201);
    expect($response->json('data.order'))->toBe($maxOrder + 1);
});

test('viewer cannot create column', function (): void {
    Sanctum::actingAs($this->viewer);

    $response = $this->postJson("/api/projects/{$this->project->id}/columns", [
        'name' => 'Viewer Column',
    ]);

    $response->assertStatus(403);
});

test('member cannot create column', function (): void {
    Sanctum::actingAs($this->member);

    $response = $this->postJson("/api/projects/{$this->project->id}/columns", [
        'name' => 'Member Column',
    ]);

    $response->assertStatus(403);
});

test('column name is required', function (): void {
    Sanctum::actingAs($this->owner);

    $response = $this->postJson("/api/projects/{$this->project->id}/columns", [
        'color' => '#FF5733',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

// ===== SHOW TESTS =====

test('can view a specific column', function (): void {
    $column = ProjectColumn::where('project_id', $this->project->id)->first();

    Sanctum::actingAs($this->owner);

    $response = $this->getJson("/api/projects/{$this->project->id}/columns/{$column->id}");

    $response->assertStatus(200)
        ->assertJsonPath('data.id', $column->id);
});

test('returns 404 for column not belonging to project', function (): void {
    $otherProject = Project::factory()->create([
        'tenant_id' => $this->tenant->id,
        'workspace_id' => $this->workspace->id,
    ]);
    $otherColumn = ProjectColumn::factory()->create([
        'project_id' => $otherProject->id,
    ]);

    Sanctum::actingAs($this->owner);

    $response = $this->getJson("/api/projects/{$this->project->id}/columns/{$otherColumn->id}");

    $response->assertStatus(404);
});

// ===== UPDATE TESTS =====

test('owner can update a column', function (): void {
    $column = ProjectColumn::where('project_id', $this->project->id)->first();

    Sanctum::actingAs($this->owner);

    $response = $this->putJson("/api/projects/{$this->project->id}/columns/{$column->id}", [
        'name' => 'Updated Column Name',
        'color' => '#00FF00',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.name', 'Updated Column Name')
        ->assertJsonPath('data.color', '#00FF00');
});

test('viewer cannot update a column', function (): void {
    $column = ProjectColumn::where('project_id', $this->project->id)->first();

    Sanctum::actingAs($this->viewer);

    $response = $this->putJson("/api/projects/{$this->project->id}/columns/{$column->id}", [
        'name' => 'Hacked',
    ]);

    $response->assertStatus(403);
});

// ===== DELETE TESTS =====

test('owner can delete an empty column', function (): void {
    $column = ProjectColumn::factory()->create([
        'project_id' => $this->project->id,
        'name' => 'Empty Column',
    ]);

    Sanctum::actingAs($this->owner);

    $response = $this->deleteJson("/api/projects/{$this->project->id}/columns/{$column->id}");

    $response->assertStatus(200);

    $this->assertDatabaseMissing('project_columns', [
        'id' => $column->id,
    ]);
});

test('cannot delete column with tasks', function (): void {
    $column = ProjectColumn::where('project_id', $this->project->id)->first();

    Task::factory()->create([
        'tenant_id' => $this->tenant->id,
        'project_id' => $this->project->id,
        'project_column_id' => $column->id,
    ]);

    Sanctum::actingAs($this->owner);

    $response = $this->deleteJson("/api/projects/{$this->project->id}/columns/{$column->id}");

    $response->assertStatus(422);
});

test('viewer cannot delete column', function (): void {
    $column = ProjectColumn::where('project_id', $this->project->id)->first();

    Sanctum::actingAs($this->viewer);

    $response = $this->deleteJson("/api/projects/{$this->project->id}/columns/{$column->id}");

    $response->assertStatus(403);
});

// ===== REORDER TESTS =====

test('owner can reorder columns', function (): void {
    $columns = ProjectColumn::where('project_id', $this->project->id)
        ->orderBy('order')
        ->get();

    $reversedIds = $columns->pluck('id')->reverse()->values()->toArray();

    Sanctum::actingAs($this->owner);

    $response = $this->postJson("/api/projects/{$this->project->id}/columns/reorder", [
        'column_ids' => $reversedIds,
    ]);

    $response->assertStatus(200);

    $reorderedNames = collect($response->json('data'))->pluck('name')->toArray();
    $originalNames = $columns->pluck('name')->reverse()->values()->toArray();

    expect($reorderedNames)->toBe($originalNames);
});

test('viewer cannot reorder columns', function (): void {
    Sanctum::actingAs($this->viewer);

    $response = $this->postJson("/api/projects/{$this->project->id}/columns/reorder", [
        'column_ids' => [1, 2, 3],
    ]);

    $response->assertStatus(403);
});
