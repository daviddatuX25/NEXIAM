<?php

namespace App\Setup\Definitions;

use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Notifications\Policies\AdmissionNotificationPolicy;
use App\Notifications\Policies\ExamNotificationPolicy;
use InvalidArgumentException;

final class NotificationsDefinition extends AbstractSetupDomainDefinition
{
    protected const MODEL = AcademicCycle::class;

    protected const KEY = 'notifications';

    protected const STAGE = 1;

    protected const STAGE_TITLE = 'Institutional Information & Academic Core';

    protected const LABEL = 'Email & Notifications';

    protected const DESCRIPTION = 'System delivery channels, office notice policies, and recent dispatch activity.';

    protected const ICON = 'BellRing';

    protected const HREF = '/setup/notifications';

    protected const CAPABILITY = 'notifications';

    protected const DESK = 'admin';

    public function summaryMetric(Institution $institution): string
    {
        $cycle = AcademicCycle::query()
            ->where('institution_id', $institution->getKey())
            ->where('is_active', true)
            ->first();

        if ($cycle === null) {
            return 'Current policy unavailable';
        }

        try {
            AdmissionNotificationPolicy::fromStorage($cycle->admission_notification_policy);
            ExamNotificationPolicy::fromStorage($cycle->exam_notification_policy);
        } catch (InvalidArgumentException) {
            return 'Current policy unavailable';
        }

        return $cycle->admission_notification_policy === null && $cycle->exam_notification_policy === null
            ? 'Using package defaults'
            : 'Current policies customized';
    }

    public function checks(Institution $institution): array
    {
        $cycle = AcademicCycle::query()
            ->where('institution_id', $institution->getKey())
            ->where('is_active', true)
            ->first();

        if ($cycle === null) {
            return [[
                'label' => 'Current academic-year notification policies are available',
                'passed' => false,
                'severity' => 'critical',
                'detail' => 'No active academic year is available for this institution.',
                'action_href' => self::HREF,
                'action_label' => 'Review notification setup',
            ]];
        }

        try {
            AdmissionNotificationPolicy::fromStorage($cycle->admission_notification_policy);
            ExamNotificationPolicy::fromStorage($cycle->exam_notification_policy);
        } catch (InvalidArgumentException $exception) {
            return [[
                'label' => 'Current academic-year notification policies are valid',
                'passed' => false,
                'severity' => 'critical',
                'detail' => $exception->getMessage(),
                'action_href' => self::HREF,
                'action_label' => 'Review notification setup',
            ]];
        }

        $usesDefaults = $cycle->admission_notification_policy === null
            && $cycle->exam_notification_policy === null;

        return [[
            'label' => 'Current academic-year notification policies are valid',
            'passed' => true,
            'severity' => 'critical',
            'detail' => $usesDefaults
                ? "Academic year ({$cycle->staffLabel()}) uses valid package defaults."
                : "Academic year ({$cycle->staffLabel()}) has valid saved notification policies.",
            'action_href' => null,
            'action_label' => null,
        ]];
    }
}
