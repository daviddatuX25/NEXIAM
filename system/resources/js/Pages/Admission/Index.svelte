<script>
    /**
     * Registrar applications queue — action visibility follows legacy Index + GF-06:
     *
     * | status            | list primary actions                          |
     * | pending           | Accept · Dismiss                              |
     * | needs_correction  | Accept · Dismiss (awaiting applicant fix)     |
     * | dismissed         | Revert to pending                             |
     * | archived          | Revert to pending                             |
     * | accepted          | (no decide actions on list; slip later)       |
     *
     * Not on list: Archive (retention, never peer of Accept/Dismiss),
     * Request correction / in-place Correct (detail journey — D-074).
     * "Flag" was a mistaken label for CorrectAdmissionAttempt.
     */
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import E2PageState from './E2PageState.svelte';
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
    import HeaderActions from '@/Components/kit/HeaderActions.svelte';
    import SortDropdown from '@/Components/kit/SortDropdown.svelte';
    import BulkActionBar from '@/Components/kit/BulkActionBar.svelte';
    import ActiveFilterChips from '@/Components/kit/ActiveFilterChips.svelte';
    import SwitchableListView from '@/Components/kit/SwitchableListView.svelte';
    import SimplePagination from '@/Components/kit/SimplePagination.svelte';
    import { Badge } from '@/Components/ui/badge';
    import { Checkbox } from '@/Components/ui/checkbox';
    import { Input } from '@/Components/ui/input';
    import { Label } from '@/Components/ui/label';
    import * as Dialog from '@/Components/ui/dialog';
    import { Button } from '@/Components/ui/button';
    import * as Select from '@/Components/ui/select';
    import * as Table from '@/Components/ui/table';
    import { Textarea } from '@/Components/ui/textarea';
    import { Link, router, useForm } from '@inertiajs/svelte';
    import { formatDate, formatDateTime } from '@/lib/date-utils.js';
    import {
        CheckCircle2,
        CheckCheck,
        FileWarning,
        XCircle,
        RotateCcw,
        GraduationCap,
        PanelRightOpen,
        Upload,
        UserPlus,
        Search,
        ArrowUp,
        ArrowDown,
        ArrowUpDown,
        Eye,
    } from 'lucide-svelte';
    import ContextualImportModal from '@/Components/Ingestion/ContextualImportModal.svelte';
    import { sound } from '@/lib/sound.js';
    import PrintDocumentsSlot from '@/Components/DocumentTemplate/PrintDocumentsSlot.svelte';
    const directAssessmentForm = useForm({
        academic_cycle_id: '',
        label: '',
        date: '',
        start_time: '',
        applicant_ids: [],
    });

    let {
        page_state = 'ready',
        attempts,
        filters = {},
        statuses = [],
        cycles = [],
        sort_options = [],
        status_counts = {},
        capabilities = {
            accept: false,
            dismiss: false,
            correct: false,
            verify_documents: false,
        },
        can_import_applicants = false,
        can_create_applicant = false,
    } = $props();
    let viewMode = $state('responsive');
    let filterSearch = $state(filters.search ?? '');
    let filterStatus = $state(filters.status ?? '');
    let filterCycle = $state(filters.cycle ?? '');
    let filterSort = $state(filters.sort ?? 'created_at');
    let filterDir = $state(filters.dir ?? 'desc');

    const defaultSortOptions = [
        { value: 'created_at', label: 'Date Applied' },
        { value: 'name', label: 'Applicant Name' },
        { value: 'reference_code', label: 'Reference No.' },
        { value: 'status', label: 'Status' },
        { value: 'preferred_program_code', label: 'Program' },
    ];
    const sortOptions = $derived(
        sort_options && sort_options.length > 0
            ? sort_options
            : defaultSortOptions,
    );
    let debounceTimer;
    let searchReady = false;
    let importModalOpen = $state(false);
    let selectedSnapshots = $state([]);
    let directAssessmentDialogOpen = $state(false);
    let bulkAcceptDialogOpen = $state(false);
    let bulkAccepting = $state(false);
    let bulkAcceptAndVerifyDialogOpen = $state(false);
    let bulkAcceptingAndVerifying = $state(false);
    let bulkCorrectionDialogOpen = $state(false);
    let bulkCorrectionReason = $state('');
    let bulkCorrecting = $state(false);
    let searchFocused = $state(false);
    let mobileFiltersOpen = $state(false);
    let previewAttempt = $state(null);
    let previewDialogOpen = $state(false);

    function showPreview(attempt) {
        previewAttempt = attempt;
        previewDialogOpen = true;
    }

    const statusShortcuts = [
        { value: 'pending', label: 'Pending' },
        { value: 'needs_correction', label: 'Needs Correction' },
        { value: 'accepted', label: 'Accepted' },
        { value: 'dismissed', label: 'Dismissed' },
    ];

    const applicantSuggestions = $derived.by(() => {
        const query = filterSearch.trim().toLocaleLowerCase();
        return rows
            .filter((attempt) => {
                if (!query) return true;
                return [
                    attempt.applicant_name,
                    attempt.reference_code,
                    attempt.contact_email,
                ].some((value) =>
                    String(value ?? '')
                        .toLocaleLowerCase()
                        .includes(query),
                );
            })
            .slice(0, 5);
    });

    function chooseStatusShortcut(status) {
        filterStatus = status;
        searchFocused = false;
        applyFilters({ status });
    }

    function sortByColumn(sort) {
        const dir = filterSort === sort && filterDir === 'asc' ? 'desc' : 'asc';
        filterSort = sort;
        filterDir = dir;
        applyFilters({ sort, dir });
    }

    function columnSortDirection(sort) {
        if (filterSort !== sort) return 'none';
        return filterDir === 'asc' ? 'ascending' : 'descending';
    }

    function applyFilters(overrides = {}) {
        const search =
            overrides.search !== undefined ? overrides.search : filterSearch;
        const status =
            overrides.status !== undefined ? overrides.status : filterStatus;
        const cycle =
            overrides.cycle !== undefined ? overrides.cycle : filterCycle;
        const sort = overrides.sort !== undefined ? overrides.sort : filterSort;
        const dir = overrides.dir !== undefined ? overrides.dir : filterDir;

        router.get(
            '/admissions',
            {
                search: search || undefined,
                status: status || undefined,
                cycle: cycle || undefined,
                sort:
                    sort !== 'created_at' || dir !== 'desc' ? sort : undefined,
                dir: sort !== 'created_at' || dir !== 'desc' ? dir : undefined,
                page: 1,
            },
            { preserveState: true, replace: true },
        );
    }

    function handleSortChange({ sort: nextSort, dir: nextDir }) {
        filterSort = nextSort;
        filterDir = nextDir;
        applyFilters({ sort: nextSort, dir: nextDir });
    }

    $effect(() => {
        const value = filterSearch;
        if (!searchReady) {
            searchReady = true;
            return;
        }
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => applyFilters({ search: value }), 300);
    });

    function clearChip(id) {
        if (id === 'search') {
            filterSearch = '';
            applyFilters({ search: '' });
        } else if (id === 'status') {
            filterStatus = '';
            applyFilters({ status: '' });
        } else if (id === 'cycle') {
            filterCycle = '';
            applyFilters({ cycle: '' });
        } else if (id === 'sort') {
            filterSort = 'created_at';
            filterDir = 'desc';
            applyFilters({ sort: 'created_at', dir: 'desc' });
        }
    }

    function clearAllFilters() {
        filterSearch = '';
        filterStatus = '';
        filterCycle = '';
        filterSort = 'created_at';
        filterDir = 'desc';
        applyFilters({
            search: '',
            status: '',
            cycle: '',
            sort: 'created_at',
            dir: 'desc',
        });
    }

    const chips = $derived(
        [
            filterSearch
                ? { id: 'search', label: `Search: ${filterSearch}` }
                : null,
            filterStatus
                ? {
                      id: 'status',
                      label: `Status: ${statuses.find((s) => s.value === filterStatus)?.label ?? filterStatus}`,
                  }
                : null,
            filterCycle
                ? {
                      id: 'cycle',
                      label: `Academic Year & Semester: ${cycles.find((c) => c.value === filterCycle)?.label ?? filterCycle}`,
                  }
                : null,
            filterSort !== 'created_at' || filterDir !== 'desc'
                ? {
                      id: 'sort',
                      label: `Sort: ${sortOptions.find((o) => o.value === filterSort)?.label ?? filterSort} (${filterDir === 'asc' ? 'Asc' : 'Desc'})`,
                  }
                : null,
        ].filter(Boolean),
    );
    const activeFilterCount = $derived(chips.length);
    function statusVariant(status) {
        switch (status) {
            case 'accepted':
                return 'default';
            case 'pending':
                return 'secondary';
            case 'needs_correction':
                return 'outline';
            case 'dismissed':
                return 'destructive';
            default:
                return 'secondary';
        }
    }

    function statusLabel(status) {
        return statuses.find((s) => s.value === status)?.label ?? status;
    }

    function initials(name) {
        const parts = String(name ?? '')
            .trim()
            .split(/\s+/)
            .filter(Boolean);
        if (parts.length === 0) return '?';
        if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
        return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
    }

    /** Decide path — only while still in the intake queue (legacy Index). */
    function canDecide(attempt) {
        return (
            attempt.status === 'pending' ||
            attempt.status === 'needs_correction'
        );
    }

    /** Non-destructive reopen — GF command allows dismissed + archived only. */
    function canRevert(attempt) {
        return attempt.status === 'dismissed' || attempt.status === 'archived';
    }
    const visibleSelectableRows = $derived(
        rows.filter((attempt) =>
            ['accepted', 'pending', 'needs_correction'].includes(
                attempt.status,
            ),
        ),
    );
    const allVisibleSelectableRowsSelected = $derived(
        visibleSelectableRows.length > 0 &&
            visibleSelectableRows.every((attempt) =>
                selectedSnapshots.some(
                    (snapshot) => snapshot.id === attempt.id,
                ),
            ),
    );
    const someVisibleSelectableRowsSelected = $derived(
        visibleSelectableRows.some((attempt) =>
            selectedSnapshots.some((snapshot) => snapshot.id === attempt.id),
        ),
    );
    const pendingSelected = $derived(
        selectedSnapshots.filter(
            (snapshot) =>
                snapshot.status === 'pending' ||
                snapshot.status === 'needs_correction',
        ),
    );
    const acceptedSelected = $derived(
        selectedSnapshots.filter((snapshot) => snapshot.status === 'accepted'),
    );
    const acceptedSelectedSnapshots = $derived(
        selectedSnapshots.filter((snapshot) => snapshot.status === 'accepted'),
    );
    const selectedIds = $derived(selectedSnapshots.map((s) => s.id));

    function toggleAttempt(attempt, checked) {
        if (checked) {
            if (
                selectedSnapshots.some((snapshot) => snapshot.id === attempt.id)
            )
                return;

            selectedSnapshots = [
                ...selectedSnapshots,
                {
                    id: attempt.id,
                    version: attempt.version,
                    reference_code: attempt.reference_code,
                    name: attempt.applicant_name,
                    status: attempt.status,
                    cycle_id: attempt.academic_cycle_id ?? attempt.cycle_id,
                },
            ];
            return;
        }

        selectedSnapshots = selectedSnapshots.filter(
            (snapshot) => snapshot.id !== attempt.id,
        );
    }

    function toggleAllEligible(checked) {
        const visibleSnapshots = visibleSelectableRows.map((attempt) => ({
            id: attempt.id,
            version: attempt.version,
            reference_code: attempt.reference_code,
            name: attempt.applicant_name,
            status: attempt.status,
            cycle_id: attempt.academic_cycle_id ?? attempt.cycle_id,
        }));

        selectedSnapshots = checked
            ? [
                  ...selectedSnapshots,
                  ...visibleSnapshots.filter(
                      (snapshot) =>
                          !selectedSnapshots.some(
                              (selected) => selected.id === snapshot.id,
                          ),
                  ),
              ]
            : selectedSnapshots.filter(
                  (snapshot) =>
                      !visibleSelectableRows.some(
                          (attempt) => attempt.id === snapshot.id,
                      ),
              );
    }

    function openDirectAssessmentDialog() {
        const selectedAttempts = acceptedSelectedSnapshots;
        const selectedCycle =
            filterCycle || selectedAttempts[0]?.cycle_id || '';
        directAssessmentForm.academic_cycle_id = String(
            selectedCycle || cycles[0]?.value || '',
        );
        directAssessmentForm.applicant_ids = selectedAttempts.map(
            (snapshot) => snapshot.id,
        );
        directAssessmentDialogOpen = true;
    }

    function submitDirectAssessment() {
        directAssessmentForm.applicant_ids = acceptedSelectedSnapshots.map(
            (snapshot) => snapshot.id,
        );
        directAssessmentForm.post('/admissions/bulk-direct-assessment', {
            onSuccess: () => {
                directAssessmentDialogOpen = false;
                selectedSnapshots = [];
            },
        });
    }

    function submitBulkAccept() {
        if (bulkAccepting || pendingSelected.length === 0) return;
        bulkAccepting = true;
        router.post(
            '/admissions/bulk-accept',
            {
                attempts: pendingSelected.map((snapshot) => ({
                    id: snapshot.id,
                    version: snapshot.version,
                })),
            },
            {
                onSuccess: () => {
                    bulkAcceptDialogOpen = false;
                    selectedSnapshots = [];
                },
                onFinish: () => {
                    bulkAccepting = false;
                },
            },
        );
    }

    function submitBulkAcceptAndVerify() {
        if (bulkAcceptingAndVerifying || pendingSelected.length === 0) return;
        bulkAcceptingAndVerifying = true;
        router.post(
            '/admissions/bulk-accept',
            {
                attempts: pendingSelected.map((snapshot) => ({
                    id: snapshot.id,
                    version: snapshot.version,
                })),
                verify_documents: true,
            },
            {
                onSuccess: () => {
                    bulkAcceptAndVerifyDialogOpen = false;
                    selectedSnapshots = [];
                },
                onFinish: () => {
                    bulkAcceptingAndVerifying = false;
                },
            },
        );
    }

    function submitBulkCorrection() {
        const reason = bulkCorrectionReason.trim();
        if (bulkCorrecting || pendingSelected.length === 0 || !reason) return;
        bulkCorrecting = true;
        router.post(
            '/admissions/bulk-request-correction',
            {
                attempts: pendingSelected.map((snapshot) => ({
                    id: snapshot.id,
                    version: snapshot.version,
                })),
                reason,
            },
            {
                onSuccess: () => {
                    bulkCorrectionDialogOpen = false;
                    bulkCorrectionReason = '';
                    selectedSnapshots = [];
                },
                onFinish: () => {
                    bulkCorrecting = false;
                },
            },
        );
    }

    function accept(attempt) {
        router.post(`/admissions/${attempt.id}/accept`, {
            expected_version: attempt.version,
        });
    }

    function acceptAndVerify(attempt) {
        router.post(`/admissions/${attempt.id}/accept`, {
            expected_version: attempt.version,
            verify_documents: true,
        });
    }

    function hasUnverifiedDocuments(attempt) {
        if (!attempt.document_summary) return false;
        const s = attempt.document_summary;
        if (s.status === 'uninitialized') return true;
        return (
            (s.missing_count ?? 0) +
                (s.deficient_count ?? 0) +
                (s.not_checked_count ?? 0) >
            0
        );
    }

    function dismiss(attempt) {
        const reason = window.prompt('Dismissal reason (required):');
        if (!reason || !reason.trim()) return;
        router.post(`/admissions/${attempt.id}/dismiss`, {
            expected_version: attempt.version,
            reason: reason.trim(),
        });
    }

    function reopen(attempt) {
        const reason = window.prompt(
            'Reason for reverting to pending (required):',
        );
        if (!reason || !reason.trim()) return;
        router.post(`/admissions/${attempt.id}/reopen`, {
            expected_version: attempt.version,
            reason: reason.trim(),
        });
    }

    const breadcrumbs = [{ label: 'Admissions' }];
    const rows = $derived(attempts?.data ?? []);
