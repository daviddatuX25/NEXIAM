<?php

namespace App\Assessment\Omr\Canonical;

use App\Assessment\Omr\OmrQrMode;
use App\Assessment\Omr\OmrRegistrationMode;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrTemplateAsset;
use RuntimeException;

/**
 * Immutable print inputs recovered from and checked against a compiled version.
 */
final readonly class OmrBlankPrintContract
{
    /**
     * @param  list<array{page_no: int}>  $compiledPages
     */
    private function __construct(
        public int $templateVersionId,
        public OmrRegistrationMode $registrationMode,
        public OmrQrMode $qrMode,
        public array $compiledPages,
        public string $nativeBundleHash,
        public string $artifactHash,
        public ?string $printPdfHash,
        public string $sourceKind,
        public bool $hasExplicitManifestPolicy,
    ) {}

    public static function fromVersion(OmrAnswerSheetTemplateVersion $version): self
    {
        $registrationMode = $version->registration_mode;
        $qrMode = $version->qr_mode;
        $nativeBundleHash = $version->native_bundle_hash;

        if (! $registrationMode instanceof OmrRegistrationMode || ! $qrMode instanceof OmrQrMode) {
            throw new RuntimeException('print_policy_invalid');
        }
        if (! is_string($nativeBundleHash) || ! self::isSha256($nativeBundleHash)) {
            throw new RuntimeException('print_artifact_hash_invalid');
        }

        $authoringManifest = is_array($version->authoring_manifest) ? $version->authoring_manifest : [];
        $sourceKind = (string) ($authoringManifest['source_kind'] ?? '');
        $uploaded = $sourceKind === 'uploaded_original';
        $nativeBundle = is_array($version->native_bundle_json) ? $version->native_bundle_json : [];
        $nativeManifest = is_array($nativeBundle['manifest'] ?? null) ? $nativeBundle['manifest'] : [];
        $hasExplicitManifestPolicy = self::hasPolicyFields($authoringManifest)
            || self::hasPolicyFields($nativeManifest);

        if ($hasExplicitManifestPolicy) {
            self::assertManifestPolicy($authoringManifest, $registrationMode, $qrMode);
            self::assertManifestPolicy($nativeManifest, $registrationMode, $qrMode);

            $compiledPages = self::pageList($authoringManifest['compiled_pages'] ?? null);
            if ($compiledPages !== self::pageList($nativeManifest['compiled_pages'] ?? null)) {
                throw new RuntimeException('print_page_manifest_mismatch');
            }
        } else {
            if ($registrationMode !== OmrRegistrationMode::FiducialAnchored || $qrMode !== OmrQrMode::Signed) {
                throw new RuntimeException('print_policy_manifest_required');
            }

            $compiledPages = self::nativeTemplatePages($nativeBundle['template']['pages'] ?? null);
        }

        if (count($compiledPages) < 1
            || count($compiledPages) > 4
            || count($compiledPages) !== $version->pageCount()) {
            throw new RuntimeException('print_page_count_mismatch');
        }

        $templatePages = self::nativeTemplatePages($nativeBundle['template']['pages'] ?? null);
        if ($templatePages !== $compiledPages) {
            throw new RuntimeException('print_page_manifest_mismatch');
        }

        $artifactSet = is_array($authoringManifest['artifact_set'] ?? null)
            ? $authoringManifest['artifact_set']
            : [];
        $artifactHash = $nativeBundleHash;
        $printPdfHash = null;

        if (array_key_exists('artifact_hash', $artifactSet)) {
            if (! is_string($artifactSet['artifact_hash']) || ! self::isSha256($artifactSet['artifact_hash'])) {
                throw new RuntimeException('print_artifact_hash_invalid');
            }
            $artifactHash = $artifactSet['artifact_hash'];
        } elseif ($hasExplicitManifestPolicy || $qrMode === OmrQrMode::Disabled) {
            throw new RuntimeException('print_artifact_hash_invalid');
        }

        if (array_key_exists('pdf_hash', $artifactSet)) {
            if ($uploaded && $artifactSet['pdf_hash'] === null) {
                $printPdfHash = null;
            } elseif (! is_string($artifactSet['pdf_hash']) || ! self::isSha256($artifactSet['pdf_hash'])) {
                throw new RuntimeException('print_artifact_hash_invalid');
            } else {
                $printPdfHash = $artifactSet['pdf_hash'];
            }
        } elseif (! $uploaded && ($hasExplicitManifestPolicy || $qrMode === OmrQrMode::Disabled)) {
            throw new RuntimeException('print_artifact_hash_invalid');
        }

        return new self(
            (int) $version->id,
            $registrationMode,
            $qrMode,
            $compiledPages,
            $nativeBundleHash,
            $artifactHash,
            $printPdfHash,
            $sourceKind,
            $hasExplicitManifestPolicy,
        );
    }

    public function assertPrintAsset(OmrTemplateAsset $asset): void
    {
        if ($this->sourceKind === 'uploaded_original') {
            throw new RuntimeException('print_uploaded_source_forbidden');
        }
        if (! is_string($asset->sha256) || ! self::isSha256($asset->sha256)) {
            throw new RuntimeException('print_artifact_hash_invalid');
        }
        if ($this->printPdfHash !== null && ! hash_equals($this->printPdfHash, $asset->sha256)) {
            throw new RuntimeException('print_artifact_hash_mismatch');
        }
        if ($this->qrMode === OmrQrMode::Disabled && $this->printPdfHash === null) {
            throw new RuntimeException('print_artifact_hash_invalid');
        }
    }

    /** @return array<string, mixed> */
    public function pinnedInputs(string $printPdfSha256): array
    {
        return [
            'template_version_id' => $this->templateVersionId,
            'registration_mode' => $this->registrationMode->value,
            'qr_mode' => $this->qrMode->value,
            'compiled_pages' => $this->compiledPages,
            'page_count' => count($this->compiledPages),
            'native_bundle_hash' => $this->nativeBundleHash,
            'artifact_hash' => $this->artifactHash,
            'print_pdf_sha256' => $printPdfSha256,
        ];
    }

    /** @param array<string, mixed> $pinnedInputs */
    public function assertPinnedInputs(array $pinnedInputs, string $printPdfSha256): void
    {
        $expected = $this->pinnedInputs($printPdfSha256);
        if ((int) ($pinnedInputs['template_version_id'] ?? 0) !== $this->templateVersionId
            || ! is_string($pinnedInputs['native_bundle_hash'] ?? null)
            || ! hash_equals($this->nativeBundleHash, $pinnedInputs['native_bundle_hash'])) {
            throw new RuntimeException('print_input_mismatch');
        }

        foreach (['registration_mode', 'qr_mode', 'compiled_pages', 'page_count', 'artifact_hash', 'print_pdf_sha256'] as $key) {
            $isRequired = $this->hasExplicitManifestPolicy || $this->qrMode === OmrQrMode::Disabled;
            if (! array_key_exists($key, $pinnedInputs)) {
                if ($isRequired) {
                    throw new RuntimeException('print_input_mismatch');
                }

                continue;
            }
            if ($pinnedInputs[$key] !== $expected[$key]) {
                throw new RuntimeException('print_input_mismatch');
            }
        }
    }

    /** @param array<string, mixed> $manifest */
    private static function hasPolicyFields(array $manifest): bool
    {
        return array_key_exists('registration_mode', $manifest)
            || array_key_exists('qr_mode', $manifest)
            || array_key_exists('compiled_pages', $manifest);
    }

    /** @param array<string, mixed> $manifest */
    private static function assertManifestPolicy(
        array $manifest,
        OmrRegistrationMode $registrationMode,
        OmrQrMode $qrMode,
    ): void {
        $artifactSet = is_array($manifest['artifact_set'] ?? null) ? $manifest['artifact_set'] : [];
        $artifactPolicy = ($artifactSet['schema'] ?? null) === 'nexiam.omr.canonical-artifact-set.v1'
            ? $artifactSet
            : [];
        foreach (['registration_mode' => $registrationMode->value, 'qr_mode' => $qrMode->value] as $field => $expected) {
            $hasManifestField = array_key_exists($field, $manifest);
            $hasArtifactField = array_key_exists($field, $artifactPolicy);
            if ((! $hasManifestField && ! $hasArtifactField)
                || ($hasManifestField && $manifest[$field] !== $expected)
                || ($hasArtifactField && $artifactPolicy[$field] !== $expected)) {
                throw new RuntimeException('print_policy_manifest_mismatch');
            }
        }
    }

    /** @return list<array{page_no: int}> */
    private static function pageList(mixed $pages): array
    {
        if (! is_array($pages) || ! array_is_list($pages)) {
            throw new RuntimeException('print_page_manifest_invalid');
        }

        $compiledPages = [];
        foreach ($pages as $index => $page) {
            if (! is_array($page) || ! is_int($page['page_no'] ?? null) || $page['page_no'] !== $index + 1) {
                throw new RuntimeException('print_page_manifest_invalid');
            }
            $compiledPages[] = ['page_no' => $page['page_no']];
        }

        return $compiledPages;
    }

    /** @return list<array{page_no: int}> */
    private static function nativeTemplatePages(mixed $pages): array
    {
        if (! is_array($pages) || ! array_is_list($pages)) {
            throw new RuntimeException('print_page_manifest_invalid');
        }

        $pageNumbers = [];
        foreach ($pages as $index => $page) {
            if (! is_array($page) || (array_key_exists('page_no', $page) && $page['page_no'] !== $index + 1)) {
                throw new RuntimeException('print_page_manifest_invalid');
            }
            $pageNumbers[] = ['page_no' => $index + 1];
        }

        return $pageNumbers;
    }

    private static function isSha256(string $hash): bool
    {
        return preg_match('/^[a-f0-9]{64}$/', $hash) === 1;
    }
}
