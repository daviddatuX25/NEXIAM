<?php

declare(strict_types=1);

use App\Assessment\Actions\Omr\Camera\CleanupOmrCameraFrames;
use App\Models\Assessment\OmrCameraCommand;
use App\Models\Assessment\OmrCameraFrame;
use App\Models\Assessment\OmrCameraPairing;
use App\Models\Assessment\OmrCaptureBatch;
use App\Models\Assessment\OmrCaptureSheet;
use App\Models\Assessment\OmrCaptureSheetPage;
use App\Models\Foundation\Institution;
use App\Support\SensitiveAssetStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(RefreshDatabase::class);

/** @return array{pair: OmrCameraPairing, command: OmrCameraCommand, frame: OmrCameraFrame, sourcePath:?string} */
function cameraFrameCleanupFixture(TestCase $test, string $state = OmrCameraFrame::STATE_STAGED, ?string $receiptOutcome = null, bool $withRawAsset = false, ?array $gradingFixture = null): array
{
    $fixture = $gradingFixture ?? buildOmrGradingFixture($test);
    $actor = $fixture['guidance'];
    $pairId = (string) Str::uuid();
    $operationId = (string) Str::uuid();
    $batch = OmrCaptureBatch::query()->create([
        'grading_session_id' => $fixture['grading']->id,
        'template_version_id' => $fixture['templateBookletPair']->template_version_id,
        'booklet_version_id' => $fixture['templateBookletPair']->booklet_version_id,
        'status' => OmrCaptureBatch::STATUS_OPEN,
        'version' => 1, 'opened_by_user_id' => $actor->id, 'opened_at' => now(),
    ]);
    $pair = OmrCameraPairing::query()->create([
        'public_id' => $pairId, 'operator_id' => $actor->id,
        'institution_id' => Institution::current()->id, 'academic_cycle_id' => $fixture['session']->academic_cycle_id,
        'production_batch_id' => $batch->id, 'workbench_id' => 'cleanup-test',
        'workbench_key' => hash('sha256', 'cleanup-test'), 'absolute_expires_at' => now()->addHour(), 'config_json' => [],
    ]);
    $command = OmrCameraCommand::query()->create([
        'pairing_id' => $pair->id, 'operation_id' => $operationId, 'command_id' => (string) Str::uuid(),
        'issuer_role' => 'node', 'sequence' => 1, 'kind' => 'CAPTURE', 'workbench_key' => $pair->workbench_key,
        'actor_id' => $actor->id, 'payload_json' => [], 'payload_fingerprint' => hash('sha256', 'command'),
        'issued_at' => now(), 'device_outcome' => 'acquired', 'execution_expires_at' => now()->subMinute(),
    ]);
    $sourcePath = null;
    $sourceAssets = [];
    $store = app(SensitiveAssetStore::class);
    if ($withRawAsset) {
        $sourcePath = 'omr/assets/'.Str::uuid().'.bin';
        $sourceBytes = 'private-raw-source';
        $store->store($sourceBytes, $sourcePath);
        $sourceAssets[] = ['path' => $sourcePath, 'hmac' => $store->hmac($sourceBytes), 'created_at' => now()->toISOString()];
    }
    if ($receiptOutcome !== null) {
        OmrCameraCommand::query()->create([
            'pairing_id' => $pair->id, 'operation_id' => $operationId, 'command_id' => (string) Str::uuid(),
            'issuer_role' => 'controller', 'kind' => 'CAPTURE_RECEIPT', 'workbench_key' => $pair->workbench_key,
            'actor_id' => $actor->id, 'receipt_key' => hash('sha256', $operationId),
            'payload_json' => [
                'workbench' => ['workbench_id' => 'cleanup-test', 'production_batch_id' => $batch->id],
                'source_assets' => $sourceAssets,
            ],
            'payload_fingerprint' => hash('sha256', 'receipt'), 'issued_at' => now(),
            'domain_outcome' => $receiptOutcome, 'completed_at' => now(),
        ]);
    }
    $frameId = (string) Str::uuid();
    $framePath = 'omr/camera/'.$pairId.'/'.$frameId;
    $bytes = "\x89PNG\r\n\x1a\nprivate-original";
    $store->store($bytes, $framePath);
    $frame = OmrCameraFrame::query()->create([
        'public_id' => $frameId, 'pairing_id' => $pair->id, 'command_id' => $command->id,
        'node_generation' => 1, 'target_json' => [], 'target_key' => 'target', 'config_revision' => 1,
        'source_generation' => 1, 'input_sha256' => hash('sha256', $bytes), 'byte_count' => strlen($bytes),
        'mime_type' => 'image/png', 'width' => 1, 'height' => 1, 'orientation' => 1,
        'asset_disk' => 'sensitive', 'asset_path' => $framePath, 'asset_hmac' => $store->hmac($bytes),
        'state' => $state, 'acquired_at' => now()->subMinutes(20), 'upload_expires_at' => now()->subMinutes(10),
        'staged_at' => now()->subMinutes(20),
    ]);

    return compact('pair', 'command', 'frame', 'sourcePath');
}

