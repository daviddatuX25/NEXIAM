<script>
  import { untrack } from 'svelte';
  import * as Dialog from '@/Components/ui/dialog';
  import { Badge } from '@/Components/ui/badge';
  import { Button } from '@/Components/ui/button';
  import AvatarBadge from '@/Components/kit/AvatarBadge.svelte';
  import SortDropdown from '@/Components/kit/SortDropdown.svelte';
  import { formatDate, formatTimeRange } from '@/lib/date-utils';
  import { sortRosterRows } from '@/lib/roster-sort';
  import { ArrowRightLeft, UserPlus, Search, Calendar, MapPin, Users } from 'lucide-svelte';

  let {
    open = $bindable(false),
    mode = $bindable('inbound'),
    session,
    roster = [],
    accommodateCandidates = [],
    transferTargetSessions = [],
    sessionRemainingCapacity = null,
    initialOutboundExaminee = null,
    initialOutboundExaminees = [],
    onSuccess = () => {},
  } = $props();

  let candidateSearch = $state('');
  let sourceFilter = $state('all');
  let inboundSort = $state('name');
  let inboundSortDir = $state('asc');
  let selectedInboundCodes = $state([]);

  let lookupCode = $state('');
  let lookupBusy = $state(false);
  let lookupResult = $state(null);
  let submitError = $state('');

  let outboundSearch = $state('');
  let outboundSort = $state('name');
  let outboundSortDir = $state('asc');
  let selectedOutboundCodes = $state([]);
  let selectedTargetSessionId = $state(null);

  let reasonCategory = $state('travel_distance');
  let reasonNotes = $state('');
  let submitBusy = $state(false);

  const sortOptions = [
    { value: 'name', label: 'Name' },
    { value: 'reference_number', label: 'Reference' },
    { value: 'current_session_code', label: 'Session' },
  ];

  const outboundSortOptions = [
    { value: 'name', label: 'Name' },
    { value: 'reference_number', label: 'Reference' },
    { value: 'seat_label', label: 'Seat' },
  ];

  const canReceiveInbound = $derived(
    ['published', 'in_progress'].includes(session?.status)
      && !session?.closed_at
      && (sessionRemainingCapacity === null || sessionRemainingCapacity > 0),
  );

  const transferableRoster = $derived(
    roster.filter((row) => {
      if (row.submitted) return false;
      if (session?.status === 'published') return row.attendance !== 'present' && row.attendance !== 'void';
      if (session?.status === 'in_progress') return !row.attendance || row.attendance === 'absent';
      if (session?.status === 'completed') return row.attendance === 'absent';
      return false;
    }),
  );

  const filteredTransferableRoster = $derived.by(() => {
    const query = outboundSearch.trim().toLocaleLowerCase();
    const filtered = transferableRoster.filter((row) => !query || [
      row.name,
      row.reference_number,
      row.seat_label,
    ].some((v) => String(v ?? '').toLocaleLowerCase().includes(query)));
    return sortRosterRows(filtered, outboundSort, outboundSortDir);
  });

  const scheduledOtherCount = $derived(
    accommodateCandidates.filter((c) => c.source_kind === 'scheduled_other').length,
  );
  const absentOtherCount = $derived(
    accommodateCandidates.filter((c) => c.source_kind === 'absent').length,
  );
  const unassignedCount = $derived(
    accommodateCandidates.filter((c) => c.source_kind === 'unassigned').length,
  );

  const filteredCandidates = $derived.by(() => {
    const query = candidateSearch.trim().toLocaleLowerCase();
    const filtered = accommodateCandidates.filter((c) => {
      const matchesSource = sourceFilter === 'all' || c.source_kind === sourceFilter;
      const matchesSearch = !query || [
        c.name,
        c.reference_code,
        c.current_session_code,
        c.current_session_room,
        c.source_label,
      ].some((val) => String(val ?? '').toLocaleLowerCase().includes(query));
      return matchesSource && matchesSearch;
    });
    return sortRosterRows(filtered, inboundSort, inboundSortDir);
  });

  const isAllInboundSelected = $derived(
    filteredCandidates.length > 0
      && filteredCandidates.every((c) => selectedInboundCodes.includes(c.reference_code)),
  );

  const isAllOutboundSelected = $derived(
    filteredTransferableRoster.length > 0
      && filteredTransferableRoster.every((r) => selectedOutboundCodes.includes(r.reference_number)),
  );

  const effectiveInboundCodes = $derived.by(() => {
    if (selectedInboundCodes.length > 0) return selectedInboundCodes;
    if (lookupResult && !lookupResult.has_prior_submission && (lookupResult.is_eligible ?? !lookupResult.already_in_session)) {
      return [lookupResult.reference_code];
    }
    return [];
  });

  const selectedTargetSession = $derived(
    transferTargetSessions.find((t) => Number(t.id) === Number(selectedTargetSessionId)) ?? null,
  );

  $effect(() => {
    const isOpen = open;
    const currentMode = mode;
    const singleExaminee = initialOutboundExaminee;
    const bulkExaminees = initialOutboundExaminees;
    if (isOpen) {
      untrack(() => {
        submitError = '';
        if (currentMode === 'outbound') {
          const preselected = bulkExaminees?.length > 0
            ? bulkExaminees.map((r) => r.reference_number).filter(Boolean)
            : singleExaminee?.reference_number
              ? [singleExaminee.reference_number]
              : transferableRoster[0]?.reference_number
                ? [transferableRoster[0].reference_number]
                : [];
          selectedOutboundCodes = preselected;
          selectedTargetSessionId = transferTargetSessions[0]?.id ?? null;
          const firstRow = transferableRoster.find((r) => preselected.includes(r.reference_number));
          reasonCategory = firstRow?.attendance === 'absent' ? 'tardiness_consideration' : 'travel_distance';
        } else {
          selectedOutboundCodes = [];
          if (selectedInboundCodes.length === 0 && !lookupResult && accommodateCandidates.length === 1) {
            toggleInboundCandidate(accommodateCandidates[0]);
          }
        }
      });
    }
  });

  export function reset() {
    candidateSearch = '';
    sourceFilter = 'all';
    selectedInboundCodes = [];
    lookupCode = '';
    lookupBusy = false;
    lookupResult = null;
    submitError = '';
    outboundSearch = '';
    selectedOutboundCodes = [];
    selectedTargetSessionId = transferTargetSessions[0]?.id ?? null;
    reasonCategory = 'travel_distance';
    reasonNotes = '';
    submitBusy = false;
  }

  function switchMode(nextMode) {
    mode = nextMode;
    submitError = '';
    reasonNotes = '';
    if (nextMode === 'outbound') {
      if (selectedOutboundCodes.length === 0 && transferableRoster[0]?.reference_number) {
        selectedOutboundCodes = [transferableRoster[0].reference_number];
      }
      selectedTargetSessionId = transferTargetSessions[0]?.id ?? null;
    }
  }

  function toggleInboundCandidate(candidate) {
    lookupResult = null;
    submitError = '';
    const code = candidate.reference_code;
    if (selectedInboundCodes.includes(code)) {
      selectedInboundCodes = selectedInboundCodes.filter((c) => c !== code);
    } else {
      selectedInboundCodes = [...selectedInboundCodes, code];
      if (selectedInboundCodes.length === 1) {
        reasonCategory = candidate.default_reason_category || 'travel_distance';
      }
    }
  }

  function toggleAllInbound() {
    const visibleCodes = filteredCandidates.map((c) => c.reference_code);
    if (isAllInboundSelected) {
      selectedInboundCodes = selectedInboundCodes.filter((c) => !visibleCodes.includes(c));
    } else {
      const merged = new Set([...selectedInboundCodes, ...visibleCodes]);
      selectedInboundCodes = [...merged];
      if (filteredCandidates[0]?.default_reason_category) {
        reasonCategory = filteredCandidates[0].default_reason_category;
      }
    }
  }

  function toggleOutboundRow(row) {
    const code = row.reference_number;
    if (selectedOutboundCodes.includes(code)) {
      selectedOutboundCodes = selectedOutboundCodes.filter((c) => c !== code);
    } else {
      selectedOutboundCodes = [...selectedOutboundCodes, code];
      if (selectedOutboundCodes.length === 1 && row.attendance === 'absent') {
        reasonCategory = 'tardiness_consideration';
      }
    }
  }

  function toggleAllOutbound() {
    const visibleCodes = filteredTransferableRoster.map((r) => r.reference_number);
    if (isAllOutboundSelected) {
      selectedOutboundCodes = selectedOutboundCodes.filter((c) => !visibleCodes.includes(c));
    } else {
      selectedOutboundCodes = [...new Set([...selectedOutboundCodes, ...visibleCodes])];
    }
  }

  function initialsAvatarClass(sex) {
    if (sex === 'male') return '[&>span]:!bg-sky-500/15 [&>span]:!text-sky-700 dark:[&>span]:!text-sky-300 [&>span]:!border-sky-500/25';
    if (sex === 'female') return '[&>span]:!bg-rose-500/15 [&>span]:!text-rose-700 dark:[&>span]:!text-rose-300 [&>span]:!border-rose-500/25';
    return '[&>span]:!bg-muted [&>span]:!text-muted-foreground [&>span]:!border-border';
  }

  function sourceBadgeClass(kind) {
    if (kind === 'absent') return 'border-amber-500/35 bg-amber-500/10 text-amber-800 dark:text-amber-300';
    if (kind === 'scheduled_other') return 'border-sky-500/35 bg-sky-500/10 text-sky-800 dark:text-sky-300';
    return 'border-emerald-500/35 bg-emerald-500/10 text-emerald-800 dark:text-emerald-300';
  }

  async function lookupApplicant() {
    const code = (lookupCode || candidateSearch).trim();
    if (!code) return;

    const exactPoolMatch = accommodateCandidates.find(
      (c) => String(c.reference_code).toLocaleLowerCase() === code.toLocaleLowerCase(),
    );
    if (exactPoolMatch) {
      if (!selectedInboundCodes.includes(exactPoolMatch.reference_code)) {
        toggleInboundCandidate(exactPoolMatch);
      }
      return;
    }

    lookupBusy = true;
    submitError = '';
    selectedInboundCodes = [];
    lookupResult = null;

    try {
      const response = await fetch(
        `/exams/sessions/${session.id}/monitoring/lookup-applicant?reference_code=${encodeURIComponent(code)}`,
        { headers: { Accept: 'application/json' } },
      );
      const data = await response.json();
      if (!response.ok) {
        throw new Error(data.message || 'Applicant not found.');
      }
      lookupResult = data;
      reasonCategory = data.attendance_outcome === 'absent' ? 'tardiness_consideration' : 'travel_distance';
    } catch (error) {
      submitError = error instanceof Error ? error.message : 'Network error.';
    } finally {
      lookupBusy = false;
    }
  }

  async function submitAccommodationRequest(targetSessionId, codes) {
    if (codes.length === 0 || reasonNotes.trim().length < 3) return;
    submitBusy = true;
    submitError = '';

    const payload = codes.length === 1
      ? {
          reference_code: codes[0],
          reason_category: reasonCategory,
          reason_notes: reasonNotes.trim(),
        }
      : {
          reference_codes: codes,
          reason_category: reasonCategory,
          reason_notes: reasonNotes.trim(),
        };

    try {
      const response = await fetch(`/exams/sessions/${targetSessionId}/monitoring/accommodate`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? ''),
          Accept: 'application/json',
        },
        body: JSON.stringify(payload),
      });
      const contentType = response.headers.get('content-type') ?? '';
      const data = contentType.includes('application/json') ? await response.json() : {};
      if (!response.ok) {
        throw new Error(
          data.message
            || Object.values(data.errors ?? {}).flat().join(' ')
            || 'Unable to complete transfer.',
        );
      }

      open = false;
      reset();
      onSuccess();
    } catch (error) {
      submitError = error instanceof Error ? error.message : 'Network error.';
    } finally {
      submitBusy = false;
    }
  }
