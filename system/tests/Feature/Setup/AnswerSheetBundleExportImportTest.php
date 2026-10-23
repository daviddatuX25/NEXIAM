<?php

use App\Assessment\Actions\Omr\ExportAnswerSheetBundle;
use App\Assessment\Actions\Omr\ImportAnswerSheetBundle;
use App\Assessment\Omr\Canonical\OmrBundlePackage;
use App\Assessment\Omr\Canonical\OmrTemplateAssetStore;
use App\Assessment\Omr\OmrQrMode;
use App\Assessment\Omr\OmrRegistrationMode;
use App\Authorization\CapabilityCatalog;
use App\Jobs\Assessment\InspectAnswerSheetImportJob;
use App\Models\Assessment\AnswerSheetImportOperation;
use App\Models\Assessment\OmrAnswerSheetTemplate;
use App\Models\Assessment\OmrMarkerProfile;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedE2Foundation();
    $this->actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_SCORING_OMR, 'web');
    $this->actor->givePermissionTo(CapabilityCatalog::GUIDANCE_SCORING_OMR);
});

function createBundleTestDraft(?User $actor = null, string $code = 'EXPORT-TEST-1'): OmrTemplateAuthoringDraft
{
    $actor ??= test()->actor;
    $institution = Institution::current();

    $profile = OmrMarkerProfile::query()->create([
        'code' => $code.'.marker',
        'version' => 1,
        'family' => 'bullseye',
        'asset_path' => 'app/Assessment/Omr/Fiducial/omr_marker.jpg',
        'asset_sha256' => hash_file('sha256', base_path('app/Assessment/Omr/Fiducial/omr_marker.jpg')),
        'size_mm' => 15,
        'quiet_zone_mm' => 5,
        'matching_config' => [],
        'status' => OmrMarkerProfile::STATUS_QUALIFIED,
        'created_by_user_id' => $actor->id,
    ]);

    $template = OmrAnswerSheetTemplate::query()->create([
        'institution_id' => $institution->id,
        'code' => $code,
        'name' => 'Standard Test 150Q',
        'is_active' => false,
        'is_synthetic' => true,
        'created_by_user_id' => $actor->id,
    ]);

    $draft = OmrTemplateAuthoringDraft::query()->create([
        'institution_id' => $institution->id,
        'is_synthetic' => true,
        'registration_mode' => OmrRegistrationMode::FiducialAnchored,
        'qr_mode' => OmrQrMode::Signed,
        'template_id' => $template->id,
        'code' => $code,
        'name' => 'Standard Test 150Q',
        'source_kind' => OmrTemplateAuthoringDraft::SOURCE_HTML,
        'stage' => OmrTemplateAuthoringDraft::STAGE_PAGE_PREPARED,
        'marker_profile_id' => $profile->id,
        'lock_version' => 1,
        'owner_user_id' => $actor->id,
        'authoring_json' => [
            'layout' => ['marginMm' => 10],
            'components' => [
                ['id' => 'q-grid-1', 'type' => 'question_grid', 'start' => 1, 'count' => 50],
            ],
        ],
    ]);

    // Attach basic assets via store
    $store = app(OmrTemplateAssetStore::class);
    $store->putDraftAsset($draft, OmrTemplateAsset::KIND_NATIVE_TEMPLATE, json_encode(['pages' => [['page_no' => 1]], 'pageDimensions' => [2060, 3144]]), 'application/json', 1);
    $store->putDraftAsset($draft, OmrTemplateAsset::KIND_NATIVE_CONFIG, json_encode(['threshold' => 45]), 'application/json', 1);
    $store->putDraftAsset($draft, OmrTemplateAsset::KIND_NATIVE_MANIFEST, json_encode(['bundle_version' => '1']), 'application/json', 1);
    $store->putDraftAsset($draft, OmrTemplateAsset::KIND_REFERENCE_PAGE, '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="40"/></svg>', 'image/svg+xml', 1);
    $store->putDraftAsset($draft, OmrTemplateAsset::KIND_PRINT_PDF, '%PDF-1.4 mock pdf content', 'application/pdf', 1);
    $store->putDraftAsset($draft, OmrTemplateAsset::KIND_DIGITAL_PROOF, 'mock-png-proof-bytes', 'image/png', 1);

    return $draft;
}

