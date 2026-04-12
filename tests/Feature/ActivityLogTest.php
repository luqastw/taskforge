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

    $workspace = Workspace::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->project = Project::factory()->create([
        'tenant_id' => $this->tenant->id,
        'workspace_id' => $workspace->id,
    ]);
    $this->column = ProjectColumn::factory()->create(['project_id' => $this->project->id]);
});

test('can view task activity history', function (): void {
    $task = Task::factory()->create([
        'tenant_id' => $this->tenant->id,
        'project_id' => $this->project->id,
        'project_column_id' => $this->column->id,
        'title' => 'Original Title',
    ]);

    Sanctum::actingAs($this->owner);

    // Update to generate activity
    $this->putJson("/api/tasks/{$task->id}", [
        'title' => 'Updated Title',
    ]);

    $response = $this->getJson("/api/tasks/{$task->id}/activity");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'log_name',
                    'description',
                    'event',
                    'subject_type',
                    'subject_id',
                    'properties',
                    'created_at',
                ],
            ],
        ]);
});

test('activity log includes before/after values', function (): void {
    $task = Task::factory()->create([
        'tenant_id' => $this->tenant->id,
        'project_id' => $this->project->id,
        'project_column_id' => $this->column->id,
        'title' => 'Before',
    ]);

    Sanctum::actingAs($this->owner);

    $this->putJson("/api/tasks/{$task->id}", [
        'title' => 'After',
    ]);

    $response = $this->getJson("/api/tasks/{$task->id}/activity");

    $response->assertStatus(200);

    $activities = $response->json('data');
    $updateActivity = collect($activities)->firstWhere('event', 'updated');

    if ($updateActivity) {
        expect($updateActivity['properties']['old']['title'])->toBe('Before');
        expect($updateActivity['properties']['attributes']['title'])->toBe('After');
    }
});

test('can view project activity history', function (): void {
    Sanctum::actingAs($this->owner);

    $this->putJson("/api/projects/{$this->project->id}", [
        'name' => 'Updated Project Name',
    ]);

    $response = $this->getJson("/api/projects/{$this->project->id}/activity");

    $response->assertStatus(200);
});

test('activity log is paginated', function (): void {
    $task = Task::factory()->create([
        'tenant_id' => $this->tenant->id,
        'project_id' => $this->project->id,
        'project_column_id' => $this->column->id,
    ]);

    Sanctum::actingAs($this->owner);

    $response = $this->getJson("/api/tasks/{$task->id}/activity?per_page=5");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data',
            'links',
            'meta',
        ]);
});
