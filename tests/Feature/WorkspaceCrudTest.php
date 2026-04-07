<?php

declare(strict_types=1);

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

    $this->workspace = Workspace::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Test Workspace',
    ]);
});

// ===== INDEX TESTS =====

test('owner can list workspaces', function (): void {
    Sanctum::actingAs($this->owner);

    $response = $this->getJson('/api/workspaces');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'description', 'settings', 'created_at', 'updated_at'],
            ],
            'links',
            'meta',
        ]);
});

test('admin can list workspaces', function (): void {
    Sanctum::actingAs($this->admin);

    $response = $this->getJson('/api/workspaces');

    $response->assertStatus(200);
});

test('member can list workspaces', function (): void {
    Sanctum::actingAs($this->member);

    $response = $this->getJson('/api/workspaces');

    $response->assertStatus(200);
});

test('workspaces are scoped to tenant', function (): void {
    $otherTenant = Tenant::factory()->create();
    $otherWorkspace = Workspace::factory()->create([
        'tenant_id' => $otherTenant->id,
        'name' => 'Other Workspace',
    ]);

    Sanctum::actingAs($this->owner);

    $response = $this->getJson('/api/workspaces');

    $response->assertStatus(200);
    $workspaceIds = collect($response->json('data'))->pluck('id');

    expect($workspaceIds)->toContain($this->workspace->id)
        ->and($workspaceIds)->not->toContain($otherWorkspace->id);
});

test('can filter workspaces by name', function (): void {
    Workspace::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Marketing Workspace',
    ]);

    Sanctum::actingAs($this->owner);

    $response = $this->getJson('/api/workspaces?name=Marketing');

    $response->assertStatus(200);
    $names = collect($response->json('data'))->pluck('name');

    expect($names)->toContain('Marketing Workspace');
});

test('workspaces are paginated', function (): void {
    Workspace::factory()->count(20)->create([
        'tenant_id' => $this->tenant->id,
    ]);

    Sanctum::actingAs($this->owner);

    $response = $this->getJson('/api/workspaces?per_page=10');

    $response->assertStatus(200)
        ->assertJsonPath('meta.per_page', 10)
        ->assertJsonCount(10, 'data');
});

// ===== SHOW TESTS =====

test('owner can view workspace', function (): void {
    Sanctum::actingAs($this->owner);

    $response = $this->getJson("/api/workspaces/{$this->workspace->id}");

    $response->assertStatus(200)
        ->assertJsonPath('data.id', $this->workspace->id)
        ->assertJsonPath('data.name', 'Test Workspace');
});

test('member can view workspace', function (): void {
    Sanctum::actingAs($this->member);

    $response = $this->getJson("/api/workspaces/{$this->workspace->id}");

    $response->assertStatus(200);
});

test('cannot view workspace from other tenant', function (): void {
    $otherTenant = Tenant::factory()->create();
    $otherWorkspace = Workspace::factory()->create([
        'tenant_id' => $otherTenant->id,
    ]);

    Sanctum::actingAs($this->owner);

    $response = $this->getJson("/api/workspaces/{$otherWorkspace->id}");

    $response->assertStatus(404);
});

test('returns 404 for non-existent workspace', function (): void {
    Sanctum::actingAs($this->owner);

    $response = $this->getJson('/api/workspaces/99999');

    $response->assertStatus(404);
});

// ===== STORE TESTS =====

