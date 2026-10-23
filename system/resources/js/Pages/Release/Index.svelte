<script>
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
  import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
  import SwitchableListView from '@/Components/kit/SwitchableListView.svelte';
  import OperationalCard from '@/Components/kit/OperationalCard.svelte';
  import HeaderActions from '@/Components/kit/HeaderActions.svelte';
  import ActiveFilterChips from '@/Components/kit/ActiveFilterChips.svelte';
  import E2PageState from '@/Pages/Admission/E2PageState.svelte';
  import { Badge } from '@/Components/ui/badge';
  import { Button } from '@/Components/ui/button';
  import { Input } from '@/Components/ui/input';
  import * as Card from '@/Components/ui/card';
  import * as Table from '@/Components/ui/table';
  import { Link, router } from '@inertiajs/svelte';
  import { Printer, FileText, ArrowRight, Search, X, ArrowUp, ArrowDown, ArrowUpDown, ChevronDown } from 'lucide-svelte';
  let {
    page_state = 'ready',
    sessions = [],
    sort_options = [
      { value: 'exam_date', label: 'Exam Date' },
      { value: 'session_code', label: 'Session Code' },
      { value: 'unprinted_count', label: 'Prepared not printed' },
      { value: 'prepared_count', label: 'Prepared Count' },
      { value: 'print_backlog_count', label: 'Printable Backlog' },
    ],
    cycles = [],
    filters = {},
    active_template = null,
    can_manage_templates = false,
  } = $props();

  let viewMode = $state('responsive');
  let filterSearch = $state(String(filters?.search ?? ''));
  let selectedStatus = $state(String(filters?.status || 'unprinted'));
  let selectedCycleId = $state(String(filters?.academic_cycle_id ?? ''));
  let currentSort = $state(String(filters?.sort ?? 'exam_date'));
  let currentDir = $state(String(filters?.dir ?? 'desc'));
  const activeFilterCount = $derived(Number(selectedStatus !== 'unprinted') + Number(Boolean(selectedCycleId)));
  let searchSuggestionsOpen = $state(false);
  let mobileFiltersOpen = $state(false);

  const activeFilterChips = $derived([
    ...(filterSearch.trim() ? [{ id: 'search', label: `Search: ${filterSearch.trim()}` }] : []),
    ...(selectedStatus !== 'unprinted' ? [{ id: 'status', label: `Print status: ${selectedStatus === 'printed' ? 'Fully printed' : 'All sessions'}` }] : []),
    ...(selectedCycleId ? [{ id: 'cycle', label: `Cycle: ${cycles.find((cycle) => String(cycle.id) === selectedCycleId)?.label ?? selectedCycleId}` }] : []),
  ]);
  const matchingSessions = $derived((sessions ?? []).filter((session) => {
    const query = filterSearch.trim().toLowerCase();
    return !query || [session.exam_code, session.room_name].some((value) => String(value ?? '').toLowerCase().includes(query));
  }).slice(0, 8));

  const totalExaminees = $derived((sessions ?? []).reduce((acc, s) => acc + (s.examinees_count || 0), 0));
  const totalPrepared = $derived((sessions ?? []).reduce((acc, s) => acc + (s.prepared_count || 0), 0));
  const totalPrinted = $derived((sessions ?? []).reduce((acc, s) => acc + (s.printed_count || 0), 0));
  const totalUnprinted = $derived((sessions ?? []).reduce((acc, s) => acc + (s.unprinted_count || 0), 0));
  const finalizedSessionsCount = $derived((sessions ?? []).filter((s) => s.status === 'finalized').length);
  const releaseContext = $derived.by(() => {
    const entityKey = 'release-hub';
    const safetyKey = `${page_state}:${active_template ? 'tmpl' : 'notmpl'}:${sessions?.length ?? 0}:${totalExaminees}:${totalPrepared}:${totalPrinted}:${totalUnprinted}:${finalizedSessionsCount}`;

    return {
      entityKey,
      safetyKey,
      freshness: 'current',
      pageState: page_state,
      hasActiveTemplate: active_template !== null,
      activeTemplateName: active_template?.name ?? null,
      canManageTemplates: Boolean(can_manage_templates),
      sessionsCount: sessions?.length ?? 0,
      totalExaminees,
      totalPrepared,
      totalPrinted,
      totalUnprinted,
      finalizedSessionsCount,
    };
  });

  const breadcrumbs = [{ label: 'Printing of Results' }];
  function applyFilters(overrides = {}) {
    const nextSearch = overrides.search !== undefined ? overrides.search : filterSearch;
    const nextStatus = overrides.status !== undefined ? overrides.status : selectedStatus;
    const nextCycleId = overrides.academic_cycle_id !== undefined ? overrides.academic_cycle_id : selectedCycleId;
    const nextSort = overrides.sort !== undefined ? overrides.sort : currentSort;
    const nextDir = overrides.dir !== undefined ? overrides.dir : currentDir;

    const params = {};
    if (nextSearch?.trim()) params.search = nextSearch.trim();
    if (nextStatus && nextStatus !== 'unprinted') params.status = nextStatus;
    if (nextCycleId) params.academic_cycle_id = nextCycleId;
    if (nextSort) params.sort = nextSort;
    if (nextDir) params.dir = nextDir;

    router.get('/release', params, {
      preserveState: true,
      preserveScroll: true,
      replace: true,
    });
  }

  function handleSearchSubmit() {
    applyFilters({ search: filterSearch });
    searchSuggestionsOpen = false;
  }

  function handleSearchClear() {
    filterSearch = '';
    applyFilters({ search: '' });
    searchSuggestionsOpen = false;
  }

  function handleStatusChange() {
    applyFilters({ status: selectedStatus });
  }

  function handleCycleChange() {
    applyFilters({ academic_cycle_id: selectedCycleId });
  }

  function handleSortChange({ sort, dir }) {
    currentSort = sort;
    currentDir = dir;
    applyFilters({ sort, dir });
  }

  function toggleColumnSort(sort) {
    handleSortChange({
      sort,
      dir: currentSort === sort && currentDir === 'asc' ? 'desc' : 'asc',
    });
  }

  function sortIndicator(sort) {
    if (currentSort !== sort) {
      return ArrowUpDown;
    }
    return currentDir === 'asc' ? ArrowUp : ArrowDown;
  }

  function clearFilter(id) {
    if (id === 'search') filterSearch = '';
    if (id === 'status') selectedStatus = 'unprinted';
    if (id === 'cycle') selectedCycleId = '';
    applyFilters();
  }

  function clearAllFilters() {
    filterSearch = '';
    selectedStatus = 'unprinted';
    selectedCycleId = '';
    currentSort = 'exam_date';
    currentDir = 'desc';
    applyFilters();
  }
