// @vitest-environment jsdom

import { cleanup, fireEvent, render, waitFor, within } from '@testing-library/svelte';
import { afterEach, describe, expect, it, vi } from 'vitest';
import TestSetup from '../../resources/js/Pages/Setup/TestKits/TestSetup.svelte';

const { put, visit, registerBefore } = vi.hoisted(() => ({
    put: vi.fn(),
    visit: vi.fn(),
    registerBefore: vi.fn(() => () => {}),
}));

vi.mock('@inertiajs/svelte', async (importOriginal) => {
    const actual = await importOriginal<Record<string, unknown>>();
    const actualRouter = actual.router;
    const routerBase = actualRouter && typeof actualRouter === 'object' ? actualRouter : {};

    return {
        ...actual,
        router: {
            ...routerBase,
            put: (...args: unknown[]) => put(...args),
            visit: (...args: unknown[]) => visit(...args),
            on: (...args: unknown[]) => registerBefore(...args),
        },
    };
});

afterEach(() => {
    cleanup();
    put.mockReset();
    visit.mockReset();
    registerBefore.mockReset();
    registerBefore.mockImplementation(() => () => {});
});

const selections = {
    test_instrument_version_id: 12,
    norm_package_version_id: 9,
    answer_sheet_draft_id: 21,
    template_version_id: 31,
    booklet_version_id: 41,
    booklet_set_id: 51,
};

