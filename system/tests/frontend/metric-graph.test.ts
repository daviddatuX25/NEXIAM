import { describe, expect, it } from 'vitest';
import {
    evaluateMetricGraph,
    metricGraphLeafCodes,
    resolveMetricBounds,
} from '../../resources/js/lib/metric-graph';
import {
    lookupIntervalScore,
    parseIntervalCsv,
    validateIntervalOverlapsAndGaps,
} from '../../resources/js/lib/norm-conversion-table';

const subtests = [
    { code: 'A_SECTION', response_mode: 'keyed_items', max_raw_value: 10 },
    { code: 'B_SECTION', response_mode: 'keyed_items', max_raw_value: 10 },
];

const metrics = [
    {
        code: 'A_RAW',
        rule_kind: 'correct_item_count',
        subtest_code: 'A_SECTION',
        max_raw_value: 10,
        input_codes: [],
    },
    {
        code: 'B_RAW',
        rule_kind: 'correct_item_count',
        subtest_code: 'B_SECTION',
        max_raw_value: 10,
        input_codes: [],
    },
    {
        code: 'SUM_RAW',
        rule_kind: 'sum_raw_metrics',
        input_codes: ['A_RAW', 'B_RAW'],
        max_raw_value: 20,
    },
    {
        code: 'WEIGHTED_RAW',
        rule_kind: 'formula_raw_metrics',
        input_codes: ['SUM_RAW', 'B_RAW'],
        formula_expression: '=ROUND(0.20*SUM_RAW+0.80*B_RAW,0)',
        precision_scale: 0,
        max_raw_value: 20,
    },
];

describe('metric graph projection', () => {
    it('resolves nested exact formula bounds and leaf inputs', () => {
        expect(
            resolveMetricBounds('WEIGHTED_RAW', metrics, subtests, 0),
        ).toEqual({ min: '0', max: '12' });
        expect(metricGraphLeafCodes('WEIGHTED_RAW', metrics)).toEqual([
            'A_RAW',
            'B_RAW',
        ]);
    });

    it('evaluates nested metrics exactly and keeps missing values unavailable', () => {
        expect(
            evaluateMetricGraph('WEIGHTED_RAW', metrics, {
                A_RAW: '10',
                B_RAW: '10',
            }),
        ).toBe('12');
        expect(
            evaluateMetricGraph('WEIGHTED_RAW', metrics, {
                A_RAW: '10',
                B_RAW: null,
            }),
        ).toBeNull();
        expect(
            evaluateMetricGraph('WEIGHTED_RAW', metrics, { A_RAW: '10' }),
        ).toBeNull();
    });

    it('rejects cyclic dependencies as unavailable', () => {
        const cyclic = [
            {
                code: 'FIRST',
                rule_kind: 'sum_raw_metrics',
                input_codes: ['SECOND'],
            },
            {
                code: 'SECOND',
                rule_kind: 'sum_raw_metrics',
                input_codes: ['FIRST'],
            },
        ];
        expect(resolveMetricBounds('FIRST', cyclic, [])).toBeNull();
        expect(evaluateMetricGraph('FIRST', cyclic, {})).toBeNull();
    });

    it('keeps an item-scoped metric independent from the whole-subtest raw metric', () => {
        const scopedSubtests = [
            {
                code: 'SECTION_A',
                response_mode: 'keyed_items',
                max_raw_value: 8,
                items: [
                    { item_code: 'A_1', is_scored: true },
                    { item_code: 'A_2', is_scored: true },
                    { item_code: 'A_3', is_scored: true },
                    { item_code: 'A_4', is_scored: true },
                    { item_code: 'A_5', is_scored: true },
                    { item_code: 'A_6', is_scored: false },
                    {
                        item_code: 'A_PRACTICE',
                        is_scored: true,
                        group_type: 'practice',
                    },
                ],
            },
            subtests[1],
        ];
        const scopedMetrics = [
            {
                code: 'A_RAW',
                rule_kind: 'correct_item_count',
                subtest_code: 'SECTION_A',
                input_codes: [],
                max_raw_value: 8,
            },
            {
                code: 'A_CORE_RAW',
                rule_kind: 'correct_item_count',
                subtest_code: 'SECTION_A',
                input_codes: [],
                item_codes: ['A_1', 'A_2', 'A_3'],
            },
            {
                code: 'B_RAW',
                rule_kind: 'correct_item_count',
                subtest_code: 'B_SECTION',
                input_codes: [],
                max_raw_value: 10,
            },
            {
                code: 'WEIGHTED_CORE',
                rule_kind: 'formula_raw_metrics',
                input_codes: ['A_CORE_RAW', 'B_RAW'],
                formula_expression: '=0.20*A_CORE_RAW+0.80*B_RAW',
                precision_scale: 1,
            },
        ];

        expect(
            resolveMetricBounds('A_CORE_RAW', scopedMetrics, scopedSubtests, 1),
        ).toEqual({ min: '0', max: '3' });
        expect(metricGraphLeafCodes('WEIGHTED_CORE', scopedMetrics)).toEqual([
            'A_CORE_RAW',
            'B_RAW',
        ]);
        expect(
            evaluateMetricGraph('WEIGHTED_CORE', scopedMetrics, {
                A_RAW: '8',
                A_CORE_RAW: '3',
                B_RAW: '10',
                WEIGHTED_CORE: '99',
            }),
        ).toBe('8.6');
        expect(
            evaluateMetricGraph('WEIGHTED_CORE', scopedMetrics, {
                A_RAW: '8',
                B_RAW: '10',
            }),
        ).toBeNull();
        expect(
            resolveMetricBounds(
                'A_CORE_RAW',
                [
                    scopedMetrics[0],
                    { ...scopedMetrics[1], item_codes: ['A_PRACTICE'] },
                ],
                scopedSubtests,
                1,
            ),
        ).toBeNull();
        expect(
            resolveMetricBounds(
                'A_CORE_RAW',
                [
                    scopedMetrics[0],
                    { ...scopedMetrics[1], item_codes: ['A_1', 'A_1'] },
                ],
                scopedSubtests,
                1,
            ),
        ).toBeNull();
        expect(
            resolveMetricBounds(
                'A_CORE_RAW',
                [
                    scopedMetrics[0],
                    { ...scopedMetrics[1], item_codes: ['FOREIGN_ITEM'] },
                ],
                scopedSubtests,
                1,
            ),
        ).toBeNull();
    });
});

