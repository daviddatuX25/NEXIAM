<?php

use App\Foundation\Actions\RecordAuditEvent;
use App\Jobs\Printing\RenderPrintJob;
use App\Models\Printing\PrintJob;
use App\Models\Printing\PrintJobItem;
use App\Models\Release\ResultDocument;
use App\Models\ResultSheet\ResultDocumentPrintReceipt;
use App\Models\ResultSheet\ResultSheetTemplate;
use App\Printing\PrintRenderer;
use App\Release\Actions\PrepareResultDocument;
use App\ResultSheet\Actions\RequestResultSheetPrint;
use App\ResultSheet\Pipeline\FillAndConvertJob;
use App\ResultSheet\Rendering\DocxToPdfConverter;
use App\ResultSheet\Rendering\PreparedResultDocumentRenderer;
use App\Support\SensitiveAssetStore;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

test('requesting print creates print_job_items rows with queued status', function (): void {
    Queue::fake();
    $fixture = releasedScoredAttempts($this, 2);
    $guidance = $fixture['guidance'];
    $documentIds = array_column($fixture['releases'], 'result_document_id');

    $job = app(RequestResultSheetPrint::class)->handle($guidance, $documentIds, copies: 1);

    expect($job->items()->count())->toBe(2)
        ->and($job->items()->first()->status)->toBe(PrintJobItem::STATUS_QUEUED)
        ->and($job->items()->first()->admission_attempt_id)->toBe($fixture['attempts'][0]->id);
});

test('rendering readies individual PDF artifacts without confirming physical printing', function (): void {
    Queue::fake();
    $fixture = releasedScoredAttempts($this, 2);
    $guidance = $fixture['guidance'];
    $documentIds = array_column($fixture['releases'], 'result_document_id');

    $job = app(RequestResultSheetPrint::class)->handle($guidance, $documentIds, copies: 1);

    (new RenderPrintJob($job->id))->handle(
        app(PrintRenderer::class),
        app(SensitiveAssetStore::class),
        app(RecordAuditEvent::class),
    );

    $job->refresh();
    $items = $job->items()->get();

    expect($job->status)->toBe(PrintJob::STATUS_READY)
        ->and($items)->toHaveCount(2)
        ->and($items->every(fn (PrintJobItem $i) => $i->status === PrintJobItem::STATUS_READY))->toBeTrue()
        ->and($items->every(fn (PrintJobItem $i) => $i->file_path !== null))->toBeTrue()
        ->and($fixture['releases'][0]->fresh()->result_printed_at)->toBeNull()
        ->and($fixture['releases'][1]->fresh()->result_printed_at)->toBeNull()
        ->and(ResultDocumentPrintReceipt::query()
            ->whereIn('result_document_id', $documentIds)->where('operation', 'confirmed')->count())->toBe(0);
});

test('individual item failure marks item failed without aborting batch', function (): void {
    Queue::fake();
    $fixture = releasedScoredAttempts($this, 2);
    $guidance = $fixture['guidance'];
    $documentIds = array_column($fixture['releases'], 'result_document_id');

    $job = app(RequestResultSheetPrint::class)->handle($guidance, $documentIds, copies: 1);

    ResultDocument::query()->whereKey($documentIds[1])
        ->update(['checksum' => hash('sha256', 'synthetic-stale-item-document')]);

    (new RenderPrintJob($job->id))->handle(
        app(PrintRenderer::class),
        app(SensitiveAssetStore::class),
        app(RecordAuditEvent::class),
    );

    $job->refresh();
    $items = $job->items()->orderBy('item_index')->get();

    expect($job->status)->toBe(PrintJob::STATUS_READY)
        ->and($items[0]->status)->toBe(PrintJobItem::STATUS_READY)
        ->and($items[1]->status)->toBe(PrintJobItem::STATUS_FAILED)
        ->and($items[1]->safe_error)->toBe('document_stale')
        ->and($fixture['releases'][0]->fresh()->result_printed_at)->toBeNull()
        ->and($fixture['releases'][1]->fresh()->result_printed_at)->toBeNull();
});

