<script>
  import * as Dialog from '@/Components/ui/dialog';
  import { untrack } from 'svelte';
  import { Button } from '@/Components/ui/button';
  import { router } from '@inertiajs/svelte';
  import { formatDate, formatTime, formatTimeRange } from '@/lib/date-utils.js';
  import {
    Sparkles,
    Check,
    AlertCircle,
    RotateCcw,
    Loader2,
    X,
    Clock,
    Type,
    GraduationCap,
    Zap,
    Users,
  } from 'lucide-svelte';

  let {
    open = $bindable(false),
    domain = 'exam', // 'exam' | 'consultation'
    academicCycleId = null,
    academicCycleName = 'Current Academic Cycle',
    cycles = [],
    sessions = [],
    distribution = {},
    onSuccess = null,
  } = $props();

  let sortStrategy = $state('fcfs');
  let packingStrategy = $state('sequential');
  let autoAssignEnabled = $state(false);
  let showOptions = $state(false);
  let candidateDrawerOpen = $state(false);
  let candidateTab = $state('all');
  let candidateSearch = $state('');

  let selectedSessionIds = $state([]);
  let previewLoading = $state(false);
  let commitLoading = $state(false);
  let previewData = $state(null);
  let errorMessage = $state('');
  let stalePlan = $state(false);
  let committedResult = $state(null);

  function getXsrfToken() {
    if (typeof document === 'undefined') return '';
    const match = document.cookie.match(new RegExp('(^|;\\s*)XSRF-TOKEN=([^;]+)'));
    return match ? decodeURIComponent(match[2]) : '';
  }

  let resolvedCycleName = $derived.by(() => {
    if (cycles && cycles.length > 0 && academicCycleId != null) {
      const match = cycles.find((c) => String(c.value ?? c.id) === String(academicCycleId));
      if (match) {
        return match.label ?? match.name ?? match.title ?? match.code ?? academicCycleName;
      }
    }
    return academicCycleName;
  });

  // Filter ONLY available/eligible sessions, sorted chronologically
  let eligibleSessions = $derived(
    sessions
      .filter((s) => {
        const sessionCycleId = s.academic_cycle_id ?? s.academic_cycle?.id;
        const sameCycle = academicCycleId && sessionCycleId ? Number(sessionCycleId) === Number(academicCycleId) : true;
        const isDraft = s.status === 'draft';
        const isStaffOnly = s.booking_mode === 'staff_only';
        const roomCapacity = Number(s.room?.capacity ?? s.capacity ?? 0);
        const activeCount = Number(s.assigned_count ?? s.active_count ?? 0);
        const hasRoom = Boolean(s.room_id || s.room);

        return sameCycle && isDraft && isStaffOnly && hasRoom && (roomCapacity - activeCount > 0);
      })
      .map((s) => {
        const roomCapacity = Number(s.room?.capacity ?? s.capacity ?? 0);
        const activeCount = Number(s.assigned_count ?? s.active_count ?? 0);
        return {
          id: Number(s.id),
          code: s.code,
          date: s.date ?? s.starts_at ?? s.scheduled_at,
          time: s.time,
          start_time: s.start_time,
          end_time: s.end_time,
          room_name: s.room?.name ?? 'Room',
          room_capacity: roomCapacity,
          active_count: activeCount,
          remaining_capacity: Math.max(0, roomCapacity - activeCount),
          sortKey: s.starts_at ?? s.scheduled_at ?? '',
        };
      })
      .sort((a, b) => a.sortKey.localeCompare(b.sortKey) || a.id - b.id)
  );

  // Initialize selected session IDs on open without reactive dependency loop
  let wasOpen = false;
  $effect(() => {
    const isNowOpen = open;
    if (isNowOpen && !wasOpen) {
      wasOpen = true;
      untrack(() => {
        committedResult = null;
        errorMessage = '';
        stalePlan = false;
        showOptions = false;
        candidateDrawerOpen = false;
        candidateSearch = '';
        candidateTab = 'all';
        const ids = eligibleSessions.map((s) => s.id);
        selectedSessionIds = ids;
        if (ids.length > 0 && academicCycleId) {
          fetchPreview();
        } else {
          previewData = null;
        }
      });
    } else if (!isNowOpen && wasOpen) {
      wasOpen = false;
    }
  });

  function toggleSession(id) {
    if (selectedSessionIds.includes(id)) {
      selectedSessionIds = selectedSessionIds.filter((item) => item !== id);
    } else {
      selectedSessionIds = [...selectedSessionIds, id];
    }
    fetchPreview();
  }

  function toggleAll(checked) {
    if (checked) {
      selectedSessionIds = eligibleSessions.map((s) => s.id);
    } else {
      selectedSessionIds = [];
    }
    fetchPreview();
  }

  async function fetchPreview() {
    if (selectedSessionIds.length === 0 || !academicCycleId) {
      previewData = null;
      return;
    }

    previewLoading = true;
    errorMessage = '';
    stalePlan = false;

    try {
      const endpoint = domain === 'exam'
        ? '/exams/sessions/auto-distribute/preview'
        : '/consultation/sessions/auto-distribute/preview';

      const response = await fetch(endpoint, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-XSRF-TOKEN': getXsrfToken(),
        },
        body: JSON.stringify({
          academic_cycle_id: Number(academicCycleId),
          target_session_ids: selectedSessionIds,
          sort_strategy: sortStrategy,
          packing_strategy: packingStrategy,
        }),
      });

      if (!response.ok) {
        const errorData = await response.json();
        throw new Error(errorData.message || 'Failed to generate preview.');
      }

      previewData = await response.json();
    } catch (e) {
      errorMessage = e.message;
      previewData = null;
    } finally {
      previewLoading = false;
    }
  }

  async function executeCommit() {
    if (!previewData || commitLoading) return;

    commitLoading = true;
    errorMessage = '';
    stalePlan = false;

    try {
      const endpoint = domain === 'exam'
        ? '/exams/sessions/auto-distribute/commit'
        : '/consultation/sessions/auto-distribute/commit';

      const expectedVersions = {};
      for (const mapping of (previewData.session_mappings ?? [])) {
        expectedVersions[mapping.session_id] = mapping.version;
      }

      const response = await fetch(endpoint, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-XSRF-TOKEN': getXsrfToken(),
        },
        body: JSON.stringify({
          academic_cycle_id: Number(academicCycleId),
          target_session_ids: selectedSessionIds,
          sort_strategy: sortStrategy,
          packing_strategy: packingStrategy,
          expected_plan_hash: previewData.plan_hash,
          expected_session_versions: expectedVersions,
          auto_assign_enabled: autoAssignEnabled,
        }),
      });

      if (response.status === 409) {
        const errorData = await response.json();
        stalePlan = true;
        errorMessage = errorData.message || 'The plan is stale because sessions or candidates changed.';
        return;
      }

      if (!response.ok) {
        const errorData = await response.json();
        throw new Error(errorData.message || 'Failed to execute distribution.');
      }

      committedResult = await response.json();
      if (onSuccess) {
        onSuccess(committedResult);
      }
      router.reload({ preserveScroll: true });
    } catch (e) {
      errorMessage = e.message;
    } finally {
      commitLoading = false;
    }
  }

  const poolCount = $derived(previewData?.total_candidates ?? distribution.pool_count ?? 0);
  const eligibleCapacity = $derived(
    selectedSessionIds.length === 0
      ? 0
      : (previewData?.total_capacity ?? distribution.eligible_capacity ?? 0)
  );
  const willBeAssigned = $derived(
    selectedSessionIds.length === 0
      ? 0
      : (previewData?.assignable_count ?? Math.min(poolCount, eligibleCapacity))
  );
  const willRemain = $derived(
    selectedSessionIds.length === 0
      ? poolCount
      : (previewData?.unassigned_count ?? Math.max(0, poolCount - willBeAssigned))
  );

  const sortStrategyLabels = {
    fcfs: 'Earliest (FCFS)',
    alphabetical: 'Surname A–Z',
    program: 'Program Track',
  };

  const packingStrategyLabels = {
    sequential: 'Fill 100% in Order',
    balanced: 'Split Evenly Across Rooms',
  };

  const mappingBySessionId = $derived(
    new Map((previewData?.session_mappings ?? []).map((m) => [Number(m.session_id), m]))
  );

  let previewCandidates = $derived(previewData?.candidates ?? []);
  let filteredCandidates = $derived(
    previewCandidates.filter((c) => {
      if (candidateTab !== 'all') {
        if (candidateTab === 'unassigned') {
          if (c.assigned_session_id !== null) return false;
        } else if (Number(c.assigned_session_id) !== Number(candidateTab)) {
          return false;
        }
      }
      if (candidateSearch.trim()) {
        const query = candidateSearch.trim().toLowerCase();
        const matchesName = (c.name ?? '').toLowerCase().includes(query);
        const matchesRef = (c.reference_code ?? '').toLowerCase().includes(query);
        const matchesProgram = (c.program_strand_key ?? '').toLowerCase().includes(query);
        if (!matchesName && !matchesRef && !matchesProgram) {
          return false;
        }
      }
      return true;
    })
  );
