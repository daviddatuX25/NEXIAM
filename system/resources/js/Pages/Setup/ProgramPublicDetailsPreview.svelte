<script lang="ts">
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
    import { Button } from '@/Components/ui/button';
    import { Link } from '@inertiajs/svelte';

    let { program, profile, revision = 0, published_revision = null } = $props();
    const editorUrl = `/setup/programs/${Number(program.id)}/public-details`;
    const breadcrumbs = [
        { label: 'Setup', href: '/setup' },
        { label: 'Public program details', href: editorUrl },
        { label: 'Private draft preview' },
    ];
</script>

<svelte:head>
    <title>Private program profile preview · Setup</title>
</svelte:head>

<StaffLayout {breadcrumbs}>
    <div class="mx-auto min-w-0 max-w-7xl space-y-6 p-4 sm:p-6">
        <PageHeaderBar
            title="Private program profile preview"
            description="Review the saved profile privately. Visitors continue to see the last explicitly published revision."
        >
            {#snippet actions()}
                <Link href={editorUrl}>
                    <Button variant="outline" class="min-h-11" disabled={false}>Back to editor</Button>
                </Link>
            {/snippet}
        </PageHeaderBar>

        <div class="border-border bg-card/70 rounded-lg border p-4 text-sm" role="note">
            {#if published_revision}
                Visitors currently see published revision {published_revision}. This preview shows saved profile revision {revision} only to authorized program staff.
            {:else}
                No profile revision has been published. This preview is private; the public program page does not show this draft.
            {/if}
        </div>

        {#if Number(revision) === 0}
            <section class="border-border bg-card/70 rounded-lg border p-6" aria-labelledby="empty-preview-title">
                <h2 id="empty-preview-title" class="text-lg font-semibold">No saved draft yet</h2>
                <p class="text-muted-foreground mt-2 text-sm leading-6">Save a profile draft in the editor before opening its private preview.</p>
            </section>
        {:else}
            <article class="border-border bg-card/70 min-w-0 overflow-hidden rounded-lg border">
                {#if profile.photos?.[0]}
                    {#if profile.photos[0].available === false}
                        <p class="bg-muted/50 text-muted-foreground p-6 text-center text-sm">The private cover image is unavailable. Remove or replace it in the editor before publishing.</p>
                    {:else}
                        <figure class="bg-muted aspect-[16/7] overflow-hidden">
                            <img src={profile.photos[0].src} alt={profile.photos[0].alt_text} class="size-full object-cover" />
                        </figure>
                    {/if}
                {/if}
                <div class="space-y-6 p-5 sm:p-8">
                    <header class="space-y-3">
                        <p class="font-mono text-xs font-semibold tracking-wide text-muted-foreground">{program.code}</p>
                        <h2 class="text-2xl font-semibold leading-tight">{program.name}</h2>
                        {#if profile.short_description}
                            <p class="max-w-3xl text-base leading-7 text-muted-foreground">{profile.short_description}</p>
                        {/if}
                    </header>
                    <div class="max-w-4xl whitespace-pre-wrap break-words text-sm leading-7">{profile.description}</div>
                    {#if profile.photos?.length > 1}
                        <section class="space-y-4" aria-labelledby="private-preview-gallery-title">
                            <h3 id="private-preview-gallery-title" class="text-lg font-semibold">Saved photo gallery</h3>
                            <ul class="grid min-w-0 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                {#each profile.photos.slice(1) as photo (photo.id)}
                                    <li class="border-border min-w-0 overflow-hidden rounded-lg border">
                                        {#if photo.available === false}
                                            <p class="bg-muted/50 text-muted-foreground p-4 text-sm">This private photo is unavailable.</p>
                                        {:else}
                                            <img src={photo.src} alt={photo.alt_text} class="aspect-[4/3] w-full object-cover" />
                                        {/if}
                                        {#if photo.caption}
                                            <p class="p-3 text-sm text-muted-foreground">{photo.caption}</p>
                                        {/if}
                                    </li>
                                {/each}
                            </ul>
                        </section>
                    {/if}
                </div>
            </article>
        {/if}
    </div>
</StaffLayout>
