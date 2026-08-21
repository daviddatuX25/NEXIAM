<script>
  import { onMount, onDestroy } from 'svelte';
  import { newIdempotencyKey } from '@/lib/utils';
  import {
    emitNativeMapping,
    emitOverlayBoxes,
    contentBlockSizeMm,
  } from '@/lib/omrStudioGeometry';
  import {
    renderFullSheetHtml,
    PRESET_150Q_COMPONENTS,
    DEFAULT_A4_SETTINGS,
    componentToSectionData,
  } from '@/lib/omrComponents';
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
    FileText,
    Sliders,
    Type,
    AlignLeft,
    AlignCenter,
    AlignRight,
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

  // Compact, Single-Page A4-Fitted 150-Q HTML Template
  // Componentized Single-Page A4-Fitted 150-Q HTML Template (Examinee ID Matrix + 6 Subjects x 25 Qs)
  const DEFAULT_150Q_HTML = `<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    html, body {
      width: 100%; height: 100%;
      padding: 8mm 10mm;
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
      color: #0f172a;
      background: #ffffff;
      overflow: hidden;
    }

    /* Document Header */
    .omr-header {
      border-bottom: 2px solid #0f172a;
      padding-bottom: 3px;
      margin-bottom: 6px;
      display: flex;
      justify-content: space-between;
      align-items: flex-end;
    }
    .omr-title h1 { font-size: 13px; font-weight: 900; letter-spacing: 0.5px; text-transform: uppercase; line-height: 1.1; color: #0f172a; }
    .omr-title p { font-size: 7px; color: #475569; font-weight: 600; text-transform: uppercase; letter-spacing: 0.3px; margin-top: 1px; }
    .omr-sheet-code { font-family: monospace; font-size: 8px; font-weight: 800; color: #0f172a; border: 1px solid #0f172a; padding: 2px 5px; border-radius: 2px; }

    /* Top Metadata & ID Section */
    .omr-top-section {
      display: grid;
      grid-template-columns: 1fr 150px;
      gap: 10px;
      margin-bottom: 8px;
      align-items: start;
    }

    /* Left Meta Block: Name, Details, Instructions */
    .omr-meta-block {
      display: flex;
      flex-direction: column;
      gap: 5px;
    }
    .omr-field-box {
      border: 1.2px solid #334155;
      border-radius: 3px;
      padding: 3px 6px;
      background: #f8fafc;
    }
    .omr-field-label {
      font-size: 6px;
      font-weight: 800;
      text-transform: uppercase;
      color: #475569;
      margin-bottom: 1px;
    }
    .omr-field-line {
      border-bottom: 1px dashed #94a3b8;
      height: 11px;
    }
    .omr-meta-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 6px;
    }
    .omr-instructions {
      border: 1px solid #cbd5e1;
      border-radius: 3px;
      padding: 3px 6px;
      background: #ffffff;
      font-size: 6px;
      color: #334155;
      line-height: 1.3;
    }
    .omr-instructions strong { color: #0f172a; }

    /* Right ID Matrix: Digit Boxes + 0-9 Shading Grid */
    .omr-id-container {
      border: 1.5px solid #0f172a;
      border-radius: 3px;
      padding: 4px 5px;
      background: #fafafa;
    }
    .omr-id-title {
      font-size: 6.5px;
      font-weight: 900;
      text-align: center;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: #0f172a;
      margin-bottom: 3px;
    }
    .omr-id-boxes {
      display: flex;
      justify-content: flex-end;
      gap: 3px;
      margin-bottom: 3px;
    }
    .omr-id-box {
      width: 11px;
      height: 13px;
      border: 1.2px solid #0f172a;
      border-radius: 2px;
      background: #ffffff;
    }
    .omr-id-matrix {
      display: flex;
      flex-direction: column;
      gap: 1.8px;
    }
    .omr-id-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    .omr-id-num {
      font-size: 6px;
      font-weight: 800;
      font-family: monospace;
      color: #334155;
      width: 8px;
      text-align: center;
    }
    .omr-id-bubs {
      display: flex;
      gap: 3px;
    }
    .omr-id-bub {
      width: 11px;
      height: 8.5px;
      border: 1.1px solid #1e293b;
      border-radius: 50%;
      background: #ffffff;
    }

    /* 6-Column Answer Sheet Grid */
    .omr-exam-grid {
      display: grid;
      grid-template-columns: repeat(6, 1fr);
      gap: 5px;
      border-top: 1.5px solid #0f172a;
      padding-top: 5px;
    }
    .omr-col {
      background: #ffffff;
    }
    .omr-subject-header {
      font-size: 6px;
      font-weight: 900;
      background: #0f172a;
      color: #ffffff;
      padding: 2px 3px;
      border-radius: 2px;
      text-align: center;
      text-transform: uppercase;
      letter-spacing: 0.3px;
      margin-bottom: 2px;
    }
    .omr-option-header {
      display: flex;
      justify-content: flex-end;
      gap: 3.5px;
      font-size: 5.5px;
      font-weight: 900;
      color: #0f172a;
      margin-bottom: 2px;
      padding-right: 1px;
    }
    .omr-opt-label {
      width: 9px;
      text-align: center;
    }

    /* Question Rows */
    .omr-q-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 1.2px;
    }
    .omr-q-num {
      font-size: 6px;
      font-weight: 700;
      font-family: monospace;
      color: #334155;
      width: 11px;
      text-align: right;
      padding-right: 2px;
    }
    .omr-bubs {
      display: flex;
      gap: 3.5px;
    }
    .omr-bub {
      width: 9px;
      height: 8.5px;
      border: 1.1px solid #1e293b;
      border-radius: 50%;
      background: #ffffff;
    }
  </style>
</head>
<body>
  <!-- Document Header -->
  <div class="omr-header">
    <div class="omr-title">
      <h1>University General Aptitude Test</h1>
      <p>Standard 150-Item Examination Answer Sheet &bull; ISO A4</p>
    </div>
    <div class="omr-sheet-code">FORM 150-A</div>
  </div>

  <!-- Top Section: Metadata & Examinee ID Matrix -->
  <div class="omr-top-section">
    <div class="omr-meta-block">
      <div class="omr-field-box">
        <div class="omr-field-label">Candidate Name (Print: Surname, First Name, Middle Initial)</div>
        <div class="omr-field-line"></div>
      </div>
      <div class="omr-meta-row">
        <div class="omr-field-box">
          <div class="omr-field-label">Birthdate (MM / DD / YYYY)</div>
          <div class="omr-field-line"></div>
        </div>
        <div class="omr-field-box">
          <div class="omr-field-label">Room / Seat / Group No.</div>
          <div class="omr-field-line"></div>
        </div>
      </div>
      <div class="omr-instructions">
        <strong>INSTRUCTIONS:</strong> Use Black Pencil/Pen (#2 or HB). Shade circle completely. Erase cleanly if changing answers. Make no stray marks on this sheet.
      </div>
    </div>

    <!-- Examinee ID Bubble Matrix (7 Columns x 10 Rows [0-9]) -->
    <div class="omr-id-container">
      <div class="omr-id-title">Examinee Number</div>
      <div class="omr-id-boxes">
        <div class="omr-id-box"></div>
        <div class="omr-id-box"></div>
        <div class="omr-id-box"></div>
        <div class="omr-id-box"></div>
        <div class="omr-id-box"></div>
        <div class="omr-id-box"></div>
        <div class="omr-id-box"></div>
      </div>
      <div class="omr-id-matrix">
        ${Array.from({ length: 10 }).map((_, digit) => `
          <div class="omr-id-row">
            <span class="omr-id-num">${digit}</span>
            <div class="omr-id-bubs">
              <div class="omr-id-bub"></div>
              <div class="omr-id-bub"></div>
              <div class="omr-id-bub"></div>
              <div class="omr-id-bub"></div>
              <div class="omr-id-bub"></div>
              <div class="omr-id-bub"></div>
              <div class="omr-id-bub"></div>
            </div>
          </div>
        `).join('')}
      </div>
    </div>
  </div>

  <!-- 6-Subject Question Grid with A-B-C-D Column Option Headers -->
  <div class="omr-exam-grid">
    <div class="omr-col">
      <div class="omr-subject-header">1. English</div>
      <div class="omr-option-header"><span class="omr-opt-label">A</span><span class="omr-opt-label">B</span><span class="omr-opt-label">C</span><span class="omr-opt-label">D</span></div>
      ${Array.from({ length: 25 }).map((_, i) => `<div class="omr-q-row"><span class="omr-q-num">${i + 1}</span><div class="omr-bubs"><div class="omr-bub"></div><div class="omr-bub"></div><div class="omr-bub"></div><div class="omr-bub"></div></div></div>`).join('')}
    </div>
    <div class="omr-col">
      <div class="omr-subject-header">2. Math</div>
      <div class="omr-option-header"><span class="omr-opt-label">A</span><span class="omr-opt-label">B</span><span class="omr-opt-label">C</span><span class="omr-opt-label">D</span></div>
      ${Array.from({ length: 25 }).map((_, i) => `<div class="omr-q-row"><span class="omr-q-num">${i + 26}</span><div class="omr-bubs"><div class="omr-bub"></div><div class="omr-bub"></div><div class="omr-bub"></div><div class="omr-bub"></div></div></div>`).join('')}
    </div>
    <div class="omr-col">
      <div class="omr-subject-header">3. Science</div>
      <div class="omr-option-header"><span class="omr-opt-label">A</span><span class="omr-opt-label">B</span><span class="omr-opt-label">C</span><span class="omr-opt-label">D</span></div>
      ${Array.from({ length: 25 }).map((_, i) => `<div class="omr-q-row"><span class="omr-q-num">${i + 51}</span><div class="omr-bubs"><div class="omr-bub"></div><div class="omr-bub"></div><div class="omr-bub"></div><div class="omr-bub"></div></div></div>`).join('')}
    </div>
    <div class="omr-col">
      <div class="omr-subject-header">4. Abstract</div>
      <div class="omr-option-header"><span class="omr-opt-label">A</span><span class="omr-opt-label">B</span><span class="omr-opt-label">C</span><span class="omr-opt-label">D</span></div>
      ${Array.from({ length: 25 }).map((_, i) => `<div class="omr-q-row"><span class="omr-q-num">${i + 76}</span><div class="omr-bubs"><div class="omr-bub"></div><div class="omr-bub"></div><div class="omr-bub"></div><div class="omr-bub"></div></div></div>`).join('')}
    </div>
    <div class="omr-col">
      <div class="omr-subject-header">5. Gen Info</div>
      <div class="omr-option-header"><span class="omr-opt-label">A</span><span class="omr-opt-label">B</span><span class="omr-opt-label">C</span><span class="omr-opt-label">D</span></div>
      ${Array.from({ length: 25 }).map((_, i) => `<div class="omr-q-row"><span class="omr-q-num">${i + 101}</span><div class="omr-bubs"><div class="omr-bub"></div><div class="omr-bub"></div><div class="omr-bub"></div><div class="omr-bub"></div></div></div>`).join('')}
    </div>
    <div class="omr-col">
      <div class="omr-subject-header">6. Logic</div>
      <div class="omr-option-header"><span class="omr-opt-label">A</span><span class="omr-opt-label">B</span><span class="omr-opt-label">C</span><span class="omr-opt-label">D</span></div>
      ${Array.from({ length: 25 }).map((_, i) => `<div class="omr-q-row"><span class="omr-q-num">${i + 126}</span><div class="omr-bubs"><div class="omr-bub"></div><div class="omr-bub"></div><div class="omr-bub"></div><div class="omr-bub"></div></div></div>`).join('')}
    </div>
  </div>
</body>
</html>`;

  // Stage 1: Component-Driven Native Designer State
  let designerSubTab = $state('designer'); // 'designer' | 'raw_html'
  let components = $state(JSON.parse(JSON.stringify(PRESET_150Q_COMPONENTS)));

  let headerComp = $derived(components.find((c) => c.type === 'header'));
  let candidateInfoComp = $derived(components.find((c) => c.type === 'candidate_info'));
  let instructionsComp = $derived(components.find((c) => c.type === 'instructions'));
  let rollIdComp = $derived(components.find((c) => c.type === 'roll'));
  let questionGridComps = $derived(components.filter((c) => c.type === 'mcq' || c.type === 'tf' || c.type === 'likert' || c.type === 'custom'));

  function syncComponentsToSections() {
    htmlCode = renderFullSheetHtml(components);
    sections = components
      .map((c) => componentToSectionData(c))
      .filter((s) => s !== null);
    triggerAutosave();
  }

  function applyPreset(presetName) {
    if (presetName === '150q') {
      components = JSON.parse(JSON.stringify(PRESET_150Q_COMPONENTS));
    } else if (presetName === '100q') {
      const copy = JSON.parse(JSON.stringify(PRESET_150Q_COMPONENTS));
      copy[0].title = 'University Standard Aptitude Exam';
      copy[0].subtitle = '100-Item Examination Answer Sheet • ISO A4';
      copy[0].formCode = 'FORM 100-B';
      components = copy.filter((c) => !['Part_5', 'Part_6'].includes(c.id));
    } else if (presetName === '50q') {
      const copy = JSON.parse(JSON.stringify(PRESET_150Q_COMPONENTS));
      copy[0].title = 'Short Evaluation Assessment';
      copy[0].subtitle = '50-Item Examination Answer Sheet • ISO A4';
      copy[0].formCode = 'FORM 50-C';
      components = copy.filter((c) => !['Part_3', 'Part_4', 'Part_5', 'Part_6'].includes(c.id));
    }
    syncComponentsToSections();
  }

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
  let showHeaderOverlay = $state(false);
  let headerTitle = $state(draft.name || 'EXAMINATION ANSWER SHEET');
  let headerSubtitle = $state(draft.code || 'AUTOMATED OMR EVALUATION FORM');
  let headerFontSize = $state(14.0);
  let headerFontFamily = $state('Inter, sans-serif');
  let headerColor = $state('#0f172a');
  let headerAlign = $state('left');
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
  let instructionsFontFamily = $state('Inter, sans-serif');
  let instructionsColor = $state('#334155');
  let instX = $state(25);
  let instY = $state(250);
  let instW = $state(90);
  let instH = $state(28);
  let instLocked = $state(false);

  let showSignatureOverlay = $state(false);
  let signatureContent = $state('EXAMINEE SIGNATURE');
  let signatureFontSize = $state(8.5);
  let signatureFontFamily = $state('Inter, sans-serif');
  let signatureColor = $state('#475569');
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

  // Stage 3: Multi-Section Geometry & Canvas State
  let canvasLocked = $state(false);
  let sections = $state([
    {
      id: 'Examinee_ID',
      name: 'Examinee Number (7 Digits)',
      type: 'roll',
      posX: 125,
      posY: 22,
      w_mm: 58,
      h_mm: 54,
      questionsCount: 7,
      cols: 7,
      optionsCount: 10,
      startQ: 1,
      customValuesStr: '0, 1, 2, 3, 4, 5, 6, 7, 8, 9',
      bubblePitchMm: 5.5,
      rowPitchMm: 4.8,
      isLocked: false,
    },
    {
      id: 'Part_1',
      name: 'Part 1: English (Q1–25)',
      type: 'mcq',
      posX: 10,
      posY: 88,
      w_mm: 28,
      h_mm: 175,
      questionsCount: 25,
      cols: 1,
      optionsCount: 4,
      startQ: 1,
      customValuesStr: 'A, B, C, D',
      bubblePitchMm: 6.0,
      rowPitchMm: 6.8,
      isLocked: false,
    },
    {
      id: 'Part_2',
      name: 'Part 2: Math (Q26–50)',
      type: 'mcq',
      posX: 42,
      posY: 88,
      w_mm: 28,
      h_mm: 175,
      questionsCount: 25,
      cols: 1,
      optionsCount: 4,
      startQ: 26,
      customValuesStr: 'A, B, C, D',
      bubblePitchMm: 6.0,
      rowPitchMm: 6.8,
      isLocked: false,
    },
    {
      id: 'Part_3',
      name: 'Part 3: Science (Q51–75)',
      type: 'mcq',
      posX: 74,
      posY: 88,
      w_mm: 28,
      h_mm: 175,
      questionsCount: 25,
      cols: 1,
      optionsCount: 4,
      startQ: 51,
      customValuesStr: 'A, B, C, D',
      bubblePitchMm: 6.0,
      rowPitchMm: 6.8,
      isLocked: false,
    },
    {
      id: 'Part_4',
      name: 'Part 4: Abstract (Q76–100)',
      type: 'mcq',
      posX: 106,
      posY: 88,
      w_mm: 28,
      h_mm: 175,
      questionsCount: 25,
      cols: 1,
      optionsCount: 4,
      startQ: 76,
      customValuesStr: 'A, B, C, D',
      bubblePitchMm: 6.0,
      rowPitchMm: 6.8,
      isLocked: false,
    },
    {
      id: 'Part_5',
      name: 'Part 5: Gen Info (Q101–125)',
      type: 'mcq',
      posX: 138,
      posY: 88,
      w_mm: 28,
      h_mm: 175,
      questionsCount: 25,
      cols: 1,
      optionsCount: 4,
      startQ: 101,
      customValuesStr: 'A, B, C, D',
      bubblePitchMm: 6.0,
      rowPitchMm: 6.8,
      isLocked: false,
    },
    {
      id: 'Part_6',
      name: 'Part 6: Logic (Q126–150)',
      type: 'mcq',
      posX: 170,
      posY: 88,
      w_mm: 28,
      h_mm: 175,
      questionsCount: 25,
      cols: 1,
      optionsCount: 4,
      startQ: 126,
      customValuesStr: 'A, B, C, D',
      bubblePitchMm: 6.0,
      rowPitchMm: 6.8,
      isLocked: false,
    },
  ]);
  let activeSectionIndex = $state(0);
  let bubbleRadiusMm = $state(4.5);

  // Canvas Pan & Zoom (Optimized Scale = 0.78 fits 100% of A4 sheet inside standard viewport without overflow)
  let scale = $state(0.78);
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
      if (studio.headerFontFamily) headerFontFamily = studio.headerFontFamily;
      if (studio.headerColor) headerColor = studio.headerColor;
      if (studio.headerAlign) headerAlign = studio.headerAlign;
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
      if (studio.instructionsFontFamily) instructionsFontFamily = studio.instructionsFontFamily;
      if (studio.instructionsColor) instructionsColor = studio.instructionsColor;
      if (typeof studio.instW === 'number') instW = studio.instW;
      if (typeof studio.instH === 'number') instH = studio.instH;
      if (typeof studio.instLocked === 'boolean') instLocked = studio.instLocked;

      if (typeof studio.showSignatureOverlay === 'boolean') showSignatureOverlay = studio.showSignatureOverlay;
      if (studio.signatureContent) signatureContent = studio.signatureContent;
      if (typeof studio.signatureFontSize === 'number') signatureFontSize = studio.signatureFontSize;
      if (studio.signatureFontFamily) signatureFontFamily = studio.signatureFontFamily;
      if (studio.signatureColor) signatureColor = studio.signatureColor;
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
  function loadSampleLayout() {
    htmlCode = DEFAULT_150Q_HTML;
    stage1Mode = 'html';
    baseType = 'html';
    sections = [
      { id: 'Examinee_ID', name: 'Examinee Number (7 Digits)', type: 'roll', posX: 125, posY: 22, w_mm: 58, h_mm: 54, questionsCount: 7, cols: 7, optionsCount: 10, startQ: 1, customValuesStr: '0, 1, 2, 3, 4, 5, 6, 7, 8, 9', bubblePitchMm: 5.5, rowPitchMm: 4.8, isLocked: false },
      { id: 'Part_1', name: 'Part 1: English (Q1–25)', type: 'mcq', posX: 10, posY: 88, w_mm: 28, h_mm: 175, questionsCount: 25, cols: 1, optionsCount: 4, startQ: 1, customValuesStr: 'A, B, C, D', bubblePitchMm: 6.0, rowPitchMm: 6.8, isLocked: false },
      { id: 'Part_2', name: 'Part 2: Math (Q26–50)', type: 'mcq', posX: 42, posY: 88, w_mm: 28, h_mm: 175, questionsCount: 25, cols: 1, optionsCount: 4, startQ: 26, customValuesStr: 'A, B, C, D', bubblePitchMm: 6.0, rowPitchMm: 6.8, isLocked: false },
      { id: 'Part_3', name: 'Part 3: Science (Q51–75)', type: 'mcq', posX: 74, posY: 88, w_mm: 28, h_mm: 175, questionsCount: 25, cols: 1, optionsCount: 4, startQ: 51, customValuesStr: 'A, B, C, D', bubblePitchMm: 6.0, rowPitchMm: 6.8, isLocked: false },
      { id: 'Part_4', name: 'Part 4: Abstract (Q76–100)', type: 'mcq', posX: 106, posY: 88, w_mm: 28, h_mm: 175, questionsCount: 25, cols: 1, optionsCount: 4, startQ: 76, customValuesStr: 'A, B, C, D', bubblePitchMm: 6.0, rowPitchMm: 6.8, isLocked: false },
      { id: 'Part_5', name: 'Part 5: Gen Info (Q101–125)', type: 'mcq', posX: 138, posY: 88, w_mm: 28, h_mm: 175, questionsCount: 25, cols: 1, optionsCount: 4, startQ: 101, customValuesStr: 'A, B, C, D', bubblePitchMm: 6.0, rowPitchMm: 6.8, isLocked: false },
      { id: 'Part_6', name: 'Part 6: Logic (Q126–150)', type: 'mcq', posX: 170, posY: 88, w_mm: 28, h_mm: 175, questionsCount: 25, cols: 1, optionsCount: 4, startQ: 126, customValuesStr: 'A, B, C, D', bubblePitchMm: 6.0, rowPitchMm: 6.8, isLocked: false },
    ];
    triggerAutosave();
  }

  async function loadSampleUploadedDocument() {
    if (!urls.source_ingest) return;
    uploadRunning = true;
    conversionStatusText = 'Importing standard 150-question test sheet…';
    conversionError = '';

    try {
      const res = await fetch('/samples/omr/standard-150q-answer-sheet.png');
      const blob = await res.blob();
      const file = new File([blob], 'standard-150q-answer-sheet.png', { type: 'image/png' });

      const form = new FormData();
      form.append('source_kind', 'image');
      form.append('source', file);
      form.append('width_mm', '210');
      form.append('height_mm', '297');
      form.append('idempotency_key', newIdempotencyKey());

      const uploadRes = await fetch(urls.source_ingest, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': getCsrfToken(), 'Accept': 'application/json' },
        body: form,
      });

      if (!uploadRes.ok) {
        const data = await uploadRes.json().catch(() => ({}));
        throw new Error(data.message || 'File upload rejected.');
      }

      conversionStatusText = 'Rasterizing standard 150-Q answer sheet at 300 PPI…';
      await pollConversionRun();
    } catch (err) {
      uploadRunning = false;
      conversionError = err.message || 'Sample document import failed.';
    }
  }

  function confirmResetAndNavigate() {
    draft.raster_url = null;
    showResetConfirmModal = false;
    if (pendingTargetStage) {
      executeGoToStage(pendingTargetStage);
      pendingTargetStage = null;
    }
  }

  async function executeGoToStage(step) {
    currentStage = step;
    await performSave();
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
      if (s.type === 'roll') {
        const digits = Number(s.questionsCount) || 7;
        const bPitch = Number(s.bubblePitchMm) || 5.5;
        const rPitch = Number(s.rowPitchMm) || 4.8;
        return {
          id: s.id,
          type: 'roll',
          x_mm: Number(s.posX) || 125,
          y_mm: Number(s.posY) || 22,
          w_mm: Number((4.5 + (digits - 1) * bPitch + 1.5).toFixed(1)),
          h_mm: Number((4.5 + (10 - 1) * rPitch + 1.5).toFixed(1)),
          digits,
          bubble_pitch_mm: bPitch,
          row_pitch_mm: rPitch,
        };
      }

      let opts = Number(s.optionsCount) || 4;
      if (s.type === 'tf') opts = 2;
      if (s.type === 'likert') opts = 5;
      if (s.type === 'custom') opts = (s.customValuesStr || '').split(',').length;

      // Section bounds are content-derived (no manual resize in the builder),
      // so the native mapping always matches the minimized canvas rectangle.
      const { w_mm, h_mm } = contentBlockSizeMm(
        opts,
        Number(s.questionsCount) || 10,
        Number(s.bubblePitchMm) || 12.0,
        Number(s.rowPitchMm) || 6.5
      );

      return {
        id: s.id,
        type: s.type,
        x_mm: Number(s.posX) || 25,
        y_mm: Number(s.posY) || 72,
        w_mm,
        h_mm,
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
        headerFontFamily,
        headerColor,
        headerAlign,
        headerX, headerY, headerW, headerH, headerLocked,
        showQrOverlay,
        qrX, qrY, qrW, qrH, qrLocked,
        showInstructionsOverlay,
        instructionsContent,
        instructionsFontSize,
        instructionsFontFamily,
        instructionsColor,
        instX, instY, instW, instH, instLocked,
        showSignatureOverlay,
        signatureContent,
        signatureFontSize,
        signatureFontFamily,
        signatureColor,
        sigX, sigY, sigW, sigH, sigLocked,
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
    if (urls.confirm_pages) {
      try {
        await fetch(urls.confirm_pages, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrfToken(), 'Accept': 'application/json' },
          body: JSON.stringify({
            pages: [{ page_no: 1, decision: 'confirmed' }],
          }),
        });
      } catch {}
    }

    uploadRunning = false;
    router.reload({
      only: ['draft'],
      onSuccess: () => {
        executeGoToStage(2);
      },
      onError: () => {
        executeGoToStage(2);
      },
    });
  }

  function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  }

  function zoomStep(delta) {
    scale = Math.min(Math.max(0.35, scale + delta), 2.5);
  }

  function resetZoom() {
    scale = 0.78;
    panX = 0;
    panY = 0;
  }

  /** Svelte action: strips padding and overflow from the page-layout <main> and its slot wrapper
      so this component fills the remaining viewport edge-to-edge with zero scroll. */
  function bleedParent(node) {
    const slot = node.parentElement;
    const pageMain = slot?.closest('main');
    if (!pageMain) return;
    const savedMain = { padding: pageMain.style.padding, overflow: pageMain.style.overflow, position: pageMain.style.position };
    const savedSlot = slot ? { overflow: slot.style.overflow, maxHeight: slot.style.maxHeight } : null;
    pageMain.style.padding = '0';
    pageMain.style.overflow = 'hidden';
    pageMain.style.position = 'relative';
    if (slot && slot !== pageMain) {
      slot.style.overflow = 'hidden';
      slot.style.maxHeight = '100%';
    }
    return {
      destroy() {
        pageMain.style.padding = savedMain.padding;
        pageMain.style.overflow = savedMain.overflow;
        pageMain.style.position = savedMain.position;
        if (slot && savedSlot) {
          slot.style.overflow = savedSlot.overflow;
          slot.style.maxHeight = savedSlot.maxHeight;
        }
      },
    };
  }
