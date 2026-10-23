<?php

namespace App\DocumentTemplate\Packaging;

use JsonException;
use RuntimeException;

final class DirectoryBundleReader
{
    /**
     * @return array{manifest: array<string, mixed>, files: array<string, string>}
     */
    public function read(string $directoryPath): array
    {
        $rootPath = realpath($directoryPath);
        if ($rootPath === false || ! is_dir($rootPath)) {
            throw new RuntimeException('Document bundle directory does not exist.');
        }

        $manifestPath = $rootPath.DIRECTORY_SEPARATOR.'manifest.json';
        $resolvedManifestPath = realpath($manifestPath);
        if ($resolvedManifestPath === false || ! is_file($resolvedManifestPath) || ! str_starts_with($resolvedManifestPath, $rootPath.DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Document bundle manifest is missing or outside its directory.');
        }

        $manifestBytes = file_get_contents($resolvedManifestPath);
        if (! is_string($manifestBytes)) {
            throw new RuntimeException('Document bundle manifest cannot be read.');
        }

        try {
            $manifest = json_decode($manifestBytes, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Document bundle manifest is invalid JSON.', previous: $exception);
        }

        if (! is_array($manifest) || ($manifest['schema_version'] ?? null) !== DocumentBundlePackage::SCHEMA_VERSION) {
            throw new RuntimeException('Document bundle schema version is unsupported.');
        }

        if (! isset($manifest['files']) || ! is_array($manifest['files'])) {
            throw new RuntimeException('Document bundle manifest files entry is invalid.');
        }

        $files = [];
        foreach ($manifest['files'] as $relativePath => $entry) {
            if (! is_string($relativePath) || ! is_array($entry) || $relativePath === '' || str_contains($relativePath, "\0") || str_contains($relativePath, '..') || str_starts_with($relativePath, '/') || str_starts_with($relativePath, '\\') || preg_match('/^[a-zA-Z]:/', $relativePath)) {
                throw new RuntimeException('Document bundle manifest contains an unsafe file path.');
            }

            $normalizedPath = str_replace('\\', '/', $relativePath);
            $segments = array_values(array_filter(explode('/', $normalizedPath), static fn (string $segment): bool => $segment !== '' && $segment !== '.'));
            if ($segments === [] || implode('/', $segments) !== $normalizedPath || $normalizedPath === 'manifest.json') {
                throw new RuntimeException('Document bundle manifest contains a non-normalized file path.');
            }

            $expectedHash = $entry['sha256'] ?? null;
            $expectedSize = $entry['size'] ?? null;
            if (! is_string($expectedHash) || ! preg_match('/^[a-f0-9]{64}$/i', $expectedHash) || ! is_int($expectedSize) || $expectedSize < 0) {
                throw new RuntimeException('Document bundle manifest file metadata is invalid.');
            }

            $filePath = $rootPath.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $normalizedPath);
            $resolvedFilePath = realpath($filePath);
            if ($resolvedFilePath === false || ! is_file($resolvedFilePath) || ! str_starts_with($resolvedFilePath, $rootPath.DIRECTORY_SEPARATOR)) {
                throw new RuntimeException('A document bundle file is missing or outside its directory.');
            }

            $bytes = file_get_contents($resolvedFilePath);
            if (! is_string($bytes)) {
                throw new RuntimeException('A document bundle file cannot be read.');
            }

            if (! hash_equals(strtolower($expectedHash), hash('sha256', $bytes)) || strlen($bytes) !== $expectedSize) {
                throw new RuntimeException('A document bundle file failed integrity validation.');
            }

            $files[$normalizedPath] = $bytes;
        }

        return ['manifest' => $manifest, 'files' => $files];
    }
}
