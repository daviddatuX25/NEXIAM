<script>
  import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
  import { Link, router, usePage } from '@inertiajs/svelte';
  import { Button } from '@/Components/ui/button';
  import { Badge } from '@/Components/ui/badge';
  import { Input } from '@/Components/ui/input';
  import * as Table from '@/Components/ui/table';
  import * as Dialog from '@/Components/ui/dialog';
  import { ArrowLeft, UserCheck, UserX, FileCheck, Play, Square, AlertTriangle } from 'lucide-svelte';
  import { success as showSuccess, error as showError } from '@/lib/toast';
  import { formatDate, formatDate as fmtDate } from '@/lib/date-utils';
  import { onMount } from 'svelte';

  let { session, applicants = [], stats = {}, breadcrumbParent = { label: 'My Sessions', href: '/proctor/my-sessions' } } = $props();

  const page = usePage();

  // ── Client-side time tracking for dynamic window checks ──
  // Server-provided flags (is_within_start_window, is_within_window, is_past_end) are
  // snapshots at render time. We recompute every 30s so the UI reacts to the clock.
  let liveNow = $state(Date.now());

  onMount(() => {
    const flash = $page.props.flash;
    if (flash?.success) showSuccess(flash.success);
    if (flash?.error) showError(flash.error);

    const timer = setInterval(() => { liveNow = Date.now(); }, 30_000);
    return () => clearInterval(timer);
  });

  function parseSessionTime(dateStr, timeStr) {
    if (!dateStr || !timeStr) return null;
    // session.date may arrive as ISO datetime ("2026-05-14T16:00:00.000000Z")
    // or plain date ("2026-05-15") — extract just the date part.
    const datePart = String(dateStr).split('T')[0];
    const [y, m, d] = datePart.split('-').map(Number);
    if (Number.isNaN(y) || Number.isNaN(m) || Number.isNaN(d)) return null;
    const parts = String(timeStr).split(':');
    const h = parseInt(parts[0], 10) || 0;
    const min = parseInt(parts[1], 10) || 0;
    return new Date(y, m - 1, d, h, min, 0).getTime();
  }

  const GRACE_BEFORE_MS = 15 * 60_000;
  const GRACE_AFTER_MS = 30 * 60_000;

  const clientWithinStartWindow = $derived.by(() => {
    const start = parseSessionTime(session.date, session.start_time);
    const end = session.end_time
      ? parseSessionTime(session.date, session.end_time)
      : (start ? start + 24 * 60 * 60_000 : null);
    if (start == null || end == null) return session.is_within_start_window;
    return liveNow >= (start - GRACE_BEFORE_MS) && liveNow <= (end + GRACE_AFTER_MS);
  });

  const clientPastEndTime = $derived.by(() => {
    const end = parseSessionTime(session.date, session.end_time);
    if (end == null) return false;
    return liveNow > end;
  });

  // Show toast when exam window ends (fires once on transition)
  let hasShownEndToast = $state(false);
  $effect(() => {
    if (clientPastEndTime && !hasShownEndToast) {
      hasShownEndToast = true;
      showError('Exam window ended. Actions locked.');
    }
  });

  let searchQuery = $state('');
  let actionError = $state('');
  let showCloseDialog = $state(false);

  const filteredApplicants = $derived(
    searchQuery.trim()
      ? applicants.filter(
          (a) =>
            (a.name ?? '').toLowerCase().includes(searchQuery.toLowerCase()) ||
            (a.reference_number ?? '').toLowerCase().includes(searchQuery.toLowerCase())
        )
      : applicants
  );

  /** Session statuses: draft, published, in_progress, completed, cancelled. Per E-019. */
  function sessionStatusVariant(status) {
    if (status === 'draft') return 'muted';
    if (status === 'published') return 'success';
    if (status === 'in_progress') return 'warning';
    if (status === 'completed') return 'outline';
    if (status === 'cancelled') return 'danger';
    return 'outline';
  }

  /** Attendance/submission statuses: pending, present, absent, submitted. Per E-019. */
  function attendanceStatusVariant(status) {
    if (status === 'pending') return 'muted';
    if (status === 'present' || status === 'submitted') return 'success';
    if (status === 'absent') return 'danger';
    return 'outline';
  }

  function sessionStatusLabel(status) {
    const labels = {
      draft: 'Draft',
      published: 'Published (scheduled)',
      in_progress: 'In progress',
      completed: 'Completed',
      cancelled: 'Cancelled',
    };
    return labels[status] ?? status;
  }

  function handleRosterError(err) {
    actionError = err?.message ?? err ?? 'Something went wrong.';
    setTimeout(() => (actionError = ''), 5000);
  }

  function markPresent(applicantId) {
    actionError = '';
    router.post(`/proctor/sessions/${session.id}/attendance`, { applicant_id: applicantId, status: 'present' }, {
      onError: handleRosterError,
      onSuccess: () => router.reload(),
    });
  }

  function markAbsent(applicantId) {
    actionError = '';
    router.post(`/proctor/sessions/${session.id}/attendance`, { applicant_id: applicantId, status: 'absent' }, {
      onError: handleRosterError,
      onSuccess: () => router.reload(),
    });
  }

  function logSubmission(applicantId) {
    actionError = '';
    router.post(`/proctor/sessions/${session.id}/submission`, { applicant_id: applicantId }, {
      onError: handleRosterError,
      onSuccess: () => router.reload(),
    });
  }

  function logSubmissionBulk() {
    actionError = '';
    router.post(`/proctor/sessions/${session.id}/submission-bulk`, {}, {
      onError: handleRosterError,
      onSuccess: () => router.reload(),
    });
  }

  let showStartDialog = $state(false);

  function confirmStartSession() {
    actionError = '';
    showStartDialog = false;
    router.post(`/proctor/sessions/${session.id}/start`, {}, { onError: handleRosterError, onSuccess: () => router.reload() });
  }

  function confirmCloseSession() {
    actionError = '';
    router.post(`/proctor/sessions/${session.id}/close`, {}, {
      onError: handleRosterError,
      onSuccess: () => { showCloseDialog = false; router.reload(); },
    });
  }

  // ── Derived permissions (use client-side time flags) ──
  const canStart = $derived(
    session.status === 'published' &&
    (clientWithinStartWindow !== false || session.can_override_schedule === true)
  );
  const canClose = $derived(session.status === 'in_progress');
  const outsideStartWindow = $derived(session.status === 'published' && clientWithinStartWindow === false);
  const showAdminOverrideHint = $derived(outsideStartWindow && session.can_override_schedule === true);
  const canMarkAttendance = $derived(
    session.status === 'in_progress' && !clientPastEndTime
  );
  const canLogSubmission = $derived(
    session.status === 'in_progress' && !clientPastEndTime
  );
  const canBulkSubmit = $derived(
    session.status === 'in_progress' && !clientPastEndTime && (stats.present_pending_submission ?? 0) > 0
  );
  // Attendance/submission columns only relevant once session has started or completed
  const showAttendanceColumns = $derived(
    session.status === 'in_progress' || session.status === 'completed'
  );

  function formatTime(value) {
    if (value == null || value === '') return '—';
    const parts = String(value).split(':');
    const hours = parseInt(parts[0], 10) || 0;
    const mins = parseInt(parts[1], 10) || 0;
    const h = hours % 12 || 12;
    const ampm = hours < 12 ? 'AM' : 'PM';
    return `${h}:${String(mins).padStart(2, '0')} ${ampm}`;
  }

  const breadcrumbs = $derived([
    breadcrumbParent,
    { label: session?.id ? 'Session #' + session.id : 'Session' }
  ]);
