// @vitest-environment jsdom

import {
    cleanup,
    fireEvent,
    render,
    screen,
    waitFor,
} from '@testing-library/svelte';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';

vi.mock('@inertiajs/svelte', async (importOriginal) => {
    const actual = await importOriginal<Record<string, unknown>>();
    return {
        ...actual,
        usePage: () => ({ props: { errors: {}, flash: {} } }),
    };
});
vi.mock('@/Layouts/GuestLayout.svelte', () => ({
    default: (
        anchor: unknown,
        { children }: { children?: (anchor: unknown) => void },
    ) => children?.(anchor),
}));

import Login from '../../resources/js/Pages/Auth/Login.svelte';

beforeEach(() => {
    localStorage.clear();
    window.history.replaceState(null, '', '/login');
});
afterEach(cleanup);

it('restores and lets staff forget a remembered account without breaking the QR login surface', async () => {
    localStorage.setItem(
        'nexiam.staff_account_email',
        'registrar@example.test',
    );
    render(Login);
    await waitFor(() =>
        expect(screen.getByLabelText('Email')).toHaveProperty(
            'value',
            'registrar@example.test',
        ),
    );
    expect(screen.getByRole('button', { name: 'Scan QR code' })).toBeTruthy();
    await fireEvent.click(
        screen.getByRole('button', { name: 'Forget account' }),
    );
    await waitFor(() =>
        expect(
            screen.queryByRole('button', { name: 'Forget account' }),
        ).toBeNull(),
    );
    expect(localStorage.getItem('nexiam.staff_account_email')).toBeNull();
});

it('opens scanning in a modal and preserves login details when cancelled', async () => {
    render(Login);
    await fireEvent.input(screen.getByLabelText('Email'), {
        target: { value: 'staff@example.test' },
    });
    await fireEvent.click(screen.getByRole('button', { name: 'Scan QR code' }));
    expect(await screen.findByRole('dialog', { name: 'Scan QR code' })).toBeTruthy();
    await fireEvent.click(
        await screen.findByRole('button', { name: 'Cancel scanning' }),
    );
    await waitFor(() =>
        expect(
            screen.queryByRole('dialog', { name: 'Scan QR code' }),
        ).toBeNull(),
    );
    expect(screen.getByLabelText('Email')).toHaveProperty('value', 'staff@example.test');
});
it('offers manual activation reference and fallback entry alongside general QR scan', async () => {
    render(Login);
    await fireEvent.click(
        screen.getByRole('button', { name: 'Enter voucher manually' }),
    );
    expect(screen.getByLabelText('Voucher reference')).toBeTruthy();
    expect(screen.getByLabelText('Fallback code')).toBeTruthy();
    expect(screen.getByRole('button', { name: 'Scan QR code' })).toBeTruthy();
});

it('prefills an account shortcut, focuses the password and can clear the hint', async () => {
    window.history.replaceState(
        null,
        '',
        '/login?account=staff%40example.test',
    );
    render(Login);
    await waitFor(() =>
        expect(screen.getByLabelText('Email')).toHaveProperty(
            'value',
            'staff@example.test',
        ),
    );
    expect(window.location.search).toBe('');
    expect(document.activeElement).toBe(screen.getByLabelText('Password'));
    await fireEvent.click(
        screen.getByRole('button', { name: 'Forget account' }),
    );
    expect(screen.getByLabelText('Email')).toHaveProperty('value', '');
    expect(screen.getByRole('button', { name: 'Sign in' })).toBeTruthy();
});