test('expired staged originals are encrypted at rest, deleted from their exact private namespace, and cleanup replay is idempotent', function (): void {
    Storage::fake('sensitive');
    config(['omr-camera.frame_staging_retention_seconds' => 600]);
    $record = cameraFrameCleanupFixture($this);
    $path = $record['frame']->asset_path;
    $encrypted = Storage::disk('sensitive')->get($path);
    expect($encrypted)->not->toContain('private-original')
        ->and(app(SensitiveAssetStore::class)->read($path, $record['frame']->asset_hmac))->toContain('private-original');

    $first = app(CleanupOmrCameraFrames::class)->handle(10);
    expect($first['deleted'])->toBe(1)
        ->and(Storage::disk('sensitive')->exists($path))->toBeFalse()
        ->and($record['frame']->fresh()->state)->toBe(OmrCameraFrame::STATE_EXPIRED)
        ->and($record['frame']->fresh()->asset_path)->toBe($path)
        ->and($record['frame']->fresh()->asset_deleted_at)->not->toBeNull();

    expect(app(CleanupOmrCameraFrames::class)->handle(10)['deleted'])->toBe(0);
});
test('expired uploading intent remains tracked and is safely pruned after an interrupted encrypted write', function (): void {
    Storage::fake('sensitive');
    config(['omr-camera.frame_staging_retention_seconds' => 600]);
    $record = cameraFrameCleanupFixture($this, OmrCameraFrame::STATE_UPLOADING);
    $path = $record['frame']->asset_path;

    $result = app(CleanupOmrCameraFrames::class)->handle(10);

    expect($result['deleted'])->toBe(1)
        ->and($record['frame']->fresh()->state)->toBe(OmrCameraFrame::STATE_EXPIRED)
        ->and($record['frame']->fresh()->asset_path)->toBe($path)
        ->and($record['frame']->fresh()->asset_deleted_at)->not->toBeNull()
        ->and(Storage::disk('sensitive')->exists($path))->toBeFalse();
});
test('staging cleanup pagination progresses beyond permanently held expired records', function (): void {
    Storage::fake('sensitive');
    config(['omr-camera.frame_staging_retention_seconds' => 600]);
    $gradingFixture = buildOmrGradingFixture($this);
    $held = cameraFrameCleanupFixture($this, receiptOutcome: 'uncertain', gradingFixture: $gradingFixture);
    $eligible = cameraFrameCleanupFixture($this, gradingFixture: $gradingFixture);
    $eligiblePath = $eligible['frame']->asset_path;

    $first = app(CleanupOmrCameraFrames::class)->handle(1);
    $second = app(CleanupOmrCameraFrames::class)->handle(1);

    expect($first['protected'])->toBeGreaterThan(0)
        ->and(Storage::disk('sensitive')->exists($held['frame']->asset_path))->toBeTrue()
        ->and($second['deleted'])->toBeGreaterThan(0)
        ->and(Storage::disk('sensitive')->exists($eligiblePath))->toBeFalse();
});

