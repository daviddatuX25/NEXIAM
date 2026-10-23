<?php

namespace App\Assessment\Omr\Canonical;

use App\Assessment\Omr\OmrRegistrationMode;
use App\Models\Assessment\OmrMarkerProfile;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use JsonException;
use RuntimeException;

use function array_keys;
use function count;
use function hash;
use function is_array;
use function json_encode;
use function ksort;
use function range;

/**
 * Deep module: Canonical Geometry Identity (OpenSpec 35.12).
 *
 * Single authority for computing and comparing the immutable physical/recognition
 * geometry of an OMR template authoring draft or canonical sheet definition.
 *
 * All recognition and printable geometry is derived from the server-owned
 * CanonicalSheetDefinition. Move-away and move-back to the same visible/canonical
 * geometry yields the exact same identity hash.
 */
final class OmrCanonicalGeometryIdentity
{
    /**
     * Compute canonical geometry identity from an authoring draft.
     */
    public static function forDraft(
        OmrTemplateAuthoringDraft $draft,
        ?OmrMarkerProfile $profile = null,
        ?int $candidateVersion = null,
    ): string {

        if ($draft->registration_mode === OmrRegistrationMode::DocumentBound) {
            if ($profile !== null) {
                throw new RuntimeException('canonical_geometry_identity_failed: document-bound drafts cannot select a marker profile.');
            }
            $markerProfile = null;
        } else {
            $markerProfile = $profile ?? $draft->markerProfile;
            if ($markerProfile === null && $draft->marker_profile_id !== null) {
                $markerProfile = OmrMarkerProfile::query()->find($draft->marker_profile_id);
            }
            if ($markerProfile === null) {
                throw new RuntimeException('canonical_geometry_identity_failed: selected marker profile is unavailable.');
            }
        }

        $definition = CanonicalSheetDefinition::fromDraft($draft, $markerProfile, $candidateVersion);

        return self::fromDefinition($definition);
    }

