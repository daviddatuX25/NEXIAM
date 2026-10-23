// @vitest-environment jsdom

import { cleanup, fireEvent, render, screen } from '@testing-library/svelte';
import { afterEach, describe, expect, it, vi } from 'vitest';

const mocks = vi.hoisted(() => ({ post: vi.fn() }));

vi.mock('@inertiajs/svelte', async (importOriginal) => {
    const actual = await importOriginal<Record<string, unknown>>();
    return {
        ...actual,
        router: { ...(actual.router as object), post: mocks.post },
        useForm: (initial: Record<string, unknown>) => ({
            ...initial,
            errors: {},
            processing: false,
            post: vi.fn(),
        }),
    };
});
vi.mock('@/Layouts/GuestLayout.svelte', () => ({
    default: (
        _anchor: unknown,
        { children }: { children?: (anchor: unknown) => void },
    ) => children?.(_anchor),
}));

import AccountSetup from '../../resources/js/Pages/Staff/AccountSetup.svelte';

afterEach(() => {
    cleanup();
    vi.clearAllMocks();
});

describe('staff recipient account setup', () => {
    it('acknowledges recovery-code handling and submits portal completion with required MFA status rendered', async () => {
        render(AccountSetup, {
            props: {
                staff: { name: 'Mara Santos', email_verified: true },
                purpose: 'activation',
                phase: 'setup_ready',
                password_set: true,
                two_factor_confirmed: true,
                two_factor_required: true,
                mfa_verified: true,
                ready_to_complete: true,
            },
        });

        expect(
            screen.getByText(
                'Recovery codes were shown only once. If you did not save them, contact an HR administrator to reset your authenticator.',
            ),
        ).toBeTruthy();
        await fireEvent.click(screen.getByRole('button', { name: 'Continue' }));
        expect(screen.getByText('Password: set')).toBeTruthy();
        expect(screen.getByText('Authenticator: enrolled')).toBeTruthy();

        expect(
            screen.getByRole('heading', { name: 'Review your setup' }),
        ).toBeTruthy();
        await fireEvent.click(
            screen.getByRole('button', { name: 'Enter Staff Portal' }),
        );

        expect(mocks.post).toHaveBeenCalledWith(
            '/staff/account-setup/complete',
        );
    });
    it('renders optional authenticator status at completion and enters the staff portal', async () => {
        render(AccountSetup, {
            props: {
                staff: { name: 'Mara Santos', email_verified: true },
                purpose: 'activation',
                phase: 'setup_ready',
                password_set: true,
                two_factor_confirmed: false,
                two_factor_required: false,
                mfa_verified: false,
                ready_to_complete: true,
            },
        });

        expect(
            screen.getByText('Authenticator: not configured (optional)'),
        ).toBeTruthy();
        await fireEvent.click(
            screen.getByRole('button', { name: 'Enter Staff Portal' }),
        );

        expect(mocks.post).toHaveBeenCalledWith(
            '/staff/account-setup/complete',
        );
    });
});
