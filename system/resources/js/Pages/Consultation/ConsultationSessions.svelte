<script>
    import { onDestroy } from 'svelte';
    /**
     * Release Day sessions Index — legacy list -> create/edit shape:
     * list + filters + Create CTA; row opens Show.
     */
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
    import HeaderActions from '@/Components/kit/HeaderActions.svelte';
    import SwitchableListView from '@/Components/kit/SwitchableListView.svelte';
    import BulkActionBar from '@/Components/kit/BulkActionBar.svelte';
    import { Checkbox } from '@/Components/ui/checkbox';
    import { Badge } from '@/Components/ui/badge';
    import { Button } from '@/Components/ui/button';
    import * as Dialog from '@/Components/ui/dialog';
    import { Input } from '@/Components/ui/input';
    import * as Select from '@/Components/ui/select';
    import * as Table from '@/Components/ui/table';
    import { Textarea } from '@/Components/ui/textarea';
    import { Link, router } from '@inertiajs/svelte';
    import {
        Ban,
        CheckCircle,
        Lock,
        LockOpen,
        Pencil,
        Plus,
        Search,
        Send,
        Users,
        EllipsisVertical,
        School,
    } from 'lucide-svelte';
    import SortableTableHead from '@/Components/kit/SortableTableHead.svelte';
    import ActionDropdown from '@/Components/kit/ActionDropdown.svelte';
    import SortDropdown from '@/Components/kit/SortDropdown.svelte';
    import SearchSuggestionDropdown from '@/Components/kit/SearchSuggestionDropdown.svelte';
    import CohortAutoDistributeModal from '@/Components/scheduling/CohortAutoDistributeModal.svelte';
    import { Sparkles } from 'lucide-svelte';
    import EntityRowActions from '@/Components/kit/EntityRowActions.svelte';

    let {
        page_state = 'ready',
        sessions = [],
        filters = {},
        statuses = [],
        cycles = [],
        booking_modes = [],
        errors = {},
        distribution = {},
        sort_options = [],
        waitlist_counts = {},
    } = $props();

    let autoDistributeOpen = $state(false);
    let viewMode = $state('responsive');
    let filterSearch = $state(filters.search ?? '');
    let filterStatus = $state(filters.status || 'active');
    let filterCycleId = $state(
        filters.academic_cycle_id ? String(filters.academic_cycle_id) : '',
    );
    const waitlistCount = $derived(Number(waitlist_counts?.ready ?? 0));
    const waitlistUrl = $derived(
        typeof route === 'function'
            ? route('consultation.sessions.waitlist', {
                academic_cycle_id: filterCycleId || undefined,
            })
            : (filterCycleId ? `/consultation/sessions/waitlist?academic_cycle_id=${filterCycleId}` : '/consultation/sessions/waitlist')
    );
    let sortValue = $state(filters.sort ?? 'scheduled_at');
    let sortDir = $state(filters.dir ?? 'desc');
    let serverSortValue = $state(filters.sort ?? 'scheduled_at');
    let serverSortDir = $state(filters.dir ?? 'desc');
    let suggestionDropdown = $state();
    let searchInput = $state(null);
    let debounceTimer;

    onDestroy(() => clearTimeout(debounceTimer));
    let cancelDialogOpen = $state(false);
    let cancelReason = $state('');
    let activeSession = $state(null);
    let selectedSessionIds = $state([]);
    let bulkCancelDialogOpen = $state(false);
    let bulkCancelReason = $state('');
    let visibleSessionIds = $derived(sessions.map((session) => session.id));
    let localSortOptions = [
        { value: 'room', label: 'Room' },
        { value: 'assignment', label: 'Assignment' },
        { value: 'assigned', label: 'Assigned' },
    ];
    let toolbarSortOptions = $derived([
        ...(sort_options ?? []),
        ...localSortOptions,
    ]);
    let sortedSessions = $derived.by(() => {
        if (!localSortOptions.some((option) => option.value === sortValue)) {
            return sessions;
        }

        const direction = sortDir === 'asc' ? 1 : -1;
        const compareValues = (left, right) => {
            if (left == null && right == null) return 0;
            if (left == null) return 1;
            if (right == null) return -1;
            if (typeof left === 'number' && typeof right === 'number') {
                return left - right;
            }
            return String(left).localeCompare(String(right), undefined, {
                numeric: true,
                sensitivity: 'base',
            });
        };
        const valueFor = (session) => {
            if (sortValue === 'room') {
                return session.room?.name ?? session.room?.code ?? null;
            }
            if (sortValue === 'assignment') {
                return session.booking_mode === 'open_for_reservation'
                    ? 'Self-booking'
                    : isDraftStaffOnly(session) && session.auto_assign_enabled
                      ? 'Auto-Assign'
                      : 'Staff only';
            }
            return Number(session.assigned_count ?? 0);
        };

        return [...sessions].sort(
            (left, right) =>
                compareValues(valueFor(left), valueFor(right)) * direction,
        );
    });
    let isAllVisibleSelected = $derived(
        visibleSessionIds.length > 0 &&
            visibleSessionIds.every((id) => selectedSessionIds.includes(id)),
    );
    let isSomeVisibleSelected = $derived(
        visibleSessionIds.some((id) => selectedSessionIds.includes(id)) &&
            !isAllVisibleSelected,
    );

    const draftCount = $derived((sessions ?? []).filter((s) => s.status === 'draft').length);
    const publishedCount = $derived((sessions ?? []).filter((s) => s.status === 'published').length);
    const openReservationCount = $derived((sessions ?? []).filter((s) => s.booking_mode === 'open_for_reservation').length);
    const staffOnlyCount = $derived((sessions ?? []).filter((s) => s.booking_mode === 'staff_only').length);
    const totalCapacity = $derived((sessions ?? []).reduce((acc, s) => acc + (s.capacity || 0), 0));
    const totalAssigned = $derived((sessions ?? []).reduce((acc, s) => acc + (s.assigned_count || 0), 0));

    const activeCycleLabel = $derived.by(() => {
        const targetId =
            filterCycleId ||
            filters?.academic_cycle_id ||
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
        Boolean(filterSearch.trim() || (filterStatus && filterStatus !== 'active')),
    );

    function clearAllFilters() {
        filterSearch = '';
        filterStatus = 'active';
        filterCycleId = '';
        applyFilters();
    }
    const schedulingContext = $derived.by(() => {
        const safetyKey = `${page_state}:${sessions?.length ?? 0}:${draftCount}:${publishedCount}:${selectedSessionIds.length}:${actionError ?? 'none'}`;

        return {
            entityKey: 'consultation-scheduling-hub',
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
            actionError,
        };
    });
    function toggleSession(id) {
        selectedSessionIds = selectedSessionIds.includes(id)
            ? selectedSessionIds.filter((selectedId) => selectedId !== id)
            : [...selectedSessionIds, id];
    }

    function toggleAllVisible() {
        selectedSessionIds = isAllVisibleSelected
            ? selectedSessionIds.filter(
                  (id) => !visibleSessionIds.includes(id),
              )
            : [...new Set([...selectedSessionIds, ...visibleSessionIds])];
    }

    function submitBulkAction(url, payload) {
        router.post(url, payload, {
            onSuccess: () => (selectedSessionIds = []),
        });
    }

    function submitBulkCancel() {
        submitBulkAction('/consultation/sessions/bulk-cancel', {
            session_ids: selectedSessionIds,
            reason: bulkCancelReason.trim(),
        });
        bulkCancelDialogOpen = false;
    }

    function applyFilters() {
        router.get(
            '/consultation/sessions',
            {
                search: filterSearch || undefined,
                status:
                    filters.status || (filterStatus && filterStatus !== 'active')
                        ? filterStatus
                        : undefined,
                academic_cycle_id: filterCycleId || undefined,
                sort: serverSortValue || undefined,
                dir: serverSortDir || undefined,
            },
            { preserveState: true },
        );
    }

    function handleSortChange(payload) {
        sortValue = payload.sort;
        sortDir = payload.dir;

        if (localSortOptions.some((option) => option.value === payload.sort)) {
            return;
        }

        serverSortValue = payload.sort;
        serverSortDir = payload.dir;
        router.get(
            '/consultation/sessions',
            {
                search: filterSearch || undefined,
                status:
                    filters.status || (filterStatus && filterStatus !== 'active')
                        ? filterStatus
                        : undefined,
                academic_cycle_id: filterCycleId || undefined,
                sort: payload.sort,
                dir: sortDir || undefined,
            },
            { preserveState: true, preserveScroll: true },
        );
    }

    function toggleColumnSort(sort) {
        handleSortChange({
            sort,
            dir: sortValue === sort && sortDir === 'asc' ? 'desc' : 'asc',
        });
    }

    function handleSuggestionActivate(action) {
        clearTimeout(debounceTimer);
        debounceTimer = undefined;

        if (action?.type === 'navigate' && action.url) {
            router.visit(action.url);
            return;
        }

        if (action?.type === 'filter' && action.query) {
            filterSearch = action.query;
            applyFilters();
        }
    }


    function onSearchInput() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(applyFilters, 300);
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
        const found = statuses.find((s) => s.value === value);
        if (found) return found.label;
        if (value === 'draft') return 'Draft';
        if (value === 'published') return 'Published';
        if (value === 'in_progress') return 'In progress';
        if (value === 'completed') return 'Completed';
        if (value === 'cancelled') return 'Cancelled';
        return value ?? '—';
    }

    function bookingModeLabel(mode) {
        const found = booking_modes.find((m) => m.value === mode);
        if (found) return found.label;
        if (mode === 'open_for_reservation')
            return 'Self-booking (students reserve)';
        if (mode === 'staff_only') return 'Staff-only (office assigns)';
        return mode ?? '—';
    }

    function isDraftStaffOnly(session) {
        return (
            session.status === 'draft' &&
            (session.booking_mode === 'staff_only' || !session.booking_mode)
        );
    }

    function toggleAutoAssign(session) {
        const url =
            typeof route === 'function'
                ? route('consultation.sessions.toggle-auto-assign', session.id)
                : `/consultation/sessions/${session.id}/toggle-auto-assign`;
        router.patch(url, {}, { preserveScroll: true });
    }

    function formatScheduledAt(scheduledAt) {
        if (!scheduledAt) return '—';
        try {
            const d = new Date(
                scheduledAt.includes('T')
                    ? scheduledAt
                    : scheduledAt.replace(' ', 'T'),
            );
            if (!isNaN(d.getTime())) {
                return d.toLocaleDateString(undefined, {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric',
                    hour: 'numeric',
                    minute: '2-digit',
                });
            }
        } catch {
            // fallback to raw value
        }
        return scheduledAt;
    }

    function publishSession(session) {
        router.post(`/consultation/sessions/${session.id}/publish`, {
            expected_version: session.version,
        });
    }

    function openBooking(session) {
        router.post(`/consultation/sessions/${session.id}/open-reservation`, {
            expected_version: session.version,
        });
    }

    function closeBooking(session) {
        router.post(`/consultation/sessions/${session.id}/close-reservation`, {
            expected_version: session.version,
        });
    }

    function completeSession(session) {
        router.post(`/consultation/sessions/${session.id}/complete`, {
            expected_version: session.version,
        });
    }

    function promptCancel(session) {
        activeSession = session;
        cancelReason = '';
        cancelDialogOpen = true;
    }

    function submitCancel() {
        if (!activeSession || !cancelReason.trim()) return;
        router.post(
            `/consultation/sessions/${activeSession.id}/cancel`,
            {
                expected_version: activeSession.version,
                reason: cancelReason.trim(),
            },
            {
                onFinish: () => {
                    cancelDialogOpen = false;
                    cancelReason = '';
                    activeSession = null;
                },
            },
        );
    }

    const breadcrumbs = [
        { label: 'Release Day', href: '/consultation' },
        { label: 'Release Day Scheduling' },
    ];

    const actionError = $derived(
        errors?.publish ||
            errors?.open_reservation ||
            errors?.close_reservation ||
            errors?.cancel ||
            errors?.complete ||
            errors?.unpublish ||
            errors?.schedule ||
            null,
    );
