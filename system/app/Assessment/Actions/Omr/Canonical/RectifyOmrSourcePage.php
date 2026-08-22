<?php

namespace App\Assessment\Actions\Omr\Canonical;

use App\Admission\Support\CommandIdempotency;
use App\Assessment\Omr\Canonical\OmrAuthoringDraftService;
use App\Assessment\Omr\Canonical\OmrQuadRectifier;
use App\Assessment\Omr\Canonical\OmrTemplateAssetStore;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\Foundation\Operation;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

final class RectifyOmrSourcePage
{
    public function __construct(
        private readonly CommandIdempotency $idempotency,
        private readonly OmrQuadRectifier $rectifier,
        private readonly OmrTemplateAssetStore $assets,
        private readonly OmrAuthoringDraftService $drafts,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {
    }

    /** @param list<array{0:int|float,1:int|float}> $points */
    public function handle(OmrTemplateAuthoringDraft $draft, User $actor, UploadedFile $source, array $points, int $targetWidth, int $targetHeight, string $idempotencyKey): Operation
    {
        $sourceBytes = (string) $source->get();
        $sourceSha256 = hash('sha256', $sourceBytes);
        $operation = $this->idempotency->claim($actor, 'omr.canonical.source_rectify', (string) $draft->id, [
            'draft_id' => $draft->id,
            'source_sha256' => $sourceSha256,
            'points' => $points,
            'target_width' => $targetWidth,
            'target_height' => $targetHeight,
        ], $idempotencyKey);
        if (! $operation->wasRecentlyCreated) {
            return $operation;
        }
        $result = $this->rectifier->rectify($sourceBytes, (string) ($source->getMimeType() ?: 'image/jpeg'), $points, $targetWidth, $targetHeight);
        $asset = $this->assets->putDraftAsset($draft, OmrTemplateAsset::KIND_INTERMEDIATE_PAGE, $result->bytes, 'image/png', 1, [
            'rectification_quality' => $result->metrics,
            'rectification_hash' => $result->resultHash,
            'server_authoritative' => true,
        ]);
        DB::transaction(function () use ($draft, $targetWidth, $targetHeight, $result, $asset, $actor): void {
            $locked = OmrTemplateAuthoringDraft::query()->lockForUpdate()->findOrFail($draft->id);
            $authoring = (array) $locked->authoring_json;
            $authoring['pages'] = [[
                'page_no' => 1,
                'asset_id' => $asset->id,
                'width' => $targetWidth,
                'height' => $targetHeight,
                'confirmed' => false,
                'rejection_reason' => null,
                'rectification_quality' => $result->metrics,
            ]];
            $locked->forceFill(['authoring_json' => $authoring, 'stage' => OmrTemplateAuthoringDraft::STAGE_DRAFT, 'lock_version' => (int) $locked->lock_version + 1])->save();
        });
        $this->recordAuditEvent->handle('omr.canonical.source_rectified', [
            'draft_id' => $draft->id,
            'asset_id' => $asset->id,
            'rectification_hash' => $result->resultHash,
            'quality' => $result->metrics,
        ], (string) $actor->id);

        return $operation;
    }
}
