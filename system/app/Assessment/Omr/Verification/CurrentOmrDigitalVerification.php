<?php

namespace App\Assessment\Omr\Verification;

use App\Assessment\Omr\Canonical\OmrCanonicalGeometryIdentity;
use App\Assessment\Omr\Canonical\OmrNoGoMatrixRunner;
use App\Assessment\Omr\OmrDocumentRectification;
use App\Assessment\Omr\OmrRegistrationMode;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\Assessment\OmrTemplateVerificationCapture;
use App\Models\Assessment\OmrTemplateVerificationRun;

use function count;
use function hash;
use function hash_equals;
use function in_array;
use function is_array;
use function is_string;
use function json_encode;

final class CurrentOmrDigitalVerification
{
    /**
     * Resolve the latest valid passing digital verification match for a draft,
     * checking strict identity against current draft artifact/mapping hashes,
     * plan/proof/vector/matrix hashes, exact required variant captures, and verified proof assets.
     */
    public function find(OmrTemplateAuthoringDraft $draft): ?OmrDigitalVerificationMatch
    {
        $authoring = (array) $draft->authoring_json;
        $canonical = (array) ($authoring['canonical'] ?? []);
        if ($draft->compiled_version_id !== null) {
            $manifest = (array) (($draft->compiledVersion?->authoring_manifest ?? null) ?: []);
            $currentGeometryIdentity = (string) ($manifest['geometry_identity'] ?? $manifest['mapping_hash'] ?? '');
        } else {
            try {
                $currentGeometryIdentity = OmrCanonicalGeometryIdentity::forDraft($draft);
            } catch (\Throwable) {
                $currentGeometryIdentity = '';
            }
        }

        if ($currentGeometryIdentity === '') {
            return null;
        }

        $artifactSet = $canonical['artifact_set']
            ?? ($canonical['superseded_artifact_sets'][$currentGeometryIdentity]['artifact_set'] ?? null);
        if (! is_array($artifactSet) || empty($artifactSet)) {
            return null;
        }
        $currentArtifactHash = (string) ($artifactSet['artifact_hash'] ?? $canonical['artifact_hash'] ?? '');
        if ($currentArtifactHash === '') {
            return null;
        }

        return $this->findForTarget((int) $draft->id, $currentArtifactHash, $currentGeometryIdentity, $draft);
    }

    /**
     * Resolve matching verification against a specific draft ID and target hashes.
     */
    public function findForTarget(
        int $draftId,
        string $currentArtifactHash,
        string $currentMappingHash = '',
        ?OmrTemplateAuthoringDraft $draft = null,
    ): ?OmrDigitalVerificationMatch {
        if ($draftId <= 0 || $currentArtifactHash === '' || $currentMappingHash === '') {
            return null;
        }

        $latestRun = OmrTemplateVerificationRun::query()
            ->where('draft_id', $draftId)
            ->where('mode', OmrTemplateVerificationRun::MODE_DIGITAL)
            ->where('artifact_hash', $currentArtifactHash)
            ->where('mapping_hash', $currentMappingHash)
            ->orderByDesc('id')
            ->first();

        if ($latestRun === null) {
            return null;
        }

        return $this->validateRun($latestRun, $currentArtifactHash, $currentMappingHash, $draft);
    }

