<?php

namespace App\Reports\Services;

use App\Admission\Support\ApplicantQrCode;
use App\Admission\Support\ApplicantReference;
use App\Assessment\Omr\Canonical\OmrQrCodeRenderer;
use App\DocumentTemplate\DocumentSurfaceRegistry;
use App\DocumentTemplate\Rendering\ComponentRendererRegistry;
use App\DocumentTemplate\Rendering\DocumentBlueprintCompiler;
use App\DocumentTemplate\Rendering\ReportHtmlContextFiller;
use App\DocumentTemplate\Services\DynamicAssetResolutionService;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Reports\ReportTemplate;
use App\Printing\PrintRenderer;
use App\ResultSheet\Rendering\ResultSheetCssService;
use App\ResultSheet\Support\TemplateShortValue;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdmissionSlipPrintService
{
    public function __construct(
        private readonly DynamicAssetResolutionService $dynamicAssets,
        private readonly ReportHtmlContextFiller $htmlFiller,
        private readonly OmrQrCodeRenderer $qrRenderer,
        private readonly ResultSheetCssService $cssService,
        private readonly PrintRenderer $printRenderer,
    ) {}

    public function renderResponse(
        AdmissionAttempt $attempt,
        Request $request,
        ?string $pdfDownloadUrl = null
    ): Response {
        $attempt->loadMissing([
            'identity',
            'academicCycle.institution',
        ]);

        $surface = DocumentSurfaceRegistry::resolve('admission_slip');
        abort_if($surface === null, 404);

        $institutionId = $attempt->academicCycle?->institution_id;
        $template = ReportTemplate::query()
            ->where('surface_key', 'admission_slip')
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('institution_id')->when($institutionId !== null, fn ($query) => $query->orWhere('institution_id', $institutionId)))
            ->when($institutionId !== null, fn ($query) => $query->orderByRaw('CASE WHEN institution_id = ? THEN 0 ELSE 1 END', [$institutionId]))
            ->latest('id')
            ->first();
        $paperSize = (string) ($template?->paper_size ?? 'a4');
        $orientation = (string) ($template?->orientation ?? 'portrait');
        $compiler = new DocumentBlueprintCompiler(
            ComponentRendererRegistry::withDefaults()
        );
        $blueprint = $surface->defaultBlueprint($paperSize, $orientation);
        $defaultPage = $blueprint['page'];
        $marginMm = (float) data_get($template?->builder_json, 'page.margin_mm', $defaultPage['margin_mm']);
        $compiledBlueprint = $compiler->compile($blueprint);

        if ($template === null) {
            $rawHtml = $compiledBlueprint;
        } elseif ($template->source_kind === ReportTemplate::SOURCE_BUILDER
            && is_array($template->builder_json)
            && ! empty($template->builder_json['components'])) {
            $templateBlueprint = $template->builder_json;
            $storedPage = is_array($templateBlueprint['page'] ?? null) ? $templateBlueprint['page'] : [];
            $templateBlueprint['page'] = [
                ...$defaultPage,
                ...$storedPage,
                'paper_size' => $paperSize,
                'orientation' => $orientation,
                'width_mm' => $defaultPage['width_mm'],
                'height_mm' => $defaultPage['height_mm'],
                'margin_mm' => $marginMm,
            ];
            $rawHtml = $compiler->compile($templateBlueprint);
        } elseif ($template->source_kind === ReportTemplate::SOURCE_HTML) {
            $rawHtml = is_string($template->compiled_html) && trim($template->compiled_html) !== ''
                ? $template->compiled_html
                : (string) $template->content;
        } elseif ($template->source_kind === ReportTemplate::SOURCE_BUILDER
            && is_string($template->compiled_html) && trim($template->compiled_html) !== '') {
            $rawHtml = $template->compiled_html;
        } else {
            abort(422, 'The active applicant QR pass uses a source that cannot be rendered as a web or PDF print.');
        }
        abort_if(trim($rawHtml) === '', 422, 'The active applicant QR pass template is empty.');

        $locatorQrToken = ApplicantQrCode::issue($attempt);
        $locatorQrUrl = ApplicantQrCode::entryUrl($attempt);
        $qrPng = $this->dynamicAssets->renderVerificationQr($locatorQrUrl, 400, 4);
        $qrDataUri = 'data:image/png;base64,'.base64_encode($qrPng);
        $context = [
            'institution_name' => (string) ($attempt->academicCycle?->institution?->name ?? config('public-site.institution.name', 'University')),
            'academic_cycle' => (string) ($attempt->academicCycle?->staffLabel() ?? 'Current Academic Cycle'),
            'academic_cycle_name' => (string) ($attempt->academicCycle?->name ?? 'Current Academic Cycle'),
            'applicant_name' => trim(($attempt->identity?->first_name ?? '').' '.($attempt->identity?->last_name ?? '')) ?: (string) $attempt->reference_code,
            'first_name' => (string) ($attempt->identity?->first_name ?? ''),
            'family_name' => (string) ($attempt->identity?->last_name ?? ''),
            'reference_code' => (string) $attempt->reference_code,
            'applicant_reference' => (string) $attempt->reference_code,
            'applicant_no' => (string) $attempt->reference_code,
            'applicant_number' => (string) $attempt->reference_code,
            'examinee_number' => ApplicantReference::paddedDigits($attempt->reference_code, $attempt->academicCycle) ?? (string) $attempt->reference_code,
            'sequence_number' => ApplicantReference::compactNumber($attempt->reference_code, $attempt->academicCycle) ?? (string) $attempt->reference_code,
            ...TemplateShortValue::fields($attempt->last_school_enrolled, $attempt->strand),
            'strand' => (string) ($attempt->strand ?? ''),
            'strand_or_prev_course' => (string) ($attempt->strand ?? ''),
            'program_code' => (string) ($attempt->preferred_program_code ?: $attempt->program_preference_1_code ?: 'N/A'),
            'admission_status' => ucfirst(str_replace('_', ' ', (string) ($attempt->status ?? 'pending'))),
            'applicant_locator_qr' => $qrDataUri,
            'verification_qr' => $qrDataUri,
            'qr_code_svg' => $this->qrRenderer->svg($locatorQrUrl, 240),
            'qr' => $locatorQrToken,
            'verification_code' => $locatorQrToken,
            'report_date' => now()->format('F j, Y'),
            'generated_at' => now()->format('F j, Y'),
            'page_i' => '1',
            'page_all' => '1',
        ];
        $filledHtml = $this->htmlFiller->fill($rawHtml, $context);
        $printDocument = '<style>.applicant-qr-image{display:block;width:100%;height:100%;aspect-ratio:1/1;object-fit:contain}</style><main data-applicant-qr-print="true">'.$filledHtml.'</main>';

        if ($request->query('format') !== 'pdf') {
            $embedded = $request->boolean('embedded');
            $downloadHref = $pdfDownloadUrl ?? $request->fullUrlWithQuery(['format' => 'pdf']);
            $actions = $embedded
                ? ''
                : '<nav class="print-actions" aria-label="Print actions">'
                    .'<button type="button" onclick="window.print()">Print Pass</button>'
                    .'<a href="'.e($downloadHref).'">Download PDF</a>'
                    .'<button type="button" onclick="window.close()">Close</button>'
                    .'</nav>';
            $bodyClass = $embedded ? ' class="embedded-print"' : '';
            $html = '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
                .'<meta name="viewport" content="width=device-width, initial-scale=1">'
                .'<title>Applicant QR Pass</title><style>'
                .'@page{size:'.$paperSize.' '.$orientation.';margin:'.$marginMm.'mm}*{box-sizing:border-box}'
                .'html,body{margin:0;padding:0;background:#eef2f6;color:#142536;font-family:Arial,Helvetica,sans-serif}'
                .'body{padding:76px 16px 24px}.embedded-print{padding:0;background:#fff}'
                .'.print-actions{position:sticky;z-index:10;top:0;display:flex;justify-content:center;gap:10px;padding:14px;background:#173f63;color:#fff;box-shadow:0 2px 10px #14253633}'
                .'.print-actions button,.print-actions a{border:1px solid #ffffff66;border-radius:6px;background:#fff;color:#173f63;padding:10px 14px;font:600 14px Arial,Helvetica,sans-serif;text-decoration:none;cursor:pointer}'
                .'.print-document{width:'.$defaultPage['width_mm'].'mm;max-width:100%;margin:0 auto;background:#fff;box-shadow:0 8px 30px #14253622;padding:12mm;overflow:visible}'
                .'@media print{html,body{background:#fff}.print-actions{display:none!important}body{padding:0}.print-document{width:auto;max-width:none;margin:0;padding:0;box-shadow:none;overflow:visible;zoom:1!important}}'
                .'@media screen and (max-width:480px){body{padding:0 8px 16px}.print-document{width:'.$defaultPage['width_mm'].'mm;max-width:none;padding:12mm;overflow:visible}.print-actions{gap:6px;padding:10px 6px}.print-actions button,.print-actions a{padding:9px 8px;font-size:12px}}'
                .'</style></head><body'.$bodyClass.'>'.$actions
                .'<div class="print-document">'.$printDocument.'</div>'
                .'<script>const sheet=document.querySelector(".print-document");const fitSheet=()=>{sheet.style.zoom=matchMedia("(max-width:480px)").matches?String(Math.max(.1,Math.min(1,(document.documentElement.clientWidth-16)/sheet.offsetWidth))):"1"};fitSheet();addEventListener("resize",fitSheet);</script></body></html>';

            return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
        }

        $wrappedHtml = $this->cssService->wrapForPdf($printDocument, $paperSize, $orientation, $marginMm);

        $pdfBytes = $this->printRenderer->render(
            $wrappedHtml,
            ['format' => $paperSize, 'orientation' => $orientation]
        );

        return response($pdfBytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="NEXIAM-Applicant-QR-Pass-'.$attempt->reference_code.'.pdf"',
        ]);
    }
}
