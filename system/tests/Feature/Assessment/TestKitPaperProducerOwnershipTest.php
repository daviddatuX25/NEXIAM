<?php

use App\Assessment\Actions\Omr\ActivateOmrConfiguration;
use App\Assessment\Actions\Omr\ImportAnswerSheetBundle;
use App\Assessment\Actions\Omr\RegisterNativeBundle;
use App\Assessment\Actions\Omr\SaveAuthoredBookletVersion;
use App\Assessment\Actions\Omr\SaveAuthoredTemplateDraft;
use App\Assessment\Omr\Canonical\OmrBundlePackage;
use App\Assessment\Omr\Fiducial\OmrFiducial;
use App\Assessment\Omr\NativeBundle;
use App\Authorization\CapabilityCatalog;
use App\Models\Assessment\OmrAnswerSheetTemplate;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\Institution;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function paperProducerInstitution(string $code): Institution
{
    return Institution::query()->create(['code' => $code, 'name' => $code.' University']);
}

function paperProducerActor(Institution $institution, bool $authorized = true): User
{
    $actor = User::factory()->create();
    $actor->staffProfile()->create([
        'institution_id' => $institution->id,
        'office' => 'guidance',
        'status' => StaffProfile::STATUS_ACTIVE,
    ]);
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_SCORING_OMR, 'web');
    if ($authorized) {
        $actor->givePermissionTo(CapabilityCatalog::GUIDANCE_SCORING_OMR);
    }

    return $actor;
}
function paperProducerSeedAreas(): void
{
    foreach ([
        'PROFILE_VERBAL' => 'Profile Verbal Reasoning',
        'PROFILE_QUANT' => 'Profile Quantitative Reasoning',
        'LOGIC' => 'General Ability',
        'SPATIAL' => 'Spatial Ability',
        'MEMORY' => 'Memory',
        'LANGUAGE' => 'Language Proficiency',
    ] as $code => $name) {
        AptitudeArea::query()->firstOrCreate(
            ['code' => $code],
            ['name' => $name, 'version' => 1, 'scoring_method' => 'count_correct', 'max_items' => 25, 'is_active' => true],
        );
    }
}

function paperProducerMarkerBlueprint(): array
{
    return [
        'schema' => 'nexiam.omr.layout-blueprint.v1',
        'paper' => 'long',
        'columns' => 6,
        'identity' => ['applicant_number' => true, 'birthdate' => false, 'repeat_on_every_page' => true],
        'markers' => ['enabled' => true],
        'sections' => array_map(static fn (string $area, int $index): array => [
            'area_code' => $area,
            'question_from' => 1,
            'question_to' => 25,
            'option_count' => 4,
            'break_before' => $index === 0 ? null : 'next_column',
        ], ['PROFILE_VERBAL', 'PROFILE_QUANT', 'LOGIC', 'SPATIAL', 'MEMORY', 'LANGUAGE'], array_keys(['PROFILE_VERBAL', 'PROFILE_QUANT', 'LOGIC', 'SPATIAL', 'MEMORY', 'LANGUAGE'])),
    ];
}

test('authored paper producer requires its existing OMR capability without creating institutions', function (): void {
    Storage::fake('local');
    $institution = paperProducerInstitution('PAPER-PRODUCER-DENIED');
    $actor = paperProducerActor($institution, authorized: false);

    expect(fn () => app(SaveAuthoredTemplateDraft::class)->handle(
        'DENIED-PAPER-TEMPLATE',
        'Denied paper template',
        [],
        $actor,
    ))->toThrow(AuthorizationException::class);

    expect(OmrAnswerSheetTemplate::query()->exists())->toBeFalse()
        ->and(OmrTemplateAuthoringDraft::query()->exists())->toBeFalse()
        ->and(Institution::query()->count())->toBe(1);
});

