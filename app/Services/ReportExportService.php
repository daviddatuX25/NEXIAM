<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\AptitudeArea;
use App\Models\ConsultationSummary;
use App\Models\Course;
use App\Models\ExamSession;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportService
{
    /**
     * Report type → domain mapping for authorization.
     */
    public const REGISTRAR_REPORTS = [
        'applications-master-list',
        'pipeline-summary',
        'course-preferences',
        'demographics',
        'dismissed-applications',
        'year-over-year',
    ];

    public const GUIDANCE_REPORTS = [
        'scores-report',
        'exam-session-roster',
        'consultation-summary',
        'release-tracking',
    ];

    /**
     * Get all available report definitions.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function definitions(): array
    {
        return [
            // Registrar domain
            [
                'type' => 'applications-master-list',
                'title' => 'Applications Master List',
                'description' => 'Complete list of all applications with biographical data, course preferences, and pipeline status.',
                'icon' => 'file-text',
                'domain' => 'registrar',
            ],
            [
                'type' => 'pipeline-summary',
                'title' => 'Pipeline Status Summary',
                'description' => 'Breakdown of applicant counts at each pipeline stage with percentages.',
                'icon' => 'bar-chart-3',
                'domain' => 'registrar',
            ],
            [
                'type' => 'course-preferences',
                'title' => 'Course Preference Analysis',
                'description' => 'Demand analysis showing how many applicants chose each program as 1st, 2nd, or 3rd choice.',
                'icon' => 'graduation-cap',
                'domain' => 'registrar',
            ],
            [
                'type' => 'demographics',
                'title' => 'Demographics Report',
                'description' => 'Applicant pool breakdown by sex, age bracket, province, and GWA range.',
                'icon' => 'users',
                'domain' => 'registrar',
            ],
            [
                'type' => 'dismissed-applications',
                'title' => 'Dismissed Applications',
                'description' => 'All rejected applications with rejection reasons, dates, and processing details.',
                'icon' => 'x-circle',
                'domain' => 'registrar',
            ],
            // Guidance domain
            [
                'type' => 'scores-report',
                'title' => 'Exam Scores Report',
                'description' => 'Per-applicant aptitude scores across all domains with raw and normalized values.',
                'icon' => 'target',
                'domain' => 'guidance',
            ],
            [
                'type' => 'exam-session-roster',
                'title' => 'Exam Session Roster',
                'description' => 'Per-session attendee list with scheduling details, attendance, and submission status.',
                'icon' => 'calendar',
                'domain' => 'guidance',
            ],
            [
                'type' => 'consultation-summary',
                'title' => 'Consultation Summary',
                'description' => 'Guidance counseling outcomes with recommended courses and counselor comments.',
                'icon' => 'message-square',
                'domain' => 'guidance',
            ],
            [
                'type' => 'release-tracking',
                'title' => 'Release Tracking',
                'description' => 'Applicants who have received their results with release dates and details.',
                'icon' => 'send',
                'domain' => 'guidance',
            ],
        ];
    }

    /**
     * Get summary counts for the reports hub KPI cards.
     *
     * @return array<string, int>
     */
    public function getCounts(int $academicYearId): array
    {
        $baseQuery = Application::where('academic_year_id', $academicYearId);

        return [
            'total_applications' => (clone $baseQuery)->count(),
            'dismissed_count' => (clone $baseQuery)->where('pipeline_status', 'dismissed')->count(),
            'released_count' => (clone $baseQuery)->where('pipeline_status', 'released')->count(),
            'pending_review_count' => (clone $baseQuery)->whereIn('pipeline_status', ['graded', 'examined'])->count(),
            'exam_sessions' => ExamSession::where('academic_year_id', $academicYearId)->count(),
        ];
    }

    /**
     * Get rich summary data for inline display on the reports page.
     *
     * @return array{pipeline: array, course_demand: array, demographics: array}
     */
    public function getSummaryData(int $academicYearId): array
    {
        return [
            'pipeline' => $this->getPipelineSummaryData($academicYearId),
            'course_demand' => $this->getCourseDemandData($academicYearId),
            'demographics' => $this->getDemographicsData($academicYearId),
        ];
    }

    /**
     * Pipeline status breakdown for inline funnel display.
     *
     * @return array<int, array{status: string, count: int, percentage: float}>
     */
    private function getPipelineSummaryData(int $academicYearId): array
    {
        $statuses = ['pending', 'accepted', 'draft_scheduled', 'scheduled', 'printed', 'attended', 'submitted', 'scored', 'graded', 'released', 'dismissed'];

        $counts = Application::where('academic_year_id', $academicYearId)
            ->select(DB::raw("COALESCE(pipeline_status, 'pending') as status"), DB::raw('COUNT(*) as count'))
            ->groupBy(DB::raw("COALESCE(pipeline_status, 'pending')"))
            ->pluck('count', 'status')
            ->toArray();

        $total = array_sum($counts);

        return array_map(fn (string $status) => [
            'status' => ucfirst($status),
            'count' => $counts[$status] ?? 0,
            'percentage' => $total > 0 ? round((($counts[$status] ?? 0) / $total) * 100, 1) : 0,
        ], $statuses);
    }

    /**
     * Course preference demand vs counselor recommendation analysis.
     *
     * @return array<int, array{code: string, name: string, pref1: int, pref2: int, pref3: int, total_demand: int, recommended: int, alignment: float}>
     */
    private function getCourseDemandData(int $academicYearId): array
    {
        $courses = Course::withTrashed()->orderBy('name')->get();

        $pref1 = Application::where('academic_year_id', $academicYearId)
            ->whereNotNull('course_preference_1')
            ->select('course_preference_1 as course_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('course_id')
            ->pluck('cnt', 'course_id');

        $pref2 = Application::where('academic_year_id', $academicYearId)
            ->whereNotNull('course_preference_2')
            ->select('course_preference_2 as course_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('course_id')
            ->pluck('cnt', 'course_id');

        $pref3 = Application::where('academic_year_id', $academicYearId)
            ->whereNotNull('course_preference_3')
            ->select('course_preference_3 as course_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('course_id')
            ->pluck('cnt', 'course_id');

        // Count recommendations per course from consultation summaries
        $recommendations = ConsultationSummary::whereHas('applicant.application', fn ($q) => $q->where('academic_year_id', $academicYearId))
            ->whereNotNull('recommended_course_id')
            ->select('recommended_course_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('recommended_course_id')
            ->pluck('cnt', 'recommended_course_id');

        $result = [];
        foreach ($courses as $course) {
            $c1 = $pref1[$course->id] ?? 0;
            $c2 = $pref2[$course->id] ?? 0;
            $c3 = $pref3[$course->id] ?? 0;
            $totalDemand = $c1 + $c2 + $c3;
            $recommended = $recommendations[$course->id] ?? 0;

            if ($totalDemand === 0 && $recommended === 0) {
                continue;
            }

            $result[] = [
                'code' => $course->code,
                'name' => $course->name,
                'pref1' => $c1,
                'pref2' => $c2,
                'pref3' => $c3,
                'total_demand' => $totalDemand,
                'recommended' => $recommended,
                'alignment' => $totalDemand > 0 ? round(($recommended / $totalDemand) * 100, 1) : 0,
            ];
        }

        // Sort by total demand descending
        usort($result, fn ($a, $b) => $b['total_demand'] <=> $a['total_demand']);

        return $result;
    }

    /**
     * Demographics snapshot for inline display.
     *
     * @return array{by_sex: array, by_age: array}
     */
    private function getDemographicsData(int $academicYearId): array
    {
        // By Sex
        $sexCounts = Application::where('academic_year_id', $academicYearId)
            ->select('sex', DB::raw('COUNT(*) as count'))
            ->groupBy('sex')
            ->pluck('count', 'sex')
            ->toArray();
        $totalSex = array_sum($sexCounts);
        $bySex = [];
        foreach ($sexCounts as $sex => $count) {
            $bySex[] = [
                'label' => ucfirst($sex ?: 'Unspecified'),
                'count' => $count,
                'percentage' => $totalSex > 0 ? round(($count / $totalSex) * 100, 1) : 0,
            ];
        }

        // By Age Bracket
        $ages = Application::where('academic_year_id', $academicYearId)
            ->whereNotNull('age')
            ->pluck('age');
        $ageBrackets = ['15 and below' => 0, '16-17' => 0, '18-19' => 0, '20-21' => 0, '22-25' => 0, '26+' => 0];
        foreach ($ages as $age) {
            if ($age <= 15) {
                $ageBrackets['15 and below']++;
            } elseif ($age <= 17) {
                $ageBrackets['16-17']++;
            } elseif ($age <= 19) {
                $ageBrackets['18-19']++;
            } elseif ($age <= 21) {
                $ageBrackets['20-21']++;
            } elseif ($age <= 25) {
                $ageBrackets['22-25']++;
            } else {
                $ageBrackets['26+']++;
            }
        }
        $totalAge = array_sum($ageBrackets);
        $byAge = [];
        foreach ($ageBrackets as $bracket => $count) {
            $byAge[] = [
                'label' => $bracket,
                'count' => $count,
                'percentage' => $totalAge > 0 ? round(($count / $totalAge) * 100, 1) : 0,
            ];
        }

        return [
            'by_sex' => $bySex,
            'by_age' => $byAge,
        ];
    }

    /**
     * Get report data as arrays (for PDF rendering).
     *
     * @return array{title: string, headers: array, rows: array}
     */
    public function getReportData(string $type, int $academicYearId, array $filters = []): array
    {
        $spreadsheet = match ($type) {
            'applications-master-list' => $this->buildApplicationsMasterList($academicYearId, $filters),
            'pipeline-summary' => $this->buildPipelineSummary($academicYearId),
            'course-preferences' => $this->buildCoursePreferences($academicYearId),
            'demographics' => $this->buildDemographics($academicYearId),
            'dismissed-applications' => $this->buildDismissedApplications($academicYearId),
            'scores-report' => $this->buildScoresReport($academicYearId),
            'exam-session-roster' => $this->buildExamSessionRoster($academicYearId),
            'consultation-summary' => $this->buildConsultationSummary($academicYearId),
            'release-tracking' => $this->buildReleaseTracking($academicYearId),
            default => throw new \InvalidArgumentException("Unknown report type: {$type}"),
        };

        $sheet = $spreadsheet->getActiveSheet();
        $title = $sheet->getTitle();
        $highestRow = $sheet->getHighestRow();
        $highestCol = $sheet->getHighestColumn();

        $headers = [];
        $colCount = Coordinate::columnIndexFromString($highestCol);
        for ($col = 1; $col <= $colCount; $col++) {
            $headers[] = (string) $sheet->getCell([$col, 1])->getValue();
        }

        $rows = [];
        for ($row = 2; $row <= $highestRow; $row++) {
            $rowData = [];
            for ($col = 1; $col <= $colCount; $col++) {
                $rowData[] = (string) ($sheet->getCell([$col, $row])->getValue() ?? '');
            }
            $rows[] = $rowData;
        }

        $spreadsheet->disconnectWorksheets();

        // Map type to a friendly title
        $definitions = collect(self::definitions());
        $def = $definitions->firstWhere('type', $type);

        return [
            'title' => $def['title'] ?? $title,
            'headers' => $headers,
            'rows' => $rows,
        ];
    }

    /**
     * Generate and stream the requested report.
     */
    public function export(string $type, int $academicYearId, array $filters = []): StreamedResponse
    {
        $spreadsheet = match ($type) {
            'applications-master-list' => $this->buildApplicationsMasterList($academicYearId, $filters),
            'pipeline-summary' => $this->buildPipelineSummary($academicYearId),
            'course-preferences' => $this->buildCoursePreferences($academicYearId),
            'demographics' => $this->buildDemographics($academicYearId),
            'dismissed-applications' => $this->buildDismissedApplications($academicYearId),
            'scores-report' => $this->buildScoresReport($academicYearId),
            'exam-session-roster' => $this->buildExamSessionRoster($academicYearId),
            'consultation-summary' => $this->buildConsultationSummary($academicYearId),
            'release-tracking' => $this->buildReleaseTracking($academicYearId),
            default => throw new \InvalidArgumentException("Unknown report type: {$type}"),
        };

        $ay = AcademicYear::find($academicYearId);
        $ayLabel = $ay ? str_replace(['/', '\\'], '-', $ay->academic_year) : $academicYearId;
        $filename = "{$type}_{$ayLabel}_".now()->format('Y-m-d').'.xlsx';

        return $this->streamXlsx($spreadsheet, $filename);
    }

    // ─── Report Builders ────────────────────────────────────────────

    private function buildApplicationsMasterList(int $academicYearId, array $filters): Spreadsheet
    {
        $query = Application::where('academic_year_id', $academicYearId)
            ->with([
                'coursePreference1:id,name,code',
                'coursePreference2:id,name,code',
                'coursePreference3:id,name,code',
            ])
            ->orderBy('last_name')
            ->orderBy('first_name');

        if (! empty($filters['pipeline_status'])) {
            $query->where('pipeline_status', $filters['pipeline_status']);
        }
        if (! empty($filters['date_from'])) {
            $query->whereDate('submitted_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('submitted_at', '<=', $filters['date_to']);
        }

        $headers = [
            'Reference #', 'Last Name', 'First Name', 'Middle Name', 'Suffix',
            'Sex', 'Classification', 'Last School Enrolled', 'Age', 'Birthdate', 'Email', 'Phone',
            'Address', 'City', 'Province', 'Zip Code', 'GWA',
            'Course Pref 1', 'Course Pref 2', 'Course Pref 3',
            'Pipeline Status', 'Date Submitted', 'Date Processed',
        ];

        $rows = $query->get()->map(fn (Application $app) => [
            $app->reference_number,
            $app->last_name,
            $app->first_name,
            $app->middle_name,
            $app->suffix,
            $app->sex,
            ucfirst($app->applicant_type ?? 'new'),
            $app->last_school_enrolled ?? '',
            $app->age,
            $app->birthdate?->format('Y-m-d'),
            $app->email,
            $app->phone,
            $app->address_line,
            $app->city,
            $app->province,
            $app->zip_code,
            $app->gwa,
            $app->coursePreference1?->name,
            $app->coursePreference2?->name,
            $app->coursePreference3?->name,
            ucfirst($app->pipeline_status ?? 'pending'),
            $app->submitted_at?->format('Y-m-d H:i'),
            $app->processed_at?->format('Y-m-d H:i'),
        ])->toArray();

        return $this->createSpreadsheet('Applications Master List', $headers, $rows);
    }

    private function buildPipelineSummary(int $academicYearId): Spreadsheet
    {
        $statuses = ['pending', 'accepted', 'draft_scheduled', 'scheduled', 'printed', 'attended', 'submitted', 'scored', 'graded', 'released', 'dismissed'];

        $counts = Application::where('academic_year_id', $academicYearId)
            ->select(DB::raw("COALESCE(pipeline_status, 'pending') as status"), DB::raw('COUNT(*) as count'))
            ->groupBy(DB::raw("COALESCE(pipeline_status, 'pending')"))
            ->pluck('count', 'status')
            ->toArray();

        $total = array_sum($counts);

        $headers = ['Pipeline Status', 'Count', '% of Total'];
        $rows = [];
        foreach ($statuses as $status) {
            $count = $counts[$status] ?? 0;
            $rows[] = [
                ucfirst($status),
                $count,
                $total > 0 ? round(($count / $total) * 100, 1).'%' : '0%',
            ];
        }
        $rows[] = ['TOTAL', $total, '100%'];

        return $this->createSpreadsheet('Pipeline Summary', $headers, $rows);
    }

    private function buildCoursePreferences(int $academicYearId): Spreadsheet
    {
        $courses = Course::withTrashed()->orderBy('name')->get();

        $pref1 = Application::where('academic_year_id', $academicYearId)
            ->whereNotNull('course_preference_1')
            ->select('course_preference_1 as course_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('course_id')
            ->pluck('cnt', 'course_id');

        $pref2 = Application::where('academic_year_id', $academicYearId)
            ->whereNotNull('course_preference_2')
            ->select('course_preference_2 as course_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('course_id')
            ->pluck('cnt', 'course_id');

        $pref3 = Application::where('academic_year_id', $academicYearId)
            ->whereNotNull('course_preference_3')
            ->select('course_preference_3 as course_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('course_id')
            ->pluck('cnt', 'course_id');

        $headers = ['Course Code', 'Course Name', '1st Choice', '2nd Choice', '3rd Choice', 'Total Mentions'];
        $rows = [];
        foreach ($courses as $course) {
            $c1 = $pref1[$course->id] ?? 0;
            $c2 = $pref2[$course->id] ?? 0;
            $c3 = $pref3[$course->id] ?? 0;
            $total = $c1 + $c2 + $c3;
            if ($total === 0) {
                continue; // Skip courses with no demand
            }
            $rows[] = [$course->code, $course->name, $c1, $c2, $c3, $total];
        }

        // Sort by total mentions descending
        usort($rows, fn ($a, $b) => $b[5] <=> $a[5]);

        return $this->createSpreadsheet('Course Preference Analysis', $headers, $rows);
    }

    private function buildDemographics(int $academicYearId): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;

        // Sheet 1 — By Sex
        $sexCounts = Application::where('academic_year_id', $academicYearId)
            ->select('sex', DB::raw('COUNT(*) as count'))
            ->groupBy('sex')
            ->pluck('count', 'sex')
            ->toArray();
        $totalSex = array_sum($sexCounts);
        $sexRows = [];
        foreach ($sexCounts as $sex => $count) {
            $sexRows[] = [ucfirst($sex ?: 'Unspecified'), $count, $totalSex > 0 ? round(($count / $totalSex) * 100, 1).'%' : '0%'];
        }
        $this->fillSheet($spreadsheet->getActiveSheet(), 'By Sex', ['Sex', 'Count', '% of Total'], $sexRows);

        // Sheet 2 — By Age Bracket
        $ages = Application::where('academic_year_id', $academicYearId)
            ->whereNotNull('age')
            ->pluck('age');
        $ageBrackets = ['15 and below' => 0, '16-17' => 0, '18-19' => 0, '20-21' => 0, '22-25' => 0, '26+' => 0];
        foreach ($ages as $age) {
            if ($age <= 15) {
                $ageBrackets['15 and below']++;
            } elseif ($age <= 17) {
                $ageBrackets['16-17']++;
            } elseif ($age <= 19) {
                $ageBrackets['18-19']++;
            } elseif ($age <= 21) {
                $ageBrackets['20-21']++;
            } elseif ($age <= 25) {
                $ageBrackets['22-25']++;
            } else {
                $ageBrackets['26+']++;
            }
        }
        $totalAge = array_sum($ageBrackets);
        $ageRows = [];
        foreach ($ageBrackets as $bracket => $count) {
            $ageRows[] = [$bracket, $count, $totalAge > 0 ? round(($count / $totalAge) * 100, 1).'%' : '0%'];
        }
        $sheet2 = $spreadsheet->createSheet();
        $this->fillSheet($sheet2, 'By Age', ['Age Bracket', 'Count', '% of Total'], $ageRows);

        // Sheet 3 — By Province
        $provinceCounts = Application::where('academic_year_id', $academicYearId)
            ->select(DB::raw("COALESCE(province, 'Unspecified') as province"), DB::raw('COUNT(*) as count'))
            ->groupBy(DB::raw("COALESCE(province, 'Unspecified')"))

            ->orderByDesc('count')
            ->get();
        $totalProv = $provinceCounts->sum('count');
        $provRows = $provinceCounts->map(fn ($r) => [$r->province, $r->count, $totalProv > 0 ? round(($r->count / $totalProv) * 100, 1).'%' : '0%'])->toArray();
        $sheet3 = $spreadsheet->createSheet();
        $this->fillSheet($sheet3, 'By Province', ['Province', 'Count', '% of Total'], $provRows);

        // Sheet 4 — By GWA Range
        $gwas = Application::where('academic_year_id', $academicYearId)
            ->whereNotNull('gwa')
            ->pluck('gwa');
        $gwaRanges = ['1.00-1.50' => 0, '1.51-2.00' => 0, '2.01-2.50' => 0, '2.51-3.00' => 0, '3.01-3.50' => 0, '3.51-4.00' => 0, '4.01-5.00' => 0];
        foreach ($gwas as $gwa) {
            if ($gwa <= 1.50) {
                $gwaRanges['1.00-1.50']++;
            } elseif ($gwa <= 2.00) {
                $gwaRanges['1.51-2.00']++;
            } elseif ($gwa <= 2.50) {
                $gwaRanges['2.01-2.50']++;
            } elseif ($gwa <= 3.00) {
                $gwaRanges['2.51-3.00']++;
            } elseif ($gwa <= 3.50) {
                $gwaRanges['3.01-3.50']++;
            } elseif ($gwa <= 4.00) {
                $gwaRanges['3.51-4.00']++;
            } else {
                $gwaRanges['4.01-5.00']++;
            }
        }
        $totalGwa = array_sum($gwaRanges);
        $gwaRows = [];
        foreach ($gwaRanges as $range => $count) {
            $gwaRows[] = [$range, $count, $totalGwa > 0 ? round(($count / $totalGwa) * 100, 1).'%' : '0%'];
        }
        $sheet4 = $spreadsheet->createSheet();
        $this->fillSheet($sheet4, 'By GWA Range', ['GWA Range', 'Count', '% of Total'], $gwaRows);

        return $spreadsheet;
    }

    private function buildDismissedApplications(int $academicYearId): Spreadsheet
    {
        $apps = Application::where('academic_year_id', $academicYearId)
            ->where('pipeline_status', 'dismissed')
            ->orderBy('processed_at', 'desc')
            ->get();

        $headers = ['Reference #', 'Last Name', 'First Name', 'Email', 'Phone', 'Rejection Reason', 'Date Submitted', 'Date Dismissed'];
        $rows = $apps->map(fn (Application $app) => [
            $app->reference_number,
            $app->last_name,
            $app->first_name,
            $app->email,
            $app->phone,
            $app->rejection_reason ?? '—',
            $app->submitted_at?->format('Y-m-d H:i'),
            $app->processed_at?->format('Y-m-d H:i'),
        ])->toArray();

        return $this->createSpreadsheet('Dismissed Applications', $headers, $rows);
    }

    private function buildScoresReport(int $academicYearId): Spreadsheet
    {
        $aptitudeAreas = AptitudeArea::orderBy('display_order')->get();
        $hasAnyConversionTable = $aptitudeAreas->some(fn ($a) => $a->scoring_method === 'conversion_table');

        $applicants = Applicant::whereHas('application', fn ($q) => $q->where('academic_year_id', $academicYearId))
            ->with([
                'application:id,reference_number,last_name,first_name,middle_name',
                'applicantScores' => fn ($q) => $q->select('id', 'applicant_id', 'aptitude_area_id', 'raw_score', 'max_score', 'normalized_score', 'percentile_string'),
            ])
            ->get();

        // Build dynamic headers
        $headers = ['Reference #', 'Last Name', 'First Name'];
        foreach ($aptitudeAreas as $area) {
            $headers[] = "{$area->name} (Raw)";
            $headers[] = "{$area->name} (Max)";
            $headers[] = "{$area->name} (Normalized)";
            if ($hasAnyConversionTable) {
                $headers[] = "{$area->name} (Percentile)";
            }
        }

        $rows = [];
        foreach ($applicants as $applicant) {
            $row = [
                $applicant->application?->reference_number,
                $applicant->application?->last_name,
                $applicant->application?->first_name,
            ];
            foreach ($aptitudeAreas as $area) {
                $score = $applicant->applicantScores->firstWhere('aptitude_area_id', $area->id);
                $row[] = $score?->raw_score ?? '';
                $row[] = $score?->max_score ?? '';
                $row[] = $score?->normalized_score !== null ? round($score->normalized_score, 2) : '';
                if ($hasAnyConversionTable) {
                    $row[] = $score?->percentile_string ?? '';
                }
            }
            $rows[] = $row;
        }

        return $this->createSpreadsheet('Exam Scores Report', $headers, $rows);
    }

    private function buildExamSessionRoster(int $academicYearId): Spreadsheet
    {
        $sessions = ExamSession::where('academic_year_id', $academicYearId)
            ->with([
                'room:id,name',
                'applicants' => fn ($q) => $q->with('application:id,reference_number,last_name,first_name'),
            ])
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        $headers = [
            'Session Date', 'Start Time', 'End Time', 'Room', 'Session Status',
            'Applicant Ref #', 'Applicant Name', 'Attendance', 'Submission',
        ];

        $rows = [];
        foreach ($sessions as $session) {
            foreach ($session->applicants as $applicant) {
                $pivot = $applicant->pivot;
                $rows[] = [
                    $session->date,
                    $session->start_time,
                    $session->end_time,
                    $session->room?->name ?? '—',
                    ucfirst($session->status ?? '—'),
                    $applicant->application?->reference_number,
                    trim(($applicant->application?->last_name ?? '').', '.($applicant->application?->first_name ?? '')),
                    ucfirst($pivot->attendance_status ?? 'pending'),
                    ucfirst($pivot->submission_status ?? 'pending'),
                ];
            }
        }

        return $this->createSpreadsheet('Exam Session Roster', $headers, $rows);
    }

    private function buildConsultationSummary(int $academicYearId): Spreadsheet
    {
        $summaries = ConsultationSummary::whereHas('applicant.application', fn ($q) => $q->where('academic_year_id', $academicYearId))
            ->with([
                'applicant.application:id,reference_number,last_name,first_name',
                'recommendedCourse:id,name,code',
                'counselor:id,name',
            ])
            ->get();

        $headers = ['Reference #', 'Last Name', 'First Name', 'Counselor', 'Recommended Course', 'Comments', 'Status', 'Released At'];
        $rows = $summaries->map(fn (ConsultationSummary $s) => [
            $s->applicant?->application?->reference_number,
            $s->applicant?->application?->last_name,
            $s->applicant?->application?->first_name,
            $s->counselor?->name ?? '—',
            $s->recommendedCourse?->name ?? '—',
            $s->counselor_comments ?? '—',
            ucfirst($s->status ?? '—'),
            $s->released_at?->format('Y-m-d H:i') ?? '—',
        ])->toArray();

        return $this->createSpreadsheet('Consultation Summary', $headers, $rows);
    }

    private function buildReleaseTracking(int $academicYearId): Spreadsheet
    {
        $apps = Application::where('academic_year_id', $academicYearId)
            ->where('pipeline_status', 'released')
            ->with([
                'applicant.consultationSummary' => fn ($q) => $q->with('recommendedCourse:id,name', 'releasedByUser:id,name'),
            ])
            ->orderBy('last_name')
            ->get();

        $headers = ['Reference #', 'Last Name', 'First Name', 'Recommended Course', 'Consultation Status', 'Released At', 'Released By'];
        $rows = $apps->map(function (Application $app) {
            $summary = $app->applicant?->consultationSummary;

            return [
                $app->reference_number,
                $app->last_name,
                $app->first_name,
                $summary?->recommendedCourse?->name ?? '—',
                ucfirst($summary?->status ?? '—'),
                $summary?->released_at?->format('Y-m-d H:i') ?? '—',
                $summary?->releasedByUser?->name ?? '—',
            ];
        })->toArray();

        return $this->createSpreadsheet('Release Tracking', $headers, $rows);
    }

    // ─── Helpers ────────────────────────────────────────────────────

    /**
     * Create a formatted spreadsheet with a single sheet.
     */
    private function createSpreadsheet(string $title, array $headers, array $rows): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $this->fillSheet($spreadsheet->getActiveSheet(), $title, $headers, $rows);

        return $spreadsheet;
    }

    /**
     * Fill a worksheet with headers and data rows, applying consistent formatting.
     */
    private function fillSheet($sheet, string $title, array $headers, array $rows): void
    {
        $sheet->setTitle(substr($title, 0, 31)); // Excel 31-char limit

        // Write headers
        $col = 1;
        foreach ($headers as $header) {
            $sheet->setCellValue([$col, 1], $header);
            $col++;
        }

        // Style headers
        $lastCol = Coordinate::stringFromColumnIndex(count($headers));
        $headerRange = "A1:{$lastCol}1";
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2563EB']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '1D4ED8']]],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(28);

        // Write data
        $rowNum = 2;
        foreach ($rows as $row) {
            $col = 1;
            foreach ($row as $value) {
                $sheet->setCellValue([$col, $rowNum], $value);
                $col++;
            }
            $rowNum++;
        }

        // Alternate row shading
        if (count($rows) > 0) {
            for ($r = 2; $r <= $rowNum - 1; $r++) {
                if ($r % 2 === 0) {
                    $sheet->getStyle("A{$r}:{$lastCol}{$r}")->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F0F4FF']],
                    ]);
                }
            }
        }

        // Auto-size columns
        foreach (range(1, count($headers)) as $colIdx) {
            $colLetter = Coordinate::stringFromColumnIndex($colIdx);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        // Freeze header row
        $sheet->freezePane('A2');
    }

    /**
     * Stream an XLSX file to the browser.
     */
    private function streamXlsx(Spreadsheet $spreadsheet, string $filename): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'max-age=0',
        ];

        return response()->stream(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, 200, $headers);
    }
}
