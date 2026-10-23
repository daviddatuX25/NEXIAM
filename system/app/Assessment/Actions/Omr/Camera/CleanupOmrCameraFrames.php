<?php

declare(strict_types=1);

namespace App\Assessment\Actions\Omr\Camera;

use App\Models\Assessment\OmrCameraCommand;
use App\Models\Assessment\OmrCameraFrame;
use App\Models\Assessment\OmrCameraPairing;
use App\Models\User;
use App\Support\SensitiveAssetStore;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

final class CleanupOmrCameraFrames
{
    public function __construct(private readonly SensitiveAssetStore $assets) {}

    /** @return array{examined:int,deleted:int,failed:int,protected:int} */
    public function handle(int $limit = 100): array
    {
        $deadline = now()->subSeconds(max(1, (int) config('omr-camera.frame_staging_retention_seconds', 600)));
        $cursorKey = 'omr-camera:staging-frames-cleanup-cursor';
        $cursor = (int) Cache::get($cursorKey, 0);
        $candidates = $this->stagingFrameCandidates($deadline, $limit, $cursor);
        if ($candidates->isEmpty() && $cursor > 0) {
            Cache::put($cursorKey, 0, now()->addDays(30));
            $candidates = $this->stagingFrameCandidates($deadline, $limit, 0);
        }

        $result = ['examined' => 0, 'deleted' => 0, 'failed' => 0, 'protected' => 0];
        foreach ($candidates as $candidate) {
            Cache::put($cursorKey, (int) $candidate->id, now()->addDays(30));
            $result['examined']++;
            try {
                $asset = $this->reserveForDeletion((int) $candidate->id, $deadline);
                if ($asset === null) {
                    $result['protected']++;

                    continue;
                }
                if ($asset['path'] === null) {
                    continue;
                }
                $disk = Storage::disk('sensitive');
                if ($asset['deleted_at'] !== null && ! $disk->exists($asset['path'])) {
                    continue;
                }
                $this->assets->delete($asset['path']);
                if ($disk->exists($asset['path'])) {
                    throw new \RuntimeException('omr_camera_staging_asset_delete_failed');
                }
                OmrCameraFrame::query()->whereKey($asset['frame_id'])->where('state', OmrCameraFrame::STATE_EXPIRED)
                    ->where('asset_disk', 'sensitive')->where('asset_path', $asset['path'])
                    ->update(['asset_deleted_at' => now()]);
                $result['deleted']++;
            } catch (Throwable) {
                $result['failed']++;
            }
        }

        $rawResult = $this->cleanupRawSourceIntents($limit);
        foreach ($result as $key => $count) {
            $result[$key] += $rawResult[$key];
        }

        return $result;
    }

    private function stagingFrameCandidates(Carbon $deadline, int $limit, int $cursor): Collection
    {
        return OmrCameraFrame::query()->where(function ($query) use ($deadline): void {
            $query->where(function ($query): void {
                $query->whereIn('state', [OmrCameraFrame::STATE_ACQUIRED, OmrCameraFrame::STATE_UPLOADING])
                    ->where('upload_expires_at', '<=', now());
            })->orWhere(function ($query) use ($deadline): void {
                $query->where('state', OmrCameraFrame::STATE_STAGED)->where('staged_at', '<=', $deadline);
            })->orWhere(function ($query): void {
                $query->whereIn('state', [OmrCameraFrame::STATE_CANCELLED, OmrCameraFrame::STATE_EXPIRED])->whereNotNull('asset_path');
            })->orWhere(function ($query): void {
                $query->where('state', OmrCameraFrame::STATE_FINALIZING)
                    ->whereNotNull('finalization_absolute_expires_at')
                    ->where('finalization_absolute_expires_at', '<=', now());
            });
        })->where('id', '>', $cursor)->orderBy('id')->limit($limit)->get(['id']);
    }

