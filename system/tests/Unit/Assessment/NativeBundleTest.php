<?php

use App\Assessment\Omr\NativeBundle;
use App\Assessment\Omr\NativeManifestDeriver;
use App\Models\Assessment\OmrAnswerSheetTemplate;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Foundation\Institution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/** @return array{version:OmrAnswerSheetTemplateVersion,directory:string,bundle:NativeBundle} */
function persistedNativeBundleFixture(string $institutionCode): array
{
    $institution = Institution::query()->create(['code' => $institutionCode, 'name' => $institutionCode]);
    $template = OmrAnswerSheetTemplate::factory()->create([
        'institution_id' => $institution->id,
        'code' => 'SYN-OMR-ONE-PAGE',
    ]);
    $bundle = NativeBundle::load('SYN-OMR-ONE-PAGE', 1);
    $directory = sprintf('omr-bundles/institutions/%d/templates/%d/v1', $institution->id, $template->id);
    $source = app_path('Assessment/Omr/NativeBundles/SYN-OMR-ONE-PAGE/v1');
    foreach (array_keys($bundle->fileHashes) as $name) {
        Storage::disk('local')->put($directory.'/'.$name, file_get_contents($source.'/'.$name));
    }
    $version = OmrAnswerSheetTemplateVersion::factory()->create([
        'template_id' => $template->id,
        'version' => 1,
        'status' => OmrAnswerSheetTemplateVersion::STATUS_COMPILED,
        'native_bundle_json' => [
            'relative_dir' => $directory,
            'files' => $bundle->fileHashes,
        ],
        'native_bundle_hash' => $bundle->bundleHash,
    ]);

    return ['version' => $version, 'directory' => $directory, 'bundle' => $bundle];
}

test('version loader selects the persisted institution template directory for shared code and version', function (): void {
    Storage::fake('local');
    $first = persistedNativeBundleFixture('NATIVE-OWNER-A');
    $second = persistedNativeBundleFixture('NATIVE-OWNER-B');
    $loadedFirst = NativeBundle::loadForVersion($first['version']);
    $loadedSecond = NativeBundle::loadForVersion($second['version']);

    expect($loadedFirst->fileHashes)->toBe($first['bundle']->fileHashes)
        ->and($loadedSecond->fileHashes)->toBe($second['bundle']->fileHashes)
        ->and($first['directory'])->not->toBe($second['directory']);
});

test('version loader rejects missing directories and tampered persisted bytes or hashes', function (): void {
    Storage::fake('local');
    $missing = persistedNativeBundleFixture('NATIVE-MISSING');
    Storage::disk('local')->deleteDirectory($missing['directory']);
    expect(fn () => NativeBundle::loadForVersion($missing['version']))
        ->toThrow(RuntimeException::class, 'directory');

    $tampered = persistedNativeBundleFixture('NATIVE-TAMPERED');
    Storage::disk('local')->put($tampered['directory'].'/config.json', '{"tampered":true}');
    expect(fn () => NativeBundle::loadForVersion($tampered['version']))
        ->toThrow(RuntimeException::class);

    $wrongHash = persistedNativeBundleFixture('NATIVE-HASH');
    DB::table('omr_answer_sheet_template_versions')->where('id', $wrongHash['version']->id)
        ->update(['native_bundle_hash' => hash('sha256', 'not-the-bundle')]);
    expect(fn () => NativeBundle::loadForVersion($wrongHash['version']))
        ->toThrow(RuntimeException::class);
});

test('version loader rejects owner namespace substitution and symlink escape', function (): void {
    Storage::fake('local');
    $owner = persistedNativeBundleFixture('NATIVE-PATH-OWNER');
    $foreign = persistedNativeBundleFixture('NATIVE-PATH-FOREIGN');
    DB::table('omr_answer_sheet_template_versions')->where('id', $owner['version']->id)
        ->update(['native_bundle_json' => json_encode([
            ...$owner['version']->native_bundle_json,
            'relative_dir' => $foreign['directory'],
        ])]);
    expect(fn () => NativeBundle::loadForVersion($owner['version']))
        ->toThrow(RuntimeException::class, 'owner and version');

    $symlink = persistedNativeBundleFixture('NATIVE-SYMLINK');
    Storage::disk('local')->deleteDirectory($symlink['directory']);
    $linkPath = Storage::disk('local')->path($symlink['directory']);
    mkdir(dirname($linkPath), 0777, true);
    symlink(Storage::disk('local')->path($foreign['directory']), $linkPath);
    expect(fn () => NativeBundle::loadForVersion($symlink['version']))
        ->toThrow(RuntimeException::class, 'escapes');
});

function syntheticNativeBundle(): NativeBundle
{
    return NativeBundle::load('SYN-OMR-ONE-PAGE', 1);
}

