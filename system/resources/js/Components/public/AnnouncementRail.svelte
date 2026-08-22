<script>
    import { ArrowUpRight, BellRing } from 'lucide-svelte';
    import { reveal, revealChildren } from '@/lib/motion.js';

    let { announcements = [] } = $props();
</script>

<section id="news" class="scroll-mt-24" use:reveal={{ y: 18, duration: 0.65 }}>
    <div class="flex items-end justify-between gap-4 border-b border-border/60 pb-6">
        <div>
            <p class="text-primary text-xs font-bold tracking-[0.22em] uppercase">From the campus</p>
            <h2 class="text-foreground mt-3 text-3xl font-semibold tracking-[-0.035em] sm:text-4xl">A few things to know.</h2>
        </div>
        <BellRing class="text-primary/60 hidden size-7 sm:block" aria-hidden="true" />
    </div>

    {#if announcements.length > 0}
        <div class="mt-5 divide-y divide-border/60" use:revealChildren={{ selector: '[data-reveal]', y: 10, stagger: 0.08 }}>
            {#each announcements as announcement (announcement.title)}
                <article data-reveal class="grid gap-3 py-6 sm:grid-cols-[0.35fr_1fr_auto] sm:items-start sm:gap-8">
                    <p class="text-primary text-xs font-bold tracking-[0.18em] uppercase">{announcement.eyebrow}</p>
                    <div>
                        <h3 class="text-foreground text-lg font-semibold tracking-[-0.02em]">{announcement.title}</h3>
                        <p class="text-muted-foreground mt-2 max-w-[58ch] text-sm leading-6">{announcement.body}</p>
                    </div>
                    {#if announcement.href}
                        <a
                            href={announcement.href}
                            class="text-foreground inline-flex min-h-11 items-center gap-2 text-sm font-semibold underline decoration-primary/40 underline-offset-4 transition hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring sm:justify-self-end"
                        >
                            {announcement.label ?? 'Read more'}
                            <ArrowUpRight class="size-4" />
                        </a>
                    {/if}
                </article>
            {/each}
        </div>
    {:else}
        <div class="mt-6 rounded-[1rem] border border-dashed border-border bg-muted/20 p-6">
            <p class="text-foreground font-medium">No announcements are published yet.</p>
            <p class="text-muted-foreground mt-1 text-sm">Check back for campus updates and admissions notices.</p>
        </div>
    {/if}
</section>
