<script>
  import { onDestroy } from 'svelte';
  import { Camera, Loader2, RefreshCw, Upload } from 'lucide-svelte';
  import {
    acceptedCopy,
    cameraUnsupportedMessage,
    DEFAULT_ACCEPT,
    DEFAULT_MAX_BYTES,
    initialCaptureMode,
    preflightTone,
    snapshotFileName,
    supportsGetUserMedia,
    validateFile,
  } from '../../../lib/omrCaptureInput.js';

  let {
    submit = null, // (file: File) => void | Promise<void> — the ONLY outward action
    busy = false,
    disabled = false,
    disabledReason = 'Capture is unavailable until the prerequisite step is complete.',
    accept = DEFAULT_ACCEPT,
    maxBytes = DEFAULT_MAX_BYTES,
    defaultMode = 'upload',
    cameraEnabled = true,
    autoSubmit = false,
    preflight = null,
    preflightError = '',
    labels = {},
  } = $props();

  let mode = $state(initialCaptureMode({ cameraEnabled, supports: supportsGetUserMedia(navigator?.mediaDevices) }) === 'camera' ? defaultMode : 'upload');
  let videoEl = $state(null);
  let canvasEl = $state(null);
  let fileInputEl = $state(null);
  let stream = $state(null);
  let cameraState = $state('idle');
  let cameraError = $state(null);
  let facing = $state('environment');
  let selectedFile = $state(null);
  let fileError = $state('');
  let dropActive = $state(false);
  let cameraEverFailed = $state(false);

  const preflightToneValue = $derived(preflightTone(preflight));

  function stopCamera() {
    if (stream) {
      stream.getTracks().forEach((track) => track.stop());
    }
    stream = null;
    if (videoEl) videoEl.srcObject = null;
    cameraState = 'idle';
  }

  async function startCamera() {
    if (disabled) return;
    if (!supportsGetUserMedia(navigator?.mediaDevices)) {
      cameraState = 'unavailable';
      cameraError = cameraUnsupportedMessage();
      cameraEverFailed = true;
      return;
    }
    stopCamera();
    cameraState = 'requesting';
    cameraError = null;
    try {
      stream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: facing, width: { ideal: 1920 }, height: { ideal: 1080 } },
      });
      if (videoEl) {
        videoEl.srcObject = stream;
        await videoEl.play();
      }
      cameraState = 'ready';
    } catch (err) {
      cameraState = 'unavailable';
      cameraError = {
        code: 'camera_denied',
        detail: err?.message || 'Camera permission was denied or the device is busy.',
      };
      cameraEverFailed = true;
    }
  }

  function selectCamera() {
    if (disabled) return;
    mode = 'camera';
    startCamera();
  }

  function handleUploadClick() {
    mode = 'upload';
    stopCamera();
    pickFile();
  }

  function selectUpload() {
    mode = 'upload';
    stopCamera();
  }

  function toggleFacing() {
    facing = facing === 'environment' ? 'user' : 'environment';
    startCamera();
  }

  function captureSnapshot() {
    if (cameraState !== 'ready' || busy || !videoEl || !canvasEl) return;
    const width = videoEl.videoWidth || 1280;
    const height = videoEl.videoHeight || 720;
    canvasEl.width = width;
    canvasEl.height = height;
    const context = canvasEl.getContext('2d');
    context.drawImage(videoEl, 0, 0, width, height);
    canvasEl.toBlob((blob) => {
      if (!blob) return;
      const file = new File([blob], snapshotFileName(Date.now()), { type: 'image/jpeg' });
      submitFile(file);
    }, 'image/jpeg', 0.92);
  }

  function pickFile() {
    fileInputEl?.click();
  }

  function onFileSelected(event) {
    const file = event.currentTarget?.files?.[0];
    handleFile(file ?? null);
    if (fileInputEl) fileInputEl.value = '';
  }

  function onDrop(event) {
    event.preventDefault();
    dropActive = false;
    if (disabled || busy) return;
    const file = event.dataTransfer?.files?.[0];
    handleFile(file ?? null);
  }

  function handleFile(file) {
    if (!file) return;
    const validation = validateFile(file, { accept, maxBytes });
    if (!validation.ok) {
      fileError = validation.message;
      return;
    }
    fileError = '';
    selectedFile = file;
    if (autoSubmit) submitFile(file);
  }

  function submitFile(file) {
    if (busy || disabled || typeof submit !== 'function') return;
        submit(file);
  }

  function runSelected() {
    if (!selectedFile) return;
    submitFile(selectedFile);
  }

  function clearSelected() {
    selectedFile = null;
    fileError = '';
  }

  function useUploadFallback() {
    selectUpload();
  }

  onDestroy(() => {
    stopCamera();
  });
</script>

