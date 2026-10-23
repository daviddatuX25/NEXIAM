<?php

declare(strict_types=1);

namespace App\DocumentTemplate\Import;

use App\Notifications\Templates\Email\NoticeEmailLinkPolicy;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Http\UploadedFile;
use ZipArchive;

class DocxPackageInspector
{
    public const MAX_FILE_BYTES = 10485760; // 10 MiB

    public const MAX_MEMBER_COUNT = 200;

    public const MAX_UNCOMPRESSED_BYTES = 104857600; // 100 MiB

    public const MAX_IMAGE_COUNT = 20;

    public const MAX_IMAGE_BYTES = 2097152; // 2 MiB

    public const MAX_IMAGE_PIXELS = 20000000; // 20 MP

    public const MAX_XML_BYTES = 8388608;

    public const MAX_XML_NODES = 100000;

    public const MAX_XML_DEPTH = 64;

    public const MAX_PROCESSING_SECONDS = 30;

    private int $deadline = 0;

    public function startProcessing(): void
    {
        $this->deadline = hrtime(true) + self::MAX_PROCESSING_SECONDS * 1000000000;
    }

    public const REQUIRED_CORE_PARTS = [
        '[Content_Types].xml',
        '_rels/.rels',
        'word/document.xml',
    ];

    /**
     * Inspect an uploaded or local DOCX file before full inflation.
     *
     * @throws DocxPackageInspectionException
     */
    public function inspect(string|UploadedFile $file): DocxPackageInspectionResult
    {
        $filePath = $file instanceof UploadedFile ? $file->getRealPath() : $file;
        $this->startProcessing();

        if ($file instanceof UploadedFile && ! $file->isValid()) {
            throw new DocxPackageInspectionException('The uploaded DOCX file is invalid.');
        }

        if (! is_string($filePath) || ! is_file($filePath) || ! is_readable($filePath)) {
            throw new DocxPackageInspectionException('The DOCX file does not exist or is not readable.');
        }

        $fileSizeBytes = (int) filesize($filePath);
        if ($fileSizeBytes > self::MAX_FILE_BYTES) {
            throw new DocxPackageInspectionException(
                "DOCX package exceeds maximum file size limit of 10 MiB ({$fileSizeBytes} bytes)."
            );
        }

        $zip = new ZipArchive;
        $opened = $zip->open($filePath, ZipArchive::RDONLY);
        if ($opened !== true) {
            throw new DocxPackageInspectionException('DOCX package is not a valid ZIP archive.');
        }

        try {
            return $this->inspectArchiveMembers($zip, $filePath, $fileSizeBytes);
        } finally {
            $zip->close();
        }
    }

