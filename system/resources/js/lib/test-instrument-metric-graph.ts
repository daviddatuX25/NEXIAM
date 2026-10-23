import type { AptitudeFormula, SubtestSection } from './test-instrument-editor';

export interface FormulaBounds {
    min: number;
    max: number;
    expression: string;
}

/** Returns the number of scored keyed items in a subtest. */
export function calculateSubtestMaxRaw(subtest: SubtestSection): number {
    if (subtest.response_mode === 'recorded_measure') {
        const measuredMaximum = Number(subtest.max_raw_value);
        return Number.isFinite(measuredMaximum) ? measuredMaximum : 0;
    }

    return subtest.items.reduce(
        (total, item) => total + (item.is_scored ? 1 : 0),
        0,
    );
}

/** Resolves a formula's raw range and readable expression, following composites recursively. */
export function resolveFormulaBounds(
    formula: AptitudeFormula,
    allSubtests: SubtestSection[],
    allFormulas: AptitudeFormula[],
): FormulaBounds {
    const formulasByCode = new Map(allFormulas.map((candidate) => [candidate.code, candidate]));
    const subtestsByCode = new Map(allSubtests.map((subtest) => [subtest.code, subtest]));
    const resolving = new Set<string>();

    const resolve = (current: AptitudeFormula): FormulaBounds => {
        const declaredMin = Number(current.min_raw_value);
        const declaredMax = Number(current.max_raw_value);
        const hasDeclaredBounds = Number.isFinite(declaredMin)
            && Number.isFinite(declaredMax)
            && declaredMin <= declaredMax;

        if (current.type === 'direct') {
            const subtest = current.subtest_code
                ? subtestsByCode.get(current.subtest_code)
                : undefined;
            const max = subtest ? calculateSubtestMaxRaw(subtest) : NaN;
            const min = current.rule_kind === 'correct_item_count' ? 0 : declaredMin;
            const resolvedMax = current.rule_kind === 'correct_item_count' ? max : declaredMax;

            return {
                min: Number.isFinite(min) ? min : 0,
                max: Number.isFinite(resolvedMax) ? resolvedMax : 0,
                expression: subtest?.name ?? current.subtest_code ?? current.code,
            };
        }

        if (resolving.has(current.code)) {
            return { min: 0, max: 0, expression: current.code };
        }
        resolving.add(current.code);

        const components = current.input_codes.map((code) => {
            const subtest = subtestsByCode.get(code);
            if (subtest) {
                const max = calculateSubtestMaxRaw(subtest);
                return {
                    min: 0,
                    max,
                    expression: subtest.name,
                };
            }

            const inputFormula = formulasByCode.get(code);
            if (inputFormula) {
                return resolve(inputFormula);
            }

            return { min: 0, max: 0, expression: code };
        });
        resolving.delete(current.code);

        if (components.length === 0) {
            return {
                min: hasDeclaredBounds ? declaredMin : 0,
                max: hasDeclaredBounds ? declaredMax : 0,
                expression: current.name || current.code,
            };
        }

        return {
            min: components.reduce((total, component) => total + component.min, 0),
            max: components.reduce((total, component) => total + component.max, 0),
            expression: components.map((component) => component.expression).join(' + '),
        };
    };

    return resolve(formula);
}

/** Returns true when adding the inputs would create or preserve a cycle at targetCode. */
export function checkCyclicDependency(
    targetCode: string,
    inputCodes: string[],
    allFormulas: AptitudeFormula[],
): boolean {
    const formulasByCode = new Map(allFormulas.map((formula) => [formula.code, formula]));
    const visiting = new Set<string>();
    const visited = new Set<string>();

    const reachesTarget = (code: string): boolean => {
        if (code === targetCode) {
            return true;
        }
        if (visited.has(code)) {
            return false;
        }
        if (visiting.has(code)) {
            return true;
        }

        const formula = formulasByCode.get(code);
        if (!formula) {
            visited.add(code);
            return false;
        }

        visiting.add(code);
        const hasCycle = formula.input_codes.some(reachesTarget);
        visiting.delete(code);
        visited.add(code);
        return hasCycle;
    };

    return inputCodes.some(reachesTarget);
}

/** Lists non-cyclic subtest/formula inputs that can be selected for a composite. */
export function getEligibleComponentCandidates(
    targetCode: string,
    allSubtests: SubtestSection[],
    allFormulas: AptitudeFormula[],
): Array<{ code: string; name: string; max: number }> {
    const candidates: Array<{ code: string; name: string; max: number }> = [];

    for (const subtest of allSubtests) {
        if (subtest.code === targetCode) {
            continue;
        }
        candidates.push({
            code: subtest.code,
            name: subtest.name,
            max: calculateSubtestMaxRaw(subtest),
        });
    }

    for (const formula of allFormulas) {
        if (formula.code === targetCode || checkCyclicDependency(targetCode, [formula.code], allFormulas)) {
            continue;
        }

        candidates.push({
            code: formula.code,
            name: formula.name,
            max: resolveFormulaBounds(formula, allSubtests, allFormulas).max,
        });
    }

    return candidates;
}
