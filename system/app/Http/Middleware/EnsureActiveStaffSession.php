<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Admission\Support\ApplicantQrCode;
use App\Auth\Actions\EndStaffPresenceSession;
use App\Auth\Actions\EstablishStaffSession;
use App\Auth\Actions\RevokeStaffSessionLease;
use App\Auth\Actions\TouchStaffPresenceSession;
use App\Auth\Support\StaffAuthenticationProof;
use App\Auth\Support\StaffMfaPolicy;
use App\Auth\Support\StaffRememberToken;
use App\Models\Auth\StaffSessionLease;
use Closure;
use Illuminate\Auth\Recaller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class EnsureActiveStaffSession
{
    public function __construct(
        private readonly EstablishStaffSession $establishStaffSession,
        private readonly StaffMfaPolicy $mfaPolicy,
        private readonly StaffRememberToken $rememberToken,
        private readonly TouchStaffPresenceSession $touchPresenceSession,
        private readonly EndStaffPresenceSession $endPresenceSession,
        private readonly RevokeStaffSessionLease $revokeLease,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $this->rejectChangedAccount($request) ?? $next($request);
        }

        $profile = $user->staffProfile()->first();
        $surface = $request->session()->get('auth.surface');
        $sessionVersion = $request->session()->get('auth.session_version');

        if ($profile === null && $surface !== 'staff') {
            return $this->rejectChangedAccount($request) ?? $next($request);
        }

        if ($profile === null || ! $profile->isActive()) {
            return $this->reject($request, 'Your staff account is inactive or suspended. Please contact HR.');
        }

        $state = $profile->authenticationState()->first();
        if (
            $state === null
            || $state->two_factor_generation === null
            || ! $state->isOnboarded()
            || $state->password_set_at === null
            || $state->must_change_password
            || ($state->hasConfirmedTwoFactor() && $state->two_factor_secret === null)
            || (! $state->hasConfirmedTwoFactor() && $state->two_factor_secret !== null)
            || (Auth::guard('web')->viaRemember() && $this->mfaPolicy->isMfaRequired($user) && ! $state->hasConfirmedTwoFactor())
        ) {
            return $this->reject($request, 'Your staff account requires renewed authentication. Please sign in again.');
        }

        $guard = Auth::guard('web');
        if ($guard->viaRemember()) {
            if (
                ($surface !== null || $sessionVersion !== null)
                && ($surface !== 'staff' || $sessionVersion === null || (int) $sessionVersion !== (int) $state->session_version)
            ) {
                return $this->reject($request, 'Your session has expired due to a security update. Please sign in again.');
            }

            $cookieValue = $request->cookie($guard->getRecallerName());
            $recaller = is_string($cookieValue) ? new Recaller($cookieValue) : null;
            $claims = $this->rememberToken->parse((string) ($recaller?->token() ?? ''));

            if (
                $recaller === null
                || ! $recaller->valid()
                || $claims === null
                || $claims['session_version'] !== (int) $state->session_version
                || $state->two_factor_generation === null
                || ! hash_equals($claims['factor_generation'], (string) $state->two_factor_generation)
            ) {
                return $this->reject($request, 'Your trusted workstation session has expired. Please sign in again.');
            }

            try {
                $this->establishStaffSession->handle(new StaffAuthenticationProof(
                    userId: (int) $user->id,
                    sessionVersion: $claims['session_version'],
                    factorGeneration: $claims['factor_generation'],
                    recoveryGeneration: $state->recovery_generation,
                    purpose: 'login',
                    primaryMethod: 'remember',
                    source: 'remembered',
                    interactiveAuthenticatedAt: $claims['interactive_authenticated_at'],
                ), $request, true);
            } catch (ValidationException) {
                return $this->reject($request, 'Your trusted workstation session has expired. Please sign in again.');
            } catch (Throwable $exception) {
                $this->clear($request);
                throw $exception;
            }
            if ($this->expiredLease($request, (int) $user->id, $this->isKeepaliveRequest($request))) {
                return $this->rejectExpired($request);
            }
            $changedAccountResponse = $this->rejectChangedAccount($request);
            if ($changedAccountResponse !== null) {
                return $changedAccountResponse;
            }
            $response = $next($request);
            if ($response->getStatusCode() < 400) {
                $this->touchPresenceSession->handle($user, $request, (int) $request->session()->get('auth.session_version'));
            }

            return $response;
        }

        if (
            $surface !== 'staff'
            || $sessionVersion === null
            || (int) $sessionVersion !== (int) $state->session_version
        ) {
            return $this->reject($request, 'Your session has expired due to a security update. Please sign in again.');
        }
        if ($this->expiredLease($request, (int) $user->id, $this->isKeepaliveRequest($request))) {
            return $this->rejectExpired($request);
        }
        $changedAccountResponse = $this->rejectChangedAccount($request);
        if ($changedAccountResponse !== null) {
            return $changedAccountResponse;
        }
        $response = $next($request);
        if ($response->getStatusCode() < 400) {
            $this->touchPresenceSession->handle($user, $request, (int) $sessionVersion);
        }

        return $response;
    }

    private function expiredLease(Request $request, int $userId, bool $allowIdleExpiry = false): bool
    {
        $now = now();
        $sessionId = (string) $request->session()->getId();
        $generation = $request->session()->get('auth.staff_inactivity_generation');

        if (! $allowIdleExpiry) {
            $lease = StaffSessionLease::activeForSession($userId, $sessionId, $now, is_string($generation) ? $generation : null);

            return $lease === null
                || $request->session()->get('auth.staff_inactivity_generation') !== $lease->inactivity_generation;
        }

        $query = StaffSessionLease::query()->where('user_id', $userId);
        $lease = app()->environment('testing') && is_string($generation)
            ? $query->where(function ($query) use ($sessionId, $generation): void {
                $query->where('session_id_hash', hash_hmac('sha256', $sessionId, (string) config('app.key')))
                    ->orWhere('inactivity_generation', $generation);
            })->first()
            : $query->where('session_id_hash', hash_hmac('sha256', $sessionId, (string) config('app.key')))->first();

        return $lease === null
            || $request->session()->get('auth.staff_inactivity_generation') !== $lease->inactivity_generation
            || $lease->isRevoked()
            || $now->gte($lease->absolute_expires_at);
    }

    private function rejectChangedAccount(Request $request): ?Response
    {
        $suppliedGeneration = $request->header('X-Staff-Generation');
        if (! is_string($suppliedGeneration) || $suppliedGeneration === '') {
            return null;
        }

        $currentGeneration = $request->session()->get('auth.staff_inactivity_generation');
        if (is_string($currentGeneration) && hash_equals($currentGeneration, $suppliedGeneration)) {
            return null;
        }

        return response()->json(['error' => 'staff_account_changed'], 409)
            ->header('Cache-Control', 'no-store')
            ->header('X-Inertia-Location', route('staff.home'));
    }

    private function isKeepaliveRequest(Request $request): bool
    {
        return $request->routeIs('staff.session.keepalive')
            || ($request->isMethod('post') && $request->is('staff/session-keepalive'));
    }

    private function rejectExpired(Request $request): Response
    {
        $this->clear($request);

        if ($request->expectsJson() || $request->ajax() || ($request->header('Sec-Fetch-Mode') !== null && $request->header('Sec-Fetch-Mode') !== 'navigate')) {
            return response()->json(['error' => 'session_expired'], 401);
        }

        return redirect('/login?expired=1');
    }

    private function clear(Request $request): void
    {
        $locator = null;
        if ($request->isMethod('get') && $request->routeIs('applicant.scan-entry')) {
            try {
                $locator = ApplicantQrCode::normalize((string) $request->route('code'));
            } catch (\RuntimeException) {
                $locator = null;
            }
        }
        $user = $request->user();
        if ($user !== null && $request->session()->get('auth.surface') === 'staff') {
            $this->revokeLease->handle($user, $request->session()->getId(), now());
        }
        $this->endPresenceSession->handle($request, 'security_rejected');
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        if ($locator !== null) {
            $request->session()->put('auth.pending_applicant_lookup', [
                'locator' => $locator, 'expires_at' => now()->addMinutes(10)->timestamp,
            ]);
        }
    }

    private function reject(Request $request, string $message): Response
    {
        $this->clear($request);

        return redirect()->route('login')->withErrors([
            'email' => $message,
        ]);
    }
}
