<script>
    import { Link } from '@inertiajs/svelte';
    import HomeLayout from '@/Layouts/HomeLayout.svelte';
    import PolicyBulletinCard from '@/Components/public/PolicyBulletinCard.svelte';
    let { program, policies = [], institution = {}, alerts = [] } = $props();
</script>

<svelte:head
    ><title
        >{program.name} policies — {institution.name ??
            'Public policies'}</title
    ></svelte:head
>
<HomeLayout {institution} {alerts}>
    <main class="mx-auto w-full max-w-7xl px-4 py-10 sm:px-6 sm:py-14 lg:px-8">
        <nav aria-label="Breadcrumb" class="mb-6 text-sm">
            <Link
                href={`/programs/${encodeURIComponent(program.code)}`}
                class="text-primary underline-offset-4 hover:underline"
                >{program.name}</Link
            ><span class="text-muted-foreground px-2" aria-hidden="true">/</span
            ><span aria-current="page">Public policies</span>
        </nav>
        <header class="max-w-3xl">
            <span
                class="bg-primary/10 text-primary inline-flex max-w-full rounded-md px-3 py-1 text-sm font-medium break-words"
                >{program.code}</span
            >
            <h1
                class="mt-2 text-3xl font-semibold tracking-tight break-words sm:text-4xl"
            >
                {program.name} public policies
            </h1>
            <p class="text-muted-foreground mt-3 text-base leading-7">
                Published documents issued for this active program.
            </p>
            <div class="mt-4">
                <Link
                    href="/policies"
                    class="text-primary inline-flex min-h-[44px] items-center text-sm underline underline-offset-4"
                    >View university-wide policies</Link
                >
            </div>
        </header>
        {#if policies.length > 0}
            <div
                class="mt-8 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3"
            >
                {#each policies as policy (policy.id)}
                    <PolicyBulletinCard {policy} />
                {/each}
            </div>
        {:else}
            <p
                role="status"
                class="border-border text-muted-foreground mt-8 max-w-3xl rounded-lg border border-dashed p-6 text-sm leading-6"
            >
                No public policies have been published for this program.
            </p>
        {/if}
    </main>
</HomeLayout>