    /** @return array{frame_id:int,path:?string,deleted_at:?\DateTimeInterface}|null */
    private function reserveForDeletion(int $frameId, Carbon $deadline): ?array
    {
        return DB::transaction(function () use ($frameId, $deadline): ?array {
            $snapshot = OmrCameraFrame::query()->whereKey($frameId)->first();
            if ($snapshot === null) {
                return null;
            }
            $pairSnapshot = OmrCameraPairing::query()->whereKey($snapshot->pairing_id)->first();
            if ($pairSnapshot === null) {
                return null;
            }
            if ($pairSnapshot->production_batch_id !== null) {
                if (DB::table('omr_capture_batches')->where('id', $pairSnapshot->production_batch_id)->lockForUpdate()->first() === null) {
                    return null;
                }
            } elseif ($pairSnapshot->diagnostic_run_id !== null) {
                if (DB::table('omr_diagnostic_runs')->where('id', $pairSnapshot->diagnostic_run_id)->lockForUpdate()->first() === null) {
                    return null;
                }
            } else {
                return null;
            }
            $captureCommand = OmrCameraCommand::query()->whereKey($snapshot->command_id)->first();
            if ($captureCommand === null) {
                return null;
            }
            $receipt = OmrCameraCommand::query()->where('actor_id', $captureCommand->actor_id)
                ->where('workbench_key', $captureCommand->workbench_key)->where('operation_id', $captureCommand->operation_id)
                ->where('kind', 'CAPTURE_RECEIPT')->lockForUpdate()->first();
            $pairing = OmrCameraPairing::query()->whereKey($snapshot->pairing_id)->lockForUpdate()->first();
            if ($pairing === null || $pairing->production_batch_id !== $pairSnapshot->production_batch_id
                || $pairing->diagnostic_run_id !== $pairSnapshot->diagnostic_run_id) {
                return null;
            }
            $captureCommand = OmrCameraCommand::query()->whereKey($snapshot->command_id)->lockForUpdate()->first();
            if ($captureCommand === null) {
                return null;
            }
            $frame = OmrCameraFrame::query()->whereKey($frameId)->lockForUpdate()->first();
            if ($frame === null || $frame->command_id !== $captureCommand->id || $frame->pairing_id !== $pairing->id
                || $frame->state === OmrCameraFrame::STATE_CONSUMED) {
                return null;
            }
            if ($frame->state === OmrCameraFrame::STATE_FINALIZING) {
                if (! $this->reconcileExpiredFinalization($pairing, $frame, $receipt)) {
                    return null;
                }
                $receipt = $receipt?->fresh();
                $frame = $frame->fresh();
                if ($frame === null || $frame->state !== OmrCameraFrame::STATE_EXPIRED) {
                    return null;
                }
            }
            if ($receipt !== null && ! in_array($receipt->domain_outcome, ['rejected'], true)) {
                return null;
            }
            if ($receipt === null && $captureCommand->device_outcome === 'uncertain') {
                return null;
            }
            $expired = match ($frame->state) {
                OmrCameraFrame::STATE_ACQUIRED, OmrCameraFrame::STATE_UPLOADING => $frame->upload_expires_at !== null && $frame->upload_expires_at->lte(now()),
                OmrCameraFrame::STATE_STAGED => $frame->staged_at !== null && $frame->staged_at->lte($deadline),
                OmrCameraFrame::STATE_CANCELLED, OmrCameraFrame::STATE_EXPIRED => true,
                default => false,
            };
            if (! $expired) {
                return null;
            }
            $path = $frame->asset_path;
            if ($path !== null && ($frame->asset_disk !== 'sensitive' || ! $this->ownsStagingPath($path, $pairing, $frame))) {
                return null;
            }
            $frame->forceFill(['state' => OmrCameraFrame::STATE_EXPIRED, 'client_quad_json' => null])->save();

            return ['frame_id' => $frame->id, 'path' => $path, 'deleted_at' => $frame->asset_deleted_at];
        }, 3);
    }

