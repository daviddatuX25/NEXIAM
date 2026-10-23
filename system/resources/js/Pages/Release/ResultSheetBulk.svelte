<script>
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
  import { MarkPrintedDialog } from '@/Components/ResultSheet';
  import { Button } from '@/Components/ui/button';
  import { Badge } from '@/Components/ui/badge';
  import { Printer, CheckSquare, ArrowLeft, RotateCw, AlertCircle } from 'lucide-svelte';
  import { router } from '@inertiajs/svelte';
  import { onMount, onDestroy } from 'svelte';

  let {
    session = {},
    pdfPreviews = [],
    sheetsHtml = [],
    document_ids = [],
    paper_size = 'a4',
    template_error = null,
  } = $props();

  let previews = $state(pdfPreviews.map((p) => ({ ...p })));
  let showMarkDialog = $state(false);
  let pollTimer = null;

  const paperWidth = { a4: 210, legal: 216, letter: 216 }[paper_size] ?? 210;
  const breadcrumbs = [
    { label: 'Release', href: '/release' },
    { label: `Session ${session.code ?? session.exam_session_id}`, href: `/release/print/sessions/${session.exam_session_id}` },
    { label: 'Print preview' },
  ];

  onMount(() => {
    if (previews.some((p) => p.status === 'queued')) {
      pollTimer = setInterval(pollQueued, 1200);
    }
  });

  onDestroy(() => {
    if (pollTimer) clearInterval(pollTimer);
  });

  async function pollQueued() {
    let pending = false;
    for (const preview of previews) {
      if (preview.status !== 'queued' || !preview.job_id) continue;
      pending = true;
      try {
        const res = await fetch(`/printing/jobs/${preview.job_id}`, {
          headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          credentials: 'same-origin',
        });
        if (!res.ok) continue;
        const job = await res.json();
        if (job.status === 'ready') {
          preview.status = 'ready';
          preview.src = `${preview.download_url}#toolbar=0`;
        } else if (job.status === 'failed') {
          preview.status = 'failed';
          preview.error = job.safe_error;
        }
      } catch {
        preview.error = 'Preview status unavailable';
      }
    }
    previews = [...previews];
    if (!pending && pollTimer) {
      clearInterval(pollTimer);
      pollTimer = null;
    }
  }

  function handlePrint() {
    window.print();
    setTimeout(() => (showMarkDialog = true), 500);
  }
</script>

<svelte:head><title>Result sheet stack · Release</title></svelte:head>

<StaffLayout {breadcrumbs}>
  <div class="min-w-0 space-y-4 p-4 sm:p-6">
    <div class="no-print flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border bg-card p-4 shadow-xs">
      <div>
        <h2 class="text-sm font-semibold text-foreground">Result Sheet Stack Preview</h2>
        <p class="text-xs text-muted-foreground">
          {previews.length || sheetsHtml.length} sheet(s) · {paper_size.toUpperCase()} · exact PDF representation
        </p>
      </div>
      <div class="flex items-center gap-2">
        <Button variant="outline" size="sm" class="min-h-10 gap-1.5" onclick={() => router.visit('/release')}>
          <ArrowLeft class="size-4" />Back to release
        </Button>
        <Button size="sm" class="min-h-10 gap-2 font-semibold" onclick={handlePrint}>
          <Printer class="size-4" />Print all sheets
        </Button>
      </div>
    </div>

    {#if template_error}
      <div class="rounded-xl border border-destructive/50 bg-destructive/10 p-6 text-destructive">{template_error}</div>
    {:else if previews.length > 0}
      <div class="space-y-8">
        {#each previews as preview, i (preview.reference ?? i)}
          <div class="sheet-page mx-auto overflow-hidden rounded-sm bg-white shadow-xs ring-1 ring-border" style={`width: ${paperWidth}mm;`}>
            {#if preview.status === 'ready' && preview.src}
              <iframe title={`Result sheet ${i + 1}`} src={preview.src} class="h-[900px] w-full border-0" />
            {:else if preview.status === 'queued'}
              <div class="flex h-[500px] flex-col items-center justify-center gap-3 text-center text-muted-foreground">
                <RotateCw class="size-7 animate-spin text-primary" />
                <p class="text-xs">Rendering {preview.name ?? 'result sheet'}…</p>
              </div>
            {:else}
              <div class="flex h-[500px] flex-col items-center justify-center gap-2 text-center text-destructive">
                <AlertCircle class="size-7" />
                <p class="text-xs">Preview failed: {preview.error ?? 'unknown error'}</p>
              </div>
            {/if}
          </div>
        {/each}
      </div>
    {:else if sheetsHtml.length > 0}
      <div class="space-y-8">
        {#each sheetsHtml as html, i (i)}
          <div class="sheet-page mx-auto rounded-sm bg-white p-4 shadow-xs ring-1 ring-border" style={`width: ${paperWidth}mm;`}>
            {@html html}
          </div>
        {/each}
      </div>
    {:else}
      <p class="text-sm text-muted-foreground">No sheets selected.</p>
    {/if}
  </div>

  <MarkPrintedDialog
    open={showMarkDialog}
    documentIds={document_ids}
    count={document_ids.length || previews.length || sheetsHtml.length}
    onmarked={() => router.reload()}
  />
</StaffLayout>

<style>
  @media print {
    :global(body) { background: white !important; margin: 0 !important; padding: 0 !important; }
    :global(aside), :global(header), :global(nav), .no-print { display: none !important; }
    .sheet-page { box-shadow: none !important; border: none !important; padding: 0 !important; margin: 0 !important; width: 100% !important; page-break-after: always; break-after: page; }
    .sheet-page iframe { height: 100vh !important; }
  }
</style>
