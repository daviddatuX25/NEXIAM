<script>
  import { Link, router, usePage } from '@inertiajs/svelte';
  import { onMount, tick } from 'svelte';
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
  import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
  import ModeBanner from '@/Components/kit/ModeBanner.svelte';
  import SearchSuggestionDropdown from '@/Components/kit/SearchSuggestionDropdown.svelte';
  import { Badge } from '@/Components/ui/badge';
  import { Button } from '@/Components/ui/button';
  import { Input } from '@/Components/ui/input';
  import * as Table from '@/Components/ui/table';
  import {
    ScanLine,
    Printer,
    UserCheck,
    Lock,
    Search,
    CheckCircle2,
    Circle,
  } from 'lucide-svelte';
  import { applySearchFocus } from '@/lib/search-focus';
  import ScoringSetupNavigation from '@/Components/Assessment/ScoringSetupNavigation.svelte';
  import ApplicantActionsMenu from '@/Components/Admission/ApplicantActionsMenu.svelte';
  import * as Dialog from '@/Components/ui/dialog';
  import FastScoringTable from '@/Components/Assessment/FastScoringTable.svelte';
  let {
    page_state = 'ready',
    is_final_only = false,
    session = {},
    applicants = [],
    focus_attempt_id = null,
    can_finalize = false,
    aptitude_areas_count = 0,
    roster_stats = { assigned: 0, present_and_submitted: 0, absent: 0, pending_attendance: 0, no_submission: 0 },
    progress_stats = { total: 0, scored: 0, pending: 0 },
    can_manage_catalog = false,
    setup_links = null,
    filters = { search: '', progress: 'all', source_mode: 'all' },
    active_academic_cycle_id = null,
    readiness_state = null,
    readiness = null,
  } = $props();
  const focusedApplicant = $derived(applicants.find((applicant) => String(applicant.attempt_id) === String(focus_attempt_id)) ?? null);

  const page = usePage();
  const finalizeError = $derived(page.props.errors?.finalize ?? '');
  let finalizing = $state(false);
  const isFinalized = $derived((session.grading_status ?? session.status) === 'finalized');
  let searchInput = $state(null);
  let suggestionDropdown = $state(null);
  let draftSearch = $state(String(filters?.search ?? ''));
  let lookupScope = $state('current_session');
  let selectedProgress = $state(String(filters?.progress ?? 'all'));
  let selectedSourceMode = $state(String(filters?.source_mode ?? 'all'));
  let entryMode = $state('roster');
  let fastApplicants = $state([]);
  let fastStatus = $state('idle');
  let fastError = $state('');
  let dirtyFastRows = $state(false);
  let confirmNavigationOpen = $state(false);
  let pendingNavigation = $state(null);
  const hasActiveFilters = $derived(Boolean(String(filters?.search ?? '').trim()) || selectedProgress !== 'all' || selectedSourceMode !== 'all');

  function applyFilters(overrides = {}) {
    const navigate = () => router.get(`/scoring/sessions/${session.exam_session_id}`, {
      search: String(overrides.search ?? draftSearch).trim(),
      progress: overrides.progress ?? selectedProgress,
      source_mode: overrides.source_mode ?? selectedSourceMode,
    }, { preserveState: true, preserveScroll: true, replace: true });
    if (dirtyFastRows) {
      pendingNavigation = navigate;
      confirmNavigationOpen = true;
      return;
    }
    navigate();
  }

  function clearFilters() {
    draftSearch = '';
    selectedProgress = 'all';
    selectedSourceMode = 'all';
    applyFilters({ search: '', progress: 'all', source_mode: 'all' });
  }

  function handleSearchActivation(action) {
    if (action?.type === 'navigate') router.visit(action.url);
    else if (action?.type === 'filter') {
      draftSearch = action.query;
      applyFilters({ search: action.query });
    }
  }

  function handleSearchInput(event) {
    draftSearch = event.currentTarget.value;
    suggestionDropdown?.handleInput(draftSearch);
  }

  function handleSearchFocus() {
    suggestionDropdown?.handleFocus();
    suggestionDropdown?.handleInput(draftSearch);
  }

  function handleSearchKeydown(event) {
    suggestionDropdown?.handleKeydown(event);
  }

  async function loadFastRows() {
    if (fastStatus === 'loading') return;
    fastStatus = 'loading';
    fastError = '';
    const loaded = [];
    try {
      for (const applicant of applicants) {
        if (!applicant.score_url) continue;
        const response = await fetch(applicant.score_url, {
          credentials: 'same-origin',
          cache: 'no-store',
          headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(data.message ?? `Could not load ${applicant.name}.`);
        loaded.push({ applicant, data });
      }
      fastApplicants = loaded;
      fastStatus = 'ready';
    } catch (error) {
      fastApplicants = loaded;
      fastError = error?.message ?? 'Unable to load scoring rows.';
      fastStatus = 'error';
    }
  }

  function setMode(mode) {
    entryMode = mode;
    if (mode === 'fast' && fastStatus === 'idle') loadFastRows();
  }

  function selectMode(mode) {
    if (entryMode === mode) return;
    if (dirtyFastRows) {
      pendingNavigation = () => setMode(mode);
      confirmNavigationOpen = true;
      return;
    }
    setMode(mode);
  }

  async function refreshRoster() {
    await router.reload({
      only: ['progress_stats', 'can_finalize', 'readiness', 'readiness_state', 'applicants'],
      preserveState: true,
      preserveScroll: true,
    });
  }

  function confirmLeave() {
    dirtyFastRows = false;
    const action = pendingNavigation;
    pendingNavigation = null;
    confirmNavigationOpen = false;
    action?.();
  }

  const breadcrumbs = [
    { label: 'Scoring', href: '/scoring' },
    { label: `Session ${session.exam_code}` },
  ];


  function submitFinalize() {
    if (!can_finalize || finalizing || !session.finalize_url) return;
    finalizing = true;
    router.post(session.finalize_url, {
      expected_version: session.grading_version ?? session.version,
    }, {
      onFinish: () => {
        finalizing = false;
      },
    });
  }

  function focusAttempt() {
    if (focus_attempt_id === null) return;
    void tick().then(() => applySearchFocus(`grading-attempt-${focus_attempt_id}`));
  }

  onMount(focusAttempt);
  $effect(() => {
    focus_attempt_id;
    focusAttempt();
  });
</script>

<svelte:head><title>Session {session.exam_code ?? ''} — Scoring</title></svelte:head>

<StaffLayout {breadcrumbs}>
  <div class="min-w-0 space-y-6 p-4 sm:p-6">
    {#if is_final_only}
      <ModeBanner variant="info" title="Final scores only">
        Enter the required supplied aptitude scores and their source references. Raw calculation, local norms and OMR do not apply to this Test Setup.
      </ModeBanner>
    {/if}

    <PageHeaderBar
      title={`Session ${session.exam_code ?? '—'} — ${session.room_name ?? '—'}`}
      description={`${session.starts_at ?? '—'} · Opened ${session.opened_at ? new Date(session.opened_at).toLocaleDateString() : '—'}`}
    >
      {#snippet actions()}
        <div class="flex flex-wrap items-center gap-2">
          {#if focusedApplicant?.attempt_id}
            <ApplicantActionsMenu attemptId={focusedApplicant.attempt_id} context={{ exam_session_id: session.exam_session_id }} />
          {/if}
          <ScoringSetupNavigation {can_manage_catalog} {setup_links} />
          {#if session.omr_url}
            <Link href={session.omr_url} class="inline-flex min-h-10 items-center gap-2 rounded-md border border-primary/40 px-4 text-sm font-medium text-primary transition-colors hover:bg-primary/10">
              <ScanLine class="size-4" />
              Open scanner
            </Link>
          {/if}
          {#if session.print_url}
            <Link href={session.print_url} class="inline-flex min-h-10 items-center gap-2 rounded-md border border-border px-3 text-sm font-medium text-foreground transition-colors hover:bg-muted">
              <Printer class="size-4 text-muted-foreground" />
              Print results
            </Link>
          {/if}
          {#if !isFinalized}
            <Button class="min-h-10 gap-2" disabled={!can_finalize || finalizing || !session.finalize_url} title={!can_finalize ? `Every applicant must be scored for all ${aptitude_areas_count} aptitude areas first` : 'Finalize grading'} onclick={submitFinalize}>
              <Lock class="size-4" />
              {finalizing ? 'Finalizing…' : 'Finalize grading'}
            </Button>
          {/if}
        </div>
      {/snippet}
    </PageHeaderBar>

    <div class="grid grid-cols-2 gap-3">
      <div class="rounded-xl border border-border bg-card px-4 py-3"><p class="text-2xl font-semibold">{progress_stats.scored}</p><p class="text-xs text-muted-foreground">Scored</p></div>
      <div class="rounded-xl border border-border bg-card px-4 py-3"><p class="text-2xl font-semibold">{progress_stats.pending}</p><p class="text-xs text-muted-foreground">Not scored</p></div>
    </div>

    {#if isFinalized}
      <ModeBanner variant="info" title="Finalized">
        Scores are locked and no longer editable. Finalized by {session.finalized_by_name ?? '—'} on
        {session.finalized_at ? new Date(session.finalized_at).toLocaleString() : '—'}.
      </ModeBanner>
    {/if}

    {#if finalizeError}
      <div class="rounded-md border border-destructive/40 bg-destructive/5 px-4 py-3 text-sm text-destructive">{finalizeError}</div>
    {/if}

    {#if !isFinalized && progress_stats.pending > 0}
      <p class="text-sm text-muted-foreground">
        {progress_stats.pending === 1 ? '1 applicant still needs scores' : `${progress_stats.pending} applicants still need scores`} against all {aptitude_areas_count} required scores in this session's Test Setup before finalizing.
      </p>
    {/if}

    <div class="rounded-xl border border-border bg-card">
      <div class="flex flex-wrap items-center justify-between gap-3 p-6">
        <div><h2 class="text-lg font-semibold tracking-tight">Applicants</h2><p class="mt-1 text-sm text-muted-foreground">Choose roster links or enter scores in the applicant table.</p></div>
        <div class="flex gap-2" role="group" aria-label="Scoring entry mode">
          <Button type="button" variant={entryMode === 'roster' ? 'default' : 'outline'} onclick={() => selectMode('roster')}>Roster</Button>
          <Button type="button" variant={entryMode === 'fast' ? 'default' : 'outline'} onclick={() => selectMode('fast')}>Fast entry</Button>
        </div>
      </div>
      {#if entryMode === 'roster'}
        <div class="flex flex-wrap items-end gap-3 border-t border-border px-6 py-4">
          <div class="min-w-[240px] flex-1">
            <label for="grading-roster-search" class="mb-1 block text-xs font-medium">Search examinees</label>
            <div class="relative">
              <Search class="text-muted-foreground absolute top-2.5 left-2.5 size-4" />
              <Input bind:ref={searchInput} id="grading-roster-search" bind:value={draftSearch} placeholder="Search examinee name or reference…" class="pl-9" role="combobox" aria-label="Search examinee name or reference" aria-autocomplete="list" aria-expanded={suggestionDropdown?.isExpanded() ?? false} aria-controls={suggestionDropdown?.listboxIdentifier()} aria-activedescendant={suggestionDropdown?.activeDescendantId()} oninput={handleSearchInput} onfocus={handleSearchFocus} onkeydown={handleSearchKeydown} />
              <SearchSuggestionDropdown bind:this={suggestionDropdown} search={draftSearch} academicCycleId={active_academic_cycle_id} examSessionId={session.exam_session_id} domainContext="grading" contextKey={session.exam_session_id} lookupScope={lookupScope} destinationKind="score_entry" inputRef={searchInput} onactivate={handleSearchActivation} />
            </div>
          </div>
          <div class="grid gap-1"><label for="grading-search-scope" class="text-xs font-medium">Search scope</label><select id="grading-search-scope" class="h-10 rounded-md border border-input bg-background px-3 text-sm" bind:value={lookupScope}><option value="current_session">This session</option><option value="selected_cycle">Other sessions in year</option></select></div>
          <div class="grid gap-1"><span class="text-xs font-medium">Progress</span><div class="flex flex-wrap gap-1">
            {#each [{ value: 'all', label: 'All' }, { value: 'complete', label: 'Complete' }, { value: 'incomplete', label: 'Incomplete' }, { value: 'awaiting_norms', label: 'Awaiting Norms' }, { value: 'not_started', label: 'Not Started' }] as option (option.value)}
              <Button type="button" size="sm" variant={selectedProgress === option.value ? 'default' : 'outline'} onclick={() => { selectedProgress = option.value; applyFilters({ progress: option.value }); }}>{option.label}</Button>
            {/each}
          </div></div>
          <div class="grid gap-1"><label for="grading-source-mode" class="text-xs font-medium">Source / Mode</label><select id="grading-source-mode" class="h-10 rounded-md border border-input bg-background px-3 text-sm" bind:value={selectedSourceMode} onchange={() => applyFilters()}><option value="all">All sources</option><option value="omr">OMR</option><option value="omr_manual">OMR + Manual</option><option value="manual_raw">Manual Raw</option><option value="direct_converted">Direct Converted</option></select></div>
          {#if hasActiveFilters}<Button type="button" variant="ghost" onclick={clearFilters}>Clear filters</Button>{/if}
        </div>
        <div class="overflow-x-auto">
          <Table.Root class="w-full min-w-[720px] text-sm">
            <Table.Header class="bg-muted/50"><Table.Row><Table.Head class="px-4 py-3">Reference</Table.Head><Table.Head class="px-4 py-3">Name</Table.Head><Table.Head class="px-4 py-3">Source / Mode</Table.Head><Table.Head class="px-4 py-3">Progress</Table.Head><Table.Head class="px-4 py-3 text-center">Action</Table.Head></Table.Row></Table.Header>
            <Table.Body>
              {#each applicants as app (app.attempt_id)}
                <Table.Row id={'grading-attempt-' + app.attempt_id} class="border-t border-border hover:bg-muted/30 {String(app.attempt_id) === String(focus_attempt_id) ? 'ring-2 ring-primary ring-offset-2 bg-primary/5' : ''}">
                  <Table.Cell class="px-4 py-3 font-medium">{app.reference_code}</Table.Cell><Table.Cell class="px-4 py-3">{app.name}</Table.Cell>
                  <Table.Cell class="px-4 py-3">{#if app.source_mode && app.source_mode !== '—'}<Badge variant="outline">{app.source_mode}</Badge>{:else}—{/if}</Table.Cell>
                  <Table.Cell class="px-4 py-3">{#if app.scored || app.progress_state === 'complete'}<Badge variant="success" class="gap-1"><CheckCircle2 class="size-3" />Complete ({app.domains_complete}/{app.domains_total})</Badge>{:else if app.progress_state === 'awaiting_norms'}<Badge variant="warning">Awaiting norms</Badge>{:else if app.domains_complete > 0 || app.progress_state === 'incomplete'}<Badge variant="warning">{app.domains_complete} of {app.domains_total} subtests</Badge>{:else}<Badge variant="outline" class="gap-1"><Circle class="size-3" />Not started</Badge>{/if}</Table.Cell>
                  <Table.Cell class="px-4 py-3 text-center"><Link href={app.score_url ?? ''}><Button variant="outline" size="sm" class="h-8 px-2 text-xs font-semibold" disabled={!app.score_url}>{isFinalized ? 'View scores' : app.scored ? 'Edit scores' : 'Input scores'}</Button></Link></Table.Cell>
                </Table.Row>
              {:else}
                <Table.Row><Table.Cell colspan={5} class="px-4 py-12 text-center text-muted-foreground">{#if roster_stats.assigned === 0}No applicants assigned to this exam session yet.{:else}No applicants match these filters.{/if}</Table.Cell></Table.Row>
              {/each}
            </Table.Body>
          </Table.Root>
        </div>
      {:else if fastStatus === 'loading'}
        <p class="border-t border-border p-6 text-sm text-muted-foreground" role="status">Loading scoring rows…</p>
      {:else if fastStatus === 'error'}
        <div class="flex items-center justify-between gap-3 border-t border-border p-6" role="alert"><p class="text-sm text-destructive">{fastError}</p><Button type="button" variant="outline" onclick={() => { fastStatus = 'idle'; loadFastRows(); }}>Retry loading</Button></div>
      {:else}
        <div class="border-t border-border"><FastScoringTable rows={fastApplicants} isFinalized={isFinalized} ondirtychange={(dirty) => { dirtyFastRows = dirty; }} onsaved={refreshRoster} /></div>
      {/if}
    </div>
    </div>
  <Dialog.Root bind:open={confirmNavigationOpen}>
    <Dialog.Content class="sm:max-w-md">
      <Dialog.Header><Dialog.Title>Unsaved scores</Dialog.Title><Dialog.Description>Leaving fast entry will discard unsaved applicant scores. Save each changed row or continue and discard those edits.</Dialog.Description></Dialog.Header>
      <Dialog.Footer>
        <Button type="button" variant="outline" onclick={() => { confirmNavigationOpen = false; pendingNavigation = null; }}>Stay on this page</Button>
        <Button type="button" variant="destructive" onclick={confirmLeave}>Discard and continue</Button>
      </Dialog.Footer>
    </Dialog.Content>
  </Dialog.Root>
</StaffLayout>
