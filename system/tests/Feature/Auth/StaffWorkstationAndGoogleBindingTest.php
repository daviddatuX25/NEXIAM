<?php

declare(strict_types=1);

use App\Models\Auth\StaffAuthenticationState;
use App\Models\Auth\StaffProviderIdentity;
use App\Models\Foundation\AuditEvent;
use App\Models\Foundation\Institution;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

beforeEach(function (): void {
    seedE2Foundation();
});

function workstationGoogleStaff(array $stateOverrides = []): User
{
    $user = User::factory()->create([
        'email' => 'google.workstation.'.fake()->unique()->numerify('########').'@gmail.com',
        'password' => Hash::make('staff-password-123'),
    ]);
    $institution = Institution::query()->firstOrFail();

    StaffProfile::query()->create([
        'user_id' => $user->id,
        'institution_id' => $institution->id,
        'office' => 'academic',
        'status' => StaffProfile::STATUS_ACTIVE,
    ]);
    StaffAuthenticationState::query()->create(array_merge([
        'user_id' => $user->id,
        'activation_completed_at' => now(),
        'password_set_at' => now(),
        'mfa_required' => false,
    ], $stateOverrides));

    return $user;
}

test('authenticated staff can ping session keepalive endpoint and touch session', function (): void {
    $staff = workstationGoogleStaff();

    $this->actingAs($staff)
        ->postJson(route('staff.session.keepalive'))
        ->assertOk()
        ->assertSessionHas('auth.last_keepalive')
        ->assertExactJson(['status' => 'ok', 'session_active' => true]);
});

test('guest is denied access to session keepalive endpoint', function (): void {
    $this->postJson(route('staff.session.keepalive'))
        ->assertUnauthorized();
});

test('guest login with unlinked Google account fails without auto-linking', function (): void {
    $staff = workstationGoogleStaff();
    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'google-unlinked-workstation',
        'email' => $staff->email,
        'user' => ['email_verified' => true],
    ]));

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors([
            'email' => 'No staff account is linked to this Google identity. Please sign in with your password and link Google in your profile.',
        ]);

    $this->assertGuest();
    expect(StaffProviderIdentity::query()->where('user_id', $staff->id)->exists())->toBeFalse();
});

test('authenticated staff can explicitly link Google account via OAuth callback', function (): void {
    $staff = workstationGoogleStaff();
    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'google-explicit-workstation',
        'email' => 'staff.google@gmail.com',
        'user' => ['email_verified' => true],
    ]));

    $this->actingAs($staff)
        ->get(route('auth.google.callback'))
        ->assertRedirect(route('staff.profile'))
        ->assertSessionHas('status', 'Google account linked successfully.');

    $identity = StaffProviderIdentity::query()->where('user_id', $staff->id)->sole();
    expect($identity->provider)->toBe('google')
        ->and($identity->subject)->toBe('google-explicit-workstation')
        ->and($identity->email_at_link)->toBe('staff.google@gmail.com')
        ->and($identity->email_verified_at_link)->toBeTrue();
    expect(AuditEvent::query()
        ->where('event_type', 'auth.staff_google_explicit_linked')
        ->where('actor', (string) $staff->id)
        ->exists())->toBeTrue();
});

test('authenticated staff can unlink Google account with valid password', function (): void {
    $staff = workstationGoogleStaff();
    $identity = StaffProviderIdentity::query()->create([
        'user_id' => $staff->id,
        'provider' => 'google',
        'subject' => 'google-to-unlink-workstation',
        'email_at_link' => 'staff.google@gmail.com',
        'email_verified_at_link' => true,
        'linked_at' => now(),
    ]);

    $this->actingAs($staff)
        ->post(route('staff.profile.google.unlink'), ['current_password' => 'staff-password-123'])
        ->assertRedirect(route('staff.profile'))
        ->assertSessionHas('status', 'Google account unlinked.');

    expect($identity->fresh()->revoked_at)->not->toBeNull();
    expect(AuditEvent::query()
        ->where('event_type', 'auth.staff_google_unlinked')
        ->where('actor', (string) $staff->id)
        ->exists())->toBeTrue();
});

test('unlinking Google account fails if user has no local password', function (): void {
    $staff = workstationGoogleStaff(['password_set_at' => null]);
    $identity = StaffProviderIdentity::query()->create([
        'user_id' => $staff->id,
        'provider' => 'google',
        'subject' => 'google-no-password-workstation',
        'email_at_link' => 'staff.google@gmail.com',
        'email_verified_at_link' => true,
        'linked_at' => now(),
    ]);

    $this->actingAs($staff)
        ->post(route('staff.profile.google.unlink'), ['current_password' => 'staff-password-123'])
        ->assertSessionHasErrors([
            'google' => 'You must set a local password before unlinking your Google account.',
        ]);

    expect($identity->fresh()->revoked_at)->toBeNull();
});