</script>

<Dialog.Root bind:open>
  <Dialog.Content class="w-full sm:max-w-2xl max-h-[90vh] flex flex-col p-0 font-sans border-border bg-card shadow-2xl rounded-2xl overflow-hidden" showCloseButton={false}>
    <!-- Minimal Header -->
    <div class="px-6 py-4 border-b border-border/80 flex items-center justify-between shrink-0 bg-card">
      <div class="flex items-center gap-3 min-w-0">
        <div class="size-9 rounded-xl bg-primary/10 text-primary flex items-center justify-center shrink-0">
          <Sparkles class="size-4.5" />
        </div>
        <div class="min-w-0">
          <div class="flex items-center gap-2">
            <h3 class="font-semibold text-foreground text-sm tracking-tight truncate">
              {domain === 'exam' ? 'Auto-Distribute Applicants' : 'Auto-Distribute Examinees'}
            </h3>
            <span class="text-[11px] text-muted-foreground/80 font-mono hidden sm:inline truncate">
              &bull; {resolvedCycleName}
            </span>
          </div>
          <!-- Single clean subtitle -->
          <p class="text-xs text-muted-foreground mt-0.5 truncate">
            <span class="font-medium text-foreground">{poolCount}</span> waiting in pool
            <span class="mx-1 text-muted-foreground/60">&rarr;</span>
            <span class="font-medium text-primary">{willBeAssigned}</span> will be assigned across
            <span class="font-medium text-foreground">{selectedSessionIds.length}</span> {selectedSessionIds.length === 1 ? 'session' : 'sessions'}
            {#if willRemain > 0}
              <span class="text-muted-foreground">({willRemain} remain)</span>
            {/if}
          </p>
        </div>
      </div>
      <button
        type="button"
        class="size-7 rounded-md border border-border/60 hover:bg-muted/80 text-muted-foreground hover:text-foreground flex items-center justify-center transition-colors cursor-pointer shrink-0 ml-3"
        onclick={() => (open = false)}
        aria-label="Close"
      >
        <X class="size-3.5" />
      </button>
    </div>

    <!-- Scrollable Body with Generous Breathing Room -->
    <div class="flex-1 overflow-y-auto px-6 py-5 space-y-5">
      {#if committedResult}
        <!-- Committed Success State -->
        <div class="p-6 rounded-xl bg-primary/[0.04] border border-primary/20 text-center space-y-4">
          <div class="size-10 rounded-full bg-primary text-primary-foreground mx-auto flex items-center justify-center shadow-sm">
            <Check class="size-5 stroke-[2.5]" />
          </div>
          <div>
            <h4 class="text-sm font-semibold text-foreground">Distribution Completed Successfully</h4>
            <p class="text-xs text-muted-foreground mt-1">
              Assigned <span class="font-semibold text-foreground">{committedResult.assigned_count}</span> applicants.
              {#if committedResult.unassigned_count > 0}
                <span class="font-semibold text-foreground"> {committedResult.unassigned_count}</span> remain in pool.
              {/if}
            </p>
          </div>

          <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 text-left font-mono text-xs pt-1">
            {#each (committedResult.session_mappings ?? []) as mapping (mapping.session_id)}
              <div class="p-2.5 rounded-lg bg-card border border-border/60">
                <p class="font-bold text-foreground text-[11px]">{mapping.session_code}</p>
                <p class="text-[10px] text-muted-foreground truncate">{mapping.room_name}</p>
                <p class="text-primary font-semibold text-[11px] mt-1">+{mapping.allocated_count} assigned</p>
              </div>
            {/each}
          </div>

          <Button size="sm" class="mt-2 text-xs" onclick={() => (open = false)}>Done</Button>
        </div>
      {:else}
        {#if stalePlan}
          <!-- Stale Plan Warning -->
          <div class="p-3.5 rounded-xl bg-destructive/10 border border-destructive/20 flex items-start gap-2.5 text-xs text-destructive">
            <AlertCircle class="size-4 shrink-0 mt-0.5" />
            <div class="flex-1">
              <p class="font-semibold">Plan is stale</p>
              <p class="text-[11px] opacity-90 mt-0.5">{errorMessage}</p>
              <Button size="xs" variant="outline" class="mt-2 text-[11px] gap-1.5 border-destructive/30 text-destructive hover:bg-destructive/10" onclick={fetchPreview}>
                <RotateCcw class="size-3" />
                Reload Preview
              </Button>
            </div>
          </div>
        {:else if errorMessage}
          <div class="p-3 rounded-xl bg-destructive/10 border border-destructive/20 text-xs text-destructive">
            {errorMessage}
          </div>
        {/if}

        <!-- 1. Progressive Disclosure: Options -->
        {#if !showOptions}
          <div class="flex items-center justify-between p-3 rounded-xl bg-muted/20 border border-border/70 text-xs">
            <div class="flex items-center gap-2 text-muted-foreground min-w-0">
              <span class="font-semibold text-foreground">Rule:</span>
              <span class="truncate">{sortStrategyLabels[sortStrategy]} &middot; {packingStrategyLabels[packingStrategy]}</span>
            </div>
            <button
              type="button"
              class="text-[11px] text-primary hover:text-primary/80 font-medium shrink-0 ml-2 cursor-pointer flex items-center gap-1"
              onclick={() => (showOptions = true)}
            >
              Show options ▾
            </button>
          </div>
        {:else}
          <div class="p-4 rounded-xl border border-border/80 bg-muted/15 space-y-3.5">
            <div class="flex items-center justify-between text-xs">
              <div class="flex items-center gap-2 text-muted-foreground min-w-0">
                <span class="font-semibold text-foreground">Rule:</span>
                <span class="truncate">{sortStrategyLabels[sortStrategy]} &middot; {packingStrategyLabels[packingStrategy]}</span>
              </div>
              <button
                type="button"
                class="text-[11px] text-primary hover:text-primary/80 font-medium shrink-0 ml-2 cursor-pointer flex items-center gap-1"
                onclick={() => (showOptions = false)}
              >
                Hide options ▴
              </button>
            </div>

            <div class="space-y-3 pt-2 border-t border-border/60">
              <!-- Candidate Sorting Segmented Buttons -->
              <div class="space-y-1.5">
                <span class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider font-mono block">
                  Candidate Sorting
                </span>
                <div class="grid grid-cols-3 gap-1.5 p-1 bg-muted/40 rounded-lg border border-border/60">
                  <button
                    type="button"
                    class="flex items-center justify-center gap-1.5 py-1.5 px-2 rounded-md text-xs font-medium transition-all cursor-pointer {sortStrategy === 'fcfs' ? 'bg-card text-foreground shadow-xs border border-border/60 font-semibold' : 'text-muted-foreground hover:text-foreground'}"
                    onclick={() => { sortStrategy = 'fcfs'; fetchPreview(); }}
                  >
                    <Clock class="size-3.5 text-primary" />
                    <span>Earliest (FCFS)</span>
                  </button>
                  <button
                    type="button"
                    class="flex items-center justify-center gap-1.5 py-1.5 px-2 rounded-md text-xs font-medium transition-all cursor-pointer {sortStrategy === 'alphabetical' ? 'bg-card text-foreground shadow-xs border border-border/60 font-semibold' : 'text-muted-foreground hover:text-foreground'}"
                    onclick={() => { sortStrategy = 'alphabetical'; fetchPreview(); }}
                  >
                    <Type class="size-3.5 text-primary" />
                    <span>Surname A–Z</span>
                  </button>
                  <button
                    type="button"
                    class="flex items-center justify-center gap-1.5 py-1.5 px-2 rounded-md text-xs font-medium transition-all cursor-pointer {sortStrategy === 'program' ? 'bg-card text-foreground shadow-xs border border-border/60 font-semibold' : 'text-muted-foreground hover:text-foreground'}"
                    onclick={() => { sortStrategy = 'program'; fetchPreview(); }}
                  >
                    <GraduationCap class="size-3.5 text-primary" />
                    <span>Program Track</span>
                  </button>
                </div>
              </div>

              <!-- Packing Segmented Buttons -->
              <div class="space-y-1.5">
                <span class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider font-mono block">
                  Packing Method
                </span>
                <div class="grid grid-cols-2 gap-1.5 p-1 bg-muted/40 rounded-lg border border-border/60">
                  <button
                    type="button"
                    class="flex items-center justify-center gap-1.5 py-1.5 px-2 rounded-md text-xs font-medium transition-all cursor-pointer {packingStrategy === 'sequential' ? 'bg-card text-foreground shadow-xs border border-border/60 font-semibold' : 'text-muted-foreground hover:text-foreground'}"
                    onclick={() => { packingStrategy = 'sequential'; fetchPreview(); }}
                  >
                    <span>Fill 100% in Order</span>
                  </button>
                  <button
                    type="button"
                    class="flex items-center justify-center gap-1.5 py-1.5 px-2 rounded-md text-xs font-medium transition-all cursor-pointer {packingStrategy === 'balanced' ? 'bg-card text-foreground shadow-xs border border-border/60 font-semibold' : 'text-muted-foreground hover:text-foreground'}"
                    onclick={() => { packingStrategy = 'balanced'; fetchPreview(); }}
                  >
                    <span>Split Evenly Across Rooms</span>
                  </button>
                </div>
              </div>
            </div>
          </div>
        {/if}

        <!-- 2. Merged Target Sessions List -->
        <div class="space-y-2.5">
          <div class="flex items-center justify-between text-xs">
            <div class="flex items-center gap-2">
              <span class="text-xs font-semibold text-foreground">Target Sessions</span>
              <span class="text-[11px] font-mono text-muted-foreground">
                ({selectedSessionIds.length} of {eligibleSessions.length} selected)
              </span>
            </div>
            <button
              type="button"
              class="text-xs text-primary hover:underline font-medium cursor-pointer"
              onclick={() => toggleAll(selectedSessionIds.length !== eligibleSessions.length)}
            >
              {selectedSessionIds.length === eligibleSessions.length ? 'Deselect all' : 'Select all'}
            </button>
          </div>

          {#if eligibleSessions.length === 0}
            <div class="p-6 rounded-xl border border-dashed border-border/80 text-center text-xs text-muted-foreground font-mono space-y-1">
              <p class="font-medium text-foreground">No available draft sessions found</p>
              <p class="text-[11px]">Create an unpublished staff-only session or reopen capacity to distribute.</p>
            </div>
          {:else}
            <div class="space-y-2 max-h-56 overflow-y-auto pr-1">
              {#each eligibleSessions as session, index (session.id)}
                {@const isSelected = selectedSessionIds.includes(session.id)}
                {@const mapping = mappingBySessionId.get(session.id)}
                {@const allocatedCount = isSelected && mapping ? mapping.allocated_count : 0}
                {@const currentActive = isSelected && mapping ? mapping.current_active : session.active_count}
                {@const newTotal = currentActive + allocatedCount}
                {@const capacity = session.room_capacity}
                {@const priorPct = capacity > 0 ? (currentActive / capacity) * 100 : 0}
                {@const allocatedPct = capacity > 0 ? (allocatedCount / capacity) * 100 : 0}
                {@const freeSeats = Math.max(0, capacity - newTotal)}

                <div
                  role="button"
                  tabindex="0"
                  class="p-3 rounded-xl border transition-all cursor-pointer text-xs {isSelected ? 'border-primary/40 bg-card shadow-xs ring-1 ring-primary/20' : 'border-border/70 bg-muted/10 hover:border-border hover:bg-muted/20 opacity-75'}"
                  onclick={() => toggleSession(session.id)}
                  onkeydown={(e) => (e.key === 'Enter' || e.key === ' ') && toggleSession(session.id)}
                >
                  <div class="flex items-start gap-3">
                    <div class="flex items-center gap-2 pt-0.5 shrink-0">
                      <input
                        type="checkbox"
                        checked={isSelected}
                        class="size-4 rounded accent-primary pointer-events-none"
                      />
                      <span class="text-xs font-mono font-bold {isSelected ? 'text-primary' : 'text-muted-foreground/60'}">
                        {index + 1}.
                      </span>
                    </div>

                    <div class="flex-1 min-w-0">
                      <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2 min-w-0">
                          <span class="font-semibold text-foreground text-xs">{session.code}</span>
                          <span class="text-[11px] text-muted-foreground truncate">{session.room_name}</span>
                        </div>
                        <div class="text-right shrink-0 font-mono text-[11px]">
                          {#if isSelected && allocatedCount > 0}
                            <span class="font-semibold text-primary">+{allocatedCount}</span>
                            <span class="text-muted-foreground ml-1">({newTotal} / {capacity})</span>
                          {:else}
                            <span class="text-muted-foreground font-mono">({currentActive} / {capacity})</span>
                          {/if}
                        </div>
                      </div>

                      <div class="flex items-center justify-between text-[11px] text-muted-foreground mt-0.5">
                        <span>{session.date ? formatDate(session.date) : 'Date TBD'}{session.start_time || session.end_time ? ` · ${formatTimeRange(session.start_time, session.end_time)}` : session.time ? ` · ${formatTime(session.time)}` : ''}</span>
                        <span class="text-[10px] font-mono {freeSeats === 0 ? 'text-muted-foreground' : 'text-primary/90 font-medium'}">
                          {freeSeats} open {freeSeats === 1 ? 'seat remains' : 'seats remain'}
                        </span>
                      </div>

                      <!-- Visual capacity progress bar directly below details -->
                      <div class="w-full bg-muted rounded-full h-1.5 overflow-hidden flex mt-2">
                        {#if priorPct > 0}
                          <div class="bg-muted-foreground/40 h-full" style="width: {priorPct}%"></div>
                        {/if}
                        {#if allocatedPct > 0}
                          <div class="bg-primary h-full transition-all duration-300" style="width: {allocatedPct}%"></div>
                        {/if}
                      </div>
                    </div>
                  </div>
                </div>
              {/each}
            </div>

            {#if selectedSessionIds.length === 0}
              <div class="p-3 rounded-lg bg-muted/30 border border-dashed border-border/80 text-center text-xs text-muted-foreground">
                Select at least one session to preview candidate distribution.
              </div>
            {/if}
          {/if}
        </div>

        <!-- 3. Continuous Auto-Assign Card -->
        <div class="p-4 rounded-xl border border-border/80 bg-muted/15 flex items-center justify-between gap-4">
          <div class="min-w-0">
            <p class="text-xs font-semibold text-foreground">
              Auto-assign incoming candidates into remaining seats
            </p>
            <p class="text-[11px] text-muted-foreground mt-0.5 leading-snug">
              Newly accepted candidates will automatically fill open seats in selected sessions.
            </p>
          </div>
          <!-- Fluid iOS-style switch -->
          <button
            type="button"
            role="switch"
            aria-checked={autoAssignEnabled}
            class="w-11 h-6 rounded-full transition-colors relative cursor-pointer shrink-0 {autoAssignEnabled ? 'bg-primary' : 'bg-muted-foreground/30'}"
            onclick={() => (autoAssignEnabled = !autoAssignEnabled)}
          >
            <span class="size-4 rounded-full bg-white absolute top-1 transition-transform shadow-xs {autoAssignEnabled ? 'left-6' : 'left-1'}"></span>
          </button>
        </div>

        <!-- 4. Progressive Disclosure: Candidate Inspector Drawer (when expanded) -->
        {#if candidateDrawerOpen && previewCandidates.length > 0}
          <div class="rounded-xl border border-border/80 bg-card p-3.5 space-y-3 font-mono text-xs">
            <div class="flex items-center justify-between">
              <span class="text-xs font-semibold text-foreground font-sans">
                Candidate Roster ({previewCandidates.length})
              </span>
              <button
                type="button"
                class="text-[11px] text-muted-foreground hover:text-foreground font-sans cursor-pointer"
                onclick={() => (candidateDrawerOpen = false)}
              >
                Hide ▴
              </button>
            </div>

            <!-- Search + Destination Filter Controls -->
            <div class="flex flex-col sm:flex-row gap-2">
              <input
                type="text"
                placeholder="Filter by name or code..."
                bind:value={candidateSearch}
                class="flex-1 px-2.5 py-1.5 rounded-lg border border-border/70 bg-background text-xs font-sans placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-primary"
              />
              <div class="flex items-center gap-1 overflow-x-auto pb-0.5 text-[11px] font-sans">
                <button
                  type="button"
                  class="px-2 py-1 rounded-md shrink-0 cursor-pointer transition-colors {candidateTab === 'all' ? 'bg-primary text-primary-foreground font-medium' : 'bg-muted/60 text-muted-foreground hover:text-foreground'}"
                  onclick={() => (candidateTab = 'all')}
                >
                  All ({previewCandidates.length})
                </button>
                {#each (previewData?.session_mappings ?? []) as mapping (mapping.session_id)}
                  <button
                    type="button"
                    class="px-2 py-1 rounded-md shrink-0 cursor-pointer transition-colors {candidateTab === String(mapping.session_id) ? 'bg-primary text-primary-foreground font-medium' : 'bg-muted/60 text-muted-foreground hover:text-foreground'}"
                    onclick={() => (candidateTab = String(mapping.session_id))}
                  >
                    {mapping.session_code} ({mapping.allocated_count})
                  </button>
                {/each}
                {#if willRemain > 0}
                  <button
                    type="button"
                    class="px-2 py-1 rounded-md shrink-0 cursor-pointer transition-colors {candidateTab === 'unassigned' ? 'bg-primary text-primary-foreground font-medium' : 'bg-muted/60 text-muted-foreground hover:text-foreground'}"
                    onclick={() => (candidateTab = 'unassigned')}
                  >
                    Waitlist ({willRemain})
                  </button>
                {/if}
              </div>
            </div>

            <!-- Candidate Cards List -->
            <div class="space-y-1.5 max-h-48 overflow-y-auto pr-1">
              {#if filteredCandidates.length === 0}
                <div class="py-6 text-center text-xs text-muted-foreground font-sans">
                  No candidates match filter.
                </div>
              {:else}
                {#each filteredCandidates as candidate, idx (candidate.id)}
                  <div class="flex items-center justify-between p-2 rounded-lg border border-border/50 bg-muted/10 text-xs">
                    <div class="flex items-center gap-2 min-w-0">
                      <span class="size-4.5 rounded-full bg-muted flex items-center justify-center text-[10px] font-bold text-muted-foreground shrink-0">
                        {idx + 1}
                      </span>
                      <div class="min-w-0">
                        <div class="flex items-center gap-1.5 truncate">
                          <span class="font-bold text-foreground text-[11px]">{candidate.reference_code}</span>
                          <span class="font-medium text-foreground text-[11px]">{candidate.name}</span>
                        </div>
                        <div class="text-[10px] text-muted-foreground truncate">
                          {candidate.program_strand_key} &bull; {candidate.eligibility_date}
                        </div>
                      </div>
                    </div>
                    <div class="shrink-0 ml-2">
                      {#if candidate.assigned_session_code}
                        <span class="px-2 py-0.5 rounded text-[10px] bg-primary/10 text-primary border border-primary/20 font-semibold">
                          {candidate.assigned_session_code} ({candidate.assigned_room_name ?? 'Room'})
                        </span>
                      {:else if autoAssignEnabled}
                        <span class="px-2 py-0.5 rounded text-[10px] bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 font-semibold border border-emerald-500/20">
                          Auto-Assign Next
                        </span>
                      {:else}
                        <span class="px-2 py-0.5 rounded text-[10px] bg-muted text-muted-foreground">
                          Waiting in pool
                        </span>
                      {/if}
                    </div>
                  </div>
                {/each}
              {/if}
            </div>
          </div>
        {/if}
      {/if}
    </div>

    <!-- Footer Actions (only shown when not in committed state) -->
    {#if !committedResult}
      <div class="px-6 py-3.5 border-t border-border/80 flex items-center justify-between shrink-0 bg-card/95 backdrop-blur-xs">
        <div class="flex items-center gap-2">
          {#if previewCandidates.length > 0}
            <button
              type="button"
              class="text-xs text-primary hover:text-primary/80 font-medium cursor-pointer flex items-center gap-1.5 transition-colors py-1.5 px-2 rounded-lg hover:bg-primary/5"
              onclick={() => (candidateDrawerOpen = !candidateDrawerOpen)}
            >
              <Users class="size-3.5" />
              <span>{candidateDrawerOpen ? 'Hide candidates ▴' : `Show ${previewCandidates.length} candidates ▾`}</span>
            </button>
          {/if}
        </div>

        <div class="flex items-center gap-2">
          <Button variant="ghost" size="sm" class="text-xs" onclick={() => (open = false)}>
            Cancel
          </Button>
          <Button
            variant="outline"
            size="sm"
            class="gap-1.5 text-xs"
            onclick={fetchPreview}
            disabled={previewLoading || commitLoading || selectedSessionIds.length === 0}
          >
            {#if previewLoading}
              <Loader2 class="size-3 animate-spin" />
            {:else}
              <RotateCcw class="size-3" />
            {/if}
            Recalculate
          </Button>
          <Button
            size="sm"
            class="gap-1.5 text-xs font-semibold"
            disabled={!previewData || (previewData.assignable_count === 0 && !autoAssignEnabled) || commitLoading || previewLoading || selectedSessionIds.length === 0}
            onclick={executeCommit}
          >
            {#if commitLoading}
              <Loader2 class="size-3.5 animate-spin" />
              Distributing…
            {:else if willBeAssigned > 0}
              <Sparkles class="size-3.5" />
              Distribute ({willBeAssigned})
            {:else}
              <Zap class="size-3.5" />
              Save Auto-Assign
            {/if}
          </Button>
        </div>
      </div>
    {/if}
  </Dialog.Content>
</Dialog.Root>