    private function reconcileExpiredFinalization(
        OmrCameraPairing $pairing,
        OmrCameraFrame $frame,
        ?OmrCameraCommand $receipt,
    ): bool {
        if ($receipt === null
            || $receipt->kind !== 'CAPTURE_RECEIPT'
            || $receipt->finalization_absolute_expires_at === null
            || $frame->finalization_absolute_expires_at === null
            || $receipt->finalization_absolute_expires_at->isFuture()
            || $frame->finalization_absolute_expires_at->isFuture()) {
            return false;
        }

        $domainReceipt = is_array($receipt->domain_receipt_json) ? $receipt->domain_receipt_json : [];
        $frameReceipt = is_array($frame->domain_receipt_json) ? $frame->domain_receipt_json : [];
        if ($receipt->domain_outcome === 'accepted' || ($frameReceipt['status'] ?? null) === 'accepted') {
            if ($domainReceipt === []) {
                $domainReceipt = $frameReceipt;
            }
            if ($receipt->domain_outcome !== 'accepted') {
                $fencingToken = (string) Str::uuid();
                $receipt->forceFill([
                    'domain_outcome' => 'accepted',
                    'domain_receipt_json' => $domainReceipt,
                    'completed_at' => $receipt->completed_at ?? now(),
                    'finalization_lease_token' => $fencingToken,
                    'finalization_lease_expires_at' => now()->subSecond(),
                    'finalization_lease_generation' => (int) $receipt->finalization_lease_generation + 1,
                ])->saveOrFail();
                $frame->finalization_lease_token = $fencingToken;
                $frame->finalization_lease_expires_at = now()->subSecond();
            }
            $frame->forceFill([
                'state' => OmrCameraFrame::STATE_CONSUMED,
                'domain_receipt_json' => $domainReceipt,
                'consumed_at' => $frame->consumed_at ?? now(),
            ])->saveOrFail();

            return false;
        }

        if (! in_array($receipt->domain_outcome, ['reserved', 'uncertain', 'rejected'], true)) {
            return false;
        }

        $evidence = $this->persistedFinalizationEvidence($pairing, $frame, $receipt);
        if ($evidence['state'] === 'accepted') {
            $fencingToken = (string) Str::uuid();
            $receipt->forceFill([
                'domain_outcome' => 'accepted',
                'domain_receipt_json' => $evidence['receipt'],
                'completed_at' => $receipt->completed_at ?? now(),
                'finalization_lease_token' => $fencingToken,
                'finalization_lease_expires_at' => now()->subSecond(),
                'finalization_lease_generation' => (int) $receipt->finalization_lease_generation + 1,
            ])->saveOrFail();
            $frame->forceFill([
                'state' => OmrCameraFrame::STATE_CONSUMED,
                'domain_receipt_json' => $evidence['receipt'],
                'consumed_at' => $frame->consumed_at ?? now(),
                'finalization_lease_token' => $fencingToken,
                'finalization_lease_expires_at' => now()->subSecond(),
            ])->saveOrFail();

            return false;
        }

        if ($evidence['state'] === 'none') {
            foreach ((array) ($receipt->payload_json['source_assets'] ?? []) as $asset) {
                if (is_array($asset) && is_string($asset['path'] ?? null)
                    && $this->hasDomainReference($asset['path'])) {
                    $evidence = ['state' => 'unknown'];
                    break;
                }
            }
        }

        if ($evidence['state'] !== 'none') {
            if ($receipt->domain_outcome === 'uncertain'
                && $frame->finalization_lease_token === null
                && $receipt->finalization_lease_expires_at?->isPast()
                && $frame->finalization_lease_expires_at?->isPast()) {
                return false;
            }
            $fencingToken = (string) Str::uuid();
            $receipt->forceFill([
                'domain_outcome' => 'uncertain',
                'finalization_lease_token' => $fencingToken,
                'finalization_lease_expires_at' => now()->subSecond(),
                'finalization_lease_generation' => (int) $receipt->finalization_lease_generation + 1,
            ])->saveOrFail();
            $frame->forceFill([
                'finalization_lease_token' => null,
                'finalization_lease_expires_at' => now()->subSecond(),
            ])->saveOrFail();

            return false;
        }

        $receipt->forceFill([
            'domain_outcome' => 'rejected',
            'device_outcome_json' => ['failure_reason' => 'finalization_absolute_expired_uncommitted'],
            'completed_at' => now(),
            'finalization_lease_token' => null,
            'finalization_lease_expires_at' => now()->subSecond(),
            'finalization_lease_generation' => (int) $receipt->finalization_lease_generation + 1,
        ])->saveOrFail();
        $frame->forceFill([
            'state' => OmrCameraFrame::STATE_EXPIRED,
            'client_quad_json' => null,
            'finalization_fingerprint' => null,
            'finalization_lease_token' => null,
            'finalization_lease_expires_at' => now()->subSecond(),
        ])->saveOrFail();

        return true;
    }