function props(overrides: Record<string, unknown> = {}) {
    return {
        test_kit: { id: 4, code: 'TK-SETUP-04', name: 'Reasoning Kit' },
        preparation: { id: 14, revision: 6, state: 'preparing', selections: { ...selections } },
        read_only: false,
        test_setup: {
            instrument: {
                id: 7,
                version_id: 12,
                version_number: 3,
                lock_version: 5,
                status: 'draft',
                source_reviewed_at: null,
                approved_at: null,
                profile: {
                    code: 'REASON-01',
                    display_name: 'Reasoning Profile',
                    publisher_name: 'Institute Press',
                    form_code: 'A',
                    edition: '2024',
                    language: 'English',
                    source_reference: 'Manual, page 8',
                    permitted_use_reference: 'License REF-1',
                    material_requirement: 'unresolved',
                    administration_procedure_reference: '',
                },
                subtests: [
                    { code: 'VERBAL', name: 'Verbal Reasoning', response_mode: 'keyed_items', max_raw_value: 2, items: [] },
                    { code: 'LOGIC', name: 'Logic', response_mode: 'keyed_items', max_raw_value: 3, items: [] },
                    { code: 'QUANT', name: 'Quantitative', response_mode: 'keyed_items', max_raw_value: 4, items: [] },
                ],
                metrics: [
                    {
                        code: 'VERBAL_SCORE',
                        name: 'Verbal Score',
                        rule_kind: 'correct_item_count',
                        subtest_code: 'VERBAL',
                        input_codes: [],
                        min_raw_value: 0,
                        max_raw_value: 2,
                        required_output_scales: ['percentile_rank'],
                    },
                    {
                        code: 'LOGIC_SCORE',
                        name: 'Logic Score',
                        rule_kind: 'correct_item_count',
                        subtest_code: 'LOGIC',
                        input_codes: [],
                        min_raw_value: 0,
                        max_raw_value: 3,
                        required_output_scales: ['percentile_rank'],
                    },
                    {
                        code: 'QUANT_SCORE',
                        name: 'Quantitative Score',
                        rule_kind: 'correct_item_count',
                        subtest_code: 'QUANT',
                        input_codes: [],
                        min_raw_value: 0,
                        max_raw_value: 4,
                        required_output_scales: ['percentile_rank'],
                    },
                ],
            },
            norm: {
                package_id: 3,
                version_id: 9,
                version_number: 2,
                lock_version: 4,
                status: 'draft',
                source_reviewed_at: '2026-01-10T00:00:00Z',
                approved_at: null,
                package: {
                    code: 'REASON-NORM',
                    display_name: 'Reasoning Norms',
                    publisher_name: 'Institute Press',
                    form_code: 'A',
                    edition: '2024',
                    language: 'English',
                    applicable_population: 'First-year applicants',
                    source_reference: 'Norm manual, page 24',
                    permitted_use_reference: 'License N-1',
                },
                tables: [
                    {
                        metric_code: 'VERBAL_SCORE',
                        output_scale: 'percentile_rank',
                        intervals: [{ min_score: 0, max_score: 1, output_value: '25' }, { min_score: 2, max_score: 2, output_value: '75' }],
                    },
                    {
                        metric_code: 'LOGIC_SCORE',
                        output_scale: 'percentile_rank',
                        intervals: [{ min_score: 0, max_score: 1, output_value: '10' }, { min_score: 2, max_score: 3, output_value: '90' }],
                    },
                    {
                        metric_code: 'QUANT_SCORE',
                        output_scale: 'percentile_rank',
                        intervals: [{ min_score: 0, max_score: 2, output_value: '30' }, { min_score: 3, max_score: 4, output_value: '95' }],
                    },
                ],
            },
        },
        instrument_candidates: [{
            instrument_id: 7,
            version_id: 12,
            code: 'REASON-01',
            display_name: 'Reasoning Profile',
            version_number: 3,
            lock_version: 5,
            status: 'draft',
            publisher_name: 'Institute Press',
            form_code: 'A',
            edition: '2024',
            language: 'English',
            source_reference: 'Manual, page 8',
            permitted_use_reference: 'License REF-1',
        }],
        norm_candidates: [{
            package_id: 3,
            version_id: 9,
            code: 'REASON-NORM',
            display_name: 'Reasoning Norms',
            version_number: 2,
            lock_version: 4,
            status: 'draft',
            publisher_name: 'Institute Press',
            form_code: 'A',
            edition: '2024',
            language: 'English',
            applicable_population: 'First-year applicants',
            source_reference: 'Norm manual, page 24',
            permitted_use_reference: 'License N-1',
        }],
        constructs: [],
        metric_codes: [
            { code: 'VERBAL_SCORE', name: 'Verbal Score', required_output_scales: ['percentile_rank'] },
            { code: 'LOGIC_SCORE', name: 'Logic Score', required_output_scales: ['percentile_rank'] },
            { code: 'QUANT_SCORE', name: 'Quantitative Score', required_output_scales: ['percentile_rank'] },
        ],
        actions: {
            save: '/setup/exam-materials/test-kits/4/test-setup',
            save_preparation: '/setup/exam-materials/test-kits/4/preparation',
            back: '/setup/exam-materials/test-kits/4',
            instrument_successor: null,
            norm_successor: null,
        },
        kit_context: {
            test_kit_id: 4,
            preparation_id: 14,
            test_kit_version_id: null,
            back_url: '/setup/exam-materials/test-kits/4',
        },
        ...overrides,
    };
}

function invokePutOption(index: number, key: string, value?: unknown) {
    const options = put.mock.calls[index]?.[2];
    if (!options || typeof options !== 'object' || !(key in options)) {
        throw new Error(`The save request did not provide ${key}.`);
    }
    const callback = options[key];
    if (typeof callback !== 'function') {
        throw new Error(`The save request ${key} option is not callable.`);
    }
    callback(value);
}

