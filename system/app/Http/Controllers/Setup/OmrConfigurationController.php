<?php

namespace App\Http\Controllers\Setup;

use App\Assessment\Actions\Omr\ActivateOmrConfiguration;
use App\Assessment\Actions\Omr\CopyBookletVersionToDraft;
use App\Assessment\Actions\Omr\RegisterNativeBundle;
use App\Assessment\Actions\Omr\RetireOmrConfiguration;
use App\Assessment\Actions\Omr\SaveAuthoredBookletVersion;
use App\Assessment\Actions\Omr\SaveAuthoredTemplateDraft;
use App\Assessment\Actions\Omr\SolveOmrTemplateLayout;
use App\Assessment\Actions\Omr\TestCaptureOmrTemplate;
use App\Assessment\Omr\Canonical\OmrCanonicalSheetPageResolver;
use App\Assessment\Omr\Layout\OmrTemplateLayoutException;
use App\Assessment\Omr\NativeBundle;
use App\Assessment\Omr\OmrCapturePolicy;
use App\Assessment\Omr\OmrRegistrationMode;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Setup\Concerns\HasSetupCapabilities;
use App\Http\Requests\Setup\StoreOmrBundleRequest;
use App\Models\Assessment\OmrAnswerSheetTemplate;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrBookletSet;
use App\Models\Assessment\OmrQuestionBooklet;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\Assessment\OmrTemplateBookletPair;
use App\Models\Foundation\AptitudeArea;
use App\Models\Printing\PrintJob;
use App\Printing\RenderBundlePdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

use function is_array;
use function json_decode;

/**
 * OMR Configuration surfaces (System Settings): answer-sheet templates,
 * question booklets, and template–booklet pairs. Guidance-assessment gated.
 */
class OmrConfigurationController extends Controller
{
    use HasSetupCapabilities;

    public function index(Request $request, CapabilityAuthorizer $authorizer): Response
    {
        $this->requireAssessment($authorizer, $request);

        $templates = OmrAnswerSheetTemplate::query()
            ->with(['versions' => fn ($query) => $query->orderByDesc('version')])
            ->orderBy('code')
            ->get();

        $booklets = OmrQuestionBooklet::query()
            ->with(['versions' => fn ($query) => $query->orderByDesc('version')])
            ->orderBy('code')
            ->get();

        $pairs = OmrTemplateBookletPair::query()
            ->with(['templateVersion.template', 'bookletVersion.booklet'])
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return Inertia::render('Setup/OmrConfiguration/Index', [
            'page_state' => 'ready',
            'templates' => $templates->map(fn (OmrAnswerSheetTemplate $template): array => [
                'id' => $template->id,
                'code' => $template->code,
                'name' => $template->name,
                'is_synthetic' => (bool) $template->is_synthetic,
                'versions' => $template->versions->map(fn ($version): array => [
                    'id' => $version->id,
                    'version' => (int) $version->version,
                    'status' => $version->status,
                    'page_width' => (int) $version->page_width,
                    'page_height' => (int) $version->page_height,
                    'native_bundle_hash' => $version->native_bundle_hash,
                    'engine_name' => $version->engine_name,
                    'engine_commit' => $version->engine_commit,
                    'patch_identity' => $version->patch_identity,
                    'active_pair_ids' => $version->pairs()
                        ->where('status', OmrTemplateBookletPair::STATUS_ACTIVE)
                        ->pluck('id')->map(fn ($id) => (int) $id)->all(),
                ])->values()->all(),
            ])->values()->all(),
            'booklets' => $booklets->map(fn (OmrQuestionBooklet $booklet): array => [
                'id' => $booklet->id,
                'code' => $booklet->code,
                'name' => $booklet->name,
                'versions' => $booklet->versions->map(fn ($version): array => [
                    'id' => $version->id,
                    'version' => (int) $version->version,
                    'status' => $version->status,
                    'answer_key_hash' => $version->answer_key_hash,
                    'questions_count' => (int) $version->questions()->count(),
                    'active_pair_ids' => $version->pairs()
                        ->where('status', OmrTemplateBookletPair::STATUS_ACTIVE)
                        ->pluck('id')->map(fn ($id) => (int) $id)->all(),
                ])->values()->all(),
            ])->values()->all(),
            'pairs' => $pairs->map(fn (OmrTemplateBookletPair $pair): array => [
                'id' => $pair->id,
                'status' => $pair->status,
                'template_version_id' => (int) $pair->template_version_id,
                'template_name' => $pair->templateVersion?->template?->name ?? 'Template',
                'template_code' => $pair->templateVersion?->template?->code ?? '—',
                'template_version' => (int) ($pair->templateVersion?->version ?? 1),
                'page_width' => (int) ($pair->templateVersion?->page_width ?? 2060),
                'page_height' => (int) ($pair->templateVersion?->page_height ?? 3144),
                'booklet_version_id' => (int) $pair->booklet_version_id,
                'booklet_id' => (int) ($pair->bookletVersion?->booklet_id ?? 0),
                'booklet_name' => $pair->bookletVersion?->booklet?->name ?? 'Booklet',
                'booklet_code' => $pair->bookletVersion?->booklet?->code ?? '—',
                'booklet_version' => (int) ($pair->bookletVersion?->version ?? 1),
                'label' => sprintf(
                    '%s v%s · %s v%s',
                    $pair->templateVersion?->template?->name ?? 'Template',
                    $pair->templateVersion?->version ?? '?',
                    $pair->bookletVersion?->booklet?->name ?? 'Booklet',
                    $pair->bookletVersion?->version ?? '?',
                ),
            ])->values()->all(),
            'readiness' => [
                'active_pair' => OmrTemplateBookletPair::query()
                    ->where('status', OmrTemplateBookletPair::STATUS_ACTIVE)
                    ->exists(),
                'draft_templates' => OmrAnswerSheetTemplateVersion::query()
                    ->where('status', OmrAnswerSheetTemplateVersion::STATUS_DRAFT)
                    ->exists(),
                'draft_booklets' => OmrQuestionBookletVersion::query()
                    ->where('status', OmrQuestionBookletVersion::STATUS_DRAFT)
                    ->exists(),
                'paper' => (string) config('print.paper', 'long'),
            ],
            'print_jobs' => PrintJob::query()
                ->orderByDesc('id')
                ->limit(10)
                ->get(['id', 'artifact_type', 'status', 'progress_done', 'progress_total', 'created_at'])
                ->map(fn ($job): array => [
                    'id' => $job->id,
                    'artifact_type' => $job->artifact_type,
                    'status' => $job->status,
                    'progress_done' => (int) $job->progress_done,
                    'progress_total' => (int) $job->progress_total,
                    'created_at' => $job->created_at?->toIso8601String(),
                ])->values()->all(),
            'capabilities' => $this->capabilities($authorizer, $request),
        ]);
    }