</script>

{#snippet rowControls(attempt, dense = true)}
    <div
        class="flex flex-nowrap items-center justify-end gap-1.5 whitespace-nowrap"
    >
        <Link href={`/admissions/${attempt.id}`} class="inline-flex shrink-0">
            <Button
                size="sm"
                variant="outline"
                class="min-h-[44px] shrink-0 px-2.5 text-xs font-semibold whitespace-nowrap md:min-h-[36px]"
            >
                <PanelRightOpen class="mr-1.5 h-3.5 w-3.5 shrink-0" />
                Review
            </Button>
        </Link>
        <Button
            type="button"
            size="icon"
            variant="ghost"
            class="min-h-[44px] min-w-[44px] shrink-0 md:size-9 md:min-h-[36px] md:min-w-[36px]"
            aria-label={`Quick preview for ${attempt.applicant_name}`}
            title="Quick preview"
            onclick={() => showPreview(attempt)}
        >
            <Eye class="size-4 shrink-0" />
        </Button>
        {#if capabilities.accept && canDecide(attempt)}
            <Button
                size="sm"
                variant="ghost"
                class="min-h-[44px] shrink-0 px-2.5 text-xs font-semibold whitespace-nowrap text-emerald-700 hover:bg-emerald-50 hover:text-emerald-800 md:min-h-[36px] dark:text-emerald-400 dark:hover:bg-emerald-950/40"
                onclick={() => accept(attempt)}
            >
                <CheckCircle2 class="mr-1.5 h-3.5 w-3.5 shrink-0" />
                Accept
            </Button>
            {#if capabilities.verify_documents && hasUnverifiedDocuments(attempt)}
                <Button
                    size="sm"
                    variant="ghost"
                    class="min-h-[44px] shrink-0 px-2.5 text-xs font-semibold whitespace-nowrap text-emerald-700 hover:bg-emerald-50 hover:text-emerald-800 md:min-h-[36px] dark:text-emerald-400 dark:hover:bg-emerald-950/40"
                    title="Accept application and verify all document requirements"
                    onclick={() => acceptAndVerify(attempt)}
                >
                    <CheckCheck class="mr-1.5 h-3.5 w-3.5 shrink-0" />
                    Accept & verify all documents
                </Button>
            {/if}
        {/if}
        {#if capabilities.dismiss && canDecide(attempt)}
            <Button
                size="sm"
                variant="ghost"
                class="text-destructive hover:bg-destructive/5 min-h-[44px] shrink-0 px-2.5 text-xs font-semibold whitespace-nowrap md:min-h-[36px]"
                onclick={() => dismiss(attempt)}
            >
                <XCircle class="mr-1.5 h-3.5 w-3.5 shrink-0" />
                Dismiss
            </Button>
        {/if}
        {#if capabilities.accept && canRevert(attempt)}
            <Button
                size="sm"
                variant="outline"
                class="min-h-[44px] shrink-0 px-2.5 text-xs font-semibold whitespace-nowrap md:min-h-[36px]"
                onclick={() => reopen(attempt)}
            >
                <RotateCcw class="mr-1.5 h-3.5 w-3.5 shrink-0" />
                Reopen
            </Button>
        {/if}
        {#if attempt.status === 'accepted'}
            <span
                class="text-muted-foreground shrink-0 px-1 text-xs font-medium whitespace-nowrap"
                >In pipeline</span
            >
        {/if}
        <div class="inline-flex shrink-0">
            <PrintDocumentsSlot
                slotKey="admissions_registry_bulk"
                attemptIds={[attempt.id]}
                autoStart={true}
                compact={true}
                buttonVariant="ghost"
                buttonSize="sm"
                class="min-h-[44px] shrink-0 whitespace-nowrap md:min-h-[36px]"
            />
        </div>
    </div>
{/snippet}

<svelte:head><title>Admissions</title></svelte:head>

<StaffLayout {breadcrumbs}>
    <div class="min-w-0 space-y-4">
        <PageHeaderBar
            title="Applications"
            description="Review admissions applications, process approvals, and manage applicant records, corrections, and credentials."
        >
            {#snippet actions()}
                <HeaderActions>
                    {#snippet primaryAction()}
                        {#if can_create_applicant || capabilities.correct}
                            <Button
                                href="/admissions/create"
                                class="min-h-10 gap-2 font-semibold"
                                onclick={() => sound.tick()}
                            >
                                <UserPlus class="size-4" />
                                <span>New Application</span>
                            </Button>
                        {/if}
                    {/snippet}
                    {#snippet secondaryActions()}
                        {#if can_import_applicants}
                            <Button
                                variant="outline"
                                class="min-h-10 gap-2"
                                onclick={() => {
                                    importModalOpen = true;
                                    sound.tick();
                                }}
                            >
                                <Upload class="size-4" />
                                <span>Import Applicants</span>
                            </Button>
                        {/if}
                    {/snippet}
                </HeaderActions>
            {/snippet}
        </PageHeaderBar>

        <E2PageState state={page_state} />

        {#if page_state !== 'denied'}
            <div
                class="bg-background/80 sticky top-0 z-20 space-y-3 py-2 backdrop-blur-md md:flex md:items-center md:gap-2"
            >
                <div class="relative min-w-0 flex-1 md:min-w-[280px]">
                    <Search
                        class="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2"
                    />
                    <Input
                        bind:value={filterSearch}
                        onfocus={() => (searchFocused = true)}
                        onblur={() => (searchFocused = false)}
                        placeholder="Search applicants, email, or reference…"
                        aria-label="Search applicants"
                        class="h-11 min-h-[44px] w-full rounded-xl pr-10 pl-10"
                    />
                    {#if filterSearch}
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            class="absolute top-1/2 right-1.5 min-h-[44px] min-w-[44px] -translate-y-1/2"
                            aria-label="Clear search"
                            onclick={() => {
                                filterSearch = '';
                                applyFilters({ search: '' });
                            }}
                        >
                            <span aria-hidden="true">×</span>
                        </Button>
                    {/if}

                    {#if searchFocused}
                        <div
                            class="border-border bg-card absolute top-full left-0 z-50 mt-1.5 w-full max-w-2xl rounded-2xl border p-4 shadow-2xl backdrop-blur-md"
                            role="region"
                            aria-label="Search suggestions"
                        >
                            <div class="space-y-3">
                                <div>
                                    <p
                                        class="text-muted-foreground mb-2 text-xs font-semibold tracking-wide uppercase"
                                    >
                                        Status shortcuts
                                    </p>
                                    <div class="flex flex-wrap gap-2">
                                        {#each statusShortcuts as shortcut (shortcut.value)}
                                            <Button
                                                type="button"
                                                variant={filterStatus ===
                                                shortcut.value
                                                    ? 'default'
                                                    : 'outline'}
                                                class="min-h-[44px] px-3 md:min-h-[36px]"
                                                onpointerdown={(event) =>
                                                    event.preventDefault()}
                                                onclick={() =>
                                                    chooseStatusShortcut(
                                                        shortcut.value,
                                                    )}
                                            >
                                                {shortcut.label}
                                            </Button>
                                        {/each}
                                    </div>
                                </div>
                                <div>
                                    <p
                                        class="text-muted-foreground mb-2 text-xs font-semibold tracking-wide uppercase"
                                    >
                                        Applicant matches
                                    </p>
                                    {#if applicantSuggestions.length > 0}
                                        <ul
                                            class="max-h-64 space-y-1 overflow-y-auto"
                                        >
                                            {#each applicantSuggestions as applicant (applicant.id)}
                                                <li>
                                                    <button
                                                        type="button"
                                                        class="hover:bg-muted flex min-h-[44px] w-full items-center justify-between gap-3 rounded-lg px-3 py-2 text-left"
                                                        onpointerdown={(
                                                            event,
                                                        ) =>
                                                            event.preventDefault()}
                                                        onclick={() => {
                                                            filterSearch =
                                                                applicant.applicant_name;
                                                            searchFocused = false;
                                                            applyFilters({
                                                                search: applicant.applicant_name,
                                                            });
                                                        }}
                                                    >
                                                        <span class="min-w-0">
                                                            <span
                                                                class="block truncate text-sm font-medium"
                                                                >{applicant.applicant_name}</span
                                                            >
                                                            <span
                                                                class="text-muted-foreground block truncate font-mono text-xs"
                                                                >{applicant.reference_code}
                                                                · {applicant.contact_email}</span
                                                            >
                                                        </span>
                                                        <Eye
                                                            class="size-4 shrink-0"
                                                            aria-hidden="true"
                                                        />
                                                    </button>
                                                </li>
                                            {/each}
                                        </ul>
                                    {:else}
                                        <p
                                            class="text-muted-foreground py-3 text-sm"
                                        >
                                            No applicants on this page match the
                                            search.
                                        </p>
                                    {/if}
                                </div>
                            </div>
                        </div>
                    {/if}
                </div>

                <div class="flex items-center justify-between gap-2 md:hidden">
                    <Button
                        type="button"
                        variant="outline"
                        class="min-h-[44px] w-full justify-center"
                        aria-expanded={mobileFiltersOpen}
                        aria-controls="admission-mobile-filters"
                        onclick={() => (mobileFiltersOpen = !mobileFiltersOpen)}
                    >
                        Filters ({activeFilterCount}) ▾
                    </Button>
                </div>
                <div
                    id="admission-mobile-filters"
                    class={`${mobileFiltersOpen ? 'grid' : 'hidden'} border-border/60 bg-muted/20 grid-cols-1 gap-2 rounded-lg border p-3 md:flex md:flex-wrap md:items-center md:justify-end md:rounded-none md:border-0 md:bg-transparent md:p-0`}
                >
                    <Select.Root
                        type="single"
                        value={filterStatus}
                        onValueChange={(value) => {
                            filterStatus = value ?? '';
                            applyFilters({ status: filterStatus });
                        }}
                    >
                        <Select.Trigger
                            aria-label="Filter by status"
                            class="min-h-[44px] w-full min-w-0 sm:w-[190px] md:h-9 md:min-h-9"
                        >
                            {filterStatus
                                ? statuses.find(
                                      (status) => status.value === filterStatus,
                                  )?.label
                                : 'All statuses'}
                        </Select.Trigger>
                        <Select.Content>
                            <Select.Item value="" label="All statuses"
                                >All statuses</Select.Item
                            >
                            {#each statuses as status (status.value)}
                                <Select.Item
                                    value={status.value}
                                    label={status.label}
                                    >{status.label}</Select.Item
                                >
                            {/each}
                        </Select.Content>
                    </Select.Root>
                    <Select.Root
                        type="single"
                        value={filterCycle}
                        onValueChange={(value) => {
                            filterCycle = value ?? '';
                            applyFilters({ cycle: filterCycle });
                        }}
                    >
                        <Select.Trigger
                            aria-label="Filter by academic year and semester"
                            class="min-h-[44px] w-full min-w-0 sm:w-[250px] md:h-9 md:min-h-9"
                        >
                            {filterCycle
                                ? cycles.find(
                                      (cycle) => cycle.value === filterCycle,
                                  )?.label
                                : 'All Academic Years & Semesters'}
                        </Select.Trigger>
                        <Select.Content>
                            <Select.Item
                                value=""
                                label="All Academic Years & Semesters"
                                >All Academic Years & Semesters</Select.Item
                            >
                            {#each cycles as cycle (cycle.value)}
                                <Select.Item
                                    value={cycle.value}
                                    label={cycle.label}
                                    >{cycle.label}</Select.Item
                                >
                            {/each}
                        </Select.Content>
                    </Select.Root>
                    <SortDropdown
                        options={sortOptions}
                        sort={filterSort}
                        dir={filterDir}
                        onchange={handleSortChange}
                    />
                    <Button
                        type="button"
                        variant="outline"
                        class="min-h-[44px] px-4 md:min-h-9 md:text-xs"
                        onclick={clearAllFilters}
                    >
                        Reset
                    </Button>
                </div>
            </div>

            <ActiveFilterChips
                {chips}
                onRemove={clearChip}
                onClearAll={clearAllFilters}
            />

            {#if rows.length === 0}
                <p
                    class="border-border text-muted-foreground rounded-lg border border-dashed p-8 text-center text-sm"
                >
                    No applications match these filters.
                </p>
            {:else}
                <SwitchableListView bind:viewMode cardsInset={false}>
                    {#snippet table()}
                        <Table.Root class="w-full min-w-[720px] text-sm">
                            <Table.Header
                                class="bg-muted/40 border-border border-b"
                            >
                                <Table.Row
                                    class="border-border/40 hover:bg-muted/30 border-b transition-colors"
                                >
                                    <Table.Head class="w-12 px-2 py-2">
                                        <div
                                            class="flex min-h-[44px] min-w-[44px] items-center justify-center"
                                        >
                                            <Checkbox
                                                checked={allVisibleSelectableRowsSelected}
                                                indeterminate={someVisibleSelectableRowsSelected &&
                                                    !allVisibleSelectableRowsSelected}
                                                disabled={visibleSelectableRows.length ===
                                                    0}
                                                onCheckedChange={(checked) =>
                                                    toggleAllEligible(
                                                        Boolean(checked),
                                                    )}
                                                aria-label="Select all eligible applicants on this page"
                                                class="after:-inset-[14px]"
                                            />
                                        </div>
                                    </Table.Head>
                                    <Table.Head
                                        aria-sort={columnSortDirection(
                                            'reference_code',
                                        )}
                                        class="px-3.5 py-2 font-sans"
                                    >
                                        <button
                                            type="button"
                                            class="flex min-h-[44px] items-center gap-2 text-left"
                                            onclick={() =>
                                                sortByColumn('reference_code')}
                                        >
                                            NOA / Reference
                                            {#if filterSort === 'reference_code'}
                                                {#if filterDir === 'asc'}<ArrowUp
                                                        class="size-4"
                                                        aria-hidden="true"
                                                    />{:else}<ArrowDown
                                                        class="size-4"
                                                        aria-hidden="true"
                                                    />{/if}
                                            {:else}
                                                <ArrowUpDown
                                                    class="size-4"
                                                    aria-hidden="true"
                                                />
                                            {/if}
                                        </button>
                                    </Table.Head>
                                    <Table.Head
                                        aria-sort={columnSortDirection('name')}
                                        class="px-3.5 py-2 font-sans"
                                    >
                                        <button
                                            type="button"
                                            class="flex min-h-[44px] items-center gap-2 text-left"
                                            onclick={() => sortByColumn('name')}
                                        >
                                            Applicant
                                            {#if filterSort === 'name'}
                                                {#if filterDir === 'asc'}<ArrowUp
                                                        class="size-4"
                                                        aria-hidden="true"
                                                    />{:else}<ArrowDown
                                                        class="size-4"
                                                        aria-hidden="true"
                                                    />{/if}
                                            {:else}
                                                <ArrowUpDown
                                                    class="size-4"
                                                    aria-hidden="true"
                                                />
                                            {/if}
                                        </button>
                                    </Table.Head>
                                    <Table.Head
                                        aria-sort={columnSortDirection(
                                            'preferred_program_code',
                                        )}
                                        class="px-3.5 py-2 font-sans"
                                    >
                                        <button
                                            type="button"
                                            class="flex min-h-[44px] items-center gap-2 text-left"
                                            onclick={() =>
                                                sortByColumn(
                                                    'preferred_program_code',
                                                )}
                                        >
                                            Program
                                            {#if filterSort === 'preferred_program_code'}
                                                {#if filterDir === 'asc'}<ArrowUp
                                                        class="size-4"
                                                        aria-hidden="true"
                                                    />{:else}<ArrowDown
                                                        class="size-4"
                                                        aria-hidden="true"
                                                    />{/if}
                                            {:else}
                                                <ArrowUpDown
                                                    class="size-4"
                                                    aria-hidden="true"
                                                />
                                            {/if}
                                        </button>
                                    </Table.Head>
                                    <Table.Head
                                        aria-sort={columnSortDirection(
                                            'status',
                                        )}
                                        class="px-3.5 py-2 font-sans"
                                    >
                                        <button
                                            type="button"
                                            class="flex min-h-[44px] items-center gap-2 text-left"
                                            onclick={() =>
                                                sortByColumn('status')}
                                        >
                                            Status
                                            {#if filterSort === 'status'}
                                                {#if filterDir === 'asc'}<ArrowUp
                                                        class="size-4"
                                                        aria-hidden="true"
                                                    />{:else}<ArrowDown
                                                        class="size-4"
                                                        aria-hidden="true"
                                                    />{/if}
                                            {:else}
                                                <ArrowUpDown
                                                    class="size-4"
                                                    aria-hidden="true"
                                                />
                                            {/if}
                                        </button>
                                    </Table.Head>
                                    <Table.Head
                                        aria-sort={columnSortDirection(
                                            'created_at',
                                        )}
                                        class="px-3.5 py-2 font-sans"
                                    >
                                        <button
                                            type="button"
                                            class="flex min-h-[44px] items-center gap-2 text-left"
                                            onclick={() =>
                                                sortByColumn('created_at')}
                                        >
                                            Date Submitted
                                            {#if filterSort === 'created_at'}
                                                {#if filterDir === 'asc'}<ArrowUp
                                                        class="size-4"
                                                        aria-hidden="true"
                                                    />{:else}<ArrowDown
                                                        class="size-4"
                                                        aria-hidden="true"
                                                    />{/if}
                                            {:else}
                                                <ArrowUpDown
                                                    class="size-4"
                                                    aria-hidden="true"
                                                />
                                            {/if}
                                        </button>
                                    </Table.Head>
                                    <Table.Head
                                        class="w-px px-3.5 py-2 text-right font-sans whitespace-nowrap"
                                        >Actions</Table.Head
                                    >
                                </Table.Row>
                            </Table.Header>
                            <Table.Body>
                                {#each rows as attempt (attempt.id)}
                                    <Table.Row
                                        class="border-border/40 hover:bg-muted/30 border-b align-top transition-colors"
                                    >
                                        <Table.Cell class="px-3 py-2.5">
                                            {#if ['accepted', 'pending', 'needs_correction'].includes(attempt.status)}
                                                <div
                                                    class="flex min-h-[44px] min-w-[44px] items-center justify-center"
                                                >
                                                    <Checkbox
                                                        checked={selectedSnapshots.some(
                                                            (snapshot) =>
                                                                snapshot.id ===
                                                                attempt.id,
                                                        )}
                                                        onCheckedChange={(
                                                            checked,
                                                        ) =>
                                                            toggleAttempt(
                                                                attempt,
                                                                Boolean(
                                                                    checked,
                                                                ),
                                                            )}
                                                        aria-label={`Select ${attempt.applicant_name} for bulk actions`}
                                                        class="after:-inset-[14px]"
                                                    />
                                                </div>
                                            {/if}
                                        </Table.Cell>
                                        <Table.Cell
                                            class="px-3 py-2.5 font-mono text-xs font-medium"
                                        >
                                            <Link
                                                href={`/admissions/${attempt.id}`}
                                                class="hover:underline"
                                            >
                                                {attempt.reference_code}
                                            </Link>
                                        </Table.Cell>
                                        <Table.Cell class="px-3 py-2.5">
                                            <div class="min-w-0">
                                                <Link
                                                    href={`/admissions/${attempt.id}`}
                                                    class="truncate font-medium hover:underline"
                                                >
                                                    {attempt.applicant_name}
                                                </Link>
                                                <p
                                                    class="text-muted-foreground truncate text-xs"
                                                >
                                                    {attempt.contact_email}
                                                </p>
                                                {#if attempt.status === 'needs_correction'}
                                                    <p
                                                        class="mt-0.5 text-xs text-amber-700 dark:text-amber-400"
                                                    >
                                                        Awaiting applicant
                                                        correction
                                                    </p>
                                                {/if}
                                            </div>
                                        </Table.Cell>
                                        <Table.Cell class="px-3 py-2.5">
                                            <span class="font-medium"
                                                >{attempt.preferred_program_code ??
                                                    '—'}</span
                                            >
                                            {#if (attempt.program_preferences?.length ?? 0) > 1}
                                                <p
                                                    class="text-muted-foreground text-xs"
                                                >
                                                    +{attempt
                                                        .program_preferences
                                                        .length - 1} more
                                                </p>
                                            {/if}
                                        </Table.Cell>
                                        <Table.Cell class="px-3 py-2.5">
                                            <Badge
                                                variant={statusVariant(
                                                    attempt.status,
                                                )}
                                                class="shrink-0 font-mono text-[10px] font-bold capitalize"
                                            >
                                                {statusLabel(attempt.status)}
                                            </Badge>
                                            {#if attempt.document_summary}
                                                <Link
                                                    href={`/admissions/${attempt.id}`}
                                                    class="text-primary mt-1 block text-xs hover:underline"
                                                    aria-label={`Review documents for ${attempt.applicant_name}`}
                                                >
                                                    {attempt.document_summary
                                                        .mandatory_pending_count}
                                                    mandatory documents pending
                                                </Link>
                                            {/if}
                                        </Table.Cell>
                                        <Table.Cell class="px-3 py-2.5 text-xs">
                                            {formatDate(attempt.submitted_at)}
                                        </Table.Cell>
                                        <Table.Cell
                                            class="w-px px-3 py-2.5 text-right align-middle whitespace-nowrap"
                                        >
                                            {@render rowControls(attempt, true)}
                                        </Table.Cell>
                                    </Table.Row>
                                {/each}
                            </Table.Body>
                        </Table.Root>
                    {/snippet}

                    {#snippet cards()}
                        {#if visibleSelectableRows.length > 0}
                            <div class="mb-3 flex items-center gap-2">
                                <div
                                    class="flex min-h-[44px] min-w-[44px] items-center justify-center"
                                >
                                    <Checkbox
                                        checked={allVisibleSelectableRowsSelected}
                                        indeterminate={someVisibleSelectableRowsSelected &&
                                            !allVisibleSelectableRowsSelected}
                                        onCheckedChange={(checked) =>
                                            toggleAllEligible(Boolean(checked))}
                                        aria-label="Select all eligible applicants on this page"
                                        class="after:-inset-[14px]"
                                    />
                                </div>
                                <span class="text-muted-foreground text-sm"
                                    >Select all eligible on this page</span
                                >
                            </div>
                        {/if}
                        <ul
                            class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3"
                            role="list"
                        >
                            {#each rows as attempt (attempt.id)}
                                <li
                                    class="craft-card-depth group flex min-w-0 flex-col justify-between gap-3.5 p-5"
                                >
                                    <div class="min-w-0 space-y-3">
                                        <div
                                            class="border-border/50 flex min-w-0 items-start justify-between gap-2.5 border-b pb-2.5"
                                        >
                                            <div
                                                class="flex min-w-0 flex-1 items-center gap-2.5"
                                            >
                                                {#if ['accepted', 'pending', 'needs_correction'].includes(attempt.status)}
                                                    <div
                                                        class="flex min-h-[44px] min-w-[44px] shrink-0 items-center justify-center"
                                                    >
                                                        <Checkbox
                                                            checked={selectedSnapshots.some(
                                                                (snapshot) =>
                                                                    snapshot.id ===
                                                                    attempt.id,
                                                            )}
                                                            onCheckedChange={(
                                                                checked,
                                                            ) =>
                                                                toggleAttempt(
                                                                    attempt,
                                                                    Boolean(
                                                                        checked,
                                                                    ),
                                                                )}
                                                            aria-label={`Select ${attempt.applicant_name} for bulk actions`}
                                                            class="after:-inset-[14px]"
                                                        />
                                                    </div>
                                                {/if}
                                                <div
                                                    class="bg-primary/10 text-primary border-primary/20 flex size-9 shrink-0 items-center justify-center rounded-full border font-sans text-xs font-bold"
                                                >
                                                    {initials(
                                                        attempt.applicant_name,
                                                    )}
                                                </div>
                                                <div class="min-w-0 flex-1">
                                                    <Link
                                                        href={`/admissions/${attempt.id}`}
                                                        class="text-foreground group-hover:text-primary line-clamp-2 block font-sans text-base font-bold break-words transition-colors"
                                                        title={attempt.applicant_name}
                                                    >
                                                        {attempt.applicant_name}
                                                    </Link>
                                                    <p
                                                        class="text-muted-foreground font-mono text-xs break-all"
                                                    >
                                                        {attempt.reference_code}
                                                    </p>
                                                </div>
                                            </div>
                                            <Badge
                                                variant={statusVariant(
                                                    attempt.status,
                                                )}
                                                class="shrink-0 font-mono text-[10px] font-bold capitalize"
                                            >
                                                {statusLabel(attempt.status)}
                                            </Badge>
                                        </div>

                                        <!-- Recessed Credentials & Choice Well -->
                                        <div
                                            class="recessed-well min-w-0 space-y-1.5 rounded-lg p-3 font-sans text-xs"
                                        >
                                            <div
                                                class="flex min-w-0 items-center justify-between gap-2"
                                            >
                                                <span
                                                    class="text-muted-foreground shrink-0"
                                                    >First Preference:</span
                                                >
                                                <strong
                                                    class="text-primary truncate text-right font-bold"
                                                    >{attempt.preferred_program_code ??
                                                        'No preference'}</strong
                                                >
                                            </div>
                                            {#if (attempt.program_preferences?.length ?? 0) > 1}
                                                <div
                                                    class="text-muted-foreground flex min-w-0 items-start justify-between gap-2 text-[11px]"
                                                >
                                                    <span class="shrink-0"
                                                        >Alternatives:</span
                                                    >
                                                    <span
                                                        class="text-foreground text-right font-medium break-words"
                                                        >{attempt.program_preferences
                                                            .slice(1)
                                                            .join(' · ')}</span
                                                    >
                                                </div>
                                            {/if}
                                            <div
                                                class="text-muted-foreground border-border/50 flex min-w-0 items-start justify-between gap-2 border-t pt-1 text-[11px]"
                                            >
                                                <span class="shrink-0"
                                                    >Academic Year & Semester:</span
                                                >
                                                <span
                                                    class="text-foreground text-right font-medium break-words"
                                                    >{attempt.cycle_name ??
                                                        attempt.cycle_code ??
                                                        '—'}</span
                                                >
                                            </div>
                                            <div
                                                class="text-muted-foreground flex min-w-0 items-center justify-between gap-2 text-[10px]"
                                            >
                                                <span class="shrink-0"
                                                    >Submitted:</span
                                                >
                                                <span
                                                    class="text-right font-mono"
                                                    >{formatDateTime(
                                                        attempt.submitted_at,
                                                    )}</span
                                                >
                                            </div>
                                        </div>
                                        {#if attempt.document_summary}
                                            <Link
                                                href={`/admissions/${attempt.id}`}
                                                class="border-border/50 flex min-h-11 items-center justify-between gap-2 border-t pt-2 text-xs hover:underline"
                                            >
                                                <span
                                                    >{attempt.document_summary
                                                        .status ===
                                                    'uninitialized'
                                                        ? 'Checklist not initialized'
                                                        : attempt
                                                                .document_summary
                                                                .status ===
                                                            'stale'
                                                          ? 'Checklist needs review'
                                                          : attempt
                                                                  .document_summary
                                                                  .status ===
                                                              'unknown_track'
                                                            ? 'Track needs Registrar review'
                                                            : `${attempt.document_summary.deficient_count + attempt.document_summary.missing_count} documents to review`}</span
                                                >
                                                <Badge variant="secondary"
                                                    >{attempt.document_summary
                                                        .mandatory_pending_count}
                                                    mandatory pending</Badge
                                                >
                                            </Link>
                                        {/if}

                                        {#if attempt.status === 'needs_correction'}
                                            <div
                                                class="rounded border border-amber-500/20 bg-amber-500/10 p-2 font-sans text-[11px] text-amber-700 dark:text-amber-400"
                                            >
                                                Awaiting applicant correction
                                            </div>
                                        {/if}
                                    </div>

                                    <div
                                        class="border-border/40 flex flex-col gap-2.5 border-t pt-3"
                                    >
                                        <div
                                            class="flex items-center justify-between gap-2"
                                        >
                                            <div
                                                class="flex min-w-[130px] flex-1 items-center gap-1.5"
                                            >
                                                <Link
                                                    href={`/admissions/${attempt.id}`}
                                                    class="min-w-0 flex-1"
                                                >
                                                    <Button
                                                        variant="default"
                                                        size="sm"
                                                        class="min-h-10 w-full gap-1.5 px-3 font-sans text-xs font-semibold shadow-sm"
                                                    >
                                                        <PanelRightOpen
                                                            class="size-3.5 shrink-0"
                                                        />
                                                        <span class="truncate"
                                                            >Review</span
                                                        >
                                                    </Button>
                                                </Link>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="icon"
                                                    class="size-10 min-h-10 min-w-10 shrink-0"
                                                    aria-label={`Quick preview for ${attempt.applicant_name}`}
                                                    title="Quick preview"
                                                    onclick={() =>
                                                        showPreview(attempt)}
                                                >
                                                    <Eye
                                                        class="size-4 shrink-0"
                                                    />
                                                </Button>
                                            </div>

                                            <div
                                                class="flex shrink-0 items-center gap-1.5"
                                            >
                                                {#if attempt.status === 'accepted'}
                                                    <span
                                                        class="text-muted-foreground shrink-0 px-1 font-mono text-[11px]"
                                                        >In pipeline</span
                                                    >
                                                {/if}
                                                <div class="shrink-0">
                                                    <PrintDocumentsSlot
                                                        slotKey="admissions_registry_bulk"
                                                        attemptIds={[
                                                            attempt.id,
                                                        ]}
                                                        autoStart={true}
                                                        compact={true}
                                                        buttonVariant="outline"
                                                        buttonSize="sm"
                                                        class="min-h-10 shrink-0"
                                                    />
                                                </div>
                                            </div>
                                        </div>

                                        {#if (capabilities.accept && canDecide(attempt)) || (capabilities.dismiss && canDecide(attempt)) || (capabilities.accept && canRevert(attempt))}
                                            <div
                                                class="border-border/30 flex flex-wrap items-center gap-1.5 border-t pt-2.5"
                                            >
                                                {#if capabilities.accept && canDecide(attempt)}
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        class="min-h-10 shrink-0 px-2.5 text-xs font-semibold whitespace-nowrap text-emerald-700 hover:bg-emerald-50 hover:text-emerald-800 dark:text-emerald-400 dark:hover:bg-emerald-950/40"
                                                        onclick={() =>
                                                            accept(attempt)}
                                                    >
                                                        <CheckCircle2
                                                            class="mr-1 h-3.5 w-3.5 shrink-0"
                                                        />
                                                        Accept
                                                    </Button>
                                                    {#if capabilities.verify_documents && hasUnverifiedDocuments(attempt)}
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            class="min-h-10 shrink-0 border-emerald-600/30 px-2.5 text-xs font-semibold whitespace-nowrap text-emerald-700 hover:bg-emerald-50 hover:text-emerald-800 dark:text-emerald-400 dark:hover:bg-emerald-950/40"
                                                            title="Accept application and verify all document requirements"
                                                            onclick={() =>
                                                                acceptAndVerify(
                                                                    attempt,
                                                                )}
                                                        >
                                                            <CheckCheck
                                                                class="mr-1 h-3.5 w-3.5 shrink-0"
                                                            />
                                                            Accept & verify all documents
                                                        </Button>
                                                    {/if}
                                                {/if}
                                                {#if capabilities.dismiss && canDecide(attempt)}
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        class="text-destructive hover:bg-destructive/5 ml-auto min-h-10 shrink-0 px-2.5 text-xs font-semibold whitespace-nowrap"
                                                        onclick={() =>
                                                            dismiss(attempt)}
                                                    >
                                                        <XCircle
                                                            class="mr-1 h-3.5 w-3.5 shrink-0"
                                                        />
                                                        Dismiss
                                                    </Button>
                                                {/if}
                                                {#if capabilities.accept && canRevert(attempt)}
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        class="min-h-10 shrink-0 px-2.5 text-xs font-semibold whitespace-nowrap"
                                                        onclick={() =>
                                                            reopen(attempt)}
                                                    >
                                                        <RotateCcw
                                                            class="mr-1 h-3.5 w-3.5 shrink-0"
                                                        />
                                                        Reopen
                                                    </Button>
                                                {/if}
                                            </div>
                                        {/if}
                                    </div>
                                </li>
                            {/each}
                        </ul>
                    {/snippet}
                </SwitchableListView>

                <SimplePagination data={attempts} />
                <BulkActionBar
                    count={selectedSnapshots.length}
                    onClear={() => (selectedSnapshots = [])}
                >
                    {#snippet actions()}
                        {#if pendingSelected.length > 0 && capabilities?.accept}
                            <Button
                                class="min-h-9 gap-1.5 bg-emerald-600 text-xs font-semibold text-white hover:bg-emerald-700"
                                onclick={() => (bulkAcceptDialogOpen = true)}
                            >
                                <CheckCircle2 class="size-3.5" /> Accept Pending ({pendingSelected.length})
                            </Button>
                            {#if capabilities?.verify_documents}
                                <Button
                                    variant="outline"
                                    class="min-h-9 gap-1.5 border-emerald-600/30 text-xs font-semibold text-emerald-700 hover:bg-emerald-50 dark:text-emerald-300 dark:hover:bg-emerald-950/40"
                                    onclick={() =>
                                        (bulkAcceptAndVerifyDialogOpen = true)}
                                    title="Accept selected applications and mark all document requirements as verified"
                                >
                                    <CheckCheck class="size-3.5" /> Accept & Verify
                                    All Documents ({pendingSelected.length})
                                </Button>
                            {/if}
                        {/if}
                        {#if pendingSelected.length > 0 && capabilities?.correct}
                            <Button
                                variant="outline"
                                class="min-h-9 gap-1.5 text-xs font-semibold"
                                onclick={() =>
                                    (bulkCorrectionDialogOpen = true)}
                            >
                                <FileWarning class="size-3.5" /> Request Correction
                                ({pendingSelected.length})
                            </Button>
                        {/if}
                        {#if acceptedSelected.length > 0}
                            <Button
                                class="min-h-9 gap-1.5 text-xs font-semibold"
                                onclick={openDirectAssessmentDialog}
                            >
                                <GraduationCap class="size-3.5" /> Direct Assessment
                                ({acceptedSelected.length})
                            </Button>
                        {/if}
                        <PrintDocumentsSlot
                            slotKey="admissions_registry_bulk"
                            attemptIds={selectedIds}
                            autoStart={false}
                            compact={true}
                        />
                    {/snippet}
                </BulkActionBar>
            {/if}
        {/if}
        {#if can_import_applicants}
            <ContextualImportModal
                bind:open={importModalOpen}
                importType="applicants_only"
                academicCycle={(() => {
                    const c =
                        cycles.find(
                            (c) => String(c.value) === String(filterCycle),
                        ) ?? cycles.find((c) => c.is_active);
                    return c ? { id: c.id, label: c.label } : null;
                })()}
            />
        {/if}
        <Dialog.Root bind:open={bulkAcceptDialogOpen}>
            <Dialog.Content class="sm:max-w-lg">
                <Dialog.Header>
                    <Dialog.Title
                        >Accept {pendingSelected.length} application{pendingSelected.length ===
                        1
                            ? ''
                            : 's'}</Dialog.Title
                    >
                    <Dialog.Description
                        >Confirm acceptance for the selected applicants.</Dialog.Description
                    >
                </Dialog.Header>
                <ul
                    class="max-h-40 space-y-2 overflow-y-auto rounded-md border p-3 text-sm"
                >
                    {#each pendingSelected as applicant (applicant.id)}
                        <li class="flex justify-between gap-3">
                            <span>{applicant.name}</span><span
                                class="text-muted-foreground shrink-0"
                                >{applicant.reference_code}</span
                            >
                        </li>
                    {/each}
                </ul>
                <Dialog.Footer>
                    <Button
                        type="button"
                        variant="outline"
                        disabled={bulkAccepting}
                        onclick={() => (bulkAcceptDialogOpen = false)}
                        >Cancel</Button
                    >
                    <Button
                        type="button"
                        disabled={bulkAccepting || pendingSelected.length === 0}
                        onclick={submitBulkAccept}
                    >
                        {bulkAccepting ? 'Accepting…' : 'Confirm Accept'}
                    </Button>
                </Dialog.Footer>
            </Dialog.Content>
        </Dialog.Root>
        <Dialog.Root bind:open={bulkAcceptAndVerifyDialogOpen}>
            <Dialog.Content class="sm:max-w-lg">
                <Dialog.Header>
                    <Dialog.Title
                        >Accept & verify all documents for {pendingSelected.length}
                        application{pendingSelected.length === 1
                            ? ''
                            : 's'}</Dialog.Title
                    >
                    <Dialog.Description
                        >This will accept the selected applications and mark all
                        their document requirements as verified.</Dialog.Description
                    >
                </Dialog.Header>
                <ul
                    class="max-h-40 space-y-2 overflow-y-auto rounded-md border p-3 text-sm"
                >
                    {#each pendingSelected as applicant (applicant.id)}
                        <li class="flex justify-between gap-3">
                            <span>{applicant.name}</span><span
                                class="text-muted-foreground shrink-0"
                                >{applicant.reference_code}</span
                            >
                        </li>
                    {/each}
                </ul>
                <Dialog.Footer>
                    <Button
                        type="button"
                        variant="outline"
                        disabled={bulkAcceptingAndVerifying}
                        onclick={() => (bulkAcceptAndVerifyDialogOpen = false)}
                        >Cancel</Button
                    >
                    <Button
                        type="button"
                        class="bg-emerald-600 text-white hover:bg-emerald-700"
                        disabled={bulkAcceptingAndVerifying ||
                            pendingSelected.length === 0}
                        onclick={submitBulkAcceptAndVerify}
                    >
                        <CheckCheck class="mr-1.5 size-4" />
                        {bulkAcceptingAndVerifying
                            ? 'Accepting & Verifying…'
                            : 'Confirm Accept & Verify All Documents'}
                    </Button>
                </Dialog.Footer>
            </Dialog.Content>
        </Dialog.Root>
        <Dialog.Root bind:open={bulkCorrectionDialogOpen}>
            <Dialog.Content class="sm:max-w-lg">
                <Dialog.Header>
                    <Dialog.Title
                        >Request correction for {pendingSelected.length} application{pendingSelected.length ===
                        1
                            ? ''
                            : 's'}</Dialog.Title
                    >
                    <Dialog.Description
                        >Choose a reason or enter instructions for the selected
                        applicants.</Dialog.Description
                    >
                </Dialog.Header>
                <div class="flex flex-wrap gap-2">
                    {#each ['Blurry PSA Birth Certificate', 'Missing Grade 12 SF-9 / Form 138', 'Incomplete Certificate of Good Moral', 'Unreadable Grade Slip'] as preset}
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onclick={() => (bulkCorrectionReason = preset)}
                            >{preset}</Button
                        >
                    {/each}
                </div>
                <Textarea
                    bind:value={bulkCorrectionReason}
                    rows={4}
                    placeholder="Describe the correction required"
                    aria-label="Correction reason"
                />
                <Dialog.Footer>
                    <Button
                        type="button"
                        variant="outline"
                        disabled={bulkCorrecting}
                        onclick={() => (bulkCorrectionDialogOpen = false)}
                        >Cancel</Button
                    >
                    <Button
                        type="button"
                        disabled={bulkCorrecting ||
                            pendingSelected.length === 0 ||
                            !bulkCorrectionReason.trim()}
                        onclick={submitBulkCorrection}
                    >
                        {bulkCorrecting
                            ? 'Sending…'
                            : 'Send Correction Requests'}
                    </Button>
                </Dialog.Footer>
            </Dialog.Content>
        </Dialog.Root>
        <Dialog.Root bind:open={directAssessmentDialogOpen}>
            <Dialog.Content class="sm:max-w-lg">
                <Dialog.Header>
                    <Dialog.Title>Direct Assessment Bypass</Dialog.Title>
                    <Dialog.Description
                        >Register {acceptedSelectedSnapshots.length} accepted applicant{acceptedSelectedSnapshots.length ===
                        1
                            ? ''
                            : 's'} for a schedule-free direct assessment session.</Dialog.Description
                    >
                </Dialog.Header>
                <form
                    onsubmit={(event) => {
                        event.preventDefault();
                        submitDirectAssessment();
                    }}
                    class="space-y-4"
                >
                    <div class="space-y-2">
                        <Label for="bulk-direct-cycle">Academic Cycle</Label>
                        <select
                            id="bulk-direct-cycle"
                            bind:value={directAssessmentForm.academic_cycle_id}
                            required
                            class="border-input bg-background flex min-h-10 w-full rounded-md border px-3 py-2 text-sm"
                        >
                            <option value="" disabled
                                >Select academic cycle</option
                            >
                            {#each cycles as cycle (cycle.value)}
                                <option value={String(cycle.value)}
                                    >{cycle.label}</option
                                >
                            {/each}
                        </select>
                        {#if directAssessmentForm.errors.academic_cycle_id}<p
                                class="text-destructive text-sm"
                            >
                                {directAssessmentForm.errors.academic_cycle_id}
                            </p>{/if}
                    </div>
                    <div class="space-y-2">
                        <Label for="bulk-direct-label">Label</Label>
                        <Input
                            id="bulk-direct-label"
                            bind:value={directAssessmentForm.label}
                            placeholder="e.g. Walk-in Batch 1"
                        />
                        {#if directAssessmentForm.errors.label}<p
                                class="text-destructive text-sm"
                            >
                                {directAssessmentForm.errors.label}
                            </p>{/if}
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="bulk-direct-date">Date</Label>
                            <Input
                                id="bulk-direct-date"
                                type="date"
                                bind:value={directAssessmentForm.date}
                                required
                            />
                            {#if directAssessmentForm.errors.date}<p
                                    class="text-destructive text-sm"
                                >
                                    {directAssessmentForm.errors.date}
                                </p>{/if}
                        </div>
                        <div class="space-y-2">
                            <Label for="bulk-direct-start">Start Time</Label>
                            <Input
                                id="bulk-direct-start"
                                type="time"
                                bind:value={directAssessmentForm.start_time}
                                required
                            />
                            {#if directAssessmentForm.errors.start_time}<p
                                    class="text-destructive text-sm"
                                >
                                    {directAssessmentForm.errors.start_time}
                                </p>{/if}
                        </div>
                    </div>
                    {#if directAssessmentForm.errors.applicant_ids}<p
                            class="text-destructive text-sm"
                        >
                            {directAssessmentForm.errors.applicant_ids}
                        </p>{/if}
                    <Dialog.Footer>
                        <Button
                            type="button"
                            variant="outline"
                            onclick={() => (directAssessmentDialogOpen = false)}
                            >Cancel</Button
                        >
                        <Button
                            type="submit"
                            disabled={directAssessmentForm.processing ||
                                acceptedSelectedSnapshots.length === 0}
                        >
                            {directAssessmentForm.processing
                                ? 'Submitting…'
                                : 'Create Session'}
                        </Button>
                    </Dialog.Footer>
                </form>
            </Dialog.Content>
        </Dialog.Root>
        <Dialog.Root bind:open={previewDialogOpen}>
            <Dialog.Content class="sm:max-w-lg">
                {#if previewAttempt}
                    <Dialog.Header>
                        <Dialog.Title>Applicant preview</Dialog.Title>
                        <Dialog.Description>
                            A quick summary of this admission application.
                        </Dialog.Description>
                    </Dialog.Header>
                    <div class="space-y-4">
                        <div
                            class="border-border bg-muted/30 flex items-center gap-3 rounded-xl border p-4"
                        >
                            <div
                                class="bg-primary/10 text-primary flex size-12 shrink-0 items-center justify-center rounded-full font-semibold"
                            >
                                {initials(previewAttempt.applicant_name)}
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-semibold">
                                    {previewAttempt.applicant_name}
                                </p>
                                <p
                                    class="text-muted-foreground truncate text-sm"
                                >
                                    {previewAttempt.contact_email}
                                </p>
                            </div>
                            <Badge
                                variant={statusVariant(previewAttempt.status)}
                                class="shrink-0 capitalize"
                            >
                                {statusLabel(previewAttempt.status)}
                            </Badge>
                        </div>
                        <dl class="grid gap-3 text-sm sm:grid-cols-2">
                            <div>
                                <dt
                                    class="text-muted-foreground text-xs font-medium"
                                >
                                    Reference code
                                </dt>
                                <dd class="mt-1 font-mono font-medium">
                                    {previewAttempt.reference_code}
                                </dd>
                            </div>
                            <div>
                                <dt
                                    class="text-muted-foreground text-xs font-medium"
                                >
                                    Program
                                </dt>
                                <dd class="mt-1 font-medium">
                                    {previewAttempt.preferred_program_code ??
                                        '—'}
                                </dd>
                            </div>
                            <div class="sm:col-span-2">
                                <dt
                                    class="text-muted-foreground text-xs font-medium"
                                >
                                    Date submitted
                                </dt>
                                <dd class="mt-1">
                                    {formatDateTime(
                                        previewAttempt.submitted_at,
                                    )}
                                </dd>
                            </div>
                        </dl>
                    </div>
                    <Dialog.Footer>
                        <Button
                            type="button"
                            variant="outline"
                            class="min-h-[44px]"
                            onclick={() => (previewDialogOpen = false)}
                        >
                            Close
                        </Button>
                        <Link href={`/admissions/${previewAttempt.id}`}>
                            <Button type="button" class="min-h-[44px]">
                                Open application
                            </Button>
                        </Link>
                    </Dialog.Footer>
                {/if}
            </Dialog.Content>
        </Dialog.Root>
    </div>
</StaffLayout>
