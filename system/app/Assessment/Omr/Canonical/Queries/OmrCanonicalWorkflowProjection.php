<?php

namespace App\Assessment\Omr\Canonical\Queries;

use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrMarkerProfile;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\Foundation\AptitudeArea;
use App\Models\User;
use function array_flip;
use function count;
use function in_array;
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
    /**
     * @return array<string, mixed>
     */
    public function indexProps(User $actor): array
    {
        $drafts = OmrTemplateAuthoringDraft::query()
            ->with('owner:id,name')
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (OmrTemplateAuthoringDraft $draft): array => [
                'id' => $draft->id,
                'code' => $draft->code,
                'name' => $draft->name,
                'source_kind' => $draft->source_kind,
                'stage' => $draft->stage,
                'can_edit' => (int) $draft->owner_user_id === (int) $actor->id,
                'owner_name' => $draft->owner?->name,
                'updated_at' => $draft->updated_at?->toIso8601String(),
                'url' => route('setup.omr.templates.canonical.drafts.show', $draft),
            ])
            ->values()
            ->all();

        return [
            'drafts' => $drafts,
            'create_url' => route('setup.omr.templates.canonical.drafts.create'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function workflowProps(OmrTemplateAuthoringDraft $draft, User $actor): array
    {
        $canEdit = (int) $draft->owner_user_id === (int) $actor->id;
        $authoring = (array) $draft->authoring_json;
        $rasterAssetId = (int) (($authoring['canonical']['raster_assets'][0]['asset_id'] ?? null) ?: ($authoring['pages'][0]['asset_id'] ?? 0));
        $printPdfAssetId = (int) ($authoring['canonical']['pdf_asset_id'] ?? 0);
        $compiledVersion = $draft->compiled_version_id !== null
            ? OmrAnswerSheetTemplateVersion::query()->find($draft->compiled_version_id)
            : null;
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

        return [
            'page_state' => 'ready',
            'draft' => [
                'id' => $draft->id,
                'code' => $draft->code,
                'name' => $draft->name,
                'source_kind' => $draft->source_kind,
                'stage' => $draft->stage,
                'lock_version' => $draft->lock_version,
                'marker_profile_id' => $draft->marker_profile_id,
                'compiled_version_id' => $draft->compiled_version_id,
                'compiled_version_status' => $compiledVersion?->status,
                'physical_qualified' => ($compiledVersion?->authoring_manifest['physical_qualification']['status'] ?? null) === 'physical_qualified',
                'owner_user_id' => $draft->owner_user_id,
                'can_edit' => $canEdit,
                'authoring' => $draft->authoring_json,
                'raster_url' => $rasterAssetId > 0
                    ? route('setup.omr.templates.canonical.drafts.assets.show', [$draft, $rasterAssetId])
                    : null,
                'print_pdf_url' => $printPdfAssetId > 0
                    ? route('setup.omr.templates.canonical.drafts.assets.show', [$draft, $printPdfAssetId])
                    : null,
            ],
            'marker_profiles' => OmrMarkerProfile::query()
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
                ->all(),
            'aptitude_areas' => $studioAptitudeAreas,
            'stages' => [
                'draft',
                'convert_inspect',
                'page_prepare',
                'overlay',
                'map',
                'digital_verify',
                'compile',
                'physical_activate',
                'blank_print',
            ],
            'urls' => [
                'source_ingest' => route('setup.omr.templates.canonical.drafts.source.ingest', $draft),
                'source_rectify' => route('setup.omr.templates.canonical.drafts.source.rectify', $draft),
                'confirm_pages' => route('setup.omr.templates.canonical.drafts.source.pages.confirm', $draft),
                'compose' => route('setup.omr.templates.canonical.drafts.compose', $draft),
                'source_run' => route('setup.omr.templates.canonical.drafts.source.run', $draft),
                'source_cancel' => route('setup.omr.templates.canonical.drafts.source.cancel', $draft),
                'overlay' => route('setup.omr.templates.canonical.drafts.overlay', $draft),
                'mapping' => route('setup.omr.templates.canonical.drafts.mapping', $draft),
                'studio' => route('setup.omr.studio.save', $draft),
                'materialize' => route('setup.omr.templates.canonical.drafts.materialize', $draft),
                'preview' => route('setup.omr.templates.canonical.drafts.preview', $draft),
                'booklet_options' => route('setup.omr.templates.canonical.drafts.booklet_options', $draft),
                'digital_verification' => route('setup.omr.templates.canonical.drafts.verify.digital', $draft),
                'native_template' => $compiledVersion !== null
                    ? route('setup.omr.templates.native_template', $compiledVersion)
                    : null,
                'compile' => route('setup.omr.templates.canonical.drafts.compile', $draft),
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function bookletOptions(): array
    {
        return OmrQuestionBookletVersion::query()
            ->with(['booklet:id,code,name', 'questions' => fn ($q) => $q->orderBy('question_position')])
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
                    'correct_option_code' => (string) ($q->correct_option_code ?? ''),
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }
}
