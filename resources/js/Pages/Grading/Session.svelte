<script>
  import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
  import { Link, router, usePage } from '@inertiajs/svelte';
  import { Button } from '@/Components/ui/button';
  import { Badge } from '@/Components/ui/badge';
  import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
  import * as Table from '@/Components/ui/table';
  import { UserCheck, CheckCircle2, Circle, Printer, RotateCcw, Send, Trash2 } from 'lucide-svelte';
  import { formatDate } from '@/lib/date-utils';

  let { sessionId = '1', session = {}, applicants = [], workflowStatus = 'in_progress' } = $props();

  const sid = $derived(String(sessionId));
  const _page = usePage();
  const releaseMode = $derived($_page?.props?.release_mode ?? 'online');
  const showPrint = $derived(workflowStatus === 'completed' && releaseMode !== 'online');
  const showRelease = $derived(workflowStatus === 'completed' && releaseMode !== 'f2f');

  const breadcrumbs = $derived([
    { label: 'Grading', href: '/admin/grading' },
    { label: session?.exam_session_id ? 'Session #' + session.exam_session_id : 'Session' }
  ]);

  const progress_stats = $derived({
    total: applicants.length,
    scored: applicants.filter((a) => a.scored).length,
    pending: applicants.filter((a) => !a.scored).length,
  });



  function formatTime(value) {
    if (!value) return '—';
    const parts = String(value).split(':');
    const h = parseInt(parts[0], 10) || 0;
    const m = parseInt(parts[1], 10) || 0;
    return `${h % 12 || 12}:${String(m).padStart(2, '0')} ${h < 12 ? 'AM' : 'PM'}`;
  }

  function toggleStatus() {
    const next = workflowStatus === 'in_progress' ? 'finalized' : 'in_progress';
    router.put(`/admin/grading/sessions/${sid}/workflow`, { status: next });
  }


</script>

<AuthenticatedLayout breadcrumbs={breadcrumbs}>
  <div class="space-y-6 min-w-0">

    <!-- Session header -->
    <Card>
      <CardHeader>
        <div class="flex items-center gap-2">
          <CardTitle>Session #{session.exam_session_id} — {session.room_name}</CardTitle>
          {#if session.exam_session_type === 'direct'}
            <span class="inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800">Direct</span>
          {/if}
        </div>
        <CardDescription>
          {formatDate(session.exam_date)} at {formatTime(session.exam_time)} · Opened {formatDate(session.opened_at)}
        </CardDescription>
      </CardHeader>
      <CardContent class="flex flex-wrap gap-4">
        <div class="flex items-center gap-2 rounded-lg bg-muted/50 px-3 py-2">
          <UserCheck class="h-4 w-4 text-muted-foreground" />
          <span class="text-sm font-medium">{progress_stats.scored} of {progress_stats.total} scored</span>
        </div>
        <Button variant="outline" onclick={toggleStatus} class="min-h-[44px]">
          <RotateCcw class="h-4 w-4 mr-2" />
          Mark as {workflowStatus === 'in_progress' ? 'Completed' : 'In progress'}
        </Button>

        {#if showPrint}
          <Link href={`/admin/release/print/${sid}`}>
            <Button class="min-h-[44px]">
              <Printer class="h-4 w-4 mr-2" />
              Print results
            </Button>
          </Link>
        {/if}
        {#if showRelease}
          <Link href="/admin/release">
            <Button variant="default" class="min-h-[44px]">
              <Send class="h-4 w-4 mr-2" />
              Go to Release
            </Button>
          </Link>
        {/if}
      </CardContent>
    </Card>

    <!-- Applicants list -->
    <Card>
      <CardHeader>
        <CardTitle>Applicants</CardTitle>
        <CardDescription>Click an applicant to input or edit aptitude area scores.</CardDescription>
      </CardHeader>
      <CardContent>
        <div class="rounded-lg border border-border overflow-hidden min-w-0">
          <Table.Root class="w-full min-w-[560px]">
            <Table.Header class="bg-muted/50">
              <Table.Row>
                <Table.Head class="px-4 py-3">Reference</Table.Head>
                <Table.Head class="px-4 py-3">Name</Table.Head>
                <Table.Head class="px-4 py-3">Progress</Table.Head>
                <Table.Head class="text-center">Action</Table.Head>
              </Table.Row>
            </Table.Header>
            <Table.Body>
              {#each applicants as app (app.applicant_id)}
                <Table.Row>
                  <Table.Cell class="px-4 py-3">{app.reference}</Table.Cell>
                  <Table.Cell class="px-4 py-3">{app.name}</Table.Cell>
                  <Table.Cell class="px-4 py-3">
                    <div class="flex flex-wrap items-center gap-1.5">
                      {#if app.scored}
                        <Badge variant="success" class="gap-1">
                          <CheckCircle2 class="h-3 w-3" />
                          Complete
                        </Badge>
                      {:else if app.domains_complete > 0}
                        <Badge variant="warning">{app.domains_complete} / 6 aptitude areas</Badge>
                      {:else}
                        <Badge variant="muted" class="gap-1">
                          <Circle class="h-3 w-3" />
                          Not started
                        </Badge>
                      {/if}
                      {#if app.scored && app.printed}
                        <Badge variant="success" class="gap-1 text-xs">
                          <Printer class="h-3 w-3" />
                          Printed
                        </Badge>
                      {:else if app.scored && !app.printed && releaseMode !== 'online'}
                        <Badge variant="outline" class="gap-1 text-xs text-muted-foreground">
                          <Printer class="h-3 w-3" />
                          Not printed
                        </Badge>
                      {/if}
                    </div>
                  </Table.Cell>
                  <Table.Cell class="text-center">
                    <Link href={`/admin/grading/sessions/${sid}/applicants/${app.applicant_id}`}>
                      <Button variant="outline" size="sm" class="h-8 px-2 text-xs">
                        {#if workflowStatus === 'completed'}
                          View scores
                        {:else}
                          {app.scored ? 'Edit scores' : 'Input scores'}
                        {/if}
                      </Button>
                    </Link>
                  </Table.Cell>
                </Table.Row>
              {/each}
            </Table.Body>
          </Table.Root>
        </div>
      </CardContent>
    </Card>
  </div>
</AuthenticatedLayout>
