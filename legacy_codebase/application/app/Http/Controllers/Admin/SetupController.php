<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\AptitudeArea;
use App\Models\Course;
use App\Models\PrivacyPolicy;
use App\Models\RatingScale;
use App\Models\ResultSheetTemplate;
use App\Models\Role;
use App\Models\Room;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SetupController extends Controller
{
    /**
     * Show the setup hub — role-filtered card grid for all configuration pages.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('Admin/Setup/Index', [
            'allowDirectAssessment' => SystemSetting::allowDirectAssessment(),
            'aiCompanionEnabled' => SystemSetting::aiCompanionEnabled(),
            'health' => $this->computeHealth(),
        ]);
    }

    /**
     * Compute setup health checks grouped by pipeline stage.
     *
     * Each check returns:
     *  - key: unique identifier
     *  - label: human-readable label
     *  - passed: bool
     *  - severity: 'critical' | 'important' | 'optional'
     *  - message: contextual description
     *
     * @return array{categories: array, overall: array{score: int, total: int, percentage: int}}
     */
    private function computeHealth(): array
    {
        $activeYear = AcademicYear::active();

        $categories = [
            $this->checkAcademicYears($activeYear),
            $this->checkCourses(),
            $this->checkRooms(),
            $this->checkAptitudeAreas(),
            $this->checkResultSheetTemplates(),
            $this->checkAdmissionSlipTemplates(),
            $this->checkPrivacyPolicies(),
            $this->checkStaffAccounts(),
            $this->checkInstitution(),
            $this->checkRatingScales(),
        ];

        $allChecks = collect($categories)->pluck('checks')->flatten(1);
        $passed = $allChecks->where('passed', true)->count();
        $total = $allChecks->count();

        return [
            'categories' => $categories,
            'overall' => [
                'score' => $passed,
                'total' => $total,
                'percentage' => $total > 0 ? (int) round(($passed / $total) * 100) : 0,
            ],
        ];
    }

    /**
     * Academic Year health: existence, activation, window configuration, and window status.
     *
     * @return array{key: string, label: string, href: string, checks: list<array>}
     */
    private function checkAcademicYears(?AcademicYear $activeYear): array
    {
        $totalYears = AcademicYear::count();
        $hasActive = $activeYear !== null;
        $hasStartDate = $hasActive && $activeYear->application_start_date !== null;
        $hasEndDate = $hasActive && $activeYear->application_end_date !== null;
        $hasWindow = $hasStartDate && $hasEndDate;

        // Derive window status flags
        $windowExpired = false;
        $windowNotStarted = false;
        $windowOpen = false;

        if ($hasActive && $hasEndDate && $activeYear->application_end_date->isPast()) {
            $windowExpired = true;
        } elseif ($hasActive && $hasStartDate && $activeYear->application_start_date->isFuture()) {
            $windowNotStarted = true;
        } elseif ($hasActive) {
            $windowOpen = $activeYear->isApplicationWindowOpen();
        }

        // Build window status message
        $windowStatusMessage = 'No active academic year to check.';
        if ($hasActive) {
            if (! $hasWindow) {
                $windowStatusMessage = 'Application window dates are not fully configured.';
            } elseif ($windowExpired) {
                $windowStatusMessage = sprintf(
                    'Window expired on %s — applicants cannot submit.',
                    $activeYear->application_end_date->format('M j, Y')
                );
            } elseif ($windowNotStarted) {
                $windowStatusMessage = sprintf(
                    'Window opens on %s — not yet accepting applications.',
                    $activeYear->application_start_date->format('M j, Y')
                );
            } elseif ($windowOpen) {
                $windowStatusMessage = sprintf(
                    'Accepting applications until %s.',
                    $activeYear->application_end_date->format('M j, Y')
                );
            }
        }

        return [
            'key' => 'academic_years',
            'label' => 'Academic Years',
            'href' => '/admin/academic-years',
            'checks' => [
                [
                    'key' => 'ay_exists',
                    'label' => 'At least one academic year created',
                    'passed' => $totalYears > 0,
                    'severity' => 'critical',
                    'message' => $totalYears > 0
                        ? "{$totalYears} academic year(s) configured."
                        : 'No academic years exist. Create one to begin accepting applications.',
                ],
                [
                    'key' => 'ay_active',
                    'label' => 'An academic year is activated',
                    'passed' => $hasActive,
                    'severity' => 'critical',
                    'message' => $hasActive
                        ? "Active: {$activeYear->academic_year} — {$activeYear->semesterLabel()}"
                        : 'No academic year is active. Activate one to enable the admissions pipeline.',
                ],
                [
                    'key' => 'ay_window_configured',
                    'label' => 'Application window dates are set',
                    'passed' => $hasWindow,
                    'severity' => 'important',
                    'message' => $hasWindow
                        ? "Window: {$activeYear->applicationWindowLabel()}"
                        : ($hasActive
                            ? 'Application start and/or end dates are missing on the active year.'
                            : 'No active academic year — configure one first.'),
                ],
                [
                    'key' => 'ay_window_not_expired',
                    'label' => 'Application window is not expired',
                    'passed' => $hasActive && $hasWindow && ! $windowExpired,
                    'severity' => 'important',
                    'message' => $windowStatusMessage,
                ],
                [
                    'key' => 'ay_window_open',
                    'label' => 'Application window is currently open',
                    'passed' => $windowOpen,
                    'severity' => 'optional',
                    'message' => $windowStatusMessage,
                ],
            ],
        ];
    }

    /**
     * Courses: existence, at least one active, and reasonable count.
     *
     * @return array{key: string, label: string, href: string, checks: list<array>}
     */
    private function checkCourses(): array
    {
        $total = Course::count();
        $active = Course::where('is_active', true)->count();

        return [
            'key' => 'courses',
            'label' => 'Programs & Courses',
            'href' => '/admin/courses',
            'checks' => [
                [
                    'key' => 'courses_exist',
                    'label' => 'At least one course/program created',
                    'passed' => $total > 0,
                    'severity' => 'critical',
                    'message' => $total > 0
                        ? "{$total} course(s) configured."
                        : 'No courses exist. Applicants need courses to apply for.',
                ],
                [
                    'key' => 'courses_active',
                    'label' => 'At least one course is active',
                    'passed' => $active > 0,
                    'severity' => 'critical',
                    'message' => $active > 0
                        ? "{$active} of {$total} course(s) active."
                        : ($total > 0
                            ? "All {$total} course(s) are deactivated. Applicants cannot select a program."
                            : 'No courses to activate.'),
                ],
            ],
        ];
    }

    /**
     * Rooms: existence, at least one active for exam scheduling.
     *
     * @return array{key: string, label: string, href: string, checks: list<array>}
     */
    private function checkRooms(): array
    {
        $total = Room::count();
        $active = Room::active()->count();
        $totalCapacity = Room::active()->sum('capacity');

        return [
            'key' => 'rooms',
            'label' => 'Rooms & Facilities',
            'href' => '/admin/rooms',
            'checks' => [
                [
                    'key' => 'rooms_exist',
                    'label' => 'At least one room created',
                    'passed' => $total > 0,
                    'severity' => 'critical',
                    'message' => $total > 0
                        ? "{$total} room(s) configured."
                        : 'No rooms exist. Rooms are required for exam scheduling.',
                ],
                [
                    'key' => 'rooms_active',
                    'label' => 'At least one room is active',
                    'passed' => $active > 0,
                    'severity' => 'important',
                    'message' => $active > 0
                        ? "{$active} active room(s) with total capacity of {$totalCapacity} seat(s)."
                        : ($total > 0
                            ? "All {$total} room(s) are deactivated. Cannot schedule exams."
                            : 'No rooms to activate.'),
                ],
            ],
        ];
    }

    /**
     * Aptitude Areas: existence, active areas, and formula completeness.
     *
     * @return array{key: string, label: string, href: string, checks: list<array>}
     */
    private function checkAptitudeAreas(): array
    {
        $total = AptitudeArea::count();
        $active = AptitudeArea::where('is_active', true)->count();
        $autoCompute = SystemSetting::enableNormalizedScores();

        // In auto-compute mode: conversion table areas MUST have data (for lookup),
        // and formula areas MUST have a formula.
        // In manual mode: conversion table areas are fine without data (user types
        // the percentile directly); only formula areas without a formula are flagged.
        $missingScoringList = AptitudeArea::where('is_active', true)
            ->where(function ($q) use ($autoCompute) {
                $q->where(function ($q2) {
                    // Formula areas missing a formula — always a problem
                    $q2->where('scoring_method', 'formula')
                        ->where(function ($q3) {
                            $q3->whereNull('formula')->orWhere('formula', '');
                        });
                })->orWhere(function ($q2) use ($autoCompute) {
                    // Conversion table areas with no entries — only a problem in auto-compute
                    if ($autoCompute) {
                        $q2->where('scoring_method', 'conversion_table')
                            ->whereDoesntHave('percentileConversions');
                    }
                })->orWhereNull('scoring_method');
            })
            ->get()
            ->map(function ($a) {
                $method = $a->scoring_method === 'conversion_table' ? 'conversion table' : 'formula';

                return "{$a->name} ({$method})";
            })
            ->toArray();

        $withoutScoring = count($missingScoringList);
        $withScoring = $active - $withoutScoring;

        // Count conversion table areas that have no data (informational in manual mode)
        $emptyConversionTables = ! $autoCompute
            ? AptitudeArea::where('is_active', true)
                ->where('scoring_method', 'conversion_table')
                ->whereDoesntHave('percentileConversions')
                ->count()
            : 0;

        $scoringMessage = match (true) {
            $active === 0 => 'No active areas to check scoring on.',
            $withoutScoring === 0 && $emptyConversionTables === 0 => "All {$active} active area(s) have scoring configured.",
            $withoutScoring === 0 && $emptyConversionTables > 0 => "All formula areas have scoring configured. {$emptyConversionTables} conversion table area(s) have no table data — this is fine in manual mode (percentiles are entered directly). Switch to auto-compute to require table data.",
            default => "{$withoutScoring} active area(s) missing scoring: ".implode(', ', $missingScoringList)." — normalized scores won't compute for them.",
        };

        return [
            'key' => 'aptitude_areas',
            'label' => 'Aptitude Areas',
            'href' => '/admin/aptitude-areas',
            'checks' => [
                [
                    'key' => 'aptitude_exist',
                    'label' => 'At least one aptitude area created',
                    'passed' => $total > 0,
                    'severity' => 'critical',
                    'message' => $total > 0
                        ? "{$total} aptitude area(s) configured."
                        : 'No aptitude areas defined. Required for scoring applicants.',
                ],
                [
                    'key' => 'aptitude_active',
                    'label' => 'At least one aptitude area is active',
                    'passed' => $active > 0,
                    'severity' => 'critical',
                    'message' => $active > 0
                        ? "{$active} of {$total} area(s) active."
                        : ($total > 0
                            ? "All {$total} area(s) are deactivated. Cannot score applicants."
                            : 'No areas to activate.'),
                ],
                [
                    'key' => 'aptitude_scoring',
                    'label' => $autoCompute
                        ? 'Active areas have scoring configured (formula or populated conversion table)'
                        : 'Active areas have scoring configured (formula or conversion table method)',
                    'passed' => $active > 0 && $withoutScoring === 0,
                    'severity' => 'optional',
                    'message' => $scoringMessage,
                ],
            ],
        ];
    }

    /**
     * Result Sheet Templates: existence and active template.
     *
     * @return array{key: string, label: string, href: string, checks: list<array>}
     */
    private function checkResultSheetTemplates(): array
    {
        $total = ResultSheetTemplate::count();
        $active = ResultSheetTemplate::where('is_active', true)->count();

        return [
            'key' => 'result_templates',
            'label' => 'Result Sheet Templates',
            'href' => '/admin/release/result-templates',
            'checks' => [
                [
                    'key' => 'result_tpl_exist',
                    'label' => 'At least one result template created',
                    'passed' => $total > 0,
                    'severity' => 'important',
                    'message' => $total > 0
                        ? "{$total} template(s) configured."
                        : 'No result sheet templates. Required for printing results.',
                ],
                [
                    'key' => 'result_tpl_active',
                    'label' => 'A result template is active',
                    'passed' => $active > 0,
                    'severity' => 'important',
                    'message' => $active > 0
                        ? "{$active} active template(s)."
                        : ($total > 0
                            ? 'Templates exist but none are active. Cannot print results.'
                            : 'No templates to activate.'),
                ],
            ],
        ];
    }

    /**
     * Admission Slip Templates: existence and active template.
     *
     * @return array{key: string, label: string, href: string, checks: list<array>}
     */
    private function checkAdmissionSlipTemplates(): array
    {
        $enabled = SystemSetting::admissionSlipEnabled();
        $hasTemplate = SystemSetting::admissionSlipTemplate() !== null;

        return [
            'key' => 'admission_templates',
            'label' => 'Admission Slip Distribution',
            'href' => '/admin/settings',
            'checks' => [
                [
                    'key' => 'admission_slip_enabled',
                    'label' => 'Admission slip distribution enabled',
                    'passed' => $enabled,
                    'severity' => 'optional',
                    'message' => $enabled
                        ? 'Admission slip distribution is enabled.'
                        : 'Admission slip distribution is disabled. Enable in Settings to allow slip printing.',
                ],
                [
                    'key' => 'admission_slip_template',
                    'label' => 'Custom admission slip template configured',
                    'passed' => $hasTemplate,
                    'severity' => 'optional',
                    'message' => $hasTemplate
                        ? 'Custom HTML template is configured. Default template will be used as fallback.'
                        : 'No custom template. The default built-in template will be used.',
                ],
            ],
        ];
    }

    /**
     * Privacy Policies: existence and active policy (legally required for application form).
     *
     * @return array{key: string, label: string, href: string, checks: list<array>}
     */
    private function checkPrivacyPolicies(): array
    {
        $total = PrivacyPolicy::count();
        $hasActive = PrivacyPolicy::active() !== null;

        return [
            'key' => 'privacy_policies',
            'label' => 'Privacy Policies',
            'href' => '/admin/privacy-policies',
            'checks' => [
                [
                    'key' => 'privacy_exist',
                    'label' => 'At least one privacy policy created',
                    'passed' => $total > 0,
                    'severity' => 'important',
                    'message' => $total > 0
                        ? "{$total} policy version(s) exist."
                        : 'No privacy policy. Required for applicant consent on the form.',
                ],
                [
                    'key' => 'privacy_active',
                    'label' => 'A privacy policy is active',
                    'passed' => $hasActive,
                    'severity' => 'important',
                    'message' => $hasActive
                        ? 'Active privacy policy is set.'
                        : ($total > 0
                            ? 'Privacy policies exist but none are active. Applicants will not see consent terms.'
                            : 'No policies to activate.'),
                ],
            ],
        ];
    }

    /**
     * Staff Accounts: verify critical roles have at least one user assigned.
     *
     * @return array{key: string, label: string, href: string, checks: list<array>}
     */
    private function checkStaffAccounts(): array
    {
        $superAdminCount = $this->countUsersWithRole('super_admin');
        $registrarCount = $this->countUsersWithRole('registrar_administrator');
        $testAdminCount = $this->countUsersWithRole('test_administrator');

        return [
            'key' => 'staff',
            'label' => 'Staff Accounts',
            'href' => '/admin/users',
            'checks' => [
                [
                    'key' => 'staff_super_admin',
                    'label' => 'At least one super admin exists',
                    'passed' => $superAdminCount > 0,
                    'severity' => 'critical',
                    'message' => $superAdminCount > 0
                        ? "{$superAdminCount} super admin(s)."
                        : 'No super admin. System cannot be fully managed.',
                ],
                [
                    'key' => 'staff_registrar',
                    'label' => 'At least one registrar administrator exists',
                    'passed' => $registrarCount > 0,
                    'severity' => 'important',
                    'message' => $registrarCount > 0
                        ? "{$registrarCount} registrar administrator(s)."
                        : 'No registrar. Admissions workflow cannot be managed.',
                ],
                [
                    'key' => 'staff_test_admin',
                    'label' => 'At least one test administrator exists',
                    'passed' => $testAdminCount > 0,
                    'severity' => 'important',
                    'message' => $testAdminCount > 0
                        ? "{$testAdminCount} test administrator(s)."
                        : 'No test administrator. Exams and grading cannot be managed.',
                ],
            ],
        ];
    }

    private function checkInstitution(): array
    {
        $name = SystemSetting::institution('name');
        $examName = SystemSetting::institution('exam_name');
        $counselorName = SystemSetting::institution('personnel.guidance_counselor.name');

        return [
            'key' => 'institution',
            'label' => 'Institution Profile',
            'href' => '/admin/setup/institution',
            'checks' => [
                [
                    'key' => 'institution_name',
                    'label' => 'Institution name is configured',
                    'passed' => ! empty($name) && $name !== 'My Institution',
                    'severity' => 'important',
                    'message' => ! empty($name) && $name !== 'My Institution'
                        ? "Institution: {$name}"
                        : 'Institution name is still the default. Update in Setup > Institution.',
                ],
                [
                    'key' => 'institution_exam_name',
                    'label' => 'Exam name is configured',
                    'passed' => ! empty($examName) && $examName !== 'College Admission Test',
                    'severity' => 'important',
                    'message' => ! empty($examName) && $examName !== 'College Admission Test'
                        ? "Exam: {$examName}"
                        : 'Exam name is still the default. Update for result sheets.',
                ],
                [
                    'key' => 'institution_counselor',
                    'label' => 'Guidance counselor name is set',
                    'passed' => ! empty($counselorName),
                    'severity' => 'optional',
                    'message' => ! empty($counselorName)
                        ? "Counselor: {$counselorName}"
                        : 'Guidance counselor name is blank. Needed for result sheet signatures.',
                ],
            ],
        ];
    }

    private function checkRatingScales(): array
    {
        $count = RatingScale::count();
        $hasDefault = RatingScale::where('is_default', true)->exists();

        return [
            'key' => 'rating_scales',
            'label' => 'Rating Scales',
            'href' => '/admin/setup/rating-scales',
            'checks' => [
                [
                    'key' => 'rating_scale_exist',
                    'label' => 'At least one rating scale created',
                    'passed' => $count > 0,
                    'severity' => 'important',
                    'message' => $count > 0
                        ? "{$count} rating scale(s) configured."
                        : 'No rating scales. Needed for descriptive ratings on result sheets.',
                ],
                [
                    'key' => 'rating_scale_default',
                    'label' => 'A default rating scale is set',
                    'passed' => $hasDefault,
                    'severity' => 'optional',
                    'message' => $hasDefault
                        ? 'Default rating scale is assigned.'
                        : ($count > 0
                            ? 'Rating scales exist but none is marked as default.'
                            : 'No scales to set as default.'),
                ],
            ],
        ];
    }

    /**
     * Count users with a specific role.
     */
    private function countUsersWithRole(string $roleName): int
    {
        return User::whereHas('roles', fn ($q) => $q->where('name', $roleName))->count();
    }
}
