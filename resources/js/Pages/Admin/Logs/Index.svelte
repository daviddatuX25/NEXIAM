<script>
  import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
  import { Link, router } from '@inertiajs/svelte';
  import * as Table from '@/Components/ui/table';
  import * as Select from '@/Components/ui/select';
  import { Button } from '@/Components/ui/button';
  import { Input } from '@/Components/ui/input';
  import SimplePagination from '@/Components/SimplePagination.svelte';
  import { ChevronDown, Filter, Download, ChevronRight, X } from 'lucide-svelte';
  import { formatDateTime } from '@/lib/date-utils';
  import { createImmediateWatch } from '@/lib/auto-filter';

  let { logs, filters = {}, events = [], categories = [], scopeLabel = 'Activity log', showActorFilter = false } = $props();

  const breadcrumbs = [{ label: 'Audit Log' }];

  let filterEvent = $state('');
  let filterCategory = $state('');
  let filterActorId = $state('');
  let filterDateFrom = $state('');
  let filterDateTo = $state('');
  let mobileFiltersDetails = $state(null);
  let detailLog = $state(null);
  let exportLoading = $state(false);
  let exportError = $state(null);

  $effect(() => {
    filterEvent = filters.event ?? '';
    filterCategory = filters.category ?? '';
    filterActorId = filters.actor_id !== undefined && filters.actor_id !== null ? String(filters.actor_id) : '';
    filterDateFrom = filters.date_from ?? '';
    filterDateTo = filters.date_to ?? '';
  });

  function applyFilters() {
    if (mobileFiltersDetails) mobileFiltersDetails.open = false;
    router.get('/admin/logs', {
      event: filterEvent || undefined,
      category: filterCategory || undefined,
      actor_id: filterActorId ? parseInt(filterActorId, 10) : undefined,
      date_from: filterDateFrom || undefined,
      date_to: filterDateTo || undefined,
      page: 1,
    }, { preserveState: true });
  }

  // Auto-apply: all filters immediate (no text search on this page)
  const filterWatch = createImmediateWatch(() => applyFilters());
  $effect(() => { filterEvent; filterCategory; filterActorId; filterDateFrom; filterDateTo; filterWatch(); });

  function actorDisplay(log) {
    if (!log.actor) return '—';
    return log.actor.name ?? log.actor.email ?? `#${log.actor_id}`;
  }

  function eventLabel(value) {
    return events.find((e) => e.value === value)?.label ?? value;
  }

  function categoryLabel(value) {
    return categories.find((c) => c.value === value)?.label ?? value;
  }

  function formatPayload(obj) {
    if (obj == null || (typeof obj === 'object' && Object.keys(obj).length === 0)) return '—';
    return JSON.stringify(obj, null, 2);
  }

  async function doExport() {
    exportError = null;
    exportLoading = true;
    const params = new URLSearchParams();
    if (filterEvent) params.set('event', filterEvent);
    if (filterCategory) params.set('category', filterCategory);
    if (filterActorId) params.set('actor_id', filterActorId);
    if (filterDateFrom) params.set('date_from', filterDateFrom);
    if (filterDateTo) params.set('date_to', filterDateTo);
    try {
      const res = await fetch(`/admin/logs/export?${params.toString()}`, { credentials: 'same-origin' });
      if (!res.ok) {
        const text = await res.text();
        exportError = res.status === 429 ? 'Too many export requests. Please try again in a minute.' : (text || 'Export failed.');
        return;
      }
      const blob = await res.blob();
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `audit-log-${new Date().toISOString().slice(0, 10)}.csv`;
      a.click();
      URL.revokeObjectURL(url);
    } finally {
      exportLoading = false;
    }
  }
</script>

