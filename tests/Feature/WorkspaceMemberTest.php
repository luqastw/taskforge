<?php

declare(strict_types=1);

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
});

// ===== INDEX TESTS =====

test('owner can list workspace members', function (): void {
    $this->workspace->members()->attach($this->member->id, ['joined_at' => now()]);

    Sanctum::actingAs($this->owner);

    $response = $this->getJson("/api/workspaces/{$this->workspace->id}/members");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'email', 'role'],
            ],
            'links',
            'meta',
        ]);
});

test('admin can list workspace members', function (): void {
    Sanctum::actingAs($this->admin);

    $response = $this->getJson("/api/workspaces/{$this->workspace->id}/members");

    $response->assertStatus(200);
});

test('member can list workspace members', function (): void {
    Sanctum::actingAs($this->member);

    $response = $this->getJson("/api/workspaces/{$this->workspace->id}/members");

    $response->assertStatus(200);
});

test('cannot list members of workspace from other tenant', function (): void {
    $otherTenant = Tenant::factory()->create();
    $otherWorkspace = Workspace::factory()->create([
        'tenant_id' => $otherTenant->id,
    ]);

    Sanctum::actingAs($this->owner);

    $response = $this->getJson("/api/workspaces/{$otherWorkspace->id}/members");

    $response->assertStatus(404);
});

// ===== STORE TESTS =====

test('owner can add member to workspace', function (): void {
    Sanctum::actingAs($this->owner);

    $response = $this->postJson("/api/workspaces/{$this->workspace->id}/members", [
        'user_id' => $this->member->id,
    ]);

    $response->assertStatus(201)
        ->assertJson([
            'success' => true,
            'message' => 'Member added to workspace successfully',
        ]);

    expect($this->workspace->members()->where('user_id', $this->member->id)->exists())
        ->toBeTrue();
});

test('admin can add member to workspace', function (): void {
    Sanctum::actingAs($this->admin);

    $response = $this->postJson("/api/workspaces/{$this->workspace->id}/members", [
        'user_id' => $this->member->id,
    ]);

    $response->assertStatus(201);
});

test('member cannot add member to workspace', function (): void {
    $anotherMember = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'is_active' => true,
    ]);
    $anotherMember->assignRole('member');

    Sanctum::actingAs($this->member);

    $response = $this->postJson("/api/workspaces/{$this->workspace->id}/members", [
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

    $response = $this->postJson("/api/workspaces/{$this->workspace->id}/members", [
        'user_id' => $otherUser->id,
    ]);

    $response->assertStatus(404)
        ->assertJson([
            'success' => false,
            'message' => 'User not found in your tenant',
        ]);
});

test('cannot add user who is already a member', function (): void {
    $this->workspace->members()->attach($this->member->id, ['joined_at' => now()]);

    Sanctum::actingAs($this->owner);

    $response = $this->postJson("/api/workspaces/{$this->workspace->id}/members", [
        'user_id' => $this->member->id,
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'message' => 'User is already a member of this workspace',
        ]);
});

test('adding member requires user_id', function (): void {
    Sanctum::actingAs($this->owner);

    $response = $this->postJson("/api/workspaces/{$this->workspace->id}/members", []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['user_id']);
});

test('adding member creates activity log', function (): void {
    Sanctum::actingAs($this->owner);

    $this->postJson("/api/workspaces/{$this->workspace->id}/members", [
        'user_id' => $this->member->id,
    ]);

    $activity = Activity::where('subject_id', $this->workspace->id)
        ->where('log_name', 'workspace_member_added')
        ->latest()
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->properties['user_id'])->toBe($this->member->id);
});

// ===== DESTROY TESTS =====

test('owner can remove member from workspace', function (): void {
    $this->workspace->members()->attach($this->member->id, ['joined_at' => now()]);

    Sanctum::actingAs($this->owner);

    $response = $this->deleteJson("/api/workspaces/{$this->workspace->id}/members/{$this->member->id}");

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Member removed from workspace successfully',
        ]);

    expect($this->workspace->members()->where('user_id', $this->member->id)->exists())
        ->toBeFalse();
});

test('admin can remove member from workspace', function (): void {
    $this->workspace->members()->attach($this->member->id, ['joined_at' => now()]);

    Sanctum::actingAs($this->admin);

    $response = $this->deleteJson("/api/workspaces/{$this->workspace->id}/members/{$this->member->id}");

    $response->assertStatus(200);
});

test('member cannot remove member from workspace', function (): void {
    $anotherMember = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'is_active' => true,
    ]);
    $anotherMember->assignRole('member');
    $this->workspace->members()->attach($anotherMember->id, ['joined_at' => now()]);

    Sanctum::actingAs($this->member);

    $response = $this->deleteJson("/api/workspaces/{$this->workspace->id}/members/{$anotherMember->id}");

    $response->assertStatus(403);
});

test('cannot remove user who is not a member', function (): void {
    Sanctum::actingAs($this->owner);

    $response = $this->deleteJson("/api/workspaces/{$this->workspace->id}/members/{$this->member->id}");

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'message' => 'User is not a member of this workspace',
        ]);
});

test('removing member creates activity log', function (): void {
    $this->workspace->members()->attach($this->member->id, ['joined_at' => now()]);

    Sanctum::actingAs($this->owner);

    $this->deleteJson("/api/workspaces/{$this->workspace->id}/members/{$this->member->id}");

    $activity = Activity::where('subject_id', $this->workspace->id)
        ->where('log_name', 'workspace_member_removed')
        ->latest()
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->properties['user_id'])->toBe($this->member->id);
});

// ===== BULK ADD TESTS =====

test('owner can add multiple members at once', function (): void {
    $member2 = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'is_active' => true,
    ]);
    $member2->assignRole('member');

    $member3 = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'is_active' => true,
    ]);
    $member3->assignRole('member');

    Sanctum::actingAs($this->owner);

    $response = $this->postJson("/api/workspaces/{$this->workspace->id}/members/bulk", [
        'user_ids' => [$this->member->id, $member2->id, $member3->id],
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.added', 3)
        ->assertJsonPath('data.already_members', 0);

    expect($this->workspace->members()->count())->toBe(3);
});

test('bulk add skips users already members', function (): void {
    $this->workspace->members()->attach($this->member->id, ['joined_at' => now()]);

    $member2 = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'is_active' => true,
    ]);
    $member2->assignRole('member');

    Sanctum::actingAs($this->owner);

    $response = $this->postJson("/api/workspaces/{$this->workspace->id}/members/bulk", [
        'user_ids' => [$this->member->id, $member2->id],
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.added', 1)
        ->assertJsonPath('data.already_members', 1);
});

test('bulk add requires user_ids array', function (): void {
    Sanctum::actingAs($this->owner);

    $response = $this->postJson("/api/workspaces/{$this->workspace->id}/members/bulk", []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['user_ids']);
});

test('bulk add ignores users from other tenant', function (): void {
    $otherTenant = Tenant::factory()->create();
    $otherUser = User::factory()->create([
        'tenant_id' => $otherTenant->id,
    ]);

    Sanctum::actingAs($this->owner);

    $response = $this->postJson("/api/workspaces/{$this->workspace->id}/members/bulk", [
        'user_ids' => [$this->member->id, $otherUser->id],
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.added', 1);

    expect($this->workspace->members()->where('user_id', $otherUser->id)->exists())
        ->toBeFalse();
});
