<script>
    import { Link } from '@inertiajs/svelte';
    import { Badge } from '@/Components/ui/badge';
    import ViewModeToggle from '@/Components/kit/ViewModeToggle.svelte';
    import SwitchableListView from '@/Components/kit/SwitchableListView.svelte';
    let { notices = [], domain, canEdit = false } = $props();
    let search = $state('');
    let statusFilter = $state('all');
    let viewMode = $state('table');

    let activeCount = $derived(notices.filter((notice) => notice.active_version > 0).length);
    let draftCount = $derived(notices.filter((notice) => notice.has_draft).length);
    let systemCount = $derived(notices.filter((notice) => notice.authoring_mode === 'builtin').length);
    let filters = $derived([
        { value: 'all', label: `All (${notices.length})` },
        { value: 'active', label: `Active (${activeCount})` },
        { value: 'draft', label: `Draft (${draftCount})` },
        { value: 'system', label: `System (${systemCount})` },
    ]);
    const defaultTriggers = {
        'session-published': 'When examination schedule is published',
        'reminder': 'Countdown intervals (72h, 24h, 2h) before exam',
        'session-cancelled': 'When scheduled exam session is cancelled',
        'session-unpublished': 'When session is returned to draft for rescheduling',
        'slot-reserved': 'When candidate reserves an exam slot',
        'proctor-duty-assigned': 'When proctoring staff is assigned to session',
        'session-completed': 'When test session concludes and grading opens',
        'submitted': 'When applicant submits admission form',
        'new-submission': 'When new dossier is queued for review',
        'accepted': 'When candidate is officially offered admission',
        'dismissed': 'When application non-admission is concluded',
        'correction-requested': 'When evaluator requests applicant update',
        'document-correction-requested': 'When document checklist correction requested',
        'released-online': 'When official exam results are released',
        'consultation-online-invited': 'When applicant receives an online consultation invitation',
        'consultation-reserved': 'When a Release Day appointment is confirmed',
    };

    function triggerFor(notice) {
        return notice.trigger ?? defaultTriggers[notice.slug] ?? 'When triggered by operational workflow';
    }

    function channelLabel(notice) {
        const channels = notice.channels ?? ['in_app'];
        const hasMail = channels.includes('mail');
        const hasInApp = channels.includes('in_app');
        if (hasMail && hasInApp) return 'Email + Portal';
        if (hasMail) return 'Email only';
        return 'Portal only';
    }

    let filtered = $derived(notices.filter((notice) => {
        const query = search.trim().toLowerCase();
        const matchesSearch = !query || `${notice.name} ${notice.description ?? ''} ${triggerFor(notice)}`.toLowerCase().includes(query);
        const matchesStatus = statusFilter === 'all'
            || (statusFilter === 'active' && notice.active_version > 0)
            || (statusFilter === 'draft' && notice.has_draft)
            || (statusFilter === 'system' && notice.authoring_mode === 'builtin');

        return matchesSearch && matchesStatus;
    }));

    function urlFor(notice) {
        const familyUrl = `/setup/notifications/${domain}/templates/${notice.slug}`;

        return notice.authoring_mode !== 'builtin' && canEdit ? `${familyUrl}/edit` : familyUrl;
    }

    function actionLabel(notice) {
        return notice.authoring_mode === 'builtin' || !canEdit ? 'View Template →' : 'Edit Message →';
    }
</script>

