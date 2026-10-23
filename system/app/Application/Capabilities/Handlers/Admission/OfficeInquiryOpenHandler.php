<?php

namespace App\Application\Capabilities\Handlers\Admission;

use App\Admission\Actions\CreateApplicantOfficeInquiry;
use App\Admission\Queries\ApplicantOfficeInquiryPreflight;
use App\Ai\Proposals\ProposalReview;
use App\Ai\Proposals\ProposalViewContract;
use App\Application\Actions\ActionExecutionIdentity;
use App\Models\User;
use Illuminate\Support\Str;
use RuntimeException;

final class OfficeInquiryOpenHandler
{
    private const CAPABILITY_ID = 'admission.office_inquiries.open@1';

    public function __construct(private readonly ApplicantOfficeInquiryPreflight $preflight) {}

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

        $command = $this->preflight->open($actor, $input);
        if ($execute) {
            $receipt = CreateApplicantOfficeInquiry::handle(
                $actor, $command['office'], $command['subject'], $command['body'], $identity->executionId,
            );

            return [
                'mode' => 'execute',
                'capability_id' => self::CAPABILITY_ID,
                ...$receipt,
                'href' => route('applicant.inquiries.show', ['inquiry' => $receipt['inquiry_id']], false),
            ];
        }

        $officeLabel = $command['office'] === 'registrar' ? 'Registrar' : 'Guidance';
        $checks = [
            ['key' => 'synthetic_applicant', 'label' => 'Registered synthetic applicant', 'status' => 'pass', 'detail' => 'Synthetic applicant eligibility verified'],
            ['key' => 'own_active_attempt', 'label' => 'Own eligible application', 'status' => 'pass', 'detail' => 'Active admission application exists for applicant'],
            ['key' => 'office_available', 'label' => 'Destination office available', 'status' => 'pass', 'detail' => 'Destination office is available to receive inquiries'],
            ['key' => 'canonical_draft', 'label' => 'Exact draft validated; not sent', 'status' => 'pass', 'detail' => 'Message draft validated and ready for confirmation'],
        ];

        $result = [
            'mode' => 'propose',
            'ok' => true,
            'capability_id' => self::CAPABILITY_ID,
            'command_input' => $command,
            'impact' => [
                'action' => 'open',
                'office' => $command['office'],
                'office_label' => $officeLabel,
                'subject' => $command['subject'],
                'body' => $command['body'],
            ],
            'proposal_review' => [
                'version' => 1,
                'operation' => 'send',
                'title' => 'Send office inquiry',
                'sections' => [
                    ProposalViewContract::fieldsSection('inquiry', 'Office inquiry', [
                        ProposalViewContract::field(
                            'office',
                            'Destination office',
                            ['type' => 'missing'],
                            ['type' => 'enum', 'code' => $command['office'], 'label' => $officeLabel],
                            'choice',
                        ),
                        ProposalViewContract::field(
                            'subject',
                            'Subject',
                            ['type' => 'missing'],
                            ['type' => 'text', 'value' => $command['subject'], 'multiline' => false],
                            'choice',
                        ),
                        ProposalViewContract::field(
                            'body',
                            'Message',
                            ['type' => 'missing'],
                            ['type' => 'text', 'value' => $command['body'], 'multiline' => true],
                            'choice',
                        ),
                    ]),
                ],
                'preflight' => ProposalViewContract::preflight('pass', $checks),
                'coverage' => [
                    'office' => ['classification' => 'display', 'field' => 'office'],
                    'subject' => ['classification' => 'display', 'field' => 'subject'],
                    'body' => ['classification' => 'display', 'field' => 'body'],
                ],
            ],
            'diff' => [
                ['key' => 'office', 'label' => 'Office', 'before' => null, 'after' => $officeLabel],
                ['key' => 'subject', 'label' => 'Subject', 'before' => null, 'after' => $command['subject']],
                ['key' => 'body', 'label' => 'Message to send', 'before' => null, 'after' => $command['body']],
            ],
            'preflight' => ['checks' => $checks],
            'evidence' => ['outcome' => 'pass', 'checks' => $checks],
        ];
        ProposalReview::fromResult(self::CAPABILITY_ID, $result);

        return $result;
    }
}
