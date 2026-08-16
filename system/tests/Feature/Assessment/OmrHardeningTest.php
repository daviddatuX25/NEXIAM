<?php

use App\Assessment\Actions\Omr\ConfirmOmrCaptureBatch;
use App\Assessment\Actions\Omr\CreateOmrCaptureBatch;
use App\Assessment\Actions\Omr\RetryOmrCaptureSheet;
use App\Assessment\Actions\Omr\ReviewOmrCaptureSheet;
use App\Assessment\Actions\Omr\RunOmrSheetAnalysis;
use App\Assessment\Omr\OmrDetectionRequest;
use App\Models\Assessment\OmrCaptureBatch;
use App\Models\Assessment\OmrCaptureSheet;
use App\Models\Assessment\OmrProcessingRun;
use App\Models\Assessment\OmrTemplateBookletPair;
use App\Support\SensitiveAssetStore;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

use function hash;

/**
 * Creates an open batch, a queued sheet with a valid sensitive asset, and a
 * pending processing run pinned to the active pair — the same shape the real
 * upload flow produces.
 *
 * @return array{0: OmrCaptureBatch, 1: OmrCaptureSheet, 2: OmrProcessingRun}
 */
function hardeningOmrFixture(TestCase $test, ?array $fixture = null): array
{
    Storage::fake('sensitive');
    $fixture ??= buildGradingFixture($test);
    seedE2OmrFoundation();
    $guidance = $fixture['guidance'];
    $grading = $fixture['grading'];
    $pair = OmrTemplateBookletPair::query()->where('status', OmrTemplateBookletPair::STATUS_ACTIVE)->firstOrFail();

    $batch = app(CreateOmrCaptureBatch::class)->handle(
        $grading,
        $guidance,
        (int) $pair->template_version_id,
        (int) $pair->booklet_version_id,
        'hardening-'.uniqid(),
    );

    $assetStore = app(SensitiveAssetStore::class);
    $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
    $path = $assetStore->store($bytes, 'omr/test/'.uniqid().'.img');

    $sheet = OmrCaptureSheet::query()->create([
        'batch_id' => $batch->id,
        'status' => OmrCaptureSheet::STATUS_QUEUED,
        'capture_mode' => OmrCaptureSheet::MODE_AUTO,
        'version' => 0,
        'client_capture_id' => (string) Str::uuid(),
        'asset_path' => $path,
        'asset_hmac' => $assetStore->hmac($bytes),
        'asset_format_version' => 'v1',
        'asset_state' => OmrCaptureSheet::ASSET_LIVE,
        'original_mime' => 'image/png',
        'original_bytes' => strlen($bytes),
        'captured_by_user_id' => $guidance->id,
        'captured_at' => now(),
    ]);

    $run = $sheet->processingRuns()->create([
        'actor_id' => $guidance->id,
        'run_no' => 1,
        'run_uuid' => (string) Str::uuid(),
        'status' => OmrProcessingRun::STATUS_PENDING,
        'provider_name' => 'nexiam.fake',
        'provider_version' => '1',
        'request_version' => OmrDetectionRequest::ENVELOPE_VERSION,
        'expected_sheet_version' => 0,
        'input_hash' => hash('sha256', $bytes),
        'template_hash' => $batch->templateVersion->native_bundle_hash,
        'booklet_hash' => $batch->bookletVersion->answer_key_hash,
        'native_bundle_hash' => $batch->templateVersion->native_bundle_hash,
        'manifest_hash' => $batch->templateVersion->review_manifest_hash,
        'config_hash' => $batch->templateVersion->config_hash,
        'patch_identity' => $batch->templateVersion->patch_identity,
    ]);
    $sheet->forceFill(['current_processing_run_id' => $run->id])->save();

    return [$batch, $sheet, $run];
}

test('confirm blocks when a sheet is still needs-review', function (): void {
    [, $sheet, $run] = hardeningOmrFixture($this);

    app(RunOmrSheetAnalysis::class)->handle($run->id);
    $sheet->refresh();

    expect($sheet->status)->toBe(OmrCaptureSheet::STATUS_NEEDS_REVIEW)
        ->and(fn () => app(ConfirmOmrCaptureBatch::class)->handle($sheet->batch, $sheet->capturedBy))
        ->toThrow(RuntimeException::class, 'reviewed');
});

