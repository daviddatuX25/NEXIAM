<?php

namespace App\DocumentTemplate\Services;

use App\Support\Media\PdfThumbnailRasterizer;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;

use function bin2hex;
use function getimagesizefromstring;
use function hash;
use function imagecreatefromstring;
use function imagedestroy;
use function is_array;
use function is_string;
use function json_encode;
use function preg_match;
use function random_bytes;
use function sprintf;
use function str_starts_with;
use function strlen;

final class DocumentThumbnailService
{
    private const VERSION = 'pdf-thumbnail-rasterizer-v1:first-page-png-240px-v1';

    private const MAX_PNG_BYTES = 1_048_576;

    private const MAX_WIDTH = 240;

    private const MAX_HEIGHT = 480;

    public function __construct(private readonly PdfThumbnailRasterizer $rasterizer) {}

    public function fingerprint(string $sourceChecksum): string
    {
        $this->validateChecksum($sourceChecksum, 'source checksum');

        return hash('sha256', json_encode([
            'renderer_transform_version' => self::VERSION,
            'source_checksum' => $sourceChecksum,
        ], JSON_THROW_ON_ERROR));
    }

    public function read(string $surface, int $templateId, string $fingerprint): ?string
    {
        $directory = $this->templateDirectory($surface, $templateId);
        $this->validateChecksum($fingerprint, 'fingerprint');
        $path = $directory.'/'.$fingerprint.'.png';

        return $this->lock($surface, $templateId)->block(5, function () use ($directory, $path, $fingerprint): ?string {
            $disk = Storage::disk('local');
            if (! $disk->exists($path)) {
                return null;
            }

            $bytes = $disk->get($path);
            if (! is_string($bytes) || ! $this->isValidPng($bytes)) {
                $disk->delete($path);

                return null;
            }

            $this->removeStaleFingerprints($directory, $fingerprint);

            return $bytes;
        });
    }

    public function store(string $surface, int $templateId, string $sourceChecksum, string $pdfBytes): string
    {
        $directory = $this->templateDirectory($surface, $templateId);
        $fingerprint = $this->fingerprint($sourceChecksum);
        $path = $directory.'/'.$fingerprint.'.png';

        return $this->lock($surface, $templateId)->block(15, function () use ($directory, $fingerprint, $path, $pdfBytes): string {
            $pngBytes = $this->rasterizer->rasterizeFirstPage($pdfBytes);
            if (! $this->isValidPng($pngBytes)) {
                throw new RuntimeException('document_thumbnail_png_invalid');
            }

            $disk = Storage::disk('local');
            $disk->makeDirectory($directory);
            $temporaryPath = $directory.'/.'.$fingerprint.'.'.bin2hex(random_bytes(12)).'.tmp';

            try {
                if (! $disk->put($temporaryPath, $pngBytes)) {
                    throw new RuntimeException('document_thumbnail_write_failed');
                }

                if ($disk->exists($path) && ! $disk->delete($path)) {
                    throw new RuntimeException('document_thumbnail_write_failed');
                }

                if (! $disk->move($temporaryPath, $path)) {
                    throw new RuntimeException('document_thumbnail_write_failed');
                }

                $storedBytes = $disk->get($path);
                if (! is_string($storedBytes) || ! hash_equals(hash('sha256', $pngBytes), hash('sha256', $storedBytes))
                    || ! $this->isValidPng($storedBytes)) {
                    $disk->delete($path);
                    throw new RuntimeException('document_thumbnail_write_failed');
                }
            } finally {
                if ($disk->exists($temporaryPath)) {
                    $disk->delete($temporaryPath);
                }
            }

            $this->removeStaleFingerprints($directory, $fingerprint);

            return $fingerprint;
        });
    }

    public function purge(string $surface, int $templateId): void
    {
        $directory = $this->templateDirectory($surface, $templateId);

        $this->lock($surface, $templateId)->block(5, function () use ($directory): void {
            Storage::disk('local')->deleteDirectory($directory);
        });
    }

    private function templateDirectory(string $surface, int $templateId): string
    {
        if (preg_match('/\A[a-z][a-z0-9_-]{0,63}\z/', $surface) !== 1) {
            throw new InvalidArgumentException('Invalid thumbnail surface.');
        }

        if ($templateId < 1) {
            throw new InvalidArgumentException('Invalid template id.');
        }

        return sprintf('thumbnails/%s/%d', $surface, $templateId);
    }

    private function validateChecksum(string $checksum, string $name): void
    {
        if (preg_match('/\A[a-f0-9]{64}\z/', $checksum) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid thumbnail %s.', $name));
        }
    }

    private function lock(string $surface, int $templateId): Lock
    {
        return Cache::lock(sprintf('document-thumbnail:%s:%d', $surface, $templateId), 30);
    }

    private function isValidPng(string $bytes): bool
    {
        if ($bytes === '' || strlen($bytes) > self::MAX_PNG_BYTES || ! str_starts_with($bytes, "\x89PNG\r\n\x1a\n")) {
            return false;
        }

        $dimensions = @getimagesizefromstring($bytes);
        $width = (int) ($dimensions[0] ?? 0);
        $height = (int) ($dimensions[1] ?? 0);
        if (! is_array($dimensions)
            || ($dimensions['mime'] ?? null) !== 'image/png'
            || $width < 1
            || $width > self::MAX_WIDTH
            || $height < 1
            || $height > self::MAX_HEIGHT
            || $width * $height > 115_200) {
            return false;
        }

        $image = @imagecreatefromstring($bytes);
        if ($image === false) {
            return false;
        }

        imagedestroy($image);

        return true;
    }

    private function removeStaleFingerprints(string $directory, string $currentFingerprint): void
    {
        $disk = Storage::disk('local');
        foreach ($disk->files($directory) as $path) {
            $filename = basename($path);
            if (preg_match('/\A([a-f0-9]{64})\.png\z/', $filename, $matches) === 1
                && $matches[1] !== $currentFingerprint) {
                $disk->delete($path);
            }
        }
    }
}
