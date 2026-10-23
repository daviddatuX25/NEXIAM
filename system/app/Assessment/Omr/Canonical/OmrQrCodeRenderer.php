<?php

namespace App\Assessment\Omr\Canonical;

use App\Assessment\Omr\OmrRenderException;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;

use function config;

/**
 * Server-side QR renderer pinned for queued blank printing.
 *
 * Output is deterministic for identical payload and options. No network or
 * external process is involved at render time.
 */
final class OmrQrCodeRenderer
{
    public function svg(string $payload, ?int $size = null): string
    {
        $code = $this->code($payload, $size);

        try {
            $result = (new SvgWriter)->write($code);

            return $result->getString();
        } catch (\Throwable $exception) {
            throw new OmrRenderException($exception);
        }
    }

    public function png(string $payload, ?int $size = null): string
    {
        $code = $this->code($payload, $size);

        try {
            $result = (new PngWriter)->write($code);

            return $result->getString();
        } catch (\Throwable $exception) {
            throw new OmrRenderException($exception);
        }
    }

    private function code(string $payload, ?int $size): QrCode
    {
        $size ??= (int) config('omr.canonical.qr.size_px', 300);
        $margin = (int) config('omr.canonical.qr.quiet_zone', 4);
        if ($size < 64 || $margin < 4) {
            throw new \InvalidArgumentException('QR size must be at least 64px and quiet zone at least four modules.');
        }

        return new QrCode(
            data: $payload,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: $size,
            margin: $margin,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
        );
    }
}
