<script>
  import {
    Lock,
    Loader2,
    Minus,
    Plus,
  } from 'lucide-svelte';
  import { contentBlockSizeMm } from '@/lib/omrStudioGeometry';

  let {
    scale = $bindable(1.0),
    panX = $bindable(0),
    panY = $bindable(0),
    marginMm = 15,
    markerPattern = 'square',
    canvasLocked = false,
    rasterUrl = null,
    showHeaderOverlay = true,
    headerTitle = 'EXAMINATION ANSWER SHEET',
    headerSubtitle = 'AUTOMATED OMR EVALUATION FORM',
    headerFontSize = $bindable(14.0),
    headerFontFamily = $bindable('Inter, sans-serif'),
    headerColor = $bindable('#0f172a'),
    headerAlign = $bindable('left'),
    headerX = $bindable(25),
    headerY = $bindable(20),
    headerW = $bindable(160),
    headerH = $bindable(15),
    headerLocked = false,
    showQrOverlay = true,
    qrX = $bindable(150),
    qrY = $bindable(32),
    qrW = $bindable(32),
    qrH = $bindable(32),
    qrLocked = false,
    showInstructionsOverlay = true,
    instructionsContent = 'Use 2B pencil only.',
    instructionsFontSize = $bindable(9.0),
    instructionsFontFamily = $bindable('Inter, sans-serif'),
    instructionsColor = $bindable('#334155'),
    instX = $bindable(25),
    instY = $bindable(250),
    instW = $bindable(90),
    instH = $bindable(28),
    instLocked = false,
    showSignatureOverlay = true,
    signatureContent = 'EXAMINEE SIGNATURE',
    signatureFontSize = $bindable(8.5),
    signatureFontFamily = $bindable('Inter, sans-serif'),
    signatureColor = $bindable('#475569'),
    sigX = $bindable(125),
    sigY = $bindable(250),
    sigW = $bindable(60),
    sigH = $bindable(28),
    sigLocked = false,
    customTextItems = $bindable([]),
    htmlContent = null,
    isHtmlMode = true,
    isComposing = false,
    sections = $bindable([]),
    activeSectionIndex = $bindable(0),
    currentStage = 1,
    onGeometryChanged = null,
  } = $props();

  let isPanning = $state(false);
  let panStartX = 0;
  let panStartY = 0;

  // Dragging State
  let isBlockDragging = $state(false);
  let isHandleDragging = $state(false);
  let dragBlockIndex = $state(null);
  let selectedOverlay = $state('header'); // 'header' | 'qr' | 'signature' | 'instructions' | number
  let dragStartX = 0;
  let dragStartY = 0;
  let blockStartGeometry = { x: 0, y: 0, w: 0, h: 0 };

  // Sanitize HTML preview content by stripping scripts and executable handlers
  let safeHtmlContent = $derived.by(() => {
    if (!htmlContent) return null;
    return htmlContent
      .replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '')
      .replace(/\son\w+\s*=\s*(['"]).*?\1/gi, '');
  });

  function handleWheel(e) {
    if (canvasLocked) return; // Prevent zooming if canvas is locked
    e.preventDefault();
    const zoomFactor = e.deltaY < 0 ? 1.1 : 0.9;
    scale = Math.min(Math.max(0.35, scale * zoomFactor), 2.5);
  }

  function handleCanvasMouseDown(e) {
    if (canvasLocked) return; // Prevent background pan if canvas is locked
    if (e.target.closest('button, input, select, textarea')) return;
    if (isBlockDragging || isHandleDragging) return;
    isPanning = true;
    panStartX = e.clientX - panX;
    panStartY = e.clientY - panY;
  }

  function handleCanvasMouseMove(e) {
    if (isPanning && !canvasLocked) {
      panX = e.clientX - panStartX;
      panY = e.clientY - panStartY;
      return;
    }

    const dXmm = (e.clientX - dragStartX) / (2.476 * scale);
    const dYmm = (e.clientY - dragStartY) / (2.476 * scale);

    // STAGE 2: Overlay Elements Dragging & Resizing (respects per-element locks)
    if (currentStage === 2) {
      if (isBlockDragging) {
        if (selectedOverlay === 'header' && !headerLocked) {
          headerX = Math.max(marginMm, Math.min(210 - marginMm - headerW, Math.round(blockStartGeometry.x + dXmm)));
          headerY = Math.max(marginMm, Math.min(297 - marginMm - headerH, Math.round(blockStartGeometry.y + dYmm)));
        } else if (selectedOverlay === 'qr' && !qrLocked) {
          qrX = Math.max(marginMm, Math.min(210 - marginMm - qrW, Math.round(blockStartGeometry.x + dXmm)));
          qrY = Math.max(marginMm, Math.min(297 - marginMm - qrH, Math.round(blockStartGeometry.y + dYmm)));
        } else if (selectedOverlay === 'signature' && !sigLocked) {
          sigX = Math.max(marginMm, Math.min(210 - marginMm - sigW, Math.round(blockStartGeometry.x + dXmm)));
          sigY = Math.max(marginMm, Math.min(297 - marginMm - sigH, Math.round(blockStartGeometry.y + dYmm)));
        } else if (selectedOverlay === 'instructions' && !instLocked) {
          instX = Math.max(marginMm, Math.min(210 - marginMm - instW, Math.round(blockStartGeometry.x + dXmm)));
          instY = Math.max(marginMm, Math.min(297 - marginMm - instH, Math.round(blockStartGeometry.y + dYmm)));
        } else if (typeof selectedOverlay === 'number' && customTextItems[selectedOverlay] && !customTextItems[selectedOverlay].isLocked) {
          const item = customTextItems[selectedOverlay];
          item.posX = Math.max(marginMm, Math.min(210 - marginMm - item.w_mm, Math.round(blockStartGeometry.x + dXmm)));
          item.posY = Math.max(marginMm, Math.min(297 - marginMm - item.h_mm, Math.round(blockStartGeometry.y + dYmm)));
        }
      } else if (isHandleDragging) {
        if (selectedOverlay === 'header' && !headerLocked) {
          headerW = Math.max(40, Math.min(210 - headerX - marginMm, Math.round(blockStartGeometry.w + dXmm)));
          headerH = Math.max(12, Math.min(297 - headerY - marginMm, Math.round(blockStartGeometry.h + dYmm)));
        } else if (selectedOverlay === 'qr' && !qrLocked) {
          const newSize = Math.max(20, Math.min(50, Math.round(blockStartGeometry.w + dXmm)));
          qrW = newSize;
          qrH = newSize;
        } else if (selectedOverlay === 'signature' && !sigLocked) {
          sigW = Math.max(30, Math.min(210 - sigX - marginMm, Math.round(blockStartGeometry.w + dXmm)));
          sigH = Math.max(15, Math.min(297 - sigY - marginMm, Math.round(blockStartGeometry.h + dYmm)));
        } else if (selectedOverlay === 'instructions' && !instLocked) {
          instW = Math.max(40, Math.min(210 - instX - marginMm, Math.round(blockStartGeometry.w + dXmm)));
          instH = Math.max(15, Math.min(297 - instY - marginMm, Math.round(blockStartGeometry.h + dYmm)));
        }
      }
      return;
    }

    // STAGE 3: Question Bubble Sections Dragging only (constrained — no manual resize)
    if (currentStage === 3 && dragBlockIndex !== null) {
      const sec = sections[dragBlockIndex];
      if (!sec || sec.isLocked || !isBlockDragging) return;

      const { w_mm, h_mm } = contentBlockSizeMm(
        getOptionLabels(sec).length,
        Number(sec.questionsCount) || 1,
        Number(sec.bubblePitchMm) || 9.5,
        Number(sec.rowPitchMm) || 4.2
      );

      let nx = Math.round((blockStartGeometry.x + dXmm) / 2) * 2;
      let ny = Math.round((blockStartGeometry.y + dYmm) / 2) * 2;
      sec.posX = Math.max(marginMm, Math.min(210 - marginMm - w_mm, nx));
      sec.posY = Math.max(marginMm + 15, Math.min(297 - marginMm - h_mm, ny));
    }
  }

  function handleCanvasMouseUp() {
    if (isPanning) isPanning = false;
    if (isBlockDragging || isHandleDragging) {
      isBlockDragging = false;
      isHandleDragging = false;
      dragBlockIndex = null;
      if (onGeometryChanged) onGeometryChanged();
    }
  }

  // Overlay Drag & Resize Triggers (Stage 2)
  function handleOverlayMouseDown(e, target, geometry, isLocked = false) {
    if (currentStage !== 2 || isLocked) return;
    e.stopPropagation();
    selectedOverlay = target;
    isBlockDragging = true;
    dragStartX = e.clientX;
    dragStartY = e.clientY;
    blockStartGeometry = { ...geometry };
  }

  function handleOverlayResizeMouseDown(e, target, geometry, isLocked = false) {
    if (currentStage !== 2 || isLocked) return;
    e.stopPropagation();
    selectedOverlay = target;
    isHandleDragging = true;
    dragStartX = e.clientX;
    dragStartY = e.clientY;
    blockStartGeometry = { ...geometry };
  }

  // Section Bubble Drag Triggers (Stage 3)
  function handleBlockMouseDown(e, index) {
    const sec = sections[index];
    if (currentStage !== 3 || sec?.isLocked) return;
    e.stopPropagation();
    activeSectionIndex = index;
    isBlockDragging = true;
    dragBlockIndex = index;
    dragStartX = e.clientX;
    dragStartY = e.clientY;
    blockStartGeometry = { x: Number(sec.posX) || 25, y: Number(sec.posY) || 72, w: Number(sec.w_mm) || 75, h: Number(sec.h_mm) || 140 };
  }

  function getOptionLabels(s) {
    if (!s) return ['A', 'B', 'C', 'D'];
    if (s.type === 'tf') return ['T', 'F'];
    if (s.type === 'likert') return ['1', '2', '3', '4', '5'];
    if (s.type === 'custom') return (s.customValuesStr || 'A, B').split(',').map((v) => v.trim()).filter(Boolean);
    const count = Number(s.optionsCount) || 4;
    return ['A', 'B', 'C', 'D', 'E'].slice(0, Math.max(2, Math.min(5, count)));
  }
</script>

<div class="flex-1 w-full h-full relative overflow-hidden flex flex-col bg-slate-100 select-none">
  <!-- Canvas Viewport with Absolute Centering (ZERO scrollHeight inflation) -->
  <div
    class="w-full h-full overflow-hidden relative cursor-grab active:cursor-grabbing canvas-grid"
    onwheel={handleWheel}
    onmousedown={handleCanvasMouseDown}
    onmousemove={handleCanvasMouseMove}
    onmouseup={handleCanvasMouseUp}
    onmouseleave={handleCanvasMouseUp}
    role="region"
    aria-label="OMR sheet design viewport"
  >
    <!-- Centering Absolute Wrapper: Zero layout height inflation -->
    <div class="absolute inset-0 flex items-center justify-center overflow-hidden pointer-events-none">
      <!-- Scalable Paper Document Sheet (A4 Proportion: 520px x 735px @ 2.476 px/mm) -->
      <div
        class="bg-white shadow-2xl rounded-xs border border-slate-300 transition-transform origin-center relative pointer-events-auto shrink-0 overflow-hidden"
        style={`width: 520px; height: 735px; transform: translate(${panX}px, ${panY}px) scale(${scale});`}
      >
      <!-- Continuous Background Layer (HTML source preview strictly contained without iframe scrollbars) -->
      {#if safeHtmlContent}
        <iframe
          srcdoc={safeHtmlContent}
          title="HTML Source Preview"
          class="absolute inset-0 w-full h-full border-none pointer-events-none rounded-xs bg-white overflow-hidden"
          sandbox="allow-same-origin"
          scrolling="no"
        ></iframe>
      {:else if rasterUrl}
        <img
          src={rasterUrl}
          alt="Composed Page"
          class="absolute inset-0 w-full h-full object-contain pointer-events-none opacity-90"
        />
      {/if}

      <!-- STAGE 2+: 4 Corner Registration Fiducial Markers (ALWAYS VISIBLE in Stage 2, 3, 4) -->
      {#if currentStage >= 2}
        <div
          class={`absolute ${markerPattern === 'target' ? 'marker-target' : 'marker-box'}`}
          style={`top: ${marginMm * 2.476}px; left: ${marginMm * 2.476}px;`}
        ></div>
        <div
          class={`absolute ${markerPattern === 'target' ? 'marker-target' : 'marker-box'}`}
          style={`top: ${marginMm * 2.476}px; right: ${marginMm * 2.476}px;`}
        ></div>
        <div
          class={`absolute ${markerPattern === 'target' ? 'marker-target' : 'marker-box'}`}
          style={`bottom: ${marginMm * 2.476}px; left: ${marginMm * 2.476}px;`}
        ></div>
        <div
          class={`absolute ${markerPattern === 'target' ? 'marker-target' : 'marker-box'}`}
          style={`bottom: ${marginMm * 2.476}px; right: ${marginMm * 2.476}px;`}
        ></div>
      {/if}

      <!-- STAGE 2: Overlays (Header, Signature, Instructions, Custom text, Simulated QR) with Typography Controls -->
      {#if currentStage === 2}
        <!-- Document Header Overlay -->
        {#if showHeaderOverlay}
          <div
            class={`absolute flex justify-between items-center pb-1.5 border-b-2 border-slate-900 transition-all ${
              selectedOverlay === 'header' && !headerLocked
                ? 'ring-2 ring-emerald-500 bg-emerald-500/10 rounded p-1 z-30'
                : 'z-10'
            }`}
            style={`top: ${headerY * 2.476}px; left: ${headerX * 2.476}px; width: ${headerW * 2.476}px; text-align: ${headerAlign};`}
            onmousedown={(e) => handleOverlayMouseDown(e, 'header', { x: headerX, y: headerY, w: headerW, h: headerH }, headerLocked)}
            role="button"
            tabindex="0"
          >
            <div class="w-full" style={`text-align: ${headerAlign};`}>
              <h3
                class="font-bold tracking-tight leading-tight"
                style={`font-size: ${headerFontSize * 0.9}px; font-family: ${headerFontFamily}; color: ${headerColor}; text-align: ${headerAlign};`}
              >
                {headerTitle}
              </h3>
              {#if headerSubtitle}
                <p class="text-[9px] font-mono text-slate-500 mt-0.5" style={`text-align: ${headerAlign};`}>{headerSubtitle}</p>
              {/if}
            </div>

            <!-- On-Canvas Fast Style Pill for Header -->
            {#if selectedOverlay === 'header' && !headerLocked}
              <div
                class="absolute -top-7 left-0 bg-slate-900/90 text-white rounded-lg px-2 py-0.5 flex items-center space-x-1.5 shadow-lg text-[10px] z-40 pointer-events-auto"
                onmousedown={(e) => e.stopPropagation()}
                role="toolbar"
                tabindex="0"
              >
                <button
                  type="button"
                  onclick={() => { headerFontSize = Math.max(9, headerFontSize - 1); if (onGeometryChanged) onGeometryChanged(); }}
                  class="hover:text-emerald-400 p-0.5"
                  title="Decrease font size"
                >
                  <Minus class="size-2.5" />
                </button>
                <span class="font-mono text-[9.5px]">{headerFontSize}pt</span>
                <button
                  type="button"
                  onclick={() => { headerFontSize = Math.min(28, headerFontSize + 1); if (onGeometryChanged) onGeometryChanged(); }}
                  class="hover:text-emerald-400 p-0.5"
                  title="Increase font size"
                >
                  <Plus class="size-2.5" />
                </button>
              </div>

              <!-- Resize Handle -->
              <div
                class="absolute -bottom-1 -right-1 w-3.5 h-3.5 bg-emerald-600 border-2 border-white rounded-full cursor-se-resize shadow-md"
                onmousedown={(e) => handleOverlayResizeMouseDown(e, 'header', { x: headerX, y: headerY, w: headerW, h: headerH }, headerLocked)}
                role="button"
                tabindex="0"
              ></div>
            {/if}
          </div>
        {/if}

        <!-- Simulated Cryptographic QR Overlay -->
        {#if showQrOverlay}
          <div
            class={`absolute border rounded bg-white p-1 flex flex-col items-center justify-between transition-all ${
              selectedOverlay === 'qr' && !qrLocked
                ? 'border-emerald-500 ring-2 ring-emerald-400/80 z-30 shadow-md'
                : 'border-slate-300 shadow-xs z-10'
            }`}
            style={`top: ${qrY * 2.476}px; left: ${qrX * 2.476}px; width: ${qrW * 2.476}px; height: ${qrH * 2.476}px;`}
            onmousedown={(e) => handleOverlayMouseDown(e, 'qr', { x: qrX, y: qrY, w: qrW, h: qrH }, qrLocked)}
            role="button"
            tabindex="0"
          >
            <!-- Simulated QR Matrix -->
            <svg viewBox="0 0 100 100" class="w-full h-[calc(100%-10px)] text-slate-900" xmlns="http://www.w3.org/2000/svg">
              <rect x="5" y="5" width="28" height="28" fill="currentColor" rx="2" />
              <rect x="11" y="11" width="16" height="16" fill="white" rx="1" />
              <rect x="15" y="15" width="8" height="8" fill="currentColor" rx="1" />
              <rect x="67" y="5" width="28" height="28" fill="currentColor" rx="2" />
              <rect x="73" y="11" width="16" height="16" fill="white" rx="1" />
              <rect x="77" y="15" width="8" height="8" fill="currentColor" rx="1" />
              <rect x="5" y="67" width="28" height="28" fill="currentColor" rx="2" />
              <rect x="11" y="73" width="16" height="16" fill="white" rx="1" />
              <rect x="15" y="77" width="8" height="8" fill="currentColor" rx="1" />
              <rect x="38" y="8" width="6" height="6" fill="currentColor" />
              <rect x="48" y="14" width="6" height="6" fill="currentColor" />
              <rect x="56" y="8" width="6" height="6" fill="currentColor" />
              <rect x="38" y="24" width="6" height="6" fill="currentColor" />
              <rect x="48" y="24" width="6" height="6" fill="currentColor" />
              <rect x="8" y="38" width="6" height="6" fill="currentColor" />
              <rect x="20" y="46" width="6" height="6" fill="currentColor" />
              <rect x="38" y="38" width="6" height="6" fill="currentColor" />
              <rect x="48" y="46" width="6" height="6" fill="currentColor" />
              <rect x="56" y="38" width="6" height="6" fill="currentColor" />
              <rect x="68" y="46" width="6" height="6" fill="currentColor" />
              <rect x="78" y="38" width="6" height="6" fill="currentColor" />
              <rect x="88" y="46" width="6" height="6" fill="currentColor" />
              <rect x="38" y="58" width="6" height="6" fill="currentColor" />
              <rect x="56" y="68" width="6" height="6" fill="currentColor" />
              <rect x="78" y="68" width="6" height="6" fill="currentColor" />
              <rect x="88" y="78" width="6" height="6" fill="currentColor" />
              <rect x="38" y="78" width="6" height="6" fill="currentColor" />
              <rect x="48" y="88" width="6" height="6" fill="currentColor" />
              <rect x="68" y="88" width="6" height="6" fill="currentColor" />
            </svg>
            <span class="text-[6.5px] font-mono font-bold text-slate-600 uppercase tracking-tighter text-center leading-none">INSTANCE QR</span>

            {#if selectedOverlay === 'qr' && !qrLocked}
              <div
                class="absolute -bottom-1 -right-1 w-3.5 h-3.5 bg-emerald-600 border-2 border-white rounded-full cursor-se-resize shadow-md"
                onmousedown={(e) => handleOverlayResizeMouseDown(e, 'qr', { x: qrX, y: qrY, w: qrW, h: qrH }, qrLocked)}
                role="button"
                tabindex="0"
              ></div>
            {/if}
          </div>
        {/if}

        <!-- Instructions Box Overlay -->
        {#if showInstructionsOverlay}
          <div
            class={`absolute border p-2 rounded text-[8px] leading-tight transition-all ${
              selectedOverlay === 'instructions' && !instLocked
                ? 'border-emerald-500 ring-2 ring-emerald-400/80 bg-white z-30 shadow-md'
                : 'border-slate-300 bg-slate-50/90 text-slate-700 z-10'
            }`}
            style={`top: ${instY * 2.476}px; left: ${instX * 2.476}px; width: ${instW * 2.476}px; font-family: ${instructionsFontFamily}; color: ${instructionsColor}; font-size: ${instructionsFontSize * 0.9}px;`}
            onmousedown={(e) => handleOverlayMouseDown(e, 'instructions', { x: instX, y: instY, w: instW, h: instH }, instLocked)}
            role="button"
            tabindex="0"
          >
            <span class="font-bold text-slate-900 uppercase block mb-0.5">Instructions:</span>
            {instructionsContent}

            {#if selectedOverlay === 'instructions' && !instLocked}
              <div
                class="absolute -bottom-1 -right-1 w-3.5 h-3.5 bg-emerald-600 border-2 border-white rounded-full cursor-se-resize shadow-md"
                onmousedown={(e) => handleOverlayResizeMouseDown(e, 'instructions', { x: instX, y: instY, w: instW, h: instH }, instLocked)}
                role="button"
                tabindex="0"
              ></div>
            {/if}
          </div>
        {/if}

        <!-- Signature Box Overlay -->
        {#if showSignatureOverlay}
          <div
            class={`absolute border-2 border-slate-400 p-2 rounded bg-white flex flex-col justify-between transition-all ${
              selectedOverlay === 'signature' && !sigLocked
                ? 'ring-2 ring-emerald-500 border-emerald-500 z-30 shadow-md'
                : 'z-10'
            }`}
            style={`top: ${sigY * 2.476}px; left: ${sigX * 2.476}px; width: ${sigW * 2.476}px; height: ${sigH * 2.476}px; font-family: ${signatureFontFamily};`}
            onmousedown={(e) => handleOverlayMouseDown(e, 'signature', { x: sigX, y: sigY, w: sigW, h: sigH }, sigLocked)}
            role="button"
            tabindex="0"
          >
            <span class="font-bold uppercase tracking-wider" style={`font-size: ${signatureFontSize * 0.9}px; color: ${signatureColor}; font-family: ${signatureFontFamily};`}>
              {signatureContent}
            </span>
            <div class="border-b border-dashed border-slate-400 w-full mb-1"></div>

            {#if selectedOverlay === 'signature' && !sigLocked}
              <div
                class="absolute -bottom-1 -right-1 w-3.5 h-3.5 bg-emerald-600 border-2 border-white rounded-full cursor-se-resize shadow-md"
                onmousedown={(e) => handleOverlayResizeMouseDown(e, 'signature', { x: sigX, y: sigY, w: sigW, h: sigH }, sigLocked)}
                role="button"
                tabindex="0"
              ></div>
            {/if}
          </div>
        {/if}

        <!-- Custom Text Items Overlay -->
        {#each customTextItems as t, idx}
          <div
            class={`absolute font-medium leading-tight p-0.5 rounded cursor-move transition-all ${
              selectedOverlay === idx && !t.isLocked
                ? 'ring-2 ring-emerald-500 bg-emerald-50/50 z-30'
                : 'z-10'
            }`}
            style={`top: ${t.posY * 2.476}px; left: ${t.posX * 2.476}px; width: ${t.w_mm * 2.476}px; font-size: ${(t.fontSize ?? 10) * 0.9}px; font-family: ${t.fontFamily || 'Inter, sans-serif'}; color: ${t.color || '#0f172a'}; text-align: ${t.align || 'left'}; font-weight: ${t.bold ? 'bold' : 'normal'}; font-style: ${t.italic ? 'italic' : 'normal'};`}
            onmousedown={(e) => handleOverlayMouseDown(e, idx, { x: t.posX, y: t.posY, w: t.w_mm, h: t.h_mm }, t.isLocked)}
            role="button"
            tabindex="0"
          >
            {t.content}
          </div>
        {/each}
      {/if}

      <!-- STAGE 3+: Multi-Section Question Bubble Grids (content-hug, constrained — ZERO wasted bottom space) -->
      {#if currentStage >= 3}
        {#each sections as sec, i}
          {@const isSelected = activeSectionIndex === i}
          {@const labels = getOptionLabels(sec)}
          {@const bPitchMm = Number(sec.bubblePitchMm) || 9.5}
          {@const rPitchMm = Number(sec.rowPitchMm) || 4.2}
          {@const bubbleGapPx = Math.max(1.5, (bPitchMm * 2.476) - 12)}
          {@const rowGapPx = Math.max(0.5, (rPitchMm * 2.476) - 12)}
          {@const blockSize = contentBlockSizeMm(labels.length, Number(sec.questionsCount) || 1, bPitchMm, rPitchMm)}
          {@const blockWidthMm = blockSize.w_mm}
          {@const blockHeightMm = blockSize.h_mm}
          {@const qEnd = (Number(sec.startQ) || 1) + (Number(sec.questionsCount) || 1) - 1}
          <div
            class={`absolute rounded-lg border transition-all backdrop-blur-[0.5px] overflow-hidden ${
              isSelected
                ? 'border-emerald-600 border-dashed bg-emerald-500/15 ring-2 ring-emerald-400/60 z-20 shadow-sm'
                : 'border-emerald-500/80 border-dashed bg-emerald-500/5 hover:border-emerald-600 z-10'
            }`}
            style={`top: ${(Number(sec.posY) || 56) * 2.476}px; left: ${(Number(sec.posX) || 16) * 2.476}px; width: ${blockWidthMm * 2.476}px; height: ${blockHeightMm * 2.476}px;`}
            onmousedown={(e) => handleBlockMouseDown(e, i)}
            role="button"
            tabindex="0"
          >
            <!-- Translucent Optical Target Rings with DYNAMIC Pitch Spacing (content-hug padding) -->
            <div
              class="p-1 w-full h-full flex flex-col justify-start"
              style={`gap: ${rowGapPx}px;`}
            >
              {#each Array.from({ length: Math.min(Number(sec?.questionsCount) || 1, 50) }) as _, r}
                <div class="flex items-center text-[7px] font-mono text-emerald-950 shrink-0 h-3 leading-none">
                  <span class="w-3.5 h-3 text-[6px] font-bold text-emerald-700 bg-emerald-100/80 border border-emerald-200 rounded px-0.5 text-center mr-1 shrink-0 flex items-center justify-center">
                    {(Number(sec?.startQ) || 1) + r}
                  </span>
                  <div
                    class="flex items-center"
                    style={`gap: ${bubbleGapPx}px;`}
                  >
                    {#each labels as opt}
                      <div class="w-3 h-3 rounded-full border border-emerald-600/90 bg-emerald-500/25 text-emerald-950 flex items-center justify-center text-[6px] font-bold shadow-xs shrink-0">
                        {opt}
                      </div>
                    {/each}
                  </div>
                </div>
              {/each}
            </div>

            <!-- Minimal Floating Q-Range Label (no header bar, no pitch text) -->
            <div
              class={`absolute top-0.5 right-0.5 px-1 py-px rounded text-[6.5px] font-mono font-bold shadow-xs pointer-events-none select-none ${
                isSelected ? 'bg-emerald-700/90 text-white' : 'bg-emerald-600/70 text-white'
              }`}
            >
              Q{Number(sec.startQ) || 1}{qEnd > (Number(sec.startQ) || 1) ? `–${qEnd}` : ''}
            </div>

            {#if sec.isLocked}
              <div class="absolute top-0.5 left-0.5 text-amber-600 pointer-events-none select-none">
                <Lock class="size-2.5" />
              </div>
            {/if}
          </div>
        {/each}
      {/if}

      <!-- Loading Overlay -->
      {#if isComposing}
        <div class="absolute inset-0 bg-white/90 backdrop-blur-xs flex flex-col items-center justify-center p-6 text-center z-40 rounded-xs">
          <div class="size-10 rounded-xl bg-slate-900 flex items-center justify-center text-white mb-3 shadow-md animate-pulse">
            <Loader2 class="size-5 animate-spin" />
          </div>
          <h4 class="text-xs font-bold text-slate-900">Composing 300-PPI Print Raster</h4>
          <p class="text-[10px] text-slate-500 mt-1 max-w-[240px]">
            Generating vector fiducial markers and normalizing print canvas quad at 300 PPI…
          </p>
        </div>
      {/if}
      </div>
    </div>
  </div>
</div>

<style>
  .canvas-grid {
    background-image: radial-gradient(circle, #cbd5e1 1px, transparent 1px);
    background-size: 20px 20px;
  }
  .marker-box {
    width: 24px;
    height: 24px;
    background-color: #0f172a;
    border: 2px solid #0f172a;
  }
  .marker-target {
    width: 24px;
    height: 24px;
    border-radius: 9999px;
    border: 3px solid #0f172a;
    position: relative;
  }
  .marker-target::after {
    content: '';
    position: absolute;
    inset: 4px;
    background-color: #0f172a;
    border-radius: 9999px;
  }
</style>
