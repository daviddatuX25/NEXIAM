<?php

namespace App\DocumentTemplate;

final class MasterDataFieldCatalog
{
    public const SCHEMA_VERSION = 1;

    /**
     * @var list<array{
     *     number: int,
     *     id: string,
     *     label: string,
     *     description?: string,
     *     leaves: list<array{id: string, label: string, kind: string, score_scale?: string}>
     * }>
     */
    public const BRANCHES = [
        [
            'number' => 1,
            'id' => 'institution_metadata',
            'label' => 'Institution & Document Metadata',
            'leaves' => [
                ['id' => 'institution_name', 'label' => 'Institution name', 'kind' => 'field'],
                ['id' => 'campus_name', 'label' => 'Campus name', 'kind' => 'field'],
                ['id' => 'campus_address', 'label' => 'Campus address', 'kind' => 'field'],
                ['id' => 'issuing_office_name', 'label' => 'Issuing office name', 'kind' => 'field'],
                ['id' => 'document_title', 'label' => 'Document title', 'kind' => 'field'],
                ['id' => 'document_code', 'label' => 'Document code', 'kind' => 'field'],
                ['id' => 'revision_no', 'label' => 'Revision number', 'kind' => 'field'],
                ['id' => 'generated_at', 'label' => 'Generated at', 'kind' => 'field'],
                ['id' => 'prepared_by_name', 'label' => 'Prepared by', 'kind' => 'field'],
                ['id' => 'page_i', 'label' => 'Page number', 'kind' => 'field'],
                ['id' => 'page_all', 'label' => 'Total pages', 'kind' => 'field'],
            ],
        ],
        [
            'number' => 2,
            'id' => 'academic_cycle_and_programs',
            'label' => 'Academic Cycle & Programs',
            'leaves' => [
                ['id' => 'academic_cycle_name', 'label' => 'Academic cycle name', 'kind' => 'field'],
                ['id' => 'school_year', 'label' => 'School year', 'kind' => 'field'],
                ['id' => 'semester', 'label' => 'Semester', 'kind' => 'field'],
                ['id' => 'program_code', 'label' => 'Program code', 'kind' => 'field'],
                ['id' => 'program_name', 'label' => 'Program name', 'kind' => 'field'],
                ['id' => 'department_code', 'label' => 'Department code', 'kind' => 'field'],
                ['id' => 'quota_capacity', 'label' => 'Quota capacity', 'kind' => 'field'],
                ['id' => 'qualified_count', 'label' => 'Qualified count', 'kind' => 'field'],
                ['id' => 'remaining_quota_slots', 'label' => 'Remaining quota slots', 'kind' => 'field'],
                ['id' => 'program_quota_rows', 'label' => 'Program quota rows', 'kind' => 'repeater'],
            ],
        ],
        [
            'number' => 3,
            'id' => 'applicant_profile',
            'label' => 'Applicant Profile',
            'leaves' => [
                ['id' => 'reference_code', 'label' => 'Reference code', 'kind' => 'field'],
                ['id' => 'examinee_number', 'label' => 'Examinee number (5-digit)', 'kind' => 'field'],
                ['id' => 'sequence_number', 'label' => 'Sequence number (compact)', 'kind' => 'field'],
                ['id' => 'applicant_name', 'label' => 'Applicant name', 'kind' => 'field'],
                ['id' => 'email', 'label' => 'Email', 'kind' => 'field'],
                ['id' => 'contact_number', 'label' => 'Contact number', 'kind' => 'field'],
                ['id' => 'last_school_enrolled', 'label' => 'Last school — full name', 'kind' => 'field'],
                ['id' => 'last_school_short', 'label' => 'Last school — abbreviation', 'kind' => 'field', 'description' => 'Example: Ilocos Sur College → ISC. Known abbreviation, otherwise generated initials.'],
                ['id' => 'strand_or_prev_course', 'label' => 'Strand / previous course — full text', 'kind' => 'field'],
                ['id' => 'strand_or_prev_course_short', 'label' => 'Strand / previous course — abbreviation', 'kind' => 'field', 'description' => 'Example: Humanities and Social Sciences → HUMSS. Known abbreviation, otherwise generated initials.'],
                ['id' => 'shs_strand', 'label' => 'Senior high school strand', 'kind' => 'field'],
                ['id' => 'gpa_or_gwa', 'label' => 'GPA / GWA', 'kind' => 'field'],
                ['id' => 'first_choice_program_name', 'label' => 'First-choice program', 'kind' => 'field'],
                ['id' => 'assigned_or_qualified_program_name', 'label' => 'Assigned or qualified program', 'kind' => 'field'],
                ['id' => 'application_status', 'label' => 'Application status', 'kind' => 'field'],
                ['id' => 'qr_code_svg', 'label' => 'QR code', 'kind' => 'field'],
                ['id' => 'verification_code', 'label' => 'Verification code', 'kind' => 'field'],
            ],
        ],
        [
            'number' => 4,
            'id' => 'requirements_and_clearance',
            'label' => 'Requirements & Clearance',
            'leaves' => [
                ['id' => 'eligibility_status', 'label' => 'Eligibility status', 'kind' => 'field'],
                ['id' => 'clearance_reference_no', 'label' => 'Clearance reference number', 'kind' => 'field'],
                ['id' => 'cleared_for_program_name', 'label' => 'Cleared for program', 'kind' => 'field'],
                ['id' => 'cleared_at', 'label' => 'Cleared at', 'kind' => 'field'],
                ['id' => 'cleared_by_staff_name', 'label' => 'Cleared by staff member', 'kind' => 'field'],
                ['id' => 'student_requirements', 'label' => 'Student requirements', 'kind' => 'repeater'],
                ['id' => 'qualifiers_or_clearance_roster', 'label' => 'Qualifiers or clearance roster', 'kind' => 'repeater'],
            ],
        ],
        [
            'number' => 5,
            'id' => 'exam_logistics',
            'label' => 'Exam Logistics',
            'leaves' => [
                ['id' => 'exam_session_code', 'label' => 'Exam session code', 'kind' => 'field'],
                ['id' => 'exam_session_label', 'label' => 'Exam session label', 'kind' => 'field'],
                ['id' => 'exam_date', 'label' => 'Exam date', 'kind' => 'field'],
                ['id' => 'exam_time_window', 'label' => 'Exam time window', 'kind' => 'field'],
                ['id' => 'building_name', 'label' => 'Building name', 'kind' => 'field'],
                ['id' => 'room_name', 'label' => 'Room name', 'kind' => 'field'],
                ['id' => 'seat_number', 'label' => 'Seat number', 'kind' => 'field'],
                ['id' => 'test_instrument_name', 'label' => 'Test instrument name', 'kind' => 'field'],
                ['id' => 'booklet_set_code', 'label' => 'Booklet set code', 'kind' => 'field'],
                ['id' => 'attendance_status', 'label' => 'Attendance status', 'kind' => 'field'],
                ['id' => 'session_examinees', 'label' => 'Session examinees', 'kind' => 'repeater'],
            ],
        ],
        [
            'number' => 6,
            'id' => 'psychometric_scores',
            'label' => 'Psychometric Scores',
            'description' => 'Aptitude Area scores use the active 0–100 scale; no prospective stanine is exposed.',
            'leaves' => [
                ['id' => 'total_raw_score', 'label' => 'Total raw score', 'kind' => 'field'],
                ['id' => 'max_possible_score', 'label' => 'Maximum possible score', 'kind' => 'field'],
                ['id' => 'overall_percentage_score', 'label' => 'Overall percentage score', 'kind' => 'field'],
                ['id' => 'overall_percentile_rank', 'label' => 'Overall percentile rank', 'kind' => 'field'],
                ['id' => 'descriptive_rating', 'label' => 'Descriptive rating', 'kind' => 'field'],
                ['id' => 'exam_outcome_verdict', 'label' => 'Exam outcome verdict', 'kind' => 'field'],
                ['id' => 'aptitude_subtest_scores', 'label' => 'Aptitude Area scores (0–100)', 'kind' => 'repeater', 'score_scale' => '0-100'],
                ['id' => 'passing_or_score_roster', 'label' => 'Passing or score roster', 'kind' => 'repeater'],
            ],
        ],
        [
            'number' => 7,
            'id' => 'guidance_consultation',
            'label' => 'Guidance Consultation',
            'leaves' => [
                ['id' => 'top_recommended_program_1', 'label' => 'Top recommended program', 'kind' => 'field'],
                ['id' => 'match_score_1', 'label' => 'Program match score', 'kind' => 'field'],
                ['id' => 'consultation_decision', 'label' => 'Consultation decision', 'kind' => 'field'],
                ['id' => 'counselor_name', 'label' => 'Counselor name', 'kind' => 'field'],
                ['id' => 'endorsed_program_name', 'label' => 'Endorsed program', 'kind' => 'field'],
                ['id' => 'department_interview_status', 'label' => 'Department interview status', 'kind' => 'field'],
                ['id' => 'consultation_or_placement_roster', 'label' => 'Consultation or placement roster', 'kind' => 'repeater'],
            ],
        ],
    ];

