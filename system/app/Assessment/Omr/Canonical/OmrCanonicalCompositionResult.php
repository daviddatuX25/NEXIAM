<?php

namespace App\Assessment\Omr\Canonical;

final readonly class OmrCanonicalCompositionResult
{
    /**
     * @param  list<array{page_no:int,bytes:string,mime:string,width:int,height:int,sha256:string,metadata:array<string,mixed>}>  $pages
     * @param  array<string, mixed>  $settings
     */
    public function __construct(
        public array $pages,
        public string $pdfBytes,
        public string $compositionHash,
        public array $settings,
    ) {}
}
