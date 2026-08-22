<script>
  import { Bold, AlignLeft, AlignCenter, AlignRight } from 'lucide-svelte';
  import { FONT_FAMILY_OPTIONS } from '@/lib/omrComponents';

  let {
    typography = $bindable(null),
    onChanged = null,
  } = $props();

  function handleInput() {
    if (onChanged) onChanged();
  }

  function toggleBold() {
    if (!typography) return;
    typography.fontWeight = typography.fontWeight >= 700 ? 400 : 700;
    handleInput();
  }

  function setAlign(value) {
    if (!typography) return;
    typography.textAlign = value;
    handleInput();
  }
</script>

{#if typography}
  <div class="space-y-2 border-t border-slate-200/80 pt-2">
    <div class="flex items-center justify-between">
      <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wide">Typography</span>
    </div>
    <div class="grid grid-cols-2 gap-2">
      <div class="space-y-0.5">
        <label class="text-[10px] font-semibold text-slate-600 block">Font Size (pt)</label>
        <input
          type="number"
          min="6"
          max="28"
          step="0.5"
          bind:value={typography.fontSizePt}
          oninput={handleInput}
          class="w-full px-2 py-1 bg-white border border-slate-200 rounded-lg text-xs font-semibold"
        />
      </div>
      <div class="space-y-0.5">
        <label class="text-[10px] font-semibold text-slate-600 block">Font Family</label>
        <select
          bind:value={typography.fontFamily}
          onchange={handleInput}
          class="w-full px-2 py-1 bg-white border border-slate-200 rounded-lg text-[11px]"
        >
          {#each FONT_FAMILY_OPTIONS as font}
            <option value={font.value}>{font.label}</option>
          {/each}
        </select>
      </div>
    </div>
    <div class="flex items-center gap-1.5">
      <button
        type="button"
        onclick={toggleBold}
        class={`p-1.5 rounded-lg border transition ${
          (typography.fontWeight ?? 400) >= 700
            ? 'bg-slate-900 text-white border-slate-900'
            : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-100'
        }`}
        title="Bold"
      >
        <Bold class="size-3.5" />
      </button>
      <div class="flex items-center bg-slate-100 rounded-lg border border-slate-200 p-0.5">
        <button
          type="button"
          onclick={() => setAlign('left')}
          class={`p-1 rounded-md transition ${typography.textAlign === 'left' ? 'bg-white shadow-xs' : 'text-slate-500'}`}
          title="Align left"
        >
          <AlignLeft class="size-3" />
        </button>
        <button
          type="button"
          onclick={() => setAlign('center')}
          class={`p-1 rounded-md transition ${typography.textAlign === 'center' ? 'bg-white shadow-xs' : 'text-slate-500'}`}
          title="Align center"
        >
          <AlignCenter class="size-3" />
        </button>
        <button
          type="button"
          onclick={() => setAlign('right')}
          class={`p-1 rounded-md transition ${typography.textAlign === 'right' ? 'bg-white shadow-xs' : 'text-slate-500'}`}
          title="Align right"
        >
          <AlignRight class="size-3" />
        </button>
      </div>
      <div class="flex items-center gap-1 ml-auto">
        <input
          type="color"
          bind:value={typography.textColor}
          oninput={handleInput}
          class="w-7 h-7 rounded-lg border border-slate-200 bg-white cursor-pointer p-0.5"
          title="Text color"
        />
        <span class="text-[9.5px] font-mono text-slate-500">{typography.textColor}</span>
      </div>
    </div>
  </div>
{/if}
