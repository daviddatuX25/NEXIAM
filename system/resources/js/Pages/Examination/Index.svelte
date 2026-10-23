<script>
    /**
     * Exam Scheduling & Session Management Hub (Domain: Examinations & Scheduling)
     *
     * Architectural Role:
     * Operational hub for scheduling institutional entrance examinations:
     * 1. Session Capacity & Allocation: Tracks max seats, registered applicants, and
     *    live walk-in accommodation headroom per examination room.
     * 2. Intelligent Auto-Distribution: Balances unassigned applicants across testing
     *    slots according to program priority criteria.
     * 3. Multi-Session Bulk Actions: Batch publish, reservation opening/locking, and
     *    cancellation with mandatory reason audit trail.
     * 4. Lifecycle Transition: Passes published sessions to Exam Monitoring.
     *
     * Persona & Guidance:
     * - Employs the 'staff-copilot' persona (Xiam) to guide testing coordinators through
     *   capacity allocation and bulk publication procedures.
     */
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
    import HeaderActions from '@/Components/kit/HeaderActions.svelte';
    import BulkActionBar from '@/Components/kit/BulkActionBar.svelte';
    import SortDropdown from '@/Components/kit/SortDropdown.svelte';
    import EntityRowActions from '@/Components/kit/EntityRowActions.svelte';
    import ActiveFilterChips from '@/Components/kit/ActiveFilterChips.svelte';
    
    import { Checkbox } from '@/Components/ui/checkbox';
    import SwitchableListView from '@/Components/kit/SwitchableListView.svelte';
    import OperationalCard from '@/Components/kit/OperationalCard.svelte';
    import SearchSuggestionDropdown from '@/Components/kit/SearchSuggestionDropdown.svelte';
    import { Badge } from '@/Components/ui/badge';
    import { Button } from '@/Components/ui/button';
    import { Input } from '@/Components/ui/input';
    import * as Select from '@/Components/ui/select';
    import * as Table from '@/Components/ui/table';
    import SortableTableHead from '@/Components/kit/SortableTableHead.svelte';
    import { Link, router, usePage } from '@inertiajs/svelte';
    import { onDestroy, onMount } from 'svelte';
    import {
        Eye,
        Pencil,
        Plus,
        Search,
        X,
        Calendar,
        Clock,
        Building2,
        Users,
        ClipboardList,
        EllipsisVertical,
        ChevronDown,
        School,
    } from 'lucide-svelte';
    import ActionDropdown from '@/Components/kit/ActionDropdown.svelte';
    import CohortAutoDistributeModal from '@/Components/scheduling/CohortAutoDistributeModal.svelte';
    import { Sparkles } from 'lucide-svelte';
    import {
        formatDate,
        formatTimeRange,
        formatHumanSchedule,
    } from '@/lib/date-utils.js';
    import { bookingUnlocked } from '@/lib/booking-visibility';
    let {
        page_state = 'ready',
        sessions = [],
        filters = {},
        statuses = [],
        cycles = [],
        distribution = {},
        suggestion_cycle_id = null,
        selected_cycle_id = null,
        sort_options = [],
        waitlist_counts = {},
    } = $props();


    let filterSort = $state(filters.sort ?? 'starts_at');
    let filterDir = $state(filters.dir ?? 'desc');
    let draftSearch = $state(filters.search ?? '');
    let committedSearch = $state(filters.search ?? '');
    let filterStatus = $state(String(filters.status ?? '').trim() || 'active');
    let filterCycleId = $state(
        selected_cycle_id ? String(selected_cycle_id) : '',
    );
    const waitlistCount = $derived(Number(waitlist_counts?.ready ?? 0));
    const waitlistUrl = $derived(
        typeof route === 'function'
            ? route('exams.sessions.waitlist', {
                academic_cycle_id: filterCycleId || undefined,
            })
            : (filterCycleId ? `/exams/sessions/waitlist?academic_cycle_id=${filterCycleId}` : '/exams/sessions/waitlist')
    );
    let filterRoomId = $state('');
    let mobileFiltersOpen = $state(false);
    let localSort = $state({
        key: filters.sort === 'code' ? 'code' : filters.sort === 'status' ? 'status' : 'date',
        dir: filters.dir ?? 'desc',
    });
    const currentSessions = $derived(page.props.sessions ?? sessions ?? []);
    const rooms = $derived.by(() => {
        const uniqueRooms = new Map();
        for (const session of currentSessions ?? []) {
            if (session.room?.id != null) {
                uniqueRooms.set(String(session.room.id), session.room);
            }
        }
        return [...uniqueRooms.values()];
    });
    const visibleSessions = $derived.by(() => {
        let rows = (currentSessions ?? []).filter((session) =>
            !filterRoomId || String(session.room?.id ?? '') === filterRoomId,
        );
        if (localSort.key) {
            const { key, dir } = localSort;
            rows = [...rows].sort((left, right) => {
                const a = key === 'room'
                    ? left.room?.name ?? left.room?.code ?? ''
                    : key === 'capacity'
                      ? occupancyPercent(left)
                      : key === 'assignment'
                        ? assignmentModeLabel(left)
                        : key === 'date'
                          ? left.starts_at ?? ''
                          : key === 'code'
                            ? left.code ?? ''
                            : left.status ?? '';
                const b = key === 'room'
                    ? right.room?.name ?? right.room?.code ?? ''
                    : key === 'capacity'
                      ? occupancyPercent(right)
                      : key === 'assignment'
                        ? assignmentModeLabel(right)
                        : key === 'date'
                          ? right.starts_at ?? ''
                          : key === 'code'
                            ? right.code ?? ''
                            : right.status ?? '';
                return (typeof a === 'number' && typeof b === 'number'
                    ? a - b
                    : String(a).localeCompare(String(b))) * (dir === 'asc' ? 1 : -1);
            });
        }
        return rows;
    });
    const activeFilterChips = $derived([
        committedSearch ? { id: 'search', label: `Search: ${committedSearch}` } : null,
        filterCycleId ? { id: 'cycle', label: `Cycle: ${cycles.find((cycle) => String(cycle.id) === filterCycleId)?.label ?? filterCycleId}` } : null,
        filterRoomId ? { id: 'room', label: `Room: ${rooms.find((room) => String(room.id) === filterRoomId)?.name ?? rooms.find((room) => String(room.id) === filterRoomId)?.code ?? 'Selected room'}` } : null,
        filterStatus !== 'active' ? { id: 'status', label: `State: ${statusLabel(filterStatus)}` } : null,
        filterSort !== 'starts_at' || filterDir !== 'desc' ? { id: 'sort', label: `Sort: ${sort_options.find((option) => option.value === filterSort)?.label ?? filterSort} (${filterDir === 'asc' ? 'Asc' : 'Desc'})` } : null,
    ].filter(Boolean));
    const activeCount = $derived(activeFilterChips.length);
    const activeCycleLabel = $derived.by(() => {
        const targetId =
            filterCycleId ||
            selected_cycle_id ||
            distribution?.active_cycle_id;
        if (targetId) {
            const found = cycles.find(
                (c) => String(c.value ?? c.id) === String(targetId),
            );
            if (found?.label) return found.label;
        }
        const active = cycles.find((c) => c.is_active) ?? cycles[0];
        return active?.label ?? 'the active academic cycle';
    });
    const hasNarrowingFilters = $derived(
        Boolean(
            committedSearch.trim() ||
                filterRoomId ||
                (filterStatus !== 'active' && filterStatus !== 'all'),
        ),
    );

    function sortColumn(key) {
        const dir = localSort.key === key && localSort.dir === 'asc' ? 'desc' : 'asc';
        localSort = { key, dir };
        if (key === 'code' || key === 'date' || key === 'status') {
            filterSort = key === 'code' ? 'code' : key === 'date' ? 'starts_at' : 'status';
            filterDir = dir;
            visitFilters(committedSearch, 'sort');
        }
    }

    function columnSortDirection(key) {
        return localSort.key === key
            ? localSort.dir === 'asc' ? 'ascending' : 'descending'
            : 'none';
    }

    function removeFilter(id) {
        if (id === 'search') {
            draftSearch = '';
            visitFilters('', 'clear');
        } else if (id === 'cycle') {
            filterCycleId = '';
            applyFilters();
        } else if (id === 'room') {
            filterRoomId = '';
        } else if (id === 'status') {
            filterStatus = 'active';
            applyFilters();
        } else if (id === 'sort') {
            filterSort = 'starts_at';
            filterDir = 'desc';
            localSort = { key: 'date', dir: 'desc' };
            visitFilters(committedSearch, 'sort');
        }
    }

    function clearAllFilters() {
        draftSearch = '';
        filterRoomId = '';
        filterStatus = 'active';
        filterCycleId = '';
        filterSort = 'starts_at';
        filterDir = 'desc';
        localSort = { key: 'date', dir: 'desc' };
        visitFilters('', 'filters');
    }



    function handleSortChange(payload) {
        filterSort = payload.sort;
        filterDir = payload.dir;
        localSort = {
            key: payload.sort === 'code' ? 'code' : payload.sort === 'status' ? 'status' : payload.sort === 'starts_at' ? 'date' : '',
            dir: payload.dir,
        };
        visitFilters(committedSearch, 'sort');
    }

    const page = usePage();
    let autoDistributeOpen = $state(false);

    let viewMode = $state('responsive');
    let suggestionDropdown = $state();
    let searchInput = $state(null);
    let draftRevision = $state(0);
    let pendingSearch = $state(null);
    let requestMessage = $state('');
    let searchInteractionActive = $state(false);
    let activeVisit = null;
    let lastKnownPageUrl = page.url;
    let historyNavigationPending = false;
    const isDraftDirty = $derived(
        draftSearch.trim() !== committedSearch.trim(),
    );
    const showClearSearch = $derived(
        Boolean(draftSearch || committedSearch || pendingSearch),
    );
    const cycleScopeConfirmed = $derived(
        String(filterCycleId ?? '') === String(selected_cycle_id ?? ''),
    );
    const suggestionCycleId = $derived(
        cycleScopeConfirmed ? suggestion_cycle_id || null : null,
    );
    const defaultCycleLabel = $derived.by(() => {
        const cycle = cycles.find(
            (cycle) => String(cycle.id) === String(distribution.active_cycle_id),
        );
        return cycle ? `Active academic year · ${cycle.label}` : 'All academic years';
    });
    const schedulingContext = $derived(
        JSON.stringify([
            page.props.auth?.user?.id ?? null,
            page.props.practice_workspace ?? null,
            suggestionCycleId,
            filterCycleId,
            filterStatus,
        ]),
    );

    function handleSearchInput(event) {
        const value = event.currentTarget.value;
        draftSearch = value;
        draftRevision += 1;
        requestMessage = '';
        suggestionDropdown?.handleInput(value);
    }

    function handleSearchFocus() {
        suggestionDropdown?.handleFocus();
    }

    function handleSearchBlur(event) {
        suggestionDropdown?.handleBlur(event);
    }

    function handleSearchKeydown(event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            commitSearch(draftSearch, 'enter');
            return;
        }
        suggestionDropdown?.handleKeydown(event);
    }

    function handleSearchInteractionChange(isInteracting) {
        searchInteractionActive = isInteracting;
    }

    function cancelActiveVisit() {
        const visit = activeVisit;
        activeVisit = null;
        pendingSearch = null;
        visit?.cancelToken?.cancel();
    }

    function visitFilters(searchValue, source = 'filters') {
        const search = String(searchValue ?? '').trim();
        const request = {
            source,
            draftRevision,
            draftWasDirty: isDraftDirty,
            cancelToken: null,
        };
        const data = {
            search: search || undefined,
            status: filterStatus || undefined,
            academic_cycle_id: filterCycleId || undefined,
            sort: (filters.sort || filterSort !== 'starts_at') ? filterSort : undefined,
            dir: (filters.dir || filterDir !== 'desc') ? filterDir : undefined,
        };

        cancelActiveVisit();
        suggestionDropdown?.dismiss();
        requestMessage = '';
        activeVisit = request;
        pendingSearch = { search, source };

        router.visit('/exams/sessions', {
            method: 'get',
            data,
            preserveState: false,
            preserveScroll: true,
            replace: source !== 'filters',
            onCancelToken: (cancelToken) => {
                if (activeVisit === request) {
                    request.cancelToken = cancelToken;
                } else {
                    cancelToken.cancel();
                }
            },
            onSuccess: (responsePage) => {
                if (activeVisit !== request) {
                    return;
                }
                const acceptedFilters = responsePage?.props?.filters ?? {};
                committedSearch = String(acceptedFilters.search ?? '').trim();
                filterStatus = String(acceptedFilters.status ?? '').trim() || 'active';
                filterSort = acceptedFilters.sort ?? filterSort;
                filterDir = acceptedFilters.dir ?? filterDir;
                filterCycleId = responsePage?.props?.selected_cycle_id
                    ? String(responsePage.props.selected_cycle_id)
                    : '';
                if (
                    draftRevision === request.draftRevision &&
                    (request.source !== 'filters' || !request.draftWasDirty)
                ) {
                    draftSearch = committedSearch;
                }
                pendingSearch = null;
                requestMessage = '';
                lastKnownPageUrl = responsePage?.url ?? lastKnownPageUrl;
            },
            onError: () => {
                if (activeVisit === request) {
                    pendingSearch = null;
                    requestMessage =
                        'The filter was not applied. The previous results remain, and your search draft is unchanged.';
                }
            },
            onHttpException: () => {
                if (activeVisit === request) {
                    pendingSearch = null;
                    requestMessage =
                        'The filter could not be applied. The previous results remain, and your search draft is unchanged.';
                }
                return false;
            },
            onNetworkError: () => {
                if (activeVisit === request) {
                    pendingSearch = null;
                    requestMessage =
                        'The filter could not be applied because the request failed. The previous results remain, and your search draft is unchanged.';
                }
                return false;
            },
            onCancel: () => {
                if (activeVisit === request) {
                    pendingSearch = null;
                    requestMessage =
                        'The filter request was cancelled. The previous results remain, and your search draft is unchanged.';
                }
            },
            onFinish: () => {
                if (activeVisit === request) {
                    activeVisit = null;
                    pendingSearch = null;
                }
            },
        });
    }

    function applyFilters() {
        visitFilters(committedSearch, 'filters');
    }

    function commitSearch(query, source = 'enter') {
        const normalizedQuery = String(query ?? '').trim();
        if (source === 'session') {
            draftSearch = normalizedQuery;
            draftRevision += 1;
        }
        visitFilters(normalizedQuery, source);
    }

    function handleSuggestionActivation(action) {
        if (
            !cycleScopeConfirmed &&
            (action?.type === 'navigate' || action?.source === 'session')
        ) {
            suggestionDropdown?.dismiss();
            return;
        }

        if (action?.type === 'navigate' && action.url) {
            suggestionDropdown?.dismiss();
            cancelActiveVisit();
            router.visit(action.url, {
                preserveState: false,
                preserveScroll: false,
            });
            return;
        }

        if (action?.type === 'filter' && action.query) {
            commitSearch(action.query, action.source);
        }
    }

    function clearSearch() {
        const clearAlreadyPending = pendingSearch?.source === 'clear';
        const pendingQuery = pendingSearch?.search ?? '';
        const shouldCommitClear =
            !clearAlreadyPending &&
            Boolean(committedSearch.trim() || pendingQuery);

        draftSearch = '';
        draftRevision += 1;
        requestMessage = '';
        suggestionDropdown?.dismiss();

        if (shouldCommitClear) {
            visitFilters('', 'clear');
        }

        searchInput?.focus();
    }

    function acceptRestoredPage(restoredPage) {
        const restoredFilters = restoredPage?.props?.filters;
        if (!restoredFilters) {
            return;
        }

        cancelActiveVisit();
        suggestionDropdown?.dismiss();
        committedSearch = String(restoredFilters.search ?? '').trim();
        draftSearch = committedSearch;
        filterStatus = String(restoredFilters.status ?? '').trim() || 'active';
        filterSort = restoredFilters.sort ?? filterSort;
        filterDir = restoredFilters.dir ?? filterDir;
        filterCycleId = restoredPage?.props?.selected_cycle_id
            ? String(restoredPage.props.selected_cycle_id)
            : '';
        draftRevision += 1;
        requestMessage = '';
    }

    onMount(() => {
        const markHistoryNavigation = () => {
            historyNavigationPending = true;
        };
        window.addEventListener('popstate', markHistoryNavigation);
        const removeNavigateListener = router.on('navigate', (event) => {
            const restoredPage = event.detail.page;
            const isHistoryNavigation = historyNavigationPending;
            historyNavigationPending = false;
            const restoredSearch = String(
                restoredPage?.props?.filters?.search ?? '',
            ).trim();
            const restoredUrl = restoredPage?.url ?? '';
            const restoredFiltersChanged = restoredSearch !== committedSearch;

            if (activeVisit && !isHistoryNavigation) {
                return;
            }
            if (
                !isHistoryNavigation &&
                restoredUrl === lastKnownPageUrl &&
                !restoredFiltersChanged
            ) {
                return;
            }

            acceptRestoredPage(restoredPage);
            lastKnownPageUrl = restoredUrl;
        });

        return () => {
            window.removeEventListener('popstate', markHistoryNavigation);
            removeNavigateListener();
        };
    });

    onDestroy(cancelActiveVisit);
    let selectedSessionIds = $state([]);
    let cancelDialogOpen = $state(false);
    let cancelReason = $state('');
    let visibleSessionIds = $derived(visibleSessions.map((session) => session.id));
    let isAllVisibleSelected = $derived(
        visibleSessionIds.length > 0 &&
            visibleSessionIds.every((id) => selectedSessionIds.includes(id)),
    );
    let isSomeVisibleSelected = $derived(
        visibleSessionIds.some((id) => selectedSessionIds.includes(id)) &&
            !isAllVisibleSelected,
    );

    const draftCount = $derived(
        (sessions ?? []).filter((s) => s.status === 'draft').length,
    );
    const publishedCount = $derived(
        (sessions ?? []).filter((s) => s.status === 'published').length,
    );
    const openReservationCount = $derived(
        (sessions ?? []).filter(
            (s) => s.booking_mode === 'open_for_reservation',
        ).length,
    );
    const staffOnlyCount = $derived(
        (sessions ?? []).filter((s) => s.booking_mode === 'staff_only').length,
    );
    const totalCapacity = $derived(
        (sessions ?? []).reduce((acc, s) => acc + (s.capacity || 0), 0),
    );
    const totalAssigned = $derived(
        (sessions ?? []).reduce((acc, s) => acc + (s.assigned_count || 0), 0),
    );

    const sessionsContext = $derived.by(() => {
        const safetyKey = `${page_state}:${sessions?.length ?? 0}:${draftCount}:${publishedCount}:${selectedSessionIds.length}:${openReservationCount}`;

        return {
            entityKey: 'exam-sessions-hub',
            safetyKey,
            freshness: 'current',
            pageState: page_state,
            sessionsCount: sessions?.length ?? 0,
            draftCount,
            publishedCount,
            openReservationCount,
            staffOnlyCount,
            selectedSessionsCount: selectedSessionIds.length,
            totalCapacity,
            totalAssigned,
        };
    });
    function toggleSession(id) {
        selectedSessionIds = selectedSessionIds.includes(id)
            ? selectedSessionIds.filter((selectedId) => selectedId !== id)
            : [...selectedSessionIds, id];
    }

    function toggleAllVisible() {
        selectedSessionIds = isAllVisibleSelected
            ? selectedSessionIds.filter((id) => !visibleSessionIds.includes(id))
            : [...new Set([...selectedSessionIds, ...visibleSessionIds])];
    }

    function submitBulkAction(url, payload, onSuccess = () => {}) {
        router.post(url, payload, {
            preserveScroll: true,
            onSuccess: () => {
                selectedSessionIds = [];
                onSuccess();
            },
        });
    }

    function submitBulkCancel() {
        if (!cancelReason.trim()) return;
        submitBulkAction('/exams/sessions/bulk-cancel', {
            session_ids: selectedSessionIds,
            reason: cancelReason.trim(),
        }, () => {
            cancelDialogOpen = false;
            cancelReason = '';
        });
    }

    function occupancyPercent(session) {
        return session.room?.capacity
            ? Math.round(
                  ((session.assigned_count ?? 0) / session.room.capacity) * 100,
              )
            : 0;
    }

    function statusVariant(status) {
        if (status === 'draft') return 'secondary';
        if (status === 'published') return 'default';
        if (status === 'in_progress') return 'outline';
        if (status === 'completed') return 'outline';
        if (status === 'cancelled') return 'destructive';
        return 'outline';
    }

    function statusLabel(value) {
        if (value === 'active') return 'Needs scheduling';
        if (value === 'all') return 'All sessions';
        return statuses.find((s) => s.value === value)?.label ?? value;
    }

    function bookingModeLabel(mode) {
        if (mode === 'open_for_reservation') return 'Open for reservation';
        return 'Staff only';
    }

    function isDraftStaffOnly(session) {
        return (
            session.status === 'draft' &&
            (session.booking_mode === 'staff_only' || !session.booking_mode)
        );
    }

    function assignmentModeLabel(session) {
        if (session.booking_mode === 'open_for_reservation') {
            return 'Open for reservation';
        }
        if (isDraftStaffOnly(session) && session.auto_assign_enabled) {
            return 'Auto-Assign: ON';
        }
        return 'Staff only';
    }

    function toggleAutoAssign(session) {
        const url =
            typeof route === 'function'
                ? route('exams.sessions.toggle-auto-assign', session.id)
                : `/exams/sessions/${session.id}/toggle-auto-assign`;
        router.patch(url, {}, { preserveScroll: true });
    }
    function openSessionReservation(session) {
        router.post(
            route('exams.sessions.open-reservation', session.id),
            { expected_version: session.version },
            { preserveScroll: true },
        );
    }

    function closeSessionReservation(session) {
        router.post(
            route('exams.sessions.close-reservation', session.id),
            { expected_version: session.version },
            { preserveScroll: true },
        );
    }

    const breadcrumbs = [{ label: 'Exam Scheduling' }];
