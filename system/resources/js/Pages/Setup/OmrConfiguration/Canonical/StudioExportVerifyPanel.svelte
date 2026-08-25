<script>
  import { onDestroy } from 'svelte';
  import { newIdempotencyKey } from '@/lib/utils';
  import {
    Camera,
    Upload,
    Video,
    Sparkles,
    Loader2,
    XCircle,
    ShieldCheck,
    Printer,
    ChevronLeft,
  } from 'lucide-svelte';

  let {
    draft = {},
    urls = {},
    getCsrfToken = () => '',
    lifecycleBusy = false,
    onBack = null,
  } = $props();
  let captureMode = $state('upload');
  let videoElement = $state(null);
  let canvasElement = $state(null);
  let fileInputElement = $state(null);
  let mediaStream = $state(null);
  let cameraActive = $state(false);
  let cameraFacing = $state('environment');
  let captureRunning = $state(false);
  let captureResult = $state(null);
  let captureFailure = $state(null);
  let selectedFileName = $state('');
  let lifecycleRunning = $state(false);
  let lifecycleNotice = $state('');
  let printerIdentity = $state('');
  let deviceIdentity = $state('');
  let measuredWidthMm = $state('');
  let measuredHeightMm = $state('');
  let physicalMatrixPassed = $state(false);
  let physicalQualified = $state(Boolean(draft.physical_qualified));

  onDestroy(() => {
    stopCameraStream();
  });

  async function startCameraStream() {
    if (!navigator.mediaDevices?.getUserMedia) {
      captureFailure = { code: 'camera_unsupported', detail: 'Camera access is not supported on this browser/device.' };
      return;
    }
    stopCameraStream();
    try {
      mediaStream = await navigator.mediaDevices.getUserMedia({
        video: {
          facingMode: cameraFacing,
          width: { ideal: 1920 },
          height: { ideal: 1080 },
        },
      });
      if (videoElement) {
        videoElement.srcObject = mediaStream;
        await videoElement.play();
      }
      cameraActive = true;
      captureFailure = null;
    } catch (err) {
      cameraActive = false;
      captureFailure = { code: 'camera_denied', detail: err.message || 'Camera permission was denied or device is busy.' };
    }
  }

  function stopCameraStream() {
    if (mediaStream) {
      mediaStream.getTracks().forEach((track) => track.stop());
      mediaStream = null;
    }
    cameraActive = false;
  }

  function toggleCameraFacing() {
    cameraFacing = cameraFacing === 'environment' ? 'user' : 'environment';
    startCameraStream();
  }

  async function captureSnapshot() {
    if (!videoElement || !canvasElement) return;
    const width = videoElement.videoWidth || 1280;
    const height = videoElement.videoHeight || 720;
    canvasElement.width = width;
    canvasElement.height = height;
    const ctx = canvasElement.getContext('2d');
    ctx.drawImage(videoElement, 0, 0, width, height);

    canvasElement.toBlob(async (blob) => {
      if (!blob) return;
      const file = new File([blob], `camera-snapshot-${Date.now()}.jpg`, { type: 'image/jpeg' });
      selectedFileName = file.name;
      await runTestCapture(file);
    }, 'image/jpeg', 0.92);
  }

  function onFileSelected(e) {
    const file = e.target.files?.[0];
    if (!file) return;
    selectedFileName = file.name;
    runTestCapture(file);
  }

  async function runTestCapture(file) {
    if (captureRunning || !file) return;
    if (!draft.compiled_version_id) {
      captureFailure = { code: 'compile_required', detail: 'Compile the digitally verified candidate before running physical Test Capture.' };
      return;
    }
    captureRunning = true;
    captureFailure = null;
    captureResult = null;

    const targetUrl = `/setup/omr/templates/${draft.compiled_version_id}/test-capture`;
    const formData = new FormData();
    formData.append('image', file);

    try {
      const res = await fetch(targetUrl, {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': getCsrfToken(),
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json',
        },
        credentials: 'same-origin',
        body: formData,
      });

      const body = await res.json().catch(() => ({}));
      if (res.ok) {
        captureResult = body;
        captureFailure = null;
      } else if (body?.outcome === 'failed') {
        captureFailure = { code: body.code ?? 'registration_failed', detail: body.detail ?? 'The fiducial markers could not be verified on the captured image.' };
      } else {
        captureFailure = { code: 'test_capture_rejected', detail: body?.error ?? body?.message ?? 'The test capture was rejected by the engine worker.' };
      }
    } catch (err) {
      captureFailure = { code: 'network_error', detail: err?.message ?? 'Test capture diagnostic worker is unreachable.' };
    } finally {
      captureRunning = false;
      if (fileInputElement) fileInputElement.value = '';
    }
  }
  async function runDigitalVerification() {
    if (lifecycleBusy || lifecycleRunning || !urls.digital_verification) return;
    lifecycleRunning = true;
    lifecycleNotice = '';
    try {
      const response = await fetch(urls.digital_verification, {
        method: 'POST',
        headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
        credentials: 'same-origin',
        body: JSON.stringify({ idempotency_key: newIdempotencyKey() }),
      });
      const body = await response.json().catch(() => ({}));
      if (!response.ok) throw new Error(body.detail || body.message || body.error || 'Digital verification failed.');
      draft.stage = body.status || 'digital_verified';
      draft.authoring = { ...(draft.authoring || {}), verification: body.verification };
      lifecycleNotice = 'Digital proof and real worker no-go matrix passed. Thresholds remain Unqualified.';
    } catch (error) {
      lifecycleNotice = error?.message || 'Digital verification failed.';
    } finally {
      lifecycleRunning = false;
    }
  }

  async function compileCandidate() {
    if (lifecycleBusy || lifecycleRunning || !urls.compile) return;
    lifecycleRunning = true;
    lifecycleNotice = '';
    try {
      const response = await fetch(urls.compile, {
        method: 'POST',
        headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
        credentials: 'same-origin',
        body: JSON.stringify({ idempotency_key: newIdempotencyKey() }),
      });
      const body = await response.json().catch(() => ({}));
      if (!response.ok) throw new Error(body.detail || body.message || body.error || 'Immutable compile failed.');
      draft.stage = body.status || 'compiled';
      draft.compiled_version_id = body.template_version_id;
      urls.native_template = body.template_version_id
        ? `/setup/omr/templates/${body.template_version_id}/native/template.json`
        : urls.native_template;
      lifecycleNotice = 'Immutable native version compiled. Physical Test Capture now uses that exact bundle.';
    } catch (error) {
      lifecycleNotice = error?.message || 'Immutable compile failed.';
    } finally {
      lifecycleRunning = false;
    }
  }
  async function qualifyPhysical() {
    if (lifecycleRunning || !draft.compiled_version_id || !printerIdentity || !deviceIdentity || !measuredWidthMm || !measuredHeightMm || !physicalMatrixPassed) return;
    lifecycleRunning = true;
    lifecycleNotice = '';
    try {
      const response = await fetch(`/setup/omr/templates/${draft.compiled_version_id}/verify/physical`, {
        method: 'POST',
        headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
        credentials: 'same-origin',
        body: JSON.stringify({
          printer: printerIdentity,
          device: deviceIdentity,
          paper: 'canonical',
          renderer_version: 'canonical-renderer-v1',
          measured_marker_center_mm: [Number(measuredWidthMm), Number(measuredHeightMm)],
          outcomes: ['pass'],
          idempotency_key: newIdempotencyKey(),
        }),
      });
      const body = await response.json().catch(() => ({}));
      if (!response.ok) throw new Error(body.message || body.error || 'Physical qualification failed.');
      physicalQualified = body.status === 'physical_qualified';
      draft.physical_qualified = physicalQualified;
      lifecycleNotice = physicalQualified ? 'Physical qualification recorded against this immutable version.' : 'Physical evidence was recorded but has not passed all gates.';
    } catch (error) {
      lifecycleNotice = error?.message || 'Physical qualification failed.';
    } finally {
      lifecycleRunning = false;
    }
  }

  async function activateVersion() {
    if (lifecycleRunning || !draft.compiled_version_id || !physicalQualified) return;
    lifecycleRunning = true;
    lifecycleNotice = '';
    try {
      const response = await fetch(`/setup/omr/templates/${draft.compiled_version_id}/activate`, {
        method: 'POST',
        headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
        credentials: 'same-origin',
        body: JSON.stringify({ idempotency_key: newIdempotencyKey() }),
      });
      const body = await response.json().catch(() => ({}));
      if (!response.ok) throw new Error(body.message || body.error || 'Activation failed.');
      lifecycleNotice = `Template version ${body.template_version_id} is now ${body.status}.`;
    } catch (error) {
      lifecycleNotice = error?.message || 'Activation failed.';
    } finally {
      lifecycleRunning = false;
    }
  }


  function openPrintSheet() {
    if (!draft.print_pdf_url) {
      captureFailure = { code: 'print_sheet_unavailable', detail: 'Run the recognition test before printing this candidate sheet.' };
      return;
    }
    window.open(draft.print_pdf_url, '_blank', 'noopener,noreferrer');
  }
