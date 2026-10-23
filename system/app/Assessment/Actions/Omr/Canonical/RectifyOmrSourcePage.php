<?php

namespace App\Assessment\Actions\Omr\Canonical;

use App\Admission\Support\CommandIdempotency;
use App\Assessment\Omr\Canonical\OmrAuthoringDraftException;
use App\Assessment\Omr\Canonical\OmrAuthoringDraftService;
use App\Assessment\Omr\Canonical\OmrQuadRectifier;
use App\Assessment\Omr\Canonical\OmrTemplateAssetStore;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\Foundation\Operation;
use App\Models\User;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class RectifyOmrSourcePage
{
    public function __construct(
        private readonly CommandIdempotency $idempotency,
        private readonly OmrQuadRectifier $rectifier,
        private readonly OmrTemplateAssetStore $assets,
        private readonly OmrAuthoringDraftService $drafts,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    /** @param list<array{0:int|float,1:int|float}> $points */
    public function handle(
        OmrTemplateAuthoringDraft $draft,
        User $actor,
        UploadedFile $source,
        array $points,
        int $targetWidth,
        int $targetHeight,
        int $expectedLockVersion,
        string $idempotencyKey,
        int $pageNo = 1,
    ): Operation {
        $this->drafts->assertOwner($draft, $actor);
        $sourceBytes = (string) $source->get();
        $sourceSha256 = hash('sha256', $sourceBytes);
        $sourceMime = (string) $source->getMimeType();
        $originalName = $source->getClientOriginalName();
        $payload = [
            'draft_id' => $draft->id,
            'page_no' => $pageNo,
            'source_sha256' => $sourceSha256,
            'points' => $points,
            'target_width' => $targetWidth,
            'target_height' => $targetHeight,
            'expected_lock_version' => $expectedLockVersion,
        ];
        $lockSeconds = (int) ceil((float) config('omr.canonical.limits.conversion_timeout_seconds_per_page', 300)) + 60;

        try {
            return Cache::lock('omr:canonical:source-rectify:draft:'.$draft->id, $lockSeconds)
                ->block(5, fn (): Operation => $this->rectifyUnderDraftLock(
                    $draft,
                    $actor,
                    $sourceBytes,
                    $sourceSha256,
                    $sourceMime,
                    $originalName,
                    $points,
                    $targetWidth,
                    $targetHeight,
                    $expectedLockVersion,
                    $idempotencyKey,
                    $pageNo,
                    $payload,
                ));
        } catch (LockTimeoutException) {
            throw new OmrAuthoringDraftException('source_rectification_busy', 'Another page rectification is running for this draft. Retry shortly.');
        }
    }

    /**
     * @param  list<array{0:int|float,1:int|float}>  $points
     * @param  array<string, mixed>  $payload
     */
    private function rectifyUnderDraftLock(
        OmrTemplateAuthoringDraft $draft,
        User $actor,
        string $sourceBytes,
        string $sourceSha256,
        string $sourceMime,
        string $originalName,
        array $points,
        int $targetWidth,
        int $targetHeight,
        int $expectedLockVersion,
        string $idempotencyKey,
        int $pageNo,
        array $payload,
    ): Operation {
        $existing = $this->findCompletedReplay($actor, (string) $draft->id, $idempotencyKey, $payload);
        if ($existing !== null) {
            return $existing;
        }

        $current = OmrTemplateAuthoringDraft::query()->findOrFail($draft->id);
        $this->drafts->assertOwner($current, $actor);
        $this->assertRectifiablePage($current, $expectedLockVersion, $pageNo);
        $result = $this->rectifier->rectify($sourceBytes, $sourceMime, $points, $targetWidth, $targetHeight);

        return DB::transaction(function () use (
            $draft,
            $actor,
            $sourceBytes,
            $sourceSha256,
            $sourceMime,
            $originalName,
            $points,
            $targetWidth,
            $targetHeight,
            $expectedLockVersion,
            $idempotencyKey,
            $result,
            $pageNo,
            $payload,
        ): Operation {
            $locked = OmrTemplateAuthoringDraft::query()->lockForUpdate()->findOrFail($draft->id);
            $this->drafts->assertOwner($locked, $actor);
            $this->assertRectifiablePage($locked, $expectedLockVersion, $pageNo);
            try {
                $operation = $this->idempotency->claim(
                    $actor,
                    'omr.canonical.source_rectify',
                    (string) $draft->id,
                    $payload,
                    $idempotencyKey,
                );
            } catch (RuntimeException $exception) {
                if ($exception->getMessage() === 'Idempotency key was already used with a different request payload.') {
                    throw new OmrAuthoringDraftException('idempotency_conflict', 'This key was already used for a different rectification request.');
                }

                throw $exception;
            }
            if (! $operation->wasRecentlyCreated) {
                return $operation;
            }

            $original = $this->assets->putDraftAsset($locked, OmrTemplateAsset::KIND_SOURCE, $sourceBytes, $sourceMime, null, [
                'original_name' => $originalName,
                'source_kind' => OmrTemplateAuthoringDraft::SOURCE_IMAGE,
                'authoring_photo' => true,
            ]);
            $asset = $this->assets->putDraftAsset($locked, OmrTemplateAsset::KIND_INTERMEDIATE_PAGE, $result->bytes, 'image/png', $pageNo, [
                'height' => $targetHeight,
                'width_mm' => 210,
                'height_mm' => 297,
                'rectification_quality' => $result->metrics,
                'rectification_hash' => $result->resultHash,
                'source_sha256' => $sourceSha256,
                'server_authoritative' => true,
            ]);
            $priorPages = collect((array) ($locked->authoring_json['pages'] ?? []))
                ->filter(static fn (mixed $page): bool => is_array($page) && ! empty($page['asset_id']) && isset($page['page_no']))
                ->keyBy('page_no');
            $priorOriginalSources = (array) ($locked->authoring_json['source']['metadata']['original_asset_ids'] ?? []);
            $priorOriginalSources[$pageNo] = $original->id;
            $reset = $this->drafts->invalidateAfterSourceChange($locked);
            $authoring = (array) $reset->authoring_json;
            if ((array) ($authoring['studio']['sections'] ?? []) !== []) {
                $authoring['invalidations']['mapping_requires_review'] = true;
                unset($authoring['studio']['reviewed_crop_asset_id']);
            }
            $authoring['source'] = [
                'sha256' => $sourceSha256,
                'converter_version' => 'source-rectification-v1',
                'settings_hash' => hash('sha256', json_encode([$points, $targetWidth, $targetHeight], JSON_THROW_ON_ERROR)),
                'metadata' => [
                    'authoring_photo' => true,
                    'original_asset_id' => $original->id,
                    'original_asset_ids' => $priorOriginalSources,
                    'rectified_sha256' => $asset->sha256,
                    'rectification_quality' => $result->metrics,
                ],
            ];
            $priorPages[$pageNo] = [
                'page_no' => $pageNo,
                'asset_id' => $asset->id,
                'original_asset_id' => $original->id,
                'sha256' => $asset->sha256,
                'height' => $targetHeight,
                'width_mm' => 210,
                'height_mm' => 297,
                'orientation' => 'portrait',
                'confirmed' => false,
                'rejection_reason' => null,
                'rectification_quality' => $result->metrics,
            ];
            $authoring['pages'] = $priorPages->values()->sortBy('page_no')->values()->all();
            $rectifiedCount = collect($authoring['pages'])->filter(static fn (mixed $page): bool => is_array($page) && ! empty($page['asset_id']))->count();
            $isComplete = $rectifiedCount === (int) $locked->requested_page_count;
            $authoring['source_run'] = [
                'operation_id' => $operation->id,
                'source_asset_id' => $original->id,
                'status' => $isComplete ? 'succeeded' : 'processing',
                'progress_done' => $rectifiedCount,
                'progress_total' => (int) $locked->requested_page_count,
                'cancel_requested' => false,
            ];
            $reset->forceFill(['authoring_json' => $authoring])->save();
            $this->recordAuditEvent->handle('omr.canonical.source_rectified', [
                'draft_id' => $draft->id,
                'source_asset_id' => $original->id,
                'source_sha256' => $sourceSha256,
                'asset_id' => $asset->id,
                'rectification_hash' => $result->resultHash,
                'quality' => $result->metrics,
            ], (string) $actor->id);

            return $operation;
        });
    }

    /** @param array<string, mixed> $payload */
    private function findCompletedReplay(User $actor, string $draftId, string $idempotencyKey, array $payload): ?Operation
    {
        $key = sprintf('%s:%s:%s:%s', $actor->id, 'omr.canonical.source_rectify', $draftId, $idempotencyKey);
        $existing = Operation::query()->where('idempotency_key', $key)->first();
        if ($existing === null) {
            return null;
        }
        $existingPayload = $existing->getAttribute('payload');
        $requestHash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
        if (! is_array($existingPayload) || ! hash_equals((string) ($existingPayload['request_hash'] ?? ''), $requestHash)) {
            throw new OmrAuthoringDraftException('idempotency_conflict', 'This key was already used for a different rectification request.');
        }
        if ($existing->status !== Operation::STATUS_SUCCEEDED) {
            throw new OmrAuthoringDraftException('idempotency_conflict', 'This key is already in use by an unfinished rectification.');
        }

        return $existing;
    }

    private function assertRectifiablePage(OmrTemplateAuthoringDraft $draft, int $expectedLockVersion, int $pageNo): void
    {
        if ($draft->source_kind !== OmrTemplateAuthoringDraft::SOURCE_IMAGE
            || (int) $draft->requested_page_count < 1
            || (int) $draft->requested_page_count > 4
            || $draft->stage === OmrTemplateAuthoringDraft::STAGE_COMPILED) {
            throw new OmrAuthoringDraftException('source_rectification_unavailable', 'Only editable image drafts can be rectified.');
        }
        if ($pageNo < 1 || $pageNo > (int) $draft->requested_page_count) {
            throw new OmrAuthoringDraftException('page_number_invalid', "The selected page {$pageNo} is outside the draft's requested page count.");
        }
        if ((int) $draft->lock_version !== $expectedLockVersion) {
            throw new OmrAuthoringDraftException('concurrency_conflict', 'Draft changed since it was loaded.');
        }
    }
}
