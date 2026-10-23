// @vitest-environment jsdom

import { cleanup, fireEvent, render, within } from '@testing-library/svelte';
import { afterEach, describe, expect, it, vi } from 'vitest';
import TestKitShow from '../../resources/js/Pages/Setup/TestKits/Show.svelte';

const { post, put } = vi.hoisted(() => ({ post: vi.fn(), put: vi.fn() }));
vi.mock('@inertiajs/svelte', async (importOriginal) => {
    const actual = await importOriginal<Record<string, unknown>>();
    const actualRouter = (actual.router as Record<string, unknown>) ?? {};

    return {
        ...actual,
        router: {
            ...actualRouter,
            post: (...args: unknown[]) => post(...args),
            put: (...args: unknown[]) => put(...args),
        },
    };
});

afterEach(() => {
    cleanup();
    post.mockReset();
    put.mockReset();
});

function props(overrides: Record<string, unknown> = {}) {
    return {
        test_kit: { id: 41, code: 'TK-HELD-01', name: 'Held candidate kit', is_synthetic: true },
        composition: {
            preparation_id: 12,
            revision: 7,
            component_vector: { sheet: 'sheet-r4', booklet: 'booklet-r6', profile: 'profile-r2' },
            requirements: { required_resources: { answer_sheet: true, booklet_form: true, key: true } },
            omr_applicable: true,
            map_applicable: true,
            map_complete: false,
            applicable_resources: { answer_sheet: true, booklet_form: true, key: true },
            preview_url: null,
            rows: [],
            groups: [],
            matched: 0,
            expected: 0,
        },
        preparation: {
            id: 12,
            revision: 7,
            state: 'preparing',
            selected: {
                test_profile: { name: 'Reasoning Profile', version_number: 2, status: 'approved' },
                norm: { name: 'Reasoning Norms', version_number: 1, status: 'Draft' },
                answer_sheet: { name: 'Two-page answer sheet', version_number: 4, status: 'Compiled' },
                booklet: { name: 'Booklet A', version_number: 6, set_code: 'A', status: 'Finalized' },
            },
            selections: { test_instrument_version_id: 14, norm_package_version_id: 9, answer_sheet_draft_id: 3, template_version_id: 14, booklet_version_id: 11, booklet_set_id: 17 },
            assembly_blockers: [],
        },
        component_choices: {
            test_instruments: [
                { id: 14, label: 'Reasoning Profile · v2', status: 'approved' },
                { id: 19, label: 'Reasoning Profile · v3', status: 'approved' },
            ],
            answer_sheet_drafts: [{ id: 3, label: 'Draft sheet', stage: 'Draft' }],
            answer_sheet_versions: [{ id: 14, draft_id: 3, label: 'Two-page answer sheet v4', status: 'Compiled' }],
            booklet_versions: [{ id: 11, set_id: 17, template_version_id: 14, label: 'Booklet A v6 · Set A', status: 'Finalized' }],
        },
        version_history: [],
        kit_context: { back_url: '/setup/exam-materials/test-kits?filter=mine' },
        actions: {
            index: '/setup/exam-materials/test-kits?filter=mine',
            test_setup: '/setup/exam-materials/test-kits/41/test-setup?context=kit',
            answer_sheet: '/setup/exam-materials/answer-sheets?test_kit_id=41',
            booklet: '/setup/exam-materials/booklets?test_kit_id=41',
            save_preparation: '/setup/exam-materials/test-kits/41/preparation',
            assemble: '/setup/exam-materials/test-kits/41/assemble',
            select_receipt: '/setup/exam-materials/test-kits/41/select-receipt',
            revoke: '/setup/exam-materials/test-kits/41/revoke',
        },
        ...overrides,
    };
}

