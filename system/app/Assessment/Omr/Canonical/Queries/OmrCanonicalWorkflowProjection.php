<?php

namespace App\Assessment\Omr\Canonical\Queries;

use App\Assessment\Actions\Omr\Canonical\DiscardOmrSandboxDraft;
use App\Assessment\Omr\Canonical\OmrCanonicalGeometryIdentity;
use App\Assessment\Omr\Canonical\OmrCanonicalMappingGuideGeometry;
use App\Assessment\Omr\Canonical\OmrNoGoMatrixRunner;
use App\Assessment\Omr\Canonical\OmrPageDimensionMath;
use App\Assessment\Omr\Canonical\OmrSourcePolicy;
use App\Assessment\Omr\OmrRegistrationMode;
use App\Assessment\Omr\Verification\CurrentOmrDigitalVerification;
use App\Assessment\Omr\Verification\OmrPhysicalAssuranceStatus;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrMarkerProfile;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\Assessment\OmrTemplateVerificationRun;
use App\Models\Assessment\TestInstrumentVersion;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Support\Facades\DB;

use function array_flip;
use function count;
use function in_array;
use function is_array;
use function is_string;
use function max;
use function min;
use function round;
use function route;
use function sprintf;

final class OmrCanonicalWorkflowProjection
{
    private const STUDIO_APTITUDE_CODES = [
        'LOGIC',
        'VERBAL',
        'QUANT',
        'SPATIAL',
        'MEMORY',
        'LANGUAGE',
    ];

    public function __construct(
        private readonly ?CurrentOmrDigitalVerification $digitalVerification = null,
        private readonly ?OmrPhysicalAssuranceStatus $physicalAssurance = null,
        private readonly ?DiscardOmrSandboxDraft $discardAction = null,
    ) {}

    /**
     * @return array{can_unlock: bool, reason: ?string}
     */
    public static function evaluateUnlockEligibility(
        OmrTemplateAuthoringDraft $draft,
        ?OmrAnswerSheetTemplateVersion $compiledVersion,
        User $actor,
    ): array {
        if ((int) $draft->owner_user_id !== (int) $actor->id
            || $draft->institution_id === null
            || $draft->is_synthetic === null) {
            return ['can_unlock' => false, 'reason' => 'Only the classified draft owner may unlock this draft.'];
        }
        if ($draft->stage !== OmrTemplateAuthoringDraft::STAGE_COMPILED && $draft->compiled_version_id === null) {
            return ['can_unlock' => false, 'reason' => 'Only compiled drafts can be unlocked.'];
        }
        if ($compiledVersion === null) {
            return ['can_unlock' => true, 'reason' => null];
        }
        if ($compiledVersion->status === OmrAnswerSheetTemplateVersion::STATUS_ACTIVE || $compiledVersion->activated_at !== null) {
            return ['can_unlock' => false, 'reason' => 'Version is already activated for production use. Create an editable copy instead.'];
        }

        $versionId = (int) $compiledVersion->id;
        foreach ([
            ['test_kit_versions', 'template_version_id', 'Used by a Test Kit version'],
            ['omr_template_booklet_pairs', 'template_version_id', 'Paired with a Question Booklet'],
            ['omr_question_booklet_versions', 'template_version_id', 'Bound to a Question Booklet version'],
            ['exam_session_material_snapshots', 'template_version_id', 'Used by an Exam Session snapshot'],
            ['omr_capture_batches', 'template_version_id', 'Used by an OMR capture batch'],
        ] as [$table, $column, $reason]) {
            if (DB::table($table)->where($column, $versionId)->exists()) {
                return ['can_unlock' => false, 'reason' => $reason];
            }
        }
        foreach (['omr_pending_capture_sets', 'omr_qr_disabled_issuances', 'omr_supplier_stock_lots'] as $table) {
            if (DB::table($table)->where('template_version_id', $versionId)->exists()) {
                return ['can_unlock' => false, 'reason' => 'Referenced by OMR stock or capture issuances'];
            }
        }

        return ['can_unlock' => true, 'reason' => null];
    }

