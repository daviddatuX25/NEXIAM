<script>
    import { formatDateTime } from '@/lib/date-utils.js';
    import { onMount } from 'svelte';
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
    import HeaderActions from '@/Components/kit/HeaderActions.svelte';
    import SwitchableListView from '@/Components/kit/SwitchableListView.svelte';
    import ModeBanner from '@/Components/kit/ModeBanner.svelte';
    import FilterToolbar from '@/Components/kit/FilterToolbar.svelte';
    import EntityRowActions from '@/Components/kit/EntityRowActions.svelte';
    import SearchSuggestionDropdown from '@/Components/kit/SearchSuggestionDropdown.svelte';
    import { Badge } from '@/Components/ui/badge';
    import { Button } from '@/Components/ui/button';
    import * as Table from '@/Components/ui/table';
    import { Link, router, usePage } from '@inertiajs/svelte';
    import {
        Layers,
        ScanLine,
        FileSpreadsheet,
        ChevronDown,
    } from 'lucide-svelte';
    import ScoringSetupNavigation from '@/Components/Assessment/ScoringSetupNavigation.svelte';
    import ContextualImportModal from '@/Components/Ingestion/ContextualImportModal.svelte';

    let {
        page_state = 'ready',
        degraded = false,
        sessions = [],
        aptitude_areas_count = 0,
        score_import_targets = [],
        import_kit_versions = [],
        import_norm_versions = [],
        cycles = [],
        rooms = [],
        can_manage_catalog = false,
        setup_links = null,
        can_import_scores = false,
        scanner_url = null,
        sort_options = [
            { value: 'starts_at', label: 'Exam date' },
            { value: 'code', label: 'Session Code' },
            { value: 'grading_status', label: 'Status' },
        ],
        filters = {},
        active_academic_cycle = null,
        summary = {},
    } = $props();
    let selectedStatus = $state(String(filters?.status ?? '').trim() || 'active');
    let selectedCycleId = $state(String(filters?.academic_cycle_id ?? ''));
    let selectedRoomId = $state(String(filters?.room_id ?? ''));
    let currentSort = $state(String(filters?.sort ?? 'starts_at'));
    let currentDir = $state(String(filters?.dir ?? 'desc'));
    const activeFilterCount = $derived(
        Number(selectedStatus !== 'active') +
            Number(Boolean(selectedCycleId)) +
            Number(Boolean(selectedRoomId)),
    );
    const hasActiveFilters = $derived(activeFilterCount > 0);
    const page = usePage();
    const inProgressCount = $derived(
        Number(summary?.total_in_progress ?? (sessions ?? []).filter((row) => row.grading_status != null && row.grading_status !== 'finalized').length),
    );
    const finalizedCount = $derived(
        Number(summary?.total_finalized ?? (sessions ?? []).filter((row) => row.grading_status === 'finalized').length),
    );

    const gradingContext = $derived.by(() => {
        const entityKey = 'grading-hub';
        const repairRequiredCount = Number(summary?.total_repair_required ?? (sessions ?? []).filter((row) => row.grading_status == null).length);
        const safetyKey = `${page_state}:${aptitude_areas_count}:${degraded ? 'deg' : 'norm'}:${repairRequiredCount}:${inProgressCount}:${finalizedCount}`;

        return {
            entityKey,
            safetyKey,
            freshness: 'current',
            pageState: page_state,
            sessionsCount: sessions?.length ?? 0,
            aptitudeAreasCount: aptitude_areas_count,
            isDegraded: Boolean(degraded),
            inProgressCount,
            repairRequiredCount,
            canImportScores: Boolean(can_import_scores),
        };
    });
    let viewMode = $state('responsive');
    let importModalOpen = $state(false);
    let searchInput = $state(null);
    let suggestionDropdown = $state(null);
    let draftSearch = $state(String(filters?.search ?? '').trim());
    let committedSearch = $state(String(filters?.search ?? '').trim());
    let pendingSearch = $state(null);
    let draftRevision = $state(0);
    let filterError = $state('');
    let activeSearchVisit = null;
    let observedServerSearch = String(filters?.search ?? '').trim();
    let skipSearchSubmit = false;
    const normalizedDraftSearch = $derived(draftSearch.trim());
    const searchIsDirty = $derived(normalizedDraftSearch !== committedSearch);
    const hasSearchToClear = $derived(
        Boolean(draftSearch || committedSearch || pendingSearch),
    );
    const suggestionContextKey = $derived(
        `${page.props.auth?.user?.id ?? 'anonymous'}:${page.props.practice_workspace?.id ?? 'operational'}:${selectedStatus}`,
    );
    $effect(() => {
        const serverSearch = String(filters?.search ?? '').trim();
        if (serverSearch === observedServerSearch) {
            return;
        }

        observedServerSearch = serverSearch;
        if (activeSearchVisit === null) {
            committedSearch = serverSearch;
            draftSearch = serverSearch;
            draftRevision += 1;
            filterError = '';
        }
    });

    onMount(() => {
        let historyNavigationPending = false;
        const markHistoryNavigation = () => {
            historyNavigationPending = true;
        };
        const handleNavigation = (event) => {
            const restoredPage = event.detail.page;
            const isHistoryNavigation = historyNavigationPending;
            historyNavigationPending = false;
            const restoredSearch = String(
                restoredPage?.props?.filters?.search ?? '',
            ).trim();

            if (
                !isHistoryNavigation &&
                (activeSearchVisit !== null ||
                    restoredSearch === committedSearch)
            ) {
                return;
            }

            activeSearchVisit?.cancelToken?.cancel();
            activeSearchVisit = null;
            pendingSearch = null;
            observedServerSearch = restoredSearch;
            selectedStatus = String(restoredPage?.props?.filters?.status ?? '').trim() || 'active';
            committedSearch = restoredSearch;
            draftSearch = restoredSearch;
            draftRevision += 1;
            filterError = '';
            suggestionDropdown?.dismiss();
        };

        window.addEventListener('popstate', markHistoryNavigation);
        const removeNavigateListener = router.on('navigate', handleNavigation);

        return () => {
            window.removeEventListener('popstate', markHistoryNavigation);
            removeNavigateListener();
        };
    });

    function statusVariant(status) {
        return status === 'finalized' ? 'success' : 'warning';
    }

    function statusLabel(status) {
        if (status === 'active') return 'Needs scoring';
        if (status === 'all') return 'All sessions';
        if (status == null || status === 'repair_required') return 'Repair required';
        if (status === 'finalized') return 'Finalized';
        if (status === 'review') return 'In review';
        return 'In progress';
    }
    
    function handleSearchInput(eventOrValue) {
        draftSearch =
            typeof eventOrValue === 'string'
                ? eventOrValue
                : eventOrValue?.currentTarget?.value ?? '';
        draftRevision += 1;
        filterError = '';
        suggestionDropdown?.handleInput(draftSearch);
    }

    function handleSearchFocus() {
        suggestionDropdown?.handleFocus();
        if (searchIsDirty) {
            suggestionDropdown?.handleInput(draftSearch);
        }
    }

    function handleSearchBlur(event) {
        suggestionDropdown?.handleBlur(event);
    }

    function handleSearchKeydown(event) {
        if (event.key === 'Enter') {
            if (event.isComposing || event.keyCode === 229) {
                skipSearchSubmit = true;
                return;
            }

            const suggestionWillActivate =
                suggestionDropdown?.isExpanded() &&
                suggestionDropdown?.activeDescendantId();

            if (suggestionWillActivate) {
                skipSearchSubmit = true;
                suggestionDropdown?.handleKeydown(event);
            }
            return;
        }

        suggestionDropdown?.handleKeydown(event);
    }
    function handleSortChange({ sort, dir }) {
        currentSort = sort;
        currentDir = dir;
        commitSearch(draftSearch, { sort, dir });
    }


    function commitSearch(value, overrides = {}) {
        const submittedSearch = String(value ?? '').trim();
        const previousRequest = activeSearchVisit;
        const request = {
            cancelToken: null,
            draftRevision,
        };
        activeSearchVisit = request;
        pendingSearch = submittedSearch;
        filterError = '';
        suggestionDropdown?.dismiss();
        previousRequest?.cancelToken?.cancel();

        const sortParam = overrides.sort !== undefined ? overrides.sort : currentSort;
        const dirParam = overrides.dir !== undefined ? overrides.dir : currentDir;

        router.get(
            '/scoring',
            {
                search: submittedSearch || undefined,
                ...(selectedStatus ? { status: selectedStatus } : {}),
                ...(selectedCycleId ? { academic_cycle_id: selectedCycleId } : {}),
                ...(selectedRoomId ? { room_id: selectedRoomId } : {}),
                ...(sortParam ? { sort: sortParam } : {}),
                ...(dirParam ? { dir: dirParam } : {}),
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onCancelToken: (cancelToken) => {
                    request.cancelToken = cancelToken;
                },
                onSuccess: (responsePage) => {
                    if (activeSearchVisit !== request) {
                        return;
                    }

                    const acceptedSearch = String(
                        responsePage.props?.filters?.search ?? '',
                    ).trim();
                    observedServerSearch = acceptedSearch;
                    committedSearch = acceptedSearch;
                    if (draftRevision === request.draftRevision) {
                        draftSearch = acceptedSearch;
                    }
                    filterError = '';
                },
                onError: () => {
                    if (activeSearchVisit === request) {
                        filterError = 'Search could not be applied. Try again.';
                    }
                },
                onHttpException: () => {
                    if (activeSearchVisit === request) {
                        filterError = 'Search could not be applied. Try again.';
                        return false;
                    }
                },
                onNetworkError: () => {
                    if (activeSearchVisit === request) {
                        filterError = 'Search could not be applied. Try again.';
                        return false;
                    }
                },
                onCancel: () => {
                    if (activeSearchVisit === request) {
                        filterError = 'Search was cancelled. Try again.';
                    }
                },
                onFinish: () => {
                    if (activeSearchVisit === request) {
                        activeSearchVisit = null;
                        pendingSearch = null;
                    }
                },
            },
        );
    }

    function handleSearchActivation(action) {
        if (action.type === 'navigate') {
            router.visit(action.url);
            return;
        }

        if (action.type === 'filter') {
            commitSearch(action.query);
        }
    }

    function clearSearch() {
        const hasAppliedOrPendingSearch = Boolean(
            committedSearch || pendingSearch,
        );
        draftSearch = '';
        draftRevision += 1;
        suggestionDropdown?.dismiss();
        if (hasAppliedOrPendingSearch) {
            commitSearch('');
        } else {
            activeSearchVisit?.cancelToken?.cancel();
            activeSearchVisit = null;
            pendingSearch = null;
        }
        searchInput?.focus();
    }

    function scoringPercentage(scored, total) {
        if (!total || total <= 0) return 0;
        return Math.min(100, Math.round(((scored ?? 0) / total) * 100));
    }

    