test('synthetic native bundle is immutable and hash addressed', function (): void {
    $bundle = syntheticNativeBundle();

    expect($bundle->code)->toBe('SYN-OMR-ONE-PAGE')
        ->and($bundle->version)->toBe(1)
        ->and($bundle->template['pageDimensions'])->toBe([2000, 5000])
        ->and($bundle->manifest['engine']['commit'])->toBe('de3f6982822895491be760325fd43e65e90fa293')
        ->and($bundle->manifest['synthetic'])->toBeTrue()
        ->and($bundle->fileHashes)->toHaveKeys(['config.json', 'manifest.json', 'reference.svg', 'template.json'])
        ->and($bundle->bundleHash)->toMatch('/^[a-f0-9]{64}$/');
});

test('native bundle manifest pins every non-self asset hash', function (): void {
    $bundle = syntheticNativeBundle();

    expect($bundle->manifest['files'])->toBe([
        'template.json' => $bundle->fileHashes['template.json'],
        'config.json' => $bundle->fileHashes['config.json'],
        'reference.svg' => $bundle->fileHashes['reference.svg'],
    ]);
});

test('native bundle expands every declared field label deterministically', function (): void {
    $bundle = syntheticNativeBundle();
    $labels = $bundle->stableFieldLabels();

    expect($labels)->toHaveCount(210)
        ->and($labels[0])->toBe('APPLICANT_NUMBER_1')
        ->and($labels[9])->toBe('APPLICANT_NUMBER_10')
        ->and($labels[10])->toBe('VERBAL_Q_1')
        ->and($labels[109])->toBe('VERBAL_Q_100')
        ->and($labels[110])->toBe('QUANT_Q_1')
        ->and($labels[209])->toBe('QUANT_Q_100')
        ->and(NativeBundle::expandLabel('DIGIT_0..DIGIT_9'))->toBe([
            'DIGIT_0', 'DIGIT_1', 'DIGIT_2', 'DIGIT_3', 'DIGIT_4',
            'DIGIT_5', 'DIGIT_6', 'DIGIT_7', 'DIGIT_8', 'DIGIT_9',
        ])
        ->and(NativeBundle::expandLabel('APPLICANT_NUMBER_1..10'))->toBe([
            'APPLICANT_NUMBER_1', 'APPLICANT_NUMBER_2', 'APPLICANT_NUMBER_3',
            'APPLICANT_NUMBER_4', 'APPLICANT_NUMBER_5', 'APPLICANT_NUMBER_6',
            'APPLICANT_NUMBER_7', 'APPLICANT_NUMBER_8', 'APPLICANT_NUMBER_9',
            'APPLICANT_NUMBER_10',
        ]);
});

test('A4 bundle v2 loads with paper-shaped dimensions and sectioned blocks', function (): void {
    $bundle = NativeBundle::load('SYN-OMR-ONE-PAGE', 2);

    expect($bundle->version)->toBe(2)
        ->and($bundle->template['pageDimensions'])->toBe([2000, 2828])
        ->and(array_keys($bundle->template['fieldBlocks']))
        ->toHaveCount(11)
        ->and($bundle->template['fieldBlocks'])->toHaveKeys([
            'ApplicantNumber',
            'VerbalPartA', 'VerbalPartB', 'VerbalPartC', 'VerbalPartD', 'VerbalPartE',
            'QuantPartA', 'QuantPartB', 'QuantPartC', 'QuantPartD', 'QuantPartE',
        ]);

    $labels = $bundle->stableFieldLabels();
    expect($labels)->toHaveCount(210)
        ->and(array_slice($labels, 10, 20))->toBe([
            'VERBAL_Q_1', 'VERBAL_Q_2', 'VERBAL_Q_3', 'VERBAL_Q_4', 'VERBAL_Q_5',
            'VERBAL_Q_6', 'VERBAL_Q_7', 'VERBAL_Q_8', 'VERBAL_Q_9', 'VERBAL_Q_10',
            'VERBAL_Q_11', 'VERBAL_Q_12', 'VERBAL_Q_13', 'VERBAL_Q_14', 'VERBAL_Q_15',
            'VERBAL_Q_16', 'VERBAL_Q_17', 'VERBAL_Q_18', 'VERBAL_Q_19', 'VERBAL_Q_20',
        ]);
});

test('A4 bundle v2 derives one in-bounds bubble per field deterministically', function (): void {
    $bundle = NativeBundle::load('SYN-OMR-ONE-PAGE', 2);
    $first = app(NativeManifestDeriver::class)->derive($bundle);
    $second = app(NativeManifestDeriver::class)->derive($bundle);

    expect($first['hash'])->toBe($second['hash'])
        ->and($first['manifest']['fields'])->toHaveCount(900);

    foreach ($first['manifest']['fields'] as $field) {
        $region = $field['region'];
        expect($region['x'])->toBeGreaterThanOrEqual(0.0)
            ->and($region['y'])->toBeGreaterThanOrEqual(0.0)
            ->and($region['x'] + $region['w'])->toBeLessThanOrEqual(1.0)
            ->and($region['y'] + $region['h'])->toBeLessThanOrEqual(1.0);
    }
});

test('native bundle rejects malformed field ranges', function (): void {
    expect(fn () => NativeBundle::expandLabel('Q_02..OTHER_04'))
        ->toThrow(RuntimeException::class);
});
