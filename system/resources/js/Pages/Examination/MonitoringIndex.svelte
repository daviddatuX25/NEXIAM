<script>
    /**
     * Exam Monitoring Index (Conduct Phase & Testing-Day Operations)
     *
     * Architectural Role:
     * 1. Live Conduct: Room status, attendance, and Test Administrator station entry.
     * 2. Conduct History: Turnout and attendance records with post-session audit context.
     * 3. Persistent Campus Telemetry: Campus-wide turnout stats maintained even in standby periods.
     * 4. Conduct Policies & Setup Integration: Non-disruptive Popover linking conduct thresholds
     *    to the Notification Operations Hub and Cycle Communication Policies.
     * 5. Paginated & Searchable History: Bounded navigation with live room and code filters.
     */
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import HeaderActions from '@/Components/kit/HeaderActions.svelte';
    import TourTriggerGlyph from '@/Components/kit/Walkthrough/TourTriggerGlyph.svelte';
    import XiamNpcDialog from '@/Components/kit/Walkthrough/XiamNpcDialog.svelte';
    import SwitchableListView from '@/Components/kit/SwitchableListView.svelte';
    import OperationalCard from '@/Components/kit/OperationalCard.svelte';
    import SimplePagination from '@/Components/kit/SimplePagination.svelte';
    import SearchSuggestionDropdown from '@/Components/kit/SearchSuggestionDropdown.svelte';
    import { Badge } from '@/Components/ui/badge';
    import { Button } from '@/Components/ui/button';
    import { Input } from '@/Components/ui/input';
    import * as Table from '@/Components/ui/table';
    import SortableTableHead from '@/Components/kit/SortableTableHead.svelte';
    import * as Select from '@/Components/ui/select';
    
    import * as Popover from '@/Components/ui/popover';
    import { onDestroy, onMount } from 'svelte';
    import { Link, router, usePage, usePoll } from '@inertiajs/svelte';
    import {
        Activity,
        Eye,
        AlertTriangle,
        Settings2,
        Calendar,
        Clock,
        School,
        Users,
        CheckCircle2,
        UserX,
        ExternalLink,
        Inbox,
        Layers,
        Search,
        X,
        ChevronDown,
    } from 'lucide-svelte';
    import { formatDate, formatTimeRange } from '@/lib/date-utils';

    let {
        page_state = 'ready',
        sessions = [],
        active_sessions = [],
        history_sessions = null,
        filters = {},
        rooms = [],
        can_manage = false,
        telemetry = null,
        active_academic_cycle = null,
        thresholds = {},
    } = $props();

    const page = usePage();
    let browserNetworkAvailable = $state(typeof navigator === 'undefined' ? true : navigator.onLine);
    let tourOpen = $state(false);
    let suggestionDropdown = $state(null);
    let searchInput = $state(null);
    let expandedCards = $state({});
    let viewMode = $state('responsive');

    function handleSearchKeydown(event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            commitFilters(draftSearch, { syncDraftOnSuccess: true });
            return;
        }
        suggestionDropdown?.handleKeydown(event);
    }

    const activeList = $derived(
        (page.props.active_sessions?.length > 0 ? page.props.active_sessions : page.props.sessions) ??
        (active_sessions?.length > 0 ? active_sessions : (sessions ?? [])),
    );
    const historyList = $derived(
        page.props.history_sessions?.data ??
        (Array.isArray(page.props.history_sessions) ? page.props.history_sessions : null) ??
        (history_sessions?.data ?? (Array.isArray(history_sessions) ? history_sessions : [])),
    );
    const historyTotal = $derived(
        page.props.history_sessions?.total ?? historyList.length,
    );
    let filterStatus = $state('');
    let mobileFiltersOpen = $state(false);
    const activeCount = $derived(
        [committedSearch, selectedRoomId, filterStatus, selectedDate].filter(Boolean).length,
    );
    let tableSort = $state({ key: '', dir: 'asc' });
    const activeRows = $derived(sortMonitoringRows(
        activeList.filter((session) => !filterStatus || session.status === filterStatus),
        'active',
    ));
    const historyRows = $derived(sortMonitoringRows(
        historyList.filter((session) => !filterStatus || session.status === filterStatus),
        'history',
    ));


    function sortMonitoringRows(rows, group) {
        if (!tableSort.key || (group === 'active' && tableSort.key === 'turnout') || (group === 'history' && tableSort.key === 'assigned')) return rows;
        const { key, dir } = tableSort;
        return [...rows].sort((left, right) => {
            if (key === 'assigned') {
                return (Number(left.assigned_count ?? 0) - Number(right.assigned_count ?? 0)) * (dir === 'asc' ? 1 : -1);
            }
            if (key === 'turnout') {
                return (Number(left.present_count ?? 0) - Number(right.present_count ?? 0)) * (dir === 'asc' ? 1 : -1);
            }
            const value = (session) => key === 'date'
                ? `${session.date ?? session.starts_at ?? ''} ${session.start_time ?? ''}`
                : key === 'room'
                  ? session.room_name ?? session.room?.name ?? session.room?.code ?? ''
                  : key === 'code'
                    ? session.code ?? ''
                    : session.status ?? '';
            return String(value(left)).localeCompare(String(value(right))) * (dir === 'asc' ? 1 : -1);
        });
    }

    function sortColumn(key) {
        tableSort = {
            key,
            dir: tableSort.key === key && tableSort.dir === 'asc' ? 'desc' : 'asc',
        };
    }

    function columnSortDirection(key) {
        return tableSort.key === key
            ? tableSort.dir === 'asc' ? 'ascending' : 'descending'
            : 'none';
    }

    function clearMonitoringControls() {
        filterStatus = '';
        selectedRoomId = '';
        selectedDate = '';
        tableSort = { key: '', dir: 'asc' };
        clearFilters();
    }


    let activeTab = $state(filters?.tab || 'active');

    function selectTab(tab) {
        if (tab === activeTab) {
            return;
        }

        activeTab = tab;
        committedTab = tab;
        contextRevision += 1;
        suggestionDropdown?.dismiss();
        const url = new URL(window.location.href);
        url.searchParams.set('tab', tab);
        url.searchParams.delete('history_page');
        url.searchParams.delete('page');
        window.history.replaceState(window.history.state, '', url.toString());
    }

    function normalizedSearch(value) {
        return String(value ?? '').trim();
    }

    function filterSnapshot(source) {
        return {
            search: normalizedSearch(source?.search),
            roomId: source?.room_id ? String(source.room_id) : '',
            date: source?.date ?? '',
            tab: source?.tab ?? '',
        };
    }

    function filterSnapshotKey(snapshot) {
        return JSON.stringify(snapshot);
    }

    let draftSearch = $state(normalizedSearch(filters.search));
    let committedSearch = $state(normalizedSearch(filters.search));
    let selectedRoomId = $state(filters.room_id ? String(filters.room_id) : '');
    let committedRoomId = $state(
        filters.room_id ? String(filters.room_id) : '',
    );
    let selectedDate = $state(filters.date ?? '');
    let committedDate = $state(filters.date ?? '');
    let committedTab = $state(filters?.tab ?? activeTab);
    let draftRevision = $state(0);
    let contextRevision = $state(0);
    let pendingSearch = $state(null);
    let filterVisitPending = $state(false);
    let filterError = $state('');
    let searchInteractionActive = $state(false);
    let activeFilterVisit = null;
    let activePollingRequest = null;
    let monitoringRowsOwnerRevision = 0;
    let navigationVisitPending = false;
    let pendingNavigationUrl = null;
    let observedFilterKey = filterSnapshotKey(filterSnapshot(filters));

    const normalizedDraftSearch = $derived(normalizedSearch(draftSearch));
    const searchIsDirty = $derived(normalizedDraftSearch !== committedSearch);
    const hasSearchToClear = $derived(
        Boolean(
            draftSearch ||
            committedSearch ||
            (filterVisitPending && pendingSearch),
        ),
    );
    const pollingStatus = $derived.by(() => {
        if (page_state === 'denied') return 'Auto-refresh unavailable';
        if (!browserNetworkAvailable) return 'Offline — current monitoring data may be stale';
        if (searchInteractionActive) return 'Refresh paused while searching';
        if (filterVisitPending) return 'Refresh paused while filters apply';
        if (navigationVisitPending) return 'Refresh paused during navigation';
        return null;
    });
    const suggestionContextKey = $derived(
        `${page.props.auth?.user?.id ?? 'anonymous'}:${page.props.practice_workspace?.id ?? 'operational'}`,
    );

    function acceptServerFilters(snapshot) {
        observedFilterKey = filterSnapshotKey(snapshot);
        committedSearch = snapshot.search;
        committedRoomId = snapshot.roomId;
        committedDate = snapshot.date;
        committedTab = snapshot.tab || 'active';
        draftSearch = committedSearch;
        selectedRoomId = committedRoomId;
        selectedDate = committedDate;
        activeTab = committedTab;
        draftRevision += 1;
        contextRevision += 1;
    }

    $effect(() => {
        const snapshot = filterSnapshot(filters);
        const key = filterSnapshotKey(snapshot);
        if (key === observedFilterKey) {
            return;
        }

        observedFilterKey = key;
        if (!filterVisitPending) {
            acceptServerFilters(snapshot);
        }
    });

    function handleSearchInput(event) {
        draftSearch = event.currentTarget.value;
        draftRevision += 1;
        filterError = '';
        suggestionDropdown?.handleInput(draftSearch);
    }

    function handleSearchFocus() {
        handleSearchInteractionChange(true);
        suggestionDropdown?.handleFocus();
        if (searchIsDirty && !filterVisitPending && !navigationVisitPending) {
            suggestionDropdown?.handleInput(draftSearch);
        }
    }

    function handleSearchBlur(event) {
        suggestionDropdown?.handleBlur(event);
    }

    function handleSearchInteractionChange(isActive) {
        searchInteractionActive = isActive;
        if (isActive) {
            pauseMonitoringPolling();
        } else {
            resumeMonitoringPollingIfAllowed();
        }
    }

    function restoreUnacceptedContext(request) {
        if (request.contextRevision !== contextRevision) {
            return;
        }

        selectedRoomId = committedRoomId;
        selectedDate = committedDate;
        activeTab = committedTab;
    }

    function commitFilters(searchValue, { syncDraftOnSuccess = false } = {}) {
        const submittedSearch = normalizedSearch(searchValue);
        const request = {
            cancelToken: null,
            succeeded: false,
            initialFilterKey: filterSnapshotKey(filterSnapshot(filters)),
            contextRevision,
            draftRevision,
            tab: activeTab,
            syncDraftOnSuccess,
        };
        const previousRequest = activeFilterVisit;
        activeFilterVisit = request;
        filterVisitPending = true;
        pendingSearch = submittedSearch;
        filterError = '';
        pauseMonitoringPolling();
        suggestionDropdown?.dismiss();
        previousRequest?.cancelToken?.cancel();

        router.get(
            '/exams/monitoring',
            {
                search: submittedSearch || undefined,
                room_id: selectedRoomId || undefined,
                date: selectedDate || undefined,
                tab: activeTab,
                history_page: 1,
            },
            {
                preserveState: false,
                preserveScroll: true,
                replace: true,
                onCancelToken: (cancelToken) => {
                    request.cancelToken = cancelToken;
                },
                onSuccess: (responsePage) => {
                    if (activeFilterVisit !== request) {
                        return;
                    }

                    if (responsePage.props?.page_state === 'denied') {
                        filterError = 'Access to exam monitoring was denied.';
                        return;
                    }

                    const accepted = filterSnapshot(
                        responsePage.props?.filters ?? {},
                    );
                    observedFilterKey = filterSnapshotKey(accepted);
                    committedSearch = accepted.search;
                    committedRoomId = accepted.roomId;
                    committedDate = accepted.date;

                    if (request.contextRevision === contextRevision) {
                        committedTab = accepted.tab || activeTab;
                        selectedRoomId = committedRoomId;
                        selectedDate = committedDate;
                        activeTab = committedTab;
                    } else if (activeTab !== request.tab) {
                        committedTab = activeTab;
                        const url = new URL(window.location.href);
                        url.searchParams.set('tab', activeTab);
                        window.history.replaceState(
                            window.history.state,
                            '',
                            url.toString(),
                        );
                    } else {
                        committedTab = accepted.tab || activeTab;
                    }
                    if (
                        request.syncDraftOnSuccess &&
                        request.draftRevision === draftRevision
                    ) {
                        draftSearch = committedSearch;
                    }

                    filterError = '';
                    request.succeeded = true;
                },
                onError: () => {
                    if (activeFilterVisit === request) {
                        filterError =
                            'Filters could not be applied. Showing the last accepted results.';
                        restoreUnacceptedContext(request);
                    }
                },
                onHttpException: (response) => {
                    if (activeFilterVisit === request) {
                        filterError =
                            response?.status === 403
                                ? 'Access to these monitoring results was denied.'
                                : 'Filters could not be applied. Showing the last accepted results.';
                        restoreUnacceptedContext(request);
                    }
                },
                onNetworkError: () => {
                    if (activeFilterVisit === request) {
                        filterError =
                            'Filters could not be applied because the request failed. Showing the last accepted results.';
                        restoreUnacceptedContext(request);
                    }
                },
                onCancel: () => {
                    if (activeFilterVisit === request) {
                        filterError =
                            'The filter request was cancelled. Showing the last accepted results.';
                        restoreUnacceptedContext(request);
                    }
                },
                onFinish: () => {
                    if (activeFilterVisit !== request) {
                        return;
                    }

                    if (!request.succeeded) {
                        const restored = filterSnapshot(filters);
                        const restoredKey = filterSnapshotKey(restored);
                        if (restoredKey !== request.initialFilterKey) {
                            observedFilterKey = restoredKey;
                            acceptServerFilters(restored);
                        }
                    }

                    activeFilterVisit = null;
                    filterVisitPending = false;
                    pendingSearch = null;
                    resumeMonitoringPollingIfAllowed();
                },
            },
        );
    }

    function applyRoomFilter(event) {
        selectedRoomId = event.currentTarget.value;
        contextRevision += 1;
        commitFilters(committedSearch);
    }

    function applyDateFilter(event) {
        selectedDate = event.currentTarget.value;
        contextRevision += 1;
        commitFilters(committedSearch);
    }

    function clearSearch() {
        const searchRemovalAlreadyPending =
            filterVisitPending && pendingSearch === '';
        const hasPendingSearchToSupersede =
            filterVisitPending && Boolean(pendingSearch);

        draftSearch = '';
        draftRevision += 1;
        filterError = '';
        suggestionDropdown?.dismiss();

        if (
            !searchRemovalAlreadyPending &&
            (committedSearch || hasPendingSearchToSupersede)
        ) {
            contextRevision += 1;
            commitFilters('', { syncDraftOnSuccess: true });
        }

        searchInput?.focus();
    }

    function clearFilters() {
        draftSearch = '';
        draftRevision += 1;
        contextRevision += 1;
        selectedRoomId = '';
        selectedDate = '';
        suggestionDropdown?.dismiss();
        commitFilters('', { syncDraftOnSuccess: true });
    }

    function handleSearchActivation(action) {
        if (action?.type === 'navigate') {
            if (typeof action.url === 'string' && action.url.length > 0) {
                visitMonitoringSession(action.url);
            }
            return;
        }

        if (action?.type !== 'filter' || typeof action.query !== 'string') {
            return;
        }

        draftSearch = action.query;
        draftRevision += 1;
        commitFilters(action.query, { syncDraftOnSuccess: true });
    }

    function pauseMonitoringPolling() {
        monitoringRowsOwnerRevision += 1;
        monitoringPoll.stop();
        const request = activePollingRequest;
        activePollingRequest = null;
        request?.cancelToken?.cancel();
    }

    function resumeMonitoringPollingIfAllowed() {
        if (
            page_state === 'denied' ||
            !browserNetworkAvailable ||
            searchInteractionActive ||
            filterVisitPending ||
            navigationVisitPending
        ) {
            return;
        }

        monitoringPoll.start();
    }

    function prepareSessionNavigation(eventOrUrl) {
        if (typeof eventOrUrl !== 'string' && eventOrUrl) {
            if (
                eventOrUrl.defaultPrevented ||
                eventOrUrl.button !== 0 ||
                eventOrUrl.metaKey ||
                eventOrUrl.ctrlKey ||
                eventOrUrl.shiftKey ||
                eventOrUrl.altKey
            ) {
                return;
            }
        }

        const expectedUrl =
            typeof eventOrUrl === 'string'
                ? eventOrUrl
                : eventOrUrl?.currentTarget?.href;
        if (typeof expectedUrl !== 'string' || expectedUrl === '') {
            return;
        }

        navigationVisitPending = true;
        pendingNavigationUrl = new URL(expectedUrl, window.location.href).href;
        pauseMonitoringPolling();
        suggestionDropdown?.dismiss();
        const pendingFilter = activeFilterVisit;
        activeFilterVisit = null;
        filterVisitPending = false;
        pendingSearch = null;
        pendingFilter?.cancelToken?.cancel();
    }

    function visitMonitoringSession(url) {
        prepareSessionNavigation(url);
        router.visit(url, { preserveState: false, preserveScroll: false });
    }

    let browserHistoryRestorePending = false;

    function markBrowserHistoryRestore() {
        browserHistoryRestorePending = true;
    }

    const removeExplicitVisitBeforeListener = router.on('before', (event) => {
        const visitUrl = event.detail?.visit?.url;
        if (event.detail?.visit?.poll || !visitUrl || activeFilterVisit) {
            return;
        }

        navigationVisitPending = true;
        pendingNavigationUrl = new URL(String(visitUrl), window.location.href)
            .href;
        pauseMonitoringPolling();
    });

    const removeHistoryNavigateListener = router.on('navigate', (event) => {
        if (!browserHistoryRestorePending) {
            return;
        }

        browserHistoryRestorePending = false;
        const restoredPage = event.detail?.page;
        if (restoredPage?.component !== 'Examination/MonitoringIndex') {
            return;
        }

        pauseMonitoringPolling();
        const previousRequest = activeFilterVisit;
        activeFilterVisit = null;
        filterVisitPending = false;
        pendingSearch = null;
        previousRequest?.cancelToken?.cancel();
        navigationVisitPending = false;
        pendingNavigationUrl = null;
        filterError = '';

        const restored = filterSnapshot(restoredPage.props?.filters ?? {});
        observedFilterKey = filterSnapshotKey(restored);
        acceptServerFilters(restored);
        resumeMonitoringPollingIfAllowed();
    });

    onMount(() => {
        const onOnline = () => {
            browserNetworkAvailable = true;
            resumeMonitoringPollingIfAllowed();
        };
        const onOffline = () => {
            browserNetworkAvailable = false;
            pauseMonitoringPolling();
        };
        window.addEventListener('online', onOnline);
        window.addEventListener('offline', onOffline);
        window.addEventListener('popstate', markBrowserHistoryRestore);
        return () => {
            window.removeEventListener('online', onOnline);
            window.removeEventListener('offline', onOffline);
            window.removeEventListener('popstate', markBrowserHistoryRestore);
        };
    });

    const removeNavigationFinishListener = router.on('finish', (event) => {
        if (!navigationVisitPending || !pendingNavigationUrl) {
            return;
        }

        const finishedUrl = event.detail?.visit?.url;
        if (
            !finishedUrl ||
            new URL(String(finishedUrl), window.location.href).href !==
                pendingNavigationUrl
        ) {
            return;
        }

        navigationVisitPending = false;
        pendingNavigationUrl = null;
        resumeMonitoringPollingIfAllowed();
    });

    onDestroy(() => {
        removeNavigationFinishListener();
        removeExplicitVisitBeforeListener();
        removeHistoryNavigateListener();
        const filterRequest = activeFilterVisit;
        activeFilterVisit = null;
        filterVisitPending = false;
        pendingSearch = null;
        filterRequest?.cancelToken?.cancel();
        const pollingRequest = activePollingRequest;
        activePollingRequest = null;
        pollingRequest?.cancelToken?.cancel();
    });
    const hasActiveFilters = $derived(
        Boolean(committedSearch || committedRoomId || committedDate),
    );
    function committedMonitoringFilterKey() {
        return filterSnapshotKey({
            search: committedSearch,
            roomId: committedRoomId,
            date: committedDate,
            tab: committedTab,
        });
    }

    const monitoringPoll = usePoll(
        15000,
        () => {
            const request = {
                cancelToken: null,
                ownerRevision: monitoringRowsOwnerRevision,
                filterKey: committedMonitoringFilterKey(),
                contextKey: suggestionContextKey,
            };
            return {
                only: [
                    'sessions',
                    'active_sessions',
                    'history_sessions',
                    'telemetry',
                    'page_state',
                    'can_manage',
                    'thresholds',
                ],
                onCancelToken: (cancelToken) => {
                    request.cancelToken = cancelToken;
                    activePollingRequest = request;
                },
                onBeforeUpdate: (responsePage) => {
                    const responseState = responsePage.props?.page_state;
                    const securityTransition =
                        responsePage.component !==
                            'Examination/MonitoringIndex' ||
                        responseState === 'denied' ||
                        responseState === 'session_invalid';

                    if (securityTransition) {
                        return;
                    }

                    const stillOwnsRows =
                        request.ownerRevision === monitoringRowsOwnerRevision &&
                        request.filterKey === committedMonitoringFilterKey() &&
                        request.contextKey === suggestionContextKey &&
                        !searchInteractionActive &&
                        !filterVisitPending &&
                        !navigationVisitPending;

                    if (stillOwnsRows) {
                        return;
                    }

                    responsePage.props.sessions = sessions;
                    responsePage.props.active_sessions = active_sessions;
                    responsePage.props.history_sessions = history_sessions;
                    responsePage.url = page.url;
                },
                onFinish: () => {
                    if (activePollingRequest === request) {
                        activePollingRequest = null;
                    }
                },
            };
        },
        { autoStart: page_state !== 'denied', keepAlive: false, mode: 'rest' },
    );

    $effect(() => {
        if (page_state === 'denied') {
            pauseMonitoringPolling();
            return;
        }

        resumeMonitoringPollingIfAllowed();
    });

    function statusVariant(status) {
        if (status === 'published') return 'default';
        if (status === 'in_progress') return 'outline';
        if (status === 'completed') return 'secondary';
        if (status === 'cancelled') return 'destructive';
        return 'outline';
    }

    function statusLabel(status) {
        if (status === 'published') return 'Published';
        if (status === 'in_progress') return 'In Progress';
        if (status === 'completed') return 'Completed';
        if (status === 'cancelled') return 'Cancelled';
        return status;
    }

    function monitoringCardStatusLabel(status) {
        if (!browserNetworkAvailable) {
            if (status === 'in_progress') return 'STALE — last seen live';
            if (status === 'published') return 'STALE — last seen standby';
            return `STALE — last known ${status}`;
        }
        if (status === 'in_progress') return 'LIVE';
        if (status === 'published') return 'STANDBY';
        if (status === 'completed') return 'Completed';
        if (status === 'cancelled') return 'Cancelled';
        return status;
    }

    function monitoringCardStatusVariant(status) {
        if (!browserNetworkAvailable) return 'secondary';
        if (status === 'in_progress') return 'default';
        if (status === 'published') return 'outline';
        return statusVariant(status);
    }

    function hasUnrecordedAttendance(session) {
        if (session.status === 'cancelled') return false;
        if (session.submission_status === 'unrecorded_attendance') return true;

        const hasAttendanceData =
            session.present_count != null ||
            session.absent_count != null ||
            session.void_count != null;

        if (!hasAttendanceData) return false;

        const recordedCount =
            Number(session.present_count ?? 0) +
            Number(session.absent_count ?? 0) +
            Number(session.void_count ?? 0);

        return Number(session.assigned_count ?? 0) > recordedCount;
    }

    function formatTime(isoString) {
        if (!isoString) return '—';
        try {
            const d = new Date(isoString);
            return d.toLocaleTimeString('en-US', {
                hour: 'numeric',
                minute: '2-digit',
                hour12: true,
            });
        } catch {
            return '—';
        }
    }

    // Compute absence percentage against started/conducted sessions
    const scheduledForAbsence = $derived(
        telemetry?.conduct_scheduled ?? telemetry?.total_scheduled ?? 0,
    );
    const absencePercent = $derived(
        telemetry && scheduledForAbsence > 0
            ? Math.round((telemetry.absent_count / scheduledForAbsence) * 100)
            : 0,
    );
    const absenceWarning = $derived(
        absencePercent >= (thresholds.absence_warning_threshold_percent ?? 20),
    );

    const monitoringContext = $derived.by(() => {
        const isLv = browserNetworkAvailable && Boolean(telemetry?.is_live);
        const safetyKey = `${page_state}:${browserNetworkAvailable ? (isLv ? 'live' : 'stdby') : 'offline'}:${absenceWarning ? 'abswarn' : 'ok'}:${activeList.length}:${historyTotal}:${telemetry?.present_count ?? 0}:${telemetry?.absent_count ?? 0}`;

        return {
            entityKey: 'exam-monitoring',
            safetyKey,
            freshness: browserNetworkAvailable ? 'current' : 'stale',
            pageState: page_state,
            isLive: isLv,
            sessionsCount: activeList.length,
            historyCount: historyTotal,
            activeRoomsCount: telemetry?.active_rooms ?? 0,
            totalRoomsCount: telemetry?.total_rooms ?? 0,
            totalScheduled: telemetry?.total_scheduled ?? 0,
            presentCount: telemetry?.present_count ?? 0,
            absentCount: telemetry?.absent_count ?? 0,
            absencePercent,
            absenceWarning,
            canManage: Boolean(can_manage),
        };
    });

    const breadcrumbs = [{ label: 'Exam Monitoring' }];
