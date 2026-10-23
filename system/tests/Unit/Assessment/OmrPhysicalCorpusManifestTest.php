<?php

use App\Assessment\Omr\Qualification\OmrPhysicalCorpusManifest;
use App\Assessment\Omr\Qualification\OmrPhysicalCorpusRunner;

function writePhysicalCorpusManifest(array $captures, string $mode = 'development'): array
{
    $directory = sys_get_temp_dir().'/nexiam-omr-corpus-'.bin2hex(random_bytes(6));
    mkdir($directory);
    $basePng = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
    $mappedCaptures = [];
    foreach ($captures as $index => $capture) {
        $id = (string) ($capture['id'] ?? "capture-{$index}");
        $fileName = 'capture_'.preg_replace('/[^a-zA-Z0-9_\-]/', '_', $id).'.png';
        $filePath = $directory.'/'.$fileName;
        if (! file_exists($filePath)) {
            file_put_contents($filePath, $basePng.pack('N', $index).hash('sha256', (string) $id));
        }
        $hash = hash_file('sha256', $filePath);
        $mappedCaptures[] = [
            'path' => $fileName,
            'sha256' => $hash,
            'source' => 'real_capture',
            'synthetic' => false,
            'page_no' => 1,
            'conditions' => [],
            ...$capture,
        ];
    }
    $manifestPath = $directory.'/manifest.json';
    file_put_contents($manifestPath, json_encode([
        'contract' => OmrPhysicalCorpusManifest::CONTRACT,
        'corpus_id' => 'test-corpus',
        'mode' => $mode,
        'strategy' => OmrPhysicalCorpusRunner::strategy(),
        'captures' => $mappedCaptures,
    ], JSON_THROW_ON_ERROR));

    return [$manifestPath, $directory];
}

