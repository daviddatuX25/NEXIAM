<script>
    import { Link } from '@inertiajs/svelte';
    import HomeLayout from '@/Layouts/HomeLayout.svelte';
    import PolicyBulletinCard from '@/Components/public/PolicyBulletinCard.svelte';

    let { institution = {}, alerts = [], publicPolicies = [] } = $props();
</script>

<svelte:head
    ><title>Public policies — {institution.name ?? 'Institution'}</title
    ></svelte:head
>

<HomeLayout {institution} {alerts}>
    <div class="mx-auto w-full max-w-7xl px-4 py-12 sm:px-6 sm:py-16 lg:px-8">
        <header class="max-w-[72ch]">
            <Link
                href="/"
                class="text-primary mb-4 inline-flex min-h-[44px] items-center text-sm font-semibold underline underline-offset-4"
                >Back to Home</Link
            >
            <h1
                class="text-foreground text-4xl font-semibold tracking-tight sm:text-5xl"
            >
                Public policies
            </h1>
            <p class="text-muted-foreground mt-4 text-base leading-7">
                Current canonical policies available for public reading.
            </p>
        </header>
        {#if publicPolicies.length > 0}
            <div
                class="mt-8 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3"
            >
                {#each publicPolicies as policy (policy.href)}
                    <PolicyBulletinCard {policy} />
                {/each}
            </div>
        {:else}
            <p
                role="status"
                class="border-border text-muted-foreground mt-8 max-w-3xl rounded-lg border border-dashed p-6 text-sm leading-6"
            >
                No active public policy is available at this time.
            </p>
        {/if}
    </div>
</HomeLayout>
