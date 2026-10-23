<?php

namespace App\Assessment\Omr\Diagnostics;

use App\Assessment\Omr\OmrCapturePolicy;
use App\Assessment\Omr\OmrDetectionRequest;
use App\Assessment\Omr\OmrDiagnosticRequestBuilder;
use App\Assessment\Omr\OmrQrMode;
use App\Assessment\Omr\OmrRegistrationMode;

use function hash;
use function is_array;
use function is_string;

/**
 * Read-only adapter for grading's already-pinned request snapshot.
 *
 * RunOmrSheetAnalysis remains the authority for batch, applicant, answer-key,
 * and score state. This adapter only lets the shared capture pipeline reuse
 * that immutable request geometry while replacing the image for each quarter
 * turn.
 */
final readonly class OmrPinnedRequestDiagnosticTarget implements OmrDiagnosticTarget
{
    public function __construct(
        private OmrDetectionRequest $request,
        private string $id,
        private int $pageCount = 1,
    ) {}

    public function kind(): string
    {
        return self::KIND_COMPILED_VERSION;
    }

    public function targetId(): string
    {
        return $this->id;
    }

    public function pageCount(): int
    {
        return max(1, $this->pageCount);
    }

    public function registrationMode(): OmrRegistrationMode
    {
        $value = $this->request->nativeBundleMetadata['registration_mode']
            ?? $this->request->reviewManifest['registration_mode']
            ?? null;
        $mode = is_string($value) ? OmrRegistrationMode::tryFrom($value) : null;
        if ($mode === null) {
            throw new OmrDiagnosticTargetException('pinned_registration_mode_missing', 'The pinned request has no explicit registration policy.');
        }

        return $mode;
    }

    public function qrMode(): OmrQrMode
    {
        $value = $this->request->nativeBundleMetadata['qr_mode']
            ?? $this->request->reviewManifest['qr_mode']
            ?? null;
        $mode = is_string($value) ? OmrQrMode::tryFrom($value) : null;
        if ($mode === null) {
            throw new OmrDiagnosticTargetException('pinned_qr_mode_missing', 'The pinned request has no explicit QR policy.');
        }

        return $mode;
    }

    /** @return list<array{page_no:int}> */
    public function compiledPages(): array
    {
        $pages = $this->request->nativeBundleMetadata['compiled_pages']
            ?? $this->request->reviewManifest['compiled_pages']
            ?? null;
        if (! is_array($pages) || ! array_is_list($pages) || count($pages) !== $this->pageCount()) {
            throw new OmrDiagnosticTargetException('pinned_compiled_pages_missing', 'The pinned request has no explicit ordered compiled page list.');
        }
        foreach ($pages as $index => $page) {
            if (! is_array($page) || (int) ($page['page_no'] ?? 0) !== $index + 1) {
                throw new OmrDiagnosticTargetException('pinned_compiled_pages_mismatch', 'The pinned page list must be contiguous and ordered.');
            }
        }

        return $pages;
    }

    /** @return array{width_mm:float,height_mm:float} */
    public function paperDimensionsMm(int $pageNo): array
    {
        $paper = (array) ($this->request->reviewManifest['paper'] ?? []);
        $width = (float) ($paper['width_mm'] ?? 0);
        $height = (float) ($paper['height_mm'] ?? 0);
        if ($width <= 0 || $height <= 0) {
            throw new OmrDiagnosticTargetException('pinned_paper_dimensions_missing', 'The pinned request has no physical paper dimensions.');
        }

        return ['width_mm' => $width, 'height_mm' => $height];
    }

    /** @return list<array<string,mixed>> */
    public function visualComponents(int $pageNo): array
    {
        $components = (array) ($this->request->reviewManifest['visual_components'] ?? $this->request->nativeTemplate['visualComponents'] ?? []);

        return array_values(array_filter($components, static fn (mixed $component): bool => is_array($component)
            && (! isset($component['page_no']) || (int) $component['page_no'] === $pageNo)));
    }

    /** @return array<string, mixed> */
    public function nativeTemplate(int $pageNo): array
    {
        return $this->request->nativeTemplate;
    }

    /** @return array<string, mixed> */
    public function nativeConfig(): array
    {
        return $this->request->nativeConfig;
    }

    /** @return array<string, string> */
    public function nativeAssets(): array
    {
        return $this->request->nativeAssets;
    }

    /** @return array<string, mixed> */
    public function reviewManifest(): array
    {
        return $this->request->reviewManifest;
    }

    public function markerAssetName(): ?string
    {
        $marker = OmrCapturePolicy::markerMetadata($this->request->nativeTemplate);

        return is_array($marker) && is_string($marker['asset_name'] ?? null)
            ? $marker['asset_name']
            : null;
    }

    public function markerAssetHash(): ?string
    {
        $name = $this->markerAssetName();

        return $name !== null && isset($this->request->nativeAssets[$name])
            ? hash('sha256', $this->request->nativeAssets[$name])
            : null;
    }

    public function markerProfileCode(): ?string
    {
        return null;
    }

    public function markerProfileVersion(): ?int
    {
        return null;
    }

    public function markerProfileQualified(): bool
    {
        return true;
    }

    public function artifactHash(): string
    {
        return $this->request->nativeBundleHash;
    }

    public function nativeBundleHash(): string
    {
        return $this->request->nativeBundleHash;
    }

    public function manifestHash(): string
    {
        return $this->request->manifestHash;
    }

    public function configHash(): string
    {
        return $this->request->configHash;
    }

    public function mappingHash(): string
    {
        $mapping = $this->request->nativeBundleMetadata['mapping_hash'] ?? null;

        return is_string($mapping) && $mapping !== '' ? $mapping : $this->request->manifestHash;
    }

    public function engineName(): string
    {
        return $this->request->engineName;
    }

    public function engineCommit(): ?string
    {
        return $this->request->engineCommit;
    }

    public function patchIdentity(): string
    {
        return $this->request->patchIdentity;
    }

    public function qualificationCapable(): bool
    {
        return true;
    }

    public function buildRequest(OmrDiagnosticRequestBuilder $builder, string $imageBytes, string $imageMime, int $pageNo): OmrDetectionRequest
    {
        return $builder->rebindImage($this->request, $imageBytes, $imageMime, $pageNo);
    }
}
