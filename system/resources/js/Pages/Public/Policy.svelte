<script>
    import { Link } from '@inertiajs/svelte';
    import HomeLayout from '@/Layouts/HomeLayout.svelte';
    let {
        policy,
        owningProgram = null,
        institution = {},
        alerts = [],
    } = $props();
</script>

<svelte:head
    ><title>{policy.title} — {institution.name ?? 'Public policy'}</title
    ></svelte:head
>
<HomeLayout {institution} {alerts}>
    <main class="mx-auto w-full max-w-4xl px-4 py-10 sm:px-6 sm:py-14 lg:px-8">
        <nav aria-label="Breadcrumb" class="mb-6 text-sm break-words">
            <Link
                href={owningProgram
                    ? `/programs/${encodeURIComponent(owningProgram.code)}/policies`
                    : '/policies'}
                class="text-primary inline-flex min-h-[44px] items-center underline-offset-4 hover:underline"
                >{owningProgram
                    ? `${owningProgram.name} public policies`
                    : 'Public policies'}</Link
            >
            <span class="text-muted-foreground px-2" aria-hidden="true">/</span>
            <span aria-current="page">Policy</span>
        </nav>
        <article
            class="border-border/80 bg-card rounded-xl border p-6 shadow-xs sm:p-10"
        >
            <header class="border-border/60 border-b pb-5">
                <p class="text-muted-foreground text-sm break-words">
                    {owningProgram
                        ? `${owningProgram.code} · program policy`
                        : 'General public policy'} · {policy.publisher_label}
                </p>
                <h1
                    class="text-foreground mt-2 text-3xl font-semibold tracking-tight break-words sm:text-4xl"
                >
                    {policy.title}
                </h1>
                <p class="text-muted-foreground mt-3 text-sm break-words">
                    Published {policy.published_at
                        ? new Date(policy.published_at).toLocaleDateString()
                        : ''}
                </p>
            </header>
            <div
                class="text-foreground/90 py-8 text-base leading-7 break-words whitespace-pre-wrap"
            >
                {policy.content}
            </div>
            <footer class="border-border/60 border-t pt-5">
                <Link
                    href={owningProgram
                        ? `/programs/${encodeURIComponent(owningProgram.code)}/policies`
                        : '/policies'}
                    class="text-primary inline-flex min-h-[44px] items-center underline underline-offset-4"
                    >Back to {owningProgram ? owningProgram.name : 'public'} policies</Link
                >
            </footer>
        </article>
    </main>
</HomeLayout>
