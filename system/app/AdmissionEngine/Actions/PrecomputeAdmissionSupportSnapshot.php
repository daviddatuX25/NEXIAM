<?php

namespace App\AdmissionEngine\Actions;

use App\AdmissionEngine\AdmissionEngineRegistry;
use App\AdmissionEngine\DTOs\AdmissionInputPayload;
use App\Consultation\Support\AttemptResultReadiness;
use App\Consultation\Support\ConsultationScoreProjection;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\AdmissionBenchmarkActivation;
use App\Models\Admission\AdmissionSupportSnapshot;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class PrecomputeAdmissionSupportSnapshot
{
    public function __construct(
        private readonly ConsultationScoreProjection $scoreProjection,
        private readonly AdmissionEngineRegistry $registry,
    ) {}

    public function handle(
        AdmissionAttempt $attempt,
        ?User $actor = null,
        string $triggerEvent = 'automated_precomputation',
    ): ?AdmissionSupportSnapshot {
        return DB::transaction(function () use ($attempt, $actor, $triggerEvent): ?AdmissionSupportSnapshot {
            $lockedAttempt = AdmissionAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            $projection = $this->scoreProjection->forAttempt($lockedAttempt);
            $inputSeries = ConsultationScoreProjection::admissionInputSeries($projection);
            $hasScoreRecords = count(array_filter(
                $projection['series'] ?? [],
                static fn (mixed $score): bool => is_array($score)
                    && (($score['score'] ?? null) !== null || ($score['percentile'] ?? null) !== null),
            )) > 0;

            if (! (bool) $lockedAttempt->getAttribute('result_ready')
                && ! AttemptResultReadiness::isReady($lockedAttempt)
                && ! $hasScoreRecords) {
                return null;
            }

            $activation = AdmissionBenchmarkActivation::currentForCycle((int) $lockedAttempt->academic_cycle_id);
            if ($activation === null) {
                return null;
            }

            $input = AdmissionInputPayload::fromAttempt($lockedAttempt, $inputSeries);
            $benchmarkHash = $activation->benchmarkSet?->content_sha256;
            $latest = AdmissionSupportSnapshot::latestForAttempt((int) $lockedAttempt->id);
            if ($latest !== null
                && $latest->engine_slot === 'tabfm_pairwise'
                && ($latest->payload['status'] ?? null) === 'evaluated'
                && json_encode($latest->payload['assessment_context'] ?? null) === json_encode($projection['assessment_context'])
                && $latest->input_digest === $input->digest()) {
                return $latest;
            }

            $result = $this->registry->tabFmPairwise->evaluateAtActivation($input, $activation);
            if ($result->status === 'unavailable') {
                throw new \RuntimeException("Inference service unavailable for attempt {$lockedAttempt->id}: {$result->message}");
            }
            $payload = $result->toArray();
            $payload['assessment_context'] = $projection['assessment_context'];
            $version = (int) (AdmissionSupportSnapshot::query()
                ->where('admission_attempt_id', $lockedAttempt->id)
                ->max('version') ?? 0) + 1;

            return AdmissionSupportSnapshot::query()->create([
                'admission_attempt_id' => $lockedAttempt->id,
                'academic_cycle_id' => $lockedAttempt->academic_cycle_id,
                'version' => $version,
                'engine_slot' => 'tabfm_pairwise',
                'engine_version' => $result->engineVersion,
                'benchmark_activation_id' => $activation->id,
                'benchmark_hash' => $result->evaluationContext?->contentDigest ?? $benchmarkHash,
                'input_digest' => $input->digest(),
                'payload' => $payload,
                'trigger_event' => $triggerEvent,
                'recorded_by_user_id' => $actor?->id,
            ]);
        }, attempts: 3);
    }
}
