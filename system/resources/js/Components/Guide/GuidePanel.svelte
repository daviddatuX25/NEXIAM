<script>
  import { HelpCircle, ChevronDown } from 'lucide-svelte';

  let {
    title = 'Placeholder Reference & Templates Guide',
    defaultOpen = false,
    class: className = '',
    children,
  } = $props();

  let open = $state(defaultOpen);
  const panelId = `guide-panel-${Math.random().toString(36).slice(2, 9)}`;
</script>

<div class="rounded-xl border border-border bg-card shadow-xs {className}">
  <button
    type="button"
    class="flex w-full items-center justify-between p-4 text-left font-medium transition-colors hover:bg-muted/30 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring"
    onclick={() => (open = !open)}
    aria-expanded={open}
    aria-controls={panelId}
  >
    <div class="flex items-center gap-2.5 text-sm font-semibold text-foreground">
      <HelpCircle class="size-4 text-primary" />
      {title}
    </div>
    <div class="flex items-center gap-2">
      <span class="text-xs text-muted-foreground">{open ? 'Hide guide' : 'Show guide'}</span>
      <ChevronDown class="size-4 text-muted-foreground transition-transform duration-200 {open ? 'rotate-180' : ''}" />
    </div>
  </button>

  {#if open}
    <div id={panelId} role="region" class="border-t border-border/60 p-4 space-y-5">
      {@render children?.()}
    </div>
  {/if}
</div>
