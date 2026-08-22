<script>
  import { onMount, onDestroy } from 'svelte';
  import { router } from '@inertiajs/svelte';
  import { ArrowLeft, Camera, Check, ChevronLeft, FileImage, Image as ImageIcon, Info, LayoutGrid, Maximize2, RefreshCw, ScanLine, Settings2, ShieldCheck, Trash2, Upload, UserCheck, X } from 'lucide-svelte';
  import { success as toastSuccess, error as toastError, info as toastInfo } from '@/lib/toast.js';
  import { newIdempotencyKey } from '@/lib/utils.js';
  import ToastManager from '@/Components/kit/ToastManager.svelte';
  import OmrRegionCrop from './OmrRegionCrop.svelte';

  let {
    page_state = 'empty',
    camera_enabled = true,
    provider = { name: 'nexiam.fake', version: '1', synthetic: true },
    available_pairs = [],
    roster = [],
    session = {},
    template = { identity_region: null, items: [] },
    batch = null,
  } = $props();

  let screen = $state('camera');
  let mode = $state('auto');
  let cameraState = $state(camera_enabled ? 'requesting' : 'unavailable');
  let activeTab = $state('draft');
  let selectedSheetId = $state(null);
  let selectedAttemptId = $state(null);
  let desktopLayout = $state('split');
  let fileInput;
  let videoEl = $state(null);
  let cameraStream = $state(null);
  let cameraRequested = false;
  let cameraUnavailableReason = $state(camera_enabled ? null : 'device');
  let settingsOpen = $state(false);
  let selectedDeviceId = $state('');
  let cameraDevices = $state([]);
  let failedImageIds = $state(new Set());
  let currentPage = $state(1);
  let activeSetId = $state(null);
  let replacingPage = $state(null);
  let replacingSheet = $state(null);
  let selectedPair = $state(available_pairs[0] ?? null);
  let startingBatch = $state(false);
  let savingReview = $state(false);
  let confirmingBatch = $state(false);
  let abstainReason = $state('');
  let resolvingIssueId = $state(null);
  // Server-driven capture preflight (marker batches run CropOnMarkers; scan
  // batches answer not_applicable and keep the normal upload path).
  let preflightState = $state('idle'); // idle | checking | validated | rejected | error
  let preflight = $state(null); // typed preflight payload
  let preflightError = $state('');

  function csrfToken() {
    const el = document.querySelector('meta[name="csrf-token"]');
    return el instanceof HTMLMetaElement ? el.content : '';
  }

  const preflightGateOrder = ['marker_count', 'registration', 'dimensions', 'shifts'];

  function preflightGateLabel(key) {
    return {
      marker_count: 'Marker count',
      registration: 'Registration',
      dimensions: 'Dimensions',
      shifts: 'Field-block shifts',
    }[key] ?? key;
  }

  function preflightBusy() {
    return preflightState === 'checking';
  }

  function resetPreflight() {
    preflight = null;
    preflightError = '';
    preflightState = 'idle';
  }

  const sheets = $derived(batch?.sheets ?? []);
  const gallery = $derived(sheets);
  // Selected sheet identity, re-derived from every refreshed batch so polling
  // replaces the stale object (queued → processing → needs_review|failed).
  const selectedSheet = $derived(sheets.find((sheet) => sheet.id === selectedSheetId) ?? null);
  const reviewBusy = $derived(['pending_pages', 'queued', 'processing'].includes(selectedSheet?.status));
  const reviewFailed = $derived(selectedSheet?.status === 'failed_terminal');
  const reviewReady = $derived(Boolean(selectedSheet) && !reviewBusy && !reviewFailed);
  let activeReviewPage = $state(1);
  const pageCount = $derived(Number(batch?.template?.page_count ?? 1));
  const allReviewed = $derived(gallery.length > 0 && gallery.every((sheet) => ['reviewed', 'committed'].includes(sheet.status)));
  const visibleScans = $derived(activeTab === 'draft'
    ? gallery.filter((sheet) => !['committed'].includes(sheet.status))
    : gallery);

  function capturedPageCount(sheet) {
    return (sheet.pages ?? []).filter((page) => page.captured).length;
  }

  function missingPageCount(sheet) {
    return Math.max(0, Number(sheet.expected_page_count ?? 1) - capturedPageCount(sheet));
  }

  function nextMissingPage(sheet) {
    const pages = sheet.pages ?? [];
    for (let no = 1; no <= Number(sheet.expected_page_count ?? 1); no += 1) {
      if (!pages.some((page) => Number(page.page_no) === no && page.captured)) return no;
    }
    return null;
  }
  const reviewPages = $derived((selectedSheet?.pages?.length ? selectedSheet.pages : null) ?? null);
  const activeReviewPageData = $derived(reviewPages?.find((page) => Number(page.page_no) === activeReviewPage) ?? null);
  const reviewImageUrl = $derived(activeReviewPageData?.image_url ?? selectedSheet?.image_url ?? null);
  function pageLabel(sheet, pageNo) {
    const page = (sheet.pages ?? []).find((candidate) => Number(candidate.page_no) === pageNo);
    return page?.captured ? 'Captured' : 'Missing';
  }


  const captureTemplate = $derived(batch?.template ?? template ?? { identity_region: null, items: [] });
  const templateItems = $derived(captureTemplate.items ?? []);
  // Immutable template geometry is authoritative: never trust proposal item
  // regions/options/page attribution, only the detection fields.
  const reviewItems = $derived((selectedSheet?.proposal?.items?.length ? selectedSheet.proposal.items : []).map((item) => {
    const geometry = templateItems.find((candidate) => candidate.position === item.position);

    return {
      ...item,
      ...geometry,
      region: geometry?.region ?? item.region ?? null,
      options: geometry?.options?.length ? geometry.options : (item.options ?? []),
      page_no: geometry?.page_no ?? item.page_no ?? 1,
      correct_option_code: item.correct_option_code ?? geometry?.correct_option_code ?? null,
    };
  }));
  const pageItems = $derived(reviewItems.filter((item) => (item.page_no ?? 1) === activeReviewPage));
  const overlayItems = $derived(pageItems
    .filter((item) => item.region && Number(item.region.w) > 0 && Number(item.region.h) > 0)
    .map((item) => {
      const marked = item.state === 'marked' && item.detected_answer;
      const ambiguous = item.state === 'ambiguous' || item.state === 'unreadable';
      const correct = marked && item.detected_answer === item.correct_option_code;
      const ovClass = correct ? 'correct' : marked ? 'incorrect' : ambiguous ? 'ambiguous' : null;
      const detectedRegion = marked
        ? (item.options ?? []).find((option) => option.code === item.detected_answer)?.region ?? null
        : null;

      return {
        ...item,
        ovClass,
        detectedRegion,
        ovLabel: `${item.area_code ?? 'Sheet'} item ${item.question_no}: ${correct ? 'correct' : marked ? 'incorrect' : 'ambiguous/unreadable'}`,
      };
    })
    .filter((item) => item.ovClass !== null));
  const scoreRows = $derived(Object.entries(selectedSheet?.reviewed?.per_area_scores ?? selectedSheet?.proposal?.per_area_scores ?? {}).map(([areaId, proposal]) => {
    const area = templateItems.find((item) => String(item.area_id) === String(areaId));

    return {
      areaId,
      areaCode: area?.area_code ?? `Area ${areaId}`,
      areaName: area?.area_name ?? 'Aptitude area',
      proposal,
    };
  }));
  const editAttemptId = $derived(selectedAttemptId ?? selectedSheet?.admission_attempt_id ?? null);
  const boundApplicant = $derived(roster.find((applicant) => String(applicant.id) === String(editAttemptId)) ?? null);
  const requiredIssues = $derived((selectedSheet?.issues ?? []).filter((issue) => issue.state === 'open' && ['required', 'terminal'].includes(issue.severity)));

  function getInitials(name) {
    if (!name) return '?';
    const clean = name.replace(/^(Mr\.|Ms\.|Mrs\.|Dr\.)\s+/i, '').trim();
    const parts = clean.split(/\s+/);
    if (parts.length === 0 || !parts[0]) return '?';
    if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
  }

  function getExamineeInfo(sheet) {
    const attemptId = sheet.admission_attempt_id ?? null;
    const applicant = roster.find((a) => String(a.id) === String(attemptId));
    const name = applicant?.name ?? sheet.detected_applicant_number ?? (sheet.id ? `Scan #${sheet.id}` : 'Unbound scan');
    const code = applicant?.reference_code ?? (sheet.detected_applicant_number ? `No. ${sheet.detected_applicant_number}` : 'No roster match');
    const initials = applicant?.name
      ? getInitials(applicant.name)
      : sheet.detected_applicant_number
        ? String(sheet.detected_applicant_number).slice(-2)
        : sheet.id
          ? `#${String(sheet.id).padStart(2, '0').slice(-2)}`
          : '?';
    const isBound = !!applicant;

    return { name, code, initials, isBound, applicant };
  }

  function imageKey(sheet) {
    return String(sheet?.id ?? sheet?.image_url ?? '');
  }

  function imageIsPlaceholder(sheet) {
    const url = sheet?.image_url;

    return typeof url === 'string' && (url.startsWith('data:image/svg') || /\.svg(?:[?#]|$)/i.test(url));
  }

  function hasImageEvidence(sheet) {
    return Boolean(sheet?.image_url && !failedImageIds.has(imageKey(sheet)) && !imageIsPlaceholder(sheet));
  }

  function evidenceLabel(sheet) {
    if (!sheet?.image_url) return 'No image evidence';
    if (imageIsPlaceholder(sheet)) return 'Preview placeholder';
    if (failedImageIds.has(imageKey(sheet))) return 'Image unavailable';
    return 'Captured image';
  }

  function markImageUnavailable(sheet) {
    const key = imageKey(sheet);
    if (!key) return;

    failedImageIds = new Set(failedImageIds).add(key);
  }

  function isRegion(value) {
    return Boolean(value && typeof value === 'object' && Number(value.w) > 0 && Number(value.h) > 0);
  }

  const pageIdentityRegions = $derived(captureTemplate.identity_regions?.[String(activeReviewPage)] ?? null);
  const identityRegion = $derived(
    isRegion(pageIdentityRegions?.applicant_number)
      ? pageIdentityRegions.applicant_number
      : isRegion(captureTemplate.identity_region)
        ? captureTemplate.identity_region
        : null,
  );
  const nameRegion = $derived(isRegion(pageIdentityRegions?.name) ? pageIdentityRegions.name : null);

  function manualScoresHref(areaId = null) {
    if (!editAttemptId) return null;
    return `/grading/sessions/${session.id}/attempts/${editAttemptId}${areaId ? `#area-${areaId}` : ''}`;
  }

  function itemStateLabel(item) {
    return { marked: 'Marked', blank: 'Blank', ambiguous: 'Ambiguous', unreadable: 'Unreadable' }[item.state] ?? 'Unavailable';
  }

  function itemCropTitle(item) {
    return `Item ${item.question_no} · ${item.area_code ?? item.area_name ?? 'Aptitude area'}${(item.page_no ?? 1) > 1 ? ` · P${item.page_no}` : ''}`;
  }

  function issueLabel(issue) {
    return {
      ambiguous_mark: 'Ambiguous mark',
      unreadable: 'Unreadable item',
      blank_mark: 'Blank mark',
      low_confidence: 'Low confidence',
      identity_unresolved: 'Identity unresolved',
      identity_conflict: 'Identity conflict',
      no_geometry: 'No geometry',
      sheet_misaligned: 'Sheet misaligned',
    }[issue.issue_code] ?? issue.issue_code;
  }

  function itemMark(item) {
    const state = itemStateLabel(item);
    if (item.state !== 'marked') return { label: state, tone: item.state === 'ambiguous' || item.state === 'unreadable' ? 'warning' : 'neutral' };
    if (item.confidence != null && item.confidence < 0.5) return { label: 'Review', tone: 'warning' };
    return item.detected_answer === item.correct_option_code
      ? { label: 'Correct', tone: 'success' }
      : { label: 'Incorrect', tone: 'danger' };
  }

  function itemScore(item) {
    if (item.state !== 'marked' || !item.detected_answer || item.confidence == null || item.confidence < 0.5) return '—';
    return item.detected_answer === item.correct_option_code ? '1' : '0';
  }

  function confidenceLabel(value) {
    return value == null ? 'Unqualified' : `${Math.round(value * 100)}%`;
  }

  function identityConfidenceLabel(sheet) {
    return confidenceLabel(sheet?.identity_confidence);
  }

  function isMediaStream(value) {
    return typeof MediaStream !== 'undefined' && value instanceof MediaStream;
  }

  $effect(() => {
    if (!videoEl || !cameraStream || !isMediaStream(cameraStream)) return;

    try {
      videoEl.srcObject = cameraStream;
      videoEl.play().catch(() => {});
    } catch {
      cameraState = 'unavailable';
    }
  });

  $effect(() => {
    if (camera_enabled && batch && screen === 'camera' && !cameraRequested) {
      cameraRequested = true;
      requestCamera();
    }
  });

  function openGallery() {
    screen = 'gallery';
  }

  function openCamera() {
    screen = 'camera';
    selectedSheetId = null;
  }

  function setDesktopLayout(layout) {
    desktopLayout = layout;
  }

  function setMode(nextMode) {
    mode = nextMode;
  }

  function setActiveTab(tab) {
    activeTab = tab;
  }

  async function toggleSettings() {
    settingsOpen = !settingsOpen;
    if (!settingsOpen) return;

    try {
      await refreshCameraDevices();
    } catch {
      cameraDevices = [];
    }
  }

  function closeSettings() {
    settingsOpen = false;
  }

  function deviceFacing(device, index) {
    const label = device.label.toLowerCase();
    if (/front|user|selfie|facetime|facing[ :_-]*front/.test(label)) return 'front';
    if (/back|rear|environment|main|wide|ultra|tele|zoom|facing[ :_-]*back/.test(label)) return 'back';
    return index === 0 ? 'back' : null;
  }

  function deviceLabel(device, index) {
    if (!device.facing) return device.label || `Camera ${index + 1}`;

    const cameraNumber = cameraDevices
      .slice(0, index + 1)
      .filter((candidate) => candidate.facing === device.facing)
      .length;

    return `${device.facing === 'front' ? 'Front' : 'Back'} camera ${cameraNumber}`;
  }

  async function refreshCameraDevices() {
    if (!navigator.mediaDevices?.enumerateDevices) {
      cameraDevices = [];
      return;
    }

    const devices = await navigator.mediaDevices.enumerateDevices();
    cameraDevices = devices
      .filter((device) => device.kind === 'videoinput')
      .map((device, index) => ({
        id: device.deviceId || `camera-${index}`,
        label: device.label || `Camera ${index + 1}`,
        facing: deviceFacing(device, index),
        selectable: Boolean(device.deviceId),
      }));
  }

  async function requestCamera(userInitiated = false) {
    if (!camera_enabled) {
      cameraUnavailableReason = 'device';
      cameraState = 'unavailable';
      return;
    }

    if (!navigator.mediaDevices?.getUserMedia) {
      cameraUnavailableReason = 'device';
      cameraState = 'unavailable';
      if (userInitiated) {
        toastError('Camera is not available on this device or HTTPS is required.');
      }
      return;
    }

    cameraState = 'requesting';

    try {
      const hasSelectedDevice = Boolean(selectedDeviceId);
      const video = hasSelectedDevice
        ? { deviceId: { exact: selectedDeviceId }, width: { ideal: 1920 }, height: { ideal: 1080 } }
        : { facingMode: 'environment', width: { ideal: 1920 }, height: { ideal: 1080 } };

      try {
        cameraStream = await navigator.mediaDevices.getUserMedia({ audio: false, video });
      } catch (error) {
        if (hasSelectedDevice) throw error;
        cameraStream = await navigator.mediaDevices.getUserMedia({ audio: false, video: true });
      }

      try {
        await refreshCameraDevices();
      } catch {
        cameraDevices = [];
      }

      const track = cameraStream.getVideoTracks()[0];
      const currentDeviceId = track?.getSettings?.().deviceId;
      if (!hasSelectedDevice) {
        selectedDeviceId = currentDeviceId && cameraDevices.some((device) => device.id === currentDeviceId)
          ? currentDeviceId
          : '';
      }
      cameraUnavailableReason = null;
      cameraState = 'ready';
    } catch (error) {
      console.error('Camera access failed', error);
      cameraUnavailableReason = error?.name === 'NotAllowedError' || error?.name === 'SecurityError'
        ? 'permission'
        : 'device';
      cameraState = 'unavailable';
      if (userInitiated) {
        selectedDeviceId = '';
        toastError(cameraUnavailableReason === 'permission'
          ? 'Camera permission is unavailable. Allow access or use file upload.'
          : 'That camera could not be opened. Pick another camera or use file upload.');
      }
    }
  }

  function stopCamera() {
    cameraStream?.getTracks().forEach((track) => track.stop());
    cameraStream = null;
    if (videoEl) {
      videoEl.srcObject = null;
    }
  }

  async function switchDevice(deviceId) {
    settingsOpen = false;
    if (deviceId === selectedDeviceId) return;
    selectedDeviceId = deviceId;
    stopCamera();
    await requestCamera(true);
  }

  function triggerUpload() {
    fileInput?.click();
  }

  function startBatch(pair = selectedPair) {
    if (!pair || startingBatch) return;
    startingBatch = true;
    toastInfo('Starting OMR capture batch…');
    router.post(`/grading/sessions/${session.id}/omr/batches`, {
      template_version_id: pair.template_version_id,
      booklet_version_id: pair.booklet_version_id,
      idempotency_key: newIdempotencyKey(),
    }, {
      onFinish: () => { startingBatch = false; },
    });
  }

  function afterSheetUpload(page) {
    const sheets = page?.props?.batch?.sheets ?? [];
    const newest = [...sheets].sort((a, b) => (b.id ?? 0) - (a.id ?? 0))[0];

    if (mode === 'manual') {
      if (newest) {
        openReview(newest);
        toastSuccess('Manual scan analyzed. Confirm examinee and marks, then save.');
      } else {
        screen = 'gallery';
        toastSuccess('Manual scan analyzed and ready for review.');
      }
      return;
    }

    screen = 'camera';
    toastInfo('Auto scan queued for review in the background.');
  }

  function finishSetAndAdvance(page) {
    if (currentPage < pageCount) {
      currentPage += 1;
      toastSuccess(`Page ${currentPage - 1} of ${pageCount} captured — capture page ${currentPage}.`);
      screen = 'camera';
      return;
    }

    activeSetId = null;
    currentPage = 1;
    afterSheetUpload(page);
  }

  function uploadSheet(file, onFinish) {
    if (!file || !batch) return;

    if (!activeSetId) activeSetId = newIdempotencyKey();
    cameraState = 'uploading';
    toastInfo(`Uploading page ${currentPage} of ${pageCount}…`);
    router.post(`/grading/sessions/${session.id}/omr/sheets`, {
      image: file,
      page_no: currentPage,
      capture_mode: mode,
      client_capture_id: activeSetId,
    }, {
      forceFormData: true,
      onSuccess: finishSetAndAdvance,
      onError: (errors) => {
        const message = errors.omr ?? errors.image ?? Object.values(errors).flat()[0];
        toastError(typeof message === 'string' ? message : 'The sheet could not be uploaded.');
      },
      onFinish: () => {
        cameraState = cameraStream ? 'ready' : 'unavailable';
        resetPreflight();
        onFinish?.();
      },
    });
  }

  // Server-driven preflight gate. Never sends the frame to /sheets when the
  // marker registration is rejected or the check itself errors (fail closed).
  // Scan (CropPage) batches answer not_applicable and continue to the normal
  // upload path — scan validation stays authoritative.
  async function runPreflight(file) {
    if (!file || !batch) return null;

    preflight = null;
    preflightError = '';
    preflightState = 'checking';

    const form = new FormData();
    form.append('image', file);

    try {
      const res = await fetch(`/grading/sessions/${session.id}/omr/batches/${batch.id}/preflight`, {
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

      if (!res.ok && !body?.mode) {
        // Non-typed validation/HTTP failure — fail closed with a retry path.
        preflightState = 'error';
        preflightError = body?.error ?? body?.message ?? 'The sheet could not be checked before upload.';
        return null;
      }

      preflight = body;
      if (body.mode === 'scan') {
        // CropPage scan batches: proceed with the existing upload path and
        // never show a false Validated state.
        preflightState = 'idle';
        return body;
      }
      if (body.outcome === 'validated' && body.submit_allowed) {
        preflightState = 'validated';
        return body;
      }
      preflightState = 'rejected';
      return body;
    } catch {
      preflightState = 'error';
      preflightError = 'The check service is unavailable — retake or retry before uploading.';
      return null;
    }
  }

  async function preflightAndUpload(file, onFinish) {
    const result = await runPreflight(file);
    if (!result) return false;
    if (result.mode === 'scan' || (result.outcome === 'validated' && result.submit_allowed)) {
      uploadSheet(file, onFinish);
      return true;
    }
    return false;
  }

  function handleFiles(event) {
    const input = event.currentTarget;
    const file = input?.files?.[0];
    if (!file) return;

    if (replacingSheet && replacingPage) {
      // Replacing a page obeys the same preflight gate as a fresh capture:
      // marker batches must validate before the replacement is accepted.
      runPreflight(file).then((result) => {
        if (!result) {
          if (input) input.value = '';
          return;
        }
        if (result.mode !== 'scan' && result.outcome !== 'validated') {
          if (input) input.value = '';
          return;
        }
        toastInfo(`Replacing scan #${replacingSheet.id} page ${replacingPage}…`);
        router.post(`/grading/sessions/${session.id}/omr/sheets/${replacingSheet.id}/pages/${replacingPage}/replace`, {
          image: file,
        }, {
          forceFormData: true,
          onSuccess: () => {
            toastSuccess(`Scan #${replacingSheet.id} page ${replacingPage} replaced.`);
            replacingSheet = null;
            replacingPage = null;
          },
          onError: (errors) => { toastError(errors.omr ?? 'The page could not be replaced.'); },
          onFinish: () => { if (input) input.value = ''; resetPreflight(); },
        });
      });
      return;
    }

    preflightAndUpload(file, () => { if (input) input.value = ''; });
  }

  function retrySheet(sheet) {
    toastInfo(`Retrying scan #${sheet.id}…`);
    router.post(`/grading/sessions/${session.id}/omr/sheets/${sheet.id}/retry`, {}, {
      onSuccess: () => { screen = 'gallery'; toastSuccess(`Scan #${sheet.id} re-queued for analysis.`); },
      onError: (errors) => { toastError(errors.omr ?? 'The scan could not be retried.'); },
    });
  }

  function rejectBatch() {
    if (!batch || confirmingBatch) return;
    if (!window.confirm('Reject this OMR batch? Every sheet and its evidence will be discarded.')) return;
    confirmingBatch = true;
    toastInfo('Rejecting OMR batch…');
    router.post(`/grading/sessions/${session.id}/omr/batches/${batch.id}/reject`, {}, {
      onSuccess: () => { screen = 'gallery'; toastSuccess('OMR batch rejected; no scores were written.'); },
      onError: (errors) => { toastError(errors.omr ?? 'The batch could not be rejected.'); },
      onFinish: () => { confirmingBatch = false; },
    });
  }

  function resumeSet(sheet) {
    activeSetId = sheet.client_capture_id ?? null;
    currentPage = nextMissingPage(sheet) ?? 1;
    mode = sheet.capture_mode ?? mode;
    screen = 'camera';
    toastInfo(`Resuming scan #${sheet.id} — capture page ${currentPage} of ${pageCount}.`);
  }

  function triggerReplace(sheet, pageNo) {
    replacingSheet = sheet;
    replacingPage = pageNo;
    fileInput?.click();
  }

  function capture() {
    if (cameraState !== 'ready' || preflightBusy() || !videoEl || !batch) return;

    const width = videoEl.videoWidth;
    const height = videoEl.videoHeight;

    if (!width || !height) {
      toastError('No camera frame is available yet.');
      return;
    }

    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    const context = canvas.getContext('2d');
    context.drawImage(videoEl, 0, 0, width, height);

    canvas.toBlob((blob) => {
      if (!blob) {
        toastError('The camera frame could not be captured.');
        return;
      }

      preflightAndUpload(new File([blob], `omr-capture-${Date.now()}.jpg`, { type: 'image/jpeg' }));
    }, 'image/jpeg', 0.92);
  }

  function openReview(sheet) {
    selectedSheetId = sheet.id;
    selectedAttemptId = sheet.admission_attempt_id ?? null;
    activeReviewPage = 1;
    abstainReason = '';
    screen = 'review';
  }

  // If the reviewed sheet disappears (batch rejected/regenerated), return to
  // the gallery instead of rendering a dead review.
  $effect(() => {
    if (screen === 'review' && selectedSheetId != null && !selectedSheet) {
      screen = 'gallery';
    }
  });

  function saveReview(action = 'confirm') {
    if (!selectedSheet || savingReview || reviewBusy) return;
    if (action === 'abstain' && !abstainReason.trim()) {
      toastError('Enter a reason before marking this sheet for manual follow-up.');
      return;
    }
    savingReview = true;
    toastInfo('Saving sheet review…');
    router.post(`/grading/sessions/${session.id}/omr/sheets/${selectedSheet.id}/review`, {
      action,
      admission_attempt_id: selectedAttemptId,
      ...(action === 'abstain' ? { abstain_reason: abstainReason.trim() } : {}),
    }, {
      onSuccess: () => {
        screen = 'gallery';
        if (action === 'abstain') {
          toastInfo('Scan marked for manual follow-up.');
        } else {
          toastSuccess('Review saved; confirm the batch to write scores.');
        }
      },
      onError: (errors) => { toastError(errors.omr ?? 'The review could not be saved.'); },
      onFinish: () => { savingReview = false; },
    });
  }

  function resolveIssue(issue, resolutionCode = 'manual_review') {
    if (!selectedSheet || resolvingIssueId === issue.id) return;
    const reason = abstainReason.trim();
    if (!reason) {
      toastError('Enter a manual follow-up reason before resolving an issue.');
      return;
    }

    resolvingIssueId = issue.id;
    router.post(`/grading/sessions/${session.id}/omr/sheets/${selectedSheet.id}/issues/${issue.id}/resolve`, {
      resolution_code: resolutionCode,
      resolution_reason: reason,
    }, {
      onSuccess: () => toastSuccess(`${issueLabel(issue)} resolved.`),
      onError: (errors) => toastError(errors.omr ?? 'The issue could not be resolved.'),
      onFinish: () => { resolvingIssueId = null; },
    });
  }

  function confirmBatch() {
    if (!batch || confirmingBatch) return;
    confirmingBatch = true;
    toastInfo('Confirming reviewed scans…');
    router.post(`/grading/sessions/${session.id}/omr/batches/${batch.id}/confirm`, {}, {
      onSuccess: () => {
        screen = 'gallery';
        toastSuccess('OMR scores committed to the grading session!');
      },
      onError: (errors) => { toastError(errors.omr ?? 'The batch could not be confirmed.'); },
      onFinish: () => { confirmingBatch = false; },
    });
  }

  function backToSession() {
    router.visit(`/grading/sessions/${session.id}`);
  }

  function statusLabel(status) {
    return {
      received: 'Draft',
      queued: 'Queued',
      processing: 'Processing',
      needs_review: 'Needs review',
      reviewed: 'Reviewed',
      committed: 'Committed',
      failed_terminal: 'Failed',
    }[status] ?? status;
  }

  onMount(() => {
    const refresh = window.setInterval(() => {
      if (!batch) return;
      const anyInFlight = gallery.some((sheet) => ['pending_pages', 'queued', 'processing'].includes(sheet.status));
      if (anyInFlight) {
        router.reload({ only: ['batch'], preserveState: true, preserveScroll: true });
      }
    }, 3000);
    const deviceChange = () => refreshCameraDevices().catch(() => { cameraDevices = []; });

    navigator.mediaDevices?.addEventListener?.('devicechange', deviceChange);
    refreshCameraDevices().catch(() => { cameraDevices = []; });

    return () => {
      window.clearInterval(refresh);
      navigator.mediaDevices?.removeEventListener?.('devicechange', deviceChange);
    };
  });

  onDestroy(() => {
    stopCamera();
  });
</script>

<svelte:head>
  <title>OMR capture · {session.exam_code ?? 'Session'}</title>
</svelte:head>

<div class="omr-app">
  <ToastManager />

  <header class="omr-topbar">
    <button class="top-action" type="button" aria-label="Back to grading session" onclick={backToSession}>
      <ArrowLeft size={18} />
      <span>Session</span>
    </button>
    <div class="session-title">
      <span>OMR capture</span>
      <small>
        {session.exam_code ?? '—'} · {session.room_name ?? '—'}
        {#if provider.synthetic}
          · <span class="synthetic-tag" title={`Synthetic provider: ${provider.name} v${provider.version}`}>synthetic v{provider.version}</span>
        {/if}
      </small>
    </div>
    <div class="top-actions">
      <span class="scan-count">{gallery.length} scans</span>

      <div class="desktop-view-toggle">
        <button
          type="button"
          class="toggle-btn"
          class:active={desktopLayout === 'split'}
          onclick={() => setDesktopLayout('split')}
          title="Combined split workspace"
        >
          <LayoutGrid size={14} />
          <span>Split</span>
        </button>
        <button
          type="button"
          class="toggle-btn"
          class:active={desktopLayout === 'focused'}
          onclick={() => setDesktopLayout('focused')}
          title="Focused single panel view"
        >
          <Maximize2 size={14} />
          <span>Focus</span>
        </button>
      </div>

      <div class="settings-wrap">
        <button class="icon-action" type="button" aria-label="Capture settings" aria-haspopup="menu" aria-expanded={settingsOpen} onclick={toggleSettings} class:active={settingsOpen}><Settings2 size={18} /></button>
        {#if settingsOpen}
          <button class="settings-backdrop" type="button" aria-label="Close capture settings" onclick={closeSettings}></button>
          <div class="settings-menu" role="menu" aria-label="Capture settings">
            <div class="settings-menu-heading">
              <p class="settings-menu-title">Camera source · {cameraDevices.length}</p>
              <button class="settings-refresh" type="button" aria-label="Refresh camera list" onclick={refreshCameraDevices}><RefreshCw size={13} /></button>
            </div>
            {#if cameraDevices.length > 0}
              {#each cameraDevices as device, index (device.id)}
                <button type="button" role="menuitemradio" aria-checked={selectedDeviceId === device.id} class:selected={selectedDeviceId === device.id} disabled={!device.selectable} onclick={() => switchDevice(device.id)}>
                  <span><strong>{deviceLabel(device, index)}</strong><small>{device.label || `Camera ${index + 1}`}{device.selectable ? '' : ' · grant camera access to select'}</small></span>
                  {#if selectedDeviceId === device.id}<Check size={14} />{/if}
                </button>
              {/each}
            {:else}
              <div class="menu-hint-box">No browser camera devices were reported. Check permission and connect a camera, then refresh.</div>
            {/if}
            <p class="settings-menu-title">External device hints</p>
            <div class="menu-hint-box">
              Use any listed USB webcam, Bluetooth document scanner, or rear multi-lens phone camera.
            </div>
          </div>
        {/if}
      </div>
    </div>
  </header>

  {#if !batch}
    <section class="start-screen" aria-label="Start OMR capture">
      <div class="start-card">
        <ScanLine size={28} />
        <p class="eyebrow">Session capture</p>
        <h1>Start a pinned OMR batch</h1>
        <p>Select the approved synthetic template and booklet pair. The batch pin is retained for every sheet in this session.</p>
        {#each available_pairs as pair}
          <button class="pair-button" type="button" onclick={() => startBatch(pair)} disabled={startingBatch}>
            <span>{pair.label}</span>
            <small>{startingBatch ? 'Opening…' : 'Start capture'}</small>
          </button>
        {:else}
          <div class="empty-gallery"><strong>No approved OMR configuration</strong><span>An administrator must activate a template/booklet pair before capture can begin.</span></div>
        {/each}
      </div>
    </section>
  {:else}
    <div class="omr-workspace" class:split-mode={desktopLayout === 'split'} class:camera-active={screen === 'camera'}>
      <!-- Left Panel: Camera Stage -->
      <section
        class="capture-screen"
        class:is-hidden={screen !== 'camera'}
        aria-label="Camera capture"
      >
        <div class="camera-stage">
          {#if cameraState === 'ready'}
            <div class="scan-hint"><ScanLine size={14} /> Align all four sheet corners inside the frame</div>
            <div class="frame frame-tl"></div><div class="frame frame-tr"></div>
            <div class="frame frame-bl"></div><div class="frame frame-br"></div>
            <video class="camera-feed" bind:this={videoEl} autoplay playsinline muted aria-label="Live camera preview"></video>
            {#if preflightState === 'idle'}
              <div class="camera-readiness" role="status" aria-live="polite">
                <span class="readiness-dot"></span>
                <span><strong>Ready to check</strong><small>Keep the sheet flat, fully visible, and well lit.</small></span>
              </div>
            {/if}
            {#if pageCount > 1}
              <div class="page-badge" role="status">Page {currentPage} of {pageCount}</div>
            {/if}
            <p class="camera-caption">Camera stream active · preflight checks the frame before upload · recognition stays Unqualified</p>
          {:else if cameraState === 'requesting'}
            <div class="camera-message"><RefreshCw class="spin" size={24} /><strong>Requesting camera access…</strong><small>Allow camera access to capture in place.</small></div>
          {:else if cameraState === 'uploading'}
            <div class="camera-message"><Upload size={28} class="muted-stage-icon" /><strong>Uploading page {currentPage} of {pageCount}…</strong><small>Keep this window open while the scan is analyzed.</small></div>
          {:else}
            <div class="camera-message">
              <Camera size={32} class="muted-stage-icon" />
              <strong>{cameraUnavailableReason === 'permission' ? 'Camera permission unavailable' : 'Camera device unavailable'}</strong>
              <small>{cameraUnavailableReason === 'permission' ? 'Allow camera access in the browser, or use file upload.' : 'Connect a camera or use file upload instead.'}</small>
              <button class="primary-button stage-upload-btn" type="button" onclick={triggerUpload}>
                <Upload size={15} /> Upload image file
              </button>
            </div>
          {/if}

          {#if preflightState === 'checking'}
            <div class="preflight-panel" role="status" aria-live="polite">
              <span class="preflight-badge neutral"><RefreshCw class="spin" size={12} /> Checking</span>
              <p class="preflight-detail">Server registration check in progress — hold the sheet steady.</p>
            </div>
          {:else if preflightState === 'validated'}
            <div class="preflight-panel validated" role="status" aria-live="polite">
              <div class="preflight-head">
                <span class="preflight-badge ok"><Check size={12} /> Validated</span>
                <small class="preflight-calibration">Calibration: Unqualified</small>
              </div>
              <div class="gate-chips">
                {#each preflightGateOrder as key (key)}
                  {@const gate = preflight?.gates?.[key]}
                  {#if gate}
                    <span class="gate-chip {gate === 'pass' ? 'gate-pass' : 'gate-fail'}" title={gate === 'pass' ? `${preflightGateLabel(key)} passed` : `${preflightGateLabel(key)} failed`}>
                      {preflightGateLabel(key)} · {gate}
                    </span>
                  {/if}
                {/each}
              </div>
              {#if preflight?.normalized_dimensions}
                <p class="preflight-meta">Normalized {preflight.normalized_dimensions.width}×{preflight.normalized_dimensions.height}px — uploading…</p>
              {:else}
                <p class="preflight-meta">Uploading the checked frame…</p>
              {/if}
            </div>
          {:else if preflightState === 'rejected'}
            <div class="preflight-panel rejected" role="alert" aria-live="polite">
              <div class="preflight-head">
                <span class="preflight-badge bad"><X size={12} /> Rejected</span>
                {#if preflight?.code}
                  <small class="preflight-code">{preflight.code}</small>
                {/if}
              </div>
              <p class="preflight-detail">{preflight?.detail ?? 'The sheet did not pass the marker registration check.'}</p>
              {#if preflight?.orientation_hint}
                <p class="preflight-hint">{preflight.orientation_hint}</p>
              {/if}
              <button type="button" class="text-button preflight-retake" onclick={resetPreflight}><RefreshCw size={14} /> Retake / replace</button>
            </div>
          {:else if preflightState === 'error'}
            <div class="preflight-panel rejected" role="alert" aria-live="polite">
              <div class="preflight-head">
                <span class="preflight-badge bad"><X size={12} /> Check failed</span>
              </div>
              <p class="preflight-detail">{preflightError}</p>
              <button type="button" class="text-button preflight-retake" onclick={resetPreflight}><RefreshCw size={14} /> Retry</button>
            </div>
          {/if}
        </div>

        <div class="capture-controls">
          <button class="control-side" type="button" aria-label="Open scans gallery" onclick={openGallery}><ImageIcon size={23} /><span>Gallery</span></button>
          <button class="shutter" type="button" aria-label={pageCount > 1 ? `Capture page ${currentPage} of ${pageCount}` : 'Capture sheet'} onclick={capture} disabled={cameraState !== 'ready' || preflightBusy() || preflightState === 'rejected' || preflightState === 'error'}><span><Camera size={25} /></span></button>
          <div class="mode-toggle" role="group" aria-label="Capture mode">
            <button class:active={mode === 'manual'} type="button" onclick={() => setMode('manual')}>Manual</button>
            <button class:active={mode === 'auto'} type="button" onclick={() => setMode('auto')}>Auto</button>
          </div>
        </div>
        <div class="camera-footer">
          {#if pageCount > 1}
            <span class="page-step" role="status">Set page {currentPage} of {pageCount}</span>
          {/if}
          {#if cameraState === 'unavailable'}
            <button type="button" class="text-button" onclick={() => requestCamera(true)}><RefreshCw size={14} /> Retry camera connection</button>
          {:else}
            <button type="button" class="text-button" onclick={triggerUpload}><FileImage size={14} /> Upload image file</button>
          {/if}
        </div>
      </section>
      <!-- Right Panel: Live Workspace (Gallery or Sheet Review) -->
      <div class="workspace-panel" class:is-hidden={screen === 'camera'}>
        {#if screen === 'review'}
          <section class="review-screen" aria-label="Sheet review">
            <div class="review-nav-bar">
              <button class="back-gallery-btn" type="button" onclick={openGallery}>
                <ChevronLeft size={16} />
                <span>Back to scans gallery</span>
              </button>
              <span class="review-status-tag {selectedSheet?.status}">
                {#if reviewBusy}<RefreshCw size={11} class="spin tag-spin" />{/if}
                {statusLabel(selectedSheet?.status)}
              </span>
            </div>
            {#if reviewBusy}
              <div class="review-busy" role="status" aria-live="polite">
                <RefreshCw size={16} class="spin" />
                <div><strong>Analysis in progress</strong><span>This scan is {statusLabel(selectedSheet?.status)}. The review below refreshes automatically when analysis completes.</span></div>
              </div>
            {:else if reviewFailed}
              <div class="review-failed" role="alert">
                <X size={16} />
                <div><strong>Analysis failed</strong><span>No answer evidence is available for this scan. Retry the scan or replace its page.</span></div>
                <button type="button" class="table-edit" onclick={() => retrySheet(selectedSheet)} disabled={confirmingBatch}><RefreshCw size={13} /> Retry scan</button>
              </div>
            {/if}
            {#if reviewPages && reviewPages.length > 1}
              <div class="review-page-tabs" role="tablist" aria-label="Sheet pages">
                {#each reviewPages as page (page.page_no)}
                  <button
                    class:active={activeReviewPage === Number(page.page_no)}
                    type="button"
                    role="tab"
                    aria-selected={activeReviewPage === Number(page.page_no)}
                    onclick={() => { activeReviewPage = Number(page.page_no); }}
                  >
                    Page {page.page_no}
                    <span class="tab-dot" class:captured={page.captured} class:missing={!page.captured}></span>
                  </button>
                {/each}
              </div>
            {/if}
            <div class="review-image">
              {#if hasImageEvidence(selectedSheet)}
                <div class="review-photo-wrap">
                  <img class="review-photo" src={reviewImageUrl} alt={`Captured answer sheet evidence${activeReviewPageData ? ` page ${activeReviewPageData.page_no}` : ''}`} onerror={() => markImageUnavailable(selectedSheet)} />
                  {#if reviewReady && overlayItems.length > 0}
                    <div class="overlay-layer" aria-hidden="true">
                      {#each overlayItems as item (item.position)}
                        <span class="ov-row {item.ovClass}" style="left:{item.region.x * 100}%;top:{item.region.y * 100}%;width:{item.region.w * 100}%;height:{item.region.h * 100}%;" title={item.ovLabel}></span>
                        {#if item.detectedRegion}
                          <span class="ov-option {item.ovClass}" style="left:{item.detectedRegion.x * 100}%;top:{item.detectedRegion.y * 100}%;width:{item.detectedRegion.w * 100}%;height:{item.detectedRegion.h * 100}%;" title={item.ovLabel}><b>{item.detected_answer}</b></span>
                        {/if}
                      {/each}
                    </div>
                  {/if}
                </div>
              {:else}
                <div class="review-paper"><span class="paper-brand">NEXIAM · ANSWER SHEET</span><div class="paper-rule"></div><div class="paper-bubbles">{#each Array(18) as _, index}<i class:marked={index % 5 === 1} class:warning={index === 7}></i>{/each}</div><div class="paper-rule"></div><span class="paper-footer">{evidenceLabel(selectedSheet)}</span></div>
              {/if}
              <div class="overlay-label">{#if hasImageEvidence(selectedSheet)}{#if activeReviewPageData}<Check size={14} /> Page {activeReviewPageData.page_no} of {reviewPages.length} evidence{:else}<Check size={14} /> Captured evidence{/if}{:else}<Info size={14} /> {evidenceLabel(selectedSheet)}{/if}</div>
            </div>
            {#if hasImageEvidence(selectedSheet) && reviewReady && overlayItems.length > 0}
              <div class="overlay-legend" role="note" aria-label="Answer overlay legend">
                <span class="legend-entry"><i class="legend-swatch correct"></i>Correct</span>
                <span class="legend-entry"><i class="legend-swatch incorrect"></i>Incorrect</span>
                <span class="legend-entry"><i class="legend-swatch ambiguous"></i>Ambiguous / unreadable</span>
                <span class="legend-entry"><i class="legend-swatch blank"></i>Blank — no mark</span>
              </div>
            {/if}
            {#if reviewPages && reviewPages.length > 1 && (reviewPages.some((page) => page.detected_applicant_number != null) || reviewPages.some((page) => page.detected_birthdate))}
              <div class="review-identity-per-page">
                {#each reviewPages as page (page.page_no)}
                  {#if page.detected_applicant_number != null}
                    <span class="per-page-identity"><b>P{page.page_no}</b> No. {page.detected_applicant_number}</span>
                  {/if}
                  {#if page.detected_birthdate}
                    <span class="per-page-identity"><b>P{page.page_no}</b> Birthdate {page.detected_birthdate}</span>
                  {/if}
                {/each}
              </div>
            {/if}
            <p class="review-meta">{selectedSheet?.id ? `Grading.OMR.Sheet.${String(selectedSheet.id).padStart(4, '0')}` : 'OMR evidence'} · {evidenceLabel(selectedSheet)} · sheet confidence {confidenceLabel(selectedSheet?.confidence)} · {provider.name} v{provider.version} · private evidence</p>

            <div class="review-card identity-card">
              <div class="review-card-heading"><h2>Examinee</h2>{#if editAttemptId}<a class="edit-link" href={manualScoresHref()}>Edit</a>{/if}</div>
              <div class="identity-row">
                <div class="identity-crop">
                  {#if hasImageEvidence(selectedSheet) && reviewReady && identityRegion}
                    {#key `${selectedSheet?.version ?? ''}:${reviewImageUrl ?? ''}:${activeReviewPage}`}
                      <OmrRegionCrop imageUrl={reviewImageUrl} region={identityRegion} alt="Cropped applicant identity evidence" width={120} />
                    {/key}
                  {:else}
                    <span>{!reviewReady ? statusLabel(selectedSheet?.status) : hasImageEvidence(selectedSheet) ? 'No crop' : evidenceLabel(selectedSheet)}</span>
                  {/if}
                </div>
                <div class="identity-copy">
                  <strong>{boundApplicant?.name ?? selectedSheet?.detected_applicant_number ?? 'Unbound capture'}</strong>
                  <span>{boundApplicant?.reference_code ?? selectedSheet?.detected_applicant_number ?? 'No roster match'} · applicant identity confidence {identityConfidenceLabel(selectedSheet)}</span>
                  {#if selectedSheet?.detected_birthdate}
                    <span>Birthdate {selectedSheet.detected_birthdate} (MMDDYYYY) · advisory</span>
                  {/if}
                </div>
                <ChevronLeft class="identity-arrow" size={17} style="transform: rotate(180deg)" />
              </div>
              <p class="review-note">Cropped identity evidence from sheet photo. Detection is advisory; confirm paper against session roster.</p>
            </div>

            {#if hasImageEvidence(selectedSheet) && reviewReady && nameRegion}
              <div class="review-card name-card">
                <div class="review-card-heading"><h2>Handwritten name</h2></div>
                <div class="name-crop-wrap">
                  {#key `${selectedSheet?.version ?? ''}:${reviewImageUrl ?? ''}:${activeReviewPage}`}
                    <OmrRegionCrop imageUrl={reviewImageUrl} region={nameRegion} alt="Cropped handwritten name evidence" width={240} />
                  {/key}
                  <p class="review-note">Crop-only evidence — handwriting is not read automatically. Confirm the printed name against the session roster.</p>
                </div>
              </div>
            {/if}

            <label class="binding-field">
              <span>Bind to roster examinee</span>
              <select bind:value={selectedAttemptId}>
                <option value={null}>Select an eligible examinee</option>
                {#each roster as applicant}
                  <option value={applicant.id}>{applicant.reference_code} · {applicant.name}</option>
                {/each}
              </select>
            </label>

            {#if requiredIssues.length > 0}
              <div class="review-card issue-card" role="alert">
                <div class="review-card-heading"><div><h2>Required detection issues</h2><p>Resolve these observations before confirming the batch, or abstain with a reason.</p></div><span class="issue-count">{requiredIssues.length} open</span></div>
                <ul class="issue-list">
                  {#each requiredIssues as issue}
                    <li>
                      <div><strong>{issueLabel(issue)}</strong><span>{issue.area_code ?? 'Sheet'}{issue.question_no ? ` · Item ${issue.question_no}` : ''}{issue.page_no ? ` · Page ${issue.page_no}` : ''}</span></div>
                      <div class="issue-actions">
                        <button type="button" class="table-edit" onclick={() => resolveIssue(issue)} disabled={resolvingIssueId === issue.id}>{resolvingIssueId === issue.id ? 'Resolving…' : 'Resolve manually'}</button>
                      </div>
                    </li>
                  {/each}
                </ul>
              </div>
            {/if}

            {#if scoreRows.length > 0}
              <div class="review-card review-scores">
                <div class="review-card-heading"><h2>Scores</h2>{#if editAttemptId}<a class="edit-link" href={manualScoresHref()}>Edit scores</a>{/if}</div>
                <div class="score-table-wrap">
                  <table class="score-table">
                    <thead><tr><th>Area</th><th>Detected</th><th>Final</th><th>Action</th></tr></thead>
                    <tbody>
                      {#each scoreRows as row}
                        <tr>
                          <td><strong>{row.areaName}</strong><small>{row.areaCode}</small></td>
                          <td class="mono">{row.proposal.score} <span class="confidence">{confidenceLabel(row.proposal.confidence)}</span></td>
                          <td class="final-score">{selectedSheet?.reviewed?.per_area_scores?.[row.areaId]?.score ?? row.proposal.score}</td>
                        </tr>
                      {/each}
                    </tbody>
                  </table>
                </div>
              </div>
            {/if}

            <div class="review-card item-editor-card">
              <div class="review-card-heading"><div><h2>Item review</h2><p>Detected answer, mark, and score for each bubble row on page {activeReviewPage}.</p></div>{#if editAttemptId}<a class="edit-link" href={manualScoresHref()}>Open manual editor</a>{/if}</div>
              {#if reviewBusy}
                <p class="empty-review"><RefreshCw size={13} class="spin inline-icon" /> Detection is in progress — per-item evidence appears here automatically when analysis finishes.</p>
              {:else if reviewFailed}
                <p class="empty-review">Analysis failed; no per-item evidence is available. Retry the scan above.</p>
              {:else if pageItems.length > 0}
                <div class="item-table-wrap">
                  <table class="item-table">
                    <thead><tr><th>Item evidence</th><th>Detected</th><th>Confidence</th><th>Mark</th><th>Score</th><th>Action</th></tr></thead>
                    <tbody>
                      {#each pageItems as item (item.position)}
                        {@const mark = itemMark(item)}
                        <tr>
                          <td><div class="item-evidence"><div class="item-crop">{#if hasImageEvidence(selectedSheet) && reviewReady && item.region}<OmrRegionCrop imageUrl={reviewImageUrl} region={item.region} options={item.options} detectedOption={item.state === 'marked' ? item.detected_answer : null} correctOption={item.correct_option_code} state={item.state} alt={`Cropped item ${item.question_no} evidence`} title={itemCropTitle(item)} />{:else}<span>—</span>{/if}</div><div><strong>Item {item.question_no}</strong><small>{item.area_code ?? item.area_name ?? 'Aptitude area'}{#if (item.page_no ?? 1) > 1} · P{item.page_no}{/if}</small></div></div></td>
                          <td><span class="answer-chip {item.detected_answer ? '' : 'empty'}">{item.detected_answer ?? '—'}</span></td>
                          <td class="confidence">{confidenceLabel(item.confidence)}</td>
                          <td><span class="item-mark {mark.tone}">{mark.label}</span></td>
                          <td class="item-score">{itemScore(item)}</td>
                          <td>{#if editAttemptId}<a class="table-edit" href={manualScoresHref(item.area_id)}>Correct</a>{:else}<span class="muted-text">Bind first</span>{/if}</td>
                        </tr>
                      {/each}
                    </tbody>
                  </table>
                </div>
              {:else}
                <p class="empty-review">No per-item detection payload is available for this sheet. Open manual score input to correct the authoritative score.</p>
              {/if}
            </div>

            {#if requiredIssues.length > 0}
              <label class="abstain-field">
                <span>Manual follow-up reason</span>
                <textarea bind:value={abstainReason} maxlength="255" placeholder="Explain why this sheet needs manual scoring or another scan"></textarea>
              </label>
            {/if}
            <div class="review-actions">
              <button class="outline-button" type="button" onclick={openGallery}><ChevronLeft size={16} /> Back to scans</button>
              {#if requiredIssues.length > 0}
                <button class="outline-button" type="button" onclick={() => saveReview('abstain')} disabled={savingReview || reviewBusy || !selectedAttemptId}><Info size={16} /> {savingReview ? 'Saving…' : 'Abstain for manual follow-up'}</button>
              {/if}
              <button class="primary-button" type="button" onclick={() => saveReview()} disabled={savingReview || reviewBusy || !selectedAttemptId || requiredIssues.length > 0}><Check size={16} /> {savingReview ? 'Saving…' : 'Save review'}</button>
            </div>
          </section>
        {:else}
          <section class="gallery-screen" aria-label="Scans gallery">
            <div class="section-heading">
              <div><p class="eyebrow">Scans</p><h1>Captures gallery</h1></div>
              <div class="heading-actions">
                {#if batch?.status === 'open' && gallery.length > 0}
                  <button class="danger-button" type="button" onclick={rejectBatch} disabled={confirmingBatch}><Trash2 size={14} /> Reject batch</button>
                {/if}
                <button class="icon-action focus-only" type="button" aria-label="Close gallery" onclick={openCamera}><X size={19} /></button>
              </div>
            </div>
            <div class="tabs" role="tablist">
              <button class:active={activeTab === 'draft'} type="button" role="tab" aria-selected={activeTab === 'draft'} onclick={() => setActiveTab('draft')}>Draft <b>{gallery.filter((sheet) => sheet.status !== 'committed').length}</b></button>
              <button class:active={activeTab === 'all'} type="button" role="tab" aria-selected={activeTab === 'all'} onclick={() => setActiveTab('all')}>All <b>{gallery.length}</b></button>
            </div>

            <div class="scan-grid">
              {#each visibleScans as sheet (sheet.id)}
                {@const info = getExamineeInfo(sheet)}
                <div class="scan-card" role="button" tabindex="0" onclick={() => openReview(sheet)} onkeydown={(event) => { if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); openReview(sheet); } }}>
                  <div class="scan-thumb">
                    {#if hasImageEvidence(sheet)}
                      <img src={sheet.image_url} alt={info.name} class="scan-img-thumb" loading="lazy" decoding="async" onerror={() => markImageUnavailable(sheet)} />
                    {:else}
                      <div class="mini-paper">
                        <span>{evidenceLabel(sheet) === 'Preview placeholder' ? 'PREVIEW' : 'NEXIAM'}</span>
                        <i></i><i class="fill"></i><i></i><i></i>
                      </div>
                    {/if}
                    <div class="examinee-avatar" title={info.name}>{info.initials}</div>
                    <span class="status-dot {sheet.status}" title={statusLabel(sheet.status)}></span>
                  </div>
                  <div class="scan-meta">
                    <div class="meta-header">
                      <strong title={info.name}>{info.name}</strong>
                      {#if info.isBound}
                        <span class="bound-icon" title="Bound to roster examinee"><UserCheck size={13} /></span>
                      {/if}
                    </div>
                    <span class="examinee-ref">{info.code}</span>
                    {#if Number(sheet.expected_page_count ?? 1) > 1}
                      <div class="page-chips" role="list" aria-label="Sheet pages">
                        {#each Array(Number(sheet.expected_page_count)) as _, index}
                          {@const pageNo = index + 1}
                          {@const page = (sheet.pages ?? []).find((candidate) => Number(candidate.page_no) === pageNo)}
                          <span class="page-chip" class:captured={page?.captured} class:missing={!page?.captured} role="listitem" title={`Page ${pageNo}: ${pageLabel(sheet, pageNo)}`}>
                            <span class="page-chip-dot"></span>
                            P{pageNo}
                          </span>
                        {/each}
                      </div>
                    {/if}
                    <div class="meta-footer">
                      <small>{sheet.capture_mode === 'auto' ? 'Auto' : 'Manual'} · {statusLabel(sheet.status)}</small>
                      <small class="time-stamp">{sheet.created_at ? new Date(sheet.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : 'just now'}</small>
                    </div>
                    {#if sheet.status === 'pending_pages' && missingPageCount(sheet) > 0}
                      <div class="card-actions">
                        <button class="card-action primary" type="button" onclick={(event) => { event.stopPropagation(); resumeSet(sheet); }}>
                          <Camera size={12} /> Resume set
                        </button>
                        {#each (sheet.pages ?? []).filter((page) => page.captured) as page (page.page_no)}
                          <button class="card-action" type="button" title={`Retake page ${page.page_no}`} onclick={(event) => { event.stopPropagation(); triggerReplace(sheet, Number(page.page_no)); }}>
                            <RefreshCw size={12} /> Retake P{page.page_no}
                          </button>
                        {/each}
                      </div>
                    {/if}
                    {#if sheet.status === 'failed_terminal'}
                      <div class="card-actions">
                        <button class="card-action primary" type="button" onclick={(event) => { event.stopPropagation(); retrySheet(sheet); }}>
                          <RefreshCw size={12} /> Retry scan
                        </button>
                      </div>
                    {/if}
                    <div class="audit-row">
                      <span class="evidence-badge" class:available={hasImageEvidence(sheet)}>
                        {#if hasImageEvidence(sheet)}<FileImage size={11} />{:else}<ImageIcon size={11} />{/if}
                        {evidenceLabel(sheet)}
                      </span>
                      {#if sheet.identity_confidence != null}
                        <span class="audit-pill" class:high-conf={sheet.identity_confidence >= 0.8} class:low-conf={sheet.identity_confidence < 0.8}>
                          {Math.round(sheet.identity_confidence * 100)}% identity match
                        </span>
                      {:else}
                        <span class="audit-pill neutral">Identity not detected</span>
                      {/if}
                    </div>
                  </div>
                </div>
              {:else}
                <div class="empty-gallery">
                  <ImageIcon size={30} />
                  <strong>No scans yet</strong>
                  <span>Capture or upload an answer sheet to start the review flow.</span>
                  <button class="outline-button" type="button" onclick={openCamera}><Camera size={16} /> Capture a sheet</button>
                </div>
              {/each}
            </div>

            <button class="primary-button gallery-capture focus-only" type="button" onclick={openCamera}><Camera size={17} /> Capture another sheet</button>

            {#if allReviewed && batch?.status === 'open'}
              <button class="confirm-button" type="button" onclick={confirmBatch} disabled={confirmingBatch}><ShieldCheck size={17} /> {confirmingBatch ? 'Confirming…' : 'Confirm session scans'}</button>
            {/if}
          </section>
        {/if}
      </div>
    </div>
  {/if}

  <input class="hidden-input" bind:this={fileInput} type="file" accept="image/jpeg,image/png,image/webp" multiple onchange={handleFiles} />
</div>

<style>
  .omr-app {
    --omr-bg: var(--background);
    --omr-surface: var(--card);
    --omr-surface-solid: var(--card-solid);
    --omr-text: var(--foreground);
    --omr-muted: var(--muted-foreground);
    --omr-border: var(--border);
    --omr-primary: var(--primary);
    --omr-primary-soft: color-mix(in oklab, var(--primary) 12%, transparent);
    --omr-primary-strong: color-mix(in oklab, var(--primary) 22%, var(--background));
    --omr-stage: oklch(0.27 0.04 148);
    --omr-stage-deep: oklch(0.20 0.035 148);
    --omr-stage-text: oklch(0.94 0.02 145);
    min-height: 100dvh;
    width: 100%;
    max-width: 100%;
    margin: 0 auto;
    display: flex;
    flex-direction: column;
    background: var(--omr-bg);
    color: var(--omr-text);
    font-family: var(--font-sans);
    isolation: isolate;
  }
  .omr-app, .omr-app * { box-sizing: border-box; }
  .omr-topbar { height: 56px; min-height: 56px; flex-shrink: 0; display: flex; align-items: center; gap: 16px; padding: 0 clamp(16px, 3vw, 24px); border-bottom: 1px solid color-mix(in oklab, var(--omr-border) 70%, transparent); background: color-mix(in oklab, var(--omr-surface) 94%, transparent); position: sticky; top: 0; z-index: 10; backdrop-filter: blur(12px); }
  .top-action, .icon-action { border: 0; background: transparent; color: var(--omr-muted); cursor: pointer; display: inline-flex; align-items: center; gap: 7px; padding: 8px; border-radius: var(--radius-md); }
  .top-action:hover, .icon-action:hover { background: var(--omr-primary-soft); color: var(--omr-primary); }
  .top-action { font-size: 13px; font-weight: 600; }
  .session-title { min-width: 0; flex: 1; text-align: center; font-weight: 650; font-size: 14px; color: var(--omr-text); }
  .session-title small { display: block; color: var(--omr-muted); font: 10px var(--font-mono); letter-spacing: .08em; text-transform: uppercase; margin-top: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .synthetic-tag { color: var(--omr-primary); font-weight: 600; }
  .top-actions { display: flex; align-items: center; gap: 8px; }
  .scan-count { font: 10px var(--font-mono); color: var(--omr-muted); white-space: nowrap; }

  .desktop-view-toggle { display: none; }
  @media (min-width: 1024px) {
    .omr-app { height: 100dvh; min-height: 0; overflow: hidden; }
    .desktop-view-toggle { display: flex; align-items: center; border: 1px solid var(--omr-border); border-radius: var(--radius-md); padding: 2px; background: var(--omr-surface-solid); }
    .toggle-btn { border: 0; background: transparent; color: var(--omr-muted); font-size: 11px; font-weight: 600; padding: 5px 9px; border-radius: calc(var(--radius-md) - 2px); cursor: pointer; display: inline-flex; align-items: center; gap: 5px; }
    .toggle-btn:hover { color: var(--omr-text); }
    .toggle-btn.active { background: var(--omr-primary-soft); color: var(--omr-primary); }
  }

  .settings-wrap { position: relative; display: inline-flex; }
  .settings-backdrop { position: fixed; inset: 0; z-index: 4; }
  .settings-menu { position: absolute; right: 0; top: calc(100% + 8px); z-index: 5; min-width: 280px; max-width: min(360px, calc(100vw - 32px)); max-height: min(70vh, 520px); overflow-y: auto; padding: 9px; background: var(--omr-surface-solid); border: 1px solid var(--omr-border); border-radius: var(--radius-lg); box-shadow: var(--shadow-lg); display: flex; flex-direction: column; gap: 4px; }
  .settings-menu-heading { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
  .settings-menu-title { margin: 6px 0 2px; padding: 2px 9px; color: var(--omr-muted); font: 10px var(--font-mono); text-transform: uppercase; letter-spacing: .1em; }
  .settings-menu-title:first-child { margin-top: 0; }
  .settings-refresh { flex: 0 0 auto; padding: 5px !important; color: var(--omr-muted) !important; }
  .settings-refresh:hover { color: var(--omr-primary) !important; }
  .settings-menu button span { min-width: 0; display: flex; flex-direction: column; gap: 2px; }
  .settings-menu button strong, .settings-menu button small { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .settings-menu button small { color: var(--omr-muted); font-size: 10px; font-weight: 400; line-height: 1.3; }
  .settings-menu button { border: 0; background: transparent; color: var(--omr-text); text-align: left; padding: 8px 10px; border-radius: var(--radius-md); font-size: 12px; cursor: pointer; display: flex; justify-content: space-between; align-items: center; gap: 10px; }
  .settings-menu button:hover:not(:disabled) { background: var(--omr-primary-soft); color: var(--omr-primary); }
  .settings-menu button.selected { background: var(--omr-primary-soft); color: var(--omr-primary); font-weight: 650; }
  .settings-menu button:disabled { cursor: not-allowed; opacity: .58; }
  .menu-hint-box { margin: 2px 4px 4px; padding: 8px 10px; border-radius: var(--radius-md); background: var(--omr-primary-soft); color: var(--omr-muted); font-size: 11px; line-height: 1.45; }

  .start-screen { flex: 1; display: grid; place-items: center; padding: 32px 18px; }
  .start-card { width: min(100%, 520px); display: flex; flex-direction: column; align-items: center; gap: 11px; text-align: center; background: var(--omr-surface); border: 1px solid var(--omr-border); border-radius: var(--radius-xl); padding: 38px 24px; box-shadow: var(--shadow-lg); }
  .start-card > svg { color: var(--omr-primary); }
  .start-card h1 { margin: 0; color: var(--omr-text); font-size: 24px; letter-spacing: -.02em; }
  .start-card > p:not(.eyebrow) { max-width: 410px; color: var(--omr-muted); font-size: 13px; line-height: 1.6; }
  .pair-button { width: 100%; display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 14px 15px; border: 1px solid color-mix(in oklab, var(--omr-primary) 38%, var(--omr-border)); border-radius: var(--radius-md); background: var(--omr-primary-soft); color: var(--omr-text); text-align: left; cursor: pointer; font-size: 13px; }
  .pair-button:hover { border-color: var(--omr-primary); background: var(--omr-primary-strong); }
  .pair-button small { color: var(--omr-primary); font-weight: 650; }

  /* Workspace Layout & Split Mode */
  .omr-workspace { display: flex; flex-direction: column; flex: 1; min-height: 0; overflow: hidden; }
  .workspace-panel { flex: 1; min-height: 0; display: flex; flex-direction: column; }
  .is-hidden { display: none !important; }

  @media (min-width: 1024px) {
    .omr-workspace.split-mode {
      display: grid;
      grid-template-columns: minmax(380px, 42%) 1fr;
      gap: 0;
      align-items: stretch;
      height: 100%;
      min-height: 0;
      overflow: hidden !important;
    }
    .omr-workspace.split-mode .capture-screen {
      border-right: 1px solid var(--omr-border);
      height: 100%;
      min-height: 0;
      overflow: hidden !important;
      display: flex !important;
      flex-direction: column !important;
    }
    .omr-workspace.split-mode .workspace-panel {
      height: 100%;
      min-height: 0;
      overflow-y: auto !important;
      display: flex !important;
      flex-direction: column !important;
      background: var(--omr-bg);
    }
    .omr-workspace.split-mode .gallery-screen,
    .omr-workspace.split-mode .review-screen {
      width: 100%;
      max-width: 1000px;
      padding: 24px 28px 36px;
    }
    .omr-workspace.split-mode .focus-only {
      display: none !important;
    }
  }

  .capture-screen { flex: 1; min-height: 0; display: flex; flex-direction: column; overflow: hidden; }
  .camera-stage { flex: 1; min-height: 0; display: flex; align-items: center; justify-content: center; position: relative; overflow: hidden; background: radial-gradient(circle at 50% 43%, var(--omr-stage), var(--omr-stage-deep) 72%); }
  .capture-controls { flex-shrink: 0; display: flex; align-items: center; justify-content: space-between; gap: 18px; padding: 18px clamp(18px, 4vw, 42px) 11px; background: var(--omr-surface-solid); border-top: 1px solid var(--omr-border); }
  .camera-footer { flex-shrink: 0; display: flex; justify-content: center; gap: 19px; padding: 0 16px 18px; background: var(--omr-surface-solid); }
  .camera-feed { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
  .scan-hint { position: absolute; top: 18px; left: 0; right: 0; display: flex; justify-content: center; align-items: center; gap: 7px; color: var(--omr-stage-text); font: 10px var(--font-mono); letter-spacing: .1em; text-transform: uppercase; opacity: .82; }
  .camera-readiness { position: absolute; left: 50%; bottom: 21%; transform: translateX(-50%); display: inline-flex; align-items: center; gap: 9px; max-width: min(88%, 360px); padding: 8px 12px; border: 1px solid color-mix(in oklab, var(--omr-primary) 52%, white 16%); border-radius: 999px; background: color-mix(in oklab, var(--omr-stage-deep) 82%, transparent); color: var(--omr-stage-text); box-shadow: 0 4px 18px rgba(0, 0, 0, .2); }
  .camera-readiness > span:last-child { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
  .camera-readiness strong { color: white; font-size: 11px; letter-spacing: .03em; }
  .camera-readiness small { color: color-mix(in oklab, var(--omr-stage-text) 82%, transparent); font-size: 10px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .readiness-dot { width: 9px; height: 9px; flex: 0 0 auto; border-radius: 50%; background: #65c18c; box-shadow: 0 0 0 4px color-mix(in oklab, #65c18c 18%, transparent); }
  .preflight-panel { position: absolute; left: 50%; bottom: 21%; transform: translateX(-50%); display: flex; flex-direction: column; gap: 6px; width: min(92%, 400px); padding: 10px 12px; border-radius: 10px; border: 1px solid color-mix(in oklab, var(--omr-primary) 45%, white 12%); background: color-mix(in oklab, var(--omr-stage-deep) 88%, transparent); color: var(--omr-stage-text); box-shadow: 0 4px 18px rgba(0, 0, 0, .22); backdrop-filter: blur(3px); }
  .preflight-panel.validated { border-color: color-mix(in oklab, #65c18c 60%, white 10%); }
  .preflight-panel.rejected { border-color: color-mix(in oklab, #e05252 65%, white 8%); }
  .preflight-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
  .preflight-badge { display: inline-flex; align-items: center; gap: 5px; padding: 3px 9px; border-radius: 999px; font-size: 11px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
  .preflight-badge.ok { background: color-mix(in oklab, #65c18c 22%, transparent); color: #a7e3bc; }
  .preflight-badge.bad { background: color-mix(in oklab, #e05252 22%, transparent); color: #f2a8a8; }
  .preflight-badge.neutral { background: color-mix(in oklab, var(--omr-primary) 18%, transparent); color: var(--omr-stage-text); }
  .preflight-calibration, .preflight-code { color: color-mix(in oklab, var(--omr-stage-text) 78%, transparent); font-size: 10px; }
  .preflight-detail { color: var(--omr-stage-text); font-size: 12px; line-height: 1.45; }
  .preflight-hint { color: #f2c9a8; font-size: 11px; }
  .preflight-meta { color: color-mix(in oklab, var(--omr-stage-text) 80%, transparent); font-size: 11px; }
  .preflight-retake { align-self: flex-start; margin-top: 2px; }
  .gate-chips { display: flex; flex-wrap: wrap; gap: 5px; }
  .gate-chip { padding: 2px 8px; border-radius: 999px; border: 1px solid; font-size: 10px; font-weight: 600; letter-spacing: .02em; }
  .gate-chip.gate-pass { border-color: color-mix(in oklab, #65c18c 55%, transparent); background: color-mix(in oklab, #65c18c 14%, transparent); color: #a7e3bc; }
  .gate-chip.gate-fail { border-color: color-mix(in oklab, #e05252 60%, transparent); background: color-mix(in oklab, #e05252 14%, transparent); color: #f2a8a8; }
  .frame { width: 31px; height: 31px; position: absolute; opacity: .9; border-color: color-mix(in oklab, var(--primary) 70%, white); }
  .frame-tl { top: 12%; left: 8%; border-left: 2px solid; border-top: 2px solid; }
  .frame-tr { top: 12%; right: 8%; border-right: 2px solid; border-top: 2px solid; }
  .frame-bl { bottom: 12%; left: 8%; border-left: 2px solid; border-bottom: 2px solid; }
  .frame-br { bottom: 12%; right: 8%; border-right: 2px solid; border-bottom: 2px solid; }
  .camera-caption { position: absolute; bottom: 15%; color: color-mix(in oklab, var(--omr-stage-text) 78%, transparent); font-size: 12px; }
  .camera-message { display: flex; flex-direction: column; align-items: center; gap: 12px; text-align: center; color: var(--omr-stage-text); padding: 25px; }
  .camera-message strong { color: white; font-size: 16px; font-weight: 600; }
  .muted-stage-icon { color: color-mix(in oklab, var(--omr-stage-text) 60%, transparent); }
  .stage-upload-btn { margin-top: 4px; }
  .capture-controls { display: flex; align-items: center; justify-content: space-between; gap: 18px; padding: 18px clamp(18px, 4vw, 42px) 11px; background: var(--omr-surface-solid); border-top: 1px solid var(--omr-border); }
  .control-side { color: var(--omr-muted); border: 0; background: transparent; display: flex; flex-direction: column; align-items: center; gap: 5px; font-size: 11px; cursor: pointer; }
  .control-side:hover, .text-button:hover { color: var(--omr-primary); }
  .shutter { width: 68px; height: 68px; border: 4px solid color-mix(in oklab, var(--omr-stage-text) 90%, transparent); border-radius: 50%; background: transparent; padding: 5px; cursor: pointer; }
  .shutter span { width: 50px; height: 50px; display: flex; align-items: center; justify-content: center; border-radius: 50%; background: var(--omr-primary); color: var(--primary-foreground); }
  .shutter:disabled { opacity: .45; cursor: not-allowed; }
  .mode-toggle { display: flex; border: 1px solid var(--omr-border); border-radius: var(--radius-md); padding: 3px; background: var(--omr-primary-soft); }
  .mode-toggle button { border: 0; background: transparent; color: var(--omr-muted); padding: 7px 9px; font: 11px var(--font-mono); cursor: pointer; border-radius: calc(var(--radius-md) - 2px); }
  .mode-toggle button.active { background: var(--omr-primary); color: var(--primary-foreground); }
  .camera-footer { display: flex; justify-content: center; gap: 19px; padding: 0 16px 18px; background: var(--omr-surface-solid); }
  .text-button { border: 0; color: var(--omr-muted); background: transparent; display: inline-flex; align-items: center; gap: 5px; font-size: 11px; cursor: pointer; }

  .gallery-screen, .review-screen { width: min(calc(100% - 32px), 900px); margin: 0 auto; padding: 28px 0 38px; flex: 1; }
  .section-heading { display: flex; justify-content: space-between; align-items: flex-start; gap: 15px; }
  .eyebrow { color: var(--omr-primary); font: 10px var(--font-mono); text-transform: uppercase; letter-spacing: .12em; margin: 0 0 7px; }
  .section-heading h1 { color: var(--omr-text); font-size: clamp(21px, 4vw, 28px); font-weight: 650; margin: 0; letter-spacing: -.025em; }
  .tabs { display: flex; gap: 6px; margin: 20px 0 15px; border-bottom: 1px solid var(--omr-border); }
  .tabs button { border: 0; border-bottom: 2px solid transparent; background: transparent; color: var(--omr-muted); padding: 9px 11px; cursor: pointer; font-size: 12px; }
  .tabs button.active { color: var(--omr-primary); border-bottom-color: var(--omr-primary); font-weight: 650; }
  .tabs b { margin-left: 5px; font: 10px var(--font-mono); color: var(--omr-muted); }

  /* Scan Grid & Cards */
  .scan-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 14px; }
  .scan-card { text-align: left; padding: 0; border: 1px solid var(--omr-border); background: var(--omr-surface); color: var(--omr-text); border-radius: var(--radius-lg); overflow: hidden; cursor: pointer; transition: border-color .15s, transform .15s, box-shadow .15s; }
  .scan-card:hover { border-color: var(--omr-primary); transform: translateY(-1px); box-shadow: var(--shadow-md); }

  .scan-thumb { height: 145px; background: var(--omr-primary-soft); display: flex; justify-content: center; align-items: center; position: relative; overflow: hidden; }
  .scan-img-thumb { width: 100%; height: 100%; object-fit: cover; transition: transform .2s ease; }
  .scan-card:hover .scan-img-thumb { transform: scale(1.03); }

  .mini-paper { width: 84px; height: 115px; background: oklch(0.97 0.015 87); color: var(--omr-primary); padding: 10px 8px; transform: rotate(-3deg); box-shadow: var(--shadow-md); font: 6px var(--font-mono); }
  .mini-paper i { display: inline-block; width: 9px; height: 9px; margin: 15px 4px 0 0; border: 1px solid oklch(0.62 0.03 145); border-radius: 50%; }
  .mini-paper i.fill { background: var(--omr-primary); border-color: var(--omr-primary); }

  .examinee-avatar { position: absolute; bottom: 8px; left: 8px; z-index: 2; width: 32px; height: 32px; border-radius: 50%; background: var(--omr-primary); color: var(--primary-foreground); display: flex; align-items: center; justify-content: center; font: 700 11px var(--font-sans); box-shadow: 0 3px 8px rgba(0, 0, 0, .25); border: 2px solid var(--omr-surface-solid); }

  .status-dot { position: absolute; top: 10px; right: 10px; z-index: 2; width: 10px; height: 10px; border-radius: 50%; background: var(--omr-muted); box-shadow: 0 0 0 3px color-mix(in oklab, var(--omr-surface) 80%, transparent); }
  .status-dot.needs_review, .status-dot.processing, .status-dot.queued { background: var(--warning); }
  .status-dot.reviewed, .status-dot.committed { background: var(--success); }
  .status-dot.failed_terminal { background: var(--destructive); }

  .scan-meta { display: flex; flex-direction: column; gap: 4px; padding: 12px 14px 14px; }
  .meta-header { display: flex; align-items: center; justify-content: space-between; gap: 6px; }
  .meta-header strong { font-size: 13px; font-weight: 650; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .bound-icon { color: var(--success); flex-shrink: 0; }
  .examinee-ref { color: var(--omr-primary); font: 11px var(--font-mono); font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .meta-footer { display: flex; justify-content: space-between; align-items: center; gap: 4px; margin-top: 2px; }
  .meta-footer small { color: var(--omr-muted); font: 10px var(--font-mono); }
  .audit-row { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; margin-top: 5px; }
  .evidence-badge { display: inline-flex; align-items: center; gap: 4px; padding: 3px 7px; border: 1px solid color-mix(in oklab, #b7791f 35%, var(--omr-border)); border-radius: 999px; font: 600 10px var(--font-mono); color: #966515; white-space: nowrap; }
  .evidence-badge.available { border-color: color-mix(in oklab, #2f8f62 35%, var(--omr-border)); color: #26724f; }
  .audit-pill { padding: 3px 7px; border-radius: 999px; font: 600 10px var(--font-mono); background: var(--omr-primary-soft); color: var(--omr-primary); white-space: nowrap; }
  .audit-pill.high-conf { background: color-mix(in oklab, #2f8f62 14%, var(--omr-surface-solid)); color: #26724f; }
  .audit-pill.low-conf { background: color-mix(in oklab, #b7791f 16%, var(--omr-surface-solid)); color: #966515; }
  .audit-pill.neutral { background: var(--omr-stage-deep); color: var(--omr-muted); }

  .empty-gallery { min-height: 270px; grid-column: 1 / -1; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 9px; color: var(--omr-muted); text-align: center; border: 1px dashed var(--omr-border); border-radius: var(--radius-lg); }
  .empty-gallery strong { color: var(--omr-text); font-size: 15px; }
  .empty-gallery span { max-width: 320px; font-size: 12px; line-height: 1.5; }

  .primary-button, .outline-button, .danger-button { border-radius: var(--radius-md); min-height: 40px; padding: 0 15px; display: inline-flex; justify-content: center; align-items: center; gap: 7px; cursor: pointer; font-weight: 650; font-size: 12px; }
  .primary-button { color: var(--primary-foreground); background: var(--omr-primary); border: 1px solid var(--omr-primary); }
  .primary-button:hover { filter: brightness(1.08); }
  .outline-button { color: var(--omr-text); background: var(--omr-surface); border: 1px solid var(--omr-border); }
  .outline-button:hover { border-color: var(--omr-primary); background: var(--omr-primary-soft); }
  .danger-button { color: var(--destructive); background: transparent; border: 1px solid color-mix(in oklab, var(--destructive) 35%, var(--omr-border)); }
  .gallery-capture { margin-top: 18px; width: 100%; }
  .confirm-button { margin-top: 14px; width: 100%; min-height: 42px; border: 1px solid var(--omr-primary); border-radius: var(--radius-md); background: var(--omr-primary-strong); color: var(--omr-primary); display: inline-flex; align-items: center; justify-content: center; gap: 7px; cursor: pointer; font-size: 12px; font-weight: 700; }
  .confirm-button:disabled { opacity: .6; cursor: wait; }

  .binding-field, .score-summary { display: flex; flex-direction: column; gap: 7px; margin-top: 14px; padding: 14px; border: 1px solid var(--omr-border); border-radius: var(--radius-lg); background: var(--omr-surface); color: var(--omr-muted); font-size: 11px; }
  .binding-field span { color: var(--omr-text); font-weight: 650; }
  .binding-field select { min-height: 40px; border: 1px solid var(--omr-border); border-radius: var(--radius-md); background: var(--omr-surface-solid); color: var(--omr-text); padding: 0 10px; }
  .score-summary span:not(.eyebrow) { display: flex; justify-content: space-between; color: var(--omr-muted); font: 11px var(--font-mono); }
  .score-summary strong { color: var(--omr-text); }

  .review-image { margin-top: 18px; min-height: 350px; background: var(--omr-stage-deep); border: 1px solid var(--omr-border); border-radius: var(--radius-lg); display: flex; align-items: center; justify-content: center; position: relative; overflow: hidden; }
  .review-paper { width: min(66%, 300px); aspect-ratio: .71; background: oklch(0.97 0.015 87); color: oklch(0.38 0.04 148); box-shadow: var(--shadow-xl); padding: 21px 18px; transform: rotate(-1.2deg); }
  .paper-brand { color: var(--omr-primary); font: 700 8px var(--font-mono); letter-spacing: .12em; }
  .paper-rule { height: 1px; background: oklch(0.82 0.02 145); margin: 16px 0; }
  .paper-bubbles { display: grid; grid-template-columns: repeat(6, 1fr); gap: 12px 8px; }
  .paper-bubbles i { width: 14px; height: 14px; border: 1px solid oklch(0.60 0.03 145); border-radius: 50%; }
  .paper-bubbles i.marked { background: var(--omr-primary); border-color: var(--omr-primary); }
  .paper-bubbles i.warning { background: var(--warning); border-color: var(--warning); }
  .paper-footer { color: oklch(0.52 0.03 148); font: 10px var(--font-mono); }
  .review-photo { max-width: 100%; max-height: 620px; object-fit: contain; border-radius: var(--radius-sm); box-shadow: var(--shadow-xl); }
  .review-photo-wrap { position: relative; display: inline-block; line-height: 0; }
  .review-photo-wrap .review-photo { display: block; }
  .overlay-layer { position: absolute; inset: 0; pointer-events: none; }
  .ov-row { position: absolute; border: 2px solid transparent; border-radius: 3px; box-sizing: border-box; }
  .ov-row.correct { border-color: #2fdd8d; }
  .ov-row.incorrect { border-color: #ff6b68; }
  .ov-row.ambiguous { border-color: #ffc24d; }
  .ov-option { position: absolute; display: grid; place-items: center; border-radius: 50%; border: 2px solid transparent; background: rgba(255, 255, 255, 0.16); box-sizing: border-box; }
  .ov-option.correct { border-color: #2fdd8d; }
  .ov-option.incorrect { border-color: #ff6b68; }
  .ov-option.ambiguous { border-color: #ffc24d; }
  .ov-option b { font: 700 10px var(--font-mono); color: #fff; text-shadow: 0 1px 2px rgba(0, 0, 0, 0.85); }
  .overlay-legend { display: flex; flex-wrap: wrap; gap: 14px; margin: 10px 2px 0; padding: 9px 12px; border: 1px solid var(--omr-border); border-radius: var(--radius-md); background: var(--omr-surface); }
  .legend-entry { display: inline-flex; align-items: center; gap: 6px; color: var(--omr-muted); font: 600 10px var(--font-mono); text-transform: uppercase; letter-spacing: .05em; }
  .legend-swatch { width: 10px; height: 10px; border-radius: 50%; display: inline-block; }
  .legend-swatch.correct { background: #2fdd8d; }
  .legend-swatch.incorrect { background: #ff6b68; }
  .legend-swatch.ambiguous { background: #ffc24d; }
  .legend-swatch.blank { border: 1px dashed var(--omr-muted); background: transparent; }
  .review-busy, .review-failed { display: flex; align-items: flex-start; gap: 10px; margin-bottom: 12px; padding: 11px 13px; border-radius: var(--radius-md); font-size: 12px; }
  .review-busy { border: 1px solid color-mix(in oklab, #b7791f 42%, var(--omr-border)); background: color-mix(in oklab, #b7791f 6%, var(--omr-surface-solid)); color: var(--omr-text); }
  .review-failed { border: 1px solid color-mix(in oklab, #b94a48 45%, var(--omr-border)); background: color-mix(in oklab, #b94a48 6%, var(--omr-surface-solid)); color: var(--omr-text); align-items: center; }
  .review-busy strong, .review-failed strong { display: block; font-size: 12px; }
  .review-busy span, .review-failed span { display: block; margin-top: 2px; color: var(--omr-muted); font-size: 11px; line-height: 1.4; }
  .review-busy > svg { color: #966515; flex-shrink: 0; margin-top: 1px; }
  .review-failed > svg { color: #a33c3a; flex-shrink: 0; }
  .review-failed .table-edit { margin-left: auto; border: 0; background: transparent; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; }
  .tag-spin { display: inline-block; vertical-align: -2px; margin-right: 4px; }
  .inline-icon { display: inline-block; vertical-align: -2px; margin-right: 6px; }
  .name-crop-wrap { margin-top: 14px; max-width: 320px; }
  .name-crop-wrap .review-note { margin-top: 10px; }
  .overlay-label, .review-badge { display: inline-flex; align-items: center; gap: 6px; }
  .overlay-label { position: absolute; top: 12px; left: 12px; padding: 6px 9px; border-radius: var(--radius-md); background: var(--omr-primary); color: var(--primary-foreground); font-size: 11px; font-weight: 650; }

  .review-meta { margin: 9px 2px 0; color: var(--omr-muted); font: 10px var(--font-mono); letter-spacing: .04em; }
  .review-card { margin-top: 18px; padding: 18px; background: var(--omr-surface-solid); border: 1px solid var(--omr-border); border-radius: var(--radius-lg); box-shadow: 0 12px 28px rgba(17, 24, 39, .04); }
  .review-card-heading { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }
  .review-card-heading h2 { margin: 0; color: var(--omr-text); font-size: 16px; }
  .review-card-heading p { margin: 5px 0 0; color: var(--omr-muted); font-size: 12px; line-height: 1.45; }
  .edit-link, .table-edit { color: var(--omr-primary); font-size: 12px; font-weight: 700; text-decoration: none; white-space: nowrap; }
  .edit-link:hover, .table-edit:hover { text-decoration: underline; }
  .identity-row { display: flex; align-items: center; gap: 14px; margin-top: 16px; }
  .identity-crop { position: relative; flex: 0 0 96px; width: 96px; min-height: 56px; overflow: hidden; border: 1px solid var(--omr-border); border-radius: var(--radius-md); background: var(--omr-stage-deep); color: var(--omr-muted); display: grid; place-items: center; font: 10px var(--font-mono); }
  .identity-copy { min-width: 0; flex: 1; display: flex; flex-direction: column; gap: 5px; }
  .identity-copy strong { color: var(--omr-text); font-size: 15px; }
  .identity-copy span { color: var(--omr-muted); font-size: 11px; line-height: 1.4; }
  .identity-arrow { flex: 0 0 auto; color: var(--omr-muted); }
  .review-note { margin: 14px 0 0; color: var(--omr-muted); font-size: 11px; line-height: 1.5; }
  .issue-card { border-color: color-mix(in oklab, #b7791f 42%, var(--omr-border)); background: color-mix(in oklab, #b7791f 5%, var(--omr-surface-solid)); }
  .issue-count { color: #966515; font: 700 10px var(--font-mono); text-transform: uppercase; white-space: nowrap; }
  .issue-list { display: grid; gap: 8px; margin: 14px 0 0; padding: 0; list-style: none; }
  .issue-list li { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; padding: 9px 10px; border: 1px solid color-mix(in oklab, #b7791f 22%, var(--omr-border)); border-radius: var(--radius-md); }
  .issue-list strong { display: block; color: var(--omr-text); font-size: 12px; }
  .issue-list span { display: block; margin-top: 3px; color: var(--omr-muted); font: 10px var(--font-mono); }
  .issue-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 8px; }
  .abstain-field { display: grid; gap: 7px; margin-top: 16px; }
  .abstain-field span { color: var(--omr-text); font-size: 12px; font-weight: 650; }
  .abstain-field textarea { min-height: 72px; resize: vertical; border: 1px solid var(--omr-border); border-radius: var(--radius-md); background: var(--omr-surface-solid); color: var(--omr-text); padding: 9px 10px; font: 12px var(--font-sans); }
  .score-table-wrap, .item-table-wrap { overflow-x: auto; margin-top: 14px; }
  .score-table, .item-table { width: 100%; min-width: 540px; border-collapse: collapse; color: var(--omr-text); font-size: 12px; }
  .score-table th, .item-table th { padding: 9px 10px; border-bottom: 1px solid var(--omr-border); color: var(--omr-muted); font: 10px var(--font-mono); letter-spacing: .05em; text-align: left; text-transform: uppercase; white-space: nowrap; }
  .score-table td, .item-table td { padding: 12px 10px; border-bottom: 1px solid color-mix(in oklab, var(--omr-border) 70%, transparent); vertical-align: middle; }
  .review-nav-bar { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px solid var(--omr-border); }
  .back-gallery-btn { border: 0; background: transparent; color: var(--omr-primary); font-size: 12px; font-weight: 650; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; padding: 0; }
  .back-gallery-btn:hover { text-decoration: underline; }
  .review-status-tag { display: inline-flex; align-items: center; font: 700 10px var(--font-mono); text-transform: uppercase; letter-spacing: .05em; padding: 3px 8px; border-radius: 999px; background: var(--omr-primary-soft); color: var(--omr-primary); }
  .review-status-tag.reviewed, .review-status-tag.committed { background: color-mix(in oklab, #2f8f62 14%, var(--omr-surface-solid)); color: #26724f; }
  .review-actions { display: flex; justify-content: space-between; gap: 10px; margin-top: 20px; }
  .hidden-input { display: none; }
  .item-crop { position: relative; flex: 0 0 170px; width: 170px; overflow: hidden; border-radius: 5px; background: var(--omr-stage-deep); color: var(--omr-muted); display: grid; place-items: center; font: 10px var(--font-mono); }
  .answer-chip, .item-mark { display: inline-flex; align-items: center; justify-content: center; min-width: 28px; padding: 5px 8px; border-radius: 999px; font: 700 10px var(--font-mono); }
  .answer-chip { background: color-mix(in oklab, var(--omr-primary) 12%, var(--omr-surface-solid)); color: var(--omr-primary); }
  .answer-chip.empty, .item-mark.neutral { background: var(--omr-stage-deep); color: var(--omr-muted); }
  .item-mark.success { background: color-mix(in oklab, #2f8f62 14%, var(--omr-surface-solid)); color: #26724f; }
  .item-mark.warning { background: color-mix(in oklab, #b7791f 16%, var(--omr-surface-solid)); color: #966515; }
  .item-mark.danger { background: color-mix(in oklab, #b94a48 14%, var(--omr-surface-solid)); color: #a33c3a; }
  .muted-text, .empty-review { color: var(--omr-muted); font-size: 11px; }
  .empty-review { margin: 15px 0 0; line-height: 1.5; }

  .review-actions { display: flex; justify-content: space-between; gap: 10px; margin-top: 20px; }
  .hidden-input { display: none; }
  .spin { animation: omr-spin 1s linear infinite; }
  @keyframes omr-spin { to { transform: rotate(360deg); } }

  @media (max-width: 640px) {
    .review-card { padding: 14px; }
    .review-card-heading h2 { font-size: 15px; }
    .identity-row { align-items: flex-start; }
    .identity-crop { flex-basis: 72px; width: 72px; min-height: 44px; }
    .review-meta { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .top-action span, .scan-count { display: none; }
    .capture-controls { padding-inline: 15px; }
    .review-actions { flex-direction: column-reverse; }
    .review-actions button { width: 100%; }
  }
  .page-badge {
    position: absolute; top: 14px; left: 14px; z-index: 4;
    padding: 5px 10px; border-radius: var(--radius-full, 999px);
    background: color-mix(in srgb, var(--omr-bg, #fff) 82%, transparent);
    border: 1px solid var(--omr-border);
    backdrop-filter: blur(6px);
    font-size: 12px; font-weight: 600; color: var(--omr-primary);
    letter-spacing: .02em;
  }
  .page-step { font-size: 12px; font-weight: 600; color: var(--omr-primary); }
  .camera-footer { display: flex; align-items: center; gap: 12px; }

  .page-chips { display: flex; flex-wrap: wrap; gap: 4px; margin: 6px 0 2px; }
  .page-chip {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 2px 7px; border-radius: var(--radius-full, 999px);
    border: 1px solid var(--omr-border); background: var(--omr-bg, #fff);
    font-size: 10px; font-weight: 600; color: var(--omr-text);
  }
  .page-chip.captured { border-color: color-mix(in srgb, var(--omr-primary) 45%, transparent); }
  .page-chip.missing { border-style: dashed; opacity: .75; }
  .page-chip-dot {
    width: 6px; height: 6px; border-radius: 999px; background: var(--omr-muted, #9aa);
  }
  .page-chip.captured .page-chip-dot { background: var(--omr-primary); }
  .page-chip.missing .page-chip-dot { background: transparent; border: 1px dashed currentColor; }

  .card-actions { display: flex; flex-wrap: wrap; gap: 6px; margin: 8px 0 4px; }
  .card-action {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 4px 9px; border-radius: var(--radius-full, 999px);
    border: 1px solid var(--omr-border); background: transparent;
    font-size: 11px; font-weight: 600; color: var(--omr-text); cursor: pointer;
  }
  .heading-actions { display: flex; align-items: center; gap: 8px; }
  .danger-button {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 7px 12px; border-radius: var(--radius-md, 8px);
    border: 1px solid color-mix(in srgb, #dc2626 55%, transparent);
    background: transparent; color: #dc2626; font-size: 12px; font-weight: 600; cursor: pointer;
  }
  .danger-button:hover { background: color-mix(in srgb, #dc2626 10%, transparent); }
  .danger-button:disabled { opacity: .5; cursor: not-allowed; }
  .card-action.primary { background: var(--omr-primary); border-color: var(--omr-primary); color: #fff; }
  .card-action.primary:hover { filter: brightness(1.06); }

  .review-page-tabs { display: flex; gap: 6px; margin-bottom: 12px; }
  .review-page-tabs button {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 7px 14px; border-radius: var(--radius-md, 8px);
    border: 1px solid var(--omr-border); background: transparent;
    font-size: 13px; font-weight: 600; color: var(--omr-text); cursor: pointer;
  }
  .review-page-tabs button.active { background: var(--omr-primary); border-color: var(--omr-primary); color: #fff; }
  .tab-dot { width: 7px; height: 7px; border-radius: 999px; background: var(--omr-muted, #9aa); }
  .tab-dot.captured { background: currentColor; }
  .tab-dot.missing { background: transparent; border: 1px dashed currentColor; }
  .review-identity-per-page {
    display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px;
    padding: 8px 10px; border: 1px dashed var(--omr-border); border-radius: var(--radius-md, 8px);
    background: var(--omr-bg, #fff);
  }
  .per-page-identity { font-size: 12px; color: var(--omr-text); }
  .per-page-identity b { color: var(--omr-primary); margin-right: 4px; }
</style>