test('review rejects binding an examinee already bound to another sheet', function (): void {
    $fixture = buildGradingFixture($this);
    $guidance = $fixture['guidance'];
    $attemptId = (int) $fixture['attempts'][0]->id;

    [$batch, $first, $firstRun] = hardeningOmrFixture($this, $fixture);
    app(RunOmrSheetAnalysis::class)->handle($firstRun->id);
    $first->refresh();

    app(ReviewOmrCaptureSheet::class)->handle($first, $guidance, null, [], $attemptId);
    expect($first->fresh()->status)->toBe(OmrCaptureSheet::STATUS_REVIEWED);

    // A second sheet for the same batch must not bind the same examinee.
    $second = OmrCaptureSheet::query()->create([
        'batch_id' => $batch->id,
        'status' => OmrCaptureSheet::STATUS_NEEDS_REVIEW,
        'capture_mode' => OmrCaptureSheet::MODE_AUTO,
        'version' => 0,
        'client_capture_id' => (string) Str::uuid(),
        'asset_path' => 'omr/test/'.uniqid().'.img',
        'asset_hmac' => 'x',
        'asset_format_version' => 'v1',
        'asset_state' => OmrCaptureSheet::ASSET_LIVE,
        'original_mime' => 'image/png',
        'original_bytes' => 1,
        'captured_by_user_id' => $guidance->id,
        'captured_at' => now(),
    ]);
    $second->issues()->create(['issue_code' => 'identity_unresolved', 'severity' => 'required', 'state' => 'open', 'version' => 1]);

    expect(fn () => app(ReviewOmrCaptureSheet::class)->handle($second, $guidance, null, [], $attemptId))
        ->toThrow(RuntimeException::class, 'already bound');
});

test('database refuses a second binding to the same examinee', function (): void {
    $fixture = buildGradingFixture($this);
    $guidance = $fixture['guidance'];
    $attemptId = (int) $fixture['attempts'][0]->id;

    [$batch, $first, $firstRun] = hardeningOmrFixture($this, $fixture);
    app(RunOmrSheetAnalysis::class)->handle($firstRun->id);
    $first->refresh();
    app(ReviewOmrCaptureSheet::class)->handle($first, $guidance, null, [], $attemptId);

    // A forged second reviewed sheet bound to the same attempt must be refused
    // by the database itself (partial unique index), before any action runs.
    expect(fn () => OmrCaptureSheet::query()->create([
        'batch_id' => $batch->id,
        'status' => OmrCaptureSheet::STATUS_REVIEWED,
        'capture_mode' => OmrCaptureSheet::MODE_AUTO,
        'version' => 0,
        'client_capture_id' => (string) Str::uuid(),
        'admission_attempt_id' => $attemptId,
        'asset_path' => 'omr/test/'.uniqid().'.img',
        'asset_hmac' => 'x',
        'asset_format_version' => 'v1',
        'asset_state' => OmrCaptureSheet::ASSET_LIVE,
        'original_mime' => 'image/png',
        'original_bytes' => 1,
        'captured_by_user_id' => $guidance->id,
        'captured_at' => now(),
        'proposal_json' => $first->proposal_json,
        'reviewed_json' => $first->reviewed_json,
    ]))->toThrow(Illuminate\Database\UniqueConstraintViolationException::class);
});

test('sweeper recovers an expired claimed run and re-analyzes', function (): void {
    [, $sheet, $run] = hardeningOmrFixture($this);

    $run->update([
        'status' => OmrProcessingRun::STATUS_CLAIMED,
        'claim_token' => (string) Str::uuid(),
        'claimed_at' => now()->subMinutes(5),
        'claim_expires_at' => now()->subMinutes(3),
        'attempts' => 1,
    ]);
    $sheet->update(['status' => OmrCaptureSheet::STATUS_PROCESSING]);

    $this->artisan('omr:recover-stale-runs')->assertSuccessful();

    $run->refresh();
    $sheet->refresh();

    expect($run->status)->toBe(OmrProcessingRun::STATUS_SUCCEEDED)
        ->and($sheet->status)->toBe(OmrCaptureSheet::STATUS_NEEDS_REVIEW);
});

