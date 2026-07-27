<script lang="ts">
  import { HelpCircle, ChevronDown } from 'lucide-svelte';
  import { slide } from 'svelte/transition';
  
  let { 
    title, 
    icon: Icon = HelpCircle, 
    defaultOpen = false, 
    class: className = '',
    children
  } = $props();

  let open = $state(defaultOpen);
  const panelId = "guide-panel-" + Math.random().toString(36).substr(2, 9);
</script>

<div class="rounded-lg border border-border bg-card {className}">
  <button 
    type="button" 
    class="flex w-full items-center justify-between p-4 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
    onclick={() => open = !open}
    aria-expanded={open}
    aria-controls={panelId}
  >
    <div class="flex items-center gap-2 font-medium">
      <Icon class="h-5 w-5 text-muted-foreground" />
      {title}
    </div>
    <ChevronDown class="h-5 w-5 text-muted-foreground transition-transform {open ? 'rotate-180' : ''}" />
  </button>
  
  {#if open}
    <div id={panelId} role="region" transition:slide={{ duration: 200 }}>
      <div class="p-4 pt-0 space-y-6">
        {@render children?.()}
      </div>
    </div>
  {/if}
</div>
