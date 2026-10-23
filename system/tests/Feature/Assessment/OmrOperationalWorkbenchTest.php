<?php

use App\Assessment\Actions\Omr\CreateOmrCaptureBatch;
use App\Assessment\Actions\Omr\StartOmrPendingCaptureSet;
use App\Assessment\Actions\Omr\UploadOmrCaptureSheet;
use App\Assessment\Omr\OmrQrMode;
use App\Assessment\Omr\Operational\OmrExecutionKind;
use App\Assessment\Omr\Operational\OmrWorkbenchCommand;
use App\Assessment\Omr\Operational\OmrWorkbenchProjection;
use App\Assessment\Omr\Review\OmrReviewDecision;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrBlankInstancePageClaim;
use App\Models\Assessment\OmrCaptureBatch;
use App\Models\Assessment\OmrCaptureSheet;
use App\Models\Assessment\OmrDiagnosticCapture;
use App\Models\Assessment\OmrDiagnosticItemEvidence;
use App\Models\Assessment\OmrDiagnosticReviewRevision;
use App\Models\Assessment\OmrDiagnosticRun;
use App\Models\Assessment\OmrQrDisabledIssuance;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\Assessment\OmrTemplateBookletPair;
use App\Models\Assessment\ScoreRecord;
use App\Support\SensitiveAssetStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

require_once __DIR__.'/../../Support/OmrAssistedCaptureTestRequests.php';

/**
 * @return array{fixture: array<string, mixed>, pair: OmrTemplateBookletPair, template: OmrAnswerSheetTemplateVersion, booklet: OmrQuestionBookletVersion}
 */
function omrOperationalMaterials(TestCase $test, ?array $fixture = null): array
{
    $fixture ??= buildOmrGradingFixture($test);
    $pair = canonicalGradingBooklet($fixture['guidance']);

    return [
        'fixture' => $fixture,
        'pair' => $pair,
        'template' => OmrAnswerSheetTemplateVersion::query()->findOrFail($pair->template_version_id),
        'booklet' => OmrQuestionBookletVersion::query()->findOrFail($pair->booklet_version_id),
    ];
}

/**
 * @return array{fixture: array<string, mixed>, pair: OmrTemplateBookletPair, template: OmrAnswerSheetTemplateVersion, booklet: OmrQuestionBookletVersion, response: TestResponse, run: OmrDiagnosticRun}
 */
function startOmrOperationalDiagnostic(TestCase $test, ?array $materials = null): array
{
    $materials ??= omrOperationalMaterials($test);
    $fixture = $materials['fixture'];

    $response = $test->actingAs($fixture['guidance'])
        ->postJson(route('setup.exam-materials.omr-test.runs.start'), [
            'template_version_id' => $materials['template']->id,
            'booklet_version_id' => $materials['booklet']->id,
            'declared_set_code' => 'A',
        ]);

    $response->assertOk();

    return [
        ...$materials,
        'response' => $response,
        'run' => OmrDiagnosticRun::query()->latest('id')->firstOrFail(),
    ];
}

