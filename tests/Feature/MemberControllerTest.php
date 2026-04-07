<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
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
});

// ===== INDEX TESTS =====

test('owner can list all members', function (): void {
    Sanctum::actingAs($this->owner);

    $response = $this->getJson('/api/members');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'email', 'is_active', 'role', 'roles'],
            ],
            'links',
            'meta',
        ]);

    expect($response->json('data'))->toHaveCount(3);
});

test('admin can list all members', function (): void {
    Sanctum::actingAs($this->admin);

    $response = $this->getJson('/api/members');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(3);
});

test('member can list all members', function (): void {
    Sanctum::actingAs($this->member);

    $response = $this->getJson('/api/members');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(3);
});

test('can filter members by role', function (): void {
    Sanctum::actingAs($this->owner);

    $response = $this->getJson('/api/members?role=admin');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.role'))->toBe('admin');
});

test('can filter members by status', function (): void {
    // Create inactive member
    $inactiveMember = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'is_active' => false,
    ]);
    $inactiveMember->assignRole('member');

    Sanctum::actingAs($this->owner);

    $response = $this->getJson('/api/members?status=active');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(3);

    $response = $this->getJson('/api/members?status=inactive');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
});

test('can search members by name', function (): void {
    Sanctum::actingAs($this->owner);

    $searchName = substr($this->member->name, 0, 5);

    $response = $this->getJson("/api/members?search={$searchName}");

    $response->assertStatus(200);
    $results = collect($response->json('data'));
    expect($results->contains('id', $this->member->id))->toBeTrue();
});

test('can search members by email', function (): void {
    Sanctum::actingAs($this->owner);

    $searchEmail = explode('@', $this->member->email)[0];

    $response = $this->getJson("/api/members?search={$searchEmail}");

    $response->assertStatus(200);
    $results = collect($response->json('data'));
    expect($results->contains('id', $this->member->id))->toBeTrue();
});

test('members from different tenant are not visible', function (): void {
    $otherTenant = Tenant::factory()->create();
    $otherUser = User::factory()->create([
        'tenant_id' => $otherTenant->id,
        'is_active' => true,
    ]);
    $otherUser->assignRole('member');

    Sanctum::actingAs($this->owner);

    $response = $this->getJson('/api/members');

    $response->assertStatus(200);
    $memberIds = collect($response->json('data'))->pluck('id');
    expect($memberIds)->not->toContain($otherUser->id);
});

// ===== SHOW TESTS =====

test('can show specific member', function (): void {
    Sanctum::actingAs($this->owner);

    $response = $this->getJson("/api/members/{$this->member->id}");

    $response->assertStatus(200)
        ->assertJsonPath('data.id', $this->member->id)
        ->assertJsonPath('data.email', $this->member->email);
});

test('returns 404 for non-existent member', function (): void {
    Sanctum::actingAs($this->owner);

    $response = $this->getJson('/api/members/99999');

    $response->assertStatus(404)
        ->assertJson(['success' => false]);
});

test('cannot show member from different tenant', function (): void {
    $otherTenant = Tenant::factory()->create();
    $otherUser = User::factory()->create([
        'tenant_id' => $otherTenant->id,
    ]);

    Sanctum::actingAs($this->owner);

    $response = $this->getJson("/api/members/{$otherUser->id}");

    $response->assertStatus(404);
});

// ===== UPDATE TESTS =====

test('owner can update member role', function (): void {
    Sanctum::actingAs($this->owner);

    $response = $this->patchJson("/api/members/{$this->member->id}", [
        'role' => 'admin',
    ]);

    $response->assertStatus(200)
        ->assertJson(['success' => true])
        ->assertJsonPath('data.role', 'admin');

    expect($this->member->fresh()->hasRole('admin'))->toBeTrue();
});

test('owner can promote member to admin', function (): void {
    Sanctum::actingAs($this->owner);

    $response = $this->patchJson("/api/members/{$this->member->id}", [
        'role' => 'admin',
    ]);

    $response->assertStatus(200);
    expect($this->member->fresh()->hasRole('admin'))->toBeTrue();
});

test('admin cannot promote member to admin', function (): void {
    Sanctum::actingAs($this->admin);

    $response = $this->patchJson("/api/members/{$this->member->id}", [
        'role' => 'admin',
    ]);

    $response->assertStatus(403)
        ->assertJson([
            'success' => false,
            'message' => 'Only owner can promote members to admin',
        ]);
});

test('admin can demote member', function (): void {
    // First promote the member to admin
    $this->member->syncRoles(['admin']);

    Sanctum::actingAs($this->owner);

    $response = $this->patchJson("/api/members/{$this->member->id}", [
        'role' => 'member',
    ]);

    $response->assertStatus(200);
    expect($this->member->fresh()->hasRole('member'))->toBeTrue();
});

