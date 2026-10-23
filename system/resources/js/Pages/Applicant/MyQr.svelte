<script>
  /* global route */
  /**
   * Applicant QR — permanent own-scope locator (founder 2026-08-15).
   * ONE code per applicant: never expires, never regenerates; the same code
   * works at the Release Day desk, registrar counter, and staff scan.
   * Printing is recommended — the printed copy is the same code.
   */
  import ApplicantLayout from '@/Pages/Applicant/ApplicantLayout.svelte';
  import E2PageState from '@/Pages/Admission/E2PageState.svelte';
  import TourTriggerGlyph from '@/Components/kit/Walkthrough/TourTriggerGlyph.svelte';
  import XiamNpcDialog from '@/Components/kit/Walkthrough/XiamNpcDialog.svelte';
  import { Button } from '@/Components/ui/button';
  import * as Card from '@/Components/ui/card';
  import QRCode from 'qrcode';
  import { Download, Printer, ShieldCheck } from 'lucide-svelte';
  import { downloadApplicantQrPdf, printApplicantQr } from '@/lib/applicant-qr-print.js';

  let {
    page_state = 'ready',
    attempt_id = null,
    qr = '',
    print_url = '',
  } = $props();

  let canvasEl = $state(null);
  let tourOpen = $state(false);
  let printing = $state(false);
  let actionError = $state('');

  function printUrl() {
    return print_url || route('applicant.qr.print');
  }

  async function handlePrint() {
    if (printing) return;
    printing = true;
    actionError = '';

    try {
      await printApplicantQr(printUrl());
    } catch (error) {
      actionError = error instanceof Error ? error.message : 'The QR document could not be loaded. Please try again.';
    } finally {
      printing = false;
    }
  }

  function handleDownloadPdf() {
    actionError = '';
    try {
      downloadApplicantQrPdf(printUrl());
    } catch (error) {
      actionError = error instanceof Error ? error.message : 'The PDF could not be downloaded. Please try again.';
    }
  }

  const qrContext = $derived.by(() => {
    const hasQ = Boolean(qr && qr.trim().length > 0);
    const safetyKey = `${page_state}:${attempt_id ?? 'noatt'}:${hasQ ? 'qr' : 'noqr'}`;

    return {
      entityKey: attempt_id ? `attempt-${attempt_id}` : 'my-qr',
      safetyKey,
      freshness: 'current',
      pageState: page_state,
      hasQr: hasQ,
      attemptId: attempt_id ? Number(attempt_id) : null,
    };
  });

  $effect(() => {
    const canvas = canvasEl;
    if (canvas && qr) {
      QRCode.toCanvas(canvas, qr, { width: 240, margin: 2 }).catch((error) => {
        console.error('QR render failed', error);
      });
    }
  });
</script>

<svelte:head><title>My QR Pass</title></svelte:head>

<ApplicantLayout title="My QR Pass">
  <div class="mx-auto max-w-lg space-y-5">
    {#if page_state !== 'ready'}
      <div class="sr-only"><E2PageState state={page_state} /></div>
      <Card.Root>
        <Card.Content class="space-y-3 p-6 text-center">
          <ShieldCheck class="mx-auto size-8 text-muted-foreground" />
          <p class="font-medium">QR unavailable</p>
          <p class="text-sm text-muted-foreground">
            This account cannot access the applicant QR.
          </p>
        </Card.Content>
      </Card.Root>
    {:else}
      <div class="space-y-1">
        <div class="flex items-center gap-2">
          <h1 class="text-2xl font-semibold tracking-tight">My QR Pass</h1>
          <TourTriggerGlyph tourKey="applicant-qr" label="Need tips on using your QR pass? Ask Kuya Xiam!" onclick={() => (tourOpen = true)} />
        </div>
        {#if attempt_id}
          <p class="font-mono text-sm text-muted-foreground">Attempt #{attempt_id}</p>
        {/if}
      </div>

      <Card.Root class="overflow-hidden shadow-sm" data-tour="qr-card">
        <Card.Content class="flex flex-col items-center gap-4 p-6">
          <div class="rounded-xl border bg-white p-3">
            <canvas
              bind:this={canvasEl}
              class="block size-60"
              role="img"
              aria-label="Your applicant QR code"
            ></canvas>
          </div>
          <div class="space-y-1 text-center">
            <p class="text-sm font-medium">Show this QR when requested by university staff</p>
            <p class="text-xs text-muted-foreground">
              Use it at the Release Day desk, registrar counter, or with any university staff scan.
            </p>
          </div>
          <div class="flex flex-wrap justify-center gap-2">
            <Button
              class="min-h-11 gap-2"
              type="button"
              onclick={handlePrint}
              disabled={printing}
              data-tour="qr-print-button"
            >
              <Printer class="size-4" />
              {printing ? 'Preparing print…' : 'Print Pass'}
            </Button>
            <Button
              variant="outline"
              class="min-h-11 gap-2"
              type="button"
              onclick={handleDownloadPdf}
              disabled={printing}
            >
              <Download class="size-4" />
              Download PDF
            </Button>
          </div>
          {#if actionError}
            <p class="w-full text-center text-sm text-destructive" role="alert">{actionError}</p>
          {/if}
        </Card.Content>
      </Card.Root>

      <Card.Root>
        <Card.Content class="pt-5">
          <p class="flex items-center gap-2 rounded-lg border border-emerald-600/20 bg-emerald-600/5 px-3 py-2 text-sm text-muted-foreground">
            <ShieldCheck class="size-4 shrink-0 text-emerald-600" />
            <span>Permanent official pass for testing, registrar desks, and Release Day.</span>
          </p>
        </Card.Content>
      </Card.Root>
    {/if}
  </div>

  <XiamNpcDialog tourKey="applicant-qr" context={qrContext} bind:open={tourOpen} />
</ApplicantLayout>
