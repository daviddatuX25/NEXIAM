<?php

use App\Assessment\Actions\Omr\AnswerSheetImportStatus;
use App\Assessment\Actions\Omr\CancelAnswerSheetImport;
use App\Assessment\Actions\Omr\ConfirmAnswerSheetImport;
use App\Assessment\Actions\Omr\CreateAnswerSheetImportOperation;
use App\Assessment\Actions\Omr\InspectAnswerSheetImport;
use App\Assessment\Actions\Omr\RestoreAnswerSheetImport;
use App\Assessment\Omr\Canonical\OmrBundlePackage;
use App\Authorization\CapabilityCatalog;
use App\Jobs\Assessment\InspectAnswerSheetImportJob;
use App\Models\Assessment\AnswerSheetImportOperation;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedE2Foundation();
    $this->actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_SCORING_OMR, 'web');
    $this->actor->givePermissionTo(CapabilityCatalog::GUIDANCE_SCORING_OMR);
    Storage::fake('sensitive');
    Storage::fake('local');
    Queue::fake();
    $this->initialDraftCount = OmrTemplateAuthoringDraft::query()->count();
});

afterEach(function (): void {
    foreach ((array) ($this->temporaryImportArchives ?? []) as $path) {
        if (is_string($path) && is_file($path)) {
            @unlink($path);
        }
    }
});

function makeAnswerSheetImportTestBundle(string $code = 'IMPORT-OPERATION-TEST'): string
{
    $authoring = [
        'studio' => [
            'sections' => [[
                'id' => 'guide-stable-1',
                'page_no' => 1,
                'native_label' => 'EXAMPLE_Q_1',
                'option_tokens' => ['A-B', 'A-C'],
            ]],
            'physicalInventory' => [
                'revision' => 1,
                'sections' => [[
                    'printed_section_uid' => 'section-opaque-1',
                    'name' => 'Example',
                    'page_no' => 1,
                    'numbering' => ['start' => 1, 'step' => 1],
                    'expected_item_count' => 1,
                    'display_order' => 1,
                    'items' => [[
                        'physical_item_uid' => 'physical-opaque-1',
                        'native_label' => 'EXAMPLE_Q_1',
                        'page_no' => 1,
                        'printed_number' => 1,
                        'option_tokens' => ['A-B', 'A-C'],
                        'guide_id' => 'guide-stable-1',
                    ]],
                ]],
            ],
        ],
        'mapping' => [
            'test_instrument_version_id' => 991,
            'test_instrument_code' => 'HISTORICAL-ONLY',
            'input' => ['test_instrument_subtest_id' => 992],
        ],
    ];

    $files = ['authoring.json' => json_encode($authoring, JSON_THROW_ON_ERROR)];
    $manifest = [
        'template' => [
            'code' => $code,
            'display_name' => 'Independent imported sheet',
            'page_count' => 1,
            'registration_mode' => 'document_bound',
            'qr_mode' => 'qr_disabled',
        ],
        'source_kind' => 'html',
        'registration_mode' => 'document_bound',
        'qr_mode' => 'qr_disabled',
        'requested_page_count' => 1,
        'draft_assets' => [],
        'test_instrument_version_id' => 991,
        'test_instrument_code' => 'HISTORICAL-ONLY',
    ];

    return app(OmrBundlePackage::class)->createArchive($files, $manifest);
}

function storeAnswerSheetImportOperation(User $actor, string $archivePath, ?UploadedFile $ledger = null): AnswerSheetImportOperation
{
    $upload = new UploadedFile($archivePath, basename($archivePath), 'application/zip', null, true);

    return app(CreateAnswerSheetImportOperation::class)->handle($upload, $ledger, $actor);
}

test('upload creates a private queued operation with a stable status page', function (): void {
    $archivePath = makeAnswerSheetImportTestBundle();
    $this->temporaryImportArchives[] = $archivePath;
    $beforeDrafts = OmrTemplateAuthoringDraft::query()->count();

    $response = actingAsAdmissionSupportStaff($this, $this->actor)
        ->post(route('setup.exam-materials.answer-sheets.import'), [
            'bundle' => new UploadedFile($archivePath, 'portable-sheet.zip', 'application/zip', null, true),
        ]);

    $operation = AnswerSheetImportOperation::query()->sole();
    $response->assertRedirect(route('setup.exam-materials.answer-sheets.imports.show', ['operation' => $operation->id]));
    expect($operation->phase)->toBe(AnswerSheetImportOperation::PHASE_QUEUED)
        ->and($operation->owner_user_id)->toBe($this->actor->id)
        ->and(Storage::disk('sensitive')->exists($operation->archive_path))->toBeTrue()
        ->and(OmrTemplateAuthoringDraft::query()->count())->toBe($beforeDrafts);

    Queue::assertPushed(InspectAnswerSheetImportJob::class, fn (InspectAnswerSheetImportJob $job): bool => $job->operationId === $operation->id && $job->generation === 1
    );

    actingAsAdmissionSupportStaff($this, $this->actor)
        ->get(route('setup.exam-materials.answer-sheets.imports.status', ['operation' => $operation->id]))
        ->assertOk()
        ->assertJsonPath('phase', AnswerSheetImportOperation::PHASE_QUEUED)
        ->assertJsonPath('filename', 'portable-sheet.zip');
});

