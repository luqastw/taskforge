<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;

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

    $this->workspace = Workspace::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Test Workspace',
    ]);

    $this->project = Project::factory()->create([
        'tenant_id' => $this->tenant->id,
        'workspace_id' => $this->workspace->id,
        'name' => 'Test Project',
    ]);

    $this->workspace->members()->attach([
        $this->owner->id => ['joined_at' => now()],
        $this->admin->id => ['joined_at' => now()],
        $this->member->id => ['joined_at' => now()],
    ]);
});

test('owner can list project members', function (): void {
    $this->project->members()->attach($this->member->id, ['joined_at' => now()]);

    Sanctum::actingAs($this->owner);

    $response = $this->getJson("/api/projects/{$this->project->id}/members");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'email', 'role'],
            ],
            'links',
            'meta',
        ]);
});

test('admin can list project members', function (): void {
    Sanctum::actingAs($this->admin);

    $response = $this->getJson("/api/projects/{$this->project->id}/members");

    $response->assertStatus(200);
});

test('member can list project members', function (): void {
    Sanctum::actingAs($this->member);

    $response = $this->getJson("/api/projects/{$this->project->id}/members");

    $response->assertStatus(200);
});

test('cannot list members of project from other tenant', function (): void {
    $otherTenant = Tenant::factory()->create();
    $otherWorkspace = Workspace::factory()->create([
        'tenant_id' => $otherTenant->id,
    ]);
    $otherProject = Project::factory()->create([
        'tenant_id' => $otherTenant->id,
        'workspace_id' => $otherWorkspace->id,
    ]);

    Sanctum::actingAs($this->owner);

    $response = $this->getJson("/api/projects/{$otherProject->id}/members");

    $response->assertStatus(404);
});

test('owner can add member to project', function (): void {
    Sanctum::actingAs($this->owner);

    $response = $this->postJson("/api/projects/{$this->project->id}/members", [
        'user_id' => $this->member->id,
    ]);

    $response->assertStatus(201)
        ->assertJson([
            'success' => true,
            'message' => 'Member added to project successfully',
        ]);

    expect($this->project->members()->where('user_id', $this->member->id)->exists())
        ->toBeTrue();
});

test('admin can add member to project', function (): void {
    Sanctum::actingAs($this->admin);

    $response = $this->postJson("/api/projects/{$this->project->id}/members", [
        'user_id' => $this->member->id,
    ]);

    $response->assertStatus(201);
});

test('member cannot add member to project', function (): void {
    $anotherMember = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'is_active' => true,
    ]);
    $anotherMember->assignRole('member');

    Sanctum::actingAs($this->member);

    $response = $this->postJson("/api/projects/{$this->project->id}/members", [
        'user_id' => $anotherMember->id,
    ]);

    $response->assertStatus(403);
});

test('cannot add user from different tenant', function (): void {
    $otherTenant = Tenant::factory()->create();
    $otherUser = User::factory()->create([
        'tenant_id' => $otherTenant->id,
    ]);

    Sanctum::actingAs($this->owner);

    $response = $this->postJson("/api/projects/{$this->project->id}/members", [
        'user_id' => $otherUser->id,
    ]);

    $response->assertStatus(404)
        ->assertJson([
            'success' => false,
            'message' => 'User not found in your tenant',
        ]);
});

test('cannot add user who is already a member', function (): void {
    $this->project->members()->attach($this->member->id, ['joined_at' => now()]);

    Sanctum::actingAs($this->owner);

    $response = $this->postJson("/api/projects/{$this->project->id}/members", [
        'user_id' => $this->member->id,
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'message' => 'User is already a member of this project',
        ]);
});

test('adding member requires user_id', function (): void {
    Sanctum::actingAs($this->owner);

    $response = $this->postJson("/api/projects/{$this->project->id}/members", []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['user_id']);
});

test('adding member creates activity log', function (): void {
    Sanctum::actingAs($this->owner);

    $this->postJson("/api/projects/{$this->project->id}/members", [
        'user_id' => $this->member->id,
    ]);

    $activity = Activity::where('subject_id', $this->project->id)
        ->where('log_name', 'project_member_added')
        ->latest()
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->properties['user_id'])->toBe($this->member->id);
});