    /**
     * @return array{state:'accepted'|'partial'|'unknown'|'none',receipt?:array<string, mixed>}
     */
    private function persistedFinalizationEvidence(
        OmrCameraPairing $pairing,
        ?OmrCameraFrame $frame,
        OmrCameraCommand $receipt,
    ): array {
        $payload = (array) $receipt->payload_json;
        $workbench = (array) ($payload['workbench'] ?? []);
        $target = (array) $receipt->target_json;
        $sourceAssets = array_values(array_filter(
            (array) ($payload['source_assets'] ?? []),
            static fn (mixed $asset): bool => is_array($asset)
                && is_string($asset['path'] ?? null)
                && is_string($asset['hmac'] ?? null)
                && preg_match('/^[a-f0-9]{64}$/i', $asset['hmac']) === 1,
        ));
        $batchId = $workbench['production_batch_id'] ?? null;
        $runId = $workbench['diagnostic_run_id'] ?? null;
        $clientCaptureId = $target['clientCaptureId'] ?? $target['client_capture_id'] ?? null;
        $pageNo = $target['pageNo'] ?? $target['page_no'] ?? null;
        if (count($sourceAssets) !== 1 || ! is_string($clientCaptureId) || ! is_numeric($pageNo)
            || (int) $pageNo < 1) {
            return ['state' => 'unknown'];
        }
        $source = $sourceAssets[0];

        $pendingCaptureId = $target['pending_capture_id'] ?? null;
        if ($pairing->production_batch_id !== null && is_numeric($batchId) && $runId === null
            && (int) $batchId === (int) $pairing->production_batch_id
            && is_string($pendingCaptureId) && $pendingCaptureId !== ''
            && Schema::hasTable('omr_pending_capture_sets')
            && Schema::hasTable('omr_pending_capture_page_evidence')) {
            $set = DB::table('omr_pending_capture_sets')
                ->where('production_batch_id', (int) $batchId)
                ->where('public_id', $pendingCaptureId)
                ->where('created_by_user_id', (int) $receipt->actor_id)
                ->first(['id', 'public_id', 'revision']);
            if ($set === null) {
                return ['state' => 'unknown'];
            }
            $evidence = DB::table('omr_pending_capture_page_evidence')
                ->where('set_id', (int) $set->id)
                ->where('client_capture_id', $clientCaptureId)
                ->where('page_no', (int) $pageNo)
                ->first(['id', 'asset_path', 'input_hash']);
            if ($evidence === null) {
                return ['state' => 'none'];
            }
            if ((string) $evidence->asset_path !== (string) $source['path']) {
                return ['state' => 'unknown'];
            }

            return ['state' => 'accepted', 'receipt' => [
                'operation_id' => (string) $receipt->operation_id,
                'target_key' => (string) ($target['targetKey'] ?? $target['target_key'] ?? ''),
                'status' => 'accepted',
                'capture_id' => (int) $set->id,
                'page_id' => (int) $evidence->id,
                'pending_capture_id' => (string) $set->public_id,
                'pending_capture_revision' => (int) $set->revision,
                'page_no' => (int) $pageNo,
                'client_capture_id' => (string) $clientCaptureId,
                'revision' => (int) $set->revision,
            ]];
        }

        if ($pairing->production_batch_id !== null && is_numeric($batchId) && $runId === null
            && (int) $batchId === (int) $pairing->production_batch_id
            && Schema::hasTable('omr_capture_sheets') && Schema::hasTable('omr_capture_sheet_pages')) {
            $page = DB::table('omr_capture_sheets as sheets')
                ->join('omr_capture_sheet_pages as pages', 'pages.sheet_id', '=', 'sheets.id')
                ->where('sheets.batch_id', (int) $batchId)
                ->where('sheets.client_capture_id', $clientCaptureId)
                ->where('pages.page_no', (int) $pageNo)
                ->first([
                    'sheets.id as sheet_id',
                    'sheets.version as sheet_version',
                    'sheets.client_capture_id',
                    'pages.id as page_id',
                    'pages.asset_path',
                    'pages.asset_hmac',
                ]);
            if ($page === null) {
                return ['state' => 'none'];
            }
            if (is_numeric($target['sheetId'] ?? $target['sheet_id'] ?? null)
                && (int) ($target['sheetId'] ?? $target['sheet_id']) !== (int) $page->sheet_id) {
                return ['state' => 'unknown'];
            }
            if ((string) $page->asset_path !== (string) $source['path']
                || ! is_string($page->asset_hmac)
                || ! hash_equals((string) $page->asset_hmac, (string) $source['hmac'])) {
                return ['state' => 'unknown'];
            }

            return ['state' => 'accepted', 'receipt' => [
                'operation_id' => (string) $receipt->operation_id,
                'target_key' => (string) ($target['targetKey'] ?? $target['target_key'] ?? ''),
                'status' => 'accepted',
                'capture_id' => (int) $page->sheet_id,
                'sheet_id' => (int) $page->sheet_id,
                'page_id' => (int) $page->page_id,
                'page_no' => (int) $pageNo,
                'client_capture_id' => (string) $page->client_capture_id,
                'revision' => (int) $page->sheet_version,
            ]];
        }

        if ($pairing->diagnostic_run_id === null || ! is_numeric($runId)
            || (int) $runId !== (int) $pairing->diagnostic_run_id
            || ! Schema::hasTable('omr_diagnostic_captures')) {
            return ['state' => 'unknown'];
        }

        $pendingCaptureId = $target['pending_capture_id'] ?? null;
        if (is_string($pendingCaptureId) && $pendingCaptureId !== '') {
            if (! Schema::hasTable('omr_pending_capture_sets')
                || ! Schema::hasTable('omr_pending_capture_page_evidence')) {
                return ['state' => 'unknown'];
            }
            $set = DB::table('omr_pending_capture_sets')
                ->where('diagnostic_run_id', (int) $runId)
                ->where('public_id', $pendingCaptureId)
                ->whereNull('production_batch_id')
                ->first(['id', 'public_id', 'revision', 'mock_instance_key']);
            if ($set === null) {
                return ['state' => 'unknown'];
            }
            if (is_string($target['instanceId'] ?? $target['instance_id'] ?? null)
                && (string) $set->mock_instance_key !== (string) ($target['instanceId'] ?? $target['instance_id'])) {
                return ['state' => 'unknown'];
            }
            $evidence = DB::table('omr_pending_capture_page_evidence')
                ->where('set_id', (int) $set->id)
                ->where('client_capture_id', $clientCaptureId)
                ->where('page_no', (int) $pageNo)
                ->first(['id', 'input_hash', 'asset_path', 'supersedes_id', 'replacement_reason']);
            if ($evidence === null) {
                return ['state' => 'none'];
            }
            $expectedSupersedesId = is_numeric($target['redo_source_page_id'] ?? null)
                ? (int) $target['redo_source_page_id']
                : null;
            if ((int) ($evidence->supersedes_id ?? 0) !== (int) ($expectedSupersedesId ?? 0)
                || ($expectedSupersedesId !== null
                    && ($target['redo_reason'] ?? null) !== $evidence->replacement_reason)) {
                return ['state' => 'unknown'];
            }
            if ((string) $evidence->asset_path !== (string) $source['path']
                || ($frame !== null && ! hash_equals((string) $evidence->input_hash, (string) $frame->input_sha256))) {
                return ['state' => 'unknown'];
            }
            $capture = DB::table('omr_diagnostic_captures')
                ->where('diagnostic_run_id', (int) $runId)
                ->where('client_capture_id', $clientCaptureId)
                ->first(['id', 'input_asset_hash', 'evidence_json']);
            if ($capture === null) {
                return ['state' => 'partial'];
            }
            if (! hash_equals((string) $capture->input_asset_hash, (string) $evidence->input_hash)) {
                return ['state' => 'unknown'];
            }
            $captureEvidence = is_array($capture->evidence_json)
                ? $capture->evidence_json
                : (array) json_decode((string) $capture->evidence_json, true);
            $link = is_array($captureEvidence['pending_page_link'] ?? null)
                ? $captureEvidence['pending_page_link']
                : [];
            if ((int) ($link['capture_set_id'] ?? 0) !== (int) $set->id
                || (int) ($link['pending_page_evidence_id'] ?? 0) !== (int) $evidence->id
                || ! hash_equals((string) ($link['input_hash'] ?? ''), (string) $evidence->input_hash)
                || (int) ($link['supersedes_pending_page_evidence_id'] ?? 0) !== (int) ($evidence->supersedes_id ?? 0)) {
                return ['state' => 'partial'];
            }

            $run = DB::table('omr_diagnostic_runs')->where('id', (int) $runId)->first(['acceptance_plan_json']);
            if ($run === null) {
                return ['state' => 'unknown'];
            }
            $plan = is_array($run->acceptance_plan_json)
                ? $run->acceptance_plan_json
                : (array) json_decode((string) $run->acceptance_plan_json, true);
            $selectionRequired = $plan !== []
                || is_string($target['instanceId'] ?? $target['instance_id'] ?? null);
            if ($selectionRequired) {
                if (! Schema::hasTable('test_kit_diagnostic_page_attempts')
                    || ! Schema::hasTable('test_kit_diagnostic_page_selections')) {
                    return ['state' => 'unknown'];
                }
                $attempt = DB::table('test_kit_diagnostic_page_attempts')
                    ->where('diagnostic_run_id', (int) $runId)
                    ->where('pending_page_evidence_id', (int) $evidence->id)
                    ->where('diagnostic_capture_id', (int) $capture->id)
                    ->first(['id', 'case_id']);
                if ($attempt === null) {
                    return ['state' => 'partial'];
                }
                $selected = DB::table('test_kit_diagnostic_page_selections')
                    ->where('diagnostic_run_id', (int) $runId)
                    ->where('case_id', (string) $attempt->case_id)
                    ->where('page_no', (int) $pageNo)
                    ->where('selected_attempt_id', (int) $attempt->id)
                    ->exists();
                if (! $selected) {
                    return ['state' => 'partial'];
                }
            }

            return ['state' => 'accepted', 'receipt' => [
                'operation_id' => (string) $receipt->operation_id,
                'target_key' => (string) ($target['targetKey'] ?? $target['target_key'] ?? ''),
                'status' => 'accepted',
                'capture_id' => (int) $set->id,
                'page_id' => (int) $evidence->id,
                'pending_capture_id' => (string) $set->public_id,
                'pending_capture_revision' => (int) $set->revision,
                'diagnostic_capture_id' => (int) $capture->id,
                'page_no' => (int) $pageNo,
                'client_capture_id' => (string) $clientCaptureId,
                'revision' => (int) $set->revision,
            ]];
        }

        if (! Schema::hasTable('omr_template_assets')) {
            return ['state' => 'unknown'];
        }
        $capture = DB::table('omr_diagnostic_captures')
            ->where('diagnostic_run_id', (int) $runId)
            ->where('client_capture_id', $clientCaptureId)
            ->first(['id', 'input_asset_id', 'input_asset_hash', 'status']);
        if ($capture === null) {
            return ['state' => 'none'];
        }
        $asset = DB::table('omr_template_assets')
            ->where('id', (int) $capture->input_asset_id)
            ->where('path', (string) $source['path'])
            ->where('kind', 'evidence')
            ->where('metadata->role', 'capture_input')
            ->first(['sha256', 'metadata']);
        $assetMetadata = is_array($asset?->metadata)
            ? $asset->metadata
            : (array) json_decode((string) ($asset->metadata ?? ''), true);
        if ($asset === null
            || ! is_string($assetMetadata['hmac'] ?? null)
            || ! hash_equals((string) $source['hmac'], (string) $assetMetadata['hmac'])
            || ($frame !== null && ! hash_equals((string) $capture->input_asset_hash, (string) $frame->input_sha256))
            || ! hash_equals((string) $asset->sha256, (string) $capture->input_asset_hash)) {
            return ['state' => 'unknown'];
        }
        if (in_array((string) $capture->status, ['queued', 'processing'], true)) {
            return ['state' => 'partial'];
        }

        return ['state' => 'accepted', 'receipt' => [
            'operation_id' => (string) $receipt->operation_id,
            'target_key' => (string) ($target['targetKey'] ?? $target['target_key'] ?? ''),
            'status' => 'accepted',
            'capture_id' => (int) $capture->id,
            'diagnostic_capture_id' => (int) $capture->id,
            'page_id' => (int) $capture->id,
            'page_no' => (int) $pageNo,
            'client_capture_id' => (string) $clientCaptureId,
        ]];
    }

