<script>
  import { tick } from 'svelte';
  import BulkActionBar from '@/Components/kit/BulkActionBar.svelte';
  import FilterToolbar from '@/Components/kit/FilterToolbar.svelte';
  import BulkAssignToSessionModal from '@/Components/scheduling/BulkAssignToSessionModal.svelte';
  import ApplicantActionsMenu from '@/Components/Admission/ApplicantActionsMenu.svelte';
  import { Button } from '@/Components/ui/button';
  import { Checkbox } from '@/Components/ui/checkbox';
  import { applySearchFocus } from '@/lib/search-focus';
  import { formatDate } from '@/lib/date-utils';
  import {
    ChevronLeft,
    ChevronRight,
    Sparkles,
    Users,
  } from 'lucide-svelte';

  let {
    domain = 'exam', // 'exam' | 'consultation'
    waitlist = null,
    programs = [],
    sessions = [],
    canManage = false,
    onFilterChange = null,
    onOpenAutoDistribute = null,
    class: className = '',
  } = $props();

  let selectedIds = $state([]);
  let modalAttemptIds = $state([]);
  let assignModalOpen = $state(false);

  let searchInputDraft = $state(waitlist?.filters?.pool_search ?? '');
  let lastServerSearch = $state(waitlist?.filters?.pool_search ?? '');

  $effect(() => {
    const currentServerSearch = waitlist?.filters?.pool_search ?? '';
    if (currentServerSearch !== lastServerSearch) {
      lastServerSearch = currentServerSearch;
      searchInputDraft = currentServerSearch;
    }
  });

  const readiness = $derived(waitlist?.readiness ?? 'ready');
  const currentBlocker = $derived(waitlist?.filters?.pool_blocker ?? '');
  const currentProgram = $derived(waitlist?.filters?.pool_program ?? '');
  const currentSort = $derived(waitlist?.filters?.pool_sort ?? 'fcfs');
  const applicantsData = $derived(waitlist?.applicants ?? null);
  const applicants = $derived(applicantsData?.data ?? []);

  const allPageSelected = $derived(
    applicants.length > 0 &&
      applicants
        .filter((a) => a.can_select_for_assignment !== false)
        .every((a) => selectedIds.includes(a.attempt_id))
  );

  const sortOptions = [
    { value: 'fcfs', label: 'FCFS (Earliest Accepted)' },
    { value: 'alphabetical', label: 'Applicant Name (A–Z)' },
    { value: 'program', label: '1st Choice Course' },
  ];

  function formatBlockerLabel(code) {
    const labels = {
      intake: 'Intake / Incomplete',
      registrar: 'Registrar Review',
      guidance: 'Guidance Evaluation',
      assessment: 'Exam Assessment',
      reconsideration: 'Reconsideration',
      correction: 'Correction Needed',
    };
    return (
      labels[code] ??
      code
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (c) => c.toUpperCase())
    );
  }

  function stageBadgeTone(tone) {
    switch (tone) {
      case 'emerald':
        return 'border-success/30 bg-success/10 text-success';
      case 'amber':
        return 'border-warning/30 bg-warning/10 text-warning';
      case 'destructive':
        return 'border-destructive/30 bg-destructive/10 text-destructive';
      case 'primary':
        return 'border-primary/30 bg-primary/10 text-primary';
      case 'slate':
      default:
        return 'border-border bg-muted text-muted-foreground';
    }
  }

  function handleReadinessChange(nextReadiness) {
    selectedIds = [];
    onFilterChange?.({ readiness: nextReadiness, pool_page: 1 });
  }

  function handleBlockerChange(blockerCode) {
    onFilterChange?.({ pool_blocker: blockerCode, pool_page: 1 });
  }

  function handleSearchSubmit(query) {
    onFilterChange?.({ pool_search: query, pool_page: 1 });
  }

  function handleSearchClear() {
    searchInputDraft = '';
    onFilterChange?.({ pool_search: '', pool_page: 1 });
  }

  function handleProgramChange(event) {
    onFilterChange?.({ pool_program: event.currentTarget.value, pool_page: 1 });
  }

  function handleSortChange(payload) {
    onFilterChange?.({ pool_sort: payload.sort, pool_dir: payload.dir, pool_page: 1 });
  }

  function toggleSelectAll(checked) {
    if (checked) {
      const selectable = applicants
        .filter((a) => a.can_select_for_assignment !== false)
        .map((a) => a.attempt_id);
      selectedIds = Array.from(new Set([...selectedIds, ...selectable]));
    } else {
      const pageIds = applicants.map((a) => a.attempt_id);
      selectedIds = selectedIds.filter((id) => !pageIds.includes(id));
    }
  }

  function toggleSelect(attemptId) {
    if (selectedIds.includes(attemptId)) {
      selectedIds = selectedIds.filter((id) => id !== attemptId);
    } else {
      selectedIds = [...selectedIds, attemptId];
    }
  }

  function openSingleAssign(attemptId) {
    modalAttemptIds = [attemptId];
    assignModalOpen = true;
  }

  function openBulkAssign() {
    modalAttemptIds = [...selectedIds];
    assignModalOpen = true;
  }

  function handleAssignSuccess() {
    selectedIds = [];
    modalAttemptIds = [];
    onFilterChange?.({});
  }

  $effect(() => {
    const focusId = waitlist?.focus_attempt_id;
    if (focusId && Number.isInteger(Number(focusId)) && Number(focusId) > 0) {
      void tick().then(() => {
        applySearchFocus(`waitlist-attempt-${focusId}`);
      });
    }
  });
