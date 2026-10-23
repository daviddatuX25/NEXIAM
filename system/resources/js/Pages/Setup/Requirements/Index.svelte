<script>
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
    import ActiveFilterChips from '@/Components/kit/ActiveFilterChips.svelte';
    import SwitchableListView from '@/Components/kit/SwitchableListView.svelte';
    import SimplePagination from '@/Components/kit/SimplePagination.svelte';
    import EntityRowActions from '@/Components/kit/EntityRowActions.svelte';
    import DocumentRequirementCard from '@/Components/Admission/DocumentRequirementCard.svelte';
    import { Badge } from '@/Components/ui/badge';
    import { Button } from '@/Components/ui/button';
    import * as Table from '@/Components/ui/table';
    import { Link, router } from '@inertiajs/svelte';
    import { ArrowDown, ArrowUp, ArrowUpDown, ChevronDown, Plus, Search, Sparkles, X } from 'lucide-svelte';

    const sortColumns = [
        { key: 'code', label: 'Code' },
        { key: 'label', label: 'Requirement Item' },
        { key: 'applicant_track', label: 'Applies to' },
        { key: 'item_kind', label: 'Kind' },
        { key: 'is_mandatory', label: 'Mandatory' },
        { key: 'is_active', label: 'Status' },
        { key: 'sort_order', label: 'Sort' },
    ];

    let { page_state = 'ready', requirements = [], filters = {}, tracks = [], kinds = [] } = $props();
    let viewMode = $state('responsive');
    let search = $state(filters.search ?? '');
    let track = $state(filters.track ?? '');
    let kind = $state(filters.kind ?? '');
    let mandatory = $state(filters.mandatory ?? '');
    let status = $state(filters.status ?? '');
    let togglingId = $state(null);
    let timer;

    const rows = $derived(Array.isArray(requirements) ? requirements : requirements?.data ?? []);
    const hasPagination = $derived(!Array.isArray(requirements) && requirements?.last_page > 1);
    let searchOpen = $state(false);
    let sort = $state('sort_order');
    let dir = $state('asc');
    let filtersOpen = $state(false);
    const activeSecondaryFilterCount = $derived([track, kind, mandatory, status].filter(Boolean).length);

    const sortedRows = $derived([...rows].sort((left, right) => {
        const first = left[sort];
        const second = right[sort];
        const comparison = typeof first === 'number' && typeof second === 'number'
            ? first - second
            : String(first ?? '').localeCompare(String(second ?? ''), undefined, { numeric: true, sensitivity: 'base' });
        return (dir === 'asc' ? comparison : -comparison) || String(left.code ?? '').localeCompare(String(right.code ?? ''));
    }));
    const suggestionRows = $derived.by(() => {
        const query = search.trim().toLowerCase();
        return rows.filter((req) => [req.code, req.label, req.applicant_track, req.item_kind]
            .some((value) => String(value ?? '').toLowerCase().includes(query))).slice(0, 6);
    });

    function toggleSort(column) {
        if (sort === column) {
            dir = dir === 'asc' ? 'desc' : 'asc';
        } else {
            sort = column;
            dir = 'asc';
        }
    }

    function sortState(column) {
        return sort === column ? (dir === 'asc' ? 'ascending' : 'descending') : 'none';
    }

    function sortIcon(column) {
        if (sort !== column) return ArrowUpDown;
        return dir === 'asc' ? ArrowUp : ArrowDown;
    }

    function normalizeOptions(options) {
        if (Array.isArray(options)) return options.map((item) => typeof item === 'string' ? { value: item, label: item } : { value: item.value ?? item.id, label: item.label ?? item.name ?? item.value });
        if (options && typeof options === 'object') return Object.entries(options).map(([value, label]) => ({ value, label: String(label) }));
        return [];
    }
    const trackList = $derived(normalizeOptions(tracks));
    const kindList = $derived(normalizeOptions(kinds));
    function applyFilters() {
        router.get('/setup/requirements', {
            search: search || undefined,
            track: track || undefined,
            kind: kind || undefined,
            mandatory: mandatory || undefined,
            status: status || undefined,
            page: 1,
        }, { preserveState: true, preserveScroll: true, replace: true });
    }
    function scheduleFilters() {
        clearTimeout(timer);
        timer = setTimeout(applyFilters, 250);
    }
    function filterChanged() { applyFilters(); }
    function clearFilter(id) {
        if (id === 'search') search = '';
        if (id === 'track') track = '';
        if (id === 'kind') kind = '';
        if (id === 'mandatory') mandatory = '';
        if (id === 'status') status = '';
        applyFilters();
    }
    function clearAllFilters() {
        search = ''; track = ''; kind = ''; mandatory = ''; status = '';
        applyFilters();
    }
    const activeFilterChips = $derived([
        ...(search ? [{ id: 'search', label: `Search: ${search}` }] : []),
        ...(track ? [{ id: 'track', label: `Track: ${trackList.find((item) => item.value === track)?.label ?? track}` }] : []),
        ...(kind ? [{ id: 'kind', label: `Kind: ${kindList.find((item) => item.value === kind)?.label ?? kind}` }] : []),
        ...(mandatory ? [{ id: 'mandatory', label: mandatory === 'mandatory' ? 'Mandatory' : 'Optional' }] : []),
        ...(status ? [{ id: 'status', label: status === 'active' ? 'Active' : 'Inactive' }] : []),
    ]);
    function toggleActive(req) {
        togglingId = req.id;
        router.patch(`/setup/requirements/${req.id}/toggle-active`, {
            is_active: !req.is_active,
            expected_version: req.version ?? 1,
        }, { preserveScroll: true, onFinish: () => { togglingId = null; } });
    }

    const breadcrumbs = [{ label: 'Setup', href: '/setup' }, { label: 'Application Requirements' }];
