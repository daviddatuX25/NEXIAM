<?php

namespace App\Http\Controllers\Setup;

use App\Authorization\CapabilityAuthorizer;
use App\DocumentTemplate\Services\DocumentThumbnailService;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Setup\Concerns\HasSetupCapabilities;
use App\Http\Requests\ResultSheet\AnalyzeResultSheetTemplateRequest;
use App\Http\Requests\ResultSheet\PreviewResultSheetTemplateRequest;
use App\Http\Requests\ResultSheet\StoreResultSheetTemplateDraftRequest;
use App\Http\Requests\ResultSheet\StoreResultSheetTemplateRequest;
use App\Http\Requests\ResultSheet\UpdateResultSheetTemplateDraftRequest;
use App\Http\Requests\ResultSheet\UpdateResultSheetTemplateRequest;
use App\Jobs\ResultSheet\RenderResultSheetThumbnailJob;
use App\Models\Foundation\AptitudeArea;
use App\Models\ResultSheet\ResultSheetTemplate;
use App\Models\ResultSheet\ResultSheetTemplateDraft;
use App\ResultSheet\Actions\ActivateResultSheetTemplate;
use App\ResultSheet\Actions\DeactivateResultSheetTemplate;
use App\ResultSheet\Actions\SaveResultSheetTemplate;
use App\ResultSheet\Actions\SaveResultSheetTemplateDraft;
use App\ResultSheet\Builder\ResultSheetBuilderCompiler;
use App\ResultSheet\Builder\ResultSheetBuilderSchema;
use App\ResultSheet\Data\TemplateFieldAliasCatalog;
use App\ResultSheet\Queries\ResultSheetTemplatesListQuery;
use App\ResultSheet\Rendering\ResultSheetPreviewRenderer;
use App\ResultSheet\Support\ResultSheetTemplateAnalyzer;
use App\Support\Http\MediaStreamResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
            'templates' => app(ResultSheetTemplatesListQuery::class)->forHumanIndex(),
            'capabilities' => $this->capabilities($authorizer, $request),
        ]);
    }

    public function create(Request $request, CapabilityAuthorizer $authorizer): Response
    {
        $this->requireAssessment($authorizer, $request);
        $draft = $this->draftFromRequest($request, null);

        return Inertia::render('Setup/ResultSheetTemplates/Create', [
            'page_state' => 'ready',
            'draft' => $draft ? $this->draftPayload($draft) : null,
            'placeholderGroups' => $this->placeholderGroups(),
            'domainPlaceholders' => $this->domainPlaceholders(),
            'sampleContext' => ResultSheetBuilderSchema::sampleContext(),
            'builderCatalog' => ResultSheetBuilderSchema::componentCatalog(),
            'builderCapabilities' => ResultSheetBuilderSchema::componentCapabilities('a4', 'portrait'),
            'builderDefaults' => ResultSheetBuilderSchema::defaultDocument(),
            'builderCompilerVersion' => ResultSheetBuilderSchema::COMPILER_VERSION,
            'capabilities' => $this->capabilities($authorizer, $request),
        ]);
    }

    public function store(
        StoreResultSheetTemplateRequest $request,
        CapabilityAuthorizer $authorizer,
        SaveResultSheetTemplate $save,
    ): RedirectResponse {
        $this->requireAssessment($authorizer, $request);

        $template = $save->create($request->validated(), $request->user());
        $this->discardDraft($request, $request->integer('draft_id'), null);

        try {
            $fingerprint = app(DocumentThumbnailService::class)->fingerprint($template->printChecksum());
            RenderResultSheetThumbnailJob::dispatch($template->id, $template->printChecksum(), $fingerprint)->afterCommit();
        } catch (\Throwable) {
            // Best-effort warm-on-save failure must not abort successful save.
        }

        return redirect()->route('setup.result-templates.index')->with('status', 'Result sheet template created.');
    }

    public function storeDraft(
        StoreResultSheetTemplateDraftRequest $request,
        CapabilityAuthorizer $authorizer,
        SaveResultSheetTemplateDraft $save,
    ): JsonResponse {
        $this->requireAssessment($authorizer, $request);

        $draft = $save->create($request->validated(), $request->user());

        return response()->json(['draft' => $this->draftPayload($draft)], 201);
    }

    public function updateDraft(
        UpdateResultSheetTemplateDraftRequest $request,
        CapabilityAuthorizer $authorizer,
        ResultSheetTemplateDraft $draft,
        SaveResultSheetTemplateDraft $save,
    ): JsonResponse {
        $this->requireAssessment($authorizer, $request);
        $draft = $this->ownedDraft($request, $draft);

        try {
            $draft = $save->update($draft, $request->validated(), $request->user());
        } catch (\RuntimeException $exception) {
            if ($exception->getMessage() === 'draft_conflict') {
                return response()->json([
                    'error' => 'draft_conflict',
                    'message' => 'This draft was modified in another session. Reload to review the latest saved draft.',
                ], 409);
            }

            throw $exception;
        }

        return response()->json(['draft' => $this->draftPayload($draft)]);
    }

    public function destroyDraft(
        Request $request,
        CapabilityAuthorizer $authorizer,
        ResultSheetTemplateDraft $draft,
    ): JsonResponse {
        $this->requireAssessment($authorizer, $request);
        $this->ownedDraft($request, $draft)->delete();

        return response()->json([], 204);
    }

    public function edit(
        Request $request,
        CapabilityAuthorizer $authorizer,
        ResultSheetTemplate $resultTemplate,
        ResultSheetTemplateAnalyzer $analyzer,
    ): Response {
        $this->requireAssessment($authorizer, $request);
        $draft = $this->draftFromRequest($request, $resultTemplate->id);

        return Inertia::render('Setup/ResultSheetTemplates/Edit', [
            'page_state' => 'ready',
            'template' => $resultTemplate,
            'draft' => $draft ? $this->draftPayload($draft) : null,
            'placeholderGroups' => $this->placeholderGroups(),
            'domainPlaceholders' => $this->domainPlaceholders(),
            'sampleContext' => ResultSheetBuilderSchema::sampleContext(),
            'builderCatalog' => ResultSheetBuilderSchema::componentCatalog(),
            'builderCapabilities' => ResultSheetBuilderSchema::componentCapabilities($resultTemplate->paper_size, $resultTemplate->orientation),
            'builderDefaults' => ResultSheetBuilderSchema::defaultDocument($resultTemplate->paper_size, $resultTemplate->orientation),
            'builderCompilerVersion' => ResultSheetBuilderSchema::COMPILER_VERSION,
            'documentAnalysis' => $resultTemplate->mode === ResultSheetTemplate::MODE_DOCX ? $analyzer->analyze($resultTemplate) : null,
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
        try {
            $template = $save->update($resultTemplate, $request->validated(), $request->user());
        } catch (\RuntimeException $exception) {
            if ($exception->getMessage() === 'template_conflict') {
                return back()
                    ->withErrors(['template_conflict' => 'This template was modified in another session. Please reload to review current changes.'])
                    ->with('page_state', 'conflict');
            }

            throw $exception;
        }

        $this->discardDraft($request, $request->integer('draft_id'), $resultTemplate->id);

        try {
            $fingerprint = app(DocumentThumbnailService::class)->fingerprint($template->printChecksum());
            RenderResultSheetThumbnailJob::dispatch($template->id, $template->printChecksum(), $fingerprint)->afterCommit();
        } catch (\Throwable) {
            // Best-effort warm-on-save failure must not abort successful update.
        }

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
        app(DocumentThumbnailService::class)->purge('result_sheet', $resultTemplate->id);

        return back()->with('status', 'Template deleted.');
    }

    public function thumbnail(
        Request $request,
        CapabilityAuthorizer $authorizer,
        ResultSheetTemplate $resultTemplate,
        DocumentThumbnailService $thumbnailService,
        MediaStreamResponder $responder
    ): \Symfony\Component\HttpFoundation\Response {
        $this->requireAssessment($authorizer, $request);

        $fingerprint = $thumbnailService->fingerprint($resultTemplate->printChecksum());
        $requestedVersion = $request->query('v');
        if ($requestedVersion !== null && $requestedVersion !== $fingerprint) {
            abort(404);
        }

        $cached = $thumbnailService->read('result_sheet', $resultTemplate->id, $fingerprint);
        if ($cached !== null) {
            $cacheControl = $requestedVersion === $fingerprint
                ? 'private, max-age=31536000, immutable'
                : 'private, no-cache';

            return $responder->inline($request, $cached, 'image/png', $cacheControl, "result-sheet-template-{$resultTemplate->id}.png");
        }

        try {
            RenderResultSheetThumbnailJob::dispatch(
                $resultTemplate->id,
                $resultTemplate->printChecksum(),
                $fingerprint
            )->afterCommit();
        } catch (\Throwable) {
            // Queue dispatch failure must not fail the request.
        }

        return response('', 404, ['Cache-Control' => 'private, no-store']);
    }

    public function activate(
        Request $request,
        CapabilityAuthorizer $authorizer,
        ResultSheetTemplate $resultTemplate,
        ActivateResultSheetTemplate $activate,
    ): RedirectResponse {
        $this->requireAssessment($authorizer, $request);

        try {
            $activate->handle($resultTemplate, $request->user());
        } catch (\Throwable $e) {
            $message = match ($e->getMessage()) {
                'Template document integrity failed.' => 'template_document_integrity_failed',
                'Template PDF verification failed.' => 'template_pdf_verification_failed',
                default => $e->getMessage(),
            };

            return back()->withErrors(['activation' => $message]);
        }

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
        $sourceChecksum = null;

        if ($request->hasFile('document')) {
            $file = $request->file('document');
            $path = (string) $file->getRealPath();
            $source['document_path'] = $path;
            $sourceChecksum = hash_file('sha256', $path);
        } elseif ($request->filled('content')) {
            $source['content'] = (string) $request->string('content');
        }

        $result = $analyzer->analyze($source);
        if ($sourceChecksum !== null) {
            $result['source_checksum'] = $sourceChecksum;
        }

        return response()->json($result);
    }

    public function preview(
        PreviewResultSheetTemplateRequest $request,
        CapabilityAuthorizer $authorizer,
        ResultSheetPreviewRenderer $previewRenderer,
        ResultSheetBuilderCompiler $builderCompiler,
    ): JsonResponse {
        $this->requireAssessment($authorizer, $request);
        $mode = $request->string('mode')->toString();
        $paperSize = $request->string('paper_size', 'a4')->toString();
        $orientation = $request->string('orientation', 'portrait')->toString();

        if ($mode === ResultSheetTemplate::MODE_DOCX) {
            if ($request->hasFile('document')) {
                $file = $request->file('document');
                $extension = strtolower((string) $file->getClientOriginalExtension());
                $tempPath = 'result-sheet-templates/preview-'.uniqid().'.'.$extension;
                Storage::disk('local')->put($tempPath, (string) $file->get());

                $template = new ResultSheetTemplate([
                    'name' => 'Preview',
                    'mode' => ResultSheetTemplate::MODE_DOCX,
                    'paper_size' => $paperSize,
                    'orientation' => $orientation,
                    'logical_unit' => ResultSheetTemplate::LOGICAL_FULL,
                    'document_path' => $tempPath,
                    'document_extension' => $extension,
                ]);

                try {
                    $result = $previewRenderer->renderPreview($template, [], $request->user(), true);

                    return response()->json([
                        'html' => '<div class="p-6 text-sm text-muted-foreground">PDF preview queued…</div>',
                        'preview_job_id' => $result['job_id'] ?? null,
                        'preview_url' => $result['download_url'] ?? null,
                        'source_checksum' => hash_file('sha256', $file->getRealPath()),
                    ]);
                } catch (\Throwable $exception) {
                    Storage::disk('local')->delete($tempPath);
                    throw $exception;
                }
            }

            if ($request->filled('template_id')) {
                $template = ResultSheetTemplate::query()->findOrFail($request->integer('template_id'));

                if ($template->mode !== ResultSheetTemplate::MODE_DOCX) {
                    return response()->json(['error' => 'template_mode_mismatch'], 422);
                }

                $result = $previewRenderer->renderPreview($template, [], $request->user(), true);

                return response()->json([
                    'html' => '<div class="p-6 text-sm text-muted-foreground">PDF preview queued…</div>',
                    'preview_job_id' => $result['job_id'] ?? null,
                    'preview_url' => $result['download_url'] ?? null,
                ]);
            }

            return response()->json(['error' => 'template_document_required'], 422);
        }

        $builder = null;
        if ($request->exists('builder_json')) {
            try {
                $builder = is_string($request->input('builder_json'))
                    ? json_decode((string) $request->input('builder_json'), true, 512, JSON_THROW_ON_ERROR)
                    : $request->input('builder_json');
            } catch (\JsonException $exception) {
                return response()->json([
                    'error' => 'builder_invalid',
                    'diagnostics' => [['code' => 'builder_json', 'message' => 'Builder JSON is malformed.']],
                ], 422);
            }

            if (! is_array($builder)) {
                return response()->json([
                    'error' => 'builder_invalid',
                    'diagnostics' => [['code' => 'builder_json', 'message' => 'Builder JSON must be an object.']],
                ], 422);
            }
        }

        $content = (string) $request->string('content');
        if (is_array($builder)) {
            $compiled = $builderCompiler->compile($builder);
            if ($compiled['diagnostics'] !== []) {
                return response()->json(['error' => 'builder_invalid', 'diagnostics' => $compiled['diagnostics']], 422);
            }
            $content = $compiled['html'];
        }

        $template = new ResultSheetTemplate([
            'name' => 'Preview HTML',
            'mode' => ResultSheetTemplate::MODE_HTML,
            'source_kind' => is_array($builder) ? ResultSheetTemplate::SOURCE_BUILDER : ResultSheetTemplate::SOURCE_LEGACY_HTML,
            'builder_schema_version' => is_array($builder) ? ($builder['schema_version'] ?? null) : null,
            'builder_json' => is_array($builder) ? $builder : null,
            'compiled_html' => is_array($builder) ? $content : null,
            'paper_size' => $paperSize,
            'orientation' => $orientation,
            'logical_unit' => ResultSheetTemplate::LOGICAL_FULL,
            'content' => $content,
        ]);
        $result = $previewRenderer->renderPreview($template, [], $request->user(), true);
        $b64 = base64_encode($result['pdf_bytes'] ?? '');
        $html = '<!DOCTYPE html><html><body style="margin:0;padding:0;overflow:hidden;"><iframe src="data:application/pdf;base64,'.$b64.'#toolbar=0" style="width:100%;height:100vh;border:none;"></iframe></body></html>';

        return response()->json(['html' => $html, 'pdf_base64' => $b64]);
    }

    private function draftFromRequest(Request $request, ?int $sourceTemplateId): ?ResultSheetTemplateDraft
    {
        if (! $request->filled('draft')) {
            return null;
        }

        $draftId = $request->integer('draft');
        abort_if($draftId < 1, 404);

        $draft = ResultSheetTemplateDraft::query()
            ->whereKey($draftId)
            ->where('owner_user_id', $request->user()->id)
            ->firstOrFail();

        abort_unless(
            (int) ($draft->source_template_id ?? 0) === (int) ($sourceTemplateId ?? 0),
            404,
        );

        return $draft;
    }

    private function ownedDraft(Request $request, ResultSheetTemplateDraft $draft): ResultSheetTemplateDraft
    {
        abort_unless((int) $draft->owner_user_id === (int) $request->user()->id, 404);

        return $draft;
    }

    private function discardDraft(Request $request, ?int $draftId, ?int $sourceTemplateId): void
    {
        if ($draftId === null || $draftId < 1) {
            return;
        }

        $query = ResultSheetTemplateDraft::query()
            ->whereKey($draftId)
            ->where('owner_user_id', $request->user()->id);

        if ($sourceTemplateId === null) {
            $query->whereNull('source_template_id');
        } else {
            $query->where('source_template_id', $sourceTemplateId);
        }

        $query->delete();
    }

    /**
     * @return array<string, mixed>
     */
    private function draftPayload(ResultSheetTemplateDraft $draft): array
    {
        return [
            'id' => $draft->id,
            'stage' => $draft->stage,
            'lock_version' => $draft->lock_version,
            'source_template_id' => $draft->source_template_id,
            'state' => $draft->state ?? [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function placeholderGroups(): array
    {
        $descriptions = TemplateFieldAliasCatalog::descriptions();
        $aliasMap = TemplateFieldAliasCatalog::aliasMapByCanonical();
        $static = TemplateFieldAliasCatalog::staticKeys();
        $aliases = array_keys(TemplateFieldAliasCatalog::domainAliases());

        return [
            'applicant' => array_values(array_map(
                fn (string $key): array => [
                    'placeholder' => $key,
                    'description' => $descriptions[$key] ?? '',
                    'aliases' => $aliasMap[$key] ?? [],
                ],
                array_intersect($static, [
                    'applicant_name', 'applicant_reference', 'family_name', 'first_name',
                    'middle_name', 'suffix', 'sex', 'gwa', 'gwa_original', 'gwa_scale', 'gwa_display', 'course_applied',
                    'course_applied_code', 'last_school_enrolled', 'last_school_short', 'strand', 'strand_or_prev_course', 'strand_or_prev_course_short', 'applicant_type',
                    'applicant_photo_2x2',
                ]),
            )),
            'security' => array_values(array_map(
                fn (string $key): array => [
                    'placeholder' => $key,
                    'description' => $descriptions[$key] ?? '',
                    'aliases' => $aliasMap[$key] ?? [],
                ],
                array_intersect($static, ['verification_qr']),
            )),
            'exam' => array_values(array_map(
                fn (string $key): array => [
                    'placeholder' => $key,
                    'description' => $descriptions[$key] ?? '',
                    'aliases' => $aliasMap[$key] ?? [],
                ],
                array_intersect($static, [
                    'exam_date', 'exam_time', 'exam_date_start', 'exam_date_end',
                    'exam_time_start', 'exam_time_end', 'room_name',
                ]),
            )),
            'scores' => array_values(array_map(
                fn (string $key): array => [
                    'placeholder' => $key,
                    'description' => $descriptions[$key] ?? '',
                    'aliases' => $aliasMap[$key] ?? [],
                ],
                array_intersect($static, ['scores_rows', 'overall_pct']),
            )),
            'recommendation' => array_values(array_map(
                fn (string $key): array => [
                    'placeholder' => $key,
                    'description' => $descriptions[$key] ?? '',
                    'aliases' => $aliasMap[$key] ?? [],
                ],
                array_intersect($static, [
                    'recommended_course', 'recommended_course_code',
                    'counselor_comments', 'counselor_name',
                ]),
            )),
            'institution' => array_values(array_map(
                fn (string $key): array => [
                    'placeholder' => $key,
                    'description' => $descriptions[$key] ?? '',
                    'aliases' => $aliasMap[$key] ?? [],
                ],
                array_intersect($static, [
                    'institution_name', 'institution_campus', 'institution_address',
                    'institution_contact_number', 'institution_email', 'institution_website',
                    'institution_exam_name', 'institution_exam_acronym',
                    'examination_name', 'examination_acronym',
                ]),
            )),
            'personnel' => array_values(array_map(
                fn (string $key): array => [
                    'placeholder' => $key,
                    'description' => $descriptions[$key] ?? '',
                    'aliases' => $aliasMap[$key] ?? [],
                ],
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

        foreach (AptitudeArea::query()->where('is_active', true)->orderBy('id')->get(['name']) as $area) {
            $slug = str_replace('-', '_', Str::slug($area->name, '_'));
            $groups[] = ['slug' => $slug, 'example' => '{{'.$slug.'}}'];
        }

        return $groups;
    }
}
