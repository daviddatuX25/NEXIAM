<?php

namespace App\DocumentTemplate;

use App\Models\User;

final class DocumentFieldPolicy
{
    public const DESK_REGISTRAR = 'registrar';

    public const DESK_GUIDANCE = 'guidance';

    public const DESK_TESTING = 'testing';

    public const DESK_ADMIN = 'admin';

    /** @var array<string, string> */
    private const REFERENCE_ALIASES = [
        'academic_cycle' => 'academic_cycle_name',
        'prepared_by' => 'prepared_by_name',
        'reviewed_by' => 'prepared_by_name',
        'approved_by' => 'prepared_by_name',
        'report_date' => 'generated_at',
        'examinee_number' => 'reference_code',
        'sequence_number' => 'reference_code',
        'cutoff_score' => 'total_raw_score',
        'target_quota' => 'quota_capacity',
        'accepted_count' => 'qualified_count',
        'committed_reservations' => 'qualified_count',
        'remaining_slots' => 'remaining_quota_slots',
        'remaining_quota' => 'remaining_quota_slots',
        'fill_rate' => 'remaining_quota_slots',
        'committed_fill_rate' => 'remaining_quota_slots',
        'institution_code' => 'document_code',
        'passers' => 'passing_or_score_roster',
        'passers.reference_number' => 'passing_or_score_roster',
        'passers.reference_code' => 'passing_or_score_roster',
        'passers.applicant_name' => 'passing_or_score_roster',
        'passers.score' => 'passing_or_score_roster',
        'passers.program' => 'passing_or_score_roster',
        'passers.stanine' => 'passing_or_score_roster',
        'passers.status' => 'passing_or_score_roster',
        'clearance_status' => 'eligibility_status',
        'receipt_number' => 'clearance_reference_no',
        'confirmed_at' => 'cleared_at',
        'verified_by' => 'cleared_by_staff_name',
        'exam_time' => 'exam_time_window',
        'admission_status' => 'application_status',
        'qualifiers' => 'qualifiers_or_clearance_roster',
        'total_examinees' => 'qualified_count',
        'total_passed' => 'qualified_count',
        'passing_rate' => 'remaining_quota_slots',
        'mean_score' => 'total_raw_score',
        'title' => 'document_title',
        'subtitle' => 'academic_cycle_name',
        'align' => 'document_title',
        'items' => 'quota_capacity',
        'repeat_key' => 'qualifiers_or_clearance_roster',
        'columns' => 'qualifiers_or_clearance_roster',
        'label' => 'prepared_by_name',
        'binding' => 'prepared_by_name',
        'text' => 'document_title',
        'caption' => 'document_title',
        'source' => 'document_title',
        'pg_i' => 'page_i',
        'pg_all' => 'page_all',
        'school_short' => 'last_school_short',
        'history_short' => 'strand_or_prev_course_short',
        'strand_or_prev_course' => 'strand_or_prev_course',
        'strand' => 'shs_strand',
        'verification_qr' => 'clearance_reference_no',
        'custom_heading' => 'document_title',
        'custom_note' => 'document_title',
        'custom_rows' => 'qualifiers_or_clearance_roster',
        'total_applications' => 'qualified_count',
        'pending_count' => 'qualified_count',
        'needs_correction_count' => 'qualified_count',
        'dismissed_count' => 'qualified_count',
        'applications' => 'qualifiers_or_clearance_roster',
        'feeder_schools' => 'qualifiers_or_clearance_roster',
        'sessions' => 'qualifiers_or_clearance_roster',
        'rooms' => 'qualifiers_or_clearance_roster',
        'subtests' => 'qualifiers_or_clearance_roster',
        'accomplishments' => 'qualifiers_or_clearance_roster',
        'programs' => 'qualifiers_or_clearance_roster',
        'examinees' => 'qualifiers_or_clearance_roster',
        'admissions_log' => 'qualifiers_or_clearance_roster',
        'total_assigned' => 'qualified_count',
        'present_count' => 'qualified_count',
        'absent_count' => 'qualified_count',
        'total_registered' => 'qualified_count',
        'total_present' => 'qualified_count',
        'total_absent' => 'qualified_count',
        'total_sessions' => 'quota_capacity',
        'total_rooms' => 'quota_capacity',
        'total_capacity' => 'quota_capacity',
        'total_examinees_seated' => 'qualified_count',
        'overall_attendance_rate' => 'remaining_quota_slots',
        'turnout_rate' => 'remaining_quota_slots',
        'total_subtests' => 'quota_capacity',
        'total_scores_recorded' => 'qualified_count',
        'overall_pass_rate' => 'remaining_quota_slots',
        'total_schools' => 'quota_capacity',
        'total_applicants' => 'qualified_count',
        'total_intake_today' => 'qualified_count',
        'accepted_today' => 'qualified_count',
        'pending_today' => 'qualified_count',
        'needs_correction_today' => 'qualified_count',
        'total_milestones' => 'quota_capacity',
        'total_operations_volume' => 'qualified_count',
        'total_audit_events' => 'qualified_count',
        'total_programs' => 'quota_capacity',
        'total_planned_quota' => 'quota_capacity',
        'total_placed_candidates' => 'qualified_count',
        'total_first_choice_demand' => 'qualified_count',
        'overall_fill_rate' => 'remaining_quota_slots',
        'office_label' => 'issuing_office_name',
        'staff_name' => 'prepared_by_name',
        'staff_email' => 'prepared_by_name',
        'staff_role' => 'document_title',
        'public_reference' => 'document_code',
        'fallback_code' => 'document_code',
        'validity_badge' => 'document_title',
        'purpose_label' => 'document_title',
        'activation_url' => 'document_code',
        'issued_at' => 'generated_at',
        'issued_by_name' => 'prepared_by_name',
        'voucher_qr' => 'document_code',
    ];

