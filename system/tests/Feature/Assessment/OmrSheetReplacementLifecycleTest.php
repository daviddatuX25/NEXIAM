<?php

use App\Assessment\Actions\Omr\ReplaceOmrSheetPage;
use App\Assessment\Actions\Omr\UploadOmrCaptureSheet;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\OmrCaptureBatch;
use App\Models\Assessment\OmrCaptureSheet;
use App\Models\Assessment\OmrCaptureSheetPageRevision;
use App\Models\Assessment\OmrTemplateBookletPair;
use App\Support\SensitiveAssetStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

test('reasoned replacement appends page history, preserves old encrypted bytes, and commits audit', function (): void {
    Storage::fake('sensitive');
    $fixture = buildOmrGradingFixture($this);
    $pair = OmrTemplateBookletPair::query()
        ->where('status', OmrTemplateBookletPair::STATUS_ACTIVE)
        ->whereHas('templateVersion', fn ($query) => $query->where('version', 3))
        ->firstOrFail();
    $guidance = $fixture['guidance'];
    $grading = $fixture['grading'];
    $batch = OmrCaptureBatch::query()->create([
        'grading_session_id' => $grading->id,
        'template_version_id' => $pair->template_version_id,
        'booklet_version_id' => $pair->booklet_version_id,
        'status' => OmrCaptureBatch::STATUS_OPEN,
        'version' => 0,
        'uploaded_by_user_id' => $guidance->id,
        'opened_by_user_id' => $guidance->id,
        'opened_at' => now(),
    ]);

    $original = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
    $replacement = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAQAAABFaP0WAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true);
    $sheet = app(UploadOmrCaptureSheet::class)->handle(
        $batch,
        $original,
        'image/png',
        OmrCaptureSheet::MODE_AUTO,
        (string) Str::uuid(),
        $guidance,
        null,
        1,
    );
    $sheet->update(['status' => OmrCaptureSheet::STATUS_PENDING_PAGES]);
    expect($sheet->status)->toBe(OmrCaptureSheet::STATUS_PENDING_PAGES);

    $page = $sheet->pages()->where('page_no', 1)->firstOrFail();
    $oldPath = (string) $page->asset_path;
    $oldHmac = (string) $page->asset_hmac;
    $assetStore = app(SensitiveAssetStore::class);
    expect($assetStore->read($oldPath, $oldHmac))->toBe($original);

    $idempotencyKey = (string) Str::uuid();
    $replacementReason = 'The page was captured at an angle.';
    app(ReplaceOmrSheetPage::class)->handle(
        $sheet,
        1,
        (int) $page->id,
        (int) $page->current_revision,
        $replacement,
        'image/png',
        $guidance,
        $replacementReason,
        $idempotencyKey,
    );
    $page->refresh();
    $newPath = (string) $page->asset_path;
    $history = $page->revisions()->orderBy('revision')->get();

    expect($newPath)->not->toBe($oldPath)
        ->and(Storage::disk('sensitive')->exists($newPath))->toBeTrue()
        ->and($assetStore->read($newPath, (string) $page->asset_hmac))->toBe($replacement)
        ->and(Storage::disk('sensitive')->exists($oldPath))->toBeTrue()
        ->and($assetStore->read($oldPath, $oldHmac))->toBe($original)
        ->and((string) $history->first()->asset_hmac)->toBe($oldHmac)
        ->and($history->last()->replacement_reason)->toBe($replacementReason)
        ->and((int) $page->current_revision)->toBe(2)
        ->and($page->currentRevision?->getKey())->toBe($history->last()->getKey())
        ->and($page->crop_policy)->toBe('auto_document_quad')
        ->and(DB::table('audit_events')->where('event_type', 'grading.omr_sheet_page_replaced')->exists())->toBeTrue();

    $filesAfterReplacement = Storage::disk('sensitive')->allFiles();
    app(ReplaceOmrSheetPage::class)->handle(
        $sheet,
        1,
        (int) $page->id,
        1,
        $replacement,
        'image/png',
        $guidance,
        $replacementReason,
        $idempotencyKey,
    );
    expect($page->revisions()->count())->toBe(2)
        ->and(Storage::disk('sensitive')->allFiles())->toBe($filesAfterReplacement);

    expect(fn () => app(ReplaceOmrSheetPage::class)->handle(
        $sheet,
        1,
        (int) $page->id,
        1,
        $original,
        'image/png',
        $guidance,
        $replacementReason,
        $idempotencyKey,
    ))->toThrow(RuntimeException::class, 'idempotency_conflict');

    $firstReplacementPath = (string) $page->asset_path;
    $secondReason = 'The replacement page was still blurred.';
    app(ReplaceOmrSheetPage::class)->handle(
        $sheet,
        1,
        (int) $page->id,
        2,
        $original,
        'image/png',
        $guidance,
        $secondReason,
        (string) Str::uuid(),
    );
    $page->refresh();
    $secondPath = (string) $page->asset_path;
    $consecutiveHistory = $page->revisions()->orderBy('id')->get();
    expect((int) $page->current_revision)->toBe(3)
        ->and($consecutiveHistory)->toHaveCount(4)
        ->and($consecutiveHistory->last()->revision_kind)->toBe(OmrCaptureSheetPageRevision::KIND_REPLACEMENT)
        ->and($consecutiveHistory->last()->replacement_reason)->toBe($secondReason)
        ->and(Storage::disk('sensitive')->exists($firstReplacementPath))->toBeTrue()
        ->and($assetStore->read($firstReplacementPath, (string) $history->last()->asset_hmac))->toBe($replacement)
        ->and($assetStore->read($secondPath, (string) $page->asset_hmac))->toBe($original);

    $filesBeforeDeniedRequests = Storage::disk('sensitive')->allFiles();
    expect(fn () => app(ReplaceOmrSheetPage::class)->handle(
        $sheet,
        1,
        (int) $page->id + 1,
        3,
        $replacement,
        'image/png',
        $guidance,
        $replacementReason,
        (string) Str::uuid(),
    ))->toThrow(RuntimeException::class, 'source_stale');

    expect(fn () => app(ReplaceOmrSheetPage::class)->handle(
        $sheet,
        1,
        (int) $page->id,
        2,
        $replacement,
        'image/png',
        $guidance,
        $replacementReason,
        (string) Str::uuid(),
    ))->toThrow(RuntimeException::class, 'source_stale');

    expect(fn () => app(ReplaceOmrSheetPage::class)->handle(
        $sheet,
        1,
        (int) $page->id,
        3,
        $replacement,
        'image/png',
        $guidance,
        '   ',
        (string) Str::uuid(),
    ))->toThrow(RuntimeException::class, 'reason_or_idempotency_invalid');

    $grading->update(['status' => GradingSession::STATUS_FINALIZED]);
    expect(fn () => app(ReplaceOmrSheetPage::class)->handle(
        $sheet,
        1,
        (int) $page->id,
        3,
        $replacement,
        'image/png',
        $guidance,
        $replacementReason,
        (string) Str::uuid(),
    ))->toThrow(RuntimeException::class, 'source_stale');

    expect($page->revisions()->count())->toBe(4)
        ->and(Storage::disk('sensitive')->allFiles())->toBe($filesBeforeDeniedRequests);
});

