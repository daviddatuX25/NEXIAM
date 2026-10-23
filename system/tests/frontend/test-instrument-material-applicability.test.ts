// @vitest-environment jsdom

import { cleanup, fireEvent, render } from '@testing-library/svelte';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const formHarness = vi.hoisted(() => ({
    submissions: [] as Array<{
        method: string;
        url: string;
        data: Record<string, unknown>;
    }>,
    errors: {} as Record<string, string>,
}));

vi.mock('@inertiajs/svelte', async (importOriginal) => {
    const actual = await importOriginal<Record<string, unknown>>();

    return {
        ...actual,
        usePage: () => ({
            props: {
                auth: {
                    user: { id: 12, name: 'Guidance Reviewer', office: 'guidance' },
                },
                flash: {},
                staff_navigation: [],
            },
        }),
        useForm(initial: Record<string, unknown>) {
            let transformPayload = (data: Record<string, unknown>) => data;
            const submissionData = () => transformPayload(Object.fromEntries(
                Object.keys(initial).map((key) => [key, form[key]]),
            ));
            const form: Record<string, any> = {
                ...initial,
                errors: { ...formHarness.errors },
                processing: false,
                transform(transformer: (data: Record<string, unknown>) => Record<string, unknown>) {
                    transformPayload = transformer;
                    return form;
                },
                post(url: string) {
                    formHarness.submissions.push({ method: 'post', url, data: submissionData() });
                },
                put(url: string) {
                    formHarness.submissions.push({ method: 'put', url, data: submissionData() });
                },
            };

            return form;
        },
    };
});

import TestInstrumentEditor from '../../resources/js/Pages/Setup/TestInstruments/TestInstrumentEditor.svelte';
import TestInstrumentShow from '../../resources/js/Pages/Setup/TestInstruments/Show.svelte';

const requestId = '960af2d6-c4ee-4c3f-a72b-40f5e4d5f500';

beforeEach(() => {
    formHarness.submissions.length = 0;
    formHarness.errors = {};
});

afterEach(() => cleanup());

