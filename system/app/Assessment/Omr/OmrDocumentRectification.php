<?php

namespace App\Assessment\Omr;

use function array_is_list;
use function count;
use function is_array;
use function is_finite;
use function is_int;
use function is_numeric;
use function is_string;
use function preg_match;

/** The common fail-closed acceptance and comparison rules for v4 document rectification. */
final class OmrDocumentRectification
{
    /** @param array<string, mixed>|null $rectification */
    public static function isAccepted(?array $rectification): bool
    {
        if ($rectification !== null && ($rectification['crop_policy'] ?? null) === 'keep_original') {
            return ($rectification['applied'] ?? null) === false
                && ($rectification['warp_count'] ?? null) === 0;
        }
        if ($rectification === null
            || ($rectification['attempted'] ?? null) !== true
            || ($rectification['applied'] ?? null) !== true
            || ($rectification['mode'] ?? null) !== OmrRegistrationMode::DocumentBound->value
            || ($rectification['status'] ?? null) !== 'success'
            || ($rectification['plausible_candidate_count'] ?? null) !== 1
            || ! is_int($rectification['selected_candidate_index'] ?? null)
            || $rectification['selected_candidate_index'] < 0
            || ($rectification['homography_count'] ?? null) !== 1
            || ($rectification['warp_count'] ?? null) !== 1
            || ! is_array($rectification['document_gates'] ?? null)
            || ($rectification['document_gates']['accepted'] ?? null) !== true
            || ! is_array($rectification['content_gate'] ?? null)
            || ($rectification['content_gate']['accepted'] ?? null) !== true
            || ! is_string($rectification['homography_hash'] ?? null)
            || preg_match('/^sha256:[a-f0-9]{64}$/', $rectification['homography_hash']) !== 1) {
            return false;
        }

        $quad = $rectification['quad_normalized'] ?? null;
        if (! is_array($quad) || ! array_is_list($quad) || count($quad) !== 4) {
            return false;
        }

        foreach ($quad as $point) {
            if (! is_array($point) || ! array_is_list($point) || count($point) !== 2) {
                return false;
            }
            foreach ($point as $coordinate) {
                if (! is_numeric($coordinate) || ! is_finite((float) $coordinate)
                    || (float) $coordinate < 0.0 || (float) $coordinate > 1.0) {
                    return false;
                }
            }
        }

        return true;
    }

    /** @param array<string, mixed>|null $left @param array<string, mixed>|null $right */
    public static function sameSelectedWarp(?array $left, ?array $right): bool
    {
        if (($left['crop_policy'] ?? null) === 'keep_original'
            || ($right['crop_policy'] ?? null) === 'keep_original') {
            return self::isAccepted($left)
                && self::isAccepted($right)
                && $left === $right;
        }

        return self::isAccepted($left)
            && self::isAccepted($right)
            && $left['selected_candidate_index'] === $right['selected_candidate_index']
            && $left['quad_normalized'] === $right['quad_normalized']
            && $left['homography_hash'] === $right['homography_hash'];
    }
}
