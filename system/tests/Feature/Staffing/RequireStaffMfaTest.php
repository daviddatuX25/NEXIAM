<?php

declare(strict_types=1);

use App\Models\Auth\StaffAuthenticationState;
use App\Models\User;

test('HR can require MFA through the personnel endpoint', function (): void {
    seedE2Foundation();

    $hr = User::query()->where('email', config('seed.hr.email'))->firstOrFail();
    $target = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $state = StaffAuthenticationState::query()->where('user_id', $target->id)->firstOrFail();
    $state->forceFill(['mfa_required' => false])->save();

    $this->actingAs($hr)
        ->from(route('staff.personnel.index'))
        ->post("/staff/personnel/{$target->id}/require-mfa", ['reason' => 'Protect administrative access'])
        ->assertRedirect(route('staff.personnel.index'))
        ->assertSessionHas('status', 'MFA requirement has been enforced for '.$target->name.'.');

    expect($state->refresh()->mfa_required)->toBeTrue();
});

test('office head can require and turn off MFA for subordinates and for themselves via personnel and profile endpoints', function (): void {
    seedE2Foundation();

    $lead = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $subordinate = User::query()->where('email', 'guidance.clerk@nexiam.test')->firstOrFail();
    $subState = StaffAuthenticationState::query()->where('user_id', $subordinate->id)->firstOrFail();
    $leadState = StaffAuthenticationState::query()->where('user_id', $lead->id)->firstOrFail();

    $this->actingAs($lead)
        ->from(route('staff.personnel.index'))
        ->post("/staff/personnel/{$subordinate->id}/require-mfa", ['reason' => 'Enable for clerk'])
        ->assertRedirect(route('staff.personnel.index'));
    expect($subState->refresh()->mfa_required)->toBeTrue()
        ->and($subState->mfa_exempt)->toBeFalse();

    $this->actingAs($lead)
        ->from(route('staff.personnel.index'))
        ->post("/staff/personnel/{$subordinate->id}/disable-mfa", ['reason' => 'Turn off for clerk'])
        ->assertRedirect(route('staff.personnel.index'));
    expect($subState->refresh()->mfa_required)->toBeFalse()
        ->and($subState->mfa_exempt)->toBeTrue();

    $this->actingAs($lead)
        ->from(route('staff.personnel.index'))
        ->post("/staff/personnel/{$lead->id}/require-mfa", ['reason' => 'Enable for self'])
        ->assertRedirect(route('staff.personnel.index'));
    expect($leadState->refresh()->mfa_required)->toBeTrue()
        ->and($leadState->mfa_exempt)->toBeFalse();

    $this->actingAs($lead)
        ->from(route('staff.profile'))
        ->post('/staff/profile/mfa/disable', ['reason' => 'Turn off on profile'])
        ->assertRedirect(route('staff.profile'));
    expect($leadState->refresh()->mfa_required)->toBeFalse()
        ->and($leadState->mfa_exempt)->toBeTrue();
});

test('non-HR staff cannot require MFA through the personnel endpoint', function (): void {
    seedE2Foundation();

    $admin = User::query()->where('email', 'admin.lead@nexiam.test')->firstOrFail();
    $target = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $state = StaffAuthenticationState::query()->where('user_id', $target->id)->firstOrFail();
    $state->forceFill(['mfa_required' => false])->save();

    $this->actingAs($admin)
        ->post("/staff/personnel/{$target->id}/require-mfa")
        ->assertForbidden();

    expect($state->refresh()->mfa_required)->toBeFalse();
});
