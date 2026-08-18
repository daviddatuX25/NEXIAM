<?php

namespace App\ResultSheet\Rendering;

use RuntimeException;
use setasign\Fpdi\Fpdi;

/**
 * Concatenates PDF byte payloads into one document via FPDI (legacy-proven
 * merge fallback). Preserves each page's size and orientation.
 *
 * @param  list<string>  $pdfBytesList
 */
final class PdfMerger
{
    public function merge(array $pdfBytesList): string
    {
        if ($pdfBytesList === []) {
            throw new RuntimeException('Nothing to merge.');
        }

        if (count($pdfBytesList) === 1) {
            return $pdfBytesList[0];
        }

        $tempDir = config('result-sheet.temp_dir', storage_path('app/temp/libreoffice'));

        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $pdf = new Fpdi;
        $pdf->SetAutoPageBreak(false);

        foreach ($pdfBytesList as $index => $bytes) {
            $temp = tempnam($tempDir, 'merge_').'.pdf';

            if ($temp === false) {
                throw new RuntimeException('Unable to allocate a merge temp file.');
            }

            file_put_contents($temp, $bytes);

            try {
                $pageCount = $pdf->setSourceFile($temp);

                for ($page = 1; $page <= $pageCount; $page++) {
                    $templateId = $pdf->importPage($page);
                    $size = $pdf->getTemplateSize($templateId);
                    $orientation = ($size['width'] ?? 0) > ($size['height'] ?? 0) ? 'L' : 'P';
                    $pdf->AddPage($orientation, [$size['width'], $size['height']]);
                    $pdf->useTemplate($templateId);
                }
            } finally {
                @unlink($temp);
            }
        }

        $output = $pdf->Output('S');

        if ($output === '') {
            throw new RuntimeException('PDF merge produced no output.');
        }

        return $output;
    }
}
