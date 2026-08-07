<script>
  import { Link } from '@inertiajs/svelte';
  import { ArrowRight, ChevronRight, AlertCircle, Clock } from 'lucide-svelte';
  import { Button } from '@/Components/ui/button';

  let {
    title,
    viewAllHref,
    items = [], // { id, label, secondary, href, urgency }
    emptyMessage = 'No items in queue',
  } = $props();

  const safeItems = $derived(Array.isArray(items) ? items : []);

  const urgencyStyles = {
    critical: 'border-l-4 border-l-destructive/50 bg-destructive/5 dark:bg-destructive/10',
    warn: 'border-l-4 border-l-amber-500/50 bg-amber-500/5 dark:bg-amber-500/10',
    ok: 'border-l-4 border-l-transparent hover:border-l-primary/20 bg-muted/20'
  };
</script>

<div class="glass-panel p-6 rounded-2xl flex flex-col h-full">
  <div class="flex items-center justify-between gap-4 mb-4 shrink-0">
    <h2 class="text-lg font-bold text-foreground">
      {title}
    </h2>
    {#if viewAllHref}
      <Link href={viewAllHref} class="text-sm font-semibold text-primary hover:underline flex items-center gap-1">
        View all <ArrowRight class="h-4 w-4" />
      </Link>
    {/if}
  </div>

  <div class="flex-1 overflow-y-auto space-y-2 pr-2 -mr-2">
    {#if safeItems.length > 0}
      {#each safeItems as item (item.id)}
        <Link
          href={item.href}
          class={`group flex items-center justify-between gap-4 rounded-xl border border-border/50 p-4 transition-all hover:bg-muted/40 min-h-[44px] ${urgencyStyles[item.urgency || 'ok']}`}
        >
          <div class="min-w-0 flex-1">
            <div class="font-semibold text-foreground truncate flex items-center gap-2">
              {#if item.urgency === 'critical'}
                <AlertCircle class="h-4 w-4 text-destructive shrink-0" />
              {:else if item.urgency === 'warn'}
                <Clock class="h-4 w-4 text-amber-500 shrink-0" />
              {/if}
              <span class="truncate">{item.label}</span>
            </div>
            {#if item.secondary}
              <div class="text-xs text-muted-foreground truncate mt-0.5 ml={item.urgency === 'critical' || item.urgency === 'warn' ? '6' : '0'}">
                {item.secondary}
              </div>
            {/if}
          </div>
          <Button variant="ghost" size="icon" class="shrink-0 h-8 w-8 rounded-full opacity-50 group-hover:opacity-100 transition-opacity" tabindex="-1">
            <ChevronRight class="h-4 w-4" />
          </Button>
        </Link>
      {/each}
    {:else}
      <div class="h-full min-h-[120px] flex items-center justify-center border-2 border-dashed border-border/50 rounded-xl px-4 py-8 text-sm text-muted-foreground text-center">
        {emptyMessage}
      </div>
    {/if}
  </div>
</div>
