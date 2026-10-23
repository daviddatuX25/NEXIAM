<script>
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
  import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
  import { PrintJobCard, PrintJobHistory, MarkPrintedDialog } from '@/Components/ResultSheet';
  import { Badge } from '@/Components/ui/badge';
  import { Button } from '@/Components/ui/button';
  import { Input } from '@/Components/ui/input';
  import * as Dialog from '@/Components/ui/dialog';
  import * as Table from '@/Components/ui/table';
  import { usePage, router } from '@inertiajs/svelte';
  import { CheckSquare, Printer, Download, Square, Eye, RotateCw, ArrowLeft, Search, ArrowUp, ArrowDown, FileText } from 'lucide-svelte';
  import { onMount, onDestroy } from 'svelte';
  import { csrfToken, newIdempotencyKey } from '@/lib/utils';
  let {
    session = {},
    applicants = [],
    filters = {},
    sort_options = [],
    template_present = true,
    can_prepare = false,
    job_history = [],
  } = $props();

  const page = usePage();
  const token = $derived(page.props.csrf_token || csrfToken());

  let applicantSearch = $state(String(filters?.search ?? ''));
  let applicantSort = $state(String(filters?.sort ?? 'reference'));
  let applicantSortDir = $state(String(filters?.dir ?? 'asc'));

  const filteredApplicants = $derived.by(() => {
    let list = applicants ?? [];
    const q = applicantSearch.trim().toLowerCase();
    if (q) {
      list = list.filter((a) =>
        (a.name ?? '').toLowerCase().includes(q) ||
        (a.reference ?? '').toLowerCase().includes(q)
      );
    }
    return [...list].sort((a, b) => {
      let cmp = 0;
      if (applicantSort === 'name') {
        cmp = (a.name ?? '').localeCompare(b.name ?? '');
      } else if (applicantSort === 'reference') {
        cmp = (a.reference ?? '').localeCompare(b.reference ?? '');
      } else if (applicantSort === 'printed') {
        cmp = (Number(a.printed) - Number(b.printed));
      } else if (applicantSort === 'document_ready') {
        cmp = (Number(a.document_ready) - Number(b.document_ready));
      }
      return applicantSortDir === 'asc' ? cmp : -cmp;
    });
  });

  const eligibleUnpreparedApplicants = $derived(filteredApplicants.filter((a) => a.scoring_ready && !a.document_ready && can_prepare && template_present));
  const printableApplicants = $derived(filteredApplicants.filter((a) => a.document_ready && a.document_id));
  let selected = $state(new Set(printableApplicants.filter((a) => !a.printed).map((a) => a.attempt_id)));
  let copies = $state(1);
  let job = $state(null);
  let isRequesting = $state(false);
  let jobError = $state(null);
  let pollTimer = null;

  // Mark Printed Dialog state
  let showMarkDialog = $state(false);
  let markDialogDocumentIds = $state([]);
  let markDialogCount = $state(1);
  let preparingAttemptIds = $state(new Set());
  let isBulkPreparing = $state(false);
  let prepareError = $state(null);
  let prepareConfirmationOpen = $state(false);
  let preparationRequest = $state(null);

  onDestroy(() => {
    if (pollTimer) {
      clearInterval(pollTimer);
      pollTimer = null;
    }
  });

  onMount(() => {
    const activeJob = job_history?.find((j) => ['queued', 'rendering'].includes(j.status));
    if (activeJob && !job) {
      job = activeJob;
      pollJob(activeJob.id);
    }
  });

  const allSelected = $derived(filteredApplicants.length > 0 && filteredApplicants.every((a) => selected.has(a.attempt_id)));
  const selectedApplicants = $derived(filteredApplicants.filter((a) => selected.has(a.attempt_id)));
  const selectedUnprinted = $derived(selectedApplicants.filter((a) => a.document_ready && a.document_id && !a.printed));
  const selectedEligibleUnprepared = $derived(selectedApplicants.filter((a) => a.scoring_ready && !a.document_ready && can_prepare && template_present));
  const rosterCounts = $derived({
    roster: (applicants ?? []).length,
    scored: (applicants ?? []).filter((a) => a.scoring_ready).length,
    eligibleUnprepared: (applicants ?? []).filter((a) => a.scoring_ready && !a.document_ready && can_prepare && template_present).length,
    prepared: (applicants ?? []).filter((a) => a.document_ready).length,
    printableUnprinted: (applicants ?? []).filter((a) => a.document_ready && a.document_id && !a.printed).length,
    printed: (applicants ?? []).filter((a) => a.printed).length,
  });

  const breadcrumbs = [
    { label: 'Printing of Results', href: '/release' },
    { label: session.code ?? `Session ${session.exam_session_id}` },
    { label: 'Print Result Sheets' },
  ];

  function toggleAll() {
    selected = allSelected ? new Set() : new Set(filteredApplicants.map((a) => a.attempt_id));
  }

  function toggleOne(id) {
    const s = new Set(selected);
    if (s.has(id)) s.delete(id);
    else s.add(id);
    selected = s;
  }

  async function requestPrint(printedOnly = false) {
    jobError = null;
    const rows = applicants.filter((a) => selected.has(a.attempt_id) && a.document_ready && a.document_id && (!printedOnly || !a.printed));
    const documentIds = rows.map((a) => a.document_id);
    if (documentIds.length === 0) return;

    isRequesting = true;
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
        body: JSON.stringify({ document_ids: documentIds, copies }),
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
          if (data.status === 'ready') {
            router.reload({
              preserveScroll: true,
              only: ['applicants', 'job_history'],
              onSuccess: () => {
                selected = new Set(
                  (applicants ?? []).filter((a) => a.document_ready && a.document_id && !a.printed).map((a) => a.attempt_id)
                );
              },
            });
          }
        }
      } catch {
        clearInterval(pollTimer);
        pollTimer = null;
      }
    }, 1200);
  }

  function promptMarkForReadyJob() {
    if (!job) return;
    const documentIds = applicants.filter((a) => selected.has(a.attempt_id) && a.document_ready && a.document_id).map((a) => a.document_id);
    markDialogDocumentIds = documentIds;
    markDialogCount = documentIds.length;
    showMarkDialog = true;
  }

  function promptMarkSelected() {
    const documentIds = selectedApplicants.filter((a) => a.document_ready && a.document_id).map((a) => a.document_id);
    markDialogDocumentIds = documentIds;
    markDialogCount = documentIds.length;
    showMarkDialog = true;
  }

  async function togglePrintedSingle(app) {
    if (!app.document_id) return;
    let reason;
    if (app.printed) {
      reason = window.prompt('Reason for correcting the physical print confirmation?');
      if (!reason?.trim()) return;
    }
    await fetch('/release/print/mark-printed', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': token,
      },
      credentials: 'same-origin',
      body: JSON.stringify({ document_ids: [app.document_id], printed: !app.printed, idempotency_key: newIdempotencyKey(), ...(reason ? { reason } : {}) }),
    });
    router.reload();
  }

  function prepareSingle(app) {
    if (!app?.attempt_id || preparingAttemptIds.has(app.attempt_id)) return;
    preparationRequest = {
      applicants: [{ attempt_id: app.attempt_id, name: app.name, reference: app.reference }],
      bulk: false,
    };
    prepareConfirmationOpen = true;
  }

  function prepareAllUnready() {
    if (isBulkPreparing || eligibleUnpreparedApplicants.length === 0) return;
    preparationRequest = {
      applicants: eligibleUnpreparedApplicants.map(({ attempt_id, name, reference }) => ({ attempt_id, name, reference })),
      bulk: true,
    };
    prepareConfirmationOpen = true;
  }

  function prepareSelected() {
    if (isBulkPreparing || selectedEligibleUnprepared.length === 0) return;
    preparationRequest = {
      applicants: selectedEligibleUnprepared.map(({ attempt_id, name, reference }) => ({ attempt_id, name, reference })),
      bulk: true,
    };
    prepareConfirmationOpen = true;
  }

  async function confirmPreparation() {
    if (!prepareConfirmationOpen || !preparationRequest) return;
    const request = preparationRequest;
    prepareConfirmationOpen = false;
    preparationRequest = null;

    if (!request.bulk) {
      const attemptId = request.applicants[0].attempt_id;
      preparingAttemptIds = new Set([...preparingAttemptIds, attemptId]);
      router.post(`/release/${attemptId}/prepare`, {}, {
        preserveScroll: true,
        onFinish: () => {
          const done = new Set(preparingAttemptIds);
          done.delete(attemptId);
          preparingAttemptIds = done;
        },
      });
      return;
    }

    isBulkPreparing = true;
    prepareError = null;
    const failures = [];

    for (const app of request.applicants) {
      if (!app?.attempt_id) continue;
      try {
        const res = await fetch(`/release/${app.attempt_id}/prepare`, {
          method: 'POST',
          headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': token,
          },
          credentials: 'same-origin',
        });
        if (!res.ok) {
          const errData = await res.json().catch(() => ({}));
          failures.push(`${app.name || app.reference}: ${errData.message || res.statusText}`);
        }
      } catch (err) {
        failures.push(`${app.name || app.reference}: Network error`);
      }
    }

    if (failures.length > 0) {
      prepareError = `Failed to prepare ${failures.length} document(s):\n` + failures.slice(0, 3).join('\n') + (failures.length > 3 ? `\n...and ${failures.length - 3} more` : '');
    }

    router.reload({
      preserveScroll: true,
      onFinish: () => {
        isBulkPreparing = false;
        selected = new Set(
          (applicants ?? []).filter((a) => a.document_ready && a.document_id && !a.printed).map((a) => a.attempt_id)
        );
      },
    });
  }
  const canPrint = $derived(template_present && selectedUnprinted.length > 0);

  const previewUrl = $derived(
    selectedUnprinted.length > 0
      ? `/release/print/sessions/${session.exam_session_id}/preview?document_ids=${selectedUnprinted.map((a) => a.document_id).join(',')}`
      : null,
  );

