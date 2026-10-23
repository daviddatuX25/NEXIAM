<?php

namespace App\Admission\Queries;

use App\Admission\Support\AttemptProgramFacts;
use App\Admission\Support\AttemptRequirementChecklistBoundary;
use App\Admission\Support\DocumentCorrectionInstructionSuggestions;
use App\Authorization\CapabilityCatalog;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\AdmissionAttemptDocumentVerification;
use App\Models\Admission\AdmissionAttemptRequirementInternalNote;
use App\Models\Admission\AdmissionAttemptRequirementSet;
use App\Models\Foundation\IntakeRequirement;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class AttemptRequirementChecklistQuery
{
    public function __construct(private readonly AttemptRequirementChecklistBoundary $boundary) {}

    /**
     * Staff checklist projection. Without an actor it is an internal domain read
     * for clearance evaluation and contains no authorization flags.
     *
     * @return array<string, mixed>
     */
    public function get(AdmissionAttempt $attempt, ?User $staffActor = null): array
    {
        $attempt = AdmissionAttempt::query()
            ->with(['academicCycle', 'identity'])
            ->findOrFail($attempt->getKey());
        $set = AdmissionAttemptRequirementSet::query()
            ->where('admission_attempt_id', $attempt->getKey())
            ->where('is_current', true)
            ->with(['items' => fn ($query) => $query->orderBy('sort_order')->orderBy('id')])
            ->first();
        $track = $this->boundary->currentTrack($attempt);
        $hasAnySet = AdmissionAttemptRequirementSet::query()
            ->where('admission_attempt_id', $attempt->getKey())
            ->exists();
        $catalogRequirements = $set === null && ! $hasAnySet && $track !== null
            ? IntakeRequirement::query()
                ->where('institution_id', $attempt->institution_id)
                ->active()
                ->forTrack($track)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
            : collect();

        $catalogIsEmpty = $set === null
            && $track !== null
            && $catalogRequirements->isEmpty();

        $status = match (true) {
            $track === null => 'unknown_track',
            $set === null && $hasAnySet => 'stale',
            $set === null && $catalogIsEmpty => 'empty',
            $set === null && $catalogRequirements->isNotEmpty() => 'current',
            $set === null => 'uninitialized',
            $set->applicant_track !== $track => 'stale',
            $set->items->isEmpty() => 'empty',
            default => 'current',
        };

        $source = $set !== null ? 'snapshot' : ($catalogRequirements->isNotEmpty() ? 'catalog' : 'none');

        $mandatoryPendingCount = 0;
        $items = [];

        if ($source === 'catalog') {
            foreach ($catalogRequirements as $requirement) {
                $isMandatory = (bool) $requirement->is_mandatory;
                if ($isMandatory) {
                    $mandatoryPendingCount++;
                }

                $items[] = [
                    'id' => (int) $requirement->id,
                    'requirement_item_id' => null,
                    'intake_requirement_id' => (int) $requirement->id,
                    'code' => (string) $requirement->code,
                    'label' => (string) $requirement->label,
                    'item_kind' => (string) ($requirement->item_kind ?? 'document'),
                    'is_mandatory' => $isMandatory,
                    'sort_order' => (int) $requirement->sort_order,
                    'latest_status' => AdmissionAttemptDocumentVerification::STATUS_NOT_CHECKED,
                    'latest_verification_id' => null,
                    'latest_verified_at' => null,
                    'applicant_instruction' => null,
                    'internal_remarks' => null,
                    'latest_note_id' => null,
                    'internal_note' => null,
                    'internal_note_history' => [],
                    'history' => [],
                ];
            }
        }

        $requirementItems = $set?->items ?? collect();
        $verificationRows = $requirementItems->isEmpty()
            ? collect()
            : AdmissionAttemptDocumentVerification::query()
                ->where('admission_attempt_id', $attempt->getKey())
                ->whereIn('requirement_item_id', $requirementItems->modelKeys())
                ->whereHas('requirementItem.requirementSet', fn ($query) => $query->where('admission_attempt_id', $attempt->getKey()))
                ->with('verifiedBy:id,name')
                ->orderBy('id')
                ->get();
        $historyByItem = $verificationRows->groupBy('requirement_item_id');
        $notesByItem = $requirementItems->isEmpty()
            ? collect()
            : AdmissionAttemptRequirementInternalNote::query()
                ->where('admission_attempt_id', $attempt->getKey())
                ->whereIn('requirement_item_id', $requirementItems->modelKeys())
                ->with('recordedBy:id,name')
                ->orderBy('id')
                ->get()
                ->groupBy('requirement_item_id');

        if ($set !== null) {
            $mandatoryPendingCount = 0;
            $items = [];
        }

        foreach ($requirementItems as $item) {
            $history = $historyByItem->get($item->getKey(), collect());
            /** @var AdmissionAttemptDocumentVerification|null $latestVerification */
            $latestVerification = $history->last();
            $noteHistory = $notesByItem->get($item->getKey(), collect());
            /** @var AdmissionAttemptRequirementInternalNote|null $latestNote */
            $latestNote = $noteHistory->last();
            $effectiveInternalRemarks = $noteHistory->isNotEmpty()
                ? $latestNote->internal_remarks
                : $latestVerification?->internal_remarks;
            $statusForItem = $latestVerification?->status ?? AdmissionAttemptDocumentVerification::STATUS_NOT_CHECKED;
            $isMandatory = (bool) $item->is_mandatory;

            if ($isMandatory && $statusForItem !== AdmissionAttemptDocumentVerification::STATUS_VERIFIED) {
                $mandatoryPendingCount++;
            }

            $items[] = [
                'id' => (int) $item->id,
                'requirement_item_id' => (int) $item->id,
                'intake_requirement_id' => $item->intake_requirement_id === null ? null : (int) $item->intake_requirement_id,
                'code' => (string) $item->code,
                'label' => (string) $item->label,
                'item_kind' => (string) ($item->item_kind ?? 'document'),
                'is_mandatory' => $isMandatory,
                'sort_order' => (int) $item->sort_order,
                'latest_status' => (string) $statusForItem,
                'latest_verification_id' => $latestVerification === null ? null : (int) $latestVerification->id,
                'latest_verified_at' => $latestVerification?->verified_at?->toIso8601String(),
                'applicant_instruction' => $latestVerification?->applicant_instruction,
                'internal_remarks' => $effectiveInternalRemarks,
                'latest_note_id' => $latestNote === null ? null : (int) $latestNote->id,
                'internal_note' => $latestNote !== null
                    ? ($latestNote->internal_remarks === null ? null : [
                        'id' => (int) $latestNote->id,
                        'body' => $latestNote->internal_remarks,
                        'actor_name' => $latestNote->recordedBy?->name,
                        'recorded_at' => $latestNote->recorded_at?->toIso8601String(),
                    ])
                    : ($latestVerification?->internal_remarks !== null ? [
                        'id' => null,
                        'body' => $latestVerification->internal_remarks,
                        'actor_name' => $latestVerification->verifiedBy?->name,
                        'recorded_at' => $latestVerification->verified_at?->toIso8601String(),
                    ] : null),
                'internal_note_history' => $noteHistory->map(static fn (AdmissionAttemptRequirementInternalNote $n): array => [
                    'id' => (int) $n->id,
                    'internal_remarks' => $n->internal_remarks,
                    'recorded_by_user_id' => (int) $n->recorded_by_user_id,
                    'recorded_by_name' => $n->recordedBy?->name,
                    'recorded_at' => $n->recorded_at?->toIso8601String(),
                    'predecessor_id' => $n->predecessor_id === null ? null : (int) $n->predecessor_id,
                ])->all(),
                'history' => $history->map(static fn (AdmissionAttemptDocumentVerification $verification): array => [
                    'id' => (int) $verification->id,
                    'status' => (string) $verification->status,
                    'applicant_instruction' => $verification->applicant_instruction,
                    'internal_remarks' => $verification->internal_remarks,
                    'verified_by_user_id' => (int) $verification->verified_by_user_id,
                    'verified_by_name' => $verification->verifiedBy?->name,
                    'verified_at' => $verification->verified_at?->toIso8601String(),
                    'predecessor_id' => $verification->predecessor_id === null ? null : (int) $verification->predecessor_id,
                ])->all(),
            ];
        }

        $allMandatoryVerified = $set !== null
            && $status === 'current'
            && $items !== []
            && $mandatoryPendingCount === 0;
        $mutationBlockReason = $this->mutationBlockReason($attempt, $status);
        $canView = $staffActor !== null && (
            $this->boundary->allows($staffActor, $attempt, CapabilityCatalog::REGISTRAR_APPLICATIONS_VIEW, false)
            || $this->boundary->allows($staffActor, $attempt, CapabilityCatalog::REGISTRAR_ENROLLMENT_ELIGIBILITY, false)
        );
        $canMutate = $staffActor !== null
            && $this->boundary->allows($staffActor, $attempt, CapabilityCatalog::REGISTRAR_DOCUMENTS_VERIFY);

        if ($mutationBlockReason === null && ! $canMutate) {
            $mutationBlockReason = $canView ? 'You have view-only access to this checklist.' : 'Checklist access is not authorized for this staff account.';
        }

        return [
            'set' => $set === null ? null : [
                'id' => (int) $set->id,
                'admission_attempt_id' => (int) $set->admission_attempt_id,
                'applicant_track' => (string) $set->applicant_track,
                'catalog_version' => (int) $set->catalog_version,
                'is_current' => (bool) $set->is_current,
                'initialized_by_user_id' => $set->initialized_by_user_id === null ? null : (int) $set->initialized_by_user_id,
                'initialized_at' => $set->initialized_at?->toIso8601String(),
            ],
            'items' => $items,
            'source' => $source,
            'all_mandatory_verified' => $allMandatoryVerified,
            'mandatory_pending_count' => $mandatoryPendingCount,
            'status' => $status,
            'current_track' => $track,
            'mutation_block_reason' => $mutationBlockReason,
            'documents_view' => $canView,
            'documents_initialize' => $canMutate && $mutationBlockReason === null && $status === 'uninitialized',
            'documents_verify' => $canMutate && $mutationBlockReason === null && $status === 'current',
            'documents_reconcile' => false,
            'documents_request' => $canMutate && $mutationBlockReason === null && $status === 'current',
            'suggestions' => DocumentCorrectionInstructionSuggestions::all(),
        ];
    }

    /**
     * Explicit allowlist for an authenticated applicant's own checklist.
     *
     * @return array<string, mixed>
     */
    public function forApplicant(AdmissionAttempt $attempt, User $applicant): array
    {
        $attempt = AdmissionAttempt::query()->with(['identity', 'academicCycle'])->findOrFail($attempt->getKey());
        $account = $applicant->applicantAccount()->with('identity')->first();
        $identity = $account?->identity;

        if ($account === null || $identity === null
            || (int) $account->applicant_identity_id !== (int) $attempt->applicant_identity_id
            || (int) $identity->institution_id !== (int) $attempt->institution_id
            || (string) $identity->practice_fixture_set_id !== (string) $attempt->practice_fixture_set_id) {
            throw (new ModelNotFoundException)->setModel(AdmissionAttempt::class, [$attempt->getKey()]);
        }

        $projection = $this->get($attempt);
        $items = array_map(static fn (array $item): array => [
            'id' => $item['id'],
            'code' => $item['code'],
            'label' => $item['label'],
            'item_kind' => $item['item_kind'],
            'is_mandatory' => $item['is_mandatory'],
            'latest_status' => $item['latest_status'],
            'latest_verified_at' => $item['latest_verified_at'],
            'applicant_instruction' => $item['applicant_instruction'],
        ], $projection['items']);

        return [
            'set' => $projection['set'] === null ? null : [
                'applicant_track' => $projection['set']['applicant_track'],
                'catalog_version' => $projection['set']['catalog_version'],
            ],
            'items' => $items,
            'all_mandatory_verified' => $projection['all_mandatory_verified'],
            'mandatory_pending_count' => $projection['mandatory_pending_count'],
            'status' => $projection['status'],
        ];
    }

    /**
     * Alias for get.
     *
     * @return array<string, mixed>
     */
    public function handle(AdmissionAttempt $attempt, ?User $staffActor = null): array
    {
        return $this->get($attempt, $staffActor);
    }

    /**
     * @return array<string, mixed>
     */
    public static function forAttempt(AdmissionAttempt $attempt): array
    {
        return app(self::class)->get($attempt);
    }

    public static function allMandatoryVerified(AdmissionAttempt $attempt): bool
    {
        return app(self::class)->get($attempt)['all_mandatory_verified'];
    }

    private function mutationBlockReason(AdmissionAttempt $attempt, string $status): ?string
    {
        if (AttemptProgramFacts::isClearanceConfirmed($attempt)) {
            return 'Admission clearance is confirmed. Reopen it with a reason before changing document evidence.';
        }

        if (AttemptProgramFacts::activeStamp($attempt) !== null) {
            return 'This admission has effective legacy enrollment history and is read-only pending reviewed disposition.';
        }

        return match ($status) {
            'unknown_track' => 'The applicant classification is unknown; the checklist is read-only.',
            'stale' => 'The requirement set is stale or historical and requires reviewed reconciliation.',
            'empty' => 'No applicable requirements exist for the resolved classification; completeness cannot be certified.',
            default => null,
        };
    }
}
