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
      'block glass-panel p-6 rounded-2xl stat-card relative overflow-hidden group',
      className
    )}
  >
    <div class="flex justify-between items-start mb-4 relative">
      <div>
        <p class="text-sm font-medium text-muted-foreground mb-1">{label}</p>
        <h3 class="text-3xl font-bold tracking-tight text-foreground">{value}</h3>
      </div>
      {#if Icon}
        <div class="inline-flex size-10 shrink-0 items-center justify-center rounded-lg text-primary ring-1 ring-primary/20">
          <Icon class="w-6 h-6" />
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
      'glass-panel p-6 rounded-2xl stat-card relative overflow-hidden group',
      className
    )}
  >
    <div class="flex justify-between items-start mb-4 relative">
      <div>
        <p class="text-sm font-medium text-muted-foreground mb-1">{label}</p>
        <h3 class="text-3xl font-bold tracking-tight text-foreground">{value}</h3>
      </div>
      {#if Icon}
        <div class="inline-flex size-10 shrink-0 items-center justify-center rounded-lg text-primary ring-1 ring-primary/20">
          <Icon class="w-6 h-6" />
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
