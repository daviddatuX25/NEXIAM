<script>
import { newIdempotencyKey } from '@/lib/utils';
  /**
   * Program Head workspace — program dashboard.
   * Server-authored metrics/lists only. Metrics are plain cards + tables,
   * so no chart region exists and no chart alternative is needed.
   */
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
  import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
  import E2PageState from '@/Pages/Admission/E2PageState.svelte';
  import KpiCard from '@/Components/kit/KpiCard.svelte';
  import { Badge } from '@/Components/ui/badge';
  import { Button } from '@/Components/ui/button';
  import * as Card from '@/Components/ui/card';
  import * as Dialog from '@/Components/ui/dialog';
  import * as Select from '@/Components/ui/select';
  import * as Table from '@/Components/ui/table';
  import * as Tabs from '@/Components/ui/tabs';
  import { Textarea } from '@/Components/ui/textarea';
  import { Link, useForm } from '@inertiajs/svelte';
  import { ClipboardList, MessageSquareWarning, UserCheck } from 'lucide-svelte';

  let {
    page_state = 'denied',
    dashboard = null,
    lists = null,
  } = $props();

  const programCode = $derived(dashboard?.program?.code ?? '');
  const cycle = $derived(dashboard?.cycle ?? lists?.cycle ?? null);
  const metrics = $derived(dashboard?.metrics ?? null);
  const quota = $derived(metrics?.quota ?? null);

  const breadcrumbs = $derived.by(() => {
    const crumbs = [
      { label: 'My programs', href: '/staff/programs' },
      { label: dashboard?.program?.code ?? 'Program' },
    ];
    if (dashboard?.program?.name) {
      crumbs[1] = { label: dashboard.program.name };
    }
    return crumbs;
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
    { value: 'cleared_by_ph', label: 'Cleared by program head' },
    { value: 'declined_by_ph', label: 'Declined by program head' },
    { value: 'appearance_requested', label: 'Appearance requested' },
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
    });
  }

  const resolveReasonRequired = $derived(
    resolveForm.outcome !== 'appearance_requested' && !resolveForm.reason.trim(),
  );

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
    enrolling: 'Enrolling',
    enrolled: 'Enrolled',
  };

  function stageLabel(stage) {
    return stageLabels[stage] ?? stage ?? '—';
  }

  function stageVariant(stage) {
    if (stage === 'enrolled' || stage === 'enrolling' || stage === 'selected' || stage === 'upcoming') return 'default';
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

  const metricCards = $derived.by(() => {
    if (!metrics) return [];
    return [
      { label: 'Preference candidates', value: metrics.preference_candidates ?? 0 },
      { label: 'Accepted candidates', value: metrics.accepted_candidates ?? 0 },
      { label: 'Exam completed', value: metrics.exam_completed_candidates ?? 0 },
      { label: 'Result released', value: metrics.result_released_candidates ?? 0 },
      { label: 'Advised', value: metrics.advised_candidates ?? 0 },
      { label: 'Guidance pool', value: metrics.guidance_pool_candidates ?? 0 },
      { label: 'Active reservations', value: metrics.active_reservations ?? 0 },
      { label: 'Open follow-ups', value: metrics.open_follow_ups ?? 0 },
      { label: 'Enrolling', value: metrics.enrolling ?? 0 },
      { label: 'Enrolled', value: metrics.enrolled ?? 0 },
    ];
  });

  let activeTab = $state('potential');

  const tabCount = (key) => (lists ? (lists[key]?.length ?? 0) : 0);
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
      />

      {#if resolveForm.errors?.follow_up || resolveForm.errors?.outcome || resolveForm.errors?.reason}
        <div class="border-destructive/40 bg-destructive/10 text-destructive rounded-md border px-4 py-3 text-sm">
          {resolveForm.errors.follow_up ?? resolveForm.errors.outcome ?? resolveForm.errors.reason}
        </div>
      {/if}

      <!-- Metrics -->
      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
        {#each metricCards as card (card.label)}
          <KpiCard label={card.label} value={card.value} />
        {/each}
      </div>

      <!-- Quota card -->
      {#if quota}
        <Card.Root>
          <Card.Header class="pb-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
              <div>
                <Card.Title class="text-lg">Quota</Card.Title>
                <Card.Description>{cycle ? `Cycle ${cycle}` : 'No active cycle'}</Card.Description>
              </div>
              {#if quota.over_capacity > 0}
                <Badge variant="destructive">Over capacity by {quota.over_capacity}</Badge>
              {:else if !quota.configured}
                <Badge variant="secondary">Not configured</Badge>
              {/if}
            </div>
          </Card.Header>
          <Card.Content class="space-y-2 pt-4 text-sm">
            <div class="flex items-center justify-between gap-3">
              <span class="text-muted-foreground">Configured</span>
              {#if quota.configured}
                <span class="inline-flex items-center gap-1.5 font-medium text-foreground"><UserCheck class="size-4 text-primary" /> Yes</span>
              {:else}
                <span class="inline-flex items-center gap-1.5 font-medium text-muted-foreground"><ClipboardList class="size-4" /> No</span>
              {/if}
            </div>
            <div class="flex items-center justify-between gap-3">
              <span class="text-muted-foreground">Capacity</span>
              <span class="tabular-nums font-semibold text-foreground">{quota.capacity ?? '—'}</span>
            </div>
            <div class="flex items-center justify-between gap-3">
              <span class="text-muted-foreground">Committed</span>
              <span class="tabular-nums font-semibold text-foreground">{quota.committed ?? 0}</span>
            </div>
            <div class="flex items-center justify-between gap-3">
              <span class="text-muted-foreground">Remaining</span>
              <span class="tabular-nums font-semibold text-foreground">{quota.remaining ?? 0}</span>
            </div>
          </Card.Content>
        </Card.Root>
      {/if}

      <!-- Candidate lists -->
      <Tabs.Root bind:value={activeTab}>
        <Tabs.List variant="line">
          <Tabs.Trigger value="potential">Potential ({tabCount('potential')})</Tabs.Trigger>
          <Tabs.Trigger value="upcoming">Upcoming ({tabCount('upcoming')})</Tabs.Trigger>
          <Tabs.Trigger value="follow_ups">Follow-up ({tabCount('follow_ups')})</Tabs.Trigger>
          <Tabs.Trigger value="enrolling">Enrolling ({tabCount('enrolling')})</Tabs.Trigger>
          <Tabs.Trigger value="enrolled">Enrolled ({tabCount('enrolled')})</Tabs.Trigger>
        </Tabs.List>

        <Tabs.Content value="potential" class="pt-4">
          {#if lists.potential.length === 0}
            <p class="text-muted-foreground py-10 text-center">No potential candidates for this program.</p>
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
                {#each lists.potential as row (row.attempt_id)}
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
          {#if lists.upcoming.length === 0}
            <p class="text-muted-foreground py-10 text-center">No upcoming candidates for this program.</p>
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
                {#each lists.upcoming as row (row.attempt_id)}
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
          {#if lists.follow_ups.length === 0}
            <p class="text-muted-foreground py-10 text-center">No open follow-ups for this program.</p>
          {:else}
            <Table.Root class="w-full min-w-[880px] text-sm">
              <Table.Header>
                <Table.Row>
                  <Table.Head>Reference</Table.Head>
                  <Table.Head>Name</Table.Head>
                  <Table.Head>Status</Table.Head>
                  <Table.Head>Reason</Table.Head>
                  <Table.Head>Opened</Table.Head>
                  <Table.Head class="text-right">Actions</Table.Head>
                </Table.Row>
              </Table.Header>
              <Table.Body>
                {#each lists.follow_ups as row (row.id)}
                  <Table.Row>
                    <Table.Cell class="font-mono text-xs">{row.reference_code}</Table.Cell>
                    <Table.Cell>
                      <span class="font-medium">{row.name}</span>
                      {#if row.overlaps_enrolling}
                        <div><Badge variant="secondary">Overlaps enrolling</Badge></div>
                      {/if}
                    </Table.Cell>
                    <Table.Cell>
                      <Badge variant={followUpStatusVariant(row.status)}>{followUpStatusLabel(row.status)}</Badge>
                    </Table.Cell>
                    <Table.Cell class="max-w-[240px]">
                      <span class="line-clamp-2 text-muted-foreground">{row.reason ?? '—'}</span>
                    </Table.Cell>
                    <Table.Cell class="whitespace-nowrap text-muted-foreground">{formatDateTime(row.opened_at)}</Table.Cell>
                    <Table.Cell class="text-right">
                      <div class="inline-flex flex-wrap justify-end gap-1">
                        <Link href={applicantUrl(row.attempt_id)}>
                          <Button variant="ghost" size="sm" class="h-8 px-2 text-xs font-semibold">
                            Open applicant
                          </Button>
                        </Link>
                        <Button variant="ghost" size="sm" class="h-8 px-2 text-xs font-semibold" onclick={() => openResolve(row)}>
                          <MessageSquareWarning class="mr-1.5 size-3.5" />
                          Resolve
                        </Button>
                      </div>
                    </Table.Cell>
                  </Table.Row>
                {/each}
              </Table.Body>
            </Table.Root>
          {/if}
        </Tabs.Content>

        <Tabs.Content value="enrolling" class="pt-4">
          {#if lists.enrolling.length === 0}
            <p class="text-muted-foreground py-10 text-center">No applicants are enrolling in this program.</p>
          {:else}
            <Table.Root class="w-full min-w-[560px] text-sm">
              <Table.Header>
                <Table.Row>
                  <Table.Head>Reference</Table.Head>
                  <Table.Head>Name</Table.Head>
                  <Table.Head>Enrolling since</Table.Head>
                </Table.Row>
              </Table.Header>
              <Table.Body>
                {#each lists.enrolling as row (row.attempt_id)}
                  <Table.Row>
                    <Table.Cell class="font-mono text-xs">{row.reference_code}</Table.Cell>
                    <Table.Cell class="font-medium">{row.name}</Table.Cell>
                    <Table.Cell class="text-muted-foreground">{formatDateTime(row.stamped_at)}</Table.Cell>
                  </Table.Row>
                {/each}
              </Table.Body>
            </Table.Root>
          {/if}
        </Tabs.Content>

        <Tabs.Content value="enrolled" class="pt-4">
          {#if lists.enrolled.length === 0}
            <p class="text-muted-foreground py-10 text-center">No applicants are enrolled in this program.</p>
          {:else}
            <Table.Root class="w-full min-w-[560px] text-sm">
              <Table.Header>
                <Table.Row>
                  <Table.Head>Reference</Table.Head>
                  <Table.Head>Name</Table.Head>
                  <Table.Head>Enrolled</Table.Head>
                </Table.Row>
              </Table.Header>
              <Table.Body>
                {#each lists.enrolled as row (row.attempt_id)}
                  <Table.Row>
                    <Table.Cell class="font-mono text-xs">{row.reference_code}</Table.Cell>
                    <Table.Cell class="font-medium">{row.name}</Table.Cell>
                    <Table.Cell class="text-muted-foreground">{formatDateTime(row.stamped_at)}</Table.Cell>
                  </Table.Row>
                {/each}
              </Table.Body>
            </Table.Root>
          {/if}
        </Tabs.Content>
      </Tabs.Root>

      <p class="text-xs text-muted-foreground">
        Source: {dashboard.source ?? 'committed domain records'} · as of {formatDateTime(dashboard.as_of)}
      </p>
    {/if}
  </div>

  <Dialog.Root bind:open={resolveDialogOpen}>
    <Dialog.Content class="sm:max-w-md">
      <Dialog.Header>
        <Dialog.Title>Resolve follow-up</Dialog.Title>
        <Dialog.Description>
          {activeFollowUp ? `${activeFollowUp.name} (${activeFollowUp.reference_code})` : ''} — record the program head's resolution. The server re-checks scope and state.
        </Dialog.Description>
      </Dialog.Header>
      <div class="space-y-4">
        <div class="space-y-2">
          <label class="text-sm font-medium" for="resolve-outcome">Outcome</label>
          <Select.Root
            type="single"
            value={resolveForm.outcome}
            onValueChange={(v) => (resolveForm.outcome = v ?? '')}
          >
            <Select.Trigger id="resolve-outcome" class="w-full min-h-[40px]">
              {resolveOutcomes.find((o) => o.value === resolveForm.outcome)?.label ?? 'Select outcome'}
            </Select.Trigger>
            <Select.Content>
              {#each resolveOutcomes as outcome (outcome.value)}
                <Select.Item value={outcome.value} label={outcome.label} />
              {/each}
            </Select.Content>
          </Select.Root>
        </div>
        <div class="space-y-2">
          <label class="text-sm font-medium" for="resolve-reason">Reason</label>
          <Textarea
            id="resolve-reason"
            bind:value={resolveForm.reason}
            rows={3}
            placeholder="Required for cleared or declined outcomes…"
          />
          <p class="text-xs text-muted-foreground">
            Terminal outcomes (cleared / declined) require a reason.
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
