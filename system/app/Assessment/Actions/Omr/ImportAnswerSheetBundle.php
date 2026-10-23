<?php

namespace App\Assessment\Actions\Omr;

use App\Assessment\Omr\Canonical\OmrBundlePackage;
use App\Assessment\Omr\Canonical\OmrTemplateAssetStore;
use App\Assessment\Omr\NativeBundle;
use App\Assessment\Omr\OmrQrMode;
use App\Assessment\Omr\OmrRegistrationMode;
use App\Assessment\Support\MaterialOwnerAccess;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\AnswerSheetImportOperation;
use App\Models\Assessment\OmrAnswerSheetTemplate;
use App\Models\Assessment\OmrMarkerProfile;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

use function count;
use function hash;
use function is_array;
use function json_decode;
use function rmdir;
use function sprintf;
use function substr;

final class ImportAnswerSheetBundle
{
    public function __construct(
        private readonly OmrBundlePackage $package,
        private readonly OmrTemplateAssetStore $assetStore,
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly MaterialOwnerAccess $institutionAccess,
        private readonly AnswerSheetImportReviewToken $reviewTokens,
    ) {}

    /**
     * Inspect a private archive without creating a template, draft, or asset catalog rows.
     *
     * @param  (callable(int, int, string): bool)|null  $progress
     * @return array{summary: array<string, mixed>, notes: list<array<string, mixed>>, manifest: array<string, mixed>}
     */
    public function inspect(string $archivePath, ?callable $progress = null): array
    {
        $extracted = $this->package->extractAndValidate($archivePath, $progress);

        try {
            try {
                $rawAuthoring = json_decode($extracted['files']['authoring.json'] ?? '{}', true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $exception) {
                throw new InvalidArgumentException('bundle_authoring_invalid: The exported answer sheet contains invalid authoring data.', previous: $exception);
            }

            if (! is_array($rawAuthoring)) {
                throw new InvalidArgumentException('bundle_authoring_invalid: The exported answer sheet authoring data must be an object.');
            }

            $this->assertPortableAssetReferences($rawAuthoring);
            $validated = $this->validateImportPayload($extracted['manifest'], $extracted['files'], $rawAuthoring);
            $studio = (array) ($rawAuthoring['studio'] ?? []);
            $physicalInventory = $studio['physicalInventory'] ?? null;
            $physicalSections = is_array($physicalInventory) ? (array) ($physicalInventory['sections'] ?? []) : [];
            $physicalItemCount = 0;
            foreach ($physicalSections as $section) {
                if (is_array($section)) {
                    $physicalItemCount += count((array) ($section['items'] ?? []));
                }
            }

            $historicalScoringReference = (array) (
                $extracted['manifest']['source_provenance']['historical_scoring_reference'] ?? []
            );
            $externalProfileId = $extracted['manifest']['test_instrument_version_id']
                ?? $historicalScoringReference['version_id']
                ?? null;
            $externalProfileCode = $extracted['manifest']['test_instrument_code']
                ?? $historicalScoringReference['instrument_code']
                ?? null;
            $notes = [];
            if ($externalProfileId !== null || $externalProfileCode !== null) {
                $notes[] = [
                    'code' => 'external_scoring_reference_not_linked',
                    'severity' => 'information',
                    'cause' => 'The archive mentions a scoring setup, but that setup is not part of this Answer Sheet restore.',
                    'action' => 'Choose a compatible Test Setup later when preparing a Test Kit.',
                ];
            }
            if ($physicalInventory === null) {
                $notes[] = [
                    'code' => 'printed_inventory_not_in_archive',
                    'severity' => 'information',
                    'cause' => 'This archive does not contain a separate printed-section inventory.',
                    'action' => 'Review printed sections in Answer Sheet Studio before using this sheet in a Test Kit.',
                ];
            }

            return [
                'summary' => [
                    'code' => (string) $validated['original_code'],
                    'name' => (string) $validated['original_name'],
                    'page_count' => (int) $validated['page_count'],
                    'recognition_guide_count' => count((array) ($studio['sections'] ?? [])),
                    'printed_section_count' => $physicalInventory === null ? null : count($physicalSections),
                    'physical_item_count' => $physicalInventory === null ? null : $physicalItemCount,
                    'asset_count' => count((array) ($extracted['manifest']['draft_assets'] ?? [])),
                ],
                'notes' => $notes,
                'manifest' => $extracted['manifest'],
            ];
        } finally {
            $this->package->cleanDirectory($extracted['temp_dir']);
            @rmdir($extracted['temp_dir']);
        }
    }

    /**
     * Import a portable answer sheet archive and hydrate an uncompiled authoring draft.
     *
     * @param  UploadedFile|string  $archive  Uploaded file or filesystem path to ZIP
     * @param  array<string, mixed>  $options
     */
    public function handle(
        UploadedFile|string $archive,
        User $actor,
        array $options = [],
    ): OmrTemplateAuthoringDraft {
        $zipPath = $archive instanceof UploadedFile ? $archive->getRealPath() : $archive;
        $institutionId = $this->institutionAccess->institutionId($actor, CapabilityCatalog::GUIDANCE_SCORING_OMR);
        $operationId = $options['import_operation_id'] ?? null;
        $expectedArchiveHash = $options['archive_sha256'] ?? null;
        $progressCallback = $options['progress_callback'] ?? null;

        if ($operationId !== null) {
            $actualArchiveHash = is_string($zipPath) && is_file($zipPath)
                ? hash_file('sha256', $zipPath)
                : false;
            if (! is_string($expectedArchiveHash)
                || ! is_string($actualArchiveHash)
                || ! hash_equals($expectedArchiveHash, $actualArchiveHash)) {
                throw new InvalidArgumentException('import_archive_changed: The checked archive changed before restoration. Inspect the operation again.');
            }
        }

        // Extraction, checksum validation, and content safety run before the short database commit.
        $extracted = $this->package->extractAndValidate(
            (string) $zipPath,
            is_callable($progressCallback) ? $progressCallback : null,
        );
        $manifest = $extracted['manifest'];
        $files = $extracted['files'];
        $tempDir = $extracted['temp_dir'];
        $draftId = null;

        try {
            try {
                $rawAuthoring = json_decode($files['authoring.json'] ?? '{}', true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $exception) {
                throw new InvalidArgumentException('The exported answer sheet contains invalid authoring JSON.', previous: $exception);
            }
            if (! is_array($rawAuthoring)) {
                throw new InvalidArgumentException('The exported answer sheet authoring data must be an object.');
            }
            $this->assertPortableAssetReferences($rawAuthoring);

            $validated = $this->validateImportPayload($manifest, $files, $rawAuthoring);

            return DB::transaction(function () use ($manifest, $files, $rawAuthoring, $actor, $institutionId, $validated, $options, &$draftId): OmrTemplateAuthoringDraft {
                $operation = null;
                if (is_string($options['import_operation_id'] ?? null)) {
                    $operation = AnswerSheetImportOperation::query()
                        ->whereKey($options['import_operation_id'])
                        ->lockForUpdate()
                        ->firstOrFail();
                    $this->assertOperationCommit($operation, $actor, $institutionId, $options);

                    if ($operation->result_draft_id !== null) {
                        return $operation->resultDraft()->with(['template', 'markerProfile'])->firstOrFail();
                    }
                }
                $templateMeta = $validated['template_meta'];
                $origCode = $validated['original_code'];
                $origName = $validated['original_name'];
                $pageCount = $validated['page_count'];
                $registrationMode = $validated['registration_mode'];
                $qrMode = $validated['qr_mode'];
                $sourceKind = $validated['source_kind'];
                $nativeTemplate = $validated['native_template'];
                // 2. Marker profile reconciliation
                $markerProfile = $this->reconcileMarkerProfile($templateMeta, $files, $actor, $institutionId);

                // 3. Immutability & Code Collision Resolution
                $allocatedCode = $this->allocateCollisionSafeCode($origCode, $institutionId);

                $template = OmrAnswerSheetTemplate::query()->firstOrCreate(
                    ['institution_id' => $institutionId, 'code' => $allocatedCode],
                    [
                        'name' => $origName,
                        'is_active' => false,
                        'is_synthetic' => true,
                        'created_by_user_id' => $actor->id,
                    ],
                );
                if (! $template->wasRecentlyCreated && ! (bool) $template->is_synthetic) {
                    throw new InvalidArgumentException('Imported bundle provenance must match the existing template.');
                }

                // 4. Create new uncompiled authoring draft
                $draft = OmrTemplateAuthoringDraft::query()->create([
                    'institution_id' => $institutionId,
                    'is_synthetic' => true,
                    'template_id' => $template->id,
                    'code' => $allocatedCode,
                    'name' => $origName,
                    'source_kind' => $sourceKind,
                    'registration_mode' => $registrationMode,
                    'qr_mode' => $qrMode,
                    'stage' => OmrTemplateAuthoringDraft::STAGE_DRAFT,
                    'marker_profile_id' => $markerProfile?->id,
                    'compiled_version_id' => null,
                    'lock_version' => 0,
                    'owner_user_id' => $actor->id,
                    'authoring_json' => [],
                    'requested_page_count' => $pageCount,
                    'test_instrument_version_id' => null,
                ]);
                $draftId = (int) $draft->id;

                // 5. Persist unpacked assets via OmrTemplateAssetStore and build logical token map
                $tokenToDbMap = [];
                $oldToNewAssetId = [];
                foreach ((array) ($manifest['draft_assets'] ?? []) as $assetEntry) {
                    if (! is_array($assetEntry)) {
                        continue;
                    }
                    $oldId = (int) ($assetEntry['old_id'] ?? 0);
                    $zipPath = (string) ($assetEntry['zip_path'] ?? '');
                    $kind = (string) ($assetEntry['kind'] ?? '');
                    if ($oldId <= 0 || ! isset($files[$zipPath])) {
                        throw new InvalidArgumentException('The imported draft asset manifest is incomplete.');
                    }
                    $bytes = $files[$zipPath];
                    $expectedHash = (string) ($assetEntry['sha256'] ?? '');
                    if ($expectedHash === '' || ! hash_equals($expectedHash, hash('sha256', $bytes))) {
                        throw new InvalidArgumentException('The imported draft asset checksum does not match its manifest.');
                    }
                    $pageNo = isset($assetEntry['page_no']) ? (int) $assetEntry['page_no'] : null;
                    $asset = $this->assetStore->putDraftAsset(
                        $draft,
                        $kind,
                        $bytes,
                        (string) ($assetEntry['mime'] ?? 'application/octet-stream'),
                        $pageNo,
                        (array) ($assetEntry['metadata'] ?? []),
                    );
                    $oldToNewAssetId[$oldId] = (int) $asset->id;
                }
                foreach ($oldToNewAssetId as $oldId => $newId) {
                    $tokenToDbMap['logical:draft_asset_'.$oldId] = $newId;
                }

                if (isset($files['native/template.json'])) {
                    $asset = $this->assetStore->putDraftAsset($draft, OmrTemplateAsset::KIND_NATIVE_TEMPLATE, $files['native/template.json'], 'application/json', 1);
                    $tokenToDbMap['logical:native_template'] = $asset->id;
                }
                if (isset($files['native/config.json'])) {
                    $asset = $this->assetStore->putDraftAsset($draft, OmrTemplateAsset::KIND_NATIVE_CONFIG, $files['native/config.json'], 'application/json', 1);
                    $tokenToDbMap['logical:native_config'] = $asset->id;
                }
                if (isset($files['native/manifest.json'])) {
                    $asset = $this->assetStore->putDraftAsset($draft, OmrTemplateAsset::KIND_NATIVE_MANIFEST, $files['native/manifest.json'], 'application/json', 1);
                    $tokenToDbMap['logical:native_manifest'] = $asset->id;
                }
                if (isset($files['print/sheet.pdf'])) {
                    $asset = $this->assetStore->putDraftAsset($draft, OmrTemplateAsset::KIND_PRINT_PDF, $files['print/sheet.pdf'], 'application/pdf', 1);
                    $tokenToDbMap['logical:print_pdf'] = $asset->id;
                }

                // Reference SVGs and Proof previews
                foreach ($files as $relPath => $bytes) {
                    if (str_starts_with($relPath, 'native/reference-page-') && str_ends_with($relPath, '.svg')) {
                        preg_match('/reference-page-(\d+)\.svg$/', $relPath, $matches);
                        $pageNo = isset($matches[1]) ? (int) $matches[1] : 1;
                        $asset = $this->assetStore->putDraftAsset($draft, OmrTemplateAsset::KIND_REFERENCE_PAGE, $bytes, 'image/svg+xml', $pageNo);
                        $tokenToDbMap[sprintf('logical:reference_page_%d', $pageNo)] = $asset->id;
                    } elseif (str_starts_with($relPath, 'print/preview-page-') && str_ends_with($relPath, '.png')) {
                        preg_match('/preview-page-(\d+)\.png$/', $relPath, $matches);
                        $pageNo = isset($matches[1]) ? (int) $matches[1] : 1;
                        $asset = $this->assetStore->putDraftAsset($draft, OmrTemplateAsset::KIND_DIGITAL_PROOF, $bytes, 'image/png', $pageNo);
                        $tokenToDbMap[sprintf('logical:preview_page_%d', $pageNo)] = $asset->id;
                    }
                }

                // 6. Rewrite typed asset references while retaining formal references as provenance only.
                $hydratedAuthoring = $this->rewriteLogicalTokensToDatabaseIds((array) $rawAuthoring, $tokenToDbMap);
                $mappingReferences = [];
                if (is_array($hydratedAuthoring['mapping'] ?? null)) {
                    [$hydratedAuthoring['mapping'], $mappingReferences] = $this->stripScoringReferences($hydratedAuthoring['mapping']);
                }

                $historicalScoringReference = (array) ($manifest['source_provenance']['historical_scoring_reference'] ?? []);
                $externalScoringReference = [
                    'manifest_version_id' => $manifest['test_instrument_version_id'] ?? $historicalScoringReference['version_id'] ?? null,
                    'manifest_instrument_code' => $manifest['test_instrument_code'] ?? $historicalScoringReference['instrument_code'] ?? null,
                    'mapping_references' => $mappingReferences,
                    'linked' => false,
                ];
                $hydratedAuthoring['import_provenance'] = [
                    'schema_version' => $manifest['schema_version'] ?? OmrBundlePackage::SCHEMA_VERSION,
                    'imported_at' => now()->toIso8601String(),
                    'source_provenance' => $manifest['source_provenance'] ?? [],
                    'external_scoring_reference' => $externalScoringReference,
                    'original_code' => $origCode,
                    'allocated_code' => $allocatedCode,
                ];

                $draft->update([
                    'authoring_json' => $hydratedAuthoring,
                ]);

                if ($operation !== null) {
                    $studio = (array) ($hydratedAuthoring['studio'] ?? []);
                    $physicalInventory = $studio['physicalInventory'] ?? null;
                    $physicalSections = is_array($physicalInventory) ? (array) ($physicalInventory['sections'] ?? []) : [];
                    $physicalItemCount = 0;
                    foreach ($physicalSections as $section) {
                        if (is_array($section)) {
                            $physicalItemCount += count((array) ($section['items'] ?? []));
                        }
                    }

                    $operation->forceFill([
                        'phase' => AnswerSheetImportOperation::PHASE_COMPLETED,
                        'revision' => (int) $operation->revision + 1,
                        'progress_done' => (int) $operation->progress_total,
                        'lease_token' => null,
                        'lease_expires_at' => null,
                        'last_heartbeat_at' => now(),
                        'result_draft_id' => $draft->id,
                        'result_receipt' => (string) Str::uuid(),
                        'result_summary' => [
                            'code' => (string) $draft->code,
                            'name' => (string) $draft->name,
                            'page_count' => (int) $draft->requested_page_count,
                            'recognition_guide_count' => count((array) ($studio['sections'] ?? [])),
                            'printed_section_count' => $physicalInventory === null ? null : count($physicalSections),
                            'physical_item_count' => $physicalInventory === null ? null : $physicalItemCount,
                        ],
                        'completed_at' => now(),
                        'safe_error' => null,
                        'error_code' => null,
                    ])->save();
                }

                // 7. Audit log is the final write in the transaction.
                $this->recordAuditEvent->handle('omr.answer_sheet_bundle_imported', [
                    'institution_id' => $institutionId,
                    'draft_id' => $draft->id,
                    'template_id' => $template->id,
                    'allocated_code' => $allocatedCode,
                    'original_code' => $origCode,
                    'registration_mode' => $registrationMode->value,
                    'qr_mode' => $qrMode->value,
                    'member_count' => count($files),
                    'operation_id' => $operation?->id,
                ], (string) $actor->id);

                return $draft->fresh(['template', 'markerProfile']);
            }, attempts: 3);
        } catch (Throwable $exception) {
            if ($draftId !== null) {
                Storage::disk((string) config('omr.canonical.storage.disk', 'local'))
                    ->deleteDirectory(sprintf('omr-canonical/drafts/%d', $draftId));
            }

            throw $exception;
        } finally {
            $this->package->cleanDirectory($tempDir);
            @rmdir($tempDir);
        }
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @param  array<string, string>  $files
     * @param  array<string, mixed>  $rawAuthoring
     * @return array{
     *   template_meta: array<string, mixed>,
     *   original_code: string,
     *   original_name: string,
     *   page_count: int,
     *   registration_mode: OmrRegistrationMode,
     *   qr_mode: OmrQrMode,
     *   source_kind: string,
     *   native_template: ?array<string, mixed>
     * }
     */
    private function validateImportPayload(array $manifest, array $files, array $rawAuthoring): array
    {
        $templateMeta = (array) ($manifest['template'] ?? []);
        $pageCount = (int) ($manifest['requested_page_count'] ?? $templateMeta['page_count'] ?? 1);
        if ($pageCount < 1 || $pageCount > 4) {
            throw new InvalidArgumentException('bundle_page_count_invalid: Imported answer sheets must contain one to four pages.');
        }

        $registrationMode = OmrRegistrationMode::tryFrom((string) ($manifest['registration_mode'] ?? $templateMeta['registration_mode'] ?? ''));
        $qrMode = OmrQrMode::tryFrom((string) ($manifest['qr_mode'] ?? $templateMeta['qr_mode'] ?? ''));
        if ($registrationMode === null || $qrMode === null) {
            throw new InvalidArgumentException('bundle_paper_policy_invalid: The imported answer sheet must declare supported registration and QR modes.');
        }

        $sourceKind = (string) ($manifest['source_kind'] ?? OmrTemplateAuthoringDraft::SOURCE_HTML);
        if (! in_array($sourceKind, OmrTemplateAuthoringDraft::SOURCE_KINDS, true)) {
            throw new InvalidArgumentException('bundle_source_kind_invalid: The imported answer sheet declares an unsupported source kind.');
        }

        if ($registrationMode === OmrRegistrationMode::DocumentBound
            && (isset($templateMeta['marker_profile']) || isset($files['native/marker.jpg']))) {
            throw new InvalidArgumentException('bundle_marker_policy_invalid: Document-bound answer sheets cannot carry a marker profile.');
        }
        if ($registrationMode === OmrRegistrationMode::FiducialAnchored
            && (! isset($templateMeta['marker_profile']) || ! isset($files['native/marker.jpg']))) {
            throw new InvalidArgumentException('bundle_marker_required: Fiducial-anchored answer sheets require their marker profile and image.');
        }

        try {
            $nativeTemplate = isset($files['native/template.json'])
                ? json_decode($files['native/template.json'], true, 512, JSON_THROW_ON_ERROR)
                : null;
        } catch (\JsonException $exception) {
            throw new InvalidArgumentException('bundle_native_template_invalid: The native template data is not valid JSON.', previous: $exception);
        }
        if ($nativeTemplate !== null && ! is_array($nativeTemplate)) {
            throw new InvalidArgumentException('bundle_native_template_invalid: The native template data must be an object.');
        }
        if ($qrMode === OmrQrMode::Disabled
            && is_array($nativeTemplate)
            && (isset($nativeTemplate['qr_reservation'])
                || array_filter(
                    (array) ($nativeTemplate['visualComponents'] ?? []),
                    static fn (mixed $component): bool => is_array($component)
                        && in_array($component['type'] ?? null, ['qr', 'blank_instance_qr'], true),
                ) !== [])) {
            throw new InvalidArgumentException('bundle_qr_policy_invalid: QR-disabled answer sheets cannot import production QR geometry.');
        }

        $seenAssetIds = [];
        foreach ((array) ($manifest['draft_assets'] ?? []) as $assetEntry) {
            if (! is_array($assetEntry)) {
                throw new InvalidArgumentException('bundle_asset_manifest_invalid: The draft asset manifest contains an invalid entry.');
            }

            $oldId = (int) ($assetEntry['old_id'] ?? 0);
            $zipPath = (string) ($assetEntry['zip_path'] ?? '');
            $assetKind = (string) ($assetEntry['kind'] ?? '');
            $assetBytes = $files[$zipPath] ?? null;
            $expectedHash = (string) ($assetEntry['sha256'] ?? '');
            if ($oldId <= 0 || isset($seenAssetIds[$oldId])
                || ! in_array($assetKind, OmrTemplateAsset::KINDS, true)
                || ! is_string($assetBytes)
                || $expectedHash === '' || ! hash_equals($expectedHash, hash('sha256', $assetBytes))) {
                throw new InvalidArgumentException('bundle_asset_manifest_invalid: A draft asset is missing, duplicated, has an unsupported type, or does not match its checksum.');
            }
            $seenAssetIds[$oldId] = true;
        }
        $this->validateArchivePageReferences($manifest, $rawAuthoring, $nativeTemplate, $pageCount);
        $this->validatePhysicalInventory($rawAuthoring, $nativeTemplate, $pageCount);

        return [
            'template_meta' => $templateMeta,
            'original_code' => (string) ($templateMeta['code'] ?? 'IMPORT'),
            'original_name' => (string) ($templateMeta['display_name'] ?? 'Imported Answer Sheet'),
            'page_count' => $pageCount,
            'registration_mode' => $registrationMode,
            'qr_mode' => $qrMode,
            'source_kind' => $sourceKind,
            'native_template' => $nativeTemplate,
        ];
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @param  array<string, mixed>  $authoring
     * @param  array<string, mixed>|null  $nativeTemplate
     */
    private function validateArchivePageReferences(
        array $manifest,
        array $authoring,
        ?array $nativeTemplate,
        int $pageCount,
    ): void {
        $manifestPages = (array) ($manifest['pages'] ?? []);
        if ($manifestPages !== [] && count($manifestPages) !== $pageCount) {
            throw new InvalidArgumentException('bundle_page_manifest_invalid: The archive page list does not match its declared page count.');
        }

        $seenPages = [];
        foreach ($manifestPages as $index => $page) {
            if (! is_array($page)) {
                throw new InvalidArgumentException('bundle_page_manifest_invalid: The archive page list contains an invalid entry.');
            }
            $pageNo = (int) ($page['page_no'] ?? 0);
            if ($pageNo < 1 || $pageNo > $pageCount || isset($seenPages[$pageNo])) {
                throw new InvalidArgumentException('bundle_page_identity_invalid: Archive page identities must be unique and within the declared page count.');
            }
            $seenPages[$pageNo] = true;
        }

        foreach ((array) ($manifest['draft_assets'] ?? []) as $assetEntry) {
            if (! is_array($assetEntry) || ! array_key_exists('page_no', $assetEntry) || $assetEntry['page_no'] === null) {
                continue;
            }
            $pageNo = (int) $assetEntry['page_no'];
            if ($pageNo < 1 || $pageNo > $pageCount) {
                throw new InvalidArgumentException('bundle_asset_page_invalid: An asset is assigned to a page outside the declared sheet.');
            }
        }

        $authoringPages = (array) ($authoring['pages'] ?? []);
        foreach ($authoringPages as $page) {
            if (! is_array($page) || ! array_key_exists('page_no', $page)) {
                continue;
            }
            $pageNo = (int) $page['page_no'];
            if ($pageNo < 1 || $pageNo > $pageCount) {
                throw new InvalidArgumentException('bundle_authoring_page_invalid: Authoring data refers to a page outside the declared sheet.');
            }
        }

        $isDraft = (bool) ($manifest['source_provenance']['is_draft'] ?? $manifest['is_draft'] ?? false);
        $nativePages = (array) ($nativeTemplate['pages'] ?? []);
        if (! $isDraft && $nativePages !== [] && count($nativePages) !== $pageCount) {
            throw new InvalidArgumentException('bundle_native_pages_invalid: Native page data does not match the declared sheet page count.');
        }
        $nativePageNumbers = [];
        foreach (array_values($nativePages) as $index => $page) {
            if (! is_array($page)) {
                throw new InvalidArgumentException('bundle_native_pages_invalid: Native page data contains an invalid page.');
            }
            $pageNo = (int) ($page['page_no'] ?? $index + 1);
            if ($pageNo < 1 || $pageNo > $pageCount || isset($nativePageNumbers[$pageNo])) {
                throw new InvalidArgumentException('bundle_native_pages_invalid: Native page identities are invalid.');
            }
            $nativePageNumbers[$pageNo] = true;
        }
    }

    /** @param array<string, mixed> $authoring @param array<string, mixed>|null $nativeTemplate */
    private function validatePhysicalInventory(array $authoring, ?array $nativeTemplate, int $pageCount): void
    {
        $studio = (array) ($authoring['studio'] ?? []);
        if (! array_key_exists('physicalInventory', $studio)) {
            return;
        }

        $inventory = $studio['physicalInventory'];
        if (! is_array($inventory)
            || ! is_int($inventory['revision'] ?? null)
            || (int) $inventory['revision'] < 1
            || ! is_array($inventory['sections'] ?? null)
            || ! array_is_list($inventory['sections'])) {
            throw new InvalidArgumentException('bundle_physical_inventory_invalid: The printed-section inventory is malformed.');
        }

        $guidesById = [];
        foreach ((array) ($studio['sections'] ?? []) as $guide) {
            if (! is_array($guide) || ! is_string($guide['id'] ?? null) || $guide['id'] === '') {
                continue;
            }
            if (isset($guidesById[$guide['id']])) {
                throw new InvalidArgumentException('bundle_physical_inventory_invalid: Recognition guide identities are ambiguous.');
            }
            $guidesById[$guide['id']] = $guide;
        }

        $nativeLabels = [];
        foreach ((array) ($nativeTemplate['pages'] ?? []) as $page) {
            foreach ((array) (($page['fieldBlocks'] ?? null) ?: []) as $block) {
                foreach ((array) ($block['fieldLabels'] ?? []) as $declaration) {
                    foreach (NativeBundle::expandLabel((string) $declaration) as $label) {
                        $nativeLabels[(string) $label] = true;
                    }
                }
            }
        }

        $sectionUids = [];
        $itemUids = [];
        $nativeLabelUids = [];
        $displayOrders = [];

        foreach ($inventory['sections'] as $section) {
            if (! is_array($section)) {
                throw new InvalidArgumentException('bundle_physical_inventory_invalid: The printed-section inventory contains an invalid section.');
            }

            $sectionUid = $section['printed_section_uid'] ?? null;
            $sectionName = $section['name'] ?? null;
            $pageNo = $section['page_no'] ?? null;
            $expectedCount = $section['expected_item_count'] ?? null;
            $displayOrder = $section['display_order'] ?? null;
            $numbering = $section['numbering'] ?? null;
            $items = $section['items'] ?? null;
            if (! is_string($sectionUid) || $sectionUid === '' || strlen($sectionUid) > 36
                || isset($sectionUids[$sectionUid])
                || ! is_string($sectionName) || trim($sectionName) === ''
                || ! is_int($pageNo) || $pageNo < 1 || $pageNo > $pageCount
                || ! is_int($expectedCount) || ! is_int($displayOrder) || $displayOrder < 1
                || isset($displayOrders[$displayOrder])
                || ! is_array($numbering)
                || ! is_int($numbering['start'] ?? null) || (int) $numbering['start'] < 1
                || ! is_int($numbering['step'] ?? null) || (int) $numbering['step'] < 1
                || ! is_array($items) || ! array_is_list($items)
                || $expectedCount !== count($items)) {
                throw new InvalidArgumentException('bundle_physical_inventory_invalid: Printed sections need unique identities, valid pages and numbering, and an exact item count.');
            }
            $sectionUids[$sectionUid] = true;
            $displayOrders[$displayOrder] = true;

            foreach ($items as $item) {
                if (! is_array($item)) {
                    throw new InvalidArgumentException('bundle_physical_inventory_invalid: A printed section contains an invalid physical location.');
                }

                $itemUid = $item['physical_item_uid'] ?? null;
                $itemPage = $item['page_no'] ?? null;
                $printedNumber = $item['printed_number'] ?? null;
                $nativeLabel = $item['native_label'] ?? null;
                $guideId = $item['guide_id'] ?? null;
                $tokens = $item['option_tokens'] ?? null;
                if (! is_string($itemUid) || $itemUid === '' || strlen($itemUid) > 36
                    || isset($itemUids[$itemUid])
                    || ! is_int($itemPage) || $itemPage !== $pageNo
                    || ! is_int($printedNumber) || $printedNumber < 1
                    || ($nativeLabel !== null && (! is_string($nativeLabel)
                        || preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,127}$/D', $nativeLabel) !== 1))
                    || ($guideId !== null && (! is_string($guideId) || ! isset($guidesById[$guideId])))
                    || ($guideId !== null && $nativeLabel === null)
                    || ! is_array($tokens) || ! array_is_list($tokens) || count($tokens) < 2 || count($tokens) > 10) {
                    throw new InvalidArgumentException('bundle_physical_inventory_invalid: A physical location has an invalid identity, guide, page, label or option list.');
                }

                foreach ($tokens as $token) {
                    if (! is_string($token) || trim($token) === '' || strlen($token) > 32) {
                        throw new InvalidArgumentException('bundle_physical_inventory_invalid: Physical option tokens must be non-empty literal strings of at most 32 characters.');
                    }
                }
                if (count(array_unique($tokens, SORT_STRING)) !== count($tokens)) {
                    throw new InvalidArgumentException('bundle_physical_inventory_invalid: A physical location repeats an option token.');
                }
                if ($nativeLabel !== null
                    && (isset($nativeLabelUids[$nativeLabel])
                        || ($nativeLabels !== [] && ! isset($nativeLabels[$nativeLabel])))) {
                    throw new InvalidArgumentException('bundle_physical_inventory_invalid: A physical native label is duplicated or absent from the supplied native page data.');
                }

                $guide = $guideId === null ? null : $guidesById[$guideId];
                if ($guide !== null) {
                    $guidePage = $guide['page_no'] ?? null;
                    $guideLabel = $guide['native_label'] ?? $guide['field_label'] ?? null;
                    $guideTokens = $guide['option_tokens'] ?? $guide['bubbleValues'] ?? null;
                    if (($guidePage !== null && (int) $guidePage !== $pageNo)
                        || (is_string($guideLabel) && $guideLabel !== $nativeLabel)
                        || (is_array($guideTokens) && array_values($guideTokens) !== array_values($tokens))) {
                        throw new InvalidArgumentException('bundle_physical_inventory_invalid: The physical location disagrees with its linked recognition guide.');
                    }
                }

                $itemUids[$itemUid] = true;
                if ($nativeLabel !== null) {
                    $nativeLabelUids[$nativeLabel] = true;
                }
            }
        }
    }

    /** @param array<string, mixed> $options */
    private function assertOperationCommit(
        AnswerSheetImportOperation $operation,
        User $actor,
        int $institutionId,
        array $options,
    ): void {
        $freshInstitutionId = $this->institutionAccess->institutionId($actor, CapabilityCatalog::GUIDANCE_SCORING_OMR);
        $confirmationToken = (string) ($options['confirmation_review_token'] ?? '');
        $expectedGeneration = (int) ($options['generation'] ?? 0);
        $expectedLeaseToken = (string) ($options['lease_token'] ?? '');
        $expectedArchiveHash = (string) ($options['archive_sha256'] ?? '');

        if ($freshInstitutionId !== $institutionId
            || (int) $operation->institution_id !== $institutionId
            || (int) $operation->owner_user_id !== (int) $actor->getKey()
            || (int) $operation->confirmed_by_user_id !== (int) $actor->getKey()
            || ! hash_equals((string) $operation->archive_sha256, $expectedArchiveHash)
            || ! hash_equals((string) $operation->confirmation_review_token, $confirmationToken)
            || ! hash_equals($this->reviewTokens->forOperation($operation), $confirmationToken)
            || (int) $operation->confirmed_review_revision !== (int) $operation->review_revision) {
            throw new InvalidArgumentException('import_confirmation_stale: The reviewed archive or authorization changed. Inspect it again before restoring.');
        }

        if ($operation->result_draft_id !== null) {
            if ($operation->phase !== AnswerSheetImportOperation::PHASE_COMPLETED) {
                throw new InvalidArgumentException('import_result_inconsistent: This import result is not available for replay.');
            }

            return;
        }

        if ($operation->phase !== AnswerSheetImportOperation::PHASE_RESTORING
            || (int) $operation->generation !== $expectedGeneration
            || $expectedLeaseToken === ''
            || ! hash_equals((string) $operation->lease_token, $expectedLeaseToken)
            || $operation->lease_expires_at === null
            || $operation->lease_expires_at->isPast()) {
            throw new InvalidArgumentException('import_operation_stale: This restore worker no longer owns the current operation generation.');
        }
    }

    /**
     * @param  array<string, mixed>  $mapping
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function stripScoringReferences(array $mapping, string $prefix = 'mapping'): array
    {
        $references = [];
        foreach ($mapping as $key => $value) {
            $path = $prefix.'.'.(string) $key;
            if (is_string($key)
                && (str_starts_with($key, 'test_instrument_') || $key === 'instrument_version_id')) {
                $references[$path] = $value;
                unset($mapping[$key]);
            } elseif (is_array($value)) {
                [$mapping[$key], $nestedReferences] = $this->stripScoringReferences($value, $path);
                $references += $nestedReferences;
            }
        }

        return [$mapping, $references];
    }

    private function reconcileMarkerProfile(array $templateMeta, array $files, User $actor, int $institutionId): ?OmrMarkerProfile
    {
        $markerBytes = $files['native/marker.jpg'] ?? null;
        if ($markerBytes === null) {
            return null;
        }

        $markerHash = hash('sha256', $markerBytes);

        // Check for existing profile matching exact hash
        $existing = OmrMarkerProfile::query()
            ->where('asset_sha256', $markerHash)
            ->first();
        if ($existing !== null) {
            return $existing;
        }

        // Create local candidate profile from embedded marker
        $disk = (string) config('omr.canonical.storage.disk', 'local');
        $storedPath = sprintf('omr-canonical/institutions/%d/markers/marker-%s.jpg', $institutionId, $markerHash);
        Storage::disk($disk)->put($storedPath, $markerBytes);

        $profileMeta = (array) ($templateMeta['marker_profile'] ?? []);

        return OmrMarkerProfile::query()->create([
            'code' => 'IMP-'.substr($markerHash, 0, 8),
            'version' => 1,
            'family' => $profileMeta['family'] ?? 'bullseye',
            'asset_path' => $storedPath,
            'asset_sha256' => $markerHash,
            'size_mm' => $profileMeta['size_mm'] ?? 15.0,
            'quiet_zone_mm' => $profileMeta['quiet_zone_mm'] ?? 5.0,
            'status' => OmrMarkerProfile::STATUS_CANDIDATE, // Candidate until qualified on local hardware
            'created_by_user_id' => $actor->id,
        ]);
    }

    private function allocateCollisionSafeCode(string $origCode, int $institutionId): string
    {
        $query = OmrAnswerSheetTemplate::query()->where('institution_id', $institutionId);
        if (! (clone $query)->where('code', $origCode)->exists()) {
            return $origCode;
        }

        $candidate = $origCode.'-IMPORT';
        if (! (clone $query)->where('code', $candidate)->exists()) {
            return $candidate;
        }

        for ($i = 2; $i <= 99; $i++) {
            $candidate = sprintf('%s-IMPORT-%d', $origCode, $i);
            if (! (clone $query)->where('code', $candidate)->exists()) {
                return $candidate;
            }
        }

        return sprintf('%s-IMP-%s', $origCode, substr(hash('sha256', uniqid('', true)), 0, 6));
    }

    /** @param array<string|int, mixed> $authoring */
    private function assertPortableAssetReferences(array $authoring, bool $assetReferences = false): void
    {
        foreach ($authoring as $key => $value) {
            $isAssetReference = $assetReferences
                || (is_string($key) && preg_match('/(?:^|_)asset_ids?$/', $key) === 1);
            if (is_array($value)) {
                $this->assertPortableAssetReferences($value, $isAssetReference);
            } elseif (! $isAssetReference && is_string($value) && str_starts_with($value, 'logical:')) {
                throw new InvalidArgumentException('This backup contains damaged numeric or text fields from an older export. Keep the original ZIP unchanged and recover it before importing.');
            }
        }
    }

    /**
     * @param  array<string, mixed>  $authoring
     * @param  array<string, int>  $tokenToDbMap
     * @return array<string, mixed>
     */
    private function rewriteLogicalTokensToDatabaseIds(array $authoring, array $tokenToDbMap): array
    {
        foreach ($authoring as $key => $value) {
            if (is_string($key) && preg_match('/(?:^|_)asset_ids?$/', $key) === 1) {
                $resolve = static function (mixed $token) use ($tokenToDbMap): mixed {
                    if (! is_string($token) || ! str_starts_with($token, 'logical:')) {
                        return $token;
                    }

                    return $tokenToDbMap[$token]
                        ?? throw new InvalidArgumentException('The imported answer sheet references a missing asset.');
                };
                $authoring[$key] = is_array($value) ? array_map($resolve, $value) : $resolve($value);
            } elseif (is_array($value)) {
                $authoring[$key] = $this->rewriteLogicalTokensToDatabaseIds($value, $tokenToDbMap);
            }
        }

        return $authoring;
    }
}
