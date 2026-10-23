<script>
  import * as Card from '@/Components/ui/card';
  import { Badge } from '@/Components/ui/badge';
  import { Button } from '@/Components/ui/button';
  import { CheckCircle2, RotateCw, AlertCircle, Download, CheckSquare } from 'lucide-svelte';

  let {
    job = null,
    isRequesting = false,
    onretry = () => {},
    onmarkprinted = () => {},
    ondownload = () => {},
    class: className = '',
  } = $props();

  function download(format = 'pdf') {
    window.open(`/printing/jobs/${job.id}/download?format=${format}`, '_blank', 'noopener');
    ondownload(format);
  }
</script>

{#if job}
  <Card.Root class="overflow-hidden border-border/80 bg-card shadow-xs {className}">
    <Card.Content class="p-5">
      {#if job.status === 'ready'}
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div class="flex items-start gap-3.5">
            <div class="rounded-full bg-green-500/10 p-2 text-green-600 dark:text-green-400">
              <CheckCircle2 class="size-6" />
            </div>
            <div>
              <div class="flex items-center gap-2">
                <h3 class="font-semibold text-foreground">Print Job #{job.id} is Ready</h3>
                <Badge variant="default" class="bg-green-600 text-[11px] text-white hover:bg-green-600">Ready</Badge>
              </div>
              <p class="mt-0.5 text-sm text-muted-foreground">
                All {job.progress_total ?? 1} result sheet(s) have been rendered into a merged PDF.
              </p>
            </div>
          </div>

          <div class="flex flex-wrap items-center gap-2 pt-2 sm:pt-0">
            {#each (job.outputs ?? ['pdf']) as output}
              <Button class="min-h-10 gap-2 font-semibold" onclick={() => download(output)}>
                <Download class="size-4" />
                {output === 'pdf' ? 'Download PDF' : output === 'docx' ? 'Download Word' : 'Download Word ZIP'}
              </Button>
            {/each}
            <Button variant="outline" class="min-h-10 gap-2" onclick={onmarkprinted}>
              <CheckSquare class="size-4 text-muted-foreground" />
              Mark as printed
            </Button>
          </div>
        </div>
        {#if job.items?.length}
          <div class="mt-3 grid gap-1.5 border-t border-border/40 pt-3 sm:grid-cols-2">
            {#each job.items as item (item.id)}
              <div class="flex items-center justify-between rounded-md bg-muted/30 px-2.5 py-1.5 text-[11px]">
                <span class="truncate text-muted-foreground">{item.name ?? `Item ${item.id}`}</span>
                <div class="flex items-center gap-1">
                  <Badge variant={item.status === 'ready' ? 'default' : 'destructive'} class="text-[9px] uppercase">{item.status}</Badge>
                  {#if item.status === 'ready'}
                    <a href={`/printing/jobs/${job.id}/items/${item.id}/download`} target="_blank" rel="noopener" class="text-primary hover:underline">PDF</a>
                    {#if job.outputs?.includes('docx')}
                      <a href={`/printing/jobs/${job.id}/items/${item.id}/download?format=docx`} target="_blank" rel="noopener" class="text-primary hover:underline">DOCX</a>
                    {/if}
                  {/if}
                </div>
              </div>
            {/each}
          </div>
        {/if}
      {:else if job.status === 'queued' || job.status === 'rendering'}
        <div class="space-y-3">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2.5">
              <RotateCw class="size-4 animate-spin text-primary" />
              <h3 class="text-sm font-semibold text-foreground">
                Generating Print Job #{job.id}…
              </h3>
              <Badge variant="secondary" class="text-[11px] capitalize">
                {job.status}
              </Badge>
            </div>
            <span class="font-mono text-xs text-muted-foreground">
              {job.progress_done ?? 0} / {job.progress_total ?? 1} sheets
            </span>
          </div>
          <div class="h-2 w-full overflow-hidden rounded-full bg-muted">
            <div
              class="h-full bg-primary transition-all duration-300"
              style={`width: ${Math.max(8, Math.round(((job.progress_done ?? 0) / (job.progress_total || 1)) * 100))}%`}
            ></div>
          </div>
          <p class="text-xs text-muted-foreground">
            Rendering result sheets in isolated worker jobs. This progress updates automatically.
          </p>
          {#if job.items?.length}
            <div class="mt-3 grid gap-1.5 border-t border-border/40 pt-3 sm:grid-cols-2">
              {#each job.items as item (item.id)}
                <div class="flex items-center justify-between rounded-md bg-muted/30 px-2.5 py-1.5 text-[11px]">
                  <span class="truncate text-muted-foreground">{item.name ?? `Item ${item.id}`}</span>
                  <Badge variant={item.status === 'ready' ? 'default' : item.status === 'failed' ? 'destructive' : 'secondary'} class="ml-2 text-[9px] uppercase">
                    {item.status}
                  </Badge>
                </div>
              {/each}
            </div>
          {/if}
        </div>
      {:else if job.status === 'failed'}
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div class="flex items-start gap-3">
            <div class="rounded-full bg-destructive/10 p-2 text-destructive">
              <AlertCircle class="size-5" />
            </div>
            <div>
              <h3 class="text-sm font-semibold text-destructive">Print Job #{job.id} Failed</h3>
              <p class="text-xs text-muted-foreground">{job.safe_error ?? 'PDF generation could not complete'}</p>
            </div>
          </div>
          <Button size="sm" variant="outline" class="gap-1.5" onclick={onretry}>
            <RotateCw class="size-3.5" />
            Retry Print
          </Button>
        </div>
      {/if}
    </Card.Content>
  </Card.Root>
{/if}
