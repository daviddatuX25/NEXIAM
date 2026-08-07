<?php

namespace App\Http\Controllers\Grading;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreScoreImportRequest;
use App\Models\AptitudeArea;
use App\Models\GradingSession;
use App\Models\SystemSetting;
use App\Services\AuditService;
use App\Services\ScoreImportService;
use App\Services\SpreadsheetParser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ScoreImportController extends Controller
{
    public function __construct(
        private readonly ScoreImportService $importService,
        private readonly SpreadsheetParser $parser,
    ) {}

    /**
     * Show the CSV import form.
     */
    public function importForm(): InertiaResponse
    {
        $this->authorize('viewAny', GradingSession::class);

        $stalePath = Session::get('score_import_temp_path');
        if ($stalePath && Storage::exists($stalePath)) {
            Storage::delete($stalePath);
        }
        Session::forget('score_import_temp_path');

        $aptitudeAreaCodes = AptitudeArea::where('is_active', true)->pluck('code')->toArray();

        // Score columns are "optional" from the file-structure perspective;
        // the user can import any subset of aptitude areas.
        $optionalColumns = array_map('strtolower', $aptitudeAreaCodes);

        return Inertia::render('Grading/Import', [
            'enableNormalizedScores' => SystemSetting::enableNormalizedScores(),
            'aptitudeAreaCodes' => $aptitudeAreaCodes,
            'requiredColumns' => ScoreImportService::REQUIRED_COLUMNS,
            'optionalColumns' => $optionalColumns,
            'previewUrl' => route('admin.grading.import.preview'),
        ]);
    }

    /**
     * Process CSV import.
     */
    public function import(StoreScoreImportRequest $request): RedirectResponse
    {
        $this->authorize('viewAny', GradingSession::class);

        try {
            $records = $this->importService->parseSpreadsheet($request->file('file'));
            $result = $this->importService->importScores($records, $request->user()->id);

            app(AuditService::class)->log('import.scores', null, null, [], [
                'imported' => $result['imported'],
                'skipped' => $result['skipped'],
            ]);

            $message = "Successfully imported {$result['imported']} scores.";
            if ($result['skipped'] > 0) {
                $message .= " {$result['skipped']} skipped.";
            }

            return back()->with('message', $message);
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Analyze uploaded file structure for real-time pre-upload feedback.
     */
    public function analyze(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:10240',
        ]);

        $aptitudeAreaCodes = AptitudeArea::where('is_active', true)->pluck('code')->map(fn ($c) => strtolower($c))->toArray();

        try {
            $analysis = $this->parser->analyze(
                $request->file('file'),
                ScoreImportService::REQUIRED_COLUMNS,
                $aptitudeAreaCodes
            );

            return response()->json($analysis);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'checks' => [['label' => 'File parsing', 'status' => 'fail', 'detail' => $e->getMessage()]],
            ], 422);
        }
    }

    /**
     * Download a CSV template for score imports.
     */
    public function template(): StreamedResponse
    {
        $aptitudeAreaCodes = AptitudeArea::where('is_active', true)->pluck('code')->toArray();
        $headers = array_merge(['applicant_number'], $aptitudeAreaCodes);

        return response()->streamDownload(function () use ($headers) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);
            fclose($handle);
        }, 'score_import_template.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * Preview parsed CSV data before importing.
     */
    public function preview(StoreScoreImportRequest $request): RedirectResponse|InertiaResponse
    {
        $this->authorize('viewAny', GradingSession::class);

        try {
            $file = $request->file('file');

            // Parse directly from the uploaded file (SpreadsheetParser handles path resolution)
            $records = $this->importService->parseSpreadsheet($file);
            $validated = $this->importService->validateRecords($records);

            // Store the file for the confirm step (avoids getRealPath issues in $file->store())
            $tempName = uniqid('score_import_').'.'.$file->getClientOriginalExtension();
            $tempPath = 'temp/score_imports/'.$tempName;
            Storage::put($tempPath, file_get_contents($file->getPathname()));
            Session::put('score_import_temp_path', $tempPath);

            return Inertia::render('Grading/ImportPreview', [
                'records' => $validated['records'],
                'totalCount' => $validated['summary']['total'],
                'validCount' => $validated['summary']['valid'],
                'enableNormalizedScores' => SystemSetting::enableNormalizedScores(),
                'aptitudeAreaCodes' => AptitudeArea::where('is_active', true)->pluck('code')->toArray(),
                'confirmUrl' => route('admin.grading.import.confirm'),
            ]);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            Log::error('Bulk score import preview failed', ['error' => $e->getMessage()]);

            return back()->with('error', 'Preview failed: '.$e->getMessage());
        }
    }

    /**
     * Confirm and import selected records.
     */
    public function confirm(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', GradingSession::class);

        $tempPath = Session::get('score_import_temp_path');

        if (! $tempPath || ! Storage::exists($tempPath)) {
            return redirect()->route('admin.grading.import')->with('error', 'Import session expired. Please upload again.');
        }

        try {
            $records = $this->importService->parseSpreadsheet(Storage::path($tempPath));
            $selectedIds = $request->input('selected_ids', []);

            $result = $this->importService->importSelectedScores($records, $selectedIds, $request->user()->id);

            Storage::delete($tempPath);
            Session::forget('score_import_temp_path');

            $message = "Successfully imported {$result['imported']} scores.";
            if ($result['skipped'] > 0) {
                $message .= " {$result['skipped']} skipped.";
            }
            if (! empty($result['errors'])) {
                $message .= "\nErrors:\n".implode("\n", $result['errors']);
            }

            app(AuditService::class)->log('import.scores_confirmed', null, null, [], [
                'imported' => $result['imported'],
                'skipped' => $result['skipped'],
                'selected_count' => count($selectedIds),
            ]);

            return redirect()->route('admin.grading.import')->with('message', $message);
        } catch (\Exception $e) {
            Log::error('Bulk score import confirm failed', ['error' => $e->getMessage()]);

            return redirect()->route('admin.grading.import')->with('error', 'Import failed: '.$e->getMessage());
        }
    }
}
