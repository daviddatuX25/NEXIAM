<?php

use App\Assessment\Omr\NativeBundle;
use App\Assessment\Omr\NativeManifestDeriver;

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
