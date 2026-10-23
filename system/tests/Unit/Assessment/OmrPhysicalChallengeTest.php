<?php

use App\Assessment\Omr\Canonical\OmrCalibrationQrPayload;
use App\Assessment\Omr\Canonical\OmrQrPayloadException;
use App\Assessment\Omr\Canonical\OmrQrReservationResolver;
use App\Assessment\Omr\Diagnostics\OmrDiagnosticScenarioManifest;
use App\Assessment\Omr\Diagnostics\OmrDiagnosticTarget;
use App\Assessment\Omr\Diagnostics\OmrDiagnosticTargetException;
use App\Assessment\Omr\Diagnostics\OmrManifestBubbleShader;
use App\Assessment\Omr\Diagnostics\OmrPhysicalChallengeRenderer;
use App\Assessment\Omr\Diagnostics\OmrProofPlan;
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
use App\Assessment\Omr\OmrLayoutVerificationResult;
use App\Assessment\Omr\OmrQrMode;
use App\Assessment\Omr\OmrRegistrationMode;
use App\Printing\PrintRenderer;
use Illuminate\Support\Facades\Http;
use Tests\Fixtures\Omr\ProofPlanFixtureTarget;

/**
 * OmrPhysicalChallengeRenderer golden tests (OpenSpec 35.19 + 35.20).
 *
 * Pins: exact printable sheet with signed calibration QR/display code and no
 * browser geometry, separate grouped human shading guide (quick = first/
 * middle/last + every option/section/ID column; full = every expanded label),
 * stable guide copy, multi-page order, challenge QR verification, and
 * byte/hash stability of repeated rendering.
 */
function omrPhysicalUnitPdfBytes(): string
{
    return app(PrintRenderer::class)->render(
        '<html><head><style>body{margin:0;padding:0}</style></head><body><div style="width:200mm;height:280mm;border:1px solid #ccc"></div></body></html>',
        ['format' => 'a4', 'orientation' => 'portrait'],
    );
}

function omrPhysicalUnitSinglePagePdfBytes(): string
{
    return app(PrintRenderer::class)->render(
        '<html><head><style>body{margin:0;padding:0}</style></head><body><div style="width:180mm;height:240mm;border:1px solid #ccc"></div></body></html>',
        ['format' => 'a4', 'orientation' => 'portrait'],
    );
}
function challengeRenderer(): OmrPhysicalChallengeRenderer
{
    config([
        'omr.qr-keyring.current' => 'challenge-v1',
        'omr.qr-keyring.keys' => ['challenge-v1' => 'challenge-secret'],
    ]);

    return app(OmrPhysicalChallengeRenderer::class);
}

test('quick guide covers first/middle/last plus every option, section, and ID column', function (): void {
    $result = challengeRenderer()->render(
        ProofPlanFixtureTarget::standard(identityMapped: true),
        ProofPurpose::QuickRehearsal,
        omrPhysicalUnitPdfBytes(),
        ['left' => 142, 'top' => 142, 'right' => 142, 'bottom' => 142],
    );

    $html = $result['guide']['html'];

    expect($result['guide']['mode'])->toBe('quick')
        // Quick picks: LOGIC first/middle/last, VERBAL first/middle/last.
        ->and($html)->toContain('LOGIC_Q_1')
        ->and($html)->toContain('LOGIC_Q_5')
        ->and($html)->toContain('LOGIC_Q_10')
        ->and($html)->toContain('VERBAL_Q_1')
        ->and($html)->toContain('VERBAL_Q_2')
        ->and($html)->toContain('VERBAL_Q_4')
        // Middle-of-quick labels are NOT listed.
        ->and($html)->not->toContain('LOGIC_Q_2')
        ->and($html)->not->toContain('LOGIC_Q_3')
        ->and($html)->not->toContain('VERBAL_Q_3')
        // Every option column and every section legend.
        ->and($html)->toContain('Options: A, B, C, D')
        ->and($html)->toContain('Options: A, B, C, D, E')
        ->and($html)->toContain('Section LOGIC')
        ->and($html)->toContain('Section VERBAL')
        // Every ID column with its calibration digit.
        ->and($html)->toContain('Applicant number columns (calibration)');
    foreach ($result['plan']->calibrationDigits as $label => $digit) {
        expect($html)->toContain($label)->toContain((string) $digit);
    }
});

test('full guide covers every expanded label', function (): void {
    $result = challengeRenderer()->render(
        ProofPlanFixtureTarget::standard(identityMapped: true),
        ProofPurpose::FullQualification,
        omrPhysicalUnitPdfBytes(),
    );

    $html = $result['guide']['html'];
    expect($result['guide']['mode'])->toBe('full');
    foreach (array_keys($result['plan']->expectedVector) as $label) {
        expect($html)->toContain($label);
    }
    // Full mode includes labels quick mode omits.
    expect($html)->toContain('LOGIC_Q_2')->toContain('LOGIC_Q_3')->toContain('VERBAL_Q_3');
});

