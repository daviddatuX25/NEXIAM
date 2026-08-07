<script>
  import { Link } from '@inertiajs/svelte';
  import { TrendingUp, TrendingDown, Minus } from 'lucide-svelte';

  let {
    label,
    value,
    status = 'ok', // 'ok' | 'warn' | 'critical'
    delta = null,
    deltaLabel = '',
    secondaryItems = [], // Array<{ label: string, value: string|number }>
    href = null
  } = $props();

  const statusStyles = {
    ok: 'border-l-4 border-l-primary/60 bg-primary/5',
    warn: 'border-l-4 border-l-amber-500/60 bg-amber-500/5',
    critical: 'border-l-4 border-l-destructive/60 bg-destructive/5'
  };
</script>

<div class={`relative overflow-hidden rounded-2xl glass-panel p-5 transition-shadow hover:shadow-md ${statusStyles[status] || statusStyles.ok}`}>
  {#if href}
    <Link {href} class="absolute inset-0 z-10">
      <span class="sr-only">View {label}</span>
    </Link>
  {/if}

  <div class="relative z-0">
    <div class="flex items-center justify-between">
      <div class="text-sm font-medium text-muted-foreground">{label}</div>
      {#if delta !== null}
        <div class="flex items-center gap-1 text-xs font-semibold px-2 py-0.5 rounded-full {delta > 0 ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : delta < 0 ? 'bg-rose-500/10 text-rose-600 dark:text-rose-400' : 'bg-muted text-muted-foreground'}">
          {#if delta > 0}
            <TrendingUp class="h-3 w-3 shrink-0" />
            <span>+{delta}</span>
          {:else if delta < 0}
            <TrendingDown class="h-3 w-3 shrink-0" />
            <span>{delta}</span>
          {:else}
            <Minus class="h-3 w-3 shrink-0" />
            <span>0</span>
          {/if}
          {#if deltaLabel}
            <span class="ml-0.5 font-normal opacity-70 truncate">{deltaLabel}</span>
          {/if}
        </div>
      {/if}
    </div>

    <div class="mt-2 text-3xl font-bold tabular-nums tracking-tight text-foreground">
      {value}
    </div>

    {#if secondaryItems.length > 0}
      <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
        {#each secondaryItems as item}
          <div>
             {item.label} <span class="tabular-nums font-semibold text-foreground/90">{item.value}</span>
          </div>
        {/each}
      </div>
    {/if}
  </div>
</div>
