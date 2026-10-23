<?php

declare(strict_types=1);

use App\Models\Auth\StaffSessionLease;
use App\Models\Foundation\AuditEvent;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

beforeEach(function (): void {
    $this->travelTo(now()->startOfSecond());
    seedE2Foundation();
    $this->staff = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    $this->staff->authenticationState()->update([
        'two_factor_secret' => null,
        'two_factor_confirmed_at' => null,
        'mfa_required' => false,
    ]);
});

function signInStaffForLeaseTests(): void
{
    test()->actingAs(test()->staff);
}

test('successful staff authentication creates a lease bound to the regenerated browser session', function (): void {
    $this->post(route('login.store'), [
        'email' => $this->staff->email,
        'password' => 'password',
    ])->assertRedirect('/staff');

    $lease = StaffSessionLease::query()->where('user_id', $this->staff->id)->sole();

    expect($lease->authenticated_at)->not->toBeNull()
        ->and($lease->idle_expires_at->equalTo($lease->authenticated_at->copy()->addMinutes(15)))->toBeTrue()
        ->and($lease->absolute_expires_at->equalTo($lease->authenticated_at->copy()->addHours(8)))->toBeTrue()
        ->and($lease->effective_idle_minutes)->toBe(15);
});

test('keepalive defaults to a fifteen minute renewal', function (): void {
    signInStaffForLeaseTests();
    $this->postJson(route('staff.session.keepalive'))
        ->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('effective_idle_minutes', 15);
});
test('notification polling and prefetched navigation do not renew the staff lease', function (): void {
    signInStaffForLeaseTests();
    $lease = StaffSessionLease::query()->where('user_id', $this->staff->id)->sole();
    $idleExpiresAt = $lease->idle_expires_at;

    $this->getJson('/notifications/unread-count')->assertOk();
    $this->withHeaders(['X-Inertia-Prefetch' => 'true'])->get('/staff')->assertOk();

    expect($lease->fresh()->idle_expires_at->equalTo($idleExpiresAt))->toBeTrue();
});

test('keepalive renews the current lease using each supported extension preset', function (int $minutes): void {
    signInStaffForLeaseTests();

    $lease = StaffSessionLease::query()->where('user_id', $this->staff->id)->sole();
    $this->postJson(route('staff.session.keepalive'), ['extend_minutes' => $minutes])
        ->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('session_active', true)
        ->assertJsonPath('effective_idle_minutes', $minutes);

    expect($lease->fresh()->idle_expires_at->timestamp)->toBe(now()->addMinutes($minutes)->timestamp)
        ->and($lease->fresh()->absolute_expires_at->timestamp)->toBe($lease->authenticated_at->copy()->addHours(8)->timestamp);
})->with([15, 30, 60]);

test('keepalive extension succeeds within the 120-second grace window', function (): void {
    signInStaffForLeaseTests();

    $lease = StaffSessionLease::query()->where('user_id', $this->staff->id)->sole();
    $idleExpiresAt = $lease->idle_expires_at;
    $this->travelTo($idleExpiresAt->copy()->addSeconds(60));

    $this->postJson(route('staff.session.keepalive'))
        ->assertOk()
        ->assertJsonPath('status', 'ok');

    expect($lease->fresh()->idle_expires_at->timestamp)->toBe(now()->addMinutes(15)->timestamp);
});

test('keepalive extension fails after the 120-second grace window', function (): void {
    signInStaffForLeaseTests();

    $lease = StaffSessionLease::query()->where('user_id', $this->staff->id)->sole();
    $this->travelTo($lease->idle_expires_at->copy()->addSeconds(121));

    $this->postJson(route('staff.session.keepalive'))
        ->assertUnauthorized()
        ->assertExactJson(['error' => 'session_expired']);
});

test('expired idle or absolute lease is rejected before protected staff data is returned', function (string $deadline): void {
    signInStaffForLeaseTests();
    $lease = StaffSessionLease::query()->where('user_id', $this->staff->id)->sole();
    $lease->forceFill([$deadline => now()->subSecond()])->save();

    $this->get('/staff')->assertRedirect('/login?expired=1');
})->with([
    'idle timeout' => 'idle_expires_at',
    'eight hour ceiling' => 'absolute_expires_at',
]);

