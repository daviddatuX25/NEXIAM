<script>
  import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
  import { Link, router } from '@inertiajs/svelte';
  import * as Table from '@/Components/ui/table';
  import * as Dialog from '@/Components/ui/dialog';
  import * as Select from '@/Components/ui/select';
  import { Button } from '@/Components/ui/button';
  import { Badge } from '@/Components/ui/badge';
  import { Input } from '@/Components/ui/input';
  import ScheduleAssistantPanel from '@/Components/ScheduleAssistantPanel.svelte';
  import InfoPopover from '@/Components/InfoPopover.svelte';
  import SwitchableListView from '@/Components/SwitchableListView.svelte';
  import SimplePagination from '@/Components/SimplePagination.svelte';
  import { Plus, Eye, Pencil, ChevronDown, Filter, ClipboardList, Sparkles, Send, Undo, X, Trash2, Search } from 'lucide-svelte';
  import { formatDate } from '@/lib/date-utils';
  import { createDebouncedWatch, createImmediateWatch } from '@/lib/auto-filter';

  let { sessions, filters = {}, statuses = [], seasons = [], active_season_id = null, view = 'admin', schedule_assistant = null, breadcrumbParent = { label: 'Exam Scheduling', href: '/admin/exam-scheduling' } } = $props();

  const isProctorView = $derived(view === 'proctor');
  const breadcrumbs = $derived([breadcrumbParent]);

  let assistantOpen = $state(false);

  $effect(() => {
    if (schedule_assistant && typeof window !== 'undefined') {
      const params = new URLSearchParams(window.location.search);
      if (params.get('open') === 'schedule-assistant') {
        assistantOpen = true;
        const url = new URL(window.location.href);
        url.searchParams.delete('open');
        window.history.replaceState({}, '', url.pathname + url.search);
      }
    }
  });

  function onScheduleApplied() {
    assistantOpen = false;
    router.reload();
  }

  let filterSearch = $state('');
  let filterStatus = $state('');
  let filterAcademicYearId = $state('');
  let filterDateFrom = $state('');
  let filterDateTo = $state('');
  $effect(() => {
    filterSearch = filters.search ?? '';
    filterStatus = filters.status ?? '';
    filterDateFrom = filters.date_from ?? '';
    filterDateTo = filters.date_to ?? '';
    const ayFromFilter = filters.academic_year_id;
    if (ayFromFilter) {
      filterAcademicYearId = String(ayFromFilter);
    } else if (active_season_id != null) {
      filterAcademicYearId = String(active_season_id);
    } else {
      filterAcademicYearId = '';
    }
  });

  function seasonLabel(season) {
    if (!season) return '—';
    return `${season.academic_year ?? ''} – ${season.semester ?? ''}`.trim() || '—';
  }

  function statusVariant(status) {
    if (status === 'draft') return 'muted';
    if (status === 'published') return 'success';
    if (status === 'in_progress') return 'warning';
    if (status === 'completed') return 'outline';
    if (status === 'cancelled') return 'danger';
    return 'outline';
  }

  function statusLabel(value) {
    const s = statuses.find((x) => x.value === value);
    return s ? s.label : value;
  }



  function formatTime(value) {
    if (value == null || value === '') return '—';
    const parts = String(value).split(':');
    const hours = parseInt(parts[0], 10) || 0;
    const mins = parseInt(parts[1], 10) || 0;
    const h = hours % 12 || 12;
    const ampm = hours < 12 ? 'AM' : 'PM';
    return `${h}:${String(mins).padStart(2, '0')} ${ampm}`;
  }

  let mobileFiltersDetails = $state(null);

  function applyFilters() {
    if (mobileFiltersDetails) mobileFiltersDetails.open = false;
    router.get('/admin/exam-scheduling', {
      search: filterSearch || undefined,
      status: filterStatus || undefined,
      academic_year_id: filterAcademicYearId || undefined,
      date_from: filterDateFrom || undefined,
      date_to: filterDateTo || undefined,
      page: 1,
    }, { preserveState: true });
  }

  // Auto-apply: search debounced, dropdowns/dates immediate
  const searchWatch = createDebouncedWatch(() => applyFilters());
  const filterWatch = createImmediateWatch(() => applyFilters());
  $effect(() => { filterSearch; searchWatch(); });
  $effect(() => { filterStatus; filterAcademicYearId; filterDateFrom; filterDateTo; filterWatch(); });

  let viewMode = $state('responsive');
  let deleteId = $state(null);

  const list = $derived(sessions?.data ?? []);

  function confirmDelete(id) {
    deleteId = id;
  }

  function cancelDelete() {
    deleteId = null;
  }

  function doDelete() {
    if (deleteId) {
      router.delete(`/admin/exam-scheduling/${deleteId}`, { onSuccess: () => (deleteId = null) });
    }
  }

  function getStateAction(session) {
    if (session.status === 'draft') {
      if (session.is_publishable === false) {
        return { label: 'Publish', icon: Send, method: 'post', href: `/admin/exam-scheduling/${session.id}/publish`, disabled: true, title: session.publish_block_reason || 'Cannot publish this session' };
      }
      return { label: 'Publish', icon: Send, method: 'post', href: `/admin/exam-scheduling/${session.id}/publish` };
    }
    if (session.status === 'published') return { label: 'Unpublish', icon: Undo, method: 'post', href: `/admin/exam-scheduling/${session.id}/unpublish` };
    if (session.status === 'in_progress') return { label: 'Cancel', icon: X, method: 'post', href: `/admin/exam-scheduling/${session.id}/cancel` };
    return null;
  }
