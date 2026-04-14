<?php

declare(strict_types=1);

use App\Jobs\CheckUpcomingDeadlines;
use App\Models\Project;
use App\Models\ProjectColumn;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\DeadlineApproachingNotification;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);

    $this->tenant = Tenant::factory()->create();
    $this->user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'is_active' => true,
    ]);
    $this->user->assignRole('member');

    $workspace = Workspace::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->project = Project::factory()->create([
        'tenant_id' => $this->tenant->id,
        'workspace_id' => $workspace->id,
    ]);
    $this->column = ProjectColumn::factory()->create(['project_id' => $this->project->id]);
});

test('sends notification for tasks with deadline within 24 hours', function (): void {
    Notification::fake();

    $task = Task::factory()->create([
        'tenant_id' => $this->tenant->id,
        'project_id' => $this->project->id,
        'project_column_id' => $this->column->id,
        'deadline' => now()->addHours(12),
    ]);
    $task->assignees()->attach($this->user->id, ['assigned_at' => now()]);

    (new CheckUpcomingDeadlines)->handle();

    Notification::assertSentTo($this->user, DeadlineApproachingNotification::class);
});

test('does not send notification for tasks with deadline beyond 24 hours', function (): void {
    Notification::fake();

    $task = Task::factory()->create([
        'tenant_id' => $this->tenant->id,
        'project_id' => $this->project->id,
        'project_column_id' => $this->column->id,
        'deadline' => now()->addDays(3),
    ]);
    $task->assignees()->attach($this->user->id, ['assigned_at' => now()]);

    (new CheckUpcomingDeadlines)->handle();

    Notification::assertNotSentTo($this->user, DeadlineApproachingNotification::class);
});

test('does not send notification for tasks without deadline', function (): void {
    Notification::fake();

    $task = Task::factory()->create([
        'tenant_id' => $this->tenant->id,
        'project_id' => $this->project->id,
        'project_column_id' => $this->column->id,
        'deadline' => null,
    ]);
    $task->assignees()->attach($this->user->id, ['assigned_at' => now()]);

    (new CheckUpcomingDeadlines)->handle();

    Notification::assertNotSentTo($this->user, DeadlineApproachingNotification::class);
});

test('does not send duplicate notification', function (): void {
    $task = Task::factory()->create([
        'tenant_id' => $this->tenant->id,
        'project_id' => $this->project->id,
        'project_column_id' => $this->column->id,
        'deadline' => now()->addHours(12),
    ]);
    $task->assignees()->attach($this->user->id, ['assigned_at' => now()]);

    // Simulate a previously sent notification (persisted to DB)
    $this->user->notify(new DeadlineApproachingNotification($task));

    // Now fake and run the job — should not send again
    Notification::fake();
    (new CheckUpcomingDeadlines)->handle();

    Notification::assertNotSentTo($this->user, DeadlineApproachingNotification::class);
});

test('does not send notification for past deadlines', function (): void {
    Notification::fake();

    $task = Task::factory()->create([
        'tenant_id' => $this->tenant->id,
        'project_id' => $this->project->id,
        'project_column_id' => $this->column->id,
        'deadline' => now()->subHour(),
    ]);
    $task->assignees()->attach($this->user->id, ['assigned_at' => now()]);

    (new CheckUpcomingDeadlines)->handle();

    Notification::assertNotSentTo($this->user, DeadlineApproachingNotification::class);
});
