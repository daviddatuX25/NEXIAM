<?php

namespace App\Application\Capabilities\Handlers\Setup;

use App\Ai\Proposals\ProposalReview;
use App\Ai\Proposals\ProposalViewContract;
use App\Models\Foundation\AcademicCycle;
use App\Models\User;
use App\Notifications\Policies\ExamNotificationPolicy;
use App\Setup\Actions\UpdateExamNotificationPolicy;
use Illuminate\Contracts\Auth\Authenticatable;
use RuntimeException;

final class SetupNotificationExamHandler
{
    private const CAPABILITY_ID = 'setup.notifications.update_exam@1';

    public function __construct(
        private readonly UpdateExamNotificationPolicy $action,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function handle(array $input = [], ?Authenticatable $actor = null): array
    {
        $cycleId = $input['academic_cycle_id'] ?? null;
        $expectedVersion = $input['expected_version'] ?? null;
        $publicationEnabled = $input['publication_email_enabled'] ?? null;
        $reminderEnabled = $input['reminder_email_enabled'] ?? null;
        $reminderOffsets = $input['reminder_offsets_minutes'] ?? null;
        if (! is_int($cycleId) || $cycleId < 1) {
            throw new RuntimeException('academic_cycle_id_invalid');
        }
        if (! is_int($expectedVersion) || $expectedVersion < 0) {
            throw new RuntimeException('expected_version_invalid');
        }
        if (! is_bool($publicationEnabled) || ! is_bool($reminderEnabled)) {
            throw new RuntimeException('exam_notification_policy_invalid');
        }
        if (! is_array($reminderOffsets) || ! array_is_list($reminderOffsets)) {
            throw new RuntimeException('reminder_offsets_minutes_invalid');
        }
        foreach ($reminderOffsets as $offset) {
            if (! is_int($offset)) {
                throw new RuntimeException('reminder_offsets_minutes_invalid');
            }
        }

        $cycle = AcademicCycle::query()->find($cycleId);
        if (! $cycle instanceof AcademicCycle) {
            throw new RuntimeException('academic_cycle_not_found');
        }

        $current = ExamNotificationPolicy::fromStorage($cycle->exam_notification_policy);
        $values = [
            'expected_version' => $expectedVersion,
            'publication_email_enabled' => $publicationEnabled,
            'reminder_email_enabled' => $reminderEnabled,
            'reminder_offsets_minutes' => $reminderOffsets,
        ];
        if (! (bool) ($input['execute'] ?? false)) {
            $command = [
                'academic_cycle_id' => (int) $cycle->id,
                'expected_version' => $expectedVersion,
                'publication_email_enabled' => $publicationEnabled,
                'reminder_email_enabled' => $reminderEnabled,
                'reminder_offsets_minutes' => array_values($reminderOffsets),
            ];

            $cycleRecord = [
                'type' => 'record',
                'label' => (string) $cycle->code,
                'context' => array_values(array_filter([(string) $cycle->staffLabel()])),
            ];

            $beforeOffsets = [
                'type' => 'list',
                'items' => array_map(static fn (int $mins): array => [
                    'type' => 'duration',
                    'value' => $mins,
                    'unit' => 'minute',
                ], $current->reminderOffsetsMinutes),
                'empty_label' => 'No reminder offsets configured',
            ];

            $afterOffsets = [
                'type' => 'list',
                'items' => array_map(static fn (int $mins): array => [
                    'type' => 'duration',
                    'value' => $mins,
                    'unit' => 'minute',
                ], $reminderOffsets),
                'empty_label' => 'No reminder offsets configured',
            ];

            $fields = [
                ProposalViewContract::field('cycle', 'Academic cycle', $cycleRecord, $cycleRecord, 'context'),
                ProposalViewContract::field(
                    'publication_email_enabled',
                    'Publication email',
                    ['type' => 'boolean', 'value' => (bool) $current->publicationEmailEnabled, 'true_label' => 'Enabled', 'false_label' => 'Disabled'],
                    ['type' => 'boolean', 'value' => (bool) $publicationEnabled, 'true_label' => 'Enabled', 'false_label' => 'Disabled'],
                    'choice',
                ),
                ProposalViewContract::field(
                    'reminder_email_enabled',
                    'Reminder email',
                    ['type' => 'boolean', 'value' => (bool) $current->reminderEmailEnabled, 'true_label' => 'Enabled', 'false_label' => 'Disabled'],
                    ['type' => 'boolean', 'value' => (bool) $reminderEnabled, 'true_label' => 'Enabled', 'false_label' => 'Disabled'],
                    'choice',
                ),
                ProposalViewContract::field('reminder_offsets_minutes', 'Reminder offsets', $beforeOffsets, $afterOffsets, 'choice'),
            ];

            $versionMatches = $current->version === $expectedVersion;
            $checks = [
                [
                    'key' => 'version_match',
                    'label' => 'Policy version current',
                    'status' => $versionMatches ? 'pass' : 'fail',
                    'detail' => $versionMatches ? 'Exam notification policy version matches' : 'Exam notification policy version has changed',
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
                    'title' => 'Update exam notifications',
                    'sections' => [
                        ProposalViewContract::fieldsSection('exam_notifications', 'Exam notification policy', $fields),
                    ],
                    'preflight' => ProposalViewContract::preflight($outcome, $checks),
                    'coverage' => [
                        'academic_cycle_id' => ['classification' => 'display', 'field' => 'cycle'],
                        'expected_version' => ['classification' => 'internal', 'reason' => 'Optimistic locking concurrency guard'],
                        'publication_email_enabled' => ['classification' => 'display', 'field' => 'publication_email_enabled'],
                        'reminder_email_enabled' => ['classification' => 'display', 'field' => 'reminder_email_enabled'],
                        'reminder_offsets_minutes' => ['classification' => 'display', 'field' => 'reminder_offsets_minutes'],
                    ],
                ],
                'diff' => [
                    [
                        'key' => 'publication_email_enabled',
                        'label' => 'Publication email',
                        'before' => $current->publicationEmailEnabled ? 'Enabled' : 'Disabled',
                        'after' => $publicationEnabled ? 'Enabled' : 'Disabled',
                    ],
                    [
                        'key' => 'reminder_email_enabled',
                        'label' => 'Reminder email',
                        'before' => $current->reminderEmailEnabled ? 'Enabled' : 'Disabled',
                        'after' => $reminderEnabled ? 'Enabled' : 'Disabled',
                    ],
                    [
                        'key' => 'reminder_offsets_minutes',
                        'label' => 'Reminder offsets (minutes)',
                        'before' => implode(', ', $current->reminderOffsetsMinutes),
                        'after' => implode(', ', $reminderOffsets),
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
            'version' => ExamNotificationPolicy::fromStorage($updatedCycle->exam_notification_policy)->version,
        ];
    }
}
