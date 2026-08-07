<?php

use App\Authorization\CapabilityAuthorizer;
use App\Models\User;
use Spatie\Permission\Models\Permission;

test('capability authorizer default-denies without a grant', function (): void {
    $user = User::factory()->create();

    $decision = app(CapabilityAuthorizer::class)->decide($user, 'foundation.probe');

    expect($decision->allowed)->toBeFalse()
        ->and($decision->reason)->toBe('capability_missing');
});

test('capability authorizer allows only when Spatie grant exists', function (): void {
    Permission::findOrCreate('foundation.probe');
    $user = User::factory()->create();
    $user->givePermissionTo('foundation.probe');

    $decision = app(CapabilityAuthorizer::class)->decide($user, 'foundation.probe');

    expect($decision->allowed)->toBeTrue()
        ->and($decision->reason)->toBe('allowed');
});

test('capability authorizer denies unauthenticated actors', function (): void {
    $decision = app(CapabilityAuthorizer::class)->decide(null, 'foundation.probe');

    expect($decision->allowed)->toBeFalse()
        ->and($decision->reason)->toBe('unauthenticated');
});

test('capability middleware denies missing grants with audit evidence', function (): void {
    Permission::findOrCreate('foundation.probe');
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/foundation/capability-probe')
        ->assertForbidden();

    expect(\App\Models\Foundation\AuditEvent::query()
        ->where('event_type', 'authorization.denied')
        ->exists())->toBeTrue();
});

test('capability middleware allows granted capability', function (): void {
    Permission::findOrCreate('foundation.probe');
    $user = User::factory()->create();
    $user->givePermissionTo('foundation.probe');

    $this->actingAs($user)
        ->getJson('/foundation/capability-probe')
        ->assertOk()
        ->assertJsonPath('ok', true);
});
