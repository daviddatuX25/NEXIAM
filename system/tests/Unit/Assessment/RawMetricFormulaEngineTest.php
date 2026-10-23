<?php

declare(strict_types=1);

use App\Assessment\Support\RawMetricFormulaEngine;
use InvalidArgumentException;

$rawMetricFormulaVectors = json_decode(
    file_get_contents(__DIR__.'/../../Fixtures/raw-metric-formula.json'),
    true,
    flags: JSON_THROW_ON_ERROR,
);

foreach ($rawMetricFormulaVectors['evaluations'] as $vector) {
    test('raw formula evaluates: '.$vector['name'], function () use ($vector): void {
        $engine = new RawMetricFormulaEngine;
        $ast = $engine->compile($vector['expression'], $vector['allowed_variables']);

        expect($engine->evaluate($ast, $vector['values'], $vector['scale']))->toBe($vector['expected']);
        if (isset($vector['expected_variables'])) {
            expect($engine->variables($ast))->toBe($vector['expected_variables']);
        }
        if (isset($vector['expected_ast'])) {
            expect($ast)->toBe($vector['expected_ast']);
        }
    });
}

foreach ($rawMetricFormulaVectors['bounds'] as $vector) {
    test('raw formula bounds: '.$vector['name'], function () use ($vector): void {
        $engine = new RawMetricFormulaEngine;
        $ast = $engine->compile($vector['expression'], $vector['allowed_variables']);

        expect($engine->bounds($ast, $vector['ranges'], $vector['scale']))->toBe($vector['expected']);
    });
}

foreach ($rawMetricFormulaVectors['invalid_formulas'] as $vector) {
    test('raw formula rejects: '.$vector['name'], function () use ($vector): void {
        $engine = new RawMetricFormulaEngine;

        expect(fn () => $engine->compile($vector['expression'], $vector['allowed_variables']))
            ->toThrow(InvalidArgumentException::class);
    });
}

foreach ($rawMetricFormulaVectors['invalid_bounds'] as $vector) {
    test('raw formula rejects invalid bounds: '.$vector['name'], function () use ($vector): void {
        $engine = new RawMetricFormulaEngine;
        $ast = $engine->compile($vector['expression'], $vector['allowed_variables']);

        expect(fn () => $engine->bounds($ast, $vector['ranges']))->toThrow(InvalidArgumentException::class);
    });
}

test('raw formula requires every referenced variable', function () use ($rawMetricFormulaVectors): void {
    $engine = new RawMetricFormulaEngine;
    $vector = $rawMetricFormulaVectors['missing'];
    $ast = $engine->compile($vector['expression'], $vector['allowed_variables']);

    expect(fn () => $engine->evaluate($ast, $vector['values']))
        ->toThrow(InvalidArgumentException::class, "Formula value for variable 'B' is missing");
});

test('raw formula rejects malformed persisted AST nodes', function () use ($rawMetricFormulaVectors): void {
    $engine = new RawMetricFormulaEngine;
    foreach ($rawMetricFormulaVectors['malicious_asts'] as $ast) {
        expect(fn () => $engine->evaluate($ast, []))->toThrow(InvalidArgumentException::class);
    }

    $deepAst = ['type' => 'number', 'value' => '1'];
    for ($depth = 0; $depth < 32; $depth++) {
        $deepAst = ['type' => 'unary', 'operator' => '-', 'operand' => $deepAst];
    }
    expect(fn () => $engine->evaluate($deepAst, []))->toThrow(InvalidArgumentException::class, '32-level nesting limit');
});

test('raw formula enforces parser and arithmetic limits', function () use ($rawMetricFormulaVectors): void {
    $engine = new RawMetricFormulaEngine;
    $limits = $rawMetricFormulaVectors['limits'];

    expect(fn () => $engine->compile(str_repeat('-', $limits['unary_depth']).'1', []))
        ->toThrow(InvalidArgumentException::class, '32-level nesting limit');
    expect(fn () => $engine->compile(implode('+', array_fill(0, $limits['node_terms'], '1')), []))
        ->toThrow(InvalidArgumentException::class, '256-node limit');
    expect(fn () => $engine->compile(str_repeat('1', $limits['formula_length']), []))
        ->toThrow(InvalidArgumentException::class, '2048 character limit');

    $variable = $engine->compile('A', ['A']);
    expect(fn () => $engine->evaluate($variable, ['A' => $limits['oversized_decimal']]))
        ->toThrow(InvalidArgumentException::class, 'bounded, non-exponential decimal strings');

    $multiplication = $engine->compile('A*A*A*A*A', ['A']);
    expect(fn () => $engine->evaluate($multiplication, ['A' => str_repeat('9', 64)]))
        ->toThrow(InvalidArgumentException::class, 'bounded exact-value limit');
});

test('raw formula supports only explicit output precision', function (): void {
    $engine = new RawMetricFormulaEngine;
    $ast = $engine->compile('1/3', []);

    expect(fn () => $engine->evaluate($ast, [], 7))
        ->toThrow(InvalidArgumentException::class, 'between 0 and 6');
});
