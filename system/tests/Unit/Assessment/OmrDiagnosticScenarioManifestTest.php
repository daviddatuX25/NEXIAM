<?php

use App\Assessment\Omr\Diagnostics\OmrDiagnosticScenarioManifest;
use App\Assessment\Omr\Diagnostics\OmrManifestBubbleShader;

function scenarioManifestReviewGeometry(int $itemCount = 18): array
{
    $fields = [];
    for ($item = 1; $item <= $itemCount; $item++) {
        foreach (['A', 'B', 'C', 'D'] as $optionIndex => $option) {
            $fields[] = [
                'page' => 1,
                'block' => 'DIAGNOSTIC',
                'label' => 'DIAGNOSTIC_Q_'.$item,
                'option' => $option,
                'pixel' => [
                    'x' => 100 + ($optionIndex * 60),
                    'y' => 100 + (($item - 1) * 70),
                    'w' => 40,
                    'h' => 40,
                ],
                'region' => [
                    'x' => (100 + ($optionIndex * 60)) / 2480,
                    'y' => (100 + (($item - 1) * 70)) / 3508,
                    'w' => 40 / 2480,
                    'h' => 40 / 3508,
                ],
            ];
        }
    }

    return [
        'manifest_version' => 'nexiam.omr.review-manifest.v1',
        'template_code' => 'DIAGNOSTIC',
        'template_version' => 1,
        'page_count' => 1,
        'fields' => $fields,
    ];
}

function scenarioManifestContext(): array
{
    return [
        'review_manifest' => scenarioManifestReviewGeometry(),
        'target_id' => 'scenario-fixture',
        'target_kind' => 'compiled_version',
        'geometry_hash' => str_repeat('a', 64),
        'artifact_hash' => str_repeat('b', 64),
        'profile_hash' => str_repeat('c', 64),
        'native_bundle_hash' => str_repeat('d', 64),
        'config_hash' => str_repeat('e', 64),
        'mapping_hash' => str_repeat('f', 64),
        'renderer_version' => OmrManifestBubbleShader::RENDERER_VERSION,
    ];
}

test('scenario manifests canonicalize ordering and bind hashes to provenance', function (): void {
    $first = OmrDiagnosticScenarioManifest::quickRehearsalV1(scenarioManifestContext());
    $payload = $first->toArray();
    $payload['scenarios'] = array_reverse($payload['scenarios']);
    ksort($payload['provenance']);
    $second = OmrDiagnosticScenarioManifest::fromArray($payload);

    expect($second->canonicalJson())->toBe($first->canonicalJson())
        ->and($second->hash())->toBe($first->hash())
        ->and($first->scenarioIds())->toHaveCount(18);

    $changed = scenarioManifestContext();
    $changed['geometry_hash'] = str_repeat('9', 64);
    $different = OmrDiagnosticScenarioManifest::quickRehearsalV1($changed);
    expect($different->hash())->not->toBe($first->hash())
        ->and($different->scenarioIds())->not->toBe($first->scenarioIds());
});

test('quick rehearsal v1 is fixed, complete, and bounded', function (): void {
    $manifest = OmrDiagnosticScenarioManifest::quickRehearsalV1(scenarioManifestContext());
    $coverage = $manifest->coverage();

    expect($manifest->scenarios)->toHaveCount(18)
        ->and($manifest->toArray()['capture_variants'])->toHaveCount(4)
        ->and($coverage->isComplete())->toBeTrue()
        ->and($coverage->gaps)->toBeEmpty()
        ->and(array_column($manifest->scenarios, 'lane'))->toEqual(array_fill(0, 18, OmrDiagnosticScenarioManifest::LANE_GENERATED));
});

test('quick rehearsal v2 binds scenarios to sampled rows and their expected options', function (): void {
    $samples = [];
    $expected = [];
    for ($position = 18; $position >= 1; $position--) {
        $label = 'DIAGNOSTIC_Q_'.$position;
        $samples[] = ['page_no' => 1, 'label' => $label];
        $expected[$label] = ['A', 'B', 'C', 'D'][$position % 4];
    }

    $manifest = OmrDiagnosticScenarioManifest::quickRehearsalV2(
        scenarioManifestContext(),
        $samples,
        $expected,
        OmrDiagnosticScenarioManifest::LANE_HAND_SHADED,
    );

    expect($manifest->manifestVersion)->toBe(OmrDiagnosticScenarioManifest::MANIFEST_VERSION_V2)
        ->and($manifest->plan)->toBe(OmrDiagnosticScenarioManifest::QUICK_REHEARSAL_V2)
        ->and(array_column(array_column($manifest->scenarios, 'item'), 'label'))->toBe(array_column($samples, 'label'))
        ->and($manifest->coverage()->isComplete())->toBeTrue();

    foreach ($manifest->scenarios as $scenario) {
        $expectedOption = $expected[$scenario['item']['label']];
        expect($scenario['answer']['expected_option'])->toBe($expectedOption);
        if ($scenario['answer']['condition'] === OmrDiagnosticScenarioManifest::ANSWER_DEFINITE_MISMATCH) {
            expect($scenario['answer']['candidates'])->not->toContain($expectedOption);
        }
    }
});

