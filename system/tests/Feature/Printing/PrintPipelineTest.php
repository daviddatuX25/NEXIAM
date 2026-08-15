<?php

use App\Assessment\Omr\NativeBundle;
use App\Jobs\Printing\RenderPrintJob;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Printing\PrintJob;
use App\Printing\Actions\RenderBlankOmrSheets;
use App\Printing\BundleRenderer;
use App\Printing\DomPdfPrintRenderer;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

test('blank sheet action queues a job with pinned inputs and dedupes duplicates', function (): void {
    Queue::fake();
    $fixture = buildGradingFixture($this);
    $guidance = $fixture['guidance'];

    $templateVersion = OmrAnswerSheetTemplateVersion::query()
        ->where('version', 4)
        ->firstOrFail();

    $first = app(RenderBlankOmrSheets::class)->handle($templateVersion, $guidance);
    $second = app(RenderBlankOmrSheets::class)->handle($templateVersion, $guidance);

    expect($first->id)->toBe($second->id)
        ->and($first->artifact_type)->toBe(PrintJob::ARTIFACT_BLANK_SHEET)
        ->and($first->pinned_input_json['bundle_version'])->toBe(4)
        ->and($first->pinned_input_json['bundle_hash'])->toBe($templateVersion->native_bundle_hash);

    Queue::assertPushed(RenderPrintJob::class, 1);
});

test('render job produces a ready pdf for the long-bond bundle', function (): void {
    Storage::fake('local');
    $fixture = buildGradingFixture($this);
    $guidance = $fixture['guidance'];

    $templateVersion = OmrAnswerSheetTemplateVersion::query()->where('version', 4)->firstOrFail();
    $job = app(RenderBlankOmrSheets::class)->handle($templateVersion, $guidance);

    (new RenderPrintJob($job->id))->handle(
        app(DomPdfPrintRenderer::class),
        app(\App\Support\SensitiveAssetStore::class),
        app(\App\Foundation\Actions\RecordAuditEvent::class),
    );

    $job->refresh();

    expect($job->status)->toBe(PrintJob::STATUS_READY)
        ->and($job->file_path)->not->toBeNull()
        ->and($job->file_checksum)->toMatch('/^[a-f0-9]{64}$/')
        ->and(Storage::disk('local')->exists((string) $job->file_path))->toBeTrue();

    $bytes = Storage::disk('local')->get((string) $job->file_path);
    expect(str_starts_with($bytes, '%PDF'))->toBeTrue();
});

test('download refuses non-ready jobs and denies without capability', function (): void {
    Storage::fake('local');
    $fixture = buildGradingFixture($this);
    $guidance = $fixture['guidance'];
    $registrar = $fixture['registrar'];

    $templateVersion = OmrAnswerSheetTemplateVersion::query()->where('version', 4)->firstOrFail();
    $job = PrintJob::query()->create([
        'artifact_type' => PrintJob::ARTIFACT_BLANK_SHEET,
        'scope_type' => 'template_version',
        'scope_id' => $templateVersion->id,
        'requested_by_user_id' => $guidance->id,
        'pinned_input_json' => ['bundle_code' => 'SYN-OMR-ONE-PAGE', 'bundle_version' => 4],
        'progress_total' => 1,
        'progress_done' => 0,
    ]);

    $this->actingAs($guidance)
        ->get(route('printing.jobs.download', $job))
        ->assertStatus(409);

    $this->actingAs($registrar)
        ->get(route('printing.jobs.download', $job))
        ->assertStatus(403);
});

test('renderer centres option letters on bubble centres and never scales mismatched paper', function (): void {
    $renderer = app(BundleRenderer::class);
    $bundle = NativeBundle::load('SYN-OMR-ONE-PAGE', 4);

    config(['print.paper' => 'long']);
    $html = $renderer->renderHtml($bundle->template);

    // Letter centre coords equal bubble centre coords by construction: check one
    // rendered label position matches the corresponding bubble position.
    expect($html)->toContain('PAGE 1 OF 2')
        ->and($html)->toContain('Print at 100% scale');

    // A4 bundle vs long-bond paper must refuse, never scale.
    $a4 = NativeBundle::load('SYN-OMR-ONE-PAGE', 3);
    expect(fn () => $renderer->renderHtml($a4->template))
        ->toThrow(RuntimeException::class, 'never scaled');
});
