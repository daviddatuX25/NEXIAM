<script>
  import { onDestroy, untrack } from 'svelte';
  import { usePage } from '@inertiajs/svelte';
  import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
  } from '@/Components/ui/dialog';
  import { Button } from '@/Components/ui/button';
  import { Badge } from '@/Components/ui/badge';
  import { csrfHeaders, csrfToken } from '@/lib/utils.js';
  import {
    Printer,
    Download,
    CheckSquare,
    CheckCircle2,
    RotateCw,
    AlertCircle,
    FileText,
    Users,
    Layers,
  } from 'lucide-svelte';
  import { savePrintJob, getCachedJob, markJobPrinted } from '@/lib/printQueue.js';
  let {
    open = $bindable(false),
    template = null,
    templates = [],
    attemptIds = [],
    sessionId = null,
    cycleId = null,
    academicCycleId = null,
    programCode = null,
    departmentCode = null,
    autoStartSingle = false,
    onClose = null,
  } = $props();

  const page = usePage();
  const currentActorId = $derived(page?.props?.auth?.user?.id ?? null);
  const currentInstitutionId = $derived(page?.props?.auth?.user?.actor_scope?.institution_id ?? page?.props?.institution?.id ?? null);


  const availableTemplates = $derived(
    Array.isArray(templates) && templates.length > 0
      ? templates
      : template
        ? [template]
        : [],
  );

  let selectedTemplateId = $state(null);

  let resolvedGrain = $state(null);
  let scopeOptions = $state({ applicants: [], sessions: [], items: [], cycles: [], programs: [] });
  let scopeOptionsLoading = $state(false);
  let scopeOptionsError = $state('');
  let selectedAttemptIds = $state([]);
  let selectedSessionId = $state(null);
  let selectedCycleId = $state(null);
  let selectedProgramCode = $state('');
  const selectedTemplate = $derived(
    availableTemplates.find((item) => String(item.id) === String(selectedTemplateId)) ??
      template ??
      availableTemplates[0] ??
      null,
  );

  const effectiveGrain = $derived(selectedTemplate?.grain ?? 'student');

  const grainBadgeLabel = $derived(
    (resolvedGrain ?? effectiveGrain) === 'cycle_program'
      ? 'Cycle / Program Grain'
      : (resolvedGrain ?? effectiveGrain) === 'session'
        ? 'Session Grain'
        : 'Student Grain',
  );

  const boundPackLabel = $derived(
    selectedTemplate?.pack_title ||
      selectedTemplate?.surface_title ||
      selectedTemplate?.surface_key ||
      'registrar.admission_slip',
  );

  const resolvedAttemptIds = $derived(
    Array.isArray(attemptIds) && attemptIds.length > 0 ? attemptIds : selectedAttemptIds,
  );
  const resolvedSessionId = $derived(sessionId ?? selectedSessionId);
  const resolvedCycleId = $derived(selectedCycleId ?? academicCycleId ?? cycleId);

  const subjectCountSummary = $derived(
    effectiveGrain === 'cycle_program'
      ? `Cycle / Program scope · ${departmentCode ? `Department ${departmentCode} · ` : ''}${selectedProgramCode || programCode || 'All Authorized Programs'}${resolvedCycleId ? ` · Cycle #${resolvedCycleId}` : ''}`
      : effectiveGrain === 'session'
        ? 'Session scope'
        : `${resolvedAttemptIds.length} applicant${resolvedAttemptIds.length === 1 ? '' : 's'}`,
  );
  const canGenerate = $derived(
    Boolean(selectedTemplate) &&
      (effectiveGrain === 'student'
        ? resolvedAttemptIds.length > 0
        : effectiveGrain === 'session'
          ? resolvedSessionId !== null && resolvedSessionId !== ''
          : resolvedCycleId !== null && resolvedCycleId !== ''),
  );

  let status = $state('idle'); // 'idle' | 'rendering' | 'processing' | 'ready' | 'error'
  let progressPercent = $state(0);
  let statusLabel = $state('');
  let pdfUrl = $state('');
  let filename = $state('document.pdf');
  let errorMessage = $state(null);
  let settledCount = $state(0);
  let totalCount = $state(0);
  let physicallyPrinted = $state(false);
  let receiptId = $state(null);
  let receiptActorName = $state(null);
  let receiptError = $state(null);
  let markingPrinted = $state(false);
  let physicallyPrintedAt = $state(null);
  let iframeEl = $state(null);
  let jobId = $state(null);
  

  let blobUrl = null;
  let activeRunToken = 0;
  let jobPollController = null;

  function stopJobPolling() {
    jobPollController?.abort();
    jobPollController = null;
  }

  function abortableDelay(milliseconds, signal) {
    return new Promise((resolve, reject) => {
      if (signal.aborted) {
        const error = new Error('Polling stopped');
        error.name = 'AbortError';
        reject(error);
        return;
      }

      const cleanup = () => signal.removeEventListener('abort', onAbort);
      const timer = setTimeout(() => {
        cleanup();
        resolve();
      }, milliseconds);
      const onAbort = () => {
        clearTimeout(timer);
        cleanup();
        const error = new Error('Polling stopped');
        error.name = 'AbortError';
        reject(error);
      };
      signal.addEventListener('abort', onAbort, { once: true });
    });
  }

  function requestHeaders() {
    return {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      ...csrfHeaders(csrfToken()),
    };
  }

  function updateProgress(progress = {}, stage = null) {
    settledCount = Number(progress.settled_count ?? 0);
    totalCount = Number(progress.total_count ?? totalCount);
    progressPercent = totalCount > 0 ? Math.min(100, Math.round((settledCount / totalCount) * 100)) : 0;
    const stageLabels = {
      resolving: 'Resolving records',
      filling: 'Filling document templates',
      converting: 'Converting documents',
      assembling: 'Assembling PDF',
    };
    statusLabel = stageLabels[stage] ?? 'Queued for processing';
  }

  function setReadyPdf(data, targetTemplate) {
    filename =
      data?.filename ||
      `${(targetTemplate.name || targetTemplate.surface_key || 'document')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')}.pdf`;
    settledCount = Number(data?.settled_count ?? settledCount);
    totalCount = Number(data?.total_count ?? totalCount);
    if (data?.pdf_base64) {
      pdfUrl = base64ToPdfUrl(
        data.pdf_base64,
        targetTemplate,
        data?.job_id ? String(data.job_id) : null,
      );
    }
    progressPercent = 100;
    statusLabel = 'Ready (100%)';
    status = 'ready';
  }

  async function pollJob(id, targetTemplate, runToken) {
    stopJobPolling();
    const controller = new AbortController();
    jobPollController = controller;

    try {
      while (!controller.signal.aborted && runToken === activeRunToken) {
        await abortableDelay(1000, controller.signal);
        const response = await fetch(`/document-printing/jobs/${encodeURIComponent(id)}`, {
          headers: requestHeaders(),
          credentials: 'same-origin',
          signal: controller.signal,
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
          throw new Error(data?.message || 'Could not read document print job status.');
        }
        if (runToken !== activeRunToken) return;

        updateProgress(data.progress, data.stage);
        if (data.status === 'ready') {
          const downloadResponse = await fetch(
            `/document-printing/jobs/${encodeURIComponent(id)}/download`,
            {
              headers: { Accept: 'application/pdf' },
              credentials: 'same-origin',
              signal: controller.signal,
            },
          );
          if (!downloadResponse.ok) {
            throw new Error('The completed document PDF could not be downloaded.');
          }

          const pdfBlob = await downloadResponse.blob();
          if (runToken !== activeRunToken) return;
          revokeCurrentBlobUrl();
          blobUrl = URL.createObjectURL(pdfBlob);
          pdfUrl = blobUrl;
          filename = data.filename || filename;
          setReadyPdf(
            { filename, settled_count: settledCount, total_count: totalCount },
            targetTemplate,
          );
          savePrintJob({
            id: String(id),
            cacheKey: [
              targetTemplate.id,
              targetTemplate.surface_key,
              targetTemplate.grain || 'cycle_program',
              (targetTemplate.grain || 'cycle_program') === 'student' ? resolvedAttemptIds.join(',') : '',
              (targetTemplate.grain || 'cycle_program') === 'session' ? String(resolvedSessionId || '') : '',
              (targetTemplate.grain || 'cycle_program') === 'cycle_program' ? String(resolvedCycleId || '') + ':' + (selectedProgramCode || programCode || '') : '',
            ].join(':'),
            title: targetTemplate.name || targetTemplate.surface_key || 'Document',
            surfaceKey: targetTemplate.surface_key || '',
            filename,
            blob: pdfBlob,
            grain: targetTemplate.grain || 'cycle_program',
            pageCount: totalCount || 1,
            actorId: currentActorId,
            institutionId: currentInstitutionId,
          });
          return;
        }
        if (data.status === 'failed') {
          status = 'error';
          errorMessage = data.message || data.error || 'Document print job failed.';
          return;
        }
      }
    } catch (err) {
      if (err?.name === 'AbortError' || runToken !== activeRunToken) return;
      status = 'error';
      errorMessage = err instanceof Error ? err.message : 'Network error while checking print job status.';
    } finally {
      if (jobPollController === controller) {
        jobPollController = null;
      }
    }
  }

  function revokeCurrentBlobUrl() {
    if (blobUrl && typeof URL !== 'undefined' && typeof URL.revokeObjectURL === 'function') {
      try {
        URL.revokeObjectURL(blobUrl);
      } catch {
        // Ignore revoke errors in non-browser test environments
      }
      blobUrl = null;
    }
  }

  function resetRenderState() {
    activeRunToken += 1;
    stopJobPolling();
    revokeCurrentBlobUrl();
    status = 'idle';
    progressPercent = 0;
    statusLabel = '';
    pdfUrl = '';
    filename = 'document.pdf';
    errorMessage = null;
    settledCount = 0;
    totalCount = 0;
    physicallyPrinted = false;
    physicallyPrintedAt = null;
    receiptId = null;
    receiptActorName = null;
    receiptError = null;
    markingPrinted = false;
    jobId = null;
    resolvedGrain = null;
  }
  function invalidateScopeArtifact() {
    resetRenderState();
    errorMessage = '';
  }

  function base64ToPdfUrl(base64, targetTemplate = null, jobId = null) {
    revokeCurrentBlobUrl();
    try {
      const bin = atob(base64);
      const arr = new Uint8Array(bin.length);
      for (let i = 0; i < bin.length; i += 1) {
        arr[i] = bin.charCodeAt(i);
      }
      const blob = new Blob([arr], { type: 'application/pdf' });
      if (targetTemplate) {
        const grain = targetTemplate.grain || 'cycle_program';
        savePrintJob({
          id: jobId,
          cacheKey: [
            targetTemplate.id,
            targetTemplate.surface_key,
            grain,
            grain === 'student' ? resolvedAttemptIds.join(',') : '',
            grain === 'session' ? String(resolvedSessionId || '') : '',
            grain === 'cycle_program' ? String(resolvedCycleId || '') + ':' + (selectedProgramCode || programCode || '') : '',
          ].join(':'),
          title: targetTemplate.name || targetTemplate.surface_key || 'Document',
          surfaceKey: targetTemplate.surface_key || '',
          filename,
          blob,
          grain,
          pageCount: totalCount || 1,
          actorId: currentActorId,
          institutionId: currentInstitutionId,
        });
      }
      if (typeof URL !== 'undefined' && typeof URL.createObjectURL === 'function') {
        blobUrl = URL.createObjectURL(blob);
        return blobUrl;
      }
    } catch {
      // Fallback to data URL if base64 decoding or createObjectURL is unavailable
    }
    return `data:application/pdf;base64,${base64}`;
  }
  function needsScopeOptions(targetTemplate) {
    const grain = targetTemplate?.grain ?? 'student';
    if (grain === 'student') {
      return !Array.isArray(attemptIds) || attemptIds.length === 0;
    }
    if (grain === 'session') {
      return sessionId === null || sessionId === undefined;
    }
    return true;
  }

  async function loadScopeOptions(targetTemplate) {
    if (!targetTemplate || !needsScopeOptions(targetTemplate)) {
      scopeOptions = { applicants: [], sessions: [], items: [], cycles: [], programs: [] };
      return;
    }

    scopeOptionsLoading = true;
    scopeOptionsError = '';
    const query = new URLSearchParams({
      surface_key: targetTemplate.surface_key ?? '',
      grain: targetTemplate.grain ?? 'student',
    });
    if (targetTemplate.id) {
      query.set('template_id', String(targetTemplate.id));
    }

    try {
      const response = await fetch(`/document-printing/scope-options?${query.toString()}`, {
        headers: requestHeaders(),
        credentials: 'same-origin',
      });
      const result = await response.json().catch(() => ({}));
      if (!response.ok) {
        throw new Error(result?.message || result?.error || 'Document scope options could not be loaded.');
      }
      scopeOptions = {
        applicants: Array.isArray(result.applicants) ? result.applicants : [],
        sessions: Array.isArray(result.sessions) ? result.sessions : [],
        items: Array.isArray(result.items) ? result.items : [],
        cycles: Array.isArray(result.cycles) ? result.cycles : [],
        programs: Array.isArray(result.programs) ? result.programs : [],
      };

      if (targetTemplate.grain === 'session' && sessionId == null) {
        selectedSessionId = scopeOptions.sessions[0]?.id ?? scopeOptions.items[0]?.id ?? null;
      }
      if (targetTemplate.grain === 'cycle_program') {
        selectedCycleId ??= scopeOptions.cycles.find((cycle) => cycle.is_active)?.id ?? scopeOptions.cycles[0]?.id ?? null;
        selectedProgramCode ||= programCode ?? scopeOptions.programs[0]?.code ?? '';
      }
    } catch (caught) {
      scopeOptionsError = caught instanceof Error ? caught.message : 'Document scope options could not be loaded.';
    } finally {
      scopeOptionsLoading = false;
    }
  }


  export async function generateDocument() {
    const targetTemplate = selectedTemplate;
    if (!targetTemplate) return;

    const runToken = ++activeRunToken;
    stopJobPolling();
    revokeCurrentBlobUrl();

    status = 'rendering';
    errorMessage = null;
    pdfUrl = '';
    physicallyPrinted = false;
    physicallyPrintedAt = null;
    receiptId = null;
    receiptActorName = null;
    receiptError = null;
    markingPrinted = false;
    jobId = null;
    progressPercent = 0;
    statusLabel = 'Resolving scope and records...';
    settledCount = 0;
    totalCount = 0;

    const targetGrain = targetTemplate.grain ?? 'student';
    const selectedCycle = resolvedCycleId;
    const selectedProgram = selectedProgramCode || programCode || null;
    const payload = {
      template_id: targetTemplate.id,
      surface_key: targetTemplate.surface_key,
      grain: targetGrain,
      cycle_id: selectedCycle,
      academic_cycle_id: selectedCycle,
      program_code: selectedProgram,
      department_code: departmentCode,
      attempt_ids: resolvedAttemptIds,
      session_id: resolvedSessionId,
    };
    const cacheKey = [
      targetTemplate.id,
      targetTemplate.surface_key,
      targetGrain,
      targetGrain === 'student' ? resolvedAttemptIds.join(',') : '',
      targetGrain === 'session' ? String(resolvedSessionId || '') : '',
      targetGrain === 'cycle_program' ? String(selectedCycle || '') + ':' + (selectedProgram || '') : '',
    ].join(':');

    try {
      const cached = await getCachedJob(cacheKey);
      if (cached && cached.blob && runToken === activeRunToken) {
        revokeCurrentBlobUrl();
        blobUrl = URL.createObjectURL(cached.blob);
        pdfUrl = blobUrl;
        filename = cached.filename || filename;
        jobId = cached.id && !isNaN(Number(cached.id)) ? Number(cached.id) : null;
        totalCount = totalCount || 1;
        progressPercent = 100;
        statusLabel = 'Loaded from local browser print cache (0ms)';
        status = 'ready';
        return;
      }
    } catch {
      // Proceed on cache miss
    }

    try {
      const preflightResponse = await fetch('/document-printing/preflight', {
        method: 'POST',
        headers: requestHeaders(),
        credentials: 'same-origin',
        body: JSON.stringify(payload),
      });
      const preflight = await preflightResponse.json().catch(() => ({}));
      if (runToken !== activeRunToken) return;
      if (!preflightResponse.ok) {
        throw new Error(preflight?.message || preflight?.error || 'Document scope could not be resolved.');
      }

      resolvedGrain = preflight.grain || payload.grain;
      totalCount = Number(preflight.count ?? 0);
      statusLabel = `Scope resolved · ${totalCount} record${totalCount === 1 ? '' : 's'}`;

      const response = await fetch('/document-printing/render', {
        method: 'POST',
        headers: requestHeaders(),
        credentials: 'same-origin',
        body: JSON.stringify({ ...payload, grain: resolvedGrain }),
      });

      const data = await response.json().catch(() => ({}));
      if (runToken !== activeRunToken) return;

      if (response.status === 200) {
        if (!data?.pdf_base64) {
          throw new Error(data?.message || 'Document PDF generation returned no preview.');
        }
        jobId = data.job_id ?? null;
        setReadyPdf(data, targetTemplate);
        return;
      }

      if (response.status === 202 && data?.job_id) {
        jobId = data.job_id;
        status = 'processing';
        updateProgress(
          { settled_count: 0, total_count: totalCount },
          data.stage || 'resolving',
        );
        void pollJob(jobId, targetTemplate, runToken);
        return;
      }

      throw new Error(
        data?.message ||
          data?.error ||
          'Document PDF generation could not complete. Please verify scope and retry.',
      );
    } catch (err) {
      if (runToken !== activeRunToken) return;
      status = 'error';
      errorMessage =
        err instanceof Error && err.message
          ? err.message
          : 'Network error while generating document PDF.';
    }
  }

  function handleTemplateChange(event) {
    const nextId = event.currentTarget.value;
    selectedTemplateId = nextId;
    resetRenderState();
    const nextTemplate = availableTemplates.find((item) => String(item.id) === String(nextId));
    void loadScopeOptions(nextTemplate);
    if (autoStartSingle && attemptIds.length === 1) {
      void generateDocument();
    }
  }

  function handlePrintNow() {
    if (!iframeEl?.contentWindow) return;
    iframeEl.contentWindow.focus?.();
    iframeEl.contentWindow.print?.();
  }

  async function markPhysicallyPrinted() {
    const targetTemplate = selectedTemplate;
    const targetJobId = jobId;
    const runToken = activeRunToken;
    if (
      status !== 'ready' ||
      !targetTemplate?.id ||
      !targetJobId ||
      physicallyPrinted ||
      markingPrinted
    ) {
      return;
    }

    markingPrinted = true;
    receiptError = null;

    markJobPrinted(String(targetJobId));
    try {
      const response = await fetch(
        `/document-printing/jobs/${encodeURIComponent(targetJobId)}/mark-printed`,
        {
          method: 'POST',
          headers: requestHeaders(),
          credentials: 'same-origin',
          body: JSON.stringify({ job_id: targetJobId }),
        },
      );
      const data = await response.json().catch(() => ({}));

      if (
        runToken !== activeRunToken ||
        selectedTemplate?.id !== targetTemplate.id
      ) {
        return;
      }

      if (
        response.status !== 200 ||
        !data?.receipt_id ||
        !data?.printed_at ||
        !data?.actor_name
      ) {
        receiptError =
          data?.message ?? 'The physical print receipt could not be recorded.';
        return;
      }

      receiptId = data.receipt_id;
      physicallyPrintedAt = data.printed_at;
      receiptActorName = data.actor_name;
      physicallyPrinted = true;
    } catch {
      if (runToken === activeRunToken) {
        receiptError = 'Network error while recording the physical print receipt.';
      }
    } finally {
      if (
        runToken === activeRunToken &&
        selectedTemplate?.id === targetTemplate.id
      ) {
        markingPrinted = false;
      }
    }
  }

  function handleClose() {
    open = false;
    activeRunToken += 1;
    stopJobPolling();
    markingPrinted = false;
    onClose?.();
  }

  function handleOpenChange(nextOpen) {
    open = nextOpen;
    if (!nextOpen) {
      activeRunToken += 1;
      stopJobPolling();
      markingPrinted = false;
      onClose?.();
    }
  }
  let wasOpen = false;
  $effect(() => {
    const isOpen = Boolean(open);
    const initialTemplate = template;
    const listTemplates = templates;
    const singleAuto = autoStartSingle;
    const currentAttempts = attemptIds;

    if (isOpen && !wasOpen) {
      wasOpen = true;
      untrack(() => {
        resetRenderState();
        scopeOptions = { applicants: [], sessions: [], items: [], cycles: [], programs: [] };
        selectedAttemptIds = Array.isArray(currentAttempts) ? [...currentAttempts] : [];
        selectedSessionId = sessionId ?? null;
        selectedCycleId = academicCycleId ?? cycleId ?? null;
        selectedProgramCode = programCode ?? '';
        const fallback =
          initialTemplate ??
          (Array.isArray(listTemplates) && listTemplates.length > 0 ? listTemplates[0] : null);
        selectedTemplateId = fallback?.id ?? null;
        if (fallback) {
          void loadScopeOptions(fallback);
        }
        if (
          singleAuto &&
          Array.isArray(currentAttempts) &&
          currentAttempts.length === 1 &&
          fallback
        ) {
          void generateDocument();
        }
      });
    } else if (!isOpen && wasOpen) {
      wasOpen = false;
      activeRunToken += 1;
      untrack(() => {
        stopJobPolling();
      });
    }
  });

  onDestroy(() => {
    activeRunToken += 1;
    stopJobPolling();
    revokeCurrentBlobUrl();
  });
</script>

<Dialog bind:open onOpenChange={handleOpenChange}>
  <DialogContent class="sm:max-w-3xl max-h-[92vh] overflow-y-auto p-6">
    <DialogHeader class="space-y-2 text-left">
      <div class="flex flex-wrap items-center justify-between gap-2 pr-6">
        <div class="flex items-center gap-2">
          <div class="flex size-8 items-center justify-center rounded-lg bg-primary/10 text-primary">
            <Printer class="size-4" />
          </div>
          <DialogTitle class="text-base font-semibold text-foreground">
            {selectedTemplate?.name ?? 'Document Print Manager'}
          </DialogTitle>
        </div>

        <div class="flex flex-wrap items-center gap-1.5">
          <Badge variant="secondary" class="gap-1 text-[11px] font-medium">
            <Layers class="size-3" />
            {grainBadgeLabel}
          </Badge>
          {#if boundPackLabel}
            <Badge variant="outline" class="font-mono text-[11px]">
              {boundPackLabel}
            </Badge>
          {/if}
        </div>
      </div>

      <DialogDescription class="text-xs text-muted-foreground">
        Generate, preview, download, or print official office documents from the pinned template layout.
      </DialogDescription>
    </DialogHeader>

    <div class="space-y-4 pt-2">
      {#if selectedTemplate && (needsScopeOptions(selectedTemplate) || effectiveGrain === 'cycle_program')}
        <section class="space-y-3 rounded-lg border border-border/80 bg-card p-3.5" aria-label="Document print scope">
          <div>
            <h3 class="text-sm font-semibold text-foreground">Choose print scope</h3>
            <p class="mt-1 text-xs text-muted-foreground">Select the records this document should include.</p>
          </div>
          {#if scopeOptionsLoading}
            <p class="text-xs text-muted-foreground" role="status">Loading available scope…</p>
          {:else if scopeOptionsError}
            <p class="text-xs text-destructive" role="alert">{scopeOptionsError}</p>
          {:else if effectiveGrain === 'student' && (!Array.isArray(attemptIds) || attemptIds.length === 0)}
            {@const applicantOptions = scopeOptions.applicants.length > 0 ? scopeOptions.applicants : scopeOptions.items}
            <div class="max-h-48 space-y-2 overflow-y-auto rounded-md border border-border p-2">
              <div class="flex items-center justify-between gap-3">
                <p class="text-xs font-medium text-foreground">Pick Applicants ({selectedAttemptIds.length} selected)</p>
                <Button type="button" variant="ghost" size="sm" class="h-7 px-2 text-xs" onclick={() => {
                  const nextAttemptIds = applicantOptions.map((item) => item.id);
                  if (JSON.stringify(nextAttemptIds) !== JSON.stringify(selectedAttemptIds)) invalidateScopeArtifact();
                  selectedAttemptIds = nextAttemptIds;
                }}>Select all</Button>
              </div>
              {#if applicantOptions.length === 0}
                <p class="py-2 text-xs text-muted-foreground">No applicants are available for this document.</p>
              {:else}
                {#each applicantOptions as applicant (applicant.id)}
                  <label class="flex cursor-pointer items-center gap-2 rounded px-1.5 py-1 text-xs hover:bg-muted">
                    <input type="checkbox" checked={selectedAttemptIds.includes(applicant.id)} onchange={(event) => {
                      const nextAttemptIds = event.currentTarget.checked
                        ? [...new Set([...selectedAttemptIds, applicant.id])]
                        : selectedAttemptIds.filter((id) => id !== applicant.id);
                      if (JSON.stringify(nextAttemptIds) !== JSON.stringify(selectedAttemptIds)) invalidateScopeArtifact();
                      selectedAttemptIds = nextAttemptIds;
                    }} />
                    <span class="min-w-0 truncate font-medium text-foreground">{applicant.applicant_name ?? applicant.name ?? applicant.reference_code ?? `Applicant #${applicant.id}`}</span>
                    {#if applicant.reference_code}<span class="ml-auto shrink-0 font-mono text-muted-foreground">{applicant.reference_code}</span>{/if}
                  </label>
                {/each}
              {/if}
            </div>
          {:else if effectiveGrain === 'session' && sessionId == null}
            {@const sessionOptions = scopeOptions.sessions.length > 0 ? scopeOptions.sessions : scopeOptions.items}
            <label class="grid gap-1.5 text-xs font-medium text-foreground" for="document-print-session-select">
              Exam or counseling session
              <select id="document-print-session-select" value={selectedSessionId ?? ''} onchange={(event) => {
                const nextSessionId = event.currentTarget.value || null;
                if (nextSessionId !== selectedSessionId) invalidateScopeArtifact();
                selectedSessionId = nextSessionId;
              }} class="h-9 rounded-md border border-input bg-background px-2.5 text-xs">
                <option value="">Select a session</option>
                {#each sessionOptions as session (session.id)}
                  <option value={session.id}>{session.label ?? session.name ?? session.code ?? `Session #${session.id}`}</option>
                {/each}
              </select>
            </label>
          {:else if effectiveGrain === 'cycle_program'}
            <div class="grid gap-3 sm:grid-cols-2">
              <label class="grid gap-1.5 text-xs font-medium text-foreground" for="document-print-cycle-select">
                Academic Cycle
                <select id="document-print-cycle-select" value={selectedCycleId ?? ''} onchange={(event) => {
                  const nextCycleId = event.currentTarget.value || null;
                  if (String(nextCycleId) !== String(selectedCycleId ?? '')) invalidateScopeArtifact();
                  selectedCycleId = nextCycleId;
                }} class="h-9 rounded-md border border-input bg-background px-2.5 text-xs">
                  <option value="">Select an academic cycle</option>
                  {#each scopeOptions.cycles as cycle (cycle.id)}
                    <option value={cycle.id}>{cycle.name ?? cycle.code ?? `Cycle #${cycle.id}`}</option>
                  {/each}
                </select>
              </label>
              <label class="grid gap-1.5 text-xs font-medium text-foreground" for="document-print-program-select">
                Program {scopeOptions.programs.length === 1 ? '' : '(Optional)'}
                <select id="document-print-program-select" value={selectedProgramCode} onchange={(event) => {
                  const nextProgramCode = event.currentTarget.value;
                  if (nextProgramCode !== selectedProgramCode) invalidateScopeArtifact();
                  selectedProgramCode = nextProgramCode;
                }} class="h-9 rounded-md border border-input bg-background px-2.5 text-xs">
                  {#if scopeOptions.programs.length !== 1}
                    <option value="">
                      {page?.props?.auth?.user?.office === 'program'
                        ? `All Department Programs (${scopeOptions.programs.length})`
                        : 'All Programs (Institution-wide)'}
                    </option>
                  {/if}
                  {#each scopeOptions.programs as program (program.id)}
                    <option value={program.code}>{program.name} ({program.code})</option>
                  {/each}
                </select>
              </label>
            </div>
          {/if}
        </section>
      {/if}

      <!-- Template & Scope Summary Bar -->
      <div class="flex flex-col gap-3 rounded-lg border border-border/80 bg-muted/30 p-3.5 sm:flex-row sm:items-center sm:justify-between">
        <div class="space-y-1">
          {#if availableTemplates.length > 1}
            <label class="block text-xs font-medium text-muted-foreground" for="document-print-template-select">
              Document Template
            </label>
            <select
              id="document-print-template-select"
              class="h-8 rounded-md border border-input bg-background px-2.5 text-xs font-medium text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
              value={selectedTemplate?.id ?? ''}
              onchange={handleTemplateChange}
            >
              {#each availableTemplates as item (item.id)}
                <option value={item.id}>{item.name}</option>
              {/each}
            </select>
          {:else}
            <div class="flex items-center gap-1.5 text-xs font-medium text-foreground">
              <FileText class="size-3.5 text-muted-foreground" />
              <span>{selectedTemplate?.name ?? 'No template selected'}</span>
            </div>
          {/if}

          <div class="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
            <span class="inline-flex items-center gap-1 font-medium text-foreground">
              <Users class="size-3.5 text-muted-foreground" />
              {subjectCountSummary}
            </span>
            {#if selectedTemplate?.paper_size || selectedTemplate?.orientation}
              <span>·</span>
              <span class="uppercase">
                {selectedTemplate?.paper_size ?? 'A4'} {selectedTemplate?.orientation ?? 'portrait'}
              </span>
            {/if}
          </div>
        </div>

        {#if status === 'idle'}
          <div class="flex items-center gap-2">
            <Button
              size="sm"
              class="gap-1.5 font-semibold"
              disabled={!canGenerate}
              onclick={() => generateDocument()}
            >
              <Printer class="size-3.5" />
              Generate & Preview PDF
            </Button>
          </div>
        {:else if status === 'ready'}
          <div class="flex items-center gap-1.5">
            <Badge variant="default" class="bg-green-600 text-[11px] text-white hover:bg-green-600">
              {statusLabel}
            </Badge>
            <Button
              variant="ghost"
              size="sm"
              class="h-7 gap-1 px-2 text-xs"
              onclick={() => generateDocument()}
            >
              <RotateCw class="size-3" />
              Regenerate
            </Button>
          </div>
        {/if}
      </div>

      {#if status === 'idle' && !canGenerate && effectiveGrain === 'student'}
        <div class="rounded-lg border border-amber-500/30 bg-amber-500/10 p-3 text-xs text-amber-700 dark:text-amber-300">
          Select at least one applicant record to generate a student-grain document.
        </div>
      {/if}

      <!-- Progress Bar & Status Stage -->
      {#if status === 'rendering' || status === 'processing' || status === 'ready'}
        <div class="space-y-2 rounded-lg border border-border/70 bg-card p-3.5" role="status" aria-live="polite">
          <div class="flex items-center justify-between text-xs">
            <div class="flex items-center gap-2 font-medium text-foreground">
              {#if status === 'rendering' || status === 'processing'}
                <RotateCw class="size-3.5 animate-spin text-primary" />
              {:else}
                <CheckCircle2 class="size-3.5 text-green-600 dark:text-green-400" />
              {/if}
              <span>{statusLabel}</span>
            </div>
            <span class="font-mono text-xs font-semibold text-foreground">
              {#if status === 'processing'}
                {settledCount} / {totalCount}
              {:else}
                {progressPercent}%
              {/if}
            </span>
          </div>

          <div class="h-2 w-full overflow-hidden rounded-full bg-muted">
            <div
              class="h-full bg-primary transition-all duration-200"
              style={`width: ${progressPercent}%`}
            ></div>
          </div>

          {#if (status === 'processing' || status === 'ready') && totalCount > 0}
            <p class="text-[11px] text-muted-foreground">
              Settled {settledCount} of {totalCount} work unit{totalCount === 1 ? '' : 's'}.
            </p>
          {/if}
        </div>
      {/if}

      <!-- Error State -->
      {#if status === 'error'}
        <div class="flex flex-col gap-3 rounded-lg border border-destructive/40 bg-destructive/10 p-4 sm:flex-row sm:items-center sm:justify-between" role="alert">
          <div class="flex items-start gap-2.5">
            <AlertCircle class="mt-0.5 size-4 shrink-0 text-destructive" />
            <div class="space-y-0.5">
              <p class="text-xs font-semibold text-destructive">Document generation failed</p>
              <p class="text-xs text-muted-foreground">{errorMessage}</p>
            </div>
          </div>
          <Button size="sm" variant="outline" class="gap-1.5 shrink-0" onclick={() => generateDocument()}>
            <RotateCw class="size-3.5" />
            Retry
          </Button>
        </div>
      {/if}

      <!-- Rendered PDF Preview + Actions -->
      {#if status === 'ready' && pdfUrl}
        <div class="space-y-3">
          <iframe
            bind:this={iframeEl}
            src={pdfUrl}
            class="h-[460px] w-full rounded-lg border border-border bg-muted/20"
            title="Document PDF preview"
          ></iframe>

          <div class="flex flex-col gap-3 rounded-lg border border-border/80 bg-muted/20 p-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-wrap items-center gap-2">
              <a
                href={pdfUrl}
                download={filename}
                class="inline-flex h-8 items-center justify-center gap-1.5 rounded-md bg-primary px-3 text-xs font-semibold text-primary-foreground shadow-xs transition-colors hover:bg-primary/90"
              >
                <Download class="size-3.5" />
                Download PDF
              </a>

              <Button
                variant="outline"
                size="sm"
                class="gap-1.5"
                onclick={handlePrintNow}
              >
                <Printer class="size-3.5" />
                Print Now
              </Button>

              <Button
                variant={physicallyPrinted ? 'secondary' : 'outline'}
                size="sm"
                class="gap-1.5"
                aria-pressed={physicallyPrinted}
                disabled={physicallyPrinted || markingPrinted}
                onclick={markPhysicallyPrinted}
              >
                <CheckSquare class="size-3.5" />
                {physicallyPrinted
                  ? 'Physically Printed'
                  : markingPrinted
                    ? 'Recording Receipt...'
                    : 'Mark Physically Printed'}
              </Button>
            </div>

            <Button variant="ghost" size="sm" onclick={handleClose}>
              Done
            </Button>
          </div>

          <!-- Physical Print Confirmation Notice -->
          <div class="flex flex-wrap items-center justify-between gap-2 rounded-md border border-border/60 bg-card px-3 py-2 text-xs">
            <div class="flex items-center gap-2 text-muted-foreground">
                {#if physicallyPrinted}
                  <Badge variant="default" class="bg-emerald-600 text-[10px] text-white hover:bg-emerald-600">
                    Receipt Recorded · {physicallyPrintedAt}
                  </Badge>
                {:else}
                  <Badge variant="outline" class="text-[10px]">
                    Optional Receipt
                  </Badge>
                {/if}
                {#if physicallyPrinted}
                  <span>
                    Receipt #{receiptId} · {receiptActorName}
                  </span>
                {:else}
                  <span>Confirmed physical print preparation — does not record applicant handover</span>
                {/if}
              {#if receiptError}
                <p class="w-full text-xs text-destructive" role="alert">
                  {receiptError}
                </p>
              {/if}
            </div>
          </div>
        </div>
      {/if}
    </div>
  </DialogContent>
</Dialog>
