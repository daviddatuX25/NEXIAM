<?php

declare(strict_types=1);

namespace App\Staffing\Actions;

use App\Auth\Actions\InvalidateStaffAuthentication;
use App\Authorization\CapabilityAuthorizer;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\User;
use App\Staffing\Support\StaffRoleCeiling;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RequireStaffMfa
{
    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly StaffRoleCeiling $ceiling,
        private readonly InvalidateStaffAuthentication $invalidateStaffAuthentication,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(User $actor, User $target, ?string $reason = null): StaffAuthenticationState
    {
        if (! $this->ceiling->canManageMfa($actor, $target)) {
            throw new RuntimeException('Actor cannot require MFA for this staff account.');
        }

        return DB::transaction(function () use ($actor, $target, $reason): StaffAuthenticationState {
            $lockedTarget = User::query()->whereKey($target->id)->lockForUpdate()->firstOrFail();
            $profile = $lockedTarget->staffProfile()->lockForUpdate()->first();

            if ($profile === null || ! $this->ceiling->canManageMfa($actor, $lockedTarget)) {
                throw new RuntimeException('Actor cannot require MFA for this staff account.');
            }

            $state = StaffAuthenticationState::query()
                ->where('user_id', $lockedTarget->id)
                ->lockForUpdate()
                ->first();

            if ($state === null) {
                throw new RuntimeException('Target is not an onboarded staff account.');
            }

            $alreadyRequired = (bool) $state->mfa_required && ! (bool) $state->mfa_exempt;
            if (! $alreadyRequired) {
                $state->forceFill([
                    'mfa_required' => true,
                    'mfa_exempt' => false,
                ])->save();
                if ($actor->id !== $lockedTarget->id) {
                    $state = $this->invalidateStaffAuthentication->handle($lockedTarget, $state);
                }
            }

            $this->recordAuditEvent->handle('security.staff_mfa_enforced', [
                'user_id' => $lockedTarget->id,
                'reason' => $reason,
                'already_required' => $alreadyRequired,
                'self_targeted' => $actor->id === $lockedTarget->id,
            ], (string) $actor->id);

            return $state->refresh();
        });
    }
}
