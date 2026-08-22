<script>
    import { ArrowUpRight, GraduationCap } from 'lucide-svelte';
    import { Link } from '@inertiajs/svelte';
    import { reveal, revealChildren } from '@/lib/motion.js';

    let { programs = [], preview = false } = $props();
</script>

<section id="programs" class="scroll-mt-24" use:reveal={{ y: 20, duration: 0.68 }}>
    <div class="flex flex-col gap-5 border-b border-border/60 pb-7 md:flex-row md:items-end md:justify-between">
        <div class="max-w-2xl">
            <p class="text-primary text-xs font-bold tracking-[0.22em] uppercase">Find your direction</p>
            <h2 class="text-foreground mt-3 text-3xl font-semibold leading-tight tracking-[-0.035em] sm:text-4xl">
                Programs that make room for your questions.
            </h2>
        </div>
        <p class="text-muted-foreground max-w-sm text-sm leading-6">
            Start with an area that feels close to your interests, then ask the people who can help you see what comes next.
        </p>
    </div>

    {#if programs.length > 0}
        <div class="mt-8 grid gap-4 md:grid-cols-2" use:revealChildren={{ selector: '[data-reveal]', y: 14, stagger: 0.09 }}>
            {#each programs as program (program.code)}
                <article
                    data-reveal
                    class="group flex min-h-[17rem] flex-col justify-between rounded-[1.25rem] border border-border/70 bg-card/70 p-6 shadow-[0_20px_55px_-38px_oklch(0.22_0.04_155_/_0.55)] transition duration-300 hover:-translate-y-1 hover:border-primary/45 hover:bg-card sm:p-7"
                >
                    <div>
                        <div class="flex items-start justify-between gap-4">
                            <span class="flex size-11 items-center justify-center rounded-full bg-primary/10 text-primary" aria-hidden="true">
                                <GraduationCap class="size-5" />
                            </span>
                            <span class="text-muted-foreground font-mono text-xs font-semibold tracking-[0.18em]">{program.code}</span>
                        </div>
                        <p class="text-primary mt-8 text-xs font-bold tracking-[0.18em] uppercase">{program.discipline ?? 'Program pathway'}</p>
                        <h3 class="text-foreground mt-2 max-w-[24ch] text-xl font-semibold leading-tight tracking-[-0.02em]">
                            {program.name}
                        </h3>
                        <p class="text-muted-foreground mt-3 max-w-[44ch] text-sm leading-6">
                            {program.summary ?? 'Program details will be published here from the approved catalog.'}
                        </p>
                    </div>
                    <Link
                        href="/apply"
                        class="text-foreground mt-8 inline-flex min-h-11 w-fit items-center gap-2 rounded-full border border-border px-4 py-2 text-sm font-semibold transition hover:border-primary/50 hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    >
                        Start with this path
                        <ArrowUpRight class="size-4" />
                    </Link>
                </article>
            {/each}
        </div>
        {#if preview}
            <p class="text-muted-foreground mt-4 text-xs">Program names come from the seeded preview catalog and can be replaced by approved catalog content.</p>
        {/if}
    {:else}
        <div class="mt-8 rounded-[1.25rem] border border-dashed border-border bg-muted/30 p-8 text-center">
            <p class="text-foreground font-semibold">Program information is being prepared.</p>
            <p class="text-muted-foreground mt-2 text-sm">Contact the campus for the latest approved program list.</p>
        </div>
    {/if}
</section>
