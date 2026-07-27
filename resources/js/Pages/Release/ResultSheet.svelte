<script>
  import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
  import { usePage } from '@inertiajs/svelte';
  import { Link, router } from '@inertiajs/svelte';
  import { Button } from '@/Components/ui/button';
  import { Printer, Download, FileDown } from 'lucide-svelte';

  let { sessionId = '1', applicantId = '1001', applicant = {}, scores = [], printed = false, templateHtml = null, templateError = null, paperSize = 'a4', orientation = 'portrait', logicalUnit = 'full' } = $props();

  const pageWidthMm = $derived(
    paperSize === 'legal' ? 216 : paperSize === 'letter' ? 216 : 210
  );

  const _page = usePage();
  const printDisabled = $derived(($_page?.props?.release_mode ?? 'online') === 'online');
  const sid = $derived(String(sessionId));

  const breadcrumbs = $derived([
    { label: 'Release', href: '/admin/release' },
    { label: 'Session #' + sid, href: `/admin/release/print/${sid}` },
    { label: 'Print' }
  ]);

  const pdfBaseUrl = $derived(`/admin/release/print/${sid}/applicants/${applicant.id}/pdf`);
  const docxUrl = $derived(`/admin/release/print/${sid}/applicants/${applicant.id}/docx`);
  const canUsePdf = $derived(!printDisabled && !templateError && templateHtml);

  let markedPrinted = $state(printed);
  $effect(() => {
    markedPrinted = printed;
  });

  function toggleMarkPrinted() {
    const next = !markedPrinted;
    markedPrinted = next;
    router.post(`/admin/release/print/${sid}/mark-printed`, {
      applicant_ids: [applicant.id],
      printed: next,
    }, { preserveScroll: true });
  }
</script>

<svelte:head>
  <title>Result sheet - {applicant.name} - SecureCAT</title>
</svelte:head>

<AuthenticatedLayout breadcrumbs={breadcrumbs}>
  <div class="p-4 space-y-4">
    <div class="flex flex-wrap gap-3">
      {#if canUsePdf}
        <a href={pdfBaseUrl} target="_blank" rel="noopener">
          <Button class="min-h-[44px] gap-2">
            <Printer class="h-4 w-4" />
            Print
          </Button>
        </a>
        <a href={`${pdfBaseUrl}?download=1`} rel="noopener">
          <Button variant="secondary" class="min-h-[44px] gap-2">
            <Download class="h-4 w-4" />
            Download PDF
          </Button>
        </a>
        <a href={docxUrl} rel="noopener">
          <Button variant="secondary" class="min-h-[44px] gap-2">
            <FileDown class="h-4 w-4" />
            Download DOCX
          </Button>
        </a>
      {/if}
      <Button variant="outline" onclick={toggleMarkPrinted} class="min-h-[44px]">
        {markedPrinted ? 'Unmark printed' : 'Mark as printed'}
      </Button>
    </div>
    {#if printDisabled}
      <p class="text-xs text-muted-foreground">
        Printing is disabled in online-only release mode.
        <a href="/admin/settings" class="underline">Change in Settings</a>
      </p>
    {/if}
  </div>

  <div class="p-6 mx-auto" style="max-width: {pageWidthMm}mm;">
    {#if templateError}
      <div class="rounded-lg border border-destructive/50 bg-destructive/10 p-6 text-destructive">
        <p>{templateError}</p>
        <Link href="/admin/release/result-templates" class="mt-4 inline-block text-sm underline">Go to Result templates</Link>
      </div>
    {:else if templateHtml}
      <div class="border border-foreground/20 rounded-lg p-6 bg-white">
        <iframe
          srcdoc={templateHtml}
          class="w-full"
          style="border: none; overflow: hidden; display: block;"
          sandbox="allow-same-origin"
          title="Result Sheet"
          onload={(e) => {
            const iframe = e.currentTarget;
            const doc = iframe.contentDocument;
            if (doc) {
              if (doc.body) {
                doc.body.style.margin = '0';
                doc.body.style.padding = '0';
                doc.body.style.background = 'transparent';
              }
              const h = doc.documentElement?.scrollHeight || doc.body?.scrollHeight;
              if (h) {
                iframe.style.height = `${h}px`;
              }
            }
          }}
        ></iframe>
      </div>
    {:else}
      <div class="rounded-lg border border-muted p-6 text-muted-foreground">
        <p>No template available.</p>
      </div>
    {/if}
  </div>
</AuthenticatedLayout>
