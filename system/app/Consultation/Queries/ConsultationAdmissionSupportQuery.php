<?php

namespace App\Consultation\Queries;

use App\Admission\Queries\AttemptRequirementChecklistQuery;
use App\Admission\Support\AttemptProgramFacts;
use App\Admission\Support\GwaScaleNormalizer;
use App\Admission\Support\QuotaReservation;
use App\AdmissionEngine\AdmissionEngineRegistry;
use App\AdmissionEngine\DTOs\AdmissionInputPayload;
use App\AdmissionEngine\DTOs\EngineEvaluationResult;
use App\AdmissionEngine\Support\BenchmarkInputSchema;
use App\AdmissionEngine\Support\PhilippineDegreeHollandMap;
use App\AdmissionEngine\Support\RiasecActivityCatalog;
use App\AdmissionEngine\Support\TabFmProvenance;
use App\Applicant\Support\ApplicantResultAccess;
use App\Consultation\Support\AttemptResultReadiness;
use App\Consultation\Support\ConsultationScoreProjection;
use App\Consultation\Support\RecordedEvaluationApplicability;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\AdmissionBenchmarkActivation;
use App\Models\Admission\AdmissionBenchmarkRevision;
use App\Models\Admission\AdmissionBenchmarkSet;
use App\Models\Admission\AdmissionSupportSnapshot;
use App\Models\Admission\ProgramFollowUp;
use App\Models\Admission\ProgramPool;
use App\Models\Admission\ProgramSelection;
use App\Models\Assessment\GradingSession;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamAttendanceRecord;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\Program;
use App\Models\Release\ConsultationDecision;
use App\Release\Queries\ReleasedResultDocumentQuery;
use Illuminate\Support\Carbon;

/**
 * workspace. It keeps authoritative consultation evidence separate from
 * advisory benchmark output; model recommendations never mutate decisions.
 */
