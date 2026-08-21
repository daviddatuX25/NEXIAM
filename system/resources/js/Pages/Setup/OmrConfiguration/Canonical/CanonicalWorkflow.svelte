<script>
  import { router } from '@inertiajs/svelte';
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
  import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
  import { Button } from '@/Components/ui/button';
  import { newIdempotencyKey } from '@/lib/utils';
  import OmrTemplateStudio from './OmrTemplateStudio.svelte';
  import BlankInstanceHistoryPanel from './BlankInstanceHistoryPanel.svelte';
  import BlankInstancePrintPanel from './BlankInstancePrintPanel.svelte';

  let {
    draft = {},
    marker_profiles = [],
    stages = [],
    urls = {},
  } = $props();

  const stageMeta = {
    draft: { label: 'Start', short: 'Start' },
    convert_inspect: { label: 'Convert / inspect', short: 'Inspect' },
    page_prepare: { label: 'Prepare page', short: 'Prepare' },
    overlay: { label: 'Overlay', short: 'Overlay' },
    map: { label: 'Map', short: 'Map' },
    digital_verify: { label: 'Digital verify', short: 'Verify' },
    compile: { label: 'Compile', short: 'Compile' },
    physical_activate: { label: 'Physical qualify / activate', short: 'Activate' },
    blank_print: { label: 'Blank print', short: 'Print' },
  };
  const domainView = {
    draft: draft.source_kind === 'preset' ? 'map' : 'draft',
    source_confirmed: 'convert_inspect',
    page_prepared: 'map',
    mapping_complete: 'map',
    digital_verified: 'compile',
    compiled: draft.compiled_version_status === 'active' ? 'blank_print' : 'physical_activate',
  };
  const breadcrumbs = [
    { label: 'Setup', href: '/setup' },
    { label: 'OMR Configuration', href: '/setup/omr' },
    { label: 'Template Studio' },
  ];

  let activeStage = $state(domainView[draft.stage] ?? 'draft');
  let busy = $state(false);
  let message = $state('');
  let error = $state('');
  let sourceFile = $state(null);
  let sourceKind = $state(draft.source_kind ?? 'image');
  let paperWidth = $state(216);
  let paperHeight = $state(330);
  let selectedProfile = $state(draft.authoring?.marker?.profile_id ?? marker_profiles[0]?.id ?? null);
  let lockVersion = $state(draft.lock_version ?? 0);
  let mapping = $state(draft.authoring?.mapping?.input ?? {
    page_width: 2551,
    page_height: 3898,
    page_count: 1,
    blocks: [],
  });
  let overlay = $state(draft.authoring?.overlay ?? { boxes: [] });
  const unlockedIndex = $derived(Math.max(0, stages.indexOf(domainView[draft.stage] ?? 'draft')));
  let selectedBlock = $state(null);
  let printerIdentity = $state('');
  let deviceIdentity = $state('');
  let measuredWidthMm = $state('');
  let measuredHeightMm = $state('');
  let physicalMatrixPassed = $state(false);
  let quantity = $state(1);
  let conversionRun = $state(draft.authoring?.source_run ?? null);

  function setResult(nextDraft, text = 'Saved') {
    if (nextDraft) {
      activeStage = nextDraft.stage ?? activeStage;
      lockVersion = nextDraft.lock_version ?? lockVersion;
      draft = { ...draft, ...nextDraft };
    }
    busy = false;
    message = text;
    error = '';
  }

  function fail(formError) {
    busy = false;
    error = formError?.message ?? 'The operation failed. No staged data was discarded.';
    message = '';
  }

  function submitIngest() {
    if (!sourceFile || !draft.can_edit) return;
    busy = true;
    const data = {
      source_kind: sourceKind,
      source: sourceFile,
      idempotency_key: newIdempotencyKey(),
      width_mm: paperWidth,
      height_mm: paperHeight,
    };
    router.post(urls.source_ingest, data, {
      forceFormData: true,
      preserveScroll: true,
      onSuccess: () => { conversionRun = { status: 'queued', progress_done: 0, progress_total: 1 }; setResult(null, 'Conversion queued. Refresh when the worker finishes.'); },
      onError: fail,
      onFinish: () => (busy = false),
    });
  }
  async function refreshConversionRun() {
    if (!urls.source_run) return;
    const response = await fetch(urls.source_run, { headers: { Accept: 'application/json' } });
    if (response.ok) conversionRun = (await response.json()).run;
  }

  async function cancelConversionRun() {
    if (!urls.source_cancel) return;
    const token = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const response = await fetch(urls.source_cancel, {
      method: 'POST',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
      body: JSON.stringify({ idempotency_key: newIdempotencyKey() }),
    });
    const body = await response.json();
    if (response.ok) conversionRun = body.run;
    else error = body?.message ?? 'Cancellation could not be requested.';
  }

  function confirmPages() {
    const pages = (draft.authoring?.pages ?? []).map((page) => ({
      page_no: page.page_no,
      decision: 'confirmed',
    }));
    busy = true;
    router.post(urls.confirm_pages, { pages }, {
      preserveScroll: true,
      onSuccess: () => setResult(null, 'All intermediate pages confirmed.'),
      onError: fail,
      onFinish: () => (busy = false),
    });
  }

  function compose() {
    busy = true;
    router.post(urls.compose, {
      paper_width_mm: Number(paperWidth),
      paper_height_mm: Number(paperHeight),
      content_margin_mm: { top: 18, right: 15, bottom: 18, left: 15 },
      printer_safe_margin_mm: { top: 5, right: 5, bottom: 5, left: 5 },
      marker_inset_mm: { top: 12, right: 12, bottom: 14, left: 12 },
      alignment: 'center',
      source_dpi: 300,
      idempotency_key: newIdempotencyKey(),
    }, {
      preserveScroll: true,
      onSuccess: () => setResult(null, 'Canonical 300-PPI page set composed.'),
      onError: fail,
      onFinish: () => (busy = false),
    });
  }

  function chooseProfile(profileId) {
    if (!draft.can_edit) return;
    selectedProfile = profileId;
    busy = true;
    router.put(`${urls.mapping?.split('/mapping')[0] ?? ''}/marker-profile/${profileId}`, {
      marker_profile_id: profileId,
      insets: { top: 12, right: 12, bottom: 14, left: 12 },
      lock_version: lockVersion,
      idempotency_key: newIdempotencyKey(),
    }, {
      preserveScroll: true,
      onSuccess: () => setResult(null, 'Marker profile selected; physical size remains profile-owned.'),
      onError: fail,
      onFinish: () => (busy = false),
    });
  }

  function saveOverlay() {
    busy = true;
    router.put(urls.overlay, { overlay, lock_version: lockVersion, idempotency_key: newIdempotencyKey() }, {
      preserveScroll: true,
      onSuccess: () => setResult(null, 'Fixed overlay saved; mapping and verification are now explicitly stale.'),
      onError: fail,
      onFinish: () => (busy = false),
    });
  }
  function saveMapping() {
    busy = true;
    router.put(urls.mapping, { mapping, lock_version: lockVersion, idempotency_key: newIdempotencyKey() }, {
      preserveScroll: true,
      onSuccess: () => setResult(null, 'Mapping saved. Individual bubbles remain inspect-only.'),
      onError: fail,
      onFinish: () => (busy = false),
    });
  }

  function runDigitalVerification() {
    busy = true;
    router.post(urls.digital_verification, { idempotency_key: newIdempotencyKey() }, {
      preserveScroll: true,
      onSuccess: () => setResult(null, 'Digital proof and no-go matrix passed. Thresholds remain Unqualified until physical calibration.'),
      onError: fail,
      onFinish: () => (busy = false),
    });
  }

  function compile() {
    busy = true;
    router.post(urls.compile, { idempotency_key: newIdempotencyKey() }, {
      preserveScroll: true,
      onSuccess: () => setResult(null, 'Immutable draft version compiled.'),
      onError: fail,
      onFinish: () => (busy = false),
    });
  }

  function physicalQualification() {
    const versionId = draft.compiled_version_id;
    if (!versionId || !printerIdentity || !deviceIdentity || !measuredWidthMm || !measuredHeightMm || !physicalMatrixPassed) return;
    busy = true;
    router.post(`/setup/omr/templates/${versionId}/verify/physical`, {
      printer: printerIdentity,
      device: deviceIdentity,
      paper: `${paperWidth}×${paperHeight} mm`,
      renderer_version: 'canonical-renderer-v1',
      measured_marker_center_mm: [Number(measuredWidthMm), Number(measuredHeightMm)],
      outcomes: ['pass'],
      idempotency_key: newIdempotencyKey(),
    }, {
      preserveScroll: true,
      onSuccess: () => setResult(null, 'Measured physical qualification evidence recorded.'),
      onError: fail,
      onFinish: () => (busy = false),
    });
  }

  function activate() {
    if (!draft.compiled_version_id) return;
    busy = true;
    router.post(`/setup/omr/templates/${draft.compiled_version_id}/activate`, { idempotency_key: newIdempotencyKey() }, {
      preserveScroll: true,
      onSuccess: () => setResult(null, 'Canonical version activated.'),
      onError: fail,
      onFinish: () => (busy = false),
    });
  }

  function printBlankInstances() {
    if (!draft.compiled_version_id) return;
    busy = true;
    router.post(`/setup/omr/templates/${draft.compiled_version_id}/blank/print`, { quantity: Number(quantity), idempotency_key: newIdempotencyKey() }, {
      preserveScroll: true,
      onSuccess: () => setResult(null, 'Blank print job queued in deterministic chunks.'),
      onError: fail,
      onFinish: () => (busy = false),
    });
  }