test('rejected raw source intents are deleted while accepted and uncertain originals remain', function (): void {
    Storage::fake('sensitive');
    config(['omr-camera.frame_staging_retention_seconds' => 600]);
    $gradingFixture = buildOmrGradingFixture($this);
    $rejected = cameraFrameCleanupFixture($this, receiptOutcome: 'rejected', withRawAsset: true, gradingFixture: $gradingFixture);
    $accepted = cameraFrameCleanupFixture($this, receiptOutcome: 'accepted', withRawAsset: true, gradingFixture: $gradingFixture);
    $uncertain = cameraFrameCleanupFixture($this, receiptOutcome: 'uncertain', withRawAsset: true, gradingFixture: $gradingFixture);

    app(CleanupOmrCameraFrames::class)->handle(10);

    expect(Storage::disk('sensitive')->exists($rejected['sourcePath']))->toBeFalse()
        ->and(Storage::disk('sensitive')->exists($accepted['sourcePath']))->toBeTrue()
        ->and(Storage::disk('sensitive')->exists($uncertain['sourcePath']))->toBeTrue();
});
test('expired frameless finalization rejects proven-uncommitted raw originals and fences replay', function (): void {
    Storage::fake('sensitive');
    Cache::forget('omr-camera:source-assets-cleanup-cursor');
    $record = cameraFrameCleanupFixture($this, receiptOutcome: 'reserved', withRawAsset: true);
    $receipt = OmrCameraCommand::query()
        ->where('operation_id', $record['command']->operation_id)
        ->where('kind', 'CAPTURE_RECEIPT')
        ->firstOrFail();
    $receipt->forceFill([
        'target_json' => [
            'targetKey' => 'cleanup-frameless-target',
            'clientCaptureId' => (string) Str::uuid(),
            'pageNo' => 1,
        ],
        'finalization_lease_token' => (string) Str::uuid(),
        'finalization_lease_expires_at' => now()->subMinute(),
        'finalization_absolute_expires_at' => now()->subMinute(),
        'finalization_lease_generation' => 1,
    ])->saveOrFail();
    $sourcePath = (string) $record['sourcePath'];

    app(CleanupOmrCameraFrames::class)->handle(10);

    expect($receipt->fresh()->domain_outcome)->toBe('rejected')
        ->and($receipt->fresh()->finalization_lease_token)->toBeNull()
        ->and($receipt->fresh()->finalization_lease_generation)->toBe(2)
        ->and(Storage::disk('sensitive')->exists($sourcePath))->toBeFalse();
});
test('expired frameless finalization reconciles committed original evidence and retains it', function (): void {
    Storage::fake('sensitive');
    Cache::forget('omr-camera:source-assets-cleanup-cursor');
    $record = cameraFrameCleanupFixture($this, receiptOutcome: 'reserved', withRawAsset: true);
    $receipt = OmrCameraCommand::query()
        ->where('operation_id', $record['command']->operation_id)
        ->where('kind', 'CAPTURE_RECEIPT')
        ->firstOrFail();
    $sourcePath = (string) $record['sourcePath'];
    $sourceBytes = 'private-raw-source';
    $sheet = OmrCaptureSheet::query()->create([
        'batch_id' => $record['pair']->production_batch_id,
        'status' => OmrCaptureSheet::STATUS_PENDING_PAGES,
        'expected_page_count' => 2,
        'capture_mode' => OmrCaptureSheet::MODE_MANUAL,
        'version' => 0,
        'client_capture_id' => (string) Str::uuid(),
        'captured_by_user_id' => $record['command']->actor_id,
        'captured_at' => now(),
    ]);
    OmrCaptureSheetPage::query()->create([
        'sheet_id' => $sheet->id,
        'page_no' => 1,
        'status' => OmrCaptureSheetPage::STATUS_QUEUED,
        'asset_path' => $sourcePath,
        'asset_hmac' => app(SensitiveAssetStore::class)->hmac($sourceBytes),
        'asset_hmac_key_id' => (string) config('omr.keyring.active_key_id'),
        'asset_key_id' => (string) config('omr.keyring.active_key_id'),
        'asset_format_version' => 'v1',
        'asset_state' => OmrCaptureSheet::ASSET_LIVE,
        'original_mime' => 'image/png',
        'original_bytes' => strlen($sourceBytes),
    ]);
    $receipt->forceFill([
        'target_json' => [
            'targetKey' => 'cleanup-frameless-accepted-target',
            'clientCaptureId' => (string) $sheet->client_capture_id,
            'pageNo' => 1,
            'sheetId' => (int) $sheet->id,
        ],
        'finalization_lease_token' => (string) Str::uuid(),
        'finalization_lease_expires_at' => now()->subMinute(),
        'finalization_absolute_expires_at' => now()->subMinute(),
        'finalization_lease_generation' => 1,
    ])->saveOrFail();

    app(CleanupOmrCameraFrames::class)->handle(10);

    expect($receipt->fresh()->domain_outcome)->toBe('accepted')
        ->and(Storage::disk('sensitive')->exists($sourcePath))->toBeTrue();
});

