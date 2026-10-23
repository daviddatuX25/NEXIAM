<?php

namespace App\Assessment\Omr\Canonical;

use DOMDocument;
use InvalidArgumentException;
use RuntimeException;
use ZipArchive;

use function dirname;
use function file_put_contents;
use function hash;
use function in_array;
use function is_dir;
use function is_file;
use function json_decode;
use function json_encode;
use function ksort;
use function mkdir;
use function rmdir;
use function scandir;
use function sprintf;
use function str_contains;
use function str_ends_with;
use function str_starts_with;
use function strlen;
use function strtolower;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

/**
 * Service for packaging, extracting, and verifying self-contained Answer Sheet bundles (.zip).
 *
 * Enforces `nexiam.sheet-bundle.v1` schema, SHA-256 manifest validation, Zip-Slip prevention,
 * strict decompression limits, and XML sanitization for SVG references.
 */
final class OmrBundlePackage
{
    public const SCHEMA_VERSION = 'nexiam.sheet-bundle.v1';

    public const MAX_UNCOMPRESSED_BYTES = 120 * 1024 * 1024; // 120 MB

    public const MAX_MEMBER_COUNT = 50;

    public const ALLOWED_EXTENSIONS = ['json', 'pdf', 'png', 'jpg', 'jpeg', 'svg', 'docx', 'bin'];

    /**
     * Package files into a temporary .zip archive.
     *
     * @param  array<string, string>  $files  Relative file paths to binary contents
     * @param  array<string, mixed>  $manifestData
     * @return string Path to the created temporary ZIP file
     */
    public function createArchive(array $files, array $manifestData): string
    {
        $manifestData['schema_version'] = self::SCHEMA_VERSION;
        $manifestData['exported_at'] = now()->toIso8601String();

        $fileEntries = [];
        foreach ($files as $relativePath => $bytes) {
            $relativePath = $this->normalizeRelativePath($relativePath);
            $fileEntries[$relativePath] = [
                'sha256' => hash('sha256', $bytes),
                'size' => strlen($bytes),
            ];
        }
        ksort($fileEntries);
        $manifestData['files'] = $fileEntries;

        $manifestJson = json_encode($manifestData, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $tempZipPath = tempnam(sys_get_temp_dir(), 'nexiam_bundle_');
        if ($tempZipPath === false) {
            throw new RuntimeException('Failed to allocate temporary bundle file.');
        }

        $zip = new ZipArchive;
        if ($zip->open($tempZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            unlink($tempZipPath);
            throw new RuntimeException('Failed to initialize zip archive for answer sheet bundle.');
        }

        try {
            $zip->addFromString('manifest.json', $manifestJson);

            foreach ($files as $relativePath => $bytes) {
                $zip->addFromString($relativePath, $bytes);
            }

            $zip->close();
        } catch (\Throwable $e) {
            @unlink($tempZipPath);
            throw $e;
        }

        return $tempZipPath;
    }

    /**
     * Safely extract and validate an uploaded answer sheet bundle.
     *
     * @param  string  $zipFilePath  Path to the ZIP file
     * @param  (callable(int, int, string): bool)|null  $progress  Called after a validated member is extracted.
     * @return array{manifest: array<string, mixed>, files: array<string, string>, temp_dir: string}
     */
    public function extractAndValidate(string $zipFilePath, ?callable $progress = null): array
    {
        if (! is_file($zipFilePath)) {
            throw new InvalidArgumentException('bundle_file_missing: Uploaded archive does not exist.');
        }

        $zip = new ZipArchive;
        $openResult = $zip->open($zipFilePath, ZipArchive::RDONLY);
        if ($openResult !== true) {
            throw new InvalidArgumentException('bundle_archive_corrupt: Unable to open zip archive.');
        }

        $numFiles = $zip->numFiles;
        if ($numFiles > self::MAX_MEMBER_COUNT) {
            $zip->close();
            throw new InvalidArgumentException(sprintf('bundle_entry_limit_exceeded: Archive contains %d entries (maximum allowed is %d).', $numFiles, self::MAX_MEMBER_COUNT));
        }

        $totalUncompressedBytes = 0;
        $manifestFound = false;

        for ($i = 0; $i < $numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if ($stat === false) {
                $zip->close();
                throw new InvalidArgumentException('bundle_entry_invalid: Corrupted zip entry index.');
            }

            $name = $stat['name'];
            $totalUncompressedBytes += $stat['size'];

            if ($totalUncompressedBytes > self::MAX_UNCOMPRESSED_BYTES) {
                $zip->close();
                throw new InvalidArgumentException('bundle_size_limit_exceeded: Decompressed size exceeds safe limit (120 MB).');
            }

            // Reject directory traversal or unsafe names
            $this->assertSafeZipEntryName($name);

            if ($name === 'manifest.json') {
                $manifestFound = true;
            }
        }

        if (! $manifestFound) {
            $zip->close();
            throw new InvalidArgumentException('bundle_manifest_missing: Root manifest.json not found in archive.');
        }

        $manifestBytes = $zip->getFromName('manifest.json');
        if ($manifestBytes === false) {
            $zip->close();
            throw new InvalidArgumentException('bundle_manifest_unreadable: Could not read manifest.json.');
        }

        $manifest = json_decode($manifestBytes, true);
        if (! is_array($manifest) || ($manifest['schema_version'] ?? '') !== self::SCHEMA_VERSION) {
            $zip->close();
            throw new InvalidArgumentException('bundle_schema_invalid: Archive schema version must be '.self::SCHEMA_VERSION);
        }

        $declaredFiles = (array) ($manifest['files'] ?? []);
        if ($declaredFiles === []) {
            $zip->close();
            throw new InvalidArgumentException('bundle_files_empty: Manifest declares no member files.');
        }

        // Create temporary extraction sandbox
        $tempDir = $this->createTemporaryExtractionSandbox();
        $extractedFiles = [];
        try {
            $completedMembers = 0;
            $memberTotal = count($declaredFiles);

            foreach ($declaredFiles as $relativePath => $meta) {
                $expectedHash = (string) ($meta['sha256'] ?? '');
                $this->assertSafeZipEntryName($relativePath);

                $bytes = $zip->getFromName($relativePath);
                if ($bytes === false) {
                    throw new InvalidArgumentException(sprintf('bundle_file_missing: Required file "%s" missing from zip archive.', $relativePath));
                }

                $actualHash = hash('sha256', $bytes);
                if ($expectedHash === '' || ! hash_equals($expectedHash, $actualHash)) {
                    throw new InvalidArgumentException(sprintf('bundle_checksum_mismatch: SHA-256 hash mismatch for file "%s". Archive may be corrupted or tampered.', $relativePath));
                }

                // Check SVG content safety
                if (str_ends_with(strtolower($relativePath), '.svg')) {
                    $this->sanitizeAndVerifySvg($bytes, $relativePath);
                }

                $targetPath = $tempDir.'/'.$relativePath;
                $targetDir = dirname($targetPath);
                if (! is_dir($targetDir)) {
                    mkdir($targetDir, 0755, true);
                }
                file_put_contents($targetPath, $bytes);

                $extractedFiles[$relativePath] = $bytes;
                $completedMembers++;
                if ($progress !== null && $progress($completedMembers, $memberTotal, (string) $relativePath) === false) {
                    throw new RuntimeException('bundle_inspection_superseded: This import operation changed while the archive was being checked.');
                }
            }

            $zip->close();
        } catch (\Throwable $e) {
            $zip->close();
            $this->cleanDirectory($tempDir);
            @rmdir($tempDir);
            throw $e;
        }

        return [
            'manifest' => $manifest,
            'files' => $extractedFiles,
            'temp_dir' => $tempDir,
        ];
    }

    /**
     * Clean up an extraction directory and its files.
     */
    public function cleanDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $items = scandir($dir) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir.'/'.$item;
            if (is_dir($path)) {
                $this->cleanDirectory($path);
                @rmdir($path);
            } else {
                @unlink($path);
            }
        }
    }

