<script>
  import { AlertTriangle, FlaskConical } from 'lucide-svelte';
  import OmrCaptureInput from '@/Components/Omr/Diagnostics/OmrCaptureInput.svelte';
  import OmrDiagnosticEvidencePanel from '@/Components/Omr/Diagnostics/OmrDiagnosticEvidencePanel.svelte';
  import { csrfHeaders } from '@/lib/utils.js';

  let {
    versionId,
    markerBearing = false,
    bookletVersionId = null,
    setCode = null,
    returnToMappingUrl = null,
  } = $props();

  let running = $state(false);
  let result = $state(null); // success payload from the test-capture endpoint
  let failure = $state(null); // { code?, detail }

  // Adapt the legacy read-only test-capture response into the bounded shared
  // page shape. No image bytes or workflow mutation cross this boundary.
  const evidencePage = $derived(result ? {
    method: 'pre_shaded',
    capture_id: Number(result.capture_id ?? result.id ?? 1),
    area: 'all',
    total: Array.isArray(result.items ?? result.item_evidence) ? (result.items ?? result.item_evidence).length : 0,
    mismatch_total: 0,
    page: 1,
    per_page: 25,
    page_count: 1,
    filter: 'all',
    evidence_version: result.evidence_version ?? 'legacy-test-capture',
    evidence_hash: result.evidence_hash ?? null,
    capture_page_no: 1,
    groups: [],
    items: result.items ?? result.item_evidence ?? [],
    lazy_asset_urls: {
      input: result.input_url ?? result.captured_url ?? null,
      aligned: result.aligned_url ?? null,
      overlay: result.overlay_url ?? null,
    },
  } : null);


  function resetRun() {
    result = null;
    failure = null;
  }

  async function runCapture(file) {
    if (running || !file) return;
    resetRun();
    running = true;
    const form = new FormData();
    form.append('image', file);
    if (bookletVersionId) form.append('booklet_version_id', String(bookletVersionId));
    if (setCode) form.append('set_code', String(setCode));

    try {
      const res = await fetch(`/setup/omr/templates/${versionId}/test-capture`, {
        method: 'POST',
        headers: {
          ...csrfHeaders(),
          'X-Requested-With': 'XMLHttpRequest',
          Accept: 'application/json',
        },
        credentials: 'same-origin',
        body: form,
      });
      const body = await res.json().catch(() => ({}));

      if (res.ok) {
        result = body;
      } else if (body?.outcome === 'failed') {
        failure = { code: body.code ?? null, detail: body.detail ?? 'The marker registration could not be verified.' };
      } else {
        failure = { code: null, detail: body?.error ?? body?.message ?? 'The test capture request was rejected.' };
      }
    } catch {
      failure = { code: null, detail: 'The test capture service is unavailable.' };
    } finally {
      running = false;
    }
  }
</script>

{#if !markerBearing}
  <div class="rounded-xl border border-border/70 bg-card p-4">
    <div class="flex items-center gap-2 text-sm font-semibold text-foreground">
      <FlaskConical class="size-4" />
      Dry Run
    </div>
    <div class="mt-2 flex items-start gap-2.5 rounded-lg border border-amber-300/60 bg-amber-50 p-3 text-xs text-amber-900">
      <AlertTriangle class="mt-0.5 size-4 shrink-0" />
      <div>
        <p class="font-semibold">This draft is a marker-less scan (CropPage) profile.</p>
        <p class="mt-1">
          Dry Run uses the fiducial marker registration pipeline and requires a marker-bearing
          version of the answer sheet (four printed corner fiducials and marker geometry in the
          blueprint). It is not available for scan-only profiles.
        </p>
      </div>
    </div>
  </div>
{:else}
  <div class="rounded-xl border border-border/70 bg-card p-4">
    <div class="flex items-center justify-between gap-2">
      <div class="flex items-center gap-2 text-sm font-semibold text-foreground">
        <FlaskConical class="size-4" />
        Dry Run
        {#if result?.rectification?.applied}
          <span class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-medium text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
            Rectified quad recovery
          </span>
        {:else if result?.rectification?.attempted}
          <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-medium text-amber-800 dark:bg-amber-950 dark:text-amber-300">
            Rectification attempted
          </span>
        {/if}
      </div>
      <span class="text-xs text-muted-foreground">Setup diagnostic · no production data is written</span>
    </div>
    {#if result?.test_context}
      <div class="mt-3 flex flex-wrap items-center gap-2 rounded-lg border border-border/70 bg-muted/40 px-3 py-2 text-xs">
        <strong>{result.test_context.booklet_name} · Set {result.test_context.set_code}</strong>
        {#if result.score_projection}
          <span class="text-muted-foreground">
            {result.score_projection.correct}/{result.score_projection.total} correct ·
            {result.score_projection.incorrect} incorrect ·
            {result.score_projection.unanswered} unanswered
          </span>
        {/if}
      </div>
    {/if}
    <div class="mt-3">
      <OmrCaptureInput
        submit={runCapture}
        busy={running}
        autoSubmit
        accept={['image/jpeg', 'image/png', 'image/webp']}
        labels={{
          runLabel: 'Run Dry Run',
          busyLabel: 'Running marker registration…',
          captureLabel: 'Capture snapshot & verify',
        }}
      />
    </div>

    <p class="mt-2 text-xs text-muted-foreground">
      Upload a photo of the printed marker-bearing answer sheet (JPEG, PNG, or WebP). The worker
      registers the fiducials and returns the normalized raster plus the engine layout overlay.
      This setup diagnostic cannot create grading scans or change activation, qualification,
      answer keys, or scores.
    </p>

    <div class="mt-3">
      <OmrDiagnosticEvidencePanel page={evidencePage} error={failure} returnUrl={returnToMappingUrl} />
    </div>
  </div>
{/if}
