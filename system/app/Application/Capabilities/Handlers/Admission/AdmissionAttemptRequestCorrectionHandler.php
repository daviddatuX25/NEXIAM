<?php

namespace App\Application\Capabilities\Handlers\Admission;

use App\Admission\Actions\RequestApplicationCorrection;
use App\Admission\Support\RequestApplicationCorrectionEvaluator;
use App\Ai\Proposals\ProposalReview;
use App\Ai\Proposals\ProposalViewContract;
use App\Models\Admission\AdmissionAttempt;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use RuntimeException;

final class AdmissionAttemptRequestCorrectionHandler
{
    private const CAPABILITY_ID = 'admissions.attempt.request_correction@1';

    public function __construct(
        private readonly RequestApplicationCorrectionEvaluator $evaluator,
        private readonly RequestApplicationCorrection $action,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function handle(array $input = [], ?Authenticatable $actor = null): array
    {
        $attemptId = $input['attempt_id'] ?? null;
        if (! is_int($attemptId) || $attemptId < 1) {
            throw new RuntimeException('attempt_id_invalid');
        }

        $notes = $input['notes'] ?? null;
        if (! is_string($notes)) {
            throw new RuntimeException('notes_invalid');
        }
        $notes = trim($notes);

        $attempt = AdmissionAttempt::query()->find($attemptId);
        if (! $attempt instanceof AdmissionAttempt) {
            throw new RuntimeException('admission_attempt_not_found');
        }

        if (! (bool) ($input['execute'] ?? false)) {
            $evaluation = $this->evaluator->evaluate($attempt, $notes);
            $command = [
                'attempt_id' => (int) $attempt->id,
                'notes' => $notes,
            ];

            $attempt->loadMissing(['identity']);
            $context = [];
            if ($attempt->identity?->fullName()) {
                $context[] = $attempt->identity->fullName();
            }
            if ($attempt->preferred_program_code) {
                $context[] = 'Program: '.$attempt->preferred_program_code;
            }
            $attemptLabel = (string) ($attempt->reference_code ?: ('Attempt #'.$attempt->id));

            $notesBefore = $attempt->correction_notes !== null && $attempt->correction_notes !== ''
                ? ['type' => 'text', 'value' => (string) $attempt->correction_notes, 'multiline' => true]
                : ['type' => 'missing'];

            $result = [
                'mode' => 'propose',
                'ok' => $evaluation->outcome === 'pass',
                'capability_id' => self::CAPABILITY_ID,
                'command_input' => $command,
                'impact' => [
                    'attempt_id' => (int) $attempt->id,
                    'reference_code' => $attempt->reference_code,
                    'notes' => $notes,
                ],
                'proposal_review' => [
                    'version' => 1,
                    'operation' => 'transition',
                    'title' => 'Request applicant correction',
                    'sections' => [
                        ProposalViewContract::fieldsSection('correction', 'Correction request', [
                            ProposalViewContract::field(
                                'attempt',
                                'Application',
                                ['type' => 'record', 'label' => $attemptLabel, 'context' => $context],
                                ['type' => 'record', 'label' => $attemptLabel, 'context' => $context],
                                'context',
                            ),
                            ProposalViewContract::field(
                                'status',
                                'Status',
                                ['type' => 'enum', 'code' => $attempt->status, 'label' => ucfirst(str_replace('_', ' ', $attempt->status))],
                                ['type' => 'enum', 'code' => AdmissionAttempt::STATUS_NEEDS_CORRECTION, 'label' => 'Needs Correction'],
                                'effect',
                            ),
                            ProposalViewContract::field(
                                'notes',
                                'Correction instructions',
                                $notesBefore,
                                ['type' => 'text', 'value' => $notes, 'multiline' => true],
                                'choice',
                            ),
                        ]),
                    ],
                    'preflight' => ProposalViewContract::preflight($evaluation->outcome, $evaluation->checks),
                    'coverage' => [
                        'attempt_id' => ['classification' => 'display', 'field' => 'attempt'],
                        'notes' => ['classification' => 'display', 'field' => 'notes'],
                    ],
                ],
                'diff' => $evaluation->diff,
                'evidence' => [
                    'outcome' => $evaluation->outcome,
                    'checks' => $evaluation->checks,
                ],
            ];
            ProposalReview::fromResult(self::CAPABILITY_ID, $result);

            return $result;
        }

        if (! $actor instanceof User) {
            throw new RuntimeException('staff_actor_required');
        }

        $updated = $this->action->handle($attempt, $actor, (int) $attempt->version, $notes);

        return [
            'mode' => 'execute',
            'ok' => true,
            'attempt_id' => (int) $updated->id,
            'status' => $updated->status,
            'version' => (int) $updated->version,
        ];
    }
}
