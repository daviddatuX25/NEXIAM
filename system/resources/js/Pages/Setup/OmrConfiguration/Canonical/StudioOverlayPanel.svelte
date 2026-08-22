<script>
  import StudioTip from './StudioTip.svelte';
  import StudioFontPicker from './StudioFontPicker.svelte';
  import StudioQrReservationControls from './StudioQrReservationControls.svelte';
  import {
    Plus,
    Trash2,
    Lock,
    Unlock,
    ChevronLeft,
    ChevronRight,
    AlignLeft,
    AlignCenter,
    AlignRight,
    Type,
    Palette,
  } from 'lucide-svelte';

  let {
    marginMm = $bindable(15),
    markerPattern = $bindable('square'),
    showHeaderOverlay = $bindable(true),
    headerTitle = $bindable('EXAMINATION ANSWER SHEET'),
    headerSubtitle = $bindable('AUTOMATED OMR EVALUATION FORM'),
    headerFontSize = $bindable(14.0),
    headerFontFamily = $bindable('Inter, sans-serif'),
    headerColor = $bindable('#0f172a'),
    headerAlign = $bindable('left'),
    headerLocked = $bindable(false),
    showSignatureOverlay = $bindable(true),
    signatureContent = $bindable('EXAMINEE SIGNATURE'),
    signatureFontSize = $bindable(8.5),
    signatureFontFamily = $bindable('Inter, sans-serif'),
    signatureColor = $bindable('#475569'),
    sigLocked = $bindable(false),
    showInstructionsOverlay = $bindable(true),
    instructionsContent = $bindable('Use 2B pencil only. Shade bubbles completely.'),
    instructionsFontSize = $bindable(9.0),
    instructionsFontFamily = $bindable('Inter, sans-serif'),
    instructionsColor = $bindable('#334155'),
    instLocked = $bindable(false),
    showQrOverlay = $bindable(true),
    qrX = $bindable(162),
    qrY = $bindable(18),
    qrW = $bindable(26),
    qrH = $bindable(26),
    qrLocked = $bindable(false),
    customTextItems = $bindable([]),
    onChanged = null,
    onBack = null,
    onNext = null,
  } = $props();

  const PRESET_COLORS = [
    { name: 'Slate Dark', value: '#0f172a' },
    { name: 'Charcoal', value: '#334155' },
    { name: 'Muted', value: '#64748b' },
    { name: 'Navy', value: '#1e3a8a' },
    { name: 'Emerald', value: '#065f46' },
    { name: 'Crimson', value: '#991b1b' },
  ];

  function addCustomText() {
    const nextId = customTextItems.length + 1;
    customTextItems = [
      ...customTextItems,
      {
        id: `custom_text_${nextId}`,
        name: `Text ${nextId}`,
        content: 'Additional Instruction / Note',
        fontSize: 10.0,
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
    if (onChanged) onChanged();
  }

  function removeCustomText(idx) {
    customTextItems = customTextItems.filter((_, i) => i !== idx);
    if (onChanged) onChanged();
  }

  function handleInput() {
    if (onChanged) onChanged();
  }
</script>

<div class="p-5 space-y-5">
  <div>
    <h2 class="text-sm font-bold text-slate-900">Overlays & Margins</h2>
    <p class="text-[11px] text-slate-500 mt-0.5">Physical margins, fiducials, and customizable vector print typography.</p>
  </div>

  <!-- Physical Insets Slider -->
  <div class="space-y-1.5 p-3 bg-slate-50 rounded-xl border border-slate-200">
    <div class="flex justify-between text-xs font-semibold text-slate-700">
      <span class="flex items-center gap-1">
        Physical Margin Inset
        <StudioTip text="Quiet zone from paper edge to corner fiducial registration quad." />
      </span>
      <span class="font-mono bg-white border px-1.5 py-0.5 rounded">{marginMm} mm</span>
    </div>
    <input
      type="range"
      min="8"
      max="30"
      step="1"
      bind:value={marginMm}
      oninput={handleInput}
      class="w-full accent-slate-900"
    />
  </div>

  <!-- Marker Profile Picker -->
  <div class="space-y-1.5 border-t border-slate-200 pt-3">
    <span class="text-xs font-bold text-slate-800 uppercase tracking-wider block">Fiducial Marker Pattern</span>
    <div class="grid grid-cols-3 gap-1.5">
      {#each ['square', 'target', 'aruco'] as pat}
        <button
          type="button"
          onclick={() => { markerPattern = pat; handleInput(); }}
          class={`py-1.5 px-2 rounded-lg border text-center transition font-semibold text-xs capitalize ${
            markerPattern === pat
              ? 'border-slate-900 bg-slate-900 text-white shadow-xs'
              : 'border-slate-200 hover:border-slate-300 text-slate-700 bg-white'
          }`}
        >
          {pat}
        </button>
      {/each}
    </div>
  </div>

  <!-- Printable Overlay Elements with Individual Locks and Typography Controls -->
  <div class="space-y-3.5 border-t border-slate-200 pt-3">
    <div class="flex items-center justify-between">
      <span class="text-xs font-bold text-slate-800 uppercase tracking-wider">Printable Overlays</span>
      <button
        type="button"
        onclick={addCustomText}
        class="text-[11px] font-semibold text-blue-600 hover:text-blue-800 flex items-center gap-1 bg-blue-50 px-2 py-0.5 rounded-lg border border-blue-200/60"
      >
        <Plus class="size-3" />
        <span>Add Text</span>
      </button>
    </div>

    <!-- 1. Document Header Overlay -->
    <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-200 space-y-3">
      <div class="flex items-center justify-between">
        <label class="flex items-center space-x-2 cursor-pointer">
          <input type="checkbox" bind:checked={showHeaderOverlay} onchange={handleInput} class="rounded text-slate-900" />
          <span class="text-xs font-bold text-slate-900">Document Header</span>
        </label>
        {#if showHeaderOverlay}
          <button
            type="button"
            onclick={() => { headerLocked = !headerLocked; handleInput(); }}
            class={`px-2 py-0.5 rounded text-[10px] font-semibold flex items-center gap-1 border transition ${
              headerLocked ? 'bg-amber-100 text-amber-900 border-amber-300' : 'bg-white text-slate-700 border-slate-200'
            }`}
            title={headerLocked ? 'Header position is locked' : 'Header can be dragged and resized'}
          >
            {#if headerLocked}
              <Lock class="size-2.5 text-amber-700" />
              <span>Locked</span>
            {:else}
              <Unlock class="size-2.5 text-slate-500" />
              <span>Unlocked</span>
            {/if}
          </button>
        {/if}
      </div>

      {#if showHeaderOverlay}
        <div class="space-y-2.5 pt-1">
          <!-- Text Inputs -->
          <div class="space-y-1">
            <input
              type="text"
              bind:value={headerTitle}
              oninput={handleInput}
              placeholder="Header Title Text"
              class="w-full text-xs p-2 bg-white rounded-lg border border-slate-200 font-bold"
            />
            <input
              type="text"
              bind:value={headerSubtitle}
              oninput={handleInput}
              placeholder="Subtitle (e.g. Code, Batch, Form No.)"
              class="w-full text-[11px] p-1.5 bg-white rounded-lg border border-slate-200 font-mono text-slate-600"
            />
          </div>

          <!-- Typography Controls -->
          <div class="grid grid-cols-2 gap-2">
            <!-- Searchable Font Picker -->
            <StudioFontPicker
              bind:value={headerFontFamily}
              label="Title Font"
              onChanged={handleInput}
            />

            <!-- Font Size -->
            <div class="space-y-1">
              <div class="flex justify-between text-[11px] font-semibold text-slate-700">
                <span>Font Size</span>
                <span class="font-mono text-[10px] bg-white border px-1 rounded">{headerFontSize} pt</span>
              </div>
              <input
                type="range"
                min="9"
                max="28"
                step="0.5"
                bind:value={headerFontSize}
                oninput={handleInput}
                class="w-full accent-slate-900"
              />
            </div>
          </div>

          <!-- Color Swatches & Text Alignment -->
          <div class="flex items-center justify-between pt-1 border-t border-slate-200/60">
            <!-- Color Swatches -->
            <div class="flex items-center space-x-1">
              {#each PRESET_COLORS as c}
                <button
                  type="button"
                  onclick={() => { headerColor = c.value; handleInput(); }}
                  style={`background-color: ${c.value};`}
                  class={`size-4 rounded-full border transition ${
                    headerColor === c.value ? 'ring-2 ring-slate-900 scale-110' : 'border-white/80 opacity-80 hover:opacity-100'
                  }`}
                  title={c.name}
                ></button>
              {/each}
            </div>

            <!-- Alignment Buttons -->
            <div class="flex items-center space-x-0.5 bg-white border border-slate-200 rounded-lg p-0.5">
              {#each [
                { id: 'left', icon: AlignLeft },
                { id: 'center', icon: AlignCenter },
                { id: 'right', icon: AlignRight },
              ] as al}
                <button
                  type="button"
                  onclick={() => { headerAlign = al.id; handleInput(); }}
                  class={`p-1 rounded transition ${
                    headerAlign === al.id ? 'bg-slate-900 text-white' : 'text-slate-500 hover:text-slate-900'
                  }`}
                >
                  <al.icon class="size-3" />
                </button>
              {/each}
            </div>
          </div>
        </div>
      {/if}
    </div>

    <!-- 2. Candidate Signature Box -->
    <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-200 space-y-3">
      <div class="flex items-center justify-between">
        <label class="flex items-center space-x-2 cursor-pointer">
          <input type="checkbox" bind:checked={showSignatureOverlay} onchange={handleInput} class="rounded text-slate-900" />
          <span class="text-xs font-bold text-slate-900">Candidate Signature Box</span>
        </label>
        {#if showSignatureOverlay}
          <button
            type="button"
            onclick={() => { sigLocked = !sigLocked; handleInput(); }}
            class={`px-2 py-0.5 rounded text-[10px] font-semibold flex items-center gap-1 border transition ${
              sigLocked ? 'bg-amber-100 text-amber-900 border-amber-300' : 'bg-white text-slate-700 border-slate-200'
            }`}
          >
            {#if sigLocked}
              <Lock class="size-2.5 text-amber-700" />
              <span>Locked</span>
            {:else}
              <Unlock class="size-2.5 text-slate-500" />
              <span>Unlocked</span>
            {/if}
          </button>
        {/if}
      </div>

      {#if showSignatureOverlay}
        <div class="space-y-2.5 pt-1">
          <input
            type="text"
            bind:value={signatureContent}
            oninput={handleInput}
            placeholder="Signature Box Label"
            class="w-full text-xs p-2 bg-white rounded-lg border border-slate-200 font-semibold"
          />

          <div class="grid grid-cols-2 gap-2">
            <StudioFontPicker
              bind:value={signatureFontFamily}
              label="Font"
              onChanged={handleInput}
            />

            <div class="space-y-1">
              <div class="flex justify-between text-[11px] font-semibold text-slate-700">
                <span>Font Size</span>
                <span class="font-mono text-[10px] bg-white border px-1 rounded">{signatureFontSize} pt</span>
              </div>
              <input
                type="range"
                min="6"
                max="16"
                step="0.5"
                bind:value={signatureFontSize}
                oninput={handleInput}
                class="w-full accent-slate-900"
              />
            </div>
          </div>
        </div>
      {/if}
    </div>

    <!-- 3. Instructions Box -->
    <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-200 space-y-3">
      <div class="flex items-center justify-between">
        <label class="flex items-center space-x-2 cursor-pointer">
          <input type="checkbox" bind:checked={showInstructionsOverlay} onchange={handleInput} class="rounded text-slate-900" />
          <span class="text-xs font-bold text-slate-900">Instructions Notice</span>
        </label>
        {#if showInstructionsOverlay}
          <button
            type="button"
            onclick={() => { instLocked = !instLocked; handleInput(); }}
            class={`px-2 py-0.5 rounded text-[10px] font-semibold flex items-center gap-1 border transition ${
              instLocked ? 'bg-amber-100 text-amber-900 border-amber-300' : 'bg-white text-slate-700 border-slate-200'
            }`}
          >
            {#if instLocked}
              <Lock class="size-2.5 text-amber-700" />
              <span>Locked</span>
            {:else}
              <Unlock class="size-2.5 text-slate-500" />
              <span>Unlocked</span>
            {/if}
          </button>
        {/if}
      </div>

      {#if showInstructionsOverlay}
        <div class="space-y-2.5 pt-1">
          <textarea
            bind:value={instructionsContent}
            oninput={handleInput}
            rows="2"
            class="w-full text-xs p-2 bg-white rounded-lg border border-slate-200 font-medium"
          ></textarea>

          <div class="grid grid-cols-2 gap-2">
            <StudioFontPicker
              bind:value={instructionsFontFamily}
              label="Font"
              onChanged={handleInput}
            />

            <div class="space-y-1">
              <div class="flex justify-between text-[11px] font-semibold text-slate-700">
                <span>Font Size</span>
                <span class="font-mono text-[10px] bg-white border px-1 rounded">{instructionsFontSize} pt</span>
              </div>
              <input
                type="range"
                min="6"
                max="14"
                step="0.5"
                bind:value={instructionsFontSize}
                oninput={handleInput}
                class="w-full accent-slate-900"
              />
            </div>
          </div>
        </div>
      {/if}
    </div>

    <!-- 4. Blank Instance QR Box -->
    <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-200">
      <StudioQrReservationControls
        bind:showQrOverlay
        bind:qrX
        bind:qrY
        bind:qrW
        bind:qrH
        bind:qrLocked
        onChanged={handleInput}
      />
    </div>

    <!-- 5. Custom Text Elements -->
    {#each customTextItems as t, idx}
      <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-200 space-y-2.5">
        <div class="flex items-center justify-between">
          <span class="text-xs font-bold text-slate-900">{t.name || `Text ${idx + 1}`}</span>
          <div class="flex items-center space-x-1.5">
            <button
              type="button"
              onclick={() => { t.isLocked = !t.isLocked; handleInput(); }}
              class={`px-2 py-0.5 rounded text-[10px] font-semibold flex items-center gap-1 border transition ${
                t.isLocked ? 'bg-amber-100 text-amber-900 border-amber-300' : 'bg-white text-slate-700 border-slate-200'
              }`}
            >
              {#if t.isLocked}
                <Lock class="size-2.5 text-amber-700" />
                <span>Locked</span>
              {:else}
                <Unlock class="size-2.5 text-slate-500" />
                <span>Unlocked</span>
              {/if}
            </button>
            <button
              type="button"
              onclick={() => removeCustomText(idx)}
              class="text-red-500 hover:text-red-700 p-0.5"
            >
              <Trash2 class="size-3.5" />
            </button>
          </div>
        </div>

        <input
          type="text"
          bind:value={t.content}
          oninput={handleInput}
          placeholder="Custom text content"
          class="w-full text-xs p-2 bg-white rounded-lg border border-slate-200"
        />

        <div class="grid grid-cols-2 gap-2">
          <StudioFontPicker
            bind:value={t.fontFamily}
            label="Font"
            onChanged={handleInput}
          />

          <div class="space-y-1">
            <div class="flex justify-between text-[11px] font-semibold text-slate-700">
              <span>Font Size</span>
              <span class="font-mono text-[10px] bg-white border px-1 rounded">{t.fontSize ?? 10} pt</span>
            </div>
            <input
              type="range"
              min="6"
              max="24"
              step="0.5"
              bind:value={t.fontSize}
              oninput={handleInput}
              class="w-full accent-slate-900"
            />
          </div>
        </div>
      </div>
    {/each}
  </div>

  <!-- Bottom Navigation Buttons -->
  <div class="pt-3 border-t border-slate-200 flex items-center justify-between gap-3">
    <button
      type="button"
      onclick={onBack}
      class="px-3 py-2 border border-slate-200 hover:bg-slate-50 text-slate-700 font-semibold rounded-xl text-xs transition flex items-center gap-1.5"
    >
      <ChevronLeft class="size-3.5" />
      <span>Back</span>
    </button>
    <button
      type="button"
      onclick={onNext}
      class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white font-semibold rounded-xl text-xs transition flex items-center gap-1.5 shadow-xs"
    >
      <span>Question Bubbles</span>
      <ChevronRight class="size-3.5" />
    </button>
  </div>
</div>
