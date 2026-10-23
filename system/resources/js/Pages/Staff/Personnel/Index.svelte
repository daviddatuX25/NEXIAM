<script>
    import { onDestroy } from 'svelte';
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
    import { Input } from '@/Components/ui/input';
    import SortDropdown from '@/Components/kit/SortDropdown.svelte';
    import ActiveFilterChips from '@/Components/kit/ActiveFilterChips.svelte';
    import SwitchableListView from '@/Components/kit/SwitchableListView.svelte';
    import OperationalCard from '@/Components/kit/OperationalCard.svelte';
    import AvatarBadge from '@/Components/kit/AvatarBadge.svelte';
    import SimplePagination from '@/Components/kit/SimplePagination.svelte';
    import VoucherDialog from '@/Pages/Staff/Personnel/VoucherDialog.svelte';
    import BulkActionBar from '@/Components/kit/BulkActionBar.svelte';
    import BatchVouchers from '@/Pages/Staff/Personnel/BatchVouchers.svelte';
    import RolloverModal from '@/Pages/Staff/Personnel/RolloverModal.svelte';
    import FastOnboardingModal from '@/Pages/Staff/Personnel/FastOnboardingModal.svelte';
    import { Button } from '@/Components/ui/button';
    import { Badge } from '@/Components/ui/badge';
    import * as Table from '@/Components/ui/table';
    import { AlertDialog, DropdownMenu } from 'bits-ui';
    import { Link, router } from '@inertiajs/svelte';
    import {
        Archive,
        ArrowDown,
        ArrowUp,
        Check,
        Ellipsis,
        Filter,
        Plus,
        RefreshCw,
        Search,
    } from 'lucide-svelte';
    let {
        users = { data: [], total: 0 },
        roles = [],
        offices = [],
        departments = [],
        programs = [],
        filters = {},
        setup_scope_options = {},
        errors = {},
        can_full_hr = false,
        can_manage_personnel = false,
        can_delegate_ta = false,
        can_delegate_setup = false,
        cycles = [],
        actor_office = 'admin',
    } = $props();
    const rows = $derived(users?.data ?? []);
    const normalizedPrograms = $derived(
        programs.map((p) => ({
            code: p.value ?? p.code,
            name: p.label ?? p.name,
            department_code: p.department_code,
        })),
    );
    let viewMode = $state('responsive');
    let filterSearch = $state(filters.search ?? '');
    let filterOffice = $state(filters.office ?? '');
    const showAcademicFilters = $derived(
        filterOffice === '' || filterOffice === 'program',
    );
    let filterDepartment = $state(filters.department ?? '');
    let filterProgram = $state(filters.program ?? '');
    let filterRole = $state(filters.role ?? '');
    let filterStatus = $state(filters.status ?? '');
    let filterSort = $state(filters.sort ?? 'name');
    let filterSortDir = $state(filters.dir ?? 'asc');
    let busyTa = $state(null);
    let busySetup = $state(null);
    let busyPersonnel = $state(null);
    let voucherTarget = $state(null);
    let voucherPurpose = $state('activation');
    let filterVisitPending = $state(false);
    let voucherDialogOpen = $state(false);
    let setupTarget = $state(null);
    let setupAction = $state(null);
    let setupReason = $state('');
    let selectedScopes = $state([]);
    let setupDialogOpen = $state(false);
    let deactivateTarget = $state(null);
    let deactivateDialogOpen = $state(false);
    let debounceTimer;
    let mobileFiltersOpen = $state(false);
    let selectedIds = $state([]);
    let rolloverModalOpen = $state(false);
    let rolloverTargetIds = $state([]);
    let rolloverInitialDisposition = $state('retain');
    let fastOnboardingModalOpen = $state(false);

    const personnelRows = $derived(
        rows.map((u) => ({
            id: u.id,
            name: u.name,
            email: u.email,
            office: u.office,
            department: u.department,
            program_codes: u.program_codes ?? [],
            roles: u.roles?.map((r) => r.name) ?? [],
            status: u.status,
            can_manage: u.can_manage_user,
            can_manage_user: u.can_manage_user,
            is_self: u.is_self,
        })),
    );

    function openCycleRollover(targetIds = [], disposition = 'retain') {
        rolloverTargetIds = targetIds.length > 0 ? targetIds : selectedIds;
        rolloverInitialDisposition = disposition;
        rolloverModalOpen = true;
    }

    function openFastOnboarding() {
        fastOnboardingModalOpen = true;
    }

    function onRolloverSuccess() {
        clearSelection();
        router.reload({ preserveScroll: true });
    }

    function onOnboardingSuccess() {
        router.reload({ preserveScroll: true });
    }

    const eligibleRows = $derived(rows.filter((u) => u.can_issue_voucher));
    const selectedEligibleRows = $derived(
        rows.filter((u) => selectedIds.includes(u.id) && u.can_issue_voucher),
    );
    const batchTargets = $derived(
        selectedEligibleRows.map((u) => ({
            id: u.id,
            name: u.name,
            email: u.email,
            office: u.office,
            program_codes: u.program_codes,
        })),
    );
    const allEligibleSelected = $derived(
        eligibleRows.length > 0 &&
            eligibleRows.every((u) => selectedIds.includes(u.id)),
    );
    const someEligibleSelected = $derived(
        eligibleRows.some((u) => selectedIds.includes(u.id)),
    );

    function toggleAllEligible() {
        if (allEligibleSelected) {
            const eligibleIdSet = new Set(eligibleRows.map((u) => u.id));
            selectedIds = selectedIds.filter((id) => !eligibleIdSet.has(id));
        } else {
            const newSet = new Set([
                ...selectedIds,
                ...eligibleRows.map((u) => u.id),
            ]);
            selectedIds = Array.from(newSet);
        }
    }

    function toggleRowSelection(userId) {
        selectedIds = selectedIds.includes(userId)
            ? selectedIds.filter((id) => id !== userId)
            : [...selectedIds, userId];
    }

    function clearSelection() {
        selectedIds = [];
    }

    const sortOptions = [
        { value: 'name', label: 'Name' },
        { value: 'email', label: 'Email' },
        { value: 'office', label: 'Office' },
        { value: 'program', label: 'Program' },
        { value: 'created_at', label: 'Created' },
    ];
    const breadcrumbs = [
        { label: 'Staff Workspace', href: '/staff' },
        { label: 'Personnel Directory' },
    ];

    $effect(() => {
        filterSearch = filters.search ?? '';
        filterOffice = filters.office ?? '';
        filterRole = filters.role ?? '';
        filterDepartment = filters.department ?? '';
        filterProgram = filters.program ?? '';
        filterStatus = filters.status ?? '';
        filterSort = filters.sort ?? 'name';
        filterSortDir = filters.dir ?? 'asc';
    });

    const activeFilterChips = $derived([
        ...(filterSearch.trim()
            ? [{ id: 'search', label: `Search: ${filterSearch.trim()}` }]
            : []),
        ...(filterOffice
            ? [
                  {
                      id: 'office',
                      label: `Office: ${offices.find((office) => office.value === filterOffice)?.label ?? filterOffice}`,
                  },
              ]
            : []),
        ...(filterDepartment
            ? [
                  {
                      id: 'department',
                      label: `Department: ${departments.find((department) => department.value === filterDepartment)?.label ?? filterDepartment}`,
                  },
              ]
            : []),
        ...(filterProgram
            ? [
                  {
                      id: 'program',
                      label: `Program: ${programs.find((program) => program.value === filterProgram)?.label ?? filterProgram}`,
                  },
              ]
            : []),
        ...(filterRole
            ? [
                  {
                      id: 'role',
                      label: `Role: ${roles.find((role) => role.name === filterRole)?.display_name ?? filterRole}`,
                  },
              ]
            : []),
        ...(filterStatus
            ? [
                  {
                      id: 'status',
                      label: `Status: ${filterStatus === 'active' ? 'Active' : 'Inactive'}`,
                  },
              ]
            : []),
    ]);

    const filteredPrograms = $derived(
        filterDepartment
            ? programs.filter(
                  (program) => program.department_code === filterDepartment,
              )
            : programs,
    );

    function changeDepartment(value) {
        filterDepartment = value === 'all' ? '' : (value ?? '');
        if (
            filterProgram &&
            !programs.some(
                (program) =>
                    program.value === filterProgram &&
                    (!filterDepartment ||
                        program.department_code === filterDepartment),
            )
        ) {
            filterProgram = '';
        }
        requestFilters();
    }

    function requestFilters({ replace = false } = {}) {
        clearTimeout(debounceTimer);
        router.get(
            '/staff/personnel',
            {
                search: filterSearch.trim() || undefined,
                office: filterOffice || undefined,
                department: filterDepartment || undefined,
                program: filterProgram || undefined,
                role: filterRole || undefined,
                status: filterStatus || undefined,
                sort: filterSort || undefined,
                dir: filterSortDir || undefined,
                page: 1,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace,
                onStart: () => {
                    filterVisitPending = true;
                },
                onFinish: () => {
                    filterVisitPending = false;
                },
            },
        );
    }

    function onSearchInput() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(
            () => requestFilters({ replace: true }),
            300,
        );
    }

    function onSortChange({ sort, dir }) {
        filterSort = sort;
        filterSortDir = dir;
        requestFilters();
    }

    function clearFilter(id) {
        clearTimeout(debounceTimer);
        if (id === 'search') filterSearch = '';
        if (id === 'office') filterOffice = '';
        if (id === 'department') filterDepartment = '';
        if (id === 'program') filterProgram = '';
        if (id === 'role') filterRole = '';
        if (id === 'status') filterStatus = '';
        requestFilters();
    }

    function clearAllFilters() {
        clearTimeout(debounceTimer);
        filterSearch = '';
        filterOffice = '';
        filterDepartment = '';
        filterProgram = '';
        filterRole = '';
        filterStatus = '';
        requestFilters();
    }
    onDestroy(() => clearTimeout(debounceTimer));

    function openVoucher(user, purpose = 'activation') {
        voucherTarget = user;
        voucherPurpose = purpose;
        voucherDialogOpen = true;
    }

    function primaryAction(user) {
        if (user.is_self)
            return { href: '/staff/profile', label: 'Manage Profile' };
        if (user.can_manage_user && can_manage_personnel)
            return { href: `/staff/personnel/${user.id}/edit`, label: 'Edit' };
        return null;
    }

    function tableActionLabel(user) {
        return `More actions for ${user.name}`;
    }

    function menuItems(user) {
        const items = [];
        if (user.can_issue_voucher)
            items.push({ id: 'voucher', label: 'Issue activation voucher' });
        if (user.can_reset_mfa)
            items.push({ id: 'mfa-reset', label: 'Reset Two-Factor Device' });
        if (user.can_require_mfa)
            items.push({
                id: 'require-mfa',
                label: 'Require Two-Factor Login',
            });
        if (user.can_disable_mfa)
            items.push({
                id: 'disable-mfa',
                label: 'Turn Off Two-Factor Login',
            });
        if (can_delegate_ta && user.can_delegate_ta) {
            items.push({
                id: user.ta_status === 'delegated' ? 'revoke-ta' : 'grant-ta',
                label:
                    user.ta_status === 'delegated'
                        ? 'Revoke Test Administrator delegation'
                        : 'Delegate Test Administrator',
            });
        }
        if (can_delegate_setup && user.can_delegate_setup) {
            items.push({
                id:
                    user.setup_status === 'delegated'
                        ? 'edit-setup'
                        : 'grant-setup',
                label:
                    user.setup_status === 'delegated'
                        ? 'Edit setup responsibilities'
                        : 'Delegate institutional setup',
            });
            if (user.setup_status === 'delegated')
                items.push({
                    id: 'revoke-setup',
                    label: 'Revoke setup delegation',
                });
        }
        if (can_manage_personnel && user.can_manage_user && !user.is_self) {
            items.push({ separator: true, id: 'account-lifecycle-separator' });
            if (user.status === 'active') {
                items.push({ id: 'retain-cycle', label: 'Retain for cycle' });
                if (user.office === 'program') {
                    items.push({
                        id: 'rotate-cycle',
                        label: 'Review program assignment',
                    });
                }
            }
            items.push(
                user.status === 'active'
                    ? {
                          id: 'deactivate',
                          label: 'Deactivate account',
                          destructive: true,
                      }
                    : { id: 'activate', label: 'Activate account' },
            );
        }
        return items;
    }

    function openSetupDialog(user, action) {
        setupTarget = user;
        setupAction = action;
        setupReason = '';
        selectedScopes =
            action === 'edit'
                ? [...(user.setup_scopes ?? [])]
                : action === 'grant'
                  ? Object.keys(setup_scope_options)
                  : [];
        setupDialogOpen = true;
    }

    function closeSetupDialog() {
        if (busySetup === setupTarget?.id) return;
        setupDialogOpen = false;
        setupTarget = null;
        setupAction = null;
        setupReason = '';
        selectedScopes = [];
    }

    function toggleSetupScope(scope) {
        selectedScopes = selectedScopes.includes(scope)
            ? selectedScopes.filter((selected) => selected !== scope)
            : [...selectedScopes, scope];
    }

    function submitSetupAction(event) {
        event.preventDefault();
        if (
            !setupTarget ||
            !setupAction ||
            setupReason.trim().length < 3 ||
            setupReason.trim().length > 500
        )
            return;
        if (setupAction !== 'revoke' && selectedScopes.length === 0) return;
        const target = setupTarget;
        const action = setupAction;
        const path = action === 'revoke' ? 'revoke-setup' : 'grant-setup';
        const payload = { reason: setupReason.trim() };
        if (action !== 'revoke') payload.scopes = selectedScopes;
        busySetup = target.id;
        router.post(`/staff/personnel/${target.id}/${path}`, payload, {
            onSuccess: (response) => {
                if (!response.props.flash?.error) {
                    setupDialogOpen = false;
                    setupTarget = null;
                    setupAction = null;
                    setupReason = '';
                    selectedScopes = [];
                }
            },
            onFinish: () => {
                busySetup = null;
            },
        });
    }

    function confirmDeactivate() {
        if (!deactivateTarget || busyPersonnel === deactivateTarget.id) return;
        const id = deactivateTarget.id;
        busyPersonnel = id;
        router.post(
            `/staff/personnel/${id}/deactivate`,
            {},
            {
                onFinish: () => {
                    busyPersonnel = null;
                },
            },
        );
        deactivateDialogOpen = false;
        deactivateTarget = null;
    }

    function runAccessAction(action, user) {
        if (
            ((action === 'grant-ta' || action === 'revoke-ta') &&
                busyTa === user.id) ||
            ((action === 'grant-setup' ||
                action === 'edit-setup' ||
                action === 'revoke-setup') &&
                busySetup === user.id) ||
            ((action === 'activate' ||
                action === 'deactivate' ||
                action === 'require-mfa' ||
                action === 'disable-mfa') &&
                busyPersonnel === user.id)
        )
            return;
        if (action === 'voucher') openVoucher(user, 'activation');
        else if (action === 'retain-cycle')
            openCycleRollover([user.id], 'retain');
        else if (action === 'rotate-cycle')
            openCycleRollover([user.id], 'rotate');
        else if (action === 'mfa-reset') openVoucher(user, 'mfa_reset');
        else if (action === 'require-mfa') {
            busyPersonnel = user.id;
            router.post(
                `/staff/personnel/${user.id}/require-mfa`,
                {},
                {
                    onFinish: () => {
                        busyPersonnel = null;
                    },
                },
            );
        } else if (action === 'disable-mfa') {
            busyPersonnel = user.id;
            router.post(
                `/staff/personnel/${user.id}/disable-mfa`,
                {},
                {
                    onFinish: () => {
                        busyPersonnel = null;
                    },
                },
            );
        } else if (action === 'grant-ta' || action === 'revoke-ta') {
            busyTa = user.id;
            router.post(
                `/staff/personnel/${user.id}/${action}`,
                {},
                {
                    onFinish: () => {
                        busyTa = null;
                    },
                },
            );
        } else if (action === 'grant-setup' || action === 'edit-setup')
            openSetupDialog(user, action === 'edit-setup' ? 'edit' : 'grant');
        else if (action === 'revoke-setup') openSetupDialog(user, 'revoke');
        else if (action === 'deactivate') {
            deactivateTarget = user;
            deactivateDialogOpen = true;
        } else if (action === 'activate') {
            busyPersonnel = user.id;
            router.post(
                `/staff/personnel/${user.id}/activate`,
                {},
                {
                    onFinish: () => {
                        busyPersonnel = null;
                    },
                },
            );
        }
    }

    function scopeLabel(scope) {
        return (
            { cycles: 'Cycles', rooms: 'Rooms', programs: 'Programs' }[scope] ??
            scope
        );
    }
