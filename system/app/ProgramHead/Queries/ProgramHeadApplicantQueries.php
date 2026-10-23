<?php

namespace App\ProgramHead\Queries;

use App\Admission\Support\AttemptProgramFacts;
use App\Admission\Support\GwaScaleNormalizer;
use App\AdmissionEngine\Support\PhilippineDegreeHollandMap;
use App\AdmissionEngine\Support\RiasecActivityCatalog;
use App\AdmissionEngine\Support\TabFmProvenance;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Consultation\Support\ConsultationScoreProjection;
use App\Consultation\Support\RecordedEvaluationApplicability;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\AdmissionClearanceReceipt;
use App\Models\Admission\AdmissionSupportSnapshot;
use App\Models\Admission\ProgramFollowUp;
use App\Models\Admission\ProgramPool;
use App\Models\Admission\ProgramSelection;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Program;
use App\Models\Release\ConsultationDecision;
use App\Models\Release\ResultDocument;
use App\Models\User;
use App\ProgramHead\Support\ProtectedViewGuard;
use App\Release\Queries\ReleasedResultDocumentQuery;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * D-080: Tier A candidate summary vs Tier B protected admission-support
 * record. Tier B persists protected-view audit + access notice BEFORE any
 * protected field is serialized (fail-closed). Tier A never receives scores,
 * counselor notes, or engine input/output.
 */
final class ProgramHeadApplicantQueries
{
    public function __construct(
        private readonly ProtectedViewGuard $protectedViewGuard,
        private readonly CapabilityAuthorizer $authorizer,
        private readonly ConsultationScoreProjection $scoreProjection,
        private readonly RecordedEvaluationApplicability $recordedApplicability,
        private readonly ReleasedResultDocumentQuery $releasedResultDocument,
    ) {}

    /**
     * Tier A summary — minimum necessary visibility.
     *
     * @return array<string, mixed>
     */
    public function summary(AdmissionAttempt $attempt, Program $program): array
    {
        $selection = AttemptProgramFacts::currentSelection($attempt);
        $released = AttemptProgramFacts::isResultReleased($attempt);

        return [
            'result_released' => $released,
            'tier' => 'A',
            'attempt_id' => $attempt->id,
            'reference_code' => $attempt->reference_code,
            'name' => $attempt->identity?->last_name.', '.$attempt->identity?->first_name,
            'basis' => $this->basis($attempt, $program),
            'lifecycle' => $this->programLifecycle($attempt, $program),
            'advised' => AttemptProgramFacts::isAdvised($attempt),
            'selection_status' => $selection?->program_id === $program->id ? $selection->status : null,
            'follow_up_count' => ProgramFollowUp::query()
                ->where('admission_attempt_id', $attempt->id)
                ->where('program_id', $program->id)
                ->whereIn('status', ProgramFollowUp::OPEN_STATUSES)
                ->count(),
            'legacy_program_mutation_blocked' => AttemptProgramFacts::isEnrolling($attempt)
                || AttemptProgramFacts::isEnrolled($attempt),
            'visibility_note' => 'Scores, counselor notes, and other program choices are not shown at this visibility level.',
            'blocked_reason' => $released
                ? 'Protected results require an acknowledged selection or a Guidance referral for this program.'
                : 'Results have not been released. An effective Guidance referral for this program is required for protected academic review or discussion.',
        ];
    }

