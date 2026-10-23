// @vitest-environment jsdom

import { cleanup, render } from '@testing-library/svelte';
import { get } from 'svelte/store';
import { tick } from 'svelte';
import { SvelteMap } from 'svelte/reactivity';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import ToastManager from '../../resources/js/Components/kit/ToastManager.svelte';
import { error, info, success, toasts, warning } from '../../resources/js/lib/toast.js';

const { sound } = vi.hoisted(() => ({
  sound: { success: vi.fn(), error: vi.fn(), press: vi.fn() },
}));

vi.mock('../../resources/js/lib/sound.js', () => ({ sound }));

const pageProps = new SvelteMap<string, Record<string, unknown>>();

vi.mock('@inertiajs/svelte', async (importOriginal) => ({
  ...await importOriginal<Record<string, unknown>>(),
  usePage: () => ({ props: { get flash() { return pageProps.get('flash'); } } }),
}));

import { sound as mockedSound } from '../../resources/js/lib/sound.js';


beforeEach(() => {
  vi.useFakeTimers();
  toasts.clear();
  Object.defineProperty(Element.prototype, 'animate', {
    configurable: true,
    value: vi.fn(() => ({
      cancel: vi.fn(),
      currentTime: 0,
      onfinish: null,
    })),
  });
});

afterEach(() => {
  cleanup();
  toasts.clear();
  pageProps.clear();
  vi.clearAllMocks();
  vi.useRealTimers();
});

describe('toast store', () => {
  it('uses severity defaults while allowing an explicit duration', () => {
    success('Saved');
    info('Working');
    warning('Review needed');
    error('Unable to save', { duration: 321 });

    expect(get(toasts)).toMatchObject([
      { message: 'Saved', type: 'success', duration: 6000 },
      { message: 'Working', type: 'info', duration: 6000 },
      { message: 'Review needed', type: 'warning', duration: 10000 },
      { message: 'Unable to save', type: 'error', duration: 321 },
    ]);
  });

  it('deduplicates identical notifications and replaces keyed notifications', () => {
    const firstId = success('Draft saved', { duration: 0 });
    const duplicateId = success('Draft saved', { duration: 0 });
    const keyedId = info('Saving draft', { dedupeKey: 'draft-status', duration: 0 });
    const replacementId = error('Draft save failed', {
      dedupeKey: 'draft-status',
      duration: 0,
    });

    expect(duplicateId).toBe(firstId);
    expect(replacementId).toBe(keyedId);
    expect(get(toasts)).toHaveLength(2);
    expect(get(toasts)[1]).toMatchObject({
      id: keyedId,
      message: 'Draft save failed',
      type: 'error',
      duration: 0,
      dedupeKey: 'draft-status',
    });
  });
  it('does not play sound again for an un-keyed duplicate', () => {
    success('Saved', { duration: 0 });
    expect(mockedSound.success).toHaveBeenCalledTimes(1);

    success('Saved', { duration: 0 });
    expect(mockedSound.success).toHaveBeenCalledTimes(1);
  });

  it('plays sound for a fresh toast and an updated keyed toast', () => {
    info('Working', { duration: 0 });
    info('Saving', { dedupeKey: 'task', duration: 0 });
    info('Still saving', { dedupeKey: 'task', duration: 0 });

    expect(mockedSound.press).toHaveBeenCalledTimes(3);
  });

  it('keeps only the four newest visible notifications', () => {
    ['One', 'Two', 'Three', 'Four', 'Five'].forEach((message) => {
      info(message, { duration: 0 });
    });

    expect(get(toasts).map((toast) => toast.message)).toEqual(['Two', 'Three', 'Four', 'Five']);
  });

  it('dismisses a toast after its configured duration', () => {
    const id = warning('Please review', { duration: 100 });

    vi.advanceTimersByTime(99);
    expect(get(toasts)).toHaveLength(1);

    vi.advanceTimersByTime(1);
    expect(get(toasts)).toHaveLength(0);
    expect(id).toEqual(expect.any(String));
  });
});

