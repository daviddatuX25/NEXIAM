<?php

use App\Assessment\Omr\Layout\OmrTemplateLayoutException;
use App\Assessment\Omr\Layout\OmrTemplateLayoutSolver;

use function array_filter;
use function array_map;
use function array_merge;
use function array_values;
use function json_decode;

function section(string $area, int $from, int $to, mixed $break = null): array
{
    return ['area_code' => $area, 'question_from' => $from, 'question_to' => $to, 'option_count' => 4, 'break_before' => $break];
}

function blueprint(array $overrides = [], array $sections = []): array
{
    return array_merge([
        'schema' => 'nexiam.omr.layout-blueprint.v1',
        'paper' => 'long',
        'columns' => 4,
        'identity' => ['applicant_number' => true, 'birthdate' => true, 'repeat_on_every_page' => true],
        'sections' => $sections,
    ], $overrides);
}

function questionBlocks(array $page): array
{
    return array_values(array_filter($page['fieldBlocks'], fn (array $b): bool => $b['fieldType'] === 'QTYPE_MCQ4'));
}

$solver = new OmrTemplateLayoutSolver();

test('4-column solve matches the canonical shipped sheet geometry', function () use ($solver): void {
    $result = $solver->solve(blueprint(sections: [section('VERBAL', 1, 100)]));

    expect($result->templateJsonHash)->toBeString()->toHaveLength(64)
        ->and($result->pages)->toHaveCount(1);

    $blocks = questionBlocks($result->pages[0]);
    expect(array_map(fn (array $b): string => $b['fieldLabels'][0], $blocks))->toBe([
        'VERBAL_Q_001..025',
        'VERBAL_Q_026..050',
        'VERBAL_Q_051..075',
        'VERBAL_Q_076..100',
    ])->and(array_map(fn (array $b): array => $b['origin'], $blocks))->toBe([
        [180, 900], [620, 900], [1060, 900], [1500, 900],
    ])->and($result->template['bubbleDimensions'])->toBe([56, 56])
        ->and($result->template['pageDimensions'])->toBe([2060, 3144]);
});

test('identity grids repeat on every page and sit outside the flow', function () use ($solver): void {
    $result = $solver->solve(blueprint(sections: [section('VERBAL', 1, 110)])); // 10 rows flow to page 2

    expect($result->pages)->toHaveCount(2);
    foreach ($result->pages as $page) {
        $labels = array_map(fn (array $b): string => $b['fieldLabels'][0], $page['fieldBlocks']);
        expect($labels)->toContain('APPLICANT_NUMBER_1..10')
            ->and($labels)->toContain('BIRTHDATE_1..6');
    }
    expect($result->pages[1]['fieldBlocks'][0]['origin'])->toBe([200, 260]);
});

test('final column overflow starts the next page and rows are atomic', function () use ($solver): void {
    $result = $solver->solve(blueprint(sections: [section('VERBAL', 1, 101)]));

    expect($result->pages)->toHaveCount(2);
    $pageOne = questionBlocks($result->pages[0]);
    $pageTwo = questionBlocks($result->pages[1]);

    expect($pageOne)->toHaveCount(4)
        ->and($pageTwo)->toHaveCount(1)
        ->and($pageTwo[0]['fieldLabels'])->toBe(['VERBAL_Q_101..101'])
        ->and($pageTwo[0]['origin'])->toBe([180, 900]);
});

test('exact fit at the last valid row keeps the column', function () use ($solver): void {
    $result = $solver->solve(blueprint(sections: [section('VERBAL', 1, 25)]));
    $blocks = questionBlocks($result->pages[0]);

    expect($blocks)->toHaveCount(1)
        ->and($blocks[0]['fieldLabels'])->toBe(['VERBAL_Q_001..025'])
        ->and($blocks[0]['origin'])->toBe([180, 900]);
});

test('one-row overflow starts the next column', function () use ($solver): void {
    $result = $solver->solve(blueprint(sections: [section('VERBAL', 1, 26)]));
    $blocks = questionBlocks($result->pages[0]);

    expect($blocks)->toHaveCount(2)
        ->and($blocks[0]['fieldLabels'])->toBe(['VERBAL_Q_001..025'])
        ->and($blocks[1]['fieldLabels'])->toBe(['VERBAL_Q_026..026'])
        ->and($blocks[1]['origin'])->toBe([620, 900]);
});

test('next_column break skips to the following column and next_page starts a new page', function () use ($solver): void {
    $withBreak = fn (string $break): array => blueprint(sections: [
        section('VERBAL', 1, 25),
        section('VERBAL', 26, 26, $break),
    ]);

    $col = $solver->solve($withBreak('next_column'));
    $colBlocks = questionBlocks($col->pages[0]);
    expect($colBlocks)->toHaveCount(2)
        ->and($colBlocks[1]['origin'])->toBe([620, 900]);

    $page = $solver->solve($withBreak('next_page'));
    expect($page->pages)->toHaveCount(2);
    $pageTwo = questionBlocks($page->pages[1]);
    expect($pageTwo)->toHaveCount(1)
        ->and($pageTwo[0]['fieldLabels'])->toBe(['VERBAL_Q_026..026'])
        ->and($pageTwo[0]['origin'])->toBe([180, 900]);
});

