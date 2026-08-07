<script>
  /**
   * Mode / context banner (legacy Release online vs F2F, similar callouts).
   */
  import { cn } from '@/lib/utils.js';

  let {
    variant = 'info',
    title = '',
    class: className = '',
    children,
  } = $props();

  const variants = {
    info: 'border-border bg-muted/40',
    primary: 'border-primary/30 bg-primary/5',
    warning: 'border-amber-500/40 bg-amber-500/10',
    danger: 'border-destructive/30 bg-destructive/5',
  };
</script>

<div class={cn('rounded-lg border px-4 py-3 text-sm', variants[variant] ?? variants.info, className)} role="note">
  {#if title}
    <strong class="text-foreground">{title}</strong>
    {#if children}
      <span class="text-muted-foreground"> </span>
    {/if}
  {/if}
  {#if children}
    <span class="text-foreground/90">{@render children()}</span>
  {/if}
</div>
