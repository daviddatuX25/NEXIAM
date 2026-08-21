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
    Sliders,
    Sparkles,
    Play,
    ChevronRight,
    Upload,
    RotateCcw,
    Layers,
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
    'Step 1: Source Substrate & Ingestion',
    'Step 2: Layout, Margins & Overlays',
    'Step 3: Question Bubbles & Pitch Geometry',
    'Step 4: Verification & Production Deliverables',
  ];

  // Stage 1: Base Mode ('html' | 'upload')
  let stage1Mode = $state((draft.source_kind === 'image' || draft.source_kind === 'pdf' || draft.source_kind === 'docx') ? 'upload' : 'html');
  let baseType = $state(draft.source_kind ?? 'html');
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
  let conversionStatusText = $state('');
  let conversionError = $state('');

  // Stage 3 Composition Loading
  let isComposing = $state(false);

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

  // Stage 3: Multi-Section Geometry & Canvas State
  let canvasDragLocked = $state(true);
  let sections = $state([
    {
      id: 'Section_A',
      name: 'Section A (General Ability)',
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
      if (studio.raster_url && !draft.raster_url) draft.raster_url = studio.raster_url;
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

      if (typeof studio.showQrOverlay === 'boolean') showQrOverlay = studio.showQrOverlay;
      if (typeof studio.qrX === 'number') qrX = studio.qrX;
      if (typeof studio.qrY === 'number') qrY = studio.qrY;
      if (typeof studio.qrW === 'number') qrW = studio.qrW;
      if (typeof studio.qrH === 'number') qrH = studio.qrH;

      if (typeof studio.showInstructionsOverlay === 'boolean') showInstructionsOverlay = studio.showInstructionsOverlay;
      if (studio.instructionsContent) instructionsContent = studio.instructionsContent;
      if (typeof studio.instructionsFontSize === 'number') instructionsFontSize = studio.instructionsFontSize;
      if (typeof studio.instX === 'number') instX = studio.instX;
      if (typeof studio.instY === 'number') instY = studio.instY;
      if (typeof studio.instW === 'number') instW = studio.instW;
      if (typeof studio.instH === 'number') instH = studio.instH;

      if (typeof studio.showSignatureOverlay === 'boolean') showSignatureOverlay = studio.showSignatureOverlay;
      if (studio.signatureContent) signatureContent = studio.signatureContent;
      if (typeof studio.signatureFontSize === 'number') signatureFontSize = studio.signatureFontSize;
      if (typeof studio.sigX === 'number') sigX = studio.sigX;
      if (typeof studio.sigY === 'number') sigY = studio.sigY;
      if (typeof studio.sigW === 'number') sigW = studio.sigW;
      if (typeof studio.sigH === 'number') sigH = studio.sigH;

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

  async function goToStage(step) {
    currentStage = step;
    if (step === 3 && !draft.raster_url && !isComposing) {
      await composeCanonicalPage();
    }
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
        headerX, headerY, headerW, headerH,
        showQrOverlay,
        qrX, qrY, qrW, qrH,
        showInstructionsOverlay,
        instructionsContent,
        instructionsFontSize,
        instX, instY, instW, instH,
        showSignatureOverlay,
        signatureContent,
        signatureFontSize,
        sigX, sigY, sigW, sigH,
        showMetadataOverlay,
        metadataContent,
        metadataFontSize,
        showRollOverlay,
        rollDigits,
        customTextItems,
        htmlCode,
        stage1Mode,
        baseType,
        raster_url: draft.raster_url,
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

  // Stage 2/3 Composition
  async function composeCanonicalPage() {
    if (!urls.compose) return;
    isComposing = true;

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
          triggerAutosave();
        }
      }
    } catch {
      // Advisory
    } finally {
      isComposing = false;
    }
  }

  function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  }

  function triggerAutosaveDebounced() {
    triggerAutosave();
  }
</script>

