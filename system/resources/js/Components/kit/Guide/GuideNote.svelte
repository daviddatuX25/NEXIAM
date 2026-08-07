<script>
  /**
   * Soft callout note — no left-border stripe (that read as sloppy chrome).
   * Tinted surface + icon; institutional, quiet.
   */
  import { Info, AlertTriangle, Lightbulb } from 'lucide-svelte';

  let {
    variant = 'info',
    title = '',
    class: className = '',
    children,
  } = $props();

  const styles = {
    info: {
      shell: 'border-border/70 bg-muted/40',
      iconWrap: 'bg-sky-500/10 text-sky-700 dark:text-sky-300',
      icon: Info,
    },
    warning: {
      shell: 'border-amber-500/25 bg-amber-500/8',
      iconWrap: 'bg-amber-500/15 text-amber-800 dark:text-amber-300',
      icon: AlertTriangle,
    },
    tip: {
      shell: 'border-primary/25 bg-primary/6',
      iconWrap: 'bg-primary/12 text-primary',
      icon: Lightbulb,
    },
  };

  const config = $derived(styles[variant] ?? styles.info);
  const Icon = $derived(config.icon);
</script>

<div
  class="rounded-xl border p-4 text-sm shadow-[inset_0_1px_0_oklch(1_0_0/0.35)] {config.shell} {className}"
  role="note"
>
  <div class="flex items-start gap-3">
    <span
      class="inline-flex size-8 shrink-0 items-center justify-center rounded-lg {config.iconWrap}"
      aria-hidden="true"
    >
      <Icon class="h-4 w-4" />
    </span>
    <div class="min-w-0 space-y-1 pt-0.5">
      {#if title}
        <h5 class="font-semibold tracking-tight text-foreground">{title}</h5>
      {/if}
      <div class="leading-relaxed text-muted-foreground">
        {@render children?.()}
      </div>
    </div>
  </div>
</div>
