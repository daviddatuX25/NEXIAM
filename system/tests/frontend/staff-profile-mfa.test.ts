// @vitest-environment jsdom
import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/svelte';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('@inertiajs/svelte', async (importOriginal) => {
    const actual = await importOriginal<Record<string, unknown>>();

    return {
        ...actual,
        router: { reload: vi.fn() },
        useForm(initial: Record<string, unknown>) {
            return {
                ...initial,
                errors: {},
                processing: false,
                recentlySuccessful: false,
                post: vi.fn(),
                put: vi.fn(),
                delete: vi.fn(),
                reset: vi.fn(),
                clearErrors: vi.fn(),
            };
        },
    };
});

vi.mock('@/Pages/Staff/StaffLayout.svelte', () => ({
    default: (_anchor: unknown, { children }: { children?: (anchor: unknown) => void }) => children?.(_anchor),
}));

import ProfileShow from '../../resources/js/Pages/Staff/Profile/Show.svelte';

const security = {
    mfa_status: 'exempt',
    mfa_required: false,
    two_factor_confirmed: false,
    mfa_enrolled: false,
    recovery_codes_remaining: 0,
    google_linked: false,
};

function jsonResponse(body: unknown) {
    return Promise.resolve({
        ok: true,
        json: () => Promise.resolve(body),
    });
}

afterEach(() => {
    cleanup();
    vi.unstubAllGlobals();
});
beforeEach(() => {
    vi.stubGlobal('ResizeObserver', class {
        observe() {}
        unobserve() {}
        disconnect() {}
    });
});


describe('staff profile MFA enrollment', () => {
    it('posts explicit JSON enrollment payloads, renders the QR, and displays returned recovery codes', async () => {
        const codes = Array.from({ length: 8 }, (_, index) => `recovery-code-${index + 1}`);
        const fetchMock = vi.fn()
            .mockImplementationOnce(() => jsonResponse({
                secret: 'JBSWY3DPEHPK3PXP',
                qr_code: 'data:image/svg+xml;base64,PHN2Zy8+',
                expires_at: new Date(Date.now() + 600_000).toISOString(),
            }))
            .mockImplementationOnce(() => jsonResponse({ recovery_codes: codes }));
        vi.stubGlobal('fetch', fetchMock);

        render(ProfileShow, {
            props: {
                page_state: 'ready',
                user: {
                    name: 'Sample Staff',
                    email: 'staff@example.test',
                    office: 'admin',
                    status: 'active',
                    avatar: { type: 'initials', preset: null, url: null },
                    roles: [],
                },
                directory: { phone_extension: '', room_number: '', consultation_hours: '' },
                security,
            },
        });

        expect(screen.getByText('Two-Factor Optional for Your Role')).toBeTruthy();
        await fireEvent.click(screen.getByRole('button', { name: 'Set Up Two-Factor Authentication' }));
        await fireEvent.input(screen.getByLabelText('Current password'), { target: { value: 'correct-horse' } });
        await fireEvent.click(screen.getByRole('button', { name: 'Continue' }));

        await waitFor(() => expect(screen.getByAltText('QR code for your new authenticator setup')).toBeTruthy());
        expect(screen.getByTestId('mfa-secret').textContent).toBe('JBSWY3DPEHPK3PXP');
        expect(screen.getByRole('timer').textContent).toContain('Enrollment expires in');
        expect(fetchMock).toHaveBeenNthCalledWith(1, '/staff/profile/mfa/enroll/begin', expect.objectContaining({
            method: 'POST',
            body: JSON.stringify({ password: 'correct-horse' }),
        }));

        const codeInput = screen.getByLabelText('Six-digit authenticator code');
        await fireEvent.input(codeInput, { target: { value: '123456' } });
        await fireEvent.submit(codeInput.closest('form')!);

        await waitFor(() => expect(screen.getByRole('list', { name: 'New recovery codes' })).toBeTruthy());
        expect(fetchMock).toHaveBeenNthCalledWith(2, '/staff/profile/mfa/enroll/confirm', expect.objectContaining({
            method: 'POST',
            body: JSON.stringify({ code: '123456' }),
        }));
        for (const code of codes) {
            expect(screen.getByText(code)).toBeTruthy();
        }
        expect(screen.getByRole('button', { name: 'Copy Codes' })).toBeTruthy();
        expect(screen.getByRole('button', { name: 'Download Codes' })).toBeTruthy();
    });

    it('supports copying the setup key and strips non-digits when confirming the six-digit code', async () => {
        const codes = ['1111-2222', '3333-4444', '5555-6666', '7777-8888', '9999-0000', 'aaaa-bbbb', 'cccc-dddd', 'eeee-ffff'];
        let writeTextArg = '';
        Object.assign(navigator, {
            clipboard: {
                writeText: vi.fn(async (text: string) => {
                    writeTextArg = text;
                }),
            },
        });

        const fetchMock = vi.fn((url: string) => {
            if (url === '/staff/profile/mfa/enroll/begin') {
                return jsonResponse({
                    status: 'ok',
                    secret: 'LO5IXYYKWVGKPMNDCG5CSALX27VQPJ57',
                    qr_code: 'data:image/svg+xml;base64,fake-qr',
                    expires_at: new Date(Date.now() + 600_000).toISOString(),
                });
            }

            if (url === '/staff/profile/mfa/enroll/confirm') {
                return jsonResponse({
                    status: 'ok',
                    recovery_codes: codes,
                });
            }

            return Promise.reject(new Error(`Unexpected url: ${url}`));
        });
        vi.stubGlobal('fetch', fetchMock);

        render(ProfileShow, {
            props: {
                page_state: 'ready',
                user: {
                    name: 'Sample Staff',
                    email: 'staff@example.test',
                    office: 'admin',
                    status: 'active',
                    avatar: { type: 'initials', preset: null, url: null },
                    roles: [],
                },
                directory: { phone_extension: '', room_number: '', consultation_hours: '' },
                security,
            },
        });

        await fireEvent.click(screen.getByRole('button', { name: 'Set Up Two-Factor Authentication' }));
        await fireEvent.input(screen.getByLabelText('Current password'), { target: { value: 'correct-horse' } });
        await fireEvent.click(screen.getByRole('button', { name: 'Continue' }));

        await waitFor(() => expect(screen.getByTestId('mfa-secret').textContent).toBe('LO5IXYYKWVGKPMNDCG5CSALX27VQPJ57'));

        const copyKeyBtn = screen.getByRole('button', { name: /Copy setup key/i });
        await fireEvent.click(copyKeyBtn);
        expect(navigator.clipboard.writeText).toHaveBeenCalledWith('LO5IXYYKWVGKPMNDCG5CSALX27VQPJ57');
        expect(await screen.findByText('Copied key')).toBeTruthy();

        const codeInput = screen.getByLabelText('Six-digit authenticator code');
        await fireEvent.input(codeInput, { target: { value: '654321' } });
        await fireEvent.submit(codeInput.closest('form')!);

        await waitFor(() => expect(screen.getByRole('list', { name: 'New recovery codes' })).toBeTruthy());
        expect(fetchMock).toHaveBeenNthCalledWith(2, '/staff/profile/mfa/enroll/confirm', expect.objectContaining({
            method: 'POST',
            body: JSON.stringify({ code: '654321' }),
        }));
    });
});