</script>

<svelte:head>
  <title>Session Roster - SecureCAT</title>
</svelte:head>

<AuthenticatedLayout breadcrumbs={breadcrumbs}>
  <div class="space-y-3">
    <!-- Session Header -->
    <div class="rounded-lg border border-border bg-card p-4">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 class="text-lg font-bold">Session #{session.id}</h1>
          <p class="mt-1 text-sm text-muted-foreground">
            {formatDate(session.date)} · {formatTime(session.start_time)}{#if session.end_time} - {formatTime(session.end_time)}{/if} · {session.room?.name ?? '-'}
          </p>
        </div>
        <div class="flex items-center gap-2">
          <Badge variant={sessionStatusVariant(session.status)}>{sessionStatusLabel(session.status)}</Badge>
          {#if canStart}
            <Button size="sm" onclick={() => showStartDialog = true}>
              <Play class="h-4 w-4 mr-1" />
              Start
            </Button>
          {/if}
          {#if canClose}
            <Button size="sm" variant="destructive" onclick={() => showCloseDialog = true}>
              <span class="relative mr-2 flex h-3 w-3">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-white/75"></span>
                <span class="relative inline-flex h-3 w-3 rounded-full bg-white"></span>
              </span>
              <Square class="h-4 w-4 mr-1" />
              Close Session
            </Button>
          {/if}
        </div>
      </div>
      {#if outsideStartWindow && !session.can_override_schedule}
        <p class="mt-2 text-sm text-amber-600 flex items-center gap-1.5">
          <AlertTriangle class="h-4 w-4" />
          Outside scheduled time window — Start will become available at {formatTime(session.start_time)}
        </p>
      {/if}
      {#if showAdminOverrideHint}
        <p class="mt-2 text-sm text-amber-600 flex items-center gap-1.5">
          <AlertTriangle class="h-4 w-4" />
          Outside scheduled time window — you may override as admin
        </p>
      {/if}
    </div>

    <!-- Stats -->
    <div class="rounded-lg border border-border bg-card p-4">
      <div class="flex flex-wrap gap-4 text-sm">
        <div><span class="text-muted-foreground">Total:</span> <strong>{stats.total ?? 0}</strong></div>
        {#if showAttendanceColumns}
          <div><span class="text-muted-foreground">Present:</span> <strong>{stats.present ?? 0}</strong></div>
          <div><span class="text-muted-foreground">Absent:</span> <strong>{stats.absent ?? 0}</strong></div>
          <div><span class="text-muted-foreground">Pending:</span> <strong>{stats.pending ?? 0}</strong></div>
          <div><span class="text-muted-foreground">Submitted:</span> <strong>{stats.submitted ?? 0}</strong></div>
        {/if}
      </div>
    </div>

    <!-- Applicants -->
    <div class="rounded-lg border border-border bg-card p-4">
      <div class="flex flex-wrap items-center gap-3">
        <h2 class="text-base font-semibold">Applicants</h2>
        <Input
          type="search"
          placeholder="Search by name or reference..."
          class="h-9 max-w-xs"
          bind:value={searchQuery}
          aria-label="Search applicants"
        />
        {#if canBulkSubmit}
          <Button size="sm" variant="outline" onclick={logSubmissionBulk}>
            <FileCheck class="h-4 w-4 mr-1" />
            Mark all submitted
          </Button>
        {/if}
      </div>
      {#if clientPastEndTime && (session.status === 'in_progress' || session.status === 'published')}
        <p class="mt-3 text-sm text-destructive">Exam window ended. Actions locked.</p>
      {/if}
      {#if filteredApplicants.length > 0}
        <div class="overflow-x-auto">
          <Table.Root class="w-full">
            <Table.Header class="bg-muted/50">
              <Table.Row>
                <Table.Head class="px-4 py-3">Reference</Table.Head>
                <Table.Head class="px-4 py-3">Name</Table.Head>
                {#if showAttendanceColumns}
                  <Table.Head class="px-4 py-3">Attendance</Table.Head>
                  <Table.Head class="px-4 py-3">Time in</Table.Head>
                  <Table.Head class="px-4 py-3">Submission</Table.Head>
                  <Table.Head class="px-4 py-3">Submitted at</Table.Head>
                {/if}
                <Table.Head class="text-center px-4 py-3">Action</Table.Head>
              </Table.Row>
            </Table.Header>
            <Table.Body>
              {#each filteredApplicants as row (row.id)}
                <Table.Row>
                  <Table.Cell class="px-4 py-3">{row.reference_number ?? '-'}</Table.Cell>
                  <Table.Cell class="px-4 py-3">{row.name ?? '-'}</Table.Cell>
                  {#if showAttendanceColumns}
                    <Table.Cell class="px-4 py-3">
                      <Badge variant={attendanceStatusVariant(row.attendance_status)}>{row.attendance_status}</Badge>
                    </Table.Cell>
                    <Table.Cell class="px-4 py-3">{fmtDate(row.attendance_marked_at, 'time')}</Table.Cell>
                    <Table.Cell class="px-4 py-3">
                      <Badge variant={attendanceStatusVariant(row.submission_status)}>{row.submission_status}</Badge>
                    </Table.Cell>
                    <Table.Cell class="px-4 py-3">{fmtDate(row.submitted_at, 'time')}</Table.Cell>
                  {/if}
                  <Table.Cell class="px-4 py-3 text-center">
                    {#if row.attendance_status === 'pending' && canMarkAttendance}
                      <div class="flex justify-center gap-1">
                        <Button size="sm" variant="outline" onclick={() => markPresent(row.id)}>Present</Button>
                        <Button size="sm" variant="outline" onclick={() => markAbsent(row.id)}>Absent</Button>
                      </div>
                    {:else if row.attendance_status === 'present' && row.submission_status === 'pending' && canLogSubmission}
                      <Button size="sm" variant="outline" onclick={() => logSubmission(row.id)}>Submit</Button>
                    {:else}
                      <span class="text-muted-foreground">-</span>
                    {/if}
                  </Table.Cell>
                </Table.Row>
              {/each}
            </Table.Body>
          </Table.Root>
        </div>
      {:else}
        <p class="mt-3 text-sm text-muted-foreground">
          {searchQuery.trim() ? 'No applicants match your search.' : 'No applicants assigned.'}
        </p>
      {/if}
    </div>
  </div>

  <!-- Close Session Confirmation Dialog -->
  <Dialog.Root bind:open={showCloseDialog}>
    <Dialog.Content>
      <Dialog.Header>
        <Dialog.Title>Close Session</Dialog.Title>
        <Dialog.Description>
          Are you sure you want to close this session? This will mark it as completed and lock all further actions.
        </Dialog.Description>
      </Dialog.Header>
      <div class="py-4">
        <p class="text-sm text-muted-foreground">
          Session #{session.id} · {formatDate(session.date)} · {formatTime(session.start_time)}{#if session.end_time} - {formatTime(session.end_time)}{/if}
        </p>
      </div>
      <Dialog.Footer>
        <Button variant="outline" onclick={() => showCloseDialog = false}>Cancel</Button>
        <Button variant="destructive" onclick={confirmCloseSession}>
          <Square class="h-4 w-4 mr-1" />
          Close Session
        </Button>
      </Dialog.Footer>
    </Dialog.Content>
  </Dialog.Root>
  <!-- Start Session Confirmation Dialog -->
  <Dialog.Root bind:open={showStartDialog}>
    <Dialog.Content>
      <Dialog.Header>
        <Dialog.Title>Start Session</Dialog.Title>
        <Dialog.Description>
          Once started, this session cannot be deleted. Attendance and submission tracking will begin immediately.
        </Dialog.Description>
      </Dialog.Header>
      <div class="py-4">
        <p class="text-sm text-muted-foreground">
          Session #{session.id} · {formatDate(session.date)} · {formatTime(session.start_time)}{#if session.end_time} - {formatTime(session.end_time)}{/if}
        </p>
      </div>
      <Dialog.Footer>
        <Button variant="outline" onclick={() => showStartDialog = false}>Cancel</Button>
        <Button onclick={confirmStartSession}>
          <Play class="h-4 w-4 mr-1" />
          Start Session
        </Button>
      </Dialog.Footer>
    </Dialog.Content>
  </Dialog.Root>
</AuthenticatedLayout>