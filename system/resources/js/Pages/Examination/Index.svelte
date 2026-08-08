<script>
  /**
   * Exam sessions Index — legacy Admin/TestScheduling/Index shape:
   * list + filters + Create CTA; no inline create form; row opens Show.
   */
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
  import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
  import SwitchableListView from '@/Components/kit/SwitchableListView.svelte';
  import { Badge } from '@/Components/ui/badge';
  import { Button } from '@/Components/ui/button';
  import { Input } from '@/Components/ui/input';
  import * as Select from '@/Components/ui/select';
  import * as Table from '@/Components/ui/table';
  import { Link, router } from '@inertiajs/svelte';
  import { Eye, Pencil, Plus, Search } from 'lucide-svelte';

  let {
    page_state = 'ready',
    sessions = [],
    filters = {},
    statuses = [],
    cycles = [],
  } = $props();

  let viewMode = $state('responsive');
  let filterSearch = $state(filters.search ?? '');
  let filterStatus = $state(filters.status ?? '');
  let filterCycleId = $state(filters.academic_cycle_id ? String(filters.academic_cycle_id) : '');
  let debounceTimer;

  function applyFilters() {
    router.get(
      '/exams/sessions',
      {
        search: filterSearch || undefined,
        status: filterStatus || undefined,
        academic_cycle_id: filterCycleId || undefined,
      },
      { preserveState: true },
    );
  }

  function onSearchInput() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(applyFilters, 300);
  }

  function statusVariant(status) {
    if (status === 'draft') return 'secondary';
    if (status === 'published') return 'default';
    if (status === 'in_progress') return 'outline';
    if (status === 'completed') return 'outline';
    if (status === 'cancelled') return 'destructive';
    return 'outline';
  }

  function statusLabel(value) {
    return statuses.find((s) => s.value === value)?.label ?? value;
  }


  function bookingModeLabel(mode) {
    if (mode === 'open_for_reservation') return 'Open for reservation';
    return 'Staff only';
  }

  function formatWhen(session) {
    if (!session.date) return '—';
    const start = session.start_time ?? '';
    const end = session.end_time ?? '';
    return end ? `${session.date} · ${start}–${end}` : `${session.date} · ${start}`;
  }

  const breadcrumbs = [{ label: 'Exams' }];
</script>

<svelte:head><title>Exam sessions</title></svelte:head>

