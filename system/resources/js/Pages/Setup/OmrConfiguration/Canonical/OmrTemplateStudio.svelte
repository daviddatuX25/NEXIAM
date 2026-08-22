<script>
  import { onMount, onDestroy } from 'svelte';
  import { router } from '@inertiajs/svelte';
  import { newIdempotencyKey, csrfToken } from '@/lib/utils';
  import { createStudioHistory } from '@/lib/studioHistory';
  import {
    emitNativeMapping,
    emitOverlayBoxes,
    contentBlockSizeMm,
  } from '@/lib/omrStudioGeometry';
  import {
    renderFullSheetHtml,
    PRESET_150Q_COMPONENTS,
    componentToSectionData,
    createComponent,
    componentBlockSizeMm,
    COMPONENT_PATH_FILTERS,
    paletteForPath,
    overlayPaletteForPath,
    COMPONENT_SUITE_PROMPT,
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
    Undo2,
    Redo2,
    FileText,
    Sliders,
    Type,
    AlignLeft,
    AlignCenter,
    AlignRight,
    Trash2,
  } from 'lucide-svelte';
  import StudioCanvas from './StudioCanvas.svelte';
  import StudioOverlayPanel from './StudioOverlayPanel.svelte';
  import StudioBubblePanel from './StudioBubblePanel.svelte';
  import StudioExportVerifyPanel from './StudioExportVerifyPanel.svelte';
  import StudioTypographyControls from './StudioTypographyControls.svelte';
  import StudioQrReservationControls from './StudioQrReservationControls.svelte';

  let {
    draft = {},
    marker_profiles = [],
    aptitude_areas = [],
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

  // Stage 1: Base Mode ('html' | 'upload'). Chosen at the entry gate before the canvas appears.
  let stage1Mode = $state((draft.source_kind === 'image' || draft.source_kind === 'pdf' || draft.source_kind === 'docx') ? 'upload' : 'html');
  let baseType = $state(draft.source_kind ?? 'html');
  let hasChosenMode = $state(Boolean(draft.authoring?.studio?.stage1Mode));
  let componentPalettePath = $derived(stage1Mode === 'html' ? 'sheet_designer' : 'upload_document');
  let componentPaletteFilter = $derived(COMPONENT_PATH_FILTERS[componentPalettePath]);

  // Stage 1: Sheet Designer Component State — starts BLANK; components come from the palette.
  let designerSubTab = $state('designer'); // 'designer' | 'raw_html'
  let components = $state([]);
  let selectedComponentId = $state(null);

  let selectedComponent = $derived(components.find((c) => c.id === selectedComponentId) ?? null);

  function htmlSheetSettings() {
    return {
      paperWidthMm: 210,
      paperHeightMm: 297,
      marginMm,
      markerPattern,
    };
  }

  function htmlDecorations() {
    return {
      showFiducials: true,
      qr: {
        visible: showQrOverlay,
        xMm: qrX,
        yMm: qrY,
        sizeMm: Math.min(Number(qrW) || 26, Number(qrH) || 26),
        overlayTag: 'blank_instance_qr',
      },
    };
  }

  function renderCurrentHtml() {
    return renderFullSheetHtml(components, htmlSheetSettings(), htmlDecorations());
  }
  function handleHtmlDecorationChanged() {
    if (components.length > 0) htmlCode = renderCurrentHtml();
    recordHistory('Edit layout', { coalesceKey: 'layout' });
    triggerAutosave();
  }

  function componentLabel(c) {
    switch (c.type) {
      case 'header': return 'Document Header';
      case 'candidate_name': return 'Candidate Name';
      case 'birthdate': return 'Birthdate';
      case 'room_seat': return 'Room / Seat';
      case 'instructions': return 'Instructions';
      case 'roll': return 'Examinee ID Matrix';
      case 'mcq': case 'tf': case 'likert': case 'custom': return c.subjectName || c.name || 'Question Grid';
      case 'custom_text': return 'Custom Text';
      case 'signature': return 'Signature Box';
      default: return c.type;
    }
  }

  function syncComponentsToSections(label = 'Edit component', options = {}) {
    htmlCode = components.length > 0 ? renderCurrentHtml() : '';
    sections = components
      .map((c) => componentToSectionData(c))
      .filter((s) => s !== null);
    const historyOptions = options.coalesceKey
      ? options
      : (label === 'Edit component' && selectedComponentId
        ? { coalesceKey: `component:${selectedComponentId}` }
        : {});
    recordHistory(label, historyOptions);
    triggerAutosave();
  }

  const PAGE_W = 210;
  const PAGE_H = 297;
  const MIN_INSET = 6;

  function addComponent(kind, point = null) {
    const comp = createComponent(kind);
    if (point && Number.isFinite(point.posX) && Number.isFinite(point.posY)) {
      comp.posX = Math.max(MIN_INSET, Math.min(PAGE_W - MIN_INSET - Number(comp.w_mm), point.posX));
      comp.posY = Math.max(MIN_INSET, Math.min(PAGE_H - MIN_INSET - Number(comp.h_mm), point.posY));
    } else {
      if (kind === 'question_grid') {
        const existingGrids = components.filter((c) => c.type === 'mcq' || c.type === 'tf' || c.type === 'likert' || c.type === 'custom');
        if (existingGrids.length > 0) {
          comp.posX = Math.min(PAGE_W - 15 - 28, 15 + existingGrids.length * 31);
          const nextStartQ = existingGrids.reduce((max, g) => Math.max(max, (Number(g.startQ) || 1) + (Number(g.questionsCount) || 25)), 1);
          comp.startQ = nextStartQ;
          comp.name = `Part ${existingGrids.length + 1}: Subject`;
          comp.subjectName = `${existingGrids.length + 1}. Subject`;
        }
      } else {
        const sameKindCount = components.filter((c) => c.type === comp.type).length;
        if (sameKindCount > 0) {
          comp.posX = Math.min(PAGE_W - 15 - Number(comp.w_mm || 40), comp.posX + ((sameKindCount * 8) % 40));
          comp.posY = Math.min(PAGE_H - 15 - Number(comp.h_mm || 12), comp.posY + ((sameKindCount * 8) % 40));
        }
      }
    }
    components = [...components, comp];
    selectedComponentId = comp.id;
    syncComponentsToSections(`Add ${componentLabel(comp)}`);
  }
  function addUploadTextOverlay(kind) {
    const labels = {
      candidate_name: 'CANDIDATE NAME',
      birthdate: 'BIRTHDATE (MM / DD / YYYY)',
      room_seat: 'ROOM / SEAT / GROUP NO.',
      custom_text: 'Additional Instruction / Note',
    };
    const nextId = customTextItems.length + 1;
    customTextItems = [
      ...customTextItems,
      {
        id: `custom_text_${nextId}`,
        name: `Text ${nextId}`,
        content: labels[kind] || labels.custom_text,
        fontSize: 10,
        fontFamily: 'Inter, sans-serif',
        color: '#0f172a',
        align: 'left',
        bold: false,
        italic: false,
        posX: 25,
        posY: Math.min(260, 230 + nextId * 10),
        w_mm: 80,
        h_mm: 15,
        isLocked: false,
      },
    ];
    recordHistory('Add overlay text');
    triggerAutosave();
  }

  function handlePaletteItem(item) {
    if (componentPalettePath === 'sheet_designer') {
      addComponent(item.kind);
      return;
    }

    if (item.kind === 'header') showHeaderOverlay = true;
    else if (item.kind === 'instructions') showInstructionsOverlay = true;
    else if (item.kind === 'signature') showSignatureOverlay = true;
    else {
      addUploadTextOverlay(item.kind);
      return;
    }
    recordHistory(`Enable ${item.label}`);
    triggerAutosave();
  }

  function handleOverlayPaletteItem(kind) {
    if (kind === 'qr_code') showQrOverlay = true;
    handleHtmlDecorationChanged();
  }

  function selectComponent(id) {
    selectedComponentId = id;
    const idx = sections.findIndex((s) => s.id === id);
    if (idx >= 0) activeSectionIndex = idx;
  }

  function removeComponent(id) {
    components = components.filter((c) => c.id !== id);
    if (selectedComponentId === id) selectedComponentId = null;
    syncComponentsToSections('Remove component');
  }

  function updateSelectedComponent(patch) {
    if (!selectedComponentId) return;
    components = components.map((c) => (c.id === selectedComponentId ? { ...c, ...patch } : c));
    syncComponentsToSections('Update component');
  }

  function handlePaletteDrop(e) {
    e.preventDefault();
    const kind = e.dataTransfer?.getData('application/x-omr-component') || window.__omrDragKind;
    if (!kind) return;
    // Convert the drop pointer position to sheet-local mm using the transformed sheet rect.
    const sheet = document.querySelector('[data-omr-sheet]');
    let point = null;
    if (sheet) {
      const rect = sheet.getBoundingClientRect();
      if (rect.width > 0 && rect.height > 0) {
        const px = ((e.clientX - rect.left) / rect.width) * 520;
        const py = ((e.clientY - rect.top) / rect.height) * 735;
        point = {
          posX: Math.round(px / 2.476 / 2) * 2,
          posY: Math.round(py / 2.476 / 2) * 2,
        };
      }
    }
    addComponent(kind, point);
  }

  function chooseMode(mode) {
    stage1Mode = mode;
    baseType = mode === 'html' ? 'html' : 'image';
    hasChosenMode = true;
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
    selectedComponentId = null;
    syncComponentsToSections(`Apply ${presetName} preset`);
  }

  function scaffoldFromAptitudeAreas() {
    if (!aptitude_areas || aptitude_areas.length === 0) return;
    const areas = aptitude_areas.slice(0, 6);
    let startQ = 1;
    const newGrids = areas.map((area, idx) => {
      const qCount = Math.min(50, Math.max(5, Number(area.max_items) || 25));
      const grid = {
        id: `Part_${idx + 1}`,
        name: `Part ${idx + 1}: ${area.name} (Q${startQ}–${startQ + qCount - 1})`,
        type: 'mcq',
        subjectName: `${idx + 1}. ${area.name}`,
        questionsCount: qCount,
        cols: 1,
        optionsCount: 4,
        startQ: startQ,
        customValuesStr: 'A, B, C, D',
        bubblePitchMm: 6.0,
        rowPitchMm: 6.8,
        labelStyle: 'column_header',
        posX: 10 + idx * 31,
        posY: 140,
        w_mm: 28,
        h_mm: 175,
        isLocked: false,
      };
      startQ += qCount;
      return grid;
    });

    const otherComps = components.filter((c) => c.type !== 'mcq' && c.type !== 'tf' && c.type !== 'likert' && c.type !== 'custom');
    components = [...otherComps, ...newGrids];
    syncComponentsToSections('Scaffold aptitude areas');
  }

  // HTML Code Editor State (blank until components are placed)
  let htmlCode = $state('');
  // Mode Switch Confirmation Modal State
  let showModeSwitchModal = $state(false);
  let pendingTargetMode = $state(null);

  function resetCanvasForModeSwitch() {
    draft.raster_url = null;
    components = [];
    sections = [];
    htmlCode = '';
    selectedComponentId = null;
    activeSectionIndex = 0;
    currentStage = 1;
    scale = 0.78;
    panX = 0;
    panY = 0;
    uploadRunning = false;
    conversionStatusText = '';
    conversionError = '';
    if (uploadFileInput) uploadFileInput.value = '';
    resetHistory();
  }

  function requestSwitchMode(targetMode) {
    if (targetMode === stage1Mode) return;
    const hasModified = draft.raster_url || components.length > 0 || uploadRunning;
    if (hasModified) {
      pendingTargetMode = targetMode;
      showModeSwitchModal = true;
      return;
    }
    resetCanvasForModeSwitch();
    stage1Mode = targetMode;
    baseType = targetMode === 'html' ? 'html' : 'image';
    triggerAutosave();
  }

  function confirmSwitchMode() {
    if (!pendingTargetMode) return;
    const nextMode = pendingTargetMode;
    resetCanvasForModeSwitch();
    stage1Mode = nextMode;
    baseType = nextMode === 'html' ? 'html' : 'image';
    showModeSwitchModal = false;
    pendingTargetMode = null;
    triggerAutosave();
  }


  // Direct Upload & Ingest State
  let uploadFileInput = $state(null);
  let uploadRunning = $state(false);
  let conversionStatusText = $state('');
  let conversionError = $state('');

  // Stage 3 Composition Loading
  let isComposing = $state(false);
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

  function readEditorState() {
    return {
      components: $state.snapshot(components),
      sections: $state.snapshot(sections),
      layout: {
        marginMm,
        markerPattern,
        showHeaderOverlay,
        headerTitle,
        headerSubtitle,
        headerFontSize,
        headerFontFamily,
        headerColor,
        headerAlign,
        headerX,
        headerY,
        headerW,
        headerH,
        headerLocked,
        showQrOverlay,
        qrX,
        qrY,
        qrW,
        qrH,
        qrLocked,
        showInstructionsOverlay,
        instructionsContent,
        instructionsFontSize,
        instructionsFontFamily,
        instructionsColor,
        instX,
        instY,
        instW,
        instH,
        instLocked,
        showSignatureOverlay,
        signatureContent,
        signatureFontSize,
        signatureFontFamily,
        signatureColor,
        sigX,
        sigY,
        sigW,
        sigH,
        sigLocked,
        metadataContent,
        metadataFontSize,
        showMetadataOverlay,
        showRollOverlay,
        rollDigits,
        customTextItems: $state.snapshot(customTextItems),
      },
    };
  }

  let historyRevision = $state(0);
  let historyNotice = $state('');
  const studioHistory = createStudioHistory(readEditorState(), 100);
  let historyStatus = $derived.by(() => {
    historyRevision;
    return studioHistory.status();
  });

  function recordHistory(label, options = {}) {
    if (studioHistory.record(readEditorState(), label, options)) {
      historyRevision += 1;
    }
  }

  function applyEditorState(nextState, notice = '') {
    components = nextState.components;
    sections = nextState.sections;
    const layout = nextState.layout;
    marginMm = layout.marginMm;
    markerPattern = layout.markerPattern;
    showHeaderOverlay = layout.showHeaderOverlay;
    headerTitle = layout.headerTitle;
    headerSubtitle = layout.headerSubtitle;
    headerFontSize = layout.headerFontSize;
    headerFontFamily = layout.headerFontFamily;
    headerColor = layout.headerColor;
    headerAlign = layout.headerAlign;
    headerX = layout.headerX;
    headerY = layout.headerY;
    headerW = layout.headerW;
    headerH = layout.headerH;
    headerLocked = layout.headerLocked;
    showQrOverlay = layout.showQrOverlay;
    qrX = layout.qrX;
    qrY = layout.qrY;
    qrW = layout.qrW;
    qrH = layout.qrH;
    qrLocked = layout.qrLocked;
    showInstructionsOverlay = layout.showInstructionsOverlay;
    instructionsContent = layout.instructionsContent;
    instructionsFontSize = layout.instructionsFontSize;
    instructionsFontFamily = layout.instructionsFontFamily;
    instructionsColor = layout.instructionsColor;
    instX = layout.instX;
    instY = layout.instY;
    instW = layout.instW;
    instH = layout.instH;
    instLocked = layout.instLocked;
    showSignatureOverlay = layout.showSignatureOverlay;
    signatureContent = layout.signatureContent;
    signatureFontSize = layout.signatureFontSize;
    signatureFontFamily = layout.signatureFontFamily;
    signatureColor = layout.signatureColor;
    sigX = layout.sigX;
    sigY = layout.sigY;
    sigW = layout.sigW;
    sigH = layout.sigH;
    sigLocked = layout.sigLocked;
    metadataContent = layout.metadataContent;
    metadataFontSize = layout.metadataFontSize;
    showMetadataOverlay = layout.showMetadataOverlay;
    showRollOverlay = layout.showRollOverlay;
    rollDigits = layout.rollDigits;
    customTextItems = layout.customTextItems;
    htmlCode = components.length > 0 ? renderCurrentHtml() : '';
    if (!components.some((component) => component.id === selectedComponentId)) selectedComponentId = null;
    activeSectionIndex = Math.min(activeSectionIndex, Math.max(0, sections.length - 1));
    historyRevision += 1;
    historyNotice = notice;
    triggerAutosave();
  }

  function undoEditor() {
    const state = studioHistory.undo();
    if (state) applyEditorState(state, `Undid ${studioHistory.status().redoLabel ?? 'edit'}`);
  }

  function redoEditor() {
    const state = studioHistory.redo();
    if (state) applyEditorState(state, `Redid ${studioHistory.status().undoLabel ?? 'edit'}`);
  }

  function resetHistory() {
    studioHistory.reset(readEditorState());
    historyRevision += 1;
    historyNotice = '';
  }

  function handleGeometryChanged() {
    recordHistory('Move or resize overlay', { coalesceKey: 'geometry' });
    triggerAutosave();
  }

  function recordBubbleEdit() {
    recordHistory('Edit question bubbles', { coalesceKey: `section:${activeSectionIndex}` });
    triggerAutosave();
  }
  function handleStudioKeydown(event) {
    if (event.repeat) return;
    if (!(event.ctrlKey || event.metaKey) || event.altKey || event.isComposing) return;
    const tagName = event.target?.tagName?.toLowerCase?.() ?? '';
    if (['input', 'textarea', 'select'].includes(tagName) || event.target?.isContentEditable) return;

    const key = event.key.toLowerCase();
    if (key === 'z' && !event.shiftKey) {
      event.preventDefault();
      undoEditor();
    } else if ((key === 'z' && event.shiftKey) || (key === 'y' && !event.shiftKey)) {
      event.preventDefault();
      redoEditor();
    }
  }

  onMount(() => {
    const studio = draft.authoring?.studio;
    if (studio) {
      if (typeof studio.current_stage === 'number') currentStage = studio.current_stage;
      if (studio.stage1Mode) stage1Mode = studio.stage1Mode;
      if (studio.baseType) baseType = studio.baseType;

      if (Array.isArray(studio.components) && studio.components.length > 0) {
        components = JSON.parse(JSON.stringify(studio.components));
        htmlCode = components.length > 0 ? renderFullSheetHtml(components) : '';
      } else if (Array.isArray(studio.sections) && studio.sections.length > 0) {
        sections = studio.sections;
      }
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

      if (studio.htmlCode && components.length === 0) htmlCode = studio.htmlCode;
    }

    if (components.length > 0) htmlCode = renderCurrentHtml();

    if (draft.lock_version !== undefined) {
      currentLockVersion = draft.lock_version;
    }
    resetHistory();
    window.addEventListener('keydown', handleStudioKeydown);

  });

  onDestroy(() => {
    if (autosaveTimer) clearTimeout(autosaveTimer);
    window.removeEventListener('keydown', handleStudioKeydown);
  });

  function requestGoToStage(step) {
    if ((step === 1 || step === 2) && currentStage >= 3 && draft.raster_url) {
      pendingTargetStage = step;
      showResetConfirmModal = true;
      return;
    }
    executeGoToStage(step);
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

      baseType = 'image';
      if (autosaveTimer) {
        clearTimeout(autosaveTimer);
        autosaveTimer = null;
      }
      if (!await performSave('image')) {
        throw new Error(errorMessage || 'Could not save the image source type.');
      }

      const form = new FormData();
      form.append('source_kind', 'image');
      form.append('source', file);
      form.append('width_mm', '210');
      form.append('height_mm', '297');
      form.append('idempotency_key', newIdempotencyKey());
      const uploadRes = await fetch(urls.source_ingest, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
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

  function syncSectionsFromComponentsForBubbleStage() {
    const nextSections = components
      .map((component) => componentToSectionData(component))
      .filter((section) => section !== null);

    if (nextSections.length > 0) {
      sections = nextSections;
      activeSectionIndex = Math.min(activeSectionIndex, nextSections.length - 1);
    }
  }

  async function executeGoToStage(step) {
    if (step === 3 && stage1Mode === 'html') {
      syncSectionsFromComponentsForBubbleStage();
    }
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
          x_mm: Number(s.posX) || 110,
          y_mm: Number(s.posY) || 28,
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

      const bPitch = Number(s.bubblePitchMm) || 6.0;
      const rPitch = Number(s.rowPitchMm) || 6.8;

      // Section bounds are content-derived, matching minimized canvas rectangle.
      const { w_mm, h_mm } = contentBlockSizeMm(
        opts,
        Number(s.questionsCount) || 25,
        bPitch,
        rPitch
      );

      return {
        id: s.id,
        type: s.type,
        x_mm: Number(s.posX) || 15,
        y_mm: Number(s.posY) || 84,
        w_mm,
        h_mm,
        questions: Number(s.questionsCount) || 25,
        cols: Number(s.cols) || 1,
        options: opts,
        start_q: Number(s.startQ) || 1,
        custom_values: s.type === 'custom' ? (s.customValuesStr || '').split(',').map((v) => v.trim()) : undefined,
        bubble_pitch_mm: bPitch,
        row_pitch_mm: rPitch,
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

  async function performSave(sourceKindOverride = null) {
    const saveUrl = urls.studio || `/setup/omr/studio/${draft.id}`;
    const persistedSourceKind = sourceKindOverride ?? baseType;

    try {
      const { blocks, overlayBlocks } = buildBlockList();
      const nativeMapping = emitNativeMapping(210, 297, blocks);
      const overlayBoxes = emitOverlayBoxes(overlayBlocks, 210, 297);

      const studioPayload = {
        current_stage: currentStage,
        sections,
        components,
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
        baseType: persistedSourceKind,
        palette_filter: componentPaletteFilter,
      };

      const res = await fetch(saveUrl, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrfToken(),
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({
          mapping: nativeMapping,
          overlay: overlayBoxes,
          studio: studioPayload,
          source_kind: persistedSourceKind,
          lock_version: currentLockVersion,
          idempotency_key: newIdempotencyKey(),
        }),
      });
      if (res.ok) {
        const data = await res.json();
        currentLockVersion = data.lock_version ?? currentLockVersion;
        draft.source_kind = persistedSourceKind;
        saveStatus = 'saved';
        errorMessage = '';
        if (onMappingSaved) onMappingSaved();
        return true;
      }

      const data = await res.json().catch(() => ({}));
      saveStatus = 'error';
      errorMessage = data.message || 'Atomic studio state save rejected.';
      return false;
    } catch (err) {
      saveStatus = 'error';
      errorMessage = err?.message || 'Network error while saving studio state.';
      return false;
    }
  }

  // File Ingestion Handler
  async function handleFileUpload(e) {
    const file = e.target.files?.[0];
    if (!file || !urls.source_ingest) return;

    uploadRunning = true;
    conversionStatusText = `Uploading ${file.name}…`;
    conversionError = '';

    const filename = file.name.toLowerCase();
    let detectedKind = 'image';
    if (filename.endsWith('.pdf')) detectedKind = 'pdf';
    else if (filename.endsWith('.docx')) detectedKind = 'docx';
    else if (filename.endsWith('.html') || filename.endsWith('.htm')) detectedKind = 'html';

    baseType = detectedKind;
    if (autosaveTimer) {
      clearTimeout(autosaveTimer);
      autosaveTimer = null;
    }
    const saved = await performSave(detectedKind);
    if (!saved) {
      uploadRunning = false;
      conversionError = errorMessage || 'Could not save the selected source type.';
      return;
    }

    const form = new FormData();
    form.append('source_kind', detectedKind);
    form.append('source', file);
    form.append('width_mm', '210');
    form.append('height_mm', '297');
    form.append('idempotency_key', newIdempotencyKey());

    try {
      const res = await fetch(urls.source_ingest, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
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
          if (run?.status === 'completed' || run?.status === 'succeeded') {
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
          headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
          body: JSON.stringify({
            pages: [{ page_no: 1, decision: 'confirmed' }],
          }),
        });
      } catch {}
    }

    uploadRunning = false;
    const continueToLayout = (page = null) => {
      const nextDraft = page?.props?.draft;
      currentLockVersion = nextDraft?.lock_version ?? draft.lock_version ?? currentLockVersion;
      executeGoToStage(2);
    };
    router.reload({
      only: ['draft'],
      onSuccess: continueToLayout,
      onError: continueToLayout,
    });
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
  <!-- ENTRY GATE: Choose how to start before any canvas or template UI appears -->
  {#if !hasChosenMode}
    <div class="absolute inset-0 z-[60] bg-slate-50 flex items-center justify-center p-6">
      <div class="w-full max-w-2xl space-y-6">
        <div class="text-center space-y-1.5">
          <h2 class="text-xl font-black text-slate-900">Start a New Template</h2>
          <p class="text-sm text-slate-500">Choose how you want to create this answer sheet. This choice defines your workflow.</p>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <button
            type="button"
            onclick={() => chooseMode('html')}
            class="group bg-white border-2 border-slate-200 hover:border-emerald-500 rounded-2xl p-6 text-left transition shadow-sm hover:shadow-md space-y-3"
          >
            <div class="size-11 rounded-xl bg-emerald-100 flex items-center justify-center text-emerald-700">
              <Sliders class="size-5" />
            </div>
            <div>
              <h3 class="text-sm font-black text-slate-900">Sheet Designer</h3>
              <p class="text-xs text-slate-500 mt-1 leading-relaxed">
                Build the answer sheet from scratch with the draggable component suite — headers, candidate fields, ID matrix, and question grids.
              </p>
            </div>
            <span class="text-[11px] font-bold text-emerald-700 group-hover:underline">Start designing →</span>
          </button>
          <button
            type="button"
            onclick={() => chooseMode('upload')}
            class="group bg-white border-2 border-slate-200 hover:border-blue-500 rounded-2xl p-6 text-left transition shadow-sm hover:shadow-md space-y-3"
          >
            <div class="size-11 rounded-xl bg-blue-100 flex items-center justify-center text-blue-700">
              <Upload class="size-5" />
            </div>
            <div>
              <h3 class="text-sm font-black text-slate-900">Upload Document</h3>
              <p class="text-xs text-slate-500 mt-1 leading-relaxed">
                Ingest an existing PDF, Word document, or scanned image, then overlay markers and OMR components on it.
              </p>
            </div>
            <span class="text-[11px] font-bold text-blue-700 group-hover:underline">Upload a file →</span>
          </button>
        </div>
      </div>
    </div>
  {/if}
  <div
    class="absolute inset-0 overflow-hidden bg-slate-100"
    ondragover={(e) => { e.preventDefault(); e.dataTransfer.dropEffect = 'copy'; }}
    ondrop={handlePaletteDrop}
  >
      <StudioCanvas
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
        bind:components
        bind:activeComponentId={selectedComponentId}
        onGeometryChanged={handleGeometryChanged}
        onComponentGeometryChanged={syncComponentsToSections}
      />
  </div>

  <!-- FLOATING TOP STEPPER BAR (Positioned over the canvas, right of Column 1) -->
  <div class="absolute top-4 left-[376px] right-4 flex items-center justify-between z-30 pointer-events-none gap-4">
      <!-- 4-Step Stepper Navigation -->
      <div class="pointer-events-auto bg-white/95 backdrop-blur-md p-1 rounded-2xl border border-slate-200/90 shadow-md flex items-center space-x-1 shrink-0">
        {#each (stage1Mode === 'html' ? ['1. Sheet Designer', '2. Question Bubbles', '3. Verify & Export'] : stageTitles) as title, idx}
          {@const stageNum = stage1Mode === 'html' ? (idx === 0 ? 1 : idx + 2) : idx + 1}
          {@const displayStep = idx + 1}
          {@const isActive = (stage1Mode === 'html' && ((idx === 0 && currentStage <= 2) || (idx === 1 && currentStage === 3) || (idx === 2 && currentStage === 4))) || (stage1Mode !== 'html' && currentStage === stageNum)}
          {@const isPast = stage1Mode === 'html' ? (idx === 0 && currentStage >= 3) || (idx === 1 && currentStage === 4) : currentStage > stageNum}
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
              {displayStep}
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
        <div class="flex items-center gap-0.5 border-r border-slate-200 pr-2">
          <button
            type="button"
            onclick={undoEditor}
            disabled={!historyStatus.canUndo}
            class="p-1.5 rounded-lg text-slate-600 hover:bg-slate-100 disabled:opacity-30 disabled:cursor-not-allowed transition"
            title={historyStatus.undoLabel ? `Undo: ${historyStatus.undoLabel}` : 'Undo'}
            aria-label={historyStatus.undoLabel ? `Undo: ${historyStatus.undoLabel}` : 'Undo'}
          >
            <Undo2 class="size-3.5" />
          </button>
          <button
            type="button"
            onclick={redoEditor}
            disabled={!historyStatus.canRedo}
            class="p-1.5 rounded-lg text-slate-600 hover:bg-slate-100 disabled:opacity-30 disabled:cursor-not-allowed transition"
            title={historyStatus.redoLabel ? `Redo: ${historyStatus.redoLabel}` : 'Redo'}
            aria-label={historyStatus.redoLabel ? `Redo: ${historyStatus.redoLabel}` : 'Redo'}
          >
            <Redo2 class="size-3.5" />
          </button>
        </div>

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
          {#if historyNotice}
            <span class="sr-only" aria-live="polite">{historyNotice}</span>
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

  <!-- FLOATING COMPONENT INSPECTOR (Top-Right of Canvas, Stage 1 Sheet Designer) -->
  {#if currentStage === 1 && stage1Mode === 'html' && designerSubTab === 'designer' && selectedComponent}
    <div class="absolute top-16 right-4 w-[320px] max-h-[calc(100%-8rem)] overflow-y-auto z-40 bg-white/95 backdrop-blur-md border border-emerald-200/90 shadow-2xl rounded-2xl p-3.5 space-y-3 pointer-events-auto text-xs">
      <!-- Header row -->
      <div class="flex items-center justify-between border-b border-slate-100 pb-2">
        <div class="flex items-center gap-1.5 min-w-0">
          <span class="size-2 rounded-full bg-emerald-500 shrink-0"></span>
          <span class="text-xs font-black text-slate-900 truncate">
            Inspector — {componentLabel(selectedComponent)}
          </span>
        </div>
        <div class="flex items-center gap-1 shrink-0">
          <button
            type="button"
            onclick={() => { selectedComponent.isLocked = !selectedComponent.isLocked; syncComponentsToSections(); }}
            class={`p-1 rounded transition text-xs font-semibold flex items-center gap-1 ${
              selectedComponent.isLocked ? 'text-amber-700 bg-amber-50' : 'text-slate-500 hover:text-slate-800 hover:bg-slate-100'
            }`}
            title={selectedComponent.isLocked ? 'Locked (click to unlock movement)' : 'Unlocked (click to lock)'}
          >
            {#if selectedComponent.isLocked}
              <Lock class="size-3 text-amber-600" />
            {:else}
              <Unlock class="size-3 text-slate-400" />
            {/if}
          </button>
          <button
            type="button"
            onclick={() => removeComponent(selectedComponent.id)}
            class="text-red-500 hover:text-red-700 p-1 hover:bg-red-50 rounded transition"
            title="Remove component"
          >
            <Trash2 class="size-3" />
          </button>
        </div>
      </div>

      <!-- Geometry Position & Size Row -->
      <div class="space-y-1 bg-slate-50/80 p-2 rounded-xl border border-slate-100">
        <span class="text-[9.5px] font-bold text-slate-500 uppercase tracking-wide block">Geometry (mm)</span>
        <div class="grid grid-cols-4 gap-1.5">
          <div>
            <label class="text-[9px] text-slate-500 block">X</label>
            <input
              type="number"
              bind:value={selectedComponent.posX}
              oninput={syncComponentsToSections}
              class="w-full px-1.5 py-0.5 bg-white border border-slate-200 rounded text-[11px] font-mono"
            />
          </div>
          <div>
            <label class="text-[9px] text-slate-500 block">Y</label>
            <input
              type="number"
              bind:value={selectedComponent.posY}
              oninput={syncComponentsToSections}
              class="w-full px-1.5 py-0.5 bg-white border border-slate-200 rounded text-[11px] font-mono"
            />
          </div>
          <div>
            <label class="text-[9px] text-slate-500 block">W</label>
            {#if selectedComponent.type === 'roll' || selectedComponent.type === 'mcq' || selectedComponent.type === 'tf' || selectedComponent.type === 'likert' || selectedComponent.type === 'custom'}
              <span class="w-full block px-1.5 py-0.5 bg-slate-100 border border-slate-200 rounded text-[11px] font-mono text-slate-500">
                {componentBlockSizeMm(selectedComponent).w_mm}
              </span>
            {:else}
              <input
                type="number"
                bind:value={selectedComponent.w_mm}
                oninput={syncComponentsToSections}
                class="w-full px-1.5 py-0.5 bg-white border border-slate-200 rounded text-[11px] font-mono"
              />
            {/if}
          </div>
          <div>
            <label class="text-[9px] text-slate-500 block">H</label>
            {#if selectedComponent.type === 'roll' || selectedComponent.type === 'mcq' || selectedComponent.type === 'tf' || selectedComponent.type === 'likert' || selectedComponent.type === 'custom'}
              <span class="w-full block px-1.5 py-0.5 bg-slate-100 border border-slate-200 rounded text-[11px] font-mono text-slate-500">
                {componentBlockSizeMm(selectedComponent).h_mm}
              </span>
            {:else}
              <input
                type="number"
                bind:value={selectedComponent.h_mm}
                oninput={syncComponentsToSections}
                class="w-full px-1.5 py-0.5 bg-white border border-slate-200 rounded text-[11px] font-mono"
              />
            {/if}
          </div>
        </div>
      </div>

      <!-- Type-specific fields -->
      {#if selectedComponent.type === 'header'}
        <div class="space-y-2">
          <div>
            <label class="text-[10px] font-semibold text-slate-600 block mb-0.5">Title</label>
            <input
              type="text"
              bind:value={selectedComponent.title}
              oninput={syncComponentsToSections}
              placeholder="Institution / Exam Title"
              class="w-full px-2 py-1 bg-white border border-slate-200 rounded-lg text-xs font-semibold"
            />
          </div>
          <div class="grid grid-cols-2 gap-2">
            <div>
              <label class="text-[10px] font-semibold text-slate-600 block mb-0.5">Subtitle</label>
              <input
                type="text"
                bind:value={selectedComponent.subtitle}
                oninput={syncComponentsToSections}
                placeholder="Subtitle"
                class="w-full px-2 py-1 bg-white border border-slate-200 rounded-lg text-[11px]"
              />
            </div>
            <div>
              <label class="text-[10px] font-semibold text-slate-600 block mb-0.5">Form Code</label>
              <input
                type="text"
                bind:value={selectedComponent.formCode}
                oninput={syncComponentsToSections}
                placeholder="Form Code"
                class="w-full px-2 py-1 bg-white border border-slate-200 rounded-lg text-[11px] font-mono"
              />
            </div>
          </div>
          <StudioTypographyControls bind:typography={selectedComponent.typography} onChanged={syncComponentsToSections} />
        </div>

      {:else if selectedComponent.type === 'candidate_name' || selectedComponent.type === 'birthdate' || selectedComponent.type === 'room_seat'}
        <div class="space-y-2">
          <div>
            <label class="text-[10px] font-semibold text-slate-600 block mb-0.5">Field Label</label>
            <input
              type="text"
              bind:value={selectedComponent.label}
              oninput={syncComponentsToSections}
              placeholder="Label text"
              class="w-full px-2 py-1 bg-white border border-slate-200 rounded-lg text-[11px]"
            />
          </div>
          <StudioTypographyControls bind:typography={selectedComponent.typography} onChanged={syncComponentsToSections} />
        </div>

      {:else if selectedComponent.type === 'instructions'}
        <div class="space-y-2">
          <div>
            <label class="text-[10px] font-semibold text-slate-600 block mb-0.5">Notice Text</label>
            <textarea
              bind:value={selectedComponent.content}
              oninput={syncComponentsToSections}
              rows="3"
              class="w-full px-2 py-1 bg-white border border-slate-200 rounded-lg text-[11px] leading-relaxed"
              placeholder="Instructions text…"
            ></textarea>
          </div>
          <div class="grid grid-cols-2 gap-2">
            <div>
              <label class="text-[10px] font-semibold text-slate-600 block mb-0.5">Border Color</label>
              <input
                type="color"
                bind:value={selectedComponent.borderColor}
                oninput={syncComponentsToSections}
                class="w-full h-7 p-0.5 bg-white border border-slate-200 rounded cursor-pointer"
              />
            </div>
            <div>
              <label class="text-[10px] font-semibold text-slate-600 block mb-0.5">Background</label>
              <input
                type="color"
                bind:value={selectedComponent.backgroundColor}
                oninput={syncComponentsToSections}
                class="w-full h-7 p-0.5 bg-white border border-slate-200 rounded cursor-pointer"
              />
            </div>
          </div>
          <StudioTypographyControls bind:typography={selectedComponent.typography} onChanged={syncComponentsToSections} />
        </div>

      {:else if selectedComponent.type === 'roll'}
        <div class="space-y-2">
          <div class="grid grid-cols-2 gap-2">
            <div>
              <label class="text-[10px] font-semibold text-slate-600 block mb-0.5">Digit Columns</label>
              <input
                type="number"
                min="2"
                max="10"
                bind:value={selectedComponent.digitsCount}
                oninput={syncComponentsToSections}
                class="w-full px-2 py-1 bg-white border border-slate-200 rounded-lg text-xs font-semibold"
              />
            </div>
            <div>
              <label class="text-[10px] font-semibold text-slate-600 block mb-0.5">Matrix Title</label>
              <input
                type="text"
                bind:value={selectedComponent.title}
                oninput={syncComponentsToSections}
                class="w-full px-2 py-1 bg-white border border-slate-200 rounded-lg text-xs"
              />
            </div>
          </div>
          <div class="grid grid-cols-2 gap-2 pt-1">
            <div>
              <label class="text-[10px] font-semibold text-slate-600 block mb-0.5">Bubble Pitch (mm)</label>
              <input
                type="number"
                min="4.5"
                max="15"
                step="0.1"
                bind:value={selectedComponent.bubblePitchMm}
                oninput={syncComponentsToSections}
                class="w-full px-2 py-1 bg-white border border-slate-200 rounded-lg text-xs"
              />
            </div>
            <div>
              <label class="text-[10px] font-semibold text-slate-600 block mb-0.5">Row Pitch (mm)</label>
              <input
                type="number"
                min="3"
                max="15"
                step="0.1"
                bind:value={selectedComponent.rowPitchMm}
                oninput={syncComponentsToSections}
                class="w-full px-2 py-1 bg-white border border-slate-200 rounded-lg text-xs"
              />
            </div>
          </div>
        </div>

      {:else if selectedComponent.type === 'mcq' || selectedComponent.type === 'tf' || selectedComponent.type === 'likert' || selectedComponent.type === 'custom'}
        <div class="space-y-2">
          <div>
            <label class="text-[10px] font-semibold text-slate-600 block mb-0.5">Subject Section Name</label>
            <input
              type="text"
              bind:value={selectedComponent.subjectName}
              oninput={syncComponentsToSections}
              placeholder="Subject name"
              class="w-full px-2 py-1 bg-white border border-slate-200 rounded-lg text-xs font-semibold"
            />
          </div>
          <div class="grid grid-cols-2 gap-2">
            <div>
              <label class="text-[10px] font-semibold text-slate-600 block mb-0.5">Questions Count</label>
              <input
                type="number"
                min="1"
                max="60"
                bind:value={selectedComponent.questionsCount}
                oninput={syncComponentsToSections}
                class="w-full px-2 py-1 bg-white border border-slate-200 rounded-lg text-xs"
              />
            </div>
            <div>
              <label class="text-[10px] font-semibold text-slate-600 block mb-0.5">Bubble Pitch (mm)</label>
              <input
                type="number"
                min="4.5"
                max="25"
                step="0.1"
                bind:value={selectedComponent.bubblePitchMm}
                oninput={syncComponentsToSections}
                class="w-full px-2 py-1 bg-white border border-slate-200 rounded-lg text-xs"
              />
            </div>
            <div>
              <label class="text-[10px] font-semibold text-slate-600 block mb-0.5">Options Count</label>
              <input
                type="number"
                min="2"
                max="5"
                bind:value={selectedComponent.optionsCount}
                oninput={syncComponentsToSections}
                class="w-full px-2 py-1 bg-white border border-slate-200 rounded-lg text-xs"
              />
            </div>
            <div>
              <label class="text-[10px] font-semibold text-slate-600 block mb-0.5">Start Q#</label>
              <input
                type="number"
                min="1"
                bind:value={selectedComponent.startQ}
                oninput={syncComponentsToSections}
                class="w-full px-2 py-1 bg-white border border-slate-200 rounded-lg text-xs"
              />
            </div>
            <div>
              <label class="text-[10px] font-semibold text-slate-600 block mb-0.5">Row Pitch (mm)</label>
              <input
                type="number"
                min="3"
                step="0.1"
                bind:value={selectedComponent.rowPitchMm}
                oninput={syncComponentsToSections}
                class="w-full px-2 py-1 bg-white border border-slate-200 rounded-lg text-xs"
              />
            </div>
          </div>
          {#if aptitude_areas && aptitude_areas.length > 0}
            <div>
              <label class="text-[10px] font-semibold text-slate-600 block mb-0.5">Bind System Aptitude Area</label>
              <select
                class="w-full text-[10.5px] bg-slate-50 border border-slate-200 rounded-lg px-2 py-1 text-slate-700"
                onchange={(e) => {
                  const area = aptitude_areas.find((a) => a.code === e.target.value);
                  if (area) {
                    updateSelectedComponent({ subjectName: area.name, questionsCount: Number(area.max_items) || 25 });
                  }
                }}
              >
                <option value="">-- Choose Aptitude Area --</option>
                {#each aptitude_areas as area}
                  <option value={area.code}>{area.name} ({area.max_items} Items)</option>
                {/each}
              </select>
            </div>
          {/if}
        </div>

      {:else if selectedComponent.type === 'custom_text'}
        <div class="space-y-2">
          <div>
            <label class="text-[10px] font-semibold text-slate-600 block mb-0.5">Text Content</label>
            <input
              type="text"
              bind:value={selectedComponent.text}
              oninput={syncComponentsToSections}
              placeholder="Custom text…"
              class="w-full px-2 py-1 bg-white border border-slate-200 rounded-lg text-[11px]"
            />
          </div>
          <StudioTypographyControls bind:typography={selectedComponent.typography} onChanged={syncComponentsToSections} />
        </div>

      {:else if selectedComponent.type === 'signature'}
        <div class="space-y-2">
          <div>
            <label class="text-[10px] font-semibold text-slate-600 block mb-0.5">Title Label</label>
            <input
              type="text"
              bind:value={selectedComponent.title}
              oninput={syncComponentsToSections}
              placeholder="Signature label"
              class="w-full px-2 py-1 bg-white border border-slate-200 rounded-lg text-[11px]"
            />
          </div>
          <div>
            <label class="text-[10px] font-semibold text-slate-600 block mb-0.5">Certification Text</label>
            <input
              type="text"
              bind:value={selectedComponent.certificationText}
              oninput={syncComponentsToSections}
              placeholder="Certification text"
              class="w-full px-2 py-1 bg-white border border-slate-200 rounded-lg text-[11px]"
            />
          </div>
          <StudioTypographyControls bind:typography={selectedComponent.typography} onChanged={syncComponentsToSections} />
        </div>
      {/if}
    </div>
  {/if}

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
          <!-- Creation Mode Indicator (deliberate change only, not an easy switch) -->
          <div class="flex items-center justify-between p-2.5 bg-slate-50 border border-slate-200 rounded-xl">
            <div class="flex items-center gap-2">
              <span class={`px-2 py-1 rounded-lg text-[10.5px] font-bold ${
                stage1Mode === 'html' ? 'bg-emerald-100 text-emerald-800' : 'bg-blue-100 text-blue-800'
              }`}>
                {stage1Mode === 'html' ? 'Sheet Designer' : 'Substrate Ingest'}
              </span>
              <span class="text-[10px] text-slate-500">
                {stage1Mode === 'html' ? 'Build from scratch with components' : 'Overlay on an uploaded document'}
              </span>
            </div>
            <button
              type="button"
              onclick={() => requestSwitchMode(stage1Mode === 'html' ? 'upload' : 'html')}
              class="text-[10.5px] font-semibold text-slate-600 hover:text-slate-900 underline underline-offset-2"
            >
              Change mode
            </button>
          </div>

          {#if stage1Mode === 'html'}
            <!-- Sub-Mode: Visual Designer vs Compiled HTML -->
            <div class="flex items-center bg-slate-100 p-0.5 rounded-lg border border-slate-200">
              <button
                type="button"
                onclick={() => { designerSubTab = 'designer'; }}
                class={`flex-1 py-1 rounded-md text-[10.5px] font-semibold transition ${
                  designerSubTab === 'designer' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-500 hover:text-slate-800'
                }`}
              >
                Visual Designer
              </button>
              <button
                type="button"
                onclick={() => { designerSubTab = 'raw_html'; }}
                class={`flex-1 py-1 rounded-md text-[10.5px] font-semibold transition ${
                  designerSubTab === 'raw_html' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-500 hover:text-slate-800'
                }`}
              >
                Compiled HTML
              </button>
            </div>

            {#if designerSubTab === 'designer'}
              <div class="space-y-3">
                <!-- 1. Draggable Component Palette -->
                <div>
                  <div class="flex items-center justify-between mb-1.5">
                    <span class="text-xs font-bold text-slate-900">Component Palette</span>
                    <span class="text-[9.5px] text-slate-400">Drag onto canvas or click to add</span>
                  </div>
                  <div class="grid grid-cols-2 gap-1.5">
                    {#each paletteForPath(componentPalettePath) as item}
                      <button
                        type="button"
                        draggable={componentPaletteFilter.includeQuestionComponents || (item.kind !== 'roll' && item.kind !== 'question_grid')}
                        ondragstart={(e) => {
                          window.__omrDragKind = item.kind;
                          try {
                            e.dataTransfer?.setData('application/x-omr-component', item.kind);
                            e.dataTransfer.effectAllowed = 'copy';
                          } catch {}
                        }}
                        onclick={() => handlePaletteItem(item)}
                        class="text-left p-2 bg-white border border-slate-200 hover:border-emerald-400 hover:bg-emerald-50/40 rounded-lg transition cursor-grab active:cursor-grabbing"
                        title={item.description}
                      >
                        <span class="block text-[10.5px] font-bold text-slate-800 leading-tight">{item.label}</span>
                        <span class="block text-[9px] text-slate-400 mt-0.5 leading-snug">{item.description}</span>
                      </button>
                    {/each}
                  </div>
                </div>

                <!-- Shared overlay palette: filtered by authoring path -->
                <div class="border-t border-slate-200/80 pt-3">
                  <div class="flex items-center justify-between mb-1.5">
                    <span class="text-xs font-bold text-slate-900">Shared Overlay Palette</span>
                    <span class="text-[9.5px] text-slate-400">Path: {componentPalettePath === 'sheet_designer' ? 'Sheet' : 'Upload'}</span>
                  </div>
                  <div class="grid grid-cols-3 gap-1.5">
                    {#each overlayPaletteForPath(componentPalettePath) as item}
                      <button
                        type="button"
                        onclick={() => handleOverlayPaletteItem(item.kind)}
                        class="text-left p-2 bg-white border border-slate-200 hover:border-blue-400 hover:bg-blue-50/40 rounded-lg transition"
                        title={item.description}
                      >
                        <span class="block text-[10px] font-bold text-slate-800 leading-tight">{item.label}</span>
                        <span class="block text-[8.5px] text-slate-400 mt-0.5 leading-snug">{item.description}</span>
                      </button>
                    {/each}
                  </div>
                </div>

                <!-- Shared layout controls: margins, markers, and QR -->
                <div class="border-t border-slate-200/80 pt-3 space-y-3">
                  <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-800 uppercase tracking-wider">Sheet Layout</span>
                    <span class="text-[9.5px] text-slate-400">Saved with this path</span>
                  </div>
                  <div class="space-y-1.5 p-2.5 bg-slate-50 rounded-xl border border-slate-200">
                    <div class="flex justify-between text-[11px] font-semibold text-slate-700">
                      <span>Margin Inset</span>
                      <span class="font-mono bg-white border px-1.5 py-0.5 rounded text-[10px]">{marginMm} mm</span>
                    </div>
                    <input
                      type="range"
                      min="8"
                      max="30"
                      step="1"
                      bind:value={marginMm}
                      oninput={handleHtmlDecorationChanged}
                      class="w-full accent-slate-900"
                    />
                  </div>
                  <div class="space-y-1.5">
                    <span class="text-[11px] font-bold text-slate-700 block">Fiducial Markers</span>
                    <div class="grid grid-cols-3 gap-1">
                      {#each ['square', 'target', 'aruco'] as pat}
                        <button
                          type="button"
                          onclick={() => { markerPattern = pat; handleHtmlDecorationChanged(); }}
                          class={`py-1 rounded-lg border text-center text-[10px] font-semibold capitalize transition ${
                            markerPattern === pat ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 text-slate-600 bg-white hover:border-slate-300'
                          }`}
                        >{pat}</button>
                      {/each}
                    </div>
                  </div>
                  <div class="p-2.5 bg-slate-50 rounded-xl border border-slate-200">
                    <StudioQrReservationControls
                      bind:showQrOverlay
                      bind:qrX
                      bind:qrY
                      bind:qrW
                      bind:qrH
                      bind:qrLocked
                      onChanged={handleHtmlDecorationChanged}
                    />
                  </div>
                </div>

                <!-- 2. Quick Scaffolds -->
                <div class="p-2.5 bg-gradient-to-r from-emerald-50 to-teal-50 border border-emerald-200/80 rounded-xl space-y-1.5">
                  <div class="flex items-center justify-between text-xs font-bold text-emerald-950">
                    <span class="flex items-center gap-1">
                      <Sparkles class="size-3 text-emerald-600" />
                      <span>Quick Scaffolds</span>
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
                  {#if aptitude_areas && aptitude_areas.length > 0}
                    <button
                      type="button"
                      onclick={scaffoldFromAptitudeAreas}
                      class="w-full py-1 px-2 bg-white hover:bg-emerald-100 border border-emerald-200 rounded-lg text-[10.5px] font-semibold text-emerald-900 transition flex items-center justify-center gap-1"
                    >
                      <Sparkles class="size-3 text-emerald-600" />
                      <span>Scaffold from System Aptitude Areas ({aptitude_areas.length})</span>
                    </button>
                  {/if}
                </div>

                <!-- 3. Placed Components List -->
                <div>
                  <div class="flex items-center justify-between mb-1.5">
                    <span class="text-xs font-bold text-slate-900">Sheet Components ({components.length})</span>
                  </div>
                  {#if components.length === 0}
                    <div class="p-4 border-2 border-dashed border-slate-300 rounded-xl text-center text-[11px] text-slate-400 leading-relaxed">
                      No components yet.<br />
                      Drag items from the palette onto the canvas — or click one to add it.
                    </div>
                  {:else}
                    <div class="space-y-1">
                      {#each components as c}
                        <div
                          class={`p-2 rounded-lg border flex items-center justify-between cursor-pointer text-[11px] transition ${
                            selectedComponentId === c.id
                              ? 'border-emerald-500 bg-emerald-50 ring-1 ring-emerald-400'
                              : 'border-slate-200 bg-white hover:border-slate-300'
                          }`}
                          onclick={() => selectComponent(c.id)}
                          role="button"
                          tabindex="0"
                        >
                          <span class="font-bold text-slate-800">{componentLabel(c)}</span>
                          <div class="flex items-center gap-1">
                            <button
                              type="button"
                              onclick={(e) => { e.stopPropagation(); c.isLocked = !c.isLocked; syncComponentsToSections(); }}
                              class={`p-1 rounded transition ${c.isLocked ? 'text-amber-600 bg-amber-50' : 'text-slate-400 hover:text-slate-600'}`}
                              title={c.isLocked ? 'Unlock position' : 'Lock position'}
                            >
                              {#if c.isLocked}<Lock class="size-3" />{:else}<Unlock class="size-3" />{/if}
                            </button>
                            <button
                              type="button"
                              onclick={(e) => { e.stopPropagation(); removeComponent(c.id); }}
                              class="p-1 rounded text-slate-400 hover:text-red-600 hover:bg-red-50 transition"
                              title="Remove component"
                            >
                              <Trash2 class="size-3" />
                            </button>
                          </div>
                        </div>
                      {/each}
                    </div>
                  {/if}
                </div>

              </div>
            {:else}
              <!-- Compiled HTML + Suite Reference Export -->
              <div class="space-y-2.5">
                <div class="flex items-center justify-between">
                  <label class="text-xs font-bold text-slate-700">Compiled HTML Markup</label>
                  <button
                    type="button"
                    onclick={() => navigator.clipboard?.writeText(htmlCode || '')}
                    class="text-[11px] font-semibold text-emerald-600 hover:text-emerald-800 flex items-center gap-1"
                  >
                    <Check class="size-3" />
                    <span>Copy</span>
                  </button>
                </div>
                <textarea
                  readonly
                  bind:value={htmlCode}
                  rows="9"
                  class="w-full font-mono text-[10px] leading-relaxed p-2.5 bg-slate-950 text-emerald-400 rounded-xl border border-slate-800 resize-y"
                  placeholder="Compiled HTML appears here as you add components."
                ></textarea>

                <div class="flex items-center justify-between pt-1">
                  <label class="text-xs font-bold text-slate-700">Suite Reference (HTML / CSS + AI Prompt)</label>
                  <button
                    type="button"
                    onclick={() => navigator.clipboard?.writeText(COMPONENT_SUITE_PROMPT)}
                    class="text-[11px] font-semibold text-blue-600 hover:text-blue-800 flex items-center gap-1"
                  >
                    <Check class="size-3" />
                    <span>Copy</span>
                  </button>
                </div>
                <textarea
                  readonly
                  value={COMPONENT_SUITE_PROMPT}
                  rows="7"
                  class="w-full font-mono text-[9px] leading-relaxed p-2.5 bg-slate-100 text-slate-700 rounded-xl border border-slate-300 resize-y"
                  placeholder="Suite definition…"
                ></textarea>
                <p class="text-[10px] text-slate-400 leading-relaxed">
                  Copy the suite reference to reproduce this component system in another interface or paste it into an AI assistant.
                </p>
              </div>
            {/if}
          {:else}
            <!-- Option 2: Upload Document File -->
            <div class="space-y-3">
              <!-- Shared palette filtered for substrate overlays: no bubbles or question grids -->
              <div class="p-3 bg-white border border-slate-200 rounded-xl space-y-2">
                <div class="flex items-center justify-between">
                  <span class="text-xs font-bold text-slate-900">Overlay Palette</span>
                  <span class="text-[9.5px] text-slate-400">Questions are configured later</span>
                </div>
                <div class="grid grid-cols-2 gap-1.5">
                  {#each paletteForPath(componentPalettePath) as item}
                    <button
                      type="button"
                      onclick={() => handlePaletteItem(item)}
                      class="text-left p-2 bg-white border border-slate-200 hover:border-blue-400 hover:bg-blue-50/40 rounded-lg transition"
                      title={item.description}
                    >
                      <span class="block text-[10px] font-bold text-slate-800 leading-tight">{item.label}</span>
                      <span class="block text-[8.5px] text-slate-400 mt-0.5 leading-snug">{item.description}</span>
                    </button>
                  {/each}
                </div>
                <div class="grid grid-cols-3 gap-1.5">
                  {#each overlayPaletteForPath(componentPalettePath) as item}
                    <button
                      type="button"
                      onclick={() => handleOverlayPaletteItem(item.kind)}
                      class="text-left p-2 bg-slate-50 border border-slate-200 hover:border-blue-400 hover:bg-blue-50/40 rounded-lg transition"
                      title={item.description}
                    >
                      <span class="block text-[9.5px] font-bold text-slate-800 leading-tight">{item.label}</span>
                    </button>
                  {/each}
                </div>
                <button
                  type="button"
                  onclick={() => requestGoToStage(2)}
                  class="w-full py-1.5 bg-slate-900 hover:bg-slate-800 text-white rounded-lg text-[10.5px] font-semibold transition"
                >
                  Open Layout &amp; Overlays
                </button>
              </div>
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
          bind:qrX
          bind:qrY
          bind:qrW
          bind:qrH
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
          onChanged={handleHtmlDecorationChanged}
          onBack={() => requestGoToStage(1)}
          onNext={() => requestGoToStage(3)}
        />
      {:else if currentStage === 3}
        <!-- STEP 3: QUESTION BUBBLES & PITCH GEOMETRY -->
        <StudioBubblePanel
          bind:sections
          bind:activeSectionIndex
          onChanged={recordBubbleEdit}
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
          onclick={() => requestGoToStage(stage1Mode === 'html' ? 3 : 2)}
          class="w-full py-2 bg-slate-900 hover:bg-slate-800 text-white font-semibold rounded-xl text-xs transition flex items-center justify-center gap-1.5 shadow-xs"
        >
          <span>{stage1Mode === 'html' ? 'Continue to Question Bubbles' : 'Continue to Layout & Markers'}</span>
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

  <!-- Mode Switch Confirmation Modal -->
  {#if showModeSwitchModal}
    <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 z-50">
      <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200 space-y-4">
        <div class="flex items-center space-x-3">
          <div class="size-10 rounded-xl bg-amber-100 flex items-center justify-center text-amber-700 shrink-0">
            <AlertTriangle class="size-5" />
          </div>
          <div>
            <h3 class="text-sm font-bold text-slate-900">Change creation mode?</h3>
            <p class="text-xs text-slate-500 mt-0.5">
              Switching to {pendingTargetMode === 'html' ? 'Sheet Designer' : 'Upload Document'} will clear the current sheet layout.
            </p>
          </div>
        </div>

        <p class="text-xs text-slate-600 leading-relaxed bg-slate-50 p-3 rounded-xl border border-slate-100">
          Sheet Designer builds the answer sheet from scratch with draggable OMR components. Upload Document ingests an existing PDF, Word file, or image for overlay work.
        </p>

        <div class="flex items-center justify-end space-x-2 pt-2">
          <button
            type="button"
            onclick={() => { showModeSwitchModal = false; pendingTargetMode = null; }}
            class="px-3.5 py-2 rounded-xl text-xs font-semibold text-slate-700 hover:bg-slate-100 transition"
          >
            Cancel
          </button>
          <button
            type="button"
            onclick={confirmSwitchMode}
            class="px-4 py-2 rounded-xl text-xs font-semibold bg-amber-600 hover:bg-amber-700 text-white shadow-xs transition"
          >
            Yes, Switch Mode
          </button>
        </div>
      </div>
    </div>
  {/if}
</div>