    public function intake(Request $request, CapabilityAuthorizer $authorizer): Response
    {
        $this->requireAssessment($authorizer, $request);

        return Inertia::render('Setup/OmrConfiguration/Intake', [
            'page_state' => 'ready',
            'capabilities' => $this->capabilities($authorizer, $request),
        ]);
    }

    public function storeBundle(
        StoreOmrBundleRequest $request,
        RegisterNativeBundle $action,
    ): RedirectResponse {
        $files = [];
        foreach (['template', 'config', 'manifest'] as $name) {
            $files[$name.'.json'] = (string) $request->file($name)->get();
        }
        foreach ((array) $request->file('references', []) as $file) {
            $files[$file->getClientOriginalName()] = (string) $file->get();
        }

        try {
            $version = $action->handle(
                (string) $request->string('template_code'),
                $files,
                $request->user(),
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['omr_bundle' => $exception->getMessage()]);
        }

        return redirect()
            ->route('setup.exam-materials.answer-sheets.index')
            ->with('status', sprintf('%s v%d registered as draft.', $version->template->code, $version->version));
    }

    public function showTemplate(Request $request, CapabilityAuthorizer $authorizer, int $template): Response
    {
        $this->requireAssessment($authorizer, $request);

        $templateModel = OmrAnswerSheetTemplate::query()
            ->with(['versions' => fn ($query) => $query->orderByDesc('version')])
            ->findOrFail($template);

        return Inertia::render('Setup/OmrConfiguration/Templates/Show', [
            'page_state' => 'ready',
            'template' => [
                'id' => $templateModel->id,
                'code' => $templateModel->code,
                'name' => $templateModel->name,
                'is_synthetic' => (bool) $templateModel->is_synthetic,
                'versions' => $templateModel->versions->map(fn ($version): array => [
                    'id' => $version->id,
                    'version' => (int) $version->version,
                    'status' => $version->status,
                    'page_width' => (int) $version->page_width,
                    'page_height' => (int) $version->page_height,
                    'page_count' => $version->pageCount(),
                    'native_bundle_hash' => $version->native_bundle_hash,
                    'config_hash' => $version->config_hash,
                    'template_json_hash' => $version->template_json_hash,
                    'review_manifest_hash' => $version->review_manifest_hash,
                    'engine_name' => $version->engine_name,
                    'engine_commit' => $version->engine_commit,
                    'patch_identity' => $version->patch_identity,
                    'capture_policy' => OmrCapturePolicy::forVersion($version),
                    'activated_at' => $version->activated_at?->toIso8601String(),
                    'pair_ids' => $version->pairs->pluck('id')->map(fn ($id) => (int) $id)->all(),
                ])->values()->all(),
            ],
            'pairs' => OmrTemplateBookletPair::query()
                ->with(['templateVersion.template', 'bookletVersion.booklet'])
                ->whereHas('templateVersion', fn ($query) => $query->where('template_id', $templateModel->id))
                ->orderByDesc('id')
                ->get()
                ->map(fn (OmrTemplateBookletPair $pair): array => [
                    'id' => $pair->id,
                    'status' => $pair->status,
                    'template_version_id' => (int) $pair->template_version_id,
                    'booklet_version_id' => (int) $pair->booklet_version_id,
                    'label' => sprintf(
                        '%s v%s · %s v%s',
                        $pair->templateVersion?->template?->name ?? 'Template',
                        $pair->templateVersion?->version ?? '?',
                        $pair->bookletVersion?->booklet?->name ?? 'Booklet',
                        $pair->bookletVersion?->version ?? '?',
                    ),
                ])->values()->all(),
            'capabilities' => $this->capabilities($authorizer, $request),
        ]);
    }

