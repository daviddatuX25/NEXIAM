<script>
  import { Link, router } from '@inertiajs/svelte';
  import { Button } from '@/Components/ui/button';
  import { ArrowLeft } from 'lucide-svelte';

  let { applicationId = '1', applicant = {}, printed = false, templateHtml = null, templateError = null } = $props();
  const appId = $derived(String(applicationId));

  let markedPrinted = $state(printed);
  $effect(() => {
    markedPrinted = printed;
  });

  function printSheet() {
    window.print();
  }

  function toggleMarkPrinted() {
    const next = !markedPrinted;
    markedPrinted = next;
    router.post('/admin/applications/print-slips/mark-printed', {
      application_ids: [Number(appId)],
      printed: next,
    }, { preserveScroll: true });
  }
</script>

<svelte:head>
  <title>Admission slip - {applicant.name ?? applicant.reference} - SecureCAT</title>
</svelte:head>

<div class="print:hidden p-4 space-y-4">
  <Link href="/admin/applications/print-slips" class="text-sm text-muted-foreground hover:text-foreground inline-flex items-center gap-1">
    <ArrowLeft class="h-4 w-4" />
    Back to print batch
  </Link>
  <div class="flex flex-wrap gap-3">
    <Button onclick={printSheet} class="min-h-[44px]">Print this slip</Button>
    <Button variant="outline" onclick={toggleMarkPrinted} class="min-h-[44px]">
      {markedPrinted ? 'Unmark printed' : 'Mark as printed'}
    </Button>
  </div>
</div>

<div class="p-6 max-w-[210mm] mx-auto print:p-4 print:max-w-none">
  {#if templateError}
    <div class="rounded-lg border border-destructive/50 bg-destructive/10 p-6 text-destructive">
      <p>{templateError}</p>
    </div>
  {:else if templateHtml}
    <div class="border border-foreground/20 rounded-lg p-6 print:border print:rounded-none print:p-6 admission-slip-content">
      {@html templateHtml}
    </div>
  {:else}
    <div class="rounded-lg border border-muted p-6 text-muted-foreground">
      <p>No slip available.</p>
    </div>
  {/if}
</div>
