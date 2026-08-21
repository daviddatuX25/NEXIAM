<?php

namespace App\Assessment\Actions\Omr\Canonical;

use App\Assessment\Omr\Canonical\OmrAuthoringDraftException;
use App\Assessment\Omr\Canonical\OmrAuthoringDraftService;
use App\Assessment\Omr\Canonical\OmrMappingValidator;
use App\Assessment\Omr\Canonical\OmrOverlaySpec;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

use function count;
use function hash;
use function in_array;
use function json_encode;

final class SaveOmrCanonicalStudioState
{
    public function __construct(
        private readonly OmrOverlaySpec $spec,
        private readonly OmrMappingValidator $validator,
        private readonly OmrAuthoringDraftService $drafts,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {
    }

    /**
     * Atomically save mapping, custom overlays, and studio state in a single transaction.
     *
     * @param array<string, mixed> $overlay
     * @param array<string, mixed>|null $mapping
     * @param array<string, mixed>|null $studio
     */
    public function handle(
        OmrTemplateAuthoringDraft $draft,
        User $actor,
        array $overlay,
        ?array $mapping,
        ?array $studio,
        int $expectedLockVersion,
    ): OmrTemplateAuthoringDraft {
        $this->drafts->assertOwner($draft, $actor);

        if (! in_array($draft->stage, [
            OmrTemplateAuthoringDraft::STAGE_PAGE_PREPARED,
            OmrTemplateAuthoringDraft::STAGE_MAPPING_COMPLETE,
            OmrTemplateAuthoringDraft::STAGE_DIGITAL_VERIFIED,
        ], true)) {
            throw new OmrAuthoringDraftException('studio_stage_invalid', 'Studio requires a prepared canonical page.');
        }

        $validatedOverlay = $this->spec->validate($overlay);

        $blocks = (array) ($mapping['blocks'] ?? []);
        $validatedMapping = null;
        $mappingHash = null;
        $warnings = [];
        $fieldBlocks = [];

        if ($blocks !== []) {
            $authoring = (array) $draft->authoring_json;
            $canonical = (array) ($authoring['canonical'] ?? []);
            $pageCount = count((array) ($authoring['pages'] ?? [])) ?: (int) ($mapping['page_count'] ?? 1);

            $result = $this->validator->validate($mapping, [
                'page_width' => $mapping['page_width'] ?? null,
                'page_height' => $mapping['page_height'] ?? null,
                'page_count' => $pageCount,
                'marker_zones' => $canonical['marker_zones'] ?? [],
                'content_zones' => $canonical['content_zones'] ?? [],
            ]);

            if (! $result['valid']) {
                throw new RuntimeException('mapping_invalid: '.json_encode($result['errors'], JSON_THROW_ON_ERROR));
            }

            $fieldBlocks = $result['field_blocks'];
            $warnings = $result['warnings'];
            $mappingHash = hash('sha256', json_encode([
                'mapping' => $mapping,
                'field_blocks' => $fieldBlocks,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

            $validatedMapping = [
                'input' => $mapping,
                'field_blocks' => $fieldBlocks,
                'mapping_hash' => $mappingHash,
                'warnings' => $warnings,
            ];
        }

        return DB::transaction(function () use (
            $draft,
            $actor,
            $validatedOverlay,
            $validatedMapping,
            $studio,
            $mappingHash,
            $fieldBlocks,
            $warnings,
            $expectedLockVersion,
        ): OmrTemplateAuthoringDraft {
            $locked = OmrTemplateAuthoringDraft::query()->lockForUpdate()->findOrFail($draft->id);

            if ((int) $locked->lock_version !== $expectedLockVersion) {
                throw new OmrAuthoringDraftException('concurrency_conflict', 'Draft changed since it was loaded.');
            }

            if ($locked->stage === OmrTemplateAuthoringDraft::STAGE_COMPILED) {
                throw new OmrAuthoringDraftException('draft_read_only', 'Compiled drafts are immutable.');
            }

            $authoring = (array) $locked->authoring_json;
            $previousMappingHash = $authoring['mapping']['mapping_hash'] ?? null;

            $authoring['overlay'] = $validatedOverlay;
            $authoring['mapping'] = $validatedMapping;
            if ($studio !== null) {
                $authoring['studio'] = $studio;
            }

            if ($validatedMapping === null || $mappingHash !== $previousMappingHash) {
                $authoring['verification'] = null;
            }

            $nextStage = $validatedMapping !== null
                ? OmrTemplateAuthoringDraft::STAGE_MAPPING_COMPLETE
                : OmrTemplateAuthoringDraft::STAGE_PAGE_PREPARED;

            if ($locked->stage === OmrTemplateAuthoringDraft::STAGE_DIGITAL_VERIFIED
                && $validatedMapping !== null
                && $mappingHash === $previousMappingHash
                && ($authoring['verification']['status'] ?? null) === 'passed') {
                $nextStage = OmrTemplateAuthoringDraft::STAGE_DIGITAL_VERIFIED;
            }

            $locked->forceFill([
                'authoring_json' => $authoring,
                'lock_version' => $expectedLockVersion + 1,
                'stage' => $nextStage,
            ])->save();

            $saved = $locked->fresh();

            $this->recordAuditEvent->handle('omr.canonical.studio_saved', [
                'draft_id' => $saved->id,
                'overlay_hash' => $validatedOverlay['hash'],
                'mapping_hash' => $mappingHash,
                'field_block_count' => count($fieldBlocks),
                'warnings' => $warnings,
                'has_studio' => $studio !== null,
                'stage' => $nextStage,
            ], (string) $actor->id);

            return $saved;
        });
    }
}
