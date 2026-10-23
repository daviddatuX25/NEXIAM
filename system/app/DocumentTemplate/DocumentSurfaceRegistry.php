<?php

namespace App\DocumentTemplate;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\DocumentTemplate\Contracts\DocumentSurfaceInterface;
use App\Models\User;
use App\Reports\Surfaces\AdmissionSlipSurface;
use App\Reports\Surfaces\ApplicationsCensusSurface;
use App\Reports\Surfaces\ClearanceSlipSurface;
use App\Reports\Surfaces\CollegeProgramQuotaSummarySurface;
use App\Reports\Surfaces\CourseQuotaQualifiersSurface;
use App\Reports\Surfaces\CustomDocumentSurface;
use App\Reports\Surfaces\DailyAdmissionsLogbookSurface;
use App\Reports\Surfaces\ExamAttendanceSurface;
use App\Reports\Surfaces\ExamSessionAttendanceSurface;
use App\Reports\Surfaces\FeederHighSchoolOriginSurface;
use App\Reports\Surfaces\InstitutionalSummarySurface;
use App\Reports\Surfaces\PassingReportSurface;
use App\Reports\Surfaces\RoomUtilizationSurface;
use App\Reports\Surfaces\ScoreDistributionSurface;
use App\Reports\Surfaces\StaffAccomplishmentSurface;
use App\Reports\Surfaces\StaffVoucherSlipSurface;

final class DocumentSurfaceRegistry
{
    /**
     * @return array<string, array{
     *   surface_key: string,
     *   report_type: string,
     *   office: string,
     *   office_label: string,
     *   class: class-string<DocumentSurfaceInterface>
     * }>
     */
    public static function definitions(): array
    {
        return [
            'course_quota_qualifiers' => [
                'surface_key' => 'course_quota_qualifiers',
                'report_type' => 'course-quota-qualifiers',
                'office' => 'registrar',
                'office_label' => 'Registrar Office',
                'class' => CourseQuotaQualifiersSurface::class,
            ],
            'applications_census' => [
                'surface_key' => 'applications_census',
                'report_type' => 'applications-census',
                'office' => 'registrar',
                'office_label' => 'Registrar Office',
                'class' => ApplicationsCensusSurface::class,
            ],
            'feeder_high_school_origin' => [
                'surface_key' => 'feeder_high_school_origin',
                'report_type' => 'feeder-school-origin',
                'office' => 'registrar',
                'office_label' => 'Registrar Office',
                'class' => FeederHighSchoolOriginSurface::class,
            ],
            'daily_admissions_logbook' => [
                'surface_key' => 'daily_admissions_logbook',
                'report_type' => 'daily-admissions-logbook',
                'office' => 'registrar',
                'office_label' => 'Registrar Office',
                'class' => DailyAdmissionsLogbookSurface::class,
            ],
            'admission_slip' => [
                'surface_key' => 'admission_slip',
                'report_type' => 'admission-slip',
                'office' => 'registrar',
                'office_label' => 'Registrar Office',
                'class' => AdmissionSlipSurface::class,
            ],
            'clearance_slip' => [
                'surface_key' => 'clearance_slip',
                'report_type' => 'clearance-slip',
                'office' => 'registrar',
                'office_label' => 'Registrar Office',
                'class' => ClearanceSlipSurface::class,
            ],
            'passing_report' => [
                'surface_key' => 'passing_report',
                'report_type' => 'passing-report',
                'office' => 'guidance',
                'office_label' => 'Guidance Office',
                'class' => PassingReportSurface::class,
            ],
            'exam_attendance' => [
                'surface_key' => 'exam_attendance',
                'report_type' => 'exam-attendance',
                'office' => 'guidance',
                'office_label' => 'Guidance Office',
                'class' => ExamAttendanceSurface::class,
            ],
            'session_attendance_sheet' => [
                'surface_key' => 'session_attendance_sheet',
                'report_type' => 'session-attendance-sheet',
                'office' => 'guidance',
                'office_label' => 'Guidance Office',
                'class' => ExamSessionAttendanceSurface::class,
            ],
            'room_utilization' => [
                'surface_key' => 'room_utilization',
                'report_type' => 'room-utilization',
                'office' => 'guidance',
                'office_label' => 'Guidance Office',
                'class' => RoomUtilizationSurface::class,
            ],
            'score_distribution' => [
                'surface_key' => 'score_distribution',
                'report_type' => 'score-distribution',
                'office' => 'guidance',
                'office_label' => 'Guidance Office',
                'class' => ScoreDistributionSurface::class,
            ],
            'institutional_executive_summary' => [
                'surface_key' => 'institutional_executive_summary',
                'report_type' => 'institutional-executive-summary',
                'office' => 'admin',
                'office_label' => 'Admin Office',
                'class' => InstitutionalSummarySurface::class,
            ],
            'staff_accomplishment_report' => [
                'surface_key' => 'staff_accomplishment_report',
                'report_type' => 'staff-accomplishment-report',
                'office' => 'admin',
                'office_label' => 'Admin Office',
                'class' => StaffAccomplishmentSurface::class,
            ],
            'staff_voucher_slip' => [
                'surface_key' => 'staff_voucher_slip',
                'report_type' => 'staff-voucher-slip',
                'office' => 'admin',
                'office_label' => 'HR & Admin Office',
                'class' => StaffVoucherSlipSurface::class,
            ],
            'college_program_quota_summary' => [
                'surface_key' => 'college_program_quota_summary',
                'report_type' => 'college-program-quota-summary',
                'office' => 'program',
                'office_label' => 'Academic Leadership',
                'class' => CollegeProgramQuotaSummarySurface::class,
            ],
            'custom_document' => [
                'surface_key' => 'custom_document',
                'report_type' => 'custom-document',
                'office' => 'custom',
                'office_label' => 'Custom Office Document',
                'class' => CustomDocumentSurface::class,
            ],
        ];
    }

