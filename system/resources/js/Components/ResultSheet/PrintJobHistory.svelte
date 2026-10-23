<script>
  import * as Table from '@/Components/ui/table';
  import { Badge } from '@/Components/ui/badge';
  import { Button } from '@/Components/ui/button';
  import { Download, History } from 'lucide-svelte';

  let { history = [], class: className = '' } = $props();
</script>

{#if history.length > 0}
  <div class="rounded-xl border border-border bg-card p-4 shadow-xs {className}">
    <div class="mb-3 flex items-center gap-2 border-b border-border/40 pb-2">
      <History class="size-4 text-primary" />
      <h3 class="text-xs font-semibold uppercase tracking-wider text-foreground">Session Print History</h3>
    </div>

    <div class="overflow-x-auto">
      <Table.Root class="w-full text-xs">
        <Table.Header class="bg-muted/40">
          <Table.Row>
            <Table.Head class="py-2">Job</Table.Head>
            <Table.Head class="py-2">Status</Table.Head>
            <Table.Head class="py-2">Items</Table.Head>
            <Table.Head class="py-2">Requested By</Table.Head>
            <Table.Head class="py-2">Time</Table.Head>
            <Table.Head class="py-2 text-right">Action</Table.Head>
          </Table.Row>
        </Table.Header>
        <Table.Body>
          {#each history as h (h.id)}
            <Table.Row>
              <Table.Cell class="font-mono font-medium">#{h.id}</Table.Cell>
              <Table.Cell>
                <Badge variant={h.status === 'ready' ? 'default' : h.status === 'failed' ? 'destructive' : 'secondary'} class="text-[10px] uppercase">
                  {h.status}
                </Badge>
              </Table.Cell>
              <Table.Cell>
                {h.ready_items} / {h.progress_total} ready
                {#if h.failed_items > 0}
                  <span class="ml-1 text-destructive font-medium">({h.failed_items} failed)</span>
                {/if}
              </Table.Cell>
              <Table.Cell class="text-muted-foreground">{h.requested_by}</Table.Cell>
              <Table.Cell class="text-muted-foreground">{h.requested_at}</Table.Cell>
              <Table.Cell class="text-right">
                {#each (h.outputs ?? (h.download_url ? ['pdf'] : [])) as output}
                  <a href={`${h.download_url}?format=${output}`} target="_blank" rel="noopener">
                    <Button variant="ghost" size="sm" class="h-7 gap-1 px-2 text-[11px]">
                      <Download class="size-3" />
                      {output === 'pdf' ? 'PDF' : output === 'docx' ? 'Word' : 'ZIP'}
                    </Button>
                  </a>
                {/each}
              </Table.Cell>
            </Table.Row>
          {/each}
        </Table.Body>
      </Table.Root>
    </div>
  </div>
{/if}
