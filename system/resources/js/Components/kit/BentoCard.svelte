<script>
  import { cn } from '@/lib/utils.js';

  let {
    class: className = '',
    variant = 'default', // 'default' | 'glass' | 'interactive'
    children,
    ...restProps
  } = $props();
</script>

<div
  class={cn(
    'relative isolate overflow-hidden rounded-[var(--radius,0.5rem)] transition-all duration-200',
    variant === 'glass'
      ? 'border border-border/60 bg-card/75 backdrop-blur-xl shadow-[0_20px_55px_-38px_oklch(0.22_0.04_155_/_0.4)]'
      : variant === 'craft'
        ? 'craft-card-depth'
        : variant === 'solid'
          ? 'border border-border/80 bg-card shadow-[0_1px_2px_oklch(0_0_0/0.04),0_8px_24px_-12px_oklch(0.31_0.02_148/0.18)] dark:shadow-[inset_0_1px_0_0_rgba(255,255,255,0.08),0_12px_32px_-12px_rgba(0,0,0,0.7)]'
          : 'border border-border/70 bg-card/85 dark:bg-card/75 backdrop-blur-md shadow-[inset_0_1px_0_0_rgba(255,255,255,0.45),0_1px_2px_oklch(0_0_0/0.04),0_8px_24px_-12px_oklch(0.31_0.02_148/0.18)] dark:shadow-[inset_0_1px_0_0_rgba(255,255,255,0.08),0_12px_32px_-12px_rgba(0,0,0,0.7)]',
    variant === 'interactive' && 'motion-safe:hover:-translate-y-0.5 hover:border-primary/45 hover:shadow-lg cursor-pointer',
    className
  )}
  {...restProps}
>
  {@render children?.()}
</div>
