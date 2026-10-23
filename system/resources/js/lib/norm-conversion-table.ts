import type { NormConversionInterval } from './test-instrument-editor';

export interface ParseIntervalCsvResult {
    intervals: NormConversionInterval[];
    errors: string[];
}

export interface IntervalCoverageValidation {
    hasOverlap: boolean;
    overlaps: string[];
    gaps: string[];
    valid: boolean;
    invalid: boolean;
}

export interface IntervalCoverageOptions {
    minAllowed?: number | string;
    precisionScale?: number;
}
const NUMBER_PATTERN = String.raw`-?(?:\d+(?:\.\d*)?|\.\d+)`;
const RANGE_PATTERN = new RegExp(
    `^\\s*(${NUMBER_PATTERN})\\s*-\\s*(${NUMBER_PATTERN})\\s*$`,
);

function parseFiniteNumber(value: string): number | null {
    if (value.trim() === '') {
        return null;
    }

    const number = Number(value.trim());
    return Number.isFinite(number) ? number : null;
}

function parseExactBound(value: string): string | null {
    const normalized = value
        .trim()
        .replace(/^(-?)\./, '$10.')
        .replace(/\.$/, '');
    const integer = normalized
        .replace(/^-/, '')
        .split('.')[0]
        .replace(/^0+/, '');
    return integer.length <= 12 && toGrid(normalized, 6) !== null
        ? normalized
        : null;
}

/** Parses a comma- or tab-delimited interval table, retaining row-specific errors. */
export function parseIntervalCsv(csvText: string): ParseIntervalCsvResult {
    const lines = csvText.replace(/^\uFEFF/, '').split(/\r?\n/);
    const intervals: NormConversionInterval[] = [];
    const errors: string[] = [];
    let columnMode: 'range-output' | 'min-max-output' | 'score-output' | null =
        null;
    let firstDataRow = true;

    lines.forEach((line, index) => {
        if (line.trim() === '') {
            return;
        }

        const rowNumber = index + 1;
        const delimiter = line.includes('\t') ? '\t' : ',';
        const cells = line.split(delimiter).map((cell) => cell.trim());

        if (firstDataRow) {
            firstDataRow = false;
            const header = cells.map((cell) =>
                cell.toLowerCase().replace(/\s+/g, '_'),
            );
            if (
                header.length === 3 &&
                header[0] === 'min_score' &&
                header[1] === 'max_score' &&
                header[2] === 'output_value'
            ) {
                columnMode = 'min-max-output';
                return;
            }
            if (
                header.length === 3 &&
                header[0] === 'min' &&
                header[1] === 'max' &&
                header[2] === 'converted'
            ) {
                columnMode = 'min-max-output';
                return;
            }
            if (
                header.length === 2 &&
                header[0] === 'score' &&
                header[1] === 'percentile'
            ) {
                columnMode = 'score-output';
                return;
            }
        }

        let minScore: string | null = null;
        let maxScore: string | null = null;
        let outputValue: number | null = null;

        if (columnMode === 'min-max-output' || cells.length === 3) {
            if (cells.length !== 3) {
                errors.push(
                    `Row ${rowNumber}: expected minimum, maximum, and output values.`,
                );
                return;
            }
            minScore = parseExactBound(cells[0]);
            maxScore = parseExactBound(cells[1]);
            outputValue = parseFiniteNumber(cells[2]);
        } else if (columnMode === 'score-output' || cells.length === 2) {
            if (cells.length !== 2) {
                errors.push(
                    `Row ${rowNumber}: expected a score or range and an output value.`,
                );
                return;
            }
            const range = cells[0].match(RANGE_PATTERN);
            if (range) {
                minScore = parseExactBound(range[1]);
                maxScore = parseExactBound(range[2]);
            } else {
                minScore = parseExactBound(cells[0]);
                maxScore = minScore;
            }
            outputValue = parseFiniteNumber(cells[1]);
        } else {
            errors.push(
                `Row ${rowNumber}: expected two or three delimited values.`,
            );
            return;
        }

        if (minScore === null || maxScore === null || outputValue === null) {
            errors.push(`Row ${rowNumber}: values must be finite numbers.`);
            return;
        }
        if (toGrid(minScore, 6)! > toGrid(maxScore, 6)!) {
            errors.push(
                `Row ${rowNumber}: minimum score cannot exceed maximum score.`,
            );
            return;
        }

        intervals.push({
            min_score: minScore,
            max_score: maxScore,
            output_value: outputValue,
        });
    });

    intervals.sort((left, right) => {
        const leftMin = toGrid(left.min_score, 6)!;
        const rightMin = toGrid(right.min_score, 6)!;
        const leftMax = toGrid(left.max_score, 6)!;
        const rightMax = toGrid(right.max_score, 6)!;
        return leftMin < rightMin
            ? -1
            : leftMin > rightMin
              ? 1
              : leftMax < rightMax
                ? -1
                : leftMax > rightMax
                  ? 1
                  : 0;
    });
    return { intervals, errors };
}