test('sweeper terminalizes exhausted claims', function (): void {
    [, $sheet, $run] = hardeningOmrFixture($this);

    $run->update([
        'status' => OmrProcessingRun::STATUS_CLAIMED,
        'claim_token' => (string) Str::uuid(),
        'claimed_at' => now()->subMinutes(5),
        'claim_expires_at' => now()->subMinutes(3),
        'attempts' => (int) config('omr.retry.tries', 3),
    ]);
    $sheet->update(['status' => OmrCaptureSheet::STATUS_PROCESSING]);

    $this->artisan('omr:recover-stale-runs')->assertSuccessful();

    $run->refresh();
    $sheet->refresh();

    expect($run->status)->toBe(OmrProcessingRun::STATUS_TERMINAL_FAILED)
        ->and($sheet->status)->toBe(OmrCaptureSheet::STATUS_FAILED_TERMINAL);
});

test('retry action creates the next run and re-analyzes a failed sheet', function (): void {
    $fixture = buildGradingFixture($this);
    $guidance = $fixture['guidance'];

    [, $sheet, $run] = hardeningOmrFixture($this, $fixture);

    $run->update(['status' => OmrProcessingRun::STATUS_TERMINAL_FAILED, 'diagnostic_code' => 'provider_failed']);
    $sheet->update([
        'status' => OmrCaptureSheet::STATUS_FAILED_TERMINAL,
        'version' => 1,
        'proposal_json' => ['envelope' => OmrDetectionRequest::ENVELOPE_VERSION, 'abstained' => true, 'reason' => 'provider_failed'],
    ]);

    $next = app(RetryOmrCaptureSheet::class)->handle($sheet, $guidance);
    $next->refresh();

    expect((int) $next->run_no)->toBe(2)
        ->and($next->status)->toBe(OmrProcessingRun::STATUS_SUCCEEDED)
        ->and($sheet->fresh()->status)->toBe(OmrCaptureSheet::STATUS_NEEDS_REVIEW);
});

test('retry refuses while another run is active', function (): void {
    $fixture = buildGradingFixture($this);
    $guidance = $fixture['guidance'];

    [, $sheet] = hardeningOmrFixture($this, $fixture);
    $sheet->update(['status' => OmrCaptureSheet::STATUS_FAILED_TERMINAL]);

    expect(fn () => app(RetryOmrCaptureSheet::class)->handle($sheet, $guidance))
        ->toThrow(RuntimeException::class, 'active analysis run');
});

test('aligned derivative is encrypted, persisted, and served when the provider emits one', function (): void {
    config(['omr.fake.with_aligned_image' => true]);
    $fixture = buildGradingFixture($this);
    $guidance = $fixture['guidance'];

    [$batch, $sheet, $run] = hardeningOmrFixture($this, $fixture);

    app(RunOmrSheetAnalysis::class)->handle($run->id);
    $sheet->refresh();

    expect($sheet->status)->toBe(OmrCaptureSheet::STATUS_NEEDS_REVIEW)
        ->and($sheet->aligned_asset_path)->not->toBeNull()
        ->and($sheet->aligned_hash)->toMatch('/^[a-f0-9]{64}$/');

    $bytes = app(SensitiveAssetStore::class)->read((string) $sheet->aligned_asset_path, (string) $sheet->aligned_asset_hmac);
    expect(hash('sha256', $bytes))->toBe($sheet->aligned_hash);

    $response = $this->actingAs($guidance)
        ->get(route('grading.sessions.omr.sheets.image', [$batch->gradingSession, $sheet]));
    $response->assertOk()
        ->assertHeader('Content-Type', 'image/png')
        ->assertHeader('Cache-Control', 'no-store, private');
});
