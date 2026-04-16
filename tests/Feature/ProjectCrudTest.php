<?php

declare(strict_types=1);

use App\Models\Project;
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

    $this->admin = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'is_active' => true,
    ]);
    $this->admin->assignRole('admin');

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
        'name' => 'Test Project',
        'status' => 'active',
    ]);

    app()->make(\App\Services\ProjectColumnService::class)->createDefaultColumns($this->project);
});

// ===== INDEX TESTS =====

test('owner can list projects', function (): void {
    Sanctum::actingAs($this->owner);

    $response = $this->getJson('/api/projects');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'description', 'status', 'workspace_id', 'deadline', 'created_at', 'updated_at'],
            ],
            'links',
            'meta',
        ]);
});

test('member can list projects', function (): void {
    Sanctum::actingAs($this->member);

    $response = $this->getJson('/api/projects');

    $response->assertStatus(200);
});

test('viewer can list projects', function (): void {
    Sanctum::actingAs($this->viewer);

    $response = $this->getJson('/api/projects');

    $response->assertStatus(200);
});

test('projects are scoped to tenant', function (): void {
    $otherTenant = Tenant::factory()->create();
    $otherWorkspace = Workspace::factory()->create(['tenant_id' => $otherTenant->id]);
    $otherProject = Project::factory()->create([
        'tenant_id' => $otherTenant->id,
        'workspace_id' => $otherWorkspace->id,
    ]);

    Sanctum::actingAs($this->owner);

    $response = $this->getJson('/api/projects');
    $projectIds = collect($response->json('data'))->pluck('id');

    expect($projectIds)->toContain($this->project->id)
        ->and($projectIds)->not->toContain($otherProject->id);
});

test('can filter projects by workspace_id', function (): void {
    $otherWorkspace = Workspace::factory()->create(['tenant_id' => $this->tenant->id]);
    Project::factory()->create([
        'tenant_id' => $this->tenant->id,
        'workspace_id' => $otherWorkspace->id,
        'name' => 'Other Workspace Project',
    ]);

    Sanctum::actingAs($this->owner);

    $response = $this->getJson("/api/projects?workspace_id={$this->workspace->id}");
    $names = collect($response->json('data'))->pluck('name');

    expect($names)->toContain('Test Project')
        ->and($names)->not->toContain('Other Workspace Project');
});

test('can filter projects by status', function (): void {
    Project::factory()->create([
        'tenant_id' => $this->tenant->id,
        'workspace_id' => $this->workspace->id,
        'status' => 'archived',
    ]);

    Sanctum::actingAs($this->owner);

    $response = $this->getJson('/api/projects?status=active');

    $statuses = collect($response->json('data'))->pluck('status')->unique();
    expect($statuses)->toContain('active')
        ->and($statuses)->not->toContain('archived');
});

test('can filter projects by name', function (): void {
    Project::factory()->create([
        'tenant_id' => $this->tenant->id,
        'workspace_id' => $this->workspace->id,
        'name' => 'Marketing Campaign',
    ]);

    Sanctum::actingAs($this->owner);

    $response = $this->getJson('/api/projects?name=Marketing');
    $names = collect($response->json('data'))->pluck('name');

    expect($names)->toContain('Marketing Campaign');
});

test('projects are paginated', function (): void {
    Project::factory()->count(20)->create([
        'tenant_id' => $this->tenant->id,
        'workspace_id' => $this->workspace->id,
    ]);

    Sanctum::actingAs($this->owner);

    $response = $this->getJson('/api/projects?per_page=10');

    $response->assertStatus(200)
        ->assertJsonPath('meta.per_page', 10)
        ->assertJsonCount(10, 'data');
});

// ===== SHOW TESTS =====

test('owner can view project', function (): void {
    Sanctum::actingAs($this->owner);

    $response = $this->getJson("/api/projects/{$this->project->id}");

    $response->assertStatus(200)
        ->assertJsonPath('data.id', $this->project->id)
        ->assertJsonPath('data.name', 'Test Project');
});

test('cannot view project from other tenant', function (): void {
    $otherTenant = Tenant::factory()->create();
    $otherWorkspace = Workspace::factory()->create(['tenant_id' => $otherTenant->id]);
    $otherProject = Project::factory()->create([
        'tenant_id' => $otherTenant->id,
        'workspace_id' => $otherWorkspace->id,
    ]);

    Sanctum::actingAs($this->owner);

    $response = $this->getJson("/api/projects/{$otherProject->id}");

    $response->assertStatus(404);
});

// ===== STORE TESTS =====