test('cannot update your own role', function (): void {
    Sanctum::actingAs($this->owner);

    $response = $this->patchJson("/api/members/{$this->owner->id}", [
        'role' => 'member',
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'message' => 'Cannot update your own role through this endpoint',
        ]);
});

test('cannot update owner role', function (): void {
    Sanctum::actingAs($this->admin);

    $response = $this->patchJson("/api/members/{$this->owner->id}", [
        'role' => 'admin',
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'message' => 'Cannot update the owner',
        ]);
});

test('cannot assign owner role through update', function (): void {
    Sanctum::actingAs($this->owner);

    $response = $this->patchJson("/api/members/{$this->member->id}", [
        'role' => 'owner',
    ]);

    // Validation prevents owner role from being assigned
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['role']);
});

test('can deactivate member', function (): void {
    Sanctum::actingAs($this->owner);

    $response = $this->patchJson("/api/members/{$this->member->id}", [
        'is_active' => false,
    ]);

    $response->assertStatus(200);
    expect($this->member->fresh()->is_active)->toBeFalse();
});

test('can reactivate member', function (): void {
    $this->member->update(['is_active' => false]);

    Sanctum::actingAs($this->owner);

    $response = $this->patchJson("/api/members/{$this->member->id}", [
        'is_active' => true,
    ]);

    $response->assertStatus(200);
    expect($this->member->fresh()->is_active)->toBeTrue();
});

test('member update creates activity log', function (): void {
    Sanctum::actingAs($this->owner);

    $this->patchJson("/api/members/{$this->member->id}", [
        'role' => 'admin',
    ]);

    $activity = Activity::where('subject_id', $this->member->id)
        ->where('log_name', 'member_updated')
        ->latest()
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($this->owner->id);
});

// ===== DESTROY TESTS =====

test('owner can remove member', function (): void {
    Sanctum::actingAs($this->owner);

    $response = $this->deleteJson("/api/members/{$this->member->id}");

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Member removed successfully',
        ]);

    expect($this->member->fresh()->is_active)->toBeFalse();
});

test('owner can remove admin', function (): void {
    Sanctum::actingAs($this->owner);

    $response = $this->deleteJson("/api/members/{$this->admin->id}");

    $response->assertStatus(200);
    expect($this->admin->fresh()->is_active)->toBeFalse();
});

test('admin cannot remove other admin', function (): void {
    // Create another admin
    $anotherAdmin = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'is_active' => true,
    ]);
    $anotherAdmin->assignRole('admin');

    Sanctum::actingAs($this->admin);

    $response = $this->deleteJson("/api/members/{$anotherAdmin->id}");

    $response->assertStatus(403)
        ->assertJson([
            'success' => false,
            'message' => 'Only owner can remove admins',
        ]);
});

test('admin can remove member', function (): void {
    Sanctum::actingAs($this->admin);

    $response = $this->deleteJson("/api/members/{$this->member->id}");

    $response->assertStatus(200);
    expect($this->member->fresh()->is_active)->toBeFalse();
});

test('cannot remove yourself', function (): void {
    Sanctum::actingAs($this->owner);

    $response = $this->deleteJson("/api/members/{$this->owner->id}");

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'message' => 'Cannot remove yourself',
        ]);
});

test('cannot remove owner', function (): void {
    Sanctum::actingAs($this->admin);

    $response = $this->deleteJson("/api/members/{$this->owner->id}");

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'message' => 'Cannot remove the owner. Transfer ownership first.',
        ]);
});

test('member removal creates activity log', function (): void {
    Sanctum::actingAs($this->owner);

    $memberId = $this->member->id;

    $this->deleteJson("/api/members/{$memberId}");

    $activity = Activity::where('subject_id', $memberId)
        ->where('log_name', 'member_removed')
        ->latest()
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($this->owner->id)
        ->and($activity->properties['member_name'])->toBe($this->member->name);
});

test('cannot remove member from different tenant', function (): void {
    $otherTenant = Tenant::factory()->create();
    $otherUser = User::factory()->create([
        'tenant_id' => $otherTenant->id,
    ]);
    $otherUser->assignRole('member');

    Sanctum::actingAs($this->owner);

    $response = $this->deleteJson("/api/members/{$otherUser->id}");

    $response->assertStatus(404);
});

// ===== VALIDATION TESTS =====

test('update requires valid role', function (): void {
    Sanctum::actingAs($this->owner);

    $response = $this->patchJson("/api/members/{$this->member->id}", [
        'role' => 'invalid_role',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['role']);
});

test('update is_active must be boolean', function (): void {
    Sanctum::actingAs($this->owner);

    $response = $this->patchJson("/api/members/{$this->member->id}", [
        'is_active' => 'not_a_boolean',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['is_active']);
});