test('docx item retains filled docx and assembled output formats', function (): void {
    Queue::fake();
    $fixture = releasedScoredAttempt($this);
    $guidance = $fixture['guidance'];
    $release = $fixture['release'];

    $asset = database_path('seed-assets/result-sheets/generic_results.docx');
    Storage::disk('local')->put('result-sheet-templates/output-fixture.docx', (string) file_get_contents($asset));
    ResultSheetTemplate::query()->update(['is_active' => false]);
    ResultSheetTemplate::query()->create([
        'name' => 'DOCX output template',
        'mode' => 'docx',
        'paper_size' => 'a4',
        'orientation' => 'portrait',
        'logical_unit' => 'full',
        'document_path' => 'result-sheet-templates/output-fixture.docx',
        'document_extension' => 'docx',
        'is_active' => true,
    ]);

    app()->instance(DocxToPdfConverter::class, new class implements DocxToPdfConverter
    {
        public function isAvailable(): bool
        {
            return true;
        }

        public function convert(string $documentPath, ?float $timeoutSeconds = null): string
        {
            return '%PDF-1.4 output-fake';
        }
    });

    $document = app(PrepareResultDocument::class)->handle($fixture['attempt']->fresh(), $guidance);
    $job = app(RequestResultSheetPrint::class)->handle($guidance, [$document->id], 1);
    (new RenderPrintJob($job->id))->handle(
        app(PrintRenderer::class),
        app(SensitiveAssetStore::class),
        app(RecordAuditEvent::class),
    );

    $job->refresh();
    $item = $job->items()->firstOrFail();
    expect($item->docx_path)->not->toBeNull()
        ->and($job->output_manifest)->toHaveKeys(['pdf', 'docx', 'zip']);
});

test('fill and convert job claims item with fencing token and increments parent progress', function (): void {
    Queue::fake();
    $fixture = releasedScoredAttempt($this);
    $guidance = $fixture['guidance'];
    $release = $fixture['release'];

    $job = app(RequestResultSheetPrint::class)->handle($guidance, [$release->result_document_id], 1);
    $item = $job->items()->firstOrFail();

    expect($item->claim_token)->toBeNull()
        ->and($item->status)->toBe(PrintJobItem::STATUS_QUEUED)
        ->and($job->progress_done)->toBe(0);

    $handler = new FillAndConvertJob($item->id);
    $handler->handle(
        app(PreparedResultDocumentRenderer::class),
        app(SensitiveAssetStore::class),
    );
    $item->refresh();
    $job->refresh();

    expect($item->status)->toBe(PrintJobItem::STATUS_READY)
        ->and($item->attempt_count)->toBe(1)
        ->and($job->progress_done)->toBe(1);
});

test('stale worker lease past expiration can be reclaimed by retry', function (): void {
    Queue::fake();
    $fixture = releasedScoredAttempt($this);
    $guidance = $fixture['guidance'];
    $release = $fixture['release'];

    $job = app(RequestResultSheetPrint::class)->handle($guidance, [$release->result_document_id], 1);
    $item = $job->items()->firstOrFail();

    // Simulate a crashed worker that left a claim expired 10 minutes ago
    $item->update([
        'status' => PrintJobItem::STATUS_FILLING,
        'claim_token' => (string) Str::uuid(),
        'claimed_at' => now()->subMinutes(15),
        'claim_expires_at' => now()->subMinutes(10),
        'attempt_count' => 1,
    ]);

    $handler = new FillAndConvertJob($item->id);
    $handler->handle(
        app(PreparedResultDocumentRenderer::class),
        app(SensitiveAssetStore::class),
    );
    $item->refresh();
    $job->refresh();

    expect($item->status)->toBe(PrintJobItem::STATUS_READY)
        ->and($item->attempt_count)->toBe(2)
        ->and($job->progress_done)->toBe(1);
});
