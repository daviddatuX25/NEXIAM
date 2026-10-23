<?php

use App\Assessment\Actions\Omr\Canonical\PrintOmrBlankInstances;
use App\Assessment\Actions\Omr\StartOmrPendingCaptureSet;
use App\Assessment\Omr\Canonical\OmrBlankInstanceService;
use App\Assessment\Omr\Canonical\OmrBlankPrintOrchestrator;
use App\Assessment\Omr\Canonical\OmrTemplateAssetStore;
use App\Assessment\Omr\OmrCaptureResolver;
use App\Assessment\Omr\OmrQrMode;
use App\Assessment\Omr\OmrRegistrationMode;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Jobs\Assessment\RenderOmrBlankInstanceBatchJob;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\OmrAnswerSheetTemplate;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrBlankInstance;
use App\Models\Assessment\OmrBlankInstancePageClaim;
use App\Models\Assessment\OmrCaptureBatch;
use App\Models\Assessment\OmrCaptureSheet;
use App\Models\Assessment\OmrDiagnosticCapture;
use App\Models\Assessment\OmrDiagnosticRun;
use App\Models\Assessment\OmrPendingCapturePageAnalysis;
use App\Models\Assessment\OmrPendingCapturePageEvidence;
use App\Models\Assessment\OmrPendingCaptureSet;
use App\Models\Assessment\OmrQrDisabledIssuance;
use App\Models\Assessment\OmrQrDisabledPageClaim;
use App\Models\Assessment\OmrQuestionBooklet;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\Assessment\ScoreRecord;
use App\Models\Examination\ExamSession;
use App\Models\Examination\ExamSessionMaterialSnapshot;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use App\Models\Printing\PrintJob;
use App\Models\StaffProfile;
use App\Models\User;
use App\Support\SensitiveAssetStore;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use setasign\Fpdi\Fpdi;
use Spatie\Permission\Models\Permission;
use ZipArchive;

require_once __DIR__.'/../../Support/OmrAssistedCaptureTestRequests.php';

uses(RefreshDatabase::class);

test('blank printing produces ordered downloadable ZIP without duplicate retry rows', function (): void {
    Storage::fake('local');
    Bus::fake();
    config(['omr.qr-keyring.current' => 'current-v1', 'omr.qr-keyring.keys' => ['current-v1' => 'test-secret']]);
    $fixture = qrSignedPrintFixture('BASE');
    $actor = $fixture['actor'];
    $version = $fixture['version'];

    app(PrintOmrBlankInstances::class)->handle($version, $actor, 2, 'print-once');
    $job = PrintJob::query()->latest('id')->firstOrFail();
    (new RenderOmrBlankInstanceBatchJob($job->id, $actor->id))->handle(
        app(OmrBlankInstanceService::class),
        app(OmrBlankPrintOrchestrator::class),
        app(RecordAuditEvent::class),
    );

    $ready = $job->fresh();
    expect(collect($ready->per_item_manifest)->pluck('error')->filter()->all())->toBe([])
        ->and($ready->status)->toBe(PrintJob::STATUS_READY)
        ->and($ready->file_path)->toEndWith('.zip')
        ->and(Storage::disk('local')->exists($ready->file_path))->toBeTrue()
        ->and(OmrBlankInstance::query()->count())->toBe(2);

    $checksum = $ready->file_checksum;
    (new RenderOmrBlankInstanceBatchJob($job->id, $actor->id))->handle(
        app(OmrBlankInstanceService::class),
        app(OmrBlankPrintOrchestrator::class),
        app(RecordAuditEvent::class),
    );
    app(PrintOmrBlankInstances::class)->handle($version, $actor, 2, 'print-once');
    expect(PrintJob::query()->count())->toBe(1)
        ->and(OmrBlankInstance::query()->count())->toBe(2)
        ->and($job->fresh()->file_checksum)->toBe($checksum);

    app(PrintOmrBlankInstances::class)->handle(
        $version,
        $actor,
        25,
        'reusable-master-once',
        OmrBlankInstance::COPY_POLICY_REUSABLE_MASTER,
    );
    $masterJob = PrintJob::query()->latest('id')->firstOrFail();
    (new RenderOmrBlankInstanceBatchJob($masterJob->id, $actor->id))->handle(
        app(OmrBlankInstanceService::class),
        app(OmrBlankPrintOrchestrator::class),
        app(RecordAuditEvent::class),
    );
    $master = OmrBlankInstance::query()->latest('id')->firstOrFail();

    expect($masterJob->fresh()->progress_total)->toBe(1)
        ->and(OmrBlankInstance::query()->count())->toBe(3)
        ->and($master->copy_policy)->toBe(OmrBlankInstance::COPY_POLICY_REUSABLE_MASTER)
        ->and($master->intended_copy_count)->toBe(25)
        ->and(data_get($masterJob->fresh()->per_item_manifest, '0.copy_policy'))->toBe(OmrBlankInstance::COPY_POLICY_REUSABLE_MASTER);
});
test('a signed delivery observing an active renderer does not create a second output set', function (): void {
    Storage::fake('local');
    Bus::fake();
    config(['omr.qr-keyring.current' => 'current-v1', 'omr.qr-keyring.keys' => ['current-v1' => 'test-secret']]);
    $fixture = qrSignedPrintFixture('BUSY');
    app(PrintOmrBlankInstances::class)->handle($fixture['version'], $fixture['actor'], 1, 'print-busy');
    $job = PrintJob::query()->latest('id')->firstOrFail();
    $job->forceFill(['status' => PrintJob::STATUS_RENDERING])->save();

    (new RenderOmrBlankInstanceBatchJob($job->id, $fixture['actor']->id))->handle(
        app(OmrBlankInstanceService::class),
        app(OmrBlankPrintOrchestrator::class),
        app(RecordAuditEvent::class),
    );

    expect($job->fresh()->status)->toBe(PrintJob::STATUS_RENDERING)
        ->and($job->fresh()->file_path)->toBeNull()
        ->and(OmrBlankInstance::query()->count())->toBe(0);
});

test('a failed signed page write cannot create a claimable blank instance', function (): void {
    Storage::fake('local');
    Bus::fake();
    config(['omr.qr-keyring.current' => 'current-v1', 'omr.qr-keyring.keys' => ['current-v1' => 'test-secret']]);
    $fixture = qrSignedPrintFixture('PAGE-WRITE-FAIL');
    app(PrintOmrBlankInstances::class)->handle($fixture['version'], $fixture['actor'], 1, 'page-write-fail');
    $job = PrintJob::query()->latest('id')->firstOrFail();
    failLocalStorageWrites(static fn (string $path): bool => str_contains($path, '/chunk-'));
    $renderJob = new RenderOmrBlankInstanceBatchJob($job->id, $fixture['actor']->id);

    expect(fn () => $renderJob->handle(
        app(OmrBlankInstanceService::class),
        app(OmrBlankPrintOrchestrator::class),
        app(RecordAuditEvent::class),
    ))->toThrow(RuntimeException::class, 'blank_print_storage_failed');
    $renderJob->failed(new RuntimeException('simulated page write failure'));

    expect($job->fresh()->status)->toBe(PrintJob::STATUS_FAILED)
        ->and($job->fresh()->file_path)->toBeNull()
        ->and(OmrBlankInstance::query()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles('omr-canonical/print-jobs/'.$job->id))->toBe([]);
});

