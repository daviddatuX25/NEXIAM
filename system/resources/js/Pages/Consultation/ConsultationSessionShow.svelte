<script>
    /**
     * Consultation session Show — legacy list -> create/edit shape:
     * details, assigned roster, ready pool assign panel, booking toggle, publish.
     */
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
    import PrintDocumentsSlot from '@/Components/DocumentTemplate/PrintDocumentsSlot.svelte';
    import { Checkbox } from '@/Components/ui/checkbox';
    import { Badge } from '@/Components/ui/badge';
    import { Button } from '@/Components/ui/button';
    import * as Dialog from '@/Components/ui/dialog';
    import { Input } from '@/Components/ui/input';
    import * as Table from '@/Components/ui/table';
    import { Textarea } from '@/Components/ui/textarea';
    import { Link, router } from '@inertiajs/svelte';
    import {
        ArrowRightLeft,
        Ban,
        CalendarCheck,
        CheckCircle,
        ExternalLink,
        Lock,
        LockOpen,
        Pencil,
        Search,
        Send,
        Undo2,
        UserMinus,
        UserPlus,
    } from 'lucide-svelte';
    import { applySearchFocus } from '@/lib/search-focus';
    import ApplicantActionsMenu from '@/Components/Admission/ApplicantActionsMenu.svelte';
    import { tick } from 'svelte';
    let {
        session,
        assigned_applicants = [],
        ready_pool = [],
        available_applicants = [],
        reassign_targets = [],
        publish_block_reason = null,
        is_publishable = false,
        is_completable = false,
        can_decide = false,
        can_plan = false,
        errors = {},
        focus_assignment_id = null,
    } = $props();
    const focusedApplicant = $derived(assigned_applicants.find((row) => String(row.id) === String(focus_assignment_id)) ?? null);

    let selectedReady = $state([]);
    let searchReady = $state('');
    let searchAssigned = $state('');
    let reasonOpen = $state(false);
    let reasonMode = $state('unpublish'); // unpublish | cancel
    let reason = $state('');
    let selectedAssigned = $state([]);
    let reassignOpen = $state(false);
    let reassignTargetId = $state('');
    let reassignReason = $state('');
    let reassigning = $state(false);

    let assignedPage = $state(1);
    const assignedPerPage = 10;
    const totalAssignedPages = $derived(
        Math.max(1, Math.ceil(filteredAssigned.length / assignedPerPage)),
    );
    const paginatedAssigned = $derived(
        filteredAssigned.slice(
            (assignedPage - 1) * assignedPerPage,
            assignedPage * assignedPerPage,
        ),
    );

    let readyPage = $state(1);
    const readyPerPage = 10;
    const totalReadyPages = $derived(
        Math.max(1, Math.ceil(filteredReady.length / readyPerPage)),
    );
    const paginatedReady = $derived(
        filteredReady.slice(
            (readyPage - 1) * readyPerPage,
            readyPage * readyPerPage,
        ),
    );

    $effect(() => {
        if (focus_assignment_id) {
            const index = filteredAssigned.findIndex(
                (r) => String(r.id) === String(focus_assignment_id),
            );
            if (index >= 0) {
                assignedPage = Math.floor(index / assignedPerPage) + 1;
            }
            void tick().then(() => {
                applySearchFocus(`consultation-assignment-${focus_assignment_id}`);
            });
        }
    });
    const readyList = $derived(
        ready_pool.length > 0 ? ready_pool : available_applicants,
    );

    const filteredReady = $derived(
        readyList.filter((app) => {
            const term = searchReady.toLowerCase();
            return (
                (app.name && app.name.toLowerCase().includes(term)) ||
                (app.reference_number &&
                    app.reference_number.toLowerCase().includes(term)) ||
                (app.email && app.email.toLowerCase().includes(term))
            );
        }),
    );

    const filteredAssigned = $derived(
        assigned_applicants.filter((app) => {
            const term = searchAssigned.toLowerCase();
            return (
                (app.name && app.name.toLowerCase().includes(term)) ||
                (app.reference_number &&
                    app.reference_number.toLowerCase().includes(term))
            );
        }),
    );

    const isAllAssignedSelected = $derived(
        filteredAssigned.length > 0 &&
            filteredAssigned.every((r) => selectedAssigned.includes(r.id)),
    );
    const isSomeAssignedSelected = $derived(
        filteredAssigned.some((r) => selectedAssigned.includes(r.id)) &&
            !isAllAssignedSelected,
    );
    const isAllReadySelected = $derived(
        filteredReady.length > 0 &&
            filteredReady.every((app) => selectedReady.includes(app.id)),
    );
    const isSomeReadySelected = $derived(
        filteredReady.some((app) => selectedReady.includes(app.id)) &&
            !isAllReadySelected,
    );

    function toggleAllAssigned(checked) {
        if (checked) {
            const newIds = filteredAssigned
                .map((r) => r.id)
                .filter((id) => !selectedAssigned.includes(id));
            selectedAssigned = [...selectedAssigned, ...newIds];
        } else {
            const visibleIds = filteredAssigned.map((r) => r.id);
            selectedAssigned = selectedAssigned.filter(
                (id) => !visibleIds.includes(id),
            );
        }
    }

    function toggleAllReady(checked) {
        if (checked) {
            const newIds = filteredReady
                .map((a) => a.id)
                .filter((id) => !selectedReady.includes(id));
            selectedReady = [...selectedReady, ...newIds];
        } else {
            const visibleIds = filteredReady.map((a) => a.id);
            selectedReady = selectedReady.filter(
                (id) => !visibleIds.includes(id),
            );
        }
    }

    function toggleReady(id) {
        selectedReady = selectedReady.includes(id)
            ? selectedReady.filter((x) => x !== id)
            : [...selectedReady, id];
    }

    function assignSingle(attemptId) {
        router.post(
            `/consultation/sessions/${session.id}/assign`,
            { admission_attempt_id: attemptId },
            { preserveScroll: true },
        );
    }

    function assignSelected() {
        if (selectedReady.length === 0) return;
        const ids = [...selectedReady];
        router.post(
            `/consultation/sessions/${session.id}/bulk-assign`,
            { admission_attempt_ids: ids },
            {
                preserveScroll: true,
                onSuccess: () => {
                    selectedReady = [];
                },
            },
        );
    }

    function cancelAssignment(assignmentId) {
        router.post(
            `/consultation/sessions/${session.id}/assignments/${assignmentId}/cancel`,
            { reason: 'Cancelled from session show' },
            { preserveScroll: true },
        );
    }

    function toggleAssigned(id) {
        selectedAssigned = selectedAssigned.includes(id)
            ? selectedAssigned.filter((x) => x !== id)
            : [...selectedAssigned, id];
    }

    function openReassign(ids) {
        selectedAssigned = [...ids];
        reassignTargetId = '';
        reassignReason = '';
        reassignOpen = true;
    }

    function submitReassign() {
        if (!reassignTargetId || selectedAssigned.length === 0) return;
        reassigning = true;
        router.post(
            `/consultation/sessions/${session.id}/reassign`,
            {
                assignment_ids: selectedAssigned,
                target_session_id: Number(reassignTargetId),
                reason: reassignReason || 'Reassigned from session show',
            },
            {
                preserveScroll: true,
                onFinish: () => {
                    reassigning = false;
                    reassignOpen = false;
                    selectedAssigned = [];
                },
            },
        );
    }

    const openReassignTargets = $derived(
        reassign_targets.filter(
            (t) => !t.full && (t.remaining === undefined || t.remaining > 0),
        ),
    );

    function publish() {
        router.post(`/consultation/sessions/${session.id}/publish`, {
            expected_version: session.version,
        });
    }

    function complete() {
        router.post(`/consultation/sessions/${session.id}/complete`, {
            expected_version: session.version,
        });
    }

    function openReservation() {
        router.post(`/consultation/sessions/${session.id}/open-reservation`, {
            expected_version: session.version,
        });
    }

    function closeReservation() {
        router.post(`/consultation/sessions/${session.id}/close-reservation`, {
            expected_version: session.version,
        });
    }

    function openReason(mode) {
        reasonMode = mode;
        reason = '';
        reasonOpen = true;
    }

    function submitReason() {
        if (!reason.trim()) return;
        const path =
            reasonMode === 'unpublish'
                ? `/consultation/sessions/${session.id}/unpublish`
                : `/consultation/sessions/${session.id}/cancel`;
        router.post(
            path,
            {
                expected_version: session.version,
                reason: reason.trim(),
            },
            {
                onFinish: () => {
                    reasonOpen = false;
                },
            },
        );
    }

    function statusVariant(status) {
        if (status === 'draft') return 'secondary';
        if (status === 'published') return 'default';
        if (status === 'in_progress') return 'outline';
        if (status === 'completed') return 'outline';
        if (status === 'cancelled') return 'destructive';
        return 'outline';
    }

    function statusLabel(status) {
        if (status === 'draft') return 'Draft';
        if (status === 'published') return 'Published';
        if (status === 'in_progress') return 'In progress';
        if (status === 'completed') return 'Completed';
        if (status === 'cancelled') return 'Cancelled';
        return status ?? '—';
    }

    function bookingModeLabel(mode) {
        if (mode === 'open_for_reservation')
            return 'Self-booking (students reserve)';
        if (mode === 'staff_only') return 'Staff-only (office assigns)';
        return mode ?? '—';
    }

    function sourceLabel(source) {
        if (source === 'applicant_reserved') return 'Applicant reserved';
        if (source === 'staff_assigned') return 'Office assigned';
        return source ?? '—';
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
            // fallback
        }
        return scheduledAt;
    }

    const assignedCount = $derived(
        session.assigned_count ?? assigned_applicants.length,
    );
    const roomCapacity = $derived(session.room?.capacity ?? null);
    const capacityPercent = $derived(
        roomCapacity && roomCapacity > 0
            ? Math.min(100, Math.round((assignedCount / roomCapacity) * 100))
            : 0,
    );

    const actionError = $derived(
        errors?.assign ||
            errors?.admission_attempt_id ||
            errors?.consultation_session_id ||
            errors?.reassign ||
            errors?.cancel_assignment ||
            errors?.remove ||
            errors?.publish ||
            errors?.complete ||
            errors?.cancel ||
            errors?.open_reservation ||
            errors?.close_reservation ||
            errors?.unpublish ||
            null,
    );

    const breadcrumbs = [
        { label: 'Release Day', href: '/consultation' },
        { label: session?.code ?? 'Release Day session' },
    ];
