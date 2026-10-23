// @vitest-environment jsdom

import { cleanup, fireEvent, render, within } from '@testing-library/svelte';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { compileFormula } from '../../resources/js/lib/raw-metric-formula';

const routerCalls = vi.hoisted(() => ({ put: vi.fn() }));
vi.mock('@inertiajs/svelte', async (importOriginal) => {
    const actual = await importOriginal<Record<string, unknown>>();
    const router = (actual.router as Record<string, unknown>) ?? {};
    return {
        ...actual,
        router: {
            ...router,
            on: (
                router.on as (
                    event: string,
                    callback: (event: unknown) => void,
                ) => () => void
            ).bind(router),
            put: (...args: unknown[]) => routerCalls.put(...args),
        },
        usePage: () => ({
            url: '/setup/test-kits/41/test-setup',
            props: {
                auth: {
                    user: {
                        id: 12,
                        name: 'Guidance Reviewer',
                        office: 'guidance',
                    },
                },
                institution: null,
                flash: {},
                staff_navigation: [],
                navigation_badges: { items: {} },
            },
        }),
    };
});

import TestSetup from '../../resources/js/Pages/Setup/TestKits/TestSetup.svelte';
import AptitudeAreasTableCard from '../../resources/js/Components/Setup/AptitudeAreasTableCard.svelte';

afterEach(() => {
    cleanup();
    routerCalls.put.mockReset();
});

function setupProps(read_only = false, rawOnly = false) {
    const formulaExpression = '=ROUND(0.20*A_RAW+0.80*B_RAW,2)';
    const inputContract = {
        allowed_sources: ['manual', 'import'],
        required_resources: [],
        source_requirements: {
            manual: {
                unit: 'count',
                identity_requirements: ['administration', 'metric'],
            },
            import: {
                unit: 'count',
                identity_requirements: ['administration', 'metric'],
            },
        },
    };
    const sections = ['A', 'B'].map((code) => ({
        code: `SECTION_${code}`,
        name: `Section ${code}`,
        response_mode: 'keyed_items',
        items: Array.from({ length: 20 }, (_, index) => ({
            item_code: `${code}_${index + 1}`,
            local_number: String(index + 1),
            is_scored: true,
            option_tokens: ['A', 'B'],
        })),
    }));
    const leaves = ['A', 'B'].map((code) => ({
        code: `${code}_RAW`,
        name: `Section ${code} raw`,
        rule_kind: 'correct_item_count',
        subtest_code: `SECTION_${code}`,
        unit: 'count',
        min_raw_value: '0',
        max_raw_value: '20',
        precision_scale: 0,
        input_codes: [],
        required_output_scales: [],
        input_contract_json: inputContract,
    }));
    const subset = {
        ...leaves[0],
        code: 'A_CORE_RAW',
        name: 'Selected A items',
        max_raw_value: '2',
        item_codes: ['A_1', 'A_2'],
    };
    const formula = {
        code: 'WEIGHTED_RAW',
        name: 'Weighted Area',
        rule_kind: 'formula_raw_metrics',
        subtest_code: null,
        unit: 'count',
        min_raw_value: '0',
        max_raw_value: '20',
        precision_scale: 2,
        is_required: true,
        required_output_scales: rawOnly ? [] : ['percentile_rank'],
        procedure_reference: null,
        construct_aptitude_area_id: null,
        input_codes: ['A_RAW', 'B_RAW'],
        formula_expression: formulaExpression,
        formula_ast_json: compileFormula(formulaExpression, ['A_RAW', 'B_RAW']),
        input_contract_json: {
            allowed_sources: ['manual', 'import'],
            required_resources: [],
            source_requirements: {},
        },
    };

    return {
        test_kit: null,
        preparation: {
            id: 12,
            revision: 4,
            selections: {
                test_instrument_version_id: 14,
                norm_package_version_id: 9,
                answer_sheet_draft_id: null,
                template_version_id: null,
                booklet_version_id: null,
                booklet_set_id: null,
            },
        },
        read_only,
        test_setup: {
            instrument: {
                id: 2,
                version_id: 14,
                lock_version: 3,
                profile: { code: 'FORMULA-TEST', display_name: 'Formula Test' },
                subtests: sections,
                metrics: [formula, ...leaves, subset],
            },
            norm: {
                package_id: 8,
                version_id: 9,
                lock_version: 2,
                package: {
                    code: 'FORMULA-NORM',
                    display_name: 'Formula Norms',
                },
                tables: rawOnly
                    ? []
                    : [
                          {
                              metric_code: 'WEIGHTED_RAW',
                              output_scale: 'percentile_rank',
                              intervals: [
                                  {
                                      min_score: 0,
                                      max_score: 20,
                                      output_value: 50,
                                  },
                              ],
                          },
                      ],
            },
        },
        kit_context: null,
        actions: {
            back: '/setup/test-instruments',
            save: '/setup/test-kits/41/test-setup',
        },
    };
}

