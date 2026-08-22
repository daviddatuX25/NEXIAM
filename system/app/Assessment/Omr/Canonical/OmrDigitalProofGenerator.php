<?php

namespace App\Assessment\Omr\Canonical;

use App\Models\Assessment\OmrTemplateAuthoringDraft;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

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
     * @return array{pages:list<array{page_no:int,bytes:string,sha256:string,width:int,height:int}>,answer_vector:array<string,string>,source_pdf_asset_id:int,proof_hash:string,instance_code:string,display_code:string}
     */
    public function generate(OmrTemplateAuthoringDraft $draft): array
    {
        $authoring = (array) $draft->authoring_json;
        $mapping = (array) ($authoring['mapping'] ?? []);
        $blocks = (array) ($mapping['field_blocks'] ?? []);
        if ($blocks === []) {
            throw new RuntimeException('digital_proof_mapping_missing');
        }
        $pdfAssetId = (int) ($authoring['canonical']['pdf_asset_id'] ?? 0);
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
        $answerVector = [];
        $pageCount = count($rasterPages);
        foreach ($rasterPages as $page) {
            $pageNo = (int) $page['page_no'];
            $image = @imagecreatefromstring((string) file_get_contents($page['path']));
            if ($image === false) {
                throw new RuntimeException('digital_proof_raster_invalid');
            }
            $black = imagecolorallocate($image, 0, 0, 0);
            $pageBlocks = array_filter($blocks, static fn (mixed $block): bool => ! is_array($block) || ! isset($block['page_no']) || (int) $block['page_no'] === $pageNo);
            $blockIndex = 0;
            foreach ($pageBlocks as $block) {
                if (! is_array($block)) {
                    continue;
                }
                $origin = (array) ($block['origin'] ?? [0, 0]);
                $bubble = (array) ($block['bubbleDimensions'] ?? [20, 20]);
                $labels = (array) ($block['fieldLabels'] ?? []);
                $bubbleGap = (int) ($block['bubblesGap'] ?? 0);
                $labelGap = (int) ($block['labelsGap'] ?? 0);
                $optionCount = match ((string) ($block['fieldType'] ?? 'QTYPE_MCQ4')) {
                    'QTYPE_MCQ5' => 5,
                    'QTYPE_INT' => 10,
                    default => 4,
                };
                foreach ($labels as $row => $label) {
                    $selected = ((int) $row + $blockIndex) % $optionCount;
                    $answerVector[(string) $label] = (string) $selected;
                    $cx = (int) $origin[0] + $selected * $bubbleGap + (int) $bubble[0] / 2;
                    $cy = (int) $origin[1] + (int) $row * $labelGap + (int) $bubble[1] / 2;
                    imagefilledellipse($image, $cx, $cy, (int) $bubble[0], (int) $bubble[1], $black);
                    imageellipse($image, $cx, $cy, (int) $bubble[0], (int) $bubble[1], $black);
                }
                $blockIndex++;
            }

            $payload = $this->qr->sign($instanceCode, $pageNo, $pageCount, $keyId);
            $qrImage = @imagecreatefromstring($this->qrRenderer->png($payload, 240));
            if ($qrImage === false) {
                throw new RuntimeException('digital_proof_qr_invalid');
            }
            $qrSize = min(240, intdiv(imagesx($image), 6));
            imagecopyresampled($image, $qrImage, imagesx($image) - $qrSize - 40, 40, 0, 0, $qrSize, $qrSize, imagesx($qrImage), imagesy($qrImage));
            imagestring($image, 3, imagesx($image) - $qrSize - 40, 48 + $qrSize, $displayCode, $black);
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
}
