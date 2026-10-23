<?php

namespace App\Staffing\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\User;
use App\Staffing\Support\EligibleSetupRecipients;
use App\Staffing\Support\StaffRoleCeiling;
use App\Staffing\Support\StaffTargetManagementScope;
use App\Staffing\Support\UsableHrAdministratorPool;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Sync the full role-helper set for a staff account within the actor ceiling.
 */
class SyncStaffRoles
{
    public function __construct(
        private readonly StaffRoleCeiling $ceiling,
        private readonly StaffTargetManagementScope $targetScope,
        private readonly UsableHrAdministratorPool $hrAdministratorPool,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    /**
     * @param  list<string>  $roles
     */
    public function handle(User $actor, User $target, array $roles): User
    {
        if (! $this->ceiling->canManagePersonnel($actor)) {
            throw new RuntimeException('Actor cannot manage personnel.');
        }

        if ($roles === []) {
            throw new RuntimeException('Staff must retain at least one role helper.');
        }

        if ($target->staffProfile === null) {
            throw new RuntimeException('Target is not a staff account.');
        }

        $this->assertRoleHelpersAllowed($actor, $target, $roles);
        if (! $this->ceiling->canManageUser($actor, $target)) {
            throw new RuntimeException('Cannot sync roles for staff of equal/higher rank or outside actor office.');
        }

        $keepsHr = in_array('hr_administrator', $roles, true);
        if ($target->id === $actor->id && $target->hasRole('hr_administrator') && ! $keepsHr) {
            throw new RuntimeException('Cannot revoke your own HR role helper.');
        }

        $institutionId = (int) ($actor->staffProfile?->institution_id ?? 0);
        if (! $keepsHr && $institutionId < 1) {
            throw new RuntimeException('Actor institution scope is invalid.');
        }

        return DB::transaction(function () use ($actor, $target, $roles, $institutionId, $keepsHr): User {
            $freshActor = $actor;
            if (! $keepsHr) {
                $this->hrAdministratorPool->lockInstitution($institutionId);

                $freshActor = User::query()
                    ->whereKey($actor->id)
                    ->with(['roles', 'staffProfile'])
                    ->lockForUpdate()
                    ->firstOrFail();
                $actorProfile = $freshActor->staffProfile()->lockForUpdate()->first();
                $freshActor->setRelation('staffProfile', $actorProfile);
                if (! $actorProfile?->isActive() || (int) $actorProfile->institution_id !== $institutionId) {
                    throw new RuntimeException('Actor is no longer active in this institution.');
                }
            }

            $freshTarget = User::query()
                ->whereKey($target->getKey())
                ->with(['roles', 'permissions', 'staffProfile'])
                ->lockForUpdate()
                ->firstOrFail();
            $targetProfile = $freshTarget->staffProfile()->lockForUpdate()->first();
            $freshTarget->setRelation('staffProfile', $targetProfile);
            if ($targetProfile === null) {
                throw new RuntimeException('Target is not a staff account.');
            }

            if (! $keepsHr) {
                if ((int) $targetProfile->institution_id !== $institutionId
                    || ! $this->targetScope->allows($freshActor, $freshTarget)) {
                    throw new RuntimeException('Cannot sync roles for staff of equal/higher rank or outside actor office.');
                }

                $this->assertRoleHelpersAllowed($freshActor, $freshTarget, $roles);
                if ($freshTarget->id === $freshActor->id && $freshTarget->hasRole('hr_administrator')) {
                    throw new RuntimeException('Cannot revoke your own HR role helper.');
                }

                if ($targetProfile->isActive()
                    && $freshTarget->hasRole('hr_administrator')
                    && ! $this->hrAdministratorPool->hasUsableAdministratorRemaining($institutionId, [(int) $freshTarget->id])) {
                    throw new RuntimeException('Cannot revoke the last usable HR administrator from the institution.');
                }
            }

            $freshTarget->syncRoles($roles);
            $this->recordAuditEvent->handle('hr.staff.roles_synced', [
                'user_id' => $freshTarget->id,
                'roles' => $roles,
            ], (string) $freshActor->id);

            $freshTarget = $freshTarget->fresh(['roles', 'permissions', 'staffProfile']) ?? $freshTarget;
            if (! app(EligibleSetupRecipients::class)->isEligible($freshTarget, (int) ($freshTarget->staffProfile?->institution_id ?? 0))) {
                app(RevokeInstitutionalSetupDelegation::class)->revokeForLifecycleChange($freshTarget, $freshActor, 'Staff role or office assignment changed.');
            }

            return $freshTarget->fresh(['roles', 'permissions', 'staffProfile']) ?? $freshTarget;
        });
    }

    /**
     * @param  list<string>  $roles
     */
    private function assertRoleHelpersAllowed(User $actor, User $target, array $roles): void
    {
        $allowed = $this->ceiling->assignableRoleHelpers($actor);
        $support = StaffRoleCeiling::supportRoleForOffice((string) $target->staffProfile?->office);
        if ($support !== null) {
            $allowed[] = $support;
        }

        foreach ($roles as $role) {
            if (! in_array($role, $allowed, true)) {
                throw new RuntimeException("Role helper [{$role}] is outside the actor ceiling.");
            }
        }
    }
}