</script>

<!-- FULL-BLEED WORKSPACE: fills parent exactly. onMount strips the page layout <main> padding/overflow
     so this container occupies the entire remaining viewport without any scroll. -->
<div class="absolute inset-0 overflow-hidden bg-slate-100 select-none" use:bleedParent>
  <!-- Canvas fills the entire workspace; all UI floats above it -->
  <div class="absolute inset-0 overflow-hidden bg-slate-100">
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
        bind:headerFontSize
        bind:headerFontFamily
        bind:headerColor
        bind:headerAlign
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
        bind:instructionsFontSize
        bind:instructionsFontFamily
        bind:instructionsColor
        bind:instX
        bind:instY
        bind:instW
        bind:instH
        bind:instLocked
        {showSignatureOverlay}
        {signatureContent}
        bind:signatureFontSize
        bind:signatureFontFamily
        bind:signatureColor
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

  <!-- FLOATING TOP STEPPER BAR (Positioned over the canvas, right of Column 1) -->
  <div class="absolute top-4 left-[376px] right-4 flex items-center justify-between z-30 pointer-events-none gap-4">
      <!-- 4-Step Stepper Navigation -->
      <div class="pointer-events-auto bg-white/95 backdrop-blur-md p-1 rounded-2xl border border-slate-200/90 shadow-md flex items-center space-x-1 shrink-0">
        {#each stageTitles as title, idx}
          {@const stageNum = idx + 1}
          {@const isActive = currentStage === stageNum}
          {@const isPast = currentStage > stageNum}
          <button
            type="button"
            onclick={() => requestGoToStage(stageNum)}
            class={`px-3 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1.5 leading-none ${
              isActive
                ? 'bg-slate-900 text-white shadow-xs'
                : isPast
                  ? 'text-slate-700 hover:text-slate-900 hover:bg-slate-100'
                  : 'text-slate-400 hover:text-slate-600'
            }`}
          >
            <span class={`size-4 rounded-full flex items-center justify-center text-[10px] font-extrabold leading-none shrink-0 ${
              isActive ? 'bg-white text-slate-900' : isPast ? 'bg-slate-200 text-slate-800' : 'bg-slate-100 text-slate-400'
            }`}>
              {stageNum}
            </span>
            <span class="truncate">{title.split('.')[1]?.trim() || title}</span>
          </button>
        {/each}
      </div>

      <!-- Right Actions: Re-edit Design Button (Step 3) + Save Action -->
      <div class="pointer-events-auto flex items-center space-x-2 bg-white/95 backdrop-blur-md p-1.5 rounded-2xl border border-slate-200/90 shadow-md shrink-0">
        {#if currentStage === 3 && draft.raster_url}
          <button
            type="button"
            onclick={() => requestGoToStage(2)}
            class="px-2.5 h-7 rounded-xl text-xs font-semibold text-emerald-800 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 flex items-center gap-1 transition"
            title="Return to Step 2 to edit overlays or re-render page raster"
          >
            <RotateCcw class="size-3 text-emerald-600" />
            <span>Re-edit Design</span>
          </button>
        {/if}

        <div class="flex items-center space-x-1.5 text-xs px-2">
          {#if saveStatus === 'saving'}
            <Loader2 class="size-3.5 text-amber-500 animate-spin" />
            <span class="text-amber-600 font-mono text-[11px]">Saving…</span>
          {:else if saveStatus === 'saved'}
            <CheckCircle2 class="size-3.5 text-emerald-600" />
            <span class="text-emerald-600 font-mono text-[11px]">Saved</span>
          {:else if saveStatus === 'error'}
            <XCircle class="size-3.5 text-rose-600" />
            <span class="text-rose-600 font-mono text-[11px] max-w-[100px] truncate" title={errorMessage}>
              {errorMessage || 'Error'}
            </span>
          {/if}
        </div>

        <button
          type="button"
          onclick={performSave}
          class="px-3.5 h-7 bg-slate-900 hover:bg-slate-800 text-white text-xs font-semibold rounded-xl transition shadow-xs"
        >
          Save
        </button>
      </div>
    </div>

  <!-- FLOATING CANVAS CONTROLS (Bottom-left beside Column 1) -->
  <div class="absolute bottom-4 left-[376px] bg-white/95 backdrop-blur-md border border-slate-200 shadow-xl rounded-2xl p-1.5 flex items-center space-x-2 z-30 pointer-events-auto">
      <button
        type="button"
        onclick={() => { canvasLocked = !canvasLocked; }}
        class={`px-3 h-8 rounded-xl text-xs font-bold transition flex items-center gap-1.5 shadow-xs ${
          canvasLocked
            ? 'bg-amber-100 text-amber-900 border border-amber-300'
            : 'bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200'
        }`}
        title={canvasLocked ? 'Canvas viewport is locked (Scroll wheel & panning disabled)' : 'Canvas is interactive (Scroll wheel zooms, drag background to pan)'}
      >
        {#if canvasLocked}
          <Lock class="size-3.5 text-amber-700" />
          <span>Canvas Locked</span>
        {:else}
          <Unlock class="size-3.5 text-slate-600" />
          <span>Canvas Active</span>
        {/if}
      </button>

      <div class="h-4 w-px bg-slate-200"></div>

      <!-- Zoom In & Out Buttons -->
      <div class="flex items-center space-x-1 bg-slate-100 border border-slate-200 rounded-xl p-0.5">
        <button
          type="button"
          onclick={() => zoomStep(-0.15)}
          title="Zoom Out"
          class="w-7 h-7 rounded-lg flex items-center justify-center text-slate-600 hover:text-slate-900 hover:bg-white transition"
        >
          <Minus class="size-3.5" />
        </button>
        <span class="text-xs font-mono font-bold px-1.5 text-slate-800 min-w-[42px] text-center">
          {Math.round(scale * 100)}%
        </span>
        <button
          type="button"
          onclick={() => zoomStep(0.15)}
          title="Zoom In"
          class="w-7 h-7 rounded-lg flex items-center justify-center text-slate-600 hover:text-slate-900 hover:bg-white transition"
        >
          <Plus class="size-3.5" />
        </button>
      </div>

      <!-- Fit Button -->
      <button
        type="button"
        onclick={resetZoom}
        title="Reset Zoom to Fit A4 Sheet (78%)"
        class="px-2.5 h-8 rounded-xl text-xs font-bold text-slate-700 hover:text-slate-900 hover:bg-slate-100 transition flex items-center gap-1 border border-slate-200"
      >
        <Maximize2 class="size-3" />
        <span>Fit (78%)</span>
      </button>
    </div>

  <!-- COLUMN 1: FLOATING LEFT TOOL CARD (Positioned strictly inside top-4 to bottom-4) -->
  <aside class="absolute top-4 left-4 bottom-4 w-[350px] max-h-[calc(100%-2rem)] bg-white/95 backdrop-blur-md rounded-2xl border border-slate-200/90 shadow-2xl flex flex-col overflow-hidden z-20">
    <!-- Card Header -->
    <div class="h-11 border-b border-slate-200 px-4 flex items-center justify-between shrink-0 bg-white/80">
      <div class="flex items-center space-x-2">
        <div class="size-5 rounded bg-emerald-600 flex items-center justify-center text-white font-bold text-[10px]">
          N
        </div>
        <span class="text-xs font-bold text-slate-900 truncate max-w-[160px]">{draft.name || 'Template Studio'}</span>
        <span class="px-1.5 py-0.5 bg-slate-100 text-[9.5px] font-mono text-slate-600 rounded border border-slate-200">
          {draft.code || 'OMR-DRAFT'}
        </span>
      </div>
      <div class="text-[10.5px] font-mono text-slate-500">
        {totalQuestionsCount} Qs
      </div>
    </div>

    <!-- Scrollable Tool Content Body (Dedicated internal scroll) -->
    <div class="flex-1 overflow-y-auto min-h-0 p-4 space-y-4">
      {#if currentStage === 1}
        <!-- STEP 1: SOURCE SUBSTRATE & INGESTION -->
        <div class="space-y-4">
          <!-- 2-Mode Switcher Tabs (HTML / File Upload) -->
          <div class="grid grid-cols-2 gap-1.5 p-1 bg-slate-100 rounded-xl border border-slate-200">
            <button
              type="button"
              onclick={() => { stage1Mode = 'html'; baseType = 'html'; triggerAutosave(); }}
              class={`py-1.5 px-2 rounded-lg text-xs font-semibold transition text-center ${
                stage1Mode === 'html' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900'
              }`}
            >
              Native Builder
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
            <!-- Sub-Mode: Visual Component Designer vs Raw HTML Code -->
            <div class="space-y-3">
              <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-900">Answer Sheet Components</span>
                <div class="flex items-center bg-slate-100 p-0.5 rounded-lg border border-slate-200">
                  <button
                    type="button"
                    onclick={() => { designerSubTab = 'designer'; }}
                    class={`px-2 py-0.5 rounded-md text-[10.5px] font-semibold transition ${
                      designerSubTab === 'designer' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-500 hover:text-slate-800'
                    }`}
                  >
                    Visual Builder
                  </button>
                  <button
                    type="button"
                    onclick={() => { designerSubTab = 'raw_html'; }}
                    class={`px-2 py-0.5 rounded-md text-[10.5px] font-semibold transition ${
                      designerSubTab === 'raw_html' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-500 hover:text-slate-800'
                    }`}
                  >
                    HTML Code
                  </button>
                </div>
              </div>

              {#if designerSubTab === 'designer'}
                <!-- 1. Presets Selector -->
                <div class="p-2.5 bg-gradient-to-r from-emerald-50 to-teal-50 border border-emerald-200/80 rounded-xl space-y-1.5">
                  <div class="flex items-center justify-between text-xs font-bold text-emerald-950">
                    <span class="flex items-center gap-1">
                      <Sparkles class="size-3 text-emerald-600" />
                      <span>Exam Presets</span>
                    </span>
                    <span class="text-[9.5px] font-mono text-emerald-700">{totalQuestionsCount} Items</span>
                  </div>
                  <div class="grid grid-cols-3 gap-1 pt-0.5">
                    <button
                      type="button"
                      onclick={() => applyPreset('150q')}
                      class="py-1 px-1.5 bg-white hover:bg-emerald-100 border border-emerald-200 rounded-lg text-[10px] font-bold text-emerald-900 transition text-center shadow-2xs"
                    >
                      150-Q (6 Subj)
                    </button>
                    <button
                      type="button"
                      onclick={() => applyPreset('100q')}
                      class="py-1 px-1.5 bg-white hover:bg-emerald-100 border border-emerald-200 rounded-lg text-[10px] font-bold text-emerald-900 transition text-center shadow-2xs"
                    >
                      100-Q (4 Subj)
                    </button>
                    <button
                      type="button"
                      onclick={() => applyPreset('50q')}
                      class="py-1 px-1.5 bg-white hover:bg-emerald-100 border border-emerald-200 rounded-lg text-[10px] font-bold text-emerald-900 transition text-center shadow-2xs"
                    >
                      50-Q (2 Subj)
                    </button>
                  </div>
                </div>

                <!-- 2. Universal Components Accordion / Section Controls -->
                <div class="space-y-3">
                  <!-- A. Document Header Section -->
                  {#if headerComp}
                    <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl space-y-2.5 text-xs">
                      <div class="flex items-center justify-between font-bold text-slate-800">
                        <span class="flex items-center gap-1.5">
                          <Type class="size-3.5 text-blue-600" />
                          <span>Document Header</span>
                        </span>
                      </div>
                      <div class="space-y-1.5">
                        <input
                          type="text"
                          bind:value={headerComp.title}
                          oninput={syncComponentsToSections}
                          placeholder="Institution / Exam Title"
                          class="w-full px-2 py-1 bg-white border border-slate-200 rounded-lg text-xs font-semibold"
                        />
                        <div class="grid grid-cols-2 gap-2">
                          <input
                            type="text"
                            bind:value={headerComp.subtitle}
                            oninput={syncComponentsToSections}
                            placeholder="Subtitle"
                            class="w-full px-2 py-1 bg-white border border-slate-200 rounded-lg text-[11px]"
                          />
                          <input
                            type="text"
                            bind:value={headerComp.formCode}
                            oninput={syncComponentsToSections}
                            placeholder="Form Code"
                            class="w-full px-2 py-1 bg-white border border-slate-200 rounded-lg text-[11px] font-mono"
                          />
                        </div>
                      </div>
                    </div>
                  {/if}

                  <!-- B. Examinee ID Matrix (0–9 Digits) -->
                  {#if rollIdComp}
                    <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl space-y-2.5 text-xs">
                      <div class="flex items-center justify-between font-bold text-slate-800">
                        <span class="flex items-center gap-1.5">
                          <Sliders class="size-3.5 text-emerald-600" />
                          <span>Examinee ID Matrix</span>
                        </span>
                        <span class="text-[9.5px] font-mono text-emerald-700 bg-emerald-50 border border-emerald-200 px-1 py-0.5 rounded">0–9 Digits</span>
                      </div>
                      <div class="grid grid-cols-2 gap-2">
                        <div>
                          <label class="text-[10px] font-semibold text-slate-600 block mb-0.5">Digit Columns</label>
                          <input
                            type="number"
                            min="2"
                            max="10"
                            bind:value={rollIdComp.digitsCount}
                            oninput={syncComponentsToSections}
                            class="w-full px-2 py-1 bg-white border border-slate-200 rounded-lg text-xs font-semibold"
                          />
                        </div>
                        <div>
                          <label class="text-[10px] font-semibold text-slate-600 block mb-0.5">Matrix Title</label>
                          <input
                            type="text"
                            bind:value={rollIdComp.title}
                            oninput={syncComponentsToSections}
                            class="w-full px-2 py-1 bg-white border border-slate-200 rounded-lg text-xs"
                          />
                        </div>
                      </div>
                    </div>
                  {/if}

                  <!-- C. Candidate Details Strip -->
                  {#if candidateInfoComp}
                    <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl space-y-2 text-xs">
                      <div class="font-bold text-slate-800 flex items-center gap-1.5">
                        <FileText class="size-3.5 text-slate-600" />
                        <span>Candidate Info Fields</span>
                      </div>
                      <div class="space-y-1.5">
                        <input
                          type="text"
                          bind:value={candidateInfoComp.candidateNameLabel}
                          oninput={syncComponentsToSections}
                          placeholder="Candidate Name Label"
                          class="w-full px-2 py-1 bg-white border border-slate-200 rounded-lg text-[10.5px]"
                        />
                        <div class="grid grid-cols-2 gap-2">
                          <input
                            type="text"
                            bind:value={candidateInfoComp.birthdateLabel}
                            oninput={syncComponentsToSections}
                            placeholder="Birthdate Label"
                            class="w-full px-2 py-1 bg-white border border-slate-200 rounded-lg text-[10.5px]"
                          />
                          <input
                            type="text"
                            bind:value={candidateInfoComp.roomSeatLabel}
                            oninput={syncComponentsToSections}
                            placeholder="Room / Seat Label"
                            class="w-full px-2 py-1 bg-white border border-slate-200 rounded-lg text-[10.5px]"
                          />
                        </div>
                      </div>
                    </div>
                  {/if}

                  <!-- D. Subject Question Columns Overview -->
                  <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl space-y-2 text-xs">
                    <div class="flex items-center justify-between font-bold text-slate-800">
                      <span>Subject Sections ({questionGridComps.length} Columns)</span>
                      <span class="text-[9.5px] font-mono text-slate-500">{totalQuestionsCount} Qs Total</span>
                    </div>
                    <div class="space-y-1">
                      {#each questionGridComps as g, idx}
                        <div class="p-1.5 bg-white border border-slate-200 rounded-lg flex items-center justify-between text-[11px]">
                          <input
                            type="text"
                            bind:value={g.subjectName}
                            oninput={syncComponentsToSections}
                            class="font-semibold text-slate-800 bg-transparent border-none p-0 focus:ring-0 text-[11px] w-28"
                          />
                          <div class="flex items-center gap-1.5">
                            <span class="font-mono text-[10px] text-slate-500">Q{g.startQ}–{g.startQ + g.questionsCount - 1}</span>
                            <span class="px-1 py-0.5 text-[9px] bg-slate-100 rounded text-slate-700 font-bold">4 Opt</span>
                          </div>
                        </div>
                      {/each}
                    </div>
                  </div>
                </div>
              {:else}
                <!-- Raw HTML Code Editor -->
                <div class="space-y-2.5">
                  <div class="flex items-center justify-between">
                    <label class="text-xs font-bold text-slate-700">Compiled HTML Markup</label>
                    <button
                      type="button"
                      onclick={() => applyPreset('150q')}
                      class="text-[11px] font-semibold text-emerald-600 hover:text-emerald-800 flex items-center gap-1"
                    >
                      <Sparkles class="size-3" />
                      <span>Reset to 150-Q</span>
                    </button>
                  </div>
                  <textarea
                    bind:value={htmlCode}
                    oninput={triggerAutosave}
                    rows="12"
                    class="w-full font-mono text-[10px] leading-relaxed p-2.5 bg-slate-950 text-emerald-400 rounded-xl border border-slate-800 resize-y focus:outline-hidden focus:ring-2 focus:ring-emerald-500 shadow-inner"
                    placeholder="Paste HTML markup here…"
                  ></textarea>
                </div>
              {/if}
            </div>
          {:else}
            <!-- Option 2: Upload Document File -->
            <div class="space-y-3">
              <!-- 1-Click Sample Sheet Ingestion -->
              <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl space-y-2">
                <div class="flex items-center justify-between text-xs font-bold text-slate-800">
                  <span class="flex items-center gap-1.5">
                    <Sparkles class="size-3.5 text-emerald-600" />
                    <span>Standard Test Sheet File</span>
                  </span>
                  <span class="text-[9.5px] font-mono text-slate-500 font-normal">PDF / PNG</span>
                </div>
                <p class="text-[10.5px] text-slate-500 leading-relaxed">
                  Official 150-item evaluation sheet layout ready to import or download for manual testing.
                </p>
                <div class="flex flex-col gap-1.5 pt-0.5">
                  <button
                    type="button"
                    onclick={loadSampleUploadedDocument}
                    disabled={uploadRunning}
                    class="w-full py-1.5 px-2.5 bg-slate-900 hover:bg-slate-800 disabled:opacity-50 text-white rounded-lg text-xs font-semibold transition flex items-center justify-center gap-1.5 shadow-xs"
                  >
                    <Sparkles class="size-3 text-emerald-400" />
                    <span>Import Sample Test Sheet File</span>
                  </button>
                  <div class="flex items-center justify-between text-[10.5px] pt-1 text-slate-600 px-0.5">
                    <a
                      href="/samples/omr/standard-150q-answer-sheet.pdf"
                      download="standard-150q-answer-sheet.pdf"
                      class="text-blue-600 hover:text-blue-800 font-medium hover:underline flex items-center gap-1"
                    >
                      Download PDF
                    </a>
                    <span class="text-slate-300">&bull;</span>
                    <a
                      href="/samples/omr/standard-150q-answer-sheet.png"
                      download="standard-150q-answer-sheet.png"
                      class="text-blue-600 hover:text-blue-800 font-medium hover:underline flex items-center gap-1"
                    >
                      Download PNG
                    </a>
                  </div>
                </div>
              </div>

              <div class="border-2 border-dashed border-slate-300 rounded-2xl p-4 text-center hover:border-slate-400 bg-white transition">
                <Upload class="size-5 text-slate-400 mx-auto mb-1" />
                <p class="text-xs font-bold text-slate-700">Or Upload Custom Document</p>
                <p class="text-[10px] text-slate-400 mt-0.5">PDF, Word (.docx), or Image (PNG/JPG)</p>
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
                  class="mt-2 px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-semibold rounded-lg transition"
                >
                  Browse Computer
                </button>
              </div>
              {#if uploadRunning}
                <div class="p-2.5 bg-blue-50 border border-blue-200 rounded-xl flex items-center gap-2 text-xs text-blue-800">
                  <Loader2 class="size-3.5 animate-spin text-blue-600 shrink-0" />
                  <span>{conversionStatusText || 'Converting file…'}</span>
                </div>
              {/if}

              {#if conversionError}
                <div class="p-2.5 bg-red-50 border border-red-200 rounded-xl text-xs text-red-800">
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
          bind:headerFontFamily
          bind:headerColor
          bind:headerAlign
          bind:headerLocked
          bind:showQrOverlay
          bind:qrLocked
          bind:showInstructionsOverlay
          bind:instructionsContent
          bind:instructionsFontSize
          bind:instructionsFontFamily
          bind:instructionsColor
          bind:instLocked
          bind:showSignatureOverlay
          bind:signatureContent
          bind:signatureFontSize
          bind:signatureFontFamily
          bind:signatureColor
          bind:sigLocked
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

    <!-- Pinned Bottom Navigation Footer in Floating Card -->
    {#if currentStage === 1}
      <div class="shrink-0 p-3 border-t border-slate-200/80 bg-white/90">
        <button
          type="button"
          onclick={() => requestGoToStage(2)}
          class="w-full py-2 bg-slate-900 hover:bg-slate-800 text-white font-semibold rounded-xl text-xs transition flex items-center justify-center gap-1.5 shadow-xs"
        >
          <span>Continue to Layout & Markers</span>
          <ChevronRight class="size-3.5" />
        </button>
      </div>
    {/if}
  </aside>

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