    private function inspectArchiveMembers(ZipArchive $zip, string $filePath, int $fileSizeBytes): DocxPackageInspectionResult
    {
        $memberCount = $zip->numFiles;
        if ($memberCount > self::MAX_MEMBER_COUNT) {
            throw new DocxPackageInspectionException(
                "DOCX package contains {$memberCount} archive members, exceeding the maximum allowed limit of ".self::MAX_MEMBER_COUNT.'.'
            );
        }

        $totalUncompressedBytes = 0;
        $seenMembers = [];
        $seenNormalized = [];
        $imageCount = 0;
        $imageMembers = [];
        $xmlMembers = [];
        $relsMembers = [];

        for ($i = 0; $i < $memberCount; $i++) {
            $stat = $zip->statIndex($i);
            $this->assertWithinDeadline();
            if ($stat === false || ! isset($stat['name']) || ! is_string($stat['name'])) {
                throw new DocxPackageInspectionException('DOCX package contains an unreadable archive member entry.');
            }

            $name = $stat['name'];
            $this->assertSafeMemberPath($name);

            $isDirectory = str_ends_with($name, '/');
            $normalizedName = strtolower(rtrim($name, '/'));

            if (isset($seenNormalized[$normalizedName])) {
                throw new DocxPackageInspectionException("Duplicate archive member detected: {$name}.");
            }
            $seenNormalized[$normalizedName] = true;
            $seenMembers[$name] = $stat;

            $uncompressedSize = (int) ($stat['size'] ?? 0);
            if ($uncompressedSize < 0 || (int) ($stat['encryption_method'] ?? ZipArchive::EM_NONE) !== ZipArchive::EM_NONE) {
                throw new DocxPackageInspectionException('Encrypted or malformed archive member is not permitted.');
            }
            if (preg_match('/\.(xml|rels)$/i', $name) && $uncompressedSize > self::MAX_XML_BYTES) {
                throw new DocxPackageInspectionException('XML member exceeds the bounded XML size limit.');
            }
            $totalUncompressedBytes += $uncompressedSize;
            if ($totalUncompressedBytes > self::MAX_UNCOMPRESSED_BYTES) {
                throw new DocxPackageInspectionException(
                    'Total uncompressed size of DOCX package exceeds 100 MiB limit.'
                );
            }

            if ($isDirectory) {
                continue;
            }

            $this->assertNoMacrosOrExecutables($name);

            $lowerName = strtolower($name);
            $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

            if (str_starts_with($lowerName, 'word/media/') || in_array($extension, ['png', 'jpg', 'jpeg'], true)) {
                $imageCount++;
                if ($imageCount > self::MAX_IMAGE_COUNT) {
                    throw new DocxPackageInspectionException(
                        'DOCX package contains more than '.self::MAX_IMAGE_COUNT." images in word/media/ (found {$imageCount})."
                    );
                }

                if ($uncompressedSize > self::MAX_IMAGE_BYTES) {
                    throw new DocxPackageInspectionException(
                        "Image member {$name} exceeds maximum size limit of 2 MiB ({$uncompressedSize} bytes)."
                    );
                }

                if (in_array($extension, ['svg', 'svgz'], true)) {
                    throw new DocxPackageInspectionException(
                        "Active SVG or vector media is not permitted in DOCX package: {$name}."
                    );
                }

                if (! in_array($extension, ['png', 'jpg', 'jpeg'], true)) {
                    throw new DocxPackageInspectionException(
                        "Unsupported media format in word/media/: {$name}. Only PNG and JPEG images are allowed."
                    );
                }

                $imageMembers[] = $name;
            }
            if (! in_array($extension, ['xml', 'rels', 'png', 'jpg', 'jpeg'], true)) {
                throw new DocxPackageInspectionException('Unsupported binary package member is not permitted.');
            }

            if ($extension === 'xml' || str_ends_with($lowerName, '[content_types].xml')) {
                $xmlMembers[] = $name;
            }

            if ($extension === 'rels') {
                $relsMembers[] = $name;
            }
        }

        // Validate required OOXML core parts
        foreach (self::REQUIRED_CORE_PARTS as $corePart) {
            if (! isset($seenMembers[$corePart])) {
                throw new DocxPackageInspectionException(
                    "Required OOXML core part is missing: {$corePart}."
                );
            }
        }

        // Inspect XML parts for XXE and OLE
        $allXmlParts = [];
        foreach (array_merge($xmlMembers, $relsMembers) as $xmlPartName) {
            $bytes = $this->readMember($zip, $xmlPartName, (int) $seenMembers[$xmlPartName]['size']);
            if (! is_string($bytes)) {
                throw new DocxPackageInspectionException("Unable to read XML archive member: {$xmlPartName}.");
            }

            $this->assertSafeXmlContent($xmlPartName, $bytes);
            $this->parseXml($xmlPartName, $bytes);
            $allXmlParts[$xmlPartName] = $bytes;
        }

        // Inspect and validate relationships
        $relationships = [];
        $documentRels = [];
        foreach ($relsMembers as $relsMemberName) {
            $relsBytes = $allXmlParts[$relsMemberName];
            $parsedRels = $this->parseAndValidateRelationships($relsMemberName, $relsBytes);
            foreach ($parsedRels as $relationship) {
                if ($relationship['target_mode'] !== 'External' && ! isset($seenMembers[$relationship['target']])) {
                    throw new DocxPackageInspectionException('Relationship references a missing package member.');
                }
            }
            $relationships[$relsMemberName] = $parsedRels;

            if ($relsMemberName === 'word/_rels/document.xml.rels') {
                foreach ($parsedRels as $r) {
                    $documentRels[$r['id']] = $r['target'];
                }
            }
        }

        // Inspect image media bytes, signatures, dimensions
        $images = [];
        foreach ($imageMembers as $imageMemberName) {
            $bytes = $this->readMember($zip, $imageMemberName, (int) $seenMembers[$imageMemberName]['size']);
            if (! is_string($bytes)) {
                throw new DocxPackageInspectionException("Unable to read image member: {$imageMemberName}.");
            }

            $images[$imageMemberName] = $this->inspectImage($imageMemberName, $bytes);
        }

        $documentXml = $allXmlParts['word/document.xml'];
        $fileSha256 = hash_file('sha256', $filePath);
        if (! is_string($fileSha256)) {
            throw new DocxPackageInspectionException('Unable to checksum DOCX package.');
        }

        return new DocxPackageInspectionResult(
            fileSizeBytes: $fileSizeBytes,
            sha256: $fileSha256,
            memberCount: $memberCount,
            uncompressedBytes: $totalUncompressedBytes,
            images: $images,
            relationships: $relationships,
            documentXml: $documentXml,
            documentRels: $documentRels,
            allXmlParts: $allXmlParts,
        );
    }

