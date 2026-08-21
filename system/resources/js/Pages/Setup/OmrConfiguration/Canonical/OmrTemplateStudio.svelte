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
    'Stage 1: Base Template & Paper Format',
    'Stage 2: Configure Layout, Markers & Overlays',
    'Stage 3: Optical Registration Alignment',
    'Stage 4: Multi-Section Bubble Coordinates & Pitch',
    'Stage 5: Dual-Input Verification & Production Export',
  ];

  // Stage 1: Base Substrate
  let baseType = $state(draft.source_kind ?? 'blank');

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
          {step === 1 ? '1. Base' : step === 2 ? '2. Overlays' : step === 3 ? '3. Alignment' : step === 4 ? '4. Bubbles' : '5. Verify'}
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
            <h2 class="text-base font-bold text-slate-900">Choose Template Base</h2>
            <p class="text-xs text-slate-500 mt-1">Select the starting substrate for your evaluation answer sheet.</p>
          </div>

          <div class="space-y-3">
            <span class="text-xs font-bold text-slate-800 uppercase tracking-wider block">Base Format</span>
            <div class="grid grid-cols-2 gap-2.5">
              <button
                type="button"
                onclick={() => { baseType = 'blank'; triggerAutosave(); }}
                class={`p-3 rounded-xl border text-left transition flex flex-col justify-between ${
                  baseType === 'blank' || baseType === 'preset'
                    ? 'border-slate-900 bg-slate-900 text-white shadow-xs'
                    : 'border-slate-200 hover:border-slate-300 text-slate-700 bg-slate-50/50'
                }`}
              >
                <FileText class="size-5 mb-2" />
                <div>
                  <div class="font-bold text-xs">Blank Canvas</div>
                  <div class="text-[10px] opacity-80 mt-0.5">Vector 300 PPI A4</div>
                </div>
              </button>
              <button
                type="button"
                onclick={() => { baseType = 'image'; triggerAutosave(); }}
                class={`p-3 rounded-xl border text-left transition flex flex-col justify-between ${
                  baseType === 'image' || baseType === 'photo' || baseType === 'pdf'
                    ? 'border-slate-900 bg-slate-900 text-white shadow-xs'
                    : 'border-slate-200 hover:border-slate-300 text-slate-700 bg-slate-50/50'
                }`}
              >
                <Layers class="size-5 mb-2" />
                <div>
                  <div class="font-bold text-xs">Source Document</div>
                  <div class="text-[10px] opacity-80 mt-0.5">Ingested scan / PDF</div>
                </div>
              </button>
            </div>
          </div>

          <div class="space-y-3 border-t border-slate-100 pt-4">
            <span class="text-xs font-bold text-slate-800 uppercase tracking-wider block">Paper Standard</span>
            <div class="p-3 bg-slate-50 rounded-xl border border-slate-200/80 space-y-1 text-xs">
              <div class="flex justify-between text-slate-700">
                <span class="font-medium">Standard Paper</span>
                <span class="font-semibold">ISO A4 (210 &times; 297 mm)</span>
              </div>
              <div class="flex justify-between text-slate-500 text-[11px]">
                <span>Render Resolution</span>
                <span class="font-mono">2480 &times; 3508 px @ 300 PPI</span>
              </div>
            </div>
          </div>
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
            <h2 class="text-base font-bold text-slate-900">Registration Alignment</h2>
            <p class="text-xs text-slate-500 mt-1">OMRChecker Quad Authority perspective alignment.</p>
          </div>

          <div class="p-4 bg-emerald-50/80 rounded-xl border border-emerald-200 text-xs text-emerald-900 space-y-2">
            <div class="flex items-center gap-1.5 font-bold">
              <ShieldCheck class="size-4 text-emerald-600" />
              <span>Normalization-First Quad Authority</span>
            </div>
            <p class="text-[11px] leading-relaxed text-emerald-800">
              The 4 corner fiducial centers define identically the <code class="font-mono bg-emerald-100 px-1 rounded">(0,0)</code> Top-Left through Bottom-Right coordinate quad. All bubble grids in Stage 4 are mapped directly onto this normalized quad.
            </p>
          </div>

          <div class="space-y-3">
            <span class="text-xs font-bold text-slate-800 uppercase tracking-wider block">Marker Quad Coordinates</span>
            <div class="space-y-1 text-xs font-mono bg-slate-900 text-emerald-400 p-3 rounded-xl">
              <div>TL: [{marginMm}, {marginMm}] mm</div>
              <div>TR: [{210 - marginMm}, {marginMm}] mm</div>
              <div>BL: [{marginMm}, {297 - marginMm}] mm</div>
              <div>BR: [{210 - marginMm}, {297 - marginMm}] mm</div>
            </div>
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
