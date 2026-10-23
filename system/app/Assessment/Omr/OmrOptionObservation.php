<?php

namespace App\Assessment\Omr;

use function array_key_exists;
use function is_finite;
use function is_float;
use function is_int;
use function is_string;

/**
 * One provider-owned observation for a declared answer option.
 *
 * The mean is an opaque engine measurement (normally a grayscale mean in the
 * provider's image space); NEXIAM records it but never reinterprets it in the
 * browser. Classification is likewise provider-owned and is intentionally
 * kept as a string so new engine classifications remain auditable.
 */
final readonly class OmrOptionObservation
{
    public function __construct(
        public string $code,
        public ?float $mean,
        public string $classification,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws OmrInvalidDetectionOutput
     */
    public static function fromArray(array $data): self
    {
        $code = $data['code'] ?? null;
        $classification = $data['classification'] ?? null;
        $mean = $data['mean'] ?? null;

        if (! is_string($code) || $code === '') {
            throw new OmrInvalidDetectionOutput('Option observation code is malformed.');
        }
        if (! is_string($classification) || $classification === '') {
            throw new OmrInvalidDetectionOutput("Option observation '{$code}' classification is malformed.");
        }
        if (array_key_exists('mean', $data)
            && $mean !== null
            && (! is_int($mean) && ! is_float($mean) || ! is_finite((float) $mean))) {
            throw new OmrInvalidDetectionOutput("Option observation '{$code}' mean is malformed.");
        }

        return new self(
            code: $code,
            mean: $mean === null ? null : (float) $mean,
            classification: $classification,
        );
    }

    /**
     * @return array{code: string, mean: float|null, classification: string}
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'mean' => $this->mean,
            'classification' => $this->classification,
        ];
    }
}
