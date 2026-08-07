<script>
  import { Link, router, usePage } from '@inertiajs/svelte';
  import { Button } from '@/Components/ui/button';
  import { Badge } from '@/Components/ui/badge';
  import { Input } from '@/Components/ui/input';
  import QrScanner from '@/Components/QrScanner.svelte';
  import {
    ArrowLeft, UserCheck, UserX, FileCheck, Play, Square, QrCode,
    UserMinus, BarChart2, Search, Clock, Info, AlertTriangle,
  } from 'lucide-svelte';
  import axios from 'axios';
  import { success as showSuccess, error as showError } from '@/lib/toast';
  import { formatDate, formatDate as fmtDate } from '@/lib/date-utils';
  import { onMount, onDestroy } from 'svelte';

  /**
   * Props
   * @prop {object} session - Exam session object (includes is_within_start_window, can_override_schedule)
   * @prop {Array}  applicants - Assigned applicants with attendance/submission pivot data
   * @prop {object} stats - Aggregate counts (total, present, absent, pending, submitted, present_pending_submission)
   * @prop {object} permissions - Boolean flags controlling which actions are visible
   * @prop {string} backHref - URL for the back link
   * @prop {string} backLabel - Label for the back link
   */
  let {
    session,
    applicants = [],
    stats = {},
    permissions = {},
    backHref = '/admin/test-admin/sessions',
    backLabel = 'Back to my sessions',
  } = $props();

  // Permission flags with safe defaults
  const p = $derived({
    canStart:           permissions.canStart           ?? false,
    canClose:           permissions.canClose           ?? false,
    canMarkAttendance:  permissions.canMarkAttendance  ?? false,
    canLogSubmission:   permissions.canLogSubmission   ?? false,
    canBulkSubmit:      permissions.canBulkSubmit      ?? false,
    canOverrideSchedule: permissions.canOverrideSchedule ?? false,
    canRemoveApplicant: permissions.canRemoveApplicant ?? false,
    showAnalytics:      permissions.showAnalytics      ?? false,
  });

  const page = usePage();

  // Show toasts on mount for flash messages
  onMount(() => {
    const flash = $page.props.flash;
    if (flash?.success) showSuccess(flash.success);
    if (flash?.error) showError(flash.error);
  });

  let searchQuery   = $state('');
  let actionError   = $state('');
  let showScanner   = $state(false);
  let scanMode      = $state('attendance'); // 'attendance' | 'submission'
  let scanHandled   = $state(false);
  let scanError     = $state('');
  let now           = $state(new Date());

  // Tick the clock every 30s so time-dependent UI updates in real-time
  const clockInterval = setInterval(() => { now = new Date(); }, 30_000);
  onDestroy(() => clearInterval(clockInterval));

  const filteredApplicants = $derived(
    searchQuery.trim()
      ? applicants.filter(
          (a) =>
            (a.name ?? '').toLowerCase().includes(searchQuery.toLowerCase()) ||
            (a.reference_number ?? '').toLowerCase().includes(searchQuery.toLowerCase())
        )
      : applicants
  );

  // ── Client-side time window recalculation ───────────────────────────────────
  // Server stamps is_within_start_window at page-load time, but it must update
  // live as the current time enters or leaves the start window.
  const clientWithinStartWindow = $derived.by(() => {
    if (session.status !== 'published') return session.is_within_start_window;
    const dateStr = session.date;
    const startStr = session.start_time;
    const endStr = session.end_time;
    if (!dateStr || !startStr) return session.is_within_start_window;

    // session.date may arrive as ISO datetime ("2026-05-14T16:00:00.000000Z")
    // or plain date ("2026-05-15") — extract just the date part.
    const datePart = String(dateStr).split('T')[0];
    const [y, m, d] = datePart.split('-').map(Number);
    if (Number.isNaN(y) || Number.isNaN(m) || Number.isNaN(d)) return session.is_within_start_window;
    const [sh, sm] = String(startStr).split(':').map(Number);
    const [eh, em] = endStr ? String(endStr).split(':').map(Number) : [23, 59];

    const sessionDate = new Date(y, m - 1, d);
    const windowStart = new Date(sessionDate);
    windowStart.setHours(sh ?? 0, (sm ?? 0) - 15, 0, 0); // 15 min grace before start
    const windowEnd = new Date(sessionDate);
    windowEnd.setHours(eh ?? 23, (em ?? 59) + 30, 0, 0); // 30 min grace after end

    return now >= windowStart && now <= windowEnd;
  });

  const clientPastEnd = $derived.by(() => {
    if (!session.end_time || !session.date) return session.is_past_end;
    const datePart = String(session.date).split('T')[0];
    const [y, m, d] = datePart.split('-').map(Number);
    if (Number.isNaN(y) || Number.isNaN(m) || Number.isNaN(d)) return session.is_past_end;
    const [eh, em] = String(session.end_time).split(':').map(Number);
    const endTime = new Date(y, m - 1, d, eh ?? 23, (em ?? 59) + 30, 0);
    return now > endTime;
  });

  // ── Derived session state ─────────────────────────────────────────────────
  const hasApplicants = $derived((stats.total ?? 0) > 0);
  const canStart = $derived(
    p.canStart &&
    hasApplicants &&
    session.status === 'published' &&
    (clientWithinStartWindow || p.canOverrideSchedule)
  );
  const canClose              = $derived(p.canClose && session.status === 'in_progress');
  const outsideStartWindow    = $derived(session.status === 'published' && hasApplicants && clientWithinStartWindow === false);
  const showOverrideHint      = $derived(outsideStartWindow && p.canOverrideSchedule);
  const isPastDate            = $derived(session.is_past_date === true);
  const noApplicants          = $derived(session.status === 'published' && !hasApplicants);
  const canMarkAttendance     = $derived(
    p.canMarkAttendance && session.status === 'in_progress'
  );
  const canLogSubmission      = $derived(p.canLogSubmission && session.status === 'in_progress');
  const canBulkSubmit         = $derived(
    p.canBulkSubmit && session.status === 'in_progress' && (stats.present_pending_submission ?? 0) > 0
  );
  
  const hasStragglers = $derived((stats.present_pending_submission ?? 0) > 0);
  const isPastEnd = $derived(session.is_past_end === true);
  const showImpendingClose = $derived(
    session.status === 'in_progress' && 
    isPastEnd && 
    !hasStragglers
  );
  const showStragglerWarning = $derived(
    session.status === 'in_progress' && 
    isPastEnd && 
    hasStragglers
  );

  // ── Helpers ───────────────────────────────────────────────────────────────
  function sessionStatusVariant(status) {
    if (status === 'draft')       return 'muted';
    if (status === 'published')   return 'success';
    if (status === 'in_progress') return 'warning';
    if (status === 'completed')   return 'outline';
    if (status === 'cancelled')   return 'danger';
    return 'outline';
  }

  function attendanceStatusVariant(status) {
    if (status === 'pending')                         return 'muted';
    if (status === 'present' || status === 'submitted') return 'success';
    if (status === 'absent')                          return 'danger';
    return 'outline';
  }

  function sessionStatusLabel(status) {
    const labels = {
      draft:       'Draft',
      published:   'Published (scheduled)',
      in_progress: 'In progress',
      completed:   'Completed',
      cancelled:   'Cancelled',
    };
    return labels[status] ?? status;
  }

  function formatTime(value) {
    if (value == null || value === '') return '—';
    const parts = String(value).split(':');
    const hours = parseInt(parts[0], 10) || 0;
    const mins  = parseInt(parts[1], 10) || 0;
    const h     = hours % 12 || 12;
    return `${h}:${String(mins).padStart(2, '0')} ${hours < 12 ? 'AM' : 'PM'}`;
  }

  function handleRosterError(err) {
    actionError = err?.message ?? err ?? 'Something went wrong.';
    setTimeout(() => (actionError = ''), 5000);
  }

  // ── QR Scanner ────────────────────────────────────────────────────────────
  function extractReference(decodedText) {
    const t     = String(decodedText ?? '').trim();
    const match = t.match(/\/(?:admission|consultation)\/([^/?#]+)/i);
    if (match) return match[1];
    if (/^APP-\d{4}-\d{5}$/.test(t)) return t;
    return t;
  }

  function handleQrScan(decodedText) {
    if (scanHandled) return;
    scanHandled = true;
    scanError   = '';
    const ref   = extractReference(decodedText);
    if (!ref) {
      scanError   = 'Invalid QR content.';
      scanHandled = false;
      return;
    }

    if (scanMode === 'attendance') {
      axios
        .post(`/proctor/sessions/${session.id}/scan-attendance`, { reference_number: ref })
        .then(() => { showScanner = false; scanHandled = false; scanError = ''; router.reload(); })
        .catch((err) => {
          scanError = err?.response?.data?.message ?? err?.message ?? 'Scan failed.';
          setTimeout(() => (scanError = ''), 5000);
          scanHandled = false;
        });
      return;
    }

    // submission mode — resolve applicant in this session
    const applicant = applicants.find(
      (a) => (a.reference_number ?? '').trim().toLowerCase() === ref.trim().toLowerCase()
    );
    if (!applicant) {
      scanError   = 'Applicant not in this session.';
      scanHandled = false;
      return;
    }
    axios
      .post(`/proctor/sessions/${session.id}/submission`, { applicant_id: applicant.id })
      .then(() => { showScanner = false; scanHandled = false; scanError = ''; router.reload(); })
      .catch((err) => {
        scanError = err?.response?.data?.message ?? err?.message ?? 'Submission failed.';
        setTimeout(() => (scanError = ''), 5000);
        scanHandled = false;
      });
  }

  function openScannerFor(mode) {
    scanMode    = mode;
    scanHandled = false;
    scanError   = '';
    showScanner = true;
  }

  function closeScanner() {
    showScanner = false;
    scanHandled = false;
    scanError   = '';
  }

  // ── Attendance / Submission actions ───────────────────────────────────────
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

  function removeApplicant(sessionApplicantId) {
    if (!confirm('Remove this applicant from the session?')) return;
    actionError = '';
    router.post(`/admin/exam-scheduling/${session.id}/remove-applicant`, { session_applicant_id: sessionApplicantId }, {
      onError: handleRosterError,
      onSuccess: () => router.reload(),
    });
  }

  function extendSession(minutes = 30) {
    actionError = '';
    router.post(`/proctor/sessions/${session.id}/extend`, { extension_minutes: minutes }, {
      onError: handleRosterError,
      onSuccess: () => router.reload(),
    });
  }

  function startSession() {
    actionError = '';
    router.post(`/proctor/sessions/${session.id}/start`, {}, {
      onError: handleRosterError,
      onSuccess: () => router.reload(),
    });
  }

  function closeSessionAction() {
    if (!confirm('Are you sure you want to close this session? This action cannot be undone.')) return;
    actionError = '';
    router.post(`/proctor/sessions/${session.id}/close`, {}, {
      onError: handleRosterError,
      onSuccess: () => router.reload(),
    });
  }
</script>

<div class="space-y-6">
  <!-- Back link -->
  <div class="flex items-center gap-4">
    <Link
      href={backHref}
      class="text-sm text-muted-foreground hover:text-foreground inline-flex items-center gap-1"
    >
      <ArrowLeft class="h-4 w-4" /> {backLabel}
    </Link>
  </div>

  {#if actionError}
    <div class="rounded-lg bg-destructive/10 p-4 text-sm text-destructive">
      {actionError}
    </div>
  {/if}

  {#if session.system_notes}
    <div class="rounded-lg border border-primary/20 bg-primary/5 p-4 flex items-start gap-3">
      <Info class="h-5 w-5 text-primary mt-0.5" />
      <div>
        <p class="text-sm font-medium text-primary">System Note</p>
        <p class="text-sm text-primary/80">{session.system_notes}</p>
      </div>
    </div>
  {/if}

  {#if showImpendingClose}
    <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 flex items-start gap-3 dark:border-amber-900/50 dark:bg-amber-900/10">
      <Clock class="h-5 w-5 text-amber-600 mt-0.5 dark:text-amber-400" />
      <div>
        <p class="text-sm font-medium text-amber-800 dark:text-amber-300">Impending Closure</p>
        <p class="text-sm text-amber-700 dark:text-amber-400">
          This session has passed its scheduled end time and all present applicants have submitted. 
          The system will automatically close this session shortly.
        </p>
      </div>
    </div>
  {/if}

  {#if showStragglerWarning}
    <div class="rounded-lg border border-destructive/20 bg-destructive/5 p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div class="flex items-start gap-3">
        <AlertTriangle class="h-5 w-5 text-destructive mt-0.5" />
        <div>
          <p class="text-sm font-medium text-destructive text-balance">Action Required: Session Time Ended</p>
          <p class="text-sm text-destructive/80 text-balance">
            {stats.present_pending_submission} applicant(s) are still pending submission. 
            The session will forcefully auto-close 30 minutes after the scheduled end time unless extended.
          </p>
        </div>
      </div>
      <Button variant="outline" size="sm" class="shrink-0 border-destructive/20 text-destructive hover:bg-destructive/10" onclick={() => extendSession(30)}>
        Extend by 30 mins
      </Button>
    </div>
  {/if}

  <!-- Session info card -->
  <div class="rounded-lg border border-border bg-card p-6">
    <h1 class="text-2xl font-bold">Session Examinees</h1>
    <dl class="mt-4 grid gap-3 sm:grid-cols-2">
      <div>
        <dt class="text-sm text-muted-foreground">Date</dt>
        <dd class="font-medium">{formatDate(session.date)}</dd>
      </div>
      <div>
        <dt class="text-sm text-muted-foreground">Time</dt>
        <dd class="font-medium">{formatTime(session.start_time)}{#if session.end_time} – {formatTime(session.end_time)}{/if}</dd>
      </div>
      <div>
        <dt class="text-sm text-muted-foreground">Room</dt>
        <dd class="font-medium">{session.room?.name ?? '—'}</dd>
      </div>
      <div>
        <dt class="text-sm text-muted-foreground">Status</dt>
        <dd>
          <Badge variant={sessionStatusVariant(session.status)}>{sessionStatusLabel(session.status)}</Badge>
        </dd>
      </div>
      {#if session.started_at}
        <div>
          <dt class="text-sm text-muted-foreground">Started</dt>
          <dd class="font-medium">{fmtDate(session.started_at, 'time')}</dd>
        </div>
      {/if}
      {#if session.closed_at}
        <div>
          <dt class="text-sm text-muted-foreground">Closed</dt>
          <dd class="font-medium">{fmtDate(session.closed_at, 'time')}</dd>
        </div>
      {/if}
    </dl>

    <!-- Start / Close session controls -->
    <div class="mt-4 flex flex-wrap gap-3">
      {#if noApplicants}
        <p class="text-sm text-muted-foreground">No applicants assigned to this session.</p>
      {:else if isPastDate && outsideStartWindow && !p.canOverrideSchedule}
        <p class="text-sm text-muted-foreground">Session date has passed. Only an admin can start this session.</p>
      {:else if isPastDate && showOverrideHint}
        <p class="text-sm text-amber-600 dark:text-amber-500">Session date has passed; you have admin override enabled.</p>
      {:else if outsideStartWindow && !p.canOverrideSchedule}
        <p class="text-sm text-muted-foreground">Outside scheduled time. Only an admin can start this session.</p>
      {:else if showOverrideHint}
        <p class="text-sm text-amber-600 dark:text-amber-500">Outside schedule; you have admin override enabled.</p>
      {/if}
      {#if canStart}
        <Button class="min-h-[44px]" onclick={startSession}>
          <Play class="h-4 w-4 mr-2" /> Start session
        </Button>
      {/if}
      {#if canClose}
        <Button class="min-h-[44px]" variant="outline" onclick={closeSessionAction}>
          <Square class="h-4 w-4 mr-2" /> Close session
        </Button>
      {/if}
    </div>
  </div>

  <!-- Analytics panel (test_admin+ only) -->
  {#if p.showAnalytics}
    <div class="rounded-lg border border-border bg-card p-6">
      <div class="flex items-center gap-2 mb-4">
        <BarChart2 class="h-5 w-5 text-primary" />
        <h2 class="text-lg font-semibold">Analytics</h2>
      </div>
      <div class="grid gap-4 sm:grid-cols-3">
        <div class="rounded-lg bg-muted/50 p-4 text-center">
          <p class="text-3xl font-bold text-foreground">{stats.total ?? 0}</p>
          <p class="text-sm text-muted-foreground mt-1">Total applicants</p>
        </div>
        <div class="rounded-lg bg-green-500/10 p-4 text-center">
          <p class="text-3xl font-bold text-green-600 dark:text-green-400">{stats.present ?? 0}</p>
          <p class="text-sm text-muted-foreground mt-1">Present</p>
        </div>
        <div class="rounded-lg bg-destructive/10 p-4 text-center">
          <p class="text-3xl font-bold text-destructive">{stats.absent ?? 0}</p>
          <p class="text-sm text-muted-foreground mt-1">Absent</p>
        </div>
        <div class="rounded-lg bg-amber-500/10 p-4 text-center">
          <p class="text-3xl font-bold text-amber-600 dark:text-amber-400">{stats.pending ?? 0}</p>
          <p class="text-sm text-muted-foreground mt-1">Pending attendance</p>
        </div>
        <div class="rounded-lg bg-primary/10 p-4 text-center">
          <p class="text-3xl font-bold text-primary">{stats.submitted ?? 0}</p>
          <p class="text-sm text-muted-foreground mt-1">Submitted</p>
        </div>
        <div class="rounded-lg bg-muted/50 p-4 text-center">
          <p class="text-3xl font-bold text-foreground">{stats.present_pending_submission ?? 0}</p>
          <p class="text-sm text-muted-foreground mt-1">Present, not submitted</p>
        </div>
      </div>
    </div>
  {:else}
    <!-- Compact stats bar for Proctors -->
    <div class="rounded-lg border border-border bg-card p-6">
      <h2 class="text-lg font-semibold">Stats</h2>
      <div class="mt-3 flex flex-wrap gap-4">
        <span class="text-sm text-muted-foreground">Total: <strong class="text-foreground">{stats.total ?? 0}</strong></span>
        <span class="text-sm text-muted-foreground">Present: <strong class="text-foreground">{stats.present ?? 0}</strong></span>
        <span class="text-sm text-muted-foreground">Absent: <strong class="text-foreground">{stats.absent ?? 0}</strong></span>
        <span class="text-sm text-muted-foreground">Pending: <strong class="text-foreground">{stats.pending ?? 0}</strong></span>
        <span class="text-sm text-muted-foreground">Submitted: <strong class="text-foreground">{stats.submitted ?? 0}</strong></span>
        {#if (stats.present_pending_submission ?? 0) > 0}
          <span class="text-sm text-muted-foreground">Present, not submitted: <strong class="text-foreground">{stats.present_pending_submission}</strong></span>
        {/if}
      </div>
    </div>
  {/if}

  <!-- Applicant table -->
  <div class="rounded-lg border border-border bg-card p-6">
    <h2 class="text-lg font-semibold">Applicants</h2>
    <p class="mt-1 text-sm text-muted-foreground">Mark attendance and log submission.</p>

    <div class="mt-4 flex flex-wrap items-center gap-3">
      <div class="relative max-w-xs">
        <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground pointer-events-none" />
        <Input
          type="search"
          class="pl-8 w-full"
          bind:value={searchQuery}
          aria-label="Search applicants"
        />
      </div>
      {#if canMarkAttendance}
        <Button variant="outline" class="min-h-[44px]" onclick={() => openScannerFor('attendance')}>
          <QrCode class="h-4 w-4 mr-2" /> Scan for attendance
        </Button>
      {/if}
      {#if canLogSubmission}
        <Button variant="outline" class="min-h-[44px]" onclick={() => openScannerFor('submission')}>
          <QrCode class="h-4 w-4 mr-2" /> Scan for submission
        </Button>
      {/if}
      {#if canBulkSubmit}
        <Button variant="outline" class="min-h-[44px]" onclick={logSubmissionBulk}>
          <FileCheck class="h-4 w-4 mr-2" /> Mark all present as submitted
        </Button>
      {/if}
    </div>

    {#if filteredApplicants.length > 0}
      <div class="mt-4 overflow-x-auto scrollbar-hide">
        <table class="w-full text-sm">
          <thead class="bg-muted/50">
            <tr>
              <th class="px-4 py-3 text-left font-medium">Applicant No.</th>
              <th class="px-4 py-3 text-left font-medium">Name</th>
              <th class="px-4 py-3 text-left font-medium">Attendance</th>
              <th class="px-4 py-3 text-left font-medium">Time in</th>
              <th class="px-4 py-3 text-left font-medium">Submission</th>
              <th class="px-4 py-3 text-left font-medium">Submitted at</th>
              <th class="px-4 py-3 text-right font-medium">Actions</th>
            </tr>
          </thead>
          <tbody>
            {#each filteredApplicants as row (row.id)}
              <tr class="border-t border-border hover:bg-muted/30">
                <td class="px-4 py-3">{row.reference_number ?? '—'}</td>
                <td class="px-4 py-3">{row.name ?? '—'}</td>
                <td class="px-4 py-3">
                  <Badge variant={attendanceStatusVariant(row.attendance_status)}>{row.attendance_status}</Badge>
                </td>
                <td class="px-4 py-3">
                  {#if row.attendance_marked_at}
                    <span>{fmtDate(row.attendance_marked_at, 'time')}</span>
                  {:else}
                    <span class="text-xs text-muted-foreground/50">—</span>
                  {/if}
                </td>
                <td class="px-4 py-3">
                  <Badge variant={attendanceStatusVariant(row.submission_status)}>{row.submission_status}</Badge>
                </td>
                <td class="px-4 py-3">
                  {#if row.submitted_at}
                    <span>{fmtDate(row.submitted_at, 'time')}</span>
                  {:else}
                    <span class="text-xs text-muted-foreground/50">—</span>
                  {/if}
                </td>
                <td class="px-4 py-3 text-right">
                  <div class="flex flex-wrap justify-end gap-1">
                    {#if row.attendance_status === 'pending' && canMarkAttendance}
                      <Button variant="outline" size="sm" class="min-h-[44px]" onclick={() => markPresent(row.id)}>
                        <UserCheck class="h-4 w-4 mr-1" /> Present
                      </Button>
                      <Button variant="outline" size="sm" class="min-h-[44px] text-destructive hover:text-destructive" onclick={() => markAbsent(row.id)}>
                        <UserX class="h-4 w-4 mr-1" /> Absent
                      </Button>
                    {:else if row.attendance_status === 'present' && row.submission_status === 'pending' && canLogSubmission}
                      <Button variant="outline" size="sm" class="min-h-[44px]" onclick={() => logSubmission(row.id)}>
                        <FileCheck class="h-4 w-4 mr-1" /> Log submission
                      </Button>
                    {/if}
                    {#if p.canRemoveApplicant && session.status !== 'completed' && session.status !== 'cancelled'}
                      <Button
                        variant="outline"
                        size="sm"
                        class="min-h-[44px] text-destructive hover:text-destructive border-destructive/20 hover:bg-destructive/10"
                        onclick={() => removeApplicant(row.session_applicant_id)}
                        title="Remove from session"
                      >
                        <UserMinus class="h-4 w-4 mr-1" /> Unassign
                      </Button>
                    {/if}
                  </div>
                </td>
              </tr>
            {/each}
          </tbody>
        </table>
      </div>
    {:else}
      <p class="mt-4 text-sm text-muted-foreground">
        {searchQuery.trim() ? 'No applicants match your search.' : 'No applicants assigned to this session.'}
      </p>
    {/if}
  </div>
</div>

<!-- QR Scanner overlay -->
{#if showScanner}
  <QrScanner
    title={scanMode === 'attendance' ? 'Scan for attendance' : 'Scan for submission'}
    onScan={handleQrScan}
    onClose={closeScanner}
  />
  {#if scanError}
    <div class="fixed bottom-4 left-4 right-4 z-[60] rounded-lg bg-destructive/90 px-4 py-3 text-sm text-destructive-foreground sm:left-auto sm:right-4 sm:max-w-sm">
      {scanError}
    </div>
  {/if}
{/if}
