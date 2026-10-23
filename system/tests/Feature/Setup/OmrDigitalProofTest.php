<?php

use App\Assessment\Actions\Omr\Canonical\MaterializeOmrCanonicalDraft;
use App\Assessment\Actions\Omr\Verification\StartOmrTemplateVerification;
use App\Assessment\Omr\Canonical\CanonicalArtifactMaterializer;
use App\Assessment\Omr\Canonical\OmrAuthoringDraftException;
use App\Assessment\Omr\Canonical\OmrAuthoringDraftService;
use App\Assessment\Omr\Canonical\OmrDigitalProofGenerator;
use App\Assessment\Omr\Canonical\OmrNoGoMatrixRunner;
use App\Assessment\Omr\Diagnostics\CandidateArtifactDiagnosticTarget;
use App\Assessment\Omr\Diagnostics\OmrDiagnosticTargetException;
use App\Assessment\Omr\Diagnostics\OmrManifestBubbleShader;
use App\Assessment\Omr\Diagnostics\OmrProofPlanner;
use App\Assessment\Omr\Diagnostics\OmrTemplateDiagnosticRunner;
use App\Assessment\Omr\Diagnostics\ProofPurpose;
use App\Assessment\Omr\OmrCheckerDetection;
use App\Assessment\Omr\OmrDetectedItem;
use App\Assessment\Omr\OmrDetectionPort;
use App\Assessment\Omr\OmrDetectionRequest;
use App\Assessment\Omr\OmrDetectionResult;
use App\Assessment\Omr\OmrDetectionSuccess;
use App\Assessment\Omr\OmrDiagnosticRequestBuilder;
use App\Assessment\Omr\OmrLayoutVerificationPort;
use App\Assessment\Omr\OmrQrMode;
use App\Assessment\Omr\OmrRegistrationMode;
use App\Assessment\Omr\Verification\CurrentOmrDigitalVerification;
use App\Assessment\Omr\Verification\OmrTemplateVerificationStatus;
use App\Assessment\Omr\Verification\OmrVerificationTargetLoader;
use App\Jobs\Assessment\RunOmrTemplateVerificationJob;
use App\Jobs\Assessment\RunOmrTemplateVerificationVariantJob;
use App\Models\Assessment\OmrMarkerProfile;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\Assessment\OmrTemplateVerificationRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

/** Extract the request manifest from a signed multipart worker call. */
function digitalProofManifestFromBody(string $body): array
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

function digitalProofRaster(int $width, int $height): string
{
    static $rasters = [];
    $key = $width.'x'.$height;
    if (isset($rasters[$key])) {
        return $rasters[$key];
    }
    $image = imagecreatetruecolor($width, $height);
    if ($image === false) {
        throw new RuntimeException('digital_proof_fixture_raster_create_failed');
    }
    $white = imagecolorallocate($image, 255, 255, 255);
    imagefilledrectangle($image, 0, 0, $width, $height, $white);
    ob_start();
    $written = imagepng($image);
    $bytes = ob_get_clean();
    imagedestroy($image);
    if (! $written || ! is_string($bytes) || $bytes === '') {
        throw new RuntimeException('digital_proof_fixture_raster_encode_failed');
    }

    return $rasters[$key] = $bytes;
}

