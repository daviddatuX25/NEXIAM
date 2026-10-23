<?php

namespace App\Ai\Adapters\LaravelAi;

use RuntimeException;

/**
 * Fail-closed projector registry and PII firewall for Assistant tool output.
 * Enforces per-capability key allowlists and fails closed on unregistered capabilities.
 */
final class XiamProjectorRegistry
{
    /**
     * Allowlist schema shared by mutation preflights and proposal envelopes.
     *
     * @var array<string, mixed>
     */
    private const PROPOSAL_CHECKS = ['key', 'label', 'status', 'detail'];

    /**
     * Standard envelope for all 7-stage Action Card proposals.
     *
     * @var array<string, mixed>
     */
    private const GENERIC_PROPOSAL_SCHEMA = [
        'id' => true,
        'proposal_id' => true,
        'capability_id' => true,
        'status' => true,
        'safety_tier' => true,
        'confirmation_phrase' => true,
        'requires_reauth' => true,
        'expires_at' => true,
        'summary' => true,
        'diff' => ['key', 'label', 'before', 'after'],
        'preflight' => ['checks' => self::PROPOSAL_CHECKS],
        'preflight_checks' => self::PROPOSAL_CHECKS,
        'evidence' => ['outcome' => true, 'checks' => self::PROPOSAL_CHECKS],
    ];

    /**
     * @var array<string, mixed>
     */
    private const ASSIGN_PROPOSAL_SCHEMA = [
        'id' => true,
        'proposal_id' => true,
        'capability_id' => true,
        'status' => true,
        'expires_at' => true,
        'summary' => [
            'impact' => ['session_id', 'code', 'candidate_refs', 'count', 'action', 'expected_version'],
            'evidence' => ['outcome' => true, 'checks' => self::PROPOSAL_CHECKS],
        ],
        'diff' => ['key', 'label', 'before', 'after'],
        'preflight' => ['checks' => self::PROPOSAL_CHECKS],
        'evidence' => ['outcome' => true, 'checks' => self::PROPOSAL_CHECKS],
    ];

    /**
     * @var array<string, mixed>
     */
    private const RESERVATION_PROPOSAL_SCHEMA = [
        'id' => true,
        'proposal_id' => true,
        'capability_id' => true,
        'status' => true,
        'expires_at' => true,
        'summary' => [
            'impact' => ['session_id', 'code', 'expected_version', 'action', 'reason'],
            'evidence' => ['outcome' => true, 'checks' => self::PROPOSAL_CHECKS],
        ],
        'diff' => ['key', 'label', 'before', 'after'],
        'preflight' => ['checks' => self::PROPOSAL_CHECKS],
        'evidence' => ['outcome' => true, 'checks' => self::PROPOSAL_CHECKS],
    ];

    /**
     * @var array<string, mixed>
     */
    private const PUBLISH_PROPOSAL_SCHEMA = [
        'id' => true,
        'proposal_id' => true,
        'capability_id' => true,
        'status' => true,
        'expires_at' => true,
        'summary' => [
            'impact' => ['session_id', 'code', 'expected_version', 'action', 'assigned_count'],
            'evidence' => ['outcome' => true, 'checks' => self::PROPOSAL_CHECKS],
        ],
        'diff' => ['key', 'label', 'before', 'after'],
        'preflight' => ['checks' => self::PROPOSAL_CHECKS],
        'evidence' => ['outcome' => true, 'checks' => self::PROPOSAL_CHECKS],
    ];

    /**
     * @var array<string, mixed>
     */
    private const SLOT_RESERVE_PROPOSAL_SCHEMA = [
        'id' => true,
        'proposal_id' => true,
        'capability_id' => true,
        'status' => true,
        'expires_at' => true,
        'summary' => [
            'impact' => ['exam_session_id', 'code', 'action'],
            'evidence' => ['outcome' => true, 'checks' => self::PROPOSAL_CHECKS],
        ],
        'diff' => ['key', 'label', 'before', 'after'],
        'preflight' => ['checks' => self::PROPOSAL_CHECKS],
        'evidence' => ['outcome' => true, 'checks' => self::PROPOSAL_CHECKS],
    ];

    /**
     * @var array<string, mixed>
     */
    private const CREATE_PROPOSAL_SCHEMA = [
        'id' => true,
        'proposal_id' => true,
        'capability_id' => true,
        'status' => true,
        'expires_at' => true,
        'summary' => [
            'impact' => [
                'code', 'room_name', 'room_capacity', 'starts_at', 'effective_end_at',
                'academic_cycle_id', 'expected_version',
            ],
            'evidence' => ['outcome' => true, 'checks' => self::PROPOSAL_CHECKS],
        ],
        'diff' => ['key', 'label', 'before', 'after'],
        'preflight' => ['checks' => self::PROPOSAL_CHECKS],
        'evidence' => ['outcome' => true, 'checks' => self::PROPOSAL_CHECKS],
    ];

