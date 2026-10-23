<?php

namespace App\Application\Capabilities;

/**
 * Immutable code-owned capability registry entry (AD-2).
 *
 * @phpstan-type XiamExposure array{human_path: non-empty-string, privacy_projection: non-empty-string, confirmation: non-empty-string, idempotency: non-empty-string, owner: non-empty-string}
 */
final readonly class CapabilityDefinition
{
    /**
     * @param  list<string>  $applicationCapabilities  GF-21 capability keys
     * @param  InputSchema  $inputSchema
     * @param  list<string>  $channels
     * @param  list<string>  $ruleSetRefs
     * @param  XiamExposure|null  $xiamExposure
     */
    public ?string $governance_scope;

    public ?string $display_group;

    public ?string $display_title;

    public function __construct(
        public string $key,
        public int $version,
        public CapabilityKind $kind,
        public array $applicationCapabilities,
        public string $authorizationMode,
        public array $inputSchema,
        public string $handlerClass,
        public array $channels = ['http_page', 'xiam_safe'],
        public array $ruleSetRefs = [],
        public ?string $publicationBinding = null,
        public string $description = '',
        public ?array $xiamExposure = null,
        ?string $governanceScope = null,
        ?string $displayGroup = null,
        ?string $displayTitle = null,
    ) {
        if ($this->key === '' || $this->version < 1) {
            throw new \InvalidArgumentException('Capability key/version invalid.');
        }

        if (! in_array($this->authorizationMode, ['any', 'all'], true)) {
            throw new \InvalidArgumentException('authorizationMode must be any|all.');
        }

        if ($this->applicationCapabilities === []) {
            throw new \InvalidArgumentException('applicationCapabilities required.');
        }

        if (! in_array($this->kind, [CapabilityKind::Read, CapabilityKind::Propose, CapabilityKind::Command], true)) {
            throw new \InvalidArgumentException('Unsupported capability kind.');
        }

        if (in_array('xiam_safe', $this->channels, true)) {
            if ($this->xiamExposure === null || count($this->xiamExposure) !== 5) {
                throw new \InvalidArgumentException('Xiam-exposable capability requires parity and privacy metadata.');
            }

            foreach (['human_path', 'privacy_projection', 'confirmation', 'idempotency', 'owner'] as $field) {
                $value = $this->xiamExposure[$field] ?? null;
                if (! is_string($value) || trim($value) === '') {
                    throw new \InvalidArgumentException('Xiam exposure contract values must be non-empty.');
                }
            }
        }

        $metadata = self::xiamMetadata($this->id());
        $this->governance_scope = $governanceScope ?? $metadata['governance_scope'] ?? null;
        $this->display_group = $displayGroup ?? $metadata['display_group'] ?? null;
        $this->display_title = $displayTitle ?? $metadata['display_title'] ?? null;
    }

    public function governanceScope(): ?string
    {
        return $this->governance_scope;
    }

    public function displayGroup(): ?string
    {
        return $this->display_group;
    }

    public function displayTitle(): ?string
    {
        return $this->display_title;
    }

    /**
     * @return array{governance_scope: string, display_group: string, display_title: string}|array{}
     */
    private static function xiamMetadata(string $id): array
    {
        $scope = self::XIAM_TOOL_GOVERNANCE_SCOPES[$id] ?? null;
        if ($scope === null) {
            return [];
        }

        return [
            'governance_scope' => $scope,
            'display_group' => self::XIAM_TOOL_DISPLAY_GROUPS[$id] ?? self::XIAM_DISPLAY_GROUPS[$scope],
            'display_title' => self::XIAM_DISPLAY_TITLES[$id],
        ];
    }

    private const XIAM_TOOL_GOVERNANCE_SCOPES = [
        'exams.slots.list@1' => 'applicant_central',
        'exams.slot.reserve@1' => 'applicant_central',
        'admission.office_inquiries.options@1' => 'applicant_central',
        'admission.office_inquiries.open@1' => 'applicant_central',
        'admission.office_inquiries.reply@1' => 'applicant_central',
        'setup.readiness.read@1' => 'institution_setup',
        'setup.cycles.create_draft@1' => 'institution_setup',
        'setup.cycles.activate@1' => 'institution_setup',
        'setup.windows.store@1' => 'institution_setup',
        'setup.rooms.create_draft@1' => 'institution_setup',
        'setup.rooms.update@1' => 'institution_setup',
        'setup.quotas.upsert@1' => 'institution_setup',
        'setup.notifications.update_admission@1' => 'institution_setup',
        'setup.notifications.update_exam@1' => 'institution_setup',
        'foundation.academic_cycles.list@1' => 'institution_setup',
        'admissions.attempt.accept@1' => 'registrar',
        'admissions.attempt.bulk_accept@1' => 'registrar',
        'admissions.attempt.request_correction@1' => 'registrar',
        'scheduling.candidate_pool.list@1' => 'guidance',
        'exams.rooms.available@1' => 'guidance',
        'exams.sessions.list@1' => 'guidance',
        'exams.session.show@1' => 'guidance',
        'exams.session.reservation.open@1' => 'guidance',
        'exams.session.assign@1' => 'guidance',
        'exams.session.publish@1' => 'guidance',
        'exams.session.create@1' => 'guidance',
        'exams.attendance.record@1' => 'guidance',
        'exams.session.start@1' => 'guidance',
        'exams.session.close@1' => 'guidance',
        'assessment.unscored_examinees.query@1' => 'guidance',
        'result_sheet.templates.list@1' => 'guidance',
        'assessment.scores.record_manual@1' => 'guidance',
        'assessment.grading.finalize@1' => 'guidance',
        'results.release.overview@1' => 'guidance',
        'release.result.publish@2' => 'guidance',
        'consultation.sessions.list@1' => 'guidance',
        'consultation.session.create@1' => 'guidance',
        'consultation.session.reservation.open@1' => 'guidance',
        'consultation.decision.record@2' => 'guidance',
        'consultation.session.cancel@1' => 'guidance',
        'knowledge.sources.list@1' => 'shared_staff',
        'knowledge.search@1' => 'shared_staff',
        'workspace.summary@1' => 'shared_staff',
        'exams.sessions.today@1' => 'shared_staff',
        'reports.applications_census@1' => 'shared_staff',
        'reports.feeder_high_schools@1' => 'shared_staff',
        'reports.daily_admissions_logbook@1' => 'shared_staff',
        'reports.college_program_quota_summary@1' => 'shared_staff',
        'reports.score_distribution@1' => 'shared_staff',
        'reports.exam_attendance@1' => 'shared_staff',
        'reports.exam_session_attendance@1' => 'shared_staff',
        'reports.room_utilization@1' => 'shared_staff',
        'reports.staff_accomplishment@1' => 'shared_staff',
    ];
    private const XIAM_DISPLAY_GROUPS = [
        'applicant_central' => 'Applicant Portal',
        'institution_setup' => 'Institutional Setup',
        'registrar' => 'Registrar Office',
        'guidance' => 'Guidance & Testing Office',
        'shared_staff' => 'Shared Staff & Knowledge',
    ];

    private const XIAM_TOOL_DISPLAY_GROUPS = [
        'reports.applications_census@1' => 'Institutional Reports',
        'reports.feeder_high_schools@1' => 'Institutional Reports',
        'reports.daily_admissions_logbook@1' => 'Institutional Reports',
        'reports.college_program_quota_summary@1' => 'Institutional Reports',
        'reports.score_distribution@1' => 'Institutional Reports',
        'reports.exam_attendance@1' => 'Institutional Reports',
        'reports.exam_session_attendance@1' => 'Institutional Reports',
        'reports.room_utilization@1' => 'Institutional Reports',
        'reports.staff_accomplishment@1' => 'Institutional Reports',
    ];

    private const XIAM_DISPLAY_TITLES = [
        'exams.slots.list@1' => 'View Available Exam Slots',
        'exams.slot.reserve@1' => 'Reserve an Exam Slot',
        'admission.office_inquiries.options@1' => 'View Office Inquiry Options',
        'admission.office_inquiries.open@1' => 'Open an Office Inquiry',
        'admission.office_inquiries.reply@1' => 'Reply to an Office Inquiry',
        'setup.readiness.read@1' => 'View Institutional Setup Readiness',
        'setup.cycles.create_draft@1' => 'Create an Academic Cycle',
        'setup.cycles.activate@1' => 'Activate an Academic Cycle',
        'setup.windows.store@1' => 'Manage Application Windows',
        'setup.rooms.create_draft@1' => 'Create an Exam Room',
        'setup.rooms.update@1' => 'Update an Exam Room',
        'setup.quotas.upsert@1' => 'Manage Program Quotas',
        'setup.notifications.update_admission@1' => 'Update Admission Notifications',
        'setup.notifications.update_exam@1' => 'Update Exam Notifications',
        'foundation.academic_cycles.list@1' => 'List Academic Cycles',
        'admissions.attempt.accept@1' => 'Accept an Admission Attempt',
        'admissions.attempt.bulk_accept@1' => 'Accept Admission Attempts in Bulk',
        'admissions.attempt.request_correction@1' => 'Request Admission Corrections',
        'scheduling.candidate_pool.list@1' => 'List the Exam Candidate Pool',
        'exams.rooms.available@1' => 'Check Exam Room Availability',
        'exams.sessions.list@1' => 'List Exam Sessions',
        'exams.session.show@1' => 'View an Exam Session',
        'exams.session.reservation.open@1' => 'Open Exam Session Reservations',
        'exams.session.assign@1' => 'Assign an Applicant to an Exam Session',
        'exams.session.publish@1' => 'Publish an Exam Session',
        'exams.session.create@1' => 'Create an Exam Session',
        'exams.attendance.record@1' => 'Record Exam Attendance',
        'exams.session.start@1' => 'Start an Exam Session',
        'exams.session.close@1' => 'Close an Exam Session',
        'assessment.unscored_examinees.query@1' => 'List Unscored Examinees',
        'result_sheet.templates.list@1' => 'List Result Sheet Templates',
        'assessment.scores.record_manual@1' => 'Record Manual Scores',
        'assessment.grading.finalize@1' => 'Finalize a Grading Session',
        'results.release.overview@1' => 'View Result Release Progress',
        'release.result.publish@2' => 'Publish a Result',
        'consultation.sessions.list@1' => 'List Release Day Sessions',
        'consultation.session.create@1' => 'Create a Release Day Session',
        'consultation.session.reservation.open@1' => 'Open Release Day Reservations',
        'consultation.decision.record@2' => 'Record a Release Day Decision',
        'consultation.session.cancel@1' => 'Cancel a Release Day Session',
        'reports.applications_census@1' => 'Applications Census Report',
        'reports.feeder_high_schools@1' => 'Feeder High Schools Origin Report',
        'reports.daily_admissions_logbook@1' => 'Daily Admissions Logbook Report',
        'reports.college_program_quota_summary@1' => 'College Program Quota Summary Report',
        'reports.score_distribution@1' => 'Score Distribution & Aptitude Yield Report',
        'reports.exam_attendance@1' => 'Exam Attendance Rollup Report',
        'reports.exam_session_attendance@1' => 'Single Exam Session Attendance Report',
        'reports.room_utilization@1' => 'Room Utilization & Facility Capacity Report',
        'reports.staff_accomplishment@1' => 'Staff Accomplishment & Lifecycle Milestones Report',
        'knowledge.sources.list@1' => 'List Knowledge Sources',
        'knowledge.search@1' => 'Search Approved Knowledge',
        'workspace.summary@1' => 'View the Staff Workspace Summary',
        'exams.sessions.today@1' => 'View Today’s Exam Sessions',
    ];

    public function id(): string
    {
        return $this->key.'@'.$this->version;
    }
}
