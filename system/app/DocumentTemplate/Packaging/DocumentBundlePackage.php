<?php

namespace App\DocumentTemplate\Packaging;

use JsonException;
use RuntimeException;
use ZipArchive;

final class DocumentBundlePackage
{
    public const SCHEMA_VERSION = 'nexiam.document-bundle.v1';

    public const MAX_UNCOMPRESSED_BYTES = 52428800;

    public const MAX_MEMBER_COUNT = 50;

    public const NESTED_MAX_UNCOMPRESSED_BYTES = 104857600;

    public const NESTED_MAX_MEMBER_COUNT = 200;

    public const ALLOWED_EXTENSIONS = ['json', 'docx', 'odt', 'html', 'png', 'jpg', 'jpeg', 'svg'];

    /**
     * @param  array<string, string>  $files
     * @param  array<string, mixed>  $manifestData
     */
    public function createArchive(array $files, array $manifestData): string
    {
        if (count($files) + 1 > self::MAX_MEMBER_COUNT) {
            throw new RuntimeException('Document bundle contains too many files.');
        }

        $normalizedFiles = [];
        $manifestEntries = [];

        foreach ($files as $path => $bytes) {
            if (! is_string($path) || ! is_string($bytes)) {
                throw new RuntimeException('Document bundle file paths and contents must be strings.');
            }

            $relativePath = $this->normalizePath($path);
            $this->assertAllowedExtension($relativePath);

            if (isset($normalizedFiles[$relativePath])) {
                throw new RuntimeException('Document bundle contains duplicate normalized paths.');
            }

            $normalizedFiles[$relativePath] = $bytes;
            $manifestEntries[$relativePath] = [
                'sha256' => hash('sha256', $bytes),
                'size' => strlen($bytes),
            ];
        }

        $manifest = $manifestData;
        $manifest['schema_version'] = self::SCHEMA_VERSION;
        $manifest['exported_at'] = now()->toIso8601String();
        $manifest['files'] = $manifestEntries;

        try {
            $manifestBytes = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Document bundle manifest could not be encoded.', previous: $exception);
        }

        $totalBytes = strlen($manifestBytes);
        foreach ($normalizedFiles as $bytes) {
            $totalBytes += strlen($bytes);
        }

        if ($totalBytes > self::MAX_UNCOMPRESSED_BYTES) {
            throw new RuntimeException('Document bundle exceeds the uncompressed size limit.');
        }

        $archivePath = tempnam(sys_get_temp_dir(), 'nexiam-document-bundle-');
        if ($archivePath === false) {
            throw new RuntimeException('Unable to create a temporary document bundle archive.');
        }

        $archive = new ZipArchive;
        $opened = $archive->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if ($opened !== true) {
            @unlink($archivePath);
            throw new RuntimeException('Unable to open a temporary document bundle archive.');
        }

        try {
            if (! $archive->addFromString('manifest.json', $manifestBytes)) {
                throw new RuntimeException('Unable to add the document bundle manifest.');
            }

            foreach ($normalizedFiles as $path => $bytes) {
                if (! $archive->addFromString($path, $bytes)) {
                    throw new RuntimeException('Unable to add a document bundle file.');
                }
            }

            if (! $archive->close()) {
                throw new RuntimeException('Unable to finalize the document bundle archive.');
            }
        } catch (\Throwable $exception) {
            $archive->close();
            @unlink($archivePath);
            throw $exception;
        }

        return $archivePath;
    }