test('authored paper bundles with the same code are institution-owned and read marker bytes from their own scoped directory', function (): void {
    Storage::fake('local');
    paperProducerSeedAreas();
    $firstInstitution = paperProducerInstitution('PAPER-PRODUCER-ONE');
    $secondInstitution = paperProducerInstitution('PAPER-PRODUCER-TWO');
    $firstActor = paperProducerActor($firstInstitution);
    $secondActor = paperProducerActor($secondInstitution);

    $save = app(SaveAuthoredTemplateDraft::class);
    $firstVersion = $save->handle('SHARED-PAPER-CODE', 'First institution sheet', [], $firstActor, null, true, paperProducerMarkerBlueprint());
    $secondVersion = $save->handle('SHARED-PAPER-CODE', 'Second institution sheet', [], $secondActor, null, true, paperProducerMarkerBlueprint());

    $firstFamily = OmrAnswerSheetTemplate::query()->findOrFail($firstVersion->template_id);
    $secondFamily = OmrAnswerSheetTemplate::query()->findOrFail($secondVersion->template_id);
    $firstPath = $firstVersion->native_bundle_json['relative_dir'];
    $secondPath = $secondVersion->native_bundle_json['relative_dir'];
    $firstBundle = NativeBundle::loadFromDirectory(Storage::disk('local')->path($firstPath), 'SHARED-PAPER-CODE', (int) $firstVersion->version);
    $secondBundle = NativeBundle::loadFromDirectory(Storage::disk('local')->path($secondPath), 'SHARED-PAPER-CODE', (int) $secondVersion->version);

    expect($firstFamily->institution_id)->toBe($firstInstitution->id)
        ->and($secondFamily->institution_id)->toBe($secondInstitution->id)
        ->and($firstFamily->is_synthetic)->toBeTrue()
        ->and($secondFamily->is_synthetic)->toBeTrue()
        ->and($firstPath)->toBe(sprintf('omr-bundles/institutions/%d/templates/%d/v1', $firstInstitution->id, $firstFamily->id))
        ->and($secondPath)->toBe(sprintf('omr-bundles/institutions/%d/templates/%d/v1', $secondInstitution->id, $secondFamily->id))
        ->and($firstPath)->not->toBe($secondPath)
        ->and($firstBundle->assetBytes('omr_marker.jpg'))->toBe(OmrFiducial::assetBytes())
        ->and($secondBundle->assetBytes('omr_marker.jpg'))->toBe(OmrFiducial::assetBytes())
        ->and(Institution::query()->count())->toBe(2);
});

test('activation retains and uses the exact institution-owned native bundle path', function (): void {
    Storage::fake('local');
    paperProducerSeedAreas();
    $institution = paperProducerInstitution('PAPER-ACTIVATION-SCOPED');
    $actor = paperProducerActor($institution);
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_BOOKLET_MANAGE, 'web');
    $actor->givePermissionTo(CapabilityCatalog::GUIDANCE_BOOKLET_MANAGE);
    $version = app(SaveAuthoredTemplateDraft::class)->handle(
        'SHARED-ACTIVATION-CODE',
        'Scoped activation sheet',
        [],
        $actor,
        null,
        true,
        paperProducerMarkerBlueprint(),
    );
    $path = $version->native_bundle_json['relative_dir'];
    $questions = [];
    $position = 1;
    foreach (paperProducerMarkerBlueprint()['sections'] as $section) {
        for ($number = 1; $number <= 25; $number++) {
            $questions[] = [
                'area_id' => (int) AptitudeArea::query()->where('code', $section['area_code'])->value('id'),
                'question_no' => $number,
                'position' => $position,
                'correct_option_code' => ['A', 'B', 'C', 'D'][($position - 1) % 4],
            ];
            $position++;
        }
    }
    $booklet = app(SaveAuthoredBookletVersion::class)->handle('PAPER-ACTIVATION-BOOKLET', 'Scoped booklet', $questions, $actor);

    $pair = app(ActivateOmrConfiguration::class)->handle($version->fresh(), $booklet, $actor);
    $activeVersion = $pair->templateVersion;

    expect($activeVersion->native_bundle_json['relative_dir'])->toBe($path)
        ->and(NativeBundle::loadFromDirectory(
            Storage::disk('local')->path($activeVersion->native_bundle_json['relative_dir']),
            'SHARED-ACTIVATION-CODE',
            (int) $activeVersion->version,
        )->assetBytes('omr_marker.jpg'))->toBe(OmrFiducial::assetBytes());
});
test('answer-sheet imports use institution-local family codes and synthetic provenance on their initial draft', function (): void {
    Storage::fake('local');
    $firstInstitution = paperProducerInstitution('PAPER-IMPORT-ONE');
    $secondInstitution = paperProducerInstitution('PAPER-IMPORT-TWO');
    $firstActor = paperProducerActor($firstInstitution);
    $secondActor = paperProducerActor($secondInstitution);
    $archive = app(OmrBundlePackage::class)->createArchive(
        ['authoring.json' => json_encode(['components' => []], JSON_THROW_ON_ERROR)],
        ['template' => [
            'code' => 'SHARED-IMPORT-CODE',
            'display_name' => 'Shared imported sheet',
            'page_count' => 1,
            'registration_mode' => 'document_bound',
            'qr_mode' => 'qr_disabled',
        ]],
    );

    try {
        $import = app(ImportAnswerSheetBundle::class);
        $firstDraft = $import->handle($archive, $firstActor);
        $secondDraft = $import->handle($archive, $secondActor);

        expect($firstDraft->institution_id)->toBe($firstInstitution->id)
            ->and($secondDraft->institution_id)->toBe($secondInstitution->id)
            ->and($firstDraft->code)->toBe('SHARED-IMPORT-CODE')
            ->and($secondDraft->code)->toBe('SHARED-IMPORT-CODE')
            ->and($firstDraft->is_synthetic)->toBeTrue()
            ->and($secondDraft->is_synthetic)->toBeTrue()
            ->and($firstDraft->template->institution_id)->toBe($firstInstitution->id)
            ->and($secondDraft->template->institution_id)->toBe($secondInstitution->id);
    } finally {
        @unlink($archive);
    }
});

