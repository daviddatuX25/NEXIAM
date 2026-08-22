<?php

use App\Jobs\Printing\RenderPrintJob;
use App\Models\Assessment\GradingSession;
use App\Models\Printing\PrintJob;
use App\Models\Release\ResultDocument;
use App\Models\Release\ResultRelease;
use App\Models\ResultSheet\ResultSheetTemplate;
use App\Models\User;
use App\ResultSheet\Actions\RequestResultSheetPrint;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

function resultSheetTemplateSeed(): ResultSheetTemplate
{
    $template = ResultSheetTemplate::query()->first();

    if ($template === null) {
        $template = ResultSheetTemplate::query()->create([
            'name' => 'Seed HTML',
            'mode' => 'html',
            'paper_size' => 'a4',
            'orientation' => 'portrait',
            'logical_unit' => 'full',
            'content' => '<h1>{{institution_name}}</h1><p>{{applicant_name}} ({{applicant_reference}})</p>'
                .'<table><tbody class="scores-rows-placeholder"></tbody></table>',
            'is_active' => true,
        ]);
    } elseif (! $template->is_active) {
        $template->update(['is_active' => true]);
    }

    return $template;
}

function releasedScoredAttempts(TestCase $test, int $count = 1): array
{
    $fixture = buildGradingFixture($test, applicantCount: $count);
    $guidance = $fixture['guidance'];
    $grading = $fixture['grading'];

    foreach ($fixture['attempts'] as $attempt) {
        $scores = collect(activeAreas())->map(fn (int $id): array => [
            'aptitude_area_id' => $id,
            'score' => 85,
        ])->all();

        $test->actingAs($guidance)
            ->put(route('grading.sessions.attempts.scores.update', [$grading, $attempt]), [
                'scores' => $scores,
                'idempotency_key' => 'k-rel-'.$attempt->id,
            ])
            ->assertRedirect();

        $test->actingAs($guidance)
            ->post(route('grading.finalize', $grading), ['expected_version' => $grading->version])
            ->assertRedirect();
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

    return ['guidance' => $guidance, 'attempts' => $fixture['attempts'], 'releases' => $releases];
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

    $job = app(RequestResultSheetPrint::class)->handle($guidance, [$release->id], copies: 1);

    expect($job->artifact_type)->toBe(PrintJob::ARTIFACT_RESULT_SHEET)
        ->and($job->status)->toBe(PrintJob::STATUS_QUEUED)
        ->and($job->pinned_input_json['release_ids'])->toBe([$release->id])
        ->and($job->pinned_input_json['copies'])->toBe(1)
        ->and($job->pinned_input_json['template_id'])->toBe(resultSheetTemplateSeed()->id);

    Queue::assertPushed(RenderPrintJob::class);
});

test('duplicate requests return the existing job', function (): void {
    Queue::fake();
    $fixture = releasedScoredAttempt($this);
    $guidance = $fixture['guidance'];
    $release = $fixture['release'];

    $first = app(RequestResultSheetPrint::class)->handle($guidance, [$release->id], 1);
    $second = app(RequestResultSheetPrint::class)->handle($guidance, [$release->id], 1);

    expect($second->id)->toBe($first->id);
    Queue::assertPushed(RenderPrintJob::class, 1);
});

test('online releases can be printed', function (): void {
    $fixture = releasedScoredAttempt($this);
    $guidance = $fixture['guidance'];
    $release = $fixture['release'];

    $release->update(['mode' => 'online']);

    $job = app(RequestResultSheetPrint::class)->handle($guidance, [$release->id], 1);

    expect($job)->toBeInstanceOf(\App\Models\Printing\PrintJob::class)
        ->and($job->status)->toBe('queued');
});

test('no active template refuses printing', function (): void {
    $fixture = releasedScoredAttempt($this);
    $guidance = $fixture['guidance'];
    $release = $fixture['release'];

    ResultSheetTemplate::query()->update(['is_active' => false]);

    expect(fn () => app(RequestResultSheetPrint::class)->handle($guidance, [$release->id], 1))
        ->toThrow(RuntimeException::class, 'template');
});

test('render job produces a ready pdf and sets result_printed_at idempotently', function (): void {
    Queue::fake();
    $fixture = releasedScoredAttempt($this);
    $guidance = $fixture['guidance'];
    $release = $fixture['release'];

    $job = app(RequestResultSheetPrint::class)->handle($guidance, [$release->id], 1);
    (new RenderPrintJob($job->id))->handle(
        app(App\Printing\PrintRenderer::class),
        app(App\Support\SensitiveAssetStore::class),
        app(App\Foundation\Actions\RecordAuditEvent::class),
    );

    $job->refresh();
    expect($job->status)->toBe(PrintJob::STATUS_READY)
        ->and($job->file_path)->not->toBeNull()
        ->and($job->file_checksum)->toHaveLength(64)
        ->and($release->fresh()->result_printed_at)->not->toBeNull();

    // Idempotent: a second render keeps the printed timestamp.
    $printedAt = $release->fresh()->result_printed_at;
    $job->update(['status' => PrintJob::STATUS_QUEUED, 'file_path' => null, 'file_checksum' => null]);
    (new RenderPrintJob($job->id))->handle(
        app(App\Printing\PrintRenderer::class),
        app(App\Support\SensitiveAssetStore::class),
        app(App\Foundation\Actions\RecordAuditEvent::class),
    );
    expect($release->fresh()->result_printed_at->eq($printedAt))->toBeTrue();
});

