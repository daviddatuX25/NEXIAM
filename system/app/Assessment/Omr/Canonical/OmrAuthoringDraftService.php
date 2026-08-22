<?php

namespace App\Assessment\Omr\Canonical;

use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

use function array_replace;
use function config;

final class OmrAuthoringDraftException extends RuntimeException
{
    /** @param array<string, mixed> $context */
    public function __construct(public readonly string $errorCode, string $message, public readonly array $context = [])
    {
        parent::__construct($errorCode.': '.$message);
    }
}

final class OmrAuthoringDraftService
{
    /**
     * @param array<string, mixed> $authoring
     */
    public function create(User $actor, string $code, string $name, string $sourceKind, array $authoring = []): OmrTemplateAuthoringDraft
    {
        if (! in_array($sourceKind, OmrTemplateAuthoringDraft::SOURCE_KINDS, true)) {
            throw new OmrAuthoringDraftException('unsupported_source', 'Source kind is not supported.');
        }
        if (preg_match('/^[A-Z0-9][A-Z0-9._-]{2,63}$/', $code) !== 1) {
            throw new OmrAuthoringDraftException('draft_code_invalid', 'Draft code must be uppercase and URL-safe.');
        }

        return OmrTemplateAuthoringDraft::query()->create([
            'code' => $code,
            'name' => $name,
            'source_kind' => $sourceKind,
            'stage' => OmrTemplateAuthoringDraft::STAGE_DRAFT,
            'authoring_json' => $authoring,
            'lock_version' => 0,
            'owner_user_id' => $actor->id,
        ]);
    }

    public function assertOwner(OmrTemplateAuthoringDraft $draft, User $actor): void
    {
        if ((int) $draft->owner_user_id !== (int) $actor->id) {
            throw new OmrAuthoringDraftException('draft_forbidden', 'Draft is owned by another staff actor.');
        }
    }

    public function transition(OmrTemplateAuthoringDraft $draft, string $nextStage): OmrTemplateAuthoringDraft
    {
        $allowed = [
            OmrTemplateAuthoringDraft::STAGE_DRAFT => [OmrTemplateAuthoringDraft::STAGE_SOURCE_CONFIRMED],
            OmrTemplateAuthoringDraft::STAGE_SOURCE_CONFIRMED => [OmrTemplateAuthoringDraft::STAGE_PAGE_PREPARED, OmrTemplateAuthoringDraft::STAGE_DRAFT],
            OmrTemplateAuthoringDraft::STAGE_PAGE_PREPARED => [OmrTemplateAuthoringDraft::STAGE_MAPPING_COMPLETE, OmrTemplateAuthoringDraft::STAGE_DRAFT],
            OmrTemplateAuthoringDraft::STAGE_MAPPING_COMPLETE => [OmrTemplateAuthoringDraft::STAGE_DIGITAL_VERIFIED, OmrTemplateAuthoringDraft::STAGE_PAGE_PREPARED],
            OmrTemplateAuthoringDraft::STAGE_DIGITAL_VERIFIED => [OmrTemplateAuthoringDraft::STAGE_COMPILED, OmrTemplateAuthoringDraft::STAGE_MAPPING_COMPLETE],
            OmrTemplateAuthoringDraft::STAGE_COMPILED => [],
        ];
        if (! in_array($nextStage, $allowed[$draft->stage] ?? [], true)) {
            throw new OmrAuthoringDraftException('stage_transition_invalid', "Cannot move draft from {$draft->stage} to {$nextStage}.");
        }

        $draft->forceFill(['stage' => $nextStage, 'lock_version' => (int) $draft->lock_version + 1])->save();

        return $draft->fresh();
    }

    /**
     * @param array<string, mixed> $authoring
     */
    public function saveAuthoring(OmrTemplateAuthoringDraft $draft, User $actor, array $authoring, int $expectedLockVersion): OmrTemplateAuthoringDraft
    {
        $this->assertOwner($draft, $actor);

        return DB::transaction(function () use ($draft, $authoring, $expectedLockVersion): OmrTemplateAuthoringDraft {
            $locked = OmrTemplateAuthoringDraft::query()->lockForUpdate()->findOrFail($draft->id);
            if ((int) $locked->lock_version !== $expectedLockVersion) {
                throw new OmrAuthoringDraftException('concurrency_conflict', 'Draft changed since it was loaded.', ['lock_version' => $locked->lock_version]);
            }
            if ($locked->stage === OmrTemplateAuthoringDraft::STAGE_COMPILED) {
                throw new OmrAuthoringDraftException('draft_read_only', 'Compiled drafts are immutable.');
            }
            $locked->forceFill([
                'authoring_json' => $authoring,
                'lock_version' => $expectedLockVersion + 1,
            ])->save();

            return $locked->fresh();
        });
    }

    public function invalidateAfterSourceChange(OmrTemplateAuthoringDraft $draft): OmrTemplateAuthoringDraft
    {
        $authoring = (array) $draft->authoring_json;
        $authoring['pages'] = [];
        $authoring['mapping'] = null;
        $authoring['overlay'] = null;
        $authoring['verification'] = null;
        $authoring['invalidations'] = [
            'stale_mapping' => true,
            'verification_invalidated' => true,
        ];

        $draft->forceFill([
            'stage' => OmrTemplateAuthoringDraft::STAGE_DRAFT,
            'authoring_json' => $authoring,
            'lock_version' => (int) $draft->lock_version + 1,
        ])->save();

        return $draft->fresh();
    }
}
