<script>
  import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
  import { usePage } from '@inertiajs/svelte';
  import { Link, router } from '@inertiajs/svelte';
  import { Button } from '@/Components/ui/button';
  import { Badge } from '@/Components/ui/badge';
  import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
  import * as Table from '@/Components/ui/table';
  import { ArrowLeft, Printer, Download, CheckSquare, Square, FileText } from 'lucide-svelte';
  import { formatDate } from '@/lib/date-utils';

  let { sessionId = '1', session = {}, applicants = [] } = $props();
  const sid = $derived(sessionId != null ? String(sessionId) : null);

  const _page = usePage();
  const printDisabled = $derived(($_page?.props?.release_mode ?? 'online') === 'online');

  const breadcrumbs = $derived([
    { label: 'Release', href: '/admin/release' },
    { label: 'Session #' + sid, href: `/admin/release/print/${sid}` },
    { label: 'Print' }
  ]);

  let selected = $state(new Set());

  let copies = $state(1);

  const allSelected = $derived(applicants.length > 0 && selected.size === applicants.length);

  const selectedPrintedCount = $derived(applicants.filter((a) => selected.has(a.applicant_id) && a.printed).length);
  const selectedUnprintedCount = $derived(applicants.filter((a) => selected.has(a.applicant_id) && !a.printed).length);
  const showMarkPrinted = $derived(selected.size > 0 && selectedUnprintedCount === selected.size);
  const showUnmarkPrinted = $derived(selected.size > 0 && selectedPrintedCount === selected.size);

  function toggleAll() {
    if (allSelected) {
      selected = new Set();
    } else {
      selected = new Set(applicants.map((a) => a.applicant_id));
    }
  }

  function toggleOne(id) {
    const s = new Set(selected);
    if (s.has(id)) s.delete(id);
    else s.add(id);
    selected = s;
  }

  function markAsPrinted() {
    router.post(`/admin/release/print/${sid}/mark-printed`, { applicant_ids: Array.from(selected), printed: true }, {
      onSuccess: () => (selected = new Set()),
    });
  }

  function unmarkPrinted() {
    router.post(`/admin/release/print/${sid}/mark-printed`, { applicant_ids: Array.from(selected), printed: false }, {
      onSuccess: () => (selected = new Set()),
    });
  }

  function printBulk() {
    const ids = Array.from(selected);
    if (ids.length === 1) {
      window.open(`/admin/release/print/${sid}/applicants/${ids[0]}`, '_blank', 'noopener');
    } else {
      router.visit(`/admin/release/print/${sid}/print-bulk?ids=${ids.join(',')}`);
    }
  }

  function togglePrinted(app) {
    const printed = !app.printed;
    router.post(`/admin/release/print/${sid}/mark-printed`, {
      applicant_ids: [app.applicant_id],
      printed,
    });
  }
</script>

<AuthenticatedLayout breadcrumbs={breadcrumbs}>
  <div class="space-y-6 min-w-0">
    <Card>
      <CardHeader>
        <CardTitle class="flex items-center gap-2">
          <Printer class="h-5 w-5" />
          Print result sheets — Batch
        </CardTitle>
        <CardDescription>
          Session #{session.exam_session_id} · {formatDate(session.exam_date)} · {session.room_name}. Print result sheets, then mark as printed.
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
          <Button variant="default" onclick={printBulk} disabled={printDisabled || selected.size === 0} title={printDisabled ? 'Switch to F2F or Both release mode in Settings to enable printing.' : undefined} class="min-h-[44px]">
            <Printer class="h-4 w-4 mr-2" />
            Print bulk{selected.size > 0 ? ` (${selected.size})` : ''}
          </Button>
          {#if !printDisabled && selected.size > 0}
            <div class="flex items-center gap-2">
              <label for="copies" class="text-sm text-muted-foreground">Copies</label>
              <input
                id="copies"
                type="number"
                min="1"
                max="10"
                bind:value={copies}
                class="w-16 rounded-md border border-input bg-transparent px-2 py-1 text-sm"
              />
            </div>
            <a href={`/admin/release/print/${sid}/print-bulk-pdf?ids=${Array.from(selected).join(',')}&copies=${copies}&download=1`} rel="noopener">
              <Button variant="secondary" class="min-h-[44px] gap-2">
                <Download class="h-4 w-4" />
                Download PDF
              </Button>
            </a>
            <a href={`/admin/release/print/${sid}/print-bulk-docx?ids=${Array.from(selected).join(',')}&merge=1`} rel="noopener">
              <Button variant="outline" class="min-h-[44px] gap-2">
                <FileText class="h-4 w-4" />
                Download Word (Merged)
              </Button>
            </a>
            <a href={`/admin/release/print/${sid}/print-bulk-docx?ids=${Array.from(selected).join(',')}`} rel="noopener">
              <Button variant="outline" class="min-h-[44px] gap-2">
                <FileText class="h-4 w-4" />
                Download Word (ZIP)
              </Button>
            </a>
          {/if}
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
                <Table.Head class="text-center">Action</Table.Head>
              </Table.Row>
            </Table.Header>
            <Table.Body>
              {#each applicants as app (app.applicant_id)}
                <Table.Row>
                  <Table.Cell class="px-4 py-3">
                    <button type="button" onclick={() => toggleOne(app.applicant_id)} aria-label="Toggle select">
                      {#if selected.has(app.applicant_id)}
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
                  <Table.Cell class="text-center">
                    <div class="flex justify-center gap-2">
                      <Link href={`/admin/release/print/${sid}/applicants/${app.applicant_id}`} target="_blank">
                        <Button
                          variant="outline"
                          size="sm"
                          disabled={printDisabled}
                          title={printDisabled ? 'Switch to F2F or Both release mode in Settings to enable printing.' : undefined}
                          class="h-8 px-2 text-xs"
                        >
                          <Printer class="mr-1.5 h-3.5 w-3.5" />
                          Print
                        </Button>
                      </Link>
                      {#if app.printed}
                        <Button
                          variant="ghost"
                          size="sm"
                          class="h-8 px-2 text-xs"
                          onclick={() => togglePrinted(app)}
                        >
                          Unmark printed
                        </Button>
                      {:else}
                        <Button
                          variant="outline"
                          size="sm"
                          class="h-8 px-2 text-xs"
                          onclick={() => togglePrinted(app)}
                        >
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