test('guide copy and JSON are stable across repeated rendering', function (): void {
    $renderer = challengeRenderer();
    $target = ProofPlanFixtureTarget::standard(identityMapped: true);

    $first = $renderer->render($target, ProofPurpose::QuickRehearsal, omrPhysicalUnitPdfBytes());
    $second = $renderer->render($target, ProofPurpose::QuickRehearsal, omrPhysicalUnitPdfBytes());

    expect($second['guide']['html'])->toBe($first['guide']['html'])
        ->and($second['guide']['sha256'])->toBe($first['guide']['sha256'])
        ->and($second['sections'])->toBe($first['sections'])
        ->and($second['guide_json'])->toBe($first['guide_json'])
        ->and($first['guide_json']['schema_version'])->toBe('v2')
        ->and($first['guide_json']['mode'])->toBe('quick')
        ->and($first['guide_json']['challenge_code'])->toBe($first['plan']->challengeCode)
        ->and($first['guide_json']['calibration_disclaimer'])->toContain('calibration only')
        ->and($first['guide_json']['sections'])->not->toBeEmpty()
        ->and($first['guide_json']['identity_columns'])->not->toBeEmpty()
        // The guide copy is byte-stable; the DomPDF wrapper adds a creation
        // timestamp, so only its validity is asserted here.
        ->and($first['guide']['pdf'])->toStartWith('%PDF')
        ->and($first['guide']['pdf'])->not->toBeEmpty();
});
test('hand-shaded sheet stays blank while pre-shaded sheet renders diagnostic scenarios', function (): void {
    $renderer = challengeRenderer();
    $target = ProofPlanFixtureTarget::sixAreas(identityMapped: true);
    $insets = ['left' => 142, 'top' => 142, 'right' => 142, 'bottom' => 142];
    $hand = $renderer->render($target, ProofPurpose::QuickRehearsal, omrPhysicalUnitSinglePagePdfBytes(), $insets, OmrProofPlanner::CURRENT_PLAN_VERSION, OmrProofPlan::METHOD_HAND_SHADED);
    $pre = $renderer->render($target, ProofPurpose::QuickRehearsal, omrPhysicalUnitSinglePagePdfBytes(), $insets, OmrProofPlanner::CURRENT_PLAN_VERSION, OmrProofPlan::METHOD_PRE_SHADED);

    expect($hand['plan']->expectedVector)->toHaveCount(18)
        ->and($hand['guide']['html'])->toContain('<title>Shading instructions</title>')
        ->and($hand['guide']['html'])->toContain('Leave every unlisted answer row blank')
        ->and($hand['guide']['html'])->toContain('intentionally tests a missing answer')
        ->and($pre['scenario_manifest']['plan'])->toBe(OmrDiagnosticScenarioManifest::QUICK_REHEARSAL_V2);

    $handImage = imagecreatefromstring($hand['sheet']['pages'][0]['bytes']);
    $preImage = imagecreatefromstring($pre['sheet']['pages'][0]['bytes']);
    expect($handImage)->not->toBeFalse()->and($preImage)->not->toBeFalse();

    foreach ($target->reviewManifest()['fields'] as $field) {
        if (str_starts_with((string) $field['label'], 'APPLICANT_NUMBER_')) {
            continue;
        }
        $x = (int) round($field['pixel']['x'] + $insets['left'] + $field['pixel']['w'] / 2);
        $y = (int) round($field['pixel']['y'] + $insets['top'] + $field['pixel']['h'] / 2);
        expect(imagecolorat($handImage, $x, $y) & 0xFF)->toBeGreaterThan(200);
    }

    $darkScenario = collect($pre['scenario_manifest']['scenarios'])->first(
        static fn (array $scenario): bool => $scenario['mark']['style'] === OmrDiagnosticScenarioManifest::MARK_NORMAL_DARK
            && $scenario['answer']['candidates'] !== [],
    );
    $darkField = collect($target->reviewManifest()['fields'])->first(
        static fn (array $field): bool => $field['label'] === $darkScenario['item']['label']
            && $field['option'] === $darkScenario['answer']['candidates'][0],
    );
    $darkX = (int) round($darkField['pixel']['x'] + $insets['left'] + $darkField['pixel']['w'] / 2);
    $darkY = (int) round($darkField['pixel']['y'] + $insets['top'] + $darkField['pixel']['h'] / 2);
    expect(imagecolorat($preImage, $darkX, $darkY) & 0xFF)->toBeLessThan(30);

    $blankScenario = collect($pre['scenario_manifest']['scenarios'])->first(
        static fn (array $scenario): bool => $scenario['answer']['condition'] === OmrDiagnosticScenarioManifest::ANSWER_BLANK,
    );
    foreach ($target->reviewManifest()['fields'] as $field) {
        if ($field['label'] !== $blankScenario['item']['label']) {
            continue;
        }
        $x = (int) round($field['pixel']['x'] + $insets['left'] + $field['pixel']['w'] / 2);
        $y = (int) round($field['pixel']['y'] + $insets['top'] + $field['pixel']['h'] / 2);
        expect(imagecolorat($preImage, $x, $y) & 0xFF)->toBeGreaterThan(200);
    }

    imagedestroy($handImage);
    imagedestroy($preImage);
});

