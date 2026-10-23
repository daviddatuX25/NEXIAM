<?php

namespace App\Assessment\Omr\Canonical;

use App\Assessment\Omr\OmrQrMode;
use App\Assessment\Omr\OmrRegistrationMode;
use App\Assessment\Omr\Verification\CurrentOmrDigitalVerification;
use App\Assessment\Support\MaterialOwnerAccess;
use App\Authorization\CapabilityCatalog;
use App\Models\Assessment\OmrMarkerProfile;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\Assessment\OmrTemplateVerificationCapture;
use App\Models\Assessment\OmrTemplateVerificationRun;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class RestoreCanonicalOmrFixture
{
    public function __construct(
        private readonly CanonicalOmrFixture $fixture,
        private readonly OmrTemplateAssetStore $assets,
        private readonly OmrMarkerProfileService $markerProfiles,
        private readonly CurrentOmrDigitalVerification $currentDigitalVerification,
    ) {}

    public function handle(User $owner): OmrTemplateAuthoringDraft
    {
        $institutionId = app(MaterialOwnerAccess::class)->institutionId(
            $owner,
            CapabilityCatalog::GUIDANCE_SCORING_OMR,
        );

        return DB::transaction(function () use ($owner, $institutionId): OmrTemplateAuthoringDraft {
            $existing = OmrTemplateAuthoringDraft::query()
                ->where('code', CanonicalOmrFixture::STABLE_CODE)
                ->lockForUpdate()
                ->first();
            if ($existing !== null) {
                return $existing;
            }

            $markerHash = '';
            foreach ($this->fixture->assets() as $assetFixture) {
                if (($assetFixture['kind'] ?? null) === OmrTemplateAsset::KIND_MARKER_PROFILE) {
                    $markerHash = (string) ($assetFixture['sha256'] ?? '');
                    break;
                }
            }

            $profile = $this->markerProfiles->seedCandidates($owner)
                ->first(fn (OmrMarkerProfile $candidate): bool => hash_equals(
                    (string) $candidate->asset_sha256,
                    $markerHash,
                ));
            if (! $profile instanceof OmrMarkerProfile) {
                throw new RuntimeException('canonical_fixture_marker_profile_missing');
            }

            $authoring = $this->fixture->authoring();
            unset($authoring['canonical']['superseded_artifact_sets']);
            data_set($authoring, 'marker.profile_id', $profile->id);

            $draft = OmrTemplateAuthoringDraft::query()->create([
                'code' => CanonicalOmrFixture::STABLE_CODE,
                'name' => 'Canonical HTML Exam Template',
                'source_kind' => OmrTemplateAuthoringDraft::SOURCE_HTML,
                'registration_mode' => OmrRegistrationMode::FiducialAnchored,
                'qr_mode' => OmrQrMode::Signed,
                'requested_page_count' => max(1, count((array) ($authoring['pages'] ?? []))),
                'stage' => OmrTemplateAuthoringDraft::STAGE_MAPPING_COMPLETE,
                'marker_profile_id' => $profile->id,
                'authoring_json' => $authoring,
                'lock_version' => 0,
                'institution_id' => $institutionId,
                'is_synthetic' => true,
                'owner_user_id' => $owner->id,
            ]);

            $proofFixture = null;
            foreach ($this->fixture->assets() as $assetFixture) {
                if (($assetFixture['kind'] ?? null) === OmrTemplateAsset::KIND_DIGITAL_PROOF) {
                    $proofFixture = $assetFixture;

                    continue;
                }

                $asset = $this->storeAsset($draft, $assetFixture);
                data_set($authoring, (string) $assetFixture['slot'], $asset->id);
            }

            $runFixture = (array) $this->fixture->verification()['run'];
            $runFixture['mapping_hash'] = OmrCanonicalGeometryIdentity::forDraft($draft->fresh());
            $run = OmrTemplateVerificationRun::query()->create([
                ...$runFixture,
                'draft_id' => $draft->id,
                'template_version_id' => null,
                'started_by_user_id' => $owner->id,
            ]);

            foreach ((array) $this->fixture->verification()['captures'] as $captureFixture) {
                OmrTemplateVerificationCapture::query()->create([
                    ...(array) $captureFixture,
                    'run_id' => $run->id,
                    'captured_by_user_id' => $owner->id,
                ]);
            }

            if (! is_array($proofFixture)) {
                throw new RuntimeException('canonical_fixture_proof_asset_missing');
            }

            $proofAsset = $this->storeAsset($draft, $proofFixture, [
                'run_id' => $run->id,
                'attempt' => $run->attempt,
                'proof_hash' => $run->proof_hash,
            ]);
            data_set($authoring, (string) $proofFixture['slot'], $proofAsset->id);
            data_set($authoring, 'verification.run_id', $run->id);

            $draft->forceFill([
                'authoring_json' => $authoring,
                'stage' => OmrTemplateAuthoringDraft::STAGE_DIGITAL_VERIFIED,
                'lock_version' => 1,
            ])->save();

            $match = $this->currentDigitalVerification->find($draft->fresh());
            if ($match === null) {
                throw new RuntimeException('canonical_fixture_digital_verification_invalid:'.json_encode([
                    'geometry' => OmrCanonicalGeometryIdentity::forDraft($draft->fresh()),
                    'mapping' => $run->mapping_hash,
                    'captures' => $run->captures()->count(),
                    'proof_assets' => $draft->assets()->where('kind', OmrTemplateAsset::KIND_DIGITAL_PROOF)->count(),
                    'summary_hash' => hash('sha256', json_encode(
                        data_get($run->result_summary, 'variants'),
                        JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
                    )),
                    'matrix_hash' => data_get($run->result_summary, 'matrix_hash'),
                ], JSON_THROW_ON_ERROR));
            }

            $authoring = (array) $draft->fresh()->authoring_json;
            data_set($authoring, 'verification.hashes.digital_verification', $match->verificationHash);
            $draft->forceFill(['authoring_json' => $authoring])->save();

            return $draft->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $fixture
     * @param  array<string, mixed>  $metadata
     */
    private function storeAsset(
        OmrTemplateAuthoringDraft $draft,
        array $fixture,
        array $metadata = [],
    ): OmrTemplateAsset {
        return $this->assets->putDraftAsset(
            draft: $draft,
            kind: (string) $fixture['kind'],
            bytes: $this->fixture->assetContents($fixture),
            mime: (string) $fixture['mime'],
            pageNo: isset($fixture['page_no']) ? (int) $fixture['page_no'] : null,
            metadata: ['fixture' => CanonicalOmrFixture::STABLE_CODE, ...$metadata],
        );
    }
}
