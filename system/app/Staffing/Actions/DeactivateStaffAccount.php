<?php

declare(strict_types=1);

namespace App\Staffing\Actions;

use App\Auth\Actions\InvalidateStaffAuthentication;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\StaffProfile;
use App\Models\User;
use App\Staffing\Support\StaffTargetManagementScope;
use App\Staffing\Support\UsableHrAdministratorPool;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;

class DeactivateStaffAccount
{
    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly StaffTargetManagementScope $targetScope,
        private readonly UsableHrAdministratorPool $hrAdministratorPool,
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly InvalidateStaffAuthentication $invalidateStaffAuthentication,
    ) {}

    public function handle(User $actor, User $target): User
    {
        $institutionId = (int) ($actor->staffProfile?->institution_id ?? 0);
        if ($institutionId < 1) {
            throw new RuntimeException('Actor institution scope is invalid.');
        }

        $actorId = (int) $actor->id;
        $targetId = (int) $target->id;

        return DB::transaction(function () use ($actorId, $institutionId, $targetId): User {
            $this->hrAdministratorPool->lockInstitution($institutionId);

            $lockedActor = User::query()
                ->whereKey($actorId)
                ->with(['roles', 'staffProfile'])
                ->lockForUpdate()
                ->firstOrFail();
            $actorProfile = $lockedActor->staffProfile()->lockForUpdate()->first();
            $lockedActor->setRelation('staffProfile', $actorProfile);
            if (! $actorProfile?->isActive() || (int) $actorProfile->institution_id !== $institutionId) {
                throw new RuntimeException('Actor is no longer active in this institution.');
            }
            if (! $this->canManagePersonnelFresh($lockedActor, $institutionId)) {
                throw new RuntimeException('Actor personnel authority has been revoked.');
            }
            if ($targetId === $actorId) {
                throw new RuntimeException('Cannot deactivate your own account.');
            }

            $lockedTarget = User::query()
                ->whereKey($targetId)
                ->with('roles')
                ->lockForUpdate()
                ->firstOrFail();
            $profile = $lockedTarget->staffProfile()->lockForUpdate()->first();
            $state = StaffAuthenticationState::query()
                ->where('user_id', $lockedTarget->id)
                ->lockForUpdate()
                ->first();
            if ($state === null || $profile === null) {
                throw new RuntimeException('Target is not an onboarded staff account.');
            }
            if (! $this->targetScope->allows($lockedActor, $lockedTarget)) {
                throw new RuntimeException('Cannot deactivate staff of equal/higher rank or outside actor office.');
            }

            if ($profile->isActive()) {
                $excludedHrIds = $lockedTarget->hasRole('hr_administrator') ? [(int) $lockedTarget->id] : [];
                if (! $this->hrAdministratorPool->hasUsableAdministratorRemaining($institutionId, $excludedHrIds)) {
                    throw new RuntimeException('Cannot deactivate: at least one active HR administrator with confirmed multi-factor authentication must remain in the institution.');
                }
            }

            if ($profile->isActive()) {
                $profile->update([
                    'status' => StaffProfile::STATUS_INACTIVE,
                    'deactivated_at' => now(),
                ]);
                $this->invalidateStaffAuthentication->handle($lockedTarget, $state);
            }
            app(RevokeInstitutionalSetupDelegation::class)->revokeForLifecycleChange($lockedTarget, $lockedActor, 'Account deactivated.');

            $delegatedTaCapabilities = CapabilityCatalog::delegatedTestAdministratorCapabilities();
            $hasDelegatedTaPermissions = false;
            foreach ($delegatedTaCapabilities as $capability) {
                if ($lockedTarget->hasDirectPermission($capability)) {
                    $hasDelegatedTaPermissions = true;
                    break;
                }
            }
            if ($hasDelegatedTaPermissions) {
                $lockedTarget->revokePermissionTo($delegatedTaCapabilities);
                app(PermissionRegistrar::class)->forgetCachedPermissions();
                $this->recordAuditEvent->handle('staff.ta_capabilities_revoked', [
                    'user_id' => $lockedTarget->id,
                    'capabilities' => $delegatedTaCapabilities,
                ], (string) $lockedActor->id);
            }

            $this->recordAuditEvent->handle('hr.staff.deactivated', [
                'user_id' => $lockedTarget->id,
                'email' => $lockedTarget->email,
            ], (string) $lockedActor->id);

            return $lockedTarget->fresh(['staffProfile']);
        });
    }

    private function canManagePersonnelFresh(User $actor, int $institutionId): bool
    {
        if ($this->authorizer->decideFresh($actor, CapabilityCatalog::HR_STAFF_MANAGE, [
            'institution_id' => $institutionId,
        ])->allowed) {
            return true;
        }

        $office = (string) ($actor->staffProfile?->office ?? '');
        if ($office !== '' && $this->authorizer->decideFresh($actor, CapabilityCatalog::OFFICE_SUPPORT_STAFF_MANAGE, [
            'institution_id' => $institutionId,
            'office' => $office,
        ])->allowed) {
            return true;
        }

        return $this->targetScope->ownedActiveDepartmentIds($actor, $institutionId) !== [];
    }
}
