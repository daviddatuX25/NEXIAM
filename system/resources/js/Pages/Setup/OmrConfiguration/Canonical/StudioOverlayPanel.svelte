<script>
  import {
    Plus,
    Trash2,
    QrCode,
    ChevronLeft,
    ChevronRight,
  } from 'lucide-svelte';

  let {
    marginMm = $bindable(15),
    markerPattern = $bindable('square'),
    showHeaderOverlay = $bindable(true),
    headerTitle = $bindable('EXAMINATION ANSWER SHEET'),
    headerFontSize = $bindable(14.0),
    showSignatureOverlay = $bindable(true),
    signatureContent = $bindable('EXAMINEE SIGNATURE'),
    showInstructionsOverlay = $bindable(true),
    instructionsContent = $bindable('Use 2B pencil only. Shade bubbles completely.'),
    showQrOverlay = $bindable(true),
    customTextItems = $bindable([]),
    onChanged = null,
    onBack = null,
    onNext = null,
  } = $props();

  function addCustomText() {
    const nextId = customTextItems.length + 1;
    customTextItems = [
      ...customTextItems,
      {
        id: `custom_text_${nextId}`,
        name: `Text ${nextId}`,
        content: 'Additional Instruction / Note',
        fontSize: 10.0,
        posX: 25,
        posY: Math.min(260, 230 + nextId * 10),
        w_mm: 80,
        h_mm: 15,
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

<div class="p-6 space-y-6">
  <div>
    <h2 class="text-base font-bold text-slate-900">Overlays & Margins</h2>
    <p class="text-xs text-slate-500 mt-1">Configure physical margins, registration markers, and custom printable overlay elements.</p>
  </div>

  <!-- Physical Insets Slider -->
  <div class="space-y-2">
    <div class="flex justify-between text-xs font-semibold text-slate-700">
      <span>Physical Margin Inset</span>
      <span class="font-mono bg-slate-100 px-1.5 py-0.5 rounded">{marginMm} mm</span>
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
    <p class="text-[10px] text-slate-400">Quiet zone from paper edge to corner fiducial registration quad.</p>
  </div>

  <!-- Marker Profile Picker -->
  <div class="space-y-2 border-t border-slate-100 pt-4">
    <span class="text-xs font-bold text-slate-800 uppercase tracking-wider block">Fiducial Marker Pattern</span>
    <div class="grid grid-cols-3 gap-2">
      {#each ['square', 'target', 'aruco'] as pat}
        <button
          type="button"
          onclick={() => { markerPattern = pat; handleInput(); }}
          class={`py-2 px-2.5 rounded-xl border text-center transition font-semibold text-xs capitalize ${
            markerPattern === pat
              ? 'border-slate-900 bg-slate-900 text-white shadow-xs'
              : 'border-slate-200 hover:border-slate-300 text-slate-700 bg-slate-50/50'
          }`}
        >
          {pat}
        </button>
      {/each}
    </div>
  </div>

  <!-- Printable Overlay Elements -->
  <div class="space-y-4 border-t border-slate-100 pt-4">
    <div class="flex items-center justify-between">
      <span class="text-xs font-bold text-slate-800 uppercase tracking-wider">Printable Overlays</span>
      <button
        type="button"
        onclick={addCustomText}
        class="text-[11px] font-semibold text-blue-600 hover:text-blue-800 flex items-center gap-1"
      >
        <Plus class="size-3" />
        <span>Add Text</span>
      </button>
    </div>

    <!-- Header Title Overlay -->
    <div class="p-3 bg-slate-50 rounded-xl border border-slate-200/80 space-y-2">
      <div class="flex items-center justify-between">
        <span class="text-xs font-bold text-slate-900">Document Header</span>
        <input type="checkbox" bind:checked={showHeaderOverlay} onchange={handleInput} class="rounded text-slate-900" />
      </div>
      {#if showHeaderOverlay}
        <div class="space-y-1.5 pt-1">
          <input
            type="text"
            bind:value={headerTitle}
            oninput={handleInput}
            placeholder="Exam Title"
            class="w-full text-xs font-semibold px-2.5 py-1.5 border border-slate-200 rounded-lg bg-white"
          />
          <div class="flex items-center justify-between gap-2">
            <span class="text-[10px] text-slate-500">Font Size ({headerFontSize}pt)</span>
            <input
              type="range"
              min="10"
              max="24"
              step="0.5"
              bind:value={headerFontSize}
              oninput={handleInput}
              class="w-24 accent-slate-900"
            />
          </div>
        </div>
      {/if}
    </div>

    <!-- Signature Box Overlay -->
    <div class="p-3 bg-slate-50 rounded-xl border border-slate-200/80 space-y-2">
      <div class="flex items-center justify-between">
        <span class="text-xs font-bold text-slate-900">Candidate Signature Box</span>
        <input type="checkbox" bind:checked={showSignatureOverlay} onchange={handleInput} class="rounded text-slate-900" />
      </div>
      {#if showSignatureOverlay}
        <div class="space-y-1.5 pt-1">
          <input
            type="text"
            bind:value={signatureContent}
            oninput={handleInput}
            placeholder="Signature Label"
            class="w-full text-xs px-2.5 py-1.5 border border-slate-200 rounded-lg bg-white"
          />
        </div>
      {/if}
    </div>

    <!-- Instructions Box Overlay -->
    <div class="p-3 bg-slate-50 rounded-xl border border-slate-200/80 space-y-2">
      <div class="flex items-center justify-between">
        <span class="text-xs font-bold text-slate-900">Instructions Notice</span>
        <input type="checkbox" bind:checked={showInstructionsOverlay} onchange={handleInput} class="rounded text-slate-900" />
      </div>
      {#if showInstructionsOverlay}
        <div class="space-y-1.5 pt-1">
          <textarea
            bind:value={instructionsContent}
            oninput={handleInput}
            rows="2"
            placeholder="Instructions text"
            class="w-full text-xs px-2.5 py-1.5 border border-slate-200 rounded-lg bg-white resize-none"
          ></textarea>
        </div>
      {/if}
    </div>

    <!-- QR Code Overlay Reservation -->
    <div class="p-3 bg-slate-50 rounded-xl border border-slate-200/80 space-y-2">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-1.5">
          <QrCode class="size-3.5 text-slate-700" />
          <span class="text-xs font-bold text-slate-900">Blank Instance QR Reservation</span>
        </div>
        <input type="checkbox" bind:checked={showQrOverlay} onchange={handleInput} class="rounded text-slate-900" />
      </div>
      <p class="text-[10px] text-slate-500">
        Reserves a 28&ndash;35mm zone. Real signed cryptographic QR codes are composited at physical print time.
      </p>
    </div>

    <!-- Custom Text Annotations List -->
    {#if customTextItems.length > 0}
      <div class="space-y-2">
        <span class="text-xs font-bold text-slate-700">Custom Text Elements</span>
        {#each customTextItems as item, idx}
          <div class="p-2.5 bg-slate-50 border border-slate-200/80 rounded-xl space-y-1.5">
            <div class="flex items-center justify-between">
              <span class="text-[11px] font-semibold text-slate-800">{item.name}</span>
              <button
                type="button"
                onclick={() => removeCustomText(idx)}
                class="text-red-500 hover:text-red-700"
              >
                <Trash2 class="size-3" />
              </button>
            </div>
            <input
              type="text"
              bind:value={item.content}
              oninput={handleInput}
              class="w-full text-xs px-2 py-1 border border-slate-200 rounded bg-white"
            />
          </div>
        {/each}
      </div>
    {/if}
  </div>

  <!-- Sticky Bottom Navigation Footer -->
  <div class="sticky bottom-0 bg-white border-t border-slate-200 p-4 -mx-6 -mb-6 flex items-center justify-between gap-3 shadow-xs">
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
      <span>Continue to Question Bubbles</span>
      <ChevronRight class="size-3.5" />
    </button>
  </div>
</div>
