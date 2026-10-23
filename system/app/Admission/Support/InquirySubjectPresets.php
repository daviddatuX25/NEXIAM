<?php

declare(strict_types=1);

namespace App\Admission\Support;

use App\Models\Admission\ApplicantOfficeInquiry;

final class InquirySubjectPresets
{
    /**
     * @return list<array{
     *     key: string,
     *     label: string,
     *     subject: string,
     *     office: 'registrar'|'guidance'|null,
     *     office_label: string
     * }>
     */
    public static function catalog(): array
    {
        return [
            [
                'key' => 'application_details',
                'label' => 'Application Form & Details',
                'subject' => 'Application Form & Personal Details',
                'office' => ApplicantOfficeInquiry::OFFICE_REGISTRAR,
                'office_label' => 'Registrar',
            ],
            [
                'key' => 'document_requirements',
                'label' => 'Documents (Form 138 / PSA / TOR)',
                'subject' => 'Document Requirements (Form 138, PSA, TOR)',
                'office' => ApplicantOfficeInquiry::OFFICE_REGISTRAR,
                'office_label' => 'Registrar',
            ],
            [
                'key' => 'transferee_academic_history',
                'label' => 'Transferee / School Not Listed',
                'subject' => 'Transferee, Second-Courser, or School Not Listed',
                'office' => ApplicantOfficeInquiry::OFFICE_REGISTRAR,
                'office_label' => 'Registrar',
            ],
            [
                'key' => 'exam_schedule_slot',
                'label' => 'Entrance Exam Schedule & Slot',
                'subject' => 'Entrance Exam Schedule & Slot Reservation',
                'office' => ApplicantOfficeInquiry::OFFICE_GUIDANCE,
                'office_label' => 'Guidance',
            ],
            [
                'key' => 'test_day_venue',
                'label' => 'Test Day & Venue Requirements',
                'subject' => 'Test Day Requirements & Venue',
                'office' => ApplicantOfficeInquiry::OFFICE_GUIDANCE,
                'office_label' => 'Guidance',
            ],
            [
                'key' => 'results_release_advising',
                'label' => 'Results, Release Day & Advising',
                'subject' => 'Release Day Appointment, Results & Advising',
                'office' => ApplicantOfficeInquiry::OFFICE_GUIDANCE,
                'office_label' => 'Guidance',
            ],
            [
                'key' => 'clearance_quota_enrollment',
                'label' => 'Clearance, Quota & Enrollment',
                'subject' => 'Admission Clearance, Program Quota & Enrollment',
                'office' => ApplicantOfficeInquiry::OFFICE_REGISTRAR,
                'office_label' => 'Registrar',
            ],
            [
                'key' => 'custom',
                'label' => 'Other / Custom Topic',
                'subject' => '',
                'office' => null,
                'office_label' => 'Choose office manually',
            ],
        ];
    }

    public static function resolveOfficeForPreset(?string $key): ?string
    {
        if ($key === null || $key === '') {
            return null;
        }

        foreach (self::catalog() as $preset) {
            if ($preset['key'] === $key) {
                return $preset['office'];
            }
        }

        return null;
    }
}
