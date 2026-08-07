<script>
  /**
   * Bulk selection action bar (legacy Applications / Release).
   * Shows when count > 0. Actions are a snippet so domain verbs stay with the page.
   */
  import { X } from 'lucide-svelte';
  import { Button } from '@/Components/ui/button';

  let {
    count = 0,
    hint = '',
    onClear = undefined,
    actions,
    class: className = '',
  } = $props();
</script>

{#if count > 0}
  <div
    class="flex flex-wrap items-center gap-3 rounded-lg border border-primary/20 bg-primary/5 px-4 py-3 text-sm {className}"
    role="status"
    aria-live="polite"
  >
    <div class="flex items-center gap-2 font-medium">
      <span
        class="inline-flex h-6 min-w-[1.5rem] items-center justify-center rounded-full bg-primary px-1.5 text-xs font-bold text-primary-foreground"
      >
        {count}
      </span>
      <span>selected</span>
    </div>

    {#if hint}
      <span class="hidden text-xs text-muted-foreground sm:inline">·</span>
      <span class="hidden text-xs text-muted-foreground sm:inline">{hint}</span>
    {/if}

    <div class="ml-auto flex flex-wrap items-center gap-2">
      {#if actions}
        {@render actions()}
      {/if}
      {#if onClear}
        <Button size="sm" variant="ghost" class="gap-1 text-muted-foreground" onclick={onClear}>
          <X class="h-3.5 w-3.5" />
          Clear
        </Button>
      {/if}
    </div>
  </div>
{/if}
