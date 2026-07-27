<script>
  import ViewModeToggle from '@/Components/ViewModeToggle.svelte';
  
  let { viewMode = $bindable('responsive'), table, cards } = $props();
</script>

<div class="space-y-3">
  <div class="flex justify-end">
    <ViewModeToggle bind:value={viewMode} />
  </div>

  <div class="min-w-0">
    <div
      class="w-full min-w-0 overflow-x-scroll overscroll-x-contain {viewMode === 'cards'
        ? 'hidden'
        : viewMode === 'table'
          ? 'block'
          : 'hidden md:block'}"
    >
      {@render table()}
    </div>
  </div>

  <div
    class="{viewMode === 'table'
      ? 'hidden'
      : viewMode === 'cards'
        ? 'block'
        : 'block md:hidden'} p-4"
  >
    {@render cards()}
  </div>
</div>
