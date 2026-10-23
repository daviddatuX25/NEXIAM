// @vitest-environment jsdom

import { cleanup, fireEvent, render, screen } from '@testing-library/svelte';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import OpeningAlert from '../../resources/js/Pages/Admission/OpeningAlert.svelte';

const { post } = vi.hoisted(() => ({ post: vi.fn() }));
vi.mock('@inertiajs/svelte', async (importOriginal) => {
    const actual = await importOriginal<Record<string, any>>();
    return {
        ...actual,
        usePage: () => ({ url: '/apply/opening-alerts/test', props: {
            auth: { user: null }, opening_alerts_available: true, public_intake_open: false,
        } }),
        router: { ...actual.router, post },
    };
});

beforeEach(() => {
    post.mockReset();
    localStorage.clear();
    vi.stubGlobal('ResizeObserver', class {
        observe() {}
        unobserve() {}
        disconnect() {}
    });
    vi.stubGlobal('matchMedia', vi.fn(() => ({
        matches: false, addEventListener() {}, removeEventListener() {},
    })));
});
afterEach(() => {
    cleanup();
    vi.unstubAllGlobals();
});

it.each([
    ['verify', 'Confirm your email', 'Confirm email'],
    ['unsubscribe', 'Unsubscribe from opening alerts', 'Unsubscribe'],
])('renders the real %s page and waits for explicit confirmation before submitting', async (purpose, heading, action) => {
    render(OpeningAlert, { props: { state: purpose, purpose, token: 'synthetic-token' } });
    expect(screen.getByRole('heading', { name: heading })).toBeTruthy();
    expect(post).not.toHaveBeenCalled();
    await fireEvent.click(screen.getByRole('button', { name: action, exact: true }));
    expect(post).toHaveBeenCalledOnce();
});