test('a failed signed ZIP write rolls back instances and leaves no downloadable artifact', function (): void {
    Storage::fake('local');
    Bus::fake();
    config(['omr.qr-keyring.current' => 'current-v1', 'omr.qr-keyring.keys' => ['current-v1' => 'test-secret']]);
    $fixture = qrSignedPrintFixture('ZIP-WRITE-FAIL');
    app(PrintOmrBlankInstances::class)->handle($fixture['version'], $fixture['actor'], 1, 'zip-write-fail');
    $job = PrintJob::query()->latest('id')->firstOrFail();
    failLocalStorageWrites(static fn (string $path): bool => str_ends_with($path, 'blank-instances.zip'));
    $renderJob = new RenderOmrBlankInstanceBatchJob($job->id, $fixture['actor']->id);

    expect(fn () => $renderJob->handle(
        app(OmrBlankInstanceService::class),
        app(OmrBlankPrintOrchestrator::class),
        app(RecordAuditEvent::class),
    ))->toThrow(RuntimeException::class, 'blank_print_storage_failed');
    $renderJob->failed(new RuntimeException('simulated ZIP write failure'));

    expect($job->fresh()->status)->toBe(PrintJob::STATUS_FAILED)
        ->and($job->fresh()->file_path)->toBeNull()
        ->and(OmrBlankInstance::query()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles('omr-canonical/print-jobs/'.$job->id))->toBe([]);
});

test('QR-disabled print emits only pinned static pages and persists separate provenance', function (): void {
    Storage::fake('local');
    Bus::fake();
    config(['omr.qr-keyring.current' => 'missing-key', 'omr.qr-keyring.keys' => []]);
    $fixture = qrDisabledPrintFixture('UNIQUE');

    $action = app(PrintOmrBlankInstances::class);
    $operation = $action->handle($fixture['version'], $fixture['actor'], 2, 'qr-disabled-print-once');
    $job = PrintJob::query()->latest('id')->firstOrFail();
    $issuance = OmrQrDisabledIssuance::query()->firstOrFail();

    expect($job->pinned_input_json['qr_mode'])->toBe(OmrQrMode::Disabled->value)
        ->and($job->pinned_input_json['registration_mode'])->toBe(OmrRegistrationMode::DocumentBound->value)
        ->and($job->pinned_input_json['compiled_pages'])->toBe([['page_no' => 1], ['page_no' => 2]])
        ->and($job->pinned_input_json['artifact_hash'])->toBe($fixture['version']->authoring_manifest['artifact_set']['artifact_hash'])
        ->and($job->pinned_input_json['print_pdf_sha256'])->toBe(hash('sha256', $fixture['static_pdf']))
        ->and($issuance->copy_policy)->toBe(OmrBlankInstance::COPY_POLICY_UNIQUE_INSTANCE)
        ->and($issuance->intended_copy_count)->toBe(2)
        ->and($issuance->status)->toBe(OmrQrDisabledIssuance::STATUS_QUEUED);

    expect($action->handle($fixture['version'], $fixture['actor'], 2, 'qr-disabled-print-once')->id)
        ->toBe($operation->id)
        ->and(PrintJob::query()->count())->toBe(1)
        ->and(OmrQrDisabledIssuance::query()->count())->toBe(1);

    (new RenderOmrBlankInstanceBatchJob($job->id, $fixture['actor']->id))->handle(
        app(OmrBlankInstanceService::class),
        app(OmrBlankPrintOrchestrator::class),
        app(RecordAuditEvent::class),
    );

    $ready = $job->fresh();
    $issued = $issuance->fresh();
    $zipPath = tempnam(sys_get_temp_dir(), 'qr-disabled-print-test-');
    expect($zipPath)->not->toBeFalse();
    file_put_contents($zipPath, Storage::disk('local')->get($ready->file_path));
    $zip = new ZipArchive;
    expect($zip->open($zipPath))->toBeTrue();
    $manifest = json_decode((string) $zip->getFromName('manifest.json'), true, flags: JSON_THROW_ON_ERROR);
    $manifestHash = $manifest['artifact_manifest_hash'];
    unset($manifest['artifact_manifest_hash']);

    expect($ready->status)->toBe(PrintJob::STATUS_READY)
        ->and($ready->file_checksum)->toBe(hash('sha256', Storage::disk('local')->get($ready->file_path)))
        ->and($issued->status)->toBe(OmrQrDisabledIssuance::STATUS_PRINTED)
        ->and($issued->artifact_manifest_hash)->toBe($manifestHash)
        ->and(hash('sha256', json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)))->toBe($manifestHash)
        ->and($manifest['qr_mode'])->toBe(OmrQrMode::Disabled->value)
        ->and($manifest['registration_mode'])->toBe(OmrRegistrationMode::DocumentBound->value)
        ->and($manifest['compiled_pages'])->toBe([['page_no' => 1], ['page_no' => 2]])
        ->and($manifest['artifact_hash'])->toBe($job->pinned_input_json['artifact_hash'])
        ->and($manifest['copy_policy'])->toBe(OmrBlankInstance::COPY_POLICY_UNIQUE_INSTANCE)
        ->and($manifest['intended_copy_count'])->toBe(2)
        ->and($manifest['rendered_copy_count'])->toBe(2)
        ->and(count($manifest['copies']))->toBe(2)
        ->and(array_key_exists('qr_payload_hash', $manifest))->toBeFalse()
        ->and(array_key_exists('signing_key_id', $manifest))->toBeFalse()
        ->and(OmrBlankInstance::query()->count())->toBe(0);

    foreach ($manifest['copies'] as $copy) {
        expect(array_column($copy['pages'], 'page_no'))->toBe([1, 2]);
        foreach ($copy['pages'] as $page) {
            $storedPdf = Storage::disk('local')->get($page['path']);
            $archivedPdf = $zip->getFromName($page['archive_name']);
            expect($storedPdf)->toBe($archivedPdf)
                ->and(hash('sha256', $storedPdf))->toBe($page['sha256'])
                ->and(omrPrintPdfPageCount($storedPdf))->toBe(1)
                ->and(array_key_exists('qr_payload_hash', $page))->toBeFalse();
        }
    }
    $zip->close();
    @unlink($zipPath);

    $this->actingAs($fixture['actor'])
        ->getJson(route('setup.omr.templates.canonical.blank.instances', $fixture['version']))
        ->assertOk()
        ->assertJsonPath('qr_mode', OmrQrMode::Disabled->value)
        ->assertJsonPath('instances', [])
        ->assertJsonPath('qr_disabled_issuances.0.id', $issued->id)
        ->assertJsonPath('qr_disabled_issuances.0.artifact_manifest_hash', $manifestHash)
        ->assertJsonPath('qr_disabled_issuances.0.print_job_status', PrintJob::STATUS_READY);
});

test('QR-disabled reusable-master policy renders one static set while preserving intended copy count', function (): void {
    Storage::fake('local');
    Bus::fake();
    config(['omr.qr-keyring.current' => 'missing-key', 'omr.qr-keyring.keys' => []]);
    $fixture = qrDisabledPrintFixture('REUSABLE');

    app(PrintOmrBlankInstances::class)->handle(
        $fixture['version'],
        $fixture['actor'],
        7,
        'qr-disabled-master-once',
        OmrBlankInstance::COPY_POLICY_REUSABLE_MASTER,
    );
    $job = PrintJob::query()->latest('id')->firstOrFail();
    $issuance = OmrQrDisabledIssuance::query()->firstOrFail();
    (new RenderOmrBlankInstanceBatchJob($job->id, $fixture['actor']->id))->handle(
        app(OmrBlankInstanceService::class),
        app(OmrBlankPrintOrchestrator::class),
        app(RecordAuditEvent::class),
    );

    $manifest = $job->fresh()->output_manifest;
    expect($job->fresh()->progress_total)->toBe(1)
        ->and($manifest['copy_policy'])->toBe(OmrBlankInstance::COPY_POLICY_REUSABLE_MASTER)
        ->and($manifest['intended_copy_count'])->toBe(7)
        ->and($manifest['rendered_copy_count'])->toBe(1)
        ->and(count($manifest['copies']))->toBe(1)
        ->and($issuance->fresh()->status)->toBe(OmrQrDisabledIssuance::STATUS_PRINTED)
        ->and($issuance->fresh()->intended_copy_count)->toBe(7)
        ->and(OmrBlankInstance::query()->count())->toBe(0);
});

