<script>
  import { onMount } from 'svelte';
  import { Link, router } from '@inertiajs/svelte';
  import { Button } from '@/Components/ui/button';
  import { ArrowLeft } from 'lucide-svelte';

  let {
    applicationIds = [],
    applicants = [],
    sheetsHtml = [],
    templateError = null,
    paperSize: initialPaperSize = 'a4',
    orientation = 'portrait',
    logicalUnit = 'full',
    paperOptions = {},
  } = $props();

  let markedAllPrinted = $state(false);
  let paperSize = $state(initialPaperSize);
  let scalePercent = $state(100);

  const MM_TO_PX = 96 / 25.4;
  const HALF_HEIGHT_MM = 148.5;
  const SAFETY_FACTOR = 0.99;

  const isHalf = $derived(['half_a4', 'half_legal', 'half_letter'].includes(logicalUnit));
  const pageWidthMm = $derived(
    paperSize === 'letter' ? 215.9 : paperSize === 'legal' ? 216 : 210
  );
  const pageHeightMm = $derived(
    paperSize === 'letter' ? 279.4 : paperSize === 'legal' ? 356 : 297
  );
  const userScale = $derived(scalePercent / 100);

  function fitToBounds() {
    const scale = Math.max(0.1, Math.min(1, userScale));
    if (isHalf) {
      const targetPx = HALF_HEIGHT_MM * MM_TO_PX * SAFETY_FACTOR;
      document.querySelectorAll('.half-layout-page').forEach((page) => {
        Array.from(page.children).forEach((child) => {
          const h = child.scrollHeight;
          if (h > targetPx && h > 0) {
            child.style.zoom = String((targetPx / h) * scale);
          } else {
            child.style.zoom = scale < 1 ? String(scale) : '';
          }
        });
      });
    } else {
      const targetPx = pageHeightMm * MM_TO_PX * SAFETY_FACTOR;
      document.querySelectorAll('.admission-slip-content:not(.half-layout-page) .sheet-inner').forEach((inner) => {
        const h = inner.scrollHeight;
        if (h > targetPx && h > 0) {
          inner.style.zoom = String((targetPx / h) * scale);
        } else {
          inner.style.zoom = scale < 1 ? String(scale) : '';
        }
      });
    }
  }

  function runFit() {
    requestAnimationFrame(() => requestAnimationFrame(fitToBounds));
  }

  function printAll() {
    window.print();
  }

  function toggleMarkAllPrinted() {
    markedAllPrinted = !markedAllPrinted;
    router.post('/admin/applications/print-slips/mark-printed', {
      application_ids: applicants.map((a) => a.id),
      printed: markedAllPrinted,
    }, { preserveScroll: true });
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
  <title>Print bulk - {applicants.length} admission slips - SecureCAT</title>
</svelte:head>

<div class="print:hidden p-4 space-y-4">
  <Link href="/admin/applications/print-slips" class="text-sm text-muted-foreground hover:text-foreground inline-flex items-center gap-1">
    <ArrowLeft class="h-4 w-4" />
    Back to print batch
  </Link>
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
    <Button
      variant="outline"
      onclick={printAll}
      class="min-h-[44px]"
      disabled={sheetsHtml.length === 0 || !!templateError}
    >
      Print all {applicants.length} slips
    </Button>
    <Button variant="outline" onclick={toggleMarkAllPrinted} class="min-h-[44px]">
      {markedAllPrinted ? 'Unmark all printed' : 'Mark all as printed'}
    </Button>
  </div>
</div>

<div
  class="p-6 mx-auto space-y-8 print:p-0 print:max-w-none print:space-y-0"
  style="max-width: {pageWidthMm}mm;"
>
  {#if templateError}
    <div class="rounded-lg border border-destructive/50 bg-destructive/10 p-6 text-destructive">
      <p>{templateError}</p>
      <Link href="/admin/settings" class="mt-4 inline-block text-sm underline">Go to Settings to configure admission slip template</Link>
    </div>
  {:else if sheetsHtml.length > 0}
    {#each sheetsHtml as html}
      <div
        class="border border-foreground/20 rounded-lg p-6 admission-slip-content bg-white print:border-none print:rounded-none {isHalf ? 'half-layout-page' : ''} {isHalf ? '' : 'min-h-[148mm]'}"
        style={isHalf ? 'height: 297mm; display: flex; flex-direction: column; gap: 0;' : ''}
      >
        {#if isHalf}
          {@html html}
        {:else}
          <div class="sheet-inner">{@html html}</div>
        {/if}
      </div>
    {/each}
  {:else}
    <div class="rounded-lg border border-muted p-6 text-muted-foreground">
      <p>No applicants selected.</p>
    </div>
  {/if}
</div>

<style>
  .half-layout-page :global(> *) {
    flex: 0 0 148.5mm;
    overflow: visible;
  }
  @media print {
    .admission-slip-content {
      page-break-after: always;
      border: none;
      border-radius: 0;
      box-shadow: none;
    }
    .admission-slip-content:last-child {
      page-break-after: auto;
    }
    @page {
      size: A4 portrait;
      margin: 0;
    }
  }
</style>
