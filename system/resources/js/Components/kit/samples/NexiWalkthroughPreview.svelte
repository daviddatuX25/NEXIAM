<script>
  /**
   * Nexi companion sample — game-style dialogue / walkthrough chrome.
   * Character is a placeholder silhouette; later swap for animated frame sprites (1 frame per beat is fine).
   * Presentational only.
   */
  import { reveal } from '@/lib/motion.js';
  import { Button } from '@/Components/ui/button';
  import { MessageCircle, ChevronRight, Sparkles } from 'lucide-svelte';

  const beats = [
    {
      id: 'welcome',
      speaker: 'Nexi',
      line: 'Hi — I’m Nexi. I’ll walk you through admissions without drowning you in menus.',
    },
    {
      id: 'scope',
      speaker: 'Nexi',
      line: 'Staff see only what their capabilities allow. If something’s missing, it’s usually scope — not a broken page.',
    },
    {
      id: 'next',
      speaker: 'Nexi',
      line: 'Ready when you are. Ask me about windows, slips, or what “pending” really means.',
    },
  ];

  let beatIndex = $state(0);
  let frame = $state(0); // placeholder “animation” frame 0|1

  const beat = $derived(beats[beatIndex] ?? beats[0]);

  function nextBeat() {
    beatIndex = (beatIndex + 1) % beats.length;
    frame = beatIndex % 2;
  }

  function prevBeat() {
    beatIndex = (beatIndex - 1 + beats.length) % beats.length;
    frame = beatIndex % 2;
  }
</script>

<div
  class="overflow-hidden rounded-2xl border border-border/50 bg-background shadow-sm"
  use:reveal={{ y: 12 }}
>
  <div class="flex items-center justify-between gap-3 border-b border-border/50 px-4 py-3 sm:px-5">
    <div class="flex items-center gap-2">
      <span class="inline-flex size-8 items-center justify-center rounded-lg bg-primary/10 text-primary">
        <Sparkles class="h-4 w-4" />
      </span>
      <div>
        <p class="text-sm font-semibold text-foreground">Nexi</p>
        <p class="text-xs text-muted-foreground">Companion walkthrough · placeholder sprite</p>
      </div>
    </div>
    <span class="rounded-md border border-border/60 px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide text-muted-foreground">
      Sample
    </span>
  </div>

  <div
    class="grid gap-0 lg:grid-cols-[minmax(0,220px)_1fr]"
    style="background-image:
      radial-gradient(ellipse 70% 50% at 0% 100%, oklch(0.88 0.06 145 / 0.35), transparent 55%),
      linear-gradient(180deg, oklch(0.98 0.01 90), var(--background));"
  >
    <!-- Character stage -->
    <div class="flex flex-col items-center justify-end gap-3 border-b border-border/40 px-6 pb-6 pt-8 lg:border-b-0 lg:border-r lg:border-border/40">
      <div
        class="relative flex h-40 w-36 items-end justify-center"
        aria-hidden="true"
        data-frame={frame}
      >
        <!-- Placeholder sprite: two simple frames (swap later for art) -->
        <div
          class="absolute inset-x-4 bottom-0 top-4 rounded-[40%_40%_28%_28%] bg-primary/15 ring-1 ring-primary/20 transition-transform duration-300 {frame === 0
            ? 'translate-y-0'
            : '-translate-y-1'}"
        ></div>
        <div
          class="absolute bottom-10 left-1/2 size-16 -translate-x-1/2 rounded-full bg-primary/25 ring-1 ring-primary/25 {frame === 1
            ? 'scale-105'
            : 'scale-100'} transition-transform duration-300"
        ></div>
        <div class="absolute bottom-2 left-1/2 h-3 w-20 -translate-x-1/2 rounded-full bg-foreground/10 blur-[2px]"></div>
      </div>
      <p class="text-center text-[11px] text-muted-foreground">
        Frame {frame + 1}/2 · replace with sprite sheet later
      </p>
    </div>

    <!-- Dialogue + chat affordance -->
    <div class="flex min-h-[280px] flex-col p-4 sm:p-6">
      <div class="glass-panel flex flex-1 flex-col rounded-xl border-0 p-4 sm:p-5">
        <div class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.16em] text-primary">
          <MessageCircle class="h-3.5 w-3.5" />
          {beat.speaker}
        </div>
        <p class="flex-1 text-base leading-relaxed text-foreground sm:text-lg">
          {beat.line}
        </p>
        <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
          <p class="text-xs text-muted-foreground">
            Beat {beatIndex + 1} of {beats.length}
          </p>
          <div class="flex gap-2">
            <Button variant="outline" size="sm" class="min-h-[40px]" onclick={prevBeat}>Back</Button>
            <Button size="sm" class="min-h-[40px] gap-1" onclick={nextBeat}>
              Continue
              <ChevronRight class="h-4 w-4" />
            </Button>
          </div>
        </div>
      </div>

      <form
        class="mt-4 flex gap-2"
        onsubmit={(e) => {
          e.preventDefault();
          nextBeat();
        }}
      >
        <label class="sr-only" for="nexi-demo-input">Ask Nexi</label>
        <input
          id="nexi-demo-input"
          type="text"
          placeholder="Ask Nexi… (demo — no backend)"
          class="h-10 min-h-[44px] flex-1 rounded-lg border border-border/70 bg-card px-3 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring/50"
        />
        <Button type="submit" class="min-h-[44px] px-4">Send</Button>
      </form>
    </div>
  </div>
</div>
