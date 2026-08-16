<?php

namespace App\Applicant\Support;

use App\Admission\Support\ApplicantScope;
use App\Models\Admission\AdmissionAttempt;
use App\Models\User;

/**
 * Server-authored applicant navigation (REQ-VS-027). Rows exist only for
 * routes the applicant principal may legitimately reach; the client renders
 * the list verbatim and never gates by hiding.
 */
final class ApplicantNavigationBuilder
{
    /**
     * @return list<array{key: string, label: string, href: string}>
     */
    public function for(?User $user): array
    {
        if ($user === null || ! $user->applicantAccount()->exists()) {
            return [];
        }

        $items = [
            ['key' => 'portal', 'label' => 'Home', 'href' => route('applicant.portal')],
            ['key' => 'schedule', 'label' => 'Exam schedule', 'href' => route('applicant.schedule')],
            ['key' => 'consultation', 'label' => 'Consultation', 'href' => route('applicant.consultation')],
            ['key' => 'program_choice', 'label' => 'Program choice', 'href' => route('applicant.program-choice')],
            ['key' => 'notifications', 'label' => 'Notifications', 'href' => route('notifications.index')],
            ['key' => 'qr', 'label' => 'My QR', 'href' => route('applicant.qr')],
            ['key' => 'admission_support', 'label' => 'Admission support', 'href' => route('applicant.admission-support')],
        ];

        $identityId = ApplicantScope::identityIdForUser($user);

        if ($identityId !== null) {
            $attemptId = AdmissionAttempt::query()
                ->where('applicant_identity_id', $identityId)
                ->orderByDesc('id')
                ->value('id');

            if ($attemptId !== null) {
                array_splice($items, 1, 0, [[
                    'key' => 'application',
                    'label' => 'My application',
                    'href' => route('applicant.application', ['attempt' => $attemptId]),
                ]]);
            }
        }

        return $items;
    }
}
