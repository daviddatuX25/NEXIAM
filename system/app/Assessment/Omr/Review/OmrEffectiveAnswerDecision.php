<?php

declare(strict_types=1);

namespace App\Assessment\Omr\Review;

final class OmrEffectiveAnswerDecision
{
    /**
     * @return array{state:string, option:string|null, source:string, stale:bool, reviewed:bool, machine:bool}
     */
    public static function resolve(
        string $machineState,
        ?string $machineOption,
        ?OmrReviewDecision $reviewDecision,
        ?string $reviewOption,
        bool $reviewStale,
    ): array {
        if ($reviewStale) {
            return self::result('unresolved', null, 'none', true, false, false);
        }

        if ($reviewDecision === OmrReviewDecision::ConfirmMachine || $reviewDecision === OmrReviewDecision::SetOption) {
            return self::result('selected', $reviewOption, 'review', false, true, false);
        }

        if ($reviewDecision === OmrReviewDecision::Blank) {
            return self::result('blank', null, 'review', false, true, false);
        }

        if ($reviewDecision === OmrReviewDecision::Unresolved) {
            return self::result('unresolved', null, 'none', false, true, false);
        }

        if ($machineState === 'marked' && $machineOption !== null) {
            return self::result('selected', $machineOption, 'machine', false, false, true);
        }

        if ($machineState === 'blank') {
            return self::result('blank', null, 'machine', false, false, true);
        }

        return self::result('unresolved', null, 'none', false, false, false);
    }

    /**
     * @return array{state:string, option:string|null, source:string, stale:bool, reviewed:bool, machine:bool}
     */
    private static function result(
        string $state,
        ?string $option,
        string $source,
        bool $stale,
        bool $reviewed,
        bool $machine,
    ): array {
        return compact('state', 'option', 'source', 'stale', 'reviewed', 'machine');
    }
}
