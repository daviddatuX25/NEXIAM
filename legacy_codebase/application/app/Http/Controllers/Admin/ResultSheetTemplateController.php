<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreResultSheetTemplateRequest;
use App\Http\Requests\UpdateResultSheetTemplateRequest;
use App\Models\AptitudeArea;
use App\Models\RatingScale;
use App\Models\ResultSheetTemplate;
use App\Services\AuditService;
use App\Services\ResultSheetTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Mews\Purifier\Facades\Purifier;

class ResultSheetTemplateController extends Controller
{
    public function __construct(
        private ResultSheetTemplateService $templateService
    ) {}

    public function index(): Response
    {
        $templates = ResultSheetTemplate::query()->orderBy('name')->get([
            'id', 'name', 'mode', 'paper_size', 'orientation', 'logical_unit', 'is_active', 'created_at',
        ]);

        return Inertia::render('Admin/ResultSheetTemplates/Index', [
            'templates' => $templates,
        ]);
    }

    public function create(): Response
    {
        $domains = AptitudeArea::where('is_active', true)->orderBy('display_order')->get(['id', 'name', 'code']);
        $domainPlaceholders = $domains->map(fn ($d) => [
            'slug' => $this->templateService->aptitudeAreaSlug($d->name),
            'name' => $d->name,
            'code' => $d->code,
            'example' => '{{'.$this->templateService->aptitudeAreaSlug($d->name).'}}',
            'exampleRaw' => '{{'.$this->templateService->aptitudeAreaSlug($d->name).'_raw}}',
        ])->values()->all();

        $placeholderGroups = $this->templateService->getPlaceholderGroups();

        return Inertia::render('Admin/ResultSheetTemplates/Create', [
            'placeholderGroups' => $placeholderGroups,
            'placeholdersApplicant1' => array_column($placeholderGroups['applicant1'], 'placeholder'),
            'placeholdersApplicant2' => array_column($placeholderGroups['applicant2'], 'placeholder'),
            'exampleRating' => $this->buildExampleRating(),
            'domainPlaceholders' => $domainPlaceholders,
            'htmlScoresNote' => 'For the scores table in HTML mode, put this inside tbody: <code>&lt;tr class="scores-rows-placeholder"&gt;&lt;td colspan="3"&gt;&lt;/td&gt;&lt;/tr&gt;</code> (Do not use {{scores_rows}}; it is stripped by the sanitizer.)',
            'htmlTemplateRules' => 'Single root div. Placeholders: {{applicant_name}}, {{applicant_reference}}, {{exam_date}}, {{room_name}}, {{overall_pct}}. Half-crosswise: keep content under ~148mm height per sheet (use p-4, text-sm, space-y-3). Full page: ~297mm. No scripts.',
            'docxPlaceholderNote' => 'DOCX supports {{applicant_name}}, {{scores_rows}} (block), and per-domain placeholders like {{spatial_awareness}} and {{spatial_awareness_raw}}. See the list below. Add _2 for applicant 2 in dual layout.',
            'layoutOptions' => [
                'full' => 'Full page',
                'half_a4' => 'Half-crosswise',
            ],
        ]);
    }

    public function store(StoreResultSheetTemplateRequest $request): RedirectResponse
    {
        $mode = $request->validated('mode', 'html');
        $data = [
            'name' => $request->validated('name'),
            'mode' => $mode,
            'paper_size' => $request->validated('paper_size', 'a4'),
            'orientation' => $request->validated('orientation', 'portrait'),
            'logical_unit' => $request->validated('logical_unit', 'full'),
            'is_active' => $request->validated('is_active', true),
            'watermark_text' => $request->validated('watermark_text'),
        ];

        if ($mode === ResultSheetTemplate::MODE_HTML) {
            $data['content'] = Purifier::clean($request->validated('content'), 'result_sheet');
            $data['document_path'] = null;
        }

        if ($data['is_active'] ?? false) {
            ResultSheetTemplate::where('is_active', true)->update(['is_active' => false]);
        }

        if ($mode === ResultSheetTemplate::MODE_HTML) {
            $template = ResultSheetTemplate::create($data);
        } else {
            $data['content'] = '';
            $file = $request->file('document');

            DB::transaction(function () use (&$template, $data, $file) {
                $template = ResultSheetTemplate::create([...$data, 'document_path' => null]);
                $destPath = 'result-sheet-templates/'.$template->id.'.'.$file->getClientOriginalExtension();
                $filePath = $file->getRealPath() ?: $file->getPathname();
                Storage::disk('local')->put($destPath, file_get_contents($filePath));
                $template->update(['document_path' => $destPath]);
            });
        }

        app(AuditService::class)->log('template.result_sheet_created', ResultSheetTemplate::class, $template->id, [], ['name' => $data['name'], 'mode' => $mode]);

        return redirect()->route('admin.release.result-templates.index')->with('success', 'Template created.');
    }