describe('Kit Test Setup', () => {
    it('creates a profile and norm draft from the empty source state with one strict atomic save', async () => {
        const view = render(TestSetup, { props: props({
            test_setup: { instrument: null, norm: null },
            instrument_candidates: [],
            norm_candidates: [],
        }) });

        const source = view.container.querySelector('details[aria-label="Preparation sources"]') as HTMLDetailsElement;
        await fireEvent.click(within(source).getByText(/Source · No instrument selected · No norm selected/));
        await fireEvent.click(view.getByText(/Show Test Details & Source/));
        const details = view.container.querySelector('section[aria-labelledby="norm-source-heading"] details') as HTMLDetailsElement;
        await fireEvent.click(within(details).getByText('Details'));
        await fireEvent.input(view.getByLabelText('Instrument code'), { target: { value: 'NEW-REASON' } });
        await fireEvent.input(view.getByLabelText('Display name'), { target: { value: 'New Reasoning Profile' } });
        await fireEvent.input(view.getByLabelText('Norm package code'), { target: { value: 'NEW-NORM' } });
        await fireEvent.input(view.getByLabelText('Norm package name'), { target: { value: 'New Norm Source' } });
        await fireEvent.input(view.getByLabelText('Applicable population'), { target: { value: 'First-year applicants' } });
        await fireEvent.input(view.getByLabelText('Norm source reference'), { target: { value: 'Manual source, page 1' } });
        await fireEvent.click(view.getByRole('button', { name: 'Save Test Setup' }));

        expect(put).toHaveBeenCalledTimes(1);
        const [url, payload] = put.mock.calls[0];
        expect(url).toBe('/setup/exam-materials/test-kits/4/test-setup');
        expect(payload).toMatchObject({
            preparation_id: 14,
            expected_preparation_revision: 6,
            instrument: {
                id: null,
                version_id: null,
                expected_revision: null,
                profile: { code: 'NEW-REASON', display_name: 'New Reasoning Profile' },
                subtests: [],
                metrics: [],
            },
            norm: {
                package_id: null,
                version_id: null,
                expected_revision: null,
                package: {
                    code: 'NEW-NORM',
                    display_name: 'New Norm Source',
                    applicable_population: 'First-year applicants',
                    source_reference: 'Manual source, page 1',
                },
                tables: [],
            },
        });
        expect(payload.instrument).not.toHaveProperty('tables');
        expect(payload).toHaveProperty('idempotency_key');
    });

    it('selects a server-listed exact norm version while preserving every sibling preparation selection', async () => {
        const view = render(TestSetup, { props: props() });
        await fireEvent.change(view.getByLabelText('Norm source'), { target: { value: '9' } });

        expect(put).toHaveBeenCalledTimes(1);
        const [url, payload] = put.mock.calls[0];
        expect(url).toBe('/setup/exam-materials/test-kits/4/preparation');
        expect(payload).toEqual({
            preparation_id: 14,
            expected_revision: 6,
            idempotency_key: expect.any(String),
            ...selections,
            norm_package_version_id: 9,
        });
    });

    it('saves formula and norm intervals atomically, retains failed edits, then adopts fresh server revisions and data', async () => {
        const initial = props();
        const view = render(TestSetup, { props: initial });
        await fireEvent.click(view.getByRole('link', { name: /^Aptitude Scoring/ }));
        const verbalRow = within(view.getByRole('table', { name: 'Aptitude areas' })).getByRole('row', { name: /VERBAL_SCORE/ });
        await fireEvent.click(within(verbalRow).getByRole('button', { name: 'Edit Area' }));
        const modal = within(view.getByRole('dialog'));
        await fireEvent.click(modal.getByLabelText('Composite Sum'));
        await fireEvent.click(modal.getByRole('checkbox', { name: /Logic Score/ }));
        await fireEvent.click(modal.getByRole('checkbox', { name: /Quantitative Score/ }));
        await fireEvent.change(modal.getByLabelText('Row 2 percentile rank'), { target: { value: '80' } });
        await fireEvent.click(modal.getByRole('button', { name: 'Apply Area' }));
        await fireEvent.click(view.getByRole('button', { name: 'Save Test Setup' }));

        const firstCall = put.mock.calls[0];
        const firstPayload = {
            ...firstCall[1],
            norm: {
                ...firstCall[1].norm,
                tables: firstCall[1].norm.tables.map((table) => ({
                    ...table,
                    intervals: table.intervals.map((row) => ({
                        ...row,
                        min_score: Number(row.min_score),
                        max_score: Number(row.max_score),
                    })),
                })),
            },
        };
        expect(firstPayload).toMatchObject({
            expected_preparation_revision: 6,
            instrument: {
                id: 7,
                version_id: 12,
                expected_revision: 5,
                metrics: [
                    { code: 'VERBAL_SCORE', rule_kind: 'sum_raw_metrics', input_codes: ['LOGIC_SCORE', 'QUANT_SCORE'] },
                    { code: 'LOGIC_SCORE', input_codes: [] },
                    { code: 'QUANT_SCORE', input_codes: [] },
                ],
            },
            norm: {
                package_id: 3,
                version_id: 9,
                expected_revision: 4,
                tables: [
                    {
                        metric_code: 'VERBAL_SCORE',
                        output_scale: 'percentile_rank',
                        intervals: [{ min_score: 0, max_score: 1, output_value: '25' }, { min_score: 2, max_score: 2, output_value: '80' }],
                    },
                    {
                        metric_code: 'LOGIC_SCORE',
                        output_scale: 'percentile_rank',
                        intervals: [{ min_score: 0, max_score: 1, output_value: '10' }, { min_score: 2, max_score: 3, output_value: '90' }],
                    },
                    {
                        metric_code: 'QUANT_SCORE',
                        output_scale: 'percentile_rank',
                        intervals: [{ min_score: 0, max_score: 2, output_value: '30' }, { min_score: 3, max_score: 4, output_value: '95' }],
                    },
                ],
            },
        });
        invokePutOption(0, 'onError', { tables: 'Revision conflict.' });
        expect((await view.findByRole('alert')).textContent).toContain('Revision conflict.');
        expect(firstCall[1].idempotency_key).toMatch(/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i);

        await fireEvent.click(view.getByRole('button', { name: 'Save Test Setup' }));
        const retryCall = put.mock.calls[1];
        expect(retryCall[1].expected_preparation_revision).toBe(6);
        expect(retryCall[1].instrument.expected_revision).toBe(5);
        expect(retryCall[1].norm.expected_revision).toBe(4);
        expect(retryCall[1].idempotency_key).not.toBe(firstCall[1].idempotency_key);
        const retryTable = retryCall[1].norm.tables.find((table) => table.metric_code === 'VERBAL_SCORE');
        expect(retryTable.intervals.map((row) => [
            Number(row.min_score), Number(row.max_score), Number(row.output_value),
        ])).toEqual([[0, 1, 25], [2, 2, 80]]);
        invokePutOption(1, 'onSuccess');

        const refreshed = props({
            preparation: { id: 14, revision: 7, state: 'preparing', selections: { ...selections } },
            test_setup: {
                ...initial.test_setup,
                instrument: {
                    ...initial.test_setup.instrument,
                    lock_version: 6,
                    metrics: [
                        { ...initial.test_setup.instrument.metrics[0], rule_kind: 'sum_raw_metrics', subtest_code: null, input_codes: ['LOGIC_SCORE', 'QUANT_SCORE'] },
                        initial.test_setup.instrument.metrics[1],
                        initial.test_setup.instrument.metrics[2],
                    ],
                },
                norm: {
                    ...initial.test_setup.norm,
                    lock_version: 5,
                    tables: [
                        {
                            metric_code: 'VERBAL_SCORE',
                            output_scale: 'percentile_rank',
                            intervals: [{ min_score: 0, max_score: 1, output_value: '25' }, { min_score: 2, max_score: 2, output_value: '80' }],
                        },
                        initial.test_setup.norm.tables[1],
                        initial.test_setup.norm.tables[2],
                    ],
                },
            },
        });
        await view.rerender(refreshed);
        const aptitudeAreas = within(view.getByRole('table', { name: 'Aptitude areas' }));
        expect(aptitudeAreas.getByRole('row', { name: /VERBAL_SCORE/ }).textContent).toContain('2 rows');
        expect(aptitudeAreas.getByRole('row', { name: /VERBAL_SCORE/ }).textContent).toContain('LOGIC_SCORE + QUANT_SCORE');
        const updatedVerbalRow = aptitudeAreas.getByRole('row', { name: /VERBAL_SCORE/ });
        await fireEvent.click(within(updatedVerbalRow).getByRole('button', { name: 'Edit Area' }));
        const refreshedModal = within(view.getByRole('dialog'));
        expect(refreshedModal.getByLabelText('Row 2 percentile rank')).toHaveProperty('value', '80');
        expect(refreshedModal.getByRole('radio', { name: 'Composite Sum' })).toHaveProperty('checked', true);
        await fireEvent.change(refreshedModal.getByLabelText('Row 2 percentile rank'), { target: { value: '90' } });
        await fireEvent.click(refreshedModal.getByRole('button', { name: 'Apply Area' }));
        await fireEvent.click(view.getByRole('button', { name: 'Save Test Setup' }));

        const secondCall = put.mock.calls[2];
        expect(secondCall[1]).toMatchObject({ expected_preparation_revision: 7 });
        expect(secondCall[1].instrument.expected_revision).toBe(6);
        expect(secondCall[1].norm.expected_revision).toBe(5);
        expect(secondCall[1].idempotency_key).not.toBe(retryCall[1].idempotency_key);
    });

    it('keeps subtest edits dirty after applying the dialog and guards Back navigation', async () => {
        let before: ((event: { preventDefault: () => void; detail: { visit: { url: string; method: string; only?: string[] } } }) => void) | undefined;
        registerBefore.mockImplementation((eventName: string, callback: typeof before) => {
            if (eventName === 'before') before = callback;
            return () => {};
        });
        const view = render(TestSetup, { props: props() });
        const subtestTable = within(view.getByRole('table', { name: 'Subtests' }));
        const verbalRow = subtestTable.getByRole('row', { name: /VERBAL/ });
        await fireEvent.click(within(verbalRow).getByRole('button', { name: 'Edit' }));
        const dialog = within(view.getByRole('dialog', { name: 'Edit Subtest' }));
        await fireEvent.input(dialog.getByLabelText('Name'), { target: { value: 'Updated Verbal Reasoning' } });
        await fireEvent.click(dialog.getByRole('button', { name: 'Save Subtest' }));
        expect(subtestTable.getByRole('row', { name: /Updated Verbal Reasoning/ })).toBeTruthy();
        const preventShellRefresh = vi.fn();
        before!({
            preventDefault: preventShellRefresh,
            detail: { visit: { url: window.location.href, method: 'get', only: ['notifications', 'navigation_badges'] } },
        });
        expect(preventShellRefresh).not.toHaveBeenCalled();
        expect(view.queryByRole('dialog', { name: 'Discard unsaved Test Setup changes?' })).toBeNull();

        await fireEvent.click(view.getByRole('link', { name: 'Back to Kit' }));
        expect(await view.findByRole('dialog', { name: 'Discard unsaved Test Setup changes?' })).toBeTruthy();
        expect(put).not.toHaveBeenCalled();
    });

    it('keeps approved sources read-only and asks before dirty Inertia navigation', async () => {
        const approved = render(TestSetup, { props: props({
            read_only: true,
            actions: {
                ...props().actions,
                save: null,
                instrument_successor: '/setup/test-instruments/7/successor',
                norm_successor: '/setup/norm-packages/3/successor',
            },
        }) });
        expect(approved.getByText('Read-only source')).toBeTruthy();
        expect(approved.queryByRole('button', { name: 'Save Test Setup' })).toBeNull();
        approved.unmount();

        let before: ((event: { preventDefault: () => void; detail: { visit: { url: string; method: string } } }) => void) | undefined;
        registerBefore.mockImplementation((eventName: string, callback: typeof before) => {
            if (eventName === 'before') before = callback;
            return () => {};
        });
        const editable = render(TestSetup, { props: props() });
        await fireEvent.input(editable.getByLabelText('Norm package name'), { target: { value: 'Edited Norm Name' } });
        const preventDefault = vi.fn();
        before?.({ preventDefault, detail: { visit: { url: '/another-page', method: 'get' } } });
        expect(preventDefault).toHaveBeenCalledOnce();
        expect(await editable.findByRole('dialog', { name: 'Discard unsaved Test Setup changes?' })).toBeTruthy();
        await fireEvent.click(editable.getByRole('button', { name: 'Discard changes' }));
        expect(visit).toHaveBeenCalledWith('/another-page', expect.objectContaining({ method: 'get' }));
    });
});
