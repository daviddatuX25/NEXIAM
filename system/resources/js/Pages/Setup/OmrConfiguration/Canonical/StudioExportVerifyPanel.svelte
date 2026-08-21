<script>
  import { onMount, onDestroy } from 'svelte';
  import {
    Camera,
    Upload,
    Video,
    Sparkles,
    Loader2,
    XCircle,
    ShieldCheck,
    Download,
    Copy,
    Check,
  } from 'lucide-svelte';

  let {
    draft = {},
    urls = {},
    jsonSchema = {},
    getCsrfToken = () => '',
  } = $props();

  let captureMode = $state('upload'); // 'camera' | 'upload'
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
  let activeProofTab = $state('overlay'); // 'overlay' | 'aligned'
  let copied = $state(false);

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
    captureRunning = true;
    captureFailure = null;
    captureResult = null;

    const versionId = draft.compiled_version_id;
    const targetUrl = versionId
      ? `/setup/omr/templates/${versionId}/test-capture`
      : (urls.test_capture || `/setup/omr/templates/canonical/drafts/${draft.id}/test-capture`);

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

  function copyJSON() {
    navigator.clipboard.writeText(JSON.stringify(jsonSchema, null, 2));
    copied = true;
    setTimeout(() => (copied = false), 2000);
  }

  function downloadJSON() {
    const dataStr = 'data:text/json;charset=utf-8,' + encodeURIComponent(JSON.stringify(jsonSchema, null, 2));
    const dl = document.createElement('a');
    dl.setAttribute('href', dataStr);
    dl.setAttribute('download', `omr_template_${draft.code || 'sheet'}.json`);
    dl.click();
  }
</script>

<div class="p-6 space-y-6">
  <div>
    <h2 class="text-base font-bold text-slate-900">Verification & Export</h2>
    <p class="text-xs text-slate-500 mt-1">Dual-input test capture (camera or file upload) and production deliverables.</p>
  </div>

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
        class="w-full py-2.5 bg-emerald-500 hover:bg-emerald-600 disabled:opacity-50 text-slate-950 font-bold rounded-xl text-xs transition flex items-center justify-center gap-2 shadow-xs"
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
        disabled={captureRunning}
        class="w-full p-6 border-2 border-dashed border-slate-300 hover:border-slate-400 rounded-2xl flex flex-col items-center justify-center text-center transition bg-slate-50/50 hover:bg-slate-50"
      >
        <Upload class="size-8 text-slate-400 mb-2" />
        <span class="text-xs font-bold text-slate-800">
          {selectedFileName ? selectedFileName : 'Choose Photo or Drag & Drop'}
        </span>
        <span class="text-[10px] text-slate-500 mt-1">Accepts JPG, PNG, WebP or scanned PDF</span>
      </button>

      {#if selectedFileName}
        <button
          type="button"
          onclick={() => fileInputElement?.files?.[0] && runTestCapture(fileInputElement.files[0])}
          disabled={captureRunning}
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
    <div class="space-y-4 p-4 bg-slate-50 rounded-2xl border border-slate-200 text-xs">
      <div class="flex items-center justify-between">
        <span class="font-bold text-slate-900 flex items-center gap-1.5">
          <ShieldCheck class="size-4 text-emerald-600" />
          <span>OMRChecker Diagnostic Results</span>
        </span>
        <span class="font-mono text-[10px] text-slate-500">
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
        <div class="flex gap-1 border-b border-slate-200 pb-1">
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

  <!-- Dual Production Deliverables -->
  <div class="space-y-3 border-t border-slate-100 pt-4">
    <span class="text-xs font-bold text-slate-800 uppercase tracking-wider block">Production Deliverables</span>
    <button
      type="button"
      onclick={downloadJSON}
      class="w-full py-2.5 border border-slate-200 hover:bg-slate-50 text-slate-800 rounded-xl text-xs font-semibold transition flex items-center justify-center space-x-2"
    >
      <Download class="size-3.5" />
      <span>Download template.json (OMRChecker Native)</span>
    </button>
  </div>

  <div class="space-y-2 border-t border-slate-100 pt-4">
    <div class="flex items-center justify-between">
      <span class="text-xs font-bold text-slate-800 uppercase tracking-wider block">Generated JSON Schema</span>
      <button onclick={copyJSON} class="text-[11px] text-slate-600 hover:text-slate-900 flex items-center gap-1 font-medium">
        {#if copied}
          <Check class="size-3 text-emerald-600" />
          <span class="text-emerald-600">Copied!</span>
        {:else}
          <Copy class="size-3" />
          <span>Copy JSON</span>
        {/if}
      </button>
    </div>
    <pre class="p-3 bg-slate-900 text-emerald-400 font-mono text-[10px] rounded-xl overflow-x-auto max-h-44 leading-relaxed">{JSON.stringify(jsonSchema, null, 2)}</pre>
  </div>
</div>