test('diagnostic start creates a server-owned run and projects the shared workbench contract', function (): void {
    $diagnostic = startOmrOperationalDiagnostic($this);
    $run = $diagnostic['run'];
    $projection = $diagnostic['response']->json();

    expect(array_keys($projection))->toEqual([
        'workbench_id',
        'execution_kind',
        'revision',
        'phase',
        'context',
        'sheets',
        'evidence',
        'recovery',
        'completion',
        'commands',
    ])
        ->and($run->execution_kind)->toBe(OmrDiagnosticRun::EXECUTION_KIND_DIAGNOSTIC)
        ->and($run->created_by_user_id)->toBe($diagnostic['fixture']['guidance']->id)
        ->and($run->template_version_id)->toBe($diagnostic['template']->id)
        ->and($run->booklet_version_id)->toBe($diagnostic['booklet']->id)
        ->and($run->declared_set_code)->toBe('A')
        ->and($projection['workbench_id'])->toBe('diagnostic:'.$run->public_id)
        ->and($projection['execution_kind'])->toBe('diagnostic')
        ->and($projection['revision'])->toBe(1)
        ->and($projection['phase'])->toBe(OmrWorkbenchProjection::PHASE_CAPTURE)
        ->and($projection['context']['run_id'])->toBe($run->id)
        ->and($projection['context']['institution_id'])->toBe($run->institution_id)
        ->and($projection['context']['academic_cycle_id'])->toBe($run->academic_cycle_id)
        ->and($projection['context']['template_version_id'])->toBe($run->template_version_id)
        ->and($projection['context']['booklet_version_id'])->toBe($run->booklet_version_id)
        ->and($projection['commands'])->toEqual([OmrWorkbenchCommand::CaptureStart->value]);

    $diagnostic['response']
        ->assertJsonMissingPath('context.session_id')
        ->assertJsonMissingPath('context.declared_set_code')
        ->assertJsonMissingPath('context.answer_key')
        ->assertJsonMissingPath('context.expected_vector')
        ->assertJsonMissingPath('production_complete');
});
test('diagnostic workbench preserves only approved authenticated image metadata for review', function (): void {
    $projection = (new OmrWorkbenchProjection(
        workbenchId: 'diagnostic:review-assets',
        executionKind: OmrExecutionKind::Diagnostic,
        revision: 2,
        phase: OmrWorkbenchProjection::PHASE_REVIEW,
        sheets: [[
            'id' => 8,
            'assets' => [
                'input' => ['id' => 81, 'hash' => str_repeat('a', 64), 'path' => '/private/input.jpg'],
                'aligned' => ['id' => 82, 'hash' => str_repeat('b', 64), 'url' => 'https://untrusted.invalid/aligned.png'],
            ],
        ]],
        evidence: [
            'captures' => [[
                'capture_id' => 8,
                'evidence' => [
                    'assets' => [
                        'aligned' => [
                            'mime' => 'image/png',
                            'width' => 2480,
                            'height' => 3508,
                            'sha256' => str_repeat('c', 64),
                            'path' => '/private/aligned.png',
                        ],
                    ],
                ],
            ]],
        ],
    ))->toSafeArray();

    expect($projection['sheets'][0]['assets'])->toEqual([
        'input' => ['id' => 81, 'hash' => str_repeat('a', 64)],
        'aligned' => ['id' => 82, 'hash' => str_repeat('b', 64)],
    ])
        ->and($projection['evidence']['captures'][0]['evidence']['assets']['aligned'])->toEqual([
            'mime' => 'image/png',
            'width' => 2480,
            'height' => 3508,
            'sha256' => str_repeat('c', 64),
        ]);
});

test('a missing exact diagnostic operation remains unknown rather than authorizing a new photo', function (): void {
    $diagnostic = startOmrOperationalDiagnostic($this);
    $operationId = (string) Str::uuid();
    $response = $this->getJson(route('setup.exam-materials.omr-test.runs.show', [
        'run' => $diagnostic['run']->public_id,
        'capture_operation_id' => $operationId,
        'capture_idempotency_key' => 'delayed-'.$operationId,
    ]))->assertOk();

    $response->assertJsonPath('context.capture_receipt.operation_id', $operationId)
        ->assertJsonPath('context.capture_receipt.status', 'pending');
    expect(OmrDiagnosticCapture::query()->where('diagnostic_run_id', $diagnostic['run']->id)->exists())->toBeFalse();
});

test('diagnostic failed capture projects stored stage and recovery without leaking worker details', function (): void {
    $diagnostic = startOmrOperationalDiagnostic($this);
    $run = $diagnostic['run'];
    $actor = $diagnostic['fixture']['guidance'];
    $secretDetail = 'internal provider trace / private path';
    $clientCaptureId = (string) Str::uuid();
    $bytes = physicalChallengePng();
    $sha256 = hash('sha256', $bytes);
    $path = 'omr/diagnostic/'.$run->public_id.'/'.$clientCaptureId.'_input.bin';
    app(SensitiveAssetStore::class)->store($bytes, $path);
    $inputAsset = OmrTemplateAsset::query()->create([
        'template_version_id' => $run->template_version_id,
        'kind' => OmrTemplateAsset::KIND_EVIDENCE,
        'page_no' => 1,
        'disk' => 'sensitive',
        'path' => $path,
        'mime' => 'image/png',
        'bytes' => strlen($bytes),
        'sha256' => $sha256,
        'metadata' => ['role' => 'capture_input', 'diagnostic_run_id' => $run->id],
    ]);

    $capture = OmrDiagnosticCapture::query()->create([
        'diagnostic_run_id' => $run->id,
        'client_capture_id' => $clientCaptureId,
        'status' => OmrDiagnosticCapture::STATUS_FAILED,
        'version' => 1,
        'input_asset_id' => $inputAsset->id,
        'input_asset_hash' => $sha256,
        'evidence_json' => ['issues' => [['code' => 'analysis_failed', 'detail' => $secretDetail]]],
        'failure_code' => 'analysis_failed',
        'failure_detail' => $secretDetail,
        'created_by_user_id' => $actor->id,
        'captured_at' => now(),
        'completed_at' => now(),
    ]);

    $response = $this->actingAs($actor)
        ->getJson(route('setup.exam-materials.omr-test.runs.show', $run->public_id))
        ->assertOk();

    expect($response->json('sheets.0.failure'))->toMatchArray([
        'code' => 'analysis_failed',
        'stage' => OmrWorkbenchProjection::PHASE_PROCESSING,
        'message' => 'The capture was received, but analysis did not complete.',
    ])
        ->and($response->json('recovery.items.0.recovery'))->toContain('Retry this capture')
        ->and(json_encode($response->json()))->not->toContain($secretDetail);

    $capture->forceFill(['failure_code' => 'document_quad_not_found'])->saveOrFail();
    $registration = $this->getJson(route('setup.exam-materials.omr-test.runs.show', $run->public_id))->assertOk();
    expect($registration->json('sheets.0.failure.code'))->toBe('document_quad_not_found')
        ->and($registration->json('sheets.0.failure.stage'))->toBe('registration')
        ->and($registration->json('sheets.0.failure.recovery'))->toContain('four sheet corners')
        ->and(json_encode($registration->json()))->not->toContain($secretDetail);

    $capture->forceFill(['failure_code' => 'document_content_missing'])->saveOrFail();
    $content = $this->getJson(route('setup.exam-materials.omr-test.runs.show', $run->public_id))->assertOk();
    expect($content->json('sheets.0.failure.code'))->toBe('document_content_missing')
        ->and($content->json('sheets.0.failure.stage'))->toBe('registration')
        ->and(json_encode($content->json()))->not->toContain($secretDetail);
});