    /** @return array{examined:int,deleted:int,failed:int,protected:int} */
    private function cleanupRawSourceIntents(int $limit): array
    {
        $result = ['examined' => 0, 'deleted' => 0, 'failed' => 0, 'protected' => 0];
        if (! Schema::hasTable('omr_camera_commands')) {
            return $result;
        }

        $cursorKey = 'omr-camera:source-assets-cleanup-cursor';
        $cursor = (int) Cache::get($cursorKey, 0);
        $candidates = OmrCameraCommand::query()
            ->where('kind', 'CAPTURE_RECEIPT')
            ->whereIn('domain_outcome', ['accepted', 'reserved', 'uncertain', 'rejected', 'released', 'expired'])
            ->whereNotNull('payload_json->source_assets')
            ->where('id', '>', $cursor)
            ->orderBy('id')->limit($limit)->get(['id']);
        if ($candidates->isEmpty() && $cursor > 0) {
            Cache::put($cursorKey, 0, now()->addDays(30));
            $candidates = OmrCameraCommand::query()
                ->where('kind', 'CAPTURE_RECEIPT')
                ->whereIn('domain_outcome', ['accepted', 'reserved', 'uncertain', 'rejected', 'released', 'expired'])
                ->whereNotNull('payload_json->source_assets')
                ->orderBy('id')->limit($limit)->get(['id']);
        }

        foreach ($candidates as $candidate) {
            Cache::put($cursorKey, (int) $candidate->id, now()->addDays(30));
            $intents = $this->reserveRawSourceIntents((int) $candidate->id);
            if ($intents === []) {
                $result['protected']++;

                continue;
            }
            foreach ($intents as $intent) {
                if ($result['examined'] >= $limit) {
                    break 2;
                }
                $result['examined']++;
                $disk = Storage::disk('sensitive');
                if (($intent['deleted_at'] ?? null) !== null && ! $disk->exists($intent['path'])) {
                    continue;
                }
                try {
                    if ($disk->exists($intent['path'])) {
                        $this->assets->read($intent['path'], $intent['hmac']);
                    }
                    $this->assets->delete($intent['path']);
                    if ($disk->exists($intent['path'])) {
                        throw new \RuntimeException('omr_camera_source_asset_delete_failed');
                    }
                    if (! $this->markRawSourceIntentDeleted($intent)) {
                        $result['protected']++;

                        continue;
                    }
                    $result['deleted']++;
                } catch (Throwable) {
                    $result['failed']++;
                }
            }
        }

        return $result;
    }

