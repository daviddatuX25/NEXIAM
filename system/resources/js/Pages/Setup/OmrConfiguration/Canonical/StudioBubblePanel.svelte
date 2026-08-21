<script>
  import StudioTip from './StudioTip.svelte';
  import {
    Plus,
    Trash2,
    Lock,
    Unlock,
    ChevronLeft,
    ChevronRight,
    Scan,
  } from 'lucide-svelte';

  let {
    sections = $bindable([]),
    activeSectionIndex = $bindable(0),
    onChanged = null,
    onBack = null,
    onNext = null,
  } = $props();

  let activeSection = $derived(sections[activeSectionIndex] ?? sections[0]);

  function addSection() {
    const nextNum = sections.length + 1;
    const lastSec = sections[sections.length - 1];
    const nextStartQ = lastSec ? (Number(lastSec.startQ) + Number(lastSec.questionsCount)) : 1;

    const newSec = {
      id: `Section_${String.fromCharCode(64 + nextNum)}`,
      name: `Section ${String.fromCharCode(64 + nextNum)}`,
      type: 'mcq',
      posX: Math.min(125, 25 + (nextNum - 1) * 20),
      posY: 72,
      w_mm: 75,
      h_mm: 140,
      questionsCount: 20,
      cols: 1,
      optionsCount: 4,
      startQ: nextStartQ,
      customValuesStr: 'A, B, C, D',
      bubblePitchMm: 12.0,
      rowPitchMm: 6.5,
      isLocked: false,
    };

    sections = [...sections, newSec];
    activeSectionIndex = sections.length - 1;
    if (onChanged) onChanged();
  }

  function addSingleQuestionBlock() {
    const nextNum = sections.length + 1;
    const lastSec = sections[sections.length - 1];
    const nextStartQ = lastSec ? (Number(lastSec.startQ) + Number(lastSec.questionsCount)) : 1;

    const newSec = {
      id: `Question_${nextStartQ}`,
      name: `Question ${nextStartQ} (Callout / Single Q)`,
      type: 'mcq',
      posX: 25,
      posY: Math.min(220, 72 + (sections.length * 15)),
      w_mm: 75,
      h_mm: 18,
      questionsCount: 1,
      cols: 1,
      optionsCount: 4,
      startQ: nextStartQ,
      customValuesStr: 'A, B, C, D',
      bubblePitchMm: 12.0,
      rowPitchMm: 7.0,
      isLocked: false,
    };

    sections = [...sections, newSec];
    activeSectionIndex = sections.length - 1;
    if (onChanged) onChanged();
  }

  function removeSection(idx) {
    if (sections.length <= 1) return;
    sections = sections.filter((_, i) => i !== idx);
    activeSectionIndex = Math.max(0, idx - 1);
    if (onChanged) onChanged();
  }

  function handleInput() {
    if (onChanged) onChanged();
  }
</script>

