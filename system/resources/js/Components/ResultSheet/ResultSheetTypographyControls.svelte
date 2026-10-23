<script>
  import { Bold, AlignLeft, AlignCenter, AlignRight, AlignJustify } from 'lucide-svelte';
  import { RESULT_SHEET_FONT_OPTIONS, RESULT_SHEET_TEXT_ALIGN_OPTIONS } from '@/lib/resultSheetStyles';
  import NumericControl from '@/Components/studio/NumericControl.svelte';

  let { typography = {}, onChanged = () => {}, emailFlow = false } = $props();
  const emailFonts = ['Arial', 'Verdana', 'Georgia', 'Times New Roman', 'Tahoma', 'Trebuchet MS'].map((value) => ({ value, label: value }));

  function change(key, value) {
    onChanged({ ...typography, [key]: value });
  }
</script>

<section class="space-y-2.5 border-t border-studio-border pt-3">
  <div class="flex items-center justify-between">
    <h4 class="text-[10px] font-bold uppercase tracking-wider text-studio-panel-muted-foreground">Typography</h4>
    <span class="font-mono text-[9px] text-studio-panel-muted-foreground">{emailFlow ? (typography.font_size ?? 16) : (typography.font_size_pt ?? 10)} {emailFlow ? 'px' : 'pt'}</span>
  </div>

  <div class="grid grid-cols-2 gap-2">
    <div>
      <NumericControl
        label="Font size"
        min={emailFlow ? 8 : 6}
        max={emailFlow ? 72 : 48}
        step={emailFlow ? 1 : 0.5}
        unit={emailFlow ? 'px' : 'pt'}
        value={emailFlow ? (typography.font_size ?? 16) : (typography.font_size_pt ?? 10)}
        oninput={(val) => change(emailFlow ? 'font_size' : 'font_size_pt', val)}
        oncommit={(val) => change(emailFlow ? 'font_size' : 'font_size_pt', val)}
      />
    </div>
    <div>
      <label for="typography-font-family" class="block text-[10px] font-semibold text-studio-panel-muted-foreground mb-1">
        Font family
      </label>
      <select
        id="typography-font-family"
        class="w-full rounded border border-studio-border bg-studio-panel px-2 py-1 text-xs text-studio-panel-foreground"
        value={typography.font_family ?? RESULT_SHEET_FONT_OPTIONS[0].value}
        onchange={(event) => change('font_family', event.currentTarget.value)}
      >
        {#each (emailFlow ? emailFonts : RESULT_SHEET_FONT_OPTIONS) as font}
          <option value={font.value}>{font.label}</option>
        {/each}
      </select>
    </div>
  </div>

  <div class="flex items-center gap-2 pt-1">
    {#if !emailFlow}
    <button
      type="button"
      class={`rounded border p-1.5 transition ${
        Number(typography.font_weight) >= 700
          ? 'border-studio-focus bg-studio-toolbar text-studio-toolbar-foreground'
          : 'border-studio-border bg-studio-panel text-studio-panel-foreground hover:bg-studio-panel-muted'
      }`}
      title="Toggle bold"
      aria-label="Toggle bold"
      onclick={() => change('font_weight', Number(typography.font_weight) >= 700 ? 400 : 700)}
    >
      <Bold class="size-3.5" />
    </button>
    {/if}

    <div class="flex items-center rounded border border-studio-border bg-studio-panel-muted p-0.5">
      {#each RESULT_SHEET_TEXT_ALIGN_OPTIONS as option}
        <button
          type="button"
          class={`rounded p-1 transition ${
            typography.text_align === option.value
              ? 'bg-studio-panel shadow-sm text-studio-panel-foreground'
              : 'text-studio-panel-muted-foreground hover:text-studio-panel-foreground'
          }`}
          title={`Align ${option.label.toLowerCase()}`}
          aria-label={`Align ${option.label.toLowerCase()}`}
          onclick={() => change('text_align', option.value)}
        >
          {#if option.value === 'left'}
            <AlignLeft class="size-3" />
          {:else if option.value === 'center'}
            <AlignCenter class="size-3" />
          {:else if option.value === 'right'}
            <AlignRight class="size-3" />
          {:else}
            <AlignJustify class="size-3" />
          {/if}
        </button>
      {/each}
    </div>

    <label class="ml-auto flex items-center gap-1.5 text-[10px] text-studio-panel-muted-foreground font-semibold cursor-pointer">
      <span>Color</span>
      <input
        type="color"
        value={typography.text_color ?? '#0f172a'}
        oninput={(event) => change('text_color', event.currentTarget.value)}
        class="size-7 cursor-pointer rounded border border-studio-border bg-studio-panel p-0.5"
        aria-label="Text color"
      />
    </label>
  </div>

  {#if !emailFlow}
  <div class="grid grid-cols-2 gap-2">
    <div>
      <NumericControl
        label="Line height"
        min={0.8}
        max={2.4}
        step={0.05}
        unit=""
        value={typography.line_height ?? 1.2}
        oninput={(val) => change('line_height', val)}
        oncommit={(val) => change('line_height', val)}
      />
    </div>
    <div>
      <NumericControl
        label="Letter spacing"
        min={-2}
        max={8}
        step={0.5}
        unit="px"
        value={typography.letter_spacing_px ?? 0}
        oninput={(val) => change('letter_spacing_px', val)}
        oncommit={(val) => change('letter_spacing_px', val)}
      />
    </div>
  </div>
  {/if}
</section>
