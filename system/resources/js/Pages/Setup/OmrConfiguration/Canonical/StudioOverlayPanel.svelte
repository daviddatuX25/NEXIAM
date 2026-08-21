<script>
  import {
    Plus,
    Trash2,
    Lock,
    Unlock,
    ChevronLeft,
    ChevronRight,
  } from 'lucide-svelte';

  let {
    marginMm = $bindable(15),
    markerPattern = $bindable('square'),
    showHeaderOverlay = $bindable(true),
    headerTitle = $bindable('EXAMINATION ANSWER SHEET'),
    headerFontSize = $bindable(14.0),
    headerLocked = $bindable(false),
    showSignatureOverlay = $bindable(true),
    signatureContent = $bindable('EXAMINEE SIGNATURE'),
    sigLocked = $bindable(false),
    showInstructionsOverlay = $bindable(true),
    instructionsContent = $bindable('Use 2B pencil only. Shade bubbles completely.'),
    instLocked = $bindable(false),
    showQrOverlay = $bindable(true),
    qrLocked = $bindable(false),
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

  <!-- Printable Overlay Elements with Individual Locks -->
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
        <div class="flex items-center space-x-2">
          <input type="checkbox" bind:checked={showHeaderOverlay} onchange={handleInput} class="rounded text-slate-900" />
          <span class="text-xs font-bold text-slate-900">Document Header</span>
        </div>
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
        <div class="space-y-1.5 pt-1">
          <input
            type="text"
            bind:value={headerTitle}
            oninput={handleInput}
            placeholder="Header Title Text"
            class="w-full text-xs p-2 bg-white rounded-lg border border-slate-200 font-semibold"
          />
        </div>
      {/if}
    </div>

    <!-- Candidate Signature Box -->
    <div class="p-3 bg-slate-50 rounded-xl border border-slate-200/80 space-y-2">
      <div class="flex items-center justify-between">
        <div class="flex items-center space-x-2">
          <input type="checkbox" bind:checked={showSignatureOverlay} onchange={handleInput} class="rounded text-slate-900" />
          <span class="text-xs font-bold text-slate-900">Candidate Signature Box</span>
        </div>
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
        <div class="space-y-1.5 pt-1">
          <input
            type="text"
            bind:value={signatureContent}
            oninput={handleInput}
            placeholder="Signature Box Label"
            class="w-full text-xs p-2 bg-white rounded-lg border border-slate-200 font-semibold"
          />
        </div>
      {/if}
    </div>

    <!-- Instructions Box -->
    <div class="p-3 bg-slate-50 rounded-xl border border-slate-200/80 space-y-2">
      <div class="flex items-center justify-between">
        <div class="flex items-center space-x-2">
          <input type="checkbox" bind:checked={showInstructionsOverlay} onchange={handleInput} class="rounded text-slate-900" />
          <span class="text-xs font-bold text-slate-900">Instructions Notice</span>
        </div>
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
        <div class="space-y-1.5 pt-1">
          <textarea
            bind:value={instructionsContent}
            oninput={handleInput}
            rows="2"
            class="w-full text-xs p-2 bg-white rounded-lg border border-slate-200 font-medium"
          ></textarea>
        </div>
      {/if}
    </div>

    <!-- Blank Instance QR Box -->
    <div class="p-3 bg-slate-50 rounded-xl border border-slate-200/80 space-y-2">
      <div class="flex items-center justify-between">
        <div class="flex items-center space-x-2">
          <input type="checkbox" bind:checked={showQrOverlay} onchange={handleInput} class="rounded text-slate-900" />
          <span class="text-xs font-bold text-slate-900">Blank Instance QR Reservation</span>
        </div>
        {#if showQrOverlay}
          <button
            type="button"
            onclick={() => { qrLocked = !qrLocked; handleInput(); }}
            class={`px-2 py-0.5 rounded text-[10px] font-semibold flex items-center gap-1 border transition ${
              qrLocked ? 'bg-amber-100 text-amber-900 border-amber-300' : 'bg-white text-slate-700 border-slate-200'
            }`}
          >
            {#if qrLocked}
              <Lock class="size-2.5 text-amber-700" />
              <span>Locked</span>
            {:else}
              <Unlock class="size-2.5 text-slate-500" />
              <span>Unlocked</span>
            {/if}
          </button>
        {/if}
      </div>
      <p class="text-[10px] text-slate-400">
        Reserves a 28–35mm zone. Real signed cryptographic QR codes are composed at physical print time.
      </p>
    </div>

    <!-- Custom Text Elements -->
    {#each customTextItems as t, idx}
      <div class="p-3 bg-slate-50 rounded-xl border border-slate-200/80 space-y-2">
        <div class="flex items-center justify-between">
          <span class="text-xs font-bold text-slate-900">{t.name || `Text ${idx + 1}`}</span>
          <div class="flex items-center space-x-2">
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
              <Trash2 class="size-3" />
            </button>
          </div>
        </div>
        <input
          type="text"
          bind:value={t.content}
          oninput={handleInput}
          class="w-full text-xs p-2 bg-white rounded-lg border border-slate-200"
        />
      </div>
    {/each}
  </div>

  <!-- Bottom Navigation Buttons -->
  <div class="pt-4 border-t border-slate-200 flex items-center justify-between gap-3">
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
