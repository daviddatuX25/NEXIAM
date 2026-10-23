<script>
import { newIdempotencyKey } from '@/lib/utils';
  /**
   * Program Head workspace — program dashboard.
   * Server-authored metrics/lists only. Metrics are plain cards + tables,
   * so no chart region exists and no chart alternative is needed.
   */
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
  import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
  import PrintDocumentsSlot from '@/Components/DocumentTemplate/PrintDocumentsSlot.svelte';
  import FilterToolbar from '@/Components/kit/FilterToolbar.svelte';
  import E2PageState from '@/Pages/Admission/E2PageState.svelte';
  import KpiCard from '@/Components/kit/KpiCard.svelte';
  import { Badge } from '@/Components/ui/badge';
  import { Button } from '@/Components/ui/button';
  import * as Card from '@/Components/ui/card';
  import * as Dialog from '@/Components/ui/dialog';
  import * as Table from '@/Components/ui/table';
  import * as Tabs from '@/Components/ui/tabs';
  import { Textarea } from '@/Components/ui/textarea';
  import { Link, router, useForm, usePage } from '@inertiajs/svelte';
  import { onMount } from 'svelte';
  import { subscribeRealtimePulse } from '@/lib/realtime-pulse.svelte';
  import { success as toastSuccess, error as toastError } from '@/lib/toast.js';
  import { CheckCircle2, MessageSquare, MessageSquareWarning } from 'lucide-svelte';
  let {
    page_state = 'denied',
    dashboard = null,
    lists = null,
    setup_links = {},
    applicant_conversations = null,
    is_dean = false,
    department = null,
  } = $props();

  const conversationsList = $derived(
    applicant_conversations?.data ?? (Array.isArray(applicant_conversations) ? applicant_conversations : [])
  );

  const programCode = $derived(dashboard?.program?.code ?? '');
  const cycle = $derived(dashboard?.cycle ?? lists?.cycle ?? null);
  const metrics = $derived(dashboard?.metrics ?? null);
  const quota = $derived(metrics?.quota ?? null);
  const pendingReviewCount = $derived(
    dashboard?.metrics?.pending_review_count ?? lists?.pending_review_count ?? 0,
  );
  const programHeadContext = $derived.by(() => ({
    entityKey: `program-show-${programCode}-${cycle ?? 'none'}`,
    safetyKey: `${page_state}:${programCode}:${pendingReviewCount}:${quota?.capacity ?? 0}`,
    freshness: 'current',
    programCode,
    cycle,
    pendingReviews: pendingReviewCount,
    quotaCapacity: quota?.capacity ?? 0,
    quotaRemaining: quota?.remaining ?? 0,
  }));

  onMount(() => subscribeRealtimePulse('messaging', () => {
    router.reload({
      only: ['applicant_conversations', 'dashboard', 'lists'],
      preserveScroll: true,
      preserveState: true,
    });
  }));

  const breadcrumbs = $derived.by(() => {
    const programLabel = dashboard?.program?.name ?? dashboard?.program?.code ?? 'Program';
    if (is_dean) {
      return [
        { label: department?.code ? `My Department (${department.code})` : 'My Department', href: '/staff/programs' },
        { label: 'My Programs', href: '/staff/programs' },
        { label: programLabel },
      ];
    }
    return [
      { label: 'My Program' },
      { label: programLabel },
    ];
  });

  // ------------------------------------------------------------------
  // Follow-up resolve (one dialog, active row state)
  // ------------------------------------------------------------------
  const resolveForm = useForm({
    outcome: '',
    reason: '',
    idempotency_key: '',
  });

  let resolveDialogOpen = $state(false);
  let activeFollowUp = $state(null);

  const resolveOutcomes = [
    { value: 'cleared_by_ph', label: 'Cleared by program head', description: 'Records this program’s academic review outcome. Registrar clearance requirements remain separate.' },
    { value: 'declined_by_ph', label: 'Declined by program head', description: 'Returns the applicant to Guidance for alternative program placement.' },
    { value: 'appearance_requested', label: 'Appearance requested', description: 'Ask the applicant to visit the program office.' },
  ];

  function openResolve(row) {
    activeFollowUp = row;
    resolveForm.clearErrors();
    resolveForm.outcome = 'cleared_by_ph';
    resolveForm.reason = '';
    resolveForm.idempotency_key = newIdempotencyKey();
    resolveDialogOpen = true;
  }

  function submitResolve() {
    if (!activeFollowUp) return;
    const url =
      typeof route === 'function'
        ? route('program-head.follow-ups.resolve', {
            program: programCode,
            followUp: activeFollowUp.id,
          })
        : `/staff/programs/${programCode}/follow-ups/${activeFollowUp.id}/resolve`;
    resolveForm.post(url, {
      preserveScroll: true,
      onSuccess: () => {
        resolveDialogOpen = false;
        activeFollowUp = null;
      },
      onError: (errors) => {
        toastError(errors?.follow_up ?? errors?.outcome ?? errors?.reason ?? 'Follow-up resolution failed.');
      },
    });
  }

  const resolveReasonRequired = $derived(
    resolveForm.outcome !== 'appearance_requested' && !resolveForm.reason.trim(),
  );
  // ------------------------------------------------------------------

  // ------------------------------------------------------------------
  // Presentation helpers
  // ------------------------------------------------------------------
  const stageLabels = {
    potential: 'Potential',
    upcoming: 'Upcoming',
    selected: 'Selected',
    selection_pending_ack: 'Selection pending ack',
    advised: 'Advised',
    result_released: 'Result released',
    accepted: 'Accepted',
    ready_for_enrollment: 'Ready for enrollment',
  };

  function stageLabel(stage) {
    return stageLabels[stage] ?? stage ?? '—';
  }

  function stageVariant(stage) {
    if (stage === 'ready_for_enrollment' || stage === 'selected' || stage === 'upcoming') return 'default';
    if (stage === 'selection_pending_ack' || stage === 'result_released') return 'outline';
    return 'secondary';
  }

  const followUpStatusLabels = {
    open: 'Open',
    appearance_requested: 'Appearance requested',
    cleared_by_ph: 'Cleared by program head',
    declined_by_ph: 'Declined by program head',
  };

  function followUpStatusLabel(status) {
    return followUpStatusLabels[status] ?? status ?? '—';
  }

  function followUpStatusVariant(status) {
    if (status === 'open') return 'default';
    if (status === 'appearance_requested') return 'outline';
    if (status === 'declined_by_ph') return 'destructive';
    return 'secondary';
  }

  function applicantUrl(attemptId) {
    return typeof route === 'function'
      ? route('program-head.applicants.show', { program: programCode, attempt: attemptId })
      : `/staff/programs/${programCode}/applicants/${attemptId}`;
  }

  function formatDateTime(iso) {
    if (!iso) return '—';
    const d = new Date(iso);
    if (isNaN(d.getTime())) return iso;
    return d.toLocaleString(undefined, {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: 'numeric',
      minute: '2-digit',
    });
  }
  function formatScore(value) {
    if (value === null || value === undefined || value === '') return '—';
    const numeric = Number(value);
    return Number.isNaN(numeric)
      ? String(value)
      : numeric.toLocaleString(undefined, { maximumFractionDigits: 1 });
  }

  function scoreEntries(row) {
    const series = row.score_profile?.series ?? row.score_summary?.series ?? row.exam_scores ?? row.subtests ?? row.scores ?? [];
    if (Array.isArray(series)) return series;
    if (series && typeof series === 'object') {
      return Object.entries(series).map(([area, score]) => ({
        area_code: area,
        area_name: area,
        score: typeof score === 'object' ? score.score : score,
        cutoff: typeof score === 'object' ? score.cutoff : null,
      }));
    }
    return [];
  }

  function scoreCutoff(row, score) {
    return score.cutoff
      ?? score.program_cutoff
      ?? row.program_cutoffs?.[score.area_code]
      ?? row.cutoffs?.[score.area_code]
      ?? null;
  }

  const metricCards = $derived.by(() => {
    if (!metrics) return [];
    return [
      { label: 'Program demand', value: metrics.preference_candidates ?? 0 },
      { label: 'Reserved places', value: quota?.committed ?? 0 },
      { label: 'Pending reviews', value: pendingReviewCount },
    ];
  });

  let activeTab = $state('potential');

  const candidateSortOptions = [
    { value: 'name', label: 'Applicant Name' },
    { value: 'reference_code', label: 'Reference Code / NOA' },
    { value: 'gwa', label: 'GWA' },
    { value: 'opened_at', label: 'Date Opened' },
  ];

  let candidateSearch = $state(lists?.filters?.search ?? '');
  let candidateStrand = $state(lists?.filters?.strand ?? '');
  let candidateSort = $state(lists?.filters?.sort ?? 'name');
  let candidateSortDir = $state(lists?.filters?.dir ?? 'asc');

  const allStrands = $derived.by(() => {
    const set = new Set();
    const all = [
      ...(lists?.potential ?? []),
      ...(lists?.upcoming ?? []),
      ...(lists?.follow_ups ?? []),
      ...(lists?.ready_for_enrollment ?? []),
    ];
    for (const r of all) {
      if (r?.strand) set.add(r.strand);
    }
    return Array.from(set).sort();
  });

  function displayedList(key) {
    const raw = lists ? (lists[key] ?? []) : [];
    if (!Array.isArray(raw)) return [];
    let filtered = [...raw];
    if (candidateStrand && candidateStrand !== 'all') {
      filtered = filtered.filter((r) => String(r.strand ?? '').toLowerCase() === candidateStrand.toLowerCase());
    }
    if (candidateSearch && candidateSearch.trim()) {
      const tokens = candidateSearch.toLowerCase().trim().split(/\s+/);
      filtered = filtered.filter((r) => {
        const text = `${r.name ?? ''} ${r.reference_code ?? ''} ${r.strand ?? ''} ${r.last_school ?? ''} ${r.reason ?? ''}`.toLowerCase();
        return tokens.every((t) => text.includes(t));
      });
    }
    filtered.sort((a, b) => {
      let valA = a[candidateSort];
      let valB = b[candidateSort];
      if (candidateSort === 'opened_at') {
        valA = a.opened_at ?? a.stamped_at ?? '';
        valB = b.opened_at ?? b.stamped_at ?? '';
      }
      if (valA === null || valA === undefined) return candidateSortDir === 'asc' ? 1 : -1;
      if (valB === null || valB === undefined) return candidateSortDir === 'asc' ? -1 : 1;
      if (typeof valA === 'number' && typeof valB === 'number') {
        return candidateSortDir === 'asc' ? valA - valB : valB - valA;
      }
      const cmp = String(valA).localeCompare(String(valB));
      return candidateSortDir === 'asc' ? cmp : -cmp;
    });
    return filtered;
  }

  const tabCount = (key) => displayedList(key).length;