    /** @var array<string, list<string>> */
    private const GRAINS_BY_DESK = [
        self::DESK_REGISTRAR => [MountSlotRegistry::GRAIN_STUDENT, MountSlotRegistry::GRAIN_CYCLE_PROGRAM],
        self::DESK_GUIDANCE => [MountSlotRegistry::GRAIN_STUDENT, MountSlotRegistry::GRAIN_SESSION, MountSlotRegistry::GRAIN_CYCLE_PROGRAM],
        self::DESK_TESTING => [MountSlotRegistry::GRAIN_STUDENT, MountSlotRegistry::GRAIN_SESSION, MountSlotRegistry::GRAIN_CYCLE_PROGRAM],
        self::DESK_ADMIN => [MountSlotRegistry::GRAIN_CYCLE_PROGRAM, MountSlotRegistry::GRAIN_STAFF],
    ];

    /** @var array<string, list<string>> */
    private const BRANCHES_BY_DESK = [
        self::DESK_REGISTRAR => [
            'institution_metadata',
            'academic_cycle_and_programs',
            'applicant_profile',
            'requirements_and_clearance',
        ],
        self::DESK_GUIDANCE => [
            'institution_metadata',
            'academic_cycle_and_programs',
            'applicant_profile',
            'exam_logistics',
            'psychometric_scores',
            'guidance_consultation',
        ],
        self::DESK_TESTING => [
            'institution_metadata',
            'academic_cycle_and_programs',
            'exam_logistics',
        ],
        self::DESK_ADMIN => [
            'institution_metadata',
            'academic_cycle_and_programs',
        ],
    ];

    /** @var array<string, list<string>> */
    private const ADDITIONAL_LEAVES_BY_DESK = [
        self::DESK_REGISTRAR => [
            'exam_session_code',
            'exam_session_label',
            'exam_date',
            'exam_time_window',
            'building_name',
            'room_name',
        ],
        self::DESK_GUIDANCE => [],
        self::DESK_TESTING => [
            'reference_code',
            'examinee_number',
            'sequence_number',
            'applicant_name',
        ],
        self::DESK_ADMIN => [],
    ];