test('native registration owns a same-code family and bundle under its authorized institution', function (): void {
    Storage::fake('local');
    $sourceInstitution = paperProducerInstitution('PAPER-REGISTER-SOURCE');
    $targetInstitution = paperProducerInstitution('PAPER-REGISTER-TARGET');
    $otherInstitution = paperProducerInstitution('PAPER-REGISTER-OTHER');
    $sourceActor = paperProducerActor($sourceInstitution);
    $targetActor = paperProducerActor($targetInstitution);
    $otherActor = paperProducerActor($otherInstitution);
    paperProducerSeedAreas();
    $sourceVersion = app(SaveAuthoredTemplateDraft::class)->handle(
        'REGISTER-SOURCE-BUNDLE',
        'Source native bundle',
        [],
        $sourceActor,
        null,
        true,
        paperProducerMarkerBlueprint(),
    );
    $sourcePath = $sourceVersion->native_bundle_json['relative_dir'];
    $sourceBundle = NativeBundle::loadFromDirectory(
        Storage::disk('local')->path($sourcePath),
        'REGISTER-SOURCE-BUNDLE',
        (int) $sourceVersion->version,
    );
    $nativeFiles = [];
    foreach ($sourceBundle->fileHashes as $name => $_hash) {
        $nativeFiles[$name] = Storage::disk('local')->get($sourcePath.'/'.$name);
    }
    $nativeFiles['manifest.json'] = Storage::disk('local')->get($sourcePath.'/manifest.json');
    $manifest = json_decode($nativeFiles['manifest.json'], true, 512, JSON_THROW_ON_ERROR);
    $manifest['template_code'] = 'REGISTERED-SHEET-CODE';
    $manifest['synthetic'] = false;
    $nativeFiles['manifest.json'] = json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

    $version = app(RegisterNativeBundle::class)->handle('REGISTERED-SHEET-CODE', $nativeFiles, $targetActor);
    $sameCodeOtherInstitution = app(RegisterNativeBundle::class)->handle('REGISTERED-SHEET-CODE', $nativeFiles, $otherActor);
    $family = OmrAnswerSheetTemplate::query()->findOrFail($version->template_id);
    $otherFamily = OmrAnswerSheetTemplate::query()->findOrFail($sameCodeOtherInstitution->template_id);
    $path = $version->native_bundle_json['relative_dir'];
    $otherPath = $sameCodeOtherInstitution->native_bundle_json['relative_dir'];
    $registeredBundle = NativeBundle::loadFromDirectory(
        Storage::disk('local')->path($path),
        'REGISTERED-SHEET-CODE',
        (int) $version->version,
    );
    $otherBundle = NativeBundle::loadFromDirectory(
        Storage::disk('local')->path($otherPath),
        'REGISTERED-SHEET-CODE',
        (int) $sameCodeOtherInstitution->version,
    );

    expect($family->institution_id)->toBe($targetInstitution->id)
        ->and($otherFamily->institution_id)->toBe($otherInstitution->id)
        ->and($family->is_synthetic)->toBeFalse()
        ->and($otherFamily->is_synthetic)->toBeFalse()
        ->and($path)->toBe(sprintf('omr-bundles/institutions/%d/templates/%d/v1', $targetInstitution->id, $family->id))
        ->and($otherPath)->toBe(sprintf('omr-bundles/institutions/%d/templates/%d/v1', $otherInstitution->id, $otherFamily->id))
        ->and($path)->not->toBe($otherPath)
        ->and($registeredBundle->assetBytes('omr_marker.jpg'))->toBe(OmrFiducial::assetBytes())
        ->and($otherBundle->assetBytes('omr_marker.jpg'))->toBe(OmrFiducial::assetBytes())
        ->and(Institution::query()->count())->toBe(3);
});
test('all paper producers deny actors without the existing OMR capability before creating owner rows', function (): void {
    Storage::fake('local');
    $institution = paperProducerInstitution('PAPER-PRODUCER-DENIALS');
    $actor = paperProducerActor($institution, authorized: false);
    $pinned = config('omr.omrchecker');
    $manifest = json_encode([
        'template_code' => 'DENIED-REGISTER-CODE',
        'engine' => [
            'name' => $pinned['engine_name'],
            'commit' => $pinned['engine_commit'],
            'patch_set' => $pinned['patch_identity'],
        ],
    ], JSON_THROW_ON_ERROR);

    expect(fn () => app(ImportAnswerSheetBundle::class)->handle('/nonexistent/portable-bundle.zip', $actor))
        ->toThrow(AuthorizationException::class)
        ->and(fn () => app(RegisterNativeBundle::class)->handle('DENIED-REGISTER-CODE', [
            'template.json' => '{}',
            'config.json' => '{}',
            'manifest.json' => $manifest,
        ], $actor))->toThrow(AuthorizationException::class)
        ->and(fn () => app(SaveAuthoredTemplateDraft::class)->handle(
            'DENIED-AUTHORED-CODE',
            'Denied authored sheet',
            [],
            $actor,
        ))->toThrow(AuthorizationException::class);

    expect(OmrAnswerSheetTemplate::query()->exists())->toBeFalse()
        ->and(OmrTemplateAuthoringDraft::query()->exists())->toBeFalse()
        ->and(Institution::query()->count())->toBe(1);
});
test('paper producers reject an existing family with conflicting provenance instead of relabeling it', function (): void {
    Storage::fake('local');
    $institution = paperProducerInstitution('PAPER-PRODUCER-PROVENANCE');
    $actor = paperProducerActor($institution);
    $syntheticTemplate = OmrAnswerSheetTemplate::query()->create([
        'institution_id' => $institution->id,
        'code' => 'NATIVE-PROVENANCE-CONFLICT',
        'name' => 'Synthetic family',
        'is_synthetic' => true,
    ]);
    $nonsyntheticTemplate = OmrAnswerSheetTemplate::query()->create([
        'institution_id' => $institution->id,
        'code' => 'AUTHORED-PROVENANCE-CONFLICT',
        'name' => 'Native family',
        'is_synthetic' => false,
    ]);
    $pinned = config('omr.omrchecker');
    $nativeManifest = json_encode([
        'template_code' => 'NATIVE-PROVENANCE-CONFLICT',
        'synthetic' => false,
        'engine' => [
            'name' => $pinned['engine_name'],
            'commit' => $pinned['engine_commit'],
            'patch_set' => $pinned['patch_identity'],
        ],
    ], JSON_THROW_ON_ERROR);

    expect(fn () => app(RegisterNativeBundle::class)->handle('NATIVE-PROVENANCE-CONFLICT', [
        'template.json' => '{}',
        'config.json' => '{}',
        'manifest.json' => $nativeManifest,
    ], $actor))->toThrow(RuntimeException::class, 'provenance')
        ->and(fn () => app(SaveAuthoredTemplateDraft::class)->handle(
            'AUTHORED-PROVENANCE-CONFLICT',
            'Conflicting authored template',
            [],
            $actor,
        ))->toThrow(RuntimeException::class, 'provenance');

    expect($syntheticTemplate->fresh()->is_synthetic)->toBeTrue()
        ->and($nonsyntheticTemplate->fresh()->is_synthetic)->toBeFalse()
        ->and(OmrAnswerSheetTemplateVersion::query()->exists())->toBeFalse()
        ->and(Institution::query()->count())->toBe(1);
});