    /**
     * @return array{manifest: array<string, mixed>, files: array<string, string>}
     */
    public function extractAndValidate(string $zipPath): array
    {
        $archive = new ZipArchive;
        if ($archive->open($zipPath) !== true) {
            throw new RuntimeException('Unable to open document bundle archive.');
        }

        try {
            if ($archive->numFiles > self::MAX_MEMBER_COUNT) {
                throw new RuntimeException('Document bundle contains too many archive members.');
            }

            $totalUncompressedBytes = 0;
            $members = [];

            for ($index = 0; $index < $archive->numFiles; $index++) {
                $stat = $archive->statIndex($index);
                if ($stat === false || ! isset($stat['name'], $stat['size']) || ! is_string($stat['name'])) {
                    throw new RuntimeException('Document bundle contains an invalid archive member.');
                }

                $name = $stat['name'];
                $isDirectory = str_ends_with($name, '/') || str_ends_with($name, '\\');
                $pathName = $isDirectory ? rtrim($name, '/\\') : $name;
                $normalizedName = $this->normalizePath($pathName);
                if (! $isDirectory && $normalizedName !== $name) {
                    throw new RuntimeException('Document bundle contains a non-normalized archive member path.');
                }

                if (isset($members[$normalizedName])) {
                    throw new RuntimeException('Document bundle contains duplicate archive member paths.');
                }

                if (! $isDirectory) {
                    $members[$normalizedName] = $index;
                }
                $totalUncompressedBytes += (int) $stat['size'];
                if ($totalUncompressedBytes > self::MAX_UNCOMPRESSED_BYTES) {
                    throw new RuntimeException('Document bundle exceeds the uncompressed size limit.');
                }
            }

            if (! isset($members['manifest.json'])) {
                throw new RuntimeException('Document bundle manifest is missing.');
            }

            $manifestBytes = $archive->getFromIndex($members['manifest.json']);
            if (! is_string($manifestBytes)) {
                throw new RuntimeException('Document bundle manifest cannot be read.');
            }

            try {
                $manifest = json_decode($manifestBytes, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new RuntimeException('Document bundle manifest is invalid JSON.', previous: $exception);
            }

            if (! is_array($manifest) || ($manifest['schema_version'] ?? null) !== self::SCHEMA_VERSION) {
                throw new RuntimeException('Document bundle schema version is unsupported.');
            }

            if (! isset($manifest['files']) || ! is_array($manifest['files'])) {
                throw new RuntimeException('Document bundle manifest files entry is invalid.');
            }

            $files = [];
            foreach ($manifest['files'] as $path => $entry) {
                if (! is_string($path) || ! is_array($entry)) {
                    throw new RuntimeException('Document bundle manifest contains an invalid file entry.');
                }

                $relativePath = $this->normalizePath($path);
                $this->assertAllowedExtension($relativePath);
                if ($relativePath !== $path || ! isset($members[$relativePath]) || $relativePath === 'manifest.json') {
                    throw new RuntimeException('A manifest file is missing or has an invalid path.');
                }

                $expectedHash = $entry['sha256'] ?? null;
                $expectedSize = $entry['size'] ?? null;
                if (! is_string($expectedHash) || ! preg_match('/^[a-f0-9]{64}$/i', $expectedHash) || ! is_int($expectedSize) || $expectedSize < 0) {
                    throw new RuntimeException('Document bundle manifest file metadata is invalid.');
                }

                $bytes = $archive->getFromIndex($members[$relativePath]);
                if (! is_string($bytes)) {
                    throw new RuntimeException('A document bundle file cannot be read.');
                }

                if (! hash_equals(strtolower($expectedHash), hash('sha256', $bytes)) || strlen($bytes) !== $expectedSize) {
                    throw new RuntimeException('A document bundle file failed integrity validation.');
                }
                if (strtolower(pathinfo($relativePath, PATHINFO_EXTENSION)) === 'svg' && preg_match('/<!\s*(?:DOCTYPE|ENTITY)\b/i', $bytes)) {
                    throw new RuntimeException('Document bundle contains unsafe SVG XML.');
                }

                $this->inspectNestedOfficeArchive($relativePath, $bytes);
                $files[$relativePath] = $bytes;
            }

            if (count($members) !== count($files) + 1) {
                throw new RuntimeException('Document bundle contains unlisted archive members.');
            }

            return ['manifest' => $manifest, 'files' => $files];
        } finally {
            $archive->close();
        }
    }

    private function normalizePath(string $path): string
    {
        if ($path === '' || str_contains($path, "\0") || str_contains($path, '..') || str_starts_with($path, '/') || str_starts_with($path, '\\') || preg_match('/^[a-zA-Z]:/', $path)) {
            throw new RuntimeException('Document bundle contains an unsafe relative path.');
        }

        $path = str_replace('\\', '/', $path);
        $segments = array_values(array_filter(explode('/', $path), static fn (string $segment): bool => $segment !== '' && $segment !== '.'));
        if ($segments === []) {
            throw new RuntimeException('Document bundle contains an empty relative path.');
        }

        return implode('/', $segments);
    }

    private function assertAllowedExtension(string $path): void
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new RuntimeException('Document bundle contains a disallowed file extension.');
        }
    }

    private function inspectNestedOfficeArchive(string $path, string $bytes): void
    {
        if (! in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['docx', 'odt'], true)) {
            return;
        }

        $nestedPath = tempnam(sys_get_temp_dir(), 'nexiam-office-bundle-');
        if ($nestedPath === false) {
            throw new RuntimeException('Unable to inspect nested office document.');
        }

        try {
            if (file_put_contents($nestedPath, $bytes) !== strlen($bytes)) {
                throw new RuntimeException('Unable to prepare nested office document for inspection.');
            }

            $nestedArchive = new ZipArchive;
            if ($nestedArchive->open($nestedPath) !== true) {
                throw new RuntimeException('Office document is not a valid ZIP archive.');
            }

            try {
                if ($nestedArchive->numFiles > self::NESTED_MAX_MEMBER_COUNT) {
                    throw new RuntimeException('Office document contains too many archive members.');
                }

                $expandedBytes = 0;
                for ($index = 0; $index < $nestedArchive->numFiles; $index++) {
                    $stat = $nestedArchive->statIndex($index);
                    if ($stat === false || ! isset($stat['name'], $stat['size']) || ! is_string($stat['name'])) {
                        throw new RuntimeException('Office document contains an invalid archive member.');
                    }

                    $memberName = $stat['name'];
                    $memberBasename = strtolower(basename(str_replace('\\', '/', $memberName)));
                    $memberExtension = strtolower(pathinfo($memberName, PATHINFO_EXTENSION));
                    if ($memberBasename === 'vbaproject.bin' || in_array($memberExtension, ['exe', 'dll'], true) || str_contains(strtolower($memberName), 'oleobject') || str_starts_with($memberBasename, 'oleobject')) {
                        throw new RuntimeException('Office document contains a macro, executable, or OLE object.');
                    }

                    $expandedBytes += (int) $stat['size'];
                    if ($expandedBytes > self::NESTED_MAX_UNCOMPRESSED_BYTES) {
                        throw new RuntimeException('Office document exceeds the nested uncompressed size limit.');
                    }

                    $memberBytes = $nestedArchive->getFromIndex($index);
                    if (! is_string($memberBytes)) {
                        throw new RuntimeException('Office document contains an unreadable archive member.');
                    }

                    if (strtolower(pathinfo($memberName, PATHINFO_EXTENSION)) === 'xml' && preg_match('/<!\s*(?:DOCTYPE|ENTITY)\b/i', $memberBytes)) {
                        throw new RuntimeException('Office document contains unsafe XML.');
                    }

                    if (str_starts_with($memberBytes, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1")) {
                        throw new RuntimeException('Office document contains an OLE object.');
                    }
                }
            } finally {
                $nestedArchive->close();
            }
        } finally {
            @unlink($nestedPath);
        }
    }
}
