<?php

namespace App\Assessment\Omr\Canonical;

use ZipArchive;

use function config;
use function file_get_contents;
use function filesize;
use function finfo_close;
use function finfo_file;
use function finfo_open;
use function getimagesize;
use function hash_file;
use function is_array;
use function is_file;
use function is_numeric;
use function pathinfo;
use function str_contains;
use function str_ends_with;
use function strtolower;

final class OmrSourcePreflight
{
    public function __construct(private readonly OmrHtmlSanitizer $htmlSanitizer) {}

    /**
     * @param  array{declared_mime?:string,page_count?:int,width_mm?:float,height_mm?:float}  $context
     */
    public function check(string $kind, string $sourcePath, array $context = []): OmrSourcePreflightResult
    {
        if (! in_array($kind, ['html', 'docx', 'pdf', 'image', 'preset'], true)) {
            throw new OmrSourcePreflightException('unsupported_source', "Unsupported source kind {$kind}.");
        }
        if (! is_file($sourcePath)) {
            throw new OmrSourcePreflightException('source_not_found', 'Source file was not found.');
        }

        $bytes = (int) filesize($sourcePath);
        $maxUpload = (int) config('omr.canonical.limits.max_upload_bytes', 25 * 1024 * 1024);
        if ($bytes <= 0 || $bytes > $maxUpload) {
            throw new OmrSourcePreflightException('upload_too_large', 'Source exceeds the configured upload limit.', ['bytes' => $bytes, 'max_bytes' => $maxUpload]);
        }

        $mime = $this->detectedMime($sourcePath);
        $this->assertExtensionAndMime($kind, $sourcePath, $mime, $context['declared_mime'] ?? null);
        $metadata = match ($kind) {
            'html' => $this->htmlMetadata($sourcePath),
            'docx' => $this->docxMetadata($sourcePath),
            'pdf' => $this->pdfMetadata($sourcePath),
            'image' => $this->imageMetadata($sourcePath),
            'preset' => ['source_kind' => 'preset'],
        };

        $pageCount = (int) ($metadata['page_count'] ?? $context['page_count'] ?? 1);
        $maxPages = (int) config('omr.canonical.limits.max_pages_per_source', 20);
        if ($pageCount < 1 || $pageCount > $maxPages) {
            throw new OmrSourcePreflightException('page_limit_exceeded', 'Source page count exceeds the configured limit.', ['page_count' => $pageCount, 'max_pages' => $maxPages]);
        }

        $widthMm = $context['width_mm'] ?? ($metadata['width_mm'] ?? null);
        $heightMm = $context['height_mm'] ?? ($metadata['height_mm'] ?? null);
        if (is_numeric($widthMm) && is_numeric($heightMm)) {
            OmrPageDimensionMath::assertPaperBounds((float) $widthMm, (float) $heightMm);
        }

        if (isset($metadata['width_px'], $metadata['height_px'])) {
            OmrPageDimensionMath::assertPixelBudget(
                (int) $metadata['width_px'],
                (int) $metadata['height_px'],
            );
        }

        return new OmrSourcePreflightResult(
            kind: $kind,
            path: $sourcePath,
            mime: $mime,
            bytes: $bytes,
            sha256: (string) hash_file('sha256', $sourcePath),
            metadata: [
                ...$metadata,
                'page_count' => $pageCount,
                'width_mm' => is_numeric($widthMm) ? (float) $widthMm : null,
                'height_mm' => is_numeric($heightMm) ? (float) $heightMm : null,
            ],
        );
    }

    private function detectedMime(string $path): string
    {
        $handle = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $handle === false ? false : finfo_file($handle, $path);
        if ($handle !== false) {
            finfo_close($handle);
        }
        if (! is_string($mime) || $mime === '') {
            throw new OmrSourcePreflightException('mime_detection_failed', 'Unable to determine source MIME type.');
        }

        return strtolower($mime);
    }

    private function assertExtensionAndMime(string $kind, string $path, string $detectedMime, ?string $declaredMime): void
    {
        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        $extensions = [
            'html' => ['html', 'htm'],
            'docx' => ['docx'],
            'pdf' => ['pdf'],
            'image' => ['jpg', 'jpeg', 'png', 'webp', 'tif', 'tiff'],
            'preset' => ['json'],
        ];
        if (! in_array($extension, $extensions[$kind], true)) {
            throw new OmrSourcePreflightException('extension_mismatch', 'Source extension does not match the selected source adapter.');
        }
        if ($declaredMime !== null && ! $this->mimeMatches($kind, strtolower($declaredMime))) {
            throw new OmrSourcePreflightException('mime_mismatch', 'Declared MIME type does not match the selected source adapter.');
        }
        if (! $this->mimeMatches($kind, $detectedMime)) {
            throw new OmrSourcePreflightException('magic_mismatch', 'Detected file type does not match the selected source adapter.', ['mime' => $detectedMime]);
        }
    }