test('owner can create workspace', function (): void {
    Sanctum::actingAs($this->owner);

    $response = $this->postJson('/api/workspaces', [
        'name' => 'New Workspace',
        'description' => 'A new workspace description',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.name', 'New Workspace')
        ->assertJsonPath('data.description', 'A new workspace description');

    $this->assertDatabaseHas('workspaces', [
        'name' => 'New Workspace',
        'tenant_id' => $this->tenant->id,
    ]);
});

test('admin can create workspace', function (): void {
    Sanctum::actingAs($this->admin);

    $response = $this->postJson('/api/workspaces', [
        'name' => 'Admin Workspace',
    ]);

    $response->assertStatus(201);
});

test('member cannot create workspace', function (): void {
    Sanctum::actingAs($this->member);

    $response = $this->postJson('/api/workspaces', [
        'name' => 'Member Workspace',
    ]);

    $response->assertStatus(403);
});

test('workspace name is required', function (): void {
    Sanctum::actingAs($this->owner);

    $response = $this->postJson('/api/workspaces', [
        'description' => 'No name provided',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

test('workspace name must be unique within tenant', function (): void {
    Sanctum::actingAs($this->owner);

    $response = $this->postJson('/api/workspaces', [
        'name' => 'Test Workspace', // Already exists
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

test('workspace name can be duplicated across tenants', function (): void {
    $otherTenant = Tenant::factory()->create();
    Workspace::factory()->create([
        'tenant_id' => $otherTenant->id,
        'name' => 'Shared Name',
    ]);

    Sanctum::actingAs($this->owner);

    $response = $this->postJson('/api/workspaces', [
        'name' => 'Shared Name',
    ]);

    $response->assertStatus(201);
});

test('can create workspace with settings', function (): void {
    Sanctum::actingAs($this->owner);

    $settings = [
        'timezone' => 'America/New_York',
        'date_format' => 'm/d/Y',
    ];

    $response = $this->postJson('/api/workspaces', [
        'name' => 'Configured Workspace',
        'settings' => $settings,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.settings.timezone', 'America/New_York');
});

// ===== UPDATE TESTS =====

test('owner can update workspace', function (): void {
    Sanctum::actingAs($this->owner);

    $response = $this->putJson("/api/workspaces/{$this->workspace->id}", [
        'name' => 'Updated Workspace Name',
        'description' => 'Updated description',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.name', 'Updated Workspace Name')
        ->assertJsonPath('data.description', 'Updated description');
});

test('admin can update workspace', function (): void {
    Sanctum::actingAs($this->admin);

    $response = $this->putJson("/api/workspaces/{$this->workspace->id}", [
        'name' => 'Admin Updated',
    ]);

    $response->assertStatus(200);
});

test('member cannot update workspace', function (): void {
    Sanctum::actingAs($this->member);

    $response = $this->putJson("/api/workspaces/{$this->workspace->id}", [
        'name' => 'Member Update',
    ]);

    $response->assertStatus(403);
});

test('cannot update workspace from other tenant', function (): void {
    $otherTenant = Tenant::factory()->create();
    $otherWorkspace = Workspace::factory()->create([
        'tenant_id' => $otherTenant->id,
    ]);

    Sanctum::actingAs($this->owner);

    $response = $this->putJson("/api/workspaces/{$otherWorkspace->id}", [
        'name' => 'Hacked Name',
    ]);

    $response->assertStatus(404);
});

test('can update workspace settings', function (): void {
    Sanctum::actingAs($this->owner);

    $response = $this->putJson("/api/workspaces/{$this->workspace->id}", [
        'settings' => [
            'timezone' => 'Europe/London',
            'notifications_enabled' => true,
        ],
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.settings.timezone', 'Europe/London');
});

// ===== DELETE TESTS =====

test('owner can delete workspace', function (): void {
    Sanctum::actingAs($this->owner);

    $response = $this->deleteJson("/api/workspaces/{$this->workspace->id}");

    $response->assertStatus(204);

    // Soft deleted
    $this->assertSoftDeleted('workspaces', [
        'id' => $this->workspace->id,
    ]);
});

test('admin can delete workspace', function (): void {
    $workspaceToDelete = Workspace::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    Sanctum::actingAs($this->admin);

    $response = $this->deleteJson("/api/workspaces/{$workspaceToDelete->id}");

    $response->assertStatus(204);
});

test('member cannot delete workspace', function (): void {
    Sanctum::actingAs($this->member);

    $response = $this->deleteJson("/api/workspaces/{$this->workspace->id}");

    $response->assertStatus(403);
});

test('cannot delete workspace from other tenant', function (): void {
    $otherTenant = Tenant::factory()->create();
    $otherWorkspace = Workspace::factory()->create([
        'tenant_id' => $otherTenant->id,
    ]);

    Sanctum::actingAs($this->owner);

    $response = $this->deleteJson("/api/workspaces/{$otherWorkspace->id}");

    $response->assertStatus(404);
});

// ===== ACTIVITY LOG TESTS =====

test('workspace creation is logged', function (): void {
    Sanctum::actingAs($this->owner);

    $this->postJson('/api/workspaces', [
        'name' => 'Logged Workspace',
    ]);

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'default',
        'description' => 'created',
        'subject_type' => Workspace::class,
    ]);
});

test('workspace update is logged', function (): void {
    Sanctum::actingAs($this->owner);

    $this->putJson("/api/workspaces/{$this->workspace->id}", [
        'name' => 'Updated Name',
    ]);

    $this->assertDatabaseHas('activity_log', [
        'subject_id' => $this->workspace->id,
        'subject_type' => Workspace::class,
        'description' => 'updated',
    ]);
});
