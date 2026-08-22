<script>
  let { quantity = 1, disabled = false, busy = false, onquantity = () => {}, onprint = () => {} } = $props();
</script>

<section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="blank-print-title">
  <h2 id="blank-print-title" class="font-semibold text-slate-950">Print blank instances</h2>
  <p class="mt-1 text-sm text-slate-600">Every page receives an opaque signed QR and checksummed display code. No applicant identity is printed.</p>
  <div class="mt-4 flex flex-wrap items-end gap-3"><label class="text-sm font-medium text-slate-700">Quantity<input class="mt-1 block w-28 rounded border-slate-300" type="number" min="1" max="10000" value={quantity} onchange={(event) => onquantity(Number(event.currentTarget.value))} /></label><button class="rounded bg-slate-950 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50" type="button" onclick={onprint} disabled={disabled || busy}>{busy ? 'Queueing…' : 'Queue blank print'}</button></div>
  <p class="mt-4 rounded bg-amber-50 p-3 text-xs text-amber-900">Print at 100% / actual size — do not fit to page. Requests render in deterministic chunks of 100 with ordered ZIP manifests.</p>
</section>
