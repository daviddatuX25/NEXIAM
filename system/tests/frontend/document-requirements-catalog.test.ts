// @vitest-environment jsdom

import {
    cleanup,
    fireEvent,
    render,
    screen,
    within,
} from '@testing-library/svelte';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const mocks = vi.hoisted(() => ({
    get: vi.fn(),
    patch: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    page: { props: { auth: { user: null }, flash: {} } },
}));

vi.mock('@inertiajs/svelte', async (importOriginal) => {
    const actual = await importOriginal<Record<string, unknown>>();
    const actualRouter = (actual.router as Record<string, unknown>) ?? {};

    return {
        ...actual,
        usePage: () => mocks.page,
        useForm(initial: Record<string, unknown>) {
            const form: Record<string, unknown> = {
                ...initial,
                errors: {},
                processing: false,
                post(url: string) {
                    mocks.post(
                        url,
                        Object.fromEntries(
                            Object.entries(form).filter(
                                ([key]) =>
                                    ![
                                        'errors',
                                        'processing',
                                        'post',
                                        'put',
                                    ].includes(key),
                            ),
                        ),
                    );
                },
                put(url: string) {
                    mocks.put(
                        url,
                        Object.fromEntries(
                            Object.entries(form).filter(
                                ([key]) =>
                                    ![
                                        'errors',
                                        'processing',
                                        'post',
                                        'put',
                                    ].includes(key),
                            ),
                        ),
                    );
                },
            };

            return form;
        },
        router: { ...actualRouter, get: mocks.get, patch: mocks.patch },
    };
});

vi.mock('@/Pages/Staff/StaffLayout.svelte', () => ({
    default: (
        _anchor: unknown,
        { children }: { children?: (anchor: unknown) => void },
    ) => children?.(_anchor),
}));

import RequirementsIndex from '../../resources/js/Pages/Setup/Requirements/Index.svelte';
import RequirementForm from '../../resources/js/Pages/Setup/Requirements/RequirementForm.svelte';

const activeRequirement = {
    id: 1,
    code: 'DOC-001',
    label: 'Certificate of live birth',
    applicant_track: 'freshman',
    item_kind: 'document',
    sort_order: 10,
    is_mandatory: true,
    is_active: true,
    version: 2,
};

function renderIndex(overrides: Record<string, unknown> = {}) {
    return render(RequirementsIndex, {
        page_state: 'ready',
        requirements: [activeRequirement],
        filters: {},
        tracks: [{ value: 'freshman', label: 'First-year applicant' }],
        kinds: [
            { value: 'document', label: 'Document' },
            { value: 'container', label: 'Folder / Envelope' },
        ],
        ...overrides,
    });
}

function actionCell(root: HTMLElement): HTMLElement {
    const cell = root.querySelector('[data-action-cell]');
    if (!cell) {
        throw new Error('Expected an entity action cell');
    }

    return cell as HTMLElement;
}

beforeEach(() => {
    mocks.get.mockReset();
    mocks.patch.mockReset();
    mocks.post.mockReset();
    mocks.put.mockReset();
});

afterEach(() => cleanup());

