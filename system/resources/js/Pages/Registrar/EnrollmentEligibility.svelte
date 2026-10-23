<script>
    /**
     * Registrar status search + eligibility card (A3).
     * Students may check their status at the Registrar counter without phones.
     * Status facts only — assessment scores and counselor notes stay Guidance-owned.
     * Extended for Phase 3 Admission Clearance and Requirement Verification.
     */
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
import DocumentRequirementsPanel from '@/Components/Admission/DocumentRequirementsPanel.svelte';
import FilterToolbar from '@/Components/kit/FilterToolbar.svelte';
import SimplePagination from '@/Components/kit/SimplePagination.svelte';
import PrintDocumentsSlot from '@/Components/DocumentTemplate/PrintDocumentsSlot.svelte';
import ActiveFilterChips from '@/Components/kit/ActiveFilterChips.svelte';
import BulkActionBar from '@/Components/kit/BulkActionBar.svelte';
import SwitchableListView from '@/Components/kit/SwitchableListView.svelte';
import * as Table from '@/Components/ui/table';
import * as Select from '@/Components/ui/select';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import * as Card from '@/Components/ui/card';
import * as Dialog from '@/Components/ui/dialog';
import { Textarea } from '@/Components/ui/textarea';
import ReasonSuggestionChips from '@/Components/kit/ReasonSuggestionChips.svelte';
import { formatEnumLabel } from '@/lib/date-utils.js';
import { REGISTRAR_CLEARANCE_REVOKE_SUGGESTIONS } from '@/lib/reason-suggestions.js';
    import { Link, router, usePage } from '@inertiajs/svelte';
