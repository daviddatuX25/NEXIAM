<script>
  /**
   * Exam Monitoring index — the conduct phase (published / in-progress sessions),
   * distinct from scheduling. Test Administrators see all conduct sessions;
   * proctor-duty holders see only their duty sessions (D-015).
   */
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
  import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
  import SwitchableListView from '@/Components/kit/SwitchableListView.svelte';
  import { Badge } from '@/Components/ui/badge';
  import { Button } from '@/Components/ui/button';
  import * as Table from '@/Components/ui/table';
  import { Link, router } from '@inertiajs/svelte';
  import { Activity, Eye } from 'lucide-svelte';

  let { page_state = 'ready', sessions = [], can_manage = false } = $props();

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

  function formatWhen(session) {
    if (!session.date) return '—';
    const start = session.start_time ?? '';
    const end = session.end_time ?? '';
    return end ? `${session.date} · ${start}–${end}` : `${session.date} · ${start}`;
  }

  const breadcrumbs = [{ label: 'Exam Monitoring' }];
</script>

<svelte:head><title>Exam monitoring</title></svelte:head>

<StaffLayout {breadcrumbs}>
  <div class="min-w-0 space-y-6 p-4 sm:p-6">
    <PageHeaderBar
      title="Exam Monitoring"
      description="Conduct phase: roster, attendance, and submission for published or in-progress sessions. Scheduling lives under Exam Scheduling."
    />

    {#if page_state === 'denied'}
      <p class="text-sm text-muted-foreground">You do not have permission to view exam monitoring.</p>
    {:else if page_state === 'empty'}
      <div class="rounded-xl border border-border bg-card p-6">
        <p class="text-sm text-muted-foreground">
          {#if can_manage}
            No sessions are currently in the conduct phase. Publish a session under Exam Scheduling to begin monitoring it.
          {:else}
            You have no active proctor duties on sessions in the conduct phase.
          {/if}
        </p>
      </div>
    {:else}
      <SwitchableListView viewMode="responsive" overflow="auto">
        {#snippet table()}
          <Table.Root class="w-full min-w-[720px] text-sm">
            <Table.Header class="bg-muted/50">
              <Table.Row>
                <Table.Head>When</Table.Head>
                <Table.Head>Room</Table.Head>
                <Table.Head>Code</Table.Head>
                <Table.Head>Status</Table.Head>
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
                    router.visit(`/exams/sessions/${session.id}/monitoring`);
                  }}
                >
                  <Table.Cell>{formatWhen(session)}</Table.Cell>
                  <Table.Cell>{session.room?.name ?? session.room?.code ?? '—'}</Table.Cell>
                  <Table.Cell class="font-medium tracking-tight">{session.code}</Table.Cell>
                  <Table.Cell>
                    <Badge variant={statusVariant(session.status)}>{statusLabel(session.status)}</Badge>
                  </Table.Cell>
                  <Table.Cell>{session.assigned_count ?? 0}{#if session.room?.capacity} / {session.room.capacity}{/if}</Table.Cell>
                  <Table.Cell class="text-center" data-action-cell>
                    <Link href={`/exams/sessions/${session.id}/monitoring`}>
                      <Button variant="ghost" size="sm" class="h-8 px-2 text-xs font-semibold">
                        <Activity class="mr-1.5 size-3.5" />
                        Monitor
                      </Button>
                    </Link>
                  </Table.Cell>
                </Table.Row>
              {:else}
                <Table.Row>
                  <Table.Cell colspan={6} class="py-12 text-center text-muted-foreground">
                    No sessions in the conduct phase right now.
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
                      <p class="text-sm text-muted-foreground">{session.room?.name ?? session.room?.code ?? '—'}</p>
                    </div>
                    <Badge variant={statusVariant(session.status)}>{statusLabel(session.status)}</Badge>
                  </div>
                  <p class="text-sm text-muted-foreground">
                    Assigned {session.assigned_count ?? 0}{#if session.room?.capacity} / {session.room.capacity}{/if}
                  </p>
                  <div class="mt-auto flex gap-2 pt-2">
                    <Link href={`/exams/sessions/${session.id}/monitoring`} class="flex-1">
                      <Button variant="outline" size="sm" class="min-h-10 w-full font-semibold">
                        <Eye class="mr-1.5 size-3.5" />
                        Monitor
                      </Button>
                    </Link>
                  </div>
                </li>
              {/each}
            </ul>
          {:else}
            <p class="py-12 text-center text-muted-foreground">No sessions in the conduct phase right now.</p>
          {/if}
        {/snippet}
      </SwitchableListView>
    {/if}
  </div>
</StaffLayout>