function removePhysicalCorpusFixture(string $directory): void
{
    if (is_dir($directory)) {
        foreach (glob($directory.'/*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        rmdir($directory);
    }
}

test('development manifests can exercise retained regressions but cannot qualify production', function () {
    [$manifestPath, $directory] = writePhysicalCorpusManifest([[
        'id' => 'regression-001',
        'classification' => 'regression_only',
        'expected_outcome' => 'reject',
        'method' => 'hand_shaded',
        'medium' => 'hb_2_pencil',
    ]]);

    try {
        $manifest = OmrPhysicalCorpusManifest::fromPath($manifestPath);

        expect($manifest->productionCoverageComplete())->toBeFalse()
            ->and($manifest->coverageIssues)->toBe(['development_corpus_not_qualification_evidence'])
            ->and($manifest->captures[0]['id'])->toBe('regression-001');
    } finally {
        removePhysicalCorpusFixture($directory);
    }
});

test('capture bytes are bound to the declared content hash', function () {
    [$manifestPath, $directory] = writePhysicalCorpusManifest([[
        'id' => 'capture-001',
        'classification' => 'positive',
        'expected_outcome' => 'exact',
        'method' => 'hand_shaded',
        'medium' => 'hb_2_pencil',
        'sha256' => str_repeat('0', 64),
    ]]);

    try {
        expect(fn () => OmrPhysicalCorpusManifest::fromPath($manifestPath))
            ->toThrow(InvalidArgumentException::class, 'qualification_capture_hash_mismatch:capture-001');
    } finally {
        removePhysicalCorpusFixture($directory);
    }
});

function physicalProductionCaptures(): array
{
    $captures = [];
    foreach (['phone_low', 'phone_mid', 'phone_high'] as $device) {
        foreach (['hb_2_pencil', 'dark_black_pen'] as $medium) {
            foreach (['normal', 'faint'] as $strength) {
                foreach (['even', 'directional_shadow'] as $lighting) {
                    foreach (['near_square', 'mild_perspective'] as $geometry) {
                        $captures[] = [
                            'id' => implode('-', [$device, $medium, $strength, $lighting, $geometry]),
                            'classification' => 'positive',
                            'expected_outcome' => 'exact',
                            'method' => 'hand_shaded',
                            'medium' => $medium,
                            'device_class' => $device,
                            'mark_strength' => $strength,
                            'lighting' => $lighting,
                            'geometry' => $geometry,
                            'expected_vector' => ['Q1' => 'A'],
                            'expected_identity' => ['Candidate' => '1'],
                        ];
                    }
                }
            }
        }
    }
    foreach ([
        'glare',
        'motion_blur',
        'jpeg_compression',
        'difficult_background',
        'rotated',
        'exif_orientation',
        'erased_mark',
        'double_mark',
        'missing_fiducial',
        'covered_fiducial',
        'partial_sheet',
        'wrong_sheet',
    ] as $index => $condition) {
        $captures[] = [
            'id' => sprintf('adversarial-%02d', $index + 1),
            'classification' => 'adversarial',
            'expected_outcome' => in_array($condition, ['erased_mark', 'double_mark'], true) ? 'review' : 'reject',
            'method' => 'hand_shaded',
            'medium' => 'hb_2_pencil',
            'conditions' => [$condition],
        ];
    }

    return $captures;
}

test('production coverage requires the complete positive and adversarial matrix', function () {
    $captures = physicalProductionCaptures();
    [$manifestPath, $directory] = writePhysicalCorpusManifest($captures, 'production');

    try {
        $manifest = OmrPhysicalCorpusManifest::fromPath($manifestPath);

        expect($manifest->productionCoverageComplete())->toBeTrue()
            ->and($manifest->coverageIssues)->toBeEmpty()
            ->and($manifest->captures)->toHaveCount(60);
    } finally {
        removePhysicalCorpusFixture($directory);
    }
});

test('production coverage rejects a replaced Cartesian matrix cell', function () {
    $captures = physicalProductionCaptures();
    $captures[0]['mark_strength'] = 'extra_dark';
    [$manifestPath, $directory] = writePhysicalCorpusManifest($captures, 'production');

    try {
        $manifest = OmrPhysicalCorpusManifest::fromPath($manifestPath);

        expect($manifest->productionCoverageComplete())->toBeFalse()
            ->and($manifest->coverageIssues)->toContain('positive_matrix_incomplete');
    } finally {
        removePhysicalCorpusFixture($directory);
    }
});

test('adversarial conditions enforce their safe expected outcomes', function () {
    $captures = physicalProductionCaptures();
    $captures[48]['expected_outcome'] = 'review';
    [$manifestPath, $directory] = writePhysicalCorpusManifest($captures, 'production');

    try {
        expect(fn () => OmrPhysicalCorpusManifest::fromPath($manifestPath))
            ->toThrow(InvalidArgumentException::class, 'qualification_capture_outcome_invalid');
    } finally {
        removePhysicalCorpusFixture($directory);
    }
});

test('each adversarial capture represents exactly one matrix condition', function () {
    $captures = physicalProductionCaptures();
    $captures[48]['conditions'] = ['glare', 'motion_blur'];
    [$manifestPath, $directory] = writePhysicalCorpusManifest($captures, 'production');

    try {
        expect(fn () => OmrPhysicalCorpusManifest::fromPath($manifestPath))
            ->toThrow(InvalidArgumentException::class, 'qualification_capture_outcome_invalid');
    } finally {
        removePhysicalCorpusFixture($directory);
    }
});

test('production coverage rejects manifests containing duplicate capture bytes', function () {
    $captures = physicalProductionCaptures();
    [$manifestPath, $directory] = writePhysicalCorpusManifest($captures, 'production');

    try {
        $decoded = json_decode((string) file_get_contents($manifestPath), true, 64, JSON_THROW_ON_ERROR);
        $decoded['captures'][1]['path'] = $decoded['captures'][0]['path'];
        $decoded['captures'][1]['sha256'] = $decoded['captures'][0]['sha256'];
        file_put_contents($manifestPath, json_encode($decoded, JSON_THROW_ON_ERROR));

        $manifest = OmrPhysicalCorpusManifest::fromPath($manifestPath);

        expect($manifest->productionCoverageComplete())->toBeFalse()
            ->and($manifest->coverageIssues)->toContain('qualification_capture_bytes_must_be_distinct');
    } finally {
        removePhysicalCorpusFixture($directory);
    }
});

test('uploaded_original corpus manifest requires supplier_blank and supplier_hand_marked per page across three devices and document-bound adversarial cases', function () {
    $captures = [
        [
            'id' => 'sup-p1-blank-d1',
            'classification' => 'positive',
            'expected_outcome' => 'exact',
            'method' => 'supplier_blank',
            'medium' => null,
            'page_no' => 1,
            'device_class' => 'ios_phone',
            'source' => 'real_capture',
            'conditions' => [],
        ],
        [
            'id' => 'sup-p1-hand-d2',
            'classification' => 'positive',
            'expected_outcome' => 'exact',
            'method' => 'supplier_hand_marked',
            'medium' => 'hb_2_pencil',
            'page_no' => 1,
            'device_class' => 'android_midrange',
            'source' => 'real_capture',
            'conditions' => [],
        ],
        [
            'id' => 'sup-p2-blank-d3',
            'classification' => 'positive',
            'expected_outcome' => 'exact',
            'method' => 'supplier_blank',
            'medium' => null,
            'page_no' => 2,
            'device_class' => 'flatbed_scanner',
            'source' => 'real_capture',
            'conditions' => [],
        ],
    ];
    [$manifestPath, $directory] = writePhysicalCorpusManifest($captures, 'production');

    try {
        $decoded = json_decode((string) file_get_contents($manifestPath), true, 64, JSON_THROW_ON_ERROR);
        $decoded['source_kind'] = 'uploaded_original';
        $decoded['page_count'] = 2;
        file_put_contents($manifestPath, json_encode($decoded, JSON_THROW_ON_ERROR));

        $manifest = OmrPhysicalCorpusManifest::fromPath($manifestPath);

        expect($manifest->sourceKind)->toBe('uploaded_original')
            ->and($manifest->pageCount)->toBe(2)
            ->and($manifest->productionCoverageComplete())->toBeFalse()
            ->and($manifest->coverageIssues)->toContain('supplier_hand_marked_missing_page_2')
            ->and($manifest->coverageIssues)->toContain('adversarial_matrix_incomplete');
    } finally {
        removePhysicalCorpusFixture($directory);
    }
});