</script>

<svelte:head><title>Staff Directory</title></svelte:head>

<StaffLayout {breadcrumbs}>
    <div class="min-w-0 space-y-6 p-6">
        <PageHeaderBar
            title="Staff Directory"
            tourKey="page:Staff/Personnel/Index"
        >
            {#snippet actions()}
                {#if can_manage_personnel}
                    <div class="flex flex-wrap items-center gap-2">
                        <Button
                            class="min-h-10 gap-1.5 shadow-xs"
                            onclick={() => openFastOnboarding()}
                        >
                            <Plus class="size-4" />
                            Onboard Staff
                        </Button>
                        <Button
                            variant="outline"
                            class="min-h-10 gap-1.5 shadow-xs"
                            onclick={() => openCycleRollover()}
                        >
                            <RefreshCw class="size-3.5" />
                            Cycle Rollover
                        </Button>
                    </div>
                {/if}
            {/snippet}
        </PageHeaderBar>

        <div
            class="bg-background/80 sticky top-0 z-20 space-y-2.5 py-2 backdrop-blur-md"
        >
            <div class="flex items-center gap-2">
                <div class="relative min-w-0 flex-1">
                    <Search
                        class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2"
                    />
                    <Input
                        id="personnel-search"
                        type="search"
                        bind:value={filterSearch}
                        oninput={(e) => {
                            filterSearch = e.currentTarget.value;
                            onSearchInput();
                        }}
                        onkeydown={(e) => {
                            if (e.key === 'Enter') {
                                filterSearch = e.currentTarget.value;
                                clearTimeout(debounceTimer);
                                requestFilters();
                            }
                        }}
                        placeholder="Search staff by name or email…"
                        aria-label="Search personnel"
                        class="border-input bg-background h-10 min-h-[40px] w-full rounded-lg border pr-9 pl-9 text-sm shadow-xs"
                    />
                    {#if filterSearch}
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            class="text-muted-foreground hover:text-foreground absolute top-1/2 right-1 size-8 -translate-y-1/2"
                            aria-label="Clear search"
                            onclick={() => {
                                filterSearch = '';
                                requestFilters();
                            }}
                        >
                            <span
                                aria-hidden="true"
                                class="text-base font-semibold">×</span
                            >
                        </Button>
                    {/if}
                </div>

                <Button
                    type="button"
                    variant="outline"
                    class="h-10 min-h-[40px] shrink-0 gap-1.5 px-3 {activeFilterChips.length >
                    0
                        ? 'border-primary text-primary font-semibold'
                        : ''}"
                    onclick={() => (mobileFiltersOpen = !mobileFiltersOpen)}
                    aria-expanded={mobileFiltersOpen}
                    aria-label="Toggle filters"
                >
                    <Filter class="size-3.5" />
                    <span class="xs:inline hidden">Filters</span>
                    {#if activeFilterChips.length > 0}
                        <Badge
                            variant="secondary"
                            class="ml-0.5 min-h-4 px-1 py-0 text-[10px]"
                            >{activeFilterChips.length}</Badge
                        >
                    {/if}
                </Button>

                <div class="hidden shrink-0 sm:block">
                    <SortDropdown
                        options={sortOptions}
                        sort={filterSort}
                        dir={filterSortDir}
                        onchange={onSortChange}
                    />
                </div>

            </div>

            {#if mobileFiltersOpen}
                <div
                    id="personnel-mobile-filters"
                    class="border-border/60 bg-card animate-in fade-in-50 space-y-3 rounded-lg border p-3 shadow-xs duration-150"
                >
                    <div
                        class="grid grid-cols-1 gap-2.5 sm:grid-cols-2 lg:grid-cols-4"
                    >
                        <label class="flex flex-col gap-1 text-xs">
                            <span class="text-muted-foreground font-medium"
                                >Office</span
                            >
                            <select
                                value={filterOffice || 'all'}
                                onchange={(e) => {
                                    filterOffice =
                                        e.currentTarget.value === 'all'
                                            ? ''
                                            : e.currentTarget.value;
                                    requestFilters();
                                }}
                                class="border-input bg-background h-9 rounded-md border px-2 text-xs"
                            >
                                <option value="all">All offices</option>
                                {#each offices as office (office.value)}
                                    <option value={office.value}
                                        >{office.label}</option
                                    >
                                {/each}
                            </select>
                        </label>

                        {#if showAcademicFilters}
                            <label class="flex flex-col gap-1 text-xs">
                                <span class="text-muted-foreground font-medium"
                                    >Department</span
                                >
                                <select
                                    value={filterDepartment || 'all'}
                                    onchange={(e) =>
                                        changeDepartment(e.currentTarget.value)}
                                    class="border-input bg-background h-9 rounded-md border px-2 text-xs"
                                >
                                    <option value="all">All departments</option>
                                    {#each departments as dept (dept.value)}
                                        <option value={dept.value}
                                            >{dept.label}</option
                                        >
                                    {/each}
                                </select>
                            </label>

                            <label class="flex flex-col gap-1 text-xs">
                                <span class="text-muted-foreground font-medium"
                                    >Program</span
                                >
                                <select
                                    value={filterProgram || 'all'}
                                    onchange={(e) => {
                                        filterProgram =
                                            e.currentTarget.value === 'all'
                                                ? ''
                                                : e.currentTarget.value;
                                        requestFilters();
                                    }}
                                    class="border-input bg-background h-9 rounded-md border px-2 text-xs"
                                >
                                    <option value="all">All programs</option>
                                    {#each filteredPrograms as prog (prog.value)}
                                        <option value={prog.value}
                                            >{prog.label}</option
                                        >
                                    {/each}
                                </select>
                            </label>
                        {/if}

                        <label class="flex flex-col gap-1 text-xs">
                            <span class="text-muted-foreground font-medium"
                                >Role</span
                            >
                            <select
                                value={filterRole || 'all'}
                                onchange={(e) => {
                                    filterRole =
                                        e.currentTarget.value === 'all'
                                            ? ''
                                            : e.currentTarget.value;
                                    requestFilters();
                                }}
                                class="border-input bg-background h-9 rounded-md border px-2 text-xs"
                            >
                                <option value="all">All roles</option>
                                {#each roles as role (role.name)}
                                    <option value={role.name}
                                        >{role.display_name}</option
                                    >
                                {/each}
                            </select>
                        </label>

                        <label class="flex flex-col gap-1 text-xs">
                            <span class="text-muted-foreground font-medium"
                                >Status</span
                            >
                            <select
                                value={filterStatus || 'all'}
                                onchange={(e) => {
                                    filterStatus =
                                        e.currentTarget.value === 'all'
                                            ? ''
                                            : e.currentTarget.value;
                                    requestFilters();
                                }}
                                class="border-input bg-background h-9 rounded-md border px-2 text-xs"
                            >
                                <option value="all">All statuses</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </label>
                    </div>

                    <div
                        class="border-border/40 flex items-center justify-between border-t pt-2"
                    >
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onclick={clearAllFilters}
                            class="text-muted-foreground h-7 px-2 text-xs"
                        >
                            Reset filters
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            onclick={() => (mobileFiltersOpen = false)}
                            class="h-7 px-3 text-xs"
                        >
                            Done
                        </Button>
                    </div>
                </div>
            {/if}
        </div>

        <ActiveFilterChips
            chips={activeFilterChips}
            onRemove={clearFilter}
            onClearAll={clearAllFilters}
        />
        {#if filterVisitPending}<p
                class="text-muted-foreground text-xs"
                role="status"
                aria-live="polite"
            >
                Updating directory…
            </p>{/if}

        {#if rows.length === 0}
            <div
                class="border-border bg-card rounded-xl border border-dashed px-6 py-14 text-center"
            >
                <h2 class="text-base font-semibold">
                    {activeFilterChips.length
                        ? 'No matching staff'
                        : 'No staff accounts yet'}
                </h2>
                <p class="text-muted-foreground mt-2 text-sm">
                    {activeFilterChips.length
                        ? 'Adjust or clear filters to see staff in your authorized directory.'
                        : 'There are no staff accounts in your authorized directory.'}
                </p>
                {#if activeFilterChips.length}
                    <Button
                        class="mt-4"
                        variant="outline"
                        onclick={clearAllFilters}>Clear filters</Button
                    >
                {:else if can_manage_personnel}
                    <Link href="/staff/personnel/create"
                        ><Button class="mt-4">Create staff</Button></Link
                    >
                {/if}
            </div>
        {:else}
            <SwitchableListView bind:viewMode cardsInset={false}>
                {#snippet table()}
                    <Table.Root class="w-full min-w-[760px] text-sm">
                        <Table.Header
                            class="bg-muted/40 border-border border-b"
                        >
                            <Table.Row
                                class="border-border/40 hover:bg-muted/30 border-b transition-colors"
                            >
                                {#if can_manage_personnel}
                                    <Table.Head
                                        class="w-10 px-3 py-3 text-center align-middle"
                                    >
                                        {#if eligibleRows.length > 0}
                                            <input
                                                type="checkbox"
                                                checked={allEligibleSelected}
                                                indeterminate={!allEligibleSelected &&
                                                    someEligibleSelected}
                                                onchange={toggleAllEligible}
                                                aria-label="Select all eligible staff on this page"
                                                class="border-input size-4 rounded"
                                            />
                                        {/if}
                                    </Table.Head>
                                {/if}
                                <Table.Head
                                    class="px-4 py-3 text-left align-middle"
                                    >Staff</Table.Head
                                >
                                <Table.Head
                                    class="px-4 py-3 text-left align-middle"
                                    aria-sort={filterSort === 'office'
                                        ? filterSortDir === 'asc'
                                            ? 'ascending'
                                            : 'descending'
                                        : 'none'}
                                >
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1.5 font-medium"
                                        onclick={() =>
                                            onSortChange({
                                                sort: 'office',
                                                dir:
                                                    filterSort === 'office' &&
                                                    filterSortDir === 'asc'
                                                        ? 'desc'
                                                        : 'asc',
                                            })}
                                    >
                                        Office / Jurisdiction {#if filterSort === 'office'}{#if filterSortDir === 'asc'}<ArrowUp
                                                    class="size-3.5"
                                                    aria-hidden="true"
                                                />{:else}<ArrowDown
                                                    class="size-3.5"
                                                    aria-hidden="true"
                                                />{/if}{/if}
                                    </button>
                                </Table.Head>
                                <Table.Head
                                    class="px-4 py-3 text-left align-middle"
                                    >Roles &amp; designations</Table.Head
                                >
                                <Table.Head
                                    class="px-4 py-3 text-left align-middle"
                                    >Status / 2-Factor</Table.Head
                                >
                                <Table.Head
                                    class="w-px px-4 py-3 text-right align-middle whitespace-nowrap"
                                    >Actions</Table.Head
                                >
                            </Table.Row>
                        </Table.Header>
                        <Table.Body>
                            {#each rows as user (user.id)}
                                <Table.Row
                                    class="border-border/40 hover:bg-muted/30 border-b transition-colors"
                                >
                                    {#if can_manage_personnel}
                                        <Table.Cell
                                            class="w-10 px-3 py-3 text-center align-middle"
                                        >
                                            {#if user.can_issue_voucher}
                                                <input
                                                    type="checkbox"
                                                    checked={selectedIds.includes(
                                                        user.id,
                                                    )}
                                                    onchange={() =>
                                                        toggleRowSelection(
                                                            user.id,
                                                        )}
                                                    aria-label={`Select ${user.name}`}
                                                    class="border-input size-4 rounded"
                                                />
                                            {/if}
                                        </Table.Cell>
                                    {/if}
                                    <Table.Cell
                                        class="px-4 py-3 text-left align-middle"
                                    >
                                        <div
                                            class="flex min-w-0 items-center gap-3"
                                        >
                                            <AvatarBadge
                                                avatar={user.avatar}
                                                name={user.name}
                                                size="md"
                                            />
                                            <div class="min-w-0">
                                                <div
                                                    class="flex min-w-0 flex-wrap items-center gap-1.5"
                                                >
                                                    <span
                                                        class="text-foreground truncate font-semibold"
                                                        title={user.name}
                                                        >{user.name}</span
                                                    >{#if user.is_self}<Badge
                                                            class="min-h-6 px-2 py-0.5 text-xs font-medium"
                                                            variant="secondary"
                                                            >You</Badge
                                                        >{/if}
                                                </div>
                                                <p
                                                    class="text-muted-foreground truncate text-xs"
                                                    title={user.email}
                                                >
                                                    {user.email}
                                                </p>
                                            </div>
                                        </div>
                                    </Table.Cell>
                                    <Table.Cell
                                        class="px-4 py-3 text-left align-middle"
                                    >
                                        <div
                                            class="flex flex-wrap items-center gap-1.5"
                                        >
                                            <Badge
                                                class="min-h-6 px-2 py-0.5 text-xs font-medium"
                                                variant="outline"
                                                >{user.office_label}</Badge
                                            >
                                            {#if user.office === 'program' && user.department_code}<Badge
                                                    class="min-h-6 px-2 py-0.5 text-xs font-medium"
                                                    variant="outline"
                                                    >{user.department_code}</Badge
                                                >{/if}
                                            {#if user.office === 'program'}{#each user.program_codes ?? [] as programCode (programCode)}<Badge
                                                        class="min-h-6 px-2 py-0.5 text-xs font-medium"
                                                        variant="secondary"
                                                        >{programCode}</Badge
                                                    >{/each}{/if}
                                        </div>
                                    </Table.Cell>
                                    <Table.Cell
                                        class="px-4 py-3 text-left align-middle whitespace-normal"
                                    >
                                        <div
                                            class="flex max-w-80 flex-wrap items-center gap-1.5"
                                        >
                                            {#each user.roles ?? [] as role (role.name)}<Badge
                                                    class="min-h-6 px-2 py-0.5 text-xs font-medium"
                                                    variant="secondary"
                                                    >{role.display_name}</Badge
                                                >{/each}
                                            {#if user.ta_status === 'inherent'}<Badge
                                                    class="min-h-6 px-2 py-0.5 text-xs font-medium"
                                                    variant="outline"
                                                    >Test Administrator</Badge
                                                >{:else if user.ta_status === 'delegated'}<Badge
                                                    class="min-h-6 px-2 py-0.5 text-xs font-medium"
                                                    variant="outline"
                                                    >Test Administrator ·
                                                    Delegated</Badge
                                                >{/if}
                                            {#if user.setup_status === 'delegated'}<Badge
                                                    class="min-h-6 px-2 py-0.5 text-xs font-medium"
                                                    variant="outline"
                                                    >{(user.setup_scopes ?? [])
                                                        .length
                                                        ? `Setup: ${(user.setup_scopes ?? []).map(scopeLabel).join(', ')}`
                                                        : 'Setup: Institutional'}</Badge
                                                >{/if}
                                        </div>
                                    </Table.Cell>
                                    <Table.Cell
                                        class="px-4 py-3 text-left align-middle whitespace-normal"
                                    >
                                        <div
                                            class="flex flex-wrap items-center gap-1.5"
                                        >
                                            <Badge
                                                class="min-h-6 px-2 py-0.5 text-xs font-medium"
                                                variant={user.status ===
                                                'active'
                                                    ? 'success'
                                                    : 'muted'}
                                                >{user.status === 'active'
                                                    ? 'Active'
                                                    : 'Inactive'}</Badge
                                            >
                                            {#if user.onboarding_state}
                                                <Badge
                                                    variant={user.onboarding_state ===
                                                    'Ready'
                                                        ? 'outline'
                                                        : 'warning'}
                                                    >{user.onboarding_state}</Badge
                                                >
                                            {/if}
                                            {#if user.mfa_status === 'enrolled'}<Badge
                                                    class="min-h-6 px-2 py-0.5 text-xs font-medium"
                                                    variant="success"
                                                    >2-Factor: Active</Badge
                                                >{:else if user.mfa_status === 'pending'}<Badge
                                                    class="min-h-6 px-2 py-0.5 text-xs font-medium"
                                                    variant="warning"
                                                    >2-Factor: Required</Badge
                                                >{:else if user.mfa_status === 'exempt'}<Badge
                                                    class="min-h-6 px-2 py-0.5 text-xs font-medium"
                                                    variant="muted"
                                                    >2-Factor: Off</Badge
                                                >{/if}
                                        </div>
                                    </Table.Cell>
                                    <Table.Cell
                                        class="w-px px-4 py-3 text-right align-middle whitespace-nowrap"
                                    >
                                        <div
                                            class="flex flex-nowrap items-center justify-end gap-1.5"
                                        >
                                            {#if primaryAction(user)}<Link
                                                    href={primaryAction(user)
                                                        .href}
                                                    ><Button
                                                        variant="outline"
                                                        size="sm"
                                                        class="min-h-[36px] px-2.5 text-xs font-semibold"
                                                        >{primaryAction(user)
                                                            .label}</Button
                                                    ></Link
                                                >{/if}
                                            {#if menuItems(user).length > 0}
                                                <DropdownMenu.Root>
                                                    <DropdownMenu.Trigger>
                                                        {#snippet child({
                                                            props,
                                                        })}
                                                            <Button
                                                                {...props}
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon"
                                                                class="text-muted-foreground hover:text-foreground size-9 min-h-[36px] min-w-[36px] p-0"
                                                                aria-label={tableActionLabel(
                                                                    user,
                                                                )}
                                                                ><Ellipsis
                                                                    class="size-4"
                                                                    aria-hidden="true"
                                                                /></Button
                                                            >
                                                        {/snippet}
                                                    </DropdownMenu.Trigger>
                                                    <DropdownMenu.Portal>
                                                        <DropdownMenu.Content
                                                            sideOffset={6}
                                                            align="end"
                                                            collisionPadding={8}
                                                            class="border-border bg-popover text-popover-foreground z-[100] max-h-[min(24rem,calc(100vh-1rem))] min-w-52 overflow-y-auto rounded-md border p-1 shadow-md"
                                                        >
                                                            {#each menuItems(user) as item (item.id)}
                                                                {#if item.separator}<DropdownMenu.Separator
                                                                        class="bg-border my-1 h-px"
                                                                    />{:else}
                                                                    <DropdownMenu.Item
                                                                        disabled={item.destructive
                                                                            ? busyPersonnel ===
                                                                              user.id
                                                                            : false}
                                                                        onSelect={() =>
                                                                            runAccessAction(
                                                                                item.id,
                                                                                user,
                                                                            )}
                                                                        class="focus:bg-accent focus:text-accent-foreground flex min-h-9 cursor-default items-center rounded-sm px-2.5 text-sm outline-none select-none data-[disabled]:pointer-events-none data-[disabled]:opacity-50 {item.destructive
                                                                            ? 'text-destructive focus:text-destructive'
                                                                            : ''}"
                                                                        >{item.label}</DropdownMenu.Item
                                                                    >
                                                                {/if}
                                                            {/each}
                                                        </DropdownMenu.Content>
                                                    </DropdownMenu.Portal>
                                                </DropdownMenu.Root>
                                            {/if}
                                            {#if !primaryAction(user) && menuItems(user).length === 0}
                                                <span
                                                    class="text-muted-foreground text-xs"
                                                    >Read only</span
                                                >
                                            {/if}
                                        </div>
                                    </Table.Cell>
                                </Table.Row>
                            {/each}
                        </Table.Body>
                    </Table.Root>
                {/snippet}
                {#snippet cards()}
                    <ul
                        class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3"
                        role="list"
                    >
                        {#each rows as user (user.id)}
                            <OperationalCard
                                id={`personnel-${user.id}`}
                                title={user.name}
                                subtitle={user.email}
                            >
                                {#snippet leading()}<AvatarBadge
                                        avatar={user.avatar}
                                        name={user.name}
                                        size="lg"
                                    />{/snippet}
                                {#snippet titleSuffix()}
                                    <div class="flex items-center gap-2">
                                        {#if can_manage_personnel && user.can_issue_voucher}
                                            <input
                                                type="checkbox"
                                                checked={selectedIds.includes(
                                                    user.id,
                                                )}
                                                onchange={() =>
                                                    toggleRowSelection(user.id)}
                                                aria-label={`Select ${user.name}`}
                                                class="border-input size-4 rounded"
                                            />
                                        {/if}
                                        {#if user.is_self}<Badge
                                                class="min-h-6 px-2 py-0.5 text-xs font-medium"
                                                variant="secondary">You</Badge
                                            >{/if}
                                    </div>
                                {/snippet}
                                {#snippet status()}
                                    <Badge
                                        class="min-h-6 shrink-0 px-2 py-0.5 text-xs font-medium"
                                        variant={user.status === 'active'
                                            ? 'success'
                                            : 'muted'}
                                        >{user.status === 'active'
                                            ? 'Active'
                                            : 'Inactive'}</Badge
                                    >
                                {/snippet}
                                {#snippet summary()}
                                    <div class="text-foreground space-y-2">
                                        <div
                                            class="flex flex-wrap items-center gap-1.5"
                                        >
                                            {#if user.onboarding_state}
                                                <Badge
                                                    variant={user.onboarding_state ===
                                                    'Ready'
                                                        ? 'outline'
                                                        : 'warning'}
                                                    class="min-h-5 px-1.5 py-0 text-[11px]"
                                                >
                                                    {user.onboarding_state}
                                                </Badge>
                                            {/if}
                                            {#if user.mfa_status === 'enrolled'}
                                                <Badge
                                                    variant="success"
                                                    class="min-h-5 px-1.5 py-0 text-[11px]"
                                                    >2FA: Active</Badge
                                                >
                                            {:else if user.mfa_status === 'pending'}
                                                <Badge
                                                    variant="warning"
                                                    class="min-h-5 px-1.5 py-0 text-[11px]"
                                                    >2FA: Required</Badge
                                                >
                                            {:else if user.mfa_status === 'exempt'}
                                                <Badge
                                                    variant="muted"
                                                    class="min-h-5 px-1.5 py-0 text-[11px]"
                                                    >2FA: Off</Badge
                                                >
                                            {/if}
                                            {#if user.active_cycle_code}
                                                <Badge
                                                    variant="outline"
                                                    class="min-h-5 px-1.5 py-0 font-mono text-[11px]"
                                                >
                                                    {user.active_cycle_code}
                                                </Badge>
                                            {/if}
                                        </div>
                                        <div>
                                            <p
                                                class="text-muted-foreground mb-1 text-xs font-medium"
                                            >
                                                Office / Jurisdiction
                                            </p>
                                            <div
                                                class="flex flex-wrap items-center gap-1.5"
                                            >
                                                <Badge
                                                    class="min-h-5 px-1.5 py-0 text-xs font-medium"
                                                    variant="outline"
                                                    >{user.office_label}</Badge
                                                >
                                                {#if user.office === 'program' && user.department_code}<Badge
                                                        class="min-h-5 px-1.5 py-0 text-xs font-medium"
                                                        variant="outline"
                                                        >{user.department_code}</Badge
                                                    >{/if}
                                                {#if user.office === 'program'}{#each user.program_codes ?? [] as programCode (programCode)}<Badge
                                                            class="min-h-5 px-1.5 py-0 text-xs font-medium"
                                                            variant="secondary"
                                                            >{programCode}</Badge
                                                        >{/each}{/if}
                                            </div>
                                        </div>
                                        <div>
                                            <p
                                                class="text-muted-foreground mb-1 text-xs font-medium"
                                            >
                                                Roles &amp; designations
                                            </p>
                                            <div
                                                class="flex flex-wrap items-center gap-1.5"
                                            >
                                                {#each user.roles ?? [] as role (role.name)}<Badge
                                                        class="min-h-6 px-2 py-0.5 text-xs font-medium"
                                                        variant="secondary"
                                                        >{role.display_name}</Badge
                                                    >{/each}
                                                {#if user.ta_status === 'inherent'}<Badge
                                                        class="min-h-6 px-2 py-0.5 text-xs font-medium"
                                                        variant="outline"
                                                        >Test Administrator</Badge
                                                    >{:else if user.ta_status === 'delegated'}<Badge
                                                        class="min-h-6 px-2 py-0.5 text-xs font-medium"
                                                        variant="outline"
                                                        >Test Administrator ·
                                                        Delegated</Badge
                                                    >{/if}
                                                {#if user.setup_status === 'delegated'}<Badge
                                                        class="min-h-6 px-2 py-0.5 text-xs font-medium"
                                                        variant="outline"
                                                        >{(
                                                            user.setup_scopes ??
                                                            []
                                                        ).length
                                                            ? `Setup: ${(user.setup_scopes ?? []).map(scopeLabel).join(', ')}`
                                                            : 'Setup: Institutional'}</Badge
                                                    >{/if}
                                            </div>
                                        </div>
                                    </div>
                                {/snippet}
                                {#snippet actions()}
                                    <div
                                        class="flex w-full items-center justify-end gap-2 pt-1"
                                    >
                                        {#if primaryAction(user)}
                                            <Link
                                                href={primaryAction(user).href}
                                                class="min-w-0 flex-1"
                                            >
                                                <Button
                                                    variant="outline"
                                                    class="h-11 min-h-[44px] w-full text-xs font-semibold"
                                                    >{primaryAction(user)
                                                        .label}</Button
                                                >
                                            </Link>
                                        {/if}
                                        {#if menuItems(user).length > 0}
                                            <DropdownMenu.Root>
                                                <DropdownMenu.Trigger>
                                                    {#snippet child({ props })}
                                                        <Button
                                                            {...props}
                                                            type="button"
                                                            variant="ghost"
                                                            class="text-muted-foreground hover:text-foreground border-input/60 hover:bg-muted size-11 min-h-[44px] min-w-[44px] shrink-0 rounded-lg border p-0"
                                                            aria-label={tableActionLabel(
                                                                user,
                                                            )}
                                                            ><Ellipsis
                                                                class="size-4"
                                                                aria-hidden="true"
                                                            /></Button
                                                        >
                                                    {/snippet}
                                                </DropdownMenu.Trigger>
                                                <DropdownMenu.Portal>
                                                    <DropdownMenu.Content
                                                        sideOffset={6}
                                                        align="end"
                                                        collisionPadding={8}
                                                        class="border-border bg-popover text-popover-foreground z-[100] max-h-[min(24rem,calc(100vh-1rem))] min-w-52 overflow-y-auto rounded-md border p-1 shadow-md"
                                                    >
                                                        {#each menuItems(user) as item (item.id)}
                                                            {#if item.separator}<DropdownMenu.Separator
                                                                    class="bg-border my-1 h-px"
                                                                />{:else}
                                                                <DropdownMenu.Item
                                                                    disabled={item.destructive
                                                                        ? busyPersonnel ===
                                                                          user.id
                                                                        : false}
                                                                    onSelect={() =>
                                                                        runAccessAction(
                                                                            item.id,
                                                                            user,
                                                                        )}
                                                                    class="focus:bg-accent focus:text-accent-foreground flex min-h-9 cursor-default items-center rounded-sm px-2.5 text-sm outline-none select-none data-[disabled]:pointer-events-none data-[disabled]:opacity-50 {item.destructive
                                                                        ? 'text-destructive focus:text-destructive'
                                                                        : ''}"
                                                                    >{item.label}</DropdownMenu.Item
                                                                >
                                                            {/if}
                                                        {/each}
                                                    </DropdownMenu.Content>
                                                </DropdownMenu.Portal>
                                            </DropdownMenu.Root>
                                        {/if}
                                        {#if !primaryAction(user) && menuItems(user).length === 0}
                                            <span
                                                class="text-muted-foreground py-2 text-xs"
                                                >Read only</span
                                            >
                                        {/if}
                                    </div>
                                {/snippet}
                            </OperationalCard>
                        {/each}
                    </ul>
                {/snippet}
            </SwitchableListView>
        {/if}

        {#if can_manage_personnel && selectedIds.length > 0}
            <BulkActionBar
                count={selectedIds.length}
                hint={`${batchTargets.length} eligible for voucher issuance/reissue`}
                onClear={clearSelection}
            >
                {#snippet actions()}
                    <div class="flex flex-wrap items-center gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="gap-1.5"
                            onclick={() =>
                                openCycleRollover(selectedIds, 'retain')}
                        >
                            <Check class="size-3.5 text-emerald-600" />
                            Retain for Cycle
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="text-destructive hover:bg-destructive/10 gap-1.5"
                            onclick={() =>
                                openCycleRollover(selectedIds, 'archive')}
                        >
                            <Archive class="size-3.5" />
                            Archive Selected
                        </Button>
                        <BatchVouchers
                            targets={batchTargets}
                            canIssueNonExpiring={can_full_hr}
                        />
                    </div>
                {/snippet}
            </BulkActionBar>
        {/if}

        <SimplePagination data={users} />

        <AlertDialog.Root
            open={setupDialogOpen}
            onOpenChange={(open) => {
                if (!open && busySetup === setupTarget?.id) return;
                setupDialogOpen = open;
                if (!open) {
                    setupTarget = null;
                    setupAction = null;
                    setupReason = '';
                    selectedScopes = [];
                }
            }}
        >
            <AlertDialog.Portal>
                <AlertDialog.Overlay class="fixed inset-0 z-50 bg-black/50" />
                <AlertDialog.Content
                    class="border-border bg-background fixed top-1/2 left-1/2 z-50 max-h-[90vh] w-[min(32rem,calc(100vw-2rem))] -translate-x-1/2 -translate-y-1/2 overflow-y-auto rounded-xl border p-6 shadow-xl"
                >
                    <AlertDialog.Title class="text-lg font-semibold"
                        >{setupAction === 'revoke'
                            ? 'Revoke setup delegation'
                            : setupTarget?.setup_status === 'delegated'
                              ? 'Edit setup access'
                              : 'Delegate institutional setup'}</AlertDialog.Title
                    >
                    <AlertDialog.Description
                        class="text-muted-foreground mt-2 text-sm"
                        >{setupAction === 'revoke'
                            ? `Revoke institutional setup authority from ${setupTarget?.name}.`
                            : `Choose setup responsibilities for ${setupTarget?.name}.`}</AlertDialog.Description
                    >
                    <form onsubmit={submitSetupAction} class="mt-5 space-y-4">
                        {#if setupAction !== 'revoke'}
                            <fieldset
                                class="border-border space-y-3 rounded-lg border p-4"
                            >
                                <legend class="px-1 text-sm font-semibold"
                                    >Setup responsibilities</legend
                                >
                                <p class="text-muted-foreground text-xs">
                                    Select the setup areas this staff member
                                    will manage.
                                </p>
                                {#each Object.entries(setup_scope_options) as [scope, label] (scope)}
                                    <label
                                        class="flex cursor-pointer items-start gap-3 text-sm"
                                        ><input
                                            type="checkbox"
                                            checked={selectedScopes.includes(
                                                scope,
                                            )}
                                            onchange={() =>
                                                toggleSetupScope(scope)}
                                            class="accent-primary mt-0.5 size-4"
                                        /><span>{label}</span></label
                                    >
                                {/each}
                                {#if errors.scopes || Object.keys(errors).some( (key) => key.startsWith('scopes.') )}<p
                                        class="text-destructive text-sm"
                                        role="alert"
                                    >
                                        {errors.scopes ??
                                            'Select valid setup responsibilities.'}
                                    </p>{/if}
                            </fieldset>
                        {/if}
                        <div class="space-y-2">
                            <label
                                for="setup-delegation-reason"
                                class="text-sm font-medium"
                                >Reason <span aria-hidden="true">*</span></label
                            >
                            <textarea
                                id="setup-delegation-reason"
                                bind:value={setupReason}
                                required
                                minlength="3"
                                maxlength="500"
                                rows="4"
                                class="border-input bg-background w-full rounded-md border px-3 py-2 text-sm"
                            ></textarea>
                            {#if errors.reason}<p
                                    class="text-destructive text-sm"
                                    role="alert"
                                >
                                    {errors.reason}
                                </p>{/if}
                            <p class="text-muted-foreground text-xs">
                                Enter 3–500 characters for the audit record.
                            </p>
                        </div>
                        <div class="flex justify-end gap-2">
                            <AlertDialog.Cancel>
                                {#snippet child({ props })}<Button
                                        {...props}
                                        type="button"
                                        variant="outline"
                                        disabled={busySetup === setupTarget?.id}
                                        onclick={closeSetupDialog}
                                        >Cancel</Button
                                    >{/snippet}
                            </AlertDialog.Cancel>
                            <Button
                                type="submit"
                                disabled={busySetup === setupTarget?.id ||
                                    setupReason.trim().length < 3 ||
                                    setupReason.trim().length > 500 ||
                                    (setupAction !== 'revoke' &&
                                        selectedScopes.length === 0)}
                                >{busySetup === setupTarget?.id
                                    ? 'Saving…'
                                    : setupAction === 'revoke'
                                      ? 'Revoke Setup'
                                      : 'Confirm'}</Button
                            >
                        </div>
                    </form>
                </AlertDialog.Content>
            </AlertDialog.Portal>
        </AlertDialog.Root>

        <AlertDialog.Root
            open={deactivateDialogOpen}
            onOpenChange={(open) => {
                deactivateDialogOpen = open;
                if (!open) deactivateTarget = null;
            }}
        >
            <AlertDialog.Portal>
                <AlertDialog.Overlay class="fixed inset-0 z-50 bg-black/50" />
                <AlertDialog.Content
                    class="border-border bg-background fixed top-1/2 left-1/2 z-50 w-[min(28rem,calc(100vw-2rem))] -translate-x-1/2 -translate-y-1/2 rounded-xl border p-6 shadow-xl"
                >
                    <AlertDialog.Title class="text-lg font-semibold"
                        >Deactivate staff account?</AlertDialog.Title
                    >
                    <AlertDialog.Description
                        class="text-muted-foreground mt-2 text-sm"
                        >{deactivateTarget?.name} will no longer be able to sign in.
                        The account and its staff history will remain in the directory
                        as inactive.</AlertDialog.Description
                    >
                    <div class="mt-6 flex justify-end gap-2">
                        <AlertDialog.Cancel
                            >{#snippet child({ props })}<Button
                                    {...props}
                                    type="button"
                                    variant="outline">Cancel</Button
                                >{/snippet}</AlertDialog.Cancel
                        ><Button
                            variant="destructive"
                            onclick={confirmDeactivate}
                            >Deactivate account</Button
                        >
                    </div>
                </AlertDialog.Content>
            </AlertDialog.Portal>
        </AlertDialog.Root>

        <VoucherDialog
            open={voucherDialogOpen}
            user={voucherTarget}
            initialPurpose={voucherPurpose}
            canIssueNonExpiring={can_full_hr}
            onOpenChange={(value) => (voucherDialogOpen = value)}
        />

        <RolloverModal
            bind:open={rolloverModalOpen}
            {cycles}
            personnel={personnelRows}
            programs={normalizedPrograms}
            initialTargetIds={rolloverTargetIds}
            initialDisposition={rolloverInitialDisposition}
            onSuccess={onRolloverSuccess}
        />

        <FastOnboardingModal
            bind:open={fastOnboardingModalOpen}
            {cycles}
            programs={normalizedPrograms}
            roleOptions={roles}
            {can_full_hr}
            {actor_office}
            onSuccess={onOnboardingSuccess}
        />
    </div>
</StaffLayout>
