<?php

declare(strict_types=1);

namespace App\Staffing\Actions;

use App\Auth\Actions\InvalidateStaffAuthentication;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Department;
use App\Models\Foundation\Program;
use App\Models\StaffProfile;
use App\Models\User;
use App\Staffing\Support\StaffTargetManagementScope;
use App\Staffing\Support\UsableHrAdministratorPool;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class TransitionStaffCycleDisposition
{
    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly InvalidateStaffAuthentication $invalidateStaffAuthentication,
        private readonly StaffTargetManagementScope $targetScope,
        private readonly UsableHrAdministratorPool $hrAdministratorPool,
    ) {}

    /**
     * @param  list<array{user_id: int, disposition: string, program_codes?: list<string>, department_id?: int|null}>  $dispositions
     * @return array{retained: int, rotated: int, archived: int, rows: list<array{user_id: int, disposition: string, status: string}>}
     */
    public function handle(User $actor, string $destinationCycleCode, array $dispositions): array
    {
        $institutionId = (int) ($actor->staffProfile?->institution_id ?? 0);
        if ($institutionId < 1) {
            throw new AuthorizationException('Actor institution scope is invalid.');
        }
        $actorId = (int) $actor->id;

        return DB::transaction(function () use ($actorId, $institutionId, $destinationCycleCode, $dispositions): array {
            $this->hrAdministratorPool->lockInstitution($institutionId);

            $lockedActor = User::query()
                ->whereKey($actorId)
                ->with(['roles', 'staffProfile'])
                ->lockForUpdate()
                ->firstOrFail();
            $actorProfile = $lockedActor->staffProfile()->lockForUpdate()->first();
            $lockedActor->setRelation('staffProfile', $actorProfile);

            if (! $actorProfile?->isActive() || (int) $actorProfile->institution_id !== $institutionId) {
                throw new AuthorizationException('Actor is no longer active in this institution.');
            }
            if (! $this->canManagePersonnelFresh($lockedActor, $institutionId)) {
                throw new AuthorizationException('Actor personnel authority has been revoked.');
            }
            $isFullHr = $this->authorizer->decideFresh(
                $lockedActor,
                CapabilityCatalog::HR_STAFF_MANAGE,
                ['institution_id' => $institutionId],
            )->allowed;

            $cycle = AcademicCycle::query()
                ->where('institution_id', $institutionId)
                ->where('code', $destinationCycleCode)
                ->lockForUpdate()
                ->first();
            if ($cycle === null) {
                throw ValidationException::withMessages([
                    'destination_cycle_code' => "Destination academic cycle [{$destinationCycleCode}] is not found in this institution.",
                ]);
            }

            $targetIdsToArchive = collect($dispositions)
                ->filter(fn (array $d) => ($d['disposition'] ?? '') === 'archive')
                ->pluck('user_id')
                ->map(fn ($id): int => (int) $id)
                ->all();
            if (in_array((int) $lockedActor->id, $targetIdsToArchive, true)) {
                throw new AuthorizationException('Cannot archive your own account during cycle rollover.');
            }

            if ($targetIdsToArchive !== []
                && ! $this->hrAdministratorPool->hasUsableAdministratorRemaining($institutionId, $targetIdsToArchive)) {
                throw new ConflictHttpException('Cannot archive: at least one active HR administrator with confirmed multi-factor authentication must remain in the institution.');
            }

            $summary = ['retained' => 0, 'rotated' => 0, 'archived' => 0, 'rows' => []];

            foreach ($dispositions as $item) {
                $userId = (int) ($item['user_id'] ?? 0);
                $disposition = (string) ($item['disposition'] ?? 'retain');

                if (! in_array($disposition, ['retain', 'rotate', 'archive'], true)) {
                    throw ValidationException::withMessages([
                        'dispositions' => "Unsupported disposition [{$disposition}].",
                    ]);
                }

                $target = User::query()->whereKey($userId)->with('roles')->lockForUpdate()->firstOrFail();
                $profile = $target->staffProfile()->lockForUpdate()->first();
                $state = StaffAuthenticationState::query()->where('user_id', $target->id)->lockForUpdate()->first();

                if ($profile === null || (int) $profile->institution_id !== $institutionId) {
                    throw new AuthorizationException("Target user #{$userId} is outside your institution scope.");
                }

                if (! $profile->isActive()) {
                    throw new ConflictHttpException("Cannot retain, rotate, or archive inactive staff #{$userId}. Reactivate the account first.");
                }

                if ($state === null) {
                    throw new ConflictHttpException("Target staff account #{$userId} is missing its authentication state.");
                }

                if (! $isFullHr && ! $this->targetScope->allows($lockedActor, $target)) {
                    throw new AuthorizationException("Cannot manage personnel #{$userId} of equal or higher rank or outside actor scope.");
                }

                if ($disposition === 'archive') {
                    $profile->update([
                        'status' => StaffProfile::STATUS_INACTIVE,
                        'deactivated_at' => now(),
                    ]);
                    $this->invalidateStaffAuthentication->handle($target, $state);

                    app(RevokeInstitutionalSetupDelegation::class)->revokeForLifecycleChange($target, $lockedActor, 'Account archived in cycle rollover.');

                    $delegatedTaCapabilities = CapabilityCatalog::delegatedTestAdministratorCapabilities();
                    $hasDelegatedTa = false;
                    foreach ($delegatedTaCapabilities as $capability) {
                        if ($target->hasDirectPermission($capability)) {
                            $hasDelegatedTa = true;
                            break;
                        }
                    }
                    if ($hasDelegatedTa) {
                        $target->revokePermissionTo($delegatedTaCapabilities);
                        app(PermissionRegistrar::class)->forgetCachedPermissions();
                    }

                    $this->recordAuditEvent->handle('staff.cycle_disposition.archived', [
                        'user_id' => $target->id,
                        'destination_cycle' => $destinationCycleCode,
                    ], (string) $lockedActor->id);

                    $summary['archived']++;
                    $summary['rows'][] = ['user_id' => $target->id, 'disposition' => 'archive', 'status' => 'inactive'];
                } elseif ($disposition === 'rotate') {
                    $programCodesProvided = isset($item['program_codes']);
                    $departmentIdProvided = array_key_exists('department_id', $item) && $item['department_id'] !== null;
                    $assignmentRequested = $programCodesProvided || $departmentIdProvided;
                    $newProgramCodes = $programCodesProvided
                        ? array_values((array) $item['program_codes'])
                        : (array) ($profile->program_codes ?? []);

                    if ($assignmentRequested && ! $profile->isAcademic()) {
                        throw ValidationException::withMessages([
                            'dispositions' => 'Program and department assignments can only be rotated for academic staff.',
                        ]);
                    }

                    if ($assignmentRequested && (
                        array_filter($newProgramCodes, fn ($code): bool => ! is_string($code) || trim($code) === '') !== []
                        || count(array_unique($newProgramCodes)) !== count($newProgramCodes)
                    )) {
                        throw ValidationException::withMessages([
                            'dispositions' => 'Rotated program assignments must contain unique, non-empty program codes.',
                        ]);
                    }

                    $validPrograms = ! $assignmentRequested || $newProgramCodes === []
                        ? collect()
                        : Program::query()
                            ->where('institution_id', $institutionId)
                            ->where('is_active', true)
                            ->whereHas('department', fn ($query) => $query
                                ->where('institution_id', $institutionId)
                                ->where('is_active', true))
                            ->whereIn('code', $newProgramCodes)
                            ->lockForUpdate()
                            ->get(['code', 'department_id']);
                    if ($assignmentRequested && $validPrograms->count() !== count($newProgramCodes)) {
                        throw ValidationException::withMessages([
                            'dispositions' => 'One or more rotated program codes are not active in this institution.',
                        ]);
                    }

                    if ($validPrograms->isNotEmpty()) {
                        $programDepartmentIds = $validPrograms->pluck('department_id')
                            ->filter()
                            ->map(fn ($id): int => (int) $id)
                            ->unique()
                            ->sort()
                            ->values();
                        $lockedDepartments = Department::query()
                            ->where('institution_id', $institutionId)
                            ->where('is_active', true)
                            ->whereIn('id', $programDepartmentIds)
                            ->orderBy('id')
                            ->lockForUpdate()
                            ->pluck('id')
                            ->map(fn ($id): int => (int) $id);
                        if ($lockedDepartments->count() !== $programDepartmentIds->count()) {
                            throw ValidationException::withMessages([
                                'dispositions' => 'Rotated programs must belong to active departments in this institution.',
                            ]);
                        }
                    }
                    $targetDeanDepartmentIds = [];
                    if ($assignmentRequested && ($target->hasRole('dean') || Department::query()
                        ->where('institution_id', $institutionId)
                        ->where('dean_user_id', $target->id)
                        ->where('is_active', true)
                        ->exists())) {
                        $targetDeanDepartmentIds = Department::query()
                            ->where('institution_id', $institutionId)
                            ->where('dean_user_id', $target->id)
                            ->where('is_active', true)
                            ->orderBy('id')
                            ->lockForUpdate()
                            ->pluck('id')
                            ->map(fn ($id): int => (int) $id)
                            ->all();

                        $programDepartmentIds = $validPrograms->pluck('department_id')
                            ->filter()
                            ->map(fn ($id): int => (int) $id)
                            ->all();
                        if (array_diff($programDepartmentIds, $targetDeanDepartmentIds) !== []) {
                            throw ValidationException::withMessages([
                                'dispositions' => 'Dean program rotation must remain within the currently assigned department; designation changes must use the authorized Department assignment workflow.',
                            ]);
                        }
                    }
                    $departmentId = $profile->department_id === null ? null : (int) $profile->department_id;
                    if ($departmentIdProvided) {
                        $destinationDepartment = Department::query()
                            ->whereKey((int) $item['department_id'])
                            ->where('institution_id', $institutionId)
                            ->where('is_active', true)
                            ->lockForUpdate()
                            ->first();
                        if ($destinationDepartment === null) {
                            throw ValidationException::withMessages([
                                'dispositions' => 'The rotated department must be an active department in this institution.',
                            ]);
                        }

                        $ownedDepartmentIds = $this->targetScope->ownedActiveDepartmentIds($lockedActor, $institutionId);
                        if (! $isFullHr && ! in_array((int) $destinationDepartment->id, $ownedDepartmentIds, true)) {
                            throw new AuthorizationException('Rotated department assignment exceeds actor department scope.');
                        }

                        if (($target->hasRole('dean') || $targetDeanDepartmentIds !== [])
                            && ! in_array((int) $destinationDepartment->id, $targetDeanDepartmentIds, true)) {
                            throw ValidationException::withMessages([
                                'dispositions' => 'Dean designation changes must use the authorized Department assignment workflow.',
                            ]);
                        }

                        $departmentId = (int) $destinationDepartment->id;
                    }

                    if ($assignmentRequested && $departmentId !== null && $newProgramCodes !== []) {
                        $programsInDepartment = $validPrograms->where('department_id', $departmentId);
                        if ($programsInDepartment->count() !== count($newProgramCodes)) {
                            throw ValidationException::withMessages([
                                'dispositions' => 'Rotated program codes must belong to the destination department.',
                            ]);
                        }
                    }

                    if (! $isFullHr && $assignmentRequested) {
                        $ownedDepartmentIds = $this->targetScope->ownedActiveDepartmentIds($lockedActor, $institutionId);
                        $permittedCodes = Program::query()
                            ->where('institution_id', $institutionId)
                            ->where('is_active', true)
                            ->whereIn('department_id', $ownedDepartmentIds)
                            ->pluck('code')
                            ->all();
                        if (array_diff($newProgramCodes, $permittedCodes) !== []) {
                            throw new AuthorizationException('Rotated program assignments exceed actor department scope.');
                        }
                    }

                    $profile->update([
                        'active_cycle_code' => $destinationCycleCode,
                        'department_id' => $departmentId,
                        'program_codes' => $newProgramCodes,
                    ]);

                    $this->recordAuditEvent->handle('staff.cycle_disposition.rotated', [
                        'user_id' => $target->id,
                        'destination_cycle' => $destinationCycleCode,
                        'program_codes' => $newProgramCodes,
                        'department_id' => $departmentId,
                    ], (string) $lockedActor->id);

                    $summary['rotated']++;
                    $summary['rows'][] = ['user_id' => $target->id, 'disposition' => 'rotate', 'status' => 'active'];
                } else {
                    $profile->update([
                        'active_cycle_code' => $destinationCycleCode,
                    ]);

                    $this->recordAuditEvent->handle('staff.cycle_disposition.retained', [
                        'user_id' => $target->id,
                        'destination_cycle' => $destinationCycleCode,
                    ], (string) $lockedActor->id);

                    $summary['retained']++;
                    $summary['rows'][] = ['user_id' => $target->id, 'disposition' => 'retain', 'status' => 'active'];
                }
            }

            return $summary;
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
