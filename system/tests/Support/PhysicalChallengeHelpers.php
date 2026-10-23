<?php

use App\Assessment\Actions\Omr\Canonical\CompileOmrCanonicalDraft;
use App\Assessment\Actions\Omr\Canonical\MaterializeOmrCanonicalDraft;
use App\Assessment\Actions\Omr\Verification\CreateOmrPhysicalChallenge;
use App\Assessment\Omr\Canonical\CanonicalArtifactMaterializer;
use App\Assessment\Omr\Canonical\OmrAuthoringDraftService;
use App\Assessment\Omr\Canonical\OmrCanonicalGeometryIdentity;
use App\Assessment\Omr\Canonical\OmrNoGoMatrixRunner;
use App\Assessment\Omr\Canonical\OmrTemplateAssetStore;
use App\Assessment\Omr\Diagnostics\CandidateArtifactDiagnosticTarget;
use App\Assessment\Omr\Diagnostics\OmrProofPlanner;
use App\Assessment\Omr\Diagnostics\ProofPurpose;
use App\Assessment\Omr\OmrCheckerDetection;
use App\Assessment\Omr\OmrDetectedItem;
use App\Assessment\Omr\OmrDetectionPort;
use App\Assessment\Omr\OmrDetectionRequest;
use App\Assessment\Omr\OmrDetectionResult;
use App\Assessment\Omr\OmrDetectionSuccess;
use App\Assessment\Omr\OmrLayoutVerificationPort;
use App\Assessment\Omr\OmrQrMode;
use App\Assessment\Omr\OmrRegistrationMode;
use App\Authorization\CapabilityCatalog;
use App\Models\Assessment\OmrAnswerSheetTemplate;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrMarkerProfile;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\Assessment\OmrTemplateVerificationCapture;
use App\Models\Assessment\OmrTemplateVerificationRun;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\Institution;
use App\Models\User;
use App\Printing\PrintRenderer;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

/** Tiny but decodable 1x1 PNG used as capture fixture bytes. */
function physicalChallengePng(): string
{
    return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
}

/** A4 single-page PDF for version-owned print assets. */
function physicalChallengePdfBytes(): string
{
    return app(PrintRenderer::class)->render(
        '<html><head><style>body{margin:0;padding:0}</style></head><body><div style="width:190mm;height:180mm;border:1px solid #ccc"></div></body></html>',
        ['format' => 'a4', 'orientation' => 'portrait'],
    );
}

/** Two-page PDF for exercising a page-specific physical capture. */
function physicalChallengeTwoPagePdfBytes(): string
{
    return app(PrintRenderer::class)->render(
        '<html><head><style>body{margin:0;padding:0}.page{width:190mm;height:180mm;border:1px solid #ccc;page-break-after:always}.page:last-child{page-break-after:auto}</style></head><body><div class="page"></div><div class="page"></div></body></html>',
        ['format' => 'a4', 'orientation' => 'portrait'],
    );
}

/** Build a tiny valid raster with controlled orientation dimensions. */
function physicalChallengeRaster(int $width, int $height): string
{
    $image = imagecreatetruecolor($width, $height);
    $background = imagecolorallocate($image, 245, 245, 245);
    imagefill($image, 0, 0, $background);
    ob_start();
    imagepng($image, null, 9, 0);
    $bytes = (string) ob_get_clean();
    imagedestroy($image);

    return $bytes;
}

function physicalChallengeLandscapePng(): string
{
    return physicalChallengeRaster(4, 3);
}

function physicalChallengePortraitPng(): string
{
    return physicalChallengeRaster(3, 4);
}

/** Qualified marker profile shared by every physical-challenge fixture. */
function physicalChallengeProfile(User $actor): OmrMarkerProfile
{
    return OmrMarkerProfile::query()->create([
        'code' => 'physical-challenge.marker.'.mt_rand(100000, 999999),
        'version' => 1,
        'family' => 'nexiam.fiducial',
        'asset_path' => 'app/Assessment/Omr/Fiducial/omr_marker.jpg',
        'asset_sha256' => hash_file('sha256', base_path('app/Assessment/Omr/Fiducial/omr_marker.jpg')),
        'size_mm' => 12,
        'quiet_zone_mm' => 3,
        'matching_config' => [],
        'status' => OmrMarkerProfile::STATUS_QUALIFIED,
        'qualification_evidence_hash' => hash('sha256', 'calibration'),
        'created_by_user_id' => $actor->id,
        'qualified_by_user_id' => $actor->id,
        'qualified_at' => now(),
    ]);
}

/**
 * Mapping-complete candidate draft with a null legacy verification block and a
 * complete durable digital pass. The durable run is the only physical
 * challenge gate; the legacy block remains intentionally stale/empty.
 *
 * `$pageCount` > 1 distributes one field block per page (LOGIC_Q_1..N).
 */
