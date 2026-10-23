<?php

namespace App\Applicant\Support;

use App\Admission\Support\ApplicantScope;
use App\Models\Admission\AdmissionAttempt;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Server-authored applicant navigation (REQ-VS-027). Rows exist only for
 * routes the applicant principal may legitimately reach; the client renders
 * the list verbatim and never gates by hiding.
 */
final class ApplicantNavigationBuilder
{
    /**
     * @return list<array{key: string, label: string, href: string, badge_key?: string}>
     */
    public function for(?User $user): array
    {
        if ($user === null || ! $user->applicantAccount()->exists()) {
            return [];
        }

        $items = [
            ['key' => 'portal', 'label' => 'Home', 'href' => route('applicant.portal')],
            ['key' => 'schedule', 'label' => 'Exam Schedule', 'href' => route('applicant.schedule')],
            ['key' => 'exam_day', 'label' => 'Exam Day', 'href' => route('applicant.exam-day')],
            ['key' => 'exam_scoring', 'label' => 'Exam Scoring', 'href' => route('applicant.exam-scoring')],
            ['key' => 'release_schedule', 'label' => 'Release Day Scheduling', 'href' => route('applicant.consultation')],
            ['key' => 'release_day', 'label' => 'Release Day', 'href' => route('applicant.release-day')],
            ['key' => 'results', 'label' => 'My Results', 'href' => route('applicant.results.show')],
            ['key' => 'program_choice', 'label' => 'Program Choice', 'href' => route('applicant.program-choice')],
            ['key' => 'clearance', 'label' => 'Admission Clearance', 'href' => route('applicant.admission-clearance')],
            ['key' => 'admission_support', 'label' => 'Messages', 'href' => route('applicant.admission-support'), 'badge_key' => 'messaging'],
            ['key' => 'access_history', 'label' => 'Privacy & access history', 'href' => route('applicant.access-history')],
        ];

        if (Route::has('notifications.index')) {
            $items[] = ['key' => 'notifications', 'label' => 'Notifications', 'href' => route('notifications.index')];
        }
        if (Route::has('applicant.qr')) {
            $items[] = ['key' => 'qr', 'label' => 'My QR', 'href' => route('applicant.qr')];
        }

        $identityId = ApplicantScope::identityIdForUser($user);

        if ($identityId !== null) {
            $attemptId = AdmissionAttempt::query()
                ->where('applicant_identity_id', $identityId)
                ->orderByDesc('id')
                ->value('id');

            if ($attemptId !== null) {
                array_splice($items, 1, 0, [[
                    'key' => 'application',
                    'label' => 'My Application',
                    'href' => route('applicant.application', ['attempt' => $attemptId]),
                ]]);
            }
        }

        return $items;
    }
}
