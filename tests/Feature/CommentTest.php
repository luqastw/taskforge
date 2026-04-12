<?php

declare(strict_types=1);

use App\Models\Comment;
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

    $workspace = Workspace::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->project = Project::factory()->create([
        'tenant_id' => $this->tenant->id,
        'workspace_id' => $workspace->id,
    ]);
    $this->column = ProjectColumn::factory()->create(['project_id' => $this->project->id]);
    $this->task = Task::factory()->create([
        'tenant_id' => $this->tenant->id,
        'project_id' => $this->project->id,
        'project_column_id' => $this->column->id,
    ]);
});

// ===== INDEX =====

test('can list comments on a task', function (): void {
    Comment::factory()->count(3)->create([
        'tenant_id' => $this->tenant->id,
        'task_id' => $this->task->id,
        'user_id' => $this->owner->id,
    ]);

    Sanctum::actingAs($this->owner);

    $response = $this->getJson("/api/tasks/{$this->task->id}/comments");

    $response->assertStatus(200)
        ->assertJsonCount(3, 'data');
});

test('comments are ordered by newest first', function (): void {
    $old = Comment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'task_id' => $this->task->id,
        'user_id' => $this->owner->id,
        'content' => 'Old comment',
        'created_at' => now()->subHour(),
    ]);

    $new = Comment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'task_id' => $this->task->id,
        'user_id' => $this->owner->id,
        'content' => 'New comment',
        'created_at' => now(),
    ]);

    Sanctum::actingAs($this->owner);

    $response = $this->getJson("/api/tasks/{$this->task->id}/comments");

    $response->assertStatus(200);
    $contents = collect($response->json('data'))->pluck('content')->toArray();
    expect($contents[0])->toBe('New comment');
});

// ===== STORE =====

test('member can create comment', function (): void {
    Sanctum::actingAs($this->member);

    $response = $this->postJson("/api/tasks/{$this->task->id}/comments", [
        'content' => 'This is a **markdown** comment',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.content', 'This is a **markdown** comment');

    $this->assertDatabaseHas('comments', [
        'task_id' => $this->task->id,
        'user_id' => $this->member->id,
        'content' => 'This is a **markdown** comment',
    ]);
});

test('viewer cannot create comment', function (): void {
    Sanctum::actingAs($this->viewer);

    $response = $this->postJson("/api/tasks/{$this->task->id}/comments", [
        'content' => 'Should fail',
    ]);

    $response->assertStatus(403);
});

test('comment content is required', function (): void {
    Sanctum::actingAs($this->owner);

    $response = $this->postJson("/api/tasks/{$this->task->id}/comments", []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['content']);
});

test('comment content max length is 10000', function (): void {
    Sanctum::actingAs($this->owner);

    $response = $this->postJson("/api/tasks/{$this->task->id}/comments", [
        'content' => str_repeat('a', 10001),
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['content']);
});

// ===== UPDATE =====

test('author can update own comment', function (): void {
    $comment = Comment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'task_id' => $this->task->id,
        'user_id' => $this->member->id,
        'content' => 'Original',
    ]);

    Sanctum::actingAs($this->member);

    $response = $this->putJson("/api/tasks/{$this->task->id}/comments/{$comment->id}", [
        'content' => 'Updated',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.content', 'Updated');
});

test('user cannot update someone elses comment', function (): void {
    $comment = Comment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'task_id' => $this->task->id,
        'user_id' => $this->owner->id,
        'content' => 'Owner comment',
    ]);

    Sanctum::actingAs($this->member);

    $response = $this->putJson("/api/tasks/{$this->task->id}/comments/{$comment->id}", [
        'content' => 'Hacked',
    ]);

    $response->assertStatus(403);
});

// ===== DELETE =====

test('author can delete own comment', function (): void {
    $comment = Comment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'task_id' => $this->task->id,
        'user_id' => $this->member->id,
    ]);

    Sanctum::actingAs($this->member);

    $response = $this->deleteJson("/api/tasks/{$this->task->id}/comments/{$comment->id}");

    $response->assertStatus(204);
    $this->assertSoftDeleted('comments', ['id' => $comment->id]);
});

test('owner can delete any comment', function (): void {
    $comment = Comment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'task_id' => $this->task->id,
        'user_id' => $this->member->id,
    ]);

    Sanctum::actingAs($this->owner);

    $response = $this->deleteJson("/api/tasks/{$this->task->id}/comments/{$comment->id}");

    $response->assertStatus(204);
});

test('viewer cannot delete comment', function (): void {
    $comment = Comment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'task_id' => $this->task->id,
        'user_id' => $this->viewer->id,
    ]);

    Sanctum::actingAs($this->viewer);

    $response = $this->deleteJson("/api/tasks/{$this->task->id}/comments/{$comment->id}");

    $response->assertStatus(403);
});

// ===== MENTIONS =====

test('comment extracts mentioned usernames', function (): void {
    Sanctum::actingAs($this->owner);

    $response = $this->postJson("/api/tasks/{$this->task->id}/comments", [
        'content' => 'Hey @john and @jane, please review this.',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.mentioned_usernames', ['john', 'jane']);
});

test('comment with no mentions returns empty array', function (): void {
    Sanctum::actingAs($this->owner);

    $response = $this->postJson("/api/tasks/{$this->task->id}/comments", [
        'content' => 'No mentions here.',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.mentioned_usernames', []);
});
