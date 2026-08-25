<?php

use App\Assessment\Omr\Canonical\OmrCalibrationQrPayload;
use App\Assessment\Omr\Canonical\OmrQrPayloadException;
use App\Assessment\Omr\Diagnostics\OmrPhysicalChallengeRenderer;
use App\Assessment\Omr\Diagnostics\ProofPurpose;
use App\Printing\PrintRenderer;
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

function challengePdfBytes(): string
{
    return app(PrintRenderer::class)->render(
        '<html><head><style>body{margin:0;padding:0}</style></head><body><div style="width:200mm;height:280mm;border:1px solid #ccc"></div></body></html>',
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
        challengePdfBytes(),
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
        challengePdfBytes(),
    );

    $html = $result['guide']['html'];
    expect($result['guide']['mode'])->toBe('full');
    foreach (array_keys($result['plan']->expectedVector) as $label) {
        expect($html)->toContain($label);
    }
    // Full mode includes labels quick mode omits.
    expect($html)->toContain('LOGIC_Q_2')->toContain('LOGIC_Q_3')->toContain('VERBAL_Q_3');
});

test('guide copy is stable across repeated rendering', function (): void {
    $renderer = challengeRenderer();
    $target = ProofPlanFixtureTarget::standard(identityMapped: true);

    $first = $renderer->render($target, ProofPurpose::QuickRehearsal, challengePdfBytes());
    $second = $renderer->render($target, ProofPurpose::QuickRehearsal, challengePdfBytes());

    expect($second['guide']['html'])->toBe($first['guide']['html'])
        ->and($second['guide']['sha256'])->toBe($first['guide']['sha256'])
        ->and($second['sections'])->toBe($first['sections'])
        // The guide copy is byte-stable; the DomPDF wrapper adds a creation
        // timestamp, so only its validity is asserted here.
        ->and($first['guide']['pdf'])->toStartWith('%PDF')
        ->and($first['guide']['pdf'])->not->toBeEmpty();
});

test('sheet rendering is byte-stable and multi-page ordered with signed calibration QRs', function (): void {
    $renderer = challengeRenderer();
    $target = ProofPlanFixtureTarget::standard(identityMapped: true);

    $first = $renderer->render($target, ProofPurpose::FullQualification, challengePdfBytes());
    $second = $renderer->render($target, ProofPurpose::FullQualification, challengePdfBytes());

    expect($first['sheet']['pages'])->toHaveCount(2)
        ->and(array_column($first['sheet']['pages'], 'page_no'))->toBe([1, 2])
        ->and(array_column($first['sheet']['qr_payloads'], 'page_no'))->toBe([1, 2])
        ->and($first['sheet']['display_code'])->toMatch('/^CH-[0-9A-HJKMNP-TV-Z]{6}[0-9A-HJKMNP-TV-Z]$/');

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
        challengePdfBytes(),
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
        expect(fn () => (new OmrCalibrationQrPayload())->verify($payloads[0]['payload']))
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
        challengePdfBytes(),
    );

    expect($result['plan']->calibrationDigits)->toBe([])
        ->and($result['guide']['html'])->not->toContain('Applicant number columns')
        ->and($result['guide']['html'])->not->toContain('APPLICANT_NUMBER_');
});
