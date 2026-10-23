<script>
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
  import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
  import SwitchableListView from '@/Components/kit/SwitchableListView.svelte';
  import SortableTableHead from '@/Components/kit/SortableTableHead.svelte';
  import BulkActionBar from '@/Components/kit/BulkActionBar.svelte';
  import ActiveFilterChips from '@/Components/kit/ActiveFilterChips.svelte';
  import { Badge } from '@/Components/ui/badge';
  import { Button } from '@/Components/ui/button';
  import { Input } from '@/Components/ui/input';
  import { Label } from '@/Components/ui/label';
  import * as Table from '@/Components/ui/table';
  import * as Dialog from '@/Components/ui/dialog';
  import { Link, router } from '@inertiajs/svelte';
  import { success as toastSuccess, error as toastError } from '@/lib/toast.js';
  import { csrfHeaders, newIdempotencyKey } from '@/lib/utils.js';
  import { Layers, CheckCircle2, Users, AlertTriangle, Search, X, Calendar, Filter, SlidersHorizontal, ArrowUpDown, Pencil, LayoutGrid, ArrowUpRight, Clock, Ban } from 'lucide-svelte';

  let { cycle = null, cycles = [], selected_program = null, program_hub_url = null, quota_projections = [], quotas = [], registrar_grants = [], registrar_candidates = [], can_manage_registrar_grants = false, is_program_head = false } = $props();
  let contextLoading = $state(false);
  let contextError = $state('');
  let search = $state('');
  let statusFilter = $state('all');
  let viewMode = $state('responsive');
  let sortBy = $state('code');
  let sortDirection = $state('asc');
  let selectedIds = $state([]);
  let dialogOpen = $state(false);
  let editing = $state(null);
  let slots = $state('');
  let reason = $state('');
  let saving = $state(false);
  let expectedVersion = $state(0);
  let idempotencyKey = $state('');
  let attemptedPayload = $state('');
  let conflict = $state(null);
  let conflictReviewed = $state(false);
  let saveError = $state('');
  let saveErrorKind = $state('');
  let validationErrors = $state({});
  let reviewedPayload = $state('');
  let bulkVersions = $state({});

  const rows = $derived.by(() => {
    const query = search.trim().toLocaleLowerCase();
    return quota_projections.filter((row) => {
      const text = `${row.program.code} ${row.program.name} ${row.quota?.last_notice ?? ''}`.toLocaleLowerCase();
      if (query && !text.includes(query)) return false;
      const status = row.over_capacity > 0 ? 'over_capacity' : !row.quota ? 'unconfigured' : row.remaining === 0 ? 'at_capacity' : 'available';
      return statusFilter === 'all' || statusFilter === 'configured' && !!row.quota || statusFilter === 'unconfigured' && !row.quota || statusFilter === status;
    }).sort((a, b) => {
      const av = sortBy === 'code' ? a.program.code : sortBy === 'name' ? a.program.name : sortBy === 'slots' ? (a.quota?.slots ?? -1) : sortBy === 'committed' ? a.commitments : sortBy === 'remaining' ? (a.remaining ?? -1) : sortBy === 'utilization' ? utilization(a) : statusFor(a);
      const bv = sortBy === 'code' ? b.program.code : sortBy === 'name' ? b.program.name : sortBy === 'slots' ? (b.quota?.slots ?? -1) : sortBy === 'committed' ? b.commitments : sortBy === 'remaining' ? (b.remaining ?? -1) : sortBy === 'utilization' ? utilization(b) : statusFor(b);
      const result = typeof av === 'string' ? av.localeCompare(bv) : av - bv;
      return sortDirection === 'asc' ? result : -result;
    });
  });
  const selectedRows = $derived(rows.filter((row) => selectedIds.includes(row.program.id) && canWrite(row)));
  const hasWritableRows = $derived(rows.some(canWrite));
  const capacity = $derived(rows.reduce((sum, row) => sum + (row.quota?.slots ?? 0), 0));
  const committed = $derived(rows.reduce((sum, row) => sum + row.commitments, 0));
  const overCapacity = $derived(rows.filter((row) => row.over_capacity > 0).length);
  const configured = $derived(rows.filter((row) => row.quota).length);
  const activeFilters = $derived([
    ...(search.trim() ? [{ id: 'search', label: `Search: ${search.trim()}` }] : []),
    ...(statusFilter !== 'all' ? [{ id: 'status', label: `Status: ${statusLabel(statusFilter)}` }] : []),
  ]);

  function canWrite(row) { return row?.can_manage === true && row?.write_enabled === true; }
  function utilization(row) { return row.quota?.slots > 0 ? row.commitments / row.quota.slots : 0; }
  function statusFor(row) { return row.over_capacity > 0 ? 'over_capacity' : !row.quota ? 'unconfigured' : row.remaining === 0 ? 'at_capacity' : 'available'; }
  function statusLabel(status) { return ({ configured: 'Quota set', unconfigured: 'Not set', available: 'Seats available', at_capacity: 'At capacity', over_capacity: 'Over capacity' })[status] ?? 'All statuses'; }
  function statusIcon(row) { return row.over_capacity > 0 ? AlertTriangle : !row.quota ? Ban : row.remaining === 0 ? Clock : CheckCircle2; }
  function sort(key) { if (sortBy === key) sortDirection = sortDirection === 'asc' ? 'desc' : 'asc'; else { sortBy = key; sortDirection = 'asc'; } }
  function namedRoute(name, fallback) { return typeof route === 'function' ? route(name) : fallback; }
  function sortState(key) { return sortBy !== key ? 'none' : sortDirection === 'asc' ? 'ascending' : 'descending'; }
  function changeCycle(value) {
    if (contextLoading) return;
    contextError = '';
    router.get('/setup/quotas', { cycle_id: value, ...(selected_program ? { program_id: selected_program.id } : {}) }, { preserveState: true, preserveScroll: true, onStart: () => { contextLoading = true; }, onError: () => { contextError = 'This academic year and semester could not be opened.'; toastError(contextError); }, onFinish: () => { contextLoading = false; } });
  }
  function clearFilters() { search = ''; statusFilter = 'all'; }
  function removeFilter(id) { if (id === 'search') search = ''; if (id === 'status') statusFilter = 'all'; }
  function toggleSelected(id) { selectedIds = selectedIds.includes(id) ? selectedIds.filter((item) => item !== id) : [...selectedIds, id]; }
  function toggleAll(event) { selectedIds = event.currentTarget.checked ? rows.filter(canWrite).map((row) => row.program.id) : []; }
  function resetSaveState() {
    saveError = '';
    saveErrorKind = '';
    validationErrors = {};
    conflict = null;
    conflictReviewed = false;
    attemptedPayload = '';
    reviewedPayload = '';
    bulkVersions = {};
  }
  function openEdit(row = null) {
    if (row ? !canWrite(row) : selectedRows.length === 0) return;
    editing = row;
    slots = String(row?.quota?.slots ?? '');
    reason = '';
    expectedVersion = Number(row?.quota?.version ?? 0);
    idempotencyKey = newIdempotencyKey();
    resetSaveState();
    bulkVersions = row ? {} : Object.fromEntries(selectedRows.map((selectedRow) => [selectedRow.program.id, Number(selectedRow.quota?.version ?? 0)]));
    dialogOpen = true;
  }
  function conflictReviewFingerprint() {
    if (!conflict?.current) return '';
    const programId = Number(conflict.current.program_id);
    return JSON.stringify({
      slots: Number(slots),
      reason: reason.trim(),
      expected_version: editing ? expectedVersion : Number(bulkVersions[programId] ?? 0),
      ...(editing ? {} : { program_id: programId }),
    });
  }
  function reviewConflict() {
    if (!conflict?.current || saving) return;
    const programId = Number(conflict.current.program_id ?? editing?.program.id);
    idempotencyKey = newIdempotencyKey();
    if (editing) {
      expectedVersion = Number(conflict.current.version);
    } else {
      bulkVersions = { ...bulkVersions, [programId]: Number(conflict.current.version) };
    }
    attemptedPayload = '';
    conflictReviewed = true;
    reviewedPayload = conflictReviewFingerprint();
    saveError = '';
    saveErrorKind = '';
  }
  function requestHeaders() {
    return {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      ...csrfHeaders(),
    };
  }
  async function submitQuota() {
    const bulk = !editing;
    if (saving || !cycle || (bulk ? selectedRows.length === 0 : !canWrite(editing)) || (conflict && (!conflictReviewed || reviewedPayload !== conflictReviewFingerprint()))) return;
    const normalizedReason = reason.trim();
    const memberPayloads = bulk
      ? selectedRows.map((row) => ({
        program_id: Number(row.program.id),
        slots: Number(slots),
        reason: normalizedReason,
        expected_version: Number(bulkVersions[row.program.id] ?? row.quota?.version ?? 0),
      }))
      : [{
        program_id: Number(editing.program.id),
        slots: Number(slots),
        reason: normalizedReason,
        expected_version: expectedVersion,
      }];
    const payload = bulk
      ? { academic_cycle_id: Number(cycle.id), quotas: memberPayloads }
      : { ...memberPayloads[0], academic_cycle_id: Number(cycle.id) };
    const fingerprint = JSON.stringify(payload);
    if (attemptedPayload && attemptedPayload !== fingerprint) {
      idempotencyKey = newIdempotencyKey();
    }
    attemptedPayload = fingerprint;
    saving = true;
    saveError = '';
    saveErrorKind = '';
    validationErrors = {};
    try {
      const response = await fetch(namedRoute(bulk ? 'setup.quotas.bulk-store' : 'setup.quotas.store', bulk ? '/setup/quotas/bulk' : '/setup/quotas'), {
        method: 'POST',
        credentials: 'same-origin',
        headers: requestHeaders(),
        body: JSON.stringify(bulk
          ? { academic_cycle_id: payload.academic_cycle_id, idempotency_key: idempotencyKey, quotas: memberPayloads }
          : { ...payload, idempotency_key: idempotencyKey }),
      });
      const result = await response.json().catch(() => ({}));
      if (response.status === 409 && result.code === 'quota_version_conflict' && result.current) {
        conflict = result;
        conflictReviewed = false;
        reviewedPayload = '';
        saveErrorKind = 'conflict';
        saveError = result.message || 'This quota changed after you opened it. Review the current version before resubmitting.';
        return;
      }
      if (response.status === 409) {
        saveErrorKind = 'conflict';
        saveError = result.message || 'One or more quotas changed. The current values could not be confirmed.';
        return;
      }
      if (response.status === 422) {
        validationErrors = result.errors ?? {};
        saveErrorKind = 'validation';
        saveError = result.message || 'Review the highlighted fields and try again.';
        return;
      }
      if (response.status === 403) {
        saveErrorKind = 'denied';
        saveError = 'You no longer have permission to change this program quota.';
        return;
      }
      if (!response.ok) {
        saveErrorKind = 'unknown';
        saveError = 'The save outcome could not be confirmed. Retry without changing your entries to safely check the same request.';
        return;
      }
      dialogOpen = false;
      selectedIds = [];
      toastSuccess('Program quota saved.');
      router.reload({ preserveScroll: true });
    } catch {
      saveErrorKind = 'unknown';
      saveError = 'The save outcome could not be confirmed. Retry without changing your entries to safely check the same request.';
    } finally {
      saving = false;
    }
  }
  const breadcrumbs = [{ label: 'Setup', href: '/setup' }, { label: 'Quotas' }];
