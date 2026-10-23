<?php

use App\Assessment\Omr\NativeBundle;
use App\Assessment\Omr\NativeManifestDeriver;

function derivedSyntheticManifest(): array
{
    return app(NativeManifestDeriver::class)->derive(NativeBundle::load('SYN-OMR-ONE-PAGE', 1));
}

test('native manifest derives one bubble per declared field and option', function (): void {
    $derived = derivedSyntheticManifest();
    $fields = $derived['manifest']['fields'];

    expect($derived['hash'])->toMatch('/^[a-f0-9]{64}$/')
        ->and($fields)->toHaveCount(900)
        ->and($derived['manifest']['identity']['free_form_supported'])->toBeFalse()
        ->and($derived['manifest']['identity']['symbols'])->toBe(['0', '1', '2', '3', '4', '5', '6', '7', '8', '9']);

    expect($fields[100]['pixel'])->toBe(['x' => 520, 'y' => 100, 'w' => 28, 'h' => 28])
        ->and($fields[499]['pixel'])->toBe(['x' => 670, 'y' => 4555, 'w' => 28, 'h' => 28])
        ->and($fields[899]['pixel'])->toBe(['x' => 1200, 'y' => 4555, 'w' => 28, 'h' => 28]);

    $keys = array_map(static fn (array $field): string => $field['label'].'|'.$field['option'], $fields);
    expect(count($keys))->toBe(count(array_unique($keys)));
});

test('native manifest derivation is byte deterministic', function (): void {
    $first = derivedSyntheticManifest();
    $second = derivedSyntheticManifest();

    expect($first)->toBe($second);
});

test('native manifest contains finite in-bounds non-overlapping bubbles', function (): void {
    $fields = derivedSyntheticManifest()['manifest']['fields'];

    foreach ($fields as $index => $field) {
        $region = $field['region'];
        expect(is_finite($region['x']))->toBeTrue()
            ->and(is_finite($region['y']))->toBeTrue()
            ->and($region['x'])->toBeGreaterThanOrEqual(0)
            ->and($region['y'])->toBeGreaterThanOrEqual(0)
            ->and($region['x'] + $region['w'])->toBeLessThanOrEqual(1)
            ->and($region['y'] + $region['h'])->toBeLessThanOrEqual(1);

        foreach (array_slice($fields, 0, $index) as $other) {
            if ($field['label'] !== $other['label']) {
                continue;
            }

            $otherRegion = $other['region'];
            $overlap = $region['x'] < $otherRegion['x'] + $otherRegion['w']
                && $otherRegion['x'] < $region['x'] + $region['w']
                && $region['y'] < $otherRegion['y'] + $otherRegion['h']
                && $otherRegion['y'] < $region['y'] + $region['h'];

            expect($overlap)->toBeFalse();
        }
    }
});
