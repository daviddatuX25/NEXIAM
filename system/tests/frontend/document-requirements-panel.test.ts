// @vitest-environment jsdom

import { cleanup, fireEvent, render, screen } from '@testing-library/svelte';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const mocks = vi.hoisted(() => ({
    post: vi.fn(),
    reload: vi.fn(),
    page: { props: { flash: {} } },
}));

vi.mock('@inertiajs/svelte', async (importOriginal) => {
    const actual = (await importOriginal()) || {};
    const router = actual.router ?? {};

    return {
        ...actual,
        usePage: () => mocks.page,
        router: { ...router, post: mocks.post, reload: mocks.reload },
    };
});

import DocumentRequirementsPanel from '../../resources/js/Components/Admission/DocumentRequirementsPanel.svelte';

const checklist = {
    set: {
        id: 8,
        applicant_track: 'freshman',
        catalog_version: 2,
        is_current: true,
    },
    status: 'current',
    all_mandatory_verified: false,
    mandatory_pending_count: 1,
    suggestions: [
        {
            code: 'present-for-inspection',
            label: 'Present for Registrar inspection',
            template:
                'Please present {document_label} to the Registrar for inspection.',
        },
    ],
    items: [
        {
            id: 13,
            code: 'TOR',
            label: 'Transcript of Records',
            item_kind: 'document',
            is_mandatory: true,
            latest_status: 'missing',
            latest_verification_id: 21,
            applicant_instruction: null,
            internal_remarks: null,
        },
    ],
};

function renderPanel(overrides: Record<string, unknown> = {}) {
    return render(DocumentRequirementsPanel, {
        attemptId: 44,
        checklist,
        expectedVersion: 5,
        flags: {
            documents_view: true,
            documents_verify: true,
            documents_request: true,
        },
        verifyUrl: '/registrar/44/verify',
        requestUrl: '/admissions/44/document-correction-requests',
        ...overrides,
    });
}
async function chooseItemAction(itemLabel: string, action: string) {
    await fireEvent.click(
        screen.getByRole('button', { name: `More actions for ${itemLabel}` }),
    );
    await fireEvent.click(screen.getByRole('menuitem', { name: action }));
}

async function openRequestComposer() {
    await fireEvent.click(
        screen.getByRole('button', { name: 'Request document correction' }),
    );
}