    /** @return list<array{operation_id:int,path:string,hmac:string,deleted_at:mixed,workbench:array<string,mixed>}> */
    private function reserveRawSourceIntents(int $operationId): array
    {
        $snapshot = OmrCameraCommand::query()->whereKey($operationId)->first();
        if ($snapshot === null) {
            return [];
        }
        $payload = (array) $snapshot->payload_json;
        $workbench = (array) ($payload['workbench'] ?? []);

        return DB::transaction(function () use ($snapshot, $workbench): array {
            if (! $this->lockRawWorkbench($workbench)) {
                return [];
            }
            $operation = OmrCameraCommand::query()->whereKey($snapshot->id)->lockForUpdate()->first();
            if ($operation === null || $operation->kind !== 'CAPTURE_RECEIPT'
                || (int) $operation->actor_id !== (int) $snapshot->actor_id
                || $operation->workbench_key !== $snapshot->workbench_key
                || ! hash_equals($operation->workbench_key, hash('sha256', (string) ($workbench['workbench_id'] ?? '')))
                || ! in_array($operation->domain_outcome, ['reserved', 'uncertain', 'rejected', 'released', 'expired'], true)
                || $operation->finalization_lease_expires_at?->isFuture()
                || ! User::query()->whereKey($operation->actor_id)->exists()) {
                return [];
            }
            if (in_array($operation->domain_outcome, ['reserved', 'uncertain'], true)) {
                if ($operation->finalization_absolute_expires_at === null
                    || $operation->finalization_absolute_expires_at->isFuture()) {
                    return [];
                }
                $pairing = new OmrCameraPairing;
                $pairing->production_batch_id = $workbench['production_batch_id'] ?? null;
                $pairing->diagnostic_run_id = $workbench['diagnostic_run_id'] ?? null;
                $evidence = $this->persistedFinalizationEvidence($pairing, null, $operation);
                if ($evidence['state'] === 'accepted') {
                    $operation->forceFill([
                        'domain_outcome' => 'accepted',
                        'domain_receipt_json' => $evidence['receipt'],
                        'completed_at' => $operation->completed_at ?? now(),
                        'finalization_lease_token' => (string) Str::uuid(),
                        'finalization_lease_expires_at' => now()->subSecond(),
                        'finalization_lease_generation' => (int) $operation->finalization_lease_generation + 1,
                    ])->saveOrFail();

                    return [];
                }
                $hasReferencedOriginal = false;
                foreach ((array) (($operation->payload_json ?? [])['source_assets'] ?? []) as $asset) {
                    if (is_array($asset) && is_string($asset['path'] ?? null)
                        && $this->hasDomainReference($asset['path'])) {
                        $hasReferencedOriginal = true;
                        break;
                    }
                }
                if ($evidence['state'] === 'unknown' || $hasReferencedOriginal) {
                    $operation->forceFill([
                        'domain_outcome' => 'uncertain',
                        'finalization_lease_token' => (string) Str::uuid(),
                        'finalization_lease_expires_at' => now()->subSecond(),
                        'finalization_lease_generation' => (int) $operation->finalization_lease_generation + 1,
                    ])->saveOrFail();

                    return [];
                }
                $operation->forceFill([
                    'domain_outcome' => 'rejected',
                    'device_outcome_json' => ['failure_reason' => 'finalization_absolute_expired_uncommitted'],
                    'completed_at' => now(),
                    'finalization_lease_token' => null,
                    'finalization_lease_expires_at' => now()->subSecond(),
                    'finalization_lease_generation' => (int) $operation->finalization_lease_generation + 1,
                ])->saveOrFail();
            }
            if (! in_array($operation->domain_outcome, ['rejected', 'released', 'expired'], true)
                || $operation->completed_at === null
                || ! User::query()->whereKey($operation->actor_id)->exists()) {
                return [];
            }
            $intents = [];
            foreach ((array) (($operation->payload_json ?? [])['source_assets'] ?? []) as $asset) {
                if (! is_array($asset) || ! is_string($asset['path'] ?? null)
                    || ! is_string($asset['hmac'] ?? null) || ! $this->isAllowedRawSourcePath($asset['path'])
                    || preg_match('/^[a-f0-9]{64}$/D', $asset['hmac']) !== 1
                    || $this->hasDomainReference($asset['path'])) {
                    continue;
                }
                $intents[] = [
                    'operation_id' => (int) $operation->id,
                    'path' => $asset['path'],
                    'hmac' => $asset['hmac'],
                    'deleted_at' => $asset['deleted_at'] ?? null,
                    'workbench' => $workbench,
                ];
            }

            return $intents;
        }, 3);
    }