    public function edit(ResultSheetTemplate $result_template): Response
    {
        $domains = AptitudeArea::where('is_active', true)->orderBy('display_order')->get(['id', 'name', 'code']);
        $domainPlaceholders = $domains->map(fn ($d) => [
            'slug' => $this->templateService->aptitudeAreaSlug($d->name),
            'name' => $d->name,
            'code' => $d->code,
            'example' => '{{'.$this->templateService->aptitudeAreaSlug($d->name).'}}',
            'exampleRaw' => '{{'.$this->templateService->aptitudeAreaSlug($d->name).'_raw}}',
        ])->values()->all();

        $placeholderGroups = $this->templateService->getPlaceholderGroups();

        return Inertia::render('Admin/ResultSheetTemplates/Edit', [
            'template' => $result_template,
            'placeholderGroups' => $placeholderGroups,
            'placeholdersApplicant1' => array_column($placeholderGroups['applicant1'], 'placeholder'),
            'placeholdersApplicant2' => array_column($placeholderGroups['applicant2'], 'placeholder'),
            'exampleRating' => $this->buildExampleRating(),
            'domainPlaceholders' => $domainPlaceholders,
            'htmlScoresNote' => 'For the scores table in HTML mode, put this inside tbody: <code>&lt;tr class="scores-rows-placeholder"&gt;&lt;td colspan="3"&gt;&lt;/td&gt;&lt;/tr&gt;</code> (Do not use {{scores_rows}}; it is stripped by the sanitizer.)',
            'htmlTemplateRules' => 'Single root div. Placeholders: {{applicant_name}}, {{applicant_reference}}, {{exam_date}}, {{room_name}}, {{overall_pct}}. Half-crosswise: keep content under ~148mm height per sheet (use p-4, text-sm, space-y-3). Full page: ~297mm. No scripts.',
            'docxPlaceholderNote' => 'DOCX supports {{applicant_name}}, {{scores_rows}} (block), and per-domain placeholders like {{spatial_awareness}} and {{spatial_awareness_raw}}. See the list below. Add _2 for applicant 2 in dual layout.',
            'layoutOptions' => [
                'full' => 'Full page',
                'half_a4' => 'Half-crosswise',
            ],
        ]);
    }

    public function update(UpdateResultSheetTemplateRequest $request, ResultSheetTemplate $result_template): RedirectResponse
    {
        $mode = $request->validated('mode', $result_template->mode);
        $data = array_filter([
            'name' => $request->validated('name'),
            'mode' => $mode,
            'paper_size' => $request->validated('paper_size'),
            'orientation' => $request->validated('orientation'),
            'logical_unit' => $request->validated('logical_unit'),
            'is_active' => $request->validated('is_active'),
            'watermark_text' => $request->validated('watermark_text'),
        ], fn ($v) => $v !== null);

        if ($data['is_active'] ?? false) {
            ResultSheetTemplate::where('is_active', true)
                ->where('id', '!=', $result_template->id)
                ->update(['is_active' => false]);
        }

        if ($mode === ResultSheetTemplate::MODE_HTML) {
            if ($request->has('content')) {
                $data['content'] = Purifier::clean($request->validated('content'), 'result_sheet');
            }
            if ($result_template->document_path) {
                Storage::disk('local')->delete($result_template->document_path);
                $data['document_path'] = null;
            }
        } else {
            $data['content'] = '';
            if ($request->hasFile('document')) {
                if ($result_template->document_path) {
                    Storage::disk('local')->delete($result_template->document_path);
                }
                $file = $request->file('document');
                $destPath = 'result-sheet-templates/'.$result_template->id.'.'.$file->getClientOriginalExtension();
                $filePath = $file->getRealPath() ?: $file->getPathname();
                Storage::disk('local')->put($destPath, file_get_contents($filePath));
                $data['document_path'] = $destPath;
            } elseif (! $result_template->document_path) {
                return redirect()->back()->withErrors(['document' => 'Document file is required for DOCX mode.']);
            }
        }

        $result_template->update($data);

        app(AuditService::class)->log('template.result_sheet_updated', ResultSheetTemplate::class, $result_template->id, [], ['name' => $data['name'] ?? $result_template->name]);

        return redirect()->route('admin.release.result-templates.index')->with('success', 'Template updated.');
    }

    public function destroy(ResultSheetTemplate $result_template): RedirectResponse
    {
        app(AuditService::class)->log('template.result_sheet_deleted', ResultSheetTemplate::class, $result_template->id, [], ['name' => $result_template->name]);

        if ($result_template->document_path) {
            Storage::disk('local')->delete($result_template->document_path);
        }
        $result_template->delete();

        return redirect()->route('admin.release.result-templates.index')->with('success', 'Template deleted.');
    }

