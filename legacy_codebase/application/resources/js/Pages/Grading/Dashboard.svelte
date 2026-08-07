<script>
  import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
  import { Link } from '@inertiajs/svelte';
  import { Button } from '@/Components/ui/button';
  import { Badge } from '@/Components/ui/badge';
  import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
  import * as Table from '@/Components/ui/table';
  import { GraduationCap, Layers, UploadCloud } from 'lucide-svelte';
  import SwitchableListView from '@/Components/SwitchableListView.svelte';
  import { formatDate } from '@/lib/date-utils';

  let viewMode = $state('responsive');

  let {
    title = 'Grading',
    description = 'Input and manage exam scores.',
    grading_sessions = [],
    aptitude_areas_count = 0,
  } = $props();



  function formatTime(value) {
    if (!value) return '—';
    const parts = String(value).split(':');
    const h = parseInt(parts[0], 10) || 0;
    const m = parseInt(parts[1], 10) || 0;
    return `${h % 12 || 12}:${String(m).padStart(2, '0')} ${h < 12 ? 'AM' : 'PM'}`;
  }

  const breadcrumbs = $derived([{ label: 'Grading' }]);
</script>

<AuthenticatedLayout breadcrumbs={breadcrumbs}>
  <div class="space-y-6 min-w-0">
    {#if aptitude_areas_count === 0}
      <Card variant="glass" class="border-amber-500/50 bg-amber-500/5">
        <CardHeader>
          <CardTitle class="flex items-center gap-2 text-amber-700 dark:text-amber-400">
            <Layers class="h-5 w-5" />
            No aptitude areas configured
          </CardTitle>
          <CardDescription>
            Aptitude areas must be set up before you can open grading sessions. Add them via
            <Link href="/admin/setup" class="font-medium text-primary underline hover:no-underline">Setup &rarr; Aptitude Areas</Link>.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <Link href="/admin/setup">
            <Button class="min-h-[44px]">
              <Layers class="mr-2 h-4 w-4" />
              Go to Setup
            </Button>
          </Link>
        </CardContent>
      </Card>
    {:else}
      <div class="flex items-center justify-between">
        <div>
          <p class="text-sm text-muted-foreground">Open sessions where scores are being or have been entered</p>
        </div>
        <div class="flex flex-wrap items-center gap-2 sm:gap-3">
          <Link href="/admin/grading/import">
            <Button variant="outline" class="min-h-[44px] gap-2">
              <UploadCloud class="h-4 w-4" />
              <span class="hidden sm:inline">Import Scores</span>
            </Button>
          </Link>

        </div>
      </div>

      <SwitchableListView bind:viewMode overflow="auto">
        {#snippet table()}
          <Table.Root class="w-full min-w-[520px] text-sm">
            <Table.Header class="bg-muted/50">
              <Table.Row>
                <Table.Head class="px-4 py-3">Session</Table.Head>
                <Table.Head class="px-4 py-3">Date</Table.Head>
                <Table.Head class="px-4 py-3">Progress</Table.Head>
                <Table.Head class="text-center px-4 py-3">Action</Table.Head>
              </Table.Row>
            </Table.Header>
            <Table.Body>
              {#each grading_sessions as gs (gs.id)}
                <Table.Row class="border-t border-border hover:bg-muted/30">
                  <Table.Cell class="px-4 py-3 font-medium">
                    #{gs.exam_session_id} &mdash; {gs.room_name ?? '—'}
                    {#if gs.exam_session_type === 'direct'}
                      <span class="inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800 ml-1">Direct</span>
                    {/if}
                  </Table.Cell>
                  <Table.Cell class="px-4 py-3 text-muted-foreground">
                    {formatDate(gs.exam_date)} {formatTime(gs.exam_time)}
                  </Table.Cell>
                  <Table.Cell class="px-4 py-3">
                    <Badge variant={gs.status === 'finalized' ? 'success' : 'warning'}>
                      {gs.applicants_scored ?? 0} of {gs.applicants_total ?? 0} scored
                    </Badge>
                  </Table.Cell>
                  <Table.Cell class="text-center">
                    <Link href={`/admin/grading/sessions/${gs.id}`}>
                      <Button variant="outline" size="sm" class="h-8 px-2 text-xs">
                        {gs.status === 'finalized' ? 'View' : 'Input scores'}
                      </Button>
                    </Link>
                  </Table.Cell>
                </Table.Row>
              {:else}
                <Table.Row>
                  <Table.Cell colspan="4" class="px-4 py-12 text-center text-muted-foreground">
                    No grading sessions yet.
                  </Table.Cell>
                </Table.Row>
              {/each}
            </Table.Body>
          </Table.Root>
        {/snippet}

        {#snippet cards()}
          <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            {#each grading_sessions as gs (gs.id)}
              <div class="rounded-xl border bg-card text-card-foreground shadow-sm hover:shadow-md transition-all">
                <div class="p-5 space-y-3">
                  <div class="flex items-start justify-between gap-2">
                    <div>
                      <p class="font-semibold leading-none mb-1">#{gs.exam_session_id} &mdash; {gs.room_name ?? '—'}</p>
                      <p class="text-sm text-muted-foreground">{formatDate(gs.exam_date)} &bull; {formatTime(gs.exam_time)}</p>
                    </div>
                    {#if gs.exam_session_type === 'direct'}
                      <Badge variant="outline" class="bg-blue-50 text-blue-700 border-blue-200">Direct</Badge>
                    {/if}
                  </div>

                  <div class="flex items-center justify-between pt-2 border-t border-border">
                    <Badge variant={gs.status === 'finalized' ? 'success' : 'warning'}>
                      {gs.applicants_scored ?? 0} of {gs.applicants_total ?? 0} scored
                    </Badge>

                    <Link href={`/admin/grading/sessions/${gs.id}`}>
                      <Button variant="outline" size="sm" class="h-8">
                        {gs.status === 'finalized' ? 'View' : 'Input scores'}
                      </Button>
                    </Link>
                  </div>
                </div>
              </div>
            {:else}
              <div class="col-span-full rounded-xl border border-dashed border-border bg-muted/20 py-12 text-center text-muted-foreground">
                No grading sessions yet.
              </div>
            {/each}
          </div>
        {/snippet}
      </SwitchableListView>
    {/if}
  </div>
</AuthenticatedLayout>