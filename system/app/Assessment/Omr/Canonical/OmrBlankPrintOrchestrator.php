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
    ) {}

    /**
     * @param  array<string, mixed>|null  $overlay
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
            $source = new Fpdi('P', 'mm');
            if ($source->setSourceFile($staticPath) !== $pageCount) {
                throw new RuntimeException('blank_static_page_count_mismatch');
            }
            $placementPx = OmrQrReservationResolver::resolvePx($overlay);
            $qrResolution = max(320, (int) $placementPx['qr_size']);
            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $payload = $this->qr->sign($instanceCode, $pageNo, $pageCount, $keyId);
                $qrBytes = $this->qrRenderer->png($payload, $qrResolution);
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
     * @param  list<array{page_no: int}>  $compiledPages
     * @return list<array{page_no: int, bytes: string, sha256: string}>
     */
    public function renderQrDisabledSet(string $staticPdfBytes, array $compiledPages): array
    {
        if (! array_is_list($compiledPages) || $compiledPages === []) {
            throw new RuntimeException('print_page_manifest_invalid');
        }

        foreach ($compiledPages as $index => $page) {
            if (! is_array($page) || ! is_int($page['page_no'] ?? null) || $page['page_no'] !== $index + 1) {
                throw new RuntimeException('print_page_manifest_invalid');
            }
        }

        $staticPath = tempnam(sys_get_temp_dir(), 'nexiam-blank-static-');
        if ($staticPath === false || file_put_contents($staticPath, $staticPdfBytes) === false) {
            throw new RuntimeException('blank_static_pdf_write_failed');
        }

        try {
            $source = new Fpdi('P', 'mm');
            if ($source->setSourceFile($staticPath) !== count($compiledPages)) {
                throw new RuntimeException('blank_static_page_count_mismatch');
            }

            $artifacts = [];
            foreach ($compiledPages as $page) {
                $pdf = new Fpdi('P', 'mm');
                $pdf->setSourceFile($staticPath);
                $template = $pdf->importPage($page['page_no']);
                $size = $pdf->getTemplateSize($template);
                $orientation = ($size['width'] ?? 0) > ($size['height'] ?? 0) ? 'L' : 'P';
                $pdf->AddPage($orientation, [$size['width'], $size['height']]);
                $pdf->useTemplate($template);
                $bytes = (string) $pdf->Output('S');

                $artifacts[] = [
                    'page_no' => $page['page_no'],
                    'bytes' => $bytes,
                    'sha256' => hash('sha256', $bytes),
                ];
            }

            return $artifacts;
        } finally {
            @unlink($staticPath);
        }
    }

    /**
     * @param  array<string, mixed>|null  $overlay
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

        $placement = OmrQrReservationResolver::resolveMm($overlay, (float) ($size['width'] ?? 210), (float) ($size['height'] ?? 297));
        $qrSize = $placement['qr_size'];
        $qrX = $placement['qr_x'];
        $qrY = $placement['qr_y'];
        $displayCodeY = $placement['display_code_y'];

        $pdf->Image($qrPath, $qrX, $qrY, $qrSize, $qrSize, 'PNG');
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->SetXY($qrX, $displayCodeY);
        $pdf->Cell($qrSize, 4, $displayCode.' · '.$pageNo.'/'.$pageCount, 0, 0, 'C');

        return (string) $pdf->Output('S');
    }

    /**
     * @param  array<string, mixed>|null  $overlay
     * @param  array{width: float, height: float}  $size
     * @return array{x: float, y: float, size: float}
     */
    private function resolveQrPlacement(?array $overlay, array $size): array
    {
        $placement = OmrQrReservationResolver::resolveMm($overlay, (float) ($size['width'] ?? 210), (float) ($size['height'] ?? 297));

        return [
            'x' => $placement['qr_x'],
            'y' => $placement['qr_y'],
            'size' => $placement['qr_size'],
        ];
    }
}
