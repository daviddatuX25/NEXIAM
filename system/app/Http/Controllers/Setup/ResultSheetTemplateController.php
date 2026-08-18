<?php

namespace App\Http\Controllers\Setup;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Setup\Concerns\HasSetupCapabilities;
use App\Http\Requests\ResultSheet\AnalyzeResultSheetTemplateRequest;
use App\Http\Requests\ResultSheet\PreviewResultSheetTemplateRequest;
use App\Http\Requests\ResultSheet\StoreResultSheetTemplateRequest;
use App\Http\Requests\ResultSheet\UpdateResultSheetTemplateRequest;
use App\Models\ResultSheet\ResultSheetTemplate;
use App\ResultSheet\Actions\ActivateResultSheetTemplate;
use App\ResultSheet\Actions\DeactivateResultSheetTemplate;
use App\ResultSheet\Actions\SaveResultSheetTemplate;
use App\ResultSheet\Data\TemplateFieldAliasCatalog;
use App\ResultSheet\Rendering\ResultSheetCssService;
use App\ResultSheet\Support\DocxTextExtractor;
use App\ResultSheet\Support\ResultSheetPlaceholderService;
use App\ResultSheet\Support\ResultSheetTemplateAnalyzer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Result-sheet template authority (legacy Admin › Result templates). Gates on
 * `guidance.assessment.catalog` — the Test-Administrator catalog capability
 * reserved for aptitude areas and result templates.
 */
class ResultSheetTemplateController extends Controller
{
    use HasSetupCapabilities;

    public function index(Request $request, CapabilityAuthorizer $authorizer): Response
    {
        $this->requireAssessment($authorizer, $request);

        return Inertia::render('Setup/ResultSheetTemplates/Index', [
            'page_state' => 'ready',
            'templates' => ResultSheetTemplate::query()
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get(['id', 'name', 'mode', 'paper_size', 'orientation', 'logical_unit', 'watermark_text', 'is_active', 'document_extension', 'updated_at']),
            'capabilities' => $this->capabilities($authorizer, $request),
        ]);
    }

    public function create(Request $request, CapabilityAuthorizer $authorizer): Response
    {
        $this->requireAssessment($authorizer, $request);

        return Inertia::render('Setup/ResultSheetTemplates/Create', [
            'page_state' => 'ready',
            'placeholderGroups' => $this->placeholderGroups(),
            'domainPlaceholders' => $this->domainPlaceholders(),
            'capabilities' => $this->capabilities($authorizer, $request),
        ]);
    }

    public function store(
        StoreResultSheetTemplateRequest $request,
        CapabilityAuthorizer $authorizer,
        SaveResultSheetTemplate $save,
    ): RedirectResponse {
        $this->requireAssessment($authorizer, $request);

        $save->create($request->validated(), $request->user());

        return redirect()->route('setup.result-templates.index')->with('status', 'Result sheet template created.');
    }

    public function edit(
        Request $request,
        CapabilityAuthorizer $authorizer,
        ResultSheetTemplate $resultTemplate,
    ): Response {
        $this->requireAssessment($authorizer, $request);

        return Inertia::render('Setup/ResultSheetTemplates/Edit', [
            'page_state' => 'ready',
            'template' => $resultTemplate,
            'placeholderGroups' => $this->placeholderGroups(),
            'domainPlaceholders' => $this->domainPlaceholders(),
            'capabilities' => $this->capabilities($authorizer, $request),
        ]);
    }

    public function update(
        UpdateResultSheetTemplateRequest $request,
        CapabilityAuthorizer $authorizer,
        ResultSheetTemplate $resultTemplate,
        SaveResultSheetTemplate $save,
    ): RedirectResponse {
        $this->requireAssessment($authorizer, $request);

        $save->update($resultTemplate, $request->validated(), $request->user());

        return redirect()->route('setup.result-templates.index')->with('status', 'Result sheet template updated.');
    }

    public function destroy(
        Request $request,
        CapabilityAuthorizer $authorizer,
        ResultSheetTemplate $resultTemplate,
    ): RedirectResponse {
        $this->requireAssessment($authorizer, $request);

        if ($resultTemplate->is_active) {
            $resultTemplate->update(['is_active' => false]);
        }

        if ($resultTemplate->document_path !== null) {
            Storage::disk('local')->delete($resultTemplate->document_path);
        }

        $resultTemplate->delete();

        return back()->with('status', 'Template deleted.');
    }

    public function activate(
        Request $request,
        CapabilityAuthorizer $authorizer,
        ResultSheetTemplate $resultTemplate,
        ActivateResultSheetTemplate $activate,
    ): RedirectResponse {
        $this->requireAssessment($authorizer, $request);

        $activate->handle($resultTemplate, $request->user());

        return back()->with('status', 'Template activated.');
    }

    public function deactivate(
        Request $request,
        CapabilityAuthorizer $authorizer,
        ResultSheetTemplate $resultTemplate,
        DeactivateResultSheetTemplate $deactivate,
    ): RedirectResponse {
        $this->requireAssessment($authorizer, $request);

        $deactivate->handle($resultTemplate, $request->user());

        return back()->with('status', 'Template deactivated.');
    }

