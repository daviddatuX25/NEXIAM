<?php

namespace App\Assessment\Actions\Omr\Canonical;

use App\Assessment\Omr\Canonical\CanonicalArtifactMaterializer;
use App\Assessment\Omr\Canonical\CanonicalArtifactSet;
use App\Assessment\Omr\Canonical\OmrAuthoringDraftException;
use App\Assessment\Omr\Canonical\OmrDigitalProofGenerator;
use App\Assessment\Omr\OmrDiagnosticRequestBuilder;
use App\Assessment\Omr\OmrLayoutVerificationPort;
use App\Assessment\Omr\OmrLayoutVerificationResult;
use App\Assessment\Omr\OmrRegistrationMode;
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
    ) {}

    /** @return array{draft:OmrTemplateAuthoringDraft,artifact:CanonicalArtifactSet,result:OmrLayoutVerificationResult,preview_page_no:int} */
    public function handle(OmrTemplateAuthoringDraft $draft, User $actor, int $expectedLockVersion, int $pageNo = 1): array
    {
        $saved = $this->materialize->handle($draft, $actor, $expectedLockVersion);
        $profile = $saved->fresh(['markerProfile'])->markerProfile;
        $artifactSet = (array) ($saved->authoring_json['canonical']['artifact_set'] ?? []);
        $candidateVersion = (int) ($artifactSet['candidate_version'] ?? 0);
        $materialized = $this->artifacts->materialize($saved->fresh(['template', 'markerProfile']), $profile, $candidateVersion);
        $targetPageNo = max(1, $pageNo);
        $page = null;
        foreach ($materialized->pages as $candidate) {
            if ((int) ($candidate['page_no'] ?? 0) === $targetPageNo) {
                $page = $candidate;
                break;
            }
        }
        if (! is_array($page)) {
            $page = $materialized->pages[0] ?? null;
        }
        if (! is_array($page)) {
            throw new OmrAuthoringDraftException('canonical_preview_missing', 'The answer-sheet preview produced no page.');
        }
        $imageBytes = $saved->registration_mode === OmrRegistrationMode::DocumentBound
            ? $this->framedDocumentPage($page['bytes'])
            : $page['bytes'];
        $request = $this->requests->buildCandidate($materialized, $imageBytes, 'image/png');

        return [
            'draft' => $saved,
            'artifact' => $materialized,
            'result' => $this->detection->verifyLayout($request),
            'preview_page_no' => (int) ($page['page_no'] ?? 1),
        ];
    }

    private function framedDocumentPage(string $bytes): string
    {
        $image = @imagecreatefromstring($bytes);
        if ($image === false) {
            throw new OmrAuthoringDraftException('canonical_preview_invalid', 'The answer-sheet page could not be read.');
        }

        $framed = OmrDigitalProofGenerator::frameDocumentBoundPage($image);
        $canvas = $framed['image'];
        try {
            ob_start();
            if (! imagepng($canvas, null, 6)) {
                ob_end_clean();
                throw new OmrAuthoringDraftException('canonical_preview_invalid', 'The answer-sheet page could not be framed.');
            }

            return (string) ob_get_clean();
        } finally {
            imagedestroy($canvas);
        }
    }
}
