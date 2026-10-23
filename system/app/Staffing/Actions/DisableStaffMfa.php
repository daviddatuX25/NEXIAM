<?php

declare(strict_types=1);

namespace App\Staffing\Actions;

use App\Auth\Actions\InvalidateStaffAuthentication;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\Auth\StaffTwoFactorRecoveryCode;
use App\Models\User;
use App\Staffing\Support\StaffRoleCeiling;
use App\Staffing\Support\UsableHrAdministratorPool;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class DisableStaffMfa
{
    public function __construct(
        private readonly StaffRoleCeiling $ceiling,
        private readonly UsableHrAdministratorPool $hrAdministratorPool,
        private readonly InvalidateStaffAuthentication $invalidateStaffAuthentication,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(User $actor, User $target, ?string $reason = null): StaffAuthenticationState
    {
        $institutionId = (int) ($actor->staffProfile?->institution_id ?? 0);
        if ($institutionId < 1 || ! $this->ceiling->canManageMfa($actor, $target)) {
            throw new RuntimeException('Actor cannot disable MFA for this staff account.');
        }

        return DB::transaction(function () use ($actor, $target, $reason, $institutionId): StaffAuthenticationState {
            $this->hrAdministratorPool->lockInstitution($institutionId);
            $lockedActor = User::query()
                ->whereKey($actor->id)
                ->with(['roles', 'staffProfile'])
                ->lockForUpdate()
                ->firstOrFail();
            $actorProfile = $lockedActor->staffProfile()->lockForUpdate()->first();
            $lockedActor->setRelation('staffProfile', $actorProfile);
            if (! $actorProfile?->isActive() || (int) $actorProfile->institution_id !== $institutionId) {
                throw new RuntimeException('Actor cannot disable MFA for this staff account.');
            }
            $lockedTarget = User::query()
                ->whereKey($target->id)
                ->with('roles')
                ->lockForUpdate()
                ->firstOrFail();
            $profile = $lockedTarget->staffProfile()->lockForUpdate()->first();
            $lockedTarget->setRelation('staffProfile', $profile);

            if ($profile === null || ! $this->ceiling->canManageMfa($lockedActor, $lockedTarget)) {
                throw new RuntimeException('Actor cannot disable MFA for this staff account.');
            }

            $state = StaffAuthenticationState::query()
                ->where('user_id', $lockedTarget->id)
                ->lockForUpdate()
                ->first();
            if ($state === null) {
                throw new RuntimeException('Target is not an onboarded staff account.');
            }

            if ($this->hrAdministratorPool->isUsableAdministrator($lockedTarget, $profile, $state, $institutionId)
                && ! $this->hrAdministratorPool->hasUsableAdministratorRemaining($institutionId, [(int) $lockedTarget->id])) {
                throw new ConflictHttpException('Cannot disable MFA: at least one active HR administrator with confirmed multi-factor authentication must remain in the institution.');
            }

            $wasRequired = (bool) $state->mfa_required || ! (bool) $state->mfa_exempt;
            $hadConfirmedFactor = $state->hasConfirmedTwoFactor();
            $hadPendingFactor = $state->pending_two_factor_secret !== null || $state->pending_enrollment_attempt_id !== null;

            StaffTwoFactorRecoveryCode::query()
                ->where('user_id', $lockedTarget->id)
                ->whereNull('consumed_at')
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            $state->forceFill([
                'mfa_required' => false,
                'mfa_exempt' => true,
                'two_factor_secret' => null,
                'two_factor_confirmed_at' => null,
                'two_factor_last_accepted_step' => null,
                'two_factor_generation' => (string) Str::uuid(),
                'recovery_generation' => (string) Str::uuid(),
                'pending_two_factor_secret' => null,
                'pending_two_factor_expires_at' => null,
                'pending_enrollment_attempt_id' => null,
            ])->save();

            if ($lockedActor->id !== $lockedTarget->id && ($wasRequired || $hadConfirmedFactor || $hadPendingFactor)) {
                $state = $this->invalidateStaffAuthentication->handle($lockedTarget, $state);
            }

            $this->recordAuditEvent->handle('security.staff_mfa_disabled', [
                'user_id' => $lockedTarget->id,
                'reason' => $reason,
                'had_confirmed_factor' => $hadConfirmedFactor,
                'self_targeted' => $lockedActor->id === $lockedTarget->id,
            ], (string) $lockedActor->id);

            return $state->refresh();
        });
    }
}
