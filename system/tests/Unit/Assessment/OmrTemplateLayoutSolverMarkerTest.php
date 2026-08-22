<?php

use App\Assessment\Omr\Fiducial\OmrFiducial;
use App\Assessment\Omr\Layout\OmrTemplateLayoutException;
use App\Assessment\Omr\Layout\OmrTemplateLayoutSolver;
use App\Assessment\Omr\NativeBundle;
use App\Assessment\Omr\OmrCapturePolicy;

/*
 * Marker-bearing solver contract (invariants #1/#2/#3): the solved page is
 * anchored in the fiducial-CENTER frame (pageDimensions == marker-center
 * rectangle), all block geometry is re-derived inside that frame, the engine
 * w/2 × h/3 quadrant split is asserted, and the version-unique fiducial asset
 * is emitted for the bundle.
 */

function markerSolverSection(string $area, int $from, int $to): array
{
    return ['area_code' => $area, 'question_from' => $from, 'question_to' => $to, 'option_count' => 4, 'break_before' => $from === 1 ? null : 'next_column'];
}

function markerSolverBlueprint(array $sections = []): array
{
    return [
        'schema' => 'nexiam.omr.layout-blueprint.v1',
        'paper' => 'long',
        'columns' => 6,
        'identity' => ['applicant_number' => true, 'birthdate' => false, 'repeat_on_every_page' => true],
        'markers' => ['enabled' => true],
        'sections' => $sections === [] ? [
            markerSolverSection('PROFILE_VERBAL', 1, 25),
            markerSolverSection('PROFILE_QUANT', 1, 25),
            markerSolverSection('LOGIC', 1, 25),
            markerSolverSection('SPATIAL', 1, 25),
            markerSolverSection('MEMORY', 1, 25),
            markerSolverSection('LANGUAGE', 1, 25),
        ] : $sections,
    ];
}

$solver = new OmrTemplateLayoutSolver;

test('marker solve anchors pageDimensions at the fiducial-center frame, not paper', function () use ($solver): void {
    $result = $solver->solve(markerSolverBlueprint());
    $frozen = OmrCapturePolicy::frozen();
    $inset = $frozen['frame_anchor']['inset'];

    expect($result->template['pageDimensions'])->toBe([2060 - 2 * $inset, 3144 - 2 * $inset])
        ->and($result->pages)->toHaveCount(1)
        ->and($result->pages[0]['pageDimensions'])->toBe([2060 - 2 * $inset, 3144 - 2 * $inset]);
});

test('quadrant membership: two fiducials in the top third, two in the bottom two-thirds', function () use ($solver): void {
    $result = $solver->solve(markerSolverBlueprint());
    $markers = $result->pages[0]['markers'];
    $frameW = $result->pages[0]['pageDimensions'][0];
    $frameH = $result->pages[0]['pageDimensions'][1];
    $paper = $markers['paper'];
    $inset = $markers['inset'];

    // Project the printed centers into the processing frame (the engine's
    // coordinate space before the w/2 × h/3 split).
    $scaleX = $frameW / $paper[0];
    $scaleY = $frameH / $paper[1];
    $centers = array_map(
        static fn (array $center): array => [$center[0] * $scaleX, $center[1] * $scaleY],
        $markers['centers'],
    );

    expect($markers['quadrants'])->toBe([0, 1, 2, 3])
        ->and($markers['family'])->toBe('omrchecker.bullseye')
        ->and($markers['asset_name'])->toBe('omr_marker.jpg');
    [$topLeft, $topRight, $bottomLeft, $bottomRight] = $centers;
    expect($topLeft[0])->toBeLessThan($frameW / 2)->and($topLeft[1])->toBeLessThan($frameH / 3)
        ->and($topRight[0])->toBeGreaterThanOrEqual($frameW / 2)->and($topRight[1])->toBeLessThan($frameH / 3)
        ->and($bottomLeft[0])->toBeLessThan($frameW / 2)->and($bottomLeft[1])->toBeGreaterThanOrEqual(2 * $frameH / 3)
        ->and($bottomRight[0])->toBeGreaterThanOrEqual($frameW / 2)->and($bottomRight[1])->toBeGreaterThanOrEqual(2 * $frameH / 3);

    OmrCapturePolicy::assertQuadrantPlacement($markers['paper'], $result->pages[0]['pageDimensions'], $inset);
});

