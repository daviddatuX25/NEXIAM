<?php

namespace App\Assessment\Omr\Diagnostics;

use App\Assessment\Omr\Canonical\CanonicalArtifactSet;
use App\Assessment\Omr\Canonical\OmrPageDimensionMath;
use App\Assessment\Omr\Canonical\OmrQrReservationResolver;
use App\Assessment\Omr\Canonical\OmrSourcePolicy;
use App\Assessment\Omr\NativeBundle;
use App\Assessment\Omr\OmrDetectionRequest;
use App\Assessment\Omr\OmrDiagnosticRequestBuilder;
use App\Assessment\Omr\OmrQrMode;
use App\Assessment\Omr\OmrRegistrationMode;
use App\Models\Assessment\OmrMarkerProfile;
use App\Models\Assessment\OmrTemplateAuthoringDraft;

use function count;
use function hash;
use function hash_equals;
use function is_array;
use function is_string;

/**
 * Rehearsal-only target over a materialized draft candidate (OpenSpec 35.12).
 *
 * The draft is the mutable owner; this adapter fails closed when the draft's
 * persisted artifact/definition hashes no longer match the artifact set being
 * verified, and never reports `qualificationCapable()` (AD-2). After compile
 * the draft stops being a valid candidate target — the immutable
 * {@see CompiledVersionDiagnosticTarget} takes over.
 */
