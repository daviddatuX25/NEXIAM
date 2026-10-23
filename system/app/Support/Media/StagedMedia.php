<?php

namespace App\Support\Media;

final readonly class StagedMedia
{
    public function __construct(
        public string $disk,
        public string $path,
        public string $sha256,
        public int $byteSize,
        public string $mime,
    ) {}
}
