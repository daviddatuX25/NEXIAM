<?php

namespace App\Assessment\Omr;

use App\Assessment\Omr\Canonical\OmrPageDimensionMath;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use Illuminate\Support\Facades\Storage;
use JsonException;
use RuntimeException;

use function array_keys;
use function array_unique;
use function array_values;
use function basename;
use function config;
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
use function max;
use function preg_match;
use function sort;
use function sprintf;
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
     * @param  array<string, mixed>  $template
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $manifest
     * @param  array<string, string>  $fileHashes
     * @param  list<array<string, mixed>>  $pages
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
        private string $directory,
    ) {}

    /** Resolve legacy demo bundles only for unbound synthetic fixtures. */
    private static function resolveDirectory(string $code, int $version): string
    {
        $durable = Storage::disk('local')->path('omr-bundles/'.$code.'/v'.$version);
        if (is_dir($durable)) {
            return $durable;
        }

        $legacy = __DIR__.'/NativeBundles/'.$code.'/v'.$version;
        if (is_dir($legacy)) {
            return $legacy;
        }

        throw new RuntimeException("OMR native bundle {$code} v{$version} not found.");
    }

    public static function loadForVersion(OmrAnswerSheetTemplateVersion $version): self
    {
        $persisted = $version->newQuery()->with('template')->find($version->getKey());
        if (! $persisted instanceof OmrAnswerSheetTemplateVersion
            || ! in_array($persisted->status, [
                OmrAnswerSheetTemplateVersion::STATUS_DRAFT,
                OmrAnswerSheetTemplateVersion::STATUS_COMPILED,
                OmrAnswerSheetTemplateVersion::STATUS_ACTIVE,
                OmrAnswerSheetTemplateVersion::STATUS_RETIRED,
            ], true)
            || ! is_array($persisted->native_bundle_json)
            || ! is_string($persisted->native_bundle_json['relative_dir'] ?? null)
            || ! is_string($persisted->native_bundle_hash)
            || preg_match('/^[a-f0-9]{64}$/', $persisted->native_bundle_hash) !== 1
            || ! $persisted->template) {
            throw new RuntimeException('The persisted OMR template version has no valid native bundle identity.');
        }

        $institutionId = (int) $persisted->template->institution_id;
        $templateId = (int) $persisted->template_id;
        $versionNumber = (int) $persisted->version;
        $relativeDirectory = $persisted->native_bundle_json['relative_dir'];
        $expectedDirectory = sprintf(
            'omr-bundles/institutions/%d/templates/%d/v%d',
            $institutionId,
            $templateId,
            $versionNumber,
        );
        if ($institutionId < 1 || $templateId < 1 || $versionNumber < 1
            || $relativeDirectory !== $expectedDirectory) {
            throw new RuntimeException('The persisted OMR native bundle directory does not match its owner and version.');
        }

        $directory = Storage::disk('local')->path($relativeDirectory);
        $realDirectory = realpath($directory);
        $realRoot = realpath(Storage::disk('local')->path('omr-bundles/institutions/'.$institutionId.'/templates/'.$templateId));
        if ($realDirectory === false || $realRoot === false
            || ! str_starts_with($realDirectory, $realRoot.DIRECTORY_SEPARATOR)
            || ! is_dir($realDirectory)) {
            throw new RuntimeException('The persisted OMR native bundle directory is missing or escapes its owner namespace.');
        }

        $bundle = self::loadFromDirectory($realDirectory, (string) $persisted->template->code, $versionNumber);
        if ($bundle->bundleHash !== $persisted->native_bundle_hash
            || ($persisted->native_bundle_json['files'] ?? null) !== $bundle->fileHashes) {
            throw new RuntimeException('The persisted OMR native bundle does not match its immutable file identity.');
        }

        return $bundle;
    }

    public static function load(string $code, int $version): self
    {
        return self::loadFromDirectory(self::resolveDirectory($code, $version), $code, $version);
    }

    /**
     * Load a candidate bundle from a caller-owned directory.
     *
     * immutable version is created; the resolved directory is retained for
     * subsequent private asset reads.
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

        $realDirectory = realpath($directory);
        foreach ($paths as $name => $path) {
            $realPath = realpath($path);
            if (! is_file($path) || is_link($path) || $realDirectory === false || $realPath === false
                || ! str_starts_with($realPath, $realDirectory.DIRECTORY_SEPARATOR)) {
                throw new RuntimeException(sprintf('OMR native bundle file is missing or escapes its directory: %s.', $name));
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
        self::validateBundleShape($pages, $config, $manifest, $code, $version, [(int) $bubble[0], (int) $bubble[1]]);
        self::validateRegistrationPolicy($template, $pages, $manifest);

        $markerAsset = self::markerAssetNameOf($template);
        if ($markerAsset !== null) {
            if (preg_match('/^[a-z0-9][a-z0-9._-]{0,63}$/', $markerAsset) !== 1) {
                throw new RuntimeException(sprintf('OMR native bundle declares an unsafe marker asset name: %s.', $markerAsset));
            }
            $assetPath = $directory.'/'.$markerAsset;
            $realAssetPath = realpath($assetPath);
            if (! is_file($assetPath) || is_link($assetPath) || $realDirectory === false || $realAssetPath === false
                || ! str_starts_with($realAssetPath, $realDirectory.DIRECTORY_SEPARATOR)) {
                throw new RuntimeException(sprintf('OMR native bundle marker asset is missing or escapes its directory: %s.', $markerAsset));
            }
            $paths[$markerAsset] = $assetPath;
        }

        $fileHashes = [];
        foreach ($paths as $name => $path) {
            $fileHashes[$name] = hash_file('sha256', $path);
        }
        ksort($fileHashes);
        self::validateManifestFileHashes($manifest, $fileHashes, $markerAsset);
        if (($manifest['registration_mode'] ?? null) === OmrRegistrationMode::FiducialAnchored->value
            && ($manifest['marker_profile']['asset_sha256'] ?? null) !== ($fileHashes[$markerAsset] ?? null)) {
            throw new RuntimeException('OMR native bundle marker profile hash differs from its immutable asset.');
        }

        $bundleHash = self::hashCanonical([
            'files' => $fileHashes,
            'engine' => $manifest['engine'],
            'bundle_version' => $manifest['bundle_version'],
        ]);

        return new self($code, $version, $template, $config, $manifest, $fileHashes, $bundleHash, $pages, $directory);
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

        $path = $this->directory.'/'.$name;
        $bytes = @file_get_contents($path);
        if ($bytes === false) {
            throw new RuntimeException(sprintf('OMR native bundle asset is unreadable: %s.', $name));
        }

        if (hash('sha256', $bytes) !== $this->fileHashes[$name]) {
            throw new RuntimeException(sprintf('OMR native bundle asset hash mismatch after load: %s.', $name));
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
            'bubbleDimensions',
            'fieldLabels',
            'origin',
            'bubblesGap',
            'labelsGap',
            'bubbleValues',
            'direction',
            'emptyValue',
            'bubblePositions',
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
     * @param  list<array<string, mixed>>  $pages
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $manifest
     * @param  array{0:int,1:int}  $bubbleDimensions
     */
    private static function validateBundleShape(array $pages, array $config, array $manifest, string $code, int $version, array $bubbleDimensions): void
    {
        if (count($pages) < 1 || count($pages) > 4) {
            throw new RuntimeException('OMR native bundle must contain between one and four ordered pages.');
        }

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
                if (array_key_exists('bubblePositions', $block)) {
                    if (! in_array($block['direction'] ?? 'horizontal', ['horizontal', 'vertical'], true)) {
                        throw new RuntimeException(sprintf('OMR native field block %s has an unsupported explicit-position direction.', $blockName));
                    }
                    $fieldLabels = [];
                    foreach ((array) ($block['fieldLabels'] ?? []) as $fieldLabel) {
                        $fieldLabels = [...$fieldLabels, ...self::expandLabel((string) $fieldLabel)];
                    }
                    $fieldType = (string) ($block['fieldType'] ?? '');
                    if (array_key_exists('bubbleValues', $block)
                        && (! is_array($block['bubbleValues']) || ! array_is_list($block['bubbleValues']))) {
                        throw new RuntimeException(sprintf('OMR native field block %s has an unsupported explicit option set.', $blockName));
                    }
                    if (isset($block['bubbleValues']) && is_array($block['bubbleValues'])) {
                        $optionCount = count($block['bubbleValues']);
                        $expectedOptionCount = $optionCount;
                    } else {
                        $optionCount = match ($fieldType) {
                            'QTYPE_MCQ4' => 4,
                            'QTYPE_MCQ5' => 5,
                            'QTYPE_INT' => 10,
                            default => 0,
                        };
                        $expectedOptionCount = match ($fieldType) {
                            'QTYPE_MCQ4' => 4,
                            'QTYPE_MCQ5' => 5,
                            'QTYPE_INT' => 10,
                            default => 0,
                        };
                    }
                    if ($fieldLabels === []) {
                        throw new RuntimeException(sprintf('OMR native field block %s has no explicit-position fields.', $blockName));
                    }
                    if ($optionCount !== $expectedOptionCount) {
                        throw new RuntimeException(sprintf('OMR native field block %s option count does not match its declared field type.', $blockName));
                    }
                    if ($optionCount <= 0 || (isset($block['bubbleValues']) && ! array_is_list($block['bubbleValues']))) {
                        throw new RuntimeException(sprintf('OMR native field block %s has an unsupported explicit option set.', $blockName));
                    }
                    $positions = $block['bubblePositions'];
                    if (! is_array($positions) || ! array_is_list($positions) || count($positions) !== count($fieldLabels)) {
                        throw new RuntimeException(sprintf('OMR native field block %s has invalid bubblePositions rows.', $blockName));
                    }
                    $blockBubbleDimensions = $bubbleDimensions;
                    if (array_key_exists('bubbleDimensions', $block)) {
                        if (! is_array($block['bubbleDimensions']) || count($block['bubbleDimensions']) !== 2
                            || ! is_int($block['bubbleDimensions'][0] ?? null) || ! is_int($block['bubbleDimensions'][1] ?? null)
                            || $block['bubbleDimensions'][0] <= 0 || $block['bubbleDimensions'][1] <= 0) {
                            throw new RuntimeException(sprintf('OMR native field block %s has invalid bubbleDimensions.', $blockName));
                        }
                        $blockBubbleDimensions = [$block['bubbleDimensions'][0], $block['bubbleDimensions'][1]];
                    }
                    $bubbleRects = [];
                    $minimumGapPx = max(1, OmrPageDimensionMath::pixelsFromMillimetres((float) config('omr.canonical.limits.minimum_option_edge_gap_mm', 1.26)) - 1);
                    foreach ($positions as $positionRow) {
                        if (! is_array($positionRow) || ! array_is_list($positionRow) || count($positionRow) !== $optionCount) {
                            throw new RuntimeException(sprintf('OMR native field block %s has invalid bubblePositions options.', $blockName));
                        }
                        foreach ($positionRow as $position) {
                            if (! is_array($position) || ! array_is_list($position) || count($position) !== 2
                                || ! is_int($position[0] ?? null) || ! is_int($position[1] ?? null)) {
                                throw new RuntimeException(sprintf('OMR native field block %s has non-integer bubblePositions.', $blockName));
                            }
                            [$x, $y] = $position;
                            if ($x < 0 || $y < 0
                                || $x + $blockBubbleDimensions[0] >= (int) $dims[0]
                                || $y + $blockBubbleDimensions[1] >= (int) $dims[1]) {
                                throw new RuntimeException(sprintf('OMR native field block %s has out-of-bounds bubblePositions.', $blockName));
                            }
                            $rect = ['x' => $x, 'y' => $y, 'w' => $blockBubbleDimensions[0], 'h' => $blockBubbleDimensions[1]];
                            foreach ($bubbleRects as $otherRect) {
                                $gapX = max(0, $otherRect['x'] - ($x + $blockBubbleDimensions[0]), $x - ($otherRect['x'] + $otherRect['w']));
                                $gapY = max(0, $otherRect['y'] - ($y + $blockBubbleDimensions[1]), $y - ($otherRect['y'] + $otherRect['h']));
                                if ($gapX * $gapX + $gapY * $gapY < $minimumGapPx * $minimumGapPx) {
                                    throw new RuntimeException(sprintf('OMR native field block %s has explicit bubbles below the minimum edge gap.', $blockName));
                                }
                            }
                            $bubbleRects[] = $rect;
                        }
                    }
                    if (($manifest['engine']['patch_set'] ?? null) !== 'nexiam-explicit-bubble-positions-v1') {
                        throw new RuntimeException('OMR native bundle explicit bubble positions require their pinned engine patch.');
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

    /** @param array<string, mixed> $template @param list<array<string, mixed>> $pages @param array<string, mixed> $manifest */
    private static function validateRegistrationPolicy(array $template, array $pages, array $manifest): void
    {
        $declaresPolicy = isset($template['registration_mode'], $template['qr_mode'], $template['compiled_pages'])
            || isset($manifest['registration_mode'], $manifest['qr_mode'], $manifest['compiled_pages']);
        if (! $declaresPolicy) {
            return; // Historical native bundles predate explicit registration and QR policy.
        }
        $registration = $manifest['registration_mode'] ?? null;
        $qr = $manifest['qr_mode'] ?? null;
        $orderedPages = array_map(
            static fn (array $page): array => ['page_no' => (int) $page['page_no']],
            $pages,
        );
        if (! in_array($registration, ['fiducial_anchored', 'document_bound'], true)
            || ! in_array($qr, ['qr_signed', 'qr_disabled'], true)
            || ($template['registration_mode'] ?? null) !== $registration
            || ($template['qr_mode'] ?? null) !== $qr
            || ($template['compiled_pages'] ?? null) !== $orderedPages
            || ($manifest['compiled_pages'] ?? null) !== $orderedPages) {
            throw new RuntimeException('OMR native bundle registration, QR, or ordered page policy is inconsistent.');
        }
        if ($qr === OmrQrMode::Disabled->value) {
            foreach ((array) ($template['visualComponents'] ?? []) as $component) {
                if (is_array($component) && in_array($component['type'] ?? null, ['qr', 'blank_instance_qr'], true)) {
                    throw new RuntimeException('QR-disabled native bundle must not contain production QR geometry.');
                }
            }
            if (isset($template['qr_reservation']) || isset($manifest['qr_reservation'])) {
                throw new RuntimeException('QR-disabled native bundle must not contain production QR geometry.');
            }
        }
        foreach ($pages as $page) {
            $markers = $page['markers'] ?? null;
            $preProcessors = $page['preProcessors'] ?? [];
            if ($registration === OmrRegistrationMode::DocumentBound->value) {
                if (array_key_exists('markers', $page) || $preProcessors !== [] || array_key_exists('marker_profile', $manifest)) {
                    throw new RuntimeException('Document-bound native bundle must not contain marker geometry or preProcessors.');
                }
            } elseif (! is_array($markers) || count((array) ($markers['centers'] ?? [])) !== 4
                || count((array) $preProcessors) !== 1
                || ($preProcessors[0]['name'] ?? null) !== 'CropOnMarkers'
                || ($preProcessors[0]['options']['relativePath'] ?? null) !== ($markers['asset_name'] ?? null)
                || ($manifest['marker_profile']['asset_sha256'] ?? null) === null) {
                throw new RuntimeException('Fiducial-anchored native bundle requires four marker centers and CropOnMarkers.');
            }
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
        $manifestKeys = is_array($manifestFiles) ? array_keys($manifestFiles) : [];
        sort($manifestKeys);
        $expectedSorted = $expectedFiles;
        sort($expectedSorted);

        if (! is_array($manifestFiles) || count($manifestFiles) !== count($expectedFiles)
            || $manifestKeys !== $expectedSorted) {
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
