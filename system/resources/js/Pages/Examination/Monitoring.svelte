<script>
  /**
   * Per-session Exam Monitoring — the conduct phase of an exam session
   * (published / in-progress). Authorized Test Administrators may record
   * attendance for assigned applicants.
   *
   * Session view: connectivity and attendance outbox, QR check-in, spatial seating grid,
   * session lifecycle controls (start, extend +15m, close).
   */
  import { onMount, tick } from 'svelte';
  import {
    isOfflineStorageAvailable,
    listAttendanceCommands,
    queueAttendanceCommand,
    removeAttendanceCommand,
    updateAttendanceCommand,
  } from '@/lib/exam-monitoring-offline.js';
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
  import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
  import { Badge } from '@/Components/ui/badge';
  import { Button } from '@/Components/ui/button';
  import * as Dialog from '@/Components/ui/dialog';
  import * as Table from '@/Components/ui/table';
  import { Input } from '@/Components/ui/input';
  import SwitchableListView from '@/Components/kit/SwitchableListView.svelte';
  import ActionDropdown from '@/Components/kit/ActionDropdown.svelte';
  import AvatarBadge from '@/Components/kit/AvatarBadge.svelte';
  import SortableTableHead from '@/Components/kit/SortableTableHead.svelte';
  import SortDropdown from '@/Components/kit/SortDropdown.svelte';
  import BulkActionBar from '@/Components/kit/BulkActionBar.svelte';
  import { sortRosterRows, nextColumnSort, ariaSortDirection } from '@/lib/roster-sort';
  import { usePage, router } from '@inertiajs/svelte';
  import { newIdempotencyKey } from '@/lib/utils';
  import { formatDate, formatDateTime, formatTime, formatTimeRange } from '@/lib/date-utils';
  import { applySearchFocus } from '@/lib/search-focus';
  import { ArrowLeft, CheckCircle2, UserCheck, UserX, UserPlus, ArrowRightLeft, QrCode, Play, Clock, XCircle, Undo2, Grid3X3, List, Wifi, WifiOff, Calendar, AlertTriangle, Camera, BookOpen, Search, SlidersHorizontal, EllipsisVertical, FileCheck } from 'lucide-svelte';
  import { QrScanner } from '@/Components/qr';
  import SessionAccommodationModal from '@/Components/Examination/SessionAccommodationModal.svelte';
  import ApplicantActionsMenu from '@/Components/Admission/ApplicantActionsMenu.svelte';
  let {
    session,
    roster = [],
    focus_assignment_id = null,
    desks = [],
    can_manage = false,
    can_record = false,
    can_start = false,
    accommodate_candidates = [],
    transfer_target_sessions = [],
    session_remaining_capacity = null,
    is_physical_seating = false,
    has_multiple_sets = false,
    grandfathered_alternate_sets = false,
    page_state = 'ready',
    return_to = null,
    thresholds = {},
    offline_actor_id = null,
  } = $props();
  const focusedRosterRow = $derived(roster.find((row) => String(row.id) === String(focus_assignment_id)) ?? null);
  const hasFocusedApplicantActions = $derived(Boolean(focusedRosterRow?.attempt_id));
  const page = usePage();
  const actorId = $derived(offline_actor_id ?? page.props.auth?.user?.id ?? null);
  let busy = $state({});
  let conductMode = $state('roster');
  let listViewMode = $state('responsive');
  let searchQuery = $state('');
  let attendanceFilter = $state('all');
  let rosterSort = $state('');
  let rosterSortDir = $state('asc');
  let selectedRosterIds = $state([]);
  let showScanModal = $state(false);
  let scanInputMode = $state('camera');
  let scanResult = $state(null);
  let qrCodeInput = $state('');
  let scanBusy = $state(false);
  let isOnline = $state(typeof navigator !== 'undefined' ? navigator.onLine : true);
  let outboxAvailable = $state(isOfflineStorageAvailable());
  let outboxEntries = $state([]);
  let outboxError = $state('');
  let replayBusy = $state(false);
  let outboxScopeRevision = 0;
  let liveNow = $state(Date.now());
  let showCloseConfirm = $state(false);
  let closeBusy = $state(false);
  let closeError = $state('');
  let showStartConfirm = $state(false);
  let startDurationMinutes = $state(90);
  let startBusy = $state(false);
  let startError = $state('');
  let showRevertConfirm = $state(false);
  let revertBusy = $state(false);
  let revertError = $state('');
  const durationPresets = [
    { minutes: 30, label: '30m' },
    { minutes: 60, label: '1h' },
    { minutes: 90, label: '1h 30m' },
    { minutes: 120, label: '2h' },
    { minutes: 180, label: '3h' },
  ];
  const expectedEndPreview = $derived.by(() => {
    const mins = Math.max(15, Number(startDurationMinutes) || 90);
    return new Date(liveNow + mins * 60 * 1000).toISOString();
  });
  let showAccommodateModal = $state(false);
  let accommodationModalMode = $state('inbound');
  let initialOutboundExaminee = $state(null);
  let initialOutboundExaminees = $state([]);
  let showDistribution = $state(false);
  let distribution = $state(null);
  let distributionBusy = $state(false);
  let distributionError = $state('');
  let distributionRequest = null;
  let scanRequestInFlight = false;
  const canViewDistribution = $derived(
    page_state !== 'denied'
      && (['published', 'in_progress'].includes(session?.status) || (session?.status === 'completed' && can_manage))
      && (new Set(session?.material?.required_set_codes ?? []).size > 1 || has_multiple_sets),
  );
  const attendanceWindowOpen = $derived(
    can_record && session?.status === 'in_progress' && !session?.closed_at,
  );
  $effect(() => {
    session?.status;
    session?.closed_at;
    if (!attendanceWindowOpen) {
      showScanModal = false;
      showCloseConfirm = false;
      scanResult = null;
      qrCodeInput = '';
      scanBusy = false;
      scanRequestInFlight = false;
    }
  });

  function clearDistribution() {
    distributionRequest?.abort();
    distributionRequest = null;
    distribution = null;
    distributionBusy = false;
    distributionError = '';
  }

  function closeDistribution() {
    showDistribution = false;
    clearDistribution();
  }

  $effect(() => {
    session?.id;
    session?.material?.snapshot_id;
    session?.material?.revision;
    session?.status;
    conductMode;
    return closeDistribution;
  });

  $effect(() => {
    if (!showDistribution) clearDistribution();
  });

  async function openDistribution() {
    if (!canViewDistribution) return;
    clearDistribution();
    showDistribution = true;
    distributionBusy = true;
    const request = new AbortController();
    distributionRequest = request;
    const revision = session.material?.revision;
    const query = revision == null ? '' : `?expected_revision=${encodeURIComponent(revision)}`;
    try {
      const response = await fetch(`/exams/sessions/${session.id}/monitoring/distribution${query}`, {
        headers: { Accept: 'application/json' },
        cache: 'no-store',
        signal: request.signal,
      });
      const data = await response.json();
      if (!response.ok) throw new Error(data.message || 'Unable to load the booklet distribution list. Close and reopen it to try again.');
      if (request.signal.aborted) return;
      if (revision != null && data.revision !== revision) {
        throw new Error('The exam material snapshot has changed. Refresh monitoring before reopening the list.');
      }
      distribution = data;
    } catch (error) {
      if (!request.signal.aborted) {
        distributionError = error instanceof Error ? error.message : 'Unable to load the booklet distribution list.';
      }
    } finally {
      if (distributionRequest === request) {
        distributionBusy = false;
        distributionRequest = null;
      }
    }
  }

  function isOutboxScopeCurrent(requestActorId, requestSessionId, scopeRevision) {
    return outboxScopeRevision === scopeRevision
      && String(actorId) === String(requestActorId)
      && String(session?.id) === String(requestSessionId);
  }

  async function refreshOutbox(requestActorId = actorId, requestSessionId = session?.id) {
    if (requestActorId === null || requestActorId === undefined || requestSessionId === null || requestSessionId === undefined) return;
    const scopeRevision = outboxScopeRevision;
    try {
      const entries = await listAttendanceCommands(requestActorId, requestSessionId);
      if (!isOutboxScopeCurrent(requestActorId, requestSessionId, scopeRevision)) return;
      outboxEntries = entries;
      outboxAvailable = true;
    } catch (error) {
      if (!isOutboxScopeCurrent(requestActorId, requestSessionId, scopeRevision)) return;
      outboxAvailable = false;
      outboxError = error instanceof Error ? error.message : 'Offline attendance storage is unavailable.';
    }
  }

  function queuedAttendance(assignmentId) {
    return outboxEntries.find((entry) => String(entry.assignmentId) === String(assignmentId));
  }
  function queuedAttendanceLabel(entry) {
    const label = entry.outcome === 'void'
      ? 'Void / Walkout'
      : `${entry.outcome.slice(0, 1).toUpperCase()}${entry.outcome.slice(1)}`;
    return `${label} · ${entry.state === 'conflict' ? 'Conflict — review required' : 'Pending sync'}`;
  }

  async function replayAttendanceOutbox() {
    if (replayBusy || !isOnline || actorId == null) return;
    const replayActorId = actorId;
    const replaySessionId = session.id;
    const replayScopeRevision = outboxScopeRevision;
    replayBusy = true;
    let didReplay = false;
    let didConflict = false;
    try {
      if (session?.status !== 'in_progress' || session?.closed_at) {
        for (const entry of outboxEntries) {
          if (entry.state !== 'pending') continue;
          if (!isOutboxScopeCurrent(replayActorId, replaySessionId, replayScopeRevision)) break;
          await updateAttendanceCommand(entry, {
            state: 'conflict',
            conflictMessage: 'This session has closed; queued attendance was retained and not submitted.',
          });
          didConflict = true;
        }
      } else if (!can_record) {
        if (isOutboxScopeCurrent(replayActorId, replaySessionId, replayScopeRevision)) {
          outboxError = 'Replay paused: this account is not currently authorized to record attendance. Pending work is preserved.';
        }
      } else {
        for (const entry of outboxEntries) {
          if (
            entry.state !== 'pending' ||
            String(entry.actorId) !== String(replayActorId) ||
            String(entry.sessionId) !== String(replaySessionId) ||
            !navigator.onLine
          ) continue;
          if (!isOutboxScopeCurrent(replayActorId, replaySessionId, replayScopeRevision)) break;
          const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
          const xsrfToken = decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '');
          let response;
          try {
            response = await fetch(`/exams/sessions/${encodeURIComponent(replaySessionId)}/monitoring/offline-attendance`, {
              method: 'POST',
              credentials: 'same-origin',
              headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                ...(xsrfToken ? { 'X-XSRF-TOKEN': xsrfToken } : {}),
              },
              body: JSON.stringify({
                assignment_id: entry.assignmentId,
                expected_actor_id: entry.actorId,
                idempotency_key: entry.idempotencyKey,
                outcome: entry.outcome,
                expected_outcome: entry.expectedOutcome,
                expected_attendance_id: entry.expectedAttendanceId,
                expected_recorded_at: entry.expectedRecordedAt,
                expected_started_at: entry.expectedStartedAt,
                ...(entry.reason ? { reason: entry.reason } : {}),
              }),
            });
          } catch {
            if (isOutboxScopeCurrent(replayActorId, replaySessionId, replayScopeRevision)) {
              outboxError = 'Server unavailable. Attendance remains pending locally and will retry after reconnection.';
            }
            break;
          }
          if (!isOutboxScopeCurrent(replayActorId, replaySessionId, replayScopeRevision)) break;
          if ([401, 403, 419].includes(response.status)) {
            outboxError = response.status === 403
              ? 'Replay paused: this account is not authorized for this session. Pending attendance is preserved.'
              : 'Replay paused: sign in again to sync pending attendance. No queued attendance was removed.';
            break;
          }
          let payload = null;
          try {
            payload = await response.json();
          } catch {
            if (isOutboxScopeCurrent(replayActorId, replaySessionId, replayScopeRevision)) {
              outboxError = 'The server response could not be confirmed. Attendance remains pending locally.';
            }
            break;
          }
          if (!isOutboxScopeCurrent(replayActorId, replaySessionId, replayScopeRevision)) break;
          if ([400, 404, 409, 410, 422].includes(response.status) || (response.ok && payload?.status === 'conflict')) {
            await updateAttendanceCommand(entry, {
              state: 'conflict',
              conflictMessage: payload?.message ?? 'The server attendance changed while this station was offline.',
            });
            if (!isOutboxScopeCurrent(replayActorId, replaySessionId, replayScopeRevision)) break;
            didConflict = true;
            continue;
          }
          if (!response.ok) {
            outboxError = 'Server could not confirm queued attendance. The pending command was retained for retry.';
            break;
          }
          if (!['applied', 'already_applied'].includes(payload?.status)) {
            outboxError = 'The server returned an unrecognized attendance receipt. The pending command was retained.';
            break;
          }
          await removeAttendanceCommand(entry);
          if (!isOutboxScopeCurrent(replayActorId, replaySessionId, replayScopeRevision)) break;
          outboxError = '';
          didReplay = true;
        }
      }
      if (
        (didReplay || didConflict) &&
        isOutboxScopeCurrent(replayActorId, replaySessionId, replayScopeRevision)
      ) {
        await refreshOutbox(replayActorId, replaySessionId);
        if (isOutboxScopeCurrent(replayActorId, replaySessionId, replayScopeRevision)) {
          router.reload({ only: ['roster', 'desks', 'session'], preserveScroll: true, preserveState: true });
        }
      }
    } finally {
      replayBusy = false;
      const scopeStillCurrent = isOutboxScopeCurrent(replayActorId, replaySessionId, replayScopeRevision);
      await refreshOutbox();
      if (!scopeStillCurrent && isOnline) {
        void refreshOutbox().then(replayAttendanceOutbox);
      }
    }
  }

  $effect(() => {
    const scopedActorId = actorId;
    const scopedSessionId = session?.id;
    outboxScopeRevision += 1;
    const scopeRevision = outboxScopeRevision;
    outboxEntries = [];
    outboxError = '';
    void refreshOutbox(scopedActorId, scopedSessionId).then(() => {
      if (
        isOutboxScopeCurrent(scopedActorId, scopedSessionId, scopeRevision) &&
        isOnline
      ) {
        void replayAttendanceOutbox();
      }
    });
  });
  onMount(() => {
    if (focus_assignment_id !== null) {
      conductMode = 'roster';
      void tick().then(() => {
        const cardsVisible = listViewMode === 'cards'
          || (listViewMode === 'responsive' && window.matchMedia('(max-width: 767px)').matches);
        applySearchFocus(`${cardsVisible ? 'card' : 'table'}-monitoring-assignment-${focus_assignment_id}`);
      });
    }

    const onOffline = () => {
      isOnline = false;
      showScanModal = false;
      showAccommodateModal = false;
      closeDistribution();
    };
    const onOnline = () => {
      isOnline = true;
      void refreshOutbox().then(replayAttendanceOutbox);
    };
    window.addEventListener('online', onOnline);
    window.addEventListener('offline', onOffline);
    void refreshOutbox().then(replayAttendanceOutbox);
    const timer = setInterval(() => { liveNow = Date.now(); }, 1000);
    const sessionRefreshTimer = setInterval(() => {
      if (session?.status === 'in_progress' && isOnline && !document.hidden) {
        router.reload({
          only: ['session', 'can_record', 'can_start', 'roster', 'desks'],
          preserveScroll: true,
          preserveState: true,
        });
      }
    }, 15000);
    const stopNavigationListener = router.on('before', closeDistribution);
    const clearOnHidden = () => {
      if (document.hidden) closeDistribution();
    };
    document.addEventListener('visibilitychange', clearOnHidden);
    return () => {
      clearInterval(timer);
      clearInterval(sessionRefreshTimer);
      stopNavigationListener();
      document.removeEventListener('visibilitychange', clearOnHidden);
      window.removeEventListener('online', onOnline);
      window.removeEventListener('offline', onOffline);
      closeDistribution();
    };
  });

  const remainingMs = $derived.by(() => {
    if (!session?.effective_end_at) return null;
    const end = new Date(session.effective_end_at).getTime();
    return Math.max(0, end - liveNow);
  });

  const isOvertime = $derived.by(() => {
    if (!session?.effective_end_at) return false;
    return liveNow > new Date(session.effective_end_at).getTime();
  });

  const graceMinutes = $derived(Math.max(0, Number(thresholds?.auto_close_grace_minutes ?? 15)));
  const autoCloseAtMs = $derived.by(() => {
    if (!session?.effective_end_at) return null;
    return new Date(session.effective_end_at).getTime() + graceMinutes * 60 * 1000;
  });
  const graceRemainingMs = $derived.by(() => {
    if (autoCloseAtMs === null) return null;
    return Math.max(0, autoCloseAtMs - liveNow);
  });
  const formattedGraceRemaining = $derived.by(() => {
    if (graceRemainingMs === null) return '00:00';
    const totalSec = Math.floor(graceRemainingMs / 1000);
    const mins = Math.floor(totalSec / 60);
    const secs = totalSec % 60;
    return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
  });

  let lastAutoCloseReloadKey = $state('');
  $effect(() => {
    if (
      session?.status === 'in_progress' &&
      autoCloseAtMs !== null &&
      liveNow >= autoCloseAtMs &&
      isOnline
    ) {
      const key = `${session.id}:${session.version}:${autoCloseAtMs}`;
      if (lastAutoCloseReloadKey !== key) {
        lastAutoCloseReloadKey = key;
        router.reload({
          only: ['session', 'roster', 'desks', 'can_record', 'can_start'],
          preserveScroll: true,
          preserveState: true,
        });
      }
    }
  });

  const formattedRemaining = $derived.by(() => {
    if (remainingMs === null) return '—';
    const totalSec = Math.floor(remainingMs / 1000);
    const hours = Math.floor(totalSec / 3600);
    const mins = Math.floor((totalSec % 3600) / 60);
    const secs = totalSec % 60;
    return `${String(hours).padStart(2, '0')}:${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
  });
  const startsInMs = $derived.by(() => {
    if (!session?.starts_at) return null;
    return new Date(session.starts_at).getTime() - liveNow;
  });
  const formattedStartsIn = $derived.by(() => {
    if (startsInMs === null) return '—';
    if (startsInMs <= 0) return 'Scheduled time reached';
    const totalSec = Math.floor(startsInMs / 1000);
    const days = Math.floor(totalSec / 86400);
    const hours = Math.floor((totalSec % 86400) / 3600);
    const mins = Math.floor((totalSec % 3600) / 60);
    const secs = totalSec % 60;
    const clock = `${String(hours).padStart(2, '0')}:${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
    return days > 0 ? `${days}d ${clock}` : clock;
  });
  const unrecordedCount = $derived(roster.filter((r) => !r.attendance).length);
  const visibleRoster = $derived.by(() => {
    const query = searchQuery.trim().toLocaleLowerCase();
    const filtered = roster.filter((row) => {
      const matchesSearch = !query || [row.name, row.reference_number, row.seat_label]
        .some((value) => String(value ?? '').toLocaleLowerCase().includes(query));
      const matchesAttendance = attendanceFilter === 'all'
        || (attendanceFilter === 'unrecorded' && !row.attendance)
        || row.attendance === attendanceFilter;
      return matchesSearch && matchesAttendance;
    });
    return sortRosterRows(filtered, rosterSort, rosterSortDir);
  });
  const rosterSortOptions = $derived([
    { value: 'reference_number', label: 'Reference' },
    { value: 'name', label: 'Name' },
    ...(is_physical_seating ? [{ value: 'seat_label', label: 'Seat' }] : []),
    { value: 'attendance', label: 'Status' },
  ]);
  const isAllVisibleRosterSelected = $derived(
    visibleRoster.length > 0 && visibleRoster.every((r) => selectedRosterIds.includes(r.id)),
  );
  const isSomeVisibleRosterSelected = $derived(
    !isAllVisibleRosterSelected && visibleRoster.some((r) => selectedRosterIds.includes(r.id)),
  );
  const selectedRosterRows = $derived(
    roster.filter((r) => selectedRosterIds.includes(r.id)),
  );
  const transferableSelectedRoster = $derived(
    selectedRosterRows.filter((r) => canTransferExaminee(r)),
  );

  function confirmCloseRoom() {
    if (!isOnline || !session?.version) return;
    closeBusy = true;
    closeError = '';
    router.post(
      `/exams/sessions/${session.id}/close`,
      { expected_version: session.version },
      {
        preserveScroll: true,
        onError: (errs) => {
          closeError = errs?.close || 'Failed to close session.';
        },
        onSuccess: () => {
          showCloseConfirm = false;
        },
        onFinish: () => {
          closeBusy = false;
        },
      },
    );
  }

  function markAllUnrecordedAbsent() {
    if (!attendanceWindowOpen || (!isOnline && !outboxAvailable)) return;
    roster.filter((row) => !row.attendance).forEach((row) => {
      recordAttendance(row.id, 'absent');
    });
  }

  function setBusy(assignmentId, key, on) {
    busy = { ...busy, [`${assignmentId}:${key}`]: on };
  }

  function isBusy(assignmentId, key) {
    return busy[`${assignmentId}:${key}`] === true
      || (key === 'attendance' && (
        Boolean(queuedAttendance(assignmentId)) ||
        (!isOnline && !outboxAvailable)
      ));
  }

  function sendOnlineOnlyAttendance(assignmentId, outcome, idempotencyKey) {
    outboxError = 'Durable offline storage unavailable. Attendance is being sent online without offline protection.';
    router.post(
      `/exams/sessions/${session.id}/monitoring/assignments/${assignmentId}/attendance`,
      { outcome, idempotency_key: idempotencyKey },
      {
        preserveScroll: true,
        onFinish: () => setBusy(assignmentId, 'attendance', false),
      },
    );
  }

  async function recordAttendance(assignmentId, outcome) {
    if (!attendanceWindowOpen || queuedAttendance(assignmentId)) return;
    const row = roster.find((item) => String(item.id) === String(assignmentId));
    if (!row || actorId == null) return;
    const scopeActorId = actorId;
    const scopeSessionId = session.id;
    const scopeRevision = outboxScopeRevision;
    setBusy(assignmentId, 'attendance', true);
    const idempotencyKey = newIdempotencyKey();
    const command = {
      actorId: String(scopeActorId),
      sessionId: String(scopeSessionId),
      assignmentId: row.id,
      outcome,
      idempotencyKey,
      expectedOutcome: row.attendance ?? null,
      expectedAttendanceId: row.attendance_id ?? null,
      expectedRecordedAt: row.attendance_recorded_at ?? null,
      expectedStartedAt: session.started_at ?? null,
    };

    if (!outboxAvailable || !command.expectedStartedAt) {
      if (!isOnline) {
        outboxError = !outboxAvailable
          ? 'Durable offline storage is unavailable. Attendance was not saved.'
          : 'The server session start time is unavailable. Attendance cannot be queued offline.';
        setBusy(assignmentId, 'attendance', false);
        return;
      }
      sendOnlineOnlyAttendance(assignmentId, outcome, idempotencyKey);
      return;
    }

    try {
      const queued = await queueAttendanceCommand(command);
      if (!isOutboxScopeCurrent(scopeActorId, scopeSessionId, scopeRevision)) {
        setBusy(assignmentId, 'attendance', false);
        return;
      }
      outboxEntries = [...outboxEntries, queued];
      await refreshOutbox(scopeActorId, scopeSessionId);
      if (!isOutboxScopeCurrent(scopeActorId, scopeSessionId, scopeRevision)) {
        setBusy(assignmentId, 'attendance', false);
        return;
      }
      setBusy(assignmentId, 'attendance', false);
      if (isOnline) await replayAttendanceOutbox();
    } catch (error) {
      if (!isOutboxScopeCurrent(scopeActorId, scopeSessionId, scopeRevision)) {
        setBusy(assignmentId, 'attendance', false);
        return;
      }
      outboxAvailable = false;
      if (isOnline) {
        sendOnlineOnlyAttendance(assignmentId, outcome, idempotencyKey);
        return;
      }
      outboxError = error instanceof Error ? error.message : 'Attendance was not saved offline.';
      setBusy(assignmentId, 'attendance', false);
    }
  }
  async function dismissAttendanceConflict(assignmentId) {
    if (!isOnline) return;
    const command = queuedAttendance(assignmentId);
    if (!command || command.state !== 'conflict') return;
    await removeAttendanceCommand(command);
    await refreshOutbox();
  }

  function openStartConfirm() {
    startDurationMinutes = session?.scheduled_duration_minutes ?? 90;
    startError = '';
    showStartConfirm = true;
  }

  function startSession() {
    if (!isOnline || !session?.version) return;
    startBusy = true;
    startError = '';
    const mins = Math.max(15, Math.min(480, Number(startDurationMinutes) || (session?.scheduled_duration_minutes ?? 90)));
    router.post(
      `/exams/sessions/${session.id}/start`,
      { expected_version: session.version, duration_minutes: mins },
      {
        preserveScroll: true,
        onError: (errs) => {
          startError = errs?.start || errs?.duration_minutes || 'Failed to start session.';
        },
        onSuccess: () => {
          showStartConfirm = false;
        },
        onFinish: () => {
          startBusy = false;
        },
      },
    );
  }

  function extendSession() {
    if (!isOnline || !session?.version || !session?.effective_end_at) return;
    const baseTime = Math.max(Date.now(), new Date(session.effective_end_at).getTime());
    const newEnd = new Date(baseTime + 15 * 60 * 1000).toISOString();
    router.post(
      `/exams/sessions/${session.id}/extend`,
      { expected_version: session.version, effective_end_at: newEnd },
      { preserveScroll: true },
    );
  }
  const canRevertStart = $derived(
    attendanceWindowOpen
      && presentCount === 0
      && absentCount === 0
      && voidCount === 0
      && !roster.some((row) => row.submitted),
  );

  function confirmRevertStart() {
    if (!isOnline || !session?.version) return;
    revertBusy = true;
    revertError = '';
    router.post(
      `/exams/sessions/${session.id}/revert-start`,
      { expected_version: session.version },
      {
        preserveScroll: true,
        onError: (errs) => {
          revertError = errs?.revert_start || 'Failed to revert session start.';
        },
        onSuccess: () => {
          showRevertConfirm = false;
        },
        onFinish: () => {
          revertBusy = false;
        },
      },
    );
  }


  async function submitQrCheckin() {
    if (!isOnline || !attendanceWindowOpen || !qrCodeInput.trim() || scanRequestInFlight) return;
    scanRequestInFlight = true;
    scanBusy = true;
    scanResult = null;
    try {
      const response = await fetch(`/exams/sessions/${session.id}/monitoring/qr-checkin`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? ''),
          Accept: 'application/json',
        },
        body: JSON.stringify({
          qr_code: qrCodeInput.trim(),
          idempotency_key: newIdempotencyKey(),
        }),
      });
      const data = await response.json();
      const result = response.ok ? data : { status: 'error', message: data.message || 'Unable to verify this applicant.' };
      scanResult = result;
      if (['ok', 'already_present'].includes(data.status)) {
        qrCodeInput = '';
        router.reload({ only: ['roster', 'desks', 'session', 'can_record', 'can_start'] });
        window.setTimeout(() => {
          if (showScanModal && scanResult === result) scanResult = null;
        }, 2400);
      }
    } catch {
      scanResult = { status: 'error', message: 'Network error. Please try again.' };
    } finally {
      scanBusy = false;
      scanRequestInFlight = false;
    }
  }

  function openInboundAccommodation() {
    if (!isOnline) return;
    accommodationModalMode = 'inbound';
    initialOutboundExaminee = null;
    initialOutboundExaminees = [];
    showAccommodateModal = true;
  }

  function openOutboundTransfer(examinee) {
    if (!isOnline) return;
    accommodationModalMode = 'outbound';
    initialOutboundExaminee = examinee;
    initialOutboundExaminees = examinee ? [examinee] : [];
    showAccommodateModal = true;
  }

  function openBulkOutboundTransfer() {
    if (!isOnline || transferableSelectedRoster.length === 0) return;
    accommodationModalMode = 'outbound';
    initialOutboundExaminee = transferableSelectedRoster[0] ?? null;
    initialOutboundExaminees = [...transferableSelectedRoster];
    showAccommodateModal = true;
  }

  function handleRosterColumnSort(columnKey) {
    const next = nextColumnSort(rosterSort, rosterSortDir, columnKey);
    rosterSort = next.sort;
    rosterSortDir = next.dir;
  }

  function toggleRosterRow(id) {
    selectedRosterIds = selectedRosterIds.includes(id)
      ? selectedRosterIds.filter((x) => x !== id)
      : [...selectedRosterIds, id];
  }

  function toggleAllVisibleRoster() {
    const visibleIds = visibleRoster.map((r) => r.id);
    if (isAllVisibleRosterSelected) {
      selectedRosterIds = selectedRosterIds.filter((id) => !visibleIds.includes(id));
    } else {
      selectedRosterIds = [...new Set([...selectedRosterIds, ...visibleIds])];
    }
  }

  function bulkRecordSelectedAttendance(outcome) {
    if (!attendanceWindowOpen || (!isOnline && !outboxAvailable) || selectedRosterRows.length === 0) return;
    selectedRosterRows.forEach((row) => {
      recordAttendance(row.id, outcome);
    });
    selectedRosterIds = [];
  }
  function canTransferExaminee(row) {
    if (!can_manage || row.submitted) return false;
    if (session?.status === 'published') return row.attendance !== 'present' && row.attendance !== 'void';
    if (session?.status === 'in_progress') return !row.attendance || row.attendance === 'absent';
    if (session?.status === 'completed') return row.attendance === 'absent';
    return false;
  }

  function handleAccommodationSuccess() {
    selectedRosterIds = [];
    router.reload({
      only: [
        'roster',
        'desks',
        'session',
        'can_record',
        'can_start',
        'accommodate_candidates',
        'transfer_target_sessions',
        'session_remaining_capacity',
      ],
    });
  }


  function attendanceBadge(attendance) {
    if (attendance === 'present') {
      return { label: 'Present', variant: 'default' };
    }
    if (attendance === 'absent') {
      return { label: 'Absent', variant: 'secondary' };
    }
    if (attendance === 'void') {
      return { label: 'Void / Walkout', variant: 'destructive' };
    }
    return { label: 'Not recorded', variant: 'outline' };
  }

  function statusVariant(status) {
    if (status === 'published') return 'default';
    if (status === 'in_progress') return 'outline';
    return 'outline';
  }

  function statusLabel(status) {
    if (status === 'published') return 'Published';
    if (status === 'in_progress') return 'In progress';
    return status;
  }

  function deskColor(desk) {
    if (desk.attendance === 'void') return 'bg-amber-500/15 border-amber-500/40 text-amber-900 dark:text-amber-200';
    if (!desk.candidate) return 'bg-muted/30 border-border/40';

    if (desk.attendance === 'present') return 'bg-blue-500/10 border-blue-500/30';
    if (desk.attendance === 'absent') return 'bg-rose-500/10 border-rose-500/30';
    return 'bg-amber-500/10 border-amber-500/30';
  }


  const presentCount = $derived(roster.filter((r) => r.attendance === 'present').length);
  const absentCount = $derived(roster.filter((r) => r.attendance === 'absent').length);
  const voidCount = $derived(roster.filter((r) => r.attendance === 'void').length);
  const hasConflictCommands = $derived(
    outboxEntries.some((entry) => entry.state === 'conflict'),
  );
  const orphanConflictCommands = $derived(
    outboxEntries.filter(
      (entry) =>
        entry.state === 'conflict' &&
        !roster.some((row) => String(row.id) === String(entry.assignmentId)),
    ),
  );
  const attendanceFilters = $derived([
    { value: 'all', label: 'All', count: roster.length },
    { value: 'unrecorded', label: 'Unrecorded', count: unrecordedCount },
    { value: 'present', label: 'Present', count: presentCount },
    { value: 'absent', label: 'Absent', count: absentCount },
    { value: 'void', label: 'Void / Walkout', count: voidCount },
  ]);

  function initialsAvatarClass(sex) {
    if (sex === 'male') return '[&>span]:!bg-sky-500/15 [&>span]:!text-sky-700 dark:[&>span]:!text-sky-300 [&>span]:!border-sky-500/25';
    if (sex === 'female') return '[&>span]:!bg-rose-500/15 [&>span]:!text-rose-700 dark:[&>span]:!text-rose-300 [&>span]:!border-rose-500/25';
    return '[&>span]:!bg-muted [&>span]:!text-muted-foreground [&>span]:!border-border';
  }

  function rowActions(row) {
    const queued = queuedAttendance(row.id);
    return [
      ...(attendanceWindowOpen ? [{ label: 'Void / Walkout', danger: true, onclick: () => recordAttendance(row.id, 'void'), disabled: row.attendance === 'void' || isBusy(row.id, 'attendance') }] : []),
      ...(isOnline && canTransferExaminee(row) ? [{
        label: 'Transfer to Another Session',
        onclick: () => openOutboundTransfer(row),
      }] : []),
      ...(queued?.state === 'conflict' ? [{
        label: 'Dismiss queued command after reviewing attendance',
        danger: true,
        onclick: () => dismissAttendanceConflict(row.id),
        disabled: !isOnline,
      }] : []),
    ];
  }

  const breadcrumbs = [
    { label: 'Exam Monitoring', href: '/exams/monitoring' },
    { label: session?.code ?? 'Session' },
  ];
