<script>
  import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
  import { Link, router, usePage } from '@inertiajs/svelte';
  import { Button } from '@/Components/ui/button';
  import { Badge } from '@/Components/ui/badge';
  import { Input } from '@/Components/ui/input';
  import { Checkbox } from '@/Components/ui/checkbox';
  import * as Select from '@/Components/ui/select';
  import * as Table from '@/Components/ui/table';
  import { Filter, ChevronDown, CheckCircle, XCircle, UploadCloud, Plus, Pencil, Search, ShieldCheck, RotateCcw, ChevronsUpDown, X, Printer, Download } from 'lucide-svelte';
  import SwitchableListView from '@/Components/SwitchableListView.svelte';
  import SimplePagination from '@/Components/SimplePagination.svelte';
  import { success as showSuccess, error as showError } from '@/lib/toast';
  import { pipelineBadgeVariant, pipelineStatusLabel, pipelineStatusOptions } from '@/lib/pipeline-helpers';
  import { formatDate } from '@/lib/date-utils';
  import { createDebouncedWatch, createImmediateWatch } from '@/lib/auto-filter';
  import { onMount } from 'svelte';

  let { applications, filters = {}, seasons = [], active_season_id = null, statuses = [], admission_slip_enabled = false } = $props();

  const page = usePage();
  const authUser = $derived($page.props.auth?.user ?? null);
  const roles = $derived(authUser?.roles?.map((r) => r.name) ?? []);
  function hasRole(r) { return roles.includes(r); }

  // Show toasts on mount for flash messages
  onMount(() => {
    const flash = $page.props.flash;
    if (flash?.success) showSuccess(flash.success);
    if (flash?.error) showError(flash.error);

    // Initialize filters after mount
    initFilters();
  });

  let filterSearch = $state('');
  let filterStatus = $state('');
  let filterAcademicYearId = $state('');
  let filterDateFrom = $state('');
  let filterDateTo = $state('');
  let filtersOpen = $state(false);
  let sortField = $state('');
  let sortDirection = $state('asc');

  // Initialize filters - runs when props change
  function initFilters() {
    filterSearch = filters.search ?? '';
    filterStatus = filters.pipeline_status ?? '';
    sortField = filters.sort ?? '';
    sortDirection = filters.direction ?? 'asc';

    // Auto-select active season if no filter provided
    const seasonFromFilter = filters.academic_year_id;
    if (seasonFromFilter) {
      filterAcademicYearId = String(seasonFromFilter);
    } else if (active_season_id != null) {
      filterAcademicYearId = active_season_id;
    } else {
      filterAcademicYearId = '';
    }

    // Dates are not auto-populated from season window
    filterDateFrom = filters.date_from ?? '';
    filterDateTo = filters.date_to ?? '';
  }

  // React to prop changes
  $effect(() => {
    // Track dependencies
    const _ = filters.search;
    const __ = filters.pipeline_status;
    const ____ = filters.academic_year_id;
    const _____ = filters.date_from;
    const ______ = filters.date_to;
    const _______ = filters.sort;
    const ________ = filters.direction;
    const _________ = active_season_id;
    const __________ = seasons.length;

    initFilters();
  });

  function applyFilters() {
    router.get('/admin/applications', {
      search: filterSearch || undefined,
      pipeline_status: filterStatus || undefined,
      academic_year_id: filterAcademicYearId || undefined,
      date_from: filterDateFrom || undefined,
      date_to: filterDateTo || undefined,
      sort: sortField || undefined,
      direction: sortDirection || undefined,
      page: 1,
    }, { preserveState: true });
    filtersOpen = false;
  }

  // Auto-apply: search debounced, dropdowns/dates immediate
  const searchWatch = createDebouncedWatch(() => applyFilters());
  const filterWatch = createImmediateWatch(() => applyFilters());
  $effect(() => { filterSearch; searchWatch(); });
  $effect(() => { filterStatus; filterAcademicYearId; filterDateFrom; filterDateTo; filterWatch(); });

  function doAccept(id) {
    router.put(`/admin/applications/${id}/accept`, {}, {
      onSuccess: () => {},
    });
  }

  function doDismiss(id) {
    if (confirm('Are you sure you want to dismiss this application?')) {
      router.put(`/admin/applications/${id}/dismiss`, {}, {
        onSuccess: () => {},
      });
    }
  }

  function seasonLabel(season) {
    if (!season) return '—';
    return `${season.academic_year ?? ''} – ${season.semester ?? ''}`.trim() || '—';
  }

  const list = $derived(applications?.data ?? []);
  let viewMode = $state('responsive');

  // ── Bulk Selection ──
  let selectedIds = $state(new Set());
  let bulkProcessing = $state(false);

  const selectedCount = $derived(selectedIds.size);
  const hasSelection = $derived(selectedCount > 0);

  // Determine unique statuses of selected items
  const selectedStatuses = $derived(() => {
    const statusSet = new Set();
    for (const app of list) {
      if (selectedIds.has(app.id)) {
        statusSet.add(app.pipeline_status ?? app.status ?? 'pending');
      }
    }
    return statusSet;
  });

  // Smart bulk action availability
  const allPending = $derived(() => {
    const s = selectedStatuses();
    return s.size === 1 && s.has('pending');
  });

  const allAccepted = $derived(() => {
    const s = selectedStatuses();
    return s.size === 1 && s.has('accepted');
  });

  const allDismissed = $derived(() => {
    const s = selectedStatuses();
    return s.size === 1 && s.has('dismissed');
  });

  const allRevertable = $derived(() => {
    const s = selectedStatuses();
    return s.size > 0 && [...s].every(st => st === 'accepted' || st === 'dismissed');
  });

  const isMixed = $derived(() => {
    const s = selectedStatuses();
    return s.size > 1;
  });

  // Bulk action description
  const bulkActionHint = $derived(() => {
    if (!hasSelection) return '';
    if (allPending()) return 'All selected are pending — you can Accept or Dismiss.';
    if (allAccepted()) return 'All selected are accepted — you can Revert to Pending.';
    if (allDismissed()) return 'All selected are dismissed — you can Revert to Pending.';
    if (allRevertable()) return 'Selected are accepted/dismissed — you can Revert to Pending.';
    return 'Mixed statuses selected — bulk actions are limited.';
  });

  // Header checkbox state
  const allOnPageSelected = $derived(list.length > 0 && list.every(app => selectedIds.has(app.id)));
  const someOnPageSelected = $derived(list.some(app => selectedIds.has(app.id)) && !allOnPageSelected);

  function toggleSelectAll() {
    if (allOnPageSelected) {
      // Deselect all on current page
      const next = new Set(selectedIds);
      for (const app of list) next.delete(app.id);
      selectedIds = next;
    } else {
      // Select all on current page
      const next = new Set(selectedIds);
      for (const app of list) next.add(app.id);
      selectedIds = next;
    }
  }

  function toggleSelect(id) {
    const next = new Set(selectedIds);
    if (next.has(id)) {
      next.delete(id);
    } else {
      next.add(id);
    }
    selectedIds = next;
  }

  function clearSelection() {
    selectedIds = new Set();
  }

  // Clear selection when navigating pages
  $effect(() => {
    const _ = applications?.current_page;
    selectedIds = new Set();
  });

  // ── Bulk Actions ──
  function doBulkAccept() {
    if (!confirm(`Accept ${selectedCount} selected application(s)?`)) return;
    bulkProcessing = true;
    router.post('/admin/applications/bulk-accept', { ids: [...selectedIds] }, {
      onSuccess: () => { clearSelection(); bulkProcessing = false; },
      onError: () => { bulkProcessing = false; },
    });
  }

  function doBulkDismiss() {
    if (!confirm(`Dismiss ${selectedCount} selected application(s)?`)) return;
    bulkProcessing = true;
    router.post('/admin/applications/bulk-dismiss', { ids: [...selectedIds] }, {
      onSuccess: () => { clearSelection(); bulkProcessing = false; },
      onError: () => { bulkProcessing = false; },
    });
  }

  function doBulkReopen() {
    if (!confirm(`Revert ${selectedCount} selected application(s) to pending?`)) return;
    bulkProcessing = true;
    router.post('/admin/applications/bulk-reopen', { ids: [...selectedIds] }, {
      onSuccess: () => { clearSelection(); bulkProcessing = false; },
      onError: () => { bulkProcessing = false; },
    });
  }

  function doBulkPrintSlips() {
    const ids = [...selectedIds];
    if (ids.length === 1) {
      window.open(`/admin/applications/${ids[0]}/admission-slip/print`, '_blank', 'noopener');
    } else {
      router.visit('/admin/applications/admission-slips/bulk-print', {
        method: 'post',
        data: { ids },
        preserveState: false,
      });
    }
  }

  function doBulkDownloadSlipsPdf() {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '/admin/applications/admission-slips/bulk-pdf';
    form.style.display = 'none';
    const csrfInput = document.createElement('input');
    csrfInput.type = 'hidden';
    csrfInput.name = '_token';
    csrfInput.value = $page.props.csrf_token ?? '';
    form.appendChild(csrfInput);
    [...selectedIds].forEach((id) => {
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = 'ids[]';
      input.value = id;
      form.appendChild(input);
    });
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
  }

  const breadcrumbs = $derived([{ label: 'Applications' }]);
