<?php

namespace App\Assessment\Omr\Diagnostics;

use App\Assessment\Omr\NativeBundle;
use App\Assessment\Omr\OmrCapturePolicy;
use App\Assessment\Omr\OmrDetectionRequest;
use App\Assessment\Omr\OmrDiagnosticRequestBuilder;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;

use function is_array;
use function is_string;

/**
 * Qualification-capable target over a compiled immutable version (OpenSpec 35.12).
 *
 * Reads ONLY pinned version-owned assets: the version's `native_bundle_json`
 * (template/config/manifest/file hashes), its review manifest, and the
 * version-pinned native bundle directory. Never reads the draft, mapping
 * editor state, or any mutable authoring row; `qualificationCapable()` is
 * always true for the compiled kind (AD-2).
 */
final readonly class CompiledVersionDiagnosticTarget implements OmrDiagnosticTarget
{
    private NativeBundle $bundle;

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
        $nativeJson = is_array($version->native_bundle_json) ? $version->native_bundle_json : [];
        $marker = is_array($nativeJson['template'] ?? null) ? OmrCapturePolicy::markerMetadata($nativeJson['template']) : null;
        if ($marker === null) {
            throw new OmrDiagnosticTargetException(
                'compiled_version_not_marker_bearing',
                "Template version {$version->id} is not marker-bearing; scan (CropPage) profiles are not supported by diagnostics.",
            );
        }
        $this->bundle = NativeBundle::load((string) $version->template->code, (int) $version->version);
    }

    public function kind(): string
    {
        return self::KIND_COMPILED_VERSION;
    }

    public function targetId(): string
    {
        return (string) $this->version->id;
    }

    public function pageCount(): int
    {
        return $this->bundle->pageCount();
    }

    /** @return array<string, mixed> */
    public function nativeTemplate(int $pageNo): array
    {
        return $this->bundle->pageTemplate($pageNo);
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
        return (array) $this->version->review_manifest_json;
    }

    public function markerAssetName(): ?string
    {
        return $this->bundle->markerAssetName();
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
        return (string) ($this->version->authoring_manifest['mapping_hash'] ?? $this->version->review_manifest_hash);
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
}
