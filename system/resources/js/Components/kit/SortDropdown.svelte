<script>
  import { ArrowDown, ArrowUp } from 'lucide-svelte';
  import { Button } from '@/Components/ui/button';

  let {
    options = [],
    sort = '',
    dir = 'desc',
    onchange = null,
    label = 'Sort',
    class: className = '',
  } = $props();

  function handleSelectChange(event) {
    const nextSort = event.currentTarget.value;
    onchange?.({ sort: nextSort, dir });
  }

  function toggleDir() {
    const nextDir = dir === 'asc' ? 'desc' : 'asc';
    onchange?.({ sort, dir: nextDir });
  }
</script>

<div class="flex items-center gap-1.5 {className}">
  <select
    aria-label={label}
    value={sort}
    onchange={handleSelectChange}
    class="h-11 min-h-11 md:h-10 md:min-h-10 rounded-md border border-input bg-background px-2.5 text-xs font-medium text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
  >
    {#each options as option (option.value)}
      <option value={option.value}>⇅ {option.label}</option>
    {/each}
  </select>

  <Button
    type="button"
    variant="outline"
    size="sm"
    class="h-11 min-h-11 md:h-10 md:min-h-10 px-2.5 text-xs"
    onclick={toggleDir}
    aria-label={dir === 'asc' ? 'Sort ascending' : 'Sort descending'}
    title={dir === 'asc' ? 'Ascending' : 'Descending'}
  >
    {#if dir === 'asc'}
      <ArrowUp class="h-3.5 w-3.5 mr-1" />
      <span>Asc</span>
    {:else}
      <ArrowDown class="h-3.5 w-3.5 mr-1" />
      <span>Desc</span>
    {/if}
  </Button>
</div>