    public function builder(Request $request, CapabilityAuthorizer $authorizer): Response|RedirectResponse
    {
        $this->requireAssessment($authorizer, $request);
        if ((bool) config('omr.canonical.enabled', true)) {
            $targetDraft = OmrTemplateAuthoringDraft::query()
                ->where('source_kind', 'preset')
                ->orWhere('stage', 'mapping_complete')
                ->orderByDesc('id')
                ->first() ?? OmrTemplateAuthoringDraft::query()->first();
            if ($targetDraft !== null) {
                return redirect()->route('setup.exam-materials.answer-sheets.edit', $targetDraft);
            }

            return redirect()->route('setup.exam-materials.answer-sheets.index');
        }
        $draftVersion = null;
        $draftRefusal = null;
        if ($request->integer('version') > 0) {
            $version = OmrAnswerSheetTemplateVersion::query()
                ->with('template')
                ->find($request->integer('version'));
            if ($version === null) {
                $draftRefusal = 'That template version does not exist.';
            } elseif ($version->status !== OmrAnswerSheetTemplateVersion::STATUS_DRAFT) {
                $draftRefusal = 'Only draft versions can be edited. Open the show page to preview activated versions.';
            } else {
                try {
                    $templateJson = NativeBundle::loadForVersion($version)->template;
                    $draftVersion = [
                        'id' => $version->id,
                        'code' => $version->template->code,
                        'name' => $version->template->name,
                        'template_json' => $templateJson,
                        'layout_blueprint_json' => $version->layout_blueprint_json ?? null,
                        'overlay' => $version->print_overlay_json ?? [],
                    ];
                } catch (\Throwable $exception) {
                    $draftRefusal = $exception->getMessage();
                }
            }
        }

        return Inertia::render('Setup/OmrConfiguration/Templates/Builder', [
            'page_state' => 'ready',
            'draft_version' => $draftVersion,
            'draft_refusal' => $draftRefusal,
            'areas' => AptitudeArea::query()->orderBy('code')->get(['id', 'code', 'name'])->map(fn (AptitudeArea $area): array => [
                'id' => (int) $area->id,
                'code' => $area->code,
                'name' => $area->name,
            ])->values()->all(),
            'paper_options' => [
                ['value' => 'long', 'label' => 'Long bond 8.5×13in (216×330mm)', 'dimensions' => [2060, 3144]],
                ['value' => 'legal', 'label' => 'US Legal 8.5×14in (216×356mm)', 'dimensions' => [2060, 3392]],
            ],
            'engine' => [
                'name' => (string) config('omr.omrchecker.engine_name'),
                'commit' => (string) config('omr.omrchecker.engine_commit'),
                'patch_identity' => (string) config('omr.omrchecker.patch_identity'),
            ],
            'capabilities' => $this->capabilities($authorizer, $request),
        ]);
    }

    public function storeAuthoredTemplate(
        Request $request,
        SaveAuthoredTemplateDraft $action,
    ): RedirectResponse {
        $validated = $request->validate([
            'template_code' => ['required', 'string', 'regex:/^[A-Z0-9-]{3,32}$/'],
            'template_name' => ['required', 'string', 'max:120'],
            'template_json' => ['required_without:layout_blueprint_json', 'json'],
            'layout_blueprint_json' => ['nullable', 'json'],
            'overlay' => ['nullable', 'array'],
            'overlay.title' => ['nullable', 'string', 'max:120'],
            'overlay.header_lines' => ['nullable', 'array', 'max:4'],
            'overlay.header_lines.*' => ['string', 'max:120'],
            'overlay.footer_lines' => ['nullable', 'array', 'max:3'],
            'overlay.footer_lines.*' => ['string', 'max:160'],
            'overlay.name_box_label' => ['nullable', 'string', 'max:80'],
            'overlay.per_page' => ['nullable', 'array', 'max:4'],
            'overlay.per_page.*.page_no' => ['integer', 'min:1'],
            'overlay.per_page.*.note' => ['nullable', 'string', 'max:200'],
        ]);

        $templateJson = null;
        if (isset($validated['template_json'])) {
            $templateJson = json_decode($validated['template_json'], true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($templateJson)) {
                return back()->withErrors(['omr_builder' => 'The authored template must be a JSON object.']);
            }
        }

        $layoutBlueprint = null;
        if (isset($validated['layout_blueprint_json'])) {
            $layoutBlueprint = json_decode($validated['layout_blueprint_json'], true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($layoutBlueprint)) {
                return back()->withErrors(['omr_builder' => 'The layout blueprint must be a JSON object.']);
            }
        }

        try {
            $version = $action->handle(
                (string) $validated['template_code'],
                (string) $validated['template_name'],
                is_array($templateJson) ? $templateJson : [],
                $request->user(),
                $this->normalizeOverlay($validated['overlay'] ?? null),
                false,
                $layoutBlueprint,
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['omr_builder' => $exception->getMessage()]);
        }

        return redirect()
            ->route('setup.omr.templates.show', $version->template_id)
            ->with('status', sprintf('%s v%d saved as draft.', $version->template->code, $version->version));
    }

