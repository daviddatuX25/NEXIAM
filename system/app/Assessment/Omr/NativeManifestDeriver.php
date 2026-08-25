<?php

namespace App\Assessment\Omr;

use RuntimeException;

use function array_filter;
use function array_map;
use function array_unique;
use function array_values;
use function count;
use function hash;
use function is_finite;
use function json_encode;
use function round;
use function sprintf;
use function str_starts_with;

/**
 * Derives the normalized NEXIAM review manifest from an immutable native
 * bundle. Multi-page bundles produce per-page fields with `page` attribution
 * and a `pages` metadata list; positions stay globally contiguous across
 * pages.
 */
final class NativeManifestDeriver
{
    /**
     * @return array{manifest: array<string, mixed>, hash: string}
     */
    public function derive(NativeBundle $bundle): array
    {
        $fields = [];
        $pages = [];

        for ($pageNo = 1; $pageNo <= $bundle->pageCount(); $pageNo++) {
            $pageTemplate = $bundle->pageTemplate($pageNo);
            $pageDims = $pageTemplate['pageDimensions'];
            $bubbleDimensions = $pageTemplate['bubbleDimensions'];
            $pageFields = [];

            foreach ((array) $pageTemplate['fieldBlocks'] as $blockName => $block) {
                $fieldType = (string) ($block['fieldType'] ?? '__CUSTOM__');
                $values = $this->bubbleValues($fieldType, $block);
                $direction = (string) ($block['direction'] ?? 'vertical');
                $origin = $block['origin'];
                $bubblesGap = (float) $block['bubblesGap'];
                $labelsGap = (float) $block['labelsGap'];
                $labels = [];

                foreach ((array) $block['fieldLabels'] as $label) {
                    $labels = [...$labels, ...NativeBundle::expandLabel((string) $label)];
                }

                foreach ($labels as $labelIndex => $label) {
                    foreach ($values as $valueIndex => $value) {
                        $x = (float) $origin[0];
                        $y = (float) $origin[1];
                        if ($direction === 'vertical') {
                            $x += $labelIndex * $labelsGap;
                            $y += $valueIndex * $bubblesGap;
                        } else {
                            $x += $valueIndex * $bubblesGap;
                            $y += $labelIndex * $labelsGap;
                        }

                        $region = [
                            'x' => $x / (float) $pageDims[0],
                            'y' => $y / (float) $pageDims[1],
                            'w' => (float) $bubbleDimensions[0] / (float) $pageDims[0],
                            'h' => (float) $bubbleDimensions[1] / (float) $pageDims[1],
                        ];

                        $this->assertRegion($region, $pageDims, (string) $blockName, $label, (string) $value);
                        $pageFields[] = [
                            'page' => $pageNo,
                            'block' => (string) $blockName,
                            'label' => $label,
                            'option' => (string) $value,
                            'pixel' => [
                                'x' => (int) round($x),
                                'y' => (int) round($y),
                                'w' => (int) $bubbleDimensions[0],
                                'h' => (int) $bubbleDimensions[1],
                            ],
                            'region' => $region,
                        ];
                    }
                }
            }

            $fields = [...$fields, ...$pageFields];
            $pages[] = [
                'page_no' => $pageNo,
                'page_dimensions' => ['width' => (int) $pageDims[0], 'height' => (int) $pageDims[1]],
                'field_count' => count($pageFields),
            ];
        }

        $this->assertUniqueFields($fields);

        $identityLabels = array_values(array_unique(array_map(
            static fn (array $field): string => $field['label'],
            array_values(array_filter(
                $fields,
                static fn (array $field): bool => str_starts_with($field['label'], 'APPLICANT_NUMBER_'),
            )),
        )));
        $identitySymbols = array_values(array_unique(array_map(
            static fn (array $field): string => $field['option'],
            array_values(array_filter(
                $fields,
                static fn (array $field): bool => str_starts_with($field['label'], 'APPLICANT_NUMBER_'),
            )),
        )));
        $expectedIdentitySymbols = array_map(static fn (int $value): string => (string) $value, range(0, 9));
        $birthdateLabels = array_values(array_unique(array_map(
            static fn (array $field): string => $field['label'],
            array_values(array_filter(
                $fields,
                static fn (array $field): bool => str_starts_with($field['label'], 'BIRTHDATE_'),
            )),
        )));
        $birthdateSymbols = array_values(array_unique(array_map(
            static fn (array $field): string => $field['option'],
            array_values(array_filter(
                $fields,
                static fn (array $field): bool => str_starts_with($field['label'], 'BIRTHDATE_'),
            )),
        )));

        $identityPolicy = (string) ($bundle->manifest['identity_policy'] ?? 'required');
        $hasIdentity = $identityLabels !== [] || $identitySymbols !== [];
        if ($identityPolicy !== 'optional' && (count($identityLabels) !== 10 || $identitySymbols !== $expectedIdentitySymbols)) {
            throw new RuntimeException('Native bundle applicant-number identity must declare ten positions with symbols 0 through 9.');
        }
        if ($identityPolicy === 'optional' && $hasIdentity
            && (count($identityLabels) < 1 || count($identityLabels) > 10 || $identitySymbols !== $expectedIdentitySymbols)) {
            throw new RuntimeException('Canonical identity grids must declare one through ten positions with symbols 0 through 9.');
        }
        if ($birthdateLabels !== [] && (count($birthdateLabels) !== 6 || $birthdateSymbols !== $expectedIdentitySymbols)) {
            throw new RuntimeException('Native bundle birthdate identity must declare six positions with symbols 0 through 9.');
        }

        $manifest = [
            'manifest_version' => 'nexiam.omr.review-manifest.v1',
            'template_code' => $bundle->code,
            'template_version' => $bundle->version,
            'bundle_hash' => $bundle->bundleHash,
            'file_hashes' => $bundle->fileHashes,
            'engine' => $bundle->manifest['engine'],
            'page_count' => $bundle->pageCount(),
            'pages' => $pages,
            'identity' => [
                'mode' => $hasIdentity ? 'bubbled_applicant_number' : 'none',
                'labels' => $identityLabels,
                'symbols' => $identitySymbols,
                'free_form_supported' => false,
                'birthdate' => $birthdateLabels === [] ? null : [
                    'labels' => $birthdateLabels,
                    'symbols' => $birthdateSymbols,
                ],
            ],
            'fields' => $fields,
        ];

        $json = json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        return ['manifest' => $manifest, 'hash' => hash('sha256', $json)];
    }

