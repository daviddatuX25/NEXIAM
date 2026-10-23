<?php

namespace App\Application\Capabilities\Handlers\Admission;

use App\Admission\Actions\SendApplicantOfficeInquiryMessage;
use App\Admission\Queries\ApplicantOfficeInquiryPreflight;
use App\Admission\Support\ApplicantOfficeInquiryAccess;
use App\Ai\Proposals\ProposalReview;
use App\Ai\Proposals\ProposalViewContract;
use App\Application\Actions\ActionExecutionIdentity;
use App\Models\Admission\ApplicantOfficeInquiry;
use App\Models\User;
use Illuminate\Support\Str;
use RuntimeException;

final class OfficeInquiryReplyHandler
{
    private const CAPABILITY_ID = 'admission.office_inquiries.reply@1';

    public function __construct(
        private readonly ApplicantOfficeInquiryPreflight $preflight,
        private readonly ApplicantOfficeInquiryAccess $access,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function handle(array $input = [], ?User $actor = null): array
    {
        $execute = $input['execute'] ?? false;
        $identity = $input['_xiam_execution_identity'] ?? null;
        unset($input['execute'], $input['_xiam_execution_identity']);
        if (! is_bool($execute)
            || ($identity !== null && (! $execute || ! $identity instanceof ActionExecutionIdentity))
            || ($execute && (! $identity instanceof ActionExecutionIdentity
                || $identity->capabilityId !== self::CAPABILITY_ID || ! Str::isUuid($identity->executionId)))) {
            throw new RuntimeException('action_execution_identity_invalid');
        }
        if ($actor === null) {
            throw new RuntimeException('office_inquiry_not_found');
        }

        $this->access->applicantAttempt($actor);
        $command = $this->preflight->reply($actor, $input);
        if ($execute) {
            $receipt = SendApplicantOfficeInquiryMessage::handle(
                $actor, $command['inquiry_id'], $command['body'], $identity->executionId,
            );

            return [
                'mode' => 'execute',
                'capability_id' => self::CAPABILITY_ID,
                ...$receipt,
                'href' => route('applicant.inquiries.show', ['inquiry' => $receipt['inquiry_id']], false),
            ];
        }

        $inquiry = ApplicantOfficeInquiry::query()->select(['id', 'office', 'subject'])->find($command['inquiry_id']);
        if ($inquiry === null) {
            throw new RuntimeException('office_inquiry_not_found');
        }
        $officeLabel = $inquiry->office === 'registrar' ? 'Registrar' : 'Guidance';
        $checks = [
            ['key' => 'synthetic_applicant', 'label' => 'Registered synthetic applicant', 'status' => 'pass', 'detail' => 'Synthetic applicant eligibility verified'],
            ['key' => 'own_open_inquiry', 'label' => 'Own open office inquiry', 'status' => 'pass', 'detail' => 'Active open inquiry exists for applicant'],
            ['key' => 'canonical_draft', 'label' => 'Exact reply validated; not sent', 'status' => 'pass', 'detail' => 'Reply draft validated and ready for confirmation'],
        ];

        $inquiryLabel = $inquiry->subject ? "#{$inquiry->id}: {$inquiry->subject}" : "#{$inquiry->id}";

        $result = [
            'mode' => 'propose',
            'ok' => true,
            'capability_id' => self::CAPABILITY_ID,
            'command_input' => $command,
            'impact' => [
                'action' => 'reply',
                'inquiry_id' => $command['inquiry_id'],
                'office' => $inquiry->office,
                'office_label' => $officeLabel,
                'body' => $command['body'],
            ],
            'proposal_review' => [
                'version' => 1,
                'operation' => 'send',
                'title' => 'Reply to office inquiry',
                'sections' => [
                    ProposalViewContract::fieldsSection('reply', 'Inquiry reply', [
                        ProposalViewContract::field(
                            'inquiry',
                            'Inquiry',
                            ['type' => 'missing'],
                            ['type' => 'record', 'label' => $inquiryLabel, 'context' => ["Office: {$officeLabel}"]],
                            'context',
                        ),
                        ProposalViewContract::field(
                            'subject',
                            'Subject',
                            ['type' => 'missing'],
                            ['type' => 'text', 'value' => (string) ($inquiry->subject ?? ''), 'multiline' => false],
                            'context',
                        ),
                        ProposalViewContract::field(
                            'body',
                            'Reply message',
                            ['type' => 'missing'],
                            ['type' => 'text', 'value' => $command['body'], 'multiline' => true],
                            'choice',
                        ),
                    ]),
                ],
                'preflight' => ProposalViewContract::preflight('pass', $checks),
                'coverage' => [
                    'inquiry_id' => ['classification' => 'display', 'field' => 'inquiry'],
                    'body' => ['classification' => 'display', 'field' => 'body'],
                ],
            ],
            'diff' => [
                ['key' => 'inquiry_id', 'label' => 'Inquiry', 'before' => null, 'after' => (string) $command['inquiry_id']],
                ['key' => 'office', 'label' => 'Office', 'before' => null, 'after' => $officeLabel],
                ['key' => 'body', 'label' => 'Reply to send', 'before' => null, 'after' => $command['body']],
            ],
            'preflight' => ['checks' => $checks],
            'evidence' => ['outcome' => 'pass', 'checks' => $checks],
        ];
        ProposalReview::fromResult(self::CAPABILITY_ID, $result);

        return $result;
    }
}
