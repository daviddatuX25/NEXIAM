<script lang="ts">
    import { Link } from '@inertiajs/svelte';

    type Desk = { key: string; label: string; href: string; can_access: boolean };
    type Sibling = { id: string | number; slug: string; name: string; active_version?: number | null; has_draft?: boolean; url: string };
    type Navigation = { current_domain: string; current_slug?: string | null; desks: Desk[]; siblings?: Sibling[] };

    let { navigation = null } = $props<{ navigation?: Navigation | null }>();
</script>

{#if navigation}
    <nav class="min-w-0 space-y-3" aria-label="Notification navigation">
        <div class="overflow-x-auto rounded-xl border border-border bg-card p-1.5">
            <div class="flex min-w-max gap-1" aria-label="Notification desks">
                {#each navigation.desks.filter((desk) => desk.can_access) as desk (desk.key)}
                    {@const isCurrentDesk = desk.key === navigation.current_domain}
                    <Link
                        href={desk.href}
                        aria-current={isCurrentDesk ? 'page' : undefined}
                        class={`inline-flex min-h-10 shrink-0 items-center justify-center rounded-lg px-3 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring sm:px-4 ${isCurrentDesk ? 'bg-primary text-primary-foreground shadow-sm' : 'text-muted-foreground hover:bg-muted hover:text-foreground'}`}
                    >
                        {desk.label}
                    </Link>
                {/each}
            </div>
        </div>

        {#if navigation.current_slug && navigation.siblings}
            <section class="min-w-0 space-y-2" aria-label="Templates in this desk">
                <h2 class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Templates in this desk</h2>
                <div class="flex min-w-0 gap-2 overflow-x-auto pb-1">
                    {#each navigation.siblings as sibling (sibling.id)}
                        {@const isCurrentTemplate = sibling.slug === navigation.current_slug}
                        <Link
                            href={sibling.url}
                            aria-current={isCurrentTemplate ? 'page' : undefined}
                            class={`inline-flex min-h-11 max-w-64 shrink-0 flex-col justify-center gap-0.5 rounded-lg border px-3 py-1.5 text-left transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring ${isCurrentTemplate ? 'border-primary bg-primary/5 text-foreground' : 'border-border bg-card text-muted-foreground hover:border-primary/50 hover:text-foreground'}`}
                        >
                            <span class="truncate text-sm font-medium">{sibling.name}</span>
                            <span class="flex items-center gap-1.5 text-[11px] leading-4">
                                {#if sibling.has_draft}
                                    <span class="inline-flex items-center gap-1"><span class="size-1.5 rounded-full bg-amber-500" aria-hidden="true"></span>Draft</span>
                                {/if}
                                {#if sibling.active_version}
                                    <span>{sibling.has_draft ? '· ' : ''}Published v{sibling.active_version}</span>
                                {:else if !sibling.has_draft}
                                    <span>Not published</span>
                                {/if}
                            </span>
                        </Link>
                    {/each}
                </div>
            </section>
        {/if}
    </nav>
{/if}
