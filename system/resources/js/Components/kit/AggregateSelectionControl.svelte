<script>
  import { CheckSquare, Square, MinusSquare, X } from 'lucide-svelte';
  import { Button } from '@/Components/ui/button';

  let {
    visibleCount = 0,
    visibleSelectedCount = 0,
    totalSelectedCount = 0,
    disabled = false,
    onSelectVisible = () => {},
    onDeselectVisible = () => {},
    onClearAll = () => {},
  } = $props();

  const isAllVisibleSelected = $derived(visibleCount > 0 && visibleSelectedCount === visibleCount);
  const isSomeVisibleSelected = $derived(visibleSelectedCount > 0 && !isAllVisibleSelected);
</script>

<div class="flex items-center gap-2 text-xs">
  <Button
    variant="outline"
    size="sm"
    class="min-h-9 gap-2"
    disabled={disabled}
    aria-label={isAllVisibleSelected ? 'Deselect visible items' : `Select visible items (${visibleCount})`}
    aria-pressed={isAllVisibleSelected ? 'true' : isSomeVisibleSelected ? 'mixed' : 'false'}
    onclick={isAllVisibleSelected ? onDeselectVisible : onSelectVisible}
  >
    {#if isAllVisibleSelected}
      <CheckSquare class="size-4 text-primary" />
      Deselect visible
    {:else if isSomeVisibleSelected}
      <MinusSquare class="size-4 text-primary" />
      Select visible ({visibleCount})
    {:else}
      <Square class="size-4 text-muted-foreground" />
      Select visible ({visibleCount})
    {/if}
  </Button>

  {#if totalSelectedCount > 0}
    <span class="font-mono font-semibold text-foreground bg-primary/10 px-2 py-1 rounded-md">
      {totalSelectedCount} selected
    </span>
    <Button
      variant="ghost"
      size="sm"
      class="h-8 px-2 text-muted-foreground hover:text-foreground"
      disabled={disabled}
      onclick={onClearAll}
    >
      <X class="size-3.5 mr-1" />
      Clear
    </Button>
  {/if}
</div>
