<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\ProjectColumn;
use App\Models\Tag;
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
});

// ===== TAG CRUD =====

test('owner can list tags', function (): void {
    Tag::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

    Sanctum::actingAs($this->owner);

    $response = $this->getJson('/api/tags');

    $response->assertStatus(200)
        ->assertJsonCount(3, 'data');
});

test('can search tags by name', function (): void {
    Tag::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Bug']);
    Tag::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Feature']);

    Sanctum::actingAs($this->owner);

    $response = $this->getJson('/api/tags?search=Bug');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Bug');
});

test('owner can create tag', function (): void {
    Sanctum::actingAs($this->owner);

    $response = $this->postJson('/api/tags', [
        'name' => 'urgent',
        'color' => '#ff0000',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.name', 'urgent')
        ->assertJsonPath('data.color', '#ff0000');

    $this->assertDatabaseHas('tags', [
        'tenant_id' => $this->tenant->id,
        'name' => 'urgent',
    ]);
});

test('member can create tag', function (): void {
    Sanctum::actingAs($this->member);

    $response = $this->postJson('/api/tags', [
        'name' => 'improvement',
    ]);

    $response->assertStatus(201);
});

test('viewer cannot create tag', function (): void {
    Sanctum::actingAs($this->viewer);

    $response = $this->postJson('/api/tags', [
        'name' => 'blocked',
    ]);

    $response->assertStatus(403);
});

test('tag name must be unique per tenant', function (): void {
    Tag::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'duplicate']);

    Sanctum::actingAs($this->owner);

    $response = $this->postJson('/api/tags', [
        'name' => 'duplicate',
    ]);

    $response->assertStatus(422);
});

test('color must be valid hex', function (): void {
    Sanctum::actingAs($this->owner);

    $response = $this->postJson('/api/tags', [
        'name' => 'test',
        'color' => 'invalid',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['color']);
});

test('owner can update tag', function (): void {
    $tag = Tag::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'old']);

    Sanctum::actingAs($this->owner);

    $response = $this->putJson("/api/tags/{$tag->id}", [
        'name' => 'new',
        'color' => '#00ff00',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.name', 'new')
        ->assertJsonPath('data.color', '#00ff00');
});

test('viewer cannot update tag', function (): void {
    $tag = Tag::factory()->create(['tenant_id' => $this->tenant->id]);

    Sanctum::actingAs($this->viewer);

    $response = $this->putJson("/api/tags/{$tag->id}", ['name' => 'changed']);

    $response->assertStatus(403);
});

test('owner can delete tag', function (): void {
    $tag = Tag::factory()->create(['tenant_id' => $this->tenant->id]);

    Sanctum::actingAs($this->owner);

    $response = $this->deleteJson("/api/tags/{$tag->id}");

    $response->assertStatus(204);
    $this->assertDatabaseMissing('tags', ['id' => $tag->id]);
});

test('member cannot delete tag', function (): void {
    $tag = Tag::factory()->create(['tenant_id' => $this->tenant->id]);

    Sanctum::actingAs($this->member);

    $response = $this->deleteJson("/api/tags/{$tag->id}");

    $response->assertStatus(403);
});

// ===== TASK TAGS =====

test('can attach tags to a task', function (): void {
    $workspace = Workspace::factory()->create(['tenant_id' => $this->tenant->id]);
    $project = Project::factory()->create(['tenant_id' => $this->tenant->id, 'workspace_id' => $workspace->id]);
    $column = ProjectColumn::factory()->create(['project_id' => $project->id]);
    $task = Task::factory()->create([
        'tenant_id' => $this->tenant->id,
        'project_id' => $project->id,
        'project_column_id' => $column->id,
    ]);

    $tag1 = Tag::factory()->create(['tenant_id' => $this->tenant->id]);
    $tag2 = Tag::factory()->create(['tenant_id' => $this->tenant->id]);

    Sanctum::actingAs($this->owner);

    $response = $this->postJson("/api/tasks/{$task->id}/tags", [
        'tag_ids' => [$tag1->id, $tag2->id],
    ]);

    $response->assertStatus(200);
    expect($task->tags()->count())->toBe(2);
});

test('can detach tag from task', function (): void {
    $workspace = Workspace::factory()->create(['tenant_id' => $this->tenant->id]);
    $project = Project::factory()->create(['tenant_id' => $this->tenant->id, 'workspace_id' => $workspace->id]);
    $column = ProjectColumn::factory()->create(['project_id' => $project->id]);
    $task = Task::factory()->create([
        'tenant_id' => $this->tenant->id,
        'project_id' => $project->id,
        'project_column_id' => $column->id,
    ]);

    $tag = Tag::factory()->create(['tenant_id' => $this->tenant->id]);
    $task->tags()->attach($tag->id);

    Sanctum::actingAs($this->owner);

    $response = $this->deleteJson("/api/tasks/{$task->id}/tags/{$tag->id}");

    $response->assertStatus(200);
    expect($task->tags()->count())->toBe(0);
});

test('can filter tasks by tag', function (): void {
    $workspace = Workspace::factory()->create(['tenant_id' => $this->tenant->id]);
    $project = Project::factory()->create(['tenant_id' => $this->tenant->id, 'workspace_id' => $workspace->id]);
    $column = ProjectColumn::factory()->create(['project_id' => $project->id]);

    $tag = Tag::factory()->create(['tenant_id' => $this->tenant->id]);

    $taggedTask = Task::factory()->create([
        'tenant_id' => $this->tenant->id,
        'project_id' => $project->id,
        'project_column_id' => $column->id,
        'title' => 'Tagged',
    ]);
    $taggedTask->tags()->attach($tag->id);

    Task::factory()->create([
        'tenant_id' => $this->tenant->id,
        'project_id' => $project->id,
        'project_column_id' => $column->id,
        'title' => 'Untagged',
    ]);

    Sanctum::actingAs($this->owner);

    $response = $this->getJson("/api/tasks?tag_id={$tag->id}");

    $response->assertStatus(200);
    $titles = collect($response->json('data'))->pluck('title');
    expect($titles)->toContain('Tagged')
        ->and($titles)->not->toContain('Untagged');
});