    /**
     * @return array<string, mixed>
     */
    public function indexProps(User $actor): array
    {
        $discarder = $this->discardAction ?? app(DiscardOmrSandboxDraft::class);
        $drafts = OmrTemplateAuthoringDraft::query()
            ->with(['owner:id,name', 'compiledVersion.template'])
            ->withExists('verificationRuns')
            ->orderByDesc('updated_at')
            ->get()
            ->map(function (OmrTemplateAuthoringDraft $draft) use ($actor, $discarder): array {
                $readOnlyReason = $draft->institution_id === null || $draft->is_synthetic === null || $draft->owner_user_id === null
                    ? 'Unclassified legacy draft (Read-Only)'
                    : ($draft->compiled_version_id !== null || $draft->stage === OmrTemplateAuthoringDraft::STAGE_COMPILED
                        ? 'Compiled template version (Immutable)'
                        : null);
                $canEdit = $readOnlyReason === null
                    && (int) $draft->owner_user_id === (int) $actor->id;
                $canDiscard = $discarder->isDiscardable($draft, $actor);
                $compiledVersion = $draft->compiledVersion;
                $authoring = (array) $draft->authoring_json;
                $isOwner = (int) $draft->owner_user_id === (int) $actor->id;
                $canViewPreview = $isOwner
                    || $draft->stage === OmrTemplateAuthoringDraft::STAGE_COMPILED
                    || $draft->compiled_version_id !== null;
                $persistedPreview = $canViewPreview
                    ? $this->resolvePersistedPreview($draft, $authoring)
                    : null;
                $pageCount = count((array) data_get($authoring, 'canonical.raster_assets', []));
                if ($pageCount === 0) {
                    $pageCount = count((array) ($authoring['pages'] ?? []));
                }

                return [
                    'id' => $draft->id,
                    'code' => $draft->code,
                    'name' => $this->staffFacingDraftName($draft),
                    'stage' => $draft->stage,
                    'can_edit' => $canEdit,
                    'read_only_reason' => $readOnlyReason,
                    'can_discard' => $canDiscard,
                    'destroy_url' => $canDiscard ? route('setup.exam-materials.answer-sheets.destroy', $draft) : null,
                    'compiled_version_id' => $draft->compiled_version_id,
                    'compiled_version_number' => $compiledVersion?->version,
                    'compiled_version_status' => $compiledVersion?->status,
                    'is_active_version' => $compiledVersion !== null && $compiledVersion->status === OmrAnswerSheetTemplateVersion::STATUS_ACTIVE,
                    'owner_name' => $draft->owner?->name,
                    'updated_at' => $draft->updated_at?->toIso8601String(),
                    'url' => route('setup.exam-materials.answer-sheets.edit', $draft),
                    'preview_url' => data_get($persistedPreview, 'artifact.canonical_url'),
                    'preview_page_count' => max($pageCount, $persistedPreview === null ? 0 : 1),
                    'authoring_method' => match (data_get($authoring, 'studio.stage1Mode')) {
                        'html' => 'Sheet Designer',
                        'upload' => 'Uploaded document',
                        default => 'Mode not chosen',
                    },
                ];
            })
            ->values()
            ->all();

        $draftsByCompiledVersionId = collect($drafts)
            ->filter(static fn (array $draft): bool => $draft['compiled_version_id'] !== null)
            ->keyBy('compiled_version_id');
        $readyAnswerSheets = OmrAnswerSheetTemplateVersion::query()
            ->whereHas('template', fn ($query) => $query->where('institution_id', Institution::current()->id))
            ->whereIn('status', [
                OmrAnswerSheetTemplateVersion::STATUS_COMPILED,
                OmrAnswerSheetTemplateVersion::STATUS_ACTIVE,
            ])
            ->with('template:id,name')
            ->orderByDesc('id')
            ->get()
            ->map(function (OmrAnswerSheetTemplateVersion $version) use ($draftsByCompiledVersionId): array {
                $draft = $draftsByCompiledVersionId->get((int) $version->id);

                return [
                    'id' => (int) $version->id,
                    'template_id' => (int) $version->template_id,
                    'name' => (string) ($version->template?->name ?? 'Answer Sheet'),
                    'version' => (int) $version->version,
                    'status' => (string) $version->status,
                    'registration_mode' => $version->registration_mode?->value,
                    'qr_mode' => $version->qr_mode?->value,
                    'page_count' => $version->pageCount(),
                    'draft_id' => $draft['id'] ?? null,
                    'preview_url' => route('setup.omr.templates.preview', ['version' => $version->id], absolute: false),
                    'inspection_url' => $draft['url'] ?? route('setup.omr.templates.native_template', ['templateVersion' => $version->id], absolute: false),
                    'export_url' => route('setup.exam-materials.answer-sheets.export.version', ['templateVersion' => $version->id], absolute: false),
                ];
            })
            ->values()
            ->all();

        return [
            'drafts' => $drafts,
            'ready_answer_sheets' => $readyAnswerSheets,
            'create_url' => route('setup.exam-materials.answer-sheets.store'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function workflowProps(OmrTemplateAuthoringDraft $draft, User $actor): array
    {
        $readOnlyReason = $draft->institution_id === null || $draft->is_synthetic === null || $draft->owner_user_id === null
            ? 'Unclassified legacy draft (Read-Only)'
            : ($draft->compiled_version_id !== null || $draft->stage === OmrTemplateAuthoringDraft::STAGE_COMPILED
                ? 'Compiled template version (Immutable)'
                : null);
        $canEdit = $readOnlyReason === null
            && (int) $draft->owner_user_id === (int) $actor->id;
        $authoring = (array) $draft->authoring_json;
        $rasterAssetId = (int) (($authoring['canonical']['raster_assets'][0]['asset_id'] ?? null) ?: ($authoring['pages'][0]['asset_id'] ?? 0));
        $isUploaded = OmrSourcePolicy::sourceKindForDraft($draft) === OmrSourcePolicy::UPLOADED_ORIGINAL;
        $printPdfAssetId = $isUploaded ? 0 : (int) ($authoring['canonical']['pdf_asset_id'] ?? 0);
        $persistedPreview = $this->resolvePersistedPreview($draft, $authoring);
        $compiledVersion = $draft->compiled_version_id !== null
            ? OmrAnswerSheetTemplateVersion::query()->find($draft->compiled_version_id)
            : null;
        $unlockEligibility = self::evaluateUnlockEligibility($draft, $compiledVersion, $actor);
        $verificationAuthority = $this->digitalVerification ?? app(CurrentOmrDigitalVerification::class);
        $matchingVerification = $verificationAuthority->find($draft);
        $digitalVerified = $matchingVerification !== null;
        $currentTarget = $this->currentTarget($draft, $authoring, $compiledVersion);
        $targetCurrent = $currentTarget['has_draft_target'] || $currentTarget['version_id'] !== null;
        $mappingCurrent = in_array((string) $draft->stage, [
            OmrTemplateAuthoringDraft::STAGE_MAPPING_COMPLETE,
            OmrTemplateAuthoringDraft::STAGE_DIGITAL_VERIFIED,
            OmrTemplateAuthoringDraft::STAGE_COMPILED,
        ], true);
        $verificationRun = $this->resolveVerificationRunSummary($draft, $authoring, $compiledVersion, $digitalVerified);
        $physicalRun = $this->latestCurrentPhysicalRun($draft, $currentTarget);
        $physicalRunId = $physicalRun?->id !== null ? (int) $physicalRun->id : null;
        $physicalQualification = ($this->physicalAssurance ?? app(OmrPhysicalAssuranceStatus::class))
            ->get($physicalRun, $compiledVersion);
        $discarder = $this->discardAction ?? app(DiscardOmrSandboxDraft::class);
        $canDiscard = $discarder->isDiscardable($draft, $actor);
        $studioAreaOrder = array_flip(self::STUDIO_APTITUDE_CODES);

        $studioAptitudeAreas = AptitudeArea::query()
            ->active()
            ->whereIn('code', self::STUDIO_APTITUDE_CODES)
            ->get(['id', 'code', 'name', 'max_items'])
            ->sortBy(fn (AptitudeArea $area): int => $studioAreaOrder[$area->code] ?? PHP_INT_MAX)
            ->values()
            ->map(fn (AptitudeArea $area): array => [
                'id' => $area->id,
                'code' => $area->code,
                'name' => $area->name,
                'max_items' => $area->max_items,
            ])
            ->all();
        $testInstrument = null;
        if ($draft->test_instrument_version_id !== null) {
            $version = TestInstrumentVersion::query()
                ->with(['instrument:id,code', 'subtests.items'])
                ->find($draft->test_instrument_version_id);
            if ($version !== null) {
                $testInstrument = [
                    'id' => (int) $version->id,
                    'code' => $version->instrument?->code,
                    'display_name' => $version->display_name,
                    'subtests' => $version->subtests->map(static fn ($subtest): array => [
                        'id' => (int) $subtest->id,
                        'code' => $subtest->code,
                        'name' => $subtest->name,
                        'response_mode' => $subtest->response_mode,
                        'item_order_policy' => $subtest->item_order_policy,
                        'items' => $subtest->items->map(static fn ($item): array => [
                            'id' => (int) $item->id,
                            'item_code' => $item->item_code,
                            'local_number' => $item->local_number,
                            'is_scored' => (bool) $item->is_scored,
                            'option_tokens' => $item->option_tokens ?? [],
                        ])->all(),
                    ])->all(),
                ];
            }
        }
        $orderedPages = collect((array) ($authoring['pages'] ?? []))
            ->sortBy('page_no')
            ->values()
            ->map(function (array $page) use ($draft): array {
                $assetId = (int) ($page['asset_id'] ?? 0);
                if ($assetId > 0) {
                    $page['raster_url'] = route('setup.omr.templates.canonical.drafts.assets.show', [$draft, $assetId]);
                }

                return $page;
            })
            ->all();
        if ($orderedPages === []) {
            $orderedPages = collect(range(1, (int) $draft->requested_page_count))
                ->map(static fn (int $pageNo): array => [
                    'page_no' => $pageNo,
                    'kind' => 'blank',
                    'confirmed' => true,
                ])
                ->all();
        }
        $markerProfiles = $draft->registration_mode === OmrRegistrationMode::FiducialAnchored
            ? OmrMarkerProfile::query()
                ->whereIn('status', [OmrMarkerProfile::STATUS_CANDIDATE, OmrMarkerProfile::STATUS_QUALIFIED])
                ->where(function ($query) use ($draft): void {
                    $query->whereKey($draft->marker_profile_id)
                        ->orWhere('code', 'omrchecker.sample')
                        ->orWhere('status', OmrMarkerProfile::STATUS_QUALIFIED);
                })
                ->orderByRaw('case when id = ? then 0 else 1 end', [$draft->marker_profile_id ?? 0])
                ->orderBy('code')
                ->get(['id', 'code', 'version', 'family', 'status', 'size_mm', 'quiet_zone_mm', 'asset_sha256'])
                ->values()
                ->all()
            : [];

        return [
            'page_state' => 'ready',
            'draft' => [
                'id' => $draft->id,
                'can_edit' => $canEdit,
                'read_only_reason' => $readOnlyReason,
                'code' => $draft->code,
                'name' => $this->staffFacingDraftName($draft),
                'source_kind' => $draft->source_kind,
                'registration_mode' => $draft->registration_mode->value,
                'qr_mode' => $draft->qr_mode->value,
                'requested_page_count' => (int) $draft->requested_page_count,
                'stage' => $draft->stage,
                'lock_version' => $draft->lock_version,
                'target_current' => $targetCurrent,
                'mapping_current' => $mappingCurrent,
                'digital_verification_current' => $digitalVerified,
                'digital_verified' => $digitalVerified,
                'matching_verification_run_id' => $matchingVerification?->run->id,
                'matching_verification' => $matchingVerification !== null ? [
                    'run_id' => $matchingVerification->run->id,
                    'artifact_hash' => $matchingVerification->artifactHash,
                    'status' => 'passed',
                ] : null,
                'marker_profile_id' => $draft->marker_profile_id,
                'compiled_version_id' => $draft->compiled_version_id,
                'compiled_version_status' => $compiledVersion?->status,
                'software_verification_status' => (string) ($physicalQualification['software_verification_status'] ?? 'held'),
                'physical_qualification_status' => (string) ($physicalQualification['physical_status'] ?? 'held'),
                'physical_qualified' => (bool) ($physicalQualification['qualified'] ?? false),
                'can_create_editable_copy' => (int) $draft->owner_user_id === (int) $actor->id
                    && $draft->institution_id !== null
                    && ($draft->compiled_version_id !== null || $draft->stage === OmrTemplateAuthoringDraft::STAGE_COMPILED),
                'can_unlock_to_edit' => $unlockEligibility['can_unlock'],
                'unlock_block_reason' => $unlockEligibility['reason'],
                'authoring' => $draft->authoring_json,
                'ordered_pages' => $orderedPages,
                'raster_url' => $rasterAssetId > 0
                    ? route('setup.omr.templates.canonical.drafts.assets.show', [$draft, $rasterAssetId])
                    : null,
                'print_pdf_url' => $printPdfAssetId > 0
                    ? route('setup.omr.templates.canonical.drafts.assets.show', [$draft, $printPdfAssetId])
                    : null,
                'persisted_preview' => $persistedPreview,
                'original_photo_url' => $draft->source_kind === OmrTemplateAuthoringDraft::SOURCE_IMAGE
                    && (int) ($authoring['source']['metadata']['original_asset_id'] ?? 0) > 0
                    ? route('setup.omr.templates.canonical.drafts.assets.show', [$draft, (int) $authoring['source']['metadata']['original_asset_id']])
                    : null,
                'original_photo_urls' => $draft->source_kind === OmrTemplateAuthoringDraft::SOURCE_IMAGE
                    ? (collect((array) ($authoring['pages'] ?? []))
                        ->filter(fn (mixed $p): bool => is_array($p) && (int) ($p['original_asset_id'] ?? 0) > 0)
                        ->mapWithKeys(fn (array $p): array => [
                            (int) $p['page_no'] => route('setup.omr.templates.canonical.drafts.assets.show', [$draft, (int) $p['original_asset_id']]),
                        ])->all()
                        ?: collect((array) ($authoring['source']['metadata']['original_asset_ids'] ?? []))
                            ->filter(fn (mixed $id): bool => (int) $id > 0)
                            ->mapWithKeys(fn (int $assetId, int|string $pageNo) => [
                                (int) $pageNo => route('setup.omr.templates.canonical.drafts.assets.show', [$draft, $assetId]),
                            ])->all())
                    : [],
                'can_discard' => $canDiscard,
            ],
            'physical_run_id' => $physicalRunId,
            'verification_run' => $verificationRun,
            'marker_profiles' => $markerProfiles,
            'aptitude_areas' => $studioAptitudeAreas,
            'test_instrument' => $testInstrument,
            'stages' => [
                'draft',
                'convert_inspect',
                'page_prepare',
                'overlay',
                'map',
                'digital_verify',
                'compile',
                ...($isUploaded ? [] : ['blank_print']),
            ],
            'urls' => [
                'editable_copy' => route('setup.exam-materials.answer-sheets.editable-copy', $draft, absolute: false),
                'unlock_draft' => route('setup.exam-materials.answer-sheets.unlock', $draft, absolute: false),
                'source_ingest' => route('setup.omr.templates.canonical.drafts.source.ingest', $draft, absolute: false),
                'source_rectify' => route('setup.omr.templates.canonical.drafts.source.rectify', $draft, absolute: false),
                'confirm_pages' => route('setup.omr.templates.canonical.drafts.source.pages.confirm', $draft, absolute: false),
                'pages' => route('setup.omr.templates.canonical.drafts.pages.update', $draft, absolute: false),
                'compose' => route('setup.omr.templates.canonical.drafts.compose', $draft, absolute: false),
                'source_run' => route('setup.omr.templates.canonical.drafts.source.run', $draft, absolute: false),
                'source_cancel' => route('setup.omr.templates.canonical.drafts.source.cancel', $draft, absolute: false),
                'overlay' => route('setup.omr.templates.canonical.drafts.overlay', $draft, absolute: false),
                'mapping' => route('setup.omr.templates.canonical.drafts.mapping', $draft, absolute: false),
                'studio' => route('setup.exam-materials.answer-sheets.update', $draft, absolute: false),
                'materialize' => route('setup.omr.templates.canonical.drafts.materialize', $draft, absolute: false),
                'preview' => route('setup.omr.templates.canonical.drafts.preview', $draft, absolute: false),
                'booklet_options' => route('setup.omr.templates.canonical.drafts.booklet_options', $draft, absolute: false),
                'digital_verification' => route('setup.omr.templates.canonical.drafts.verification_runs.start', $draft, absolute: false),
                'native_template' => $compiledVersion !== null
                    ? route('setup.omr.templates.native_template', $compiledVersion, absolute: false)
                    : null,
                'compile' => route('setup.omr.templates.canonical.drafts.compile', $draft, absolute: false),
                'destroy' => $canDiscard
                    ? route('setup.exam-materials.answer-sheets.destroy', $draft, absolute: false)
                    : null,
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function bookletOptions(): array
    {
        return OmrQuestionBookletVersion::query()
            ->with([
                'booklet:id,code,name',
                'questions' => fn ($q) => $q
                    ->select(['id', 'booklet_version_id', 'question_position'])
                    ->orderBy('question_position'),
            ])
            ->whereIn('status', [
                OmrQuestionBookletVersion::STATUS_DRAFT,
                OmrQuestionBookletVersion::STATUS_ACTIVE,
            ])
            ->orderBy('booklet_id')
            ->orderByDesc('version')
            ->get()
            ->map(fn (OmrQuestionBookletVersion $version): array => [
                'id' => (int) $version->id,
                'booklet_id' => (int) $version->booklet_id,
                'code' => (string) ($version->booklet?->code ?? ''),
                'name' => (string) ($version->booklet?->name ?? ''),
                'version' => (int) $version->version,
                'status' => (string) $version->status,
                'questions_count' => (int) $version->questions->count(),
                'questions' => $version->questions->map(fn ($q): array => [
                    'position' => (int) $q->question_position,
                    'label' => sprintf('q%d', (int) $q->question_position),
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    private function staffFacingDraftName(OmrTemplateAuthoringDraft $draft): string
    {
        $name = str_ireplace('Canonical HTML Exam Template', 'NEXIAM answer sheet', $draft->name);

        return str_ireplace('Canonical', 'Answer sheet', $name);
    }

    /**
     * @param  array<string, mixed>  $authoring
     * @return array{
     *     source: string,
     *         canonical_url: string,
     *         pages: list<array{page_no: int, asset_id: int, canonical_url: string, sha256: string}>,
     *         artifact_hash: string,
     *         paper: array{width_mm: float, height_mm: float}|null,
     *         insets_mm: array<string, mixed>|null,
     *     aligned_url: null,
     *     overlay_url: null,
     *     mapping_guides: list<array{id:string,x_mm:float,y_mm:float,w_mm:float,h_mm:float,bubbles:list<array{x_mm:float,y_mm:float,w_mm:float,h_mm:float}>}>,
     *     diagnostics_available: bool,
     * }|null
     */
    private function resolvePersistedPreview(OmrTemplateAuthoringDraft $draft, array $authoring): ?array
    {
        $canonical = (array) ($authoring['canonical'] ?? []);
        $artifactSet = $canonical['artifact_set'] ?? null;
        if (! is_array($artifactSet) || empty($artifactSet)) {
            $pageEntries = (array) ($authoring['pages'] ?? []);
            $assetIds = array_values(array_filter(array_map(static fn ($p) => (int) ($p['asset_id'] ?? 0), $pageEntries)));
            if ($assetIds === []) {
                return null;
            }
            $assets = OmrTemplateAsset::query()
                ->whereIn('id', $assetIds)
                ->where(function ($query) use ($draft): void {
                    $query->where('authoring_draft_id', $draft->id);
                    if ($draft->compiled_version_id !== null) {
                        $query->orWhere('template_version_id', $draft->compiled_version_id);
                    }
                })
                ->get()
                ->keyBy('id');
            if ($assets->isEmpty()) {
                return null;
            }
            $pages = [];
            foreach ($pageEntries as $p) {
                $pId = (int) ($p['asset_id'] ?? 0);
                if (isset($assets[$pId])) {
                    $pages[] = [
                        'page_no' => (int) ($p['page_no'] ?? 1),
                        'asset_id' => $pId,
                        'canonical_url' => route('setup.omr.templates.canonical.drafts.assets.show', [$draft, $pId]),
                        'sha256' => (string) ($p['sha256'] ?? ''),
                    ];
                }
            }
            if ($pages === []) {
                return null;
            }

            return [
                'source' => 'persisted',
                'artifact' => [
                    'canonical_url' => $pages[0]['canonical_url'],
                    'pages' => $pages,
                    'artifact_hash' => (string) ($pages[0]['sha256'] ?? ''),
                    'paper' => null,
                    'insets_mm' => null,
                ],
                'aligned_url' => null,
                'overlay_url' => null,
                'mapping_guides' => [],
                'diagnostics_available' => false,
            ];
        }

        $artifactHash = (string) ($artifactSet['artifact_hash'] ?? $canonical['artifact_hash'] ?? '');
        if ($artifactHash === '') {
            return null;
        }

        $rasterList = (array) ($artifactSet['raster_assets']
            ?? $canonical['raster_assets']
            ?? $authoring['pages']
            ?? []);
        $assetIds = array_values(array_filter(array_map(static fn ($entry) => (int) ($entry['asset_id'] ?? 0), $rasterList)));
        if ($assetIds === []) {
            return null;
        }

        $assets = OmrTemplateAsset::query()
            ->whereIn('id', $assetIds)
            ->where(function ($query) use ($draft): void {
                $query->where('authoring_draft_id', $draft->id);
                if ($draft->compiled_version_id !== null) {
                    $query->orWhere('template_version_id', $draft->compiled_version_id);
                }
            })
            ->whereIn('kind', [
                OmrTemplateAsset::KIND_RASTER_PAGE,
                OmrTemplateAsset::KIND_INTERMEDIATE_PAGE,
            ])
            ->get()
            ->keyBy('id');

        $pages = [];
        foreach ($rasterList as $entry) {
            $assetId = (int) ($entry['asset_id'] ?? 0);
            if (isset($assets[$assetId])) {
                $pages[] = [
                    'page_no' => (int) ($entry['page_no'] ?? 1),
                    'asset_id' => $assetId,
                    'canonical_url' => route('setup.omr.templates.canonical.drafts.assets.show', [$draft, $assetId]),
                    'sha256' => (string) ($entry['sha256'] ?? ''),
                ];
            }
        }
        if ($pages === []) {
            return null;
        }

        $paper = null;
        if (isset($artifactSet['definition']['paper']) && is_array($artifactSet['definition']['paper'])) {
            $paper = [
                'width_mm' => (float) ($artifactSet['definition']['paper']['width_mm'] ?? 210.0),
                'height_mm' => (float) ($artifactSet['definition']['paper']['height_mm'] ?? 297.0),
            ];
        } elseif (isset($artifactSet['template']['pages'][0]['markers']['paper']) && is_array($artifactSet['template']['pages'][0]['markers']['paper'])) {
            $paper = [
                'width_mm' => (float) ($artifactSet['template']['pages'][0]['markers']['paper']['width_mm'] ?? 210.0),
                'height_mm' => (float) ($artifactSet['template']['pages'][0]['markers']['paper']['height_mm'] ?? 297.0),
            ];
        } elseif (isset($canonical['settings']['paper_width_mm'], $canonical['settings']['paper_height_mm'])) {
            $paper = [
                'width_mm' => (float) $canonical['settings']['paper_width_mm'],
                'height_mm' => (float) $canonical['settings']['paper_height_mm'],
            ];
        } elseif (isset($canonical['paper']) && is_array($canonical['paper'])) {
            $paper = [
                'width_mm' => (float) ($canonical['paper']['width_mm'] ?? 210.0),
                'height_mm' => (float) ($canonical['paper']['height_mm'] ?? 297.0),
            ];
        }

        $insetsMm = null;
        if (isset($artifactSet['definition']['insets_mm']) && is_array($artifactSet['definition']['insets_mm'])) {
            $insetsMm = $artifactSet['definition']['insets_mm'];
        } elseif (isset($artifactSet['template']['pages'][0]['markers']['insets_mm']) && is_array($artifactSet['template']['pages'][0]['markers']['insets_mm'])) {
            $insetsMm = $artifactSet['template']['pages'][0]['markers']['insets_mm'];
        } elseif (isset($authoring['marker']['insets_mm']) && is_array($authoring['marker']['insets_mm'])) {
            $insetsMm = $authoring['marker']['insets_mm'];
        } elseif (isset($canonical['settings']['marker_inset_mm']) && is_array($canonical['settings']['marker_inset_mm'])) {
            $insetsMm = $canonical['settings']['marker_inset_mm'];
        }

        $template = is_array($artifactSet['template'] ?? null)
            ? $artifactSet['template']
            : (is_array($canonical['template'] ?? null) ? $canonical['template'] : []);
        $insetsPx = (array) (
            $artifactSet['insets_px']
            ?? $artifactSet['definition']['insets_px']
            ?? $template['pages'][0]['markers']['insets_px']
            ?? $canonical['insets_px']
            ?? []
        );
        if ($insetsPx === [] && is_array($insetsMm)) {
            $insetsPx = [
                'top' => OmrPageDimensionMath::pixelsFromMillimetres((float) ($insetsMm['top'] ?? 0)),
                'right' => OmrPageDimensionMath::pixelsFromMillimetres((float) ($insetsMm['right'] ?? 0)),
                'bottom' => OmrPageDimensionMath::pixelsFromMillimetres((float) ($insetsMm['bottom'] ?? 0)),
                'left' => OmrPageDimensionMath::pixelsFromMillimetres((float) ($insetsMm['left'] ?? 0)),
            ];
        }

        $mappingGuides = OmrCanonicalMappingGuideGeometry::resolve($template, $insetsPx);

        return [
            'source' => 'persisted',
            'artifact' => [
                'canonical_url' => $pages[0]['canonical_url'],
                'pages' => $pages,
                'artifact_hash' => $artifactHash,
                'paper' => $paper,
                'insets_mm' => $insetsMm,
            ],
            'aligned_url' => null,
            'overlay_url' => null,
            'mapping_guides' => $mappingGuides,
            'diagnostics_available' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $authoring
     * @return array{
     *     projection: 'summary',
     *     run_id: int,
     *     mode: string,
     *     status: string,
     *     status_url: string,
     *     attempt: int,
     *     progress: array{done: int, total: int, percent: float},
     *     phase_detail: array<string, mixed>,
     *     current_variant: string|null,
     *     safe_issue: array{code: string, detail: string|null}|null,
     *     result_summary: array<string, mixed>|null,
     *     run_version: int,
     *     target_current: bool,
     *     target_stale: bool,
     *     digital_verification_current: bool,
     *     physical_assurance: array<string,mixed>|null,
     *     qualification: array<string,mixed>|null,
     * }|null
     */
    private function resolveVerificationRunSummary(
        OmrTemplateAuthoringDraft $draft,
        array $authoring,
        ?OmrAnswerSheetTemplateVersion $compiledVersion,
        bool $digitalVerificationCurrent,
    ): ?array {
        $currentTarget = $this->currentTarget($draft, $authoring, $compiledVersion);
        $currentArtifactHash = $currentTarget['artifact_hash'];
        $currentMappingHash = $currentTarget['mapping_hash'];
        $hasDraftTarget = $currentTarget['has_draft_target'];
        $compiledVersionId = $currentTarget['version_id'];
        $hasVersionTarget = $compiledVersionId !== null;

        if (! $hasDraftTarget && ! $hasVersionTarget) {
            return null;
        }

        // Active runs are prioritized for live progress.
        $activeRun = OmrTemplateVerificationRun::query()
            ->where(function ($query) use ($draft, $hasDraftTarget, $currentArtifactHash, $currentMappingHash, $hasVersionTarget, $compiledVersionId): void {
                if ($hasDraftTarget && $hasVersionTarget) {
                    $query->where(function ($sub) use ($draft, $currentArtifactHash, $currentMappingHash): void {
                        $sub->where('draft_id', $draft->id)
                            ->where('artifact_hash', $currentArtifactHash)
                            ->where('mapping_hash', $currentMappingHash);
                    })->orWhere('template_version_id', $compiledVersionId);
                } elseif ($hasDraftTarget) {
                    $query->where('draft_id', $draft->id)
                        ->where('artifact_hash', $currentArtifactHash)
                        ->where('mapping_hash', $currentMappingHash);
                } else {
                    $query->where('template_version_id', $compiledVersionId);
                }
            })
            ->whereIn('status', OmrTemplateVerificationRun::ACTIVE_STATUSES)
            ->orderByDesc('id')
            ->first();

        if ($activeRun !== null) {
            return $this->formatRunSummary($activeRun, $digitalVerificationCurrent);
        }

        // Across all terminal modes, select the newest run matching either the
        // current draft artifact/mapping target or the current compiled version.
        $terminalRun = OmrTemplateVerificationRun::query()
            ->where(function ($query) use ($draft, $hasDraftTarget, $currentArtifactHash, $currentMappingHash, $hasVersionTarget, $compiledVersionId): void {
                if ($hasDraftTarget && $hasVersionTarget) {
                    $query->where(function ($sub) use ($draft, $currentArtifactHash, $currentMappingHash): void {
                        $sub->where('draft_id', $draft->id)
                            ->where('artifact_hash', $currentArtifactHash)
                            ->where('mapping_hash', $currentMappingHash);
                    })->orWhere('template_version_id', $compiledVersionId);
                } elseif ($hasDraftTarget) {
                    $query->where('draft_id', $draft->id)
                        ->where('artifact_hash', $currentArtifactHash)
                        ->where('mapping_hash', $currentMappingHash);
                } else {
                    $query->where('template_version_id', $compiledVersionId);
                }
            })
            ->whereIn('status', OmrTemplateVerificationRun::TERMINAL_STATUSES)
            ->orderByDesc('id')
            ->first();

        return $terminalRun !== null
            ? $this->formatRunSummary($terminalRun, $digitalVerificationCurrent)
            : null;
    }

    /**
     * Resolve the current mutable draft target using the canonical artifact
     * hash and the normalized geometry identity. A compiled version is an
     * immutable target and is identified by its version ID.
     *
     * @param  array<string, mixed>  $authoring
     * @return array{artifact_hash:string,mapping_hash:string,has_draft_target:bool,version_id:int|null}
     */
    private function currentTarget(
        OmrTemplateAuthoringDraft $draft,
        array $authoring,
        ?OmrAnswerSheetTemplateVersion $compiledVersion,
    ): array {
        $canonical = (array) ($authoring['canonical'] ?? []);
        $artifactSet = $canonical['artifact_set'] ?? null;
        $hasArtifactSet = is_array($artifactSet) && ! empty($artifactSet);
        $artifactHash = $hasArtifactSet
            ? (string) ($artifactSet['artifact_hash'] ?? $canonical['artifact_hash'] ?? '')
            : '';
        $mappingHash = '';
        if ($hasArtifactSet) {
            try {
                // Never trust a persisted identity after mutable authoring
                // state changes; derive it from the current normalized
                // definition.
                $mappingHash = OmrCanonicalGeometryIdentity::forDraft($draft);
            } catch (\Throwable) {
                $mappingHash = '';
            }
            if ($mappingHash === '') {
                $mappingHash = (string) ($artifactSet['geometry_identity'] ?? $artifactSet['mapping_hash'] ?? $authoring['mapping']['mapping_hash'] ?? '');
            }
        }

        return [
            'artifact_hash' => $artifactHash,
            'mapping_hash' => $mappingHash,
            'has_draft_target' => $hasArtifactSet && $artifactHash !== '' && $mappingHash !== '',
            'version_id' => $compiledVersion?->id !== null ? (int) $compiledVersion->id : null,
        ];
    }

    /**
     * Resolve the latest passed full qualification run bound to the current draft target or
     * compiled version so qualification is derived rather than read from manifest metadata.
     *
     * @param  array{artifact_hash:string,mapping_hash:string,has_draft_target:bool,version_id:int|null}  $target
     */
    private function latestCurrentPhysicalRun(OmrTemplateAuthoringDraft $draft, array $target): ?OmrTemplateVerificationRun
    {
        if (! $target['has_draft_target'] && $target['version_id'] === null) {
            return null;
        }

        return OmrTemplateVerificationRun::query()
            ->where('mode', OmrTemplateVerificationRun::MODE_FULL_QUALIFICATION)
            ->where('status', OmrTemplateVerificationRun::STATUS_PASSED)
            ->where(function ($query) use ($draft, $target): void {
                $hasDraftTarget = $target['has_draft_target'];
                if ($hasDraftTarget) {
                    $query->where(function ($sub) use ($draft, $target): void {
                        $sub->where('draft_id', $draft->id)
                            ->where('artifact_hash', $target['artifact_hash'])
                            ->where('mapping_hash', $target['mapping_hash']);
                    });
                }
                if ($target['version_id'] !== null) {
                    if ($hasDraftTarget) {
                        $query->orWhere('template_version_id', $target['version_id']);
                    } else {
                        $query->where('template_version_id', $target['version_id']);
                    }
                }
            })
            ->latest('id')
            ->first();
    }

    /**
     * @return array{
     *     projection: 'summary',
     *     run_id: int,
     *     mode: string,
     *     status: string,
     *     status_url: string,
     *     attempt: int,
     *     progress: array{done: int, total: int, percent: float},
     *     phase_detail: array<string, mixed>,
     *     current_variant: string|null,
     *     safe_issue: array{code: string, detail: string|null}|null,
     *     result_summary: array<string, mixed>|null,
     *     run_version: int,
     *     target_current: bool,
     *     target_stale: bool,
     *     digital_verification_current: bool,
     *     physical_assurance: array<string,mixed>|null,
     *     qualification: array<string,mixed>|null,
     * }|null
     */
    private function formatRunSummary(
        OmrTemplateVerificationRun $run,
        bool $digitalVerificationCurrent = false,
    ): array {
        $total = max(0, (int) $run->progress_total);
        if ($run->mode === OmrTemplateVerificationRun::MODE_DIGITAL
            && $run->draft?->registration_mode instanceof OmrRegistrationMode
            && (int) $run->draft->requested_page_count >= 1
            && (int) $run->draft->requested_page_count <= 4) {
            $total = max($total, count(OmrNoGoMatrixRunner::variantKeys(
                (int) $run->draft->requested_page_count,
                $run->draft->registration_mode,
            )));
        }
        $done = min($total, max(0, (int) $run->progress_done));
        $percent = $total > 0 ? round($done / $total * 100, 1) : 0.0;
        $phaseDetail = (array) ($run->phase_detail ?? []);
        $physicalAssurance = ($this->physicalAssurance ?? app(OmrPhysicalAssuranceStatus::class))->get($run);

        return [
            'projection' => 'summary',
            'run_id' => (int) $run->id,
            'mode' => (string) $run->mode,
            'status' => (string) $run->status,
            'status_url' => route('setup.omr.templates.canonical.verification_runs.status', ['run' => $run->id]),
            'attempt' => (int) $run->attempt,
            'progress' => [
                'done' => $done,
                'total' => $total,
                'percent' => $percent,
            ],
            'phase_detail' => $phaseDetail,
            'current_variant' => is_string($phaseDetail['current_variant'] ?? null) ? $phaseDetail['current_variant'] : null,
            'safe_issue' => $run->error_code !== null
                ? ['code' => (string) $run->error_code, 'detail' => $run->safe_error]
                : null,
            'result_summary' => is_array($run->result_summary) ? $run->result_summary : null,
            'run_version' => (int) $run->optimistic_version,
            'target_current' => true,
            'target_stale' => false,
            'digital_verification_current' => $digitalVerificationCurrent,
            'physical_assurance' => $physicalAssurance,
            'qualification' => $physicalAssurance,
        ];
    }
}