function digitalProofWorkerResponse(
    string $runId,
    bool $success,
    string $requestBody,
    array $expectedVector = [],
    ?array $rectification = null,
    string $errorCode = 'marker_missing',
    bool $forLayout = true,
): array {
    if (! $success) {
        $payload = ['code' => $errorCode, 'detail' => 'fixture rejection'];
        if ($rectification !== null) {
            $payload['rectification'] = $rectification;
        }

        return [json_encode($payload, JSON_THROW_ON_ERROR), ['Content-Type' => 'application/json']];
    }
    $manifest = digitalProofManifestFromBody($requestBody);
    $targetIdentity = array_key_exists('template_version_id', $manifest)
        ? ['template_version_id' => $manifest['template_version_id']]
        : ['candidate_target_id' => $manifest['candidate_target_id']];
    $items = [];
    foreach ((array) ($manifest['items'] ?? []) as $geometry) {
        if (! is_array($geometry)) {
            continue;
        }
        $label = strtoupper((string) ($geometry['area_code'] ?? '')).'_Q_'.(int) ($geometry['question_no'] ?? 0);
        $selected = $expectedVector[$label] ?? $expectedVector[strtolower($label)] ?? null;
        $options = (array) ($geometry['options'] ?? []);
        $items[] = [
            'schema_version' => OmrDetectedItem::SCHEMA_VERSION,
            'position' => (int) ($geometry['position'] ?? 0),
            'area_code' => (string) ($geometry['area_code'] ?? ''),
            'question_no' => (int) ($geometry['question_no'] ?? 0),
            'selected_option' => $selected,
            'state' => 'marked',
            'confidence' => null,
            'region' => (array) ($geometry['region'] ?? []),
            'observed_options' => is_string($selected) ? [$selected] : [],
            'option_observations' => array_map(static fn (array $option): array => [
                'code' => (string) ($option['code'] ?? ''),
                'mean' => null,
                'classification' => ($option['code'] ?? null) === $selected ? 'observed' : 'not_observed',
            ], $options),
            'issue_codes' => [],
            'legacy_evidence_limited' => false,
        ];
    }
    $fieldBlockShifts = array_map(
        static fn (string $block): array => ['block' => $block, 'x' => 0, 'y' => 0],
        array_keys((array) ($manifest['native_template']['fieldBlocks'] ?? [])),
    );
    $payload = [
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
        'aligned_image' => ['mime' => 'image/png', 'width' => $manifest['image_width'], 'height' => $manifest['image_height']],
        'layout_overlay' => ['mime' => 'image/png', 'width' => $manifest['image_width'], 'height' => $manifest['image_height']],
        'marker_matches' => $manifest['registration_mode'] === OmrRegistrationMode::DocumentBound->value
            ? []
            : ['quarter_1' => .9, 'quarter_2' => .9, 'quarter_3' => .9, 'quarter_4' => .9],
        'field_block_shifts' => $fieldBlockShifts,
        'bubble_means' => [],
        'item_evidence' => [],
        'items' => $items,
        'timings' => ['preprocess_ms' => 1.0, 'verification_ms' => 2.0],
    ];
    if ($rectification !== null) {
        $payload['rectification'] = $rectification;
    }
    $resultBytes = json_encode($payload, JSON_THROW_ON_ERROR);
    $raster = digitalProofRaster((int) $manifest['image_width'], (int) $manifest['image_height']);
    $boundary = 'digital-proof-worker';
    $body = '--'.$boundary."\r\n"
        ."Content-Disposition: form-data; name=\"result\"\r\nContent-Type: application/json\r\n\r\n".$resultBytes."\r\n"
        .'--'.$boundary."\r\n"
        ."Content-Disposition: form-data; name=\"aligned_image\"; filename=\"aligned.png\"\r\nContent-Type: image/png\r\n\r\n".$raster."\r\n";
    if ($forLayout) {
        $body .= '--'.$boundary."\r\n"
            ."Content-Disposition: form-data; name=\"layout_overlay\"; filename=\"overlay.png\"\r\nContent-Type: image/png\r\n\r\n".$raster."\r\n";
    }
    $body .= '--'.$boundary."--\r\n";
    $timestamp = (string) time();
    $resultHash = hash('sha256', $resultBytes);
    $alignedHash = hash('sha256', $raster);
    $signatureParts = [$timestamp, $runId, $resultHash, $alignedHash];
    if ($forLayout) {
        $signatureParts[] = $alignedHash;
    }
    $signature = hash_hmac('sha256', implode("\n", $signatureParts), (string) config('omr.omrchecker.signing_secret'));
    $headers = [
        'Content-Type' => 'multipart/form-data; boundary='.$boundary,
        'X-Nexiam-Contract' => OmrDetectionRequest::ENVELOPE_VERSION,
        'X-Nexiam-Run-Id' => $runId,
        'X-Nexiam-Timestamp' => $timestamp,
        'X-Nexiam-Result-SHA256' => $resultHash,
        'X-Nexiam-Aligned-SHA256' => $alignedHash,
        'X-Nexiam-Signature' => $signature,
    ];
    if ($forLayout) {
        $headers['X-Nexiam-Layout-Overlay-SHA256'] = $alignedHash;
    }

    return [$body, $headers];
}

function digitalProofDocumentRectification(bool $accepted): array
{
    if ($accepted) {
        return [
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
            'quad_normalized' => [[0, 0], [1, 0], [1, 1], [0, 1]],
        ];
    }

    return [
        'attempted' => true,
        'applied' => false,
        'mode' => OmrRegistrationMode::DocumentBound->value,
        'status' => 'rejected',
        'plausible_candidate_count' => 0,
        'selected_candidate_index' => null,
        'homography_count' => 0,
        'warp_count' => 0,
        'document_gates' => ['accepted' => false],
        'content_gate' => ['accepted' => false],
    ];
}

function digitalProofDocumentBoundWorkerFixture(array $expectedVector, bool $allowCroppedFalseAccept = false): stdClass
{
    $state = (object) ['calls' => 0];
    $rejectionCodes = [
        4 => 'document_aspect_mismatch',
        5 => 'document_quad_not_found',
        6 => 'document_aspect_mismatch',
        7 => 'ambiguous_rectification',
    ];
    Http::fake(['*' => function ($request) use ($state, $expectedVector, $allowCroppedFalseAccept, $rejectionCodes) {
        $variantIndex = intdiv($state->calls, 2);
        $accepted = $variantIndex < 4 || ($allowCroppedFalseAccept && $variantIndex === 4);
        $code = $rejectionCodes[$variantIndex] ?? 'document_quad_not_found';
        $forLayout = str_ends_with($request->url(), '/v1/layout-verification');
        [$body, $headers] = digitalProofWorkerResponse(
            (string) ($request->header('X-Nexiam-Run-Id')[0] ?? ''),
            $accepted,
            $request->body(),
            $expectedVector,
            digitalProofDocumentRectification($accepted),
            $code,
            $forLayout,
        );
        $state->calls++;

        return Http::response($body, $accepted ? 200 : 422, $headers);
    }]);
    $worker = new OmrCheckerDetection;
    app()->instance(OmrDetectionPort::class, $worker);
    app()->instance(OmrLayoutVerificationPort::class, $worker);

    return $state;
}

function digitalProofRunInline(int $runId): void
{
    $run = OmrTemplateVerificationRun::query()->findOrFail($runId);
    app()->call([new RunOmrTemplateVerificationJob($runId, (int) $run->attempt, (int) $run->optimistic_version), 'handle']);
    for ($step = 0; $step < 8; $step++) {
        $run = $run->fresh();
        $variant = $run->phase_detail['current_variant'] ?? null;
        if (! is_string($variant) || ($run->phase_detail['state'] ?? null) !== 'ready') {
            break;
        }
        app()->call([new RunOmrTemplateVerificationVariantJob($runId, (int) $run->attempt, $variant, (int) $run->optimistic_version), 'handle']);
    }
}

