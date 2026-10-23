<?php

namespace App\Http\Controllers\Reports;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\DocumentTemplate\Contracts\DocumentSurfaceInterface;
use App\DocumentTemplate\DocumentSurfaceRegistry;
use App\DocumentTemplate\Rendering\DocumentBlueprintCompiler;
use App\DocumentTemplate\Rendering\DocxTemplateFiller;
use App\DocumentTemplate\Rendering\LibreOfficeConverter;
use App\DocumentTemplate\Rendering\ReportHtmlContextFiller;
use App\DocumentTemplate\Support\DocxTextExtractor;
use App\DocumentTemplate\Validation\DocumentTemplateAnalyzer;
use App\Foundation\Actions\RecordAuditEvent;
use App\Http\Controllers\Controller;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Foundation\AcademicCycle;
use App\Models\Reports\ReportTemplate;
use App\Models\User;
use App\Printing\PrintRenderer;
use App\Reports\Queries\ReportProjection;
use App\Reports\Surfaces\CourseQuotaQualifiersSurface;
use App\Reports\Surfaces\PassingReportSurface;
use App\ResultSheet\Builder\ResultSheetBuilderSchema;
use App\ResultSheet\Rendering\ResultSheetCssService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpWord\PhpWord;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly RecordAuditEvent $recordAuditEvent
    ) {}

    public function index(Request $request, ReportProjection $projection): Response
    {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }

        $canRegistrar = $this->authorizer->allows($user, CapabilityCatalog::REGISTRAR_REPORTS_VIEW);
        $canGuidance = $this->authorizer->allows($user, CapabilityCatalog::GUIDANCE_REPORTS_VIEW);
        $canExecutive = $this->authorizer->allows($user, CapabilityCatalog::EXECUTIVE_DASHBOARD_VIEW);
        $canProgramHead = $this->authorizer->allows($user, CapabilityCatalog::PROGRAM_HEAD_VIEW);

        if (! $canRegistrar && ! $canGuidance && ! $canExecutive && ! $canProgramHead) {
            abort(403, 'You do not have capability to view reports.');
        }

        $cycleId = $request->query('cycle_id') ? (int) $request->query('cycle_id') : null;

        return Inertia::render('Reports/Index', [
            'report' => $projection->for($user, $cycleId),
        ]);
    }

    public function export(
        Request $request,
        string $type,
        PassingReportSurface $passingReport,
        CourseQuotaQualifiersSurface $courseQuotaQualifiers,
        DocxTemplateFiller $filler,
        LibreOfficeConverter $converter,
        DocumentBlueprintCompiler $compiler,
        ReportHtmlContextFiller $htmlFiller,
        PrintRenderer $printRenderer,
        DocumentTemplateAnalyzer $analyzer
    ): StreamedResponse|HttpResponse {
        $user = $request->user();
        abort_unless($user !== null, 401);
        $this->authorizeExport($user, $type);
        $format = (string) $request->query('format', $type === 'passing-report' ? 'docx' : 'csv');
        abort_unless(in_array($format, ['docx', 'pdf', 'csv'], true), 422, 'Unsupported report format.');
        $surfaceKey = match ($type) {
            'passing-report' => 'passing_report',
            'course-quota-qualifiers', 'course_quota_qualifiers' => 'course_quota_qualifiers',
            default => DocumentSurfaceRegistry::resolveByReportType($type)?->surfaceKey()
                ?? (DocumentSurfaceRegistry::has(str_replace('-', '_', $type)) ? str_replace('-', '_', $type) : null),
        };

        $filters = $request->query();
        $hasExplicitCycle = array_key_exists('academic_cycle_id', $filters);
        $requestedCycle = $hasExplicitCycle ? $filters['academic_cycle_id'] : ($filters['cycle_id'] ?? null);
        abort_unless($requestedCycle === null || is_scalar($requestedCycle), 422, 'Academic cycle filter is invalid.');
        $cycleId = $requestedCycle !== null && $requestedCycle !== ''
            ? (int) $requestedCycle
            : ($surfaceKey !== null ? AcademicCycle::active()?->id : null);
        unset($filters['cycle_id']);
        unset($filters['academic_cycle_id']);
        if ($cycleId !== null) {
            $filters['academic_cycle_id'] = $cycleId;
        }
        if ($user->actorScope()->office === 'program' && $user->actorScope()->programCodes !== []) {
            $filters['authorized_program_codes'] = $user->actorScope()->programCodes;
        }

        $selectedTemplate = $format === 'pdf' && $surfaceKey !== null
            ? ReportTemplate::query()->where('surface_key', $surfaceKey)->where('is_active', true)->latest('id')->first()
            : null;

        if ($type === 'passing-report') {
            try {
                $data = $passingReport->resolveData($filters);
            } catch (\Throwable $exception) {
                if ($format === 'pdf') {
                    $this->recordPdfFailureAudit($type, $selectedTemplate, $filters, $cycleId, $user);
                }
                throw $exception;
            }
            if ($format === 'pdf') {
                $pdf = $this->renderReportPdf(
                    $type, 'passing_report', $selectedTemplate, $data, $filters, $cycleId,
                    $user, $filler, $converter, $compiler, $htmlFiller, $printRenderer, $analyzer
                );

                return $this->pdfDownload($pdf, $type);
            }

            $this->recordAuditEvent->handle('reports.export_downloaded', [
                'type' => $type, 'format' => $format, 'cycle_id' => $cycleId,
                'filters' => $this->auditFilters($filters), 'actor_id' => $user->id,
                'office' => $user->actorScope()->office,
            ], (string) $user->id);

            if ($format === 'csv') {
                return $this->passingReportCsv($data);
            }

            return $this->downloadTemporaryFile(
                $this->passingReportDocument($data, $filler),
                $this->filename($type, 'docx'),
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            );
        }

        if (in_array($type, ['course-quota-qualifiers', 'course_quota_qualifiers'], true)) {
            try {
                $data = $courseQuotaQualifiers->resolveData($filters);
            } catch (\Throwable $exception) {
                if ($format === 'pdf') {
                    $this->recordPdfFailureAudit($type, $selectedTemplate, $filters, $cycleId, $user);
                }
                throw $exception;
            }
            if ($format === 'pdf') {
                $pdf = $this->renderReportPdf(
                    'course-quota-qualifiers', 'course_quota_qualifiers', $selectedTemplate,
                    $data, $filters, $cycleId, $user, $filler, $converter, $compiler, $htmlFiller,
                    $printRenderer, $analyzer
                );

                return $this->pdfDownload($pdf, 'course-quota-qualifiers');
            }

            $this->recordAuditEvent->handle('reports.export_downloaded', [
                'type' => $type, 'format' => $format, 'cycle_id' => $cycleId,
                'filters' => $this->auditFilters($filters), 'actor_id' => $user->id,
                'office' => $user->actorScope()->office,
            ], (string) $user->id);

            if ($format === 'csv') {
                return $this->courseQuotaQualifiersCsv($data);
            }

            return $this->downloadTemporaryFile(
                $this->courseQuotaQualifiersDocument($data, $filler),
                $this->filename('course-quota-qualifiers', 'docx'),
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            );
        }
        $registeredSurface = DocumentSurfaceRegistry::resolveByReportType($type)
            ?? DocumentSurfaceRegistry::resolve(str_replace('-', '_', $type));

        if ($registeredSurface !== null) {
            try {
                $data = $registeredSurface->resolveData($filters);
            } catch (\Throwable $exception) {
                if ($format === 'pdf') {
                    $this->recordPdfFailureAudit($type, $selectedTemplate, $filters, $cycleId, $user);
                }
                throw $exception;
            }

            if ($format === 'pdf') {
                $pdf = $this->renderReportPdf(
                    $type, $registeredSurface->surfaceKey(), $selectedTemplate,
                    $data, $filters, $cycleId, $user, $filler, $converter, $compiler, $htmlFiller,
                    $printRenderer, $analyzer
                );

                return $this->pdfDownload($pdf, $type);
            }

            $this->recordAuditEvent->handle('reports.export_downloaded', [
                'type' => $type, 'format' => $format, 'cycle_id' => $cycleId,
                'filters' => $this->auditFilters($filters), 'actor_id' => $user->id,
                'office' => $user->actorScope()->office,
            ], (string) $user->id);

            if ($format === 'csv') {
                return $this->genericSurfaceCsv($registeredSurface, $data);
            }

            return $this->downloadTemporaryFile(
                $this->genericSurfaceDocument($registeredSurface, $data),
                $this->filename($type, 'docx'),
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            );
        }

        abort_unless($format === 'csv', 422, 'This report currently supports CSV export only.');
        $this->recordAuditEvent->handle('reports.export_downloaded', [
            'type' => $type, 'format' => $format, 'cycle_id' => $cycleId,
            'filters' => $this->auditFilters($filters), 'actor_id' => $user->id,
            'office' => $user->actorScope()->office,
        ], (string) $user->id);

        return $this->registrarCsv($type, $cycleId);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $filters
     */
    private function renderReportPdf(
        string $type,
        string $surfaceKey,
        ?ReportTemplate $template,
        array $data,
        array $filters,
        ?int $cycleId,
        User $user,
        DocxTemplateFiller $docxFiller,
        LibreOfficeConverter $converter,
        DocumentBlueprintCompiler $compiler,
        ReportHtmlContextFiller $htmlFiller,
        PrintRenderer $printRenderer,
        DocumentTemplateAnalyzer $analyzer
    ): string {
        $surface = DocumentSurfaceRegistry::resolve($surfaceKey);
        abort_if($surface === null, 422, 'Report surface is unavailable.');
        $checksum = $template?->sourceChecksum();
        $sourceKind = $template?->source_kind ?? 'default';
        $compilerVersion = $template?->compiler_version;

        try {
            if ($template === null) {
                if ($surfaceKey === 'passing_report') {
                    $docxPath = $this->passingReportDocument($data, $docxFiller, useSelectedTemplate: true);
                    try {
                        $pdfBytes = $converter->convertToPdf($docxPath);
                    } finally {
                        @unlink($docxPath);
                    }
                } elseif ($surfaceKey === 'course_quota_qualifiers') {
                    $docxPath = $this->courseQuotaQualifiersDocument($data, $docxFiller, useSelectedTemplate: true);
                    try {
                        $pdfBytes = $converter->convertToPdf($docxPath);
                    } finally {
                        @unlink($docxPath);
                    }
                } else {
                    $blueprint = $surface->defaultBlueprint();
                    $document = ResultSheetBuilderSchema::normalize($blueprint);
                    $html = $compiler->compile($document);
                    $filledHtml = $htmlFiller->fill($html, $data, $surface->repeatingRegions());
                    $paperSize = $document['page']['paper_size'] ?? 'a4';
                    $orientation = $document['page']['orientation'] ?? 'portrait';
                    $margin = (float) ($document['page']['margin_mm'] ?? 12);
                    $wrapped = (new ResultSheetCssService)->wrapForPdf(
                        $filledHtml,
                        $paperSize,
                        $orientation,
                        $margin
                    );
                    $paper = config('result-sheet.paper_sizes.'.$paperSize) ?? ['width_mm' => 210, 'height_mm' => 297];
                    $pdfBytes = $printRenderer->render($wrapped, [
                        'format' => $paperSize,
                        'orientation' => $orientation,
                        'width_mm' => (float) ($paper['width_mm'] ?? 210),
                        'height_mm' => (float) ($paper['height_mm'] ?? 297),
                    ]);
                }
            } elseif ($template->mode === 'docx' && $template->source_kind === ReportTemplate::SOURCE_DOCX) {
                $path = $template->document_path === null
                    ? ''
                    : Storage::disk('local')->path($template->document_path);
                abort_unless($path !== '' && is_file($path), 422, 'The selected report template is unavailable.');
                abort_unless(hash_file('sha256', $path) === $template->document_checksum, 422, 'The selected report template is stale.');
                $filledPath = $docxFiller->fill($path, $data, $surface->repeatingRegions());
                try {
                    $pdfBytes = $converter->convertToPdf($filledPath);
                } finally {
                    @unlink($filledPath);
                }
            } elseif ($template->mode === ReportTemplate::MODE_HTML && $template->source_kind === ReportTemplate::SOURCE_BUILDER) {
                abort_unless(
                    is_array($template->builder_json)
                        && $template->builder_schema_version === 1
                        && $template->compiler_version === 'document-blueprint-1',
                    422,
                    'The selected report builder is missing or unsupported.'
                );
                $catalog = $surface->placeholderCatalog();
                $document = ResultSheetBuilderSchema::normalize($template->builder_json);
                abort_unless(
                    in_array($template->paper_size, ['a4', 'letter', 'legal'], true)
                        && in_array($template->orientation, ['portrait', 'landscape'], true)
                        && ($document['page']['paper_size'] ?? null) === $template->paper_size
                        && ($document['page']['orientation'] ?? null) === $template->orientation,
                    422,
                    'The selected report builder page settings are stale.'
                );
                $allowedBindings = array_values(array_unique(array_merge(
                    $catalog['all'] ?? [],
                    ['page_i', 'page_all', 'pg_i', 'pg_all']
                )));
                $allowedRepeatKeys = [];
                foreach ($allowedBindings as $binding) {
                    if (is_string($binding) && str_contains($binding, '.')) {
                        $allowedRepeatKeys[explode('.', $binding, 2)[0]] = true;
                    }
                }
                foreach ($document['components'] ?? [] as $component) {
                    if (($component['type'] ?? null) !== 'data_table') {
                        continue;
                    }
                    $repeatKey = $component['props']['repeat_key'] ?? 'repeat_key';
                    abort_unless(is_string($repeatKey) && isset($allowedRepeatKeys[$repeatKey]), 422, 'The selected report builder uses an unsupported repeating source.');
                }
                abort_unless(
                    array_diff($compiler->usedBindings($document), $allowedBindings) === [],
                    422,
                    'The selected report builder uses bindings outside this report surface.'
                );
                $diagnostics = ResultSheetBuilderSchema::diagnostics($document, null, $allowedBindings);
                abort_if($diagnostics !== [], 422, 'The selected report builder is invalid.');
                $html = $compiler->compile($document);
                abort_unless(is_string($html) && $html !== '', 422, 'The selected report builder could not be compiled.');
                if (! is_string($template->compiled_html) || ! hash_equals($template->compiled_html, $html)) {
                    $template->update(['compiled_html' => $html]);
                    $checksum = $template->sourceChecksum();
                }
                $margin = (float) ($document['page']['margin_mm'] ?? 12);
                $wrapped = (new ResultSheetCssService)->wrapForPdf(
                    $htmlFiller->fill($html, $data),
                    $template->paper_size,
                    $template->orientation,
                    $margin
                );
                $paper = config('result-sheet.paper_sizes.'.$template->paper_size);
                abort_unless(is_array($paper) && is_numeric($paper['width_mm'] ?? null) && is_numeric($paper['height_mm'] ?? null), 422, 'The selected report paper size is unavailable.');
                $pdfBytes = $printRenderer->render($wrapped, [
                    'format' => $template->paper_size,
                    'orientation' => $template->orientation,
                    'width_mm' => (float) $paper['width_mm'],
                    'height_mm' => (float) $paper['height_mm'],
                ]);
            } elseif ($template->mode === ReportTemplate::MODE_HTML && $template->source_kind === ReportTemplate::SOURCE_HTML) {
                abort_unless(
                    in_array($template->paper_size, ['a4', 'letter', 'legal'], true)
                        && in_array($template->orientation, ['portrait', 'landscape'], true),
                    422,
                    'The selected HTML report page settings are unsupported.'
                );
                $paper = config('result-sheet.paper_sizes.'.$template->paper_size);
                abort_unless(is_array($paper) && is_numeric($paper['width_mm'] ?? null) && is_numeric($paper['height_mm'] ?? null), 422, 'The selected report paper size is unavailable.');
                $html = $template->compiled_html ?? $template->content;
                abort_unless(is_string($html) && trim($html) !== '', 422, 'The selected HTML report template is empty.');
                $catalog = $surface->placeholderCatalog();
                $allowedBindings = $catalog['all'] ?? null;
                abort_unless(is_array($allowedBindings), 422, 'The selected report surface catalog is unavailable.');
                $analysis = $analyzer->analyze($html, [
                    'required' => [],
                    'recommended' => [],
                    'all' => $allowedBindings,
                ]);
                abort_unless($analysis['valid'], 422, 'The selected HTML report template contains unsupported or unresolved bindings.');
                $wrapped = (new ResultSheetCssService)->wrapForPdf(
                    $htmlFiller->fill($html, $data),
                    $template->paper_size,
                    $template->orientation,
                    12
                );
                $pdfBytes = $printRenderer->render($wrapped, [
                    'format' => $template->paper_size,
                    'orientation' => $template->orientation,
                    'width_mm' => (float) $paper['width_mm'],
                    'height_mm' => (float) $paper['height_mm'],
                ]);
            } else {
                abort(422, 'The selected report template source is unsupported.');
            }

            if ($template !== null) {
                abort_unless($template->fresh()?->sourceChecksum() === $checksum, 409, 'The selected report template changed during rendering.');
            }
            abort_unless(str_starts_with($pdfBytes, "\x25\x50\x44\x46"), 500, 'Report PDF rendering failed.');

            $this->recordAuditEvent->handle('reports.export_downloaded', [
                'type' => $type,
                'format' => 'pdf',
                'academic_cycle_id' => $cycleId,
                'cycle_id' => $cycleId,
                'filters' => $this->auditFilters($filters),
                'actor_id' => $user->id,
                'office' => $user->actorScope()->office,
                'template_id' => $template?->id,
                'template_source_kind' => $sourceKind,
                'template_checksum' => $checksum,
                'compiler_version' => $compilerVersion,
                'rendered_pdf_sha256' => hash('sha256', $pdfBytes),
                'render_status' => 'success',
            ], (string) $user->id);

            return $pdfBytes;
        } catch (\Throwable $exception) {
            $this->recordPdfFailureAudit($type, $template, $filters, $cycleId, $user);
            throw $exception;
        }
    }

    /** @param array<string, mixed> $filters */
    private function auditFilters(array $filters): array
    {
        return array_intersect_key($filters, array_flip(['academic_cycle_id', 'program_code', 'cutoff_score']));
    }

    /** @param array<string, mixed> $filters */
    private function recordPdfFailureAudit(string $type, ?ReportTemplate $template, array $filters, ?int $cycleId, User $user): void
    {
        $this->recordAuditEvent->handle('reports.export_downloaded', [
            'type' => $type,
            'format' => 'pdf',
            'academic_cycle_id' => $cycleId,
            'cycle_id' => $cycleId,
            'filters' => $this->auditFilters($filters),
            'actor_id' => $user->id,
            'office' => $user->actorScope()->office,
            'template_id' => $template?->id,
            'template_source_kind' => $template?->source_kind ?? 'default',
            'template_checksum' => $template?->sourceChecksum(),
            'compiler_version' => $template?->compiler_version,
            'rendered_pdf_sha256' => null,
            'render_status' => 'failed',
        ], (string) $user->id);
    }

    private function pdfDownload(string $pdfBytes, string $type): StreamedResponse
    {
        return response()->streamDownload(static function () use ($pdfBytes): void {
            echo $pdfBytes;
        }, $this->filename($type, 'pdf'), ['Content-Type' => 'application/pdf']);
    }

    public function templates(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);
        $surface = (string) $request->query('surface', 'passing_report');
        abort_unless(DocumentSurfaceRegistry::has($surface), 422, 'Unsupported report surface.');
        $this->authorizeTemplateManagement($user, $surface);

        return response()->json([
            'surface' => $surface,
            'templates' => ReportTemplate::query()->where('surface_key', $surface)->latest('id')->get([
                'id', 'surface_key', 'name', 'mode', 'paper_size', 'orientation', 'document_extension',
                'is_active', 'created_by_user_id', 'created_at', 'updated_at',
            ]),
        ]);
    }

    public function storeTemplate(
        Request $request,
        DocxTextExtractor $extractor,
        DocumentTemplateAnalyzer $analyzer
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user !== null, 401);
        $requestedSurface = (string) $request->input('surface', 'passing_report');
        if (DocumentSurfaceRegistry::has($requestedSurface)) {
            $this->authorizeTemplateManagement($user, $requestedSurface);
        }
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'surface' => ['required', 'in:passing_report,course_quota_qualifiers'],
            'document' => ['required', 'file', 'mimes:docx', 'max:20480'],
        ]);
        $surfaceKey = $validated['surface'];
        $this->authorizeTemplateManagement($user, $surfaceKey);
        $surface = DocumentSurfaceRegistry::resolve($surfaceKey);
        abort_if($surface === null, 404);
        $file = $request->file('document');
        abort_unless($file !== null && $file->isValid(), 422, 'A valid DOCX document is required.');
        $inspection = $extractor->inspect($file->getRealPath());
        abort_if($inspection['unresolved'] !== [], 422, 'The DOCX contains unsupported or malformed macros.');
        $analysis = $analyzer->analyze($extractor->text($file->getRealPath()), $surface);
        abort_unless($analysis['valid'], 422, 'The DOCX macros do not match this report surface placeholder catalog.');

        $template = DB::transaction(function () use ($validated, $file, $user, $surfaceKey): ReportTemplate {
            $template = ReportTemplate::query()->create([
                'surface_key' => $surfaceKey,
                'name' => $validated['name'],
                'mode' => 'docx',
                'source_kind' => 'docx',
                'document_extension' => 'docx',
                'document_checksum' => hash_file('sha256', $file->getRealPath()),
                'created_by_user_id' => $user->getAuthIdentifier(),
            ]);
            $path = "report-templates/{$surfaceKey}/{$template->id}.docx";
            if (! Storage::disk('local')->putFileAs("report-templates/{$surfaceKey}", $file, "{$template->id}.docx")) {
                throw new \RuntimeException('Unable to store report template.');
            }
            $template->document_path = $path;
            $template->save();

            return $template;
        });

        return response()->json(['id' => $template->id], 201);
    }

    public function activateTemplate(Request $request, ReportTemplate $template): HttpResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);
        abort_unless(DocumentSurfaceRegistry::has($template->surface_key), 404);
        $this->authorizeTemplateManagement($user, $template->surface_key);

        DB::transaction(function () use ($template): void {
            ReportTemplate::query()->where('surface_key', $template->surface_key)->lockForUpdate()->get();
            ReportTemplate::query()->where('surface_key', $template->surface_key)->update(['is_active' => false]);
            $template->forceFill(['is_active' => true])->save();
        });

        return response()->noContent();
    }

    public function previewTemplate(
        Request $request,
        DocxTemplateFiller $filler,
        LibreOfficeConverter $converter
    ): StreamedResponse {
        $user = $request->user();
        abort_unless($user !== null, 401);
        $validated = $request->validate([
            'template_id' => ['required', 'integer', 'exists:report_templates,id'],
            'format' => ['sometimes', 'in:docx,pdf'],
        ]);
        $template = ReportTemplate::query()->findOrFail($validated['template_id']);
        abort_unless(DocumentSurfaceRegistry::has($template->surface_key), 404);
        $this->authorizeTemplateManagement($user, $template->surface_key);
        $surface = DocumentSurfaceRegistry::resolve($template->surface_key);
        abort_if($surface === null, 404);
        $path = Storage::disk('local')->path((string) $template->document_path);
        abort_unless($template->document_path !== null && is_file($path), 404, 'Template document is unavailable.');
        $filledPath = $filler->fill($path, $surface->sampleContext(), $surface->repeatingRowKey() ?? '');
        $format = $validated['format'] ?? 'docx';
        if ($format === 'pdf') {
            try {
                $contents = $converter->convertToPdf($filledPath);
            } finally {
                @unlink($filledPath);
            }

            return response()->streamDownload(static function () use ($contents): void {
                echo $contents;
            }, $this->filename($template->surface_key, 'pdf'), ['Content-Type' => 'application/pdf']);
        }

        return $this->downloadTemporaryFile($filledPath, $this->filename($template->surface_key, 'docx'), 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    }

    /** @param array<string, mixed> $data */
    private function courseQuotaQualifiersCsv(array $data): StreamedResponse
    {
        return response()->streamDownload(function () use ($data): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                throw new \RuntimeException('Unable to open CSV output stream.');
            }
            fputcsv($handle, ['Row #', 'Reference Code', 'Applicant Name', 'Strand', 'GWA', 'Program', 'Admission Status', 'Accepted At']);
            foreach ($data['qualifiers'] ?? [] as $row) {
                fputcsv($handle, array_map(fn (mixed $value): string => $this->sanitizeCsvField(is_scalar($value) ? (string) $value : ''), [
                    $row['row_no'] ?? '', $row['reference_code'] ?? '', $row['applicant_name'] ?? '', $row['strand'] ?? '',
                    $row['gwa'] ?? '', $row['program_code'] ?? '', $row['admission_status'] ?? '', $row['accepted_at'] ?? '',
                ]));
            }
            fclose($handle);
        }, $this->filename('course-quota-qualifiers', 'csv'), ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @param array<string, mixed> $data */
    private function courseQuotaQualifiersDocument(array $data, DocxTemplateFiller $filler, ?ReportTemplate $selected = null, bool $useSelectedTemplate = false): string
    {
        $template = $useSelectedTemplate ? $selected : ReportTemplate::query()
            ->where('surface_key', 'course_quota_qualifiers')
            ->where('is_active', true)
            ->latest('id')
            ->first();
        if ($template !== null && $template->document_path !== null) {
            $path = Storage::disk('local')->path($template->document_path);
            abort_unless(is_file($path), 500, 'Active report template document is unavailable.');

            return $filler->fill($path, $data, 'qualifiers');
        }

        $word = new PhpWord;
        $section = $word->addSection();
        $section->addTitle('Course Quota Qualifiers', 1);
        $section->addText('Academic cycle: '.($data['academic_cycle'] ?? ''));
        $section->addText('Target quota: '.($data['target_quota'] ?? 0).' | Accepted: '.($data['accepted_count'] ?? 0).' | Remaining slots: '.($data['remaining_slots'] ?? 0).' | Fill rate: '.($data['fill_rate'] ?? '0%'));
        $table = $section->addTable(['borderSize' => 6, 'borderColor' => '999999', 'cellMargin' => 80]);
        $columns = ['row_no', 'reference_code', 'applicant_name', 'strand', 'gwa', 'program_code', 'admission_status', 'accepted_at'];
        $table->addRow(null, ['tblHeader' => true, 'cantSplit' => true]);
        foreach (['Row #', 'Reference Code', 'Applicant Name', 'Strand', 'GWA', 'Program', 'Admission Status', 'Accepted At'] as $heading) {
            $table->addCell()->addText($heading, ['bold' => true]);
        }
        foreach ($data['qualifiers'] ?? [] as $row) {
            $table->addRow(null, ['cantSplit' => true]);
            foreach ($columns as $column) {
                $table->addCell()->addText((string) ($row[$column] ?? ''));
            }
        }
        $path = tempnam(sys_get_temp_dir(), 'course-quota-qualifiers-');
        if ($path === false) {
            throw new \RuntimeException('Unable to allocate report document.');
        }
        $docxPath = $path.'.docx';
        @unlink($path);
        $word->save($docxPath, 'Word2007');

        return $docxPath;
    }

    /** @param array<string, mixed> $data */
    private function passingReportCsv(array $data): StreamedResponse
    {
        return response()->streamDownload(function () use ($data): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                throw new \RuntimeException('Unable to open CSV output stream.');
            }
            fputcsv($handle, ['Row #', 'Reference Code', 'Applicant Name', 'Sex', 'Strand', 'Program', 'Composite Score', 'Stanine', 'Rating', 'Remarks']);
            foreach ($data['passers'] as $row) {
                fputcsv($handle, array_map(fn (mixed $value): string => $this->sanitizeCsvField(is_scalar($value) ? (string) $value : ''), [
                    $row['row_no'] ?? '', $row['reference_code'] ?? '', $row['applicant_name'] ?? '', $row['sex'] ?? '',
                    $row['strand'] ?? '', $row['program_code'] ?? '', $row['composite_score'] ?? '', $row['stanine'] ?? '',
                    $row['rating'] ?? '', $row['remarks'] ?? '',
                ]));
            }
            fclose($handle);
        }, $this->filename('passing-report', 'csv'), ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function registrarCsv(string $type, ?int $cycleId): StreamedResponse
    {
        return response()->streamDownload(function () use ($cycleId): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Reference Code', 'Status', 'Preferred Program', 'Strand', 'GWA', 'Submitted At']);
            $attempts = AdmissionAttempt::query()->forPracticeSet(null)->when($cycleId !== null, fn ($query) => $query->where('academic_cycle_id', $cycleId))->latest('id')->limit(500)->get();
            foreach ($attempts as $attempt) {
                fputcsv($handle, [
                    $this->sanitizeCsvField($attempt->reference_code),
                    $this->sanitizeCsvField($attempt->status),
                    $this->sanitizeCsvField((string) $attempt->preferred_program_code),
                    $this->sanitizeCsvField((string) $attempt->strand),
                    $this->sanitizeCsvField((string) $attempt->gwa),
                    $attempt->created_at?->toIso8601String() ?? '',
                ]);
            }
            fclose($handle);
        }, $this->filename($type, 'csv'), ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function authorizeExport(User $user, string $type): void
    {
        $surface = DocumentSurfaceRegistry::resolveByReportType($type)
            ?? DocumentSurfaceRegistry::resolve(str_replace('-', '_', $type));

        if ($surface !== null) {
            abort_unless(
                DocumentSurfaceRegistry::canViewSurface($this->authorizer, $user, $surface->surfaceKey()),
                403,
                'You do not have capability to export this report.'
            );

            return;
        }

        if (in_array($type, ['course-quota-qualifiers', 'course_quota_qualifiers'], true)) {
            abort_unless(DocumentSurfaceRegistry::canViewSurface($this->authorizer, $user, 'course_quota_qualifiers'), 403, 'You do not have capability to export this report.');

            return;
        }
        if ($type === 'passing-report') {
            $authorized = $this->authorizer->allows($user, CapabilityCatalog::GUIDANCE_REPORTS_VIEW)
                || $this->isTestAdministrator($user);
            abort_unless($authorized, 403, 'You do not have capability to export this report.');

            return;
        }

        $capability = match ($type) {
            'applications-census', 'program-demand', 'geographic-origin' => CapabilityCatalog::REGISTRAR_REPORTS_VIEW,
            'exam-attendance', 'room-utilization', 'score-distribution' => CapabilityCatalog::GUIDANCE_REPORTS_VIEW,
            default => abort(404),
        };
        abort_unless($this->authorizer->allows($user, $capability), 403, 'You do not have capability to export this report.');
    }

    private function authorizeTemplateManagement(User $user, string $surface = 'passing_report'): void
    {
        abort_unless(DocumentSurfaceRegistry::canAuthorSurface($this->authorizer, $user, $surface), 403, 'You do not have capability to manage report templates.');
    }

    private function isTestAdministrator(User $user): bool
    {
        foreach (CapabilityCatalog::legacyTestAdministratorCapabilities() as $capability) {
            if (! $this->authorizer->allows($user, $capability)) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string, mixed> $data */
    private function passingReportDocument(array $data, DocxTemplateFiller $filler, ?ReportTemplate $selected = null, bool $useSelectedTemplate = false): string
    {
        $template = $useSelectedTemplate ? $selected : ReportTemplate::query()->where('surface_key', 'passing_report')->where('is_active', true)->latest('id')->first();
        if ($template !== null && $template->document_path !== null) {
            $path = Storage::disk('local')->path($template->document_path);
            abort_unless(is_file($path), 500, 'Active report template document is unavailable.');

            return $filler->fill($path, $data, 'passers');
        }
        $word = new PhpWord;
        $section = $word->addSection();
        $section->addTitle('Passing Candidates Census', 1);
        $section->addText('Academic cycle: '.($data['academic_cycle'] ?? ''));
        $section->addText('Total examinees: '.($data['total_examinees'] ?? 0));
        $section->addText('Total passed: '.($data['total_passed'] ?? 0));
        $section->addText('Passing rate: '.($data['passing_rate'] ?? '0%'));
        $table = $section->addTable(['borderSize' => 6, 'borderColor' => '999999', 'cellMargin' => 80]);
        $table->addRow(null, ['tblHeader' => true, 'cantSplit' => true]);
        foreach (['Row #', 'Reference Code', 'Applicant Name', 'Sex', 'Strand', 'Program', 'Composite Score', 'Stanine', 'Rating', 'Remarks'] as $heading) {
            $table->addCell()->addText($heading, ['bold' => true]);
        }
        foreach ($data['passers'] as $row) {
            $table->addRow(null, ['cantSplit' => true]);
            foreach (['row_no', 'reference_code', 'applicant_name', 'sex', 'strand', 'program_code', 'composite_score', 'stanine', 'rating', 'remarks'] as $key) {
                $table->addCell()->addText((string) ($row[$key] ?? ''));
            }
        }
        $path = tempnam(sys_get_temp_dir(), 'passing-report-');
        if ($path === false) {
            throw new \RuntimeException('Unable to allocate report document.');
        }
        $docxPath = $path.'.docx';
        @unlink($path);
        $word->save($docxPath, 'Word2007');

        return $docxPath;
    }

    private function downloadTemporaryFile(string $path, string $filename, string $contentType): StreamedResponse
    {
        return response()->streamDownload(static function () use ($path): void {
            try {
                readfile($path);
            } finally {
                @unlink($path);
            }
        }, $filename, ['Content-Type' => $contentType]);
    }

    private function filename(string $type, string $extension): string
    {
        return $type.'-'.now()->format('Y-m-d').'.'.$extension;
    }

    private function sanitizeCsvField(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (in_array($value[0], ['=', '+', '-', '@'], true)) {
            return "'{$value}";
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function genericSurfaceDocument(DocumentSurfaceInterface $surface, array $data): string
    {
        $word = new PhpWord;
        $section = $word->addSection([
            'paperSize' => 'A4',
            'orientation' => 'portrait',
        ]);
        $section->addTitle($surface->title(), 1);
        $section->addText((string) ($data['academic_cycle'] ?? $data['academic_cycle_name'] ?? ''));
        $section->addText('Generated on: '.((string) ($data['report_date'] ?? $data['generated_at'] ?? now()->toDateString())));
        $section->addText('');

        $repeatKey = $surface->repeatingRowKey();
        $rows = $repeatKey !== null && isset($data[$repeatKey]) && is_array($data[$repeatKey])
            ? $data[$repeatKey]
            : [];

        if (count($rows) > 0) {
            $table = $section->addTable(['borderSize' => 6, 'borderColor' => '999999']);
            $firstRow = $rows[0];
            $table->addRow();
            foreach (array_keys($firstRow) as $colKey) {
                if (str_starts_with((string) $colKey, '_')) {
                    continue;
                }
                $table->addCell()->addText(ucwords(str_replace('_', ' ', (string) $colKey)), ['bold' => true]);
            }
            foreach ($rows as $row) {
                $table->addRow(null, ['cantSplit' => true]);
                foreach ($row as $colKey => $cellVal) {
                    if (str_starts_with((string) $colKey, '_')) {
                        continue;
                    }
                    $table->addCell()->addText((string) $cellVal);
                }
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'report-');
        $docxPath = $path.'.docx';
        @unlink($path);
        $word->save($docxPath, 'Word2007');

        return $docxPath;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function genericSurfaceCsv(DocumentSurfaceInterface $surface, array $data): StreamedResponse
    {
        $filename = $surface->surfaceKey().'-'.now()->format('Y-m-d').'.csv';
        $repeatKey = $surface->repeatingRowKey();
        $rows = $repeatKey !== null && isset($data[$repeatKey]) && is_array($data[$repeatKey])
            ? $data[$repeatKey]
            : [];

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }
            if (count($rows) > 0) {
                $firstRow = $rows[0];
                $headers = array_filter(array_keys($firstRow), fn ($k) => ! str_starts_with((string) $k, '_'));
                fputcsv($handle, array_map(fn ($h) => ucwords(str_replace('_', ' ', (string) $h)), $headers));
                foreach ($rows as $row) {
                    $cells = [];
                    foreach ($headers as $col) {
                        $cells[] = $this->sanitizeCsvField((string) ($row[$col] ?? ''));
                    }
                    fputcsv($handle, $cells);
                }
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
