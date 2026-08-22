<?php

use App\Assessment\Omr\Canonical\OmrPageDimensionMath;


test('canonical millimetres convert to deterministic 300 PPI pixels', function (): void {
    expect(OmrPageDimensionMath::pixelsFromMillimetres(216))->toBe(2551)
        ->and(OmrPageDimensionMath::pixelsFromMillimetres(330))->toBe(3898)
        ->and(OmrPageDimensionMath::millimetresFromPixels(2551))->toBe(215.98466666666664);
});

test('normalized rectangles use actual source and target page dimensions', function (): void {
    expect(OmrPageDimensionMath::normalizedRect(
        ['x' => 100, 'y' => 200, 'w' => 300, 'h' => 400],
        ['width' => 1000, 'height' => 2000],
        ['width' => 2000, 'height' => 4000],
    ))->toBe(['x' => 200, 'y' => 400, 'w' => 600, 'h' => 800]);
});

test('field block transform preserves labels and scales geometry by axis', function (): void {
    $blocks = [
        'Questions' => [
            'direction' => 'horizontal',
            'fieldLabels' => ['Q1..Q4'],
            'origin' => [100, 200],
            'bubblesGap' => 20,
            'labelsGap' => 30,
            'bubbleDimensions' => [10, 12],
        ],
    ];

    expect(OmrPageDimensionMath::transformFieldBlocks(
        $blocks,
        ['width' => 1000, 'height' => 2000],
        ['width' => 2000, 'height' => 4000],
    ))->toBe([
        'Questions' => [
            'direction' => 'horizontal',
            'fieldLabels' => ['Q1..Q4'],
            'origin' => [200, 400],
            'bubblesGap' => 40,
            'labelsGap' => 60,
            'bubbleDimensions' => [20, 24],
        ],
    ]);
});

test('placement uses white padding and nine point alignment', function (): void {
    expect(OmrPageDimensionMath::place(
        ['width' => 1000, 'height' => 500],
        ['width' => 2000, 'height' => 2000],
        ['left' => 100, 'right' => 100, 'top' => 200, 'bottom' => 200],
        OmrPageDimensionMath::ALIGN_TOP_RIGHT,
    ))->toMatchArray([
        'x' => 100,
        'y' => 200,
        'width' => 1800,
        'height' => 900,
        'alignment' => OmrPageDimensionMath::ALIGN_TOP_RIGHT,
    ]);
});

test('enlargement is rejected when effective DPI falls below the source floor', function (): void {
    config(['omr.canonical.source_dpi_floor.image' => 150]);

    expect(fn () => OmrPageDimensionMath::scaleForPlacement(
        ['width' => 1000, 'height' => 1000],
        ['width' => 3000, 'height' => 3000],
        [],
        'image',
        40,
    ))->toThrow(InvalidArgumentException::class, 'insufficient_resolution');
});

test('pixel budgets and physical paper bounds reject unsafe pages', function (): void {
    expect(fn () => OmrPageDimensionMath::assertPixelBudget(5000, 5000, 20_000_000))
        ->toThrow(InvalidArgumentException::class, 'pixel_budget_exceeded')
        ->and(fn () => OmrPageDimensionMath::assertPaperBounds(100, 200))
        ->toThrow(InvalidArgumentException::class, 'paper_out_of_bounds');
});
