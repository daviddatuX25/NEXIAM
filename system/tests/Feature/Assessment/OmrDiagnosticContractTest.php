<?php

use App\Assessment\Actions\Omr\SaveAuthoredTemplateDraft;
use App\Assessment\Omr\Canonical\CanonicalArtifactSet;
use App\Assessment\Omr\Diagnostics\CandidateArtifactDiagnosticTarget;
use App\Assessment\Omr\Diagnostics\CompiledVersionDiagnosticTarget;
use App\Assessment\Omr\Diagnostics\OmrDiagnosticTarget;
use App\Assessment\Omr\Diagnostics\OmrDiagnosticTargetException;
use App\Assessment\Omr\Diagnostics\OmrProofPlan;
use App\Assessment\Omr\Diagnostics\OmrTemplateDiagnosticRunner;
use App\Assessment\Omr\Diagnostics\ProofPurpose;
use App\Assessment\Omr\OmrDetectionOutcome;
use App\Assessment\Omr\OmrDetectionPort;
use App\Assessment\Omr\OmrDetectionRequest;
use App\Assessment\Omr\OmrDetectionResult;
use App\Assessment\Omr\OmrDetectionSuccess;
use App\Assessment\Omr\OmrDetectedItem;
use App\Assessment\Omr\OmrIdentityField;
use App\Assessment\Omr\OmrLayoutVerificationPort;
use App\Assessment\Omr\OmrLayoutVerificationResult;
use App\Assessment\Omr\OmrRegion;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrMarkerProfile;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\Foundation\AptitudeArea;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/*
 * Contract tests for the shared diagnostic kernel (OpenSpec 35.16):
 * candidate and compiled adapters run through the SAME runner seam (fake and
 * real worker) and produce the identical normalized evidence shape, carry
 * target/hash identity, surface typed failures, and refuse candidate
 * qualification.
 */

final class OmrDiagContractFakeLayout implements OmrLayoutVerificationPort
{
    public function __construct(
        private readonly bool $succeed = true,
        private readonly array $shifts = [],
    ) {
    }

    public function verifyLayout(OmrDetectionRequest $request): OmrLayoutVerificationResult
    {
        if (! $this->succeed) {
            return OmrLayoutVerificationResult::failed('marker_missing', 'fixture: no registration markers found.');
        }
        $frame = is_array($request->nativeTemplate['pageDimensions'] ?? null) ? $request->nativeTemplate['pageDimensions'] : [100, 100];
        $aligned = ['mime' => 'image/png', 'width' => (int) $frame[0], 'height' => (int) $frame[1]];

        $means = [];
        foreach ($request->items as $geometry) {
            $option = $geometry->options[0]->code ?? 'A';
            $means[] = ['label' => $geometry->nativeLabel, 'option' => $option, 'mean' => 245.0];
        }

        return new OmrLayoutVerificationResult(
            outcome: OmrLayoutVerificationResult::OUTCOME_SUCCEEDED,
            alignedBytes: 'aligned-raster',
            alignedImage: [...$aligned, 'hash' => hash('sha256', 'aligned-raster')],
            overlayBytes: 'overlay-raster',
            overlayImage: [...$aligned, 'hash' => hash('sha256', 'overlay-raster')],
            markerMatches: ['quarter_1' => 0.9, 'quarter_2' => 0.9, 'quarter_3' => 0.9, 'quarter_4' => 0.9],
            fieldBlockShifts: $this->shifts,
            bubbleMeans: ['fixture' => $means],
            gates: ['marker_count' => 'pass', 'dimensions' => 'pass', 'shifts' => 'pass'],
            preprocessMs: 12.5,
            verificationMs: 30.0,
            itemEvidence: [['label' => 'LOGIC_Q_1', 'option' => 'A', 'mean' => 245.0]],
        );
    }
}

final class OmrDiagContractFakeDetection implements OmrDetectionPort
{
    public function __construct(
        private readonly string $markOption = 'A',
        private readonly string $markDigit = '7',
        private readonly ?string $failCode = null,
    ) {
    }