    /**
     * @var array<string, mixed>
     */
    private const CONSULTATION_CREATE_PROPOSAL_SCHEMA = [
        'mode' => true,
        'ok' => true,
        'id' => true,
        'proposal_id' => true,
        'expires_at' => true,
        'summary' => [
            'session_id', 'code', 'room_name', 'scheduled_at', 'booking_mode', 'academic_cycle_id', 'expected_version',
            'impact' => ['session_id', 'code', 'room_name', 'scheduled_at', 'booking_mode', 'academic_cycle_id', 'expected_version'],
            'evidence' => ['outcome' => true, 'checks' => self::PROPOSAL_CHECKS],
        ],
        'capability_id' => true,
        'session_id' => true,
        'code' => true,
        'status' => true,
        'command_input' => ['academic_cycle_id', 'room_id', 'scheduled_at', 'code', 'booking_mode'],
        'impact' => ['session_id', 'code', 'room_name', 'scheduled_at', 'booking_mode', 'academic_cycle_id', 'expected_version'],
        'diff' => ['key', 'label', 'before', 'after'],
        'preflight' => ['checks' => self::PROPOSAL_CHECKS],
        'evidence' => ['outcome' => true, 'checks' => self::PROPOSAL_CHECKS],
    ];

    /**
     * @var array<string, mixed>
     */
    private const CONSULTATION_RESERVATION_PROPOSAL_SCHEMA = [
        'mode' => true,
        'ok' => true,
        'id' => true,
        'proposal_id' => true,
        'expires_at' => true,
        'summary' => [
            'session_id', 'code', 'expected_version', 'action', 'reason',
            'impact' => ['session_id', 'code', 'expected_version', 'action', 'reason'],
            'evidence' => ['outcome' => true, 'checks' => self::PROPOSAL_CHECKS],
        ],
        'session_id' => true,
        'booking_mode' => true,
        'version' => true,
        'status' => true,
        'capability_id' => true,
        'impact' => ['session_id', 'code', 'expected_version', 'action', 'reason'],
        'diff' => ['key', 'label', 'before', 'after'],
        'preflight' => ['checks' => self::PROPOSAL_CHECKS],
        'evidence' => ['outcome' => true, 'checks' => self::PROPOSAL_CHECKS],
    ];

    /**
     * Only the applicant-reviewed draft and safe receipt may cross the tool boundary.
     *
     * @var array<string, mixed>
     */
    private const OFFICE_INQUIRY_PROPOSAL_SCHEMA = [
        'mode' => true,
        'id' => true,
        'proposal_id' => true,
        'capability_id' => true,
        'status' => true,
        'safety_tier' => true,
        'confirmation_phrase' => true,
        'requires_reauth' => true,
        'expires_at' => true,
        'summary' => [
            'office', 'office_label', 'subject', 'body', 'action', 'inquiry_id',
            'impact' => ['office', 'office_label', 'subject', 'body', 'action', 'inquiry_id'],
            'evidence' => ['outcome' => true, 'checks' => self::PROPOSAL_CHECKS],
        ],
        'impact' => ['office', 'office_label', 'subject', 'body', 'action', 'inquiry_id'],
        'diff' => ['key', 'label', 'before', 'after'],
        'preflight' => ['checks' => self::PROPOSAL_CHECKS],
        'preflight_checks' => self::PROPOSAL_CHECKS,
        'evidence' => ['outcome' => true, 'checks' => self::PROPOSAL_CHECKS],
        'inquiry_id' => true,
        'message_id' => true,
        'sequence' => true,
        'office' => true,
        'receipt_id' => true,
        'replayed' => true,
        'href' => true,
    ];

    /**
     * @var list<string>
     */
    private const GLOBAL_DENIED_KEYS = [
        'reference_code', 'access_key', 'password', 'secret', 'token', 'email', 'phone', 'lrn', '_command_input',
    ];