</script>

<Dialog.Root
  bind:open
  onOpenChange={(nextOpen) => {
    open = nextOpen;
    if (!nextOpen) reset();
  }}
>
  <Dialog.Content class="max-h-[90dvh] overflow-y-auto sm:max-w-xl">
    <Dialog.Header>
      <Dialog.Title>
        {mode === 'outbound' ? `Transfer Out · ${session?.code ?? ''}` : 'Accommodate Walk-in / Transfer'}
      </Dialog.Title>
      <Dialog.Description class="text-xs">
        {mode === 'outbound'
          ? `Select examinees from ${session?.code} and choose an open destination room.`
          : `Select eligible applicants or look up a reference code to accommodate into ${session?.code}.`}
      </Dialog.Description>
    </Dialog.Header>

    <div class="space-y-3.5">
      <!-- Direction Tabs -->
      <div
        class="grid grid-cols-2 gap-1 rounded-xl border border-border/60 bg-muted/60 p-1 text-xs font-semibold"
        role="tablist"
        aria-label="Accommodation direction"
      >
        <button
          type="button"
          role="tab"
          aria-selected={mode === 'inbound'}
          class="flex items-center justify-center gap-1.5 rounded-lg px-2.5 py-1.5 transition-colors {mode === 'inbound' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'}"
          onclick={() => switchMode('inbound')}
        >
          <UserPlus class="size-3.5" />
          <span>Bring into {session?.code ?? 'Room'}</span>
          <span class="rounded-full bg-muted px-1.5 py-0.5 font-mono text-[10px]">{accommodateCandidates.length}</span>
        </button>
        <button
          type="button"
          role="tab"
          aria-selected={mode === 'outbound'}
          class="flex items-center justify-center gap-1.5 rounded-lg px-2.5 py-1.5 transition-colors {mode === 'outbound' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'}"
          onclick={() => switchMode('outbound')}
        >
          <ArrowRightLeft class="size-3.5" />
          <span>Transfer to Other Session</span>
          <span class="rounded-full bg-muted px-1.5 py-0.5 font-mono text-[10px]">{transferTargetSessions.length}</span>
        </button>
      </div>

      {#if mode === 'inbound'}
        {#if !canReceiveInbound}
          <div class="rounded-lg border border-amber-500/35 bg-amber-500/10 p-3 text-xs text-amber-900 dark:text-amber-200">
            Room is closed or at full capacity. Switch to <strong>Transfer to Other Session</strong> to move absent examinees out.
          </div>
        {:else}
          <div class="space-y-2.5">
            <!-- Search + Sort Toolbar -->
            <div class="flex flex-wrap items-center gap-2">
              <div class="relative min-w-[200px] flex-1">
                <Search class="pointer-events-none absolute left-2.5 top-1/2 size-3.5 -translate-y-1/2 text-muted-foreground" />
                <input
                  id="walkin-reference-code"
                  type="text"
                  class="h-9 w-full rounded-md border border-input bg-background pl-8 pr-2.5 text-xs font-mono"
                  bind:value={lookupCode}
                  oninput={(e) => { candidateSearch = e.currentTarget.value; }}
                  placeholder="Search name, reference code, or session…"
                  aria-label="Applicant reference code"
                  onkeydown={(event) => { if (event.key === 'Enter') void lookupApplicant(); }}
                  disabled={lookupBusy || submitBusy}
                />
              </div>
              <Button
                variant="outline"
                size="sm"
                class="h-9 px-3 text-xs"
                onclick={() => void lookupApplicant()}
                disabled={!(lookupCode || candidateSearch).trim() || lookupBusy || submitBusy}
              >
                {lookupBusy ? 'Searching...' : 'Search'}
              </Button>
              {#if accommodateCandidates.length > 1}
                <SortDropdown
                  options={sortOptions}
                  sort={inboundSort}
                  dir={inboundSortDir}
                  label="Sort candidates"
                  onchange={({ sort, dir }) => { inboundSort = sort; inboundSortDir = dir; }}
                />
              {/if}
            </div>

            {#if accommodateCandidates.length > 0}
              <!-- Compact Filter Pills + Select All -->
              <div class="flex flex-wrap items-center justify-between gap-2 text-xs">
                <div class="flex flex-wrap items-center gap-1" role="group" aria-label="Candidate source filter">
                  <button
                    type="button"
                    class="rounded-full border px-2.5 py-0.5 text-[11px] font-medium transition-colors {sourceFilter === 'all' ? 'border-primary bg-primary text-primary-foreground' : 'border-border bg-background text-muted-foreground hover:bg-muted'}"
                    onclick={() => (sourceFilter = 'all')}
                  >All ({accommodateCandidates.length})</button>
                  {#if scheduledOtherCount > 0}
                    <button
                      type="button"
                      class="rounded-full border px-2.5 py-0.5 text-[11px] font-medium transition-colors {sourceFilter === 'scheduled_other' ? 'border-primary bg-primary text-primary-foreground' : 'border-border bg-background text-muted-foreground hover:bg-muted'}"
                      onclick={() => (sourceFilter = 'scheduled_other')}
                    >Other Sessions ({scheduledOtherCount})</button>
                  {/if}
                  {#if absentOtherCount > 0}
                    <button
                      type="button"
                      class="rounded-full border px-2.5 py-0.5 text-[11px] font-medium transition-colors {sourceFilter === 'absent' ? 'border-primary bg-primary text-primary-foreground' : 'border-border bg-background text-muted-foreground hover:bg-muted'}"
                      onclick={() => (sourceFilter = 'absent')}
                    >Absent ({absentOtherCount})</button>
                  {/if}
                  {#if unassignedCount > 0}
                    <button
                      type="button"
                      class="rounded-full border px-2.5 py-0.5 text-[11px] font-medium transition-colors {sourceFilter === 'unassigned' ? 'border-primary bg-primary text-primary-foreground' : 'border-border bg-background text-muted-foreground hover:bg-muted'}"
                      onclick={() => (sourceFilter = 'unassigned')}
                    >Walk-in ({unassignedCount})</button>
                  {/if}
                </div>

                {#if filteredCandidates.length > 0}
                  <label class="inline-flex cursor-pointer items-center gap-1.5 text-[11px] font-medium text-muted-foreground hover:text-foreground">
                    <input
                      type="checkbox"
                      class="size-3.5 rounded accent-primary"
                      checked={isAllInboundSelected}
                      onchange={toggleAllInbound}
                    />
                    <span>Select all ({filteredCandidates.length})</span>
                  </label>
                {/if}
              </div>

              <!-- Multi-select Candidate List -->
              <div class="max-h-48 divide-y divide-border/60 overflow-y-auto rounded-xl border border-border bg-card" role="listbox" aria-label="Eligible applicants">
                {#each filteredCandidates as candidate (candidate.reference_code)}
                  {@const isSelected = selectedInboundCodes.includes(candidate.reference_code)}
                  <button
                    type="button"
                    role="option"
                    aria-selected={isSelected}
                    class="flex w-full items-center justify-between gap-2.5 px-3 py-2 text-left text-xs transition-colors {isSelected ? 'bg-primary/10' : 'hover:bg-muted/50'}"
                    onclick={() => toggleInboundCandidate(candidate)}
                  >
                    <div class="flex min-w-0 items-center gap-2.5">
                      <input
                        type="checkbox"
                        class="size-3.5 shrink-0 rounded accent-primary pointer-events-none"
                        checked={isSelected}
                        tabindex="-1"
                        readonly
                      />
                      <AvatarBadge name={candidate.name} size="sm" class={initialsAvatarClass(candidate.sex)} />
                      <div class="min-w-0">
                        <div class="truncate font-semibold text-foreground">{candidate.name}</div>
                        <div class="truncate font-mono text-[11px] text-muted-foreground">
                          {candidate.reference_code}{candidate.current_session_room ? ` · ${candidate.current_session_room}` : ''}
                        </div>
                      </div>
                    </div>
                    <span class="shrink-0 rounded-full border px-2 py-0.5 text-[10px] font-semibold {sourceBadgeClass(candidate.source_kind)}">
                      {candidate.source_label}
                    </span>
                  </button>
                {:else}
                  <div class="px-3 py-5 text-center text-xs text-muted-foreground">
                    No match in list. Click <strong>Search</strong> to look up by reference code.
                  </div>
                {/each}
              </div>
            {/if}
          </div>

          {#if lookupResult}
            <div class="rounded-xl border border-border bg-muted/30 p-3 text-xs">
              <div class="flex items-center justify-between">
                <span class="font-semibold">{lookupResult.name}</span>
                <span class="font-mono text-muted-foreground">{lookupResult.reference_code}</span>
              </div>
              {#if lookupResult.has_prior_submission || lookupResult.is_eligible === false}
                <div class="mt-2 rounded-md border border-destructive/40 bg-destructive/10 p-2 text-destructive" role="alert">
                  {lookupResult.ineligible_reason || 'Ineligible: Applicant has already submitted an exam paper in another session.'}
                </div>
              {/if}
            </div>
          {/if}

          {#if effectiveInboundCodes.length > 0}
            <div class="space-y-3 rounded-xl border border-border bg-muted/30 p-3.5">
              <div class="flex items-center justify-between text-xs">
                <span class="font-semibold text-foreground">
                  Selected: {effectiveInboundCodes.length} applicant{effectiveInboundCodes.length === 1 ? '' : 's'}
                </span>
                {#if sessionRemainingCapacity !== null}
                  <span class="font-mono text-[11px] text-muted-foreground">{sessionRemainingCapacity} seat{sessionRemainingCapacity === 1 ? '' : 's'} left</span>
                {/if}
              </div>

              <div class="grid gap-2.5 sm:grid-cols-2">
                <div class="space-y-1">
                  <label for="accommodation-reason-category" class="text-xs font-medium">Reason category</label>
                  <select
                    id="accommodation-reason-category"
                    class="h-9 w-full rounded-md border border-input bg-background px-2.5 text-xs"
                    bind:value={reasonCategory}
                    disabled={submitBusy}
                  >
                    <option value="travel_distance">Travel Distance / Early Arrival</option>
                    <option value="tardiness_consideration">Transport Delay / Tardy Consideration</option>
                    <option value="administrative_override">Administrative Override</option>
                  </select>
                </div>
                <div class="space-y-1 sm:col-span-2">
                  <label for="accommodation-reason-notes" class="text-xs font-medium">Reason notes</label>
                  <textarea
                    id="accommodation-reason-notes"
                    class="min-h-16 w-full rounded-md border border-input bg-background px-2.5 py-1.5 text-xs"
                    bind:value={reasonNotes}
                    minlength="3"
                    placeholder="Brief accommodation reason (min. 3 chars)…"
                    disabled={submitBusy}
                  ></textarea>
                </div>
              </div>
            </div>
          {/if}
        {/if}
      {:else}
        <!-- OUTBOUND MODE: Bulk / Custom Select Examinees & Target Session -->
        {#if transferableRoster.length === 0}
          <div class="rounded-xl border border-dashed border-border bg-muted/20 p-4 text-center text-xs text-muted-foreground">
            No transferable examinees in {session?.code} (Present, Void, and Submitted examinees cannot be moved).
          </div>
        {:else if transferTargetSessions.length === 0}
          <div class="rounded-xl border border-amber-500/35 bg-amber-500/10 p-3.5 text-xs text-amber-900 dark:text-amber-200">
            No other published or in-progress sessions with available seats in this cycle.
          </div>
        {:else}
          <div class="space-y-3">
            <!-- 1. Multi-select Examinees from Current Session -->
            <div class="space-y-2">
              <div class="flex flex-wrap items-center justify-between gap-2">
                <span class="text-xs font-semibold text-foreground">
                  Examinees in {session?.code} ({selectedOutboundCodes.length} selected)
                </span>
                <label class="inline-flex cursor-pointer items-center gap-1.5 text-[11px] font-medium text-muted-foreground hover:text-foreground">
                  <input
                    type="checkbox"
                    class="size-3.5 rounded accent-primary"
                    checked={isAllOutboundSelected}
                    onchange={toggleAllOutbound}
                  />
                  <span>Select all ({filteredTransferableRoster.length})</span>
                </label>
              </div>

              {#if transferableRoster.length > 3}
                <div class="flex items-center gap-2">
                  <div class="relative min-w-0 flex-1">
                    <Search class="pointer-events-none absolute left-2.5 top-1/2 size-3.5 -translate-y-1/2 text-muted-foreground" />
                    <input
                      type="text"
                      class="h-8 w-full rounded-md border border-input bg-background pl-8 pr-2.5 text-xs font-mono"
                      bind:value={outboundSearch}
                      placeholder="Filter examinees by name, reference, or seat…"
                      aria-label="Filter examinees to transfer"
                    />
                  </div>
                  <SortDropdown
                    options={outboundSortOptions}
                    sort={outboundSort}
                    dir={outboundSortDir}
                    label="Sort examinees"
                    onchange={({ sort, dir }) => { outboundSort = sort; outboundSortDir = dir; }}
                  />
                </div>
              {/if}

              <div class="max-h-36 divide-y divide-border/60 overflow-y-auto rounded-xl border border-border bg-card" role="listbox" aria-label="Examinees to transfer">
                {#each filteredTransferableRoster as row (row.id)}
                  {@const isChecked = selectedOutboundCodes.includes(row.reference_number)}
                  <button
                    type="button"
                    role="option"
                    aria-selected={isChecked}
                    class="flex w-full items-center justify-between gap-2.5 px-3 py-2 text-left text-xs transition-colors {isChecked ? 'bg-primary/10' : 'hover:bg-muted/50'}"
                    onclick={() => toggleOutboundRow(row)}
                  >
                    <div class="flex min-w-0 items-center gap-2.5">
                      <input
                        type="checkbox"
                        class="size-3.5 shrink-0 rounded accent-primary pointer-events-none"
                        checked={isChecked}
                        tabindex="-1"
                        readonly
                      />
                      <AvatarBadge name={row.name} size="sm" class={initialsAvatarClass(row.sex)} />
                      <div class="min-w-0">
                        <span class="font-semibold text-foreground">{row.name}</span>
                        <span class="ml-1.5 font-mono text-[11px] text-muted-foreground">{row.reference_number}</span>
                      </div>
                    </div>
                    <div class="flex shrink-0 items-center gap-1.5 font-mono text-[10px]">
                      {#if row.seat_label}
                        <Badge variant="outline" class="text-[10px]">{row.seat_label}</Badge>
                      {/if}
                      {#if row.attendance === 'absent'}
                        <Badge variant="secondary" class="text-[10px]">Absent</Badge>
                      {/if}
                    </div>
                  </button>
                {/each}
              </div>
            </div>

            <!-- 2. Destination Session -->
            <div class="space-y-1.5">
              <div class="text-xs font-semibold text-foreground">Destination Session</div>
              <div class="max-h-36 space-y-1.5 overflow-y-auto pr-0.5" role="radiogroup" aria-label="Destination exam session">
                {#each transferTargetSessions as target (target.id)}
                  {@const isSelectedTarget = Number(selectedTargetSessionId) === Number(target.id)}
                  <button
                    type="button"
                    role="radio"
                    aria-checked={isSelectedTarget}
                    class="flex w-full items-center justify-between gap-2.5 rounded-xl border px-3 py-2 text-left text-xs transition-colors {isSelectedTarget ? 'border-primary bg-primary/5 ring-1 ring-primary/40' : 'border-border bg-card hover:bg-muted/50'}"
                    onclick={() => (selectedTargetSessionId = target.id)}
                    disabled={submitBusy}
                  >
                    <div class="min-w-0">
                      <div class="flex items-center gap-2">
                        <span class="font-mono font-bold text-foreground">{target.code}</span>
                        <Badge variant={target.status === 'in_progress' ? 'default' : 'outline'} class="text-[10px]">
                          {target.status === 'in_progress' ? 'In progress' : 'Published'}
                        </Badge>
                        <span class="truncate text-[11px] text-muted-foreground">· {target.room_name}</span>
                      </div>
                      {#if target.date}
                        <div class="mt-0.5 text-[11px] text-muted-foreground">
                          {formatDate(target.date, 'short')}{target.start_time ? ` · ${formatTimeRange(target.start_time, target.end_time)}` : ''}
                        </div>
                      {/if}
                    </div>
                    <span class="shrink-0 rounded-md bg-emerald-500/10 px-2 py-0.5 font-mono text-[11px] font-bold text-emerald-700 dark:text-emerald-300">
                      {target.remaining_capacity} left
                    </span>
                  </button>
                {/each}
              </div>
            </div>

            <!-- 3. Reason -->
            <div class="space-y-2.5 rounded-xl border border-border bg-muted/30 p-3.5">
              <div class="space-y-1">
                <label for="outbound-reason-category" class="text-xs font-medium">Reason category</label>
                <select
                  id="outbound-reason-category"
                  class="h-9 w-full rounded-md border border-input bg-background px-2.5 text-xs"
                  bind:value={reasonCategory}
                  disabled={submitBusy}
                >
                  <option value="travel_distance">Travel Distance / Schedule Change</option>
                  <option value="tardiness_consideration">Transport Delay / Tardy Consideration</option>
                  <option value="administrative_override">Administrative Override</option>
                </select>
              </div>
              <div class="space-y-1">
                <label for="outbound-reason-notes" class="text-xs font-medium">Reason notes</label>
                <textarea
                  id="outbound-reason-notes"
                  class="min-h-16 w-full rounded-md border border-input bg-background px-2.5 py-1.5 text-xs"
                  bind:value={reasonNotes}
                  minlength="3"
                  placeholder="Brief transfer reason (min. 3 chars)…"
                  disabled={submitBusy}
                ></textarea>
              </div>
            </div>
          </div>
        {/if}
      {/if}

      {#if submitError}
        <div class="rounded-lg border border-destructive/40 bg-destructive/10 p-2.5 text-xs text-destructive" role="alert">
          {submitError}
        </div>
      {/if}
    </div>

    <Dialog.Footer>
      <Button variant="outline" size="sm" onclick={() => (open = false)} disabled={submitBusy}>Cancel</Button>
      {#if mode === 'inbound' && effectiveInboundCodes.length > 0}
        <Button
          size="sm"
          onclick={() => void submitAccommodationRequest(session.id, effectiveInboundCodes)}
          disabled={reasonNotes.trim().length < 3 || submitBusy || (sessionRemainingCapacity !== null && effectiveInboundCodes.length > sessionRemainingCapacity)}
        >
          {submitBusy
            ? 'Accommodating...'
            : effectiveInboundCodes.length === 1
              ? 'Accommodate into this Room'
              : `Accommodate (${effectiveInboundCodes.length}) into this Room`}
        </Button>
      {:else if mode === 'outbound' && selectedOutboundCodes.length > 0 && selectedTargetSession}
        <Button
          size="sm"
          onclick={() => void submitAccommodationRequest(selectedTargetSession.id, selectedOutboundCodes)}
          disabled={reasonNotes.trim().length < 3 || submitBusy || selectedOutboundCodes.length > selectedTargetSession.remaining_capacity}
        >
          {submitBusy
            ? 'Transferring...'
            : selectedOutboundCodes.length === 1
              ? `Transfer to ${selectedTargetSession.code}`
              : `Transfer (${selectedOutboundCodes.length}) to ${selectedTargetSession.code}`}
        </Button>
      {/if}
    </Dialog.Footer>
  </Dialog.Content>
</Dialog.Root>
