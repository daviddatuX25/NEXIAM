<?php

namespace App\Assessment\Actions\Omr\Canonical;

use App\Assessment\Omr\Canonical\CanonicalArtifactMaterializer;
use App\Assessment\Omr\Canonical\CanonicalArtifactSet;
use App\Assessment\Omr\Canonical\OmrAuthoringDraftException;
use App\Assessment\Omr\OmrDiagnosticRequestBuilder;
use App\Assessment\Omr\OmrLayoutVerificationPort;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\User;

/** Materialize a draft and run its clean page through the real layout worker. */
final class PreviewOmrCanonicalDraft
{
    public function __construct(
        private readonly MaterializeOmrCanonicalDraft $materialize,
        private readonly CanonicalArtifactMaterializer $artifacts,
        private readonly OmrDiagnosticRequestBuilder $requests,
        private readonly OmrLayoutVerificationPort $detection,
    ) {
    }

    /** @return array{draft:OmrTemplateAuthoringDraft,artifact:CanonicalArtifactSet,result:\App\Assessment\Omr\OmrLayoutVerificationResult} */
    public function handle(OmrTemplateAuthoringDraft $draft, User $actor, int $expectedLockVersion): array
    {
        $saved = $this->materialize->handle($draft, $actor, $expectedLockVersion);
        $profile = $saved->fresh(['markerProfile'])->markerProfile;
        if ($profile === null) {
            throw new OmrAuthoringDraftException('marker_profile_not_selected', 'Select a marker profile before preview verification.');
        }
        $artifactSet = (array) ($saved->authoring_json['canonical']['artifact_set'] ?? []);
        $candidateVersion = (int) ($artifactSet['candidate_version'] ?? 0);
        $materialized = $this->artifacts->materialize($saved->fresh(['template', 'markerProfile']), $profile, $candidateVersion);
        $page = $materialized->pages[0] ?? null;
        if (! is_array($page)) {
            throw new OmrAuthoringDraftException('canonical_preview_missing', 'Canonical materialization produced no page.');
        }
        $request = $this->requests->buildCandidate($materialized, $page['bytes'], 'image/png');

        return [
            'draft' => $saved,
            'artifact' => $materialized,
            'result' => $this->detection->verifyLayout($request),
        ];
    }
}
