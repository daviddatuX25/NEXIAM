<?php

namespace App\Assessment\Omr;

use RuntimeException;

use function getimagesizefromstring;
use function hash;
use function imagealphablending;
use function imagecreatefromstring;
use function imagedestroy;
use function imagejpeg;
use function imagepng;
use function imagerotate;
use function imagesavealpha;
use function imagesx;
use function imagesy;
use function is_array;
use function is_string;
use function max;
use function microtime;
use function ob_end_clean;
use function ob_get_contents;
use function ob_get_level;
use function ob_start;
use function round;
use function str_starts_with;
use function strlen;
use function strtolower;
use function trim;

/**
 * Produces four deterministic quarter-turn candidates after applying EXIF
 * orientation once. A trusted semantic hint changes order only.
 */
final class OmrQuarterTurnNormalizer
{
    public const ORIENTATIONS = OmrQuarterTurnCandidate::ORIENTATIONS;

    private readonly int $maxPixels;

    private readonly int $maxCandidateBytes;

    public function __construct(
        ?int $maxPixels = null,
        ?int $maxCandidateBytes = null,
        private readonly int $jpegQuality = 92,
    ) {
        $this->maxPixels = $maxPixels ?? (int) config('omr.canonical.limits.max_pixels_per_page', 20_000_000);
        $this->maxCandidateBytes = $maxCandidateBytes ?? (int) config('omr.omrchecker.max_candidate_bytes', 10 * 1024 * 1024);
    }

    public function candidates(OmrCaptureInput|string $capture, ?string $mime = null, ?int $preferredOrientation = null): OmrQuarterTurnDiagnostics
    {
        $input = is_string($capture)
            ? new OmrCaptureInput(bytes: $capture, mime: (string) $mime)
            : $capture;
        if (! in_array($preferredOrientation, [null, ...self::ORIENTATIONS], true)) {
            throw new OmrQuarterTurnNormalizationException(
                'orientation_invalid',
                'The preferred orientation must be a quarter-turn.',
            );
        }
        $orientations = $preferredOrientation === null
            ? self::ORIENTATIONS
            : [$preferredOrientation, ...array_values(array_filter(
                self::ORIENTATIONS,
                static fn (int $orientation): bool => $orientation !== $preferredOrientation,
            ))];
        $entryBufferLevel = ob_get_level();

        $dimensions = @getimagesizefromstring($input->bytes);
        if (! is_array($dimensions) || (int) ($dimensions[0] ?? 0) < 1 || (int) ($dimensions[1] ?? 0) < 1) {
            throw new OmrQuarterTurnNormalizationException(
                'image_decode_failed',
                'The capture image cannot be decoded for orientation normalization.',
            );
        }

        $width = (int) $dimensions[0];
        $height = (int) $dimensions[1];
        if ($width > intdiv(PHP_INT_MAX, max(1, $height)) || $width * $height > $this->maxPixels) {
            throw new OmrQuarterTurnNormalizationException(
                'pixel_budget_exceeded',
                'The capture exceeds the configured pixel budget for orientation normalization.',
            );
        }

        $detectedMime = strtolower(trim($dimensions['mime'] ?? ''));
        $inputMime = strtolower(trim($input->mime ?: $detectedMime));
        $isJpeg = $detectedMime === 'image/jpeg'
            || $inputMime === 'image/jpeg'
            || str_starts_with($input->bytes, "\xFF\xD8\xFF");
        $isPng = ! $isJpeg && ($detectedMime === 'image/png' || $inputMime === 'image/png' || str_starts_with($input->bytes, "\x89PNG\r\n\x1a\n"));

        $image = @imagecreatefromstring($input->bytes);
        if ($image === false) {
            throw new OmrQuarterTurnNormalizationException(
                'image_decode_failed',
                'The capture image could not be decoded for orientation normalization.',
            );
        }
        $exifOrientation = $isJpeg ? self::exifOrientation($input->bytes) : 1;
        if ($exifOrientation !== 1) {
            $oriented = $this->applyExifOrientation($image, $exifOrientation);
            if ($oriented !== $image) {
                imagedestroy($image);
                $image = $oriented;
            }
            $width = imagesx($image);
            $height = imagesy($image);
        }

        $candidates = [];
        $timings = [];
        try {
            foreach ($orientations as $orientation) {
                $started = microtime(true);
                if ($orientation === 0) {
                    if ($exifOrientation === 1 && strlen($input->bytes) <= $this->maxCandidateBytes) {
                        $candidateBytes = $input->bytes;
                        $candidateMime = $inputMime !== '' ? $inputMime : ($detectedMime ?: 'image/jpeg');
                    } else {
                        $candidateBufferLevel = ob_get_level();
                        ob_start();
                        try {
                            $encoded = @imagejpeg($image, null, $this->jpegQuality);
                            $candidateBytes = $encoded ? ob_get_contents() : false;
                        } finally {
                            while (ob_get_level() > $candidateBufferLevel) {
                                ob_end_clean();
                            }
                        }
                        $candidateMime = 'image/jpeg';
                    }
                    $candidateWidth = $width;
                    $candidateHeight = $height;
                } else {
                    $candidateImage = @imagerotate($image, $this->rotationAngle($orientation), 0);
                    if ($candidateImage === false) {
                        throw new OmrQuarterTurnNormalizationException(
                            'rotation_failed',
                            "The {$orientation}-degree orientation candidate could not be generated.",
                        );
                    }

                    try {
                        $candidateWidth = imagesx($candidateImage);
                        $candidateHeight = imagesy($candidateImage);
                        if ($candidateWidth < 1 || $candidateHeight < 1
                            || $candidateWidth > intdiv(PHP_INT_MAX, max(1, $candidateHeight))
                            || $candidateWidth * $candidateHeight > $this->maxPixels) {
                            throw new OmrQuarterTurnNormalizationException(
                                'pixel_budget_exceeded',
                                "The {$orientation}-degree orientation candidate exceeds the configured pixel budget.",
                            );
                        }

                        if ($isJpeg) {
                            $candidateBufferLevel = ob_get_level();
                            ob_start();
                            try {
                                $encoded = @imagejpeg($candidateImage, null, $this->jpegQuality);
                                $candidateBytes = $encoded ? ob_get_contents() : false;
                            } finally {
                                while (ob_get_level() > $candidateBufferLevel) {
                                    ob_end_clean();
                                }
                            }
                            $candidateMime = 'image/jpeg';
                        } elseif ($isPng) {
                            imagealphablending($candidateImage, false);
                            imagesavealpha($candidateImage, true);
                            $candidateBufferLevel = ob_get_level();
                            ob_start();
                            try {
                                $encoded = @imagepng($candidateImage, null, 6, 0);
                                $candidateBytes = $encoded ? ob_get_contents() : false;
                            } finally {
                                while (ob_get_level() > $candidateBufferLevel) {
                                    ob_end_clean();
                                }
                            }
                            $candidateMime = 'image/png';

                            if (is_string($candidateBytes) && strlen($candidateBytes) > $this->maxCandidateBytes) {
                                $candidateBufferLevel = ob_get_level();
                                ob_start();
                                try {
                                    $encoded = @imagejpeg($candidateImage, null, 95);
                                    $candidateBytes = $encoded ? ob_get_contents() : false;
                                } finally {
                                    while (ob_get_level() > $candidateBufferLevel) {
                                        ob_end_clean();
                                    }
                                }
                                $candidateMime = 'image/jpeg';
                            }
                        } else {
                            $candidateBufferLevel = ob_get_level();
                            ob_start();
                            try {
                                $encoded = @imagejpeg($candidateImage, null, $this->jpegQuality);
                                $candidateBytes = $encoded ? ob_get_contents() : false;
                            } finally {
                                while (ob_get_level() > $candidateBufferLevel) {
                                    ob_end_clean();
                                }
                            }
                            $candidateMime = 'image/jpeg';
                        }

                        if (! is_string($candidateBytes) || $candidateBytes === '') {
                            throw new OmrQuarterTurnNormalizationException(
                                'rotation_encode_failed',
                                "The {$orientation}-degree orientation candidate could not be encoded.",
                            );
                        }
                    } finally {
                        imagedestroy($candidateImage);
                    }
                }

                if (! is_string($candidateBytes) || $candidateBytes === '') {
                    throw new OmrQuarterTurnNormalizationException(
                        'rotation_encode_failed',
                        "The {$orientation}-degree orientation candidate is empty.",
                    );
                }

                if (strlen($candidateBytes) > $this->maxCandidateBytes) {
                    throw new OmrQuarterTurnNormalizationException(
                        'candidate_size_exceeded',
                        "The {$orientation}-degree orientation candidate exceeds the transport byte budget.",
                    );
                }

                $elapsed = (microtime(true) - $started) * 1000;
                $timings[(string) $orientation] = round($elapsed, 3);
                $candidates[] = new OmrQuarterTurnCandidate(
                    orientation: $orientation,
                    bytes: $candidateBytes,
                    mime: $candidateMime,
                    sha256: hash('sha256', $candidateBytes),
                    width: $candidateWidth,
                    height: $candidateHeight,
                    diagnostics: [
                        'status' => 'generated',
                        'duration_ms' => round($elapsed, 3),
                    ],
                );
            }
        } catch (OmrQuarterTurnNormalizationException $exception) {
            throw $exception;
        } catch (RuntimeException $exception) {
            throw new OmrQuarterTurnNormalizationException(
                'rotation_failed',
                'The capture orientation candidates could not be generated.',
                $exception,
            );
        } finally {
            imagedestroy($image);
            while (ob_get_level() > $entryBufferLevel) {
                ob_end_clean();
            }
        }

        return new OmrQuarterTurnDiagnostics(
            candidates: $candidates,
            timings: $timings,
        );
    }

