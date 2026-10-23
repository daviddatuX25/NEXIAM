<script>
import { newIdempotencyKey } from '@/lib/utils';
  /**
   * Program Head workspace — applicant record.
   * Tier A (summary) and Tier B (protected) shapes are server-authored.
   * The score region renders ONLY server-provided score_profile values; no
   * engine or recommendation UI exists here. Every visual has an identical
   * accessible table alternative.
   */
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
  import E2PageState from '@/Pages/Admission/E2PageState.svelte';
  import { Badge } from '@/Components/ui/badge';
  import { Button } from '@/Components/ui/button';
  import * as Card from '@/Components/ui/card';
  import * as Dialog from '@/Components/ui/dialog';
  import * as Select from '@/Components/ui/select';
  import * as Table from '@/Components/ui/table';
  import { Textarea } from '@/Components/ui/textarea';
  import { useForm } from '@inertiajs/svelte';
  import { AlertTriangle, Lock, ShieldAlert } from 'lucide-svelte';

  let {
    page_state = 'denied',
    record = null,
  } = $props();

  const programCode = $derived(record?.selection?.selection_program ?? '');

  const breadcrumbs = $derived([
    { label: 'My programs', href: '/staff/programs' },
    { label: record?.reference_code ?? 'Applicant' },
  ]);

  // ------------------------------------------------------------------
  // Decline selection (Tier B only; server re-checks every gate)
  // ------------------------------------------------------------------
  const declineForm = useForm({
    program_code: '',
    reason: '',
    idempotency_key: '',
  });

  let declineDialogOpen = $state(false);

  const canDecline = $derived(
    record?.tier === 'B' &&
      !!record?.selection?.selection_program &&
      !record?.legacy_program_mutation_blocked,
  );

  function openDecline() {
    declineForm.clearErrors();
    declineForm.program_code = record?.selection?.selection_program ?? '';
    declineForm.reason = '';
    declineForm.idempotency_key = newIdempotencyKey();
    declineDialogOpen = true;
  }

  function submitDecline() {
    if (!record) return;
    const program = record.selection?.selection_program ?? '';
    const url =
      typeof route === 'function'
        ? route('program-head.applicants.decline', { program, attempt: record.attempt_id })
        : `/staff/programs/${program}/applicants/${record.attempt_id}/decline`;
    declineForm.post(url, {
      preserveScroll: true,
      onSuccess: () => {
        declineDialogOpen = false;
      },
    });
  }

  // ------------------------------------------------------------------
  // Follow-up resolve (rows carry an id when the server sends one)
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
  const lifecycleLabels = {
    accepted: 'Accepted',
    result_released: 'Result released',
    advised: 'Advised',
    selection_pending_ack: 'Selection pending ack',
    selected: 'Selected',
    ready_for_enrollment: 'Ready for enrollment',
  };

  function lifecycleLabel(value) {
    return lifecycleLabels[value] ?? value ?? '—';
  }

  function lifecycleVariant(value) {
    if (value === 'ready_for_enrollment' || value === 'selected') return 'default';
    if (value === 'selection_pending_ack' || value === 'result_released') return 'outline';
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

  function formatScore(value, scale = null) {
    if (value === null || value === undefined || value === '') return '—';
    const numeric = Number(value);
    const formatted = Number.isNaN(numeric)
      ? String(value)
      : numeric.toLocaleString(undefined, { maximumFractionDigits: 1 });
    return scale === 'percentile_rank' ? `${formatted} percentile rank` : formatted;
  }

  const scoreProfile = $derived(record?.score_profile ?? null);
  const series = $derived(scoreProfile?.series ?? []);

  // SVG bar geometry — derived purely from server-provided series values.
  const chart = $derived.by(() => {
    const max = Math.max(1, ...series.map((s) => Number(s.score) || 0));
    const barHeight = 18;
    const rowGap = 16;
    const labelWidth = 170;
    const valueWidth = 64;
    const chartWidth = 760;
    const plotWidth = chartWidth - labelWidth - valueWidth;
    const chartHeight = series.length > 0 ? series.length * (barHeight + rowGap) + 8 : 0;
    return { max, barHeight, rowGap, labelWidth, valueWidth, chartWidth, plotWidth, chartHeight };
  });

  const consultationOutcome = $derived(record?.consultation_outcome ?? null);
  const selection = $derived(record?.selection ?? null);
  const followUps = $derived(record?.follow_ups ?? []);
</script>

<svelte:head><title>Applicant record</title></svelte:head>

<StaffLayout {breadcrumbs}>
  <div class="min-w-0 space-y-6 p-4 sm:p-6">
    {#if page_state !== 'ready'}
      <E2PageState state={page_state} />
    {:else if record}
      {#if declineForm.errors?.decline}
        <div class="border-destructive/40 bg-destructive/10 text-destructive rounded-md border px-4 py-3 text-sm">
          {declineForm.errors.decline}
        </div>
      {/if}

      <!-- Identity summary -->
      <Card.Root>
        <Card.Header class="border-b bg-muted/20 pb-3">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
              <Card.Title class="text-lg">{record.name}</Card.Title>
              <Card.Description class="font-mono text-xs">{record.reference_code}</Card.Description>
            </div>
            <div class="flex flex-wrap items-center gap-2">
              {#if record.lifecycle}
                <Badge variant={lifecycleVariant(record.lifecycle)}>{lifecycleLabel(record.lifecycle)}</Badge>
              {/if}
              <Badge variant={record.tier === 'B' ? 'default' : 'secondary'}>
                Tier {record.tier}
              </Badge>
              {#if record.tier === 'B'}
                <Badge variant="destructive">
                  <ShieldAlert class="mr-1 size-3" />
                  Protected record
                </Badge>
              {/if}
            </div>
          </div>
        </Card.Header>
        <Card.Content class="space-y-3 pt-5 text-sm">
          {#if record.tier === 'A' && (record.basis ?? []).length > 0}
            <div class="flex items-center justify-between gap-3">
              <span class="text-muted-foreground">Basis</span>
              <span class="flex flex-wrap justify-end gap-1">
                {#each record.basis as basis (basis)}
                  <Badge variant="outline">{basis}</Badge>
                {/each}
              </span>
            </div>
          {/if}
          {#if record.advised !== undefined && record.advised !== null}
            <div class="flex items-center justify-between gap-3">
              <span class="text-muted-foreground">Advised</span>
              <span class="font-medium">{record.advised ? 'Yes' : 'No'}</span>
            </div>
          {/if}
          {#if record.selection_status !== undefined && record.selection_status !== null}
            <div class="flex items-center justify-between gap-3">
              <span class="text-muted-foreground">Selection status</span>
              <span class="font-mono text-xs font-semibold uppercase tracking-wide">{record.selection_status}</span>
            </div>
          {/if}
          {#if record.follow_up_count !== undefined && record.follow_up_count !== null}
            <div class="flex items-center justify-between gap-3">
              <span class="text-muted-foreground">Open follow-ups</span>
              <span class="tabular-nums font-semibold">{record.follow_up_count}</span>
            </div>
          {/if}
          {#if record.legacy_program_mutation_blocked}
            <div class="flex items-center justify-between gap-3">
              <span class="text-muted-foreground">Historical program record</span>
              <span class="font-medium">Program changes unavailable</span>
            </div>
          {/if}
          {#if record.visibility_note}
            <div class="border-border rounded-md border bg-muted/30 p-3 text-sm">
              <p class="flex items-center gap-2 font-medium">
                <Lock class="size-4 text-muted-foreground" />
                {record.visibility_note}
              </p>
            </div>
          {/if}
          {#if record.tier === 'B' && record.access_reason}
            <div class="border-destructive/40 bg-destructive/10 text-destructive rounded-md border p-3 text-sm">
              <p class="flex items-center gap-2 font-medium">
                <ShieldAlert class="size-4" />
                Access reason
              </p>
              <p class="mt-1">{record.access_reason}</p>
            </div>
          {/if}
        </Card.Content>
      </Card.Root>

      {#if record.tier === 'B' && selection}
        <Card.Root>
          <Card.Header class="pb-3">
            <Card.Title class="text-lg">Selection</Card.Title>
            <Card.Description>
              {selection.version ? `Pool version ${selection.version}` : 'No active pool'}
            </Card.Description>
          </Card.Header>
          <Card.Content class="space-y-3 pt-4 text-sm">
            <div class="flex items-center justify-between gap-3">
              <span class="text-muted-foreground">Selected program</span>
              <span class="font-mono text-xs font-semibold text-foreground">{selection.selection_program ?? '—'}</span>
            </div>
            <div class="flex items-center justify-between gap-3">
              <span class="text-muted-foreground">Status</span>
              <span class="font-mono text-xs font-semibold uppercase tracking-wide">{selection.selection_status ?? '—'}</span>
            </div>
            {#if (selection.options ?? []).length > 0}
              <div>
                <p class="mb-1 text-muted-foreground">Options</p>
                <ul class="divide-y rounded-md border">
                  {#each selection.options as option (option.program_code)}
                    <li class="flex items-center justify-between gap-2 px-3 py-2">
                      <span class="font-medium">{option.program_name}</span>
                      <span class="flex items-center gap-2">
                        {#if option.basis}
                          <Badge variant="outline">{option.basis}</Badge>
                        {/if}
                        <span class="font-mono text-xs text-muted-foreground">{option.program_code}</span>
                      </span>
                    </li>
                  {/each}
                </ul>
              </div>
            {/if}
            {#if canDecline}
              <div class="flex justify-end pt-1">
                <Button type="button" variant="destructive" class="min-h-10" onclick={openDecline}>
                  <AlertTriangle class="size-4" />
                  Decline selection
                </Button>
              </div>
            {/if}
          </Card.Content>
        </Card.Root>
      {/if}

      {#if record.tier === 'B' && scoreProfile}
        <Card.Root>
          <Card.Header class="pb-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
              <div>
                <Card.Title class="text-lg">{scoreProfile.title ?? 'Released score profile'}</Card.Title>
                <Card.Description>
                  Unit: {scoreProfile.unit ?? 'score'}
                  {#if scoreProfile.source} · {scoreProfile.source}{/if}
                  {#if scoreProfile.version} · v{scoreProfile.version}{/if}
                  {#if scoreProfile.released_at} · released {formatDateTime(scoreProfile.released_at)}{/if}
                  {#if scoreProfile.freshness} · {scoreProfile.freshness}{/if}
                </Card.Description>
              </div>
            </div>
          </Card.Header>
          <Card.Content class="space-y-4 pt-4">
            {#if series.length === 0}
              <p class="text-muted-foreground py-8 text-center">No score values are available for this record.</p>
            {:else}
              <svg
                role="img"
                aria-labelledby="score-chart-title score-chart-desc"
                viewBox="0 0 {chart.chartWidth} {chart.chartHeight}"
                class="h-auto w-full"
              >
                <title id="score-chart-title">{scoreProfile.title ?? 'Released score profile'}</title>
                <desc id="score-chart-desc">
                  Bar chart of released scores by aptitude area. The identical values are listed in the table below.
                </desc>
                {#each series as s, i (s.area_code)}
                  {@const y = i * (chart.barHeight + chart.rowGap) + 4}
                  {@const barWidth = series.length > 0 ? (Number(s.score) || 0) / chart.max * chart.plotWidth : 0}
                  <text
                    x={chart.labelWidth - 10}
                    y={y + chart.barHeight / 2 + 4}
                    text-anchor="end"
                    class="fill-muted-foreground text-[11px]"
                  >
                    {s.area_name}
                  </text>
                  <rect
                    x={chart.labelWidth}
                    y={y}
                    width={barWidth}
                    height={chart.barHeight}
                    rx="3"
                    class="fill-primary/80"
                  />
                  <text
                    x={chart.labelWidth + chart.plotWidth + 10}
                    y={y + chart.barHeight / 2 + 4}
                    class="fill-foreground text-[11px] tabular-nums"
                  >
                    {formatScore(s.score, s.scale)}
                  </text>
                {/each}
              </svg>

              <Table.Root class="w-full text-sm">
                <Table.Caption class="text-left text-xs text-muted-foreground">
                  Score profile — equivalent values to the chart above.
                </Table.Caption>
                <Table.Header>
                  <Table.Row>
                    <Table.Head>Area</Table.Head>
                    <Table.Head class="text-right">Score</Table.Head>
                  </Table.Row>
                </Table.Header>
                <Table.Body>
                  {#each series as s (s.area_code)}
                    <Table.Row>
                      <Table.Cell class="font-medium">{s.area_name}</Table.Cell>
                      <Table.Cell class="text-right tabular-nums">{formatScore(s.score, s.scale)}</Table.Cell>
                    </Table.Row>
                  {/each}
                </Table.Body>
              </Table.Root>
            {/if}
          </Card.Content>
        </Card.Root>
      {/if}

      {#if record.tier === 'B' && consultationOutcome}
        <Card.Root>
          <Card.Header class="pb-3">
            <Card.Title class="text-lg">Consultation outcome</Card.Title>
            {#if consultationOutcome.version}
              <Card.Description>Evidence version {consultationOutcome.version}</Card.Description>
            {/if}
          </Card.Header>
          <Card.Content class="space-y-3 pt-4 text-sm">
            <div class="flex items-center justify-between gap-3">
              <span class="text-muted-foreground">Decision</span>
              <span class="font-mono text-xs font-semibold uppercase tracking-wide">{consultationOutcome.decision_type ?? '—'}</span>
            </div>
            <div class="flex items-center justify-between gap-3">
              <span class="text-muted-foreground">Recorded by</span>
              <span class="font-medium">{consultationOutcome.recorded_by ?? '—'}</span>
            </div>
            <div class="flex items-center justify-between gap-3">
              <span class="text-muted-foreground">Recorded at</span>
              <span class="text-muted-foreground">{formatDateTime(consultationOutcome.recorded_at)}</span>
            </div>
            {#if consultationOutcome.notes}
              <div class="border-border rounded-md border bg-muted/30 p-3">
                <p class="whitespace-pre-wrap text-sm text-foreground">{consultationOutcome.notes}</p>
              </div>
            {/if}
          </Card.Content>
        </Card.Root>
      {/if}

      {#if record.tier === 'B' && followUps.length > 0}
        <Card.Root>
          <Card.Header class="pb-3">
            <Card.Title class="text-lg">Follow-ups</Card.Title>
            <Card.Description>Open and historical follow-up activity for this applicant.</Card.Description>
          </Card.Header>
          <Card.Content class="pt-2">
            <Table.Root class="w-full text-sm">
              <Table.Header>
                <Table.Row>
                  <Table.Head>Program</Table.Head>
                  <Table.Head>Status</Table.Head>
                  <Table.Head>Reason</Table.Head>
                  <Table.Head>Opened</Table.Head>
                  <Table.Head class="text-right">Actions</Table.Head>
                </Table.Row>
              </Table.Header>
              <Table.Body>
                {#each followUps as row, i (i)}
                  <Table.Row>
                    <Table.Cell class="font-mono text-xs">{row.program_code ?? '—'}</Table.Cell>
                    <Table.Cell>
                      <Badge variant={followUpStatusVariant(row.status)}>{followUpStatusLabel(row.status)}</Badge>
                    </Table.Cell>
                    <Table.Cell class="max-w-[240px]">
                      <span class="line-clamp-2 text-muted-foreground">{row.reason ?? row.resolution_reason ?? '—'}</span>
                    </Table.Cell>
                    <Table.Cell class="whitespace-nowrap text-muted-foreground">{formatDateTime(row.opened_at)}</Table.Cell>
                    <Table.Cell class="text-right">
                      {#if row.id}
                        <Button variant="ghost" size="sm" class="h-8 px-2 text-xs font-semibold" onclick={() => openResolve(row)}>
                          Resolve
                        </Button>
                      {:else}
                        <span class="text-xs text-muted-foreground">
                          {row.resolved_at ? `Resolved ${formatDateTime(row.resolved_at)}` : ''}
                        </span>
                      {/if}
                    </Table.Cell>
                  </Table.Row>
                {/each}
              </Table.Body>
            </Table.Root>
          </Card.Content>
        </Card.Root>
      {/if}

      <p class="text-xs text-muted-foreground">
        Record as of {formatDateTime(record.as_of)}. Scores and counselor notes are shown only at the visibility the server granted.
      </p>
    {/if}
  </div>

  <Dialog.Root bind:open={declineDialogOpen}>
    <Dialog.Content class="sm:max-w-md">
      <Dialog.Header>
        <Dialog.Title>Decline program selection</Dialog.Title>
        <Dialog.Description>
          Terminal action: releases the applicant's selection for this program and records an immutable decline. Requires a reason.
        </Dialog.Description>
      </Dialog.Header>
      <div class="space-y-4">
        <div class="flex items-center justify-between gap-3 rounded-md border bg-muted/30 px-3 py-2 text-sm">
          <span class="text-muted-foreground">Program</span>
          <span class="font-mono text-xs font-semibold">{declineForm.program_code || '—'}</span>
        </div>
        <div class="space-y-2">
          <label class="text-sm font-medium" for="decline-reason">Reason</label>
          <Textarea
            id="decline-reason"
            bind:value={declineForm.reason}
            rows={3}
            placeholder="Reason for declining this selection…"
          />
        </div>
        {#if declineForm.errors?.decline}
          <p class="border-destructive/40 bg-destructive/10 text-destructive rounded-md border px-3 py-2 text-xs">
            {declineForm.errors.decline}
          </p>
        {/if}
      </div>
      <Dialog.Footer>
        <Button type="button" variant="outline" onclick={() => (declineDialogOpen = false)}>Back</Button>
        <Button
          type="button"
          variant="destructive"
          disabled={!declineForm.reason.trim() || declineForm.processing}
          onclick={submitDecline}
        >
          {declineForm.processing ? 'Declining…' : 'Confirm decline'}
        </Button>
      </Dialog.Footer>
    </Dialog.Content>
  </Dialog.Root>

  <Dialog.Root bind:open={resolveDialogOpen}>
    <Dialog.Content class="sm:max-w-md">
      <Dialog.Header>
        <Dialog.Title>Resolve follow-up</Dialog.Title>
        <Dialog.Description>
          {activeFollowUp ? `${record?.name ?? ''}` : ''} — record the program head's resolution. The server re-checks scope and state.
        </Dialog.Description>
      </Dialog.Header>
      <div class="space-y-4">
        <div class="space-y-2">
          <label class="text-sm font-medium" for="record-resolve-outcome">Outcome</label>
          <Select.Root
            type="single"
            value={resolveForm.outcome}
            onValueChange={(v) => (resolveForm.outcome = v ?? '')}
          >
            <Select.Trigger id="record-resolve-outcome" class="w-full min-h-[40px]">
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
          <label class="text-sm font-medium" for="record-resolve-reason">Reason</label>
          <Textarea
            id="record-resolve-reason"
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