</script>

<StaffLayout {breadcrumbs}>
  <PageHeaderBar
    title={`Template Studio · ${draft.name ?? draft.code ?? 'Answer Sheet'}`}
    description="Design printable answer sheets, configure optical registration, and map question bubbles."
  />

  <section class="space-y-4 px-6 pt-2 pb-6">
    <div class="flex flex-wrap items-center gap-2 rounded-lg border border-border/80 bg-card p-2.5 shadow-xs">
      {#each stages as stage, index}
        <button
          type="button"
          class:font-semibold={stage === activeStage}
          class="rounded-full border px-3 py-1 text-xs transition hover:border-slate-900 disabled:cursor-not-allowed disabled:opacity-50"
          disabled={!draft.can_edit || index > unlockedIndex}
          aria-current={stage === activeStage ? 'step' : undefined}
          title={index > unlockedIndex ? 'Complete the previous stage first.' : stageMeta[stage]?.label}
          onclick={() => (activeStage = stage)}
        >
          <span class="mr-1 text-slate-400">{index + 1}</span>{stageMeta[stage]?.short ?? stage}
        </button>
      {/each}
      <span class="ml-auto rounded-full bg-amber-50 px-3 py-1 text-xs font-medium text-amber-800">Calibration: Unqualified</span>
    </div>

    {#if message}
      <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900" role="status">{message}</div>
    {/if}
    {#if error}
      <div class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-900" role="alert">{error}</div>
    {/if}
    {#if !draft.can_edit}
      <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">Read-only view. The draft owner must make staged changes.</div>
    {/if}

    {#if activeStage === 'draft'}
      <div class="grid gap-6 lg:grid-cols-[1fr_0.8fr]">
        <article class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
          <h2 class="text-lg font-semibold text-slate-950">Start with a source</h2>
          <p class="mt-1 text-sm text-slate-600">HTML, DOCX, PDF, image, and preset sources share the same review gate.</p>
          <div class="mt-5 grid gap-4 sm:grid-cols-2">
            <label class="text-sm font-medium text-slate-700">Source kind
              <select bind:value={sourceKind} class="mt-1 block w-full rounded-md border-slate-300 text-sm" disabled={!draft.can_edit}>
                <option value="html">HTML</option><option value="docx">DOCX</option><option value="pdf">PDF</option><option value="image">Image / photo</option><option value="preset">Existing preset</option>
              </select>
            </label>
            <label class="text-sm font-medium text-slate-700">Source file
              <input type="file" class="mt-1 block w-full text-sm" onchange={(event) => (sourceFile = event.currentTarget.files?.[0] ?? null)} disabled={!draft.can_edit} />
            </label>
          </div>
          <button class="mt-6 rounded-md bg-slate-950 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-50" type="button" onclick={submitIngest} disabled={busy || !sourceFile || !draft.can_edit}>{busy ? 'Queueing…' : 'Convert and inspect'}</button>
          {#if conversionRun}
            <div class="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-3 text-xs text-slate-700"><div class="flex flex-wrap items-center justify-between gap-2"><span>Status: <strong>{conversionRun.status}</strong> · {conversionRun.progress_done ?? 0}/{conversionRun.progress_total ?? 1}</span><div class="flex gap-2"><button type="button" class="font-medium underline" onclick={refreshConversionRun}>Refresh</button>{#if ['queued','processing'].includes(conversionRun.status)}<button type="button" class="font-medium text-red-700 underline" onclick={cancelConversionRun}>Cancel conversion</button>{/if}</div></div></div>
          {/if}
        </article>
        <aside class="rounded-xl border border-slate-200 bg-slate-50 p-6 text-sm text-slate-700">
          <h3 class="font-semibold text-slate-950">Boundary guarantees</h3>
          <ul class="mt-3 list-disc space-y-2 pl-5"><li>25 MB upload and hostile-file preflight.</li><li>Remote HTML/scripts/macros/encrypted PDFs are blocked.</li><li>Photo rectification is server-authoritative.</li><li>Non-sRGB ICC is rejected until a deterministic pipeline is qualified.</li></ul>
        </aside>
      </div>
    {:else if activeStage === 'convert_inspect'}
      <article class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-4"><div><h2 class="text-lg font-semibold text-slate-950">Confirm every intermediate page</h2><p class="mt-1 text-sm text-slate-600">A wrong page cannot silently enter composition.</p></div><div class="flex gap-2"><Button size="sm" variant="outline" onclick={confirmPages} disabled={busy || !draft.can_edit}>Confirm all visible pages</Button><Button size="sm" onclick={compose} disabled={busy || !draft.can_edit}>Compose canonical page</Button></div></div>
        <div class="mt-5 grid gap-3 md:grid-cols-2">{#each draft.authoring?.pages ?? [] as page (page.page_no)}<div class="rounded-lg border border-slate-200 p-4"><div class="flex items-center justify-between"><span class="font-medium">Page {page.page_no}</span><span class="text-xs text-slate-500">{page.width_mm ?? '—'} × {page.height_mm ?? '—'} mm</span></div><p class="mt-2 text-xs text-slate-600">{page.confirmed ? 'Confirmed' : 'Needs staff classification'}</p></div>{/each}</div>
      </article>
    {:else if ['page_prepare', 'overlay', 'map'].includes(activeStage)}
      <div class="space-y-4">
        <OmrTemplateStudio
          {draft}
          {marker_profiles}
          {urls}
          onMappingSaved={() => {
            message = 'Studio layout autosaved. Mapping and overlay are synchronized.';
            error = '';
          }}
        />
      </div>
    {:else if activeStage === 'digital_verify'}
      <article class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm"><h2 class="text-lg font-semibold text-slate-950">Digital verification</h2><p class="mt-1 text-sm text-slate-600">Generate deterministic marks from the final composed page and run the declared no-go matrix. No applicant identity is fabricated.</p><Button class="mt-5" onclick={runDigitalVerification} disabled={busy || !draft.can_edit || draft.stage !== 'mapping_complete'}>Run digital proof and no-go matrix</Button></article>
    {:else if activeStage === 'compile'}
      <article class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm"><h2 class="text-lg font-semibold text-slate-950">Compile immutable version</h2><p class="mt-1 text-sm text-slate-600">Requires a qualified marker profile and passing digital evidence. Failure leaves no partial version.</p><Button class="mt-5" onclick={compile} disabled={busy || !draft.can_edit || draft.stage !== 'digital_verified'}>Compile immutable version</Button></article>
    {:else if activeStage === 'physical_activate'}
      <div class="grid gap-6 lg:grid-cols-2">
        <article class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm"><h2 class="text-lg font-semibold text-slate-950">Physical qualification</h2><p class="mt-1 text-sm text-slate-600">Print the exact compiled PDF, execute the physical matrix, and record measured marker-center distances.</p><div class="mt-4 grid gap-3 sm:grid-cols-2"><label class="text-xs font-medium text-slate-700">Printer identity<input class="mt-1 w-full rounded border-slate-300 text-sm" bind:value={printerIdentity} /></label><label class="text-xs font-medium text-slate-700">Camera / scanner device<input class="mt-1 w-full rounded border-slate-300 text-sm" bind:value={deviceIdentity} /></label><label class="text-xs font-medium text-slate-700">Measured frame width (mm)<input class="mt-1 w-full rounded border-slate-300 text-sm" type="number" step="0.1" bind:value={measuredWidthMm} /></label><label class="text-xs font-medium text-slate-700">Measured frame height (mm)<input class="mt-1 w-full rounded border-slate-300 text-sm" type="number" step="0.1" bind:value={measuredHeightMm} /></label></div><label class="mt-3 flex items-start gap-2 text-xs text-slate-700"><input type="checkbox" bind:checked={physicalMatrixPassed} /> I executed the required print/device/paper matrix and every declared variant passed.</label><Button class="mt-5" onclick={physicalQualification} disabled={busy || !draft.can_edit || !printerIdentity || !deviceIdentity || !measuredWidthMm || !measuredHeightMm || !physicalMatrixPassed}>Record measured qualification</Button></article>
        <article class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm"><h2 class="text-lg font-semibold text-slate-950">Activate immutable version</h2><p class="mt-1 text-sm text-slate-600">Activation stays blocked until physical evidence passes; the old active version remains live until commit.</p>{#if !draft.physical_qualified}<p class="mt-3 rounded bg-amber-50 p-2 text-xs text-amber-900">Blocked: measured physical qualification is missing.</p>{/if}<Button class="mt-5" onclick={activate} disabled={busy || !draft.can_edit || !draft.physical_qualified}>Activate version</Button></article>
      </div>
    {:else if activeStage === 'blank_print'}
      <div class="space-y-6">
        <BlankInstancePrintPanel {quantity} {busy} disabled={!draft.can_edit || draft.compiled_version_status !== 'active'} onquantity={(value) => (quantity = value)} onprint={printBlankInstances} />
        <BlankInstanceHistoryPanel templateVersionId={draft.compiled_version_id} />
      </div>
    {/if}
  </section>
</StaffLayout>
