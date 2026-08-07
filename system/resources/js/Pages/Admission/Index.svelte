<script>
  /**
   * Registrar applications queue — action visibility follows legacy Index + GF-06:
   *
   * | status            | list primary actions                          |
   * | pending           | Accept · Dismiss                              |
   * | needs_correction  | Accept · Dismiss (awaiting applicant fix)     |
   * | dismissed         | Revert to pending                             |
   * | archived          | Revert to pending                             |
   * | accepted          | (no decide actions on list; slip later)       |
   *
   * Not on list: Archive (retention, never peer of Accept/Dismiss),
   * Request correction / in-place Correct (detail journey — D-074).
   * "Flag" was a mistaken label for CorrectAdmissionAttempt.
   */
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
  import E2PageState from './E2PageState.svelte';
  import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
  import FilterToolbar from '@/Components/kit/FilterToolbar.svelte';
  import ActiveFilterChips from '@/Components/kit/ActiveFilterChips.svelte';
  import SwitchableListView from '@/Components/kit/SwitchableListView.svelte';
  import SimplePagination from '@/Components/kit/SimplePagination.svelte';
  import { Badge } from '@/Components/ui/badge';
  import { Button } from '@/Components/ui/button';
  import * as Select from '@/Components/ui/select';
  import * as Table from '@/Components/ui/table';
  import { Link, router } from '@inertiajs/svelte';
  import { CheckCircle, XCircle, RotateCcw, GraduationCap, PanelRightOpen } from 'lucide-svelte';

  let {
    page_state = 'ready',
    attempts,
    filters = {},
    statuses = [],
    cycles = [],
    status_counts = {},
    capabilities = { accept: false, dismiss: false, correct: false },
  } = $props();

  let viewMode = $state('responsive');
  let filterSearch = $state(filters.search ?? '');
  let filterStatus = $state(filters.status ?? '');
  let filterCycle = $state(filters.cycle ?? '');
  let debounceTimer;
  let searchReady = false;

  function applyFilters(overrides = {}) {
    const search = overrides.search !== undefined ? overrides.search : filterSearch;
    const status = overrides.status !== undefined ? overrides.status : filterStatus;
    const cycle = overrides.cycle !== undefined ? overrides.cycle : filterCycle;

    router.get(
      '/admissions',
      {
        search: search || undefined,
        status: status || undefined,
        cycle: cycle || undefined,
        page: 1,
      },
      { preserveState: true, replace: true },
    );
  }

  $effect(() => {
    const value = filterSearch;
    if (!searchReady) {
      searchReady = true;
      return;
    }
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => applyFilters({ search: value }), 300);
  });

  function clearChip(id) {
    if (id === 'search') {
      filterSearch = '';
      applyFilters({ search: '' });
    } else if (id === 'status') {
      filterStatus = '';
      applyFilters({ status: '' });
    } else if (id === 'cycle') {
      filterCycle = '';
      applyFilters({ cycle: '' });
    }
  }

  function clearAllFilters() {
    filterSearch = '';
    filterStatus = '';
    filterCycle = '';
    applyFilters({ search: '', status: '', cycle: '' });
  }

  const chips = $derived(
    [
      filterSearch ? { id: 'search', label: `Search: ${filterSearch}` } : null,
      filterStatus
        ? {
            id: 'status',
            label: `Status: ${statuses.find((s) => s.value === filterStatus)?.label ?? filterStatus}`,
          }
        : null,
      filterCycle
        ? {
            id: 'cycle',
            label: `Cycle: ${cycles.find((c) => c.value === filterCycle)?.label ?? filterCycle}`,
          }
        : null,
    ].filter(Boolean),
  );

  function statusVariant(status) {
    switch (status) {
      case 'accepted':
        return 'default';
      case 'pending':
        return 'secondary';
      case 'needs_correction':
        return 'outline';
      case 'dismissed':
        return 'destructive';
      default:
        return 'secondary';
    }
  }

  function statusLabel(status) {
    return statuses.find((s) => s.value === status)?.label ?? status;
  }

  function formatDate(iso) {
    if (!iso) return '—';
    try {
      return new Date(iso).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
      });
    } catch {
      return iso;
    }
  }

  function initials(name) {
    const parts = String(name ?? '')
      .trim()
      .split(/\s+/)
      .filter(Boolean);
    if (parts.length === 0) return '?';
    if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
  }

  /** Decide path — only while still in the intake queue (legacy Index). */
  function canDecide(attempt) {
    return attempt.status === 'pending' || attempt.status === 'needs_correction';
  }

  /** Non-destructive reopen — GF command allows dismissed + archived only. */
  function canRevert(attempt) {
    return attempt.status === 'dismissed' || attempt.status === 'archived';
  }

  function accept(attempt) {
    router.post(`/admissions/${attempt.id}/accept`, {
      expected_version: attempt.version,
    });
  }

  function dismiss(attempt) {
    const reason = window.prompt('Dismissal reason (required):');
    if (!reason || !reason.trim()) return;
    router.post(`/admissions/${attempt.id}/dismiss`, {
      expected_version: attempt.version,
      reason: reason.trim(),
    });
  }

  function reopen(attempt) {
    const reason = window.prompt('Reason for reverting to pending (required):');
    if (!reason || !reason.trim()) return;
    router.post(`/admissions/${attempt.id}/reopen`, {
      expected_version: attempt.version,
      reason: reason.trim(),
    });
  }

  const breadcrumbs = [{ label: 'Admissions' }];
  const rows = $derived(attempts?.data ?? []);
