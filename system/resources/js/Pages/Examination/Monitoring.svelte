<script>
  /**
   * Per-session Exam Monitoring — the conduct phase of an exam session
   * (published / in-progress). Test Administrators and proctors on active duty
   * for this session may record attendance and submission per assigned applicant.
   */
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
  import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
  import { Badge } from '@/Components/ui/badge';
  import { Button } from '@/Components/ui/button';
  import * as Table from '@/Components/ui/table';
  import { router } from '@inertiajs/svelte';
  import { newIdempotencyKey } from '@/lib/utils';
  import { CheckCircle2, Circle, UserCheck, UserX, FileCheck } from 'lucide-svelte';

  let {
    session,
    roster = [],
    can_manage = false,
    can_record = false,
    page_state = 'ready',
  } = $props();

  let busy = $state({});

  function setBusy(assignmentId, key, on) {
    busy = { ...busy, [`${assignmentId}:${key}`]: on };
  }

  function isBusy(assignmentId, key) {
    return busy[`${assignmentId}:${key}`] === true;
  }

  function recordAttendance(assignmentId, outcome) {
    setBusy(assignmentId, 'attendance', true);
    router.post(
      `/exams/sessions/${session.id}/monitoring/assignments/${assignmentId}/attendance`,
      { outcome, idempotency_key: newIdempotencyKey() },
      {
        preserveScroll: true,
        onFinish: () => setBusy(assignmentId, 'attendance', false),
      },
    );
  }

  function recordSubmission(assignmentId) {
    setBusy(assignmentId, 'submission', true);
    router.post(
      `/exams/sessions/${session.id}/monitoring/assignments/${assignmentId}/submission`,
      { idempotency_key: newIdempotencyKey() },
      {
        preserveScroll: true,
        onFinish: () => setBusy(assignmentId, 'submission', false),
      },
    );
  }

  function attendanceBadge(attendance) {
    if (attendance === 'present') {
      return { label: 'Present', variant: 'default' };
    }
    if (attendance === 'absent') {
      return { label: 'Absent', variant: 'secondary' };
    }
    return { label: 'Not recorded', variant: 'outline' };
  }

  function statusVariant(status) {
    if (status === 'published') return 'default';
    if (status === 'in_progress') return 'outline';
    return 'outline';
  }

  function statusLabel(status) {
    if (status === 'published') return 'Published';
    if (status === 'in_progress') return 'In progress';
    return status;
  }

  const breadcrumbs = [
    { label: 'Exam Monitoring', href: '/exams/monitoring' },
    { label: session?.code ?? 'Session' },
  ];
</script>

<svelte:head><title>{session?.code ?? 'Session'} · Monitoring</title></svelte:head>

