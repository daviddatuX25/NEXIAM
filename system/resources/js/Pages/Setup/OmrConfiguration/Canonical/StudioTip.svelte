<script>
  import { Info } from 'lucide-svelte';

  let { text = '', position = 'top' } = $props();
</script>

<span
  class="group/tip relative inline-flex items-center cursor-help text-slate-400 hover:text-slate-600 focus-within:text-slate-600"
  tabindex="0"
  role="tooltip"
  aria-label={text}
>
  <Info class="size-3.5 shrink-0" />
  <span
    class="pointer-events-none absolute z-40 w-60 rounded-lg bg-slate-900 px-2.5 py-1.5 text-[10px] font-medium leading-relaxed text-white shadow-xl opacity-0 transition-opacity duration-150 group-hover/tip:opacity-100 group-focus-within/tip:opacity-100 {position === 'top'
      ? 'bottom-full left-1/2 mb-1.5 -translate-x-1/2'
      : 'top-full left-1/2 mt-1.5 -translate-x-1/2'}"
  >
    {text}
  </span>
</span>
