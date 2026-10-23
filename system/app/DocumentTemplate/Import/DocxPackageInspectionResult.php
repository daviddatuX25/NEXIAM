<?php

declare(strict_types=1);

namespace App\DocumentTemplate\Import;

final class DocxPackageInspectionResult
{
    /**
     * @param  array<string, array{member_name: string, sha256: string, width: int, height: int, mime: string, size_bytes: int, bytes: string}>  $images
     * @param  array<string, list<array{id: string, type: string, target: string, target_mode: ?string}>>  $relationships
     * @param  array<string, string>  $documentRels  rId => target
     * @param  array<string, string>  $allXmlParts  member_name => xml_content
     */
    public function __construct(
        public readonly int $fileSizeBytes,
        public readonly string $sha256,
        public readonly int $memberCount,
        public readonly int $uncompressedBytes,
        public readonly array $images,
        public readonly array $relationships,
        public readonly string $documentXml,
        public readonly array $documentRels,
        public readonly array $allXmlParts,
    ) {}
}