test('sheet rendering produces deterministic Actual-Size PDF and retained page images', function (): void {
    $renderer = challengeRenderer();
    $target = ProofPlanFixtureTarget::standard(identityMapped: true);

    $first = $renderer->render($target, ProofPurpose::FullQualification, omrPhysicalUnitPdfBytes());
    $second = $renderer->render($target, ProofPurpose::FullQualification, omrPhysicalUnitPdfBytes());

    expect($first['sheet']['pages'])->toHaveCount(2)
        ->and(array_column($first['sheet']['pages'], 'page_no'))->toBe([1, 2])
        ->and(array_column($first['sheet']['qr_payloads'], 'page_no'))->toBe([1, 2])
        ->and($first['sheet']['display_code'])->toMatch('/^CH-[0-9A-HJKMNP-TV-Z]{6}[0-9A-HJKMNP-TV-Z]$/')
        ->and($first['sheet']['pdf_bytes'])->toStartWith('%PDF')
        ->and($first['sheet']['pdf_sha256'])->toHaveLength(64)
        ->and($second['sheet']['pdf_bytes'])->toBe($first['sheet']['pdf_bytes'])
        ->and($second['sheet']['pdf_sha256'])->toBe($first['sheet']['pdf_sha256']);

    $pages = $first['sheet']['pages'];
    $again = $second['sheet']['pages'];
    expect($again[0]['sha256'])->toBe($pages[0]['sha256'])
        ->and($again[1]['sha256'])->toBe($pages[1]['sha256'])
        ->and($again[0]['bytes'])->toBe($pages[0]['bytes'])
        ->and($second['sheet']['sheet_hash'])->toBe($first['sheet']['sheet_hash'])
        ->and($second['sheet']['challenge_code'])->toBe($first['sheet']['challenge_code'])
        ->and($first['sheet']['sheet_hash'])->toHaveLength(64);
});

test('challenge QR verifies, rejects tampering, and rejects unknown keys', function (): void {
    $renderer = challengeRenderer();
    $result = $renderer->render(
        ProofPlanFixtureTarget::standard(identityMapped: true),
        ProofPurpose::QuickRehearsal,
        omrPhysicalUnitPdfBytes(),
    );

    $payloads = $result['sheet']['qr_payloads'];
    expect($payloads)->toHaveCount(2);

    $qr = app(OmrCalibrationQrPayload::class);
    $verified = $qr->verify($payloads[0]['payload']);
    expect($verified['challenge_code'])->toBe($result['plan']->challengeCode)
        ->and($verified['page_no'])->toBe(1)
        ->and($verified['page_count'])->toBe(2)
        ->and($verified['signing_key_id'])->toBe('challenge-v1')
        ->and($verified['signature_version'])->toBe(OmrCalibrationQrPayload::SIGNATURE_VERSION)
        ->and($verified['payload_hash'])->toBe($payloads[0]['payload_hash'])
        ->and($payloads[1]['payload'])->not->toBe($payloads[0]['payload']);

    $tampered = substr($payloads[0]['payload'], 0, -1).($payloads[0]['payload'][-1] === 'a' ? 'b' : 'a');
    expect(fn () => $qr->verify($tampered))->toThrow(OmrQrPayloadException::class, 'qr_signature_invalid');

    config(['omr.qr-keyring.keys' => []]);
    try {
        expect(fn () => (new OmrCalibrationQrPayload)->verify($payloads[0]['payload']))
            ->toThrow(OmrQrPayloadException::class, 'qr_key_unknown');
    } finally {
        config(['omr.qr-keyring.keys' => ['challenge-v1' => 'challenge-secret']]);
    }
});

test('unmapped identity produces no calibration digits and no guide identity section', function (): void {
    $renderer = challengeRenderer();
    $result = $renderer->render(
        ProofPlanFixtureTarget::standard(identityMapped: false),
        ProofPurpose::QuickRehearsal,
        omrPhysicalUnitPdfBytes(),
    );

    expect($result['plan']->calibrationDigits)->toBe([])
        ->and($result['guide']['html'])->not->toContain('Applicant number columns')
        ->and($result['guide']['html'])->not->toContain('APPLICANT_NUMBER_');
});

final class CustomManifestTarget implements OmrDiagnosticTarget
{
    public function __construct(
        private readonly OmrDiagnosticTarget $base,
        private readonly ?array $overrideFields = null,
        private readonly ?array $rawManifest = null,
    ) {}

    public function kind(): string
    {
        return $this->base->kind();
    }

    public function targetId(): string
    {
        return $this->base->targetId();
    }

    public function pageCount(): int
    {
        return $this->base->pageCount();
    }

    public function registrationMode(): OmrRegistrationMode
    {
        return $this->base->registrationMode();
    }

    public function qrMode(): OmrQrMode
    {
        return $this->base->qrMode();
    }

    public function compiledPages(): array
    {
        return $this->base->compiledPages();
    }

    public function paperDimensionsMm(int $pageNo): array
    {
        return $this->base->paperDimensionsMm($pageNo);
    }