<div class="h-screen w-screen flex flex-col bg-slate-900 text-slate-100 overflow-hidden font-sans select-none">
  <!-- TOP STUDIO HEADER & 4-STEP BREADCRUMB -->
  <header class="h-14 bg-slate-900 border-b border-slate-800 px-6 flex items-center justify-between shrink-0 z-30">
    <div class="flex items-center space-x-4">
      <div class="flex items-center space-x-2.5">
        <div class="size-8 rounded-lg bg-blue-600 flex items-center justify-center text-white shadow-sm font-black text-sm">
          N
        </div>
        <div>
          <div class="flex items-center space-x-2">
            <span class="text-xs font-bold text-white tracking-wide uppercase">{draft.name || 'Template Studio'}</span>
            <span class="px-1.5 py-0.5 bg-slate-800 border border-slate-700 text-[10px] font-mono text-slate-400 rounded">
              {draft.code || 'OMR-DRAFT'}
            </span>
          </div>
          <p class="text-[10px] text-slate-400 font-mono mt-0.5">
            {stageTitles[currentStage - 1]} &bull; {totalQuestionsCount} Questions Total
          </p>
        </div>
      </div>
    </div>

    <!-- 4-Stage Modern Stepper Navigation -->
    <div class="hidden md:flex items-center space-x-1 bg-slate-950/80 p-1 rounded-xl border border-slate-800">
      {#each stageTitles as title, idx}
        {@const stageNum = idx + 1}
        {@const isActive = currentStage === stageNum}
        {@const isPast = currentStage > stageNum}
        <button
          type="button"
          onclick={() => goToStage(stageNum)}
          class={`px-3 py-1.5 rounded-lg text-xs font-semibold transition flex items-center space-x-2 ${
            isActive
              ? 'bg-blue-600 text-white shadow-xs'
              : isPast
                ? 'text-slate-300 hover:text-white hover:bg-slate-800/60'
                : 'text-slate-500 hover:text-slate-400'
          }`}
        >
          <span class={`size-4 rounded-full flex items-center justify-center text-[10px] font-bold ${
            isActive ? 'bg-white text-blue-600' : isPast ? 'bg-slate-700 text-white' : 'bg-slate-800 text-slate-400'
          }`}>
            {stageNum}
          </span>
          <span>{title.split(':')[1]?.split('&')[0]?.trim() || title}</span>
        </button>
      {/each}
    </div>

    <!-- Right Controls: Save Status & Actions -->
    <div class="flex items-center space-x-3">
      <div class="flex items-center space-x-1.5 text-xs">
        {#if saveStatus === 'saving'}
          <Loader2 class="size-3.5 text-amber-400 animate-spin" />
          <span class="text-amber-400 font-mono text-[11px]">Saving…</span>
        {:else if saveStatus === 'saved'}
          <CheckCircle2 class="size-3.5 text-emerald-400" />
          <span class="text-emerald-400 font-mono text-[11px]">Saved</span>
        {:else if saveStatus === 'error'}
          <XCircle class="size-3.5 text-rose-400" />
          <span class="text-rose-400 font-mono text-[11px] max-w-[140px] truncate" title={errorMessage}>
            {errorMessage || 'Save error'}
          </span>
        {/if}
      </div>

      <button
        type="button"
        onclick={performSave}
        class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-white text-xs font-semibold rounded-lg border border-slate-700 transition"
      >
        Save
      </button>
    </div>
  </header>

  <!-- MAIN STUDIO WORKBENCH SPLIT -->
  <div class="flex-1 flex overflow-hidden">
    <!-- LEFT WORKBENCH SIDEBAR (Tool Controls for Current Step) -->
    <aside class="w-[390px] bg-white text-slate-900 border-r border-slate-200 flex flex-col justify-between shrink-0 overflow-y-auto">
      {#if currentStage === 1}
        <!-- STEP 1: SOURCE SUBSTRATE & INGESTION -->
        <div class="p-6 space-y-6 flex-1 flex flex-col justify-between">
          <div class="space-y-5">
            <div>
              <h2 class="text-base font-bold text-slate-900">Source Substrate</h2>
              <p class="text-xs text-slate-500 mt-0.5">Choose how to generate or ingest your exam answer sheet.</p>
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
              <div class="space-y-3">
                <div class="flex items-center justify-between">
                  <label class="text-xs font-bold text-slate-700">HTML Source Code</label>
                  <button
                    type="button"
                    onclick={() => {
                      htmlCode = `<!DOCTYPE html><html><head><meta charset="utf-8"><style>body{font-family:sans-serif;padding:20px;color:#0f172a;}.header{text-align:center;border-bottom:2px solid #0f172a;margin-bottom:12px;}.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;}.row{display:flex;justify-content:space-between;margin-bottom:4px;font-size:9px;}.bub{width:12px;height:12px;border:1px solid #334155;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:7px;}</style></head><body><div class="header"><h2>University General Aptitude Test</h2><p>Standard 150-Q Template</p></div><div class="grid">${Array.from({length:3}).map((_,c)=>`<div><h4 style="font-size:10px;margin-bottom:4px;">Part ${c+1}</h4>${Array.from({length:50}).map((_,r)=>`<div class="row"><span>${c*50+r+1}</span><div><span class="bub">A</span> <span class="bub">B</span> <span class="bub">C</span> <span class="bub">D</span></div></div>`).join('')}</div>`).join('')}</div></body></html>`;
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
                  class="w-full font-mono text-[11px] leading-relaxed p-2.5 bg-slate-900 text-slate-100 rounded-xl border border-slate-800 resize-y focus:outline-hidden focus:ring-2 focus:ring-blue-500"
                  placeholder="Paste HTML markup here…"
                ></textarea>
              </div>
            {:else}
              <!-- Option 2: Upload Document File -->
              <div class="space-y-4">
                <div class="border-2 border-dashed border-slate-300 rounded-2xl p-6 text-center hover:border-slate-400 bg-slate-50 transition">
                  <Upload class="size-8 text-slate-400 mx-auto mb-2" />
                  <p class="text-xs font-bold text-slate-700">Drop PDF, Word (.docx), or Image file</p>
                  <p class="text-[11px] text-slate-400 mt-1">Automatic rasterization at 300 PPI print resolution</p>
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
                    class="mt-4 px-4 py-2 bg-slate-900 text-white text-xs font-semibold rounded-xl hover:bg-slate-800 transition shadow-xs"
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

          <!-- Continue to Step 2 Button -->
          <div class="pt-4 border-t border-slate-200">
            <button
              type="button"
              onclick={() => goToStage(2)}
              class="w-full py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-semibold rounded-xl text-xs transition flex items-center justify-center gap-2 shadow-xs"
            >
              <span>Continue to Layout & Markers</span>
              <ChevronRight class="size-4" />
            </button>
          </div>
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
          bind:showQrOverlay
          bind:showInstructionsOverlay
          bind:instructionsContent
          bind:instructionsFontSize
          bind:showSignatureOverlay
          bind:signatureContent
          bind:signatureFontSize
          bind:customTextItems
          onChanged={triggerAutosave}
          onBack={() => goToStage(1)}
          onNext={() => goToStage(3)}
        />
      {:else if currentStage === 3}
        <!-- STEP 3: QUESTION BUBBLES & PITCH GEOMETRY -->
        <div class="flex-1 flex flex-col justify-between">
          <!-- Step 3 Raster Settlement Status Banner -->
          {#if draft.raster_url}
            <div class="mx-6 mt-4 p-2.5 bg-emerald-50 border border-emerald-200 rounded-xl text-xs flex items-center justify-between text-emerald-900">
              <div class="flex items-center gap-2">
                <Layers class="size-4 text-emerald-600 shrink-0" />
                <span class="font-medium">300-PPI Page Raster Composed & Persisted</span>
              </div>
              <button
                type="button"
                onclick={composeCanonicalPage}
                class="text-[11px] font-semibold text-emerald-700 hover:text-emerald-900 underline flex items-center gap-1"
                title="Recompose print raster from current design"
              >
                <RotateCcw class="size-3" />
                <span>Recompose</span>
              </button>
            </div>
          {/if}

          <StudioBubblePanel
            bind:sections
            bind:activeSectionIndex
            onChanged={triggerAutosave}
            onBack={() => goToStage(2)}
            onNext={() => goToStage(4)}
          />
        </div>
      {:else if currentStage === 4}
        <!-- STEP 4: VERIFICATION & PRODUCTION DELIVERABLES -->
        <StudioExportVerifyPanel
          {draft}
          {jsonSchema}
          {urls}
          onBack={() => goToStage(3)}
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
      isHtmlMode={stage1Mode === 'html' && !draft.raster_url}
      {isComposing}
      {showHeaderOverlay}
      {headerTitle}
      {headerSubtitle}
      {headerFontSize}
      bind:headerX
      bind:headerY
      bind:headerW
      bind:headerH
      {showQrOverlay}
      bind:qrX
      bind:qrY
      bind:qrW
      bind:qrH
      {showInstructionsOverlay}
      {instructionsContent}
      bind:instX
      bind:instY
      bind:instW
      bind:instH
      {showSignatureOverlay}
      {signatureContent}
      bind:sigX
      bind:sigY
      bind:sigW
      bind:sigH
      bind:customTextItems
      bind:sections
      bind:activeSectionIndex
      {currentStage}
      onGeometryChanged={triggerAutosave}
    />
  </div>
</div>