/** Converts decimal scores to the exact integer grid used by coverage checks. */
function toGrid(value: number | string, precisionScale: number): bigint | null {
    const match = /^(-?)(\d+)(?:\.(\d*))?$/.exec(String(value).trim());
    if (!match) return null;
    let fraction = match[3] ?? '';
    while (fraction.length > precisionScale && fraction.endsWith('0'))
        fraction = fraction.slice(0, -1);
    if (fraction.length > precisionScale) return null;
    const scale = 10n ** BigInt(precisionScale);
    const magnitude =
        BigInt(match[2]) * scale +
        BigInt(fraction + '0'.repeat(precisionScale - fraction.length) || '0');
    return match[1] === '-' ? -magnitude : magnitude;
}

function fromGrid(value: bigint, precisionScale: number): string {
    const scale = 10n ** BigInt(precisionScale);
    const negative = value < 0n;
    const magnitude = negative ? -value : value;
    const whole = magnitude / scale;
    if (precisionScale === 0) return `${negative ? '-' : ''}${whole}`;
    return `${negative ? '-' : ''}${whole}.${String(magnitude % scale).padStart(precisionScale, '0')}`;
}

export interface GeneratedNormInterval {
    min_score: string;
    max_score: string;
    output_value: '';
}

/** Generate at most rowLimit blank-value entries on an exact decimal grid. */
export function generateDecimalGridIntervals(
    minAllowed: number | string,
    maxAllowed: number | string,
    precisionScale: number,
    rowLimit = 500,
): GeneratedNormInterval[] | null {
    const scale = Math.max(0, Math.min(6, Math.trunc(precisionScale)));
    const min = toGrid(minAllowed, scale);
    const max = toGrid(maxAllowed, scale);
    if (min === null || max === null || max < min) return null;
    const count = max - min + 1n;
    if (count > BigInt(rowLimit)) return null;
    const rows: GeneratedNormInterval[] = [];
    for (let score = min; score <= max; score += 1n) {
        const value = fromGrid(score, scale);
        rows.push({ min_score: value, max_score: value, output_value: '' });
    }
    return rows;
}

/** Advance one exact grid unit without converting a decimal bound to binary floating point. */
export function nextDecimalGridValue(
    value: number | string,
    precisionScale: number,
): string | null {
    const scale = Math.max(0, Math.min(6, Math.trunc(precisionScale)));
    const grid = toGrid(value, scale);
    return grid === null ? null : fromGrid(grid + 1n, scale);
}

