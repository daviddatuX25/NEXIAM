<?php

declare(strict_types=1);

namespace App\Http\Controllers\Staff;

use App\Assessment\Omr\Canonical\OmrQrCodeRenderer;
use App\Auth\Actions\InvalidateStaffAuthentication;
use App\Auth\Support\StaffMfaPolicy;
use App\Auth\Support\StaffSurface;
use App\Foundation\Actions\RecordAuditEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\UpdateStaffProfileNameRequest;
use App\Http\Requests\Staffing\UpdateStaffAvatarRequest;
use App\Models\Auth\StaffEmailChange;
use App\Models\Auth\StaffProviderIdentity;
use App\Models\User;
use App\Staffing\Actions\BeginStaffProfileTwoFactorEnrollment;
use App\Staffing\Actions\BeginStaffTwoFactorReEnrollment;
use App\Staffing\Actions\CancelStaffEmailChange;
use App\Staffing\Actions\ConfirmStaffEmailChange;
use App\Staffing\Actions\ConfirmStaffProfileTwoFactorEnrollment;
use App\Staffing\Actions\ConfirmStaffTwoFactorReEnrollment;
use App\Staffing\Actions\DisableStaffMfa;
use App\Staffing\Actions\RegenerateStaffRecoveryCodes;
use App\Staffing\Actions\RequestStaffEmailChange;
use App\Staffing\Actions\RequireStaffMfa;
use App\Staffing\Actions\UpdateStaffAvatar;
use App\Staffing\Actions\UpdateStaffDirectoryInfo;
use App\Staffing\Actions\UpdateStaffSelfPassword;
use App\Staffing\Support\StaffRoleCeiling;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use PragmaRX\Google2FA\Google2FA;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ProfileController extends Controller
{
    public function show(Request $request, StaffMfaPolicy $mfaPolicy, StaffSurface $staffSurface, StaffRoleCeiling $ceiling): Response
    {
        abort_unless($staffSurface->allows($request), 403);
        $user = $request->user()->loadMissing(['staffProfile', 'authenticationState']);
        $state = $user->authenticationState;
        $mfaRequired = $mfaPolicy->isMfaRequired($user);
        $mfaEnrolled = $state?->hasConfirmedTwoFactor() ?? false;
        $pendingEmail = StaffEmailChange::query()
            ->where('user_id', $user->id)
            ->whereNull('confirmed_at')
            ->whereNull('cancelled_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();
        $recoveryCodesRemaining = $state?->recoveryCodes()
            ->where('generation', $state->recovery_generation)
            ->whereNull('consumed_at')
            ->whereNull('revoked_at')
            ->count() ?? 0;
        $googleLinked = StaffProviderIdentity::query()
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->exists();

        return Inertia::render('Staff/Profile/Show', [
            'page_state' => 'ready',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'office' => $user->staffProfile?->office,
                'status' => $user->staffProfile?->status,
                'roles' => $user->getRoleNames()->values()->all(),
                'avatar' => $user->staffProfile?->avatarProjection() ?? [
                    'type' => 'initials',
                    'preset' => null,
                    'url' => null,
                ],
            ],
            'directory' => $user->staffProfile?->directoryProjection() ?? [
                'phone_extension' => null,
                'room_number' => null,
                'consultation_hours' => null,
            ],
            'security' => [
                'has_local_password' => $state?->password_set_at !== null,
                'mfa_enrolled' => $mfaEnrolled,
                'mfa_status' => $mfaEnrolled ? 'enrolled' : ($mfaRequired ? 'pending' : 'exempt'),
                'mfa_required' => $mfaRequired,
                'can_toggle_mfa' => $ceiling->canManageMfa($user, $user),
                'google_linked' => $googleLinked,
                'recovery_codes_remaining' => $recoveryCodesRemaining,
                'pending_email' => $pendingEmail === null ? null : [
                    'new_email' => $pendingEmail->new_email,
                    'expires_at' => $pendingEmail->expires_at->toIso8601String(),
                ],
            ],
        ]);
    }

    public function beginProfileMfaEnrollment(
        Request $request,
        BeginStaffProfileTwoFactorEnrollment $action,
        OmrQrCodeRenderer $qr,
    ): JsonResponse {
        $request->validate(['password' => 'required|string']);
        /** @var User $user */
        $user = $request->user();
        $result = $action->handle($user, $request->input('password'), $qr, $request);

        return response()->json([
            'status' => 'ok',
            'secret' => $result['secret'],
            'qr_code' => $result['qr_code'],
            'expires_at' => $result['expires_at']->toISOString(),
        ])->header('Cache-Control', 'no-store');
    }

    public function confirmProfileMfaEnrollment(
        Request $request,
        ConfirmStaffProfileTwoFactorEnrollment $action,
    ): JsonResponse {
        $code = preg_replace('/\D/', '', (string) $request->input('code'));
        $request->merge(['code' => $code]);
        $request->validate(['code' => ['required', 'digits:6']]);
        /** @var User $user */
        $user = $request->user();
        $recoveryCodes = $action->handle($user, $code, $request);

        return response()->json([
            'status' => 'ok',
            'recovery_codes' => $recoveryCodes,
        ])->header('Cache-Control', 'no-store');
    }

    public function requireMfa(Request $request, RequireStaffMfa $action, StaffRoleCeiling $ceiling): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user !== null && $ceiling->canManageMfa($user, $user), 403);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $action->handle($user, $user, $data['reason'] ?? null);

        return back()->with('status', 'MFA requirement has been enabled for your account.');
    }

    public function disableMfa(Request $request, DisableStaffMfa $action, StaffRoleCeiling $ceiling): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user !== null && $ceiling->canManageMfa($user, $user), 403);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $action->handle($user, $user, $data['reason'] ?? null);

        return back()->with('status', 'MFA has been turned off for your account.');
    }

    public function updateAvatar(UpdateStaffAvatarRequest $request, UpdateStaffAvatar $action): RedirectResponse
    {
        $action->handle($request->user(), $request->only(['type', 'preset']), $request->file('photo'));

        return back()->with('status', 'Avatar updated successfully.');
    }

    public function updateDirectory(Request $request, UpdateStaffDirectoryInfo $action): RedirectResponse
    {
        $request->validate([
            'phone_extension' => ['nullable', 'string', 'max:30'],
            'room_number' => ['nullable', 'string', 'max:100'],
            'consultation_hours' => ['nullable', 'string', 'max:255'],
        ]);

        $action->handle($request->user(), $request->only(['phone_extension', 'room_number', 'consultation_hours']));

        return back()->with('status', 'Directory information updated successfully.');
    }

    public function updateName(UpdateStaffProfileNameRequest $request, RecordAuditEvent $recordAuditEvent): RedirectResponse
    {
        $validated = $request->validated();
        /** @var User $user */
        $user = $request->user();
        DB::transaction(function () use ($user, $validated, $recordAuditEvent): void {
            $user->forceFill(['name' => $validated['name']])->save();

            $recordAuditEvent->handle('auth.staff_name.updated', [
                'user_id' => $user->id,
            ], (string) $user->id);
        });

        return redirect()->route('staff.profile')->with('status', 'Name updated.');
    }

    public function unlinkGoogle(
        Request $request,
        RecordAuditEvent $recordAuditEvent,
        InvalidateStaffAuthentication $invalidateStaffAuthentication,
    ): RedirectResponse {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'totp_code' => ['nullable', 'string'],
        ]);
        /** @var User $requestUser */
        $requestUser = $request->user();

        $sessionVersionToRetain = DB::transaction(function () use ($validated, $requestUser, $request, $recordAuditEvent, $invalidateStaffAuthentication): ?int {
            $user = User::query()->whereKey($requestUser->id)->lockForUpdate()->firstOrFail();
            $state = $user->authenticationState()->lockForUpdate()->first();
            $profile = $user->staffProfile()->lockForUpdate()->first();

            if (
                $state === null
                || ! $profile?->isActive()
                || $state->password_set_at === null
                || (string) Auth::id() !== (string) $user->id
                || $request->session()->get('auth.surface') !== 'staff'
                || $request->session()->get('auth.session_version') === null
                || (int) $request->session()->get('auth.session_version') !== (int) $state->session_version
                || ! Hash::check($validated['current_password'], $user->password)
            ) {
                throw ValidationException::withMessages([
                    'current_password' => 'The current account proof is invalid.',
                ]);
            }

            if ($state->hasConfirmedTwoFactor()) {
                $step = is_string($validated['totp_code'] ?? null) && is_string($state->two_factor_secret)
                    ? (new Google2FA)->verifyKeyNewer(
                        $state->two_factor_secret,
                        $validated['totp_code'],
                        $state->two_factor_last_accepted_step ?? -1,
                    )
                    : false;

                if ($step === false) {
                    throw ValidationException::withMessages([
                        'totp_code' => 'The authenticator code is invalid.',
                    ]);
                }

                $state->forceFill(['two_factor_last_accepted_step' => $step])->save();
            }

            if ($state->two_factor_generation === null) {
                $state->forceFill(['two_factor_generation' => (string) Str::uuid()])->save();
            }

            $identities = StaffProviderIdentity::query()
                ->where('user_id', $user->id)
                ->where('provider', 'google')
                ->whereNull('revoked_at')
                ->lockForUpdate()
                ->get();

            if ($identities->isNotEmpty()) {
                StaffProviderIdentity::query()
                    ->where('user_id', $user->id)
                    ->where('provider', 'google')
                    ->whereNull('revoked_at')
                    ->update(['revoked_at' => now()]);

                $state = $invalidateStaffAuthentication->handle($user, $state);
                $recordAuditEvent->handle('auth.staff_google_unlinked', [
                    'user_id' => $user->id,
                ], (string) $user->id);

                return (int) $state->session_version;
            }

            return null;
        });

        if ($sessionVersionToRetain !== null) {
            $request->session()->put('auth.session_version', $sessionVersionToRetain);
        }

        return redirect()->route('staff.profile')->with('status', 'Google account unlinked.');
    }

    public function regenerateRecoveryCodes(Request $request, RegenerateStaffRecoveryCodes $action): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'totp_code' => ['nullable', 'string'],
        ]);
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'recovery_codes' => $action->handle($user, $validated['current_password'], $validated['totp_code'] ?? null, $request),
        ])->header('Cache-Control', 'no-store');
    }

    public function beginTwoFactorReEnrollment(
        Request $request,
        BeginStaffTwoFactorReEnrollment $action,
        OmrQrCodeRenderer $qr,
    ): JsonResponse {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
        ]);
        /** @var User $user */
        $user = $request->user();
        $result = $action->handle($user, $validated['current_password'], $qr, $request);

        return response()->json([
            'secret' => $result['secret'],
            'qr_svg' => $result['qr_svg'],
            'expires_at' => $result['expires_at']->toIso8601String(),
        ])->header('Cache-Control', 'no-store');
    }

    public function confirmTwoFactorReEnrollment(Request $request, ConfirmStaffTwoFactorReEnrollment $action): JsonResponse
    {
        $code = preg_replace('/\D/', '', (string) $request->input('code'));
        $request->merge(['code' => $code]);
        $validated = $request->validate([
            'code' => ['required', 'digits:6'],
        ]);
        /** @var User $user */
        $user = $request->user();

        return response()->json($action->handle($user, $validated['code'], $request))
            ->header('Cache-Control', 'no-store');
    }

    public function updatePassword(Request $request, UpdateStaffSelfPassword $action): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'totp_code' => ['nullable', 'string'],
        ]);
        /** @var User $user */
        $user = $request->user();
        $action->handle($user, $validated['current_password'], $validated['password'], $validated['totp_code'] ?? null);

        return redirect()->route('staff.profile')->with('status', 'Password updated. Please sign in again on other devices.');
    }

    public function requestEmailChange(Request $request, RequestStaffEmailChange $action): RedirectResponse
    {
        $validated = $request->validate([
            'new_email' => ['required', 'string', 'email', 'max:255'],
            'current_password' => ['required', 'string'],
            'totp_code' => ['nullable', 'string'],
        ]);
        /** @var User $user */
        $user = $request->user();
        $action->handle($user, $validated['new_email'], $validated['current_password'], $validated['totp_code'] ?? null);

        return redirect()->route('staff.profile')->with('status', 'A confirmation link was sent to the new email address.');
    }

    public function cancelEmailChange(Request $request, CancelStaffEmailChange $action): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $action->handle($user);

        return redirect()->route('staff.profile')->with('status', 'Pending email change cancelled.');
    }

    public function confirmEmailChange(string $token, ConfirmStaffEmailChange $action): HttpResponse|RedirectResponse
    {
        $record = $action->findPending($token);
        if ($record === null) {
            throw ValidationException::withMessages(['token' => 'This email confirmation link is invalid or expired.']);
        }

        if (request()->isMethod('get') || request()->isMethod('head')) {
            $user = Auth::user();
            $targetUser = $record->user()->with(['authenticationState', 'staffProfile'])->first();
            $props = [
                'page_state' => 'confirm_email',
                'user' => $user instanceof User ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'office' => $user->staffProfile?->office,
                    'status' => $user->staffProfile?->status,
                    'roles' => $user->getRoleNames()->values()->all(),
                ] : null,
                'security' => null,
                'email_confirmation' => [
                    'new_email' => $record->new_email,
                    'token' => $token,
                    'expires_at' => $record->expires_at->toIso8601String(),
                    'mfa_enrolled' => $targetUser?->authenticationState?->hasConfirmedTwoFactor() ?? false,
                ],
            ];

            return Inertia::render('Staff/Profile/Show', $props)
                ->toResponse(request())
                ->withHeaders([
                    'Cache-Control' => 'no-store, private',
                    'Referrer-Policy' => 'no-referrer',
                ]);
        }

        $validated = request()->validate([
            'current_password' => ['required', 'string'],
            'totp_code' => ['nullable', 'string'],
        ]);
        /** @var User|null $user */
        $user = Auth::user();
        if ($user === null) {
            abort(401);
        }

        $action->handle($token, $user, $validated['current_password'], $validated['totp_code'] ?? null);

        return to_route('staff.profile')->with('status', 'Email address confirmed.');
    }
}
