<?php

namespace App\Assessment\Omr;

/** Resolves an optional trusted turn from a canonical, EXIF-normalized capture. */
interface OmrCaptureOrientationPort
{
    public function inspect(
        string $bytes,
        string $mime,
        string $challengeCode,
        int $pageNo,
    ): ?int;
}