describe('Test Setup formula persistence', () => {
    it('saves a changed exact subset without changing the full-section variable', async () => {
        routerCalls.put.mockImplementation((_url, _payload, options) =>
            options.onSuccess(),
        );
        const view = render(TestSetup, { props: setupProps() });
        const subsetRow = within(view.getByRole('row', { name: /A_CORE_RAW/ }));
        await fireEvent.click(
            subsetRow.getByRole('button', { name: 'Edit Area' }),
        );
        await fireEvent.click(view.getByRole('checkbox', { name: /A_3/ }));
        await fireEvent.click(view.getByRole('button', { name: 'Apply Area' }));
        await fireEvent.click(
            view.getByRole('button', { name: 'Save Test Setup' }),
        );

        expect(routerCalls.put).toHaveBeenCalledTimes(1);
        const metrics = routerCalls.put.mock.calls[0][1].instrument.metrics;
        const savedSubset = metrics.find(
            (metric) => metric.code === 'A_CORE_RAW',
        );
        expect(savedSubset.item_codes).toEqual(['A_1', 'A_2', 'A_3']);
        expect(Number(savedSubset.max_raw_value)).toBe(3);
        expect(
            Number(
                metrics.find((metric) => metric.code === 'A_RAW').max_raw_value,
            ),
        ).toBe(20);
    });

    it('shows stored formulas on a read-only Test Setup without enabling edits', async () => {
        const view = render(TestSetup, { props: setupProps(true) });

        expect(
            view.getByText(/^=\s*ROUND\(0\.20\*A_RAW\+0\.80\*B_RAW,2\)$/),
        ).toBeTruthy();
        await fireEvent.click(view.getByRole('button', { name: 'View Table' }));

        expect(
            (view.getByLabelText('Formula expression') as HTMLTextAreaElement)
                .disabled,
        ).toBe(true);
        expect(
            (
                view.getByRole('button', {
                    name: 'Apply Area',
                }) as HTMLButtonElement
            ).disabled,
        ).toBe(true);
    });

    it('applies and saves a raw-only formula without requiring percentile coverage', async () => {
        routerCalls.put.mockImplementation((_url, _payload, options) =>
            options.onSuccess(),
        );
        const view = render(TestSetup, { props: setupProps(false, true) });
        await fireEvent.click(
            within(view.getByRole('row', { name: /WEIGHTED_RAW/ })).getByRole(
                'button',
                { name: 'Edit Area' },
            ),
        );

        const applyArea = view.getByRole('button', {
            name: 'Apply Area',
        }) as HTMLButtonElement;
        expect(applyArea.disabled).toBe(false);
        await fireEvent.click(applyArea);
        await fireEvent.click(
            view.getByRole('button', { name: 'Save Test Setup' }),
        );

        const payload = routerCalls.put.mock.calls[0][1];
        expect(payload.instrument.metrics).toContainEqual(
            expect.objectContaining({
                code: 'WEIGHTED_RAW',
                rule_kind: 'formula_raw_metrics',
                required_output_scales: [],
                formula_expression: '=ROUND(0.20*A_RAW+0.80*B_RAW,2)',
            }),
        );
        expect(payload.norm.tables).toEqual([]);
    });

    it('authors a final-only aptitude area without exposing the formula or norm editor', async () => {
        const onDirty = vi.fn();
        const view = render(AptitudeAreasTableCard, {
            props: {
                metrics: [],
                tables: {},
                subtests: [],
                constructs: [{ id: 901, code: 'VERBAL', name: 'Verbal Aptitude' }],
                finalOnlyMode: true,
                onDirty,
            },
        });

        await fireEvent.click(view.getByRole('button', { name: 'Add Area' }));
        await fireEvent.input(view.getByLabelText('Code'), { target: { value: 'VERBAL_FINAL' } });
        await fireEvent.input(view.getByLabelText('Name'), { target: { value: 'Verbal Final' } });
        await fireEvent.change(view.getByLabelText('Construct'), { target: { value: '901' } });
        await fireEvent.change(view.getByLabelText('PR decimal precision'), { target: { value: '3' } });

        expect(view.queryByText('Formula Selector')).toBeNull();
        expect(view.queryByText('Percentile Conversion Table (0–100)')).toBeNull();
        await fireEvent.click(view.getByRole('button', { name: 'Apply Area' }));

        expect(onDirty).toHaveBeenCalledWith(true);
    });

    it('serializes a final-only Kit Setup with no norm object or raw subtests', async () => {
        routerCalls.put.mockImplementation((_url, _payload, options) =>
            options.onSuccess(),
        );
        const view = render(TestSetup, {
            props: {
                ...setupProps(),
                test_kit: { id: 41, code: 'FINAL-KIT', name: 'Final Kit' },
                preparation: {
                    id: 72,
                    revision: 1,
                    state: 'preparing',
                    selections: {
                        test_instrument_version_id: null,
                        norm_package_version_id: null,
                        answer_sheet_draft_id: null,
                        template_version_id: null,
                        booklet_version_id: null,
                        booklet_set_id: null,
                    },
                },
                test_setup: { instrument: null, norm: null },
                instrument_candidates: [],
                norm_candidates: [],
                constructs: [{ id: 901, code: 'VERBAL', name: 'Verbal Aptitude' }],
            },
        });

        await fireEvent.change(view.getByRole('combobox', { name: 'Scoring definition' }), {
            target: { value: 'final_only' },
        });
        await fireEvent.click(view.getByRole('button', { name: 'Add Area' }));
        await fireEvent.input(view.getByLabelText('Code'), { target: { value: 'VERBAL_FINAL' } });
        await fireEvent.input(view.getByLabelText('Name'), { target: { value: 'Verbal Final' } });
        await fireEvent.change(view.getByLabelText('Construct'), { target: { value: '901' } });
        await fireEvent.change(view.getByLabelText('PR decimal precision'), { target: { value: '3' } });
        expect(view.queryByText('Formula Selector')).toBeNull();
        expect(view.queryByText('Conversion Table')).toBeNull();
        await fireEvent.click(view.getByRole('button', { name: 'Apply Area' }));

        await fireEvent.click(view.getByRole('button', { name: /Show Test Details & Source/ }));
        await fireEvent.input(view.getByLabelText('Instrument code'), { target: { value: 'FINAL-KIT-PROFILE' } });
        await fireEvent.input(view.getByLabelText('Display name'), { target: { value: 'External final profile' } });
        await fireEvent.input(view.getByLabelText('Official source reference'), { target: { value: 'software-only://external-final/report' } });
        await fireEvent.input(view.getByLabelText('Permitted-use reference'), { target: { value: 'software-only://external-final/fixture-only' } });
        await fireEvent.input(view.getByLabelText('Administration procedure reference'), { target: { value: 'Synthetic external PR intake procedure.' } });
        await fireEvent.click(view.getByRole('button', { name: 'Save Test Setup' }));

        const payload = routerCalls.put.mock.calls[0][1];
        const metric = payload.instrument.metrics[0];
        expect(payload.instrument.profile).toMatchObject({
            material_requirement: 'no_paper',
            code: 'FINAL-KIT-PROFILE',
            display_name: 'External final profile',
        });
        expect(payload.instrument.subtests).toEqual([]);
        expect(metric).toMatchObject({
            code: 'VERBAL_FINAL',
            rule_kind: 'external_final',
            unit: null,
            precision_scale: 3,
            is_required: true,
            required_output_scales: ['percentile_rank'],
            construct_aptitude_area_id: 901,
            input_codes: [],
            input_contract_json: {
                allowed_sources: ['import', 'manual'],
                required_resources: [],
                source_requirements: {
                    import: { unit: 'percentile_rank', identity_requirements: ['administration', 'metric'] },
                    manual: { unit: 'percentile_rank', identity_requirements: ['administration', 'metric'] },
                },
            },
        });
        expect(payload.norm).toBeNull();
    });

    it('covers negative direct recorded-measure bounds on the declared decimal scale', async () => {
        const view = render(AptitudeAreasTableCard, {
            props: {
                metrics: [
                    {
                        code: 'REACTION_TIME_RAW',
                        name: 'Reaction Time',
                        rule_kind: 'recorded_measure',
                        subtest_code: 'TIMED_MEASURE',
                        input_codes: [],
                        unit: 'seconds',
                        min_raw_value: '-0.10',
                        max_raw_value: '0.20',
                        precision_scale: 2,
                        required_output_scales: ['percentile_rank'],
                        input_contract_json: {
                            allowed_sources: ['manual', 'import'],
                            required_resources: [],
                            source_requirements: {},
                        },
                    },
                ],
                tables: {
                    REACTION_TIME_RAW: [
                        { min_score: -0.1, max_score: 0, output_value: 10 },
                        { min_score: 0.01, max_score: 0.2, output_value: 90 },
                    ],
                },
                subtests: [
                    {
                        code: 'TIMED_MEASURE',
                        name: 'Timed measure',
                        response_mode: 'recorded_measure',
                        min_raw_value: '-0.10',
                        max_raw_value: '0.20',
                        unit: 'seconds',
                        items: [],
                    },
                ],
                constructs: [],
                readOnly: false,
            },
        });

        await fireEvent.click(view.getByRole('button', { name: 'Edit Area' }));

        expect(
            (view.getByLabelText('Minimum raw value') as HTMLInputElement)
                .value,
        ).toBe('-0.10');
        expect(
            (view.getByLabelText('Maximum raw value') as HTMLInputElement)
                .value,
        ).toBe('0.20');
        expect(
            (view.getByLabelText('Raw precision') as HTMLSelectElement).value,
        ).toBe('2');
        expect(
            view.getByText(/Coverage: -0\.10 to 0\.20 pts · Contiguous/),
        ).toBeTruthy();
        expect(
            (
                view.getByRole('button', {
                    name: 'Apply Area',
                }) as HTMLButtonElement
            ).disabled,
        ).toBe(false);
    });
});
