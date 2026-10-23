<?php

declare(strict_types=1);

namespace App\DocumentTemplate\Import;

final class DocxEmailImportResult
{
    /**
     * @param  array<string, mixed>  $candidateBlueprint
     * @param  array{report_sha256: string, source_sha256: string, converter_version: string, mapped_blocks_count: int, mapped: list<array{source_part: string, source_location: string, kind: string}>, findings: list<array{id: string, source_part: string, source_location: string, kind: string, severity: string, disposition: string, source_text: string}>}  $report
     * @param  array<string, array{member_name: string, sha256: string, width: int, height: int, mime: string, size_bytes: int, bytes: string}>  $images
     */
    public function __construct(
        public readonly array $candidateBlueprint,
        public readonly array $report,
        public readonly array $images,
        public readonly DocxPackageInspectionResult $inspection,
    ) {}
}