test('every bubble of every block sits inside the marker frame with the printable margin', function () use ($solver): void {
    $result = $solver->solve(markerSolverBlueprint());
    $frameW = $result->pages[0]['pageDimensions'][0];
    $frameH = $result->pages[0]['pageDimensions'][1];
    $bubble = $result->template['bubbleDimensions'][0];

    $violations = [];
    foreach ($result->pages[0]['fieldBlocks'] as $name => $block) {
        [$originX, $originY] = $block['origin'];
        $labels = count(NativeBundle::expandLabel((string) $block['fieldLabels'][0]));
        $horizontal = (string) ($block['direction'] ?? 'vertical') !== 'vertical';
        $labelGap = (float) $block['labelsGap'];
        $bubbleGap = (float) $block['bubblesGap'];
        $optionCount = $block['fieldType'] === 'QTYPE_INT' ? 10 : 4;

        $right = $horizontal ? $originX + ($optionCount - 1) * $bubbleGap + $bubble : $originX + ($labels - 1) * $labelGap + $bubble;
        $bottom = $horizontal ? $originY + ($labels - 1) * $labelGap + $bubble : $originY + ($optionCount - 1) * $bubbleGap + $bubble;

        if ($originX < OmrTemplateLayoutSolver::MARGIN || $originY < OmrTemplateLayoutSolver::MARGIN
            || $right > $frameW - OmrTemplateLayoutSolver::MARGIN
            || $bottom > $frameH - OmrTemplateLayoutSolver::MARGIN) {
            $violations[] = sprintf('%s escapes the frame margin (origin %d,%d right %d bottom %d in %dx%d).', $name, $originX, $originY, $right, $bottom, $frameW, $frameH);
        }
    }

    expect($violations)->toBeEmpty();
});

test('marker pages swap CropPage for CropOnMarkers with the fiducial asset', function () use ($solver): void {
    $result = $solver->solve(markerSolverBlueprint());

    expect($result->pages[0]['preProcessors'])->toHaveCount(1);
    $pre = $result->pages[0]['preProcessors'][0];
    expect($pre['name'])->toBe('CropOnMarkers')
        ->and($pre['options']['relativePath'])->toBe('omr_marker.jpg')
        ->and($pre['options']['sheetToMarkerWidthRatio'])->toBe(17);

    expect($result->fiducial['name'])->toBe('omr_marker.jpg')
        ->and($result->fiducial['mime'])->toBe('image/jpeg')
        ->and(str_starts_with($result->fiducial['bytes'], "\xff\xd8\xff"))->toBeTrue()
        ->and($result->fiducial['asset_hash'])->toBe(OmrFiducial::assetHash())
        ->and($result->fiducial['bytes'])->toBe(OmrFiducial::assetBytes());
});

test('the official bullseye asset is identical across versions (alignment fiducial)', function () use ($solver): void {
    $first = $solver->solve(markerSolverBlueprint());
    $second = $solver->solve(markerSolverBlueprint());

    // The marker is OMRChecker's own bullseye — no version-derived pattern.
    expect($first->fiducial['asset_hash'])->toBe(OmrFiducial::assetHash())
        ->and($second->fiducial['asset_hash'])->toBe($first->fiducial['asset_hash'])
        ->and($first->fiducial['bytes'])->toBe($second->fiducial['bytes']);
});

test('malformed markers option is refused at validation time', function () use ($solver): void {
    $blueprint = markerSolverBlueprint();
    $blueprint['markers'] = 'enabled';

    $diagnostics = $solver->validate($blueprint);
    expect($diagnostics)->toHaveCount(1)
        ->and($diagnostics[0]->code)->toBe('invalid_markers');
});

test('a marker frame that cannot fit the flow is a typed diagnostic, not a silent page', function () use ($solver): void {
    // 4 columns of 56px bubbles with 200 rows per section cannot fit the
    // marker frame flow; the solver must refuse instead of emitting page 2.
    $blueprint = [
        'schema' => 'nexiam.omr.layout-blueprint.v1',
        'paper' => 'long',
        'columns' => 4,
        'identity' => ['applicant_number' => true, 'birthdate' => false, 'repeat_on_every_page' => true],
        'markers' => ['enabled' => true],
        'sections' => [
            ['area_code' => 'VERBAL', 'question_from' => 1, 'question_to' => 200, 'option_count' => 4, 'break_before' => null],
        ],
    ];

    expect(fn () => $solver->solve($blueprint))->toThrow(OmrTemplateLayoutException::class, 'overflows the marker frame flow');
});

test('the compact six-column profile is a one-page contract: overflow is refused', function () use ($solver): void {
    $blueprint = [
        'schema' => 'nexiam.omr.layout-blueprint.v1',
        'paper' => 'long',
        'columns' => 6,
        'identity' => ['applicant_number' => true, 'birthdate' => false, 'repeat_on_every_page' => true],
        'sections' => [
            ['area_code' => 'VERBAL', 'question_from' => 1, 'question_to' => 200, 'option_count' => 4, 'break_before' => null],
        ],
    ];

    expect(fn () => $solver->solve($blueprint))
        ->toThrow(OmrTemplateLayoutException::class, 'must fit one page');
});
