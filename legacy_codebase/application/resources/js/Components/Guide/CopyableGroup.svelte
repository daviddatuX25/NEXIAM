<script lang="ts">
  import CopyableItem from './CopyableItem.svelte';
  import type { CopyableItemData } from './index';

  let { items, subtitle, class: className = '' } = $props<{
    items: CopyableItemData[];
    subtitle?: string;
    class?: string;
  }>();
</script>

<div class={className}>
  {#if subtitle}
    <p class="mb-2 text-sm text-muted-foreground">{subtitle}</p>
  {/if}
  <div role="list" class="flex flex-wrap gap-2">
    {#each items as item}
      <div role="listitem">
        <CopyableItem value={item.value} label={item.label} />
      </div>
    {/each}
  </div>
</div>