    public function validateDocument(
        AnalyzeResultSheetTemplateRequest $request,
        CapabilityAuthorizer $authorizer,
        ResultSheetTemplateAnalyzer $analyzer,
    ): JsonResponse {
        $this->requireAssessment($authorizer, $request);

        $source = [];

        if ($request->hasFile('document')) {
            $path = (string) $request->file('document')->getRealPath();
            $source['document_path'] = $path;
        } elseif ($request->filled('content')) {
            $source['content'] = (string) $request->string('content');
        }

        $result = $analyzer->analyze($source);

        return response()->json($result);
    }

    public function preview(
        PreviewResultSheetTemplateRequest $request,
        CapabilityAuthorizer $authorizer,
        ResultSheetPlaceholderService $placeholders,
        ResultSheetCssService $css,
        DocxTextExtractor $extractor,
    ): JsonResponse {
        $this->requireAssessment($authorizer, $request);

        $mode = $request->string('mode')->toString();
        $paperSize = $request->string('paper_size', 'a4')->toString();
        $orientation = $request->string('orientation', 'portrait')->toString();
        $replacements = $placeholders->buildReplacements([], useSampleData: true);

        if ($mode === ResultSheetTemplate::MODE_DOCX && $request->hasFile('document')) {
            $file = $request->file('document');
            $temp = (string) $file->getRealPath();
            $extension = strtolower((string) $file->getClientOriginalExtension());

            $text = $extension === 'odt'
                ? $this->odtPreviewText($temp)
                : $extractor->text($temp);

            $html = $placeholders->renderRaw('<pre class="docx-preview">'.e($text).'</pre>', $replacements);

            return response()->json(['html' => $css->wrapForPdf($html, $paperSize, $orientation)]);
        }

        $content = (string) $request->string('content');
        $html = $placeholders->renderRaw($content, $replacements);

        return response()->json(['html' => $css->wrapForPdf($html, $paperSize, $orientation)]);
    }

    private function odtPreviewText(string $path): string
    {
        $zip = new \ZipArchive;

        if ($zip->open($path) !== true) {
            return '';
        }

        try {
            $content = $zip->getFromName('content.xml') ?? '';
        } finally {
            $zip->close();
        }

        return html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * @return array<string, mixed>
     */
    private function placeholderGroups(): array
    {
        $descriptions = TemplateFieldAliasCatalog::descriptions();
        $static = TemplateFieldAliasCatalog::staticKeys();
        $aliases = array_keys(TemplateFieldAliasCatalog::domainAliases());

        return [
            'applicant' => array_values(array_map(
                fn (string $key): array => ['placeholder' => $key, 'description' => $descriptions[$key] ?? ''],
                array_intersect($static, [
                    'applicant_name', 'applicant_reference', 'family_name', 'first_name',
                    'middle_name', 'suffix', 'sex', 'gwa', 'course_applied',
                    'course_applied_code', 'strand', 'strand_or_prev_course', 'applicant_type',
                ]),
            )),
            'exam' => array_values(array_map(
                fn (string $key): array => ['placeholder' => $key, 'description' => $descriptions[$key] ?? ''],
                array_intersect($static, [
                    'exam_date', 'exam_time', 'exam_date_start', 'exam_date_end',
                    'exam_time_start', 'exam_time_end', 'room_name',
                ]),
            )),
            'scores' => array_values(array_map(
                fn (string $key): array => ['placeholder' => $key, 'description' => $descriptions[$key] ?? ''],
                array_intersect($static, ['scores_rows', 'overall_pct']),
            )),
            'recommendation' => array_values(array_map(
                fn (string $key): array => ['placeholder' => $key, 'description' => $descriptions[$key] ?? ''],
                array_intersect($static, [
                    'recommended_course', 'recommended_course_code',
                    'counselor_comments', 'counselor_name',
                ]),
            )),
            'institution' => array_values(array_map(
                fn (string $key): array => ['placeholder' => $key, 'description' => $descriptions[$key] ?? ''],
                array_intersect($static, [
                    'institution_name', 'institution_campus', 'institution_address',
                    'institution_contact_number', 'institution_email', 'institution_website',
                    'institution_exam_name', 'institution_exam_acronym',
                    'examination_name', 'examination_acronym',
                ]),
            )),
            'personnel' => array_values(array_map(
                fn (string $key): array => ['placeholder' => $key, 'description' => $descriptions[$key] ?? ''],
                array_filter($static, fn (string $key): bool => str_contains($key, '_name') || str_contains($key, '_title') || str_contains($key, '_credentials')),
            )),
            'aliases' => array_values(array_map(
                fn (string $alias): array => ['placeholder' => $alias, 'description' => 'Legacy ICAT alias (resolved to the matching aptitude area)'],
                $aliases,
            )),
            'course_checks' => ['placeholder' => '{PROGRAM_CODE}_check', 'description' => 'Checkmark for the recommended program'],
            'dual' => ['placeholder' => '*_2', 'description' => 'Second applicant slot for crosswise templates'],
        ];
    }

    /**
     * @return list<array{slug: string, example: string}>
     */
    private function domainPlaceholders(): array
    {
        $groups = [];

        foreach (\App\Models\Foundation\AptitudeArea::query()->where('is_active', true)->orderBy('display_order')->orderBy('id')->get(['name']) as $area) {
            $slug = str_replace('-', '_', \Illuminate\Support\Str::slug($area->name, '_'));
            $groups[] = ['slug' => $slug, 'example' => '{{'.$slug.'}}'];
        }

        return $groups;
    }
}