    public function templatesIndex(Request $request, CapabilityAuthorizer $authorizer): Response
    {
        $this->requireAssessment($authorizer, $request);

        return Inertia::render('Setup/OmrConfiguration/Templates/Index', [
            'page_state' => 'ready',
            'templates' => OmrAnswerSheetTemplate::query()
                ->with(['versions' => fn ($query) => $query->orderByDesc('version')])
                ->orderBy('code')
                ->get()
                ->map(fn (OmrAnswerSheetTemplate $template): array => [
                    'id' => $template->id,
                    'code' => $template->code,
                    'name' => $template->name,
                    'is_synthetic' => (bool) $template->is_synthetic,
                    'versions' => $template->versions->map(fn ($version): array => [
                        'id' => $version->id,
                        'version' => (int) $version->version,
                        'status' => $version->status,
                        'page_width' => (int) $version->page_width,
                        'page_height' => (int) $version->page_height,
                        'page_count' => $version->pageCount(),
                    ])->values()->all(),
                ])->values()->all(),
            'capabilities' => $this->capabilities($authorizer, $request),
        ]);
    }

    public function booklets(Request $request, CapabilityAuthorizer $authorizer): Response
    {
        $this->requireAssessment($authorizer, $request);

        return Inertia::render('Setup/OmrConfiguration/Booklets/Index', [
            'page_state' => 'ready',
            'booklets' => OmrQuestionBooklet::query()
                ->with(['versions' => fn ($query) => $query->orderByDesc('version'), 'versions.questions.aptitudeArea'])
                ->orderBy('code')
                ->get()
                ->map(fn (OmrQuestionBooklet $booklet): array => [
                    'id' => $booklet->id,
                    'code' => $booklet->code,
                    'name' => $booklet->name,
                    'versions' => $booklet->versions->map(fn ($version): array => [
                        'id' => $version->id,
                        'version' => (int) $version->version,
                        'status' => $version->status,
                        'answer_key_hash' => $version->answer_key_hash,
                        'questions_count' => (int) $version->questions()->count(),
                    ])->values()->all(),
                ])->values()->all(),
            'areas' => AptitudeArea::query()->orderBy('code')->get(['id', 'code', 'name'])->map(fn (AptitudeArea $area): array => [
                'id' => (int) $area->id,
                'code' => $area->code,
                'name' => $area->name,
            ])->values()->all(),
            'capabilities' => $this->capabilities($authorizer, $request),
        ]);
    }

    public function storeBooklet(
        Request $request,
        SaveAuthoredBookletVersion $action,
    ): RedirectResponse {
        $validated = $request->validate([
            'code' => ['required', 'string', 'regex:/^[A-Z0-9-]{3,32}$/'],
            'name' => ['required', 'string', 'max:120'],
            'questions' => ['array'],
            'questions.*.area_id' => ['integer', 'exists:aptitude_areas,id'],
            'questions.*.position' => ['integer', 'between:1,200'],
            'questions.*.correct_option_code' => ['string', 'in:A,B,C,D'],
            'bulk' => ['nullable', 'string', 'max:20000'],
        ]);

        $questions = array_values((array) ($validated['questions'] ?? []));
        if (trim((string) ($validated['bulk'] ?? '')) !== '') {
            $bulkError = $this->parseBulkKey((string) $validated['bulk'], $questions);
            if ($bulkError !== null) {
                return back()->withErrors(['omr_booklet' => $bulkError])->withInput();
            }
        }

        if ($questions === []) {
            return back()->withErrors(['omr_booklet' => 'A booklet needs at least one question.']);
        }

        try {
            $action->handle(
                (string) $validated['code'],
                (string) $validated['name'],
                $questions,
                $request->user(),
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['omr_booklet' => $exception->getMessage()]);
        }

        return redirect()->route('setup.omr.booklets')->with('status', 'Booklet version saved as draft.');
    }

    public function pairs(Request $request, CapabilityAuthorizer $authorizer): Response
    {
        $this->requireAssessment($authorizer, $request);

        $templates = OmrAnswerSheetTemplateVersion::query()
            ->with('template')
            ->where('status', OmrAnswerSheetTemplateVersion::STATUS_DRAFT)
            ->orderBy('version')
            ->get()
            ->map(fn ($version): array => [
                'id' => $version->id,
                'label' => sprintf('%s v%s', $version->template?->name ?? 'Template', $version->version),
                'code' => $version->template?->code ?? '—',
                'page_count' => $version->pageCount(),
                'page_width' => (int) $version->page_width,
                'page_height' => (int) $version->page_height,
            ])->values()->all();

        $booklets = OmrQuestionBookletVersion::query()
            ->with(['booklet', 'questions'])
            ->where('status', OmrQuestionBookletVersion::STATUS_DRAFT)
            ->orderBy('version')
            ->get()
            ->map(fn ($version): array => [
                'id' => $version->id,
                'label' => sprintf('%s v%s', $version->booklet?->name ?? 'Booklet', $version->version),
                'code' => $version->booklet?->code ?? '—',
                'questions_count' => (int) $version->questions->count(),
            ])->values()->all();

        return Inertia::render('Setup/OmrConfiguration/Pairs/Create', [
            'page_state' => 'ready',
            'draft_templates' => $templates,
            'draft_booklets' => $booklets,
            'capabilities' => $this->capabilities($authorizer, $request),
        ]);
    }

