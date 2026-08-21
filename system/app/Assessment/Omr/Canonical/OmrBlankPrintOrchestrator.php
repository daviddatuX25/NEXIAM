<?php

namespace App\Assessment\Omr\Canonical;

use RuntimeException;
use setasign\Fpdi\Fpdi;

use function file_put_contents;
use function hash;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

final class OmrBlankPrintOrchestrator
{
    public function __construct(
        private readonly OmrQrPayload $qr,
        private readonly OmrQrCodeRenderer $qrRenderer,
    ) {
    }

    /**
     * @param array<string, mixed>|null $overlay
     * @return list<array{page_no:int,bytes:string,sha256:string,payload:string,payload_hash:string}>
     */
    public function renderSet(string $staticPdfBytes, string $instanceCode, string $displayCode, int $pageCount, ?string $keyId = null, ?array $overlay = null): array
    {
        $keyId ??= (string) config('omr.qr-keyring.current', 'dev-v1');
        $staticPath = tempnam(sys_get_temp_dir(), 'nexiam-blank-static-');
        if ($staticPath === false || file_put_contents($staticPath, $staticPdfBytes) === false) {
            throw new RuntimeException('blank_static_pdf_write_failed');
        }
        $artifacts = [];
        try {
            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $payload = $this->qr->sign($instanceCode, $pageNo, $pageCount, $keyId);
                $qrBytes = $this->qrRenderer->png($payload);
                $qrPath = tempnam(sys_get_temp_dir(), 'nexiam-blank-qr-');
                if ($qrPath === false || file_put_contents($qrPath, $qrBytes) === false) {
                    throw new RuntimeException('blank_qr_write_failed');
                }
                try {
                    $bytes = $this->composePage($staticPath, $qrPath, $pageNo, $displayCode, $pageCount, $overlay);
                } finally {
                    @unlink($qrPath);
                }
                $artifacts[] = [
                    'page_no' => $pageNo,
                    'bytes' => $bytes,
                    'sha256' => hash('sha256', $bytes),
                    'payload' => $payload,
                    'payload_hash' => $this->qr->payloadHash($payload),
                ];
            }
        } finally {
            @unlink($staticPath);
        }

        return $artifacts;
    }

    /**
     * @param array<string, mixed>|null $overlay
     */
    private function composePage(string $staticPath, string $qrPath, int $pageNo, string $displayCode, int $pageCount, ?array $overlay = null): string
    {
        $pdf = new Fpdi('P', 'mm');
        $sourcePages = $pdf->setSourceFile($staticPath);
        if ($pageNo > $sourcePages) {
            throw new RuntimeException('blank_static_page_missing');
        }
        $template = $pdf->importPage($pageNo);
        $size = $pdf->getTemplateSize($template);
        $orientation = ($size['width'] ?? 0) > ($size['height'] ?? 0) ? 'L' : 'P';
        $pdf->AddPage($orientation, [$size['width'], $size['height']]);
        $pdf->useTemplate($template);

        $placement = $this->resolveQrPlacement($overlay, ['width' => (float) ($size['width'] ?? 210), 'height' => (float) ($size['height'] ?? 297)]);
        $qrSize = $placement['size'];
        $qrX = $placement['x'];
        $qrY = $placement['y'];

        $pdf->Image($qrPath, $qrX, $qrY, $qrSize, $qrSize, 'PNG');
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->SetXY($qrX, $qrY + $qrSize + 2);
        $pdf->Cell($qrSize, 4, $displayCode.' · '.$pageNo.'/'.$pageCount, 0, 0, 'C');

        return (string) $pdf->Output('S');
    }

    /**
     * @param array<string, mixed>|null $overlay
     * @param array{width: float, height: float} $size
     * @return array{x: float, y: float, size: float}
     */
    private function resolveQrPlacement(?array $overlay, array $size): array
    {
        $boxes = $overlay['boxes'] ?? [];
        $qrBox = null;
        if (is_array($boxes)) {
            foreach ($boxes as $box) {
                if (is_array($box) && in_array($box['tag'] ?? '', ['blank_instance_qr', 'instance_qr'], true)) {
                    $qrBox = $box;
                    break;
                }
            }
        } elseif (isset($overlay['x'], $overlay['y'], $overlay['width'])) {
            $qrBox = $overlay;
        }

        if ($qrBox !== null) {
            $boxW = (float) $qrBox['width'];
            $boxH = (float) ($qrBox['height'] ?? $qrBox['width']);
            $boxX = (float) $qrBox['x'];
            $boxY = (float) $qrBox['y'];

            if ($boxW > 100 || $boxX > 100 || $boxY > 100) {
                $pxPerMm = 300.0 / 25.4;
                $boxX /= $pxPerMm;
                $boxY /= $pxPerMm;
                $boxW /= $pxPerMm;
                $boxH /= $pxPerMm;
            }

            $qrSize = min($boxW, $boxH);
            $qrSize = min(max(15.0, $qrSize), ((float) $size['width']) / 3);

            return [
                'x' => max(0.0, min((float) $size['width'] - $qrSize, $boxX)),
                'y' => max(0.0, min((float) $size['height'] - $qrSize - 6, $boxY)),
                'size' => $qrSize,
            ];
        }

        $defaultSize = min(28.0, ((float) $size['width']) / 7);

        return [
            'x' => (float) $size['width'] - $defaultSize - 12,
            'y' => 10.0,
            'size' => $defaultSize,
        ];
    }
}
