<?php

declare(strict_types=1);

namespace App\Staffing\Queries;

use App\Auth\Support\StaffMfaPolicy;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Department;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Program;
use App\Models\StaffProfile;
use App\Models\User;
use App\Staffing\Support\StaffTargetManagementScope;

final class StaffAccountReadiness
{
    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly StaffTargetManagementScope $targetScope,
        private readonly StaffMfaPolicy $mfaPolicy,
    ) {}

    /** @return array{rows: list<array<string, mixed>>, summary: array<string, int>, cycle_summary: array<string, mixed>} */
    public function handle(?User $actor, ?Institution $institution = null, ?string $cycleCode = null): array
    {
        $actorProfile = $actor ? StaffProfile::query()->where('user_id', $actor->id)->first() : null;
        $institutionId = $actor ? (int) ($actorProfile?->institution_id ?? 0) : (int) ($institution?->id ?? 0);
        if ($actor && ($institutionId < 1 || ! $actorProfile?->isActive())) {
            return ['rows' => [], 'summary' => $this->emptySummary(), 'cycle_summary' => $this->emptyCycleSummary()];
        }
        $cycleCode ??= AcademicCycle::query()->where('institution_id', $institutionId)->where('is_active', true)->value('code');
        $isHr = $actor !== null && $this->authorizer->decideFresh($actor, CapabilityCatalog::HR_STAFF_MANAGE, ['institution_id' => $institutionId])->allowed;
        $ownedDepartments = $actor && ! $isHr ? $this->targetScope->ownedActiveDepartmentIds($actor, $institutionId) : [];
        $actorOffice = (string) ($actorProfile?->office ?? '');
        $canManageOffice = $actor && ! $isHr && $actorOffice !== ''
            && $this->authorizer->decideFresh($actor, CapabilityCatalog::OFFICE_SUPPORT_STAFF_MANAGE, ['institution_id' => $institutionId, 'office' => $actorOffice])->allowed;
        $departmentsQuery = Department::query()->where('institution_id', $institutionId);
        $programsQuery = Program::query()->where('institution_id', $institutionId)->orderBy('code');
        if ($actor && ! $isHr) {
            if ($ownedDepartments !== []) {
                $departmentsQuery->whereIn('id', $ownedDepartments);
                $programsQuery->whereIn('department_id', $ownedDepartments);
            } elseif ($canManageOffice) {
                $departmentsQuery->whereRaw('1 = 0');
                $programsQuery->whereRaw('1 = 0');
            }
        }
        $departments = $departmentsQuery->get(['id', 'code', 'name', 'is_active', 'dean_user_id']);
        $programs = $programsQuery->get(['id', 'department_id', 'code', 'name', 'is_active']);
        $profilesQuery = StaffProfile::query()->where('institution_id', $institutionId);
        if ($actor && ! $isHr && $ownedDepartments === [] && $canManageOffice) {
            $profilesQuery->where('office', $actorOffice);
        } elseif ($actor && ! $isHr && $ownedDepartments !== []) {
            $profilesQuery->where('office', 'program');
        }
        $profiles = $profilesQuery->with(['user.roles:id,name', 'user.staffProfile', 'user.authenticationState.attempts', 'user.authenticationState.vouchers'])->get();
        $activeProfiles = $profiles->filter(fn (StaffProfile $profile) => $profile->isActive() && $profile->user !== null);
        $rows = [];
        $cycleCounts = ['current' => 0, 'needs_rollover' => 0, 'missing' => 0, 'conflict' => 0];

        foreach ($programs as $program) {
            $department = $departments->firstWhere('id', $program->department_id);
            if (! $program->is_active || ! $department?->is_active) {
                $rows[] = $this->academicRow('program:'.$program->id, 'program_head', $program->name, $program->code, $program->department_id, null, 'inactive_scope', $isHr, $actor, 'inactive_scope');

                continue;
            }
            $heads = $activeProfiles->filter(fn (StaffProfile $profile) => $profile->office === 'program'
                && in_array('program_head', $profile->user->roles->pluck('name')->all(), true)
                && in_array($program->code, (array) ($profile->program_codes ?? []), true));
            $state = $heads->count() > 1 ? 'conflict' : ($heads->isNotEmpty() ? $this->academicState($heads->first()) : 'missing');
            $person = $heads->count() === 1 ? $heads->first() : null;

            $cycleCoverage = $heads->count() > 1 ? 'conflict' : ($person === null ? 'missing' : ($cycleCode === null ? 'no_active_cycle' : ($person->active_cycle_code === $cycleCode ? 'current' : 'needs_rollover')));
            if (isset($cycleCounts[$cycleCoverage])) {
                $cycleCounts[$cycleCoverage]++;
            }

            $rows[] = $this->academicRow('program:'.$program->id, 'program_head', $program->name, $program->code, $department->id, $person, $state, $isHr, $actor, $cycleCoverage);
        }

        foreach ($departments as $department) {
            if (! $department->is_active) {
                $rows[] = $this->academicRow('department:'.$department->id, 'dean', $department->name, $department->code, $department->id, null, 'inactive_scope', $isHr, $actor, 'inactive_scope');

                continue;
            }
            $dean = $department->dean_user_id ? $activeProfiles->first(fn (StaffProfile $profile) => (int) $profile->user_id === (int) $department->dean_user_id
                && (int) $profile->institution_id === $institutionId && $profile->office === 'program') : null;
            $state = $dean ? $this->academicState($dean) : 'missing';

            $cycleCoverage = $dean === null ? 'missing' : ($cycleCode === null ? 'no_active_cycle' : ($dean->active_cycle_code === $cycleCode ? 'current' : 'needs_rollover'));
            if (isset($cycleCounts[$cycleCoverage])) {
                $cycleCounts[$cycleCoverage]++;
            }

            $rows[] = $this->academicRow('department:'.$department->id, 'dean', $department->name, $department->code, $department->id, $dean, $state, $isHr, $actor, $cycleCoverage);
        }
        $centralProfiles = $profiles->filter(fn (StaffProfile $profile) => $profile->office !== 'program');
        foreach ($centralProfiles as $profile) {
            if ($actor && ! $isHr && ! $this->targetScope->allows($actor, $profile->user)) {
                continue;
            }
            $person = $profile->user;
            $employmentState = $profile->isActive() ? 'active' : 'inactive';
            $setupState = $this->centralSetupState($profile);
            $state = ! $profile->isActive() ? 'inactive_scope' : $setupState;
            $authState = $person?->authenticationState;
            $centralActions = $actor && $person && $this->targetScope->allows($actor, $person) ? ['reset', 'issue_voucher'] : [];
            if ($authState?->protected_from_reset) {
                $centralActions = array_values(array_diff($centralActions, ['reset']));
            }
            $rows[] = [
                'key' => 'staff:'.$profile->user_id,
                'role' => $person?->roles->pluck('name')->first() ?? '',
                'state' => $state,
                'label' => $person?->name ?? 'Staff account',
                'office' => $profile->office,
                'scope_code' => null,
                'program_codes' => [],
                'department_id' => null,
                'user_id' => $person?->id,
                'name' => $person?->name,
                'email' => $person?->email,
                'employment_state' => $employmentState,
                'setup_state' => $setupState,
                'voucher_state' => $this->voucherState($person),
                'expected_generation' => $person?->authenticationState ? ((string) $person->authenticationState->session_version.':'.(string) $person->authenticationState->two_factor_generation) : null,
                'permitted_actions' => $centralActions,
            ];
        }

        $counts = $this->emptySummary();
        foreach ($rows as $row) {
            if (array_key_exists($row['state'], $counts)) {
                $counts[$row['state']]++;
            }
        }
        $cycleStatus = $cycleCode === null
            ? 'no_active_cycle'
            : (($cycleCounts['missing'] > 0 || $cycleCounts['conflict'] > 0)
                ? 'incomplete'
                : ($cycleCounts['needs_rollover'] > 0 ? 'needs_rollover' : 'ready'));

        $cycleSummary = [
            'cycle_code' => $cycleCode,
            'current' => $cycleCounts['current'],
            'needs_rollover' => $cycleCounts['needs_rollover'],
            'missing' => $cycleCounts['missing'],
            'conflict' => $cycleCounts['conflict'],
            'awaiting_setup' => $counts['awaiting_setup'],
            'status' => $cycleStatus,
        ];
        $cycleSummary['summary_metric'] = $this->cycleSummaryText($cycleSummary);
        $cycleSummary['detail'] = $this->cycleSummaryText($cycleSummary, true);
        $canSeePersonnel = $isHr || $ownedDepartments !== [] || (bool) $canManageOffice;

        return [
            'rows' => $actor !== null && $canSeePersonnel ? $rows : [],
            'summary' => $counts,
            'cycle_summary' => $cycleSummary,
        ];
    }

    /** @return array<string, mixed> */
    public function emptyCycleSummary(?string $cycleCode = null): array
    {
        $summary = [
            'cycle_code' => $cycleCode,
            'current' => 0,
            'needs_rollover' => 0,
            'missing' => 0,
            'conflict' => 0,
            'awaiting_setup' => 0,
            'status' => 'no_active_cycle',
        ];
        $summary['summary_metric'] = $this->cycleSummaryText($summary);
        $summary['detail'] = $this->cycleSummaryText($summary, true);

        return $summary;
    }

    /** @param array<string, int|string|null> $summary */
    private function cycleSummaryText(array $summary, bool $sentence = false): string
    {
        $parts = [];
        if ($summary['status'] === 'no_active_cycle') {
            $parts[] = 'No active academic cycle';
        } elseif (is_string($summary['cycle_code'] ?? null)) {
            $parts[] = 'Cycle '.$summary['cycle_code'];
        }
        $hasDiagnostics = false;
        foreach ([
            'missing' => 'missing',
            'conflict' => 'conflicts',
            'needs_rollover' => 'assignments need rollover',
            'awaiting_setup' => 'awaiting setup',
        ] as $key => $label) {
            $count = (int) ($summary[$key] ?? 0);
            if ($count > 0 || $sentence) {
                $parts[] = $count.' '.$label;
            }
            $hasDiagnostics = $hasDiagnostics || $count > 0;
        }
        if (! $hasDiagnostics) {
            $parts[] = 'Coverage current';
        }

        return $sentence ? implode('; ', $parts).'.' : implode(' · ', $parts);
    }

    /** @return array<string, int> */
    private function emptySummary(): array
    {
        return array_fill_keys(['covered', 'ready', 'missing', 'awaiting_setup', 'setup_in_progress', 'conflict', 'inactive_scope', 'protected'], 0);
    }

    private function academicState(?StaffProfile $profile): string
    {
        if ($profile === null || ! $profile->isActive() || $profile->user === null) {
            return 'missing';
        }
        $state = $profile->user->authenticationState;
        if (! $state?->activation_completed_at || ! $state?->password_set_at) {
            return $state?->attempts?->contains(fn ($attempt) => $attempt->completed_at === null && $attempt->revoked_at === null && ! $attempt->isExpired()) ? 'setup_in_progress' : 'awaiting_setup';
        }

        return $this->mfaPolicy->isMfaRequired($profile->user)
            && (! $state->hasConfirmedTwoFactor() || ! is_string($state->two_factor_secret) || $state->two_factor_secret === '' || $state->two_factor_generation === null)
            ? 'setup_in_progress'
            : 'covered';
    }

    private function centralSetupState(StaffProfile $profile): string
    {
        if (! $profile->user) {
            return 'awaiting_setup';
        }
        $state = $profile->user->authenticationState;
        if ($state?->protected_from_reset) {
            return 'protected';
        }
        if (! $state?->activation_completed_at || ! $state?->password_set_at || $state?->must_change_password) {
            return $state?->attempts?->contains(fn ($attempt) => $attempt->completed_at === null && $attempt->revoked_at === null && ! $attempt->isExpired()) ? 'setup_in_progress' : 'awaiting_setup';
        }

        return $this->mfaPolicy->isMfaRequired($profile->user)
            && (! $state->hasConfirmedTwoFactor() || ! is_string($state->two_factor_secret) || $state->two_factor_secret === '' || $state->two_factor_generation === null)
            ? 'setup_in_progress'
            : 'ready';
    }

    private function voucherState(?User $user): string
    {
        $voucher = $user?->authenticationState?->vouchers?->sortByDesc('created_at')->first();
        if (! $voucher) {
            return 'none';
        }
        if ($voucher->isRedeemed()) {
            return 'redeemed';
        }
        if ($voucher->isRevoked()) {
            return 'revoked';
        }

        return $voucher->isExpired() ? 'expired' : 'active';
    }

    /** @return array<string, mixed> */
    private function academicRow(string $key, string $role, string $label, ?string $scopeCode, ?int $departmentId, ?StaffProfile $profile, string $state, bool $isHr, ?User $actor, string $cycleCoverage = 'current'): array
    {
        $person = $profile?->user;
        $authState = $person?->authenticationState;
        $canSeePerson = $actor !== null && $person !== null && ($isHr || $this->targetScope->allows($actor, $person));
        $actions = [];
        if ($state === 'missing' && $isHr) {
            $actions[] = 'prepare';
        }
        if ($actor && $person && $this->targetScope->allows($actor, $person)) {
            $actions[] = 'reset';
            $actions[] = 'issue_voucher';
        }
        if ($authState?->protected_from_reset) {
            $state = 'protected';
            $actions = array_values(array_diff($actions, ['reset']));
        }

        return [
            'key' => $key,
            'role' => $role,
            'state' => $state,
            'label' => $label,
            'office' => 'program',
            'scope_code' => $scopeCode,
            'program_codes' => $role === 'program_head' ? ($profile ? (array) ($profile->program_codes ?? []) : [$scopeCode]) : [],
            'department_id' => $departmentId,
            'user_id' => $canSeePerson ? $person?->id : null,
            'name' => $canSeePerson ? $person?->name : null,
            'email' => $canSeePerson ? $person?->email : null,
            'employment_state' => $profile?->isActive() ? 'active' : ($profile ? 'inactive' : 'unfilled'),
            'setup_state' => $profile ? $this->academicState($profile) : 'unassigned',
            'voucher_state' => $this->voucherState($person),
            'expected_generation' => $canSeePerson && $authState ? ((string) $authState->session_version.':'.(string) $authState->two_factor_generation) : null,
            'permitted_actions' => $actions,
            'cycle_coverage' => $cycleCoverage,
            'active_cycle_code' => $profile?->active_cycle_code,
        ];
    }
}
