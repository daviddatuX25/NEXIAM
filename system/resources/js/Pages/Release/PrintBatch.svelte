<script>
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
  import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
  import { Badge } from '@/Components/ui/badge';
  import { Button } from '@/Components/ui/button';
  import * as Table from '@/Components/ui/table';
  import { usePage } from '@inertiajs/svelte';
  import { CheckSquare, Printer, Download, Square, Eye, RotateCw } from 'lucide-svelte';

  let { session = {}, applicants = [], template_present = true } = $props();

  const page = usePage();

  let selected = $state(new Set());
  let copies = $state(1);
  let job = $state(null);
  let jobError = $state(null);
  let pollTimer = null;

  const allSelected = $derived(applicants.length > 0 && selected.size === applicants.length);
  const selectedReleased = $derived(applicants.filter((a) => selected.has(a.attempt_id) && a.released));
  const selectedUnprinted = $derived(selectedReleased.filter((a) => !a.printed));
  const selectedPrinted = $derived(selectedReleased.filter((a) => a.printed));

  const breadcrumbs = [
    { label: 'Release', href: '/release' },
    { label: `Session ${session.code ?? session.id}` },
    { label: 'Print result sheets' },
  ];

  function toggleAll() {
    selected = allSelected ? new Set() : new Set(applicants.map((a) => a.attempt_id));
  }

  function toggleOne(id) {
    const s = new Set(selected);
    if (s.has(id)) s.delete(id);
    else s.add(id);
    selected = s;
  }

  async function requestPrint(printedOnly = false) {
    jobError = null;
    const rows = applicants.filter((a) => selected.has(a.attempt_id) && a.released && (!printedOnly || !a.printed));
    const releaseIds = rows.map((a) => a.release_id);
    if (releaseIds.length === 0) return;

    try {
      const res = await fetch('/release/print', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': $page.props.csrf_token,
        },
        body: JSON.stringify({ release_ids: releaseIds, copies }),
      });
      if (!res.ok) {
        const data = await res.json().catch(() => ({}));
        jobError = data.message ?? `Print request failed (${res.status})`;
        return;
      }
      const data = await res.json();
      job = data;
      pollJob(data.id);
    } catch (err) {
      jobError = String(err);
    }
  }

  async function pollJob(id) {
    clearInterval(pollTimer);
    pollTimer = setInterval(async () => {
      try {
        const res = await fetch(`/printing/jobs/${id}`, {
          headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        const data = await res.json();
        job = data;
        if (data.status === 'ready' || data.status === 'failed') {
          clearInterval(pollTimer);
          pollTimer = null;
        }
      } catch {
        clearInterval(pollTimer);
        pollTimer = null;
      }
    }, 1500);
  }

  async function markPrinted(printed) {
    const rows = applicants.filter((a) => selected.has(a.attempt_id) && a.released);
    const releaseIds = rows.map((a) => a.release_id);
    if (releaseIds.length === 0) return;
    await fetch('/release/print/mark-printed', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': $page.props.csrf_token,
      },
      body: JSON.stringify({ release_ids: releaseIds, printed }),
    });
    window.location.reload();
  }

  const canPrint = $derived(template_present && selectedReleased.length > 0);

  const previewUrl = $derived(
    selectedReleased.length > 0
      ? `/release/print/batches/${session.id}/preview?ids=${selectedReleased.map((a) => a.release_id).join(',')}`
      : null,
  );
</script>

<svelte:head><title>Print result sheets · Release</title></svelte:head>