test('QR-disabled print refuses inactive versions, invalid copy policy, bad page manifests, and print hashes', function (): void {
    Storage::fake('local');
    Bus::fake();
    $action = app(PrintOmrBlankInstances::class);

    $inactive = qrDisabledPrintFixture('INACTIVE', ['status' => OmrAnswerSheetTemplateVersion::STATUS_DRAFT]);
    expect(fn () => $action->handle($inactive['version'], $inactive['actor'], 1, 'inactive-print'))
        ->toThrow(RuntimeException::class, 'template_not_active');

    $invalidCopy = qrDisabledPrintFixture('COPY');
    expect(fn () => $action->handle($invalidCopy['version'], $invalidCopy['actor'], 1, 'invalid-copy', 'unknown-policy'))
        ->toThrow(RuntimeException::class, 'copy_policy_invalid');

    $badPages = qrDisabledPrintFixture('PAGES', ['page_count' => 1]);
    expect(fn () => $action->handle($badPages['version'], $badPages['actor'], 1, 'bad-page-count'))
        ->toThrow(RuntimeException::class, 'print_page_count_mismatch');

    $badHash = qrDisabledPrintFixture('HASH', [
        'authoring_manifest' => [
            'artifact_set' => ['pdf_hash' => hash('sha256', 'different-pdf')],
        ],
    ]);
    expect(fn () => $action->handle($badHash['version'], $badHash['actor'], 1, 'bad-print-hash'))
        ->toThrow(RuntimeException::class, 'print_artifact_hash_mismatch');

    expect(PrintJob::query()->count())->toBe(0)
        ->and(OmrQrDisabledIssuance::query()->count())->toBe(0);
});

test('failed QR-disabled print history is retained and a fresh idempotency key creates a recoverable issuance', function (): void {
    Storage::fake('local');
    Bus::fake();
    $fixture = qrDisabledPrintFixture('RECOVERY');
    $action = app(PrintOmrBlankInstances::class);
    $action->handle($fixture['version'], $fixture['actor'], 1, 'qr-disabled-failed-run');
    $failedJob = PrintJob::query()->latest('id')->firstOrFail();

    (new RenderOmrBlankInstanceBatchJob($failedJob->id, $fixture['actor']->id))
        ->failed(new RuntimeException('simulated renderer failure'));

    $failedIssuance = OmrQrDisabledIssuance::query()->firstOrFail();
    expect($failedJob->fresh()->status)->toBe(PrintJob::STATUS_FAILED)
        ->and($failedIssuance->fresh()->status)->toBe(OmrQrDisabledIssuance::STATUS_FAILED)
        ->and($failedIssuance->fresh()->failure_code)->toBe('blank_print_failed')
        ->and($failedIssuance->fresh()->failed_at)->not->toBeNull();

    $action->handle($fixture['version'], $fixture['actor'], 1, 'qr-disabled-retry-run');
    expect(OmrQrDisabledIssuance::query()->count())->toBe(2)
        ->and(OmrQrDisabledIssuance::query()->where('status', OmrQrDisabledIssuance::STATUS_QUEUED)->count())->toBe(1)
        ->and(OmrQrDisabledIssuance::query()->where('status', OmrQrDisabledIssuance::STATUS_FAILED)->count())->toBe(1);
});

