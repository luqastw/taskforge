<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);

    $this->tenant = Tenant::factory()->create();
    $this->user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'is_active' => true,
    ]);
    $this->user->assignRole('member');
});

test('can get notification preferences with defaults', function (): void {
    Sanctum::actingAs($this->user);

    $response = $this->getJson('/api/notification-preferences');

    $response->assertStatus(200)
        ->assertJsonPath('data.email_task_assigned', true)
        ->assertJsonPath('data.email_mentioned', true)
        ->assertJsonPath('data.email_deadline', true)
        ->assertJsonPath('data.email_task_status', false);
});

test('can update notification preferences', function (): void {
    Sanctum::actingAs($this->user);

    $response = $this->putJson('/api/notification-preferences', [
        'email_task_assigned' => false,
        'email_mentioned' => false,
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.email_task_assigned', false)
        ->assertJsonPath('data.email_mentioned', false);

    $this->user->refresh();
    expect($this->user->notification_preferences['email_task_assigned'])->toBeFalse();
    expect($this->user->notification_preferences['email_mentioned'])->toBeFalse();
});

test('partial update preserves existing preferences', function (): void {
    $this->user->update(['notification_preferences' => [
        'email_task_assigned' => false,
        'email_mentioned' => true,
    ]]);

    Sanctum::actingAs($this->user);

    $response = $this->putJson('/api/notification-preferences', [
        'email_deadline' => false,
    ]);

    $response->assertStatus(200);

    $this->user->refresh();
    expect($this->user->notification_preferences['email_task_assigned'])->toBeFalse();
    expect($this->user->notification_preferences['email_mentioned'])->toBeTrue();
    expect($this->user->notification_preferences['email_deadline'])->toBeFalse();
});

test('rejects invalid preference values', function (): void {
    Sanctum::actingAs($this->user);

    $response = $this->putJson('/api/notification-preferences', [
        'email_task_assigned' => 'not-a-boolean',
    ]);

    $response->assertStatus(422);
});
