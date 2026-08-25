<?php
use App\Assessment\Actions\Omr\Canonical\RunOmrDigitalVerification;
use App\Assessment\Omr\Canonical\OmrAuthoringDraftService;
use App\Assessment\Omr\Canonical\OmrTemplateAssetStore;
use App\Models\Assessment\OmrMarkerProfile;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use App\Assessment\Omr\OmrDetectionPort;
use App\Assessment\Omr\OmrDetectionResult;
use App\Assessment\Omr\OmrDetectionSuccess;
use App\Assessment\Omr\OmrDetectedItem;

uses(RefreshDatabase::class);

function digitalProofWorkerResponse(string $runId, bool $success): array
{
    if (! $success) {
        return [json_encode(['code' => 'marker_missing', 'detail' => 'fixture rejection'], JSON_THROW_ON_ERROR), ['Content-Type' => 'application/json']];
    }
    $payload = [
        'contract_version' => 'nexiam.omr.detection.v2',
        'run_id' => $runId,
        'correlation_id' => 'omr:digital-proof',
        'outcome' => 'succeeded',
        'engine' => ['name' => 'OMRChecker', 'version' => 'de3f69828228', 'commit' => str_repeat('c', 40), 'patch_identity' => 'nexiam-headless-overlay-v1'],
        'input_hash' => str_repeat('i', 64),
        'native_bundle_hash' => str_repeat('n', 64),
        'manifest_hash' => str_repeat('m', 64),
        'config_hash' => str_repeat('g', 64),
        'calibration_status' => 'unqualified',
        'aligned_image' => ['mime' => 'image/png', 'width' => 2480, 'height' => 3508],
        'layout_overlay' => ['mime' => 'image/png', 'width' => 2480, 'height' => 3508],
        'marker_matches' => ['quarter_1' => .9, 'quarter_2' => .9, 'quarter_3' => .9, 'quarter_4' => .9],
        'field_block_shifts' => [],
        'bubble_means' => [],
        'item_evidence' => [],
        'timings' => ['preprocess_ms' => 1.0, 'verification_ms' => 2.0],
    ];
    $resultBytes = json_encode($payload, JSON_THROW_ON_ERROR);
    $raster = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
    $boundary = 'digital-proof-worker';
    $body = '--'.$boundary."\r\n"
        ."Content-Disposition: form-data; name=\"result\"\r\nContent-Type: application/json\r\n\r\n".$resultBytes."\r\n"
        .'--'.$boundary."\r\n"
        ."Content-Disposition: form-data; name=\"aligned_image\"; filename=\"aligned.png\"\r\nContent-Type: image/png\r\n\r\n".$raster."\r\n"
        .'--'.$boundary."\r\n"
        ."Content-Disposition: form-data; name=\"layout_overlay\"; filename=\"overlay.png\"\r\nContent-Type: image/png\r\n\r\n".$raster."\r\n"
        .'--'.$boundary."--\r\n";
    $timestamp = (string) time();
    $resultHash = hash('sha256', $resultBytes);
    $alignedHash = hash('sha256', $raster);
    $signature = hash_hmac('sha256', implode("\n", [$timestamp, $runId, $resultHash, $alignedHash, $alignedHash]), (string) config('omr.omrchecker.signing_secret'));

    return [$body, [
        'Content-Type' => 'multipart/form-data; boundary='.$boundary,
        'X-Nexiam-Contract' => 'nexiam.omr.detection.v2',
        'X-Nexiam-Run-Id' => $runId,
        'X-Nexiam-Timestamp' => $timestamp,
        'X-Nexiam-Result-SHA256' => $resultHash,
        'X-Nexiam-Aligned-SHA256' => $alignedHash,
        'X-Nexiam-Layout-Overlay-SHA256' => $alignedHash,
        'X-Nexiam-Signature' => $signature,
    ]];
}

final class DigitalProofDetectorFake implements OmrDetectionPort
{
    public function detect(\App\Assessment\Omr\OmrDetectionRequest $request): OmrDetectionResult
    {
        $items = array_map(static fn ($geometry): OmrDetectedItem => new OmrDetectedItem(
            position: $geometry->position,
            areaCode: $geometry->areaCode,
            questionNo: $geometry->questionNo,
            selectedOption: $geometry->options[0]->code ?? null,
            state: OmrDetectedItem::STATE_MARKED,
            confidence: null,
            region: $geometry->region,
        ), $request->items);

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
            calibrationStatus: 'unqualified',
            fieldBlockShifts: [],
            imageMime: $request->imageMime,
            imageWidth: $request->imageWidth,
            imageHeight: $request->imageHeight,
        ));
    }
}

test('digital proof is draft-scoped, deterministic, and does not fabricate identity', function (): void {
    config([
        'omr.qr-keyring.current' => 'current-v1',
        'omr.qr-keyring.keys' => ['current-v1' => 'test-secret'],
        'omr.omrchecker.signing_secret' => str_repeat('s', 40),
    ]);
    $actor = User::factory()->create();
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
    $calls = 0;
    Http::fake(['*/layout-verification' => function ($request) use (&$calls) {
        $calls++;
        [$body, $headers] = digitalProofWorkerResponse((string) ($request->header('X-Nexiam-Run-Id')[0] ?? ''), $calls === 1);
        return Http::response($body, $calls === 1 ? 200 : 422, $headers);
    }]);
    app()->instance(OmrDetectionPort::class, new DigitalProofDetectorFake);

    $saved = app(RunOmrDigitalVerification::class)->handle($draft->fresh(), $actor);

    expect($saved->stage)->toBe(OmrTemplateAuthoringDraft::STAGE_DIGITAL_VERIFIED)
        ->and($saved->authoring_json['verification']['status'])->toBe('passed')
        ->and($saved->authoring_json['verification'])->not->toHaveKey('identity')
        ->and($saved->authoring_json['verification']['matrix']['variants']['clean']['execution'])->toBe('omrchecker_layout_verification')
        ->and($calls)->toBeGreaterThan(1);
});