    private function assertSafeMemberPath(string $name): void
    {
        if ($name === '' || preg_match('/[\x00-\x1f\x7f:%?#]/', $name)) {
            throw new DocxPackageInspectionException("Archive member contains invalid null byte: {$name}.");
        }

        if (str_contains($name, '..')) {
            throw new DocxPackageInspectionException("Zip path traversal detected in archive member: {$name}.");
        }

        if (str_starts_with($name, '/') || str_starts_with($name, '\\') || str_contains($name, '\\')) {
            throw new DocxPackageInspectionException("Absolute or backslash path detected in archive member: {$name}.");
        }

        if (preg_match('/^[a-zA-Z]:/', $name)) {
            throw new DocxPackageInspectionException("Absolute drive path detected in archive member: {$name}.");
        }

        if (str_contains($name, '//') || str_starts_with($name, './') || str_contains($name, '/./')) {
            throw new DocxPackageInspectionException("Non-normalized path detected in archive member: {$name}.");
        }
    }

    private function assertNoMacrosOrExecutables(string $name): void
    {
        $lowerName = strtolower($name);
        $basename = strtolower(basename($name));
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if ($basename === 'vbaproject.bin' || str_starts_with($basename, 'vba') || $extension === 'docm' || str_contains($lowerName, 'vba')) {
            throw new DocxPackageInspectionException("DOCX package contains macro files: {$name}.");
        }

        if (in_array($extension, ['exe', 'dll', 'com', 'bat', 'cmd', 'scr', 'ps1', 'vbs', 'jar', 'bin'], true)) {
            throw new DocxPackageInspectionException("DOCX package contains forbidden executable or binary file: {$name}.");
        }

        if (str_starts_with($lowerName, 'word/embeddings/') || str_contains($lowerName, 'embeddings/') || str_contains($lowerName, 'oleobject') || str_starts_with($basename, 'oleobject')) {
            throw new DocxPackageInspectionException("DOCX package contains embedded OLE or external object: {$name}.");
        }
    }

