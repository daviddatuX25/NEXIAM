<script>
  import { onDestroy, onMount } from 'svelte';
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
  import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
  import { Button } from '@/Components/ui/button';
  import { Input } from '@/Components/ui/input';
  import { Label } from '@/Components/ui/label';
  import { Link, router } from '@inertiajs/svelte';
  import { ArrowLeft, Camera, CheckCircle2, FileImage, ScanLine, ShieldCheck, Upload, XCircle } from 'lucide-svelte';
  import { newIdempotencyKey } from '@/lib/utils.js';

  let {
    applicant_number = '',
    outcome = null,
    candidates = [],
    recovery = null,
    pending_capture = null,
    capture_url = '/scoring/omr/captures',
  } = $props();

  let fileInput;
  let videoEl;
  let cameraStream = $state(null);
  let cameraState = $state('starting');
  let uploading = $state(false);
  let uploadProgress = $state(0);
  let retrying = $state(false);
  let adopting = $state(false);
  let resolvingApplicant = $state(false);
  let selectingSession = $state(false);
  let resolvingDisplayCode = $state(false);
  let applicantNumber = $state('');
  let displayCode = $state('');
  let manualConfirmed = $state(false);

  const identityRecoveryCodes = [
    'applicant_unresolved',
    'identity_unresolved',
    'identity_conflict',
    'unreadable',
  ];

  const pendingActions = $derived(pending_capture?.actions ?? {});
  const pendingCandidates = $derived(
    pending_capture?.candidates?.length ? pending_capture.candidates : candidates,
  );
  const resolvedSession = $derived(pending_capture?.status === 'ready');
  const manualIdentityAllowed = $derived(
    pending_capture !== null && identityRecoveryCodes.includes(pending_capture.resolution_code),
  );
  const displayCodeAllowed = $derived(
    pending_capture !== null &&
      ['absent', 'undecodable'].includes(pending_capture.qr_state) &&
      recovery?.secondary_action === 'enter_display_code',
  );

  const scannerContext = $derived.by(() => {
    const entityKey = String(pending_capture?.id ?? 'station');
    const isBlk = recovery !== null;
    const safetyKey = `${pending_capture ? pending_capture.status : 'idle'}:${cameraState}:${resolvedSession ? 'res' : 'unres'}:${isBlk ? 'blk' : 'ok'}:${manualIdentityAllowed ? 'manual' : 'auto'}:${displayCodeAllowed ? 'disp' : 'norm'}`;

    return {
      entityKey,
      safetyKey,
      freshness: 'current',
      hasPendingCapture: pending_capture !== null,
      captureStatus: pending_capture?.status ?? null,
      cameraState,
      isResolvedSession: resolvedSession,
      manualIdentityAllowed,
      displayCodeAllowed,
      isBlocked: isBlk,
      recoveryCode: recovery?.code ?? null,
      recoveryOwner: recovery?.owner ?? null,
      candidateCount: pendingCandidates.length,
      sessionCode: pending_capture?.session?.exam_code ?? null,
      roomName: pending_capture?.session?.room_name ?? null,
    };
  });
  onMount(() => {
    if (pending_capture === null) startCamera();
  });

  onDestroy(() => {
    stopCamera();
  });

  async function startCamera() {
    if (!navigator.mediaDevices?.getUserMedia) {
      cameraState = 'unavailable';
      return;
    }
    try {
      cameraStream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: { ideal: 'environment' } },
        audio: false,
      });
      if (!videoEl) return;
      videoEl.srcObject = cameraStream;
      await videoEl.play();
      cameraState = 'ready';
    } catch {
      cameraState = 'unavailable';
    }
  }

  function stopCamera() {
    cameraStream?.getTracks?.().forEach((track) => track.stop());
    cameraStream = null;
  }

  function triggerUpload() {
    fileInput?.click();
  }

  function onFileChange(event) {
    const file = event.currentTarget.files?.[0];
    if (file) uploadCapture(file);
    event.currentTarget.value = '';
  }

  function captureFrame() {
    if (!videoEl || cameraState !== 'ready') return;
    const width = videoEl.videoWidth;
    const height = videoEl.videoHeight;
    if (!width || !height) return;
    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    canvas.getContext('2d')?.drawImage(videoEl, 0, 0, width, height);
    canvas.toBlob((blob) => {
      if (blob) uploadCapture(new File([blob], `omr-capture-${Date.now()}.jpg`, { type: 'image/jpeg' }));
    }, 'image/jpeg', 0.92);
  }

  function uploadCapture(file) {
    if (uploading || !file) return;
    uploading = true;
    uploadProgress = 0;
    router.post(
      capture_url,
      {
        image: file,
        client_capture_id: newIdempotencyKey(),
        idempotency_key: newIdempotencyKey(),
      },
      {
        forceFormData: true,
        onProgress: (progress) => {
          const percentage = Number(progress?.percentage);
          if (Number.isFinite(percentage)) {
            uploadProgress = Math.min(100, Math.max(0, percentage));
          }
        },
        onFinish: () => {
          uploading = false;
          uploadProgress = 0;
        },
      },
    );
  }

  function retryCapture() {
    if (!pendingActions.retry || retrying || pending_capture === null) return;
    retrying = true;
    router.post(
      pendingActions.retry,
      {
        expected_version: pending_capture.version,
        idempotency_key: newIdempotencyKey(),
      },
      { onFinish: () => (retrying = false) },
    );
  }

  function submitApplicant(event) {
    event.preventDefault();
    const value = applicantNumber.trim();
    if (!pendingActions.resolve_applicant || !value || resolvingApplicant || pending_capture === null) return;
    resolvingApplicant = true;
    router.post(
      pendingActions.resolve_applicant,
      {
        applicant_number: value,
        expected_version: pending_capture.version,
        idempotency_key: newIdempotencyKey(),
      },
      { onFinish: () => (resolvingApplicant = false) },
    );
  }
  function chooseSession(id) {
    if (!pendingActions.select_session || selectingSession || pending_capture === null) return;
    selectingSession = true;
    router.post(
      pendingActions.select_session,
      {
        exam_session_id: Number(id),
        expected_version: pending_capture.version,
        idempotency_key: newIdempotencyKey(),
      },
      { onFinish: () => (selectingSession = false) },
    );
  }
  function submitDisplayCode(event) {
    event.preventDefault();
    const value = displayCode.trim();
    if (!pendingActions.resolve_display_code || !value || !manualConfirmed || resolvingDisplayCode || pending_capture === null) return;
    resolvingDisplayCode = true;
    router.post(
      pendingActions.resolve_display_code,
      {
        display_code: value,
        manual_confirmed: true,
        expected_version: pending_capture.version,
        idempotency_key: newIdempotencyKey(),
      },
      {
        onSuccess: () => {
          displayCode = '';
          manualConfirmed = false;
        },
        onFinish: () => (resolvingDisplayCode = false),
      },
    );
  }

  function adoptCapture() {
    if (!pendingActions.adopt || adopting || pending_capture === null) return;
    adopting = true;
    router.post(
      pendingActions.adopt,
      {
        expected_version: pending_capture.version,
        idempotency_key: newIdempotencyKey(),
      },
      { onFinish: () => (adopting = false) },
    );
  }

  function openRecoveryDestination() {
    if (recovery?.destination_url) window.location.assign(recovery.destination_url);
  }

  function openLegacyMonitoring(candidate) {
    if (!candidate?.monitoring_url) return;
    const returnTo = '/scoring/omr?applicant_number=' + encodeURIComponent(applicant_number.trim());
    const separator = candidate.monitoring_url.includes('?') ? '&' : '?';
    window.location.assign(`${candidate.monitoring_url}${separator}return_to=${encodeURIComponent(returnTo)}`);
  }
