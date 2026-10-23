<?php

namespace App\Assessment\Omr\Review;

enum OmrReviewDecision: string
{
    case ConfirmMachine = 'confirm_machine';

    case SetOption = 'set_option';

    case Blank = 'blank';

    case Unresolved = 'unresolved';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $decision): string => $decision->value, self::cases());
    }

    public function acceptsSelectedOption(): bool
    {
        return in_array($this, [self::ConfirmMachine, self::SetOption], true);
    }

    public function isUnresolved(): bool
    {
        return $this === self::Unresolved;
    }
}