test('failed transaction preserves old evidence and deletes new candidate asset', function (): void {
    Storage::fake('sensitive');
    $fixture = buildOmrGradingFixture($this);
    $pair = OmrTemplateBookletPair::query()
        ->where('status', OmrTemplateBookletPair::STATUS_ACTIVE)
        ->whereHas('templateVersion', fn ($query) => $query->where('version', 3))
        ->firstOrFail();
    $guidance = $fixture['guidance'];
    $grading = $fixture['grading'];
    $batch = OmrCaptureBatch::query()->create([
        'grading_session_id' => $grading->id,
        'template_version_id' => $pair->template_version_id,
        'booklet_version_id' => $pair->booklet_version_id,
        'status' => OmrCaptureBatch::STATUS_OPEN,
        'version' => 0,
        'uploaded_by_user_id' => $guidance->id,
        'opened_by_user_id' => $guidance->id,
        'opened_at' => now(),
    ]);

    $original = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
    $replacement = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAQAAABFaP0WAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true);
    $sheet = app(UploadOmrCaptureSheet::class)->handle(
        $batch,
        $original,
        'image/png',
        OmrCaptureSheet::MODE_AUTO,
        (string) Str::uuid(),
        $guidance,
        null,
        1,
    );
    $sheet->update(['status' => OmrCaptureSheet::STATUS_PENDING_PAGES]);
    expect($sheet->status)->toBe(OmrCaptureSheet::STATUS_PENDING_PAGES);

    $page = $sheet->pages()->where('page_no', 1)->firstOrFail();
    $oldPath = (string) $page->asset_path;
    $oldHmac = (string) $page->asset_hmac;
    $assetStore = app(SensitiveAssetStore::class);
    $filesBeforeReplacement = Storage::disk('sensitive')->allFiles();

    $audit = Mockery::mock(RecordAuditEvent::class);
    $audit->shouldReceive('handle')->once()->andThrow(new RuntimeException('Audit failed'));
    app()->instance(RecordAuditEvent::class, $audit);

    expect(fn () => app(ReplaceOmrSheetPage::class)->handle(
        $sheet,
        1,
        (int) $page->id,
        (int) $page->current_revision,
        $replacement,
        'image/png',
        $guidance,
        'The original capture was incomplete.',
        (string) Str::uuid(),
    ))
        ->toThrow(RuntimeException::class, 'Audit failed');

    $page->refresh();
    expect(Storage::disk('sensitive')->exists($oldPath))->toBeTrue()
        ->and($assetStore->read($oldPath, $oldHmac))->toBe($original)
        ->and(Storage::disk('sensitive')->allFiles())->toBe($filesBeforeReplacement)
        ->and($sheet->fresh()->status)->toBe(OmrCaptureSheet::STATUS_PENDING_PAGES)
        ->and($page->fresh()->asset_path)->toBe($oldPath);
});
