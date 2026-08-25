<?php

namespace App\Assessment\Omr\Diagnostics;

use App\Assessment\Omr\Canonical\OmrCalibrationQrPayload;
use App\Assessment\Omr\Canonical\OmrPdfPageRasterizer;
use App\Assessment\Omr\Canonical\OmrQrCodeRenderer;
use App\Printing\PrintRenderer;
use RuntimeException;

use function array_map;
use function array_search;
use function array_values;
use function config;
use function count;
use function file_get_contents;
use function file_put_contents;
use function hash;
use function htmlspecialchars;
use function imagecolorallocate;
use function imagecopyresampled;
use function imagecreatefromstring;
use function imageellipse;
use function imagefilledellipse;
use function imagepng;
use function imagesavealpha;
use function imagesx;
use function imagesy;
use function imagestring;
use function implode;
use function intdiv;
use function is_array;
use function is_string;
use function json_encode;
use function min;
use function ob_get_clean;
use function ob_start;
use function ord;
use function preg_match;
use function sprintf;
use function str_split;
use function str_starts_with;
use function strpos;
use function substr;
use function tempnam;
use function unlink;

/**
 * Physical challenge pack renderer (OpenSpec 35.19, AD-7).
 *
 * Produces TWO separate artifacts from one proof plan:
 *
 *  (a) the exact printable challenge sheet — the canonical page raster
 *      machine-filled with the plan's expected vector (and calibration
 *      digits when an ID grid is mapped), plus the signed calibration-purpose
 *      QR and checksummed display code. Rendered server-side with GD at
 *      300 PPI; NO browser geometry, HTML, or CSS enters the sheet.
 *
 *  (b) a grouped human shading guide (HTML + PDF via the shared print
 *      renderer). Quick mode lists only the first/middle/last labels per
 *      section plus the full option legend, section list, and ID columns;
 *      full mode lists every expanded label.
 *
 * Repeated rendering of the same target/purpose produces byte-identical
 * sheets and guides (35.20 golden stability).
 */
final class OmrPhysicalChallengeRenderer
{
    private const CROCKFORD = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    public function __construct(
        private readonly OmrProofPlanner $planner,
        private readonly OmrPdfPageRasterizer $rasterizer,
        private readonly OmrQrCodeRenderer $qrRenderer,
        private readonly OmrCalibrationQrPayload $calibrationQr,
        private readonly PrintRenderer $pdf,
    ) {
    }
    /**
     * @param  array<string, int>  $insetsPx  full-paper inset ring (left/top/right/bottom px) mapping the marker frame to the printed paper
     * @return array{
     *     plan: OmrProofPlan,
     *     sheet: array{
     *         pages: list<array{page_no:int,bytes:string,sha256:string,width:int,height:int}>,
     *         qr_payloads: list<array{page_no:int,payload:string,payload_hash:string}>,
     *         challenge_code: string,
     *         display_code: string,
     *         sheet_hash: string,
     *     },
     *     guide: array{html:string,pdf:string,sha256:string,mode:string},
     *     sections: list<array{page_no:int,area:string,options:list<string>,labels:list<array{label:string,option:string}>}>,
     * }
     */
    public function render(OmrDiagnosticTarget $target, ProofPurpose $purpose, string $pdfBytes, array $insetsPx = [], string $planVersion = 'v1'): array
    {
        $plan = $this->planner->plan($target, $purpose, $planVersion);
        $full = $purpose !== ProofPurpose::QuickRehearsal;

        $sheet = $this->renderSheet($target, $plan, $pdfBytes, $insetsPx);
        $guide = $this->renderGuide($target, $plan, $full);

        return [
            'plan' => $plan,
            'sheet' => $sheet,
            'guide' => $guide,
            'sections' => $guide['sections'],
        ];
    }