    public function activate(ResultSheetTemplate $result_template): RedirectResponse
    {
        DB::transaction(function () use ($result_template) {
            ResultSheetTemplate::where('is_active', true)->update(['is_active' => false]);
            $result_template->update(['is_active' => true]);
        });

        app(AuditService::class)->log('template.result_sheet_activated', ResultSheetTemplate::class, $result_template->id, [], ['name' => $result_template->name]);

        return redirect()->route('admin.release.result-templates.index')->with('success', 'Template activated.');
    }

    public function deactivate(ResultSheetTemplate $result_template): RedirectResponse
    {
        $result_template->update(['is_active' => false]);

        app(AuditService::class)->log('template.result_sheet_deactivated', ResultSheetTemplate::class, $result_template->id, [], ['name' => $result_template->name]);

        return redirect()->route('admin.release.result-templates.index')->with('success', 'Template deactivated.');
    }

    public function preview(Request $request): JsonResponse
    {
        $request->validate([
            'mode' => ['required', Rule::in(['html', 'docx'])],
            'content' => ['required_if:mode,html', 'nullable', 'string'],
            'template_id' => ['nullable', 'exists:result_sheet_templates,id'],
            'document' => ['nullable', 'file', 'max:5120'],
        ]);

        $mode = $request->input('mode');
        if ($mode === 'docx' && ! $request->hasFile('document') && ! $request->input('template_id')) {
            return response()->json(['error' => 'Either document file or template_id is required for DOCX preview.'], 422);
        }
        $paperSize = $request->input('paper_size', 'a4');
        $orientation = $request->input('orientation', 'portrait');
        $logicalUnit = $request->input('logical_unit', 'full');

        try {
            if ($mode === 'html') {
                $content = Purifier::clean($request->input('content', ''), 'result_sheet');
                $renderResult = $this->templateService->renderHtmlContent($content, [], true, $paperSize, $orientation, $logicalUnit);
            } else {
                if ($request->hasFile('document')) {
                    $uploaded = $request->file('document');
                    $path = $uploaded->getRealPath() ?: $uploaded->getPathname();
                    $originalName = $uploaded->getClientOriginalName();
                    $renderResult = $this->templateService->renderDocumentFile($path, [], true, $paperSize, $orientation, $logicalUnit, $originalName);
                } else {
                    $template = ResultSheetTemplate::findOrFail($request->input('template_id'));
                    $renderResult = $this->templateService->render($template, [], true);
                }
            }

            $dims = $renderResult->pageDimensions();

            return response()->json([
                'html' => $renderResult->html,
                'dimensions' => [
                    'width' => $dims['width'].'mm',
                    'height' => $dims['height'].'mm',
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function validateDocument(Request $request): JsonResponse
    {
        $request->validate([
            'document' => ['nullable', 'file', 'mimes:docx,odt', 'max:5120'],
            'template_id' => ['nullable', 'exists:result_sheet_templates,id'],
            'logical_unit' => ['required', Rule::in(['full', 'half_a4'])],
        ]);

        if ($request->hasFile('document')) {
            $uploaded = $request->file('document');
            if (! $uploaded->isValid()) {
                return response()->json(['error' => 'File upload failed: '.$uploaded->getErrorMessage()], 422);
            }
            $fullPath = $uploaded->getRealPath() ?: $uploaded->getPathname();
        } elseif ($request->input('template_id')) {
            $template = ResultSheetTemplate::findOrFail($request->input('template_id'));
            if (! $template->document_path) {
                return response()->json(['error' => 'Template has no document file.'], 422);
            }
            $fullPath = Storage::path($template->document_path);
        } else {
            return response()->json(['error' => 'Either document file or template_id is required.'], 422);
        }

        $isCrosswise = $request->input('logical_unit') === 'half_a4';
        $originalName = $request->hasFile('document') ? $request->file('document')->getClientOriginalName() : null;

        return response()->json(
            $this->templateService->getDocumentValidation($fullPath, $isCrosswise, $originalName)->toArray()
        );
    }

    protected function buildExampleRating(): array
    {
        $ratingScale = RatingScale::default();
        $domains = AptitudeArea::where('is_active', true)->orderBy('display_order')->first();
        $slug = $domains ? $this->templateService->aptitudeAreaSlug($domains->name) : 'spatial_awareness';

        return [
            'placeholder' => '{{'.$slug.'_rating}}',
            'value' => $ratingScale ? $ratingScale->ratingFor(85) : 'Above Average',
        ];
    }
}
