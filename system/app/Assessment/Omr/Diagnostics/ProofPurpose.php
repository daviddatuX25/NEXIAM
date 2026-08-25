<?php

namespace App\Assessment\Omr\Diagnostics;

/**
 * What a proof/challenge run is allowed to prove (OpenSpec 35.13).
 *
 * `digital` and `quick_rehearsal` plans never satisfy physical qualification;
 * only `full_qualification` combined with a qualification-capable
 * (compiled-version) target can feed the derived qualification gate (AD-7).
 */
enum ProofPurpose: string
{
    case Digital = 'digital';
    case QuickRehearsal = 'quick_rehearsal';
    case FullQualification = 'full_qualification';

    /** A purpose that may feed physical qualification derivation. */
    public function isQualification(): bool
    {
        return $this === self::FullQualification;
    }
}