<section class="min-w-0 space-y-4" aria-labelledby={`families-${domain}`}>
    <div>
        <h2 id={`families-${domain}`} class="text-lg font-semibold">Notice families</h2>
        <p class="mt-1 text-sm text-muted-foreground">Open an actionable message directly, or inspect protected built-in configuration.</p>
    </div>

    <p class="sr-only" role="status">{filtered.length} matching notice templates</p>
    <div class="space-y-3">
        <div class="sticky top-0 z-20 flex flex-wrap items-center justify-between gap-3 border-b border-border bg-background/90 py-3 backdrop-blur-md">
            <label class="relative min-w-64 flex-1" for={`notice-search-${domain}`}>
                <span class="sr-only">Search notice templates</span>
                <span aria-hidden="true" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2">🔍</span>
                <input id={`notice-search-${domain}`} type="search" bind:value={search} placeholder="Search notice templates..." class="min-h-11 w-full rounded-md border border-border bg-background py-2 pl-10 pr-11 text-sm" />
                {#if search}
                    <button type="button" onclick={() => search = ''} aria-label="Clear search" class="absolute right-2 top-1/2 min-h-8 min-w-8 -translate-y-1/2 rounded text-muted-foreground hover:bg-muted hover:text-foreground">×</button>
                {/if}
            </label>
            <div class="flex flex-wrap items-center gap-2">
                <div class="flex flex-wrap gap-2" aria-label="Filter notice templates">
                    {#each filters as filter (filter.value)}
                        <button type="button" aria-pressed={statusFilter === filter.value} onclick={() => statusFilter = filter.value} class={`min-h-9 rounded-full border px-3 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring ${statusFilter === filter.value ? 'border-primary bg-primary text-primary-foreground' : 'border-border bg-background text-muted-foreground hover:text-foreground'}`}>
                            {filter.label}
                        </button>
                    {/each}
                </div>
                <ViewModeToggle bind:value={viewMode} />
            </div>
        </div>
        {#if viewMode === 'cards'}
            <div class="grid min-w-0 grid-cols-1 gap-3 lg:grid-cols-2">
                {#each filtered as notice (notice.id)}
                    <article class="min-w-0 space-y-3 rounded-xl border border-border bg-card p-4 transition-colors hover:border-border/80">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h3 class="break-words font-semibold"><Link href={urlFor(notice)} class="hover:underline">{notice.name}</Link></h3>
                                <p class="mt-1 break-words text-xs text-muted-foreground">{notice.description ?? 'No description supplied.'}</p>
                            </div>
                            <div class="flex shrink-0 flex-wrap gap-1.5">
                                {#if notice.has_draft}
                                    <Badge variant="warning">● Draft</Badge>
                                {:else if notice.active_version > 0}
                                    <Badge variant="success">● Active</Badge>
                                {/if}
                                {#if notice.authoring_mode === 'builtin'}
                                    <Badge variant="secondary">● System</Badge>
                                {/if}
                            </div>
                        </div>
                        <div class="space-y-1 rounded-lg border border-border/60 bg-muted/20 p-2.5 text-xs">
                            <div class="flex items-center gap-1.5 font-medium text-foreground">
                                <span>{channelLabel(notice)}</span>
                                {#if notice.supports_pdf}
                                    <span class="rounded bg-muted px-1.5 py-0.5 text-[10px] font-semibold text-muted-foreground">+ PDF</span>
                                {/if}
                            </div>
                            <p class="text-muted-foreground">{triggerFor(notice)}</p>
                        </div>
                        <Link href={urlFor(notice)} class={`inline-flex min-h-9 w-full items-center justify-center rounded-md px-4 text-xs font-semibold transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring ${notice.authoring_mode === 'builtin' || !canEdit ? 'border border-border bg-secondary text-secondary-foreground hover:bg-secondary/80' : 'bg-primary text-primary-foreground hover:bg-primary/90 shadow-sm'}`}>
                            {actionLabel(notice)}
                        </Link>
                    </article>
                {:else}
                    <p class="p-5 text-sm text-muted-foreground">{search.trim() || statusFilter !== 'all' ? 'No templates match these filters.' : 'No notice families are configured for this domain.'}</p>
                {/each}
            </div>
        {:else}
            <div class="overflow-x-auto rounded-xl border border-border bg-card">
                <table class="w-full min-w-[760px] text-sm">
                    <thead class="bg-muted/35 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th class="px-5 py-3">Notice Template</th>
                            <th class="px-5 py-3">Channels &amp; Trigger</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        {#each filtered as notice (notice.id)}
                            <tr class="hover:bg-muted/25 transition-colors">
                                <td class="max-w-[28rem] px-5 py-4">
                                    <p class="font-semibold"><Link href={urlFor(notice)} class="hover:underline">{notice.name}</Link></p>
                                    <p class="mt-1 text-xs text-muted-foreground line-clamp-2">{notice.description ?? 'No description supplied.'}</p>
                                </td>
                                <td class="px-5 py-4">
                                    <div class="space-y-1">
                                        <div class="flex items-center gap-1.5 text-xs font-medium text-foreground">
                                            <span>{channelLabel(notice)}</span>
                                            {#if notice.supports_pdf}
                                                <span class="rounded bg-muted px-1.5 py-0.5 text-[10px] font-semibold text-muted-foreground">+ PDF</span>
                                            {/if}
                                        </div>
                                        <p class="text-xs text-muted-foreground">{triggerFor(notice)}</p>
                                    </div>
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex flex-wrap gap-1.5">
                                        {#if notice.has_draft}
                                            <Badge variant="warning">● Draft</Badge>
                                        {:else if notice.active_version > 0}
                                            <Badge variant="success">● Active</Badge>
                                        {/if}
                                        {#if notice.authoring_mode === 'builtin'}
                                            <Badge variant="secondary">● System</Badge>
                                        {/if}
                                    </div>
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <Link href={urlFor(notice)} class={`inline-flex min-h-9 items-center justify-center rounded-md px-3.5 text-xs font-semibold transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring ${notice.authoring_mode === 'builtin' || !canEdit ? 'border border-border bg-secondary text-secondary-foreground hover:bg-secondary/80' : 'bg-primary text-primary-foreground hover:bg-primary/90 shadow-sm'}`}>
                                        {actionLabel(notice)}
                                    </Link>
                                </td>
                            </tr>
                        {:else}
                            <tr><td colspan="4" class="p-5 text-sm text-muted-foreground">{search.trim() || statusFilter !== 'all' ? 'No templates match these filters.' : 'No notice families are configured for this domain.'}</td></tr>
                        {/each}
                    </tbody>
                </table>
            </div>
        {/if}
    </div>
    {#if !canEdit}<p class="text-sm text-muted-foreground">Metadata-only view. Private configured copy and historical content require full-source access; authoring also depends on the family’s server-owned type.</p>{/if}
</section>
