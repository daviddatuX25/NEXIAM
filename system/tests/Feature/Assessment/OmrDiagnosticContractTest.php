<?php

use App\Assessment\Actions\Omr\CreateOmrCaptureBatch;
use App\Assessment\Actions\Omr\Operational\CompareOmrDiagnosticRun;
use App\Assessment\Actions\Omr\Operational\CompleteOmrDiagnosticRun;
use App\Assessment\Actions\Omr\Operational\StartOmrDiagnosticRun;
use App\Assessment\Actions\Omr\SaveAuthoredTemplateDraft;
use App\Assessment\Omr\Canonical\CanonicalArtifactSet;
use App\Assessment\Omr\Canonical\OmrAuthoringDraftService;
use App\Assessment\Omr\Diagnostics\CandidateArtifactDiagnosticTarget;
use App\Assessment\Omr\Diagnostics\CompiledVersionDiagnosticTarget;
use App\Assessment\Omr\Diagnostics\OmrDiagnosticTarget;
use App\Assessment\Omr\Diagnostics\OmrDiagnosticTargetException;
use App\Assessment\Omr\Diagnostics\OmrProofPlan;
use App\Assessment\Omr\Diagnostics\OmrProofQrReservationPlanner;
use App\Assessment\Omr\Diagnostics\OmrTemplateDiagnosticRunner;
use App\Assessment\Omr\Diagnostics\ProofPurpose;
use App\Assessment\Omr\NativeBundle;
use App\Assessment\Omr\OmrCheckerDetection;
use App\Assessment\Omr\OmrDetectedItem;
use App\Assessment\Omr\OmrDetectionOutcome;
use App\Assessment\Omr\OmrDetectionPort;
use App\Assessment\Omr\OmrDetectionRequest;
use App\Assessment\Omr\OmrDetectionResult;
use App\Assessment\Omr\OmrDetectionSuccess;
use App\Assessment\Omr\OmrDiagnosticRequestBuilder;
use App\Assessment\Omr\OmrIdentityField;
use App\Assessment\Omr\OmrLayoutVerificationPort;
use App\Assessment\Omr\OmrLayoutVerificationResult;
use App\Assessment\Omr\OmrQrMode;
use App\Assessment\Omr\OmrRegion;
use App\Assessment\Omr\OmrRegistrationMode;
use App\Assessment\Omr\Operational\OmrDiagnosticComparisonProjector;
use App\Assessment\Omr\Operational\OmrDiagnosticEffectiveAnswerProjector;
use App\Assessment\Omr\Operational\OmrExecutionKind;
use App\Assessment\Omr\Review\GradingOmrEffectiveAnswerProjector;
use App\Assessment\Omr\Review\OmrReviewDecision;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrBlankInstancePageClaim;
use App\Models\Assessment\OmrBookletSet;
use App\Models\Assessment\OmrCaptureBatch;
use App\Models\Assessment\OmrCaptureSheet;
use App\Models\Assessment\OmrCaptureSheetIssue;
use App\Models\Assessment\OmrDiagnosticCapture;
use App\Models\Assessment\OmrDiagnosticItemEvidence;
use App\Models\Assessment\OmrDiagnosticReviewRevision;
use App\Models\Assessment\OmrDiagnosticRun;
use App\Models\Assessment\OmrItemReviewRevision;
use App\Models\Assessment\OmrMarkerProfile;
use App\Models\Assessment\OmrQuestionBooklet;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\Assessment\ScoreRecord;
use App\Models\Assessment\TestKit;
use App\Models\Assessment\TestKitAcceptancePlanRecord;
use App\Models\Assessment\TestKitVersion;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

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
        private readonly ?array $itemEvidence = null,
        private readonly ?array $alignedImage = null,
    ) {}

    public function verifyLayout(OmrDetectionRequest $request): OmrLayoutVerificationResult
    {
        if (! $this->succeed) {
            return OmrLayoutVerificationResult::failed('marker_missing', 'fixture: no registration markers found.');
        }
        $frame = is_array($request->nativeTemplate['pageDimensions'] ?? null) ? $request->nativeTemplate['pageDimensions'] : [100, 100];
        $aligned = $this->alignedImage ?? ['mime' => 'image/png', 'width' => (int) $frame[0], 'height' => (int) $frame[1]];

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
            itemEvidence: $this->itemEvidence ?? [['label' => 'LOGIC_Q_1', 'option' => 'A', 'mean' => 245.0]],
        );
    }
}

final class OmrDiagContractFakeDetection implements OmrDetectionPort
{
    public function __construct(
        private readonly string $markOption = 'A',
        private readonly string $markDigit = '7',
        private readonly ?string $failCode = null,
    ) {}

