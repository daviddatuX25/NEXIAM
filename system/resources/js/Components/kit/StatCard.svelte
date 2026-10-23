<script>
  import { Link } from '@inertiajs/svelte';
  import { TrendingUp, TrendingDown, Minus } from 'lucide-svelte';
  import { cn } from '@/lib/utils.js';

  let {
    label,
    value,
    icon: Icon,
    trend,
    trendLabel,
    href,
    accent = 'primary',
    class: className,
  } = $props();

  const trendPositive = $derived(trend === 'up');
  const trendNegative = $derived(trend === 'down');
  const trendNeutral = $derived(trend === 'neutral' || trend == null);
</script>

{#if href}
  <Link
    href={href}
    class={cn(
      'block rounded-[var(--radius,0.5rem)] border border-border/80 bg-card p-6 shadow-sm dark:shadow-[inset_0_1px_0_0_rgba(255,255,255,0.08)] motion-safe:hover:-translate-y-0.5 hover:border-primary/40 transition-all duration-200 relative overflow-hidden group',
      className
    )}
  >
    <div class="flex justify-between items-start mb-4 relative">
      <div>
        <p class="text-xs font-mono font-bold uppercase tracking-wider text-muted-foreground mb-1">{label}</p>
        <h3 class="text-3xl font-extrabold font-mono tabular-nums tracking-tight text-foreground">{value}</h3>
      </div>
      {#if Icon}
        <div class="inline-flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary border border-primary/20">
          <Icon class="size-5" />
        </div>
      {/if}
    </div>
    {#if trendLabel}
      <div class={cn(
        'flex items-center gap-2 text-sm font-medium',
        trendPositive && 'text-green-600 dark:text-green-400',
        trendNegative && 'text-red-500',
        trendNeutral && 'text-muted-foreground'
      )}>
        {#if trendPositive}
          <TrendingUp class="w-4 h-4" />
        {:else if trendNegative}
          <TrendingDown class="w-4 h-4" />
        {:else}
          <Minus class="w-4 h-4" />
        {/if}
        <span>{trendLabel}</span>
      </div>
    {/if}
  </Link>
{:else}
  <div
    class={cn(
      'rounded-[var(--radius,0.5rem)] border border-border/80 bg-card p-6 shadow-sm dark:shadow-[inset_0_1px_0_0_rgba(255,255,255,0.08)] transition-all duration-200 relative overflow-hidden',
      className
    )}
  >
    <div class="flex justify-between items-start mb-4 relative">
      <div>
        <p class="text-xs font-mono font-bold uppercase tracking-wider text-muted-foreground mb-1">{label}</p>
        <h3 class="text-3xl font-extrabold font-mono tabular-nums tracking-tight text-foreground">{value}</h3>
      </div>
      {#if Icon}
        <div class="inline-flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary border border-primary/20">
          <Icon class="size-5" />
        </div>
      {/if}
    </div>
    {#if trendLabel}
      <div class={cn(
        'flex items-center gap-2 text-sm font-medium',
        trendPositive && 'text-green-600 dark:text-green-400',
        trendNegative && 'text-red-500',
        trendNeutral && 'text-muted-foreground'
      )}>
        {#if trendPositive}
          <TrendingUp class="w-4 h-4" />
        {:else if trendNegative}
          <TrendingDown class="w-4 h-4" />
        {:else}
          <Minus class="w-4 h-4" />
        {/if}
        <span>{trendLabel}</span>
      </div>
    {/if}
  </div>
{/if}
