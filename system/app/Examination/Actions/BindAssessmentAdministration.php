<?php

namespace App\Examination\Actions;

use App\Assessment\Support\IndividualResultMutationFence;
use App\Assessment\Support\KitCompositionContext;
use App\Assessment\Support\TestKitEligibility;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Assessment\AssessmentRawResultRevision;
use App\Models\Assessment\AssessmentScoringRun;
use App\Models\Assessment\TestInstrumentVersion;
use App\Models\Assessment\TestKitAcceptanceReceipt;
use App\Models\Assessment\TestKitVersion;
use App\Models\Examination\AssessmentAdministration;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Examination\ExamSessionMaterialSet;
use App\Models\Examination\ExamSessionMaterialSnapshot;
use App\Models\Examination\ExamSessionSeat;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class BindAssessmentAdministration
{
    public function __construct(
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly CapabilityAuthorizer $authorizer,
        private readonly TestKitEligibility $eligibility,
        private readonly KitCompositionContext $compositionContext,
        private readonly IndividualResultMutationFence $mutationFence,
    ) {}

    public function handle(
        ExamAssignment $assignment,
        TestKitVersion $kitVersion,
        User $actor,
        string $operation = 'manual',
        ?string $replacementReason = null,
        ?string $acquisitionChannel = null,
    ): AssessmentAdministration {
        $deferredAuditEvidence = null;

        return $this->bindExact($assignment, $kitVersion, $actor, $operation, $replacementReason, $acquisitionChannel, false, null, null, null, $deferredAuditEvidence);
    }

    /** @param array{event:string,properties:array<string,mixed>,actor:string}|null $deferredAuditEvidence */
    private function bindExact(
        ExamAssignment $assignment,
        TestKitVersion $kitVersion,
        User $actor,
        string $operation,
        ?string $replacementReason,
        ?string $acquisitionChannel,
        bool $deferAudit,
        ?ExamSessionMaterialSnapshot $materialSnapshot,
        ?ExamSessionSeat $seat,
        ?ExamSessionMaterialSet $materialSet,
        ?array &$deferredAuditEvidence,
    ): AssessmentAdministration {
        if ($deferAudit) {
            $deferredAuditEvidence = null;
        }
        if (! in_array($operation, ['manual', 'import', 'omr', 'external_final'], true)) {
            throw new RuntimeException('assessment_operation_not_supported');
        }
        if ($operation === 'external_final' && ! in_array($acquisitionChannel, ['manual', 'import'], true)) {
            throw new RuntimeException('assessment_external_final_acquisition_channel_required');
        }

        return DB::transaction(function () use (
            $assignment,
            $kitVersion,
            $actor,
            $operation,
            $acquisitionChannel,
            $replacementReason,
            $deferAudit,
            $materialSnapshot,
            $seat,
            $materialSet,
            &$deferredAuditEvidence,
        ): AssessmentAdministration {
            $candidate = ExamAssignment::query()->findOrFail($assignment->id);
            $session = ExamSession::query()->lockForUpdate()->findOrFail($candidate->exam_session_id);
            $lockedAssignment = ExamAssignment::query()->lockForUpdate()->findOrFail($candidate->id);
            $lockedAssignment->load(['admissionAttempt', 'examSession.academicCycle']);
            $attempt = $lockedAssignment->admissionAttempt;
            $cycle = $lockedAssignment->examSession?->academicCycle;
            if (! $lockedAssignment->isActive() || $attempt === null || $cycle === null
                || $attempt->status !== AdmissionAttempt::STATUS_ACCEPTED
                || (int) $attempt->id !== (int) $lockedAssignment->admission_attempt_id
                || (int) $session->id !== (int) $lockedAssignment->exam_session_id
                || (int) $attempt->institution_id !== (int) $cycle->institution_id
                || (int) $attempt->academic_cycle_id !== (int) $cycle->id) {
                throw ValidationException::withMessages([
                    'assignment' => 'The selected sitting is not an active assignment in the current institution and academic cycle.',
                ]);
            }

            $scope = [
                'institution_id' => (int) $cycle->institution_id,
                'academic_cycle_id' => (int) $cycle->id,
                'office' => 'guidance',
            ];
            $capability = match ($operation) {
                'import' => CapabilityCatalog::GUIDANCE_SCORING_IMPORT,
                'omr' => CapabilityCatalog::GUIDANCE_SCORING_OMR,
                'external_final' => $acquisitionChannel === 'import'
                    ? CapabilityCatalog::GUIDANCE_SCORING_IMPORT
                    : CapabilityCatalog::GUIDANCE_SCORING_MANUAL,
                default => CapabilityCatalog::GUIDANCE_SCORING_MANUAL,
            };
            $hasScoringCapability = $this->authorizer->decideFresh($actor, $capability, $scope)->allowed;
            if (! $hasScoringCapability
                && ! $this->authorizer->decideFresh($actor, CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE, $scope)->allowed
                && ! $this->authorizer->decideFresh($actor, CapabilityCatalog::REGISTRAR_APPLICATIONS_ACCEPT, [
                    'institution_id' => (int) $cycle->institution_id,
                    'academic_cycle_id' => (int) $cycle->id,
                    'office' => 'registrar',
                ])->allowed) {
                throw new AuthorizationException('unauthorized_assessment_administration_binding');
            }
            /** @var AssessmentAdministration|null $current */
            $current = AssessmentAdministration::query()
                ->where('exam_assignment_id', $lockedAssignment->id)
                ->whereNull('superseded_at')
                ->lockForUpdate()
                ->first();
            if ($current !== null) {
                $this->mutationFence->assertMutable($current);
            }

            /** @var TestKitVersion $lockedKit */
            $lockedKit = TestKitVersion::query()->lockForUpdate()->findOrFail($kitVersion->id);
            $lockedKit->load([
                'testKit',
                'testInstrumentVersion.instrument',
                'testInstrumentVersion.metrics.inputs.inputMetric',
                'selectedAcceptanceReceipt',
            ]);
            if ($materialSnapshot !== null) {
                if ($seat === null) {
                    throw ValidationException::withMessages([
                        'seat' => 'A material snapshot binding requires its exact applicant seat.',
                    ]);
                }
                $materialSnapshot = ExamSessionMaterialSnapshot::query()
                    ->lockForUpdate()
                    ->findOrFail($materialSnapshot->id);
                $seat = ExamSessionSeat::query()
                    ->lockForUpdate()
                    ->findOrFail($seat->id);
                if ((int) $materialSnapshot->exam_session_id !== (int) $lockedAssignment->exam_session_id
                    || (int) $materialSnapshot->test_kit_version_id !== (int) $lockedKit->id
                    || (int) $materialSnapshot->test_instrument_version_id !== (int) $lockedKit->test_instrument_version_id
                    || ! hash_equals((string) $materialSnapshot->manifest_sha256, (string) $lockedKit->manifest_sha256)
                    || (int) $seat->material_snapshot_id !== (int) $materialSnapshot->id) {
                    throw ValidationException::withMessages([
                        'material_snapshot' => 'The exact snapshot and seat must belong to the selected Kit and sitting.',
                    ]);
                }
                if ($materialSet !== null) {
                    $materialSet = ExamSessionMaterialSet::query()
                        ->lockForUpdate()
                        ->findOrFail($materialSet->id);
                    if ((int) $materialSet->snapshot_id !== (int) $materialSnapshot->id
                        || (int) $materialSet->booklet_set_id !== (int) $seat->booklet_set_id) {
                        throw ValidationException::withMessages([
                            'material_set' => 'The selected material Set must match the exact snapshot and applicant seat.',
                        ]);
                    }
                }
                if ($materialSnapshot->acceptance_receipt_id === null) {
                    throw ValidationException::withMessages([
                        'acceptance_receipt_id' => 'An OMR material snapshot must preserve its original acceptance receipt.',
                    ]);
                }
                $originalReceipt = TestKitAcceptanceReceipt::query()
                    ->where('test_kit_version_id', $lockedKit->id)
                    ->whereKey($materialSnapshot->acceptance_receipt_id)
                    ->lockForUpdate()
                    ->first();
                if ($originalReceipt === null
                    || $originalReceipt->result !== TestKitAcceptanceReceipt::RESULT_PASSED
                    || ! hash_equals((string) $originalReceipt->manifest_sha256, (string) $lockedKit->manifest_sha256)) {
                    throw ValidationException::withMessages([
                        'acceptance_receipt_id' => 'The snapshot receipt must be the original passing receipt for this exact Kit manifest.',
                    ]);
                }
            } elseif ($seat !== null || $materialSet !== null) {
                throw ValidationException::withMessages([
                    'material_snapshot' => 'A material seat or Set cannot be bound without its exact snapshot.',
                ]);
            }
            $profile = $lockedKit->testInstrumentVersion;
            if (! $profile instanceof TestInstrumentVersion
                || $profile->status !== TestInstrumentVersion::STATUS_APPROVED
                || (int) $lockedKit->institution_id !== (int) $cycle->institution_id
                || (int) $lockedKit->testKit?->institution_id !== (int) $cycle->institution_id
                || (int) $lockedKit->test_instrument_version_id !== (int) $profile->id) {
                throw ValidationException::withMessages([
                    'test_kit_version_id' => 'The exact selected Kit, approved Setup, sitting, and institution must match.',
                ]);
            }

            try {
                $resolved = $this->compositionContext->resolveVersion($lockedKit);
            } catch (\Throwable $exception) {
                throw ValidationException::withMessages([
                    'test_kit_version_id' => 'The exact selected Kit configuration is stale or incoherent: '.$exception->getMessage(),
                ]);
            }
            $kitIsPaperBacked = (bool) ($resolved['applicable_resources']['physical_procedure'] ?? false)
                || (bool) ($resolved['applicable_resources']['answer_sheet'] ?? false)
                || (bool) ($resolved['applicable_resources']['booklet_form'] ?? false);
            $materialRequirement = $kitIsPaperBacked
                ? TestInstrumentVersion::MATERIAL_REQUIREMENT_PAPER_BACKED
                : TestInstrumentVersion::MATERIAL_REQUIREMENT_NO_PAPER;
            if ($profile->material_requirement !== $materialRequirement) {
                throw ValidationException::withMessages([
                    'test_kit_version_id' => 'The exact approved Setup requirement does not match the selected Kit applicability.',
                ]);
            }
            $sameBinding = $current !== null
                && (int) $current->test_kit_version_id === (int) $lockedKit->id
                && (int) $current->test_instrument_version_id === (int) $profile->id
                && hash_equals((string) $current->manifest_sha256, (string) $lockedKit->manifest_sha256);
            if ($operation === 'omr'
                && $materialSnapshot === null
                && (! $sameBinding || $current->material_snapshot_id === null || $current->exam_session_seat_id === null)) {
                throw ValidationException::withMessages([
                    'material_snapshot' => 'New OMR use requires the exact published snapshot and applicant seat.',
                ]);
            }
            $frozenReceipt = null;
            if ($sameBinding) {
                $current->loadMissing('acceptanceReceipt');
                $frozenReceipt = $current->acceptanceReceipt;
            }
            $receipt = $lockedKit->selectedAcceptanceReceipt;
            $eligibility = $this->eligibility->evaluate(
                $lockedKit,
                $cycle,
                $sameBinding ? TestKitEligibility::PURPOSE_PINNED_USE : TestKitEligibility::PURPOSE_NEW_USE,
                $frozenReceipt,
                $operation === 'omr' ? $receipt : null,
                $operation,
            );
            if (! $eligibility['eligible']) {
                throw ValidationException::withMessages([
                    'test_kit_version_id' => 'The selected Kit is not eligible for this operation: '.implode(', ', array_column($eligibility['blockers'], 'code')),
                ]);
            }
            if ($sameBinding) {
                if ($materialSnapshot !== null) {
                    if (($current->material_snapshot_id !== null
                            && (int) $current->material_snapshot_id !== (int) $materialSnapshot->id)
                        || ($current->exam_session_seat_id !== null
                            && (int) $current->exam_session_seat_id !== (int) $seat->id)
                        || ($current->material_set_id !== null
                            && (int) $current->material_set_id !== (int) ($materialSet?->id ?? 0))
                        || ($current->booklet_set_id !== null
                            && (int) $current->booklet_set_id !== (int) $seat->booklet_set_id)
                        || ($current->acceptance_receipt_id !== null
                            && (int) $current->acceptance_receipt_id !== (int) $materialSnapshot->acceptance_receipt_id)) {
                        throw ValidationException::withMessages([
                            'material_snapshot' => 'The current exact Kit binding already has different frozen material pins.',
                        ]);
                    }
                    $needsMaterialContext = $current->material_snapshot_id === null
                        || $current->exam_session_seat_id === null
                        || ($materialSet !== null && $current->material_set_id === null)
                        || ($seat->booklet_set_id !== null && $current->booklet_set_id === null);
                    if ($needsMaterialContext) {
                        $current->forceFill([
                            'material_snapshot_id' => (int) $materialSnapshot->id,
                            'exam_session_seat_id' => (int) $seat->id,
                            'material_set_id' => $materialSet?->id,
                            'booklet_set_id' => $seat->booklet_set_id,
                        ])->save();
                        $deferredAuditEvidence = [
                            'event' => 'assessment.administration_material_context_bound',
                            'properties' => [
                                'assessment_administration_id' => $current->id,
                                'exam_assignment_id' => $lockedAssignment->id,
                                'material_snapshot_id' => (int) $materialSnapshot->id,
                                'exam_session_seat_id' => (int) $seat->id,
                                'material_set_id' => $materialSet?->id,
                                'booklet_set_id' => $seat->booklet_set_id,
                                'test_kit_version_id' => $lockedKit->id,
                                'manifest_sha256' => $lockedKit->manifest_sha256,
                            ],
                            'actor' => $actor->name ?? (string) $actor->id,
                        ];
                        if (! $deferAudit) {
                            $this->recordAuditEvent->handle(
                                $deferredAuditEvidence['event'],
                                $deferredAuditEvidence['properties'],
                                $deferredAuditEvidence['actor'],
                            );
                        }
                    }
                }

                return $current;
            }

            if ($current !== null) {
                if (trim((string) $replacementReason) === '') {
                    throw ValidationException::withMessages([
                        'replacement_reason' => 'A reason is required to replace the current assessment binding.',
                    ]);
                }
                if ($this->hasAcceptedUse($current)) {
                    throw ValidationException::withMessages([
                        'test_kit_version_id' => 'An assessment binding with accepted use cannot be replaced.',
                    ]);
                }
            }

            $metrics = $profile->metrics->sortBy([['code', 'asc'], ['id', 'asc']]);
            $requiredMetrics = $metrics->where('is_required', true)->values()->map(static fn ($metric): array => [
                'code' => (string) $metric->code,
                'name' => (string) $metric->name,
                'rule_kind' => (string) $metric->rule_kind,
                'unit' => $metric->unit,
                'min_raw_value' => $metric->min_raw_value,
                'max_raw_value' => $metric->max_raw_value,
                'precision_scale' => $metric->precision_scale,
                'required_output_scales' => $metric->required_output_scales,
                'input_contract_json' => $metric->input_contract_json,
            ])->all();
            if ($requiredMetrics === []) {
                throw ValidationException::withMessages([
                    'test_kit_version_id' => 'The exact approved Setup has no required metrics for this sitting.',
                ]);
            }

            $revision = (int) AssessmentAdministration::query()
                ->where('exam_assignment_id', $lockedAssignment->id)
                ->max('binding_revision') + 1;
            $now = Carbon::now();
            if ($current !== null) {
                $current->update(['superseded_at' => $now]);
            }
            $administration = AssessmentAdministration::query()->create([
                'exam_assignment_id' => $lockedAssignment->id,
                'admission_attempt_id' => $attempt->id,
                'binding_kind' => $kitIsPaperBacked
                    ? AssessmentAdministration::KIND_SCHEDULED_MATERIAL
                    : AssessmentAdministration::KIND_DIRECT_MANUAL,
                'test_instrument_version_id' => (int) $profile->id,
                'binding_revision' => $revision,
                'test_kit_version_id' => (int) $lockedKit->id,
                'manifest_sha256' => (string) $lockedKit->manifest_sha256,
                'acceptance_receipt_id' => $materialSnapshot?->acceptance_receipt_id ?? $receipt?->getKey(),
                'procedure_snapshot_json' => [
                    'material_requirement' => $profile->material_requirement,
                    'administration_procedure_reference' => $profile->administration_procedure_reference,
                    'content_sha256' => $profile->content_sha256,
                    'manifest_schema_version' => (int) $lockedKit->manifest_schema_version,
                    'kit_manifest_sha256' => (string) $lockedKit->manifest_sha256,
                    'operation' => $operation,
                ],
                'material_snapshot_id' => $materialSnapshot?->id,
                'exam_session_seat_id' => $seat?->id,
                'material_set_id' => $materialSet?->id,
                'booklet_set_id' => $seat?->booklet_set_id ?? $lockedKit->booklet_set_id,
                'required_metric_set' => $requiredMetrics,
                'binding_state' => $kitIsPaperBacked
                    ? AssessmentAdministration::STATE_AWAITING_ITEM_MAP
                    : AssessmentAdministration::STATE_READY,
                'bound_by_user_id' => $actor->id,
                'bound_at' => $now,
            ]);
            $auditEvidence = [
                'event' => 'assessment.administration_bound',
                'properties' => [
                    'assessment_administration_id' => $administration->id,
                    'exam_assignment_id' => $lockedAssignment->id,
                    'admission_attempt_id' => $attempt->id,
                    'binding_revision' => $revision,
                    'test_kit_version_id' => $lockedKit->id,
                    'test_instrument_version_id' => $profile->id,
                    'manifest_sha256' => $lockedKit->manifest_sha256,
                    'operation' => $operation,
                    'replacement_reason' => $current === null ? null : trim((string) $replacementReason),
                    'material_snapshot_id' => $materialSnapshot?->id,
                    'exam_session_seat_id' => $seat?->id,
                    'material_set_id' => $materialSet?->id,
                    'booklet_set_id' => $seat?->booklet_set_id ?? $lockedKit->booklet_set_id,
                    'acceptance_receipt_id' => $materialSnapshot?->acceptance_receipt_id ?? $receipt?->getKey(),
                ],
                'actor' => $actor->name ?? (string) $actor->id,
            ];
            if ($deferAudit) {
                $deferredAuditEvidence = $auditEvidence;
            } else {
                $this->recordAuditEvent->handle(
                    $auditEvidence['event'],
                    $auditEvidence['properties'],
                    $auditEvidence['actor'],
                );
            }

            return $administration;
        });
    }

    /**
     * Retained snapshot ingress adapter. The snapshot itself supplies the exact Kit identity.
     */
    public function bindScheduledAssignment(
        ExamAssignment $assignment,
        ExamSessionMaterialSnapshot $snapshot,
        ExamSessionSeat $seat,
        ?ExamSessionMaterialSet $materialSet,
        User $actor,
        bool $deferAudit = false,
        ?array &$deferredAuditEvidence = null,
    ): ?AssessmentAdministration {
        $deferredAuditEvidence = null;
        if ($snapshot->test_kit_version_id === null
            || (int) $snapshot->exam_session_id !== (int) $assignment->exam_session_id
            || (int) $seat->material_snapshot_id !== (int) $snapshot->id) {
            throw new RuntimeException('assessment_snapshot_adapter_requires_exact_kit_session_and_seat');
        }
        $kit = TestKitVersion::query()->findOrFail($snapshot->test_kit_version_id);
        if ((int) $snapshot->test_instrument_version_id !== (int) $kit->test_instrument_version_id
            || ! hash_equals((string) $snapshot->manifest_sha256, (string) $kit->manifest_sha256)
            || ($materialSet !== null
                && ((int) $materialSet->snapshot_id !== (int) $snapshot->id
                    || (int) $materialSet->booklet_set_id !== (int) $seat->booklet_set_id))) {
            throw new RuntimeException('assessment_snapshot_adapter_pins_mismatch');
        }
        $administration = $this->bindExact(
            $assignment,
            $kit,
            $actor,
            'omr',
            null,
            null,
            true,
            $snapshot,
            $seat,
            $materialSet,
            $deferredAuditEvidence,
        );
        if ($deferAudit || $deferredAuditEvidence === null) {
            return $administration;
        }
        $this->recordAuditEvent->handle(
            $deferredAuditEvidence['event'],
            $deferredAuditEvidence['properties'],
            $deferredAuditEvidence['actor'],
        );

        return $administration;
    }

    public function supersedeSessionBindings(ExamSession $session, ?ExamSessionMaterialSnapshot $snapshot = null): void
    {
        AssessmentAdministration::query()
            ->whereIn('exam_assignment_id', $session->assignments()->select('id'))
            ->whereNull('superseded_at')
            ->update(['superseded_at' => Carbon::now(), 'updated_at' => Carbon::now()]);
    }

    private function hasAcceptedUse(AssessmentAdministration $administration): bool
    {
        return AssessmentScoringRun::query()->where('administration_id', $administration->id)->exists()
            || AssessmentRawResultRevision::query()->where('administration_id', $administration->id)->exists()
            || $administration->conversionRevisions()->exists()
            || $administration->material_snapshot_id !== null
            || $administration->exam_session_seat_id !== null
            || $administration->material_set_id !== null
            || $administration->binding_state === AssessmentAdministration::STATE_FINALIZED;
    }
}
