<?php

namespace App\Assessment\Queries;

use App\Assessment\Omr\NativeBundle;
use App\Assessment\Support\PhysicalSheetInventory;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrTemplateAsset;
use Closure;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

use function array_is_list;
use function array_key_exists;
use function array_keys;
use function array_values;
use function count;
use function fclose;
use function feof;
use function fread;
use function hash_equals;
use function hash_final;
use function hash_init;
use function hash_update;
use function in_array;
use function is_array;
use function is_finite;
use function is_int;
use function is_numeric;
use function is_resource;
use function is_string;
use function min;
use function preg_match;
use function round;
use function strlen;
use function strtolower;

final class KitPairedAnswerKeyPreviewQuery
{
    private const ALLOWED_RASTER_MIMES = ['image/png', 'image/jpeg', 'image/webp'];

    public function __construct(private readonly PhysicalSheetInventory $inventory) {}

    /**
     * @param  list<array<string, mixed>>  $compositionRows
     * @param  list<array{physical_item_uid:string,correct_option_code:string}>  $validatedKeys
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function forKit(
        OmrAnswerSheetTemplateVersion $sheet,
        array $compositionRows,
        array $validatedKeys,
        array $context,
        Closure $rasterUrl,
        bool $verifyAllRasterAssets = true,
        ?array $requiredKeyUids = null,
    ): array {
        try {
            $inventory = $this->inventory->forVersion($sheet);
        } catch (RuntimeException) {
            return $this->unavailable('physical_inventory_unavailable', $context);
        }
        $template = is_array($sheet->native_bundle_json) ? ($sheet->native_bundle_json['template'] ?? null) : null;
        $nativeManifest = is_array($sheet->native_bundle_json) ? ($sheet->native_bundle_json['manifest'] ?? null) : null;
        $review = is_array($sheet->review_manifest_json) ? $sheet->review_manifest_json : [];
        if (! is_array($template) || ! is_array($nativeManifest) || $review === []) {
            return $this->unavailable('sheet_geometry_unavailable', $context);
        }

        $nativePages = is_array($template['pages'] ?? null)
            ? array_values($template['pages'])
            : [[
                'page_no' => 1,
                'pageDimensions' => $template['pageDimensions'] ?? null,
                'fieldBlocks' => $template['fieldBlocks'] ?? null,
                'markers' => $template['markers'] ?? null,
            ]];
        $pageCount = (int) $sheet->page_count;
        $pageRows = $sheet->pages()->orderBy('page_no')->get()->keyBy('page_no');
        $assets = $sheet->canonicalAssets()
            ->where('kind', OmrTemplateAsset::KIND_RASTER_PAGE)
            ->orderBy('page_no')
            ->get()
            ->keyBy('page_no');
        if ($pageCount < 1 || count($nativePages) !== $pageCount || $pageRows->count() !== $pageCount || $assets->count() !== $pageCount) {
            return $this->unavailable('sheet_page_inventory_mismatch', $context);
        }

        $fields = [];
        foreach ((array) ($review['fields'] ?? []) as $field) {
            if (! is_array($field) || ! is_array($field['pixel'] ?? null)) {
                return $this->unavailable('sheet_geometry_invalid', $context);
            }
            $pageNo = $field['page'] ?? null;
            $label = $field['label'] ?? null;
            $token = $field['option'] ?? null;
            if (! $this->positiveInteger($pageNo) || ! is_string($label) || $label === ''
                || ! is_string($token) || $token === '') {
                return $this->unavailable('sheet_geometry_invalid', $context);
            }
            $fieldKey = (int) $pageNo.':'.$label;
            if (isset($fields[$fieldKey][$token])) {
                return $this->unavailable('sheet_geometry_duplicate', $context);
            }
            $fields[$fieldKey][$token] = $field;
        }

        $nativeLocations = [];
        foreach ($nativePages as $index => $nativePage) {
            if (! is_array($nativePage) || (int) ($nativePage['page_no'] ?? $index + 1) !== $index + 1) {
                return $this->unavailable('sheet_page_manifest_invalid', $context);
            }
            foreach ((array) ($nativePage['fieldBlocks'] ?? []) as $block) {
                if (! is_array($block) || ! is_array($block['fieldLabels'] ?? null)) {
                    continue;
                }
                $labels = [];
                foreach ($block['fieldLabels'] as $declaration) {
                    if (! is_string($declaration)) {
                        continue;
                    }
                    try {
                        $labels = [...$labels, ...NativeBundle::expandLabel($declaration)];
                    } catch (RuntimeException) {
                        return $this->unavailable('sheet_native_geometry_invalid', $context);
                    }
                }
                $tokens = is_array($block['bubbleValues'] ?? null) ? array_values($block['bubbleValues']) : [];
                $hasExplicitPositions = array_key_exists('bubblePositions', $block);
                $positions = $block['bubblePositions'] ?? null;
                $positionsValid = ! $hasExplicitPositions ||
                    (is_array($positions) && array_is_list($positions) && count($positions) === count($labels));
                if ($hasExplicitPositions && $positionsValid) {
                    foreach ($positions as $positionRow) {
                        if (! is_array($positionRow) || ! array_is_list($positionRow) || count($positionRow) !== count($tokens)) {
                            $positionsValid = false;
                            break;
                        }
                        foreach ($positionRow as $position) {
                            if (! is_array($position) || ! array_is_list($position) || count($position) !== 2
                                || ! is_int($position[0] ?? null) || ! is_int($position[1] ?? null)
                                || $position[0] < 0 || $position[1] < 0) {
                                $positionsValid = false;
                                break 2;
                            }
                        }
                    }
                }
                foreach ($labels as $labelIndex => $label) {
                    $positionRow = $hasExplicitPositions && is_array($positions) ? ($positions[$labelIndex] ?? null) : null;
                    $locationKey = ($index + 1).':'.$label;
                    if (isset($nativeLocations[$locationKey])) {
                        return $this->unavailable('sheet_geometry_duplicate', $context);
                    }
                    $nativeLocations[$locationKey] = [
                        'page_no' => $index + 1,
                        'tokens' => $tokens,
                        'bubble_dimensions' => $block['bubbleDimensions'] ?? $template['bubbleDimensions'] ?? null,
                        'positions_valid' => $positionsValid,
                        'has_explicit_positions' => $hasExplicitPositions,
                        'bubble_positions' => is_array($positionRow) ? $positionRow : [],
                    ];
                }
            }
        }

        $dimensionsByPage = [];
        $insetsByPage = [];
        foreach ($nativePages as $index => $nativePage) {
            $pageNo = $index + 1;
            $dimensions = $nativePage['pageDimensions'] ?? null;
            $pageRow = $pageRows->get($pageNo);
            $asset = $assets->get($pageNo);
            if (! is_array($dimensions) || count($dimensions) !== 2
                || ! $this->positiveInteger($dimensions[0] ?? null)
                || ! $this->positiveInteger($dimensions[1] ?? null)
                || $pageRow === null || $asset === null
                || (int) $pageRow->width !== (int) $dimensions[0]
                || (int) $pageRow->height !== (int) $dimensions[1]
                || (int) $asset->template_version_id !== (int) $sheet->id
                || (int) $asset->page_no !== $pageNo) {
                return $this->unavailable('sheet_page_asset_mismatch', $context);
            }
            $reviewPage = null;
            foreach ((array) ($review['pages'] ?? []) as $candidatePage) {
                if (is_array($candidatePage) && (int) ($candidatePage['page_no'] ?? 0) === $pageNo) {
                    $reviewPage = $candidatePage;
                    break;
                }
            }
            $reviewDimensions = is_array($reviewPage['page_dimensions'] ?? null) ? $reviewPage['page_dimensions'] : [];
            $persistedManifest = is_array($pageRow->page_manifest_json) ? $pageRow->page_manifest_json : [];
            $persistedPages = (array) ($persistedManifest['pages'] ?? []);
            $persistedPage = null;
            foreach ($persistedPages as $candidatePage) {
                if (is_array($candidatePage) && (int) ($candidatePage['page_no'] ?? 0) === $pageNo) {
                    $persistedPage = $candidatePage;
                    break;
                }
            }
            $persistedDimensions = is_array($persistedPage['page_dimensions'] ?? null) ? $persistedPage['page_dimensions'] : [];
            if ($reviewPage === null
                || (int) ($reviewDimensions['width'] ?? 0) !== (int) $dimensions[0]
                || (int) ($reviewDimensions['height'] ?? 0) !== (int) $dimensions[1]
                || ($persistedManifest['fields'] ?? null) !== ($review['fields'] ?? null)
                || (int) ($persistedDimensions['width'] ?? 0) !== (int) $dimensions[0]
                || (int) ($persistedDimensions['height'] ?? 0) !== (int) $dimensions[1]
                || preg_match('/\A[a-f0-9]{64}\z/i', (string) $pageRow->page_manifest_hash) !== 1) {
                return $this->unavailable('sheet_page_manifest_stale', $context);
            }
            $manifestHash = (string) $pageRow->page_manifest_hash;
            foreach ($sheet->areas as $area) {
                if (! hash_equals($manifestHash, (string) $area->projection_manifest_hash)) {
                    return $this->unavailable('sheet_projection_manifest_stale', $context);
                }
                foreach ($area->questions as $question) {
                    if (! hash_equals($manifestHash, (string) $question->projection_manifest_hash)) {
                        return $this->unavailable('sheet_projection_manifest_stale', $context);
                    }
                    foreach ($question->options as $option) {
                        if (! hash_equals($manifestHash, (string) $option->projection_manifest_hash)) {
                            return $this->unavailable('sheet_projection_manifest_stale', $context);
                        }
                    }
                }
            }
            $metadata = is_array($asset->metadata) ? $asset->metadata : [];
            $width = $this->rasterDimension($metadata, 'width', 'width_px');
            $height = $this->rasterDimension($metadata, 'height', 'height_px');
            if ($width === null || $height === null) {
                $paperWidth = $metadata['paper_width_mm'] ?? null;
                $paperHeight = $metadata['paper_height_mm'] ?? null;
                $ppi = $metadata['ppi'] ?? null;
                if (is_numeric($paperWidth) && is_numeric($paperHeight) && is_numeric($ppi)
                    && (float) $paperWidth > 0 && (float) $paperHeight > 0 && (float) $ppi > 0) {
                    $width = (int) round((float) $paperWidth * (float) $ppi / 25.4);
                    $height = (int) round((float) $paperHeight * (float) $ppi / 25.4);
                }
            }
            $mime = strtolower((string) $asset->mime);
            if ($width === null || $height === null || ! in_array($mime, self::ALLOWED_RASTER_MIMES, true)
                || preg_match('/\A[a-f0-9]{64}\z/i', (string) $asset->sha256) !== 1 || (int) $asset->bytes < 1
                || ! Storage::disk((string) $asset->disk)->exists((string) $asset->path)
                || ($verifyAllRasterAssets && ! $this->assetIntegrityMatches(Storage::disk((string) $asset->disk), $asset))) {
                return $this->unavailable('sheet_raster_unavailable', $context);
            }
            $insets = $this->insetsForPage(
                (string) ($nativeManifest['registration_mode'] ?? ''),
                $nativePage,
                (int) $dimensions[0],
                (int) $dimensions[1],
                $width,
                $height,
                (string) $sheet->registration_mode?->value,
            );
            if ($insets === null) {
                return $this->unavailable('sheet_page_frame_mismatch', $context);
            }
            $dimensionsByPage[$pageNo] = ['width' => $width, 'height' => $height, 'asset' => $asset];
            $insetsByPage[$pageNo] = $insets;
        }

        $locationsByUid = [];
        foreach ($inventory['items'] as $item) {
            $locationsByUid[$item['physical_item_uid']] = $item;
        }
        $bubblesByPage = array_fill(1, $pageCount, []);
        $geometryIssues = [];
        foreach ($inventory['items'] as $item) {
            $label = (string) $item['native_label'];
            $locationKey = (int) $item['page_no'].':'.$label;
            $native = $nativeLocations[$locationKey] ?? null;
            if (! is_array($native) || $native['tokens'] !== $item['option_tokens']) {
                $geometryIssues[] = ['code' => 'physical_geometry_mismatch', 'message' => 'A saved physical location does not match its sheet geometry.'];

                continue;
            }
            if (! $native['positions_valid']) {
                $geometryIssues[] = ['code' => 'physical_geometry_invalid', 'message' => 'A physical location has invalid explicit bubble positions.'];

                continue;
            }
            $bubbleDimensions = $native['bubble_dimensions'];
            if (! is_array($bubbleDimensions) || count($bubbleDimensions) !== 2
                || ! $this->positiveInteger($bubbleDimensions[0] ?? null)
                || ! $this->positiveInteger($bubbleDimensions[1] ?? null)) {
                $geometryIssues[] = ['code' => 'physical_geometry_invalid', 'message' => 'Bubble dimensions are unavailable for a physical location.'];

                continue;
            }
            $options = [];
            $valid = true;
            foreach ($item['option_tokens'] as $optionIndex => $token) {
                $field = $fields[(int) $item['page_no'].':'.$label][$token] ?? null;
                $pixel = is_array($field) ? ($field['pixel'] ?? null) : null;
                if (! is_array($field) || (int) ($field['page'] ?? 0) !== (int) $item['page_no']
                    || ! $this->nonNegativeInteger($pixel['x'] ?? null)
                    || ! $this->nonNegativeInteger($pixel['y'] ?? null)
                    || ! $this->positiveInteger($pixel['w'] ?? null)
                    || ! $this->positiveInteger($pixel['h'] ?? null)
                    || (int) $pixel['w'] !== (int) $bubbleDimensions[0]
                    || (int) $pixel['h'] !== (int) $bubbleDimensions[1]
                    || ($native['has_explicit_positions']
                        && ($native['bubble_positions'][$optionIndex] ?? null) !== [(int) $pixel['x'], (int) $pixel['y']])) {
                    $valid = false;
                    break;
                }
                $insets = $insetsByPage[(int) $item['page_no']];
                $cx = (float) $pixel['x'] + $insets['left'] + (int) $pixel['w'] / 2;
                $cy = (float) $pixel['y'] + $insets['top'] + (int) $pixel['h'] / 2;
                $raster = $dimensionsByPage[(int) $item['page_no']];
                if (! is_finite($cx) || ! is_finite($cy)
                    || $cx + (int) $pixel['w'] / 2 > $raster['width']
                    || $cy + (int) $pixel['h'] / 2 > $raster['height']) {
                    $valid = false;
                    break;
                }
                $options[$token] = ['cx' => $cx, 'cy' => $cy, 'r' => min((int) $pixel['w'], (int) $pixel['h']) / 2];
            }
            if (! $valid || count($options) !== count($item['option_tokens'])) {
                $geometryIssues[] = ['code' => 'physical_geometry_incomplete', 'message' => 'Some answer choices have no verified physical bubble position.'];

                continue;
            }
            $bubblesByPage[(int) $item['page_no']][] = [
                'physical_item_uid' => (string) $item['physical_item_uid'],
                'sheet_item_id' => (int) $item['sheet_item_id'],
                'page_no' => (int) $item['page_no'],
                'question_no' => $this->questionNumber($label),
                'global_position' => (int) $item['global_position'],
                'options' => $options,
            ];
        }

        $rowsByUid = [];
        foreach ($compositionRows as $row) {
            $uid = $row['physical_item_uid'] ?? null;
            if (is_string($uid) && $uid !== '') {
                if (isset($rowsByUid[$uid])) {
                    return $this->unavailable('kit_map_duplicate_location', $context);
                }
                $rowsByUid[$uid] = $row;
            }
        }
        $keysByUid = [];
        $hasInvalidKey = false;
        foreach ($validatedKeys as $key) {
            $uid = (string) ($key['physical_item_uid'] ?? '');
            $token = $key['correct_option_code'] ?? null;
            if ($uid === '' || ! isset($locationsByUid[$uid])) {
                return $this->unavailable('kit_key_contract_invalid', $context);
            }
            if ($token === null) {
                continue;
            }
            if (isset($keysByUid[$uid]) || ! is_string($token) || ! in_array($token, $locationsByUid[$uid]['option_tokens'], true)) {
                $hasInvalidKey = true;

                continue;
            }
            $keysByUid[$uid] = $token;
        }

        $issues = [];
        $mapComplete = (bool) ($context['map_complete'] ?? false);
        $requiredKeyUids ??= array_keys($locationsByUid);
        $keysComplete = $mapComplete && ! $hasInvalidKey && $requiredKeyUids !== [] && count($keysByUid) === count($requiredKeyUids);
        foreach ($requiredKeyUids as $uid) {
            if (! isset($keysByUid[$uid])) {
                $keysComplete = false;
                break;
            }
        }
        $bubbleCount = 0;
        foreach ($bubblesByPage as $pageBubbles) {
            $bubbleCount += count($pageBubbles);
        }
        $allGeometryReady = $geometryIssues === [] && $bubbleCount === count($locationsByUid);
        $issues = [...$issues, ...$geometryIssues];
        if (! $mapComplete) {
            $issues[] = ['code' => 'composition_incomplete', 'message' => 'Complete the explicit physical, Booklet, and Test Setup matches before key shading is available.'];
        } elseif (! $keysComplete) {
            $issues[] = ['code' => 'answer_key_incomplete', 'message' => 'A valid saved answer is missing for one or more mapped items.'];
        }

        $pages = [];
        foreach (range(1, $pageCount) as $pageNo) {
            $asset = $dimensionsByPage[$pageNo]['asset'];
            $pages[] = [
                'page_no' => $pageNo,
                'width_px' => $dimensionsByPage[$pageNo]['width'],
                'height_px' => $dimensionsByPage[$pageNo]['height'],
                'raster_url' => $rasterUrl($pageNo),
                'raster_sha256' => strtolower((string) $asset->sha256),
                'bubbles' => $bubblesByPage[$pageNo],
            ];
        }

        $ready = $mapComplete && $keysComplete && $allGeometryReady;

        return [
            'state' => $ready ? 'ready' : 'incomplete',
            'unavailable_code' => null,
            'page_count' => $pageCount,
            'pages' => $pages,
            'answers' => $ready ? array_map(
                static fn (string $uid, string $token): array => [
                    'physical_item_uid' => $uid,
                    'correct_option_code' => $token,
                ],
                array_keys($keysByUid),
                array_values($keysByUid),
            ) : [],
            'issues' => $ready ? [] : $issues,
            'context' => $context,
        ];
    }

    /** @return array<string, mixed> */
    private function unavailable(string $code, array $context): array
    {
        return [
            'state' => 'unavailable',
            'unavailable_code' => $code,
            'page_count' => 0,
            'pages' => [],
            'answers' => [],
            'issues' => [],
            'context' => $context,
        ];
    }

