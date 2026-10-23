<?php

declare(strict_types=1);

namespace App\Support\Media;

final readonly class SanitizedImage
{
    public function __construct(
        public string $bytes,
        public string $mime,
        public string $extension,
        public int $width,
        public int $height,
        public int $byteSize,
        public string $sha256,
    ) {}
}