</script>

<svelte:head><title>Quotas · Setup</title></svelte:head>
<StaffLayout {breadcrumbs}>
  <div class="min-w-0 space-y-6 p-4 sm:p-6">
    <PageHeaderBar title={selected_program ? `${selected_program.code} — Program quota` : 'Program quotas'} description={selected_program ? selected_program.name : 'Compare quota capacity and commitments across your authorized programs.'}>
      {#snippet actions()}
        {#if program_hub_url && !contextLoading}
          <Link href={program_hub_url} class="inline-flex min-h-11 items-center gap-2 rounded-md border border-input px-3 text-sm font-medium hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">Back to program hub<ArrowUpRight class="size-4" aria-hidden="true" /></Link>
        {/if}
        <Link href="/setup/programs"><Button variant="outline" size="sm">Explore Degree Programs</Button></Link><Link href="/setup"><Button variant="outline" size="sm">Back to readiness</Button></Link>
      {/snippet}
    </PageHeaderBar>
    <div class="sticky top-0 z-20 space-y-3 border-b border-border/60 bg-background/90 py-3 backdrop-blur-md">
      <div class="flex flex-col gap-3 xl:flex-row xl:items-end">
        <div class="relative min-w-0 flex-1"><Search class="pointer-events-none absolute left-3 top-3.5 size-4 text-muted-foreground"/><Input aria-label="Search programs" bind:value={search} placeholder="Search code, program name, or notice" class="min-h-11 pl-9 pr-10"/>{#if search}<button type="button" aria-label="Clear search" class="absolute right-2 top-2 inline-flex size-7 items-center justify-center rounded hover:bg-muted" onclick={() => search = ''}><X class="size-4"/></button>{/if}</div>
        <label class="flex min-w-56 items-center gap-2"><Calendar class="size-4 shrink-0 text-muted-foreground"/><select aria-label="Academic year and semester" value={cycle ? String(cycle.id) : ''} onchange={(event) => changeCycle(event.currentTarget.value)} disabled={contextLoading || !cycles.length} class="min-h-11 w-full rounded-md border border-input bg-background px-3 text-sm">{#each cycles as item (item.id)}<option value={String(item.id)}>{item.staff_label || item.name || item.code}</option>{/each}</select></label>
        <label class="flex min-w-52 items-center gap-2"><Filter class="size-4 shrink-0 text-muted-foreground"/><select aria-label="Quota status filter" bind:value={statusFilter} class="min-h-11 w-full rounded-md border border-input bg-background px-3 text-sm"><option value="all">All statuses</option><option value="configured">Quota set</option><option value="unconfigured">Not set</option><option value="available">Seats available</option><option value="at_capacity">At capacity</option><option value="over_capacity">Over capacity</option></select></label>
        <label class="flex min-w-48 items-center gap-2"><ArrowUpDown class="size-4 shrink-0 text-muted-foreground"/><select aria-label="Sort programs" value={sortBy} onchange={(event) => sortBy = event.currentTarget.value} class="min-h-11 w-full rounded-md border border-input bg-background px-3 text-sm"><option value="code">Program code</option><option value="name">Program name</option><option value="slots">Capacity</option><option value="committed">Committed</option><option value="remaining">Remaining</option><option value="utilization">Utilization</option><option value="status">Status</option></select><Button variant="outline" class="min-h-11" aria-label="Toggle sort direction" onclick={() => sortDirection = sortDirection === 'asc' ? 'desc' : 'asc'}>{sortDirection === 'asc' ? 'Asc' : 'Desc'}</Button></label>
      </div>
      <ActiveFilterChips chips={activeFilters} onRemove={removeFilter} onClearAll={clearFilters}/>
    </div>
    {#if contextError}<p role="alert" class="text-sm text-destructive">{contextError}</p>{/if}
    {#if !cycle}<p class="rounded-xl border border-border bg-card p-4 text-sm text-muted-foreground">Select an authorized academic year and semester to view quotas.</p>
    {:else}
      <dl aria-label="Quota summary" class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-xl border bg-card p-4"><dt class="flex items-center gap-2 text-sm text-muted-foreground"><Layers class="size-4"/>Programs in view</dt><dd class="mt-1 text-2xl font-semibold">{rows.length}<span class="ml-2 text-xs font-normal text-muted-foreground">of {quota_projections.length} total</span></dd></div>
        <div class="rounded-xl border bg-card p-4"><dt class="flex items-center gap-2 text-sm text-muted-foreground"><CheckCircle2 class="size-4"/>Configured quotas</dt><dd class="mt-1 text-2xl font-semibold">{configured}<span class="text-sm font-normal text-muted-foreground"> / {rows.length}</span></dd></div>
        <div class="rounded-xl border bg-card p-4"><dt class="flex items-center gap-2 text-sm text-muted-foreground"><Users class="size-4"/>Committed / capacity</dt><dd class="mt-1 text-2xl font-semibold">{committed}<span class="text-sm font-normal text-muted-foreground"> / {capacity}</span></dd><div class="mt-2 h-2 overflow-hidden rounded-full bg-muted"><div class="h-full bg-primary" style={`width: ${capacity ? Math.min(100, committed / capacity * 100) : 0}%`}></div></div></div>
        <div class="rounded-xl border bg-card p-4"><dt class="flex items-center gap-2 text-sm text-muted-foreground"><AlertTriangle class="size-4"/>Over capacity</dt><dd class="mt-1 text-2xl font-semibold">{overCapacity}</dd></div>
      </dl>
      {#if hasWritableRows}
        <BulkActionBar count={selectedRows.length} hint="Set one capacity for all selected programs" onClear={() => selectedIds = []}>
          {#snippet actions()}<Button class="min-h-10 gap-2" disabled={!selectedRows.length} onclick={() => openEdit()}><SlidersHorizontal class="size-4"/>Bulk Set Quota ({selectedRows.length})</Button>{/snippet}
        </BulkActionBar>
      {/if}
      {#if rows.length === 0}<p class="rounded-xl border border-dashed bg-card p-8 text-center text-sm text-muted-foreground">No programs match these filters. Clear filters to view programs.</p>
      {:else}
        <SwitchableListView bind:viewMode cardsInset={false}>
          {#snippet table()}
            <Table.Root class="w-full min-w-[1100px] text-sm"><Table.Header><Table.Row>
              <Table.Head>{#if hasWritableRows}<input type="checkbox" aria-label="Select all manageable programs" checked={rows.filter(canWrite).length > 0 && rows.filter(canWrite).every((row) => selectedIds.includes(row.program.id))} onchange={toggleAll}/>{/if}</Table.Head>
              <SortableTableHead label="Program" direction={sortState('code')} onclick={() => sort('code')}/><SortableTableHead label="Capacity" direction={sortState('slots')} align="right" onclick={() => sort('slots')}/><SortableTableHead label="Committed" direction={sortState('committed')} align="right" onclick={() => sort('committed')}/><SortableTableHead label="Remaining" direction={sortState('remaining')} align="right" onclick={() => sort('remaining')}/><SortableTableHead label="Utilization" direction={sortState('utilization')} onclick={() => sort('utilization')}/><SortableTableHead label="Status" direction={sortState('status')} onclick={() => sort('status')}/><Table.Head>Actions</Table.Head>
            </Table.Row></Table.Header><Table.Body>{#each rows as row (row.program.id)}<Table.Row>
              <Table.Cell>{#if canWrite(row)}<input type="checkbox" aria-label={`Select ${row.program.code}`} checked={selectedIds.includes(row.program.id)} onchange={() => toggleSelected(row.program.id)}/>{/if}</Table.Cell>
              <Table.Cell><strong>{row.program.code}</strong><span class="block text-xs text-muted-foreground">{row.program.name}</span></Table.Cell><Table.Cell class="text-right tabular-nums">{row.quota?.slots ?? 'Not set'}</Table.Cell><Table.Cell class="text-right tabular-nums">{row.commitments}</Table.Cell><Table.Cell class="text-right tabular-nums">{row.remaining ?? '—'}</Table.Cell>
              <Table.Cell><div class="flex min-w-24 items-center gap-2"><div class="h-2 flex-1 overflow-hidden rounded-full bg-muted"><div class="h-full bg-primary" style={`width: ${Math.min(100, utilization(row) * 100)}%`}></div></div><span class="text-xs tabular-nums">{row.quota ? `${Math.round(utilization(row) * 100)}%` : '—'}</span></div></Table.Cell>
              <Table.Cell>{@const StatusIcon = statusIcon(row)}<Badge variant={row.over_capacity > 0 ? 'destructive' : 'secondary'} class="gap-1"><StatusIcon class="size-3.5"/>{row.over_capacity > 0 ? 'Over capacity' : !row.quota ? 'Not set' : row.remaining === 0 ? 'At capacity' : 'Available'}</Badge>{#if row.quota?.updated_at}<p class="mt-1 text-xs text-muted-foreground">Updated {new Date(row.quota.updated_at).toLocaleDateString()}</p>{/if}{#if row.quota?.last_notice}<p class="mt-1 max-w-52 truncate text-xs text-muted-foreground" title={row.quota.last_notice}>{row.quota.last_notice}</p>{/if}</Table.Cell>
              <Table.Cell><div class="flex flex-wrap gap-2">{#if canWrite(row)}<Button variant="outline" size="sm" class="gap-1.5" onclick={() => openEdit(row)}><Pencil class="size-4"/>{row.quota ? 'Edit Quota' : 'Set Quota'}</Button>{/if}<Link href={row.hub_url}><Button variant="ghost" size="sm" class="gap-1.5"><LayoutGrid class="size-4"/><span class="sr-only sm:not-sr-only">Program Hub</span><ArrowUpRight class="size-3.5"/></Button></Link></div></Table.Cell>
            </Table.Row>{/each}</Table.Body></Table.Root>
          {/snippet}
          {#snippet cards()}
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">{#each rows as row (row.program.id)}{@const CardStatusIcon = statusIcon(row)}<article class="space-y-4 rounded-xl border bg-card p-4 shadow-sm">
              <div class="flex items-start justify-between gap-3"><div><h3 class="font-semibold">{row.program.code}</h3><p class="text-sm text-muted-foreground">{row.program.name}</p></div>{#if canWrite(row)}<input type="checkbox" aria-label={`Select ${row.program.code}`} checked={selectedIds.includes(row.program.id)} onchange={() => toggleSelected(row.program.id)}/>{/if}</div>
              <Badge variant={row.over_capacity > 0 ? 'destructive' : 'secondary'} class="gap-1"><CardStatusIcon class="size-3.5"/>{row.over_capacity > 0 ? 'Over capacity' : !row.quota ? 'Not set' : row.remaining === 0 ? 'At capacity' : 'Seats available'}</Badge>
              <div class="grid grid-cols-3 gap-2 text-sm"><div><p class="text-xs text-muted-foreground">Capacity</p><p class="font-semibold tabular-nums">{row.quota?.slots ?? '—'}</p></div><div><p class="text-xs text-muted-foreground">Committed</p><p class="font-semibold tabular-nums">{row.commitments}</p></div><div><p class="text-xs text-muted-foreground">Remaining</p><p class="font-semibold tabular-nums">{row.remaining ?? '—'}</p></div></div>
              <div><div class="mb-1 flex justify-between text-xs text-muted-foreground"><span>Seat utilization</span><span>{row.quota ? `${Math.round(utilization(row) * 100)}%` : 'Not configured'}</span></div><div class="h-2 overflow-hidden rounded-full bg-muted"><div class="h-full bg-primary" style={`width: ${Math.min(100, utilization(row) * 100)}%`}></div></div></div>
              {#if row.quota}<div class="border-t pt-3 text-xs text-muted-foreground">{row.quota.updated_at ? `Updated ${new Date(row.quota.updated_at).toLocaleDateString()}` : ''}{#if row.quota.last_notice}<p class="mt-1">{row.quota.last_notice}</p>{/if}</div>{/if}
              <div class="flex flex-wrap gap-2">{#if canWrite(row)}<Button variant="outline" class="min-h-10 gap-2" onclick={() => openEdit(row)}><Pencil class="size-4"/>{row.quota ? 'Edit Quota' : 'Set Quota'}</Button>{/if}<Link href={row.hub_url}><Button variant="ghost" class="min-h-10 gap-2"><LayoutGrid class="size-4"/>Program Hub<ArrowUpRight class="size-4"/></Button></Link></div>
            </article>{/each}</div>
          {/snippet}
        </SwitchableListView>
      {/if}
    {/if}
  </div>
  <Dialog.Root bind:open={dialogOpen}>
    <Dialog.Content class="max-h-[90dvh] overflow-y-auto sm:max-w-xl">
      <Dialog.Header>
        <Dialog.Title>{editing ? (editing.quota ? 'Edit program quota' : 'Set program quota') : 'Bulk set program quotas'}</Dialog.Title>
        <Dialog.Description>{cycle?.staff_label || cycle?.name}. Capacity updates preserve current reservations.</Dialog.Description>
      </Dialog.Header>
      {#if editing}
        <p class="text-sm text-muted-foreground" data-testid="quota-version">Current version: {expectedVersion}</p>
      {:else}
        <div class="flex flex-wrap gap-2">{#each selectedRows as row (row.program.id)}<Badge variant="outline">{row.program.code} · version {bulkVersions[row.program.id] ?? row.quota?.version ?? 0}</Badge>{/each}</div>
      {/if}
      {#if conflict && saveErrorKind === 'conflict'}
        <section class="space-y-2 rounded-md border border-destructive/40 bg-destructive/5 p-3" role="alert" data-testid="quota-conflict">
          <p class="font-medium">Quota conflict</p>
          <p>{saveError}</p>
          <p>{editing ? editing.program.code : quota_projections.find((row) => Number(row.program.id) === Number(conflict.current.program_id))?.program.code} has saved capacity {conflict.current.slots}; current version is {conflict.current.version}.</p>
          <p>Your capacity and reason remain unchanged. Review this current version before resubmitting.</p>
          <Button type="button" variant="outline" disabled={saving} onclick={reviewConflict}>Review current version</Button>
        </section>
      {:else if saveError}
        <p role={saveErrorKind === 'denied' ? 'alert' : 'status'} data-testid={`quota-${saveErrorKind}-error`}>{saveError}</p>
      {/if}
      <form class="space-y-4" onsubmit={(event) => { event.preventDefault(); submitQuota(); }}>
        <div class="space-y-2">
          <Label for="quota-slots">Capacity slots</Label>
          <Input id="quota-slots" aria-describedby="quota-slots-error" type="number" min="0" max="10000" step="1" bind:value={slots} required/>
          <p class="text-xs text-muted-foreground">Enter the total capacity for this academic year and semester.</p>
          {#if validationErrors.slots}<p id="quota-slots-error" class="text-sm text-destructive">{validationErrors.slots[0]}</p>{/if}
        </div>
        <div class="space-y-2">
          <Label for="quota-reason">Reason for change</Label>
          <textarea id="quota-reason" aria-describedby="quota-reason-error" bind:value={reason} maxlength="500" rows="3" required class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" placeholder="Explain this capacity change"></textarea>
          {#if validationErrors.reason}<p id="quota-reason-error" class="text-sm text-destructive">{validationErrors.reason[0]}</p>{/if}
        </div>
        <div class="flex justify-end gap-2">
          <Button type="button" variant="outline" onclick={() => dialogOpen = false}>Cancel</Button>
          <Button type="submit" disabled={saving || (!editing && selectedRows.length === 0) || (conflict && (!conflictReviewed || reviewedPayload !== conflictReviewFingerprint()))}>{saving ? 'Saving…' : conflict ? 'Resubmit reviewed quota' : 'Save quota'}</Button>
        </div>
      </form>
    </Dialog.Content>
  </Dialog.Root>
</StaffLayout>
