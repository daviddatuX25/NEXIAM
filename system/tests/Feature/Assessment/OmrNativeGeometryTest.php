<?php

use App\Models\Assessment\OmrAnswerSheetTemplateVersion;

/**
 * @param array{x: float, y: float, w: float, h: float} $outer
 * @param array{x: float, y: float, w: float, h: float} $inner
 */
function omrNativeRectContains(array $outer, array $inner): bool
{
    return $inner['x'] >= $outer['x']
        && $inner['y'] >= $outer['y']
        && $inner['x'] + $inner['w'] <= $outer['x'] + $outer['w'] + 1.0e-9
        && $inner['y'] + $inner['h'] <= $outer['y'] + $outer['h'] + 1.0e-9;
}

/**
 * @param array{x: float, y: float, w: float, h: float} $left
 * @param array{x: float, y: float, w: float, h: float} $right
 */
function omrNativeRectsOverlap(array $left, array $right): bool
{
    return $left['x'] < $right['x'] + $right['w']
        && $left['x'] + $left['w'] > $right['x']
        && $left['y'] < $right['y'] + $right['h']
        && $left['y'] + $left['h'] > $right['y'];
}

test('native projections replace hand-calculated seed geometry', function (): void {
    seedE2Foundation();

    $version = OmrAnswerSheetTemplateVersion::query()
        ->whereHas('template', fn ($query) => $query->where('code', 'SYN-OMR-ONE-PAGE'))
        ->with('areas.questions.options')
        ->firstOrFail();

    $violations = [];
    $bubbles = [];

    foreach ($version->areas as $area) {
        foreach ($area->questions as $question) {
            $questionRegion = [
                'x' => (float) $question->region_x,
                'y' => (float) $question->region_y,
                'w' => (float) $question->region_w,
                'h' => (float) $question->region_h,
            ];

            foreach ($question->options as $option) {
                $bubble = [
                    'x' => (float) $option->region_x,
                    'y' => (float) $option->region_y,
                    'w' => (float) $option->region_w,
                    'h' => (float) $option->region_h,
                ];

                if (! omrNativeRectContains($questionRegion, $bubble)) {
                    $violations[] = sprintf(
                        '%s question %d option %s is outside its field block',
                        $area->aptitudeArea->code,
                        $question->question_no,
                        $option->code,
                    );
                }

                foreach ($bubbles as $previous) {
                    if (omrNativeRectsOverlap($previous['region'], $bubble)) {
                        $violations[] = sprintf(
                            '%s question %d option %s overlaps %s question %d option %s',
                            $area->aptitudeArea->code,
                            $question->question_no,
                            $option->code,
                            $previous['area'],
                            $previous['question'],
                            $previous['option'],
                        );
                    }
                }

                $bubbles[] = [
                    'area' => $area->aptitudeArea->code,
                    'question' => $question->question_no,
                    'option' => $option->code,
                    'region' => $bubble,
                ];
            }
        }
    }

    expect($violations)->toBeEmpty()
        ->and($version->native_bundle_hash)->toMatch('/^[a-f0-9]{64}$/')
        ->and($version->review_manifest_json['manifest_version'])->toBe('nexiam.omr.review-manifest.v1')
        ->and($version->areas->every(fn ($area): bool => $area->projection_source === 'activation_manifest'))->toBeTrue();
});
