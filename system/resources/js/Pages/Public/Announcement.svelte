<script>
    import { Link } from '@inertiajs/svelte';
    import HomeLayout from '@/Layouts/HomeLayout.svelte';

    let {
        institution = {},
        alerts = [],
        announcement,
        program = null,
    } = $props();
    const announcementsHref = $derived(
        program
            ? `/programs/${encodeURIComponent(program.code)}/announcements`
            : '/announcements',
    );
    const announcementsLabel = $derived(
        program ? `All ${program.code} announcements` : 'All announcements',
    );
</script>

<svelte:head
    ><title>{announcement.title} — {institution.name ?? 'Institution'}</title
    ></svelte:head
>

<HomeLayout {institution} {alerts}>
        <article
            class="mx-auto w-full max-w-7xl px-4 py-12 sm:px-6 sm:py-16 lg:px-8"
        >
            <div class="max-w-[72ch]">
                <Link
                    href={announcementsHref}
                    class="text-primary inline-flex min-h-[44px] items-center text-sm font-semibold underline underline-offset-4"
                    >{announcementsLabel}</Link
                >
                <p class="text-primary mt-6 text-sm font-semibold">
                    {announcement.publisher_label}
                </p>
                <h1
                    class="text-foreground mt-3 text-4xl font-semibold tracking-tight break-words sm:text-5xl"
                >
                    {announcement.title}
                </h1>
                {#if announcement.published_at}<p
                        class="text-muted-foreground mt-4 text-sm"
                    >
                        <time datetime={announcement.published_at}
                            >{new Date(
                                announcement.published_at,
                            ).toLocaleString()}</time
                        >
                    </p>{/if}
                <p
                    class="text-foreground mt-8 text-lg leading-8 break-words whitespace-pre-wrap"
                >
                    {announcement.summary}
                </p>
                <div
                    class="text-foreground mt-8 max-w-[72ch] text-base leading-7 break-words whitespace-pre-wrap"
                >
                    {announcement.body}
                </div>
            </div>
        </article>
</HomeLayout>