    /**
     * Explicit allowlist schemas keyed by exact capability ID and version.
     *
     * @var array<string, array<string, mixed>>
     */
    private const ALLOWLIST_SCHEMAS = [
        'admission.office_inquiries.options@1' => [
            'mode' => true,
            'capability_id' => true,
            'destinations' => ['code', 'label'],
            'synthetic_only' => true,
            'inquiries' => ['id', 'office', 'office_label', 'status'],
        ],
        'admission.office_inquiries.open@1' => self::OFFICE_INQUIRY_PROPOSAL_SCHEMA,
        'admission.office_inquiries.reply@1' => self::OFFICE_INQUIRY_PROPOSAL_SCHEMA,
        'workspace.summary@1' => [
            'summary' => [
                'institution_id', 'active_cycle_name', 'pending_reviews_count', 'today_sessions_count',
                'upcoming_sessions_count', 'draft_sessions_count', 'as_of',
            ],
        ],
        'exams.sessions.today@1' => [
            'today_count' => true,
            'sessions' => ['id', 'code', 'status', 'starts_at', 'room_code', 'room_capacity', 'assigned_count'],
            'as_of' => true,
        ],
        'setup.readiness.read@1' => [
            'overall_percent' => true,
            'passed' => true,
            'total' => true,
            'blocking_count' => true,
            'ready' => true,
            'categories' => ['key', 'label', 'capability', 'href', 'status', 'summary'],
            'groups' => [
                'key', 'label',
                'categories' => [
                    'key', 'label', 'href',
                    'checks' => ['label', 'passed', 'severity', 'detail'],
                ],
            ],
        ],
        'result_sheet.templates.list@1' => [
            'summary' => ['total_count', 'active_count', 'active_template_name', 'active_template_mode', 'as_of'],
            'templates' => ['id', 'display_name', 'is_active', 'mode', 'orientation', 'paper_size'],
        ],
        'results.release.overview@1' => [
            'summary' => ['total_grading_sessions', 'total_examinees', 'total_released', 'sessions_count', 'as_of'],
            'sessions' => ['id', 'exam_code', 'room_name', 'starts_at', 'status', 'examinees_count', 'released_count'],
        ],
        'assessment.unscored_examinees.query@1' => [
            'unscored_examinees_count' => true,
            'completed_sessions_count' => true,
            'sessions_awaiting_scores' => ['session_id', 'session_code', 'exam_date', 'room_name', 'unscored_count'],
            'as_of' => true,
        ],
        'exams.rooms.available@1' => [
            'rooms' => ['id', 'name', 'code', 'capacity', 'is_available'],
        ],
        'foundation.academic_cycles.list@1' => [
            'cycles' => ['id', 'code', 'name', 'school_year', 'semester', 'is_active', 'starts_on', 'ends_on'],
        ],
        'exams.sessions.list@1' => [
            'summary' => ['total_count', 'assigned_count', 'capacity_count', 'by_status'],
            'sessions' => ['id', 'code', 'status', 'starts_at', 'assigned_count', 'room_capacity', 'room_code'],
            'page_state' => true,
        ],
        'exams.session.show@1' => [
            'session' => [
                'id', 'code', 'status', 'starts_at', 'effective_end_at', 'date', 'start_time', 'end_time',
                'assigned_count', 'room', 'academic_cycle', 'proctors', 'is_publishable', 'publish_block_reason',
                'version', 'academic_cycle_id', 'room_id',
                'assignments' => ['id', 'admission_attempt_id', 'status', 'source'],
            ],
        ],
        'exams.session.reservation.open@1' => self::RESERVATION_PROPOSAL_SCHEMA,
        'exams.session.assign@1' => self::ASSIGN_PROPOSAL_SCHEMA,
        'exams.session.publish@1' => self::PUBLISH_PROPOSAL_SCHEMA,
        'exams.session.create@1' => self::CREATE_PROPOSAL_SCHEMA,
        'exams.slot.reserve@1' => self::SLOT_RESERVE_PROPOSAL_SCHEMA,
        'consultation.session.create@1' => self::CONSULTATION_CREATE_PROPOSAL_SCHEMA,
        'consultation.session.reservation.open@1' => self::CONSULTATION_RESERVATION_PROPOSAL_SCHEMA,
        'consultation.decision.record@1' => self::GENERIC_PROPOSAL_SCHEMA,
        'consultation.decision.record@2' => self::GENERIC_PROPOSAL_SCHEMA,
        'consultation.session.cancel@1' => self::GENERIC_PROPOSAL_SCHEMA,
        'admissions.attempt.accept@1' => self::GENERIC_PROPOSAL_SCHEMA,
        'admissions.attempt.bulk_accept@1' => self::GENERIC_PROPOSAL_SCHEMA,
        'admissions.attempt.request_correction@1' => self::GENERIC_PROPOSAL_SCHEMA,
        'exams.attendance.record@1' => self::GENERIC_PROPOSAL_SCHEMA,
        'exams.session.start@1' => self::GENERIC_PROPOSAL_SCHEMA,
        'exams.session.close@1' => self::GENERIC_PROPOSAL_SCHEMA,
        'registrar.enrollment.stamp_enrolling@1' => self::GENERIC_PROPOSAL_SCHEMA,
        'registrar.enrollment.stamp_enrolled@1' => self::GENERIC_PROPOSAL_SCHEMA,
        'registrar.enrollment.reverse_stamp@1' => self::GENERIC_PROPOSAL_SCHEMA,
        'assessment.scores.record_manual@1' => self::GENERIC_PROPOSAL_SCHEMA,
        'assessment.grading.finalize@1' => self::GENERIC_PROPOSAL_SCHEMA,
        'release.result.publish@2' => self::GENERIC_PROPOSAL_SCHEMA,
        'release.result.publish@1' => self::GENERIC_PROPOSAL_SCHEMA,
        'setup.cycles.activate@1' => self::GENERIC_PROPOSAL_SCHEMA,
        'setup.rooms.update@1' => self::GENERIC_PROPOSAL_SCHEMA,
        'setup.quotas.upsert@1' => self::GENERIC_PROPOSAL_SCHEMA,
        'setup.rooms.create_draft@1' => self::GENERIC_PROPOSAL_SCHEMA,
        'setup.cycles.create_draft@1' => self::GENERIC_PROPOSAL_SCHEMA,
        'setup.notifications.update_admission@1' => self::GENERIC_PROPOSAL_SCHEMA,
        'setup.notifications.update_exam@1' => self::GENERIC_PROPOSAL_SCHEMA,
        'setup.windows.store@1' => self::GENERIC_PROPOSAL_SCHEMA,
        'consultation.sessions.list@1' => [
            'summary' => ['total_count', 'assigned_count', 'capacity_count', 'by_status'],
            'sessions' => ['id', 'code', 'status', 'scheduled_at', 'assigned_count', 'room_capacity', 'room_code'],
            'page_state' => true,
        ],
        'consultation.session.show@1' => [
            'session' => [
                'id', 'code', 'status', 'scheduled_at', 'assigned_count', 'room', 'academic_cycle',
                'version', 'academic_cycle_id', 'room_id',
                'assignments' => ['id', 'status', 'source'],
            ],
        ],
        'exams.slots.list@1' => [
            'slots' => [
                'id', 'code', 'date', 'start_time', 'end_time', 'room', 'capacity', 'reserved', 'remaining',
                'full', 'starts_at', 'effective_end_at', 'available_seats', 'room_name',
            ],
            'academic_cycle_id' => true,
        ],
        'scheduling.candidate_pool.list@1' => [
            'summary' => ['academic_cycle_id', 'pool_count', 'available_capacity', 'returned_count', 'as_of'],
            'candidates' => ['candidate_ref', 'preferred_program_code', 'strand'],
        ],
        'knowledge.sources.list@1' => [
            'sources' => ['id', 'title', 'category', 'status', 'version', 'updated_at'],
        ],
        'knowledge.search@1' => [
            'outcome' => true,
            'hit_count' => true,
            'citations' => [
                'citation_id', 'source_class', 'version_id', 'source_id', 'title', 'excerpt', 'score', 'locator', 'as_of',
            ],
        ],
        'test.capability@1' => [
            'session_id' => true,
            'code' => true,
            'safe_nested' => ['status', 'code'],
        ],
        'reports.applications_census@1' => [
            'institution_id', 'cycle_id', 'cycle_name', 'total_applications',
            'status_distribution' => ['status', 'count', 'percentage'],
            'program_distribution' => ['program_code', 'count', 'percentage'],
            'sex_distribution' => ['sex', 'count', 'percentage'],
            'as_of',
        ],
        'reports.feeder_high_schools@1' => [
            'institution_id', 'cycle_id', 'cycle_name', 'total_feeders_count', 'total_applicants',
            'top_schools' => ['school_name', 'city', 'province', 'applicant_count', 'accepted_count', 'conversion_rate_percent'],
            'province_distribution' => ['province', 'count', 'percentage'],
            'as_of',
        ],
        'reports.daily_admissions_logbook@1' => [
            'institution_id', 'cycle_id', 'cycle_name', 'target_date', 'total_daily_applicants',
            'strand_distribution' => ['strand', 'count', 'percentage'],
            'applicant_type_distribution' => ['applicant_type', 'count', 'percentage'],
            'hourly_distribution' => ['hour', 'count'],
            'as_of',
        ],
        'reports.college_program_quota_summary@1' => [
            'institution_id', 'cycle_id', 'cycle_name', 'total_quota', 'total_committed', 'overall_utilization_percent',
            'programs' => ['program_code', 'program_name', 'department_code', 'quota', 'first_choice_demand', 'committed', 'open_slots', 'utilization_percent', 'status_label'],
            'departments' => ['department_code', 'quota', 'committed', 'utilization_percent'],
            'as_of',
        ],
        'reports.score_distribution@1' => [
            'institution_id', 'cycle_id', 'cycle_name', 'total_examinees_scored', 'overall_passing_rate_percent',
            'subtests' => ['subtest_code', 'subtest_name', 'examinee_count', 'mean_score', 'max_score', 'passing_count', 'pass_rate_percent'],
            'as_of',
        ],
        'reports.exam_attendance@1' => [
            'institution_id', 'cycle_id', 'cycle_name', 'total_sessions', 'total_registered', 'total_present', 'total_absent', 'overall_attendance_rate_percent',
            'sessions' => ['session_code', 'room_name', 'starts_at', 'capacity', 'registered_count', 'present_count', 'absent_count', 'attendance_rate_percent', 'status'],
            'as_of',
        ],
        'reports.exam_session_attendance@1' => [
            'institution_id', 'cycle_id', 'session_id', 'session_code', 'session_title', 'room_name', 'starts_at',
            'capacity', 'registered_count', 'present_count', 'absent_count', 'attendance_rate_percent', 'status', 'as_of',
        ],
        'reports.room_utilization@1' => [
            'institution_id', 'cycle_id', 'cycle_name', 'total_rooms', 'total_capacity', 'total_seats_utilized', 'overall_utilization_rate_percent',
            'rooms' => ['room_code', 'room_name', 'building', 'capacity', 'sessions_hosted', 'seats_utilized', 'utilization_rate_percent', 'status'],
            'as_of',
        ],
        'reports.staff_accomplishment@1' => [
            'institution_id', 'cycle_id', 'cycle_name', 'total_applications_received', 'total_applications_verified',
            'total_examinees_tested', 'total_sessions_scored', 'total_clearances_issued', 'total_audit_events',
            'milestones' => ['office', 'activity_milestone', 'standard_metric', 'volume_processed', 'completion_status', 'benchmark_yield'],
            'as_of',
        ],
    ];

