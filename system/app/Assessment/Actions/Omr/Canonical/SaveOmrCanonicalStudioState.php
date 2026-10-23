<?php

namespace App\Assessment\Actions\Omr\Canonical;

use App\Assessment\Omr\Canonical\OmrAuthoringDraftException;
use App\Assessment\Omr\Canonical\OmrAuthoringDraftService;
use App\Assessment\Omr\Canonical\OmrCanonicalGeometryIdentity;
use App\Assessment\Omr\Canonical\OmrMappingValidationException;
use App\Assessment\Omr\Canonical\OmrMappingValidator;
use App\Assessment\Omr\Canonical\OmrOverlaySpec;
use App\Assessment\Omr\OmrQrMode;
use App\Assessment\Omr\OmrRegistrationMode;
use App\Assessment\Omr\Verification\CurrentOmrDigitalVerification;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

use function count;
use function in_array;
use function is_array;
use function json_encode;

final class SaveOmrCanonicalStudioState
{
    public function __construct(
        private readonly OmrOverlaySpec $spec,
        private readonly OmrMappingValidator $validator,
        private readonly OmrAuthoringDraftService $drafts,
        private readonly CurrentOmrDigitalVerification $verification,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    /**
     * Atomically save mapping, custom overlays, and studio state in a single transaction.
     *
     * @param  array<string, mixed>  $overlay
     * @param  array<string, mixed>|null  $mapping
     * @param  array<string, mixed>|null  $studio
     */
    public function handle(
        OmrTemplateAuthoringDraft $draft,
        User $actor,
        array $overlay,
        ?array $mapping,
        ?array $studio,
        int $expectedLockVersion,
        ?string $sourceKind = null,
    ): OmrTemplateAuthoringDraft {
        $this->drafts->assertOwner($draft, $actor);
        if ($sourceKind !== null && ! in_array($sourceKind, OmrTemplateAuthoringDraft::SOURCE_KINDS, true)) {
            throw new OmrAuthoringDraftException('unsupported_source', 'Source kind is not supported.');
        }

        if (! in_array($draft->stage, [
            OmrTemplateAuthoringDraft::STAGE_DRAFT,
            OmrTemplateAuthoringDraft::STAGE_SOURCE_CONFIRMED,
            OmrTemplateAuthoringDraft::STAGE_PAGE_PREPARED,
            OmrTemplateAuthoringDraft::STAGE_MAPPING_COMPLETE,
            OmrTemplateAuthoringDraft::STAGE_DIGITAL_VERIFIED,
        ], true)) {
            throw new OmrAuthoringDraftException('studio_stage_invalid', 'Studio requires an editable authoring draft.');
        }

        $uploaded = in_array($sourceKind ?? $draft->source_kind, [
            OmrTemplateAuthoringDraft::SOURCE_DOCX,
            OmrTemplateAuthoringDraft::SOURCE_PDF,
            OmrTemplateAuthoringDraft::SOURCE_IMAGE,
        ], true);
        $pageCount = (int) $draft->requested_page_count;
        if ($mapping !== null && (int) ($mapping['page_count'] ?? $pageCount) !== $pageCount) {
            throw new RuntimeException("page_count_mismatch: Mapping must describe exactly {$pageCount} pages.");
        }
        foreach ((array) ($overlay['boxes'] ?? []) as $box) {
            $boxPage = isset($box['page_no']) ? (int) $box['page_no'] : ($pageCount === 1 ? 1 : 0);
            $this->assertPageNumber($boxPage, $pageCount, 'Overlay');
        }
        foreach (['components', 'sections'] as $collection) {
            foreach ((array) ($studio[$collection] ?? []) as $item) {
                $itemPage = isset($item['page_no']) ? (int) $item['page_no'] : ($pageCount === 1 ? 1 : 0);
                $this->assertPageNumber($itemPage, $pageCount, ucfirst($collection));
            }
        }
        $instrumentVersionId = null;
        if (! $uploaded) {
            $this->assertGloballyContiguousQuestions((array) ($studio['sections'] ?? []), false);
        }
        if (is_array($studio['physicalInventory'] ?? null)) {
            $this->assertPhysicalInventory($studio['physicalInventory'], $pageCount);
        }
        $this->assertModeCompatibleState($draft, $overlay, $studio);
        $authoring = (array) $draft->authoring_json;
        $cropReviewRequired = ($authoring['invalidations']['mapping_requires_review'] ?? false) === true;
        if ($cropReviewRequired && $sourceKind !== null && $sourceKind !== $draft->source_kind) {
            throw new RuntimeException('crop_review_stale: Finish reviewing the corrected source before changing source kind.');
        }
        $reviewedCropAssetId = $studio['reviewed_crop_asset_id'] ?? null;
        if ($cropReviewRequired && $reviewedCropAssetId !== null) {
            $page = (array) ($authoring['pages'][0] ?? []);
            if ((int) $reviewedCropAssetId < 1
                || (int) $reviewedCropAssetId !== (int) ($page['asset_id'] ?? 0)
                || ($page['confirmed'] ?? false) !== true
                || $mapping === null) {
                throw new RuntimeException('crop_review_stale: Review the confirmed crop and all recognition guides before accepting their positions.');
            }
        }
        $cropReviewPending = $cropReviewRequired && $reviewedCropAssetId === null;
        if ($cropReviewPending) {
            $mapping = null;
        }
        $validatedOverlay = $this->spec->validate($overlay);
        $documentFields = $studio['document_fields']
            ?? $mapping['document_fields']
            ?? $mapping['input']['document_fields']
            ?? null;
        if ($documentFields !== null) {
            $canonicalSettings = (array) ($authoring['canonical']['settings'] ?? []);
            $paper = (array) ($authoring['paper'] ?? []);
            $paperWidth = (float) ($canonicalSettings['paper_width_mm'] ?? $paper['width_mm'] ?? ($draft->source_kind === OmrTemplateAuthoringDraft::SOURCE_HTML ? 210.0 : 216.0));
            $paperHeight = (float) ($canonicalSettings['paper_height_mm'] ?? $paper['height_mm'] ?? ($draft->source_kind === OmrTemplateAuthoringDraft::SOURCE_HTML ? 297.0 : 330.0));
            $documentFieldErrors = $this->validator->validateDocumentFields(
                $documentFields,
                $pageCount,
                $paperWidth,
                $paperHeight,
            );
            if ($documentFieldErrors !== []) {
                $firstError = $documentFieldErrors[0];
                throw new OmrMappingValidationException(
                    $firstError['code'],
                    $firstError['message'],
                    ['errors' => $documentFieldErrors],
                );
            }
        }

        $blocks = (array) ($mapping['blocks'] ?? []);
        $validatedMapping = (array) ($draft->authoring_json['mapping'] ?? null) ?: null;
        $warnings = $validatedMapping['warnings'] ?? [];
        $fieldBlocks = $validatedMapping['field_blocks'] ?? [];
        $mappingComplete = $validatedMapping !== null && ($validatedMapping['errors'] ?? []) === [] && $fieldBlocks !== [];
        if ($mapping !== null && ($blocks !== [] || $uploaded)) {
            unset($mapping['test_instrument_version_id']);
            foreach ($mapping['blocks'] ?? [] as &$block) {
                if (is_array($block)) {
                    unset($block['subtest_code'], $block['item_codes']);
                }
            }
            unset($block);
            $authoring = (array) $draft->authoring_json;
            $canonical = (array) ($authoring['canonical'] ?? []);
            $result = $this->validator->validate($mapping, [
                'page_width' => $mapping['page_width'] ?? null,
                'page_height' => $mapping['page_height'] ?? null,
                'page_count' => $pageCount,
                'paper_width_mm' => (float) ($authoring['canonical']['settings']['paper_width_mm'] ?? $authoring['paper']['width_mm'] ?? ($draft->source_kind === OmrTemplateAuthoringDraft::SOURCE_HTML ? 210.0 : 216.0)),
                'paper_height_mm' => (float) ($authoring['canonical']['settings']['paper_height_mm'] ?? $authoring['paper']['height_mm'] ?? ($draft->source_kind === OmrTemplateAuthoringDraft::SOURCE_HTML ? 297.0 : 330.0)),
                'marker_zones' => $canonical['marker_zones'] ?? [],
                'content_zones' => $canonical['content_zones'] ?? [],
                'is_uploaded' => $uploaded,
            ]);
            if (! $result['valid'] && ! $uploaded) {
                throw new RuntimeException('mapping_invalid: '.json_encode($result['errors'], JSON_THROW_ON_ERROR));
            }
            $mapping = $result['input'] ?? $mapping;
            $mappingComplete = $result['valid'];
            $fieldBlocks = $result['field_blocks'];
            $warnings = $result['warnings'];
            $validatedMapping = [
                'input' => $mapping,
                'field_blocks' => $fieldBlocks,
                'warnings' => $warnings,
                'errors' => $result['errors'],
            ];
        }

        return DB::transaction(function () use (
            $draft,
            $actor,
            $validatedOverlay,
            $validatedMapping,
            $mapping,
            $studio,
            $fieldBlocks,
            $warnings,
            $expectedLockVersion,
            $mappingComplete,
            $cropReviewRequired,
            $cropReviewPending,
            $uploaded,
            $instrumentVersionId,
            $sourceKind,
        ): OmrTemplateAuthoringDraft {
            $locked = OmrTemplateAuthoringDraft::query()->lockForUpdate()->findOrFail($draft->id);

            if ((int) $locked->lock_version !== $expectedLockVersion) {
                throw new OmrAuthoringDraftException('concurrency_conflict', 'Draft changed since it was loaded.');
            }

            if ($locked->stage === OmrTemplateAuthoringDraft::STAGE_COMPILED) {
                throw new OmrAuthoringDraftException('draft_read_only', 'Compiled drafts are immutable.');
            }

            $authoring = (array) $locked->authoring_json;
            $previousCanonical = (array) (($authoring['canonical'] ?? []) ?: []);
            if ($studio !== null && is_array($studio['physicalInventory'] ?? null)) {
                $previousInventory = (array) data_get($authoring, 'studio.physicalInventory', []);
                $nextInventory = $studio['physicalInventory'];
                $previousRevision = (int) ($previousInventory['revision'] ?? 0);
                $nextRevision = (int) ($nextInventory['revision'] ?? -1);
                $inventoryChanged = json_encode($previousInventory, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)
                    !== json_encode($nextInventory, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
                if ($nextRevision !== $previousRevision + ($inventoryChanged ? 1 : 0)) {
                    throw new OmrAuthoringDraftException('physical_inventory_revision_stale', 'Printed section inventory changed. Reload it before saving this edit.');
                }
                $impact = $this->physicalInventoryImpact($previousInventory, $nextInventory);
                if ($impact !== []) {
                    $confirmation = (array) ($studio['physicalInventoryConfirmation'] ?? []);
                    $currentRevision = (int) ($previousInventory['revision'] ?? 0);
                    $expectedDigest = hash('sha256', json_encode([
                        'revision' => $currentRevision,
                        'impact' => $impact,
                    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
                    if ((int) ($confirmation['revision'] ?? 0) !== $currentRevision
                        || ! hash_equals($expectedDigest, (string) ($confirmation['digest'] ?? ''))) {
                        throw new OmrAuthoringDraftException('physical_inventory_impact_stale', 'Review the exact physical locations and guides affected by this change before confirming it.');
                    }
                }
                unset($studio['physicalInventoryConfirmation']);
            }
            $previousArtifactSet = $previousCanonical['artifact_set'] ?? null;
            $previousIdentity = (string) (is_array($previousArtifactSet) ? ($previousArtifactSet['geometry_identity'] ?? '') : '');
            if ($previousIdentity === '') {
                try {
                    $previousIdentity = OmrCanonicalGeometryIdentity::forDraft($locked);
                } catch (\Throwable) {
                    $previousIdentity = '';
                }
            }

            $nextAuthoring = $authoring;
            if ((array) ($nextAuthoring['pages'] ?? []) === []) {
                $nextAuthoring['pages'] = collect(range(1, (int) $locked->requested_page_count))
                    ->map(static fn (int $pageNo): array => [
                        'page_no' => $pageNo,
                        'kind' => 'blank',
                        'confirmed' => true,
                    ])
                    ->all();
            }
            $nextAuthoring['overlay'] = $validatedOverlay;
            if ($validatedMapping !== null) {
                $nextAuthoring['mapping'] = $validatedMapping;
            }
            if ($studio !== null) {
                if (! array_key_exists('physicalInventory', $studio)) {
                    $studio['physicalInventory'] = data_get($authoring, 'studio.physicalInventory');
                }
                $nextAuthoring['studio'] = $studio;
                if (isset($studio['smartSnapSearchMm']) && is_numeric($studio['smartSnapSearchMm'])) {
                    $nextAuthoring['canonical'] = (array) ($nextAuthoring['canonical'] ?? []);
                    $nextAuthoring['canonical']['settings'] = (array) ($nextAuthoring['canonical']['settings'] ?? []);
                    $nextAuthoring['canonical']['settings']['smart_snap_search_mm'] = round(
                        max(2.0, min(12.0, (float) $studio['smartSnapSearchMm'])),
                        2,
                    );
                }
            }
            if ($cropReviewPending) {
                $nextAuthoring['mapping'] = null;
            } elseif ($cropReviewRequired) {
                $nextAuthoring['invalidations']['mapping_requires_review'] = false;
                $nextAuthoring['invalidations']['stale_mapping'] = false;
            }

            $tempDraft = clone $locked;
            $tempDraft->authoring_json = $nextAuthoring;
            $tempDraft->source_kind = $sourceKind ?? $locked->source_kind;

            try {
                $nextIdentity = OmrCanonicalGeometryIdentity::forDraft($tempDraft);
            } catch (\Throwable) {
                $nextIdentity = '';
            }

            $sourceKindChanged = $sourceKind !== null && $sourceKind !== $locked->source_kind;
            $mappingChanged = $uploaded && $mapping !== null && (($authoring['mapping']['input'] ?? null) !== $mapping);
            $geometryChanged = $sourceKindChanged || $mappingChanged || $previousIdentity === '' || $nextIdentity === '' || $previousIdentity !== $nextIdentity;

            if ($validatedMapping !== null) {
                $validatedMapping['mapping_hash'] = $nextIdentity;
                $nextAuthoring['mapping'] = $validatedMapping;
            }

            // Keep prior materialized targets addressable for a reversible edit.
            // A move-back can restore the exact artifact set, then authority still
            // validates its durable passing run before retaining digital_verified.
            $history = (array) ($previousCanonical['superseded_artifact_sets'] ?? []);
            $restoredHistory = $history[$nextIdentity] ?? null;
            if (! $cropReviewPending && $geometryChanged && ! $mappingChanged && $nextIdentity !== '' && is_array($restoredHistory)) {
                $restoredArtifactSet = (array) ($restoredHistory['artifact_set'] ?? []);
                if ((string) ($restoredArtifactSet['artifact_hash'] ?? '') !== '') {
                    $nextAuthoring['canonical']['artifact_set'] = $restoredArtifactSet;
                    $nextAuthoring['canonical']['artifact_hash'] = $restoredArtifactSet['artifact_hash'];
                    $nextAuthoring['canonical']['raster_assets'] = (array) ($restoredArtifactSet['raster_assets'] ?? []);
                    $nextAuthoring['canonical']['pdf_asset_id'] = $restoredArtifactSet['pdf_asset_id'] ?? null;
                    $nextAuthoring['verification'] = $restoredHistory['verification'] ?? null;
                    $geometryChanged = false;
                }
            }

            if ($geometryChanged) {
                if (is_array($previousArtifactSet) && $previousArtifactSet !== [] && $previousIdentity !== '') {
                    $history[$previousIdentity] = [
                        'artifact_set' => $previousArtifactSet,
                        'verification' => $authoring['verification'] ?? null,
                    ];
                    $nextAuthoring['canonical']['superseded_artifact_sets'] = $history;
                }
                $nextAuthoring['canonical']['artifact_set'] = null;
                $nextAuthoring['canonical']['artifact_hash'] = null;
                $nextAuthoring['canonical']['raster_assets'] = [];
                $nextAuthoring['canonical']['pdf_asset_id'] = null;
                $nextAuthoring['verification'] = null;
                $nextAuthoring['compiled'] = null;
            }

            $tempDraft->authoring_json = $nextAuthoring;

            $nextStage = $locked->stage;
            $uploadedSourceNotComposed = in_array($tempDraft->source_kind, [
                OmrTemplateAuthoringDraft::SOURCE_DOCX,
                OmrTemplateAuthoringDraft::SOURCE_PDF,
                OmrTemplateAuthoringDraft::SOURCE_IMAGE,
            ], true) && in_array($locked->stage, [
                OmrTemplateAuthoringDraft::STAGE_DRAFT,
                OmrTemplateAuthoringDraft::STAGE_SOURCE_CONFIRMED,
            ], true);
            if (! $uploadedSourceNotComposed && $validatedMapping !== null && (! $uploaded || $mappingComplete)) {
                $nextStage = OmrTemplateAuthoringDraft::STAGE_MAPPING_COMPLETE;
            } elseif ($uploaded && $validatedMapping !== null) {
                $nextStage = OmrTemplateAuthoringDraft::STAGE_PAGE_PREPARED;
            } elseif (! $uploadedSourceNotComposed && $locked->stage === OmrTemplateAuthoringDraft::STAGE_DRAFT) {
                $nextStage = OmrTemplateAuthoringDraft::STAGE_PAGE_PREPARED;
            }
            if ($cropReviewPending) {
                $nextStage = ($locked->authoring_json['pages'][0]['confirmed'] ?? false) === true
                    ? OmrTemplateAuthoringDraft::STAGE_PAGE_PREPARED
                    : OmrTemplateAuthoringDraft::STAGE_DRAFT;
            }

            // If geometry didn't change and we have a valid passing verification, preserve it.
            $matchingVerification = (! $cropReviewPending && ! $geometryChanged && $nextIdentity !== '')
                ? $this->verification->findForTarget($locked->id, (string) ($nextAuthoring['canonical']['artifact_set']['artifact_hash'] ?? $nextAuthoring['canonical']['artifact_hash'] ?? ''), $nextIdentity, $tempDraft)
                : null;

            if (($locked->stage === OmrTemplateAuthoringDraft::STAGE_DIGITAL_VERIFIED || is_array($restoredHistory))
                && ! $geometryChanged
                && $matchingVerification !== null) {
                $nextStage = OmrTemplateAuthoringDraft::STAGE_DIGITAL_VERIFIED;
                if (($nextAuthoring['verification']['status'] ?? null) !== 'passed') {
                    $nextAuthoring['verification'] = $matchingVerification->rebuildVerificationPayload($locked);
                }
            }

            $locked->forceFill([
                'authoring_json' => $nextAuthoring,
                'lock_version' => $expectedLockVersion + 1,
                'stage' => $nextStage,
                'source_kind' => $sourceKind ?? $locked->source_kind,
                'test_instrument_version_id' => $instrumentVersionId,
                'compiled_version_id' => $geometryChanged ? null : $locked->compiled_version_id,
            ])->save();

            $saved = $locked->fresh();

            $this->recordAuditEvent->handle('omr.canonical.studio_saved', [
                'draft_id' => $saved->id,
                'overlay_hash' => $validatedOverlay['hash'],
                'mapping_hash' => $nextIdentity,
                'geometry_identity' => $nextIdentity,
                'geometry_changed' => $geometryChanged,
                'field_block_count' => count($fieldBlocks),
                'source_page_count' => count((array) ($saved->authoring_json['pages'] ?? [])),
                'source_run_status' => $saved->authoring_json['source_run']['status'] ?? null,
                'warnings' => $warnings,
                'has_studio' => $studio !== null,
                'stage' => $nextStage,
            ], (string) $actor->id);

            return $saved;
        });
    }

    /**
     * @param  array<string, mixed>  $inventory
     */
    private function assertPhysicalInventory(array $inventory, int $pageCount): void
    {
        $revision = (int) ($inventory['revision'] ?? -1);
        $sections = $inventory['sections'] ?? null;
        if ($revision < 0 || ! is_array($sections) || ! array_is_list($sections) || count($sections) > 100) {
            throw new RuntimeException('physical_inventory_invalid: Printed sections are malformed.');
        }
        if ($revision === 0 && $sections === []) {
            return;
        }
        if ($revision < 1 || $sections === []) {
            throw new RuntimeException('physical_inventory_invalid: Add a Printed section before saving an inventory revision.');
        }

        $sectionUids = [];
        $physicalUids = [];
        $guideIds = [];
        foreach ($sections as $section) {
            if (! is_array($section)) {
                throw new RuntimeException('physical_inventory_invalid: A Printed section is malformed.');
            }
            $sectionUid = (string) ($section['printed_section_uid'] ?? '');
            $name = trim((string) ($section['name'] ?? ''));
            $pageNo = (int) ($section['page_no'] ?? 0);
            $numbering = (array) ($section['numbering'] ?? []);
            $items = $section['items'] ?? null;
            $expectedCount = (int) ($section['expected_item_count'] ?? 0);
            if ($sectionUid === '' || isset($sectionUids[$sectionUid]) || $name === ''
                || $pageNo < 1 || $pageNo > $pageCount
                || (int) ($numbering['start'] ?? 0) < 1 || (int) ($numbering['step'] ?? 0) < 1
                || $expectedCount < 1 || ! is_array($items) || ! array_is_list($items)
                || count($items) !== $expectedCount) {
                throw new RuntimeException('physical_inventory_invalid: Section identity, page, numbering, or expected item count is inconsistent.');
            }
            $sectionUids[$sectionUid] = true;
            foreach ($items as $item) {
                if (! is_array($item)) {
                    throw new RuntimeException('physical_inventory_invalid: A physical location is malformed.');
                }
                $physicalUid = (string) ($item['physical_item_uid'] ?? '');
                $nativeLabel = $item['native_label'] ?? null;
                $optionTokens = $item['option_tokens'] ?? null;
                $guideId = $item['guide_id'] ?? null;
                if ($physicalUid === '' || isset($physicalUids[$physicalUid])
                    || ! is_array($optionTokens) || count($optionTokens) > 10
                    || (int) ($item['page_no'] ?? 0) !== $pageNo
                    || (int) ($item['printed_number'] ?? 0) < 1
                    || ($nativeLabel !== null && (! is_string($nativeLabel) || strlen($nativeLabel) > 100))
                    || ($guideId !== null && (! is_string($guideId) || $guideId === '' || isset($guideIds[$guideId])))) {
                    throw new RuntimeException('physical_inventory_invalid: Physical location identity or page is inconsistent.');
                }
                foreach ($optionTokens as $token) {
                    if (! is_string($token) || trim($token) !== $token || $token === '' || strlen($token) > 32) {
                        throw new RuntimeException('physical_inventory_invalid: Response tokens must remain exact non-empty strings.');
                    }
                }
                if (count(array_unique($optionTokens, SORT_STRING)) !== count($optionTokens)) {
                    throw new RuntimeException('physical_inventory_invalid: Literal response tokens must be unique.');
                }
                $physicalUids[$physicalUid] = true;
                if ($guideId !== null) {
                    $guideIds[$guideId] = true;
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $previous
     * @param  array<string, mixed>  $next
     * @return list<array{physical_item_uid:string,printed_section_uid:string,section_name:string,native_label:mixed,page_no:int,guide_id:mixed}>
     */
    private function physicalInventoryImpact(array $previous, array $next): array
    {
        $nextItems = [];
        foreach ((array) ($next['sections'] ?? []) as $section) {
            foreach ((array) ($section['items'] ?? []) as $item) {
                $nextItems[(string) ($item['physical_item_uid'] ?? '')] = [
                    'section' => $section,
                    'item' => $item,
                ];
            }
        }

        $impact = [];
        foreach ((array) ($previous['sections'] ?? []) as $section) {
            foreach ((array) ($section['items'] ?? []) as $item) {
                $uid = (string) ($item['physical_item_uid'] ?? '');
                $replacement = $nextItems[$uid] ?? null;
                if ($replacement === null
                    || (string) ($replacement['section']['printed_section_uid'] ?? '') !== (string) ($section['printed_section_uid'] ?? '')
                    || (int) ($replacement['section']['page_no'] ?? 0) !== (int) ($section['page_no'] ?? 0)) {
                    $impact[] = [
                        'physical_item_uid' => $uid,
                        'printed_section_uid' => (string) ($section['printed_section_uid'] ?? ''),
                        'section_name' => (string) ($section['name'] ?? ''),
                        'native_label' => $item['native_label'] ?? null,
                        'page_no' => (int) ($section['page_no'] ?? 0),
                        'guide_id' => $item['guide_id'] ?? null,
                    ];
                }
            }
        }
        usort($impact, static fn (array $left, array $right): int => $left['physical_item_uid'] <=> $right['physical_item_uid']);

        return $impact;
    }

    private function assertPageNumber(int $pageNo, int $pageCount, string $subject): void
    {
        if ($pageNo < 1 || $pageNo > $pageCount) {
            throw new RuntimeException("page_number_invalid: {$subject} page must be between 1 and {$pageCount}.");
        }
    }

    /** @param list<array<string, mixed>> $sections */
    private function assertGloballyContiguousQuestions(array $sections, bool $partitionBySubtest = false): void
    {
        $questionSections = collect($sections)
            ->filter(static fn (mixed $section): bool => is_array($section) && in_array($section['type'] ?? null, ['mcq', 'tf', 'likert', 'custom'], true));

        $byPartition = $partitionBySubtest
            ? $questionSections->groupBy(
                static fn (array $section): string => (string) ($section['subtest_code'] ?? $section['area_code'] ?? $section['aptitudeAreaCode'] ?? 'default')
            )
            : collect(['default' => $questionSections]);

        foreach ($byPartition as $partitionKey => $group) {
            $sorted = $group->sortBy(static fn (array $section): int => (int) ($section['startQ'] ?? 0))->values();
            $expectedStart = 1;
            foreach ($sorted as $section) {
                $start = (int) ($section['startQ'] ?? 0);
                $count = (int) ($section['questionsCount'] ?? 0);
                if ($start !== $expectedStart || $count < 1) {
                    $label = $partitionKey === 'default' ? 'Question' : "Subtest [{$partitionKey}] question";
                    throw new RuntimeException("question_numbering_invalid: {$label} numbering must be contiguous from 1; expected {$expectedStart}, found {$start}.");
                }
                $expectedStart += $count;
            }
        }
    }

    /** @param array<string, mixed> $overlay @param array<string, mixed>|null $studio */
    private function assertModeCompatibleState(OmrTemplateAuthoringDraft $draft, array $overlay, ?array $studio): void
    {
        $studio ??= [];
        $html = (string) ($studio['htmlCode'] ?? '');
        if ($draft->registration_mode === OmrRegistrationMode::DocumentBound) {
            $submitsMarkerState = array_key_exists('markerPattern', $studio)
                || $draft->marker_profile_id !== null
                || preg_match("/<[^>]+class=[\"'][^\"']*omr-fiducial/i", $html) === 1;
            if ($submitsMarkerState) {
                throw new RuntimeException('registration_mode_incompatible: Document-bound drafts cannot submit fiducial state.');
            }
        }

        if ($draft->qr_mode === OmrQrMode::Disabled) {
            $submitsQrState = ($studio['showQrOverlay'] ?? false) === true
                || collect((array) ($overlay['boxes'] ?? []))->contains(static fn (mixed $box): bool => is_array($box) && ($box['tag'] ?? null) === 'blank_instance_qr')
                || preg_match("/data-omr-overlay=[\"']blank_instance_qr[\"']/i", $html) === 1;
            if ($submitsQrState) {
                throw new RuntimeException('qr_mode_incompatible: QR-disabled drafts cannot submit QR reservation state.');
            }
        }
    }
}