</script>

<AuthenticatedLayout breadcrumbs={breadcrumbs}>
    <div class="space-y-6 min-w-0">
    <div class="flex items-center justify-between">
      <div>
        <p class="text-sm text-muted-foreground">View and manage exam sessions by date and status</p>
      </div>
      <div class="flex flex-wrap items-center gap-2 sm:gap-3">
        {#if !isProctorView && schedule_assistant}
          <Button
            variant="outline"
            class="min-h-[44px] gap-2"
            onclick={() => (assistantOpen = true)}
          >
            <Sparkles class="h-4 w-4" />
            <span class="hidden sm:inline">Schedule with AI</span>
          </Button>
        {/if}
        {#if !isProctorView}
          <Link href="/admin/exam-scheduling/create">
            <Button class="min-h-[44px] gap-2">
              <Plus class="h-4 w-4" />
              <span class="hidden sm:inline">Create Session</span>
            </Button>
          </Link>
          <Link href="/admin/direct-assessments/create">
            <Button variant="secondary" class="min-h-[44px] gap-2">
              <ClipboardList class="h-4 w-4" />
              <span class="hidden sm:inline">Direct Assessment</span>
            </Button>
          </Link>
        {/if}
      </div>
    </div>


    <!-- Filters: one row on desktop; on mobile search + collapsible "Filters" dropdown, dates always together, Apply always visible -->
    <div class="flex flex-col gap-3">
      <!-- Desktop -->
      <div class="hidden md:flex flex-wrap items-end gap-3">
        <div>
          <label class="text-xs font-medium text-muted-foreground block mb-1">Search</label>
          <div class="relative">
            <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground pointer-events-none" />
            <Input
              type="search"
              placeholder="Search sessions..."
              bind:value={filterSearch}
              class="pl-8 min-w-[160px] max-w-[220px] h-10"
            />
          </div>
        </div>
        <div>
          <label class="text-xs font-medium text-muted-foreground block mb-1">Status</label>
          <Select.Root type="single" bind:value={filterStatus}>
            <Select.Trigger class="w-[150px] min-h-[40px]">
              {#if filterStatus}
                {statuses.find(s => s.value === filterStatus)?.label || 'All statuses'}
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
          <label class="text-xs font-medium text-muted-foreground block mb-1">Season</label>
          <Select.Root type="single" bind:value={filterAcademicYearId}>
            <Select.Trigger class="w-[200px] min-h-[40px]">
              {#if filterAcademicYearId}
                {@const sel = seasons.find(s => String(s.id) === filterAcademicYearId)}
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
          <Input type="date" bind:value={filterDateFrom} class="min-h-[40px] max-w-[160px]" />
        </div>
        <div>
          <label class="text-xs font-medium text-muted-foreground block mb-1">To</label>
          <Input type="date" bind:value={filterDateTo} class="min-h-[40px] max-w-[160px]" />
        </div>

      </div>

      <!-- Mobile -->
      <div class="flex flex-wrap items-center gap-3 md:hidden">
        <div class="relative flex-1 min-w-0">
          <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground pointer-events-none" />
          <Input
            type="search"
            placeholder="Search sessions..."
            bind:value={filterSearch}
            class="pl-8 min-h-[44px] w-full"
          />
        </div>
        <details class="relative group" bind:this={mobileFiltersDetails}>
          <summary class="list-none flex items-center gap-2 min-h-[44px] px-4 rounded-md border border-input bg-background text-sm font-medium cursor-pointer hover:bg-muted/50">
            <Filter class="h-4 w-4" />
            <span>Filters</span>
            <ChevronDown class="h-4 w-4 transition-transform group-open:rotate-180" />
          </summary>
          <div class="absolute right-0 top-full z-10 mt-1 w-[min(320px,calc(100vw-2rem))] rounded-lg border border-border bg-card p-4 shadow-lg flex flex-col gap-3">
            <div>
              <label for="filter-status-mob" class="text-sm font-medium block mb-1">Status</label>
              <Select.Root type="single" bind:value={filterStatus}>
                <Select.Trigger id="filter-status-mob" class="w-full min-h-[44px]">
                  {#if filterStatus}
                    {statuses.find(s => s.value === filterStatus)?.label || 'All statuses'}
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
              <label for="filter-ay-mob" class="text-sm font-medium block mb-1">Season</label>
              <Select.Root type="single" bind:value={filterAcademicYearId}>
                <Select.Trigger id="filter-ay-mob" class="w-full min-h-[44px]">
                  {#if filterAcademicYearId}
                    {@const sel = seasons.find(s => String(s.id) === filterAcademicYearId)}
                    {sel ? seasonLabel(sel) : 'Active season'}
                  {:else}
                    <span class="text-muted-foreground">Active season</span>
                  {/if}
                </Select.Trigger>
                <Select.Content>
                  <Select.Item value="" label="Active season">Active season</Select.Item>
                  {#each seasons as s}
                    <Select.Item value={String(s.id)} label={seasonLabel(s)}>{seasonLabel(s)}</Select.Item>
                  {/each}
                </Select.Content>
              </Select.Root>
            </div>
            <div>
              <span class="text-sm font-medium block mb-1">Date range</span>
              <div class="flex items-center gap-2">
                <Input type="date" bind:value={filterDateFrom} class="flex-1 min-w-0 min-h-[44px]" />
                <span class="text-muted-foreground">–</span>
                <Input type="date" bind:value={filterDateTo} class="flex-1 min-w-0 min-h-[44px]" />
              </div>
            </div>
          </div>
        </details>

      </div>
    </div>

    <SwitchableListView bind:viewMode overflow="auto">
      {#snippet table()}
        <Table.Root class="w-full min-w-[640px] text-sm">
          <Table.Header class="bg-muted/50">
            <Table.Row>
              <Table.Head class="px-4 py-3">Date</Table.Head>
              <Table.Head class="px-4 py-3">Time</Table.Head>
              <Table.Head class="px-4 py-3">Room</Table.Head>
              <Table.Head class="px-4 py-3">Status</Table.Head>
              <Table.Head class="px-4 py-3">Proctors</Table.Head>
              <Table.Head class="text-center px-4 py-3">Actions</Table.Head>
            </Table.Row>
          </Table.Header>
          <Table.Body>
            {#each list as session}
              <Table.Row class="border-t border-border cursor-pointer hover:bg-muted/30" onclick={(e) => { if (e.target.closest('[data-action-cell]')) return; router.visit(`/admin/exam-scheduling/${session.id}`); }}>
                <Table.Cell class="px-4 py-3">{formatDate(session.date)}</Table.Cell>
                <Table.Cell class="px-4 py-3">
                  {formatTime(session.start_time)}{#if session.end_time} – {formatTime(session.end_time)}{/if}
                </Table.Cell>
                <Table.Cell class="px-4 py-3">{session.room?.name ?? '—'}</Table.Cell>
                <Table.Cell class="px-4 py-3">
                  {#if session.type === 'direct'}
                    <span class="inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800 mr-2">Direct</span>
                  {/if}
                  <Badge variant={statusVariant(session.status)}>{statusLabel(session.status)}</Badge>
                </Table.Cell>
                <Table.Cell>
                  {#if (session.proctors ?? []).length > 0}
                    {(session.proctors ?? []).map((p) => p.name).join(', ')}
                  {:else}
                    —
                  {/if}
                </Table.Cell>
                <Table.Cell class="text-center" data-action-cell>
                    <div class="flex justify-center gap-2">
                      {#if !isProctorView && session.status === 'draft'}
                        <Link href={`/admin/exam-scheduling/${session.id}/edit`}>
                          <Button variant="ghost" size="sm" class="h-8 px-2 text-xs" onclick={(e) => e.stopPropagation()}>
                            <Pencil class="mr-1.5 h-3.5 w-3.5" />
                            Edit
                          </Button>
                        </Link>
                      {/if}
                      {#if !isProctorView && ['draft', 'published'].includes(session.status)}
                        <Button
                          variant="ghost"
                          size="sm"
                          class="h-8 px-2 text-xs text-destructive"
                          onclick={(e) => { e.stopPropagation(); confirmDelete(session.id); }}
                        >
                          <Trash2 class="mr-1.5 h-3.5 w-3.5" />
                          Delete
                        </Button>
                      {/if}
                    </div>
                </Table.Cell>
              </Table.Row>
            {:else}
              <Table.Row>
                <Table.Cell colspan={6} class="py-12 text-center text-muted-foreground">
                  {isProctorView ? 'No assigned sessions.' : 'No exam sessions yet. Create one to get started.'}
                </Table.Cell>
              </Table.Row>
            {/each}
          </Table.Body>
        </Table.Root>
        <SimplePagination data={sessions} variant="table" />
      {/snippet}

      {#snippet cards()}
        {#if list.length > 0}
          <ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3" role="list">
            {#each list as session}
              <li class="flex flex-col gap-3 rounded-xl border border-border bg-card p-4 shadow-sm hover:shadow-md transition-all">
                <div class="flex items-start justify-between gap-2">
                  <h3 class="font-semibold">{formatDate(session.date)} {formatTime(session.start_time)}</h3>
                  <div class="flex items-center gap-1">
                    {#if session.type === 'direct'}
                      <span class="inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800">Direct</span>
                    {/if}
                    <Badge variant={statusVariant(session.status)}>{statusLabel(session.status)}</Badge>
                  </div>
                </div>
                <dl class="grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
                  <dt class="text-muted-foreground">Room</dt>
                  <dd>{session.room?.name ?? '—'}</dd>
                  <dt class="text-muted-foreground">Time</dt>
                  <dd>{formatTime(session.start_time)}{#if session.end_time} – {formatTime(session.end_time)}{/if}</dd>
                  <dt class="text-muted-foreground">Proctors</dt>
                  <dd class="col-span-1">
                    {(session.proctors ?? []).length > 0 ? (session.proctors ?? []).map((p) => p.name).join(', ') : '—'}
                  </dd>
                </dl>
                <div class="mt-auto flex flex-wrap gap-2 pt-2">
                  <Link href={`/admin/exam-scheduling/${session.id}`} class="flex-1 min-w-0">
                    <Button variant="outline" size="sm" class="w-full min-h-[40px] font-semibold">
                      <Eye class="h-4 w-4 mr-1.5" />
                      Manage
                    </Button>
                  </Link>
                  {#if isProctorView && (session.status === 'published' || session.status === 'in_progress')}
                    <Link href={`/proctor/sessions/${session.id}`}>
                      <Button variant="outline" size="sm" class="min-h-[40px] font-semibold">
                        <ClipboardList class="h-4 w-4 mr-1.5" />
                        View Session
                      </Button>
                    </Link>
                  {/if}
                </div>
              </li>
            {/each}
          </ul>
        {:else}
          <p class="py-12 text-center text-muted-foreground">{isProctorView ? 'No assigned sessions.' : 'No exam sessions yet. Create one to get started.'}</p>
        {/if}
        <SimplePagination data={sessions} variant="centered" />
      {/snippet}
    </SwitchableListView>
  </div>

  {#if schedule_assistant}
    <Dialog.Root bind:open={assistantOpen}>
      <Dialog.Content class="w-[95vw] max-w-2xl max-h-[90vh] flex flex-col" aria-describedby="schedule-assistant-description">
        <Dialog.Header class="shrink-0">
          <Dialog.Title>AI Exam Scheduler</Dialog.Title>
          <InfoPopover
            content="Chat with the assistant to refine your schedule. After you get a reply, click Generate Schedule to create a preview."
            label="Beta"
          />
          <Dialog.Description id="schedule-assistant-description" class="sr-only">
            Chat with the assistant to refine your schedule. After you get a reply, click Generate Schedule to create a preview.
          </Dialog.Description>
        </Dialog.Header>
        <div class="mt-4 min-h-0 flex-1 overflow-y-auto">
          <ScheduleAssistantPanel
            applicant_count={schedule_assistant.applicant_count}
            rooms={schedule_assistant.rooms}
            draft_sessions={schedule_assistant.draft_sessions}
            messages={schedule_assistant.messages}
            openrouter_configured={schedule_assistant.openrouter_configured}
            csrf_token={schedule_assistant.csrf_token}
            onApplied={onScheduleApplied}
          />
        </div>
      </Dialog.Content>
    </Dialog.Root>
  {/if}

  {#if deleteId}
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true">
      <div class="rounded-lg bg-card p-6 shadow-lg max-w-sm w-full">
        <h2 class="text-lg font-semibold">Delete exam session?</h2>
        <p class="mt-2 text-sm text-muted-foreground">This action cannot be undone.</p>
        <div class="mt-4 flex justify-end gap-2">
          <Button variant="outline" onclick={cancelDelete}>Cancel</Button>
          <Button variant="destructive" onclick={doDelete}>Delete</Button>
        </div>
      </div>
    </div>
  {/if}
</AuthenticatedLayout>