</script>

<svelte:head><title>{session?.code ?? 'Session'} · Monitoring</title></svelte:head>

<StaffLayout {breadcrumbs}>
  <div class="min-w-0 space-y-6 p-4 sm:p-6">
    <PageHeaderBar
      title={`${session.code} · Monitoring`}
      description="Record attendance for each assigned applicant. Close the room to proceed to scoring."
    >
      {#snippet actions()}
        <div class="flex flex-wrap items-center gap-2">
          {#if return_to}
            <Button variant="outline" onclick={() => router.visit(return_to)}>
              <ArrowLeft class="size-4" /> Return to OMR scan
            </Button>
          {/if}
          {#if canViewDistribution}
            <Button variant="outline" onclick={openDistribution} disabled={!isOnline} class="gap-1.5">
              <BookOpen class="size-4" aria-hidden="true" /> Booklet distribution list
            </Button>
          {/if}
          {#if session.status === 'in_progress' && session.effective_end_at}
            <div class="flex items-center gap-2 font-mono text-xs px-3 py-1.5 rounded-xl bg-muted/60 border border-border">
              <span class="text-[10px] text-muted-foreground uppercase font-bold">Time Left:</span>
              <span class="font-bold {isOvertime ? 'text-rose-500 animate-pulse' : 'text-foreground'}">
                {isOvertime ? `OVERTIME (${formattedGraceRemaining})` : formattedRemaining}
              </span>
            </div>
          {:else if session.status === 'published' && session.starts_at}
            <div class="flex items-center gap-2 font-mono text-xs px-3 py-1.5 rounded-xl bg-muted/60 border border-border">
              <span class="text-[10px] text-muted-foreground uppercase font-bold">Starts in:</span>
              <span class="font-bold text-foreground">
                {formattedStartsIn}
              </span>
            </div>
          {/if}
          <Badge variant={statusVariant(session.status)}>{statusLabel(session.status)}</Badge>
        </div>
      {/snippet}
    </PageHeaderBar>

    <div role="status" class="flex flex-wrap items-center justify-between gap-3 rounded-xl border px-4 py-2.5 font-mono text-xs {isOnline ? 'border-emerald-500/30 bg-emerald-500/5' : 'border-amber-500/30 bg-amber-500/5'}">
      <div class="flex flex-wrap items-center gap-2">
        {#if isOnline}
          <Wifi class="size-4 text-emerald-600 dark:text-emerald-400" />
          <span class="font-bold text-emerald-700 dark:text-emerald-300">Browser reports network available</span>
        {:else}
          <WifiOff class="size-4 text-amber-600 dark:text-amber-400" />
          <span class="font-bold text-amber-700 dark:text-amber-300">No network connection</span>
        {/if}
        {#if outboxAvailable}
          <Badge variant="secondary">{outboxEntries.filter((entry) => entry.state === 'pending').length} attendance pending sync</Badge>
          {#if isOnline && outboxEntries.some((entry) => entry.state === 'pending')}
            <Button size="sm" variant="outline" disabled={replayBusy || !attendanceWindowOpen} onclick={() => void replayAttendanceOutbox()}>
              Retry attendance sync
            </Button>
          {/if}
          {#if outboxEntries.some((entry) => entry.state === 'conflict')}
            <Badge variant="destructive">{outboxEntries.filter((entry) => entry.state === 'conflict').length} attendance conflict — review required</Badge>
          {/if}
        {:else}
          <Badge variant="destructive">Durable offline attendance unavailable</Badge>
        {/if}
        {#if replayBusy}<span class="text-muted-foreground">Syncing attendance…</span>{/if}
        {#if outboxError}<span class="text-rose-700 dark:text-rose-300" role="alert">{outboxError}</span>{/if}
      </div>
      <span class="text-muted-foreground">Network indicator reflects browser connectivity; server access may still fail.</span>
    </div>
    {#if !isOnline}
      <p role="alert" class="rounded-lg border border-amber-500/40 bg-amber-500/10 px-4 py-3 text-sm text-amber-900 dark:text-amber-200">
        Attendance Present, Absent, and Void actions can be saved to this browser only when durable storage is available. QR check-in, session changes, transfers, and server lookups are unavailable offline. Roster details are the last server response.
      </p>
    {/if}
    {#if orphanConflictCommands.length > 0}
      <section class="space-y-3 rounded-xl border border-destructive/40 bg-destructive/5 p-4" aria-label="Attendance conflict review">
        <h2 class="text-sm font-semibold">Conflicts for assignments no longer in this roster</h2>
        {#each orphanConflictCommands as command (command.idempotencyKey)}
          <div class="flex flex-wrap items-start justify-between gap-3 border-t border-destructive/20 pt-3">
            <div class="space-y-1 text-sm">
              <p class="font-medium">Assignment {command.assignmentId} · {queuedAttendanceLabel(command)}</p>
              <p class="text-xs text-muted-foreground">{command.conflictMessage}</p>
            </div>
            <Button size="sm" variant="outline" disabled={!isOnline} onclick={() => void dismissAttendanceConflict(command.assignmentId)}>
              Dismiss after review
            </Button>
          </div>
        {/each}
      </section>
    {/if}

    <!-- Session Info -->
    <div class="rounded-xl border border-border bg-card p-6">
      <dl class="grid gap-3 sm:grid-cols-2">
        <div>
          <dt class="text-xs text-muted-foreground font-medium">Date & Time</dt>
          <dd class="mt-0.5 space-y-0.5 font-sans">
            <div class="flex items-center gap-1.5 font-medium text-foreground text-sm">
              <Calendar class="size-4 text-primary shrink-0" />
              <span>{formatDate(session.date, 'long')}</span>
            </div>
            {#if session.start_time}
              <div class="flex items-center gap-1.5 text-xs text-muted-foreground font-mono">
                <Clock class="size-3.5 text-muted-foreground shrink-0" />
                <span>{session.started_at ? `Scheduled ${formatTime(session.start_time)}` : formatTimeRange(session.start_time, session.end_time)}</span>
              </div>
            {/if}
            {#if session.started_at || session.closed_at}
              <div class="space-y-0.5 border-t border-border/60 pt-1 text-xs font-mono text-muted-foreground">
                {#if session.started_at}<div>Started {formatDateTime(session.started_at)}</div>{/if}
                {#if session.status === 'in_progress' && session.effective_end_at}<div>Expected end {formatDateTime(session.effective_end_at)}</div>{/if}
                {#if session.closed_at}<div>Closed {formatDateTime(session.closed_at)}</div>{/if}
              </div>
            {/if}
          </dd>
        </div>
        <div>
          <dt class="text-sm text-muted-foreground">Room</dt>
          <dd class="font-medium">
            {session.room?.name ?? session.room?.code ?? '—'} (cap. {session.room?.capacity ?? '—'})
          </dd>
        </div>
        <div>
          <dt class="text-sm text-muted-foreground">Academic year</dt>
          <dd class="font-medium">{session.academic_cycle?.label ?? '—'}</dd>
        </div>
        <div>
          <dt class="text-sm text-muted-foreground">Assigned applicants</dt>
          <dd class="font-medium">{roster.length}</dd>
        </div>
      </dl>
    </div>

    <!-- Live KPIs -->
    {#if roster.length > 0}
      <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 text-xs font-mono">
        <div class="p-3 rounded-xl border border-border bg-card text-center">
          <div class="text-xl font-extrabold text-emerald-600 dark:text-emerald-400">{presentCount}</div>
          <div class="text-[10px] text-muted-foreground uppercase tracking-wider">Present</div>
        </div>
        <div class="p-3 rounded-xl border border-border bg-card text-center">
          <div class="text-xl font-extrabold text-rose-500">{absentCount}</div>
          <div class="text-[10px] text-muted-foreground uppercase tracking-wider">Absent</div>
        </div>
        <div class="p-3 rounded-xl border border-border bg-card text-center">
          <div class="text-xl font-extrabold text-amber-700 dark:text-amber-300">{voidCount}</div>
          <div class="text-[10px] text-muted-foreground uppercase tracking-wider">Void / Incomplete</div>
        </div>
      </div>
    {/if}

    {#if session.status === 'in_progress' && isOvertime}
      <div
        role="alert"
        class="flex flex-col gap-3 rounded-xl border border-amber-500/40 bg-amber-500/10 p-4 sm:flex-row sm:items-center sm:justify-between"
      >
        <div class="flex items-start gap-3">
          <AlertTriangle class="mt-0.5 size-5 shrink-0 text-amber-600 dark:text-amber-400" />
          <div class="space-y-1 text-sm">
            <div class="font-bold text-amber-900 dark:text-amber-200">
              Session Overtime — Auto-Close Grace Window Active
            </div>
            <p class="text-xs leading-relaxed text-amber-800 dark:text-amber-300">
              Expected end time ({formatDateTime(session.effective_end_at)}) has passed. Auto-closing in <span class="font-mono font-bold">{formattedGraceRemaining}</span> (at {autoCloseAtMs ? formatDateTime(new Date(autoCloseAtMs).toISOString()) : '—'}), which will mark any unrecorded examinees as Absent and seal the room for scoring unless you extend or close now.
            </p>
          </div>
        </div>
        {#if attendanceWindowOpen}
          <div class="flex shrink-0 flex-wrap items-center gap-2">
            <Button variant="outline" size="sm" class="gap-1.5" disabled={!isOnline} onclick={extendSession}>
              <Clock class="size-4" /> Extend +15m
            </Button>
            <Button variant="destructive" size="sm" class="gap-1.5" disabled={!isOnline} onclick={() => (showCloseConfirm = true)}>
              <XCircle class="size-4" /> Close Room Now
            </Button>
          </div>
        {/if}
      </div>
    {/if}

    <!-- Session Lifecycle + Action Buttons -->
    {#if can_start || attendanceWindowOpen || can_manage}
      <div class="flex flex-wrap items-center gap-2">
        {#if attendanceWindowOpen}
          <Button variant="outline" class="gap-1.5" disabled={!isOnline} onclick={() => (showScanModal = true)}>
            <QrCode class="size-4" /> Scan Examinee QR (Door)
          </Button>
        {/if}
        {#if can_start && session.status === 'published'}
          <Button class="gap-1.5" disabled={!isOnline} onclick={openStartConfirm}>
            <Play class="size-4" /> Start Session
          </Button>
        {/if}
        {#if can_manage && ['published', 'in_progress'].includes(session.status) && !session.closed_at}
          <Button variant="outline" class="gap-1.5" disabled={!isOnline} onclick={openInboundAccommodation}>
            <UserPlus class="size-4" /> Accommodate Walk-in / Transfer
            {#if accommodate_candidates.length > 0}
              <Badge variant="secondary" class="ml-0.5 px-1.5 py-0 font-mono text-[10px]">{accommodate_candidates.length}</Badge>
            {/if}
          </Button>
        {/if}
        {#if attendanceWindowOpen}
          <Button variant="outline" class="gap-1.5" disabled={!isOnline} onclick={extendSession}>
            <Clock class="size-4" /> Extend +15m
          </Button>
          {#if canRevertStart}
            <Button variant="outline" class="gap-1.5" disabled={!isOnline} onclick={() => { revertError = ''; showRevertConfirm = true; }}>
              <Undo2 class="size-4" /> Revert to Published
            </Button>
          {/if}
          <Button variant="destructive" class="gap-1.5" disabled={!isOnline} onclick={() => (showCloseConfirm = true)}>
            <XCircle class="size-4" /> Close Room
          </Button>
        {/if}
        {#if session.status === 'completed' && can_manage}
          <Button class="gap-1.5" disabled={!isOnline} onclick={() => router.visit(`/scoring/sessions/${session.id}`)}>
            <FileCheck class="size-4" /> Proceed to Scoring
          </Button>
        {/if}
      </div>
    {/if}

    {#if page_state === 'denied'}
      <p class="text-sm text-muted-foreground">You do not have permission to monitor this session.</p>
    {:else}
      <section class="min-w-0 space-y-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
          <div>
            <h2 class="text-lg font-semibold tracking-tight">Conduct roster</h2>
            <p class="mt-1 text-sm text-muted-foreground">
              {#if can_manage}
                Mark attendance for each applicant. Close the room to proceed to scoring.
              {:else}
                You can record attendance for this assigned session only.
              {/if}
            </p>
          </div>
          {#if desks.length > 0}
            <div class="inline-flex self-start rounded-lg border border-border bg-muted/40 p-1" role="group" aria-label="Conduct view">
              <button class="rounded-md px-3 py-1.5 text-xs font-medium {conductMode === 'roster' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground'}" onclick={() => (conductMode = 'roster')}>
                <List class="mr-1.5 inline size-3.5" /> Roster View
              </button>
              <button class="rounded-md px-3 py-1.5 text-xs font-medium {conductMode === 'spatial' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground'}" onclick={() => (conductMode = 'spatial')}>
                <Grid3X3 class="mr-1.5 inline size-3.5" /> Spatial Grid
              </button>
            </div>
          {/if}
        </div>

        <div class="flex flex-col gap-3 border-y border-border/60 py-3 lg:flex-row lg:items-center">
          <div class="relative min-w-0 flex-1">
            <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input bind:value={searchQuery} class="pl-9" placeholder="Search name, reference code, or seat…" aria-label="Search examinees" />
          </div>
          <div class="flex flex-wrap items-center gap-1.5" aria-label="Attendance filters">
            <SlidersHorizontal class="mr-1 size-4 text-muted-foreground" />
            {#each attendanceFilters as filter (filter.value)}
              <button
                class="rounded-full border px-3 py-1.5 text-xs font-medium transition-colors {attendanceFilter === filter.value ? 'border-primary bg-primary text-primary-foreground' : 'border-border bg-background text-muted-foreground hover:bg-muted'}"
                aria-pressed={attendanceFilter === filter.value}
                onclick={() => (attendanceFilter = filter.value)}
              >{filter.label}<span class="ml-1.5 opacity-75">{filter.count}</span></button>
            {/each}
          </div>
          <SortDropdown
            options={rosterSortOptions}
            sort={rosterSort || 'reference_number'}
            dir={rosterSortDir}
            label="Sort examinees"
            onchange={({ sort, dir }) => { rosterSort = sort; rosterSortDir = dir; }}
          />
        </div>

        <BulkActionBar count={selectedRosterIds.length} onClear={() => (selectedRosterIds = [])}>
          {#snippet actions()}
            {#if attendanceWindowOpen}
              <Button size="sm" variant="outline" class="h-8 gap-1.5 text-xs" disabled={!isOnline && !outboxAvailable} onclick={() => bulkRecordSelectedAttendance('present')}>
                <UserCheck class="size-3.5" /> Mark Present ({selectedRosterIds.length})
              </Button>
              <Button size="sm" variant="outline" class="h-8 gap-1.5 text-xs" disabled={!isOnline && !outboxAvailable} onclick={() => bulkRecordSelectedAttendance('absent')}>
                <UserX class="size-3.5" /> Mark Absent ({selectedRosterIds.length})
              </Button>
            {/if}
            {#if can_manage && transferableSelectedRoster.length > 0}
              <Button size="sm" class="h-8 gap-1.5 text-xs" disabled={!isOnline} onclick={openBulkOutboundTransfer}>
                <ArrowRightLeft class="size-3.5" /> Transfer Selected ({transferableSelectedRoster.length})
              </Button>
            {/if}
          {/snippet}
        </BulkActionBar>
        {#if conductMode === 'spatial' && desks.length > 0}
          <div class="space-y-4">
            <div class="flex items-center justify-center gap-2 rounded-xl border border-border/80 bg-muted/40 p-3 text-center text-xs font-bold uppercase tracking-wider text-muted-foreground">
              <span class="text-primary">▲</span> Whiteboard &amp; Test Administrator Desk (Front of Room) <span class="text-primary">▲</span>
            </div>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
              {#each desks as desk (desk.desk_id)}
                {@const candidateRow = visibleRoster.find((row) => String(row.id) === String(desk.candidate?.assignment_id))}
                <div class="flex flex-col justify-between space-y-2.5 rounded-xl border p-3.5 text-xs leading-tight transition-opacity {deskColor(desk)} {desk.candidate && (searchQuery.trim() || attendanceFilter !== 'all') && !candidateRow ? 'opacity-25' : 'opacity-100'}">
                  <span class="font-sans text-sm font-bold">{desk.seat_label}</span>
                  {#if desk.candidate}
                    <div class="flex min-w-0 items-center gap-2">
                      <span title={desk.candidate.sex === 'male' ? 'Male' : desk.candidate.sex === 'female' ? 'Female' : 'Unknown gender'}>
                        <AvatarBadge name={desk.candidate.name ?? 'Assigned'} size="sm" class={initialsAvatarClass(desk.candidate.sex)} />
                      </span>
                      <div class="min-w-0">
                        <div class="truncate font-sans font-semibold text-foreground">{desk.candidate.name ?? 'Assigned'}</div>
                        <div class="truncate text-[11px] text-muted-foreground">{desk.candidate.reference_number}</div>
                      </div>
                    </div>
                    <div class="flex items-center justify-between border-t border-border/50 pt-1.5 text-[11px]">
                      <Badge variant={attendanceBadge(desk.attendance).variant}>{attendanceBadge(desk.attendance).label}</Badge>
                    </div>
                    {#if attendanceWindowOpen}
                      <div class="flex items-center gap-1.5 font-sans">
                        <Button size="sm" class="h-7 flex-1 px-2 py-0 text-[11px]" onclick={() => recordAttendance(desk.candidate.assignment_id, 'present')} disabled={isBusy(desk.candidate.assignment_id, 'attendance')}>Present</Button>
                        <Button variant="outline" size="sm" class="h-7 flex-1 px-2 py-0 text-[11px]" onclick={() => recordAttendance(desk.candidate.assignment_id, 'absent')} disabled={isBusy(desk.candidate.assignment_id, 'attendance')}>Absent</Button>
                        <ActionDropdown triggerIcon={EllipsisVertical} items={[
                          { label: 'Void / Walkout', danger: true, onclick: () => recordAttendance(desk.candidate.assignment_id, 'void'), disabled: isBusy(desk.candidate.assignment_id, 'attendance') },
                          ...(canTransferExaminee({ submitted: desk.submitted, attendance: desk.attendance }) ? [{
                            label: 'Transfer to Another Session',
                            onclick: () => openOutboundTransfer({
                              id: desk.candidate.assignment_id,
                              reference_number: desk.candidate.reference_number,
                              name: desk.candidate.name,
                              seat_label: desk.seat_label,
                              attendance: desk.attendance,
                              submitted: desk.submitted,
                            }),
                          }] : []),
                        ]} />
                      </div>
                    {/if}
                  {:else}
                    <div class="my-auto py-4 text-center text-xs italic text-muted-foreground/50">Empty Desk</div>
                  {/if}
                </div>
              {/each}
            </div>
            <div class="flex flex-wrap gap-4 border-t border-border/50 pt-3 text-xs text-muted-foreground">
              <span>Present</span><span>Absent</span><span>Unrecorded</span><span>Empty</span>
            </div>
          </div>
        {:else if roster.length === 0}
          <p class="text-sm text-muted-foreground">No assigned applicants to monitor.</p>
        {:else}
          <SwitchableListView bind:viewMode={listViewMode} cardsInset={false}>
            {#snippet table()}
              <Table.Root class="w-full min-w-[760px] text-sm">
                <Table.Header class="border-b border-border bg-muted/40">
                  <Table.Row>
                    {#if can_manage || attendanceWindowOpen}
                      <Table.Head class="w-10">
                        {#if visibleRoster.length > 0}
                          <input
                            type="checkbox"
                            class="size-4 cursor-pointer rounded accent-primary"
                            checked={isAllVisibleRosterSelected}
                            indeterminate={isSomeVisibleRosterSelected}
                            onchange={toggleAllVisibleRoster}
                            aria-label="Select all visible examinees"
                          />
                        {/if}
                      </Table.Head>
                    {/if}
                    <SortableTableHead
                      label="Reference"
                      direction={ariaSortDirection(rosterSort, rosterSortDir, 'reference_number')}
                      onclick={() => handleRosterColumnSort('reference_number')}
                    />
                    <SortableTableHead
                      label="Name"
                      direction={ariaSortDirection(rosterSort, rosterSortDir, 'name')}
                      onclick={() => handleRosterColumnSort('name')}
                    />
                    {#if is_physical_seating}
                      <SortableTableHead
                        label="Seat"
                        direction={ariaSortDirection(rosterSort, rosterSortDir, 'seat_label')}
                        onclick={() => handleRosterColumnSort('seat_label')}
                      />
                    {/if}
                    <SortableTableHead
                      label="Status"
                      direction={ariaSortDirection(rosterSort, rosterSortDir, 'attendance')}
                      onclick={() => handleRosterColumnSort('attendance')}
                    />
                    {#if can_record || can_manage || hasConflictCommands || hasFocusedApplicantActions}<Table.Head class="text-right">Actions</Table.Head>{/if}
                  </Table.Row>
                </Table.Header>
                <Table.Body>
                  {#each visibleRoster as row (row.id)}
                    <Table.Row id={'table-monitoring-assignment-' + row.id} class={String(row.id) === String(focus_assignment_id) ? 'bg-primary/5 ring-2 ring-primary ring-offset-2' : ''}>
                      {#if can_manage || attendanceWindowOpen}
                        <Table.Cell class="w-10">
                          <input
                            type="checkbox"
                            class="size-4 cursor-pointer rounded accent-primary"
                            checked={selectedRosterIds.includes(row.id)}
                            onchange={() => toggleRosterRow(row.id)}
                            aria-label={`Select ${row.name}`}
                          />
                        </Table.Cell>
                      {/if}
                      <Table.Cell class="font-mono text-xs">{row.reference_number}</Table.Cell>
                      <Table.Cell>
                        <div class="flex items-center gap-2.5">
                          <span title={row.sex === 'male' ? 'Male' : row.sex === 'female' ? 'Female' : 'Unknown gender'}><AvatarBadge name={row.name} size="sm" class={initialsAvatarClass(row.sex)} /></span>
                          <span class="font-medium">{row.name}</span>
                        </div>
                      </Table.Cell>
                      {#if is_physical_seating}<Table.Cell class="font-mono text-xs">{row.seat_label ?? '—'}</Table.Cell>{/if}
                      <Table.Cell><Badge variant={attendanceBadge(row.attendance).variant}>{attendanceBadge(row.attendance).label}</Badge>{#if queuedAttendance(row.id)}<Badge class="ml-1" title={queuedAttendance(row.id).conflictMessage ?? undefined} variant={queuedAttendance(row.id).state === 'conflict' ? 'destructive' : 'secondary'}>{queuedAttendanceLabel(queuedAttendance(row.id))}</Badge>{#if queuedAttendance(row.id).state === 'conflict'}<span class="block max-w-xs text-[10px] text-destructive" role="alert">{queuedAttendance(row.id).conflictMessage}</span>{/if}{/if}</Table.Cell>
                      {#if can_record || can_manage || queuedAttendance(row.id)?.state === 'conflict' || (String(row.id) === String(focus_assignment_id) && row.attempt_id)}
                        <Table.Cell>
                          <div class="flex items-center justify-end gap-1">
                            {#if attendanceWindowOpen}
                              <Button variant="ghost" size="sm" class="h-8 px-2 text-xs" disabled={row.attendance === 'present' || isBusy(row.id, 'attendance')} onclick={() => recordAttendance(row.id, 'present')}><UserCheck class="mr-1 size-3.5" />Present</Button>
                              <Button variant="ghost" size="sm" class="h-8 px-2 text-xs" disabled={row.attendance === 'absent' || isBusy(row.id, 'attendance')} onclick={() => recordAttendance(row.id, 'absent')}><UserX class="mr-1 size-3.5" />Absent</Button>
                            {/if}
                            {#if String(row.id) === String(focus_assignment_id) && row.attempt_id}
                              <ApplicantActionsMenu attemptId={row.attempt_id} context={{ exam_session_id: session.id }} />
                            {/if}
                            {#if can_record || can_manage || queuedAttendance(row.id)?.state === 'conflict'}<ActionDropdown triggerIcon={EllipsisVertical} items={rowActions(row)} />{/if}
                          </div>
                        </Table.Cell>
                      {/if}
                    </Table.Row>
                  {:else}
                    <Table.Row><Table.Cell colspan={(can_manage || attendanceWindowOpen ? 1 : 0) + (is_physical_seating ? 3 : 2) + 1 + (can_record || can_manage || hasConflictCommands || hasFocusedApplicantActions ? 1 : 0)} class="py-10 text-center text-muted-foreground">No examinees match these filters.</Table.Cell></Table.Row>
                  {/each}
                </Table.Body>
              </Table.Root>
            {/snippet}
            {#snippet cards()}
              <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                {#each visibleRoster as row (row.id)}
                  <article id={'card-monitoring-assignment-' + row.id} class="space-y-3 rounded-xl border border-border bg-card p-4 {String(row.id) === String(focus_assignment_id) ? 'ring-2 ring-primary ring-offset-2' : ''}">
                    <div class="flex items-start justify-between gap-3">
                      <div class="flex min-w-0 items-center gap-2.5">
                        {#if can_manage || attendanceWindowOpen}
                          <input
                            type="checkbox"
                            class="size-4 shrink-0 cursor-pointer rounded accent-primary"
                            checked={selectedRosterIds.includes(row.id)}
                            onchange={() => toggleRosterRow(row.id)}
                            aria-label={`Select ${row.name}`}
                          />
                        {/if}
                        <span title={row.sex === 'male' ? 'Male' : row.sex === 'female' ? 'Female' : 'Unknown gender'}><AvatarBadge name={row.name} size="md" class={initialsAvatarClass(row.sex)} /></span>
                        <div class="min-w-0"><h3 class="truncate font-semibold">{row.name}</h3><p class="truncate font-mono text-xs text-muted-foreground">{row.reference_number}</p></div>
                      </div>
                      {#if String(row.id) === String(focus_assignment_id) && row.attempt_id}<ApplicantActionsMenu attemptId={row.attempt_id} context={{ exam_session_id: session.id }} />{/if}
                      {#if can_record || can_manage || queuedAttendance(row.id)?.state === 'conflict'}<ActionDropdown triggerIcon={EllipsisVertical} items={rowActions(row)} />{/if}
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-2 border-t border-border/60 pt-3">
                      <div class="space-y-1"><Badge variant={attendanceBadge(row.attendance).variant}>{attendanceBadge(row.attendance).label}</Badge>{#if queuedAttendance(row.id)}<Badge title={queuedAttendance(row.id).conflictMessage ?? undefined} variant={queuedAttendance(row.id).state === 'conflict' ? 'destructive' : 'secondary'}>{queuedAttendanceLabel(queuedAttendance(row.id))}</Badge>{#if queuedAttendance(row.id).state === 'conflict'}<p class="max-w-xs text-xs text-destructive" role="alert">{queuedAttendance(row.id).conflictMessage}</p>{/if}{/if}{#if is_physical_seating}<p class="text-xs text-muted-foreground">Seat: <span class="font-mono font-medium text-foreground">{row.seat_label ?? '—'}</span></p>{/if}</div>
                    </div>
                    {#if attendanceWindowOpen}<div class="flex gap-2">
                      <Button class="flex-1" size="sm" disabled={row.attendance === 'present' || isBusy(row.id, 'attendance')} onclick={() => recordAttendance(row.id, 'present')}><UserCheck class="mr-1.5 size-4" />Present</Button>
                      <Button class="flex-1" variant="outline" size="sm" disabled={row.attendance === 'absent' || isBusy(row.id, 'attendance')} onclick={() => recordAttendance(row.id, 'absent')}><UserX class="mr-1.5 size-4" />Absent</Button>
                    </div>{/if}
                  </article>
                {:else}
                  <p class="col-span-full py-8 text-center text-sm text-muted-foreground">No examinees match these filters.</p>
                {/each}
              </div>
            {/snippet}
          </SwitchableListView>
        {/if}
      </section>
    {/if}

  <!-- QR Door Check-in Modal -->
  <Dialog.Root open={showScanModal} onOpenChange={(open) => {
    showScanModal = open;
    if (!open) {
      scanResult = null;
      qrCodeInput = '';
    }
  }}>
    <Dialog.Content class="sm:max-w-lg">
      <Dialog.Header>
        <Dialog.Title>Door Check-in</Dialog.Title>
        <Dialog.Description>Verify each examinee's QR token or reference code. Successful check-ins rearm automatically for the next applicant.</Dialog.Description>
      </Dialog.Header>
      <div class="space-y-4">
        <div class="grid grid-cols-2 gap-1 rounded-xl border border-border/50 bg-muted/70 p-1 text-xs font-semibold" role="tablist" aria-label="Check-in method">
          <button type="button" role="tab" aria-selected={scanInputMode === 'camera'} class="rounded-lg px-3 py-2 {scanInputMode === 'camera' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground'}" onclick={() => (scanInputMode = 'camera')}><Camera class="mr-1.5 inline size-4" />Camera Scanner</button>
          <button type="button" role="tab" aria-selected={scanInputMode === 'manual'} class="rounded-lg px-3 py-2 {scanInputMode === 'manual' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground'}" onclick={() => (scanInputMode = 'manual')}><QrCode class="mr-1.5 inline size-4" />Manual Code</button>
        </div>
        {#if scanInputMode === 'camera'}
          <QrScanner active={showScanModal && scanInputMode === 'camera'} paused={scanBusy} onScan={(code) => {
            if (scanRequestInFlight) return;
            qrCodeInput = code;
            void submitQrCheckin();
          }} />
        {:else}
          <div class="space-y-2">
            <label for="door-checkin-code" class="text-sm font-medium">Reference code or QR token</label>
            <Input id="door-checkin-code" bind:value={qrCodeInput} class="font-mono" placeholder="APP-2026-00005 or paste QR token…" disabled={scanBusy} onkeydown={(event) => { if (event.key === 'Enter') void submitQrCheckin(); }} />
          </div>
        {/if}
        {#if scanResult}
          {#if scanResult.status === 'ok'}
            <div class="rounded-xl border border-emerald-500/35 bg-emerald-500/10 p-4 text-sm" role="status">
              <div class="font-bold text-emerald-800 dark:text-emerald-200">Check-in confirmed</div>
              <div class="mt-1 font-medium">{scanResult.candidate?.name}</div>
              <div class="font-mono text-xs text-muted-foreground">{scanResult.candidate?.reference_number}</div>
              <div class="mt-1 text-xs">Desk: {scanResult.candidate?.desk_label ?? 'No assigned desk'}</div>
            </div>
          {:else if scanResult.status === 'already_present'}
            <div class="rounded-xl border border-sky-500/35 bg-sky-500/10 p-4 text-sm" role="status">
              <div class="font-bold text-sky-800 dark:text-sky-200">Already Present</div>
              <div class="mt-1">{scanResult.candidate?.name} ({scanResult.candidate?.reference_number}) is already checked in.</div>
            </div>
          {:else if scanResult.status === 'mismatch'}
            <div class="rounded-xl border border-amber-500/35 bg-amber-500/10 p-4 text-sm" role="status">
              <div class="font-bold text-amber-800 dark:text-amber-200">Wrong Room</div>
              <div class="mt-1">Assigned to <strong>{scanResult.session_code}</strong> in <strong>{scanResult.room ?? 'an unlisted room'}</strong>.</div>
              {#if scanResult.starts_at}<div class="mt-1 text-xs">Session start: {new Date(scanResult.starts_at).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true })}</div>{/if}
            </div>
          {:else if scanResult.status === 'conflict'}
            <div class="rounded-xl border border-amber-500/35 bg-amber-500/10 p-4 text-sm" role="alert">
              <div class="font-bold text-amber-800 dark:text-amber-200">Attendance conflict</div>
              <div class="mt-1">{scanResult.candidate?.name} ({scanResult.candidate?.reference_number}) is already marked absent or void.</div>
              <div class="mt-1 text-xs">{scanResult.message}</div>
            </div>
          {:else}
            <div class="rounded-xl border border-rose-500/35 bg-rose-500/10 p-4 text-sm" role="alert">
              <div class="font-bold text-rose-800 dark:text-rose-200">Check-in error</div>
              <div class="mt-1">{scanResult.message}</div>
            </div>
          {/if}
        {/if}
      </div>
      <Dialog.Footer>
        <Button variant="outline" onclick={() => (showScanModal = false)}>Close</Button>
        {#if scanInputMode === 'manual'}
          <Button onclick={() => void submitQrCheckin()} disabled={!qrCodeInput.trim() || scanBusy}>{scanBusy ? 'Verifying…' : 'Check In'}</Button>
        {/if}
      </Dialog.Footer>
    </Dialog.Content>
  </Dialog.Root>

  <SessionAccommodationModal
    bind:open={showAccommodateModal}
    bind:mode={accommodationModalMode}
    {session}
    {roster}
    accommodateCandidates={accommodate_candidates}
    transferTargetSessions={transfer_target_sessions}
    sessionRemainingCapacity={session_remaining_capacity}
    {initialOutboundExaminee}
    {initialOutboundExaminees}
    onSuccess={handleAccommodationSuccess}
  />


  <!-- Start Session Confirmation Modal -->
  <Dialog.Root bind:open={showStartConfirm}>
    <Dialog.Content class="sm:max-w-md">
      <Dialog.Header>
        <Dialog.Title>Start Session · {session.code}</Dialog.Title>
        <Dialog.Description>
          Opens door check-in and attendance recording immediately, and sets the expected end time for this sitting and automated room closure.
        </Dialog.Description>
      </Dialog.Header>
      <div class="space-y-4 py-2 text-xs">
        <div class="rounded-xl border border-border bg-muted/40 p-3 space-y-1 font-mono">
          <div class="text-[10px] uppercase font-bold text-muted-foreground">Scheduled Slot</div>
          <div class="text-sm font-semibold text-foreground">{formatDate(session.date, 'long')} · {formatTimeRange(session.start_time, session.end_time)}</div>
          <div class="text-[11px] text-muted-foreground">Room: {session.room?.name ?? session.room?.code ?? '—'} · Scheduled length: {session.scheduled_duration_minutes ?? 90} min</div>
        </div>

        <div class="space-y-2">
          <label for="start-session-duration" class="block text-xs font-semibold text-foreground">Session duration (minutes)</label>
          <div class="flex flex-wrap gap-1.5">
            {#each durationPresets as preset (preset.minutes)}
              <button
                type="button"
                class="rounded-lg border px-2.5 py-1.5 font-mono text-xs font-medium transition-colors {Number(startDurationMinutes) === preset.minutes ? 'border-primary bg-primary text-primary-foreground' : 'border-border bg-background text-muted-foreground hover:bg-muted'}"
                onclick={() => (startDurationMinutes = preset.minutes)}
              >
                {preset.label}{preset.minutes === (session.scheduled_duration_minutes ?? 90) ? ' (Scheduled)' : ''}
              </button>
            {/each}
          </div>
          <Input
            id="start-session-duration"
            type="number"
            min="15"
            max="480"
            step="5"
            bind:value={startDurationMinutes}
            aria-label="Session duration (minutes)"
            class="font-mono"
          />
        </div>

        <div class="rounded-xl border border-primary/30 bg-primary/5 p-3 space-y-1 font-mono">
          <div class="flex justify-between">
            <span class="text-muted-foreground">Expected end:</span>
            <span class="font-bold text-foreground">{formatDateTime(expectedEndPreview)}</span>
          </div>
          <div class="text-[11px] text-muted-foreground font-sans">
            If the room is not manually closed after papers are collected, NEXIAM auto-closes {thresholds?.auto_close_grace_minutes ?? 15} minutes after the expected end. You can still use Extend +15m during conduct.
          </div>
        </div>

        {#if startError}
          <div class="rounded-xl border border-destructive/40 bg-destructive/10 p-3 text-destructive" role="alert">
            {startError}
          </div>
        {/if}
      </div>
      <Dialog.Footer class="flex gap-2">
        <Button variant="outline" onclick={() => (showStartConfirm = false)} disabled={startBusy}>Cancel</Button>
        <Button onclick={startSession} disabled={startBusy} class="gap-1.5 font-bold">
          <Play class="size-4" />
          {#if startBusy}Starting...{:else}Confirm & Start Session{/if}
        </Button>
      </Dialog.Footer>
    </Dialog.Content>
  </Dialog.Root>
  <!-- Revert Accidental Start Confirmation Modal -->
  <Dialog.Root bind:open={showRevertConfirm}>
    <Dialog.Content class="sm:max-w-md">
      <Dialog.Header>
        <Dialog.Title>Revert to Published · {session.code}</Dialog.Title>
        <Dialog.Description>
          No examinee attendance has been recorded yet. Reverting returns this session to Published and restores its scheduled time window so assigned examinees remain scheduled for the planned sitting.
        </Dialog.Description>
      </Dialog.Header>
      <div class="space-y-3 py-2 text-xs">
        <div class="rounded-xl border border-border bg-muted/40 p-3 space-y-1 font-mono">
          <div class="text-[10px] uppercase font-bold text-muted-foreground">Restored Schedule</div>
          <div class="text-sm font-semibold text-foreground">{formatDate(session.date, 'long')} · {formatTime(session.start_time)} ({session.scheduled_duration_minutes ?? 90} min)</div>
          <div class="text-[11px] text-muted-foreground">Assigned examinees: {roster.length} · Recorded attendance: 0</div>
        </div>
        {#if revertError}
          <div class="rounded-xl border border-destructive/40 bg-destructive/10 p-3 text-destructive" role="alert">
            {revertError}
          </div>
        {/if}
      </div>
      <Dialog.Footer class="flex gap-2">
        <Button variant="outline" onclick={() => (showRevertConfirm = false)} disabled={revertBusy}>Cancel</Button>
        <Button onclick={confirmRevertStart} disabled={revertBusy} class="gap-1.5 font-bold">
          <Undo2 class="size-4" />
          {#if revertBusy}Reverting...{:else}Confirm & Revert Start{/if}
        </Button>
      </Dialog.Footer>
    </Dialog.Content>
  </Dialog.Root>
  <!-- Close Room Confirmation Modal -->
  <Dialog.Root bind:open={showCloseConfirm}>
    <Dialog.Content class="sm:max-w-md">
      <Dialog.Header>
        <Dialog.Title>Close Room & Proceed to Scoring</Dialog.Title>
        <Dialog.Description>
          Closes the attendance roster and opens the Scoring Hub.
        </Dialog.Description>
      </Dialog.Header>
      <div class="space-y-4 py-2 text-xs font-mono">
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-center p-3 rounded-xl bg-muted/40 border border-border">
          <div><div class="text-[10px] text-muted-foreground uppercase font-bold">Present</div><div class="text-base font-bold text-emerald-500">{presentCount}</div></div>
          <div><div class="text-[10px] text-muted-foreground uppercase font-bold">Void / Incomplete</div><div class="text-base font-bold text-amber-600 dark:text-amber-300">{voidCount}</div></div>
          <div><div class="text-[10px] text-muted-foreground uppercase font-bold">Absent</div><div class="text-base font-bold text-rose-500">{absentCount}</div></div>
          <div><div class="text-[10px] text-muted-foreground uppercase font-bold">Unrecorded</div><div class="text-base font-bold {unrecordedCount > 0 ? 'text-amber-500 font-extrabold' : 'text-foreground'}">{unrecordedCount}</div></div>
        </div>

        {#if attendanceWindowOpen && unrecordedCount > 0}
          <div class="p-3 rounded-xl border border-amber-500/40 bg-amber-500/10 text-amber-600 dark:text-amber-400 space-y-2">
            <div class="flex items-center gap-2 font-bold">
              <AlertTriangle class="size-4 shrink-0" />
              <span>Cannot close room yet ({unrecordedCount} unrecorded)</span>
            </div>
            <p class="text-[11px] leading-relaxed">
              Every examinee must have attendance recorded before closing to derive the scoring roster. You can mark all unrecorded examinees as absent (no-show) below:
            </p>
            <Button
              variant="outline"
              size="sm"
              class="w-full text-xs font-bold border-amber-500/40 hover:bg-amber-500/20 text-amber-600 dark:text-amber-400"
              onclick={markAllUnrecordedAbsent}
            >
              Mark {unrecordedCount} Unrecorded as Absent (No-Show)
            </Button>
          </div>
        {:else if attendanceWindowOpen}
          <div class="p-3 rounded-xl border border-emerald-500/40 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center gap-2 text-xs">
            <CheckCircle2 class="size-4 shrink-0" />
            <span>All examinees accounted for. Ready to seal roster and proceed to scoring.</span>
          </div>
        {/if}

        {#if closeError}
          <div class="p-3 rounded-xl border border-destructive/40 bg-destructive/10 text-destructive text-xs">
            {closeError}
          </div>
        {/if}
      </div>

      <Dialog.Footer class="flex gap-2">
        <Button variant="outline" onclick={() => (showCloseConfirm = false)}>Cancel</Button>
        <Button
          variant="destructive"
          disabled={!attendanceWindowOpen || unrecordedCount > 0 || closeBusy}
          onclick={confirmCloseRoom}
          class="gap-1.5 font-bold"
        >
          {#if closeBusy}Closing...{:else}Confirm & Close Room{/if}
        </Button>
      </Dialog.Footer>
    </Dialog.Content>
  </Dialog.Root>
  <Dialog.Root bind:open={showDistribution}>
    <Dialog.Content class="max-h-[85dvh] overflow-y-auto sm:max-w-3xl">
      <Dialog.Header>
        <Dialog.Title>Booklet Distribution List</Dialog.Title>
        <Dialog.Description>
          Match each applicant to the Booklet Set frozen for this exam. This protected list is loaded only when opened.
        </Dialog.Description>
      </Dialog.Header>
      {#if grandfathered_alternate_sets}
        <p class="text-sm text-muted-foreground">Grandfathered: This exam was published with alternate Booklet Sets.</p>
      {/if}
      {#if distributionBusy}
        <p role="status" class="py-6 text-sm text-muted-foreground">Loading booklet distribution list…</p>
      {:else if distributionError}
        <p role="alert" class="text-sm text-destructive">{distributionError}</p>
      {:else if distribution}
        <div class="space-y-4">
          <p class="text-sm text-muted-foreground">
            Snapshot revision {distribution.revision} · Generated {new Date(distribution.generated_at).toLocaleString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true, month: 'short', day: 'numeric', year: 'numeric' })}
          </p>
          <dl class="flex flex-wrap gap-x-6 gap-y-2 text-sm" aria-label="Booklet Set counts">
            {#each Object.entries(distribution.sets_summary ?? {}) as [code, count]}
              <div class="flex gap-2">
                <dt class="font-medium">Set {code}:</dt>
                <dd class="tabular-nums">{count}</dd>
              </div>
            {/each}
          </dl>
          <div class="overflow-x-auto">
            <Table.Root>
              <Table.Header>
                <Table.Row>
                  <Table.Head>Applicant Reference</Table.Head>
                  <Table.Head>Name</Table.Head>
                  <Table.Head>Assigned Set Code</Table.Head>
                </Table.Row>
              </Table.Header>
              <Table.Body>
                {#each distribution.roster ?? [] as candidate}
                  <Table.Row>
                    <Table.Cell>{candidate.applicant_reference ?? '—'}</Table.Cell>
                    <Table.Cell class="font-medium">{candidate.applicant_name}</Table.Cell>
                    <Table.Cell>{candidate.set_code}</Table.Cell>
                  </Table.Row>
                {:else}
                  <Table.Row><Table.Cell colspan={3} class="text-muted-foreground">No candidates assigned.</Table.Cell></Table.Row>
                {/each}
              </Table.Body>
            </Table.Root>
          </div>
        </div>
      {/if}
      <Dialog.Footer>
        <Button variant="outline" onclick={closeDistribution}>Close</Button>
      </Dialog.Footer>
    </Dialog.Content>
  </Dialog.Root>
  </div>
</StaffLayout>
