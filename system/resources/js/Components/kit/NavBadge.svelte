<script lang="ts">
  type BadgeData =
    | {
        kind: 'count';
        count: number;
        unit?: string;
        variant?: 'neutral' | 'info' | 'warning' | 'critical';
        pulse?: boolean;
        accessible_label?: string;
      }
    | {
        kind: 'dot';
        variant?: 'neutral' | 'info' | 'warning' | 'critical';
        accessible_label?: string;
      }
    | null
    | undefined;

  let { badge, active = false, size = 'md' }: {
    badge: BadgeData;
    active?: boolean;
    size?: 'sm' | 'md';
  } = $props();

  const variants = {
    info: 'bg-primary/15 text-primary dark:bg-primary/25',
    warning: 'bg-amber-500/15 text-amber-700 dark:text-amber-300',
    critical: 'bg-destructive text-white',
    neutral: 'bg-muted text-muted-foreground',
  };
  const dotVariants = {
    info: 'bg-primary',
    warning: 'bg-amber-500',
    critical: 'bg-destructive',
    neutral: 'bg-muted-foreground',
  };

  const countSizes = {
    sm: 'gap-1 px-1.5 py-0.5 text-[10px]',
    md: 'gap-1.5 px-2 py-0.5 text-xs',
  };
</script>

{#if badge && !(badge.kind === 'count' && badge.count <= 0)}
  {#if badge.kind === 'count'}
    <span
      class="inline-flex shrink-0 items-center rounded-full font-semibold tabular-nums {countSizes[size]} {active ? 'bg-primary-foreground/20 text-primary-foreground' : variants[badge.variant ?? 'neutral']}"
      aria-label={badge.accessible_label ?? `${badge.count} unread`}
    >
      {#if badge.pulse ?? badge.unit === 'unread_messages'}
        <span class="size-1.5 shrink-0 animate-pulse rounded-full bg-current" aria-hidden="true"></span>
      {/if}
      <span>{badge.count > 99 ? '99+' : badge.count}</span>
    </span>
  {:else if badge.kind === 'dot'}
    <span class="relative inline-flex size-2.5 shrink-0 rounded-full {active ? 'bg-primary-foreground' : dotVariants[badge.variant ?? 'info']}">
      <span class="sr-only">{badge.accessible_label ?? 'Needs attention'}</span>
    </span>
  {/if}
{/if}
