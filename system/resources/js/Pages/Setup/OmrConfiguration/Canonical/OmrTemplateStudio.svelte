<script>
  import { onMount, onDestroy } from 'svelte';
  import { newIdempotencyKey } from '@/lib/utils';
  import {
    emitNativeMapping,
    emitOverlayBoxes,
  } from '@/lib/omrStudioGeometry';
  import {
    CheckCircle2,
    XCircle,
    Loader2,
    Sparkles,
    ChevronRight,
    Upload,
    Maximize2,
    Lock,
    Unlock,
    Minus,
    Plus,
    Check,
    AlertTriangle,
    RotateCcw,
  } from 'lucide-svelte';

  import StudioCanvas from './StudioCanvas.svelte';
  import StudioOverlayPanel from './StudioOverlayPanel.svelte';
  import StudioBubblePanel from './StudioBubblePanel.svelte';
  import StudioExportVerifyPanel from './StudioExportVerifyPanel.svelte';

  let {
    draft = {},
    marker_profiles = [],
    urls = {},
    onMappingSaved = null,
  } = $props();

  // 4-Step Streamlined Navigation
  let currentStage = $state(1);
  const stageTitles = [
    '1. Source Substrate',
    '2. Layout & Overlays',
    '3. Question Bubbles',
    '4. Verify & Export',
  ];

  // Stage 1: Base Mode ('html' | 'upload')
  let stage1Mode = $state((draft.source_kind === 'image' || draft.source_kind === 'pdf' || draft.source_kind === 'docx') ? 'upload' : 'html');
  let baseType = $state(draft.source_kind ?? 'html');

  // Compact, Single-Page A4-Fitted 150-Q HTML Template (Fits 100% within A4 sheet bounds)
  const DEFAULT_150Q_HTML = `<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    * { box-sizing: border-box; }
    html, body {
      margin: 0; padding: 12px;
      font-family: 'Helvetica Neue', Arial, sans-serif;
      color: #0f172a;
      overflow: hidden;
      width: 100%; height: 100%;
    }
    .header { text-align: center; border-bottom: 1.5px solid #0f172a; padding-bottom: 4px; margin-bottom: 6px; }
    .header h1 { font-size: 13px; margin: 0 0 1px 0; text-transform: uppercase; font-weight: 800; }
    .header p { font-size: 8px; color: #475569; margin: 0; font-family: monospace; }
    .info { display: flex; justify-content: space-between; font-size: 8px; margin-bottom: 6px; border: 1px solid #cbd5e1; padding: 3px 6px; border-radius: 4px; background: #f8fafc; }
    .grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
    .col { background: #fff; }
    .part { font-size: 7.5px; font-weight: bold; background: #0f172a; color: #fff; padding: 2px 4px; border-radius: 3px; margin-bottom: 3px; text-align: center; text-transform: uppercase; }
    .row { display: flex; align-items: center; justify-content: space-between; font-size: 7px; font-family: monospace; margin-bottom: 1.8px; }
    .num { font-weight: bold; width: 14px; color: #334155; }
    .bubs { display: flex; gap: 2px; }
    .bub { width: 9.5px; height: 9.5px; border: 1px solid #334155; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 6px; font-weight: bold; }
  </style>
</head>
<body>
  <div class="header">
    <h1>University General Aptitude Test</h1>
    <p>Standard 150-Item Answer Sheet &bull; ISO A4 (210 &times; 297 mm)</p>
  </div>
  <div class="info">
    <div><strong>Name:</strong> ____________________________</div>
    <div><strong>Examinee No:</strong> __________</div>
    <div><strong>Date:</strong> _______</div>
  </div>
  <div class="grid">
    <div class="col">
      <div class="part">Part 1: Verbal Ability</div>
      ${Array.from({ length: 50 }).map((_, i) => `<div class="row"><span class="num">${String(i + 1).padStart(2, '0')}</span><div class="bubs"><span class="bub">A</span><span class="bub">B</span><span class="bub">C</span><span class="bub">D</span></div></div>`).join('')}
    </div>
    <div class="col">
      <div class="part">Part 2: Quantitative</div>
      ${Array.from({ length: 50 }).map((_, i) => `<div class="row"><span class="num">${i + 51}</span><div class="bubs"><span class="bub">A</span><span class="bub">B</span><span class="bub">C</span><span class="bub">D</span></div></div>`).join('')}
    </div>
    <div class="col">
      <div class="part">Part 3: Logical Reasoning</div>
      ${Array.from({ length: 50 }).map((_, i) => `<div class="row"><span class="num">${i + 101}</span><div class="bubs"><span class="bub">A</span><span class="bub">B</span><span class="bub">C</span><span class="bub">D</span></div></div>`).join('')}
    </div>
  </div>
</body>
</html>`;

  // HTML Code Editor State
  let htmlCode = $state(DEFAULT_150Q_HTML);

  // Direct Upload & Ingest State
  let uploadFileInput = $state(null);
  let uploadRunning = $state(false);
  let conversionStatusText = $state('');
  let conversionError = $state('');

  // Stage 3 Composition Loading
  let isComposing = $state(false);

  // Reset Confirmation Modal State
  let showResetConfirmModal = $state(false);
  let pendingTargetStage = $state(null);

  // Stage 2: Overlays & Insets
  let marginMm = $state(15);
  let markerPattern = $state('square');
  let showHeaderOverlay = $state(false); // In HTML mode, header is already inside HTML template
  let headerTitle = $state(draft.name || 'EXAMINATION ANSWER SHEET');
  let headerSubtitle = $state(draft.code || 'AUTOMATED OMR EVALUATION FORM');
  let headerFontSize = $state(14.0);
  let headerX = $state(25);
  let headerY = $state(20);
  let headerW = $state(160);
  let headerH = $state(15);
  let headerLocked = $state(false);

  let showQrOverlay = $state(true);
  let qrX = $state(162);
  let qrY = $state(18);
  let qrW = $state(26);
  let qrH = $state(26);
  let qrLocked = $state(false);

  let showInstructionsOverlay = $state(false);
  let instructionsContent = $state('Use 2B pencil only. Shade bubbles completely. Erase cleanly to change.');
  let instructionsFontSize = $state(9.0);
  let instX = $state(25);
  let instY = $state(250);
  let instW = $state(90);
  let instH = $state(28);
  let instLocked = $state(false);

  let showSignatureOverlay = $state(false);
  let signatureContent = $state('EXAMINEE SIGNATURE');
  let signatureFontSize = $state(8.5);
  let sigX = $state(125);
  let sigY = $state(250);
  let sigW = $state(60);
  let sigH = $state(28);
  let sigLocked = $state(false);

  let showMetadataOverlay = $state(false);
  let metadataContent = $state('ROOM / SEAT NO.');
  let metadataFontSize = $state(9.0);
  let metaX = $state(25);
  let metaY = $state(45);
  let metaW = $state(50);
  let metaH = $state(15);

  let showRollOverlay = $state(false);
  let rollDigits = $state(6);
  let rollX = $state(25);
  let rollY = $state(45);

  let customTextItems = $state([]);

  // Stage 3: Multi-Section Geometry & Canvas State (3 Columns corresponding to 150 items)
  let canvasLocked = $state(false);
  let sections = $state([
    {
      id: 'Part_1',
      name: 'Part 1 (Q1–50)',
      type: 'mcq',
      posX: 16,
      posY: 56,
      w_mm: 56,
      h_mm: 220,
      questionsCount: 50,
      cols: 1,
      optionsCount: 4,
      startQ: 1,
      customValuesStr: 'A, B, C, D',
      bubblePitchMm: 9.5,
      rowPitchMm: 4.2,
      isLocked: false,
    },
    {
      id: 'Part_2',
      name: 'Part 2 (Q51–100)',
      type: 'mcq',
      posX: 77,
      posY: 56,
      w_mm: 56,
      h_mm: 220,
      questionsCount: 50,
      cols: 1,
      optionsCount: 4,
      startQ: 51,
      customValuesStr: 'A, B, C, D',
      bubblePitchMm: 9.5,
      rowPitchMm: 4.2,
      isLocked: false,
    },
    {
      id: 'Part_3',
      name: 'Part 3 (Q101–150)',
      type: 'mcq',
      posX: 138,
      posY: 56,
      w_mm: 56,
      h_mm: 220,
      questionsCount: 50,
      cols: 1,
      optionsCount: 4,
      startQ: 101,
      customValuesStr: 'A, B, C, D',
      bubblePitchMm: 9.5,
      rowPitchMm: 4.2,
      isLocked: false,
    },
  ]);
  let activeSectionIndex = $state(0);
  let bubbleRadiusMm = $state(4.5);

  // Canvas Pan & Zoom (Optimized Default Scale = 0.88 so entire A4 sheet is 100% visible & centered)
  let scale = $state(0.88);
  let panX = $state(0);
  let panY = $state(0);

  // Persistence & Locking
  let currentLockVersion = $state(draft.lock_version ?? 0);
  let saveStatus = $state('saved'); // 'saved' | 'saving' | 'error'
  let errorMessage = $state('');
  let autosaveTimer = null;

  let totalQuestionsCount = $derived(
    sections.reduce((sum, s) => sum + (Number(s.questionsCount) || 0), 0)
  );

  let jsonSchema = $derived.by(() => {
    const fields = {};
    sections.forEach((s) => {
      fields[s.id.toLowerCase()] = {
        type: s.type === 'tf' ? 'QTYPE_TF' : s.type === 'likert' ? 'QTYPE_LIKERT5' : s.optionsCount === 5 ? 'QTYPE_MCQ5' : 'QTYPE_MCQ4',
        origin_mm: [s.posX, s.posY],
        dimensions_mm: [s.w_mm, s.h_mm],
        columns: s.cols,
        questions_range: [s.startQ, s.startQ + (Number(s.questionsCount) || 1) - 1],
        bubbles_pitch_mm: s.bubblePitchMm,
        rows_pitch_mm: s.rowPitchMm,
        options: s.type === 'tf' ? ['T', 'F'] : s.type === 'likert' ? ['1', '2', '3', '4', '5'] : s.type === 'custom' ? (s.customValuesStr || '').split(',').map((v) => v.trim()) : ['A', 'B', 'C', 'D'].slice(0, s.optionsCount || 4),
      };
    });

    return {
      template_name: draft.name || 'Standard_Evaluation_Sheet',
      template_code: draft.code || 'OMR-STUDIO-2026',
      page_dimensions_mm: [210, 297],
      margins_mm: marginMm,
      registration_markers: {
        pattern: markerPattern,
        top_left: [marginMm, marginMm],
        top_right: [210 - marginMm, marginMm],
        bottom_left: [marginMm, 297 - marginMm],
        bottom_right: [210 - marginMm, 297 - marginMm],
      },
      overlays: {
        header: showHeaderOverlay,
        header_title: headerTitle,
        qr_code: showQrOverlay,
        instructions_box: showInstructionsOverlay,
        signature_box: showSignatureOverlay,
        metadata_box: showMetadataOverlay,
      },
      total_questions: totalQuestionsCount,
      sections: fields,
    };
  });

  onMount(() => {
    const studio = draft.authoring?.studio;
    if (studio) {
      if (typeof studio.current_stage === 'number') currentStage = studio.current_stage;
      if (studio.stage1Mode) stage1Mode = studio.stage1Mode;
      if (studio.baseType) baseType = studio.baseType;

      if (Array.isArray(studio.sections) && studio.sections.length > 0) sections = studio.sections;
      if (typeof studio.marginMm === 'number') marginMm = studio.marginMm;
      if (studio.markerPattern) markerPattern = studio.markerPattern;
      if (typeof studio.bubbleRadiusMm === 'number') bubbleRadiusMm = studio.bubbleRadiusMm;

      if (typeof studio.showHeaderOverlay === 'boolean') showHeaderOverlay = studio.showHeaderOverlay;
      if (studio.headerTitle) headerTitle = studio.headerTitle;
      if (studio.headerSubtitle) headerSubtitle = studio.headerSubtitle;
      if (typeof studio.headerFontSize === 'number') headerFontSize = studio.headerFontSize;
      if (typeof studio.headerX === 'number') headerX = studio.headerX;
      if (typeof studio.headerY === 'number') headerY = studio.headerY;
      if (typeof studio.headerW === 'number') headerW = studio.headerW;
      if (typeof studio.headerH === 'number') headerH = studio.headerH;
      if (typeof studio.headerLocked === 'boolean') headerLocked = studio.headerLocked;

      if (typeof studio.showQrOverlay === 'boolean') showQrOverlay = studio.showQrOverlay;
      if (typeof studio.qrX === 'number') qrX = studio.qrX;
      if (typeof studio.qrY === 'number') qrY = studio.qrY;
      if (typeof studio.qrW === 'number') qrW = studio.qrW;
      if (typeof studio.qrH === 'number') qrH = studio.qrH;
      if (typeof studio.qrLocked === 'boolean') qrLocked = studio.qrLocked;

      if (typeof studio.showInstructionsOverlay === 'boolean') showInstructionsOverlay = studio.showInstructionsOverlay;
      if (studio.instructionsContent) instructionsContent = studio.instructionsContent;
      if (typeof studio.instructionsFontSize === 'number') instructionsFontSize = studio.instructionsFontSize;
      if (typeof studio.instX === 'number') instX = studio.instX;
      if (typeof studio.instY === 'number') instY = studio.instY;
      if (typeof studio.instW === 'number') instW = studio.instW;
      if (typeof studio.instH === 'number') instH = studio.instH;
      if (typeof studio.instLocked === 'boolean') instLocked = studio.instLocked;

      if (typeof studio.showSignatureOverlay === 'boolean') showSignatureOverlay = studio.showSignatureOverlay;
      if (studio.signatureContent) signatureContent = studio.signatureContent;
      if (typeof studio.signatureFontSize === 'number') signatureFontSize = studio.signatureFontSize;
      if (typeof studio.sigX === 'number') sigX = studio.sigX;
      if (typeof studio.sigY === 'number') sigY = studio.sigY;
      if (typeof studio.sigW === 'number') sigW = studio.sigW;
      if (typeof studio.sigH === 'number') sigH = studio.sigH;
      if (typeof studio.sigLocked === 'boolean') sigLocked = studio.sigLocked;

      if (typeof studio.showMetadataOverlay === 'boolean') showMetadataOverlay = studio.showMetadataOverlay;
      if (studio.metadataContent) metadataContent = studio.metadataContent;

      if (Array.isArray(studio.customTextItems)) customTextItems = studio.customTextItems;
      if (studio.htmlCode) htmlCode = studio.htmlCode;
    }

    if (draft.lock_version !== undefined) {
      currentLockVersion = draft.lock_version;
    }
  });

  onDestroy(() => {
    if (autosaveTimer) clearTimeout(autosaveTimer);
  });

  function requestGoToStage(step) {
    if ((step === 1 || step === 2) && currentStage >= 3 && draft.raster_url) {
      pendingTargetStage = step;
      showResetConfirmModal = true;
      return;
    }
    executeGoToStage(step);
  }

  function confirmResetAndNavigate() {
    draft.raster_url = null;
    showResetConfirmModal = false;
    if (pendingTargetStage) {
      executeGoToStage(pendingTargetStage);
      pendingTargetStage = null;
    }
  }

  function executeGoToStage(step) {
    currentStage = step;
    triggerAutosave();
  }

  function triggerAutosave() {
    if (!draft.can_edit) return;
    saveStatus = 'saving';

    if (autosaveTimer) clearTimeout(autosaveTimer);
    autosaveTimer = setTimeout(async () => {
      await performSave();
    }, 600);
  }

  function buildBlockList() {
    const blocks = sections.map((s) => {
      let opts = Number(s.optionsCount) || 4;
      if (s.type === 'tf') opts = 2;
      if (s.type === 'likert') opts = 5;
      if (s.type === 'custom') opts = (s.customValuesStr || '').split(',').length;

      return {
        id: s.id,
        type: s.type,
        x_mm: Number(s.posX) || 25,
        y_mm: Number(s.posY) || 72,
        w_mm: Number(s.w_mm) || 75,
        h_mm: Number(s.h_mm) || 165,
        questions: Number(s.questionsCount) || 10,
        cols: Number(s.cols) || 1,
        options: opts,
        start_q: Number(s.startQ) || 1,
        custom_values: s.type === 'custom' ? (s.customValuesStr || '').split(',').map((v) => v.trim()) : undefined,
        bubble_pitch_mm: Number(s.bubblePitchMm) || 12.0,
        row_pitch_mm: Number(s.rowPitchMm) || 6.5,
      };
    });

    if (showRollOverlay) {
      blocks.push({ id: 'Student_ID', type: 'roll', x_mm: rollX, y_mm: rollY, w_mm: 45, h_mm: 40, digits: rollDigits });
    }

    const overlayBlocks = [];
    if (showQrOverlay) {
      overlayBlocks.push({ id: 'Exam_QR', type: 'qr', tag: 'blank_instance_qr', scope: 'blank_instance', x_mm: qrX, y_mm: qrY, w_mm: qrW, h_mm: qrH });
    }
    if (showHeaderOverlay) {
      overlayBlocks.push({
        id: 'Header_Text',
        type: 'header',
        tag: 'header_title',
        scope: 'template',
        x_mm: headerX,
        y_mm: headerY,
        w_mm: headerW,
        h_mm: headerH,
        font_size: headerFontSize,
        content: headerTitle,
      });
    }
    if (showSignatureOverlay) {
      overlayBlocks.push({
        id: 'Signature_Area',
        type: 'signature',
        tag: 'signature_box',
        scope: 'template',
        x_mm: sigX,
        y_mm: sigY,
        w_mm: sigW,
        h_mm: sigH,
        font_size: signatureFontSize,
        content: signatureContent,
      });
    }
    if (showInstructionsOverlay) {
      overlayBlocks.push({
        id: 'Instructions_Box',
        type: 'instructions',
        tag: 'instructions_box',
        scope: 'template',
        x_mm: instX,
        y_mm: instY,
        w_mm: instW,
        h_mm: instH,
        font_size: instructionsFontSize,
        content: instructionsContent,
      });
    }
    if (showMetadataOverlay) {
      overlayBlocks.push({
        id: 'Metadata_Box',
        type: 'metadata',
        tag: 'metadata_box',
        scope: 'template',
        x_mm: metaX,
        y_mm: metaY,
        w_mm: metaW,
        h_mm: metaH,
        font_size: metadataFontSize,
        content: metadataContent,
      });
    }

    return { blocks, overlayBlocks };
  }

  async function performSave() {
    const saveUrl = urls.studio || `/setup/omr/studio/${draft.id}`;

    try {
      const { blocks, overlayBlocks } = buildBlockList();
      const nativeMapping = emitNativeMapping(210, 297, blocks);
      const overlayBoxes = emitOverlayBoxes(overlayBlocks, 210, 297);

      const studioPayload = {
        current_stage: currentStage,
        sections,
        marginMm,
        markerPattern,
        bubbleRadiusMm,
        showHeaderOverlay,
        headerTitle,
        headerSubtitle,
        headerFontSize,
        headerX, headerY, headerW, headerH, headerLocked,
        showQrOverlay,
        qrX, qrY, qrW, qrH, qrLocked,
        showInstructionsOverlay,
        instructionsContent,
        instructionsFontSize,
        instX, instY, instW, instH, instLocked,
        showSignatureOverlay,
        signatureContent,
        signatureFontSize,
        sigX, sigY, sigW, sigH, sigLocked,
        showMetadataOverlay,
        metadataContent,
        metadataFontSize,
        showRollOverlay,
        rollDigits,
        customTextItems,
        htmlCode,
        stage1Mode,
        baseType,
      };

      const res = await fetch(saveUrl, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': getCsrfToken(),
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({
          mapping: nativeMapping,
          overlay: overlayBoxes,
          studio: studioPayload,
          lock_version: currentLockVersion,
          idempotency_key: newIdempotencyKey(),
        }),
      });

      if (res.ok) {
        const data = await res.json();
        currentLockVersion = data.lock_version ?? currentLockVersion;
        saveStatus = 'saved';
        errorMessage = '';
        if (onMappingSaved) onMappingSaved();
      } else {
        const data = await res.json().catch(() => ({}));
        saveStatus = 'error';
        errorMessage = data.message || 'Atomic studio state save rejected.';
      }
    } catch (err) {
      saveStatus = 'error';
      errorMessage = err?.message || 'Network error while saving studio state.';
    }
  }

  // File Ingestion Handler
  async function handleFileUpload(e) {
    const file = e.target.files?.[0];
    if (!file || !urls.source_ingest) return;

    uploadRunning = true;
    conversionStatusText = `Uploading ${file.name}…`;
    conversionError = '';

    let detectedKind = 'image';
    if (file.name.endsWith('.pdf')) detectedKind = 'pdf';
    else if (file.name.endsWith('.docx')) detectedKind = 'docx';
    else if (file.name.endsWith('.html') || file.name.endsWith('.htm')) detectedKind = 'html';

    const form = new FormData();
    form.append('source_kind', detectedKind);
    form.append('source', file);
    form.append('width_mm', '210');
    form.append('height_mm', '297');
    form.append('idempotency_key', newIdempotencyKey());

    try {
      const res = await fetch(urls.source_ingest, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': getCsrfToken(), 'Accept': 'application/json' },
        body: form,
      });

      if (!res.ok) {
        const data = await res.json().catch(() => ({}));
        throw new Error(data.message || 'File upload rejected.');
      }

      conversionStatusText = `Converting ${file.name}…`;
      await pollConversionRun();
    } catch (err) {
      uploadRunning = false;
      conversionError = err.message || 'File conversion failed.';
    }
  }

  async function pollConversionRun() {
    if (!urls.source_run) {
      uploadRunning = false;
      return;
    }

    const pollInterval = setInterval(async () => {
      try {
        const res = await fetch(urls.source_run, { headers: { 'Accept': 'application/json' } });
        if (res.ok) {
          const body = await res.json();
          const run = body.run;
          if (run?.status === 'completed') {
            clearInterval(pollInterval);
            conversionStatusText = 'Conversion completed. Confirming pages…';
            await autoConfirmPages();
          } else if (run?.status === 'failed') {
            clearInterval(pollInterval);
            uploadRunning = false;
            conversionError = run?.error || 'Document conversion failed.';
          } else if (run?.status === 'processing') {
            conversionStatusText = 'Rasterizing pages at 300 PPI…';
          }
        }
      } catch {
        clearInterval(pollInterval);
        uploadRunning = false;
      }
    }, 1500);
  }

  async function autoConfirmPages() {
    if (!urls.confirm_pages) {
      uploadRunning = false;
      executeGoToStage(2);
      return;
    }

    try {
      await fetch(urls.confirm_pages, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrfToken(), 'Accept': 'application/json' },
        body: JSON.stringify({
          pages: [{ page_no: 1, decision: 'confirmed' }],
        }),
      });

      uploadRunning = false;
      executeGoToStage(2);
    } catch {
      uploadRunning = false;
      executeGoToStage(2);
    }
  }

  function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  }

  function zoomStep(delta) {
    scale = Math.min(Math.max(0.35, scale + delta), 2.5);
  }

  function resetZoom() {
    scale = 0.88; // Default optimal scale fitting 100% of A4 sheet vertically & horizontally
    panX = 0;
    panY = 0;
  }