import ApplicantActionsMenu from '@/Components/Admission/ApplicantActionsMenu.svelte';
    import { newIdempotencyKey } from '@/lib/utils';
    import {
        CheckCircle2,
        ArrowLeft,
        XCircle,
        ArrowUp,
        ArrowDown,
        FileCheck2,
        RotateCcw,
        History,
        ShieldAlert,
    } from 'lucide-svelte';

    let {
        page_state = 'search',
        query = '',
        results = [],
        card = null,
        filters = {},
        sort_options = [],
        counts = { all: 0, ready: 0, confirmed: 0, pending_requirements: 0 },
        pagination = null,
    } = $props();
    const page = usePage();
    const canReopenClearance = $derived((page.props.auth?.user?.capabilities ?? []).includes('registrar.admission.clearance.reopen'));

    let searchTerm = $state(filters.search ?? query ?? '');
    let filterStatus = $state(filters.status ?? 'all');
    let filterSort = $state(filters.sort ?? 'id');
    let filterDir = $state(filters.dir ?? 'desc');

    const defaultSortOptions = [
        { value: 'id', label: 'ID' },
        { value: 'name', label: 'Applicant Name' },
        { value: 'reference_code', label: 'Reference No.' },
    ];
    const sortOptions = $derived(
        sort_options && sort_options.length > 0
            ? sort_options
            : defaultSortOptions,
    );

    const statusOptions = $derived([
        { value: 'all', label: `All (${counts?.all ?? 0})` },
        { value: 'ready', label: `Ready for Clearance (${counts?.ready ?? 0})` },
        { value: 'confirmed', label: `Clearance Confirmed (${counts?.confirmed ?? 0})` },
        { value: 'pending_requirements', label: `Prerequisites Incomplete (${counts?.pending_requirements ?? 0})` },
    ]);

    const rows = $derived(
        Array.isArray(results) ? results : (results?.data ?? []),
    );
    let selectedClearanceIds = $state([]);
    let viewMode = $state('responsive');
    const activeFilterChips = $derived([
        ...(searchTerm.trim() ? [{ id: 'search', label: `Search: ${searchTerm.trim()}` }] : []),
        ...(filterStatus !== 'all' ? [{ id: 'status', label: statusOptions.find((option) => option.value === filterStatus)?.label ?? filterStatus }] : []),
    ]);

    function clearListFilter(id) {
        if (!id || id === 'search') searchTerm = '';
        if (!id || id === 'status') filterStatus = 'all';
        applyFilters({ search: searchTerm, status: filterStatus });
    }

    function isRowConfirmed(row) {
        return row.clearance_status === 'confirmed' || row.status === 'confirmed';
    }
    const confirmedClearanceRows = $derived(rows.filter(isRowConfirmed));
    const selectedConfirmedIds = $derived(
        confirmedClearanceRows.filter((row) => selectedClearanceIds.includes(row.id)).map((row) => row.id),
    );
    const ineligibleSelectedCount = $derived(selectedClearanceIds.length - selectedConfirmedIds.length);
    const allOnPageSelected = $derived(
        rows.length > 0 && rows.every((row) => selectedClearanceIds.includes(row.id)),
    );

    $effect(() => {
        const visibleIds = new Set(rows.map((row) => row.id));
        const remainingIds = selectedClearanceIds.filter((id) =>
            visibleIds.has(id),
        );
        if (remainingIds.length !== selectedClearanceIds.length) {
            selectedClearanceIds = remainingIds;
        }
    });
    let isSubmitting = $state(false);
    let clearanceOperationKeys = $state({});

    function clearanceKey(command, payload) {
        const signature = `${command}:${JSON.stringify(payload)}`;
        clearanceOperationKeys[signature] ??= newIdempotencyKey();
        return clearanceOperationKeys[signature];
    }

    // Clearance action states
    let isRevokeDialogOpen = $state(false);
    let revokeReason = $state('');

    function toggleClearanceSelection(attemptId, checked) {
        selectedClearanceIds = checked
            ? [...new Set([...selectedClearanceIds, attemptId])]
            : selectedClearanceIds.filter((id) => id !== attemptId);
    }

    function toggleAllOnPage() {
        selectedClearanceIds = allOnPageSelected ? [] : rows.map((row) => row.id);
    }
    function applyFilters(overrides = {}) {
        const search =
            overrides.search !== undefined ? overrides.search : searchTerm;
        const status =
            overrides.status !== undefined ? overrides.status : filterStatus;
        const sort =
            overrides.sort !== undefined ? overrides.sort : filterSort;
        const dir =
            overrides.dir !== undefined ? overrides.dir : filterDir;

        selectedClearanceIds = [];
        router.get(
            '/registrar/enrollment-eligibility',
            {
                q: search || undefined,
                search: search || undefined,
                status: status && status !== 'all' ? status : undefined,
                sort: sort !== 'id' || dir !== 'desc' ? sort : undefined,
                dir: sort !== 'id' || dir !== 'desc' ? dir : undefined,
                page: 1,
            },
            {
                preserveState: true,
                replace: true,
            },
        );

    }
    function runSearch() {
        applyFilters({ search: searchTerm });
    }

    function handleSortChange({ sort: nextSort, dir: nextDir }) {
        filterSort = nextSort;
        filterDir = nextDir;
        applyFilters({ sort: nextSort, dir: nextDir });
    }

    function handleStatusChange(nextStatus) {
        filterStatus = nextStatus;
        applyFilters({ status: nextStatus });
    }

    const factLabels = {
        accepted: 'Application accepted',
        result_ready: 'Exam completed and scored',
        result_released: 'Result released',
        advised: 'Result released',
    };
    const milestones = [
        { key: 'accepted', label: 'Application accepted' },
        { key: 'result_ready', label: 'Exam completed and scored' },
        { key: 'advised', label: 'Result released' },
    ];

    function formatDate(iso) {
        if (!iso) return null;
        return new Date(iso).toLocaleDateString(undefined, {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    }

    const missingFactLabels = $derived(
        (card?.missing ?? []).map((k) => factLabels[k] ?? k),
    );

    const clearanceReadiness = $derived(card?.clearance_readiness ?? null);
    const isClearanceReady = $derived(clearanceReadiness?.is_ready ?? false);
    const latestClearance = $derived(card?.latest_clearance ?? null);
    const latestHandoff = $derived(card?.historical_latest_sheet_handoff ?? null);
    const isClearanceConfirmed = $derived(
        latestClearance?.status === 'confirmed',
    );

    const eligibilityContext = $derived.by(() => {
        const isCard = page_state === 'card' && Boolean(card);
        const safetyKey = `${page_state}:${query ? 'q' : 'noq'}:${results?.length ?? 0}:${isCard ? (card.eligible ? 'elig' : 'inelig') : 'search'}`;

        return {
            entityKey: isCard
                ? String(card.id ?? card.reference_number ?? 'card')
                : 'search',
            safetyKey,
            freshness: 'current',
            pageState: page_state,
            hasQuery: Boolean(query && query.trim().length > 0),
            resultsCount: results?.length ?? 0,
            hasCard: isCard,
            candidateName: card?.name ?? null,
            isEligible: Boolean(card?.eligible),
            missingFacts: missingFactLabels,
        };
    });

    function confirmClearance() {
        if (!isClearanceReady || isClearanceConfirmed || isSubmitting) return;
        isSubmitting = true;

        const content = {
            expected_version: card.version ?? card.expected_version,
        };
        const signaturePayload = {
            ...content,
            command: 'confirm',
            attempt_id: card.id,
        };
        router.post(
            `/registrar/enrollment-eligibility/${card.id}/clearance/confirm`,
            {
                ...content,
                idempotency_key: clearanceKey('confirm', signaturePayload),
            },
            {
                preserveScroll: true,
                onFinish: () => {
                    isSubmitting = false;
                },
            },
        );
    }

    function submitRevocation() {
        if (!revokeReason.trim() || isSubmitting) return;
        isSubmitting = true;
        const content = {
            expected_version: card.version ?? card.expected_version,
            reason: revokeReason.trim(),
        };
        const signaturePayload = {
            ...content,
            command: 'reopen',
            attempt_id: card.id,
        };

        router.post(
            `/registrar/enrollment-eligibility/${card.id}/clearance/revoke`,
            {
                ...content,
                idempotency_key: clearanceKey('reopen', signaturePayload),
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    isRevokeDialogOpen = false;
                    revokeReason = '';
                },
                onFinish: () => {
                    isSubmitting = false;
                },
            },
        );
    }

</script>

<svelte:head><title>Admission Clearance · NEXIAM</title></svelte:head>

{#snippet clearanceBadge(status)}
    <Badge variant={status === 'confirmed' ? 'default' : 'secondary'} class="max-w-full whitespace-normal text-xs">
        {status === 'confirmed' ? 'Clearance confirmed'
            : status === 'ready' ? 'Ready for clearance'
            : status === 'revoked' ? 'Clearance reopened'
            : 'Prerequisites incomplete'}
    </Badge>
{/snippet}

{#snippet rowSelection(row)}
    <label
        class="grid min-h-[44px] min-w-[44px] shrink-0 place-items-center"
        title="Select applicant for bulk actions"
    >
        <input
            type="checkbox"
            checked={selectedClearanceIds.includes(row.id)}
            aria-label={`Select applicant ${row.name}`}
            onchange={(event) => toggleClearanceSelection(row.id, event.currentTarget.checked)}
            class="size-4 rounded border-input text-primary focus:ring-ring disabled:cursor-not-allowed disabled:opacity-40"
        />
    </label>
{/snippet}

<StaffLayout breadcrumbs={[{ label: 'Admission Clearance', ...(card ? { href: '/registrar/enrollment-eligibility' } : {}) }, ...(card ? [{ label: card.name }] : [])]}>
    <div class="min-w-0 space-y-6 p-4 sm:p-6">
        <PageHeaderBar
            title="Admission Clearance"
            tourKey="enrollment-eligibility"
            context={eligibilityContext}
        />

        {#if page_state === 'search'}
            <div class="sticky top-0 z-20 bg-background/90 py-2 backdrop-blur-md" data-tour="eligibility-table">
                <FilterToolbar
                    bind:search={searchTerm}
                    inputId="clearance-search"
                    searchLabel="Search applicants"
                    searchPlaceholder="Search name, reference number, or email…"
                    onSearchSubmit={runSearch}
                    onSearchClear={() => clearListFilter('search')}
                    {sortOptions}
                    sortValue={filterSort}
                    sortDir={filterDir}
                    onSortChange={handleSortChange}
                    mobileFilterCount={filterStatus === 'all' ? 0 : 1}
                >
                    {#snippet desktopFilters()}
                        <Select.Root type="single" value={filterStatus} onValueChange={(value) => handleStatusChange(value ?? 'all')}>
                            <Select.Trigger aria-label="Filter by clearance status" class="min-h-10 w-64">
                                {statusOptions.find((option) => option.value === filterStatus)?.label ?? 'All statuses'}
                            </Select.Trigger>
                            <Select.Content>
                                {#each statusOptions as option}
                                    <Select.Item value={option.value} label={option.label}>{option.label}</Select.Item>
                                {/each}
                            </Select.Content>
                        </Select.Root>
                    {/snippet}
                    {#snippet mobileFilters()}
                        <div class="space-y-1.5">
                            <label for="clearance-status-mobile" class="text-sm font-medium">Clearance status</label>
                            <Select.Root type="single" value={filterStatus} onValueChange={(value) => handleStatusChange(value ?? 'all')}>
                                <Select.Trigger id="clearance-status-mobile" aria-label="Filter by clearance status" class="min-h-[44px] w-full">
                                    {statusOptions.find((option) => option.value === filterStatus)?.label ?? 'All statuses'}
                                </Select.Trigger>
                                <Select.Content>
                                    {#each statusOptions as option}
                                        <Select.Item value={option.value} label={option.label}>{option.label}</Select.Item>
                                    {/each}
                                </Select.Content>
                            </Select.Root>
                        </div>
                    {/snippet}
                    {#snippet actions()}
                        <Button type="button" class="min-h-[44px]" onclick={runSearch}>Search</Button>
                    {/snippet}
                </FilterToolbar>
            </div>

            <ActiveFilterChips chips={activeFilterChips} onRemove={clearListFilter} onClearAll={() => clearListFilter()} />
            {#if selectedClearanceIds.length > 0}
            <section class="space-y-2" aria-labelledby="clearance-bulk-heading">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 id="clearance-bulk-heading" class="text-sm font-semibold">Bulk actions</h2>
                    <div class="flex flex-wrap gap-2">
                        <Button type="button" variant="outline" class="min-h-[44px]" disabled={!rows.length} onclick={toggleAllOnPage}>
                            {allOnPageSelected ? 'Deselect page' : 'Select all on page'}
                        </Button>
                        <Button type="button" variant="ghost" class="min-h-[44px]" disabled={!confirmedClearanceRows.length} onclick={() => (selectedClearanceIds = confirmedClearanceRows.map((row) => row.id))}>
                            Select confirmed only
                        </Button>
                    </div>
                </div>
                <p class="text-sm text-muted-foreground" role="status" aria-live="polite">
                    {selectedClearanceIds.length} selected · {selectedConfirmedIds.length} eligible for clearance printing · {ineligibleSelectedCount} ineligible
                </p>
                <BulkActionBar count={selectedClearanceIds.length} onClear={() => (selectedClearanceIds = [])}>
                    {#snippet actions()}
                        {#if selectedConfirmedIds.length}
                            <PrintDocumentsSlot slotKey="registrar_enrollment_clearance" attemptIds={selectedConfirmedIds} autoStart={false} />
                        {:else}
                            <Button type="button" variant="outline" class="min-h-[44px]" disabled>Print confirmed clearances (0)</Button>
                        {/if}
                    {/snippet}
                </BulkActionBar>
            </section>
            {/if}

            {#if rows.length === 0}
                <div class="rounded-xl border border-dashed border-border px-6 py-12 text-center">
                    <h2 class="text-base font-semibold">{activeFilterChips.length ? 'No matching applicants' : 'No applicants available'}</h2>
                    {#if activeFilterChips.length}
                        <Button variant="outline" class="mt-4 min-h-[44px]" onclick={() => clearListFilter()}>Clear filters</Button>
                    {/if}
                </div>
            {:else}
                <SwitchableListView bind:viewMode cardsInset={false}>
                    {#snippet controls()}
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
                            <label class="flex min-h-[44px] items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    checked={allOnPageSelected}
                                    indeterminate={selectedClearanceIds.length > 0 && !allOnPageSelected}
                                    disabled={rows.length === 0}
                                    onchange={toggleAllOnPage}
                                    class="size-4 rounded border-input text-primary focus:ring-ring disabled:opacity-40"
                                />
                                Select all on page ({rows.length})
                            </label>
                        </div>
                    {/snippet}
                    {#snippet table()}
                        <Table.Root class="w-full min-w-[900px] text-sm">
                            <Table.Header class="border-b border-border bg-muted/40">
                                <Table.Row>
                                    <Table.Head class="w-12 px-2"><span class="sr-only">Select clearance</span></Table.Head>
                                    <Table.Head aria-sort={filterSort === 'name' ? (filterDir === 'asc' ? 'ascending' : 'descending') : 'none'}>
                                        <button type="button" class="inline-flex min-h-[44px] items-center gap-1.5" onclick={() => handleSortChange({ sort: 'name', dir: filterSort === 'name' && filterDir === 'asc' ? 'desc' : 'asc' })}>
                                            Applicant {#if filterSort === 'name'}{#if filterDir === 'asc'}<ArrowUp class="size-3.5" />{:else}<ArrowDown class="size-3.5" />{/if}{/if}
                                        </button>
                                    </Table.Head>
                                    <Table.Head>Reference number</Table.Head>
                                    <Table.Head>Program preference</Table.Head>
                                    <Table.Head>Application status</Table.Head>
                                    <Table.Head>Clearance status</Table.Head>
                                    <Table.Head class="w-px text-right">Action</Table.Head>
                                </Table.Row>
                            </Table.Header>
                            <Table.Body>
                                {#each rows as row (row.id)}
                                    <Table.Row class="border-b border-border/40 align-middle hover:bg-muted/30">
                                        <Table.Cell class="px-2">{@render rowSelection(row)}</Table.Cell>
                                        <Table.Cell class="font-medium">{row.name}</Table.Cell>
                                        <Table.Cell class="font-mono text-xs">{row.reference_number}</Table.Cell>
                                        <Table.Cell>{row.preferred_program_code ?? 'Not recorded'}</Table.Cell>
                                        <Table.Cell>{formatEnumLabel(row.status)}</Table.Cell>
                                        <Table.Cell>{@render clearanceBadge(row.clearance_status ?? row.status)}</Table.Cell>
                                        <Table.Cell class="text-right">
                                            <div class="flex items-center justify-end gap-2">
                                                <ApplicantActionsMenu attemptId={row.id} />
                                                <Link href={`/registrar/enrollment-eligibility/${row.id}`}><Button variant="outline" class="min-h-[44px] whitespace-nowrap">Review clearance</Button></Link>
                                            </div>
                                        </Table.Cell>
                                    </Table.Row>
                                {/each}
                            </Table.Body>
                        </Table.Root>
                    {/snippet}
                    {#snippet cards()}
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                            {#each rows as row (row.id)}
                                <article class="flex min-w-0 flex-col gap-4 rounded-xl border border-border bg-card p-4">
                                    <div class="flex min-w-0 items-start justify-between gap-2">
                                        <div class="min-w-0 space-y-1">
                                            <h2 class="break-words text-base font-semibold">{row.name}</h2>
                                            <p class="break-all font-mono text-xs text-muted-foreground">{row.reference_number}</p>
                                        </div>
                                        {@render rowSelection(row)}
                                    </div>
                                    <dl class="grid grid-cols-2 gap-3 text-sm">
                                        <div class="min-w-0"><dt class="text-xs text-muted-foreground">Program preference</dt><dd class="mt-1 break-words font-medium">{row.preferred_program_code ?? 'Not recorded'}</dd></div>
                                        <div class="min-w-0"><dt class="text-xs text-muted-foreground">Application status</dt><dd class="mt-1 break-words">{formatEnumLabel(row.status)}</dd></div>
                                    </dl>
                                    <div>{@render clearanceBadge(row.clearance_status ?? row.status)}</div>
                                    <div class="mt-auto flex flex-col gap-2 border-t border-border/60 pt-3">
                                        <ApplicantActionsMenu attemptId={row.id} class="self-end" />
                                        <Link href={`/registrar/enrollment-eligibility/${row.id}`}><Button variant="outline" class="min-h-[44px] w-full">Review clearance</Button></Link>
                                    </div>
                                </article>
                            {/each}
                        </div>
                    {/snippet}
                </SwitchableListView>
                {#if pagination}<SimplePagination data={pagination} />{/if}
            {/if}
        {:else if page_state === 'card' && card}
            <Link href="/registrar/enrollment-eligibility" class="inline-flex">
                <Button variant="ghost" class="min-h-[44px] gap-2"><ArrowLeft class="size-4" />Back to clearance list</Button>
            </Link>

            <section class="flex flex-col gap-3 border-b border-border pb-5 sm:flex-row sm:items-center sm:justify-between" aria-label="Applicant clearance summary">
                <div class="min-w-0 space-y-1">
                    <h2 class="break-words text-xl font-semibold">{card.name}</h2>
                    <p class="break-all font-mono text-sm text-muted-foreground">{card.reference_number}</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <ApplicantActionsMenu attemptId={card.id} />
                    {@render clearanceBadge(isClearanceConfirmed ? 'confirmed' : isClearanceReady ? 'ready' : latestClearance?.status)}
                </div>
            </section>

            <div class="grid min-w-0 grid-cols-1 items-start gap-6 lg:grid-cols-12">
                <div class="min-w-0 space-y-6 lg:col-span-6">
                    <Card.Root>
                        <Card.Header class="border-b border-border/60">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <Card.Title class="text-base">Application milestones</Card.Title>
                                <Badge variant={card.eligible ? 'default' : 'secondary'}>{card.eligible ? 'Complete' : 'Pending'}</Badge>
                            </div>
                            <Card.Description>Review the prerequisite facts before confirming clearance.</Card.Description>
                        </Card.Header>
                        <Card.Content class="space-y-6 pt-5">
                            <ul class="divide-y divide-border/60" data-tour="pre-clearance-milestones">
                                {#each milestones as { key, label }}
                                    <li class="flex flex-wrap items-center justify-between gap-2 py-3 first:pt-0 last:pb-0 text-sm">
                                        <span>{label}</span>
                                        {#if card.facts?.[key]}
                                            <span class="inline-flex items-center gap-1.5 text-emerald-700 dark:text-emerald-400">
                                                <CheckCircle2 class="size-4 shrink-0" />
                                                {key === 'advised' ? 'Released' : 'Done'}
                                            </span>
                                        {:else}
                                            <span class="inline-flex items-center gap-1.5 text-muted-foreground"><XCircle class="size-4 shrink-0" />{key === 'advised' ? 'Pending' : 'Missing'}</span>
                                        {/if}
                                    </li>
                                {/each}
                            </ul>
                            <section class="space-y-3 border-t border-border/60 pt-5" aria-labelledby="clearance-program-heading">
                                <h3 id="clearance-program-heading" class="text-sm font-semibold">Program choice and follow-ups</h3>
                                {#if card.selection}
                                    <dl class="space-y-3 text-sm">
                                        <div class="flex flex-wrap justify-between gap-2"><dt class="text-muted-foreground">Selected program</dt><dd class="font-semibold">{card.selection.program_code}</dd></div>
                                        <div class="flex flex-wrap justify-between gap-2"><dt class="text-muted-foreground">Selection status</dt><dd>{formatEnumLabel(card.selection.status)}</dd></div>
                                        <div class="flex flex-wrap justify-between gap-2"><dt class="text-muted-foreground">Selection source</dt><dd>{formatEnumLabel(card.selection.source)}</dd></div>
                                        <div class="flex flex-wrap justify-between gap-2"><dt class="text-muted-foreground">Applicant acknowledged</dt><dd>{card.selection.acknowledged ? 'Yes' : 'Pending'}</dd></div>
                                    </dl>
                                {:else}
                                    <p class="text-sm text-muted-foreground">No program selection recorded.</p>
                                {/if}
                                {#if card.follow_ups && card.follow_ups.length > 0}
                                    <div class="space-y-2 rounded-lg border border-border p-3 text-sm">
                                        <p class="font-medium">Targeted follow-ups pending</p>
                                        <ul class="list-inside list-disc space-y-1 text-muted-foreground">
                                            {#each card.follow_ups as followUp}<li>{followUp.program_code}: {formatEnumLabel(followUp.status)}</li>{/each}
                                        </ul>
                                    </div>
                                {/if}
                            </section>
                            {#if clearanceReadiness && !clearanceReadiness.is_ready && clearanceReadiness.blocking_reasons?.length > 0}
                                <section class="space-y-2 border-t border-border/60 pt-5" aria-labelledby="clearance-blockers-heading">
                                    <h3 id="clearance-blockers-heading" class="flex items-center gap-2 text-sm font-semibold"><ShieldAlert class="size-4 shrink-0 text-amber-600 dark:text-amber-400" />Outstanding prerequisites</h3>
                                    <ul class="list-inside list-disc space-y-2 text-sm text-muted-foreground">
                                        {#each clearanceReadiness.blocking_reasons as reason}<li class="break-words">{reason}</li>{/each}
                                    </ul>
                                </section>
                            {/if}
                        </Card.Content>
                    </Card.Root>
                </div>

                <div class="min-w-0 space-y-6 lg:col-span-6">
                    <div data-tour="document-requirements-panel">
                        <DocumentRequirementsPanel
                            attemptId={card.id}
                            checklist={card.document_checklist ?? card.checklist}
                            requestHistory={card.document_checklist?.request_history ?? card.request_history ?? []}
                            expectedVersion={card.document_checklist?.expected_version ?? card.version ?? card.expected_version ?? 0}
                            flags={{
                                documents_view: card.document_checklist?.documents_view ?? card.flags?.documents_view,
                                documents_verify: card.document_checklist?.documents_verify ?? card.flags?.documents_verify,
                                documents_request: card.document_checklist?.documents_request ?? card.flags?.documents_request,
                            }}
                            mutationBlockReason={card.document_checklist?.mutation_block_reason ?? card.mutation_block_reason}
                            {isClearanceConfirmed}
                            verifyUrl={`/registrar/enrollment-eligibility/${card.id}/documents/verify`}
                            requestUrl={`/admissions/${card.id}/document-correction-requests`}
                            loading={page_state === 'loading'}
                        />
                    </div>
                    <Card.Root>
                        <Card.Header class="border-b border-border/60">
                            <Card.Title class="flex items-center gap-2 text-base"><FileCheck2 class="size-5 shrink-0 text-primary" />{isClearanceConfirmed ? 'Admission clearance confirmed' : 'Admission clearance'}</Card.Title>
                            <Card.Description>
                                {isClearanceConfirmed ? 'Thank you. Admission processing in NEXIAM is complete. No further clearance action is needed.'
                                    : isClearanceReady ? 'All required checks are satisfied. Clearance can be confirmed.'
                                    : 'Complete the outstanding prerequisites and mandatory document checks before confirmation.'}
                            </Card.Description>
                        </Card.Header>
                        <Card.Content class="space-y-5 pt-5">
                            {#if isClearanceConfirmed}
                                <details class="text-sm">
                                    <summary class="min-h-11 cursor-pointer py-3 font-medium">Confirmation details</summary>
                                    <dl class="grid grid-cols-1 gap-3 py-3 sm:grid-cols-2">
                                    <div><dt class="text-xs text-muted-foreground">Clearance receipt</dt><dd class="mt-1 break-all font-mono">{latestClearance.receipt_number}</dd></div>
                                    <div><dt class="text-xs text-muted-foreground">Admitted program</dt><dd class="mt-1 font-medium">{latestClearance.program_code}</dd></div>
                                    <div><dt class="text-xs text-muted-foreground">Confirmed by</dt><dd class="mt-1 break-words">{latestClearance.confirmed_by_name ?? 'Registrar'}</dd></div>
                                    <div><dt class="text-xs text-muted-foreground">Confirmed on</dt><dd class="mt-1">{formatDate(latestClearance.confirmed_at)}</dd></div>
                                    </dl>
                                </details>
                            {/if}
                            <div class="flex flex-col gap-2">
                                {#if !isClearanceConfirmed}
                                    <Button type="button" data-tour="admission-clearance-action" class="min-h-[44px] h-auto whitespace-normal py-3" disabled={!isClearanceReady || isSubmitting} onclick={confirmClearance}>
                                        <CheckCircle2 class="size-4 shrink-0" />Confirm Admission Clearance
                                    </Button>
                                {:else if canReopenClearance}
                                    <Button type="button" variant="outline" class="min-h-[44px] h-auto whitespace-normal py-3" disabled={isSubmitting} onclick={() => (isRevokeDialogOpen = true)}>
                                        <RotateCcw class="size-4 shrink-0" />Revoke / Reopen Clearance
                                    </Button>
                                {/if}
                            </div>
                            {#if latestHandoff}
                                <details class="border-t border-border/60 pt-3 text-sm">
                                    <summary class="min-h-11 cursor-pointer py-3 font-medium">Historical sheet handoff</summary>
                                    <dl class="space-y-3 py-3">
                                        <div><dt class="text-xs text-muted-foreground">Handoff receipt</dt><dd class="mt-1 break-all font-mono">{latestHandoff.receipt_number}</dd></div>
                                        <div><dt class="text-xs text-muted-foreground">Issued by</dt><dd class="mt-1 break-words">{latestHandoff.issued_by_name}</dd></div>
                                        <div><dt class="text-xs text-muted-foreground">Issued on</dt><dd class="mt-1">{formatDate(latestHandoff.issued_at)}</dd></div>
                                        {#if latestHandoff.paper_reference}<div><dt class="text-xs text-muted-foreground">Paper reference</dt><dd class="mt-1 break-all">{latestHandoff.paper_reference}</dd></div>{/if}
                                    </dl>
                                </details>
                            {/if}
                        </Card.Content>
                    </Card.Root>
                </div>
            </div>

            <details class="rounded-xl border border-border">
                <summary class="flex min-h-[44px] cursor-pointer items-center gap-2 p-4 text-sm font-medium">
                    <History class="size-4 shrink-0" />Legacy enrollment history ({card.enrollment_stamps?.length ?? 0})
                </summary>
                <div class="space-y-3 border-t border-border/60 p-4">
                    <p class="text-xs text-muted-foreground">Historical records only. These stamps are not admission clearance receipts.</p>
                    {#each card.enrollment_stamps ?? [] as stamp (stamp.id)}
                        <div class="space-y-3 rounded-lg border border-border/60 p-3 text-sm">
                            <div class="flex flex-wrap justify-between gap-2"><p class="font-medium">Version {stamp.version}: {formatEnumLabel(stamp.state)}</p><p class="text-xs text-muted-foreground">{formatDate(stamp.created_at) ?? 'Date unavailable'}</p></div>
                            <dl class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                <div><dt class="text-xs text-muted-foreground">Provisional program</dt><dd class="mt-1 break-words">{stamp.provisional_program ?? 'None'}</dd></div>
                                <div><dt class="text-xs text-muted-foreground">Final program</dt><dd class="mt-1 break-words">{stamp.final_program ?? 'None'}</dd></div>
                                <div><dt class="text-xs text-muted-foreground">Paper reference</dt><dd class="mt-1 break-all">{stamp.paper_reference ?? 'None'}</dd></div>
                            </dl>
                            {#if stamp.reason}<p class="break-words text-muted-foreground">Reason: {stamp.reason}</p>{/if}
                        </div>
                    {:else}
                        <p class="text-sm text-muted-foreground">No legacy enrollment history is recorded for this application.</p>
                    {/each}
                </div>
            </details>
            <p class="text-xs text-muted-foreground">Status facts only. Assessment scores and counselor notes are not shown outside Guidance. Every lookup is recorded.</p>
        {/if}
    </div>
</StaffLayout>

<!-- Revocation Dialog -->
<Dialog.Root bind:open={isRevokeDialogOpen}>
    <Dialog.Content class="sm:max-w-md">
        <Dialog.Header>
            <Dialog.Title>Revoke / Reopen Admission Clearance</Dialog.Title>
            <Dialog.Description>
                Provide a mandatory audit reason for revoking confirmed
                clearance. This will reopen prerequisite edits and document
                verification.
            </Dialog.Description>
        </Dialog.Header>

        <div class="space-y-4 py-2">
            <div class="space-y-2">
                <label for="revoke-reason" class="text-sm font-medium"
                    >Revocation Reason</label
                >
                <Textarea
                    id="revoke-reason"
                    placeholder="State reason (e.g. deficient document found, applicant program adjustment authorized)..."
                    bind:value={revokeReason}
                    rows={3}
                />
                <ReasonSuggestionChips
                    suggestions={REGISTRAR_CLEARANCE_REVOKE_SUGGESTIONS}
                    bind:value={revokeReason}
                    maxlength={255}
                />
            </div>
        </div>

        <Dialog.Footer class="gap-2 sm:gap-0">
            <Button
                type="button"
                variant="outline"
                onclick={() => {
                    isRevokeDialogOpen = false;
                    revokeReason = '';
                }}
            >
                Cancel
            </Button>
            <Button
                type="button"
                variant="destructive"
                disabled={!revokeReason.trim() || isSubmitting}
                onclick={submitRevocation}
            >
                Confirm Revocation
            </Button>
        </Dialog.Footer>
    </Dialog.Content>
</Dialog.Root>