    public function storePair(
        Request $request,
        ActivateOmrConfiguration $action,
    ): RedirectResponse {
        $validated = $request->validate([
            'template_version_id' => ['required', 'integer', 'exists:omr_answer_sheet_template_versions,id'],
            'booklet_version_id' => ['required', 'integer', 'exists:omr_question_booklet_versions,id'],
        ]);

        $templateVersion = OmrAnswerSheetTemplateVersion::query()->findOrFail($validated['template_version_id']);
        $bookletVersion = OmrQuestionBookletVersion::query()->findOrFail($validated['booklet_version_id']);

        try {
            $pair = $action->handle($templateVersion, $bookletVersion, $request->user());
        } catch (RuntimeException $exception) {
            return back()->withErrors(['omr_pair' => $exception->getMessage()]);
        }

        return redirect()
            ->route('setup.exam-materials.answer-sheets.index')
            ->with('status', sprintf('Pair #%d activated and ready for capture.', $pair->id));
    }

    public function retire(
        Request $request,
        RetireOmrConfiguration $action,
    ): RedirectResponse {
        $validated = $request->validate([
            'kind' => ['required', 'string', 'in:template_version,booklet_version,pair'],
            'id' => ['required', 'integer'],
        ]);

        try {
            $action->handle((string) $validated['kind'], (int) $validated['id'], $request->user());
        } catch (RuntimeException $exception) {
            return back()->withErrors(['omr_retire' => $exception->getMessage()]);
        }

        return back()->with('status', 'OMR configuration retired.');
    }