</script>

<AuthenticatedLayout breadcrumbs={breadcrumbs}>
  <div class="space-y-6 min-w-0">
    <div class="flex items-center justify-between">
      <div>
        <p class="mt-1 text-sm text-muted-foreground">View and manage applications by season</p>
      </div>
      <div class="flex flex-wrap items-center gap-2 sm:gap-3">
        {#if hasRole('super_admin') || hasRole('staff') || hasRole('registrar_administrator')}
          <Link href="/admin/applications/create">
            <Button class="min-h-[44px] gap-2">
              <Plus class="h-4 w-4" />
              <span class="hidden sm:inline">Create</span>
            </Button>
          </Link>
        {/if}
        {#if hasRole('super_admin')}
          <Link href="/admin/applications/import">
            <Button variant="outline" class="min-h-[44px] gap-2">
              <UploadCloud class="h-4 w-4" />
              <span class="hidden sm:inline">Import</span>
            </Button>
          </Link>
        {/if}
        {#if hasRole('super_admin') || hasRole('staff') || hasRole('registrar_administrator')}
          <Link href="/admin/privacy-policies">
            <Button variant="outline" class="min-h-[44px] gap-2">
              <ShieldCheck class="h-4 w-4" />
              <span class="hidden sm:inline">Privacy Policy</span>
            </Button>
          </Link>
        {/if}
      </div>
    </div>

    <!-- Filters -->
    <div class="flex flex-col gap-3">
      <div class="flex flex-wrap items-center gap-3">
        <div class="relative min-w-[160px] max-w-[220px] md:max-w-[220px] flex-1 min-w-0 md:flex-none">
          <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground pointer-events-none" />
          <Input
            type="search"
            placeholder="Search applicants..."
            bind:value={filterSearch}
            class="pl-8 min-h-[44px] md:min-h-[40px] h-10 w-full"
          />
        </div>
        <details class="relative md:hidden" bind:open={filtersOpen}>
          <summary class="flex cursor-pointer list-none items-center gap-2 rounded-lg border border-input bg-background px-3 py-2 text-sm min-h-[44px] min-w-[44px]">
            <Filter class="h-4 w-4" />
            <span>Filters</span>
            <ChevronDown class="h-4 w-4 transition-transform [details[open]_&]:rotate-180" />
          </summary>
          <div class="absolute right-0 top-full z-10 mt-1 w-[min(320px,calc(100vw-2rem))] rounded-lg border border-border bg-card p-4 shadow-lg">
            <div class="space-y-3">
              <label for="filter-status-mobile" class="block text-sm font-medium">Status</label>
              <Select.Root type="single" bind:value={filterStatus}>
                <Select.Trigger id="filter-status-mobile" class="w-full min-h-[44px]">
                  {#if filterStatus}
                    {statuses.find(s => String(s.value) === String(filterStatus))?.label || 'All'}
                  {:else}
                    <span class="text-muted-foreground">All</span>
                  {/if}
                </Select.Trigger>
                <Select.Content>
                  <Select.Item value="" label="All">All</Select.Item>
                  {#each statuses as s}
                    <Select.Item value={String(s.value)} label={s.label}>{s.label}</Select.Item>
                  {/each}
                </Select.Content>
              </Select.Root>
              
              <label for="filter-season-mobile" class="block text-sm font-medium">Season</label>
              <Select.Root type="single" bind:value={filterAcademicYearId}>
                <Select.Trigger id="filter-season-mobile" class="w-full min-h-[44px]">
                  {#if filterAcademicYearId}
                    {seasonLabel(seasons.find(s => String(s.id) === String(filterAcademicYearId))) || 'Active'}
                  {:else}
                    <span class="text-muted-foreground">Active</span>
                  {/if}
                </Select.Trigger>
                <Select.Content>
                  <Select.Item value="" label="Active">Active</Select.Item>
                  {#each seasons as s}
                    <Select.Item value={String(s.id)} label={seasonLabel(s)}>{seasonLabel(s)}</Select.Item>
                  {/each}
                </Select.Content>
              </Select.Root>
              <div class="grid grid-cols-2 gap-2">
                <div>
                  <label for="filter-date-from-mobile" class="block text-xs text-muted-foreground">From</label>
                  <Input id="filter-date-from-mobile" type="date" bind:value={filterDateFrom} class="w-full min-h-[44px]" />
                </div>
                <div>
                  <label for="filter-date-to-mobile" class="block text-xs text-muted-foreground">To</label>
                  <Input id="filter-date-to-mobile" type="date" bind:value={filterDateTo} class="w-full min-h-[44px]" />
                </div>
              </div>
            </div>
          </div>
        </details>
        <div class="hidden md:flex md:items-end md:gap-3">
          <div>
            <label class="text-xs font-medium text-muted-foreground block mb-1">Status</label>
            <Select.Root type="single" bind:value={filterStatus}>
              <Select.Trigger class="w-[150px] min-h-[40px]">
                {#if filterStatus}
                  {statuses.find(s => String(s.value) === String(filterStatus))?.label || 'All statuses'}
                {:else}
                  <span class="text-muted-foreground">All statuses</span>
                {/if}
              </Select.Trigger>
              <Select.Content>
                <Select.Item value="" label="All statuses">All statuses</Select.Item>
                {#each statuses as s}
                  <Select.Item value={String(s.value)} label={s.label}>{s.label}</Select.Item>
                {/each}
              </Select.Content>
            </Select.Root>
          </div>
          
          <div>
            <label class="text-xs font-medium text-muted-foreground block mb-1">Season</label>
            <Select.Root type="single" bind:value={filterAcademicYearId}>
              <Select.Trigger class="w-[200px] min-h-[40px]">
                {#if filterAcademicYearId}
                  {@const sel = seasons.find(s => String(s.id) === String(filterAcademicYearId))}
                  {sel ? seasonLabel(sel) : 'Active season'}
                {:else}
                  <span class="text-muted-foreground">Active season</span>
                {/if}
              </Select.Trigger>
              <Select.Content>
                <Select.Item value="" label="Active season">Active season</Select.Item>
                {#each seasons as s}
                  <Select.Item value={String(s.id)} label={seasonLabel(s)}>
                    {seasonLabel(s)}
                    {#if s.is_active}
                      <Badge variant="success" class="ml-2 text-[10px] px-1.5 py-0">Active</Badge>
                    {/if}
                  </Select.Item>
                {/each}
              </Select.Content>
            </Select.Root>
          </div>
          <div>
            <label class="text-xs font-medium text-muted-foreground block mb-1">From</label>
            <Input type="date" bind:value={filterDateFrom} class="min-h-[40px]" placeholder="From" />
          </div>
          <div>
            <label class="text-xs font-medium text-muted-foreground block mb-1">To</label>
            <Input type="date" bind:value={filterDateTo} class="min-h-[40px]" placeholder="To" />
          </div>
        </div>

      </div>
    </div>

    <!-- Bulk Actions Bar -->
    {#if hasSelection}
      <div class="flex flex-wrap items-center gap-3 rounded-lg border border-primary/20 bg-primary/5 px-4 py-3 text-sm animate-in slide-in-from-top-2 duration-200">
        <div class="flex items-center gap-2 font-medium">
          <span class="inline-flex items-center justify-center min-w-[1.5rem] h-6 rounded-full bg-primary text-primary-foreground text-xs font-bold px-1.5">
            {selectedCount}
          </span>
          <span>selected</span>
        </div>

        <span class="text-muted-foreground text-xs hidden sm:inline">·</span>
        <span class="text-muted-foreground text-xs hidden sm:inline">{bulkActionHint()}</span>

        <div class="flex items-center gap-2 ml-auto">
          {#if allPending()}
            <Button
              size="sm"
              variant="default"
              class="gap-1.5 bg-green-600 hover:bg-green-700 text-white"
              onclick={doBulkAccept}
              disabled={bulkProcessing}
            >
              <CheckCircle class="h-3.5 w-3.5" />
              Accept All
            </Button>
            <Button
              size="sm"
              variant="destructive"
              class="gap-1.5"
              onclick={doBulkDismiss}
              disabled={bulkProcessing}
            >
              <XCircle class="h-3.5 w-3.5" />
              Dismiss All
            </Button>
          {:else if allRevertable()}
            <Button
              size="sm"
              variant="outline"
              class="gap-1.5"
              onclick={doBulkReopen}
              disabled={bulkProcessing}
            >
              <RotateCcw class="h-3.5 w-3.5" />
              Revert to Pending
            </Button>
          {:else}
            <span class="text-xs text-muted-foreground italic">No bulk actions for mixed statuses</span>
          {/if}

          {#if admission_slip_enabled && allAccepted()}
            <Button
              size="sm"
              variant="outline"
              class="gap-1.5"
              onclick={doBulkPrintSlips}
            >
              <Printer class="h-3.5 w-3.5" />
              Print Slips
            </Button>
            <Button
              size="sm"
              variant="outline"
              class="gap-1.5"
              onclick={doBulkDownloadSlipsPdf}
            >
              <Download class="h-3.5 w-3.5" />
              Download PDFs
            </Button>
          {/if}

          <Button
            size="sm"
            variant="ghost"
            class="gap-1 text-muted-foreground"
            onclick={clearSelection}
          >
            <X class="h-3.5 w-3.5" />
            Clear
          </Button>
        </div>
      </div>
    {/if}

    <SwitchableListView bind:viewMode overflow="auto">
      {#snippet table()}
        <Table.Root class="w-full min-w-[640px] text-sm">
          <Table.Header class="bg-muted/50">
            <Table.Row>
              <Table.Head class="px-2 py-3 w-10">
                <Checkbox
                  checked={allOnPageSelected}
                  indeterminate={someOnPageSelected}
                  onCheckedChange={toggleSelectAll}
                  aria-label="Select all on page"
                />
              </Table.Head>
              <Table.Head class="px-4 py-3">Applicant No.</Table.Head>
              <Table.Head class="px-4 py-3">Name</Table.Head>
              <Table.Head class="px-4 py-3">Email</Table.Head>
              <Table.Head class="px-4 py-3 cursor-pointer select-none" onclick={() => { sortField = 'pipeline_status'; sortDirection = sortDirection === 'asc' ? 'desc' : 'asc'; applyFilters(); }}>
                Status
                {#if sortField === 'pipeline_status'}
                  <span class="ml-1 text-xs">{sortDirection === 'asc' ? '↑' : '↓'}</span>
                {/if}
              </Table.Head>
              <Table.Head class="px-4 py-3">Submitted</Table.Head>
              <Table.Head class="text-center">Actions</Table.Head>
            </Table.Row>
          </Table.Header>
          <Table.Body>
            {#each list as app}
              <Table.Row class="border-t border-border hover:bg-muted/30 {selectedIds.has(app.id) ? 'bg-primary/5' : ''}">
                <Table.Cell class="px-2 py-3" onclick={(e) => e.stopPropagation()}>
                  <Checkbox
                    checked={selectedIds.has(app.id)}
                    onCheckedChange={() => toggleSelect(app.id)}
                    aria-label="Select {app.reference_number}"
                  />
                </Table.Cell>
                <Table.Cell class="px-4 py-3 font-mono text-xs cursor-pointer" onclick={() => router.visit(`/admin/applications/${app.id}`)}>{app.reference_number ?? '—'}</Table.Cell>
                <Table.Cell class="px-4 py-3 cursor-pointer" onclick={() => router.visit(`/admin/applications/${app.id}`)}>{app.full_name ?? '—'}</Table.Cell>
                <Table.Cell class="px-4 py-3 text-muted-foreground cursor-pointer" onclick={() => router.visit(`/admin/applications/${app.id}`)}>{app.email ?? '—'}</Table.Cell>
                <Table.Cell class="px-4 py-3 cursor-pointer" onclick={() => router.visit(`/admin/applications/${app.id}`)}>
                  <Badge variant={pipelineBadgeVariant(app.pipeline_status)}>{pipelineStatusLabel(app.pipeline_status)}</Badge>
                </Table.Cell>
                <Table.Cell class="px-4 py-3 text-muted-foreground cursor-pointer" onclick={() => router.visit(`/admin/applications/${app.id}`)}>
                  {formatDate(app.submitted_at, 'short')}
                </Table.Cell>
                <Table.Cell class="text-center" onclick={(e) => e.stopPropagation()}>
                  <div class="inline-flex gap-2" onclick={(e) => e.stopPropagation()}>
                    <Link href={`/admin/applications/${app.id}/edit`}>
                      <Button variant="ghost" size="sm" class="h-8 px-2 text-xs font-semibold hover:bg-muted">
                        <Pencil class="mr-1.5 h-3.5 w-3.5" />
                        Edit
                      </Button>
                    </Link>
                    {#if app.status === 'pending'}
                      <Button
                        variant="ghost"
                        size="sm"
                        class="h-8 px-2 text-xs font-semibold text-green-600 hover:text-green-700 hover:bg-green-50"
                        onclick={(e) => { e.stopPropagation(); doAccept(app.id); }}
                      >
                        <CheckCircle class="mr-1.5 h-3.5 w-3.5" />
                        Accept
                      </Button>
                      <Button
                        variant="ghost"
                        size="sm"
                        class="h-8 px-2 text-xs font-semibold text-destructive hover:text-destructive hover:bg-destructive/5"
                        onclick={(e) => { e.stopPropagation(); doDismiss(app.id); }}
                      >
                        <XCircle class="mr-1.5 h-3.5 w-3.5" />
                        Dismiss
                      </Button>
                    {/if}
                    {#if admission_slip_enabled && (app.pipeline_status === 'accepted' || app.status === 'accepted')}
                      <Link href={`/admin/applications/${app.id}/admission-slip/print`} target="_blank">
                        <Button variant="ghost" size="sm" class="h-8 px-2 text-xs font-semibold hover:bg-muted">
                          <Printer class="mr-1.5 h-3.5 w-3.5" />
                          Slip
                        </Button>
                      </Link>
                    {/if}
                  </div>
                </Table.Cell>
              </Table.Row>
            {:else}
              <Table.Row>
                <Table.Cell colspan="7" class="px-4 py-12 text-center text-muted-foreground">
                  No applications match the filters. Try a different season or search.
                </Table.Cell>
              </Table.Row>
            {/each}
          </Table.Body>
        </Table.Root>
        <SimplePagination data={applications} variant="table" />
      {/snippet}

      {#snippet cards()}
        <!-- Card view: select all toggle -->
        {#if list.length > 0}
          <div class="flex items-center gap-2 mb-3 px-1">
            <Checkbox
              checked={allOnPageSelected}
              indeterminate={someOnPageSelected}
              onCheckedChange={toggleSelectAll}
              aria-label="Select all on page"
            />
            <span class="text-sm text-muted-foreground">Select all on page</span>
          </div>
        {/if}
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          {#each list as app}
            <div
              class="rounded-xl border bg-card p-4 shadow-sm cursor-pointer hover:bg-muted/30 transition-colors {selectedIds.has(app.id) ? 'border-primary/40 bg-primary/5' : 'border-border'}"
              onclick={(e) => e.stopPropagation()}
              onkeydown={(e) => e.key === 'Enter' && router.visit(`/admin/applications/${app.id}`)}
              role="button"
              tabindex="0"
            >
              <div class="flex items-start gap-3">
                <div class="pt-0.5" onclick={(e) => e.stopPropagation()}>
                  <Checkbox
                    checked={selectedIds.has(app.id)}
                    onCheckedChange={() => toggleSelect(app.id)}
                    aria-label="Select {app.reference_number}"
                  />
                </div>
                <div class="flex-1 min-w-0">
                  <p class="font-mono text-xs text-muted-foreground" onclick={() => router.visit(`/admin/applications/${app.id}`)}>{app.reference_number ?? '—'}</p>
                  <p class="mt-1 font-medium" onclick={() => router.visit(`/admin/applications/${app.id}`)}>{app.full_name ?? '—'}</p>
                  <p class="text-sm text-muted-foreground" onclick={() => router.visit(`/admin/applications/${app.id}`)}>{app.email ?? '—'}</p>
                  <p class="mt-2" onclick={() => router.visit(`/admin/applications/${app.id}`)}>
                    <Badge variant={pipelineBadgeVariant(app.pipeline_status)}>{pipelineStatusLabel(app.pipeline_status)}</Badge>
                  </p>
                  <p class="mt-1 text-xs text-muted-foreground" onclick={() => router.visit(`/admin/applications/${app.id}`)}>
                    {formatDate(app.submitted_at, 'short')}
                  </p>
                  <div class="mt-3 flex gap-2" onclick={(e) => e.stopPropagation()}>
                    <Link href={`/admin/applications/${app.id}/edit`} class="flex-1" onclick={(e) => e.stopPropagation()}>
                      <Button variant="outline" size="sm" class="w-full min-h-[40px] font-semibold">
                        <Pencil class="h-3.5 w-3.5 mr-1.5" />
                        Edit
                      </Button>
                    </Link>
                    {#if app.status === 'pending'}
                      <Button
                        variant="outline"
                        size="sm"
                        class="flex-1 min-h-[40px] font-semibold text-green-600 border-green-200 hover:bg-green-50 hover:text-green-700"
                        onclick={(e) => { e.stopPropagation(); doAccept(app.id); }}
                      >
                        <CheckCircle class="h-3.5 w-3.5 mr-1.5" />
                        Accept
                      </Button>
                      <Button
                        variant="outline"
                        size="sm"
                        class="flex-1 min-h-[40px] font-semibold text-destructive border-destructive/20 hover:bg-destructive/5"
                        onclick={(e) => { e.stopPropagation(); doDismiss(app.id); }}
                      >
                        <XCircle class="h-3.5 w-3.5 mr-1.5" />
                        Dismiss
                      </Button>
                    {/if}
                    {#if admission_slip_enabled && (app.pipeline_status === 'accepted' || app.status === 'accepted')}
                      <Link href={`/admin/applications/${app.id}/admission-slip/print`} target="_blank" onclick={(e) => e.stopPropagation()}>
                        <Button variant="outline" size="sm" class="min-h-[40px] font-semibold">
                          <Printer class="h-3.5 w-3.5 mr-1.5" />
                          Slip
                        </Button>
                      </Link>
                    {/if}
                  </div>
                </div>
              </div>
            </div>
          {:else}
            <div class="col-span-full rounded-xl border border-dashed border-border bg-muted/20 py-12 text-center text-muted-foreground">
              No applications match the filters.
            </div>
          {/each}
        </div>
        <SimplePagination data={applications} variant="centered" />
      {/snippet}
    </SwitchableListView>
  </div>
</AuthenticatedLayout>