    /**
     * Global denied PII keys for extra defense in depth.
     *
     * @var list<string>
     */
    private const DENIED_PII_KEYS = [
        'first_name',
        'last_name',
        'name',
        'email',
        'contact_email',
        'phone',
        'phone_number',
        'birthdate',
        'birth_date',
        'access_key',
        'raw_password',
        'password',
        'raw_token',
        'internal_notes',
        'unreleased_scores',
        'reference_code',
        'reference_number',
    ];

    public function has(string $capabilityId): bool
    {
        return isset(self::ALLOWLIST_SCHEMAS[$capabilityId]);
    }

    /**
     * Project and scrub tool output data before serialization to LLM provider.
     * Throws RuntimeException if capability is not registered.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    public function project(string $capabilityId, array $data): array
    {
        if (! isset(self::ALLOWLIST_SCHEMAS[$capabilityId])) {
            throw new RuntimeException("Unregistered capability [{$capabilityId}] refused by XiamProjectorRegistry.");
        }

        $schema = self::ALLOWLIST_SCHEMAS[$capabilityId];

        return $this->applyAllowlist(
            $data,
            $schema,
            in_array($capabilityId, ['exams.rooms.available@1', 'foundation.academic_cycles.list@1'], true),
        );
    }

    /**
     * Recursively projects an array according to the allowlist schema.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private function applyAllowlist(array $data, array $schema, bool $allowCatalogName = false): array
    {
        $projected = [];

        foreach ($data as $key => $value) {
            $normalizedKey = strtolower((string) $key);

            $isDeniedPii = in_array($normalizedKey, self::DENIED_PII_KEYS, true)
                && ! ($allowCatalogName && $normalizedKey === 'name');
            if ($isDeniedPii || in_array($normalizedKey, self::GLOBAL_DENIED_KEYS, true)) {
                continue;
            }

            // Check if key is allowlisted.
            if (! array_key_exists($key, $schema) && ! in_array($key, $schema, true)) {
                continue;
            }

            $rule = $schema[$key] ?? true;

            if ($rule === true) {
                $projected[$key] = is_array($value) ? $this->scrubWildcard($value) : $value;
            } elseif (is_array($value) && is_array($rule)) {
                if (array_is_list($value)) {
                    $projected[$key] = array_map(
                        fn ($item): mixed => is_array($item) ? $this->applyAllowlist($item, $rule, $allowCatalogName) : $item,
                        $value
                    );
                } else {
                    $projected[$key] = $this->applyAllowlist($value, $rule, $allowCatalogName);
                }
            } else {
                $projected[$key] = $value;
            }
        }

        return $projected;
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private function scrubWildcard(array $data): array
    {
        $scrubbed = [];

        foreach ($data as $key => $value) {
            $normalizedKey = strtolower((string) $key);
            if (in_array($normalizedKey, self::GLOBAL_DENIED_KEYS, true)
                || in_array($normalizedKey, self::DENIED_PII_KEYS, true)) {
                continue;
            }

            $scrubbed[$key] = is_array($value) ? $this->scrubWildcard($value) : $value;
        }

        return $scrubbed;
    }

    /**
     * Browser-safe projection of proposal review and view envelopes.
     * Preserves and validates the proposal_review structure for browser emission
     * while stripping internal coverage maps, secret tokens, and unprojected keys.
     * Keeps provider projection minimized by ensuring internal structures are not leaked.
     *
     * @param  array<string, mixed>  $rawView
     * @return array<string, mixed>
     */
    public function safeProposalView(array $rawView): array
    {
        $reviewData = $rawView;
        if (isset($rawView['proposal_review']) && is_array($rawView['proposal_review'])) {
            $reviewData = $rawView['proposal_review'];
        } elseif (isset($rawView['proposal_view']) && is_array($rawView['proposal_view'])) {
            $reviewData = $rawView['proposal_view'];
        }

        $projectedReview = $this->sanitizeProposalReview($reviewData, $rawView);

        $hasEnvelopeIdentity = isset($rawView['proposal_id']) || isset($rawView['id']);
        if (! $hasEnvelopeIdentity) {
            return $projectedReview;
        }

        $proposalId = (string) ($rawView['proposal_id'] ?? $rawView['id'] ?? '');
        $runId = (string) ($rawView['run_id'] ?? '');
        $capabilityId = (string) ($rawView['capability_id'] ?? '');
        $status = (string) ($rawView['status'] ?? 'pending');

        $rawSafety = is_array($rawView['safety'] ?? null) ? $rawView['safety'] : [
            'tier' => $rawView['safety_tier'] ?? 't1',
            'confirmation_phrase' => $rawView['confirmation_phrase'] ?? null,
            'requires_reauth' => $rawView['requires_reauth'] ?? false,
            'expires_at' => $rawView['expires_at'] ?? null,
        ];
        $safety = $this->sanitizeSafety($rawSafety);

        $outcome = $projectedReview['preflight']['outcome'] ?? 'unknown';
        $confirmable = (bool) ($rawView['confirmable'] ?? ($status === 'pending' && $outcome === 'pass'));
        $error = isset($rawView['error']) && is_array($rawView['error'])
            ? $this->sanitizePublicError($rawView['error'])
            : null;
        $receipt = isset($rawView['receipt']) && is_array($rawView['receipt'])
            ? $this->sanitizeReceipt($rawView['receipt'])
            : null;
        if (($projectedReview['version'] ?? null) !== 1) {
            return [
                ...$projectedReview,
                'version' => $projectedReview['version'] ?? null,
                'proposal_id' => $proposalId,
                'run_id' => $runId,
                'capability_id' => $capabilityId,
                'status' => $status,
                'confirmable' => false,
                'safety' => $safety,
                'error' => $error,
                'receipt' => $receipt,
                'proposal_review' => $projectedReview,
                'proposal_view' => $projectedReview,
            ];
        }

        return [
            'version' => 1,
            'proposal_id' => $proposalId,
            'run_id' => $runId,
            'capability_id' => $capabilityId,
            'operation' => $projectedReview['operation'],
            'title' => $projectedReview['title'],
            'sections' => $projectedReview['sections'],
            'preflight' => $projectedReview['preflight'],
            'safety' => $safety,
            'status' => $status,
            'confirmable' => $confirmable,
            'error' => $error,
            'receipt' => $receipt,
        ];
    }