test('authorized guidance counselor can export draft as valid zip bundle', function (): void {
    Storage::fake('local');
    $actor = $this->actor;
    $draft = createBundleTestDraft($actor, 'EXPORT-TEST-1');

    $exportAction = app(ExportAnswerSheetBundle::class);
    $result = $exportAction->handle($draft, $actor);

    expect(file_exists($result['zip_path']))->toBeTrue()
        ->and($result['filename'])->toBe('answersheet-export-test-1-v1.zip');

    // Verify zip contents
    $package = app(OmrBundlePackage::class);
    $unpacked = $package->extractAndValidate($result['zip_path']);

    expect($unpacked['manifest']['schema_version'])->toBe(OmrBundlePackage::SCHEMA_VERSION)
        ->and($unpacked['manifest']['template']['code'])->toBe('EXPORT-TEST-1')
        ->and(isset($unpacked['files']['authoring.json']))->toBeTrue()
        ->and(isset($unpacked['files']['native/template.json']))->toBeTrue()
        ->and(isset($unpacked['files']['print/sheet.pdf']))->toBeTrue()
        ->and(array_key_exists('test_instrument_version_id', $unpacked['manifest']))->toBeFalse()
        ->and(array_key_exists('test_instrument_code', $unpacked['manifest']))->toBeFalse();

    @unlink($result['zip_path']);
});

test('exported bundle can be imported into fresh database and hydrates new authoring draft', function (): void {
    Storage::fake('local');
    $actor = $this->actor;
    $draft = createBundleTestDraft($actor, 'RESTORE-TEST-1');

    $exportAction = app(ExportAnswerSheetBundle::class);
    $result = $exportAction->handle($draft, $actor);

    // Prepare uploaded file
    $uploadedZip = new UploadedFile($result['zip_path'], $result['filename'], 'application/zip', null, true);

    $importAction = app(ImportAnswerSheetBundle::class);
    $importedDraft = $importAction->handle($uploadedZip, $actor, ['force_new_template' => true]);

    expect($importedDraft)->toBeInstanceOf(OmrTemplateAuthoringDraft::class)
        ->and($importedDraft->code)->toBe('RESTORE-TEST-1-IMPORT')
        ->and($importedDraft->stage)->toBe(OmrTemplateAuthoringDraft::STAGE_DRAFT)
        ->and($importedDraft->compiled_version_id)->toBeNull()
        ->and($importedDraft->test_instrument_version_id)->toBeNull()
        ->and($importedDraft->assets()->count())->toBeGreaterThanOrEqual(4);

    @unlink($result['zip_path']);
});
test('legacy profile hints are preserved only as source provenance during independent restore', function (): void {
    Storage::fake('local');
    $actor = $this->actor;
    $draft = createBundleTestDraft($actor, 'PROFILE-HINT-RESTORE');
    $package = app(OmrBundlePackage::class);
    $export = app(ExportAnswerSheetBundle::class)->handle($draft, $actor);
    $extracted = $package->extractAndValidate($export['zip_path']);
    $manifest = $extracted['manifest'];
    $manifest['test_instrument_version_id'] = 998877;
    $manifest['test_instrument_code'] = 'NOT-A-LOCAL-PROFILE';
    $hintedArchive = $package->createArchive($extracted['files'], $manifest);

    try {
        $imported = app(ImportAnswerSheetBundle::class)->handle($hintedArchive, $actor);

        expect($imported->test_instrument_version_id)->toBeNull()
            ->and($imported->authoring_json['import_provenance']['external_scoring_reference']['manifest_version_id'])->toBe(998877)
            ->and($imported->authoring_json['import_provenance']['external_scoring_reference']['manifest_instrument_code'])->toBe('NOT-A-LOCAL-PROFILE')
            ->and($imported->authoring_json['import_provenance']['external_scoring_reference']['linked'])->toBeFalse();
    } finally {
        @unlink($export['zip_path']);
        @unlink($hintedArchive);
        $package->cleanDirectory($extracted['temp_dir']);
        @rmdir($extracted['temp_dir']);
    }
});

test('portable export and import preserve QR-free document-bound paper policy', function (): void {
    Storage::fake('local');
    $actor = $this->actor;
    $draft = createBundleTestDraft($actor, 'DOC-PORTABLE');
    $draft->forceFill([
        'registration_mode' => OmrRegistrationMode::DocumentBound,
        'qr_mode' => OmrQrMode::Disabled,
        'marker_profile_id' => null,
    ])->save();

    $result = app(ExportAnswerSheetBundle::class)->handle($draft, $actor);
    try {
        $unpacked = app(OmrBundlePackage::class)->extractAndValidate($result['zip_path']);
        expect($unpacked['manifest']['template']['registration_mode'])->toBe('document_bound')
            ->and($unpacked['manifest']['template']['qr_mode'])->toBe('qr_disabled');

        $imported = app(ImportAnswerSheetBundle::class)->handle($result['zip_path'], $actor);
        expect($imported->registration_mode)->toBe(OmrRegistrationMode::DocumentBound)
            ->and($imported->qr_mode)->toBe(OmrQrMode::Disabled)
            ->and($imported->marker_profile_id)->toBeNull();
    } finally {
        @unlink($result['zip_path']);
    }
});