    /** @return list<OmrQuarterTurnCandidate> */
    public function normalize(OmrCaptureInput|string $capture, ?string $mime = null, ?int $preferredOrientation = null): array
    {
        return $this->candidates($capture, $mime, $preferredOrientation)->candidates;
    }

    public static function exifOrientation(string $bytes): int
    {
        if (! function_exists('exif_read_data')) {
            return 1;
        }

        $path = tempnam(sys_get_temp_dir(), 'nexiam-omr-exif-');
        if ($path === false || file_put_contents($path, $bytes) === false) {
            if ($path !== false) {
                @unlink($path);
            }

            return 1;
        }

        try {
            $exif = @exif_read_data($path);

            return is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;
        } finally {
            @unlink($path);
        }
    }

    private function applyExifOrientation(\GdImage $image, int $orientation): \GdImage
    {
        $oriented = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };
        if (! $oriented instanceof \GdImage) {
            throw new OmrQuarterTurnNormalizationException(
                'rotation_failed',
                'The capture EXIF orientation could not be normalized.',
            );
        }
        if (in_array($orientation, [2, 4, 5, 7], true)) {
            imageflip($oriented, in_array($orientation, [2, 5], true) ? IMG_FLIP_HORIZONTAL : IMG_FLIP_VERTICAL);
        }

        return $oriented;
    }

    private function rotationAngle(int $orientation): int
    {
        return match ($orientation) {
            90 => -90,
            180 => 180,
            270 => 90,
            default => throw new OmrQuarterTurnNormalizationException(
                'orientation_invalid',
                'Only quarter-turn orientations are supported.',
            ),
        };
    }
}
