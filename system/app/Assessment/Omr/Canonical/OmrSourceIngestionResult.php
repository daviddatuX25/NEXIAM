<?php

namespace App\Assessment\Omr\Canonical;

final readonly class OmrSourceIngestionResult
{
    /**
     * @param list<array{page_no:int,path:string,mime:string,bytes:int,sha256:string,width:int,height:int,width_mm:float|null,height_mm:float|null,orientation:string,metadata:array<string,mixed>}> $pages
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $kind,
        public string $sourcePath,
        public string $sourceSha256,
        public string $converterVersion,
        public string $settingsHash,
        public array $pages,
        public array $metadata = [],
    ) {
    }

    public function pageCount(): int
    {
        return count($this->pages);
    }
}
