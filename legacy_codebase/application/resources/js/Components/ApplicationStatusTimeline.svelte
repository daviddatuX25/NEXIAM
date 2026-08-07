<script>
  import { CheckCircle2, Circle } from 'lucide-svelte';
  import { Badge } from '@/Components/ui/badge';

  /** @type {{ stage: string; completed: boolean; timestamp?: string | null }[]} */
  let { stages = [] } = $props();

  const completedCount = $derived(stages.filter((s) => s.completed).length);
  const firstPendingIndex = $derived(stages.findIndex((s) => !s.completed));
</script>

<div class="relative">
  <ul class="relative space-y-0 list-none p-0 m-0">
    {#each stages as stage, i}
      {@const isLast = i === stages.length - 1}
      {@const isFirstPending = firstPendingIndex >= 0 && i === firstPendingIndex}
      <li class="relative flex gap-4">
        <!-- Left column: node + connector -->
        <div class="flex flex-col items-center shrink-0">
          <!-- Node -->
          <div class="relative z-10">
            {#if isFirstPending}
              <div class="absolute -inset-1.5 rounded-full bg-primary/20 animate-ping opacity-75" aria-hidden="true" style="animation-duration: 3s;"></div>
            {/if}
            <div
              class="relative flex h-8 w-8 shrink-0 items-center justify-center rounded-full border-2 transition-all duration-300 {stage.completed
                ? 'border-primary bg-primary text-primary-foreground'
                : isFirstPending
                  ? 'border-primary bg-background text-primary shadow-sm shadow-primary/20 ring-4 ring-primary/10'
                  : 'border-muted-foreground/30 bg-background text-muted-foreground'}"
            >
              {#if stage.completed}
                <CheckCircle2 class="h-4 w-4" aria-hidden="true" />
              {:else if isFirstPending}
                <Circle class="h-4 w-4 fill-primary/20" aria-hidden="true" />
              {:else}
                <Circle class="h-4 w-4" aria-hidden="true" />
              {/if}
            </div>
          </div>
          <!-- Connector line to next step -->
          {#if !isLast}
            <div
              class="w-0.5 flex-1 min-h-[28px] rounded-full mt-1 transition-colors duration-300 {stage.completed
                ? 'bg-primary'
                : isFirstPending
                  ? 'bg-primary/40 animate-pulse'
                  : 'bg-border'}"
              aria-hidden="true"
            ></div>
          {/if}
        </div>

        <!-- Content -->
        <div class="min-w-0 flex-1 pb-6 last:pb-0 pt-0.5">
          <div class="flex flex-wrap items-center gap-2">
            <span
              class="font-medium {stage.completed || isFirstPending
                ? 'text-foreground'
                : 'text-muted-foreground'}"
            >
              {stage.stage}
            </span>
            <Badge variant={stage.completed ? 'success' : (isFirstPending ? 'default' : 'muted')} class="shrink-0 {isFirstPending ? 'shadow-[0_0_10px_rgba(var(--primary),0.3)] animate-pulse border-primary/50' : ''}">
              {stage.completed ? 'Done' : (isFirstPending ? 'Current' : 'Pending')}
            </Badge>
          </div>
          {#if stage.timestamp}
            <p class="mt-1 text-sm text-muted-foreground">
              {stage.timestamp}
            </p>
          {/if}
        </div>
      </li>
    {/each}
  </ul>
</div>
