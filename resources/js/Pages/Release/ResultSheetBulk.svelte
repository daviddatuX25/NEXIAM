<script>
  import { onMount } from 'svelte';
  import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
  import { usePage } from '@inertiajs/svelte';
  import { Link, router } from '@inertiajs/svelte';
  import { Button } from '@/Components/ui/button';
  import { Printer, Download, FileText } from 'lucide-svelte';

  let {
    sessionId = '1',
    applicantIds = [],
    applicants = [],
    sheetsHtml = [],
    templateError = null,
    paperSize: initialPaperSize = 'a4',
    orientation = 'portrait',
    logicalUnit = 'full',
    paperOptions = { a4: 'A4', letter: 'Letter' },
  } = $props();
  const sid = $derived(sessionId != null ? String(sessionId) : null);

  const _page = usePage();
  const printDisabled = $derived(($_page?.props?.release_mode ?? 'online') === 'online');

  const breadcrumbs = $derived(
    sessionId
      ? [
          { label: 'Release', href: '/admin/release' },
          { label: 'Session #' + sid, href: `/admin/release/print/${sid}` },
          { label: 'Print' }
        ]
      : [
          { label: 'Release', href: '/admin/release' },
          { label: 'Print' }
        ]
  );

  let markedAllPrinted = $state(false);
  let paperSize = $state(initialPaperSize);
  let scalePercent = $state(100);

  const MM_TO_PX = 96 / 25.4;
  const HALF_HEIGHT_MM = 148.5;
  const SAFETY_FACTOR = 0.99;

  const isHalf = $derived(['half_a4', 'half_legal', 'half_letter'].includes(logicalUnit));
  const pageWidthMm = $derived(paperSize === 'legal' ? 216 : paperSize === 'letter' ? 215.9 : 210);
  const pageHeightMm = $derived(paperSize === 'letter' ? 279.4 : 297);
  const userScale = $derived(scalePercent / 100);

  function fitToBounds() {
    const scale = Math.max(0.1, Math.min(1, userScale));
    const iframes = document.querySelectorAll('.result-sheet-iframe');

    iframes.forEach((iframe) => {
      const doc = iframe.contentDocument;
      if (!doc) return;

      if (doc.body) {
        doc.body.style.margin = '0';
        doc.body.style.padding = '0';
        doc.body.style.background = 'transparent';
      }

      if (isHalf) {
        const dual = doc.querySelector('.print-template--dual');
        if (dual) {
          dual.style.height = `${pageHeightMm}mm`;
        }

        const targetPx = HALF_HEIGHT_MM * MM_TO_PX * SAFETY_FACTOR;
        const halfs = doc.querySelectorAll('.print-template--half');
        halfs.forEach((half) => {
          const h = half.scrollHeight;
          if (h > targetPx && h > 0) {
            half.style.zoom = String((targetPx / h) * scale);
          } else {
            half.style.zoom = scale < 1 ? String(scale) : '';
          }
        });
      } else {
        const targetPx = pageHeightMm * MM_TO_PX * SAFETY_FACTOR;
        let tmpls = doc.querySelectorAll('.print-template');
        if (tmpls.length === 0 && doc.body) {
          const contentDiv = doc.body.querySelector('div') || doc.body;
          tmpls = [contentDiv];
        }
        tmpls.forEach((tmpl) => {
          const h = tmpl.scrollHeight;
          if (h > targetPx && h > 0) {
            tmpl.style.zoom = String((targetPx / h) * scale);
          } else {
            tmpl.style.zoom = scale < 1 ? String(scale) : '';
          }
        });
      }

      // Automatically adjust iframe height to contain zoomed contents without scrollbar
      const totalH = doc.documentElement?.scrollHeight || doc.body?.scrollHeight;
      if (totalH) {
        iframe.style.height = `${totalH + 4}px`;
      }
    });
  }

  function runFit() {
    requestAnimationFrame(() => requestAnimationFrame(fitToBounds));
  }

  const bulkPdfBaseUrl = $derived(
    sid
      ? `/admin/release/print/${sid}/print-bulk-pdf?ids=${applicantIds.join(',')}`
      : `/admin/release/print/bulk-pdf?ids=${applicantIds.join(',')}`
  );

  const bulkDocxBaseUrl = $derived(
    sid
      ? `/admin/release/print/${sid}/print-bulk-docx?ids=${applicantIds.join(',')}`
      : `/admin/release/print/bulk-docx?ids=${applicantIds.join(',')}`
  );

  function toggleMarkAllPrinted() {
    markedAllPrinted = !markedAllPrinted;
    if (sid) {
      router.post(`/admin/release/print/${sid}/mark-printed`, {
        applicant_ids: applicants.map((a) => a.id),
        printed: markedAllPrinted,
      }, { preserveScroll: true });
    }
  }

  onMount(() => {
    runFit();
    document.fonts?.ready?.then(runFit);
  });

  $effect(() => {
    sheetsHtml;
    logicalUnit;
    paperSize;
    scalePercent;
    runFit();
  });