    /**
     * Tier B protected record. Access evidence persists before serialization.
     *
     * @return array<string, mixed>
     */
    public function protectedRecord(AdmissionAttempt $attempt, Program $program, User $ph): array
    {
        $this->assertProgramScope($attempt, $program, $ph);

        $tier = $this->protectedViewGuard->tierFor($attempt, $program);

        if ($tier['tier'] !== 'B') {
            return $this->summary($attempt, $program);
        }

        $this->protectedViewGuard->recordProtectedView($attempt, $program, $ph, $tier['access_reason'] ?? '');

        $selection = AttemptProgramFacts::currentSelection($attempt);
        $scoreProjection = $this->scoreProjection->forAttempt($attempt);
        $selectionIsProgram = $selection?->program_id === $program->id;
        $decision = ConsultationDecision::query()->where('admission_attempt_id', $attempt->id)
            ->with('counselor:id,name')->latest('id')->first();

        return [
            'result_released' => AttemptProgramFacts::isResultReleased($attempt),
            'tier' => 'B',
            'access_reason' => $tier['access_reason'],
            'blocked_reason' => null,
            'attempt_id' => $attempt->id,
            'reference_code' => $attempt->reference_code,
            'name' => $attempt->identity?->last_name.', '.$attempt->identity?->first_name,
            'lifecycle' => $this->programLifecycle($attempt, $program),
            'score_profile' => $this->academicScoreProfile($attempt),
            'selection' => [
                'status' => $selectionIsProgram ? $selection->status : null,
                'program_code' => $selectionIsProgram ? $program->code : null,
                'acknowledged' => $selectionIsProgram && $selection->applicant_acknowledged_at !== null,
            ],
            'follow_ups' => ProgramFollowUp::query()
                ->where('admission_attempt_id', $attempt->id)->where('program_id', $program->id)
                ->with('openedBy:id,name')->orderByDesc('id')->get()
                ->map(static fn (ProgramFollowUp $followUp): array => [
                    'id' => (int) $followUp->id,
                    'program_code' => $program->code,
                    'status' => $followUp->status,
                    'reason' => $followUp->reason,
                    'resolution_reason' => $followUp->resolution_reason,
                    'opened_at' => $followUp->opened_at?->toIso8601String(),
                    'resolved_at' => $followUp->resolved_at?->toIso8601String(),
                    'opened_by' => $followUp->openedBy?->name,
                ])->all(),
            'advising_outcome' => $decision === null ? null : [
                'decision_type' => $decision->decision_type,
                'recorded_at' => $decision->created_at?->toIso8601String(),
                'recorded_by' => $decision->counselor?->name,
            ],
            'result_sheet_url' => route('program-head.applicants.result-sheet', [
                'program' => $program->code, 'attempt' => $attempt->id,
            ], false),
            'analysis' => $this->academicAnalysis($attempt, $program, $scoreProjection),
            'background' => $this->academicBackground($attempt, $program),
            'legacy_program_mutation_blocked' => AttemptProgramFacts::isEnrolling($attempt)
                || AttemptProgramFacts::isEnrolled($attempt),
            'as_of' => now()->toIso8601String(),
        ];
    }

    /**
     * Project only a persisted TABFM result. This path must never call the
     * consultation query, which may execute a fresh model evaluation.
     *
     * @return array<string, mixed>
     */
    private function academicAnalysis(AdmissionAttempt $attempt, Program $program, array $scoreProjection): array
    {
        $snapshot = AdmissionSupportSnapshot::latestForAttempt((int) $attempt->id);
        $payload = is_array($snapshot?->payload) ? $snapshot->payload : [];
        $recommendations = is_array($payload['recommendations'] ?? null)
            ? $payload['recommendations']
            : [];
        $selected = null;

        foreach ($recommendations as $recommendation) {
            if (is_array($recommendation) && ($recommendation['program_code'] ?? $recommendation['code'] ?? null) === $program->code) {
                $selected = $recommendation;
                break;
            }
        }

        $hasApprovedProvenance = $snapshot !== null
            && $snapshot->engine_slot === 'tabfm_pairwise'
            && TabFmProvenance::isApprovedStoredEvaluation($payload, $snapshot->engine_version);
        $isCurrent = $hasApprovedProvenance && $this->recordedApplicability->matchesCurrentEvidence(
            $attempt,
            $snapshot,
            $payload,
            $scoreProjection,
        );
        $status = $snapshot === null
            ? (AttemptProgramFacts::isResultReady($attempt) ? 'no_analysis' : 'result_not_ready')
            : ($hasApprovedProvenance ? 'evaluated' : 'unavailable');
        $recommendation = $isCurrent && $selected !== null
            ? $this->academicRecommendation($selected, $program)
            : null;

        return [
            'slot' => 'tabfm_pairwise',
            'status' => $status,
            'evaluated' => $hasApprovedProvenance,
            'source_kind' => $snapshot === null ? 'active' : 'recorded',
            'current_applicable' => $isCurrent,
            'source_version' => $this->safeScalar($payload['benchmark_version'] ?? null),
            'benchmark_version' => $this->safeScalar($payload['benchmark_version'] ?? null),
            'engine_version' => $this->safeScalar($snapshot?->engine_version),
            'evaluated_at' => $this->safeTimestamp($payload['evaluated_at'] ?? null),
            'inference_backend' => $hasApprovedProvenance ? 'tabpfn_pytorch' : null,
            'fallback_reason' => $hasApprovedProvenance ? null : 'not_approved',
            'modality_coverage' => [
                'inference_provenance' => $hasApprovedProvenance
                    ? $this->approvedProvenance($payload)
                    : [],
            ],
            'recommendations' => $recommendation === null ? [] : [$recommendation],
            'message' => match (true) {
                $snapshot === null && ! AttemptProgramFacts::isResultReady($attempt) => 'TABFM evaluation is unavailable until the assessment result is ready.',
                $snapshot === null => 'No saved TABFM evaluation is available.',
                ! $hasApprovedProvenance => 'The saved TABFM result is unavailable because its approved evaluation provenance could not be confirmed.',
                ! $isCurrent => 'A saved TABFM result exists, but it does not apply to the current assessment evidence.',
                $selected === null => 'No TABFM result is available for this program.',
                default => 'Saved TABFM evidence applies to the current assessment. It is advisory only.',
            },
        ];
    }

