<script>
  import {
    Lock,
    Unlock,
    Loader2,
    Minus,
    Plus,
  } from 'lucide-svelte';
  import {
    CANVAS_BUBBLE_DIAMETER_PX,
    CANVAS_RENDER_BUBBLE_PX,
    gridOptionLabels,
    isQuestionGridComponent,
    canvasComponentBlockSizeMm,
    canvasSectionBoundsMm,
    componentBackgroundColor,
    componentInputBackgroundColor,
  } from '@/lib/omrComponents';

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
    components = $bindable([]),
    activeComponentId = $bindable(null),
    activeComponentIds = $bindable([]),
    currentStage = 1,
    onGeometryChanged = null,
    onComponentGeometryChanged = null,
    onComponentSelected = null,
    onCanvasSelectionClear = null,
    onDuplicateComponents = null,
  } = $props();

  let isPanning = $state(false);
  let panStartX = 0;
  let panStartY = 0;

  // Dragging State (Stage 2/3)
  let isBlockDragging = $state(false);
  let isHandleDragging = $state(false);
  let dragBlockIndex = $state(null);
  let selectedOverlay = $state('header');
  let dragStartX = 0;
  let dragStartY = 0;
  let blockStartGeometry = { x: 0, y: 0, w: 0, h: 0 };

  // Dragging & Resizing State (Stage 1 HTML Mode Universal Components)
  let isComponentDragging = $state(false);
  let isComponentResizing = $state(false);
  let dragCompId = $state(null);
  let dragComponentIds = $state([]);
  let componentStartPositions = {};
  let isDuplicateDragging = $state(false);

  // Sanitize HTML preview content by stripping scripts and executable handlers
  let safeHtmlContent = $derived.by(() => {
    if (!htmlContent) return null;
    return htmlContent
      .replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '')
      .replace(/\son\w+\s*=\s*(['"]).*?\1/gi, '');
  });
  function setCanvasSelection(componentId, additive = false) {
    const currentSelection = Array.isArray(activeComponentIds) ? activeComponentIds : [];
    const fallbackSelection = additive
      ? currentSelection.includes(componentId)
        ? currentSelection.filter((id) => id !== componentId)
        : [...currentSelection, componentId]
      : [componentId];
    const callbackSelection = onComponentSelected
      ? onComponentSelected(componentId, additive)
      : null;
    const nextSelection = Array.isArray(callbackSelection) ? callbackSelection : fallbackSelection;
    activeComponentIds = nextSelection;
    activeComponentId = nextSelection.at(-1) ?? null;
    return nextSelection;
  }


  function handleWheel(e) {
    if (canvasLocked) return;
    e.preventDefault();
    const zoomFactor = e.deltaY < 0 ? 1.1 : 0.9;
    scale = Math.min(Math.max(0.35, scale * zoomFactor), 2.5);
  }

  function handleCanvasMouseDown(e) {
    if (canvasLocked) return;
    if (e.target.closest('button, input, select, textarea')) return;
    if (!e.target?.closest?.('[data-omr-component-id]')) {
      activeComponentId = null;
      activeComponentIds = [];
      if (onCanvasSelectionClear) onCanvasSelectionClear();
    }
    if (isBlockDragging || isHandleDragging || isComponentDragging || isComponentResizing) return;
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

    // STAGE 1 (HTML Mode): Universal Component Dragging & Resizing
    if (isHtmlMode && currentStage === 1 && dragCompId) {
      const primary = components.find((c) => c.id === dragCompId);
      if (!primary) return;

      if (isComponentResizing) {
        if (primary.isLocked) return;
        primary.w_mm = Math.max(20, Math.min(210 - marginMm - primary.posX, Math.round(blockStartGeometry.w + dXmm)));
        primary.h_mm = Math.max(8, Math.min(297 - marginMm - primary.posY, Math.round(blockStartGeometry.h + dYmm)));
      } else if (isComponentDragging) {
        const ids = dragComponentIds.length > 0 ? dragComponentIds : [dragCompId];
        for (const id of ids) {
          const comp = components.find((candidate) => candidate.id === id);
          const start = componentStartPositions[id];
          if (!comp || !start || comp.isLocked) continue;
          const { w_mm: bw, h_mm: bh } = canvasComponentBlockSizeMm(comp);
          const nx = Math.round((start.x + dXmm) / 2) * 2;
          const ny = Math.round((start.y + dYmm) / 2) * 2;
          comp.posX = Math.max(marginMm, Math.min(210 - marginMm - bw, nx));
          comp.posY = Math.max(marginMm, Math.min(297 - marginMm - bh, ny));
        }
      }
      return;
    }
    if (currentStage === 2 || (isHtmlMode && currentStage === 1)) {
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

      const { w_mm, h_mm } = canvasSectionBoundsMm(
        getOptionLabels(sec).length,
        Number(sec.questionsCount) || 1,
        Number(sec.bubblePitchMm) || 6.0,
        Number(sec.rowPitchMm) || 6.8,
        sec.type === 'roll',
        Number(sec.bubbleDiameterMm) || 4.5,
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
    if (isComponentDragging || isComponentResizing) {
      isComponentDragging = false;
      isComponentResizing = false;
      dragCompId = null;
      dragComponentIds = [];
      componentStartPositions = {};
      isDuplicateDragging = false;
      if (onComponentGeometryChanged) onComponentGeometryChanged();
    }
  }

  // Universal Component Drag & Resize Triggers (Stage 1 HTML Mode)
  function handleComponentMouseDown(e, compId) {
    if (currentStage !== 1 || !isHtmlMode) return;
    e.stopPropagation();
    const source = components.find((component) => component.id === compId);
    if (!source) return;

    if (e.altKey && !source.isLocked && onDuplicateComponents) {
      const duplicatedComponents = onDuplicateComponents(compId, { deferHistory: true });
      const copies = Array.isArray(duplicatedComponents) ? duplicatedComponents : [];
      if (copies.length > 0) {
        activeComponentIds = copies.map((copy) => copy.id);
        activeComponentId = activeComponentIds.at(-1) ?? null;
        dragComponentIds = [...activeComponentIds];
        componentStartPositions = Object.fromEntries(
          copies.map((copy) => [
            copy.id,
            {
              x: Number(copy.posX) || 10,
              y: Number(copy.posY) || 10,
              w: Number(copy.w_mm) || 40,
              h: Number(copy.h_mm) || 12,
            },
          ]),
        );
        isDuplicateDragging = true;
        isComponentDragging = true;
        dragCompId = activeComponentId;
        dragStartX = e.clientX;
        dragStartY = e.clientY;
        const primary = copies.at(-1);
        blockStartGeometry = {
          x: Number(primary.posX) || 10,
          y: Number(primary.posY) || 10,
          w: Number(primary.w_mm) || 40,
          h: Number(primary.h_mm) || 12,
        };
        return;
      }
    }

    const currentSelection = Array.isArray(activeComponentIds) ? activeComponentIds : [];
    const nextSelection = !e.shiftKey && currentSelection.length > 1 && currentSelection.includes(compId)
      ? currentSelection
      : setCanvasSelection(compId, e.shiftKey);
    if (!nextSelection.includes(compId) || source.isLocked) return;

    isDuplicateDragging = false;
    isComponentDragging = true;
    dragCompId = compId;
    dragComponentIds = [...nextSelection];
    const selectedComponents = nextSelection
      .map((id) => components.find((component) => component.id === id))
      .filter(Boolean);
    componentStartPositions = Object.fromEntries(
      selectedComponents.map((component) => [
        component.id,
        {
          x: Number(component.posX) || 10,
          y: Number(component.posY) || 10,
          w: Number(component.w_mm) || 40,
          h: Number(component.h_mm) || 12,
        },
      ]),
    );
    dragStartX = e.clientX;
    dragStartY = e.clientY;
    blockStartGeometry = {
      x: Number(source.posX) || 10,
      y: Number(source.posY) || 10,
      w: Number(source.w_mm) || 40,
      h: Number(source.h_mm) || 12,
    };
  }

  function handleComponentResizeMouseDown(e, compId) {
    if (currentStage !== 1 || !isHtmlMode) return;
    e.stopPropagation();
    setCanvasSelection(compId);
    const comp = components.find((c) => c.id === compId);
    if (!comp || comp.isLocked) return;

    isDuplicateDragging = false;
    isComponentResizing = true;
    dragCompId = compId;
    dragComponentIds = [compId];
    dragStartX = e.clientX;
    dragStartY = e.clientY;
    blockStartGeometry = {
      x: Number(comp.posX) || 10,
      y: Number(comp.posY) || 10,
      w: Number(comp.w_mm) || 40,
      h: Number(comp.h_mm) || 12,
    };
  }

  // Overlay Drag & Resize Triggers (Stage 2)
  function handleOverlayMouseDown(e, target, geometry, isLocked = false) {
    if (!(currentStage === 2 || (isHtmlMode && currentStage === 1)) || isLocked) return;
    e.stopPropagation();
    selectedOverlay = target;
    isBlockDragging = true;
    dragStartX = e.clientX;
    dragStartY = e.clientY;
    blockStartGeometry = { ...geometry };
  }

  function handleOverlayResizeMouseDown(e, target, geometry, isLocked = false) {
    if (!(currentStage === 2 || (isHtmlMode && currentStage === 1)) || isLocked) return;
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

  function componentLabel(c) {
    if (!c) return 'Component';
    switch (c.type) {
      case 'header': return 'Document Header';
      case 'candidate_name': return 'Candidate Name';
      case 'birthdate': return 'Birthdate';
      case 'room_seat': return 'Room / Seat';
      case 'instructions': return 'Instructions';
      case 'roll': return 'Examinee ID Matrix';
      case 'mcq':
      case 'tf':
      case 'likert':
      case 'custom': return c.subjectName || c.name || 'Question Grid';
      case 'custom_text': return 'Custom Text';
      case 'custom_input': return 'Custom Input Box';
      case 'signature': return 'Signature Box';
      default: return c.type || 'Component';
    }
  }

  function getOptionLabels(s) {
    if (!s) return ['A', 'B', 'C', 'D'];
    if (s.type === 'roll') return ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    if (s.type === 'tf') return ['T', 'F'];
    if (s.type === 'likert') return ['1', '2', '3', '4', '5'];
    if (s.type === 'custom') return (s.customValuesStr || 'A, B').split(',').map((v) => v.trim()).filter(Boolean);
    const count = Number(s.optionsCount) || 4;
    return ['A', 'B', 'C', 'D', 'E'].slice(0, Math.max(2, Math.min(5, count)));
  }
  function markerClassName(pattern) {
    if (pattern === 'target') return 'marker-target';
    if (pattern === 'aruco') return 'marker-aruco';
    return 'marker-box';
  }
</script>

<div class="flex-1 w-full h-full relative overflow-hidden flex flex-col bg-slate-100 select-none">
  <!-- Canvas Viewport with Absolute Centering (ZERO scrollHeight inflation) -->
  <div
    class={`w-full h-full overflow-hidden relative ${isDuplicateDragging ? 'cursor-copy' : 'cursor-grab active:cursor-grabbing'} canvas-grid`}
    onwheel={handleWheel}
    onmousedown={handleCanvasMouseDown}
    onmousemove={handleCanvasMouseMove}
    onmouseup={handleCanvasMouseUp}
    onmouseleave={handleCanvasMouseUp}
    role="region"
    aria-label="OMR sheet design viewport"
    aria-keyshortcuts="Delete Backspace Control+D Meta+D"
  >
    <!-- Centering Absolute Wrapper: Zero layout height inflation -->
    <div class="absolute inset-0 flex items-center justify-center overflow-hidden pointer-events-none">
      <!-- Scalable Paper Document Sheet (A4 Proportion: 520px x 735px @ 2.476 px/mm) -->
      <div
        data-omr-sheet="true"
        class="bg-white shadow-2xl rounded-xs border border-slate-300 transition-transform origin-center relative pointer-events-auto shrink-0 overflow-hidden"
        style={`width: 520px; height: 735px; transform: translate(${panX}px, ${panY}px) scale(${scale});`}
      >
      <!-- Continuous Background Layer (HTML source preview or composed raster — disabled in Stage 1 Sheet Designer to avoid duplicate rendering) -->
      {#if safeHtmlContent && !(isHtmlMode && currentStage === 1)}
        <div class="absolute inset-0 w-full h-full overflow-hidden pointer-events-none rounded-xs bg-white">
          <iframe
            srcdoc={safeHtmlContent}
            title="HTML Source Preview"
            class="border-none pointer-events-none bg-white overflow-hidden"
            style="width: 793.7px; height: 1122.5px; transform: scale(0.65517); transform-origin: top left;"
            scrolling="no"
          ></iframe>
        </div>
      {:else if rasterUrl && !(isHtmlMode && currentStage === 1)}
        <img
          src={rasterUrl}
          alt="Composed Page"
          class="absolute inset-0 w-full h-full object-contain pointer-events-none opacity-90"
        />
      {/if}

      <!-- STAGE 2+: 4 Corner Registration Fiducial Markers (ALWAYS VISIBLE in Stage 2, 3, 4) -->
      {#if currentStage >= 2 || (isHtmlMode && currentStage === 1)}
        <div
          class={`absolute ${markerClassName(markerPattern)}`}
          style={`top: ${marginMm * 2.476}px; left: ${marginMm * 2.476}px;`}
        ></div>
        <div
          class={`absolute ${markerClassName(markerPattern)}`}
          style={`top: ${marginMm * 2.476}px; right: ${marginMm * 2.476}px;`}
        ></div>
        <div
          class={`absolute ${markerClassName(markerPattern)}`}
          style={`bottom: ${marginMm * 2.476}px; left: ${marginMm * 2.476}px;`}
        ></div>
        <div
          class={`absolute ${markerClassName(markerPattern)}`}
          style={`bottom: ${marginMm * 2.476}px; right: ${marginMm * 2.476}px;`}
        ></div>
      {/if}
      {#if isHtmlMode && currentStage === 1}
        <div
          class="absolute border border-dashed border-emerald-300/70 pointer-events-none z-0"
          style={`top: ${marginMm * 2.476}px; left: ${marginMm * 2.476}px; right: ${marginMm * 2.476}px; bottom: ${marginMm * 2.476}px;`}
          aria-label={`Printable margin inset ${marginMm} millimeters`}
        ></div>
      {/if}

      <!-- Shared overlay layer (Stage 2 and Sheet Designer Stage 1) -->
      {#if currentStage === 2 || (isHtmlMode && currentStage === 1)}
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

      <!-- STAGE 1: Sheet Designer Universal Component Blocks (Single-source interactive document) -->
      {#if isHtmlMode && currentStage === 1}
        {#each components as comp, compIndex (comp.id)}
          {@const isSelected = (activeComponentIds ?? []).includes(comp.id) || activeComponentId === comp.id}
          {@const isPrimarySelected = activeComponentId === comp.id}
          {@const isDragging = isComponentDragging && dragComponentIds.includes(comp.id)}
          {@const isRoll = comp.type === 'roll'}
          {@const isGrid = isQuestionGridComponent(comp)}
          {@const isText = !isRoll && !isGrid}
          {@const isInputField = comp.type === 'candidate_name' || comp.type === 'birthdate' || comp.type === 'room_seat' || comp.type === 'custom_input'}
          {@const fieldInputClass = comp.inputKind === 'multiline'
            ? 'border border-slate-700 rounded-xs'
            : comp.inputStyle === 'underline'
              ? 'border-b border-slate-700'
              : comp.inputStyle === 'dotted_line'
                ? 'border-b border-dashed border-slate-400'
                : 'border border-slate-700 rounded-xs'}
          {@const { w_mm: bw, h_mm: bh } = canvasComponentBlockSizeMm(comp)}
          {@const digitsCount = isRoll ? (Number(comp.digitsCount) || 7) : 0}
          {@const gridLabels = isGrid ? gridOptionLabels(comp) : []}
          {@const qEnd = isGrid ? ((Number(comp.startQ) || 1) + (Number(comp.questionsCount) || 1) - 1) : 0}
          {@const bubblePitchMm = isRoll ? (Number(comp.bubblePitchMm) || 5.5) : (isGrid ? (Number(comp.bubblePitchMm) || 6.0) : 6.0)}
          {@const bubbleDiameterPx = isRoll ? (Math.max(4.2, Number(comp.bubbleDiameterMm) || 4.5) * 2.476) : CANVAS_BUBBLE_DIAMETER_PX}
          {@const bubbleGapPx = Math.max(1, bubblePitchMm * 2.476 - bubbleDiameterPx)}
          {@const labelWidthPx = 3.5 * 2.476}
          {@const labelGapPx = 1.5 * 2.476}
          {@const bubbleStyle = `width: ${bubbleDiameterPx}px; height: ${bubbleDiameterPx}px;`}

          <div
            class={`absolute rounded-sm transition-all select-none overflow-visible ${
              isDragging && isDuplicateDragging
                ? 'ring-2 ring-sky-500 shadow-2xl z-40 bg-white/95 scale-[1.005]'
                : isDragging
                  ? 'ring-2 ring-emerald-500 shadow-2xl z-40 bg-white/95 scale-[1.005]'
                  : isSelected
                    ? 'ring-2 ring-emerald-500 shadow-sm z-30 bg-white'
                    : 'hover:ring-1 hover:ring-slate-400/80 z-10 bg-white'
            } ${comp.isLocked ? 'cursor-default' : isDuplicateDragging && isDragging ? 'cursor-copy' : isDragging ? 'cursor-grabbing' : 'cursor-grab'}`}
            data-omr-component-id={comp.id}
            data-omr-component-type={comp.type}
            style={`top: ${comp.posY * 2.476}px; left: ${comp.posX * 2.476}px; width: ${bw * 2.476}px; height: ${bh * 2.476}px; z-index: ${isDragging ? 40 : isSelected ? 30 : (Number(comp.zIndex) || compIndex + 1)}; background-color: ${componentBackgroundColor(comp)};`}
            onmousedown={(e) => handleComponentMouseDown(e, comp.id)}
            role="button"
            tabindex="0"
          >
            {#if isRoll}
              <!-- Roll / Examinee ID 0-9 Bubble Matrix (print-matched spacing) -->
              <div class="p-1 w-full h-full flex flex-col justify-start">
                <div
                  class="font-black text-center text-slate-900 uppercase tracking-wider mb-1"
                  style={`font-size: ${(comp.typography?.fontSizePt ?? 7.5) * 0.9}px; font-family: ${comp.typography?.fontFamily ?? 'Inter, sans-serif'}; color: ${comp.typography?.textColor ?? '#0f172a'}; text-align: ${comp.typography?.textAlign ?? 'center'};`}
                >
                  {comp.title || 'EXAMINEE NUMBER'}
                </div>
                <!-- Write-in boxes row -->
                <div class="flex justify-start mb-1" style={`gap: ${bubbleGapPx}px; padding-left: ${labelWidthPx + labelGapPx}px;`}>
                  {#each Array.from({ length: digitsCount }) as _}
                    <div class="border border-slate-900 rounded-xs bg-white" style={`width: ${bubbleDiameterPx}px; height: ${bubbleDiameterPx * 1.15}px;`}></div>
                  {/each}
                </div>
                <!-- 0-9 Matrix rows: number and bubbles share one compact row rhythm -->
                <div class="flex flex-col gap-0.5">
                  {#each Array.from({ length: 10 }) as _, digit}
                    <div class="flex items-center justify-start" style={`gap: ${labelGapPx}px; height: ${bubbleDiameterPx}px;`}>
                      <span
                        class="font-mono font-bold text-right"
                        style={`width: ${labelWidthPx}px; flex: 0 0 ${labelWidthPx}px; font-size: ${(comp.typography?.fontSizePt ?? 6.5) * 0.8}px; font-family: ${comp.typography?.fontFamily ?? 'monospace'}; color: ${comp.typography?.textColor ?? '#334155'};`}
                      >
                        {digit}
                      </span>
                      <div class="flex items-center" style={`gap: ${bubbleGapPx}px;`}>
                        {#each Array.from({ length: digitsCount }) as _}
                          <div class="rounded-full border border-slate-800 bg-white shrink-0" style={bubbleStyle}></div>
                        {/each}
                      </div>
                    </div>
                  {/each}
                </div>
              </div>

            {:else if isGrid}
              <!-- Question Grid (compact rows matching the print renderer) -->
              <div class="w-full h-full flex flex-col justify-start bg-white pointer-events-none" style={`background-color: ${componentBackgroundColor(comp)};`}>
                <div class="bg-slate-900 text-white font-black text-[7px] text-center uppercase tracking-wide rounded-xs py-0.5 mb-1 truncate px-1">
                  {comp.subjectName || comp.name || 'Subject'}
                </div>
                <!-- Option labels align with the bubble centers below -->
                <div class="flex items-center justify-start text-[6.5px] font-mono font-bold text-slate-900 mb-0.5">
                  <span aria-hidden="true" style={`width: ${labelWidthPx}px; flex: 0 0 ${labelWidthPx}px;`}></span>
                  <div class="flex items-center" style={`gap: ${bubbleGapPx}px;`}>
                    {#each gridLabels as opt}
                      <span class="text-center" style={bubbleStyle}>{opt}</span>
                    {/each}
                  </div>
                </div>
                <!-- Question Rows: number immediately precedes the bubble group -->
                <div class="flex flex-col gap-0.5">
                  {#each Array.from({ length: Math.min(Number(comp.questionsCount) || 1, 50) }) as _, r}
                    <div class="flex items-center justify-start" style={`gap: ${labelGapPx}px; height: ${bubbleDiameterPx}px;`}>
                      <span class="text-[6.5px] font-mono font-bold text-slate-600 text-right shrink-0" style={`width: ${labelWidthPx}px; flex: 0 0 ${labelWidthPx}px;`}>
                        {(Number(comp.startQ) || 1) + r}
                      </span>
                      <div class="flex items-center" style={`gap: ${bubbleGapPx}px;`}>
                        {#each gridLabels as _}
                          <div class="rounded-full border border-slate-800 bg-white text-slate-900 flex items-center justify-center text-[5.5px] font-bold shrink-0 shadow-2xs" style={bubbleStyle}></div>
                        {/each}
                      </div>
                    </div>
                  {/each}
                </div>
              </div>

            {:else if comp.type === 'header'}
              <!-- Document Header (Live print typography) -->
              <div class="p-1.5 w-full h-full flex flex-col justify-between border-b-2 border-slate-900 pointer-events-none">
                <div class="flex items-end justify-between gap-2">
                  <div class="min-w-0">
                    <h1
                      class="font-black uppercase leading-tight truncate"
                      style={`font-size: ${(comp.typography?.fontSizePt ?? 13) * 0.85}px; font-family: ${comp.typography?.fontFamily ?? 'Inter, sans-serif'}; font-weight: ${comp.typography?.fontWeight ?? 900}; color: ${comp.typography?.textColor ?? '#0f172a'}; text-align: ${comp.typography?.textAlign ?? 'left'};`}
                    >
                      {comp.title}
                    </h1>
                    {#if comp.subtitle}
                      <p
                        class="text-slate-500 font-semibold uppercase tracking-wider mt-0.5 truncate"
                        style={`font-size: ${Math.max(5, (comp.typography?.fontSizePt ?? 13) * 0.55)}px; font-family: ${comp.typography?.fontFamily ?? 'Inter, sans-serif'}; color: ${comp.typography?.textColor ?? '#64748b'}; text-align: ${comp.typography?.textAlign ?? 'left'};`}
                      >
                        {comp.subtitle}
                      </p>
                    {/if}
                  </div>
                  <span class="font-mono text-[8px] font-extrabold text-slate-900 border border-slate-900 px-1.5 py-0.5 rounded-xs shrink-0">
                    {comp.formCode}
                  </span>
                </div>
              </div>

            {:else if isInputField}
              <!-- Candidate/custom input fields keep the label and writing area separate. -->
              <div class="p-1.5 w-full h-full flex flex-col gap-1 bg-slate-50/80 border border-slate-700/80 rounded-xs pointer-events-none" style={`background-color: ${componentBackgroundColor(comp)};`}>
                <span
                  class="font-extrabold uppercase tracking-tight truncate shrink-0"
                  style={`font-size: ${(comp.typography?.fontSizePt ?? 6.5) * 0.9}px; font-family: ${comp.typography?.fontFamily ?? 'Inter, sans-serif'}; color: ${comp.typography?.textColor ?? '#334155'};`}
                >
                  {comp.label}
                </span>
                <div
                  class={`flex-1 min-h-1 w-full ${fieldInputClass}`}
                  style={`background-color: ${componentInputBackgroundColor(comp)};`}
                ></div>
              </div>

            {:else if comp.type === 'instructions'}
              <!-- Instructions Notice Box -->
              <div
                class="p-2 w-full h-full flex flex-col rounded-xs border pointer-events-none leading-tight"
                style={`border-color: ${comp.borderColor || '#cbd5e1'}; background-color: ${comp.backgroundColor || '#ffffff'}; font-size: ${(comp.typography?.fontSizePt ?? 6.5) * 0.9}px; font-family: ${comp.typography?.fontFamily ?? 'Inter, sans-serif'}; color: ${comp.typography?.textColor ?? '#334155'};`}
              >
                <span class="font-bold text-slate-900 uppercase tracking-wide block mb-0.5 text-[7px]">{comp.label || 'INSTRUCTIONS'}:</span>
                <span class="line-clamp-3 leading-snug">{comp.content}</span>
              </div>

            {:else if comp.type === 'custom_text'}
              <!-- Custom Text Block -->
              <div
                class="p-1 w-full h-full flex items-center pointer-events-none"
                style={`font-size: ${(comp.typography?.fontSizePt ?? 9) * 0.9}px; font-family: ${comp.typography?.fontFamily ?? 'Inter, sans-serif'}; font-weight: ${comp.typography?.fontWeight ?? 600}; color: ${comp.typography?.textColor ?? '#0f172a'}; text-align: ${comp.typography?.textAlign ?? 'left'};`}
              >
                <span class="truncate">{comp.text}</span>
              </div>

            {:else if comp.type === 'signature'}
              <!-- Signature Box -->
              <div class="p-1.5 w-full h-full flex flex-col justify-between border border-slate-400 bg-white rounded-xs pointer-events-none" style={`background-color: ${componentBackgroundColor(comp)};`}>
                <span
                  class="font-bold uppercase tracking-wide truncate"
                  style={`font-size: ${(comp.typography?.fontSizePt ?? 7.5) * 0.9}px; font-family: ${comp.typography?.fontFamily ?? 'Inter, sans-serif'}; color: ${comp.typography?.textColor ?? '#1e293b'};`}
                >
                  {comp.title}
                </span>
                <div class="border-b border-slate-500 w-full my-0.5"></div>
                <span class="text-[6.5px] text-slate-500 leading-tight truncate">{comp.certificationText}</span>
              </div>
            {/if}

            <!-- Selection Chip on top-right -->
            {#if isPrimarySelected}
              <div class="absolute -top-3.5 right-1 px-1.5 py-px rounded bg-slate-900 text-[7px] font-mono font-bold text-emerald-400 shadow-sm pointer-events-none flex items-center gap-1 z-40">
                <span>{componentLabel(comp)}</span>
                {#if comp.isLocked}
                  <Lock class="size-2 text-amber-400" />
                {/if}
              </div>
            {/if}

            <!-- Lock indicator when unselected but locked -->
            {#if comp.isLocked && !isSelected}
              <div class="absolute -top-1 -left-1 size-3.5 rounded-full bg-amber-100 border border-amber-300 flex items-center justify-center text-amber-700 pointer-events-none z-20">
                <Lock class="size-2" />
              </div>
            {/if}

            <!-- Resize Handle for Text Components -->
            {#if isText && isPrimarySelected && !comp.isLocked}
              <div
                class="absolute -bottom-1.5 -right-1.5 size-3.5 bg-emerald-600 border-2 border-white rounded-full cursor-se-resize shadow-md pointer-events-auto z-40"
                onmousedown={(e) => handleComponentResizeMouseDown(e, comp.id)}
                role="button"
                tabindex="0"
                title="Drag to resize width and height"
              ></div>
            {/if}
          </div>
        {/each}
      {/if}

      <!-- STAGE 3+ Multi-Section grids (for Section Bubbles stage) -->
      {#if currentStage >= 3}
        {#each sections as sec, i}
          {@const isSelected = activeSectionIndex === i}
          {@const labels = getOptionLabels(sec)}
          {@const isRoll = sec.type === 'roll'}
          {@const bPitchMm = Number(sec.bubblePitchMm) || (isRoll ? 5.5 : 5.2)}
          {@const rPitchMm = Number(sec.rowPitchMm) || (isRoll ? 4.8 : 6.8)}
          {@const stageBubblePx = isRoll ? ((Number(sec.bubbleDiameterMm) || 4.5) * 2.476) : CANVAS_RENDER_BUBBLE_PX}
          {@const bubbleGapPx = Math.max(1.5, (bPitchMm * 2.476) - stageBubblePx)}
          {@const rowGapPx = Math.max(0.5, (rPitchMm * 2.476) - stageBubblePx)}
          {@const digitsCount = Number(sec.questionsCount) || (isRoll ? 7 : 25)}
          {@const visualBounds = canvasSectionBoundsMm(labels.length, Number(sec.questionsCount) || (isRoll ? 10 : 1), bPitchMm, rPitchMm, isRoll, Number(sec.bubbleDiameterMm) || 4.5)}
          {@const blockWidthMm = visualBounds.w_mm}
          {@const blockHeightMm = visualBounds.h_mm}
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
            {#if isRoll}
              <!-- Roll / Examinee ID 0-9 Bubble Matrix -->
              <div class="p-1 w-full h-full flex flex-col justify-start" style={`gap: ${rowGapPx}px;`}>
                {#each Array.from({ length: 10 }) as _, digit}
                  <div class="flex items-center text-[7px] font-mono text-emerald-950 shrink-0 leading-none" style={`height: ${stageBubblePx}px;`}>
                    <span class="text-[6px] font-bold text-emerald-700 bg-emerald-100/80 border border-emerald-200 rounded px-0.5 text-center mr-1 shrink-0 flex items-center justify-center" style={`width: ${stageBubblePx}px; height: ${stageBubblePx}px;`}>
                      {digit}
                    </span>
                    <div class="flex items-center" style={`gap: ${bubbleGapPx}px;`}>
                      {#each Array.from({ length: digitsCount }) as _}
                        <div class="rounded-full border border-emerald-600/90 bg-emerald-500/25 text-emerald-950 flex items-center justify-center text-[5.5px] font-bold shadow-xs shrink-0" style={`width: ${stageBubblePx}px; height: ${stageBubblePx}px;`}>
                        </div>
                      {/each}
                    </div>
                  </div>
                {/each}
              </div>
              <div
                class={`absolute top-0.5 right-0.5 px-1 py-px rounded text-[6.5px] font-mono font-bold shadow-xs pointer-events-none select-none ${
                  isSelected ? 'bg-emerald-700/90 text-white' : 'bg-emerald-600/70 text-white'
                }`}
              >
                ID ({digitsCount} Digits)
              </div>
            {:else}
              <!-- Translucent Optical Target Rings with DYNAMIC Pitch Spacing (content-hug padding) -->
              <div
                class="p-1 w-full h-full flex flex-col justify-start"
                style={`gap: ${rowGapPx}px;`}
              >
                {#each Array.from({ length: Math.min(Number(sec?.questionsCount) || 1, 50) }) as _, r}
                  <div class="flex items-center text-[7px] font-mono text-emerald-950 shrink-0 leading-none" style={`height: ${stageBubblePx}px;`}>
                    <span class="text-[6px] font-bold text-emerald-700 bg-emerald-100/80 border border-emerald-200 rounded px-0.5 text-center mr-1 shrink-0 flex items-center justify-center" style={`width: 14px; height: ${stageBubblePx}px;`}>
                      {(Number(sec?.startQ) || 1) + r}
                    </span>
                    <div
                      class="flex items-center"
                      style={`gap: ${bubbleGapPx}px;`}
                    >
                      {#each labels as opt}
                        <div class="rounded-full border border-emerald-600/90 bg-emerald-500/25 text-emerald-950 flex items-center justify-center text-[6px] font-bold shadow-xs shrink-0" style={`width: ${stageBubblePx}px; height: ${stageBubblePx}px;`}>
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
            {/if}
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
    position: absolute;
    background: #ffffff;
  }
  .marker-target::after {
    content: '';
    position: absolute;
    inset: 4px;
    background-color: #0f172a;
    border-radius: 9999px;
  }
  .marker-aruco {
    width: 24px;
    height: 24px;
    border: 2px solid #0f172a;
    background:
      linear-gradient(90deg, #0f172a 25%, transparent 25% 50%, #0f172a 50% 75%, transparent 75%),
      linear-gradient(#0f172a 25%, transparent 25% 50%, #0f172a 50% 75%, transparent 75%);
    background-size: 8px 8px;
  }
</style>