test('quick rehearsal remains fixed when canonical geometry exposes fewer than eighteen unique items', function (): void {
    $context = scenarioManifestContext();
    $context['review_manifest'] = scenarioManifestReviewGeometry(3);

    $manifest = OmrDiagnosticScenarioManifest::quickRehearsalV1($context);

    expect($manifest->scenarios)->toHaveCount(18)
        ->and(array_values(array_unique(array_column(array_column($manifest->scenarios, 'item'), 'label'))))
        ->toBe(['DIAGNOSTIC_Q_1', 'DIAGNOSTIC_Q_2', 'DIAGNOSTIC_Q_3'])
        ->and($manifest->coverage()->isComplete())->toBeTrue();
});

test('full diagnostic plan reports targeted pair gaps when a scenario is removed', function (): void {
    $manifest = OmrDiagnosticScenarioManifest::fullDiagnosticV1(scenarioManifestContext());
    expect($manifest->coverage()->isComplete())->toBeTrue();

    $payload = $manifest->toArray();
    $payload['scenarios'] = array_values(array_filter(
        $payload['scenarios'],
        static fn (array $scenario): bool => $scenario['mark']['style'] !== OmrDiagnosticScenarioManifest::MARK_NORMAL_DARK
            || $scenario['answer']['condition'] !== OmrDiagnosticScenarioManifest::ANSWER_EXPECTED_MATCH,
    ));
    $incomplete = OmrDiagnosticScenarioManifest::fromArray($payload);
    $coverage = $incomplete->coverage();

    expect($coverage->isComplete())->toBeFalse()
        ->and($coverage->gaps)->toContain('pair:mark_answer:normal_dark|expected_match');
});

test('scenario validation fails closed for tampered identifiers and partial-page outcome', function (): void {
    $manifest = OmrDiagnosticScenarioManifest::quickRehearsalV1(scenarioManifestContext());
    $payload = $manifest->toArray();
    $payload['scenarios'][0]['id'] = 'SCN-00000000000000000000';

    expect(fn () => OmrDiagnosticScenarioManifest::fromArray($payload))
        ->toThrow(InvalidArgumentException::class, 'scenario_id_invalid');

    $payload = $manifest->toArray();
    $payload['scenarios'][17]['expected']['machine_class'] = OmrDiagnosticScenarioManifest::EXPECTED_REVIEW_REQUIRED;
    expect(fn () => OmrDiagnosticScenarioManifest::fromArray($payload))
        ->toThrow(InvalidArgumentException::class, 'scenario_expected_class_invalid');
});

test('all deterministic mark primitives return bounded repeatable operations', function (): void {
    $shader = new OmrManifestBubbleShader;
    $geometry = ['x' => 100, 'y' => 100, 'w' => 53, 'h' => 53];
    $neighbor = ['x' => 170, 'y' => 100, 'w' => 53, 'h' => 53];

    foreach (OmrManifestBubbleShader::PRIMITIVES as $primitive) {
        $parameters = $primitive === OmrManifestBubbleShader::PRIMITIVE_BETWEEN_BUBBLE
            ? ['neighbor' => $neighbor]
            : [];
        $first = $shader->primitive($primitive, $geometry, $parameters);
        $second = $shader->primitive($primitive, $geometry, $parameters);
        expect($second)->toBe($first)
            ->and($first['operations'])->not->toBeEmpty();
        foreach ($first['operations'] as $operation) {
            expect($operation['type'])->toBeIn(['ellipse', 'arc', 'line']);
        }
    }
});

test('scenario shader preserves every candidate and draws between-bubble marks', function (): void {
    $shader = new OmrManifestBubbleShader;
    $index = $shader->index([
        ['page' => 1, 'label' => 'Q_1', 'option' => 'A', 'pixel' => ['x' => 100, 'y' => 100, 'w' => 53, 'h' => 53]],
        ['page' => 1, 'label' => 'Q_1', 'option' => 'B', 'pixel' => ['x' => 170, 'y' => 100, 'w' => 53, 'h' => 53]],
        ['page' => 1, 'label' => 'Q_1', 'option' => 'C', 'pixel' => ['x' => 240, 'y' => 100, 'w' => 53, 'h' => 53]],
    ]);
    $image = imagecreatetruecolor(400, 300);
    $white = imagecolorallocate($image, 255, 255, 255);
    $black = imagecolorallocate($image, 0, 0, 0);
    imagefilledrectangle($image, 0, 0, 399, 299, $white);

    $shader->shadeScenario($image, $index, 1, [
        'item' => ['page_no' => 1, 'label' => 'Q_1'],
        'mark' => ['style' => OmrDiagnosticScenarioManifest::MARK_NORMAL_DARK, 'parameters' => []],
        'answer' => ['condition' => OmrDiagnosticScenarioManifest::ANSWER_TRIPLE, 'candidates' => ['A', 'B', 'C']],
    ], [], $black);
    expect(imagecolorat($image, 126, 126) & 0xFF)->toBeLessThan(30)
        ->and(imagecolorat($image, 196, 126) & 0xFF)->toBeLessThan(30)
        ->and(imagecolorat($image, 266, 126) & 0xFF)->toBeLessThan(30);

    imagefilledrectangle($image, 0, 0, 399, 299, $white);
    $shader->shadeScenario($image, $index, 1, [
        'item' => ['page_no' => 1, 'label' => 'Q_1'],
        'mark' => ['style' => OmrDiagnosticScenarioManifest::MARK_NORMAL_DARK, 'parameters' => []],
        'answer' => ['condition' => OmrDiagnosticScenarioManifest::ANSWER_BETWEEN_BUBBLE, 'candidates' => ['A', 'B']],
    ], [], $black);
    expect(imagecolorat($image, 161, 126) & 0xFF)->toBeLessThan(30);
    imagedestroy($image);
});