</script>

<svelte:head>
  <title>Print bulk - {applicants.length} result sheets - SecureCAT</title>
</svelte:head>

<AuthenticatedLayout breadcrumbs={breadcrumbs}>
  <div class="p-4 space-y-4">
    <div class="flex flex-wrap gap-3 items-center">
      {#if sheetsHtml.length > 0 && !templateError}
        <div class="flex items-center gap-2">
          <label for="paper-size" class="text-sm font-medium">Paper</label>
          <select
            id="paper-size"
            bind:value={paperSize}
            class="flex h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm"
          >
            {#each Object.entries(paperOptions) as [k, v]}
              <option value={k}>{v}</option>
            {/each}
          </select>
        </div>
        <div class="flex items-center gap-2">
          <label for="scale" class="text-sm font-medium">Scale</label>
          <select
            id="scale"
            bind:value={scalePercent}
            class="flex h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm"
          >
            {#each [100, 95, 90, 85, 80] as pct}
              <option value={pct}>{pct}%</option>
            {/each}
          </select>
        </div>
      {/if}
      {#if !printDisabled && sheetsHtml.length > 0 && !templateError}
        <a href={bulkPdfBaseUrl} target="_blank" rel="noopener">
          <Button class="min-h-[44px] gap-2">
            <Printer class="h-4 w-4" />
            Print all {applicants.length} sheets
          </Button>
        </a>
        <a href={`${bulkPdfBaseUrl}&download=1`} rel="noopener">
          <Button variant="secondary" class="min-h-[44px] gap-2">
            <Download class="h-4 w-4" />
            Download PDF
          </Button>
        </a>
        <a href={`${bulkDocxBaseUrl}&merge=1`} rel="noopener">
          <Button variant="outline" class="min-h-[44px] gap-2">
            <FileText class="h-4 w-4" />
            Download Word (Merged)
          </Button>
        </a>
        <a href={bulkDocxBaseUrl} rel="noopener">
          <Button variant="outline" class="min-h-[44px] gap-2">
            <FileText class="h-4 w-4" />
            Download Word (ZIP)
          </Button>
        </a>
      {/if}
      {#if printDisabled}
        <p class="text-xs text-muted-foreground">
          Printing is disabled in online-only release mode.
          <a href="/admin/settings" class="underline">Change in Settings</a>
        </p>
      {/if}
      {#if sessionId}
        <Button variant="outline" onclick={toggleMarkAllPrinted} class="min-h-[44px]">
          {markedAllPrinted ? 'Unmark all printed' : 'Mark all as printed'}
        </Button>
      {/if}
    </div>
  </div>

  <div
    class="p-6 mx-auto space-y-8"
    style="max-width: {pageWidthMm}mm;"
  >
    {#if templateError}
      <div class="rounded-lg border border-destructive/50 bg-destructive/10 p-6 text-destructive">
        <p>{templateError}</p>
        <Link href="/admin/release/result-templates" class="mt-4 inline-block text-sm underline">Go to Result templates</Link>
      </div>
    {:else if sheetsHtml.length > 0}
      {#each sheetsHtml as html, index}
        <div
          class="border border-foreground/20 rounded-lg p-6 bg-white"
        >
          <iframe
            srcdoc={html}
            class="w-full result-sheet-iframe"
            style="border: none; overflow: hidden; display: block;"
            sandbox="allow-same-origin"
            title="Result Sheet {index + 1}"
            onload={() => {
              fitToBounds();
            }}
          ></iframe>
        </div>
      {/each}
    {:else}
      <div class="rounded-lg border border-muted p-6 text-muted-foreground">
        <p>No template or applicants selected.</p>
      </div>
    {/if}
  </div>
</AuthenticatedLayout>

<style>
  .half-layout-page :global(> .print-template--dual) {
    height: 297mm;
  }
  .half-layout-page :global(.print-template--half) {
    overflow: hidden;
  }
</style>