<StaffLayout {breadcrumbs}>
  <div class="min-w-0 space-y-6 p-4 sm:p-6">
    <PageHeaderBar
      title={`${session.code} · Monitoring`}
      description="Record attendance and submission for each assigned applicant during the exam session."
    >
      {#snippet actions()}
        <div class="flex flex-wrap gap-2">
          <Badge variant={statusVariant(session.status)}>{statusLabel(session.status)}</Badge>
        </div>
      {/snippet}
    </PageHeaderBar>

    <div class="rounded-xl border border-border bg-card p-6">
      <dl class="grid gap-3 sm:grid-cols-2">
        <div>
          <dt class="text-sm text-muted-foreground">When</dt>
          <dd class="font-medium">
            {session.date} · {session.start_time}{#if session.end_time}–{session.end_time}{/if}
          </dd>
        </div>
        <div>
          <dt class="text-sm text-muted-foreground">Room</dt>
          <dd class="font-medium">
            {session.room?.name ?? session.room?.code ?? '—'} (cap. {session.room?.capacity ?? '—'})
          </dd>
        </div>
        <div>
          <dt class="text-sm text-muted-foreground">Academic year</dt>
          <dd class="font-medium">{session.academic_cycle?.label ?? '—'}</dd>
        </div>
        <div>
          <dt class="text-sm text-muted-foreground">Assigned applicants</dt>
          <dd class="font-medium">{roster.length}</dd>
        </div>
      </dl>
    </div>

    {#if page_state === 'denied'}
      <p class="text-sm text-muted-foreground">You do not have permission to monitor this session.</p>
    {:else}
      <div class="rounded-xl border border-border bg-card p-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
          <div>
            <h2 class="text-lg font-semibold tracking-tight">Conduct roster</h2>
            <p class="mt-1 text-sm text-muted-foreground">
              {#if can_manage}
                Mark attendance and submission for each applicant. Closing the session opens grading.
              {:else}
                You can record attendance and submission for this assigned session only.
              {/if}
            </p>
          </div>
        </div>

        {#if roster.length > 0}
          <div class="mt-4 overflow-x-auto">
            <Table.Root>
              <Table.Header class="bg-muted/50">
                <Table.Row>
                  <Table.Head>Reference</Table.Head>
                  <Table.Head>Name</Table.Head>
                  <Table.Head>Attendance</Table.Head>
                  <Table.Head>Submission</Table.Head>
                  {#if can_record}
                    <Table.Head class="text-center">Actions</Table.Head>
                  {/if}
                </Table.Row>
              </Table.Header>
              <Table.Body>
                {#each roster as row (row.id)}
                  {@const att = attendanceBadge(row.attendance)}
                  <Table.Row>
                    <Table.Cell>{row.reference_number}</Table.Cell>
                    <Table.Cell class="font-medium">{row.name}</Table.Cell>
                    <Table.Cell>
                      <Badge variant={att.variant}>{att.label}</Badge>
                    </Table.Cell>
                    <Table.Cell>
                      {#if row.submitted}
                        <span class="inline-flex items-center gap-1.5 text-sm font-medium text-emerald-600 dark:text-emerald-400">
                          <CheckCircle2 class="size-4" />
                          Submitted
                        </span>
                      {:else}
                        <span class="inline-flex items-center gap-1.5 text-sm text-muted-foreground">
                          <Circle class="size-4" />
                          Not submitted
                        </span>
                      {/if}
                    </Table.Cell>
                    {#if can_record}
                      <Table.Cell class="text-center">
                        <div class="inline-flex flex-wrap justify-center gap-1">
                          <Button
                            variant="ghost"
                            size="sm"
                            class="h-8 px-2 text-xs"
                            disabled={row.attendance === 'present' || isBusy(row.id, 'attendance')}
                            onclick={() => recordAttendance(row.id, 'present')}
                          >
                            <UserCheck class="mr-1.5 size-3.5" />
                            Present
                          </Button>
                          <Button
                            variant="ghost"
                            size="sm"
                            class="h-8 px-2 text-xs"
                            disabled={row.attendance === 'absent' || isBusy(row.id, 'attendance')}
                            onclick={() => recordAttendance(row.id, 'absent')}
                          >
                            <UserX class="mr-1.5 size-3.5" />
                            Absent
                          </Button>
                          <Button
                            variant="ghost"
                            size="sm"
                            class="h-8 px-2 text-xs"
                            disabled={row.submitted || isBusy(row.id, 'submission') || row.attendance !== 'present'}
                            title={row.attendance !== 'present' ? 'Mark present before recording submission' : 'Record submission'}
                            onclick={() => recordSubmission(row.id)}
                          >
                            <FileCheck class="mr-1.5 size-3.5" />
                            Submitted
                          </Button>
                        </div>
                      </Table.Cell>
                    {/if}
                  </Table.Row>
                {/each}
              </Table.Body>
            </Table.Root>
          </div>
        {:else}
          <p class="mt-4 text-sm text-muted-foreground">No assigned applicants to monitor.</p>
        {/if}
      </div>
    {/if}
  </div>
</StaffLayout>
