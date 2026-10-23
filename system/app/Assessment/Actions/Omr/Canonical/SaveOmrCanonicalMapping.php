<?php

namespace App\Assessment\Actions\Omr\Canonical;

use App\Assessment\Omr\Canonical\OmrAuthoringDraftException;
use App\Assessment\Omr\Canonical\OmrAuthoringDraftService;
use App\Assessment\Omr\Canonical\OmrCanonicalGeometryIdentity;
use App\Assessment\Omr\Canonical\OmrMappingValidator;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

use function in_array;
use function json_encode;

final class SaveOmrCanonicalMapping
{
    public function __construct(
        private readonly OmrMappingValidator $validator,
        private readonly OmrAuthoringDraftService $drafts,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    /** @param array<string, mixed> $mapping */
    public function handle(OmrTemplateAuthoringDraft $draft, User $actor, array $mapping, int $expectedLockVersion): OmrTemplateAuthoringDraft
    {
        $this->drafts->assertOwner($draft, $actor);
        if ($draft->stage !== OmrTemplateAuthoringDraft::STAGE_PAGE_PREPARED) {
            throw new OmrAuthoringDraftException('mapping_stage_invalid', 'Mapping requires a prepared answer-sheet page.');
        }
        $uploaded = in_array($draft->source_kind, [
            OmrTemplateAuthoringDraft::SOURCE_PDF,
            OmrTemplateAuthoringDraft::SOURCE_IMAGE,
            OmrTemplateAuthoringDraft::SOURCE_DOCX,
        ], true);
        $authoring = (array) $draft->authoring_json;
        unset($mapping['test_instrument_version_id']);
        foreach ($mapping['blocks'] ?? [] as &$block) {
            if (is_array($block)) {
                unset($block['subtest_code'], $block['item_codes']);
            }
        }
        unset($block);
        $canonical = (array) ($authoring['canonical'] ?? []);
        $result = $this->validator->validate($mapping, [
            'page_width' => $mapping['page_width'] ?? null,
            'page_height' => $mapping['page_height'] ?? null,
            'page_count' => count((array) ($authoring['pages'] ?? [])),
            'marker_zones' => $canonical['marker_zones'] ?? [],
            'content_zones' => $canonical['content_zones'] ?? [],
            'require_declaration' => $uploaded,
            'source_kind' => $draft->source_kind,
            'is_uploaded' => $uploaded,
        ]);
        if (! $result['valid']) {
            throw new RuntimeException('mapping_invalid: '.json_encode($result['errors'], JSON_THROW_ON_ERROR));
        }

        return DB::transaction(function () use ($draft, $actor, $mapping, $result, $expectedLockVersion): OmrTemplateAuthoringDraft {
            $locked = OmrTemplateAuthoringDraft::query()->lockForUpdate()->findOrFail($draft->id);
            if ((int) $locked->lock_version !== $expectedLockVersion) {
                throw new OmrAuthoringDraftException('concurrency_conflict', 'Draft changed since it was loaded.');
            }
            $authoring = (array) $locked->authoring_json;
            $authoring['mapping'] = [
                'input' => $mapping,
                'field_blocks' => $result['field_blocks'],
                'warnings' => $result['warnings'],
            ];

            $tempDraft = clone $locked;
            $tempDraft->authoring_json = $authoring;
            $mappingHash = OmrCanonicalGeometryIdentity::forDraft($tempDraft);
            $authoring['mapping']['mapping_hash'] = $mappingHash;
            $authoring['verification'] = null;

            $locked->forceFill([
                'stage' => OmrTemplateAuthoringDraft::STAGE_MAPPING_COMPLETE,
                'authoring_json' => $authoring,
                'lock_version' => $expectedLockVersion + 1,
                'test_instrument_version_id' => null,
            ])->save();
            $saved = $locked->fresh();
            $this->recordAuditEvent->handle('omr.canonical.mapping_saved', [
                'draft_id' => $saved->id,
                'mapping_hash' => $mappingHash,
                'geometry_identity' => $mappingHash,
                'field_block_count' => count($result['field_blocks']),
                'warnings' => $result['warnings'],
            ], (string) $actor->id);

            return $saved;
        });
    }
}
