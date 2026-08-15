<?php

namespace App\Application\Capabilities\Handlers\Exams;

use App\Examination\Actions\AssignApplicantToExamSession;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Examination\ExamSession;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

final class ExamSessionAssignHandler
{
    public function __construct(private readonly AssignApplicantToExamSession $action) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function handle(array $input = []): array
    {
        $sessionId = (int) ($input['session_id'] ?? 0);
        $attemptId = (int) ($input['admission_attempt_id'] ?? 0);
        $execute = (bool) ($input['execute'] ?? false);

        $session = ExamSession::query()->find($sessionId);
        $attempt = AdmissionAttempt::query()->find($attemptId);
        if ($session === null || $attempt === null) {
            throw new RuntimeException('target_not_found');
        }

        if (! $attempt->isExamEligible()) {
            throw new RuntimeException('attempt_not_exam_eligible');
        }

        $impact = [
            'session_id' => $session->id,
            'code' => $session->code,
            'admission_attempt_id' => $attempt->id,
            'reference_code' => $attempt->reference_code,
            'action' => 'assign',
            'expected_version' => (int) $session->version,
        ];

        if (! $execute) {
            return [
                'mode' => 'propose',
                'capability_id' => 'exams.session.assign@1',
                'impact' => $impact,
                'evidence' => [
                    'outcome' => 'pass',
                    'checks' => ['exam_eligible', 'session_not_terminal_precheck'],
                ],
            ];
        }

        $actor = Auth::user();
        if (! $actor instanceof User) {
            throw new RuntimeException('unauthenticated');
        }

        $assignment = $this->action->handle($session, $attempt, $actor);

        return [
            'mode' => 'execute',
            'assignment_id' => $assignment->id,
            'session_id' => $session->id,
            'admission_attempt_id' => $attempt->id,
        ];
    }
}
