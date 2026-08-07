<script>
  /**
   * Active filter chips row — clear individual filters or all.
   * Presentational; parent mutates filter state via callbacks.
   */
  import { X } from 'lucide-svelte';
  import { Badge } from '@/Components/ui/badge';
  import { Button } from '@/Components/ui/button';

  /**
   * @typedef {{ id: string, label: string }} Chip
   */

  let {
    chips = /** @type {Chip[]} */ ([]),
    onRemove = /** @type {(id: string) => void} */ (() => {}),
    onClearAll = undefined,
    class: className = '',
  } = $props();
</script>

{#if chips.length > 0}
  <div class="flex flex-wrap items-center gap-2 {className}" aria-label="Active filters">
    {#each chips as chip (chip.id)}
      <Badge variant="outline" class="gap-1 pr-1 font-normal">
        <span>{chip.label}</span>
        <button
          type="button"
          class="inline-flex size-5 items-center justify-center rounded-md hover:bg-muted"
          aria-label="Remove filter {chip.label}"
          onclick={() => onRemove(chip.id)}
        >
          <X class="h-3 w-3" />
        </button>
      </Badge>
    {/each}
    {#if onClearAll}
      <Button size="sm" variant="ghost" class="h-7 px-2 text-xs text-muted-foreground" onclick={onClearAll}>
        Clear all
      </Button>
    {/if}
  </div>
{/if}