    public function visualComponents(int $pageNo): array
    {
        return $this->base->visualComponents($pageNo);
    }

    public function nativeTemplate(int $pageNo): array
    {
        return $this->base->nativeTemplate($pageNo);
    }

    public function nativeConfig(): array
    {
        return $this->base->nativeConfig();
    }

    public function nativeAssets(): array
    {
        return $this->base->nativeAssets();
    }

    public function markerAssetName(): ?string
    {
        return $this->base->markerAssetName();
    }

    public function markerAssetHash(): ?string
    {
        return $this->base->markerAssetHash();
    }

    public function markerProfileCode(): ?string
    {
        return $this->base->markerProfileCode();
    }

    public function markerProfileVersion(): ?int
    {
        return $this->base->markerProfileVersion();
    }

    public function markerProfileQualified(): bool
    {
        return $this->base->markerProfileQualified();
    }

    public function artifactHash(): string
    {
        return $this->base->artifactHash();
    }

    public function nativeBundleHash(): string
    {
        return $this->base->nativeBundleHash();
    }

    public function manifestHash(): string
    {
        return hash('sha256', json_encode($this->reviewManifest(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    public function configHash(): string
    {
        return $this->base->configHash();
    }

    public function mappingHash(): string
    {
        return $this->base->mappingHash();
    }

    public function engineName(): string
    {
        return $this->base->engineName();
    }

    public function engineCommit(): ?string
    {
        return $this->base->engineCommit();
    }

    public function patchIdentity(): string
    {
        return $this->base->patchIdentity();
    }

    public function qualificationCapable(): bool
    {
        return $this->base->qualificationCapable();
    }

    public function buildRequest(OmrDiagnosticRequestBuilder $builder, string $imageBytes, string $imageMime, int $pageNo): OmrDetectionRequest
    {
        return $this->base->buildRequest($builder, $imageBytes, $imageMime, $pageNo);
    }

    public function reviewManifest(): array
    {
        if ($this->rawManifest !== null) {
            return $this->rawManifest;
        }
        $manifest = $this->base->reviewManifest();
        if ($this->overrideFields !== null) {
            $manifest['fields'] = $this->overrideFields;
        }

        return $manifest;
    }
}

test('missing field geometry fails with challenge_geometry_missing', function (): void {
    $base = ProofPlanFixtureTarget::standard(identityMapped: true);
    $fields = $base->reviewManifest()['fields'];
    // Move LOGIC_Q_1 to page 2 in the manifest so page 1 is missing geometry for LOGIC_Q_1
    $modifiedFields = array_map(
        static fn (array $f): array => $f['label'] === 'LOGIC_Q_1' ? [...$f, 'page' => 2] : $f,
        $fields
    );
    $target = new CustomManifestTarget($base, overrideFields: $modifiedFields);

    try {
        challengeRenderer()->render($target, ProofPurpose::QuickRehearsal, omrPhysicalUnitPdfBytes());
        $this->fail('Expected OmrDiagnosticTargetException not thrown');
    } catch (OmrDiagnosticTargetException $e) {
        expect($e->errorCode)->toBe('challenge_geometry_missing')
            ->and($e->getMessage())->toContain('challenge_geometry_missing')
            ->and($e->getMessage())->toContain('LOGIC_Q_1');
    }
});

test('duplicate field geometry fails with challenge_geometry_duplicate', function (): void {
    $base = ProofPlanFixtureTarget::standard(identityMapped: true);
    $fields = $base->reviewManifest()['fields'];
    $duplicatedFields = [...$fields, $fields[0]];
    $target = new CustomManifestTarget($base, overrideFields: $duplicatedFields);

    try {
        challengeRenderer()->render($target, ProofPurpose::QuickRehearsal, omrPhysicalUnitPdfBytes());
        $this->fail('Expected OmrDiagnosticTargetException not thrown');
    } catch (OmrDiagnosticTargetException $e) {
        expect($e->errorCode)->toBe('challenge_geometry_duplicate')
            ->and($e->getMessage())->toContain('challenge_geometry_duplicate');
    }
});
test('physical challenge QR uses the authored top-left anchor and exact matrix size', function (): void {
    $base = ProofPlanFixtureTarget::standard(identityMapped: true, multiPage: false);
    $manifest = $base->reviewManifest();
    $manifest['qr_reservation'] = [
        'x' => 1181,
        'y' => 307,
        'width' => 402,
        'height' => 402,
    ];

    $target = new CustomManifestTarget($base, rawManifest: $manifest);
    $result = challengeRenderer()->render($target, ProofPurpose::QuickRehearsal, omrPhysicalUnitSinglePagePdfBytes());

    expect($result['sheet']['pages'])->toHaveCount(1);
    $pageBytes = $result['sheet']['pages'][0]['bytes'];
    expect($pageBytes)->not->toBeEmpty();

    $resolved = OmrQrReservationResolver::resolvePx($manifest, 2480, 3508);
    expect($resolved['has_reservation'])->toBeTrue()
        ->and($resolved['qr_x'])->toBe(1181)
        ->and($resolved['qr_y'])->toBe(307)
        ->and($resolved['qr_size'])->toBe(402)
        ->and($resolved['display_code_y'])->toBeGreaterThanOrEqual(709);
});

test('invalid field geometry fails with challenge_geometry_invalid', function (): void {
    $base = ProofPlanFixtureTarget::standard(identityMapped: true);
    $fields = $base->reviewManifest()['fields'];
    $corruptedFields = $fields;
    $corruptedFields[0]['pixel']['w'] = 4;
    $corruptedFields[0]['pixel']['h'] = 4;
    $target = new CustomManifestTarget($base, overrideFields: $corruptedFields);

    try {
        challengeRenderer()->render($target, ProofPurpose::QuickRehearsal, omrPhysicalUnitPdfBytes());
        $this->fail('Expected OmrDiagnosticTargetException not thrown');
    } catch (OmrDiagnosticTargetException $e) {
        expect($e->errorCode)->toBe('challenge_geometry_invalid')
            ->and($e->getMessage())->toContain('challenge_geometry_invalid');
    }
});

test('out-of-bounds field geometry fails with challenge_geometry_out_of_bounds', function (): void {
    $base = ProofPlanFixtureTarget::standard(identityMapped: true);
    $fields = $base->reviewManifest()['fields'];
    $outOfBoundsFields = $fields;
    $outOfBoundsFields[0]['pixel']['x'] = 2400;
    $outOfBoundsFields[0]['pixel']['w'] = 100;
    $target = new CustomManifestTarget($base, overrideFields: $outOfBoundsFields);

    try {
        challengeRenderer()->render($target, ProofPurpose::QuickRehearsal, omrPhysicalUnitPdfBytes(), ['left' => 142, 'top' => 142]);
        $this->fail('Expected OmrDiagnosticTargetException not thrown');
    } catch (OmrDiagnosticTargetException $e) {
        expect($e->errorCode)->toBe('challenge_geometry_out_of_bounds')
            ->and($e->getMessage())->toContain('challenge_geometry_out_of_bounds');
    }
});

test('53x53 option geometry pixel regression confirms dark center, tight centroid, proportional diameter, and white neighbor', function (): void {
    $pageOne = [
        'page_no' => 1,
        'blocks' => [
            'PART_1' => [
                'fieldType' => 'QTYPE_MCQ4',
                'direction' => 'horizontal',
                'fieldLabels' => ['PART_1_Q_1'],
                'origin' => [106, 1134],
                'bubblesGap' => 60,
                'labelsGap' => 80,
                'bubbleDimensions' => [53, 53],
            ],
        ],
    ];
    $target = new ProofPlanFixtureTarget([$pageOne], identityMapped: false);
    $renderer = challengeRenderer();
    $insets = ['left' => 142, 'top' => 142, 'right' => 142, 'bottom' => 142];

    $result = $renderer->render($target, ProofPurpose::QuickRehearsal, omrPhysicalUnitSinglePagePdfBytes(), $insets);

    $pageBytes = $result['sheet']['pages'][0]['bytes'];
    $img = imagecreatefromstring($pageBytes);
    expect($img)->not->toBeFalse();

    // Option A paper bounding box: [248, 1276, 248 + 53 = 301, 1276 + 53 = 1329]
    // Expected center: cx = 275 (248 + 26.5), cy = 1303 (1276 + 26.5)
    $cxA = 275;
    $cyA = 1303;

    // 1. Center of selected Option A is dark
    $centerColorA = imagecolorat($img, $cxA, $cyA) & 0xFF;
    expect($centerColorA)->toBeLessThan(30);

    // 2. Centroid of Option A fill is centered within tight tolerance (<= 1.0 px)
    $sumX = 0;
    $sumY = 0;
    $darkCountA = 0;
    for ($y = 1276; $y <= 1329; $y++) {
        for ($x = 248; $x <= 301; $x++) {
            $lum = imagecolorat($img, $x, $y) & 0xFF;
            if ($lum < 128) {
                $sumX += $x;
                $sumY += $y;
                $darkCountA++;
            }
        }
    }
    expect($darkCountA)->toBeGreaterThan(0);
    $centroidX = $sumX / $darkCountA;
    $centroidY = $sumY / $darkCountA;

    expect(abs($centroidX - $cxA))->toBeLessThanOrEqual(1.0)
        ->and(abs($centroidY - $cyA))->toBeLessThanOrEqual(1.0);

    // 3. Proportional diameter: fill scales with 53px geometry (47px diameter => ~1700 dark pixels, NOT ~314 for a 20px dot)
    expect($darkCountA)->toBeGreaterThan(1500)
        ->and($darkCountA)->toBeLessThan(2000);

    // Horizontal dark span along cy = 1303 across Option A box
    $darkSpanX = 0;
    for ($x = 248; $x <= 301; $x++) {
        if ((imagecolorat($img, $x, $cyA) & 0xFF) < 128) {
            $darkSpanX++;
        }
    }
    expect($darkSpanX)->toBeGreaterThanOrEqual(45)
        ->and($darkSpanX)->toBeLessThanOrEqual(49);

    // 4. Red-capability proof: with the old 20px bug, center was at (258, 1286) and (275, 1303) was white.
    // Centroid is NOT at the old buggy coordinate (258, 1286)
    expect(abs($centroidX - 258))->toBeGreaterThan(10.0)
        ->and(abs($centroidY - 1286))->toBeGreaterThan(10.0);

    // 5. Neighbor Option B box: [308, 1276, 361, 1329], center (335, 1303)
    $cxB = 335;
    $cyB = 1303;
    $centerColorB = imagecolorat($img, $cxB, $cyB) & 0xFF;
    expect($centerColorB)->toBeGreaterThan(200);

    $darkCountB = 0;
    for ($y = 1276; $y <= 1329; $y++) {
        for ($x = 308; $x <= 361; $x++) {
            if ((imagecolorat($img, $x, $y) & 0xFF) < 128) {
                $darkCountB++;
            }
        }
    }
    expect($darkCountB)->toBe(0);

    imagedestroy($img);
});

test('challenge sheet produces deliberate review evidence through detector seam', function (): void {
    $target = ProofPlanFixtureTarget::standard(identityMapped: true);
    $renderer = challengeRenderer();
    $insets = ['left' => 142, 'top' => 142, 'right' => 142, 'bottom' => 142];

    $result = $renderer->render($target, ProofPurpose::QuickRehearsal, omrPhysicalUnitPdfBytes(), $insets);
    $plan = $result['plan'];
    $pageBytes = $result['sheet']['pages'][0]['bytes'];

    $layoutPort = new class implements OmrLayoutVerificationPort
    {
        public function verifyLayout(OmrDetectionRequest $request): OmrLayoutVerificationResult
        {
            return new OmrLayoutVerificationResult(
                outcome: OmrLayoutVerificationResult::OUTCOME_SUCCEEDED,
                alignedBytes: $request->imageBytes,
                alignedImage: ['mime' => 'image/png', 'width' => $request->imageWidth, 'height' => $request->imageHeight, 'hash' => $request->inputHash],
                overlayBytes: $request->imageBytes,
                overlayImage: ['mime' => 'image/png', 'width' => $request->imageWidth, 'height' => $request->imageHeight, 'hash' => $request->inputHash],
                markerMatches: ['quarter_1' => 1.0, 'quarter_2' => 1.0, 'quarter_3' => 1.0, 'quarter_4' => 1.0],
                fieldBlockShifts: [],
                bubbleMeans: [],
                gates: ['marker_count' => 'pass', 'dimensions' => 'pass', 'shifts' => 'pass'],
                preprocessMs: 1.0,
                verificationMs: 2.0,
            );
        }
    };

    $detectionPort = new class($insets) implements OmrDetectionPort
    {
        public function __construct(private readonly array $insets) {}

        public function detect(OmrDetectionRequest $request): OmrDetectionResult
        {
            $img = imagecreatefromstring($request->imageBytes);
            $items = [];
            foreach ($request->items as $item) {
                $selectedOption = null;
                $bestDarkness = 255;
                foreach ($item->options as $opt) {
                    foreach ($request->reviewManifest['fields'] as $f) {
                        if ($f['label'] === $item->nativeLabel && $f['option'] === $opt->code && (int) $f['page'] === 1) {
                            $cx = (int) round($f['pixel']['x'] + (int) ($this->insets['left'] ?? 0) + $f['pixel']['w'] / 2.0);
                            $cy = (int) round($f['pixel']['y'] + (int) ($this->insets['top'] ?? 0) + $f['pixel']['h'] / 2.0);
                            $lum = imagecolorat($img, $cx, $cy) & 0xFF;
                            if ($lum < 50 && $lum < $bestDarkness) {
                                $bestDarkness = $lum;
                                $selectedOption = $opt->code;
                            }
                            break;
                        }
                    }
                }
                $items[] = OmrDetectedItem::fromGeometry(
                    geometry: $item,
                    selectedOption: $selectedOption,
                    state: $selectedOption !== null ? OmrDetectedItem::STATE_MARKED : OmrDetectedItem::STATE_BLANK,
                    confidence: 0.99,
                );
            }
            imagedestroy($img);

            return OmrDetectionResult::succeeded(new OmrDetectionSuccess(
                engineName: $request->engineName,
                engineVersion: 'fixture-v1',
                engineCommit: $request->engineCommit,
                inputHash: $request->inputHash,
                configHash: $request->configHash,
                applicantNumberProposal: null,
                applicantNumberConfidence: null,
                identityFields: [],
                items: $items,
                issues: [],
                sheetConfidence: 0.99,
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
    };

    $runner = new OmrTemplateDiagnosticRunner(
        app(OmrDiagnosticRequestBuilder::class),
        $layoutPort,
        $detectionPort,
    );

    $evidence = $runner->verify($target, $pageBytes, 'image/png', $plan, pageNo: 1);

    $expectedPage1 = $plan->expectedVectorForPage(1);
    expect($evidence->succeeded())->toBeTrue()
        ->and($evidence->summary['vector_match'])->toBeFalse()
        ->and($evidence->summary['exact'])->toBeLessThan(count($expectedPage1))
        ->and($evidence->summary['missing'] + $evidence->summary['ambiguous'])->toBeGreaterThan(0);
});

test('OmrManifestBubbleShader direct operations validate, index, resolve, and shade 53x53 bubbles centered', function (): void {
    $shader = new OmrManifestBubbleShader;

    $fields = [
        [
            'page' => 1,
            'label' => 'Q_1',
            'option' => 'A',
            'pixel' => ['x' => 100, 'y' => 200, 'w' => 53, 'h' => 53],
        ],
        [
            'page' => 1,
            'label' => 'Q_1',
            'option' => 'B',
            'pixel' => ['x' => 160, 'y' => 200, 'w' => 53, 'h' => 53],
        ],
    ];

    $index = $shader->index($fields);
    expect($index)->toHaveKey(1)
        ->and($index[1])->toHaveKey('Q_1')
        ->and($index[1]['Q_1'])->toHaveKey('A')
        ->and($index[1]['Q_1']['A'])->toBe(['x' => 100, 'y' => 200, 'w' => 53, 'h' => 53]);

    $img = imagecreatetruecolor(500, 500);
    $white = imagecolorallocate($img, 255, 255, 255);
    $black = imagecolorallocate($img, 0, 0, 0);
    imagefilledrectangle($img, 0, 0, 500, 500, $white);

    $insets = ['left' => 50, 'top' => 50];
    $shader->shadePage($img, $index, 1, ['Q_1' => 'A'], [], $insets, $black);

    // Option A paper bounding box: [150, 250, 203, 303], center cx = 177, cy = 277
    $cx = 177;
    $cy = 277;
    expect(imagecolorat($img, $cx, $cy) & 0xFF)->toBeLessThan(30);

    // Option B paper bounding box: [210, 250, 263, 303], center cx = 237, cy = 277
    $cxB = 237;
    expect(imagecolorat($img, $cxB, $cy) & 0xFF)->toBeGreaterThan(200);

    imagedestroy($img);
});

test('OmrCheckerDetection authenticated worker protocol characterization through diagnostic runner', function (): void {
    config([
        'omr.omrchecker.signing_secret' => str_repeat('s', 40),
    ]);

    $target = ProofPlanFixtureTarget::standard(identityMapped: true);
    $renderer = challengeRenderer();
    $insets = ['left' => 142, 'top' => 142, 'right' => 142, 'bottom' => 142];

    $result = $renderer->render($target, ProofPurpose::QuickRehearsal, omrPhysicalUnitPdfBytes(), $insets, OmrProofPlanner::CURRENT_PLAN_VERSION, OmrProofPlan::METHOD_HAND_SHADED);
    $plan = $result['plan'];
    $pageBytes = $result['sheet']['pages'][0]['bytes'];

    // Protocol characterization using fake HTTP transport for signed worker responses
    Http::fake([
        '*/layout-verification' => function ($request) {
            $body = omrPhysicalChallengeWorkerLayoutResponse(
                (string) ($request->header('X-Nexiam-Run-Id')[0] ?? 'run-1'),
                $request->body()
            );

            return Http::response($body[0], 200, $body[1]);
        },
        '*/detections' => function ($request) {
            $body = omrPhysicalChallengeWorkerDetectionResponse(
                (string) ($request->header('X-Nexiam-Run-Id')[0] ?? 'run-1'),
                $request->body()
            );

            return Http::response($body[0], 200, $body[1]);
        },
    ]);

    $worker = new OmrCheckerDetection;
    $runner = new OmrTemplateDiagnosticRunner(
        app(OmrDiagnosticRequestBuilder::class),
        $worker,
        $worker,
    );

    $evidence = $runner->verify($target, $pageBytes, 'image/png', $plan, pageNo: 1);
    expect($evidence->succeeded())->toBeTrue()
        ->and($evidence->gates['marker_count'])->toBe('pass')
        ->and($evidence->gates['shifts'])->toBe('pass')
        ->and($evidence->summary['expected'])->toBe(count($plan->expectedVectorForPage(1)));
});

/** Build an authenticated layout-verification worker response for the physical challenge test. */
function omrPhysicalChallengeWorkerLayoutResponse(string $runId, string $requestBody): array
{
    $manifest = physicalChallengeManifestFromBody($requestBody);
    $pageDims = is_array($manifest['native_template']['pageDimensions'] ?? null)
        ? $manifest['native_template']['pageDimensions']
        : [2480, 3508];
    $alignedWidth = (int) ($pageDims[0] ?? 2480);
    $alignedHeight = (int) ($pageDims[1] ?? 3508);

    $means = [];
    foreach ((array) ($manifest['review_manifest']['fields'] ?? []) as $field) {
        $means[] = [
            'label' => $field['label'] ?? '',
            'option' => $field['option'] ?? '',
            'mean' => 245.0,
        ];
    }
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
        'aligned_image' => ['mime' => 'image/png', 'width' => $alignedWidth, 'height' => $alignedHeight],
        'layout_overlay' => ['mime' => 'image/png', 'width' => $alignedWidth, 'height' => $alignedHeight],
        'marker_matches' => ['quarter_1' => 0.95, 'quarter_2' => 0.95, 'quarter_3' => 0.95, 'quarter_4' => 0.95],
        'field_block_shifts' => [],
        'bubble_means' => ['fixture' => $means],
        'gates' => ['marker_count' => 'pass', 'dimensions' => 'pass', 'shifts' => 'pass'],
        'timings' => ['preprocess_ms' => 12.5, 'verification_ms' => 30.0],
        'item_evidence' => [],
    ];
    $resultBytes = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    $raster = "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x06\x00\x00\x00\x1f\x15c4\x00\x00\x00\nIDATx\x9cc\x00\x01\x00\x00\x05\x00\x01\r\n-\xb4\x00\x00\x00\x00IEND\xaeB`\x82";
    $boundary = 'test-layout-boundary';
    $body = "--{$boundary}\r\n"
        ."Content-Disposition: form-data; name=\"result\"\r\nContent-Type: application/json\r\n\r\n".$resultBytes."\r\n"
        ."--{$boundary}\r\n"
        ."Content-Disposition: form-data; name=\"aligned_image\"; filename=\"aligned.png\"\r\nContent-Type: image/png\r\n\r\n".$raster."\r\n"
        ."--{$boundary}\r\n"
        ."Content-Disposition: form-data; name=\"layout_overlay\"; filename=\"overlay.png\"\r\nContent-Type: image/png\r\n\r\n".$raster."\r\n"
        ."--{$boundary}--\r\n";
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

/** Build an authenticated detection worker response for the physical challenge test. */
function omrPhysicalChallengeWorkerDetectionResponse(string $runId, string $requestBody): array
{
    $manifest = physicalChallengeManifestFromBody($requestBody);
    $items = [];
    foreach ((array) ($manifest['items'] ?? []) as $item) {
        $optionCodes = array_map(
            static fn (array $option): string => (string) ($option['code'] ?? ''),
            (array) ($item['options'] ?? []),
        );
        $optionCodes = array_values(array_filter($optionCodes, static fn (string $code): bool => $code !== ''));
        $selectedOption = $optionCodes[0] ?? 'A';
        $items[] = [
            'position' => $item['position'] ?? 1,
            'area_code' => $item['area_code'] ?? 'DEFAULT',
            'question_no' => $item['question_no'] ?? 1,
            'selected_option' => $selectedOption,
            'state' => OmrDetectedItem::STATE_MARKED,
            'confidence' => 0.95,
            'region' => $item['region'] ?? ['x' => 0.1, 'y' => 0.1, 'w' => 0.1, 'h' => 0.1],
            'observed_options' => [$selectedOption],
            'option_observations' => array_map(
                static function (string $code) use ($selectedOption): array {
                    return [
                        'code' => $code,
                        'mean' => $code === $selectedOption ? 245.0 : null,
                        'classification' => $code === $selectedOption ? 'observed' : 'not_observed',
                    ];
                },
                $optionCodes,
            ),
            'issue_codes' => [],
            'legacy_evidence_limited' => false,
        ];
    }
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
            'version' => 'de3f69828228',
            'commit' => $manifest['engine']['commit'],
            'patch_identity' => $manifest['engine']['patch_identity'],
        ],
        'input_hash' => $manifest['input_hash'],
        'native_bundle_hash' => $manifest['native_bundle_hash'],
        'manifest_hash' => $manifest['manifest_hash'],
        'config_hash' => $manifest['config_hash'],
        'booklet_hash' => $manifest['booklet_hash'],
        'calibration_status' => 'unqualified',
        'applicant_number_proposal' => null,
        'applicant_number_confidence' => null,
        'identity_fields' => [],
        'items' => $items,
        'issues' => [],
        'sheet_confidence' => 0.95,
        'field_block_shifts' => [],
        'aligned_image' => ['mime' => 'image/png', 'width' => $manifest['image_width'], 'height' => $manifest['image_height']],
        'image' => ['mime' => $manifest['image_mime'], 'width' => $manifest['image_width'], 'height' => $manifest['image_height']],
        'timings' => ['preprocess_ms' => 12.5, 'verification_ms' => 30.0],
    ];
    $resultBytes = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    $raster = "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x06\x00\x00\x00\x1f\x15c4\x00\x00\x00\nIDATx\x9cc\x00\x01\x00\x00\x05\x00\x01\r\n-\xb4\x00\x00\x00\x00IEND\xaeB`\x82";
    $boundary = 'test-detection-boundary';
    $body = "--{$boundary}\r\n"
        ."Content-Disposition: form-data; name=\"result\"\r\nContent-Type: application/json\r\n\r\n".$resultBytes."\r\n"
        ."--{$boundary}\r\n"
        ."Content-Disposition: form-data; name=\"aligned_image\"; filename=\"aligned.png\"\r\nContent-Type: image/png\r\n\r\n".$raster."\r\n"
        ."--{$boundary}--\r\n";

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