</script>


<svelte:head><title>Print result sheets · Release</title></svelte:head>

<StaffLayout {breadcrumbs}>
  <div class="min-w-0 space-y-6 p-4 sm:p-6">
    <PageHeaderBar
      title="Print result sheets — Batch"
      description={`Session ${session.code ?? ''} · ${session.exam_date ?? ''} · ${session.room_name ?? '—'}. Select prepared result documents, generate PDFs, and confirm physical printing.`}
    >
      {#snippet actions()}
        <Button variant="outline" size="sm" class="gap-1.5" onclick={() => router.visit('/release')}>
          <ArrowLeft class="size-4" />
          Back to release
        </Button>
      {/snippet}
    </PageHeaderBar>
    <div class="flex flex-wrap items-center gap-2 text-xs">
      <Badge variant={session.status === 'finalized' ? 'default' : 'secondary'}>
        Batch grading: {session.status === 'finalized' ? 'Finalized' : session.status === 'review' ? 'Review' : 'Open'}
      </Badge>
      <span class="text-muted-foreground">Applicant score completeness and preparation remain individual.</span>
    </div>

    <!-- Toolbar -->
    <div class="flex flex-wrap items-center gap-3 rounded-xl border border-border bg-card p-4 shadow-xs">
      <Button variant="outline" size="sm" class="min-h-10" onclick={toggleAll}>
        {#if allSelected}<Square class="size-4 mr-2" />{:else}<CheckSquare class="size-4 mr-2" />{/if}
        {allSelected ? 'Deselect all' : 'Select all'}
      </Button>
      <Button
        variant="outline"
        size="sm"
        class="min-h-10"
        onclick={() => (selected = new Set(printableApplicants.filter((a) => !a.printed).map((a) => a.attempt_id)))}
      >
        Select All Unprinted
      </Button>
      {#if eligibleUnpreparedApplicants.length > 0}
        <Button
          variant="outline"
          size="sm"
          class="min-h-10"
          onclick={() => (selected = new Set(eligibleUnpreparedApplicants.map((a) => a.attempt_id)))}
        >
          Select All Unprepared
        </Button>
      {/if}
      <Button variant="outline" size="sm" class="min-h-10" onclick={() => (selected = new Set())}>
        Deselect All
      </Button>
      <Button
        variant="default"
        size="sm"
        class="min-h-10 gap-2 font-semibold"
        disabled={!canPrint || isRequesting || (job && (job.status === 'queued' || job.status === 'rendering'))}
        onclick={() => requestPrint(true)}
      >
        {#if isRequesting || (job && (job.status === 'queued' || job.status === 'rendering'))}
          <RotateCw class="size-4 animate-spin" />
          Rendering…
        {:else}
          <Printer class="size-4" />
          Print {selectedUnprinted.length > 0 ? `(${selectedUnprinted.length})` : ''}
        {/if}
      </Button>
      <div class="flex items-center gap-2 rounded-lg border border-border/80 bg-muted/20 px-3 py-1.5 text-xs">
        <label for="copies" class="font-medium text-muted-foreground">Copies</label>
        <input
          id="copies"
          type="number"
          min="1"
          max="10"
          bind:value={copies}
          class="w-12 rounded border border-input bg-card px-2 py-0.5 font-mono text-xs font-semibold text-center"
        />
      </div>

      {#if previewUrl}
        <a href={previewUrl} target="_blank" rel="noopener">
          <Button variant="outline" size="sm" class="min-h-10 gap-1.5">
            <Eye class="size-4 text-muted-foreground" />
            Preview stack
          </Button>
        </a>
      {/if}

      {#if selectedApplicants.length > 0}
        <Button variant="outline" size="sm" class="min-h-10 gap-1.5" onclick={promptMarkSelected}>
          <CheckSquare class="size-4 text-muted-foreground" />
          Mark {selectedApplicants.length} as printed…
        </Button>
      {/if}

      {#if can_prepare && template_present}
        {#if selectedEligibleUnprepared.length > 0}
          <Button
            variant="default"
            size="sm"
            class="min-h-10 gap-1.5 font-semibold"
            disabled={isBulkPreparing}
            onclick={prepareSelected}
          >
            {#if isBulkPreparing}
              <RotateCw class="size-4 animate-spin" />
              Preparing…
            {:else}
              <FileText class="size-4" />
              Prepare {selectedEligibleUnprepared.length} selected
            {/if}
          </Button>
        {/if}
        {#if eligibleUnpreparedApplicants.length > 0 && selectedEligibleUnprepared.length !== eligibleUnpreparedApplicants.length}
          <Button
            variant="outline"
            size="sm"
            class="min-h-10 gap-1.5"
            disabled={isBulkPreparing}
            onclick={prepareAllUnready}
          >
            {#if isBulkPreparing}
              <RotateCw class="size-4 animate-spin" />
              Preparing…
            {:else}
              <FileText class="size-4 text-muted-foreground" />
              Prepare {eligibleUnpreparedApplicants.length} eligible
            {/if}
          </Button>
        {/if}
      {/if}

      {#if prepareError}
        <p class="w-full text-xs font-medium text-destructive whitespace-pre-line">{prepareError}</p>
      {/if}

      {#if jobError}
        <p class="text-xs font-medium text-destructive">{jobError}</p>
      {/if}
    </div>

    <dl class="grid grid-cols-2 gap-3 rounded-xl border border-border bg-card p-4 text-xs sm:grid-cols-3 lg:grid-cols-6">
      <div><dt class="text-muted-foreground">Roster</dt><dd class="mt-1 font-semibold">{rosterCounts.roster}</dd></div>
      <div><dt class="text-muted-foreground">Scored</dt><dd class="mt-1 font-semibold">{rosterCounts.scored}</dd></div>
      <div><dt class="text-muted-foreground">Eligible · not prepared</dt><dd class="mt-1 font-semibold">{rosterCounts.eligibleUnprepared}</dd></div>
      <div><dt class="text-muted-foreground">Prepared</dt><dd class="mt-1 font-semibold">{rosterCounts.prepared}</dd></div>
      <div><dt class="text-muted-foreground">Prepared · not printed</dt><dd class="mt-1 font-semibold">{rosterCounts.printableUnprinted}</dd></div>
      <div><dt class="text-muted-foreground">Confirmed printed</dt><dd class="mt-1 font-semibold">{rosterCounts.printed}</dd></div>
    </dl>

    <!-- Active Job Status Card -->
    {#if job}
      <PrintJobCard
        {job}
        {isRequesting}
        onretry={() => requestPrint(false)}
        onmarkprinted={promptMarkForReadyJob}
      />
    {/if}

    <!-- Examinee Search & Sort Bar -->
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border bg-card p-4 shadow-xs">
      <div class="relative min-w-[240px] flex-1 max-w-sm">
        <Search class="pointer-events-none absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
        <Input
          type="search"
          placeholder="Search examinee name or reference…"
          bind:value={applicantSearch}
          class="h-9 w-full pl-8 font-mono text-xs"
        />
      </div>
      <div class="flex items-center gap-2">
        <label for="applicant-sort" class="text-xs font-medium text-muted-foreground">Sort</label>
        <select
          id="applicant-sort"
          bind:value={applicantSort}
          class="h-9 rounded-md border border-input bg-background px-2.5 text-xs font-medium text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
        >
          <option value="reference">Reference Code</option>
          <option value="name">Applicant Name</option>
          <option value="printed">Print Status</option>
          <option value="document_ready">Document Status</option>
        </select>
        <Button
          type="button"
          variant="outline"
          size="sm"
          class="h-9 px-2 text-xs"
          onclick={() => (applicantSortDir = applicantSortDir === 'asc' ? 'desc' : 'asc')}
          aria-label={applicantSortDir === 'asc' ? 'Ascending' : 'Descending'}
        >
          {#if applicantSortDir === 'asc'}
            <ArrowUp class="size-3.5 mr-1" /> Asc
          {:else}
            <ArrowDown class="size-3.5 mr-1" /> Desc
          {/if}
        </Button>
      </div>
    </div>

    <!-- Applicants Table -->
    <div class="overflow-hidden rounded-xl border border-border bg-card shadow-xs">
      <Table.Root class="w-full min-w-[800px]">
        <Table.Header class="bg-muted/50">
          <Table.Row>
            <Table.Head class="w-10 px-4 py-3">
              <button type="button" onclick={toggleAll} aria-label={allSelected ? 'Deselect all' : 'Select all'} class="p-1">
                {#if allSelected}<CheckSquare class="size-5 text-primary" />{:else}<Square class="size-5 text-muted-foreground" />{/if}
              </button>
            </Table.Head>
            <Table.Head class="px-4 py-3 font-semibold">Reference</Table.Head>
            <Table.Head class="px-4 py-3 font-semibold">Name</Table.Head>
            <Table.Head class="px-4 py-3 font-semibold">Scoring Status</Table.Head>
            <Table.Head class="px-4 py-3 font-semibold">Document Status</Table.Head>
            <Table.Head class="px-4 py-3 font-semibold">Print Status</Table.Head>
            <Table.Head class="px-4 py-3 text-right font-semibold">Actions</Table.Head>
          </Table.Row>
        </Table.Header>
        <Table.Body>
          {#if filteredApplicants.length === 0}
            <Table.Row>
              <Table.Cell colspan={7} class="py-8 text-center text-sm text-muted-foreground">
                {#if applicantSearch.trim()}
                  No examinees match "{applicantSearch}".
                {:else}
                  No examinees found in this examination session.
                {/if}
              </Table.Cell>
            </Table.Row>
          {:else}
            {#each filteredApplicants as app (app.attempt_id)}
              <Table.Row>
                <Table.Cell class="px-4 py-3">
                  <button type="button" onclick={() => toggleOne(app.attempt_id)} aria-label="Toggle select" class="p-1">
                    {#if selected.has(app.attempt_id)}<CheckSquare class="size-5 text-primary" />{:else}<Square class="size-5 text-muted-foreground" />{/if}
                  </button>
                </Table.Cell>
                <Table.Cell class="px-4 py-3 font-mono text-xs font-medium">{app.reference}</Table.Cell>
                <Table.Cell class="px-4 py-3 font-medium">{app.name}</Table.Cell>
                <Table.Cell class="px-4 py-3">
                  <Badge variant={app.scoring_ready ? 'default' : 'destructive'} class="text-[11px]">
                    {app.scoring_ready ? 'Scored' : 'Scoring incomplete'}
                  </Badge>
                  {#if !app.scoring_ready && app.scoring_reason}
                    <p class="mt-1 max-w-56 text-xs text-muted-foreground">{app.scoring_reason}</p>
                  {/if}
                </Table.Cell>
                <Table.Cell class="px-4 py-3">
                  <Badge variant={app.document_ready ? 'default' : 'secondary'} class="text-[11px]">
                    {app.document_ready ? 'Prepared' : 'Not prepared'}
                  </Badge>
                </Table.Cell>
                <Table.Cell class="px-4 py-3">
                  <Badge variant={app.printed ? 'default' : 'outline'} class="text-[11px]">
                    {app.printed ? 'Printed' : 'Not printed'}
                  </Badge>
                </Table.Cell>
                <Table.Cell class="px-4 py-3 text-right">
                  <div class="flex items-center justify-end gap-1.5">
                    <a href={`/release/print/sessions/${session.exam_session_id}/applicants/${app.attempt_id}`} target="_blank" rel="noopener">
                      <Button variant="outline" size="sm" class="h-8 gap-1 px-2.5 text-xs" disabled={!app.document_ready}>
                        <Eye class="size-3.5 text-muted-foreground" />
                        View
                      </Button>
                    </a>
                    {#if app.document_ready}
                      {#if app.printed}
                        <Button variant="ghost" size="sm" class="h-8 px-2 text-xs text-muted-foreground" onclick={() => togglePrintedSingle(app)}>
                          Correct print
                        </Button>
                      {:else}
                        <Button variant="outline" size="sm" class="h-8 px-2 text-xs" onclick={() => togglePrintedSingle(app)}>
                          Mark printed
                        </Button>
                      {/if}
                    {:else if can_prepare && template_present && app.scoring_ready}
                      <Button
                        variant="outline"
                        size="sm"
                        class="h-8 gap-1 px-2.5 text-xs"
                        disabled={preparingAttemptIds.has(app.attempt_id)}
                        onclick={() => prepareSingle(app)}
                      >
                        <FileText class="size-3.5 text-muted-foreground" />
                        {preparingAttemptIds.has(app.attempt_id) ? 'Preparing…' : 'Prepare'}
                      </Button>
                    {/if}
                  </div>
                </Table.Cell>
              </Table.Row>
            {/each}
          {/if}
        </Table.Body>
      </Table.Root>
    </div>

    <!-- Print History -->
    {#if job_history?.length > 0}
      <PrintJobHistory history={job_history} />
    {/if}
  </div>

  <Dialog.Root bind:open={prepareConfirmationOpen}>
    <Dialog.Content class="sm:max-w-md">
      <Dialog.Header>
        <Dialog.Title>Prepare and lock individual results?</Dialog.Title>
        <Dialog.Description>
          This prepares {preparationRequest?.applicants.length ?? 0} document{preparationRequest?.applicants.length === 1 ? '' : 's'} and locks each selected applicant's current scores against routine edits.
          Other examinees in the batch can remain unfinished.
        </Dialog.Description>
      </Dialog.Header>
      <Dialog.Footer>
        <Button type="button" variant="outline" onclick={() => { prepareConfirmationOpen = false; preparationRequest = null; }}>Cancel</Button>
        <Button type="button" onclick={confirmPreparation}>Prepare documents</Button>
      </Dialog.Footer>
    </Dialog.Content>
  </Dialog.Root>

  <!-- Mark as Printed Modal Dialog -->
  <MarkPrintedDialog
    open={showMarkDialog}
    documentIds={markDialogDocumentIds}
    count={markDialogCount}
    onclose={() => (showMarkDialog = false)}
    onmarked={() => router.reload()}
  />
</StaffLayout>