final class ConsultationAdmissionSupportQuery
{
    public function __construct(
        private readonly ConsultationScoreProjection $scoreProjection,
        private readonly RecordedEvaluationApplicability $recordedApplicability,
        private readonly ApplicantResultAccess $applicantResultAccess,
        private readonly ReleasedResultDocumentQuery $releasedResultDocument,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function for(AdmissionAttempt $attempt): array
    {
        $requirementsChecklist = app(AttemptRequirementChecklistQuery::class)->get($attempt);
        $requirementsItems = array_map(static fn (array $item): array => [
            'id' => (int) $item['id'],
            'code' => (string) $item['code'],
            'label' => (string) $item['label'],
            'is_mandatory' => (bool) $item['is_mandatory'],
            'status' => (string) $item['latest_status'],
        ], $requirementsChecklist['items'] ?? []);
        $released = AttemptProgramFacts::isResultReleased($attempt);
        $ready = AttemptProgramFacts::isResultReady($attempt);
        $currentAssignment = ExamAssignment::query()
            ->active()
            ->where('admission_attempt_id', $attempt->id)
            ->with('examSession.gradingSession')
            ->orderByDesc('id')
            ->first();
        $gradingFinalized = $currentAssignment?->examSession?->gradingSession?->status === GradingSession::STATUS_FINALIZED;
        $analysis = [
            'status' => $ready ? 'no_analysis' : 'result_not_ready',
            'engine' => null,
            'source' => null,
            'version' => null,
            'generated_at' => null,
            'message' => $ready
                ? 'No analysis provider is configured. Consultation evidence is complete without one.'
                : 'Consultation evidence unlocks after an individual result is frozen and prepared.',
        ];
        $activation = AdmissionBenchmarkActivation::currentForCycle((int) $attempt->academic_cycle_id);
        $scoreProjection = $this->scoreProjection->forAttempt($attempt);
        $scoreProfile = ($ready || $released)
            ? ['series' => $scoreProjection['series']]
            : ['series' => []];
        $activeSet = $activation?->benchmarkSet()->with('features')->first();
        $inputSchema = $activeSet === null
            ? ['subjects' => [], 'activities' => RiasecActivityCatalog::definitions()]
            : BenchmarkInputSchema::projection($activeSet);
        $input = AdmissionInputPayload::fromAttempt(
            $attempt,
            ConsultationScoreProjection::admissionInputSeries($scoreProfile),
        );
        $snapshot = AdmissionSupportSnapshot::latestForAttempt((int) $attempt->id);
        $applicantEvidence = $this->applicantEvidence($attempt, $input, $scoreProfile);
        $initialTabFmStatus = $ready ? 'no_provider' : 'result_not_ready';
        $initialTabFmMessage = $ready
            ? 'No active benchmark set is configured for this cycle. Applicant evidence remains available without an evaluation.'
            : 'TabFM evaluation is unavailable until an individual result is frozen and prepared. Applicant evidence remains available.';
        $models = [
            [
                'slot' => 'tabfm_pairwise',
                'label' => 'Course Placement & Compatibility Triage',
                'subtitle' => 'In-context pairwise compatibility against institutional program benchmarks.',
                'status' => $initialTabFmStatus,
                'evaluated' => false,
                'current_applicable' => false,
                'source_kind' => 'active',
                'source_version' => null,
                'message' => $initialTabFmMessage,
                'applicant_evidence' => $applicantEvidence,
            ],
        ];

        $result = null;
        if ($snapshot !== null) {
            $recordedResult = $snapshot->payload;
            $capturedContext = $recordedResult['assessment_context'] ?? null;
            $recordedBackend = $recordedResult['inference_backend'] ?? null;
            $snapshotEvidenceMatches = $this->recordedApplicability->matchesCurrentEvidence(
                $attempt,
                $snapshot,
                $recordedResult,
                $scoreProjection,
            );
            $hasNeuralResult = $snapshot->engine_slot === 'tabfm_pairwise'
                && TabFmProvenance::isApprovedStoredEvaluation($recordedResult, $snapshot->engine_version);
            $snapshotApplicable = $ready && $snapshotEvidenceMatches && $hasNeuralResult;
            $evaluatedAt = $recordedResult['evaluated_at'] ?? null;
            $recordedAt = $evaluatedAt
                ?? ($snapshot->created_at === null ? null : Carbon::parse($snapshot->created_at)->toIso8601String());
            $recordedEnvelope = [
                'payload' => $recordedResult,
                'evaluated_at' => $evaluatedAt,
                'recorded_at' => $recordedAt,
                'assessment_context' => $capturedContext,
                'inference_backend' => $recordedBackend,
                'engine_version' => $snapshot->engine_version,
                'benchmark_version' => $recordedResult['benchmark_version'] ?? null,
                'snapshot_version' => (int) $snapshot->version,
            ];
            $status = $snapshotApplicable
                ? 'evaluated'
                : (! $ready ? 'result_not_ready' : 'unavailable');
            $message = $snapshotApplicable
                ? ($recordedResult['message'] ?? 'Stored decision support evaluation snapshot.')
                : (! $ready
                    ? 'TabFM evaluation is unavailable until grading is finalized and the result is prepared. Applicant evidence remains available.'
                    : 'The recorded TabFM result is stale and does not apply to the current assessment evidence. Record new supplemental evidence to evaluate the current sitting.');
            $analysis = [
                'status' => $status,
                'engine' => $snapshot->engine_slot,
                'source' => $recordedResult['benchmark_name'] ?? null,
                'version' => $recordedResult['benchmark_version'] ?? null,
                'generated_at' => $recordedAt,
                'message' => $message,
            ];
            $models = [[
                'slot' => $snapshot->engine_slot,
                'label' => 'TabFM Course Match Index',
                'subtitle' => 'In-context pairwise compatibility against institutional program benchmarks.',
                'status' => $status,
                'evaluated' => $snapshotApplicable,
                'current_applicable' => $snapshotApplicable,
                'source_kind' => 'recorded',
                'source_version' => $recordedResult['benchmark_version'] ?? null,
                'engine_version' => $snapshot->engine_version,
                'benchmark_version' => $recordedResult['benchmark_version'] ?? null,
                'benchmark_name' => $recordedResult['benchmark_name'] ?? null,
                'evaluated_at' => $recordedAt,
                'modality_coverage' => $snapshotApplicable ? ($recordedResult['modality_coverage'] ?? []) : [],
                'inference_backend' => $recordedBackend ?? 'not_reported',
                'fallback_reason' => $recordedResult['fallback_reason'] ?? null,
                'recommendations' => $snapshotApplicable && is_array($recordedResult['recommendations'] ?? null)
                    ? $this->attachQuotaHeadroom($attempt, $recordedResult['recommendations'])
                    : [],
                'applicant_evidence' => $applicantEvidence,
                'recorded_result' => $recordedEnvelope,
                'message' => $message,
            ]];
        } elseif ($ready && $activation !== null) {
            $tabfm = app(AdmissionEngineRegistry::class)->tabFmPairwise;
            if ($tabfm->canEvaluate($input)) {
                $result = $tabfm->evaluateAtActivation($input, $activation);
                $analysis = [
                    'status' => $result->status,
                    'engine' => 'tabfm_pairwise',
                    'source' => $result->benchmarkName,
                    'version' => $result->benchmarkVersion,
                    'generated_at' => $result->evaluatedAt,
                    'message' => $result->message,
                ];
                $models = [
                    [
                        'slot' => 'tabfm_pairwise',
                        'label' => 'Course Placement & Compatibility Triage',
                        'subtitle' => 'In-context pairwise compatibility against institutional program benchmarks.',
                        'status' => $result->status,
                        'evaluated' => $result->status === 'evaluated',
                        'current_applicable' => $ready
                            && $result->status === 'evaluated'
                            && $result->inferenceBackend === 'tabpfn_pytorch'
                            && $result->fallbackReason === null,
                        'source_kind' => 'active',
                        'source_version' => $result->benchmarkVersion,
                        'engine_version' => $result->engineVersion,
                        'benchmark_version' => $result->benchmarkVersion,
                        'benchmark_name' => $result->benchmarkName,
                        'evaluated_at' => $result->evaluatedAt,
                        'modality_coverage' => $result->modalityCoverage,
                        'inference_backend' => $result->inferenceBackend,
                        'fallback_reason' => $result->fallbackReason,
                        'recommendations' => $this->attachQuotaHeadroom($attempt, $result->toArray()['recommendations']),
                        'applicant_evidence' => $applicantEvidence,
                        'message' => $result->message,
                    ],
                ];
            } else {
                $models[0]['status'] = 'unavailable';
                $models[0]['message'] = 'The active benchmark for this cycle is not usable for an evaluation. Applicant evidence remains available, but no match result was produced.';
                $analysis = [
                    'status' => 'unavailable',
                    'engine' => 'tabfm_pairwise',
                    'source' => null,
                    'version' => null,
                    'generated_at' => null,
                    'message' => $models[0]['message'],
                ];
            }
        }
        $captureContext = $activeSet === null ? null : [
            'benchmark_activation_id' => $activation->id,
            'benchmark_set_id' => $activeSet->id,
            'schema_digest' => $activeSet->schema_sha256,
        ];
        foreach ($models as &$model) {
            $model['input_schema'] = $inputSchema;
            $model['evidence_context'] = $captureContext;
            $recordedContext = $snapshot?->payload['evaluation_context'] ?? null;
            $model['evaluation_context'] = $recordedContext ?? ($result instanceof EngineEvaluationResult ? $result->evaluationContext?->toArray() : null);
            $model['benchmark_context_changed'] = $snapshot !== null && $activeSet !== null
                && ((int) $snapshot->benchmark_activation_id !== (int) $activation->id || $snapshot->benchmark_hash !== $activeSet->content_sha256);
        }
        unset($model);

        return [
            'readiness' => [
                'documents_verified' => (bool) $requirementsChecklist['all_mandatory_verified'],
                'accepted' => AttemptProgramFacts::isAccepted($attempt),
                'exam_completed' => $this->examCompleted($attempt),
                'grading_finalized' => $gradingFinalized,
                'result_ready' => $ready,
                'result_released' => $released,
                'advised' => AttemptProgramFacts::isAdvised($attempt),
                'selection' => $this->selectionState($attempt),
                'enrolling' => AttemptProgramFacts::isEnrolling($attempt),
                'enrolled' => AttemptProgramFacts::isEnrolled($attempt),
            ],
            'score_profile' => ($ready || $released) ? $this->scoreProfile($attempt, $released) : null,
            'assessment_detail' => $scoreProjection['assessment_detail'] ?? null,
            'preferences' => array_values(array_filter([
                $attempt->program_preference_1_code,
                $attempt->program_preference_2_code,
                $attempt->program_preference_3_code,
            ])),
            'program_pool' => $this->poolState($attempt),
            'consultation_evidence' => $this->consultationEvidence($attempt),
            'consultation_history' => $this->consultationHistory($attempt),
            'follow_ups' => $this->followUpState($attempt),
            'analysis' => $analysis,
            'requirements_checklist' => [
                'status' => $requirementsChecklist['status'],
                'all_mandatory_verified' => (bool) $requirementsChecklist['all_mandatory_verified'],
                'mandatory_pending_count' => (int) $requirementsChecklist['mandatory_pending_count'],
                'verified_count' => count(array_filter($requirementsItems, static fn (array $item): bool => $item['status'] === 'verified')),
                'total_count' => count($requirementsItems),
                'items' => $requirementsItems,
            ],
            'applicant_evidence' => $applicantEvidence,
            'input_schema' => $inputSchema,
            'benchmark_activation_id' => $activation?->id,
            'schema_digest' => $activeSet?->schema_sha256,
            'models' => $models,
            'vocational_profile' => $applicantEvidence['vocational_profile'] ?? null,
            'as_of' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function selectionState(AdmissionAttempt $attempt): array
    {
        $selection = ProgramSelection::query()
            ->where('admission_attempt_id', $attempt->id)
            ->whereIn('status', ProgramSelection::NON_TERMINAL_STATUSES)
            ->with('program')
            ->latest('id')
            ->first();

        return [
            'status' => $selection?->status,
            'program_code' => $selection?->program?->code,
            'source' => $selection?->source,
            'acknowledged' => $selection?->applicant_acknowledged_at !== null,
        ];
    }

    private function examCompleted(AdmissionAttempt $attempt): bool
    {
        if (AttemptResultReadiness::isReady($attempt)) {
            return true;
        }

        return ExamAssignment::query()
            ->active()
            ->with(['examSession', 'submissionRecord', 'attendanceRecord'])
            ->where('admission_attempt_id', $attempt->id)
            ->get()
            ->contains(function (ExamAssignment $assignment): bool {
                if ($assignment->submissionRecord?->submitted_at !== null) {
                    return true;
                }

                if ($assignment->examSession?->status !== ExamSession::STATUS_COMPLETED) {
                    return false;
                }

                if ($assignment->attendanceRecord?->outcome !== ExamAttendanceRecord::OUTCOME_ABSENT) {
                    return true;
                }

                return GradingSession::query()
                    ->where('exam_session_id', $assignment->exam_session_id)
                    ->where('status', GradingSession::STATUS_FINALIZED)
                    ->exists();
            });
    }

    /**
     * Applicant-portal hub projection: released per-domain score series only.
     * Returns null before release; scores never serialize pre-release.
     */
    public function releasedScoreProfile(AdmissionAttempt $attempt): ?array
    {
        return AttemptProgramFacts::isResultReleased($attempt)
            ? $this->scoreProfile($attempt, released: true, portalRelease: true)
            : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function scoreProfile(AdmissionAttempt $attempt, bool $released = false, bool $portalRelease = false): array
    {
        if ($released) {
            $facts = $portalRelease
                ? $this->applicantResultAccess->forAttempt($attempt, verifyArtifact: false)
                : $this->releasedResultDocument->forAttempt($attempt);
            $document = $portalRelease && ! $facts['available'] ? null : $facts['document'];
            $documentScores = is_array($document?->snapshot_payload_json['scores'] ?? null)
                ? $document->snapshot_payload_json['scores']
                : [];
            $series = array_values(array_map(
                static fn (array $score): array => [
                    'area_code' => is_scalar($score['area_code'] ?? null) ? (string) $score['area_code'] : '',
                    'area_name' => is_scalar($score['domain'] ?? null) ? (string) $score['domain'] : '',
                    'score' => is_scalar($score['converted'] ?? null) ? $score['converted'] : null,
                    'scale' => ($score['scale'] ?? null) === 'percentile_rank' ? 'percentile_rank' : 'converted',
                ],
                array_values(array_filter($documentScores, 'is_array')),
            ));
            $release = $facts['release'];
        } else {
            $projection = $this->scoreProjection->forAttempt($attempt);
            $series = $projection['series'];
            $release = null;
        }

        return [
            'chart_id' => 'admission-support-score-profile',
            'title' => 'Released score profile',
            'unit' => 'released score scale',
            'series' => $series,
            'version' => $release?->release_version,
            'released_at' => self::isoOrNull($release?->released_at),
            'freshness' => 'current',
        ];
    }

    /**
     * Explicit Applicant Evidence block for live consultation triage display.
     *
     * @param  array<string, mixed>  $scoreProfile
     * @return array<string, mixed>
     */
    private function applicantEvidence(AdmissionAttempt $attempt, AdmissionInputPayload $input, array $scoreProfile): array
    {
        $supplemental = is_array($attempt->payload['supplemental_evidence'] ?? null)
            ? $attempt->payload['supplemental_evidence']
            : [];
        $activityIds = array_values($supplemental['vocational_activities'] ?? []);
        $reviewed = (bool) ($supplemental['activities_reviewed'] ?? false);
        $rawFlags = RiasecActivityCatalog::project($activityIds, $reviewed);
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

        $historicalSubjects = is_array($supplemental['subject_grades'] ?? null)
            ? $supplemental['subject_grades']
            : (is_array($attempt->payload['subject_grades'] ?? null) ? $attempt->payload['subject_grades'] : []);
        foreach (['math' => 'shs_math', 'sci' => 'shs_sci', 'eng' => 'shs_eng'] as $legacyKey => $featureKey) {
            if (! array_key_exists($featureKey, $historicalSubjects) && array_key_exists($legacyKey, $supplemental)) {
                $historicalSubjects[$featureKey] = $supplemental[$legacyKey];
            }
        }
        $vocationalProfile = RiasecActivityCatalog::profileFromActivities($activityIds, $reviewed);
        $programTargets = $this->resolveProgramTargets($attempt);
        $preferredProgramCode = $attempt->preferred_program_code ?? $attempt->program_preference_1_code ?? '';
        $applicantType = (string) ($attempt->applicant_type ?? 'new');

        return [
            'vocational_profile' => [
                'status' => $reviewed ? 'recorded' : 'unrecorded',
                'is_reviewed' => $reviewed,
                'scores' => $vocationalProfile['scores'],
                'counts' => $vocationalProfile['counts'],
                'emergent_code' => $vocationalProfile['emergent_code'],
                'emergent_themes' => $vocationalProfile['emergent_themes'],
                'program_targets' => $programTargets,
                'applicant_strand' => $attempt->strand,
                'applicant_type' => $applicantType,
                'preferred_program_code' => $attempt->preferred_program_code,
                'program_preference_1_code' => $attempt->program_preference_1_code,
                'strand_alignment' => PhilippineDegreeHollandMap::evaluateStrandAlignment(
                    $attempt->strand,
                    $preferredProgramCode,
                    $applicantType,
                    $programTargets[$preferredProgramCode]['priority_strands'] ?? null,
                ),
            ],
            ...(\Closure::fromCallable(static function () use ($attempt, $input): array {
                $gwaInfo = GwaScaleNormalizer::fromAttempt($attempt);

                return [
                    'gwa' => $input->gwa ?? $gwaInfo['gwa'],
                    'gwa_raw' => $gwaInfo['gwa_raw'],
                    'gwa_scale' => $gwaInfo['gwa_scale'],
                    'gwa_scale_label' => $gwaInfo['gwa_scale_label'],
                    'gwa_is_converted' => $gwaInfo['is_converted'],
                ];
            }))(),
            'gwa_band' => null,
            'strand' => $attempt->strand,
            'applicant_type' => $applicantType,
            'preferred_program_code' => $attempt->preferred_program_code,
            'program_preference_1_code' => $attempt->program_preference_1_code,
            'program_preference_2_code' => $attempt->program_preference_2_code,
            'program_preference_3_code' => $attempt->program_preference_3_code,
            'subject_grades' => $historicalSubjects,
            'has_subject_grades' => (bool) array_filter($historicalSubjects, static fn ($value): bool => $value !== null),
            'riasec_flags' => $rawFlags,
            'riasec_tags' => $activeTags,
            'has_riasec' => $reviewed || ! empty($activeTags),
            'vocational_activities' => $activityIds,
            'activities_reviewed' => $reviewed,
            'aptitude_scores' => array_values(array_filter(
                $scoreProfile['series'] ?? [],
                static fn ($area): bool => is_array($area) && (($area['score'] ?? null) !== null || ($area['raw_score'] ?? null) !== null),
            )),
            'evidence_source' => $supplemental['evidence_source'] ?? null,
            'counselor_notes' => $supplemental['counselor_notes'] ?? null,
            'score_records_count' => count(array_filter($scoreProfile['series'] ?? [], static fn ($s): bool => is_array($s) && ($s['score'] ?? null) !== null)),
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function resolveProgramTargets(AdmissionAttempt $attempt): array
    {
        $targets = PhilippineDegreeHollandMap::allTargets();
        $activation = AdmissionBenchmarkActivation::currentForCycle((int) $attempt->academic_cycle_id);
        $set = $activation?->benchmarkSet;
        if ($set === null) {
            return $targets;
        }

        $candidate = $set;
        $manifestEntries = [];
        $visited = [];
        for ($depth = 0; $candidate !== null && $depth < 20; $depth++) {
            if (isset($visited[$candidate->id])) {
                break;
            }
            $visited[$candidate->id] = true;
            $manifest = is_array($candidate->publication_manifest_json) ? $candidate->publication_manifest_json : [];
            foreach ($manifest as $progId => $entry) {
                if (is_array($entry) && isset($entry['revision_id']) && ! isset($manifestEntries[(int) $progId])) {
                    $manifestEntries[(int) $progId] = (int) $entry['revision_id'];
                }
            }
            $predecessorId = $candidate->predecessor_id;
            $candidate = $predecessorId === null
                ? null
                : AdmissionBenchmarkSet::query()
                    ->where('institution_id', $attempt->institution_id)
                    ->where('status', 'published')
                    ->find($predecessorId);
        }

        if ($manifestEntries === []) {
            return $targets;
        }

        $programs = Program::query()
            ->where('institution_id', $attempt->institution_id)
            ->whereIn('id', array_keys($manifestEntries))
            ->get()
            ->keyBy('id');

        $revisions = AdmissionBenchmarkRevision::query()
            ->whereIn('id', array_values($manifestEntries))
            ->get()
            ->keyBy('id');

        foreach ($manifestEntries as $progId => $revId) {
            $program = $programs->get($progId);
            $revision = $revisions->get($revId);
            if ($program === null || $revision === null) {
                continue;
            }
            $code = (string) $program->code;
            $authoring = $revision->content['authoring'] ?? null;
            if (is_array($authoring) && array_key_exists('preferred_strands', $authoring)) {
                $rawStrands = $authoring['preferred_strands'];
                if (is_array($rawStrands)) {
                    $strands = array_values(array_filter($rawStrands, static fn ($s): bool => is_string($s) && trim($s) !== ''));
                    $targets[$code] ??= [
                        'code' => '',
                        'name' => (string) $program->name,
                        'primary' => '',
                        'secondary' => '',
                        'cmo_reference' => null,
                        'cgp_strand_alignment' => '',
                        'priority_strands' => [],
                        'domain_focus' => null,
                        'vector' => ['riasec_r' => 0, 'riasec_i' => 0, 'riasec_a' => 0, 'riasec_s' => 0, 'riasec_e' => 0, 'riasec_c' => 0],
                    ];
                    $targets[$code]['priority_strands'] = $strands;
                    $targets[$code]['cgp_strand_alignment'] = $strands !== [] ? implode(' / ', $strands) : 'Open feeder track';
                }
            }
        }

        return $targets;
    }

    /**
     * @return array<string, mixed>
     */
    private function consultationEvidence(AdmissionAttempt $attempt): array
    {
        $decision = ConsultationDecision::query()
            ->where('admission_attempt_id', $attempt->id)
            ->latest('id')
            ->first();

        return [
            'decision_type' => $decision?->decision_type,
            'notes' => $decision?->notes,
            'recorded_by' => $decision?->counselor?->name,
            'recorded_at' => self::isoOrNull($decision?->created_at),
            'version' => $decision?->version,
        ];
    }

    /**
     * @return list<array{decision_type: string, notes: ?string, recorded_by: ?string, recorded_at: ?string, version: int}>
     */
    private function consultationHistory(AdmissionAttempt $attempt): array
    {
        return ConsultationDecision::query()
            ->where('admission_attempt_id', $attempt->id)
            ->with('counselor:id,name')
            ->orderBy('version')
            ->orderBy('id')
            ->get(['id', 'decision_type', 'notes', 'counselor_user_id', 'created_at', 'version'])
            ->map(fn (ConsultationDecision $entry): array => [
                'decision_type' => $entry->decision_type,
                'notes' => $entry->notes,
                'recorded_by' => $entry->counselor?->name,
                'recorded_at' => self::isoOrNull($entry->created_at),
                'version' => $entry->version,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function poolState(AdmissionAttempt $attempt): array
    {
        $pool = ProgramPool::currentFor($attempt->id);

        if ($pool === null) {
            return ['version' => null, 'options' => [], 'default' => null];
        }

        $selection = ProgramSelection::query()
            ->where('admission_attempt_id', $attempt->id)
            ->orderByDesc('id')
            ->first();

        return [
            'version' => $pool->version,
            'options' => $pool->options()->with('program')->get()->map(fn ($option) => [
                'program_code' => $option->program?->code ?? '',
                'program_name' => $option->program?->name ?? '',
                'basis' => $option->basis,
                'display_order' => $option->display_order,
            ])->all(),
            'default' => $selection?->source === ProgramSelection::SOURCE_GUIDANCE_DEFAULT
                ? $selection->program?->code
                : null,
            'selection_status' => $selection?->status,
            'selection_program' => $selection?->program?->code,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function followUpState(AdmissionAttempt $attempt): array
    {
        $rows = [];
        $followUps = ProgramFollowUp::query()
            ->where('admission_attempt_id', $attempt->id)
            ->with('program')
            ->orderByDesc('id')
            ->get();

        foreach ($followUps as $followUp) {
            $rows[] = [
                'program_code' => $followUp->program?->code,
                'status' => $followUp->status,
                'reason' => $followUp->reason,
                'resolution_reason' => $followUp->resolution_reason,
                'opened_at' => self::isoOrNull($followUp->opened_at),
                'resolved_at' => self::isoOrNull($followUp->resolved_at),
            ];
        }

        return $rows;
    }

    private static function isoOrNull(mixed $value): ?string
    {
        return $value === null ? null : Carbon::parse($value)->toIso8601String();
    }

    /**
     * @param  list<array<string, mixed>>  $recommendations
     * @return list<array<string, mixed>>
     */
    private function attachQuotaHeadroom(AdmissionAttempt $attempt, array $recommendations): array
    {
        $codes = array_values(array_filter(array_column($recommendations, 'program_code')));
        if ($codes === []) {
            return $recommendations;
        }

        $programs = Program::query()
            ->where('institution_id', $attempt->institution_id)
            ->whereIn('code', $codes)
            ->get()
            ->keyBy('code');

        foreach ($recommendations as &$rec) {
            $code = $rec['program_code'] ?? null;
            $program = $code ? $programs->get($code) : null;
            if ($program === null) {
                $rec['quota'] = [
                    'configured' => false,
                    'capacity' => 0,
                    'committed' => 0,
                    'remaining' => 0,
                    'status' => 'unconfigured',
                    'status_label' => 'No Quota Set',
                ];

                continue;
            }

            $snapshot = QuotaReservation::varianceSnapshot($attempt, (int) $program->id);
            $configured = (bool) $snapshot['configured'];
            $remaining = (int) $snapshot['remaining'];

            $status = ! $configured ? 'unconfigured' : ($remaining === 0 ? 'full' : ($remaining <= 5 ? 'limited' : 'available'));
            $statusLabel = ! $configured
                ? 'No Quota Set'
                : ($remaining === 0
                    ? 'Capacity Full'
                    : "{$remaining} slots left");

            $rec['quota'] = [
                'configured' => $configured,
                'capacity' => (int) $snapshot['capacity'],
                'committed' => (int) $snapshot['committed'],
                'remaining' => $remaining,
                'over_capacity' => (int) $snapshot['over_capacity'],
                'status' => $status,
                'status_label' => $statusLabel,
            ];
        }
        unset($rec);

        return $recommendations;
    }
}