<StaffLayout {breadcrumbs}>
  <div class="min-w-0 space-y-6 p-4 sm:p-6">
    <PageHeaderBar
      title="Print result sheets — Batch"
      description={`Session ${session.code ?? ''} · ${session.exam_date ?? ''} · ${session.room_name ?? '—'}. Select released applicants, generate PDFs, then mark them printed.`}
    />

    <div class="flex flex-wrap items-center gap-3 rounded-xl border border-border bg-card p-4">
      <Button variant="outline" size="sm" class="min-h-10" onclick={toggleAll}>
        {#if allSelected}<Square class="size-4 mr-2" />{:else}<CheckSquare class="size-4 mr-2" />{/if}
        {allSelected ? 'Deselect all' : 'Select all'}
      </Button>
      <Button variant="default" size="sm" class="min-h-10 gap-2" disabled={!canPrint} onclick={() => requestPrint(false)}>
        <Printer class="size-4" />
        Print {selectedReleased.length > 0 ? `(${selectedReleased.length})` : ''}
      </Button>
      <div class="flex items-center gap-2">
        <label for="copies" class="text-xs text-muted-foreground">Copies</label>
        <input id="copies" type="number" min="1" max="10" bind:value={copies} class="w-16 rounded-md border border-input bg-transparent px-2 py-1 text-sm" />
      </div>
      {#if previewUrl}
        <a href={previewUrl} target="_blank" rel="noopener">
          <Button variant="outline" size="sm" class="min-h-10 gap-2">
            <Eye class="size-4" />
            Preview stack
          </Button>
        </a>
      {/if}
      {#if selectedUnprinted.length === selectedReleased.length && selectedReleased.length > 0}
        <Button variant="outline" size="sm" class="min-h-10" onclick={() => markPrinted(true)}>
          Mark {selectedReleased.length} as printed
        </Button>
      {:else if selectedPrinted.length === selectedReleased.length && selectedReleased.length > 0}
        <Button variant="ghost" size="sm" class="min-h-10" onclick={() => markPrinted(false)}>
          Unmark {selectedReleased.length} printed
        </Button>
      {/if}
      {#if jobError}
        <p class="text-xs text-destructive">{jobError}</p>
      {/if}
    </div>

    {#if job}
      <div class="rounded-xl border border-border bg-card p-4">
        <div class="flex flex-wrap items-center gap-3">
          <p class="text-sm font-medium">Print job #{job.id}</p>
          <Badge variant={job.status === 'ready' ? 'default' : job.status === 'failed' ? 'destructive' : 'secondary'}>
            {job.status}{job.status === 'rendering' ? ` (${job.progress_done}/${job.progress_total})` : ''}
          </Badge>
          {#if job.status === 'ready'}
            <a href={`/printing/jobs/${job.id}/download`} target="_blank" rel="noopener">
              <Button size="sm" class="h-8 gap-1.5 text-xs">
                <Download class="size-3.5" />
                Download PDF
              </Button>
            </a>
          {:else if job.status === 'failed'}
            <Button size="sm" variant="outline" class="h-8 gap-1.5 text-xs" onclick={() => requestPrint(false)}>
              <RotateCw class="size-3.5" />
              Retry
            </Button>
            <p class="text-xs text-destructive">{job.safe_error ?? 'render_failed'}</p>
          {/if}
        </div>
      </div>
    {/if}

    <div class="overflow-hidden rounded-xl border border-border bg-card">
      <Table.Root class="w-full min-w-[640px]">
        <Table.Header class="bg-muted/50">
          <Table.Row>
            <Table.Head class="w-10 px-4 py-3">
              <button type="button" onclick={toggleAll} aria-label={allSelected ? 'Deselect all' : 'Select all'} class="p-1">
                {#if allSelected}<CheckSquare class="size-5 text-primary" />{:else}<Square class="size-5 text-muted-foreground" />{/if}
              </button>
            </Table.Head>
            <Table.Head class="px-4 py-3">Reference</Table.Head>
            <Table.Head class="px-4 py-3">Name</Table.Head>
            <Table.Head class="px-4 py-3">Release</Table.Head>
            <Table.Head class="px-4 py-3">Printed</Table.Head>
          </Table.Row>
        </Table.Header>
        <Table.Body>
          {#each applicants as app (app.attempt_id)}
            <Table.Row class={app.released ? '' : 'opacity-60'}>
              <Table.Cell class="px-4 py-3">
                <button type="button" disabled={!app.released} onclick={() => toggleOne(app.attempt_id)} aria-label="Toggle select" class="p-1 disabled:opacity-30">
                  {#if selected.has(app.attempt_id)}<CheckSquare class="size-5 text-primary" />{:else}<Square class="size-5 text-muted-foreground" />{/if}
                </button>
              </Table.Cell>
              <Table.Cell class="px-4 py-3 font-mono text-xs">{app.reference}</Table.Cell>
              <Table.Cell class="px-4 py-3">{app.name}</Table.Cell>
              <Table.Cell class="px-4 py-3">
                <Badge variant={app.released ? 'default' : 'muted'}>{app.released ? `Released (${app.mode})` : 'Not released'}</Badge>
              </Table.Cell>
              <Table.Cell class="px-4 py-3">
                <Badge variant={app.printed ? 'default' : 'muted'}>{app.printed ? 'Printed' : 'Not printed'}</Badge>
              </Table.Cell>
            </Table.Row>
          {/each}
        </Table.Body>
      </Table.Root>
    </div>
  </div>
</StaffLayout>
