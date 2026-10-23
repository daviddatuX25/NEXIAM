<?php

namespace App\Assessment\Omr;

/**
 * Result of resource-only capture preflight.
 *
 * A rejected result contains a bounded, safe code/detail pair. It never
 * contains marker, crop, worker, scoring, or workflow information.
 */
final readonly class OmrPreflightResult
{
    public const OUTCOME_ACCEPTED = 'accepted';

    public const OUTCOME_REJECTED = 'rejected';

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $outcome,
        public string $mime,
        public int $byteCount,
        public string $sha256,
        public ?int $width,
        public ?int $height,
        public int $pageCount,
        public ?string $code = null,
        public ?string $detail = null,
        public array $metadata = [],
    ) {}

    public static function accepted(OmrCaptureInput $input, int $pageCount, ?int $width, ?int $height, array $metadata = []): self
    {
        return new self(
            outcome: self::OUTCOME_ACCEPTED,
            mime: strtolower(trim($input->mime)),
            byteCount: $input->byteCount,
            sha256: $input->sha256,
            width: $width,
            height: $height,
            pageCount: $pageCount,
            metadata: $metadata,
        );
    }

    public static function rejected(OmrCaptureInput $input, string $code, string $detail, ?int $pageCount = null, array $metadata = []): self
    {
        return new self(
            outcome: self::OUTCOME_REJECTED,
            mime: strtolower(trim($input->mime)),
            byteCount: $input->byteCount,
            sha256: $input->sha256,
            width: $input->width,
            height: $input->height,
            pageCount: $pageCount ?? max(0, $input->pageCount),
            code: $code,
            detail: $detail,
            metadata: $metadata,
        );
    }

    public function succeeded(): bool
    {
        return $this->outcome === self::OUTCOME_ACCEPTED;
    }
}