    private function assertSafeXmlContent(string $name, string $bytes): void
    {
        if (preg_match('/macroEnabled|vbaProject|oleObject|<[^>]*\b(?:OLEObject|object|altChunk)\b/i', $bytes)) {
            throw new DocxPackageInspectionException('Active or embedded object XML is not permitted.');
        }
        if (preg_match('/<!\s*(?:DOCTYPE|ENTITY)\b/i', $bytes)) {
            throw new DocxPackageInspectionException(
                "Unsafe XML detected in {$name}: DOCTYPE and ENTITY declarations are not allowed."
            );
        }

        if (str_starts_with($bytes, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1")) {
            throw new DocxPackageInspectionException(
                "OLE compound document detected in {$name}."
            );
        }
    }

    /**
     * @return list<array{id: string, type: string, target: string, target_mode: ?string}>
     */
    private function parseAndValidateRelationships(string $name, string $xmlBytes): array
    {
        $dom = $this->parseXml($name, $xmlBytes);

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('rel', 'http://schemas.openxmlformats.org/package/2006/relationships');

        $nodes = $xpath->query('//rel:Relationship');
        if ($nodes === false) {
            return [];
        }

        $results = [];
        $ids = [];
        foreach ($nodes as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $id = $node->getAttribute('Id');
            $type = $node->getAttribute('Type');
            $target = $node->getAttribute('Target');
            $targetMode = $node->hasAttribute('TargetMode') ? $node->getAttribute('TargetMode') : null;
            if ($id === '' || isset($ids[$id]) || $target === '' || ! in_array($targetMode, [null, 'Internal', 'External'], true)) {
                throw new DocxPackageInspectionException('Malformed or duplicate relationship identity.');
            }
            $ids[$id] = true;
            if (preg_match('~/relationships/(?:oleObject|package|attachedTemplate|control|vbaProject|aFChunk)$~i', $type)) {
                throw new DocxPackageInspectionException('Active relationship is not permitted.');
            }

            // Reject unsafe schemes in target
            if (preg_match('/^(file|php|gopher|data|javascript|vbscript|ftp|jar|phar):\/\//i', $target)
                || preg_match('/^(file|php|gopher|data|javascript|vbscript|ftp|jar|phar):/i', $target)) {
                throw new DocxPackageInspectionException(
                    "Unsafe relationship URL scheme detected: '{$target}' in {$name}."
                );
            }

            if ($targetMode === 'External') {
                $isHyperlink = $type === 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink';
                if (! $isHyperlink) {
                    throw new DocxPackageInspectionException(
                        "External relationship of type '{$type}' in {$name} is not permitted. Only external hyperlinks are allowed."
                    );
                }

                if (! NoticeEmailLinkPolicy::allows($target)) {
                    throw new DocxPackageInspectionException(
                        'Unsafe external hyperlink origin or URL in package relationship.'
                    );
                }
            }
            if ($targetMode !== 'External') {
                if (str_starts_with($target, '/') || str_contains($target, '\\') || preg_match('/[:%?#\x00-\x20]/', $target)) {
                    throw new DocxPackageInspectionException('Unsafe internal relationship target.');
                }
                $base = $name === '_rels/.rels' ? '' : dirname(dirname($name)).'/';
                $segments = [];
                foreach (explode('/', $base.$target) as $segment) {
                    if ($segment === '..') {
                        if ($segments === []) {
                            throw new DocxPackageInspectionException('Relationship escapes package root.');
                        }
                        array_pop($segments);
                    } elseif ($segment !== '.' && $segment !== '') {
                        $segments[] = $segment;
                    }
                }
                $target = implode('/', $segments);
            }

            $results[] = [
                'id' => $id,
                'type' => $type,
                'target' => $target,
                'target_mode' => $targetMode,
            ];
        }

        return $results;
    }

    /**
     * @return array{member_name: string, sha256: string, width: int, height: int, mime: string, size_bytes: int, bytes: string}
     */
    private function inspectImage(string $name, string $bytes): array
    {
        $this->assertWithinDeadline();
        if (strlen($bytes) > self::MAX_IMAGE_BYTES) {
            throw new DocxPackageInspectionException('Image exceeds the bounded byte size.');
        }
        $isPng = str_starts_with($bytes, "\x89PNG\r\n\x1a\n");
        $isJpeg = str_starts_with($bytes, "\xff\xd8\xff");

        if (! $isPng && ! $isJpeg) {
            throw new DocxPackageInspectionException(
                "Image member {$name} has invalid magic bytes or unsupported format."
            );
        }

        $size = @getimagesizefromstring($bytes);
        if ($size === false || ! isset($size[0], $size[1])) {
            throw new DocxPackageInspectionException("Unable to decode image dimensions for {$name}.");
        }

        $width = (int) $size[0];
        $height = (int) $size[1];
        $mime = (string) ($size['mime'] ?? ($isPng ? 'image/png' : 'image/jpeg'));

        if (! in_array($mime, ['image/png', 'image/jpeg'], true)) {
            throw new DocxPackageInspectionException("Image member {$name} has disallowed MIME type: {$mime}.");
        }

        $pixels = $width * $height;
        if ($width <= 0 || $height <= 0 || $pixels > self::MAX_IMAGE_PIXELS) {
            throw new DocxPackageInspectionException(
                "Image member {$name} exceeds maximum resolution of 20 MP ({$pixels} pixels)."
            );
        }

        return [
            'member_name' => $name,
            'sha256' => hash('sha256', $bytes),
            'width' => $width,
            'height' => $height,
            'mime' => $mime,
            'size_bytes' => strlen($bytes),
            'bytes' => $bytes,
        ];
    }

    private function readMember(ZipArchive $zip, string $name, int $expectedSize): string
    {
        $this->assertWithinDeadline();
        $bytes = $zip->getFromName($name, $expectedSize + 1);
        if (! is_string($bytes) || strlen($bytes) !== $expectedSize) {
            throw new DocxPackageInspectionException('Archive member size or integrity mismatch.');
        }

        return $bytes;
    }

    public function parseXml(string $name, string $bytes): DOMDocument
    {
        $this->assertSafeXmlContent($name, $bytes);
        if ($bytes === '') {
            throw new DocxPackageInspectionException("Malformed XML package member: {$name} is empty.");
        }
        if (strlen($bytes) > self::MAX_XML_BYTES || str_contains($bytes, "\0")) {
            throw new DocxPackageInspectionException('XML encoding or size is outside supported bounds.');
        }
        $reader = new \XMLReader;
        if (! $reader->XML($bytes, null, LIBXML_NONET)) {
            throw new DocxPackageInspectionException('Malformed XML package member.');
        }
        $reader->setParserProperty(\XMLReader::LOADDTD, false);
        $reader->setParserProperty(\XMLReader::SUBST_ENTITIES, false);
        $reader->setParserProperty(\XMLReader::VALIDATE, false);
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();
        try {
            $nodes = 0;
            while ($reader->read()) {
                $this->assertWithinDeadline();
                if (++$nodes > self::MAX_XML_NODES || $reader->depth > self::MAX_XML_DEPTH) {
                    throw new DocxPackageInspectionException('XML node or depth limit exceeded.');
                }
                if (in_array($reader->nodeType, [\XMLReader::DOC_TYPE, \XMLReader::ENTITY, \XMLReader::ENTITY_REF], true)) {
                    throw new DocxPackageInspectionException('XML entity declarations are not permitted.');
                }
            }
            if (libxml_get_errors() !== []) {
                throw new DocxPackageInspectionException('Malformed XML package member.');
            }
            $dom = new DOMDocument;
            $dom->resolveExternals = false;
            $dom->substituteEntities = false;
            if (! $dom->loadXML($bytes, LIBXML_NONET)) {
                throw new DocxPackageInspectionException('Malformed XML package member.');
            }
            $xpath = new DOMXPath($dom);
            foreach ($xpath->query('//*[@*[local-name()="src" or local-name()="href"]]') as $resource) {
                throw new DocxPackageInspectionException('Direct linked resources are not permitted in package XML.');
            }
            $this->assertSafeFields($xpath);

            return $dom;
        } finally {
            $reader->close();
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function assertSafeFields(DOMXPath $xpath): void
    {
        $stack = [];
        $word = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
        foreach ($xpath->query('//*[local-name()="fldChar" or local-name()="instrText" or local-name()="fldSimple"]') as $node) {
            $this->assertWithinDeadline();
            if ($node->localName === 'fldSimple') {
                $this->assertSafeFieldInstruction($node->getAttributeNS($word, 'instr'));

                continue;
            }
            if ($node->localName === 'instrText') {
                if ($stack === []) {
                    throw new DocxPackageInspectionException('Unbound Word field instruction is not permitted.');
                }
                foreach ($stack as &$field) {
                    if (! $field['separated']) {
                        $field['instruction'] .= $node->textContent;
                        if (strlen($field['instruction']) > 16384) {
                            throw new DocxPackageInspectionException('Word field instruction exceeds its bounded size.');
                        }
                    }
                }
                unset($field);

                continue;
            }
            $type = $node->getAttributeNS($word, 'fldCharType');
            if ($type === 'begin') {
                if (count($stack) >= 16) {
                    throw new DocxPackageInspectionException('Word field nesting exceeds its bounded depth.');
                }
                $stack[] = ['instruction' => '', 'separated' => false];
            } elseif ($type === 'separate' && $stack !== []) {
                $index = array_key_last($stack);
                if ($stack[$index]['separated']) {
                    throw new DocxPackageInspectionException('Malformed Word field separator.');
                }
                $this->assertSafeFieldInstruction($stack[$index]['instruction']);
                $stack[$index]['separated'] = true;
            } elseif ($type === 'end' && $stack !== []) {
                $field = array_pop($stack);
                $this->assertSafeFieldInstruction($field['instruction']);
            } else {
                throw new DocxPackageInspectionException('Malformed Word field boundary.');
            }
        }
        if ($stack !== []) {
            throw new DocxPackageInspectionException('Unclosed Word field is not permitted.');
        }
    }

    private function assertSafeFieldInstruction(string $instruction): void
    {
        if (strlen($instruction) > 16384) {
            throw new DocxPackageInspectionException('Word field instruction exceeds its bounded size.');
        }
        $instruction = trim($instruction);
        if (! preg_match('/^([A-Z]+|=)(?=\s|\\\\|$)/i', $instruction, $match)) {
            throw new DocxPackageInspectionException('Unknown Word field instruction cannot be proven safe.');
        }
        $command = strtoupper($match[1]);
        if ($command === 'HYPERLINK') {
            if (! preg_match('/^HYPERLINK\s+"([^"]+)"\s*$/i', $instruction, $link)
                || ! NoticeEmailLinkPolicy::allows($link[1])) {
                throw new DocxPackageInspectionException('Unsafe Word hyperlink field is not permitted.');
            }

            return;
        }
        if (preg_match('/\b(?:DDEAUTO|DDE|INCLUDETEXT|INCLUDEPICTURE|LINK|MACROBUTTON)\b/i', $instruction)) {
            throw new DocxPackageInspectionException('Active or external-fetch field instructions are not permitted.');
        }
        if (! in_array($command, ['PAGE', 'NUMPAGES', 'SECTION', 'SECTIONPAGES', 'DATE', 'TIME', 'CREATEDATE', 'SAVEDATE',
            'PRINTDATE', 'AUTHOR', 'TITLE', 'SUBJECT', 'KEYWORDS', 'COMMENTS', 'FILENAME', 'MERGEFIELD', 'DOCPROPERTY',
            'REF', 'PAGEREF', 'SEQ', 'IF', '=', 'SYMBOL'], true)) {
            throw new DocxPackageInspectionException('Unknown Word field instruction cannot be proven safe.');
        }
    }

    public function assertWithinDeadline(): void
    {
        if ($this->deadline !== 0 && hrtime(true) > $this->deadline) {
            throw new DocxPackageInspectionException('DOCX processing time limit exceeded.');
        }
    }
}
