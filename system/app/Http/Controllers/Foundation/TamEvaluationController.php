<?php

namespace App\Http\Controllers\Foundation;

use App\Http\Controllers\Controller;
use App\Models\TamEvaluation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TamEvaluationController extends Controller
{
    /**
     * Target sample size based on Slovin formula (N=890, e=0.05 => n=276).
     * Surcharge oversampling (290-305+) is encouraged to ensure >= 276 valid responses after screening.
     */
    public const int TARGET_SAMPLE_SIZE = 276;

    public const int SURCHARGE_SAMPLE_TARGET = 290;

    public const int POPULATION_SIZE = 890;

    /**
     * Show the public TAM evaluation instrument for First-Year College Students.
     */
    public function create(Request $request): View
    {
        TamEvaluation::ensureTableExists();
        $submitted = $request->boolean('submitted');

        $totalCount = TamEvaluation::count();
        $targetSample = self::TARGET_SAMPLE_SIZE;

        $casCount = TamEvaluation::where('department', 'CAS')->count();
        $cbmeCount = TamEvaluation::where('department', 'CBME')->count();
        $cteCount = TamEvaluation::where('department', 'CTE')->count();

        // Target milestone allocations: 50% CAS, 25% CBME, 25% CTE
        $casTarget = (int) round($targetSample * 0.50); // 138
        $cbmeTarget = (int) round($targetSample * 0.25); // 69
        $cteTarget = (int) round($targetSample * 0.25); // 69

        $departmentStats = [
            'CAS' => [
                'name' => 'College of Arts and Sciences (CAS)',
                'short' => 'CAS',
                'quota_percent' => '50%',
                'target' => $casTarget,
                'count' => $casCount,
                'percent' => $casTarget > 0 ? min(100, round(($casCount / $casTarget) * 100)) : 0,
                'color' => '#2563eb',
            ],
            'CBME' => [
                'name' => 'College of Business Management & Entrepreneurship (CBME)',
                'short' => 'CBME',
                'quota_percent' => '25%',
                'target' => $cbmeTarget,
                'count' => $cbmeCount,
                'percent' => $cbmeTarget > 0 ? min(100, round(($cbmeCount / $cbmeTarget) * 100)) : 0,
                'color' => '#059669',
            ],
            'CTE' => [
                'name' => 'College of Teacher Education (CTE)',
                'short' => 'CTE',
                'quota_percent' => '25%',
                'target' => $cteTarget,
                'count' => $cteCount,
                'percent' => $cteTarget > 0 ? min(100, round(($cteCount / $cteTarget) * 100)) : 0,
                'color' => '#d97706',
            ],
        ];

        return view('tam.first-years', [
            'submitted' => $submitted,
            'totalCount' => $totalCount,
            'targetSample' => $targetSample,
            'surchargeTarget' => self::SURCHARGE_SAMPLE_TARGET,
            'populationSize' => self::POPULATION_SIZE,
            'departments' => TamEvaluation::DEPARTMENTS,
            'departmentStats' => $departmentStats,
            'defaultDate' => date('Y-m-d'),
        ]);
    }

    /**
     * Store an evaluation response in the isolated TAM SQLite database.
     */
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        TamEvaluation::ensureTableExists();

        $validated = $request->validate([
            'evaluator_role' => ['nullable', 'string', 'max:100'],
            'evaluation_date' => ['nullable', 'date'],
            'evaluator_name' => ['nullable', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:100'],
            'academic_program' => ['nullable', 'string', 'max:255'],
            'video_watched_confirmed' => ['nullable'],
            'STU-PU-01' => ['nullable', 'integer', 'between:1,5'],
            'STU-PU-02' => ['nullable', 'integer', 'between:1,5'],
            'STU-PU-03' => ['nullable', 'integer', 'between:1,5'],
            'STU-PU-04' => ['nullable', 'integer', 'between:1,5'],
            'STU-PU-05' => ['nullable', 'integer', 'between:1,5'],
            'STU-PEOU-01' => ['nullable', 'integer', 'between:1,5'],
            'STU-PEOU-02' => ['nullable', 'integer', 'between:1,5'],
            'STU-PEOU-03' => ['nullable', 'integer', 'between:1,5'],
            'STU-PEOU-04' => ['nullable', 'integer', 'between:1,5'],
            'useful_features' => ['nullable', 'string', 'max:5000'],
            'difficulties_encountered' => ['nullable', 'string', 'max:5000'],
            'recommendations' => ['nullable', 'string', 'max:5000'],
            'evaluator_signature' => ['nullable', 'string', 'max:255'],
        ]);

        $watchedConfirmed = filter_var(
            $request->input('video_watched_confirmed', true),
            FILTER_VALIDATE_BOOLEAN,
            FILTER_NULL_ON_FAILURE
        ) ?? true;

        $record = TamEvaluation::create([
            'evaluator_role' => $request->input('evaluator_role', 'First-Year College Student'),
            'evaluation_date' => $validated['evaluation_date'] ?? date('Y-m-d'),
            'evaluator_name' => $validated['evaluator_name'] ?? null,
            'department' => $validated['department'] ?? null,
            'academic_program' => $validated['academic_program'] ?? null,
            'video_watched_confirmed' => $watchedConfirmed,
            'stu_pu_01' => $validated['STU-PU-01'] ?? null,
            'stu_pu_02' => $validated['STU-PU-02'] ?? null,
            'stu_pu_03' => $validated['STU-PU-03'] ?? null,
            'stu_pu_04' => $validated['STU-PU-04'] ?? null,
            'stu_pu_05' => $validated['STU-PU-05'] ?? null,
            'stu_peou_01' => $validated['STU-PEOU-01'] ?? null,
            'stu_peou_02' => $validated['STU-PEOU-02'] ?? null,
            'stu_peou_03' => $validated['STU-PEOU-03'] ?? null,
            'stu_peou_04' => $validated['STU-PEOU-04'] ?? null,
            'useful_features' => $validated['useful_features'] ?? null,
            'difficulties_encountered' => $validated['difficulties_encountered'] ?? null,
            'recommendations' => $validated['recommendations'] ?? null,
            'evaluator_signature' => $validated['evaluator_signature'] ?? null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        if ($request->wantsJson() || $request->isJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Your TAM evaluation has been recorded successfully. Thank you for your feedback!',
                'id' => $record->id,
            ], 201);
        }

        return redirect()->route('tam.first-years', ['submitted' => 1])
            ->with('status', 'Your TAM evaluation response has been recorded successfully.');
    }

    /**
     * Show the management interface to view responses, statistics, and observations.
     */
    public function admin(Request $request): View|Response
    {
        $this->authorizeAdminAccess($request);
        TamEvaluation::ensureTableExists();

        $evaluations = TamEvaluation::query()->latest('id')->get();
        $totalResponses = $evaluations->count();
        $targetSample = self::TARGET_SAMPLE_SIZE;
        $progressPercent = $targetSample > 0 ? min(100, round(($totalResponses / $targetSample) * 100, 1)) : 0;
        $surchargeTarget = self::SURCHARGE_SAMPLE_TARGET;
        $surchargeProgressPercent = $surchargeTarget > 0 ? min(100, round(($totalResponses / $surchargeTarget) * 100, 1)) : 0;
        $definitions = TamEvaluation::itemDefinitions();
        $itemStats = [];
        $puTotals = [];
        $peouTotals = [];

        foreach ($definitions as $code => $meta) {
            $field = $meta['field'];
            $ratings = $evaluations->pluck($field)->filter(fn ($v) => $v !== null && $v >= 1 && $v <= 5)->values();
            $count = $ratings->count();
            $mean = $count > 0 ? round($ratings->avg(), 2) : null;
            $stdDev = null;
            if ($count > 1 && $mean !== null) {
                $variance = $ratings->reduce(fn ($carry, $v) => $carry + pow($v - $mean, 2), 0) / ($count - 1);
                $stdDev = round(sqrt($variance), 2);
            } elseif ($count === 1) {
                $stdDev = 0.0;
            }

            $distribution = [
                5 => $ratings->filter(fn ($v) => (int) $v === 5)->count(),
                4 => $ratings->filter(fn ($v) => (int) $v === 4)->count(),
                3 => $ratings->filter(fn ($v) => (int) $v === 3)->count(),
                2 => $ratings->filter(fn ($v) => (int) $v === 2)->count(),
                1 => $ratings->filter(fn ($v) => (int) $v === 1)->count(),
            ];

            $itemStats[$code] = [
                'code' => $code,
                'construct' => $meta['construct'],
                'statement' => $meta['statement'],
                'count' => $count,
                'mean' => $mean,
                'sd' => $stdDev,
                'interpretation' => $this->verbalInterpretation($mean),
                'distribution' => $distribution,
            ];

            if ($mean !== null) {
                if ($meta['construct'] === 'Perceived Usefulness') {
                    $puTotals[] = $mean;
                } else {
                    $peouTotals[] = $mean;
                }
            }
        }

        $overallPuMean = ! empty($puTotals) ? round(array_sum($puTotals) / count($puTotals), 2) : null;
        $overallPeouMean = ! empty($peouTotals) ? round(array_sum($peouTotals) / count($peouTotals), 2) : null;
        $overallGrandMean = ($overallPuMean !== null && $overallPeouMean !== null)
            ? round(($overallPuMean + $overallPeouMean) / 2, 2)
            : ($overallPuMean ?? $overallPeouMean);

        $summary = [
            'total_responses' => $totalResponses,
            'target_sample' => $targetSample,
            'surcharge_target' => $surchargeTarget,
            'population_total' => self::POPULATION_SIZE,
            'progress_percent' => $progressPercent,
            'surcharge_progress_percent' => $surchargeProgressPercent,
            'pu_mean' => $overallPuMean,
            'pu_sd' => $this->calculateStandardDeviation($puTotals),
            'pu_interpretation' => $this->verbalInterpretation($overallPuMean),
            'peou_mean' => $overallPeouMean,
            'peou_sd' => $this->calculateStandardDeviation($peouTotals),
            'peou_interpretation' => $this->verbalInterpretation($overallPeouMean),
            'overall_mean' => $overallGrandMean,
            'overall_sd' => $this->calculateStandardDeviation(array_merge($puTotals, $peouTotals)),
            'overall_interpretation' => $this->verbalInterpretation($overallGrandMean),
        ];

        $departmentCounts = [
            'CAS' => $evaluations->where('department', 'CAS')->count(),
            'CBME' => $evaluations->where('department', 'CBME')->count(),
            'CTE' => $evaluations->where('department', 'CTE')->count(),
            'Other' => $evaluations->filter(fn ($e) => ! in_array($e->department, ['CAS', 'CBME', 'CTE'], true) && ! empty($e->department))->count(),
            'Unspecified' => $evaluations->filter(fn ($e) => empty($e->department))->count(),
        ];

        return view('tam.admin', [
            'summary' => $summary,
            'departmentCounts' => $departmentCounts,
            'departments' => TamEvaluation::DEPARTMENTS,
            'itemStats' => $itemStats,
            'evaluations' => $evaluations,
            'definitions' => $definitions,
            'adminKey' => $request->query('key'),
        ]);
    }

    /**
     * Export all TAM responses to a clean CSV for SPSS / Excel / Python analysis.
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $this->authorizeAdminAccess($request);
        TamEvaluation::ensureTableExists();

        $filename = 'nexiam-tam-first-year-responses-'.date('Y-m-d-His').'.csv';

        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');

            // BOM for UTF-8 Excel compatibility
            fwrite($handle, "\xEF\xBB\xBF");

            // Header row
            fputcsv($handle, [
                'ID',
                'Evaluation Date',
                'Evaluator Name',
                'Department',
                'Academic Program',
                'Evaluator Role',
                'Video Watched Confirmed',
                'STU-PU-01',
                'STU-PU-02',
                'STU-PU-03',
                'STU-PU-04',
                'STU-PU-05',
                'Mean_PU',
                'STU-PEOU-01',
                'STU-PEOU-02',
                'STU-PEOU-03',
                'STU-PEOU-04',
                'Mean_PEOU',
                'Overall_TAM_Mean',
                'Useful Features',
                'Difficulties Encountered',
                'Recommendations',
                'Evaluator Signature',
                'IP Address',
                'Submitted At (UTC)',
            ]);

            TamEvaluation::query()->orderBy('id')->chunk(100, function ($records) use ($handle) {
                /** @var TamEvaluation $record */
                foreach ($records as $record) {
                    fputcsv($handle, [
                        $record->id,
                        $record->evaluation_date?->format('Y-m-d') ?? ($record->created_at?->format('Y-m-d') ?? ''),
                        $record->evaluator_name ?? '',
                        $record->department ?? '',
                        $record->academic_program ?? '',
                        $record->evaluator_role,
                        $record->video_watched_confirmed ? 'Yes' : 'No',
                        $record->stu_pu_01,
                        $record->stu_pu_02,
                        $record->stu_pu_03,
                        $record->stu_pu_04,
                        $record->stu_pu_05,
                        $record->averagePu(),
                        $record->stu_peou_01,
                        $record->stu_peou_02,
                        $record->stu_peou_03,
                        $record->stu_peou_04,
                        $record->averagePeou(),
                        $record->overallScore(),
                        $record->useful_features,
                        $record->difficulties_encountered,
                        $record->recommendations,
                        $record->evaluator_signature,
                        $record->ip_address,
                        $record->created_at?->toIso8601String(),
                    ]);
                }
            });
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ]);
    }

    /**
     * Clear / reset all TAM evaluation responses from the isolated SQLite store.
     */
    public function reset(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorizeAdminAccess($request);
        TamEvaluation::ensureTableExists();

        TamEvaluation::query()->truncate();

        if ($request->wantsJson() || $request->isJson()) {
            return response()->json([
                'success' => true,
                'message' => 'All evaluation responses have been cleared. Database is now clean.',
            ]);
        }

        $key = $request->input('key') ?? $request->query('key');

        return redirect()->route('tam.responses.index', array_filter(['key' => $key]))
            ->with('status', 'All TAM evaluation responses have been successfully cleared. The database is clean.');
    }

    /**
     * Calculate sample standard deviation for a set of numbers.
     *
     * @param  array<int, float|int>  $values
     */
    protected function calculateStandardDeviation(array $values): ?float
    {
        $count = count($values);
        if ($count <= 1) {
            return $count === 1 ? 0.0 : null;
        }

        $mean = array_sum($values) / $count;
        $variance = array_reduce($values, fn ($carry, $v) => $carry + pow($v - $mean, 2), 0) / ($count - 1);

        return round(sqrt($variance), 2);
    }

    /**
     * Map a Likert scale numerical mean (1.00 - 5.00) to its academic verbal interpretation.
     */
    protected function verbalInterpretation(?float $mean): string
    {
        if ($mean === null) {
            return 'No data';
        }

        return match (true) {
            $mean >= 4.50 => 'Strongly Agree (Very High Acceptance)',
            $mean >= 3.50 => 'Agree (High Acceptance)',
            $mean >= 2.50 => 'Neutral (Moderate Acceptance)',
            $mean >= 1.50 => 'Disagree (Low Acceptance)',
            default => 'Strongly Disagree (Very Low Acceptance)',
        };
    }

    /**
     * Verify whether the requester has permission to inspect TAM management data.
     */
    protected function authorizeAdminAccess(Request $request): void
    {
        // Allowed if logged-in user is authenticated staff
        if ($request->user() !== null) {
            return;
        }

        // Allowed if valid TAM admin key is provided in query or header
        $configuredKey = config('app.tam_admin_key') ?? env('TAM_ADMIN_KEY', 'nexiam-tam-research-2026');
        $providedKey = $request->input('key') ?? $request->query('key') ?? $request->header('X-TAM-Admin-Key');

        if ($configuredKey !== null && $configuredKey !== '' && $providedKey === $configuredKey) {
            return;
        }

        abort(403, 'Unauthorized access to TAM Evaluation responses. Please sign in as staff or provide a valid research access key.');
    }
}
