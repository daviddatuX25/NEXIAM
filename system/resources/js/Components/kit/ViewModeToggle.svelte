<script>
  import { MonitorSmartphone, Table2, LayoutGrid } from 'lucide-svelte';

  let { value = 'responsive', onchange = null } = $props();

  function selectMode(mode) {
    onchange?.(mode);
  }
</script>

<div
  class="inline-flex h-9 items-center rounded-md border border-border bg-muted/40 p-0.5 text-muted-foreground shadow-xs"
  role="group"
  aria-label="View layout"
  data-slot="toggle-group"
>
  <button
    type="button"
    class="inline-flex h-7 items-center justify-center rounded-[4px] px-2.5 text-xs font-medium transition-all {value === 'responsive' ? 'bg-background text-foreground shadow-xs font-semibold' : 'text-muted-foreground hover:text-foreground'}"
    aria-label="Auto (responsive)"
    title="Auto"
    data-state={value === 'responsive' ? 'on' : 'off'}
    data-value="responsive"
    onclick={() => selectMode('responsive')}
  >
    <MonitorSmartphone class="h-3.5 w-3.5 pointer-events-none" />
    <span class="sr-only">Auto</span>
  </button>
  <button
    type="button"
    class="inline-flex h-7 items-center justify-center rounded-[4px] px-2.5 text-xs font-medium transition-all {value === 'table' ? 'bg-background text-foreground shadow-xs font-semibold' : 'text-muted-foreground hover:text-foreground'}"
    aria-label="Table view"
    title="Table"
    data-state={value === 'table' ? 'on' : 'off'}
    data-value="table"
    onclick={() => selectMode('table')}
  >
    <Table2 class="h-3.5 w-3.5 pointer-events-none" />
    <span class="sr-only">Table</span>
  </button>
  <button
    type="button"
    class="inline-flex h-7 items-center justify-center rounded-[4px] px-2.5 text-xs font-medium transition-all {value === 'cards' ? 'bg-background text-foreground shadow-xs font-semibold' : 'text-muted-foreground hover:text-foreground'}"
    aria-label="Card view"
    title="Cards"
    data-state={value === 'cards' ? 'on' : 'off'}
    data-value="cards"
    onclick={() => selectMode('cards')}
  >
    <LayoutGrid class="h-3.5 w-3.5 pointer-events-none" />
    <span class="sr-only">Cards</span>
  </button>
</div>
