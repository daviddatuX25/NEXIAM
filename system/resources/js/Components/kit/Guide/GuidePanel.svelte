<script>
  import { HelpCircle, ChevronDown } from 'lucide-svelte';
  import { slide } from 'svelte/transition';

  let {
    title,
    icon: Icon = HelpCircle,
    defaultOpen = false,
    class: className = '',
    children,
  } = $props();

  let open = $state(defaultOpen);
  const panelId = 'guide-panel-' + Math.random().toString(36).slice(2, 9);
</script>

<div class="overflow-hidden rounded-xl border border-border/70 bg-card shadow-sm {className}">
  <button
    type="button"
    class="flex w-full items-center justify-between gap-3 px-4 py-3.5 text-left transition-colors hover:bg-muted/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
    onclick={() => (open = !open)}
    aria-expanded={open}
    aria-controls={panelId}
  >
    <div class="flex items-center gap-2.5 font-medium text-foreground">
      <span class="inline-flex size-8 items-center justify-center rounded-lg bg-muted text-muted-foreground" aria-hidden="true">
        <Icon class="h-4 w-4" />
      </span>
      {title}
    </div>
    <ChevronDown
      class="h-4 w-4 shrink-0 text-muted-foreground transition-transform duration-200 {open ? 'rotate-180' : ''}"
    />
  </button>

  {#if open}
    <div id={panelId} role="region" transition:slide={{ duration: 200 }}>
      <div class="space-y-4 border-t border-border/60 px-4 py-4">
        {@render children?.()}
      </div>
    </div>
  {/if}
</div>