/** Accept structurally valid draft rows while allowing intentionally blank percentile outputs. */
export function intervalRowsWellFormed(
    intervals: NormConversionInterval[],
    precisionScale: number,
): boolean {
    const scale = Math.max(0, Math.min(6, Math.trunc(precisionScale)));
    return intervals.every((interval) => {
        const min = toGrid(interval.min_score, scale);
        const max = toGrid(interval.max_score, scale);
        if (min === null || max === null || min > max) return false;
        const outputText = String(interval.output_value ?? '').trim();
        if (outputText === '') return true;
        const output = parseFiniteNumber(outputText);
        return output !== null && output >= 0 && output <= 100;
    });
}
/** Validates inclusive interval coverage on the declared decimal grid. */
export function validateIntervalOverlapsAndGaps(
    intervals: NormConversionInterval[],
    maxAllowed?: number | string,
    options: IntervalCoverageOptions = {},
): IntervalCoverageValidation {
    const precisionScale = Math.max(
        0,
        Math.min(6, Math.trunc(options.precisionScale ?? 0)),
    );
    const minAllowed = toGrid(options.minAllowed ?? 0, precisionScale);
    const maxGrid =
        maxAllowed === undefined ? null : toGrid(maxAllowed, precisionScale);
    const rows = intervals.map((interval) => ({
        interval,
        min: toGrid(interval.min_score, precisionScale),
        max: toGrid(interval.max_score, precisionScale),
    }));
    const invalid = rows.some((row) => {
        const output = parseFiniteNumber(
            String(row.interval.output_value ?? ''),
        );
        return (
            row.min === null ||
            row.max === null ||
            row.min > row.max ||
            output === null ||
            output < 0 ||
            output > 100
        );
    });
    const validIntervals = rows
        .filter(
            (
                row,
            ): row is {
                interval: NormConversionInterval;
                min: bigint;
                max: bigint;
            } => row.min !== null && row.max !== null && row.min <= row.max,
        )
        .sort((left, right) =>
            left.min < right.min
                ? -1
                : left.min > right.min
                  ? 1
                  : left.max < right.max
                    ? -1
                    : left.max > right.max
                      ? 1
                      : 0,
        );
    const overlaps: string[] = [];
    const gaps: string[] = [];
    if (validIntervals.length === 0) {
        if (minAllowed !== null && maxGrid !== null && maxGrid >= minAllowed)
            gaps.push(
                `${fromGrid(minAllowed, precisionScale)}–${fromGrid(maxGrid, precisionScale)}`,
            );
        else gaps.push('No valid intervals');
        return {
            hasOverlap: false,
            overlaps,
            gaps,
            valid: false,
            invalid: true,
        };
    }
    const step = 1n;
    if (minAllowed === null || maxGrid === null || maxGrid < minAllowed)
        gaps.push('Invalid supported raw range');
    if (minAllowed !== null && validIntervals[0].min > minAllowed)
        gaps.push(
            `${fromGrid(minAllowed, precisionScale)}–${fromGrid(validIntervals[0].min - step, precisionScale)}`,
        );
    if (minAllowed !== null && validIntervals[0].min < minAllowed)
        gaps.push(
            `Intervals begin below supported minimum ${fromGrid(minAllowed, precisionScale)}`,
        );
    let coveredThrough = validIntervals[0].max;
    for (let index = 1; index < validIntervals.length; index += 1) {
        const interval = validIntervals[index];
        if (interval.min <= coveredThrough) {
            overlaps.push(
                `${fromGrid(interval.min, precisionScale)}–${fromGrid(interval.max < coveredThrough ? interval.max : coveredThrough, precisionScale)}`,
            );
        } else if (interval.min - coveredThrough > step) {
            gaps.push(
                `${fromGrid(coveredThrough + step, precisionScale)}–${fromGrid(interval.min - step, precisionScale)}`,
            );
        }
        if (interval.max > coveredThrough) coveredThrough = interval.max;
    }
    if (maxGrid !== null && coveredThrough < maxGrid)
        gaps.push(
            `${fromGrid(coveredThrough + step, precisionScale)}–${fromGrid(maxGrid, precisionScale)}`,
        );
    if (maxGrid !== null && coveredThrough > maxGrid)
        gaps.push(
            `Intervals exceed maximum ${fromGrid(maxGrid, precisionScale)}`,
        );
    return {
        hasOverlap: overlaps.length > 0,
        overlaps,
        gaps,
        valid: !invalid && overlaps.length === 0 && gaps.length === 0,
        invalid,
    };
}

/** Returns the unique inclusive interval output for rawScore, or null if unmatched/ambiguous. */
export function lookupIntervalScore(
    rawScore: number | string,
    intervals: NormConversionInterval[],
): number | null {
    const raw = toGrid(rawScore, 6);
    if (raw === null) return null;
    let matchedOutput: number | null = null;
    for (const interval of intervals) {
        const minimum = toGrid(interval.min_score, 6);
        const maximum = toGrid(interval.max_score, 6);
        const output = parseFiniteNumber(String(interval.output_value ?? ''));
        if (
            minimum !== null &&
            maximum !== null &&
            output !== null &&
            output >= 0 &&
            output <= 100 &&
            minimum <= maximum &&
            raw >= minimum &&
            raw <= maximum
        ) {
            if (matchedOutput !== null) return null;
            matchedOutput = output;
        }
    }
    return matchedOutput;
}