test('inspection publishes real archive member progress and typed review notes without materializing a draft', function (): void {
    $archivePath = makeAnswerSheetImportTestBundle('INSPECT-ONLY');
    $this->temporaryImportArchives[] = $archivePath;
    $operation = storeAnswerSheetImportOperation($this->actor, $archivePath);
    $beforeDrafts = OmrTemplateAuthoringDraft::query()->count();

    app(InspectAnswerSheetImport::class)->handle((string) $operation->id, 1);

    $operation->refresh();
    expect($operation->phase)->toBe(AnswerSheetImportOperation::PHASE_READY_TO_RESTORE)
        ->and($operation->progress_total)->toBeGreaterThan(0)
        ->and($operation->progress_done)->toBe($operation->progress_total)
        ->and($operation->review_revision)->toBe(1)
        ->and($operation->report_json['can_restore'])->toBeTrue()
        ->and(collect($operation->report_json['notes'])->pluck('code'))->toContain('external_scoring_reference_not_linked')
        ->and(OmrTemplateAuthoringDraft::query()->count())->toBe($beforeDrafts);
});

test('unrecognized recovery ledgers block restore with a replacement remedy', function (): void {
    $archivePath = makeAnswerSheetImportTestBundle('UNVERIFIED-LEDGER');
    $this->temporaryImportArchives[] = $archivePath;
    $ledger = UploadedFile::fake()->createWithContent('recovery.json', '{"original_zip_sha256":"invented"}');
    $operation = storeAnswerSheetImportOperation($this->actor, $archivePath, $ledger);

    app(InspectAnswerSheetImport::class)->handle((string) $operation->id, 1);

    $operation->refresh();
    expect($operation->phase)->toBe(AnswerSheetImportOperation::PHASE_NEEDS_REVIEW)
        ->and($operation->report_json['issues'][0]['code'])->toBe('unrecognized_recovery_ledger')
        ->and($operation->report_json['issues'][0]['allowed_actions'])->toContain('replace_source')
        ->and($operation->result_draft_id)->toBeNull();
});

test('stale confirmation is rejected and a repeated successful restore returns one independent draft', function (): void {
    $archivePath = makeAnswerSheetImportTestBundle('EXACT-ONCE-IMPORT');
    $this->temporaryImportArchives[] = $archivePath;
    $operation = storeAnswerSheetImportOperation($this->actor, $archivePath);

    app(InspectAnswerSheetImport::class)->handle((string) $operation->id, 1);
    $operation->refresh();
    $status = app(AnswerSheetImportStatus::class)->project($operation);
    $beforeDrafts = OmrTemplateAuthoringDraft::query()->count();

    expect(fn () => app(ConfirmAnswerSheetImport::class)->handle(
        $this->actor,
        $operation,
        0,
        (string) $status['review_token'],
    ))->toThrow(ValidationException::class);

    $confirmed = app(ConfirmAnswerSheetImport::class)->handle(
        $this->actor,
        $operation,
        (int) $status['review_revision'],
        (string) $status['review_token'],
    );
    app(ConfirmAnswerSheetImport::class)->handle(
        $this->actor,
        $confirmed,
        (int) $status['review_revision'],
        (string) $status['review_token'],
    );

    app(RestoreAnswerSheetImport::class)->handle((string) $operation->id, (int) $confirmed->generation);
    app(RestoreAnswerSheetImport::class)->handle((string) $operation->id, (int) $confirmed->generation);

    $operation->refresh();
    $draft = $operation->resultDraft()->firstOrFail();
    expect($operation->phase)->toBe(AnswerSheetImportOperation::PHASE_COMPLETED)
        ->and($operation->result_receipt)->not->toBeNull()
        ->and(OmrTemplateAuthoringDraft::query()->count())->toBe($beforeDrafts + 1)
        ->and($draft->test_instrument_version_id)->toBeNull()
        ->and($draft->authoring_json['studio']['physicalInventory']['sections'][0]['items'][0]['physical_item_uid'])->toBe('physical-opaque-1')
        ->and($draft->authoring_json['mapping']['test_instrument_version_id'] ?? null)->toBeNull()
        ->and($draft->authoring_json['import_provenance']['external_scoring_reference']['manifest_version_id'])->toBe(991);
});

test('cancellation advances the generation and stale checking work cannot revive the operation', function (): void {
    $archivePath = makeAnswerSheetImportTestBundle('CANCEL-IMPORT');
    $this->temporaryImportArchives[] = $archivePath;
    $operation = storeAnswerSheetImportOperation($this->actor, $archivePath);

    $cancelled = app(CancelAnswerSheetImport::class)->handle($this->actor, $operation, 1);
    app(InspectAnswerSheetImport::class)->handle((string) $operation->id, 1);

    $operation->refresh();
    expect($cancelled->phase)->toBe(AnswerSheetImportOperation::PHASE_CANCELLED)
        ->and((int) $operation->generation)->toBe(2)
        ->and($operation->phase)->toBe(AnswerSheetImportOperation::PHASE_CANCELLED)
        ->and($operation->report_json)->toBeNull()
        ->and(OmrTemplateAuthoringDraft::query()->count())->toBe($this->initialDraftCount);
});

test('corrupt archives remain review blockers without partial materialization', function (): void {
    $archivePath = tempnam(sys_get_temp_dir(), 'sheet-import-corrupt-');
    file_put_contents($archivePath, 'not a zip archive');
    $this->temporaryImportArchives[] = $archivePath;
    $operation = storeAnswerSheetImportOperation($this->actor, $archivePath);
    $beforeDrafts = OmrTemplateAuthoringDraft::query()->count();

    app(InspectAnswerSheetImport::class)->handle((string) $operation->id, 1);

    $operation->refresh();
    expect($operation->phase)->toBe(AnswerSheetImportOperation::PHASE_NEEDS_REVIEW)
        ->and($operation->report_json['issues'][0]['code'])->toBe('bundle_archive_corrupt')
        ->and($operation->report_json['issues'][0]['remedy'])->toContain('does not repair unknown corruption')
        ->and($operation->result_draft_id)->toBeNull()
        ->and(OmrTemplateAuthoringDraft::query()->count())->toBe($beforeDrafts);
});