<StaffLayout {breadcrumbs}>
  <div class="min-w-0 space-y-6 p-4 sm:p-6">
    <PageHeaderBar
      title="Exam sessions"
      description="View and manage exam sessions by date and status. Create and edit on separate pages."
    >
      {#snippet actions()}
        <div class="flex flex-wrap gap-2">
          <Link href="/exams/sessions/create">
            <Button class="min-h-10 gap-2">
              <Plus class="size-4" />
              Create session
            </Button>
          </Link>
        </div>
      {/snippet}
    </PageHeaderBar>

    {#if page_state === 'denied'}
      <p class="text-sm text-muted-foreground">You do not have permission to manage exam sessions.</p>
    {:else}
      <div class="flex flex-wrap items-end gap-3">
        <div>
          <label class="mb-1 block text-xs font-medium text-muted-foreground" for="exam-search">Search</label>
          <div class="relative">
            <Search
              class="pointer-events-none absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
            />
            <Input
              id="exam-search"
              type="search"
              placeholder="Code or room..."
              class="min-h-10 min-w-[180px] pl-8"
              bind:value={filterSearch}
              oninput={onSearchInput}
            />
          </div>
        </div>
        <div>
          <label class="mb-1 block text-xs font-medium text-muted-foreground" for="exam-status">Status</label>
          <Select.Root
            type="single"
            value={filterStatus}
            onValueChange={(v) => {
              filterStatus = v ?? '';
              applyFilters();
            }}
          >
            <Select.Trigger id="exam-status" class="min-h-10 w-[160px]">
              {#if filterStatus}
                {statusLabel(filterStatus)}
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
        </div>
        <div>
          <label class="mb-1 block text-xs font-medium text-muted-foreground" for="exam-cycle"
            >Academic year</label
          >
          <Select.Root
            type="single"
            value={filterCycleId}
            onValueChange={(v) => {
              filterCycleId = v ?? '';
              applyFilters();
            }}
          >
            <Select.Trigger id="exam-cycle" class="min-h-10 w-[220px]">
              {#if filterCycleId}
                {cycles.find((c) => String(c.id) === filterCycleId)?.label ?? 'Academic year'}
              {:else}
                <span class="text-muted-foreground">All academic years</span>
              {/if}
            </Select.Trigger>
            <Select.Content>
              <Select.Item value="" label="All academic years">All academic years</Select.Item>
              {#each cycles as c}
                <Select.Item value={String(c.id)} label={c.label}>{c.label}</Select.Item>
              {/each}
            </Select.Content>
          </Select.Root>
        </div>
      </div>

      <SwitchableListView bind:viewMode overflow="auto">
        {#snippet table()}
          <Table.Root class="w-full min-w-[820px] text-sm">
            <Table.Header class="bg-muted/50">
              <Table.Row>
                <Table.Head>When</Table.Head>
                <Table.Head>Room</Table.Head>
                <Table.Head>Code</Table.Head>
                <Table.Head>Status</Table.Head>
                <Table.Head>Booking</Table.Head>
                <Table.Head>Assigned</Table.Head>
                <Table.Head class="text-center">Actions</Table.Head>
              </Table.Row>
            </Table.Header>
            <Table.Body>
              {#each sessions as session (session.id)}
                <Table.Row
                  class="cursor-pointer hover:bg-muted/30"
                  onclick={(e) => {
                    if (e.target.closest('[data-action-cell]')) return;
                    router.visit(`/exams/sessions/${session.id}`);
                  }}
                >
                  <Table.Cell>{formatWhen(session)}</Table.Cell>
                  <Table.Cell>{session.room?.name ?? session.room?.code ?? '—'}</Table.Cell>
                  <Table.Cell class="font-medium tracking-tight">{session.code}</Table.Cell>
                  <Table.Cell>
                    <Badge variant={statusVariant(session.status)}>{statusLabel(session.status)}</Badge>
                  </Table.Cell>
                  <Table.Cell>
                    <Badge variant={session.booking_mode === 'open_for_reservation' ? 'default' : 'outline'}>
                      {bookingModeLabel(session.booking_mode)}
                    </Badge>
                  </Table.Cell>
                  <Table.Cell>
                    {session.assigned_count ?? 0}{#if session.room?.capacity}
                      / {session.room.capacity}{/if}
                  </Table.Cell>
                  <Table.Cell class="text-center" data-action-cell>
                    <div class="inline-flex flex-wrap justify-center gap-1">
                      <Link href={`/exams/sessions/${session.id}`}>
                        <Button variant="ghost" size="sm" class="h-8 px-2 text-xs font-semibold">
                          <Eye class="mr-1.5 size-3.5" />
                          Show
                        </Button>
                      </Link>
                      {#if session.status === 'draft'}
                        <Link href={`/exams/sessions/${session.id}/edit`}>
                          <Button variant="ghost" size="sm" class="h-8 px-2 text-xs font-semibold">
                            <Pencil class="mr-1.5 size-3.5" />
                            Edit
                          </Button>
                        </Link>
                      {/if}
                    </div>
                  </Table.Cell>
                </Table.Row>
              {:else}
                <Table.Row>
                  <Table.Cell colspan={7} class="py-12 text-center text-muted-foreground">
                    No exam sessions yet. Create one to get started.
                  </Table.Cell>
                </Table.Row>
              {/each}
            </Table.Body>
          </Table.Root>
        {/snippet}

        {#snippet cards()}
          {#if sessions.length > 0}
            <ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3" role="list">
              {#each sessions as session (session.id)}
                <li class="flex flex-col gap-3 rounded-xl border border-border bg-card p-4 shadow-sm">
                  <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                      <p class="truncate font-semibold tracking-tight">{session.code}</p>
                      <p class="text-sm text-muted-foreground">{formatWhen(session)}</p>
                      <p class="text-sm text-muted-foreground">
                        {session.room?.name ?? session.room?.code ?? '—'}
                      </p>
                    </div>
                    <div class="flex shrink-0 flex-col items-end gap-1">
                      <Badge variant={statusVariant(session.status)}>
                        {statusLabel(session.status)}
                      </Badge>
                      <Badge variant={session.booking_mode === 'open_for_reservation' ? 'default' : 'outline'}>
                        {bookingModeLabel(session.booking_mode)}
                      </Badge>
                    </div>
                  </div>
                  <p class="text-sm text-muted-foreground">
                    Assigned {session.assigned_count ?? 0}{#if session.room?.capacity}
                      / {session.room.capacity}{/if}
                  </p>
                  <div class="mt-auto flex gap-2 pt-2">
                    <Link href={`/exams/sessions/${session.id}`} class="flex-1">
                      <Button variant="outline" size="sm" class="min-h-10 w-full font-semibold">
                        <Eye class="mr-1.5 size-3.5" />
                        Show
                      </Button>
                    </Link>
                    {#if session.status === 'draft'}
                      <Link href={`/exams/sessions/${session.id}/edit`} class="flex-1">
                        <Button variant="outline" size="sm" class="min-h-10 w-full font-semibold">
                          <Pencil class="mr-1.5 size-3.5" />
                          Edit
                        </Button>
                      </Link>
                    {/if}
                  </div>
                </li>
              {/each}
            </ul>
          {:else}
            <p class="py-12 text-center text-muted-foreground">No exam sessions yet. Create one to get started.</p>
          {/if}
        {/snippet}
      </SwitchableListView>
    {/if}
  </div>
</StaffLayout>
