<?php

declare(strict_types=1);

namespace App\Communications;

final readonly class AnnouncementSource
{
    public function __construct(
        public string $key,
        public string $label,
        public int $institutionId,
        public ?string $office,
        public ?string $program,
    ) {}
}