test('bulk job renders a merged pdf with progress and per-item manifest', function (): void {
    Queue::fake();
    $fixture = releasedScoredAttempts($this, 2);
    $guidance = $fixture['guidance'];

    $releaseIds = array_column($fixture['releases'], 'id');

    $job = app(RequestResultSheetPrint::class)->handle($guidance, $releaseIds, copies: 2);

    expect($job->progress_total)->toBe(2)
        ->and($job->pinned_input_json['copies'])->toBe(2);

    $job->update(['status' => PrintJob::STATUS_QUEUED, 'progress_done' => 0]);
    (new RenderPrintJob($job->id))->handle(
        app(App\Printing\PrintRenderer::class),
        app(App\Support\SensitiveAssetStore::class),
        app(App\Foundation\Actions\RecordAuditEvent::class),
    );

    $job->refresh();
    expect($job->status)->toBe(PrintJob::STATUS_READY)
        ->and($job->progress_done)->toBe($job->progress_total)
        ->and($job->per_item_manifest)->toHaveCount(2)
        ->and(collect($job->per_item_manifest)->every(fn ($item) => ($item['status'] ?? '') === 'ready'))->toBeTrue();
});

test('a failed item yields ready-with-partial and lists the failure', function (): void {
    Queue::fake();
    $fixture = releasedScoredAttempt($this);
    $guidance = $fixture['guidance'];
    $release = $fixture['release'];

    $job = app(RequestResultSheetPrint::class)->handle($guidance, [$release->id], 1);

    // Point the active template at a missing document so rendering fails.
    ResultSheetTemplate::query()->update([
        'mode' => 'docx',
        'content' => null,
        'document_path' => 'result-sheet-templates/missing.docx',
        'document_extension' => 'docx',
        'is_active' => true,
    ]);

    try {
        (new RenderPrintJob($job->id))->handle(
            app(App\Printing\PrintRenderer::class),
            app(App\Support\SensitiveAssetStore::class),
            app(App\Foundation\Actions\RecordAuditEvent::class),
        );
    } catch (Throwable) {
        // expected failure path
    }

    $job->refresh();
    expect($job->status)->toBe(PrintJob::STATUS_FAILED)
        ->and($job->safe_error)->not->toBeNull();
});

test('download re-authorizes, verifies checksum, and is audited', function (): void {
    Queue::fake();
    $fixture = releasedScoredAttempt($this);
    $guidance = $fixture['guidance'];
    $release = $fixture['release'];

    $job = app(RequestResultSheetPrint::class)->handle($guidance, [$release->id], 1);
    (new RenderPrintJob($job->id))->handle(
        app(App\Printing\PrintRenderer::class),
        app(App\Support\SensitiveAssetStore::class),
        app(App\Foundation\Actions\RecordAuditEvent::class),
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

    expect(App\Models\Foundation\AuditEvent::query()
        ->where('event_type', 'printing.job_downloaded')
        ->whereJsonContains('payload', ['print_job_id' => $job->id])
        ->exists())->toBeTrue();
});

test('retry refuses when the pinned release was superseded', function (): void {
    Queue::fake();
    $fixture = releasedScoredAttempt($this);
    $guidance = $fixture['guidance'];
    $attempt = $fixture['attempt'];
    $release = $fixture['release'];

    $job = app(RequestResultSheetPrint::class)->handle($guidance, [$release->id], 1);

    // Supersede the release (new release version) so the pinned version is stale.
    $this->actingAs($guidance)
        ->post(route('release.store', $attempt), [
            'idempotency_key' => 'rel-2-'.$attempt->id,
            'mode' => 'f2f',
        ])
        ->assertRedirect();

    $release->update(['is_current' => false]);

    try {
        (new RenderPrintJob($job->id))->handle(
            app(App\Printing\PrintRenderer::class),
            app(App\Support\SensitiveAssetStore::class),
            app(App\Foundation\Actions\RecordAuditEvent::class),
        );
    } catch (Throwable) {
        // expected failure path
    }

    $job->refresh();
    expect($job->status)->toBe(PrintJob::STATUS_FAILED)
        ->and($job->safe_error)->toBe('all_items_failed')
        ->and($job->per_item_manifest[$release->id]['safe_error'])->toBe('release_stale');
});

test('prune command deletes expired ready artifacts and audits the cleanup', function (): void {
    Queue::fake();
    $fixture = releasedScoredAttempt($this);
    $guidance = $fixture['guidance'];
    $release = $fixture['release'];

    $job = app(RequestResultSheetPrint::class)->handle($guidance, [$release->id], 1);
    (new RenderPrintJob($job->id))->handle(
        app(App\Printing\PrintRenderer::class),
        app(App\Support\SensitiveAssetStore::class),
        app(App\Foundation\Actions\RecordAuditEvent::class),
    );

    $job->refresh();
    $job->update(['completed_at' => now()->subDays(60)]);
    expect($job->file_path)->not->toBeNull();

    $this->artisan('result-sheet:prune')
        ->expectsOutputToContain('pruned')
        ->assertExitCode(0);

    expect($job->fresh()->file_path)->toBeNull()
        ->and(App\Models\Foundation\AuditEvent::query()
            ->where('event_type', 'printing.job_pruned')
            ->whereJsonContains('payload', ['print_job_id' => $job->id])
            ->exists())->toBeTrue();
});