</script>

<svelte:head><title>Application Requirements · Setup</title></svelte:head>
<StaffLayout {breadcrumbs}>
    <div class="min-w-0 space-y-6 p-4 sm:p-6">
        <PageHeaderBar title="Application Requirements" description="Configure required and optional physical documents and supporting items for Registrar review.">
            {#snippet actions()}
                <Link href="/setup/requirements/create"><Button class="min-h-11 gap-2"><Plus class="size-4" />Add document requirement</Button></Link>
                <Link href="/setup"><Button variant="outline" size="sm">Back to readiness</Button></Link>
            {/snippet}
        </PageHeaderBar>
        <div class="sticky top-0 z-20 backdrop-blur-md bg-background/80 py-2 space-y-3">
            <div class="flex flex-col gap-3 md:flex-row md:items-start">
            <div class="relative w-full md:min-w-[280px] md:flex-1">
                <label for="requirement-search" class="sr-only">Search document requirements</label>
                <div class="relative flex items-center">
                    <Search class="pointer-events-none absolute left-3.5 size-4 text-muted-foreground" aria-hidden="true" />
                    <input
                        id="requirement-search"
                        type="search"
                        bind:value={search}
                        onfocus={() => (searchOpen = true)}
                        oninput={() => { searchOpen = true; scheduleFilters(); }}
                        onkeydown={(event) => {
                            if (event.key === 'Enter') { applyFilters(); searchOpen = false; }
                            if (event.key === 'Escape') searchOpen = false;
                        }}
                        placeholder="Search code or requirement label…"
                        class="h-11 min-h-[44px] w-full rounded-xl border border-input bg-background pl-10 pr-24 text-sm shadow-xs outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        role="combobox"
                        aria-autocomplete="list"
                        aria-expanded={searchOpen}
                        aria-controls="requirement-search-suggestions"
                    />
                    <div class="absolute right-2 flex items-center gap-1">
                        {#if search}
                            <button type="button" onclick={() => { search = ''; applyFilters(); }} class="grid min-h-[44px] min-w-[44px] place-items-center rounded-md text-muted-foreground hover:bg-muted md:min-h-[36px] md:min-w-[36px]" aria-label="Clear search"><X class="size-4" /></button>
                        {/if}
                        <Button class="min-h-[44px] md:min-h-[36px]" onclick={() => { applyFilters(); searchOpen = false; }}>Search</Button>
                    </div>
                </div>
                {#if searchOpen}
                    <div class="fixed inset-0 z-30" role="presentation" onclick={() => (searchOpen = false)}></div>
                    <div id="requirement-search-suggestions" class="absolute left-0 top-full z-50 mt-1.5 w-full max-w-2xl rounded-2xl border border-border bg-card p-4 shadow-2xl backdrop-blur-md" role="listbox" aria-label="Requirement search suggestions">
                        <div class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground"><Sparkles class="size-4 text-primary" />Quick shortcuts &amp; requirement matches</div>
                        <div class="flex flex-wrap gap-2">
                            {#each [{ label: 'Mandatory', value: 'mandatory' }, { label: 'Optional', value: 'optional' }] as shortcut}
                                <button type="button" class="min-h-[44px] rounded-lg border border-border bg-muted/40 px-3 text-xs font-medium hover:bg-muted md:min-h-[36px]" onclick={() => { mandatory = shortcut.value; applyFilters(); searchOpen = false; }}>{shortcut.label}</button>
                            {/each}
                        </div>
                        {#if suggestionRows.length}
                            <div class="mt-3 max-h-56 space-y-1 overflow-y-auto border-t border-border/50 pt-2">
                                {#each suggestionRows as req (req.id)}
                                    <button type="button" class="flex min-h-[44px] w-full items-center justify-between gap-3 rounded-lg px-3 text-left text-sm hover:bg-muted" onclick={() => { search = req.code; applyFilters(); searchOpen = false; }}>
                                        <span class="min-w-0 truncate"><span class="font-mono text-xs text-muted-foreground">{req.code}</span> · {req.label}</span><span class="shrink-0 text-xs text-primary">Search</span>
                                    </button>
                                {/each}
                            </div>
                        {/if}
                    </div>
                {/if}
            </div>
            <div class="flex items-center justify-between gap-2 md:hidden">
                <button
                    type="button"
                    class="inline-flex min-h-[44px] w-full items-center justify-between rounded-md border border-input bg-background px-3 text-sm font-semibold text-foreground shadow-xs dark:bg-input/30"
                    aria-expanded={filtersOpen}
                    aria-controls="requirement-mobile-filters"
                    onclick={() => (filtersOpen = !filtersOpen)}
                >
                    <span class="min-w-0 flex-1 truncate text-left">Filters ({activeSecondaryFilterCount})</span>
                    <ChevronDown class="shrink-0 ml-1.5 size-4 transition-transform {filtersOpen ? 'rotate-180' : ''}" />
                </button>
            </div>
            <div id="requirement-mobile-filters" class={filtersOpen ? 'grid grid-cols-1 gap-3 pt-2 md:flex md:flex-wrap md:items-end md:pt-0' : 'hidden md:flex md:flex-wrap md:items-end md:pt-0'}>
                <label class="space-y-1.5"><span class="text-xs font-medium text-muted-foreground">Track</span><div class="relative flex h-10 min-h-[44px] md:h-9 md:min-h-9 items-center rounded-md border border-input bg-background pr-2 text-foreground shadow-xs dark:bg-input/30"><select bind:value={track} onchange={filterChanged} class="min-w-0 flex-1 truncate appearance-none bg-transparent px-3 text-xs outline-none"><option value="">Any track</option>{#each trackList as item (item.value)}<option value={item.value}>{item.label}</option>{/each}</select><ChevronDown class="shrink-0 ml-1.5 size-4" aria-hidden="true" /></div></label>
                <label class="space-y-1.5"><span class="text-xs font-medium text-muted-foreground">Kind</span><div class="relative flex h-10 min-h-[44px] md:h-9 md:min-h-9 items-center rounded-md border border-input bg-background pr-2 text-foreground shadow-xs dark:bg-input/30"><select bind:value={kind} onchange={filterChanged} class="min-w-0 flex-1 truncate appearance-none bg-transparent px-3 text-xs outline-none"><option value="">Any kind</option>{#each kindList as item (item.value)}<option value={item.value}>{item.label}</option>{/each}</select><ChevronDown class="shrink-0 ml-1.5 size-4" aria-hidden="true" /></div></label>
                <label class="space-y-1.5"><span class="text-xs font-medium text-muted-foreground">Mandatory</span><div class="relative flex h-10 min-h-[44px] md:h-9 md:min-h-9 items-center rounded-md border border-input bg-background pr-2 text-foreground shadow-xs dark:bg-input/30"><select bind:value={mandatory} onchange={filterChanged} class="min-w-0 flex-1 truncate appearance-none bg-transparent px-3 text-xs outline-none"><option value="">Any requirement</option><option value="mandatory">Mandatory</option><option value="optional">Optional</option></select><ChevronDown class="shrink-0 ml-1.5 size-4" aria-hidden="true" /></div></label>
                <label class="space-y-1.5"><span class="text-xs font-medium text-muted-foreground">Status</span><div class="relative flex h-10 min-h-[44px] md:h-9 md:min-h-9 items-center rounded-md border border-input bg-background pr-2 text-foreground shadow-xs dark:bg-input/30"><select bind:value={status} onchange={filterChanged} class="min-w-0 flex-1 truncate appearance-none bg-transparent px-3 text-xs outline-none"><option value="">Any status</option><option value="active">Active</option><option value="inactive">Inactive</option></select><ChevronDown class="shrink-0 ml-1.5 size-4" aria-hidden="true" /></div></label>
            </div>
        </div>
        <ActiveFilterChips chips={activeFilterChips} onRemove={clearFilter} onClearAll={clearAllFilters} />

        {#if page_state === 'empty'}
            <div class="rounded-xl border border-dashed border-border bg-card px-6 py-14 text-center">
                <h2 class="font-semibold">No document requirements yet</h2>
                <p class="mx-auto mt-1 max-w-md text-sm text-muted-foreground">Create the first document requirement for Registrar review.</p>
                <Link href="/setup/requirements/create" class="mt-5 inline-flex"><Button class="min-h-11 gap-2"><Plus class="size-4" />Add document requirement</Button></Link>
            </div>
        {:else if rows.length === 0}
            <div class="rounded-xl border border-dashed border-border bg-card px-6 py-14 text-center">
                <h2 class="font-semibold">No matching document requirements</h2>
                <p class="mt-1 text-sm text-muted-foreground">Adjust or clear the active filters to see requirements.</p>
                <Button variant="outline" class="mt-4" onclick={clearAllFilters}>Clear filters</Button>
            </div>
        {:else}
            <SwitchableListView bind:viewMode cardsInset={false}>
                {#snippet table()}

                    <Table.Root class="w-full min-w-[900px] text-sm">
                        <Table.Header class="bg-muted/40 border-b border-border">
                            <Table.Row>
                                {#each sortColumns as column (column.key)}
                                    {@const SortIcon = sortIcon(column.key)}
                                    <Table.Head class="px-4 py-3 text-left align-middle" aria-sort={sortState(column.key)}>
                                        <button type="button" onclick={() => toggleSort(column.key)} class="inline-flex min-h-[44px] items-center gap-1.5 rounded-sm font-medium hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                                            <span>{column.label}</span>
                                            <SortIcon class={sort === column.key ? 'size-3.5 text-primary' : 'size-3.5 opacity-40'} aria-hidden="true" />
                                        </button>
                                    </Table.Head>
                                {/each}
                                <Table.Head class="w-px whitespace-nowrap px-4 py-3 text-right">Actions</Table.Head>
                            </Table.Row>
                        </Table.Header>
                        <Table.Body>
                            {#each sortedRows as req (req.id)}
                                <Table.Row class="border-b border-border/40 hover:bg-muted/30 transition-colors">
                                    <Table.Cell class="font-mono text-xs">{req.code}</Table.Cell><Table.Cell class="font-medium">{req.label}</Table.Cell><Table.Cell>{trackList.find((item) => item.value === req.applicant_track)?.label ?? req.applicant_track}</Table.Cell><Table.Cell class="capitalize">{req.item_kind}</Table.Cell><Table.Cell>{req.is_mandatory ? 'Mandatory' : 'Optional'}</Table.Cell><Table.Cell><Badge variant={req.is_active ? 'default' : 'secondary'}>{req.is_active ? 'Active' : 'Inactive'}</Badge></Table.Cell><Table.Cell>{req.sort_order}</Table.Cell>
                                    <Table.Cell class="w-px whitespace-nowrap text-right"><EntityRowActions primaryAction={{ label: 'Edit', href: `/setup/requirements/${req.id}/edit`, icon: false }} overflowItems={[{ label: req.is_active ? 'Deactivate' : 'Activate', disabled: togglingId === req.id, onclick: () => toggleActive(req) }]} /></Table.Cell>
                                </Table.Row>
                            {/each}
                        </Table.Body>
                    </Table.Root>
                {/snippet}
                {#snippet cards()}
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {#each rows as req (req.id)}
                            <DocumentRequirementCard
                                id={`setup-req-card-${req.id}`}
                                label={req.label}
                                code={req.code}
                                itemKind={req.item_kind}
                                isMandatory={req.is_mandatory}
                                trackLabel={trackList.find((item) => item.value === req.applicant_track)?.label ?? req.applicant_track}
                            >
                                {#snippet status()}
                                    <Badge variant={req.is_active ? 'default' : 'secondary'}>{req.is_active ? 'Active' : 'Inactive'}</Badge>
                                {/snippet}
                                {#snippet metadata()}
                                    <span class="text-xs text-muted-foreground">Sort #{req.sort_order}</span>
                                {/snippet}
                                {#snippet actions()}
                                    <EntityRowActions primaryAction={{ label: 'Edit', href: `/setup/requirements/${req.id}/edit`, icon: false }} overflowItems={[{ label: req.is_active ? 'Deactivate' : 'Activate', disabled: togglingId === req.id, onclick: () => toggleActive(req) }]} />
                                {/snippet}
                            </DocumentRequirementCard>
                        {/each}
                    </div>
                {/snippet}
            </SwitchableListView>
            {#if hasPagination}<SimplePagination data={requirements} />{/if}
        {/if}
    </div>
</StaffLayout>