</script>

<svelte:head><title>Exam Monitoring</title></svelte:head>

<StaffLayout {breadcrumbs}>
    <div class="min-w-0 space-y-6 p-4 sm:p-6">
        <!-- Header Bar -->
        <div
            class="border-border/60 flex flex-col justify-between gap-3 border-b pb-2 sm:flex-row sm:items-center"
        >
            <div>
                <div class="flex items-center gap-3">
                    <h1
                        class="text-foreground text-2xl font-bold tracking-tight sm:text-3xl"
                    >
                        Exam Monitoring
                    </h1>
                    <TourTriggerGlyph
                        tourKey="exam-monitoring"
                        onclick={() => (tourOpen = true)}
                    />
                    {#if !browserNetworkAvailable}
                        <span class="bg-amber-500/10 text-amber-800 border-amber-500/30 inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 font-mono text-xs font-semibold dark:text-amber-200">
                            OFFLINE · DATA STALE
                        </span>
                    {:else if telemetry?.is_live}
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full border border-emerald-500/20 bg-emerald-500/10 px-2.5 py-0.5 font-mono text-xs font-bold text-emerald-600 motion-safe:animate-pulse dark:text-emerald-400"
                        >
                            <span class="h-2 w-2 rounded-full bg-emerald-500"
                            ></span> LIVE
                        </span>
                    {:else}
                        <span
                            class="bg-muted text-muted-foreground border-border inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 font-mono text-xs font-semibold"
                        >
                            STANDBY
                        </span>
                    {/if}
                </div>
                <p class="text-muted-foreground mt-1 text-xs">
                    Testing-day conduct hub: live room status, examinee
                    attendance intake, and session reconciliation.
                </p>
            </div>

            <div class="flex items-center gap-3">
                <HeaderActions>
                    {#snippet primaryAction()}
                        <!-- Conduct & Policy Popover -->
                        <Popover.Root>
                            <Popover.Trigger>
                                {#snippet child({ props })}
                                    <Button
                                        {...props}
                                        variant="outline"
                                        size="sm"
                                        class="hover:bg-muted h-8 gap-1.5 font-mono text-xs font-medium transition-colors"
                                        title="Conduct Policies & Cadence"
                                    >
                                        <Settings2 class="size-3.5" />
                                        <span>Conduct Policies</span>
                                    </Button>
                                {/snippet}
                            </Popover.Trigger>
                            <Popover.Content
                                class="w-84 space-y-4 p-4 font-mono text-xs sm:w-96"
                                align="end"
                            >
                                <div
                                    class="border-border/60 space-y-1 border-b pb-2"
                                >
                                    <div
                                        class="flex items-center justify-between"
                                    >
                                        <h3
                                            class="text-foreground flex items-center gap-1.5 text-sm font-semibold"
                                        >
                                            <Settings2
                                                class="text-primary size-4"
                                            />
                                            <span>Conduct Policies</span>
                                        </h3>
                                        <Badge
                                            variant="outline"
                                            class="font-mono text-[10px]"
                                            >{thresholds.cycle_label ??
                                                'Active Cycle'}</Badge
                                        >
                                    </div>
                                    <p
                                        class="text-muted-foreground font-sans text-[11px]"
                                    >
                                        Campus testing-day thresholds and
                                        communication cadence.
                                    </p>
                                </div>

                                <!-- Testing-Day Conduct Rules -->
                                <div class="space-y-2">
                                    <div
                                        class="text-muted-foreground text-[10px] font-bold tracking-wider uppercase"
                                    >
                                        Testing-Day Conduct Rules
                                    </div>
                                    <div class="grid grid-cols-3 gap-2">
                                        <div
                                            class="border-border bg-muted/30 rounded-lg border p-2.5"
                                        >
                                            <div
                                                class="text-muted-foreground text-[10px]"
                                            >
                                                Check-in Lead
                                            </div>
                                            <div
                                                class="text-foreground mt-0.5 text-sm font-bold"
                                            >
                                                {thresholds.door_checkin_lead_minutes ??
                                                    30}m
                                            </div>
                                            <div
                                                class="text-muted-foreground mt-0.5 font-sans text-[9px]"
                                            >
                                                Door opens early
                                            </div>
                                        </div>
                                        <div
                                            class="border-border bg-muted/30 rounded-lg border p-2.5"
                                        >
                                            <div
                                                class="text-muted-foreground text-[10px]"
                                            >
                                                Auto-Close
                                            </div>
                                            <div
                                                class="text-foreground mt-0.5 text-sm font-bold"
                                            >
                                                {thresholds.auto_close_grace_minutes ??
                                                    15}m
                                            </div>
                                            <div
                                                class="text-muted-foreground mt-0.5 font-sans text-[9px]"
                                            >
                                                Grace after end
                                            </div>
                                        </div>
                                        <div
                                            class="border-border bg-muted/30 rounded-lg border p-2.5"
                                        >
                                            <div
                                                class="text-muted-foreground text-[10px]"
                                            >
                                                Absence Alert
                                            </div>
                                            <div
                                                class="text-foreground mt-0.5 text-sm font-bold"
                                            >
                                                &ge; {thresholds.absence_warning_threshold_percent ??
                                                    20}%
                                            </div>
                                            <div
                                                class="text-muted-foreground mt-0.5 font-sans text-[9px]"
                                            >
                                                Triggers alert
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Candidate Reminders -->
                                <div
                                    class="border-border/60 space-y-2 border-t pt-3"
                                >
                                    <div
                                        class="flex items-center justify-between"
                                    >
                                        <span
                                            class="text-muted-foreground text-[10px] font-bold tracking-wider uppercase"
                                            >Candidate Reminders</span
                                        >
                                        <span
                                            class="text-muted-foreground text-[10px]"
                                            >Cycle default</span
                                        >
                                    </div>
                                    <div
                                        class="border-border bg-muted/30 space-y-1 rounded-lg border p-2.5"
                                    >
                                        <div class="text-foreground font-bold">
                                            Offsets: {(
                                                thresholds.reminder_windows ?? [
                                                    '72h',
                                                    '24h',
                                                    '2h',
                                                    '1h',
                                                ]
                                            ).join(', ')}
                                        </div>
                                        <p
                                            class="text-muted-foreground font-sans text-[10px] leading-tight"
                                        >
                                            Dispatched before scheduled start.
                                            Reminder policies freeze when an
                                            exam session is published.
                                        </p>
                                    </div>
                                </div>

                                <!-- Deep Links to Setup -->
                                {#if thresholds.notifications_setup_url || thresholds.cycle_notifications_url}
                                    <div
                                        class="border-border/60 space-y-1.5 border-t pt-3 font-sans"
                                    >
                                        {#if thresholds.notifications_setup_url}
                                            <Link
                                                href={thresholds.notifications_setup_url}
                                                class="hover:bg-muted/50 text-foreground group flex items-center justify-between rounded-lg p-2 text-xs font-medium transition-colors"
                                            >
                                                <div
                                                    class="flex items-center gap-2"
                                                >
                                                    <Inbox
                                                        class="text-primary size-3.5"
                                                    />
                                                    <span
                                                        >Notification Operations
                                                        Hub</span
                                                    >
                                                </div>
                                                <ExternalLink
                                                    class="text-muted-foreground group-hover:text-foreground size-3 transition-colors"
                                                />
                                            </Link>
                                        {/if}
                                        {#if thresholds.cycle_notifications_url}
                                            <Link
                                                href={thresholds.cycle_notifications_url}
                                                class="hover:bg-muted/50 text-foreground group flex items-center justify-between rounded-lg p-2 text-xs font-medium transition-colors"
                                            >
                                                <div
                                                    class="flex items-center gap-2"
                                                >
                                                    <Layers
                                                        class="text-primary size-3.5"
                                                    />
                                                    <span
                                                        >Edit Cycle
                                                        Communication Policy</span
                                                    >
                                                </div>
                                                <ExternalLink
                                                    class="text-muted-foreground group-hover:text-foreground size-3 transition-colors"
                                                />
                                            </Link>
                                        {/if}
                                    </div>
                                {/if}
                            </Popover.Content>
                        </Popover.Root>
                    {/snippet}
                </HeaderActions>

                {#if pollingStatus}
                    <div
                        class="text-muted-foreground flex items-center gap-2 font-mono text-[11px]"
                    >
                        <span
                            class="inline-flex h-2 w-2 rounded-full bg-amber-500"
                        ></span>
                        <span>{pollingStatus}</span>
                    </div>
                {/if}
            </div>
        </div>

        <!-- Persistent Campus Telemetry HUD (Never hides on 0 active rooms) -->
        {#if telemetry}
            <div
                class="grid grid-cols-2 gap-3 font-mono text-xs sm:grid-cols-4"
                data-tour="monitoring-telemetry"
            >
                <div
                    class="craft-card-depth border-border/70 space-y-1 rounded-xl border p-3.5 text-center"
                >
                    <div
                        class="text-muted-foreground flex items-center justify-center gap-1 text-[10px] font-semibold tracking-wider uppercase"
                    >
                        <School class="text-muted-foreground size-3" /> Active Rooms
                    </div>
                    <div
                        class="text-foreground text-2xl font-extrabold tabular-nums"
                    >
                        {telemetry.active_rooms} / {telemetry.total_rooms}
                    </div>
                    <div class="text-muted-foreground font-sans text-[10px]">
                        {telemetry.active_sessions ?? 0} active · {telemetry.completed_sessions ??
                            0} completed
                    </div>
                </div>

                <div
                    class="craft-card-depth border-border/70 space-y-1 rounded-xl border p-3.5 text-center"
                >
                    <div
                        class="text-muted-foreground flex items-center justify-center gap-1 text-[10px] font-semibold tracking-wider uppercase"
                    >
                        <Users class="text-muted-foreground size-3" /> Scheduled
                    </div>
                    <div
                        class="text-foreground text-2xl font-extrabold tabular-nums"
                    >
                        {telemetry.total_scheduled}
                    </div>
                    <div
                        class="text-muted-foreground truncate font-sans text-[10px]"
                    >
                        {thresholds.cycle_label ?? 'Academic cycle'}
                    </div>
                </div>

                <div
                    class="craft-card-depth border-border/70 space-y-1 rounded-xl border p-3.5 text-center"
                >
                    <div
                        class="text-muted-foreground flex items-center justify-center gap-1 text-[10px] font-semibold tracking-wider uppercase"
                    >
                        <CheckCircle2 class="size-3 text-emerald-500" /> Present
                    </div>
                    <div
                        class="text-2xl font-extrabold text-emerald-600 tabular-nums dark:text-emerald-400"
                    >
                        {telemetry.present_count}
                    </div>
                    <div class="text-muted-foreground font-sans text-[10px]">
                        {#if (telemetry.conduct_scheduled ?? telemetry.total_scheduled) > 0}
                            {Math.round(
                                (telemetry.present_count /
                                    (telemetry.conduct_scheduled ??
                                        telemetry.total_scheduled)) *
                                    100,
                            )}% turnout
                        {:else}
                            0% turnout
                        {/if}
                    </div>
                </div>

                <div
                    class="craft-card-depth border-border/70 space-y-1 rounded-xl border p-3.5 text-center {absenceWarning
                        ? 'border-rose-500/40 bg-rose-500/5 ring-1 ring-rose-500/20'
                        : ''}"
                >
                    <div
                        class="text-muted-foreground flex items-center justify-center gap-1 text-[10px] font-semibold tracking-wider uppercase"
                    >
                        <UserX
                            class="size-3 {absenceWarning
                                ? 'text-rose-500'
                                : 'text-muted-foreground'}"
                        /> Absent
                        {#if absenceWarning}
                            <AlertTriangle
                                class="size-3 animate-pulse text-rose-500"
                            />
                        {/if}
                    </div>
                    <div
                        class="text-2xl font-extrabold {absenceWarning
                            ? 'text-rose-500'
                            : 'text-foreground'} tabular-nums"
                    >
                        {telemetry.absent_count}
                    </div>
                    {#if absenceWarning}
                        <div class="text-[9px] font-bold text-rose-500">
                            {absencePercent}% — threshold exceeded
                        </div>
                    {:else}
                        <div
                            class="text-muted-foreground font-sans text-[10px]"
                        >
                            {telemetry.void_count
                                ? `${telemetry.void_count} voided`
                                : 'Within policy'}
                        </div>
                    {/if}
                </div>

            </div>
        {/if}

        <!-- Access Denied State -->
        {#if page_state === 'denied'}
            <div class="border-border bg-card rounded-xl border p-6">
                <p class="text-muted-foreground text-sm">
                    You do not have permission to view exam monitoring.
                </p>
            </div>
        {:else}
            <!-- Segmented Navigation Bar -->
            <div
                class="sticky top-0 z-20 backdrop-blur-md bg-background/80 py-2 space-y-3"
            >
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="inline-flex min-h-[44px] items-center gap-2 rounded-lg px-3 py-1.5 text-xs font-semibold transition-all {activeTab ===
                        'active'
                            ? 'bg-primary text-primary-foreground shadow-xs'
                            : 'text-muted-foreground hover:text-foreground hover:bg-muted/50'}"
                        onclick={() => selectTab('active')}
                    >
                        <Activity class="size-3.5" />
                        <span>Active Conduct</span>
                        <span
                            class="py-0.2 ml-0.5 rounded-full px-1.5 font-mono text-[10px] {activeTab ===
                            'active'
                                ? 'bg-primary-foreground/20 text-primary-foreground'
                                : 'bg-muted text-muted-foreground'}"
                        >
                            {activeList.length}
                        </span>
                    </button>

                    <button
                        type="button"
                        class="inline-flex min-h-[44px] items-center gap-2 rounded-lg px-3 py-1.5 text-xs font-semibold transition-all {activeTab ===
                        'history'
                            ? 'bg-primary text-primary-foreground shadow-xs'
                            : 'text-muted-foreground hover:text-foreground hover:bg-muted/50'}"
                        onclick={() => selectTab('history')}
                    >
                        <Clock class="size-3.5" />
                        <span>Conduct History</span>
                        <span
                            class="py-0.2 ml-0.5 rounded-full px-1.5 font-mono text-[10px] {activeTab ===
                            'history'
                                ? 'bg-primary-foreground/20 text-primary-foreground'
                                : 'bg-muted text-muted-foreground'}"
                        >
                            {historyTotal}
                        </span>
                    </button>
                </div>
                <div role="status" class="mb-3 flex items-center gap-2 rounded-lg border px-3 py-2 text-xs {browserNetworkAvailable ? 'border-emerald-500/30 bg-emerald-500/5 text-emerald-700 dark:text-emerald-300' : 'border-amber-500/40 bg-amber-500/10 text-amber-800 dark:text-amber-200'}">
                    {#if browserNetworkAvailable}
                        <span class="font-semibold">Browser reports network available</span>
                    {:else}
                        <span class="font-semibold">No network connection — monitoring data may be stale</span>
                    {/if}
                    <span class="text-muted-foreground">Browser connectivity does not confirm server availability.</span>
                </div>

                <!-- Filter & Search Toolbar -->
                <!-- Full-width search with secondary conduct filters -->
                <div class="w-full space-y-3 md:flex md:flex-wrap md:items-center md:gap-3 md:space-y-0">
                    <div class="w-full md:min-w-[280px] md:flex-1">
                        <label id="monitor-search-label" class="mb-1 block text-xs font-medium text-muted-foreground" for="monitor-search">Search</label>
                        <div class="relative">
                            <Search class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            bind:ref={searchInput}
                            id="monitor-search"
                            type="text"
                            placeholder="Search code or room..."
                            class="h-11 min-h-[44px] w-full border border-input bg-background pr-24 pl-10 text-foreground shadow-xs dark:bg-input/30 md:h-10"
                            bind:value={draftSearch}
                            oninput={handleSearchInput}
                            onfocus={handleSearchFocus}
                            onblur={handleSearchBlur}
                            onkeydown={handleSearchKeydown}
                            role="combobox"
                            aria-labelledby="monitor-search-label"
                            aria-autocomplete="list"
                            aria-expanded={suggestionDropdown?.isExpanded() ?? false}
                            aria-controls={suggestionDropdown?.listboxIdentifier()}
                            aria-activedescendant={suggestionDropdown?.activeDescendantId()}
                        />
                        <div class="absolute top-1/2 right-1.5 flex -translate-y-1/2 items-center gap-1">
                            {#if hasSearchToClear}
                                <button type="button" class="grid size-8 place-items-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:outline-none" aria-label="Clear search" title="Clear search" onclick={clearSearch}>
                                    <X class="size-4" aria-hidden="true" />
                                </button>
                            {/if}
                            <Button
                                type="button"
                                size="sm"
                                class="h-8 px-2.5 text-xs font-semibold"
                                onclick={() => commitFilters(draftSearch, { syncDraftOnSuccess: true })}
                                title="Search"
                                aria-label="Search"
                            >
                                Search
                            </Button>
                        </div>
                        <SearchSuggestionDropdown
                            bind:this={suggestionDropdown}
                            search={draftSearch}
                            academicCycleId={active_academic_cycle?.id}
                            domainContext="monitoring"
                            contextKey={suggestionContextKey}
                            tab={activeTab}
                            date={selectedDate}
                            roomId={selectedRoomId}
                            inputRef={searchInput}
                            onactivate={handleSearchActivation}
                            oninteractionchange={handleSearchInteractionChange}
                            class="monitoring-search-suggestions"
                        />
                        </div>
                    </div>
                    <div class="flex items-center justify-between gap-2 md:hidden">
                        <button
                            type="button"
                            class="flex min-h-[44px] w-full items-center justify-between rounded-xl border border-input bg-background px-3 text-sm font-semibold text-foreground shadow-xs dark:bg-input/30"
                            aria-expanded={mobileFiltersOpen}
                            aria-controls="monitoring-mobile-filters"
                            onclick={() => (mobileFiltersOpen = !mobileFiltersOpen)}
                        >
                            <span class="min-w-0 flex-1 truncate text-left">Filters ({activeCount})</span>
                            <ChevronDown class="ml-1.5 size-4 shrink-0 transition-transform {mobileFiltersOpen ? 'rotate-180' : ''}" />
                        </button>
                    </div>
                    <div
                        id="monitoring-mobile-filters"
                        class={`${mobileFiltersOpen ? 'flex' : 'hidden md:flex'} flex-wrap items-end gap-2 pt-2 md:pt-0`}
                    >
                        {#if rooms.length > 0}
                            <div class="min-w-0 flex-1 md:flex-none">
                                <label class="mb-1 block text-xs font-medium text-muted-foreground" for="monitor-room">Room</label>
                                <select
                                    id="monitor-room"
                                    aria-label="Room"
                                    class="h-10 min-h-[40px] w-full rounded-md border border-input bg-background px-3 pr-8 text-xs text-foreground shadow-xs dark:bg-input/30 md:w-[220px]"
                                    bind:value={selectedRoomId}
                                    onchange={applyRoomFilter}
                                >
                                    <option value="">All Rooms</option>
                                    {#each rooms as room (room.id)}
                                        <option value={String(room.id)}>{room.name ?? room.code}</option>
                                    {/each}
                                </select>
                            </div>
                        {/if}
                        <div class="min-w-0 flex-1 md:flex-none">
                            <label class="mb-1 block text-xs font-medium text-muted-foreground" for="monitor-status">Status</label>
                            <Select.Root type="single" bind:value={filterStatus} onValueChange={(value) => filterStatus = value ?? ''}>
                                <Select.Trigger id="monitor-status" aria-label="Status" class="h-10 min-h-[40px] w-full border border-input bg-background text-xs text-foreground shadow-xs dark:bg-input/30 md:w-[200px]">{filterStatus ? statusLabel(filterStatus) : 'All statuses'}</Select.Trigger>
                                <Select.Content>
                                    <Select.Item value="" label="All statuses">All statuses</Select.Item>
                                    <Select.Item value="published" label="Published">Published</Select.Item>
                                    <Select.Item value="in_progress" label="In Progress">In Progress</Select.Item>
                                    <Select.Item value="completed" label="Completed">Completed</Select.Item>
                                    <Select.Item value="cancelled" label="Cancelled">Cancelled</Select.Item>
                                </Select.Content>
                            </Select.Root>
                        </div>
                        <div class="min-w-0 flex-1 md:flex-none">
                            <label class="mb-1 block text-xs font-medium text-muted-foreground" for="monitor-date">Date</label>
                            <input id="monitor-date" type="date" class="border-input bg-background text-foreground focus-visible:ring-ring h-10 min-h-[40px] w-full rounded-md border px-3 text-xs shadow-xs focus-visible:ring-2 focus-visible:outline-none dark:bg-input/30 md:w-auto" bind:value={selectedDate} onchange={applyDateFilter} title="Filter by session date" />
                        </div>
                        {#if hasActiveFilters || filterStatus}
                            <Button variant="outline" class="h-10 min-h-[40px] flex-1 px-4 text-xs md:flex-none" onclick={clearMonitoringControls}>Reset</Button>
                        {/if}
                    </div>
                    {#if searchIsDirty}<p role="status" aria-live="polite" class="text-xs text-muted-foreground md:basis-full">Press Enter to filter. Currently showing {committedSearch ? `“${committedSearch}”` : 'all sessions'}.</p>{/if}
                    {#if committedSearch && !searchIsDirty}<p class="text-xs text-muted-foreground md:basis-full" data-applied-search={committedSearch}>Filtered: “{committedSearch}”</p>{/if}
                    {#if filterVisitPending}<p role="status" aria-live="polite" class="text-xs text-muted-foreground md:basis-full">Updating monitoring results…</p>{/if}
                    {#if filterError}<p role="alert" class="basis-full text-xs text-destructive">{filterError}</p>{/if}
                </div>
            </div>

            <!-- Tab View 1: Active Conduct -->
            {#if activeTab === 'active'}
                {#if activeRows.length > 0}
                    <div data-tour="monitoring-checkin">
                        <SwitchableListView bind:viewMode overflow="auto">
                            {#snippet table()}
                                <Table.Root
                                    class="w-full min-w-[720px] text-sm"
                                >
                                    <Table.Header class="bg-muted/40 border-b border-border">
                                        <Table.Row class="border-b border-border/40 hover:bg-muted/30 transition-colors">
                                        <SortableTableHead label="Date &amp; Time" ariaLabel="Sort by date and time" direction={columnSortDirection('date')} onclick={() => sortColumn('date')} class="px-2 py-1" />
                                        <SortableTableHead label="Room" ariaLabel="Sort by room" direction={columnSortDirection('room')} onclick={() => sortColumn('room')} class="px-2 py-1" />
                                        <SortableTableHead label="Code" direction={columnSortDirection('code')} onclick={() => sortColumn('code')} class="px-2 py-1" />
                                        <SortableTableHead label="Status" direction={columnSortDirection('status')} onclick={() => sortColumn('status')} class="px-2 py-1" />
                                        <SortableTableHead label="Assigned" direction={columnSortDirection('assigned')} onclick={() => sortColumn('assigned')} ariaLabel="Sort by assigned count" class="min-h-[44px] px-4 py-3" />
                                        <Table.Head class="min-h-[44px] text-center">Actions</Table.Head>
 
                                        </Table.Row>
                                    </Table.Header>
                                    <Table.Body>
                                        {#each activeRows as session (session.id)}
                                            <Table.Row
                                                class="border-b border-border/40 hover:bg-muted/30 transition-colors cursor-pointer"
                                                onclick={(e) => {
                                                    if (
                                                        e.target.closest(
                                                            '[data-action-cell]',
                                                        )
                                                    )
                                                        return;
                                                    visitMonitoringSession(
                                                        `/exams/sessions/${session.id}/monitoring`,
                                                    );
                                                }}
                                            >
                                                <Table.Cell class="font-sans">
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
                                                    >{session.room?.name ??
                                                        session.room?.code ??
                                                        '—'}</Table.Cell
                                                >
                                                <Table.Cell
                                                    class="font-mono font-medium tracking-tight"
                                                    >{session.code}</Table.Cell
                                                >
                                                <Table.Cell>
                                                    <Badge
                                                        variant={monitoringCardStatusVariant(session.status)}
                                                        >{monitoringCardStatusLabel(session.status)}</Badge
                                                    >
                                                </Table.Cell>
                                                <Table.Cell
                                                    >{session.assigned_count ??
                                                        0}{#if session.room?.capacity}
                                                        / {session.room
                                                            .capacity}{/if}</Table.Cell
                                                >
                                                <Table.Cell
                                                    class="text-center"
                                                    data-action-cell
                                                >
                                                    <Link
                                                        href={`/exams/sessions/${session.id}/monitoring`}
                                                        onclick={prepareSessionNavigation}
                                                    >
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            class="h-8 px-2 text-xs font-semibold"
                                                        >
                                                            <Activity
                                                                class="mr-1.5 size-3.5"
                                                            />
                                                            Open Station
                                                        </Button>
                                                    </Link>
                                                </Table.Cell>
                                            </Table.Row>
                                        {/each}
                                    </Table.Body>
                                </Table.Root>
                            {/snippet}
                            {#snippet cards()}
                                <ul
                                    class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3"
                                    role="list"
                                >
                                    {#each activeRows as session (session.id)}
                                        <OperationalCard
                                            id={session.id}
                                            title={session.room_name ||
                                                session.code}
                                            subtitle={`${session.starts_at || session.date || ''} · Ref ${session.code}`}
                                            bind:expanded={
                                                expandedCards[session.id]
                                            }
                                        >
                                            {#snippet status()}
                                                <Badge
                                                    variant={monitoringCardStatusVariant(
                                                        session.status,
                                                    )}
                                                    class="font-mono text-[10px] font-bold uppercase"
                                                >
                                                    {monitoringCardStatusLabel(
                                                        session.status,
                                                    )}
                                                </Badge>
                                            {/snippet}

                                            {#snippet summary()}
                                                <div
                                                    class="flex flex-wrap items-center gap-2"
                                                >
                                                    {#if session.present_count != null}
                                                        <span
                                                            class="text-foreground font-medium"
                                                        >
                                                            Turnout: {session.present_count}
                                                            / {session.assigned_count ??
                                                                0}
                                                            {#if session.turnout_percent != null}
                                                                <span
                                                                    class="text-muted-foreground"
                                                                    >({session.turnout_percent}%)</span
                                                                >
                                                            {/if}
                                                        </span>
                                                    {:else}
                                                        <span
                                                            class="text-foreground font-medium"
                                                            >{session.assigned_count ??
                                                                0} assigned</span
                                                        >
                                                    {/if}
                                                    {#if hasUnrecordedAttendance(session)}
                                                        <Badge
                                                            variant="outline"
                                                            class="border-rose-500/40 bg-rose-500/5 text-[10px] text-rose-600 dark:text-rose-400"
                                                        >
                                                            <AlertTriangle
                                                                class="mr-1 size-3"
                                                            />
                                                            Attendance unrecorded
                                                        </Badge>
                                                    {/if}
                                                </div>
                                            {/snippet}

                                            {#snippet details()}
                                                <div
                                                    class="grid grid-cols-1 gap-2 sm:grid-cols-2"
                                                >
                                                    <div class="space-y-0.5">
                                                        <p
                                                            class="text-muted-foreground text-[10px] font-semibold tracking-wide uppercase"
                                                        >
                                                            Scheduled
                                                        </p>
                                                        <p
                                                            class="text-foreground"
                                                        >
                                                            {session.date
                                                                ? formatDate(
                                                                      session.date,
                                                                  )
                                                                : '—'}
                                                            {#if session.start_time}
                                                                · {formatTimeRange(
                                                                    session.start_time,
                                                                    session.end_time,
                                                                )}
                                                            {/if}
                                                        </p>
                                                    </div>
                                                    {#if session.closed_at}
                                                        <div
                                                            class="space-y-0.5"
                                                        >
                                                            <p
                                                                class="text-muted-foreground text-[10px] font-semibold tracking-wide uppercase"
                                                            >
                                                                Closed
                                                            </p>
                                                            <p
                                                                class="text-foreground"
                                                            >
                                                                {formatDate(
                                                                    session.closed_at,
                                                                )} · {formatTime(
                                                                    session.closed_at,
                                                                )}
                                                            </p>
                                                        </div>
                                                    {/if}
                                                    {#if session.absent_count != null}
                                                        <div
                                                            class="space-y-0.5"
                                                        >
                                                            <p
                                                                class="text-muted-foreground text-[10px] font-semibold tracking-wide uppercase"
                                                            >
                                                                Absent
                                                            </p>
                                                            <p
                                                                class="text-foreground"
                                                            >
                                                                {session.absent_count}
                                                            </p>
                                                        </div>
                                                    {/if}
                                                    {#if session.void_count != null}
                                                        <div
                                                            class="space-y-0.5"
                                                        >
                                                            <p
                                                                class="text-muted-foreground text-[10px] font-semibold tracking-wide uppercase"
                                                            >
                                                                Voided
                                                            </p>
                                                            <p
                                                                class="text-foreground"
                                                            >
                                                                {session.void_count}
                                                            </p>
                                                        </div>
                                                    {/if}
                                                    <div class="space-y-0.5">
                                                        <p
                                                            class="text-muted-foreground text-[10px] font-semibold tracking-wide uppercase"
                                                        >
                                                            Capacity
                                                        </p>
                                                        <p
                                                            class="text-foreground"
                                                        >
                                                            {session.assigned_count ??
                                                                0} assigned / {session
                                                                .room
                                                                ?.capacity ??
                                                                '—'} seats
                                                        </p>
                                                    </div>
                                                </div>
                                            {/snippet}

                                            {#snippet actions()}
                                                <Link
                                                    href={`/exams/sessions/${session.id}/monitoring`}
                                                    class="flex-1"
                                                    onclick={prepareSessionNavigation}
                                                >
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        class="min-h-10 w-full font-semibold"
                                                    >
                                                        <Activity
                                                            class="mr-1.5 size-3.5"
                                                        />
                                                        Open Station
                                                    </Button>
                                                </Link>
                                            {/snippet}
                                        </OperationalCard>
                                    {/each}
                                </ul>
                            {/snippet}
                        </SwitchableListView>
                    </div>
                {:else}
                    <!-- Standby HUD (When zero active rooms are live) -->
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
                                No Sessions in Active Conduct
                            </h3>
                            <p
                                class="text-muted-foreground text-xs leading-relaxed"
                            >
                                {#if can_manage}
                                    All examination rooms are currently on
                                    standby for {thresholds.cycle_label ??
                                        'the active academic cycle'}.
                                    {#if historyTotal > 0}
                                        You can review {historyTotal} completed session
                                        records in Conduct History.
                                    {:else}
                                        Publish an exam session under Exam
                                        Scheduling to initiate live testing-day
                                        monitoring.
                                    {/if}
                                {:else}
                                    Test Administrators can open any session
                                    they are authorized to monitor.
                                {/if}
                            </p>
                        </div>
                        <div
                            class="flex flex-wrap items-center justify-center gap-3 pt-2"
                        >
                            {#if historyTotal > 0}
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onclick={() => selectTab('history')}
                                >
                                    <Clock class="mr-1.5 size-3.5" />
                                    <span
                                        >View Conduct History ({historyTotal})</span
                                    >
                                </Button>
                            {/if}
                            {#if can_manage}
                                <Link
                                    href="/exams/sessions"
                                    onclick={prepareSessionNavigation}
                                >
                                    <Button variant="default" size="sm">
                                        <Calendar class="mr-1.5 size-3.5" />
                                        <span>Go to Exam Scheduling</span>
                                    </Button>
                                </Link>
                            {/if}
                        </div>
                    </div>
                {/if}
            {:else}
                <!-- Tab View 2: Conduct History -->
                {#if historyRows.length > 0}
                    <SwitchableListView bind:viewMode overflow="auto">
                        {#snippet table()}
                            <Table.Root class="w-full min-w-[760px] text-sm">
                                <Table.Header class="bg-muted/40 border-b border-border">
                                    <Table.Row class="border-b border-border/40 hover:bg-muted/30 transition-colors">
                                        <SortableTableHead label="Date &amp; Time" ariaLabel="Sort by date and time" direction={columnSortDirection('date')} onclick={() => sortColumn('date')} class="px-2 py-1" />
                                        <SortableTableHead label="Room" ariaLabel="Sort by room" direction={columnSortDirection('room')} onclick={() => sortColumn('room')} class="px-2 py-1" />
                                        <SortableTableHead label="Code" direction={columnSortDirection('code')} onclick={() => sortColumn('code')} class="px-2 py-1" />
                                        <SortableTableHead label="Status" direction={columnSortDirection('status')} onclick={() => sortColumn('status')} class="px-2 py-1" />
                                        <SortableTableHead label="Turnout" direction={columnSortDirection('turnout')} onclick={() => sortColumn('turnout')} ariaLabel="Sort by turnout count" class="min-h-[44px] px-4 py-3" />
                                        <Table.Head class="min-h-[44px] text-center">Actions</Table.Head>
                                    </Table.Row>
                                </Table.Header>
                                <Table.Body>
                                    {#each historyRows as session (session.id)}
                                        <Table.Row
                                            class="border-b border-border/40 hover:bg-muted/30 transition-colors cursor-pointer"
                                            onclick={(e) => {
                                                if (
                                                    e.target.closest(
                                                        '[data-action-cell]',
                                                    )
                                                )
                                                    return;
                                                visitMonitoringSession(
                                                    `/exams/sessions/${session.id}/monitoring`,
                                                );
                                            }}
                                        >
                                            <Table.Cell class="font-sans">
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
                                                    {#if session.closed_at}
                                                        <div
                                                            class="text-muted-foreground font-mono text-[10px]"
                                                        >
                                                            Closed: {formatTime(
                                                                session.closed_at,
                                                            )}
                                                        </div>
                                                    {/if}
                                                </div>
                                            </Table.Cell>
                                            <Table.Cell
                                                >{session.room?.name ??
                                                    session.room?.code ??
                                                    '—'}</Table.Cell
                                            >
                                            <Table.Cell
                                                class="font-mono font-medium tracking-tight"
                                                >{session.code}</Table.Cell
                                            >
                                            <Table.Cell>
                                                <Badge
                                                    variant={monitoringCardStatusVariant(session.status)}
                                                    >{monitoringCardStatusLabel(session.status)}</Badge
                                                >
                                            </Table.Cell>
                                            <Table.Cell>
                                                <div class="font-mono text-xs">
                                                    <span
                                                        class="font-semibold text-emerald-600 dark:text-emerald-400"
                                                        >{session.present_count ??
                                                            0}</span
                                                    >
                                                    <span
                                                        class="text-muted-foreground"
                                                        >/{session.assigned_count ??
                                                            0} ({session.turnout_percent ??
                                                            0}%)</span
                                                    >
                                                </div>
                                                {#if (session.absent_count ?? 0) > 0}
                                                    <div
                                                        class="font-mono text-[10px] text-rose-500"
                                                    >
                                                        {session.absent_count} absent
                                                    </div>
                                                {/if}
                                            </Table.Cell>
                                            <Table.Cell
                                                class="text-center"
                                                data-action-cell
                                            >
                                                <Link
                                                    href={`/exams/sessions/${session.id}/monitoring`}
                                                    onclick={prepareSessionNavigation}
                                                >
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        class="h-8 px-2 text-xs font-semibold"
                                                    >
                                                        <Eye
                                                            class="mr-1.5 size-3.5"
                                                        />
                                                        Review Roster
                                                    </Button>
                                                </Link>
                                            </Table.Cell>
                                        </Table.Row>
                                    {/each}
                                </Table.Body>
                            </Table.Root>
                        {/snippet}
                        {#snippet cards()}
                            <ul
                                class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3"
                                role="list"
                            >
                                {#each historyRows as session (session.id)}
                                    <OperationalCard
                                        id={session.id}
                                        title={session.room_name ||
                                            session.code}
                                        subtitle={`${session.starts_at || session.date || ''} · Ref ${session.code}`}
                                        bind:expanded={
                                            expandedCards[session.id]
                                        }
                                    >
                                        {#snippet status()}
                                            <Badge
                                                variant={monitoringCardStatusVariant(
                                                    session.status,
                                                )}
                                                class="font-mono text-[10px] font-bold uppercase"
                                            >
                                                {monitoringCardStatusLabel(
                                                    session.status,
                                                )}
                                            </Badge>
                                        {/snippet}

                                        {#snippet summary()}
                                            <div
                                                class="flex flex-wrap items-center gap-2"
                                            >
                                                <span
                                                    class="text-foreground font-medium"
                                                >
                                                    Turnout: {session.present_count ??
                                                        0} / {session.assigned_count ??
                                                        0}
                                                    <span
                                                        class="text-muted-foreground"
                                                        >({session.turnout_percent ??
                                                            0}%)</span
                                                    >
                                                </span>
                                                {#if hasUnrecordedAttendance(session)}
                                                    <Badge
                                                        variant="outline"
                                                        class="border-rose-500/40 bg-rose-500/5 text-[10px] text-rose-600 dark:text-rose-400"
                                                    >
                                                        <AlertTriangle
                                                            class="mr-1 size-3"
                                                        />
                                                        Attendance unrecorded
                                                    </Badge>
                                                {/if}
                                            </div>
                                        {/snippet}

                                        {#snippet details()}
                                            <div
                                                class="grid grid-cols-1 gap-2 sm:grid-cols-2"
                                            >
                                                <div class="space-y-0.5">
                                                    <p
                                                        class="text-muted-foreground text-[10px] font-semibold tracking-wide uppercase"
                                                    >
                                                        Scheduled
                                                    </p>
                                                    <p class="text-foreground">
                                                        {session.date
                                                            ? formatDate(
                                                                  session.date,
                                                              )
                                                            : '—'}
                                                        {#if session.start_time}
                                                            · {formatTimeRange(
                                                                session.start_time,
                                                                session.end_time,
                                                            )}
                                                        {/if}
                                                    </p>
                                                </div>
                                                <div class="space-y-0.5">
                                                    <p
                                                        class="text-muted-foreground text-[10px] font-semibold tracking-wide uppercase"
                                                    >
                                                        Closed
                                                    </p>
                                                    <p class="text-foreground">
                                                        {#if session.closed_at}
                                                            {formatDate(
                                                                session.closed_at,
                                                            )} · {formatTime(
                                                                session.closed_at,
                                                            )}
                                                        {:else}
                                                            —
                                                        {/if}
                                                    </p>
                                                </div>
                                                <div class="space-y-0.5">
                                                    <p
                                                        class="text-muted-foreground text-[10px] font-semibold tracking-wide uppercase"
                                                    >
                                                        Absent
                                                    </p>
                                                    <p class="text-foreground">
                                                        {session.absent_count ??
                                                            0}
                                                    </p>
                                                </div>
                                                <div class="space-y-0.5">
                                                    <p
                                                        class="text-muted-foreground text-[10px] font-semibold tracking-wide uppercase"
                                                    >
                                                        Voided
                                                    </p>
                                                    <p class="text-foreground">
                                                        {session.void_count ??
                                                            0}
                                                    </p>
                                                </div>
                                                <div class="space-y-0.5">
                                                    <p
                                                        class="text-muted-foreground text-[10px] font-semibold tracking-wide uppercase"
                                                    >
                                                        Capacity
                                                    </p>
                                                    <p class="text-foreground">
                                                        {session.assigned_count ??
                                                            0} assigned / {session
                                                            .room?.capacity ??
                                                            '—'} seats
                                                    </p>
                                                </div>
                                            </div>
                                        {/snippet}

                                        {#snippet actions()}
                                            <Link
                                                href={`/exams/sessions/${session.id}/monitoring`}
                                                class="flex-1"
                                                onclick={prepareSessionNavigation}
                                            >
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    class="min-h-10 w-full font-semibold"
                                                >
                                                    <Eye
                                                        class="mr-1.5 size-3.5"
                                                    />
                                                    Review Roster
                                                </Button>
                                            </Link>
                                        {/snippet}
                                    </OperationalCard>
                                {/each}
                            </ul>
                        {/snippet}
                    </SwitchableListView>

                    <!-- History Pagination Controls -->
                    {#if history_sessions}
                        <SimplePagination data={history_sessions} />
                    {/if}
                {:else}
                    <div
                        class="border-border bg-card space-y-2 rounded-xl border p-8 text-center"
                    >
                        <p class="text-foreground text-sm font-medium">
                            No completed sessions match your current filter
                        </p>
                        <p class="text-muted-foreground text-xs">
                            Try clearing your search term, date, or room
                            selection.
                        </p>
                        {#if hasActiveFilters}
                            <Button
                                variant="outline"
                                size="sm"
                                class="mt-2 text-xs"
                                onclick={clearFilters}
                            >
                                Clear All Filters
                            </Button>
                        {/if}
                    </div>
                {/if}
            {/if}
        {/if}
    </div>

    <XiamNpcDialog
        tourKey="exam-monitoring"
        context={monitoringContext}
        bind:open={tourOpen}
    />
</StaffLayout>
<style>
    :global(.monitoring-search-suggestions > div.absolute) {
        z-index: 50;
        width: 100%;
        max-width: min(42rem, calc(100vw - 2rem));
        min-width: 0;
        padding: 1rem;
        border-radius: 1rem;
    }
    :global(.monitoring-search-suggestions > div.absolute button) {
        min-height: 44px;
    }

    @media (min-width: 768px) {
        .monitoring-filter-content {
            display: flex !important;
            padding-top: 0;
        }
    }
</style>
