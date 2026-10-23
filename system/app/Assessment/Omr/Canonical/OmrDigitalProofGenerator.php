<?php

namespace App\Assessment\Omr\Canonical;

use App\Assessment\Omr\Diagnostics\OmrManifestBubbleShader;
use App\Assessment\Omr\Diagnostics\OmrProofPlan;
use App\Assessment\Omr\OmrRegistrationMode;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class OmrDigitalProofGenerator
{
    public function __construct(
        private readonly OmrPdfPageRasterizer $rasterizer,
        private readonly OmrCalibrationQrPayload $calibrationQr,
        private readonly OmrQrCodeRenderer $qrRenderer,
        private readonly OmrManifestBubbleShader $shader = new OmrManifestBubbleShader,
    ) {}

    /**
     * Machine-fill every compiled page and add only purpose-bound diagnostic QR.
     *
     * @return array{pages:list<array{page_no:int,bytes:string,sha256:string,width:int,height:int,diagnostic_qr_payload_hash:string,paper_bounds?:array{x:int,y:int,width:int,height:int}}>,answer_vector:array<string,string>,source_pdf_asset_id:int,proof_hash:string,registration_mode:string,qr_mode:string,compiled_pages:list<array{page_no:int}>,diagnostic_qr_payload_hashes:list<string>}
     */
    public function generate(OmrTemplateAuthoringDraft $draft, OmrProofPlan $plan): array
    {
        $authoring = (array) $draft->authoring_json;
        $artifactSet = (array) ($authoring['canonical']['artifact_set'] ?? []);
        $reviewManifest = is_array($artifactSet['review_manifest'] ?? null)
            ? $artifactSet['review_manifest']
            : (is_array($authoring['canonical']['review_manifest'] ?? null) ? $authoring['canonical']['review_manifest'] : []);
        if ($reviewManifest === [] || ! is_array($reviewManifest['fields'] ?? null)) {
            throw new RuntimeException('digital_proof_mapping_missing');
        }
        if (($reviewManifest['registration_mode'] ?? null) !== $plan->registrationMode->value
            || ($reviewManifest['qr_mode'] ?? null) !== $plan->qrMode->value
            || ($reviewManifest['compiled_pages'] ?? null) !== $plan->compiledPages) {
            throw new RuntimeException('digital_proof_policy_mismatch');
        }

        $geometryIndex = $this->shader->index($reviewManifest);
        $isUploaded = OmrSourcePolicy::sourceKindForDraft($draft) === OmrSourcePolicy::UPLOADED_ORIGINAL
            || $plan->sourceKind === OmrSourcePolicy::UPLOADED_ORIGINAL;
        $expectedPageNos = array_map(static fn (array $page): int => (int) $page['page_no'], $plan->compiledPages);
        $proofPages = [];
        $qrPayloadHashes = [];
        $sourceRasterHashes = [];
        $insetsPx = (array) ($artifactSet['insets_px'] ?? $artifactSet['definition']['insets_px'] ?? []);

        if ($isUploaded) {
            $rasterAssets = array_values((array) ($artifactSet['raster_assets'] ?? $authoring['canonical']['raster_assets'] ?? []));
            if (count($rasterAssets) !== count($expectedPageNos)) {
                throw new RuntimeException('digital_proof_raster_missing');
            }
            foreach ($rasterAssets as $index => $rasterRef) {
                $pageNo = (int) ($rasterRef['page_no'] ?? 0);
                $assetId = (int) ($rasterRef['asset_id'] ?? 0);
                if ($pageNo !== $expectedPageNos[$index] || $assetId <= 0) {
                    throw new RuntimeException('digital_proof_page_set_mismatch');
                }
                $asset = $draft->assets()->whereKey($assetId)->first();
                if ($asset === null) {
                    throw new RuntimeException('digital_proof_raster_missing');
                }
                $rasterBytes = Storage::disk($asset->disk)->get($asset->path);
                if (! is_string($rasterBytes) || $rasterBytes === '' || hash('sha256', $rasterBytes) !== (string) $asset->sha256) {
                    throw new RuntimeException('asset_integrity_failure');
                }
                $sourceRasterHashes[] = ['page_no' => $pageNo, 'sha256' => (string) $asset->sha256];
                $image = @imagecreatefromstring($rasterBytes);
                if ($image === false) {
                    throw new RuntimeException('digital_proof_raster_invalid');
                }
                $black = imagecolorallocate($image, 0, 0, 0);
                $this->shader->shadePage(
                    $image,
                    $geometryIndex,
                    $pageNo,
                    $plan->expectedVectorForPage($pageNo),
                    $plan->calibrationDigits,
                    $insetsPx,
                    $black,
                );
                $paperBounds = null;
                if ($plan->registrationMode === OmrRegistrationMode::DocumentBound) {
                    $framed = self::frameDocumentBoundPage($image);
                    $image = $framed['image'];
                    $paperBounds = $framed['paper_bounds'];
                }
                $proofWidth = imagesx($image);
                $proofHeight = imagesy($image);
                imagesavealpha($image, false);
                $proofPath = tempnam(sys_get_temp_dir(), 'nexiam-digital-proof-page-');
                if ($proofPath === false) {
                    throw new RuntimeException('digital_proof_page_output_create_failed');
                }
                try {
                    if (! imagepng($image, $proofPath, 6)) {
                        throw new RuntimeException('digital_proof_page_output_failed');
                    }
                    $proofBytes = file_get_contents($proofPath);
                    if (! is_string($proofBytes) || $proofBytes === '') {
                        throw new RuntimeException('digital_proof_page_output_invalid');
                    }
                } finally {
                    @unlink($proofPath);
                    imagedestroy($image);
                }
                $proofPages[] = [
                    'page_no' => $pageNo,
                    'bytes' => $proofBytes,
                    'sha256' => hash('sha256', $proofBytes),
                    'width' => $proofWidth,
                    'height' => $proofHeight,
                    'diagnostic_qr_payload_hash' => '',
                    ...($paperBounds !== null ? ['paper_bounds' => $paperBounds] : []),
                ];
            }

            $proofHash = hash('sha256', json_encode([
                'source_kind' => OmrSourcePolicy::UPLOADED_ORIGINAL,
                'source_raster_hashes' => $sourceRasterHashes,
                'plan_hash' => $plan->planHash(),
                'registration_mode' => $plan->registrationMode->value,
                'qr_mode' => $plan->qrMode->value,
                'compiled_pages' => $plan->compiledPages,
                'pages' => array_map(static function (array $page): array {
                    $proofPage = ['page_no' => $page['page_no'], 'sha256' => $page['sha256']];
                    if (is_array($page['paper_bounds'] ?? null)) {
                        $proofPage['paper_bounds'] = $page['paper_bounds'];
                    }

                    return $proofPage;
                }, $proofPages),
                'answer_vector' => $plan->expectedVector,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

            return [
                'pages' => $proofPages,
                'answer_vector' => $plan->expectedVector,
                'source_pdf_asset_id' => 0,
                'source_raster_hashes' => $sourceRasterHashes,
                'proof_hash' => $proofHash,
                'registration_mode' => $plan->registrationMode->value,
                'qr_mode' => $plan->qrMode->value,
                'compiled_pages' => $plan->compiledPages,
                'diagnostic_qr_payload_hashes' => [],
            ];
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
        unset($pdfBytes);
        try {
            $rasterPages = $this->rasterizer->rasterize($pdfPath, [
                'width_mm' => $authoring['canonical']['settings']['paper_width_mm'] ?? null,
                'height_mm' => $authoring['canonical']['settings']['paper_height_mm'] ?? null,
                'ppi' => (int) config('omr.canonical.ppi', 300),
            ]);
        } finally {
            @unlink($pdfPath);
        }

        $rasterPageNos = array_map(static fn (array $page): int => (int) ($page['page_no'] ?? 0), $rasterPages);
        if ($rasterPageNos !== $expectedPageNos) {
            throw new RuntimeException('digital_proof_page_set_mismatch');
        }

        $pageCount = count($rasterPages);
        foreach ($rasterPages as $page) {
            $pageNo = (int) $page['page_no'];
            $reservation = $plan->diagnosticQrReservation[$pageNo - 1] ?? null;
            if (! is_array($reservation) || (int) ($reservation['page_no'] ?? 0) !== $pageNo) {
                throw new RuntimeException('digital_proof_qr_reservation_missing');
            }
            $image = @imagecreatefrompng((string) $page['path']);
            if ($image === false) {
                throw new RuntimeException('digital_proof_raster_invalid');
            }
            $black = imagecolorallocate($image, 0, 0, 0);
            $this->shader->shadePage(
                $image,
                $geometryIndex,
                $pageNo,
                $plan->expectedVectorForPage($pageNo),
                $plan->calibrationDigits,
                $insetsPx,
                $black,
            );

            $payload = $this->calibrationQr->sign($plan->challengeCode, $pageNo, $pageCount);
            $qrPayloadHash = $this->calibrationQr->payloadHash($payload);
            $scaleX = imagesx($image) / (float) $reservation['paper_width_mm'];
            $scaleY = imagesy($image) / (float) $reservation['paper_height_mm'];
            $qrSize = min(
                (int) round((float) $reservation['qr_size_mm'] * $scaleX),
                (int) round((float) $reservation['qr_size_mm'] * $scaleY),
            );
            $qrX = (int) round((float) $reservation['qr_x_mm'] * $scaleX);
            $qrY = (int) round((float) $reservation['qr_y_mm'] * $scaleY);
            $displayCodeX = (int) round((float) $reservation['display_code_x_mm'] * $scaleX);
            $displayCodeY = (int) round((float) $reservation['display_code_y_mm'] * $scaleY);
            if ($qrSize < 64 || $qrX < 0 || $qrY < 0 || $qrX + $qrSize > imagesx($image)
                || $qrY + $qrSize > imagesy($image) || $displayCodeX < 0 || $displayCodeY < 0
                || $displayCodeX >= imagesx($image) || $displayCodeY >= imagesy($image)) {
                throw new RuntimeException('digital_proof_qr_reservation_out_of_bounds');
            }
            $qrImage = @imagecreatefromstring($this->qrRenderer->png($payload, max(320, $qrSize)));
            if ($qrImage === false) {
                throw new RuntimeException('digital_proof_qr_invalid');
            }
            imagecopyresampled($image, $qrImage, $qrX, $qrY, 0, 0, $qrSize, $qrSize, imagesx($qrImage), imagesy($qrImage));
            imagestring($image, 3, $displayCodeX, $displayCodeY, $plan->challengeCode, $black);
            $paperBounds = null;
            if ($plan->registrationMode === OmrRegistrationMode::DocumentBound) {
                $framed = self::frameDocumentBoundPage($image);
                $image = $framed['image'];
                $paperBounds = $framed['paper_bounds'];
            }
            $proofWidth = imagesx($image);
            $proofHeight = imagesy($image);
            imagesavealpha($image, false);
            $proofPath = tempnam(sys_get_temp_dir(), 'nexiam-digital-proof-page-');
            if ($proofPath === false) {
                throw new RuntimeException('digital_proof_page_output_create_failed');
            }
            try {
                if (! imagepng($image, $proofPath, 6)) {
                    throw new RuntimeException('digital_proof_page_output_failed');
                }
                $proofBytes = file_get_contents($proofPath);
                if (! is_string($proofBytes) || $proofBytes === '') {
                    throw new RuntimeException('digital_proof_page_output_invalid');
                }
            } finally {
                @unlink($proofPath);
                imagedestroy($qrImage);
                imagedestroy($image);
            }
            $proofPages[] = [
                'page_no' => $pageNo,
                'bytes' => $proofBytes,
                'sha256' => hash('sha256', $proofBytes),
                'width' => $proofWidth,
                'height' => $proofHeight,
                'diagnostic_qr_payload_hash' => $qrPayloadHash,
            ];
            if ($paperBounds !== null) {
                $proofPages[array_key_last($proofPages)]['paper_bounds'] = $paperBounds;
            }
            $qrPayloadHashes[] = $qrPayloadHash;
        }

        $proofHash = hash('sha256', json_encode([
            'source_pdf_asset_id' => $pdfAsset->id,
            'source_pdf_sha256' => $pdfAsset->sha256,
            'plan_hash' => $plan->planHash(),
            'registration_mode' => $plan->registrationMode->value,
            'qr_mode' => $plan->qrMode->value,
            'compiled_pages' => $plan->compiledPages,
            'diagnostic_qr_reservation' => $plan->diagnosticQrReservation,
            'diagnostic_qr_payload_hashes' => $qrPayloadHashes,
            'pages' => array_map(static function (array $page): array {
                $proofPage = ['page_no' => $page['page_no'], 'sha256' => $page['sha256']];
                if (is_array($page['paper_bounds'] ?? null)) {
                    $proofPage['paper_bounds'] = $page['paper_bounds'];
                }

                return $proofPage;
            }, $proofPages),
            'answer_vector' => $plan->expectedVector,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        return [
            'pages' => $proofPages,
            'answer_vector' => $plan->expectedVector,
            'source_pdf_asset_id' => $pdfAsset->id,
            'proof_hash' => $proofHash,
            'registration_mode' => $plan->registrationMode->value,
            'qr_mode' => $plan->qrMode->value,
            'compiled_pages' => $plan->compiledPages,
            'diagnostic_qr_payload_hashes' => $qrPayloadHashes,
        ];
    }

    /** @return array{image:\GdImage,paper_bounds:array{x:int,y:int,width:int,height:int}} */
    public static function frameDocumentBoundPage(\GdImage $paper): array
    {
        $paperWidth = imagesx($paper);
        $paperHeight = imagesy($paper);
        $margin = max(32, min(192, (int) round(min($paperWidth, $paperHeight) * .05)));
        $canvas = imagecreatetruecolor($paperWidth + 2 * $margin, $paperHeight + 2 * $margin);
        if ($canvas === false) {
            throw new RuntimeException('digital_proof_canvas_create_failed');
        }
        $background = imagecolorallocate($canvas, 128, 128, 128);
        imagefilledrectangle($canvas, 0, 0, imagesx($canvas), imagesy($canvas), $background);
        imagecopy($canvas, $paper, $margin, $margin, 0, 0, $paperWidth, $paperHeight);
        imagedestroy($paper);

        return [
            'image' => $canvas,
            'paper_bounds' => [
                'x' => $margin,
                'y' => $margin,
                'width' => $paperWidth,
                'height' => $paperHeight,
            ],
        ];
    }
}
