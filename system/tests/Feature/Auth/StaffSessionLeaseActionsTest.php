<?php

use App\Auth\Actions\EstablishStaffSessionLease;
use App\Auth\Actions\RenewStaffSessionLease;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use RuntimeException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->now = CarbonImmutable::parse('2026-10-15 12:00:00 UTC');
    $this->sessionId = 'opaque-session-id';
});

test('establish creates a hashed session lease with an eight hour ceiling', function (): void {
    $lease = app(EstablishStaffSessionLease::class)->handle($this->user, $this->sessionId, $this->now);

    expect($lease->session_id_hash)->toBe(hash_hmac('sha256', $this->sessionId, (string) config('app.key')))
        ->and($lease->authenticated_at->equalTo($this->now))->toBeTrue()
        ->and($lease->idle_expires_at->equalTo($this->now->addMinutes(15)))->toBeTrue()
        ->and($lease->absolute_expires_at->equalTo($this->now->addHours(8)))->toBeTrue()
        ->and($lease->effective_idle_minutes)->toBe(15);
});

test('renew applies each allowed duration', function (int $minutes): void {
    $lease = app(EstablishStaffSessionLease::class)->handle($this->user, $this->sessionId, $this->now);
    $result = app(RenewStaffSessionLease::class)->handle($this->user, $this->sessionId, $this->now, $minutes);

    expect($result['idle_expires_at']->equalTo($this->now->addMinutes($minutes)))->toBeTrue()
        ->and($result['effective_idle_minutes'])->toBe($minutes)
        ->and($result['absolute_expires_at']->equalTo($lease->absolute_expires_at))->toBeTrue();
})->with([15, 30, 60, 480]);

test('renew preserves a longer existing idle deadline', function (): void {
    $lease = app(EstablishStaffSessionLease::class)->handle($this->user, $this->sessionId, $this->now, 60);
    $renewalTime = $this->now->addMinutes(5);

    $result = app(RenewStaffSessionLease::class)->handle($this->user, $this->sessionId, $renewalTime, 15);

    expect($result['idle_expires_at']->equalTo($lease->idle_expires_at))->toBeTrue()
        ->and($result['absolute_expires_at']->equalTo($lease->absolute_expires_at))->toBeTrue();
});

test('renew clamps at absolute expiry', function (): void {
    $lease = app(EstablishStaffSessionLease::class)->handle($this->user, $this->sessionId, $this->now);
    $lease->forceFill(['idle_expires_at' => $this->now->addHours(7)->addMinutes(56)])->save();
    $renewalTime = $this->now->addHours(7)->addMinutes(55);

    $result = app(RenewStaffSessionLease::class)->handle($this->user, $this->sessionId, $renewalTime, 15);

    expect($result['idle_expires_at']->equalTo($lease->absolute_expires_at))->toBeTrue();
});

test('renew rejects expired and revoked leases', function (string $state): void {
    $lease = app(EstablishStaffSessionLease::class)->handle($this->user, $this->sessionId, $this->now);

    if ($state === 'expired') {
        $lease->forceFill(['idle_expires_at' => $this->now->subSeconds(121)])->save();
    } else {
        $lease->forceFill(['revoked_at' => $this->now])->save();
    }

    expect(fn () => app(RenewStaffSessionLease::class)->handle($this->user, $this->sessionId, $this->now))
        ->toThrow(RuntimeException::class);
})->with(['expired', 'revoked']);

test('renew rejects unsupported durations', function (): void {
    app(EstablishStaffSessionLease::class)->handle($this->user, $this->sessionId, $this->now);

    expect(fn () => app(RenewStaffSessionLease::class)->handle($this->user, $this->sessionId, $this->now, 20))
        ->toThrow(ValidationException::class);
});
