<script>
    import { Link } from '@inertiajs/svelte';
    import HomeLayout from '@/Layouts/HomeLayout.svelte';

    let {
        institution = {},
        alerts = [],
        announcements = { data: [], links: [] },
        program = null,
    } = $props();
    const rows = $derived(announcements?.data ?? []);

    function paginationLabel(label) {
        return String(label ?? '')
            .replace(/<[^>]*>/g, '')
            .replaceAll('&laquo;', '«')
            .replaceAll('&raquo;', '»');
    }
</script>

<svelte:head>
    <title>
        {program ? `${program.name} announcements` : 'Announcements'} — {institution.name ?? 'Institution'}
    </title>
</svelte:head>

<HomeLayout {institution} {alerts}>
        <div
            class="mx-auto w-full max-w-7xl px-4 py-12 sm:px-6 sm:py-16 lg:px-8"
        >
            <header class="max-w-[72ch]">
                {#if program}
                    <Link
                        href={`/programs/${encodeURIComponent(program.code)}`}
                        class="text-primary inline-flex min-h-[44px] items-center text-sm font-semibold underline underline-offset-4"
                    >
                        Back to {program.code}
                    </Link>
                {/if}
                <h1
                    class="text-foreground text-4xl font-semibold tracking-tight sm:text-5xl"
                >
                    {program ? `${program.name} announcements` : 'Announcements'}
                </h1>
                <p class="text-muted-foreground mt-4 text-base leading-7">
                    {program
                        ? `Announcements for ${program.code}.`
                        : 'General updates from Registrar and Guidance.'}
                </p>
            </header>
            {#if rows.length > 0}
                <ol
                    class="divide-border/60 border-border/60 mt-8 divide-y border-y"
                >
                    {#each rows as announcement (announcement.id)}
                        <li
                            class="grid min-w-0 gap-3 py-6 sm:grid-cols-[minmax(12rem,0.35fr)_minmax(0,1fr)_auto] sm:items-start sm:gap-8"
                        >
                            <div
                                class="text-primary flex flex-wrap items-center gap-x-3 gap-y-1 text-xs font-semibold"
                            >
                                <span class="break-words"
                                    >{announcement.publisher_label}</span
                                >
                                {#if announcement.published_at}<time
                                        datetime={announcement.published_at}
                                        >{new Date(
                                            announcement.published_at,
                                        ).toLocaleDateString()}</time
                                    >{/if}
                            </div>
                            <div class="min-w-0">
                                <h2
                                    class="text-foreground text-xl font-semibold break-words"
                                >
                                    {announcement.title}
                                </h2>
                                <p
                                    class="text-muted-foreground mt-2 max-w-[65ch] text-sm leading-6 break-words whitespace-pre-wrap"
                                >
                                    {announcement.summary}
                                </p>
                            </div>
                            <Link
                                href={announcement.href}
                                class="text-primary inline-flex min-h-[44px] items-center text-sm font-semibold underline underline-offset-4 sm:justify-self-end"
                                >Read announcement</Link
                            >
                        </li>
                    {/each}
                </ol>
                {#if announcements.links?.length > 0}
                    <nav
                        aria-label="Announcement pages"
                        class="mt-6 flex flex-wrap items-center gap-2"
                    >
                        {#each announcements.links as pageLink (pageLink.label)}
                            {#if pageLink.url}
                                <Link
                                    href={pageLink.url}
                                    preserveScroll
                                    class="border-border inline-flex min-h-[44px] items-center rounded-md border px-3 text-sm {pageLink.active
                                        ? 'bg-primary text-primary-foreground'
                                        : 'text-foreground hover:bg-muted'}"
                                    >{paginationLabel(pageLink.label)}</Link
                                >
                            {:else}
                                <span
                                    aria-disabled="true"
                                    class="border-border/50 text-muted-foreground inline-flex min-h-[44px] items-center rounded-md border px-3 text-sm"
                                    >{paginationLabel(pageLink.label)}</span
                                >
                            {/if}
                        {/each}
                    </nav>
                {/if}
            {:else}
                <p
                    role="status"
                    class="border-border text-muted-foreground mt-8 rounded-lg border border-dashed p-6 text-sm"
                >
                    There are no published announcements to display right now.
                </p>
            {/if}
        </div>
</HomeLayout>