    private function mimeMatches(string $kind, string $mime): bool
    {
        return match ($kind) {
            'html' => in_array($mime, ['text/html', 'text/plain', 'application/xhtml+xml'], true),
            'docx' => in_array($mime, ['application/zip', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'], true),
            'pdf' => $mime === 'application/pdf',
            'image' => str_starts_with($mime, 'image/'),
            'preset' => in_array($mime, ['application/json', 'text/plain'], true),
        };
    }

    /** @return array<string, mixed> */
    private function htmlMetadata(string $path): array
    {
        $html = (string) file_get_contents($path);
        $sanitized = $this->htmlSanitizer->sanitize($html);

        return [
            'sanitized_html' => $sanitized['html'],
            'overlay_slots' => $sanitized['slots'],
            'page_count' => 1,
        ];
    }

    /** @return array<string, mixed> */
    private function docxMetadata(string $path): array
    {
        if ((string) file_get_contents($path, false, null, 0, 4) !== "PK\x03\x04") {
            throw new OmrSourcePreflightException('magic_mismatch', 'DOCX must be a ZIP container.');
        }
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new OmrSourcePreflightException('corrupt_docx', 'DOCX archive could not be opened.');
        }
        $memberCap = 1000;
        $memberCount = $zip->numFiles;
        $uncompressedCap = (int) config('omr.canonical.limits.max_upload_bytes', 25 * 1024 * 1024) * 20;
        $uncompressed = 0;
        for ($index = 0; $index < $memberCount; $index++) {
            $stat = $zip->statIndex($index);
            $uncompressed += (int) ($stat['size'] ?? 0);
            if ($memberCount > $memberCap || $uncompressed > $uncompressedCap) {
                $zip->close();
                throw new OmrSourcePreflightException('zip_bomb', 'DOCX archive exceeds decompression limits.');
            }
            if (str_ends_with(strtolower((string) ($stat['name'] ?? '')), 'vbaproject.bin')) {
                $zip->close();
                throw new OmrSourcePreflightException('macro_rejected', 'DOCX VBA/macros are not accepted.');
            }
        }
        $hasTypes = $zip->locateName('[Content_Types].xml', ZipArchive::FL_NOCASE) !== false;
        $hasDocument = $zip->locateName('word/document.xml', ZipArchive::FL_NOCASE) !== false;
        $zip->close();
        if (! $hasTypes || ! $hasDocument) {
            throw new OmrSourcePreflightException('invalid_docx', 'DOCX is missing required package members.');
        }

        return ['page_count' => 1, 'zip_members' => $memberCount, 'uncompressed_bytes' => $uncompressed];
    }

    /** @return array<string, mixed> */
    private function pdfMetadata(string $path): array
    {
        $bytes = (string) file_get_contents($path);
        if (! str_starts_with($bytes, '%PDF-')) {
            throw new OmrSourcePreflightException('magic_mismatch', 'PDF source does not begin with a PDF header.');
        }
        if (str_contains($bytes, '/Encrypt')) {
            throw new OmrSourcePreflightException('encrypted_pdf', 'Password-protected or encrypted PDFs are not accepted.');
        }
        if (! str_contains($bytes, 'startxref') || ! str_contains($bytes, '%%EOF')) {
            throw new OmrSourcePreflightException('invalid_pdf', 'PDF xref/trailer markers are missing.');
        }
        preg_match_all('/\/Type\s*\/Page\b/', $bytes, $matches);
        preg_match_all('/\/MediaBox\s*\[\s*([0-9.]+)\s+([0-9.]+)\s+([0-9.]+)\s+([0-9.]+)\s*\]/', $bytes, $mediaBoxes, PREG_SET_ORDER);
        $pageSizes = [];
        foreach ($mediaBoxes as $box) {
            $pageSizes[] = [
                'width_mm' => round(((float) $box[3] - (float) $box[1]) * 25.4 / 72, 3),
                'height_mm' => round(((float) $box[4] - (float) $box[2]) * 25.4 / 72, 3),
            ];
        }

        return ['page_count' => max(1, count($matches[0])), 'page_sizes_mm' => $pageSizes];
    }

    /** @return array<string, mixed> */
    private function imageMetadata(string $path): array
    {
        $image = @getimagesize($path);
        if (! is_array($image) || (int) ($image[0] ?? 0) < 1 || (int) ($image[1] ?? 0) < 1) {
            throw new OmrSourcePreflightException('invalid_image', 'Image dimensions could not be read.');
        }
        $bytes = (string) file_get_contents($path);
        if (str_contains($bytes, 'ICC_PROFILE') && ! str_contains($bytes, 'sRGB')) {
            throw new OmrSourcePreflightException('image_icc_unsupported', 'Non-sRGB ICC profiles are rejected until a deterministic conversion path is pinned.');
        }
        $dpi = null;
        if (function_exists('exif_read_data')) {
            $exif = @exif_read_data($path);
            $resolution = $exif['XResolution'] ?? null;
            $unit = (int) ($exif['ResolutionUnit'] ?? 2);
            if (is_string($resolution) && str_contains($resolution, '/')) {
                [$numerator, $denominator] = array_pad(explode('/', $resolution, 2), 2, '0');
                $resolution = is_numeric($denominator) && (float) $denominator !== 0
                    ? (float) $numerator / (float) $denominator
                    : null;
            }
            if (is_numeric($resolution) && (float) $resolution > 0) {
                $dpi = $unit === 3 ? (float) $resolution * 2.54 : (float) $resolution;
            }
        }

        return [
            'page_count' => 1,
            'width_px' => (int) $image[0],
            'height_px' => (int) $image[1],
            'bits' => (int) ($image['bits'] ?? 8),
            'channels' => (int) ($image['channels'] ?? 0),
            'has_alpha' => str_ends_with(strtolower((string) ($image['mime'] ?? '')), 'png'),
            'source_dpi' => $dpi,
        ];
    }
}