test('last-column next_column break continues on the next page', function () use ($solver): void {
    $result = $solver->solve(blueprint(sections: [
        section('VERBAL', 1, 100),
        section('VERBAL', 101, 101, 'next_column'),
    ]));

    expect($result->pages)->toHaveCount(2);
    $pageTwo = questionBlocks($result->pages[1]);
    expect($pageTwo[0]['origin'])->toBe([180, 900]);
});

test('validated column pin places the section and refuses when it cannot fit', function () use ($solver): void {
    $result = $solver->solve(blueprint(['columns' => 2], [
        section('VERBAL', 1, 25),
        section('QUANT', 1, 25, ['kind' => 'column', 'column' => 2]),
    ]));
    $blocks = questionBlocks($result->pages[0]);
    expect($blocks[1]['origin'])->toBe([1050, 900]);

    // Flow ends in the last column after 100 VERBAL rows on a 2-column sheet:
    // pinning QUANT into the current column has no remaining capacity.
    expect(fn () => $solver->solve(blueprint(['columns' => 2], [
        section('VERBAL', 1, 100),
        section('QUANT', 1, 40, ['kind' => 'column', 'column' => 2]),
    ])))->toThrow(OmrTemplateLayoutException::class, 'does not fit');
});

test('pin moving backward is refused', function () use ($solver): void {
    expect(fn () => $solver->solve(blueprint(['columns' => 2], [
        section('VERBAL', 1, 26), // flow lands in column 2
        section('VERBAL', 27, 30, ['kind' => 'column', 'column' => 1]),
    ])))->toThrow(OmrTemplateLayoutException::class, 'moves backward');
});

test('pin out of range is refused at validation time', function () use ($solver): void {
    $diagnostics = $solver->validate(blueprint(sections: [
        section('VERBAL', 1, 25, ['kind' => 'column', 'column' => 9]),
    ]));
    expect($diagnostics)->toHaveCount(1)
        ->and($diagnostics[0]->code)->toBe('invalid_break');
});

test('duplicate question ranges are refused with both sections named', function () use ($solver): void {
    $diagnostics = $solver->validate(blueprint(sections: [
        section('VERBAL', 1, 60),
        section('VERBAL', 41, 80),
    ]));
    expect($diagnostics)->toHaveCount(1)
        ->and($diagnostics[0]->code)->toBe('duplicate_question_range')
        ->and($diagnostics[0]->context['sections'])->toBe([1, 2]);
});

test('unsupported schema, paper, columns, and empty sections are typed', function () use ($solver): void {
    expect($solver->validate(blueprint(['schema' => 'bogus']))[0]->code)->toBe('unsupported_schema')
        ->and($solver->validate(blueprint(['paper' => 'a5']))[0]->code)->toBe('unsupported_paper')
        ->and($solver->validate(blueprint(['columns' => 5]))[0]->code)->toBe('unsupported_columns')
        ->and($solver->validate(blueprint(sections: []))[0]->code)->toBe('empty_sections')
        ->and($solver->validate(blueprint(sections: [section('VERBAL', 10, 5)]))[0]->code)->toBe('invalid_range');
});

test('identical blueprints produce byte-identical canonical JSON and hashes', function () use ($solver): void {
    $first = $solver->solve(blueprint(sections: [section('VERBAL', 1, 100)]));
    $second = $solver->solve(blueprint(sections: [section('VERBAL', 1, 100)]));

    expect($second->canonicalJson)->toBe($first->canonicalJson)
        ->and($second->templateJsonHash)->toBe($first->templateJsonHash);
});

test('canonical output round-trips through the pinned template shape', function () use ($solver): void {
    $result = $solver->solve(blueprint(sections: [section('VERBAL', 1, 100)]));

    expect(json_decode($result->canonicalJson, true))->toBe($result->template)
        ->and($result->template['pages'])->toHaveCount(1)
        ->and($result->template['pages'][0]['pageDimensions'])->toBe([2060, 3144])
        ->and($result->template['pages'][0]['preProcessors'][0]['name'])->toBe('CropPage');
});

test('3-column and 2-column layouts derive distinct column origins', function () use ($solver): void {
    $three = $solver->solve(blueprint(['columns' => 3], [section('VERBAL', 1, 30)]));
    $threeBlocks = questionBlocks($three->pages[0]);
    expect($threeBlocks)->toHaveCount(2) // 25 fit column 1; 5 flow to column 2
        ->and($threeBlocks[1]['origin'][0])->toBe(820);

    $two = $solver->solve(blueprint(['columns' => 2], [section('VERBAL', 1, 30)]));
    $twoBlocks = questionBlocks($two->pages[0]);
    expect($twoBlocks)->toHaveCount(2)
        ->and($twoBlocks[1]['origin'][0])->toBe(1050);
});

test('legal paper derives its own dimensions and page capacity', function () use ($solver): void {
    $result = $solver->solve(blueprint(['paper' => 'legal'], [section('VERBAL', 1, 101)]));

    expect($result->template['pageDimensions'])->toBe([2060, 3392])
        ->and($result->pages)->toHaveCount(1); // 101 rows still fit one legal page (28 rows/column × 4)
});