describe('norm interval coverage', () => {
    it('preserves and matches adjacent six-place CSV boundaries without floating point', () => {
        const parsed = parseIntervalCsv(
            '999999999999.999999,999999999999.999999,90\n999999999999.999998,999999999999.999998,80',
        );
        expect(parsed.errors).toEqual([]);
        expect(parsed.intervals.map((row) => row.min_score)).toEqual([
            '999999999999.999998',
            '999999999999.999999',
        ]);
        expect(
            lookupIntervalScore('999999999999.999998', parsed.intervals),
        ).toBe(80);
        expect(
            lookupIntervalScore('999999999999.999999', parsed.intervals),
        ).toBe(90);
        expect(
            lookupIntervalScore('999999999999.999997', parsed.intervals),
        ).toBeNull();
    });

    it('checks negative decimal bounds on the declared decimal grid', () => {
        expect(
            validateIntervalOverlapsAndGaps(
                [
                    { min_score: -0.1, max_score: 0, output_value: 10 },
                    { min_score: 0.01, max_score: 0.2, output_value: 90 },
                ],
                '0.20',
                { minAllowed: '-0.10', precisionScale: 2 },
            ).valid,
        ).toBe(true);
    });

    it('reports a missing decimal-grid point rather than rounding it away', () => {
        const coverage = validateIntervalOverlapsAndGaps(
            [
                { min_score: -0.1, max_score: 0, output_value: 10 },
                { min_score: 0.02, max_score: 0.2, output_value: 90 },
            ],
            '0.20',
            { minAllowed: '-0.10', precisionScale: 2 },
        );
        expect(coverage.valid).toBe(false);
        expect(coverage.gaps).toContain('0.01–0.01');
    });

    it('does not accept generated rows without actual norm values', () => {
        const coverage = validateIntervalOverlapsAndGaps(
            [
                { min_score: 0, max_score: 0, output_value: '' },
                { min_score: 1, max_score: 1, output_value: '' },
            ],
            1,
        );
        expect(coverage.valid).toBe(false);
        expect(coverage.invalid).toBe(true);
    });
});
