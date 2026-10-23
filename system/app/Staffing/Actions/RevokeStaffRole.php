<?php

namespace App\Staffing\Actions;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\User;
use App\Staffing\Support\StaffRoleCeiling;
use App\Staffing\Support\StaffTargetManagementScope;
use App\Staffing\Support\UsableHrAdministratorPool;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RevokeStaffRole
{
    public function __construct(
        private readonly StaffRoleCeiling $ceiling,
        private readonly CapabilityAuthorizer $authorizer,
        private readonly StaffTargetManagementScope $targetScope,
        private readonly UsableHrAdministratorPool $hrAdministratorPool,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(User $actor, User $target, string $role): User
    {
        if (! $this->ceiling->canManagePersonnel($actor)) {
            throw new RuntimeException('Actor cannot manage personnel.');
        }

        if ($target->id === $actor->id && $role === 'hr_administrator') {
            throw new RuntimeException('Cannot revoke your own HR role helper.');
        }

        if ($target->staffProfile === null) {
            throw new RuntimeException('Target is not a staff account.');
        }

        if (! $this->ceiling->canManageUser($actor, $target)) {
            throw new RuntimeException('Cannot revoke roles from staff of equal/higher rank or outside actor office.');
        }

        $this->ceiling->assertRolesAllowed($actor, [$role]);
        $institutionId = (int) ($actor->staffProfile?->institution_id ?? 0);

        return DB::transaction(function () use ($actor, $target, $role, $institutionId): User {
            $roleTarget = $target;
            $auditActor = $actor;

            if ($role === 'hr_administrator') {
                if ($institutionId < 1) {
                    throw new RuntimeException('Actor institution scope is invalid.');
                }

                $this->hrAdministratorPool->lockInstitution($institutionId);

                $lockedActor = User::query()
                    ->whereKey($actor->id)
                    ->with(['roles', 'staffProfile'])
                    ->lockForUpdate()
                    ->firstOrFail();
                $actorProfile = $lockedActor->staffProfile()->lockForUpdate()->first();
                $lockedActor->setRelation('staffProfile', $actorProfile);
                if (! $actorProfile?->isActive() || (int) $actorProfile->institution_id !== $institutionId) {
                    throw new RuntimeException('Actor is no longer active in this institution.');
                }
                if (! $this->authorizer->decideFresh($lockedActor, CapabilityCatalog::HR_STAFF_MANAGE, [
                    'institution_id' => $institutionId,
                ])->allowed) {
                    throw new RuntimeException('Actor HR authority has been revoked.');
                }

                $roleTarget = User::query()
                    ->whereKey($target->id)
                    ->with(['roles', 'staffProfile'])
                    ->lockForUpdate()
                    ->firstOrFail();
                $targetProfile = $roleTarget->staffProfile()->lockForUpdate()->first();
                $roleTarget->setRelation('staffProfile', $targetProfile);
                if ($targetProfile === null || (int) $targetProfile->institution_id !== $institutionId) {
                    throw new RuntimeException('Target is not an onboarded staff account in this institution.');
                }
                if (! $this->ceiling->canManageUser($lockedActor, $roleTarget)
                    || ! $this->targetScope->allows($lockedActor, $roleTarget)) {
                    throw new RuntimeException('Cannot revoke roles from staff of equal/higher rank or outside actor office.');
                }
                if ($roleTarget->id === $lockedActor->id) {
                    throw new RuntimeException('Cannot revoke your own HR role helper.');
                }
                $this->ceiling->assertRolesAllowed($lockedActor, [$role]);

                if ($targetProfile->isActive()
                    && $roleTarget->hasRole('hr_administrator')
                    && ! $this->hrAdministratorPool->hasUsableAdministratorRemaining($institutionId, [(int) $roleTarget->id])) {
                    throw new RuntimeException('Cannot revoke the last usable HR administrator from the institution.');
                }

                $auditActor = $lockedActor;
            }

            $roleTarget->removeRole($role);

            if ($roleTarget->roles()->count() < 1) {
                throw new RuntimeException('Staff must retain at least one role helper.');
            }

            $this->recordAuditEvent->handle('hr.staff.role_revoked', [
                'user_id' => $roleTarget->id,
                'role' => $role,
                'capability_ref' => CapabilityCatalog::HR_STAFF_MANAGE,
            ], (string) $auditActor->id);

            return $roleTarget->fresh(['roles']);
        });
    }
}