</script>

<div class="space-y-4 {className}">
  <!-- Readiness Selector -->
  <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
    <div
      class="inline-flex rounded-xl p-1 bg-muted/40 border border-border/60 self-start"
      role="group"
      aria-label="Readiness selector"
    >
      <button
        type="button"
        aria-pressed={readiness === 'ready'}
        class="flex items-center gap-2 py-1.5 px-3 rounded-lg text-xs font-medium transition-all cursor-pointer {readiness ===
        'ready'
          ? 'bg-card text-foreground shadow-xs border border-border/60 font-semibold'
          : 'text-muted-foreground hover:text-foreground'}"
        onclick={() => handleReadinessChange('ready')}
      >
        <span class="size-2 rounded-full bg-success"></span>
        <span>Ready for Scheduling ({waitlist?.counts?.ready ?? 0})</span>
      </button>

      <button
        type="button"
        aria-pressed={readiness === 'not_ready'}
        class="flex items-center gap-2 py-1.5 px-3 rounded-lg text-xs font-medium transition-all cursor-pointer {readiness ===
        'not_ready'
          ? 'bg-card text-foreground shadow-xs border border-border/60 font-semibold'
          : 'text-muted-foreground hover:text-foreground'}"
        onclick={() => handleReadinessChange('not_ready')}
      >
        <span class="size-2 rounded-full bg-warning"></span>
        <span
          >Not Yet Ready &bull; Pending Prerequisite ({waitlist?.counts?.not_ready ??
            0})</span
        >
      </button>
    </div>
  </div>

  <!-- Blocker Filter Pills (when not_ready) -->
  {#if readiness === 'not_ready'}
    <div class="flex flex-wrap items-center gap-1.5 py-1">
      <span class="text-xs font-medium text-muted-foreground mr-1">Blocker:</span>
      <button
        type="button"
        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium border transition-colors cursor-pointer {currentBlocker ===
        ''
          ? 'border-primary bg-primary/10 text-primary font-semibold'
          : 'border-border bg-background text-muted-foreground hover:bg-muted/60'}"
        onclick={() => handleBlockerChange('')}
      >
        All ({waitlist?.counts?.not_ready ?? 0})
      </button>

      {#each Object.entries(waitlist?.counts?.blockers ?? {}) as [blockerCode, count] (blockerCode)}
        <button
          type="button"
          class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium border transition-colors cursor-pointer {currentBlocker ===
          blockerCode
            ? 'border-primary bg-primary/10 text-primary font-semibold'
            : 'border-border bg-background text-muted-foreground hover:bg-muted/60'}"
          onclick={() => handleBlockerChange(blockerCode)}
        >
          <span>{formatBlockerLabel(blockerCode)}</span>
          <span class="opacity-75 font-mono">({count})</span>
        </button>
      {/each}
    </div>
  {/if}

  <!-- Toolbar with Search, Program, and SortDropdown -->
  <FilterToolbar
    bind:search={searchInputDraft}
    searchPlaceholder="Search applicant name, email, reference…"
    onSearchSubmit={handleSearchSubmit}
    onSearchClear={handleSearchClear}
    {sortOptions}
    sortValue={currentSort}
    onSortChange={handleSortChange}
  >
    {#snippet desktopFilters()}
      <div class="space-y-1">
        <label for="pool-program-filter" class="block text-xs font-medium text-muted-foreground">
          1st Choice Course
        </label>
        <select
          id="pool-program-filter"
          value={currentProgram}
          onchange={handleProgramChange}
          class="h-10 rounded-md border border-input bg-background px-3 text-xs font-medium text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
        >
          <option value="">All courses / programs</option>
          {#each programs as prog (prog.code)}
            <option value={prog.code}>{prog.code} &bull; {prog.name}</option>
          {/each}
        </select>
      </div>
    {/snippet}

    {#snippet mobileFilters()}
      <div class="space-y-3">
        <div class="space-y-1">
          <label for="mobile-pool-program-filter" class="block text-xs font-medium text-muted-foreground">
            1st Choice Course
          </label>
          <select
            id="mobile-pool-program-filter"
            value={currentProgram}
            onchange={handleProgramChange}
            class="h-10 w-full rounded-md border border-input bg-background px-3 text-xs font-medium text-foreground"
          >
            <option value="">All courses / programs</option>
            {#each programs as prog (prog.code)}
              <option value={prog.code}>{prog.code} &bull; {prog.name}</option>
            {/each}
          </select>
        </div>
      </div>
    {/snippet}
  </FilterToolbar>

  <!-- Bulk Action Bar -->
  {#if selectedIds.length > 0}
    <BulkActionBar
      count={selectedIds.length}
      hint={`${selectedIds.length} applicant${selectedIds.length === 1 ? '' : 's'} selected`}
      onClear={() => (selectedIds = [])}
    >
      {#snippet actions()}
        <Button
          size="sm"
          class="text-xs gap-1.5 cursor-pointer"
          onclick={openBulkAssign}
        >
          <Users class="size-3.5" />
          Assign to Session… ({selectedIds.length})
        </Button>

        {#if onOpenAutoDistribute}
          <Button
            size="sm"
            variant="outline"
            class="text-xs gap-1.5 cursor-pointer"
            onclick={() => onOpenAutoDistribute(selectedIds)}
          >
            <Sparkles class="size-3.5" />
            Auto-Distribute Selected ({selectedIds.length})
          </Button>
        {/if}
      {/snippet}
    </BulkActionBar>
  {/if}

  <!-- Applicants Table -->
  <div class="overflow-x-auto">
    <table class="w-full text-left text-xs">
      <thead class="border-b border-border bg-muted/40 text-muted-foreground font-medium">
        <tr>
          {#if readiness === 'ready' && canManage}
            <th class="p-3 w-10 text-center">
              <Checkbox
                checked={allPageSelected}
                onCheckedChange={toggleSelectAll}
                aria-label="Select all applicants on page"
              />
            </th>
          {/if}
          <th class="p-3">NOA / Reference</th>
          <th class="p-3">Applicant</th>
          <th class="p-3">1st Choice Course</th>
          <th class="p-3">Accepted / Submitted</th>
          <th class="p-3">Pipeline Readiness Status</th>
          <th class="p-3 text-right">Action / Blocker Owner</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-border/40">
        {#if applicants.length === 0}
          <tr>
            <td
              colspan={readiness === 'ready' && canManage ? 7 : 6}
              class="p-8 text-center text-muted-foreground"
            >
              No applicants found in this waitlist pool.
            </td>
          </tr>
        {:else}
          {#each applicants as row (row.attempt_id)}
            {@const isSelected = selectedIds.includes(row.attempt_id)}
            <tr
              id={`waitlist-attempt-${row.attempt_id}`}
              class="hover:bg-muted/40 transition-colors {isSelected ? 'bg-primary/[0.04]' : ''}"
            >
              {#if readiness === 'ready' && canManage}
                <td class="p-3 text-center">
                  <Checkbox
                    checked={isSelected}
                    disabled={row.can_select_for_assignment === false}
                    onCheckedChange={() => toggleSelect(row.attempt_id)}
                    aria-label={`Select ${row.full_name}`}
                  />
                </td>
              {/if}
              <td class="p-3 font-mono font-semibold text-foreground">
                {row.reference_code}
              </td>
              <td class="p-3">
                <div class="font-medium text-foreground">{row.full_name}</div>
                {#if row.email || row.strand}
                  <div class="text-[11px] text-muted-foreground truncate max-w-[200px]">
                    {row.email ?? ''}{row.email && row.strand ? ' · ' : ''}{row.strand ?? ''}
                  </div>
                {/if}
              </td>
              <td class="p-3">
                <div class="font-medium text-foreground">
                  {row.first_choice_program_name ?? row.first_choice_program_code ?? '—'}
                </div>
                {#if row.first_choice_program_code && row.first_choice_program_name}
                  <span class="text-[10px] font-mono text-muted-foreground">
                    {row.first_choice_program_code}
                  </span>
                {/if}
              </td>
              <td class="p-3 whitespace-nowrap">
                {#if row.accepted_at}
                  <div class="text-foreground">{formatDate(row.accepted_at)}</div>
                  <div class="text-[10px] text-muted-foreground">Accepted</div>
                {:else if row.submitted_at}
                  <div class="text-foreground">{formatDate(row.submitted_at)}</div>
                  <div class="text-[10px] text-muted-foreground">Submitted</div>
                {:else}
                  <span class="text-muted-foreground">—</span>
                {/if}
              </td>
              <td class="p-3 whitespace-nowrap">
                <span
                  class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-[11px] font-medium {stageBadgeTone(
                    row.stage_tone
                  )}"
                >
                  {row.stage_label}
                </span>
              </td>
              <td class="p-3 text-right">
                {#if String(waitlist?.focus_attempt_id ?? '') === String(row.attempt_id)}
                  <ApplicantActionsMenu attemptId={row.attempt_id} />
                {/if}
                {#if readiness === 'ready'}
                  {#if canManage}
                    <Button
                      size="sm"
                      variant="outline"
                      class="h-8 text-xs gap-1 cursor-pointer"
                      onclick={() => openSingleAssign(row.attempt_id)}
                    >
                      Assign to Session…
                    </Button>
                  {:else}
                    <span class="text-xs text-muted-foreground">Ready for scheduling</span>
                  {/if}
                {:else}
                  <div class="space-y-0.5 inline-block text-right">
                    <div class="font-semibold text-foreground text-xs">
                      {row.blocking_office ?? 'Prerequisites'}
                    </div>
                    {#if row.detail_hint}
                      <div class="text-[11px] text-muted-foreground truncate max-w-[220px]">
                        {row.detail_hint}
                      </div>
                    {/if}
                  </div>
                {/if}
              </td>
            </tr>
          {/each}
        {/if}
      </tbody>
    </table>
  </div>

  <!-- Pagination Footer -->
  {#if applicantsData && applicantsData.last_page > 1}
    <div class="mt-4 pt-4 border-t border-border/50 flex items-center justify-between">
      <div class="text-xs text-muted-foreground hidden sm:block">
        Showing <span class="font-medium text-foreground">{applicantsData.from || 0}</span> to
        <span class="font-medium text-foreground">{applicantsData.to || 0}</span> of
        <span class="font-medium text-foreground">{applicantsData.total}</span> results
      </div>
      <nav aria-label="Waitlist Pagination" class="flex items-center space-x-2">
        <Button
          variant="outline"
          size="sm"
          class="h-8 w-8 p-0 cursor-pointer"
          disabled={applicantsData.current_page <= 1}
          onclick={() => onFilterChange?.({ pool_page: applicantsData.current_page - 1 })}
        >
          <span class="sr-only">Previous</span>
          <ChevronLeft class="h-4 w-4" />
        </Button>
        <div class="text-xs font-medium">
          Page {applicantsData.current_page} of {applicantsData.last_page}
        </div>
        <Button
          variant="outline"
          size="sm"
          class="h-8 w-8 p-0 cursor-pointer"
          disabled={applicantsData.current_page >= applicantsData.last_page}
          onclick={() => onFilterChange?.({ pool_page: applicantsData.current_page + 1 })}
        >
          <span class="sr-only">Next</span>
          <ChevronRight class="h-4 w-4" />
        </Button>
      </nav>
    </div>
  {/if}
</div>

<!-- Bulk Assign to Session Modal -->
<BulkAssignToSessionModal
  bind:open={assignModalOpen}
  {domain}
  {sessions}
  selectedAttemptIds={modalAttemptIds}
  onSuccess={handleAssignSuccess}
/>