test('QR-disabled diagnostic capture set pins ordered pages and rejects replay after expiry or foreign owner', function (): void {
    Storage::fake('sensitive');
    $fixture = qrDisabledPrintFixture('CAPTURE-SET-DIAGNOSTIC');
    $actor = $fixture['actor'];
    $cycle = AcademicCycle::query()->where('institution_id', $actor->staffProfile->institution_id)->firstOrFail();
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG, 'web');
    $actor->givePermissionTo(CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG);
    $booklet = OmrQuestionBooklet::query()->create(['code' => 'QDI-DIAG-SET', 'name' => 'Diagnostic set']);
    $bookletVersion = OmrQuestionBookletVersion::query()->create([
        'booklet_id' => $booklet->id, 'template_version_id' => $fixture['version']->id,
        'version' => 1, 'status' => OmrQuestionBookletVersion::STATUS_ACTIVE,
        'answer_key_hash' => hash('sha256', 'diagnostic-set'),
    ]);
    $run = OmrDiagnosticRun::query()->create([
        'institution_id' => $cycle->institution_id, 'academic_cycle_id' => $cycle->id,
        'template_version_id' => $fixture['version']->id, 'booklet_version_id' => $bookletVersion->id,
        'declared_set_code' => 'A', 'revision' => 1, 'created_by_user_id' => $actor->id,
    ]);
    $action = app(StartOmrPendingCaptureSet::class);
    $key = (string) Str::uuid();
    $set = $action->forDiagnostic($run, $actor, $key);
    $replay = $action->forDiagnostic($run, $actor, $key);
    expect($replay->id)->toBe($set->id)
        ->and($set->production_batch_id)->toBeNull()
        ->and($set->diagnostic_run_id)->toBe($run->id)
        ->and($set->issuance_id)->toBeNull()
        ->and($set->expected_pages_json)->toBe([['page_no' => 1], ['page_no' => 2]])
        ->and($set->active_page_no)->toBe(1)
        ->and($set->revision)->toBe(1)
        ->and($set->copy_policy)->toBe('diagnostic_only')
        ->and(OmrPendingCaptureSet::query()->count())->toBe(1);

    $diagnosticUrl = route('setup.exam-materials.omr-test.runs.pending-capture.start', $run->public_id);
    $this->actingAs($actor)->postJson($diagnosticUrl, [
        'idempotency_key' => $key,
    ])->assertOk()
        ->assertJsonPath('context.pending_capture.id', $set->public_id)
        ->assertJsonPath('context.pending_capture.active_page_no', 1)
        ->assertJsonPath('context.pending_capture.pages.1.page_no', 2);
    $this->getJson(route('setup.exam-materials.omr-test.runs.show', $run->public_id))
        ->assertOk()->assertJsonPath('context.pending_capture.id', $set->public_id);
    $this->postJson($diagnosticUrl, [
        'idempotency_key' => (string) Str::uuid(),
        'template_version_id' => $fixture['version']->id,
    ])->assertUnprocessable();
    $diagnosticCaptureUrl = route('setup.exam-materials.omr-test.runs.capture', $run->public_id);
    $wrongPagePayload = omrAssistedCapturePayload($this, route('setup.exam-materials.omr-test.runs.show', $run->public_id), [
        'image' => UploadedFile::fake()->image('wrong-page.png'),
    ]);
    $wrongPagePayload['page_no'] = 2;
    $this->post($diagnosticCaptureUrl, $wrongPagePayload, ['Accept' => 'application/json'])->assertUnprocessable();
    expect(OmrPendingCaptureSet::query()->count())->toBe(1)
        ->and(OmrDiagnosticCapture::query()->count())->toBe(0);

    $second = $this->postJson($diagnosticUrl, [
        'idempotency_key' => (string) Str::uuid(),
    ])->assertOk()->json('context.pending_capture.id');
    expect($second)->not->toBe($set->public_id);
    $this->postJson($diagnosticUrl, ['idempotency_key' => $key])
        ->assertOk()->assertJsonPath('context.pending_capture.id', $set->public_id);
    $this->getJson(route('setup.exam-materials.omr-test.runs.show', $run->public_id))
        ->assertOk()->assertJsonPath('context.pending_capture.id', $second);

    $this->post($diagnosticCaptureUrl, omrAssistedCapturePayload($this, route('setup.exam-materials.omr-test.runs.show', $run->public_id), [
        'image' => UploadedFile::fake()->image('older-target.png'),
        'pending_capture_id' => $set->public_id,
    ]), ['Accept' => 'application/json'])->assertStatus(409);
    expect(OmrPendingCapturePageEvidence::query()->where('set_id', $set->id)->count())->toBe(0)
        ->and($set->fresh()->revision)->toBe(1)
        ->and($set->fresh()->status)->toBe(OmrPendingCaptureSet::STATUS_PENDING)
        ->and(OmrDiagnosticCapture::query()->count())->toBe(0);
    $set = OmrPendingCaptureSet::query()->where('public_id', $second)->firstOrFail();
    $captureUrl = $diagnosticCaptureUrl;
    $pageOneBytes = (string) UploadedFile::fake()->image('first-page.png')->get();
    $pageOnePayload = omrAssistedCapturePayload($this, route('setup.exam-materials.omr-test.runs.show', $run->public_id), [
        'image' => UploadedFile::fake()->createWithContent('first-page.png', $pageOneBytes),
    ]);
    $pageOneResponse = $this->post($captureUrl, $pageOnePayload, ['Accept' => 'application/json'])->assertOk()
        ->assertJsonPath('context.pending_capture.revision', 2)
        ->assertJsonPath('context.pending_capture.active_page_no', 2);
    $pageTwoBytes = (string) UploadedFile::fake()->image('second-page.png')->get();
    $pageTwoPayload = omrAssistedCapturePayload($this, route('setup.exam-materials.omr-test.runs.show', $run->public_id), [
        'image' => UploadedFile::fake()->createWithContent('second-page.png', $pageTwoBytes),
        'page_no' => 2,
    ]);
    $this->post($captureUrl, $pageTwoPayload, ['Accept' => 'application/json'])->assertOk()
        ->assertJsonPath('context.pending_capture.status', 'captured')
        ->assertJsonPath('context.pending_capture.revision', 4)
        ->assertJsonPath('context.pending_capture.pairing_code', 'pairing_missing')
        ->assertJsonPath('context.pending_capture.pages.1.capture_count', 1);
    $pageTwoPayload['image'] = UploadedFile::fake()->createWithContent('second-page.png', $pageTwoBytes);
    $this->post($captureUrl, $pageTwoPayload, ['Accept' => 'application/json'])->assertOk()
        ->assertJsonPath('context.pending_capture.revision', 4);
    $pageTwoPayload['image'] = UploadedFile::fake()->image('changed.png', 17, 19);
    $this->post($captureUrl, $pageTwoPayload, ['Accept' => 'application/json'])->assertStatus(409)
        ->assertJsonPath('code', 'omr_capture_set_idempotency_conflict');
    $stalePageTwoPayload = omrAssistedCapturePayload($this, route('setup.exam-materials.omr-test.runs.show', $run->public_id), [
        'image' => UploadedFile::fake()->image('stale.png'),
        'page_no' => 2,
        'pending_capture_revision' => 2,
    ]);
    $this->post($captureUrl, $stalePageTwoPayload, ['Accept' => 'application/json'])->assertStatus(409)
        ->assertJsonPath('code', 'omr_capture_set_revision_stale');
    $replacementPayload = omrAssistedCapturePayload($this, route('setup.exam-materials.omr-test.runs.show', $run->public_id), [
        'image' => UploadedFile::fake()->image('retake.png'),
        'replacement_reason' => 'Page one image was blurred',
    ]);
    $this->post($captureUrl, $replacementPayload, ['Accept' => 'application/json'])->assertOk()
        ->assertJsonPath('context.pending_capture.revision', 6)
        ->assertJsonPath('context.pending_capture.pages.0.capture_count', 2);
    $history = OmrPendingCapturePageEvidence::query()->where('set_id', $set->id)
        ->where('page_no', 1)->orderBy('id')->get();
    expect($history[1]->supersedes_id)->toBe($history[0]->id)
        ->and($history[0]->replacement_reason)->toBeNull()
        ->and(hash('sha256', app(SensitiveAssetStore::class)->read($history[0]->asset_path)))->toBe($history[0]->input_hash)
        ->and(hash('sha256', app(SensitiveAssetStore::class)->read($history[1]->asset_path)))->toBe($history[1]->input_hash);
    expect(OmrPendingCapturePageAnalysis::query()->whereIn('evidence_id', $history->pluck('id'))->count())->toBe(2);
    expect(OmrPendingCapturePageEvidence::query()->where('set_id', $set->id)->count())->toBe(3)
        ->and(OmrDiagnosticCapture::query()->count())->toBe(0)
        ->and(ScoreRecord::query()->count())->toBe(0);

    $foreign = User::factory()->create();
    expect(fn () => $action->forDiagnostic($run, $foreign, (string) Str::uuid()))
        ->toThrow(AuthorizationException::class);
    $foreignPayload = omrAssistedCapturePayload($this, route('setup.exam-materials.omr-test.runs.show', $run->public_id), [
        'image' => UploadedFile::fake()->image('foreign.png'),
        'replacement_reason' => 'Foreign attempt',
    ]);
    $this->actingAs($foreign)->postJson($diagnosticUrl, [
        'idempotency_key' => (string) Str::uuid(),
    ])->assertForbidden();
    $this->post($captureUrl, $foreignPayload, ['Accept' => 'application/json'])->assertForbidden();
    $this->actingAs($actor);
    $expiredPayload = omrAssistedCapturePayload($this, route('setup.exam-materials.omr-test.runs.show', $run->public_id), [
        'image' => UploadedFile::fake()->image('expired.png'),
        'replacement_reason' => 'Expired page replacement',
    ]);
    $this->travel(31)->minutes();
    $this->getJson(route('setup.exam-materials.omr-test.runs.show', $run->public_id))
        ->assertOk()->assertJsonPath('context.pending_capture', null);
    $this->post($captureUrl, $expiredPayload, ['Accept' => 'application/json'])->assertUnprocessable()
        ->assertJsonPath('code', 'omr_capture_set_expired');
    expect(fn () => $action->forDiagnostic($run, $actor, $key))->toThrow(RuntimeException::class, 'omr_capture_set_expired');
    expect($action->forDiagnostic($run, $actor, (string) Str::uuid())->id)->not->toBe($set->id);
});

