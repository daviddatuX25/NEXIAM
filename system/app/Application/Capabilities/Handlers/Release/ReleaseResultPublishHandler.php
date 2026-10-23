<?php

namespace App\Application\Capabilities\Handlers\Release;

use App\Ai\Proposals\ProposalReview;
use App\Ai\Proposals\ProposalViewContract;
use App\Application\Actions\ActionExecutionIdentity;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Release\ResultDocument;
use App\Models\User;
use App\Release\Actions\DeliverResultAction;
use App\Release\Support\ResultReleaseEvaluator;
use Illuminate\Contracts\Auth\Authenticatable;
use RuntimeException;

final class ReleaseResultPublishHandler
{
    private const CAPABILITY_ID = 'release.result.publish@2';

    public function __construct(
        private readonly ResultReleaseEvaluator $evaluator,
        private readonly DeliverResultAction $delivery,
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

        $attemptId = $this->requiredInteger($input['attempt_id'] ?? null, 'attempt_id');
        $documentId = $this->requiredInteger($input['document_id'] ?? null, 'document_id');
        if (($input['channel'] ?? null) !== 'portal') {
            throw new RuntimeException('release_channel_invalid');
        }

        $attempt = AdmissionAttempt::query()->find($attemptId);
        $document = ResultDocument::query()->find($documentId);
        if (! $attempt instanceof AdmissionAttempt) {
            throw new RuntimeException('admission_attempt_not_found');
        }
        if (! $document instanceof ResultDocument || (int) $document->admission_attempt_id !== (int) $attempt->id) {
            throw new RuntimeException('result_document_not_found');
        }

        $evaluation = $this->evaluator->evaluate($attempt);
        if ($evaluation->outcome !== 'pass') {
            if (! (bool) ($input['execute'] ?? false)) {
                return [
                    'mode' => 'denied',
                    'reason' => 'result_release_preflight_failed',
                    'evidence' => ['outcome' => $evaluation->outcome, 'checks' => $evaluation->checks],
                ];
            }

            throw new RuntimeException('result_release_preflight_failed');
        }

        if (! (bool) ($input['execute'] ?? false)) {
            $applicant = $attempt->identity;
            $name = $applicant?->fullName();
            $applicantName = ($name !== null && trim($name) !== '')
                ? $name
                : ($attempt->contact_email ?: "Attempt #{$attempt->id}");
            $applicationNo = $attempt->reference_code ?: (string) $attempt->id;
            $applicantEmail = $applicant?->email ?: $attempt->contact_email;
            $recipientContext = array_values(array_filter([
                "Application: {$applicationNo}",
                $applicantEmail ? "Email: {$applicantEmail}" : null,
            ]));

            $documentLabel = "Document #{$document->id}";
            $documentContext = [
                "Version: {$document->version}",
                "Status: {$document->status}",
            ];

            $commandInput = [
                'attempt_id' => (int) $attempt->id,
                'document_id' => (int) $document->id,
                'channel' => 'portal',
            ];

            $fields = [
                ProposalViewContract::field(
                    'recipient',
                    'Recipient',
                    ['type' => 'missing'],
                    [
                        'type' => 'record',
                        'label' => $applicantName,
                        'context' => $recipientContext,
                    ],
                    'context',
                ),
                ProposalViewContract::field(
                    'document',
                    'Result document',
                    ['type' => 'missing'],
                    [
                        'type' => 'record',
                        'label' => $documentLabel,
                        'context' => $documentContext,
                    ],
                    'context',
                ),
                ProposalViewContract::field(
                    'channel',
                    'Delivery channel',
                    ['type' => 'missing'],
                    [
                        'type' => 'enum',
                        'code' => 'portal',
                        'label' => 'Applicant Portal',
                    ],
                    'choice',
                ),
                ProposalViewContract::field(
                    'release_status',
                    'Release status',
                    [
                        'type' => 'enum',
                        'code' => 'unreleased',
                        'label' => 'Unreleased',
                    ],
                    [
                        'type' => 'enum',
                        'code' => 'released',
                        'label' => 'Released to portal',
                    ],
                    'effect',
                ),
            ];

            $result = [
                'mode' => 'propose',
                'ok' => true,
                'capability_id' => self::CAPABILITY_ID,
                'command_input' => $commandInput,
                'impact' => [
                    'attempt_id' => (int) $attempt->id,
                    'document_id' => (int) $document->id,
                    'channel' => 'portal',
                ],
                'proposal_review' => [
                    'version' => 1,
                    'operation' => 'send',
                    'title' => 'Release examination result',
                    'sections' => [
                        ProposalViewContract::fieldsSection('delivery', 'Result delivery', $fields),
                    ],
                    'preflight' => ProposalViewContract::preflight($evaluation->outcome, $evaluation->checks),
                    'coverage' => [
                        'attempt_id' => ['classification' => 'display', 'field' => 'recipient'],
                        'document_id' => ['classification' => 'display', 'field' => 'document'],
                        'channel' => ['classification' => 'display', 'field' => 'channel'],
                    ],
                ],
                'diff' => $evaluation->diff,
                'evidence' => ['outcome' => $evaluation->outcome, 'checks' => $evaluation->checks],
            ];

            ProposalReview::fromResult(self::CAPABILITY_ID, $result);

            return $result;
        }

        if (! $actor instanceof User) {
            throw new RuntimeException('staff_actor_required');
        }

        if ($executionIdentity instanceof ActionExecutionIdentity) {
            $explicitKey = $input['idempotency_key'] ?? null;
            if ($explicitKey !== null && (! is_string($explicitKey) || $explicitKey === '' || $explicitKey !== $executionIdentity->executionId)) {
                throw new RuntimeException('idempotency_key_invalid');
            }
            $idempotencyKey = $executionIdentity->executionId;
        } else {
            $idempotencyKey = $input['idempotency_key'] ?? null;
        }

        if (! is_string($idempotencyKey) || $idempotencyKey === '') {
            throw new RuntimeException('idempotency_key_invalid');
        }

        $release = $this->delivery->issuePortal($attempt, $document, $actor, $idempotencyKey);

        return [
            'mode' => 'execute',
            'ok' => true,
            'attempt_id' => (int) $attempt->id,
            'document_id' => (int) $document->id,
            'release_id' => (int) $release->id,
            'release_version' => (int) $release->release_version,
            'channel' => 'portal',
            'status' => 'issued',
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