    /** @param array<string,mixed> $metadata */
    private function rasterDimension(array $metadata, string $key, string $pxKey): ?int
    {
        $value = $metadata[$key] ?? $metadata[$pxKey] ?? null;
        if ($value === null && is_array($metadata['dimensions_px'] ?? null)) {
            $value = $metadata['dimensions_px'][$key === 'width' ? 0 : 1] ?? null;
        }

        return $this->positiveInteger($value) ? (int) $value : null;
    }

    /** @param array<string,mixed> $page @return array{left:int,top:int}|null */
    private function insetsForPage(string $manifestMode, array $page, int $nativeWidth, int $nativeHeight, int $rasterWidth, int $rasterHeight, string $versionMode): ?array
    {
        if ($manifestMode !== $versionMode) {
            return null;
        }
        if ($versionMode === 'document_bound') {
            return $nativeWidth === $rasterWidth && $nativeHeight === $rasterHeight
                ? ['left' => 0, 'top' => 0]
                : null;
        }
        if ($versionMode !== 'fiducial_anchored') {
            return null;
        }
        $insets = $page['markers']['insets_px'] ?? null;
        if (! is_array($insets)) {
            return null;
        }
        foreach (['left', 'top', 'right', 'bottom'] as $side) {
            if (! array_key_exists($side, $insets) || ! is_int($insets[$side]) || $insets[$side] < 0) {
                return null;
            }
        }

        return $nativeWidth + $insets['left'] + $insets['right'] === $rasterWidth
            && $nativeHeight + $insets['top'] + $insets['bottom'] === $rasterHeight
            ? ['left' => $insets['left'], 'top' => $insets['top']]
            : null;
    }

    private function questionNumber(string $nativeLabel): ?int
    {
        if (preg_match('/_Q_(\d+)\z/', $nativeLabel, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }

    private function nonNegativeInteger(mixed $value): bool
    {
        return is_numeric($value) && is_finite((float) $value) && (float) (int) $value === (float) $value && (int) $value >= 0;
    }

    private function positiveInteger(mixed $value): bool
    {
        return is_numeric($value) && is_finite((float) $value) && (float) (int) $value === (float) $value && (int) $value > 0;
    }

    private function assetIntegrityMatches(mixed $disk, OmrTemplateAsset $asset): bool
    {
        $stream = $disk->readStream((string) $asset->path);
        if (! is_resource($stream)) {
            return false;
        }
        $hashContext = hash_init('sha256');
        $bytes = 0;
        try {
            while (! feof($stream)) {
                $chunk = fread($stream, 8192);
                if ($chunk === false) {
                    return false;
                }
                if ($chunk === '') {
                    break;
                }
                $bytes += strlen($chunk);
                hash_update($hashContext, $chunk);
            }
        } finally {
            fclose($stream);
        }

        return $bytes === (int) $asset->bytes
            && hash_equals(strtolower((string) $asset->sha256), hash_final($hashContext));
    }
}
