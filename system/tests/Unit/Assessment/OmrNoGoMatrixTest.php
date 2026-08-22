<?php

use App\Assessment\Omr\Canonical\OmrNoGoMatrixRunner;

test('digital no-go matrix declares clean pass and typed rejection variants', function (): void {
    $result = app(OmrNoGoMatrixRunner::class)->run(['proof_hash' => hash('sha256', 'proof')]);

    expect($result['variants'])->toHaveCount(count(OmrNoGoMatrixRunner::VARIANTS))
        ->and($result['variants']['clean']['actual'])->toBe('pass')
        ->and($result['variants']['missing_marker']['actual'])->toBe('typed_rejection')
        ->and($result['matrix_hash'])->toHaveLength(64);
});