</script>

<svelte:head><title>{session?.code ?? 'Release Day session'}</title></svelte:head>
<StaffLayout {breadcrumbs}>
    <div class="min-w-0 space-y-6 p-4 sm:p-6">
            <PageHeaderBar
                title={session?.code ?? 'Release Day session'}
                description="View Release Day session details, assign applicants, and publish the schedule."
            >
            {#snippet actions()}
                <div class="flex flex-wrap gap-2">
                    {#if focusedApplicant?.admission_attempt_id}
                        <ApplicantActionsMenu attemptId={focusedApplicant.admission_attempt_id} context={{ consultation_session_id: session.id }} />
                    {/if}
                    <PrintDocumentsSlot slotKey="consultation_session_show" sessionId={session?.id ?? null} sessionKind="consultation" label="Session roster" />
                    <PrintDocumentsSlot slotKey="guidance_applicant_support" attemptIds={(assigned_applicants ?? []).map((row) => row.admission_attempt_id ?? row.id).filter(Boolean)} label="Applicant documents" />
                    {#if can_decide}
                        <Link href="/consultation">
                            <Button variant="outline" class="min-h-10 gap-2">
                                <CalendarCheck class="size-4" />
                                Academic Advising
                            </Button>
                        </Link>
                    {/if}

                    {#if session.status === 'draft'}
                        <Link
                            href={`/consultation/sessions/${session.id}/edit`}
                        >
                            <Button variant="outline" class="min-h-10 gap-2">
                                <Pencil class="size-4" />
                                Edit session
                            </Button>
                        </Link>
                        <Button
                            class="min-h-10 gap-2"
                            disabled={!is_publishable}
                            title={publish_block_reason || 'Publish session'}
                            onclick={publish}
                        >
                            <Send class="size-4" />
                            Publish
                        </Button>
                        <Button
                            variant="outline"
                            class="text-destructive min-h-10 gap-2"
                            onclick={() => openReason('cancel')}
                        >
                            <Ban class="size-4" />
                            Cancel session
                        </Button>
                    {:else if session.status === 'published'}
                        <Button
                            variant="outline"
                            class="min-h-10 gap-2"
                            onclick={() => openReason('unpublish')}
                        >
                            <Undo2 class="size-4" />
                            Unpublish
                        </Button>
                        <Button
                            variant="outline"
                            class="text-destructive min-h-10 gap-2"
                            onclick={() => openReason('cancel')}
                        >
                            <Ban class="size-4" />
                            Cancel session
                        </Button>
                    {/if}

                    {#if session.status === 'published' || session.status === 'in_progress' || is_completable}
                        <Button
                            variant="outline"
                            class="min-h-10 gap-2"
                            onclick={complete}
                        >
                            <CheckCircle class="size-4" />
                            Complete session
                        </Button>
                    {/if}

                    {#if can_plan && session.status !== 'completed' && session.status !== 'cancelled'}
                        {#if session.booking_mode === 'open_for_reservation'}
                            <Button
                                variant="outline"
                                class="min-h-10 gap-2"
                                onclick={closeReservation}
                            >
                                <Lock class="size-4" />
                                Close reservation
                            </Button>
                        {:else if session.status === 'draft'}
                            <Button
                                variant="outline"
                                class="min-h-10 gap-2"
                                onclick={openReservation}
                            >
                                <LockOpen class="size-4" />
                                Open reservation
                            </Button>
                        {/if}
                    {/if}
                </div>
            {/snippet}
            </PageHeaderBar>


        {#if publish_block_reason && session.status === 'draft'}
            <div
                class="rounded-md border border-amber-500/40 bg-amber-500/10 px-4 py-3 text-sm"
            >
                Cannot publish yet: {publish_block_reason}
            </div>
        {/if}

        {#if actionError}
            <div
                class="border-destructive/40 bg-destructive/10 text-destructive rounded-md border px-4 py-3 text-sm"
            >
                {actionError}
            </div>
        {/if}

        <div class="border-border bg-card rounded-xl border p-6">
            <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <dt class="text-muted-foreground text-sm">When</dt>
                    <dd class="font-medium">
                        {formatScheduledAt(session.scheduled_at)}
                    </dd>
                </div>
                <div>
                    <dt class="text-muted-foreground text-sm">Room</dt>
                    <dd class="font-medium">
                        {session.room?.name ?? session.room?.code ?? '—'} (cap. {session
                            .room?.capacity ?? '—'})
                    </dd>
                </div>
                <div>
                    <dt class="text-muted-foreground text-sm">Academic year</dt>
                    <dd class="font-medium">
                        {session.academic_cycle?.name ??
                            (session.academic_cycle?.school_year
                                ? `${session.academic_cycle.school_year} ${session.academic_cycle.semester ?? ''}`
                                : '—')}
                    </dd>
                </div>
                <div>
                    <dt class="text-muted-foreground text-sm">Status</dt>
                    <dd>
                        <Badge variant={statusVariant(session.status)}
                            >{statusLabel(session.status)}</Badge
                        >
                    </dd>
                </div>
                {#if can_plan || session.booking_mode === 'open_for_reservation'}
                    <div>
                        <dt class="text-muted-foreground text-sm">Booking mode</dt>
                        <dd>
                            <Badge
                                variant={session.booking_mode ===
                                'open_for_reservation'
                                    ? 'default'
                                    : 'outline'}
                            >
                                {bookingModeLabel(session.booking_mode)}
                            </Badge>
                        </dd>
                    </div>
                {/if}
                <div>
                    <dt class="text-muted-foreground text-sm">
                        Capacity usage
                    </dt>
                    <dd class="font-medium">
                        {assignedCount}{#if roomCapacity}
                            / {roomCapacity} ({capacityPercent}%){/if}
                        {#if roomCapacity}
                            <div
                                class="bg-muted mt-1.5 h-2 w-full max-w-[180px] overflow-hidden rounded-full"
                            >
                                <div
                                    class="bg-primary h-full transition-all duration-300"
                                    style="width: {capacityPercent}%"
                                ></div>
                            </div>
                        {/if}
                    </dd>
                </div>
            </dl>
        </div>

        <div class="border-border bg-card rounded-xl border p-6">
            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
            >
                <div>
                    <h2 class="text-lg font-semibold tracking-tight">
                        Assigned applicants
                    </h2>
                    <p class="text-muted-foreground mt-1 text-sm">
                        Cancel frees the seat. Reassign moves them to another
                        session in one step.
                    </p>
                </div>
                {#if session.status !== 'completed' && session.status !== 'cancelled'}
                    <div class="flex flex-wrap items-center gap-2">
                        {#if selectedAssigned.length > 0}
                            <span class="text-xs text-muted-foreground">Selected {selectedAssigned.length} applicant(s)</span>
                            <Button
                                variant="ghost"
                                size="sm"
                                class="h-8 px-2 text-xs text-destructive hover:bg-destructive/10"
                                onclick={() => (selectedAssigned = [])}
                            >
                                Clear selection
                            </Button>
                        {/if}
                        <Button
                            variant="outline"
                            class="min-h-10 gap-2 font-mono text-xs"
                            disabled={selectedAssigned.length === 0 ||
                                openReassignTargets.length === 0}
                            onclick={() => openReassign(selectedAssigned)}
                            title={openReassignTargets.length === 0
                                ? 'No other sessions with capacity'
                                : 'Reassign selected'}
                        >
                            <ArrowRightLeft class="size-4" />
                            Reassign selected ({selectedAssigned.length})
                        </Button>
                    </div>
                {/if}
            </div>

            {#if assigned_applicants.length > 0}
                <div class="relative mt-4 max-w-sm">
                    <Search
                        class="text-muted-foreground pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2"
                    />
                    <Input
                        type="search"
                        placeholder="Search assigned..."
                        class="h-9 pl-8"
                        bind:value={searchAssigned}
                        oninput={() => (assignedPage = 1)}
                    />
                </div>
                <div class="mt-4 overflow-x-auto">
                    <Table.Root>
                        <Table.Header class="bg-muted/50">
                            <Table.Row>
                                <Table.Head class="w-12">
                                    {#if session.status !== 'completed' && session.status !== 'cancelled' && filteredAssigned.length > 0}
                                        <Checkbox
                                            checked={isAllAssignedSelected}
                                            indeterminate={isSomeAssignedSelected}
                                            onCheckedChange={(checked) =>
                                                toggleAllAssigned(Boolean(checked))}
                                            aria-label="Select all assigned applicants in this view"
                                        />
                                    {/if}
                                </Table.Head>
                                <Table.Head>Reference</Table.Head>
                                <Table.Head>Name</Table.Head>
                                <Table.Head>Source</Table.Head>
                                <Table.Head>Status</Table.Head>
                                <Table.Head class="text-center"
                                    >Actions</Table.Head
                                >
                            </Table.Row>
                        </Table.Header>
                        <Table.Body>
                            {#each paginatedAssigned as row (row.id)}
                                <Table.Row id={`consultation-assignment-${row.id}`}>
                                    <Table.Cell>
                                        {#if session.status !== 'completed' && session.status !== 'cancelled'}
                                            <input
                                                type="checkbox"
                                                class="accent-primary size-4"
                                                checked={selectedAssigned.includes(
                                                    row.id,
                                                )}
                                                onchange={() =>
                                                    toggleAssigned(row.id)}
                                                onclick={(e) =>
                                                    e.stopPropagation()}
                                                aria-label={`Select ${row.name}`}
                                            />
                                        {/if}
                                    </Table.Cell>
                                    <Table.Cell class="font-medium"
                                        >{row.reference_number}</Table.Cell
                                    >
                                    <Table.Cell>{row.name}</Table.Cell>
                                    <Table.Cell>
                                        <Badge
                                            variant={row.source ===
                                            'applicant_reserved'
                                                ? 'default'
                                                : 'secondary'}
                                        >
                                            {sourceLabel(row.source)}
                                        </Badge>
                                    </Table.Cell>
                                    <Table.Cell class="text-muted-foreground">
                                        <div>{row.status ?? 'assigned'}</div>
                                        {#if row.scoring_blocker}
                                            <p role="status" class="mt-1 max-w-xs text-xs text-amber-700 dark:text-amber-300">
                                                Scoring incomplete: {row.scoring_blocker}
                                            </p>
                                        {/if}
                                    </Table.Cell>
                                    <Table.Cell class="text-center">
                                        <div class="flex flex-wrap items-center justify-center gap-1">
                                            {#if row.admission_attempt_id || row.attempt_id || row.attempt?.id}
                                                <Link href={`/consultation/applicants/${row.admission_attempt_id || row.attempt_id || row.attempt?.id}?session_id=${session.id}`}>
                                                    <Button variant="ghost" size="sm" class="h-8 px-2 text-xs">
                                                        <ExternalLink class="mr-1.5 size-3.5" />
                                                        Advisory Workspace
                                                    </Button>
                                                </Link>
                                            {/if}
                                            {#if session.status !== 'completed' && session.status !== 'cancelled'}
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    class="h-8 px-2 text-xs"
                                                    disabled={openReassignTargets.length === 0}
                                                    onclick={() => openReassign([row.id])}
                                                >
                                                    <ArrowRightLeft class="mr-1.5 size-3.5" />
                                                    Reassign
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    class="text-destructive h-8 px-2 text-xs"
                                                    onclick={() => cancelAssignment(row.id)}
                                                >
                                                    <UserMinus class="mr-1.5 size-3.5" />
                                                    Cancel
                                                </Button>
                                            {/if}
                                        </div>
                                    </Table.Cell>
                                </Table.Row>
                            {/each}
                        </Table.Body>
                    </Table.Root>
                </div>
                {#if totalAssignedPages > 1}
                    <div class="mt-4 flex items-center justify-between border-t border-border/40 pt-3">
                        <span class="text-xs text-muted-foreground">
                            Showing {(assignedPage - 1) * assignedPerPage + 1} to {Math.min(assignedPage * assignedPerPage, filteredAssigned.length)} of {filteredAssigned.length}
                        </span>
                        <div class="flex items-center gap-1">
                            <Button
                                variant="outline"
                                size="sm"
                                class="h-8 px-2.5 text-xs"
                                disabled={assignedPage <= 1}
                                onclick={() => (assignedPage = Math.max(1, assignedPage - 1))}
                            >
                                Previous
                            </Button>
                            <span class="px-2 font-mono text-xs text-muted-foreground">
                                {assignedPage} / {totalAssignedPages}
                            </span>
                            <Button
                                variant="outline"
                                size="sm"
                                class="h-8 px-2.5 text-xs"
                                disabled={assignedPage >= totalAssignedPages}
                                onclick={() => (assignedPage = Math.min(totalAssignedPages, assignedPage + 1))}
                            >
                                Next
                            </Button>
                        </div>
                    </div>
                {/if}
            {:else}
                <p class="text-muted-foreground mt-4 text-sm">
                    No applicants assigned yet.
                </p>
            {/if}
        </div>

        {#if session.status !== 'completed' && session.status !== 'cancelled'}
            <div class="border-border bg-card rounded-xl border p-6">
                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <h2 class="text-lg font-semibold tracking-tight">
                            Ready pool (available applicants)
                        </h2>
                        <p class="text-muted-foreground mt-1 text-sm">
                            Accepted applicants with finalized results and
                            prepared documents, without an active Release Day
                            appointment assignment.
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        {#if selectedReady.length > 0}
                            <span class="text-xs text-muted-foreground">Selected {selectedReady.length} applicant(s)</span>
                            <Button
                                variant="ghost"
                                size="sm"
                                class="h-8 px-2 text-xs text-destructive hover:bg-destructive/10"
                                onclick={() => (selectedReady = [])}
                            >
                                Clear selection
                            </Button>
                        {/if}
                        <Button
                            class="min-h-10 gap-2 font-mono text-xs"
                            disabled={selectedReady.length === 0}
                            onclick={assignSelected}
                        >
                            <UserPlus class="size-4" />
                            Assign selected ({selectedReady.length})
                        </Button>
                    </div>
                </div>

                {#if readyList.length > 0}
                    <div class="relative mt-4 max-w-sm">
                        <Search
                            class="text-muted-foreground pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2"
                        />
                        <Input
                            type="search"
                            placeholder="Search ready pool..."
                            class="h-9 pl-8"
                            bind:value={searchReady}
                            oninput={() => (readyPage = 1)}
                        />
                    </div>
                    <div class="mt-4 overflow-x-auto">
                        <Table.Root>
                            <Table.Header class="bg-muted/50">
                                <Table.Row>
                                    <Table.Head class="w-12">
                                        {#if filteredReady.length > 0}
                                            <Checkbox
                                                checked={isAllReadySelected}
                                                indeterminate={isSomeReadySelected}
                                                onCheckedChange={(checked) =>
                                                    toggleAllReady(Boolean(checked))}
                                                aria-label="Select all ready applicants in this view"
                                            />
                                        {/if}
                                    </Table.Head>
                                    <Table.Head>Reference</Table.Head>
                                    <Table.Head>Name</Table.Head>
                                    <Table.Head>Email</Table.Head>
                                    <Table.Head class="text-center"
                                        >Action</Table.Head
                                    >
                                </Table.Row>
                            </Table.Header>
                            <Table.Body>
                                {#each paginatedReady as app (app.id)}
                                    <Table.Row id={`ready-applicant-${app.id}`}>
                                        <Table.Cell>
                                            <input
                                                type="checkbox"
                                                class="accent-primary size-4"
                                                checked={selectedReady.includes(
                                                    app.id,
                                                )}
                                                onchange={() =>
                                                    toggleReady(app.id)}
                                                onclick={(e) =>
                                                    e.stopPropagation()}
                                                aria-label={`Select ${app.name}`}
                                            />
                                        </Table.Cell>
                                        <Table.Cell class="font-medium"
                                            >{app.reference_number}</Table.Cell
                                        >
                                        <Table.Cell>{app.name}</Table.Cell>
                                        <Table.Cell
                                            class="text-muted-foreground"
                                            >{app.email ?? '—'}</Table.Cell
                                        >
                                        <Table.Cell class="text-center">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                class="h-8 px-2 text-xs font-semibold"
                                                onclick={() =>
                                                    assignSingle(app.id)}
                                            >
                                                <UserPlus
                                                    class="mr-1.5 size-3.5"
                                                />
                                                Assign
                                            </Button>
                                        </Table.Cell>
                                    </Table.Row>
                                {/each}
                            </Table.Body>
                        </Table.Root>
                    </div>
                    {#if totalReadyPages > 1}
                        <div class="mt-4 flex items-center justify-between border-t border-border/40 pt-3">
                            <span class="text-xs text-muted-foreground">
                                Showing {(readyPage - 1) * readyPerPage + 1} to {Math.min(readyPage * readyPerPage, filteredReady.length)} of {filteredReady.length}
                            </span>
                            <div class="flex items-center gap-1">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    class="h-8 px-2.5 text-xs"
                                    disabled={readyPage <= 1}
                                    onclick={() => (readyPage = Math.max(1, readyPage - 1))}
                                >
                                    Previous
                                </Button>
                                <span class="px-2 font-mono text-xs text-muted-foreground">
                                    {readyPage} / {totalReadyPages}
                                </span>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    class="h-8 px-2.5 text-xs"
                                    disabled={readyPage >= totalReadyPages}
                                    onclick={() => (readyPage = Math.min(totalReadyPages, readyPage + 1))}
                                >
                                    Next
                                </Button>
                            </div>
                        </div>
                    {/if}
                {:else}
                    <p class="text-muted-foreground mt-4 text-sm">
                        No eligible applicants in ready pool.
                    </p>
                {/if}
            </div>
        {/if}
    </div>

    <Dialog.Root bind:open={reasonOpen}>
        <Dialog.Content class="sm:max-w-md">
            <Dialog.Header>
                <Dialog.Title>
                    {reasonMode === 'unpublish'
                        ? 'Unpublish Release Day session'
                        : 'Cancel Release Day session'}
                </Dialog.Title>
                <Dialog.Description>
                    {reasonMode === 'unpublish'
                        ? 'Returns the session to draft. Assignments stay; applicants may need a new publish notice later.'
                        : 'Terminal cancel. Frees active assignments. Requires a reason.'}
                </Dialog.Description>
            </Dialog.Header>
            <Textarea bind:value={reason} rows={3} placeholder="Reason…" />
            <Dialog.Footer>
                <Button
                    type="button"
                    variant="outline"
                    onclick={() => (reasonOpen = false)}>Back</Button
                >
                <Button
                    type="button"
                    variant={reasonMode === 'cancel'
                        ? 'destructive'
                        : 'default'}
                    disabled={!reason.trim()}
                    onclick={submitReason}
                >
                    Confirm
                </Button>
            </Dialog.Footer>
        </Dialog.Content>
    </Dialog.Root>

    <Dialog.Root bind:open={reassignOpen}>
        <Dialog.Content class="sm:max-w-md">
            <Dialog.Header>
                <Dialog.Title
                    >Reassign applicant{selectedAssigned.length === 1
                        ? ''
                        : 's'}</Dialog.Title
                >
                <Dialog.Description>
                    Move {selectedAssigned.length} seat{selectedAssigned.length ===
                    1
                        ? ''
                        : 's'} to another Release Day session in this academic year.
                </Dialog.Description>
            </Dialog.Header>
            <div class="space-y-3">
                <div>
                    <label
                        class="text-muted-foreground mb-1 block text-xs font-medium"
                        for="reassign-target"
                    >
                        Target session
                    </label>
                    <select
                        id="reassign-target"
                        class="border-input bg-background flex min-h-10 w-full rounded-md border px-3 text-sm"
                        bind:value={reassignTargetId}
                    >
                        <option value="">Select a session…</option>
                        {#each openReassignTargets as target (target.id)}
                            <option value={String(target.id)}>
                                {target.code} · {formatScheduledAt(
                                    target.scheduled_at,
                                )} · {target.room_name ?? target.room ?? 'Room'} ·
                                {target.remaining ?? '—'} seats left
                            </option>
                        {/each}
                    </select>
                    {#if openReassignTargets.length === 0}
                        <p class="text-muted-foreground mt-2 text-sm">
                            No other sessions with remaining capacity.
                        </p>
                    {/if}
                </div>
                <div>
                    <label
                        class="text-muted-foreground mb-1 block text-xs font-medium"
                        for="reassign-reason"
                    >
                        Reason (optional)
                    </label>
                    <Textarea
                        id="reassign-reason"
                        rows={3}
                        bind:value={reassignReason}
                        placeholder="Why are they moving?"
                    />
                </div>
            </div>
            <Dialog.Footer>
                <Button
                    type="button"
                    variant="outline"
                    class="min-h-10"
                    onclick={() => (reassignOpen = false)}
                >
                    Cancel
                </Button>
                <Button
                    type="button"
                    class="min-h-10 gap-2"
                    disabled={reassigning ||
                        !reassignTargetId ||
                        selectedAssigned.length === 0}
                    onclick={submitReassign}
                >
                    <ArrowRightLeft class="size-4" />
                    {reassigning ? 'Reassigning…' : 'Confirm reassign'}
                </Button>
            </Dialog.Footer>
        </Dialog.Content>
    </Dialog.Root>
</StaffLayout>