final readonly class CandidateArtifactDiagnosticTarget implements OmrDiagnosticTarget
{
    public function __construct(
        private OmrTemplateAuthoringDraft $draft,
        private CanonicalArtifactSet $artifactSet,
        private ?OmrMarkerProfile $profile,
    ) {
        $this->assertCurrent();
        if ($this->registrationMode() === OmrRegistrationMode::FiducialAnchored) {
            $this->assertMarkerAssets();
        }
    }

    public function kind(): string
    {
        return self::KIND_CANDIDATE;
    }

    public function sourceKind(): string
    {
        return OmrSourcePolicy::sourceKindForDraft($this->draft) ?? OmrSourcePolicy::SYSTEM_DESIGNED;
    }

    public function targetId(): string
    {
        return (string) $this->draft->id;
    }

    public function pageCount(): int
    {
        return count($this->compiledPages());
    }

    public function registrationMode(): OmrRegistrationMode
    {
        $mode = $this->draft->registration_mode;
        if (! $mode instanceof OmrRegistrationMode) {
            throw new OmrDiagnosticTargetException('candidate_registration_mode_missing', 'The draft registration policy is not explicit.');
        }

        return $mode;
    }

    public function qrMode(): OmrQrMode
    {
        $mode = $this->draft->qr_mode;
        if (! $mode instanceof OmrQrMode) {
            throw new OmrDiagnosticTargetException('candidate_qr_mode_missing', 'The draft QR policy is not explicit.');
        }

        return $mode;
    }

    /** @return list<array{page_no:int}> */
    public function compiledPages(): array
    {
        $pages = $this->artifactSet->manifest['compiled_pages'] ?? $this->artifactSet->definition['compiled_pages'] ?? null;
        if (! is_array($pages) || ! array_is_list($pages) || $pages === []) {
            throw new OmrDiagnosticTargetException('candidate_compiled_pages_missing', 'The candidate has no explicit ordered compiled page list.');
        }
        foreach ($pages as $index => $page) {
            if (! is_array($page) || (int) ($page['page_no'] ?? 0) !== $index + 1) {
                throw new OmrDiagnosticTargetException('candidate_compiled_pages_mismatch', 'Candidate compiled pages must be a contiguous ordered page list.');
            }
        }

        return $pages;
    }

    /** @return array{width_mm:float,height_mm:float} */
    public function paperDimensionsMm(int $pageNo): array
    {
        $paper = (array) ($this->artifactSet->definition['paper'] ?? []);
        $width = (float) ($paper['width_mm'] ?? 0);
        $height = (float) ($paper['height_mm'] ?? 0);
        if ($width <= 0 || $height <= 0) {
            $page = $this->page($pageNo);
            $dimensions = (array) ($page['pageDimensions'] ?? []);
            $insets = (array) ($this->artifactSet->definition['insets_px'] ?? []);
            $width = OmrPageDimensionMath::millimetresFromPixels(
                (int) ($dimensions[0] ?? 0) + (int) ($insets['left'] ?? 0) + (int) ($insets['right'] ?? 0),
            );
            $height = OmrPageDimensionMath::millimetresFromPixels(
                (int) ($dimensions[1] ?? 0) + (int) ($insets['top'] ?? 0) + (int) ($insets['bottom'] ?? 0),
            );
        }
        if ($width <= 0 || $height <= 0) {
            throw new OmrDiagnosticTargetException('candidate_paper_dimensions_missing', 'The candidate paper dimensions are not available.');
        }

        return ['width_mm' => $width, 'height_mm' => $height];
    }

    /** @return list<array<string,mixed>> */
    public function visualComponents(int $pageNo): array
    {
        $components = (array) ($this->artifactSet->definition['visual_components'] ?? []);
        $pageCount = count($this->compiledPages());

        return array_values(array_filter($components, static function (mixed $component) use ($pageNo, $pageCount): bool {
            if (! is_array($component)) {
                return false;
            }
            if (! isset($component['page_no'])) {
                return $pageCount === 1 && $pageNo === 1;
            }

            return (int) $component['page_no'] === $pageNo;
        }));
    }

    /** @return array<string, mixed> */
    public function nativeTemplate(int $pageNo): array
    {
        $page = $this->page($pageNo);

        return [
            'pageDimensions' => $page['pageDimensions'] ?? $this->artifactSet->template['pageDimensions'] ?? null,
            'bubbleDimensions' => $this->artifactSet->template['bubbleDimensions'] ?? null,
            'customLabels' => new \stdClass,
            'fieldBlocks' => NativeBundle::workerFieldBlocks((array) ($page['fieldBlocks'] ?? [])),
            'preProcessors' => $page['preProcessors'] ?? [],
            'markers' => $page['markers'] ?? [],
            'visualComponents' => $this->artifactSet->template['visualComponents'] ?? [],
        ];
    }

    /** @return array<string, mixed> */
    public function nativeConfig(): array
    {
        return $this->artifactSet->config;
    }

    /** @return array<string, string> */
    public function nativeAssets(): array
    {
        $name = $this->markerAssetName();

        return $name !== null ? [$name => $this->artifactSet->file($name)] : [];
    }

    /** @return array<string, mixed> */
    public function reviewManifest(): array
    {
        $manifest = $this->artifactSet->reviewManifest;
        $manifest['registration_mode'] = $this->registrationMode()->value;
        $manifest['qr_mode'] = $this->qrMode()->value;
        $manifest['compiled_pages'] = $this->compiledPages();
        $manifest['paper'] = $this->artifactSet->definition['paper'] ?? ($manifest['paper'] ?? null);
        $manifest['insets_px'] = $this->artifactSet->definition['insets_px'] ?? ($manifest['insets_px'] ?? []);
        $manifest['visual_components'] = $this->artifactSet->definition['visual_components'] ?? ($manifest['visual_components'] ?? []);
        if ($this->qrMode() === OmrQrMode::Signed) {
            $reservation = OmrQrReservationResolver::extractReservationBox(
                (array) ($this->artifactSet->definition['overlay'] ?? []),
            );
            if ($reservation !== null) {
                $manifest['qr_reservation'] = [
                    'x' => (int) round($reservation['x']),
                    'y' => (int) round($reservation['y']),
                    'width' => (int) round($reservation['width']),
                    'height' => (int) round($reservation['height']),
                ];
            }
        } elseif (OmrQrReservationResolver::extractReservationBox((array) ($this->artifactSet->definition['overlay'] ?? [])) !== null) {
            throw new OmrDiagnosticTargetException('candidate_qr_mode_mismatch', 'A QR-disabled candidate cannot contain a production QR reservation.');
        }

        return $manifest;
    }

    public function markerAssetName(): ?string
    {
        foreach ((array) ($this->artifactSet->template['pages'] ?? []) as $page) {
            if (is_array($page) && is_string($page['markers']['asset_name'] ?? null)) {
                return $page['markers']['asset_name'];
            }
        }

        return null;
    }

    public function markerAssetHash(): ?string
    {
        $name = $this->markerAssetName();

        return $name !== null ? $this->artifactSet->markerHash : null;
    }

    public function markerProfileCode(): ?string
    {
        return $this->profile?->code;
    }

    public function markerProfileVersion(): ?int
    {
        return $this->profile !== null ? (int) $this->profile->version : null;
    }

    public function markerProfileQualified(): bool
    {
        return $this->profile?->isQualified() ?? false;
    }

    public function artifactHash(): string
    {
        return $this->artifactSet->artifactHash;
    }

    public function nativeBundleHash(): string
    {
        return (string) ($this->artifactSet->reviewManifest['bundle_hash'] ?? '');
    }

    public function manifestHash(): string
    {
        return $this->artifactSet->reviewManifestHash;
    }

    public function configHash(): string
    {
        return $this->artifactSet->configHash;
    }

    public function mappingHash(): string
    {
        return (string) ($this->artifactSet->geometryIdentity
            ?: ($this->artifactSet->definition['geometry_identity'] ?? ''));
    }

    public function engineName(): string
    {
        return (string) ($this->artifactSet->manifest['engine']['name'] ?? 'OMRChecker');
    }

    public function engineCommit(): ?string
    {
        $commit = $this->artifactSet->manifest['engine']['commit'] ?? null;

        return is_string($commit) && $commit !== '' ? $commit : null;
    }

    public function patchIdentity(): string
    {
        return (string) ($this->artifactSet->manifest['engine']['patch_set'] ?? '');
    }

    public function qualificationCapable(): bool
    {
        return false;
    }

    public function buildRequest(OmrDiagnosticRequestBuilder $builder, string $imageBytes, string $imageMime, int $pageNo): OmrDetectionRequest
    {
        return $builder->buildCandidate($this->artifactSet, $imageBytes, $imageMime, $pageNo);
    }

    /**
     * @return array<string, mixed>
     */
    private function page(int $pageNo): array
    {
        foreach ((array) ($this->artifactSet->template['pages'] ?? []) as $page) {
            if (is_array($page) && (int) ($page['page_no'] ?? 0) === $pageNo) {
                return $page;
            }
        }

        throw new OmrDiagnosticTargetException('diagnostic_page_out_of_range', "The candidate bundle has no page {$pageNo}.");
    }

    private function assertCurrent(): void
    {
        if ($this->draft->stage === OmrTemplateAuthoringDraft::STAGE_COMPILED) {
            throw new OmrDiagnosticTargetException(
                'candidate_draft_compiled',
                'The draft is compiled; its immutable version target owns diagnostics now.',
            );
        }
        $registrationMode = $this->registrationMode();
        $qrMode = $this->qrMode();
        $draftProfileId = $this->draft->marker_profile_id;
        if ($registrationMode === OmrRegistrationMode::FiducialAnchored) {
            if ($this->profile === null || (int) $draftProfileId !== (int) $this->profile->id) {
                throw new OmrDiagnosticTargetException(
                    'candidate_marker_profile_mismatch',
                    'The artifact set was materialized against a different marker profile than the draft currently selects.',
                );
            }
        } elseif ($this->profile !== null || $draftProfileId !== null || $this->markerAssetName() !== null) {
            throw new OmrDiagnosticTargetException(
                'candidate_registration_mode_mismatch',
                'A document-bound candidate must not carry marker profile state or marker assets.',
            );
        }

        if (($this->artifactSet->manifest['registration_mode'] ?? null) !== $registrationMode->value
            || ($this->artifactSet->definition['registration_mode'] ?? null) !== $registrationMode->value
            || ($this->artifactSet->manifest['qr_mode'] ?? null) !== $qrMode->value
            || ($this->artifactSet->definition['qr_mode'] ?? null) !== $qrMode->value) {
            throw new OmrDiagnosticTargetException('candidate_policy_mismatch', 'Candidate mode columns, definition, and native manifest must agree explicitly.');
        }

        $compiledPages = $this->compiledPages();
        $definitionPages = $this->artifactSet->definition['compiled_pages'] ?? null;
        $templatePages = array_map(
            static fn (array $page): array => ['page_no' => (int) ($page['page_no'] ?? 0)],
            array_values(array_filter((array) ($this->artifactSet->template['pages'] ?? []), 'is_array')),
        );
        if ($definitionPages !== $compiledPages
            || $templatePages !== $compiledPages
            || count($compiledPages) !== (int) $this->draft->requested_page_count) {
            throw new OmrDiagnosticTargetException('candidate_page_policy_mismatch', 'Candidate page count and ordered native page list must match the draft authority.');
        }
        if ($registrationMode === OmrRegistrationMode::DocumentBound) {
            foreach ((array) ($this->artifactSet->template['pages'] ?? []) as $page) {
                if (! is_array($page)
                    || ! empty($page['markers'])
                    || in_array('CropOnMarkers', (array) ($page['preProcessors'] ?? []), true)) {
                    throw new OmrDiagnosticTargetException('candidate_registration_mode_mismatch', 'A document-bound candidate cannot contain marker geometry or marker cropping.');
                }
            }
        }
        if ($qrMode === OmrQrMode::Disabled) {
            foreach ((array) ($this->artifactSet->definition['visual_components'] ?? []) as $component) {
                if (is_array($component) && strtolower((string) ($component['type'] ?? '')) === 'qr') {
                    throw new OmrDiagnosticTargetException('candidate_qr_mode_mismatch', 'A QR-disabled candidate cannot contain a production QR component.');
                }
            }
        }

        $authoring = (array) $this->draft->authoring_json;
        $artifactSet = (array) (($authoring['canonical']['artifact_set'] ?? null) ?: []);
        $persistedArtifactHash = (string) ($artifactSet['artifact_hash'] ?? '');
        $persistedDefinitionHash = (string) ($artifactSet['definition_hash'] ?? '');
        if ($persistedArtifactHash !== '' && ! hash_equals($persistedArtifactHash, $this->artifactSet->artifactHash)) {
            throw new OmrDiagnosticTargetException(
                'candidate_artifact_stale',
                'The draft artifact set changed after this candidate was materialized; rematerialize before verifying.',
            );
        }
        if ($persistedDefinitionHash !== '' && ! hash_equals($persistedDefinitionHash, (string) ($this->artifactSet->definition['source_hash'] ?? ''))) {
            throw new OmrDiagnosticTargetException(
                'candidate_definition_stale',
                'The draft definition changed after this candidate was materialized; rematerialize before verifying.',
            );
        }
    }

    private function assertMarkerAssets(): void
    {
        $name = $this->markerAssetName();
        if ($name === null || ! isset($this->artifactSet->files[$name])) {
            throw new OmrDiagnosticTargetException(
                'candidate_marker_asset_missing',
                'The candidate bundle has no declared marker asset; marker-bearing templates are required for diagnostics.',
            );
        }
        if (! hash_equals($this->artifactSet->markerHash, hash('sha256', $this->artifactSet->files[$name]))) {
            throw new OmrDiagnosticTargetException(
                'candidate_marker_asset_integrity_failure',
                'The candidate marker asset bytes do not match the artifact hash.',
            );
        }
    }
}