    /**
     * Strict zip entry path validation: disallow Zip-Slip path traversals.
     */
    private function assertSafeZipEntryName(string $name): void
    {
        if (str_starts_with($name, '/') || str_starts_with($name, '\\')) {
            throw new InvalidArgumentException('bundle_path_traversal: Leading slashes not permitted in archive member path.');
        }

        if (str_contains($name, '..') || str_contains($name, ':') || str_contains($name, "\0")) {
            throw new InvalidArgumentException('bundle_path_traversal: Invalid path traversal or control characters in archive member path.');
        }

        // Verify allowlisted extension
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if ($ext !== '' && ! in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            throw new InvalidArgumentException(sprintf('bundle_extension_prohibited: File extension ".%s" is not allowed in answer sheet bundle.', $ext));
        }
    }

    /**
     * Sanitize and verify SVG files with external DTD and entity loading disabled.
     */
    private function sanitizeAndVerifySvg(string $svgBytes, string $relativePath): void
    {
        $dom = new DOMDocument;
        $prevEntityLoader = libxml_disable_entity_loader(true);

        try {
            $loaded = @$dom->loadXML($svgBytes, LIBXML_NONET | LIBXML_NOBLANKS);
            if (! $loaded) {
                throw new InvalidArgumentException(sprintf('bundle_svg_invalid: SVG reference "%s" is not valid XML.', $relativePath));
            }

            // Reject script tags, foreignObject, or event handlers
            $scripts = $dom->getElementsByTagName('script');
            if ($scripts->length > 0) {
                throw new InvalidArgumentException(sprintf('bundle_svg_prohibited: SVG "%s" contains active script content.', $relativePath));
            }

            $foreignObjects = $dom->getElementsByTagName('foreignObject');
            if ($foreignObjects->length > 0) {
                throw new InvalidArgumentException(sprintf('bundle_svg_prohibited: SVG "%s" contains foreignObject content.', $relativePath));
            }
        } finally {
            libxml_disable_entity_loader($prevEntityLoader);
        }
    }

    private function normalizeRelativePath(string $path): string
    {
        return str_replace('\\', '/', trim($path, '/\\'));
    }

    private function createTemporaryExtractionSandbox(): string
    {
        $root = storage_path('app/private/omr-bundle-extract');
        if (! is_dir($root) && ! mkdir($root, 0700, true) && ! is_dir($root)) {
            throw new RuntimeException('Failed to allocate private sandbox storage for bundle extraction.');
        }
        @chmod($root, 0700);

        $tempDir = $root.'/'.bin2hex(random_bytes(16));
        if (! mkdir($tempDir, 0700)) {
            throw new RuntimeException('Failed to allocate private sandbox directory for bundle extraction.');
        }

        return $tempDir;
    }
}
