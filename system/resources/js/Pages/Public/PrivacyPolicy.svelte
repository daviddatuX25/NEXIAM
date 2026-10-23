<script>
    import { Link } from '@inertiajs/svelte';
    import HomeLayout from '@/Layouts/HomeLayout.svelte';

    let { institution = {}, alerts = [], policy = null } = $props();
</script>

<svelte:head
    ><title
        >{policy?.title ?? 'Privacy Policy'} — {institution.name ??
            'Institution'}</title
    ></svelte:head
>

<HomeLayout {institution} {alerts}>
    <main class="mx-auto w-full max-w-4xl px-4 py-10 sm:px-6 sm:py-14 lg:px-8">
        <nav aria-label="Breadcrumb" class="mb-6 text-sm">
            <Link
                href="/policies"
                class="text-primary inline-flex min-h-[44px] items-center underline-offset-4 hover:underline"
                >Public policies</Link
            >
            <span class="text-muted-foreground px-2" aria-hidden="true">/</span>
            <span aria-current="page">Privacy Policy</span>
        </nav>
        {#if policy}
            <article
                class="border-border/80 bg-card rounded-xl border p-6 shadow-xs sm:p-10"
            >
                <header class="border-border/60 border-b pb-5">
                    <h1
                        class="text-foreground text-3xl font-semibold tracking-tight break-words sm:text-4xl"
                    >
                        {policy.title}
                    </h1>
                    <p class="text-muted-foreground mt-3 text-sm">
                        {policy.published_at
                            ? `Published ${new Date(policy.published_at).toLocaleDateString()}`
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
                        href="/policies"
                        class="text-primary inline-flex min-h-[44px] items-center underline underline-offset-4"
                        >Back to public policies</Link
                    >
                </footer>
            </article>
        {:else}
            <section
                class="border-border rounded-lg border border-dashed p-6"
                role="status"
            >
                <h1 class="text-foreground text-2xl font-semibold">
                    No active Privacy Policy
                </h1>
                <p class="text-muted-foreground mt-3 text-sm leading-6">
                    A canonical public Privacy Policy is not currently
                    available.
                </p>
                <div class="mt-5">
                    <Link
                        href="/policies"
                        class="text-primary inline-flex min-h-[44px] items-center underline underline-offset-4"
                        >Back to public policies</Link
                    >
                </div>
            </section>
        {/if}
    </main>
</HomeLayout>
