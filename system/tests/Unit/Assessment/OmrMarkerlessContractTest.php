<?php

use App\Assessment\Omr\Fiducial\OmrFiducial;
use App\Assessment\Omr\Layout\OmrTemplateLayoutSolver;

/*
 * Marker-less contract proof (OpenSpec change omr-profile-test-readiness).
 *
 * These tests pin the EXISTING marker-less profile/blueprint behavior while
 * the marker-bearing seam (fiducial asset, CropOnMarkers, capture policy) is
 * being added. They prove:
 *   1. long paper stays 2060×3144,
 *   2. the native page keeps the existing CropPage preprocessor,
 *   3. no marker metadata, fiducial asset, or capture policy is emitted,
 *   4. marker-bearing behavior is strictly opt-in via blueprint `markers`.
 *
 * Pure solver-level assertions: no database, no generated IDs, no marker core
 * edits, no frontend. Helper names are unique to this file so they cannot
 * collide with the global helpers in OmrTemplateLayoutSolverTest.
 */

function markerlessSection(string $area, int $from, int $to, mixed $break = null): array
{
    return ['area_code' => $area, 'question_from' => $from, 'question_to' => $to, 'option_count' => 4, 'break_before' => $break];
}

function markerlessBlueprint(array $overrides = [], array $sections = []): array
{
    return array_merge([
        'schema' => 'nexiam.omr.layout-blueprint.v1',
        'paper' => 'long',
        'columns' => 4,
        'identity' => ['applicant_number' => true, 'birthdate' => true, 'repeat_on_every_page' => true],
        'sections' => $sections,
    ], $overrides);
}

function markerlessQuestionBlocks(array $page): array
{
    return array_values(array_filter($page['fieldBlocks'], fn (array $b): bool => $b['fieldType'] === 'QTYPE_MCQ4'));
}

$solver = new OmrTemplateLayoutSolver;

test('marker-less long blueprint keeps 2060×3144 paper and the CropPage preprocessor', function () use ($solver): void {
    $result = $solver->solve(markerlessBlueprint(sections: [markerlessSection('VERBAL', 1, 25)]));

    expect($result->template['pageDimensions'])->toBe([2060, 3144])
        ->and($result->template['bubbleDimensions'])->toBe([56, 56]);

    foreach ($result->pages as $page) {
        expect($page['pageDimensions'])->toBe([2060, 3144])
            ->and($page['preProcessors'])->toBe([['name' => 'CropPage', 'options' => ['morphKernel' => [5, 5]]]])
            ->and($page)->not->toHaveKey('markers');
    }
});

test('marker-less solve emits no fiducial asset, marker metadata, or capture policy', function () use ($solver): void {
    $result = $solver->solve(markerlessBlueprint(sections: [markerlessSection('VERBAL', 1, 25)]));

    expect($result->fiducial)->toBeNull()
        ->and($result->normalizedBlueprint['markers'])->toBeNull();

    // The canonical bundle bytes are the persistence contract: they must not
    // leak any marker/fiducial/capture-policy vocabulary.
    expect($result->canonicalJson)->not->toContain('CropOnMarkers')
        ->and($result->canonicalJson)->not->toContain('fiducial')
        ->and($result->canonicalJson)->not->toContain('markers')
        ->and($result->canonicalJson)->not->toContain('capture-policy')
        ->and($result->canonicalJson)->toContain('CropPage');
});

test('six-column compact profile stays marker-less on one 2060×3144 page', function () use ($solver): void {
    $sections = array_map(
        static fn (int $domain): array => markerlessSection('AREA'.str_pad((string) $domain, 2, '0', STR_PAD_LEFT), 1, 25),
        range(1, 6),
    );

    $result = $solver->solve(markerlessBlueprint(['columns' => 6], $sections));

    expect($result->pages)->toHaveCount(1)
        ->and(markerlessQuestionBlocks($result->pages[0]))->toHaveCount(6)
        ->and($result->template['pageDimensions'])->toBe([2060, 3144])
        ->and($result->template['bubbleDimensions'])->toBe([40, 40])
        ->and($result->pages[0]['preProcessors'])->toBe([['name' => 'CropPage', 'options' => ['morphKernel' => [5, 5]]]])
        ->and($result->pages[0])->not->toHaveKey('markers')
        ->and($result->fiducial)->toBeNull();
});

