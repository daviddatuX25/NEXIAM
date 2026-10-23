<?php

namespace App\Assessment\Omr;

/** Configuration snapshot for the read-only capture preflight boundary. */
final readonly class OmrCapturePreflightLimits
{
    public function __construct(
        public int $maxUploadBytes,
        public int $maxPixelsPerPage,
        public int $maxPagesPerSource,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            maxUploadBytes: (int) config('omr.canonical.limits.max_upload_bytes', 25 * 1024 * 1024),
            maxPixelsPerPage: (int) config('omr.canonical.limits.max_pixels_per_page', 20_000_000),
            maxPagesPerSource: (int) config('omr.canonical.limits.max_pages_per_source', 20),
        );
    }
}
