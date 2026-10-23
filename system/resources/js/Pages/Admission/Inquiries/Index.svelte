<script>
    import { Link, router, usePage } from '@inertiajs/svelte';
    import { onMount } from 'svelte';
    import { SvelteURLSearchParams } from 'svelte/reactivity';
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import CaseConversation from '@/Components/Admission/CaseConversation.svelte';
    import CaseConversationStarter from '@/Components/Admission/CaseConversationStarter.svelte';
    import OfficeInquiryWorkspace from '@/Components/Admission/OfficeInquiryWorkspace.svelte';

    import NavBadge from '@/Components/kit/NavBadge.svelte';
    import { subscribeRealtimePulse } from '@/lib/realtime-pulse.svelte';
    let props = $props();
    const page = usePage();
    const selected = $derived(props.group === 'case_threads' ? props.selected_conversation : props.initial);
    const selectedId = $derived(selected?.thread?.id ?? '');
    const rows = $derived(props.group === 'case_threads' ? (props.thread_rows ?? []) : (props.inquiries ?? []));
    const startContext = $derived(props.group === 'case_threads' ? props.start_context : null);
    const starterProgramCode = $derived(props.program_context?.program_code ?? props.selected_conversation?.summary?.program_code ?? '');
    const starterAudience = $derived(props.selected_conversation?.thread?.thread_type ?? 'internal_ph_guidance');
    const starterPrincipalContext = $derived([
        props.principal_key ?? '',
        page.props.auth?.user?.id ?? '',
        page.props.auth?.staff_inactivity_generation ?? '',
        page.props.practice_workspace?.id ?? '',
        props.group ?? '',
        startContext?.cycle_code ?? '',
        props.program_context?.program_code ?? '',
        props.selected_conversation?.thread?.id ?? '',
    ].join(':'));
    const groupUnread = $derived(page.props.navigation_badges?.groups?.messaging ?? {
        case_threads: props.group === 'case_threads' ? rows.reduce((total, row) => total + (row.unread_count ?? 0), 0) : 0,
        offices: props.group === 'offices' ? rows.reduce((total, row) => total + (row.unread_count ?? 0), 0) : 0,
    });

    onMount(() => {
        let navigating = false;
        const removeStartListener = router.on('start', () => { navigating = true; });
        const removeFinishListener = router.on('finish', () => { navigating = false; });
        const unsubscribe = subscribeRealtimePulse('messaging', () => {
            if (navigating) return;
            router.reload({
                only: ['thread_rows', 'inquiries', 'next_cursor', 'urls', 'navigation_badges', 'start_context'],
                preserveState: true,
                preserveScroll: true,
            });
        });

        return () => {
            unsubscribe();
            removeStartListener();
            removeFinishListener();
        };
    });

    function groupUrl(group) {
        return `${props.urls.index.split('?')[0]}?group=${group}`;
    }

    function olderUrl() {
        const params = new SvelteURLSearchParams(props.urls.index.split('?')[1] ?? '');
        params.set('group', props.group);
        params.delete('thread_id');
        params.delete('inquiry_id');
        if (props.search) params.set(props.group === 'case_threads' ? 'case_search' : 'office_search', props.search);
        if (props.next_cursor) params.set(props.group === 'case_threads' ? 'case_cursor' : 'office_cursor', props.next_cursor);
        return `${props.urls.index.split('?')[0]}?${params.toString()}`;
    }
</script>

