<script>
  import { RESULT_SHEET_BORDER_STYLE_OPTIONS } from '@/lib/resultSheetStyles';
  import NumericControl from '@/Components/studio/NumericControl.svelte';

  let {
    appearance = {},
    spacing = {},
    onAppearanceChanged = () => {},
    onSpacingChanged = () => {},
  } = $props();

  function changeAppearance(key, value) {
    onAppearanceChanged({ ...appearance, [key]: value });
  }

  function toggleBackground(event) {
    changeAppearance(
      'background_color',
      event.currentTarget.checked
        ? 'transparent'
        : appearance.background_color === 'transparent'
          ? '#ffffff'
          : appearance.background_color
    );
  }
</script>

<section class="space-y-2.5 border-t border-studio-border pt-3">
  <div class="flex items-center justify-between">
    <h4 class="text-[10px] font-bold uppercase tracking-wider text-studio-panel-muted-foreground">
      Appearance & spacing
    </h4>
    <span class="font-mono text-[9px] text-studio-panel-muted-foreground">
      {spacing.padding_mm ?? 2} mm pad
    </span>
  </div>

  <div class="space-y-2.5 rounded-lg border border-studio-border bg-studio-panel-muted p-2.5">
    <div class="flex items-center justify-between">
      <label class="flex items-center gap-1.5 text-[10px] font-semibold text-studio-panel-muted-foreground cursor-pointer">
        <input
          type="checkbox"
          checked={appearance.background_color === 'transparent'}
          onchange={toggleBackground}
          class="rounded border border-studio-border text-studio-toolbar-foreground focus:ring-studio-focus"
        />
        <span>Transparent background</span>
      </label>
      <input
        type="color"
        disabled={appearance.background_color === 'transparent'}
        value={appearance.background_color === 'transparent' ? '#ffffff' : (appearance.background_color ?? '#ffffff')}
        oninput={(event) => changeAppearance('background_color', event.currentTarget.value)}
        class="size-7 cursor-pointer rounded border border-studio-border bg-studio-panel p-0.5 disabled:cursor-not-allowed disabled:opacity-40"
        aria-label="Background color"
      />
    </div>

    <div class="grid grid-cols-2 gap-2">
      <div class="space-y-1">
        <NumericControl
          label="Border width"
          min={0}
          max={4}
          step={1}
          unit="px"
          value={appearance.border_width_px ?? 0}
          oninput={(val) => changeAppearance('border_width_px', val)}
          oncommit={(val) => changeAppearance('border_width_px', val)}
        />
      </div>
      <div>
        <label for="appearance-border-style" class="block text-[10px] font-semibold text-studio-panel-muted-foreground mb-1">
          Border style
        </label>
        <select
          id="appearance-border-style"
          class="w-full rounded border border-studio-border bg-studio-panel px-2 py-1 text-xs text-studio-panel-foreground"
          value={appearance.border_style ?? 'solid'}
          onchange={(event) => changeAppearance('border_style', event.currentTarget.value)}
        >
          {#each RESULT_SHEET_BORDER_STYLE_OPTIONS as option}
            <option value={option.value}>{option.label}</option>
          {/each}
        </select>
      </div>
    </div>

    <div class="grid grid-cols-2 gap-2 items-end">
      <div>
        <label for="appearance-border-color" class="block text-[10px] font-semibold text-studio-panel-muted-foreground mb-1">
          Border color
        </label>
        <input
          id="appearance-border-color"
          class="h-7 w-full cursor-pointer rounded border border-studio-border bg-studio-panel p-0.5"
          type="color"
          value={appearance.border_color ?? '#cbd5e1'}
          oninput={(event) => changeAppearance('border_color', event.currentTarget.value)}
          aria-label="Border color"
        />
      </div>
      <div>
        <NumericControl
          label="Radius"
          min={0}
          max={8}
          step={0.5}
          unit="mm"
          value={appearance.border_radius_mm ?? 0}
          oninput={(val) => changeAppearance('border_radius_mm', val)}
          oncommit={(val) => changeAppearance('border_radius_mm', val)}
        />
      </div>
    </div>
  </div>

  <div>
    <NumericControl
      label="Inner padding"
      min={0}
      max={12}
      step={0.5}
      unit="mm"
      value={spacing.padding_mm ?? 2}
      oninput={(val) => onSpacingChanged({ ...spacing, padding_mm: val })}
      oncommit={(val) => onSpacingChanged({ ...spacing, padding_mm: val })}
    />
  </div>
</section>
