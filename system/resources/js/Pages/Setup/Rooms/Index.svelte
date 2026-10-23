<script>
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
    import ActiveFilterChips from '@/Components/kit/ActiveFilterChips.svelte';
    import EntityRowActions from '@/Components/kit/EntityRowActions.svelte';
    import { Badge } from '@/Components/ui/badge';
    import { Button } from '@/Components/ui/button';
    import * as Table from '@/Components/ui/table';
    import { Link, router } from '@inertiajs/svelte';
    import SimplePagination from '@/Components/kit/SimplePagination.svelte';
    import { ArrowDown, ArrowUp, ArrowUpDown, Building2, ChevronDown, Eye, Pencil, Plus, Search, Sparkles, X } from 'lucide-svelte';

    let { rooms, filters = {}, kind_counts = {}, enable_seating_arrangements = false, can_manage_seating_policy = false, exam_feature_policy_version = 1, errors = {}, space_kinds = [] } = $props();
    const fallbackSpaceKinds = [
        { value: 'room', label: 'Exam rooms', short_label: 'Exam Room', badge_variant: 'secondary', badge_class: '' },
        { value: 'assembly_venue', label: 'Assembly venues', short_label: 'Assembly Venue', badge_variant: 'outline', badge_class: 'border-blue-500/30 text-blue-600 dark:text-blue-400' },
        { value: 'service_point', label: 'Service windows', short_label: 'Service Window', badge_variant: 'outline', badge_class: 'border-amber-500/30 text-amber-600 dark:text-amber-400' },
        { value: 'office', label: 'Offices / Other rooms', short_label: 'Office / Other', badge_variant: 'outline', badge_class: 'border-emerald-500/30 text-emerald-600 dark:text-emerald-400' },
    ];
    const effectiveSpaceKinds = $derived(space_kinds.length > 0 ? space_kinds : fallbackSpaceKinds);
    let seatingPolicySaving = $state(false);
    const sortOptions = [
        { value: 'code', label: 'Space Code' },
        { value: 'name', label: 'Space Name' },
        { value: 'capacity', label: 'Capacity' },
    ];

    let search = $state(filters.search ?? '');
    let selectedKind = $state(filters.kind ?? '');
    let status = $state(filters.status ?? '');
    let sort = $state(filters.sort ?? 'code');
    let dir = $state(filters.dir ?? 'asc');
    let timer;
    let filtersOpen = $state(false);
    const activeSecondaryFilterCount = $derived([selectedKind, status].filter(Boolean).length);
    let searchOpen = $state(false);
    const suggestionRooms = $derived.by(() => {
        const query = search.trim().toLowerCase();
        return (rooms.data ?? []).filter((room) => [room.code, room.name, room.space_kind]
            .some((value) => String(value ?? '').toLowerCase().includes(query))).slice(0, 6);
    });
    function toggleSort(column) {
        if (sort === column) dir = dir === 'asc' ? 'desc' : 'asc';
        else { sort = column; dir = 'asc'; }
        filterRooms();
    }
    function sortState(column) {
        return sort === column ? (dir === 'asc' ? 'ascending' : 'descending') : 'none';
    }

    const activeFilterChips = $derived.by(() => {
        const chips = [];
        if (search) chips.push({ id: 'search', label: `Search: ${search}` });
        if (status) chips.push({ id: 'status', label: status === 'active' ? 'Active' : 'Inactive' });
        if (selectedKind) {
            const match = effectiveSpaceKinds.find((k) => k.value === selectedKind);
            const kindLabel = match ? (match.short_label || match.label) : selectedKind;
            chips.push({ id: 'kind', label: `Kind: ${kindLabel}` });
        }
        return chips;
    });

    function clearFilter(id) {
        if (id === 'search') search = '';
        if (id === 'status') status = '';
        if (id === 'kind') selectedKind = '';
        filterRooms();
    }

    function clearAllFilters() {
        search = '';
        status = '';
        selectedKind = '';
        filterRooms();
    }

    function filterRooms() {
        clearTimeout(timer);
        timer = setTimeout(
            () =>
                router.get(
                    '/setup/rooms',
                    {
                        search: search || undefined,
                        kind: selectedKind || undefined,
                        status: status || undefined,
                        sort: sort || undefined,
                        dir: dir || undefined,
                        page: 1,
                    },
                    { preserveState: true, replace: true },
                ),
            250,
        );
    }
    function filterKind(kind) {
        selectedKind = kind;
        filterRooms();
    }
    function toggleSeatingPolicy(enabled) {
        if (seatingPolicySaving) return;
        seatingPolicySaving = true;
        router.post(
            '/setup/rooms/seating-policy',
            { enable_seating_arrangements: enabled, expected_version: exam_feature_policy_version },
            {
                preserveScroll: true,
                onFinish: () => {
                    seatingPolicySaving = false;
                },
            },
        );
    }
    function toggleRoom(room) {
        router.post(
            `/setup/rooms/${room.id}/${room.is_active ? 'deactivate' : 'activate'}`,
            {},
            { preserveScroll: true },
        );
    }
    const breadcrumbs = [
        { label: 'Setup', href: '/setup' },
        { label: enable_seating_arrangements ? 'Rooms & Seating' : 'Campus Venues & Exam Rooms' },
    ];
