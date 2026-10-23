<?php

namespace App\Consultation\Actions;

use App\Admission\Support\CommandIdempotency;
use App\Admission\Support\GwaScaleNormalizer;
use App\AdmissionEngine\AdmissionEngineRegistry;
use App\AdmissionEngine\DTOs\AdmissionInputPayload;
use App\AdmissionEngine\Support\BenchmarkInputSchema;
use App\AdmissionEngine\Support\RiasecActivityCatalog;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Consultation\Support\ConsultationScoreProjection;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\AdmissionBenchmarkActivation;
use App\Models\Admission\AdmissionSupportSnapshot;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;

final class RecordSupplementalEvidence
{
    public function __construct(
        private readonly CommandIdempotency $idempotency,
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly AdmissionEngineRegistry $registry,
        private readonly ConsultationScoreProjection $scoreProjection,
    ) {}

    /** @param array<string, mixed> $validated */
    public function handle(AdmissionAttempt $attempt, User $actor, array $validated, string $idempotencyKey): ?AdmissionSupportSnapshot
    {
        return DB::transaction(function () use ($attempt, $actor, $validated, $idempotencyKey): ?AdmissionSupportSnapshot {
            /** @var AdmissionAttempt $lockedAttempt */
            $lockedAttempt = AdmissionAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            if (! app(CapabilityAuthorizer::class)->decideFresh(
                $actor,
                CapabilityCatalog::GUIDANCE_CONSULTATION_DECIDE,
                ['institution_id' => (int) $lockedAttempt->institution_id, 'office' => 'guidance'],
            )->allowed) {
                throw new AuthorizationException('Supplemental evidence is not authorized for this applicant.');
            }
            $allowedKeys = ['vocational_activities', 'activities_reviewed', 'evidence_source', 'counselor_notes', 'gwa', 'gwa_scale', 'benchmark_activation_id', 'schema_digest', 'idempotency_key'];
            $data = [...$validated, 'evidence_source' => $validated['evidence_source'] ?? 'examinee_statement'];
            if (array_diff(array_keys($validated), $allowedKeys) !== []
                || $idempotencyKey === '' || mb_strlen($idempotencyKey) > 128
                || $data['evidence_source'] !== 'examinee_statement') {
                throw new InvalidArgumentException('Supplemental evidence requires advisory source and command identity.');
            }
            $activationQuery = AdmissionBenchmarkActivation::query()
                ->where('institution_id', $lockedAttempt->institution_id)
                ->where('academic_cycle_id', $lockedAttempt->academic_cycle_id)
                ->with('benchmarkSet.features');
            $selectedActivation = ! empty($data['benchmark_activation_id'])
                ? $activationQuery->whereKey((int) $data['benchmark_activation_id'])->first()
                : $activationQuery->where('is_current', true)->first();
            if (! empty($data['benchmark_activation_id']) && $selectedActivation === null) {
                throw ValidationException::withMessages(['benchmark_activation_id' => 'The selected benchmark activation is unavailable.']);
            }
            $set = $selectedActivation?->benchmarkSet;
            if ($set !== null && (($data['schema_digest'] ?? null) !== null
                && ! hash_equals((string) $set->schema_sha256, (string) $data['schema_digest']))) {
                throw ValidationException::withMessages(['schema_digest' => 'The selected evidence schema or benchmark content changed.']);
            }
            if ($set !== null
                && (! hash_equals((string) $set->schema_sha256, $set->computedSchemaDigest())
                    || ! hash_equals((string) $set->content_sha256, $set->computedContentDigest()))) {
                throw ValidationException::withMessages(['schema_digest' => 'The selected evidence schema or benchmark content changed.']);
            }
            if ($set === null && (($data['schema_digest'] ?? null) !== null || ! empty($data['benchmark_activation_id']))) {
                throw ValidationException::withMessages(['schema_digest' => 'The selected evidence schema or benchmark content changed.']);
            }
            $activities = array_values($data['vocational_activities'] ?? []);
            $reviewed = (bool) ($data['activities_reviewed'] ?? false);
            RiasecActivityCatalog::project($activities, $reviewed);
            $idempotencyPayload = $data;
            unset($idempotencyPayload['idempotency_key']);
            $idempotencyPayload['benchmark_activation_id'] = $selectedActivation?->id;
            $idempotencyPayload['schema_digest'] = $set?->schema_sha256;
            $operation = $this->idempotency->claim(
                $actor,
                'consultation.supplemental_evidence_recorded',
                'attempt:'.$lockedAttempt->id,
                $idempotencyPayload,
                $idempotencyKey,
            );
            $operationPayload = is_array($operation->payload) ? $operation->payload : [];
            if (array_key_exists('evidence_recorded', $operationPayload)) {
                if (isset($operationPayload['snapshot_id'])) {
                    $snapshot = AdmissionSupportSnapshot::query()->find($operationPayload['snapshot_id']);
                    if ($snapshot instanceof AdmissionSupportSnapshot) {
                        return $snapshot;
                    }

                    throw new RuntimeException('The idempotent supplemental evidence snapshot is unavailable.');
                }

                return null;
            }
            if ($selectedActivation !== null) {
                $currentActivation = AdmissionBenchmarkActivation::currentForCycle((int) $lockedAttempt->academic_cycle_id);
                if ($currentActivation === null || (int) $currentActivation->id !== (int) $selectedActivation->id) {
                    throw ValidationException::withMessages(['schema_digest' => 'The benchmarks in use changed. Your input is kept; review the current evidence fields before recording.']);
                }
            }
            $payload = is_array($lockedAttempt->payload) ? $lockedAttempt->payload : [];
            $before = is_array($payload['supplemental_evidence'] ?? null) ? $payload['supplemental_evidence'] : [];
            $inputSchema = $set === null ? null : BenchmarkInputSchema::projection($set);
            $evidence = [
                ...$before,
                'evidence_format_version' => 2,
                'evidence_source' => $data['evidence_source'],
                'vocational_activities' => array_key_exists('vocational_activities', $data) ? $activities : array_values($before['vocational_activities'] ?? []),
                'activities_reviewed' => array_key_exists('activities_reviewed', $data) ? $reviewed : (bool) ($before['activities_reviewed'] ?? false),
                'counselor_notes' => array_key_exists('counselor_notes', $data) ? $data['counselor_notes'] : ($before['counselor_notes'] ?? null),
                'benchmark_activation_id' => $selectedActivation?->id,
                'schema_digest' => $set?->schema_sha256,
                'recorded_by_user_id' => $actor->id,
                'recorded_at' => now()->toIso8601String(),
            ];
            if (array_key_exists('gwa', $data)) {
                if ($data['gwa'] !== null && $data['gwa'] !== '') {
                    $rawGwa = (float) $data['gwa'];
                    $scale = is_string($data['gwa_scale'] ?? null) ? $data['gwa_scale'] : null;
                    $gwaMeta = GwaScaleNormalizer::normalize($rawGwa, $scale);
                    $lockedAttempt->gwa = $gwaMeta['normalized'];
                    $evidence['gwa'] = $gwaMeta['normalized'];
                    $evidence['gwa_meta'] = $gwaMeta;
                    $payload['gwa_meta'] = $gwaMeta;
                } else {
                    $lockedAttempt->gwa = null;
                    $evidence['gwa'] = null;
                    unset($evidence['gwa_meta'], $payload['gwa_meta']);
                }
            } elseif (isset($before['gwa_meta'])) {
                $evidence['gwa_meta'] = $before['gwa_meta'];
            }
            $payload['supplemental_evidence'] = $evidence;
            $lockedAttempt->payload = $payload;
            $lockedAttempt->save();

            if ($selectedActivation === null) {
                $operation->payload = [...$operationPayload, 'evidence_recorded' => true];
                $operation->save();
                $this->recordAuditEvent->handle(
                    'consultation.supplemental_evidence_recorded',
                    ['admission_attempt_id' => $lockedAttempt->id, 'evidence_format_version' => $evidence['evidence_format_version']],
                    (string) $actor->id,
                );

                return null;
            }

            $version = (int) (AdmissionSupportSnapshot::query()
                ->where('admission_attempt_id', $lockedAttempt->id)
                ->max('version') ?? 0) + 1;
            $scoreProjection = $this->scoreProjection->forAttempt($lockedAttempt);
            $assessmentContext = $scoreProjection['assessment_context'];
            $inputScoreSeries = ConsultationScoreProjection::admissionInputSeries($scoreProjection);
            $input = AdmissionInputPayload::fromAttempt($lockedAttempt->fresh(), $inputScoreSeries);
            $provider = $this->registry->tabFmPairwise;
            $result = $provider->evaluateAtActivation($input, $selectedActivation);
            $resultPayload = $result->toArray();
            $resultPayload['input_schema'] = $inputSchema;
            $resultPayload['assessment_context'] = $assessmentContext;
            $resultPayload['applicant_evidence'] = $this->applicantEvidence($lockedAttempt, $input);
            $snapshot = AdmissionSupportSnapshot::query()->create([
                'admission_attempt_id' => $lockedAttempt->id,
                'academic_cycle_id' => $lockedAttempt->academic_cycle_id,
                'version' => $version,
                'engine_slot' => $result->slot,
                'engine_version' => $result->engineVersion,
                'benchmark_activation_id' => $result->evaluationContext?->benchmarkActivationId,
                'benchmark_hash' => $result->evaluationContext?->contentDigest,
                'input_digest' => $result->evaluationContext?->inputDigest,
                'payload' => $resultPayload,
                'trigger_event' => 'supplemental_evidence_recorded',
                'recorded_by_user_id' => $actor->id,
            ]);

            $operation->payload = [...$operationPayload, 'evidence_recorded' => true, 'snapshot_id' => $snapshot->id];
            $operation->save();
            $this->recordAuditEvent->handle(
                'consultation.supplemental_evidence_recorded',
                [
                    'admission_attempt_id' => $lockedAttempt->id,
                    'snapshot_id' => $snapshot->id,
                    'version' => $snapshot->version,
                    'trigger_event' => $snapshot->trigger_event,
                    'evidence_format_version' => $evidence['evidence_format_version'],
                    'schema_digest' => $set?->schema_sha256,
                ],
                (string) $actor->id,
            );

            return $snapshot;
        });
    }