    /**
     * @param  array<string, mixed>  $source
     * @return array<string, mixed>
     */
    private function academicRecommendation(array $source, Program $program): array
    {
        $drivers = is_array($source['drivers'] ?? null) && array_is_list($source['drivers'])
            ? array_values(array_filter($source['drivers'], 'is_string'))
            : [];
        $radar = $this->academicRadar($source['radar_comparison'] ?? null);

        return [
            'program_code' => $program->code,
            'program_name' => $program->name,
            'compatibility_index' => is_numeric($source['compatibility_index'] ?? null)
                ? (float) $source['compatibility_index']
                : null,
            'band' => is_string($source['band'] ?? null) ? $source['band'] : null,
            'passed_cutoff' => is_bool($source['passed_cutoff'] ?? null) ? $source['passed_cutoff'] : null,
            'drivers' => $drivers,
            'radar_comparison' => $radar,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function academicRadar(mixed $source): ?array
    {
        if (! is_array($source)
            || ($source['version'] ?? null) !== 1
            || ! is_array($source['scale'] ?? null)
            || ! is_array($source['axes'] ?? null)
            || ! is_array($source['student']['values'] ?? null)
            || ! is_array($source['profiles'] ?? null)) {
            return null;
        }

        $allowedAxes = [
            'logic' => ['label' => 'Logic', 'source_key' => 'LOGIC'],
            'verbal' => ['label' => 'Verbal', 'source_key' => 'PROFILE_VERBAL'],
            'numerical' => ['label' => 'Numerical', 'source_key' => 'PROFILE_QUANT'],
            'spatial' => ['label' => 'Spatial', 'source_key' => 'SPATIAL'],
            'memory' => ['label' => 'Memory', 'source_key' => 'MEMORY'],
            'language' => ['label' => 'Language', 'source_key' => 'LANGUAGE'],
        ];
        $axes = [];
        foreach ($source['axes'] as $axis) {
            $key = is_array($axis) ? ($axis['key'] ?? null) : null;
            if (! is_string($key) || ! isset($allowedAxes[$key])
                || ($axis['source_key'] ?? null) !== $allowedAxes[$key]['source_key']) {
                return null;
            }
            $axes[] = ['key' => $key, ...$allowedAxes[$key]];
        }
        $keys = array_column($axes, 'key');
        $values = static function (mixed $candidate) use ($keys): ?array {
            if (! is_array($candidate) || array_diff(array_keys($candidate), $keys) !== []) {
                return null;
            }
            $safe = [];
            foreach ($keys as $key) {
                $value = $candidate[$key] ?? null;
                if ($value !== null && (! is_numeric($value) || ! is_finite((float) $value))) {
                    return null;
                }
                $safe[$key] = $value === null ? null : (float) $value;
            }

            return $safe;
        };
        $student = $values($source['student']['values']);
        if ($student === null) {
            return null;
        }

        $profiles = [];
        foreach (['cutoff', 'regular', 'honors'] as $name) {
            $profile = $source['profiles'][$name] ?? null;
            $profileValues = is_array($profile) ? $values($profile['values'] ?? null) : null;
            if ($profileValues === null || ! is_int($profile['tier'] ?? null)) {
                return null;
            }
            $profiles[$name] = ['tier' => $profile['tier'], 'values' => $profileValues];
        }

        return [
            'version' => 1,
            'scale' => ['kind' => 'percentile', 'min' => 0, 'max' => 100],
            'axes' => $axes,
            'student' => ['values' => $student],
            'profiles' => $profiles,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, scalar>
     */
    private function approvedProvenance(array $payload): array
    {
        $provenance = $payload['modality_coverage']['inference_provenance'] ?? [];

        return array_intersect_key($provenance, array_flip([
            'package',
            'package_version',
            'checkpoint_identity',
            'checkpoint_sha256',
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function academicBackground(AdmissionAttempt $attempt, Program $program): array
    {
        $supplemental = is_array($attempt->payload['supplemental_evidence'] ?? null)
            ? $attempt->payload['supplemental_evidence']
            : [];
        $reviewed = (bool) ($supplemental['activities_reviewed'] ?? false);
        $activities = is_array($supplemental['vocational_activities'] ?? null)
            ? array_values(array_filter($supplemental['vocational_activities'], 'is_string'))
            : [];
        $profile = RiasecActivityCatalog::profileFromActivities($activities, $reviewed);
        $scores = is_array($profile['scores'] ?? null) ? $profile['scores'] : [];
        $themes = is_array($profile['emergent_themes'] ?? null) ? $profile['emergent_themes'] : [];
        $scoreKeys = [
            'riasec_r' => 'R',
            'riasec_i' => 'I',
            'riasec_a' => 'A',
            'riasec_s' => 'S',
            'riasec_e' => 'E',
            'riasec_c' => 'C',
        ];
        $projectedScores = [];
        foreach ($scoreKeys as $sourceKey => $targetKey) {
            if ($reviewed && is_numeric($scores[$sourceKey] ?? null)) {
                $projectedScores[$targetKey] = (float) $scores[$sourceKey];
            }
        }
        $selectedTarget = PhilippineDegreeHollandMap::allTargets()[$program->code] ?? null;
        $programTargets = $selectedTarget === null
            ? []
            : [$program->code => array_intersect_key($selectedTarget, array_flip([
                'priority_strands',
                'cgp_strand_alignment',
                'cmo_reference',
                'cmo_short',
                'cmo_url',
            ]))];
        $gwa = GwaScaleNormalizer::fromAttempt($attempt)['gwa'];

        return [
            'applicant_type' => $this->safeScalar($attempt->applicant_type),
            'strand' => $this->safeScalar($attempt->strand),
            'gwa' => is_numeric($gwa) ? (float) $gwa : null,
            'vocational_profile' => [
                'status' => $reviewed ? 'reviewed' : 'unreviewed',
                'is_reviewed' => $reviewed,
                'scores' => $projectedScores,
                'emergent_code' => $reviewed && is_string($profile['emergent_code'] ?? null) ? $profile['emergent_code'] : null,
                'emergent_themes' => $reviewed ? array_values(array_filter($themes, 'is_string')) : [],
                'program_targets' => $programTargets,
            ],
            'selected_program_code' => $program->code,
        ];
    }

    private function safeScalar(mixed $value): string|int|float|bool|null
    {
        return is_scalar($value) ? $value : null;
    }

    private function safeTimestamp(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    /**
     * Verify the fresh actor scope and capability before returning any row.
     */
    private function assertProgramScope(AdmissionAttempt $attempt, Program $program, User $actor): void
    {
        $freshActor = User::query()->find($actor->id);
        $profile = $freshActor?->staffProfile()->first();
        $scope = $freshActor?->actorScope();
        $cycle = AcademicCycle::query()->find($attempt->academic_cycle_id);

        if ($freshActor === null
            || $profile === null
            || ! $profile->isActive()
            || $profile->deactivated_at !== null
            || $attempt->status !== AdmissionAttempt::STATUS_ACCEPTED
            || $profile->office !== 'program'
            || (int) $profile->institution_id !== (int) $attempt->institution_id
            || (int) $program->institution_id !== (int) $attempt->institution_id
            || ! $program->is_active
            || $cycle === null
            || ! $cycle->is_active
            || $scope?->institutionId !== (int) $attempt->institution_id
            || ! AdmissionAttempt::query()->forWorkspace()->whereKey($attempt->id)->exists()
            || $scope->activeCycleCode !== $cycle->code
            || ! in_array($program->code, $scope->programCodes, true)
            || ! $this->authorizer->decideFresh($freshActor, CapabilityCatalog::PROGRAM_HEAD_VIEW, [
                'institution_id' => $attempt->institution_id,
                'program' => $program->code,
            ])->allowed) {
            throw new AuthorizationException('The current actor is not authorized for this program applicant projection.');
        }
    }

    private function programLifecycle(AdmissionAttempt $attempt, Program $program): string
    {
        $currentClearance = $attempt->latestClearanceReceipt()->first();
        if ($currentClearance?->status === AdmissionClearanceReceipt::STATUS_CONFIRMED
            && (int) $currentClearance->program_id === (int) $program->id) {
            return 'ready_for_enrollment';
        }

        $selection = AttemptProgramFacts::currentSelection($attempt);
        if ($selection !== null && $selection->program_id === $program->id) {
            return $selection->status === ProgramSelection::STATUS_ACTIVE ? 'selected' : 'selection_pending_ack';
        }

        if (AttemptProgramFacts::isAdvised($attempt)) {
            return 'advised';
        }

        return AttemptProgramFacts::isResultReleased($attempt) ? 'result_released' : 'accepted';
    }

    private function basis(AdmissionAttempt $attempt, Program $program): array
    {
        $basis = [];
        foreach (['program_preference_1_code', 'program_preference_2_code', 'program_preference_3_code'] as $i => $column) {
            if ($attempt->{$column} === $program->code) {
                $basis[] = 'preference #'.($i + 1);
            }
        }

        if (AttemptProgramFacts::currentSelection($attempt)?->program_id === $program->id) {
            $basis[] = 'current selection';
        }

        if (ProgramFollowUp::query()
            ->where('admission_attempt_id', $attempt->id)
            ->where('program_id', $program->id)
            ->exists()) {
            $basis[] = 'targeted follow-up';
        }

        $currentPool = ProgramPool::currentFor((int) $attempt->id);
        if ($currentPool?->options()->where('program_id', $program->id)->exists()) {
            $basis[] = 'current guidance pool';
        }

        return $basis;
    }

    private function academicScoreProfile(AdmissionAttempt $attempt): array
    {
        $released = AttemptProgramFacts::isResultReleased($attempt);

        if ($released) {
            $facts = $this->releasedResultDocument->forAttempt($attempt);

            return [
                'chart_id' => 'admission-support-score-profile',
                'title' => 'Released score profile',
                'unit' => 'released score scale',
                'series' => $this->scoreSeriesFromDocument($facts['document']),
                'version' => $facts['release']?->release_version,
                'released_at' => $facts['release']?->released_at?->toIso8601String(),
                'freshness' => 'current',
            ];
        }

        $document = AttemptProgramFacts::isResultReady($attempt)
            ? ResultDocument::query()
                ->whereKey($attempt->current_result_document_id)
                ->where('admission_attempt_id', $attempt->id)
                ->where('status', ResultDocument::STATUS_READY)
                ->first()
            : null;

        if ($document !== null) {
            return [
                'chart_id' => 'admission-support-score-profile',
                'title' => 'Current scored profile',
                'unit' => 'current score scale',
                'series' => $this->scoreSeriesFromDocument($document),
                'version' => null,
                'released_at' => null,
                'freshness' => 'current',
            ];
        }

        return [
            'chart_id' => 'admission-support-score-profile',
            'title' => 'Current scored profile',
            'unit' => 'current score scale',
            'series' => [],
            'version' => null,
            'released_at' => null,
            'freshness' => 'unavailable',
        ];
    }

    /**
     * @return list<array{area_code: string, area_name: string, score: mixed, scale: string, percentile: ?float}>
     */
    private function scoreSeriesFromDocument(?ResultDocument $document): array
    {
        $documentScores = is_array($document?->snapshot_payload_json['scores'] ?? null)
            ? $document->snapshot_payload_json['scores']
            : [];

        return array_values(array_map(
            static fn (array $score): array => [
                'area_code' => is_scalar($score['area_code'] ?? null) ? (string) $score['area_code'] : '',
                'area_name' => is_scalar($score['domain'] ?? null) ? (string) $score['domain'] : '',
                'score' => is_scalar($score['converted'] ?? null) ? $score['converted'] : null,
                'scale' => ($score['scale'] ?? null) === 'percentile_rank' ? 'percentile_rank' : 'converted',
                'percentile' => ($score['scale'] ?? null) === 'percentile_rank' && is_numeric($score['converted'] ?? null)
                    ? (float) $score['converted']
                    : null,
            ],
            array_values(array_filter($documentScores, 'is_array')),
        ));
    }
}
