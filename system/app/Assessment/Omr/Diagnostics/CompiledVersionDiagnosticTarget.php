<?php

namespace App\Assessment\Omr\Diagnostics;

use App\Assessment\Omr\Canonical\OmrPageDimensionMath;
use App\Assessment\Omr\Canonical\OmrQrReservationResolver;
use App\Assessment\Omr\Canonical\OmrSourcePolicy;
use App\Assessment\Omr\NativeBundle;
use App\Assessment\Omr\OmrCapturePolicy;
use App\Assessment\Omr\OmrDetectionRequest;
use App\Assessment\Omr\OmrDiagnosticRequestBuilder;
use App\Assessment\Omr\OmrQrMode;
use App\Assessment\Omr\OmrRegistrationMode;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;

use function is_array;
use function is_string;

/**
 * Immutable compiled-version diagnostic target (OpenSpec 35.12).
 *
 * Diagnostically inferable immutable versions in either explicit registration
 * mode can enter the qualification workflow; actual readiness is derived later.
 */
final readonly class CompiledVersionDiagnosticTarget implements OmrDiagnosticTarget
{
    private NativeBundle $bundle;

    private OmrRegistrationMode $registrationPolicy;

    private OmrQrMode $qrPolicy;

    /** @var list<array{page_no:int}> */
    private array $orderedPages;

    public function __construct(
        private OmrAnswerSheetTemplateVersion $version,
    ) {
        $reviewManifest = is_array($version->review_manifest_json) ? $version->review_manifest_json : null;
        if ($reviewManifest === null) {
            throw new OmrDiagnosticTargetException(
                'compiled_version_no_review_manifest',
                "Template version {$version->id} has no review manifest to derive diagnostic geometry from.",
            );
        }
        $this->bundle = NativeBundle::loadForVersion($version);
        $this->assertVersionPolicy();
    }

    public function kind(): string
    {
        return self::KIND_COMPILED_VERSION;
    }

    public function sourceKind(): string
    {
        return OmrSourcePolicy::sourceKindForVersion($this->version) ?? OmrSourcePolicy::SYSTEM_DESIGNED;
    }

    public function targetId(): string
    {
        return (string) $this->version->id;
    }

    public function pageCount(): int
    {
        return count($this->orderedPages);
    }

    public function registrationMode(): OmrRegistrationMode
    {
        return $this->registrationPolicy;
    }

    public function qrMode(): OmrQrMode
    {
        return $this->qrPolicy;
    }

    /** @return list<array{page_no:int}> */
    public function compiledPages(): array
    {
        return $this->orderedPages;
    }

    /** @return array{width_mm:float,height_mm:float} */
    public function paperDimensionsMm(int $pageNo): array
    {
        $manifest = (array) $this->version->authoring_manifest;
        $paper = (array) ($manifest['paper'] ?? $this->bundle->manifest['paper'] ?? []);
        $template = $this->nativeTemplate($pageNo);
        $markers = (array) ($template['markers'] ?? []);
        $paper = (array) ($paper ?: ($markers['paper'] ?? []));
        $width = (float) ($paper['width_mm'] ?? 0);
        $height = (float) ($paper['height_mm'] ?? 0);
        if ($width <= 0 || $height <= 0) {
            $dimensions = (array) ($template['pageDimensions'] ?? []);
            $insets = (array) ($this->reviewManifest()['insets_px'] ?? []);
            $width = OmrPageDimensionMath::millimetresFromPixels(
                (int) ($dimensions[0] ?? 0) + (int) ($insets['left'] ?? 0) + (int) ($insets['right'] ?? 0),
            );
            $height = OmrPageDimensionMath::millimetresFromPixels(
                (int) ($dimensions[1] ?? 0) + (int) ($insets['top'] ?? 0) + (int) ($insets['bottom'] ?? 0),
            );
        }
        if ($width <= 0 || $height <= 0) {
            throw new OmrDiagnosticTargetException('compiled_version_paper_dimensions_missing', 'The immutable version paper dimensions are not available.');
        }

        return ['width_mm' => $width, 'height_mm' => $height];
    }

    /** @return list<array<string,mixed>> */
    public function visualComponents(int $pageNo): array
    {
        $manifest = (array) $this->version->authoring_manifest;
        $components = (array) ($manifest['visual_components'] ?? $this->bundle->template['visualComponents'] ?? []);

        return array_values(array_filter($components, function (mixed $component) use ($pageNo): bool {
            if (! is_array($component)) {
                return false;
            }
            if (! isset($component['page_no'])) {
                return $this->pageCount() === 1 && $pageNo === 1;
            }

            return (int) $component['page_no'] === $pageNo;
        }));
    }

    /** @return array<string, mixed> */
    public function nativeTemplate(int $pageNo): array
    {
        $template = $this->bundle->pageTemplate($pageNo);
        $page = $this->bundle->pages[$pageNo - 1] ?? [];
        $template['markers'] = is_array($page['markers'] ?? null) ? $page['markers'] : [];
        $template['visualComponents'] = $this->bundle->template['visualComponents'] ?? [];

        return $template;
    }

    /** @return array<string, mixed> */
    public function nativeConfig(): array
    {
        return $this->bundle->config;
    }

    /** @return array<string, string> */
    public function nativeAssets(): array
    {
        $name = $this->markerAssetName();

        return $name !== null ? [$name => $this->bundle->assetBytes($name)] : [];
    }

    /** @return array<string, mixed> */
    public function reviewManifest(): array
    {
        $manifest = (array) $this->version->review_manifest_json;
        $authoring = (array) $this->version->authoring_manifest;
        $firstMarkers = (array) (($this->bundle->pages[0]['markers'] ?? null) ?: []);
        $manifest['registration_mode'] = $this->registrationMode()->value;
        $manifest['qr_mode'] = $this->qrMode()->value;
        $manifest['compiled_pages'] = $this->compiledPages();
        $manifest['paper'] = $authoring['paper'] ?? ($manifest['paper'] ?? ($this->bundle->manifest['paper'] ?? ($firstMarkers['paper'] ?? null)));
        $manifest['insets_px'] = $authoring['insets_px'] ?? ($manifest['insets_px'] ?? ($firstMarkers['insets_px'] ?? []));
        $manifest['visual_components'] = $authoring['visual_components'] ?? ($this->bundle->template['visualComponents'] ?? []);
        if ($this->qrMode() === OmrQrMode::Signed) {
            $reservation = OmrQrReservationResolver::extractReservationBox([
                'qr_reservation' => $this->bundle->manifest['qr_reservation'] ?? null,
                'overlay' => (array) $this->version->print_overlay_json,
            ]);
            if ($reservation !== null) {
                $manifest['qr_reservation'] = [
                    'x' => (int) round($reservation['x']),
                    'y' => (int) round($reservation['y']),
                    'width' => (int) round($reservation['width']),
                    'height' => (int) round($reservation['height']),
                ];
            }
        } elseif (OmrQrReservationResolver::extractReservationBox([
            'qr_reservation' => $this->bundle->manifest['qr_reservation'] ?? null,
            'overlay' => (array) $this->version->print_overlay_json,
        ]) !== null) {
            throw new OmrDiagnosticTargetException('compiled_version_qr_mode_mismatch', 'A QR-disabled version cannot contain a production QR reservation.');
        }

        return $manifest;
    }

    public function markerAssetName(): ?string
    {
        return $this->registrationMode() === OmrRegistrationMode::FiducialAnchored
            ? $this->bundle->markerAssetName()
            : null;
    }

    public function markerAssetHash(): ?string
    {
        $name = $this->markerAssetName();

        return $name !== null ? ($this->bundle->fileHashes[$name] ?? null) : null;
    }

    public function markerProfileCode(): ?string
    {
        $profile = $this->markerProfile();

        return isset($profile['code']) ? (string) $profile['code'] : null;
    }

    public function markerProfileVersion(): ?int
    {
        $profile = $this->markerProfile();

        return isset($profile['version']) ? (int) $profile['version'] : null;
    }

    public function markerProfileQualified(): bool
    {
        if ($this->registrationMode() === OmrRegistrationMode::DocumentBound) {
            return false;
        }
        $profile = $this->markerProfile();

        return (string) ($profile['status'] ?? '') === 'qualified';
    }

    public function artifactHash(): string
    {
        return (string) ($this->version->authoring_manifest['artifact_set']['artifact_hash'] ?? '');
    }

    public function nativeBundleHash(): string
    {
        return (string) $this->version->native_bundle_hash;
    }

    public function manifestHash(): string
    {
        return (string) $this->version->review_manifest_hash;
    }

    public function configHash(): string
    {
        return (string) $this->version->config_hash;
    }

    public function mappingHash(): string
    {
        return (string) ($this->version->authoring_manifest['geometry_identity']
            ?? $this->version->authoring_manifest['mapping_hash']
            ?? $this->version->review_manifest_hash);
    }

    public function engineName(): string
    {
        return (string) $this->version->engine_name;
    }

    public function engineCommit(): ?string
    {
        $commit = $this->version->engine_commit;

        return is_string($commit) && $commit !== '' ? $commit : null;
    }

    public function patchIdentity(): string
    {
        return (string) $this->version->patch_identity;
    }

    public function qualificationCapable(): bool
    {
        return true;
    }

    public function buildRequest(OmrDiagnosticRequestBuilder $builder, string $imageBytes, string $imageMime, int $pageNo): OmrDetectionRequest
    {
        return $builder->build($this->version, $imageBytes, $imageMime, $pageNo);

    }

    /** @return array<string, mixed> */
    private function markerProfile(): array
    {
        $nativeJson = (array) $this->version->native_bundle_json;
        $profile = (array) (($nativeJson['manifest']['marker_profile'] ?? null) ?: []);

        return $profile;
    }

    private function assertVersionPolicy(): void
    {
        $registration = $this->version->registration_mode;
        $qr = $this->version->qr_mode;
        if (! $registration instanceof OmrRegistrationMode || ! $qr instanceof OmrQrMode) {
            throw new OmrDiagnosticTargetException('compiled_version_policy_missing', 'The immutable version has no explicit registration and QR modes.');
        }

        $manifest = $this->bundle->manifest;
        $hasManifestPolicy = array_key_exists('registration_mode', $manifest)
            || array_key_exists('qr_mode', $manifest)
            || array_key_exists('compiled_pages', $manifest);
        if ($hasManifestPolicy) {
            if (($manifest['registration_mode'] ?? null) !== $registration->value
                || ($manifest['qr_mode'] ?? null) !== $qr->value
                || ! is_array($manifest['compiled_pages'] ?? null)
                || ! array_is_list($manifest['compiled_pages'])) {
                throw new OmrDiagnosticTargetException('compiled_version_policy_mismatch', 'Version columns and native manifest paper policy must agree explicitly.');
            }
            $pages = $manifest['compiled_pages'];
        } else {
            if ($registration !== OmrRegistrationMode::FiducialAnchored || $qr !== OmrQrMode::Signed) {
                throw new OmrDiagnosticTargetException('compiled_version_policy_missing', 'Manifest policy may be inferred only for historical anchored/signed versions whose columns agree.');
            }
            $pages = array_map(
                static fn (array $page): array => ['page_no' => (int) ($page['page_no'] ?? 0)],
                array_values($this->bundle->pages),
            );
        }
        foreach ($pages as $index => $page) {
            if (! is_array($page) || (int) ($page['page_no'] ?? 0) !== $index + 1) {
                throw new OmrDiagnosticTargetException('compiled_version_pages_mismatch', 'The native page list must be contiguous and ordered from Page 1.');
            }
        }
        $nativePages = array_map(
            static fn (array $page): array => ['page_no' => (int) ($page['page_no'] ?? 0)],
            array_values($this->bundle->pages),
        );
        if ($pages === []
            || $pages !== $nativePages
            || count($pages) !== (int) $this->version->page_count
            || count($pages) !== $this->bundle->pageCount()) {
            throw new OmrDiagnosticTargetException('compiled_version_pages_mismatch', 'Version page_count, native page count, and compiled page list must be identical.');
        }
        $this->registrationPolicy = $registration;
        $this->qrPolicy = $qr;
        $this->orderedPages = $pages;

        $marker = OmrCapturePolicy::markerMetadata($this->bundle->template);
        if ($registration === OmrRegistrationMode::FiducialAnchored && $marker === null) {
            throw new OmrDiagnosticTargetException('compiled_version_marker_contract_missing', 'Fiducial-anchored versions require the complete native marker contract.');
        }
        if ($registration === OmrRegistrationMode::DocumentBound && ($marker !== null || $this->bundle->markerAssetName() !== null)) {
            throw new OmrDiagnosticTargetException('compiled_version_policy_mismatch', 'Document-bound versions must be marker-free.');
        }
        foreach ($this->bundle->pages as $page) {
            if ($registration === OmrRegistrationMode::DocumentBound
                && (! empty($page['markers']) || in_array('CropOnMarkers', (array) ($page['preProcessors'] ?? []), true))) {
                throw new OmrDiagnosticTargetException('compiled_version_policy_mismatch', 'Document-bound native pages cannot contain markers or marker cropping.');
            }
        }
        if ($qr === OmrQrMode::Disabled) {
            $overlay = (array) $this->version->print_overlay_json;
            $components = (array) ($this->bundle->template['visualComponents'] ?? []);
            $hasQrComponent = array_filter($components, static fn (mixed $component): bool => is_array($component) && strtolower((string) ($component['type'] ?? '')) === 'qr') !== [];
            if ($hasQrComponent || OmrQrReservationResolver::extractReservationBox([
                'qr_reservation' => $manifest['qr_reservation'] ?? null,
                'overlay' => $overlay,
            ]) !== null) {
                throw new OmrDiagnosticTargetException('compiled_version_qr_mode_mismatch', 'A QR-disabled version cannot contain production QR geometry.');
            }
        }
    }
}
