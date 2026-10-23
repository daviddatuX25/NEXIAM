<script>
  import { onMount, onDestroy } from 'svelte';
  import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
  } from '@/Components/ui/dialog';
  import { Button } from '@/Components/ui/button';
  import { Badge } from '@/Components/ui/badge';
  import {
    Printer,
    Download,
    Trash2,
    CheckCircle2,
    Clock,
    FileText,
    Layers,
    RotateCcw,
    HardDrive,
  } from 'lucide-svelte';
  import {
    getAllPrintJobs,
    deletePrintJob,
    clearAllPrintJobs,
    markJobPrinted,
  } from '@/lib/printQueue.js';

  let { open = $bindable(false) } = $props();

  let jobs = $state([]);
  let loading = $state(true);
  let printFrame = $state(null);

  async function refreshJobs() {
    loading = true;
    try {
      jobs = await getAllPrintJobs();
    } catch {
      jobs = [];
    } finally {
      loading = false;
    }
  }

  function handlePrint(job) {
    if (!job.blob) return;
    const url = URL.createObjectURL(job.blob);
    if (printFrame) {
      printFrame.src = url;
      printFrame.onload = () => {
        try {
          printFrame.contentWindow.focus();
          printFrame.contentWindow.print();
          markJobPrinted(job.id);
        } catch (e) {
          console.warn('Print iframe failed:', e);
        }
      };
    } else {
      const w = window.open(url, '_blank');
      if (w) {
        w.focus();
        markJobPrinted(job.id);
      }
    }
  }

  function handleDownload(job) {
    if (!job.blob) return;
    const url = URL.createObjectURL(job.blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = job.filename || 'document.pdf';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    setTimeout(() => URL.revokeObjectURL(url), 1000);
  }

  async function handleDelete(id) {
    await deletePrintJob(id);
    await refreshJobs();
  }

  async function handleClearAll() {
    await clearAllPrintJobs();
    await refreshJobs();
  }

  function formatBytes(bytes) {
    if (!bytes) return '0 KB';
    const kb = bytes / 1024;
    if (kb < 1000) return `${Math.round(kb)} KB`;
    return `${(kb / 1024).toFixed(1)} MB`;
  }

  function formatDate(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) + ' · ' + d.toLocaleDateString();
  }

  onMount(() => {
    refreshJobs();
    if (typeof window !== 'undefined') {
      window.addEventListener('nexiam-print-queue-updated', refreshJobs);
    }
  });

  onDestroy(() => {
    if (typeof window !== 'undefined') {
      window.removeEventListener('nexiam-print-queue-updated', refreshJobs);
    }
  });

  $effect(() => {
    if (open) {
      refreshJobs();
    }
  });
</script>

<Dialog bind:open>
  <DialogContent class="max-w-3xl max-h-[85vh] flex flex-col p-6 gap-4">
    <DialogHeader class="border-b border-border/60 pb-3">
      <div class="flex items-center justify-between gap-3">
        <div class="space-y-1">
          <DialogTitle class="flex items-center gap-2 text-lg font-bold text-foreground">
            <HardDrive class="size-5 text-primary" />
            Local Print Queue &amp; Document Cache
          </DialogTitle>
          <DialogDescription class="text-xs text-muted-foreground">
            Fast client-side cache powered by browser IndexedDB. Preserves generated PDFs across modals without consuming server storage.
          </DialogDescription>
        </div>

        {#if jobs.length > 0}
          <Button
            variant="ghost"
            size="sm"
            class="text-xs text-muted-foreground hover:text-destructive gap-1.5 h-8 px-2.5"
            onclick={handleClearAll}
          >
            <Trash2 class="size-3.5" /> Clear Queue
          </Button>
        {/if}
      </div>
    </DialogHeader>

    <div class="flex-1 overflow-y-auto pr-1 space-y-3 min-h-[220px]">
      {#if loading}
        <div class="py-12 text-center text-xs text-muted-foreground">
          Loading cached print documents…
        </div>
      {:else if jobs.length === 0}
        <div class="rounded-xl border border-dashed border-border p-10 text-center space-y-3">
          <div class="size-10 rounded-full bg-muted/60 flex items-center justify-center mx-auto text-muted-foreground">
            <Layers class="size-5" />
          </div>
          <div>
            <h4 class="text-sm font-semibold text-foreground">Print Queue is Empty</h4>
            <p class="text-xs text-muted-foreground max-w-sm mx-auto mt-1">
              Documents generated across the Reports Hub or admissions portals will automatically cache here for instant re-printing without server re-computation.
            </p>
          </div>
        </div>
      {:else}
        {#each jobs as job (job.id)}
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3.5 rounded-xl border border-border/70 bg-card hover:border-primary/30 transition-all text-xs">
            <div class="flex items-start gap-3 min-w-0">
              <div class="size-8 rounded-lg bg-primary/10 text-primary flex items-center justify-center shrink-0 mt-0.5">
                <FileText class="size-4" />
              </div>
              <div class="min-w-0 space-y-1">
                <div class="flex flex-wrap items-center gap-2">
                  <span class="font-bold text-foreground truncate max-w-[280px] sm:max-w-[360px]">{job.title}</span>
                  {#if job.status === 'printed'}
                    <Badge variant="outline" class="text-[9px] text-emerald-600 dark:text-emerald-400 border-emerald-500/30 gap-1 py-0">
                      <CheckCircle2 class="size-2.5" /> Printed
                    </Badge>
                  {:else}
                    <Badge variant="outline" class="text-[9px] text-primary border-primary/30 py-0">
                      Ready
                    </Badge>
                  {/if}
                </div>
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-[10px] text-muted-foreground font-mono">
                  <span>{job.filename}</span>
                  <span>&bull;</span>
                  <span>{formatBytes(job.size_bytes)}</span>
                  <span>&bull;</span>
                  <span>{formatDate(job.created_at)}</span>
                </div>
              </div>
            </div>

            <div class="flex items-center gap-1.5 shrink-0 self-end sm:self-center border-t sm:border-t-0 pt-2 sm:pt-0 w-full sm:w-auto justify-end">
              <Button
                variant="default"
                size="sm"
                class="h-7 text-xs gap-1.5 px-3 font-semibold"
                onclick={() => handlePrint(job)}
              >
                <Printer class="size-3.5" /> Print
              </Button>
              <Button
                variant="outline"
                size="sm"
                class="h-7 text-xs gap-1.5 px-2.5"
                onclick={() => handleDownload(job)}
                title="Download PDF"
              >
                <Download class="size-3.5" /> PDF
              </Button>
              <Button
                variant="ghost"
                size="sm"
                class="h-7 text-xs px-2 text-muted-foreground hover:text-destructive"
                onclick={() => handleDelete(job.id)}
                title="Remove from queue"
              >
                <Trash2 class="size-3.5" />
              </Button>
            </div>
          </div>
        {/each}
      {/if}
    </div>

    <!-- Hidden iframe for silent printing -->
    <iframe bind:this={printFrame} class="hidden" title="Print document frame"></iframe>
  </DialogContent>
</Dialog>
