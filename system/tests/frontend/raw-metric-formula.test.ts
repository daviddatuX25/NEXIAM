import vectors from '../Fixtures/raw-metric-formula.json';
import {
    compileFormula,
    evaluateFormula,
    formulaBounds,
    formulaVariables,
} from '../../resources/js/lib/raw-metric-formula';
import { describe, expect, it } from 'vitest';

describe('raw metric formula shared vectors', () => {
    for (const vector of vectors.evaluations) {
        it(`evaluates ${vector.name}`, () => {
            const ast = compileFormula(
                vector.expression,
                vector.allowed_variables,
            );
            expect(evaluateFormula(ast, vector.values, vector.scale)).toBe(
                vector.expected,
            );
            if (vector.expected_variables)
                expect(formulaVariables(ast)).toEqual(
                    vector.expected_variables,
                );
            if (vector.expected_ast) expect(ast).toEqual(vector.expected_ast);
        });
    }

    for (const vector of vectors.bounds) {
        it(`bounds ${vector.name}`, () => {
            const ast = compileFormula(
                vector.expression,
                vector.allowed_variables,
            );
            expect(formulaBounds(ast, vector.ranges, vector.scale)).toEqual(
                vector.expected,
            );
        });
    }

    for (const vector of vectors.invalid_formulas) {
        it(`rejects ${vector.name}`, () => {
            expect(() =>
                compileFormula(vector.expression, vector.allowed_variables),
            ).toThrow();
        });
    }

    for (const vector of vectors.invalid_bounds) {
        it(`rejects invalid bounds: ${vector.name}`, () => {
            const ast = compileFormula(
                vector.expression,
                vector.allowed_variables,
            );
            expect(() => formulaBounds(ast, vector.ranges)).toThrow();
        });
    }

    it('requires every referenced variable', () => {
        const ast = compileFormula(
            vectors.missing.expression,
            vectors.missing.allowed_variables,
        );
        expect(() => evaluateFormula(ast, vectors.missing.values)).toThrow(
            "Formula value for variable 'B' is missing",
        );
    });

    it('rejects malformed persisted AST nodes', () => {
        for (const ast of vectors.malicious_asts) {
            expect(() => evaluateFormula(ast as never, {})).toThrow();
        }

        let deepAst: unknown = { type: 'number', value: '1' };
        for (let depth = 0; depth < 32; depth += 1) {
            deepAst = { type: 'unary', operator: '-', operand: deepAst };
        }
        expect(() => evaluateFormula(deepAst as never, {})).toThrow(
            '32-level nesting limit',
        );
    });

    it('enforces parser and arithmetic limits', () => {
        const limits = vectors.limits;
        expect(() =>
            compileFormula(`${'-'.repeat(limits.unary_depth)}1`, []),
        ).toThrow('32-level nesting limit');
        expect(() =>
            compileFormula(
                Array.from({ length: limits.node_terms }, () => '1').join('+'),
                [],
            ),
        ).toThrow('256-node limit');
        expect(() =>
            compileFormula('1'.repeat(limits.formula_length), []),
        ).toThrow('2048 character limit');

        const variable = compileFormula('A', ['A']);
        expect(() =>
            evaluateFormula(variable, { A: limits.oversized_decimal }),
        ).toThrow('bounded, non-exponential decimal strings');
        const multiplication = compileFormula('A*A*A*A*A', ['A']);
        expect(() =>
            evaluateFormula(multiplication, { A: '9'.repeat(64) }),
        ).toThrow('bounded exact-value limit');
    });

    it('enforces the declared final precision limit', () => {
        const ast = compileFormula('1/3', []);
        expect(() => evaluateFormula(ast, {}, 7)).toThrow('between 0 and 6');
    });
});
