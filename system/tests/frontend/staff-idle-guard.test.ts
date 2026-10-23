// @vitest-environment jsdom
import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/svelte';
import { tick } from 'svelte';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type * as StaffSessionLeaseModule from '../../resources/js/lib/staff-session-lease';
import IdleTimeoutGuard from '../../resources/js/Components/kit/IdleTimeoutGuard.svelte';

const cues = vi.hoisted(() => ({
  warning: vi.fn(async (_isCurrent: () => boolean) => true),
  countdownUrgent: vi.fn(async (_second: number, _isCurrent: () => boolean) => true),
  expired: vi.fn(async () => true),
  press: vi.fn(async () => true),
  requestSessionExtension: vi.fn(),
  writeWorkstationTimeoutPreference: vi.fn(),
}));
const sharedPage = vi.hoisted(() => ({
  props: {
    auth: {
      staff_inactivity_generation: 'guard-session',
      session_lease: null as { idle_expires_at: string; absolute_expires_at: string; effective_idle_minutes: number } | null,
    },
  },
}));
vi.mock('@inertiajs/svelte', () => ({
  usePage: () => sharedPage,
  router: { post: vi.fn() },
}));
vi.mock('../../resources/js/lib/sound', () => ({ sound: cues }));

vi.mock('../../resources/js/lib/staff-session-lease', async (importOriginal) => ({
  ...await importOriginal<typeof StaffSessionLeaseModule>(),
  requestSessionExtension: cues.requestSessionExtension,
  readWorkstationTimeoutPreference: () => 15,
  writeWorkstationTimeoutPreference: cues.writeWorkstationTimeoutPreference,
}));
beforeEach(() => {
  vi.useFakeTimers();
  vi.setSystemTime(new Date('2026-10-05T00:00:00Z'));
  sessionStorage.clear();
  vi.clearAllMocks();
  sharedPage.props.auth.session_lease = null;
  vi.stubGlobal('fetch', vi.fn(async () => ({
    ok: true,
    status: 200,
    json: async () => ({
      status: 'ok',
      server_now: new Date(Date.now()).toISOString(),
      idle_expires_at: new Date(Date.now() + 30 * 60 * 1000).toISOString(),
      absolute_expires_at: new Date(Date.now() + 8 * 60 * 60 * 1000).toISOString(),
      effective_idle_minutes: 30,
    }),
  })));
  cues.requestSessionExtension.mockImplementation(async (minutes: number) => ({
    server_now: Date.now(),
    idle_expires_at: Date.now() + minutes * 60 * 1000,
    absolute_expires_at: Date.now() + 8 * 60 * 60 * 1000,
    effective_idle_minutes: minutes,
    status: 'ok',
  }));
});
afterEach(() => {
  cleanup();
  vi.useRealTimers();
  vi.unstubAllGlobals();
});

async function mountAtRemaining(seconds: number): Promise<void> {
  sessionStorage.setItem('nexiam.staff.last_activity_at', JSON.stringify({
    generation: 'guard-session',
    lastActivityAt: Date.now() - 15 * 60 * 1000 + seconds * 1000,
  }));
  render(IdleTimeoutGuard);
  await tick();
}

