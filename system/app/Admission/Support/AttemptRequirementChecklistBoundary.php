<?php

namespace App\Admission\Support;

use App\Admission\Exceptions\AttemptClearanceConfirmedException;
use App\Authorization\CapabilityAuthorizer;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\AdmissionAttemptDocumentVerification;
use App\Models\Admission\AdmissionAttemptRequirementItem;
use App\Models\Admission\AdmissionAttemptRequirementSet;
use App\Models\Foundation\IntakeRequirement;
use App\Models\User;
use App\Testing\PracticeFixtureSet;
use App\Testing\PracticeWorkspace;
use Illuminate\Auth\Access\AuthorizationException;
use RuntimeException;

final class AttemptRequirementChecklistBoundary
{
    public function __construct(private readonly CapabilityAuthorizer $authorizer) {}

    public function allows(User $actor, AdmissionAttempt $attempt, string $capability, bool $requireCurrentCycle = true): bool
    {
        $freshAttempt = AdmissionAttempt::query()->with(['academicCycle', 'identity'])->find($attempt->getKey());
        $freshActor = User::query()->with('staffProfile')->find($actor->getKey());

        if ($freshAttempt === null || $freshActor === null) {
            return false;
        }

        $profile = $freshActor->staffProfile;
        $cycle = $freshAttempt->academicCycle;

        if ($profile === null
            || ! $profile->isActive()
            || $profile->deactivated_at !== null
            || $profile->office !== 'registrar'
            || (int) $profile->institution_id !== (int) $freshAttempt->institution_id
            || $cycle === null
            || (int) $cycle->institution_id !== (int) $freshAttempt->institution_id
            || ! $this->practiceScopeAllows($freshActor, $freshAttempt)) {
            return false;
        }

        $scope = [
            'office' => 'registrar',
            'institution_id' => (int) $freshAttempt->institution_id,
        ];

        if ($requireCurrentCycle) {
            $scope['cycle'] = (string) $cycle->code;
        }

        return $this->authorizer->decideFresh($freshActor, $capability, $scope)->allowed;
    }

    public function authorizeFresh(
        User $actor,
        AdmissionAttempt $attempt,
        string $capability,
        bool $requireCurrentCycle = true,
    ): User {
        $freshActor = User::query()->with('staffProfile')->find($actor->getKey());
        $freshAttempt = AdmissionAttempt::query()->with(['academicCycle', 'identity'])->find($attempt->getKey());

        if ($freshActor === null || $freshAttempt === null
            || ! $this->allows($freshActor, $freshAttempt, $capability, $requireCurrentCycle)) {
            throw new AuthorizationException('The actor is not authorized for this admission document operation.');
        }

        return $freshActor;
    }

    public function lockAttempt(int $attemptId): AdmissionAttempt
    {
        return AdmissionAttempt::query()->whereKey($attemptId)->lockForUpdate()->firstOrFail();
    }

    public function assertMutable(AdmissionAttempt $attempt): void
    {
        if (AttemptProgramFacts::isClearanceConfirmed($attempt)) {
            throw new AttemptClearanceConfirmedException('Admission clearance is confirmed. Reopen it with a reason before changing document evidence.');
        }

        if (AttemptProgramFacts::activeStamp($attempt) !== null) {
            throw new RuntimeException('This admission has effective legacy enrollment history and is read-only pending reviewed disposition.');
        }

        if ($attempt->practice_fixture_set_id !== null
            && ! PracticeFixtureSet::query()->whereKey($attempt->practice_fixture_set_id)->where('status', 'active')->exists()) {
            throw new RuntimeException('This historical practice admission is read-only.');
        }
    }

    public function currentTrack(AdmissionAttempt $attempt): ?string
    {
        $track = $attempt->getAttribute('applicant_track');
        if (! is_string($track) || trim($track) === '') {
            $track = $attempt->applicant_type;
        }

        return match (strtolower(trim((string) $track))) {
            'new', 'freshman' => IntakeRequirement::TRACK_FRESHMAN,
            IntakeRequirement::TRACK_TRANSFEREE => IntakeRequirement::TRACK_TRANSFEREE,
            IntakeRequirement::TRACK_ALS => IntakeRequirement::TRACK_ALS,
            default => null,
        };
    }

    public function ownedItem(AdmissionAttempt $attempt, int $setId, int $itemId): AdmissionAttemptRequirementItem
    {
        $set = AdmissionAttemptRequirementSet::query()
            ->whereKey($setId)
            ->where('admission_attempt_id', $attempt->getKey())
            ->firstOrFail();

        return AdmissionAttemptRequirementItem::query()
            ->whereKey($itemId)
            ->where('requirement_set_id', $set->getKey())
            ->firstOrFail();
    }

    public function currentSet(AdmissionAttempt $attempt, int $setId): AdmissionAttemptRequirementSet
    {
        $track = $this->currentTrack($attempt);
        $set = AdmissionAttemptRequirementSet::query()
            ->whereKey($setId)
            ->where('admission_attempt_id', $attempt->getKey())
            ->where('is_current', true)
            ->firstOrFail();

        if ($track === null || $set->applicant_track !== $track) {
            throw new RuntimeException('The requirement set is stale for the applicant classification. Refresh and review the checklist.');
        }

        if (! $set->items()->exists()) {
            throw new RuntimeException('The current requirement set is empty and cannot be used for document verification.');
        }

        return $set;
    }

    public function latestVerification(AdmissionAttempt $attempt, int $itemId): ?AdmissionAttemptDocumentVerification
    {
        return AdmissionAttemptDocumentVerification::query()
            ->where('admission_attempt_id', $attempt->getKey())
            ->where('requirement_item_id', $itemId)
            ->whereHas('requirementItem.requirementSet', fn ($query) => $query->where('admission_attempt_id', $attempt->getKey()))
            ->latest('id')
            ->first();
    }

    public function normalizeText(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        $normalized = preg_replace('/\s+/u', ' ', $text);

        if ($normalized === false) {
            return null;
        }

        $normalized = trim($normalized);

        if ($normalized === '') {
            return null;
        }

        return $normalized;
    }

    private function practiceScopeAllows(User $actor, AdmissionAttempt $attempt): bool
    {
        $workspaceId = app(PracticeWorkspace::class)->currentId();
        $practiceSetId = $attempt->practice_fixture_set_id;
        $identity = $attempt->identity;

        if ($identity === null
            || (int) $identity->institution_id !== (int) $attempt->institution_id
            || (string) $identity->practice_fixture_set_id !== (string) $practiceSetId) {
            return false;
        }

        if ($practiceSetId === null) {
            return $workspaceId === null;
        }

        if ((string) $workspaceId !== (string) $practiceSetId) {
            return false;
        }

        $set = PracticeFixtureSet::query()->whereKey($practiceSetId)->where('status', 'active')->first();

        return $set !== null
            && (int) $set->institution_id === (int) $attempt->institution_id
            && (int) $set->academic_cycle_id === (int) $attempt->academic_cycle_id
            && (int) ($set->actor_bindings['registrar'] ?? 0) === (int) $actor->getKey();
    }
}
