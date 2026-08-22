<script>
  import { Button } from '@/Components/ui/button';
  import {
    AlertTriangle,
    ChevronDown,
    ChevronRight,
    FlaskConical,
    Loader2,
    ScanLine,
    ShieldCheck,
    Upload,
    XCircle,
  } from 'lucide-svelte';

  let {
    versionId,
    markerBearing = false,
    returnToMappingUrl = null,
  } = $props();

  let fileInput = $state(null);
  let selectedName = $state('');
  let running = $state(false);
  let result = $state(null); // success payload from the test-capture endpoint
  let failure = $state(null); // { code?, detail }
  let diagnosticsOpen = $state(false);
  let rastersFailed = $state(false);

  function csrfToken() {
    const el = document.querySelector('meta[name="csrf-token"]');
    return el instanceof HTMLMetaElement ? el.content : '';
  }

  function pickFile() {
    fileInput?.click();
  }

  function onFileSelected(event) {
    const file = event.currentTarget?.files?.[0];
    if (!file) return;
    selectedName = file.name;
    runCapture(file);
  }

  function resetRun() {
    running = false;
    result = null;
    failure = null;
    rastersFailed = false;
    diagnosticsOpen = false;
  }

  async function runCapture(file) {
    if (running || !file) return;
    resetRun();
    running = true;
    const form = new FormData();
    form.append('image', file);

    try {
      const res = await fetch(`/setup/omr/templates/${versionId}/test-capture`, {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': csrfToken(),
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
      if (fileInput) fileInput.value = '';
    }
  }

  function gateTone(gate) {
    return gate === 'pass' ? 'pass' : 'fail';
  }

  const gateOrder = ['marker_count', 'registration', 'dimensions', 'shifts'];

  function gateLabel(key) {
    return {
      marker_count: 'Marker count',
      registration: 'Registration',
      dimensions: 'Dimensions',
      shifts: 'Field-block shifts',
    }[key] ?? key;
  }

  const markerEntries = $derived(result?.marker_matches
    ? Object.entries(result.marker_matches)
        .sort(([a], [b]) => a.localeCompare(b))
    : []);

  const shiftEntries = $derived(Array.isArray(result?.field_block_shifts) ? result.field_block_shifts : []);

  const bubbleKeys = $derived(Object.keys(result?.bubble_means ?? {}));
  const visibleBubbleKeys = $derived(bubbleKeys.slice(0, 6));
  const hiddenBubbleCount = $derived(Math.max(0, bubbleKeys.length - visibleBubbleKeys.length));
  const itemEvidence = $derived(Array.isArray(result?.item_evidence) ? result.item_evidence : []);

  function meanText(value) {
    return value == null ? '—' : Number(value).toFixed(2);
  }

  function shortHash(value) {
    return value ? String(value).slice(0, 10) : '—';
  }

  function rasterTitle(kind) {
    const meta = kind === 'aligned' ? result?.aligned_image : result?.overlay_image;
    return meta
      ? `${meta.width ?? '?'}×${meta.height ?? '?'}px${meta.mime ? ` · ${meta.mime}` : ''} · ${shortHash(meta.hash)}`
      : '';
  }

  function mappingUrl(label) {
    if (!returnToMappingUrl) return null;
    const separator = returnToMappingUrl.includes('?') ? '&' : '?';
    return `${returnToMappingUrl}${separator}row=${encodeURIComponent(label ?? '')}`;
  }
</script>

{#if !markerBearing}
  <div class="rounded-xl border border-border/70 bg-card p-4">
    <div class="flex items-center gap-2 text-sm font-semibold text-foreground">
      <FlaskConical class="size-4" />
      Test capture
    </div>
    <div class="mt-2 flex items-start gap-2.5 rounded-lg border border-amber-300/60 bg-amber-50 p-3 text-xs text-amber-900">
      <AlertTriangle class="mt-0.5 size-4 shrink-0" />
      <div>
        <p class="font-semibold">This draft is a marker-less scan (CropPage) profile.</p>
        <p class="mt-1">
          Test capture runs the fiducial marker registration pipeline and requires the marker-bearing
          version of the template (four printed corner fiducials, <code class="font-mono">markers</code> in the
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
        Test capture
      </div>
      <span class="text-xs text-muted-foreground">Read-only diagnostic · no data is written</span>
    </div>

    <input
      class="hidden"
      bind:this={fileInput}
      type="file"
      accept="image/jpeg,image/png,image/webp"
      onchange={onFileSelected}
    />

    <div class="mt-3 flex flex-wrap items-center gap-2">
      <Button type="button" variant="outline" size="sm" onclick={pickFile} disabled={running}>
        <Upload class="mr-1.5 size-3.5" />
        {selectedName ? `Change photo (${selectedName})` : 'Choose a photo of the printed sheet'}
      </Button>
      <Button type="button" size="sm" onclick={() => fileInput?.files?.[0] && runCapture(fileInput.files[0])} disabled={running || !selectedName}>
        {running ? 'Running marker registration…' : 'Run test capture'}
      </Button>
      {#if running}
        <Loader2 class="size-4 animate-spin text-muted-foreground" />
      {/if}
    </div>
    <p class="mt-2 text-xs text-muted-foreground">
      Upload a photo of the printed marker-bearing sheet (JPEG, PNG, or WebP). The worker registers the
      fiducials and returns the normalized raster plus the engine layout overlay — nothing is saved, no
      activation, pair, answer key, or score changes.
    </p>

    {#if failure}
      <div class="mt-3 rounded-lg border border-red-300/70 bg-red-50 p-3 text-xs text-red-900" role="alert">
        <div class="flex items-center gap-2 font-semibold">
          <XCircle class="size-4 shrink-0" />
          {failure.code ? `Registration rejected — ${failure.code}` : 'Test capture failed'}
        </div>
        <p class="mt-1">{failure.detail}</p>
      </div>
    {/if}

    {#if result}
      <div class="mt-4 space-y-4">
        <div class="flex items-center gap-2 text-xs text-muted-foreground">
          <ShieldCheck class="size-4 text-emerald-600" />
          <span>
            Normalized raster verified ({rasterTitle('aligned')}) — the engine overlay is drawn from the
            persisted layout, not the browser.
          </span>
        </div>

        {#if rastersFailed}
          <p class="rounded-lg border border-amber-300/60 bg-amber-50 p-3 text-xs text-amber-900" role="alert">
            The diagnostic rasters could not be loaded from the private evidence store. Re-run the test or
            check that the version's bundle is intact.
          </p>
        {:else}
          <div class="grid gap-3 sm:grid-cols-2">
            <figure class="min-w-0">
              <div class="overflow-hidden rounded-lg border bg-black/5">
                <img
                  src={result.aligned_url}
                  alt="Normalized clean answer-sheet raster after marker registration"
                  class="mx-auto block max-h-96 w-auto"
                  loading="lazy"
                  decoding="async"
                  onerror={() => (rastersFailed = true)}
                />
              </div>
              <figcaption class="mt-1 text-center text-xs text-muted-foreground">
                Normalized clean raster
              </figcaption>
            </figure>
            <figure class="min-w-0">
              <div class="overflow-hidden rounded-lg border bg-black/5">
                <img
                  src={result.overlay_url}
                  alt="Engine layout overlay drawn over the registered page"
                  class="mx-auto block max-h-96 w-auto"
                  loading="lazy"
                  decoding="async"
                  onerror={() => (rastersFailed = true)}
                />
              </div>
              <figcaption class="mt-1 text-center text-xs text-muted-foreground">
                Engine layout overlay
              </figcaption>
            </figure>
          </div>
        {/if}

        <div class="flex flex-wrap items-center gap-1.5" role="list" aria-label="Registration gate chips">
          {#each gateOrder as key (key)}
            {@const gate = result?.gates?.[key]}
            {#if gate}
              <span
                class="inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs font-medium {gate === 'pass'
                  ? 'border-emerald-300/70 bg-emerald-50 text-emerald-800'
                  : 'border-red-300/70 bg-red-50 text-red-800'}"
                role="listitem"
                title={gate === 'pass' ? `${gateLabel(key)} passed` : `${gateLabel(key)} failed`}
              >
                <span class="size-1.5 rounded-full {gate === 'pass' ? 'bg-emerald-500' : 'bg-red-500'}"></span>
                {gateLabel(key)} · {gate}
              </span>
            {/if}
          {/each}
          {#each (result?.gates?.unsafe_blocks ?? []) as block (block)}
            <span
              class="inline-flex items-center gap-1 rounded-full border border-red-300/70 bg-red-50 px-2.5 py-0.5 font-mono text-xs text-red-800"
              role="listitem"
              title="Field block shifted far enough to cross the option pitch"
            >
              unsafe: {block}
            </span>
          {/each}
        </div>

        <div class="rounded-lg border border-amber-300/60 bg-amber-50 p-3 text-xs text-amber-900">
          <p class="font-semibold">Calibration status: {result?.calibration_status ?? 'unqualified'}</p>
          <p class="mt-1">
            Real-engine thresholds stay <span class="font-semibold">Unqualified</span> until the protected labeled
            regression and the physical acceptance matrix pass. This diagnostic never implies recognition
            confidence and cannot be used to accept a scan.
          </p>
        </div>

        {#if itemEvidence.length}
          <div class="rounded-lg border border-border/70 bg-card p-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
              <div>
                <p class="text-sm font-semibold text-foreground">Per-item engine evidence</p>
                <p class="text-xs text-muted-foreground">Declared options, normalized rectangles, crops, means, and threshold deltas. Read-only.</p>
              </div>
              {#if returnToMappingUrl}
                <Button href={mappingUrl(itemEvidence[0]?.label)} variant="outline" size="xs">Return to mapping and select row</Button>
              {/if}
            </div>
            <div class="mt-3 max-h-96 space-y-3 overflow-y-auto">
              {#each itemEvidence as item (item.label)}
                <article class="rounded border border-border/60 p-3">
                  <div class="flex items-center justify-between gap-2"><span class="font-mono text-xs font-semibold">{item.label}</span><span class="text-[11px] text-muted-foreground">Page {item.page ?? 1}</span></div>
                  <div class="mt-2 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                    {#each item.options ?? [] as option (option.option)}
                      <div class="rounded border border-border/50 bg-muted/20 p-2">
                        <div class="flex items-center justify-between"><span class="font-semibold">{option.option}</span><span class="text-[10px] font-medium text-amber-700">{option.threshold_status ?? 'Unqualified'}</span></div>
                        {#if option.crop_png_base64}<img src={`data:image/png;base64,${option.crop_png_base64}`} alt={`${item.label} option ${option.option} normalized crop`} class="mt-2 h-16 w-full rounded bg-white object-contain" />{/if}
                        <dl class="mt-2 space-y-1 text-[11px] text-muted-foreground"><div class="flex justify-between"><dt>Mean</dt><dd class="font-mono text-foreground">{meanText(option.bubble_mean)}</dd></div><div class="flex justify-between"><dt>Δ threshold</dt><dd class="font-mono text-foreground">{meanText(option.threshold_delta)}</dd></div></dl>
                      </div>
                    {/each}
                  </div>
                </article>
              {/each}
            </div>
          </div>
        {/if}

        <div class="rounded-lg border border-border/70">
          <button
            type="button"
            class="flex w-full items-center justify-between px-3 py-2 text-xs font-medium text-muted-foreground hover:text-foreground"
            onclick={() => (diagnosticsOpen = !diagnosticsOpen)}
            aria-expanded={diagnosticsOpen}
          >
            <span class="flex items-center gap-1.5">
              <ScanLine class="size-3.5" />
              Marker scores, field-block shifts & bounded per-bubble means
            </span>
            {#if diagnosticsOpen}
              <ChevronDown class="size-3.5" />
            {:else}
              <ChevronRight class="size-3.5" />
            {/if}
          </button>

          {#if diagnosticsOpen}
            <div class="space-y-4 border-t border-border/60 p-3 text-xs">
              <div>
                <p class="font-semibold text-foreground">Marker matches (TM_CCOEFF_NORMED per quadrant)</p>
                {#if markerEntries.length}
                  <dl class="mt-1.5 grid grid-cols-2 gap-x-6 gap-y-1 sm:grid-cols-4">
                    {#each markerEntries as [key, value]}
                      <div class="flex items-center justify-between gap-2 rounded border border-border/50 px-2 py-1">
                        <dt class="text-muted-foreground">{key}</dt>
                        <dd class="font-mono">{Number(value).toFixed(3)}</dd>
                      </div>
                    {/each}
                  </dl>
                {:else}
                  <p class="mt-1 text-muted-foreground">No per-quadrant scores were reported.</p>
                {/if}
              </div>

              <div>
                <p class="font-semibold text-foreground">Field-block shifts</p>
                {#if shiftEntries.length}
                  <div class="mt-1.5 overflow-x-auto rounded border border-border/50">
                    <table class="w-full text-left">
                      <thead class="bg-muted/40 text-muted-foreground">
                        <tr>
                          <th class="px-2 py-1 font-medium">Block</th>
                          <th class="px-2 py-1 font-medium">Δx</th>
                          <th class="px-2 py-1 font-medium">Δy</th>
                        </tr>
                      </thead>
                      <tbody class="divide-y divide-border/50">
                        {#each shiftEntries as shift (shift.block)}
                          <tr>
                            <td class="px-2 py-1 font-mono">{shift.block}</td>
                            <td class="px-2 py-1 font-mono">{shift.x ?? '—'}</td>
                            <td class="px-2 py-1 font-mono">{shift.y ?? '—'}</td>
                          </tr>
                        {/each}
                      </tbody>
                    </table>
                  </div>
                {:else}
                  <p class="mt-1 text-muted-foreground">No field-block shifts were reported.</p>
                {/if}
              </div>

              <div>
                <p class="font-semibold text-foreground">Per-bubble means (bounded view)</p>
                {#if visibleBubbleKeys.length}
                  <div class="mt-1.5 max-h-72 overflow-y-auto rounded border border-border/50">
                    <table class="w-full text-left">
                      <thead class="sticky top-0 bg-muted/40 text-muted-foreground">
                        <tr>
                          <th class="px-2 py-1 font-medium">Label</th>
                          <th class="px-2 py-1 font-medium">Option</th>
                          <th class="px-2 py-1 font-medium">Mean</th>
                        </tr>
                      </thead>
                      <tbody class="divide-y divide-border/50">
                        {#each visibleBubbleKeys as label (label)}
                          {#each (result.bubble_means?.[label] ?? []) as bubble (label + (bubble.option ?? ''))}
                            <tr>
                              <td class="px-2 py-1 font-mono">{bubble.label ?? label}</td>
                              <td class="px-2 py-1">{bubble.option ?? '—'}</td>
                              <td class="px-2 py-1 font-mono">{meanText(bubble.mean)}</td>
                            </tr>
                          {/each}
                        {/each}
                      </tbody>
                    </table>
                  </div>
                  {#if hiddenBubbleCount > 0}
                    <p class="mt-1 text-muted-foreground">
                      …and {hiddenBubbleCount} more label{hiddenBubbleCount === 1 ? '' : 's'} omitted from this bounded view.
                    </p>
                  {/if}
                {:else}
                  <p class="mt-1 text-muted-foreground">No per-bubble means were reported.</p>
                {/if}
              </div>

              {#if result?.timings}
                <p class="text-muted-foreground">
                  Timing — preprocess: {(result.timings.preprocess_ms ?? 0).toFixed(0)} ms · verification:{' '}
                  {(result.timings.verification_ms ?? 0).toFixed(0)} ms
                </p>
              {/if}
            </div>
          {/if}
        </div>
      </div>
    {/if}
  </div>
{/if}