    /**
     * @param  array<string, mixed>  $reviewData
     * @param  array<string, mixed>  $fallbackSource
     * @return array<string, mixed>
     */
    private function sanitizeProposalReview(array $reviewData, array $fallbackSource = []): array
    {
        $version = $reviewData['version'] ?? ($fallbackSource['version'] ?? null);
        if ($version !== 1) {
            return $reviewData;
        }
        $operation = (string) ($reviewData['operation'] ?? ($fallbackSource['operation'] ?? 'update'));
        if (! in_array($operation, ['create', 'update', 'transition', 'bulk', 'send'], true)) {
            $operation = 'update';
        }

        $title = (string) ($reviewData['title'] ?? ($fallbackSource['title'] ?? 'Review proposal'));
        if (trim($title) === '') {
            $title = 'Review proposal';
        }

        $rawSections = is_array($reviewData['sections'] ?? null)
            ? $reviewData['sections']
            : (is_array($fallbackSource['sections'] ?? null) ? $fallbackSource['sections'] : []);

        $sections = [];
        foreach ($rawSections as $section) {
            if (is_array($section)) {
                $sanitizedSection = $this->sanitizeSection($section);
                if ($sanitizedSection !== null) {
                    $sections[] = $sanitizedSection;
                }
            }
        }

        $rawPreflight = is_array($reviewData['preflight'] ?? null)
            ? $reviewData['preflight']
            : (is_array($fallbackSource['preflight'] ?? null) ? $fallbackSource['preflight'] : []);
        $preflight = $this->sanitizePreflight($rawPreflight);

        return [
            'version' => $version,
            'operation' => $operation,
            'title' => $title,
            'sections' => $sections,
            'preflight' => $preflight,
        ];
    }

