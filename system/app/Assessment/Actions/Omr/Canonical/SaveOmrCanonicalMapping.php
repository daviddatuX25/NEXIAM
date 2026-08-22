<?php

namespace App\Assessment\Actions\Omr\Canonical;

use App\Assessment\Omr\Canonical\OmrAuthoringDraftException;
use App\Assessment\Omr\Canonical\OmrAuthoringDraftService;
use App\Assessment\Omr\Canonical\OmrMappingValidator;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

use function hash;
use function json_encode;

final class SaveOmrCanonicalMapping
{
    public function __construct(
        private readonly OmrMappingValidator $validator,
        private readonly OmrAuthoringDraftService $drafts,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {
    }

    /** @param array<string, mixed> $mapping */
    public function handle(OmrTemplateAuthoringDraft $draft, User $actor, array $mapping, int $expectedLockVersion): OmrTemplateAuthoringDraft
    {
        $this->drafts->assertOwner($draft, $actor);
        if ($draft->stage !== OmrTemplateAuthoringDraft::STAGE_PAGE_PREPARED) {
            throw new OmrAuthoringDraftException('mapping_stage_invalid', 'Mapping requires a composed canonical page.');
        }
        $authoring = (array) $draft->authoring_json;
        $canonical = (array) ($authoring['canonical'] ?? []);
        $result = $this->validator->validate($mapping, [
            'page_width' => $mapping['page_width'] ?? null,
            'page_height' => $mapping['page_height'] ?? null,
            'page_count' => count((array) ($authoring['pages'] ?? [])),
            'marker_zones' => $canonical['marker_zones'] ?? [],
            'content_zones' => $canonical['content_zones'] ?? [],
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
            $mappingHash = hash('sha256', json_encode([
                'mapping' => $mapping,
                'field_blocks' => $result['field_blocks'],
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
            $authoring['mapping'] = [
                'input' => $mapping,
                'field_blocks' => $result['field_blocks'],
                'mapping_hash' => $mappingHash,
                'warnings' => $result['warnings'],
            ];
            $authoring['verification'] = null;
            $locked->forceFill([
                'stage' => OmrTemplateAuthoringDraft::STAGE_MAPPING_COMPLETE,
                'authoring_json' => $authoring,
                'lock_version' => $expectedLockVersion + 1,
            ])->save();
            $saved = $locked->fresh();
            $this->recordAuditEvent->handle('omr.canonical.mapping_saved', [
                'draft_id' => $saved->id,
                'mapping_hash' => $mappingHash,
                'field_block_count' => count($result['field_blocks']),
                'warnings' => $result['warnings'],
            ], (string) $actor->id);

            return $saved;
        });
    }
}
