<script>
  /* global route */
  /**
   * Applicant QR — permanent own-scope locator (founder 2026-08-15).
   * ONE code per applicant: never expires, never regenerates; the same code
   * works at the consultation front desk, registrar counter, and staff scan.
   * Printing is recommended — the printed copy is the same code.
   */
  import ApplicantLayout from '@/Pages/Applicant/ApplicantLayout.svelte';
  import E2PageState from '@/Pages/Admission/E2PageState.svelte';
  import { Button } from '@/Components/ui/button';
  import * as Card from '@/Components/ui/card';
  import QRCode from 'qrcode';
  import { ShieldCheck, Printer } from 'lucide-svelte';

  let { page_state = 'ready', attempt_id = null, qr = '' } = $props();

  let canvasEl = $state(null);

  $effect(() => {
    const canvas = canvasEl;
    if (canvas && qr) {
      QRCode.toCanvas(canvas, qr, { width: 240, margin: 2 }).catch((error) => {
        console.error('QR render failed', error);
      });
    }
  });
</script>

<svelte:head><title>My NEXIAM QR</title></svelte:head>

<ApplicantLayout title="My NEXIAM QR">
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
        <h1 class="text-2xl font-semibold tracking-tight">My NEXIAM QR</h1>
        {#if attempt_id}
          <p class="font-mono text-sm text-muted-foreground">Attempt #{attempt_id}</p>
        {/if}
      </div>

      <Card.Root class="overflow-hidden shadow-sm">
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
            <p class="text-sm font-medium">Show this QR when asked by NEXIAM staff</p>
            <p class="text-xs text-muted-foreground">
              One permanent code for your admission attempt — it never changes or expires.
            </p>
          </div>
          <Button variant="outline" class="min-h-11" onclick={() => window.print()}>
            <Printer class="size-4" />
            Print approved copy
          </Button>
        </Card.Content>
      </Card.Root>

      <Card.Root>
        <Card.Header class="pb-3">
          <Card.Title class="text-lg">About this QR</Card.Title>
        </Card.Header>
        <Card.Content class="space-y-3 pt-2 text-sm">
          <ul class="list-disc space-y-1.5 pl-5 text-muted-foreground">
            <li>It identifies your admission attempt — nothing else.</li>
            <li>It contains no name, scores, program, action, or permission.</li>
            <li>Permanent — the same code is valid for your whole admission window.</li>
            <li>Use it at the consultation front desk, registrar counter, or with any staff scan.</li>
          </ul>
          <p class="flex gap-2 text-xs text-muted-foreground">
            <ShieldCheck class="mt-0.5 size-3.5 shrink-0" />
            Printing is recommended; the printed copy is the same permanent code.
          </p>
        </Card.Content>
      </Card.Root>
    {/if}
  </div>
</ApplicantLayout>