    /**
     * @param  array<string, mixed>  $section
     * @return array<string, mixed>|null
     */
    private function sanitizeSection(array $section): ?array
    {
        $kind = (string) ($section['kind'] ?? '');
        $key = (string) ($section['key'] ?? '');
        $label = (string) ($section['label'] ?? '');
        if ($key === '' || $label === '') {
            return null;
        }

        if ($kind === 'fields') {
            $rawFields = is_array($section['fields'] ?? null) ? $section['fields'] : [];
            $fields = [];
            foreach ($rawFields as $field) {
                if (is_array($field)) {
                    $sanitizedField = $this->sanitizeField($field);
                    if ($sanitizedField !== null) {
                        $fields[] = $sanitizedField;
                    }
                }
            }

            return [
                'kind' => 'fields',
                'key' => $key,
                'label' => $label,
                'fields' => $fields,
            ];
        }

        if ($kind === 'groups') {
            $rawGroups = is_array($section['groups'] ?? null) ? $section['groups'] : [];
            $groups = [];
            foreach ($rawGroups as $group) {
                if (is_array($group)) {
                    $sanitizedGroup = $this->sanitizeGroup($group);
                    if ($sanitizedGroup !== null) {
                        $groups[] = $sanitizedGroup;
                    }
                }
            }

            return [
                'kind' => 'groups',
                'key' => $key,
                'label' => $label,
                'groups' => $groups,
            ];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $field
     * @return array{key: string, label: string, role: string, change: string, before: array<string, mixed>, after: array<string, mixed>}|null
     */
    private function sanitizeField(array $field): ?array
    {
        $key = (string) ($field['key'] ?? '');
        $label = (string) ($field['label'] ?? '');
        if ($key === '' || $label === '') {
            return null;
        }

        $role = (string) ($field['role'] ?? 'choice');
        if (! in_array($role, ['context', 'choice', 'effect'], true)) {
            $role = 'choice';
        }

        $change = (string) ($field['change'] ?? 'unchanged');
        if (! in_array($change, ['unchanged', 'set', 'clear'], true)) {
            $change = 'unchanged';
        }

        $before = $this->sanitizeValue($field['before'] ?? ['type' => 'missing']);
        $after = $this->sanitizeValue($field['after'] ?? ['type' => 'missing']);

        return [
            'key' => $key,
            'label' => $label,
            'role' => $role,
            'change' => $change,
            'before' => $before,
            'after' => $after,
        ];
    }

    /**
     * @param  array<string, mixed>  $group
     * @return array<string, mixed>|null
     */
    private function sanitizeGroup(array $group): ?array
    {
        $key = (string) ($group['key'] ?? '');
        $label = (string) ($group['label'] ?? '');
        if ($key === '' || $label === '') {
            return null;
        }

        $context = $this->sanitizeValue($group['context'] ?? ['type' => 'null']);
        $rawFields = is_array($group['fields'] ?? null) ? $group['fields'] : [];
        $fields = [];
        foreach ($rawFields as $field) {
            if (is_array($field)) {
                $sanitized = $this->sanitizeField($field);
                if ($sanitized !== null) {
                    $fields[] = $sanitized;
                }
            }
        }

        $preflight = $this->sanitizePreflight(is_array($group['preflight'] ?? null) ? $group['preflight'] : []);

        return [
            'key' => $key,
            'label' => $label,
            'context' => $context,
            'fields' => $fields,
            'preflight' => $preflight,
        ];
    }

    /**
     * @param  array<string, mixed>  $preflight
     * @return array{outcome: string, checks: list<array{key: string, label: string, status: string, detail: string|null}>}
     */
    private function sanitizePreflight(array $preflight): array
    {
        $outcome = (string) ($preflight['outcome'] ?? 'unknown');
        if (! in_array($outcome, ['pass', 'fail', 'noop', 'unknown'], true)) {
            $outcome = 'unknown';
        }

        $rawChecks = is_array($preflight['checks'] ?? null) ? $preflight['checks'] : [];
        $checks = [];
        foreach ($rawChecks as $check) {
            if (! is_array($check)) {
                continue;
            }
            $key = (string) ($check['key'] ?? '');
            $label = (string) ($check['label'] ?? '');
            if ($key === '' || $label === '') {
                continue;
            }
            $status = (string) ($check['status'] ?? 'unknown');
            if (! in_array($status, ['pass', 'fail', 'warn', 'noop', 'unknown'], true)) {
                $status = 'unknown';
            }
            $detail = isset($check['detail']) && is_string($check['detail']) ? $check['detail'] : null;

            $checks[] = [
                'key' => $key,
                'label' => $label,
                'status' => $status,
                'detail' => $detail,
            ];
        }

        return [
            'outcome' => $outcome,
            'checks' => $checks,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sanitizeValue(mixed $value, int $depth = 0): array
    {
        if (! is_array($value) || $depth > 12) {
            return ['type' => 'null'];
        }

        $type = (string) ($value['type'] ?? $value['kind'] ?? '');

        return match ($type) {
            'missing' => ['type' => 'missing'],
            'null' => ['type' => 'null'],
            'text' => [
                'type' => 'text',
                'value' => (string) ($value['value'] ?? ''),
                'multiline' => (bool) ($value['multiline'] ?? false),
            ],
            'code' => [
                'type' => 'code',
                'value' => (string) ($value['value'] ?? ''),
            ],
            'integer' => [
                'type' => 'integer',
                'value' => (int) ($value['value'] ?? 0),
                'semantic' => in_array($value['semantic'] ?? '', ['count', 'integer', 'raw_score'], true) ? $value['semantic'] : 'integer',
                'unit' => isset($value['unit']) && is_string($value['unit']) ? $value['unit'] : null,
            ],
            'boolean' => [
                'type' => 'boolean',
                'value' => (bool) ($value['value'] ?? false),
                'true_label' => (string) ($value['true_label'] ?? 'Yes'),
                'false_label' => (string) ($value['false_label'] ?? 'No'),
            ],
            'enum' => [
                'type' => 'enum',
                'code' => (string) ($value['code'] ?? ''),
                'label' => (string) ($value['label'] ?? ''),
            ],
            'date' => [
                'type' => 'date',
                'value' => (string) ($value['value'] ?? ''),
            ],
            'instant' => [
                'type' => 'instant',
                'value' => (string) ($value['value'] ?? ''),
                'precision' => 'second',
                'display_zone' => 'Asia/Manila',
            ],
            'interval' => [
                'type' => 'interval',
                'start' => $this->sanitizeValue($value['start'] ?? ['type' => 'instant', 'value' => '', 'precision' => 'second', 'display_zone' => 'Asia/Manila'], $depth + 1),
                'end' => $this->sanitizeValue($value['end'] ?? ['type' => 'instant', 'value' => '', 'precision' => 'second', 'display_zone' => 'Asia/Manila'], $depth + 1),
            ],
            'duration' => [
                'type' => 'duration',
                'value' => (int) ($value['value'] ?? 0),
                'unit' => 'minute',
            ],
            'record' => [
                'type' => 'record',
                'label' => (string) ($value['label'] ?? ''),
                'context' => array_values(array_map('strval', is_array($value['context'] ?? null) ? $value['context'] : [])),
            ],
            'list' => [
                'type' => 'list',
                'items' => array_values(array_map(
                    fn ($item): array => $this->sanitizeValue($item, $depth + 1),
                    is_array($value['items'] ?? null) ? $value['items'] : []
                )),
                'empty_label' => (string) ($value['empty_label'] ?? 'None'),
            ],
            default => ['type' => 'null'],
        };
    }

    /**
     * @param  array<string, mixed>  $safety
     * @return array{tier: string, confirmation_phrase: string|null, requires_reauth: bool, expires_at: string}
     */
    private function sanitizeSafety(array $safety): array
    {
        $tier = (string) ($safety['tier'] ?? 't1');
        if (! in_array($tier, ['t1', 't2', 't3'], true)) {
            $tier = 't1';
        }
        $phrase = isset($safety['confirmation_phrase']) && is_string($safety['confirmation_phrase']) && trim($safety['confirmation_phrase']) !== ''
            ? trim($safety['confirmation_phrase'])
            : null;
        $requiresReauth = (bool) ($safety['requires_reauth'] ?? false);
        $expiresAt = is_string($safety['expires_at'] ?? null) && trim($safety['expires_at']) !== ''
            ? $safety['expires_at']
            : now()->addMinutes(10)->toIso8601String();

        return [
            'tier' => $tier,
            'confirmation_phrase' => $phrase,
            'requires_reauth' => $requiresReauth,
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $error
     * @return array{code: string, category: string, message: string, remediation: string}|null
     */
    private function sanitizePublicError(?array $error): ?array
    {
        if ($error === null) {
            return null;
        }
        $code = (string) ($error['code'] ?? 'unknown_error');
        $category = (string) ($error['category'] ?? 'failed');
        if (! in_array($category, ['denied', 'invalid', 'stale', 'conflict', 'expired', 'unavailable', 'failed', 'unsupported', 'retired'], true)) {
            $category = 'failed';
        }
        $message = (string) ($error['message'] ?? 'An error occurred.');
        $remediation = (string) ($error['remediation'] ?? 'none');
        if (! in_array($remediation, ['repropose', 'reauthenticate', 'refresh', 'manual', 'reconcile', 'none'], true)) {
            $remediation = 'none';
        }

        return [
            'code' => $code,
            'category' => $category,
            'message' => $message,
            'remediation' => $remediation,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $receipt
     * @return array{receipt_id: string, outcome_label: string, confirmed_at: string, references: list<array{id: string, label: string, href: string, version: int}>}|null
     */
    private function sanitizeReceipt(?array $receipt): ?array
    {
        if ($receipt === null) {
            return null;
        }
        $receiptId = (string) ($receipt['receipt_id'] ?? $receipt['id'] ?? '');
        $outcomeLabel = (string) ($receipt['outcome_label'] ?? 'Confirmed');
        $confirmedAt = (string) ($receipt['confirmed_at'] ?? now()->toIso8601String());

        $rawRefs = is_array($receipt['references'] ?? null) ? $receipt['references'] : [];
        $references = [];
        foreach ($rawRefs as $ref) {
            if (! is_array($ref)) {
                continue;
            }
            $id = (string) ($ref['id'] ?? '');
            $label = (string) ($ref['label'] ?? '');
            $href = (string) ($ref['href'] ?? '');
            if ($id !== '' && $label !== '' && str_starts_with($href, '/')) {
                $references[] = [
                    'id' => $id,
                    'label' => $label,
                    'href' => $href,
                    'version' => 1,
                ];
            }
        }

        return [
            'receipt_id' => $receiptId,
            'outcome_label' => $outcomeLabel,
            'confirmed_at' => $confirmedAt,
            'references' => $references,
        ];
    }
}
