<?php

namespace App\Authorization;

use App\Models\Admission\RegistrarQuotaGovernanceGrant;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Program;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;

final class GovernanceAuthorizer
{
    public function __construct(private CapabilityAuthorizer $capabilities) {}

    public function allows(User $actor, string $capability, Program $program, AcademicCycle $cycle, bool $write = false): bool
    {
        if (! in_array($capability, $this->governanceCapabilities(), true)) {
            return false;
        }

        $principal = User::query()->find($actor->getKey());

        if ($principal === null) {
            return false;
        }

        $freshProgram = Program::query()->find($program->getKey());
        $freshCycle = AcademicCycle::query()->find($cycle->getKey());

        if (
            $freshProgram === null
            || ! $freshProgram->is_active
            || $freshCycle === null
            || (int) $freshProgram->institution_id !== (int) $freshCycle->institution_id
        ) {
            return false;
        }

        $scope = $principal->actorScope();

        if (
            $capability === CapabilityCatalog::ADMISSION_BENCHMARK_PUBLISH
            && ! $this->isCurrentGuidanceTaPublisher($principal, $scope->office)
        ) {
            return false;
        }

        if (
            $scope->institutionId === null
            || (int) $scope->institutionId !== (int) $freshProgram->institution_id
        ) {
            return false;
        }

        if ($scope->office === 'registrar') {
            if (! in_array($capability, $this->registrarQuotaCapabilities(), true)) {
                return false;
            }

            return $principal->hasDirectPermission($capability)
                && $this->capabilities->decideFresh($principal, $capability)->allowed
                && $this->hasActiveRegistrarQuotaGrant($principal, $freshProgram, $freshCycle);
        }

        if ($scope->office !== 'guidance' && ! in_array($freshProgram->code, $scope->programCodes, true)) {
            return false;
        }

        if ($write && $this->isConfigurationCapability($capability)) {
            $isPlanned = $freshCycle->opens_on !== null
                && $freshCycle->opens_on->gt(Carbon::today());
            if (! $freshCycle->is_active && ! $isPlanned) {
                return false;
            }
        } elseif (
            $write
            && $freshCycle->closes_on !== null
            && $freshCycle->closes_on->lt(Carbon::today())
        ) {
            return false;
        }

        return $this->capabilities->decideFresh($principal, $capability)->allowed;
    }

    public function authorize(User $actor, string $capability, Program $program, AcademicCycle $cycle, bool $write = false): void
    {
        if (! $this->allows($actor, $capability, $program, $cycle, $write)) {
            throw new AuthorizationException('This governance operation is not authorized for the current program and academic cycle.');
        }
    }

    public function canPublishInInstitution(User $actor, int $institutionId): bool
    {
        $principal = User::query()->find($actor->getKey());
        if ($principal === null) {
            return false;
        }
        $scope = $principal->actorScope();

        return $scope->institutionId === $institutionId
            && $this->isCurrentGuidanceTaPublisher($principal, $scope->office);
    }

    /**
     * @return list<string>
     */
    private function registrarQuotaCapabilities(): array
    {
        return [
            CapabilityCatalog::PROGRAM_QUOTA_READ,
            CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_QUOTA_READ,
            CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_QUOTA_SEND,
        ];
    }

    private function hasActiveRegistrarQuotaGrant(User $recipient, Program $program, AcademicCycle $cycle): bool
    {
        $latest = RegistrarQuotaGovernanceGrant::query()
            ->where('recipient_user_id', $recipient->getKey())
            ->where('institution_id', $program->institution_id)
            ->where('program_id', $program->getKey())
            ->where('academic_cycle_id', $cycle->getKey())
            ->latestVersion()
            ->first();

        return $latest?->status === RegistrarQuotaGovernanceGrant::STATUS_ACTIVE;
    }

    /**
     * @return list<string>
     */
    private function governanceCapabilities(): array
    {
        return [
            CapabilityCatalog::PROGRAM_QUOTA_MANAGE,
            CapabilityCatalog::PROGRAM_QUOTA_READ,
            CapabilityCatalog::ADMISSION_BENCHMARK_GOVERNANCE_VIEW,
            CapabilityCatalog::ADMISSION_BENCHMARK_DRAFT_EDIT,
            CapabilityCatalog::ADMISSION_BENCHMARK_PREVIEW,
            CapabilityCatalog::ADMISSION_BENCHMARK_PUBLISH,
            CapabilityCatalog::ADMISSION_BENCHMARK_ACTIVATE,
            CapabilityCatalog::ADMISSION_ADVISING_NOTE_VIEW,
            CapabilityCatalog::ADMISSION_ADVISING_NOTE_DRAFT_EDIT,
            CapabilityCatalog::ADMISSION_ADVISING_NOTE_PUBLISH,
            CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_BENCHMARK_READ,
            CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_BENCHMARK_SEND,
            CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_BENCHMARK_MANAGE_STATE,
            CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_QUOTA_READ,
            CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_QUOTA_SEND,
            CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_QUOTA_MANAGE_STATE,
            CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_ADVISING_READ,
            CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_ADVISING_SEND,
            CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_ADVISING_MANAGE_STATE,
        ];
    }

    private function isCurrentGuidanceTaPublisher(User $principal, ?string $office): bool
    {
        if ($office !== 'guidance') {
            return false;
        }

        foreach (CapabilityCatalog::legacyTestAdministratorCapabilities() as $capability) {
            if (! $this->capabilities->decideFresh($principal, $capability)->allowed) {
                return false;
            }
        }

        return true;
    }

    private function isConfigurationCapability(string $capability): bool
    {
        return in_array($capability, [
            CapabilityCatalog::ADMISSION_BENCHMARK_DRAFT_EDIT,
            CapabilityCatalog::ADMISSION_BENCHMARK_PUBLISH,
            CapabilityCatalog::ADMISSION_BENCHMARK_ACTIVATE,
            CapabilityCatalog::ADMISSION_ADVISING_NOTE_DRAFT_EDIT,
            CapabilityCatalog::ADMISSION_ADVISING_NOTE_PUBLISH,
        ], true);
    }
}