    public function detect(OmrDetectionRequest $request): OmrDetectionResult
    {
        if ($this->failCode !== null) {
            return OmrDetectionResult::failed(OmrDetectionOutcome::RetryableFailure, $this->failCode, 'fixture detection failure.');
        }
        $items = [];
        foreach ($request->items as $geometry) {
            $items[] = OmrDetectedItem::fromGeometry(
                geometry: $geometry,
                selectedOption: $this->markOption,
                state: OmrDetectedItem::STATE_MARKED,
                confidence: 0.9,
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

function omrDiagContractActor(): User
{
    seedE2Foundation();

    return User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
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
    $markerBytes = file_get_contents(base_path('app/Assessment/Omr/Fiducial/omr_marker.jpg'));
    $config = ['dimensions' => ['display_width' => 100, 'display_height' => 100], 'float_probe' => 5.0];
    $configBytes = json_encode($config, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    $configHash = hash('sha256', $configBytes);
    $template = [
        'registration_mode' => OmrRegistrationMode::FiducialAnchored->value,
        'qr_mode' => OmrQrMode::Signed->value,
        'compiled_pages' => [['page_no' => 1]],
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
    $templateBytes = json_encode($template, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    $templateHash = hash('sha256', $templateBytes);
    $referenceBytes = '<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100"></svg>';
    $referenceFileHash = hash('sha256', $referenceBytes);
    $markerHash = hash('sha256', $markerBytes);
    $fileHashes = [
        'template.json' => $templateHash,
        'config.json' => $configHash,
        'reference-page-1.svg' => $referenceFileHash,
        'marker.jpg' => $markerHash,
    ];
    $engine = [
        'name' => 'OMRChecker',
        'commit' => str_repeat('c', 40),
        'patch_set' => 'nexiam-headless-overlay-v1',
    ];
    $bundleVersion = 'nexiam.omr.native-bundle.v1';
    $nativeManifest = [
        'bundle_version' => $bundleVersion,
        'template_code' => 'DIAG-CONTRACT',
        'template_version' => 1,
        'registration_mode' => OmrRegistrationMode::FiducialAnchored->value,
        'qr_mode' => OmrQrMode::Signed->value,
        'compiled_pages' => [['page_no' => 1]],
        'engine' => $engine,
        'files' => $fileHashes,
    ];
    $manifestBytes = json_encode($nativeManifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    $fileHashes['manifest.json'] = hash('sha256', $manifestBytes);
    ksort($fileHashes);
    $bundleHash = hash('sha256', json_encode([
        'files' => $fileHashes,
        'engine' => $engine,
        'bundle_version' => $bundleVersion,
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    $referenceHash = hash('sha256', json_encode(
        ['reference-page-1.svg' => $referenceFileHash],
        JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
    ));
    $reviewManifest = [
        'manifest_version' => 'nexiam.omr.review-manifest.v1',
        'bundle_hash' => $bundleHash,
        'file_hashes' => $fileHashes,
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

    $artifactHash = hash('sha256', json_encode([
        'definition_hash' => str_repeat('d', 64),
        'files' => $fileHashes,
        'pages' => [['page_no' => 1, 'sha256' => hash('sha256', $png)]],
        'pdf_sha256' => hash('sha256', 'pdf'),
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

    return new CanonicalArtifactSet(
        definition: [
            'source_hash' => str_repeat('d', 64),
            'geometry_identity' => str_repeat('i', 64),
            'insets_px' => ['top' => 0, 'left' => 0],
            'registration_mode' => OmrRegistrationMode::FiducialAnchored->value,
            'qr_mode' => OmrQrMode::Signed->value,
            'compiled_pages' => [['page_no' => 1]],
        ],
        template: $template,
        config: $config,
        manifest: $nativeManifest,
        reviewManifest: $reviewManifest,
        reviewManifestHash: hash('sha256', json_encode($reviewManifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
        files: [
            'template.json' => $templateBytes,
            'config.json' => $configBytes,
            'reference-page-1.svg' => $referenceBytes,
            'marker.jpg' => $markerBytes,
            'manifest.json' => $manifestBytes,
        ],
        pages: [],
        pdfBytes: 'pdf',
        artifactHash: $artifactHash,
        templateHash: $templateHash,
        configHash: $configHash,
        pdfHash: str_repeat('p', 64),
        referenceHash: $referenceHash,
        markerHash: $markerHash,
        geometryIdentity: str_repeat('i', 64),
    );
}

function omrDiagContractDraft(User $actor, OmrMarkerProfile $profile, CanonicalArtifactSet $artifact, ?string $persistedArtifactHash = null, ?string $persistedDefinitionHash = null): OmrTemplateAuthoringDraft
{
    $draft = app(OmrAuthoringDraftService::class)->create($actor, 'DIAG-CONTRACT-'.strtoupper(substr(hash('sha256', $artifact->artifactHash.$actor->id.$profile->id), 0, 8)), 'Diagnostic contract draft', 'html');
    $draft->forceFill([
        'marker_profile_id' => $profile->id,
        'stage' => OmrTemplateAuthoringDraft::STAGE_MAPPING_COMPLETE,
        'authoring_json' => [
            'canonical' => ['artifact_set' => [
                'artifact_hash' => $persistedArtifactHash ?? $artifact->artifactHash,
                'geometry_identity' => $artifact->geometryIdentity,
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
        'SYN-DIAG-'.Str::upper(Str::random(8)),
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
        registrationMode: $target->registrationMode(),
        qrMode: $target->qrMode(),
        compiledPages: $target->compiledPages(),
        diagnosticQrReservation: (new OmrProofQrReservationPlanner)->plan($target),
        method: $purpose === ProofPurpose::Digital ? null : OmrProofPlan::METHOD_PRE_SHADED,
        requiredPageCount: $target->pageCount(),
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
    $actor = omrDiagContractActor();
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
    $actor = omrDiagContractActor();
    $profile = omrDiagContractProfile($actor, 'diag.contract.identity', OmrMarkerProfile::STATUS_CANDIDATE);
    $artifact = omrDiagContractArtifactSet();
    $candidateRequest = app(OmrDiagnosticRequestBuilder::class)->buildCandidate(
        $artifact,
        omrDiagContractPng(),
        'image/png',
    );
    expect($artifact->config['float_probe'])->toBe(5.0)
        ->and($candidateRequest->nativeConfigJson)->toBe($artifact->file('config.json'))
        ->and($candidateRequest->nativeConfig['float_probe'])->toBe(5);
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
        ->and($candidate->manifestHash())->toBe($artifact->reviewManifestHash)
        ->and($candidate->configHash())->toBe($artifact->configHash)
        ->and($candidate->mappingHash())->toBe($artifact->geometryIdentity)
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
    $actor = omrDiagContractActor();
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
        ->and($candidateEvidence->normalize()['target']['registration_mode'])->toBe($candidate->registrationMode()->value)
        ->and($candidateEvidence->normalize()['target']['qr_mode'])->toBe($candidate->qrMode()->value)
        ->and($candidateEvidence->normalize()['target']['compiled_pages'])->toBe($candidate->compiledPages())
        ->and($compiledEvidence->normalize()['target']['kind'])->toBe('compiled_version')
        ->and($compiledEvidence->normalize()['target']['qualification_capable'])->toBeTrue()
        ->and($compiledEvidence->normalize()['target']['registration_mode'])->toBe($compiled->registrationMode()->value)
        ->and($compiledEvidence->normalize()['target']['qr_mode'])->toBe($compiled->qrMode()->value)
        ->and($compiledEvidence->normalize()['target']['compiled_pages'])->toBe($compiled->compiledPages());
});

test('expected/detected comparison drives exact and missing summaries', function (): void {
    $actor = omrDiagContractActor();
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
    $actor = omrDiagContractActor();
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
    $actor = omrDiagContractActor();
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

test('the shared layout seam records marker, frame, and unsafe-shift gates', function (): void {
    $actor = omrDiagContractActor();
    $profile = omrDiagContractProfile($actor, 'diag.contract.gates');
    $candidate = omrDiagContractCandidate($actor, $profile, omrDiagContractArtifactSet());
    $layout = new class implements OmrLayoutVerificationPort
    {
        public int $calls = 0;

        public function verifyLayout(OmrDetectionRequest $request): OmrLayoutVerificationResult
        {
            $this->calls++;

            return new OmrLayoutVerificationResult(
                outcome: OmrLayoutVerificationResult::OUTCOME_SUCCEEDED,
                alignedImage: ['mime' => 'image/png', 'width' => 99, 'height' => 100],
                overlayImage: ['mime' => 'image/png', 'width' => 99, 'height' => 100],
                markerMatches: ['quarter_1' => 0.9, 'quarter_2' => 0.9, 'quarter_3' => 0.9],
                fieldBlockShifts: [['block' => 'Part_1', 'x' => 100, 'y' => 0]],
            );
        }
    };

    $evidence = omrDiagContractRunner($layout, new OmrDiagContractFakeDetection)
        ->verify($candidate, omrDiagContractPng(), 'image/png');

    expect($layout->calls)->toBe(1)
        ->and($evidence->succeeded())->toBeTrue()
        ->and($evidence->gates)->toMatchArray([
            'marker_count' => 'fail',
            'registration' => 'pass',
            'dimensions' => 'fail',
            'shifts' => 'fail',
            'unsafe_blocks' => ['Part_1'],
        ])
        ->and($evidence->normalize()['field_block_shifts'])->toBe([['block' => 'Part_1', 'x' => 100, 'y' => 0]]);
});

test('diagnostic runner projects corrected option rects into normalized item evidence and preserves pinned geometry on invalid or missing rects', function (): void {
    $actor = omrDiagContractActor();
    $profile = omrDiagContractProfile($actor, 'diag.contract.corrected-layout');
    $artifact = omrDiagContractArtifactSet();
    $candidate = omrDiagContractCandidate($actor, $profile, $artifact);
    $bytes = omrDiagContractPng();
    $plan = omrDiagContractPlan($candidate, ProofPurpose::QuickRehearsal);

    // Layout itemEvidence with:
    // - LOGIC_Q_1 Option A: valid pixel rect (44, 42, 10, 10) in 200x200 raster -> projects to (0.22, 0.21, 0.05, 0.05)
    // - LOGIC_Q_1 Option B: invalid out-of-bounds rect (195, 195, 20, 20) (195 + 20 > 200) -> discarded, preserves pinned
    // - LOGIC_Q_1 Option C: invalid negative-coordinate rect (-5, 20, 10, 10) -> discarded, preserves pinned
    // - LOGIC_Q_1 Option D: missing from itemEvidence options -> preserves pinned
    // - UNRECOGNIZED_Q_99: unrecognized label -> safely ignored
    $itemEvidence = [
        [
            'label' => 'LOGIC_Q_1',
            'options' => [
                ['option' => 'A', 'rect' => ['x' => 44, 'y' => 42, 'w' => 10, 'h' => 10]],
                ['option' => 'B', 'rect' => ['x' => 195, 'y' => 195, 'w' => 20, 'h' => 20]],
                ['option' => 'C', 'rect' => ['x' => -5, 'y' => 20, 'w' => 10, 'h' => 10]],
            ],
        ],
        [
            'label' => 'UNRECOGNIZED_Q_99',
            'options' => [
                ['option' => 'A', 'rect' => ['x' => 50, 'y' => 50, 'w' => 10, 'h' => 10]],
            ],
        ],
    ];

    $alignedImage = ['mime' => 'image/png', 'width' => 200, 'height' => 200];
    $layout = new OmrDiagContractFakeLayout(itemEvidence: $itemEvidence, alignedImage: $alignedImage);
    $runner = omrDiagContractRunner($layout, new OmrDiagContractFakeDetection('A'));

    // 1. Physical runner verification path
    $evidence = $runner->verify($candidate, $bytes, 'image/png', $plan);
    expect($evidence->succeeded())->toBeTrue();

    $items = collect($evidence->items)->keyBy('native_label');
    expect($items)->toHaveKey('LOGIC_Q_1')
        ->and($items)->not->toHaveKey('UNRECOGNIZED_Q_99');

    $q1 = $items['LOGIC_Q_1'];
    $q1Options = collect($q1['options'])->keyBy('code');
    expect($q1Options)->toHaveKeys(['A', 'B', 'C', 'D']);

    // Valid corrected option rect: projected into normalized coordinates using verified aligned dimensions (200x200)
    expect($q1Options['A']['region']['x'])->toEqualWithDelta(0.22, 1e-6)
        ->and($q1Options['A']['region']['y'])->toEqualWithDelta(0.21, 1e-6)
        ->and($q1Options['A']['region']['w'])->toEqualWithDelta(0.05, 1e-6)
        ->and($q1Options['A']['region']['h'])->toEqualWithDelta(0.05, 1e-6);

    // Invalid out-of-bounds rect (195 + 20 > 200): falls back safely to pinned geometry (0.32, 0.20, 0.04, 0.04)
    expect($q1Options['B']['region']['x'])->toEqualWithDelta(0.32, 1e-6)
        ->and($q1Options['B']['region']['y'])->toEqualWithDelta(0.20, 1e-6)
        ->and($q1Options['B']['region']['w'])->toEqualWithDelta(0.04, 1e-6)
        ->and($q1Options['B']['region']['h'])->toEqualWithDelta(0.04, 1e-6);

    // Invalid negative-coordinate rect (-5 < 0): falls back safely to pinned geometry (0.44, 0.20, 0.04, 0.04)
    expect($q1Options['C']['region']['x'])->toEqualWithDelta(0.44, 1e-6)
        ->and($q1Options['C']['region']['y'])->toEqualWithDelta(0.20, 1e-6)
        ->and($q1Options['C']['region']['w'])->toEqualWithDelta(0.04, 1e-6)
        ->and($q1Options['C']['region']['h'])->toEqualWithDelta(0.04, 1e-6);

    // Missing rect (option D not in layout itemEvidence): falls back safely to pinned geometry (0.56, 0.20, 0.04, 0.04)
    expect($q1Options['D']['region']['x'])->toEqualWithDelta(0.56, 1e-6)
        ->and($q1Options['D']['region']['y'])->toEqualWithDelta(0.20, 1e-6)
        ->and($q1Options['D']['region']['w'])->toEqualWithDelta(0.04, 1e-6)
        ->and($q1Options['D']['region']['h'])->toEqualWithDelta(0.04, 1e-6);

    // Item bounding region is the union of corrected option A and pinned fallback options B, C, D
    expect($q1['region']['x'])->toEqualWithDelta(0.22, 1e-6)
        ->and($q1['region']['y'])->toEqualWithDelta(0.20, 1e-6)
        ->and($q1['region']['w'])->toEqualWithDelta(0.38, 1e-6)
        ->and($q1['region']['h'])->toEqualWithDelta(0.06, 1e-6);

    // 2. Physical capture pipeline parameter binding (explicit verifiedLayout + preparedRequest)
    $preparedRequest = $candidate->buildRequest(app(OmrDiagnosticRequestBuilder::class), $bytes, 'image/png', 1);
    $verifiedLayout = $layout->verifyLayout($preparedRequest);
    $pipelineEvidence = $runner->verify(
        target: $candidate,
        bytes: $bytes,
        mime: 'image/png',
        plan: $plan,
        pageNo: 1,
        verifiedLayout: $verifiedLayout,
        preparedRequest: $preparedRequest,
    );
    expect($pipelineEvidence->succeeded())->toBeTrue();
    $pipelineItems = collect($pipelineEvidence->items)->keyBy('native_label');
    $pipeQ1Options = collect($pipelineItems['LOGIC_Q_1']['options'])->keyBy('code');
    expect($pipeQ1Options['A']['region']['x'])->toEqualWithDelta(0.22, 1e-6)
        ->and($pipeQ1Options['B']['region']['x'])->toEqualWithDelta(0.32, 1e-6)
        ->and($pipelineItems['LOGIC_Q_1']['region']['x'])->toEqualWithDelta(0.22, 1e-6);

    // 3. Fallback when an item has only invalid options (item region retains pinned geometry)
    $allInvalidLayout = new OmrDiagContractFakeLayout(
        itemEvidence: [
            [
                'label' => 'LOGIC_Q_1',
                'options' => [
                    ['option' => 'A', 'rect' => ['x' => -10, 'y' => 20, 'w' => 10, 'h' => 10]],
                    ['option' => 'B', 'rect' => ['x' => 210, 'y' => 20, 'w' => 10, 'h' => 10]],
                ],
            ],
        ],
        alignedImage: $alignedImage,
    );
    $fallbackEvidence = omrDiagContractRunner($allInvalidLayout, new OmrDiagContractFakeDetection('A'))
        ->verify($candidate, $bytes, 'image/png', $plan);
    $fallbackItems = collect($fallbackEvidence->items)->keyBy('native_label');
    $fbQ1 = $fallbackItems['LOGIC_Q_1'];
    $fbQ1Options = collect($fbQ1['options'])->keyBy('code');

    expect($fbQ1['region']['x'])->toEqualWithDelta(0.20, 1e-6)
        ->and($fbQ1['region']['y'])->toEqualWithDelta(0.20, 1e-6)
        ->and($fbQ1['region']['w'])->toEqualWithDelta(0.40, 1e-6)
        ->and($fbQ1['region']['h'])->toEqualWithDelta(0.04, 1e-6)
        ->and($fbQ1Options['A']['region']['x'])->toEqualWithDelta(0.20, 1e-6)
        ->and($fbQ1Options['B']['region']['x'])->toEqualWithDelta(0.32, 1e-6);

    // 4. Safe fallback when aligned image dimensions are zero or invalid
    $zeroDimLayout = new OmrDiagContractFakeLayout(
        itemEvidence: $itemEvidence,
        alignedImage: ['mime' => 'image/png', 'width' => 0, 'height' => 0],
    );
    $zeroDimEvidence = omrDiagContractRunner($zeroDimLayout, new OmrDiagContractFakeDetection('A'))
        ->verify($candidate, $bytes, 'image/png', $plan);
    $zeroItems = collect($zeroDimEvidence->items)->keyBy('native_label');
    $zeroQ1 = $zeroItems['LOGIC_Q_1'];
    $zeroQ1Options = collect($zeroQ1['options'])->keyBy('code');

    expect($zeroQ1Options['A']['region']['x'])->toEqualWithDelta(0.20, 1e-6)
        ->and($zeroQ1['region']['x'])->toEqualWithDelta(0.20, 1e-6)
        ->and($zeroQ1['region']['w'])->toEqualWithDelta(0.40, 1e-6);

    // 5. Multi-question compiled version target: unreferenced questions retain pinned geometry
    $compiled = new CompiledVersionDiagnosticTarget(omrDiagContractVersion($actor)->fresh());
    $compiledPlan = omrDiagContractPlan($compiled, ProofPurpose::QuickRehearsal);
    $compiledEvidence = $runner->verify($compiled, $bytes, 'image/png', $compiledPlan);
    expect($compiledEvidence->succeeded())->toBeTrue();

    $compiledItems = collect($compiledEvidence->items)->keyBy('native_label');
    $cQ1 = $compiledItems['LOGIC_Q_1'];
    $cQ1Options = collect($cQ1['options'])->keyBy('code');
    expect($cQ1Options['A']['region']['x'])->toEqualWithDelta(0.22, 1e-6);

    $compiledRequest = $compiled->buildRequest(app(OmrDiagnosticRequestBuilder::class), $bytes, 'image/png', 1);
    $pinnedCompiledItems = collect($compiledRequest->items)->keyBy('nativeLabel');
    $pinnedQ1Options = collect($pinnedCompiledItems['LOGIC_Q_1']->options)->keyBy('code');
    expect($cQ1Options['B']['region']['x'])->toEqualWithDelta($pinnedQ1Options['B']->region->x, 1e-6);

    // LOGIC_Q_2 was omitted from layout itemEvidence: item and options retain pinned geometry
    $pinnedQ2 = $pinnedCompiledItems['LOGIC_Q_2'];
    $cQ2 = $compiledItems['LOGIC_Q_2'];
    $cQ2Options = collect($cQ2['options'])->keyBy('code');
    $pinnedQ2Options = collect($pinnedQ2->options)->keyBy('code');

    expect($cQ2['region']['x'])->toEqualWithDelta($pinnedQ2->region->x, 1e-6)
        ->and($cQ2['region']['y'])->toEqualWithDelta($pinnedQ2->region->y, 1e-6)
        ->and($cQ2['region']['w'])->toEqualWithDelta($pinnedQ2->region->w, 1e-6)
        ->and($cQ2['region']['h'])->toEqualWithDelta($pinnedQ2->region->h, 1e-6);

    foreach (['A', 'B', 'C', 'D'] as $code) {
        expect($cQ2Options[$code]['region']['x'])->toEqualWithDelta($pinnedQ2Options[$code]->region->x, 1e-6)
            ->and($cQ2Options[$code]['region']['y'])->toEqualWithDelta($pinnedQ2Options[$code]->region->y, 1e-6)
            ->and($cQ2Options[$code]['region']['w'])->toEqualWithDelta($pinnedQ2Options[$code]->region->w, 1e-6)
            ->and($cQ2Options[$code]['region']['h'])->toEqualWithDelta($pinnedQ2Options[$code]->region->h, 1e-6);
    }
});

test('candidate qualification is refused while compiled full qualification is allowed', function (): void {
    $actor = omrDiagContractActor();
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
    $actor = omrDiagContractActor();
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
        registrationMode: $plan->registrationMode,
        qrMode: $plan->qrMode,
        compiledPages: $plan->compiledPages,
        diagnosticQrReservation: $plan->diagnosticQrReservation,
        method: $plan->method,
        requiredPageCount: $plan->requiredPageCount,
    );

    $evidence = omrDiagContractRunner(new OmrDiagContractFakeLayout, new OmrDiagContractFakeDetection)
        ->verify($candidate, omrDiagContractPng(), 'image/png', $tampered);
    expect($evidence->succeeded())->toBeFalse()
        ->and($evidence->code)->toBe('plan_target_mismatch');
});

test('out-of-range pages and unverifiable targets are typed exceptions', function (): void {
    $actor = omrDiagContractActor();
    $profile = omrDiagContractProfile($actor, 'diag.contract.pages');
    $candidate = omrDiagContractCandidate($actor, $profile, omrDiagContractArtifactSet());

    expect(fn () => omrDiagContractRunner(new OmrDiagContractFakeLayout, new OmrDiagContractFakeDetection)
        ->verify($candidate, omrDiagContractPng(), 'image/png', null, 2))
        ->toThrow(OmrDiagnosticTargetException::class, 'diagnostic_page_out_of_range');
});

test('proof plan is deterministic and bound to purpose, version, and target', function (): void {
    $actor = omrDiagContractActor();
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
    $actor = omrDiagContractActor();
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
    $actor = omrDiagContractActor();
    config(['omr.omrchecker.signing_secret' => str_repeat('s', 40)]);
    $version = omrDiagContractVersion($actor)->fresh();
    $compiled = new CompiledVersionDiagnosticTarget($version);
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

    $worker = new OmrCheckerDetection;
    $runner = new OmrTemplateDiagnosticRunner(app(OmrDiagnosticRequestBuilder::class), $worker, $worker);
    $evidence = $runner->verify($compiled, $bytes, 'image/png', omrDiagContractPlan($compiled, ProofPurpose::QuickRehearsal));

    $capturedManifest = null;
    Http::assertSent(function ($request) use (&$capturedManifest): bool {
        if (! str_contains((string) $request->url(), '/detections')) {
            return false;
        }
        $capturedManifest = omrDiagContractManifestFromBody($request->body());

        return true;
    });
    $pinnedBundle = NativeBundle::loadForVersion($version);
    expect($capturedManifest['native_config_json'] ?? null)->toBe($pinnedBundle->assetBytes('config.json'))
        ->and($capturedManifest)->not->toHaveKey('native_config')
        ->and(hash('sha256', (string) ($capturedManifest['native_config_json'] ?? '')))->toBe($compiled->configHash())
        ->and($capturedManifest['native_template'] ?? null)->toBe($pinnedBundle->pageTemplate(1));
    $validRequest = app(OmrDiagnosticRequestBuilder::class)->build($version, $bytes, 'image/png');
    $constructorArgs = get_object_vars($validRequest);
    unset($constructorArgs['nativeConfig']);
    $alteredBytes = array_replace($constructorArgs, [
        'nativeConfigJson' => $validRequest->nativeConfigJson.' ',
    ]);
    expect(fn () => new OmrDetectionRequest(...$alteredBytes))->toThrow(InvalidArgumentException::class);
    $alteredFileBinding = $validRequest->nativeBundleMetadata;
    $alteredFileBinding['files']['config.json'] = str_repeat('0', 64);
    expect(fn () => new OmrDetectionRequest(...array_replace($constructorArgs, [
        'nativeBundleMetadata' => $alteredFileBinding,
    ])))->toThrow(InvalidArgumentException::class);
    $nonFiniteConfigJson = '{"value":1e309}';
    $nonFiniteMetadata = $validRequest->nativeBundleMetadata;
    $nonFiniteMetadata['files']['config.json'] = hash('sha256', $nonFiniteConfigJson);
    expect(fn () => new OmrDetectionRequest(...array_replace($constructorArgs, [
        'configHash' => hash('sha256', $nonFiniteConfigJson),
        'nativeConfigJson' => $nonFiniteConfigJson,
        'nativeBundleMetadata' => $nonFiniteMetadata,
    ])))->toThrow(InvalidArgumentException::class);
    expect($evidence->succeeded())->toBeTrue()
        ->and($evidence->gates['marker_count'])->toBe('pass')
        ->and($evidence->gates['shifts'])->toBe('pass')
        ->and($evidence->assets['aligned']['sha256'])->toBe($evidence->hashes['aligned'])
        ->and($evidence->assets['overlay']['sha256'])->toBe($evidence->hashes['overlay'])
        ->and($evidence->hashes['input'])->toBe(hash('sha256', $bytes))
        ->and($evidence->summary['expected'])->toBeGreaterThan(0)
        ->and($evidence->normalize()['evidence_hash'])->toHaveLength(64);

});

function omrDiagnosticParityTestFixture(TestCase $test): array
{
    $fixture = buildOmrGradingFixture($test);
    $actor = $fixture['guidance'];
    $attempt = $fixture['attempts'][0];
    $academicCycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();

    $pair = canonicalGradingBooklet($actor);
    $template = OmrAnswerSheetTemplateVersion::query()->findOrFail($pair->template_version_id);
    $booklet = OmrQuestionBookletVersion::query()->findOrFail($pair->booklet_version_id);
    $area = AptitudeArea::query()->where('is_active', true)->firstOrFail();

    $batch = app(CreateOmrCaptureBatch::class)->handle(
        $fixture['grading'],
        $actor,
        'parity-batch-'.(string) Str::uuid(),
    );

    $hash1 = hash('sha256', 'parity-item-1');
    $hash2 = hash('sha256', 'parity-item-2');
    $hash3 = hash('sha256', 'parity-item-3');

    // 1. Production capture sheet
    $productionSheet = OmrCaptureSheet::query()->create([
        'batch_id' => $batch->id,
        'admission_attempt_id' => null,
        'detected_applicant_number' => $attempt->reference_code,
        'status' => OmrCaptureSheet::STATUS_NEEDS_REVIEW,
        'capture_mode' => OmrCaptureSheet::MODE_AUTO,
        'version' => 1,
        'client_capture_id' => (string) Str::uuid(),
        'asset_path' => 'omr/parity/'.(string) Str::uuid().'.png',
        'asset_hmac' => hash('sha256', 'parity-asset'),
        'asset_format_version' => 1,
        'asset_state' => OmrCaptureSheet::ASSET_LIVE,
        'proposal_json' => [
            'items' => [
                [
                    'position' => 1,
                    'area_code' => $area->code,
                    'area_id' => $area->id,
                    'question_no' => 1,
                    'options' => ['A', 'B', 'C', 'D'],
                    'evidence_hash' => $hash1,
                    'correct_option_code' => 'A',
                    'machine' => [
                        'state' => 'marked',
                        'selected_option' => 'A',
                        'observed_options' => ['A'],
                        'confidence' => 0.95,
                        'issue_codes' => [],
                    ],
                ],
                [
                    'position' => 2,
                    'area_code' => $area->code,
                    'area_id' => $area->id,
                    'question_no' => 2,
                    'options' => ['A', 'B', 'C', 'D'],
                    'evidence_hash' => $hash2,
                    'correct_option_code' => 'B',
                    'machine' => [
                        'state' => 'blank',
                        'selected_option' => null,
                        'observed_options' => [],
                        'confidence' => 0.98,
                        'issue_codes' => [],
                    ],
                ],
                [
                    'position' => 3,
                    'area_code' => $area->code,
                    'area_id' => $area->id,
                    'question_no' => 3,
                    'options' => ['A', 'B', 'C', 'D'],
                    'evidence_hash' => $hash3,
                    'correct_option_code' => 'C',
                    'machine' => [
                        'state' => 'ambiguous',
                        'selected_option' => null,
                        'observed_options' => ['B', 'C'],
                        'confidence' => 0.65,
                        'issue_codes' => ['ambiguous_mark'],
                    ],
                ],
            ],
        ],
    ]);

    // 2. Equivalent diagnostic run and capture
    $diagnosticRun = OmrDiagnosticRun::query()->create([
        'public_id' => (string) Str::uuid(),
        'execution_kind' => OmrDiagnosticRun::EXECUTION_KIND_DIAGNOSTIC,
        'institution_id' => (int) $academicCycle->institution_id,
        'academic_cycle_id' => (int) $academicCycle->id,
        'template_version_id' => (int) $template->id,
        'booklet_version_id' => (int) $booklet->id,
        'declared_set_code' => 'A',
        'status' => OmrDiagnosticRun::STATUS_OPEN,
        'phase' => OmrDiagnosticRun::PHASE_REVIEW,
        'revision' => 1,
        'created_by_user_id' => (int) $actor->id,
    ]);

    $inputAssetBytes = omrDiagContractPng();
    $inputAssetHash = hash('sha256', $inputAssetBytes);
    $inputAsset = OmrTemplateAsset::query()->create([
        'template_version_id' => (int) $template->id,
        'kind' => OmrTemplateAsset::KIND_EVIDENCE,
        'page_no' => 1,
        'disk' => 'sensitive',
        'path' => 'omr/diagnostic/parity-'.(string) Str::uuid().'.png',
        'mime' => 'image/png',
        'bytes' => strlen($inputAssetBytes),
        'sha256' => $inputAssetHash,
        'metadata' => [
            'role' => 'capture_input',
            'diagnostic_run_id' => (int) $diagnosticRun->id,
        ],
    ]);

    $diagnosticCapture = OmrDiagnosticCapture::query()->create([
        'diagnostic_run_id' => (int) $diagnosticRun->id,
        'client_capture_id' => (string) Str::uuid(),
        'status' => OmrDiagnosticCapture::STATUS_NEEDS_REVIEW,
        'version' => 1,
        'input_asset_id' => (int) $inputAsset->id,
        'input_asset_hash' => $inputAssetHash,
        'aligned_asset_id' => null,
        'aligned_asset_hash' => null,
        'overlay_asset_id' => null,
        'overlay_asset_hash' => null,
        'evidence_json' => ['kind' => 'diagnostic_parity_evidence'],
        'evidence_hash' => hash('sha256', 'diag-cap-1'),
        'created_by_user_id' => (int) $actor->id,
        'captured_at' => now(),
    ]);

    $diagItem1 = OmrDiagnosticItemEvidence::query()->create([
        'diagnostic_capture_id' => (int) $diagnosticCapture->id,
        'item_key' => $area->code.'_Q_1',
        'page_no' => 1,
        'area_code' => $area->code,
        'question_no' => 1,
        'option_code' => 'A',
        'option_evidence_json' => ['options' => ['A', 'B', 'C', 'D']],
        'machine_proposal_json' => [
            'state' => 'marked',
            'selected_option' => 'A',
            'confidence' => 0.95,
        ],
        'machine_confidence' => 0.95,
        'evidence_hash' => $hash1,
        'evidence_version' => 'v1',
    ]);

    $diagItem2 = OmrDiagnosticItemEvidence::query()->create([
        'diagnostic_capture_id' => (int) $diagnosticCapture->id,
        'item_key' => $area->code.'_Q_2',
        'page_no' => 1,
        'area_code' => $area->code,
        'question_no' => 2,
        'option_code' => null,
        'option_evidence_json' => ['options' => ['A', 'B', 'C', 'D']],
        'machine_proposal_json' => [
            'state' => 'blank',
            'selected_option' => null,
            'confidence' => 0.98,
        ],
        'machine_confidence' => 0.98,
        'evidence_hash' => $hash2,
        'evidence_version' => 'v1',
    ]);

    $diagItem3 = OmrDiagnosticItemEvidence::query()->create([
        'diagnostic_capture_id' => (int) $diagnosticCapture->id,
        'item_key' => $area->code.'_Q_3',
        'page_no' => 1,
        'area_code' => $area->code,
        'question_no' => 3,
        'option_code' => null,
        'option_evidence_json' => ['options' => ['A', 'B', 'C', 'D']],
        'machine_proposal_json' => [
            'state' => 'ambiguous',
            'selected_option' => null,
            'confidence' => 0.65,
        ],
        'machine_confidence' => 0.65,
        'evidence_hash' => $hash3,
        'evidence_version' => 'v1',
    ]);

    return [
        'actor' => $actor,
        'fixture' => $fixture,
        'academicCycle' => $academicCycle,
        'template' => $template,
        'booklet' => $booklet,
        'area' => $area,
        'hashes' => [$hash1, $hash2, $hash3],
        'itemKeys' => [
            $area->code.'_Q_1',
            $area->code.'_Q_2',
            $area->code.'_Q_3',
        ],
        'production' => [
            'batch' => $batch,
            'sheet' => $productionSheet,
            'projector' => app(GradingOmrEffectiveAnswerProjector::class),
        ],
        'diagnostic' => [
            'run' => $diagnosticRun,
            'capture' => $diagnosticCapture,
            'items' => [$diagItem1, $diagItem2, $diagItem3],
            'projector' => app(OmrDiagnosticEffectiveAnswerProjector::class),
        ],
    ];
}

test('production and diagnostic projectors derive equivalent observable effective answers for marked, blank, and unresolved machine states', function (): void {
    $ctx = omrDiagnosticParityTestFixture($this);
    $keys = $ctx['itemKeys'];

    $prod = $ctx['production']['projector']->project($ctx['production']['sheet']);
    $diag = $ctx['diagnostic']['projector']->project($ctx['diagnostic']['capture']);

    // Observable parity: Marked state
    $prodItem1 = collect($prod['items'])->firstWhere('item_key', $keys[0]);
    $diagItem1 = collect($diag['items'])->firstWhere('item_key', $keys[0]);
    expect($prodItem1['effective_state'])->toBe('selected')
        ->and($diagItem1['effective_state'])->toBe('selected')
        ->and($prodItem1['effective_option'])->toBe('A')
        ->and($diagItem1['effective_option'])->toBe('A')
        ->and($prodItem1['effective_source'])->toBe('machine')
        ->and($diagItem1['effective_source'])->toBe('machine')
        ->and($diag['answers'][$keys[0]])->toEqual(['state' => 'selected', 'option' => 'A']);

    // Observable parity: Blank state
    $prodItem2 = collect($prod['items'])->firstWhere('item_key', $keys[1]);
    $diagItem2 = collect($diag['items'])->firstWhere('item_key', $keys[1]);
    expect($prodItem2['effective_state'])->toBe('blank')
        ->and($diagItem2['effective_state'])->toBe('blank')
        ->and($prodItem2['effective_option'])->toBeNull()
        ->and($diagItem2['effective_option'])->toBeNull()
        ->and($prodItem2['effective_source'])->toBe('machine')
        ->and($diagItem2['effective_source'])->toBe('machine')
        ->and($diag['answers'][$keys[1]])->toEqual(['state' => 'blank', 'option' => null]);

    // Observable parity: Ambiguous / Unresolved state
    $prodItem3 = collect($prod['items'])->firstWhere('item_key', $keys[2]);
    $diagItem3 = collect($diag['items'])->firstWhere('item_key', $keys[2]);
    expect($prodItem3['effective_state'])->toBe('unresolved')
        ->and($diagItem3['effective_state'])->toBe('unresolved')
        ->and($prodItem3['effective_option'])->toBeNull()
        ->and($diagItem3['effective_option'])->toBeNull()
        ->and($prodItem3['effective_source'])->toBe('none')
        ->and($diagItem3['effective_source'])->toBe('none')
        ->and($diag['answers'][$keys[2]])->toEqual(['state' => 'unresolved', 'option' => null]);

    // Observable parity: Incomplete run status and unresolved tracking
    expect($prod['is_complete'])->toBeFalse()
        ->and($diag['is_complete'])->toBeFalse()
        ->and($prod['has_unresolved'])->toBeTrue()
        ->and($diag['has_unresolved'])->toBeTrue()
        ->and($prod['has_stale'])->toBeFalse()
        ->and($diag['has_stale'])->toBeFalse()
        ->and($prod['unresolved_item_keys'])->toContain($keys[2])
        ->and($diag['unresolved_item_keys'])->toContain($keys[2])
        ->and($prod['stale_item_keys'])->toBeEmpty()
        ->and($diag['stale_item_keys'])->toBeEmpty();
});

test('human review overrides take identical precedence across production and diagnostic projectors for SetOption and Blank', function (): void {
    $ctx = omrDiagnosticParityTestFixture($this);
    $actor = $ctx['actor'];
    $keys = $ctx['itemKeys'];
    $sheet = $ctx['production']['sheet'];
    $capture = $ctx['diagnostic']['capture'];
    $diagItems = $ctx['diagnostic']['items'];
    $hashes = $ctx['hashes'];

    // 1. Override Item 1 (marked A -> SetOption B)
    OmrItemReviewRevision::query()->create([
        'capture_sheet_id' => $sheet->id,
        'item_key' => $keys[0],
        'revision' => 1,
        'parent_version' => 1,
        'decision' => OmrReviewDecision::SetOption,
        'selected_option' => 'B',
        'reason_code' => 'correction_verified',
        'reviewed_machine_evidence_hash' => $hashes[0],
        'reviewed_by_user_id' => $actor->id,
        'command_name' => 'omr.item_review.record',
        'correlation_id' => (string) Str::uuid(),
        'idempotency_key' => 'rev-p-1-'.(string) Str::uuid(),
    ]);
    OmrDiagnosticReviewRevision::query()->create([
        'diagnostic_item_evidence_id' => $diagItems[0]->id,
        'revision' => 1,
        'parent_revision' => 0,
        'decision' => OmrReviewDecision::SetOption,
        'final_option' => 'B',
        'reason' => 'correction_verified',
        'reviewed_evidence_hash' => $hashes[0],
        'actor_id' => $actor->id,
        'idempotency_key' => 'rev-d-1-'.(string) Str::uuid(),
    ]);

    // 2. Override Item 2 (blank -> SetOption C)
    OmrItemReviewRevision::query()->create([
        'capture_sheet_id' => $sheet->id,
        'item_key' => $keys[1],
        'revision' => 1,
        'parent_version' => 1,
        'decision' => OmrReviewDecision::SetOption,
        'selected_option' => 'C',
        'reason_code' => 'correction_verified',
        'reviewed_machine_evidence_hash' => $hashes[1],
        'reviewed_by_user_id' => $actor->id,
        'command_name' => 'omr.item_review.record',
        'correlation_id' => (string) Str::uuid(),
        'idempotency_key' => 'rev-p-2-'.(string) Str::uuid(),
    ]);
    OmrDiagnosticReviewRevision::query()->create([
        'diagnostic_item_evidence_id' => $diagItems[1]->id,
        'revision' => 1,
        'parent_revision' => 0,
        'decision' => OmrReviewDecision::SetOption,
        'final_option' => 'C',
        'reason' => 'correction_verified',
        'reviewed_evidence_hash' => $hashes[1],
        'actor_id' => $actor->id,
        'idempotency_key' => 'rev-d-2-'.(string) Str::uuid(),
    ]);

    // 3. Resolve Item 3 (ambiguous -> Blank)
    OmrItemReviewRevision::query()->create([
        'capture_sheet_id' => $sheet->id,
        'item_key' => $keys[2],
        'revision' => 1,
        'parent_version' => 1,
        'decision' => OmrReviewDecision::Blank,
        'selected_option' => null,
        'reason_code' => 'candidate_erased',
        'reviewed_machine_evidence_hash' => $hashes[2],
        'reviewed_by_user_id' => $actor->id,
        'command_name' => 'omr.item_review.record',
        'correlation_id' => (string) Str::uuid(),
        'idempotency_key' => 'rev-p-3-'.(string) Str::uuid(),
    ]);
    OmrDiagnosticReviewRevision::query()->create([
        'diagnostic_item_evidence_id' => $diagItems[2]->id,
        'revision' => 1,
        'parent_revision' => 0,
        'decision' => OmrReviewDecision::Blank,
        'final_option' => null,
        'reason' => 'candidate_erased',
        'reviewed_evidence_hash' => $hashes[2],
        'actor_id' => $actor->id,
        'idempotency_key' => 'rev-d-3-'.(string) Str::uuid(),
    ]);

    $prod = $ctx['production']['projector']->project($sheet->fresh());
    $diag = $ctx['diagnostic']['projector']->project($capture->fresh());

    $prodItem1 = collect($prod['items'])->firstWhere('item_key', $keys[0]);
    $diagItem1 = collect($diag['items'])->firstWhere('item_key', $keys[0]);
    expect($prodItem1['effective_state'])->toBe('selected')
        ->and($diagItem1['effective_state'])->toBe('selected')
        ->and($prodItem1['effective_option'])->toBe('B')
        ->and($diagItem1['effective_option'])->toBe('B')
        ->and($prodItem1['effective_source'])->toBe('review')
        ->and($diagItem1['effective_source'])->toBe('review');

    $prodItem2 = collect($prod['items'])->firstWhere('item_key', $keys[1]);
    $diagItem2 = collect($diag['items'])->firstWhere('item_key', $keys[1]);
    expect($prodItem2['effective_state'])->toBe('selected')
        ->and($diagItem2['effective_state'])->toBe('selected')
        ->and($prodItem2['effective_option'])->toBe('C')
        ->and($diagItem2['effective_option'])->toBe('C')
        ->and($prodItem2['effective_source'])->toBe('review')
        ->and($diagItem2['effective_source'])->toBe('review');

    $prodItem3 = collect($prod['items'])->firstWhere('item_key', $keys[2]);
    $diagItem3 = collect($diag['items'])->firstWhere('item_key', $keys[2]);
    expect($prodItem3['effective_state'])->toBe('blank')
        ->and($diagItem3['effective_state'])->toBe('blank')
        ->and($prodItem3['effective_option'])->toBeNull()
        ->and($diagItem3['effective_option'])->toBeNull()
        ->and($prodItem3['effective_source'])->toBe('review')
        ->and($diagItem3['effective_source'])->toBe('review');

    // Both projectors are now complete and resolved
    expect($prod['is_complete'])->toBeTrue()
        ->and($diag['is_complete'])->toBeTrue()
        ->and($prod['has_unresolved'])->toBeFalse()
        ->and($diag['has_unresolved'])->toBeFalse()
        ->and($prod['has_stale'])->toBeFalse()
        ->and($diag['has_stale'])->toBeFalse()
        ->and($prod['unresolved_item_keys'])->toBeEmpty()
        ->and($diag['unresolved_item_keys'])->toBeEmpty();
});

test('human review ConfirmMachine and Unresolved decisions yield identical observable semantics across hosts', function (): void {
    $ctx = omrDiagnosticParityTestFixture($this);
    $actor = $ctx['actor'];
    $keys = $ctx['itemKeys'];
    $sheet = $ctx['production']['sheet'];
    $capture = $ctx['diagnostic']['capture'];
    $diagItems = $ctx['diagnostic']['items'];
    $hashes = $ctx['hashes'];

    // 1. ConfirmMachine on Item 1 (marked A)
    OmrItemReviewRevision::query()->create([
        'capture_sheet_id' => $sheet->id,
        'item_key' => $keys[0],
        'revision' => 1,
        'parent_version' => 1,
        'decision' => OmrReviewDecision::ConfirmMachine,
        'selected_option' => 'A',
        'reason_code' => null,
        'reviewed_machine_evidence_hash' => $hashes[0],
        'reviewed_by_user_id' => $actor->id,
        'command_name' => 'omr.item_review.record',
        'correlation_id' => (string) Str::uuid(),
        'idempotency_key' => 'rev-p-1-'.(string) Str::uuid(),
    ]);
    OmrDiagnosticReviewRevision::query()->create([
        'diagnostic_item_evidence_id' => $diagItems[0]->id,
        'revision' => 1,
        'parent_revision' => 0,
        'decision' => OmrReviewDecision::ConfirmMachine,
        'final_option' => 'A',
        'reason' => null,
        'reviewed_evidence_hash' => $hashes[0],
        'actor_id' => $actor->id,
        'idempotency_key' => 'rev-d-1-'.(string) Str::uuid(),
    ]);

    // 2. Explicit Unresolved decision on Item 2 (machine blank)
    OmrItemReviewRevision::query()->create([
        'capture_sheet_id' => $sheet->id,
        'item_key' => $keys[1],
        'revision' => 1,
        'parent_version' => 1,
        'decision' => OmrReviewDecision::Unresolved,
        'selected_option' => null,
        'reason_code' => 'unclear_candidate_intent',
        'reviewed_machine_evidence_hash' => $hashes[1],
        'reviewed_by_user_id' => $actor->id,
        'command_name' => 'omr.item_review.record',
        'correlation_id' => (string) Str::uuid(),
        'idempotency_key' => 'rev-p-2-'.(string) Str::uuid(),
    ]);
    OmrDiagnosticReviewRevision::query()->create([
        'diagnostic_item_evidence_id' => $diagItems[1]->id,
        'revision' => 1,
        'parent_revision' => 0,
        'decision' => OmrReviewDecision::Unresolved,
        'final_option' => null,
        'reason' => 'unclear_candidate_intent',
        'reviewed_evidence_hash' => $hashes[1],
        'actor_id' => $actor->id,
        'idempotency_key' => 'rev-d-2-'.(string) Str::uuid(),
    ]);

    $prod = $ctx['production']['projector']->project($sheet->fresh());
    $diag = $ctx['diagnostic']['projector']->project($capture->fresh());

    $prodItem1 = collect($prod['items'])->firstWhere('item_key', $keys[0]);
    $diagItem1 = collect($diag['items'])->firstWhere('item_key', $keys[0]);
    expect($prodItem1['effective_state'])->toBe('selected')
        ->and($diagItem1['effective_state'])->toBe('selected')
        ->and($prodItem1['effective_option'])->toBe('A')
        ->and($diagItem1['effective_option'])->toBe('A')
        ->and($prodItem1['effective_source'])->toBe('review')
        ->and($diagItem1['effective_source'])->toBe('review');

    $prodItem2 = collect($prod['items'])->firstWhere('item_key', $keys[1]);
    $diagItem2 = collect($diag['items'])->firstWhere('item_key', $keys[1]);
    expect($prodItem2['effective_state'])->toBe('unresolved')
        ->and($diagItem2['effective_state'])->toBe('unresolved')
        ->and($prodItem2['effective_option'])->toBeNull()
        ->and($diagItem2['effective_option'])->toBeNull()
        ->and($prodItem2['effective_source'])->toBe('none')
        ->and($diagItem2['effective_source'])->toBe('none');

    expect($prod['is_complete'])->toBeFalse()
        ->and($diag['is_complete'])->toBeFalse()
        ->and($prod['has_unresolved'])->toBeTrue()
        ->and($diag['has_unresolved'])->toBeTrue()
        ->and($prod['unresolved_item_keys'])->toContain($keys[1]);
});

test('stale evidence hash invalidates prior reviews and blocks completion in both production and diagnostic contexts', function (): void {
    $ctx = omrDiagnosticParityTestFixture($this);
    $actor = $ctx['actor'];
    $keys = $ctx['itemKeys'];
    $sheet = $ctx['production']['sheet'];
    $capture = $ctx['diagnostic']['capture'];
    $diagItems = $ctx['diagnostic']['items'];
    $staleHash = hash('sha256', 'older-rescanned-evidence-hash');

    // Human review was recorded against $staleHash, while current evidence has $ctx['hashes'][0]
    OmrItemReviewRevision::query()->create([
        'capture_sheet_id' => $sheet->id,
        'item_key' => $keys[0],
        'revision' => 1,
        'parent_version' => 1,
        'decision' => OmrReviewDecision::SetOption,
        'selected_option' => 'B',
        'reason_code' => 'correction_verified',
        'reviewed_machine_evidence_hash' => $staleHash,
        'reviewed_by_user_id' => $actor->id,
        'command_name' => 'omr.item_review.record',
        'correlation_id' => (string) Str::uuid(),
        'idempotency_key' => 'rev-p-stale-'.(string) Str::uuid(),
    ]);

    OmrDiagnosticReviewRevision::query()->create([
        'diagnostic_item_evidence_id' => $diagItems[0]->id,
        'revision' => 1,
        'parent_revision' => 0,
        'decision' => OmrReviewDecision::SetOption,
        'final_option' => 'B',
        'reason' => 'correction_verified',
        'reviewed_evidence_hash' => $staleHash,
        'actor_id' => $actor->id,
        'idempotency_key' => 'rev-d-stale-'.(string) Str::uuid(),
    ]);

    $prod = $ctx['production']['projector']->project($sheet->fresh());
    $diag = $ctx['diagnostic']['projector']->project($capture->fresh());

    // Both projectors detect staleness on Item 1
    $prodItem1 = collect($prod['items'])->firstWhere('item_key', $keys[0]);
    $diagItem1 = collect($diag['items'])->firstWhere('item_key', $keys[0]);
    expect($prodItem1['is_stale'])->toBeTrue()
        ->and($diagItem1['review_stale'])->toBeTrue();

    // In both production and diagnostic shared kernels, a stale review yields unresolved state and source 'none'
    expect($prodItem1['effective_state'])->toBe('unresolved')
        ->and($diagItem1['effective_state'])->toBe('unresolved')
        ->and($prodItem1['effective_option'])->toBeNull()
        ->and($diagItem1['effective_option'])->toBeNull()
        ->and($prodItem1['effective_source'])->toBe('none')
        ->and($diagItem1['effective_source'])->toBe('none')
        ->and($diag['answers'][$keys[0]])->toEqual(['state' => 'unresolved', 'option' => null]);

    // Aggregate completion and stale tracking match identically
    expect($prod['has_stale'])->toBeTrue()
        ->and($diag['has_stale'])->toBeTrue()
        ->and($prod['is_complete'])->toBeFalse()
        ->and($diag['is_complete'])->toBeFalse()
        ->and($prod['stale_item_keys'])->toContain($keys[0])
        ->and($diag['stale_item_keys'])->toContain($keys[0])
        ->and($prod['unresolved_item_keys'])->toContain($keys[0])
        ->and($diag['unresolved_item_keys'])->toContain($keys[0]);
});

test('evidence version and hash validation enforces immutability and monotonic audit history', function (): void {
    $ctx = omrDiagnosticParityTestFixture($this);
    $diagItem = $ctx['diagnostic']['items'][0];
    $capture = $ctx['diagnostic']['capture'];
    $actor = $ctx['actor'];

    // 1. Diagnostic item evidence is immutable: update and delete throw
    expect(static fn () => $diagItem->update(['option_code' => 'B']))
        ->toThrow(RuntimeException::class, 'omr_diagnostic_item_evidence_immutable');

    expect(static fn () => $diagItem->delete())
        ->toThrow(RuntimeException::class, 'omr_diagnostic_item_evidence_immutable');

    // 2. Diagnostic capture version is monotonic: decrements throw
    expect(static fn () => $capture->update(['version' => 0]))
        ->toThrow(RuntimeException::class, 'omr_diagnostic_capture_version_not_monotonic');

    // 3. Review revision is immutable once recorded
    $review = OmrDiagnosticReviewRevision::query()->create([
        'diagnostic_item_evidence_id' => $diagItem->id,
        'revision' => 1,
        'parent_revision' => 0,
        'decision' => OmrReviewDecision::ConfirmMachine,
        'final_option' => 'A',
        'reason' => null,
        'reviewed_evidence_hash' => $ctx['hashes'][0],
        'actor_id' => $actor->id,
        'idempotency_key' => 'rev-immut-'.(string) Str::uuid(),
    ]);

    expect(static fn () => $review->update(['final_option' => 'C']))
        ->toThrow(RuntimeException::class, 'omr_diagnostic_review_revision_immutable');

    expect(static fn () => $review->delete())
        ->toThrow(RuntimeException::class, 'omr_diagnostic_review_revision_immutable');
});

test('diagnostic execution, comparison, and completion perform strictly zero writes to production tables', function (): void {
    $ctx = omrDiagnosticParityTestFixture($this);
    $actor = $ctx['actor'];
    $run = $ctx['diagnostic']['run'];
    $capture = $ctx['diagnostic']['capture'];
    $diagItems = $ctx['diagnostic']['items'];
    $hashes = $ctx['hashes'];

    // Resolve all 3 items with valid reviews so the run can be completed
    OmrDiagnosticReviewRevision::query()->create([
        'diagnostic_item_evidence_id' => $diagItems[0]->id,
        'revision' => 1,
        'parent_revision' => 0,
        'decision' => OmrReviewDecision::ConfirmMachine,
        'final_option' => 'A',
        'reason' => null,
        'reviewed_evidence_hash' => $hashes[0],
        'actor_id' => $actor->id,
        'idempotency_key' => 'rev-w-1-'.(string) Str::uuid(),
    ]);
    OmrDiagnosticReviewRevision::query()->create([
        'diagnostic_item_evidence_id' => $diagItems[1]->id,
        'revision' => 1,
        'parent_revision' => 0,
        'decision' => OmrReviewDecision::Blank,
        'final_option' => null,
        'reason' => 'verified_blank',
        'reviewed_evidence_hash' => $hashes[1],
        'actor_id' => $actor->id,
        'idempotency_key' => 'rev-w-2-'.(string) Str::uuid(),
    ]);
    OmrDiagnosticReviewRevision::query()->create([
        'diagnostic_item_evidence_id' => $diagItems[2]->id,
        'revision' => 1,
        'parent_revision' => 0,
        'decision' => OmrReviewDecision::SetOption,
        'final_option' => 'C',
        'reason' => 'verified_override',
        'reviewed_evidence_hash' => $hashes[2],
        'actor_id' => $actor->id,
        'idempotency_key' => 'rev-w-3-'.(string) Str::uuid(),
    ]);

    // Snapshot production entity counts before diagnostic operations
    $beforeProductionCounts = [
        'batches' => OmrCaptureBatch::query()->count(),
        'sheets' => OmrCaptureSheet::query()->count(),
        'production_reviews' => OmrItemReviewRevision::query()->count(),
        'sheet_issues' => OmrCaptureSheetIssue::query()->count(),
        'page_claims' => OmrBlankInstancePageClaim::query()->count(),
        'score_records' => ScoreRecord::query()->count(),
        'grading_sessions' => GradingSession::query()->count(),
        'exam_sessions' => ExamSession::query()->count(),
    ];

    // 1. Diagnostic Effective Answer Projection
    $effectiveProjector = app(OmrDiagnosticEffectiveAnswerProjector::class);
    $effectiveAnswers = $effectiveProjector->project($capture);
    expect($effectiveAnswers['is_complete'])->toBeTrue();

    // 2. Diagnostic Comparison Action
    $compareAction = app(CompareOmrDiagnosticRun::class);
    $compareResult = $compareAction->handle(
        $run->fresh(),
        $actor,
        (int) $run->revision,
        'compare-key-'.(string) Str::uuid(),
    );
    expect($compareResult['outcome'])->toBe('succeeded')
        ->and($compareResult['comparison'])->toBeArray()
        ->and($run->fresh()->execution_kind)->toBe(OmrExecutionKind::Diagnostic->value);

    // 3. Diagnostic Complete Action
    $completeAction = app(CompleteOmrDiagnosticRun::class);
    $completeResult = $completeAction->handle(
        $run->fresh(),
        $actor,
        (int) $run->fresh()->revision,
        'complete-key-'.(string) Str::uuid(),
    );
    expect($completeResult['outcome'])->toBe('succeeded')
        ->and($completeResult['status'])->toBe(OmrDiagnosticRun::STATUS_COMPLETED)
        ->and($run->fresh()->status)->toBe(OmrDiagnosticRun::STATUS_COMPLETED);

    // Snapshot production entity counts after all diagnostic operations
    $afterProductionCounts = [
        'batches' => OmrCaptureBatch::query()->count(),
        'sheets' => OmrCaptureSheet::query()->count(),
        'production_reviews' => OmrItemReviewRevision::query()->count(),
        'sheet_issues' => OmrCaptureSheetIssue::query()->count(),
        'page_claims' => OmrBlankInstancePageClaim::query()->count(),
        'score_records' => ScoreRecord::query()->count(),
        'grading_sessions' => GradingSession::query()->count(),
        'exam_sessions' => ExamSession::query()->count(),
    ];

    // Strictly ZERO writes occurred on ANY production tables
    expect($afterProductionCounts)->toEqual($beforeProductionCounts);
});

test('diagnostic expected vectors remain comparison-only and do not leak into normal evidence or effective answers', function (): void {
    $ctx = omrDiagnosticParityTestFixture($this);
    $actor = $ctx['actor'];
    $run = $ctx['diagnostic']['run'];
    $capture = $ctx['diagnostic']['capture'];
    $diagProjector = $ctx['diagnostic']['projector'];

    // 1. Effective answers derivation is answer-key blind
    $effective = $diagProjector->project($capture);
    foreach ($effective['items'] as $item) {
        expect($item)->not->toHaveKey('correct_option_code')
            ->and($item)->not->toHaveKey('expected_answer')
            ->and($item)->not->toHaveKey('expected')
            ->and($item)->not->toHaveKey('is_correct');
    }

    // 2. Run comparison produces comparison-only projection with unofficial score
    $compareAction = app(CompareOmrDiagnosticRun::class);
    $compareAction->handle(
        $run,
        $actor,
        (int) $run->revision,
        'compare-comp-'.(string) Str::uuid(),
    );

    $comparisonProjector = app(OmrDiagnosticComparisonProjector::class);
    $projection = $comparisonProjector->project($capture->fresh());

    // Comparison is labeled unofficial and never a production score
    expect($projection['execution_kind'])->toBe('diagnostic')
        ->and($projection['projected_score']['official'])->toBeFalse()
        ->and($projection['projected_score']['label'])->toBe('Projected / unofficial');

    // Normal evidence items sanitized of sensitive answer-key data
    foreach ($projection['evidence']['items'] as $item) {
        expect($item)->not->toHaveKey('correct_option')
            ->and($item)->not->toHaveKey('correct_option_code')
            ->and($item)->not->toHaveKey('expected')
            ->and($item)->not->toHaveKey('expected_answer')
            ->and($item)->not->toHaveKey('answer_key');
    }

    // In contrast, production projector resolves real booklet set answer keys and produces official per-area scores
    $prodProjection = $ctx['production']['projector']->project($ctx['production']['sheet']);
    expect($prodProjection)->toHaveKey('per_area_scores')
        ->and($prodProjection['per_area_scores'])->not->toBeEmpty();
});

test('material resolution explicitly distinguishes host-specific production session context from test-owned diagnostic targets', function (): void {
    $ctx = omrDiagnosticParityTestFixture($this);
    $sheet = $ctx['production']['sheet'];
    $diagnosticRun = $ctx['diagnostic']['run'];

    // 1. Production resolver context requires valid batch and booklet pair
    // Corrupting production material association causes fail-closed typed exception
    $sheetWithMissingMaterial = clone $sheet;
    $sheetWithMissingMaterial->batch_id = 999999;
    expect(static fn () => app(GradingOmrEffectiveAnswerProjector::class)->project($sheetWithMissingMaterial))
        ->toThrow(ErrorException::class, 'Attempt to read property "gradingSession" on null');

    // 2. Diagnostic target operates without admission attempt, exam session, or seating allocation
    expect($diagnosticRun->execution_kind)->toBe(OmrDiagnosticRun::EXECUTION_KIND_DIAGNOSTIC)
        ->and($diagnosticRun->template_version_id)->toBeGreaterThan(0)
        ->and($diagnosticRun->booklet_version_id)->toBeGreaterThan(0)
        ->and($diagnosticRun->declared_set_code)->toBe('A');

    // Diagnostic run refuses non-diagnostic execution kind
    expect(static fn () => OmrDiagnosticRun::query()->create([
        'public_id' => (string) Str::uuid(),
        'execution_kind' => 'production',
        'institution_id' => $ctx['academicCycle']->institution_id,
        'academic_cycle_id' => $ctx['academicCycle']->id,
        'template_version_id' => $ctx['template']->id,
        'booklet_version_id' => $ctx['booklet']->id,
        'declared_set_code' => 'A',
    ]))->toThrow(InvalidArgumentException::class, 'omr_diagnostic_run_execution_kind_invalid');

    // Diagnostic run refuses invalid declared set code
    expect(static fn () => OmrDiagnosticRun::query()->create([
        'public_id' => (string) Str::uuid(),
        'execution_kind' => OmrDiagnosticRun::EXECUTION_KIND_DIAGNOSTIC,
        'institution_id' => $ctx['academicCycle']->institution_id,
        'academic_cycle_id' => $ctx['academicCycle']->id,
        'template_version_id' => $ctx['template']->id,
        'booklet_version_id' => $ctx['booklet']->id,
        'declared_set_code' => 'INVALID_SET',
    ]))->toThrow(InvalidArgumentException::class, 'omr_diagnostic_run_set_code_invalid');
});

test('candidate kit diagnostic start freezes the kit, manifest, plan, and protocol pins, permitting rehearsal without active status', function (): void {
    seedE2Foundation();
    $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $institution = Institution::current();
    $academicCycle = AcademicCycle::query()->where('institution_id', $institution->id)->where('is_active', true)->firstOrFail();

    $now = now();
    $profileId = DB::table('test_instruments')->insertGetId([
        'institution_id' => $institution->id,
        'code' => 'REHEARSAL-'.Str::upper(Str::random(8)),
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $profileContentSha256 = hash('sha256', 'rehearsal-profile-content');
    $profileVersionId = DB::table('test_instrument_versions')->insertGetId([
        'test_instrument_id' => $profileId,
        'version_number' => 1,
        'status' => 'approved',
        'display_name' => 'Rehearsal profile',
        'publisher_name' => 'Synthetic test fixture',
        'form_code' => 'REHEARSAL-TEST',
        'edition' => 'Test fixture',
        'language' => 'en',
        'source_reference' => 'private-test://rehearsal',
        'permitted_use_reference' => 'Test use only',
        'source_reviewed_by_user_id' => $actor->id,
        'source_reviewed_at' => $now,
        'approved_by_user_id' => $actor->id,
        'approved_at' => $now,
        'lock_version' => 1,
        'content_sha256' => $profileContentSha256,
        'created_by_user_id' => $actor->id,
        'material_requirement' => 'paper_backed',
        'administration_procedure_reference' => 'Synthetic test procedure',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    // Note: status is STATUS_COMPILED, NOT STATUS_ACTIVE!
    $templateVersion = omrDiagContractVersion($actor);
    $templateVersion->forceFill([
        'test_instrument_version_id' => $profileVersionId,
        'status' => OmrAnswerSheetTemplateVersion::STATUS_COMPILED,
        'engine_commit' => 'worker-engine-commit-abcdef',
    ])->save();

    $booklet = OmrQuestionBooklet::query()->create([
        'institution_id' => $institution->id,
        'code' => 'REHEARSAL-'.Str::upper(Str::random(8)),
        'name' => 'Rehearsal booklet fixture',
        'is_synthetic' => true,
    ]);
    $bookletVersion = OmrQuestionBookletVersion::query()->create([
        'booklet_id' => $booklet->id,
        'template_version_id' => $templateVersion->id,
        'test_instrument_version_id' => $profileVersionId,
        'version' => 1,
        'status' => OmrQuestionBookletVersion::STATUS_FINALIZED,
        'authoring_mode' => OmrQuestionBookletVersion::AUTHORING_MODE_ANSWER_KEYS_ONLY,
        'allow_multiple_sets' => false,
        'lock_version' => 1,
        'answer_key_hash' => hash('sha256', 'rehearsal-booklet-key'),
        'finalized_by_user_id' => $actor->id,
        'finalized_at' => $now,
    ]);
    $setId = DB::table('omr_booklet_sets')->insertGetId([
        'booklet_version_id' => $bookletVersion->id,
        'set_code' => 'A',
        'status' => OmrBookletSet::STATUS_FINALIZED,
        'generation_version' => 'test-kit-rehearsal-v1',
        'order_hash' => hash('sha256', 'rehearsal-order'),
        'answer_key_hash' => hash('sha256', 'rehearsal-key'),
        'created_at' => $now,
        'updated_at' => $now,
        'finalized_at' => $now,
    ]);

    $kit = TestKit::query()->create([
        'public_id' => (string) Str::uuid(),
        'institution_id' => $institution->id,
        'code' => 'KIT-'.Str::upper(Str::random(8)),
        'name' => 'Rehearsal test kit',
        'is_synthetic' => true,
        'created_by_user_id' => $actor->id,
    ]);

    $manifest = ['fixture' => 'candidate manifest for rehearsal test'];
    $manifestHash = hash('sha256', json_encode($manifest, JSON_THROW_ON_ERROR));

    // Note: kit status is STATUS_CANDIDATE, NOT STATUS_ACTIVE!
    $version = TestKitVersion::query()->create([
        'test_kit_id' => $kit->id,
        'version_number' => 1,
        'template_version_id' => $templateVersion->id,
        'booklet_version_id' => $bookletVersion->id,
        'booklet_set_id' => $setId,
        'test_instrument_version_id' => $profileVersionId,
        'booklet_set_code' => 'A',
        'booklet_set_order_hash' => hash('sha256', 'rehearsal-order'),
        'booklet_set_answer_key_hash' => hash('sha256', 'rehearsal-key'),
        'manifest_schema_version' => 1,
        'canonicalizer_version' => 'test-kit-canonicalizer-v1',
        'manifest_json' => $manifest,
        'manifest_sha256' => $manifestHash,
        'status' => TestKitVersion::STATUS_CANDIDATE,
        'revision' => 1,
        'created_by_user_id' => $actor->id,
    ]);

    $planPayload = [
        'schema_version' => 1,
        'plan_schema_id' => 'test-kit-acceptance-plan-v1',
        'cases' => [
            [
                'case_id' => 'rehearsal-case-1',
                'coverage' => ['correct'],
                'pages' => [['page_no' => 1, 'responses' => []]],
            ],
            [
                'case_id' => 'rehearsal-case-2',
                'coverage' => ['incorrect'],
                'pages' => [['page_no' => 1, 'responses' => []]],
            ],
            [
                'case_id' => 'rehearsal-case-3',
                'coverage' => ['blank'],
                'pages' => [['page_no' => 1, 'responses' => []]],
            ],
        ],
        'scoring_kernel_identity' => 'assessment-raw-evaluator-v1',
        'expected_worker_identity' => [
            'contract_version' => '1.0.0',
            'engine_name' => 'omrchecker',
            'engine_commit' => 'worker-engine-commit-abcdef',
            'patch_identity' => 'standard',
        ],
    ];
    $planSha256 = hash('sha256', 'rehearsal-plan-sha');
    $protocolSha256 = hash('sha256', 'rehearsal-protocol-sha');

    $planRecord = TestKitAcceptancePlanRecord::query()->create([
        'public_id' => (string) Str::uuid(),
        'test_kit_version_id' => $version->id,
        'manifest_sha256' => $manifestHash,
        'source_key' => 'two-item-count-v1',
        'authored_source_sha256' => hash('sha256', 'rehearsal-source-sha'),
        'protocol_schema_version' => 'test-kit-protocol-v1',
        'protocol_sha256' => $protocolSha256,
        'plan_schema_version' => 'test-kit-acceptance-plan-v1',
        'plan_sha256' => $planSha256,
        'plan_payload' => $planPayload,
        'provenance_kind' => 'synthetic_software_fixture',
        'author_reference' => 'Synthetic Rehearsal Author',
        'truth_source_references_json' => [['reference' => 'truth-ref', 'sha256' => hash('sha256', 'truth')]],
        'review_evidence_reference' => 'review-ref',
        'review_evidence_sha256' => hash('sha256', 'evidence'),
        'created_by_user_id' => $actor->id,
    ]);

    // 1. Direct action call asserts frozen pins and non-active status permission
    $run = app(StartOmrDiagnosticRun::class)->handle(
        templateVersion: null,
        bookletVersion: null,
        declaredSetCode: null,
        actor: $actor,
        institutionId: (int) $institution->id,
        academicCycleId: (int) $academicCycle->id,
        testKitVersion: $version,
        acceptancePlan: $planRecord,
    );

    expect($run)->toBeInstanceOf(OmrDiagnosticRun::class)
        ->and($run->status)->toBe(OmrDiagnosticRun::STATUS_OPEN)
        ->and($run->phase)->toBe(OmrDiagnosticRun::PHASE_CAPTURE)
        ->and($run->test_kit_id)->toBe($kit->id)
        ->and($run->test_kit_version_id)->toBe($version->id)
        ->and($run->acceptance_plan_id)->toBe($planRecord->id)
        ->and($run->manifest_sha256)->toBe($manifestHash)
        ->and($run->test_kit_manifest_sha256)->toBe($manifestHash)
        ->and($run->acceptance_protocol_schema_version)->toBe('test-kit-protocol-v1')
        ->and($run->acceptance_protocol_sha256)->toBe($protocolSha256)
        ->and($run->acceptance_plan_schema_version)->toBe('test-kit-acceptance-plan-v1')
        ->and($run->acceptance_plan_sha256)->toBe($planSha256)
        ->and($run->scoring_kernel_identity)->toBe('assessment-raw-evaluator-v1')
        ->and($run->worker_commit)->toBe('worker-engine-commit-abcdef')
        ->and($run->acceptance_plan_json['cases'])->toHaveCount(3)
        ->and($run->encrypted_acceptance_plan_snapshot['cases'])->toHaveCount(3)
        ->and($templateVersion->status)->toBe(OmrAnswerSheetTemplateVersion::STATUS_COMPILED)
        ->and($version->status)->toBe(TestKitVersion::STATUS_CANDIDATE)
        ->and($run->testKitVersion->is($version))->toBeTrue()
        ->and($run->acceptancePlan->is($planRecord))->toBeTrue();

    // 2. HTTP controller start route accepts test_kit_version_id and acceptance_plan_id
    $response = $this->actingAs($actor)->postJson(route('setup.exam-materials.omr-test.runs.start'), [
        'test_kit_version_id' => $version->id,
        'acceptance_plan_id' => $planRecord->id,
        'expected_revision' => 1,
        'idempotency_key' => (string) Str::uuid(),
    ]);

    $response->assertSuccessful();
    $data = $response->json();
    expect($data)->toHaveKey('run_id');
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
    $targetIdentity = array_key_exists('template_version_id', $manifest)
        ? ['template_version_id' => $manifest['template_version_id']]
        : ['candidate_target_id' => $manifest['candidate_target_id']];
    $payload = [
        'contract_version' => OmrDetectionRequest::ENVELOPE_VERSION,
        'registration_mode' => $manifest['registration_mode'],
        ...$targetIdentity,
        'expected_page_no' => $manifest['expected_page_no'],
        'source_image' => $manifest['source_image'],
        'run_id' => $manifest['run_id'],
        'correlation_id' => $manifest['correlation_id'],
        'outcome' => 'succeeded',
        'engine' => [
            'name' => $manifest['engine']['name'],
            'version' => 'fixture',
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
        'X-Nexiam-Contract' => OmrDetectionRequest::ENVELOPE_VERSION,
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
    $items = array_map(static function (array $item): array {
        $optionCodes = [];
        foreach ((array) ($item['options'] ?? []) as $option) {
            $code = is_array($option) ? (string) ($option['code'] ?? '') : (string) $option;
            if ($code !== '') {
                $optionCodes[] = $code;
            }
        }
        if ($optionCodes === []) {
            $optionCodes = ['A', 'B', 'C', 'D'];
        }

        $optionObservations = array_map(static fn (string $code): array => [
            'code' => $code,
            'mean' => $code === 'A' ? 245.0 : null,
            'classification' => $code === 'A' ? 'observed' : 'not_observed',
        ], $optionCodes);

        return [
            'position' => $item['position'],
            'area_code' => $item['area_code'],
            'question_no' => $item['question_no'],
            'selected_option' => 'A',
            'state' => 'marked',
            'confidence' => 0.9,
            'region' => $item['region'],
            'observed_options' => ['A'],
            'option_observations' => $optionObservations,
            'issue_codes' => [],
            'legacy_evidence_limited' => false,
        ];
    }, (array) ($manifest['items'] ?? []));
    $targetIdentity = array_key_exists('template_version_id', $manifest)
        ? ['template_version_id' => $manifest['template_version_id']]
        : ['candidate_target_id' => $manifest['candidate_target_id']];
    $payload = [
        'contract_version' => OmrDetectionRequest::ENVELOPE_VERSION,
        'registration_mode' => $manifest['registration_mode'],
        ...$targetIdentity,
        'expected_page_no' => $manifest['expected_page_no'],
        'source_image' => $manifest['source_image'],
        'run_id' => $manifest['run_id'],
        'correlation_id' => $manifest['correlation_id'],
        'input_hash' => $manifest['input_hash'],
        'config_hash' => $manifest['config_hash'],
        'native_bundle_hash' => $manifest['native_bundle_hash'],
        'manifest_hash' => $manifest['manifest_hash'],
        'booklet_hash' => $manifest['booklet_hash'],
        'engine' => [
            'name' => $manifest['engine']['name'],
            'version' => 'fixture',
            'commit' => $manifest['engine']['commit'],
            'patch_identity' => $manifest['engine']['patch_identity'],
        ],
        'items' => $items,
        'issues' => [],
        'identity_fields' => [],
        'sheet_confidence' => 0.9,
        'calibration_status' => 'unqualified',
        'image' => [
            'mime' => $manifest['image_mime'],
            'width' => (int) $manifest['image_width'],
            'height' => (int) $manifest['image_height'],
        ],
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
        'X-Nexiam-Contract' => OmrDetectionRequest::ENVELOPE_VERSION,
        'X-Nexiam-Run-Id' => $runId,
        'X-Nexiam-Timestamp' => $timestamp,
        'X-Nexiam-Result-SHA256' => $resultHash,
        'X-Nexiam-Aligned-SHA256' => $alignedHash,
        'X-Nexiam-Signature' => $signature,
    ]];
}