test('import fails closed when archive member is tampered with or corrupted', function (): void {
    Storage::fake('local');
    $actor = $this->actor;
    $draft = createBundleTestDraft($actor, 'TAMPER-TEST-1');

    $exportAction = app(ExportAnswerSheetBundle::class);
    $result = $exportAction->handle($draft, $actor);

    // Tamper with zip file by modifying an internal file
    $zip = new ZipArchive;
    $zip->open($result['zip_path']);
    $zip->addFromString('native/template.json', '{"corrupted": true}'); // Corrupt hash
    $zip->close();

    $uploadedZip = new UploadedFile($result['zip_path'], $result['filename'], 'application/zip', null, true);

    $importAction = app(ImportAnswerSheetBundle::class);

    expect(fn () => $importAction->handle($uploadedZip, $actor))
        ->toThrow(InvalidArgumentException::class, 'SHA-256 hash mismatch');

    @unlink($result['zip_path']);
});

test('import rejects archives containing zip-slip path traversals', function (): void {
    $tempZip = tempnam(sys_get_temp_dir(), 'test_slip_');
    $zip = new ZipArchive;
    $zip->open($tempZip, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('manifest.json', json_encode(['schema_version' => OmrBundlePackage::SCHEMA_VERSION, 'files' => ['../malicious.json' => ['sha256' => 'abc']]]));
    $zip->addFromString('../malicious.json', 'evil');
    $zip->close();

    $package = app(OmrBundlePackage::class);

    expect(fn () => $package->extractAndValidate($tempZip))
        ->toThrow(InvalidArgumentException::class, 'bundle_path_traversal');

    @unlink($tempZip);
});

test('import endpoint queues a private durable operation instead of materializing synchronously', function (): void {
    Storage::fake('local');
    Storage::fake('sensitive');
    Queue::fake();
    $actor = $this->actor;
    $draft = createBundleTestDraft($actor, 'HTTP-IMPORT-TEST');

    $result = app(ExportAnswerSheetBundle::class)->handle($draft, $actor);
    $beforeDrafts = OmrTemplateAuthoringDraft::query()->count();
    $uploadedZip = new UploadedFile($result['zip_path'], $result['filename'], 'application/zip', null, true);

    $response = actingAsAdmissionSupportStaff($this, $this->actor)
        ->post(route('setup.exam-materials.answer-sheets.import'), [
            'bundle' => $uploadedZip,
        ]);

    $operation = AnswerSheetImportOperation::query()->sole();
    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('setup.exam-materials.answer-sheets.imports.show', ['operation' => $operation->id]));
    expect($operation->phase)->toBe(AnswerSheetImportOperation::PHASE_QUEUED)
        ->and(Storage::disk('sensitive')->exists($operation->archive_path))->toBeTrue()
        ->and(OmrTemplateAuthoringDraft::query()->count())->toBe($beforeDrafts);

    Queue::assertPushed(InspectAnswerSheetImportJob::class, fn (InspectAnswerSheetImportJob $job): bool => $job->operationId === $operation->id
    );

    @unlink($result['zip_path']);
});