test('owner can create project', function (): void {
    Sanctum::actingAs($this->owner);

    $response = $this->postJson('/api/projects', [
        'workspace_id' => $this->workspace->id,
        'name' => 'New Project',
        'description' => 'A new project description',
        'status' => 'active',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.name', 'New Project')
        ->assertJsonPath('data.status', 'active');

    $this->assertDatabaseHas('projects', [
        'name' => 'New Project',
        'tenant_id' => $this->tenant->id,
        'workspace_id' => $this->workspace->id,
    ]);
});

test('project creation creates default columns', function (): void {
    Sanctum::actingAs($this->owner);

    $response = $this->postJson('/api/projects', [
        'workspace_id' => $this->workspace->id,
        'name' => 'Project With Columns',
    ]);

    $response->assertStatus(201);

    $projectId = $response->json('data.id');

    $this->assertDatabaseCount('project_columns', 10); // 5 from beforeEach project + 5 new
    $this->assertDatabaseHas('project_columns', [
        'project_id' => $projectId,
        'name' => 'Backlog',
    ]);
    $this->assertDatabaseHas('project_columns', [
        'project_id' => $projectId,
        'name' => 'Done',
    ]);
});

test('admin can create project', function (): void {
    Sanctum::actingAs($this->admin);

    $response = $this->postJson('/api/projects', [
        'workspace_id' => $this->workspace->id,
        'name' => 'Admin Project',
    ]);

    $response->assertStatus(201);
});

test('member can create project', function (): void {
    Sanctum::actingAs($this->member);

    $response = $this->postJson('/api/projects', [
        'workspace_id' => $this->workspace->id,
        'name' => 'Member Project',
    ]);

    $response->assertStatus(201);
});

test('viewer cannot create project', function (): void {
    Sanctum::actingAs($this->viewer);

    $response = $this->postJson('/api/projects', [
        'workspace_id' => $this->workspace->id,
        'name' => 'Viewer Project',
    ]);

    $response->assertStatus(403);
});

test('project name is required', function (): void {
    Sanctum::actingAs($this->owner);

    $response = $this->postJson('/api/projects', [
        'workspace_id' => $this->workspace->id,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

test('workspace_id must belong to user tenant', function (): void {
    $otherTenant = Tenant::factory()->create();
    $otherWorkspace = Workspace::factory()->create(['tenant_id' => $otherTenant->id]);

    Sanctum::actingAs($this->owner);

    $response = $this->postJson('/api/projects', [
        'workspace_id' => $otherWorkspace->id,
        'name' => 'Cross Tenant Project',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['workspace_id']);
});

// ===== UPDATE TESTS =====

test('owner can update project', function (): void {
    Sanctum::actingAs($this->owner);

    $response = $this->putJson("/api/projects/{$this->project->id}", [
        'name' => 'Updated Project Name',
        'description' => 'Updated description',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.name', 'Updated Project Name');
});

test('admin can update project', function (): void {
    Sanctum::actingAs($this->admin);

    $response = $this->putJson("/api/projects/{$this->project->id}", [
        'name' => 'Admin Updated',
    ]);

    $response->assertStatus(200);
});

test('member cannot update project', function (): void {
    Sanctum::actingAs($this->member);

    $response = $this->putJson("/api/projects/{$this->project->id}", [
        'name' => 'Member Update',
    ]);

    $response->assertStatus(403);
});

test('can update project status', function (): void {
    Sanctum::actingAs($this->owner);

    $response = $this->putJson("/api/projects/{$this->project->id}", [
        'status' => 'on_hold',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.status', 'on_hold');
});

test('cannot update project from other tenant', function (): void {
    $otherTenant = Tenant::factory()->create();
    $otherWorkspace = Workspace::factory()->create(['tenant_id' => $otherTenant->id]);
    $otherProject = Project::factory()->create([
        'tenant_id' => $otherTenant->id,
        'workspace_id' => $otherWorkspace->id,
    ]);

    Sanctum::actingAs($this->owner);

    $response = $this->putJson("/api/projects/{$otherProject->id}", [
        'name' => 'Hacked',
    ]);

    $response->assertStatus(404);
});

// ===== DELETE TESTS =====

test('owner can delete project', function (): void {
    Sanctum::actingAs($this->owner);

    $response = $this->deleteJson("/api/projects/{$this->project->id}");

    $response->assertStatus(204);

    $this->assertSoftDeleted('projects', [
        'id' => $this->project->id,
    ]);
});

test('admin can delete project', function (): void {
    $projectToDelete = Project::factory()->create([
        'tenant_id' => $this->tenant->id,
        'workspace_id' => $this->workspace->id,
    ]);

    Sanctum::actingAs($this->admin);

    $response = $this->deleteJson("/api/projects/{$projectToDelete->id}");

    $response->assertStatus(204);
});

test('member cannot delete project', function (): void {
    Sanctum::actingAs($this->member);

    $response = $this->deleteJson("/api/projects/{$this->project->id}");

    $response->assertStatus(403);
});

test('cannot delete project from other tenant', function (): void {
    $otherTenant = Tenant::factory()->create();
    $otherWorkspace = Workspace::factory()->create(['tenant_id' => $otherTenant->id]);
    $otherProject = Project::factory()->create([
        'tenant_id' => $otherTenant->id,
        'workspace_id' => $otherWorkspace->id,
    ]);

    Sanctum::actingAs($this->owner);

    $response = $this->deleteJson("/api/projects/{$otherProject->id}");

    $response->assertStatus(404);
});

// ===== ACTIVITY LOG TESTS =====

test('project creation is logged', function (): void {
    Sanctum::actingAs($this->owner);

    $this->postJson('/api/projects', [
        'workspace_id' => $this->workspace->id,
        'name' => 'Logged Project',
    ]);

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'default',
        'description' => 'created',
        'subject_type' => Project::class,
    ]);
});

test('project update is logged', function (): void {
    Sanctum::actingAs($this->owner);

    $this->putJson("/api/projects/{$this->project->id}", [
        'name' => 'Updated Name',
    ]);

    $this->assertDatabaseHas('activity_log', [
        'subject_id' => $this->project->id,
        'subject_type' => Project::class,
        'description' => 'updated',
    ]);
});