    /** @return list<string> */
    private function bubbleValues(string $fieldType, array $block): array
    {
        if (isset($block['bubbleValues']) && is_array($block['bubbleValues'])) {
            return array_values(array_map('strval', $block['bubbleValues']));
        }

        return match ($fieldType) {
            'QTYPE_INT' => ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            'QTYPE_MCQ4' => ['A', 'B', 'C', 'D'],
            'QTYPE_MCQ5' => ['A', 'B', 'C', 'D', 'E'],
            default => throw new RuntimeException(sprintf('Native field block type %s has no declared bubble values.', $fieldType)),
        };
    }

    /** @param array{x: float, y: float, w: float, h: float} $region @param array{0: int, 1: int} $page */
    private function assertRegion(array $region, array $page, string $block, string $label, string $option): void
    {
        if (! is_finite($region['x']) || ! is_finite($region['y']) || ! is_finite($region['w']) || ! is_finite($region['h'])
            || $region['w'] <= 0.0 || $region['h'] <= 0.0
            || $region['x'] < 0.0 || $region['y'] < 0.0
            || $region['x'] + $region['w'] > 1.0 || $region['y'] + $region['h'] > 1.0) {
            throw new RuntimeException(sprintf('Native field block %s produced an out-of-bounds bubble %s/%s.', $block, $label, $option));
        }
    }

    /** @param list<array<string, mixed>> $fields */
    private function assertUniqueFields(array $fields): void
    {
        // Identity grids repeat across pages; uniqueness is (page, label, option).
        $keys = array_map(static fn (array $field): string => $field['page'].'|'.$field['label'].'|'.$field['option'], $fields);
        if (count($keys) !== count(array_unique($keys))) {
            throw new RuntimeException('Native bundle expanded duplicate field bubbles.');
        }

        $labelKeys = array_map(static fn (array $field): string => $field['label'].'|'.$field['option'], $fields);
        $scorable = array_filter(
            $fields,
            static fn (array $field): bool => ! str_starts_with($field['label'], 'APPLICANT_NUMBER_')
                && ! str_starts_with($field['label'], 'BIRTHDATE_'),
        );
        $scorableKeys = array_map(static fn (array $field): string => $field['label'].'|'.$field['option'], array_values($scorable));
        if (count($scorableKeys) !== count(array_unique($scorableKeys))) {
            $dupes = array_keys(array_diff_assoc($scorableKeys, array_unique($scorableKeys)));
            throw new RuntimeException('Native bundle scorable field bubbles must be unique across pages: '.implode(', ', array_map(fn ($k) => $scorable[$k]['page'].':'.$scorableKeys[$k], $dupes)));
        }
        unset($labelKeys);

        $this->assertNoOverlappingFields($fields);
    }

    /** @param list<array<string, mixed>> $fields */
    private function assertNoOverlappingFields(array $fields): void
    {
        for ($index = 0; $index < count($fields); $index++) {
            $region = $fields[$index]['region'];

            for ($otherIndex = 0; $otherIndex < $index; $otherIndex++) {
                if ((int) $fields[$index]['page'] !== (int) $fields[$otherIndex]['page']) {
                    continue;
                }

                $otherRegion = $fields[$otherIndex]['region'];
                $overlap = $region['x'] < $otherRegion['x'] + $otherRegion['w']
                    && $region['x'] + $region['w'] > $otherRegion['x']
                    && $region['y'] < $otherRegion['y'] + $otherRegion['h']
                    && $region['y'] + $region['h'] > $otherRegion['y'];

                if ($overlap) {
                    throw new RuntimeException(sprintf(
                        'Native bundle bubbles overlap on page %d: %s/%s and %s/%s.',
                        (int) $fields[$index]['page'],
                        $fields[$index]['label'],
                        $fields[$index]['option'],
                        $fields[$otherIndex]['label'],
                        $fields[$otherIndex]['option'],
                    ));
                }
            }
        }
    }
}
