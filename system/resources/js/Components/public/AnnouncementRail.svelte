<script>
    import { Link } from '@inertiajs/svelte';
    import { reveal, revealChildren } from '@/lib/motion.js';

    let { announcements = [] } = $props();
</script>

<section
    id="announcements"
    class="scroll-mt-24"
    use:reveal={{ y: 18, duration: 0.65 }}
>
    <div
        class="border-border/60 flex flex-col gap-4 border-b pb-6 sm:flex-row sm:items-end sm:justify-between"
    >
        <div>
            <h2
                class="text-foreground text-3xl font-semibold tracking-[-0.035em] sm:text-4xl"
            >
                Announcements
            </h2>
            <p
                class="text-muted-foreground mt-3 max-w-[65ch] text-sm leading-6"
            >
                Recent updates from campus offices and academic programs.
            </p>
        </div>
        <Link
            href="/announcements"
            class="text-primary decoration-primary/40 inline-flex min-h-11 shrink-0 items-center text-sm font-semibold underline underline-offset-4"
            >All announcements</Link
        >
    </div>

    {#if announcements.length > 0}
        <div
            class="divide-border/60 mt-5 divide-y"
            use:revealChildren={{
                selector: '[data-reveal]',
                y: 10,
                stagger: 0.08,
            }}
        >
            {#each announcements.slice(0, 3) as announcement (announcement.id)}
                <article
                    data-reveal
                    class="grid min-w-0 gap-3 py-6 sm:grid-cols-[minmax(10rem,0.35fr)_minmax(0,1fr)_auto] sm:items-start sm:gap-8"
                >
                    <div
                        class="text-primary flex flex-wrap items-center gap-x-2 text-xs font-semibold"
                    >
                        <span>{announcement.publisher_label}</span>
                        {#if announcement.published_at}<time
                                datetime={announcement.published_at}
                                >{new Date(
                                    announcement.published_at,
                                ).toLocaleDateString()}</time
                            >{/if}
                    </div>
                    <div class="min-w-0">
                        <h3
                            class="text-foreground text-lg font-semibold tracking-[-0.02em] break-words"
                        >
                            {announcement.title}
                        </h3>
                        <p
                            class="text-muted-foreground mt-2 max-w-[65ch] text-sm leading-6 break-words whitespace-pre-wrap"
                        >
                            {announcement.summary}
                        </p>
                    </div>
                    <Link
                        href={announcement.href}
                        class="text-foreground decoration-primary/40 hover:text-primary inline-flex min-h-11 items-center text-sm font-semibold underline underline-offset-4 sm:justify-self-end"
                        >Read announcement</Link
                    >
                </article>
            {/each}
        </div>
    {:else}
        <div class="border-border mt-6 border-y border-dashed py-6">
            <p class="text-foreground font-medium">
                No announcements are published yet.
            </p>
        </div>
    {/if}
</section>
