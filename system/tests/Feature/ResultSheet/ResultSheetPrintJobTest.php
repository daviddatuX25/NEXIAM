<?php

use App\Assessment\Actions\RecordScores;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Jobs\Printing\RenderPrintJob;
use App\Models\Foundation\AuditEvent;
use App\Models\Printing\PrintJob;
use App\Models\Release\ResultDocument;
use App\Models\Release\ResultRelease;
use App\Models\ResultSheet\ResultDocumentPrintReceipt;
use App\Models\ResultSheet\ResultSheetTemplate;
use App\Models\User;
use App\Printing\PrintRenderer;
use App\Release\Actions\PrepareResultDocument;
use App\ResultSheet\Actions\RequestResultSheetPrint;
use App\Support\SensitiveAssetStore;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

require_once __DIR__.'/../../Support/NormConversionFixtures.php';

function resultSheetTemplateSeed(): ResultSheetTemplate
{
    $template = ResultSheetTemplate::query()->where('mode', ResultSheetTemplate::MODE_HTML)->first();

    if ($template === null) {
        $template = ResultSheetTemplate::query()->create([
            'name' => 'Seed HTML',
            'mode' => ResultSheetTemplate::MODE_HTML,
            'paper_size' => 'a4',
            'orientation' => 'portrait',
            'logical_unit' => 'full',
            'content' => '<h1>{{institution_name}}</h1><p>{{applicant_name}} ({{applicant_reference}})</p>'
                .'<table><tbody class="scores-rows-placeholder"></tbody></table>',
            'is_active' => false,
        ]);
    }

    // Print-pipeline tests intentionally exercise the HTML seam; make that
    // choice explicit now that the official DOCX template is the seed default.
    ResultSheetTemplate::query()
        ->whereKey($template->id)
        ->update(['is_active' => true]);
    ResultSheetTemplate::query()
        ->where('id', '!=', $template->id)
        ->update(['is_active' => false]);

    return $template->fresh();
}

function releasedScoredAttempts(TestCase $test, int $count = 1): array
{
    $fixture = buildGradingFixture($test, applicantCount: $count);
    $guidance = $fixture['guidance'];
    $grading = $fixture['grading'];
    $guidance->givePermissionTo([
        CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG,
        CapabilityCatalog::GUIDANCE_SCORING_MANUAL,
    ]);
    $profile = createTestApprovedProfileVersion($test, $guidance, 'PRINT-JOB-'.Str::upper(Str::random(10)));
    $kit = createTestActiveKitVersion($guidance, $profile);

    foreach ($fixture['attempts'] as $attempt) {
        app(RecordScores::class)->handle(
            gradingSession: $grading,
            attempt: $attempt,
            scores: [[
                'metric_code' => 'RAW_A',
                'typed_outputs' => ['percentile_rank' => '82', 'stanine' => '7'],
                'external_provenance' => 'Synthetic software-only result; no institutional claim',
            ]],
            actor: $guidance,
            idempotencyKey: 'print-job-'.$attempt->id.'-'.Str::uuid(),
            scoringMode: 'manual_converted',
            testKitVersionId: (int) $kit->id,
        );
    }

    resultSheetTemplateSeed();

    $releases = [];

    foreach ($fixture['attempts'] as $attempt) {
        $test->actingAs($guidance)
            ->post(route('release.prepare', $attempt))
            ->assertRedirect();

        $document = ResultDocument::query()->where('admission_attempt_id', $attempt->id)->latest('id')->firstOrFail();
        expect($document->status)->toBe(ResultDocument::STATUS_READY);

        $test->actingAs($guidance)
            ->post(route('release.store', $attempt), [
                'idempotency_key' => 'rel-'.$attempt->id,
                'mode' => 'f2f',
            ])
            ->assertRedirect();

        $releases[] = ResultRelease::query()
            ->where('admission_attempt_id', $attempt->id)
            ->where('is_current', true)
            ->firstOrFail();
    }

    return ['guidance' => $guidance, 'attempts' => $fixture['attempts'], 'releases' => $releases, 'grading' => $fixture['grading']];
}

function releasedScoredAttempt(TestCase $test): array
{
    $result = releasedScoredAttempts($test, 1);

    return ['guidance' => $result['guidance'], 'attempt' => $result['attempts'][0], 'release' => $result['releases'][0]];
}

test('requesting a result sheet print creates a queued job with pinned inputs', function (): void {
    Queue::fake();
    $fixture = releasedScoredAttempt($this);
    $guidance = $fixture['guidance'];
    $release = $fixture['release'];

    $job = app(RequestResultSheetPrint::class)->handle($guidance, [$release->result_document_id], copies: 1);

    expect($job->artifact_type)->toBe(PrintJob::ARTIFACT_RESULT_SHEET)
        ->and($job->status)->toBe(PrintJob::STATUS_QUEUED)
        ->and($job->pinned_input_json['release_ids'])->toBe([$release->id])
        ->and($job->pinned_input_json['copies'])->toBe(1)
        ->and($job->pinned_input_json['template_id'])->toBe(resultSheetTemplateSeed()->id)
        ->and($job->items()->count())->toBe(1);
});

