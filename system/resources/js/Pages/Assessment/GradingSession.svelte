<script>
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
  import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
  import ModeBanner from '@/Components/kit/ModeBanner.svelte';
  import { Badge } from '@/Components/ui/badge';
  import { Button } from '@/Components/ui/button';
  import * as Table from '@/Components/ui/table';
  import { Link, router, usePage } from '@inertiajs/svelte';
  import { CheckCircle2, Circle, Lock, ScanLine, UserCheck } from 'lucide-svelte';

  let {
    page_state = 'ready',
    degraded = false,
    session = {},
    applicants = [],
    roster_stats = { assigned: 0, present_and_submitted: 0, absent: 0, pending_attendance: 0, no_submission: 0 },
    progress_stats = { total: 0, scored: 0, pending: 0 },
    aptitude_areas_count = 0,
    can_finalize = false,
  } = $props();

  const page = usePage();
  const finalizeError = $derived(page.props.errors?.finalize ?? '');
  let finalizing = $state(false);
  const isFinalized = $derived(session.status === 'finalized');

  const breadcrumbs = [
    { label: 'Scoring', href: '/grading' },
    { label: `Session ${session.exam_code}` },
  ];

  function submitFinalize() {
    if (!can_finalize || finalizing) return;
    finalizing = true;
    router.post(`/grading/sessions/${session.id}/finalize`, {
      expected_version: session.version,
    }, {
      onFinish: () => {
        finalizing = false;
      },
    });
  }
</script>

<svelte:head><title>Session {session.exam_code ?? ''} — Scoring</title></svelte:head>

