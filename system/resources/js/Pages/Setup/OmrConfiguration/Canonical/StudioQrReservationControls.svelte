<script>
  import { Lock, Unlock } from 'lucide-svelte';

  let {
    showQrOverlay = $bindable(true),
    qrX = $bindable(162),
    qrY = $bindable(18),
    qrW = $bindable(26),
    qrH = $bindable(26),
    qrLocked = $bindable(false),
    onChanged = null,
  } = $props();

  function notifyChanged() {
    if (onChanged) onChanged();
  }

  function setQrSize(event) {
    const size = Math.max(20, Math.min(50, Number(event.currentTarget.value) || 26));
    qrW = size;
    qrH = size;
    notifyChanged();
  }

  function toggleLock() {
    qrLocked = !qrLocked;
    notifyChanged();
  }
</script>

<div class="space-y-2.5">
  <div class="flex items-center justify-between">
    <label class="flex items-center gap-2 cursor-pointer">
      <input type="checkbox" bind:checked={showQrOverlay} onchange={notifyChanged} class="rounded text-slate-900" />
      <span class="text-[11px] font-bold text-slate-800">Blank Instance QR</span>
    </label>
    {#if showQrOverlay}
      <button
        type="button"
        onclick={toggleLock}
        class={`px-2 py-0.5 rounded text-[10px] font-semibold flex items-center gap-1 border transition ${
          qrLocked ? 'bg-amber-100 text-amber-900 border-amber-300' : 'bg-white text-slate-700 border-slate-200'
        }`}
        title={qrLocked ? 'Unlock QR reservation' : 'Lock QR reservation'}
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

  {#if showQrOverlay}
    <div class="grid grid-cols-2 gap-2 text-[10px]">
      <div>
        <label class="font-semibold text-slate-600 block mb-0.5">X (mm)</label>
        <input type="number" min="10" max="190" bind:value={qrX} oninput={notifyChanged} class="w-full px-2 py-1 bg-white border border-slate-200 rounded-lg text-xs" />
      </div>
      <div>
        <label class="font-semibold text-slate-600 block mb-0.5">Y (mm)</label>
        <input type="number" min="10" max="280" bind:value={qrY} oninput={notifyChanged} class="w-full px-2 py-1 bg-white border border-slate-200 rounded-lg text-xs" />
      </div>
    </div>
    <div class="space-y-1.5">
      <div class="flex items-center justify-between text-[10px] font-semibold text-slate-700">
        <span>QR Size</span>
        <span class="font-mono bg-white border px-1.5 py-0.5 rounded">{qrW} mm</span>
      </div>
      <input type="range" min="20" max="50" step="1" value={qrW} oninput={setQrSize} class="w-full accent-slate-900" aria-label="QR size in millimetres" />
      <p class="text-[9.5px] text-slate-500 leading-relaxed">Reserves a square area for the signed instance QR added at print time.</p>
    </div>
  {/if}
</div>
