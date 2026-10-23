<?php

use App\Assessment\Omr\OmrPreparedCaptureEvidence;
use App\Assessment\Omr\OmrQuarterTurnCandidate;
use App\Assessment\Omr\OmrQuarterTurnDiagnostics;

test('prepared capture evidence is immutable and projects complete safe provenance', function (): void {
    $candidate = new OmrQuarterTurnCandidate(
        orientation: 90,
        bytes: 'normalized',
        mime: 'image/png',
        sha256: str_repeat('n', 64),
        width: 20,
        height: 10,
        diagnostics: ['elapsed_ms' => 1.2],
    );
    $evidence = new OmrPreparedCaptureEvidence(
        outcome: OmrPreparedCaptureEvidence::OUTCOME_SUCCEEDED,
        code: null,
        detail: null,
        pageNo: 1,
        inputHash: str_repeat('i', 64),
        acceptedOrientation: 90,
        orientationDiagnostics: new OmrQuarterTurnDiagnostics([$candidate]),
        layout: null,
        diagnosticEvidence: null,
        target: ['kind' => 'candidate', 'id' => '1'],
        hashes: [
            'input' => str_repeat('i', 64),
            'aligned' => str_repeat('a', 64),
            'overlay' => str_repeat('o', 64),
        ],
        assets: [
            'captured' => ['asset_id' => 1, 'sha256' => str_repeat('i', 64), 'bytes' => 'forbidden'],
            'aligned' => ['asset_id' => 2, 'sha256' => str_repeat('a', 64)],
            'overlay' => null,
        ],
        gates: ['marker_count' => 'pass'],
        markerMatches: ['corner_1' => 1.0],
        fieldBlockShifts: [],
        items: [['item_id' => 'Q1', 'expected' => 'A', 'detected' => 'A']],
        identity: [],
        timings: ['detection_ms' => 2.5],
        issues: [],
        expectedVectorHash: str_repeat('e', 64),
    );

    $projection = $evidence->toArray();
    expect($evidence->succeeded())->toBeTrue()
        ->and($evidence->acceptedOrientation())->toBe(90)
        ->and($projection['orientation_diagnostics']['candidates'][0]['orientation'])->toBe(90)
        ->and($projection['assets']['captured'])->not->toHaveKey('bytes')
        ->and($projection['items'])->toHaveCount(1)
        ->and($evidence->evidenceHash())->toHaveLength(64);

    expect(fn () => $evidence->acceptedOrientation = 0)->toThrow(Error::class);
});