    /**
     * Compute canonical geometry identity from a normalized CanonicalSheetDefinition.
     */
    public static function fromDefinition(CanonicalSheetDefinition $definition): string
    {
        $payload = [
            'registration_mode' => $definition->registrationMode->value,
            'qr_mode' => $definition->qrMode->value,
            'compiled_pages' => $definition->compiledPages,
            'paper' => [
                'width_mm' => (float) ($definition->paper['width_mm'] ?? 210.0),
                'height_mm' => (float) ($definition->paper['height_mm'] ?? 297.0),
                'width_px' => (int) ($definition->paper['width_px'] ?? 0),
                'height_px' => (int) ($definition->paper['height_px'] ?? 0),
            ],
            'insets_mm' => [
                'top' => (float) ($definition->insetsMm['top'] ?? 0.0),
                'right' => (float) ($definition->insetsMm['right'] ?? 0.0),
                'bottom' => (float) ($definition->insetsMm['bottom'] ?? 0.0),
                'left' => (float) ($definition->insetsMm['left'] ?? 0.0),
            ],
            'insets_px' => [
                'top' => (int) ($definition->insetsPx['top'] ?? 0),
                'right' => (int) ($definition->insetsPx['right'] ?? 0),
                'bottom' => (int) ($definition->insetsPx['bottom'] ?? 0),
                'left' => (int) ($definition->insetsPx['left'] ?? 0),
            ],
            'frame' => [
                'width' => (int) ($definition->frame['width'] ?? 0),
                'height' => (int) ($definition->frame['height'] ?? 0),
            ],
            'field_blocks' => self::normalizeFieldBlocksForIdentity($definition->fieldBlocks),
            'visual_components' => self::normalizeVisualComponentsForIdentity($definition->visualComponents),
            'overlay' => self::normalizeOverlayForIdentity($definition->overlay),
            'settings' => [
                'paper_width_mm' => (float) ($definition->settings['paper_width_mm'] ?? 210.0),
                'paper_height_mm' => (float) ($definition->settings['paper_height_mm'] ?? 297.0),
                'alignment' => (string) ($definition->settings['alignment'] ?? OmrPageDimensionMath::ALIGN_CENTER),
            ],
            'marker_profile_id' => $definition->markerProfileId,
            ...($definition->sourceKind === OmrSourcePolicy::UPLOADED_ORIGINAL ? [
                'source_kind' => $definition->sourceKind,
                'declared_inventory' => $definition->declaredInventory,
                'identity_method' => $definition->identityMethod,
            ] : []),
        ];

        try {
            return hash('sha256', json_encode(self::sortRecursive($payload), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        } catch (JsonException $exception) {
            throw new RuntimeException('canonical_geometry_identity_failed', 0, $exception);
        }
    }

    /**
     * @param  array<string, mixed>  $fieldBlocks
     * @return array<string, mixed>
     */
    private static function normalizeFieldBlocksForIdentity(array $fieldBlocks): array
    {
        $normalized = [];
        foreach ($fieldBlocks as $name => $block) {
            if (! is_array($block)) {
                continue;
            }
            $normalizedBlock = [
                'fieldType' => (string) ($block['fieldType'] ?? ''),
                'direction' => (string) ($block['direction'] ?? ''),
                'origin' => [
                    (int) ($block['origin'][0] ?? 0),
                    (int) ($block['origin'][1] ?? 0),
                ],
                'bubbleDimensions' => [
                    (int) ($block['bubbleDimensions'][0] ?? 0),
                    (int) ($block['bubbleDimensions'][1] ?? 0),
                ],
                'bubblesGap' => (int) ($block['bubblesGap'] ?? 0),
                'labelsGap' => (int) ($block['labelsGap'] ?? 0),
                'fieldLabels' => array_values((array) ($block['fieldLabels'] ?? [])),
                'bubbleValues' => array_values((array) ($block['bubbleValues'] ?? [])),
                'displayLabel' => (string) ($block['displayLabel'] ?? ''),
                'showSectionHeader' => (bool) ($block['showSectionHeader'] ?? true),
                'page_no' => (int) ($block['page_no'] ?? 1),
            ];
            if (isset($block['bubblePositions'])) {
                $normalizedBlock['bubblePositions'] = array_map(
                    static fn (array $row): array => array_map(
                        static fn (array $position): array => [(int) $position[0], (int) $position[1]],
                        array_values($row),
                    ),
                    array_values((array) $block['bubblePositions']),
                );
            }
            $normalized[(string) $name] = $normalizedBlock;
        }
        ksort($normalized);

        return $normalized;
    }

    /**
     * @param  list<array<string, mixed>>  $components
     * @return list<array<string, mixed>>
     */
    private static function normalizeVisualComponentsForIdentity(array $components): array
    {
        $normalized = [];
        foreach ($components as $component) {
            if (! is_array($component) || ! isset($component['id'])) {
                continue;
            }
            $normalized[] = [
                'id' => (string) $component['id'],
                'type' => (string) ($component['type'] ?? ''),
                'page_no' => (int) ($component['page_no'] ?? 1),
                'posX' => round((float) ($component['posX'] ?? 0), 2),
                'posY' => round((float) ($component['posY'] ?? 0), 2),
                'w_mm' => round((float) ($component['w_mm'] ?? 0), 2),
                'h_mm' => round((float) ($component['h_mm'] ?? 0), 2),
                'title' => (string) ($component['title'] ?? ''),
                'subtitle' => (string) ($component['subtitle'] ?? ''),
                'subjectName' => (string) ($component['subjectName'] ?? ''),
                'aptitudeAreaCode' => (string) ($component['aptitudeAreaCode'] ?? ''),
                'name' => (string) ($component['name'] ?? ''),
                'label' => (string) ($component['label'] ?? ''),
                'content' => (string) ($component['content'] ?? ''),
                'text' => (string) ($component['text'] ?? ''),
                'questionsCount' => (int) ($component['questionsCount'] ?? 0),
                'startQ' => (int) ($component['startQ'] ?? 1),
                'optionsCount' => (int) ($component['optionsCount'] ?? 0),
                'cols' => (int) ($component['cols'] ?? 1),
                'customValuesStr' => (string) ($component['customValuesStr'] ?? ''),
                'digitsCount' => (int) ($component['digitsCount'] ?? 0),
                'bubbleDiameterMm' => round((float) ($component['bubbleDiameterMm'] ?? 4.5), 2),
                'bubblePitchMm' => round((float) ($component['bubblePitchMm'] ?? 6.0), 2),
                'rowPitchMm' => round((float) ($component['rowPitchMm'] ?? 6.8), 2),
                'showSectionHeader' => (bool) ($component['showSectionHeader'] ?? true),
            ];
        }

        usort($normalized, static fn (array $a, array $b): int => strcmp($a['id'], $b['id']));

        return array_values($normalized);
    }

    /**
     * @param  array<string, mixed>  $overlay
     * @return array<string, mixed>
     */
    private static function normalizeOverlayForIdentity(array $overlay): array
    {
        $boxes = [];
        foreach ((array) ($overlay['boxes'] ?? []) as $box) {
            if (! is_array($box)) {
                continue;
            }
            $boxes[] = [
                'id' => (string) ($box['id'] ?? ''),
                'page_no' => (int) ($box['page_no'] ?? 1),
                'x' => round((float) ($box['x'] ?? 0), 2),
                'y' => round((float) ($box['y'] ?? 0), 2),
                'width' => round((float) ($box['width'] ?? 0), 2),
                'height' => round((float) ($box['height'] ?? 0), 2),
                'type' => (string) ($box['type'] ?? ''),
                'content' => (string) ($box['content'] ?? ''),
                'is_qr_reservation' => (bool) ($box['is_qr_reservation'] ?? false),
            ];
        }
        usort($boxes, static fn (array $a, array $b): int => strcmp($a['id'], $b['id']));

        return [
            'boxes' => array_values($boxes),
        ];
    }

    private static function sortRecursive(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (array_keys($value) !== range(0, count($value) - 1)) {
            ksort($value);
        }
        foreach ($value as $key => $item) {
            $value[$key] = self::sortRecursive($item);
        }

        return $value;
    }
}
