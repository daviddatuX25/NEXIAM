export type MetricValue = number | string | null | undefined;

export interface MetricInput {
    code: string;
    raw_score?: MetricValue;
    raw_value?: MetricValue;
    score?: MetricValue;
    value?: MetricValue;
}

export interface SumMetric {
    code: string;
    rule_kind?: string;
    input_codes: string[];
}

export interface MetricSumPreview {
    expression: string;
    score: number | null;
    isComplete: boolean;
    missingInputs: string[];
}

export interface NormInterval {
    min_score: number;
    max_score: number;
    output_value: number;
}

function finiteNumber(value: unknown): number | null {
    if (typeof value !== 'number' && typeof value !== 'string') {
        return null;
    }

    if (typeof value === 'string' && value.trim() === '') {
        return null;
    }

    const parsed = typeof value === 'number' ? value : Number(value);

    return Number.isFinite(parsed) ? parsed : null;
}

/** Calculates a sum metric from its declared input codes and currently entered metric values. */
export function evaluateMetricSum(
    metric: SumMetric,
    domainsOrMetrics: readonly (MetricInput | Record<string, MetricValue>)[],
): MetricSumPreview {
    const valuesByCode = new Map<string, number>();

    for (const entry of domainsOrMetrics) {
        if (!entry || typeof entry !== 'object' || typeof entry.code !== 'string') {
            continue;
        }

        const value = finiteNumber(
            entry.raw_score ?? entry.raw_value ?? entry.score ?? entry.value,
        );
        if (value !== null) {
            valuesByCode.set(entry.code, value);
        }
    }

    const inputCodes = Array.isArray(metric.input_codes) ? metric.input_codes : [];
    const missingInputs: string[] = [];
    const expressionParts: string[] = [];
    let score = 0;

    for (const code of inputCodes) {
        const value = valuesByCode.get(code);
        if (value === undefined) {
            missingInputs.push(code);
            expressionParts.push(`${code}: —`);
            continue;
        }

        score += value;
        expressionParts.push(`${code}: ${value}`);
    }

    const isComplete = inputCodes.length > 0 && missingInputs.length === 0 && Number.isFinite(score);

    return {
        expression: expressionParts.join(' + '),
        score: isComplete ? score : null,
        isComplete,
        missingInputs,
    };
}

/** Returns the output for the inclusive interval containing rawScore, or null when unavailable. */
export function previewNormConversion(
    rawScore: number,
    intervals: readonly NormInterval[],
): number | null {
    if (!Number.isFinite(rawScore) || !Array.isArray(intervals)) {
        return null;
    }

    const matchingIntervals = intervals.filter((interval) =>
        interval
        && Number.isFinite(interval.min_score)
        && Number.isFinite(interval.max_score)
        && Number.isFinite(interval.output_value)
        && interval.min_score <= interval.max_score
        && rawScore >= interval.min_score
        && rawScore <= interval.max_score,
    );

    // Ambiguous overlapping source ranges should never produce a fabricated preview.
    return matchingIntervals.length === 1 ? matchingIntervals[0].output_value : null;
}
