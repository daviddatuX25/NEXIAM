<?php

namespace App\Http\Controllers\Setup;

use App\Authorization\CapabilityAuthorizer;
use App\DocumentTemplate\BaselineInventoryCatalog;
use App\DocumentTemplate\Contracts\DocumentSurfaceInterface;
use App\DocumentTemplate\DocumentFieldPolicy;
use App\DocumentTemplate\DocumentSurfaceRegistry;
use App\DocumentTemplate\MountSlotRegistry;
use App\DocumentTemplate\OfficeDocumentPackCatalog;
use App\DocumentTemplate\Rendering\DocumentBlueprintCompiler;
use App\DocumentTemplate\Rendering\DocxTemplateFiller;
use App\DocumentTemplate\Rendering\LibreOfficeConverter;
use App\DocumentTemplate\Rendering\ReportHtmlContextFiller;
use App\DocumentTemplate\Services\DocumentThumbnailService;
use App\DocumentTemplate\Services\ValidateDocumentExecutionContract;
use App\DocumentTemplate\Support\DocxTextExtractor;
use App\DocumentTemplate\Validation\DocumentTemplateAnalyzer;
use App\Foundation\Actions\RecordAuditEvent;
use App\Http\Controllers\Controller;
use App\Jobs\Reports\RenderReportTemplateThumbnailJob;
use App\Models\Foundation\Institution;
use App\Models\Reports\ReportTemplate;
use App\Models\User;
use App\Printing\PrintRenderer;
use App\ResultSheet\Builder\ResultSheetBuilderSchema;
use App\ResultSheet\Rendering\ResultSheetCssService;
use App\ResultSheet\Support\TemplateShortValue;
use App\Support\Http\MediaStreamResponder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ReportTemplateController extends Controller
{
    public function downloadBaseline(Request $request, string $baseline): SymfonyResponse
    {
        abort_unless($request->user() !== null, 401);

        return BaselineInventoryCatalog::streamDownload($baseline);
    }

    public function downloadSource(
        Request $request,
        CapabilityAuthorizer $authorizer,
        string $surface,
        ReportTemplate $reportTemplate
    ): SymfonyResponse {
        $this->assertTemplateSurface($reportTemplate, $surface);
        $user = $this->authorizeSurface($authorizer, $request->user(), $surface);
        $this->assertTemplateOffice($reportTemplate, $user);

        abort_unless(
            $reportTemplate->mode === 'docx' && $reportTemplate->document_path !== null,
            404,
            'No DOCX document is associated with this template.'
        );

        $fullPath = Storage::disk('local')->path($reportTemplate->document_path);
        abort_unless(is_file($fullPath), 404, 'The stored document file could not be found.');

        $safeName = Str::slug($reportTemplate->name) ?: 'template';
        $filename = "{$safeName}_{$reportTemplate->id}.docx";

        return response()->download($fullPath, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);
    }

    public function index(Request $request, CapabilityAuthorizer $authorizer): Response
    {
        $user = $request->user();
        $offices = [];
        $availableSurfaces = [];
        $flatVariants = [];
        $thumbnailService = app(DocumentThumbnailService::class);
        foreach (DocumentSurfaceRegistry::systemDefinitions() as $surfaceKey => $definition) {
            if (! DocumentSurfaceRegistry::canAuthorSurface($authorizer, $user, $surfaceKey)) {
                continue;
            }

            $surface = DocumentSurfaceRegistry::resolve($surfaceKey);
            if ($surface === null) {
                continue;
            }

            if (true) {
                $officeKey = $definition['office'];
                $offices[$officeKey] ??= [
                    'office' => $officeKey,
                    'office_label' => $definition['office_label'],
                    'surfaces' => [],
                ];
                $activeModel = ReportTemplate::query()
                    ->where('surface_key', $surfaceKey)
                    ->where('is_active', true)
                    ->first();
                $activeTemplate = $activeModel === null ? null : [
                    'id' => $activeModel->id,
                    'name' => $activeModel->name,
                    'mode' => $activeModel->mode,
                    'paper_size' => $activeModel->paper_size,
                    'orientation' => $activeModel->orientation,
                    'builder_json' => $activeModel->builder_json,
                    'updated_at' => $activeModel->updated_at,
                    'thumbnail_url' => route('setup.report-templates.thumbnail', [
                        'surface' => $surfaceKey,
                        'reportTemplate' => $activeModel->id,
                    ]).'?v='.$thumbnailService->fingerprint($activeModel->sourceChecksum()),
                ];
                $offices[$officeKey]['surfaces'][] = [
                    'surface_key' => $surfaceKey,
                    'title' => $surface->title(),
                    'office' => $officeKey,
                    'office_label' => $definition['office_label'],
                    'layout_paradigm' => $surface->layoutParadigm()->value,
                    'templates_count' => ReportTemplate::query()->where('surface_key', $surfaceKey)->count(),
                    'active_template' => $activeTemplate,
                ];
            }
            $packMeta = method_exists(OfficeDocumentPackCatalog::class, 'forSurface')
                ? OfficeDocumentPackCatalog::forSurface($surfaceKey)
                : [];
            $defaultGrain = $packMeta['grain'] ?? (in_array($surfaceKey, ['admission_slip', 'clearance_slip', 'registrar.admission_slip', 'registrar.clearance_slip'], true) ? 'student' : 'cycle_program');
            $defaultGrainLabel = $packMeta['grain_label'] ?? MountSlotRegistry::grainLabel($defaultGrain);
            $defaultPurpose = $packMeta['purpose'] ?? (in_array($surfaceKey, ['admission_slip', 'clearance_slip'], true) ? 'admissions' : 'analytics');
            $defaultMountedSlots = $packMeta['default_mounted_slots'] ?? ['analytics_reports_hub'];
            $packTitle = $packMeta['title'] ?? $surface->title();
            $availableSurfaces[] = [
                'surface_key' => $surfaceKey,
                'title' => $surface->title(),
                'office' => $definition['office'],
                'office_label' => $definition['office_label'],
                'layout_paradigm' => $surface->layoutParadigm()->value,
                'grain' => $defaultGrain,
                'grain_label' => $defaultGrainLabel,
                'purpose' => $defaultPurpose,
                'pack_title' => $packTitle,
                'create_variant_url' => route('setup.report-templates.create', ['surface' => $surfaceKey]),
            ];

            $surfaceTemplates = ReportTemplate::query()
                ->where('surface_key', $surfaceKey)
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get();

            foreach ($surfaceTemplates as $tpl) {
                $grain = is_string($tpl->grain ?? null) && $tpl->grain !== '' ? $tpl->grain : $defaultGrain;
                $grainLabel = $grain === $defaultGrain ? $defaultGrainLabel : MountSlotRegistry::grainLabel($grain);
                $mountedSlots = is_array($tpl->mounted_slots)
                    ? array_values($tpl->mounted_slots)
                    : $defaultMountedSlots;

                $flatVariants[] = [
                    'id' => $tpl->id,
                    'name' => $tpl->name,
                    'surface_key' => $surfaceKey,
                    'surface_title' => $surface->title(),
                    'office' => $definition['office'],
                    'office_label' => $definition['office_label'],
                    'mode' => $tpl->mode,
                    'paper_size' => $tpl->paper_size,
                    'orientation' => $tpl->orientation,
                    'is_active' => (bool) $tpl->is_active,
                    'grain' => $grain,
                    'grain_label' => $grainLabel,
                    'purpose' => $defaultPurpose,
                    'custom_group' => $tpl->custom_group,
                    'mounted_slots' => $mountedSlots,
                    'pack_title' => $packTitle,
                    'thumbnail_url' => route('setup.report-templates.thumbnail', [
                        'surface' => $surfaceKey,
                        'reportTemplate' => $tpl->id,
                    ]).'?v='.$thumbnailService->fingerprint($tpl->sourceChecksum()),
                    'edit_url' => route('setup.report-templates.edit', [
                        'surface' => $surfaceKey,
                        'reportTemplate' => $tpl->id,
                    ]),
                    'activate_url' => route('setup.report-templates.activate', [
                        'surface' => $surfaceKey,
                        'reportTemplate' => $tpl->id,
                    ]),
                    'deactivate_url' => route('setup.report-templates.deactivate', [
                        'surface' => $surfaceKey,
                        'reportTemplate' => $tpl->id,
                    ]),
                    'delete_url' => route('setup.report-templates.destroy', [
                        'surface' => $surfaceKey,
                        'reportTemplate' => $tpl->id,
                    ]),
                    'create_variant_url' => route('setup.report-templates.create', [
                        'surface' => $surfaceKey,
                    ]),
                    'download_source_url' => $tpl->mode === 'docx' && $tpl->document_path !== null
                        ? route('setup.report-templates.download-source', [
                            'surface' => $surfaceKey,
                            'reportTemplate' => $tpl->id,
                        ])
                        : null,
                ];
            }
        }

        $office = $user?->actorScope()->office;
        $customDocuments = ReportTemplate::query()
            ->where('surface_key', 'custom_document')
            ->where(function ($query) use ($office): void {
                $query->where('office', $office)->orWhereNull('office');
            })
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get()
            ->map(function (ReportTemplate $template) use ($thumbnailService, $office): array {
                $surface = DocumentSurfaceRegistry::resolve('custom_document');
                $officeLabel = match ($office) {
                    'admin' => 'Admin Office',
                    'registrar' => 'Registrar Office',
                    'guidance' => 'Guidance Office',
                    default => 'Office',
                };

                return [
                    'id' => $template->id,
                    'name' => $template->name,
                    'surface_key' => 'custom_document',
                    'surface_title' => $surface?->title() ?? 'Custom Office Document',
                    'office' => $template->office ?? $office,
                    'office_label' => $officeLabel,
                    'mode' => $template->mode,
                    'source_kind' => $template->source_kind,
                    'paper_size' => $template->paper_size,
                    'orientation' => $template->orientation,
                    'grain' => $template->grain,
                    'selected_leaves' => $template->selected_leaves ?? [],
                    'mounted_slots' => $template->mounted_slots ?? [],
                    'custom_group' => $template->custom_group,
                    'is_active' => (bool) $template->is_active,
                    'version' => $template->lock_version,
                    'updated_at' => $template->updated_at,
                    'thumbnail_url' => route('setup.report-templates.thumbnail', ['surface' => 'custom_document', 'reportTemplate' => $template->id]).'?v='.$thumbnailService->fingerprint($template->sourceChecksum()),
                    'docx_original_filename' => null,
                    'download_source_url' => $template->mode === 'docx' && $template->document_path !== null
                        ? route('setup.report-templates.download-source', ['surface' => 'custom_document', 'reportTemplate' => $template->id])
                        : null,
                    'edit_url' => route('setup.report-templates.edit', ['surface' => 'custom_document', 'reportTemplate' => $template->id]),
                    'activate_url' => route('setup.report-templates.activate', ['surface' => 'custom_document', 'reportTemplate' => $template->id]),
                    'deactivate_url' => route('setup.report-templates.deactivate', ['surface' => 'custom_document', 'reportTemplate' => $template->id]),
                    'delete_url' => route('setup.report-templates.destroy', ['surface' => 'custom_document', 'reportTemplate' => $template->id]),
                ];
            })->all();

        return Inertia::render('Setup/ReportTemplates/Index', [
            'offices' => array_values($offices),
            'flat_variants' => $flatVariants,
            'available_surfaces' => $availableSurfaces,
            'can_create_custom_document' => DocumentSurfaceRegistry::canAuthorSurface($authorizer, $user, 'custom_document'),
            'custom_documents' => $customDocuments,
            'page_state' => 'ready',
            'baseline_inventory' => BaselineInventoryCatalog::all(),
            'office_packs' => OfficeDocumentPackCatalog::builtinDefinitions(),
        ]);
    }

    public function surface(Request $request, CapabilityAuthorizer $authorizer, string $surface): Response
    {
        abort_unless(DocumentSurfaceRegistry::has($surface), 404);
        $this->authorizeSurface($authorizer, $request->user(), $surface);
        $definition = DocumentSurfaceRegistry::definition($surface);
        $surfaceObject = DocumentSurfaceRegistry::resolve($surface);
        abort_if($surfaceObject === null || $definition === null, 404);

        $thumbnailService = app(DocumentThumbnailService::class);
        $templatesQuery = ReportTemplate::query()->where('surface_key', $surface);
        if ($surface === 'custom_document') {
            $office = $request->user()?->actorScope()->office;
            $templatesQuery->where(function ($query) use ($office): void {
                $query->where('office', $office)->orWhereNull('office');
            });
        }
        $templates = $templatesQuery
            ->orderByDesc('is_active')->orderBy('name')->get()
            ->map(fn (ReportTemplate $tpl): array => [
                ...$tpl->toArray(),
                'thumbnail_url' => route('setup.report-templates.thumbnail', [
                    'surface' => $surface,
                    'reportTemplate' => $tpl->id,
                ]).'?v='.$thumbnailService->fingerprint($tpl->sourceChecksum()),
            ]);

        return Inertia::render('Setup/ReportTemplates/Surface', [
            'surface' => $this->surfaceData($definition, $surfaceObject),
            'templates' => $templates,
            'page_state' => 'ready',
            'baseline_inventory' => BaselineInventoryCatalog::all(),
            'office_packs' => OfficeDocumentPackCatalog::packsForOffice($definition['office'] ?? null),
        ]);
    }

    public function create(Request $request, CapabilityAuthorizer $authorizer, string $surface): Response
    {
        $user = $this->authorizeSurface($authorizer, $request->user(), $surface);
        $definition = DocumentSurfaceRegistry::definition($surface);
        $surfaceObject = DocumentSurfaceRegistry::resolve($surface);
        abort_if($definition === null || $surfaceObject === null, 404);
        $activePack = $this->activePack($surface, $surfaceObject);
        $officeKey = $surface === 'custom_document'
            ? ($user?->actorScope()->office ?? 'guidance')
            : $definition['office'];
        $defaultGrain = $surface === 'custom_document'
            ? app(DocumentFieldPolicy::class)->defaultGrainForSurface($user, $surface)
            : ($activePack['grain'] ?? 'cycle_program');
        $fieldPolicy = $user !== null
            ? app(DocumentFieldPolicy::class)->allowedFor($user, $surface, $defaultGrain)
            : null;
        $policy = app(DocumentFieldPolicy::class);
        $allowedGrains = $policy->allowedGrainsForDesk($officeKey, $surface);
        $activePack['office_key'] = $officeKey;
        $activePack['office'] = $officeKey;
        $activePack['grain'] = $defaultGrain;
        $activePack['compatible_slots'] = MountSlotRegistry::forOffice($officeKey);
        if ($surface === 'custom_document') {
            $officeLabel = match ($officeKey) {
                'admin' => 'Admin Office',
                'registrar' => 'Registrar Office',
                default => 'Guidance Office',
            };
            $definition['office'] = $officeKey;
            $definition['office_label'] = $officeLabel;
            $activePack['office_label'] = $officeLabel;
            $activePack['default_mounted_slots'] = [];
        }
        $defaultSelectedLeaves = $surface === 'custom_document' || $request->boolean('blank')
            ? []
            : OfficeDocumentPackCatalog::defaultSelectedLeavesForSurface($surface);
        $activePack['default_selected_leaves'] = $defaultSelectedLeaves;
        $allowedLeaves = $fieldPolicy ?? [];

        return Inertia::render('Setup/ReportTemplates/Create', [
            'surface' => $this->surfaceData($definition, $surfaceObject),
            'active_pack' => $activePack,
            'field_policy' => [
                'allowed_leaves' => $allowedLeaves,
                'default_selected_leaves' => $defaultSelectedLeaves,
                'allowed_grains' => $allowedGrains,
                'default_grain' => $defaultGrain,
                'office_key' => $officeKey,
            ],
            'placeholderCatalog' => $this->placeholderCatalogFor($surfaceObject, $allowedLeaves),
            'placeholderGroups' => $this->placeholderGroups($surfaceObject, $allowedLeaves),
            'domainPlaceholders' => [],
            'sampleContext' => array_replace($this->sampleContextFor($surfaceObject, $allowedLeaves), [
                'page_i' => '1',
                'page_all' => '2',
                'pg_i' => '1',
                'pg_all' => '2',
            ]),
            'builderCatalog' => $surfaceObject->componentCatalog(),
            'builderCapabilities' => ResultSheetBuilderSchema::componentCapabilities('a4', 'portrait'),
            'builderDefaults' => $surfaceObject->defaultBlueprint('a4', 'portrait'),
            'builderCompilerVersion' => 'document-blueprint-1',
            'draft' => $this->resolveCreateDraftState($request, $surface),
            'baseline_inventory' => BaselineInventoryCatalog::all(),
            'office_packs' => OfficeDocumentPackCatalog::packsForOffice($definition['office'] ?? null),
        ]);
    }

    public function store(
        Request $request,
        CapabilityAuthorizer $authorizer,
        string $surface,
        DocxTextExtractor $extractor,
        DocumentTemplateAnalyzer $analyzer,
        DocumentBlueprintCompiler $compiler,
        RecordAuditEvent $audit,
        ValidateDocumentExecutionContract $executionContract
    ): JsonResponse|RedirectResponse {
        $user = $this->authorizeSurface($authorizer, $request->user(), $surface);
        $surfaceObject = DocumentSurfaceRegistry::resolve($surface);
        abort_if($surfaceObject === null, 404);
        $this->decodeBuilderInput($request);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'mode' => ['required', 'in:docx,html'],
            'paper_size' => ['sometimes', 'in:a4,letter,legal'],
            'orientation' => ['sometimes', 'in:portrait,landscape'],
            'document' => ['required_if:mode,docx', 'nullable', 'file', 'mimes:docx', 'max:20480'],
            'content' => ['nullable', 'string'],
            'builder_json' => ['sometimes', 'nullable', 'array'],
            'is_active' => ['sometimes', 'boolean'],
            'mounted_slots' => ['sometimes', 'nullable', 'array'],
            'mounted_slots.*' => ['string', 'max:80'],
            'custom_group' => ['sometimes', 'nullable', 'string', 'max:120'],
            'selected_leaves' => ['sometimes', 'array'],
            'selected_leaves.*' => ['string', 'max:160'],
            'field_policy_version' => ['sometimes', 'integer', 'min:1'],
            'grain' => ['sometimes', 'string', 'max:40'],
        ]);
        $activePack = $this->activePack($surface, $surfaceObject);
        $grain = $validated['grain'] ?? $activePack['grain'] ?? 'cycle_program';
        $definition = DocumentSurfaceRegistry::definition($surface);
        $office = $surface === 'custom_document'
            ? ($user->actorScope()->office ?? 'guidance')
            : ($definition['office'] ?? null);
        $selectedLeaves = $validated['selected_leaves'] ?? [];
        $executionContract->validate(
            $user,
            $surface,
            $grain,
            $selectedLeaves,
            $validated['builder_json'] ?? null,
            $validated['content'] ?? null,
        );
        abort_unless($validated['mode'] !== 'html' || isset($validated['content']) || isset($validated['builder_json']), 422, 'HTML template content is required.');

        $attributes = [
            'surface_key' => $surface,
            'name' => $validated['name'],
            'mode' => $validated['mode'],
            'source_kind' => isset($validated['builder_json']) ? 'builder' : $validated['mode'],
            'paper_size' => $validated['paper_size'] ?? 'a4',
            'orientation' => $validated['orientation'] ?? 'portrait',
            'is_active' => false,
            'created_by_user_id' => $user->getAuthIdentifier(),
            'institution_id' => $request->user()?->staffProfile?->institution_id ?? Institution::current()?->id,
            'office' => $office,
        ];
        if (Schema::hasColumn('report_templates', 'mounted_slots')) {
            $requestedSlots = array_key_exists('mounted_slots', $validated) && is_array($validated['mounted_slots'])
                ? $validated['mounted_slots']
                : (array_key_exists('custom_group', $validated) ? [] : ($activePack['default_mounted_slots'] ?? ['analytics_reports_hub']));
            $attributes['grain'] = $grain;
            $attributes['mounted_slots'] = MountSlotRegistry::sanitizeSlots($requestedSlots, $grain, $office);
            $attributes['custom_group'] = isset($validated['custom_group']) && trim((string) $validated['custom_group']) !== ''
                ? trim((string) $validated['custom_group'])
                : null;
        }
        if (array_key_exists('selected_leaves', $validated)) {
            $attributes['selected_leaves'] = array_values(array_unique($validated['selected_leaves']));
        }
        if (array_key_exists('field_policy_version', $validated)) {
            $attributes['field_policy_version'] = $validated['field_policy_version'];
        }
        if ($validated['mode'] === 'docx') {
            $file = $request->file('document');
            abort_unless($file !== null && $file->isValid(), 422, 'A valid DOCX document is required.');
            $inspection = $extractor->inspect($file->getRealPath());
            abort_if($inspection['unresolved'] !== [], 422, 'The DOCX contains unsupported or malformed macros.');
            $documentText = $extractor->text($file->getRealPath());
            $analysis = $analyzer->analyze($documentText, $surfaceObject->placeholderCatalog());
            abort_unless($analysis['valid'], 422, 'The DOCX macros do not match this report surface placeholder catalog.');
            $executionContract->validate($user, $surface, $grain, $selectedLeaves, null, $documentText);
            $attributes['document_extension'] = 'docx';
            $attributes['document_checksum'] = hash_file('sha256', $file->getRealPath());
        } else {
            if (isset($validated['builder_json'])) {
                $compiled = $this->compileBuilder($compiler, $validated['builder_json'], $surfaceObject);
                abort_if($compiled['diagnostics'] !== [], 422, 'The document builder contains invalid components.');
                $attributes['content'] = $compiled['html'];
                $attributes['compiled_html'] = $compiled['html'];
                $attributes['builder_json'] = $compiled['document'];
            } else {
                $attributes['content'] = $validated['content'] ?? '';
                $attributes['compiled_html'] = $validated['content'] ?? '';
                $attributes['builder_json'] = null;
            }
            $attributes['builder_schema_version'] = isset($validated['builder_json']) ? 1 : null;
            $attributes['compiler_version'] = isset($validated['builder_json']) ? 'document-blueprint-1' : null;
        }

        $template = DB::transaction(function () use ($attributes, $request, $surface, $user, $audit): ReportTemplate {
            $template = ReportTemplate::query()->create($attributes);
            if ($attributes['mode'] === 'docx') {
                $file = $request->file('document');
                $checksum = $attributes['document_checksum'];
                $filename = "{$template->id}_{$checksum}.docx";
                $path = "report-templates/{$surface}/{$filename}";
                abort_unless($file !== null && Storage::disk('local')->putFileAs("report-templates/{$surface}", $file, $filename), 500, 'Unable to store report template.');
                $template->forceFill(['document_path' => $path])->save();
            }
            if ($request->boolean('is_active')) {
                if ($surface !== 'custom_document') {
                    ReportTemplate::query()->where('surface_key', $surface)->lockForUpdate()->get();
                    ReportTemplate::query()->where('surface_key', $surface)->where('id', '!=', $template->id)->update(['is_active' => false]);
                }
                $template->forceFill(['is_active' => true])->save();
            }
            $audit->handle('report_template.created', ['template_id' => $template->id, 'surface_key' => $surface], (string) $user->getAuthIdentifier());

            return $template;
        });

        try {
            $fingerprint = app(DocumentThumbnailService::class)->fingerprint($template->sourceChecksum());
            RenderReportTemplateThumbnailJob::dispatch($surface, $template->id, $template->sourceChecksum(), $fingerprint)->afterCommit();
        } catch (\Throwable) {
            // Best-effort warm-on-save failure must not abort successful save.
        }

        $redirectRoute = $surface === 'custom_document'
            ? route('setup.document-templates.index')
            : ($request->input('return_to') === 'hub'
                ? route('setup.report-templates.index')
                : route('setup.report-templates.surface', ['surface' => $surface]));

        return $request->wantsJson()
            ? response()->json(['id' => $template->id], 201)
            : redirect()->to($redirectRoute)->with('status', 'Report template created.');
    }

    public function edit(
        Request $request,
        CapabilityAuthorizer $authorizer,
        string $surface,
        ReportTemplate $reportTemplate,
        DocxTextExtractor $extractor,
        DocumentTemplateAnalyzer $analyzer
    ): Response {
        $this->assertTemplateSurface($reportTemplate, $surface);
        $user = $this->authorizeSurface($authorizer, $request->user(), $surface);
        $this->assertTemplateOffice($reportTemplate, $user);
        $definition = DocumentSurfaceRegistry::definition($surface);
        $surfaceObject = DocumentSurfaceRegistry::resolve($surface);
        abort_if($definition === null || $surfaceObject === null, 404);
        $activePack = $this->activePack($surface, $surfaceObject);
        $officeKey = $surface === 'custom_document'
            ? ($reportTemplate->office ?? $user?->actorScope()->office ?? 'guidance')
            : $definition['office'];
        $defaultGrain = $surface === 'custom_document'
            ? ($reportTemplate->grain ?? app(DocumentFieldPolicy::class)->defaultGrainForSurface($user, $surface))
            : ($activePack['grain'] ?? 'cycle_program');
        $fieldPolicy = $user !== null
            ? app(DocumentFieldPolicy::class)->allowedFor($user, $surface, $defaultGrain)
            : null;
        $policy = app(DocumentFieldPolicy::class);
        $allowedGrains = $policy->allowedGrainsForDesk($officeKey, $surface);
        $activePack['office_key'] = $officeKey;
        $activePack['office'] = $officeKey;
        $activePack['grain'] = $defaultGrain;
        $activePack['compatible_slots'] = MountSlotRegistry::forOffice($officeKey);
        if ($surface === 'custom_document') {
            $officeLabel = match ($officeKey) {
                'admin' => 'Admin Office',
                'registrar' => 'Registrar Office',
                default => 'Guidance Office',
            };
            $definition['office'] = $officeKey;
            $definition['office_label'] = $officeLabel;
            $activePack['office_label'] = $officeLabel;
            $activePack['default_mounted_slots'] = [];
        }
        $activePack['default_selected_leaves'] = $activePack['default_selected_leaves'] ?? [];
        $allowedLeaves = $fieldPolicy ?? [];
        $paperSize = $reportTemplate->paper_size ?? 'a4';
        $orientation = $reportTemplate->orientation ?? 'portrait';
        $documentPath = $reportTemplate->document_path !== null
            ? Storage::disk('local')->path($reportTemplate->document_path)
            : null;
        $documentAnalysis = $reportTemplate->mode === 'docx'
            && $documentPath !== null
            && is_file($documentPath)
            ? $analyzer->analyze($extractor->text($documentPath), $surfaceObject->placeholderCatalog()) + [
                'source_checksum' => $reportTemplate->document_checksum,
            ]
            : null;

        $templatePayload = [
            ...$reportTemplate->toArray(),
            'grain' => $defaultGrain,
            'mounted_slots' => $reportTemplate->mounted_slots ?? $activePack['default_mounted_slots'] ?? ['analytics_reports_hub'],
            'custom_group' => $reportTemplate->custom_group,
            'download_source_url' => $reportTemplate->mode === 'docx' && $reportTemplate->document_path !== null
                ? route('setup.report-templates.download-source', ['surface' => $surface, 'reportTemplate' => $reportTemplate->id])
                : null,
        ];

        return Inertia::render('Setup/ReportTemplates/Edit', [
            'active_pack' => $activePack,
            'field_policy' => [
                'allowed_leaves' => $allowedLeaves,
                'allowed_grains' => $allowedGrains,
                'default_grain' => $defaultGrain,
                'office_key' => $officeKey,
            ],
            'surface' => $this->surfaceData($definition, $surfaceObject),
            'template' => $templatePayload,
            'placeholderCatalog' => $this->placeholderCatalogFor($surfaceObject, $allowedLeaves),
            'placeholderGroups' => $this->placeholderGroups($surfaceObject, $allowedLeaves),
            'domainPlaceholders' => [],
            'sampleContext' => array_replace($this->sampleContextFor($surfaceObject, $allowedLeaves), [
                'page_i' => '1',
                'page_all' => '2',
                'pg_i' => '1',
                'pg_all' => '2',
            ]),
            'builderCatalog' => $surfaceObject->componentCatalog(),
            'builderCapabilities' => ResultSheetBuilderSchema::componentCapabilities($paperSize, $orientation),
            'builderDefaults' => $surfaceObject->defaultBlueprint($paperSize, $orientation),
            'builderCompilerVersion' => 'document-blueprint-1',
            'draft' => $request->filled('draft')
                ? $this->resolveDraftState($request, $surface, $reportTemplate)
                : null,
            'documentAnalysis' => $documentAnalysis,
            'baseline_inventory' => BaselineInventoryCatalog::all(),
            'office_packs' => OfficeDocumentPackCatalog::packsForOffice($definition['office'] ?? null),
        ]);
    }

    public function update(
        Request $request,
        CapabilityAuthorizer $authorizer,
        string $surface,
        ReportTemplate $reportTemplate,
        DocxTextExtractor $extractor,
        DocumentTemplateAnalyzer $analyzer,
        RecordAuditEvent $audit,
        ValidateDocumentExecutionContract $executionContract,
        DocumentBlueprintCompiler $compiler,
    ): JsonResponse|RedirectResponse {
        $this->assertTemplateSurface($reportTemplate, $surface);
        $user = $this->authorizeSurface($authorizer, $request->user(), $surface);
        $this->assertTemplateOffice($reportTemplate, $user);
        $surfaceObject = DocumentSurfaceRegistry::resolve($surface);
        abort_if($surfaceObject === null, 404);
        $this->decodeBuilderInput($request);
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'mode' => ['sometimes', 'in:docx,html'],
            'paper_size' => ['sometimes', 'in:a4,letter,legal'],
            'orientation' => ['sometimes', 'in:portrait,landscape'],
            'content' => ['sometimes', 'nullable', 'string'],
            'document' => ['sometimes', 'nullable', 'file', 'mimes:docx', 'max:20480'],
            'builder_json' => ['sometimes', 'nullable', 'array'],
            'is_active' => ['sometimes', 'boolean'],
            'mounted_slots' => ['sometimes', 'nullable', 'array'],
            'mounted_slots.*' => ['string', 'max:80'],
            'custom_group' => ['sometimes', 'nullable', 'string', 'max:120'],
            'selected_leaves' => ['sometimes', 'array'],
            'selected_leaves.*' => ['string', 'max:160'],
            'field_policy_version' => ['sometimes', 'integer', 'min:1'],
            'grain' => ['sometimes', 'string', 'max:40'],
            'expected_lock_version' => ['sometimes', 'integer', 'min:0'],
        ]);
        $mode = $validated['mode'] ?? $reportTemplate->mode;
        $activePack = $this->activePack($surface, $surfaceObject);
        $definition = DocumentSurfaceRegistry::definition($surface);
        $officeKey = $reportTemplate->office ?? $request->user()?->actorScope()->office ?? 'guidance';
        if ($surface === 'custom_document') {
            $officeLabel = match ($officeKey) {
                'admin' => 'Admin Office',
                'registrar' => 'Registrar Office',
                default => 'Guidance Office',
            };
            $definition['office'] = $officeKey;
            $definition['office_label'] = $officeLabel;
            $activePack['office_key'] = $officeKey;
            $activePack['office'] = $officeKey;
            $activePack['office_label'] = $officeLabel;
            $activePack['default_mounted_slots'] = [];
            $activePack['compatible_slots'] = MountSlotRegistry::forGrain($activePack['grain'], $officeKey);
        }
        $office = $surface === 'custom_document' ? $officeKey : ($definition['office'] ?? null);
        $grain = $validated['grain'] ?? $reportTemplate->grain ?? $activePack['grain'] ?? 'cycle_program';
        if ($surface === 'custom_document') {
            $activePack['compatible_slots'] = MountSlotRegistry::forGrain($grain, $officeKey);
        }
        $selectedLeaves = $validated['selected_leaves'] ?? (array) ($reportTemplate->selected_leaves ?? []);
        $sourceKind = isset($validated['builder_json'])
            ? ReportTemplate::SOURCE_BUILDER
            : (array_key_exists('content', $validated)
                ? ReportTemplate::SOURCE_HTML
                : ($mode === ReportTemplate::MODE_HTML && $reportTemplate->mode !== ReportTemplate::MODE_HTML
                    ? ReportTemplate::SOURCE_HTML
                    : $reportTemplate->source_kind));
        $executionContract->validate(
            $user,
            $surface,
            $grain,
            $selectedLeaves,
            $validated['builder_json'] ?? $reportTemplate->builder_json,
            $mode === ReportTemplate::MODE_HTML && $sourceKind === ReportTemplate::SOURCE_BUILDER
                ? null
                : ($validated['content'] ?? $reportTemplate->content),
        );
        abort_unless($mode !== 'docx' || $reportTemplate->mode === 'docx' || $request->hasFile('document'), 422, 'A DOCX document is required when changing template mode.');
        $changes = array_intersect_key($validated, array_flip(['name', 'mode', 'paper_size', 'orientation', 'content']));
        if (Schema::hasColumn('report_templates', 'mounted_slots')) {
            $changes['grain'] = $grain;
            if ($request->has('mounted_slots')) {
                $requestedSlots = is_array($validated['mounted_slots'] ?? null) ? $validated['mounted_slots'] : [];
                $changes['mounted_slots'] = MountSlotRegistry::sanitizeSlots($requestedSlots, $grain, $office);
            }
            if (array_key_exists('custom_group', $validated)) {
                $changes['custom_group'] = isset($validated['custom_group']) && trim((string) $validated['custom_group']) !== ''
                    ? trim((string) $validated['custom_group'])
                    : null;
            }
        }
        if (array_key_exists('selected_leaves', $validated)) {
            $changes['selected_leaves'] = array_values(array_unique($validated['selected_leaves']));
        }
        if (array_key_exists('field_policy_version', $validated)) {
            $changes['field_policy_version'] = $validated['field_policy_version'];
        }
        if ($mode === 'html') {
            abort_unless(isset($validated['content']) || isset($validated['builder_json']) || $reportTemplate->mode === 'html', 422, 'HTML template content is required.');
            if (isset($validated['builder_json']) || array_key_exists('content', $validated) || $reportTemplate->mode === 'docx') {
                $changes['source_kind'] = isset($validated['builder_json']) ? 'builder' : 'html';
            }
            if ($reportTemplate->mode === 'docx') {
                $changes += ['document_path' => null, 'document_checksum' => null, 'document_extension' => null];
            }
            if (isset($validated['builder_json'])) {
                $compiled = $this->compileBuilder($compiler, $validated['builder_json'], $surfaceObject);
                abort_if($compiled['diagnostics'] !== [], 422, 'The document builder contains invalid components.');
                $changes['content'] = $compiled['html'];
                $changes['compiled_html'] = $compiled['html'];
                $changes['builder_json'] = $compiled['document'];
                $changes['builder_schema_version'] = 1;
                $changes['compiler_version'] = 'document-blueprint-1';
            } elseif (array_key_exists('content', $validated)) {
                $changes['content'] = $validated['content'];
                $changes['compiled_html'] = $validated['content'];
                $changes['builder_json'] = null;
                $changes['builder_schema_version'] = null;
                $changes['compiler_version'] = null;
            }
        }
        $previousDocumentPath = $reportTemplate->document_path;
        if ($request->hasFile('document')) {
            abort_unless($mode === 'docx', 422, 'DOCX documents require DOCX mode.');
            $file = $request->file('document');
            abort_unless($file !== null && $file->isValid(), 422, 'A valid DOCX document is required.');
            $inspection = $extractor->inspect($file->getRealPath());
            abort_if($inspection['unresolved'] !== [], 422, 'The DOCX contains unsupported or malformed macros.');
            $documentText = $extractor->text($file->getRealPath());
            $analysis = $analyzer->analyze($documentText, $surfaceObject->placeholderCatalog());
            abort_unless($analysis['valid'], 422, 'The DOCX macros do not match this report surface placeholder catalog.');
            $executionContract->validate($user, $surface, $grain, $selectedLeaves, null, $documentText);
            $checksum = hash_file('sha256', $file->getRealPath());
            $filename = "{$reportTemplate->id}_{$checksum}.docx";
            $path = "report-templates/{$surface}/{$filename}";
            $changes += [
                'mode' => 'docx',
                'source_kind' => 'docx',
                'document_extension' => 'docx',
                'document_path' => $path,
                'document_checksum' => $checksum,
                'content' => null,
                'compiled_html' => null,
                'builder_json' => null,
                'builder_schema_version' => null,
                'compiler_version' => null,
            ];
        } elseif ($mode === 'docx' && $reportTemplate->document_path !== null) {
            $documentPath = Storage::disk('local')->path($reportTemplate->document_path);
            if (is_file($documentPath)) {
                $executionContract->validate($user, $surface, $grain, $selectedLeaves, null, $extractor->text($documentPath));
            }
        }
        $reportTemplate = DB::transaction(function () use ($reportTemplate, $changes, $audit, $user, $surface, $request): ReportTemplate {
            ReportTemplate::query()->where('surface_key', $surface)->lockForUpdate()->get();
            $template = ReportTemplate::query()->whereKey($reportTemplate->getKey())->lockForUpdate()->firstOrFail();
            if ($request->has('expected_lock_version') && $template->lock_version !== (int) $request->input('expected_lock_version')) {
                abort(409, 'Conflict: This template was updated by another user.');
            }
            if ($request->hasFile('document')) {
                $file = $request->file('document');
                $documentPath = (string) $changes['document_path'];
                $filename = basename($documentPath);
                abort_unless(
                    Storage::disk('local')->exists($documentPath)
                        || ($file !== null && Storage::disk('local')->putFileAs("report-templates/{$surface}", $file, $filename)),
                    500,
                    'Unable to store report template.',
                );
            }
            $template->fill($changes);
            $template->lock_version = (int) $template->lock_version + 1;
            $template->save();
            if ($request->boolean('is_active')) {
                if ($surface !== 'custom_document') {
                    ReportTemplate::query()->where('surface_key', $surface)->where('id', '!=', $template->id)->update(['is_active' => false]);
                }
                $template->forceFill(['is_active' => true])->save();
            }
            $audit->handle('report_template.updated', ['template_id' => $template->id, 'surface_key' => $surface], (string) $user->getAuthIdentifier());

            return $template;
        });
        $userId = (string) $user->getAuthIdentifier();
        Cache::forget($this->draftCacheKey($surface, $userId, 'report_draft_'.$surface.'_'.$userId.'_'.$reportTemplate->id));
        if ($request->filled('draft_id')) {
            Cache::forget($this->draftCacheKey($surface, $userId, (string) $request->input('draft_id')));
        }
        if ($previousDocumentPath !== null && $reportTemplate->document_path !== $previousDocumentPath) {
            Storage::disk('local')->delete($previousDocumentPath);
        }

        try {
            $fingerprint = app(DocumentThumbnailService::class)->fingerprint($reportTemplate->sourceChecksum());
            RenderReportTemplateThumbnailJob::dispatch($surface, $reportTemplate->id, $reportTemplate->sourceChecksum(), $fingerprint)->afterCommit();
        } catch (\Throwable) {
            // Best-effort warm-on-save failure must not abort successful update.
        }

        $redirectRoute = $surface === 'custom_document'
            ? route('setup.document-templates.index')
            : ($request->input('return_to') === 'hub'
                ? route('setup.report-templates.index')
                : route('setup.report-templates.surface', ['surface' => $surface]));

        return $request->wantsJson()
            ? response()->json(['id' => $reportTemplate->id])
            : redirect()->to($redirectRoute)->with('status', 'Report template updated.');
    }

    public function validateDocument(
        Request $request,
        CapabilityAuthorizer $authorizer,
        string $surface,
        DocxTextExtractor $extractor,
        DocumentTemplateAnalyzer $analyzer
    ): JsonResponse {
        $this->authorizeSurface($authorizer, $request->user(), $surface);
        $surfaceObject = DocumentSurfaceRegistry::resolve($surface);
        abort_if($surfaceObject === null, 404);
        $validated = $request->validate([
            'document' => ['sometimes', 'file', 'mimes:docx', 'max:20480'],
            'content' => ['sometimes', 'nullable', 'string'],
        ]);
        abort_unless($request->hasFile('document') || is_string($validated['content'] ?? null), 422, 'A DOCX document or content string is required.');

        if ($request->hasFile('document')) {
            $file = $request->file('document');
            abort_unless($file !== null && $file->isValid(), 422, 'A valid DOCX document is required.');
            $analysis = $analyzer->analyze($extractor->text($file->getRealPath()), $surfaceObject->placeholderCatalog());
            $analysis['source_checksum'] = hash_file('sha256', $file->getRealPath());
        } else {
            $content = (string) $validated['content'];
            $analysis = $analyzer->analyze($content, $surfaceObject->placeholderCatalog());
            $analysis['source_checksum'] = hash('sha256', $content);
        }

        return response()->json($analysis);
    }

    public function preview(
        Request $request,
        CapabilityAuthorizer $authorizer,
        string $surface,
        DocumentBlueprintCompiler $compiler,
        DocxTemplateFiller $filler,
        LibreOfficeConverter $converter,
        DocxTextExtractor $extractor,
        ReportHtmlContextFiller $htmlFiller
    ): JsonResponse {
        $this->decodeBuilderInput($request);
        $user = $this->authorizeSurface($authorizer, $request->user(), $surface);
        $surfaceObject = DocumentSurfaceRegistry::resolve($surface);
        abort_if($surfaceObject === null, 404);
        $validated = $request->validate([
            'mode' => ['required', 'in:html,docx'],
            'paper_size' => ['sometimes', 'in:a4,letter,legal'],
            'orientation' => ['sometimes', 'in:portrait,landscape'],
            'builder_json' => ['sometimes', 'nullable', 'array'],
            'content' => ['sometimes', 'nullable', 'string'],
            'document' => ['sometimes', 'nullable', 'file', 'mimes:docx', 'max:20480'],
            'template_id' => ['sometimes', 'integer', 'exists:report_templates,id'],
        ]);
        $previewTemplate = isset($validated['template_id'])
            ? ReportTemplate::query()->findOrFail((int) $validated['template_id'])
            : null;
        if ($previewTemplate !== null) {
            $this->assertTemplateSurface($previewTemplate, $surface);
            $this->assertTemplateOffice($previewTemplate, $user);
        }
        $policy = app(DocumentFieldPolicy::class);
        $grain = $previewTemplate?->grain
            ?? ($surface === 'custom_document'
                ? $policy->defaultGrainForSurface($user, $surface)
                : (OfficeDocumentPackCatalog::forSurface($surface)['grain'] ?? MountSlotRegistry::GRAIN_CYCLE_PROGRAM));
        $sampleContext = $this->sampleContextFor($surfaceObject, $policy->allowedFor($user, $surface, $grain));
        $paperSize = $validated['paper_size'] ?? 'a4';
        $orientation = $validated['orientation'] ?? 'portrait';
        if ($validated['mode'] === 'html') {
            $builder = $request->input('builder_json');
            if (is_string($builder) && trim($builder) !== '') {
                $decoded = json_decode($builder, true);
                if (is_array($decoded)) {
                    $builder = $decoded;
                }
            }
            $content = $request->input('content');
            if ((! is_array($builder) || empty($builder['components'])) && (empty($content) || trim((string) $content) === '') && $request->filled('template_id')) {
                $fallbackTemplate = ReportTemplate::query()->find($request->integer('template_id'));
                if ($fallbackTemplate !== null) {
                    $this->assertTemplateOffice($fallbackTemplate, $user);
                    $this->assertTemplateSurface($fallbackTemplate, $surface);
                    $builder = $fallbackTemplate->builder_json;
                    $content = $fallbackTemplate->content;
                }
            }
            if ((! is_array($builder) || empty($builder['components'])) && (empty($content) || trim((string) $content) === '')) {
                $builder = $surfaceObject->defaultBlueprint($paperSize, $orientation);
            }
            abort_unless(is_array($builder) || (is_string($content) && trim($content) !== ''), 422, 'A valid builder document or HTML content is required.');
            if (is_array($builder) && ! empty($builder['components'])) {
                $compiled = $this->compileBuilder($compiler, $builder, $surfaceObject);
                if ($compiled['diagnostics'] !== []) {
                    return response()->json(['error' => 'builder_invalid', 'diagnostics' => $compiled['diagnostics']], 422);
                }
                $rawHtml = $compiled['html'];
            } else {
                $rawHtml = (string) $content;
            }
            $filledHtml = $htmlFiller->fill($rawHtml, $sampleContext);
            $wrappedHtml = (new ResultSheetCssService)->wrapForPdf(
                $filledHtml,
                $paperSize,
                $orientation,
                (float) (is_array($builder) ? ($builder['page']['margin_mm'] ?? 12) : 12)
            );
            $pdfBytes = app(PrintRenderer::class)->render($wrappedHtml, [
                'format' => $paperSize,
                'orientation' => $orientation,
            ]);

            return response()->json([
                'html' => $this->pdfIframe($pdfBytes),
                'pdf_base64' => base64_encode($pdfBytes),
            ]);
        } else {
            $sourcePath = null;
            if ($request->hasFile('document')) {
                $file = $request->file('document');
                abort_unless($file !== null && $file->isValid(), 422, 'A valid DOCX document is required.');
                $sourcePath = $file->getRealPath();
            } elseif (isset($validated['template_id'])) {
                $template = ReportTemplate::query()->findOrFail($validated['template_id']);
                $this->assertTemplateSurface($template, $surface);
                $this->assertTemplateOffice($template, $user);
                abort_unless($template->mode === 'docx' && $template->document_path !== null, 422, 'A DOCX template is required.');
                $sourcePath = Storage::disk('local')->path($template->document_path);
                $paperSize = $validated['paper_size'] ?? $template->paper_size;
                $orientation = $validated['orientation'] ?? $template->orientation;
            }
            abort_unless(is_string($sourcePath) && is_file($sourcePath), 422, 'A DOCX document or template is required.');
            $filledPath = $filler->fill($sourcePath, $sampleContext, $surfaceObject->repeatingRegions());
            try {
                if ($converter->isAvailable()) {
                    $pdfBytes = $converter->convertToPdf($filledPath);
                } else {
                    $fallbackHtml = '<html><body>'.nl2br(e($extractor->text($filledPath))).'</body></html>';
                    $pdfBytes = Pdf::loadHTML($fallbackHtml)->setPaper($paperSize, $orientation)->output();
                }
            } finally {
                if (is_file($filledPath)) {
                    unlink($filledPath);
                }
            }
            $html = $this->pdfIframe($pdfBytes);
        }

        return response()->json([
            'html' => $html,
            'pdf_base64' => base64_encode($pdfBytes),
        ]);
    }

    public function storeDraft(Request $request, CapabilityAuthorizer $authorizer, string $surface): JsonResponse
    {
        $user = $this->authorizeSurface($authorizer, $request->user(), $surface);
        $userId = (string) $user->getAuthIdentifier();
        $templateId = $request->input('source_template_id') ?? $request->input('template_id') ?? 'new';
        $office = $user->actorScope()->office;
        $defaultDraftId = $surface === 'custom_document'
            ? "report_draft_custom_document_{$office}_{$userId}_{$templateId}"
            : 'report_draft_'.$surface.'_'.$userId.'_'.$templateId;
        $draftId = (string) ($request->input('draft') ?? $request->input('draft_id') ?? $defaultDraftId);
        $payload = [
            'id' => $draftId,
            'stage' => (int) $request->input('stage', 1),
            'lock_version' => ((int) $request->input('lock_version', 1)) + 1,
            'source_template_id' => $request->input('source_template_id'),
            'state' => $request->input('state', []),
        ];
        Cache::put($this->draftCacheKey($surface, $userId, $draftId), $payload, now()->addDays(7));

        return response()->json(['draft' => $payload], 201);
    }

    public function updateDraft(Request $request, CapabilityAuthorizer $authorizer, string $surface, string $draft): JsonResponse
    {
        $user = $this->authorizeSurface($authorizer, $request->user(), $surface);
        $userId = (string) $user->getAuthIdentifier();
        $payload = [
            'id' => $draft,
            'stage' => (int) $request->input('stage', 1),
            'lock_version' => ((int) $request->input('lock_version', 1)) + 1,
            'source_template_id' => $request->input('source_template_id'),
            'state' => $request->input('state', []),
        ];
        Cache::put($this->draftCacheKey($surface, $userId, $draft), $payload, now()->addDays(7));

        return response()->json(['draft' => $payload], 200);
    }

    public function destroyDraft(Request $request, CapabilityAuthorizer $authorizer, string $surface, string $draft): JsonResponse
    {
        $user = $this->authorizeSurface($authorizer, $request->user(), $surface);
        $userId = (string) $user->getAuthIdentifier();
        Cache::forget($this->draftCacheKey($surface, $userId, $draft));

        return response()->json([], 204);
    }

    private function draftCacheKey(string $surface, string $userId, string $draftId): string
    {
        return "report_draft_{$surface}_{$userId}_{$draftId}";
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveDraftState(Request $request, string $surface, ReportTemplate $reportTemplate): ?array
    {
        $userId = (string) $request->user()?->getAuthIdentifier();
        $defaultDraftId = 'report_draft_'.$surface.'_'.$userId.'_'.$reportTemplate->id;
        $draftId = $request->filled('draft')
            ? (string) $request->input('draft')
            : $defaultDraftId;

        $cached = Cache::get($this->draftCacheKey($surface, $userId, $draftId));
        if (is_array($cached)) {
            return $cached;
        }
        if ($draftId !== $defaultDraftId) {
            $defaultCached = Cache::get($this->draftCacheKey($surface, $userId, $defaultDraftId));
            if (is_array($defaultCached)) {
                return $defaultCached;
            }
        }

        if (! $request->filled('draft')) {
            return null;
        }

        return [
            'id' => $draftId,
            'stage' => 1,
            'lock_version' => 1,
            'state' => [
                'name' => $reportTemplate->name,
                'mode' => $reportTemplate->mode,
                'paper_size' => $reportTemplate->paper_size,
                'orientation' => $reportTemplate->orientation,
                'content' => $reportTemplate->content,
                'builder_json' => $reportTemplate->builder_json,
                'is_active' => $reportTemplate->is_active,
                'mounted_slots' => $reportTemplate->mounted_slots ?? ['analytics_reports_hub'],
                'custom_group' => $reportTemplate->custom_group,
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveCreateDraftState(Request $request, string $surface): ?array
    {
        $userId = (string) $request->user()?->getAuthIdentifier();
        $office = $request->user()?->actorScope()->office;
        $defaultDraftId = $surface === 'custom_document'
            ? "report_draft_custom_document_{$office}_{$userId}_new"
            : 'report_draft_'.$surface.'_'.$userId.'_new';
        $draftId = $request->filled('draft') ? (string) $request->input('draft') : $defaultDraftId;

        $cached = Cache::get($this->draftCacheKey($surface, $userId, $draftId));

        return is_array($cached) ? $cached : null;
    }

    public function activate(Request $request, CapabilityAuthorizer $authorizer, string $surface, ReportTemplate $reportTemplate, RecordAuditEvent $audit): SymfonyResponse
    {
        $this->assertTemplateSurface($reportTemplate, $surface);
        $user = $this->authorizeSurface($authorizer, $request->user(), $surface);
        $this->assertTemplateOffice($reportTemplate, $user);
        DB::transaction(function () use ($request, $surface, $reportTemplate, $audit, $user): void {
            if ($surface !== 'custom_document') {
                ReportTemplate::query()->where('surface_key', $surface)->lockForUpdate()->get();
            }
            $template = ReportTemplate::query()->whereKey($reportTemplate->getKey())->lockForUpdate()->firstOrFail();
            if ($request->has('expected_lock_version') && $template->lock_version !== (int) $request->input('expected_lock_version')) {
                abort(409, 'Conflict: This template was updated by another user.');
            }
            if ($surface !== 'custom_document') {
                ReportTemplate::query()->where('surface_key', $surface)->where('id', '!=', $template->id)->update(['is_active' => false]);
            }
            $template->forceFill([
                'is_active' => true,
                'lock_version' => (int) $template->lock_version + 1,
            ])->save();
            $audit->handle('report_template.activated', ['template_id' => $template->id, 'surface_key' => $surface], (string) $user->getAuthIdentifier());
        });

        $redirectRoute = $request->input('return_to') === 'hub'
            ? route('setup.report-templates.index')
            : route('setup.report-templates.surface', ['surface' => $surface]);

        return $request->wantsJson() ? response()->noContent() : redirect()->to($redirectRoute)->with('status', 'Report template activated.');
    }

    public function deactivate(Request $request, CapabilityAuthorizer $authorizer, string $surface, ReportTemplate $reportTemplate, RecordAuditEvent $audit): SymfonyResponse
    {
        $this->assertTemplateSurface($reportTemplate, $surface);
        $user = $this->authorizeSurface($authorizer, $request->user(), $surface);
        $this->assertTemplateOffice($reportTemplate, $user);
        DB::transaction(function () use ($reportTemplate, $surface, $audit, $user): void {
            $reportTemplate->forceFill(['is_active' => false])->save();
            $audit->handle('report_template.deactivated', ['template_id' => $reportTemplate->id, 'surface_key' => $surface], (string) $user->getAuthIdentifier());
        });

        $redirectRoute = $request->input('return_to') === 'hub'
            ? route('setup.report-templates.index')
            : route('setup.report-templates.surface', ['surface' => $surface]);

        return $request->wantsJson() ? response()->noContent() : redirect()->to($redirectRoute)->with('status', 'Report template deactivated.');
    }

    public function destroy(Request $request, CapabilityAuthorizer $authorizer, string $surface, ReportTemplate $reportTemplate, RecordAuditEvent $audit): SymfonyResponse
    {
        $this->assertTemplateSurface($reportTemplate, $surface);
        $user = $this->authorizeSurface($authorizer, $request->user(), $surface);
        $this->assertTemplateOffice($reportTemplate, $user);
        DB::transaction(function () use ($reportTemplate, $surface, $audit, $user): void {
            $reportTemplate->delete();
            $audit->handle('report_template.deleted', ['template_id' => $reportTemplate->id, 'surface_key' => $surface], (string) $user->getAuthIdentifier());
        });
        if ($reportTemplate->document_path !== null) {
            Storage::disk('local')->delete($reportTemplate->document_path);
        }
        app(DocumentThumbnailService::class)->purge($surface, $reportTemplate->id);

        $redirectRoute = $request->input('return_to') === 'hub'
            ? route('setup.report-templates.index')
            : route('setup.report-templates.surface', ['surface' => $surface]);

        return $request->wantsJson() ? response()->noContent() : redirect()->to($redirectRoute)->with('status', 'Report template deleted.');
    }

    public function thumbnail(
        Request $request,
        CapabilityAuthorizer $authorizer,
        string $surface,
        ReportTemplate $reportTemplate,
        DocumentThumbnailService $thumbnailService,
        MediaStreamResponder $responder
    ): SymfonyResponse {
        $this->assertTemplateSurface($reportTemplate, $surface);
        abort_unless(DocumentSurfaceRegistry::canViewSurface($authorizer, $request->user(), $surface), 403);
        $user = $request->user();
        abort_unless($user !== null, 401);
        $this->assertTemplateOffice($reportTemplate, $user);

        $fingerprint = $thumbnailService->fingerprint($reportTemplate->sourceChecksum());
        $requestedVersion = $request->query('v');
        if ($requestedVersion !== null && $requestedVersion !== $fingerprint) {
            abort(404);
        }

        $cached = $thumbnailService->read($surface, $reportTemplate->id, $fingerprint);
        if ($cached === null) {
            try {
                RenderReportTemplateThumbnailJob::dispatchSync(
                    $surface,
                    $reportTemplate->id,
                    $reportTemplate->sourceChecksum(),
                    $fingerprint
                );
                $cached = $thumbnailService->read($surface, $reportTemplate->id, $fingerprint);
            } catch (\Throwable) {
                // If on-demand render fails, return 404
            }
        }

        if ($cached !== null) {
            $cacheControl = $requestedVersion === $fingerprint
                ? 'private, max-age=31536000, immutable'
                : 'private, no-cache';

            return $responder->inline($request, $cached, 'image/png', $cacheControl, "report-template-{$reportTemplate->id}.png");
        }

        return response('', 404, ['Cache-Control' => 'private, no-store']);
    }

    private function decodeBuilderInput(Request $request): void
    {
        $builder = $request->input('builder_json');
        if (is_string($builder) && trim($builder) !== '') {
            $decoded = json_decode($builder, true);
            if (json_last_error() === JSON_ERROR_NONE && (is_array($decoded) || $decoded === null)) {
                $request->merge(['builder_json' => $decoded]);
                $request->request->set('builder_json', $decoded);
            }
        }

        if ($request->has('mounted_slots')) {
            $rawSlots = $request->input('mounted_slots');
            if (is_string($rawSlots)) {
                $trimmed = trim($rawSlots);
                if ($trimmed === '') {
                    $request->merge(['mounted_slots' => []]);
                    $request->request->set('mounted_slots', []);
                } else {
                    $decodedSlots = json_decode($trimmed, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decodedSlots)) {
                        $request->merge(['mounted_slots' => $decodedSlots]);
                        $request->request->set('mounted_slots', $decodedSlots);
                    }
                }
            } elseif ($rawSlots === null) {
                $request->merge(['mounted_slots' => []]);
                $request->request->set('mounted_slots', []);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $builder
     * @return array{html: string, document: array<string, mixed>, diagnostics: list<array<string, mixed>>}
     */
    private function compileBuilder(
        DocumentBlueprintCompiler $compiler,
        array $builder,
        DocumentSurfaceInterface $surface
    ): array {
        $document = ResultSheetBuilderSchema::normalize($builder);
        $catalog = $surface->placeholderCatalog();
        $diagnostics = ResultSheetBuilderSchema::diagnostics(
            $document,
            null,
            is_array($catalog['all'] ?? null) ? $catalog['all'] : []
        );
        $compiled = $compiler->compile($document);
        if (is_array($compiled)) {
            $diagnostics = array_merge($diagnostics, $compiled['diagnostics'] ?? []);

            return [
                'html' => (string) ($compiled['html'] ?? ''),
                'document' => is_array($compiled['document'] ?? null) ? $compiled['document'] : $document,
                'diagnostics' => $diagnostics,
            ];
        }

        return ['html' => $compiled, 'document' => $document, 'diagnostics' => $diagnostics];
    }

    private function pdfIframe(string $pdfBytes): string
    {
        return '<iframe title="Document preview" src="data:application/pdf;base64,'.base64_encode($pdfBytes).'" style="width:100%;height:100%;border:0"></iframe>';
    }

    /**
     * @param  list<string>  $allowedLeaves
     * @return array<string, list<array{placeholder: string, description: string, label?: string, example?: string, aliases?: list<string>}|string>>
     */
    private function placeholderGroups(DocumentSurfaceInterface $surface, array $allowedLeaves): array
    {
        $groups = ['institution' => [], 'summary' => [], 'applicant' => [], 'table_rows' => [], 'signatories' => []];
        $catalog = $surface->placeholderCatalog();
        $all = is_array($catalog['all'] ?? null) ? $catalog['all'] : [];
        $institution = ['institution_name', 'institution_code', 'academic_cycle', 'report_date'];
        $summary = ['cutoff_score', 'total_examinees', 'total_passed', 'passing_rate', 'target_quota', 'accepted_count', 'remaining_slots', 'fill_rate'];
        $signatories = ['prepared_by', 'reviewed_by', 'verified_by', 'approved_by'];

        foreach ($all as $key => $label) {
            if (! is_string($key)) {
                if (! is_string($label)) {
                    continue;
                }
                $key = $label;
                $description = $label;
            } else {
                $description = is_string($label) ? $label : $key;
            }

            $group = str_contains($key, '.')
                ? 'table_rows'
                : (in_array($key, $institution, true)
                    ? 'institution'
                    : (in_array($key, $summary, true)
                        ? 'summary'
                        : (in_array($key, $signatories, true) ? 'signatories' : null)));
            if ($group !== null) {
                $groups[$group][] = ['placeholder' => $key, 'description' => $description];
            }
        }

        if (in_array('last_school_enrolled', $allowedLeaves, true)) {
            $groups['applicant'] = [
                [
                    'placeholder' => 'last_school_enrolled',
                    'label' => 'Last school — full name',
                    'description' => 'Original applicant-entered school name.',
                    'example' => 'Ilocos Sur College',
                ],
                [
                    'placeholder' => 'last_school_short',
                    'label' => 'Last school — abbreviation',
                    'description' => 'Known abbreviation, otherwise generated initials.',
                    'example' => 'Ilocos Sur College → ISC',
                    'aliases' => ['school_short'],
                ],
                [
                    'placeholder' => 'strand_or_prev_course',
                    'label' => 'Strand / previous course — full text',
                    'description' => 'Original saved academic-history text.',
                    'example' => 'Humanities and Social Sciences',
                ],
                [
                    'placeholder' => 'strand_or_prev_course_short',
                    'label' => 'Strand / previous course — abbreviation',
                    'description' => 'Known abbreviation, otherwise generated initials.',
                    'example' => 'Humanities and Social Sciences → HUMSS',
                    'aliases' => ['history_short'],
                ],
                [
                    'placeholder' => 'strand',
                    'label' => 'Strand — original text',
                    'description' => 'Existing raw strand / previous-course value.',
                    'example' => 'Humanities and Social Sciences',
                ],
            ];
        }

        $groups['Pagination'] = [
            ['placeholder' => 'page_i', 'description' => 'Current page number (1-based)'],
            ['placeholder' => 'page_all', 'description' => 'Total page count of the document'],
            ['placeholder' => 'pg_i', 'description' => 'Current page number alias (ISPSC QAD-F032a)'],
            ['placeholder' => 'pg_all', 'description' => 'Total page count alias (ISPSC QAD-F032a)'],
        ];

        return $groups;
    }

    /**
     * @param  list<string>  $allowedLeaves
     * @return array<string, mixed>
     */
    private function placeholderCatalogFor(DocumentSurfaceInterface $surface, array $allowedLeaves): array
    {
        $catalog = $surface->placeholderCatalog();
        $studentOnly = [
            'last_school_enrolled',
            'last_school_short',
            'school_short',
            'strand_or_prev_course',
            'strand_or_prev_course_short',
            'history_short',
        ];

        $policy = app(DocumentFieldPolicy::class);
        foreach (['required', 'recommended', 'all'] as $key) {
            if (! is_array($catalog[$key] ?? null)) {
                continue;
            }
            $catalog[$key] = array_filter(
                $catalog[$key],
                function (mixed $value) use ($allowedLeaves, $studentOnly, $policy): bool {
                    $reference = is_string($value) ? $value : '';
                    if (! in_array($reference, $studentOnly, true)) {
                        return true;
                    }
                    $canonical = $policy->canonicalReferenceLeafId($reference);

                    return $canonical !== null && in_array($canonical, $allowedLeaves, true);
                },
            );
        }
        if (is_array($catalog['aliases'] ?? null)) {
            $catalog['aliases'] = array_filter(
                $catalog['aliases'],
                static fn (mixed $canonical): bool => is_string($canonical)
                    && in_array($canonical, $allowedLeaves, true),
            );
        }

        return $catalog;
    }

    /**
     * @param  list<string>  $allowedLeaves
     * @return array<string, mixed>
     */
    private function sampleContextFor(DocumentSurfaceInterface $surface, array $allowedLeaves): array
    {
        $context = $surface->sampleContext();
        if (! in_array('last_school_enrolled', $allowedLeaves, true)) {
            return $context;
        }

        return array_replace($context, TemplateShortValue::fields('Ilocos Sur College', 'Humanities and Social Sciences'), [
            'strand' => 'Humanities and Social Sciences',
            'strand_or_prev_course' => 'Humanities and Social Sciences',
        ]);
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    private function surfaceData(array $definition, DocumentSurfaceInterface $surface): array
    {
        return [
            'surface_key' => $definition['surface_key'],
            'title' => $surface->title(),
            'office' => $definition['office'],
            'office_label' => $definition['office_label'],
            'layout_paradigm' => $surface->layoutParadigm()->value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function activePack(string $surfaceKey, DocumentSurfaceInterface $surface): array
    {
        $packMeta = method_exists(OfficeDocumentPackCatalog::class, 'forSurface')
            ? OfficeDocumentPackCatalog::forSurface($surfaceKey)
            : [];
        $definition = DocumentSurfaceRegistry::definition($surfaceKey);
        $office = $definition['office'];
        $grain = $packMeta['grain'] ?? (in_array($surfaceKey, ['admission_slip', 'clearance_slip', 'registrar.admission_slip', 'registrar.clearance_slip'], true) ? 'student' : 'cycle_program');
        $grainLabel = $packMeta['grain_label'] ?? MountSlotRegistry::grainLabel($grain);
        $compatibleSlots = MountSlotRegistry::forOffice($office);
        $defaultMountedSlots = $packMeta['default_mounted_slots'] ?? ['analytics_reports_hub'];

        $components = $surface->defaultBlueprint()['components'] ?? [];
        $kpi = collect($components)->firstWhere('type', 'kpi_grid');
        $table = collect($components)->firstWhere('type', 'data_table');

        $title = $surfaceKey === 'course_quota_qualifiers'
            ? 'Registrar — Course Quota Qualifiers Pack'
            : ($packMeta['title'] ?? $surface->title());
        $officeLabel = $surfaceKey === 'course_quota_qualifiers'
            ? 'Registrar Office'
            : ($packMeta['office_label'] ?? $definition['office_label'] ?? 'Office');
        $repeatKey = $surfaceKey === 'course_quota_qualifiers'
            ? 'qualifiers'
            : ($packMeta['repeat_key'] ?? $surface->repeatingRowKey() ?? 'rows');
        $includedPrefix = $surfaceKey === 'course_quota_qualifiers'
            ? 'Registrar — Course Quota Qualifiers'
            : $title;

        return [
            'key' => $packMeta['key'] ?? $surfaceKey,
            'pack_key' => $packMeta['pack_key'] ?? $packMeta['key'] ?? $surfaceKey,
            'title' => $title,
            'office' => $office,
            'office_key' => $office,
            'office_label' => $officeLabel,
            'repeat_key' => $repeatKey,
            'grain' => $grain,
            'grain_label' => $grainLabel,
            'purpose' => $packMeta['purpose'] ?? 'analytics',
            'compatible_slots' => $compatibleSlots,
            'default_mounted_slots' => $defaultMountedSlots,
            'included_packs' => [
                $includedPrefix,
                'Institution Identity',
                'Academic Cycle',
                'Multi-Page Pagination',
            ],
            'columns' => $table['props']['columns'] ?? ($packMeta['columns'] ?? []),
            'metrics' => $kpi['props']['metrics'] ?? ($packMeta['metrics'] ?? []),
        ];
    }

    private function authorizeSurface(CapabilityAuthorizer $authorizer, ?User $user, string $surface): User
    {
        abort_unless(DocumentSurfaceRegistry::has($surface), 404);
        abort_unless(DocumentSurfaceRegistry::canAuthorSurface($authorizer, $user, $surface), 403);
        abort_unless($user !== null, 401);

        return $user;
    }

    private function assertTemplateSurface(ReportTemplate $reportTemplate, string $surface): void
    {
        abort_unless($reportTemplate->surface_key === $surface, 404);
    }

    private function assertTemplateOffice(ReportTemplate $reportTemplate, User $user): void
    {
        if ($reportTemplate->surface_key === 'custom_document') {
            abort_unless(
                $reportTemplate->office === null || $reportTemplate->office === $user->actorScope()->office,
                404,
            );
        }
    }
}
