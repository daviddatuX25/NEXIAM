<script>
  import { onMount, onDestroy } from 'svelte';
  import { newIdempotencyKey } from '@/lib/utils';
  import {
    emitNativeMapping,
    emitOverlayBoxes,
  } from '@/lib/omrStudioGeometry';
  import {
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    XCircle,
    Loader2,
    FileText,
    Layers,
    ShieldCheck,
    Code,
    Upload,
    Sparkles,
    RefreshCw,
    Play,
    Sliders,
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

  // 5-Stage Stepper Navigation
  let currentStage = $state(1);
  const stageTitles = [
    'Stage 1: Base Template & Ingestion',
    'Stage 2: Configure Layout, Markers & Overlays',
    'Stage 3: Optical Registration Alignment',
    'Stage 4: Multi-Section Bubble Coordinates & Pitch',
    'Stage 5: Dual-Input Verification & Production Export',
  ];

  // Stage 1: Base Mode ('blank' | 'html' | 'upload')
  let stage1Mode = $state(draft.source_kind === 'html' ? 'html' : (draft.source_kind === 'image' || draft.source_kind === 'pdf' || draft.source_kind === 'docx') ? 'upload' : 'blank');
  let baseType = $state(draft.source_kind ?? 'blank');

  // HTML Code Editor State
  let htmlCode = $state(`<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    body { font-family: 'Helvetica Neue', Arial, sans-serif; margin: 0; padding: 20px; color: #0f172a; }
    .header { text-align: center; border-bottom: 2px solid #0f172a; padding-bottom: 10px; margin-bottom: 12px; }
    .header h1 { font-size: 18px; margin: 0 0 2px 0; text-transform: uppercase; }
    .header p { font-size: 10px; color: #475569; margin: 0; font-family: monospace; }
    .info { display: flex; justify-content: space-between; font-size: 10px; margin-bottom: 14px; border: 1px solid #cbd5e1; padding: 6px 10px; border-radius: 6px; background: #f8fafc; }
    .grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; }
    .part { font-size: 8.5px; font-weight: bold; background: #0f172a; color: #fff; padding: 3px 6px; border-radius: 4px; margin-bottom: 6px; text-align: center; text-transform: uppercase; }
    .row { display: flex; align-items: center; justify-content: space-between; font-size: 8.5px; font-family: monospace; margin-bottom: 3.5px; }
    .num { font-weight: bold; width: 18px; color: #334155; }
    .bubs { display: flex; gap: 3.5px; }
    .bub { width: 12px; height: 12px; border: 1.2px solid #334155; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 7px; font-weight: bold; }
  </style>
</head>
<body>
  <div class="header">
    <h1>University General Aptitude Test</h1>
    <p>Standard 150-Item Answer Sheet &bull; ISO A4</p>
  </div>
  <div class="info">
    <div><strong>Name:</strong> ____________________________</div>
    <div><strong>Examinee No:</strong> __________</div>
    <div><strong>Date:</strong> _______</div>
  </div>
  <div class="grid">
    <div>
      <div class="part">Part 1: Verbal Ability</div>
      <div class="row"><span class="num">01</span><div class="bubs"><span class="bub">A</span><span class="bub">B</span><span class="bub">C</span><span class="bub">D</span></div></div>
      <div class="row"><span class="num">02</span><div class="bubs"><span class="bub">A</span><span class="bub">B</span><span class="bub">C</span><span class="bub">D</span></div></div>
      <div class="row"><span class="num">03</span><div class="bubs"><span class="bub">A</span><span class="bub">B</span><span class="bub">C</span><span class="bub">D</span></div></div>
      <div class="row"><span class="num">04</span><div class="bubs"><span class="bub">A</span><span class="bub">B</span><span class="bub">C</span><span class="bub">D</span></div></div>
      <div class="row"><span class="num">05</span><div class="bubs"><span class="bub">A</span><span class="bub">B</span><span class="bub">C</span><span class="bub">D</span></div></div>
    </div>
    <div>
      <div class="part">Part 2: Quantitative</div>
      <div class="row"><span class="num">26</span><div class="bubs"><span class="bub">A</span><span class="bub">B</span><span class="bub">C</span><span class="bub">D</span></div></div>
      <div class="row"><span class="num">27</span><div class="bubs"><span class="bub">A</span><span class="bub">B</span><span class="bub">C</span><span class="bub">D</span></div></div>
      <div class="row"><span class="num">28</span><div class="bubs"><span class="bub">A</span><span class="bub">B</span><span class="bub">C</span><span class="bub">D</span></div></div>
      <div class="row"><span class="num">29</span><div class="bubs"><span class="bub">A</span><span class="bub">B</span><span class="bub">C</span><span class="bub">D</span></div></div>
      <div class="row"><span class="num">30</span><div class="bubs"><span class="bub">A</span><span class="bub">B</span><span class="bub">C</span><span class="bub">D</span></div></div>
    </div>
    <div>
      <div class="part">Part 3: Logical Reasoning</div>
      <div class="row"><span class="num">51</span><div class="bubs"><span class="bub">A</span><span class="bub">B</span><span class="bub">C</span><span class="bub">D</span></div></div>
      <div class="row"><span class="num">52</span><div class="bubs"><span class="bub">A</span><span class="bub">B</span><span class="bub">C</span><span class="bub">D</span></div></div>
      <div class="row"><span class="num">53</span><div class="bubs"><span class="bub">A</span><span class="bub">B</span><span class="bub">C</span><span class="bub">D</span></div></div>
      <div class="row"><span class="num">54</span><div class="bubs"><span class="bub">A</span><span class="bub">B</span><span class="bub">C</span><span class="bub">D</span></div></div>
      <div class="row"><span class="num">55</span><div class="bubs"><span class="bub">A</span><span class="bub">B</span><span class="bub">C</span><span class="bub">D</span></div></div>
    </div>
  </div>
</body>
</html>`);

  // Direct Upload & Ingest State
  let uploadFileInput = $state(null);
  let uploadRunning = $state(false);
  let uploadProgress = $state(0);
  let conversionStatusText = $state('');
  let conversionError = $state('');

  // Stage 3 Composition Loading
  let isComposing = $state(false);
  let composeStatusText = $state('');

  // Stage 2: Overlays & Insets
  let marginMm = $state(15);
  let markerPattern = $state('square');
  let showHeaderOverlay = $state(true);
  let headerTitle = $state(draft.name || 'EXAMINATION ANSWER SHEET');
  let headerSubtitle = $state(draft.code || 'AUTOMATED OMR EVALUATION FORM');
  let headerFontSize = $state(14.0);
  let headerX = $state(25);
  let headerY = $state(20);
  let headerW = $state(160);
  let headerH = $state(15);

  let showQrOverlay = $state(true);
  let qrX = $state(150);
  let qrY = $state(32);
  let qrW = $state(32);
  let qrH = $state(32);

  let showInstructionsOverlay = $state(true);
  let instructionsContent = $state('Use 2B pencil only. Shade bubbles completely. Erase cleanly to change.');
  let instructionsFontSize = $state(9.0);
  let instX = $state(25);
  let instY = $state(250);
  let instW = $state(90);
  let instH = $state(28);

  let showSignatureOverlay = $state(true);
  let signatureContent = $state('EXAMINEE SIGNATURE');
  let signatureFontSize = $state(8.5);
  let sigX = $state(125);
  let sigY = $state(250);
  let sigW = $state(60);
  let sigH = $state(28);

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

  // Stage 4: Multi-Section Geometry & Canvas State
  let canvasDragLocked = $state(true);
  let sections = $state([
    {
      id: 'Section_A',
      name: 'Section A (General Knowledge)',
      type: 'mcq',
      posX: 25,
      posY: 72,
      w_mm: 75,
      h_mm: 165,
      questionsCount: 25,
      cols: 1,
      optionsCount: 4,
      startQ: 1,
      customValuesStr: 'A, B, C, D',
      bubblePitchMm: 12.0,
      rowPitchMm: 6.5,
    },
    {
      id: 'Section_B',
      name: 'Section B (Mathematics)',
      type: 'mcq',
      posX: 110,
      posY: 72,
      w_mm: 75,
      h_mm: 165,
      questionsCount: 25,
      cols: 1,
      optionsCount: 4,
      startQ: 26,
      customValuesStr: 'A, B, C, D',
      bubblePitchMm: 12.0,
      rowPitchMm: 6.5,
    },
  ]);
  let activeSectionIndex = $state(0);
  let bubbleRadiusMm = $state(4.5);

  // Canvas Pan & Zoom
  let scale = $state(1.0);
  let panX = $state(0);
  let panY = $state(0);

  // Persistence & Locking
  let currentLockVersion = $state(draft.lock_version ?? 0);
  let saveStatus = $state('saved'); // 'saved' | 'saving' | 'error'
  let errorMessage = $state('');
  let autosaveTimer = null;

  let totalQuestionsCount = $derived(
    sections.reduce((sum, s) => sum + (s.questionsCount || 0), 0)
  );

  let jsonSchema = $derived.by(() => {
    const fields = {};
    sections.forEach((s) => {
      fields[s.id.toLowerCase()] = {
        type: s.type === 'tf' ? 'QTYPE_TF' : s.type === 'likert' ? 'QTYPE_LIKERT5' : s.optionsCount === 5 ? 'QTYPE_MCQ5' : 'QTYPE_MCQ4',
        origin_mm: [s.posX, s.posY],
        dimensions_mm: [s.w_mm, s.h_mm],
        columns: s.cols,
        questions_range: [s.startQ, s.startQ + s.questionsCount - 1],
        bubbles_pitch_mm: s.bubblePitchMm,
        rows_pitch_mm: s.rowPitchMm,
        options: s.type === 'tf' ? ['T', 'F'] : s.type === 'likert' ? ['1', '2', '3', '4', '5'] : s.type === 'custom' ? (s.customValuesStr || '').split(',').map((v) => v.trim()) : ['A', 'B', 'C', 'D'].slice(0, s.optionsCount),
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
      if (Array.isArray(studio.sections) && studio.sections.length > 0) sections = studio.sections;
      if (typeof studio.marginMm === 'number') marginMm = studio.marginMm;
      if (studio.markerPattern) markerPattern = studio.markerPattern;
      if (typeof studio.bubbleRadiusMm === 'number') bubbleRadiusMm = studio.bubbleRadiusMm;

      if (typeof studio.showHeaderOverlay === 'boolean') showHeaderOverlay = studio.showHeaderOverlay;
      if (studio.headerTitle) headerTitle = studio.headerTitle;
      if (studio.headerSubtitle) headerSubtitle = studio.headerSubtitle;
      if (studio.headerFontSize) headerFontSize = studio.headerFontSize;

      if (typeof studio.showQrOverlay === 'boolean') showQrOverlay = studio.showQrOverlay;
      if (typeof studio.showInstructionsOverlay === 'boolean') showInstructionsOverlay = studio.showInstructionsOverlay;
      if (studio.instructionsContent) instructionsContent = studio.instructionsContent;
      if (studio.instructionsFontSize) instructionsFontSize = studio.instructionsFontSize;

      if (typeof studio.showSignatureOverlay === 'boolean') showSignatureOverlay = studio.showSignatureOverlay;
      if (studio.signatureContent) signatureContent = studio.signatureContent;
      if (studio.signatureFontSize) signatureFontSize = studio.signatureFontSize;

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

  function goToStage(step) {
    currentStage = step;
    if (step === 3 && !draft.raster_url && !isComposing) {
      composeCanonicalPage();
    }
    triggerAutosave();
  }

  function nextStage() {
    if (currentStage < 5) goToStage(currentStage + 1);
  }

  function prevStage() {
    if (currentStage > 1) goToStage(currentStage - 1);
  }

  function triggerAutosave() {
    if (!draft.can_edit) return;
    saveStatus = 'saving';

    if (autosaveTimer) clearTimeout(autosaveTimer);
    autosaveTimer = setTimeout(async () => {
      await performSave();
    }, 800);
  }

  function buildBlockList() {
    const blocks = sections.map((s) => {
      let opts = s.optionsCount;
      if (s.type === 'tf') opts = 2;
      if (s.type === 'likert') opts = 5;
      if (s.type === 'custom') opts = (s.customValuesStr || '').split(',').length;

      return {
        id: s.id,
        type: s.type,
        x_mm: s.posX,
        y_mm: s.posY,
        w_mm: s.w_mm,
        h_mm: s.h_mm,
        questions: s.questionsCount,
        cols: s.cols,
        options: opts,
        start_q: s.startQ,
        custom_values: s.type === 'custom' ? (s.customValuesStr || '').split(',').map((v) => v.trim()) : undefined,
        bubble_pitch_mm: s.bubblePitchMm,
        row_pitch_mm: s.rowPitchMm,
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
        type: 'metadata_box',
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

    customTextItems.forEach((t) => {
      overlayBlocks.push({
        id: t.id,
        type: 'custom_text',
        tag: 'custom_text',
        scope: 'template',
        x_mm: t.posX,
        y_mm: t.posY,
        w_mm: t.w_mm,
        h_mm: t.h_mm,
        font_size: t.fontSize ?? 10.0,
        content: t.content,
        name: t.name,
      });
    });

    return { blocks, overlayBlocks };
  }

  async function performSave() {
    const saveUrl = urls.studio || `/setup/omr/templates/canonical/drafts/${draft.id}/studio`;

    try {
      const { blocks, overlayBlocks } = buildBlockList();
      const nativeMapping = emitNativeMapping(210, 297, blocks);
      const overlayBoxes = emitOverlayBoxes(overlayBlocks, 210, 297);

      const studioPayload = {
        sections,
        marginMm,
        markerPattern,
        bubbleRadiusMm,
        showHeaderOverlay,
        headerTitle,
        headerSubtitle,
        headerFontSize,
        showQrOverlay,
        qrX, qrY, qrW, qrH,
        showInstructionsOverlay,
        instructionsContent,
        instructionsFontSize,
        showSignatureOverlay,
        signatureContent,
        signatureFontSize,
        showMetadataOverlay,
        metadataContent,
        metadataFontSize,
        showRollOverlay,
        rollDigits,
        customTextItems,
        htmlCode,
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

  // HTML Ingest & Compilation Action
  async function compileHtmlSource() {
    if (!urls.source_ingest || !htmlCode) return;
    uploadRunning = true;
    conversionStatusText = 'Uploading HTML template…';
    conversionError = '';

    const htmlBlob = new Blob([htmlCode], { type: 'text/html' });
    const file = new File([htmlBlob], 'answersheet-template.html', { type: 'text/html' });

    const form = new FormData();
    form.append('source_kind', 'html');
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
        throw new Error(data.message || 'HTML source upload rejected.');
      }

      conversionStatusText = 'Converting HTML into 300-PPI print raster…';
      await pollConversionRun();
    } catch (err) {
      uploadRunning = false;
      conversionError = err.message || 'HTML conversion failed.';
    }
  }

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
      goToStage(2);
      return;
    }

    try {
      const res = await fetch(urls.confirm_pages, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrfToken(), 'Accept': 'application/json' },
        body: JSON.stringify({
          pages: [{ page_no: 1, decision: 'confirmed' }],
        }),
      });

      uploadRunning = false;
      goToStage(2);
    } catch {
      uploadRunning = false;
      goToStage(2);
    }
  }

  // Stage 3 Composition
  async function composeCanonicalPage() {
    if (!urls.compose) return;
    isComposing = true;
    composeStatusText = 'Composing 300-PPI print raster & marker quad…';

    try {
      const res = await fetch(urls.compose, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrfToken(), 'Accept': 'application/json' },
        body: JSON.stringify({
          paper_width_mm: 210,
          paper_height_mm: 297,
          parametric: stage1Mode === 'blank' || baseType === 'blank' || baseType === 'preset',
          content_margin_mm: { top: Number(marginMm) || 15, right: Number(marginMm) || 15, bottom: Number(marginMm) || 15, left: Number(marginMm) || 15 },
          printer_safe_margin_mm: { top: 5, right: 5, bottom: 5, left: 5 },
          marker_inset_mm: { top: 12, right: 12, bottom: 14, left: 12 },
          alignment: 'center',
          source_dpi: 300,
          idempotency_key: newIdempotencyKey(),
        }),
      });

      if (res.ok) {
        const data = await res.json();
        draft.stage = data.stage ?? 'page_prepared';
        if (data.draft_id) {
          draft.raster_url = `/setup/omr/templates/canonical/drafts/${draft.id}/assets/${data.composition?.raster_assets?.[0]?.asset_id || 1}`;
        }
      }
    } catch {
      // Advisory
    } finally {
      isComposing = false;
    }
  }

  function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
  }
</script>

<!-- Root Studio Container -->
<div class="flex flex-col h-full bg-slate-50 border border-slate-200/80 rounded-2xl overflow-hidden shadow-xs">
  <!-- Stepper Header -->
  <header class="bg-white border-b border-slate-200 px-6 py-3 flex items-center justify-between z-20 shrink-0">
    <div class="flex items-center space-x-3">
      <div class="w-8 h-8 rounded-xl bg-slate-900 flex items-center justify-center text-white font-bold text-sm shadow-xs">
        {currentStage}
      </div>
      <div>
        <h1 class="text-sm font-bold text-slate-900 leading-tight">
          {stageTitles[currentStage - 1]}
        </h1>
        <p class="text-[11px] text-slate-500 font-mono">
          {draft.code || 'OMR-STUDIO'} &bull; Lock v{currentLockVersion} &bull; {totalQuestionsCount} Questions Total
        </p>
      </div>
    </div>

    <!-- Stepper Navigation Buttons -->
    <div class="hidden md:flex items-center space-x-1.5 bg-slate-100/80 p-1 rounded-xl border border-slate-200/60">
      {#each [1, 2, 3, 4, 5] as step}
        <button
          type="button"
          onclick={() => goToStage(step)}
          class={`px-3 py-1 text-xs font-semibold rounded-lg transition ${
            currentStage === step
              ? 'bg-white text-slate-900 shadow-xs'
              : 'text-slate-500 hover:text-slate-800'
          }`}
        >
          {step === 1 ? '1. Base' : step === 2 ? '2. Overlays' : step === 3 ? '3. Compose' : step === 4 ? '4. Bubbles' : '5. Verify'}
        </button>
      {/each}
    </div>

    <!-- Status & Navigation Actions -->
    <div class="flex items-center space-x-2">
      {#if saveStatus === 'saving'}
        <span class="text-[11px] text-slate-500 flex items-center gap-1">
          <Loader2 class="size-3 animate-spin text-slate-400" />
          <span>Saving…</span>
        </span>
      {:else if saveStatus === 'saved'}
        <span class="text-[11px] text-emerald-600 flex items-center gap-1 font-medium">
          <CheckCircle2 class="size-3" />
          <span>Saved</span>
        </span>
      {:else if saveStatus === 'error'}
        <span class="text-[11px] text-red-600 flex items-center gap-1 font-medium" title={errorMessage}>
          <XCircle class="size-3" />
          <span>Save Error</span>
        </span>
      {/if}

      <button
        type="button"
        onclick={performSave}
        class="px-2.5 py-1 text-xs font-semibold bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg transition"
      >
        Save
      </button>

      <button
        type="button"
        onclick={prevStage}
        disabled={currentStage === 1}
        class="p-1.5 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-100 disabled:opacity-30 disabled:cursor-not-allowed transition"
        title="Previous Stage"
      >
        <ChevronLeft class="size-4" />
      </button>
      <button
        type="button"
        onclick={nextStage}
        disabled={currentStage === 5}
        class="p-1.5 rounded-lg bg-slate-900 text-white hover:bg-slate-800 disabled:opacity-30 disabled:cursor-not-allowed transition shadow-xs"
        title="Next Stage"
      >
        <ChevronRight class="size-4" />
      </button>
    </div>
  </header>

  <!-- Main Split Workbench -->
  <div class="flex flex-1 overflow-hidden relative">
    <!-- LEFT CONFIGURATION SIDEBAR -->
    <aside class="w-[380px] border-r border-slate-200 bg-white flex flex-col shrink-0 overflow-y-auto z-10">
      {#if currentStage === 1}
        <div class="p-6 space-y-6">
          <div>
            <h2 class="text-base font-bold text-slate-900">Template Base & Ingestion</h2>
            <p class="text-xs text-slate-500 mt-1">Start from clean HTML, upload an existing document, or use a blank canvas.</p>
          </div>

          <!-- 3 Mode Switcher Tabs -->
          <div class="grid grid-cols-3 gap-1.5 p-1 bg-slate-100 rounded-xl border border-slate-200">
            <button
              type="button"
              onclick={() => { stage1Mode = 'blank'; baseType = 'blank'; triggerAutosave(); }}
              class={`py-1.5 px-2 rounded-lg text-xs font-semibold transition text-center ${
                stage1Mode === 'blank'
                  ? 'bg-white text-slate-900 shadow-xs'
                  : 'text-slate-600 hover:text-slate-900'
              }`}
            >
              Blank Canvas
            </button>
            <button
              type="button"
              onclick={() => { stage1Mode = 'html'; baseType = 'html'; triggerAutosave(); }}
              class={`py-1.5 px-2 rounded-lg text-xs font-semibold transition text-center ${
                stage1Mode === 'html'
                  ? 'bg-white text-slate-900 shadow-xs'
                  : 'text-slate-600 hover:text-slate-900'
              }`}
            >
              Paste HTML
            </button>
            <button
              type="button"
              onclick={() => { stage1Mode = 'upload'; baseType = 'image'; triggerAutosave(); }}
              class={`py-1.5 px-2 rounded-lg text-xs font-semibold transition text-center ${
                stage1Mode === 'upload'
                  ? 'bg-white text-slate-900 shadow-xs'
                  : 'text-slate-600 hover:text-slate-900'
              }`}
            >
              Upload File
            </button>
          </div>

          <!-- Option 1: Blank Parametric Canvas -->
          {#if stage1Mode === 'blank'}
            <div class="p-4 bg-slate-50 rounded-xl border border-slate-200/80 space-y-3">
              <div class="flex items-center gap-2">
                <Sliders class="size-4 text-slate-700" />
                <span class="text-xs font-bold text-slate-900">Parametric Vector Layout</span>
              </div>
              <p class="text-[11px] text-slate-500 leading-relaxed">
                Generates a clean ISO A4 vector sheet (210 &times; 297 mm) with physical fiducials and customizable printable overlays.
              </p>
              <button
                type="button"
                onclick={() => goToStage(2)}
                class="w-full py-2 bg-slate-900 hover:bg-slate-800 text-white font-semibold rounded-xl text-xs transition flex items-center justify-center gap-1.5"
              >
                <span>Continue to Overlays & Margins</span>
                <ChevronRight class="size-3.5" />
              </button>
            </div>

          <!-- Option 2: Paste / Edit HTML Code -->
          {:else if stage1Mode === 'html'}
            <div class="space-y-3">
              <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-800 uppercase tracking-wider">HTML Code Editor</span>
                <button
                  type="button"
                  onclick={() => {
                    htmlCode = `<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    body { font-family: 'Helvetica Neue', Arial, sans-serif; margin: 0; padding: 20px; color: #0f172a; }
    .header { text-align: center; border-bottom: 2px solid #0f172a; padding-bottom: 10px; margin-bottom: 12px; }
    .header h1 { font-size: 18px; margin: 0 0 2px 0; text-transform: uppercase; }
    .header p { font-size: 10px; color: #475569; margin: 0; font-family: monospace; }
    .info { display: flex; justify-content: space-between; font-size: 10px; margin-bottom: 14px; border: 1px solid #cbd5e1; padding: 6px 10px; border-radius: 6px; background: #f8fafc; }
    .grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; }
    .part { font-size: 8.5px; font-weight: bold; background: #0f172a; color: #fff; padding: 3px 6px; border-radius: 4px; margin-bottom: 6px; text-align: center; text-transform: uppercase; }
    .row { display: flex; align-items: center; justify-content: space-between; font-size: 8.5px; font-family: monospace; margin-bottom: 3.5px; }
    .num { font-weight: bold; width: 18px; color: #334155; }
    .bubs { display: flex; gap: 3.5px; }
    .bub { width: 12px; height: 12px; border: 1.2px solid #334155; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 7px; font-weight: bold; }
  </style>
</head>
<body>
  <div class="header">
    <h1>University General Aptitude Test</h1>
    <p>Standard 150-Item Answer Sheet &bull; ISO A4</p>
  </div>
  <div class="info">
    <div><strong>Name:</strong> ____________________________</div>
    <div><strong>Examinee No:</strong> __________</div>
    <div><strong>Date:</strong> _______</div>
  </div>
  <div class="grid">
    <div>
      <div class="part">Part 1: Verbal Ability</div>
      <div class="row"><span class="num">01</span><div class="bubs"><span class="bub">A</span><span class="bub">B</span><span class="bub">C</span><span class="bub">D</span></div></div>
      <div class="row"><span class="num">02</span><div class="bubs"><span class="bub">A</span><span class="bub">B</span><span class="bub">C</span><span class="bub">D</span></div></div>
      <div class="row"><span class="num">03</span><div class="bubs"><span class="bub">A</span><span class="bub">B</span><span class="bub">C</span><span class="bub">D</span></div></div>
      <div class="row"><span class="num">04</span><div class="bubs"><span class="bub">A</span><span class="bub">B</span><span class="bub">C</span><span class="bub">D</span></div></div>
      <div class="row"><span class="num">05</span><div class="bubs"><span class="bub">A</span><span class="bub">B</span><span class="bub">C</span><span class="bub">D</span></div></div>
    </div>
    <div>
      <div class="part">Part 2: Quantitative</div>
      <div class="row"><span class="num">26</span><div class="bubs"><span class="bub">A</span><span class="bub">B</span><span class="bub">C</span><span class="bub">D</span></div></div>
      <div class="row"><span class="num">27</span><div class="bubs"><span class="bub">A</span><span class="bub">B</span><span class="bub">C</span><span class="bub">D</span></div></div>
      <div class="row"><span class="num">28</span><div class="bubs"><span class="bub">A</span><span class="bub">B</span><span class="bub">C</span><span class="bub">D</span></div></div>
      <div class="row"><span class="num">29</span><div class="bubs"><span class="bub">A</span><span class="bub">B</span><span class="bub">C</span><span class="bub">D</span></div></div>
      <div class="row"><span class="num">30</span><div class="bubs"><span class="bub">A</span><span class="bub">B</span><span class="bub">C</span><span class="bub">D</span></div></div>
    </div>
    <div>
      <div class="part">Part 3: Logical Reasoning</div>
      <div class="row"><span class="num">51</span><div class="bubs"><span class="bub">A</span><span class="bub">B</span><span class="bub">C</span><span class="bub">D</span></div></div>
      <div class="row"><span class="num">52</span><div class="bubs"><span class="bub">A</span><span class="bub">B</span><span class="bub">C</span><span class="bub">D</span></div></div>
      <div class="row"><span class="num">53</span><div class="bubs"><span class="bub">A</span><span class="bub">B</span><span class="bub">C</span><span class="bub">D</span></div></div>
      <div class="row"><span class="num">54</span><div class="bubs"><span class="bub">A</span><span class="bub">B</span><span class="bub">C</span><span class="bub">D</span></div></div>
      <div class="row"><span class="num">55</span><div class="bubs"><span class="bub">A</span><span class="bub">B</span><span class="bub">C</span><span class="bub">D</span></div></div>
    </div>
  </div>
</body>
</html>`;
                    triggerAutosave();
                  }}
                  class="text-[11px] font-semibold text-blue-600 hover:text-blue-800 flex items-center gap-1"
                >
                  <Sparkles class="size-3" />
                  <span>Insert 150-Q Template</span>
                </button>
              </div>

              <textarea
                bind:value={htmlCode}
                oninput={triggerAutosave}
                rows="10"
                class="w-full font-mono text-[10px] p-2.5 bg-slate-900 text-emerald-400 rounded-xl border border-slate-800 leading-relaxed resize-y focus:outline-hidden"
                placeholder="Paste HTML markup here…"
              ></textarea>

              <button
                type="button"
                onclick={compileHtmlSource}
                disabled={uploadRunning || !htmlCode}
                class="w-full py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-semibold rounded-xl text-xs transition flex items-center justify-center gap-2 shadow-xs"
              >
                {#if uploadRunning}
                  <Loader2 class="size-3.5 animate-spin" />
                  <span>{conversionStatusText}</span>
                {:else}
                  <Play class="size-3.5 text-emerald-400" />
                  <span>Compile HTML into 300-PPI Sheet</span>
                {/if}
              </button>
            </div>

          <!-- Option 3: Direct File Upload Dropzone (PDF / DOCX / Image) -->
          {:else if stage1Mode === 'upload'}
            <div class="space-y-3">
              <input
                bind:this={uploadFileInput}
                type="file"
                accept=".pdf,.docx,.doc,image/jpeg,image/png,image/webp,text/html"
                onchange={handleFileUpload}
                class="hidden"
              />
              <button
                type="button"
                onclick={() => uploadFileInput?.click()}
                disabled={uploadRunning}
                class="w-full p-6 border-2 border-dashed border-slate-300 hover:border-slate-400 rounded-2xl flex flex-col items-center justify-center text-center transition bg-slate-50/50 hover:bg-slate-50"
              >
                <Upload class="size-8 text-slate-400 mb-2" />
                <span class="text-xs font-bold text-slate-800">
                  Choose Document or Drag & Drop
                </span>
                <span class="text-[10px] text-slate-500 mt-1">Accepts PDF, Word (DOCX), Scanned JPG/PNG (Up to 25 MB)</span>
              </button>

              {#if uploadRunning}
                <div class="p-3 bg-slate-100 rounded-xl text-xs flex items-center gap-2 text-slate-700 font-medium">
                  <Loader2 class="size-4 animate-spin text-slate-900" />
                  <span>{conversionStatusText}</span>
                </div>
              {/if}

              {#if conversionError}
                <div class="p-3 bg-red-50 border border-red-200 rounded-xl text-xs text-red-900 flex items-center gap-2 font-medium">
                  <XCircle class="size-4 text-red-600 shrink-0" />
                  <span>{conversionError}</span>
                </div>
              {/if}
            </div>
          {/if}
        </div>

      {:else if currentStage === 2}
        <StudioOverlayPanel
          bind:marginMm
          bind:markerPattern
          bind:showHeaderOverlay
          bind:headerTitle
          bind:headerFontSize
          bind:showSignatureOverlay
          bind:signatureContent
          bind:showInstructionsOverlay
          bind:instructionsContent
          bind:showQrOverlay
          bind:customTextItems
          onChanged={triggerAutosave}
        />

      {:else if currentStage === 3}
        <div class="p-6 space-y-6">
          <div>
            <h2 class="text-base font-bold text-slate-900">300-PPI Registration & Quad</h2>
            <p class="text-xs text-slate-500 mt-1">Composites fiducial markers and freezes the 300-PPI print authority.</p>
          </div>

          <div class="p-4 bg-emerald-50/80 rounded-xl border border-emerald-200 text-xs text-emerald-900 space-y-2">
            <div class="flex items-center gap-1.5 font-bold">
              <ShieldCheck class="size-4 text-emerald-600" />
              <span>Normalization-First Quad Authority</span>
            </div>
            <p class="text-[11px] leading-relaxed text-emerald-800">
              The 4 corner fiducials define identically the <code class="font-mono bg-emerald-100 px-1 rounded">(0,0)</code> quad. In Stage 4, your question bubbles will map directly onto this calibrated quad with zero coordinate drift.
            </p>
          </div>

          <!-- 300-PPI Compose Trigger -->
          <div class="space-y-2">
            <button
              type="button"
              onclick={composeCanonicalPage}
              disabled={isComposing}
              class="w-full py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-bold rounded-xl text-xs transition flex items-center justify-center gap-2 shadow-xs"
            >
              {#if isComposing}
                <Loader2 class="size-3.5 animate-spin" />
                <span>{composeStatusText}</span>
              {:else}
                <RefreshCw class="size-3.5 text-emerald-400" />
                <span>Build 300-PPI Canonical Print Raster</span>
              {/if}
            </button>
            <p class="text-[10px] text-slate-500 text-center">
              Re-renders print authority with physical markers and custom overlays.
            </p>
          </div>
        </div>

      {:else if currentStage === 4}
        <StudioBubblePanel
          bind:sections
          bind:activeSectionIndex
          onChanged={triggerAutosave}
        />

      {:else if currentStage === 5}
        <StudioExportVerifyPanel
          {draft}
          {urls}
          {jsonSchema}
          {getCsrfToken}
        />
      {/if}
    </aside>

    <!-- RIGHT CANVAS WORKBENCH -->
    <StudioCanvas
      bind:scale
      bind:panX
      bind:panY
      bind:marginMm
      bind:markerPattern
      bind:canvasDragLocked
      rasterUrl={draft.raster_url}
      htmlContent={stage1Mode === 'html' ? htmlCode : null}
      {isComposing}
      {showHeaderOverlay}
      {headerTitle}
      {headerSubtitle}
      {headerFontSize}
      {headerX}
      {headerY}
      {headerW}
      {headerH}
      {showQrOverlay}
      {qrX}
      {qrY}
      {qrW}
      {qrH}
      {showInstructionsOverlay}
      {instructionsContent}
      {instX}
      {instY}
      {instW}
      {instH}
      {showSignatureOverlay}
      {signatureContent}
      {sigX}
      {sigY}
      {sigW}
      {sigH}
      {customTextItems}
      bind:sections
      bind:activeSectionIndex
      {currentStage}
      onGeometryChanged={triggerAutosave}
    />
  </div>
</div>
