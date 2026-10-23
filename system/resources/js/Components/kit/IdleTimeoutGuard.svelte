<script lang="ts">
  import { onDestroy, onMount, untrack } from 'svelte';
  import { router, usePage } from '@inertiajs/svelte';
  import { Button } from '@/Components/ui/button';
  import * as Dialog from '@/Components/ui/dialog';
  import { Clock } from 'lucide-svelte';
  import SessionExpiredCard from './SessionExpiredCard.svelte';
  import { isStaffAccountChangedResponse } from '@/lib/staff-account-generation';
  import { sound } from '@/lib/sound';
  import {
    parseLeaseProjection,
    readWorkstationTimeoutPreference,
    requestSessionExtension,
    writeWorkstationTimeoutPreference,
    StaffAccountChangedError,
  } from '@/lib/staff-session-lease';
  import {
    clearStaffIdleMarker,
    readStaffIdleMarker,
    writeStaffIdleMarker,
  } from '@/lib/staff-idle-marker';
  import {
    closeStaffIdleAlert,
    showStaffIdleAlert,
  } from '@/lib/staff-idle-alerts.svelte';

  type GuardState = 'active' | 'warning' | 'reconciling' | 'expired' | 'logged_out';
  type SharedSessionLease = {
    server_now: string;
    idle_expires_at: string;
    absolute_expires_at: string;
    effective_idle_minutes: number;
  };
  type StaffSharedProps = {
    auth?: {
      staff_inactivity_generation?: string | null;
      session_lease?: SharedSessionLease | null;
    };
  };

  const warningWindow = 2 * 60 * 1000;
  const activityEvents = ['mousemove', 'keydown', 'click', 'scroll', 'wheel', 'touchstart'];
  const keepaliveTimeout = 8000;

  const page = usePage<StaffSharedProps>();
  const generationFromPage = $derived(page.props.auth?.staff_inactivity_generation);
  const staffGeneration = $derived(
    typeof generationFromPage === 'string' && generationFromPage.trim().length > 0
      ? generationFromPage
      : null,
  );

  let state = $state<GuardState>('active');
  let remainingSeconds = $state(120);
  let serverClockOffset = $state(0);
  let keepAliveBusy = $state(false);
  let keepAliveError = $state('');
  let selectedPreset = $state<TimeoutPreset>(readWorkstationTimeoutPreference());
  let lastActivityAt = 0;
  let lastScheduledAt = 0;
  let idleExpiresAt = 0;
  let absoluteExpiresAt: number | null = null;
  let lastUrgentSecond = -1;
  let warningTimer: ReturnType<typeof setTimeout> | undefined;
  let logoutTimer: ReturnType<typeof setTimeout> | undefined;
  let countdownTimer: ReturnType<typeof setInterval> | undefined;
  let keepaliveTimeoutTimer: ReturnType<typeof setTimeout> | undefined;
  let keepaliveController: AbortController | undefined;
  let activeGeneration: string | null = null;
  let mounted = false;
  let warningCycle = 0;
  let activeWarningEpisodeId: string | null = null;
  let destroyed = false;
  let hasAuthoritativeLease = false;
  let statusBusy = $state(false);
  let statusController: AbortController | undefined;
  let statusTimeoutTimer: ReturnType<typeof setTimeout> | undefined;

  const countdown = $derived(
    `${String(Math.floor(remainingSeconds / 60)).padStart(2, '0')}:${String(remainingSeconds % 60).padStart(2, '0')}`,
  );
  function nowWithOffset(): number {
    return Date.now() + serverClockOffset;
  }

  function clearTimers(): void {
    clearTimeout(warningTimer);
    clearTimeout(logoutTimer);
    clearInterval(countdownTimer);
    warningTimer = undefined;
    logoutTimer = undefined;
    countdownTimer = undefined;
  }

  function clearBrowserAlert(): void {
    try {
      closeStaffIdleAlert();
    } catch {
      // Browser notifications are best-effort.
    }
  }

  function showBrowserAlert(generation: string): void {
    const warningEpisodeId = activeWarningEpisodeId;
    if (!warningEpisodeId) {
      return;
    }

    try {
      showStaffIdleAlert(generation, warningEpisodeId);
    } catch {
      // Browser notifications are best-effort.
    }
  }

  function playCue(cue: () => Promise<boolean>): void {
    try {
      void cue().catch(() => false);
    } catch {
      // Audio must never block an idle-state transition.
    }
  }

  function abortKeepalive(): void {
    clearTimeout(keepaliveTimeoutTimer);
    keepaliveTimeoutTimer = undefined;

    const controller = keepaliveController;
    keepaliveController = undefined;
    controller?.abort();
    keepAliveBusy = false;
  }

  function abortSessionStatus(): void {
    clearTimeout(statusTimeoutTimer);
    statusTimeoutTimer = undefined;
    const controller = statusController;
    statusController = undefined;
    controller?.abort();
    statusBusy = false;
  }

  function applyLeaseDeadlines(
    idleDeadline: number,
    absoluteDeadline: number,
    serverNow: number,
    generation: string,
  ): void {
    if (destroyed || activeGeneration !== generation || state === 'expired' || state === 'logged_out') {
      return;
    }

    clearTimers();
    abortKeepalive();
    abortSessionStatus();
    clearBrowserAlert();
    hasAuthoritativeLease = true;
    serverClockOffset = serverNow - Date.now();
    idleExpiresAt = idleDeadline;
    absoluteExpiresAt = absoluteDeadline;
    warningCycle += 1;
    activeWarningEpisodeId = null;
    state = 'active';
    keepAliveError = '';
    lastUrgentSecond = -1;
    remainingSeconds = Math.max(0, Math.ceil((idleExpiresAt - nowWithOffset()) / 1000));
    armDeadlineTimers(generation);
    reconcileDeadline(generation);
  }

  function applySharedLease(lease: SharedSessionLease, generation: string): boolean {
    const serverNow = lease.server_now ? Date.parse(lease.server_now) : Date.now();
    const idleDeadline = Date.parse(lease.idle_expires_at);
    const absoluteDeadline = Date.parse(lease.absolute_expires_at);
    if (
      !Number.isFinite(serverNow)
      || !Number.isFinite(idleDeadline)
      || !Number.isFinite(absoluteDeadline)
      || idleDeadline > absoluteDeadline
    ) {
      return false;
    }

    if (!hasAuthoritativeLease || idleDeadline > idleExpiresAt) {
      applyLeaseDeadlines(idleDeadline, absoluteDeadline, serverNow, generation);
    }
    return true;
  }

  async function reconcileSessionStatus(generation = activeGeneration): Promise<void> {
    if (!generation || generation !== activeGeneration || destroyed || state === 'expired' || state === 'logged_out') {
      return;
    }

    if (nowWithOffset() >= idleExpiresAt || (absoluteExpiresAt !== null && nowWithOffset() >= absoluteExpiresAt)) {
      clearTimers();
      clearBrowserAlert();
      state = 'reconciling';
      remainingSeconds = 0;
    }
    if (statusController || keepAliveBusy) {
      return;
    }

    const controller = new AbortController();
    statusController = controller;
    statusBusy = true;
    keepAliveError = '';
    const requestTimeout = setTimeout(() => controller.abort(), keepaliveTimeout);
    statusTimeoutTimer = requestTimeout;
    const isCurrent = () => !destroyed && activeGeneration === generation && statusController === controller;

    try {
      const response = await fetch('/staff/session-keepalive', {
        method: 'GET',
        credentials: 'same-origin',
        cache: 'no-store',
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        signal: controller.signal,
      });
      if (!isCurrent()) return;
      if (response.status === 409) {
        const payload: unknown = await response.clone().json().catch(() => null);
        if (isStaffAccountChangedResponse(response.status, payload)) {
          return;
        }
      }
      if ([401, 403, 409, 419].includes(response.status)) {
        expireSession('server_session_expired', generation);
        return;
      }
      if (!response.ok) {
        throw new Error('Your session could not be checked. Please try again.');
      }

      const lease = parseLeaseProjection(await response.json());
      if (!isCurrent()) return;
      if (lease === null || lease.status !== 'ok' || lease.idle_expires_at > lease.absolute_expires_at) {
        throw new Error('Your session could not be checked. Please try again.');
      }
      serverClockOffset = lease.server_now - Date.now();
      if (nowWithOffset() >= lease.idle_expires_at || nowWithOffset() >= lease.absolute_expires_at) {
        expireSession('server_session_expired', generation);
        return;
      }

      applyLeaseDeadlines(lease.idle_expires_at, lease.absolute_expires_at, lease.server_now, generation);
    } catch (error) {
      if (isCurrent()) {
        keepAliveError = error instanceof Error && error.name === 'AbortError'
          ? 'The session check timed out. Please try again.'
          : 'Your session could not be checked. Check your connection and try again.';
      }
    } finally {
      clearTimeout(requestTimeout);
      if (statusController === controller) {
        statusController = undefined;
        statusTimeoutTimer = undefined;
        statusBusy = false;
      }
    }
  }

  function armDeadlineTimers(generation: string): void {
    clearTimeout(warningTimer);
    clearTimeout(logoutTimer);
    warningTimer = undefined;
    logoutTimer = undefined;

    if (state === 'active') {
      warningTimer = setTimeout(() => {
        if (activeGeneration !== generation || state === 'expired' || state === 'logged_out') {
          return;
        }

        reconcileDeadline(generation);
        if (state === 'active') {
          armDeadlineTimers(generation);
        }
      }, Math.max(0, idleExpiresAt - warningWindow - nowWithOffset()));
    }

    logoutTimer = setTimeout(() => {
      if (activeGeneration !== generation || state === 'expired' || state === 'logged_out') {
        return;
      }

      reconcileDeadline(generation);
      if (state === 'active' || state === 'warning') {
        armDeadlineTimers(generation);
      }
    }, Math.max(0, idleExpiresAt - nowWithOffset()));
  }

  function manualLogOut(): void {
    clearTimers();
    abortKeepalive();
    abortSessionStatus();
    clearBrowserAlert();
    clearStaffIdleMarker();
    state = 'logged_out';
    router.post('/logout', {}, {
      onFinish: () => { window.location.replace('/login'); },
      onError: () => { window.location.replace('/login'); },
    });
  }


  function expireSession(_reason: string, generation = activeGeneration): void {
    if (
      !generation
      || generation !== activeGeneration
      || state === 'expired'
      || state === 'logged_out'
    ) {
      return;
    }

    clearTimers();
    state = 'expired';
    abortKeepalive();
    abortSessionStatus();
    clearBrowserAlert();
    clearStaffIdleMarker();
    playCue(() => sound.expired(
      () => !destroyed && activeGeneration === generation && state === 'expired',
    ));
  }

  function updateCountdown(): void {
    const generation = activeGeneration;
    if (state !== 'warning' || !generation) {
      return;
    }

    const secondsUntilLogout = Math.ceil((idleExpiresAt - nowWithOffset()) / 1000);
    if (secondsUntilLogout <= 0) {
      void reconcileSessionStatus(generation);
      return;
    }

    remainingSeconds = secondsUntilLogout;

    if ((remainingSeconds === 10 || remainingSeconds === 5) && remainingSeconds !== lastUrgentSecond) {
      lastUrgentSecond = remainingSeconds;
      const warningCycleAtCue = warningCycle;
      const displayedSecond = remainingSeconds;
      playCue(() => sound.countdownUrgent(
        displayedSecond,
        () => !destroyed
          && activeGeneration === generation
          && state === 'warning'
          && warningCycle === warningCycleAtCue
          && Math.ceil((idleExpiresAt - nowWithOffset()) / 1000) === displayedSecond,
      ));
    }
  }

  function enterWarning(): void {
    if (state !== 'active' || !activeGeneration) {
      return;
    }

    selectedPreset = readWorkstationTimeoutPreference();
    const generation = activeGeneration;
    const warningCycleAtEntry = ++warningCycle;
    activeWarningEpisodeId = `${generation}:${idleExpiresAt}`;
    state = 'warning';
    keepAliveError = '';
    lastUrgentSecond = -1;
    playCue(() => sound.warning(
      () => !destroyed
        && activeGeneration === generation
        && state === 'warning'
        && warningCycle === warningCycleAtEntry
        && nowWithOffset() < idleExpiresAt,
    ));

    if (document.visibilityState === 'hidden') {
      showBrowserAlert(generation);
    }

    updateCountdown();
    armDeadlineTimers(generation);

    if (state === 'warning') {
      countdownTimer = setInterval(updateCountdown, 250);
    }
  }

  function scheduleIntervalAt(timestamp: number, generation: string): void {
    if (activeGeneration !== generation) {
      return;
    }

    clearTimers();
    abortKeepalive();
    clearBrowserAlert();
    warningCycle += 1;
    activeWarningEpisodeId = null;
    lastActivityAt = timestamp;
    lastScheduledAt = timestamp;
    const idleDuration = readWorkstationTimeoutPreference();
    idleExpiresAt = Math.min(
      timestamp + idleDuration * 60 * 1000,
      absoluteExpiresAt ?? Number.POSITIVE_INFINITY,
    );
    writeStaffIdleMarker(generation, timestamp, idleDuration);
    state = 'active';
    keepAliveError = '';
    remainingSeconds = Math.ceil((idleExpiresAt - nowWithOffset()) / 1000);
    lastUrgentSecond = -1;
    armDeadlineTimers(generation);
  }

  function reconcileDeadline(generation = activeGeneration): void {
    if (
      !generation
      || generation !== activeGeneration
      || state === 'expired'
      || state === 'logged_out'
    ) {
      return;
    }

    if (nowWithOffset() >= idleExpiresAt || (absoluteExpiresAt !== null && nowWithOffset() >= absoluteExpiresAt)) {
      void reconcileSessionStatus(generation);
      return;
    }

    if (idleExpiresAt - nowWithOffset() <= warningWindow) {
      if (state === 'active') {
        enterWarning();
      } else if (state === 'warning') {
        updateCountdown();
      }
    }
  }

  function startGeneration(generation: string): void {
    if (activeGeneration === generation) {
      return;
    }
    if (activeGeneration !== null) {
      return;
    }
    activeGeneration = generation;

    abortSessionStatus();
    hasAuthoritativeLease = false;
    absoluteExpiresAt = null;
    state = 'active';
    keepAliveError = '';
    remainingSeconds = 120;
    lastUrgentSecond = -1;

    const sharedLease = page.props.auth?.session_lease;
    if (sharedLease && applySharedLease(sharedLease, generation)) {
      return;
    }

    const now = nowWithOffset();
    const storedActivity = readStaffIdleMarker(generation, now);
    scheduleIntervalAt(storedActivity ?? now, generation);
    reconcileDeadline(generation);
  }

  let lastHeartbeatAt = 0;

  async function sendActiveHeartbeat(generation: string): Promise<void> {
    if (!generation || activeGeneration !== generation || keepAliveBusy || state !== 'active') {
      return;
    }
    lastHeartbeatAt = nowWithOffset();
    try {
      const preset = readWorkstationTimeoutPreference();
      const lease = await requestSessionExtension(preset);
      if (activeGeneration !== generation || state !== 'active' || lease.status !== 'ok') {
        return;
      }
      serverClockOffset = lease.server_now - Date.now();
      idleExpiresAt = lease.idle_expires_at;
      absoluteExpiresAt = lease.absolute_expires_at;
      armDeadlineTimers(generation);
    } catch {
      // Background heartbeat is best-effort
    }
  }

  function handleActivity(event: Event): void {
    const generation = activeGeneration;
    if (!event.isTrusted || !generation || state === 'reconciling') {
      return;
    }

    reconcileDeadline(generation);
    if (state !== 'active') {
      return;
    }

    const now = nowWithOffset();
    if (now - lastScheduledAt >= 10_000) {
      scheduleIntervalAt(now, generation);
      if (now - lastHeartbeatAt >= 3 * 60 * 1000) {
        void sendActiveHeartbeat(generation);
      }
    }
  }
  function reconcilePageResume(): void {
    const generation = activeGeneration;
    void reconcileSessionStatus(generation);

    if (document.visibilityState === 'visible') {
      clearBrowserAlert();
    } else if (generation && state === 'warning') {
      showBrowserAlert(generation);
    }
  }

  function isCurrentKeepalive(generation: string, controller: AbortController): boolean {
    return activeGeneration === generation
      && (state === 'warning' || state === 'reconciling')
      && keepaliveController === controller;
  }

  async function extendSession(): Promise<void> {
    const generation = activeGeneration;
    if (!generation || keepAliveBusy || state !== 'warning') {
      return;
    }

    keepAliveBusy = true;
    keepAliveError = '';
    const controller = new AbortController();
    keepaliveController = controller;
    const requestTimeout = setTimeout(() => controller.abort(), keepaliveTimeout);
    keepaliveTimeoutTimer = requestTimeout;

    try {
      const lease = await requestSessionExtension(selectedPreset, controller.signal);
      if (!isCurrentKeepalive(generation, controller)) {
        return;
      }

      serverClockOffset = lease.server_now - Date.now();
      if (
        lease.status !== 'ok'
        || lease.idle_expires_at <= nowWithOffset()
        || lease.absolute_expires_at <= nowWithOffset()
        || lease.idle_expires_at > lease.absolute_expires_at
      ) {
        throw new Error('Your session could not be extended. Please try again.');
      }

      applyLeaseDeadlines(lease.idle_expires_at, lease.absolute_expires_at, lease.server_now, generation);
      writeWorkstationTimeoutPreference(selectedPreset);
      playCue(() => sound.press());
    } catch (error) {
      if (error instanceof StaffAccountChangedError) {
        return;
      }
      if (isCurrentKeepalive(generation, controller)) {
        keepAliveError = error instanceof Error && error.name === 'AbortError'
          ? 'The extension request timed out. Please try again.'
          : error instanceof Error
            ? error.message
            : 'Your session could not be extended. Please try again.';
      }
    } finally {
      clearTimeout(requestTimeout);
      if (keepaliveController === controller) {
        keepaliveTimeoutTimer = undefined;
        keepaliveController = undefined;
        keepAliveBusy = false;
        reconcileDeadline(generation);
      }
    }
  }

  function absoluteExpiryLabel(): string | null {
    if (absoluteExpiresAt === null || absoluteExpiresAt - nowWithOffset() > 60 * 60 * 1000) {
      return null;
    }

    return new Date(absoluteExpiresAt).toLocaleTimeString([], {
      hour: 'numeric',
      minute: '2-digit',
    });
  }


  $effect(() => {
    const generation = staffGeneration;
    const lease = page.props.auth?.session_lease;
    if (mounted && generation) {
      untrack(() => {
        if (generation !== activeGeneration) {
          startGeneration(generation);
        } else if (lease) {
          applySharedLease(lease, generation);
        }
      });
    }
  });

  onMount(() => {
    mounted = true;
    for (const eventName of activityEvents) {
      window.addEventListener(eventName, handleActivity, { capture: true, passive: true });
    }
    document.addEventListener('visibilitychange', reconcilePageResume);
    window.addEventListener('pageshow', reconcilePageResume);
    window.addEventListener('focus', reconcilePageResume);
    window.addEventListener('online', reconcilePageResume);

    if (staffGeneration) {
      startGeneration(staffGeneration);
    }
  });

  onDestroy(() => {
    destroyed = true;
    mounted = false;
    abortSessionStatus();
    clearTimers();
    abortKeepalive();
    for (const eventName of activityEvents) {
      window.removeEventListener(eventName, handleActivity, { capture: true });
    }
    document.removeEventListener('visibilitychange', reconcilePageResume);
    window.removeEventListener('pageshow', reconcilePageResume);
    window.removeEventListener('focus', reconcilePageResume);
    window.removeEventListener('online', reconcilePageResume);
  });
