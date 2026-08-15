<?php

namespace App\Assessment\Omr;

/**
 * Per-item detection output. `selectedOption` is null unless the provider
 * reports one observed mark; `state` explains the observation. Correctness is
 * derived by NEXIAM after validation against its pinned answer key.
 */
final readonly class OmrDetectedItem
{
    public const STATE_MARKED = 'marked';
    public const STATE_BLANK = 'blank';
    public const STATE_AMBIGUOUS = 'ambiguous';
    public const STATE_UNREADABLE = 'unreadable';

    public const STATES = [self::STATE_MARKED, self::STATE_BLANK, self::STATE_AMBIGUOUS, self::STATE_UNREADABLE];

    public function __construct(
        public int $position,
        public string $areaCode,
        public int $questionNo,
        public ?string $selectedOption,
        public string $state,
        public ?float $confidence,
        public OmrRegion $region,
    ) {
    }

    public function isMarked(): bool
    {
        return $this->state === self::STATE_MARKED;
    }
}
