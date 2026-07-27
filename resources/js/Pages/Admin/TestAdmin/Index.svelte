<script>
  import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
  import { router, usePage } from '@inertiajs/svelte';
  import { Badge } from '@/Components/ui/badge';
  import { Button } from '@/Components/ui/button';
  import { Play, Square, ClipboardList, Calendar, Clock, Users, AlertTriangle } from 'lucide-svelte';
  import { success as showSuccess, error as showError } from '@/lib/toast';
  import { hasApplicants, timeWindowLabel, isOutsideStartWindow, canOverrideStartWindow } from '@/lib/session-helpers';
  import { formatDate } from '@/lib/date-utils';
  import { onMount } from 'svelte';

  let { today = [], upcoming = [], past = [], filters = {}, statuses = [], flash = {}, breadcrumbParent = { label: 'My Sessions', href: '/proctor/my-sessions' } } = $props();

  const breadcrumbs = $derived([breadcrumbParent]);

  // Show toasts on mount for flash messages
  onMount(() => {
    if (flash?.success) showSuccess(flash.success);
    if (flash?.error) showError(flash.error);
  });

  function statusVariant(s) {
    if (s === 'draft') return 'muted';
    if (s === 'published') return 'success';
    if (s === 'in_progress') return 'warning';
    if (s === 'completed') return 'outline';
    if (s === 'cancelled') return 'danger';
    return 'outline';
  }

  function statusLabel(s) {
    const labels = {
      draft: 'Draft',
      published: 'Published',
      in_progress: 'In progress',
      completed: 'Completed',
      cancelled: 'Cancelled',
    };
    return labels[s] ?? s;
  }



  function formatTime(value) {
    if (!value) return '—';
    const parts = String(value).split(':');
    const hours = parseInt(parts[0], 10) || 0;
    const mins = parseInt(parts[1], 10) || 0;
    const h = hours % 12 || 12;
    return `${h}:${String(mins).padStart(2, '0')} ${hours < 12 ? 'AM' : 'PM'}`;
  }

  function formatTimeRange(start, end) {
    if (!start) return '—';
    let range = formatTime(start);
    if (end) range += ' – ' + formatTime(end);
    return range;
  }

  let showCloseConfirm = $state(false);
  let closeTargetId = $state(null);

  let showStartConfirm = $state(false);
  let startTargetId = $state(null);

  function initiateStart(sessionId) {
    startTargetId = sessionId;
    showStartConfirm = true;
  }

  function confirmStart() {
    if (startTargetId === null) return;
    const sessionId = startTargetId;
    showStartConfirm = false;
    startTargetId = null;
    router.post(`/admin/exam-scheduling/${sessionId}/start`, {}, {
      onSuccess: () => router.reload(),
      onError: (err) => showError(err?.message ?? 'Unable to start session. Please refresh the page or try again.'),
    });
  }

  function cancelStart() {
    showStartConfirm = false;
    startTargetId = null;
  }

  function initiateClose(sessionId) {
    closeTargetId = sessionId;
    showCloseConfirm = true;
  }

  function confirmClose() {
    if (closeTargetId === null) return;
    const sessionId = closeTargetId;
    showCloseConfirm = false;
    closeTargetId = null;
    router.post(`/admin/exam-scheduling/${sessionId}/complete`, {}, {
      onSuccess: () => router.reload(),
      onError: (err) => showError(err?.message ?? 'Unable to close session. Please refresh the page or try again.'),
    });
  }

  function cancelClose() {
    showCloseConfirm = false;
    closeTargetId = null;
  }

  function isInProgress(session) {
    return session.status === 'in_progress';
  }

  function canStart(session) {
    return session.can_start && hasApplicants(session) && session.is_within_start_window;
  }

  function canStartWithOverride(session) {
    return session.can_start && hasApplicants(session) && !session.is_within_start_window && session.can_override_schedule;
  }

  function canComplete(session) {
    return session.can_complete && session.status === 'in_progress';
  }

  function hasGroups() {
    return today.length > 0 || upcoming.length > 0 || past.length > 0;
  }

  function sessionRosterHref(session) {
    return `/admin/test-admin/sessions/${session.id}/roster`;
  }
</script>

<svelte:head>
  <title>My Sessions - SecureCAT</title>
</svelte:head>