test('an AJAX request with an expired lease receives a session-expired response', function (): void {
    signInStaffForLeaseTests();
    $lease = StaffSessionLease::query()->where('user_id', $this->staff->id)->sole();
    $lease->forceFill(['idle_expires_at' => now()->subSecond()])->save();

    $this->getJson('/staff')
        ->assertUnauthorized()
        ->assertExactJson(['error' => 'session_expired']);
});
test('remembered restoration rejects stale generation while retaining the original authentication anchor', function (): void {
    $login = $this->post(route('login.store'), [
        'email' => $this->staff->email,
        'password' => 'password',
        'remember' => true,
    ])->assertRedirect('/staff');
    $cookieName = Auth::guard('web')->getRecallerName();
    $recaller = $login->getCookie($cookieName, false);
    $originalLease = StaffSessionLease::query()->where('user_id', $this->staff->id)->sole();
    expect($recaller)->not->toBeNull();

    $this->travel(7)->hours();
    $this->flushSession();
    Auth::forgetGuards();

    $this->withUnencryptedCookie($cookieName, $recaller->getValue())
        ->withHeader('X-Staff-Generation', 'stale-generation')
        ->get('/staff', ['Accept' => 'application/json'])
        ->assertStatus(409)
        ->assertExactJson(['error' => 'staff_account_changed'])
        ->assertHeader('X-Inertia-Location', route('staff.home'));

    $restoredLease = StaffSessionLease::query()
        ->where('user_id', $this->staff->id)
        ->orderByDesc('id')
        ->firstOrFail();
    $restoredGeneration = session('auth.staff_inactivity_generation');

    expect(session('auth.surface'))->toBe('staff')
        ->and($restoredGeneration)->toBe($restoredLease->inactivity_generation)
        ->and($restoredLease->authenticated_at->equalTo($originalLease->authenticated_at))->toBeTrue()
        ->and($restoredLease->absolute_expires_at->equalTo($originalLease->absolute_expires_at))->toBeTrue();
});

test('logout revokes only the current staff session lease', function (): void {
    signInStaffForLeaseTests();
    $lease = StaffSessionLease::query()->where('user_id', $this->staff->id)->sole();

    $this->post(route('logout'))->assertRedirect(route('login'));

    expect($lease->fresh()->revoked_at)->not->toBeNull();
});
test('stale staff generations reject reads and writes without clearing the valid session', function (): void {
    $this->post(route('login.store'), [
        'email' => $this->staff->email,
        'password' => 'password',
    ])->assertRedirect(route('staff.home'));
    $generation = session('auth.staff_inactivity_generation');
    $lease = StaffSessionLease::query()->where('user_id', $this->staff->id)->sole();
    $idleExpiresAt = $lease->idle_expires_at;

    $staleRead = $this->withHeader('X-Staff-Generation', 'stale-generation')
        ->getJson('/staff');
    $staleRead->assertStatus(409)
        ->assertExactJson(['error' => 'staff_account_changed'])
        ->assertHeader('X-Inertia-Location', route('staff.home'));
    expect($staleRead->headers->get('Cache-Control'))->toContain('no-store');

    $this->withHeader('X-Staff-Generation', 'stale-generation')
        ->postJson(route('staff.session.keepalive'))
        ->assertStatus(409)
        ->assertExactJson(['error' => 'staff_account_changed']);

    expect(session('auth.staff_inactivity_generation'))->toBe($generation)
        ->and(session('auth.surface'))->toBe('staff')
        ->and(Auth::user()?->is($this->staff))->toBeTrue()
        ->and($lease->fresh()->idle_expires_at->equalTo($idleExpiresAt))->toBeTrue();
});

test('matching staff generation reaches staff pages and genuine capability denials remain audited', function (): void {
    $this->post(route('login.store'), [
        'email' => $this->staff->email,
        'password' => 'password',
    ])->assertRedirect(route('staff.home'));
    $generation = session('auth.staff_inactivity_generation');

    $this->withHeader('X-Staff-Generation', $generation)
        ->get('/staff')
        ->assertOk();

    $this->withHeader('X-Staff-Generation', $generation)
        ->get('/admin')
        ->assertForbidden();

    expect(AuditEvent::query()->where('event_type', 'authorization.denied')->exists())->toBeTrue();
});