</script>

<svelte:head><title>Exam Scheduling</title></svelte:head>

<StaffLayout {breadcrumbs}>
    <div class="min-w-0 space-y-6 p-4 sm:p-6">
        <PageHeaderBar
            title="Exam Scheduling"
            description="Schedule examination sessions, allocate seating arrangements, assign applicants, and publish sessions for exam-day administration."
            tourKey="exam-sessions"
            context={sessionsContext}
        >
            {#snippet actions()}
                <HeaderActions>
                    {#snippet primaryAction()}
                        <Link href="/exams/sessions/create">
                            <Button
                                class="h-9 gap-1.5 px-3 text-xs font-semibold shadow-xs transition-all duration-200 hover:shadow-sm active:scale-[0.98]"
                            >
                                <Plus class="size-3.5" />
                                <span>Create session</span>
                            </Button>
                        </Link>
                    {/snippet}
                    {#snippet alertAction()}
                        {#if waitlistCount > 0}
                            <Link href={waitlistUrl}>
                                <Button
                                    variant="outline"
                                    class="h-9 gap-1.5 px-2.5 sm:px-3 text-xs font-medium border-primary/30 bg-primary/5 hover:bg-primary/10 text-foreground shadow-xs"
                                    title={`Applicant Waitlist: ${waitlistCount} ready for assignment`}
                                >
                                    <Users class="size-3.5 text-primary" />
                                    <span class="hidden sm:inline">Waitlist</span>
                                    <Badge variant="default" class="h-5 px-1.5 text-[10px] font-mono font-semibold">
                                        {waitlistCount}
                                    </Badge>
                                </Button>
                            </Link>
                        {/if}
                    {/snippet}
                    {#snippet secondaryActions()}
                        <Link href="/exams/direct-assessment">
                            <Button variant="outline" class="h-9 gap-1.5 px-3 text-xs">
                                <ClipboardList class="size-3.5" />
                                <span>Direct assessment</span>
                            </Button>
                        </Link>
                        <Button
                            type="button"
                            variant="outline"
                            class="group h-9 gap-1.5 px-3 text-xs font-medium shadow-xs transition-all duration-200 hover:shadow-sm active:scale-[0.98]"
                            data-tour="auto-distribute-button"
                            onclick={() => (autoDistributeOpen = true)}
                        >
                            <Sparkles class="size-3.5 transition-transform duration-300 group-hover:rotate-12 group-hover:scale-110" />
                            <span>Auto-Distribute</span>
                        </Button>
                        {#if waitlistCount === 0}
                            <ActionDropdown
                                items={[
                                    {
                                        label: 'Applicant Waitlist',
                                        href: waitlistUrl,
                                        icon: Users,
                                    },
                                ]}
                                triggerIcon={EllipsisVertical}
                            />
                        {/if}
                    {/snippet}
                </HeaderActions>
            {/snippet}
        </PageHeaderBar>

        {#if page_state === 'denied'}
            <p class="text-muted-foreground text-sm">
                You do not have permission to manage exam sessions.
            </p>
        {:else}
            <div class="space-y-6" data-tour="sessions-list-container">
                <div class="sticky top-0 z-20 backdrop-blur-md bg-background/80 py-2 space-y-3">
                    <div class="space-y-3 md:flex md:flex-wrap md:items-center md:gap-3 md:space-y-0">
                    <div class="relative w-full md:flex-1 md:min-w-[280px]">
                        <label class="text-muted-foreground mb-1 block text-xs font-medium" for="exam-search">Search</label>
                        <div class="relative">
                            <Search class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                            <Input
                                bind:ref={searchInput}
                                id="exam-search"
                                type="text"
                                placeholder="Search session code or room..."
                                class="h-11 min-h-[44px] w-full border border-input bg-background pl-10 pr-24 text-foreground shadow-xs dark:bg-input/30 md:h-10"
                                bind:value={draftSearch}
                                oninput={handleSearchInput}
                                onfocus={handleSearchFocus}
                                onblur={handleSearchBlur}
                                onkeydown={handleSearchKeydown}
                                role="combobox"
                                aria-autocomplete="list"
                                aria-expanded={suggestionDropdown?.isExpanded() ?? false}
                                aria-controls={suggestionDropdown?.listboxIdentifier()}
                                aria-activedescendant={suggestionDropdown?.activeDescendantId()}
                                aria-describedby={searchInteractionActive &&
                                isDraftDirty &&
                                !pendingSearch
                                    ? 'exam-search-guidance'
                                    : undefined}
                            />
                            <div class="absolute top-1/2 right-1.5 flex -translate-y-1/2 items-center gap-1">
                                {#if showClearSearch}
                                    <button type="button" class="grid size-8 place-items-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:outline-none" aria-label="Clear search" onclick={clearSearch}>
                                        <X class="size-4" />
                                    </button>
                                {/if}
                                <Button
                                    type="button"
                                    size="sm"
                                    class="h-8 px-2.5 text-xs font-semibold"
                                    onclick={() => commitSearch(draftSearch, 'search')}
                                    title="Search"
                                    aria-label="Search"
                                >
                                    Search
                                </Button>
                            </div>
                            <SearchSuggestionDropdown
                                bind:this={suggestionDropdown}
                                search={draftSearch}
                                academicCycleId={Number(suggestionCycleId || 0)}
                                status={filterStatus}
                                domainContext="scheduling"
                                contextKey={schedulingContext}
                                onactivate={handleSuggestionActivation}
                                oninteractionchange={handleSearchInteractionChange}
                                class="exam-session-suggestions"
                                inputRef={searchInput}
                            />
                        </div>
                        {#if requestMessage}<p class="text-destructive mt-1 text-xs" role="alert">{requestMessage}</p>{/if}
                        {#if pendingSearch}
                            <p class="text-muted-foreground mt-1 text-xs" role="status" aria-live="polite">
                                {pendingSearch.source === 'filters'
                                    ? 'Updating the session list'
                                    : pendingSearch.search
                                      ? `Applying search “${pendingSearch.search}”`
                                      : 'Removing the applied search'}
                                ; current results remain based on {committedSearch
                                    ? `“${committedSearch}”`
                                    : 'the current filters without a search'} until
                                the request succeeds.
                            </p>
                        {:else if isDraftDirty}
                            <p id="exam-search-guidance" class="text-muted-foreground mt-1 text-xs">
                                Press Enter to filter. The current list still reflects {committedSearch
                                    ? `“${committedSearch}”`
                                    : 'the applied filters without a search'}.
                            </p>
                        {:else if committedSearch}
                            <p class="text-muted-foreground mt-1 text-xs" role="status">
                                Showing sessions filtered by “{committedSearch}”.
                            </p>
                        {/if}
                    </div>
                    <div class="flex items-center justify-between gap-2 md:hidden">
                        <button
                            type="button"
                            class="flex min-h-[44px] w-full items-center justify-between rounded-xl border border-input bg-background px-3 text-sm font-semibold text-foreground shadow-xs dark:bg-input/30"
                            aria-expanded={mobileFiltersOpen}
                            aria-controls="exam-mobile-filters"
                            onclick={() => (mobileFiltersOpen = !mobileFiltersOpen)}
                        >
                            <span class="min-w-0 flex-1 truncate text-left">Filters ({activeCount})</span>
                            <ChevronDown class="ml-1.5 size-4 shrink-0 transition-transform {mobileFiltersOpen ? 'rotate-180' : ''}" />
                        </button>
                    </div>
                    <div
                        id="exam-mobile-filters"
                        class={`${mobileFiltersOpen ? 'grid grid-cols-1 gap-3 sm:grid-cols-2' : 'hidden'} w-full pt-2 md:flex md:w-auto md:flex-wrap md:items-end md:gap-2.5 md:pt-0`}
                    >
                        <div class="w-full md:w-[190px]">
                            <label class="text-muted-foreground mb-1 block text-xs font-medium" for="exam-cycle">Academic year</label>
                            <Select.Root type="single" bind:value={filterCycleId} onValueChange={(v) => { filterCycleId = v ?? ''; applyFilters(); }}>
                                <Select.Trigger id="exam-cycle" aria-label="Academic year" class="h-10 min-h-[44px] md:min-h-[40px] w-full border border-input bg-background text-xs text-foreground shadow-xs dark:bg-input/30">
                                    <span class="min-w-0 flex-1 truncate text-left">{#if filterCycleId}{cycles.find((c) => String(c.id) === filterCycleId)?.label ?? 'Academic year'}{:else}{defaultCycleLabel}{/if}</span>
                                </Select.Trigger>
                                <Select.Content>
                                    <Select.Item value="" label={defaultCycleLabel}>{defaultCycleLabel}</Select.Item>
                                    {#each cycles as c}<Select.Item value={String(c.id)} label={c.label}>{c.label}</Select.Item>{/each}
                                </Select.Content>
                            </Select.Root>
                        </div>
                        <div class="w-full md:w-[160px]">
                            <label class="text-muted-foreground mb-1 block text-xs font-medium" for="exam-room">Room</label>
                            <Select.Root type="single" bind:value={filterRoomId} onValueChange={(v) => filterRoomId = v ?? ''}>
                                <Select.Trigger id="exam-room" aria-label="Room" class="h-10 min-h-[44px] md:min-h-[40px] w-full border border-input bg-background text-xs text-foreground shadow-xs dark:bg-input/30">
                                    <span class="min-w-0 flex-1 truncate text-left">{#if filterRoomId}{rooms.find((room) => String(room.id) === filterRoomId)?.name ?? rooms.find((room) => String(room.id) === filterRoomId)?.code ?? 'Room'}{:else}All rooms{/if}</span>
                                </Select.Trigger>
                                <Select.Content>
                                    <Select.Item value="" label="All rooms">All rooms</Select.Item>
                                    {#each rooms as room (room.id)}<Select.Item value={String(room.id)} label={room.name ?? room.code}>{room.name ?? room.code}</Select.Item>{/each}
                                </Select.Content>
                            </Select.Root>
                        </div>
                        <div class="w-full md:w-[150px]">
                            <label class="text-muted-foreground mb-1 block text-xs font-medium" for="exam-status">Status</label>
                            <Select.Root type="single" bind:value={filterStatus} onValueChange={(v) => { filterStatus = v || 'active'; applyFilters(); }}>
                                <Select.Trigger id="exam-status" aria-label="Status" class="h-10 min-h-[44px] md:min-h-[40px] w-full border border-input bg-background text-xs text-foreground shadow-xs dark:bg-input/30">
                                    <span class="min-w-0 flex-1 truncate text-left">{statusLabel(filterStatus)}</span>
                                </Select.Trigger>
                                <Select.Content>
                                    <Select.Item value="active" label="Needs scheduling">Needs scheduling</Select.Item>
                                    <Select.Item value="all" label="All sessions">All sessions</Select.Item>
                                    {#each statuses as s}<Select.Item value={s.value} label={s.label}>{s.label}</Select.Item>{/each}
                                </Select.Content>
                            </Select.Root>
                        </div>
                        {#if sort_options?.length > 0}
                            <div class={viewMode === 'cards' ? 'w-full md:w-auto' : 'w-full md:hidden'}>
                                <label class="text-muted-foreground mb-1 block text-xs font-medium">Sort</label>
                                <SortDropdown options={sort_options} sort={filterSort} dir={filterDir} onchange={handleSortChange} class="exam-session-sort w-full" />
                            </div>
                        {/if}
                        <div class="w-full md:w-auto">
                            <Button variant="outline" class="h-10 min-h-[44px] md:min-h-[40px] w-full px-4 text-xs md:w-auto" onclick={clearAllFilters}>Reset</Button>
                        </div>
                    </div>
                    <ActiveFilterChips chips={activeFilterChips} onRemove={removeFilter} onClearAll={clearAllFilters} class="exam-session-filter-chips md:basis-full" />
                    </div>
                </div>

                {#if visibleSessions.length > 0}
                    <SwitchableListView bind:viewMode overflow="auto">
                        {#snippet table()}
                        <Table.Root class="w-full min-w-[780px] text-sm">
                            <Table.Header class="bg-muted/40 border-b border-border">
                                <Table.Row class="border-b border-border/40 hover:bg-muted/30 transition-colors">
                                    <Table.Head class="w-10 px-3 py-2.5 text-center"><Checkbox aria-label="Select all visible sessions" checked={isAllVisibleSelected} indeterminate={isSomeVisibleSelected} onCheckedChange={toggleAllVisible} /></Table.Head>
                                    <SortableTableHead label="Session Code" ariaLabel="Sort by session code" direction={columnSortDirection('code')} onclick={() => sortColumn('code')} />
                                    <SortableTableHead label="Date &amp; Time" ariaLabel="Sort by date and time" direction={columnSortDirection('date')} onclick={() => sortColumn('date')} />
                                    <SortableTableHead label="Room" ariaLabel="Sort by room" direction={columnSortDirection('room')} onclick={() => sortColumn('room')} />
                                    <SortableTableHead label="Occupancy" ariaLabel="Sort by capacity occupancy" direction={columnSortDirection('capacity')} onclick={() => sortColumn('capacity')} />
                                    <SortableTableHead label="Assignment" ariaLabel="Sort by assignment mode" direction={columnSortDirection('assignment')} onclick={() => sortColumn('assignment')} />
                                    <SortableTableHead label="Status" ariaLabel="Sort by session status" direction={columnSortDirection('status')} onclick={() => sortColumn('status')} />
                                    <Table.Head class="w-px px-3 py-2.5 pr-4 text-right font-sans whitespace-nowrap">Actions</Table.Head>
                                </Table.Row>
                            </Table.Header>
                            <Table.Body>
                                {#each visibleSessions as session (session.id)}
                                    <Table.Row
                                        class="border-b border-border/40 hover:bg-muted/30 transition-colors cursor-pointer"
                                        onclick={(e) => {
                                            if (
                                                e.target.closest(
                                                    'input[type="checkbox"], [data-action-cell]',
                                                )
                                            )
                                                return;
                                            router.visit(
                                                `/exams/sessions/${session.id}`,
                                            );
                                        }}
                                    >
                                        <Table.Cell
                                            class="w-10 px-3 py-3 text-center"
                                            data-action-cell
                                        >
                                            <input
                                                type="checkbox"
                                                aria-label={`Select session ${session.code}`}
                                                checked={selectedSessionIds.includes(
                                                    session.id,
                                                )}
                                                onchange={() =>
                                                    toggleSession(session.id)}
                                                onclick={(e) =>
                                                    e.stopPropagation()}
                                                class="border-border accent-primary size-4 rounded"
                                            />
                                        </Table.Cell>
                                        <Table.Cell
                                            class="text-foreground px-3 py-3 text-left font-mono font-bold whitespace-nowrap"
                                            >{session.code}</Table.Cell
                                        >
                                        <Table.Cell class="px-3 py-3 text-left font-sans whitespace-nowrap">
                                            <div class="space-y-0.5">
                                                <div
                                                    class="text-foreground flex items-center gap-1.5 text-xs font-medium"
                                                >
                                                    <Calendar
                                                        class="text-primary size-3.5 shrink-0"
                                                    />
                                                    <span
                                                        >{formatDate(
                                                            session.date,
                                                        )}</span
                                                    >
                                                </div>
                                                {#if session.start_time}
                                                    <div
                                                        class="text-muted-foreground flex items-center gap-1.5 font-mono text-[11px]"
                                                    >
                                                        <Clock
                                                            class="text-muted-foreground size-3 shrink-0"
                                                        />
                                                        <span
                                                            >{formatTimeRange(
                                                                session.start_time,
                                                                session.end_time,
                                                            )}</span
                                                        >
                                                    </div>
                                                {/if}
                                            </div>
                                        </Table.Cell>
                                        <Table.Cell
                                            class="max-w-[200px] px-3 py-3 text-left font-sans text-xs whitespace-normal"
                                        >
                                            <span
                                                class="text-foreground block truncate font-medium"
                                                title={session.room?.name ?? session.room?.code ?? ''}
                                                >{session.room?.name ??
                                                    session.room?.code ??
                                                    '—'}</span
                                            >
                                            {#if session.room?.building}
                                                <span
                                                    class="text-muted-foreground block truncate text-[11px]"
                                                    >{session.room
                                                        .building}</span
                                                >
                                            {/if}
                                        </Table.Cell>
                                        <Table.Cell class="px-3 py-3 text-left">
                                            <div
                                                class="min-w-[115px] max-w-[140px] space-y-1 font-mono"
                                            >
                                                <div
                                                    class="flex justify-between text-xs"
                                                >
                                                    <span
                                                        class="text-foreground font-bold"
                                                        >{session.assigned_count ??
                                                            0} / {session.room
                                                            ?.capacity ??
                                                            '—'}</span
                                                    >
                                                </div>
                                                <div
                                                    class="bg-muted h-1.5 w-full overflow-hidden rounded-full"
                                                >
                                                    <div
                                                        class="h-1.5 rounded-full bg-emerald-500 transition-all duration-300"
                                                        style="width: {session
                                                            .room?.capacity
                                                            ? Math.min(
                                                                  100,
                                                                  Math.round(
                                                                      ((session.assigned_count ??
                                                                          0) /
                                                                          session
                                                                              .room
                                                                              .capacity) *
                                                                          100,
                                                                  ),
                                                              )
                                                            : 0}%"
                                                    ></div>
                                                </div>
                                            </div>
                                        </Table.Cell>
                                        <Table.Cell class="px-3 py-3 text-left whitespace-nowrap">
                                            {#if session.booking_mode === 'open_for_reservation'}
                                                <Badge
                                                    variant="outline"
                                                    class="text-primary border-primary/30 font-sans text-[10px]"
                                                >
                                                    Open for reservation
                                                </Badge>
                                            {:else if isDraftStaffOnly(session) && session.auto_assign_enabled}
                                                <Badge
                                                    variant="outline"
                                                    class="gap-1.5 border-emerald-500/30 bg-emerald-500/10 font-sans text-[10px] font-semibold text-emerald-700 dark:text-emerald-300"
                                                >
                                                    <span class="size-1.5 rounded-full bg-emerald-500"></span>
                                                    Auto-Assign: ON
                                                </Badge>
                                            {:else}
                                                <Badge
                                                    variant="outline"
                                                    class="border-border/60 text-muted-foreground font-sans text-[10px]"
                                                >
                                                    Staff only
                                                </Badge>
                                            {/if}
                                        </Table.Cell>
                                        <Table.Cell class="px-3 py-3 text-left whitespace-nowrap">
                                            <Badge
                                                variant={statusVariant(
                                                    session.status,
                                                )}
                                                class="font-mono text-[10px] font-bold"
                                            >
                                                {statusLabel(
                                                    session.status,
                                                )}
                                            </Badge>
                                        </Table.Cell>
                                        <Table.Cell
                                            class="w-px px-3 py-3 pr-4 text-right whitespace-nowrap"
                                            data-action-cell
                                        >
                                            <EntityRowActions
                                                primaryAction={{
                                                    label: 'Show Roster',
                                                    href: `/exams/sessions/${session.id}`,
                                                    icon: Eye,
                                                    variant: 'outline',
                                                }}
                                                overflowItems={[
                                                    session.status === 'draft' ? {
                                                        label: 'Edit Session',
                                                        href: `/exams/sessions/${session.id}/edit`,
                                                        icon: Pencil,
                                                    } : null,
                                                    session.status === 'draft' && session.booking_mode === 'open_for_reservation' ? {
                                                        label: 'Close Reservation (Staff Only)',
                                                        onclick: () => closeSessionReservation(session),
                                                    } : null,
                                                    session.status === 'draft' && session.booking_mode !== 'open_for_reservation' ? {
                                                        label: 'Open Reservation',
                                                        onclick: () => openSessionReservation(session),
                                                    } : null,
                                                    isDraftStaffOnly(session) ? {
                                                        label: session.auto_assign_enabled
                                                            ? 'Disable Auto-Assign'
                                                            : 'Enable Auto-Assign',
                                                        onclick: () => toggleAutoAssign(session),
                                                        icon: Sparkles,
                                                    } : null,
                                                ]}
                                                dense={true}
                                            />
                                        </Table.Cell>
                                    </Table.Row>
                                {/each}
                            </Table.Body>
                        </Table.Root>
                    {/snippet}

                    {#snippet cards()}
                            <div class="mb-3 flex items-center gap-2">
                                <Checkbox
                                    checked={isAllVisibleSelected}
                                    indeterminate={isSomeVisibleSelected}
                                    onCheckedChange={toggleAllVisible}
                                    aria-label="Select all visible sessions"
                                />
                                <span class="text-muted-foreground text-sm"
                                    >Select all visible sessions</span
                                >
                            </div>
                            <ul
                                class="grid grid-cols-1 gap-5 text-xs md:grid-cols-2 xl:grid-cols-3"
                                role="list"
                            >
                                {#each visibleSessions as session (session.id)}
                                    {@const percent = occupancyPercent(session)}
                                    <OperationalCard
                                        id={session.id}
                                        title={formatHumanSchedule(
                                            session.date,
                                            session.start_time,
                                            session.end_time,
                                        )}
                                        subtitle={`${session.room?.name ?? session.room?.code ?? 'Room TBD'} · ${session.code}`}
                                    >
                                        {#snippet selection()}
                                            <input
                                                type="checkbox"
                                                aria-label={`Select session ${session.code}`}
                                                checked={selectedSessionIds.includes(
                                                    session.id,
                                                )}
                                                onchange={() =>
                                                    toggleSession(session.id)}
                                                class="border-border accent-primary size-4 shrink-0 rounded"
                                            />
                                        {/snippet}
                                        {#snippet status()}
                                            <div class="flex shrink-0 items-center justify-end gap-1.5 flex-wrap">
                                                <Badge
                                                    variant={statusVariant(
                                                        session.status,
                                                    )}
                                                    class="font-mono text-[10px] font-bold"
                                                >
                                                    {statusLabel(
                                                        session.status,
                                                    )}
                                                </Badge>
                                                {#if session.booking_mode === 'open_for_reservation'}
                                                    <Badge
                                                        variant="outline"
                                                        class="text-primary border-primary/30 font-sans text-[10px]"
                                                    >
                                                        Open for reservation
                                                    </Badge>
                                                {:else if isDraftStaffOnly(session) && session.auto_assign_enabled}
                                                    <Badge
                                                        variant="outline"
                                                        class="gap-1.5 border-emerald-500/30 bg-emerald-500/10 font-sans text-[10px] font-semibold text-emerald-700 dark:text-emerald-300"
                                                    >
                                                        <span class="size-1.5 rounded-full bg-emerald-500"></span>
                                                        Auto-Assign: ON
                                                    </Badge>
                                                {:else}
                                                    <Badge
                                                        variant="outline"
                                                        class="border-border/60 text-muted-foreground font-sans text-[10px]"
                                                    >
                                                        Staff only
                                                    </Badge>
                                                {/if}
                                            </div>
                                        {/snippet}
                                        {#snippet summary()}
                                            <div class="space-y-1.5">
                                                <div
                                                    class="text-foreground font-mono text-[11px] font-semibold"
                                                >
                                                    Occupancy: {session.assigned_count ??
                                                        0} / {session.room
                                                        ?.capacity ?? '—'}
                                                </div>
                                                <div
                                                    class="bg-muted h-1.5 overflow-hidden rounded-full"
                                                    role="progressbar"
                                                    aria-label={`Session occupancy for ${session.code}`}
                                                    aria-valuenow={Math.min(
                                                        100,
                                                        percent,
                                                    )}
                                                    aria-valuemin="0"
                                                    aria-valuemax="100"
                                                >
                                                    <div
                                                        class="h-full rounded-full bg-emerald-500 transition-all"
                                                        style="width: {Math.min(
                                                            100,
                                                            percent,
                                                        )}%"
                                                    ></div>
                                                </div>
                                            </div>
                                        {/snippet}
                                        {#snippet details()}
                                            <div class="space-y-2.5">
                                                <div
                                                    class="flex items-start gap-2"
                                                >
                                                    <Building2
                                                        class="text-muted-foreground mt-0.5 size-3.5 shrink-0"
                                                    />
                                                    <div class="min-w-0">
                                                        <div
                                                            class="text-foreground font-semibold"
                                                        >
                                                            {session.room
                                                                ?.name ??
                                                                session.room
                                                                    ?.code ??
                                                                'Room TBD'}
                                                        </div>
                                                        <div
                                                            class="text-muted-foreground text-[11px]"
                                                        >
                                                            {session.room
                                                                ?.building ??
                                                                'Building not specified'}
                                                        </div>
                                                    </div>
                                                </div>
                                                <div
                                                    class="text-muted-foreground flex items-center gap-2"
                                                >
                                                    <Calendar
                                                        class="text-primary size-3.5 shrink-0"
                                                    />
                                                    <span
                                                        >{formatDate(
                                                            session.date,
                                                        )}</span
                                                    >
                                                </div>
                                                {#if session.start_time}
                                                    <div
                                                        class="text-muted-foreground flex items-center gap-2"
                                                    >
                                                        <Clock
                                                            class="size-3.5 shrink-0"
                                                        />
                                                        <span class="font-mono"
                                                            >{formatTimeRange(
                                                                session.start_time,
                                                                session.end_time,
                                                            )}</span
                                                        >
                                                    </div>
                                                {/if}
                                            </div>
                                        {/snippet}
                                        {#snippet actions()}
                                            <div
                                                class="flex items-center justify-between gap-2"
                                                onclick={(e) => e.stopPropagation()}
                                                role="presentation"
                                            >
                                                <EntityRowActions
                                                    class="w-full justify-between"
                                                    primaryAction={{
                                                        label: 'Show Roster',
                                                        href: `/exams/sessions/${session.id}`,
                                                        icon: Eye,
                                                        variant: 'outline',
                                                    }}
                                                    overflowItems={[
                                                        session.status === 'draft' ? {
                                                            label: 'Edit Session',
                                                            href: `/exams/sessions/${session.id}/edit`,
                                                            icon: Pencil,
                                                        } : null,
                                                        session.status === 'draft' && session.booking_mode === 'open_for_reservation' ? {
                                                            label: 'Close Reservation (Staff Only)',
                                                            onclick: () => closeSessionReservation(session),
                                                        } : null,
                                                        session.status === 'draft' && session.booking_mode !== 'open_for_reservation' ? {
                                                            label: 'Open Reservation',
                                                            onclick: () => openSessionReservation(session),
                                                        } : null,
                                                        isDraftStaffOnly(session) ? {
                                                            label: session.auto_assign_enabled
                                                                ? 'Disable Auto-Assign'
                                                                : 'Enable Auto-Assign',
                                                            onclick: () => toggleAutoAssign(session),
                                                            icon: Sparkles,
                                                        } : null,
                                                    ]}
                                                />
                                            </div>
                                        {/snippet}
                                    </OperationalCard>
                                {/each}
                            </ul>
                    {/snippet}
                    </SwitchableListView>
                {:else if hasNarrowingFilters}
                    <div
                        class="border-border bg-card space-y-2 rounded-xl border p-8 text-center"
                    >
                        <p class="text-foreground text-sm font-medium">
                            No exam sessions match your current filter
                        </p>
                        <p class="text-muted-foreground text-xs">
                            Try clearing your search term, room, or status
                            selection.
                        </p>
                        <Button
                            variant="outline"
                            size="sm"
                            class="mt-2 text-xs"
                            onclick={clearAllFilters}
                        >
                            Clear All Filters
                        </Button>
                    </div>
                {:else}
                    <div
                        class="border-border bg-card space-y-4 rounded-2xl border p-8 text-center shadow-xs"
                    >
                        <div
                            class="bg-muted/60 text-muted-foreground mx-auto flex size-12 items-center justify-center rounded-full"
                        >
                            <School class="text-muted-foreground/70 size-6" />
                        </div>
                        <div class="mx-auto max-w-md space-y-1.5">
                            <h3 class="text-foreground text-base font-bold">
                                No Sessions in Active Schedule
                            </h3>
                            <p
                                class="text-muted-foreground text-xs leading-relaxed"
                            >
                                All examination rooms are currently on standby
                                for {activeCycleLabel}. Create an exam session
                                under Exam Scheduling to initiate live
                                testing-day monitoring.
                            </p>
                        </div>
                        <div
                            class="flex flex-wrap items-center justify-center gap-3 pt-2"
                        >
                            <Link href="/exams/sessions/create">
                                <Button variant="default" size="sm">
                                    <Plus class="mr-1.5 size-3.5" />
                                    <span>Create Exam Session</span>
                                </Button>
                            </Link>
                        </div>
                    </div>
                {/if}
            </div>
    {/if}
    {#if selectedSessionIds.length > 0}
        <BulkActionBar
        count={selectedSessionIds.length}
        onClear={() => (selectedSessionIds = [])}
    >
        {#snippet actions()}
            <Button
                size="sm"
                onclick={() =>
                    submitBulkAction('/exams/sessions/bulk-publish', {
                        session_ids: selectedSessionIds,
                    })}>Bulk Publish</Button
            >
            <Button
                size="sm"
                variant="outline"
                onclick={() =>
                    submitBulkAction('/exams/sessions/bulk-booking-mode', {
                        session_ids: selectedSessionIds,
                        booking_mode: 'open_for_reservation',
                    })}>Open Reservation</Button
            >
            <Button
                size="sm"
                variant="outline"
                onclick={() =>
                    submitBulkAction('/exams/sessions/bulk-booking-mode', {
                        session_ids: selectedSessionIds,
                        booking_mode: 'staff_only',
                    })}>Staff Only</Button
            >
            <Button
                size="sm"
                variant="destructive"
                onclick={() => (cancelDialogOpen = true)}>Bulk Cancel</Button
            >
        {/snippet}
    </BulkActionBar>
    {/if}
    {#if cancelDialogOpen}
        <div
            class="fixed inset-0 z-50 grid place-items-center bg-black/50 p-4"
            role="presentation"
            onclick={(event) => {
                if (event.target === event.currentTarget)
                    cancelDialogOpen = false;
            }}
        >
            <section
                class="border-border bg-card w-full max-w-lg space-y-4 rounded-xl border p-5 shadow-xl"
                role="dialog"
                aria-modal="true"
                aria-labelledby="bulk-cancel-title"
                aria-describedby="bulk-cancel-description"
            >
                <div>
                    <h2 id="bulk-cancel-title" class="text-lg font-semibold">
                        Cancel selected sessions
                    </h2>
                    <p
                        id="bulk-cancel-description"
                        class="text-muted-foreground mt-1 text-sm"
                    >
                        This will cancel {selectedSessionIds.length} selected session{selectedSessionIds.length ===
                        1
                            ? ''
                            : 's'}. Provide a reason to continue.
                    </p>
                </div>
                <label
                    for="bulk-cancel-reason"
                    class="block text-sm font-medium">Cancellation reason</label
                >
                <div
                    class="flex flex-wrap gap-2"
                    aria-label="Cancellation reason presets"
                >
                    {#each ['Weather / Typhoon Suspension', 'Holiday Declaration', 'Venue / Room Maintenance', 'Administrative Rescheduling'] as preset}
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onclick={() => (cancelReason = preset)}
                            >{preset}</Button
                        >
                    {/each}
                </div>
                <textarea
                    id="bulk-cancel-reason"
                    bind:value={cancelReason}
                    rows="4"
                    required
                    class="border-input bg-background w-full rounded-md border px-3 py-2 text-sm"
                    placeholder="Enter the reason for cancellation"></textarea>
                <div class="flex justify-end gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        onclick={() => {
                            cancelDialogOpen = false;
                            cancelReason = '';
                        }}>Back</Button
                    >
                    <Button
                        type="button"
                        variant="destructive"
                        disabled={!cancelReason.trim()}
                        onclick={submitBulkCancel}>Confirm cancellation</Button
                    >
                </div>
            </section>
        </div>
    {/if}
    </div>
    <CohortAutoDistributeModal
        bind:open={autoDistributeOpen}
        domain="exam"
        academicCycleId={distribution?.active_cycle_id ??
            filters?.academic_cycle_id}
        academicCycleName={cycles.find(
            (c) =>
                String(c.value ?? c.id) ===
                String(
                    distribution?.active_cycle_id ?? filters?.academic_cycle_id,
                ),
        )?.label ?? 'Current Academic Cycle'}
        {sessions}
        {distribution}
    />
</StaffLayout>
<style>
    :global(.exam-session-suggestions > div.absolute) {
        z-index: 50;
        width: 100%;
        max-width: min(42rem, calc(100vw - 2rem));
        min-width: 0;
        margin-top: 0.375rem;
        padding: 1rem;
        border: 1px solid var(--border);
        border-radius: 1rem;
        background: var(--card);
        backdrop-filter: blur(12px);
    }
    :global(.exam-session-suggestions > div.absolute button) {
        min-height: 44px;
    }
    :global(.exam-session-filter-chips button[aria-label^='Remove filter']) {
        min-width: 44px;
        min-height: 44px;
    }
    :global(.exam-session-filter-chips button) {
        min-height: 44px;
    }
    :global(.exam-session-sort select),
    :global(.exam-session-sort button) {
        min-height: 44px !important;
    }
</style>