<StaffLayout breadcrumbs={[{ label: 'Messages' }]}>
    <div class="min-w-0 p-3 sm:p-5">
        {#if startContext}
            <div class="mb-3">
                <CaseConversationStarter
                    programs={startContext.programs ?? []}
                    cycleCode={startContext.cycle_code}
                    principalContext={starterPrincipalContext}
                    initialProgramCode={starterProgramCode}
                    initialAudience={starterAudience}
                    allowApplicantSupport={true}
                    applicantLookupUrl={startContext.lookup_url}
                    destination="messages"
                    destinationUrl={props.urls.index}
                />
            </div>
        {/if}
        <div class="grid min-h-[min(78vh,900px)] min-w-0 grid-cols-1 overflow-hidden rounded-xl border border-border bg-card shadow-sm md:grid-cols-[minmax(17rem,22rem)_minmax(0,1fr)]">
            <aside class:hidden={Boolean(selected)} class="min-w-0 border-b border-border bg-muted/20 md:block md:border-b-0 md:border-r">
                <div class="space-y-3 border-b border-border p-4">
                    <h1 class="text-xl font-semibold tracking-tight">{Object.values(props.groups_available ?? {}).filter(Boolean).length > 1 ? 'Messages' : (props.group === 'case_threads' ? 'Case threads' : 'Office inquiries')}</h1>
                    {#if props.program_context}
                        <p class="text-sm text-muted-foreground">{props.program_context.program_code} · {props.program_context.cycle_code}</p>
                    {/if}
                    {#if Object.values(props.groups_available ?? {}).filter(Boolean).length > 1}
                        <nav class="flex gap-1 rounded-lg bg-muted p-1" aria-label="Message groups">
                            {#if props.groups_available.case_threads}
                                <Link href={groupUrl('case_threads')} class={`flex-1 inline-flex items-center justify-center gap-2 rounded-md px-3 py-2 text-center text-sm font-medium ${props.group === 'case_threads' ? 'bg-background shadow-sm' : 'text-muted-foreground'}`}>Case threads{#if groupUnread.case_threads > 0}<NavBadge badge={{ kind: 'count', count: groupUnread.case_threads, accessible_label: `${groupUnread.case_threads} unread case threads` }} size="sm" />{/if}</Link>
                            {/if}
                            {#if props.groups_available.offices}
                                <Link href={groupUrl('offices')} class={`flex-1 inline-flex items-center justify-center gap-2 rounded-md px-3 py-2 text-center text-sm font-medium ${props.group === 'offices' ? 'bg-background shadow-sm' : 'text-muted-foreground'}`}>Office inquiries{#if groupUnread.offices > 0}<NavBadge badge={{ kind: 'count', count: groupUnread.offices, accessible_label: `${groupUnread.offices} unread office inquiries` }} size="sm" />{/if}</Link>
                            {/if}
                        </nav>
                    {/if}
                    <form action={props.urls.index.split('?')[0]} method="get" class="flex gap-2">
                        <label class="sr-only" for="staff-messages-search">Search conversations</label>
                        <input id="staff-messages-search" name={props.group === 'case_threads' ? 'case_search' : 'office_search'} value={props.search ?? ''} placeholder="Search conversations" class="h-10 min-w-0 flex-1 rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring" />
                        <input type="hidden" name="group" value={props.group} />
                        {#if props.program_context}
                            <input type="hidden" name="program_code" value={props.program_context.program_code} />
                            <input type="hidden" name="cycle_code" value={props.program_context.cycle_code} />
                        {/if}
                        <button type="submit" class="min-h-10 rounded-md border border-border px-3 text-sm font-medium hover:bg-muted">Search</button>
                    </form>
                </div>
                <div class="max-h-[68vh] overflow-y-auto">
                    {#if rows.length === 0}
                        <p class="p-5 text-sm text-muted-foreground">{props.group === 'case_threads' ? 'No case threads in your current scope.' : 'No office inquiries in your queue.'}</p>
                    {:else}
                        <ul class="divide-y divide-border">
                            {#each rows as row (row.id)}
                                <li>
                                    <Link href={row.show_url} class={`block min-h-16 px-4 py-3 hover:bg-muted/50 ${selectedId === row.id ? 'bg-muted' : ''}`}>
                                        <span class="flex items-start justify-between gap-3">
                                            <span class="min-w-0 space-y-1">
                                                <span class="block truncate font-medium text-foreground">{row.subject}</span>
                                                {#if props.group !== 'case_threads' && row.applicant?.reference_code}
                                                    <span class="inline-flex items-center rounded-full border border-border bg-background px-2 py-0.5 text-[11px] font-medium text-muted-foreground">
                                                        {row.is_pre_application ? `Public · ${row.applicant.reference_code}` : `Applicant · ${row.applicant.reference_code}`}
                                                    </span>
                                                {/if}
                                                <span class="block truncate text-xs text-muted-foreground">{props.group === 'case_threads' ? (row.applicant?.display_name ?? 'Applicant case') : `${row.applicant?.display_name ?? row.office_label} · ${row.office_label}`}</span>
                                                {#if props.group === 'case_threads'}
                                                    <span class="block text-xs text-muted-foreground">
                                                        {row.thread_type === 'internal_ph_guidance' ? 'Staff discussion' : 'With applicant'} · {row.program_code}
                                                    </span>
                                                {/if}
                                            </span>
                                            <span class="flex shrink-0 items-center gap-2">
                                                {#if row.unread_count > 0}<span class="rounded-full bg-primary px-2 py-0.5 text-xs font-semibold text-primary-foreground" aria-label={`${row.unread_count} unread`}>{row.unread_count}</span>{/if}
                                                <span class={`rounded-full px-2 py-0.5 text-xs font-medium ${row.status === 'open' ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300' : 'bg-muted text-muted-foreground'}`}>{row.status}</span>
                                            </span>
                                        </span>
                                    </Link>
                                </li>
                            {/each}
                        </ul>
                    {/if}
                </div>
                {#if props.next_cursor}
                    <div class="border-t border-border p-3"><Link href={olderUrl()} class="block rounded-md px-3 py-2 text-center text-sm font-medium text-primary hover:bg-muted">Load older</Link></div>
                {/if}
            </aside>
            <main class:hidden={!selected} class="min-w-0 bg-background md:block">
                {#if selected}
                    <div class="flex min-w-0 items-center justify-between gap-2 border-b border-border px-3 py-2 md:hidden">
                        <Link href={props.urls.index} class="min-h-10 rounded-md px-3 py-2 text-sm font-medium text-primary">← Back to conversations</Link>
                        {#if props.group === 'case_threads' && selected.case_record_url}
                            <Link href={selected.case_record_url} class="rounded-md px-2 py-2 text-sm font-medium text-primary">View applicant review →</Link>
                        {/if}
                    </div>
                    {#if props.group === 'case_threads'}
                        <div class="hidden justify-end border-b border-border px-4 py-2 md:flex">
                            <Link href={selected.case_record_url} class="rounded-md px-3 py-2 text-sm font-medium text-primary hover:bg-muted">View applicant review →</Link>
                        </div>
                        <CaseConversation initial={selected} snapshotUrl={selected.snapshot_url} sendUrl={selected.send_url} readUrl={selected.read_url} principalContext={selected.principal_key} attemptContext={selected.attempt_key} />
                    {:else}
                        <OfficeInquiryWorkspace {...props} embedded={true} />
                    {/if}
                {:else}
                    <div class="hidden h-full min-h-80 items-center justify-center p-8 text-center text-sm text-muted-foreground md:flex">Select a conversation to read and reply.</div>
                {/if}
            </main>
        </div>
    </div>
</StaffLayout>
