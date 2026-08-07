<script>
  /**
   * Marketing homepage section sample — rebuilt (not legacy Home).
   * Brand-first hero, restrained Motion reveals, institutional forest/paper.
   */
  import { reveal, revealChildren } from '@/lib/motion.js';
  import { Button } from '@/Components/ui/button';
  import { ArrowRight, ClipboardList, ShieldCheck, GraduationCap } from 'lucide-svelte';

  const pillars = [
    {
      icon: ClipboardList,
      title: 'Apply once, track clearly',
      body: 'Applicants see status without guessing. Staff work from one scoped queue.',
    },
    {
      icon: ShieldCheck,
      title: 'Capability-bound access',
      body: 'Every sensitive action is gated by office, program, and duty — not role labels alone.',
    },
    {
      icon: GraduationCap,
      title: 'Exam to release, intact',
      body: 'Scheduling, proctoring, grading, and release stay connected with recoverable failure paths.',
    },
  ];
</script>

<div class="overflow-hidden rounded-2xl bg-background shadow-sm ring-1 ring-border/40">
  <section
    class="relative isolate min-h-[min(78vh,620px)] overflow-hidden px-6 py-16 sm:px-10 sm:py-24 lg:px-16"
    use:reveal={{ y: 10, duration: 0.7 }}
  >
    <div
      class="pointer-events-none absolute inset-0 -z-10"
      style="background-image:
        radial-gradient(ellipse 85% 55% at 8% -10%, oklch(0.86 0.07 145 / 0.55), transparent 58%),
        radial-gradient(ellipse 60% 45% at 100% 10%, oklch(0.93 0.03 95 / 0.4), transparent 52%),
        radial-gradient(ellipse 50% 40% at 70% 100%, oklch(0.90 0.04 145 / 0.25), transparent 55%),
        linear-gradient(180deg, oklch(0.985 0.008 90), var(--background));"
      aria-hidden="true"
    ></div>

    <p class="text-sm font-semibold tracking-[0.28em] text-primary uppercase">NEXIAM</p>
    <h2 class="mt-5 max-w-[14ch] text-[clamp(2.25rem,5vw,3.75rem)] font-semibold leading-[1.05] tracking-tight text-foreground">
      Admissions that stay fair when the season gets loud.
    </h2>
    <p class="mt-5 max-w-xl text-base leading-relaxed text-muted-foreground sm:text-lg">
      Computerized admission and testing for campuses that need clarity, auditability, and calm staff tooling.
    </p>
    <div class="mt-10 flex flex-wrap gap-3">
      <Button href="#home-sample-pillars" class="min-h-[48px] gap-2 px-6 text-base">
        See how it works
        <ArrowRight class="h-4 w-4" />
      </Button>
      <Button href="/login" variant="outline" class="min-h-[48px] px-6 text-base">Staff sign in</Button>
    </div>
  </section>

  <section
    id="home-sample-pillars"
    class="border-t border-border/40 px-6 py-16 sm:px-10 lg:px-16"
    use:revealChildren={{ stagger: 0.1 }}
  >
    <p data-reveal class="text-xs font-semibold tracking-[0.2em] text-muted-foreground uppercase">
      Built for the season
    </p>
    <h3 data-reveal class="mt-3 max-w-lg text-2xl font-semibold tracking-tight text-foreground sm:text-3xl">
      One connected journey — without the fragile shortcuts.
    </h3>
    <ul class="mt-12 grid gap-8 sm:grid-cols-3" role="list">
      {#each pillars as pillar}
        {@const Icon = pillar.icon}
        <li data-reveal class="min-w-0">
          <span
            class="inline-flex size-11 items-center justify-center rounded-xl bg-primary/10 text-primary"
            aria-hidden="true"
          >
            <Icon class="h-5 w-5" />
          </span>
          <h4 class="mt-5 text-lg font-semibold text-foreground">{pillar.title}</h4>
          <p class="mt-2 text-sm leading-relaxed text-muted-foreground">{pillar.body}</p>
        </li>
      {/each}
    </ul>
  </section>

  <section class="border-t border-border/40 px-6 py-14 sm:px-10 lg:px-16" use:reveal={{ y: 12 }}>
    <div
      class="glass-panel flex flex-col gap-6 rounded-2xl border-0 p-6 sm:flex-row sm:items-end sm:justify-between sm:p-8"
    >
      <div class="max-w-lg">
        <h3 class="text-xl font-semibold tracking-tight text-foreground sm:text-2xl">
          Ready when your academic cycle is.
        </h3>
        <p class="mt-2 text-sm text-muted-foreground">
          Kit sample for Motion + brand surfaces before journey pages ship. Glass sits on paper — one edge, not nested borders.
        </p>
      </div>
      <Button href="/login" class="min-h-[44px] shrink-0 px-5">Open staff console</Button>
    </div>
  </section>
</div>
