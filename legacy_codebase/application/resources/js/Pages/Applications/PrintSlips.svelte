<script>
  import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
  import { Link, router } from '@inertiajs/svelte';
  import { Button } from '@/Components/ui/button';
  import { Badge } from '@/Components/ui/badge';
  import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
  import * as Table from '@/Components/ui/table';
  import { ArrowLeft, Printer, CheckSquare, Square } from 'lucide-svelte';

  let { applications = [] } = $props();

  let selected = $state(new Set());

  const allSelected = $derived(applications.length > 0 && selected.size === applications.length);

  const selectedPrintedCount = $derived(applications.filter((a) => selected.has(a.id) && a.printed).length);
  const selectedUnprintedCount = $derived(applications.filter((a) => selected.has(a.id) && !a.printed).length);
  const showMarkPrinted = $derived(selected.size > 0 && selectedUnprintedCount === selected.size);
  const showUnmarkPrinted = $derived(selected.size > 0 && selectedPrintedCount === selected.size);

  function toggleAll() {
    if (allSelected) {
      selected = new Set();
    } else {
      selected = new Set(applications.map((a) => a.id));
    }
  }

  function toggleOne(id) {
    const s = new Set(selected);
    if (s.has(id)) s.delete(id);
    else s.add(id);
    selected = s;
  }

  function markAsPrinted() {
    router.post('/admin/applications/print-slips/mark-printed', { application_ids: Array.from(selected), printed: true }, {
      onSuccess: () => (selected = new Set()),
    });
  }

  function unmarkPrinted() {
    router.post('/admin/applications/print-slips/mark-printed', { application_ids: Array.from(selected), printed: false }, {
      onSuccess: () => (selected = new Set()),
    });
  }

  function printBulk() {
    const ids = Array.from(selected);
    if (ids.length === 1) {
      window.open(`/admin/applications/print-slips/${ids[0]}/single`, '_blank', 'noopener');
    } else {
      router.visit(`/admin/applications/print-slips/bulk?ids=${ids.join(',')}`);
    }
  }

  function togglePrinted(app) {
    const printed = !app.printed;
    router.post('/admin/applications/print-slips/mark-printed', {
      application_ids: [app.id],
      printed,
    });
  }

  const breadcrumbs = $derived([
    { label: 'Applications', href: '/admin/applications' },
    { label: 'Print Slips' }
  ]);
</script>

<AuthenticatedLayout breadcrumbs={breadcrumbs}>
  <div class="space-y-6 min-w-0">
    <Card>
      <CardHeader>
        <CardTitle class="flex items-center gap-2">
          <Printer class="h-5 w-5" />
          Print admission slips
        </CardTitle>
        <CardDescription>
          Print admission slips for accepted applications, then mark as printed.
        </CardDescription>
      </CardHeader>
      <CardContent class="space-y-4">
        <div class="flex flex-wrap items-center gap-3">
          <Button variant="outline" size="sm" onclick={toggleAll} class="min-h-[44px]">
            {#if allSelected}
              <Square class="h-4 w-4 mr-2" />
            {:else}
              <CheckSquare class="h-4 w-4 mr-2" />
            {/if}
            {allSelected ? 'Deselect all' : 'Select all'}
          </Button>
          <Button variant="default" onclick={printBulk} disabled={selected.size === 0} class="min-h-[44px]">
            <Printer class="h-4 w-4 mr-2" />
            Print bulk{selected.size > 0 ? ` (${selected.size})` : ''}
          </Button>
          {#if showMarkPrinted}
            <Button variant="outline" onclick={markAsPrinted} class="min-h-[44px]">
              Mark {selected.size} as printed
            </Button>
          {:else if showUnmarkPrinted}
            <Button variant="ghost" onclick={unmarkPrinted} class="min-h-[44px]">
              Unmark {selected.size} printed
            </Button>
          {/if}
        </div>

        <div class="rounded-lg border border-border overflow-hidden min-w-0">
          <Table.Root class="w-full min-w-[560px]">
            <Table.Header class="bg-muted/50">
              <Table.Row>
                <Table.Head class="px-4 py-3 w-10">
                  <button type="button" onclick={toggleAll} aria-label={allSelected ? 'Deselect all' : 'Select all'} class="p-1">
                    {#if allSelected}
                      <CheckSquare class="h-5 w-5 text-primary" />
                    {:else}
                      <Square class="h-5 w-5 text-muted-foreground" />
                    {/if}
                  </button>
                </Table.Head>
                <Table.Head class="px-4 py-3">Reference</Table.Head>
                <Table.Head class="px-4 py-3">Name</Table.Head>
                <Table.Head class="px-4 py-3">Printed</Table.Head>
                <Table.Head class="px-4 py-3 text-right">Action</Table.Head>
              </Table.Row>
            </Table.Header>
            <Table.Body>
              {#each applications as app (app.id)}
                <Table.Row>
                  <Table.Cell class="px-4 py-3">
                    <button type="button" onclick={() => toggleOne(app.id)} aria-label="Toggle select">
                      {#if selected.has(app.id)}
                        <CheckSquare class="h-5 w-5 text-primary" />
                      {:else}
                        <Square class="h-5 w-5 text-muted-foreground" />
                      {/if}
                    </button>
                  </Table.Cell>
                  <Table.Cell class="px-4 py-3">{app.reference}</Table.Cell>
                  <Table.Cell class="px-4 py-3">{app.name}</Table.Cell>
                  <Table.Cell class="px-4 py-3">
                    <Badge variant={app.printed ? 'success' : 'muted'}>{app.printed ? 'Printed' : 'Not printed'}</Badge>
                  </Table.Cell>
                  <Table.Cell class="px-4 py-3 text-right">
                    <div class="flex justify-end gap-2">
                      <Link href={`/admin/applications/print-slips/${app.id}/single`} target="_blank">
                        <Button variant="outline" size="sm" class="min-h-[44px]">
                          <Printer class="h-4 w-4 mr-1.5" />
                          Print
                        </Button>
                      </Link>
                      {#if app.printed}
                        <Button variant="ghost" size="sm" class="min-h-[44px]" onclick={() => togglePrinted(app)}>
                          Unmark printed
                        </Button>
                      {:else}
                        <Button variant="outline" size="sm" class="min-h-[44px]" onclick={() => togglePrinted(app)}>
                          Mark printed
                        </Button>
                      {/if}
                    </div>
                  </Table.Cell>
                </Table.Row>
              {/each}
            </Table.Body>
          </Table.Root>
        </div>
      </CardContent>
    </Card>
  </div>
</AuthenticatedLayout>