test('rejected raw intents with a live diagnostic/template asset reference are held', function (): void {
    Storage::fake('sensitive');
    $record = cameraFrameCleanupFixture($this, receiptOutcome: 'rejected', withRawAsset: true);
    $templateVersionId = $record['pair']->productionBatch->template_version_id;
    DB::table('omr_template_assets')->insert([
        'template_version_id' => $templateVersionId,
        'kind' => 'evidence',
        'disk' => 'sensitive',
        'path' => $record['sourcePath'],
        'mime' => 'image/png',
        'bytes' => 18,
        'sha256' => hash('sha256', 'private-raw-source'),
        'metadata' => json_encode(['source' => 'omr_diagnostic']),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    app(CleanupOmrCameraFrames::class)->handle(10);

    expect(Storage::disk('sensitive')->exists($record['sourcePath']))->toBeTrue();
});

test('expired staged originals with unresolved or accepted receipts are never deleted', function (): void {
    Storage::fake('sensitive');
    config(['omr-camera.frame_staging_retention_seconds' => 600]);
    $grading = buildOmrGradingFixture($this);
    $uncertain = cameraFrameCleanupFixture($this, receiptOutcome: 'uncertain', gradingFixture: $grading);
    $accepted = cameraFrameCleanupFixture($this, receiptOutcome: 'accepted', gradingFixture: $grading);
    $cancelRace = cameraFrameCleanupFixture($this, OmrCameraFrame::STATE_CANCELLED, 'reserved', gradingFixture: $grading);
    $consumed = cameraFrameCleanupFixture($this, OmrCameraFrame::STATE_CONSUMED, 'accepted', gradingFixture: $grading);
    $uncertainPath = $uncertain['frame']->asset_path;
    $acceptedPath = $accepted['frame']->asset_path;
    $cancelPath = $cancelRace['frame']->asset_path;
    $consumedPath = $consumed['frame']->asset_path;

    $result = app(CleanupOmrCameraFrames::class)->handle(10);

    expect($result['deleted'])->toBe(0)
        ->and(Storage::disk('sensitive')->exists($uncertainPath))->toBeTrue()
        ->and(Storage::disk('sensitive')->exists($acceptedPath))->toBeTrue()
        ->and(Storage::disk('sensitive')->exists($cancelPath))->toBeTrue()
        ->and(Storage::disk('sensitive')->exists($consumedPath))->toBeTrue()
        ->and($uncertain['frame']->fresh()->state)->toBe(OmrCameraFrame::STATE_STAGED)
        ->and($accepted['frame']->fresh()->state)->toBe(OmrCameraFrame::STATE_STAGED)
        ->and($cancelRace['frame']->fresh()->state)->toBe(OmrCameraFrame::STATE_CANCELLED)
        ->and($consumed['frame']->fresh()->state)->toBe(OmrCameraFrame::STATE_CONSUMED);
});

test('storage deletion failure retains private asset metadata for a later retry', function (): void {
    Storage::fake('sensitive');
    config(['omr-camera.frame_staging_retention_seconds' => 600]);
    $record = cameraFrameCleanupFixture($this);
    $path = $record['frame']->asset_path;
    $disk = Mockery::mock();
    $disk->shouldReceive('delete')->twice()->andReturnFalse();
    $disk->shouldReceive('exists')->once()->with($path)->andReturnTrue();
    Storage::shouldReceive('disk')->with('sensitive')->andReturn($disk);

    $result = app(CleanupOmrCameraFrames::class)->handle(10);

    expect($result['failed'])->toBe(1)
        ->and($record['frame']->fresh()->state)->toBe(OmrCameraFrame::STATE_EXPIRED)
        ->and($record['frame']->fresh()->asset_path)->toBe($path)
        ->and($record['frame']->fresh()->asset_hmac)->toBeString();
});
function cameraFrameCleanupFinalizingFixture(TestCase $test, bool $withCommittedPage): array
{
    $record = cameraFrameCleanupFixture(
        $test,
        OmrCameraFrame::STATE_FINALIZING,
        'reserved',
        true,
    );
    $frame = $record['frame'];
    $receipt = OmrCameraCommand::query()
        ->where('actor_id', $record['command']->actor_id)
        ->where('workbench_key', $record['command']->workbench_key)
        ->where('operation_id', $record['command']->operation_id)
        ->where('kind', 'CAPTURE_RECEIPT')
        ->firstOrFail();
    $bytes = (string) UploadedFile::fake()->image('expired-finalization.png', 24, 24)->get();
    $assets = app(SensitiveAssetStore::class);
    $assets->store($bytes, (string) $record['sourcePath']);
    $clientCaptureId = (string) Str::uuid();
    $pageNo = 1;
    $sheet = null;
    if ($withCommittedPage) {
        $sheet = OmrCaptureSheet::query()->create([
            'batch_id' => $record['pair']->production_batch_id,
            'status' => OmrCaptureSheet::STATUS_PENDING_PAGES,
            'expected_page_count' => 2,
            'capture_mode' => OmrCaptureSheet::MODE_MANUAL,
            'version' => 0,
            'client_capture_id' => $clientCaptureId,
            'captured_by_user_id' => $record['command']->actor_id,
            'captured_at' => now(),
        ]);
        OmrCaptureSheetPage::query()->create([
            'sheet_id' => $sheet->id,
            'page_no' => $pageNo,
            'status' => OmrCaptureSheetPage::STATUS_QUEUED,
            'asset_path' => $record['sourcePath'],
            'asset_hmac' => $assets->hmac($bytes),
            'asset_hmac_key_id' => (string) config('omr.keyring.active_key_id'),
            'asset_key_id' => (string) config('omr.keyring.active_key_id'),
            'asset_format_version' => 'v1',
            'asset_state' => OmrCaptureSheet::ASSET_LIVE,
            'original_mime' => 'image/png',
            'original_bytes' => strlen($bytes),
        ]);
    }
    $leaseToken = (string) Str::uuid();
    $target = [
        'targetKey' => 'cleanup-finalization-target',
        'target_key' => 'cleanup-finalization-target',
        'clientCaptureId' => $clientCaptureId,
        'client_capture_id' => $clientCaptureId,
        'pageNo' => $pageNo,
        'page_no' => $pageNo,
        'sheetId' => $sheet?->id,
        'sheet_id' => $sheet?->id,
    ];
    $payload = (array) $receipt->payload_json;
    $payload['workbench'] = [
        'workbench_id' => 'cleanup-test',
        'production_batch_id' => $record['pair']->production_batch_id,
    ];
    $payload['source_assets'] = [[
        'path' => $record['sourcePath'],
        'hmac' => $assets->hmac($bytes),
        'created_at' => now()->toISOString(),
    ]];
    $receipt->forceFill([
        'payload_json' => $payload,
        'target_json' => $target,
        'domain_outcome' => 'reserved',
        'finalization_lease_token' => $leaseToken,
        'finalization_lease_expires_at' => now()->subMinute(),
        'finalization_absolute_expires_at' => now()->subMinute(),
        'finalization_lease_generation' => 1,
    ])->saveOrFail();
    $assets->store($bytes, (string) $frame->asset_path);
    $frame->forceFill([
        'state' => OmrCameraFrame::STATE_FINALIZING,
        'input_sha256' => hash('sha256', $bytes),
        'byte_count' => strlen($bytes),
        'mime_type' => 'image/png',
        'asset_hmac' => $assets->hmac($bytes),
        'finalization_fingerprint' => hash('sha256', 'expired-finalization'),
        'finalization_lease_token' => $leaseToken,
        'finalization_lease_expires_at' => now()->subMinute(),
        'finalization_absolute_expires_at' => now()->subMinute(),
    ])->saveOrFail();

    return [...$record, 'receipt' => $receipt, 'clientCaptureId' => $clientCaptureId];
}

test('expired finalizing work without domain evidence is fenced and its private originals become reclaimable', function (): void {
    Storage::fake('sensitive');
    Cache::forget('omr-camera:staging-frames-cleanup-cursor');
    Cache::forget('omr-camera:source-assets-cleanup-cursor');
    config(['omr-camera.frame_staging_retention_seconds' => 600]);
    $record = cameraFrameCleanupFinalizingFixture($this, false);
    $framePath = $record['frame']->asset_path;
    $sourcePath = $record['sourcePath'];

    app(CleanupOmrCameraFrames::class)->handle(10);

    expect($record['receipt']->fresh()->domain_outcome)->toBe('rejected')
        ->and($record['frame']->fresh()->state)->toBe(OmrCameraFrame::STATE_EXPIRED)
        ->and(Storage::disk('sensitive')->exists($framePath))->toBeFalse()
        ->and(Storage::disk('sensitive')->exists($sourcePath))->toBeFalse();
});

test('expired finalizing work reconciles persisted capture evidence and retains committed originals', function (): void {
    Storage::fake('sensitive');
    Cache::forget('omr-camera:staging-frames-cleanup-cursor');
    Cache::forget('omr-camera:source-assets-cleanup-cursor');
    config(['omr-camera.frame_staging_retention_seconds' => 600]);
    $record = cameraFrameCleanupFinalizingFixture($this, true);
    $framePath = $record['frame']->asset_path;
    $sourcePath = $record['sourcePath'];

    app(CleanupOmrCameraFrames::class)->handle(10);

    expect($record['receipt']->fresh()->domain_outcome)->toBe('accepted')
        ->and($record['frame']->fresh()->state)->toBe(OmrCameraFrame::STATE_CONSUMED)
        ->and(Storage::disk('sensitive')->exists($framePath))->toBeTrue()
        ->and(Storage::disk('sensitive')->exists($sourcePath))->toBeTrue();
});