<StaffLayout {breadcrumbs}>
  <div class="min-w-0 space-y-6 p-4 sm:p-6">
    {#if degraded}
      <ModeBanner variant="warning" title="Manual grading mode">
        Inference provider unavailable; manual entry remains authoritative.
      </ModeBanner>
    {/if}

    <PageHeaderBar
      title={`Session ${session.exam_code ?? '—'} — ${session.room_name ?? '—'}`}
      description={`${session.starts_at ?? '—'} · Opened ${session.opened_at ? new Date(session.opened_at).toLocaleDateString() : '—'}`}
    >
      {#snippet actions()}
        <div class="flex flex-wrap items-center gap-2">
          <Link href={`/grading/sessions/${session.id}/omr`} class="inline-flex min-h-10 items-center gap-2 rounded-md border border-primary/40 px-4 text-sm font-medium text-primary transition-colors hover:bg-primary/10">
            <ScanLine class="size-4" />
            Open scanner
          </Link>
          <div class="flex items-center gap-2 rounded-lg bg-muted/50 px-3 py-2">
            <UserCheck class="size-4 text-muted-foreground" />
            <span class="text-sm font-medium">
              {progress_stats.scored} of {progress_stats.total} scored
            </span>
          </div>
          {#if !isFinalized}
            <Button
              class="min-h-10 gap-2"
              disabled={!can_finalize || finalizing}
              title={!can_finalize
                ? `Every applicant must be scored for all ${aptitude_areas_count} aptitude areas first`
                : 'Finalize grading'}
              onclick={submitFinalize}
            >
              <Lock class="size-4" />
              {finalizing ? 'Finalizing…' : 'Finalize grading'}
            </Button>
          {/if}
        </div>
      {/snippet}
    </PageHeaderBar>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
      <div class="rounded-xl border border-border bg-card px-4 py-3">
        <p class="text-2xl font-semibold">{roster_stats.assigned}</p>
        <p class="text-xs text-muted-foreground">Assigned</p>
      </div>
      <div class="rounded-xl border border-border bg-card px-4 py-3">
        <p class="text-2xl font-semibold">{roster_stats.present_and_submitted}</p>
        <p class="text-xs text-muted-foreground">Present &amp; submitted</p>
      </div>
      <div class="rounded-xl border border-border bg-card px-4 py-3">
        <p class="text-2xl font-semibold">
          {roster_stats.absent}
          {#if roster_stats.pending_attendance > 0}
            <span class="text-sm font-normal text-muted-foreground"> + {roster_stats.pending_attendance} pending</span>
          {/if}
        </p>
        <p class="text-xs text-muted-foreground">Absent</p>
      </div>
      <div class="rounded-xl border border-border bg-card px-4 py-3">
        <p class="text-2xl font-semibold">{roster_stats.no_submission}</p>
        <p class="text-xs text-muted-foreground">Present, no submission</p>
      </div>
    </div>

    {#if roster_stats.absent > 0 || roster_stats.no_submission > 0}
      <p class="text-sm text-muted-foreground">
        {roster_stats.absent} absent and {roster_stats.no_submission} present-without-submission
        applicant{roster_stats.absent + roster_stats.no_submission === 1 ? '' : 's'} are not part of the grading
        roster and do not require scores.
      </p>
    {/if}

    {#if isFinalized}
      <ModeBanner variant="info" title="Finalized">
        Scores are locked and no longer editable. Finalized by {session.finalized_by_name ?? '—'} on
        {session.finalized_at ? new Date(session.finalized_at).toLocaleString() : '—'}.
      </ModeBanner>
    {/if}

    {#if finalizeError}
      <div class="rounded-md border border-destructive/40 bg-destructive/5 px-4 py-3 text-sm text-destructive">
        {finalizeError}
      </div>
    {/if}

    {#if !isFinalized && progress_stats.pending > 0}
      <p class="text-sm text-muted-foreground">
        {progress_stats.pending} applicant{progress_stats.pending === 1 ? '' : 's'} still need
        scores in all {aptitude_areas_count} aptitude areas before finalizing.
      </p>
    {/if}

    <div class="rounded-xl border border-border bg-card">
      <div class="p-6">
        <h2 class="text-lg font-semibold tracking-tight">Applicants</h2>
        <p class="mt-1 text-sm text-muted-foreground">
          Click an applicant to input or edit aptitude area scores.
        </p>
      </div>
      <div class="overflow-x-auto">
        <Table.Root class="w-full min-w-[560px] text-sm">
          <Table.Header class="bg-muted/50">
            <Table.Row>
              <Table.Head class="px-4 py-3">Reference</Table.Head>
              <Table.Head class="px-4 py-3">Name</Table.Head>
              <Table.Head class="px-4 py-3">Progress</Table.Head>
              <Table.Head class="px-4 py-3 text-center">Action</Table.Head>
            </Table.Row>
          </Table.Header>
          <Table.Body>
            {#each applicants as app (app.attempt_id)}
              <Table.Row class="border-t border-border hover:bg-muted/30">
                <Table.Cell class="px-4 py-3 font-medium">{app.reference_code}</Table.Cell>
                <Table.Cell class="px-4 py-3">{app.name}</Table.Cell>
                <Table.Cell class="px-4 py-3">
                  {#if app.scored}
                    <Badge variant="success" class="gap-1">
                      <CheckCircle2 class="size-3" />
                      Complete
                    </Badge>
                  {:else if app.domains_complete > 0}
                    <Badge variant="warning">
                      {app.domains_complete} of {app.domains_total} aptitude areas
                    </Badge>
                  {:else}
                    <Badge variant="outline" class="gap-1">
                      <Circle class="size-3" />
                      Not started
                    </Badge>
                  {/if}
                </Table.Cell>
                <Table.Cell class="px-4 py-3 text-center">
                  <Link href={`/grading/sessions/${session.id}/attempts/${app.attempt_id}`}>
                    <Button variant="outline" size="sm" class="h-8 px-2 text-xs font-semibold">
                      {#if isFinalized}
                        View scores
                      {:else}
                        {app.scored ? 'Edit scores' : 'Input scores'}
                      {/if}
                    </Button>
                  </Link>
                </Table.Cell>
              </Table.Row>
            {:else}
              <Table.Row>
                <Table.Cell colspan={4} class="px-4 py-12 text-center text-muted-foreground">
                  {#if roster_stats.assigned === 0}
                    No applicants assigned to this exam session yet.
                  {:else}
                    No present-and-submitted applicants — nothing to grade.
                  {/if}
                </Table.Cell>
              </Table.Row>
            {/each}
          </Table.Body>
        </Table.Root>
      </div>
    </div>
  </div>
</StaffLayout>