describe('ToastManager', () => {
  it('announces backend validation errors, including nested bags, without losing the reason', async () => {
    const { getByRole } = render(ToastManager);
    await tick();

    document.dispatchEvent(new CustomEvent('inertia:error', {
      detail: { errors: { cancellation: { session: ['Session is already terminal.'] } } },
    }));
    await tick();

    expect(getByRole('alert').textContent).toContain('Session is already terminal.');
  });

  it('allows the same rejection again after dismissal and removes its listener on unmount', async () => {
    const { getByRole, unmount } = render(ToastManager);
    await tick();
    const reject = () => document.dispatchEvent(new CustomEvent('inertia:error', {
      detail: { errors: { reason: 'The reason must not exceed 500 characters.' } },
    }));
    reject();
    await tick();
    expect(getByRole('alert').textContent).toContain('The reason must not exceed 500 characters.');
    toasts.clear();
    reject();
    await tick();
    expect(get(toasts)).toMatchObject([
      { type: 'error', message: 'The reason must not exceed 500 characters.' },
    ]);
    unmount();
    toasts.clear();
    reject();
    expect(get(toasts)).toEqual([]);
  });

  it('uses assertive alert semantics for warnings', async () => {
    const { getByRole } = render(ToastManager, {
      props: { placement: 'viewport' },
    });

    warning('A review is required', { duration: 0 });
    await tick();

    const warningToast = getByRole('alert');
    expect(warningToast.getAttribute('aria-live')).toBe('assertive');
    expect(getByRole('button', { name: 'Dismiss notification' })).toBeTruthy();
  });

  it('uses polite status semantics for success', async () => {
    const { getByRole } = render(ToastManager);

    success('Draft saved', { duration: 0 });
    await tick();

    expect(getByRole('status').getAttribute('aria-live')).toBe('polite');
  });

  it('exposes the requested placement while defaulting to viewport', async () => {
    const { container, rerender } = render(ToastManager);

    expect(container.querySelector('[data-toast-placement]')?.getAttribute('data-toast-placement')).toBe(
      'viewport',
    );

    await rerender({ placement: 'below-anchor' });

    expect(container.querySelector('[data-toast-placement]')?.getAttribute('data-toast-placement')).toBe(
      'below-anchor',
    );
  });
  it('consumes the same flash status only once across rerenders', async () => {
    pageProps.set('flash', { status: 'Profile updated' });
    const { rerender, getAllByText } = render(ToastManager);
    await tick();

    expect(getAllByText('Profile updated')).toHaveLength(1);
    await rerender({});
    await tick();

    expect(getAllByText('Profile updated')).toHaveLength(1);
  });
  it('shows identical flash feedback from a later response after dismissal', async () => {
    pageProps.set('flash', { error: 'Session is already terminal.' });
    render(ToastManager);
    await tick();
    expect(get(toasts)).toMatchObject([{ type: 'error', message: 'Session is already terminal.' }]);
    toasts.clear();

    pageProps.set('flash', { error: 'Session is already terminal.' });
    await tick();

    expect(get(toasts)).toMatchObject([{ type: 'error', message: 'Session is already terminal.' }]);
  });

  it('does not replay flash or validation error toasts when background polling re-wraps page.props or preserves errors', async () => {
    const rawFlash: Record<string, unknown> = {
      error: '0 sessions published; 1 skipped: EX-PG-7C33AB62DA2B: Session is published, not draft.',
    };
    pageProps.set('flash', { ...rawFlash });

    const { unmount } = render(ToastManager);
    await tick();
    expect(get(toasts)).toHaveLength(1);

    // Dismiss the initial toast
    toasts.clear();
    expect(get(toasts)).toHaveLength(0);

    // Simulate @inertiajs/core + @inertiajs/svelte partial reload (e.g. NotificationBell 8s usePoll):
    // inertia:start with poll: true, only: ['notifications', 'navigation_badges'] followed by inertia:beforeUpdate
    // and a new proxy reference around the carried-over rawFlash object.
    const pollPage = { props: { flash: rawFlash } };
    document.dispatchEvent(new CustomEvent('inertia:start', {
      detail: { visit: { id: 'poll-visit-1', poll: true, preserveErrors: true, only: ['notifications', 'navigation_badges'] } },
    }));
    document.dispatchEvent(new CustomEvent('inertia:beforeUpdate', {
      detail: { page: pollPage },
    }));
    pageProps.set('flash', { ...pollPage.props.flash });
    await tick();

    expect(get(toasts)).toHaveLength(0);

    // Simulate ToastManager remount (e.g. preserveState: false) while carried-over flash is still on page.props
    unmount();
    render(ToastManager);
    await tick();
    expect(get(toasts)).toHaveLength(0);

    // Simulate background poll visit preserving validation errors (poll: true, preserveErrors: true)
    document.dispatchEvent(new CustomEvent('inertia:error', {
      detail: { visitId: 'poll-visit-1', errors: { reason: 'The reason must not exceed 500 characters.' } },
    }));
    document.dispatchEvent(new CustomEvent('inertia:finish', {
      detail: { visit: { id: 'poll-visit-1' } },
    }));
    await tick();
    expect(get(toasts)).toHaveLength(0);
  });

  it('re-announces identical flash feedback when a fresh non-partial Inertia response arrives and preserveEqualProps reused the flash reference', async () => {
    const sharedFlash: Record<string, unknown> = { error: 'Session is already terminal.' };
    pageProps.set('flash', sharedFlash);
    render(ToastManager);
    await tick();
    expect(get(toasts)).toHaveLength(1);

    toasts.clear();
    expect(get(toasts)).toHaveLength(0);

    // Simulate a fresh full visit where @inertiajs/core preserveEqualProps kept sharedFlash on incomingPage.props.flash
    const incomingPage = { props: { flash: sharedFlash } };
    const visit: Record<string, unknown> = { id: 'post-visit-2', method: 'post', only: [], poll: false };
    document.dispatchEvent(new CustomEvent('inertia:start', {
      detail: { visit },
    }));
    (visit.onBeforeUpdate as ((page: unknown) => void) | undefined)?.(incomingPage);
    document.dispatchEvent(new CustomEvent('inertia:beforeUpdate', {
      detail: { page: incomingPage },
    }));
    document.dispatchEvent(new CustomEvent('inertia:finish', {
      detail: { visit },
    }));
    pageProps.set('flash', incomingPage.props.flash);
    await tick();

    expect(get(toasts)).toMatchObject([{ type: 'error', message: 'Session is already terminal.' }]);
  });

  it('preserves per-visit flash ownership when a background poll responds while a foreground POST is in flight, and ignores null-state hash popstate', async () => {
    const staleFlash: Record<string, unknown> = { status: 'Initial session saved.' };
    pageProps.set('flash', staleFlash);
    render(ToastManager);
    await tick();
    expect(get(toasts)).toMatchObject([{ type: 'success', message: 'Initial session saved.' }]);
    toasts.clear();

    // Start a foreground POST visit
    const postVisit: Record<string, unknown> = { id: 'post-visit-concurrent', method: 'post', only: [], poll: false };
    document.dispatchEvent(new CustomEvent('inertia:start', { detail: { visit: postVisit } }));

    // While POST is in flight, NotificationBell starts a background poll and responds FIRST with the carried-over staleFlash
    const pollVisit: Record<string, unknown> = {
      id: 'poll-visit-concurrent',
      method: 'get',
      only: ['notifications', 'navigation_badges'],
      poll: true,
      preserveErrors: true,
    };
    document.dispatchEvent(new CustomEvent('inertia:start', { detail: { visit: pollVisit } }));
    const pollPage = { props: { flash: staleFlash } };
    (pollVisit.onBeforeUpdate as ((page: unknown) => void) | undefined)?.(pollPage);
    document.dispatchEvent(new CustomEvent('inertia:beforeUpdate', { detail: { page: pollPage } }));
    document.dispatchEvent(new CustomEvent('inertia:finish', { detail: { visit: pollVisit } }));
    pageProps.set('flash', { ...pollPage.props.flash });
    await tick();

    // Poll must NOT replay staleFlash
    expect(get(toasts)).toHaveLength(0);

    // Null-state hash popstate (e.g. #subtests anchor) must NOT arm history suppression
    window.dispatchEvent(new PopStateEvent('popstate', { state: null }));

    // Now the foreground POST finishes with identical flash text
    const postPage = { props: { flash: staleFlash } };
    (postVisit.onBeforeUpdate as ((page: unknown) => void) | undefined)?.(postPage);
    document.dispatchEvent(new CustomEvent('inertia:beforeUpdate', { detail: { page: postPage } }));
    document.dispatchEvent(new CustomEvent('inertia:finish', { detail: { visit: postVisit } }));
    pageProps.set('flash', postPage.props.flash);
    await tick();

    expect(get(toasts)).toMatchObject([{ type: 'success', message: 'Initial session saved.' }]);
  });
});
