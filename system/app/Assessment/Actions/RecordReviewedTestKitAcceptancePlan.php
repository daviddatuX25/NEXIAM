<?php

namespace App\Assessment\Actions;

use App\Admission\Support\CommandIdempotency;
use App\Assessment\Support\TestKitAcceptancePlan;
use App\Assessment\Support\TestKitManifestBuilder;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrBookletSet;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\Assessment\TestInstrumentVersion;
use App\Models\Assessment\TestKit;
use App\Models\Assessment\TestKitAcceptancePlanRecord;
use App\Models\Assessment\TestKitVersion;
use App\Models\Foundation\Operation;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/** Records a reviewed, attributable acceptance plan before any diagnostic run. */
final class RecordReviewedTestKitAcceptancePlan
{
    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly CommandIdempotency $idempotency,
        private readonly TestKitAcceptancePlan $plans,
        private readonly TestKitManifestBuilder $manifestBuilder,
        private readonly RecordAuditEvent $audit,
    ) {}

    public function handle(
        User $actor,
        int $testKitVersionId,
        string $sourceKey,
        int $expectedRevision,
        string $idempotencyKey,
    ): TestKitAcceptancePlanRecord {
        return DB::transaction(function () use ($actor, $testKitVersionId, $sourceKey, $expectedRevision, $idempotencyKey): TestKitAcceptancePlanRecord {
            $unlockedVersion = TestKitVersion::query()->findOrFail($testKitVersionId);
            $kit = TestKit::query()->lockForUpdate()->findOrFail((int) $unlockedVersion->test_kit_id);
            $principal = User::query()->find($actor->getKey());
            if ($principal === null || ! $this->authorizer->decideFresh(
                $principal,
                CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG,
                ['office' => 'guidance', 'institution_id' => (int) $kit->institution_id],
            )->allowed) {
                throw new AuthorizationException('Guidance assessment-catalog authority for the Test Kit institution is required.');
            }

            $version = TestKitVersion::query()->whereKey($testKitVersionId)->lockForUpdate()->firstOrFail();
            if ((int) $version->test_kit_id !== (int) $kit->getKey()) {
                throw new RuntimeException('The Test Kit version changed family during reviewed-plan registration.');
            }
            $source = $this->plans->loadReviewedSource($sourceKey);
            $payload = [
                'test_kit_id' => (int) $kit->getKey(),
                'test_kit_version_id' => (int) $version->getKey(),
                'source_key' => $sourceKey,
                'registry_sha256' => $source['source_evidence']['registry_sha256'],
                'authored_source_sha256' => $source['source_sha256'],
                'review_evidence_sha256' => $source['review_evidence_sha256'],
                'expected_revision' => $expectedRevision,
            ];
            $operation = $this->idempotency->claim(
                $principal,
                'test_kit.acceptance_plan.record',
                (string) $version->getKey(),
                $payload,
                $idempotencyKey,
            );
            if (($planId = $this->replayedPlanId($operation)) !== null) {
                return TestKitAcceptancePlanRecord::query()
                    ->where('test_kit_version_id', $version->getKey())
                    ->findOrFail($planId);
            }
            if ((int) $version->revision !== $expectedRevision) {
                throw ValidationException::withMessages([
                    'expected_revision' => 'The Test Kit version changed since it was opened. Refresh before recording a plan.',
                ]);
            }
            if ($version->revoked_at !== null) {
                throw ValidationException::withMessages([
                    'test_kit_version_id' => 'A revoked Test Kit version cannot start a new acceptance plan.',
                ]);
            }

            $profile = TestInstrumentVersion::query()->whereKey($version->test_instrument_version_id)->lockForUpdate()->firstOrFail();
            $booklet = OmrQuestionBookletVersion::query()->whereKey($version->booklet_version_id)->lockForUpdate()->firstOrFail();
            $set = OmrBookletSet::query()->where('booklet_version_id', $booklet->getKey())
                ->whereKey($version->booklet_set_id)->lockForUpdate()->firstOrFail();
            $template = OmrAnswerSheetTemplateVersion::query()->whereKey($version->template_version_id)->lockForUpdate()->firstOrFail();
            $this->manifestBuilder->verify($version);

            $frozen = $this->plans->freeze($version, [
                ...$source['authored_plan'],
                'truth_source' => $source['truth_source'],
                'source_evidence' => $source['source_evidence'],
            ]);
            $plan = TestKitAcceptancePlanRecord::query()->create([
                'public_id' => (string) Str::uuid(),
                'test_kit_version_id' => (int) $version->getKey(),
                'manifest_sha256' => (string) $version->manifest_sha256,
                'source_key' => $sourceKey,
                'authored_source_sha256' => $source['source_sha256'],
                'protocol_schema_version' => $frozen['acceptance_protocol_schema_version'],
                'protocol_sha256' => $frozen['acceptance_protocol_sha256'],
                'plan_schema_version' => $frozen['acceptance_plan_schema_version'],
                'plan_sha256' => $frozen['acceptance_plan_sha256'],
                'plan_payload' => $frozen['acceptance_plan_json'],
                'provenance_kind' => $source['provenance_kind'],
                'author_reference' => $source['author_reference'],
                'truth_source_references_json' => $source['truth_source_references'],
                'review_evidence_reference' => $source['review_evidence_reference'],
                'review_evidence_sha256' => $source['review_evidence_sha256'],
                'created_by_user_id' => (int) $principal->getKey(),
            ]);

            $this->rememberPlan($operation, $plan);
            $this->audit->handle('assessment.test_kit.acceptance_plan_recorded', [
                'test_kit_id' => (int) $kit->getKey(),
                'acceptance_plan_id' => (int) $plan->getKey(),
                'plan_sha256' => $frozen['acceptance_plan_sha256'],
                'protocol_sha256' => $frozen['acceptance_protocol_sha256'],
                'registry_sha256' => $frozen['registry_sha256'],
                'scoring_kernel_identity' => $frozen['scoring_kernel_identity'],
                'provenance_kind' => $source['provenance_kind'],
            ], (string) $principal->getKey());

            return $plan;
        });
    }

    private function replayedPlanId(Operation $operation): ?int
    {
        $payload = $operation->payload;
        $result = is_array($payload) ? ($payload['test_kit_acceptance_plan_result'] ?? null) : null;
        if ($result === null) {
            return null;
        }
        if (! is_array($result) || ! is_numeric($result['acceptance_plan_id'] ?? null)) {
            throw new RuntimeException('The Test Kit acceptance plan command receipt has an invalid result identity.');
        }

        return (int) $result['acceptance_plan_id'];
    }

    private function rememberPlan(Operation $operation, TestKitAcceptancePlanRecord $plan): void
    {
        $payload = $operation->payload;
        if (! is_array($payload) || ! is_string($payload['request_hash'] ?? null)) {
            throw new RuntimeException('The Test Kit acceptance plan command receipt has no payload digest.');
        }
        $payload['test_kit_acceptance_plan_result'] = ['acceptance_plan_id' => (int) $plan->getKey()];
        $operation->forceFill(['payload' => $payload])->save();
    }
}
