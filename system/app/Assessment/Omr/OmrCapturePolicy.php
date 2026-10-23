<?php

namespace App\Assessment\Omr;

use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use RuntimeException;

use function is_array;
use function is_int;
use function is_string;
use function max;

/**
 * Versioned capture-policy contract `nexiam.omr.capture-policy.v1`.
 *
 * template-versioned configuration path; this class derives the full
 * per-version policy from immutable native geometry/bundle identity and the
 * currently configured worker execution identity. It never duplicates fieldBlocks,
 * bubble positions, or answer-key semantics — it may only reference native geometry.
 */
final readonly class OmrCapturePolicy
{
    public const CONTRACT = 'nexiam.omr.capture-policy.v1';

    private const CONFIG_KEY = 'omr.capture-policy.v1';

    /**
     * @return array<string, mixed>
     */
    public static function frozen(): array
    {
        $policy = config(self::CONFIG_KEY);

        if (! is_array($policy) || ($policy['contract'] ?? null) !== self::CONTRACT) {
            throw new RuntimeException('The frozen nexiam.omr.capture-policy.v1 contract is not loaded.');
        }

        return $policy;
    }

    /**
     * Derive the served capture policy for one solved marker-bearing template.
     *
     * @param  array<string, mixed>  $template  the solved native template (pages carry marker metadata)
     * @param  array<string, mixed>  $context  {solver_version, bundle_hash, manifest_hash, engine{name,commit,patch_identity}}
     * @return array<string, mixed>
     */
    public static function derive(array $template, array $context): array
    {
        $frozen = self::frozen();
        $marker = self::markerMetadata($template);

        return [
            'contract' => self::CONTRACT,
            'capture_mode' => $frozen['capture_mode'],
            'target_dimensions' => $frozen['target_dimensions'],
            'frame_dimensions' => $marker['frame'],
            'frame_anchor' => $frozen['frame_anchor'],
            'fiducial' => [
                'family' => $marker['family'],
                'asset_name' => $marker['asset_name'],
                'sheet_to_marker_width_ratio' => $frozen['fiducial']['sheet_to_marker_width_ratio'],
            ],
            'quadrant_placement' => [
                'split' => $frozen['quadrant_placement']['split'],
                'corners' => $frozen['quadrant_placement']['corners'],
                'centers' => $marker['centers'],
                'quadrant_membership' => $marker['quadrants'],
                'required_marker_count' => $frozen['quadrant_placement']['required_marker_count'],
            ],
            'identity' => [
                'solver_version' => (string) ($context['solver_version'] ?? ''),
                'native_bundle_hash' => (string) ($context['bundle_hash'] ?? ''),
                'review_manifest_hash' => (string) ($context['manifest_hash'] ?? ''),
                'engine' => [
                    'name' => (string) ($context['engine']['name'] ?? ''),
                    'commit' => (string) ($context['engine']['commit'] ?? ''),
                    'patch_identity' => (string) ($context['engine']['patch_identity'] ?? ''),
                ],
            ],
            'calibration_status' => 'unqualified',
        ];
    }

    /**
     * Derive the served policy for an immutable template version, or null when
     * the version is not marker-bearing. Reads only the persisted native
     * bundle identity — never client geometry.
     *
     * @return array<string, mixed>|null
     */
    public static function forVersion(OmrAnswerSheetTemplateVersion $version): ?array
    {
        $template = is_array($version->native_bundle_json) && isset($version->native_bundle_json['template'])
            ? $version->native_bundle_json['template']
            : null;

        if (! is_array($template) || self::markerMetadata($template) === null) {
            return null;
        }

        return self::derive($template, [
            'solver_version' => (string) $version->layout_solver_version,
            'bundle_hash' => (string) $version->native_bundle_hash,
            'manifest_hash' => (string) $version->review_manifest_hash,
            'engine' => [
                'name' => (string) config('omr.omrchecker.engine_name', ''),
                'commit' => (string) config('omr.omrchecker.engine_commit', ''),
                'patch_identity' => (string) config('omr.omrchecker.patch_identity', ''),
            ],
        ]);
    }

    /**
     * Read the marker contract off a solved template (first marker-bearing
     * page), or null when the template is marker-less.
     *
     * @param  array<string, mixed>  $template
     * @return array<string, mixed>|null
     */
    public static function markerMetadata(array $template): ?array
    {
        $pages = $template['pages'] ?? null;
        if (! is_array($pages)) {
            $pages = [['markers' => $template['markers'] ?? null, 'pageDimensions' => $template['pageDimensions'] ?? null]];
        }

        foreach ($pages as $page) {
            if (! is_array($page) || ! is_array($page['markers'] ?? null)) {
                continue;
            }
            $markers = $page['markers'];
            $frame = $page['pageDimensions'] ?? null;

            if (! is_array($frame)
                || ! is_int($frame[0] ?? null) || $frame[0] <= 0
                || ! is_int($frame[1] ?? null) || $frame[1] <= 0
                || ! is_string($markers['family'] ?? null)
                || ! is_string($markers['asset_name'] ?? null)
                || ! is_array($markers['centers'] ?? null)
                || ! is_array($markers['quadrants'] ?? null)) {
                throw new RuntimeException('The marker-bearing template carries malformed marker metadata.');
            }

            return [
                'family' => $markers['family'],
                'asset_name' => $markers['asset_name'],
                'size' => (int) $markers['size'],
                'quiet_zone' => (int) $markers['quiet_zone'],
                'inset' => (int) $markers['inset'],
                'insets_px' => is_array($markers['insets_px'] ?? null) ? $markers['insets_px'] : null,
                'paper' => $markers['paper'],
                'frame' => [$frame[0], $frame[1]],
                'centers' => $markers['centers'],
                'quadrants' => $markers['quadrants'],
            ];
        }

        return null;
    }

    /**
     * Frame derivation used by the solver: primitives are frozen; the inset is
     * their sum and the frame is paper minus twice the inset.
     *
     * @param  array{0: int, 1: int}  $paper
     * @return array{inset: int, frame: array{0: int, 1: int}, marker: int, quiet_zone: int}
     */
    public static function frameBudget(array $paper): array
    {
        $frozen = self::frozen();
        $anchor = $frozen['frame_anchor'];

        $inset = (int) $anchor['printer_unprintable_margin']
            + (int) $anchor['marker_size']
            + (int) $anchor['marker_quiet_zone'];

        return [
            'inset' => $inset,
            'frame' => [max(1, $paper[0] - 2 * $inset), max(1, $paper[1] - 2 * $inset)],
            'marker' => (int) $anchor['marker_size'],
            'quiet_zone' => (int) $anchor['marker_quiet_zone'],
        ];
    }

    /**
     * Assert the frozen quadrant contract: with a w/2 × h/3 split, corner
     * placement keeps two fiducials in the top third and two in the bottom
     * two-thirds exactly when the inset is below one third of the paper
     * (projected into the processing frame, which is what the engine matches).
     *
     * @param  array{0: int, 1: int}  $paper
     * @param  array{0: int, 1: int}  $frame
     */
    public static function assertQuadrantPlacement(array $paper, array $frame, int $inset): void
    {
        [$paperW, $paperH] = $paper;
        [$frameW, $frameH] = $frame;
        $scaleX = $frameW / $paperW;
        $scaleY = $frameH / $paperH;

        $centers = [
            [$inset * $scaleX, $inset * $scaleY],                     // top-left
            [($paperW - $inset) * $scaleX, $inset * $scaleY],         // top-right
            [$inset * $scaleX, ($paperH - $inset) * $scaleY],         // bottom-left
            [($paperW - $inset) * $scaleX, ($paperH - $inset) * $scaleY], // bottom-right
        ];

        [$topLeft, $topRight, $bottomLeft, $bottomRight] = $centers;
        $third = $frameH / 3;

        $ok = $topLeft[0] < $frameW / 2 && $topLeft[1] < $third
            && $topRight[0] >= $frameW / 2 && $topRight[1] < $third
            && $bottomLeft[0] < $frameW / 2 && $bottomLeft[1] >= 2 * $third
            && $bottomRight[0] >= $frameW / 2 && $bottomRight[1] >= 2 * $third;

        if (! $ok) {
            throw new RuntimeException(sprintf(
                'Marker frame geometry violates the w/2 × h/3 quadrant contract (inset %d on %d×%d paper).',
                $inset,
                $paperW,
                $paperH,
            ));
        }
    }
}
