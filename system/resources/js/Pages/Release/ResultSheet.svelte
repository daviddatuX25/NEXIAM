<script>
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
  import { Button } from '@/Components/ui/button';
  import { Printer } from 'lucide-svelte';

  let { applicant = {}, sheetHtml = null, paper_size = 'a4', template_error = null } = $props();

  const paperWidth = { a4: 210, legal: 216, letter: 216 }[paper_size] ?? 210;

  const breadcrumbs = [
    { label: 'Release', href: '/release' },
    { label: 'Result sheet' },
  ];
</script>

<svelte:head><title>Result sheet · Release</title></svelte:head>

<StaffLayout {breadcrumbs}>
  <div class="min-w-0 space-y-4 p-4 sm:p-6">
    <div class="flex flex-wrap items-center gap-3 rounded-xl border border-border bg-card p-4">
      <p class="text-sm text-muted-foreground">{applicant.name ?? ''} · {applicant.reference ?? ''}</p>
      <Button variant="outline" size="sm" class="min-h-10 gap-2" onclick={() => window.print()}>
        <Printer class="size-4" />
        Print
      </Button>
    </div>

    {#if template_error}
      <div class="rounded-xl border border-destructive/50 bg-destructive/10 p-6 text-destructive">
        {template_error}
      </div>
    {:else if sheetHtml}
      <div class="mx-auto rounded-sm bg-white p-2 shadow-sm ring-1 ring-border" style={`width: ${paperWidth}mm;`}>
        <div class="print-content" style="width: {paperWidth}mm;">
          {@html sheetHtml}
        </div>
      </div>
    {:else}
      <p class="text-sm text-muted-foreground">No sheet rendered.</p>
    {/if}
  </div>
</StaffLayout>