test('owner can remove member from project', function (): void {
    $this->project->members()->attach($this->member->id, ['joined_at' => now()]);

    Sanctum::actingAs($this->owner);

    $response = $this->deleteJson("/api/projects/{$this->project->id}/members/{$this->member->id}");

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Member removed from project successfully',
        ]);

    expect($this->project->members()->where('user_id', $this->member->id)->exists())
        ->toBeFalse();
});

test('admin can remove member from project', function (): void {
    $this->project->members()->attach($this->member->id, ['joined_at' => now()]);

    Sanctum::actingAs($this->admin);

    $response = $this->deleteJson("/api/projects/{$this->project->id}/members/{$this->member->id}");

    $response->assertStatus(200);
});

test('member cannot remove member from project', function (): void {
    $anotherMember = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'is_active' => true,
    ]);
    $anotherMember->assignRole('member');
    $this->project->members()->attach($anotherMember->id, ['joined_at' => now()]);

    Sanctum::actingAs($this->member);

    $response = $this->deleteJson("/api/projects/{$this->project->id}/members/{$anotherMember->id}");

    $response->assertStatus(403);
});

test('cannot remove user who is not a member', function (): void {
    Sanctum::actingAs($this->owner);

    $response = $this->deleteJson("/api/projects/{$this->project->id}/members/{$this->member->id}");

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'message' => 'User is not a member of this project',
        ]);
});

test('removing member creates activity log', function (): void {
    $this->project->members()->attach($this->member->id, ['joined_at' => now()]);

    Sanctum::actingAs($this->owner);

    $this->deleteJson("/api/projects/{$this->project->id}/members/{$this->member->id}");

    $activity = Activity::where('subject_id', $this->project->id)
        ->where('log_name', 'project_member_removed')
        ->latest()
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->properties['user_id'])->toBe($this->member->id);
});

test('owner can add multiple members at once', function (): void {
    $member2 = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'is_active' => true,
    ]);
    $member2->assignRole('member');
    $this->workspace->members()->attach($member2->id, ['joined_at' => now()]);

    $member3 = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'is_active' => true,
    ]);
    $member3->assignRole('member');
    $this->workspace->members()->attach($member3->id, ['joined_at' => now()]);

    Sanctum::actingAs($this->owner);

    $response = $this->postJson("/api/projects/{$this->project->id}/members/bulk", [
        'user_ids' => [$this->member->id, $member2->id, $member3->id],
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.added', 3)
        ->assertJsonPath('data.already_members', 0);

    expect($this->project->members()->count())->toBe(3);
});

test('bulk add skips users already members', function (): void {
    $this->project->members()->attach($this->member->id, ['joined_at' => now()]);

    $member2 = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'is_active' => true,
    ]);
    $member2->assignRole('member');
    $this->workspace->members()->attach($member2->id, ['joined_at' => now()]);

    Sanctum::actingAs($this->owner);

    $response = $this->postJson("/api/projects/{$this->project->id}/members/bulk", [
        'user_ids' => [$this->member->id, $member2->id],
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.added', 1)
        ->assertJsonPath('data.already_members', 1);
});

test('bulk add requires user_ids array', function (): void {
    Sanctum::actingAs($this->owner);

    $response = $this->postJson("/api/projects/{$this->project->id}/members/bulk", []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['user_ids']);
});

test('bulk add ignores users from other tenant', function (): void {
    $otherTenant = Tenant::factory()->create();
    $otherUser = User::factory()->create([
        'tenant_id' => $otherTenant->id,
    ]);

    Sanctum::actingAs($this->owner);

    $response = $this->postJson("/api/projects/{$this->project->id}/members/bulk", [
        'user_ids' => [$this->member->id, $otherUser->id],
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.added', 1);

    expect($this->project->members()->where('user_id', $otherUser->id)->exists())
        ->toBeFalse();
});