test('diagnostic routes reject stale revisions and client-owned context fields before persistence', function (): void {
    $materials = omrOperationalMaterials($this);
    $fixture = $materials['fixture'];
    $runCount = OmrDiagnosticRun::query()->count();

    $invalidStart = $this->actingAs($fixture['guidance'])
        ->postJson(route('setup.exam-materials.omr-test.runs.start'), [
            'template_version_id' => $materials['template']->id,
            'booklet_version_id' => $materials['booklet']->id,
            'declared_set_code' => 'A',
            'institution_id' => 999,
            'academic_cycle_id' => 999,
            'execution_kind' => OmrExecutionKind::Production->value,
            'kind' => 'diagnostic',
            'mode' => 'diagnostic',
            'run_id' => 999,
            'workbench_id' => 'diagnostic:spoofed',
            'session_id' => 999,
            'answer_key' => ['A'],
            'expected_vector' => ['A'],
        ]);

    $invalidStart
        ->assertStatus(422)
        ->assertJsonValidationErrors([
            'institution_id',
            'academic_cycle_id',
            'execution_kind',
            'kind',
            'mode',
            'run_id',
            'workbench_id',
            'session_id',
            'answer_key',
            'expected_vector',
        ]);

    expect(OmrDiagnosticRun::query()->count())->toBe($runCount);

    $diagnostic = startOmrOperationalDiagnostic($this, $materials);
    $run = $diagnostic['run'];
    $set = app(StartOmrPendingCaptureSet::class)->forDiagnostic(
        $run,
        $fixture['guidance'],
        (string) Str::uuid(),
    );
    $run->forceFill(['revision' => 2])->saveOrFail();
    $showUrl = route('setup.exam-materials.omr-test.runs.show', $run->public_id);
    $this->actingAs($fixture['guidance']);
    $stalePayload = omrAssistedCapturePayload($this, $showUrl, [
        'image' => UploadedFile::fake()->createWithContent('stale-capture.png', physicalChallengePng(), 'image/png'),
        'expected_revision' => 1,
    ]);
    $stale = $this->post(route('setup.exam-materials.omr-test.runs.capture', $run->public_id), $stalePayload, ['Accept' => 'application/json']);
    expect($stale->status())->toBe(409);

    expect($run->fresh()->revision)->toBe(2)
        ->and($run->fresh()->status)->toBe(OmrDiagnosticRun::STATUS_OPEN)
        ->and($run->fresh()->phase)->toBe(OmrDiagnosticRun::PHASE_CAPTURE);

    $prohibitedTransition = $this->actingAs($fixture['guidance'])
        ->postJson(route('setup.exam-materials.omr-test.runs.compare', $run->public_id), [
            'expected_revision' => 2,
            'idempotency_key' => 'prohibited-transition-'.$run->id,
            'execution_kind' => OmrExecutionKind::Diagnostic->value,
            'mode' => OmrExecutionKind::Production->value,
            'context' => ['session_id' => 999],
            'expected_vector' => ['A'],
        ]);

    $prohibitedTransition
        ->assertStatus(422)
        ->assertJsonValidationErrors([
            'execution_kind',
            'mode',
            'context',
            'expected_vector',
        ]);

    expect($run->fresh()->revision)->toBe(2)
        ->and(OmrDiagnosticCapture::query()->count())->toBe(0)
        ->and(OmrDiagnosticItemEvidence::query()->count())->toBe(0)
        ->and(OmrDiagnosticReviewRevision::query()->count())->toBe(0);
});

