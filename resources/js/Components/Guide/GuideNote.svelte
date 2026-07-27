<script lang="ts">
  import { Info, AlertTriangle, Lightbulb } from 'lucide-svelte';

  let { variant, title, class: className = '', children } = $props<{
    variant: 'info' | 'warning' | 'tip';
    title?: string;
    class?: string;
    children?: any;
  }>();

  const styles = {
    info: { border: 'border-blue-400', icon: Info, iconClass: 'text-blue-500' },
    warning: { border: 'border-amber-400', icon: AlertTriangle, iconClass: 'text-amber-500' },
    tip: { border: 'border-green-400', icon: Lightbulb, iconClass: 'text-green-500' }
  };

  const config = $derived(styles[variant]);
  const Icon = $derived(config.icon);
</script>

<div class="rounded-lg bg-muted/50 p-3 text-sm border-l-4 {config.border} {className}">
  <div class="flex items-start gap-3">
    <Icon class="h-5 w-5 shrink-0 {config.iconClass} mt-0.5" />
    <div class="space-y-1">
      {#if title}
        <h5 class="font-medium text-foreground">{title}</h5>
      {/if}
      <div class="text-muted-foreground">
        {@render children?.()}
      </div>
    </div>
  </div>
</div>
