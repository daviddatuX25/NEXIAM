<script>
    import { Link } from '@inertiajs/svelte';
    import PolicyBulletinCard from '@/Components/public/PolicyBulletinCard.svelte';

    let { publicPolicies = [] } = $props();
</script>

<section
    id="policies"
    aria-labelledby="public-policies-heading"
    class="border-border/60 scroll-mt-[calc(var(--public-header-height,4rem)+1rem)] border-t pt-8"
>
    <div
        class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
    >
        <div>
            <h2
                id="public-policies-heading"
                class="text-foreground text-2xl font-semibold tracking-tight"
            >
                Public policies
            </h2>
            <p
                class="text-muted-foreground mt-2 max-w-[65ch] text-sm leading-6"
            >
                Read the current public policies and their published revisions.
            </p>
        </div>
        <Link
            href="/policies"
            class="text-primary inline-flex min-h-[44px] items-center gap-2 text-sm font-semibold underline underline-offset-4"
            >View all policies</Link
        >
    </div>
    {#if publicPolicies.length > 0}
        <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {#each publicPolicies as policy (policy.href)}
                <PolicyBulletinCard {policy} />
            {/each}
        </div>
    {:else}
        <p
            class="text-muted-foreground border-border mt-4 rounded-lg border border-dashed p-4 text-sm"
        >
            No active public policy is available.
        </p>
    {/if}
</section>