test('starting a diagnostic run writes only diagnostic state and leaves production aggregates unchanged', function (): void {
    $materials = omrOperationalMaterials($this);
    $fixture = $materials['fixture'];
    $productionBatch = app(CreateOmrCaptureBatch::class)->handle(
        $fixture['grading'],
        $fixture['guidance'],
        'operational-diagnostic-production-'.uniqid('', true),
    );

    $productionBefore = [
        'batch' => $productionBatch->fresh()->getRawOriginal(),
        'batches' => OmrCaptureBatch::query()->count(),
        'sheets' => OmrCaptureSheet::query()->count(),
        'scores' => ScoreRecord::query()->count(),
        'claims' => OmrBlankInstancePageClaim::query()->count(),
    ];
    $diagnosticBefore = [
        'runs' => OmrDiagnosticRun::query()->count(),
        'captures' => OmrDiagnosticCapture::query()->count(),
        'evidence' => OmrDiagnosticItemEvidence::query()->count(),
        'reviews' => OmrDiagnosticReviewRevision::query()->count(),
    ];
    $templateBefore = $materials['template']->fresh()->getRawOriginal();
    $bookletBefore = $materials['booklet']->fresh()->getRawOriginal();

    $response = $this->actingAs($fixture['guidance'])
        ->postJson(route('setup.exam-materials.omr-test.runs.start'), [
            'template_version_id' => $materials['template']->id,
            'booklet_version_id' => $materials['booklet']->id,
            'declared_set_code' => 'A',
        ])
        ->assertOk();

    expect(OmrDiagnosticRun::query()->count())->toBe($diagnosticBefore['runs'] + 1)
        ->and(OmrDiagnosticCapture::query()->count())->toBe($diagnosticBefore['captures'])
        ->and(OmrDiagnosticItemEvidence::query()->count())->toBe($diagnosticBefore['evidence'])
        ->and(OmrDiagnosticReviewRevision::query()->count())->toBe($diagnosticBefore['reviews'])
        ->and(OmrCaptureBatch::query()->count())->toBe($productionBefore['batches'])
        ->and(OmrCaptureSheet::query()->count())->toBe($productionBefore['sheets'])
        ->and(ScoreRecord::query()->count())->toBe($productionBefore['scores'])
        ->and(OmrBlankInstancePageClaim::query()->count())->toBe($productionBefore['claims'])
        ->and($productionBatch->fresh()->getRawOriginal())->toEqual($productionBefore['batch'])
        ->and($materials['template']->fresh()->getRawOriginal())->toEqual($templateBefore)
        ->and($materials['booklet']->fresh()->getRawOriginal())->toEqual($bookletBefore)
        ->and($response->json('execution_kind'))->toBe(OmrExecutionKind::Diagnostic->value);
});

test('a missing exact production operation remains unknown rather than authorizing a new photo', function (): void {
    $materials = omrOperationalMaterials($this);
    $fixture = $materials['fixture'];
    $batch = app(CreateOmrCaptureBatch::class)->handle(
        $fixture['grading'], $fixture['guidance'], 'missing-operation-'.Str::uuid(),
    );
    $operationId = (string) Str::uuid();
    $this->actingAs($fixture['guidance'])
        ->getJson(route('scoring.sessions.omr.batches.workbench', [
            'examSession' => $fixture['session'],
            'batch' => $batch,
            'capture_operation_id' => $operationId,
            'capture_idempotency_key' => 'delayed-'.$operationId,
        ]))->assertOk()
        ->assertJsonPath('context.capture_receipt.operation_id', $operationId)
        ->assertJsonPath('context.capture_receipt.status', 'pending');
    expect(OmrCaptureSheet::query()->where('batch_id', $batch->id)->exists())->toBeFalse();
});

test('production workbench is owned by the nested scoring exam-session route', function (): void {
    $first = omrOperationalMaterials($this);
    $firstBatch = app(CreateOmrCaptureBatch::class)->handle(
        $first['fixture']['grading'],
        $first['fixture']['guidance'],
        'operational-production-first-'.uniqid('', true),
    );

    $valid = $this->actingAs($first['fixture']['guidance'])
        ->getJson(route('scoring.sessions.omr.batches.workbench', [
            'examSession' => $first['fixture']['session'],
            'batch' => $firstBatch,
        ]));

    $valid
        ->assertOk()
        ->assertJsonPath('execution_kind', OmrExecutionKind::Production->value)
        ->assertJsonPath('workbench_id', sprintf(
            'production:%d:%d',
            $first['fixture']['grading']->id,
            $firstBatch->id,
        ))
        ->assertJsonPath('context.session_id', $first['fixture']['grading']->id)
        ->assertJsonPath('context.run_id', $firstBatch->id)
        ->assertJsonMissingPath('context.declared_set_code')
        ->assertJsonMissingPath('commands.5');

    // Keep the first fixture's applicant/cycle intact; ownership only needs a foreign session and batch context, so clone those rows rather than submitting the same normalized identity twice.
    $foreignSession = $first['fixture']['session']->replicate();
    $foreignSession->forceFill(['code' => 'EX-OPERATIONAL-FOREIGN'])->saveOrFail();

    $foreignGrading = $first['fixture']['grading']->replicate();
    $foreignGrading->forceFill(['exam_session_id' => $foreignSession->id])->saveOrFail();

    $secondBatch = $firstBatch->replicate();
    $secondBatch->forceFill(['grading_session_id' => $foreignGrading->id])->saveOrFail();

    $this->actingAs($first['fixture']['guidance'])
        ->getJson(route('scoring.sessions.omr.batches.workbench', [
            'examSession' => $foreignSession,
            'batch' => $secondBatch,
        ]))
        ->assertNotFound();
});