<AuthenticatedLayout {breadcrumbs}>
  <div class="space-y-6 min-w-0">
    <div class="flex items-center justify-between">
      <div>
        <p class="text-sm text-muted-foreground">{scopeLabel}</p>
      </div>
      <div class="flex flex-wrap items-center gap-2 sm:gap-3">
        <Button
          variant="outline"
          class="min-h-[44px] gap-2"
          onclick={doExport}
          disabled={exportLoading}
        >
          <Download class="h-4 w-4" />
          <span class="hidden sm:inline">{exportLoading ? 'Exporting…' : 'Export CSV'}</span>
        </Button>
      </div>
    </div>

    {#if exportError}
      <div class="rounded-lg bg-destructive/10 px-4 py-3 text-sm text-destructive">
        {exportError}
      </div>
    {/if}

    <!-- Filters -->
    <div class="flex flex-col gap-3">
      <div class="hidden md:flex flex-wrap items-center gap-3">
        <Select.Root type="single" bind:value={filterEvent}>
          <Select.Trigger class="w-[170px] min-h-[40px]">
            {#if filterEvent}
              {events.find(e => e.value === filterEvent)?.label || 'All events'}
            {:else}
              <span class="text-muted-foreground">All events</span>
            {/if}
          </Select.Trigger>
          <Select.Content>
            <Select.Item value="" label="All events">All events</Select.Item>
            {#each events as e}
              <Select.Item value={e.value} label={e.label}>{e.label}</Select.Item>
            {/each}
          </Select.Content>
        </Select.Root>
        <Select.Root type="single" bind:value={filterCategory}>
          <Select.Trigger class="w-[160px] min-h-[40px]">
            {#if filterCategory}
              {categories.find(c => c.value === filterCategory)?.label || 'All categories'}
            {:else}
              <span class="text-muted-foreground">All categories</span>
            {/if}
          </Select.Trigger>
          <Select.Content>
            <Select.Item value="" label="All categories">All categories</Select.Item>
            {#each categories as c}
              <Select.Item value={c.value} label={c.label}>{c.label}</Select.Item>
            {/each}
          </Select.Content>
        </Select.Root>
        {#if showActorFilter}
          <Input
            type="number"
            placeholder="Actor ID"
            bind:value={filterActorId}
            class="min-w-[100px] max-w-[120px] h-10"
            min="1"
          />
        {/if}
        <Input type="date" bind:value={filterDateFrom} class="min-h-[40px]" />
        <Input type="date" bind:value={filterDateTo} class="min-h-[40px]" />

      </div>

      <div class="flex flex-col gap-2 md:hidden">
        <div class="flex items-center gap-3">
          <details class="relative group" bind:this={mobileFiltersDetails}>
            <summary class="list-none flex items-center gap-2 min-h-[44px] px-4 rounded-md border border-input bg-background text-sm font-medium cursor-pointer hover:bg-muted/50">
              <Filter class="h-4 w-4" />
              <span>Filters</span>
              <ChevronDown class="h-4 w-4 transition-transform group-open:rotate-180" />
            </summary>
            <div class="absolute right-0 top-full z-10 mt-1 w-[min(320px,calc(100vw-2rem))] rounded-lg border border-border bg-card p-4 shadow-lg flex flex-col gap-3">
              <div>
                <label for="filter-event-mob" class="text-sm font-medium block mb-1">Event</label>
                <Select.Root type="single" bind:value={filterEvent}>
                  <Select.Trigger id="filter-event-mob" class="w-full min-h-[44px]">
                    {#if filterEvent}
                      {events.find(e => e.value === filterEvent)?.label || 'All events'}
                    {:else}
                      <span class="text-muted-foreground">All events</span>
                    {/if}
                  </Select.Trigger>
                  <Select.Content>
                    <Select.Item value="" label="All events">All events</Select.Item>
                    {#each events as e}
                      <Select.Item value={e.value} label={e.label}>{e.label}</Select.Item>
                    {/each}
                  </Select.Content>
                </Select.Root>
              </div>
              <div>
                <label for="filter-category-mob" class="text-sm font-medium block mb-1">Category</label>
                <Select.Root type="single" bind:value={filterCategory}>
                  <Select.Trigger id="filter-category-mob" class="w-full min-h-[44px]">
                    {#if filterCategory}
                      {categories.find(c => c.value === filterCategory)?.label || 'All categories'}
                    {:else}
                      <span class="text-muted-foreground">All categories</span>
                    {/if}
                  </Select.Trigger>
                  <Select.Content>
                    <Select.Item value="" label="All categories">All categories</Select.Item>
                    {#each categories as c}
                      <Select.Item value={c.value} label={c.label}>{c.label}</Select.Item>
                    {/each}
                  </Select.Content>
                </Select.Root>
              </div>
              {#if showActorFilter}
                <div>
                  <label for="filter-actor-mob" class="text-sm font-medium block mb-1">Actor ID</label>
                  <Input
                    id="filter-actor-mob"
                    type="number"
                    bind:value={filterActorId}
                    class="w-full"
                    min="1"
                  />
                </div>
              {/if}
              <div>
                <span class="text-sm font-medium block mb-1">Date range</span>
                <div class="flex items-center gap-2">
                  <Input
                    id="filter-date-from-mob"
                    type="date"
                    bind:value={filterDateFrom}
                    class="flex-1 min-w-0 min-h-[44px]"
                  />
                  <span class="text-muted-foreground">–</span>
                  <Input
                    id="filter-date-to-mob"
                    type="date"
                    bind:value={filterDateTo}
                    class="flex-1 min-w-0 min-h-[44px]"
                  />
                </div>
              </div>
            </div>
          </details>
        </div>

      </div>
    </div>

    <div class="min-w-0 overflow-x-auto scrollbar-hide">
      <Table.Root class="w-full min-w-[640px] text-sm">
        <Table.Header class="bg-muted/50">
          <Table.Row>
            <Table.Head class="px-4 py-3">Time</Table.Head>
            <Table.Head class="px-4 py-3">Event</Table.Head>
            <Table.Head class="px-4 py-3">Category</Table.Head>
            <Table.Head class="px-4 py-3">Summary</Table.Head>
            <Table.Head class="px-4 py-3">Actor</Table.Head>
            <Table.Head class="px-4 py-3">IP</Table.Head>
            <Table.Head class="px-4 py-3 text-right">Details</Table.Head>
          </Table.Row>
        </Table.Header>
        <Table.Body>
          {#each (logs?.data ?? []) as log (log.id)}
            <Table.Row class="border-t border-border hover:bg-muted/30">
              <Table.Cell class="px-4 py-3 text-muted-foreground whitespace-nowrap">
                {formatDateTime(log.created_at)}
              </Table.Cell>
              <Table.Cell class="px-4 py-3">{eventLabel(log.event)}</Table.Cell>
              <Table.Cell class="px-4 py-3">{categoryLabel(log.category)}</Table.Cell>
              <Table.Cell class="px-4 py-3 max-w-[200px] truncate" title={log.summary ?? ''}>
                {log.summary ?? '—'}
              </Table.Cell>
              <Table.Cell class="px-4 py-3">{actorDisplay(log)}</Table.Cell>
              <Table.Cell class="px-4 py-3 text-muted-foreground font-mono text-xs">{log.ip_address ?? '—'}</Table.Cell>
              <Table.Cell class="px-4 py-3 text-right">
                <Button
                  variant="ghost"
                  size="sm"
                  class="min-h-[36px]"
                  onclick={() => (detailLog = log)}
                  aria-label="View details"
                >
                  <ChevronRight class="h-4 w-4" />
                </Button>
              </Table.Cell>
            </Table.Row>
          {:else}
            <Table.Row>
              <Table.Cell colspan={7} class="px-4 py-12 text-center text-muted-foreground">
                No audit entries match the filters.
              </Table.Cell>
            </Table.Row>
          {/each}
        </Table.Body>
      </Table.Root>
      <SimplePagination data={logs} variant="table" />
    </div>
  </div>

  {#if detailLog}
    <div
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
      role="dialog"
      aria-modal="true"
      aria-labelledby="detail-title"
    >
      <div class="rounded-lg bg-card p-6 shadow-lg max-w-2xl w-full max-h-[90vh] overflow-hidden flex flex-col">
        <div class="flex items-center justify-between mb-4">
          <h2 id="detail-title" class="text-lg font-semibold">Audit entry #{detailLog.id}</h2>
          <Button variant="ghost" size="icon" aria-label="Close" onclick={() => (detailLog = null)}>
            <X class="h-4 w-4" />
          </Button>
        </div>
        <div class="grid grid-cols-2 gap-2 text-sm mb-4">
          <span class="text-muted-foreground">Event</span>
          <span>{eventLabel(detailLog.event)}</span>
          <span class="text-muted-foreground">Time</span>
          <span>{formatDateTime(detailLog.created_at)}</span>
          <span class="text-muted-foreground">Actor</span>
          <span>{actorDisplay(detailLog)}</span>
          <span class="text-muted-foreground">IP</span>
          <span class="font-mono text-xs">{detailLog.ip_address ?? '—'}</span>
        </div>
        <div class="flex-1 min-h-0 overflow-auto space-y-4">
          <div>
            <h3 class="text-sm font-medium mb-1">Before (old_values)</h3>
            <pre class="rounded bg-muted p-3 text-xs overflow-x-auto">{formatPayload(detailLog.old_values)}</pre>
          </div>
          <div>
            <h3 class="text-sm font-medium mb-1">After (new_values)</h3>
            <pre class="rounded bg-muted p-3 text-xs overflow-x-auto">{formatPayload(detailLog.new_values)}</pre>
          </div>
        </div>
        <div class="mt-4 flex justify-end">
          <Button variant="outline" onclick={() => (detailLog = null)}>Close</Button>
        </div>
      </div>
    </div>
  {/if}
</AuthenticatedLayout>