</script>

{#if state === 'expired'}
  <SessionExpiredCard />
{:else if state === 'reconciling'}
  <div class="fixed inset-0 z-50 bg-background" aria-hidden="true"></div>
  <Dialog.Root open={true} onOpenChange={() => {}}>
    <Dialog.Content
      role="alertdialog"
      aria-modal="true"
      aria-labelledby="session-check-title"
      aria-describedby="session-check-description"
      showCloseButton={false}
      class="sm:max-w-md"
    >
      <Dialog.Header>
        <Dialog.Title id="session-check-title">Checking your session</Dialog.Title>
        <Dialog.Description id="session-check-description">
          Your workstation is locked while the server confirms whether your session is still active.
        </Dialog.Description>
      </Dialog.Header>
      {#if keepAliveError}
        <p class="text-sm text-destructive" role="alert">{keepAliveError}</p>
      {/if}
      <Dialog.Footer>
        <Button type="button" variant="outline" onclick={manualLogOut}>Log Out Now</Button>
        <Button type="button" onclick={() => { void reconcileSessionStatus(); }} disabled={statusBusy || keepAliveBusy}>
          {statusBusy || keepAliveBusy ? 'Checking…' : 'Check session again'}
        </Button>
      </Dialog.Footer>
    </Dialog.Content>
  </Dialog.Root>
{:else if state === 'warning'}
  <Dialog.Root open={true} onOpenChange={() => {}}>
    <Dialog.Content
        role="alertdialog"
        aria-live="polite"
        aria-modal="true"
        aria-labelledby="session-inactivity-title"
        aria-describedby="session-inactivity-description"
        showCloseButton={false}
        class="sm:max-w-md"
      >
        <div class="mb-2 flex size-12 items-center justify-center rounded-full bg-amber-500/10 text-amber-500 mx-auto">
          <Clock class="size-6" aria-hidden="true" />
        </div>
        <Dialog.Header class="text-center">
          <Dialog.Title id="session-inactivity-title" class="text-center">Session Inactivity Warning</Dialog.Title>
          <Dialog.Description id="session-inactivity-description" class="text-center">
            For campus security and data privacy, this workstation will automatically log out in {countdown} due to inactivity.
          </Dialog.Description>
        </Dialog.Header>
        {#if remainingSeconds >= 1}
          <div class="my-2 flex items-center justify-center gap-2">
            <Clock class="size-5 text-amber-500 animate-pulse" aria-hidden="true" />
            <p class="font-mono text-2xl font-bold tracking-tight text-foreground tabular-nums" aria-live="polite">{countdown}</p>
          </div>
        {/if}
        {#if keepAliveError}
          <p class="text-sm text-destructive" role="alert">{keepAliveError}</p>
        {/if}
        <div class="flex flex-wrap justify-center gap-2" aria-label="Session extension duration">
          {#each [
            { minutes: 15, label: '+15 min (Standard)' },
            { minutes: 30, label: '+30 min' },
            { minutes: 60, label: '+1 hour' },
            { minutes: 480, label: '+8 hours (Full Shift)' }
          ] as preset}
            <Button
              type="button"
              variant={selectedPreset === preset.minutes ? 'default' : 'outline'}
              size="sm"
              aria-pressed={selectedPreset === preset.minutes}
              onclick={() => { selectedPreset = preset.minutes as TimeoutPreset; }}
              disabled={keepAliveBusy}
              class="gap-1.5"
            >
              <Clock class="size-3.5" aria-hidden="true" />
              <span>{preset.label}</span>
            </Button>
          {/each}
        </div>
        {#if absoluteExpiryLabel()}
          <p class="text-center text-sm text-muted-foreground">
            Re-authentication required at {absoluteExpiryLabel()}.
          </p>
        {/if}
        <Dialog.Footer>
          <Button type="button" variant="outline" onclick={manualLogOut}>
            Log Out Now
          </Button>
          <Button type="button" onclick={extendSession} disabled={keepAliveBusy} class="gap-1.5">
            <Clock class="size-4" aria-hidden="true" />
            <span>{keepAliveBusy ? 'Extending…' : 'Extend Session'}</span>
          </Button>
        </Dialog.Footer>
      </Dialog.Content>
  </Dialog.Root>
{/if}
