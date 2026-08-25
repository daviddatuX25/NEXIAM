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
    XCircle,
  } from 'lucide-svelte';

  let {
    sections = $bindable([]),
    activeSectionIndex = $bindable(0),
    markerProfiles = [],
    selectedMarkerProfileId = null,
    onMarkerProfileChange = null,
    previewRunning = false,
    previewError = '',
    mappingErrors = [],
    mappingDirty = false,
    canonicalPreview = null,
    previewSurface = $bindable('canonical'),
    onChanged = null,
    onBack = null,
    onVerify = null,
    onNext = null,
  } = $props();
  let selectedProfile = $derived(markerProfiles.find((profile) => Number(profile.id) === Number(selectedMarkerProfileId)) ?? null);

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
    if (onChanged) onChanged('mapping');
  }

  function handleLockToggle() {
    activeSection.isLocked = !activeSection.isLocked;
    if (onChanged) onChanged('lock');
  }
</script>

<div class="p-5 space-y-5 text-foreground [&_input]:border-input [&_input]:bg-background [&_input]:text-foreground [&_select]:border-input [&_select]:bg-background [&_select]:text-foreground">
  <div>
    <h2 class="text-sm font-bold text-foreground">Recognition Mapping</h2>
    <p class="text-[11px] text-muted-foreground mt-0.5">Place OMRChecker guides over the fixed printable sheet.</p>
  </div>

  <div class="space-y-2">
    <span class="text-[10px] font-bold uppercase tracking-wide text-muted-foreground">Add recognition block</span>
    <div class="grid grid-cols-2 gap-2">
      <button
        type="button"
        onclick={addSingleQuestionBlock}
        class="min-h-20 rounded-xl border border-border bg-card p-3 text-left transition hover:border-emerald-500/60 hover:bg-emerald-500/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500"
      >
        <span class="mb-2 flex size-7 items-center justify-center rounded-lg bg-muted text-muted-foreground">
          <Plus class="size-3.5" />
        </span>
        <span class="block text-[11px] font-bold text-foreground">Single question</span>
        <span class="mt-0.5 block text-[10px] leading-snug text-muted-foreground">Map one isolated item.</span>
      </button>
      <button
        type="button"
        onclick={addSection}
        class="min-h-20 rounded-xl border border-border bg-card p-3 text-left transition hover:border-emerald-500/60 hover:bg-emerald-500/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500"
      >
        <span class="mb-2 flex size-7 items-center justify-center rounded-lg bg-primary text-primary-foreground">
          <Scan class="size-3.5" />
        </span>
        <span class="block text-[11px] font-bold text-foreground">Question range</span>
        <span class="mt-0.5 block text-[10px] leading-snug text-muted-foreground">Map consecutive items.</span>
      </button>
    </div>
  </div>

  {#if previewRunning}
    <div class="px-3 py-2 rounded-xl bg-blue-500/10 border border-blue-500/30 text-[11px] text-blue-800 dark:text-blue-300">
      Rendering the canonical page and verifying its native OMR geometry…
    </div>
  {:else if previewError}
    <div class="px-3 py-2 rounded-xl bg-rose-500/10 border border-rose-500/30 text-[11px] text-rose-800 dark:text-rose-300">
      Recognition mapping needs attention: {previewError}
    </div>
  {:else if mappingErrors.length > 0}
    <div class="space-y-2 rounded-xl border border-rose-500/30 bg-rose-500/10 px-3 py-2 text-[11px] text-rose-800 dark:text-rose-300" role="alert" aria-live="assertive">
      <div class="flex items-center gap-1.5 font-bold">
        <XCircle class="size-3.5 shrink-0" />
        <span>Recognition mapping needs attention</span>
      </div>
      <ul class="space-y-1 pl-5">
        {#each mappingErrors as issue}
          <li class="list-disc">
            {issue.block && !String(issue.message).includes(issue.block) ? `${issue.block}: ` : ''}{issue.message}
          </li>
        {/each}
      </ul>
      <p class="text-[10px] opacity-80">Move the listed guide or restore its last valid position, then save again.</p>
    </div>
  {:else if mappingDirty}
    <div class="px-3 py-2 rounded-xl bg-amber-500/10 border border-amber-500/30 text-[11px] text-amber-800 dark:text-amber-300">
      Mapping changes are saved. Run the recognition test to refresh Aligned Check and Recognition Overlay.
    </div>
  {:else if canonicalPreview}
    <div class="px-3 py-2 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-[11px] text-emerald-800 dark:text-emerald-300">
      Native OMR geometry verified against the fixed printable sheet. Green guides are mapping aids only.
    </div>
  {:else}
    <div class="px-3 py-2 rounded-xl bg-muted border border-border text-[11px] text-muted-foreground">
      The printable source page is ready. Add recognition blocks, then run the recognition test.
    </div>
  {/if}

  {#if canonicalPreview}
    <div class="grid grid-cols-3 rounded-lg border border-border bg-muted p-0.5" aria-label="Preview surface">
      {#each [
        ['canonical', 'Print Sheet'],
        ['aligned', 'Aligned Check'],
        ['overlay', 'Recognition Overlay'],
      ] as surface}
        <button
          type="button"
          onclick={() => { previewSurface = surface[0]; }}
          class={`rounded-md px-2 py-1.5 text-[10px] font-semibold transition ${
            previewSurface === surface[0] ? 'bg-card text-foreground shadow-xs' : 'text-muted-foreground hover:text-foreground'
          }`}
        >{surface[1]}</button>
      {/each}
    </div>
  {/if}

  <details class="group rounded-xl border border-border bg-card">
    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-3 py-2.5">
      <div class="min-w-0">
        <span class="block text-[11px] font-bold text-foreground">Page registration</span>
        <span class="block truncate text-[10px] text-muted-foreground">
          {selectedProfile ? `${selectedProfile.code} v${selectedProfile.version}` : 'Select the marker detector'}
        </span>
      </div>
      <span class={`shrink-0 rounded-full px-2 py-0.5 text-[9px] font-semibold ${
        selectedProfile?.status === 'qualified'
          ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'
          : 'bg-amber-500/10 text-amber-700 dark:text-amber-400'
      }`}>
        {selectedProfile?.status === 'qualified' ? 'Qualified' : 'Preview only'}
      </span>
    </summary>
    <div class="space-y-2 border-t border-border px-3 py-3">
      <p class="text-[10px] leading-relaxed text-muted-foreground">
        Controls how OMRChecker finds the four corner markers and straightens the paper. It does not define questions or answer keys.
      </p>
      {#if markerProfiles.length > 1}
        <select
          value={selectedMarkerProfileId ?? ''}
          onchange={(event) => onMarkerProfileChange?.(Number(event.currentTarget.value))}
          class="h-8 w-full rounded-md border border-input bg-background px-2 text-xs text-foreground"
        >
          <option value="" disabled>Select registration profile</option>
          {#each markerProfiles as profile}
            <option value={profile.id}>{profile.code} v{profile.version} · {profile.status}</option>
          {/each}
        </select>
      {/if}
      {#if selectedProfile?.status !== 'qualified'}
        <p class="text-[10px] text-amber-700 dark:text-amber-400">Preview is available; production compile remains blocked until physical calibration.</p>
      {/if}
    </div>
  </details>

  <!-- Recognition-guide explanation -->
  <div class="flex items-center gap-2 rounded-lg bg-muted/50 px-2.5 py-2 text-muted-foreground">
    <Scan class="size-3.5 shrink-0 text-emerald-600 dark:text-emerald-400" />
    <span class="text-[11px] font-medium text-foreground">Recognition guides</span>
    <StudioTip
      position="top"
      text="Green rings show the exact OMRChecker read coordinates over the fixed printed bubbles. They do not add or move printed content."
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
            ? 'bg-primary text-primary-foreground shadow-xs'
            : 'bg-muted text-muted-foreground hover:bg-muted/80 hover:text-foreground'
        }`}
      >
        {#if sec.type === 'roll'}
          <span>{sec.id} ({Number(sec.questionsCount) || 7} Digits)</span>
        {:else}
          <span>{sec.id} (Q{sec.startQ}&ndash;{Number(sec.startQ) + Number(sec.questionsCount) - 1})</span>
        {/if}
        {#if sec.isLocked}
          <Lock class="size-2.5 text-amber-400" />
        {/if}
      </button>
    {/each}
  </div>

  <!-- Active Section Detail Editor -->
  {#if activeSection}
    <div class="p-4 bg-muted/50 rounded-xl border border-border space-y-4 text-xs">
      <div class="flex items-center justify-between">
        <span class="font-bold text-foreground text-sm">{activeSection.name}</span>
        <div class="flex items-center space-x-2">
          <!-- Per-Section Lock Toggle -->
          <button
            type="button"
            onclick={handleLockToggle}
            class={`px-2 py-0.5 rounded text-[10px] font-semibold flex items-center gap-1 border transition ${
              activeSection.isLocked
                ? 'bg-amber-500/10 text-amber-700 dark:text-amber-400 border-amber-500/40'
                : 'bg-card text-foreground border-border hover:bg-muted'
            }`}
            title={activeSection.isLocked ? 'Unlock recognition guide for positioning' : 'Lock recognition guide in place'}
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

      <!-- Question Count / Digits Count & Start Number -->
      <div class="grid grid-cols-2 gap-3">
        <div class="space-y-1">
          <label class="font-semibold text-slate-700 block">
            {activeSection.type === 'roll' ? 'Digits Count' : 'Question Count'}
          </label>
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
          <label class="font-semibold text-slate-700 block">
            {activeSection.type === 'roll' ? 'Column Prefix' : 'Start Question #'}
          </label>
          {#if activeSection.type === 'roll'}
            <input
              type="text"
              bind:value={activeSection.id}
              oninput={handleInput}
              class="w-full px-2.5 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-semibold"
            />
          {:else}
            <input
              type="number"
              min="1"
              max="500"
              bind:value={activeSection.startQ}
              oninput={handleInput}
              class="w-full px-2.5 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-semibold"
            />
          {/if}
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
          <option value="roll">Examinee / Student ID Matrix (0–9 Digits)</option>
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
            min="4.5"
            max="25.0"
            step="0.1"
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
    {#if mappingDirty || previewError || !canonicalPreview}
      <button
        type="button"
        onclick={onVerify}
        disabled={previewRunning}
        class="px-4 py-2 bg-emerald-700 hover:bg-emerald-800 disabled:opacity-50 text-white font-semibold rounded-xl text-xs transition flex items-center gap-1.5 shadow-xs"
      >
        <Scan class="size-3.5" />
        <span>{previewRunning ? 'Running recognition test…' : 'Run recognition test'}</span>
      </button>
    {:else}
      <button
        type="button"
        onclick={onNext}
        class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white font-semibold rounded-xl text-xs transition flex items-center gap-1.5 shadow-xs"
      >
        <span>Continue to Verification</span>
        <ChevronRight class="size-3.5" />
      </button>
    {/if}
  </div>
</div>