test('production workbench command routes are explicit and revision-bound', function (): void {
    $materials = omrOperationalMaterials($this);
    $fixture = $materials['fixture'];
    $batch = app(CreateOmrCaptureBatch::class)->handle(
        $fixture['grading'],
        $fixture['guidance'],
        'operational-production-command-routes-'.uniqid('', true),
    );
    $parameters = [
        'examSession' => $fixture['session'],
        'batch' => $batch,
    ];

    $routes = [
        'scoring.sessions.omr.batches.workbench.capture_start' => [],
        'scoring.sessions.omr.batches.workbench.capture_retry' => ['sheet' => 1],
        'scoring.sessions.omr.batches.workbench.review_save' => ['sheet' => 1],
        'scoring.sessions.omr.batches.workbench.resolve_applicant' => ['sheet' => 1],
        'scoring.sessions.omr.batches.workbench.resolve_duplicate' => ['sheet' => 1],
        'scoring.sessions.omr.batches.workbench.production_complete' => [],
    ];

    $urls = [];
    foreach ($routes as $name => $extra) {
        $urls[] = route($name, [...$parameters, ...$extra]);
    }

    expect($urls)->toHaveCount(6);
    foreach ($urls as $url) {
        expect($url)->toContain('/scoring/sessions/'.$fixture['session']->id.'/omr/batches/'.$batch->id.'/workbench');
    }

    $this->actingAs($fixture['guidance'])
        ->postJson(route('scoring.sessions.omr.batches.workbench.production_complete', $parameters), [
            'workbench_id' => 'production:999:999',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['expected_revision', 'workbench_id']);
});

test('production capture rejects unsupported crop policy before authority dispatch', function (): void {
    $materials = omrOperationalMaterials($this);
    $fixture = $materials['fixture'];
    $materials['template']->forceFill(['qr_mode' => OmrQrMode::Signed])->saveOrFail();
    $batch = app(CreateOmrCaptureBatch::class)->handle(
        $fixture['grading'],
        $fixture['guidance'],
        'operational-production-invalid-crop-'.uniqid('', true),
    );
    $parameters = ['examSession' => $fixture['session'], 'batch' => $batch];
    $showUrl = route('scoring.sessions.omr.batches.workbench', $parameters);
    $this->actingAs($fixture['guidance']);
    $response = $this->postJson(route('scoring.sessions.omr.batches.workbench.capture_start', $parameters),
        omrAssistedCapturePayload($this, $showUrl, [
            'capture_mode' => 'auto',
            'image_bytes' => base64_encode('invalid image'),
            'mime' => 'image/png',
            'crop_policy' => 'skip_rectification',
        ]));
    $response->assertStatus(422)->assertJsonValidationErrors(['crop_policy']);
    expect(fn () => app(UploadOmrCaptureSheet::class)->handle(
        $batch,
        'invalid image',
        'image/png',
        OmrCaptureSheet::MODE_AUTO,
        (string) Str::uuid(),
        $fixture['guidance'],
        cropPolicy: 'skip_rectification',
    ))->toThrow(RuntimeException::class, 'Unsupported OMR crop policy.');
});
test('production capture rejects mixed raw and staged frame inputs', function (): void {
    $materials = omrOperationalMaterials($this);
    $fixture = $materials['fixture'];
    $materials['template']->forceFill(['qr_mode' => OmrQrMode::Signed])->saveOrFail();
    $batch = app(CreateOmrCaptureBatch::class)->handle(
        $fixture['grading'],
        $fixture['guidance'],
        'operational-production-capture-xor-'.uniqid('', true),
    );
    $parameters = ['examSession' => $fixture['session'], 'batch' => $batch];
    $showUrl = route('scoring.sessions.omr.batches.workbench', $parameters);
    $this->actingAs($fixture['guidance'])
        ->post(route('scoring.sessions.omr.batches.workbench.capture_start', $parameters),
            omrAssistedCapturePayload($this, $showUrl, [
                'capture_mode' => 'manual',
                'image' => UploadedFile::fake()->image('raw.png'),
                'staged_frame_id' => (string) Str::uuid(),
                'camera_command_id' => (string) Str::uuid(),
                'pairing_id' => (string) Str::uuid(),
            ]), ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['staged_frame_id']);

    expect(OmrCaptureSheet::query()->where('batch_id', $batch->id)->count())->toBe(0);
});
test('diagnostic capture rejects mixed raw and staged frame inputs', function (): void {
    $diagnostic = startOmrOperationalDiagnostic($this);
    $run = $diagnostic['run'];
    $fixture = $diagnostic['fixture'];
    $showUrl = route('setup.exam-materials.omr-test.runs.show', $run->public_id);

    $this->actingAs($fixture['guidance'])
        ->post(route('setup.exam-materials.omr-test.runs.capture', $run->public_id),
            omrAssistedCapturePayload($this, $showUrl, [
                'image' => UploadedFile::fake()->image('raw.png'),
                'staged_frame_id' => (string) Str::uuid(),
                'camera_command_id' => (string) Str::uuid(),
                'pairing_id' => (string) Str::uuid(),
            ]), ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['staged_frame_id']);

    expect(OmrDiagnosticCapture::query()->where('diagnostic_run_id', $run->id)->count())->toBe(0);
});

test('production and diagnostic projections share canonical top-level keys but keep completion commands distinct', function (): void {
    $diagnostic = startOmrOperationalDiagnostic($this);
    $fixture = $diagnostic['fixture'];
    $productionBatch = app(CreateOmrCaptureBatch::class)->handle(
        $fixture['grading'],
        $fixture['guidance'],
        'operational-parity-'.uniqid('', true),
    );

    $production = $this->actingAs($fixture['guidance'])
        ->getJson(route('scoring.sessions.omr.batches.workbench', [
            'examSession' => $fixture['session'],
            'batch' => $productionBatch,
        ]))
        ->assertOk();

    expect(array_keys($production->json()))->toEqual(array_keys($diagnostic['response']->json()))
        ->and($production->json('execution_kind'))->toBe(OmrExecutionKind::Production->value)
        ->and($diagnostic['response']->json('execution_kind'))->toBe(OmrExecutionKind::Diagnostic->value);

    $allCommands = OmrWorkbenchCommand::cases();
    $productionProjection = (new OmrWorkbenchProjection(
        workbenchId: 'production:101:202',
        executionKind: OmrExecutionKind::Production,
        revision: 1,
        phase: OmrWorkbenchProjection::PHASE_COMPLETION,
        commands: $allCommands,
    ))->toSafeArray();
    $diagnosticProjection = (new OmrWorkbenchProjection(
        workbenchId: 'diagnostic:parity-run',
        executionKind: OmrExecutionKind::Diagnostic,
        revision: 1,
        phase: OmrWorkbenchProjection::PHASE_COMPLETION,
        commands: $allCommands,
    ))->toSafeArray();

    expect($productionProjection['commands'])->toEqual([
        'capture_start',
        'capture_retry',
        'review_save',
        'resolve_applicant',
        'resolve_duplicate',
        'production_complete',
    ])
        ->and($diagnosticProjection['commands'])->toEqual([
            'capture_start',
            'capture_retry',
            'review_save',
            'resolve_applicant',
            'resolve_display_code',
            'resolve_duplicate',
            'quarantine_capture',
            'diagnostic_compare',
            'diagnostic_complete',
        ])
        ->and($productionProjection['commands'])->not->toContain('diagnostic_compare')
        ->and($productionProjection['commands'])->not->toContain('diagnostic_complete')
        ->and($diagnosticProjection['commands'])->not->toContain('production_complete')
        ->and($diagnosticProjection['commands'])->not->toContain('select_session');
});

test('projected diagnostic command passes through HTTP adapter boundary and unprojected or foreign command cannot persist', function (): void {
    $materials = omrOperationalMaterials($this);
    $fixture = $materials['fixture'];
    $guidance = $fixture['guidance'];
    $template = $materials['template'];
    $booklet = $materials['booklet'];

    $productionBatchesBefore = OmrCaptureBatch::query()->count();
    $productionSheetsBefore = OmrCaptureSheet::query()->count();
    $scoresBefore = ScoreRecord::query()->count();

    // Create a diagnostic run in needs_review phase with an item evidence row
    $run = OmrDiagnosticRun::query()->create([
        'public_id' => (string) Str::uuid(),
        'execution_kind' => OmrDiagnosticRun::EXECUTION_KIND_DIAGNOSTIC,
        'institution_id' => (int) $guidance->staffProfile->institution_id,
        'academic_cycle_id' => (int) $fixture['session']->academic_cycle_id,
        'template_version_id' => $template->id,
        'booklet_version_id' => $booklet->id,
        'declared_set_code' => 'A',
        'status' => OmrDiagnosticRun::STATUS_NEEDS_REVIEW,
        'phase' => OmrDiagnosticRun::PHASE_REVIEW,
        'revision' => 1,
        'created_by_user_id' => $guidance->id,
    ]);

    $clientCaptureId = (string) Str::uuid();
    $assetStore = app(SensitiveAssetStore::class);
    $bytes = physicalChallengePng();
    $sha256 = hash('sha256', $bytes);
    $path = 'omr/diagnostic/'.$run->public_id.'/'.$clientCaptureId.'_input.bin';
    $assetStore->store($bytes, $path);

    $inputAsset = OmrTemplateAsset::query()->create([
        'template_version_id' => $run->template_version_id,
        'kind' => OmrTemplateAsset::KIND_EVIDENCE,
        'page_no' => 1,
        'disk' => 'sensitive',
        'path' => $path,
        'mime' => 'image/png',
        'bytes' => strlen($bytes),
        'sha256' => $sha256,
        'metadata' => ['role' => 'capture_input', 'diagnostic_run_id' => $run->id],
    ]);

    $capture = OmrDiagnosticCapture::query()->create([
        'diagnostic_run_id' => $run->id,
        'client_capture_id' => $clientCaptureId,
        'status' => OmrDiagnosticCapture::STATUS_NEEDS_REVIEW,
        'version' => 1,
        'input_asset_id' => $inputAsset->id,
        'input_asset_hash' => $sha256,
        'created_by_user_id' => $guidance->id,
        'captured_at' => now(),
    ]);

    $itemEvidenceHash = hash('sha256', 'item-review-proof');
    $item = OmrDiagnosticItemEvidence::query()->create([
        'diagnostic_capture_id' => $capture->id,
        'item_key' => 'PROFILE_VERBAL_Q_001',
        'page_no' => 1,
        'area_code' => 'PROFILE_VERBAL',
        'question_no' => 1,
        'option_code' => 'A',
        'evidence_hash' => $itemEvidenceHash,
        'evidence_version' => 'v1',
    ]);

    // Verify current projection: ReviewSave is projected; DiagnosticCompare and Complete are unprojected
    $projectionResponse = $this->actingAs($guidance)
        ->getJson(route('setup.exam-materials.omr-test.runs.show', $run->public_id))
        ->assertOk();

    $projectedCommands = $projectionResponse->json('commands');
    expect($projectedCommands)->toContain(OmrWorkbenchCommand::ReviewSave->value)
        ->and($projectedCommands)->not->toContain(OmrWorkbenchCommand::DiagnosticCompare->value)
        ->and($projectedCommands)->not->toContain(OmrWorkbenchCommand::DiagnosticComplete->value);

    // 1. Projected command passes through the adapter boundary and persists state
    $reviewResponse = $this->actingAs($guidance)
        ->postJson(route('setup.exam-materials.omr-test.runs.review', [
            'run' => $run->public_id,
            'item' => $item->id,
        ]), [
            'item_evidence_id' => $item->id,
            'decision' => OmrReviewDecision::ConfirmMachine->value,
            'final_option' => $item->option_code,
            'expected_run_revision' => $run->revision,
            'expected_capture_version' => (int) $capture->version,
            'expected_item_revision' => 0,
            'expected_evidence_version' => 1,
            'reviewed_evidence_hash' => $itemEvidenceHash,
            'idempotency_key' => 'adapter-proof-review-'.$item->id,
        ]);

    $reviewResponse->assertOk()
        ->assertJsonPath('execution_kind', OmrExecutionKind::Diagnostic->value)
        ->assertJsonPath('revision', 2);

    expect(OmrDiagnosticReviewRevision::query()->where('diagnostic_item_evidence_id', $item->id)->count())->toBe(1)
        ->and($run->fresh()->revision)->toBe(2);

    // 2. Unprojected command (complete) is intercepted by the adapter boundary and cannot persist
    $unprojected = $this->actingAs($guidance)
        ->postJson(route('setup.exam-materials.omr-test.runs.complete', $run->public_id), [
            'expected_revision' => $run->fresh()->revision,
            'idempotency_key' => 'unprojected-complete-'.$run->id,
        ]);

    $unprojected->assertStatus(422)
        ->assertJson([
            'outcome' => 'failed',
            'code' => 'diagnostic_command_not_projected',
        ]);

    expect($run->fresh()->revision)->toBe(2);

    // 3. Foreign command context (foreign workbench_id and run_id) cannot persist
    $foreign = $this->actingAs($guidance)
        ->postJson(route('setup.exam-materials.omr-test.runs.review', [
            'run' => $run->public_id,
            'item' => $item->id,
        ]), [
            'item_evidence_id' => $item->id,
            'decision' => OmrReviewDecision::ConfirmMachine->value,
            'final_option' => $item->option_code,
            'expected_run_revision' => $run->fresh()->revision,
            'expected_capture_version' => (int) $capture->fresh()->version,
            'expected_item_revision' => 1,
            'expected_evidence_version' => 1,
            'reviewed_evidence_hash' => $itemEvidenceHash,
            'idempotency_key' => 'foreign-proof-'.$item->id,
            'workbench_id' => 'diagnostic:foreign-run-id',
            'run_id' => 999999,
            'execution_kind' => OmrExecutionKind::Production->value,
        ]);

    $foreign->assertStatus(422);

    // Foreign entity (item route mismatch) returns 404
    $foreignItem = $this->actingAs($guidance)
        ->postJson(route('setup.exam-materials.omr-test.runs.review', [
            'run' => $run->public_id,
            'item' => $item->id,
        ]), [
            'item_evidence_id' => 999999,
            'decision' => OmrReviewDecision::ConfirmMachine->value,
            'final_option' => $item->option_code,
            'expected_run_revision' => $run->fresh()->revision,
            'expected_capture_version' => 1,
            'expected_item_revision' => 0,
            'expected_evidence_version' => 1,
            'reviewed_evidence_hash' => hash('sha256', 'unknown'),
            'idempotency_key' => 'foreign-item-proof',
        ]);

    $foreignItem->assertStatus(404);

    // Assert that foreign commands did not create additional reviews and left revision unchanged
    expect(OmrDiagnosticReviewRevision::query()->where('diagnostic_item_evidence_id', $item->id)->count())->toBe(1)
        ->and($run->fresh()->revision)->toBe(2);

    // 4. Production aggregates remain completely isolated with zero writes
    expect(OmrCaptureBatch::query()->count())->toBe($productionBatchesBefore)
        ->and(OmrCaptureSheet::query()->count())->toBe($productionSheetsBefore)
        ->and(ScoreRecord::query()->count())->toBe($scoresBefore)
        ->and(OmrBlankInstancePageClaim::query()->count())->toBe(0);
});

test('canonical production entry preserves compiled pixels without inventing legacy print overlays', function (): void {
    $fixture = buildOmrGradingFixture($this);
    app(CreateOmrCaptureBatch::class)->handle($fixture['grading'], $fixture['guidance'], 'canonical-browser-entry');
    $this->actingAs($fixture['guidance'])
        ->get(route('scoring.sessions.omr', $fixture['session']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Assessment/Omr/Capture')
            ->where('batch.template.page_width', 2480)
            ->where('batch.template.page_height', 3508)
            ->where('batch.template.page_count', 1)
            ->where('batch.template.identity_regions.1.name', null));
});

test('pending-page changes advance the production projection even before adoption creates a sheet', function (): void {
    $fixture = buildOmrGradingFixture($this);
    $actor = $fixture['guidance'];
    $batch = app(CreateOmrCaptureBatch::class)->handle($fixture['grading'], $actor, 'pending-projection-revision');
    $cycle = $fixture['session']->academicCycle;
    $issuance = OmrQrDisabledIssuance::query()->create([
        'template_version_id' => $batch->template_version_id,
        'institution_id' => $cycle->institution_id,
        'academic_cycle_id' => $cycle->id,
        'issuance_code' => 'QDI-'.Str::uuid(),
        'page_count' => $batch->templateVersion->page_count,
        'copy_policy' => 'unique_instance',
        'intended_copy_count' => 1,
        'status' => 'printed',
        'artifact_manifest_hash' => hash('sha256', 'synthetic-pending-revision'),
        'created_by_user_id' => $actor->id,
    ]);
    $url = route('scoring.sessions.omr.batches.workbench', ['examSession' => $fixture['session'], 'batch' => $batch]);
    $before = $this->actingAs($actor)->getJson($url)->assertOk()->json('revision');
    $set = app(StartOmrPendingCaptureSet::class)
        ->forProduction($fixture['grading'], $batch, $issuance, $actor, (string) Str::uuid());
    $started = $this->getJson($url)->assertOk();
    expect($started->json('revision'))->toBeGreaterThan($before)
        ->and($started->json('context.pending_capture.revision'))->toBe(1);
    $set->forceFill(['revision' => 2])->saveOrFail();
    $changed = $this->getJson($url)->assertOk();
    expect($changed->json('revision'))->toBeGreaterThan($started->json('revision'))
        ->and($changed->json('context.pending_capture.revision'))->toBe(2)
        ->and(OmrCaptureSheet::query()->where('batch_id', $batch->id)->count())->toBe(0);
});
