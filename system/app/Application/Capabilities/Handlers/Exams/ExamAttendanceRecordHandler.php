<?php

namespace App\Application\Capabilities\Handlers\Exams;

use App\Ai\Proposals\ProposalReview;
use App\Ai\Proposals\ProposalViewContract;
use App\Application\Actions\ActionExecutionIdentity;
use App\Examination\Actions\RecordExamAttendance;
use App\Examination\Support\RecordExamAttendanceEvaluator;
use App\Models\Examination\ExamAssignment;
use App\Models\User;
use RuntimeException;

final class ExamAttendanceRecordHandler
{
    private const CAPABILITY_ID = 'exams.attendance.record@1';

    public function __construct(
        private readonly RecordExamAttendance $action,
        private readonly RecordExamAttendanceEvaluator $evaluator,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function handle(array $input = [], ?User $actor = null): array
    {
        $executionIdentity = $input['_xiam_execution_identity'] ?? null;
        unset($input['_xiam_execution_identity']);
        if ($executionIdentity !== null && ! $executionIdentity instanceof ActionExecutionIdentity) {
            throw new RuntimeException('action_execution_identity_invalid');
        }

        $assignmentId = $this->requiredInteger($input['assignment_id'] ?? null, 'assignment_id');
        $status = $input['status'] ?? null;
        if (! is_string($status)) {
            throw new RuntimeException('attendance_status_invalid');
        }
        $assignment = ExamAssignment::query()->with('examSession')->find($assignmentId);
        if (! $assignment instanceof ExamAssignment) {
            throw new RuntimeException('assignment_not_found');
        }

        $preflight = $this->evaluator->evaluate($assignment, $status);
        $execute = (bool) ($input['execute'] ?? false);
        if (! $execute) {
            $command = [
                'assignment_id' => (int) $assignment->id,
                'status' => (string) $status,
            ];

            $assignment->loadMissing(['examSession.room', 'admissionAttempt.identity', 'attendanceRecord']);
            $session = $assignment->examSession;

            $sessionLabel = $session ? (string) $session->code : 'Exam session';
            $sessionContext = array_values(array_filter([
                $session?->room?->name ? "Room: {$session->room->name}" : null,
                $session?->status ? 'Session status: '.ucfirst(str_replace('_', ' ', $session->status)) : null,
            ]));
            $sessionField = ProposalViewContract::field(
                'session',
                'Exam session',
                ['type' => 'record', 'label' => $sessionLabel, 'context' => $sessionContext],
                ['type' => 'record', 'label' => $sessionLabel, 'context' => $sessionContext],
                'context',
            );

            $candidateLabel = $assignment->admissionAttempt?->reference_code ?: ("Assignment #{$assignment->id}");
            $candidateContext = array_values(array_filter([
                $assignment->admissionAttempt?->identity?->fullName(),
                $assignment->seat_number ? "Seat {$assignment->seat_number}" : null,
            ]));
            $assignmentField = ProposalViewContract::field(
                'assignment',
                'Candidate assignment',
                ['type' => 'record', 'label' => $candidateLabel, 'context' => $candidateContext],
                ['type' => 'record', 'label' => $candidateLabel, 'context' => $candidateContext],
                'context',
            );

            $existingOutcome = $assignment->attendanceRecord?->outcome;
            $beforeStatus = $existingOutcome !== null
                ? ['type' => 'enum', 'code' => $existingOutcome, 'label' => ucfirst(str_replace('_', ' ', $existingOutcome))]
                : ['type' => 'null'];
            $afterStatus = ['type' => 'enum', 'code' => $status, 'label' => ucfirst(str_replace('_', ' ', $status))];
            $attendanceStatusField = ProposalViewContract::field(
                'attendance_status',
                'Attendance status',
                $beforeStatus,
                $afterStatus,
                'effect',
            );

            $currentSessionStatus = (string) ($session?->status ?? 'in_progress');
            $sessionStatusField = ProposalViewContract::field(
                'session_status',
                'Session status',
                ['type' => 'enum', 'code' => $currentSessionStatus, 'label' => ucfirst(str_replace('_', ' ', $currentSessionStatus))],
                ['type' => 'enum', 'code' => $currentSessionStatus, 'label' => ucfirst(str_replace('_', ' ', $currentSessionStatus))],
                'context',
            );

            $checks = array_map(static function (array $c): array {
                return [
                    'key' => (string) ($c['key'] ?? 'check'),
                    'label' => (string) ($c['label'] ?? 'Check'),
                    'status' => in_array($c['status'] ?? null, ['pass', 'fail', 'warn', 'noop', 'unknown'], true) ? $c['status'] : 'unknown',
                    'detail' => isset($c['detail']) ? (string) $c['detail'] : (isset($c['note']) ? (string) $c['note'] : null),
                ];
            }, $preflight->checks);

            $result = [
                'mode' => 'propose',
                'ok' => $preflight->outcome === 'pass',
                'capability_id' => self::CAPABILITY_ID,
                'command_input' => $command,
                'impact' => [
                    'assignment_id' => $assignment->id,
                    'session_id' => $session?->id,
                    'status' => $status,
                ],
                'proposal_review' => [
                    'version' => 1,
                    'operation' => 'transition',
                    'title' => 'Record exam attendance',
                    'sections' => [
                        ProposalViewContract::fieldsSection('attendance', 'Attendance verification', [
                            $sessionField,
                            $sessionStatusField,
                            $assignmentField,
                            $attendanceStatusField,
                        ]),
                    ],
                    'preflight' => ProposalViewContract::preflight($preflight->outcome, $checks),
                    'coverage' => [
                        'assignment_id' => ['classification' => 'display', 'field' => 'assignment'],
                        'status' => ['classification' => 'display', 'field' => 'attendance_status'],
                    ],
                ],
                'diff' => $preflight->diff,
                'evidence' => ['outcome' => $preflight->outcome, 'checks' => $checks],
            ];

            ProposalReview::fromResult(self::CAPABILITY_ID, $result);

            return $result;
        }

        if (! $actor instanceof User) {
            throw new RuntimeException('staff_actor_required');
        }
        if ($preflight->outcome !== 'pass') {
            throw new RuntimeException('exam_attendance_preflight_failed');
        }

        $idempotencyKey = $executionIdentity instanceof ActionExecutionIdentity
            ? $executionIdentity->executionId
            : (string) ($input['idempotency_key'] ?? 'exam-attendance-'.$assignmentId.'-'.bin2hex(random_bytes(16)));
        if ($idempotencyKey === '') {
            throw new RuntimeException('idempotency_key_invalid');
        }

        $record = $this->action->handle($assignment, $actor, $status, $idempotencyKey);

        return [
            'mode' => 'execute',
            'ok' => true,
            'assignment_id' => $assignment->id,
            'attendance_record_id' => $record->id,
            'status' => $record->outcome,
            'diff' => $preflight->diff,
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
