<script>
    import { Link } from '@inertiajs/svelte';
    import HomeLayout from '@/Layouts/HomeLayout.svelte';
    import PolicyBulletinCard from '@/Components/public/PolicyBulletinCard.svelte';

    let {
        institution = {},
        program,
        publicProfile = null,
        alerts = [],
        announcements = [],
        programPolicies = [],
        homeHref = '/',
        navigation = null,
        programIndex = null,
        programPolicyIndex = null,
    } = $props();

    const programAnnouncementIndex = $derived(programIndex ?? null);
    const programsHref = $derived(
        homeHref.includes('#')
            ? homeHref
            : homeHref === '/'
              ? '/#programs'
              : `${homeHref.replace(/\/$/, '')}#programs`,
    );
    const paragraphs = $derived(
        String(publicProfile?.description ?? '')
            .split(/\n+/)
            .map((paragraph) => paragraph.trim())
            .filter(Boolean),
    );
    const photos = $derived(publicProfile?.photos ?? []);
    const cover = $derived(photos[0] ?? null);
    const gallery = $derived(photos.slice(1));
</script>

<svelte:head>
    <title>{program.name} ({program.code}) — {institution.name}</title>
    <meta name="description" content={publicProfile?.short_description ?? ''} />
</svelte:head>

<HomeLayout {institution} {alerts} {homeHref} {navigation}>
    <article
        class="mx-auto w-full max-w-7xl space-y-12 px-4 py-8 sm:space-y-16 sm:px-6 sm:py-12 lg:px-8"
    >
        <header class="space-y-5">
            <Link
                href={programsHref}
                class="text-primary focus-visible:ring-ring inline-flex min-h-[44px] items-center rounded-lg px-3 py-2 text-sm font-semibold underline underline-offset-4 focus-visible:ring-2 focus-visible:outline-none"
            >
                Back to programs
            </Link>
            <div class="border-border/60 border-b pb-6">
                <p
                    class="text-muted-foreground font-mono text-sm font-semibold tracking-[0.12em]"
                >
                    {program.code}
                </p>
                <h1
                    class="text-foreground mt-2 text-3xl leading-tight font-semibold tracking-[-0.035em] sm:text-5xl"
                >
                    {program.name}
                </h1>
                {#if publicProfile?.short_description}
                    <p
                        class="text-muted-foreground mt-5 max-w-[65ch] text-base leading-7 sm:text-lg"
                    >
                        {publicProfile.short_description}
                    </p>
                {/if}
            </div>
        </header>

        {#if cover}
            <figure class="mx-auto w-full max-w-5xl">
                <img
                    src={cover.src}
                    alt={cover.alt_text}
                    class="border-border/70 max-h-[min(72svh,42rem)] w-full rounded-lg border object-cover"
                    loading="eager"
                    decoding="async"
                />
                {#if cover.caption}
                    <figcaption
                        class="text-muted-foreground mt-3 text-sm leading-6"
                    >
                        {cover.caption}
                    </figcaption>
                {/if}
            </figure>
        {:else}
            <p
                class="text-muted-foreground border-border rounded-lg border border-dashed p-5 text-sm leading-6"
            >
                No program photos are published yet.
            </p>
        {/if}

        <section
            aria-labelledby="program-about-heading"
            class="max-w-3xl space-y-5"
        >
            <h2
                id="program-about-heading"
                class="text-foreground text-2xl font-semibold tracking-tight sm:text-3xl"
            >
                About this program
            </h2>
            {#if paragraphs.length > 0}
                {#each paragraphs as paragraph}
                    <p
                        class="text-foreground text-base leading-7 break-words whitespace-pre-wrap"
                    >
                        {paragraph}
                    </p>
                {/each}
            {:else}
                <p
                    class="text-muted-foreground border-border rounded-lg border border-dashed p-5 text-sm leading-6"
                >
                    A full program description has not been published.
                </p>
            {/if}
        </section>

        {#if gallery.length > 0}
            <section aria-labelledby="program-photos-heading" class="space-y-5">
                <h2
                    id="program-photos-heading"
                    class="text-foreground text-2xl font-semibold tracking-tight sm:text-3xl"
                >
                    Program photos
                </h2>
                <div class="grid min-w-0 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    {#each gallery as photo (photo.id)}
                        <figure class="min-w-0">
                            <img
                                src={photo.src}
                                alt={photo.alt_text}
                                class="border-border/70 aspect-[4/3] w-full rounded-lg border object-cover"
                                loading="lazy"
                                decoding="async"
                            />
                            {#if photo.caption}
                                <figcaption
                                    class="text-muted-foreground mt-2 text-sm leading-6"
                                >
                                    {photo.caption}
                                </figcaption>
                            {/if}
                        </figure>
                    {/each}
                </div>
            </section>
        {/if}

        <section
            aria-labelledby="program-announcements-heading"
            class="border-border/60 border-t pt-8"
        >
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2
                    id="program-announcements-heading"
                    class="text-foreground text-2xl font-semibold tracking-tight sm:text-3xl"
                >
                    Program notices
                </h2>
                {#if programAnnouncementIndex}
                    <Link
                        href={programAnnouncementIndex}
                        class="text-primary inline-flex min-h-[44px] items-center text-sm font-semibold underline underline-offset-4"
                        >All program notices</Link
                    >
                {/if}
            </div>
            {#if announcements.length > 0}
                <ul
                    class="divide-border/60 border-border/60 mt-4 divide-y border-y"
                >
                    {#each announcements as announcement (announcement.id)}
                        <li class="py-4">
                            <Link
                                href={announcement.href}
                                class="text-foreground hover:text-primary focus-visible:ring-ring inline-flex min-h-[44px] items-center rounded-lg px-2 text-base font-semibold underline underline-offset-4 focus-visible:ring-2 focus-visible:outline-none"
                            >
                                {announcement.title}
                            </Link>
                            {#if announcement.summary}
                                <p
                                    class="text-muted-foreground px-2 pb-1 text-sm leading-6"
                                >
                                    {announcement.summary}
                                </p>
                            {/if}
                        </li>
                    {/each}
                </ul>
            {:else}
                <p
                    class="text-muted-foreground border-border mt-4 rounded-lg border border-dashed p-5 text-sm leading-6"
                >
                    No program notices are currently available.
                </p>
            {/if}
        </section>

        <section
            aria-labelledby="program-policies-heading"
            class="border-border/60 border-t pt-8"
        >
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2
                    id="program-policies-heading"
                    class="text-foreground text-2xl font-semibold tracking-tight sm:text-3xl"
                >
                    Program policies
                </h2>
                {#if programPolicyIndex}
                    <Link
                        href={programPolicyIndex}
                        class="text-primary inline-flex min-h-[44px] items-center text-sm font-semibold underline underline-offset-4"
                        >View all {program.code} policies</Link
                    >
                {/if}
            </div>
            <p class="text-muted-foreground mt-3 text-sm leading-6">
                Published documents issued for this active program.
            </p>
            {#if programPolicies.length > 0}
                <div
                    class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3"
                >
                    {#each programPolicies as policy (policy.href || policy.id)}
                        <PolicyBulletinCard {policy} />
                    {/each}
                </div>
            {:else}
                <p
                    class="text-muted-foreground border-border mt-4 rounded-lg border border-dashed p-5 text-sm leading-6"
                >
                    No program policies are currently available.
                </p>
            {/if}
        </section>
    </article>
</HomeLayout>
