<?php

namespace App\Assessment\Omr;

use JsonException;
use RuntimeException;

use function array_keys;
use function array_unique;
use function array_values;
use function basename;
use function count;
use function explode;
use function file_get_contents;
use function glob;
use function hash_file;
use function is_array;
use function is_file;
use function is_numeric;
use function json_decode;
use function json_encode;
use function ksort;
use function preg_match;
use function sprintf;
use function sort;
use function str_contains;
use function str_ends_with;
use function str_starts_with;

/**
 * Immutable file-backed OMRChecker-native bundle metadata.
 *
 * The native JSON is the only detection geometry authority. Relational rows
 * and review crops must be derived from this bundle, never the reverse.
 *
 * Multi-page bundles (v3+) declare an ordered `pages` list; each page carries
 * its own pageDimensions and fieldBlocks and its own reference asset
 * (`reference-page-N.svg`). Single-page bundles keep `reference.svg`.
 */
final readonly class NativeBundle
{
    /**
     * @param array<string, mixed> $template
     * @param array<string, mixed> $config
     * @param array<string, mixed> $manifest
     * @param array<string, string> $fileHashes
     * @param list<array<string, mixed>> $pages
     */
    private function __construct(
        public string $code,
        public int $version,
        public array $template,
        public array $config,
        public array $manifest,
        public array $fileHashes,
        public string $bundleHash,
        public array $pages,
    ) {
    }

    /**
     * Resolves the bundle directory: durable private storage first, legacy
     * in-tree bundles second. New saves stage under storage/app/private/
     * omr-bundles/<code>/v<N>; the shipped v1-v5 demo bundles remain in the
     * source tree as a read-only fallback.
     */
    private static function resolveDirectory(string $code, int $version): string
    {
        $durable = \Illuminate\Support\Facades\Storage::disk('local')->path('omr-bundles/'.$code.'/v'.$version);
        if (is_dir($durable)) {
            return $durable;
        }

        $legacy = __DIR__.'/NativeBundles/'.$code.'/v'.$version;
        if (is_dir($legacy)) {
            return $legacy;
        }

        throw new RuntimeException("OMR native bundle {$code} v{$version} not found.");
    }

    public static function load(string $code, int $version): self
    {
        return self::loadFromDirectory(self::resolveDirectory($code, $version), $code, $version);
    }

    /**
     * Load a candidate bundle from a caller-owned directory.
     *
     * Candidate materialization uses this same validation path before any
     * immutable version is created. The directory is never retained here.
     */
    public static function loadFromDirectory(string $directory, string $code, int $version): self
    {
        $paths = [
            'template.json' => $directory.'/template.json',
            'config.json' => $directory.'/config.json',
            'manifest.json' => $directory.'/manifest.json',
        ];

        $referenceFiles = glob($directory.'/reference*.svg') ?: [];
        if ($referenceFiles === []) {
            throw new RuntimeException('OMR native bundle has no reference asset.');
        }
        sort($referenceFiles);
        foreach ($referenceFiles as $referencePath) {
            $paths[basename($referencePath)] = $referencePath;
        }

        foreach ($paths as $name => $path) {
            if (! is_file($path)) {
                throw new RuntimeException(sprintf('OMR native bundle file is missing: %s.', $name));
            }
        }

        $template = self::decodeJson($paths['template.json']);
        $config = self::decodeJson($paths['config.json']);
        $manifest = self::decodeJson($paths['manifest.json']);

        $bubble = $template['bubbleDimensions'] ?? null;
        if (! is_array($bubble) || count($bubble) !== 2 || (int) $bubble[0] <= 0 || (int) $bubble[1] <= 0) {
            throw new RuntimeException('OMR native bundle bubbleDimensions must contain positive dimensions.');
        }

        $pages = self::pagesOf($template);
        self::validateBundleShape($pages, $config, $manifest, $code, $version);

        $markerAsset = self::markerAssetNameOf($template);
        if ($markerAsset !== null) {
            if (preg_match('/^[a-z0-9][a-z0-9._-]{0,63}$/', $markerAsset) !== 1) {
                throw new RuntimeException(sprintf('OMR native bundle declares an unsafe marker asset name: %s.', $markerAsset));
            }
            $assetPath = $directory.'/'.$markerAsset;
            if (! is_file($assetPath)) {
                throw new RuntimeException(sprintf(
                    'OMR native bundle %s v%d declares marker asset %s but the bundle file is missing; a marker-bearing bundle must carry its fiducial asset.',
                    $code,
                    $version,
                    $markerAsset,
                ));
            }
            $paths[$markerAsset] = $assetPath;
        }

        $fileHashes = [];
        foreach ($paths as $name => $path) {
            $fileHashes[$name] = hash_file('sha256', $path);
        }
        ksort($fileHashes);
        self::validateManifestFileHashes($manifest, $fileHashes, $markerAsset);

        $bundleHash = self::hashCanonical([
            'files' => $fileHashes,
            'engine' => $manifest['engine'],
            'bundle_version' => $manifest['bundle_version'],
        ]);

        return new self($code, $version, $template, $config, $manifest, $fileHashes, $bundleHash, $pages);
    }

    public function pageCount(): int
    {
        return count($this->pages);
    }

    /** True when any page declares the fiducial marker contract. */
    public function hasMarkerPages(): bool
    {
        return self::markerAssetNameOf($this->template) !== null;
    }

    /** Name of the declared fiducial asset (e.g. fiducial.png), or null. */
    public function markerAssetName(): ?string
    {
        return self::markerAssetNameOf($this->template);
    }

    /**
     * Read a bundle asset's bytes (the fiducial rides the bundle to the
     * worker via OmrPinnedSnapshot; this is the disk-side source).
     */
    public function assetBytes(string $name): string
    {
        if (! isset($this->fileHashes[$name])) {
            throw new RuntimeException(sprintf('OMR native bundle has no asset %s.', $name));
        }

        $path = self::resolveDirectory($this->code, $this->version).'/'.$name;
        $bytes = @file_get_contents($path);
        if ($bytes === false) {
            throw new RuntimeException(sprintf('OMR native bundle asset is unreadable: %s.', $name));
        }

        return $bytes;
    }

    /**
     * @param  array<string, mixed>  $template
     */
    private static function markerAssetNameOf(array $template): ?string
    {
        $pages = $template['pages'] ?? null;
        if (is_array($pages)) {
            foreach ($pages as $page) {
                if (is_array($page['markers'] ?? null) && is_string($page['markers']['asset_name'] ?? null)) {
                    return $page['markers']['asset_name'];
                }
            }

            return null;
        }

        $markers = $template['markers'] ?? null;

        return is_array($markers) && is_string($markers['asset_name'] ?? null) ? $markers['asset_name'] : null;
    }

    /** @return array<string, mixed> */
    public function pageTemplate(int $pageNo): array
    {
        $page = $this->pages[$pageNo - 1] ?? null;
        if (! is_array($page)) {
            throw new RuntimeException(sprintf('OMR native bundle has no page %d.', $pageNo));
        }

        $pageTemplate = [
            'pageDimensions' => $page['pageDimensions'],
            'bubbleDimensions' => $this->template['bubbleDimensions'],
            'customLabels' => [],
            'fieldBlocks' => self::workerFieldBlocks((array) $page['fieldBlocks']),
            'preProcessors' => $page['preProcessors'] ?? [],
        ];
        $customLabels = self::pageCustomLabels($pageTemplate);
        $pageTemplate['customLabels'] = $customLabels === [] ? new \stdClass : $customLabels;

        return $pageTemplate;
    }

    /** Strip NEXIAM authoring metadata before pinned OMRChecker schema validation. */
    public static function workerFieldBlocks(array $fieldBlocks): array
    {
        $allowed = array_flip([
            'fieldType',
            'fieldLabels',
            'origin',
            'bubblesGap',
            'labelsGap',
            'bubbleValues',
            'direction',
            'emptyValue',
        ]);

        return array_map(
            static fn (array $block): array => array_intersect_key($block, $allowed),
            $fieldBlocks,
        );
    }

    /** @param array<string, mixed> $pageTemplate @return array<string, list<string>> */
    private static function pageCustomLabels(array $pageTemplate): array
    {
        $labels = [];
        foreach ((array) ($pageTemplate['fieldBlocks'] ?? []) as $block) {
            foreach ((array) ($block['fieldLabels'] ?? []) as $fieldLabel) {
                $labels = [...$labels, ...self::expandLabel((string) $fieldLabel)];
            }
        }

        $grouped = [];
        foreach ($labels as $label) {
            if (str_starts_with($label, 'APPLICANT_NUMBER_')) {
                $grouped['APPLICANT_NUMBER'][] = $label;
                continue;
            }
            if (preg_match('/^([A-Z0-9_]+)_Q_\d+$/', $label, $matches) === 1) {
                $grouped[$matches[1]][] = $label;
            }
        }

        return $grouped;
    }


    /** @return array{0: int, 1: int} */
    public function pageDimensions(int $pageNo): array
    {
        $page = $this->pages[$pageNo - 1] ?? null;
        if (! is_array($page)) {
            throw new RuntimeException(sprintf('OMR native bundle has no page %d.', $pageNo));
        }

        return [(int) $page['pageDimensions'][0], (int) $page['pageDimensions'][1]];
    }

    public function pageReferenceName(int $pageNo): string
    {
        $expected = 'reference-page-'.$pageNo.'.svg';
        if (isset($this->fileHashes[$expected])) {
            return $expected;
        }
        if ($this->pageCount() === 1 && isset($this->fileHashes['reference.svg'])) {
            return 'reference.svg';
        }

        throw new RuntimeException(sprintf('OMR native bundle has no reference asset for page %d.', $pageNo));
    }

    /** @return list<string> */
    public function stableFieldLabels(): array
    {
        $labels = [];
        foreach ($this->pages as $page) {
            foreach ((array) ($page['fieldBlocks'] ?? []) as $block) {
                foreach ((array) ($block['fieldLabels'] ?? []) as $fieldLabel) {
                    foreach (self::expandLabel((string) $fieldLabel) as $expanded) {
                        $labels[] = $expanded;
                    }
                }
            }
        }

        return $labels;
    }

    /**
     * @return list<string>
     */
    public static function expandLabel(string $label): array
    {
        if (! str_contains($label, '..')) {
            return [$label];
        }

        [$start, $end] = explode('..', $label, 2);
        $startMatch = [];
        $endMatch = [];

        if (preg_match('/^(.*?)(\d+)$/', $start, $startMatch) !== 1) {
            throw new RuntimeException(sprintf('Unsupported native field label range: %s.', $label));
        }

        if (preg_match('/^(\d+)$/', $end, $endMatch) === 1) {
            $endMatch = [$end, $startMatch[1], $endMatch[1]];
        } elseif (preg_match('/^(.*?)(\d+)$/', $end, $endMatch) !== 1 || $startMatch[1] !== $endMatch[1]) {
            throw new RuntimeException(sprintf('Unsupported native field label range: %s.', $label));
        }

        $from = (int) $startMatch[2];
        $to = (int) $endMatch[2];
        if ($from >= $to) {
            throw new RuntimeException(sprintf('Native field label range is not ascending: %s.', $label));
        }

        $expanded = [];
        for ($number = $from; $number <= $to; $number++) {
            $expanded[] = $startMatch[1].$number;
        }

        return $expanded;
    }

    /** @param array<string, mixed> $template @return list<array<string, mixed>> */
    private static function pagesOf(array $template): array
    {
        $declared = $template['pages'] ?? null;

        if (is_array($declared)) {
            if ($declared === []) {
                throw new RuntimeException('OMR native bundle pages list must not be empty.');
            }

            return array_values($declared);
        }

        // Single-page bundle: wrap the legacy top-level shape.
        return [[
            'page_no' => 1,
            'pageDimensions' => $template['pageDimensions'] ?? null,
            'fieldBlocks' => $template['fieldBlocks'] ?? null,
            'preProcessors' => $template['preProcessors'] ?? [],
        ]];
    }

    /**
     * @param list<array<string, mixed>> $pages
     * @param array<string, mixed> $config
     * @param array<string, mixed> $manifest
     */
    private static function validateBundleShape(array $pages, array $config, array $manifest, string $code, int $version): void
    {
        $configDimensions = $config['dimensions'] ?? null;
        $labels = [];

        foreach ($pages as $index => $page) {
            if (! is_array($page) || (int) ($page['page_no'] ?? 0) !== $index + 1) {
                throw new RuntimeException('OMR native bundle pages must be ordered from page 1.');
            }

            $dims = $page['pageDimensions'] ?? null;
            $blocks = $page['fieldBlocks'] ?? null;

            if (! is_array($dims) || count($dims) !== 2 || (int) $dims[0] <= 0 || (int) $dims[1] <= 0) {
                throw new RuntimeException(sprintf('OMR native bundle page %d has invalid pageDimensions.', $index + 1));
            }
            if (! is_array($blocks) || $blocks === []) {
                throw new RuntimeException(sprintf('OMR native bundle page %d must declare fieldBlocks.', $index + 1));
            }
            if (! is_array($configDimensions)
                || (int) ($configDimensions['display_width'] ?? 0) !== (int) $dims[0]
                || (int) ($configDimensions['display_height'] ?? 0) !== (int) $dims[1]) {
                throw new RuntimeException('OMR native bundle config dimensions must match every pageDimensions.');
            }

            foreach ($blocks as $blockName => $block) {
                if (! is_array($block) || ! is_array($block['origin'] ?? null) || count($block['origin']) !== 2) {
                    throw new RuntimeException(sprintf('OMR native field block %s has no valid origin.', $blockName));
                }
                foreach (['bubblesGap', 'labelsGap'] as $key) {
                    if (! is_numeric($block[$key] ?? null) || (float) $block[$key] <= 0) {
                        throw new RuntimeException(sprintf('OMR native field block %s has invalid %s.', $blockName, $key));
                    }
                }
                foreach ((array) ($block['fieldLabels'] ?? []) as $label) {
                    $labels = [...$labels, ...self::expandLabel((string) $label)];
                }
            }
        }

        $scorableLabels = array_values(array_filter(
            $labels,
            static fn (string $label): bool => ! str_starts_with($label, 'APPLICANT_NUMBER_')
                && ! str_starts_with($label, 'BIRTHDATE_'),
        ));
        if ($labels === []
            || $scorableLabels === []
            || count($scorableLabels) !== count(array_unique($scorableLabels))) {
            throw new RuntimeException('OMR native bundle field labels must be unique and non-empty.');
        }
        if (($manifest['template_code'] ?? null) !== $code || (int) ($manifest['template_version'] ?? 0) !== $version) {
            throw new RuntimeException('OMR native bundle manifest identity does not match its path.');
        }
        if (! isset($manifest['engine']['name'], $manifest['engine']['commit'], $manifest['engine']['patch_set'])) {
            throw new RuntimeException('OMR native bundle manifest is missing exact engine identity.');
        }
    }

    /** @param array<string, mixed> $manifest @param array<string, string> $fileHashes */
    private static function validateManifestFileHashes(array $manifest, array $fileHashes, ?string $markerAsset = null): void
    {
        $referenceFiles = [];
        foreach (array_keys($fileHashes) as $name) {
            if (str_starts_with($name, 'reference') && str_ends_with($name, '.svg')) {
                $referenceFiles[] = $name;
            }
        }
        sort($referenceFiles);
        $expectedFiles = ['template.json', 'config.json', ...$referenceFiles];
        if ($markerAsset !== null) {
            $expectedFiles[] = $markerAsset;
        }

        $manifestFiles = $manifest['files'] ?? null;

        if (! is_array($manifestFiles) || count($manifestFiles) !== count($expectedFiles)
            || array_keys($manifestFiles) !== $expectedFiles) {
            throw new RuntimeException($markerAsset !== null
                ? 'OMR native bundle manifest must declare template, config, reference, and fiducial asset hashes.'
                : 'OMR native bundle manifest must declare template, config, and reference asset hashes.');
        }

        foreach ($expectedFiles as $name) {
            $declaredHash = $manifestFiles[$name] ?? null;
            if (! is_string($declaredHash) || preg_match('/^[a-f0-9]{64}$/', $declaredHash) !== 1
                || ($fileHashes[$name] ?? null) !== $declaredHash) {
                throw new RuntimeException(sprintf('OMR native bundle asset hash mismatch: %s.', $name));
            }
        }
    }

    /** @return array<string, mixed> */
    private static function decodeJson(string $path): array
    {
        try {
            $value = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException(sprintf('OMR native bundle JSON is invalid: %s.', basename($path)), 0, $exception);
        }

        if (! is_array($value)) {
            throw new RuntimeException(sprintf('OMR native bundle JSON must be an object: %s.', basename($path)));
        }

        return $value;
    }

    private static function hashCanonical(mixed $value): string
    {
        try {
            return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        } catch (JsonException $exception) {
            throw new RuntimeException('Unable to hash OMR native bundle.', 0, $exception);
        }
    }
}
