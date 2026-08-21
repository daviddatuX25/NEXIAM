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
  <div class="h-[calc(100vh-3.5rem)] w-full flex flex-col overflow-hidden">
    {#if activeStage === 'blank_print'}
      <div class="p-6 space-y-6 max-w-5xl mx-auto w-full overflow-y-auto">
        <BlankInstancePrintPanel {quantity} {busy} disabled={!draft.can_edit || draft.compiled_version_status !== 'active'} onquantity={(value) => (quantity = value)} onprint={printBlankInstances} />
        <BlankInstanceHistoryPanel templateVersionId={draft.compiled_version_id} />
      </div>
    {:else}
      <OmrTemplateStudio
        {draft}
        {marker_profiles}
        {urls}
        onMappingSaved={() => {
          message = 'Studio layout autosaved. Mapping and overlay are synchronized.';
          error = '';
        }}
      />
    {/if}
  </div>
</StaffLayout>