test('duplicate requests return the existing job', function (): void {
    Queue::fake();
    $fixture = releasedScoredAttempt($this);
    $guidance = $fixture['guidance'];
    $release = $fixture['release'];

    $first = app(RequestResultSheetPrint::class)->handle($guidance, [$release->result_document_id], 1);
    $second = app(RequestResultSheetPrint::class)->handle($guidance, [$release->result_document_id], 1);

    expect($second->id)->toBe($first->id)
        ->and($second->items()->count())->toBe(1);
});

test('online releases can be printed', function (): void {
    $fixture = releasedScoredAttempt($this);
    $guidance = $fixture['guidance'];
    $release = $fixture['release'];

    $release->update(['mode' => 'online']);

    $job = app(RequestResultSheetPrint::class)->handle($guidance, [$release->result_document_id], 1);

    expect($job->status)->toBe(PrintJob::STATUS_QUEUED)
        ->and($job->items()->count())->toBe(1);
});

test('no active template refuses printing', function (): void {
    $fixture = releasedScoredAttempt($this);
    $guidance = $fixture['guidance'];
    $release = $fixture['release'];

    ResultSheetTemplate::query()->update(['is_active' => false]);

    expect(fn () => app(RequestResultSheetPrint::class)->handle($guidance, [$release->result_document_id], 1))
        ->toThrow(RuntimeException::class, 'template');
});

test('render job produces a ready pdf without confirming physical printing', function (): void {
    Queue::fake();
    $fixture = releasedScoredAttempt($this);
    $guidance = $fixture['guidance'];
    $release = $fixture['release'];
    $document = ResultDocument::query()->findOrFail($release->result_document_id);

    $job = app(RequestResultSheetPrint::class)->handle($guidance, [$document->id], 1);
    (new RenderPrintJob($job->id))->handle(
        app(PrintRenderer::class),
        app(SensitiveAssetStore::class),
        app(RecordAuditEvent::class),
    );

    $job->refresh();
    $document->refresh();
    expect($job->status)->toBe(PrintJob::STATUS_READY)
        ->and($job->file_path)->not->toBeNull()
        ->and($job->file_checksum)->toHaveLength(64)
        ->and($document->artifact_manifest['pdf']['status'])->toBe('ready')
        ->and($release->fresh()->result_printed_at)->toBeNull();

    $job->update(['status' => PrintJob::STATUS_QUEUED, 'file_path' => null, 'file_checksum' => null]);
    (new RenderPrintJob($job->id))->handle(
        app(PrintRenderer::class),
        app(SensitiveAssetStore::class),
        app(RecordAuditEvent::class),
    );

    expect($release->fresh()->result_printed_at)->toBeNull();
});

test('bulk job renders a merged pdf with progress and per-item manifest', function (): void {
    Queue::fake();
    $fixture = releasedScoredAttempts($this, 2);
    $guidance = $fixture['guidance'];

    $documentIds = array_column($fixture['releases'], 'result_document_id');

    $job = app(RequestResultSheetPrint::class)->handle($guidance, $documentIds, copies: 2);

    expect($job->progress_total)->toBe(2)
        ->and($job->pinned_input_json['copies'])->toBe(2);

    $job->update(['status' => PrintJob::STATUS_QUEUED, 'progress_done' => 0]);
    (new RenderPrintJob($job->id))->handle(
        app(PrintRenderer::class),
        app(SensitiveAssetStore::class),
        app(RecordAuditEvent::class),
    );

    $job->refresh();
    expect($job->status)->toBe(PrintJob::STATUS_READY)
        ->and($job->progress_done)->toBe($job->progress_total)
        ->and($job->per_item_manifest)->toHaveCount(2)
        ->and(collect($job->per_item_manifest)->every(fn ($item) => ($item['status'] ?? '') === 'ready'))->toBeTrue();
});

test('a prepared print job retains its frozen template when the live template becomes unavailable', function (): void {
    Queue::fake();
    $fixture = releasedScoredAttempt($this);
    $guidance = $fixture['guidance'];
    $release = $fixture['release'];

    $job = app(RequestResultSheetPrint::class)->handle($guidance, [$release->result_document_id], 1);

    // Rendering must use the prepared document's pinned bytes, not this replacement.
    ResultSheetTemplate::query()->update([
        'mode' => 'docx',
        'content' => null,
        'document_path' => 'result-sheet-templates/missing.docx',
        'document_extension' => 'docx',
        'is_active' => true,
    ]);

    (new RenderPrintJob($job->id))->handle(
        app(PrintRenderer::class),
        app(SensitiveAssetStore::class),
        app(RecordAuditEvent::class),
    );

    $job->refresh();
    $bytes = app(SensitiveAssetStore::class)->read($job->file_path);
    expect($job->status)->toBe(PrintJob::STATUS_READY)
        ->and(str_starts_with($bytes, '%PDF-'))->toBeTrue()
        ->and(ResultDocumentPrintReceipt::query()
            ->where('result_document_id', $release->result_document_id)
            ->where('operation', 'confirmed')->count())->toBe(0);
});

