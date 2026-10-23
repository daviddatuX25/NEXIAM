<script>
  let {
    markerInsetsMm = $bindable({ top: 12, right: 12, bottom: 14, left: 12 }),
    disabled = false,
    onPreview = null,
    onCommit = null,
  } = $props();

  const sides = [
    { key: 'top', label: 'Top' },
    { key: 'right', label: 'Right' },
    { key: 'bottom', label: 'Bottom' },
    { key: 'left', label: 'Left' },
  ];

  function previewInset(side, event) {
    markerInsetsMm = {
      ...markerInsetsMm,
      [side]: Number(event.currentTarget.value),
    };
    onPreview?.(markerInsetsMm);
  }
</script>

<div class="space-y-2.5 rounded-xl border border-slate-200 bg-slate-50 p-2.5">
  <div class="space-y-0.5">
    <div class="flex items-center justify-between gap-2">
      <span class="text-[11px] font-bold text-slate-700">Fiducial Insets</span>
      {#if disabled}
        <span class="text-[9px] font-semibold text-amber-700">Select a profile first</span>
      {/if}
    </div>
    <p class="text-[9.5px] leading-relaxed text-slate-500">
      Moves the profile-owned bullseyes independently from the content margin. Marker shape remains scanner-controlled.
    </p>
  </div>

  <div class="grid grid-cols-2 gap-x-3 gap-y-2">
    {#each sides as side}
      <label class="min-w-0 space-y-1">
        <span class="flex items-center justify-between gap-2 text-[10px] font-semibold text-slate-600">
          <span>{side.label}</span>
          <span class="font-mono text-[9px] text-slate-700">{markerInsetsMm[side.key]} mm</span>
        </span>
        <input
          type="range"
          min="3"
          max="40"
          step="1"
          value={markerInsetsMm[side.key]}
          disabled={disabled}
          aria-label={`${side.label} fiducial inset`}
          oninput={(event) => previewInset(side.key, event)}
          onchange={() => onCommit?.(markerInsetsMm)}
          class="w-full accent-slate-900 disabled:cursor-not-allowed disabled:opacity-40"
        />
      </label>
    {/each}
  </div>
</div>
