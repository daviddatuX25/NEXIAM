<?php

use App\Assessment\Omr\Layout\OmrTemplateLayoutSolver;
use App\Printing\BundleRenderer;

/*
 * Marker-page print composition (founder decision 2026-08-19): the render-only
 * NAME/EDQ header stays in the central band between the top markers — never
 * under a marker or on the paper border — and marker pages carry NO decorative
 * frame that could cross the fiducials.
 */

function markerPrintBlueprint(): array
{
    $sections = [];
    foreach (['PROFILE_VERBAL', 'PROFILE_QUANT', 'LOGIC', 'SPATIAL', 'MEMORY', 'LANGUAGE'] as $index => $area) {
        $sections[] = [
            'area_code' => $area,
            'question_from' => 1,
            'question_to' => 25,
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

function markerPrintOverlay(bool $edq = false): array
{
    $base = [
        'title' => 'NEXIAM ANSWER SHEET',
        'header_lines' => ['SIX-DOMAIN PRACTICE PROFILE'],
        'footer_lines' => [],
        'name_box_label' => 'NAME (PRINT):',
        'per_page' => [],
    ];
    if ($edq) {
        $base['edq_header'] = true;
        $base['edq_header_label'] = 'EDQ / HEADER ZONE';
        $base['exam_information_lines'] = ['EXAM: __________'];
        $base['instructions_lines'] = ['Shade one answer per question.'];
        $base['birthdate_label'] = 'BIRTHDATE:';
        $base['birthdate_placeholder'] = 'MMDDYYYY';
        $base['edq_layout'] = ['columns' => 3, 'gap_mm' => 4, 'reserve_native_identity' => true];
    }

    return $base;
}

test('marker-page render-only header stays inside the central band, clear of both top markers', function (): void {
    $solver = new OmrTemplateLayoutSolver;
    $template = $solver->solve(markerPrintBlueprint())->template;
    $renderer = app(BundleRenderer::class);
    $markers = $template['pages'][0]['markers'];
    $paperMmW = 216.0;

    $insetPx = (int) $markers['inset'];
    $markerHalfPx = intdiv((int) $markers['size'], 2);
    $quietPx = (int) $markers['quiet_zone'];
    $exclusionMm = round(($insetPx + $markerHalfPx + $quietPx) / BundleRenderer::PPI * 25.4, 2);

    foreach ([false, true] as $edq) {
        $regions = $renderer->reviewOverlayRegions($template, markerPrintOverlay($edq));
        $name = $regions[1]['name'];

        expect($name)->not->toBeNull()
            ->and($name['x'])->toBeGreaterThan(0.0)
            ->and($name['w'])->toBeGreaterThan(0.0)
            ->and($name['y'])->toBeGreaterThan(0.0)
            ->and($name['h'])->toBeGreaterThan(0.0);

        $nameLeftMm = $name['x'] * $paperMmW;
        $nameRightMm = ($name['x'] + $name['w']) * $paperMmW;

        // Inside the printable page and clear of both top markers' full
        // rectangle plus quiet zone (the exclusion band).
        // 0.01mm epsilon absorbs the normalized-fraction round-trip.
        expect($nameLeftMm)->toBeGreaterThanOrEqual($exclusionMm + 2 - 0.01)
            ->and($nameRightMm)->toBeLessThanOrEqual($paperMmW - $exclusionMm - 2 + 0.01);
    }
});

test('marker-page print output has no decorative frame and a name box inside the band', function (): void {
    $solver = new OmrTemplateLayoutSolver;
    $template = $solver->solve(markerPrintBlueprint())->template;
    $renderer = app(BundleRenderer::class);
    $markers = $template['pages'][0]['markers'];

    $html = $renderer->renderHtml($template, ['overlay' => markerPrintOverlay(false)]);

    // No page frame border on marker pages (would cross the fiducials).
    expect($html)->not->toContain('border:0.7mm solid #000');
    // The four official bullseye markers are embedded.
    expect(substr_count($html, 'data:image/jpeg;base64,'))->toBe(4);

    $insetPx = (int) $markers['inset'];
    $markerHalfPx = intdiv((int) $markers['size'], 2);
    $quietPx = (int) $markers['quiet_zone'];
    $exclusionMm = round(($insetPx + $markerHalfPx + $quietPx) / BundleRenderer::PPI * 25.4, 2);
    $expectedLeft = round($exclusionMm + 2, 2);

    expect($html)->toContain('left:'.$expectedLeft.'mm')
        ->and($html)->toContain('NAME (PRINT):');
});