final class DigitalProofDetectorFake implements OmrDetectionPort
{
    public function detect(OmrDetectionRequest $request): OmrDetectionResult
    {
        $items = array_map(static function ($geometry): OmrDetectedItem {
            $selected = $geometry->options[($geometry->position - 1) % count($geometry->options)]->code ?? null;

            return OmrDetectedItem::fromGeometry(
                geometry: $geometry,
                selectedOption: $selected,
                state: OmrDetectedItem::STATE_MARKED,
                confidence: null,
            );
        }, $request->items);

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
            registrationMode: $request->registrationMode->value,
            templateVersionId: $request->templateVersionId,
            candidateTargetId: $request->candidateTargetId,
            expectedPageNo: $request->pageNo,
            sourceImage: $request->sourceImage->toArray(),
            nativeBundleHash: $request->nativeBundleHash,
            manifestHash: $request->manifestHash,
            patchIdentity: $request->patchIdentity,
            calibrationStatus: 'unqualified',
            fieldBlockShifts: [],
            imageMime: $request->imageMime,
            imageWidth: $request->imageWidth,
            imageHeight: $request->imageHeight,
        ));
    }
}

function digitalProofDraft(User $actor): OmrTemplateAuthoringDraft
{
    $profile = OmrMarkerProfile::query()->create([
        'code' => 'digital-proof.marker',
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
    $draft = app(OmrAuthoringDraftService::class)->create($actor, 'DIGITAL-PROOF', 'Digital proof', 'html');
    $draft->forceFill([
        'marker_profile_id' => $profile->id,
        'stage' => OmrTemplateAuthoringDraft::STAGE_MAPPING_COMPLETE,
        'authoring_json' => [
            'pages' => [['page_no' => 1, 'confirmed' => true]],
            'marker' => ['insets_mm' => ['top' => 12, 'right' => 12, 'bottom' => 14, 'left' => 12]],
            'canonical' => ['settings' => ['paper_width_mm' => 210, 'paper_height_mm' => 297]],
            'studio' => ['components' => [[
                'id' => 'Part_1', 'type' => 'mcq', 'name' => 'Part 1', 'subjectName' => 'General Ability',
                'posX' => 25, 'posY' => 70, 'w_mm' => 40, 'h_mm' => 50, 'questionsCount' => 1,
                'startQ' => 1, 'optionsCount' => 4, 'bubbleDiameterMm' => 4.5, 'bubblePitchMm' => 6, 'rowPitchMm' => 6.8,
            ]]],
            'mapping' => [
                'input' => ['page_width' => 2480, 'page_height' => 3508, 'page_count' => 1],
                'field_blocks' => ['Part_1' => [
                    'fieldType' => 'QTYPE_MCQ4', 'direction' => 'horizontal', 'fieldLabels' => ['LOGIC_Q_1'],
                    'bubblesGap' => 71, 'labelsGap' => 80, 'origin' => [295, 826], 'bubbleDimensions' => [53, 53],
                ]],
            ],
        ],
    ])->save();

    return $draft->fresh();
}

function digitalProof150Draft(User $actor): OmrTemplateAuthoringDraft
{
    $draft = digitalProofDraft($actor);
    $authoring = (array) $draft->authoring_json;
    $components = [];
    $fieldBlocks = [];

    foreach (range(1, 6) as $part) {
        $start = (($part - 1) * 25) + 1;
        $id = 'Part_'.$part;
        $area = 'PART_'.chr(64 + $part);
        $components[] = [
            'id' => $id,
            'type' => 'mcq',
            'name' => 'Part '.$part,
            'subjectName' => 'Part '.$part,
            'aptitudeAreaCode' => $area,
            'posX' => 20 + (($part - 1) * 30),
            'posY' => 70,
            'w_mm' => 40,
            'h_mm' => 150,
            'questionsCount' => 25,
            'startQ' => $start,
            'optionsCount' => 4,
            'bubbleDiameterMm' => 4.5,
            'bubblePitchMm' => 6,
            'rowPitchMm' => 6.8,
        ];
        $fieldBlocks[$id] = [
            'fieldType' => 'QTYPE_MCQ4',
            'direction' => 'horizontal',
            'fieldLabels' => [sprintf('%s_Q_%03d..%03d', $area, $start, $start + 24)],
            'bubblesGap' => 71,
            'labelsGap' => 80,
            'origin' => [200 + (($part - 1) * 320), 826],
            'bubbleDimensions' => [53, 53],
        ];
    }

    $authoring['studio']['components'] = $components;
    $authoring['mapping']['field_blocks'] = $fieldBlocks;
    $draft->forceFill(['authoring_json' => $authoring])->save();

    return $draft->fresh();
}

test('digital proof is draft-scoped, deterministic, and does not fabricate identity', function (): void {
    config([
        'omr.qr-keyring.current' => 'current-v1',
        'omr.qr-keyring.keys' => ['current-v1' => 'test-secret'],
        'omr.omrchecker.signing_secret' => str_repeat('s', 40),
    ]);
    $actor = User::factory()->create();
    $draft = digitalProofDraft($actor);
    $calls = 0;
    Http::fake(['*/layout-verification' => function ($request) use (&$calls) {
        $calls++;
        [$body, $headers] = digitalProofWorkerResponse((string) ($request->header('X-Nexiam-Run-Id')[0] ?? ''), $calls <= 7, $request->body());

        return Http::response($body, $calls <= 7 ? 200 : 422, $headers);
    }]);
    app()->instance(OmrDetectionPort::class, new DigitalProofDetectorFake);
    app()->instance(OmrLayoutVerificationPort::class, new OmrCheckerDetection);

    $run = app(StartOmrTemplateVerification::class)->handle($draft->fresh(), $actor);
    RunOmrTemplateVerificationJob::dispatchSync($run->id);

    $run = $run->fresh();
    $saved = $draft->fresh();

    expect($run->status)->toBe(OmrTemplateVerificationRun::STATUS_PASSED)
        ->and($run->attempt)->toBe(1)
        ->and($run->progress_done)->toBe($run->progress_total)
        ->and($run->error_code)->toBeNull()
        ->and($saved->stage)->toBe(OmrTemplateAuthoringDraft::STAGE_DIGITAL_VERIFIED)
        ->and($saved->authoring_json['verification']['status'])->toBe('passed')
        ->and($saved->authoring_json['verification'])->not->toHaveKey('identity')
        ->and($saved->authoring_json['verification']['matrix']['variants']['clean']['execution'])->toBe('omrchecker_layout_verification')
        ->and($saved->authoring_json['verification']['run_id'])->toBe($run->id)
        ->and($calls)->toBeGreaterThan(1);
});

test('150-item digital proof completes through the worker memory seam', function (): void {
    config([
        'omr.qr-keyring.current' => 'current-v1',
        'omr.qr-keyring.keys' => ['current-v1' => 'test-secret'],
        'omr.omrchecker.signing_secret' => str_repeat('s', 40),
    ]);
    $actor = User::factory()->create();
    $draft = digitalProof150Draft($actor);
    $calls = 0;
    Http::fake(['*/layout-verification' => function ($request) use (&$calls) {
        $calls++;
        [$body, $headers] = digitalProofWorkerResponse((string) ($request->header('X-Nexiam-Run-Id')[0] ?? ''), $calls <= 7, $request->body());

        return Http::response($body, $calls <= 7 ? 200 : 422, $headers);
    }]);
    app()->instance(OmrDetectionPort::class, new DigitalProofDetectorFake);
    app()->instance(OmrLayoutVerificationPort::class, new OmrCheckerDetection);

    $run = app(StartOmrTemplateVerification::class)->handle($draft->fresh(), $actor);
    RunOmrTemplateVerificationJob::dispatchSync($run->id);
    $run = $run->fresh();

    expect($run->status)->toBe(OmrTemplateVerificationRun::STATUS_PASSED)
        ->and($run->progress_done)->toBe(14)
        ->and($run->progress_total)->toBe(14)
        ->and($run->error_code)->toBeNull()
        ->and($run->captures()->where('variant', 'clean')->value('status'))->toBe('pass')
        ->and($calls)->toBeGreaterThan(1);
});

test('durable run persists one capture per variant with exact clean vector', function (): void {
    config([
        'omr.qr-keyring.current' => 'current-v1',
        'omr.qr-keyring.keys' => ['current-v1' => 'test-secret'],
        'omr.omrchecker.signing_secret' => str_repeat('s', 40),
    ]);
    $actor = User::factory()->create();
    $draft = digitalProofDraft($actor);
    $calls = 0;
    Http::fake(['*/layout-verification' => function ($request) use (&$calls) {
        $calls++;
        [$body, $headers] = digitalProofWorkerResponse((string) ($request->header('X-Nexiam-Run-Id')[0] ?? ''), $calls <= 7, $request->body());

        return Http::response($body, $calls <= 7 ? 200 : 422, $headers);
    }]);
    app()->instance(OmrDetectionPort::class, new DigitalProofDetectorFake);
    app()->instance(OmrLayoutVerificationPort::class, new OmrCheckerDetection);

    $run = app(StartOmrTemplateVerification::class)->handle($draft->fresh(), $actor);
    RunOmrTemplateVerificationJob::dispatchSync($run->id);
    $run = $run->fresh();
    $captures = $run->captures()->orderBy('id')->get();

    expect($captures)->toHaveCount(14)
        ->and($captures->where('variant', 'clean')->first()->status)->toBe('pass')
        ->and($captures->where('variant', 'clean')->first()->vector_match)->toBeTrue()
        ->and($captures->where('variant', 'clean')->first()->detected_vector_hash)->toBe($run->clean_vector_hash)
        ->and($captures->whereIn('variant', ['perspective', 'rotation', 'uneven_light', 'blur', 'glare', 'low_resolution'])->every(fn ($capture): bool => $capture->status === 'pass' && $capture->vector_match === true))->toBeTrue()
        ->and($captures->whereIn('variant', ['partial_page', 'missing_marker', 'wrong_marker'])->every(fn ($capture): bool => $capture->actual === 'typed_rejection'))->toBeTrue()
        ->and($captures->where('variant', 'wrong_instance')->first()->error_code)->toBe('wrong_instance')
        ->and($captures->where('variant', 'clean')->first()->aligned_asset_id)->not->toBeNull()
        ->and($run->result_summary['variants']['clean']['execution'])->toBe('omrchecker_layout_verification');

    foreach ($captures as $capture) {
        expect($capture->input_asset_id)->not->toBeNull()
            ->and($capture->input_hash)->toBe($capture->inputAsset->sha256);
    }
    $cleanEvidence = $captures->where('variant', 'clean')->first()->summary['evidence'];
    expect($cleanEvidence['items'])->toHaveCount(1)
        ->and($cleanEvidence['items'][0])->toHaveKeys(['native_label', 'question_no', 'expected', 'detected', 'exact'])
        ->and($cleanEvidence['items'][0])->not->toHaveKey('score')
        ->and($cleanEvidence['issues'])->toBeArray();
});

test('digital proof generator pixel regression confirms centered 53x53 bubble fill', function (): void {
    config([
        'omr.qr-keyring.current' => 'current-v1',
        'omr.qr-keyring.keys' => ['current-v1' => 'test-secret'],
    ]);
    $actor = User::factory()->create();
    $draft = digitalProofDraft($actor);
    $draft = app(MaterializeOmrCanonicalDraft::class)->handle($draft, $actor, (int) $draft->lock_version);
    $materialized = app(CanonicalArtifactMaterializer::class)->materialize($draft, $draft->markerProfile);
    $target = new CandidateArtifactDiagnosticTarget($draft, $materialized, $draft->markerProfile);
    $plan = app(OmrProofPlanner::class)->plan($target, ProofPurpose::Digital);

    $generator = app(OmrDigitalProofGenerator::class);
    $result = $generator->generate($draft->fresh(), $plan);

    $pageBytes = $result['pages'][0]['bytes'];
    $img = imagecreatefromstring($pageBytes);
    expect($img)->not->toBeFalse();

    $insets = (array) ($materialized->definition['insets_px'] ?? []);
    $manifest = $materialized->reviewManifest;
    $firstLabel = (string) array_key_first($plan->expectedVector);
    $expectedOption = $plan->expectedVector[$firstLabel];
    $field = null;
    foreach ($manifest['fields'] as $f) {
        if ($f['label'] === $firstLabel && $f['option'] === $expectedOption) {
            $field = $f;
            break;
        }
    }
    expect($field)->not->toBeNull();
    $paperX = $field['pixel']['x'] + (int) ($insets['left'] ?? 0);
    $paperY = $field['pixel']['y'] + (int) ($insets['top'] ?? 0);
    $cx = (int) round($paperX + $field['pixel']['w'] / 2.0);
    $cy = (int) round($paperY + $field['pixel']['h'] / 2.0);

    expect(imagecolorat($img, $cx, $cy) & 0xFF)->toBeLessThan(30);

    imagedestroy($img);
});

test('current production six-column 53px proof recovers 150 marks under deterministic uneven light', function (): void {
    $workerSecret = (string) config('omr.omrchecker.signing_secret');
    config([
        'omr.qr-keyring.current' => 'current-v1',
        'omr.qr-keyring.keys' => ['current-v1' => 'test-secret'],
        'omr.omrchecker.enabled' => true,
        'omr.omrchecker.endpoint' => 'http://inference:8100',
        'omr.omrchecker.signing_secret' => $workerSecret,
        'omr.omrchecker.contract_version' => OmrDetectionRequest::ENVELOPE_VERSION,
        'omr.omrchecker.engine_name' => 'OMRChecker',
        'omr.omrchecker.engine_commit' => 'de3f6982822895491be760325fd43e65e90fa293',
        'omr.omrchecker.patch_identity' => 'nexiam-headless-overlay-v1',
    ]);
    $actor = User::factory()->create();
    $draft = digitalProof150Draft($actor);
    $draft = app(MaterializeOmrCanonicalDraft::class)->handle($draft, $actor, (int) $draft->lock_version);
    $artifact = app(CanonicalArtifactMaterializer::class)->materialize($draft, $draft->markerProfile);
    $target = new CandidateArtifactDiagnosticTarget($draft, $artifact, $draft->markerProfile);
    $plan = app(OmrProofPlanner::class)->plan($target, ProofPurpose::Digital);
    $proof = app(OmrDigitalProofGenerator::class)->generate($draft->fresh(), $plan);
    $workerRequest = app(OmrDiagnosticRequestBuilder::class)->buildCandidate($artifact, $proof['pages'][0]['bytes'], 'image/png');
    expect($workerRequest->nativeTemplate)->not->toHaveKey('markers')
        ->and($workerRequest->nativeAssets)->not->toBe([]);

    $questionBlocks = array_values(array_filter(
        (array) ($artifact->template['pages'][0]['fieldBlocks'] ?? []),
        static fn (array $block): bool => ($block['fieldType'] ?? null) === 'QTYPE_MCQ4',
    ));
    expect($questionBlocks)->toHaveCount(6)
        ->and(array_map(static fn (array $block): array => $block['bubbleDimensions'] ?? [], $questionBlocks))
        ->toBe(array_fill(0, 6, [53, 53]));

    $worker = new OmrCheckerDetection;
    app()->instance(OmrDetectionPort::class, $worker);
    app()->instance(OmrLayoutVerificationPort::class, $worker);
    $sourcePath = tempnam(sys_get_temp_dir(), 'omr-production-source-');
    expect($sourcePath)->not->toBeFalse();
    file_put_contents($sourcePath, $proof['pages'][0]['bytes']);

    try {
        $entry = app(OmrNoGoMatrixRunner::class)->runVariant(
            'uneven_light',
            $sourcePath,
            $artifact,
            ['answer_vector' => $proof['answer_vector']],
        );

        expect($entry['item_summary'])->toMatchArray([
            'expected' => 150,
            'exact' => 150,
            'ambiguous' => 0,
            'missing' => 0,
            'vector_match' => true,
        ])
            ->and($entry['status'])->toBe('pass')
            ->and($entry['answer_vector_match'])->toBeTrue()
            ->and($entry['item_evidence'])->toHaveCount(150)
            ->and($entry['generator']['path'])->toBe('bin/omr-variant.php')
            ->and($entry['generator']['sha256'])->toHaveLength(64)
            ->and($entry['renderer']['name'])->toBe('OmrDigitalProofGenerator')
            ->and($entry['renderer']['version'])->toBe(OmrManifestBubbleShader::RENDERER_VERSION)
            ->and($entry['detection_outcome'])->toBe('succeeded');

        foreach ($entry['item_evidence'] as $item) {
            expect($item['exact'])->toBeTrue()
                ->and($item['mismatch'])->toBeNull();
        }
    } finally {
        @unlink($sourcePath);
        app()->forgetInstance(OmrDetectionPort::class);
        app()->forgetInstance(OmrLayoutVerificationPort::class);
    }
});

test('generated document-bound proof passes each page matrix through the live worker', function (int $pageCount): void {
    config([
        'omr.qr-keyring.current' => 'current-v1',
        'omr.qr-keyring.keys' => ['current-v1' => 'test-secret'],
        'omr.omrchecker.enabled' => true,
        'omr.omrchecker.endpoint' => 'http://inference:8100',
        'omr.omrchecker.contract_version' => OmrDetectionRequest::ENVELOPE_VERSION,
        'omr.omrchecker.engine_name' => 'OMRChecker',
        'omr.omrchecker.engine_commit' => 'de3f6982822895491be760325fd43e65e90fa293',
        'omr.omrchecker.patch_identity' => 'nexiam-headless-overlay-v1',
    ]);
    $actor = User::factory()->create();
    $draft = $pageCount === 2 ? digitalProofDocumentBoundTwoPageDraft($actor) : digitalProofDocumentBoundDraft($actor);
    $draft = app(MaterializeOmrCanonicalDraft::class)->handle($draft, $actor, (int) $draft->lock_version);
    $artifact = app(CanonicalArtifactMaterializer::class)->materialize($draft, null);
    $target = new CandidateArtifactDiagnosticTarget($draft, $artifact, null);
    $plan = app(OmrProofPlanner::class)->plan($target, ProofPurpose::Digital);
    $proof = app(OmrDigitalProofGenerator::class)->generate($draft->fresh(), $plan);
    expect($proof['pages'])->toHaveCount($pageCount);
    $worker = new OmrCheckerDetection;
    app()->instance(OmrDetectionPort::class, $worker);
    app()->instance(OmrLayoutVerificationPort::class, $worker);

    try {
        foreach ($proof['pages'] as $page) {
            $pageNo = (int) $page['page_no'];
            $sourcePath = tempnam(sys_get_temp_dir(), 'omr-document-source-');
            expect($sourcePath)->not->toBeFalse();
            file_put_contents($sourcePath, $page['bytes']);

            try {
                $request = app(OmrDiagnosticRequestBuilder::class)->buildCandidate($artifact, $page['bytes'], 'image/png', $pageNo);
                expect($request->nativeTemplate)->not->toHaveKey('markers');
                expect($request->nativeAssets)->toBe([]);
                $diagnostic = app(OmrTemplateDiagnosticRunner::class)->verify($target, $page['bytes'], 'image/png', $plan, $pageNo);
                expect($diagnostic->succeeded())->toBeTrue()
                    ->and(array_column($diagnostic->issues, 'code'))->not->toContain('sheet_misaligned');
                $evidence = [
                    'registration_mode' => OmrRegistrationMode::DocumentBound->value,
                    'answer_vector' => $plan->expectedVectorForPage($pageNo),
                    'paper_bounds' => $page['paper_bounds'],
                ];
                $entry = app(OmrNoGoMatrixRunner::class)->runVariant(
                    'clean',
                    $sourcePath,
                    $artifact,
                    $evidence,
                    pageNo: $pageNo,
                    pinnedMode: OmrRegistrationMode::DocumentBound,
                );
                expect($entry['actual'])->toBe('pass')
                    ->and($entry['status'])->toBe('pass')
                    ->and($entry['item_summary'])->toMatchArray(['expected' => 1, 'exact' => 1, 'vector_match' => true])
                    ->and($entry['worker_codes'])->toBe(['layout' => null, 'detection' => null])
                    ->and($entry['rectification']['accepted'])->toBeTrue()
                    ->and($entry['rectification']['same_selected_warp'])->toBeTrue();

                $failures = [];
                foreach (array_slice(OmrNoGoMatrixRunner::DOCUMENT_BOUND_VARIANTS, 1) as $variant) {
                    $variantEntry = app(OmrNoGoMatrixRunner::class)->runVariant(
                        $variant,
                        $sourcePath,
                        $artifact,
                        $evidence,
                        pageNo: $pageNo,
                        pinnedMode: OmrRegistrationMode::DocumentBound,
                    );
                    if ($variantEntry['status'] !== 'pass') {
                        $failures[$variant] = [
                            'actual' => $variantEntry['actual'],
                            'error_code' => $variantEntry['error_code'],
                            'worker_codes' => $variantEntry['worker_codes'],
                            'rectification' => collect(['layout', 'detection'])->mapWithKeys(static function (string $path) use ($variantEntry): array {
                                $rectification = $variantEntry['rectification'][$path] ?? [];

                                return [$path => [
                                    'reason' => $rectification['reason'] ?? null,
                                    'candidate_count' => $rectification['candidate_count'] ?? null,
                                    'plausible_candidate_count' => $rectification['plausible_candidate_count'] ?? null,
                                    'quad_normalized' => $rectification['quad_normalized'] ?? null,
                                    'supported_region_count' => $rectification['content_gate']['supported_region_count'] ?? null,
                                    'rejected_by_gate' => $rectification['rectifier_diagnostics']['rejected_by_gate'] ?? null,
                                ]];
                            })->all(),
                            'summary' => $variantEntry['item_summary'],
                        ];
                    }
                }
                expect($failures)->toBe([]);
            } finally {
                @unlink($sourcePath);
            }
        }
    } finally {
        app()->forgetInstance(OmrDetectionPort::class);
        app()->forgetInstance(OmrLayoutVerificationPort::class);
    }
})->with([1, 2]);

test('fiducial-anchored verification keeps the approved marker and canonical target hashes pinned', function (): void {
    Queue::fake();
    $actor = User::factory()->create();
    $draft = digitalProofDraft($actor);
    $profile = $draft->markerProfile;
    $run = app(StartOmrTemplateVerification::class)->handle($draft, $actor);
    $artifact = app(OmrVerificationTargetLoader::class)->load($run->fresh())['artifact'];
    $target = new CandidateArtifactDiagnosticTarget($draft, $artifact, $profile);

    expect($run->status)->toBe(OmrTemplateVerificationRun::STATUS_QUEUED)
        ->and($target->registrationMode())->toBe(OmrRegistrationMode::FiducialAnchored)
        ->and($target->markerProfileCode())->toBe($profile->code)
        ->and($target->markerProfileVersion())->toBe((int) $profile->version)
        ->and($target->markerAssetHash())->toBe($profile->asset_sha256)
        ->and($run->pinnedTargetHashes())->toBe([
            'artifact' => $target->artifactHash(),
            'native_bundle' => $target->nativeBundleHash(),
            'manifest' => $target->manifestHash(),
            'config' => $target->configHash(),
            'mapping' => $target->mappingHash(),
        ])
        ->and(app(OmrTemplateVerificationStatus::class)->hasTargetDrifted($run->fresh()))->toBeFalse();
});

function digitalProofDocumentBoundDraft(User $actor): OmrTemplateAuthoringDraft
{
    $draft = digitalProofDraft($actor);
    $authoring = (array) $draft->authoring_json;
    unset($authoring['marker']);

    $draft->forceFill([
        'registration_mode' => OmrRegistrationMode::DocumentBound,
        'qr_mode' => OmrQrMode::Disabled,
        'marker_profile_id' => null,
        'authoring_json' => $authoring,
    ])->save();

    return $draft->fresh();
}

function digitalProofDocumentBoundTwoPageDraft(User $actor): OmrTemplateAuthoringDraft
{
    $draft = digitalProofDocumentBoundDraft($actor);
    $authoring = (array) $draft->authoring_json;
    $authoring['pages'][] = ['page_no' => 2, 'confirmed' => true];
    $component = $authoring['studio']['components'][0];
    $component['page_no'] = 1;
    $authoring['studio']['components'][0] = $component;
    $authoring['studio']['components'][] = [
        ...$component,
        'id' => 'Part_2',
        'page_no' => 2,
        'startQ' => 2,
    ];
    $authoring['mapping']['input']['page_count'] = 2;
    $fieldBlock = $authoring['mapping']['field_blocks']['Part_1'];
    $fieldBlock['page_no'] = 1;
    $authoring['mapping']['field_blocks']['Part_1'] = $fieldBlock;
    $authoring['mapping']['field_blocks']['Part_2'] = [
        ...$fieldBlock,
        'page_no' => 2,
        'fieldLabels' => ['LOGIC_Q_2'],
    ];
    $draft->forceFill(['requested_page_count' => 2, 'authoring_json' => $authoring])->save();

    return $draft->fresh();
}

test('document-bound verification qualifies only matching accepted worker rectifications without fiducials', function (): void {
    config([
        'omr.qr-keyring.current' => 'current-v1',
        'omr.qr-keyring.keys' => ['current-v1' => 'test-secret'],
        'omr.omrchecker.signing_secret' => str_repeat('s', 40),
    ]);
    Queue::fake();
    $actor = User::factory()->create();
    $draft = digitalProofDocumentBoundDraft($actor);
    $run = app(StartOmrTemplateVerification::class)->handle($draft, $actor);
    $loaded = app(OmrVerificationTargetLoader::class)->load($run->fresh());
    $page = $loaded['artifact']->template['pages'][0];
    $workerState = digitalProofDocumentBoundWorkerFixture($loaded['plan']->expectedVectorForPage(1));

    expect($run->status)->toBe(OmrTemplateVerificationRun::STATUS_QUEUED)
        ->and($run->mapping_hash)->toBe($loaded['artifact']->geometryIdentity)
        ->and($loaded['artifact']->definition['registration_mode'])->toBe(OmrRegistrationMode::DocumentBound->value)
        ->and($loaded['artifact']->markerHash)->toBe('')
        ->and($page)->not->toHaveKey('markers')
        ->and($page['preProcessors'])->toBe([])
        ->and(app(OmrTemplateVerificationStatus::class)->hasTargetDrifted($run->fresh()))->toBeFalse();

    digitalProofRunInline($run->id);
    $run = $run->fresh();
    $captures = $run->captures()->orderBy('id')->get();
    $cropCapture = $captures->firstWhere('variant', 'cropped_page');
    $proofAsset = $draft->fresh()->assets()->where('kind', OmrTemplateAsset::KIND_DIGITAL_PROOF)->firstOrFail();
    $proofMetadata = (array) $proofAsset->metadata;
    $paperBounds = (array) ($proofMetadata['paper_bounds'] ?? []);

    expect($run->status)->toBe(OmrTemplateVerificationRun::STATUS_PASSED)
        ->and($run->progress_done)->toBe(8)
        ->and($run->progress_total)->toBe(8)
        ->and($captures->pluck('variant')->all())->toBe(OmrNoGoMatrixRunner::DOCUMENT_BOUND_VARIANTS)
        ->and($captures->whereIn('variant', ['clean', 'mild_shear', 'rotation', 'uneven_light'])->every(
            fn ($capture): bool => $capture->status === 'pass' && $capture->vector_match === true,
        ))->toBeTrue()
        ->and($captures->whereIn('variant', ['cropped_page', 'background', 'aspect_mismatch', 'ambiguous_boundary'])->every(
            fn ($capture): bool => $capture->status === 'pass' && $capture->actual === 'typed_rejection' && $capture->vector_match === null,
        ))->toBeTrue()
        ->and($cropCapture->summary['worker_codes'])->toBe([
            'layout' => 'document_aspect_mismatch',
            'detection' => 'document_aspect_mismatch',
        ])
        ->and($cropCapture->summary['rectification']['layout']['document_gates']['accepted'])->toBeFalse()
        ->and($cropCapture->summary['rectification']['detection']['document_gates']['accepted'])->toBeFalse()
        ->and($workerState->calls)->toBe(16)
        ->and($captures->whereIn('variant', ['clean', 'mild_shear', 'rotation', 'uneven_light'])->pluck('input_hash')->unique()->count())->toBe(4)
        ->and($proofMetadata['registration_mode'])->toBe(OmrRegistrationMode::DocumentBound->value)
        ->and($proofMetadata['qr_mode'])->toBe(OmrQrMode::Disabled->value)
        ->and($paperBounds)->toHaveKeys(['x', 'y', 'width', 'height'])
        ->and($draft->fresh()->stage)->toBe(OmrTemplateAuthoringDraft::STAGE_DIGITAL_VERIFIED);
    $current = app(CurrentOmrDigitalVerification::class);
    expect($current->validateRun($run, $run->artifact_hash, $run->mapping_hash, $draft->fresh()))->not->toBeNull();
    $cropSummary = (array) $cropCapture->summary;
    $cropSummary['worker_codes']['detection'] = 'worker_unavailable';
    $cropCapture->forceFill(['summary' => $cropSummary])->save();
    expect($current->validateRun($run->fresh(), $run->artifact_hash, $run->mapping_hash, $draft->fresh()))->toBeNull();
});

test('document-bound adversarial variant that is accepted by both workers fails qualification', function (): void {
    config([
        'omr.qr-keyring.current' => 'current-v1',
        'omr.qr-keyring.keys' => ['current-v1' => 'test-secret'],
        'omr.omrchecker.signing_secret' => str_repeat('s', 40),
    ]);
    Queue::fake();
    $actor = User::factory()->create();
    $draft = digitalProofDocumentBoundDraft($actor);
    $run = app(StartOmrTemplateVerification::class)->handle($draft, $actor);
    $loaded = app(OmrVerificationTargetLoader::class)->load($run->fresh());
    $workerState = digitalProofDocumentBoundWorkerFixture($loaded['plan']->expectedVectorForPage(1), true);

    digitalProofRunInline($run->id);
    $run = $run->fresh();
    $captures = $run->captures()->orderBy('id')->get();
    $falseAccept = $captures->firstWhere('variant', 'cropped_page');

    expect($run->status)->toBe(OmrTemplateVerificationRun::STATUS_FAILED)
        ->and($run->error_code)->toBe('no_go_variant_failed')
        ->and($falseAccept->actual)->toBe('false_accept')
        ->and($falseAccept->status)->toBe('fail')
        ->and($falseAccept->vector_match)->toBeNull()
        ->and($workerState->calls)->toBe(16);
});

test('document-bound verification rejects stale marker selection during startup', function (): void {
    Queue::fake();
    $actor = User::factory()->create();
    $draft = digitalProofDocumentBoundDraft($actor);
    $profile = OmrMarkerProfile::query()->firstOrFail();
    $draft->forceFill(['marker_profile_id' => $profile->id])->save();

    expect(fn () => app(StartOmrTemplateVerification::class)->handle($draft->fresh(), $actor))
        ->toThrow(OmrAuthoringDraftException::class, 'registration_mode_incompatible');

    expect(OmrTemplateVerificationRun::query()->where('draft_id', $draft->id)->exists())->toBeFalse();
    Queue::assertNothingPushed();
});

test('document-bound target loading rejects a marker profile that drifted onto the draft after pinning', function (): void {
    Queue::fake();
    $actor = User::factory()->create();
    $draft = digitalProofDocumentBoundDraft($actor);
    $run = app(StartOmrTemplateVerification::class)->handle($draft, $actor);
    $profile = OmrMarkerProfile::query()->firstOrFail();
    $draft->forceFill(['marker_profile_id' => $profile->id])->save();

    expect(fn () => app(OmrVerificationTargetLoader::class)->load($run->fresh()))
        ->toThrow(OmrDiagnosticTargetException::class, 'candidate_registration_mode_mismatch');
    expect(app(OmrTemplateVerificationStatus::class)->hasTargetDrifted($run->fresh()))->toBeTrue();
});
