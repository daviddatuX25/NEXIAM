<?php

namespace App\Http\Controllers\Admin;

use App\Models\AcademicYear;
use App\Services\ReportExportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    /**
     * Display the reports hub page.
     */
    public function index(Request $request): Response
    {
        $academicYears = AcademicYear::orderByDesc('academic_year')
            ->orderByDesc('semester')
            ->get()
            ->map(fn (AcademicYear $ay) => [
                'id' => $ay->id,
                'label' => $ay->academic_year.' – '.$ay->semesterLabel(),
                'is_active' => $ay->is_active,
            ]);

        $activeAyId = $request->input('academic_year_id')
            ?? $academicYears->firstWhere('is_active', true)['id']
            ?? $academicYears->first()['id']
            ?? null;

        $service = new ReportExportService;
        $counts = $activeAyId ? $service->getCounts((int) $activeAyId) : [];
        $summaryData = $activeAyId ? $service->getSummaryData((int) $activeAyId) : [];

        return Inertia::render('Admin/Reports/Index', [
            'academicYears' => $academicYears,
            'activeAcademicYearId' => (int) $activeAyId,
            'reports' => ReportExportService::definitions(),
            'counts' => $counts,
            'summaryData' => $summaryData,
        ]);
    }

    /**
     * Export a specific report as .xlsx or .pdf download.
     */
    public function export(Request $request, string $type): StreamedResponse|HttpResponse
    {
        $request->validate([
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
        ]);

        // Domain-level authorization
        $user = Auth::user();
        $roles = $user->roles->pluck('name')->toArray();
        $isSuperAdmin = in_array('super_admin', $roles, true);

        if (! $isSuperAdmin) {
            $isRegistrar = in_array('registrar_administrator', $roles, true);
            $isTestAdmin = in_array('test_administrator', $roles, true);

            if (in_array($type, ReportExportService::REGISTRAR_REPORTS, true) && ! $isRegistrar) {
                abort(403, 'You do not have access to this report.');
            }

            if (in_array($type, ReportExportService::GUIDANCE_REPORTS, true) && ! $isTestAdmin) {
                abort(403, 'You do not have access to this report.');
            }
        }

        $academicYearId = (int) $request->input('academic_year_id');
        $filters = $request->only(['pipeline_status', 'date_from', 'date_to']);
        $service = new ReportExportService;

        // PDF export
        if ($request->input('format') === 'pdf') {
            $reportData = $service->getReportData($type, $academicYearId, $filters);

            $ay = AcademicYear::find($academicYearId);
            $ayLabel = $ay ? $ay->academic_year.' – '.$ay->semesterLabel() : (string) $academicYearId;
            $filename = $type.'_'.str_replace(['/', '\\', ' '], '-', $ayLabel).'_'.now()->format('Y-m-d').'.pdf';

            $colCount = count($reportData['headers']);

            // Cards layout (>10 cols) works best on portrait; tables (8-10 cols) need landscape
            $orientation = ($colCount > 10 || $colCount <= 8) ? 'portrait' : 'landscape';

            $pdf = Pdf::loadView('reports.pdf-report', [
                'title' => $reportData['title'],
                'headers' => $reportData['headers'],
                'rows' => $reportData['rows'],
                'academicYear' => $ayLabel,
                'generatedAt' => now()->format('F j, Y g:i A'),
                'columnCount' => $colCount,
            ])->setPaper('a4', $orientation);

            return $pdf->download($filename);
        }

        return $service->export($type, $academicYearId, $filters);
    }
}