    /**
     * @return array{
     *     pages: list<array{page_no:int,bytes:string,sha256:string,width:int,height:int}>,
     *     qr_payloads: list<array{page_no:int,payload:string,payload_hash:string}>,
     *     challenge_code: string,
     *     display_code: string,
     *     sheet_hash: string,
     * }
     */
    private function renderSheet(OmrDiagnosticTarget $target, OmrProofPlan $plan, string $pdfBytes, array $insetsPx): array
    {
        $pdfPath = tempnam(sys_get_temp_dir(), 'nexiam-challenge-sheet-');
        if ($pdfPath === false || file_put_contents($pdfPath, $pdfBytes) === false) {
            if ($pdfPath !== false) {
                @unlink($pdfPath);
            }
            throw new RuntimeException('challenge_sheet_pdf_write_failed');
        }
        try {
            $rasterPages = $this->rasterizer->rasterize($pdfPath, ['ppi' => (int) config('omr.canonical.ppi', 300)]);
        } finally {
            @unlink($pdfPath);
        }
        if ($rasterPages === []) {
            throw new RuntimeException('challenge_sheet_raster_empty');
        }

        $keyId = (string) config('omr.qr-keyring.current', 'dev-v1');
        $pageCount = $target->pageCount();
        $displayCode = $this->displayCode($plan->challengeCode);
        $sheetPages = [];
        $qrPayloads = [];

        foreach ($rasterPages as $page) {
            $pageNo = (int) $page['page_no'];
            $image = @imagecreatefromstring((string) file_get_contents($page['path']));
            if ($image === false) {
                throw new RuntimeException('challenge_sheet_raster_invalid');
            }
            $black = imagecolorallocate($image, 0, 0, 0);
            $template = $target->nativeTemplate($pageNo);
            $expected = $plan->expectedVectorForPage($pageNo);
            $identity = [];
            foreach ((array) ($template['fieldBlocks'] ?? []) as $block) {
                if (! is_array($block)) {
                    continue;
                }
                $values = is_array($block['bubbleValues'] ?? null)
                    ? array_values(array_map('strval', $block['bubbleValues']))
                    : match ((string) ($block['fieldType'] ?? 'QTYPE_MCQ4')) {
                        'QTYPE_MCQ5' => ['A', 'B', 'C', 'D', 'E'],
                        'QTYPE_INT' => ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
                        default => ['A', 'B', 'C', 'D'],
                    };
                $labels = [];
                foreach ((array) ($block['fieldLabels'] ?? []) as $declaration) {
                    $labels = [...$labels, ...\App\Assessment\Omr\NativeBundle::expandLabel((string) $declaration)];
                }
                foreach ($labels as $labelIndex => $label) {
                    if (array_key_exists($label, $expected)) {
                        $selected = array_search($expected[$label], $values, true);
                        if ($selected === false) {
                            throw new RuntimeException('challenge_plan_option_missing: '.$label);
                        }
                        $this->shade($image, $block, $values, $labelIndex, $selected, $black, $insetsPx);
                    } elseif (str_starts_with($label, 'APPLICANT_NUMBER_')) {
                        $identity[$label] = ['block' => $block, 'values' => $values, 'labelIndex' => $labelIndex];
                    }
                }
            }
            foreach ($plan->calibrationDigits as $label => $digit) {
                $entry = $identity[$label] ?? null;
                if ($entry === null) {
                    continue;
                }
                $selected = array_search((string) $digit, $entry['values'], true);
                if ($selected === false) {
                    throw new RuntimeException('challenge_calibration_option_missing: '.$label);
                }
                $this->shade($image, $entry['block'], $entry['values'], $entry['labelIndex'], $selected, $black, $insetsPx);
            }

            $payload = $this->calibrationQr->sign($plan->challengeCode, $pageNo, $pageCount, $keyId);
            $qrImage = @imagecreatefromstring($this->qrRenderer->png($payload, 240));
            if ($qrImage === false) {
                throw new RuntimeException('challenge_sheet_qr_invalid');
            }
            $qrSize = min(240, intdiv(imagesx($image), 6));
            [$qrX, $qrY] = $this->qrPlacement($image, $qrSize);
            imagecopyresampled($image, $qrImage, $qrX, $qrY, 0, 0, $qrSize, $qrSize, imagesx($qrImage), imagesy($qrImage));
            imagestring($image, 3, $qrX, min(imagesy($image) - 12, $qrY + $qrSize + 8), $displayCode, $black);
            imagesavealpha($image, false);
            ob_start();
            imagepng($image, null, 6);
            $sheetBytes = (string) ob_get_clean();
            $sheetPages[] = [
                'page_no' => $pageNo,
                'bytes' => $sheetBytes,
                'sha256' => hash('sha256', $sheetBytes),
                'width' => imagesx($image),
                'height' => imagesy($image),
            ];
            $qrPayloads[] = [
                'page_no' => $pageNo,
                'payload' => $payload,
                'payload_hash' => $this->calibrationQr->payloadHash($payload),
            ];
        }

        $sheetHash = hash('sha256', json_encode([
            'plan_hash' => $plan->planHash(),
            'pages' => array_map(
                static fn (array $page): array => ['page_no' => $page['page_no'], 'sha256' => $page['sha256']],
                $sheetPages,
            ),
            'qr_payloads' => array_map(
                static fn (array $entry): array => ['page_no' => $entry['page_no'], 'payload_hash' => $entry['payload_hash']],
                $qrPayloads,
            ),
            'display_code' => $displayCode,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        return [
            'pages' => $sheetPages,
            'qr_payloads' => $qrPayloads,
            'challenge_code' => $plan->challengeCode,
            'display_code' => $displayCode,
            'sheet_hash' => $sheetHash,
        ];
    }

    /**
     * @param  array<string, mixed>  $block
     * @param  list<string>  $values
     */
    private function shade(\GdImage $image, array $block, array $values, int $labelIndex, int $selected, int $black, array $insetsPx): void
    {
        $origin = (array) ($block['origin'] ?? [0, 0]);
        $originX = (int) ($origin[0] ?? 0) + (int) ($insetsPx['left'] ?? 0);
        $originY = (int) ($origin[1] ?? 0) + (int) ($insetsPx['top'] ?? 0);
        $bubble = (array) ($block['bubbleDimensions'] ?? [20, 20]);
        $bubbleGap = (int) ($block['bubblesGap'] ?? 0);
        $labelGap = (int) ($block['labelsGap'] ?? 0);
        $vertical = (string) ($block['direction'] ?? 'horizontal') === 'vertical';
        $cx = $vertical
            ? $originX + $labelIndex * $labelGap + (int) $bubble[0] / 2
            : $originX + $selected * $bubbleGap + (int) $bubble[0] / 2;
        $cy = $vertical
            ? $originY + $selected * $bubbleGap + (int) $bubble[1] / 2
            : $originY + $labelIndex * $labelGap + (int) $bubble[1] / 2;
        imagefilledellipse($image, $cx, $cy, (int) $bubble[0], (int) $bubble[1], $black);
        imageellipse($image, $cx, $cy, (int) $bubble[0], (int) $bubble[1], $black);
    }

    /** @return array{0: int, 1: int} */
    private function qrPlacement(\GdImage $image, int $size): array
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $left = 48;
        $right = $width - 48;
        $top = 48;
        $bottom = $height - 48;
        $x = max($left, min(intdiv($width - $size, 2), $right - $size));
        $y = max($top, min($top + 20, $bottom - $size));

        return [max(0, $x), max(0, $y)];
    }

    /**
     * @return array{html:string,pdf:string,sha256:string,mode:string,sections:list<array{page_no:int,area:string,options:list<string>,labels:list<array{label:string,option:string}>}>}
     */
    private function renderGuide(OmrDiagnosticTarget $target, OmrProofPlan $plan, bool $full): array
    {
        $sections = $this->guideSections($target, $plan, $full);

        $purposeLabel = match ($plan->purpose) {
            ProofPurpose::Digital => 'Digital proof',
            ProofPurpose::QuickRehearsal => 'Quick rehearsal',
            ProofPurpose::FullQualification => 'Full qualification',
        };
        $mode = $full ? 'full' : 'quick';
        $lines = [];
        $lines[] = '<!doctype html>';
        $lines[] = '<html lang="en"><head><meta charset="utf-8">';
        $lines[] = '<title>NEXIAM OMR Shading Guide</title>';
        $lines[] = '<style>'
            .'body{font-family:Helvetica,Arial,sans-serif;font-size:10pt;padding:14mm;color:#111;}'
            .'h1{font-size:15pt;margin:0 0 2mm;}'
            .'h2{font-size:12pt;margin:6mm 0 1mm;border-bottom:1px solid #999;padding-bottom:1mm;}'
            .'h3{font-size:10.5pt;margin:3mm 0 0.5mm;}'
            .'p,li{font-size:9.5pt;}'
            .'code{font-family:Menlo,Consolas,monospace;font-size:9pt;}'
            .'table{border-collapse:collapse;margin:1mm 0 3mm;}'
            .'th,td{border:1px solid #bbb;padding:0.8mm 2mm;font-size:9pt;text-align:left;}'
            .'th{background:#f0f0f0;}'
            .'</style></head><body>';
        $lines[] = '<h1>NEXIAM OMR Shading Guide</h1>';
        $lines[] = '<p><strong>Challenge:</strong> <code>'.htmlspecialchars($plan->challengeCode).'</code>'
            .' &nbsp; <strong>Purpose:</strong> '.htmlspecialchars($purposeLabel)
            .' &nbsp; <strong>Mode:</strong> '.htmlspecialchars($mode).'</p>';
        $lines[] = '<p><strong>Plan hash:</strong> <code>'.htmlspecialchars($plan->planHash()).'</code></p>';
        $lines[] = '<p><strong>Instruction:</strong> '.htmlspecialchars($this->instructionOf($plan)).'</p>';
        $lines[] = '<p><strong>Print at 100% / actual size — do not fit to page.</strong></p>';

        foreach ($sections as $index => $section) {
            if ($index === 0 || $sections[$index - 1]['page_no'] !== $section['page_no']) {
                $lines[] = '<h2>Page '.$section['page_no'].'</h2>';
            }
            $lines[] = '<h3>Section '.htmlspecialchars($section['area']).'</h3>';
            $lines[] = '<p>Options: '.implode(', ', array_map('htmlspecialchars', $section['options'])).'</p>';
            $lines[] = '<table><thead><tr><th>Field</th><th>Shade</th></tr></thead><tbody>';
            foreach ($section['labels'] as $entry) {
                $lines[] = '<tr><td><code>'.htmlspecialchars($entry['label']).'</code></td><td>'.htmlspecialchars($entry['option']).'</td></tr>';
            }
            $lines[] = '</tbody></table>';
        }

        $identity = $this->guideIdentity($target, $plan);
        if ($identity !== []) {
            $lines[] = '<h2>Applicant number columns (calibration)</h2>';
            $lines[] = '<p>Shade one digit per column. This is a calibration-only expectation — no applicant is identified.</p>';
            $lines[] = '<table><thead><tr><th>Column</th><th>Digit</th></tr></thead><tbody>';
            foreach ($identity as $label => $digit) {
                $lines[] = '<tr><td><code>'.htmlspecialchars($label).'</code></td><td>'.htmlspecialchars((string) $digit).'</td></tr>';
            }
            $lines[] = '</tbody></table>';
        }

        $lines[] = '</body></html>';
        $html = implode("\n", $lines);
        $pdfBytes = $this->pdf->render($html, ['format' => 'a4', 'orientation' => 'portrait']);

        return [
            'html' => $html,
            'pdf' => $pdfBytes,
            'sha256' => hash('sha256', $html),
            'mode' => $mode,
            'sections' => $sections,
        ];
    }

    /**
     * Grouped shading entries: one section per page + area. Quick mode keeps
     * only the first/middle/last labels plus every section, its full option
     * legend, and every ID column; full mode lists every expanded label.
     *
     * @return list<array{page_no:int,area:string,options:list<string>,labels:list<array{label:string,option:string}>}>
     */
    private function guideSections(OmrDiagnosticTarget $target, OmrProofPlan $plan, bool $full): array
    {
        $optionsByLabel = [];
        foreach ((array) ($target->reviewManifest()['fields'] ?? []) as $field) {
            if (! is_array($field) || ! is_string($field['label'] ?? null) || ! is_string($field['option'] ?? null)) {
                continue;
            }
            $optionsByLabel[(string) $field['label']][] = (string) $field['option'];
        }

        $sections = [];
        foreach ($plan->pageInstructions as $instruction) {
            $pageNo = (int) $instruction['page_no'];
            $byArea = [];
            foreach ((array) ($instruction['labels'] ?? []) as $label) {
                if (preg_match('/^([A-Z0-9_]+)_Q_\d+$/', (string) $label, $matches) === 1) {
                    $byArea[$matches[1]][] = (string) $label;
                }
            }
            foreach ($byArea as $area => $labels) {
                $picked = $full ? $labels : $this->firstMiddleLast($labels);
                $options = $optionsByLabel[$labels[0] ?? ''] ?? [];
                $entries = [];
                foreach ($picked as $label) {
                    $entries[] = [
                        'label' => $label,
                        'option' => (string) ($plan->expectedVector[$label] ?? ''),
                    ];
                }
                $sections[] = [
                    'page_no' => $pageNo,
                    'area' => $area,
                    'options' => array_values($options),
                    'labels' => $entries,
                ];
            }
        }

        return $sections;
    }

    /** @return array<string, int> */
    private function guideIdentity(OmrDiagnosticTarget $target, OmrProofPlan $plan): array
    {
        if ($plan->calibrationDigits === []) {
            return [];
        }
        $mapped = [];
        foreach ((array) ($target->reviewManifest()['identity']['labels'] ?? []) as $label) {
            $mapped[(string) $label] = true;
        }
        if ($mapped === []) {
            return [];
        }

        return array_filter($plan->calibrationDigits, static fn (string $label): bool => isset($mapped[$label]), ARRAY_FILTER_USE_KEY);
    }

    /** @param list<string> $labels @return list<string> */
    private function firstMiddleLast(array $labels): array
    {
        $count = count($labels);
        if ($count <= 2) {
            return $labels;
        }
        $middle = $labels[intdiv($count - 1, 2)];
        $picked = [$labels[0], $middle, $labels[$count - 1]];

        return array_values(array_unique($picked));
    }

    private function instructionOf(OmrProofPlan $plan): string
    {
        $instruction = $plan->pageInstructions[0]['instruction'] ?? '';
        if (! is_string($instruction) || $instruction === '') {
            return OmrProofPlanner::PLAN_INSTRUCTION_DIGITAL;
        }

        return $instruction;
    }

    private function displayCode(string $challengeCode): string
    {
        $base = substr($this->crockford(hash('sha256', $challengeCode)), 0, 6);

        return 'CH-'.$base.$this->checkDigit($base);
    }

    private function crockford(string $bytes): string
    {
        $buffer = 0;
        $bits = 0;
        $output = '';
        foreach (str_split($bytes) as $character) {
            $buffer = ($buffer << 8) | ord($character);
            $bits += 8;
            while ($bits >= 5) {
                $bits -= 5;
                $output .= self::CROCKFORD[($buffer >> $bits) & 31];
            }
        }
        if ($bits > 0) {
            $output .= self::CROCKFORD[($buffer << (5 - $bits)) & 31];
        }

        return $output;
    }

    private function checkDigit(string $base): string
    {
        $sum = 0;
        foreach (str_split($base) as $character) {
            $sum += strpos(self::CROCKFORD, $character);
        }

        return self::CROCKFORD[$sum % strlen(self::CROCKFORD)];
    }
}
