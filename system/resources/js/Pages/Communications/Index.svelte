<script>
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
    import HeaderActions from '@/Components/kit/HeaderActions.svelte';
    import ActiveFilterChips from '@/Components/kit/ActiveFilterChips.svelte';
    import SwitchableListView from '@/Components/kit/SwitchableListView.svelte';
    import OperationalCard from '@/Components/kit/OperationalCard.svelte';
    import ActionDropdown from '@/Components/kit/ActionDropdown.svelte';
    import SimplePagination from '@/Components/kit/SimplePagination.svelte';
    import { Button } from '@/Components/ui/button';
    import { Badge } from '@/Components/ui/badge';
    import * as Table from '@/Components/ui/table';
    import * as Dialog from '@/Components/ui/dialog';
    import { Link, router, usePage } from '@inertiajs/svelte';
    import {
        error as toastError,
        success as toastSuccess,
    } from '@/lib/toast.js';
    import { onDestroy, onMount } from 'svelte';
    import { newIdempotencyKey } from '@/lib/utils.js';
    import { ArrowDown, ArrowUp, ArrowUpDown, ChevronDown, ExternalLink, Plus, Search, Sparkles, X } from 'lucide-svelte';

    let {
        announcements = { data: [], current_page: 1, last_page: 1, total: 0 },
        filters = { status: 'all', search: '', placement: 'all' },
        status_counts = {},
        capabilities = { manage: false, publish: false, campus: false },
        publishers = [],
    } = $props();
    const page = usePage();

    const statuses = [
        { value: 'all', label: 'All' },
        { value: 'published', label: 'Published' },
        { value: 'draft', label: 'Draft' },
        { value: 'scheduled', label: 'Scheduled' },
        { value: 'withdrawn', label: 'Withdrawn' },
        { value: 'expired', label: 'Expired' },
    ];
    let status = $state(filters.status ?? 'all');
    let filterSearch = $state(filters.search ?? '');
    let placement = $state(filters.placement ?? 'all');
    let viewMode = $state('responsive');
    let filterTimer;
    let searchOpen = $state(false);
    let mobileFiltersOpen = $state(false);
    let sort = $state('title');
    let dir = $state('asc');
    let actionListPage = 1;
    let withdrawOpen = $state(false);
    let withdrawTarget = $state(null);
    let withdrawReason = $state('');
    let withdrawRequestId = $state('');
    let withdrawProcessing = $state(false);
    let withdrawError = $state('');
    let activeMutation = $state(null);
    let actionError = $state('');
    let withdrawSignature = '';
    const commandRequestIds = Object.create(null);
    const rows = $derived(announcements?.data ?? []);
    const sortedRows = $derived([...rows].sort((left, right) => {
        const values = {
            title: [left.title, right.title],
            placement: [placementLabel(left), placementLabel(right)],
            effective_status: [statusLabel(left), statusLabel(right)],
            starts_at: [left.starts_at, right.starts_at],
            issuer: [left.publisher_label, right.publisher_label],
        }[sort] ?? ['', ''];
        const comparison = String(values[0] ?? '').localeCompare(String(values[1] ?? ''), undefined, { numeric: true, sensitivity: 'base' });
        return (dir === 'asc' ? comparison : -comparison) || String(left.id).localeCompare(String(right.id), undefined, { numeric: true });
    }));
    const suggestionRows = $derived.by(() => {
        const query = filterSearch.trim().toLowerCase();
        return rows.filter((row) => [row.title, row.publisher_label, row.summary, row.program_code]
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
    const statusCounts = $derived({
        all: status_counts.all ?? announcements.total ?? 0,
        published: status_counts.published ?? 0,
        draft: status_counts.draft ?? 0,
        scheduled: status_counts.scheduled ?? 0,
        withdrawn: status_counts.withdrawn ?? 0,
        expired: status_counts.expired ?? 0,
    });
    const activeFilterChips = $derived([
        ...(filterSearch.trim()
            ? [{ id: 'search', label: `Search: ${filterSearch.trim()}` }]
            : []),
        ...(status !== 'all'
            ? [
                  {
                      id: 'status',
                      label:
                          statuses.find((item) => item.value === status)
                              ?.label ?? status,
                  },
              ]
            : []),
        ...(placement !== 'all'
            ? [
                  {
                      id: 'placement',
                      label:
                          placement === 'program'
                              ? 'Program placement'
                              : 'General placement',
                  },
              ]
            : []),
    ]);
    const hasActiveFilters = $derived(activeFilterChips.length > 0);

    // Shared <ToastManager> in StaffLayout already consumes page.props.flash.
    onDestroy(() => clearTimeout(filterTimer));

    function applyFilters() {
        clearTimeout(filterTimer);
        actionListPage = 1;
        router.get(
            '/communications',
            {
                search: filterSearch.trim() || undefined,
                status: status === 'all' ? undefined : status,
                placement: placement === 'all' ? undefined : placement,
                page: 1,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    function scheduleFilters() {
        clearTimeout(filterTimer);
        filterTimer = setTimeout(applyFilters, 300);
    }

    function clearFilter(id) {
        if (id === 'search') filterSearch = '';
        if (id === 'status') status = 'all';
        if (id === 'placement') placement = 'all';
        applyFilters();
    }
    function selectStatus(value) {
        status = value;
        applyFilters();
    }

    function clearAllFilters() {
        filterSearch = '';
        status = 'all';
        placement = 'all';
        applyFilters();
    }
    function refreshFilteredList() {
        router.get(
            '/communications',
            {
                search: filterSearch.trim() || undefined,
                status: status === 'all' ? undefined : status,
                placement: placement === 'all' ? undefined : placement,
                page: actionListPage,
            },
            {
                only: ['announcements', 'filters'],
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    }

    function requestId(key) {
        return (commandRequestIds[key] ??= newIdempotencyKey());
    }

    function publish(row) {
        if (activeMutation) return;
        const key = `${row.id}:publish:${row.revision}`;
        actionError = '';
        activeMutation = row.id;
        actionListPage = announcements.current_page ?? 1;
        router.post(
            `/communications/${row.id}/publish`,
            {
                request_id: requestId(key),
                expected_revision: row.revision,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    actionError = '';
                    delete commandRequestIds[key];
                    toastSuccess('Announcement published.');
                },
                onError: (errors) => {
                    actionError =
                        errors?.expected_revision ||
                        errors?.request_id ||
                        errors?.message ||
                        'The announcement could not be published. Review the current status and retry.';
                    toastError(actionError);
                },
                onFinish: () => {
                    activeMutation = null;
                    refreshFilteredList();
                },
            },
        );
    }

    function openWithdraw(row) {
        actionListPage = announcements.current_page ?? 1;
        withdrawTarget = row;
        withdrawReason = '';
        withdrawRequestId = '';
        withdrawSignature = '';
        withdrawError = '';
        withdrawOpen = true;
    }

    function closeWithdraw() {
        if (withdrawProcessing) return;
        withdrawOpen = false;
        withdrawTarget = null;
        withdrawReason = '';
        withdrawError = '';
    }

    function withdraw(event) {
        event.preventDefault();
        if (
            !withdrawTarget ||
            activeMutation ||
            withdrawReason.trim().length < 3 ||
            withdrawReason.trim().length > 500
        )
            return;
        const reason = withdrawReason.trim();
        const signature = JSON.stringify([
            withdrawTarget.id,
            withdrawTarget.revision,
            reason,
        ]);
        if (signature !== withdrawSignature) {
            withdrawSignature = signature;
            withdrawRequestId = newIdempotencyKey();
        }
        withdrawProcessing = true;
        activeMutation = withdrawTarget.id;
        withdrawError = '';
        router.post(
            `/communications/${withdrawTarget.id}/withdraw`,
            {
                reason,
                request_id: withdrawRequestId,
                expected_revision: withdrawTarget.revision,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toastSuccess('Announcement withdrawn.');
                    withdrawOpen = false;
                    withdrawTarget = null;
                    withdrawRequestId = '';
                    withdrawSignature = '';
                },
                onError: (errors) => {
                    withdrawError =
                        errors?.expected_revision ||
                        errors?.request_id ||
                        errors?.reason ||
                        errors?.message ||
                        'The announcement could not be withdrawn. Your reason is still available to retry.';
                },
                onFinish: () => {
                    withdrawProcessing = false;
                    activeMutation = null;
                    refreshFilteredList();
                },
            },
        );
    }

    function statusLabel(row) {
        const effectiveStatus = row.effective_status ?? row.status;

        return (
            statuses.find((option) => option.value === effectiveStatus)?.label ??
            effectiveStatus
        );
    }

    function placementLabel(row) {
        const placement = row.placement ?? (row.program_code ? 'program' : 'general');

        if (placement === 'program' && row.program_code) {
            return `Program: ${row.program_code}`;
        }

        return placement === 'general' ? 'General' : placement;
    }

    function actionItems(row) {
        return [
            ...(row.can_edit || row.can_view
                ? [
                      {
                          label: 'Edit',
                          href: `/communications/${row.id}/edit`,
                      },
                  ]
                : []),
            ...(row.effective_status === 'published'
                ? [
                      {
                          label: 'View live',
                          href: `/announcements/${row.id}`,
                          external: true,
                      },
                  ]
                : []),
            ...(row.can_publish && row.status === 'draft'
                ? [
                      {
                          label: 'Publish',
                          onclick: () => publish(row),
                          disabled: activeMutation !== null,
                      },
                  ]
                : []),
            ...(row.can_withdraw
                ? [
                      {
                          label: 'Withdraw',
                          onclick: () => openWithdraw(row),
                          danger: true,
                          disabled: activeMutation !== null,
                      },
                  ]
                : []),
        ];
    }

    function dateLabel(value) {
        if (!value) return '—';
        const date = new Date(value);
        return Number.isNaN(date.getTime()) ? value : date.toLocaleString();
    }

    const breadcrumbs = [
        { label: 'Setup', href: '/setup' },
        { label: 'Announcements' },
    ];
</script>

<svelte:head><title>Announcements</title></svelte:head>

<StaffLayout {breadcrumbs}>
    <div class="min-w-0 space-y-6 p-4 sm:p-6">
        <PageHeaderBar
            title="Announcements"
            description="Create and publish updates for your authorized office or academic program."
        >
            {#snippet actions()}
                <HeaderActions>
                    {#snippet primaryAction()}
                        {#if capabilities.manage && publishers.length > 0}
                            <Button
                                href="/communications/create"
                                variant="default"
                                class="min-h-10"
                            >
                                <Plus class="size-4" />
                                New announcement
                            </Button>
                        {/if}
                    {/snippet}
                    {#snippet secondaryActions()}
                        <Button
                            href="/announcements"
                            variant="outline"
                            class="min-h-10"
                        >
                            <ExternalLink class="size-4" />
                            View public board ↗
                        </Button>
                    {/snippet}
                </HeaderActions>
            {/snippet}
        </PageHeaderBar>

            {#if actionError}
                <p
                    class="border-destructive/30 bg-destructive/5 text-foreground mb-4 rounded-md border p-3 text-sm"
                    role="alert"
                >
                    {actionError}
                </p>
            {/if}
            <div class="sticky top-0 z-20 backdrop-blur-md bg-background/80 py-2 space-y-3">
                <div class="flex flex-col gap-3 md:flex-row md:items-center">
                    <div class="relative min-w-0 w-full md:flex-1 md:min-w-[280px]">
                        <label for="announcement-search" class="sr-only">Search announcements</label>
                        <div class="relative flex items-center">
                            <Search class="pointer-events-none absolute left-3.5 size-4 text-muted-foreground" aria-hidden="true" />
                            <input
                                id="announcement-search"
                                type="search"
                                bind:value={filterSearch}
                                onfocus={() => (searchOpen = true)}
                                oninput={() => { searchOpen = true; scheduleFilters(); }}
                                onkeydown={(event) => {
                                    if (event.key === 'Enter') { applyFilters(); searchOpen = false; }
                                    if (event.key === 'Escape') searchOpen = false;
                                }}
                                placeholder="Search title, issuer, summary or program…"
                                class="h-11 min-h-[44px] w-full rounded-xl border border-input bg-background pl-10 pr-24 text-sm text-foreground shadow-xs outline-none focus-visible:ring-2 focus-visible:ring-ring dark:bg-input/30"
                                role="combobox"
                                aria-autocomplete="list"
                                aria-expanded={searchOpen}
                                aria-controls="announcement-search-suggestions"
                            />
                            <div class="absolute right-2 flex items-center gap-1">
                                {#if filterSearch}
                                    <button type="button" onclick={() => { filterSearch = ''; applyFilters(); }} class="grid min-h-[44px] min-w-[44px] place-items-center rounded-md text-muted-foreground hover:bg-muted" aria-label="Clear search"><X class="size-4" /></button>
                                {/if}
                                <Button type="button" class="min-h-[44px]" onclick={() => { applyFilters(); searchOpen = false; }}>Search</Button>
                            </div>
                        </div>
                        {#if searchOpen}
                            <div class="fixed inset-0 z-30" role="presentation" onclick={() => (searchOpen = false)}></div>
                            <div id="announcement-search-suggestions" class="absolute left-0 top-full z-50 mt-1.5 w-full max-w-2xl rounded-2xl border border-border bg-card p-4 shadow-2xl backdrop-blur-md" role="listbox" aria-label="Announcement search suggestions">
                                <div class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground"><Sparkles class="size-4 text-primary" />Quick shortcuts &amp; announcement matches</div>
                                <div class="flex flex-wrap gap-2">
                                    {#each statuses.filter((option) => option.value !== 'all') as shortcut (shortcut.value)}
                                        <button type="button" class="min-h-[44px] rounded-lg border border-border bg-muted/40 px-3 text-xs font-medium hover:bg-muted" onclick={() => { selectStatus(shortcut.value); searchOpen = false; }}>{shortcut.label}</button>
                                    {/each}
                                    {#each [{ label: 'General placement', value: 'general' }, { label: 'Program placement', value: 'program' }] as shortcut (shortcut.value)}
                                        <button type="button" class="min-h-[44px] rounded-lg border border-border bg-muted/40 px-3 text-xs font-medium hover:bg-muted" onclick={() => { placement = shortcut.value; applyFilters(); searchOpen = false; }}>{shortcut.label}</button>
                                    {/each}
                                </div>
                                {#if suggestionRows.length}
                                    <div class="mt-3 max-h-56 space-y-1 overflow-y-auto border-t border-border/50 pt-2">
                                        {#each suggestionRows as row (row.id)}
                                            <button type="button" class="flex min-h-[44px] w-full items-center justify-between gap-3 rounded-lg px-3 text-left text-sm hover:bg-muted" onclick={() => { filterSearch = row.title; applyFilters(); searchOpen = false; }}>
                                                <span class="min-w-0 truncate"><span class="font-medium">{row.title}</span><span class="ml-2 text-xs text-muted-foreground">{row.publisher_label} · {placementLabel(row)}</span></span><span class="shrink-0 text-xs text-primary">Search</span>
                                            </button>
                                        {/each}
                                    </div>
                                {/if}
                            </div>
                        {/if}
                    </div>
                    <button type="button" class="inline-flex min-h-[44px] items-center justify-between rounded-md border border-input bg-background px-3 text-sm text-foreground shadow-xs md:hidden dark:bg-input/30" aria-expanded={mobileFiltersOpen} aria-controls="announcement-mobile-filters" onclick={() => (mobileFiltersOpen = !mobileFiltersOpen)}>
                        <span class="min-w-0 flex-1 truncate">Filters ({(status !== 'all' ? 1 : 0) + (placement !== 'all' ? 1 : 0)})</span><ChevronDown class="shrink-0 ml-1.5" />
                    </button>
                    <div id="announcement-mobile-filters" class={mobileFiltersOpen ? 'grid grid-cols-1 gap-3 md:flex md:items-end' : 'hidden md:flex md:items-end'}>
                        <label class="relative flex min-w-0 flex-1 flex-col gap-1.5">
                            <span class="text-xs font-medium text-muted-foreground">Lifecycle status</span>
                            <span class="flex min-w-0 items-center rounded-md border border-input bg-background shadow-xs dark:bg-input/30">
                                <select bind:value={status} onchange={() => selectStatus(status)} class="min-w-0 flex-1 truncate appearance-none bg-transparent min-h-10 px-3 pr-1 text-xs text-foreground">
                                    {#each statuses as option (option.value)}
                                        <option value={option.value}>{option.label} ({statusCounts[option.value]})</option>
                                    {/each}
                                </select>
                                <ChevronDown class="shrink-0 ml-1.5 mr-2 size-4 text-muted-foreground" />
                            </span>
                        </label>
                        <label class="relative flex min-w-0 flex-1 flex-col gap-1.5">
                            <span class="text-xs font-medium text-muted-foreground">Placement</span>
                            <span class="flex min-w-0 items-center rounded-md border border-input bg-background shadow-xs dark:bg-input/30">
                                <select bind:value={placement} onchange={applyFilters} class="min-w-0 flex-1 truncate appearance-none bg-transparent min-h-10 px-3 pr-1 text-xs text-foreground">
                                    <option value="all">All placements</option>
                                    <option value="general">General</option>
                                    <option value="program">Program</option>
                                </select>
                                <ChevronDown class="shrink-0 ml-1.5 mr-2 size-4 text-muted-foreground" />
                            </span>
                        </label>
                    </div>
                </div>
            </div>
            <ActiveFilterChips
                chips={activeFilterChips}
                onRemove={clearFilter}
                onClearAll={clearAllFilters}
            />
            <p class="text-muted-foreground mt-3 text-sm" aria-live="polite">
                {announcements.total ?? 0} announcement{announcements.total ===
                1
                    ? ''
                    : 's'}
            </p>
            {#snippet rowActions(row)}
                <ActionDropdown items={actionItems(row)} />
            {/snippet}

            {#if rows.length === 0}
                <div
                    class="border-border mt-5 rounded-lg border border-dashed px-5 py-8 text-center"
                    role="status"
                >
                    <h2 class="text-foreground font-semibold">
                        {announcements.total > 0
                            ? 'No announcements on this page.'
                            : hasActiveFilters
                              ? 'No announcements match these filters in your authorized scope.'
                              : 'No announcements in this authorized scope yet.'}
                    </h2>
                    <p
                        class="text-muted-foreground mx-auto mt-2 max-w-[60ch] text-sm leading-6"
                    >
                        {announcements.total > 0
                            ? 'Return to the first page to continue browsing.'
                            : hasActiveFilters
                              ? 'Change or clear a filter to see other announcements in your authorized scope.'
                              : 'Create a draft for one of your authorized office or program sources.'}
                    </p>
                    {#if announcements.total > 0}
                        <Button
                            type="button"
                            variant="outline"
                            class="mt-4 min-h-11"
                            onclick={applyFilters}
                        >
                            Return to first page
                        </Button>
                    {:else if hasActiveFilters}
                        <Button
                            type="button"
                            variant="outline"
                            class="mt-4 min-h-11"
                            onclick={clearAllFilters}
                        >
                            Clear filters
                        </Button>
                    {:else if capabilities.manage && publishers.length > 0}
                        <Button
                            href="/communications/create"
                            class="mt-4 min-h-11"
                        >
                            Create announcement
                        </Button>
                    {/if}
                </div>
            {:else}
                <SwitchableListView bind:viewMode cardsInset={false}>
                    {#snippet table()}
                        <div class="overflow-x-auto">
                            <Table.Root class="w-full min-w-[1100px] text-sm">
                                <Table.Header class="bg-muted/40 border-b border-border">
                                    <Table.Row class="border-b border-border/40 hover:bg-muted/30 transition-colors">
                                        <Table.Head class="px-4 py-3 text-left align-middle" aria-sort={sortState('title')}>
                                            <button type="button" onclick={() => toggleSort('title')} class="inline-flex min-h-[44px] items-center gap-1.5 rounded-sm font-medium hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">Title <svelte:component this={sortIcon('title')} class={sort === 'title' ? 'size-3.5 text-primary' : 'size-3.5 opacity-40'} aria-hidden="true" /></button>
                                        </Table.Head>
                                        <Table.Head class="px-4 py-3 text-left align-middle" aria-sort={sortState('placement')}>
                                            <button type="button" onclick={() => toggleSort('placement')} class="inline-flex min-h-[44px] items-center gap-1.5 rounded-sm font-medium hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">Placement <svelte:component this={sortIcon('placement')} class={sort === 'placement' ? 'size-3.5 text-primary' : 'size-3.5 opacity-40'} aria-hidden="true" /></button>
                                        </Table.Head>
                                        <Table.Head class="px-4 py-3 text-left align-middle" aria-sort={sortState('effective_status')}>
                                            <button type="button" onclick={() => toggleSort('effective_status')} class="inline-flex min-h-[44px] items-center gap-1.5 rounded-sm font-medium hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">Effective Status <svelte:component this={sortIcon('effective_status')} class={sort === 'effective_status' ? 'size-3.5 text-primary' : 'size-3.5 opacity-40'} aria-hidden="true" /></button>
                                        </Table.Head>
                                        <Table.Head class="px-4 py-3 text-left align-middle" aria-sort={sortState('starts_at')}>
                                            <button type="button" onclick={() => toggleSort('starts_at')} class="inline-flex min-h-[44px] items-center gap-1.5 rounded-sm font-medium hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">Scheduled Date <svelte:component this={sortIcon('starts_at')} class={sort === 'starts_at' ? 'size-3.5 text-primary' : 'size-3.5 opacity-40'} aria-hidden="true" /></button>
                                        </Table.Head>
                                        <Table.Head class="px-4 py-3 text-left align-middle" aria-sort={sortState('issuer')}>
                                            <button type="button" onclick={() => toggleSort('issuer')} class="inline-flex min-h-[44px] items-center gap-1.5 rounded-sm font-medium hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">Issuer <svelte:component this={sortIcon('issuer')} class={sort === 'issuer' ? 'size-3.5 text-primary' : 'size-3.5 opacity-40'} aria-hidden="true" /></button>
                                        </Table.Head>
                                        <Table.Head class="px-4 py-3 text-right align-middle">Actions</Table.Head>
                                    </Table.Row>
                                </Table.Header>
                                <Table.Body>
                                    {#each sortedRows as row (row.id)}
                                        <Table.Row class="border-b border-border/40 hover:bg-muted/30 transition-colors">
                                            <Table.Cell class="min-w-56 px-4 py-3 align-middle">
                                                <Link href={`/communications/${row.id}/edit`} class="text-foreground max-w-[36ch] font-semibold break-words hover:underline">{row.title}</Link>
                                                <p class="text-muted-foreground mt-1 max-w-[42ch] text-xs break-words whitespace-pre-wrap">{row.summary}</p>
                                            </Table.Cell>
                                            <Table.Cell class="min-w-36 px-4 py-3 align-middle">
                                                {#if row.program_code}<Badge variant="secondary">{row.program_code} Program</Badge>{:else}<Badge variant="outline">General</Badge>{/if}
                                            </Table.Cell>
                                            <Table.Cell class="px-4 py-3 align-middle">
                                                <Badge variant={row.effective_status === 'published' ? 'default' : 'secondary'}>{statusLabel(row)}</Badge>
                                            </Table.Cell>
                                            <Table.Cell class="min-w-44 px-4 py-3 align-middle text-xs">
                                                Starts {dateLabel(row.starts_at)}<br />Ends {dateLabel(row.ends_at)}
                                            </Table.Cell>
                                            <Table.Cell class="min-w-36 px-4 py-3 align-middle">{row.publisher_label}</Table.Cell>
                                            <Table.Cell class="px-4 py-3 text-right align-middle">{@render rowActions(row)}</Table.Cell>
                                        </Table.Row>
                                    {/each}
                                </Table.Body>
                            </Table.Root>
                        </div>
                    {/snippet}
                    {#snippet cards()}
                        <ul
                            class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3"
                            role="list"
                        >
                            {#each rows as row (row.id)}
                                <OperationalCard
                                    id={row.id}
                                    title={row.title}
                                    subtitle={`${row.publisher_label} · ${placementLabel(row)}`}
                                >
                                    {#snippet status()}
                                        <Badge
                                            variant={row.effective_status === 'published'
                                                ? 'default'
                                                : 'secondary'}
                                        >
                                            {statusLabel(row)}
                                        </Badge>
                                    {/snippet}
                                    {#snippet summary()}
                                        <p class="text-muted-foreground line-clamp-2 text-sm leading-5 break-words">
                                            {row.summary || 'No summary provided.'}
                                        </p>
                                    {/snippet}
                                    {#snippet details()}
                                        <dl class="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
                                            <div class="sm:col-span-2">
                                                <dt class="text-muted-foreground text-xs">Schedule</dt>
                                                <dd class="text-xs">
                                                    Starts {dateLabel(row.starts_at)}<br />
                                                    Ends {dateLabel(row.ends_at)}
                                                </dd>
                                            </div>
                                            {#if row.published_at}
                                                <div>
                                                    <dt class="text-muted-foreground text-xs">Published</dt>
                                                    <dd class="text-xs">{dateLabel(row.published_at)}</dd>
                                                </div>
                                            {/if}
                                            <div>
                                                <dt class="text-muted-foreground text-xs">Program</dt>
                                                <dd class="text-xs">
                                                    {row.program_code
                                                        ? `${row.program_code} program`
                                                        : 'General announcement'}
                                                </dd>
                                            </div>
                                            <div>
                                                <dt class="text-muted-foreground text-xs">Target audience</dt>
                                                <dd class="text-xs">
                                                    {row.program_code
                                                        ? `Visitors to the ${row.program_code} program page`
                                                        : 'Visitors to the general announcement board'}
                                                </dd>
                                            </div>
                                            <div>
                                                <dt class="text-muted-foreground text-xs">Revision</dt>
                                                <dd class="text-xs">
                                                    v{row.revision}{row.priority === 'urgent'
                                                        ? ' · Urgent'
                                                        : ''}{row.is_featured
                                                        ? ' · Featured'
                                                        : ''}
                                                </dd>
                                            </div>
                                        </dl>
                                    {/snippet}
                                    {#snippet actions()}
                                        <div class="flex min-w-0 items-center gap-2">
                                            {#if row.can_edit}
                                                <Button
                                                    href={`/communications/${row.id}/edit`}
                                                    variant="default"
                                                    class="min-h-10"
                                                >
                                                    Edit announcement
                                                </Button>
                                            {:else if row.can_view}
                                                <Button
                                                    href={`/communications/${row.id}/edit`}
                                                    variant="outline"
                                                    class="min-h-10"
                                                >
                                                    View details
                                                </Button>
                                            {:else if row.effective_status === 'published'}
                                                <Button
                                                    href={`/announcements/${row.id}`}
                                                    variant="outline"
                                                    class="min-h-10"
                                                >
                                                    View live ↗
                                                </Button>
                                            {/if}
                                            {@render rowActions(row)}
                                        </div>
                                    {/snippet}
                                </OperationalCard>
                            {/each}
                        </ul>
                    {/snippet}
                </SwitchableListView>
            {/if}
            <SimplePagination data={announcements} />
    </div>

    <Dialog.Root
        bind:open={withdrawOpen}
        onOpenChange={(open) => {
            withdrawOpen = open;
            if (!open && !withdrawProcessing) withdrawTarget = null;
        }}
    >
        <Dialog.Content
            class="max-h-[calc(100dvh-2rem)] overflow-y-auto sm:max-w-lg"
        >
            <Dialog.Header>
                <Dialog.Title>Withdraw announcement?</Dialog.Title>
                <Dialog.Description
                    >This immediately removes “{withdrawTarget?.title ??
                        'this announcement'}” from public pages. Its publication
                    history is retained.</Dialog.Description
                >
            </Dialog.Header>
            <form class="space-y-4" onsubmit={withdraw}>
                <div class="space-y-2">
                    <label for="withdraw-reason" class="text-sm font-medium"
                        >Reason for withdrawal</label
                    >
                    <textarea
                        id="withdraw-reason"
                        bind:value={withdrawReason}
                        minlength="3"
                        maxlength="500"
                        required
                        rows="4"
                        disabled={withdrawProcessing}
                        class="border-input bg-background text-foreground focus-visible:ring-ring min-h-24 w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:outline-none disabled:opacity-60"
                    ></textarea>
                    <p class="text-muted-foreground text-xs">
                        {withdrawReason.trim().length}/500 characters
                    </p>
                </div>
                {#if withdrawError}<p
                        role="alert"
                        class="text-destructive text-sm"
                    >
                        {withdrawError}
                    </p>{/if}
                <Dialog.Footer>
                    <Button
                        type="button"
                        variant="outline"
                        class="min-h-[44px]"
                        disabled={withdrawProcessing}
                        onclick={closeWithdraw}>Keep announcement</Button
                    >
                    <Button
                        type="submit"
                        variant="destructive"
                        class="min-h-[44px]"
                        disabled={withdrawProcessing ||
                            withdrawReason.trim().length < 3 ||
                            withdrawReason.trim().length > 500}
                        >{withdrawProcessing
                            ? 'Withdrawing…'
                            : 'Confirm withdrawal'}</Button
                    >
                </Dialog.Footer>
            </form>
        </Dialog.Content>
    </Dialog.Root>
</StaffLayout>