describe('shared admission document checklist', () => {
    beforeEach(() => {
        mocks.post.mockReset();
        mocks.reload.mockReset();
        mocks.page.props.flash = {};
        mocks.post.mockImplementation(
            (
                _url: string,
                _payload: unknown,
                options: { onFinish?: () => void },
            ) => {
                options?.onFinish?.();
            },
        );
    });

    afterEach(() => cleanup());

    it('renders and mutates an always-on catalog checklist without a saved set', async () => {
        renderPanel({
            checklist: {
                ...checklist,
                set: null,
                items: [
                    {
                        ...checklist.items[0],
                        requirement_item_id: null,
                        intake_requirement_id: 55,
                    },
                ],
            },
        });

        expect(screen.getByText('Transcript of Records')).toBeTruthy();
        expect(screen.queryByRole('button', { name: 'Initialize requirements' })).toBeNull();
        await fireEvent.click(screen.getByRole('button', { name: 'Mark complete' }));
        expect(mocks.post.mock.calls[0][1]).toMatchObject({
            requirement_set_id: null,
            requirement_item_id: null,
            intake_requirement_id: 55,
            status: 'verified',
        });

        await chooseItemAction('Transcript of Records', 'Flag as deficient');
        await fireEvent.input(screen.getByLabelText(/Applicant instruction/), {
            target: { value: 'Please present the original.' },
        });
        await fireEvent.click(screen.getByRole('button', { name: 'Save deficient' }));
        expect(mocks.post.mock.calls[1][1]).toMatchObject({
            requirement_set_id: null,
            requirement_item_id: null,
            intake_requirement_id: 55,
            status: 'deficient',
        });

        await chooseItemAction('Transcript of Records', 'Add/edit internal note');
        await fireEvent.input(screen.getByLabelText(/Internal note/), {
            target: { value: 'Original reviewed at the counter.' },
        });
        await fireEvent.click(screen.getByRole('button', { name: 'Save internal note' }));
        expect(mocks.post.mock.calls[2][0]).toBe(
            '/registrar/enrollment-eligibility/44/requirements/internal-notes',
        );
        expect(mocks.post.mock.calls[2][1]).toMatchObject({
            requirement_set_id: null,
            requirement_item_id: null,
            intake_requirement_id: 55,
            internal_remarks: 'Original reviewed at the counter.',
        });
    });

    it('shows an empty state when no catalog requirements are active', () => {
        renderPanel({
            checklist: { ...checklist, set: null, items: [] },
        });

        expect(
            screen.getByText(
                'No active document requirements configured for this applicant track.',
            ),
        ).toBeTruthy();
        expect(screen.queryByRole('button', { name: 'Initialize requirements' })).toBeNull();
    });

    it('shows compact row actions by default and supports immediate completion', async () => {
        const { container } = renderPanel();

        expect(
            screen.getByRole('button', { name: 'Mark complete' }),
        ).toBeTruthy();
        expect(
            screen.getByRole('button', {
                name: 'More actions for Transcript of Records',
            }),
        ).toBeTruthy();
        const actionCell = container.querySelector('[data-action-cell]');
        expect(actionCell?.className).toContain('flex-nowrap');
        expect(screen.queryByLabelText('Include in document request')).toBeNull();
        expect(screen.queryByLabelText('Applicant instruction')).toBeNull();
        expect(screen.queryByLabelText('Internal note')).toBeNull();

        await fireEvent.click(
            screen.getByRole('button', { name: 'Mark complete' }),
        );

        expect(mocks.post).toHaveBeenCalledTimes(1);
        expect(mocks.post.mock.calls[0][0]).toBe('/registrar/44/verify');
        expect(mocks.post.mock.calls[0][1]).toMatchObject({
            status: 'verified',
            applicant_instruction: null,
            expected_version: 5,
            requirement_set_id: 8,
            requirement_item_id: 13,
        });
    });

    it('requires an applicant instruction and keeps the internal note private for deficient findings', async () => {
        renderPanel();
        await chooseItemAction('Transcript of Records', 'Flag as deficient');
        await fireEvent.click(
            screen.getByRole('button', { name: 'Save deficient' }),
        );

        expect(screen.getByRole('alert').textContent).toContain(
            'Add an applicant instruction',
        );
        expect(mocks.post).not.toHaveBeenCalled();

        await fireEvent.click(
            screen.getByRole('button', {
                name: 'Present for Registrar inspection',
            }),
        );
        await fireEvent.input(screen.getByLabelText(/Internal note/), {
            target: { value: 'Registrar-only observation' },
        });
        await fireEvent.click(
            screen.getByRole('button', { name: 'Save deficient' }),
        );

        expect(mocks.post).toHaveBeenCalledTimes(1);
        const [, payload] = mocks.post.mock.calls[0];
        expect(payload).toMatchObject({
            expected_version: 5,
            requirement_set_id: 8,
            requirement_item_id: 13,
            expected_latest_verification_id: 21,
            status: 'deficient',
            applicant_instruction:
                'Please present Transcript of Records to the Registrar for inspection.',
            internal_remarks: 'Registrar-only observation',
        });
        expect(payload).not.toHaveProperty('remarks');
    });

    it('records a missing finding from the progressive editor', async () => {
        renderPanel();
        await chooseItemAction('Transcript of Records', 'Mark missing');
        expect(screen.getByLabelText(/Internal note/)).toBeTruthy();
        expect(screen.queryByLabelText('Applicant instruction')).toBeNull();

        await fireEvent.click(
            screen.getByRole('button', { name: 'Save missing' }),
        );

        expect(mocks.post).toHaveBeenCalledTimes(1);
        expect(mocks.post.mock.calls[0][0]).toBe('/registrar/44/verify');
        expect(mocks.post.mock.calls[0][1]).toMatchObject({
            expected_latest_verification_id: 21,
            status: 'missing',
            applicant_instruction: null,
            internal_remarks: null,
        });
    });

    it('saves an internal note through the item-specific Registrar endpoint', async () => {
        renderPanel({
            internalNoteUrl: (item: { id: number }) =>
                `/registrar/enrollment-eligibility/44/requirements/${item.id}/internal-notes`,
            checklist: {
                ...checklist,
                items: [
                    {
                        ...checklist.items[0],
                        latest_note_id: 34,
                        internal_note: { id: 34, body: 'Earlier note' },
                    },
                ],
            },
        });
        await chooseItemAction(
            'Transcript of Records',
            'Add/edit internal note',
        );
        await fireEvent.input(screen.getByLabelText(/Internal note/), {
            target: { value: 'Registrar-only note update' },
        });
        await fireEvent.click(
            screen.getByRole('button', { name: 'Save internal note' }),
        );

        expect(mocks.post).toHaveBeenCalledTimes(1);
        expect(mocks.post.mock.calls[0][0]).toBe(
            '/registrar/enrollment-eligibility/44/requirements/13/internal-notes',
        );
        expect(mocks.post.mock.calls[0][1]).toMatchObject({
            expected_latest_verification_id: 21,
            expected_latest_note_id: 34,
            internal_remarks: 'Registrar-only note update',
        });
    });

    it('does not persist a canceled request review and lets staff select optional missing items deliberately', async () => {
        const optionalChecklist = {
            ...checklist,
            items: [
                {
                    ...checklist.items[0],
                    is_mandatory: false,
                    latest_status: 'missing',
                },
            ],
        };
        renderPanel({ checklist: optionalChecklist });
        await openRequestComposer();
        await fireEvent.click(
            screen.getByRole('checkbox', {
                name: 'Include in document request',
            }),
        );
        expect(
            (
                screen.getByLabelText(
                    'Applicant instruction for this request',
                ) as HTMLTextAreaElement
            ).value,
        ).toContain('Transcript of Records');
        await fireEvent.click(
            screen.getByRole('button', { name: 'Review 1 selected item' }),
        );
        expect(screen.getByRole('dialog').textContent).toContain(
            'Transcript of Records',
        );
        await fireEvent.click(screen.getByRole('button', { name: 'Cancel' }));
        expect(mocks.post).not.toHaveBeenCalled();
    });
    it('opens the request composer only from its CTA and freezes the reviewed request payload', async () => {
        renderPanel();
        expect(screen.queryByLabelText('Include in document request')).toBeNull();
        await openRequestComposer();
        await fireEvent.click(
            screen.getByRole('checkbox', {
                name: 'Include in document request',
            }),
        );
        await fireEvent.input(
            screen.getByLabelText('Applicant instruction for this request'),
            { target: { value: 'Please bring the transcript.' } },
        );
        await fireEvent.click(
            screen.getByRole('button', { name: 'Review 1 selected item' }),
        );
        expect(mocks.post).not.toHaveBeenCalled();
        expect(screen.getByRole('dialog').textContent).toContain(
            'Please bring the transcript.',
        );
        await fireEvent.click(
            screen.getByRole('button', { name: 'Send document request' }),
        );
        expect(mocks.post).toHaveBeenCalledTimes(1);
        const firstPayload = mocks.post.mock.calls[0][1];
        expect(mocks.post.mock.calls[0][0]).toBe(
            '/admissions/44/document-correction-requests',
        );
        expect(firstPayload).toMatchObject({
            expected_version: 5,
            requirement_set_id: 8,
            items: [
                {
                    requirement_item_id: 13,
                    expected_latest_verification_id: 21,
                    applicant_instruction: 'Please bring the transcript.',
                },
            ],
            overall_note: null,
        });
        expect(Object.keys(firstPayload).sort()).toEqual([
            'expected_version',
            'idempotency_key',
            'items',
            'overall_note',
            'requirement_set_id',
        ]);
        expect(firstPayload).not.toHaveProperty('internal_remarks');
        expect(firstPayload.idempotency_key).toBeTruthy();
    });

    it('sends only after review and keeps the frozen idempotency key after an uncertain response', async () => {
        mocks.post.mockImplementationOnce(
            (
                _url: string,
                _payload: unknown,
                options: { onFinish?: () => void },
            ) => {
                options.onFinish?.();
            },
        );
        renderPanel();
        await openRequestComposer();
        await fireEvent.click(
            screen.getByRole('checkbox', {
                name: 'Include in document request',
            }),
        );
        await fireEvent.input(
            screen.getByLabelText('Applicant instruction for this request'),
            { target: { value: 'Please bring the transcript.' } },
        );
        await fireEvent.click(
            screen.getByRole('button', { name: 'Review 1 selected item' }),
        );
        expect(mocks.post).not.toHaveBeenCalled();
        expect(screen.getByRole('dialog').textContent).toContain(
            'Please bring the transcript.',
        );
        await fireEvent.click(
            screen.getByRole('button', { name: 'Send document request' }),
        );
        expect(mocks.post).toHaveBeenCalledTimes(1);
        const firstPayload = mocks.post.mock.calls[0][1];
        expect(firstPayload).toMatchObject({
            expected_version: 5,
            requirement_set_id: 8,
            items: [
                {
                    requirement_item_id: 13,
                    expected_latest_verification_id: 21,
                    applicant_instruction: 'Please bring the transcript.',
                },
            ],
        });
        expect(firstPayload).not.toHaveProperty('internal_remarks');

        await fireEvent.click(screen.getByRole('button', { name: 'Cancel' }));
        await fireEvent.click(
            screen.getByRole('button', {
                name: 'Review exact retry of original request',
            }),
        );
        await fireEvent.click(
            screen.getByRole('button', { name: 'Send document request' }),
        );
        expect(mocks.post).toHaveBeenCalledTimes(2);
        expect(mocks.post.mock.calls[1][1].idempotency_key).toBe(
            firstPayload.idempotency_key,
        );
    });

    it('renders applicant mode read-only and never exposes private remarks or staff controls', () => {
        const applicantChecklist = {
            set: { applicant_track: 'freshman', catalog_version: 2 },
            status: 'current',
            items: [
                {
                    ...checklist.items[0],
                    applicant_instruction: 'Please present the transcript.',
                    internal_remarks: 'Private Registrar note',
                },
            ],
        };
        renderPanel({
            checklist: applicantChecklist,
            readOnly: true,
            flags: {},
        });

        expect(screen.getByText('Please present the transcript.')).toBeTruthy();
        expect(screen.queryByText('Private Registrar note')).toBeNull();
        expect(
            screen.queryByRole('button', { name: 'Mark complete' }),
        ).toBeNull();
        expect(
            screen.queryByRole('button', {
                name: 'More actions for Transcript of Records',
            }),
        ).toBeNull();
        expect(screen.queryByLabelText('Include in document request')).toBeNull();
    });

    it('shows loading and locked clearance states without mutation controls', () => {
        const loading = renderPanel({ loading: true });
        expect(loading.getByRole('status').textContent).toContain(
            'Loading document checklist',
        );
        expect(loading.queryByText('Transcript of Records')).toBeNull();
        loading.unmount();

        const locked = renderPanel({ isClearanceConfirmed: true });
        expect(
            locked.getByText(/locked while admission clearance is confirmed/),
        ).toBeTruthy();
        expect(
            locked.queryByRole('button', { name: 'Mark complete' }),
        ).toBeNull();
        expect(
            locked.queryByRole('button', {
                name: 'Request document correction',
            }),
        ).toBeNull();
    });

    it.each(['document_request', 'checklist', 'verification'])(
        'renders backend %s errors without losing their text',
        async (errorKey) => {
            const message = `Exact ${errorKey} adapter failure`;
            mocks.post.mockImplementationOnce(
                (
                    _url: string,
                    _payload: unknown,
                    options: {
                        onError?: (errors: Record<string, string>) => void;
                        onFinish?: () => void;
                    },
                ) => {
                    options.onError?.({ [errorKey]: message });
                    options.onFinish?.();
                },
            );
            renderPanel();
            await openRequestComposer();
            await fireEvent.click(
                screen.getByRole('checkbox', {
                    name: 'Include in document request',
                }),
            );
            await fireEvent.click(
                screen.getByRole('button', { name: 'Review 1 selected item' }),
            );
            await fireEvent.click(
                screen.getByRole('button', { name: 'Send document request' }),
            );

            expect(
                screen
                    .getAllByRole('alert')
                    .some((alert) => alert.textContent?.includes(message)),
            ).toBe(true);
            expect(
                screen.getAllByRole('button', { name: 'Refresh checklist' })
                    .length,
            ).toBeGreaterThan(0);
        },
    );

    it('preserves dirty text across evidence refresh, refreshes clean drafts, and requires explicit re-review', async () => {
        const twoItems = {
            ...checklist,
            items: [
                checklist.items[0],
                {
                    ...checklist.items[0],
                    id: 14,
                    code: 'FORM138',
                    label: 'Form 138',
                    latest_verification_id: 31,
                    applicant_instruction: 'Old clean baseline',
                },
            ],
        };
        const panel = renderPanel({ checklist: twoItems });
        await chooseItemAction('Transcript of Records', 'Flag as deficient');
        const dirtyInput = screen.getByLabelText(/Applicant instruction/);
        await fireEvent.input(dirtyInput, {
            target: { value: 'Keep this private draft' },
        });

        const refreshed = {
            ...twoItems,
            items: [
                {
                    ...twoItems.items[0],
                    latest_verification_id: 22,
                    applicant_instruction: 'Server refreshed instruction',
                },
                {
                    ...twoItems.items[1],
                    latest_verification_id: 32,
                    applicant_instruction: 'Refreshed clean baseline',
                },
            ],
        };
        panel.rerender({
            attemptId: 44,
            checklist: refreshed,
            expectedVersion: 6,
            flags: {
                documents_view: true,
                documents_initialize: true,
                documents_verify: true,
                documents_request: true,
            },
            initializeUrl: '/registrar/44/initialize',
            verifyUrl: '/registrar/44/verify',
            requestUrl: '/admissions/44/document-correction-requests',
        });

        expect(
            (
                screen.getByLabelText(
                    /Applicant instruction/,
                ) as HTMLTextAreaElement
            ).value,
        ).toBe('Keep this private draft');
        expect(screen.getByText(/older checklist evidence/)).toBeTruthy();
        await fireEvent.click(
            screen.getByRole('button', { name: 'Save deficient' }),
        );
        expect(mocks.post).not.toHaveBeenCalled();

        await fireEvent.click(
            screen.getByRole('button', {
                name: 'Review current evidence and keep draft',
            }),
        );
        await fireEvent.click(
            screen.getByRole('button', { name: 'Save deficient' }),
        );
        expect(mocks.post).toHaveBeenCalledTimes(1);
        expect(mocks.post.mock.calls[0][1]).toMatchObject({
            expected_version: 6,
            expected_latest_verification_id: 22,
            applicant_instruction: 'Keep this private draft',
        });
    });

    it('refreshes a clean draft from the latest checklist projection', async () => {
        const item = {
            ...checklist.items[0],
            applicant_instruction: 'Old clean instruction',
        };
        const panel = renderPanel({
            checklist: { ...checklist, items: [item] },
        });
        await chooseItemAction('Transcript of Records', 'Flag as deficient');
        expect(
            (
                screen.getByLabelText(
                    /Applicant instruction/,
                ) as HTMLTextAreaElement
            ).value,
        ).toBe('Old clean instruction');

        panel.rerender({
            attemptId: 44,
            checklist: {
                ...checklist,
                items: [
                    {
                        ...item,
                        latest_verification_id: 22,
                        applicant_instruction: 'Current checklist instruction',
                    },
                ],
            },
            expectedVersion: 6,
            flags: {
                documents_view: true,
                documents_initialize: true,
                documents_verify: true,
                documents_request: true,
            },
            initializeUrl: '/registrar/44/initialize',
            verifyUrl: '/registrar/44/verify',
            requestUrl: '/admissions/44/document-correction-requests',
        });

        expect(
            (
                screen.getByLabelText(
                    /Applicant instruction/,
                ) as HTMLTextAreaElement
            ).value,
        ).toBe('Current checklist instruction');
        expect(screen.queryByText(/older checklist evidence/)).toBeNull();
    });

    it('resets retained drafts when the panel switches applicant identity', async () => {
        const panel = renderPanel();
        await chooseItemAction('Transcript of Records', 'Flag as deficient');
        await fireEvent.input(screen.getByLabelText(/Applicant instruction/), {
            target: { value: 'Previous applicant private text' },
        });
        await fireEvent.input(screen.getByLabelText(/Internal note/), {
            target: { value: 'Previous applicant private remark' },
        });

        panel.rerender({
            attemptId: 45,
            checklist: {
                ...checklist,
                items: [
                    {
                        ...checklist.items[0],
                        applicant_instruction: 'New applicant baseline',
                        internal_remarks: 'New applicant note',
                    },
                ],
            },
            expectedVersion: 5,
            flags: {
                documents_view: true,
                documents_initialize: true,
                documents_verify: true,
                documents_request: true,
            },
            initializeUrl: '/registrar/45/initialize',
            verifyUrl: '/registrar/45/verify',
            requestUrl: '/admissions/45/document-correction-requests',
        });

        await chooseItemAction('Transcript of Records', 'Flag as deficient');
        expect(
            (
                screen.getByLabelText(
                    /Applicant instruction/,
                ) as HTMLTextAreaElement
            ).value,
        ).toBe('New applicant baseline');
        expect(
            (screen.getByLabelText(/Internal note/) as HTMLTextAreaElement)
                .value,
        ).toBe('New applicant note');
        expect(
            screen.queryByText('Previous applicant private text'),
        ).toBeNull();
        expect(
            screen.queryByText('Previous applicant private remark'),
        ).toBeNull();
    });

    it('replays the exact original request after refresh following a lost response', async () => {
        mocks.post.mockImplementationOnce(
            (
                _url: string,
                _payload: unknown,
                options: {
                    onError?: (errors: Record<string, string>) => void;
                    onFinish?: () => void;
                },
            ) => {
                options.onError?.({
                    checklist: 'Refresh before retrying this request.',
                });
                options.onFinish?.();
            },
        );
        const panel = renderPanel();
        await openRequestComposer();
        await fireEvent.click(
            screen.getByRole('checkbox', {
                name: 'Include in document request',
            }),
        );
        await fireEvent.input(
            screen.getByLabelText('Applicant instruction for this request'),
            { target: { value: 'Bring the original transcript.' } },
        );
        await fireEvent.click(
            screen.getByRole('button', { name: 'Review 1 selected item' }),
        );
        await fireEvent.click(
            screen.getByRole('button', { name: 'Send document request' }),
        );
        const originalPayload = mocks.post.mock.calls[0][1];

        await fireEvent.click(
            screen.getAllByRole('button', { name: 'Refresh checklist' })[0],
        );
        panel.rerender({
            attemptId: 44,
            checklist: {
                ...checklist,
                items: [
                    {
                        ...checklist.items[0],
                        latest_verification_id: 99,
                        latest_status: 'deficient',
                    },
                ],
            },
            expectedVersion: 6,
            flags: {
                documents_view: true,
                documents_initialize: true,
                documents_verify: true,
                documents_request: true,
            },
            initializeUrl: '/registrar/44/initialize',
            verifyUrl: '/registrar/44/verify',
            requestUrl: '/admissions/44/document-correction-requests',
        });
        await fireEvent.click(
            screen.getByRole('button', { name: 'Send document request' }),
        );

        expect(mocks.post).toHaveBeenCalledTimes(2);
        expect(mocks.post.mock.calls[1][1]).toEqual(originalPayload);
        expect(mocks.post.mock.calls[1][1].idempotency_key).toBe(
            originalPayload.idempotency_key,
        );
    });

    it('keeps a dispatcher-error request ambiguous and retries the exact frozen payload after refresh', async () => {
        mocks.post.mockImplementationOnce(
            (
                _url: string,
                _payload: unknown,
                options: {
                    onError?: (errors: Record<string, string>) => void;
                    onFinish?: () => void;
                },
            ) => {
                options.onError?.({
                    document_request: 'The notification could not be queued.',
                });
                options.onFinish?.();
            },
        );
        const panel = renderPanel();
        await openRequestComposer();
        await fireEvent.click(
            screen.getByRole('checkbox', {
                name: 'Include in document request',
            }),
        );
        await fireEvent.input(
            screen.getByLabelText('Applicant instruction for this request'),
            { target: { value: 'Bring the original transcript.' } },
        );
        await fireEvent.click(
            screen.getByRole('button', { name: 'Review 1 selected item' }),
        );
        await fireEvent.click(
            screen.getByRole('button', { name: 'Send document request' }),
        );
        const originalPayload = mocks.post.mock.calls[0][1];

        expect(
            screen.getByRole('button', {
                name: 'Review exact retry of original request',
            }),
        ).toBeTruthy();
        await fireEvent.click(
            screen.getAllByRole('button', { name: 'Refresh checklist' })[0],
        );
        panel.rerender({
            attemptId: 44,
            checklist: {
                ...checklist,
                items: [
                    {
                        ...checklist.items[0],
                        latest_verification_id: 99,
                        latest_status: 'deficient',
                    },
                ],
            },
            expectedVersion: 6,
            flags: {
                documents_view: true,
                documents_initialize: true,
                documents_verify: true,
                documents_request: true,
            },
            initializeUrl: '/registrar/44/initialize',
            verifyUrl: '/registrar/44/verify',
            requestUrl: '/admissions/44/document-correction-requests',
        });
        await fireEvent.click(
            screen.getByRole('button', { name: 'Send document request' }),
        );

        expect(mocks.post).toHaveBeenCalledTimes(2);
        expect(mocks.post.mock.calls[1][1]).toEqual(originalPayload);
        expect(mocks.post.mock.calls[1][1].idempotency_key).toBe(
            originalPayload.idempotency_key,
        );
    });

    it('shows a recorded receipt separately from a failed delivery handoff', async () => {
        mocks.page.props.flash.document_correction_request_recorded = {
            id: 91,
        };
        mocks.page.props.flash.document_correction_request_delivery = 'failed';
        mocks.post.mockImplementationOnce(
            (
                _url: string,
                _payload: unknown,
                options: { onSuccess?: () => void; onFinish?: () => void },
            ) => {
                options.onSuccess?.();
                options.onFinish?.();
            },
        );
        renderPanel();
        await openRequestComposer();
        await fireEvent.click(
            screen.getByRole('checkbox', {
                name: 'Include in document request',
            }),
        );
        await fireEvent.click(
            screen.getByRole('button', { name: 'Review 1 selected item' }),
        );
        await fireEvent.click(
            screen.getByRole('button', { name: 'Send document request' }),
        );

        expect(screen.getByRole('status').textContent).toContain(
            'Request recorded, but notification delivery could not be queued.',
        );
        expect(
            screen.queryByRole('button', {
                name: 'Review exact retry of original request',
            }),
        ).toBeNull();
    });

    it('releases a definitively stale request after refresh and allows a newly reviewed send', async () => {
        mocks.post.mockImplementationOnce(
            (
                _url: string,
                _payload: unknown,
                options: {
                    onError?: (errors: Record<string, string>) => void;
                    onFinish?: () => void;
                },
            ) => {
                mocks.page.props.flash.document_correction_request_outcome =
                    'definite_rejection';
                options.onError?.({
                    document_request:
                        'Stale admission attempt version; refresh and review the document request.',
                });
                options.onFinish?.();
            },
        );
        const panel = renderPanel();
        await openRequestComposer();
        await fireEvent.click(
            screen.getByRole('checkbox', {
                name: 'Include in document request',
            }),
        );
        await fireEvent.input(
            screen.getByLabelText('Applicant instruction for this request'),
            { target: { value: 'Bring the current transcript.' } },
        );
        await fireEvent.click(
            screen.getByRole('button', { name: 'Review 1 selected item' }),
        );
        await fireEvent.click(
            screen.getByRole('button', { name: 'Send document request' }),
        );
        const rejectedPayload = mocks.post.mock.calls[0][1];

        await fireEvent.click(
            screen.getAllByRole('button', { name: 'Refresh checklist' })[0],
        );
        panel.rerender({
            attemptId: 44,
            checklist,
            expectedVersion: 6,
            flags: {
                documents_view: true,
                documents_initialize: true,
                documents_verify: true,
                documents_request: true,
            },
            initializeUrl:
                '/registrar/enrollment-eligibility/44/requirements/initialize',
            verifyUrl: '/registrar/44/verify',
            requestUrl: '/admissions/44/document-correction-requests',
        });
        await fireEvent.click(
            screen.getByRole('button', {
                name: 'Review current evidence and keep draft',
            }),
        );
        await fireEvent.click(
            screen.getByRole('button', { name: 'Review 1 selected item' }),
        );
        await fireEvent.click(
            screen.getByRole('button', { name: 'Send document request' }),
        );

        expect(mocks.post).toHaveBeenCalledTimes(2);
        expect(mocks.post.mock.calls[1][1]).toMatchObject({
            expected_version: 6,
            items: [
                {
                    requirement_item_id: 13,
                    applicant_instruction: 'Bring the current transcript.',
                },
            ],
        });
        expect(mocks.post.mock.calls[1][1].idempotency_key).not.toBe(
            rejectedPayload.idempotency_key,
        );
    });

    it('releases a definitively rejected field payload while preserving editable request text', async () => {
        mocks.post.mockImplementationOnce(
            (
                _url: string,
                _payload: unknown,
                options: {
                    onError?: (errors: Record<string, string>) => void;
                    onFinish?: () => void;
                },
            ) => {
                options.onError?.({
                    'items.0.applicant_instruction':
                        'The instruction is invalid.',
                });
                options.onFinish?.();
            },
        );
        renderPanel();
        await openRequestComposer();
        await fireEvent.click(
            screen.getByRole('checkbox', {
                name: 'Include in document request',
            }),
        );
        await fireEvent.input(
            screen.getByLabelText('Applicant instruction for this request'),
            { target: { value: 'Original instruction' } },
        );
        await fireEvent.click(
            screen.getByRole('button', { name: 'Review 1 selected item' }),
        );
        await fireEvent.click(
            screen.getByRole('button', { name: 'Send document request' }),
        );

        const instruction = screen.getByLabelText(
            'Applicant instruction for this request',
        ) as HTMLTextAreaElement;
        expect(instruction.value).toBe('Original instruction');
        await fireEvent.input(instruction, {
            target: { value: 'Corrected instruction' },
        });
        await fireEvent.click(
            screen.getByRole('button', { name: 'Review 1 selected item' }),
        );
        await fireEvent.click(
            screen.getByRole('button', { name: 'Send document request' }),
        );

        expect(mocks.post).toHaveBeenCalledTimes(2);
        expect(mocks.post.mock.calls[1][1].items[0].applicant_instruction).toBe(
            'Corrected instruction',
        );
        expect(mocks.post.mock.calls[1][1].idempotency_key).not.toBe(
            mocks.post.mock.calls[0][1].idempotency_key,
        );
    });

    it('filters checklist items by All, Pending, and Complete and shows empty filter states', async () => {
        const mixedChecklist = {
            ...checklist,
            mandatory_pending_count: 1,
            items: [
                {
                    ...checklist.items[0],
                    id: 13,
                    label: 'Transcript of Records',
                    latest_status: 'missing',
                },
                {
                    ...checklist.items[0],
                    id: 14,
                    code: 'PSA',
                    label: 'PSA Birth Certificate',
                    latest_status: 'verified',
                },
            ],
        };

        renderPanel({ checklist: mixedChecklist });

        const filterGroup = screen.getByTestId('checklist-status-filter');
        expect(filterGroup.getAttribute('aria-label')).toBe(
            'Filter checklist items by status',
        );

        const allBtn = screen.getByRole('button', { name: 'All (2)' });
        const pendingBtn = screen.getByRole('button', { name: 'Pending (1)' });
        const completeBtn = screen.getByRole('button', { name: 'Complete (1)' });

        expect(allBtn.getAttribute('aria-pressed')).toBe('true');
        expect(screen.getByText('Transcript of Records')).toBeTruthy();
        expect(screen.getByText('PSA Birth Certificate')).toBeTruthy();

        await fireEvent.click(pendingBtn);
        expect(pendingBtn.getAttribute('aria-pressed')).toBe('true');
        expect(screen.getByText('Transcript of Records')).toBeTruthy();
        expect(screen.queryByText('PSA Birth Certificate')).toBeNull();

        await fireEvent.click(completeBtn);
        expect(completeBtn.getAttribute('aria-pressed')).toBe('true');
        expect(screen.queryByText('Transcript of Records')).toBeNull();
        expect(screen.getByText('PSA Birth Certificate')).toBeTruthy();

        await fireEvent.click(allBtn);
        expect(screen.getByText('Transcript of Records')).toBeTruthy();
        expect(screen.getByText('PSA Birth Certificate')).toBeTruthy();

        cleanup();
        renderPanel({ checklist });
        await fireEvent.click(
            screen.getByRole('button', { name: 'Complete (0)' }),
        );
        expect(
            screen.getByText('No completed checklist items yet.'),
        ).toBeTruthy();
        await fireEvent.click(
            screen.getByRole('button', { name: 'Show all items' }),
        );
        expect(screen.getByText('Transcript of Records')).toBeTruthy();
    });

    it('renders non-blocking correction requested and unsent badges and allows re-selecting already requested items', async () => {
        const twoEligibleChecklist = {
            ...checklist,
            mandatory_pending_count: 2,
            items: [
                {
                    ...checklist.items[0],
                    id: 13,
                    code: 'TOR',
                    label: 'Transcript of Records',
                    latest_status: 'deficient',
                    latest_verification_id: 21,
                    applicant_instruction: 'Bring sealed copy.',
                },
                {
                    ...checklist.items[0],
                    id: 14,
                    code: 'GMC',
                    label: 'Good Moral Certificate',
                    latest_status: 'missing',
                    latest_verification_id: 22,
                    applicant_instruction: null,
                },
            ],
        };

        renderPanel({
            checklist: twoEligibleChecklist,
            requestHistory: {
                requests: [
                    {
                        id: 501,
                        status: 'current',
                        recorded_at: '2026-10-10 09:30',
                        overall_note: 'Please submit before Friday.',
                        items: [
                            {
                                requirement_item_id: 13,
                                label: 'Transcript of Records',
                                status: 'current',
                                observed_status: 'deficient',
                                applicant_instruction: 'Bring sealed copy.',
                            },
                        ],
                    },
                ],
            },
        });

        expect(screen.getByText('1 unsent')).toBeTruthy();
        expect(screen.getByText('1 correction requested')).toBeTruthy();
        expect(screen.getByText('Correction requested')).toBeTruthy();
        expect(
            screen.getByText(/Correction request sent/i),
        ).toBeTruthy();

        await openRequestComposer();
        expect(screen.getByText('Already requested')).toBeTruthy();
        expect(screen.getByText('Not yet requested')).toBeTruthy();
        expect(
            screen.getByText(
                'Included at the top of the correction request notice.',
            ),
        ).toBeTruthy();

        const checkboxes = screen.getAllByRole('checkbox', {
            name: 'Include in document request',
        });
        expect(checkboxes).toHaveLength(2);
        await fireEvent.click(checkboxes[0]);
        await fireEvent.click(
            screen.getByRole('button', { name: 'Review 1 selected item' }),
        );
        await fireEvent.click(
            screen.getByRole('button', { name: 'Send document request' }),
        );
        expect(mocks.post).toHaveBeenCalledTimes(1);
        expect(mocks.post.mock.calls[0][1].items).toEqual([
            {
                requirement_item_id: 13,
                expected_latest_verification_id: 21,
                applicant_instruction: 'Bring sealed copy.',
            },
        ]);
    });

    it('shows required and optional indicators and updated staff-only helper copy in item editors', async () => {
        renderPanel();
        await chooseItemAction('Transcript of Records', 'Flag as deficient');

        expect(screen.getByText('(required)')).toBeTruthy();
        expect(screen.getByText('(optional)')).toBeTruthy();
        expect(
            screen.getByText(
                'Shown to the applicant in their portal and correction notice.',
            ),
        ).toBeTruthy();
        expect(
            screen.getByText(
                'Staff reference only; not visible to the applicant.',
            ),
        ).toBeTruthy();
        expect(
            screen.queryByText('Registrar only; never sent to the applicant'),
        ).toBeNull();
    });

    it('supports selecting multiple cards, displays BulkActionBar, and executes bulk verify', async () => {
        const multiItemChecklist = {
            ...checklist,
            items: [
                {
                    id: 13,
                    code: 'TOR',
                    label: 'Transcript of Records',
                    item_kind: 'document',
                    is_mandatory: true,
                    latest_status: 'missing',
                    latest_verification_id: 21,
                    applicant_instruction: null,
                    internal_remarks: null,
                },
                {
                    id: 14,
                    code: 'PSA',
                    label: 'Birth Certificate',
                    item_kind: 'document',
                    is_mandatory: true,
                    latest_status: 'deficient',
                    latest_verification_id: 22,
                    applicant_instruction: 'Original copy needed',
                    internal_remarks: null,
                },
            ],
        };

        renderPanel({ checklist: multiItemChecklist });

        // Check individual selection
        const torCheckbox = screen.getByLabelText('Select Transcript of Records');
        const psaCheckbox = screen.getByLabelText('Select Birth Certificate');
        expect(torCheckbox).toBeTruthy();
        expect(psaCheckbox).toBeTruthy();

        // BulkActionBar is hidden when count is 0
        expect(screen.queryByText('selected')).toBeNull();

        await fireEvent.click(torCheckbox);
        expect(screen.getByText('1')).toBeTruthy();
        expect(screen.getByText('selected')).toBeTruthy();

        await fireEvent.click(psaCheckbox);
        expect(screen.getByText('2')).toBeTruthy();

        // Both are unverified, so Accept / Verify (2) should be available
        const verifyBtn = screen.getByRole('button', { name: /Accept \/ Verify \(2\)/i });
        expect(verifyBtn).toBeTruthy();

        // Click verify to open confirmation dialog
        await fireEvent.click(verifyBtn);
        expect(screen.getByRole('dialog')).toBeTruthy();
        expect(screen.getByText('Verify document requirements')).toBeTruthy();

        // Confirm verification
        const confirmBtn = screen.getByRole('button', { name: /Confirm Verify \(2\)/i });
        await fireEvent.click(confirmBtn);

        expect(mocks.post).toHaveBeenCalledWith(
            '/registrar/44/bulk-verify',
            expect.objectContaining({
                expected_version: 5,
                requirement_set_id: 8,
                items: expect.arrayContaining([
                    expect.objectContaining({ requirement_item_id: 13, expected_latest_verification_id: 21 }),
                    expect.objectContaining({ requirement_item_id: 14, expected_latest_verification_id: 22 }),
                ]),
            }),
            expect.any(Object),
        );
    });

    it('supports Select all and opens Request Reissue composer pre-populated with selected items', async () => {
        const multiItemChecklist = {
            ...checklist,
            items: [
                {
                    id: 13,
                    code: 'TOR',
                    label: 'Transcript of Records',
                    item_kind: 'document',
                    is_mandatory: true,
                    latest_status: 'missing',
                    latest_verification_id: 21,
                    applicant_instruction: null,
                    internal_remarks: null,
                },
                {
                    id: 14,
                    code: 'PSA',
                    label: 'Birth Certificate',
                    item_kind: 'document',
                    is_mandatory: true,
                    latest_status: 'deficient',
                    latest_verification_id: 22,
                    applicant_instruction: 'Original copy needed',
                    internal_remarks: null,
                },
            ],
        };

        renderPanel({ checklist: multiItemChecklist });

        // Use Select all toggle
        const selectAll = screen.getByLabelText('Select all displayed requirements');
        await fireEvent.click(selectAll);

        expect(screen.getByText('2')).toBeTruthy();

        // Click Request Reissue button
        const reissueBtn = screen.getByRole('button', { name: /Request Reissue \(2\)/i });
        expect(reissueBtn).toBeTruthy();
        await fireEvent.click(reissueBtn);

        // The document correction composer dialog should now be open
        expect(screen.getByRole('dialog')).toBeTruthy();
        expect(screen.getByRole('heading', { name: 'Request document correction' })).toBeTruthy();
        expect(screen.getByText('Review 2 selected items')).toBeTruthy();
    });

    it('renders a 2-column grid when columns={2} is configured', () => {
        const { container } = renderPanel({ columns: 2 });
        const list = container.querySelector('ul.grid');
        expect(list).toBeTruthy();
        expect(list?.classList.contains('md:grid-cols-2')).toBe(true);
    });

    it('defaults to a single column vertical stack when columns is not specified', () => {
        const { container } = renderPanel();
        const list = container.querySelector('ul.space-y-2\\.5');
        expect(list).toBeTruthy();
    });
});
