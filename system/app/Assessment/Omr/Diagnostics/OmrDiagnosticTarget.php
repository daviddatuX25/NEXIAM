<?php

namespace App\Assessment\Omr\Diagnostics;

use App\Assessment\Omr\OmrDetectionRequest;
use App\Assessment\Omr\OmrDiagnosticRequestBuilder;
use App\Assessment\Omr\OmrQrMode;
use App\Assessment\Omr\OmrRegistrationMode;

/**
 * Uniform read-only view of a template the diagnostic kernel can verify
 * (OpenSpec 35.11, AD-2).
 *
 * Two adapters implement it: {@see CandidateArtifactDiagnosticTarget}
 * (materialized draft artifact set, rehearsal-only) and
 * {@see CompiledVersionDiagnosticTarget} (pinned immutable version assets,
 * qualification-capable).
 *
 * Invariant: `qualificationCapable()` is a property of the target KIND, not
 * of any worker result. A candidate target ALWAYS reports `false` and a
 * compiled-version target ALWAYS reports `true`; the runner refuses
 * full-qualification plans against targets that are not qualification-capable
 * regardless of what the worker would return. `buildRequest()` is the only
 * path that turns a target into a signed worker envelope and always delegates
 * to the shared {@see OmrDiagnosticRequestBuilder} (AD-1: no second request
 * builder).
 */
interface OmrDiagnosticTarget
{
    public const KIND_CANDIDATE = 'candidate';

    public const KIND_COMPILED_VERSION = 'compiled_version';

    /** `candidate` or `compiled_version`. */
    public function kind(): string;

    /** Stable identity: draft id for candidates, template-version id for versions. */
    public function targetId(): string;

    /** Number of pages the target covers. */
    public function pageCount(): int;

    /** Explicit immutable paper registration policy; never inferred from markers. */
    public function registrationMode(): OmrRegistrationMode;

    /** Explicit production QR policy; diagnostic calibration QR is separate. */
    public function qrMode(): OmrQrMode;

    /** Ordered immutable compiled/native page authority. @return list<array{page_no:int}> */
    public function compiledPages(): array;

    /** Page physical dimensions in millimetres. @return array{width_mm:float,height_mm:float} */
    public function paperDimensionsMm(int $pageNo): array;

    /** Visual components intersecting the page, in physical paper coordinates. @return list<array<string,mixed>> */
    public function visualComponents(int $pageNo): array;

    /**
     * The worker-ready native page template (marker-frame geometry).
     *
     * @return array<string, mixed>
     */
    public function nativeTemplate(int $pageNo): array;

    /** @return array<string, mixed> */
    public function nativeConfig(): array;

    /**
     * Native assets the worker materializes next to template.json
     * (name => bytes, bounded marker assets).
     *
     * @return array<string, string>
     */
    public function nativeAssets(): array;

    /** @return array<string, mixed> */
    public function reviewManifest(): array;

    public function markerAssetName(): ?string;

    public function markerAssetHash(): ?string;

    public function markerProfileCode(): ?string;

    public function markerProfileVersion(): ?int;

    public function markerProfileQualified(): bool;

    public function artifactHash(): string;

    public function nativeBundleHash(): string;

    public function manifestHash(): string;

    public function configHash(): string;

    /** Source/mapping hash identifying the authored definition behind the artifact. */
    public function mappingHash(): string;

    public function engineName(): string;

    public function engineCommit(): ?string;

    public function patchIdentity(): string;

    /**
     * Kind-bound capability invariant: candidates are rehearsal-only,
     * compiled versions may satisfy full qualification.
     */
    public function qualificationCapable(): bool;

    /**
     * Build the signed worker request through the shared builder (AD-1).
     *
     * @throws OmrDiagnosticTargetException when the target cannot serve the requested page
     */
    public function buildRequest(OmrDiagnosticRequestBuilder $builder, string $imageBytes, string $imageMime, int $pageNo): OmrDetectionRequest;
}