test('download re-authorizes, verifies checksum, and is audited', function (): void {
    Queue::fake();
    $fixture = releasedScoredAttempt($this);
    $guidance = $fixture['guidance'];
    $release = $fixture['release'];

    $job = app(RequestResultSheetPrint::class)->handle($guidance, [$release->result_document_id], 1);
    (new RenderPrintJob($job->id))->handle(
        app(PrintRenderer::class),
        app(SensitiveAssetStore::class),
        app(RecordAuditEvent::class),
    );

    $response = $this->actingAs($guidance)
        ->get(route('printing.jobs.download', $job))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    expect($response->headers->get('Cache-Control'))->toContain('private')
        ->and($response->headers->get('Cache-Control'))->toContain('no-store');

    $this->actingAs(User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail())
        ->get(route('printing.jobs.download', $job))
        ->assertForbidden();

    expect(AuditEvent::query()
        ->where('event_type', 'printing.job_downloaded')
        ->whereJsonContains('payload', ['print_job_id' => $job->id])
        ->exists())->toBeTrue();
});

test('a print worker rejects a prepared document superseded after the request', function (): void {
    Queue::fake();
    $fixture = releasedScoredAttempt($this);
    $guidance = $fixture['guidance'];
    $attempt = $fixture['attempt'];
    $release = $fixture['release'];

    $job = app(RequestResultSheetPrint::class)->handle($guidance, [$release->result_document_id], 1);

    $template = resultSheetTemplateSeed();
    $template->update(['content' => $template->content.'<p>Synthetic template revision.</p>']);
    $replacement = app(PrepareResultDocument::class)->handle($attempt->fresh(), $guidance);
    expect($replacement->id)->not->toBe($release->result_document_id);

    (new RenderPrintJob($job->id))->handle(
        app(PrintRenderer::class),
        app(SensitiveAssetStore::class),
        app(RecordAuditEvent::class),
    );

    $job->refresh();
    expect($job->status)->toBe(PrintJob::STATUS_FAILED)
        ->and($job->safe_error)->toBe('document_stale')
        ->and($job->items()->firstOrFail()->safe_error)->toBe('document_stale');
});

test('a print worker rejects tampered frozen template bytes rather than substituting a live template', function (): void {
    Queue::fake();
    $fixture = releasedScoredAttempt($this);
    $guidance = $fixture['guidance'];
    $release = $fixture['release'];

    $job = app(RequestResultSheetPrint::class)->handle($guidance, [$release->result_document_id], 1);
    $document = ResultDocument::query()->findOrFail($release->result_document_id);
    $snapshot = $document->snapshot_payload_json;
    $snapshot['_template_source']['bytes_base64'] = base64_encode('<p>Synthetic tampered source.</p>');
    $document->update(['snapshot_payload_json' => $snapshot]);

    (new RenderPrintJob($job->id))->handle(
        app(PrintRenderer::class),
        app(SensitiveAssetStore::class),
        app(RecordAuditEvent::class),
    );

    $job->refresh();
    expect($job->status)->toBe(PrintJob::STATUS_FAILED)
        ->and($job->safe_error)->toBe('render_failed')
        ->and($job->items()->firstOrFail()->safe_error)->toBe('render_failed');
});

test('prune command deletes expired ready artifacts and audits the cleanup', function (): void {
    Queue::fake();
    $fixture = releasedScoredAttempt($this);
    $guidance = $fixture['guidance'];
    $release = $fixture['release'];

    $job = app(RequestResultSheetPrint::class)->handle($guidance, [$release->result_document_id], 1);
    (new RenderPrintJob($job->id))->handle(
        app(PrintRenderer::class),
        app(SensitiveAssetStore::class),
        app(RecordAuditEvent::class),
    );
    $item = $job->items()->firstOrFail();
    $docxBytes = 'filled-docx';
    $docxPath = 'print-jobs/'.$job->id.'/items/'.$item->id.'-docx.docx';
    app(SensitiveAssetStore::class)->store($docxBytes, $docxPath);
    $item->update([
        'docx_path' => $docxPath,
        'docx_checksum' => hash('sha256', $docxBytes),
    ]);

    $job->refresh();
    $job->completed_at = now()->subDays(60);
    $job->save();
    expect($job->file_path)->not->toBeNull()
        ->and($item->fresh()->docx_path)->toBe($docxPath);

    $this->artisan('result-sheet:prune --dry-run')
        ->assertSuccessful();
    expect($item->fresh()->docx_path)->toBe($docxPath)
        ->and(Storage::disk('sensitive')->exists($docxPath))->toBeTrue();

    $this->artisan('result-sheet:prune')
        ->assertSuccessful();

    expect($job->fresh()->file_path)->toBeNull()
        ->and($item->fresh()->docx_path)->toBeNull()
        ->and(Storage::disk('sensitive')->exists($docxPath))->toBeFalse()
        ->and(AuditEvent::query()
            ->where('event_type', 'printing.job_pruned')
            ->whereJsonContains('payload', ['print_job_id' => $job->id])
            ->exists())->toBeTrue();
});