    /**
     * Return the leaves available to an active staff actor for the requested desk and grain.
     *
     * @return list<string>
     */
    public function allowedFor(User $actor, string $deskKey, string $grain): array
    {
        $customDocument = $deskKey === 'custom_document';
        $desk = $customDocument
            ? $this->deskForOffice($actor->actorScope()->office)
            : $this->resolveDeskKey($deskKey);

        if (
            $desk === null
            || ($customDocument
                ? ! in_array($grain, $this->allowedGrainsForSurface($deskKey, $desk), true)
                : ! in_array($grain, self::GRAINS_BY_DESK[$desk], true))
            || ! $this->actorBelongsToDesk($actor, $desk)
        ) {
            return [];
        }

        $leafIds = [];

        foreach (self::BRANCHES_BY_DESK[$desk] as $branchId) {
            $leafIds = array_merge($leafIds, MasterDataFieldCatalog::leafIdsForBranch($branchId));
        }
        if ($grain !== MountSlotRegistry::GRAIN_STUDENT) {
            $leafIds = array_values(array_diff($leafIds, [
                'last_school_enrolled',
                'last_school_short',
                'strand_or_prev_course',
                'strand_or_prev_course_short',
            ]));
        }

        return array_values(array_unique(array_merge(
            $leafIds,
            self::ADDITIONAL_LEAVES_BY_DESK[$desk],
            $customDocument ? ['document_title'] : [],
            $grain === MountSlotRegistry::GRAIN_STUDENT
                ? ['last_school_enrolled', 'last_school_short', 'strand_or_prev_course', 'strand_or_prev_course_short']
                : [],
        )));
    }

    /**
     * @return list<string>
     */
    public function allowedGrainsForDesk(string $desk, string $surfaceKey): array
    {
        $resolvedDesk = $this->deskForOffice($desk) ?? $this->resolveDeskKey($desk);

        return $this->allowedGrainsForSurface($surfaceKey, $resolvedDesk);
    }

    public function defaultGrainForSurface(User $user, string $surfaceKey): string
    {
        if ($surfaceKey === 'custom_document') {
            $desk = $this->deskForOffice($user->actorScope()->office);
            $allowedGrains = $this->allowedGrainsForSurface($surfaceKey, $desk);

            return $allowedGrains[0] ?? MountSlotRegistry::GRAIN_STUDENT;
        }

        return OfficeDocumentPackCatalog::forSurface($surfaceKey)['grain']
            ?? MountSlotRegistry::GRAIN_CYCLE_PROGRAM;
    }

    /**
     * @return list<string>
     */
    public function allowedGrainsForSurface(string $surfaceKey, ?string $desk = null): array
    {
        if ($surfaceKey === 'custom_document') {
            return $desk !== null && isset(self::GRAINS_BY_DESK[$desk])
                ? self::GRAINS_BY_DESK[$desk]
                : [MountSlotRegistry::GRAIN_STUDENT, MountSlotRegistry::GRAIN_SESSION, MountSlotRegistry::GRAIN_CYCLE_PROGRAM];
        }

        $resolvedDesk = $this->resolveDeskKey($surfaceKey);

        return $resolvedDesk === null ? [] : self::GRAINS_BY_DESK[$resolvedDesk];
    }

    /**
     * Keep requested leaf IDs that are registered and authorized, preserving request order.
     * Repeated IDs are returned once.
     *
     * @param  array<int|string, mixed>  $requestedLeafIds
     * @return list<string>
     */
    public function filterLeaves(User $actor, string $deskKey, string $grain, array $requestedLeafIds): array
    {
        $allowed = array_fill_keys($this->allowedFor($actor, $deskKey, $grain), true);
        $filtered = [];

        foreach ($requestedLeafIds as $leafId) {
            if (is_string($leafId) && isset($allowed[$leafId])) {
                $filtered[$leafId] = $leafId;
            }
        }

        return array_values($filtered);
    }