</script>

<svelte:head><title>Program workspace</title></svelte:head>

<StaffLayout {breadcrumbs}>
  <div class="min-w-0 space-y-6 p-4 sm:p-6">
    {#if page_state !== 'ready'}
      <E2PageState state={page_state} />
    {:else if dashboard && lists}
      <PageHeaderBar
        title={dashboard.program.name}
        description={`Program ${dashboard.program.code}${cycle ? ` · cycle ${cycle}` : ''} — pipeline and follow-up workspace.`}
        tourKey="program-head-overview"
        context={programHeadContext}
      >
        {#snippet actions()}
          <PrintDocumentsSlot slotKey="program_workspace_show" programCode={dashboard?.program?.code ?? null} />
          {#if setup_links.studio}
            <Link href={setup_links.studio} class="inline-flex min-h-9 items-center rounded-md border border-input px-3 text-sm font-medium hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">Benchmark Studio</Link>
          {/if}
          {#if setup_links.quotas}
            <Link href={setup_links.quotas} class="inline-flex min-h-9 items-center rounded-md border border-input px-3 text-sm font-medium hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">Quotas</Link>
          {/if}
          {#if setup_links.public_details}
            <Link href={setup_links.public_details} class="inline-flex min-h-9 items-center rounded-md border border-input px-3 text-sm font-medium hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">Edit public details</Link>
          {/if}
        {/snippet}
      </PageHeaderBar>
      {#if usePage().props.flash?.status}
        <div class="flex items-center gap-2 rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm font-medium text-emerald-900 dark:text-emerald-100" role="status">
          <CheckCircle2 class="size-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
          <span>{usePage().props.flash.status}</span>
        </div>
      {/if}

      {#if resolveForm.errors?.follow_up || resolveForm.errors?.outcome || resolveForm.errors?.reason}
        <div class="border-destructive/40 bg-destructive/10 text-destructive rounded-md border px-4 py-3 text-sm">
          {resolveForm.errors.follow_up ?? resolveForm.errors.outcome ?? resolveForm.errors.reason}
        </div>
      {/if}

      <!-- Metrics -->
      <div class="grid gap-4 sm:grid-cols-3" aria-label="Program overview" data-tour="program-head-kpis">
        {#each metricCards as card (card.label)}
          <KpiCard label={card.label} value={card.value} />
        {/each}
      </div>

      <!-- Quota card -->
      {#if quota}
        <section class="flex flex-wrap items-center justify-between gap-x-6 gap-y-3 rounded-lg border px-4 py-3 text-sm" aria-label="Program quota" data-tour="program-head-quota">
          {#if quota.configured}
            <dl class="flex flex-wrap gap-x-6 gap-y-2">
              <div class="flex items-baseline gap-2">
                <dt class="text-muted-foreground">Capacity</dt>
                <dd class="font-semibold tabular-nums">{quota.capacity}</dd>
              </div>
              <div class="flex items-baseline gap-2">
                <dt class="text-muted-foreground">Places remaining</dt>
                <dd class="font-semibold tabular-nums">{quota.remaining}</dd>
              </div>
            </dl>
            {#if quota.over_capacity > 0}
              <Badge variant="destructive">Over capacity by {quota.over_capacity}</Badge>
            {/if}
          {:else}
            <Badge variant="secondary">Not configured</Badge>
          {/if}
        </section>
      {/if}
      <!-- Candidate Filter Toolbar -->
      <FilterToolbar
        bind:search={candidateSearch}
        searchPlaceholder="Search by name, NOA, strand, school, reason..."
        searchLabel="Filter candidate lists"
        sortOptions={candidateSortOptions}
        sortValue={candidateSort}
        sortDir={candidateSortDir}
        onSortChange={({ sort, dir }) => {
          candidateSort = sort;
          candidateSortDir = dir;
        }}
      >
        {#snippet desktopFilters()}
          <label class="space-y-1">
            <span class="text-xs font-medium text-muted-foreground">Strand</span>
            <select
              bind:value={candidateStrand}
              class="h-10 min-h-[40px] rounded-md border border-input bg-background px-2.5 text-xs font-medium text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            >
              <option value="">All strands</option>
              {#each allStrands as strand (strand)}
                <option value={strand}>{strand}</option>
              {/each}
            </select>
          </label>
        {/snippet}
        {#snippet mobileFilters()}
          <label class="space-y-1">
            <span class="text-xs font-medium text-muted-foreground">Strand</span>
            <select
              bind:value={candidateStrand}
              class="h-10 min-h-[44px] w-full rounded-md border border-input bg-background px-2.5 text-xs font-medium text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            >
              <option value="">All strands</option>
              {#each allStrands as strand (strand)}
                <option value={strand}>{strand}</option>
              {/each}
            </select>
          </label>
        {/snippet}
      </FilterToolbar>

      <!-- Candidate lists -->
      <Tabs.Root bind:value={activeTab}>
        <Tabs.List variant="line" data-tour="program-head-candidate-tabs">
          <Tabs.Trigger value="upcoming">Upcoming ({tabCount('upcoming')})</Tabs.Trigger>
          <Tabs.Trigger value="follow_ups">
            Follow-up ({tabCount('follow_ups')})
            {#if pendingReviewCount > 0}
              <span class="ml-1 inline-flex size-2 rounded-full bg-destructive" aria-hidden="true"></span>
              <span class="sr-only">Unresolved academic reviews pending</span>
            {/if}
          </Tabs.Trigger>
          <Tabs.Trigger value="ready_for_enrollment">Ready for enrollment ({tabCount('ready_for_enrollment')})</Tabs.Trigger>
        </Tabs.List>

        <Tabs.Content value="potential" class="pt-4">
          {#if displayedList('potential').length === 0}
            <p class="text-muted-foreground py-10 text-center">No potential candidates matching current filters.</p>
          {:else}
            <Table.Root class="w-full min-w-[720px] text-sm">
              <Table.Header>
                <Table.Row>
                  <Table.Head>Reference</Table.Head>
                  <Table.Head>Name</Table.Head>
                  <Table.Head>Basis</Table.Head>
                  <Table.Head>Stage</Table.Head>
                </Table.Row>
              </Table.Header>
              <Table.Body>
                {#each displayedList('potential') as row (row.attempt_id)}
                  <Table.Row>
                    <Table.Cell class="font-mono text-xs">{row.reference_code}</Table.Cell>
                    <Table.Cell class="font-medium">{row.name}</Table.Cell>
                    <Table.Cell>
                      <div class="flex flex-wrap gap-1">
                        {#if (row.basis ?? []).length === 0}
                          <span class="text-muted-foreground">—</span>
                        {:else}
                          {#each row.basis as basis (basis)}
                            <Badge variant="outline">{basis}</Badge>
                          {/each}
                        {/if}
                      </div>
                    </Table.Cell>
                    <Table.Cell>
                      <Badge variant={stageVariant(row.stage)}>{stageLabel(row.stage)}</Badge>
                    </Table.Cell>
                  </Table.Row>
                {/each}
              </Table.Body>
            </Table.Root>
          {/if}
        </Tabs.Content>

        <Tabs.Content value="upcoming" class="pt-4">
          {#if displayedList('upcoming').length === 0}
            <p class="text-muted-foreground py-10 text-center">No upcoming candidates matching current filters.</p>
          {:else}
            <Table.Root class="w-full min-w-[720px] text-sm">
              <Table.Header>
                <Table.Row>
                  <Table.Head>Reference</Table.Head>
                  <Table.Head>Name</Table.Head>
                  <Table.Head>Basis</Table.Head>
                  <Table.Head>Stage</Table.Head>
                </Table.Row>
              </Table.Header>
              <Table.Body>
                {#each displayedList('upcoming') as row (row.attempt_id)}
                  <Table.Row>
                    <Table.Cell class="font-mono text-xs">{row.reference_code}</Table.Cell>
                    <Table.Cell class="font-medium">{row.name}</Table.Cell>
                    <Table.Cell>
                      <div class="flex flex-wrap gap-1">
                        {#if (row.basis ?? []).length === 0}
                          <span class="text-muted-foreground">—</span>
                        {:else}
                          {#each row.basis as basis (basis)}
                            <Badge variant="outline">{basis}</Badge>
                          {/each}
                        {/if}
                      </div>
                    </Table.Cell>
                    <Table.Cell>
                      <Badge variant={stageVariant(row.stage)}>{stageLabel(row.stage)}</Badge>
                    </Table.Cell>
                  </Table.Row>
                {/each}
              </Table.Body>
            </Table.Root>
          {/if}
        </Tabs.Content>

        <Tabs.Content value="follow_ups" class="pt-4">
          {#if displayedList('follow_ups').length === 0}
            <p class="text-muted-foreground py-10 text-center">No open follow-ups matching current filters.</p>
          {:else}
            <div class="space-y-3">
              {#each displayedList('follow_ups') as row (row.id)}
                <Card.Root>
                  <Card.Content class="space-y-4 p-4 sm:p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                      <div class="min-w-0">
                        <h3 class="font-semibold">{row.name}</h3>
                        <p class="mt-1 font-mono text-xs text-muted-foreground">{row.reference_code}</p>
                      </div>
                      <div class="flex flex-wrap items-center gap-2">
                        <Badge variant={followUpStatusVariant(row.status)}>{followUpStatusLabel(row.status)}</Badge>
                        <span class="text-xs text-muted-foreground">Opened {formatDateTime(row.opened_at)}</span>
                      </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-[minmax(0,1fr)_minmax(0,1.5fr)]">
                      <section aria-label="Applicant academic profile" class="rounded-md border bg-muted/20 p-3">
                        <h4 class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Applicant profile</h4>
                        <dl class="mt-2 grid grid-cols-2 gap-3 text-sm">
                          <div><dt class="text-xs text-muted-foreground">SHS strand</dt><dd class="font-medium">{row.strand ?? '—'}</dd></div>
                          <div><dt class="text-xs text-muted-foreground">GWA</dt><dd class="font-medium">{formatScore(row.gwa)}</dd></div>
                        </dl>
                      </section>
                      <section aria-label="Exam scores compared with program cutoffs" class="rounded-md border bg-muted/20 p-3">
                        <h4 class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Exam scores vs program cutoffs</h4>
                        {#if scoreEntries(row).length}
                          <ul class="mt-2 grid gap-2 sm:grid-cols-2">
                            {#each scoreEntries(row) as score, index (score.area_code ?? score.area_name ?? index)}
                              {@const cutoff = scoreCutoff(row, score)}
                              <li class="flex min-w-0 items-center justify-between gap-2 rounded bg-background px-2.5 py-2 text-sm">
                                <span class="truncate text-muted-foreground">{score.area_name ?? score.area_code ?? score.name ?? `Subtest ${index + 1}`}</span>
                                <span class="shrink-0 font-medium">{formatScore(score.score)} <span class="text-xs text-muted-foreground">/ cutoff {formatScore(cutoff)}</span></span>
                              </li>
                            {/each}
                          </ul>
                        {:else}
                          {#if row.result_available}
                            <p class="mt-2 text-sm text-muted-foreground">A finalized assessment result is available. Scores are shown only in the released applicant record.</p>
                          {:else}
                            <p class="mt-2 text-sm text-muted-foreground">No finalized assessment result is available.</p>
                          {/if}
                        {/if}
                      </section>
                    </div>

                    <div class="rounded-md border-l-2 border-primary/50 bg-primary/5 px-3 py-2.5">
                      <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Referral reason from Guidance</p>
                      <p class="mt-1 whitespace-pre-wrap text-sm">{row.reason || 'No referral reason was recorded.'}</p>
                    </div>

                    <div class="flex flex-wrap justify-end gap-2 border-t pt-3">
                      <Link href={applicantUrl(row.attempt_id)}>
                        <Button variant="outline" size="sm">Open applicant</Button>
                      </Link>
                      <Button variant="default" size="sm" class="gap-1.5" onclick={() => openResolve(row)}>
                        <MessageSquareWarning class="size-4" />
                        Resolve
                      </Button>
                    </div>
                  </Card.Content>
                </Card.Root>
              {/each}
            </div>
          {/if}
        </Tabs.Content>

        <Tabs.Content value="ready_for_enrollment" class="pt-4">
          {#if displayedList('ready_for_enrollment').length === 0}
            <p class="text-muted-foreground py-10 text-center">No applicants with a current confirmed admission clearance for this program.</p>
          {:else}
            <Table.Root class="w-full min-w-[720px] text-sm">
              <Table.Header>
                <Table.Row>
                  <Table.Head>Reference</Table.Head>
                  <Table.Head>Name</Table.Head>
                  <Table.Head>Clearance receipt</Table.Head>
                  <Table.Head>Confirmed</Table.Head>
                </Table.Row>
              </Table.Header>
              <Table.Body>
                {#each displayedList('ready_for_enrollment') as row (row.attempt_id)}
                  <Table.Row>
                    <Table.Cell class="font-mono text-xs">{row.reference_code}</Table.Cell>
                    <Table.Cell class="font-medium">{row.name}</Table.Cell>
                    <Table.Cell class="font-mono text-xs">{row.clearance_receipt_number}</Table.Cell>
                    <Table.Cell class="text-muted-foreground">{formatDateTime(row.cleared_at)}</Table.Cell>
                  </Table.Row>
                {/each}
              </Table.Body>
            </Table.Root>
          {/if}
        </Tabs.Content>
      </Tabs.Root>

      <!-- Applicant conversations -->
      <Card.Root>
        <Card.Header class="pb-3">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
              <div class="flex items-center gap-2">
                <MessageSquare class="size-4 text-primary" />
                <Card.Title class="text-lg">Applicant conversations</Card.Title>
              </div>
              <Card.Description>
                Bounded preview of active applicant support case threads for {dashboard.program.code}{cycle ? ` · cycle ${cycle}` : ''}.
              </Card.Description>
            </div>
            <div class="flex items-center gap-3">
              <Badge variant="secondary" class="font-mono text-xs">
                {conversationsList.length} shown
              </Badge>
              <Link
                href={`/staff/inquiries?group=case_threads&program_code=${encodeURIComponent(programCode)}&cycle_code=${encodeURIComponent(cycle ?? '')}`}
                class="text-sm font-medium text-primary underline-offset-4 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
              >
                View all in Messages
              </Link>
            </div>
          </div>
        </Card.Header>
        <Card.Content class="pt-2">
          {#if conversationsList.length === 0}
            <p class="py-8 text-center text-sm text-muted-foreground">
              No applicant conversations recorded for this program and cycle.
            </p>
          {:else}
            <Table.Root class="w-full min-w-[640px] text-sm">
              <Table.Header>
                <Table.Row>
                  <Table.Head>Candidate</Table.Head>
                  <Table.Head>Subject</Table.Head>
                  <Table.Head>Status</Table.Head>
                  <Table.Head>Last Activity</Table.Head>
                  <Table.Head class="text-right">Action</Table.Head>
                </Table.Row>
              </Table.Header>
              <Table.Body>
                {#each conversationsList as conv (conv.id)}
                  <Table.Row>
                    <Table.Cell>
                      <div class="space-y-0.5">
                        <div class="font-medium text-foreground">
                          {conv.applicant?.display_name ?? conv.applicant?.reference_code ?? `Attempt #${conv.attempt_id}`}
                        </div>
                        {#if conv.applicant?.reference_code && conv.applicant?.display_name}
                          <div class="font-mono text-xs text-muted-foreground">
                            {conv.applicant.reference_code}
                          </div>
                        {/if}
                      </div>
                    </Table.Cell>
                    <Table.Cell>
                      <div class="flex items-center gap-1.5">
                        <span class="font-medium">{conv.subject}</span>
                        {#if conv.unread_count > 0}
                          <Badge variant="destructive" class="px-1.5 py-0 text-[10px]">
                            {conv.unread_count} unread
                          </Badge>
                        {/if}
                      </div>
                    </Table.Cell>
                    <Table.Cell>
                      <Badge variant={conv.status === 'open' ? 'default' : 'secondary'} class="capitalize text-xs">
                        {conv.status}
                      </Badge>
                    </Table.Cell>
                    <Table.Cell class="text-muted-foreground text-xs">
                      {formatDateTime(conv.last_activity_at)}
                    </Table.Cell>
                    <Table.Cell class="text-right">
                      <Link
                        href={conv.html_url}
                        class="inline-flex items-center text-xs font-medium text-primary hover:underline"
                      >
                        Open record &rarr;
                      </Link>
                    </Table.Cell>
                  </Table.Row>
                {/each}
              </Table.Body>
            </Table.Root>
          {/if}
        </Card.Content>
      </Card.Root>

      <p class="text-xs text-muted-foreground">
        Source: {dashboard.source ?? 'committed domain records'} · as of {formatDateTime(dashboard.as_of)}
      </p>
    {/if}
  </div>

  <Dialog.Root bind:open={resolveDialogOpen}>
    <Dialog.Content class="max-h-[90vh] overflow-y-auto sm:max-w-xl">
      <Dialog.Header>
        <Dialog.Title>Record academic review outcome</Dialog.Title>
        <Dialog.Description>
          {activeFollowUp ? `${activeFollowUp.name} (${activeFollowUp.reference_code})` : ''} — record the program head's resolution. The server re-checks scope and state.
        </Dialog.Description>
      </Dialog.Header>
      <div class="space-y-5">
        <fieldset class="space-y-2">
          <legend class="text-sm font-semibold">Choose an action</legend>
          <div class="grid gap-2">
            {#each resolveOutcomes as outcome (outcome.value)}
              <Button
                type="button"
                variant={resolveForm.outcome === outcome.value ? (outcome.value === 'declined_by_ph' ? 'destructive' : 'default') : 'outline'}
                aria-pressed={resolveForm.outcome === outcome.value}
                class={`h-auto w-full items-start justify-start whitespace-normal p-3 text-left ${resolveForm.outcome === outcome.value ? 'ring-2 ring-ring ring-offset-2' : ''}`}
                onclick={() => (resolveForm.outcome = outcome.value)}
              >
                <span class="flex w-full flex-col items-start gap-1">
                  <span class="font-semibold">{outcome.label}</span>
                  <span class={`text-xs font-normal ${outcome.variant === 'destructive' ? 'text-destructive-foreground/90' : (resolveForm.outcome === outcome.value ? 'text-primary-foreground/90' : 'text-muted-foreground')}`}>{outcome.description}</span>
                </span>
              </Button>
            {/each}
          </div>
        </fieldset>
        <div class="space-y-2">
          <label class="text-sm font-medium" for="resolve-reason">
            Resolution reason {#if resolveForm.outcome !== 'appearance_requested'}<span aria-hidden="true" class="text-destructive">*</span><span class="sr-only">required</span>{:else}<span class="text-muted-foreground">(optional)</span>{/if}
          </label>
          <Textarea
            id="resolve-reason"
            bind:value={resolveForm.reason}
            rows={3}
            autofocus
            required={resolveForm.outcome !== 'appearance_requested'}
            aria-required={resolveForm.outcome !== 'appearance_requested'}
            aria-invalid={Boolean(resolveForm.errors?.reason)}
            aria-describedby="resolve-reason-help"
            placeholder={resolveForm.outcome === 'appearance_requested' ? 'Add an optional note…' : 'Explain why this applicant is cleared or declined…'}
          />
          <p id="resolve-reason-help" class="text-xs text-muted-foreground">
            Clear and decline require a reason. Academic review does not confirm admission clearance or enrollment; declining returns the applicant to Guidance for alternative placement.
          </p>
        </div>
        {#if resolveForm.errors?.outcome || resolveForm.errors?.reason}
          <p class="border-destructive/40 bg-destructive/10 text-destructive rounded-md border px-3 py-2 text-xs">
            {resolveForm.errors.outcome ?? resolveForm.errors.reason}
          </p>
        {/if}
      </div>
      <Dialog.Footer>
        <Button type="button" variant="outline" onclick={() => (resolveDialogOpen = false)}>Back</Button>
        <Button
          type="button"
          disabled={resolveReasonRequired || resolveForm.processing}
          onclick={submitResolve}
        >
          {resolveForm.processing ? 'Resolving…' : 'Confirm resolution'}
        </Button>
      </Dialog.Footer>
    </Dialog.Content>
  </Dialog.Root>
</StaffLayout>
