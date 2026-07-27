<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreApplicantImportRequest;
use App\Models\AcademicYear;
use App\Models\Application;
use App\Models\Course;
use App\Services\ApplicantImportService;
use App\Services\SpreadsheetParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ApplicationImportController extends Controller
{
    public function __construct(
        private readonly ApplicantImportService $importService,
        private readonly SpreadsheetParser $parser,
    ) {}

    /**
     * Show the CSV import form.
     */
    public function importForm(): InertiaResponse
    {
        $this->authorize('create', Application::class);

        $academicYears = AcademicYear::orderByDesc('application_start_date')
            ->get(['id', 'academic_year', 'semester', 'application_start_date', 'application_end_date']);

        $courses = Course::where('is_active', true)
            ->orderBy('code')
            ->get(['code', 'name']);

        return Inertia::render('Admin/Applications/Import', [
            'academicYears' => $academicYears,
            'courses' => $courses,
            'requiredColumns' => ApplicantImportService::REQUIRED_COLUMNS,
            'optionalColumns' => ApplicantImportService::OPTIONAL_COLUMNS,
        ]);
    }

    /**
     * Analyze uploaded file — returns header mapping, checks, and row count
     * without importing anything. Used for pre-upload validation feedback.
     */
    public function analyze(Request $request): JsonResponse
    {
        $this->authorize('create', Application::class);

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:10240'],
        ]);

        try {
            $analysis = $this->parser->analyze(
                $request->file('file'),
                ApplicantImportService::REQUIRED_COLUMNS,
                ApplicantImportService::OPTIONAL_COLUMNS
            );

            return response()->json($analysis);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'checks' => [
                    ['label' => 'File parsing', 'status' => 'fail', 'detail' => $e->getMessage()],
                ],
            ], 422);
        }
    }

    /**
     * Process CSV import.
     */
    public function import(StoreApplicantImportRequest $request): RedirectResponse
    {
        $this->authorize('create', Application::class);

        try {
            // Parse and validate CSV
            $records = $this->importService->parseSpreadsheet($request->file('file'));
            $validation = $this->importService->validateRecords($records);

            // If there are errors, redirect back with error message
            if (! empty($validation['errors'])) {
                $errorMessage = implode("\n", $validation['errors']);

                return back()->with('error', $errorMessage);
            }

            // Import valid records
            $result = $this->importService->importRecords(
                $validation['valid'],
                $request->integer('academic_year_id'),
                $request->user()->id
            );

            // Build success message
            $message = "Successfully imported {$result['imported']} applicants.";
            if ($result['skipped'] > 0) {
                $message .= " {$result['skipped']} skipped.";
            }
            if (! empty($result['errors'])) {
                $message .= "\nErrors:\n".implode("\n", $result['errors']);
            }

            Log::info('Bulk applicant import completed', [
                'user_id' => $request->user()->id,
                'academic_year_id' => $request->integer('academic_year_id'),
                'imported' => $result['imported'],
                'skipped' => $result['skipped'],
            ]);

            return back()->with('message', $message);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            Log::error('Bulk applicant import failed', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Import failed: '.$e->getMessage());
        }
    }

    /**
     * Preview parsed CSV data before importing.
     */
    public function preview(StoreApplicantImportRequest $request): RedirectResponse|InertiaResponse
    {
        $this->authorize('create', Application::class);

        try {
            // Parse CSV
            $records = $this->importService->parseSpreadsheet($request->file('file'));

            // Validate with details
            $validated = $this->importService->validateRecordsWithDetails($records);

            // Store in session for confirm step
            Session::put('import_records', $records);
            Session::put('import_academic_year_id', $request->integer('academic_year_id'));

            $academicYears = AcademicYear::orderByDesc('application_start_date')
                ->get(['id', 'academic_year', 'semester', 'application_start_date', 'application_end_date']);

            return Inertia::render('Admin/Applications/ImportPreview', [
                'records' => $validated,
                'totalCount' => count($records),
                'validCount' => array_sum(array_column($validated, 'is_valid')),
                'academicYearId' => $request->integer('academic_year_id'),
                'academicYears' => $academicYears,
            ]);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            Log::error('Bulk applicant import preview failed', [
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Preview failed: '.$e->getMessage());
        }
    }

    /**
     * Confirm and import selected records.
     */
    public function confirm(Request $request): RedirectResponse
    {
        $this->authorize('create', Application::class);

        $records = Session::get('import_records', []);
        $academicYearId = Session::get('import_academic_year_id');

        if (empty($records)) {
            return redirect()->route('admin.applications.import')
                ->with('error', 'No import data found. Please upload again.');
        }

        try {
            $selectedIds = $request->input('selected_ids', []);

            // If empty, import all valid
            if (empty($selectedIds)) {
                $result = $this->importService->importRecords(
                    $records,
                    $academicYearId,
                    $request->user()->id
                );
            } else {
                $result = $this->importService->importSelectedRecords(
                    $records,
                    $selectedIds,
                    $academicYearId,
                    $request->user()->id
                );
            }

            // Clear session
            Session::forget(['import_records', 'import_academic_year_id']);

            $message = "Successfully imported {$result['imported']} applicants.";
            if ($result['skipped'] > 0) {
                $message .= " {$result['skipped']} skipped.";
            }
            if (! empty($result['errors'])) {
                $message .= "\nErrors:\n".implode("\n", $result['errors']);
            }

            Log::info('Bulk applicant import confirmed', [
                'user_id' => $request->user()->id,
                'academic_year_id' => $academicYearId,
                'imported' => $result['imported'],
                'skipped' => $result['skipped'],
            ]);

            return redirect()->route('admin.applications.import')
                ->with('message', $message);
        } catch (\Exception $e) {
            Log::error('Bulk applicant import confirm failed', [
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('admin.applications.import')
                ->with('error', 'Import failed: '.$e->getMessage());
        }
    }

    /**
     * Download a CSV template for applicant import.
     */
    public function template(): StreamedResponse
    {
        $headers = array_merge(
            ApplicantImportService::REQUIRED_COLUMNS,
            ApplicantImportService::OPTIONAL_COLUMNS
        );

        return response()->streamDownload(function () use ($headers) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);
            fputcsv($handle, [
                'Juan',            // first_name
                'Dela Cruz',       // last_name
                'APP-2026-00001',  // applicant_number
                'juan.delacruz@example.com', // email
                'Santos',          // middle_name
                'Jr.',             // suffix
                '2000-01-15',      // birthdate
                'Male',            // sex
                'New',             // applicant_type
                'Manila High School', // last_school_enrolled
                'STEM',            // strand
                '09171234567',     // phone
                '123 Main St',     // address_line
                'Manila',          // city
                'Metro Manila',    // province
                '1000',            // zip_code
                'BSCS',            // course_preference_1
                'BSIT',            // course_preference_2
                '',                // course_preference_3
                '1.50',            // gwa
            ]);
            fclose($handle);
        }, 'applicant_import_template.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }
}