    /**
     * Validate an existing run against all verification invariants.
     */
    public function validateRun(
        OmrTemplateVerificationRun $run,
        string $currentArtifactHash,
        string $currentMappingHash = '',
        ?OmrTemplateAuthoringDraft $draft = null,
    ): ?OmrDigitalVerificationMatch {
        if ($run->mode !== OmrTemplateVerificationRun::MODE_DIGITAL) {
            return null;
        }

        if ($run->status !== OmrTemplateVerificationRun::STATUS_PASSED) {
            return null;
        }

        if (! is_string($run->artifact_hash) || $run->artifact_hash === '' || ! hash_equals($currentArtifactHash, $run->artifact_hash)) {
            return null;
        }

        if ($currentMappingHash === '' || ! is_string($run->mapping_hash) || $run->mapping_hash === '' || ! hash_equals($currentMappingHash, $run->mapping_hash)) {
            return null;
        }

        // 1. Plan and vector/proof hashes must be non-empty strings
        if (! is_string($run->plan_hash) || $run->plan_hash === '') {
            return null;
        }

        if (! is_string($run->expected_vector_hash) || $run->expected_vector_hash === '') {
            return null;
        }

        if (! is_string($run->proof_hash) || $run->proof_hash === '') {
            return null;
        }

        if (! is_string($run->clean_vector_hash) || $run->clean_vector_hash === '') {
            return null;
        }

        // 2. Summary integrity: must be array and internal hashes must match run fields exactly
        $summary = $run->result_summary;
        if (! is_array($summary)) {
            return null;
        }

        $summaryProofHash = (string) ($summary['proof_hash'] ?? '');
        if ($summaryProofHash === '' || ! hash_equals((string) $run->proof_hash, $summaryProofHash)) {
            return null;
        }

        $summaryExpectedVectorHash = (string) ($summary['expected_vector_hash'] ?? '');
        if ($summaryExpectedVectorHash === '' || ! hash_equals((string) $run->expected_vector_hash, $summaryExpectedVectorHash)) {
            return null;
        }

        $summaryCleanVectorHash = (string) ($summary['clean_vector_hash'] ?? '');
        if ($summaryCleanVectorHash === '' || ! hash_equals((string) $run->clean_vector_hash, $summaryCleanVectorHash)) {
            return null;
        }

        $variantsMap = $summary['variants'] ?? null;
        if (! is_array($variantsMap)) {
            return null;
        }

        // Recompute matrix hash from summary variants and verify it matches matrix_hash
        $computedMatrixHash = hash('sha256', json_encode($variantsMap, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        $matrixHash = (string) ($summary['matrix_hash'] ?? '');
        if ($matrixHash === '' || ! hash_equals($computedMatrixHash, $matrixHash)) {
            return null;
        }

        // 3. Validate exact required variant set: exact count, no missing, no extras, all pass
        $draft ??= $run->draft;
        if ($draft === null || $draft->registration_mode === null
            || ! $draft->registration_mode instanceof OmrRegistrationMode
            || $draft->requested_page_count < 1 || $draft->requested_page_count > 4) {
            return null;
        }
        $expectedVariants = OmrNoGoMatrixRunner::variantKeys($draft->requested_page_count, $draft->registration_mode);
        $captures = OmrTemplateVerificationCapture::query()
            ->where('run_id', $run->id)
            ->where('attempt', $run->attempt)
            ->get();

        if ($captures->count() !== count($expectedVariants)) {
            return null;
        }

        $capturesByVariant = [];
        foreach ($captures as $capture) {
            if (! is_string($capture->variant) || $capture->variant === '' || ! in_array($capture->variant, $expectedVariants, true)) {
                return null; // Unknown extra or null variant
            }
            $variantPageNo = OmrNoGoMatrixRunner::parseVariantKey($capture->variant, $draft->registration_mode)['page_no'];
            if ((int) $capture->page_no !== $variantPageNo) {
                return null;
            }
            if (isset($capturesByVariant[$capture->variant])) {
                return null; // Duplicate variant capture
            }
            if ($capture->status !== OmrTemplateVerificationCapture::STATUS_PASS) {
                return null; // Failed variant capture
            }

            $capturesByVariant[$capture->variant] = $capture;
        }

        foreach ($expectedVariants as $variant) {
            if (! isset($capturesByVariant[$variant])) {
                return null; // Missing required variant
            }
        }
        if ($draft->registration_mode === OmrRegistrationMode::DocumentBound) {
            if (count($variantsMap) !== count($expectedVariants)) {
                return null;
            }
            foreach ($capturesByVariant as $key => $capture) {
                $variant = OmrNoGoMatrixRunner::parseVariantKey($key, OmrRegistrationMode::DocumentBound)['variant'];
                $exact = OmrNoGoMatrixRunner::expectsExactRecovery(OmrRegistrationMode::DocumentBound, $variant);
                $expected = $exact ? 'pass' : 'typed_rejection';
                $rectification = $capture->summary['rectification'] ?? null;
                $layout = is_array($rectification) ? ($rectification['layout'] ?? null) : null;
                $detection = is_array($rectification) ? ($rectification['detection'] ?? null) : null;
                if ($capture->expected !== $expected || $capture->actual !== $expected
                    || ($capture->summary['registration_mode'] ?? null) !== OmrRegistrationMode::DocumentBound->value
                    || ($variantsMap[$key]['registration_mode'] ?? null) !== OmrRegistrationMode::DocumentBound->value
                    || ($variantsMap[$key]['rectification'] ?? null) !== $rectification) {
                    return null;
                }
                if ($exact) {
                    if (! is_array($layout) || ! is_array($detection)
                        || ! OmrDocumentRectification::sameSelectedWarp($layout, $detection)
                        || ($rectification['accepted'] ?? null) !== true
                        || ($rectification['same_selected_warp'] ?? null) !== true
                        || $capture->vector_match !== true) {
                        return null;
                    }
                } else {
                    $codes = $capture->summary['worker_codes'] ?? null;
                    $allowed = OmrNoGoMatrixRunner::expectedDocumentRejectionCodes($variant);
                    if (! is_array($layout) || ! is_array($detection)
                        || ($layout['attempted'] ?? null) !== true
                        || ($detection['attempted'] ?? null) !== true
                        || ($layout['mode'] ?? null) !== OmrRegistrationMode::DocumentBound->value
                        || ($detection['mode'] ?? null) !== OmrRegistrationMode::DocumentBound->value
                        || ($layout['document_gates']['accepted'] ?? null) !== false
                        || ($detection['document_gates']['accepted'] ?? null) !== false
                        || ! is_array($codes)
                        || $capture->vector_match !== null
                        || ! in_array($codes['layout'] ?? null, $allowed, true)
                        || ! in_array($codes['detection'] ?? null, $allowed, true)
                        || ($variantsMap[$key]['worker_codes'] ?? null) !== $codes) {
                        return null;
                    }
                }
            }
        }

        // 4. Validate proof assets: must match run_id, attempt, and proof_hash; require at least 1 valid proof asset
        $draftId = $run->draft_id ?? $draft?->id;
        if ($draftId === null) {
            return null;
        }

        $proofAssetRecords = OmrTemplateAsset::query()
            ->where('authoring_draft_id', (int) $draftId)
            ->where('kind', OmrTemplateAsset::KIND_DIGITAL_PROOF)
            ->orderBy('page_no')
            ->get();

        $proofAssets = [];
        foreach ($proofAssetRecords as $asset) {
            $meta = is_array($asset->metadata) ? $asset->metadata : [];
            $runIdMatch = (int) ($meta['run_id'] ?? 0) === (int) $run->id;
            $proofHashMatch = is_string($meta['proof_hash'] ?? null) && hash_equals((string) $meta['proof_hash'], (string) $run->proof_hash);
            if ($runIdMatch && $proofHashMatch) {
                $proofAssets[] = [
                    'page_no' => (int) $asset->page_no,
                    'asset_id' => $asset->id,
                    'sha256' => $asset->sha256,
                ];
            }
        }
        if ($proofAssets === []) {
            return null; // Require at least one valid proof asset
        }

        $verificationHash = hash('sha256', json_encode([
            'run_id' => $run->id,
            'attempt' => (int) $run->attempt,
            'plan_hash' => (string) $run->plan_hash,
            'artifact_hash' => (string) $run->artifact_hash,
            'proof_hash' => (string) $run->proof_hash,
            'matrix_hash' => $matrixHash,
            'clean_vector_hash' => (string) $run->clean_vector_hash,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        return new OmrDigitalVerificationMatch(
            run: $run,
            artifactHash: (string) $run->artifact_hash,
            proofHash: (string) $run->proof_hash,
            expectedVectorHash: (string) $run->expected_vector_hash,
            cleanVectorHash: (string) $run->clean_vector_hash,
            matrixHash: $matrixHash,
            verificationHash: $verificationHash,
            resultSummary: $summary,
            proofAssets: $proofAssets,
        );
    }
}
