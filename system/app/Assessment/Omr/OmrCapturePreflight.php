<?php

namespace App\Assessment\Omr;

use function finfo_buffer;
use function finfo_close;
use function finfo_open;
use function getimagesizefromstring;
use function is_array;
use function is_string;
use function preg_match_all;
use function strlen;
use function strtolower;
use function substr;

/**
 * Read-only capture resource preflight (OpenSpec 36.6).
 *
 * This boundary checks only MIME/magic bytes, byte limits, decoded image
 * pixels, and PDF page count. Marker detection, layout registration, worker
 * calls, recognition, score decisions, and workflow writes belong to later
 * seams.
 */
final class OmrCapturePreflight
{
    public const MODE_MARKER = 'marker';

    public const MODE_SCAN = 'scan';

    public const OUTCOME_VALIDATED = 'validated';

    public const OUTCOME_REJECTED = 'rejected';

    public const OUTCOME_NOT_APPLICABLE = 'not_applicable';

    private const IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    private readonly OmrCapturePreflightLimits $limits;

    public function __construct(?OmrCapturePreflightLimits $limits = null)
    {
        $this->limits = $limits ?? OmrCapturePreflightLimits::fromConfig();
    }

    public function validate(OmrCaptureInput|string $input, ?string $mime = null, int $pageNo = 1, ?int $expectedPageCount = null): OmrPreflightResult
    {
        $input = is_string($input)
            ? new OmrCaptureInput(bytes: $input, mime: (string) $mime, pageNo: $pageNo, expectedPageCount: $expectedPageCount)
            : $input;
        $mime = strtolower(trim($input->mime));
        $bytes = $input->bytes;

        if ($bytes === '' || strlen($bytes) > $this->limits->maxUploadBytes) {
            return OmrPreflightResult::rejected(
                $input,
                'upload_too_large',
                'The capture exceeds the configured upload limit.',
            );
        }

        $detected = $this->detectedMime($bytes);
        if ($detected === '' || ! in_array($detected, [...self::IMAGE_MIMES, 'application/pdf'], true)) {
            return OmrPreflightResult::rejected(
                $input,
                'mime_detection_failed',
                'Captures must be JPEG, PNG, WebP, or a scanned PDF.',
            );
        }
        if ($detected !== $mime) {
            return OmrPreflightResult::rejected(
                $input,
                'magic_mismatch',
                'The declared capture type does not match the file contents.',
            );
        }

        if ($mime === 'application/pdf') {
            return $this->validatePdf($input);
        }

        $dimensions = @getimagesizefromstring($bytes);
        if (! is_array($dimensions) || (int) ($dimensions[0] ?? 0) < 1 || (int) ($dimensions[1] ?? 0) < 1) {
            return OmrPreflightResult::rejected(
                $input,
                'image_decode_failed',
                'The submitted capture image cannot be decoded.',
            );
        }

        $width = (int) $dimensions[0];
        $height = (int) $dimensions[1];
        if ($width > intdiv(PHP_INT_MAX, max(1, $height)) || $width * $height > $this->limits->maxPixelsPerPage) {
            return OmrPreflightResult::rejected(
                $input,
                'pixel_budget_exceeded',
                'The capture exceeds the configured pixel budget.',
                pageCount: 1,
                metadata: ['width' => $width, 'height' => $height],
            );
        }

        if ($input->pageNo < 1
            || ($input->expectedPageCount !== null
                && ($input->expectedPageCount < 1 || $input->pageNo > $input->expectedPageCount))) {
            return OmrPreflightResult::rejected(
                $input,
                'capture_page_out_of_range',
                'The submitted page is outside the pinned capture.',
                pageCount: 1,
            );
        }

        return OmrPreflightResult::accepted(
            $input,
            pageCount: 1,
            width: $width,
            height: $height,
            metadata: ['detected_mime' => $detected],
        );
    }

    private function detectedMime(string $bytes): string
    {
        $handle = finfo_open(FILEINFO_MIME_TYPE);
        if ($handle === false) {
            return '';
        }

        try {
            $detected = finfo_buffer($handle, $bytes);
        } finally {
            finfo_close($handle);
        }

        return is_string($detected) ? strtolower($detected) : '';
    }

    private function validatePdf(OmrCaptureInput $input): OmrPreflightResult
    {
        if (! str_starts_with($input->bytes, '%PDF-')) {
            return OmrPreflightResult::rejected(
                $input,
                'magic_mismatch',
                'The declared PDF does not start with the PDF magic bytes.',
            );
        }

        $scan = substr($input->bytes, 0, min(strlen($input->bytes), 4 * 1024 * 1024));
        preg_match_all('/\/Type\s*\/Page\b/i', $scan, $matches);
        $pageCount = is_array($matches[0] ?? null) ? count($matches[0]) : 0;
        if ($pageCount < 1) {
            return OmrPreflightResult::rejected(
                $input,
                'pdf_page_count_invalid',
                'The scanned PDF has no readable pages.',
                pageCount: 0,
            );
        }
        if ($pageCount > $this->limits->maxPagesPerSource) {
            return OmrPreflightResult::rejected(
                $input,
                'page_limit_exceeded',
                'The scanned PDF page count exceeds the configured limit.',
                pageCount: $pageCount,
                metadata: ['max_pages' => $this->limits->maxPagesPerSource],
            );
        }
        if ($input->pageNo < 1 || $input->pageNo > $pageCount
            || ($input->expectedPageCount !== null
                && ($input->expectedPageCount < 1 || $input->pageNo > $input->expectedPageCount))) {
            return OmrPreflightResult::rejected(
                $input,
                'capture_page_out_of_range',
                'The submitted page is outside the scanned PDF.',
                pageCount: $pageCount,
            );
        }

        return OmrPreflightResult::accepted(
            $input,
            pageCount: $pageCount,
            width: null,
            height: null,
            metadata: ['detected_mime' => 'application/pdf'],
        );
    }
}
