// @vitest-environment jsdom

import {
    cleanup,
    fireEvent,
    render,
    screen,
    waitFor,
} from '@testing-library/svelte';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const mocks = vi.hoisted(() => ({ post: vi.fn() }));

vi.mock('@inertiajs/svelte', async (importOriginal) => {
    const actual = await importOriginal<Record<string, unknown>>();
    return {
        ...actual,
        Link: (
            _anchor: unknown,
            { children }: { children?: (anchor: unknown) => void },
        ) => children?.(_anchor),
        router: { ...(actual.router as object), post: mocks.post },
    };
});
vi.mock('@/Pages/Staff/StaffLayout.svelte', () => ({
    default: (
        _anchor: unknown,
        { children }: { children?: (anchor: unknown) => void },
    ) => children?.(_anchor),
}));
vi.mock(
    '../../resources/js/Pages/Staff/Personnel/BatchVouchers.svelte',
    () => ({ default: () => null }),
);

import Prepare from '../../resources/js/Pages/Staff/Personnel/Prepare.svelte';
import Edit from '../../resources/js/Pages/Staff/Personnel/Edit.svelte';

afterEach(() => {
    cleanup();
    vi.unstubAllGlobals();
    vi.clearAllMocks();
});

beforeEach(() => {
    mocks.post.mockImplementation(
        (_url: string, _data: unknown, options: { onFinish?: () => void }) =>
            options.onFinish?.(),
    );
});

const readiness = {
    rows: [
        {
            key: 'staff:24',
            role: 'registrar_clerk',
            state: 'awaiting_setup',
            label: 'Registrar Clerk',
            office: 'registrar',
            scope_code: null,
            program_codes: [],
            department_id: null,
            user_id: 24,
            name: 'Reset Target',
            email: null,
            expected_generation: '3:factor-generation',
            permitted_actions: ['reset', 'issue_voucher'],
        },
    ],
    summary: {
        covered: 0,
        ready: 0,
        missing: 0,
        awaiting_setup: 1,
        setup_in_progress: 0,
        conflict: 0,
        inactive_scope: 0,
        protected: 0,
    },
};

function renderPrepare(overrides: Record<string, unknown> = {}) {
    return render(Prepare, {
        props: {
            readiness,
            can_create: true,
            roles: [
                {
                    name: 'registrar_support_staff',
                    display_name: 'Registrar Support Staff',
                },
            ],
            default_office: 'registrar',
            can_full_hr: true,
            demo_mode_available: false,
            eligible_existing_heads: [],
            ...overrides,
        },
    });
}

describe('staff personnel edit', () => {
    it('renders and clicks recovery and MFA controls without a runtime reference error', async () => {
        render(Edit, {
            props: {
                user: {
                    id: 24,
                    name: 'Reset Target',
                    email: null,
                    email_pending: true,
                    roles: [],
                    office: 'registrar',
                    status: 'active',
                    program_codes: [],
                    mfa_status: 'exempt',
                    can_require_mfa: true,
                    can_disable_mfa: false,
                },
                can_reset_password: true,
            },
        });

        expect(
            screen.getByRole('button', {
                name: 'Issue password recovery voucher',
            }),
        ).toBeTruthy();
        const requireMfa = screen.getByRole('button', {
            name: 'Require Two-Factor Login',
        });
        await fireEvent.click(requireMfa);
        expect(mocks.post).toHaveBeenCalledWith(
            '/staff/personnel/24/require-mfa',
            {},
            expect.objectContaining({
                preserveScroll: true,
                onFinish: expect.any(Function),
            }),
        );
    });
});

describe('staff account preparation', () => {
    it('creates identified rows from an explicit count, reviews them, and submits independent operation ids', async () => {
        const fetchMock = vi.fn().mockResolvedValue({
            ok: true,
            json: async () => ({
                review_token: 'signed-review',
                expires_at: '2026-10-21T12:00:00Z',
            }),
        });
        vi.stubGlobal('fetch', fetchMock);
        renderPrepare();

        await fireEvent.click(
            screen.getByRole('checkbox', {
                name: /Prepare several identified recipients/,
            }),
        );
        await fireEvent.input(screen.getByLabelText('Recipient count'), {
            target: { value: '2' },
        });
        await fireEvent.click(
            screen.getByRole('button', {
                name: 'Add identified recipient rows',
            }),
        );
        const names = screen.getAllByLabelText(/Recipient \d+ name/);
        expect(names).toHaveLength(2);
        await fireEvent.input(names[0], {
            target: { value: 'First identified person' },
        });
        await fireEvent.input(names[1], {
            target: { value: 'Second identified person' },
        });
        await fireEvent.click(
            screen.getByRole('button', { name: 'Review recipients' }),
        );
        await waitFor(() => expect(fetchMock).toHaveBeenCalledOnce());
        const reviewPayload = JSON.parse(
            fetchMock.mock.calls[0][1].body as string,
        );
        expect(reviewPayload.recipients).toHaveLength(2);
        expect(
            reviewPayload.recipients.every(
                (recipient: { operation_id: string }) => recipient.operation_id,
            ),
        ).toBe(true);

        await fireEvent.click(
            screen.getByRole('button', { name: 'Commit reviewed batch' }),
        );
        expect(mocks.post).toHaveBeenCalledOnce();
        expect(mocks.post.mock.calls[0][0]).toBe(
            '/staff/personnel/prepare/commit',
        );
        expect(mocks.post.mock.calls[0][1].recipients).toHaveLength(2);
        expect(mocks.post.mock.calls[0][1].review_token).toBe('signed-review');
    });

    it('submits selected full-reset targets with fresh proof and their displayed generations', async () => {
        renderPrepare();
        await fireEvent.click(
            screen.getByRole('checkbox', {
                name: 'Select Reset Target for staff access operations',
            }),
        );
        await fireEvent.click(
            screen.getByRole('button', { name: 'Full reset selected' }),
        );
        await fireEvent.input(screen.getByLabelText('Reason'), {
            target: { value: 'Recipient requested a full setup restart.' },
        });
        await fireEvent.input(screen.getByLabelText('Your current password'), {
            target: { value: 'FreshPassword!42' },
        });
        await fireEvent.input(screen.getByLabelText('Authenticator code'), {
            target: { value: '123456' },
        });
        await fireEvent.click(
            screen.getByRole('button', { name: 'Confirm full reset' }),
        );

        expect(mocks.post).toHaveBeenCalledOnce();
        const [url, payload] = mocks.post.mock.calls[0];
        expect(url).toBe('/staff/personnel/full-reset/batch');
        expect(payload.targets).toEqual([24]);
        expect(payload.expected_generations['24']).toBe('3:factor-generation');
        expect(payload.operation_ids['24']).toMatch(/^[0-9a-f-]{36}$/i);
    });
});
