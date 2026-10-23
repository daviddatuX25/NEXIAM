import {
    compileFormula,
    evaluateFormula,
    formulaBounds,
    type RawMetricFormulaAst,
} from './raw-metric-formula';

export interface MetricGraphMetric {
    code: string;
    rule_kind?: string | null;
    subtest_code?: string | null;
    input_codes?: string[] | null;
    formula_expression?: string | null;
    formula_ast_json?: RawMetricFormulaAst | null;
    precision_scale?: number | null;
    min_raw_value?: string | number | null;
    max_raw_value?: string | number | null;
    max_raw_score?: string | number | null;
    item_codes?: string[] | null;
}

export interface MetricGraphSubtest {
    code: string;
    response_mode?: string | null;
    min_raw_value?: string | number | null;
    max_raw_value?: string | number | null;
    max_raw_score?: string | number | null;
    items?: Array<{
        item_code?: string | null;
        is_scored?: boolean;
        group_type?: string;
        is_practice?: boolean;
    }>;
}

export interface MetricRawBounds {
    min: string;
    max: string;
}

/** Returns the direct raw-entry codes needed to evaluate a metric graph. */
export function metricGraphLeafCodes(
    metricCode: string,
    metrics: readonly MetricGraphMetric[],
): string[] {
    const leaves = new Set<string>();
    const visit = (code: string, visiting: Set<string>): void => {
        if (visiting.has(code)) return;
        const metric = metrics.find((candidate) => candidate.code === code);
        if (!metric) return;
        const inputs = metric.input_codes ?? [];
        if (inputs.length === 0) {
            leaves.add(metric.code);
            return;
        }
        const nextVisiting = new Set(visiting);
        nextVisiting.add(code);
        for (const inputCode of inputs) visit(inputCode, nextVisiting);
    };
    visit(metricCode, new Set());
    return [...leaves].sort();
}
/** Resolve conservative supported bounds through a metric dependency graph. */
export function resolveMetricBounds(
    metricCode: string,
    metrics: readonly MetricGraphMetric[],
    subtests: readonly MetricGraphSubtest[],
    precisionScale = 6,
): MetricRawBounds | null {
    return boundsFor(metricCode, metrics, subtests, precisionScale, new Set());
}

function boundsFor(
    code: string,
    metrics: readonly MetricGraphMetric[],
    subtests: readonly MetricGraphSubtest[],
    precisionScale: number,
    visiting: Set<string>,
): MetricRawBounds | null {
    if (visiting.has(code)) return null;
    const metric = metrics.find((candidate) => candidate.code === code);
    if (!metric) return null;
    if (metric.rule_kind === 'recorded_measure') {
        if (metric.min_raw_value == null || metric.max_raw_value == null)
            return null;
        return {
            min: String(metric.min_raw_value),
            max: String(metric.max_raw_value),
        };
    }
    if (metric.rule_kind === 'correct_item_count') {
        const subtest = subtests.find(
            (candidate) => candidate.code === metric.subtest_code,
        );
        if (!subtest || subtest.response_mode === 'recorded_measure')
            return null;
        const scored = (subtest.items ?? []).filter(
            (item) =>
                item.is_scored !== false &&
                item.group_type !== 'practice' &&
                item.is_practice !== true &&
                typeof item.item_code === 'string' &&
                item.item_code.trim() !== '',
        );
        if (metric.item_codes != null) {
            const allowed = new Set(
                scored.map((item) => item.item_code as string),
            );
            if (
                metric.item_codes.length === 0 ||
                new Set(metric.item_codes).size !== metric.item_codes.length ||
                metric.item_codes.some((itemCode) => !allowed.has(itemCode))
            )
                return null;
            return { min: '0', max: String(metric.item_codes.length) };
        }
        const maximum =
            subtest.max_raw_value ?? subtest.max_raw_score ?? scored.length;
        return { min: '0', max: String(maximum) };
    }

    const inputs = metric.input_codes ?? [];
    if (inputs.length === 0) return null;
    const nextVisiting = new Set(visiting);
    nextVisiting.add(code);
    const inputRanges: Record<string, MetricRawBounds> = {};
    for (const inputCode of inputs) {
        const inputRange = boundsFor(
            inputCode,
            metrics,
            subtests,
            precisionScale,
            nextVisiting,
        );
        if (!inputRange) return null;
        inputRanges[inputCode] = inputRange;
    }
    if (metric.rule_kind === 'formula_raw_metrics') {
        try {
            const ast = compileFormula(
                String(metric.formula_expression ?? ''),
                inputs,
            );
            return formulaBounds(
                ast,
                inputRanges,
                metric.precision_scale ?? precisionScale,
            );
        } catch {
            return null;
        }
    }
    const ast: RawMetricFormulaAst = {
        type: 'call',
        name: 'SUM',
        arguments: inputs.map((name) => ({ type: 'variable', name })),
    };
    return formulaBounds(ast, inputRanges, precisionScale);
}

/** Evaluate a metric graph exactly; missing or cyclic dependencies remain unavailable. */
export function evaluateMetricGraph(
    metricCode: string,
    metrics: readonly MetricGraphMetric[],
    rawValues: Readonly<Record<string, string | number | null | undefined>>,
): string | null {
    return valueFor(metricCode, metrics, rawValues, new Set());
}

function valueFor(
    code: string,
    metrics: readonly MetricGraphMetric[],
    rawValues: Readonly<Record<string, string | number | null | undefined>>,
    visiting: Set<string>,
): string | null {
    if (visiting.has(code)) return null;
    const metric = metrics.find((candidate) => candidate.code === code);
    if (!metric) return null;
    const inputs = metric.input_codes ?? [];
    if (inputs.length === 0) {
        if (!Object.hasOwn(rawValues, metric.code)) return null;
        const directValue = rawValues[metric.code];
        return directValue === null ||
            directValue === undefined ||
            String(directValue).trim() === ''
            ? null
            : String(directValue);
    }
    const nextVisiting = new Set(visiting);
    nextVisiting.add(code);
    const values: Record<string, string> = {};
    for (const inputCode of inputs) {
        const inputValue = valueFor(
            inputCode,
            metrics,
            rawValues,
            nextVisiting,
        );
        if (inputValue === null) return null;
        values[inputCode] = inputValue;
    }
    try {
        if (metric.rule_kind === 'formula_raw_metrics') {
            const ast = compileFormula(
                String(metric.formula_expression ?? ''),
                inputs,
            );
            return evaluateFormula(ast, values, metric.precision_scale ?? 6);
        }
        return evaluateFormula(
            {
                type: 'call',
                name: 'SUM',
                arguments: inputs.map((name) => ({ type: 'variable', name })),
            },
            values,
            6,
        );
    } catch {
        return null;
    }
}