function physicalChallengeDraft(User $actor, int $pageCount = 1): OmrTemplateAuthoringDraft
{
    // Per-call nonce: identical fixture content would produce identical
    // artifact hashes and therefore identical (unique) challenge codes.
    $nonce = mt_rand(100000, 999999);
    $profile = physicalChallengeProfile($actor);
    $draft = app(OmrAuthoringDraftService::class)->create($actor, 'PHY-CHAL-'.strtoupper(substr(hash('sha256', $actor->id.$pageCount.$nonce), 0, 8)), 'Physical challenge draft', 'html', requestedPageCount: $pageCount);
    $blocks = [];
    for ($page = 1; $page <= $pageCount; $page++) {
        $blocks['Part_'.$page] = [
            'page_no' => $page,
            'fieldType' => 'QTYPE_MCQ4',
            'direction' => 'horizontal',
            'fieldLabels' => ['LOGIC_Q_'.$page],
            'bubblesGap' => 71,
            'labelsGap' => 80,
            'origin' => [295 + ($nonce % 7), 826],
            'bubbleDimensions' => [53, 53],
        ];
    }
    $draft->forceFill([
        'marker_profile_id' => $profile->id,
        'stage' => OmrTemplateAuthoringDraft::STAGE_MAPPING_COMPLETE,
        'authoring_json' => [
            'marker' => ['insets_mm' => ['top' => 12, 'right' => 12, 'bottom' => 14, 'left' => 12]],
            'canonical' => ['settings' => ['paper_width_mm' => 210, 'paper_height_mm' => 297]],
            'studio' => ['components' => [[
                'id' => 'Part_1', 'type' => 'mcq', 'name' => 'Part 1', 'subjectName' => 'General Ability',
                'posX' => 25, 'posY' => 70, 'w_mm' => 40, 'h_mm' => 50, 'questionsCount' => 1,
                'startQ' => 1, 'optionsCount' => 4, 'bubbleDiameterMm' => 4.5, 'bubblePitchMm' => 6, 'rowPitchMm' => 6.8,
            ]]],
            'pages' => array_map(static fn (int $page): array => ['page_no' => $page], range(1, $pageCount)),
            'mapping' => [
                'input' => ['page_width' => 2480, 'page_height' => 3508, 'page_count' => $pageCount],
                'field_blocks' => $blocks,
            ],
            'verification' => null,
        ],
    ])->save();

    $disk = (string) config('omr.canonical.storage.disk', 'local');
    config(['omr.canonical.storage.disk' => 'sensitive']);
    try {
        $draft = app(MaterializeOmrCanonicalDraft::class)
            ->handle($draft->fresh(['markerProfile', 'template']), $actor, (int) $draft->lock_version);
    } finally {
        config(['omr.canonical.storage.disk' => $disk]);
    }
    physicalChallengeSeedDigitalPass($draft->fresh(), $actor);

    return $draft->fresh();
}

/**
 * Seed a complete durable digital verification match for a physical challenge
 * fixture without writing legacy authoring verification JSON.
 */
