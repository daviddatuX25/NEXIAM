<script>
  import ViewModeToggle from '@/Components/kit/ViewModeToggle.svelte';

  let {
    viewMode = $bindable('responsive'),
    table,
    cards,
    controls = null,
    cardsInset = false,
  } = $props();

  let mode = $state(viewMode ?? 'responsive');

  function setMode(m) {
    mode = m;
    try {
      viewMode = m;
    } catch {
      // ignore
    }
  }
</script>

<div class="space-y-3">
  <div class="flex flex-wrap items-center justify-between gap-3">
    <div>
      {#if controls}
        {@render controls()}
      {/if}
    </div>
    <div class="ml-auto">
      <ViewModeToggle value={mode} onchange={setMode} />
    </div>
  </div>

  <div class="min-w-0">
    <div
      class="w-full min-w-0 overflow-x-scroll overscroll-x-contain {mode === 'cards'
        ? 'hidden'
        : mode === 'table'
          ? 'block'
          : 'hidden md:block'}"
    >
      {@render table()}
    </div>
  </div>

  <div
    class="{mode === 'table'
      ? 'hidden'
      : mode === 'cards'
        ? 'block'
        : 'block md:hidden'} min-w-0 {cardsInset ? 'p-4' : ''}"
  >
    {@render cards()}
  </div>
</div>
