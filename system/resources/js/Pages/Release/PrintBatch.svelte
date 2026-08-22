<script>
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
  import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
  import { Badge } from '@/Components/ui/badge';
  import { Button } from '@/Components/ui/button';
  import * as Card from '@/Components/ui/card';
  import * as Table from '@/Components/ui/table';
  import { usePage } from '@inertiajs/svelte';
  import { CheckSquare, Printer, Download, Square, Eye, RotateCw, CheckCircle2, AlertCircle, Sparkles } from 'lucide-svelte';
  import { csrfToken } from '@/lib/utils';
  import { onDestroy } from 'svelte';

  let { session = {}, applicants = [], template_present = true } = $props();

  const page = usePage();

  let selected = $state(new Set());
  let copies = $state(1);
  let job = $state(null);
  let isRequesting = $state(false);
  let jobError = $state(null);
  let pollTimer = null;

  onDestroy(() => {
    if (pollTimer) {
      clearInterval(pollTimer);
      pollTimer = null;
    }
  });

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

    isRequesting = true;
    const token = csrfToken() || page.props.csrf_token || '';
    try {
      const res = await fetch('/release/print', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': token,
        },
        credentials: 'same-origin',
        body: JSON.stringify({ release_ids: releaseIds, copies }),
      });
      if (!res.ok) {
        const data = await res.json().catch(() => ({}));
        jobError = data.message ?? `Print request failed (${res.status})`;
        isRequesting = false;
        return;
      }
      const data = await res.json();
      job = data;
      isRequesting = false;
      pollJob(data.id);
    } catch (err) {
      jobError = String(err);
      isRequesting = false;
    }
  }

  async function pollJob(id) {
    if (pollTimer) clearInterval(pollTimer);
    pollTimer = setInterval(async () => {
      try {
        const res = await fetch(`/printing/jobs/${id}`, {
          headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          credentials: 'same-origin',
        });
        if (!res.ok) return;
        const data = await res.json();
        job = data;
        if (data.status === 'ready' || data.status === 'failed') {
          clearInterval(pollTimer);
          pollTimer = null;
        }
      } catch (err) {
        clearInterval(pollTimer);
        pollTimer = null;
      }
    }, 1200);
  }

  async function togglePrintedSingle(app) {
    if (!app.released) return;
    const token = csrfToken() || page.props.csrf_token || '';
    await fetch('/release/print/mark-printed', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': token,
      },
      credentials: 'same-origin',
      body: JSON.stringify({ release_ids: [app.release_id], printed: !app.printed }),
    });
    window.location.reload();
  }
  async function markPrinted(printed) {
    const rows = applicants.filter((a) => selected.has(a.attempt_id) && a.released);
    const releaseIds = rows.map((a) => a.release_id);
    if (releaseIds.length === 0) return;
    const token = csrfToken() || page.props.csrf_token || '';
    await fetch('/release/print/mark-printed', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': token,
      },
      credentials: 'same-origin',
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

    <!-- Action Bar -->
    <div class="flex flex-wrap items-center gap-3 rounded-xl border border-border bg-card p-4">
      <Button variant="outline" size="sm" class="min-h-10" onclick={toggleAll}>
        {#if allSelected}<Square class="size-4 mr-2" />{:else}<CheckSquare class="size-4 mr-2" />{/if}
        {allSelected ? 'Deselect all' : 'Select all'}
      </Button>
      <Button
        variant="default"
        size="sm"
        class="min-h-10 gap-2"
        disabled={!canPrint || isRequesting || (job && (job.status === 'queued' || job.status === 'rendering'))}
        onclick={() => requestPrint(false)}
      >
        {#if isRequesting || (job && (job.status === 'queued' || job.status === 'rendering'))}
          <RotateCw class="size-4 animate-spin" />
          Generating PDF…
        {:else}
          <Printer class="size-4" />
          Print {selectedReleased.length > 0 ? `(${selectedReleased.length})` : ''}
        {/if}
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
        <p class="text-xs font-medium text-destructive">{jobError}</p>
      {/if}
    </div>

    <!-- Live Job Status & Feedback Card -->
    {#if job}
      <Card.Root class="overflow-hidden border-border/80 bg-card shadow-xs">
        <Card.Content class="p-5">
          {#if job.status === 'ready'}
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
              <div class="flex items-start gap-3.5">
                <div class="rounded-full bg-green-500/10 p-2 text-green-600 dark:text-green-400">
                  <CheckCircle2 class="size-6" />
                </div>
                <div>
                  <div class="flex items-center gap-2">
                    <h3 class="font-semibold text-foreground">Print Job #{job.id} is Ready</h3>
                    <Badge variant="default" class="bg-green-600 text-[11px] text-white hover:bg-green-600">Ready</Badge>
                  </div>
                  <p class="mt-0.5 text-sm text-muted-foreground">
                    All {job.progress_total ?? 1} result sheet(s) have been rendered into a merged PDF. Click below to download or print.
                  </p>
                </div>
              </div>

              <div class="flex flex-wrap items-center gap-2 pt-2 sm:pt-0">
                <a href={`/printing/jobs/${job.id}/download`} target="_blank" rel="noopener">
                  <Button class="min-h-10 gap-2 font-semibold">
                    <Download class="size-4" />
                    Download PDF ({job.progress_total ?? 1} sheet{job.progress_total === 1 ? '' : 's'})
                  </Button>
                </a>
                <Button variant="outline" class="min-h-10 gap-2" onclick={() => markPrinted(true)}>
                  <CheckSquare class="size-4 text-muted-foreground" />
                  Mark as printed
                </Button>
              </div>
            </div>
          {:else if job.status === 'queued' || job.status === 'rendering'}
            <div class="space-y-3">
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                  <RotateCw class="size-4 animate-spin text-primary" />
                  <h3 class="text-sm font-semibold text-foreground">
                    Generating Print Job #{job.id}…
                  </h3>
                  <Badge variant="secondary" class="text-[11px] capitalize">
                    {job.status}
                  </Badge>
                </div>
                <span class="text-xs font-mono text-muted-foreground">
                  {job.progress_done ?? 0} / {job.progress_total ?? 1} sheets
                </span>
              </div>
              <div class="h-2 w-full overflow-hidden rounded-full bg-muted">
                <div
                  class="h-full bg-primary transition-all duration-300"
                  style={`width: ${Math.max(10, Math.round(((job.progress_done ?? 0) / (job.progress_total || 1)) * 100))}%`}
                ></div>
              </div>
              <p class="text-xs text-muted-foreground">
                Rendering result sheets with active template. This updates live as background workers complete.
              </p>
            </div>
          {:else if job.status === 'failed'}
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
              <div class="flex items-start gap-3">
                <div class="rounded-full bg-destructive/10 p-2 text-destructive">
                  <AlertCircle class="size-5" />
                </div>
                <div>
                  <h3 class="text-sm font-semibold text-destructive">Print Job #{job.id} Failed</h3>
                  <p class="text-xs text-muted-foreground">{job.safe_error ?? 'PDF generation could not complete'}</p>
                </div>
              </div>
              <Button size="sm" variant="outline" class="gap-1.5" onclick={() => requestPrint(false)}>
                <RotateCw class="size-3.5" />
                Retry Print
              </Button>
            </div>
          {/if}
        </Card.Content>
      </Card.Root>
    {/if}

    <!-- Applicants Table -->
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
            <Table.Head class="px-4 py-3">Release Status</Table.Head>
            <Table.Head class="px-4 py-3">Printed Status</Table.Head>
            <Table.Head class="px-4 py-3 text-right">Actions</Table.Head>
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
              <Table.Cell class="px-4 py-3 font-mono text-xs font-medium">{app.reference}</Table.Cell>
              <Table.Cell class="px-4 py-3 font-medium">{app.name}</Table.Cell>
              <Table.Cell class="px-4 py-3">
                <Badge variant={app.released ? 'default' : 'secondary'} class="text-[11px]">
                  {app.released ? `Released (${app.mode})` : 'Not released'}
                </Badge>
              </Table.Cell>
              <Table.Cell class="px-4 py-3">
                <Badge variant={app.printed ? 'default' : 'outline'} class="text-[11px]">
                  {app.printed ? 'Printed' : 'Not printed'}
                </Badge>
              </Table.Cell>
              <Table.Cell class="px-4 py-3 text-right">
                <div class="flex items-center justify-end gap-1.5">
                  <a href={`/release/print/batches/${session.id}/applicants/${app.attempt_id}`} target="_blank" rel="noopener">
                    <Button variant="outline" size="sm" class="h-8 gap-1.5 px-2.5 text-xs font-medium" disabled={!app.released}>
                      <Eye class="size-3.5 text-muted-foreground" />
                      View
                    </Button>
                  </a>
                  {#if app.printed}
                    <Button variant="ghost" size="sm" class="h-8 px-2 text-xs text-muted-foreground" onclick={() => togglePrintedSingle(app)}>
                      Unmark
                    </Button>
                  {:else}
                    <Button variant="outline" size="sm" class="h-8 px-2 text-xs" disabled={!app.released} onclick={() => togglePrintedSingle(app)}>
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
  </div>
</StaffLayout>