describe('test instrument material applicability', () => {
    it('defaults to unresolved and persists an explicit no-paper procedure declaration', async () => {
        const view = render(TestInstrumentEditor, {
            props: { mode: 'create', request_id: requestId, constructs: [] },
        });
        const requirement = view.getByRole('combobox', {
            name: 'Material requirement',
        }) as HTMLSelectElement;
        const procedure = view.getByRole('textbox', {
            name: 'Administration procedure reference',
        }) as HTMLInputElement;

        expect(requirement.value).toBe('unresolved');
        expect(procedure.required).toBe(false);
        expect(
            view.getByText(/does not create a schedule-free route/i),
        ).not.toBeNull();

        await fireEvent.input(view.getByLabelText('Instrument code'), {
            target: { value: 'NO_PAPER_PROFILE' },
        });
        await fireEvent.input(view.getByLabelText('Display name'), {
            target: { value: 'Direct Manual Profile' },
        });
        await fireEvent.change(requirement, {
            target: { value: 'no_paper' },
        });
        await fireEvent.input(procedure, {
            target: { value: 'Approved direct-manual administration protocol 4.2' },
        });
        await fireEvent.submit(view.container.querySelector('form')!);

        expect(formHarness.submissions).toHaveLength(1);
        expect(formHarness.submissions[0]).toMatchObject({
            method: 'post',
            url: '/setup/test-instruments',
            data: {
                profile: {
                    material_requirement: 'no_paper',
                    administration_procedure_reference:
                        'Approved direct-manual administration protocol 4.2',
                },
            },
        });
    });

    it('hydrates and re-saves the exact declared requirement and procedure on draft edit', async () => {
        const view = render(TestInstrumentEditor, {
            props: {
                mode: 'edit',
                instrument: { id: 33 },
                version: {
                    id: 71,
                    lock_version: 5,
                    profile: {
                        code: 'PAPER_PROFILE',
                        display_name: 'Paper Profile',
                        material_requirement: 'paper_backed',
                        administration_procedure_reference:
                            'Paper administration procedure, edition 2',
                    },
                    subtests: [],
                    metrics: [],
                },
                request_id: requestId,
                constructs: [],
            },
        });

        expect(
            (view.getByRole('combobox', {
                name: 'Material requirement',
            }) as HTMLSelectElement).value,
        ).toBe('paper_backed');
        expect(
            (
                view.getByRole('textbox', {
                    name: 'Administration procedure reference',
                }) as HTMLInputElement
            ).value,
        ).toBe('Paper administration procedure, edition 2');

        await fireEvent.submit(view.container.querySelector('form')!);

        expect(formHarness.submissions).toHaveLength(1);
        expect(formHarness.submissions[0]).toMatchObject({
            method: 'put',
            url: '/setup/test-instruments/33/versions/71',
            data: {
                profile: {
                    material_requirement: 'paper_backed',
                    administration_procedure_reference:
                        'Paper administration procedure, edition 2',
                },
            },
        });
    });

    it('displays the declared procedure and surfaces approval validation without implying a schedule-free route', () => {
        formHarness.errors = {
            'profile.material_requirement':
                'Choose a declared material requirement before approval.',
            'profile.administration_procedure_reference':
                'Record the approved administration procedure before approval.',
        };
        const draft = {
            id: 71,
            version_number: 2,
            lock_version: 5,
            display_name: 'Direct Manual Profile',
            publisher_name: 'Institutional source',
            form_code: 'DM-2',
            edition: '2',
            language: 'English',
            source_reference: 'Approved reference D-2',
            permitted_use_reference: 'Permission record P-2',
            material_requirement: 'no_paper',
            administration_procedure_reference:
                'Approved direct-manual administration protocol 4.2',
            subtests: [],
            metrics: [],
        };
        const view = render(TestInstrumentShow, {
            props: {
                request_id: requestId,
                instrument: {
                    id: 33,
                    code: 'NO_PAPER_PROFILE',
                    is_synthetic: false,
                    source_readiness: 'unverified',
                    raw_readiness: 'draft',
                    norm_readiness: 'unavailable',
                    draft_version: draft,
                    current_approved_version: null,
                    versions: [{ ...draft, status: 'draft' }],
                },
            },
        });

        expect(view.getAllByText('No-paper, direct-manual only')).not.toHaveLength(0);
        expect(
            view.getAllByText(
                'Approved direct-manual administration protocol 4.2',
            ),
        ).not.toHaveLength(0);
        expect(
            view.getByText(/not a schedule-free route/i),
        ).not.toBeNull();
        expect(
            view.getByText(
                'Choose a declared material requirement before approval.',
            ),
        ).not.toBeNull();
        expect(
            view.getByText(
                'Record the approved administration procedure before approval.',
            ),
        ).not.toBeNull();
    });

    it('opens AptitudeAreaModal without contractSelected errors and updates area name, code, and generated intervals', async () => {
        const view = render(TestInstrumentEditor, {
            props: {
                mode: 'edit',
                instrument: { id: 2, code: 'OASIS-3-ICAT' },
                version: {
                    id: 2,
                    lock_version: 1,
                    profile: {
                        code: 'OASIS-3-ICAT',
                        display_name: 'OASIS-3 College Entrance Test',
                        material_requirement: 'no_paper',
                    },
                    subtests: [
                        {
                            code: 'VOCABULARY',
                            name: 'Vocabulary',
                            response_mode: 'keyed_items',
                            max_raw_value: 3,
                            items: [
                                { item_code: 'V_1', is_scored: true, option_tokens: ['A', 'B'] },
                                { item_code: 'V_2', is_scored: true, option_tokens: ['A', 'B'] },
                                { item_code: 'V_3', is_scored: true, option_tokens: ['A', 'B'] },
                            ],
                        },
                    ],
                    metrics: [
                        {
                            code: 'VOCABULARY_RAW',
                            name: 'Vocabulary',
                            rule_kind: 'correct_item_count',
                            subtest_code: 'VOCABULARY',
                            input_codes: [],
                            max_raw_value: 3,
                            required_output_scales: ['percentile_rank'],
                            input_contract_json: {
                                allowed_sources: ['import', 'manual'],
                                required_resources: [],
                                source_requirements: {},
                            },
                        },
                    ],
                    tables: [],
                },
                request_id: requestId,
                constructs: [{ id: 1, code: 'VERBAL', name: 'Verbal Aptitude' }],
            },
        });

        await fireEvent.click(view.getByRole('button', { name: /Aptitude Areas/i }));
        await fireEvent.click(view.getAllByRole('button', { name: 'Edit Area' })[0]);

        const nameInput = view.getByLabelText('Area Name') as HTMLInputElement;
        expect(nameInput.value).toBe('Vocabulary');
        await fireEvent.input(nameInput, { target: { value: 'Vocabulary Updated' } });
        await fireEvent.click(view.getByRole('button', { name: 'Generate 0–3' }));
        for (const input of view.getAllByRole('spinbutton', { name: /percentile rank/i })) {
            await fireEvent.change(input, { target: { value: '50' } });
        }
        await fireEvent.click(view.getByRole('button', { name: 'Apply Area' }));
        await fireEvent.submit(view.container.querySelector('form')!);

        expect(formHarness.submissions).toHaveLength(1);
        expect(formHarness.submissions[0]).toMatchObject({
            method: 'put',
            url: '/setup/test-instruments/2/versions/2',
            data: {
                metrics: [
                    expect.objectContaining({
                        code: 'VOCABULARY_RAW',
                        name: 'Vocabulary Updated',
                    }),
                ],
            },
        });
        const intervals = formHarness.submissions[0].data.tables.VOCABULARY_RAW;
        expect(intervals.map((row) => ({
            min_score: Number(row.min_score),
            max_score: Number(row.max_score),
            output_value: Number(row.output_value),
        }))).toEqual([
            { min_score: 0, max_score: 0, output_value: 50 },
            { min_score: 1, max_score: 1, output_value: 50 },
            { min_score: 2, max_score: 2, output_value: 50 },
            { min_score: 3, max_score: 3, output_value: 50 },
        ]);
    });
    it('authors and persists a weighted formula through variable pills and precision controls', async () => {
        const inputContract = {
            allowed_sources: ['import', 'manual'],
            required_resources: [],
            source_requirements: {},
        };
        const view = render(TestInstrumentEditor, {
            props: {
                mode: 'edit',
                instrument: { id: 9, code: 'FORMULA-TEST' },
                version: {
                    id: 9,
                    lock_version: 1,
                    profile: { code: 'FORMULA-TEST', display_name: 'Formula Test', material_requirement: 'no_paper' },
                    subtests: [
                        { code: 'SECTION_A', name: 'Section A', response_mode: 'keyed_items', max_raw_value: 1, items: [{ item_code: 'A_1', is_scored: true, option_tokens: ['A', 'B'] }] },
                        { code: 'SECTION_B', name: 'Section B', response_mode: 'keyed_items', max_raw_value: 1, items: [{ item_code: 'B_1', is_scored: true, option_tokens: ['A', 'B'] }] },
                    ],
                    metrics: [
                        { code: 'A_RAW', name: 'Metric A', rule_kind: 'correct_item_count', subtest_code: 'SECTION_A', input_codes: [], max_raw_value: 1, required_output_scales: ['percentile_rank'], input_contract_json: inputContract },
                        { code: 'B_RAW', name: 'Metric B', rule_kind: 'correct_item_count', subtest_code: 'SECTION_B', input_codes: [], max_raw_value: 1, required_output_scales: ['percentile_rank'], input_contract_json: inputContract },
                    ],
                    tables: [],
                },
                request_id: requestId,
                constructs: [],
            },
        });

        await fireEvent.click(view.getByRole('button', { name: /Aptitude Areas/i }));
        await fireEvent.click(view.getByRole('button', { name: '+ Add Aptitude Area' }));
        await fireEvent.input(view.getByLabelText('Area Name'), { target: { value: 'Weighted Area' } });
        await fireEvent.input(view.getByLabelText('Area Code'), { target: { value: 'WEIGHTED_RAW' } });
        await fireEvent.click(view.getByRole('radio', { name: 'Composite Sum' }));
        await fireEvent.click(view.getByLabelText(/Metric A/));
        await fireEvent.click(view.getByLabelText(/Metric B/));
        await fireEvent.click(view.getByRole('checkbox', { name: 'Use a custom formula' }));

        const expression = view.getByLabelText('Formula expression') as HTMLTextAreaElement;
        await fireEvent.input(expression, { target: { value: '=' } });
        expression.setSelectionRange(1, 1);
        await fireEvent.select(expression);
        await fireEvent.click(view.getByRole('button', { name: 'Insert A_RAW' }));
        expect(expression.value).toBe('=A_RAW');
        await fireEvent.input(expression, { target: { value: '=0.20*A_RAW+0.80*B_RAW' } });

        const variableB = view.getByLabelText(/Metric B/) as HTMLInputElement;
        await fireEvent.click(variableB);
        expect(expression.value).toBe('=0.20*A_RAW+0.80*B_RAW');
        expect((view.getByRole('button', { name: 'Apply Area' }) as HTMLButtonElement).disabled).toBe(true);
        await fireEvent.click(variableB);
        await fireEvent.change(view.getByLabelText('Output precision'), { target: { value: '2' } });
        await fireEvent.click(view.getByRole('button', { name: 'Declare No Paper (manual / import)' }));
        await fireEvent.click(view.getByRole('button', { name: '+ Add Row' }));
        await fireEvent.change(view.getByLabelText('Row 1 raw to'), { target: { value: '1' } });
        await fireEvent.change(view.getByLabelText('Row 1 percentile rank'), { target: { value: '50' } });
        await fireEvent.click(view.getByRole('button', { name: 'Apply Area' }));
        await fireEvent.submit(view.container.querySelector('form')!);

        const submittedMetric = formHarness.submissions[0].data.metrics as Array<Record<string, unknown>>;
        expect(submittedMetric.find((metric) => metric.code === 'WEIGHTED_RAW')).toMatchObject({
            rule_kind: 'formula_raw_metrics',
            input_codes: ['A_RAW', 'B_RAW'],
            formula_expression: '=0.20*A_RAW+0.80*B_RAW',
            precision_scale: 2,
            formula_ast_json: { type: 'binary', operator: '+' },
        });
    });

    it('strips server-owned formula identity when resaving a normalized formula metric', async () => {
        const view = render(TestInstrumentEditor, {
            props: {
                mode: 'edit',
                instrument: { id: 10, code: 'FORMULA-TEST' },
                version: {
                    id: 11,
                    lock_version: 2,
                    profile: { code: 'FORMULA-TEST', display_name: 'Formula Test', material_requirement: 'no_paper' },
                    subtests: [],
                    metrics: [{
                        code: 'A_WEIGHTED',
                        name: 'Weighted Raw',
                        rule_kind: 'formula_raw_metrics',
                        subtest_code: null,
                        input_codes: ['A_RAW'],
                        formula_expression: '=A_RAW',
                        formula_ast_json: { type: 'variable', name: 'A_RAW' },
                        formula_algorithm_identity: 'nexiam.raw-formula.v1',
                        precision_scale: 2,
                        min_raw_value: '0',
                        max_raw_value: '10',
                        required_output_scales: [],
                        input_contract_json: {
                            allowed_sources: ['manual'],
                            required_resources: [],
                            source_requirements: {},
                        },
                    }],
                    tables: [],
                },
                request_id: requestId,
                constructs: [],
            },
        });

        await fireEvent.submit(view.container.querySelector('form')!);

        const submittedMetric = (formHarness.submissions[0].data.metrics as Array<Record<string, unknown>>)[0];
        expect(submittedMetric).toMatchObject({
            code: 'A_WEIGHTED',
            rule_kind: 'formula_raw_metrics',
            formula_expression: '=A_RAW',
            formula_ast_json: { type: 'variable', name: 'A_RAW' },
            precision_scale: 2,
        });
        expect(Object.hasOwn(submittedMetric, 'formula_algorithm_identity')).toBe(false);
    });

    it('applies a raw-only formula without requiring an empty percentile table to pass coverage', async () => {
        const inputContract = {
            allowed_sources: ['manual', 'import'],
            required_resources: [],
            source_requirements: {},
        };
        const view = render(TestInstrumentEditor, {
            props: {
                mode: 'edit',
                instrument: { id: 12, code: 'RAW-ONLY-TEST' },
                version: {
                    id: 13,
                    lock_version: 1,
                    profile: { code: 'RAW-ONLY-TEST', display_name: 'Raw Only Test', material_requirement: 'no_paper' },
                    subtests: [{ code: 'SECTION_A', name: 'Section A', response_mode: 'keyed_items', max_raw_value: 10, items: [] }],
                    metrics: [
                        { code: 'A_RAW', name: 'Metric A', rule_kind: 'correct_item_count', subtest_code: 'SECTION_A', input_codes: [], max_raw_value: 10, required_output_scales: ['percentile_rank'], input_contract_json: inputContract },
                        { code: 'RAW_ONLY', name: 'Raw Only', rule_kind: 'formula_raw_metrics', input_codes: ['A_RAW'], formula_expression: '=0.2*A_RAW', precision_scale: 2, min_raw_value: '0', max_raw_value: '2', required_output_scales: [], input_contract_json: inputContract },
                    ],
                    tables: [],
                },
                request_id: requestId,
                constructs: [],
            },
        });

        await fireEvent.click(view.getByRole('button', { name: /Aptitude Areas/i }));
        await fireEvent.click(view.getAllByRole('button', { name: 'Edit Area' })[1]);

        const applyArea = view.getByRole('button', { name: 'Apply Area' }) as HTMLButtonElement;
        expect((view.getByLabelText('Require percentile-rank conversion') as HTMLInputElement).checked).toBe(false);
        expect(applyArea.disabled).toBe(false);
        await fireEvent.click(applyArea);
        await fireEvent.submit(view.container.querySelector('form')!);

        const submittedMetrics = formHarness.submissions[0].data.metrics as Array<Record<string, unknown>>;
        expect(submittedMetrics.find((metric) => metric.code === 'RAW_ONLY')).toMatchObject({
            rule_kind: 'formula_raw_metrics',
            required_output_scales: [],
            formula_expression: '=0.2*A_RAW',
            precision_scale: 2,
        });
        expect(formHarness.submissions[0].data.tables).toMatchObject({ RAW_ONLY: [] });
    });
    it('authors an external-final metric without raw formulas or norm tables', async () => {
        const view = render(TestInstrumentEditor, {
            props: {
                mode: 'create',
                request_id: requestId,
                constructs: [{ id: 901, code: 'VERBAL', name: 'Verbal Aptitude' }],
            },
        });

        await fireEvent.change(view.getByRole('combobox', { name: 'Scoring definition' }), {
            target: { value: 'final_only' },
        });
        await fireEvent.click(view.getByRole('button', { name: '+ Add Aptitude Area' }));
        await fireEvent.input(view.getByLabelText('Area Name'), { target: { value: 'Verbal Final' } });
        await fireEvent.input(view.getByLabelText('Area Code'), { target: { value: 'VERBAL_FINAL' } });
        await fireEvent.change(view.getByLabelText('Institutional construct'), { target: { value: '901' } });
        expect(view.queryByText('Formula Builder')).toBeNull();
        expect(view.queryByText('Conversion Table')).toBeNull();
        await fireEvent.click(view.getByRole('button', { name: 'Apply Area' }));
        await fireEvent.input(view.getByLabelText('Instrument code'), { target: { value: 'EXTERNAL_FINAL_TEST' } });
        await fireEvent.input(view.getByLabelText('Display name'), { target: { value: 'External final test definition' } });
        await fireEvent.input(view.getByLabelText('Official source reference'), { target: { value: 'synthetic-source://percentile-report' } });
        await fireEvent.input(view.getByLabelText('Permitted-use / digitization reference'), { target: { value: 'synthetic-permission://test-only' } });
        await fireEvent.input(view.getByLabelText('Administration procedure reference'), { target: { value: 'Synthetic external percentile-rank procedure.' } });
        await fireEvent.submit(view.container.querySelector('form')!);

        const metric = (formHarness.submissions[0].data.metrics as Array<Record<string, unknown>>)[0];
        expect(metric).toMatchObject({
            code: 'VERBAL_FINAL',
            rule_kind: 'external_final',
            unit: null,
            precision_scale: 2,
            is_required: true,
            required_output_scales: ['percentile_rank'],
            construct_aptitude_area_id: 901,
            min_raw_value: null,
            max_raw_value: null,
            subtest_code: null,
            input_codes: [],
            input_contract_json: {
                allowed_sources: ['import', 'manual'],
                required_resources: [],
            },
        });
        expect(formHarness.submissions[0].data.subtests).toEqual([]);
        expect(formHarness.submissions[0].data.tables).toEqual({ VERBAL_FINAL: [] });
    });
});
