// @vitest-environment jsdom

import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/svelte';
import { afterEach, expect, it, vi } from 'vitest';
import OpeningAlertSubscribersDialog from '../../resources/js/Pages/Setup/Cycles/OpeningAlertSubscribersDialog.svelte';

afterEach(() => {
    cleanup();
    vi.unstubAllGlobals();
});

it('retries the requested next page rather than displaying the previous page after a network failure', async () => {
    let nextPageFailed = false;
    vi.stubGlobal('fetch', vi.fn(async (url: string) => {
        const requested = new URL(url, 'http://localhost').searchParams.get('page');
        if (requested === '2' && !nextPageFailed) {
            nextPageFailed = true;
            throw new TypeError('Network connection lost');
        }
        const second = requested === '2';
        return {
            ok: true,
            json: async () => ({
                current_page: second ? 2 : 1,
                last_page: 2,
                total: 16,
                data: [{
                    id: second ? 16 : 1,
                    email: second ? 'last-person@example.test' : 'first-person@example.test',
                    state: 'verified',
                    created_at: '2026-10-23T12:00:00Z',
                    verified_at: '2026-10-23T12:01:00Z',
                }],
            }),
        };
    }));
    render(OpeningAlertSubscribersDialog);
    await fireEvent.click(screen.getByRole('button', { name: 'View opening-alert signups' }));
    expect(await screen.findByText('first-person@example.test')).toBeTruthy();
    await fireEvent.click(screen.getByRole('button', { name: 'Next' }));
    await screen.findByRole('alert');
    await fireEvent.click(screen.getByRole('button', { name: 'Try again' }));
    expect(await screen.findByText('last-person@example.test')).toBeTruthy();
    expect(screen.queryByText('first-person@example.test')).toBeNull();
    await waitFor(() => expect(screen.getByText(/Page 2 of 2/)).toBeTruthy());
});
