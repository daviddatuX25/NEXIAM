<?php

namespace App\Application\Capabilities\Handlers\Exams;

use App\Admission\Support\ApplicantScope;
use App\Examination\Actions\ReserveExamSlot;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Examination\ExamSession;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * exams.slot.reserve@1 — OWN-scope propose/execute for the applicant slot skill
 * (Slice 9). Propose never writes; confirm (execute) calls ReserveExamSlot with
 * the same guards + idempotency as /applicant/schedule/reserve.
 */
final class ExamSlotReserveHandler
{
    public function __construct(private readonly ReserveExamSlot $action) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function handle(array $input = []): array
    {
        $actor = Auth::user();
        if (! $actor instanceof User) {
            return ['mode' => 'denied', 'reason' => 'unauthenticated'];
        }

        $sessionId = (int) ($input['exam_session_id'] ?? 0);
        $attemptId = (int) ($input['admission_attempt_id'] ?? 0);

        $session = ExamSession::query()->find($sessionId);
        $attempt = AdmissionAttempt::query()->find($attemptId);

        if ($session === null || $attempt === null) {
            return ['mode' => 'denied', 'reason' => 'target_not_found'];
        }

        if (! ApplicantScope::attemptBelongsToUser($attempt, $actor)) {
            return ['mode' => 'denied', 'reason' => 'not_own_attempt'];
        }

        $impact = [
            'exam_session_id' => $session->id,
            'code' => $session->code,
            'admission_attempt_id' => $attempt->id,
            'reference_code' => $attempt->reference_code,
            'action' => 'reserve_slot',
            'idempotency_key' => isset($input['idempotency_key']) && is_string($input['idempotency_key'])
                ? $input['idempotency_key']
                : null,
        ];

        if (! ($input['execute'] ?? false)) {
            return [
                'mode' => 'propose',
                'capability_id' => 'exams.slot.reserve@1',
                'impact' => $impact,
                'evidence' => [
                    'outcome' => 'pass',
                    'checks' => ['own_attempt', 'session_open_precheck'],
                ],
            ];
        }

        $assignment = $this->action->handle(
            $session,
            $attempt,
            $actor,
            $impact['idempotency_key'],
        );

        return [
            'mode' => 'execute',
            'assignment_id' => $assignment->id,
            'exam_session_id' => $session->id,
            'admission_attempt_id' => $attempt->id,
            'idempotency_key' => $impact['idempotency_key'],
        ];
    }
}