    /** @return array<string, array{surface_key: string, report_type: string, office: string, office_label: string, class: class-string<DocumentSurfaceInterface>}> */
    public static function systemDefinitions(): array
    {
        return array_filter(self::definitions(), static fn (array $definition): bool => $definition['surface_key'] !== 'custom_document');
    }

    /** @return list<array<string, mixed>> */
    public static function baselineInventory(): array
    {
        return BaselineInventoryCatalog::all();
    }

    /** @return list<array<string, mixed>> */
    public static function officePacks(?string $office = null): array
    {
        return OfficeDocumentPackCatalog::packsForOffice($office);
    }

    public static function has(string $surfaceKey): bool
    {
        return isset(self::definitions()[$surfaceKey]);
    }

    /**
     * @return array{surface_key: string, report_type: string, office: string, office_label: string, class: class-string<DocumentSurfaceInterface>}|null
     */
    public static function definition(string $surfaceKey): ?array
    {
        return self::definitions()[$surfaceKey] ?? null;
    }

    public static function resolve(string $surfaceKey): ?DocumentSurfaceInterface
    {
        $definition = self::definition($surfaceKey);
        if ($definition === null || ! class_exists($definition['class'])) {
            return null;
        }

        return app($definition['class']);
    }

    public static function resolveByReportType(string $reportType): ?DocumentSurfaceInterface
    {
        foreach (self::definitions() as $definition) {
            if ($definition['report_type'] === $reportType || $definition['surface_key'] === $reportType) {
                return self::resolve($definition['surface_key']);
            }
        }

        return null;
    }

    public static function canViewSurface(CapabilityAuthorizer $authorizer, ?User $user, string $surfaceKey): bool
    {
        if ($user === null) {
            return false;
        }

        $definition = self::definition($surfaceKey);
        if ($definition === null) {
            return false;
        }

        $office = $definition['office'] === 'custom' ? $user->actorScope()->office : $definition['office'];
        if (! in_array($office, ['registrar', 'guidance', 'admin', 'program'], true)
            || ($definition['office'] !== 'custom' && $user->actorScope()->office !== $office && ! $authorizer->allows($user, CapabilityCatalog::EXECUTIVE_DASHBOARD_VIEW))) {
            return false;
        }

        return match ($office) {
            'registrar' => $authorizer->allows($user, CapabilityCatalog::REGISTRAR_REPORTS_VIEW)
                || (in_array($surfaceKey, ['admission_slip', 'clearance_slip'], true)
                    && $authorizer->allows($user, CapabilityCatalog::REGISTRAR_APPLICATIONS_VIEW))
                || $authorizer->allows($user, CapabilityCatalog::EXECUTIVE_DASHBOARD_VIEW),
            'guidance' => $authorizer->allows($user, CapabilityCatalog::GUIDANCE_REPORTS_VIEW)
                || $authorizer->allows($user, CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG)
                || $authorizer->allows($user, CapabilityCatalog::EXECUTIVE_DASHBOARD_VIEW),
            'admin' => $authorizer->allowsAny($user, [
                CapabilityCatalog::SETUP_INSTITUTION_MANAGE,
                CapabilityCatalog::EXECUTIVE_DASHBOARD_VIEW,
                CapabilityCatalog::HR_STAFF_MANAGE,
            ]),
            'program' => $authorizer->allowsAny($user, [
                CapabilityCatalog::PROGRAM_HEAD_VIEW,
                CapabilityCatalog::EXECUTIVE_DASHBOARD_VIEW,
            ]),
            default => false,
        };
    }

    public static function canAuthorSurface(CapabilityAuthorizer $authorizer, ?User $user, string $surfaceKey): bool
    {
        if ($user === null) {
            return false;
        }

        $definition = self::definition($surfaceKey);
        if ($definition === null) {
            return false;
        }

        $office = $definition['office'] === 'custom' ? $user->actorScope()->office : $definition['office'];
        if (! in_array($office, ['registrar', 'guidance', 'admin', 'program'], true)
            || ($definition['office'] !== 'custom' && $user->actorScope()->office !== $office && ! $authorizer->allows($user, CapabilityCatalog::SETUP_INSTITUTION_MANAGE))) {
            return false;
        }

        return match ($office) {
            'registrar' => $authorizer->allows($user, CapabilityCatalog::REGISTRAR_REPORTS_VIEW)
                && $authorizer->allowsAny($user, [
                    CapabilityCatalog::SETUP_INSTITUTION_MANAGE,
                    CapabilityCatalog::REGISTRAR_SETUP_MANAGE,
                    CapabilityCatalog::REGISTRAR_REQUIREMENTS_MANAGE,
                ]),
            'guidance' => $authorizer->allows($user, CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG)
                && $authorizer->allows($user, CapabilityCatalog::GUIDANCE_REPORTS_VIEW),
            'admin' => $authorizer->allowsAny($user, [
                CapabilityCatalog::SETUP_INSTITUTION_MANAGE,
                CapabilityCatalog::EXECUTIVE_DASHBOARD_VIEW,
                CapabilityCatalog::HR_STAFF_MANAGE,
            ]),
            'program' => $authorizer->allowsAny($user, [
                CapabilityCatalog::SETUP_INSTITUTION_MANAGE,
                CapabilityCatalog::PROGRAM_HEAD_VIEW,
            ]),
            default => false,
        };
    }
}