    public function previewVersion(
        Request $request,
        CapabilityAuthorizer $authorizer,
        OmrAnswerSheetTemplateVersion $version,
    ) {
        $this->requireAssessment($authorizer, $request);
        $pageNo = max(1, $request->integer('page', 1));
        $isUploadedOrDocumentBound = $version->registration_mode === OmrRegistrationMode::DocumentBound
            || data_get($version->authoring_manifest, 'source_kind') === 'uploaded_original';

        if ($isUploadedOrDocumentBound) {
            if (! $request->has('page') && ! $request->wantsJson()) {
                $draft = OmrTemplateAuthoringDraft::query()
                    ->where('compiled_version_id', $version->id)
                    ->first();
                if ($draft !== null) {
                    return redirect()->route(
                        'setup.exam-materials.answer-sheets.edit',
                        ['draft' => $draft->id] + $request->only(['test_kit_id', 'test_kit_version_id']),
                    );
                }
            }
            $resolver = app(OmrCanonicalSheetPageResolver::class);
            $asset = $resolver->pageAssetForVersion($version, $pageNo);
            if ($asset !== null) {
                return $resolver->inlineResponse($asset, 'answer-sheet-'.$version->template->code.'-v'.$version->version.'-p'.$pageNo);
            }
        }

        try {
            $templateJson = NativeBundle::loadForVersion($version)->template;
            $bytes = app(RenderBundlePdf::class)->renderTemplate($templateJson, $version->print_overlay_json);
        } catch (\Throwable $exception) {
            $resolver = app(OmrCanonicalSheetPageResolver::class);
            $asset = $resolver->pageAssetForVersion($version, $pageNo);
            if ($asset !== null) {
                return $resolver->inlineResponse($asset, 'answer-sheet-'.$version->template->code.'-v'.$version->version.'-p'.$pageNo);
            }
            abort(422, $exception->getMessage());
        }

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="answer-sheet-'.$version->template->code.'-v'.$version->version.'.pdf"',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    public function previewDraft(Request $request, CapabilityAuthorizer $authorizer)
    {
        $this->requireAssessment($authorizer, $request);

        if ($request->filled('layout_blueprint_json')) {
            $blueprint = json_decode((string) $request->input('layout_blueprint_json'), true, 512);
            if (! is_array($blueprint)) {
                return response()->json(['error' => 'The layout blueprint must be a JSON object.'], 422);
            }

            try {
                $templateJson = app(SolveOmrTemplateLayout::class)->handle($blueprint)->template;
            } catch (\Throwable $exception) {
                return response()->json(['error' => $exception->getMessage()], 422);
            }
        } else {
            $templateJson = json_decode((string) $request->input('template_json'), true, 512);
            if (! is_array($templateJson)) {
                return response()->json(['error' => 'The authored template must be a JSON object.'], 422);
            }
        }

        $overlay = is_array($request->input('overlay')) ? $request->input('overlay') : null;

        try {
            $bytes = app(RenderBundlePdf::class)->renderTemplate(
                $templateJson,
                $this->normalizeOverlay($overlay),
            );
        } catch (\Throwable $exception) {
            return response()->json(['error' => $exception->getMessage()], 422);
        }

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="answer-sheet-preview.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function layoutPreview(Request $request, CapabilityAuthorizer $authorizer)
    {
        $this->requireAssessment($authorizer, $request);

        $blueprint = json_decode((string) $request->input('layout_blueprint_json'), true, 512);
        if (! is_array($blueprint)) {
            return response()->json(['error' => 'The layout blueprint must be a JSON object.'], 422);
        }

        try {
            $solved = app(SolveOmrTemplateLayout::class)->handle($blueprint);
        } catch (OmrTemplateLayoutException $exception) {
            return response()->json(['diagnostics' => $exception->diagnosticsToArray()], 422);
        } catch (\Throwable $exception) {
            return response()->json(['error' => $exception->getMessage()], 422);
        }

        return response()->json([
            'normalized_blueprint' => $solved->normalizedBlueprint,
            'layout' => [
                'pages' => $solved->pages,
                'page_count' => count($solved->pages),
            ],
            'template_json_hash' => $solved->templateJsonHash,
            'diagnostics' => [],
        ]);
    }

    /**
     * Read-only builder Test capture (tasks 10.5/10.6 backend seam).
     *
     * Authorized setup staff upload a photo of the printed marker-bearing
     * sheet; the worker runs marker registration in diagnostic mode and the
     * response exposes the normalized raster, the engine layout overlay,
     * per-bubble means, marker diagnostics, and shift gates. Nothing here
     * mutates activation, pairs, answer keys, or scores; marker-less versions
     * are rejected with a typed error.
     */
    public function testCapture(
        Request $request,
        CapabilityAuthorizer $authorizer,
        TestCaptureOmrTemplate $action,
        OmrAnswerSheetTemplateVersion $version,
    ): JsonResponse {
        $this->requireAssessment($authorizer, $request);

        $validated = $request->validate([
            'image' => ['required', 'file', 'image', 'mimes:jpeg,png,webp', 'max:25600'],
            'booklet_version_id' => ['nullable', 'required_with:set_code', 'integer'],
            'set_code' => ['nullable', 'required_with:booklet_version_id', 'string', 'in:A,B,C,D'],
        ]);

        $booklet = null;
        $bookletSet = null;
        if (isset($validated['booklet_version_id'], $validated['set_code'])) {
            $booklet = OmrQuestionBookletVersion::query()
                ->with('booklet')
                ->whereKey((int) $validated['booklet_version_id'])
                ->whereNotNull('finalized_at')
                ->firstOrFail();
            if ((int) $booklet->template_version_id !== (int) $version->id) {
                return response()->json(['error' => 'The selected Booklet does not inherit this Answer Sheet.'], 422);
            }
            $bookletSet = $booklet->sets()
                ->finalized()
                ->where('set_code', (string) $validated['set_code'])
                ->firstOrFail();
        }

        /** @var UploadedFile $image */
        $image = $validated['image'];
        try {
            $result = $action->handle($version, (string) $image->get(), (string) $image->getMimeType(), $request->user());
        } catch (RuntimeException $exception) {
            return response()->json(['error' => $exception->getMessage()], 422);
        }

        if (! $result->succeeded()) {
            return response()->json([
                'outcome' => 'failed',
                'code' => $result->code,
                'detail' => $result->detail,
                'rectification' => $result->rectification,
            ], 422);
        }

        $token = (string) Str::uuid();
        $directory = 'omr-diagnostics/'.$token;
        $disk = Storage::disk('local');
        $disk->put($directory.'/aligned.png', (string) $result->alignedBytes);
        $disk->put($directory.'/layout-overlay.png', (string) $result->overlayBytes);

        return response()->json([
            'outcome' => 'succeeded',
            'aligned_url' => route('setup.omr.templates.test_capture_asset', ['token' => $token, 'file' => 'aligned.png']),
            'overlay_url' => route('setup.omr.templates.test_capture_asset', ['token' => $token, 'file' => 'layout-overlay.png']),
            'aligned_image' => $result->alignedImage,
            'overlay_image' => $result->overlayImage,
            'marker_matches' => $result->markerMatches,
            'field_block_shifts' => $result->fieldBlockShifts,
            'bubble_means' => $result->bubbleMeans,
            'item_evidence' => $result->itemEvidence,
            'test_context' => $booklet === null || $bookletSet === null ? null : [
                'booklet_version_id' => (int) $booklet->id,
                'booklet_name' => (string) ($booklet->booklet?->name ?? 'Booklet'),
                'set_code' => (string) $bookletSet->set_code,
            ],
            'score_projection' => $bookletSet === null
                ? null
                : $this->diagnosticScoreProjection($result->itemEvidence ?? [], $bookletSet),
            'gates' => $result->gates,
            'calibration_status' => 'unqualified',
            'rectification' => $result->rectification,
            'timings' => [
                'preprocess_ms' => $result->preprocessMs,
                'verification_ms' => $result->verificationMs,
            ],
        ]);
    }

    /**
     * @param  array<int, mixed>  $itemEvidence
     * @return array{total: int, correct: int, incorrect: int, unanswered: int}
     */
    private function diagnosticScoreProjection(array $itemEvidence, OmrBookletSet $bookletSet): array
    {
        $itemsByPosition = collect($itemEvidence)->keyBy(
            static fn (mixed $item): int => (int) data_get($item, 'position', data_get($item, 'global_position', 0)),
        );
        $correct = 0;
        $incorrect = 0;
        $unanswered = 0;
        $answerKey = $bookletSet->answerKeyByPosition();

        foreach ($answerKey as $position => $correctOption) {
            $item = $itemsByPosition->get($position);
            $selectedOption = data_get($item, 'machine.selected_option', data_get($item, 'selected_option'));
            if (! is_string($selectedOption) || trim($selectedOption) === '') {
                $observedOptions = data_get($item, 'machine.observed_options', data_get($item, 'observed_options', []));
                $selectedOption = is_array($observedOptions) && count($observedOptions) === 1
                    ? $observedOptions[0]
                    : null;
            }
            if (! is_string($selectedOption) || trim($selectedOption) === '') {
                $unanswered++;
            } elseif (strtoupper(trim($selectedOption)) === strtoupper(trim($correctOption))) {
                $correct++;
            } else {
                $incorrect++;
            }
        }

        return [
            'total' => count($answerKey),
            'correct' => $correct,
            'incorrect' => $incorrect,
            'unanswered' => $unanswered,
        ];
    }

    /** Streams one stored test-capture raster (capability-gated, no-store). */
    public function testCaptureAsset(Request $request, CapabilityAuthorizer $authorizer, string $token, string $file)
    {
        $this->requireAssessment($authorizer, $request);

        if (! in_array($file, ['aligned.png', 'layout-overlay.png'], true)) {
            abort(404);
        }
        $disk = Storage::disk('local');
        $path = 'omr-diagnostics/'.$token.'/'.$file;
        if (! $disk->exists($path)) {
            abort(404);
        }

        return response($disk->get($path), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function storeOverlay(
        Request $request,
        SaveAuthoredTemplateDraft $action,
        CapabilityAuthorizer $authorizer,
    ): RedirectResponse {
        abort_unless($authorizer->allows($request->user(), CapabilityCatalog::GUIDANCE_SCORING_OMR), 403);
        $validated = $request->validate([
            'version_id' => ['required', 'integer', 'exists:omr_answer_sheet_template_versions,id'],
            'overlay' => ['nullable', 'array'],
            'overlay.title' => ['nullable', 'string', 'max:120'],
            'overlay.header_lines' => ['nullable', 'array', 'max:4'],
            'overlay.header_lines.*' => ['string', 'max:120'],
            'overlay.footer_lines' => ['nullable', 'array', 'max:3'],
            'overlay.footer_lines.*' => ['string', 'max:160'],
            'overlay.name_box_label' => ['nullable', 'string', 'max:80'],
            'overlay.per_page' => ['nullable', 'array', 'max:4'],
            'overlay.per_page.*.page_no' => ['integer', 'min:1'],
            'overlay.per_page.*.note' => ['nullable', 'string', 'max:200'],
        ]);

        $overlay = $this->normalizeOverlay($validated['overlay'] ?? null);
        $version = OmrAnswerSheetTemplateVersion::query()->with('template')->findOrFail($validated['version_id']);

        if ($version->status === OmrAnswerSheetTemplateVersion::STATUS_DRAFT) {
            $version->update(['print_overlay_json' => $overlay]);

            return back()->with('status', 'Sheet text saved on the draft.');
        }

        // Active versions are immutable: copy the exact owned bundle into a staged successor draft.
        try {
            $bundle = NativeBundle::loadForVersion($version);
            $draft = $action->handle(
                $version->template->code,
                $version->template->name,
                $bundle->template,
                $request->user(),
                $overlay,
                true,
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['omr_overlay' => $exception->getMessage()]);
        }

        return redirect()
            ->route('setup.omr.templates.show', $draft->template_id)
            ->with('status', sprintf('New answer-sheet version v%d created with your sheet text. Continue editing it here.', $draft->version));
    }

    public function bookletShow(Request $request, CapabilityAuthorizer $authorizer, int $booklet): Response
    {
        $this->requireAssessment($authorizer, $request);

        $model = OmrQuestionBooklet::query()
            ->with(['versions' => fn ($query) => $query->orderByDesc('version'), 'versions.questions.aptitudeArea'])
            ->findOrFail($booklet);

        return Inertia::render('Setup/OmrConfiguration/Booklets/Show', [
            'page_state' => 'ready',
            'booklet' => [
                'id' => $model->id,
                'code' => $model->code,
                'name' => $model->name,
                'versions' => $model->versions->map(fn ($version): array => [
                    'id' => $version->id,
                    'version' => (int) $version->version,
                    'status' => $version->status,
                    'answer_key_hash' => $version->answer_key_hash,
                    'questions' => $version->questions
                        ->sortBy('question_position')
                        ->map(fn ($question): array => [
                            'position' => (int) $question->question_position,
                            'area_id' => (int) $question->aptitude_area_id,
                            'area_code' => $question->aptitudeArea?->code ?? '—',
                            'area_name' => $question->aptitudeArea?->name ?? 'Aptitude area',
                            'correct_option_code' => $question->correct_option_code,
                        ])->values()->all(),
                ])->values()->all(),
            ],
            'pairs' => OmrTemplateBookletPair::query()
                ->with(['templateVersion.template', 'bookletVersion.booklet'])
                ->whereHas('bookletVersion', fn ($query) => $query->where('booklet_id', $model->id))
                ->orderByDesc('id')
                ->get()
                ->map(fn (OmrTemplateBookletPair $pair): array => [
                    'id' => $pair->id,
                    'status' => $pair->status,
                    'template_version_id' => (int) $pair->template_version_id,
                    'label' => sprintf(
                        '%s v%s · %s v%s',
                        $pair->templateVersion?->template?->name ?? 'Template',
                        $pair->templateVersion?->version ?? '?',
                        $pair->bookletVersion?->booklet?->name ?? 'Booklet',
                        $pair->bookletVersion?->version ?? '?',
                    ),
                ])->values()->all(),
            'areas' => AptitudeArea::query()->orderBy('code')->get(['id', 'code', 'name'])->map(fn (AptitudeArea $area): array => [
                'id' => (int) $area->id,
                'code' => $area->code,
                'name' => $area->name,
            ])->values()->all(),
            'capabilities' => $this->capabilities($authorizer, $request),
        ]);
    }

    public function bookletCreate(Request $request, CapabilityAuthorizer $authorizer): Response
    {
        $this->requireAssessment($authorizer, $request);

        $draft = null;
        if ($request->integer('draft') > 0) {
            $version = OmrQuestionBookletVersion::query()
                ->with(['booklet', 'questions.aptitudeArea'])
                ->find($request->integer('draft'));
            if ($version !== null && $version->status === OmrQuestionBookletVersion::STATUS_DRAFT) {
                $draft = [
                    'version_id' => $version->id,
                    'code' => $version->booklet?->code ?? '',
                    'name' => $version->booklet?->name ?? '',
                    'questions' => $version->questions
                        ->sortBy('question_position')
                        ->map(fn ($question): array => [
                            'position' => (int) $question->question_position,
                            'area_id' => (int) $question->aptitude_area_id,
                            'area_code' => $question->aptitudeArea?->code ?? '',
                            'correct_option_code' => $question->correct_option_code,
                        ])->values()->all(),
                ];
            }
        }

        return Inertia::render('Setup/OmrConfiguration/Booklets/Create', [
            'page_state' => 'ready',
            'draft' => $draft,
            'areas' => AptitudeArea::query()->orderBy('code')->get(['id', 'code', 'name'])->map(fn (AptitudeArea $area): array => [
                'id' => (int) $area->id,
                'code' => $area->code,
                'name' => $area->name,
            ])->values()->all(),
            'capabilities' => $this->capabilities($authorizer, $request),
        ]);
    }

    public function copyBooklet(
        Request $request,
        CopyBookletVersionToDraft $action,
    ): RedirectResponse {
        $validated = $request->validate([
            'version_id' => ['required', 'integer', 'exists:omr_question_booklet_versions,id'],
        ]);

        $source = OmrQuestionBookletVersion::query()->findOrFail($validated['version_id']);

        try {
            $draft = $action->handle($source, $request->user());
        } catch (RuntimeException $exception) {
            return back()->withErrors(['omr_booklet' => $exception->getMessage()]);
        }

        return redirect()
            ->route('setup.omr.booklets.create', ['draft' => $draft->id])
            ->with('status', sprintf('Draft v%d copied. Edit it now.', $draft->version));
    }

    /**
     * @param  array<string, mixed>|null  $overlay
     * @return array<string, mixed>|null
     */
    private function normalizeOverlay(?array $overlay): ?array
    {
        if ($overlay === null) {
            return null;
        }

        $normalized = [
            'title' => trim((string) ($overlay['title'] ?? '')),
            'header_lines' => array_values(array_filter(array_map(
                fn ($line) => trim((string) $line),
                (array) ($overlay['header_lines'] ?? []),
            ), fn ($line) => $line !== '')),
            'footer_lines' => array_values(array_filter(array_map(
                fn ($line) => trim((string) $line),
                (array) ($overlay['footer_lines'] ?? []),
            ), fn ($line) => $line !== '')),
            'name_box_label' => trim((string) ($overlay['name_box_label'] ?? '')),
            'per_page' => array_values(array_filter(array_map(
                fn ($page) => [
                    'page_no' => (int) ($page['page_no'] ?? 1),
                    'note' => trim((string) ($page['note'] ?? '')),
                ],
                (array) ($overlay['per_page'] ?? []),
            ), fn ($page) => $page['note'] !== '')),
        ];

        if ($normalized === [
            'title' => '',
            'header_lines' => [],
            'footer_lines' => [],
            'name_box_label' => '',
            'per_page' => [],
        ]) {
            return null;
        }

        return $normalized;
    }

    /**
     * Parses pasted bulk key rows ("position area_code option", whitespace/comma
     * separated, '#' comments) into the questions list. Returns an error
     * message with line numbers, or null on success.
     *
     * @param  array<int, array<string, mixed>>  $questions
     */
    private function parseBulkKey(string $bulk, array &$questions): ?string
    {
        $errors = [];

        foreach (preg_split('/\r\n|\r|\n/', $bulk) as $lineNo => $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            $parts = preg_split('/[\s,]+/', $trimmed);
            if (count($parts) !== 3) {
                $errors[] = sprintf('Line %d needs exactly "position area_code option".', $lineNo + 1);

                continue;
            }

            [$position, $areaCode, $option] = $parts;
            $area = AptitudeArea::query()->where('code', strtoupper((string) $areaCode))->first();
            if ($area === null) {
                $errors[] = sprintf('Line %d: unknown area code "%s".', $lineNo + 1, $areaCode);

                continue;
            }

            $option = strtoupper((string) $option);
            if (! in_array($option, ['A', 'B', 'C', 'D'], true)) {
                $errors[] = sprintf('Line %d: option must be A–D.', $lineNo + 1);

                continue;
            }

            if ((int) $position < 1 || (int) $position > 200) {
                $errors[] = sprintf('Line %d: position must be 1–200.', $lineNo + 1);

                continue;
            }

            $questions[] = [
                'area_id' => (int) $area->id,
                'position' => (int) $position,
                'correct_option_code' => $option,
            ];
        }

        return $errors === [] ? null : implode(' ', $errors);
    }
}
