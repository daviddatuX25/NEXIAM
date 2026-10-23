<?php

namespace App\Application\Capabilities\Handlers\Setup;

use App\Ai\Proposals\ProposalReview;
use App\Ai\Proposals\ProposalViewContract;
use App\Models\Foundation\AcademicCycle;
use App\Models\User;
use App\Notifications\Policies\AdmissionNotificationPolicy;
use App\Setup\Actions\UpdateAdmissionNotificationPolicy;
use Illuminate\Contracts\Auth\Authenticatable;
use RuntimeException;

final class SetupNotificationAdmissionHandler
{
    private const CAPABILITY_ID = 'setup.notifications.update_admission@1';

    public function __construct(
        private readonly UpdateAdmissionNotificationPolicy $action,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function handle(array $input = [], ?Authenticatable $actor = null): array
    {
        $cycleId = $input['academic_cycle_id'] ?? null;
        $expectedVersion = $input['expected_version'] ?? null;
        $applicantStatusEnabled = $input['applicant_status_email_enabled'] ?? null;
        $registrarAlertEnabled = $input['registrar_new_submission_alert_enabled'] ?? null;
        if (! is_int($cycleId) || $cycleId < 1) {
            throw new RuntimeException('academic_cycle_id_invalid');
        }
        if (! is_int($expectedVersion) || $expectedVersion < 0) {
            throw new RuntimeException('expected_version_invalid');
        }
        if (! is_bool($applicantStatusEnabled) || ! is_bool($registrarAlertEnabled)) {
            throw new RuntimeException('admission_notification_policy_invalid');
        }

        $cycle = AcademicCycle::query()->find($cycleId);
        if (! $cycle instanceof AcademicCycle) {
            throw new RuntimeException('academic_cycle_not_found');
        }

        $current = AdmissionNotificationPolicy::fromStorage($cycle->admission_notification_policy);
        $values = [
            'expected_version' => $expectedVersion,
            'applicant_status_email_enabled' => $applicantStatusEnabled,
            'registrar_new_submission_alert_enabled' => $registrarAlertEnabled,
        ];
        if (! (bool) ($input['execute'] ?? false)) {
            $command = [
                'academic_cycle_id' => (int) $cycle->id,
                'expected_version' => $expectedVersion,
                'applicant_status_email_enabled' => $applicantStatusEnabled,
                'registrar_new_submission_alert_enabled' => $registrarAlertEnabled,
            ];

            $cycleRecord = [
                'type' => 'record',
                'label' => (string) $cycle->code,
                'context' => array_values(array_filter([(string) $cycle->staffLabel()])),
            ];

            $fields = [
                ProposalViewContract::field('cycle', 'Academic cycle', $cycleRecord, $cycleRecord, 'context'),
                ProposalViewContract::field(
                    'applicant_status_email_enabled',
                    'Applicant status email',
                    ['type' => 'boolean', 'value' => (bool) $current->applicantStatusEmailEnabled, 'true_label' => 'Enabled', 'false_label' => 'Disabled'],
                    ['type' => 'boolean', 'value' => (bool) $applicantStatusEnabled, 'true_label' => 'Enabled', 'false_label' => 'Disabled'],
                    'choice',
                ),
                ProposalViewContract::field(
                    'registrar_new_submission_alert_enabled',
                    'Registrar new submission alert',
                    ['type' => 'boolean', 'value' => (bool) $current->registrarNewSubmissionAlertEnabled, 'true_label' => 'Enabled', 'false_label' => 'Disabled'],
                    ['type' => 'boolean', 'value' => (bool) $registrarAlertEnabled, 'true_label' => 'Enabled', 'false_label' => 'Disabled'],
                    'choice',
                ),
            ];

            $versionMatches = $current->version === $expectedVersion;
            $checks = [
                [
                    'key' => 'version_match',
                    'label' => 'Policy version current',
                    'status' => $versionMatches ? 'pass' : 'fail',
                    'detail' => $versionMatches ? 'Admission notification policy version matches' : 'Admission notification policy version has changed',
                ],
            ];
            $outcome = $versionMatches ? 'pass' : 'fail';

            $result = [
                'mode' => 'propose',
                'ok' => $outcome === 'pass',
                'capability_id' => self::CAPABILITY_ID,
                'command_input' => $command,
                'impact' => ['academic_cycle_id' => (int) $cycle->id],
                'proposal_review' => [
                    'version' => 1,
                    'operation' => 'update',
                    'title' => 'Update admission notifications',
                    'sections' => [
                        ProposalViewContract::fieldsSection('admission_notifications', 'Admission notification policy', $fields),
                    ],
                    'preflight' => ProposalViewContract::preflight($outcome, $checks),
                    'coverage' => [
                        'academic_cycle_id' => ['classification' => 'display', 'field' => 'cycle'],
                        'expected_version' => ['classification' => 'internal', 'reason' => 'Optimistic locking concurrency guard'],
                        'applicant_status_email_enabled' => ['classification' => 'display', 'field' => 'applicant_status_email_enabled'],
                        'registrar_new_submission_alert_enabled' => ['classification' => 'display', 'field' => 'registrar_new_submission_alert_enabled'],
                    ],
                ],
                'diff' => [
                    [
                        'key' => 'applicant_status_email_enabled',
                        'label' => 'Applicant status email',
                        'before' => $current->applicantStatusEmailEnabled ? 'Enabled' : 'Disabled',
                        'after' => $applicantStatusEnabled ? 'Enabled' : 'Disabled',
                    ],
                    [
                        'key' => 'registrar_new_submission_alert_enabled',
                        'label' => 'Registrar new submission alert',
                        'before' => $current->registrarNewSubmissionAlertEnabled ? 'Enabled' : 'Disabled',
                        'after' => $registrarAlertEnabled ? 'Enabled' : 'Disabled',
                    ],
                ],
                'evidence' => ['outcome' => $outcome, 'checks' => $checks],
            ];
            ProposalReview::fromResult(self::CAPABILITY_ID, $result);

            return $result;
        }

        if (! $actor instanceof User) {
            throw new RuntimeException('staff_actor_required');
        }

        $updatedCycle = $this->action->handle($cycle, $actor, $values);

        return [
            'mode' => 'execute',
            'ok' => true,
            'academic_cycle_id' => (int) $updatedCycle->id,
            'version' => AdmissionNotificationPolicy::fromStorage($updatedCycle->admission_notification_policy)->version,
        ];
    }
}