test('QR-disabled production capture set binds nested batch and printed issuance, refusing mismatched replay', function (): void {
    Storage::fake('local');
    Storage::fake('sensitive');
    Bus::fake();
    $fixture = qrDisabledPrintFixture('CAPTURE-SET-PRODUCTION');
    $actor = $fixture['actor'];
    $version = $fixture['version'];
    $cycle = AcademicCycle::query()->where('institution_id', $actor->staffProfile->institution_id)->firstOrFail();
    app(PrintOmrBlankInstances::class)->handle($version, $actor, 1, 'capture-set-print');
    $job = PrintJob::query()->latest('id')->firstOrFail();
    (new RenderOmrBlankInstanceBatchJob($job->id, $actor->id))->handle(
        app(OmrBlankInstanceService::class),
        app(OmrBlankPrintOrchestrator::class),
        app(RecordAuditEvent::class),
    );
    $issuance = OmrQrDisabledIssuance::query()->firstOrFail();
    expect($issuance->fresh()->status)->toBe(OmrQrDisabledIssuance::STATUS_PRINTED);

    $booklet = OmrQuestionBooklet::query()->create(['code' => 'QDI-PROD-SET', 'name' => 'Production set']);
    $bookletVersion = OmrQuestionBookletVersion::query()->create([
        'booklet_id' => $booklet->id, 'template_version_id' => $version->id,
        'version' => 1, 'status' => OmrQuestionBookletVersion::STATUS_ACTIVE,
        'answer_key_hash' => hash('sha256', 'production-set'),
    ]);
    $room = Room::factory()->create(['institution_id' => $cycle->institution_id]);
    $examSession = ExamSession::factory()->create([
        'academic_cycle_id' => $cycle->id, 'room_id' => $room->id,
        'booklet_version_id' => $bookletVersion->id,
        'status' => ExamSession::STATUS_COMPLETED,
    ]);
    ExamSessionMaterialSnapshot::query()->create([
        'exam_session_id' => $examSession->id, 'revision' => 1, 'is_current' => true,
        'template_version_id' => $version->id, 'booklet_version_id' => $bookletVersion->id,
        'material_hash' => hash('sha256', 'production-material'),
        'scoring_policy_identity' => 'capture-set-test',
        'published_at' => now(),
    ]);
    $session = GradingSession::query()->create([
        'exam_session_id' => $examSession->id, 'status' => GradingSession::STATUS_OPEN,
        'version' => 1, 'opened_by_user_id' => $actor->id, 'opened_at' => now(),
    ]);
    $this->actingAs($actor)->get(route('scoring.sessions.omr', ['examSession' => $examSession]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Assessment/Omr/Capture')
            ->where('material.qr_mode', 'qr_disabled')
            ->where('qr_disabled_issuances.0.id', $issuance->id)
            ->where('qr_disabled_issuances.0.remaining_claims', 1)
            ->etc());

    $batch = OmrCaptureBatch::query()->create([
        'grading_session_id' => $session->id, 'template_version_id' => $version->id,
        'booklet_version_id' => $bookletVersion->id, 'status' => OmrCaptureBatch::STATUS_OPEN,
        'version' => 1, 'opened_by_user_id' => $actor->id,
    ]);

    $action = app(StartOmrPendingCaptureSet::class);
    $key = (string) Str::uuid();
    $set = $action->forProduction($session, $batch, $issuance, $actor, $key);
    expect($action->forProduction($session, $batch, $issuance, $actor, $key)->id)->toBe($set->id)
        ->and($set->production_batch_id)->toBe($batch->id)
        ->and($set->issuance_id)->toBe($issuance->id)
        ->and($set->template_version_id)->toBe($version->id)
        ->and($set->copy_policy)->toBe(OmrBlankInstance::COPY_POLICY_UNIQUE_INSTANCE)
        ->and($set->expected_pages_json)->toBe([['page_no' => 1], ['page_no' => 2]]);
    $this->actingAs($actor)->postJson(route('scoring.sessions.omr.batches.workbench.pending-capture.adopt', [
        'examSession' => $examSession, 'batch' => $batch,
    ]), [
        'pending_capture_id' => $set->public_id,
        'pending_capture_revision' => $set->revision,
    ])->assertUnprocessable()->assertJsonPath('code', 'omr_capture_set_pairing_required');
    expect(OmrQrDisabledPageClaim::query()->count())->toBe(0)
        ->and(OmrCaptureSheet::query()->count())->toBe(0)
        ->and($set->fresh()->adopted_sheet_id)->toBeNull();

    $productionUrl = route('scoring.sessions.omr.batches.workbench.pending-capture.start', [
        'examSession' => $examSession, 'batch' => $batch,
    ]);
    $started = $this->actingAs($actor)->postJson($productionUrl, [
        'issuance_id' => $issuance->id, 'idempotency_key' => $key,
    ])->assertOk()
        ->assertJsonPath('context.pending_capture.id', $set->public_id)
        ->assertJsonPath('context.pending_capture.revision', 1)
        ->assertJsonPath('context.pending_capture.pages.1.page_no', 2);
    $this->getJson(route('scoring.sessions.omr.batches.workbench', [
        'examSession' => $examSession, 'batch' => $batch,
    ]))->assertOk()->assertJsonPath('context.pending_capture.id', $set->public_id);
    $this->postJson($productionUrl, [
        'issuance_id' => $issuance->id, 'idempotency_key' => (string) Str::uuid(),
        'template_version_id' => $version->id,
    ])->assertUnprocessable();
    $captureUrl = route('scoring.sessions.omr.batches.workbench.capture_start', [
        'examSession' => $examSession, 'batch' => $batch,
    ]);
    $workbenchUrl = route('scoring.sessions.omr.batches.workbench', [
        'examSession' => $examSession, 'batch' => $batch,
    ]);
    $wrongPagePayload = omrAssistedCapturePayload($this, $workbenchUrl, [
        'image' => UploadedFile::fake()->image('wrong-page.png'),
        'capture_mode' => 'manual',
    ]);
    $wrongPagePayload['page_no'] = 2;
    $this->post($captureUrl, $wrongPagePayload, ['Accept' => 'application/json'])->assertUnprocessable();
    $this->post($captureUrl, omrAssistedCapturePayload($this, $workbenchUrl, [
        'image' => UploadedFile::fake()->image('forged-template.png'),
        'capture_mode' => 'manual',
        'template_version_id' => $version->id,
    ]), ['Accept' => 'application/json'])->assertUnprocessable();
    expect(OmrPendingCapturePageEvidence::query()->where('set_id', $set->id)->count())->toBe(0)
        ->and(OmrCaptureSheet::query()->count())->toBe(0)
        ->and(OmrQrDisabledPageClaim::query()->count())->toBe(0)
        ->and(ScoreRecord::query()->count())->toBe(0)
        ->and(OmrPendingCaptureSet::query()->count())->toBe(1);

    $second = $this->postJson($productionUrl, [
        'issuance_id' => $issuance->id, 'idempotency_key' => (string) Str::uuid(),
    ])->assertOk()->json('context.pending_capture.id');
    expect($second)->not->toBe($set->public_id);
    $this->postJson($productionUrl, [
        'issuance_id' => $issuance->id, 'idempotency_key' => $key,
    ])->assertOk()->assertJsonPath('context.pending_capture.id', $set->public_id);
    $this->getJson(route('scoring.sessions.omr.batches.workbench', [
        'examSession' => $examSession, 'batch' => $batch,
    ]))->assertOk()->assertJsonPath('context.pending_capture.id', $second);

    $pageOneBytes = (string) UploadedFile::fake()->image('page-one.png', 20, 20)->get();
    $pageOneQuad = [
        'points' => [[0.1, 0.1], [0.9, 0.1], [0.9, 0.9], [0.1, 0.9]],
        'width' => 20,
        'height' => 20,
        'orientation' => 1,
        'input_hash' => hash('sha256', $pageOneBytes),
        'page_no' => 1,
    ];
    $this->post($captureUrl, omrAssistedCapturePayload($this, $workbenchUrl, [
        'image' => UploadedFile::fake()->image('older-target.png'),
        'pending_capture_id' => $set->public_id,
    ]), ['Accept' => 'application/json'])->assertStatus(409);
    expect(OmrPendingCapturePageEvidence::query()->where('set_id', $set->id)->count())->toBe(0)
        ->and($set->fresh()->revision)->toBe(1)
        ->and($set->fresh()->status)->toBe(OmrPendingCaptureSet::STATUS_PENDING)
        ->and(OmrCaptureSheet::query()->count())->toBe(0)
        ->and(OmrQrDisabledPageClaim::query()->count())->toBe(0)
        ->and(ScoreRecord::query()->count())->toBe(0);
    $set = OmrPendingCaptureSet::query()->where('public_id', $second)->firstOrFail();
    $pageOnePayload = omrAssistedCapturePayload($this, $workbenchUrl, [
        'image' => UploadedFile::fake()->createWithContent('page-one.png', $pageOneBytes),
        'capture_mode' => 'manual',
        'client_quad' => $pageOneQuad,
    ]);
    $pageOneResponse = $this->post($captureUrl, $pageOnePayload, ['Accept' => 'application/json'])->assertOk()
        ->assertJsonPath('context.pending_capture.revision', 2)
        ->assertJsonPath('context.pending_capture.active_page_no', 2)
        ->assertJsonPath('context.pending_capture.pages.0.capture_count', 1);
    $pageOneEvidence = OmrPendingCapturePageEvidence::query()
        ->where('set_id', $set->id)
        ->where('page_no', 1)
        ->firstOrFail();
    expect($pageOneEvidence->client_quad_json)->toBe($pageOneQuad);
    $replayPageOnePayload = $pageOnePayload;
    $replayPageOnePayload['image'] = UploadedFile::fake()->createWithContent('page-one.png', $pageOneBytes);
    $this->post($captureUrl, $replayPageOnePayload, ['Accept' => 'application/json'])->assertOk()
        ->assertJsonPath('context.pending_capture.pages.0.capture_count', 1);
    expect(OmrPendingCapturePageEvidence::query()->where('set_id', $set->id)->count())->toBe(1)
        ->and(ScoreRecord::query()->count())->toBe(0);
    $stalePageTwoPayload = omrAssistedCapturePayload($this, $workbenchUrl, [
        'image' => UploadedFile::fake()->image('stale-page-two.png'),
        'capture_mode' => 'manual',
        'page_no' => 2,
        'pending_capture_revision' => 1,
    ]);
    $this->post($captureUrl, $stalePageTwoPayload, ['Accept' => 'application/json'])->assertStatus(409)
        ->assertJsonPath('code', 'omr_capture_set_revision_stale');
    expect(OmrPendingCapturePageEvidence::query()->where('set_id', $set->id)->count())->toBe(1)
        ->and(ScoreRecord::query()->count())->toBe(0);
    $pageTwoResponse = $this->post($captureUrl, omrAssistedCapturePayload($this, $workbenchUrl, [
        'image' => UploadedFile::fake()->image('page-two.png'),
        'capture_mode' => 'manual',
        'page_no' => 2,
    ]), ['Accept' => 'application/json'])->assertOk()
        ->assertJsonPath('context.pending_capture.status', 'captured')
        ->assertJsonPath('context.pending_capture.revision', 4)
        ->assertJsonPath('context.pending_capture.pairing_code', 'pairing_missing')
        ->assertJsonPath('context.pending_capture.pages.1.capture_count', 1);
    $this->post($captureUrl, omrAssistedCapturePayload($this, $workbenchUrl, [
        'image' => UploadedFile::fake()->image('no-reason.png'),
        'capture_mode' => 'manual',
    ]), ['Accept' => 'application/json'])->assertUnprocessable()
        ->assertJsonPath('code', 'omr_capture_set_replacement_reason_required');
    expect(OmrPendingCapturePageEvidence::query()->where('set_id', $set->id)->count())->toBe(2)
        ->and(OmrCaptureSheet::query()->count())->toBe(0)
        ->and(OmrBlankInstancePageClaim::query()->count())->toBe(0)
        ->and(ScoreRecord::query()->count())->toBe(0);

    $replacementBytes = (string) UploadedFile::fake()->image('page-one-retake.png', 20, 20)->get();
    $replacementQuad = [
        'points' => [[0.1, 0.1], [0.9, 0.1], [0.9, 0.9], [0.1, 0.9]],
        'width' => 20,
        'height' => 20,
        'orientation' => 1,
        'input_hash' => hash('sha256', $replacementBytes),
        'page_no' => 1,
    ];
    $replacement = $this->post($captureUrl, omrAssistedCapturePayload($this, $workbenchUrl, [
        'image' => UploadedFile::fake()->createWithContent('page-one-retake.png', $replacementBytes),
        'capture_mode' => 'manual',
        'replacement_reason' => 'Page one image was blurred',
        'client_quad' => $replacementQuad,
    ]), ['Accept' => 'application/json'])->assertOk()
        ->assertJsonPath('context.pending_capture.status', 'captured')
        ->assertJsonPath('context.pending_capture.revision', 6)
        ->assertJsonPath('context.pending_capture.pairing_code', 'pairing_missing')
        ->assertJsonPath('context.pending_capture.pages.0.capture_count', 2);
    $pageOneHistory = OmrPendingCapturePageEvidence::query()->where('set_id', $set->id)
        ->where('page_no', 1)->orderBy('id')->get();
    expect($pageOneHistory[1]->client_quad_json)->toMatchArray($replacementQuad)
        ->and($pageOneHistory[1]->supersedes_id)->toBe($pageOneHistory[0]->id)
        ->and($pageOneHistory[0]->replacement_reason)->toBeNull()
        ->and($pageOneHistory[1]->replacement_reason)->toBe('Page one image was blurred')
        ->and(hash('sha256', app(SensitiveAssetStore::class)->read($pageOneHistory[0]->asset_path)))->toBe($pageOneHistory[0]->input_hash)
        ->and(hash('sha256', app(SensitiveAssetStore::class)->read($pageOneHistory[1]->asset_path)))->toBe($pageOneHistory[1]->input_hash);
    expect(OmrPendingCapturePageEvidence::query()->where('set_id', $set->id)->count())->toBe(3)
        ->and(OmrCaptureSheet::query()->count())->toBe(0)
        ->and(OmrQrDisabledPageClaim::query()->count())->toBe(0)
        ->and(ScoreRecord::query()->count())->toBe(0);

    $otherExamSession = ExamSession::factory()->create([
        'academic_cycle_id' => $cycle->id, 'room_id' => $room->id,
        'booklet_version_id' => $bookletVersion->id,
        'status' => ExamSession::STATUS_COMPLETED,
    ]);
    $otherSession = GradingSession::query()->create([
        'exam_session_id' => $otherExamSession->id, 'status' => GradingSession::STATUS_OPEN,
        'version' => 1, 'opened_by_user_id' => $actor->id, 'opened_at' => now(),
    ]);
    $mismatchedCaptureUrl = route('scoring.sessions.omr.batches.workbench.capture_start', [
        'examSession' => $otherExamSession, 'batch' => $batch,
    ]);
    $mismatchedPayload = omrAssistedCapturePayload($this, $workbenchUrl, [
        'image' => UploadedFile::fake()->image('foreign-session.png'),
        'capture_mode' => 'manual',
    ]);
    $this->post($mismatchedCaptureUrl, $mismatchedPayload, ['Accept' => 'application/json'])->assertNotFound()
        ->assertJsonPath('code', 'omr_production_workbench_not_found');
    expect(OmrPendingCapturePageEvidence::query()->where('set_id', $set->id)->count())->toBe(3)
        ->and(ScoreRecord::query()->count())->toBe(0);

    expect(fn () => $action->forProduction($otherSession, $batch, $issuance, $actor, (string) Str::uuid()))
        ->toThrow(RuntimeException::class, 'omr_capture_set_session_batch_mismatch');

    app(PrintOmrBlankInstances::class)->handle(
        $version, $actor, 3, 'capture-set-master-print', OmrBlankInstance::COPY_POLICY_REUSABLE_MASTER,
    );
    $masterJob = PrintJob::query()->latest('id')->firstOrFail();
    (new RenderOmrBlankInstanceBatchJob($masterJob->id, $actor->id))->handle(
        app(OmrBlankInstanceService::class),
        app(OmrBlankPrintOrchestrator::class),
        app(RecordAuditEvent::class),
    );
    $otherIssuance = OmrQrDisabledIssuance::query()->latest('id')->firstOrFail();
    expect($otherIssuance->status)->toBe(OmrQrDisabledIssuance::STATUS_PRINTED);
    expect(fn () => $action->forProduction($session, $batch, $otherIssuance, $actor, $key))
        ->toThrow(RuntimeException::class, 'omr_capture_set_idempotency_conflict');
    expect(OmrPendingCaptureSet::query()->count())->toBe(2);
    $expiredPayload = omrAssistedCapturePayload($this, $workbenchUrl, [
        'image' => UploadedFile::fake()->image('expired-retake.png'),
        'capture_mode' => 'manual',
        'pending_capture_revision' => 6,
        'replacement_reason' => 'Expired page replacement',
    ]);
    $this->travel(31)->minutes();
    $expiredWorkbench = $this->getJson($workbenchUrl)->assertOk()->assertJsonPath('context.pending_capture', null);
    $this->post($captureUrl, $expiredPayload, ['Accept' => 'application/json'])->assertUnprocessable()
        ->assertJsonPath('code', 'omr_capture_set_expired');
    expect(OmrPendingCapturePageEvidence::query()->where('set_id', $set->id)->count())->toBe(3)
        ->and(OmrCaptureSheet::query()->count())->toBe(0)
        ->and(OmrQrDisabledPageClaim::query()->count())->toBe(0)
        ->and(ScoreRecord::query()->count())->toBe(0);
});

test('QR-disabled resolver requires complete issued claims before applicant material lookup', function (): void {
    Storage::fake('local');
    Storage::fake('sensitive');
    Bus::fake();
    $fixture = qrDisabledPrintFixture('CLAIM-BOUNDARY');
    $actor = $fixture['actor'];
    $version = $fixture['version'];
    $cycle = AcademicCycle::query()->where('institution_id', $actor->staffProfile->institution_id)->firstOrFail();
    app(PrintOmrBlankInstances::class)->handle($version, $actor, 1, 'claim-boundary-print');
    $job = PrintJob::query()->latest('id')->firstOrFail();
    (new RenderOmrBlankInstanceBatchJob($job->id, $actor->id))->handle(
        app(OmrBlankInstanceService::class),
        app(OmrBlankPrintOrchestrator::class),
        app(RecordAuditEvent::class),
    );
    $issuance = OmrQrDisabledIssuance::query()->firstOrFail();
    $booklet = OmrQuestionBooklet::query()->create(['code' => 'CLAIM-BOOKLET', 'name' => 'Claim booklet']);
    $bookletVersion = OmrQuestionBookletVersion::query()->create([
        'booklet_id' => $booklet->id, 'template_version_id' => $version->id,
        'version' => 1, 'status' => OmrQuestionBookletVersion::STATUS_ACTIVE,
        'answer_key_hash' => hash('sha256', 'claim-booklet'),
    ]);
    $room = Room::factory()->create(['institution_id' => $cycle->institution_id]);
    $exam = ExamSession::factory()->create([
        'academic_cycle_id' => $cycle->id, 'room_id' => $room->id,
        'booklet_version_id' => $bookletVersion->id, 'status' => ExamSession::STATUS_PUBLISHED,
    ]);
    ExamSessionMaterialSnapshot::query()->create([
        'exam_session_id' => $exam->id, 'revision' => 1, 'is_current' => true,
        'template_version_id' => $version->id, 'booklet_version_id' => $bookletVersion->id,
        'material_hash' => hash('sha256', 'claim-material'),
        'scoring_policy_identity' => 'claim-boundary', 'published_at' => now(),
    ]);
    $session = GradingSession::query()->create([
        'exam_session_id' => $exam->id, 'status' => GradingSession::STATUS_OPEN,
        'version' => 1, 'opened_by_user_id' => $actor->id, 'opened_at' => now(),
    ]);
    $batch = OmrCaptureBatch::query()->create([
        'grading_session_id' => $session->id, 'template_version_id' => $version->id,
        'booklet_version_id' => $bookletVersion->id, 'status' => OmrCaptureBatch::STATUS_OPEN,
        'version' => 1, 'opened_by_user_id' => $actor->id,
    ]);
    $set = app(StartOmrPendingCaptureSet::class)->forProduction(
        $session, $batch, $issuance, $actor, (string) Str::uuid(),
    );
    $set->update(['status' => OmrPendingCaptureSet::STATUS_READY, 'revision' => 2]);
    $sheet = $batch->sheets()->create([
        'status' => OmrCaptureSheet::STATUS_NEEDS_REVIEW,
        'expected_page_count' => 2, 'capture_mode' => 'manual',
        'client_capture_id' => $set->public_id, 'detected_applicant_number' => 'UNASSIGNED7',
        'version' => 0, 'asset_state' => OmrCaptureSheet::ASSET_LIVE,
    ]);
    $set->update(['adopted_sheet_id' => $sheet->id, 'revision' => 3]);
    $resolver = app(OmrCaptureResolver::class);
    expect($resolver->resolveSheet($sheet)->failureCode())->toBe('qr_disabled_claim_missing');
    foreach ([1, 2] as $pageNo) {
        $hash = hash('sha256', 'claim-input-'.$pageNo);
        $assetPath = 'omr/test/claim-'.$pageNo.'.bin';
        $evidence = OmrPendingCapturePageEvidence::query()->create([
            'set_id' => $set->id, 'page_no' => $pageNo,
            'client_capture_id' => (string) Str::uuid(), 'input_hash' => $hash,
            'asset_path' => $assetPath, 'mime_type' => 'image/png',
            'byte_count' => 1, 'captured_by_user_id' => $actor->id,
        ]);
        OmrPendingCapturePageAnalysis::query()->create([
            'evidence_id' => $evidence->id, 'detected_applicant_number' => 'UNASSIGNED7',
            'evidence_json' => ['input_hash' => $hash, 'page_no' => $pageNo],
        ]);
        $page = $sheet->pages()->create([
            'page_no' => $pageNo, 'status' => 'succeeded',
            'asset_path' => $assetPath,
            'proposal_json' => ['input_hash' => $hash, 'page_no' => $pageNo],
        ]);
        OmrQrDisabledPageClaim::query()->create([
            'issuance_id' => $issuance->id, 'set_id' => $set->id,
            'evidence_id' => $evidence->id, 'capture_sheet_page_id' => $page->id,
            'page_no' => $pageNo, 'input_hash' => $hash, 'created_by_user_id' => $actor->id,
        ]);
    }
    expect($resolver->resolveSheet($sheet)->failureCode())->toBe('applicant_unresolved');
    $sheet->pages()->where('page_no', 2)->firstOrFail()->update(['asset_path' => 'omr/test/foreign.bin']);
    expect($resolver->resolveSheet($sheet)->failureCode())->toBe('qr_disabled_claim_stale');
    expect(OmrBlankInstancePageClaim::query()->count())->toBe(0)
        ->and(ScoreRecord::query()->count())->toBe(0);
});

/**
 * @param  Closure(string): bool  $shouldFail
 */
function failLocalStorageWrites(Closure $shouldFail): void
{
    $disk = Storage::disk('local');
    $mock = Mockery::mock($disk)->makePartial();
    $mock->shouldReceive('put')->andReturnUsing(static function (string $path, string $contents) use ($disk, $shouldFail): bool {
        if ($shouldFail($path)) {
            return false;
        }

        return $disk->put($path, $contents);
    });
    Storage::shouldReceive('disk')->with('local')->andReturn($mock);
}

/**
 * @return array{actor: User, version: OmrAnswerSheetTemplateVersion, static_pdf: string}
 */
function qrSignedPrintFixture(string $suffix): array
{
    $fixture = qrDisabledPrintFixture('SIGNED-'.$suffix);
    $version = $fixture['version'];
    $authoringManifest = $version->authoring_manifest;
    $authoringManifest['qr_mode'] = OmrQrMode::Signed->value;
    $nativeBundle = $version->native_bundle_json;
    $nativeBundle['manifest']['qr_mode'] = OmrQrMode::Signed->value;
    $version->forceFill([
        'qr_mode' => OmrQrMode::Signed,
        'authoring_manifest' => $authoringManifest,
        'native_bundle_json' => $nativeBundle,
    ])->save();
    $fixture['version'] = $version->fresh();

    return $fixture;
}

/**
 * @param  array<string, mixed>  $versionOverrides
 * @return array{
 *     actor: User,
 *     version: OmrAnswerSheetTemplateVersion,
 *     static_pdf: string
 * }
 */
function qrDisabledPrintFixture(string $suffix, array $versionOverrides = []): array
{
    $identifier = strtoupper(substr(hash('sha256', $suffix), 0, 8));
    $institution = Institution::query()->create([
        'code' => 'QRD-'.$identifier,
        'name' => 'QR-disabled print fixture',
    ]);
    $cycleCode = 'QRD-CYCLE-'.$identifier;
    AcademicCycle::query()->create([
        'institution_id' => $institution->id,
        'code' => $cycleCode,
        'name' => 'QR-disabled print fixture cycle',
        'opens_on' => now()->subYear()->toDateString(),
        'closes_on' => now()->addYear()->toDateString(),
    ]);
    $actor = User::factory()->create();
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_SCORING_OMR, 'web');
    $actor->givePermissionTo(CapabilityCatalog::GUIDANCE_SCORING_OMR);
    $actor->staffProfile()->create([
        'institution_id' => $institution->id,
        'office' => 'guidance',
        'active_cycle_code' => $cycleCode,
        'status' => StaffProfile::STATUS_ACTIVE,
    ]);

    $pdf = new FPDF('P', 'mm', [216, 330]);
    for ($pageNo = 1; $pageNo <= 2; $pageNo++) {
        $pdf->AddPage('P', [216, 330]);
        $pdf->SetFont('Helvetica', '', 12);
        $pdf->Text(20, 20, 'QR-free static page '.$pageNo);
    }
    $staticPdf = (string) $pdf->Output('S');
    $compiledPages = [['page_no' => 1], ['page_no' => 2]];
    $artifactSet = [
        'artifact_hash' => hash('sha256', 'qr-disabled-artifact-'.$identifier),
        'pdf_hash' => hash('sha256', $staticPdf),
    ];
    $attributes = [
        'template_id' => OmrAnswerSheetTemplate::query()->create([
            'institution_id' => $institution->id,
            'is_synthetic' => false,
            'code' => 'QRD-'.$identifier,
            'name' => 'QR-disabled print fixture',
        ])->id,
        'version' => 1,
        'status' => OmrAnswerSheetTemplateVersion::STATUS_ACTIVE,
        'registration_mode' => OmrRegistrationMode::DocumentBound,
        'qr_mode' => OmrQrMode::Disabled,
        'page_count' => 2,
        'page_width' => 2551,
        'page_height' => 3898,
        'identity_regions_json' => [],
        'provider_config_json' => [],
        'config_hash' => hash('sha256', 'config-'.$identifier),
        'template_json_hash' => hash('sha256', 'template-'.$identifier),
        'native_bundle_version' => 'nexiam.omr.native-bundle.v1',
        'native_bundle_json' => [
            'template' => ['pages' => [
                ['page_no' => 1, 'pageDimensions' => [2551, 3898], 'fieldBlocks' => []],
                ['page_no' => 2, 'pageDimensions' => [2551, 3898], 'fieldBlocks' => []],
            ]],
            'config' => [],
            'manifest' => [
                'registration_mode' => OmrRegistrationMode::DocumentBound->value,
                'qr_mode' => OmrQrMode::Disabled->value,
                'compiled_pages' => $compiledPages,
            ],
        ],
        'native_bundle_hash' => hash('sha256', 'bundle-'.$identifier),
        'engine_name' => 'OMRChecker',
        'engine_commit' => 'test',
        'patch_identity' => 'test',
        'review_manifest_json' => [],
        'review_manifest_hash' => hash('sha256', 'review-'.$identifier),
        'authoring_manifest' => [
            'registration_mode' => OmrRegistrationMode::DocumentBound->value,
            'qr_mode' => OmrQrMode::Disabled->value,
            'compiled_pages' => $compiledPages,
            'artifact_set' => $artifactSet,
        ],
    ];
    $version = OmrAnswerSheetTemplateVersion::query()->create(array_replace_recursive($attributes, $versionOverrides));
    $draft = OmrTemplateAuthoringDraft::query()->create([
        'code' => 'QRD-DRAFT-'.$identifier,
        'name' => 'QR-disabled print fixture draft',
        'source_kind' => OmrTemplateAuthoringDraft::SOURCE_HTML,
        'registration_mode' => OmrRegistrationMode::DocumentBound,
        'qr_mode' => OmrQrMode::Disabled,
        'requested_page_count' => 2,
        'stage' => OmrTemplateAuthoringDraft::STAGE_COMPILED,
        'authoring_json' => [],
        'lock_version' => 0,
        'owner_user_id' => $actor->id,
        'compiled_version_id' => $version->id,
    ]);
    $asset = app(OmrTemplateAssetStore::class)->putDraftAsset(
        $draft,
        OmrTemplateAsset::KIND_PRINT_PDF,
        $staticPdf,
        'application/pdf',
    );
    app(OmrTemplateAssetStore::class)->pinToVersion($asset, $version);

    return ['actor' => $actor, 'version' => $version, 'static_pdf' => $staticPdf];
}

function omrPrintPdfPageCount(string $pdfBytes): int
{
    $path = tempnam(sys_get_temp_dir(), 'qr-disabled-page-count-');
    if ($path === false || file_put_contents($path, $pdfBytes) === false) {
        throw new RuntimeException('test_pdf_unavailable');
    }

    try {
        $pdf = new Fpdi;

        return $pdf->setSourceFile($path);
    } finally {
        @unlink($path);
    }
}