</script>

<div class="p-6 space-y-6 text-foreground [&_input]:border-input [&_input]:bg-background [&_input]:text-foreground">
  <div>
    <h2 class="text-base font-bold text-foreground">Verification &amp; Test Capture</h2>
    <p class="text-xs text-muted-foreground mt-1">Print the candidate, run digital gates, then test a real camera or uploaded scan.</p>
  </div>

  <div class="rounded-xl border border-border bg-card p-3 space-y-2">
    <span class="text-xs font-bold uppercase tracking-wider text-foreground">Physical test sheet</span>
    <button
      type="button"
      onclick={openPrintSheet}
      disabled={!draft.print_pdf_url}
      class="w-full rounded-lg bg-slate-900 px-3 py-2 text-[11px] font-semibold text-white transition hover:bg-slate-800 disabled:opacity-40"
    >
      <span class="flex items-center justify-center gap-1.5"><Printer class="size-3.5" /> Open printable PDF</span>
    </button>
    <p class="text-[10px] leading-relaxed text-muted-foreground">Print at 100% / Actual Size. Do not use “Fit to page”.</p>
  </div>

  {#if !draft.compiled_version_id}
    <div class="p-3 rounded-xl bg-amber-50 border border-amber-200 text-[11px] text-amber-900">
      Physical Test Capture is locked until this draft passes digital verification and is compiled into an immutable native bundle.
    </div>
  {/if}

  <div class="p-3 rounded-xl border border-border bg-muted/40 space-y-2">
    <div class="flex items-center justify-between">
      <span class="text-xs font-bold text-foreground uppercase tracking-wider">Digital candidate lifecycle</span>
      {#if draft.compiled_version_id}
        <span class="text-[10px] font-mono text-emerald-700 dark:text-emerald-400">compiled v{draft.compiled_version_id}</span>
      {:else}
        <span class="text-[10px] font-mono text-muted-foreground">{draft.stage || 'mapping_complete'}</span>
      {/if}
    </div>
    <div class="grid grid-cols-2 gap-2">
      <button
        type="button"
        onclick={runDigitalVerification}
        disabled={lifecycleBusy || lifecycleRunning || draft.stage === 'digital_verified' || Boolean(draft.compiled_version_id)}
        class="py-2 rounded-lg bg-blue-700 text-white text-[11px] font-semibold disabled:opacity-40"
      >
        {lifecycleRunning && !draft.compiled_version_id ? 'Running worker matrix…' : 'Run digital verification'}
      </button>
      <button
        type="button"
        onclick={compileCandidate}
        disabled={lifecycleBusy || lifecycleRunning || draft.stage !== 'digital_verified' || Boolean(draft.compiled_version_id)}
        class="py-2 rounded-lg bg-slate-900 text-white text-[11px] font-semibold disabled:opacity-40"
      >
        Compile immutable version
      </button>
    </div>
    {#if lifecycleNotice}
      <div class="rounded-lg border border-border bg-card px-2.5 py-2 text-[11px] text-muted-foreground" role="status" aria-live="polite">
        {lifecycleNotice}
      </div>
    {/if}
  </div>

  {#if draft.compiled_version_id}
    <div class="p-3 rounded-xl border border-border bg-card space-y-3">
      <div class="flex items-center justify-between">
        <span class="text-xs font-bold text-foreground uppercase tracking-wider">Physical qualification</span>
      </div>
      <div class="grid grid-cols-2 gap-2">
        <input bind:value={printerIdentity} placeholder="Printer identity" class="rounded-lg border border-slate-200 px-2 py-1.5 text-xs" />
        <input bind:value={deviceIdentity} placeholder="Camera/scanner identity" class="rounded-lg border border-slate-200 px-2 py-1.5 text-xs" />
        <input bind:value={measuredWidthMm} type="number" min="1" step="0.01" placeholder="Marker width (mm)" class="rounded-lg border border-slate-200 px-2 py-1.5 text-xs" />
        <input bind:value={measuredHeightMm} type="number" min="1" step="0.01" placeholder="Marker height (mm)" class="rounded-lg border border-slate-200 px-2 py-1.5 text-xs" />
      </div>
      <label class="flex items-center gap-2 text-[11px] text-slate-700">
        <input type="checkbox" bind:checked={physicalMatrixPassed} />
        Physical matrix passed on the named printer/device/paper
      </label>
      <div class="grid grid-cols-2 gap-2">
        <button type="button" onclick={qualifyPhysical} disabled={lifecycleBusy || lifecycleRunning || physicalQualified || !physicalMatrixPassed} class="py-2 rounded-lg bg-emerald-700 text-white text-[11px] font-semibold disabled:opacity-40">Record qualification</button>
        <button type="button" onclick={activateVersion} disabled={lifecycleBusy || lifecycleRunning || !physicalQualified} class="py-2 rounded-lg bg-slate-900 text-white text-[11px] font-semibold disabled:opacity-40">Activate version</button>
      </div>
    </div>
  {/if}

  <!-- Dual-Input Mode Switcher -->
  <div class="space-y-3">
    <span class="text-xs font-bold text-slate-800 uppercase tracking-wider block">Test Capture Method</span>
    <div class="grid grid-cols-2 gap-2">
      <button
        type="button"
        onclick={() => {
          captureMode = 'camera';
          startCameraStream();
        }}
        class={`py-2 px-3 rounded-xl border text-center transition font-semibold text-xs flex items-center justify-center gap-1.5 ${
          captureMode === 'camera'
            ? 'border-slate-900 bg-slate-900 text-white shadow-xs'
            : 'border-slate-200 hover:border-slate-300 text-slate-700 bg-slate-50/50'
        }`}
        disabled={!draft.compiled_version_id || captureRunning}
      >
        <Camera class="size-3.5" />
        <span>Device Camera</span>
      </button>
      <button
        type="button"
        onclick={() => {
          captureMode = 'upload';
          stopCameraStream();
        }}
        class={`py-2 px-3 rounded-xl border text-center transition font-semibold text-xs flex items-center justify-center gap-1.5 ${
          captureMode === 'upload'
            ? 'border-slate-900 bg-slate-900 text-white shadow-xs'
            : 'border-slate-200 hover:border-slate-300 text-slate-700 bg-slate-50/50'
        }`}
      >
        <Upload class="size-3.5" />
        <span>Upload Scan / Photo</span>
      </button>
    </div>
  </div>

  <!-- Camera Stream Viewfinder (Option A) -->
  {#if captureMode === 'camera'}
    <div class="space-y-3 p-3 bg-slate-900 rounded-2xl text-white">
      <div class="flex items-center justify-between text-xs">
        <span class="font-semibold flex items-center gap-1.5 text-emerald-400">
          <Video class="size-3.5" />
          <span>Live Viewfinder</span>
        </span>
        <button
          type="button"
          onclick={toggleCameraFacing}
          class="text-[11px] px-2 py-0.5 bg-slate-800 hover:bg-slate-700 rounded text-slate-300"
        >
          Switch Camera
        </button>
      </div>

      <!-- Viewfinder Frame -->
      <div class="relative w-full aspect-4/3 bg-black rounded-xl overflow-hidden flex items-center justify-center border border-slate-800">
        <video
          bind:this={videoElement}
          autoplay
          playsinline
          muted
          class="w-full h-full object-cover"
        ></video>
        <canvas bind:this={canvasElement} class="hidden"></canvas>

        <!-- Alignment Overlay Target Frame -->
        <div class="absolute inset-4 border-2 border-dashed border-white/50 rounded-lg pointer-events-none flex items-center justify-center">
          <div class="w-8 h-8 border border-white/40 rounded-full flex items-center justify-center">
            <div class="w-1.5 h-1.5 bg-red-500 rounded-full animate-ping"></div>
          </div>
        </div>
      </div>

      <button
        type="button"
        onclick={captureSnapshot}
        disabled={captureRunning || !cameraActive}
        class="w-full py-2.5 bg-emerald-500 hover:bg-emerald-600 disabled:opacity-50 text-emerald-950 font-bold rounded-xl text-xs transition flex items-center justify-center gap-2 shadow-xs"
      >
        {#if captureRunning}
          <Loader2 class="size-3.5 animate-spin" />
          <span>Running Diagnostic…</span>
        {:else}
          <Camera class="size-3.5" />
          <span>Capture Snapshot & Verify</span>
        {/if}
      </button>
    </div>

  <!-- File Upload Dropzone (Option B) -->
  {:else}
    <div class="space-y-3">
      <input
        bind:this={fileInputElement}
        type="file"
        accept="image/jpeg,image/png,image/webp,application/pdf"
        onchange={onFileSelected}
        class="hidden"
      />
      <button
        type="button"
        onclick={() => fileInputElement?.click()}
        disabled={!draft.compiled_version_id || captureRunning}
        class="w-full p-6 border-2 border-dashed border-border hover:border-primary/50 rounded-2xl flex flex-col items-center justify-center text-center transition bg-muted/30 hover:bg-muted/50"
      >
        <Upload class="size-8 text-muted-foreground mb-2" />
        <span class="text-xs font-bold text-foreground">
          {selectedFileName ? selectedFileName : 'Choose Photo or Drag & Drop'}
        </span>
        <span class="text-[10px] text-muted-foreground mt-1">Accepts JPG, PNG, WebP or scanned PDF</span>
      </button>

      {#if selectedFileName}
        <button
          type="button"
          onclick={() => fileInputElement?.files?.[0] && runTestCapture(fileInputElement.files[0])}
          disabled={!draft.compiled_version_id || captureRunning}
          class="w-full py-2 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-xs font-semibold transition flex items-center justify-center gap-1.5"
        >
          {#if captureRunning}
            <Loader2 class="size-3.5 animate-spin" />
            <span>Processing OMRChecker Diagnostic…</span>
          {:else}
            <Sparkles class="size-3.5" />
            <span>Run Test Capture Diagnostic</span>
          {/if}
        </button>
      {/if}
    </div>
  {/if}

  <!-- Diagnostic Failure Alert -->
  {#if captureFailure}
    <div class="p-3 bg-red-50 border border-red-200 rounded-xl text-xs text-red-900 space-y-1">
      <div class="flex items-center gap-1.5 font-bold">
        <XCircle class="size-4 text-red-600 shrink-0" />
        <span>{captureFailure.code ? `Verification Rejected (${captureFailure.code})` : 'Diagnostic Failed'}</span>
      </div>
      <p class="text-[11px] text-red-800 leading-relaxed">{captureFailure.detail}</p>
    </div>
  {/if}

  <!-- Diagnostic Result Visualization -->
  {#if captureResult}
    <div class="space-y-4 p-4 bg-muted/40 rounded-2xl border border-border text-xs">
      <div class="flex items-center justify-between">
        <span class="font-bold text-foreground flex items-center gap-1.5">
          <ShieldCheck class="size-4 text-emerald-600 dark:text-emerald-400" />
          <span>OMRChecker Diagnostic Results</span>
        </span>
        <span class="font-mono text-[10px] text-muted-foreground">
          {captureResult.timings?.verification_ms ?? 0}ms
        </span>
      </div>

      <!-- Gate Badges -->
      <div class="flex flex-wrap gap-1.5">
        {#each ['marker_count', 'registration', 'dimensions', 'shifts'] as gateKey}
          {@const status = captureResult.gates?.[gateKey]}
          {#if status}
            <span class={`px-2 py-0.5 rounded-full font-semibold text-[10px] border flex items-center gap-1 ${
              status === 'pass' ? 'bg-emerald-100 text-emerald-800 border-emerald-300' : 'bg-red-100 text-red-800 border-red-300'
            }`}>
              <span class={`size-1.5 rounded-full ${status === 'pass' ? 'bg-emerald-500' : 'bg-red-500'}`}></span>
              <span class="capitalize">{gateKey.replace('_', ' ')}: {status}</span>
            </span>
          {/if}
        {/each}
      </div>

      <!-- Proof Image Tabs -->
      <div class="space-y-2">
        <div class="flex gap-1 border-b border-border pb-1">
          <button
            type="button"
            onclick={() => { activeProofTab = 'overlay'; }}
            class={`px-2.5 py-0.5 text-xs font-semibold rounded ${activeProofTab === 'overlay' ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-200'}`}
          >
            Engine Layout Overlay
          </button>
          <button
            type="button"
            onclick={() => { activeProofTab = 'aligned'; }}
            class={`px-2.5 py-0.5 text-xs font-semibold rounded ${activeProofTab === 'aligned' ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-200'}`}
          >
            Normalized Raster
          </button>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white aspect-4/3 flex items-center justify-center">
          {#if activeProofTab === 'overlay' && captureResult.overlay_url}
            <img src={captureResult.overlay_url} alt="Engine layout overlay" class="w-full h-full object-contain" />
          {:else if activeProofTab === 'aligned' && captureResult.aligned_url}
            <img src={captureResult.aligned_url} alt="Aligned clean raster" class="w-full h-full object-contain" />
          {/if}
        </div>
      </div>
    </div>
  {/if}


  <!-- Sticky Bottom Navigation Footer -->
  <div class="sticky bottom-0 bg-card border-t border-border p-4 -mx-6 -mb-6 flex items-center justify-start shadow-xs">
    <button
      type="button"
      onclick={onBack}
      class="px-3 py-2 border border-slate-200 hover:bg-slate-50 text-slate-700 font-semibold rounded-xl text-xs transition flex items-center gap-1.5"
    >
      <ChevronLeft class="size-3.5" />
      <span>Back to Recognition Mapping</span>
    </button>
  </div>
</div>
