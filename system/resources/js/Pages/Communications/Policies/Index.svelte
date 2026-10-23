<script>
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
    import HeaderActions from '@/Components/kit/HeaderActions.svelte';
    import InfoPopover from '@/Components/kit/InfoPopover.svelte';
    import ActiveFilterChips from '@/Components/kit/ActiveFilterChips.svelte';
    import SwitchableListView from '@/Components/kit/SwitchableListView.svelte';
    import OperationalCard from '@/Components/kit/OperationalCard.svelte';
    import ActionDropdown from '@/Components/kit/ActionDropdown.svelte';
    import SimplePagination from '@/Components/kit/SimplePagination.svelte';
    import { Button } from '@/Components/ui/button';
    import { Badge } from '@/Components/ui/badge';
    import { Input } from '@/Components/ui/input';
    import { Label } from '@/Components/ui/label';
    import * as Table from '@/Components/ui/table';
    import { Link, router } from '@inertiajs/svelte';
    import { onDestroy } from 'svelte';
    import { ArrowDown, ArrowUp, ArrowUpDown, ChevronDown, ExternalLink, Plus, Search, Sparkles, X } from 'lucide-svelte';
    import { success as toastSuccess, error as toastError } from '@/lib/toast.js';
    import { newIdempotencyKey } from '@/lib/utils.js';
    let {
        policies = { data: [], total: 0 },
        publishers = [],
        capabilities = { manage: false, publish: false },
        filters = { status: 'all', search: '', kind: 'all' },
        status_counts = {},
        current_intake_selection = null,
        expected_intake_policy_id: expectedCurrentIntakePolicyId = null,
    } = $props();
    let filterSearch = $state(filters.search ?? '');
    let status = $state(filters.status ?? 'all');
    let kind = $state(filters.kind ?? 'all');
    let viewMode = $state('responsive');
    let searchOpen = $state(false);
    let mobileFiltersOpen = $state(false);
    let sort = $state('title');
    let dir = $state('asc');
    let withdrawDialog = $state(null);
    let withdrawTarget = $state(null);
    let withdrawReason = $state('');
    let withdrawError = $state('');
    let designateDialog = $state(null);
    let designateTarget = $state(null);
    let designateError = $state('');
    let processing = $state(false);
    let publishingId = $state(null);
    let actionError = $state('');
    let privacyModalOpen = $state(false);
    let privacyTitle = $state('');
    let privacyContent = $state('');
    let privacySaving = $state(false);

    function openPrivacyModal() {
        if (!current_intake_selection) return;
        privacyTitle = current_intake_selection.draft_title ?? current_intake_selection.title ?? '';
        privacyContent = current_intake_selection.content ?? '';
        privacyModalOpen = true;
    }

    function savePrivacyPolicy(event) {
        event.preventDefault();
        if (!current_intake_selection || privacySaving) return;
        privacySaving = true;
        const payload = {
            title: privacyTitle.trim(),
            content: privacyContent.trim(),
            kind: 'privacy',
            expected_revision: current_intake_selection.draft_revision ?? current_intake_selection.revision,
            request_id: newIdempotencyKey(),
        };
        router.put(`/communications/policies/${current_intake_selection.id}`, payload, {
            preserveScroll: true,
            onSuccess: () => {
                privacyModalOpen = false;
                toastSuccess('Application Privacy Policy draft saved.');
            },
            onError: (errors) => {
                toastError(errors?.title || errors?.content || errors?.message || 'Failed to save privacy policy.');
            },
            onFinish: () => {
                privacySaving = false;
            },
        });
    }

    function publishDirectPrivacyPolicy() {
        if (!current_intake_selection || processing) return;
        processing = true;
        const payload = {
            expected_revision: current_intake_selection.draft_revision ?? current_intake_selection.revision,
            request_id: newIdempotencyKey(),
        };
        router.post(`/communications/policies/${current_intake_selection.id}/publish`, payload, {
            preserveScroll: true,
            onSuccess: () => {
                toastSuccess('Application Privacy Policy revision published.');
            },
            onError: (errors) => {
                toastError(errors?.message || 'Failed to publish privacy policy revision.');
            },
            onFinish: () => {
                processing = false;
            },
        });
    }
    let filterTimer;
    let requestSignature = '';
    let requestToken = '';
    let actionListPage = 1;
    let refreshAfterAction = false;

    const allRows = $derived(policies.data ?? []);
    const rows = $derived(allRows.filter((row) => kind === 'all' || row.kind === kind));
    const sortedRows = $derived([...rows].sort((left, right) => {
        const values = {
            title: [left.title, right.title],
            placement: [placementLabel(left), placementLabel(right)],
            status: [policyStatusLabel(left.status), policyStatusLabel(right.status)],
            published_at: [left.published_at, right.published_at],
            issuer: [left.publisher_label, right.publisher_label],
        }[sort] ?? ['', ''];
        const comparison = String(values[0] ?? '').localeCompare(String(values[1] ?? ''), undefined, { numeric: true, sensitivity: 'base' });
        return (dir === 'asc' ? comparison : -comparison) || String(left.id).localeCompare(String(right.id), undefined, { numeric: true });
    }));
    const suggestionRows = $derived.by(() => {
        const query = filterSearch.trim().toLowerCase();
        return allRows.filter((row) => [row.title, row.publisher_label, policyExcerpt(row), row.program_code, row.kind]
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
    const statuses = [
        { value: 'all', label: 'All' },
        { value: 'published', label: 'Published' },
        { value: 'draft', label: 'Draft' },
        { value: 'withdrawn', label: 'Withdrawn' },
    ];
    const statusCounts = $derived({
        all: status_counts.all ?? policies.total ?? rows.length,
        published: status_counts.published ?? 0,
        draft: status_counts.draft ?? 0,
        withdrawn: status_counts.withdrawn ?? 0,
    });
    const activeFilterChips = $derived([
        ...(filterSearch.trim()
            ? [{ id: 'search', label: `Search: ${filterSearch.trim()}` }]
            : []),
        ...(status !== 'all'
            ? [{
                  id: 'status',
                  label: statuses.find((item) => item.value === status)?.label ?? status,
              }]
            : []),
        ...(kind !== 'all'
            ? [{ id: 'kind', label: kind === 'privacy' ? 'Privacy policy' : 'Policy' }]
            : []),
    ]);
    const hasActiveFilters = $derived(activeFilterChips.length > 0);
    const breadcrumbs = [
        { label: 'Setup', href: '/setup' },
        { label: 'Public Policies' },
    ];

    onDestroy(() => clearTimeout(filterTimer));

    function applyFilters() {
        clearTimeout(filterTimer);
        actionListPage = 1;
        router.get(
            '/communications/policies',
            {
                search: filterSearch.trim() || undefined,
                status: status === 'all' ? undefined : status,
                kind: kind === 'all' ? undefined : kind,
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
        if (id === 'kind') kind = 'all';
        applyFilters();
    }
    function clearAllFilters() {
        filterSearch = '';
        status = 'all';
        kind = 'all';
        applyFilters();
    }
    function refreshFilteredList() {
        router.get(
            '/communications/policies',
            {
                search: filterSearch.trim() || undefined,
                status: status === 'all' ? undefined : status,
                kind: kind === 'all' ? undefined : kind,
                page: actionListPage,
            },
            {
                only: ['policies', 'filters', 'current_intake_selection', 'expected_intake_policy_id', 'status_counts'],
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    }

    function stableRequestId(payload) {
        const signature = JSON.stringify(payload);
        if (signature !== requestSignature) {
            requestSignature = signature;
            requestToken = newIdempotencyKey();
        }
        return requestToken;
    }

    function canPublish(row) {
        return Boolean(row.can_publish) && row.status !== 'withdrawn' && publishingId === null && !processing;
    }

    function hasPendingDraft(row) {
        return row.revision > (row.published_revision ?? 0);
    }

    function needsPrivacyKindEvidence(row) {
        return row.kind === 'privacy' && row.status === 'published' && row.published_kind == null && !hasPendingDraft(row);
    }
    function policyStatusLabel(status) {
        return {
            published: 'Published',
            draft: 'Draft',
            withdrawn: 'Withdrawn',
        }[status] ?? status;
    }

    function placementLabel(row) {
        return row.placement ?? (row.program_code ? `${row.program_code} program` : 'General');
    }

    function programDetailsLabel(row) {
        if (!row.program_code) return 'General policy';
        return row.program_name ? `${row.program_code} · ${row.program_name}` : row.program_code;
    }

    function policyExcerpt(row) {
        return row.excerpt ?? row.summary ?? '';
    }

    function actionItems(row) {
        return [
            {
                label: 'Edit',
                href: `/communications/policies/${row.id}/edit`,
            },
            ...(row.status === 'published'
                ? [{
                      label: 'View live',
                      href: `/policies/${row.id}`,
                      external: true,
                  }]
                : []),
            ...((hasPendingDraft(row) || needsPrivacyKindEvidence(row)) && canPublish(row)
                ? [{
                      label: needsPrivacyKindEvidence(row) ? 'Publish privacy classification evidence' : 'Publish',
                      onclick: () => publish(row),
                  }]
                : []),
            ...(row.status === 'published' && canPublish(row)
                ? [{
                      label: 'Withdraw',
                      onclick: () => openWithdraw(row),
                      danger: true,
                  }]
                : []),
        ];
    }

    function dateLabel(value) {
        if (!value) return '—';
        const date = new Date(value);
        return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString();
    }
    function timestampLabel(value) {
        if (!value) return '—';
        const date = new Date(value);
        return Number.isNaN(date.getTime()) ? value : date.toLocaleString();
    }

    function publish(row) {
        if (publishingId !== null || processing) return;
        actionError = '';
        publishingId = row.id;
        actionListPage = policies.current_page ?? 1;
        refreshAfterAction = false;
        const expected_revision = row.revision;
        const request_id = stableRequestId({
            operation: 'publish',
            policy_id: row.id,
            expected_revision,
        });
        router.post(
            `/communications/policies/${row.id}/publish`,
            { request_id, expected_revision },
            {
                preserveScroll: true,
                onSuccess: () => {
                    requestSignature = '';
                    requestToken = '';
                    refreshAfterAction = true;
                    toastSuccess('Public policy published.');
                },
                onError: (errors) => {
                    actionError =
                        errors?.expected_revision ||
                        errors?.request_id ||
                        errors?.message ||
                        'The policy could not be published. Reload and retry.';
                    toastError(actionError);
                },
                onFinish: () => {
                    publishingId = null;
                    if (refreshAfterAction) {
                        refreshAfterAction = false;
                        refreshFilteredList();
                    }
                },
            },
        );
    }
    function openDesignate(row) {
        if (!row.can_designate_intake || publishingId !== null || processing) return;
        actionListPage = policies.current_page ?? 1;
        designateTarget = row;
        designateError = '';
        designateDialog?.showModal();
    }

    function closeDesignate() {
        if (processing) return;
        designateDialog?.close();
        designateTarget = null;
    }

    function designateIntake(event) {
        event.preventDefault();
        if (!designateTarget || !designateTarget.can_designate_intake || processing) return;
        refreshAfterAction = false;
        processing = true;
        designateError = '';
        const expected_revision = designateTarget.revision;
        const expected_intake_policy_id = expectedCurrentIntakePolicyId ?? null;
        const request_id = stableRequestId({
            operation: 'designate_intake',
            policy_id: designateTarget.id,
            expected_revision,
            expected_intake_policy_id,
        });
        router.post(
            `/communications/policies/${designateTarget.id}/designate-intake`,
            { request_id, expected_revision, expected_intake_policy_id },
            {
                preserveScroll: true,
                onSuccess: () => {
                    designateDialog?.close();
                    designateTarget = null;
                    requestSignature = '';
                    requestToken = '';
                    refreshAfterAction = true;
                    toastSuccess('Application Privacy Policy updated.');
                },
                onError: (errors) => {
                    designateError =
                        errors?.expected_intake_policy_id ||
                        errors?.expected_revision ||
                        errors?.request_id ||
                        errors?.policy ||
                        errors?.message ||
                        'The Application Privacy Policy was not changed. Reload and retry.';
                    toastError(designateError);
                    refreshAfterAction = true;
                },
                onFinish: () => {
                    processing = false;
                    if (refreshAfterAction) {
                        refreshAfterAction = false;
                        refreshFilteredList();
                    }
                },
            },
        );
    }

    function openWithdraw(row) {
        if (publishingId !== null || processing) return;
        actionListPage = policies.current_page ?? 1;
        withdrawTarget = row;
        withdrawReason = '';
        withdrawError = '';
        withdrawDialog?.showModal();
    }

    function closeWithdraw() {
        if (processing) return;
        withdrawDialog?.close();
        withdrawTarget = null;
    }

    function withdraw(event) {
        event.preventDefault();
        if (!withdrawTarget || !withdrawReason.trim()) return;
        refreshAfterAction = false;
        processing = true;
        withdrawError = '';
        const reason = withdrawReason.trim();
        const expected_revision = withdrawTarget.revision;
        const request_id = stableRequestId({
            operation: 'withdraw',
            policy_id: withdrawTarget.id,
            expected_revision,
            reason,
        });
        router.post(
            `/communications/policies/${withdrawTarget.id}/withdraw`,
            { reason, request_id, expected_revision },
            {
                preserveScroll: true,
                onSuccess: () => {
                    withdrawDialog?.close();
                    withdrawTarget = null;
                    requestSignature = '';
                    requestToken = '';
                    refreshAfterAction = true;
                    toastSuccess('Public policy withdrawn.');
                },
                onError: (errors) => {
                    withdrawError =
                        errors?.expected_revision ||
                        errors?.request_id ||
                        errors?.reason ||
                        errors?.message ||
                        'The policy was not withdrawn. Your reason is still available to retry.';
                    toastError(withdrawError);
                },
                onFinish: () => {
                    processing = false;
                    if (refreshAfterAction) {
                        refreshAfterAction = false;
                        refreshFilteredList();
                    }
                },
            },
        );
    }
</script>

<svelte:head><title>Public policies</title></svelte:head>

<StaffLayout {breadcrumbs}>
    <div class="min-w-0 space-y-6 p-4 sm:p-6">
        <PageHeaderBar
            title="Public policies"
            description="Manage general and program-specific public documents. Each document has a separate saved draft and publication history."
        >
            {#snippet actions()}
                <HeaderActions>
                    {#snippet primaryAction()}
                        {#if capabilities.manage && publishers.length > 0}
                            <Button href="/communications/policies/create" variant="default" class="min-h-11">
                                <Plus class="size-4" />
                                New policy
                            </Button>
                        {/if}
                    {/snippet}
                    {#snippet secondaryActions()}
                        <Button href="/policies" variant="ghost" class="min-h-11 w-full sm:w-auto">
                            <ExternalLink class="size-4" />
                            View public policies ↗
                        </Button>
                    {/snippet}
                </HeaderActions>
            {/snippet}
        </PageHeaderBar>


            <section class="rounded-lg border border-border bg-card/60 p-4" aria-labelledby="intake-selection-title">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2">
                            <h2 id="intake-selection-title" class="font-semibold text-base">Application Privacy Policy</h2>
                            {#if current_intake_selection}
                                <Badge variant="secondary">Version {current_intake_selection.published_revision ?? current_intake_selection.revision}</Badge>
                                {#if current_intake_selection.has_pending_draft}
                                    <Badge variant="outline" class="border-amber-500/40 text-amber-700 dark:text-amber-400">Draft v{current_intake_selection.revision} pending</Badge>
                                {/if}
                            {/if}
                        </div>
                        {#if current_intake_selection}
                            <p class="text-sm text-muted-foreground">
                                {current_intake_selection.published_title ?? current_intake_selection.title}
                            </p>
                        {:else}
                            <p class="text-sm text-muted-foreground">No Application Privacy Policy is designated.</p>
                        {/if}
                    </div>
                    {#if current_intake_selection && (current_intake_selection.can_manage || current_intake_selection.can_publish)}
                        <div class="flex items-center gap-2">
                            {#if current_intake_selection.can_manage}
                                <Button size="sm" variant="outline" onclick={openPrivacyModal}>
                                    Edit Privacy Policy
                                </Button>
                            {/if}
                            {#if current_intake_selection.can_publish && current_intake_selection.has_pending_draft}
                                <Button size="sm" variant="default" onclick={publishDirectPrivacyPolicy} disabled={processing}>
                                    Publish Revision
                                </Button>
                            {/if}
                        </div>
                    {/if}
                </div>
            </section>
            {#if actionError}
                <p
                    class="mb-4 rounded-lg border border-amber-500/40 bg-amber-500/10 p-3 text-sm"
                    role="alert"
                >
                    {actionError}
                </p>
            {/if}

            <div class="sticky top-0 z-20 backdrop-blur-md bg-background/80 py-2 space-y-3">
                <div class="flex flex-col gap-3 md:flex-row md:items-center">
                    <div class="relative min-w-0 w-full md:flex-1 md:min-w-[280px]">
                        <label for="policy-search" class="sr-only">Search public policies</label>
                        <div class="relative flex items-center">
                            <Search class="pointer-events-none absolute left-3.5 size-4 text-muted-foreground" aria-hidden="true" />
                            <input
                                id="policy-search"
                                type="search"
                                bind:value={filterSearch}
                                onfocus={() => (searchOpen = true)}
                                oninput={() => { searchOpen = true; scheduleFilters(); }}
                                onkeydown={(event) => {
                                    if (event.key === 'Enter') { applyFilters(); searchOpen = false; }
                                    if (event.key === 'Escape') searchOpen = false;
                                }}
                                placeholder="Search title, issuer or policy content…"
                                class="h-11 min-h-[44px] w-full rounded-xl border border-input bg-background pl-10 pr-24 text-sm text-foreground shadow-xs outline-none focus-visible:ring-2 focus-visible:ring-ring dark:bg-input/30"
                                role="combobox"
                                aria-autocomplete="list"
                                aria-expanded={searchOpen}
                                aria-controls="policy-search-suggestions"
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
                            <div id="policy-search-suggestions" class="absolute left-0 top-full z-50 mt-1.5 w-full max-w-2xl rounded-2xl border border-border bg-card p-4 shadow-2xl backdrop-blur-md" role="listbox" aria-label="Policy search suggestions">
                                <div class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground"><Sparkles class="size-4 text-primary" />Quick shortcuts &amp; policy matches</div>
                                <div class="flex flex-wrap gap-2">
                                    {#each statuses.filter((option) => option.value !== 'all') as shortcut (shortcut.value)}
                                        <button type="button" class="min-h-[44px] rounded-lg border border-border bg-muted/40 px-3 text-xs font-medium hover:bg-muted" onclick={() => { status = shortcut.value; applyFilters(); searchOpen = false; }}>{shortcut.label}</button>
                                    {/each}
                                    {#each [{ label: 'Policy', value: 'policy' }, { label: 'Privacy', value: 'privacy' }] as shortcut (shortcut.value)}
                                        <button type="button" class="min-h-[44px] rounded-lg border border-border bg-muted/40 px-3 text-xs font-medium hover:bg-muted" onclick={() => { kind = shortcut.value; applyFilters(); searchOpen = false; }}>{shortcut.label}</button>
                                    {/each}
                                </div>
                                {#if suggestionRows.length}
                                    <div class="mt-3 max-h-56 space-y-1 overflow-y-auto border-t border-border/50 pt-2">
                                        {#each suggestionRows as row (row.id)}
                                            <button type="button" class="flex min-h-[44px] w-full items-center justify-between gap-3 rounded-lg px-3 text-left text-sm hover:bg-muted" onclick={() => { filterSearch = row.title; applyFilters(); searchOpen = false; }}>
                                                <span class="min-w-0 truncate"><span class="font-medium">{row.title}</span><span class="ml-2 text-xs text-muted-foreground">{row.publisher_label} · {row.kind === 'privacy' ? 'Privacy' : 'Policy'}</span></span><span class="shrink-0 text-xs text-primary">Search</span>
                                            </button>
                                        {/each}
                                    </div>
                                {/if}
                            </div>
                        {/if}
                    </div>
                    <button type="button" class="inline-flex min-h-[44px] items-center justify-between rounded-md border border-input bg-background px-3 text-sm text-foreground shadow-xs md:hidden dark:bg-input/30" aria-expanded={mobileFiltersOpen} aria-controls="policy-mobile-filters" onclick={() => (mobileFiltersOpen = !mobileFiltersOpen)}>
                        <span class="min-w-0 flex-1 truncate">Filters ({(status !== 'all' ? 1 : 0) + (kind !== 'all' ? 1 : 0)})</span><ChevronDown class="shrink-0 ml-1.5" />
                    </button>
                    <div id="policy-mobile-filters" class={mobileFiltersOpen ? 'grid grid-cols-1 gap-3 md:flex md:items-end' : 'hidden md:flex md:items-end'}>
                        <label class="relative flex min-w-0 flex-1 flex-col gap-1.5">
                            <span class="text-xs font-medium text-muted-foreground">Lifecycle status</span>
                            <span class="flex min-w-0 items-center rounded-md border border-input bg-background shadow-xs dark:bg-input/30">
                                <select bind:value={status} onchange={applyFilters} class="min-w-0 flex-1 truncate appearance-none bg-transparent min-h-10 px-3 pr-1 text-xs text-foreground">
                                    {#each statuses as option (option.value)}
                                        <option value={option.value}>{option.label} ({statusCounts[option.value]})</option>
                                    {/each}
                                </select>
                                <ChevronDown class="shrink-0 ml-1.5 mr-2 size-4 text-muted-foreground" />
                            </span>
                        </label>
                        <label class="relative flex min-w-0 flex-1 flex-col gap-1.5">
                            <span class="text-xs font-medium text-muted-foreground">Policy kind</span>
                            <span class="flex min-w-0 items-center rounded-md border border-input bg-background shadow-xs dark:bg-input/30">
                                <select bind:value={kind} onchange={applyFilters} class="min-w-0 flex-1 truncate appearance-none bg-transparent min-h-10 px-3 pr-1 text-xs text-foreground">
                                    <option value="all">All policy kinds</option>
                                    <option value="policy">Policy</option>
                                    <option value="privacy">Privacy</option>
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
            <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
                <p class="text-sm font-semibold">Policy catalog</p>
                <p class="text-muted-foreground text-sm" aria-live="polite">
                    {policies.total ?? rows.length} document{(policies.total ??
                        rows.length) === 1
                        ? ''
                        : 's'}
                </p>
            </div>

            {#snippet rowActions(row)}
                <ActionDropdown items={actionItems(row)} />
            {/snippet}

            {#if rows.length === 0}
                <div
                    class="border-border rounded-lg border border-dashed p-8 text-center"
                    role="status"
                >
                    <h2 class="font-semibold">
                        {policies.total > 0 && kind === 'all'
                            ? 'No policies on this page.'
                            : hasActiveFilters
                              ? 'No policies match these filters in your authorized scope.'
                              : 'No policies in this authorized scope yet.'}
                    </h2>
                    <p class="text-muted-foreground mt-1 text-sm">
                        {policies.total > 0 && kind === 'all'
                            ? 'Return to the first page to continue browsing.'
                            : hasActiveFilters
                              ? 'Change or clear a filter to see other policies in your authorized scope.'
                              : 'Create a draft when you are ready to prepare an authorized public document.'}
                    </p>
                    {#if policies.total > 0 && kind === 'all'}
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
                            href="/communications/policies/create"
                            class="mt-4 min-h-11">New policy</Button
                        >
                    {/if}
                </div>
                {#if policies.total > 0}
                    <SimplePagination data={policies} />
                {/if}
            {:else}
                <SwitchableListView bind:viewMode cardsInset={false}>
                    {#snippet table()}
                        <div class="overflow-x-auto">
                            <Table.Root class="w-full min-w-[1100px] text-sm">
                                <Table.Header class="bg-muted/40 border-b border-border">
                                    <Table.Row class="border-b border-border/40 hover:bg-muted/30 transition-colors">
                                        <Table.Head class="px-4 py-3 text-left align-middle" aria-sort={sortState('title')}>
                                            <button type="button" onclick={() => toggleSort('title')} class="inline-flex min-h-[44px] items-center gap-1.5 rounded-sm font-medium hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                                                <span>Title</span>
                                                <svelte:component this={sortIcon('title')} class={sort === 'title' ? 'size-3.5 text-primary' : 'size-3.5 opacity-40'} aria-hidden="true" />
                                            </button>
                                        </Table.Head>
                                        <Table.Head class="px-4 py-3 text-left align-middle" aria-sort={sortState('placement')}>
                                            <button type="button" onclick={() => toggleSort('placement')} class="inline-flex min-h-[44px] items-center gap-1.5 rounded-sm font-medium hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                                                <span>Placement</span>
                                                <svelte:component this={sortIcon('placement')} class={sort === 'placement' ? 'size-3.5 text-primary' : 'size-3.5 opacity-40'} aria-hidden="true" />
                                            </button>
                                        </Table.Head>
                                        <Table.Head class="px-4 py-3 text-left align-middle" aria-sort={sortState('status')}>
                                            <button type="button" onclick={() => toggleSort('status')} class="inline-flex min-h-[44px] items-center gap-1.5 rounded-sm font-medium hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                                                <span>Lifecycle Status</span>
                                                <svelte:component this={sortIcon('status')} class={sort === 'status' ? 'size-3.5 text-primary' : 'size-3.5 opacity-40'} aria-hidden="true" />
                                            </button>
                                        </Table.Head>
                                        <Table.Head class="px-4 py-3 text-left align-middle" aria-sort={sortState('published_at')}>
                                            <button type="button" onclick={() => toggleSort('published_at')} class="inline-flex min-h-[44px] items-center gap-1.5 rounded-sm font-medium hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                                                <span>Publication</span>
                                                <svelte:component this={sortIcon('published_at')} class={sort === 'published_at' ? 'size-3.5 text-primary' : 'size-3.5 opacity-40'} aria-hidden="true" />
                                            </button>
                                        </Table.Head>
                                        <Table.Head class="px-4 py-3 text-left align-middle" aria-sort={sortState('issuer')}>
                                            <button type="button" onclick={() => toggleSort('issuer')} class="inline-flex min-h-[44px] items-center gap-1.5 rounded-sm font-medium hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                                                <span>Issuer</span>
                                                <svelte:component this={sortIcon('issuer')} class={sort === 'issuer' ? 'size-3.5 text-primary' : 'size-3.5 opacity-40'} aria-hidden="true" />
                                            </button>
                                        </Table.Head>
                                        <Table.Head class="px-4 py-3 text-right align-middle">Actions</Table.Head>
                                    </Table.Row>
                                </Table.Header>
                                <Table.Body>
                                    {#each sortedRows as row (row.id)}
                                        <Table.Row class="border-b border-border/40 hover:bg-muted/30 transition-colors">
                                            <Table.Cell class="max-w-[24rem] px-4 py-3 align-middle">
                                                <Link href={`/communications/policies/${row.id}/edit`} class="text-primary font-semibold underline-offset-4 hover:underline">{row.title}</Link>
                                                <div class="mt-2 flex flex-wrap gap-1">
                                                    <Badge variant="outline">{row.kind === 'privacy' ? 'Privacy' : row.kind === 'policy' ? 'Policy' : 'Unknown kind'}</Badge>
                                                    {#if row.is_designated_intake}
                                                        {#if row.is_valid_intake_selection}<Badge variant="secondary">Application Privacy Policy</Badge>{:else}<Badge variant="destructive">Selected · applications unavailable</Badge>{/if}
                                                    {/if}
                                                    {#if row.kind === 'privacy' && row.status === 'published' && row.published_kind == null}<Badge variant="outline">Privacy snapshot needed</Badge>{/if}
                                                </div>
                                                {#if row.status === 'withdrawn' && row.withdrawal_reason}
                                                    <p class="text-muted-foreground mt-2 text-xs break-words">Withdrawal reason: {row.withdrawal_reason}</p>
                                                {/if}
                                            </Table.Cell>
                                            <Table.Cell class="px-4 py-3 align-middle">{row.program_code ? `${row.program_code} program` : 'General'}</Table.Cell>
                                            <Table.Cell class="px-4 py-3 align-middle whitespace-nowrap">
                                                <Badge variant={row.status === 'published' ? 'default' : 'secondary'}>{policyStatusLabel(row.status)}</Badge>
                                            </Table.Cell>
                                            <Table.Cell class="min-w-44 px-4 py-3 align-middle text-sm">
                                                <div>{dateLabel(row.published_at)}</div>
                                                <p class="text-muted-foreground mt-0.5 font-mono text-xs">
                                                    v{row.revision}{hasPendingDraft(row) ? ' · draft' : ''} · {row.published_revision ? `Public v${row.published_revision}` : 'Not published'}
                                                </p>
                                            </Table.Cell>
                                            <Table.Cell class="min-w-36 px-4 py-3 align-middle">{row.publisher_label}</Table.Cell>
                                            <Table.Cell class="min-w-52 px-4 py-3 align-middle">{@render rowActions(row)}</Table.Cell>
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
                                    subtitle={`${row.publisher_label} · ${row.placement ?? placementLabel(row)}`}
                                >
                                    {#snippet status()}
                                        <div class="flex shrink-0 flex-col items-end gap-1">
                                            <Badge variant={row.status === 'published' ? 'default' : 'secondary'}>
                                                {policyStatusLabel(row.status)}
                                            </Badge>
                                            <Badge variant="outline">{row.kind === 'privacy' ? 'Privacy' : row.kind === 'policy' ? 'Policy' : 'Unknown kind'}</Badge>
                                            {#if row.is_designated_intake}
                                                {#if row.is_valid_intake_selection}<Badge variant="secondary">Application Privacy Policy</Badge>{:else}<Badge variant="destructive">Selected · applications unavailable</Badge>{/if}
                                            {/if}
                                            {#if row.kind === 'privacy' && row.status === 'published' && row.published_kind == null}<Badge variant="outline">Privacy snapshot needed</Badge>{/if}
                                        </div>
                                    {/snippet}
                                    {#snippet summary()}
                                        {#if policyExcerpt(row)}
                                            <p class="line-clamp-2 break-words">{policyExcerpt(row)}</p>
                                        {/if}
                                    {/snippet}
                                    {#snippet details()}
                                        <dl class="grid grid-cols-2 gap-x-3 gap-y-2 text-xs">
                                            <div>
                                                <dt class="text-muted-foreground">Revision</dt>
                                                <dd class="font-medium">
                                                    v{row.revision}{hasPendingDraft(row) ? ' · draft' : ''}
                                                </dd>
                                            </div>
                                            <div>
                                                <dt class="text-muted-foreground">Publisher</dt>
                                                <dd class="font-medium">{row.publisher_label}</dd>
                                            </div>
                                            <div class="col-span-2">
                                                <dt class="text-muted-foreground">Program</dt>
                                                <dd class="font-medium">{programDetailsLabel(row)}</dd>
                                            </div>
                                            <div>
                                                <dt class="text-muted-foreground">
                                                    {row.status === 'withdrawn' ? 'Last public' : 'Current public'}
                                                </dt>
                                                <dd class="font-medium">
                                                    {row.published_revision ? `v${row.published_revision}` : 'Not published'}
                                                </dd>
                                            </div>
                                            <div>
                                                <dt class="text-muted-foreground">Published</dt>
                                                <dd>{dateLabel(row.published_at)}</dd>
                                            </div>
                                            <div class="col-span-2">
                                                <dt class="text-muted-foreground">Last updated</dt>
                                                <dd>{timestampLabel(row.updated_at)}</dd>
                                            </div>
                                        </dl>
                                        {#if row.status === 'withdrawn' && row.withdrawal_reason}
                                            <p class="text-muted-foreground break-words">
                                                Withdrawal reason: {row.withdrawal_reason}
                                            </p>
                                        {/if}
                                    {/snippet}
                                    {#snippet actions()}
                                        <div class="flex items-center gap-2">
                                            <Link href={`/communications/policies/${row.id}/edit`} class="flex-1">
                                                <Button variant="default" class="min-h-9 w-full">Edit policy</Button>
                                            </Link>
                                            {@render rowActions(row)}
                                        </div>
                                    {/snippet}
                                </OperationalCard>
                            {/each}
                        </ul>
                    {/snippet}
                </SwitchableListView>
                <SimplePagination data={policies} />
            {/if}
    </div>

    <dialog
        bind:this={withdrawDialog}
        onclose={() => {
            if (!processing) withdrawTarget = null;
        }}
        class="border-border bg-card text-card-foreground m-auto w-[min(92vw,34rem)] rounded-xl border p-0 shadow-xl backdrop:bg-black/50"
    >
        {#if withdrawTarget}
            <form class="space-y-4 p-5 sm:p-6" onsubmit={withdraw}>
                <div>
                    <h2 class="text-lg font-semibold">
                        Withdraw this public policy?
                    </h2>
                    <p class="text-muted-foreground mt-1 text-sm">
                        “{withdrawTarget.title}” will stop appearing publicly.
                        Its released content and history are retained; withdrawn
                        policies cannot be republished.
                    </p>
                </div>
                <label
                    class="grid gap-2 text-sm font-medium"
                    for="withdraw-reason"
                >
                    Reason for withdrawal
                    <textarea
                        id="withdraw-reason"
                        bind:value={withdrawReason}
                        maxlength="1000"
                        required
                        disabled={processing}
                        class="border-input bg-background min-h-28 rounded-md border px-3 py-2 font-normal"
                    ></textarea>
                    <span class="text-muted-foreground text-xs"
                        >{withdrawReason.length}/1000</span
                    >
                </label>
                {#if withdrawError}
                    <p role="alert" class="text-destructive text-sm">
                        {withdrawError}
                    </p>
                {/if}
                <div class="flex flex-wrap justify-end gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        class="min-h-11"
                        disabled={processing}
                        onclick={closeWithdraw}
                    >
                        Keep policy
                    </Button>
                    <Button
                        type="submit"
                        variant="destructive"
                        class="min-h-11"
                        disabled={processing || !withdrawReason.trim()}
                    >
                        {processing ? 'Withdrawing…' : 'Withdraw policy'}
                    </Button>
                </div>
            </form>
        {/if}
    </dialog>
    <dialog
        bind:this={designateDialog}
        onclose={() => {
            if (!processing) designateTarget = null;
        }}
        class="border-border bg-card text-card-foreground m-auto w-[min(92vw,34rem)] rounded-xl border p-0 shadow-xl backdrop:bg-black/50"
    >
        {#if designateTarget}
            <form class="space-y-4 p-5 sm:p-6" onsubmit={designateIntake}>
                <div>
                    <div class="flex items-center gap-1.5">
                        <h2 class="text-lg font-semibold">Designate this Application Privacy Policy?</h2>
                        <InfoPopover
                            title="Intake consent binding"
                            content="Binds all new /apply submissions to record explicit applicant consent against this exact published revision."
                        />
                    </div>
                    <p class="mt-1 text-sm"><span class="font-medium">New selection:</span> “{designateTarget.published_title ?? 'Published title unavailable'}” · Version {designateTarget.published_revision}</p>
                    {#if current_intake_selection}
                        <p class="mt-2 text-sm"><span class="font-medium">Current selection:</span> “{current_intake_selection.title ?? 'Published title unavailable'}”{#if current_intake_selection.revision} · Version {current_intake_selection.revision}{/if} · {current_intake_selection.status}</p>
                    {:else}
                        <p class="mt-2 text-sm"><span class="font-medium">Current selection:</span> None</p>
                    {/if}
                    <p class="mt-2 text-muted-foreground text-sm">This changes only the institution's Application Privacy Policy selection. It does not edit or republish policy content, and historical applicant consent receipts remain unchanged.</p>
                </div>
                {#if designateError}
                    <p role="alert" class="text-destructive text-sm">{designateError}</p>
                {/if}
                <div class="flex flex-wrap justify-end gap-2">
                    <Button type="button" variant="outline" class="min-h-11" disabled={processing} onclick={closeDesignate}>Cancel</Button>
                    <Button type="submit" variant="default" class="min-h-11" disabled={processing || !designateTarget.can_designate_intake}>
                        {processing ? 'Updating Application Privacy Policy…' : 'Designate Application Privacy Policy'}
                    </Button>
                </div>
            </form>
        {/if}
    </dialog>
    {#if privacyModalOpen && current_intake_selection}
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true">
            <div class="w-full max-w-2xl rounded-xl border border-border bg-card p-5 sm:p-6 shadow-xl space-y-4">
                <div class="flex items-center justify-between border-b border-border pb-3">
                    <div>
                        <h3 class="font-semibold text-lg">Edit Application Privacy Policy</h3>
                        <p class="text-xs text-muted-foreground">Draft revision {current_intake_selection.revision}</p>
                    </div>
                    <Button size="icon" variant="ghost" class="h-8 w-8" onclick={() => privacyModalOpen = false}>
                        <X class="size-4" />
                    </Button>
                </div>

                <form onsubmit={savePrivacyPolicy} class="space-y-4">
                    <div class="space-y-1.5">
                        <Label for="privacy-modal-title">Title</Label>
                        <Input id="privacy-modal-title" bind:value={privacyTitle} maxlength="160" required />
                    </div>

                    <div class="space-y-1.5">
                        <Label for="privacy-modal-content">Notice content (plain text)</Label>
                        <textarea
                            id="privacy-modal-content"
                            bind:value={privacyContent}
                            rows="9"
                            maxlength="10000"
                            required
                            class="border-input bg-background w-full rounded-md border p-3 text-sm focus-visible:outline-2 focus-visible:outline-ring"
                        ></textarea>
                    </div>

                    {#if current_intake_selection.revisions && current_intake_selection.revisions.length > 0}
                        <div class="space-y-1">
                            <span class="text-xs font-medium text-muted-foreground">Revision history</span>
                            <div class="max-h-24 overflow-y-auto divide-y divide-border/40 text-xs rounded border border-border/50 px-2.5">
                                {#each current_intake_selection.revisions as rev}
                                    <div class="py-1 flex items-center justify-between">
                                        <span>Rev {rev.revision} · {rev.state}</span>
                                        <span class="text-muted-foreground">{rev.actor_name ?? ''} · {dateLabel(rev.created_at)}</span>
                                    </div>
                                {/each}
                            </div>
                        </div>
                    {/if}

                    <div class="flex items-center justify-end gap-2 pt-2 border-t border-border">
                        <Button type="button" variant="outline" onclick={() => privacyModalOpen = false}>Cancel</Button>
                        <Button type="submit" disabled={privacySaving}>
                            {privacySaving ? 'Saving…' : 'Save Draft'}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    {/if}
</StaffLayout>