<AuthenticatedLayout {breadcrumbs}>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-semibold">My Sessions</h1>
      <p class="mt-1 text-sm text-muted-foreground">
        Exam sessions assigned to you. Click "View Session" to manage attendance and submissions.
      </p>
    </div>

    {#if !hasGroups()}
      <!-- Empty state -->
      <div class="rounded-lg border border-border bg-card px-4 py-16 text-center">
        <ClipboardList class="mx-auto h-12 w-12 text-muted-foreground/40 mb-4" />
        <p class="text-sm font-medium">No sessions found</p>
        <p class="mt-1 text-sm text-muted-foreground">
          No exam sessions match your current view. Sessions will appear here once published.
        </p>
      </div>
    {:else}
      <!-- Today -->
      {#if today.length > 0}
        <div class="space-y-3">
          <h2 class="text-lg font-semibold flex items-center gap-2">
            <Calendar class="h-5 w-5" />
            Today
          </h2>
          <div class="space-y-2">
            {#each today as session (session.id)}
              <div
                class="rounded-lg border border-border bg-card p-4 transition-colors {isInProgress(session) ? 'border-l-4 border-l-primary bg-primary/5' : ''}"
              >
                <div class="flex flex-wrap items-start justify-between gap-3">
                  <!-- Left: date, time, room -->
                  <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
                      <div class="flex items-center gap-1.5 text-muted-foreground">
                        <Calendar class="h-4 w-4 shrink-0" />
                        <span>{formatDate(session.date)}</span>
                      </div>
                      <div class="flex items-center gap-1.5 text-muted-foreground">
                        <Clock class="h-4 w-4 shrink-0" />
                        <span>{formatTimeRange(session.start_time, session.end_time)}</span>
                      </div>
                      {#if session.room_name}
                        <div class="flex items-center gap-1.5 text-muted-foreground">
                          <span>{session.room_name}</span>
                          {#if session.building}
                            <span class="text-muted-foreground/70">({session.building})</span>
                          {/if}
                        </div>
                      {/if}
                      {#if session.applicants_count != null}
                        <div class="flex items-center gap-1.5 text-muted-foreground">
                          <Users class="h-4 w-4 shrink-0" />
                          <span>{session.applicants_count}</span>
                        </div>
                      {/if}
                    </div>
                  </div>

                  <!-- Right: status + actions -->
                  <div class="flex items-center gap-2 shrink-0">
                    <Badge variant={statusVariant(session.status)}>{statusLabel(session.status)}</Badge>

                    {#if canStart(session)}
                      <Button size="sm" onclick={() => initiateStart(session.id)}>
                        <Play class="h-4 w-4 mr-1" />
                        Start session
                      </Button>
                    {:else if canStartWithOverride(session)}
                      <Button size="sm" variant="outline" class="border-amber-500 text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-950" onclick={() => initiateStart(session.id)}>
                        <Play class="h-4 w-4 mr-1" />
                        Start (override)
                      </Button>
                    {:else if timeWindowLabel(session) === 'no-applicants'}
                      <span class="text-sm text-muted-foreground">No applicants</span>
                    {:else if timeWindowLabel(session) === 'past-date'}
                      <span class="text-sm text-muted-foreground">Session date has passed</span>
                    {:else if isOutsideStartWindow(session)}
                      <span class="text-sm text-muted-foreground">Outside scheduled time</span>
                    {/if}

                    {#if canComplete(session)}
                      <Button size="sm" variant="outline" onclick={() => initiateClose(session.id)}>
                        <Square class="h-4 w-4 mr-1" />
                        Close session
                      </Button>
                    {/if}

                    <a href={sessionRosterHref(session)} class="inline-flex items-center gap-1 text-sm text-primary hover:underline">
                      <ClipboardList class="h-4 w-4" />
                      View Session
                    </a>
                  </div>
                </div>
              </div>
            {/each}
          </div>
        </div>
      {/if}

      <!-- Upcoming -->
      {#if upcoming.length > 0}
        <div class="space-y-3">
          <h2 class="text-lg font-semibold flex items-center gap-2">
            <Calendar class="h-5 w-5" />
            Upcoming
          </h2>
          <div class="space-y-2">
            {#each upcoming as session (session.id)}
              <div
                class="rounded-lg border border-border bg-card p-4 transition-colors {isInProgress(session) ? 'border-l-4 border-l-primary bg-primary/5' : ''}"
              >
                <div class="flex flex-wrap items-start justify-between gap-3">
                  <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
                      <div class="flex items-center gap-1.5 text-muted-foreground">
                        <Calendar class="h-4 w-4 shrink-0" />
                        <span>{formatDate(session.date)}</span>
                      </div>
                      <div class="flex items-center gap-1.5 text-muted-foreground">
                        <Clock class="h-4 w-4 shrink-0" />
                        <span>{formatTimeRange(session.start_time, session.end_time)}</span>
                      </div>
                      {#if session.room_name}
                        <div class="flex items-center gap-1.5 text-muted-foreground">
                          <span>{session.room_name}</span>
                          {#if session.building}
                            <span class="text-muted-foreground/70">({session.building})</span>
                          {/if}
                        </div>
                      {/if}
                      {#if session.applicants_count != null}
                        <div class="flex items-center gap-1.5 text-muted-foreground">
                          <Users class="h-4 w-4 shrink-0" />
                          <span>{session.applicants_count}</span>
                        </div>
                      {/if}
                    </div>
                  </div>

                  <div class="flex items-center gap-2 shrink-0">
                    <Badge variant={statusVariant(session.status)}>{statusLabel(session.status)}</Badge>

                    {#if canStart(session)}
                      <Button size="sm" onclick={() => startSession(session.id)}>
                        <Play class="h-4 w-4 mr-1" />
                        Start session
                      </Button>
                    {:else if isOutsideStartWindow(session)}
                      <span class="text-sm text-muted-foreground">Outside scheduled time</span>
                    {/if}

                    {#if canComplete(session)}
                      <Button size="sm" variant="outline" onclick={() => initiateClose(session.id)}>
                        <Square class="h-4 w-4 mr-1" />
                        Close session
                      </Button>
                    {/if}

                    <a href={sessionRosterHref(session)} class="inline-flex items-center gap-1 text-sm text-primary hover:underline">
                      <ClipboardList class="h-4 w-4" />
                      View Session
                    </a>
                  </div>
                </div>
              </div>
            {/each}
          </div>
        </div>
      {/if}

      <!-- Past -->
      {#if past.length > 0}
        <div class="space-y-3">
          <h2 class="text-lg font-semibold flex items-center gap-2">
            <Calendar class="h-5 w-5" />
            Past
          </h2>
          <div class="space-y-2">
            {#each past as session (session.id)}
              <div
                class="rounded-lg border border-border bg-card p-4 transition-colors {isInProgress(session) ? 'border-l-4 border-l-primary bg-primary/5' : ''}"
              >
                <div class="flex flex-wrap items-start justify-between gap-3">
                  <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
                      <div class="flex items-center gap-1.5 text-muted-foreground">
                        <Calendar class="h-4 w-4 shrink-0" />
                        <span>{formatDate(session.date)}</span>
                      </div>
                      <div class="flex items-center gap-1.5 text-muted-foreground">
                        <Clock class="h-4 w-4 shrink-0" />
                        <span>{formatTimeRange(session.start_time, session.end_time)}</span>
                      </div>
                      {#if session.room_name}
                        <div class="flex items-center gap-1.5 text-muted-foreground">
                          <span>{session.room_name}</span>
                          {#if session.building}
                            <span class="text-muted-foreground/70">({session.building})</span>
                          {/if}
                        </div>
                      {/if}
                      {#if session.applicants_count != null}
                        <div class="flex items-center gap-1.5 text-muted-foreground">
                          <Users class="h-4 w-4 shrink-0" />
                          <span>{session.applicants_count}</span>
                        </div>
                      {/if}
                    </div>
                  </div>

                  <div class="flex items-center gap-2 shrink-0">
                    <Badge variant={statusVariant(session.status)}>{statusLabel(session.status)}</Badge>

                    {#if canStart(session)}
                      <Button size="sm" onclick={() => startSession(session.id)}>
                        <Play class="h-4 w-4 mr-1" />
                        Start session
                      </Button>
                    {:else if isOutsideStartWindow(session)}
                      <span class="text-sm text-muted-foreground">Outside scheduled time</span>
                    {/if}

                    {#if canComplete(session)}
                      <Button size="sm" variant="outline" onclick={() => initiateClose(session.id)}>
                        <Square class="h-4 w-4 mr-1" />
                        Close session
                      </Button>
                    {/if}

                    <a href={sessionRosterHref(session)} class="inline-flex items-center gap-1 text-sm text-primary hover:underline">
                      <ClipboardList class="h-4 w-4" />
                      View Session
                    </a>
                  </div>
                </div>
              </div>
            {/each}
          </div>
        </div>
      {/if}
    {/if}
  </div>
</AuthenticatedLayout>

<!-- Start confirmation dialog -->
{#if showStartConfirm}
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" role="dialog" aria-modal="true">
    <div class="bg-card rounded-lg p-6 max-w-md shadow-xl border border-border">
      <h3 class="text-lg font-semibold mb-2">Start session</h3>
      <p class="text-sm text-muted-foreground mb-4">
        Once started, this session cannot be deleted. Attendance and submission tracking will begin immediately.
      </p>
      <div class="flex gap-3 justify-end">
        <Button variant="outline" onclick={cancelStart}>Cancel</Button>
        <Button onclick={confirmStart}>Start session</Button>
      </div>
    </div>
  </div>
{/if}

<!-- Close confirmation dialog -->
{#if showCloseConfirm}
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
    <div class="bg-card rounded-lg p-6 max-w-md shadow-xl border border-border">
      <h3 class="text-lg font-semibold mb-2">Close session</h3>
      <p class="text-sm text-muted-foreground mb-4">
        Some applicants have not submitted yet. Close anyway?
      </p>
      <div class="flex gap-3 justify-end">
        <Button variant="outline" onclick={cancelClose}>Cancel</Button>
        <Button variant="destructive" onclick={confirmClose}>Confirm</Button>
      </div>
    </div>
  </div>
{/if}
