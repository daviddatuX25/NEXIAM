<?php

namespace App\Assessment\Omr\Diagnostics;

use App\Assessment\Omr\Canonical\CanonicalArtifactSet;
use App\Assessment\Omr\NativeBundle;
use App\Assessment\Omr\OmrDetectionRequest;
use App\Assessment\Omr\OmrDiagnosticRequestBuilder;
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
        private OmrMarkerProfile $profile,
    ) {
        $this->assertCurrent();
        $this->assertMarkerAssets();
    }

    public function kind(): string
    {
        return self::KIND_CANDIDATE;
    }

    public function targetId(): string
    {
        return (string) $this->draft->id;
    }

    public function pageCount(): int
    {
        return count((array) ($this->artifactSet->template['pages'] ?? []));
    }

    /** @return array<string, mixed> */
    public function nativeTemplate(int $pageNo): array
    {
        $page = $this->page($pageNo);

        return [
            'pageDimensions' => $page['pageDimensions'] ?? $this->artifactSet->template['pageDimensions'] ?? null,
            'bubbleDimensions' => $this->artifactSet->template['bubbleDimensions'] ?? null,
            'customLabels' => new stdClass,
            'fieldBlocks' => NativeBundle::workerFieldBlocks((array) ($page['fieldBlocks'] ?? [])),
            'preProcessors' => $page['preProcessors'] ?? [],
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
        return $this->artifactSet->reviewManifest;
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
        return $this->profile->code;
    }

    public function markerProfileVersion(): ?int
    {
        return (int) $this->profile->version;
    }

    public function markerProfileQualified(): bool
    {
        return $this->profile->isQualified();
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
        return (string) ($this->artifactSet->definition['source_hash'] ?? $this->artifactSet->reviewManifestHash);
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
        if ((int) $this->draft->marker_profile_id !== (int) $this->profile->id) {
            throw new OmrDiagnosticTargetException(
                'candidate_marker_profile_mismatch',
                'The artifact set was materialized against a different marker profile than the draft currently selects.',
            );
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