    /** @param array<string, mixed> $workbench */
    private function lockRawWorkbench(array $workbench): bool
    {
        $batchId = $workbench['production_batch_id'] ?? null;
        $runId = $workbench['diagnostic_run_id'] ?? null;
        if (is_numeric($batchId) && (int) $batchId > 0 && $runId === null) {
            return DB::table('omr_capture_batches')->where('id', (int) $batchId)->lockForUpdate()->first() !== null;
        }
        if (is_numeric($runId) && (int) $runId > 0 && $batchId === null) {
            return DB::table('omr_diagnostic_runs')->where('id', (int) $runId)->lockForUpdate()->first() !== null;
        }

        return false;
    }

    private function markRawSourceIntentDeleted(array $intent): bool
    {
        return DB::transaction(function () use ($intent): bool {
            if (! $this->lockRawWorkbench($intent['workbench'])) {
                return false;
            }
            $operation = OmrCameraCommand::query()->whereKey($intent['operation_id'])->lockForUpdate()->first();
            if ($operation === null || $operation->kind !== 'CAPTURE_RECEIPT'
                || ! in_array($operation->domain_outcome, ['rejected', 'released', 'expired'], true)
                || $operation->completed_at === null || $operation->finalization_lease_expires_at?->isFuture()
                || ! hash_equals($operation->workbench_key, hash('sha256', (string) ($intent['workbench']['workbench_id'] ?? '')))
                || ! User::query()->whereKey($operation->actor_id)->exists()
                || ! $this->isAllowedRawSourcePath($intent['path'])
                || $this->hasDomainReference($intent['path'])) {
                return false;
            }
            $payload = (array) $operation->payload_json;
            $assets = (array) ($payload['source_assets'] ?? []);
            $updated = false;
            foreach ($assets as &$asset) {
                if (is_array($asset) && ($asset['path'] ?? null) === $intent['path']
                    && is_string($asset['hmac'] ?? null) && hash_equals($asset['hmac'], $intent['hmac'])) {
                    $asset['deleted_at'] = now()->toISOString();
                    $updated = true;
                    break;
                }
            }
            unset($asset);
            if (! $updated) {
                return false;
            }
            $payload['source_assets'] = $assets;
            $operation->forceFill(['payload_json' => $payload])->saveOrFail();

            return true;
        }, 3);
    }