test('portable multi-page sheets preserve geometry and remap only asset references', function (int $pageCount): void {
    Storage::fake('local');
    $draft = createBundleTestDraft($this->actor, 'MULTIPAGE-'.$pageCount);
    $store = app(OmrTemplateAssetStore::class);
    $pages = [];
    $originals = [];
    for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
        $raster = $store->putDraftAsset($draft, OmrTemplateAsset::KIND_RASTER_PAGE, 'page-'.$pageNo, 'image/png', $pageNo);
        $pages[] = ['page_no' => $pageNo, 'asset_id' => $raster->id, 'original_asset_id' => $raster->id];
        $originals[$pageNo] = $raster->id;
        if ($pageNo > 1) {
            $store->putDraftAsset($draft, OmrTemplateAsset::KIND_REFERENCE_PAGE, '<svg xmlns="http://www.w3.org/2000/svg"><text>'.$pageNo.'</text></svg>', 'image/svg+xml', $pageNo);
            $store->putDraftAsset($draft, OmrTemplateAsset::KIND_DIGITAL_PROOF, 'proof-'.$pageNo, 'image/png', $pageNo);
        }
    }
    $numericCollision = $pages[0]['asset_id'];
    $authoring = [
        'pages' => $pages,
        'source' => ['metadata' => ['original_asset_ids' => $originals]],
        'layout' => ['marginMm' => $numericCollision],
        'components' => [['id' => (string) $numericCollision, 'count' => $numericCollision, 'x' => $numericCollision]],
    ];
    $draft->forceFill(['requested_page_count' => $pageCount, 'authoring_json' => $authoring])->save();
    $result = app(ExportAnswerSheetBundle::class)->handle($draft, $this->actor);
    try {
        $imported = app(ImportAnswerSheetBundle::class)->handle($result['zip_path'], $this->actor);
        expect($imported->requested_page_count)->toBe($pageCount)
            ->and($imported->authoring_json['layout'])->toBe($authoring['layout'])
            ->and($imported->authoring_json['components'])->toBe($authoring['components']);
        foreach ($imported->authoring_json['pages'] as $index => $page) {
            $pageNo = $index + 1;
            $asset = $imported->assets()->findOrFail($page['asset_id']);
            expect($page['page_no'])->toBe($pageNo)
                ->and($page['asset_id'])->not->toBe($pages[$index]['asset_id'])
                ->and($page['original_asset_id'])->toBe($page['asset_id'])
                ->and($imported->authoring_json['source']['metadata']['original_asset_ids'][$pageNo])->toBe($page['asset_id'])
                ->and(Storage::disk($asset->disk)->get($asset->path))->toBe('page-'.$pageNo);
            $reference = $imported->assets()->where('kind', OmrTemplateAsset::KIND_REFERENCE_PAGE)->where('page_no', $pageNo)->sole();
            expect(Storage::disk($reference->disk)->get($reference->path))->toContain('<svg');
            $proof = $imported->assets()->where('kind', OmrTemplateAsset::KIND_DIGITAL_PROOF)->where('page_no', $pageNo)->sole();
            expect(Storage::disk($proof->disk)->get($proof->path))->toBe($pageNo === 1 ? 'mock-png-proof-bytes' : 'proof-'.$pageNo);
        }
    } finally {
        @unlink($result['zip_path']);
    }
})->with([2, 4]);

test('import endpoint rejects missing files and non-zip documents without creating a draft', function (): void {
    Storage::fake('local');
    $before = OmrTemplateAuthoringDraft::query()->count();
    actingAsAdmissionSupportStaff($this, $this->actor)
        ->post(route('setup.exam-materials.answer-sheets.import'), [])
        ->assertSessionHasErrors('bundle');
    actingAsAdmissionSupportStaff($this, $this->actor)
        ->post(route('setup.exam-materials.answer-sheets.import'), [
            'bundle' => UploadedFile::fake()->createWithContent('sheet.json', '{"pages":[]}'),
        ])->assertSessionHasErrors('bundle');
    expect(OmrTemplateAuthoringDraft::query()->count())->toBe($before);
});

test('import endpoint denies staff without OMR authority before any material is written', function (): void {
    $before = OmrTemplateAuthoringDraft::query()->count();
    $clerk = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    actingAsAdmissionSupportStaff($this, $clerk)
        ->post(route('setup.exam-materials.answer-sheets.import'))
        ->assertForbidden();
    expect(OmrTemplateAuthoringDraft::query()->count())->toBe($before);
});

test('legacy backups with misplaced asset tokens are rejected before any assets or drafts are written', function (): void {
    Storage::fake('local');
    $draft = createBundleTestDraft($this->actor, 'LEGACY-DAMAGED');
    $result = app(ExportAnswerSheetBundle::class)->handle($draft, $this->actor);
    $package = app(OmrBundlePackage::class);
    $unpacked = $package->extractAndValidate($result['zip_path']);
    $files = $unpacked['files'];
    $files['authoring.json'] = json_encode(['pages' => [['width_mm' => 'logical:native_manifest']]], JSON_THROW_ON_ERROR);
    $damaged = $package->createArchive($files, $unpacked['manifest']);
    $beforeDrafts = OmrTemplateAuthoringDraft::query()->count();
    $beforeAssets = OmrTemplateAsset::query()->count();
    $beforeFiles = Storage::disk('local')->allFiles();
    try {
        expect(fn () => app(ImportAnswerSheetBundle::class)->handle($damaged, $this->actor))
            ->toThrow(InvalidArgumentException::class, 'damaged numeric or text fields');
        expect(OmrTemplateAuthoringDraft::query()->count())->toBe($beforeDrafts)
            ->and(OmrTemplateAsset::query()->count())->toBe($beforeAssets)
            ->and(Storage::disk('local')->allFiles())->toBe($beforeFiles);
    } finally {
        @unlink($result['zip_path']);
        @unlink($damaged);
        $package->cleanDirectory($unpacked['temp_dir']);
        @rmdir($unpacked['temp_dir']);
    }
});