<div class="omr-ci">
  <input
    class="omr-ci-hidden-input"
    bind:this={fileInputEl}
    type="file"
    accept={accept.join(',')}
    onchange={onFileSelected}
  />
  <div class="omr-ci-modes" role="group" aria-label="Capture method">
    <button
      type="button"
      class:active={mode === 'camera'}
      onclick={selectCamera}
      disabled={disabled || busy}
    >
      <Camera class="omr-ci-icon" size={14} />
      {labels.cameraLabel ?? 'Device Camera'}
    </button>
    <button
      type="button"
      class:active={mode === 'upload'}
      onclick={handleUploadClick}
      ondrop={onDrop}
      ondragover={(event) => {
        event.preventDefault();
        if (!disabled && !busy) dropActive = true;
      }}
      ondragleave={() => (dropActive = false)}
      disabled={disabled || busy}
    >
      <Upload class="omr-ci-icon" size={14} />
      {selectedFile
        ? `${labels.changeLabel ?? 'Change photo'} (${selectedFile.name})`
        : labels.chooseLabel ?? 'Choose Photo or Drag & Drop'}
    </button>
  </div>

  {#if disabled}
    <p class="omr-ci-disabled" role="note">{labels.disabledHint ?? disabledReason}</p>
  {/if}

  {#if mode === 'camera'}
    <div class="omr-ci-camera">
      <div class="omr-ci-camera-head">
        <span class="omr-ci-camera-title">Live Viewfinder</span>
        <button type="button" onclick={toggleFacing} disabled={disabled || busy || cameraState !== 'ready'} class="omr-ci-small-btn">
          {labels.switchCameraLabel ?? 'Switch Camera'}
        </button>
      </div>

      <div class="omr-ci-viewfinder">
        <video bind:this={videoEl} autoplay playsinline muted></video>
        <canvas bind:this={canvasEl} class="omr-ci-canvas"></canvas>
        <div class="omr-ci-target" aria-hidden="true">
          <div class="omr-ci-target-ring"></div>
        </div>
      </div>

      {#if cameraState === 'unavailable'}
        <div class="omr-ci-camera-error" role="alert">
          <p class="omr-ci-camera-error-title">{cameraError?.code === 'camera_unsupported' ? 'Camera unavailable' : 'Camera could not be opened'}</p>
          <p class="omr-ci-camera-error-detail">{cameraError?.detail ?? 'Camera access is unavailable.'}</p>
          <div class="omr-ci-camera-error-actions">
            <button type="button" class="omr-ci-small-btn" onclick={startCamera} disabled={busy}>
              <RefreshCw class="omr-ci-icon" size={12} />
              {labels.retryLabel ?? 'Retry camera'}
            </button>
            <button type="button" class="omr-ci-small-btn" onclick={useUploadFallback} disabled={busy}>
              <Upload class="omr-ci-icon" size={12} />
              {labels.uploadFallbackLabel ?? 'Use file upload instead'}
            </button>
          </div>
        </div>
      {:else}
        <button
          type="button"
          class="omr-ci-primary"
          onclick={captureSnapshot}
          disabled={busy || disabled || cameraState !== 'ready'}
        >
          {#if busy}
            <Loader2 class="omr-ci-spin" size={14} />
            {labels.busyLabel ?? 'Running…'}
          {:else}
            <Camera class="omr-ci-icon" size={14} />
            {labels.captureLabel ?? 'Capture Snapshot'}
          {/if}
        </button>
      {/if}
    </div>
  {/if}

  {#if selectedFile}
      <div class="omr-ci-selected">
        <span class="omr-ci-selected-name" title={selectedFile.name}>{selectedFile.name}</span>
        <button type="button" class="omr-ci-small-btn" onclick={clearSelected}>Clear</button>
      </div>
      {#if !autoSubmit}
        <button type="button" class="omr-ci-primary" onclick={runSelected} disabled={busy || disabled}>
          {#if busy}
            <Loader2 class="omr-ci-spin" size={14} />
            {labels.busyLabel ?? 'Running…'}
          {:else}
            <Upload class="omr-ci-icon" size={14} />
            {labels.runLabel ?? 'Run Test Capture'}
          {/if}
        </button>
      {/if}
    {/if}

  {#if fileError}
    <p class="omr-ci-file-error" role="alert">{fileError}</p>
  {/if}

  {#if preflightToneValue === 'validated'}
    <p class="omr-ci-preflight ok" role="status">Preflight validated — ready to upload.</p>
  {:else if preflightToneValue === 'rejected'}
    <p class="omr-ci-preflight fail" role="alert">Preflight rejected — retake or retry before uploading.</p>
  {:else if preflightToneValue === 'scan'}
    <p class="omr-ci-preflight neutral" role="status">Scan profile — preflight not applicable; the normal upload path applies.</p>
  {/if}
  {#if preflightError}
    <p class="omr-ci-preflight fail" role="alert">{preflightError}</p>
  {/if}
</div>

<style>
  .omr-ci {
    display: flex;
    flex-direction: column;
    gap: 10px;
    font: 12px var(--font-sans, system-ui, sans-serif);
    color: var(--omr-text, #1c2530);
  }
  .omr-ci-modes {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
  }
  .omr-ci-modes button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 9px 10px;
    border: 1px solid var(--omr-border, #d8dee6);
    border-radius: 10px;
    background: var(--omr-surface-soft, #f8fafc);
    color: var(--omr-muted, #64748b);
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    transition: border-color 0.15s ease, background 0.15s ease, color 0.15s ease;
  }
  .omr-ci-modes button.active {
    border-color: var(--omr-text, #1c2530);
    background: var(--omr-text, #1c2530);
    color: var(--omr-surface-solid, #fff);
  }
  .omr-ci-modes button:disabled { opacity: 0.45; cursor: not-allowed; }
  .omr-ci-icon { flex-shrink: 0; }
  .omr-ci-spin { animation: omr-ci-spin 0.9s linear infinite; }
  @keyframes omr-ci-spin { to { transform: rotate(360deg); } }

  .omr-ci-disabled {
    margin: 0;
    padding: 10px 12px;
    border: 1px solid color-mix(in srgb, #b7791f 55%, transparent);
    border-radius: 8px;
    background: color-mix(in srgb, #b7791f 8%, transparent);
    color: #966515;
    font-size: 11px;
    line-height: 1.5;
  }

  .omr-ci-camera {
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 12px;
    border-radius: 14px;
    background: var(--omr-stage-deep, #16211a);
    color: var(--omr-surface-solid, #fff);
  }
  .omr-ci-camera-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 12px;
  }
  .omr-ci-camera-title { font-weight: 700; color: var(--omr-success, #3fdd96); }
  .omr-ci-viewfinder {
    position: relative;
    aspect-ratio: 4 / 3;
    border-radius: 10px;
    overflow: hidden;
    background: #000;
    display: grid;
    place-items: center;
  }
  .omr-ci-viewfinder video { width: 100%; height: 100%; object-fit: cover; }
  .omr-ci-canvas { display: none; }
  .omr-ci-target {
    position: absolute;
    inset: 14px;
    border: 2px dashed rgba(255, 255, 255, 0.5);
    border-radius: 10px;
    pointer-events: none;
    display: grid;
    place-items: center;
  }
  .omr-ci-target-ring {
    width: 30px;
    height: 30px;
    border: 1px solid rgba(255, 255, 255, 0.4);
    border-radius: 999px;
    display: grid;
    place-items: center;
  }
  .omr-ci-target-ring::after {
    content: '';
    width: 6px;
    height: 6px;
    border-radius: 999px;
    background: #ef4444;
  }

  .omr-ci-primary {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    width: 100%;
    padding: 10px 12px;
    border: 0;
    border-radius: 10px;
    background: var(--omr-success, #10b981);
    color: #06281c;
    font-size: 12px;
    font-weight: 800;
    cursor: pointer;
  }
  .omr-ci-primary:disabled { opacity: 0.5; cursor: not-allowed; }

  .omr-ci-small-btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 10px;
    border: 1px solid var(--omr-border, #d8dee6);
    border-radius: 7px;
    background: var(--omr-surface-soft, #f8fafc);
    color: var(--omr-muted, #64748b);
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
  }
  .omr-ci-small-btn:hover { color: var(--omr-text, #1c2530); }
  .omr-ci-small-btn:disabled { opacity: 0.5; cursor: not-allowed; }

  .omr-ci-camera-error {
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding: 10px 12px;
    border: 1px solid color-mix(in srgb, #b94a48 55%, transparent);
    border-radius: 10px;
    background: color-mix(in srgb, #b94a48 10%, transparent);
    color: #ffd7d5;
  }
  .omr-ci-camera-error-title { margin: 0; font-weight: 700; font-size: 12px; }
  .omr-ci-camera-error-detail { margin: 0; font-size: 11px; line-height: 1.5; }
  .omr-ci-camera-error-actions { display: flex; gap: 8px; flex-wrap: wrap; }

  .omr-ci-hidden-input { display: none; }
  .omr-ci-selected {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 8px 10px;
    border: 1px solid var(--omr-border, #d8dee6);
    border-radius: 8px;
    background: var(--omr-surface-solid, #fff);
  }
  .omr-ci-selected-name {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font: 11px var(--font-mono, monospace);
  }

  .omr-ci-file-error {
    margin: 0;
    padding: 9px 11px;
    border: 1px solid color-mix(in srgb, #b94a48 55%, transparent);
    border-radius: 8px;
    background: color-mix(in srgb, #b94a48 8%, transparent);
    color: var(--omr-danger-strong, #a33c3a);
    font-size: 11px;
    line-height: 1.5;
  }

  .omr-ci-preflight {
    margin: 0;
    padding: 8px 11px;
    border-radius: 8px;
    font-size: 11px;
    line-height: 1.5;
  }
  .omr-ci-preflight.ok { border: 1px solid color-mix(in srgb, #2f8f62 50%, transparent); background: color-mix(in srgb, #2f8f62 8%, transparent); color: #26724f; }
  .omr-ci-preflight.fail { border: 1px solid color-mix(in srgb, #b94a48 55%, transparent); background: color-mix(in srgb, #b94a48 8%, transparent); color: #a33c3a; }
  .omr-ci-preflight.neutral { border: 1px solid var(--omr-border, #d8dee6); background: var(--omr-surface-soft, #f8fafc); color: var(--omr-muted, #64748b); }
</style>