    private function hasDomainReference(string $path): bool
    {
        $references = [
            'omr_capture_sheets' => ['asset_path', 'aligned_asset_path'],
            'omr_capture_sheet_pages' => ['asset_path', 'aligned_asset_path'],
            'omr_capture_sheet_page_revisions' => ['asset_path', 'aligned_asset_path'],
            'omr_pending_captures' => ['asset_path', 'aligned_asset_path'],
            'omr_pending_capture_page_evidence' => ['asset_path'],
            'omr_template_assets' => ['path'],
            'omr_camera_frames' => ['asset_path'],
        ];
        foreach ($references as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach ($columns as $column) {
                if (Schema::hasColumn($table, $column) && DB::table($table)->where($column, $path)->exists()) {
                    return true;
                }
            }
        }

        return false;
    }

    private function isAllowedRawSourcePath(string $path): bool
    {
        return preg_match('#^omr/assets/[0-9a-f-]{36}\\.bin$#i', $path) === 1
            || preg_match('#^omr/camera-originals/(?:[0-9a-f-]{36}|receipt-[1-9][0-9]*)\\.bin$#i', $path) === 1
            || preg_match('#^omr/pending-pages/(?:[0-9a-f-]{36}|camera-(?:[0-9a-f-]{36}|[1-9][0-9]*))\\.bin$#i', $path) === 1
            || preg_match('#^omr/diagnostic/[0-9a-f-]{36}/[a-f0-9]{64}_(?:input|aligned|overlay)\\.bin$#i', $path) === 1
            || preg_match('#^omr/diagnostic/[0-9a-f-]{36}/camera-(?:[0-9a-f-]{36}|[1-9][0-9]*)_input\\.bin$#i', $path) === 1;
    }

    private function ownsStagingPath(string $path, OmrCameraPairing $pairing, OmrCameraFrame $frame): bool
    {
        return $path === 'omr/camera/'.$pairing->public_id.'/'.$frame->public_id
            && Str::isUuid($pairing->public_id) && Str::isUuid($frame->public_id);
    }
}
