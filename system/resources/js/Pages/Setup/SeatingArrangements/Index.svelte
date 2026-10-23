<script>
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
    import ActiveFilterChips from '@/Components/kit/ActiveFilterChips.svelte';
    import SwitchableListView from '@/Components/kit/SwitchableListView.svelte';
    import SimplePagination from '@/Components/kit/SimplePagination.svelte';
    import ActionDropdown from '@/Components/kit/ActionDropdown.svelte';
    import { Badge } from '@/Components/ui/badge';
    import { Button } from '@/Components/ui/button';
    import * as Table from '@/Components/ui/table';
    import { Link, router } from '@inertiajs/svelte';
    import { ArrowDown, ArrowUp, ArrowUpDown, ChevronDown, Grid3X3, Pencil, Plus, Power, PowerOff, Search, Sparkles, X } from 'lucide-svelte';

    let {
        page_state = 'ready',
        arrangements = [],
        filters = {},
    } = $props();

    let viewMode = $state('responsive');
    let search = $state(filters.search ?? '');
    let status = $state(filters.status ?? '');
    let sort = $state(filters.sort ?? 'name');
    let dir = $state(filters.dir ?? 'asc');
    let searchOpen = $state(false);
    let filtersOpen = $state(false);
    const activeSecondaryFilterCount = $derived([status].filter(Boolean).length);
    let timer;

    const rows = $derived(Array.isArray(arrangements) ? arrangements : arrangements?.data ?? []);
    const hasPagination = $derived(!Array.isArray(arrangements) && arrangements?.last_page);
    const suggestionRows = $derived.by(() => {
        const query = search.trim().toLowerCase();
        return rows.filter((arrangement) => [arrangement.name, arrangement.label, arrangement.booklet_pattern]
            .some((value) => String(value ?? '').toLowerCase().includes(query))).slice(0, 6);
    });
    const activeFilterChips = $derived([
        ...(search ? [{ id: 'search', label: `Search: ${search}` }] : []),
        ...(status ? [{ id: 'status', label: status === 'active' ? 'Active' : 'Inactive' }] : []),
    ]);

    function applyFilters() {
        clearTimeout(timer);
        router.get(
            '/setup/seating-arrangements',
            { search: search || undefined, status: status || undefined, sort, dir },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    function toggleSort(column) {
        if (sort === column) dir = dir === 'asc' ? 'desc' : 'asc';
        else { sort = column; dir = 'asc'; }
        applyFilters();
    }

    function sortState(column) {
        return sort === column ? (dir === 'asc' ? 'ascending' : 'descending') : 'none';
    }

    function scheduleFilters() {
        clearTimeout(timer);
        timer = setTimeout(applyFilters, 250);
    }

    function clearFilter(id) {
        if (id === 'search') search = '';
        if (id === 'status') status = '';
        applyFilters();
    }

    function clearAllFilters() {
        search = '';
        status = '';
        applyFilters();
    }

    function arrangementLabel(arrangement) {
        return arrangement.name ?? arrangement.label ?? `Arrangement ${arrangement.id}`;
    }

    function patternLabel(pattern) {
        return {
            uniform: 'Uniform A',
            checkerboard: 'Checkerboard A/B',
            latin_four: 'Latin four A–D',
            linear_alternating: 'Linear alternating A/B',
        }[pattern] ?? pattern ?? '—';
    }

    function availabilityLabel(arrangement) {
        if (arrangement.availability_label) return arrangement.availability_label;
        return arrangement.availability_mode === 'selected_rooms'
            ? `Selected rooms (${arrangement.rooms_count ?? arrangement.room_count ?? (arrangement.rooms ?? []).length})`
            : 'All compatible rooms';
    }

    function actionItems(arrangement) {
        return [
            {
                label: 'Edit arrangement',
                href: `/setup/seating-arrangements/${arrangement.id}/edit`,
                icon: Pencil,
            },
            {
                label: arrangement.is_active ? 'Deactivate' : 'Activate',
                href: `/setup/seating-arrangements/${arrangement.id}/${arrangement.is_active ? 'deactivate' : 'activate'}`,
                method: 'post',
                icon: arrangement.is_active ? PowerOff : Power,
                danger: arrangement.is_active,
            },
        ];
    }

    const breadcrumbs = [
        { label: 'Setup', href: '/setup' },
        { label: 'Seating Arrangements' },
    ];
</script>

<svelte:head><title>Seating Arrangements · Setup</title></svelte:head>

<StaffLayout {breadcrumbs}>
    <div class="min-w-0 space-y-6 p-4 sm:p-6">
        <PageHeaderBar
            title="Seating Arrangements"
            description="Create and maintain reusable seating layouts and desk arrangements for examination venues."
        >
            {#snippet actions()}
                <Link href="/setup/seating-arrangements/create">
                    <Button class="min-h-11 gap-2"><Plus class="h-4 w-4" /><span class="hidden sm:inline">Create arrangement</span></Button>
                </Link>
            {/snippet}
        </PageHeaderBar>

        <div class="sticky top-0 z-20 backdrop-blur-md bg-background/80 py-2 space-y-3">
            <div class="flex flex-col gap-3 md:flex-row md:items-start">
                <div class="relative w-full md:min-w-[280px] md:flex-1">
                    <label for="seating-search" class="sr-only">Search seating arrangements</label>
                    <div class="relative flex items-center">
                        <Search class="pointer-events-none absolute left-3.5 size-4 text-muted-foreground" aria-hidden="true" />
                        <input id="seating-search" type="search" bind:value={search} onfocus={() => (searchOpen = true)} oninput={() => { searchOpen = true; scheduleFilters(); }} onkeydown={(event) => {
                            if (event.key === 'Enter') { applyFilters(); searchOpen = false; }
                            if (event.key === 'Escape') searchOpen = false;
                        }} placeholder="Search arrangement names or desk patterns…" class="h-11 min-h-[44px] w-full rounded-xl border border-input bg-background pl-10 pr-24 text-sm text-foreground shadow-xs outline-none focus-visible:ring-2 focus-visible:ring-ring dark:bg-input/30" role="combobox" aria-autocomplete="list" aria-expanded={searchOpen} aria-controls="seating-search-suggestions" />
                        <div class="absolute right-2 flex items-center gap-1">
                            {#if search}<button type="button" onclick={() => { search = ''; applyFilters(); }} class="grid min-h-[44px] min-w-[44px] place-items-center rounded-md text-muted-foreground hover:bg-muted md:min-h-[36px] md:min-w-[36px]" aria-label="Clear search"><X class="size-4" /></button>{/if}
                            <Button class="min-h-[44px] md:min-h-[36px]" onclick={() => { applyFilters(); searchOpen = false; }}>Search</Button>
                        </div>
                    </div>
                    {#if searchOpen}
                        <div class="fixed inset-0 z-30" role="presentation" onclick={() => (searchOpen = false)}></div>
                        <div id="seating-search-suggestions" class="absolute left-0 top-full z-50 mt-1.5 w-full max-w-2xl rounded-2xl border border-border bg-card p-4 shadow-2xl backdrop-blur-md" role="listbox" aria-label="Seating arrangement search suggestions">
                            <div class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground"><Sparkles class="size-4 text-primary" />Quick shortcuts &amp; matching arrangements</div>
                            <div class="flex flex-wrap gap-2">
                                {#each [{ label: 'Active', value: 'active' }, { label: 'Inactive', value: 'inactive' }] as shortcut}
                                    <button type="button" class="min-h-[44px] rounded-lg border border-border bg-muted/40 px-3 text-xs font-medium hover:bg-muted md:min-h-[36px]" onclick={() => { status = shortcut.value; applyFilters(); searchOpen = false; }}>{shortcut.label}</button>
                                {/each}
                            </div>
                            {#if suggestionRows.length}
                                <div class="mt-3 max-h-56 space-y-1 overflow-y-auto border-t border-border/50 pt-2">
                                    {#each suggestionRows as arrangement (arrangement.id)}
                                        <button type="button" class="flex min-h-[44px] w-full items-center justify-between gap-3 rounded-lg px-3 text-left text-sm hover:bg-muted" onclick={() => { search = arrangementLabel(arrangement); applyFilters(); searchOpen = false; }}>
                                            <span class="min-w-0 truncate">{arrangementLabel(arrangement)} · {patternLabel(arrangement.booklet_pattern)}</span><span class="shrink-0 text-xs text-primary">Search</span>
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
                        aria-controls="seating-mobile-filters"
                        onclick={() => (filtersOpen = !filtersOpen)}
                    >
                        <span>Filters ({activeSecondaryFilterCount})</span>
                        <ChevronDown class="shrink-0 ml-1.5 size-4 transition-transform {filtersOpen ? 'rotate-180' : ''}" />
                    </button>
                </div>
                <div id="seating-mobile-filters" class={filtersOpen ? 'grid grid-cols-1 gap-3 pt-2 md:flex md:flex-wrap md:items-end md:pt-0' : 'hidden md:flex md:flex-wrap md:items-end md:pt-0'}>
                    <label class="space-y-1.5"><span class="text-xs font-medium text-muted-foreground">Status</span><div class="flex h-10 min-h-[44px] md:h-9 md:min-h-9 items-center rounded-md border border-input bg-background pr-2 text-foreground shadow-xs dark:bg-input/30"><select bind:value={status} onchange={applyFilters} class="min-w-0 flex-1 truncate appearance-none bg-transparent px-3 text-xs outline-none"><option value="">All statuses</option><option value="active">Active</option><option value="inactive">Inactive</option></select><ChevronDown class="shrink-0 ml-1.5 size-4" aria-hidden="true" /></div></label>
                    <label class="space-y-1.5"><span class="text-xs font-medium text-muted-foreground">Sort by</span><div class="flex h-10 min-h-[44px] md:h-9 md:min-h-9 items-center rounded-md border border-input bg-background pr-2 text-foreground shadow-xs dark:bg-input/30"><select bind:value={sort} onchange={applyFilters} class="min-w-0 flex-1 truncate appearance-none bg-transparent px-3 text-xs outline-none"><option value="name">Arrangement</option><option value="capacity">Desk capacity</option><option value="is_active">Status</option></select><ChevronDown class="shrink-0 ml-1.5 size-4" aria-hidden="true" /></div></label>
                    <label class="space-y-1.5"><span class="text-xs font-medium text-muted-foreground">Direction</span><div class="flex h-10 min-h-[44px] md:h-9 md:min-h-9 items-center rounded-md border border-input bg-background pr-2 text-foreground shadow-xs dark:bg-input/30"><select bind:value={dir} onchange={applyFilters} class="min-w-0 flex-1 truncate appearance-none bg-transparent px-3 text-xs outline-none"><option value="asc">Ascending</option><option value="desc">Descending</option></select><ChevronDown class="shrink-0 ml-1.5 size-4" aria-hidden="true" /></div></label>
                </div>
        </div>
        <ActiveFilterChips chips={activeFilterChips} onRemove={clearFilter} onClearAll={clearAllFilters} />

        {#if page_state === 'denied'}
            <div class="border-border bg-card rounded-xl border p-6 text-sm text-muted-foreground" role="status">
                You do not have permission to manage seating arrangements.
            </div>
        {:else if page_state === 'loading'}
            <div class="border-border bg-card animate-pulse rounded-xl border p-6" aria-label="Loading seating arrangements">
                <div class="bg-muted h-5 w-48 rounded"></div>
                <div class="bg-muted mt-4 h-12 rounded"></div>
                <div class="bg-muted mt-2 h-12 rounded"></div>
            </div>
        {:else if page_state === 'error'}
            <div class="border-destructive/40 bg-destructive/10 text-destructive rounded-xl border p-6 text-sm" role="alert">
                Seating arrangements could not be loaded. Refresh the page and try again.
            </div>
        {:else if rows.length === 0}
            <div class="border-border bg-card rounded-xl border border-dashed px-6 py-14 text-center">
                <Grid3X3 class="text-muted-foreground mx-auto h-8 w-8" />
                <h2 class="mt-4 font-semibold">{search || status ? 'No matching arrangements' : 'No seating arrangements yet'}</h2>
                <p class="text-muted-foreground mx-auto mt-1 max-w-md text-sm">
                    {search || status ? 'Try a different search or status filter.' : 'Create the first reusable arrangement to define deterministic room seating.'}
                </p>
                {#if !search && !status}
                    <Link href="/setup/seating-arrangements/create" class="mt-5 inline-flex">
                        <Button class="min-h-11 gap-2"><Plus class="h-4 w-4" />Create arrangement</Button>
                    </Link>
                {/if}
            </div>
        {:else}
            <SwitchableListView bind:viewMode>
                {#snippet table()}
                    <Table.Root class="w-full min-w-[760px] text-sm">
                        <Table.Header class="bg-muted/40 border-b border-border">
                            <Table.Row>
                                <Table.Head class="px-4 py-3 text-left align-middle" aria-sort={sortState('name')}><button type="button" class="inline-flex min-h-[44px] items-center gap-1.5 font-medium hover:text-primary" onclick={() => toggleSort('name')}>Arrangement {#if sort === 'name'}{#if dir === 'asc'}<ArrowUp class="size-3.5" aria-hidden="true" />{:else}<ArrowDown class="size-3.5" aria-hidden="true" />{/if}{:else}<ArrowUpDown class="size-3.5 opacity-40" aria-hidden="true" />{/if}</button></Table.Head>
                                <Table.Head class="px-4 py-3 text-left align-middle" aria-sort={sortState('capacity')}><button type="button" class="inline-flex min-h-[44px] items-center gap-1.5 font-medium hover:text-primary" onclick={() => toggleSort('capacity')}>Desk pattern {#if sort === 'capacity'}{#if dir === 'asc'}<ArrowUp class="size-3.5" aria-hidden="true" />{:else}<ArrowDown class="size-3.5" aria-hidden="true" />{/if}{:else}<ArrowUpDown class="size-3.5 opacity-40" aria-hidden="true" />{/if}</button></Table.Head>
                                <Table.Head>Availability</Table.Head>
                                <Table.Head class="px-4 py-3 text-left align-middle" aria-sort={sortState('is_active')}><button type="button" class="inline-flex min-h-[44px] items-center gap-1.5 font-medium hover:text-primary" onclick={() => toggleSort('is_active')}>Status {#if sort === 'is_active'}{#if dir === 'asc'}<ArrowUp class="size-3.5" aria-hidden="true" />{:else}<ArrowDown class="size-3.5" aria-hidden="true" />{/if}{:else}<ArrowUpDown class="size-3.5 opacity-40" aria-hidden="true" />{/if}</button></Table.Head>
                            </Table.Row>
                        </Table.Header>
                        <Table.Body>
                            {#each rows as arrangement (arrangement.id)}
                                <Table.Row class="border-b border-border/40 hover:bg-muted/30 transition-colors {!arrangement.is_active ? 'text-muted-foreground/75' : ''}">
                                    <Table.Cell>
                                        <p class="font-medium text-foreground">{arrangementLabel(arrangement)}</p>
                                        <p class="text-muted-foreground text-xs">Version {arrangement.lock_version ?? '—'}</p>
                                    </Table.Cell>
                                    <Table.Cell>
                                        <p>{arrangement.rows ?? '—'} × {arrangement.columns ?? '—'} · {arrangement.desk_count ?? ((arrangement.rows ?? 0) * (arrangement.columns ?? 0))} desks</p>
                                        <p class="text-muted-foreground text-xs">{patternLabel(arrangement.booklet_pattern)}</p>
                                    </Table.Cell>
                                    <Table.Cell>{availabilityLabel(arrangement)}</Table.Cell>
                                    <Table.Cell><Badge variant={arrangement.is_active ? 'default' : 'secondary'}>{arrangement.is_active ? 'Active' : 'Inactive'}</Badge></Table.Cell>
                                    <Table.Cell class="text-right"><ActionDropdown items={actionItems(arrangement)} /></Table.Cell>
                                </Table.Row>
                            {/each}
                        </Table.Body>
                    </Table.Root>
                {/snippet}
                {#snippet cards()}
                    <ul class="grid grid-cols-1 gap-3" role="list">
                        {#each rows as arrangement (arrangement.id)}
                            <li class="border-border bg-card min-w-0 rounded-xl border p-4 {arrangement.is_active ? '' : 'opacity-80'}">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <h2 class="truncate font-semibold">{arrangementLabel(arrangement)}</h2>
                                        <p class="text-muted-foreground mt-1 text-sm">{arrangement.rows ?? '—'} × {arrangement.columns ?? '—'} · {arrangement.desk_count ?? ((arrangement.rows ?? 0) * (arrangement.columns ?? 0))} desks</p>
                                    </div>
                                    <Badge variant={arrangement.is_active ? 'default' : 'secondary'}>{arrangement.is_active ? 'Active' : 'Inactive'}</Badge>
                                </div>
                                <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                                    <div><dt class="text-muted-foreground">Pattern</dt><dd class="font-medium">{patternLabel(arrangement.booklet_pattern)}</dd></div>
                                    <div><dt class="text-muted-foreground">Availability</dt><dd class="font-medium">{availabilityLabel(arrangement)}</dd></div>
                                </dl>
                                <div class="mt-4 flex items-center justify-between gap-2">
                                    <Link href={`/setup/seating-arrangements/${arrangement.id}/edit`}><Button variant="outline" class="min-h-11 gap-2"><Pencil class="h-4 w-4" />Edit</Button></Link>
                                    <ActionDropdown items={actionItems(arrangement)} />
                                </div>
                            </li>
                        {/each}
                    </ul>
                {/snippet}
            </SwitchableListView>
            {#if hasPagination}<SimplePagination data={arrangements} />{/if}
        {/if}
    </div>
</StaffLayout>