</script>

{#snippet rowControls(attempt, dense = false)}
  <div class="flex flex-wrap items-center {dense ? 'justify-end gap-1' : 'gap-2'}">
    <Link href={`/admissions/${attempt.id}`}>
      <Button
        size="sm"
        variant="outline"
        class="{dense ? 'h-8 px-2 text-xs' : 'min-h-10'} font-semibold"
      >
        <PanelRightOpen class={dense ? 'mr-1 h-3.5 w-3.5' : 'mr-1.5 h-4 w-4'} />
        Open
      </Button>
    </Link>
    {#if capabilities.accept && canDecide(attempt)}
      <Button
        size="sm"
        variant="ghost"
        class="{dense ? 'h-8 px-2 text-xs' : 'min-h-10'} font-semibold text-emerald-700 hover:bg-emerald-50 hover:text-emerald-800 dark:text-emerald-400 dark:hover:bg-emerald-950/40"
        onclick={() => accept(attempt)}
      >
        <CheckCircle class={dense ? 'mr-1 h-3.5 w-3.5' : 'mr-1.5 h-4 w-4'} />
        Accept
      </Button>
    {/if}
    {#if capabilities.dismiss && canDecide(attempt)}
      <Button
        size="sm"
        variant="ghost"
        class="{dense ? 'h-8 px-2 text-xs' : 'min-h-10'} font-semibold text-destructive hover:bg-destructive/5"
        onclick={() => dismiss(attempt)}
      >
        <XCircle class={dense ? 'mr-1 h-3.5 w-3.5' : 'mr-1.5 h-4 w-4'} />
        Dismiss
      </Button>
    {/if}
    {#if capabilities.accept && canRevert(attempt)}
      <Button
        size="sm"
        variant="outline"
        class="{dense ? 'h-8 px-2 text-xs' : 'min-h-10'} font-semibold"
        onclick={() => reopen(attempt)}
      >
        <RotateCcw class={dense ? 'mr-1 h-3.5 w-3.5' : 'mr-1.5 h-4 w-4'} />
        Revert to pending
      </Button>
    {/if}
    {#if attempt.status === 'accepted'}
      <span class="text-xs text-muted-foreground">In pipeline</span>
    {/if}
  </div>
{/snippet}

<svelte:head><title>Admissions</title></svelte:head>

<StaffLayout {breadcrumbs}>
  <div class="space-y-4 min-w-0 p-4 sm:p-6">
    <PageHeaderBar
      title="Applications"
      description="List: Accept / Dismiss / Revert. Open an application for corrections, archive, and access-key tools."
    />

    <E2PageState state={page_state} />

    {#if page_state !== 'denied'}
      <div class="flex flex-wrap gap-2">
        <Button
          size="sm"
          variant={!filterStatus ? 'default' : 'outline'}
          class="h-8"
          onclick={() => {
            filterStatus = '';
            applyFilters({ status: '' });
          }}
        >
          All ({status_counts.all ?? 0})
        </Button>
        {#each statuses as s}
          <Button
            size="sm"
            variant={filterStatus === s.value ? 'default' : 'outline'}
            class="h-8"
            onclick={() => {
              filterStatus = s.value;
              applyFilters({ status: s.value });
            }}
          >
            {s.label} ({status_counts[s.value] ?? 0})
          </Button>
        {/each}
      </div>

      <FilterToolbar bind:search={filterSearch} searchPlaceholder="Search name, email, reference…">
        {#snippet desktopFilters()}
          <Select.Root
            type="single"
            value={filterStatus}
            onValueChange={(v) => {
              filterStatus = v ?? '';
              applyFilters({ status: filterStatus });
            }}
          >
            <Select.Trigger class="min-h-10 w-[170px]">
              {#if filterStatus}
                {statuses.find((s) => s.value === filterStatus)?.label || 'Status'}
              {:else}
                <span class="text-muted-foreground">All statuses</span>
              {/if}
            </Select.Trigger>
            <Select.Content>
              <Select.Item value="" label="All statuses">All statuses</Select.Item>
              {#each statuses as s}
                <Select.Item value={s.value} label={s.label}>{s.label}</Select.Item>
              {/each}
            </Select.Content>
          </Select.Root>

          <Select.Root
            type="single"
            value={filterCycle}
            onValueChange={(v) => {
              filterCycle = v ?? '';
              applyFilters({ cycle: filterCycle });
            }}
          >
            <Select.Trigger class="min-h-10 w-[190px]">
              {#if filterCycle}
                {cycles.find((c) => c.value === filterCycle)?.label || 'Cycle'}
              {:else}
                <span class="text-muted-foreground">All cycles</span>
              {/if}
            </Select.Trigger>
            <Select.Content>
              <Select.Item value="" label="All cycles">All cycles</Select.Item>
              {#each cycles as c}
                <Select.Item value={c.value} label={c.label}>{c.label}</Select.Item>
              {/each}
            </Select.Content>
          </Select.Root>
        {/snippet}

        {#snippet mobileFilters()}
          <div class="space-y-3">
            <div>
              <p class="mb-1 text-xs font-medium text-muted-foreground">Status</p>
              <Select.Root
                type="single"
                value={filterStatus}
                onValueChange={(v) => {
                  filterStatus = v ?? '';
                  applyFilters({ status: filterStatus });
                }}
              >
                <Select.Trigger class="min-h-11 w-full">
                  {filterStatus
                    ? statuses.find((s) => s.value === filterStatus)?.label
                    : 'All statuses'}
                </Select.Trigger>
                <Select.Content>
                  <Select.Item value="" label="All statuses">All statuses</Select.Item>
                  {#each statuses as s}
                    <Select.Item value={s.value} label={s.label}>{s.label}</Select.Item>
                  {/each}
                </Select.Content>
              </Select.Root>
            </div>
            <div>
              <p class="mb-1 text-xs font-medium text-muted-foreground">Cycle</p>
              <Select.Root
                type="single"
                value={filterCycle}
                onValueChange={(v) => {
                  filterCycle = v ?? '';
                  applyFilters({ cycle: filterCycle });
                }}
              >
                <Select.Trigger class="min-h-11 w-full">
                  {filterCycle ? cycles.find((c) => c.value === filterCycle)?.label : 'All cycles'}
                </Select.Trigger>
                <Select.Content>
                  <Select.Item value="" label="All cycles">All cycles</Select.Item>
                  {#each cycles as c}
                    <Select.Item value={c.value} label={c.label}>{c.label}</Select.Item>
                  {/each}
                </Select.Content>
              </Select.Root>
            </div>
          </div>
        {/snippet}
      </FilterToolbar>

      <ActiveFilterChips chips={chips} onRemove={clearChip} onClearAll={clearAllFilters} />

      {#if rows.length === 0}
        <p class="rounded-lg border border-dashed border-border p-8 text-center text-sm text-muted-foreground">
          No applications match these filters.
        </p>
      {:else}
        <SwitchableListView bind:viewMode>
          {#snippet table()}
            <Table.Root class="w-full min-w-[720px] text-sm">
              <Table.Header class="bg-muted/50">
                <Table.Row>
                  <Table.Head class="px-3 py-2">Reference</Table.Head>
                  <Table.Head class="px-3 py-2">Applicant</Table.Head>
                  <Table.Head class="px-3 py-2">Program</Table.Head>
                  <Table.Head class="px-3 py-2">Status</Table.Head>
                  <Table.Head class="px-3 py-2">Submitted</Table.Head>
                  <Table.Head class="px-3 py-2 text-right">Actions</Table.Head>
                </Table.Row>
              </Table.Header>
              <Table.Body>
                {#each rows as attempt (attempt.id)}
                  <Table.Row class="align-top">
                    <Table.Cell class="px-3 py-2.5 font-mono text-xs font-medium">
                      <Link href={`/admissions/${attempt.id}`} class="hover:underline">
                        {attempt.reference_code}
                      </Link>
                    </Table.Cell>
                    <Table.Cell class="px-3 py-2.5">
                      <div class="min-w-0">
                        <Link href={`/admissions/${attempt.id}`} class="truncate font-medium hover:underline">
                          {attempt.applicant_name}
                        </Link>
                        <p class="truncate text-xs text-muted-foreground">{attempt.contact_email}</p>
                        {#if attempt.status === 'needs_correction'}
                          <p class="mt-0.5 text-xs text-amber-700 dark:text-amber-400">
                            Awaiting applicant correction
                          </p>
                        {/if}
                      </div>
                    </Table.Cell>
                    <Table.Cell class="px-3 py-2.5">
                      <span class="font-medium">{attempt.preferred_program_code ?? '—'}</span>
                      {#if (attempt.program_preferences?.length ?? 0) > 1}
                        <p class="text-xs text-muted-foreground">
                          +{attempt.program_preferences.length - 1} more
                        </p>
                      {/if}
                    </Table.Cell>
                    <Table.Cell class="px-3 py-2.5">
                      <Badge variant={statusVariant(attempt.status)} class="capitalize">
                        {statusLabel(attempt.status)}
                      </Badge>
                    </Table.Cell>
                    <Table.Cell class="px-3 py-2.5 text-muted-foreground">
                      {formatDate(attempt.submitted_at)}
                    </Table.Cell>
                    <Table.Cell class="px-3 py-2.5">
                      {@render rowControls(attempt, true)}
                    </Table.Cell>
                  </Table.Row>
                {/each}
              </Table.Body>
            </Table.Root>
          {/snippet}

          {#snippet cards()}
            <!-- Operate polish: status via Badge only — no left stripe, wash, or nested cards (Impeccable craft-floor). -->
            <ul class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3" role="list">
              {#each rows as attempt (attempt.id)}
                <li class="flex flex-col rounded-xl border border-border bg-card p-4 transition-colors hover:bg-muted/20">
                  <div class="flex items-start gap-3">
                    <div
                      class="flex size-10 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-semibold tracking-wide text-muted-foreground"
                      aria-hidden="true"
                    >
                      {initials(attempt.applicant_name)}
                    </div>
                      <div class="min-w-0 flex-1">
                      <div class="flex items-start justify-between gap-2">
                        <Link
                          href={`/admissions/${attempt.id}`}
                          class="font-mono text-[11px] text-muted-foreground hover:underline"
                        >
                          {attempt.reference_code}
                        </Link>
                        <Badge variant={statusVariant(attempt.status)} class="shrink-0 capitalize">
                          {statusLabel(attempt.status)}
                        </Badge>
                      </div>
                      <Link
                        href={`/admissions/${attempt.id}`}
                        class="mt-0.5 block truncate font-medium leading-snug hover:underline"
                      >
                        {attempt.applicant_name}
                      </Link>
                      <p class="truncate text-sm text-muted-foreground">{attempt.contact_email}</p>
                    </div>
                  </div>

                  <div class="mt-3 flex items-start gap-2 text-sm">
                    <GraduationCap class="mt-0.5 size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                    <div class="min-w-0">
                      <p class="truncate font-medium">{attempt.preferred_program_code ?? 'No preference'}</p>
                      {#if (attempt.program_preferences?.length ?? 0) > 1}
                        <p class="text-xs text-muted-foreground">
                          {attempt.program_preferences.join(' · ')}
                        </p>
                      {/if}
                    </div>
                  </div>

                  <p class="mt-2 text-xs text-muted-foreground">
                    Submitted {formatDate(attempt.submitted_at)}
                    {#if attempt.cycle_name}
                      <span aria-hidden="true"> · </span>{attempt.cycle_name}
                    {/if}
                  </p>

                  {#if attempt.status === 'needs_correction'}
                    <p class="mt-2 text-xs text-muted-foreground">Awaiting applicant correction.</p>
                  {/if}

                  <div class="mt-auto pt-3">
                    {@render rowControls(attempt, false)}
                  </div>
                </li>
              {/each}
            </ul>
          {/snippet}
        </SwitchableListView>

        <SimplePagination data={attempts} />
      {/if}
    {/if}
  </div>
</StaffLayout>