</script>

<svelte:head><title>Answer Sheet Scanner · NEXIAM</title></svelte:head>

<StaffLayout breadcrumbs={[{ label: 'Scoring', href: '/scoring' }, { label: 'Answer Sheet Scanner' }]}>
  <div class="mx-auto min-w-0 max-w-4xl space-y-6 p-4 sm:p-6">
    <PageHeaderBar
      title="Answer Sheet Scanner"
      description="Capture physical answer sheets to verify QR provenance, read applicant identifiers, and match submitted exam sessions."
      tourKey="assessment-omr-camera-scanner"
      context={scannerContext}
    >
      {#snippet actions()}
        <Link href="/scoring">
          <Button variant="outline" class="min-h-10 gap-2"><ArrowLeft class="size-4" /> Back to scoring</Button>
        </Link>
      {/snippet}
    </PageHeaderBar>

    {#if pending_capture === null}
      <section class="rounded-xl border border-border bg-card p-5 shadow-sm sm:p-6" data-tour="omr-scanner-capture">
        <div class="flex items-start gap-3">
          <div class="rounded-lg bg-primary/10 p-2 text-primary"><ScanLine class="size-5" /></div>
          <div>
            <h2 class="font-semibold tracking-tight">Capture the paper</h2>
            <p class="mt-1 text-sm text-muted-foreground">
              Use the camera or upload one clear image. Do not type an Applicant Number until the scan reports an unreadable or unresolved identity.
            </p>
          </div>
        </div>

        <div class="mt-5 grid gap-4 lg:grid-cols-[1.4fr_1fr]">
          <div class="overflow-hidden rounded-xl border border-border bg-muted/40">
            <video bind:this={videoEl} class="aspect-[4/3] h-full min-h-56 w-full object-cover" playsinline muted></video>
            {#if cameraState !== 'ready'}
              <div class="flex items-center gap-2 p-3 text-sm text-muted-foreground">
                {#if cameraState === 'unavailable'}<XCircle class="size-4" /> Camera unavailable; use image upload.
                {:else}<Camera class="size-4" /> Requesting camera access…{/if}
              </div>
            {/if}
          </div>
          <div class="flex flex-col justify-center gap-3">
            <Button class="min-h-11 gap-2" onclick={captureFrame} disabled={uploading || cameraState !== 'ready'}>
              <Camera class="size-4" /> {uploading ? 'Uploading capture…' : 'Capture frame'}
            </Button>
            <Button variant="outline" class="min-h-11 gap-2" onclick={triggerUpload} disabled={uploading}>
              <Upload class="size-4" /> Upload image
            </Button>
            {#if uploading}
              <div class="space-y-1" aria-live="polite">
                <div class="flex items-center justify-between text-xs text-muted-foreground">
                  <span>Uploading capture</span>
                  <span>{Math.round(uploadProgress)}%</span>
                </div>
                <div
                  class="h-1.5 w-full overflow-hidden rounded-full bg-muted"
                  role="progressbar"
                  aria-label="Upload progress"
                  aria-valuemin="0"
                  aria-valuemax="100"
                  aria-valuenow={Math.round(uploadProgress)}
                >
                  <div class="h-full rounded-full bg-primary transition-[width] duration-200" style={`width: ${uploadProgress}%`}></div>
                </div>
              </div>
            {/if}
            <input bind:this={fileInput} class="hidden" type="file" accept="image/jpeg,image/png,image/webp" capture="environment" onchange={onFileChange} />
            <p class="text-xs leading-5 text-muted-foreground">
              The original image is preserved server-side. QR/template verification and all identity/session matching stay server-authoritative.
            </p>
          </div>
        </div>
      </section>
    {:else}
      <section class="rounded-xl border border-border bg-card p-5 shadow-sm sm:p-6" data-tour="omr-scanner-capture-status">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div class="flex items-start gap-3">
            <div class="rounded-lg bg-primary/10 p-2 text-primary"><FileImage class="size-5" /></div>
            <div>
              <h2 class="font-semibold tracking-tight">Preserved capture #{pending_capture.id}</h2>
              <p class="mt-1 text-sm text-muted-foreground">The same immutable image remains attached while identity or session recovery is completed.</p>
            </div>
          </div>
          <span class="rounded-full border border-border px-2.5 py-1 text-xs font-semibold uppercase tracking-wide">{pending_capture.status}</span>
        </div>

        {#if pending_capture.status === 'analyzing'}
          <div class="mt-5 flex items-center gap-2 rounded-lg border border-primary/30 bg-primary/5 p-4 text-sm"><ScanLine class="size-4 animate-pulse" /> Verifying QR, template, and sheet marks…</div>
        {:else if resolvedSession}
          <div class="mt-5 rounded-lg border border-emerald-500/40 bg-emerald-500/5 p-4">
            <div class="flex items-start gap-2"><CheckCircle2 class="mt-0.5 size-5 text-emerald-600" /><div><h3 class="font-semibold">Session resolved</h3><p class="mt-1 text-sm text-muted-foreground">{pending_capture.session?.exam_code ?? 'Verified session'} · {pending_capture.session?.room_name ?? 'Room unavailable'}</p></div></div>
            <p class="mt-3 text-sm text-muted-foreground">The capture is ready to enter the existing review workbench. No score is written until staff review and batch confirmation.</p>
            <Button class="mt-4 min-h-10 gap-2" onclick={adoptCapture} disabled={adopting}><ShieldCheck class="size-4" /> {adopting ? 'Adopting…' : 'Open review workbench'}</Button>
          </div>
        {/if}
      </section>
    {/if}

    {#if recovery}
      <section class="rounded-xl border border-amber-500/40 bg-amber-500/5 p-5 sm:p-6" role="alert">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
          <div><h2 class="font-semibold text-amber-800 dark:text-amber-300">{recovery.title}</h2><p class="mt-1 text-sm text-muted-foreground">{recovery.explanation}</p></div>
          <span class="w-fit rounded-full border border-amber-500/40 px-2.5 py-1 text-xs font-semibold text-amber-800 dark:text-amber-300">Scoring blocked</span>
        </div>
        <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2"><div><dt class="text-muted-foreground">Responsible owner</dt><dd class="font-medium">{recovery.owner}</dd></div><div><dt class="text-muted-foreground">Support code</dt><dd class="font-mono font-medium">{recovery.code}</dd></div></dl>
        {#if recovery.primary_action === 'open_monitoring' && recovery.destination_url}
          <Button class="mt-4 min-h-10" onclick={openRecoveryDestination}>Open Exam Monitoring</Button>
        {:else if recovery.primary_action === 'retry_scan' && pending_capture}
          <Button class="mt-4 min-h-10 gap-2" onclick={retryCapture} disabled={retrying}><ScanLine class="size-4" /> {retrying ? 'Retrying…' : 'Retry this preserved capture'}</Button>
        {/if}
      </section>
    {/if}
    {#if manualIdentityAllowed}
      <section class="rounded-xl border border-border bg-card p-5 sm:p-6" data-tour="omr-scanner-applicant-number">
        <h2 class="font-semibold tracking-tight">Applicant Number needs staff confirmation</h2>
        <p class="mt-1 text-sm text-muted-foreground">The machine identity was absent, unreadable, or unresolved. Enter only the printed Applicant Number; names, birthdate, room, time, handwriting, and observed Set are not used to infer a session.</p>
        <form class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end" onsubmit={submitApplicant}>
          <div class="min-w-0 flex-1 space-y-2"><Label for="manual-applicant-number">Applicant Number</Label><Input id="manual-applicant-number" bind:value={applicantNumber} autocomplete="off" maxlength="64" pattern="[A-Za-z0-9._-]+" required /></div>
          <Button type="submit" class="min-h-10 gap-2" disabled={resolvingApplicant || !applicantNumber.trim()}><ShieldCheck class="size-4" /> {resolvingApplicant ? 'Matching…' : 'Match eligible records'}</Button>
        </form>
      </section>
    {/if}

    {#if displayCodeAllowed}
      <section class="rounded-xl border border-border bg-card p-5 sm:p-6" data-tour="omr-scanner-display-code">
        <h2 class="font-semibold tracking-tight">QR recovery: use the printed Display Code</h2>
        <p class="mt-1 text-sm text-muted-foreground">This option is available only when the QR is absent or unreadable. A present-but-invalid QR can never be bypassed.</p>
        <form class="mt-4 space-y-3" onsubmit={submitDisplayCode}>
          <div class="space-y-2"><Label for="display-code">Display Code</Label><Input id="display-code" bind:value={displayCode} autocomplete="off" maxlength="64" required /></div>
          <label class="flex items-start gap-2 text-sm"><input type="checkbox" bind:checked={manualConfirmed} class="mt-1" /> <span>I verified this printed Display Code against the preserved paper.</span></label>
          <Button type="submit" class="min-h-10" disabled={resolvingDisplayCode || !displayCode.trim() || !manualConfirmed}>{resolvingDisplayCode ? 'Resolving…' : 'Record Display Code and retry'}</Button>
        </form>
      </section>
    {/if}

    {#if pending_capture && pendingCandidates.length > 1}
      <section class="rounded-xl border border-border bg-card p-5 sm:p-6">
        <h2 class="font-semibold tracking-tight">Choose the verified session</h2>
        <p class="mt-1 text-sm text-muted-foreground">Multiple eligible submitted sessions match. Choose explicitly; no other paper detail breaks the tie.</p>
        <div class="mt-4 grid gap-3 sm:grid-cols-2">{#each pendingCandidates as candidate (candidate.exam_session_id)}<button type="button" class="rounded-lg border border-border p-4 text-left transition-colors hover:border-primary/50 hover:bg-primary/5" onclick={() => chooseSession(candidate.exam_session_id)} disabled={selectingSession}><strong class="block">{candidate.exam_code}</strong><span class="mt-1 block text-sm text-muted-foreground">{candidate.room_name}</span><span class="mt-3 block text-sm font-semibold text-primary">{selectingSession ? 'Selecting…' : 'Use this session'}</span></button>{/each}</div>
      </section>
    {:else if !pending_capture && outcome === 'selection_required' && candidates.length > 1}
      <section class="rounded-xl border border-border bg-card p-5 sm:p-6"><h2 class="font-semibold tracking-tight">Choose the verified session</h2><p class="mt-1 text-sm text-muted-foreground">Multiple eligible submitted sessions match. Choose explicitly; no other paper detail breaks the tie.</p><div class="mt-4 grid gap-3 sm:grid-cols-2">{#each candidates as candidate (candidate.exam_session_id)}<button type="button" class="rounded-lg border border-border p-4 text-left" onclick={() => openLegacyMonitoring(candidate)}><strong class="block">{candidate.exam_code}</strong><span class="mt-1 block text-sm text-muted-foreground">{candidate.room_name}</span><span class="mt-3 block text-sm font-semibold text-primary">Open session workbench</span></button>{/each}</div></section>
    {/if}
  </div>
</StaffLayout>
