<?php

namespace App\Assessment\Omr;

use RuntimeException;

use function getimagesizefromstring;
use function hash;
use function is_array;

/** Immutable identity of the exact raster bytes sent to the inference worker. */
final readonly class OmrSourceImage
{
    public function __construct(
        public int $width,
        public int $height,
        public int $orientation,
        public string $inputHash,
    ) {
        if ($width < 1 || $height < 1 || $orientation < 1 || $orientation > 8 || preg_match('/^[a-f0-9]{64}$/', $inputHash) !== 1) {
            throw new RuntimeException('OMR source image identity is invalid.');
        }
    }

    public static function fromBytes(string $bytes): self
    {
        $dimensions = @getimagesizefromstring($bytes);
        if (! is_array($dimensions) || (int) ($dimensions[0] ?? 0) < 1 || (int) ($dimensions[1] ?? 0) < 1) {
            throw new RuntimeException('The OMR source image cannot be decoded.');
        }

        return new self(
            width: (int) $dimensions[0],
            height: (int) $dimensions[1],
            orientation: OmrQuarterTurnNormalizer::exifOrientation($bytes),
            inputHash: hash('sha256', $bytes),
        );
    }

    /** @return array{width:int,height:int,orientation:int,input_hash:string} */
    public function toArray(): array
    {
        return [
            'width' => $this->width,
            'height' => $this->height,
            'orientation' => $this->orientation,
            'input_hash' => $this->inputHash,
        ];
    }
}
