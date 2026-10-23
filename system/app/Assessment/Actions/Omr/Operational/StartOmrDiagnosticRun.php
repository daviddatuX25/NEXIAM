<?php

declare(strict_types=1);

namespace App\Assessment\Actions\Omr\Operational;

use App\Admission\Support\CommandIdempotency;
use App\Assessment\Actions\Omr\StartOmrPendingCaptureSet;
use App\Assessment\Support\KitCompositionContext;
use App\Assessment\Support\TestKitAcceptancePlan;
use App\Assessment\Support\TestKitEligibility;
use App\Assessment\Support\TestKitMockSession;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrBookletSet;
use App\Models\Assessment\OmrDiagnosticRun;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\Assessment\TestKit;
use App\Models\Assessment\TestKitAcceptancePlanRecord;
use App\Models\Assessment\TestKitVersion;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

use function array_column;
use function in_array;
use function is_int;
use function strtoupper;
use function trim;

/**
 * Creates a setup-owned diagnostic run, optionally pinned to a candidate kit.
 */
final class StartOmrDiagnosticRun
{
    private readonly TestKitEligibility $eligibilityService;

    private readonly TestKitAcceptancePlan $acceptancePlans;

    private readonly KitCompositionContext $kitCompositions;

    private readonly TestKitMockSession $mockSessions;

    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly RecordAuditEvent $recordAuditEvent,
        ?TestKitEligibility $eligibility = null,
        ?TestKitAcceptancePlan $acceptancePlans = null,
        ?KitCompositionContext $kitCompositions = null,
        ?TestKitMockSession $mockSessions = null,
    ) {
        $this->eligibilityService = $eligibility ?? app(TestKitEligibility::class);
        $this->acceptancePlans = $acceptancePlans ?? new TestKitAcceptancePlan;
        $this->kitCompositions = $kitCompositions ?? app(KitCompositionContext::class);
        $this->mockSessions = $mockSessions ?? app(TestKitMockSession::class);
    }

    public function handle(
        OmrAnswerSheetTemplateVersion|int|null $templateVersion,
        OmrQuestionBookletVersion|int|null $bookletVersion,
        ?string $declaredSetCode,
        User $actor,
        ?int $institutionId = null,
        ?int $academicCycleId = null,
        TestKitVersion|int|null $testKitVersion = null,
        TestKitAcceptancePlanRecord|int|null $acceptancePlan = null,
        ?int $expectedRevision = null,
        ?string $idempotencyKey = null,
        ?string $diagnosticPurpose = null,
        ?int $instanceCount = null,
        ?string $patternMode = null,
        ?string $expectedMockManifestSha256 = null,
        ?string $resumeRunPublicId = null,
    ): OmrDiagnosticRun {
        $diagnosticPurpose ??= $testKitVersion !== null
            ? OmrDiagnosticRun::PURPOSE_KIT_ACCEPTANCE
            : OmrDiagnosticRun::PURPOSE_MATERIAL_DIAGNOSTIC;

        return DB::transaction(fn (): OmrDiagnosticRun => $this->startWithinTransaction(
            $templateVersion,
            $bookletVersion,
            $declaredSetCode,
            $actor,
            $institutionId,
            $academicCycleId,
            $testKitVersion,
            $acceptancePlan,
            $expectedRevision,
            $idempotencyKey,
            $diagnosticPurpose,
            $instanceCount,
            $patternMode,
            $expectedMockManifestSha256,
            $resumeRunPublicId,
        ));
    }

    private function startWithinTransaction(
        OmrAnswerSheetTemplateVersion|int|null $templateVersion,
        OmrQuestionBookletVersion|int|null $bookletVersion,
        ?string $declaredSetCode,
        User $actor,
        ?int $institutionId,
        ?int $academicCycleId,
        TestKitVersion|int|null $testKitVersion,
        TestKitAcceptancePlanRecord|int|null $acceptancePlan,
        ?int $expectedRevision,
        ?string $idempotencyKey,
        string $diagnosticPurpose,
        ?int $instanceCount,
        ?string $patternMode,
        ?string $expectedMockManifestSha256,
        ?string $resumeRunPublicId,
    ): OmrDiagnosticRun {
        $candidate = null;
        $planRecord = null;
        $planSnapshot = null;
        $kitComposition = null;
        $mockPrepared = null;
        $mockLaunchReceipt = null;
        if (! in_array($diagnosticPurpose, OmrDiagnosticRun::DIAGNOSTIC_PURPOSES, true)) {
            throw new RuntimeException('omr_diagnostic_run_purpose_invalid');
        }
        if ($diagnosticPurpose === OmrDiagnosticRun::PURPOSE_MOCK_REHEARSAL
            && ($instanceCount === null || ! in_array($instanceCount, [1, 2, 3], true)
                || ! in_array($patternMode, ['guided_mix', 'perfect_set_a', 'blank_baseline'], true)
                || $expectedMockManifestSha256 === null)) {
            throw new RuntimeException('omr_mock_config_invalid');
        }
        if ($testKitVersion !== null) {
            if (! in_array($diagnosticPurpose, [
                OmrDiagnosticRun::PURPOSE_KIT_ACCEPTANCE,
                OmrDiagnosticRun::PURPOSE_MOCK_REHEARSAL,
            ], true)) {
                throw new RuntimeException('omr_diagnostic_kit_purpose_invalid');
            }
            $requestedVersionId = $testKitVersion instanceof TestKitVersion
                ? (int) $testKitVersion->getKey()
                : $testKitVersion;
            $unlockedCandidate = TestKitVersion::query()->findOrFail($requestedVersionId);
            $kit = TestKit::query()->lockForUpdate()->findOrFail((int) $unlockedCandidate->test_kit_id);
            $candidate = TestKitVersion::query()
                ->with([
                    'testKit',
                    'templateVersion',
                    'bookletVersion',
                    'bookletSet',
                    'testInstrumentVersion',
                    'sourcePreparation',
                ])
                ->where('test_kit_id', (int) $kit->getKey())
                ->lockForUpdate()
                ->findOrFail($requestedVersionId);
            if (! $this->eligibilityService->allowsReacceptance($candidate)) {
                throw new RuntimeException('test_kit_rehearsal_version_unavailable');
            }
            if ($expectedRevision !== null && (int) $candidate->revision !== (int) $expectedRevision) {
                throw new RuntimeException('test_kit_version_revision_mismatch');
            }
            if ($institutionId !== null && $institutionId !== (int) $kit->institution_id) {
                throw new RuntimeException('omr_diagnostic_scope_mismatch');
            }
            $institutionId = (int) $kit->institution_id;
            $kitComposition = $this->kitCompositions->resolveVersion($candidate);
            if ((int) $kitComposition['template_version_id'] !== (int) $candidate->template_version_id
                || (int) $kitComposition['booklet_version_id'] !== (int) $candidate->booklet_version_id
                || (int) $kitComposition['booklet_set_id'] !== (int) $candidate->booklet_set_id
                || (int) $kitComposition['test_instrument_version_id'] !== (int) $candidate->test_instrument_version_id) {
                throw new RuntimeException('test_kit_rehearsal_composition_mismatch');
            }
            $templateVersion = $kitComposition['template'];
            $bookletVersion = $kitComposition['booklet'];
            $declaredSetCode = 'A';
            if ($diagnosticPurpose === OmrDiagnosticRun::PURPOSE_KIT_ACCEPTANCE) {
                $planId = $acceptancePlan instanceof TestKitAcceptancePlanRecord
                    ? (int) $acceptancePlan->getKey()
                    : (int) $acceptancePlan;
                $planSnapshot = $this->acceptancePlans->resolveForStart($actor, $candidate, $planId);
                $planRecord = TestKitAcceptancePlanRecord::query()
                    ->where('test_kit_version_id', (int) $candidate->getKey())
                    ->lockForUpdate()
                    ->findOrFail($planId);
            } else {
                if ($acceptancePlan !== null) {
                    throw new RuntimeException('omr_mock_acceptance_plan_forbidden');
                }
                $mockPrepared = $this->mockSessions->prepare($candidate, (int) $instanceCount, (string) $patternMode);
                if (! hash_equals((string) $expectedMockManifestSha256, (string) $mockPrepared['sha256'])) {
                    throw new RuntimeException('omr_mock_manifest_hash_stale');
                }
            }
        } elseif ($diagnosticPurpose !== OmrDiagnosticRun::PURPOSE_MATERIAL_DIAGNOSTIC) {
            throw new RuntimeException('omr_diagnostic_kit_required');
        }

        $scope = $this->resolveScope($actor, $institutionId, $academicCycleId);
        if ($candidate !== null) {
            $eligibility = $this->eligibilityService->evaluate(
                $candidate,
                AcademicCycle::query()->findOrFail($scope['academic_cycle_id']),
                TestKitEligibility::PURPOSE_REHEARSAL,
                operation: $diagnosticPurpose === OmrDiagnosticRun::PURPOSE_MOCK_REHEARSAL ? 'rehearsal' : 'omr',
            );
            if (! $eligibility['eligible']) {
                throw new RuntimeException('test_kit_rehearsal_ineligible:'.implode(
                    ',',
                    array_column($eligibility['blockers'], 'code'),
                ));
            }
        }
        if ($diagnosticPurpose === OmrDiagnosticRun::PURPOSE_MOCK_REHEARSAL && $resumeRunPublicId === null) {
            if ($idempotencyKey === null || trim($idempotencyKey) === '') {
                throw new RuntimeException('omr_mock_start_idempotency_key_required');
            }
            $mockLaunchReceipt = app(CommandIdempotency::class)->claim(
                $actor,
                'omr.diagnostic.start.mock',
                (string) $candidate->getKey(),
                [
                    'diagnostic_purpose' => $diagnosticPurpose,
                    'test_kit_version_id' => (int) $candidate->getKey(),
                    'expected_revision' => $expectedRevision,
                    'instance_count' => $instanceCount,
                    'pattern_mode' => $patternMode,
                    'mock_manifest_sha256' => $mockPrepared['sha256'],
                ],
                $idempotencyKey,
            );
            if (! $mockLaunchReceipt->wasRecentlyCreated) {
                $priorPayload = is_array($mockLaunchReceipt->payload) ? $mockLaunchReceipt->payload : [];
                $priorRunPublicId = (string) ($priorPayload['run_public_id'] ?? '');
                if ($priorRunPublicId === '') {
                    throw new RuntimeException('omr_mock_start_receipt_stale');
                }

                return OmrDiagnosticRun::query()
                    ->where('public_id', $priorRunPublicId)
                    ->where('created_by_user_id', $actor->id)
                    ->where('test_kit_version_id', (int) $candidate->getKey())
                    ->where('diagnostic_purpose', OmrDiagnosticRun::PURPOSE_MOCK_REHEARSAL)
                    ->firstOrFail();
            }
        }
        if ($resumeRunPublicId !== null) {
            $resume = OmrDiagnosticRun::query()
                ->where('public_id', $resumeRunPublicId)
                ->where('created_by_user_id', $actor->id)
                ->where('diagnostic_purpose', $diagnosticPurpose)
                ->firstOrFail();
            $frozenConfig = $resume->mock_manifest_json['config'] ?? null;
            if ($diagnosticPurpose !== OmrDiagnosticRun::PURPOSE_MOCK_REHEARSAL
                || (int) $resume->test_kit_version_id !== (int) $candidate?->getKey()
                || ! hash_equals((string) $resume->mock_manifest_sha256, (string) $expectedMockManifestSha256)
                || ! is_array($frozenConfig)
                || (int) ($frozenConfig['instance_count'] ?? 0) !== $instanceCount
                || (string) ($frozenConfig['pattern_mode'] ?? '') !== $patternMode
                || $resume->isTerminal()) {
                throw new RuntimeException('omr_mock_resume_context_mismatch');
            }

            return $resume->fresh(['templateVersion', 'bookletVersion', 'testKitVersion', 'acceptancePlan']);
        }

        $setCode = strtoupper(trim((string) $declaredSetCode));
        if (! in_array($setCode, OmrBookletSet::SET_CODES, true)) {
            throw new RuntimeException('omr_diagnostic_set_code_invalid');
        }

        $templateId = is_int($templateVersion) ? $templateVersion : (int) ($templateVersion instanceof OmrAnswerSheetTemplateVersion ? $templateVersion->id : 0);
        $bookletId = is_int($bookletVersion) ? $bookletVersion : (int) ($bookletVersion instanceof OmrQuestionBookletVersion ? $bookletVersion->id : 0);
        if ($templateId < 1 || $bookletId < 1) {
            throw new RuntimeException('omr_diagnostic_material_identity_invalid');
        }

        return DB::transaction(function () use (
            $templateId,
            $bookletId,
            $setCode,
            $actor,
            $scope,
            $candidate,
            $planRecord,
            $planSnapshot,
            $kitComposition,
            $mockPrepared,
            $diagnosticPurpose,
            $idempotencyKey,
            $mockLaunchReceipt,
        ): OmrDiagnosticRun {
            $template = OmrAnswerSheetTemplateVersion::query()
                ->with('template')
                ->lockForUpdate()
                ->findOrFail($templateId);
            $booklet = OmrQuestionBookletVersion::query()
                ->lockForUpdate()
                ->findOrFail($bookletId);

            if ($candidate === null && ! in_array($template->status, [OmrAnswerSheetTemplateVersion::STATUS_COMPILED, OmrAnswerSheetTemplateVersion::STATUS_ACTIVE], true)) {
                throw new RuntimeException('omr_diagnostic_template_not_active');
            }
            if ($candidate !== null
                && ((int) $template->id !== (int) $candidate->template_version_id
                    || (int) $booklet->id !== (int) $candidate->booklet_version_id)) {
                throw new RuntimeException('test_kit_rehearsal_material_mismatch');
            }
            if ($candidate === null && $booklet->template_version_id !== null && (int) $booklet->template_version_id !== (int) $template->id) {
                throw new RuntimeException('omr_diagnostic_material_incompatible');
            }
            if (! $booklet->isFinalized()) {
                throw new RuntimeException('omr_diagnostic_booklet_not_finalized');
            }

            $set = OmrBookletSet::query()
                ->where('booklet_version_id', $booklet->id)
                ->where('set_code', $setCode)
                ->lockForUpdate()
                ->first();
            if ($set === null) {
                throw new RuntimeException('omr_diagnostic_set_missing');
            }
            if (! $set->isFinalized()) {
                throw new RuntimeException('omr_diagnostic_set_not_finalized');
            }
            if ($candidate !== null && (int) $set->id !== (int) $kitComposition['booklet_set_id']) {
                throw new RuntimeException('test_kit_rehearsal_set_mismatch');
            }

            $attributes = [
                'execution_kind' => OmrDiagnosticRun::EXECUTION_KIND_DIAGNOSTIC,
                'diagnostic_purpose' => $diagnosticPurpose,
                'institution_id' => $scope['institution_id'],
                'academic_cycle_id' => $scope['academic_cycle_id'],
                'template_version_id' => $template->id,
                'booklet_version_id' => $booklet->id,
                'declared_set_code' => $setCode,
                'status' => OmrDiagnosticRun::STATUS_OPEN,
                'phase' => OmrDiagnosticRun::PHASE_CAPTURE,
                'revision' => 1,
                'created_by_user_id' => $actor->id,
            ];

            if ($candidate !== null && $diagnosticPurpose === OmrDiagnosticRun::PURPOSE_KIT_ACCEPTANCE
                && $planRecord !== null && $planSnapshot !== null) {
                /** @var array<string, string> $workerIdentity */
                $workerIdentity = $planSnapshot['expected_worker_identity'];
                $workerCommit = (string) $workerIdentity['engine_commit'];
                $planPayload = $planSnapshot['acceptance_plan_json'];
                $attributes = [
                    ...$attributes,
                    'test_kit_id' => (int) $candidate->test_kit_id,
                    'test_kit_version_id' => (int) $candidate->id,
                    'acceptance_plan_id' => (int) $planRecord->id,
                    'booklet_set_id' => (int) $candidate->booklet_set_id,
                    'manifest_sha256' => (string) $candidate->manifest_sha256,
                    'test_kit_manifest_sha256' => (string) $candidate->manifest_sha256,
                    'test_instrument_version_id' => (int) $candidate->test_instrument_version_id,
                    'profile_content_sha256' => (string) $planPayload['profile_content_sha256'],
                    'acceptance_protocol_schema_version' => (string) $planSnapshot['acceptance_protocol_schema_version'],
                    'acceptance_protocol_sha256' => (string) $planSnapshot['acceptance_protocol_sha256'],
                    'acceptance_plan_schema_version' => (string) $planSnapshot['acceptance_plan_schema_version'],
                    'acceptance_plan_sha256' => (string) $planSnapshot['acceptance_plan_sha256'],
                    'scoring_kernel_identity' => (string) $planSnapshot['scoring_kernel_identity'],
                    'expected_worker_identity_json' => $workerIdentity,
                    'worker_commit' => $workerCommit,
                    'acceptance_plan_json' => $planPayload,
                    'encrypted_acceptance_plan_snapshot' => $planPayload,
                ];
            } elseif ($candidate !== null && $diagnosticPurpose === OmrDiagnosticRun::PURPOSE_MOCK_REHEARSAL
                && is_array($mockPrepared)) {
                $manifest = $mockPrepared['manifest'];
                $workerIdentity = $manifest['expected_worker_identity'];
                $attributes = [
                    ...$attributes,
                    'test_kit_id' => (int) $candidate->test_kit_id,
                    'test_kit_version_id' => (int) $candidate->id,
                    'booklet_set_id' => (int) $candidate->booklet_set_id,
                    'manifest_sha256' => (string) $candidate->manifest_sha256,
                    'test_kit_manifest_sha256' => (string) $candidate->manifest_sha256,
                    'test_instrument_version_id' => (int) $candidate->test_instrument_version_id,
                    'profile_content_sha256' => (string) $manifest['profile_content_sha256'],
                    'scoring_kernel_identity' => (string) $manifest['scoring_kernel_identity'],
                    'expected_worker_identity_json' => $workerIdentity,
                    'worker_commit' => (string) ($workerIdentity['engine_commit'] ?? ''),
                    'mock_manifest_json' => $manifest,
                    'mock_manifest_schema_version' => (string) ($manifest['schema_version'] ?? ''),
                    'mock_manifest_sha256' => (string) $mockPrepared['sha256'],
                ];
            } elseif ($candidate !== null) {
                throw new RuntimeException('test_kit_rehearsal_acceptance_plan_unresolved');
            }

            $run = OmrDiagnosticRun::query()->create($attributes);
            if ($mockLaunchReceipt !== null) {
                $receiptPayload = is_array($mockLaunchReceipt->payload) ? $mockLaunchReceipt->payload : [];
                $receiptPayload['run_public_id'] = (string) $run->public_id;
                $mockLaunchReceipt->forceFill(['payload' => $receiptPayload])->saveOrFail();
            }

            $auditPayload = [
                'run_id' => (int) $run->id,
                'run_public_id' => (string) $run->public_id,
                'diagnostic_purpose' => $diagnosticPurpose,
                'template_version_id' => (int) $template->id,
                'booklet_version_id' => (int) $booklet->id,
                'test_kit_version_id' => $candidate?->id,
                'acceptance_plan_id' => $planRecord?->id,
                'declared_set_code' => $setCode,
                'institution_id' => $scope['institution_id'],
                'academic_cycle_id' => $scope['academic_cycle_id'],
            ];
            if ($idempotencyKey !== null) {
                $auditPayload['idempotency_key'] = $idempotencyKey;
            }
            $this->recordAuditEvent->handle('omr.diagnostic.run_started', $auditPayload, (string) $actor->id);

            if ($diagnosticPurpose === OmrDiagnosticRun::PURPOSE_MOCK_REHEARSAL
                && $template->qr_mode->value === 'qr_disabled') {
                app(StartOmrPendingCaptureSet::class)->forDiagnostic(
                    $run,
                    $actor,
                    (string) Str::uuid(),
                    'instance-001',
                );
            }

            return $run->fresh(['templateVersion', 'bookletVersion', 'testKitVersion', 'acceptancePlan']);
        });
    }

    /**
     * @return array{institution_id: int, academic_cycle_id: int, cycle_code: string}
     */
    private function resolveScope(User $actor, ?int $institutionId, ?int $academicCycleId): array
    {
        $actor->loadMissing('staffProfile');
        $profile = $actor->staffProfile;

        if ($profile === null || ! $profile->isActive()) {
            throw new RuntimeException('omr_diagnostic_owner_unavailable');
        }

        $actorScope = $actor->actorScope();
        $resolvedInstitutionId = $institutionId ?? $actorScope->institutionId ?? (int) Institution::current()->id;

        if ($resolvedInstitutionId < 1
            || ($actorScope->institutionId !== null && $resolvedInstitutionId !== $actorScope->institutionId)) {
            throw new RuntimeException('omr_diagnostic_scope_mismatch');
        }

        $institutionExists = Institution::query()->whereKey($resolvedInstitutionId)->exists();
        if (! $institutionExists) {
            throw new RuntimeException('omr_diagnostic_scope_missing');
        }

        $cycleQuery = AcademicCycle::query()->where('institution_id', $resolvedInstitutionId);
        if ($academicCycleId !== null) {
            $cycle = (clone $cycleQuery)->whereKey($academicCycleId)->first();
        } elseif ($actorScope->activeCycleCode !== null) {
            $cycle = (clone $cycleQuery)->where('code', $actorScope->activeCycleCode)->first();
        } else {
            $cycle = (clone $cycleQuery)->where('is_active', true)->first();
        }

        if ($cycle === null) {
            throw new RuntimeException('omr_diagnostic_cycle_missing');
        }

        if ($actorScope->activeCycleCode !== null
            && (string) $cycle->code !== (string) $actorScope->activeCycleCode) {
            throw new RuntimeException('omr_diagnostic_scope_mismatch');
        }

        $decision = $this->authorizer->decide($actor, CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG, [
            'office' => 'guidance',
            'institution_id' => (int) $cycle->institution_id,
            'cycle' => (string) $cycle->code,
        ]);

        if (! $decision->allowed) {
            throw new RuntimeException('omr_diagnostic_capability_'.$decision->reason);
        }

        return [
            'institution_id' => (int) $cycle->institution_id,
            'academic_cycle_id' => (int) $cycle->id,
            'cycle_code' => (string) $cycle->code,
        ];
    }
}
