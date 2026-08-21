<script>
  import {
    Plus,
    Minus,
    Lock,
    Unlock,
    QrCode,
  } from 'lucide-svelte';

  let {
    scale = $bindable(1.0),
    panX = $bindable(0),
    panY = $bindable(0),
    marginMm = 15,
    markerPattern = 'square',
    canvasDragLocked = $bindable(true),
    rasterUrl = null,
    showHeaderOverlay = true,
    headerTitle = 'EXAMINATION ANSWER SHEET',
    headerSubtitle = 'AUTOMATED OMR EVALUATION FORM',
    headerFontSize = 14.0,
    headerX = 25,
    headerY = 20,
    headerW = 160,
    headerH = 15,
    showQrOverlay = true,
    qrX = 150,
    qrY = 32,
    qrW = 32,
    qrH = 32,
    showInstructionsOverlay = true,
    instructionsContent = 'Use 2B pencil only.',
    instX = 25,
    instY = 250,
    instW = 90,
    instH = 28,
    showSignatureOverlay = true,
    signatureContent = 'EXAMINEE SIGNATURE',
    sigX = 125,
    sigY = 250,
    sigW = 60,
    sigH = 28,
    customTextItems = [],
    htmlContent = null,
    isComposing = false,
    sections = $bindable([]),
    activeSectionIndex = $bindable(0),
    currentStage = 1,
    onGeometryChanged = null,
  } = $props();

  let isPanning = $state(false);
  let panStartX = 0;
  let panStartY = 0;

  let isBlockDragging = $state(false);
  let isHandleDragging = $state(false);
  let dragBlockIndex = $state(null);
  let dragStartX = 0;
  let dragStartY = 0;
  let blockStartGeometry = { x: 0, y: 0, w: 0, h: 0 };

  function handleWheel(e) {
    e.preventDefault();
    const zoomFactor = e.deltaY < 0 ? 1.1 : 0.9;
    scale = Math.min(Math.max(0.4, scale * zoomFactor), 2.5);
  }

  function handleCanvasMouseDown(e) {
    if (e.target.closest('button, input, select, textarea')) return;
    if (isBlockDragging || isHandleDragging) return;
    isPanning = true;
    panStartX = e.clientX - panX;
    panStartY = e.clientY - panY;
  }

  function handleCanvasMouseMove(e) {
    if (isPanning) {
      panX = e.clientX - panStartX;
      panY = e.clientY - panStartY;
      return;
    }

    if (currentStage === 4 && !canvasDragLocked && isBlockDragging && dragBlockIndex !== null) {
      const dXmm = (e.clientX - dragStartX) / (2.2 * scale);
      const dYmm = (e.clientY - dragStartY) / (2.2 * scale);
      const sec = sections[dragBlockIndex];
      if (sec) {
        let nx = Math.round((blockStartGeometry.x + dXmm) / 2) * 2;
        let ny = Math.round((blockStartGeometry.y + dYmm) / 2) * 2;
        sec.posX = Math.max(marginMm, Math.min(210 - marginMm - sec.w_mm, nx));
        sec.posY = Math.max(marginMm + 20, Math.min(297 - marginMm - sec.h_mm, ny));
      }
    } else if (currentStage === 4 && !canvasDragLocked && isHandleDragging && dragBlockIndex !== null) {
      const dXmm = (e.clientX - dragStartX) / (2.2 * scale);
      const dYmm = (e.clientY - dragStartY) / (2.2 * scale);
      const sec = sections[dragBlockIndex];
      if (sec) {
        let nw = Math.round((blockStartGeometry.w + dXmm) / 2) * 2;
        let nh = Math.round((blockStartGeometry.h + dYmm) / 2) * 2;
        sec.w_mm = Math.max(35, Math.min(210 - sec.posX - marginMm, nw));
        sec.h_mm = Math.max(18, Math.min(297 - sec.posY - marginMm, nh));
      }
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

  function handleBlockMouseDown(e, index) {
    if (currentStage !== 4 || canvasDragLocked) return;
    e.stopPropagation();
    activeSectionIndex = index;
    isBlockDragging = true;
    dragBlockIndex = index;
    dragStartX = e.clientX;
    dragStartY = e.clientY;
    const sec = sections[index];
    blockStartGeometry = { x: sec.posX, y: sec.posY, w: sec.w_mm, h: sec.h_mm };
  }

  function handleResizeMouseDown(e, index) {
    if (currentStage !== 4 || canvasDragLocked) return;
    e.stopPropagation();
    activeSectionIndex = index;
    isHandleDragging = true;
    dragBlockIndex = index;
    dragStartX = e.clientX;
    dragStartY = e.clientY;
    const sec = sections[index];
    blockStartGeometry = { x: sec.posX, y: sec.posY, w: sec.w_mm, h: sec.h_mm };
  }

  function zoomStep(delta) {
    scale = Math.min(Math.max(0.4, scale + delta), 2.5);
  }

  function resetZoom() {
    scale = 1.0;
    panX = 0;
    panY = 0;
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

<div class="flex-1 bg-slate-100 flex flex-col relative overflow-hidden">
  <!-- Floating Canvas Controls -->
  <div class="absolute top-4 right-4 bg-white border border-slate-200/80 rounded-xl shadow-md p-1.5 flex items-center space-x-1.5 z-30">
    {#if currentStage === 4}
      <button
        type="button"
        onclick={() => { canvasDragLocked = !canvasDragLocked; }}
        class={`px-2 h-7 rounded-lg text-xs font-semibold transition flex items-center gap-1 ${
          canvasDragLocked ? 'bg-slate-100 text-slate-700' : 'bg-amber-100 text-amber-900 border border-amber-300'
        }`}
      >
        {#if canvasDragLocked}
          <Lock class="size-3" />
          <span>Locked</span>
        {:else}
          <Unlock class="size-3 text-amber-600" />
          <span>Unlocked</span>
        {/if}
      </button>
    {/if}

    <button
      type="button"
      onclick={() => zoomStep(-0.15)}
      title="Zoom Out"
      class="w-7 h-7 rounded-lg flex items-center justify-center text-slate-600 hover:text-slate-900 hover:bg-slate-100 transition"
    >
      <Minus class="size-3" />
    </button>
    <span class="text-xs font-mono font-semibold px-1.5 text-slate-700 min-w-[45px] text-center">
      {Math.round(scale * 100)}%
    </span>
    <button
      type="button"
      onclick={() => zoomStep(0.15)}
      title="Zoom In"
      class="w-7 h-7 rounded-lg flex items-center justify-center text-slate-600 hover:text-slate-900 hover:bg-slate-100 transition"
    >
      <Plus class="size-3" />
    </button>
    <button
      type="button"
      onclick={resetZoom}
      title="Reset Zoom Fit"
      class="px-2 h-7 rounded-lg text-xs font-semibold text-slate-600 hover:text-slate-900 hover:bg-slate-100 transition"
    >
      Fit
    </button>
  </div>

  <!-- Canvas Viewport -->
  <div
    class="w-full h-full overflow-hidden relative cursor-grab active:cursor-grabbing canvas-grid flex items-center justify-center"
    onwheel={handleWheel}
    onmousedown={handleCanvasMouseDown}
    onmousemove={handleCanvasMouseMove}
    onmouseup={handleCanvasMouseUp}
    onmouseleave={handleCanvasMouseUp}
    role="region"
    aria-label="OMR sheet design viewport"
  >
    <!-- Scalable Paper Document Sheet (A4 Proportion: 520px x 735px) -->
    <div
      class="bg-white shadow-2xl rounded-xs border border-slate-300 transition-transform origin-center relative shrink-0 select-none"
      style={`width: 520px; height: 735px; transform: translate(${panX}px, ${panY}px) scale(${scale});`}
    >
      <!-- Continuous Background Layer (HTML preview OR raster image OR clean paper) -->
      {#if rasterUrl}
        <img
          src={rasterUrl}
          alt="Composed Page"
          class="absolute inset-0 w-full h-full object-contain pointer-events-none opacity-85"
        />
      {:else if htmlContent}
        <iframe
          srcdoc={htmlContent}
          title="HTML Source Preview"
          class="absolute inset-0 w-full h-full border-none pointer-events-none rounded-xs"
          sandbox=""
        ></iframe>
      {/if}
      <!-- STAGE 2+: 4 Corner Registration Fiducial Markers -->
      {#if currentStage >= 2}
        <div
          class={`absolute ${markerPattern === 'target' ? 'marker-target' : 'marker-box'}`}
          style={`top: ${marginMm * 2.47}px; left: ${marginMm * 2.47}px;`}
        ></div>
        <div
          class={`absolute ${markerPattern === 'target' ? 'marker-target' : 'marker-box'}`}
          style={`top: ${marginMm * 2.47}px; right: ${marginMm * 2.47}px;`}
        ></div>
        <div
          class={`absolute ${markerPattern === 'target' ? 'marker-target' : 'marker-box'}`}
          style={`bottom: ${marginMm * 2.47}px; left: ${marginMm * 2.47}px;`}
        ></div>
        <div
          class={`absolute ${markerPattern === 'target' ? 'marker-target' : 'marker-box'}`}
          style={`bottom: ${marginMm * 2.47}px; right: ${marginMm * 2.47}px;`}
        ></div>
      {/if}
      <!-- STAGE 2+: Overlays (Header, Signature, Instructions, Custom text) -->
      {#if currentStage >= 2}
        <!-- Document Header Overlay -->
        {#if showHeaderOverlay}
          <div
            class="absolute flex justify-between items-center pb-2 border-b-2 border-slate-900"
            style={`top: ${headerY * 2.47}px; left: ${headerX * 2.47}px; width: ${headerW * 2.47}px;`}
          >
            <div>
              <h3 class="font-bold tracking-tight text-slate-900" style={`font-size: ${headerFontSize * 0.9}px;`}>
                {headerTitle}
              </h3>
              <p class="text-[9px] text-slate-500 font-mono">{headerSubtitle}</p>
            </div>
            {#if showQrOverlay}
              <div
                class="border border-slate-300 rounded flex flex-col items-center justify-center bg-slate-50 p-1 shrink-0"
                style={`width: ${qrW * 1.5}px; height: ${qrH * 1.5}px;`}
              >
                <QrCode class="size-6 text-slate-800" />
                <span class="text-[7px] font-mono text-slate-500 mt-0.5">INSTANCE QR</span>
              </div>
            {/if}
          </div>
        {/if}

        <!-- Instructions Box Overlay -->
        {#if showInstructionsOverlay}
          <div
            class="absolute border border-slate-300 p-2 rounded bg-slate-50/80 text-[8px] leading-tight text-slate-700"
            style={`top: ${instY * 2.47}px; left: ${instX * 2.47}px; width: ${instW * 2.47}px;`}
          >
            <span class="font-bold text-slate-900 uppercase block mb-0.5">Instructions:</span>
            {instructionsContent}
          </div>
        {/if}

        <!-- Signature Box Overlay -->
        {#if showSignatureOverlay}
          <div
            class="absolute border-2 border-slate-400 p-2 rounded bg-white flex flex-col justify-between"
            style={`top: ${sigY * 2.47}px; left: ${sigX * 2.47}px; width: ${sigW * 2.47}px; height: ${sigH * 2.47}px;`}
          >
            <span class="text-[7px] font-bold uppercase tracking-wider text-slate-500">{signatureContent}</span>
            <div class="border-b border-dashed border-slate-400 w-full mb-1"></div>
          </div>
        {/if}

        <!-- Custom Text Items Overlay -->
        {#each customTextItems as t}
          <div
            class="absolute text-slate-800 font-medium leading-tight"
            style={`top: ${t.posY * 2.47}px; left: ${t.posX * 2.47}px; width: ${t.w_mm * 2.47}px; font-size: ${(t.fontSize ?? 10) * 0.9}px;`}
          >
            {t.content}
          </div>
        {/each}
      {/if}

      <!-- STAGE 3+: Multi-Section Question Bubble Grids (Rendered in Step 3 & 4) -->
      {#if currentStage >= 3}
        {#each sections as sec, i}
          {@const isSelected = activeSectionIndex === i}
          {@const labels = getOptionLabels(sec)}
          <div
            class={`absolute rounded border transition-all ${
              isSelected
                ? 'border-blue-500 bg-blue-500/5 ring-2 ring-blue-400/50 z-20'
                : 'border-slate-300 bg-white/70 hover:border-slate-400 z-10'
            }`}
            style={`top: ${sec.posY * 2.47}px; left: ${sec.posX * 2.47}px; width: ${sec.w_mm * 2.47}px; height: ${sec.h_mm * 2.47}px;`}
            onmousedown={(e) => handleBlockMouseDown(e, i)}
            role="button"
            tabindex="0"
          >
            <!-- Section Header Chip -->
            <div class="px-1.5 py-0.5 bg-slate-900 text-white rounded-t-xs text-[8px] font-bold font-mono flex justify-between items-center">
              <span>{sec.id} (Q{sec.startQ}&ndash;{sec.startQ + sec.questionsCount - 1})</span>
              <span class="opacity-70">{sec.bubblePitchMm}mm / {sec.rowPitchMm}mm</span>
            </div>

            <!-- Bubble Rows Preview -->
            <div class="p-1.5 space-y-1 overflow-hidden h-[calc(100%-18px)]">
              {#each Array.from({ length: Math.max(1, Math.min(Number(sec?.questionsCount) || 1, 12)) }) as _, r}
                <div class="flex items-center justify-between text-[8px] font-mono text-slate-700">
                  <span class="w-4 font-bold">{(Number(sec?.startQ) || 1) + r}</span>
                  <div class="flex items-center space-x-1.5">
                    {#each labels as opt}
                      <div class="w-3.5 h-3.5 rounded-full border border-slate-700 flex items-center justify-center text-[7px] font-bold text-slate-800 bg-white">
                        {opt}
                      </div>
                    {/each}
                  </div>
                </div>
              {/each}
              {#if (Number(sec?.questionsCount) || 0) > 12}
                <div class="text-[7px] font-mono text-slate-400 text-center italic mt-0.5">
                  + {(Number(sec?.questionsCount) || 0) - 12} more rows
                </div>
              {/if}
            </div>

            <!-- Resize Handle -->
            {#if isSelected && !canvasDragLocked}
              <div
                class="absolute -bottom-1.5 -right-1.5 w-3.5 h-3.5 bg-blue-600 border border-white rounded-full cursor-se-resize shadow-xs"
                onmousedown={(e) => handleResizeMouseDown(e, i)}
                role="button"
                tabindex="0"
              ></div>
            {/if}
          </div>
        {/each}
      {/if}

      <!-- Stage 3 / Compose Building Loading Overlay -->
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