</script>

<svelte:head><title>Release Day Scheduling · NEXIAM</title></svelte:head>

<StaffLayout {breadcrumbs}>
    <div class="min-w-0 space-y-6 p-4 sm:p-6">
        <PageHeaderBar
            title="Release Day Scheduling"
            description="Schedule Release Day sessions, assign eligible applicants, and publish session schedules."
            tourKey="consultation-scheduling"
            context={schedulingContext}
        >
            {#snippet actions()}
                <HeaderActions>
                    {#snippet primaryAction()}
                        <Link href="/consultation/sessions/create">
                            <Button
                                class="h-9 gap-1.5 px-3 text-xs font-semibold shadow-xs transition-all duration-200 hover:shadow-sm active:scale-[0.98]"
                                data-tour="consultation-create-session"
                            >
                                <Plus class="size-3.5" />
                                <span>Schedule Release Day</span>
                            </Button>
                        </Link>
                    {/snippet}
                    {#snippet alertAction()}
                        {#if waitlistCount > 0}
                            <Link href={waitlistUrl}>
                                <Button
                                    variant="outline"
                                    class="h-9 gap-1.5 px-2.5 sm:px-3 text-xs font-medium border-primary/30 bg-primary/5 hover:bg-primary/10 text-foreground shadow-xs"
                                    title={`Applicant Waitlist: ${waitlistCount} ready for Release Day`}
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
                        <Button
                            type="button"
                            variant="outline"
                            class="group h-9 gap-1.5 px-3 text-xs font-medium shadow-xs transition-all duration-200 hover:shadow-sm active:scale-[0.98]"
                            onclick={() => (autoDistributeOpen = true)}
                            data-tour="consultation-auto-distribute"
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


        {#if actionError}
            <div
                class="border-destructive/40 bg-destructive/10 text-destructive rounded-md border px-4 py-3 text-sm"
            >
                {actionError}
            </div>
        {/if}

        {#if page_state === 'denied'}
            <p class="text-muted-foreground text-sm">
                You do not have permission to manage Release Day sessions.
            </p>
        {:else}
            <div class="sticky top-0 z-20 backdrop-blur-md bg-background/80 py-2">
            <div class="flex flex-wrap items-end gap-3">
                <div class="relative w-full md:flex-1 md:min-w-[280px]">
                    <label
                        class="text-muted-foreground mb-1 block text-xs font-medium"
                        for="consultation-search">Search</label
                    >
                    <div class="relative">
                        <Search
                            class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2"
                        />
                        <Input
                            id="consultation-search"
                            type="search"
                            placeholder="Search session code or room..."
                            class="h-11 min-h-[44px] w-full border border-input bg-background pl-10 text-foreground shadow-xs dark:bg-input/30 md:h-10"
                            bind:value={filterSearch}
                            bind:ref={searchInput}
                            oninput={onSearchInput}
                            onfocus={() => suggestionDropdown?.handleFocus()}
                            onblur={(event) => suggestionDropdown?.handleBlur(event)}
                            onkeydown={(event) => suggestionDropdown?.handleKeydown(event)}
                        />
                        <SearchSuggestionDropdown
                            bind:this={suggestionDropdown}
                            search={filterSearch}
                            academicCycleId={Number(filterCycleId || 0)}
                            status={filterStatus}
                            domainContext="consultation_scheduling"
                            contextKey={schedulingContext}
                            onactivate={handleSuggestionActivate}
                            inputRef={searchInput}
                        />
                    </div>
                </div>
                <div>
                    <label
                        class="text-muted-foreground mb-1 block text-xs font-medium"
                        for="consultation-status">Status</label
                    >
                    <Select.Root
                        type="single"
                        value={filterStatus}
                        onValueChange={(v) => {
                            filterStatus = v || 'active';
                            applyFilters();
                        }}
                    >
                        <Select.Trigger
                            id="consultation-status"
                            class="min-h-10 w-[160px]"
                        >
                            {statusLabel(filterStatus)}
                        </Select.Trigger>
                        <Select.Content>
                            <Select.Item value="active" label="Needs scheduling"
                                >Needs scheduling</Select.Item
                            >
                            <Select.Item value="all" label="All sessions"
                                >All sessions</Select.Item
                            >
                            {#each statuses as s (s.value)}
                                <Select.Item value={s.value} label={s.label}
                                    >{s.label}</Select.Item
                                >
                            {/each}
                        </Select.Content>
                    </Select.Root>
                </div>
                <div>
                    <label
                        class="text-muted-foreground mb-1 block text-xs font-medium"
                        for="consultation-cycle">Academic year</label
                    >
                    <Select.Root
                        type="single"
                        value={filterCycleId}
                        onValueChange={(v) => {
                            filterCycleId = v ?? '';
                            applyFilters();
                        }}
                    >
                        <Select.Trigger
                            id="consultation-cycle"
                            class="min-h-10 w-[220px]"
                        >
                            {#if filterCycleId}
                                {cycles.find(
                                    (c) => String(c.id) === filterCycleId,
                                )?.label ?? 'Academic year'}
                            {:else}
                                <span class="text-muted-foreground"
                                    >All academic years</span
                                >
                            {/if}
                        </Select.Trigger>
                        <Select.Content>
                            <Select.Item value="" label="All academic years"
                                >All academic years</Select.Item
                            >
                            {#each cycles as c (c.id)}
                                <Select.Item
                                    value={String(c.id)}
                                    label={c.label}>{c.label}</Select.Item
                                >
                            {/each}
                        </Select.Content>
                    </Select.Root>
                </div>
                {#if sort_options && sort_options.length > 0}
                    <div>
                        <label
                            class="text-muted-foreground mb-1 block text-xs font-medium"
                            for="consultation-sort">Sort</label
                        >
                        <SortDropdown
                            options={toolbarSortOptions}
                            sort={sortValue}
                            dir={sortDir}
                            onchange={handleSortChange}
                        />
                    </div>
                {/if}
            </div>
            </div>

            {#if sortedSessions.length > 0}
                <SwitchableListView bind:viewMode overflow="auto">
                    {#snippet table()}
                    <Table.Root class="w-full min-w-205 text-sm" data-tour="consultation-session-list">
                    <Table.Header class="bg-muted/40 border-b border-border">
                        <Table.Row class="border-b border-border/40 hover:bg-muted/30 transition-colors">
                                <Table.Head class="w-12 px-4 py-3">
                                    <Checkbox
                                        aria-label="Select all visible sessions"
                                        checked={isAllVisibleSelected}
                                        indeterminate={isSomeVisibleSelected}
                                        onCheckedChange={toggleAllVisible}
                                    />
                                </Table.Head>
                                <SortableTableHead label="When" direction={sortValue === 'scheduled_at' ? (sortDir === 'asc' ? 'ascending' : 'descending') : 'none'} onclick={() => toggleColumnSort('scheduled_at')} class="px-4 py-3 font-semibold" />
                                <SortableTableHead label="Room" direction={sortValue === 'room' ? (sortDir === 'asc' ? 'ascending' : 'descending') : 'none'} onclick={() => toggleColumnSort('room')} class="px-4 py-3 font-semibold" />
                                <SortableTableHead label="Code" direction={sortValue === 'code' ? (sortDir === 'asc' ? 'ascending' : 'descending') : 'none'} onclick={() => toggleColumnSort('code')} class="px-4 py-3 font-semibold" />
                                <SortableTableHead label="Assignment" direction={sortValue === 'assignment' ? (sortDir === 'asc' ? 'ascending' : 'descending') : 'none'} onclick={() => toggleColumnSort('assignment')} class="px-4 py-3 font-semibold" />
                                <SortableTableHead label="Status" direction={sortValue === 'status' ? (sortDir === 'asc' ? 'ascending' : 'descending') : 'none'} onclick={() => toggleColumnSort('status')} class="px-4 py-3 font-semibold" />
                                <SortableTableHead label="Assigned" align="right" direction={sortValue === 'assigned' ? (sortDir === 'asc' ? 'ascending' : 'descending') : 'none'} onclick={() => toggleColumnSort('assigned')} class="px-4 py-3 font-semibold" />
                                <Table.Head class="px-4 py-3 font-semibold text-right pr-6">Actions</Table.Head>
                            </Table.Row>
                        </Table.Header>
                        <Table.Body>
                            {#each sortedSessions as session (session.id)}
                                <Table.Row
                                    class="border-b border-border/40 hover:bg-muted/30 transition-colors cursor-pointer"
                                    onclick={(e) => {
                                        if (
                                            e.target.closest(
                                                '[data-action-cell]',
                                            )
                                        )
                                            return;
                                        router.visit(
                                            `/consultation/sessions/${session.id}`,
                                        );
                                    }}
                                >
                                    <Table.Cell class="w-12 px-4 py-3" data-action-cell>
                                        <input
                                            type="checkbox"
                                            aria-label={`Select session ${session.code}`}
                                            checked={selectedSessionIds.includes(session.id)}
                                            onchange={() => toggleSession(session.id)}
                                            onclick={(e) => e.stopPropagation()}
                                            class="size-4 rounded border-border accent-primary"
                                        />
                                    </Table.Cell>
                                    <Table.Cell class="px-4 py-3 font-sans text-xs text-foreground text-left"
                                        >{formatScheduledAt(
                                            session.scheduled_at,
                                        )}</Table.Cell
                                    >
                                    <Table.Cell class="px-4 py-3 font-medium text-xs text-left"
                                        >{session.room?.name ??
                                            session.room?.code ??
                                            '—'}</Table.Cell
                                    >
                                    <Table.Cell
                                        class="px-4 py-3 font-mono font-bold text-xs text-left"
                                        >{session.code}</Table.Cell
                                    >
                                    <Table.Cell class="px-4 py-3 text-left whitespace-nowrap">
                                        {#if session.booking_mode === 'open_for_reservation'}
                                            <Badge variant="outline" class="text-[10px] text-primary border-primary/30 font-sans">
                                                Self-booking
                                            </Badge>
                                        {:else if isDraftStaffOnly(session) && session.auto_assign_enabled}
                                            <Badge variant="outline" class="gap-1.5 border-emerald-500/30 bg-emerald-500/10 font-sans text-[10px] font-semibold text-emerald-700 dark:text-emerald-300">
                                                <span class="size-1.5 rounded-full bg-emerald-500"></span>
                                                Auto-Assign: ON
                                            </Badge>
                                        {:else}
                                            <Badge variant="outline" class="border-border/60 text-muted-foreground font-sans text-[10px]">
                                                Staff only
                                            </Badge>
                                        {/if}
                                    </Table.Cell>
                                    <Table.Cell class="px-4 py-3 text-left whitespace-nowrap">
                                        <Badge
                                            variant={statusVariant(
                                                session.status,
                                            )}
                                            class="font-mono text-[10px] font-bold"
                                            >{statusLabel(
                                                session.status,
                                            )}</Badge
                                        >
                                    </Table.Cell>
                                    <Table.Cell class="px-4 py-3 text-right font-mono tabular-nums font-semibold">
                                        {session.assigned_count ??
                                            0}{#if session.room?.capacity}
                                            / {session.room.capacity}{/if}
                                    </Table.Cell>
                                    <Table.Cell
                                        class="px-4 py-3 text-right pr-6"
                                        data-action-cell
                                    >
                                        <EntityRowActions
                                            primaryAction={{
                                                label: 'Roster',
                                                href: `/consultation/sessions/${session.id}`,
                                                variant: 'outline',
                                            }}
                                            overflowItems={[
                                                session.status === 'draft' ? {
                                                    label: 'Edit Session',
                                                    href: `/consultation/sessions/${session.id}/edit`,
                                                    icon: Pencil,
                                                } : null,
                                                isDraftStaffOnly(session) ? {
                                                    label: session.auto_assign_enabled
                                                        ? 'Disable Auto-Assign'
                                                        : 'Enable Auto-Assign',
                                                    onclick: () => toggleAutoAssign(session),
                                                    icon: Sparkles,
                                                } : null,
                                                session.status === 'draft' && (session.assigned_count ?? 0) > 0 ? {
                                                    label: 'Publish Session',
                                                    onclick: () => publishSession(session),
                                                    icon: Send,
                                                } : null,
                                                session.status === 'draft' && session.booking_mode === 'open_for_reservation' ? {
                                                    label: 'Close Booking',
                                                    onclick: () => closeBooking(session),
                                                    icon: Lock,
                                                } : null,
                                                session.status === 'draft' && session.booking_mode !== 'open_for_reservation' ? {
                                                    label: 'Open Booking',
                                                    onclick: () => openBooking(session),
                                                    icon: LockOpen,
                                                } : null,
                                                session.status === 'published' || session.status === 'in_progress' ? {
                                                    label: 'Complete Session',
                                                    onclick: () => completeSession(session),
                                                    icon: CheckCircle,
                                                } : null,
                                                session.status !== 'completed' && session.status !== 'cancelled' ? {
                                                    label: 'Cancel Session',
                                                    onclick: () => promptCancel(session),
                                                    destructive: true,
                                                    icon: Ban,
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
                            class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3"
                            role="list"
                            data-tour="consultation-session-list"
                        >
                            {#each sortedSessions as session (session.id)}
                                <li
                                    class="border-border bg-card flex min-w-0 flex-col gap-3.5 rounded-xl border p-5 shadow-sm"
                                >
                                    <div
                                        class="flex items-start justify-between gap-2"
                                    >
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-2">
                                                <input
                                                    type="checkbox"
                                                    aria-label={`Select session ${session.code}`}
                                                    checked={selectedSessionIds.includes(session.id)}
                                                    onchange={() => toggleSession(session.id)}
                                                    class="size-4 shrink-0 rounded border-border accent-primary"
                                                />
                                                <p
                                                    class="truncate font-semibold tracking-tight"
                                                >
                                                    {session.code}
                                                </p>
                                            </div>
                                            <p class="text-muted-foreground text-sm">
                                                {formatScheduledAt(
                                                    session.scheduled_at,
                                                )}
                                            </p>
                                            <p class="truncate text-muted-foreground text-sm">
                                                {session.room?.name ??
                                                    session.room?.code ??
                                                    '—'}
                                            </p>
                                        </div>
                                        <div class="flex shrink-0 items-center justify-end gap-1.5 flex-wrap">
                                            <Badge
                                                variant={statusVariant(
                                                    session.status,
                                                )}
                                                class="shrink-0 whitespace-nowrap"
                                            >
                                                {statusLabel(session.status)}
                                            </Badge>
                                            {#if session.booking_mode === 'open_for_reservation'}
                                                <Badge variant="outline" class="text-[10px] text-primary border-primary/30">
                                                    Self-booking
                                                </Badge>
                                            {:else if isDraftStaffOnly(session) && session.auto_assign_enabled}
                                                <Badge variant="outline" class="gap-1.5 border-emerald-500/30 bg-emerald-500/10 font-sans text-[10px] font-semibold text-emerald-700 dark:text-emerald-300">
                                                    <span class="size-1.5 rounded-full bg-emerald-500"></span>
                                                    Auto-Assign: ON
                                                </Badge>
                                            {:else}
                                                <Badge variant="outline" class="border-border/60 text-muted-foreground font-sans text-[10px]">
                                                    Staff only
                                                </Badge>
                                            {/if}
                                        </div>
                                    </div>
                                    <p class="text-muted-foreground text-sm">
                                        Assigned {session.assigned_count ??
                                            0}{#if session.room?.capacity}
                                            / {session.room.capacity}{/if}
                                    </p>
                                    <div
                                        class="mt-auto pt-3 border-t border-border/40 flex items-center justify-between"
                                        onclick={(e) => e.stopPropagation()}
                                        role="presentation"
                                    >
                                        <span class="text-xs text-muted-foreground font-mono">
                                            {session.assigned_count ?? 0}{#if session.room?.capacity} / {session.room.capacity} seats{/if}
                                        </span>
                                        <EntityRowActions
                                            primaryAction={{
                                                label: 'Review Roster',
                                                href: `/consultation/sessions/${session.id}`,
                                                variant: 'outline',
                                            }}
                                            overflowItems={[
                                                session.status === 'draft' ? {
                                                    label: 'Edit Session',
                                                    href: `/consultation/sessions/${session.id}/edit`,
                                                    icon: Pencil,
                                                } : null,
                                                isDraftStaffOnly(session) ? {
                                                    label: session.auto_assign_enabled
                                                        ? 'Disable Auto-Assign'
                                                        : 'Enable Auto-Assign',
                                                    onclick: () => toggleAutoAssign(session),
                                                    icon: Sparkles,
                                                } : null,
                                                session.status === 'draft' && (session.assigned_count ?? 0) > 0 ? {
                                                    label: 'Publish Session',
                                                    onclick: () => publishSession(session),
                                                    icon: Send,
                                                } : null,
                                                session.status === 'draft' && session.booking_mode === 'open_for_reservation' ? {
                                                    label: 'Close Booking',
                                                    onclick: () => closeBooking(session),
                                                    icon: Lock,
                                                } : null,
                                                session.status === 'draft' && session.booking_mode !== 'open_for_reservation' ? {
                                                    label: 'Open Booking',
                                                    onclick: () => openBooking(session),
                                                    icon: LockOpen,
                                                } : null,
                                                session.status === 'published' || session.status === 'in_progress' ? {
                                                    label: 'Complete Session',
                                                    onclick: () => completeSession(session),
                                                    icon: CheckCircle,
                                                } : null,
                                                session.status !== 'completed' && session.status !== 'cancelled' ? {
                                                    label: 'Cancel Session',
                                                    onclick: () => promptCancel(session),
                                                    destructive: true,
                                                    icon: Ban,
                                                } : null,
                                            ]}
                                        />
                                    </div>
                                </li>
                            {/each}
                        </ul>
                {/snippet}
                </SwitchableListView>
            {:else if hasNarrowingFilters}
                <div
                    class="border-border bg-card space-y-2 rounded-xl border p-8 text-center"
                    data-tour="consultation-session-list"
                >
                    <p class="text-foreground text-sm font-medium">
                        No Release Day sessions match your current filter
                    </p>
                    <p class="text-muted-foreground text-xs">
                        Try clearing your search term or status selection.
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
                    data-tour="consultation-session-list"
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
                            All consultation rooms are currently on standby for {activeCycleLabel}.
                            Schedule a Release Day session under Release Day
                            Scheduling to initiate live Release Day advising.
                        </p>
                    </div>
                    <div
                        class="flex flex-wrap items-center justify-center gap-3 pt-2"
                    >
                        <Link href="/consultation/sessions/create">
                            <Button variant="default" size="sm">
                                <Plus class="mr-1.5 size-3.5" />
                                <span>Schedule Release Day</span>
                            </Button>
                        </Link>
                    </div>
                </div>
            {/if}
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
                            submitBulkAction(
                                '/consultation/sessions/bulk-publish',
                                { session_ids: selectedSessionIds },
                            )}>Bulk Publish</Button
                    >
                    <Button
                        size="sm"
                        variant="outline"
                        onclick={() =>
                            submitBulkAction(
                                '/consultation/sessions/bulk-booking-mode',
                                {
                                    session_ids: selectedSessionIds,
                                    booking_mode: 'open_for_reservation',
                                },
                            )}>Open Reservation</Button
                    >
                    <Button
                        size="sm"
                        variant="outline"
                        onclick={() =>
                            submitBulkAction(
                                '/consultation/sessions/bulk-booking-mode',
                                {
                                    session_ids: selectedSessionIds,
                                    booking_mode: 'staff_only',
                                },
                            )}>Staff Only</Button
                    >
                    <Button
                        size="sm"
                        variant="destructive"
                        onclick={() => (bulkCancelDialogOpen = true)}
                        >Bulk Cancel</Button
                    >
                {/snippet}
            </BulkActionBar>
        {/if}
    </div>

    <Dialog.Root bind:open={cancelDialogOpen}>
        <Dialog.Content class="sm:max-w-md">
            <Dialog.Header>
                <Dialog.Title>Cancel Release Day session</Dialog.Title>
                <Dialog.Description>
                    Terminal cancel. Frees active assignments and open
                    reservations. Requires a reason.
                </Dialog.Description>
            </Dialog.Header>
            <Textarea
                bind:value={cancelReason}
                rows={3}
                placeholder="Reason for cancellation…"
            />
            <Dialog.Footer>
                <Button
                    type="button"
                    variant="outline"
                    onclick={() => (cancelDialogOpen = false)}>Back</Button
                >
                <Button
                    type="button"
                    variant="destructive"
                    disabled={!cancelReason.trim()}
                    onclick={submitCancel}
                >
                    Confirm cancellation
                </Button>
            </Dialog.Footer>
        </Dialog.Content>
    </Dialog.Root>
    <Dialog.Root bind:open={bulkCancelDialogOpen}>
        <Dialog.Content class="sm:max-w-md">
            <Dialog.Header>
                <Dialog.Title>Cancel selected Release Day sessions</Dialog.Title>
                <Dialog.Description>
                    This will cancel {selectedSessionIds.length} selected
                    session(s). Provide a reason to continue.
                </Dialog.Description>
            </Dialog.Header>
            <div class="flex flex-wrap gap-2">
                {#each [
                    'Weather / Typhoon Suspension',
                    'Holiday Declaration',
                    'Counselor On Leave / Unavailable',
                    'Administrative Rescheduling',
                ] as reason}
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onclick={() => (bulkCancelReason = reason)}
                    >
                        {reason}
                    </Button>
                {/each}
            </div>
            <Textarea
                bind:value={bulkCancelReason}
                rows={3}
                placeholder="Reason for cancellation…"
            />
            <Dialog.Footer>
                <Button
                    type="button"
                    variant="outline"
                    onclick={() => (bulkCancelDialogOpen = false)}>Back</Button
                >
                <Button
                    type="button"
                    variant="destructive"
                    disabled={!bulkCancelReason.trim()}
                    onclick={submitBulkCancel}
                >
                    Confirm cancellation
                </Button>
            </Dialog.Footer>
        </Dialog.Content>
    </Dialog.Root>

    <CohortAutoDistributeModal
        bind:open={autoDistributeOpen}
        domain="consultation"
        academicCycleId={distribution?.active_cycle_id ?? filters?.academic_cycle_id}
        academicCycleName={cycles.find((c) => String(c.value ?? c.id) === String(distribution?.active_cycle_id ?? filters?.academic_cycle_id))?.label ?? 'Current Academic Cycle'}
        {sessions}
        {distribution}
    />
</StaffLayout>
