<?php

namespace App\Assessment\Omr\Canonical;

use App\Assessment\Omr\Diagnostics\OmrProofPlan;
use App\Assessment\Omr\NativeBundle;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

use function array_key_exists;
use function array_search;
use function array_values;
use function count;
use function file_get_contents;
use function file_put_contents;
use function hash;
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
use function is_array;
use function is_string;
use function json_encode;
use function min;
use function ob_get_clean;
use function ob_start;
use function str_starts_with;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

final class OmrDigitalProofGenerator
{
    public function __construct(
        private readonly OmrPdfPageRasterizer $rasterizer,
        private readonly OmrBlankInstanceService $instances,
        private readonly OmrQrPayload $qr,
        private readonly OmrQrCodeRenderer $qrRenderer,
    ) {
    }

    /**
     * Machine-fills the draft's final composed exact-size PDF against an
     * {@see OmrProofPlan} (OpenSpec 35.18): the answer vector and any
     * calibration digits come from the plan, never from a local modulo
     * pattern. The signed blank-instance QR and display code keep their
     * exact machine rendering; the proof hash binds the plan hash so
     * repeated identical plans produce identical hashes.
     *
     * @return array{pages:list<array{page_no:int,bytes:string,sha256:string,width:int,height:int}>,answer_vector:array<string,string>,source_pdf_asset_id:int,proof_hash:string,instance_code:string,display_code:string}
     */
    public function generate(OmrTemplateAuthoringDraft $draft, OmrProofPlan $plan): array
    {
        $authoring = (array) $draft->authoring_json;
        $mapping = (array) ($authoring['mapping'] ?? []);
        $artifactSet = (array) ($authoring['canonical']['artifact_set'] ?? []);
        $templatePages = (array) ($artifactSet['template']['pages'] ?? []);
        $blocksByPage = [];
        if ($templatePages !== []) {
            foreach ($templatePages as $templatePage) {
                if (! is_array($templatePage)) {
                    continue;
                }
                $templatePageNo = max(1, (int) ($templatePage['page_no'] ?? 1));
                foreach ((array) ($templatePage['fieldBlocks'] ?? []) as $blockName => $block) {
                    if (! is_array($block)) {
                        continue;
                    }
                    $block['page_no'] = $templatePageNo;
                    $blocksByPage[$templatePageNo][(string) $blockName] = $block;
                }
            }
        } else {
            foreach ((array) ($mapping['field_blocks'] ?? []) as $blockName => $block) {
                if (! is_array($block)) {
                    continue;
                }
                $pageNo = max(1, (int) ($block['page_no'] ?? 1));
                $blocksByPage[$pageNo][(string) $blockName] = $block;
            }
        }
        if ($blocksByPage === []) {
            throw new RuntimeException('digital_proof_mapping_missing');
        }
        $pdfAssetId = (int) (($artifactSet['pdf_asset_id'] ?? null) ?: ($authoring['canonical']['pdf_asset_id'] ?? 0));
        $pdfAsset = $draft->assets()->whereKey($pdfAssetId)->first();
        if ($pdfAsset === null || $pdfAsset->mime !== 'application/pdf') {
            throw new RuntimeException('digital_proof_pdf_missing');
        }
        $pdfBytes = Storage::disk($pdfAsset->disk)->get($pdfAsset->path);
        if (! is_string($pdfBytes) || hash('sha256', $pdfBytes) !== $pdfAsset->sha256) {
            throw new RuntimeException('asset_integrity_failure');
        }
        $pdfPath = tempnam(sys_get_temp_dir(), 'nexiam-digital-proof-');
        if ($pdfPath === false || file_put_contents($pdfPath, $pdfBytes) === false) {
            throw new RuntimeException('digital_proof_pdf_write_failed');
        }
        try {
            $rasterPages = $this->rasterizer->rasterize($pdfPath, [
                'width_mm' => $authoring['canonical']['settings']['paper_width_mm'] ?? null,
                'height_mm' => $authoring['canonical']['settings']['paper_height_mm'] ?? null,
                'ppi' => (int) config('omr.canonical.ppi', 300),
            ]);
        } finally {
            @unlink($pdfPath);
        }

        $instanceCode = $this->instances->newInstanceCode();
        $displayCode = $this->instances->displayCodeFor($instanceCode);
        $keyId = (string) config('omr.qr-keyring.current', 'dev-v1');
        $proofPages = [];
        $answerVector = $plan->expectedVector;
        $pageCount = count($rasterPages);
        $insetsPx = (array) ($artifactSet['insets_px'] ?? $artifactSet['definition']['insets_px'] ?? []);
        foreach ($rasterPages as $page) {
            $pageNo = (int) $page['page_no'];
            $image = @imagecreatefromstring((string) file_get_contents($page['path']));
            if ($image === false) {
                throw new RuntimeException('digital_proof_raster_invalid');
            }
            $black = imagecolorallocate($image, 0, 0, 0);
            $pageBlocks = $blocksByPage[$pageNo] ?? [];
            $expected = $plan->expectedVectorForPage($pageNo);
            $identity = [];
            foreach ($pageBlocks as $block) {
                if (! is_array($block)) {
                    continue;
                }
                $labels = [];
                foreach ((array) ($block['fieldLabels'] ?? []) as $declaration) {
                    $labels = [...$labels, ...NativeBundle::expandLabel((string) $declaration)];
                }
                $values = is_array($block['bubbleValues'] ?? null)
                    ? array_values(array_map('strval', $block['bubbleValues']))
                    : match ((string) ($block['fieldType'] ?? 'QTYPE_MCQ4')) {
                        'QTYPE_MCQ5' => ['A', 'B', 'C', 'D', 'E'],
                        'QTYPE_INT' => ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
                        default => ['A', 'B', 'C', 'D'],
                    };
                foreach ($labels as $labelIndex => $label) {
                    if (array_key_exists($label, $expected)) {
                        $selected = array_search($expected[$label], $values, true);
                        if ($selected === false) {
                            throw new RuntimeException('digital_proof_plan_option_missing: '.$label);
                        }
                        $this->shade($image, $block, $insetsPx, $values, $labelIndex, $selected, $black);
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
                    throw new RuntimeException('digital_proof_calibration_option_missing: '.$label);
                }
                $this->shade($image, $entry['block'], $insetsPx, $entry['values'], $entry['labelIndex'], $selected, $black);
            }
            $payload = $this->qr->sign($instanceCode, $pageNo, $pageCount, $keyId);
            $qrImage = @imagecreatefromstring($this->qrRenderer->png($payload, 240));
            if ($qrImage === false) {
                throw new RuntimeException('digital_proof_qr_invalid');
            }
            $qrSize = min(240, intdiv(imagesx($image), 6));
            [$qrX, $qrY] = $this->qrPlacement($artifactSet, imagesx($image), imagesy($image), $qrSize);
            imagecopyresampled($image, $qrImage, $qrX, $qrY, 0, 0, $qrSize, $qrSize, imagesx($qrImage), imagesy($qrImage));
            imagestring($image, 3, $qrX, min(imagesy($image) - 12, $qrY + $qrSize + 8), $displayCode, $black);
            imagesavealpha($image, false);
            ob_start();
            imagepng($image, null, 6);
            $proofBytes = (string) ob_get_clean();
            $proofPages[] = [
                'page_no' => $pageNo,
                'bytes' => $proofBytes,
                'sha256' => hash('sha256', $proofBytes),
                'width' => imagesx($image),
                'height' => imagesy($image),
            ];
        }
        $proofHash = hash('sha256', json_encode([
            'source_pdf_asset_id' => $pdfAsset->id,
            'source_pdf_sha256' => $pdfAsset->sha256,
            'plan_hash' => $plan->planHash(),
            'pages' => array_map(static fn (array $page): array => ['page_no' => $page['page_no'], 'sha256' => $page['sha256']], $proofPages),
            'answer_vector' => $answerVector,
            'instance_code' => $instanceCode,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        return [
            'pages' => $proofPages,
            'answer_vector' => $answerVector,
            'source_pdf_asset_id' => $pdfAsset->id,
            'proof_hash' => $proofHash,
            'instance_code' => $instanceCode,
            'display_code' => $displayCode,
        ];
    }

    /**
     * Fill one declared-option bubble (or one calibration digit bubble) at
     * marker-frame origin plus the paper inset ring.
     *
     * @param  array<string, mixed>  $block
     * @param  array<string, int>  $insetsPx
     * @param  list<string>  $values
     */
    private function shade(\GdImage $image, array $block, array $insetsPx, array $values, int $labelIndex, int $selected, int $black): void
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

    /** @return array{0:int,1:int} */
    private function qrPlacement(array $artifactSet, int $width, int $height, int $size): array
    {
        $markers = (array) (($artifactSet['template']['pages'][0]['markers'] ?? null) ?: []);
        $insets = (array) (($artifactSet['insets_px'] ?? $artifactSet['definition']['insets_px'] ?? null) ?: []);
        $markerHalf = intdiv((int) ($markers['size'] ?? 0), 2);
        $quiet = (int) ($markers['quiet_zone'] ?? 0);
        $left = (int) ($insets['left'] ?? 0) + $markerHalf + $quiet + 12;
        $right = $width - (int) ($insets['right'] ?? 0) - $markerHalf - $quiet - 12;
        $top = (int) ($insets['top'] ?? 0) + $markerHalf + $quiet + 12;
        $bottom = $height - (int) ($insets['bottom'] ?? 0) - $markerHalf - $quiet - 12;
        $x = max($left, min(intdiv($width - $size, 2), $right - $size));
        $y = max($top, min($top + 20, $bottom - $size));

        return [max(0, $x), max(0, $y)];
    }
}