<div class="p-5 space-y-5">
  <div class="flex items-center justify-between">
    <div>
      <h2 class="text-sm font-bold text-slate-900">Question Sections</h2>
      <p class="text-[11px] text-slate-500 mt-0.5">Define optical recognition grid zones.</p>
    </div>
    <div class="flex items-center space-x-1.5">
      <button
        type="button"
        onclick={addSingleQuestionBlock}
        class="px-2 py-1 text-[11px] font-semibold bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg transition"
        title="Add a single question callout block"
      >
        + Single Q
      </button>
      <button
        type="button"
        onclick={addSection}
        class="px-2.5 py-1 text-xs font-semibold bg-slate-900 hover:bg-slate-800 text-white rounded-lg transition flex items-center gap-1 shadow-xs"
      >
        <Plus class="size-3" />
        <span>Add Section</span>
      </button>
    </div>
  </div>

  <!-- OMR Optical Grid Notice (compact row; details in tooltip) -->
  <div class="flex items-center gap-1.5 px-1">
    <Scan class="size-3.5 text-blue-600 shrink-0" />
    <span class="text-xs font-semibold text-blue-900">OMR Optical Recognition Grid</span>
    <StudioTip
      position="top"
      text="These translucent blue halos define the optical sensor coordinates for OMR recognition. They align over your document's printed bubbles and do not add duplicate print text."
    />
  </div>

  <!-- Section Select Tabs -->
  <div class="flex flex-wrap gap-1.5">
    {#each sections as sec, i}
      <button
        type="button"
        onclick={() => { activeSectionIndex = i; }}
        class={`px-2.5 py-1 text-xs font-semibold rounded-lg transition flex items-center gap-1.5 ${
          activeSectionIndex === i
            ? 'bg-slate-900 text-white shadow-xs'
            : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
        }`}
      >
        <span>{sec.id} (Q{sec.startQ}&ndash;{Number(sec.startQ) + Number(sec.questionsCount) - 1})</span>
        {#if sec.isLocked}
          <Lock class="size-2.5 text-amber-400" />
        {/if}
      </button>
    {/each}
  </div>

  <!-- Active Section Detail Editor -->
  {#if activeSection}
    <div class="p-4 bg-slate-50 rounded-xl border border-slate-200 space-y-4 text-xs">
      <div class="flex items-center justify-between">
        <span class="font-bold text-slate-900 text-sm">{activeSection.name}</span>
        <div class="flex items-center space-x-2">
          <!-- Per-Section Lock Toggle -->
          <button
            type="button"
            onclick={() => { activeSection.isLocked = !activeSection.isLocked; handleInput(); }}
            class={`px-2 py-0.5 rounded text-[10px] font-semibold flex items-center gap-1 border transition ${
              activeSection.isLocked
                ? 'bg-amber-100 text-amber-900 border-amber-300'
                : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50'
            }`}
            title={activeSection.isLocked ? 'Unlock section to allow dragging & resizing' : 'Lock section in place'}
          >
            {#if activeSection.isLocked}
              <Lock class="size-2.5 text-amber-700" />
              <span>Locked</span>
            {:else}
              <Unlock class="size-2.5 text-slate-500" />
              <span>Unlocked</span>
            {/if}
          </button>

          {#if sections.length > 1}
            <button
              type="button"
              onclick={() => removeSection(activeSectionIndex)}
              class="text-red-500 hover:text-red-700 p-1"
              title="Delete section"
            >
              <Trash2 class="size-3.5" />
            </button>
          {/if}
        </div>
      </div>

      <!-- Question Count & Start Number -->
      <div class="grid grid-cols-2 gap-3">
        <div class="space-y-1">
          <label class="font-semibold text-slate-700 block">Question Count</label>
          <input
            type="number"
            min="1"
            max="100"
            bind:value={activeSection.questionsCount}
            oninput={handleInput}
            class="w-full px-2.5 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-semibold"
          />
        </div>
        <div class="space-y-1">
          <label class="font-semibold text-slate-700 block">Start Question #</label>
          <input
            type="number"
            min="1"
            max="500"
            bind:value={activeSection.startQ}
            oninput={handleInput}
            class="w-full px-2.5 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-semibold"
          />
        </div>
      </div>

      <!-- Option Type -->
      <div class="space-y-1">
        <label class="font-semibold text-slate-700 block">Option Format</label>
        <select
          bind:value={activeSection.type}
          onchange={handleInput}
          class="w-full px-2.5 py-1.5 bg-white border border-slate-200 rounded-lg text-xs"
        >
          <option value="mcq">Multiple Choice (4 Options: A, B, C, D)</option>
          <option value="mcq" selected={activeSection.optionsCount === 5}>Multiple Choice (5 Options: A, B, C, D, E)</option>
          <option value="tf">True / False (2 Options: T, F)</option>
          <option value="likert">Likert Scale (5 Options: 1, 2, 3, 4, 5)</option>
          <option value="custom">Custom Values</option>
        </select>
      </div>

      <!-- Pitch Spacing Sliders with clear explanations -->
      <div class="space-y-3 border-t border-slate-200/80 pt-3">
        <div class="space-y-1.5">
          <div class="flex justify-between font-semibold text-slate-700">
            <span class="flex items-center gap-1">
              Bubble Spacing (Horizontal)
              <StudioTip text="Horizontal center-to-center distance between option bubbles (A ↔ B ↔ C ↔ D)." />
            </span>
            <span class="font-mono bg-white border px-1.5 py-0.5 rounded">{activeSection.bubblePitchMm} mm</span>
          </div>
          <input
            type="range"
            min="6.0"
            max="25.0"
            step="0.5"
            bind:value={activeSection.bubblePitchMm}
            oninput={handleInput}
            class="w-full accent-slate-900"
          />
        </div>

        <!-- Row Spacing Slider (Only shown if questionsCount > 1; single question blocks have no row pitch) -->
        {#if (Number(activeSection.questionsCount) || 1) > 1}
          <div class="space-y-1.5">
            <div class="flex justify-between font-semibold text-slate-700">
              <span class="flex items-center gap-1">
                Row Spacing (Vertical)
                <StudioTip text="Vertical center-to-center distance between question rows (Q1 ↕ Q2)." />
              </span>
              <span class="font-mono bg-white border px-1.5 py-0.5 rounded">{activeSection.rowPitchMm} mm</span>
            </div>
            <input
              type="range"
              min="3.0"
              max="20.0"
              step="0.2"
              bind:value={activeSection.rowPitchMm}
              oninput={handleInput}
              class="w-full accent-slate-900"
            />
          </div>
        {/if}
      </div>

      <!-- Position (X, Y) Coordinates -->
      <div class="grid grid-cols-2 gap-3 border-t border-slate-200/80 pt-3">
        <div class="space-y-1">
          <label class="font-semibold text-slate-700 block">Origin X (mm)</label>
          <input
            type="number"
            min="10"
            max="180"
            bind:value={activeSection.posX}
            oninput={handleInput}
            class="w-full px-2.5 py-1.5 bg-white border border-slate-200 rounded-lg text-xs"
          />
        </div>
        <div class="space-y-1">
          <label class="font-semibold text-slate-700 block">Origin Y (mm)</label>
          <input
            type="number"
            min="20"
            max="260"
            bind:value={activeSection.posY}
            oninput={handleInput}
            class="w-full px-2.5 py-1.5 bg-white border border-slate-200 rounded-lg text-xs"
          />
        </div>
      </div>
    </div>
  {/if}

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
      <span>Test & Verify Sheet</span>
      <ChevronRight class="size-3.5" />
    </button>
  </div>
</div>
