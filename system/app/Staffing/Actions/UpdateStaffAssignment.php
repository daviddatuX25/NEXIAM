<?php

declare(strict_types=1);

namespace App\Staffing\Actions;

use App\Auth\Actions\InvalidateStaffAuthentication;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\Auth\StaffProviderIdentity;
use App\Models\User;
use App\Staffing\Support\EligibleSetupRecipients;
use App\Staffing\Support\StaffRoleCeiling;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class UpdateStaffAssignment
{
    public function __construct(
        private readonly StaffRoleCeiling $ceiling,
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly InvalidateStaffAuthentication $invalidateStaffAuthentication,
    ) {}

    /**
     * @param  list<string>|null  $programCodes
     */
    public function handle(
        User $actor,
        User $target,
        string $name,
        ?string $email,
        string $office,
        ?array $programCodes = null,
        ?string $activeCycleCode = null,
    ): User {
        if (! $this->ceiling->canManagePersonnel($actor)) {
            throw new RuntimeException('Actor cannot manage personnel.');
        }

        if (! $this->ceiling->canManageUser($actor, $target)) {
            throw new RuntimeException('Cannot update staff of equal/higher rank or outside actor office.');
        }

        $result = DB::transaction(function () use ($actor, $target, $name, $email, $office, $programCodes, $activeCycleCode): array {
            $lockedTarget = User::query()->whereKey($target->id)->lockForUpdate()->firstOrFail();
            $state = StaffAuthenticationState::query()->where('user_id', $lockedTarget->id)->lockForUpdate()->first();
            $profile = $lockedTarget->staffProfile()->lockForUpdate()->first();

            if ($state === null || $profile === null) {
                throw new RuntimeException('Target staff authentication state is missing.');
            }

            $emailChanged = $email !== null && $lockedTarget->email !== $email;
            $sessionVersion = session('auth.session_version');
            $isCurrentTargetSession = (string) Auth::id() === (string) $lockedTarget->id
                && session('auth.surface') === 'staff'
                && $sessionVersion !== null
                && (int) $sessionVersion === (int) $state->session_version;

            if (
                (string) $actor->id === (string) $lockedTarget->id
                && (string) Auth::id() === (string) $lockedTarget->id
                && ! $isCurrentTargetSession
            ) {
                throw new RuntimeException('The current staff session is stale.');
            }

            $lockedTarget->name = $name;
            if ($email !== null) {
                $lockedTarget->email = $email;
            }
            $lockedTarget->save();

            $effectiveOffice = $this->ceiling->isFullHr($actor) ? $office : $profile->office;
            $effectiveProgramCodes = $effectiveOffice === 'program'
                ? ($programCodes ?? $profile->program_codes ?? [])
                : [];
            $departmentId = $effectiveOffice === 'program' ? $profile->department_id : null;

            $profile->update([
                'office' => $effectiveOffice,
                'department_id' => $departmentId,
                'program_codes' => $effectiveProgramCodes,
                'active_cycle_code' => $activeCycleCode ?? $profile->active_cycle_code,
            ]);
            $freshTarget = $lockedTarget->fresh(['roles', 'permissions', 'staffProfile']) ?? $lockedTarget;
            if (! app(EligibleSetupRecipients::class)->isEligible($freshTarget, (int) ($freshTarget->staffProfile?->institution_id ?? 0))) {
                app(RevokeInstitutionalSetupDelegation::class)->revokeForLifecycleChange($freshTarget, $actor, 'Staff role or office assignment changed.');
            }

            if ($emailChanged) {
                StaffProviderIdentity::query()
                    ->where('user_id', $lockedTarget->id)
                    ->whereNull('revoked_at')
                    ->update(['revoked_at' => now()]);
                $state = $this->invalidateStaffAuthentication->handle($lockedTarget, $state);
            }

            $this->recordAuditEvent->handle('hr.staff.updated', [
                'user_id' => $lockedTarget->id,
                'email' => $lockedTarget->email,
                'office' => $profile->fresh()->office,
            ], (string) $actor->id);

            return [
                'user' => $freshTarget->fresh(['staffProfile', 'roles', 'permissions']) ?? $freshTarget,
                'session_version' => $isCurrentTargetSession && $emailChanged
                    ? (int) $state->session_version
                    : null,
            ];
        });

        if ($result['session_version'] !== null) {
            session(['auth.session_version' => $result['session_version']]);
        }

        return $result['user'];
    }
}