</script>

<svelte:head><title>Scoring</title></svelte:head>

<StaffLayout breadcrumbs={[{ label: 'Scoring' }]}>
    <div class="min-w-0 space-y-6 p-4 sm:p-6">

        {#if page_state === 'denied'}
            <p class="text-muted-foreground text-sm">
                You do not have permission to access scoring.
            </p>
        {:else}
            {#if aptitude_areas_count === 0}
                <div class="rounded-xl border border-amber-500/40 bg-amber-500/5 p-6">
                    <div class="flex items-center gap-2 text-amber-700 dark:text-amber-400">
                        <Layers class="size-5" />
                        <h2 class="text-lg font-semibold">No aptitude areas configured</h2>
                    </div>
                    <p class="text-muted-foreground mt-1 text-sm">
                        Configure aptitude areas to enable scoring. Existing sessions remain available below.
                    </p>
                </div>
            {/if}
            <PageHeaderBar
                title="Scoring"
                description="Manage scoring sessions, enter examinee raw scores, and review converted aptitude results."
                tourKey="assessment-grading-dashboard"
                context={gradingContext}
            >
                {#snippet actions()}
                    <HeaderActions>
                        {#snippet primaryAction()}
                            {#if scanner_url}
                                <Link href={scanner_url}>
                                    <Button class="min-h-10 w-full gap-2 font-medium sm:w-auto">
                                        <ScanLine class="size-4" />
                                        <span>Scan any paper</span>
                                    </Button>
                                </Link>
                            {/if}
                        {/snippet}
                        {#snippet secondaryActions()}
                            <ScoringSetupNavigation {can_manage_catalog} {setup_links} />
                            {#if can_import_scores}
                                <Button
                                    type="button"
                                    variant="outline"
                                    class="min-h-10 w-full gap-2 sm:w-auto"
                                    data-tour="grading-import-scores"
                                    onclick={() => (importModalOpen = true)}
                                >
                                    <FileSpreadsheet class="size-4" />
                                    <span>Import CSV scores</span>
                                </Button>
                            {/if}
                        {/snippet}
                    </HeaderActions>
                {/snippet}
            </PageHeaderBar>
            <div class="space-y-3 pb-2">
                <FilterToolbar
                    class="sticky top-0 z-20 backdrop-blur-md bg-background/80 py-2 space-y-3"
                    mobileFilterCount={activeFilterCount}
                    bind:search={draftSearch}
                    searchLabel="Search"
                    inputId="grading-search"
                    searchPlaceholder="Search session code, room, or examinee…"
                    onSearchInput={handleSearchInput}
                    onSearchSubmit={(val) => {
                        if (skipSearchSubmit) {
                            skipSearchSubmit = false;
                            return;
                        }
                        commitSearch(val);
                    }}
                    onSearchClear={clearSearch}
                    onkeydown={handleSearchKeydown}
                    sortOptions={sort_options}
                    sortValue={currentSort}
                    sortDir={currentDir}
                    onSortChange={handleSortChange}
                    comboboxProps={{
                        role: 'combobox',
                        'aria-label': 'Search grading sessions and examinees',
                        'aria-autocomplete': 'list',
                        'aria-expanded': suggestionDropdown?.isExpanded() ?? false,
                        'aria-controls': suggestionDropdown?.listboxIdentifier(),
                        'aria-activedescendant': suggestionDropdown?.activeDescendantId(),
                    }}
                >
                    {#snippet searchSuggestions()}
                        <SearchSuggestionDropdown
                            bind:this={suggestionDropdown}
                            search={draftSearch}
                            academicCycleId={active_academic_cycle?.id}
                            domainContext="grading"
                            contextKey={suggestionContextKey}
                            status={selectedStatus}
                            inputRef={searchInput}
                            onactivate={handleSearchActivation}
                        />
                    {/snippet}
                    {#snippet actions()}
                        {#if hasSearchToClear}
                            <Button
                                type="button"
                                variant="outline"
                                class="h-11 min-h-[44px]"
                                onclick={clearSearch}
                            >
                                Clear search
                            </Button>
                        {/if}
                    {/snippet}
                    {#snippet desktopFilters()}
                        <div class="flex flex-wrap items-end gap-3">
                            <div class="relative grid gap-1">
                                <label for="grading-status-filter" class="text-xs font-medium">Status</label>
                                <select id="grading-status-filter" class="h-9 min-w-0 flex-1 truncate appearance-none rounded-md border border-input bg-background px-3 pr-8 text-xs text-foreground shadow-xs dark:bg-input/30" bind:value={selectedStatus} onchange={() => commitSearch(draftSearch)}>
                                    <option value="active">Needs scoring</option>
                                    <option value="all">All sessions</option>
                                    <option value="open">In progress</option>
                                    <option value="review">In review</option>
                                    <option value="finalized">Finalized</option>
                                    <option value="repair_required">Repair required</option>
                                </select>
                                <ChevronDown class="absolute right-2 top-8 size-4 shrink-0 ml-1.5" aria-hidden="true" />
                            </div>
                            {#if cycles.length > 0}
                                <div class="relative grid gap-1">
                                    <label for="grading-cycle-filter" class="text-xs font-medium">Academic Year &amp; Semester</label>
                                    <select id="grading-cycle-filter" class="h-9 min-w-0 flex-1 truncate appearance-none rounded-md border border-input bg-background px-3 pr-8 text-xs text-foreground shadow-xs dark:bg-input/30" bind:value={selectedCycleId} onchange={() => commitSearch(draftSearch)}>
                                        <option value="">All academic years &amp; semesters</option>
                                        {#each cycles as cycle (cycle.id)}
                                            <option value={String(cycle.id)}>{cycle.label}</option>
                                        {/each}
                                    </select>
                                    <ChevronDown class="absolute right-2 top-8 size-4 shrink-0 ml-1.5" aria-hidden="true" />
                                </div>
                            {/if}
                            {#if rooms.length > 0}
                                <div class="relative grid gap-1">
                                    <label for="grading-room-filter" class="text-xs font-medium">Room</label>
                                    <select id="grading-room-filter" class="h-9 min-w-0 flex-1 truncate appearance-none rounded-md border border-input bg-background px-3 pr-8 text-xs text-foreground shadow-xs dark:bg-input/30" bind:value={selectedRoomId} onchange={() => commitSearch(draftSearch)}>
                                        <option value="">All rooms</option>
                                        {#each rooms as room (room.id)}
                                            <option value={String(room.id)}>{room.name}</option>
                                        {/each}
                                    </select>
                                    <ChevronDown class="absolute right-2 top-8 size-4 shrink-0 ml-1.5" aria-hidden="true" />
                                </div>
                            {/if}
                            {#if hasActiveFilters}
                                <Button type="button" variant="outline" class="h-9" onclick={() => {
                                    selectedStatus = 'active';
                                    selectedCycleId = '';
                                    selectedRoomId = '';
                                    commitSearch(draftSearch);
                                }}>Clear filters</Button>
                            {/if}
                        </div>
                    {/snippet}
                    {#snippet mobileFilters()}
                        <div class="space-y-3">
                            <div class="relative grid gap-1">
                                <label for="grading-status-filter-mobile" class="text-xs font-medium">Status</label>
                                <select id="grading-status-filter-mobile" class="h-9 min-w-0 w-full flex-1 truncate appearance-none rounded-md border border-input bg-background px-3 pr-8 text-xs text-foreground shadow-xs dark:bg-input/30" bind:value={selectedStatus} onchange={() => commitSearch(draftSearch)}>
                                    <option value="active">Needs scoring</option>
                                    <option value="all">All sessions</option>
                                    <option value="open">In progress</option>
                                    <option value="review">In review</option>
                                    <option value="finalized">Finalized</option>
                                    <option value="repair_required">Repair required</option>
                                </select>
                                <ChevronDown class="absolute right-2 top-8 size-4 shrink-0 ml-1.5" aria-hidden="true" />
                            </div>
                            {#if cycles.length > 0}
                                <div class="relative grid gap-1">
                                    <label for="grading-cycle-filter-mobile" class="text-xs font-medium">Academic Year &amp; Semester</label>
                                    <select id="grading-cycle-filter-mobile" class="h-9 min-w-0 w-full flex-1 truncate appearance-none rounded-md border border-input bg-background px-3 pr-8 text-xs text-foreground shadow-xs dark:bg-input/30" bind:value={selectedCycleId} onchange={() => commitSearch(draftSearch)}>
                                        <option value="">All academic years &amp; semesters</option>
                                        {#each cycles as cycle (cycle.id)}
                                            <option value={String(cycle.id)}>{cycle.label}</option>
                                        {/each}
                                    </select>
                                    <ChevronDown class="absolute right-2 top-8 size-4 shrink-0 ml-1.5" aria-hidden="true" />
                                </div>
                            {/if}
                            {#if rooms.length > 0}
                                <div class="relative grid gap-1">
                                    <label for="grading-room-filter-mobile" class="text-xs font-medium">Room</label>
                                    <select id="grading-room-filter-mobile" class="h-9 min-w-0 w-full flex-1 truncate appearance-none rounded-md border border-input bg-background px-3 pr-8 text-xs text-foreground shadow-xs dark:bg-input/30" bind:value={selectedRoomId} onchange={() => commitSearch(draftSearch)}>
                                        <option value="">All rooms</option>
                                        {#each rooms as room (room.id)}
                                            <option value={String(room.id)}>{room.name}</option>
                                        {/each}
                                    </select>
                                    <ChevronDown class="absolute right-2 top-8 size-4 shrink-0 ml-1.5" aria-hidden="true" />
                                </div>
                            {/if}
                            {#if hasActiveFilters}
                                <Button type="button" variant="outline" class="h-9 w-full" onclick={() => {
                                    selectedStatus = 'active';
                                    selectedCycleId = '';
                                    selectedRoomId = '';
                                    commitSearch(draftSearch);
                                }}>Clear filters</Button>
                            {/if}
                        </div>
                    {/snippet}
                </FilterToolbar>
                {#if searchIsDirty || pendingSearch !== null || filterError}
                    <div class="flex items-center gap-2 text-xs">
                        {#if searchIsDirty}
                            <span role="status" class="text-muted-foreground text-[10px]">Press Enter to filter.</span>
                        {/if}
                        {#if pendingSearch !== null}
                            <span role="status" class="text-muted-foreground">Applying search…</span>
                        {/if}
                        {#if filterError}
                            <span role="alert" class="text-destructive font-medium">{filterError}</span>
                        {/if}
                    </div>
                {/if}
            </div>

            <div data-tour="grading-sessions-list">
                <SwitchableListView bind:viewMode overflow="auto">
                    {#snippet table()}
                        <Table.Root class="w-full min-w-205 text-sm">
                            <Table.Header class="bg-muted/40 border-b border-border">
                                <Table.Row class="border-b border-border/40 hover:bg-muted/30 transition-colors">
                                    <Table.Head
                                        class="px-4 py-3 text-left font-semibold"
                                        >Session</Table.Head
                                    >
                                    <Table.Head
                                        class="px-4 py-3 text-left font-semibold"
                                        >Date &amp; Time</Table.Head
                                    >
                                    <Table.Head
                                        class="px-4 py-3 text-right font-semibold"
                                        >Scoring Progress</Table.Head
                                    >
                                    <Table.Head
                                        class="px-4 py-3 text-center font-semibold"
                                        >Status</Table.Head
                                    >
                                    <Table.Head
                                        class="sr-only px-4 py-3 pr-6 text-right font-semibold"
                                        >Action</Table.Head
                                    >
                                </Table.Row>
                            </Table.Header>
                            <Table.Body>
                                {#each sessions as row (row.exam_session_id)}
                                    <Table.Row class="border-b border-border/40 hover:bg-muted/30 transition-colors">
                                        <Table.Cell class="px-4 py-3 text-left font-mono font-medium">
                                            <span class="text-foreground font-bold">{row.exam_code}</span>
                                            <span class="text-muted-foreground block font-sans text-xs">{row.room_name}</span>
                                        </Table.Cell>
                                        <Table.Cell class="text-muted-foreground px-4 py-3 text-left font-mono text-xs">
                                            {formatDateTime(row.starts_at)}
                                        </Table.Cell>
                                        <Table.Cell class="px-4 py-3 text-right">
                                            {row.applicants_scored ?? 0} / {row.applicants_total ?? 0} Scored
                                        </Table.Cell>
                                        <Table.Cell class="px-4 py-3 text-center">
                                            <Badge variant={statusVariant(row.grading_status)} class="font-mono text-[10px] font-bold">
                                                {statusLabel(row.grading_status)}
                                            </Badge>
                                        </Table.Cell>
                                        <Table.Cell class="px-4 py-3 pr-6 text-right">
                                            <EntityRowActions
                                                primaryAction={{
                                                    label: row.grading_status == null ? 'Repair required' : row.grading_status === 'finalized' ? 'Results' : 'Scores',
                                                    href: row.score_url,
                                                    variant: 'outline',
                                                }}
                                                overflowItems={[]}
                                                dense={true}
                                            />
                                        </Table.Cell>
                                    </Table.Row>
                                {:else}
                                    <Table.Row class="border-b border-border/40 hover:bg-muted/30 transition-colors">
                                        <Table.Cell colspan={5} class="text-muted-foreground px-4 py-12 text-center">
                                            No completed exam or Direct Assessment sessions match these filters.
                                        </Table.Cell>
                                    </Table.Row>
                                {/each}
                            </Table.Body>
                        </Table.Root>
                    {/snippet}

                    {#snippet cards()}
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {#each sessions as row (row.exam_session_id)}
                                <div class="craft-card-depth flex min-w-0 flex-col justify-between gap-3.5 p-5">
                                    <div class="min-w-0 space-y-2.5">
                                        <div class="flex items-start justify-between gap-2 border-b border-border/50 pb-2.5">
                                            <div class="min-w-0">
                                                <p class="truncate font-mono text-base font-bold tracking-tight text-foreground">{row.exam_code}</p>
                                                <p class="mt-0.5 font-mono text-xs text-muted-foreground">{formatDateTime(row.starts_at)}</p>
                                            </div>
                                            <Badge variant={statusVariant(row.grading_status)} class="shrink-0 whitespace-nowrap font-mono text-[10px] font-bold">
                                                {statusLabel(row.grading_status)}
                                            </Badge>
                                        </div>
                                        <p class="truncate text-xs font-semibold text-foreground">Room: {row.room_name ?? '—'}</p>
                                        <p class="font-mono text-xs text-muted-foreground">
                                            {row.applicants_scored ?? 0} / {row.applicants_total ?? 0} scored
                                        </p>
                                    </div>
                                    <EntityRowActions
                                        primaryAction={{
                                            label: row.grading_status == null ? 'Repair required' : row.grading_status === 'finalized' ? 'View Results' : 'Input Scores',
                                            href: row.score_url,
                                            variant: 'outline',
                                        }}
                                        overflowItems={[]}
                                    />
                                </div>
                            {:else}
                                <div class="border-border bg-muted/20 text-muted-foreground col-span-full rounded-[var(--radius,0.5rem)] border border-dashed py-12 text-center">
                                    No completed exam or Direct Assessment sessions match these filters.
                                </div>
                            {/each}
                        </div>
                    {/snippet}
                </SwitchableListView>
            </div>
        {/if}
    </div>


    {#if can_import_scores}
        <ContextualImportModal
            bind:open={importModalOpen}
            importType="scores_only"
            academicCycle={active_academic_cycle}
            scoreImportTargets={score_import_targets}
            kitVersions={import_kit_versions}
            normVersions={import_norm_versions}
        />
    {/if}
</StaffLayout>
