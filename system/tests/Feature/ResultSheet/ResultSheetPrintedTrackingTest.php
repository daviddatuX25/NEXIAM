<?php

use App\Models\Release\ResultDocument;
use App\Models\ResultSheet\ResultSheetTemplate;
use App\Models\User;
use App\Release\Actions\PrepareResultDocument;
use App\ResultSheet\Actions\MarkResultSheetsPrinted;

test('mark printed sets the timestamp and audits each record', function (): void {
    $fixture = releasedScoredAttempts($this, 2);
    $guidance = $fixture['guidance'];
    $releaseIds = array_column($fixture['releases'], 'id');

    expect($fixture['releases'][0]->result_printed_at)->toBeNull();

    $count = app(MarkResultSheetsPrinted::class)->handle($guidance, $releaseIds, true);

    expect($count)->toBe(2)
        ->and($fixture['releases'][0]->fresh()->result_printed_at)->not->toBeNull()
        ->and($fixture['releases'][1]->fresh()->result_printed_at)->not->toBeNull();

    foreach ($releaseIds as $releaseId) {
        $event = App\Models\Foundation\AuditEvent::query()
            ->where('event_type', 'release.result_printed_toggled')
            ->whereJsonContains('payload', ['release_id' => $releaseId])
            ->first();

        expect($event)->not->toBeNull()
            ->and($event->payload['printed'])->toBeTrue();
    }
});

test('mark printed clears the timestamp', function (): void {
    $fixture = releasedScoredAttempts($this, 1);
    $guidance = $fixture['guidance'];
    $release = $fixture['releases'][0];

    $release->update(['result_printed_at' => now()]);
    app(MarkResultSheetsPrinted::class)->handle($guidance, [$release->id], false);

    expect($release->fresh()->result_printed_at)->toBeNull();
});

test('staff without the release capability cannot toggle printed status', function (): void {
    $fixture = releasedScoredAttempts($this, 1);
    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    $release = $fixture['releases'][0];

    expect(fn () => app(MarkResultSheetsPrinted::class)->handle($registrar, [$release->id], true))
        ->toThrow(RuntimeException::class);
});

test('prepare produces a template-backed ready artifact with checksum', function (): void {
    $fixture = finalizedScoredFixture($this);
    $guidance = $fixture['guidance'];
    $attempt = $fixture['attempts'][0];

    resultSheetTemplateSeed();

    $document = app(PrepareResultDocument::class)->handle($attempt, $guidance);

    expect($document->status)->toBe(ResultDocument::STATUS_READY)
        ->and($document->checksum)->toHaveLength(64)
        ->and($document->template_version)->toBe('template:'.resultSheetTemplateSeed()->id)
        ->and(Storage::disk('local')->exists($document->storage_path))->toBeTrue();

    $html = (string) Storage::disk('local')->get($document->storage_path);
    expect($html)->toContain('Grading 0, Test')
        ->and($html)->toContain($attempt->reference_code);
});

test('prepare without an active template fails without a fake-ready artifact', function (): void {
    $fixture = finalizedScoredFixture($this);
    $guidance = $fixture['guidance'];
    $attempt = $fixture['attempts'][0];

    ResultSheetTemplate::query()->update(['is_active' => false]);

    expect(fn () => app(PrepareResultDocument::class)->handle($attempt, $guidance))
        ->toThrow(RuntimeException::class, 'template');

    expect(ResultDocument::query()->where('admission_attempt_id', $attempt->id)->count())->toBe(0);
});

test('prepare with a docx template produces a filled text artifact', function (): void {
    $fixture = finalizedScoredFixture($this);
    $guidance = $fixture['guidance'];
    $attempt = $fixture['attempts'][0];

    $asset = database_path('seed-assets/result-sheets/generic_results.docx');
    Storage::disk('local')->put('result-sheet-templates/fixture.docx', (string) file_get_contents($asset));

    ResultSheetTemplate::query()->update(['is_active' => false]);
    ResultSheetTemplate::query()->create([
        'name' => 'Word Active',
        'mode' => 'docx',
        'paper_size' => 'a4',
        'orientation' => 'portrait',
        'logical_unit' => 'full',
        'document_path' => 'result-sheet-templates/fixture.docx',
        'document_extension' => 'docx',
        'is_active' => true,
    ]);

    $document = app(PrepareResultDocument::class)->handle($attempt, $guidance);

    expect($document->status)->toBe(ResultDocument::STATUS_READY)
        ->and(Storage::disk('local')->exists($document->storage_path))->toBeTrue();

    $html = (string) Storage::disk('local')->get($document->storage_path);
    expect($html)->toContain($attempt->reference_code);
});
