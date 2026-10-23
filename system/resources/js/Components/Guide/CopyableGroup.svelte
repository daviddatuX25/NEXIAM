<script>
  import CopyableItem from './CopyableItem.svelte';

  let { items = [], subtitle = '', class: className = '' } = $props();
</script>

{#if items.length > 0}
  <div class="space-y-1.5 {className}">
    {#if subtitle}
      <p class="text-[11px] font-medium text-muted-foreground">{subtitle}</p>
    {/if}
    <div class="flex flex-wrap gap-1.5">
      {#each items as item}
        <CopyableItem
          value={typeof item === 'string' ? item : (item.placeholder ?? item.value ?? '')}
          label={typeof item === 'string' ? '' : (item.description ?? item.label ?? '')}
        />
      {/each}
    </div>
  </div>
{/if}
