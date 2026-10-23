<script>
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
  import { MarkPrintedDialog } from '@/Components/ResultSheet';
  import { Button } from '@/Components/ui/button';
  import { Printer, CheckSquare, RotateCw } from 'lucide-svelte';
  import { onMount, onDestroy } from 'svelte';
  import { router } from '@inertiajs/svelte';
  import ApplicantActionsMenu from '@/Components/Admission/ApplicantActionsMenu.svelte';

  let {
    applicant = {},
    sheetHtml = null,
    pdf_base64 = null,
    pdf_url = null,
    preview_job_id = null,
    paper_size = 'a4',
    template_error = null,
  } = $props();

  const paperWidth = { a4: 210, legal: 216, letter: 216 }[paper_size] ?? 210;
  let pdfSrc = $state(pdf_base64 ? `data:application/pdf;base64,${pdf_base64}#toolbar=0` : (pdf_url ?? ''));
  let previewStatus = $state(preview_job_id ? 'queued' : (pdfSrc ? 'ready' : 'none'));
  let showMarkDialog = $state(false);
  let pollTimer = null;

  const breadcrumbs = [
    { label: 'Release', href: '/release' },
    { label: 'Result sheet' },
    { label: applicant.name ?? 'Applicant' },
  ];

  onMount(() => {
    if (preview_job_id) pollPreview();
  });

  onDestroy(() => {
    if (pollTimer) clearInterval(pollTimer);
  });

  function pollPreview() {
    pollTimer = setInterval(async () => {
      try {
        const res = await fetch(`/printing/jobs/${preview_job_id}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        if (!res.ok) return;
        const job = await res.json();
        if (job.status === 'ready') {
          clearInterval(pollTimer);
          previewStatus = 'ready';
          pdfSrc = `${pdf_url}#toolbar=0`;
        } else if (job.status === 'failed') {
          clearInterval(pollTimer);
          previewStatus = 'failed';
        }
      } catch {
        clearInterval(pollTimer);
        previewStatus = 'failed';
      }
    }, 1200);
  }

  function handlePrint() {
    if (pdfSrc) window.open(pdfSrc, '_blank', 'noopener');
    else window.print();
    setTimeout(() => (showMarkDialog = true), 500);
  }
</script>

<svelte:head><title>Result sheet · {applicant.name ?? 'Release'}</title></svelte:head>

<StaffLayout {breadcrumbs}>
  <div class="min-w-0 space-y-4 p-4 sm:p-6">
    <div class="no-print flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border bg-card p-4 shadow-xs">
      <div>
        <h2 class="text-sm font-semibold text-foreground">{applicant.name ?? ''}</h2>
        <p class="font-mono text-xs text-muted-foreground">{applicant.reference ?? ''}</p>
      </div>

      <div class="flex items-center gap-2">
        {#if applicant.attempt_id}
          <ApplicantActionsMenu attemptId={applicant.attempt_id} context={{ exam_session_id: applicant.exam_session_id ?? null }} />
        {/if}
        <Button variant="outline" size="sm" class="min-h-10 gap-2" onclick={() => (showMarkDialog = true)}>
          <CheckSquare class="size-4 text-muted-foreground" />Mark as printed…
        </Button>
        <Button size="sm" class="min-h-10 gap-2 font-semibold" onclick={handlePrint} disabled={previewStatus === 'queued'}>
          {#if previewStatus === 'queued'}<RotateCw class="size-4 animate-spin" />Preparing PDF…{:else}<Printer class="size-4" />Print result sheet{/if}
        </Button>
      </div>
    </div>

    {#if template_error}
      <div class="rounded-xl border border-destructive/50 bg-destructive/10 p-6 text-destructive">{template_error}</div>
    {:else if previewStatus === 'queued'}
      <div class="mx-auto flex h-[700px] max-w-3xl flex-col items-center justify-center gap-3 rounded-sm border border-border bg-card text-muted-foreground">
        <RotateCw class="size-8 animate-spin text-primary" />
        <p class="text-sm">Generating the exact DOCX PDF preview…</p>
      </div>
    {:else if previewStatus === 'failed'}
      <div class="mx-auto max-w-3xl rounded-xl border border-destructive/30 bg-destructive/10 p-6 text-sm text-destructive">PDF preview failed. Use the batch retry flow.</div>
    {:else if pdfSrc}
      <div class="sheet-print-container mx-auto overflow-hidden rounded-sm bg-white shadow-xs ring-1 ring-border" style={`width: ${paperWidth}mm;`}>
        <iframe title="Result sheet PDF" src={pdfSrc} class="h-[900px] w-full border-0" />
      </div>
    {:else if sheetHtml}
      <div class="sheet-print-container mx-auto rounded-sm bg-white p-4 shadow-xs ring-1 ring-border" style={`width: ${paperWidth}mm;`}>
        <div class="print-content" style="width: 100%;">{@html sheetHtml}</div>
      </div>
    {:else}
      <p class="text-sm text-muted-foreground">No sheet rendered.</p>
    {/if}
  </div>

  <MarkPrintedDialog open={showMarkDialog} documentIds={applicant.document_id ? [applicant.document_id] : []} count={1} onclose={() => (showMarkDialog = false)} onmarked={() => router.reload()} />
</StaffLayout>

<style>
  @media print {
    :global(body) { background: white !important; margin: 0 !important; padding: 0 !important; }
    :global(aside), :global(header), :global(nav), .no-print { display: none !important; }
    .sheet-print-container { box-shadow: none !important; border: none !important; padding: 0 !important; margin: 0 !important; width: 100% !important; }
  }
</style>