    /**
     * @return array{
     *     schema_version: int,
     *     branches: list<array{
     *         number: int,
     *         id: string,
     *         label: string,
     *         description?: string,
     *         leaves: list<array{id: string, label: string, kind: string, score_scale?: string}>
     *     }>
     * }
     */
    public static function all(): array
    {
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'branches' => self::BRANCHES,
        ];
    }

    /**
     * @return list<array{
     *     number: int,
     *     id: string,
     *     label: string,
     *     description?: string,
     *     leaves: list<array{id: string, label: string, kind: string, score_scale?: string}>
     * }>
     */
    public static function branches(): array
    {
        return self::BRANCHES;
    }

    /** @return list<string> */
    public static function leafIds(): array
    {
        $leafIds = [];

        foreach (self::BRANCHES as $branch) {
            foreach ($branch['leaves'] as $leaf) {
                $leafIds[] = $leaf['id'];
            }
        }

        return $leafIds;
    }

    /** @return list<string> */
    public static function leafIdsForBranch(string $branchId): array
    {
        foreach (self::BRANCHES as $branch) {
            if ($branch['id'] === $branchId) {
                return array_column($branch['leaves'], 'id');
            }
        }

        return [];
    }

    /**
     * @return array{id: string, label: string, kind: string, score_scale?: string, branch_id: string, branch_number: int}|null
     */
    public static function findLeaf(string $leafId): ?array
    {
        foreach (self::BRANCHES as $branch) {
            foreach ($branch['leaves'] as $leaf) {
                if ($leaf['id'] === $leafId) {
                    return [
                        ...$leaf,
                        'branch_id' => $branch['id'],
                        'branch_number' => $branch['number'],
                    ];
                }
            }
        }

        return null;
    }
}