describe('Test Kit workspace', () => {
    it('renders the three component selector cards with staff-facing labels and the configured Test Setup destination', () => {
        const view = render(TestKitShow, { props: props() });
        const setup = view.getByRole('region', { name: 'Test Kit preparation' });
        expect(within(setup).getByText('Reasoning Profile')).toBeTruthy();
        expect(within(setup).getByText('Norm: Reasoning Norms · Draft')).toBeTruthy();
        expect(within(setup).getAllByText('Two-page answer sheet v4').length).toBeGreaterThan(0);
        expect(within(setup).getAllByText('Booklet A v6 · Set A').length).toBeGreaterThan(0);
        expect(within(setup).getByLabelText('Ready Answer Sheet')).toBeTruthy();
        expect(within(setup).getByLabelText('Finalized Booklet (Set A)')).toBeTruthy();
        expect(within(setup).queryByText(/Matches selected Answer Sheet|No booklet matches this Answer Sheet/)).toBeNull();

        const setupUrl = new URL(view.getByRole('link', { name: /Configure Test Setup/ }).getAttribute('href') ?? '', 'http://localhost:3000');
        const answerSheetsUrl = new URL(view.getByRole('link', { name: /Manage Answer Sheets/ }).getAttribute('href') ?? '', 'http://localhost:3000');
        const bookletsUrl = new URL(view.getByRole('link', { name: /Manage Question Booklets/ }).getAttribute('href') ?? '', 'http://localhost:3000');
        const backUrl = new URL(view.getByRole('link', { name: 'Back to Test Kits' }).getAttribute('href') ?? '', 'http://localhost:3000');
        expect(setupUrl.pathname).toBe('/setup/exam-materials/test-kits/41/test-setup');
        expect(setupUrl.searchParams.get('context')).toBe('kit');
        expect(answerSheetsUrl.pathname).toBe('/setup/exam-materials/answer-sheets');
        expect(bookletsUrl.pathname).toBe('/setup/exam-materials/booklets');
        expect(backUrl.pathname).toBe('/setup/exam-materials/test-kits');
        expect(backUrl.searchParams.get('filter')).toBe('mine');
        expect((view.getByRole('button', { name: 'Lock' }) as HTMLButtonElement).disabled).toBe(false);
        expect(view.queryByRole('link', { name: /Trial|Activate/i })).toBeNull();
        expect(view.queryByRole('button', { name: /Activate/i })).toBeNull();
    });

    it('selects an explicit approved Test Setup without copying or inferring its declaration', async () => {
        put.mockImplementation((_url, _payload, options) => options.onFinish());
        const view = render(TestKitShow, { props: props() });
        const selector = view.getByLabelText('Select approved Test Setup') as HTMLSelectElement;

        expect(selector.value).toBe('14');
        await fireEvent.change(selector, { target: { value: '19' } });

        expect(put).toHaveBeenCalledTimes(1);
        expect(put.mock.calls[0][1]).toMatchObject({
            preparation_id: 12,
            expected_revision: 7,
            test_instrument_version_id: 19,
            norm_package_version_id: 9,
            template_version_id: 14,
            booklet_version_id: 11,
            booklet_set_id: 17,
        });
        expect(put.mock.calls[0][1]).not.toHaveProperty('expected_preparation_revision');
        expect(put.mock.calls[0][1]).not.toHaveProperty('required_resources');
        await view.rerender(props({
            preparation: {
                ...props().preparation,
                revision: 8,
                selections: { ...props().preparation.selections, test_instrument_version_id: 19 },
            },
        }));
        await fireEvent.click(view.getByRole('button', { name: 'Save', exact: true }));
        expect(put).toHaveBeenCalledTimes(2);
        expect(put.mock.calls[1][1]).toMatchObject({
            preparation_id: 12,
            expected_revision: 8,
            test_instrument_version_id: 19,
        });
    });

    it('loads the copied selections when Inertia reuses Show for Edit into a new preparation', async () => {
        put.mockImplementation((_url, _payload, options) => options.onFinish());
        const view = render(TestKitShow, { props: props({
            preparation: null,
            test_kit_version: {
                id: 81,
                status: 'candidate',
                version_number: 1,
                revision: 1,
                selected_acceptance_receipt_id: null,
            },
            actions: { ...props().actions, start_preparation: '/setup/exam-materials/test-kits/41/preparation/start' },
        }) });
        const nextPreparation = {
            ...props().preparation,
            id: 106,
            revision: 2,
            selections: {
                ...props().preparation.selections,
                test_instrument_version_id: 336,
                answer_sheet_draft_id: null,
                template_version_id: null,
                booklet_version_id: null,
                booklet_set_id: null,
            },
        };

        await view.rerender(props({
            preparation: nextPreparation,
            test_kit_version: null,
            component_choices: {
                ...props().component_choices,
                test_instruments: [{ id: 336, label: 'Approved profile · v1', status: 'approved' }],
                answer_sheet_versions: [],
                booklet_versions: [],
            },
        }));

        const selector = view.getByLabelText('Select approved Test Setup') as HTMLSelectElement;
        expect(selector.value).toBe('336');
        await fireEvent.click(view.getByRole('button', { name: 'Save', exact: true }));
        expect(put).toHaveBeenCalledTimes(1);
        expect(put.mock.calls[0][1]).toMatchObject({
            preparation_id: 106,
            expected_revision: 2,
            test_instrument_version_id: 336,
            template_version_id: null,
            booklet_version_id: null,
        });
    });

    it('auto-saves component changes and only assembles a complete preparation without blockers', async () => {
        const incompletePreparation = {
            ...props().preparation,
            selections: { answer_sheet_draft_id: null, template_version_id: null, booklet_version_id: null, booklet_set_id: null },
        };
        const blocked = render(TestKitShow, { props: props({
            preparation: incompletePreparation,
            component_choices: { answer_sheet_versions: [], booklet_versions: [] },
        }) });
        const answerSheet = blocked.getByLabelText('Ready Answer Sheet') as HTMLSelectElement;
        expect((blocked.getByRole('button', { name: 'Lock' }) as HTMLButtonElement).disabled).toBe(true);
        expect(answerSheet.disabled).toBe(false);
        await fireEvent.change(answerSheet, { target: { value: '' } });
        expect(put).toHaveBeenCalledTimes(1);
        expect(put.mock.calls[0][0]).toBe('/setup/exam-materials/test-kits/41/preparation');
        expect(put.mock.calls[0][1]).toMatchObject({ template_version_id: null, answer_sheet_draft_id: null });
        blocked.unmount();

        const view = render(TestKitShow, { props: props({ preparation: { ...props().preparation, assembly_blockers: [] } }) });
        expect((view.getByRole('button', { name: 'Lock' }) as HTMLButtonElement).disabled).toBe(false);
        await fireEvent.click(view.getByRole('button', { name: 'Lock' }));
        expect(post).toHaveBeenCalledTimes(1);
        expect(post.mock.calls[0][0]).toBe('/setup/exam-materials/test-kits/41/assemble');
    });

    it('does not expose a client-controlled OMR applicability toggle', () => {
        const view = render(TestKitShow, { props: props() });

        expect(view.queryByLabelText('Prepare Answer Sheet and matching')).toBeNull();
        expect(view.getByLabelText('Ready Answer Sheet')).toBeTruthy();
        expect(view.getByLabelText('Finalized Booklet (Set A)')).toBeTruthy();
    });

    it('retains receipt selection in More actions without introducing an activation action', async () => {
        const view = render(TestKitShow, { props: props({
            preparation: null,
            test_kit_version: { status: 'candidate', version_number: 3, revision: 7, selected_acceptance_receipt_id: null },
            test_profile: { display_name: 'Reasoning Profile', subtests_count: 5, form_code: 'RP-A', version_number: 2, status: 'Approved' },
            answer_sheet: { title: 'Two-page answer sheet', total_pages: 2, total_bubbles: 120, version_number: 4, status: 'Compiled', preview_url: '/answer-sheets/version/14/preview' },
            booklet: { title: 'Booklet A', set_code: 'A', total_keys: 5, version_number: 6, status: 'Finalized', workspace_url: '/booklets/family/11' },
            trial: {},
            recent_receipts: [{ id: 81, result: 'passed' }],
            readiness: {},
            actions: { ...props().actions, select_receipt: '/setup/exam-materials/test-kits/41/select-receipt' },
        }) });

        expect(view.queryByRole('link', { name: /Trial/i })).toBeNull();
        expect(view.queryByRole('button', { name: /Activate/i })).toBeNull();
        const details = view.container.querySelector('details') as HTMLDetailsElement;
        expect(details.open).toBe(false);
        await fireEvent.click(within(details).getByText('More'));
        expect(details.open).toBe(true);
        await fireEvent.change(within(details).getByLabelText('Select passing receipt'), { target: { value: '81' } });
        await fireEvent.click(within(details).getByRole('button', { name: 'Select Receipt' }));
        expect(post).toHaveBeenCalledTimes(1);
        expect(post.mock.calls[0][0]).toBe('/setup/exam-materials/test-kits/41/select-receipt');
        expect(post.mock.calls[0][1]).toMatchObject({ acceptance_receipt_id: '81', expected_revision: 7 });
    });
});
