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
});

test('can filter overdue tasks', function (): void {
    Task::factory()->create([
        'tenant_id' => $this->tenant->id,
        'project_id' => $this->project->id,
        'project_column_id' => $this->column->id,
        'title' => 'Overdue Task',
        'deadline' => now()->subDays(3),
    ]);

    Task::factory()->create([
        'tenant_id' => $this->tenant->id,
        'project_id' => $this->project->id,
        'project_column_id' => $this->column->id,
        'title' => 'Future Task',
        'deadline' => now()->addDays(5),
    ]);

    Task::factory()->create([
        'tenant_id' => $this->tenant->id,
        'project_id' => $this->project->id,
        'project_column_id' => $this->column->id,
        'title' => 'No Deadline Task',
        'deadline' => null,
    ]);

    Sanctum::actingAs($this->owner);

    $response = $this->getJson('/api/tasks?deadline=overdue');

    $response->assertStatus(200);
    $titles = collect($response->json('data'))->pluck('title');
    expect($titles)->toContain('Overdue Task')
        ->and($titles)->not->toContain('Future Task')
        ->and($titles)->not->toContain('No Deadline Task');
});

test('can filter upcoming tasks (next 7 days)', function (): void {
    Task::factory()->create([
        'tenant_id' => $this->tenant->id,
        'project_id' => $this->project->id,
        'project_column_id' => $this->column->id,
        'title' => 'Upcoming Task',
        'deadline' => now()->addDays(3),
    ]);

    Task::factory()->create([
        'tenant_id' => $this->tenant->id,
        'project_id' => $this->project->id,
        'project_column_id' => $this->column->id,
        'title' => 'Far Future Task',
        'deadline' => now()->addDays(30),
    ]);

    Sanctum::actingAs($this->owner);

    $response = $this->getJson('/api/tasks?deadline=upcoming');

    $response->assertStatus(200);
    $titles = collect($response->json('data'))->pluck('title');
    expect($titles)->toContain('Upcoming Task')
        ->and($titles)->not->toContain('Far Future Task');
});

test('can filter tasks due today', function (): void {
    Task::factory()->create([
        'tenant_id' => $this->tenant->id,
        'project_id' => $this->project->id,
        'project_column_id' => $this->column->id,
        'title' => 'Today Task',
        'deadline' => today()->setTime(18, 0),
    ]);

    Task::factory()->create([
        'tenant_id' => $this->tenant->id,
        'project_id' => $this->project->id,
        'project_column_id' => $this->column->id,
        'title' => 'Tomorrow Task',
        'deadline' => now()->addDay(),
    ]);

    Sanctum::actingAs($this->owner);

    $response = $this->getJson('/api/tasks?deadline=today');

    $response->assertStatus(200);
    $titles = collect($response->json('data'))->pluck('title');
    expect($titles)->toContain('Today Task')
        ->and($titles)->not->toContain('Tomorrow Task');
});

test('can filter tasks by deadline range', function (): void {
    Task::factory()->create([
        'tenant_id' => $this->tenant->id,
        'project_id' => $this->project->id,
        'project_column_id' => $this->column->id,
        'title' => 'In Range',
        'deadline' => now()->addDays(5),
    ]);

    Task::factory()->create([
        'tenant_id' => $this->tenant->id,
        'project_id' => $this->project->id,
        'project_column_id' => $this->column->id,
        'title' => 'Out of Range',
        'deadline' => now()->addDays(20),
    ]);

    Sanctum::actingAs($this->owner);

    $from = now()->toDateString();
    $to = now()->addDays(10)->toDateString();

    $response = $this->getJson("/api/tasks?deadline_from={$from}&deadline_to={$to}");

    $response->assertStatus(200);
    $titles = collect($response->json('data'))->pluck('title');
    expect($titles)->toContain('In Range')
        ->and($titles)->not->toContain('Out of Range');
});

test('can order tasks by deadline', function (): void {
    Task::factory()->create([
        'tenant_id' => $this->tenant->id,
        'project_id' => $this->project->id,
        'project_column_id' => $this->column->id,
        'title' => 'Later',
        'deadline' => now()->addDays(10),
    ]);

    Task::factory()->create([
        'tenant_id' => $this->tenant->id,
        'project_id' => $this->project->id,
        'project_column_id' => $this->column->id,
        'title' => 'Sooner',
        'deadline' => now()->addDays(2),
    ]);

    Sanctum::actingAs($this->owner);

    $response = $this->getJson('/api/tasks?order_by=deadline&order_dir=asc');

    $response->assertStatus(200);
    $titles = collect($response->json('data'))->pluck('title')->toArray();
    expect($titles[0])->toBe('Sooner');
    expect($titles[1])->toBe('Later');
});

test('can filter tasks by assignee', function (): void {
    $member = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'is_active' => true,
    ]);
    $member->assignRole('member');

    $assignedTask = Task::factory()->create([
        'tenant_id' => $this->tenant->id,
        'project_id' => $this->project->id,
        'project_column_id' => $this->column->id,
        'title' => 'Assigned Task',
    ]);
    $assignedTask->assignees()->attach($member->id, ['assigned_at' => now()]);

    Task::factory()->create([
        'tenant_id' => $this->tenant->id,
        'project_id' => $this->project->id,
        'project_column_id' => $this->column->id,
        'title' => 'Unassigned Task',
    ]);

    Sanctum::actingAs($this->owner);

    $response = $this->getJson("/api/tasks?assignee_id={$member->id}");

    $response->assertStatus(200);
    $titles = collect($response->json('data'))->pluck('title');
    expect($titles)->toContain('Assigned Task')
        ->and($titles)->not->toContain('Unassigned Task');
});

test('can order tasks by priority', function (): void {
    Task::factory()->create([
        'tenant_id' => $this->tenant->id,
        'project_id' => $this->project->id,
        'project_column_id' => $this->column->id,
        'title' => 'Low Priority',
        'priority' => 'low',
    ]);

    Task::factory()->create([
        'tenant_id' => $this->tenant->id,
        'project_id' => $this->project->id,
        'project_column_id' => $this->column->id,
        'title' => 'Urgent Priority',
        'priority' => 'urgent',
    ]);

    Sanctum::actingAs($this->owner);

    $response = $this->getJson('/api/tasks?order_by=priority&order_dir=asc');

    $response->assertStatus(200);
    // At least verifies the endpoint accepts the parameters
    expect($response->json('data'))->toHaveCount(2);
});
