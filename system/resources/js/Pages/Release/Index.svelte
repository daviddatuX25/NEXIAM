<script>
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
  import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
  import E2PageState from '@/Pages/Admission/E2PageState.svelte';
  import { Badge } from '@/Components/ui/badge';
  import { Button } from '@/Components/ui/button';
  import * as Card from '@/Components/ui/card';
  import * as Table from '@/Components/ui/table';
  import { Link } from '@inertiajs/svelte';
  import { Printer, MessagesSquare, FileText, CheckCircle2, Lock, ArrowRight } from 'lucide-svelte';

  let {
    page_state = 'ready',
    sessions = [],
    active_template = null,
    can_manage_templates = false,
  } = $props();

  const breadcrumbs = [{ label: 'Release' }];
</script>

<svelte:head><title>Results Release &amp; Printing · NEXIAM</title></svelte:head>

<StaffLayout {breadcrumbs}>
  <div class="min-w-0 space-y-6 p-4 sm:p-6">
    <PageHeaderBar
      title="Results release &amp; printing"
      description="Batch print official result sheets, monitor release progress, and configure result sheet templates."
    >
      {#snippet actions()}
        <div class="flex flex-wrap items-center gap-2">
          {#if can_manage_templates}
            <Link href="/setup/result-templates">
              <Button variant="outline" class="min-h-10 gap-2 font-medium">
                <Printer class="size-4 text-muted-foreground" />
                Result templates
              </Button>
            </Link>
          {/if}
          <Link href="/consultation">
            <Button variant="outline" class="min-h-10 gap-2 font-medium">
              <MessagesSquare class="size-4 text-muted-foreground" />
              Consultation dashboard
            </Button>
          </Link>
        </div>
      {/snippet}
    </PageHeaderBar>

    <E2PageState state={page_state} />

    {#if page_state === 'ready'}
      <!-- Active Template Status Card -->
      <Card.Root class="border-border/70 bg-card">
        <Card.Content class="flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:justify-between">
          <div class="flex items-start gap-3">
            <div class="rounded-lg bg-primary/10 p-2.5 text-primary">
              <FileText class="size-5" />
            </div>
            <div>
              <div class="flex items-center gap-2">
                <h2 class="text-sm font-semibold text-foreground">Active Print Template</h2>
                {#if active_template}
                  <Badge variant="default" class="text-[11px]">Active</Badge>
                {:else}
                  <Badge variant="destructive" class="text-[11px]">No template active</Badge>
                {/if}
              </div>
              <p class="mt-0.5 text-xs text-muted-foreground">
                {#if active_template}
                  <span class="font-medium text-foreground">{active_template.name}</span>
                  ({active_template.mode.toUpperCase()} · {active_template.paper_size.toUpperCase()} {active_template.orientation})
                {:else}
                  Result printing requires an active template. Create or activate one in Result Templates setup.
                {/if}
              </p>
            </div>
          </div>
          {#if can_manage_templates}
            <Link href="/setup/result-templates" class="shrink-0">
              <Button variant="outline" size="sm" class="gap-1.5 text-xs">
                Configure templates
                <ArrowRight class="size-3" />
              </Button>
            </Link>
          {/if}
        </Card.Content>
      </Card.Root>

      <!-- Grading Sessions Table for Batch Printing -->
      <div class="space-y-3">
        <div class="flex items-center justify-between">
          <div>
            <h2 class="text-base font-semibold text-foreground">Examination sessions</h2>
            <p class="text-xs text-muted-foreground">
              Select an examination session to open its dedicated batch printing workspace.
            </p>
          </div>
        </div>

        <div class="rounded-xl border border-border bg-card">
          <Table.Root>
            <Table.Header>
              <Table.Row>
                <Table.Head>Session</Table.Head>
                <Table.Head>Room</Table.Head>
                <Table.Head>Date / Time</Table.Head>
                <Table.Head>Status</Table.Head>
                <Table.Head class="text-center">Examinees</Table.Head>
                <Table.Head class="text-center">Released</Table.Head>
                <Table.Head class="text-right">Action</Table.Head>
              </Table.Row>
            </Table.Header>
            <Table.Body>
              {#if sessions.length === 0}
                <Table.Row>
                  <Table.Cell colspan={7} class="py-8 text-center text-sm text-muted-foreground">
                    No examination grading sessions recorded. Complete an examination session and score examinees first.
                  </Table.Cell>
                </Table.Row>
              {:else}
                {#each sessions as session (session.id)}
                  <Table.Row>
                    <Table.Cell class="font-medium">
                      <span class="font-mono text-sm">{session.exam_code}</span>
                    </Table.Cell>
                    <Table.Cell class="text-sm text-muted-foreground">{session.room_name}</Table.Cell>
                    <Table.Cell class="text-sm text-muted-foreground">{session.starts_at}</Table.Cell>
                    <Table.Cell>
                      {#if session.status === 'finalized'}
                        <Badge variant="default" class="gap-1 text-xs">
                          <CheckCircle2 class="size-3" /> Finalized
                        </Badge>
                      {:else}
                        <Badge variant="secondary" class="gap-1 text-xs">
                          <Lock class="size-3" /> In Progress
                        </Badge>
                      {/if}
                    </Table.Cell>
                    <Table.Cell class="text-center font-medium">{session.examinees_count}</Table.Cell>
                    <Table.Cell class="text-center">
                      <span class="text-xs font-semibold text-primary">{session.released_count}</span>
                      <span class="text-xs text-muted-foreground"> / {session.examinees_count}</span>
                    </Table.Cell>
                    <Table.Cell class="text-right">
                      <Link href={`/release/print/batches/${session.id}`}>
                        <Button size="sm" class="h-8 gap-1.5 px-3 text-xs font-medium">
                          <Printer class="size-3.5" />
                          Batch Print
                        </Button>
                      </Link>
                    </Table.Cell>
                  </Table.Row>
                {/each}
              {/if}
            </Table.Body>
          </Table.Root>
        </div>
      </div>
    {/if}
  </div>
</StaffLayout>
