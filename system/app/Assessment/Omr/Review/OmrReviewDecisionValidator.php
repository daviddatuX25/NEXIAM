<?php

declare(strict_types=1);

namespace App\Assessment\Omr\Review;

use RuntimeException;

final class OmrReviewDecisionValidator
{
    /**
     * @param  list<string>  $supportedOptions
     * @return array{0:string|null,1:string|null,2:string|null}
     */
    public static function validate(
        string $machineState,
        ?string $machineOption,
        array $supportedOptions,
        OmrReviewDecision $decision,
        ?string $selectedOption,
        ?string $reasonCode,
        ?string $note,
        string $codePrefix = 'omr_review',
    ): array {
        if ($note !== null && mb_strlen($note) > 2000) {
            throw new RuntimeException($codePrefix.'_note_too_long');
        }
        if ($reasonCode !== null && preg_match('/^[a-z][a-z0-9_]{0,63}$/', $reasonCode) !== 1) {
            throw new RuntimeException($codePrefix.'_reason_code_invalid');
        }

        if ($decision === OmrReviewDecision::ConfirmMachine) {
            if ($machineState !== 'marked' || $machineOption === null) {
                throw new RuntimeException($codePrefix.'_confirm_machine_invalid');
            }
            if ($selectedOption !== null && $selectedOption !== $machineOption) {
                throw new RuntimeException($codePrefix.'_selection_mismatch');
            }

            return [$machineOption, $reasonCode, $note];
        }

        if ($decision === OmrReviewDecision::SetOption) {
            if ($selectedOption === null || ! in_array($selectedOption, $supportedOptions, true)) {
                throw new RuntimeException($codePrefix.'_option_invalid');
            }
            if ($reasonCode === null) {
                throw new RuntimeException($codePrefix.'_reason_required');
            }
            if ($machineState === 'marked' && $machineOption !== null && $machineOption !== $selectedOption && $note === null) {
                throw new RuntimeException($codePrefix.'_note_required');
            }

            return [$selectedOption, $reasonCode, $note];
        }

        if ($selectedOption !== null) {
            throw new RuntimeException($codePrefix.'_selection_forbidden');
        }

        if ($decision === OmrReviewDecision::Blank) {
            if ($machineState === 'marked' && $machineOption !== null && $reasonCode === null) {
                throw new RuntimeException($codePrefix.'_reason_required');
            }

            return [null, $reasonCode, $note];
        }

        if ($reasonCode === null) {
            throw new RuntimeException($codePrefix.'_reason_required');
        }

        return [null, $reasonCode, $note];
    }
}