    /**
     * Validate that every macro reference resolves to an authorized catalog leaf.
     * A qualified repeater reference such as `session_examinees.applicant_name` is checked
     * against its repeater leaf (`session_examinees`).
     *
     * @param  array<int|string, mixed>  $references
     */
    public function validateReferences(User $actor, string $deskKey, string $grain, array $references): bool
    {
        $allowed = $this->allowedFor($actor, $deskKey, $grain);
        if ($allowed === []) {
            return false;
        }

        $allowedLookup = array_fill_keys($allowed, true);

        foreach ($references as $reference) {
            if (! is_string($reference)) {
                return false;
            }

            $leafId = $this->canonicalReferenceLeafId($reference);
            if ($leafId === null || ! isset($allowedLookup[$leafId])) {
                return false;
            }
        }

        return true;
    }

    private function actorBelongsToDesk(User $actor, string $desk): bool
    {
        $scope = $actor->actorScope();
        if ($scope->institutionId === null) {
            return false;
        }

        $requiredOffice = match ($desk) {
            self::DESK_TESTING => 'guidance',
            self::DESK_REGISTRAR => 'registrar',
            self::DESK_GUIDANCE => 'guidance',
            self::DESK_ADMIN => 'admin',
            default => null,
        };

        return $requiredOffice !== null && $scope->office === $requiredOffice;
    }

    private function resolveDeskKey(string $deskKey): ?string
    {
        $normalized = self::normalizeKey($deskKey);

        if (isset(self::GRAINS_BY_DESK[$normalized])) {
            return $normalized;
        }

        foreach (DocumentSurfaceRegistry::definitions() as $surfaceKey => $definition) {
            if (self::normalizeKey($surfaceKey) === $normalized) {
                return $this->deskForOffice($definition['office']);
            }
        }

        foreach (OfficeDocumentPackCatalog::builtinDefinitions() as $definition) {
            foreach (['key', 'pack_key', 'surface_key'] as $field) {
                $candidate = $definition[$field] ?? null;
                if (is_string($candidate) && self::normalizeKey($candidate) === $normalized) {
                    return $this->deskForOffice($definition['office'] ?? null);
                }
            }
        }

        return null;
    }

    private static function normalizeKey(string $key): string
    {
        return str_replace('-', '_', strtolower(trim($key)));
    }

    private function deskForOffice(mixed $office): ?string
    {
        return match ($office) {
            'registrar' => self::DESK_REGISTRAR,
            'guidance' => self::DESK_GUIDANCE,
            'admin' => self::DESK_ADMIN,
            'testing' => self::DESK_TESTING,
            default => null,
        };
    }

    public function canonicalReferenceLeafId(string $reference): ?string
    {
        $reference = trim($reference, "{} \t\n\r\0\x0B");

        if (isset(self::REFERENCE_ALIASES[$reference])) {
            return self::REFERENCE_ALIASES[$reference];
        }

        if (str_starts_with($reference, 'passers.')) {
            return 'passing_or_score_roster';
        }

        if (str_starts_with($reference, 'qualifiers.')
            || str_starts_with($reference, 'custom_rows.')
            || str_starts_with($reference, 'applications.')
            || str_starts_with($reference, 'feeder_schools.')
            || str_starts_with($reference, 'sessions.')
            || str_starts_with($reference, 'rooms.')
            || str_starts_with($reference, 'subtests.')
            || str_starts_with($reference, 'accomplishments.')
            || str_starts_with($reference, 'programs.')
            || str_starts_with($reference, 'examinees.')
            || str_starts_with($reference, 'admissions_log.')) {
            return 'qualifiers_or_clearance_roster';
        }
        $leaf = MasterDataFieldCatalog::findLeaf($reference);
        if ($leaf !== null) {
            return $reference;
        }

        $separator = strpos($reference, '.');
        if ($separator === false) {
            return null;
        }

        $repeaterId = substr($reference, 0, $separator);
        $childKey = substr($reference, $separator + 1);
        if ($childKey === '' || str_contains($childKey, '.')) {
            return null;
        }

        $repeater = MasterDataFieldCatalog::findLeaf($repeaterId);

        return ($repeater['kind'] ?? null) === 'repeater' ? $repeaterId : null;
    }
}