</script>

<svelte:head><title>Printing of Results · NEXIAM</title></svelte:head>

<StaffLayout {breadcrumbs}>
  <div class="min-w-0 space-y-6 p-4 sm:p-6">
    <PageHeaderBar
      title="Printing of Results"
      description="Batch-print official rating sheets and manage physical print receipts by examination session."
      tourKey="release-batch-releasing"
      context={releaseContext}
    >
      {#snippet actions()}
        <div class="w-full min-w-0 sm:w-auto">
          {#if can_manage_templates}
            <HeaderActions>
              {#snippet primaryAction()}
                <Link href="/setup/result-templates">
                  <Button variant="outline" class="min-h-10 w-full gap-2 font-medium sm:w-auto">
                    <Printer class="size-4 text-muted-foreground" />
                    Result templates
                  </Button>
                </Link>
              {/snippet}
            </HeaderActions>
          {/if}
        </div>
      {/snippet}
    </PageHeaderBar>

    <E2PageState state={page_state} />

    {#if page_state === 'ready'}
      <!-- Active Template Status Card -->
      <Card.Root class="border-border/70 bg-card" data-tour="release-active-template">
        <Card.Content class="flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:justify-between">
          <div class="flex items-start gap-3">
            <div class="rounded-lg bg-primary/10 p-2.5 text-primary">
              <FileText class="size-5" />
            </div>
            <div>
              <div class="flex items-center gap-2">
                <h2 class="text-sm font-semibold text-foreground">Active Print Template</h2>
                {#if active_template}
                  <Badge variant="default" class="text-[11px]">Active</Badge>
                {:else}
                  <Badge variant="destructive" class="text-[11px]">No template active</Badge>
                {/if}
              </div>
              <p class="mt-0.5 text-xs text-muted-foreground">
                {#if active_template}
                  <span class="font-medium text-foreground">{active_template.name}</span>
                  ({active_template.mode.toUpperCase()} · {active_template.paper_size.toUpperCase()} {active_template.orientation})
                {:else}
                  Result printing requires an active template. Create or activate one in Result Templates setup.
                {/if}
              </p>
            </div>
          </div>
          {#if can_manage_templates}
            <Link href="/setup/result-templates" class="shrink-0">
              <Button variant="outline" size="sm" class="gap-1.5 text-xs">
                Configure templates
                <ArrowRight class="size-3" />
              </Button>
            </Link>
          {/if}
        </Card.Content>
      </Card.Root>

      <!-- FilterToolbar and Batch Printing Sessions -->
      <div class="space-y-4" data-tour="release-batch-sessions">
        <div class="flex items-center justify-between">
          <div>
            <h2 class="text-base font-semibold text-foreground">Examination Sessions — Printing Studio</h2>
            <p class="text-xs text-muted-foreground">
              Batch-print official Certificate of Rating sheets and track physical print receipts by session.
            </p>
          </div>
        </div>

        {#snippet filterControls(suffix)}
          <div class="flex flex-wrap items-end gap-3">
            <div class="grid min-w-0 gap-1">
              <label for={`release-status-filter-${suffix}`} class="text-xs font-medium text-muted-foreground">Print status</label>
              <div class="relative min-w-0">
                <select id={`release-status-filter-${suffix}`} class="h-9 min-w-0 flex-1 truncate appearance-none rounded-md border border-input bg-background px-3 pr-8 text-xs font-medium text-foreground shadow-xs dark:bg-input/30" bind:value={selectedStatus} onchange={handleStatusChange}>
                  <option value="unprinted">Printable backlog</option>
                  <option value="printed">Fully printed</option>
                  <option value="all">All sessions</option>
                </select>
                <ChevronDown class="pointer-events-none absolute right-2 top-1/2 size-4 -translate-y-1/2 shrink-0 ml-1.5" aria-hidden="true" />
              </div>
            </div>
            {#if cycles.length > 0}
              <div class="grid min-w-0 gap-1">
                <label for={`release-cycle-filter-${suffix}`} class="text-xs font-medium text-muted-foreground">Cycle</label>
                <div class="relative min-w-0">
                  <select id={`release-cycle-filter-${suffix}`} class="h-9 min-w-0 flex-1 truncate appearance-none rounded-md border border-input bg-background px-3 pr-8 text-xs font-medium text-foreground shadow-xs dark:bg-input/30" bind:value={selectedCycleId} onchange={handleCycleChange}>
                    <option value="">All cycles</option>
                    {#each cycles as cycle (cycle.id)}
                      <option value={String(cycle.id)}>{cycle.label}</option>
                    {/each}
                  </select>
                  <ChevronDown class="pointer-events-none absolute right-2 top-1/2 size-4 -translate-y-1/2 shrink-0 ml-1.5" aria-hidden="true" />
                </div>
              </div>
            {/if}
            <div class="grid min-w-0 gap-1">
              <label for={`release-sort-${suffix}`} class="text-xs font-medium text-muted-foreground">Sort by</label>
              <div class="relative min-w-0">
                <select id={`release-sort-${suffix}`} class="h-9 min-w-0 flex-1 truncate appearance-none rounded-md border border-input bg-background px-3 pr-8 text-xs font-medium text-foreground shadow-xs dark:bg-input/30" value={`${currentSort}:${currentDir}`} onchange={(event) => {
                  const [sort, dir] = event.currentTarget.value.split(':');
                  handleSortChange({ sort, dir });
                }}>
                  {#each sort_options as option (option.value)}
                    <option value={`${option.value}:asc`}>{option.label} ascending</option>
                    <option value={`${option.value}:desc`}>{option.label} descending</option>
                  {/each}
                </select>
                <ChevronDown class="pointer-events-none absolute right-2 top-1/2 size-4 -translate-y-1/2 shrink-0 ml-1.5" aria-hidden="true" />
              </div>
            </div>
            {#if activeFilterChips.length > 0 || currentSort !== 'exam_date' || currentDir !== 'desc'}
              <Button type="button" variant="outline" class="min-h-9" onclick={clearAllFilters}>Reset</Button>
            {/if}
          </div>
        {/snippet}
        <div class="sticky top-0 z-20 backdrop-blur-md bg-background/80 py-2 space-y-3">
          <div class="relative w-full">
            <label for="release-search" class="sr-only">Search sessions, rooms, or examinees</label>
            <div class="relative flex items-center">
              <Search class="pointer-events-none absolute left-3.5 size-4 text-muted-foreground" aria-hidden="true" />
              <Input
                id="release-search"
                type="search"
                bind:value={filterSearch}
                placeholder="Search session code, room, examinee, NOA…"
                class="h-11 min-h-[44px] w-full rounded-xl border border-input bg-background pl-10 pr-24 text-foreground shadow-xs dark:bg-input/30"
                onfocus={() => (searchSuggestionsOpen = true)}
                oninput={() => (searchSuggestionsOpen = true)}
                onkeydown={(event) => {
                  if (event.key === 'Enter') handleSearchSubmit();
                  if (event.key === 'Escape') searchSuggestionsOpen = false;
                }}
                role="combobox"
                aria-autocomplete="list"
                aria-expanded={searchSuggestionsOpen}
                aria-controls="release-search-suggestions"
              />
              <div class="absolute right-2 flex items-center gap-1">
                {#if filterSearch}
                  <Button type="button" variant="ghost" size="icon" class="size-9 min-h-[44px] min-w-[44px]" onclick={handleSearchClear} aria-label="Clear search">
                    <X class="size-4" />
                  </Button>
                {/if}
                <Button type="button" size="sm" class="min-h-[44px] px-3" onclick={handleSearchSubmit}>Search</Button>
              </div>
            </div>
            {#if searchSuggestionsOpen}
              <div role="presentation" class="fixed inset-0 z-30" onclick={() => (searchSuggestionsOpen = false)}></div>
              <div id="release-search-suggestions" class="absolute left-0 top-full z-50 mt-1.5 w-full max-w-2xl rounded-2xl border border-border bg-card p-4 shadow-2xl backdrop-blur-md" role="listbox" aria-label="Session search suggestions">
                <div class="space-y-3">
                  <div>
                    <p class="mb-2 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Quick status filters</p>
                    <div class="flex flex-wrap gap-2">
                      {#each [{ value: 'unprinted', label: 'Unprinted pending' }, { value: 'printed', label: 'Fully printed' }, { value: 'all', label: 'All sessions' }] as shortcut}
                        <button type="button" class="inline-flex min-h-[44px] items-center rounded-lg border border-border bg-muted/40 px-3 py-1.5 text-xs font-semibold hover:border-primary hover:bg-primary/10 hover:text-primary" onclick={() => {
                          selectedStatus = shortcut.value;
                          handleStatusChange();
                          searchSuggestionsOpen = false;
                        }}>{shortcut.label}</button>
                      {/each}
                    </div>
                  </div>
                  <div class="border-t border-border/50 pt-3">
                    <p class="mb-2 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Matching sessions</p>
                    <div class="max-h-52 space-y-1 overflow-y-auto">
                      {#each matchingSessions as session (session.exam_session_id)}
                        <button type="button" class="flex min-h-[44px] w-full items-center justify-between gap-3 rounded-lg px-3 py-2 text-left text-xs transition-colors hover:bg-muted" onclick={() => {
                          filterSearch = session.exam_code;
                          handleSearchSubmit();
                        }}>
                          <span class="truncate font-mono font-semibold text-foreground">{session.exam_code}</span>
                          <span class="shrink-0 text-muted-foreground">{session.room_name}</span>
                        </button>
                      {:else}
                        <p class="px-3 py-2 text-xs text-muted-foreground">No matching sessions in this result set.</p>
                      {/each}
                    </div>
                  </div>
                </div>
              </div>
            {/if}
          </div>
          <div class="flex items-center justify-between gap-2 md:hidden">
            <Button
              type="button"
              variant="outline"
              class="min-h-[44px] w-full justify-between"
              aria-expanded={mobileFiltersOpen}
              onclick={() => (mobileFiltersOpen = !mobileFiltersOpen)}
            >
              <span>Filters ({activeFilterCount})</span>
              <ChevronDown class="size-4 shrink-0 transition-transform {mobileFiltersOpen ? 'rotate-180' : ''}" aria-hidden="true" />
            </Button>
          </div>
          <div class="{mobileFiltersOpen ? 'block' : 'hidden md:block'}">
            {@render filterControls('desktop')}
          </div>
          {#if activeFilterChips.length > 0}
            <ActiveFilterChips chips={activeFilterChips} onRemove={clearFilter} onClearAll={clearAllFilters} />
          {/if}
        </div>

        <SwitchableListView bind:viewMode>
          {#snippet table()}

          <Table.Root class="w-full min-w-[760px] text-sm">
            <Table.Header class="bg-muted/40 border-b border-border">
              <Table.Row class="border-b border-border/40 hover:bg-muted/30 transition-colors">
                <Table.Head class="px-4 py-2 text-left align-middle" aria-sort={currentSort === 'exam_date' ? (currentDir === 'asc' ? 'ascending' : 'descending') : 'none'}>
                  <button type="button" class="inline-flex min-h-[44px] md:min-h-[36px] items-center gap-1.5 font-semibold" onclick={() => toggleColumnSort('exam_date')}>
                    Exam Date <svelte:component this={sortIndicator('exam_date')} class="size-3.5" aria-hidden="true" />
                  </button>
                </Table.Head>
                <Table.Head class="px-4 py-2 text-left align-middle" aria-sort={currentSort === 'session_code' ? (currentDir === 'asc' ? 'ascending' : 'descending') : 'none'}>
                  <button type="button" class="inline-flex min-h-[44px] md:min-h-[36px] items-center gap-1.5 font-semibold" onclick={() => toggleColumnSort('session_code')}>
                    Session Code <svelte:component this={sortIndicator('session_code')} class="size-3.5" aria-hidden="true" />
                  </button>
                </Table.Head>
                <Table.Head class="px-4 py-2 text-right align-middle">Scored / eligible</Table.Head>
                <Table.Head class="px-4 py-2 text-right align-middle" aria-sort={currentSort === 'prepared_count' ? (currentDir === 'asc' ? 'ascending' : 'descending') : 'none'}>
                  <button type="button" class="ml-auto inline-flex min-h-[44px] md:min-h-[36px] items-center gap-1.5 font-semibold" onclick={() => toggleColumnSort('prepared_count')}>
                    Prepared / unprinted
                  </button>
                </Table.Head>
                <Table.Head class="px-4 py-2 text-right align-middle" aria-sort={currentSort === 'print_backlog_count' ? (currentDir === 'asc' ? 'ascending' : 'descending') : 'none'}>
                  <button type="button" class="ml-auto inline-flex min-h-[44px] md:min-h-[36px] items-center gap-1.5 font-semibold" onclick={() => toggleColumnSort('print_backlog_count')}>
                    Printed / printable <svelte:component this={sortIndicator('print_backlog_count')} class="size-3.5" aria-hidden="true" />
                  </button>
                </Table.Head>
                <Table.Head class="px-4 py-2 text-right align-middle">Action</Table.Head>
              </Table.Row>
            </Table.Header>
            <Table.Body>
              {#if sessions.length === 0}
                <Table.Row class="border-b border-border/40 hover:bg-muted/30 transition-colors">
                  <Table.Cell colspan={6} class="py-8 text-center text-sm text-muted-foreground">
                    No sessions match the current printing filters.
                  </Table.Cell>
                </Table.Row>
              {:else}
                {#each sessions as session (session.exam_session_id)}
                  <Table.Row class="border-b border-border/40 hover:bg-muted/30 transition-colors">
                    <Table.Cell class="px-4 py-3 font-medium text-foreground">{session.starts_at}</Table.Cell>
                    <Table.Cell class="px-4 py-3">
                      <div class="font-mono font-semibold">{session.exam_code}</div>
                      <div class="text-xs text-muted-foreground">{session.room_name}</div>
                      <Badge variant={session.status === 'finalized' ? 'default' : 'secondary'} class="mt-1 text-[10px]">
                        {session.status === 'finalized' ? 'Grading finalized' : session.status === 'review' ? 'Grading review' : 'Grading open'}
                      </Badge>
                    </Table.Cell>
                    <Table.Cell class="px-4 py-3 text-right font-mono tabular-nums">
                      <div class="font-semibold">Scored {session.scored_count} / {session.examinees_count}</div>
                      <div class="text-xs text-muted-foreground">Eligible, not prepared {session.eligible_unprepared_count}</div>
                    </Table.Cell>
                    <Table.Cell class="px-4 py-3 text-right font-mono tabular-nums">
                      <div class="font-semibold">{session.prepared_count} prepared</div>
                      <div class="text-xs text-muted-foreground">{session.unprinted_count} prepared, not printed</div>
                    </Table.Cell>
                    <Table.Cell class="px-4 py-3 text-right font-mono tabular-nums">
                      <div class="font-semibold text-primary">{session.printed_count} confirmed printed</div>
                      <div class="text-xs text-muted-foreground">{session.print_backlog_count} printable backlog</div>
                    </Table.Cell>
                    <Table.Cell class="px-4 py-3 text-right">
                      <Button href={session.print_url} variant="outline" size="sm" class="min-h-[44px] md:min-h-[36px] gap-1.5"><Printer class="size-3.5" /> Open roster</Button>
                    </Table.Cell>
                  </Table.Row>
                {/each}
              {/if}
            </Table.Body>
          </Table.Root>
          {/snippet}
          {#snippet cards()}
            {#if sessions.length === 0}
              <p class="py-12 text-center text-muted-foreground">No sessions match the current printing filters.</p>
            {:else}
              <ul class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3" role="list">
                {#each sessions as session (session.exam_session_id)}
                  <OperationalCard
                    id={session.exam_session_id}
                    title={session.starts_at}
                    subtitle={`${session.room_name} · Ref ${session.exam_code}`}
                  >
                    {#snippet status()}
                      <Badge variant={session.status === 'finalized' ? 'default' : 'secondary'} class="text-[10px]">
                        {session.status === 'finalized' ? 'Grading finalized' : session.status === 'review' ? 'Grading review' : 'Grading open'}
                      </Badge>
                    {/snippet}
                    {#snippet summary()}
                      <div class="space-y-1">
                        <div class="font-semibold text-foreground">Roster {session.examinees_count} · Scored {session.scored_count}</div>
                        <div class="text-xs text-muted-foreground">
                          Eligible unprepared <strong>{session.eligible_unprepared_count}</strong> · Prepared <strong>{session.prepared_count}</strong> · Not printed <strong>{session.unprinted_count}</strong>
                        </div>
                        <div class="text-xs text-muted-foreground">Printed <strong>{session.printed_count}</strong> · Printable backlog <strong>{session.print_backlog_count}</strong></div>
                      </div>
                    {/snippet}
                    {#snippet actions()}
                      <Button href={session.print_url} variant="outline" size="sm" class="w-full gap-1.5 min-h-9">
                        <Printer class="size-3.5" /> Batch Print
                      </Button>
                    {/snippet}
                  </OperationalCard>
                {/each}
              </ul>
            {/if}
          {/snippet}
        </SwitchableListView>
      </div>
    {/if}
  </div>
</StaffLayout>