test('explicit markers.enabled=false is byte-identical to an absent markers option', function () use ($solver): void {
    $baseline = $solver->solve(markerlessBlueprint(sections: [markerlessSection('VERBAL', 1, 25)]));
    $explicitOff = $solver->solve(markerlessBlueprint(
        ['markers' => ['enabled' => false]],
        [markerlessSection('VERBAL', 1, 25)],
    ));

    expect($explicitOff->canonicalJson)->toBe($baseline->canonicalJson)
        ->and($explicitOff->templateJsonHash)->toBe($baseline->templateJsonHash)
        ->and($explicitOff->blueprintHash)->toBe($baseline->blueprintHash)
        ->and($explicitOff->fiducial)->toBeNull()
        ->and($explicitOff->normalizedBlueprint['markers'])->toBeNull();
});

test('marker-bearing behavior is opt-in: markers.enabled=true switches the page to CropOnMarkers and emits the official bullseye asset', function () use ($solver): void {
    // The compact six-column profile is the OpenSpec subject: 25 rows per
    // column fits the marker frame (40px bubbles).
    $sections = array_map(
        static fn (int $domain): array => markerlessSection('AREA'.str_pad((string) $domain, 2, '0', STR_PAD_LEFT), 1, 25),
        range(1, 6),
    );
    $result = $solver->solve(markerlessBlueprint(
        ['columns' => 6, 'markers' => ['enabled' => true]],
        $sections,
    ));

    // The marker asset is OMRChecker's own pinned bullseye (alignment
    // fiducial, not a template ID), emitted only on the marker path.
    expect($result->fiducial)->not->toBeNull()
        ->and($result->fiducial['name'])->toBe('omr_marker.jpg')
        ->and($result->fiducial['mime'])->toBe('image/jpeg')
        ->and($result->fiducial['asset_hash'])->toBe(OmrFiducial::assetHash())
        ->and(str_starts_with($result->fiducial['bytes'], "\xff\xd8\xff"))->toBeTrue();

    // Frame anchoring: pageDimensions become the fiducial-center frame, not paper.
    $frozen = config('omr.capture-policy.v1');
    $inset = $frozen['frame_anchor']['inset'];
    expect($result->template['pageDimensions'])->toBe([2060 - 2 * $inset, 3144 - 2 * $inset])
        ->and($result->template['pageDimensions'])->not->toBe([2060, 3144]);

    // The preprocessor chain swaps CropPage for the marker registration path
    // with the upstream mobile recipe (ratio 17, official asset).
    expect($result->pages[0]['preProcessors'][0]['name'])->toBe('CropOnMarkers')
        ->and($result->pages[0]['preProcessors'][0]['options']['relativePath'])->toBe('omr_marker.jpg')
        ->and($result->pages[0]['preProcessors'][0]['options']['sheetToMarkerWidthRatio'])->toBe(17);

    // Page marker metadata is present, and the canonical bundle bytes carry it.
    expect($result->pages[0]['markers'])->toBe([
        'family' => 'omrchecker.bullseye',
        'asset_name' => 'omr_marker.jpg',
        'size' => $frozen['frame_anchor']['marker_size'],
        'quiet_zone' => $frozen['frame_anchor']['marker_quiet_zone'],
        'inset' => $inset,
        'paper' => [2060, 3144],
        'centers' => [[$inset, $inset], [2060 - $inset, $inset], [$inset, 3144 - $inset], [2060 - $inset, 3144 - $inset]],
        'quadrants' => [0, 1, 2, 3],
    ])->and($result->canonicalJson)->toContain('CropOnMarkers')
        ->and($result->canonicalJson)->toContain('omr_marker.jpg');
});
