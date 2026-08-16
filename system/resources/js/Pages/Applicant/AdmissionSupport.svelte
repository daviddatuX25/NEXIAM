<script>
  /* global route */
  /**
   * Applicant admission support — own-scope portal page.
   * Lists staff↔applicant support threads (read-only for closed threads) and
   * the audit trail of who has viewed this applicant's admission records.
   * Privacy-safe rows only; the server decides what is exposed.
   */
  import ApplicantLayout from '@/Pages/Applicant/ApplicantLayout.svelte';
  import E2PageState from '@/Pages/Admission/E2PageState.svelte';
  import { Badge } from '@/Components/ui/badge';
  import * as Card from '@/Components/ui/card';
  import * as Table from '@/Components/ui/table';
  import { History, MessageSquareText, ShieldCheck } from 'lucide-svelte';

  let { page_state = 'ready', threads = [], access_history = [] } = $props();

  const eventLabels = {
    program_head_record_viewed: 'Program Head viewed assigned-program record',
    'registrar.enrollment-eligibility.viewed': 'Registrar opened enrollment eligibility',
    applicant_qr_resolved: 'Staff resolved applicant QR',
  };

  function eventLabel(type) {
    return eventLabels[type] ?? type;
  }

  function formatDateTime(iso) {
    if (!iso) return '—';
    try {
      return new Date(iso).toLocaleString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
      });
    } catch {
      return iso;
    }
  }

  function threadStatusVariant(status) {
    return status === 'open' ? 'default' : 'secondary';
  }
</script>

<svelte:head><title>Admission support</title></svelte:head>

<ApplicantLayout title="Admission support">
  <div class="mx-auto max-w-3xl space-y-6">
    {#if page_state !== 'ready'}
      <div class="sr-only"><E2PageState state={page_state} /></div>
      <Card.Root>
        <Card.Content class="space-y-3 p-6 text-center">
          <ShieldCheck class="mx-auto size-8 text-muted-foreground" />
          <p class="font-medium">Admission support unavailable</p>
          <p class="text-sm text-muted-foreground">
            This account cannot access admission support.
          </p>
        </Card.Content>
      </Card.Root>
    {:else}
      <div class="space-y-1">
        <h1 class="text-2xl font-semibold tracking-tight">Admission support</h1>
        <p class="text-sm text-muted-foreground">
          Messages with staff, and who has viewed your admission records.
        </p>
      </div>

      <section class="space-y-3">
        <div class="flex items-center gap-2">
          <MessageSquareText class="size-4 text-muted-foreground" />
          <h2 class="text-lg font-semibold tracking-tight">Support threads</h2>
        </div>

        {#if threads.length === 0}
          <Card.Root>
            <Card.Content class="space-y-2 p-6 text-center">
              <MessageSquareText class="mx-auto size-8 text-muted-foreground" />
              <p class="font-medium">No support threads yet</p>
              <p class="text-sm text-muted-foreground">
                When staff message you about your admission, the conversation appears here.
              </p>
            </Card.Content>
          </Card.Root>
        {:else}
          {#each threads as thread (thread.id)}
            <Card.Root class="overflow-hidden shadow-sm">
              <Card.Header class="border-b bg-muted/20 pb-3">
                <div class="flex items-start justify-between gap-3">
                  <div class="min-w-0 space-y-1">
                    <Card.Title class="text-base">{thread.subject}</Card.Title>
                    {#if thread.status !== 'open'}
                      <p class="text-xs text-muted-foreground">
                        Read-only — this thread is no longer open for replies.
                      </p>
                    {/if}
                  </div>
                  <Badge variant={threadStatusVariant(thread.status)} class="shrink-0">
                    {thread.status}
                  </Badge>
                </div>
              </Card.Header>
              <Card.Content class="space-y-4 pt-4">
                {#each thread.messages as message (message.id)}
                  <div class="space-y-1">
                    <div class="flex items-baseline justify-between gap-3">
                      <p class="text-sm font-medium">{message.sender ?? 'Staff'}</p>
                      <p class="text-xs text-muted-foreground">{formatDateTime(message.created_at)}</p>
                    </div>
                    <p class="whitespace-pre-line text-sm text-muted-foreground">{message.body}</p>
                  </div>
                {/each}
              </Card.Content>
            </Card.Root>
          {/each}
        {/if}
      </section>

      <section class="space-y-3">
        <div class="flex items-center gap-2">
          <History class="size-4 text-muted-foreground" />
          <h2 class="text-lg font-semibold tracking-tight">Access history</h2>
        </div>

        {#if access_history.length === 0}
          <Card.Root>
            <Card.Content class="space-y-2 p-6 text-center">
              <ShieldCheck class="mx-auto size-8 text-muted-foreground" />
              <p class="font-medium">No access recorded yet</p>
              <p class="text-sm text-muted-foreground">
                Each time staff open your admission records, the visit is listed here.
              </p>
            </Card.Content>
          </Card.Root>
        {:else}
          <Card.Root class="overflow-hidden shadow-sm">
            <Card.Content class="p-0">
              <Table.Root>
              <Table.Header>
                <Table.Row>
                  <Table.Head>Who</Table.Head>
                  <Table.Head>Action</Table.Head>
                  <Table.Head class="text-right">When</Table.Head>
                </Table.Row>
              </Table.Header>
              <Table.Body>
                {#each access_history as event (`${event.occurred_at}-${event.event_type}`)}
                  <Table.Row>
                    <Table.Cell class="font-medium">{event.actor ?? 'Staff'}</Table.Cell>
                    <Table.Cell class="text-muted-foreground">{eventLabel(event.event_type)}</Table.Cell>
                    <Table.Cell class="text-right text-muted-foreground">
                      {formatDateTime(event.occurred_at)}
                    </Table.Cell>
                  </Table.Row>
                {/each}
              </Table.Body>
              </Table.Root>
            </Card.Content>
          </Card.Root>
        {/if}
      </section>
    {/if}
  </div>
</ApplicantLayout>