describe('authoritative staff lease reconciliation', () => {
  it('uses the shared lease instead of a stale browser activity marker', async () => {
    sharedPage.props.auth.session_lease = {
      server_now: new Date(Date.now()).toISOString(),
      idle_expires_at: new Date(Date.now() + 60 * 60 * 1000).toISOString(),
      absolute_expires_at: new Date(Date.now() + 8 * 60 * 60 * 1000).toISOString(),
      effective_idle_minutes: 60,
    };

    await mountAtRemaining(30);
    await vi.advanceTimersByTimeAsync(30_000);

    expect(screen.queryByText('Session Inactivity Warning')).toBeNull();
    expect(cues.warning).not.toHaveBeenCalled();
    expect(fetch).not.toHaveBeenCalled();
  });

  it('adopts a sibling tab extension before expiring at the cached deadline', async () => {
    await mountAtRemaining(1);
    await vi.advanceTimersByTimeAsync(1000);
    await tick();

    expect(fetch).toHaveBeenCalledWith('/staff/session-keepalive', expect.objectContaining({
      method: 'GET',
      cache: 'no-store',
    }));
    expect(screen.queryByRole('alertdialog')).toBeNull();
    expect(screen.queryByText('Session expired')).toBeNull();
    expect(cues.expired).not.toHaveBeenCalled();
    expect(cues.requestSessionExtension).not.toHaveBeenCalled();
  });

  it('enters terminal expiration only after the server rejects the lease', async () => {
    vi.mocked(fetch).mockResolvedValueOnce({ ok: false, status: 401 } as Response);
    await mountAtRemaining(1);
    await vi.advanceTimersByTimeAsync(1000);
    await tick();

    expect(screen.getByText('Session expired')).toBeTruthy();
    expect(cues.expired).toHaveBeenCalledOnce();
  });
  it('does not treat account-change conflict as expiration of the newly signed-in account', async () => {
    vi.mocked(fetch).mockResolvedValueOnce({
      ok: false,
      status: 409,
      clone: () => ({ json: async () => ({ error: 'staff_account_changed' }) }),
    } as Response);
    await mountAtRemaining(1);
    await vi.advanceTimersByTimeAsync(1000);
    await tick();

    expect(screen.queryByText('Session expired')).toBeNull();
    expect(cues.expired).not.toHaveBeenCalled();
  });


  it('keeps an offline deadline locked but allows a successful status retry', async () => {
    vi.mocked(fetch).mockRejectedValueOnce(new Error('Offline'));
    await mountAtRemaining(1);
    await vi.advanceTimersByTimeAsync(1000);
    await tick();

    expect(screen.getByText('Checking your session')).toBeTruthy();
    expect(screen.queryByText('Session expired')).toBeNull();
    expect(screen.queryByText('00:00')).toBeNull();
    expect(cues.expired).not.toHaveBeenCalled();

    await fireEvent.click(screen.getByRole('button', { name: 'Check session again' }));
    await tick();

    expect(screen.queryByText('Checking your session')).toBeNull();
    expect(screen.queryByText('Session expired')).toBeNull();
    expect(cues.requestSessionExtension).not.toHaveBeenCalled();
  });
});

describe('deferred inactivity audio after timer suspension', () => {
  it('rejects a warning resumed after its absolute deadline without a timer callback', async () => {
    await mountAtRemaining(120);
    const isCurrent = cues.warning.mock.calls[0][0];
    expect(isCurrent()).toBe(true);
    vi.setSystemTime(Date.now() + 120_000);
    expect(isCurrent()).toBe(false);
  });

  it('rejects an urgent pulse resumed in a later second without a timer callback', async () => {
    await mountAtRemaining(10);
    const [second, isCurrent] = cues.countdownUrgent.mock.calls[0];
    expect(second).toBe(10);
    expect(isCurrent()).toBe(true);
    vi.setSystemTime(Date.now() + 1000);
    expect(isCurrent()).toBe(false);
  });
});
describe('staff session extension warning', () => {
  it.each([
    [15, '+15 min (Standard)'],
    [30, '+30 min'],
    [60, '+1 hour'],
    [480, '+8 hours (Full Shift)'],
  ] as const)('requests the %s-minute preset and resumes on confirmation', async (minutes, label) => {
    await mountAtRemaining(90);
    expect(screen.getByText('01:30')).toBeTruthy();

    await fireEvent.click(screen.getByRole('button', { name: label }));
    await fireEvent.click(screen.getByRole('button', { name: 'Extend Session' }));
    await tick();

    expect(cues.requestSessionExtension).toHaveBeenCalledWith(minutes, expect.any(AbortSignal));
    expect(cues.writeWorkstationTimeoutPreference).toHaveBeenCalledWith(minutes);
    expect(screen.queryByRole('alertdialog')).toBeNull();
    expect(cues.press).toHaveBeenCalled();
  });

  it('shows re-authentication at the absolute shift ceiling', async () => {
    const ceiling = new Date(Date.now() + 30 * 1000);
    sharedPage.props.auth.session_lease = {
      server_now: new Date(Date.now()).toISOString(),
      idle_expires_at: ceiling.toISOString(),
      absolute_expires_at: ceiling.toISOString(),
      effective_idle_minutes: 15,
    };
    await mountAtRemaining(30);
    expect(screen.getByText(/Re-authentication required at/)).toBeTruthy();
  });

  it('keeps the warning open and shows an extension failure', async () => {
    cues.requestSessionExtension.mockRejectedValueOnce(new Error('Offline'));
    await mountAtRemaining(90);
    await fireEvent.click(screen.getByRole('button', { name: 'Extend Session' }));
    await tick();

    expect(screen.getByRole('dialog')).toBeTruthy();
    expect(screen.getByRole('alert').textContent).toContain('Offline');
  });
});