function physicalChallengeSeedDigitalPass(
    OmrTemplateAuthoringDraft $draft,
    User $actor,
    ?string $artifactHash = null,
    array $failedVariants = [],
    array $missingVariants = [],
    ?string $mappingHashOverride = null,
): OmrTemplateVerificationRun {
    $authoring = (array) $draft->authoring_json;
    $artifactSet = (array) ($authoring['canonical']['artifact_set'] ?? []);
    $hash = $artifactHash ?? (string) ($artifactSet['artifact_hash'] ?? $authoring['canonical']['artifact_hash'] ?? hash('sha256', 'artifact'));
    $mappingHash = (string) ($mappingHashOverride ?? $artifactSet['geometry_identity'] ?? $artifactSet['mapping_hash'] ?? '');
    if ($mappingHash === '') {
        try {
            $mappingHash = OmrCanonicalGeometryIdentity::forDraft($draft);
        } catch (Throwable) {
            $mappingHash = hash('sha256', 'mapping');
        }
    }
    $plan = null;
    if ($artifactHash === null && $mappingHashOverride === null && isset($artifactSet['template'], $artifactSet['marker_asset_id'])) {
        $profile = $draft->markerProfile;
        if ($profile !== null) {
            $artifact = app(CanonicalArtifactMaterializer::class)->loadPersisted($draft, $profile);
            $target = new CandidateArtifactDiagnosticTarget($draft, $artifact, $profile);
            $plan = app(OmrProofPlanner::class)->plan($target, ProofPurpose::Digital);
            $hash = $artifact->artifactHash;
            $mappingHash = $artifact->geometryIdentity;
        }
    }
    $planVersion = $plan?->planVersion ?? 'v1';
    $planHash = $plan?->planHash() ?? hash('sha256', 'plan:'.$hash);
    $expectedVectorHash = $plan?->expectedVectorHash ?? hash('sha256', 'expected_vector:'.$hash);
    $nativeBundleHash = $plan?->targetHashes['native_bundle'] ?? hash('sha256', 'native_bundle:'.$hash);
    $manifestHash = $plan?->targetHashes['manifest'] ?? hash('sha256', 'manifest:'.$hash);
    $configHash = $plan?->targetHashes['config'] ?? hash('sha256', 'config:'.$hash);
    $variants = OmrNoGoMatrixRunner::variantKeys((int) $draft->requested_page_count);
    $variantSummary = [];
    foreach ($variants as $variant) {
        $baseVariant = OmrNoGoMatrixRunner::parseVariantKey($variant)['variant'];
        $status = in_array($variant, $failedVariants, true) || in_array($baseVariant, $failedVariants, true) ? 'fail' : 'pass';
        $variantSummary[$variant] = ['status' => $status, 'expected' => 'pass', 'actual' => $status];
    }

    $proofHash = hash('sha256', 'proof:'.$hash);
    $run = OmrTemplateVerificationRun::query()->create([
        'draft_id' => $draft->id,
        'mode' => OmrTemplateVerificationRun::MODE_DIGITAL,
        'status' => OmrTemplateVerificationRun::STATUS_PASSED,
        'attempt' => 1,
        'plan_version' => $planVersion,
        'plan_hash' => $planHash,
        'expected_vector_hash' => $expectedVectorHash,
        'clean_vector_hash' => hash('sha256', 'clean_vector:'.$hash),
        'proof_hash' => $proofHash,
        'artifact_hash' => $hash,
        'native_bundle_hash' => $nativeBundleHash,
        'manifest_hash' => $manifestHash,
        'config_hash' => $configHash,
        'mapping_hash' => $mappingHash,
        'progress_done' => count($variants),
        'progress_total' => count($variants),
        'result_summary' => [
            'matrix_hash' => hash('sha256', json_encode($variantSummary, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
            'variants' => $variantSummary,
            'proof_hash' => $proofHash,
            'expected_vector_hash' => $expectedVectorHash,
            'clean_vector_hash' => hash('sha256', 'clean_vector:'.$hash),
            'threshold_status' => 'Unqualified',
        ],
        'started_by_user_id' => $actor->id,
        'started_at' => now(),
        'finished_at' => now(),
    ]);

    for ($pageNo = 1; $pageNo <= (int) $draft->requested_page_count; $pageNo++) {
        app(OmrTemplateAssetStore::class)->putDraftAsset(
            $draft,
            OmrTemplateAsset::KIND_DIGITAL_PROOF,
            'dummy proof png bytes page '.$pageNo,
            'image/png',
            $pageNo,
            ['run_id' => $run->id, 'attempt' => 1, 'proof_hash' => $proofHash],
        );
    }

    foreach ($variants as $variant) {
        $variantSpec = OmrNoGoMatrixRunner::parseVariantKey($variant);
        $baseVariant = $variantSpec['variant'];
        if (in_array($variant, $missingVariants, true) || in_array($baseVariant, $missingVariants, true)) {
            continue;
        }
        $status = in_array($variant, $failedVariants, true) || in_array($baseVariant, $failedVariants, true) ? 'fail' : 'pass';
        OmrTemplateVerificationCapture::query()->create([
            'run_id' => $run->id,
            'attempt' => 1,
            'page_no' => $variantSpec['page_no'],
            'mode' => OmrTemplateVerificationRun::MODE_DIGITAL,
            'variant' => $variant,
            'expected' => 'pass',
            'actual' => $status,
            'status' => $status,
            'input_hash' => hash('sha256', $variant.':input'),
            'evidence_hash' => hash('sha256', $variant.':evidence'),
            'vector_match' => $status === 'pass',
            'captured_by_user_id' => $actor->id,
            'captured_at' => now(),
        ]);
    }

    return $run;
}

/**
 * Materialized marker-free document-bound candidate with a structurally
 * complete digital-only verification fixture. This helper never creates
 * physical captures, printer/device attestations, or qualification state.
 */
function physicalChallengeDocumentBoundDraft(User $actor, int $pageCount = 1): OmrTemplateAuthoringDraft
{
    $nonce = mt_rand(100000, 999999);
    $fieldBlocks = [];
    for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
        $fieldBlocks['Part_'.$pageNo] = [
            'page_no' => $pageNo,
            'fieldType' => 'QTYPE_MCQ4',
            'direction' => 'horizontal',
            'fieldLabels' => ['LOGIC_Q_'.$pageNo],
            'bubblesGap' => 71,
            'labelsGap' => 80,
            'origin' => [295 + ($nonce % 7), 826],
            'bubbleDimensions' => [53, 53],
        ];
    }
    $authoring = [
        'pages' => array_map(static fn (int $pageNo): array => ['page_no' => $pageNo, 'confirmed' => true], range(1, $pageCount)),
        'canonical' => ['settings' => ['paper_width_mm' => 210, 'paper_height_mm' => 297]],
        'mapping' => [
            'input' => ['page_width' => 2480, 'page_height' => 3508, 'page_count' => $pageCount],
            'field_blocks' => $fieldBlocks,
        ],
        'verification' => null,
    ];
    $draft = app(OmrAuthoringDraftService::class)->create(
        $actor,
        'PHY-DOC-'.strtoupper(substr(hash('sha256', $actor->id.$pageCount.$nonce), 0, 8)),
        'Document-bound physical challenge draft',
        OmrTemplateAuthoringDraft::SOURCE_HTML,
        $authoring,
        OmrRegistrationMode::DocumentBound,
        OmrQrMode::Disabled,
        $pageCount,
    );
    $draft->forceFill(['stage' => OmrTemplateAuthoringDraft::STAGE_MAPPING_COMPLETE])->save();

    $disk = (string) config('omr.canonical.storage.disk', 'local');
    config(['omr.canonical.storage.disk' => 'sensitive']);
    try {
        $draft = app(MaterializeOmrCanonicalDraft::class)->handle(
            $draft->fresh(['markerProfile', 'template']),
            $actor,
            (int) $draft->lock_version,
        );
    } finally {
        config(['omr.canonical.storage.disk' => $disk]);
    }

    physicalChallengeSeedDocumentBoundDigitalPass($draft->fresh(['assets', 'template']), $actor);

    return $draft->fresh();
}

/**
 * Compile the document-bound digital fixture through the ordinary compile action.
 */
function physicalChallengeDocumentBoundCompiledDraft(User $actor, int $pageCount = 1): OmrTemplateAuthoringDraft
{
    AptitudeArea::query()->updateOrCreate(
        ['code' => 'LOGIC'],
        ['name' => 'Logic', 'max_items' => $pageCount, 'display_order' => 1, 'is_active' => true],
    );

    $draft = physicalChallengeDocumentBoundDraft($actor, $pageCount);

    return app(CompileOmrCanonicalDraft::class)->handle($draft, $actor)->fresh(['compiledVersion', 'assets']);
}

/**
 * Persist a complete synthetic digital matrix fixture for document-bound
 * challenge-path tests; it never represents real printer/device evidence.
 */
function physicalChallengeSeedDocumentBoundDigitalPass(
    OmrTemplateAuthoringDraft $draft,
    User $actor,
): OmrTemplateVerificationRun {
    $artifact = app(CanonicalArtifactMaterializer::class)->loadPersisted($draft, null);
    $target = new CandidateArtifactDiagnosticTarget($draft, $artifact, null);
    $plan = app(OmrProofPlanner::class)->plan($target, ProofPurpose::Digital);
    $variants = OmrNoGoMatrixRunner::variantKeys($draft->requested_page_count, OmrRegistrationMode::DocumentBound);
    $acceptedWarp = [
        'attempted' => true,
        'applied' => true,
        'mode' => OmrRegistrationMode::DocumentBound->value,
        'status' => 'success',
        'plausible_candidate_count' => 1,
        'selected_candidate_index' => 0,
        'homography_count' => 1,
        'warp_count' => 1,
        'document_gates' => ['accepted' => true],
        'content_gate' => ['accepted' => true],
        'homography_hash' => 'sha256:'.str_repeat('a', 64),
        'quad_normalized' => [[0.0, 0.0], [1.0, 0.0], [1.0, 1.0], [0.0, 1.0]],
    ];
    $variantSummary = [];
    $captureRows = [];
    foreach ($variants as $variant) {
        $variantSpec = OmrNoGoMatrixRunner::parseVariantKey($variant, OmrRegistrationMode::DocumentBound);
        $exactRecovery = OmrNoGoMatrixRunner::expectsExactRecovery(OmrRegistrationMode::DocumentBound, $variantSpec['variant']);
        if ($exactRecovery) {
            $rectification = [
                'layout' => $acceptedWarp,
                'detection' => $acceptedWarp,
                'accepted' => true,
                'same_selected_warp' => true,
            ];
            $workerCodes = null;
            $expected = 'pass';
            $vectorMatch = true;
        } else {
            $allowedCodes = OmrNoGoMatrixRunner::expectedDocumentRejectionCodes($variantSpec['variant']);
            $workerCodes = [
                'layout' => $allowedCodes[0],
                'detection' => $allowedCodes[1] ?? $allowedCodes[0],
            ];
            $rejectedWarp = [
                'attempted' => true,
                'mode' => OmrRegistrationMode::DocumentBound->value,
                'document_gates' => ['accepted' => false],
            ];
            $rectification = [
                'layout' => $rejectedWarp,
                'detection' => $rejectedWarp,
                'accepted' => false,
                'same_selected_warp' => false,
            ];
            $expected = 'typed_rejection';
            $vectorMatch = null;
        }
        $variantSummary[$variant] = [
            'status' => 'pass',
            'expected' => $expected,
            'actual' => $expected,
            'registration_mode' => OmrRegistrationMode::DocumentBound->value,
            'rectification' => $rectification,
            'worker_codes' => $workerCodes,
        ];
        $captureRows[] = [
            'variant' => $variant,
            'page_no' => $variantSpec['page_no'],
            'expected' => $expected,
            'actual' => $expected,
            'vector_match' => $vectorMatch,
            'summary' => [
                'registration_mode' => OmrRegistrationMode::DocumentBound->value,
                'rectification' => $rectification,
                'worker_codes' => $workerCodes,
            ],
        ];
    }

    $matrixHash = hash('sha256', json_encode($variantSummary, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    $proofHash = hash('sha256', 'document-bound-digital-fixture:'.$artifact->artifactHash);
    $cleanVectorHash = $plan->expectedVectorHash;
    $run = OmrTemplateVerificationRun::query()->create([
        'draft_id' => $draft->id,
        'mode' => OmrTemplateVerificationRun::MODE_DIGITAL,
        'status' => OmrTemplateVerificationRun::STATUS_PASSED,
        'attempt' => 1,
        'plan_version' => $plan->planVersion,
        'plan_hash' => $plan->planHash(),
        'expected_vector_hash' => $plan->expectedVectorHash,
        'clean_vector_hash' => $cleanVectorHash,
        'proof_hash' => $proofHash,
        'artifact_hash' => $plan->targetHashes['artifact'],
        'native_bundle_hash' => $plan->targetHashes['native_bundle'],
        'manifest_hash' => $plan->targetHashes['manifest'],
        'config_hash' => $plan->targetHashes['config'],
        'mapping_hash' => $plan->targetHashes['mapping'],
        'progress_done' => count($variants),
        'progress_total' => count($variants),
        'result_summary' => [
            'matrix_hash' => $matrixHash,
            'variants' => $variantSummary,
            'proof_hash' => $proofHash,
            'expected_vector_hash' => $plan->expectedVectorHash,
            'clean_vector_hash' => $cleanVectorHash,
            'threshold_status' => 'Unqualified',
        ],
        'started_by_user_id' => $actor->id,
        'started_at' => now(),
        'finished_at' => now(),
    ]);

    for ($pageNo = 1; $pageNo <= (int) $draft->requested_page_count; $pageNo++) {
        app(OmrTemplateAssetStore::class)->putDraftAsset(
            $draft,
            OmrTemplateAsset::KIND_DIGITAL_PROOF,
            'digital-only document-bound proof fixture page '.$pageNo,
            'image/png',
            $pageNo,
            ['run_id' => $run->id, 'attempt' => 1, 'proof_hash' => $proofHash],
        );
    }
    foreach ($captureRows as $row) {
        OmrTemplateVerificationCapture::query()->create([
            'run_id' => $run->id,
            'attempt' => 1,
            'page_no' => $row['page_no'],
            'mode' => OmrTemplateVerificationRun::MODE_DIGITAL,
            'variant' => $row['variant'],
            'expected' => $row['expected'],
            'actual' => $row['actual'],
            'status' => OmrTemplateVerificationCapture::STATUS_PASS,
            'input_hash' => hash('sha256', $row['variant'].':input'),
            'evidence_hash' => hash('sha256', $row['variant'].':evidence'),
            'vector_match' => $row['vector_match'],
            'summary' => $row['summary'],
            'captured_by_user_id' => $actor->id,
            'captured_at' => now(),
        ]);
    }

    return $run;
}

/**
 * Marker-bearing compiled version + on-disk native bundle + version-owned
 * print PDF. The fixture pins paper 210x297 mm with 12/12/14/12 mm insets,
 * so the expected marker-center rectangle is 186 x 271 mm.
 */
function physicalChallengeVersion(User $actor): OmrAnswerSheetTemplateVersion
{
    $profile = physicalChallengeProfile($actor);
    $template = OmrAnswerSheetTemplate::query()->create(['code' => 'PHY-VERSION-'.strtoupper(substr(hash('sha256', $actor->id), 0, 8)), 'name' => 'Physical version']);
    $version = OmrAnswerSheetTemplateVersion::query()->create([
        'template_id' => $template->id,
        'version' => 1,
        'status' => OmrAnswerSheetTemplateVersion::STATUS_DRAFT,
        'page_width' => physicalChallengeFixtureFrame()[0],
        'page_height' => physicalChallengeFixtureFrame()[1],
        'identity_regions_json' => [],
        'provider_config_json' => [],
        'config_hash' => hash('sha256', 'config'),
        'template_json_hash' => hash('sha256', 'template'),
        'native_bundle_version' => 'nexiam.omr.native-bundle.v1',
        'native_bundle_json' => [
            'template' => [
                'bubbleDimensions' => [28, 28],
                'pages' => [[
                    'page_no' => 1,
                    'pageDimensions' => physicalChallengeFixtureFrame(),
                    'fieldBlocks' => ['Questions' => [
                        'fieldType' => 'QTYPE_MCQ4',
                        'direction' => 'horizontal',
                        'fieldLabels' => ['LOGIC_Q_1..4'],
                        'bubblesGap' => 71,
                        'labelsGap' => 80,
                        'origin' => [295, 826],
                        'bubbleDimensions' => [53, 53],
                    ]],
                    'preProcessors' => [['name' => 'CropOnMarkers', 'options' => ['relativePath' => 'marker.jpg']]],
                    'markers' => [
                        'family' => 'nexiam.fiducial',
                        'asset_name' => 'marker.jpg',
                        'size' => 142,
                        'quiet_zone' => 36,
                        'inset' => 142,
                        'paper' => [2480, 3508],
                        'centers' => [[0, 0], [physicalChallengeFixtureFrame()[0], 0], [0, physicalChallengeFixtureFrame()[1]], [physicalChallengeFixtureFrame()[0], physicalChallengeFixtureFrame()[1]]],
                        'quadrants' => [[0, 0], [1, 0], [0, 1], [1, 1]],
                    ],
                ]],
            ],
            'manifest' => [
                'bundle_version' => 'nexiam.omr.native-bundle.v1',
                'template_code' => $template->code,
                'template_version' => 1,
                'synthetic' => true,
                'engine' => ['name' => 'OMRChecker', 'commit' => 'de3f6982822895491be760325fd43e65e90fa293', 'patch_set' => 'nexiam-headless-overlay-v1'],
                'marker_profile' => [
                    'id' => $profile->id,
                    'code' => $profile->code,
                    'version' => $profile->version,
                    'asset_sha256' => $profile->asset_sha256,
                    'status' => 'qualified',
                ],
            ],
        ],
        'native_bundle_hash' => hash('sha256', 'bundle'),
        'engine_name' => 'OMRChecker',
        'engine_commit' => 'test',
        'patch_identity' => 'nexiam-headless-overlay-v1',
        'review_manifest_json' => physicalChallengeReviewManifest(),
        'review_manifest_hash' => hash('sha256', 'review'),
        'authoring_manifest' => [
            'marker_profile' => ['id' => $profile->id, 'code' => $profile->code, 'version' => $profile->version, 'asset_sha256' => $profile->asset_sha256],
            'paper' => ['width_mm' => 210, 'height_mm' => 297],
            'marker_insets_mm' => ['top' => 12, 'right' => 12, 'bottom' => 14, 'left' => 12],
            'artifact_set' => ['artifact_hash' => str_repeat('a', 64)],
            'mapping_hash' => str_repeat('z', 64),
            'page_count' => 1,
        ],
    ]);

    physicalChallengeWriteBundle($template->code, 1);
    app(OmrTemplateAssetStore::class)->putVersionAsset(
        $version,
        OmrTemplateAsset::KIND_PRINT_PDF,
        physicalChallengePdfBytes(),
        'application/pdf',
        null,
        ['role' => 'print_authority'],
    );

    return $version->fresh();
}

/** @return array{manifest_version: string, bundle_hash: string, fields: list<array<string, mixed>>} */
function physicalChallengeReviewManifest(): array
{
    $fields = [];
    foreach (range(1, 4) as $no) {
        foreach (['A', 'B', 'C', 'D'] as $index => $option) {
            $fields[] = [
                'page' => 1,
                'label' => 'LOGIC_Q_'.$no,
                'option' => $option,
                'region' => ['x' => 0.2 + $no * 0.05 + $index * 0.03, 'y' => 0.2 + $no * 0.05, 'w' => 0.02, 'h' => 0.02],
                // ManifestBubbleShader consumes frame-relative pixel geometry.
                'pixel' => [
                    'x' => 375 + ($index * 71),
                    'y' => 826 + (($no - 1) * 71),
                    'w' => 53,
                    'h' => 53,
                ],
            ];
        }
    }

    return [
        'manifest_version' => 'nexiam.omr.review-manifest.v1',
        'bundle_hash' => str_repeat('b', 64),
        'fields' => $fields,
    ];
}

/** Write a marker-bearing native bundle to the durable bundle directory. */
function physicalChallengeWriteBundle(string $code, int $version): void
{
    $markerBytes = (string) file_get_contents(base_path('app/Assessment/Omr/Fiducial/omr_marker.jpg'));
    $templateJson = [
        'bubbleDimensions' => [28, 28],
        'pages' => [[
            'page_no' => 1,
            'pageDimensions' => physicalChallengeFixtureFrame(),
            'fieldBlocks' => ['Questions' => [
                'fieldType' => 'QTYPE_MCQ4',
                'direction' => 'horizontal',
                'fieldLabels' => ['LOGIC_Q_1..4'],
                'bubblesGap' => 71,
                'labelsGap' => 80,
                'origin' => [295, 826],
                'bubbleDimensions' => [53, 53],
            ]],
            'preProcessors' => [['name' => 'CropOnMarkers', 'options' => ['relativePath' => 'marker.jpg']]],
            'markers' => [
                'family' => 'nexiam.fiducial',
                'asset_name' => 'marker.jpg',
                'size' => 142,
                'quiet_zone' => 36,
                'inset' => 142,
                'paper' => [2480, 3508],
                'centers' => [[0, 0], [physicalChallengeFixtureFrame()[0], 0], [0, physicalChallengeFixtureFrame()[1]], [physicalChallengeFixtureFrame()[0], physicalChallengeFixtureFrame()[1]]],
                'quadrants' => [[0, 0], [1, 0], [0, 1], [1, 1]],
            ],
        ]],
    ];
    $frame = physicalChallengeFixtureFrame();
    $configJson = ['dimensions' => ['display_width' => $frame[0], 'display_height' => $frame[1]]];
    $referenceSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="'.$frame[0].'" height="'.$frame[1].'"></svg>';
    $files = [
        'template.json' => json_encode($templateJson, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        'config.json' => json_encode($configJson, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        'reference.svg' => $referenceSvg,
        'marker.jpg' => $markerBytes,
    ];
    $manifest = [
        'bundle_version' => 'nexiam.omr.native-bundle.v1',
        'template_code' => $code,
        'template_version' => $version,
        'synthetic' => true,
        'engine' => ['name' => 'OMRChecker', 'commit' => 'de3f6982822895491be760325fd43e65e90fa293', 'patch_set' => 'nexiam-headless-overlay-v1'],
        'files' => array_map(fn (string $bytes): string => hash('sha256', $bytes), $files),
    ];
    $files['manifest.json'] = json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

    $dir = Storage::disk('local')->path('omr-bundles/'.$code.'/v'.$version);
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    foreach ($files as $name => $bytes) {
        file_put_contents($dir.'/'.$name, $bytes);
    }
}

/** Marker-centre frame for the 210x297 mm fixture with 12/12/14/12 mm insets. */
function physicalChallengeFixtureFrame(): array
{
    // The materializer computes paper_px - insets_px where each mm value is
    // rounded to pixels first: 2480 - 142 - 142 = 2196, 3508 - 142 - 165 = 3201.
    return [2196, 3201];
}

/** Extract the request manifest from a signed multipart worker call. */
function physicalChallengeManifestFromBody(string $body): array
{
    if (! str_contains($body, 'name="manifest"')) {
        return [];
    }

    $parts = preg_split("/\r\n--[A-Za-z0-9._-]+/", $body);
    foreach ($parts as $part) {
        if (! str_contains($part, 'name="manifest"')) {
            continue;
        }
        [, $json] = explode("\r\n\r\n", $part, 2);
        $decoded = json_decode(trim($json), true);

        return is_array($decoded) ? $decoded : [];
    }

    return [];
}

/** Signed multipart layout-verification worker response (mirrors the durable-run fake). */
function physicalChallengeWorkerResponse(string $runId, bool $success, string $requestBody, array $overrides = []): array
{
    if (! $success) {
        return [json_encode(['code' => $overrides['code'] ?? 'marker_missing', 'detail' => $overrides['detail'] ?? 'fixture rejection'], JSON_THROW_ON_ERROR), ['Content-Type' => 'application/json']];
    }
    // The gate checks the aligned image against the marker-centre FRAME
    // (paper minus insets), so the fake must answer with the exact frame.
    $manifest = physicalChallengeManifestFromBody($requestBody);
    $targetIdentity = array_key_exists('template_version_id', $manifest)
        ? ['template_version_id' => $manifest['template_version_id']]
        : ['candidate_target_id' => $manifest['candidate_target_id']];
    $frame = physicalChallengeFixtureFrame();
    $payload = array_merge([
        'contract_version' => OmrDetectionRequest::ENVELOPE_VERSION,
        'registration_mode' => $manifest['registration_mode'],
        ...$targetIdentity,
        'expected_page_no' => $manifest['expected_page_no'],
        'source_image' => $manifest['source_image'],
        'run_id' => $runId,
        'correlation_id' => $manifest['correlation_id'],
        'outcome' => 'succeeded',
        'engine' => [
            'name' => $manifest['engine']['name'],
            'version' => 'de3f69828228',
            'commit' => $manifest['engine']['commit'],
            'patch_identity' => $manifest['engine']['patch_identity'],
        ],
        'input_hash' => $manifest['input_hash'],
        'native_bundle_hash' => $manifest['native_bundle_hash'],
        'manifest_hash' => $manifest['manifest_hash'],
        'config_hash' => $manifest['config_hash'],
        'booklet_hash' => $manifest['booklet_hash'],
        'image' => [
            'mime' => $manifest['image_mime'],
            'width' => $manifest['image_width'],
            'height' => $manifest['image_height'],
        ],
        'calibration_status' => 'unqualified',
        'aligned_image' => ['mime' => 'image/png', 'width' => $frame[0], 'height' => $frame[1]],
        'layout_overlay' => ['mime' => 'image/png', 'width' => $frame[0], 'height' => $frame[1]],
        'marker_matches' => ['quarter_1' => .9, 'quarter_2' => .9, 'quarter_3' => .9, 'quarter_4' => .9],
        'field_block_shifts' => [],
        'bubble_means' => [],
        'item_evidence' => [],
        'timings' => ['preprocess_ms' => 1.0, 'verification_ms' => 2.0],
    ], $overrides);
    $resultBytes = json_encode($payload, JSON_THROW_ON_ERROR);
    $raster = physicalChallengePng();
    $boundary = 'physical-challenge-worker';
    $body = '--'.$boundary."\r\n"
        ."Content-Disposition: form-data; name=\"result\"\r\nContent-Type: application/json\r\n\r\n".$resultBytes."\r\n"
        .'--'.$boundary."\r\n"
        ."Content-Disposition: form-data; name=\"aligned_image\"; filename=\"aligned.png\"\r\nContent-Type: image/png\r\n\r\n".$raster."\r\n"
        .'--'.$boundary."\r\n"
        ."Content-Disposition: form-data; name=\"layout_overlay\"; filename=\"overlay.png\"\r\nContent-Type: image/png\r\n\r\n".$raster."\r\n"
        .'--'.$boundary."--\r\n";
    $timestamp = (string) time();
    $signature = hash_hmac('sha256', implode("\n", [$timestamp, $runId, hash('sha256', $resultBytes), hash('sha256', $raster), hash('sha256', $raster)]), (string) config('omr.omrchecker.signing_secret'));

    return [$body, [
        'Content-Type' => 'multipart/form-data; boundary='.$boundary,
        'X-Nexiam-Contract' => OmrDetectionRequest::ENVELOPE_VERSION,
        'X-Nexiam-Run-Id' => $runId,
        'X-Nexiam-Timestamp' => $timestamp,
        'X-Nexiam-Result-SHA256' => hash('sha256', $resultBytes),
        'X-Nexiam-Aligned-SHA256' => hash('sha256', $raster),
        'X-Nexiam-Layout-Overlay-SHA256' => hash('sha256', $raster),
        'X-Nexiam-Signature' => $signature,
    ]];
}

/** Fake the layout-verification worker; returns the number of calls made. */
function physicalChallengeFakeWorker(bool $success = true, array $overrides = []): int
{
    config([
        'omr.provider.driver' => 'omrchecker',
        'omr.omrchecker.enabled' => true,
    ]);
    // The production container shares one adapter instance for detection and
    // layout verification; this helper overrides detection with a
    // detection-only fake, so bind the real layout adapter explicitly.
    app()->instance(
        OmrLayoutVerificationPort::class,
        new OmrCheckerDetection,
    );
    $calls = 0;
    Http::fake(['*/layout-verification' => function ($request) use (&$calls, $success, $overrides) {
        $calls++;
        $effectiveOverrides = $overrides;
        if ($success && ! array_key_exists('marker_matches', $effectiveOverrides)) {
            // The pipeline evaluates all four candidates; deterministic score
            // ordering gives it one winner instead of a synthetic tie.
            $score = 0.90 + ((($calls - 1) % 4) * 0.01);
            $effectiveOverrides['marker_matches'] = array_fill_keys(
                ['quarter_1', 'quarter_2', 'quarter_3', 'quarter_4'],
                $score
            );
        }
        [$body, $headers] = physicalChallengeWorkerResponse(
            (string) ($request->header('X-Nexiam-Run-Id')[0] ?? ''),
            $success,
            $request->body(),
            $effectiveOverrides,
        );

        return Http::response($body, $success ? 200 : 422, $headers);
    }]);

    return $calls;
}

/**
 * Fake layout verification responses in call order. Each physical capture
 * evaluates four quarter-turn candidates, so callers can preserve a failed
 * first capture and then exercise a later selected pass.
 *
 * @param  list<bool>  $successes
 * @param  list<array<string, mixed>>  $overridesByCall
 */
function physicalChallengeFakeWorkerSequence(array $successes, array $overridesByCall = []): void
{
    config([
        'omr.provider.driver' => 'omrchecker',
        'omr.omrchecker.enabled' => true,
    ]);
    // Keep the detection-only fake separate from the authenticated layout
    // adapter while exercising the shared worker transport.
    app()->instance(
        OmrLayoutVerificationPort::class,
        new OmrCheckerDetection,
    );
    $calls = 0;
    Http::swap(new Factory);
    Http::fake(['*/layout-verification' => function ($request) use (&$calls, $successes, $overridesByCall) {
        $index = $calls++;
        $success = (bool) ($successes[$index] ?? true);
        $effectiveOverrides = is_array($overridesByCall[$index] ?? null)
            ? $overridesByCall[$index]
            : [];
        if ($success && ! array_key_exists('marker_matches', $effectiveOverrides)) {
            $score = 0.90 + (($index % 4) * 0.01);
            $effectiveOverrides['marker_matches'] = array_fill_keys(
                ['quarter_1', 'quarter_2', 'quarter_3', 'quarter_4'],
                $score
            );
        }
        [$body, $headers] = physicalChallengeWorkerResponse(
            (string) ($request->header('X-Nexiam-Run-Id')[0] ?? ''),
            $success,
            $request->body(),
            $effectiveOverrides,
        );

        return Http::response($body, $success ? 200 : 422, $headers);
    }]);
}

/**
 * Detector fake: marks the plan-expected option per label. `mode`:
 * exact (expected options), mismatch/ambiguous/missing (one item degraded).
 */
final class PhysicalCaptureDetectorFake implements OmrDetectionPort
{
    /** @param array<string, string> $expectedByLabel */
    public function __construct(
        private readonly array $expectedByLabel = [],
        private readonly string $mode = 'exact',
    ) {}

    public function detect(OmrDetectionRequest $request): OmrDetectionResult
    {
        $items = [];
        $degraded = false;
        foreach ($request->items as $geometry) {
            $hasExpected = array_key_exists($geometry->nativeLabel, $this->expectedByLabel);
            $expected = $this->expectedByLabel[$geometry->nativeLabel] ?? $geometry->options[0]->code ?? 'A';
            $selected = $hasExpected ? $expected : null;
            $state = $hasExpected ? OmrDetectedItem::STATE_MARKED : OmrDetectedItem::STATE_BLANK;
            if (! $degraded && $this->mode === 'mismatch' && count($geometry->options) > 1) {
                $selected = $geometry->options[0]->code === $expected
                    ? ($geometry->options[1]->code ?? $expected)
                    : $geometry->options[0]->code;
                $degraded = true;
            }
            if (! $degraded && $this->mode === 'ambiguous') {
                $state = OmrDetectedItem::STATE_AMBIGUOUS;
                $selected = null;
                $degraded = true;
            }
            if (! $degraded && $this->mode === 'missing') {
                $state = OmrDetectedItem::STATE_BLANK;
                $selected = null;
                $degraded = true;
            }
            $observedOptions = $state === OmrDetectedItem::STATE_AMBIGUOUS
                ? array_map(static fn ($option): string => $option->code, array_slice($geometry->options, 0, 2))
                : null;
            $items[] = OmrDetectedItem::fromGeometry(
                geometry: $geometry,
                selectedOption: $selected,
                state: $state,
                confidence: null,
                observedOptions: $observedOptions,
                issueCodes: $state === OmrDetectedItem::STATE_AMBIGUOUS ? ['ambiguous_mark'] : [],
            );
        }

        return OmrDetectionResult::succeeded(new OmrDetectionSuccess(
            engineName: $request->engineName,
            engineVersion: 'test',
            engineCommit: $request->engineCommit,
            inputHash: $request->inputHash,
            configHash: $request->configHash,
            applicantNumberProposal: null,
            applicantNumberConfidence: null,
            identityFields: [],
            items: $items,
            issues: [],
            sheetConfidence: null,
            responseEnvelopeVersion: $request->envelopeVersion,
            runId: $request->runId,
            correlationId: $request->correlationId,
            bookletHash: $request->bookletHash,
            nativeBundleHash: $request->nativeBundleHash,
            manifestHash: $request->manifestHash,
            patchIdentity: $request->patchIdentity,
            registrationMode: $request->registrationMode->value,
            templateVersionId: $request->templateVersionId,
            candidateTargetId: $request->candidateTargetId,
            expectedPageNo: $request->pageNo,
            sourceImage: $request->sourceImage->toArray(),
            calibrationStatus: 'unqualified',
            fieldBlockShifts: [],
            imageMime: $request->imageMime,
            imageWidth: $request->imageWidth,
            imageHeight: $request->imageHeight,
        ));
    }
}

/** Build the expected label=>option map a planner would produce for a target. */
function physicalChallengeExpectedVector(object $target, string $purpose = 'quick_rehearsal'): array
{
    $plan = app(OmrProofPlanner::class)->plan(
        $target,
        ProofPurpose::from($purpose),
    );

    return $plan->expectedVector;
}

/** Create a physical challenge run directly through the action (quick mode). */
function physicalChallengeQuickRun(User $actor, OmrTemplateAuthoringDraft $draft): OmrTemplateVerificationRun
{
    $result = app(CreateOmrPhysicalChallenge::class)->handle($draft, $actor, 'quick');

    return $result['run'];
}

/** Capability-granted staff actor for challenge/capture flows. */
function physicalChallengeActor(): User
{
    $institution = Institution::query()->first() ?? Institution::query()->create(['code' => 'SYN-INST-001', 'name' => 'Synthetic Institution']);
    $user = User::factory()->create();
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_SCORING_OMR, 'web');
    $user->givePermissionTo(CapabilityCatalog::GUIDANCE_SCORING_OMR);
    $user->staffProfile()->create([
        'institution_id' => $institution->id,
        'office' => 'guidance',
    ]);
    StaffAuthenticationState::query()->create([
        'user_id' => $user->id,
        'session_version' => 1,
        'password_set_at' => now(),
        'two_factor_generation' => (string) Str::uuid(),
        'activation_completed_at' => now(),
        'two_factor_confirmed_at' => now(),
        'two_factor_secret' => 'TESTSECRET2FA',
        'must_change_password' => false,
    ]);

    return $user;
}
