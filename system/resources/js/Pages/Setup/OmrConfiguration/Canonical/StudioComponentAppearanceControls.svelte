<script>
  let {
    backgroundColor = $bindable('transparent'),
    onChanged = null,
  } = $props();

  function setTransparent(event) {
    if (event.currentTarget.checked) backgroundColor = 'transparent';
    else if (backgroundColor === 'transparent') backgroundColor = '#ffffff';
    if (onChanged) onChanged(backgroundColor);
  }

  function setColor(event) {
    backgroundColor = event.currentTarget.value;
    if (onChanged) onChanged(backgroundColor);
  }
</script>

<div class="space-y-1.5 bg-slate-50/80 p-2 rounded-xl border border-slate-100">
  <div class="flex items-center justify-between">
    <span class="text-[10px] font-semibold text-slate-600">Component Background</span>
    <span class="text-[9px] font-mono text-slate-400">{backgroundColor}</span>
  </div>
  <div class="flex items-center gap-2">
    <label class="flex items-center gap-1.5 text-[10px] text-slate-600 cursor-pointer">
      <input type="checkbox" checked={backgroundColor === 'transparent'} onchange={setTransparent} class="rounded text-slate-900" />
      Transparent
    </label>
    <input
      type="color"
      value={backgroundColor === 'transparent' ? '#ffffff' : backgroundColor}
      disabled={backgroundColor === 'transparent'}
      oninput={setColor}
      class="h-7 w-12 p-0.5 bg-white border border-slate-200 rounded cursor-pointer disabled:opacity-40"
      aria-label="Component background color"
    />
    <span class="text-[9px] text-slate-400">White / black / custom</span>
  </div>
</div>
