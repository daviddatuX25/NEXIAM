<?php

use App\Assessment\Omr\OmrCapturePolicy;

test('probe capture-policy keys in test process', function (): void {
    fwrite(STDERR, 'PROBE capture-policy.v1 = '.json_encode(config('capture-policy.v1')).PHP_EOL);
    fwrite(STDERR, 'PROBE omr.capture-policy.v1 = '.json_encode(array_keys(config('omr.capture-policy.v1') ?? [])).PHP_EOL);
    try {
        $frozen = OmrCapturePolicy::frozen();
        fwrite(STDERR, 'PROBE frozen() = '.json_encode(array_keys($frozen)).PHP_EOL);
    } catch (\Throwable $e) {
        fwrite(STDERR, 'PROBE frozen() threw: '.$e->getMessage().PHP_EOL);
    }
    expect(true)->toBeTrue();
});
