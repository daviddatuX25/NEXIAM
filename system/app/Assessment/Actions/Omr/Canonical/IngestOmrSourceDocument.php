<?php

namespace App\Assessment\Actions\Omr\Canonical;

use App\Admission\Support\CommandIdempotency;
use App\Assessment\Omr\Canonical\OmrAuthoringDraftService;
use App\Assessment\Omr\Canonical\OmrTemplateAssetStore;
use App\Models\Assessment\OmrTemplateAsset;
use App\Jobs\Assessment\RenderOmrSourcePagesJob;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\Foundation\Operation;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use App\Foundation\Actions\RecordAuditEvent;

final class IngestOmrSourceDocument
{
    public function __construct(
        private readonly CommandIdempotency $idempotency,
        private readonly OmrTemplateAssetStore $assets,
        private readonly OmrAuthoringDraftService $drafts,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {
    }

    /**
     * @param array<string, mixed> $options
     */
    public function handle(
        OmrTemplateAuthoringDraft $draft,
        User $actor,
        UploadedFile $source,
        string $sourceKind,
        string $idempotencyKey,
        array $options = [],
    ): Operation {
        $this->drafts->assertOwner($draft, $actor);
        if ($draft->stage === OmrTemplateAuthoringDraft::STAGE_COMPILED) {
            throw new \RuntimeException('draft_read_only');
        }
        if ($draft->source_kind !== $sourceKind) {
            throw new \RuntimeException('source_kind_mismatch');
        }

        return DB::transaction(function () use ($draft, $actor, $source, $sourceKind, $idempotencyKey, $options): Operation {
            $operation = $this->idempotency->claim(
                $actor,
                'omr.canonical.source_ingest',
                (string) $draft->id,
                [
                    'draft_id' => $draft->id,
                    'source_kind' => $sourceKind,
                    'source_sha256' => hash_file('sha256', $source->getRealPath()),
                    'options' => $options,
                ],
                $idempotencyKey,
            );
            if (! $operation->wasRecentlyCreated) {
                return $operation;
            }

            $asset = $this->assets->putDraftAsset(
                $draft,
                OmrTemplateAsset::KIND_SOURCE,
                (string) $source->get(),
                (string) ($source->getMimeType() ?: $source->getClientMimeType() ?: 'application/octet-stream'),
                null,
                ['original_name' => $source->getClientOriginalName(), 'source_kind' => $sourceKind, 'options' => $options],
            );
            $reset = $this->drafts->invalidateAfterSourceChange($draft->fresh());
            $authoring = (array) $reset->authoring_json;
            $authoring['source_run'] = [
                'operation_id' => $operation->id,
                'source_asset_id' => $asset->id,
                'status' => 'queued',
                'progress_done' => 0,
                'progress_total' => 1,
                'cancel_requested' => false,
            ];
            $reset->forceFill(['authoring_json' => $authoring])->save();
            RenderOmrSourcePagesJob::dispatch($draft->id, $asset->id, $sourceKind, (int) $actor->id, $options)->afterCommit();
            $this->recordAuditEvent->handle('omr.canonical.source_ingest_queued', [
                'draft_id' => $draft->id,
                'asset_id' => $asset->id,
                'source_kind' => $sourceKind,
                'source_sha256' => $asset->sha256,
            ], (string) $actor->id);

            return $operation;
        });
    }
}