</script>

<svelte:head><title>{enable_seating_arrangements ? 'Rooms &amp; Seating' : 'Campus Venues &amp; Exam Rooms'} · Setup</title></svelte:head>

<StaffLayout {breadcrumbs}>
    <div class="min-w-0 space-y-6 p-4 sm:p-6">
        <PageHeaderBar
            title={enable_seating_arrangements ? 'Rooms & Seating' : 'Campus Venues & Exam Rooms'}
            description={enable_seating_arrangements
                ? 'Manage examination venues, room capacities, and default seating arrangements configured for test administrations.'
                : 'Manage examination venues, room capacities, and institutional service locations.'}
        >
            {#snippet actions()}<Link href="/setup/rooms/create"
                    ><Button class="min-h-11 gap-2"
                        ><Plus class="h-4 w-4" />Add room</Button
                    ></Link
                >{/snippet}
        </PageHeaderBar>

        {#if can_manage_seating_policy}
            <section class="craft-card-depth flex flex-col gap-4 rounded-xl border border-border/70 p-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0 space-y-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-sm font-semibold">Assigned seating availability</h2>
                        <Badge variant={enable_seating_arrangements ? 'default' : 'secondary'}>
                            {enable_seating_arrangements ? 'Available for new sessions' : 'Simple capacity default'}
                        </Badge>
                    </div>
                    <p id="seating-policy-help" class="text-muted-foreground max-w-2xl text-sm">
                        {enable_seating_arrangements
                            ? 'New sessions still use room capacity until staff explicitly assigns physical seats. Published seating stays unchanged.'
                            : 'New sessions use room capacity. Existing arrangements and published exam records are retained.'}
                    </p>
                </div>
                <label class="flex min-h-11 shrink-0 cursor-pointer items-center gap-2.5 text-sm font-medium select-none">
                    <input
                        type="checkbox"
                        class="accent-primary size-4 rounded focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                        checked={enable_seating_arrangements}
                        aria-describedby="seating-policy-help"
                        disabled={seatingPolicySaving}
                        onchange={(event) => toggleSeatingPolicy(event.currentTarget.checked)}
                    />
                    <span>Allow assigned seating</span>
                    {#if seatingPolicySaving}<span class="text-muted-foreground text-xs" aria-live="polite">Saving…</span>{/if}
                </label>
            </section>
        {/if}
        {#if errors.policy}
            <p class="text-destructive text-sm" role="alert">{errors.policy}</p>
        {/if}

        <div class="sticky top-0 z-20 backdrop-blur-md bg-background/80 py-2 space-y-3">
            <div class="flex flex-col gap-3 md:flex-row md:items-start">
                <div class="relative w-full md:min-w-[280px] md:flex-1">
                    <label for="room-search" class="sr-only">Search spaces</label>
                    <div class="relative flex items-center">
                        <Search class="pointer-events-none absolute left-3.5 size-4 text-muted-foreground" aria-hidden="true" />
                        <input id="room-search" type="search" bind:value={search} onfocus={() => (searchOpen = true)} oninput={() => { searchOpen = true; filterRooms(); }} onkeydown={(event) => {
                            if (event.key === 'Enter') { filterRooms(); searchOpen = false; }
                            if (event.key === 'Escape') searchOpen = false;
                        }} placeholder="Search space code or name…" class="h-11 min-h-[44px] w-full rounded-xl border border-input bg-background pl-10 pr-24 text-sm text-foreground shadow-xs outline-none focus-visible:ring-2 focus-visible:ring-ring dark:bg-input/30" role="combobox" aria-autocomplete="list" aria-expanded={searchOpen} aria-controls="room-search-suggestions" />
                        <div class="absolute right-2 flex items-center gap-1">
                            {#if search}<button type="button" onclick={() => { search = ''; filterRooms(); }} class="grid min-h-[44px] min-w-[44px] place-items-center rounded-md text-muted-foreground hover:bg-muted md:min-h-[36px] md:min-w-[36px]" aria-label="Clear search"><X class="size-4" /></button>{/if}
                            <Button class="min-h-[44px] md:min-h-[36px]" onclick={() => { filterRooms(); searchOpen = false; }}>Search</Button>
                        </div>
                    </div>
                    {#if searchOpen}
                        <div class="fixed inset-0 z-30" role="presentation" onclick={() => (searchOpen = false)}></div>
                        <div id="room-search-suggestions" class="absolute left-0 top-full z-50 mt-1.5 w-full max-w-2xl rounded-2xl border border-border bg-card p-4 shadow-2xl backdrop-blur-md" role="listbox" aria-label="Space search suggestions">
                            <div class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground"><Sparkles class="size-4 text-primary" />Quick shortcuts &amp; matching spaces</div>
                            <div class="flex flex-wrap gap-2">
                                {#each effectiveSpaceKinds as shortcut (shortcut.value)}
                                    <button type="button" class="min-h-[44px] rounded-lg border border-border bg-muted/40 px-3 text-xs font-medium hover:bg-muted md:min-h-[36px]" onclick={() => { filterKind(shortcut.value); searchOpen = false; }}>{shortcut.label}</button>
                                {/each}
                            </div>
                            {#if suggestionRooms.length}
                                <div class="mt-3 max-h-56 space-y-1 overflow-y-auto border-t border-border/50 pt-2">
                                    {#each suggestionRooms as room (room.id)}
                                        <button type="button" class="flex min-h-[44px] w-full items-center justify-between gap-3 rounded-lg px-3 text-left text-sm hover:bg-muted" onclick={() => { search = room.code; filterRooms(); searchOpen = false; }}>
                                            <span class="min-w-0 truncate"><span class="font-mono text-xs text-muted-foreground">{room.code}</span> · {room.name}</span><span class="shrink-0 text-xs text-primary">Search</span>
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
                        aria-controls="room-mobile-filters"
                        onclick={() => (filtersOpen = !filtersOpen)}
                    >
                        <span>Filters ({activeSecondaryFilterCount})</span>
                        <ChevronDown class="shrink-0 ml-1.5 size-4 transition-transform {filtersOpen ? 'rotate-180' : ''}" />
                    </button>
                </div>
                <div id="room-mobile-filters" class={filtersOpen ? 'grid grid-cols-1 gap-3 pt-2 md:flex md:flex-wrap md:items-end md:pt-0' : 'hidden md:flex md:flex-wrap md:items-end md:pt-0'}>
                    <label class="space-y-1"><span class="text-xs font-medium text-muted-foreground">Venue type</span><div class="flex h-10 min-h-[44px] md:h-9 md:min-h-9 items-center rounded-md border border-input bg-background pr-2 text-foreground shadow-xs dark:bg-input/30"><select bind:value={selectedKind} onchange={filterRooms} class="min-w-0 flex-1 truncate appearance-none bg-transparent px-3 text-xs outline-none"><option value="">All venues ({kind_counts.all ?? 0})</option>{#each effectiveSpaceKinds as kind (kind.value)}<option value={kind.value}>{kind.label} ({kind_counts[kind.value] ?? 0})</option>{/each}</select><ChevronDown class="shrink-0 ml-1.5 size-4" aria-hidden="true" /></div></label>
                    <label class="space-y-1"><span class="text-xs font-medium text-muted-foreground">Status</span><div class="flex h-10 min-h-[44px] md:h-9 md:min-h-9 items-center rounded-md border border-input bg-background pr-2 text-foreground shadow-xs dark:bg-input/30"><select bind:value={status} onchange={filterRooms} class="min-w-0 flex-1 truncate appearance-none bg-transparent px-3 text-xs outline-none"><option value="">All statuses</option><option value="active">Active</option><option value="inactive">Inactive</option></select><ChevronDown class="shrink-0 ml-1.5 size-4" aria-hidden="true" /></div></label>
                    <label class="space-y-1"><span class="text-xs font-medium text-muted-foreground">Sort by</span><div class="flex h-10 min-h-[44px] md:h-9 md:min-h-9 items-center rounded-md border border-input bg-background pr-2 text-foreground shadow-xs dark:bg-input/30"><select bind:value={sort} onchange={filterRooms} class="min-w-0 flex-1 truncate appearance-none bg-transparent px-3 text-xs outline-none">{#each sortOptions as option (option.value)}<option value={option.value}>{option.label}</option>{/each}</select><ChevronDown class="shrink-0 ml-1.5 size-4" aria-hidden="true" /></div></label>
                    <label class="space-y-1"><span class="text-xs font-medium text-muted-foreground">Direction</span><div class="flex h-10 min-h-[44px] md:h-9 md:min-h-9 items-center rounded-md border border-input bg-background pr-2 text-foreground shadow-xs dark:bg-input/30"><select bind:value={dir} onchange={filterRooms} class="min-w-0 flex-1 truncate appearance-none bg-transparent px-3 text-xs outline-none"><option value="asc">Ascending</option><option value="desc">Descending</option></select><ChevronDown class="shrink-0 ml-1.5 size-4" aria-hidden="true" /></div></label>
                </div>
        </div>

        {#if activeFilterChips.length > 0}
            <ActiveFilterChips
                chips={activeFilterChips}
                onremove={clearFilter}
                onclearall={clearAllFilters}
            />
        {/if}
        {#if rooms.data.length === 0}
            <div
                class="craft-card-depth rounded-xl border border-dashed border-border/70 px-6 py-14 text-center"
            >
                <Building2 class="text-muted-foreground mx-auto h-8 w-8" />
                <h2 class="mt-4 font-semibold">
                    {search ? 'No matching rooms' : 'No rooms yet'}
                </h2>
                <p class="text-muted-foreground mt-1 text-sm">
                    {search
                        ? 'Try a different code or room name.'
                        : 'Add the first exam room to define seating layouts.'}
                </p>
            </div>
        {:else}
            <div class="hidden overflow-x-auto md:block">
                <Table.Root class="w-full min-w-[720px] text-sm">
                    <Table.Header class="bg-muted/40 border-b border-border">
                        <Table.Row>
                            <Table.Head class="px-4 py-3 text-left align-middle" aria-sort={sortState('code')}><button type="button" class="inline-flex min-h-[44px] items-center gap-1.5 font-medium hover:text-primary" onclick={() => toggleSort('code')}>Space {#if sort === 'code'}{#if dir === 'asc'}<ArrowUp class="size-3.5" aria-hidden="true" />{:else}<ArrowDown class="size-3.5" aria-hidden="true" />{/if}{:else}<ArrowUpDown class="size-3.5 opacity-40" aria-hidden="true" />{/if}</button></Table.Head>
                            <Table.Head class="px-4 py-3 text-left align-middle">Kind</Table.Head>
                            <Table.Head class="px-4 py-3 text-left align-middle" aria-sort={sortState('capacity')}><button type="button" class="inline-flex min-h-[44px] items-center gap-1.5 font-medium hover:text-primary" onclick={() => toggleSort('capacity')}>Capacity {#if sort === 'capacity'}{#if dir === 'asc'}<ArrowUp class="size-3.5" aria-hidden="true" />{:else}<ArrowDown class="size-3.5" aria-hidden="true" />{/if}{:else}<ArrowUpDown class="size-3.5 opacity-40" aria-hidden="true" />{/if}</button></Table.Head>
                            {#if enable_seating_arrangements}<Table.Head>Seating arrangements</Table.Head>{/if}
                            <Table.Head>Status</Table.Head>
                            <Table.Head class="w-px whitespace-nowrap px-4 py-3 text-right">Actions</Table.Head>
                        </Table.Row>
                    </Table.Header>
                    <Table.Body
                        >{#each rooms.data as room (room.id)}<Table.Row class="border-b border-border/40 hover:bg-muted/30 transition-colors">
                                <Table.Cell>
                                    <Link href={`/setup/rooms/${room.id}`} class="font-medium text-foreground hover:underline">
                                        {room.name}
                                    </Link>
                                    <p
                                        class="text-muted-foreground font-mono text-xs"
                                    >
                                        {room.code}
                                    </p>
                                </Table.Cell>
                                <Table.Cell>
                                    {@const kindDef = effectiveSpaceKinds.find((k) => k.value === room.space_kind)}
                                    {#if kindDef}
                                        <Badge variant={kindDef.badge_variant} class={kindDef.badge_class}>{kindDef.short_label}</Badge>
                                    {:else}
                                        <Badge variant="secondary">Exam Room</Badge>
                                    {/if}
                                </Table.Cell>
                                <Table.Cell>{room.capacity} seats</Table.Cell>
                                {#if enable_seating_arrangements}
                                    <Table.Cell>
                                        {#if room.space_kind && room.space_kind !== 'room'}
                                            <span class="text-muted-foreground text-xs font-mono">Bypassed (Open/Deskless)</span>
                                        {:else}
                                            {room.active_seating_arrangements_count ?? 0} active ·
                                            {room.seating_arrangements_count ?? 0} total
                                        {/if}
                                    </Table.Cell>
                                {/if}
                                <Table.Cell
                                    ><Badge
                                        variant={room.is_active
                                            ? 'default'
                                            : 'secondary'}
                                        >{room.is_active
                                            ? 'Active'
                                            : 'Inactive'}</Badge
                                    ></Table.Cell
                                >
                                <Table.Cell class="w-px whitespace-nowrap text-right">
                                    <EntityRowActions
                                        primaryAction={{
                                            label: 'Edit',
                                            href: `/setup/rooms/${room.id}/edit`,
                                            icon: Pencil,
                                        }}
                                        overflowItems={[
                                            {
                                                label: 'View preview',
                                                href: `/setup/rooms/${room.id}`,
                                                icon: Eye,
                                            },
                                            {
                                                label: room.is_active ? 'Deactivate' : 'Activate',
                                                onclick: () => toggleRoom(room),
                                            },
                                        ]}
                                    />
                                </Table.Cell>
                            </Table.Row>{/each}</Table.Body
                    >
                </Table.Root>
            </div>
            <ul class="grid grid-cols-1 gap-3 md:hidden" role="list">
                {#each rooms.data as room (room.id)}<li
                        class="border-border bg-card min-w-0 rounded-xl border p-4"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <Link href={`/setup/rooms/${room.id}`} class="hover:underline">
                                    <h2 class="font-semibold text-foreground">{room.name}</h2>
                                </Link>
                                <p
                                    class="text-muted-foreground font-mono text-xs"
                                >
                                    {room.code}
                                </p>
                            </div>
                            <Badge
                                variant={room.is_active
                                    ? 'default'
                                    : 'secondary'}
                                >{room.is_active ? 'Active' : 'Inactive'}</Badge
                            >
                        </div>
                        <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <dt class="text-muted-foreground">Capacity</dt>
                                <dd class="font-medium">{room.capacity} seats</dd>
                            </div>
                            {#if enable_seating_arrangements}
                                <div>
                                    <dt class="text-muted-foreground">Arrangements</dt>
                                    <dd class="font-medium">
                                        {room.active_seating_arrangements_count ?? 0} active
                                    </dd>
                                </div>
                            {/if}
                        </dl>
                        <div class="mt-4 flex items-center gap-2">
                            <Link
                                href={`/setup/rooms/${room.id}/edit`}
                                class="flex-1"
                            >
                                <Button variant="outline" class="min-h-11 w-full gap-1.5 font-semibold">
                                    <Pencil class="h-4 w-4" />Edit
                                </Button>
                            </Link>
                            <Link
                                href={`/setup/rooms/${room.id}`}
                                class="flex-1"
                            >
                                <Button variant="outline" class="min-h-11 w-full gap-1.5 font-semibold">
                                    <Eye class="h-4 w-4" />View
                                </Button>
                            </Link>
                        </div>
                    </li>{/each}
            </ul>
        {/if}
        <SimplePagination data={rooms} />
    </div>
</StaffLayout>
