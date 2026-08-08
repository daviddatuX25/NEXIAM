<script>
  /**
   * Applicant schedule pipeline — legacy Portal ApplicationStatusTimeline shape,
   * with status + summary per step (content panels live on the page).
   */
  import { Badge } from '@/Components/ui/badge';
  import { CheckCircle2, Circle } from 'lucide-svelte';

  let { stages = [] } = $props();
</script>

<ol class="relative m-0 list-none space-y-0 p-0" aria-label="Exam schedule progress">
  {#each stages as stage, i (stage.id)}
    {@const isLast = i === stages.length - 1}
    <li class="relative flex gap-3">
      <div class="flex shrink-0 flex-col items-center">
        <div class="relative z-10">
          {#if stage.status === 'current'}
            <div
              class="absolute -inset-1.5 animate-ping rounded-full bg-primary/20 opacity-75"
              style="animation-duration: 3s;"
              aria-hidden="true"
            ></div>
          {/if}
          <div
            class="relative flex size-8 items-center justify-center rounded-full border-2 transition-colors
              {stage.status === 'done'
                ? 'border-primary bg-primary text-primary-foreground'
                : stage.status === 'current'
                  ? 'border-primary bg-background text-primary ring-4 ring-primary/10'
                  : 'border-muted-foreground/30 bg-background text-muted-foreground'}"
          >
            {#if stage.status === 'done'}
              <CheckCircle2 class="size-4" aria-hidden="true" />
            {:else if stage.status === 'current'}
              <Circle class="size-4 fill-primary/20" aria-hidden="true" />
            {:else}
              <Circle class="size-4" aria-hidden="true" />
            {/if}
          </div>
        </div>
        {#if !isLast}
          <div
            class="mt-1 min-h-7 w-0.5 flex-1 rounded-full
              {stage.status === 'done'
                ? 'bg-primary'
                : stage.status === 'current'
                  ? 'bg-primary/40'
                  : 'bg-border'}"
            aria-hidden="true"
          ></div>
        {/if}
      </div>

      <div class="min-w-0 flex-1 pb-5 pt-0.5 last:pb-0">
        <div class="flex flex-wrap items-center gap-2">
          <span
            class="text-sm font-medium
              {stage.status === 'upcoming' ? 'text-muted-foreground' : 'text-foreground'}"
          >
            {stage.label}
          </span>
          <Badge
            variant={stage.status === 'done' ? 'outline' : stage.status === 'current' ? 'default' : 'secondary'}
            class="shrink-0 text-[10px] uppercase tracking-wide"
          >
            {stage.status === 'done' ? 'Done' : stage.status === 'current' ? 'Now' : 'Next'}
          </Badge>
        </div>
        {#if stage.summary}
          <p class="mt-1 text-sm leading-relaxed text-muted-foreground">{stage.summary}</p>
        {/if}
      </div>
    </li>
  {/each}
</ol>