    /** @return array<string, mixed> */
    private function applicantEvidence(AdmissionAttempt $attempt, AdmissionInputPayload $input): array
    {
        $supplemental = $attempt->payload['supplemental_evidence'] ?? [];
        $activities = array_values($supplemental['vocational_activities'] ?? []);
        $reviewed = (bool) ($supplemental['activities_reviewed'] ?? false);
        $rawFlags = RiasecActivityCatalog::project($activities, $reviewed);
        $activeTags = [];
        $hollandLabels = [
            'riasec_r' => 'Realistic',
            'riasec_i' => 'Investigative',
            'riasec_a' => 'Artistic',
            'riasec_s' => 'Social',
            'riasec_e' => 'Enterprising',
            'riasec_c' => 'Conventional',
        ];
        foreach ($hollandLabels as $key => $label) {
            if (! empty($rawFlags[$key])) {
                $activeTags[] = $label;
            }
        }

        $gwaInfo = GwaScaleNormalizer::fromAttempt($attempt);

        return [
            'gwa' => $input->gwa ?? $gwaInfo['gwa'],
            'gwa_raw' => $gwaInfo['gwa_raw'],
            'gwa_scale' => $gwaInfo['gwa_scale'],
            'gwa_scale_label' => $gwaInfo['gwa_scale_label'],
            'gwa_is_converted' => $gwaInfo['is_converted'],
            'riasec_flags' => $rawFlags,
            'riasec_tags' => $activeTags,
            'has_riasec' => $reviewed || ! empty($activeTags),
            'vocational_activities' => $activities,
            'activities_reviewed' => $reviewed,
            'evidence_source' => $supplemental['evidence_source'] ?? null,
            'counselor_notes' => $supplemental['counselor_notes'] ?? null,
            'recorded_by_user_id' => $supplemental['recorded_by_user_id'] ?? null,
            'recorded_at' => $supplemental['recorded_at'] ?? null,
        ];
    }
}