    public function detect(OmrDetectionRequest $request): OmrDetectionResult
    {
        if ($this->failCode !== null) {
            return OmrDetectionResult::failed(OmrDetectionOutcome::RetryableFailure, $this->failCode, 'fixture detection failure.');
        }
        $items = [];
        foreach ($request->items as $geometry) {
            $items[] = new OmrDetectedItem(
                position: $geometry->position,
                areaCode: $geometry->areaCode,
                questionNo: $geometry->questionNo,
                selectedOption: $this->markOption,
                state: OmrDetectedItem::STATE_MARKED,
                confidence: 0.9,
                region: $geometry->region,
            );
        }

        return OmrDetectionResult::succeeded(new OmrDetectionSuccess(
            engineName: $request->engineName,
            engineVersion: 'fixture-1',
            engineCommit: $request->engineCommit,
            inputHash: $request->inputHash,
            configHash: $request->configHash,
            applicantNumberProposal: null,
            applicantNumberConfidence: null,
            identityFields: $this->identityFields($request),
            items: $items,
            issues: [],
            sheetConfidence: 0.9,
            responseEnvelopeVersion: $request->envelopeVersion,
            runId: $request->runId,
            correlationId: $request->correlationId,
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

    /** @return list<OmrIdentityField> */
    private function identityFields(OmrDetectionRequest $request): array
    {
        $positions = [];
        foreach ((array) ($request->reviewManifest['fields'] ?? []) as $field) {
            if (! is_array($field) || ! is_string($field['label'] ?? null)) {
                continue;
            }
            if (preg_match('/^APPLICANT_NUMBER_(\d+)$/', $field['label'], $matches) !== 1 || ! is_array($field['region'] ?? null)) {
                continue;
            }
            $positions[(int) $matches[1]] = OmrRegion::fromArray($field['region']);
        }
        ksort($positions);
        $fields = [];
        foreach ($positions as $region) {
            $fields[] = new OmrIdentityField(OmrIdentityField::CODE_APPLICANT_NUMBER, $region, 0.95, $this->markDigit);
        }

        return $fields;
    }
}

function omrDiagContractPng(): string
{
    return (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
}

function omrDiagContractProfile(User $actor, string $code, string $status = OmrMarkerProfile::STATUS_QUALIFIED): OmrMarkerProfile
{
    return OmrMarkerProfile::query()->create([
        'code' => $code,
        'version' => 1,
        'family' => 'nexiam.fiducial',
        'asset_path' => 'app/Assessment/Omr/Fiducial/omr_marker.jpg',
        'asset_sha256' => hash_file('sha256', base_path('app/Assessment/Omr/Fiducial/omr_marker.jpg')),
        'size_mm' => 12,
        'quiet_zone_mm' => 3,
        'matching_config' => [],
        'status' => $status,
        'qualification_evidence_hash' => $status === OmrMarkerProfile::STATUS_QUALIFIED ? hash('sha256', 'calibration') : null,
        'created_by_user_id' => $actor->id,
        'qualified_by_user_id' => $status === OmrMarkerProfile::STATUS_QUALIFIED ? $actor->id : null,
        'qualified_at' => $status === OmrMarkerProfile::STATUS_QUALIFIED ? now() : null,
    ]);
}

function omrDiagContractArtifactSet(): CanonicalArtifactSet
{
    $png = omrDiagContractPng();
    $template = [
        'bubbleDimensions' => [4, 4],
        'pageDimensions' => [100, 100],
        'pages' => [[
            'page_no' => 1,
            'pageDimensions' => [100, 100],
            'fieldBlocks' => ['Part_1' => [
                'fieldType' => 'QTYPE_MCQ4',
                'direction' => 'horizontal',
                'fieldLabels' => ['LOGIC_Q_1'],
                'bubblesGap' => 10,
                'labelsGap' => 10,
                'origin' => [20, 20],
                'bubbleDimensions' => [4, 4],
            ]],
            'preProcessors' => [['name' => 'CropOnMarkers', 'options' => ['relativePath' => 'marker.jpg']]],
            'markers' => ['asset_name' => 'marker.jpg', 'size' => 8, 'quiet_zone' => 2, 'centers' => [[0, 0]], 'insets_px' => ['top' => 0, 'left' => 0]],
        ]],
    ];
    $reviewManifest = [
        'manifest_version' => 'nexiam.omr.review-manifest.v1',
        'bundle_hash' => str_repeat('b', 64),
        'fields' => [],
    ];
    foreach (['A', 'B', 'C', 'D'] as $index => $option) {
        $reviewManifest['fields'][] = [
            'page' => 1,
            'label' => 'LOGIC_Q_1',
            'option' => $option,
            'region' => ['x' => 0.2 + $index * 0.12, 'y' => 0.2, 'w' => 0.04, 'h' => 0.04],
        ];
    }
    foreach ([1, 2] as $position) {
        $reviewManifest['fields'][] = [
            'page' => 1,
            'label' => 'APPLICANT_NUMBER_'.$position,
            'option' => (string) $position,
            'region' => ['x' => 0.2 + $position * 0.05, 'y' => 0.05, 'w' => 0.04, 'h' => 0.04],
        ];
    }

    return new CanonicalArtifactSet(
        definition: ['source_hash' => str_repeat('d', 64), 'insets_px' => ['top' => 0, 'left' => 0]],
        template: $template,
        config: ['dimensions' => ['display_width' => 100, 'display_height' => 100]],
        manifest: ['files' => ['template.json' => str_repeat('t', 64)], 'engine' => ['name' => 'OMRChecker', 'commit' => str_repeat('c', 40), 'patch_set' => 'nexiam-headless-overlay-v1'], 'bundle_version' => 'nexiam.omr.native-bundle.v1'],
        reviewManifest: $reviewManifest,
        reviewManifestHash: str_repeat('m', 64),
        files: ['marker.jpg' => $png],
        pages: [['page_no' => 1, 'bytes' => $png, 'mime' => 'image/png', 'width' => 1, 'height' => 1, 'sha256' => hash('sha256', $png), 'metadata' => []]],
        pdfBytes: 'pdf',
        artifactHash: str_repeat('a', 64),
        templateHash: str_repeat('t', 64),
        configHash: str_repeat('g', 64),
        pdfHash: str_repeat('p', 64),
        referenceHash: str_repeat('r', 64),
        markerHash: hash('sha256', $png),
    );
}

function omrDiagContractDraft(User $actor, OmrMarkerProfile $profile, CanonicalArtifactSet $artifact, ?string $persistedArtifactHash = null, ?string $persistedDefinitionHash = null): OmrTemplateAuthoringDraft
{
    $draft = app(App\Assessment\Omr\Canonical\OmrAuthoringDraftService::class)->create($actor, 'DIAG-CONTRACT-'.strtoupper(substr(hash('sha256', $artifact->artifactHash.$actor->id.$profile->id), 0, 8)), 'Diagnostic contract draft', 'html');
    $draft->forceFill([
        'marker_profile_id' => $profile->id,
        'stage' => OmrTemplateAuthoringDraft::STAGE_MAPPING_COMPLETE,
        'authoring_json' => [
            'canonical' => ['artifact_set' => [
                'artifact_hash' => $persistedArtifactHash ?? $artifact->artifactHash,
                'definition_hash' => $persistedDefinitionHash ?? $artifact->definition['source_hash'],
            ]],
        ],
    ])->save();

    return $draft->fresh();
}

function omrDiagContractCandidate(User $actor, OmrMarkerProfile $profile, CanonicalArtifactSet $artifact): CandidateArtifactDiagnosticTarget
{
    return new CandidateArtifactDiagnosticTarget(omrDiagContractDraft($actor, $profile, $artifact), $artifact, $profile);
}

function omrDiagContractBlueprint(): array
{
    $areas = ['PROFILE_VERBAL', 'PROFILE_QUANT', 'LOGIC', 'SPATIAL', 'MEMORY', 'LANGUAGE'];
    $sections = [];
    foreach ($areas as $index => $area) {
        $sections[] = [
            'area_code' => $area,
            'question_from' => 1,
            'question_to' => 4,
            'option_count' => 4,
            'break_before' => $index === 0 ? null : 'next_column',
        ];
    }

    return [
        'schema' => 'nexiam.omr.layout-blueprint.v1',
        'paper' => 'long',
        'columns' => 6,
        'identity' => ['applicant_number' => true, 'birthdate' => false, 'repeat_on_every_page' => true],
        'markers' => ['enabled' => true],
        'sections' => $sections,
    ];
}

function omrDiagContractVersion(User $actor): OmrAnswerSheetTemplateVersion
{
    foreach (['PROFILE_VERBAL' => 'Profile Verbal Reasoning', 'PROFILE_QUANT' => 'Profile Quantitative Reasoning', 'LOGIC' => 'General Ability', 'SPATIAL' => 'Spatial Ability', 'MEMORY' => 'Memory', 'LANGUAGE' => 'Language Proficiency'] as $code => $name) {
        AptitudeArea::query()->firstOrCreate(
            ['code' => $code],
            ['name' => $name, 'version' => 1, 'scoring_method' => 'count_correct', 'max_items' => 25, 'is_active' => true],
        );
    }

    $version = app(SaveAuthoredTemplateDraft::class)->handle(
        'SYN-DIAG-CONTRACT',
        'Diagnostic contract template',
        [],
        $actor,
        null,
        true,
        omrDiagContractBlueprint(),
    );

    $native = (array) $version->native_bundle_json;
    $native['manifest']['marker_profile'] = [
        'id' => 1,
        'code' => 'diag.contract.compiled',
        'version' => 1,
        'asset_sha256' => str_repeat('f', 64),
        'status' => 'qualified',
    ];
    $version->forceFill([
        'native_bundle_json' => $native,
        'authoring_manifest' => [
            'mapping_hash' => str_repeat('z', 64),
            'artifact_set' => ['artifact_hash' => str_repeat('a', 64)],
        ],
    ])->save();

    return $version->fresh();
}

function omrDiagContractPlan(OmrDiagnosticTarget $target, ProofPurpose $purpose, string $mark = 'A', int $digit = 7): OmrProofPlan
{
    $labels = [];
    $identity = [];
    foreach ((array) ($target->reviewManifest()['fields'] ?? []) as $field) {
        if (! is_array($field) || ! is_string($field['label'] ?? null)) {
            continue;
        }
        if (preg_match('/^APPLICANT_NUMBER_(\d+)$/', $field['label'], $matches) === 1) {
            $identity['APPLICANT_NUMBER_'.$matches[1]] = $digit;
            continue;
        }
        if (preg_match('/^[A-Z0-9_]+_Q_\d+$/', $field['label']) === 1) {
            $labels[$field['label']] = $mark;
        }
    }
    ksort($labels);
    ksort($identity);

    return new OmrProofPlan(
        planVersion: 'v1',
        purpose: $purpose,
        seed: $target->artifactHash().$target->nativeBundleHash().'v1'.$purpose->value,
        challengeCode: 'CHAL-'.substr(hash('sha256', $target->targetId().$purpose->value), 0, 10),
        expectedVector: $labels,
        calibrationDigits: $identity,
        pageInstructions: [['page_no' => 1, 'labels' => array_keys($labels), 'instruction' => 'Shade '.$mark.' on every row.']],
        targetHashes: [
            'artifact' => $target->artifactHash(),
            'native_bundle' => $target->nativeBundleHash(),
            'manifest' => $target->manifestHash(),
            'config' => $target->configHash(),
            'mapping' => $target->mappingHash(),
        ],
    );
}

/** Recursive key-shape of a normalized evidence array (structure, not values). */
function omrDiagContractShape(mixed $value): array|string
{
    if (! is_array($value)) {
        return get_debug_type($value);
    }
    if (array_is_list($value)) {
        return ['list' => $value === [] ? 'empty' : omrDiagContractShape($value[0])];
    }
    $shape = [];
    foreach ($value as $key => $child) {
        $shape[$key] = omrDiagContractShape($child);
    }
    ksort($shape);

    return $shape;
}

/** Recursively assert no forbidden mutation/applicant field name exists. */
function omrDiagContractAssertNoForbiddenKeys(mixed $value, array $forbidden, string $path = '$'): void
{
    foreach ($forbidden as $needle) {
        if (is_array($value) && array_key_exists($needle, $value)) {
            throw new RuntimeException("Forbidden evidence key {$needle} at {$path}.");
        }
    }
    if (! is_array($value)) {
        return;
    }
    foreach ($value as $key => $child) {
        omrDiagContractAssertNoForbiddenKeys($child, $forbidden, $path.'.'.$key);
    }
}

function omrDiagContractRunner(OmrLayoutVerificationPort $layout, OmrDetectionPort $detection): OmrTemplateDiagnosticRunner
{
    app()->instance(OmrLayoutVerificationPort::class, $layout);
    app()->instance(OmrDetectionPort::class, $detection);

    return app(OmrTemplateDiagnosticRunner::class);
}

test('candidate adapter fails closed on stale hashes and compiled drafts', function (): void {
    $actor = User::factory()->create();
    $profile = omrDiagContractProfile($actor, 'diag.contract.candidate');
    $artifact = omrDiagContractArtifactSet();

    expect(fn () => new CandidateArtifactDiagnosticTarget(omrDiagContractDraft($actor, $profile, $artifact, persistedArtifactHash: str_repeat('x', 64)), $artifact, $profile))
        ->toThrow(OmrDiagnosticTargetException::class, 'candidate_artifact_stale');
    expect(fn () => new CandidateArtifactDiagnosticTarget(omrDiagContractDraft($actor, $profile, $artifact, persistedDefinitionHash: str_repeat('y', 64)), $artifact, $profile))
        ->toThrow(OmrDiagnosticTargetException::class, 'candidate_definition_stale');
    $compiledDraft = omrDiagContractDraft($actor, $profile, $artifact);
    $compiledDraft->forceFill(['stage' => OmrTemplateAuthoringDraft::STAGE_COMPILED])->save();
    expect(fn () => new CandidateArtifactDiagnosticTarget($compiledDraft->fresh(), $artifact, $profile))
        ->toThrow(OmrDiagnosticTargetException::class, 'candidate_draft_compiled');
});

test('candidate and compiled adapters expose kind-bound identity and hashes', function (): void {
    $actor = User::factory()->create();
    $profile = omrDiagContractProfile($actor, 'diag.contract.identity', OmrMarkerProfile::STATUS_CANDIDATE);
    $artifact = omrDiagContractArtifactSet();
    $candidate = omrDiagContractCandidate($actor, $profile, $artifact);

    expect($candidate->kind())->toBe(OmrDiagnosticTarget::KIND_CANDIDATE)
        ->and($candidate->pageCount())->toBe(1)
        ->and($candidate->qualificationCapable())->toBeFalse()
        ->and($candidate->markerProfileCode())->toBe('diag.contract.identity')
        ->and($candidate->markerProfileVersion())->toBe(1)
        ->and($candidate->markerProfileQualified())->toBeFalse()
        ->and($candidate->markerAssetName())->toBe('marker.jpg')
        ->and($candidate->markerAssetHash())->toBe($artifact->markerHash)
        ->and($candidate->artifactHash())->toBe($artifact->artifactHash)
        ->and($candidate->nativeBundleHash())->toBe(str_repeat('b', 64))
        ->and($candidate->manifestHash())->toBe($artifact->reviewManifestHash)
        ->and($candidate->configHash())->toBe($artifact->configHash)
        ->and($candidate->mappingHash())->toBe($artifact->definition['source_hash'])
        ->and($candidate->nativeAssets())->toHaveKey('marker.jpg');

    $version = omrDiagContractVersion($actor);
    $compiled = new CompiledVersionDiagnosticTarget($version->fresh());

    expect($compiled->kind())->toBe(OmrDiagnosticTarget::KIND_COMPILED_VERSION)
        ->and($compiled->targetId())->toBe((string) $version->id)
        ->and($compiled->pageCount())->toBe(1)
        ->and($compiled->qualificationCapable())->toBeTrue()
        ->and($compiled->markerProfileCode())->toBe('diag.contract.compiled')
        ->and($compiled->markerProfileVersion())->toBe(1)
        ->and($compiled->markerProfileQualified())->toBeTrue()
        ->and($compiled->markerAssetName())->not->toBeNull()
        ->and($compiled->markerAssetHash())->toHaveLength(64)
        ->and($compiled->nativeBundleHash())->toBe((string) $version->native_bundle_hash)
        ->and($compiled->manifestHash())->toBe((string) $version->review_manifest_hash)
        ->and($compiled->configHash())->toBe((string) $version->config_hash)
        ->and($compiled->mappingHash())->toBe(str_repeat('z', 64));
});

test('both adapters produce the identical normalized evidence shape through the same seam', function (): void {
    $actor = User::factory()->create();
    $profile = omrDiagContractProfile($actor, 'diag.contract.shape');
    $candidate = omrDiagContractCandidate($actor, $profile, omrDiagContractArtifactSet());
    $compiled = new CompiledVersionDiagnosticTarget(omrDiagContractVersion($actor)->fresh());

    $bytes = omrDiagContractPng();
    $runner = omrDiagContractRunner(new OmrDiagContractFakeLayout, new OmrDiagContractFakeDetection);

    $candidateEvidence = $runner->verify($candidate, $bytes, 'image/png', omrDiagContractPlan($candidate, ProofPurpose::QuickRehearsal));
    $compiledEvidence = $runner->verify($compiled, $bytes, 'image/png', omrDiagContractPlan($compiled, ProofPurpose::QuickRehearsal));

    expect($candidateEvidence->succeeded())->toBeTrue()
        ->and($compiledEvidence->succeeded())->toBeTrue()
        ->and(omrDiagContractShape($candidateEvidence->normalize()))->toBe(omrDiagContractShape($compiledEvidence->normalize()))
        ->and($candidateEvidence->normalize()['evidence_hash'])->toHaveLength(64)
        ->and($candidateEvidence->normalize()['hashes']['input'])->toBe($compiledEvidence->normalize()['hashes']['input'])
        ->and($candidateEvidence->normalize()['hashes']['aligned'])->toBe($compiledEvidence->normalize()['hashes']['aligned'])
        ->and($candidateEvidence->normalize()['assets']['captured']['sha256'])->toBe(hash('sha256', $bytes))
        ->and(omrDiagContractShape($candidateEvidence->normalize()['summary']))->toBe(omrDiagContractShape($compiledEvidence->normalize()['summary']))
        ->and($candidateEvidence->normalize()['summary']['vector_match'])->toBeTrue()
        ->and($compiledEvidence->normalize()['summary']['vector_match'])->toBeTrue();
    expect($candidateEvidence->normalize()['target']['kind'])->toBe('candidate')
        ->and($candidateEvidence->normalize()['target']['qualification_capable'])->toBeFalse()
        ->and($compiledEvidence->normalize()['target']['kind'])->toBe('compiled_version')
        ->and($compiledEvidence->normalize()['target']['qualification_capable'])->toBeTrue();
});

test('expected/detected comparison drives exact and missing summaries', function (): void {
    $actor = User::factory()->create();
    $profile = omrDiagContractProfile($actor, 'diag.contract.compare');
    $candidate = omrDiagContractCandidate($actor, $profile, omrDiagContractArtifactSet());
    $bytes = omrDiagContractPng();
    $plan = omrDiagContractPlan($candidate, ProofPurpose::QuickRehearsal, mark: 'A');

    $exact = omrDiagContractRunner(new OmrDiagContractFakeLayout, new OmrDiagContractFakeDetection('A'))->verify($candidate, $bytes, 'image/png', $plan);
    expect($exact->succeeded())->toBeTrue()
        ->and($exact->summary['expected'])->toBe(1)
        ->and($exact->summary['exact'])->toBe(1)
        ->and($exact->summary['ambiguous'])->toBe(0)
        ->and($exact->summary['missing'])->toBe(0)
        ->and($exact->summary['vector_match'])->toBeTrue()
        ->and($exact->normalize()['items'][0]['exact'])->toBeTrue()
        ->and($exact->normalize()['items'][0]['detected']['selected_option'])->toBe('A')
        ->and($exact->normalize()['items'][0]['expected'])->toBe('A')
        ->and($exact->normalize()['items'][0]['options'])->toHaveCount(4)
        ->and($exact->normalize()['items'][0]['options'][0]['threshold_status'])->toBe('unqualified');

    $mismatch = omrDiagContractRunner(new OmrDiagContractFakeLayout, new OmrDiagContractFakeDetection('B'))->verify($candidate, $bytes, 'image/png', $plan);
    expect($mismatch->summary['exact'])->toBe(0)
        ->and($mismatch->summary['missing'])->toBe(1)
        ->and($mismatch->summary['vector_match'])->toBeFalse();
});

test('identity calibration digits are compared advisory', function (): void {
    $actor = User::factory()->create();
    $compiled = new CompiledVersionDiagnosticTarget(omrDiagContractVersion($actor)->fresh());
    $bytes = omrDiagContractPng();
    $plan = omrDiagContractPlan($compiled, ProofPurpose::QuickRehearsal, digit: 7);

    expect($plan->calibrationDigits)->not->toBeEmpty();

    $exact = omrDiagContractRunner(new OmrDiagContractFakeLayout, new OmrDiagContractFakeDetection(markDigit: '7'))->verify($compiled, $bytes, 'image/png', $plan);
    expect($exact->succeeded())->toBeTrue()
        ->and($exact->normalize()['identity'][0]['code'])->toBe('applicant_number')
        ->and($exact->normalize()['identity'][0]['expected_digit'])->toBe(7)
        ->and($exact->normalize()['identity'][0]['exact'])->toBeTrue();

    $mismatch = omrDiagContractRunner(new OmrDiagContractFakeLayout, new OmrDiagContractFakeDetection(markDigit: '3'))->verify($compiled, $bytes, 'image/png', $plan);
    expect($mismatch->normalize()['identity'][0]['exact'])->toBeFalse();
});

test('typed layout and detection failures keep the same evidence shape', function (): void {
    $actor = User::factory()->create();
    $profile = omrDiagContractProfile($actor, 'diag.contract.failures');
    $candidate = omrDiagContractCandidate($actor, $profile, omrDiagContractArtifactSet());
    $compiled = new CompiledVersionDiagnosticTarget(omrDiagContractVersion($actor)->fresh());
    $bytes = omrDiagContractPng();

    $layoutFailure = omrDiagContractRunner(new OmrDiagContractFakeLayout(false), new OmrDiagContractFakeDetection)->verify($candidate, $bytes, 'image/png');
    expect($layoutFailure->succeeded())->toBeFalse()
        ->and($layoutFailure->code)->toBe('marker_missing')
        ->and($layoutFailure->detail)->toContain('fixture')
        ->and($layoutFailure->target['kind'])->toBe('candidate')
        ->and($layoutFailure->hashes['input'])->toBe(hash('sha256', $bytes));

    $detectionFailure = omrDiagContractRunner(new OmrDiagContractFakeLayout(shifts: [['block' => 'Part_1', 'x' => 100, 'y' => 0]]), new OmrDiagContractFakeDetection(failCode: 'worker_unavailable'))->verify($candidate, $bytes, 'image/png');
    expect($detectionFailure->succeeded())->toBeFalse()
        ->and($detectionFailure->code)->toBe('worker_unavailable')
        ->and($detectionFailure->gates['marker_count'])->toBe('pass')
        ->and($detectionFailure->gates['shifts'])->toBe('fail')
        ->and($detectionFailure->issues)->toContain(['code' => 'sheet_misaligned', 'severity' => 'warning', 'area_code' => null, 'question_no' => null]);

    $layoutFailureAgain = omrDiagContractRunner(new OmrDiagContractFakeLayout(false), new OmrDiagContractFakeDetection)->verify($candidate, $bytes, 'image/png');
    expect(omrDiagContractShape($layoutFailure->normalize()))->toBe(omrDiagContractShape($layoutFailureAgain->normalize()))
        ->and($detectionFailure->assets['aligned']['sha256'])->toBe($detectionFailure->hashes['aligned'])
        ->and($detectionFailure->assets['overlay']['sha256'])->toBe($detectionFailure->hashes['overlay']);
});

test('candidate qualification is refused while compiled full qualification is allowed', function (): void {
    $actor = User::factory()->create();
    $profile = omrDiagContractProfile($actor, 'diag.contract.qualification');
    $candidate = omrDiagContractCandidate($actor, $profile, omrDiagContractArtifactSet());
    $compiled = new CompiledVersionDiagnosticTarget(omrDiagContractVersion($actor)->fresh());
    $bytes = omrDiagContractPng();

    $refused = omrDiagContractRunner(new OmrDiagContractFakeLayout, new OmrDiagContractFakeDetection)
        ->verify($candidate, $bytes, 'image/png', omrDiagContractPlan($candidate, ProofPurpose::FullQualification));
    expect($refused->succeeded())->toBeFalse()
        ->and($refused->code)->toBe('candidate_qualification_refused')
        ->and($refused->planPurpose)->toBe('full_qualification')
        ->and($refused->hashes['plan'])->toBe(omrDiagContractPlan($candidate, ProofPurpose::FullQualification)->planHash());

    $passed = omrDiagContractRunner(new OmrDiagContractFakeLayout, new OmrDiagContractFakeDetection)
        ->verify($compiled, $bytes, 'image/png', omrDiagContractPlan($compiled, ProofPurpose::FullQualification));
    expect($passed->succeeded())->toBeTrue()
        ->and($passed->planPurpose)->toBe('full_qualification');
});

test('plan-target hash mismatch is a typed refusal', function (): void {
    $actor = User::factory()->create();
    $profile = omrDiagContractProfile($actor, 'diag.contract.binding');
    $candidate = omrDiagContractCandidate($actor, $profile, omrDiagContractArtifactSet());
    $plan = omrDiagContractPlan($candidate, ProofPurpose::QuickRehearsal);
    $tampered = new OmrProofPlan(
        planVersion: $plan->planVersion,
        purpose: $plan->purpose,
        seed: $plan->seed,
        challengeCode: $plan->challengeCode,
        expectedVector: $plan->expectedVector,
        calibrationDigits: $plan->calibrationDigits,
        pageInstructions: $plan->pageInstructions,
        targetHashes: [...$plan->targetHashes, 'artifact' => str_repeat('f', 64)],
    );

    $evidence = omrDiagContractRunner(new OmrDiagContractFakeLayout, new OmrDiagContractFakeDetection)
        ->verify($candidate, omrDiagContractPng(), 'image/png', $tampered);
    expect($evidence->succeeded())->toBeFalse()
        ->and($evidence->code)->toBe('plan_target_mismatch');
});

test('out-of-range pages and unverifiable targets are typed exceptions', function (): void {
    $actor = User::factory()->create();
    $profile = omrDiagContractProfile($actor, 'diag.contract.pages');
    $candidate = omrDiagContractCandidate($actor, $profile, omrDiagContractArtifactSet());

    expect(fn () => omrDiagContractRunner(new OmrDiagContractFakeLayout, new OmrDiagContractFakeDetection)
        ->verify($candidate, omrDiagContractPng(), 'image/png', null, 2))
        ->toThrow(OmrDiagnosticTargetException::class, 'diagnostic_page_out_of_range');
});

test('proof plan is deterministic and bound to purpose, version, and target', function (): void {
    $actor = User::factory()->create();
    $profile = omrDiagContractProfile($actor, 'diag.contract.plan');
    $candidate = omrDiagContractCandidate($actor, $profile, omrDiagContractArtifactSet());
    $first = omrDiagContractPlan($candidate, ProofPurpose::Digital);
    $second = omrDiagContractPlan($candidate, ProofPurpose::Digital);

    expect($first->planHash())->toBe($second->planHash())
        ->and($first->planHash())->toHaveLength(64)
        ->and($first->bindsTo($first->targetHashes))->toBeTrue();

    $quick = omrDiagContractPlan($candidate, ProofPurpose::QuickRehearsal);
    expect($quick->planHash())->not->toBe($first->planHash());

    $differentTarget = omrDiagContractPlan(new CandidateArtifactDiagnosticTarget(
        omrDiagContractDraft($actor, $profile, omrDiagContractArtifactSet()),
        omrDiagContractArtifactSet(),
        $profile,
    ), ProofPurpose::Digital);
    expect($differentTarget->planHash())->not->toBe($first->planHash());
});

test('evidence prohibits applicant and score mutation fields', function (): void {
    $actor = User::factory()->create();
    $profile = omrDiagContractProfile($actor, 'diag.contract.forbidden');
    $candidate = omrDiagContractCandidate($actor, $profile, omrDiagContractArtifactSet());
    $evidence = omrDiagContractRunner(new OmrDiagContractFakeLayout, new OmrDiagContractFakeDetection)
        ->verify($candidate, omrDiagContractPng(), 'image/png', omrDiagContractPlan($candidate, ProofPurpose::QuickRehearsal));

    omrDiagContractAssertNoForbiddenKeys($evidence->normalize(), [
        'applicant_id', 'applicant_number', 'roster_id', 'score', 'scores', 'correctness',
        'converted_score', 'answer_key', 'correct_option', 'area_scores', 'per_area_scores',
        'submit', 'confirm', 'activate', 'qualify',
    ]);
    expect($evidence->normalize())->toBe($evidence->normalize());
});

test('real worker seam validates response hashes and HMAC signatures end to end', function (): void {
    $actor = User::factory()->create();
    config(['omr.omrchecker.signing_secret' => str_repeat('s', 40)]);
    $compiled = new CompiledVersionDiagnosticTarget(omrDiagContractVersion($actor)->fresh());
    $bytes = omrDiagContractPng();

    Http::fake([
        '*/layout-verification' => function ($request) {
            [$body, $headers] = omrDiagContractWorkerLayoutResponse((string) ($request->header('X-Nexiam-Run-Id')[0] ?? ''), $request->body(), (string) ($request->header('Content-Type')[0] ?? ''));

            return Http::response($body, 200, $headers);
        },
        '*/detections' => function ($request) {
            [$body, $headers] = omrDiagContractWorkerDetectionResponse((string) ($request->header('X-Nexiam-Run-Id')[0] ?? ''), $request->body(), (string) ($request->header('Content-Type')[0] ?? ''));

            return Http::response($body, 200, $headers);
        },
    ]);

    $worker = new App\Assessment\Omr\OmrCheckerDetection;
    $runner = new OmrTemplateDiagnosticRunner(app(App\Assessment\Omr\OmrDiagnosticRequestBuilder::class), $worker, $worker);
    $evidence = $runner->verify($compiled, $bytes, 'image/png', omrDiagContractPlan($compiled, ProofPurpose::QuickRehearsal));

    expect($evidence->succeeded())->toBeTrue()
        ->and($evidence->gates['marker_count'])->toBe('pass')
        ->and($evidence->gates['shifts'])->toBe('pass')
        ->and($evidence->assets['aligned']['sha256'])->toBe($evidence->hashes['aligned'])
        ->and($evidence->assets['overlay']['sha256'])->toBe($evidence->hashes['overlay'])
        ->and($evidence->hashes['input'])->toBe(hash('sha256', $bytes))
        ->and($evidence->summary['expected'])->toBeGreaterThan(0)
        ->and($evidence->normalize()['evidence_hash'])->toHaveLength(64);
});

/** Extract the JSON manifest part of an authenticated worker request body. */
function omrDiagContractManifestFromBody(string $body): array
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

/** Build an authenticated three-part layout-verification worker response. */
function omrDiagContractWorkerLayoutResponse(string $runId, string $requestBody): array
{
    $manifest = omrDiagContractManifestFromBody($requestBody);
    $frame = (array) ($manifest['native_template']['pageDimensions'] ?? [100, 100]);
    $payload = [
        'contract_version' => 'nexiam.omr.detection.v2',
        'run_id' => $runId,
        'outcome' => 'succeeded',
        'engine' => ['name' => 'OMRChecker', 'version' => 'fixture', 'commit' => str_repeat('c', 40), 'patch_identity' => 'nexiam-headless-overlay-v1'],
        'marker_matches' => ['quarter_1' => 0.9, 'quarter_2' => 0.9, 'quarter_3' => 0.9, 'quarter_4' => 0.9],
        'aligned_image' => ['mime' => 'image/png', 'width' => (int) $frame[0], 'height' => (int) $frame[1]],
        'layout_overlay' => ['mime' => 'image/png', 'width' => (int) $frame[0], 'height' => (int) $frame[1]],
        'field_block_shifts' => [['block' => 'DIAGLOGIC', 'x' => 0, 'y' => 0]],
        'bubble_means' => [],
        'item_evidence' => [],
        'timings' => ['preprocess_ms' => 10.0, 'verification_ms' => 20.0],
    ];
    $resultBytes = json_encode($payload, JSON_THROW_ON_ERROR);
    $raster = omrDiagContractPng();
    $boundary = 'nexiam-diag-contract-layout';
    $body = '--'.$boundary."\r\n"
        ."Content-Disposition: form-data; name=\"result\"\r\nContent-Type: application/json\r\n\r\n".$resultBytes."\r\n"
        .'--'.$boundary."\r\n"
        ."Content-Disposition: form-data; name=\"aligned_image\"; filename=\"aligned.png\"\r\nContent-Type: image/png\r\n\r\n".$raster."\r\n"
        .'--'.$boundary."\r\n"
        ."Content-Disposition: form-data; name=\"layout_overlay\"; filename=\"layout-overlay.png\"\r\nContent-Type: image/png\r\n\r\n".$raster."\r\n"
        .'--'.$boundary."--\r\n";
    $timestamp = (string) time();
    $resultHash = hash('sha256', $resultBytes);
    $alignedHash = hash('sha256', $raster);
    $overlayHash = hash('sha256', $raster);
    $signature = hash_hmac('sha256', implode("\n", [$timestamp, $runId, $resultHash, $alignedHash, $overlayHash]), (string) config('omr.omrchecker.signing_secret'));

    return [$body, [
        'Content-Type' => 'multipart/form-data; boundary='.$boundary,
        'X-Nexiam-Contract' => 'nexiam.omr.detection.v2',
        'X-Nexiam-Run-Id' => $runId,
        'X-Nexiam-Timestamp' => $timestamp,
        'X-Nexiam-Result-SHA256' => $resultHash,
        'X-Nexiam-Aligned-SHA256' => $alignedHash,
        'X-Nexiam-Layout-Overlay-SHA256' => $overlayHash,
        'X-Nexiam-Signature' => $signature,
    ]];
}

/** Build an authenticated two-part detection worker response echoing the request items. */
function omrDiagContractWorkerDetectionResponse(string $runId, string $requestBody): array
{
    $manifest = omrDiagContractManifestFromBody($requestBody);
    $items = array_map(static fn (array $item): array => [
        'position' => $item['position'],
        'area_code' => $item['area_code'],
        'question_no' => $item['question_no'],
        'selected_option' => 'A',
        'state' => 'marked',
        'confidence' => 0.9,
        'region' => $item['region'],
    ], (array) ($manifest['items'] ?? []));
    $payload = [
        'contract_version' => 'nexiam.omr.detection.v2',
        'run_id' => $runId,
        'input_hash' => $manifest['input_hash'] ?? '',
        'config_hash' => $manifest['config_hash'] ?? '',
        'engine' => ['name' => 'OMRChecker', 'version' => 'fixture', 'commit' => str_repeat('c', 40), 'patch_identity' => 'nexiam-headless-overlay-v1'],
        'items' => $items,
        'issues' => [],
        'identity_fields' => [],
        'sheet_confidence' => 0.9,
        'calibration_status' => 'unqualified',
        'image' => ['mime' => 'image/png', 'width' => (int) ($manifest['image_width'] ?? 100), 'height' => (int) ($manifest['image_height'] ?? 100)],
        'aligned_image' => ['mime' => 'image/png', 'width' => 100, 'height' => 100],
        'timings' => ['preprocess_ms' => 5.0, 'detection_ms' => 15.0],
    ];
    $resultBytes = json_encode($payload, JSON_THROW_ON_ERROR);
    $raster = omrDiagContractPng();
    $boundary = 'nexiam-diag-contract-detection';
    $body = '--'.$boundary."\r\n"
        ."Content-Disposition: form-data; name=\"result\"\r\nContent-Type: application/json\r\n\r\n".$resultBytes."\r\n"
        .'--'.$boundary."\r\n"
        ."Content-Disposition: form-data; name=\"aligned_image\"; filename=\"aligned.png\"\r\nContent-Type: image/png\r\n\r\n".$raster."\r\n"
        .'--'.$boundary."--\r\n";
    $timestamp = (string) time();
    $resultHash = hash('sha256', $resultBytes);
    $alignedHash = hash('sha256', $raster);
    $signature = hash_hmac('sha256', implode("\n", [$timestamp, $runId, $resultHash, $alignedHash]), (string) config('omr.omrchecker.signing_secret'));

    return [$body, [
        'Content-Type' => 'multipart/form-data; boundary='.$boundary,
        'X-Nexiam-Contract' => 'nexiam.omr.detection.v2',
        'X-Nexiam-Run-Id' => $runId,
        'X-Nexiam-Timestamp' => $timestamp,
        'X-Nexiam-Result-SHA256' => $resultHash,
        'X-Nexiam-Aligned-SHA256' => $alignedHash,
        'X-Nexiam-Signature' => $signature,
    ]];
}