</script>

<!-- TEMPLATE STUDIO WORKBENCH: Contained height (calc(100vh - 3.5rem)) filling remaining vertical space with NO outer scroll -->
<div class="h-[calc(100vh-3.5rem)] w-full flex bg-slate-100 text-slate-900 overflow-hidden font-sans select-none border-t border-slate-200">
  <!-- COLUMN 1: LEFT WORKBENCH SIDEBAR (Strictly contained, fixed width, independent scroll) -->
  <aside class="w-[380px] h-full bg-white border-r border-slate-200 flex flex-col shrink-0 overflow-hidden shadow-xs z-20">
    <!-- Sidebar Header (Template Name & Code) -->
    <div class="h-12 border-b border-slate-200 px-5 flex items-center justify-between shrink-0 bg-white">
      <div class="flex items-center space-x-2">
        <div class="size-6 rounded bg-emerald-600 flex items-center justify-center text-white font-bold text-xs">
          N
        </div>
        <span class="text-xs font-bold text-slate-900 truncate max-w-[170px]">{draft.name || 'Template Studio'}</span>
        <span class="px-1.5 py-0.5 bg-slate-100 text-[10px] font-mono text-slate-600 rounded border border-slate-200">
          {draft.code || 'OMR-DRAFT'}
        </span>
      </div>
      <div class="text-[11px] font-mono text-slate-500">
        {totalQuestionsCount} Qs
      </div>
    </div>

    <!-- Scrollable Sidebar Tool Content Area (Independent Internal Scroll) -->
    <div class="flex-1 overflow-y-auto min-h-0">
      {#if currentStage === 1}
        <!-- STEP 1: SOURCE SUBSTRATE & INGESTION -->
        <div class="p-5 space-y-5">
          <div>
            <h2 class="text-sm font-bold text-slate-900">Source Substrate</h2>
            <p class="text-[11px] text-slate-500 mt-0.5">Design or upload the base answer sheet layout.</p>
          </div>

          <!-- 2-Mode Switcher Tabs (HTML / File Upload) -->
          <div class="grid grid-cols-2 gap-1.5 p-1 bg-slate-100 rounded-xl border border-slate-200">
            <button
              type="button"
              onclick={() => { stage1Mode = 'html'; baseType = 'html'; triggerAutosave(); }}
              class={`py-1.5 px-2 rounded-lg text-xs font-semibold transition text-center ${
                stage1Mode === 'html' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900'
              }`}
            >
              Paste / Edit HTML
            </button>
            <button
              type="button"
              onclick={() => { stage1Mode = 'upload'; baseType = 'image'; triggerAutosave(); }}
              class={`py-1.5 px-2 rounded-lg text-xs font-semibold transition text-center ${
                stage1Mode === 'upload' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900'
              }`}
            >
              Upload File
            </button>
          </div>

          {#if stage1Mode === 'html'}
            <!-- Option 1: Paste / Edit HTML Code -->
            <div class="space-y-2.5">
              <div class="flex items-center justify-between">
                <label class="text-xs font-bold text-slate-700">HTML Source Code</label>
                <button
                  type="button"
                  onclick={() => {
                    htmlCode = DEFAULT_150Q_HTML;
                    triggerAutosave();
                  }}
                  class="text-[11px] font-semibold text-emerald-600 hover:text-emerald-800 flex items-center gap-1"
                >
                  <Sparkles class="size-3" />
                  <span>Reset 150-Q Template</span>
                </button>
              </div>
              <textarea
                bind:value={htmlCode}
                oninput={triggerAutosave}
                rows="11"
                class="w-full font-mono text-[10.5px] leading-relaxed p-2.5 bg-slate-950 text-emerald-400 rounded-xl border border-slate-800 resize-y focus:outline-hidden focus:ring-2 focus:ring-emerald-500 shadow-inner"
                placeholder="Paste HTML markup here…"
              ></textarea>

              <div class="p-2.5 bg-emerald-50 border border-emerald-200/80 rounded-xl flex items-center gap-2 text-[11px] text-emerald-800">
                <Check class="size-3.5 text-emerald-600 shrink-0" />
                <span>Single-Page A4 Proportion: 100% within paper bounds</span>
              </div>
            </div>
          {:else}
            <!-- Option 2: Upload Document File -->
            <div class="space-y-3">
              <div class="border-2 border-dashed border-slate-300 rounded-2xl p-6 text-center hover:border-slate-400 bg-slate-50 transition">
                <Upload class="size-7 text-slate-400 mx-auto mb-2" />
                <p class="text-xs font-bold text-slate-700">Drop PDF, Word (.docx), or Image</p>
                <p class="text-[10px] text-slate-400 mt-0.5">Automatic 300 PPI print rasterization</p>
                <input
                  type="file"
                  accept=".pdf,.docx,.doc,.png,.jpg,.jpeg,.html"
                  bind:this={uploadFileInput}
                  onchange={handleFileUpload}
                  class="hidden"
                />
                <button
                  type="button"
                  onclick={() => uploadFileInput?.click()}
                  class="mt-3 px-3.5 py-1.5 bg-slate-900 text-white text-xs font-semibold rounded-xl hover:bg-slate-800 transition shadow-xs"
                >
                  Select File
                </button>
              </div>

              {#if uploadRunning}
                <div class="p-3 bg-blue-50 border border-blue-200 rounded-xl flex items-center gap-2 text-xs text-blue-800">
                  <Loader2 class="size-4 animate-spin text-blue-600 shrink-0" />
                  <span>{conversionStatusText || 'Converting file…'}</span>
                </div>
              {/if}

              {#if conversionError}
                <div class="p-3 bg-red-50 border border-red-200 rounded-xl text-xs text-red-800">
                  {conversionError}
                </div>
              {/if}
            </div>
          {/if}
        </div>
      {:else if currentStage === 2}
        <!-- STEP 2: LAYOUT, MARGINS & OVERLAYS -->
        <StudioOverlayPanel
          bind:marginMm
          bind:markerPattern
          bind:showHeaderOverlay
          bind:headerTitle
          bind:headerSubtitle
          bind:headerFontSize
          bind:headerLocked
          bind:showQrOverlay
          bind:qrLocked
          bind:showInstructionsOverlay
          bind:instructionsContent
          bind:instructionsFontSize
          bind:instLocked
          bind:showSignatureOverlay
          bind:signatureContent
          bind:signatureFontSize
          bind:sigLocked
          bind:customTextItems
          onChanged={triggerAutosave}
          onBack={() => requestGoToStage(1)}
          onNext={() => requestGoToStage(3)}
        />
      {:else if currentStage === 3}
        <!-- STEP 3: QUESTION BUBBLES & PITCH GEOMETRY -->
        <StudioBubblePanel
          bind:sections
          bind:activeSectionIndex
          onChanged={triggerAutosave}
          onBack={() => requestGoToStage(2)}
          onNext={() => requestGoToStage(4)}
        />
      {:else if currentStage === 4}
        <!-- STEP 4: VERIFICATION & PRODUCTION DELIVERABLES -->
        <StudioExportVerifyPanel
          {draft}
          {jsonSchema}
          {urls}
          onBack={() => requestGoToStage(3)}
        />
      {/if}
    </div>

    <!-- Pinned Bottom Navigation Footer in Sidebar (Step 1 only; Steps 2-4 have their own footers) -->
    {#if currentStage === 1}
      <div class="shrink-0 p-4 border-t border-slate-200 bg-white">
        <button
          type="button"
          onclick={() => requestGoToStage(2)}
          class="w-full py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-semibold rounded-xl text-xs transition flex items-center justify-center gap-2 shadow-xs"
        >
          <span>Continue to Layout & Markers</span>
          <ChevronRight class="size-4" />
        </button>
      </div>
    {/if}
  </aside>

  <!-- COLUMN 2: RIGHT CANVAS WORKBENCH (Stationary Top Stepper & Controls Bar + Centered Canvas Area) -->
  <main class="flex-1 h-full flex flex-col min-w-0 bg-slate-100 overflow-hidden relative">
    <!-- Stationary Top Bar (Above Canvas: 4-Step Stepper + Canvas Viewport Controls + Save) -->
    <div class="h-12 bg-white border-b border-slate-200 px-4 flex items-center justify-between shrink-0 shadow-2xs z-20">
      <!-- 4-Step Stepper Navigation (Vertically centered with clean typography) -->
      <div class="flex items-center space-x-1.5 bg-slate-100 p-1 rounded-xl border border-slate-200">
        {#each stageTitles as title, idx}
          {@const stageNum = idx + 1}
          {@const isActive = currentStage === stageNum}
          {@const isPast = currentStage > stageNum}
          <button
            type="button"
            onclick={() => requestGoToStage(stageNum)}
            class={`px-3 py-1 rounded-lg text-xs font-bold transition flex items-center gap-1.5 leading-none ${
              isActive
                ? 'bg-slate-900 text-white shadow-xs'
                : isPast
                  ? 'text-slate-700 hover:text-slate-900 hover:bg-slate-200/70'
                  : 'text-slate-400 hover:text-slate-600'
            }`}
          >
            <span class={`size-3.5 rounded-full flex items-center justify-center text-[9px] font-extrabold leading-none shrink-0 ${
              isActive ? 'bg-white text-slate-900' : isPast ? 'bg-slate-300 text-slate-800' : 'bg-slate-200 text-slate-400'
            }`}>
              {stageNum}
            </span>
            <span class="truncate">{title.split('.')[1]?.trim() || title}</span>
          </button>
        {/each}
      </div>

      <!-- Right Actions: Recompose Action (Step 3) + Canvas Viewport Controls + Save -->
      <div class="flex items-center space-x-2">
        {#if currentStage === 3 && draft.raster_url}
          <button
            type="button"
            onclick={() => requestGoToStage(2)}
            class="px-2.5 h-7 rounded-lg text-xs font-semibold text-emerald-800 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 flex items-center gap-1 transition shadow-xs"
            title="Return to Step 2 to edit overlays or re-render page raster"
          >
            <RotateCcw class="size-3 text-emerald-600" />
            <span>Re-edit Design</span>
          </button>
          <div class="h-4 w-px bg-slate-200 mx-0.5"></div>
        {/if}

        <!-- Canvas Lock Toggle Button -->
        <button
          type="button"
          onclick={() => { canvasLocked = !canvasLocked; }}
          class={`px-2.5 h-7 rounded-lg text-xs font-bold transition flex items-center gap-1.5 shadow-xs ${
            canvasLocked
              ? 'bg-amber-100 text-amber-900 border border-amber-300'
              : 'bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200'
          }`}
          title={canvasLocked ? 'Canvas viewport is locked (Scroll wheel & panning disabled)' : 'Canvas is interactive (Scroll wheel zooms, drag background to pan)'}
        >
          {#if canvasLocked}
            <Lock class="size-3 text-amber-700" />
            <span>Canvas Locked</span>
          {:else}
            <Unlock class="size-3 text-slate-600" />
            <span>Canvas Active</span>
          {/if}
        </button>

        <div class="h-4 w-px bg-slate-200 mx-0.5"></div>

        <!-- Zoom Controls -->
        <div class="flex items-center space-x-1 bg-slate-100 border border-slate-200 rounded-lg p-0.5">
          <button
            type="button"
            onclick={() => zoomStep(-0.15)}
            title="Zoom Out"
            class="w-6 h-6 rounded flex items-center justify-center text-slate-600 hover:text-slate-900 hover:bg-white transition"
          >
            <Minus class="size-3" />
          </button>
          <span class="text-xs font-mono font-bold px-1 text-slate-800 min-w-[38px] text-center">
            {Math.round(scale * 100)}%
          </span>
          <button
            type="button"
            onclick={() => zoomStep(0.15)}
            title="Zoom In"
            class="w-6 h-6 rounded flex items-center justify-center text-slate-600 hover:text-slate-900 hover:bg-white transition"
          >
            <Plus class="size-3" />
          </button>
          <button
            type="button"
            onclick={resetZoom}
            title="Reset Zoom to Fit A4 Sheet (88%)"
            class="px-1.5 h-6 rounded text-xs font-bold text-slate-700 hover:text-slate-900 hover:bg-white transition flex items-center gap-0.5"
          >
            <Maximize2 class="size-2.5" />
            <span>Fit</span>
          </button>
        </div>

        <div class="h-4 w-px bg-slate-200 mx-0.5"></div>

        <!-- Save Indicator & Save Button -->
        <div class="flex items-center space-x-1.5 text-xs">
          {#if saveStatus === 'saving'}
            <Loader2 class="size-3 text-amber-500 animate-spin" />
            <span class="text-amber-600 font-mono text-[10px]">Saving…</span>
          {:else if saveStatus === 'saved'}
            <CheckCircle2 class="size-3 text-emerald-600" />
            <span class="text-emerald-600 font-mono text-[10px]">Saved</span>
          {:else if saveStatus === 'error'}
            <XCircle class="size-3 text-rose-600" />
            <span class="text-rose-600 font-mono text-[10px] max-w-[100px] truncate" title={errorMessage}>
              {errorMessage || 'Error'}
            </span>
          {/if}
        </div>

        <button
          type="button"
          onclick={performSave}
          class="px-3 h-7 bg-slate-900 hover:bg-slate-800 text-white text-xs font-semibold rounded-lg transition shadow-xs"
        >
          Save
        </button>
      </div>
    </div>

    <!-- Scalable Canvas Area -->
    <div class="flex-1 w-full h-full relative overflow-hidden flex flex-col bg-slate-100">
      <StudioCanvas
        bind:scale
        bind:panX
        bind:panY
        bind:marginMm
        bind:markerPattern
        bind:canvasLocked
        rasterUrl={draft.raster_url}
        htmlContent={htmlCode}
        isHtmlMode={stage1Mode === 'html'}
        {isComposing}
        {showHeaderOverlay}
        {headerTitle}
        {headerSubtitle}
        {headerFontSize}
        bind:headerX
        bind:headerY
        bind:headerW
        bind:headerH
        bind:headerLocked
        {showQrOverlay}
        bind:qrX
        bind:qrY
        bind:qrW
        bind:qrH
        bind:qrLocked
        {showInstructionsOverlay}
        {instructionsContent}
        bind:instX
        bind:instY
        bind:instW
        bind:instH
        bind:instLocked
        {showSignatureOverlay}
        {signatureContent}
        bind:sigX
        bind:sigY
        bind:sigW
        bind:sigH
        bind:sigLocked
        bind:customTextItems
        bind:sections
        bind:activeSectionIndex
        {currentStage}
        onGeometryChanged={triggerAutosave}
      />
    </div>
  </main>

  <!-- Re-edit & Raster Reset Confirmation Modal -->
  {#if showResetConfirmModal}
    <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 z-50">
      <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200 space-y-4">
        <div class="flex items-center space-x-3">
          <div class="size-10 rounded-xl bg-amber-100 flex items-center justify-center text-amber-700 shrink-0">
            <AlertTriangle class="size-5" />
          </div>
          <div>
            <h3 class="text-sm font-bold text-slate-900">Re-edit Design & Reset Raster?</h3>
            <p class="text-xs text-slate-500 mt-0.5">Returning to Step {pendingTargetStage} resets the 300-PPI print raster.</p>
          </div>
        </div>

        <p class="text-xs text-slate-600 leading-relaxed bg-slate-50 p-3 rounded-xl border border-slate-100">
          Step 3 relies on a settled, composed print raster for optical bubble calibration. Going back to modify HTML source or overlays will invalidate the current raster image so you can re-render a fresh document.
        </p>

        <div class="flex items-center justify-end space-x-2 pt-2">
          <button
            type="button"
            onclick={() => { showResetConfirmModal = false; pendingTargetStage = null; }}
            class="px-3.5 py-2 rounded-xl text-xs font-semibold text-slate-700 hover:bg-slate-100 transition"
          >
            Cancel & Stay on Step {currentStage}
          </button>
          <button
            type="button"
            onclick={confirmResetAndNavigate}
            class="px-4 py-2 rounded-xl text-xs font-semibold bg-amber-600 hover:bg-amber-700 text-white shadow-xs transition"
          >
            Yes, Reset Raster & Re-edit
          </button>
        </div>
      </div>
    </div>
  {/if}
</div>
