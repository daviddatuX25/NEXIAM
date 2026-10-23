<?php

namespace App\Application\Capabilities\Handlers\Assessment;

use App\Ai\Proposals\ProposalReview;
use App\Ai\Proposals\ProposalViewContract;
use App\Application\Actions\ActionExecutionIdentity;
use App\Assessment\Actions\RecordScores;
use App\Assessment\Support\ScoreRecordingEvaluator;
use App\Models\Assessment\AssessmentNormPackageVersion;
use App\Models\Assessment\TestKitVersion;
use App\Models\Examination\ExamAssignment;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use RuntimeException;

final class AssessmentScoresRecordHandler
{
    private const CAPABILITY_ID = 'assessment.scores.record_manual@1';

    public function __construct(
        private readonly ScoreRecordingEvaluator $evaluator,
        private readonly RecordScores $action,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function handle(array $input = [], ?Authenticatable $actor = null): array
    {
        $executionIdentity = $input['_xiam_execution_identity'] ?? null;
        unset($input['_xiam_execution_identity']);
        if ($executionIdentity !== null && ! $executionIdentity instanceof ActionExecutionIdentity) {
            throw new RuntimeException('action_execution_identity_invalid');
        }

        $gradingSession = GradingSession::query()->find($this->requiredInteger($input['grading_session_id'] ?? null, 'grading_session_id'));
        if (! $gradingSession instanceof GradingSession) {
            throw new RuntimeException('grading_session_not_found');
        }
        $attempt = AdmissionAttempt::query()->find($this->requiredInteger($input['attempt_id'] ?? null, 'attempt_id'));
        if (! $attempt instanceof AdmissionAttempt) {
            throw new RuntimeException('admission_attempt_not_found');
        }
        $scores = $input['scores'] ?? null;
        if (! is_array($scores)) {
            throw new RuntimeException('scores_invalid');
        }
        $assignment = ExamAssignment::query()
            ->where('exam_session_id', $gradingSession->exam_session_id)
            ->where('admission_attempt_id', $attempt->id)
            ->active()
            ->first();
        $currentAdministration = $assignment === null
            ? null
            : AssessmentAdministration::query()
                ->where('exam_assignment_id', $assignment->id)
                ->active()
                ->first();
        $testKitVersionId = isset($input['test_kit_version_id'])
            ? $this->requiredInteger($input['test_kit_version_id'], 'test_kit_version_id')
            : ($currentAdministration?->test_kit_version_id === null ? null : (int) $currentAdministration->test_kit_version_id);
        $normPackageVersionId = isset($input['norm_package_version_id'])
            ? $this->requiredInteger($input['norm_package_version_id'], 'norm_package_version_id')
            : null;
        $scoringMode = is_string($input['scoring_mode'] ?? null) ? (string) $input['scoring_mode'] : 'manual_raw';
        if (! in_array($scoringMode, ['manual_raw', 'manual_converted'], true)) {
            throw new RuntimeException('scoring_mode_invalid');
        }
        $evaluationInput = [
            'attempt_id' => (int) $attempt->id,
            'scores' => $scores,
            'test_kit_version_id' => $testKitVersionId,
            'norm_package_version_id' => $normPackageVersionId,
            'scoring_mode' => $scoringMode,
        ];

        if (! (bool) ($input['execute'] ?? false)) {
            $evaluation = $this->evaluator->evaluate($gradingSession, $evaluationInput);

            $normalizedCommandScores = array_values(array_map(static function (array $score): array {
                return array_filter([
                    'aptitude_area_id' => isset($score['aptitude_area_id']) ? (int) $score['aptitude_area_id'] : null,
                    'metric_id' => isset($score['metric_id']) ? (int) $score['metric_id'] : null,
                    'metric_code' => isset($score['metric_code']) ? (string) $score['metric_code'] : null,
                    'score' => isset($score['score']) ? $score['score'] : ($score['raw_score'] ?? null),
                    'typed_outputs' => isset($score['typed_outputs']) && is_array($score['typed_outputs']) ? $score['typed_outputs'] : null,
                    'external_provenance' => isset($score['external_provenance']) ? (string) $score['external_provenance'] : null,
                ], static fn (mixed $value): bool => $value !== null);
            }, $scores));

            $command = [
                'grading_session_id' => (int) $gradingSession->id,
                'attempt_id' => (int) $attempt->id,
                'scores' => $normalizedCommandScores,
                'test_kit_version_id' => $testKitVersionId,
                'norm_package_version_id' => $normPackageVersionId,
                'scoring_mode' => $scoringMode,
            ];

            $gradingSession->loadMissing(['examSession.academicCycle']);
            $sessionLabel = "Grading session #{$gradingSession->id}";
            $sessionContext = array_values(array_filter([
                $gradingSession->examSession?->code ? "Exam: {$gradingSession->examSession->code}" : null,
                $gradingSession->status ? 'Status: '.ucfirst($gradingSession->status) : null,
            ]));
            $sessionField = ProposalViewContract::field(
                'session',
                'Grading session',
                ['type' => 'record', 'label' => $sessionLabel, 'context' => $sessionContext],
                ['type' => 'record', 'label' => $sessionLabel, 'context' => $sessionContext],
                'context',
            );

            $attempt->loadMissing(['identity']);
            $attemptContext = array_values(array_filter([
                $attempt->identity?->fullName(),
                $attempt->preferred_program_code ? "Program: {$attempt->preferred_program_code}" : null,
            ]));
            $attemptLabel = (string) ($attempt->reference_code ?: ("Attempt #{$attempt->id}"));
            $attemptField = ProposalViewContract::field(
                'attempt',
                'Examinee',
                ['type' => 'record', 'label' => $attemptLabel, 'context' => $attemptContext],
                ['type' => 'record', 'label' => $attemptLabel, 'context' => $attemptContext],
                'context',
            );

            $kitVersion = $testKitVersionId === null
                ? null
                : TestKitVersion::query()
                    ->where('institution_id', $gradingSession->examSession?->academicCycle?->institution_id)
                    ->with('testKit:id,code,name')
                    ->find($testKitVersionId);
            $kitLabel = $kitVersion === null
                ? 'No exact Test Kit selected'
                : ($kitVersion->testKit?->name ?? $kitVersion->testKit?->code ?? 'Test Kit').' v'.$kitVersion->version_number;
            $kitField = ProposalViewContract::field(
                'test_kit_version_id',
                'Exact Test Kit version',
                $kitVersion === null ? ['type' => 'null'] : ['type' => 'record', 'label' => $kitLabel],
                $kitVersion === null ? ['type' => 'null'] : ['type' => 'record', 'label' => $kitLabel],
                'context',
            );
            $normVersion = $normPackageVersionId === null
                ? null
                : AssessmentNormPackageVersion::query()
                    ->where('status', AssessmentNormPackageVersion::STATUS_APPROVED)
                    ->whereHas('package', fn ($query) => $query
                        ->where('institution_id', $gradingSession->examSession?->academicCycle?->institution_id))
                    ->find($normPackageVersionId);
            $normField = ProposalViewContract::field(
                'norm_package_version_id',
                'Norm package version',
                ['type' => 'null'],
                $normVersion === null
                    ? ['type' => 'null']
                    : ['type' => 'record', 'label' => $normVersion->display_name.' v'.$normVersion->version_number],
                'choice',
            );

            $afterListItems = [];
            $scoreFields = [];
            foreach ($evaluation->diff as $index => $change) {
                $label = (string) ($change['label'] ?? 'Profile score');
                $after = (string) ($change['after'] ?? '');
                $before = (string) ($change['before'] ?? 'not recorded');
                $afterListItems[] = ['type' => 'text', 'value' => "{$label}: {$after}", 'multiline' => false];
                $scoreFields[] = ProposalViewContract::field(
                    (string) ($change['key'] ?? "score_{$index}"),
                    $label,
                    ['type' => 'text', 'value' => $before],
                    ['type' => 'text', 'value' => $after],
                    'choice',
                );
            }
            $scoresField = ProposalViewContract::field(
                'scores',
                'Recorded profile scores',
                ['type' => 'list', 'items' => [], 'empty_label' => 'No prior score inputs'],
                ['type' => 'list', 'items' => $afterListItems, 'empty_label' => 'No scores recorded'],
                'choice',
            );

            $checks = array_map(static function (array $c): array {
                return [
                    'key' => (string) ($c['key'] ?? 'check'),
                    'label' => (string) ($c['label'] ?? 'Check'),
                    'status' => in_array($c['status'] ?? null, ['pass', 'fail', 'warn', 'noop', 'unknown'], true) ? $c['status'] : 'unknown',
                    'detail' => isset($c['detail']) ? (string) $c['detail'] : (isset($c['note']) ? (string) $c['note'] : null),
                ];
            }, $evaluation->checks);

            $result = [
                'mode' => 'propose',
                'ok' => $evaluation->outcome === 'pass',
                'capability_id' => self::CAPABILITY_ID,
                'command_input' => $command,
                'impact' => ['grading_session_id' => (int) $gradingSession->id, 'attempt_id' => (int) $attempt->id],
                'proposal_review' => [
                    'version' => 1,
                    'operation' => 'update',
                    'title' => 'Record assessment scores',
                    'sections' => [
                        ProposalViewContract::fieldsSection('context', 'Evaluation context', [
                            $sessionField,
                            $attemptField,
                            $kitField,
                            $normField,
                            $scoresField,
                        ]),
                        ProposalViewContract::fieldsSection(
                            'scores_breakdown',
                            $scoringMode === 'manual_converted' ? 'Typed external final outputs' : 'Profile metric raw scores',
                            $scoreFields,
                        ),
                    ],
                    'preflight' => ProposalViewContract::preflight($evaluation->outcome, $checks),
                    'coverage' => [
                        'grading_session_id' => ['classification' => 'display', 'field' => 'session'],
                        'attempt_id' => ['classification' => 'display', 'field' => 'attempt'],
                        'test_kit_version_id' => ['classification' => 'choice', 'field' => 'test_kit_version_id'],
                        'norm_package_version_id' => ['classification' => 'choice', 'field' => 'norm_package_version_id'],
                        'scores' => ['classification' => 'display', 'field' => 'scores'],
                    ],
                ],
                'diff' => $evaluation->diff,
                'evidence' => ['outcome' => $evaluation->outcome, 'checks' => $checks],
            ];

            ProposalReview::fromResult(self::CAPABILITY_ID, $result);

            return $result;
        }
        if (! $actor instanceof User) {
            throw new RuntimeException('staff_actor_required');
        }
        if ($this->evaluator->evaluate($gradingSession, $evaluationInput)->outcome !== 'pass') {
            throw new RuntimeException('score_recording_preflight_failed');
        }
        $idempotencyKey = $executionIdentity instanceof ActionExecutionIdentity
            ? $executionIdentity->executionId
            : (string) ($input['idempotency_key'] ?? 'score-rec-'.$attempt->id.'-'.bin2hex(random_bytes(8)));

        $normalizedScores = array_map(static function (mixed $score): array {
            if (! is_array($score)) {
                throw new RuntimeException('scores_invalid');
            }
            if (! array_key_exists('score', $score) && array_key_exists('raw_score', $score)) {
                $score['score'] = $score['raw_score'];
            }

            return $score;
        }, $scores);

        $records = $this->action->handle(
            gradingSession: $gradingSession,
            attempt: $attempt,
            scores: $normalizedScores,
            actor: $actor,
            idempotencyKey: $idempotencyKey,
            scoringMode: $scoringMode,
            testKitVersionId: $testKitVersionId,
            normPackageVersionId: $normPackageVersionId,
        );

        return [
            'mode' => 'execute',
            'ok' => true,
            'grading_session_id' => (int) $gradingSession->id,
            'attempt_id' => (int) $attempt->id,
            'recorded_count' => count($records),
        ];
    }

    private function requiredInteger(mixed $value, string $field): int
    {
        if (! is_int($value) || $value < 1) {
            throw new RuntimeException($field.'_invalid');
        }

        return $value;
    }
}