describe('document requirements catalog', () => {
    it('renders the catalog index with a separate create page link', () => {
        renderIndex();

        expect(
            screen.getByRole('heading', { name: 'Document requirements' }),
        ).toBeTruthy();
        expect(
            screen
                .getByRole('link', { name: 'Add document requirement' })
                .getAttribute('href'),
        ).toContain('/setup/requirements/create');
        expect(
            screen.queryByRole('button', { name: 'Create requirement' }),
        ).toBeNull();
        expect(screen.queryByLabelText('Requirement label')).toBeNull();
    });

    it('renders table and card actions and patches the active state with its expected version', async () => {
        const { container } = renderIndex();

        const tableView = container.querySelector('.overflow-x-scroll');
        expect(tableView).not.toBeNull();
        expect(
            within(tableView as HTMLElement).getByText(
                'Certificate of live birth',
            ),
        ).toBeTruthy();
        expect(
            within(tableView as HTMLElement)
                .getByRole('link', { name: 'Edit' })
                .getAttribute('href'),
        ).toContain('/setup/requirements/1/edit');

        await fireEvent.click(
            within(actionCell(tableView as HTMLElement)).getByRole('button', {
                name: 'More actions',
            }),
        );
        const menu = await screen.findByRole('menu');
        expect(tableView?.contains(menu)).toBe(false);
        await fireEvent.click(
            screen.getByRole('menuitem', { name: 'Deactivate' }),
        );

        expect(mocks.patch).toHaveBeenCalledWith(
            '/setup/requirements/1/toggle-active',
            { is_active: false, expected_version: 2 },
            expect.objectContaining({
                preserveScroll: true,
                onFinish: expect.any(Function),
            }),
        );
        const patchOptions = mocks.patch.mock.calls[0]?.[2] as
            { onFinish?: () => void } | undefined;
        patchOptions?.onFinish?.();

        await fireEvent.click(screen.getByLabelText('Card view'));
        const cardView = container.querySelector('.grid.grid-cols-1.gap-4');
        expect(cardView).not.toBeNull();
        expect(
            within(cardView as HTMLElement)
                .getByRole('link', { name: 'Edit' })
                .getAttribute('href'),
        ).toContain('/setup/requirements/1/edit');

        await fireEvent.click(
            within(actionCell(cardView as HTMLElement)).getByRole('button', {
                name: 'More actions',
            }),
        );
        await screen.findByRole('menuitem', { name: 'Deactivate' });
        expect(
            screen.getByRole('menuitem', { name: 'Deactivate' }),
        ).toBeTruthy();
    });

    it('distinguishes an initially empty catalog from empty filtered results', () => {
        const { rerender } = renderIndex({
            page_state: 'empty',
            requirements: [],
        });

        expect(
            screen.getByRole('heading', {
                name: 'No document requirements yet',
            }),
        ).toBeTruthy();
        expect(
            screen.queryByRole('heading', {
                name: 'No matching document requirements',
            }),
        ).toBeNull();

        rerender({
            page_state: 'ready',
            requirements: [],
            filters: { search: 'missing' },
        });

        expect(
            screen.getByRole('heading', {
                name: 'No matching document requirements',
            }),
        ).toBeTruthy();
    });

    it('submits all editable fields in create mode', async () => {
        render(RequirementForm, {
            requirement: null,
            tracks: [
                { value: 'freshman', label: 'First-year applicant' },
                { value: 'transfer', label: 'Transfer applicant' },
            ],
            kinds: [
                { value: 'document', label: 'Document' },
                { value: 'container', label: 'Folder / Envelope' },
            ],
            defaults: {
                applicant_track: 'freshman',
                item_kind: 'document',
                sort_order: 100,
                is_mandatory: true,
                is_active: true,
            },
        });

        await fireEvent.input(screen.getByLabelText('Code'), {
            target: { value: 'DOC-020' },
        });
        await fireEvent.input(screen.getByLabelText('Requirement label'), {
            target: { value: 'Transfer records' },
        });
        await fireEvent.change(screen.getByLabelText('Applicant track'), {
            target: { value: 'transfer' },
        });
        await fireEvent.change(screen.getByLabelText('Item kind'), {
            target: { value: 'container' },
        });
        await fireEvent.input(screen.getByLabelText('Sort order'), {
            target: { value: '12' },
        });
        await fireEvent.click(screen.getByLabelText('Mandatory'));
        await fireEvent.click(screen.getByLabelText('Active'));
        await fireEvent.click(
            screen.getByRole('button', { name: 'Create requirement' }),
        );

        expect(mocks.post).toHaveBeenCalledWith('/setup/requirements', {
            code: 'DOC-020',
            label: 'Transfer records',
            applicant_track: 'transfer',
            item_kind: 'container',
            sort_order: 12,
            is_mandatory: false,
            is_active: false,
        });
    });

    it('keeps the code readonly and excludes it from edit submissions while sending the expected version', async () => {
        render(RequirementForm, {
            requirement: { ...activeRequirement, version: 7 },
            tracks: [{ value: 'freshman', label: 'First-year applicant' }],
            kinds: [{ value: 'document', label: 'Document' }],
        });

        const code = screen.getByLabelText('Code');
        expect(code.hasAttribute('readonly')).toBe(true);
        expect(
            screen.getByText('Code cannot be changed after creation.'),
        ).toBeTruthy();
        await fireEvent.click(
            screen.getByRole('button', { name: 'Save changes' }),
        );

        expect(mocks.put).toHaveBeenCalledOnce();
        expect(mocks.put).toHaveBeenCalledWith('/setup/requirements/1', {
            label: 'Certificate of live birth',
            applicant_track: 'freshman',
            item_kind: 'document',
            sort_order: 10,
            is_mandatory: true,
            is_active: true,
            expected_version: 7,
        });
        expect(mocks.put.mock.calls[0][1]).not.toHaveProperty('code');
    });
});
