<script>
    /**
     * Consultation session Show — legacy list -> create/edit shape:
     * details, assigned roster, ready pool assign panel, booking toggle, publish.
     */
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
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
        Lock,
        LockOpen,
        Pencil,
        Search,
        Send,
        Undo2,
        UserMinus,
        UserPlus,
    } from 'lucide-svelte';

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
        errors = {},
    } = $props();

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
        selectedReady = [];

        const postNext = (index) => {
            if (index >= ids.length) {
                router.reload();
                return;
            }
            router.post(
                `/consultation/sessions/${session.id}/assign`,
                { admission_attempt_id: ids[index] },
                {
                    preserveScroll: true,
                    onFinish: () => postNext(index + 1),
                },
            );
        };

        postNext(0);
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
        { label: 'Consultation', href: '/consultation' },
        { label: 'Session Scheduling', href: '/consultation/sessions' },
        { label: session?.code ?? 'Session' },
    ];
</script>

<svelte:head
    ><title>{session?.code ?? 'Consultation session'}</title></svelte:head
>

<StaffLayout {breadcrumbs}>
    <div class="min-w-0 space-y-6 p-4 sm:p-6">
        <PageHeaderBar
            title={session.code}
            description="View session details, assign applicants, manage booking, and publish the schedule."
        >
            {#snippet actions()}
                <div class="flex flex-wrap gap-2">
                    {#if can_decide}
                        <Link href="/consultation">
                            <Button variant="outline" class="min-h-10 gap-2">
                                <CalendarCheck class="size-4" />
                                Consultation decisions
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

                    {#if session.status !== 'completed' && session.status !== 'cancelled'}
                        {#if session.booking_mode === 'open_for_reservation'}
                            <Button
                                variant="outline"
                                class="min-h-10 gap-2"
                                onclick={closeReservation}
                            >
                                <Lock class="size-4" />
                                Close reservation
                            </Button>
                        {:else}
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
                    <Button
                        variant="outline"
                        class="min-h-10 gap-2"
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
                    />
                </div>
                <div class="mt-4 overflow-x-auto">
                    <Table.Root>
                        <Table.Header class="bg-muted/50">
                            <Table.Row>
                                <Table.Head class="w-12"></Table.Head>
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
                            {#each filteredAssigned as row (row.id)}
                                <Table.Row>
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
                                    <Table.Cell class="text-muted-foreground"
                                        >{row.status ?? 'assigned'}</Table.Cell
                                    >
                                    <Table.Cell class="text-center">
                                        {#if session.status !== 'completed' && session.status !== 'cancelled'}
                                            <div
                                                class="flex flex-wrap items-center justify-center gap-1"
                                            >
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    class="h-8 px-2 text-xs"
                                                    disabled={openReassignTargets.length ===
                                                        0}
                                                    onclick={() =>
                                                        openReassign([row.id])}
                                                >
                                                    <ArrowRightLeft
                                                        class="mr-1.5 size-3.5"
                                                    />
                                                    Reassign
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    class="text-destructive h-8 px-2 text-xs"
                                                    onclick={() =>
                                                        cancelAssignment(
                                                            row.id,
                                                        )}
                                                >
                                                    <UserMinus
                                                        class="mr-1.5 size-3.5"
                                                    />
                                                    Cancel
                                                </Button>
                                            </div>
                                        {/if}
                                    </Table.Cell>
                                </Table.Row>
                            {/each}
                        </Table.Body>
                    </Table.Root>
                </div>
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
                            prepared documents, without an active consultation
                            assignment.
                        </p>
                    </div>
                    <Button
                        class="min-h-10 gap-2"
                        disabled={selectedReady.length === 0}
                        onclick={assignSelected}
                    >
                        <UserPlus class="size-4" />
                        Assign selected ({selectedReady.length})
                    </Button>
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
                        />
                    </div>
                    <div class="mt-4 overflow-x-auto">
                        <Table.Root>
                            <Table.Header class="bg-muted/50">
                                <Table.Row>
                                    <Table.Head class="w-12"></Table.Head>
                                    <Table.Head>Reference</Table.Head>
                                    <Table.Head>Name</Table.Head>
                                    <Table.Head>Email</Table.Head>
                                    <Table.Head class="text-center"
                                        >Action</Table.Head
                                    >
                                </Table.Row>
                            </Table.Header>
                            <Table.Body>
                                {#each filteredReady as app (app.id)}
                                    <Table.Row>
                                        <Table.Cell>
                                            <input
                                                type="checkbox"
                                                class="accent-primary size-4"
                                                checked={selectedReady.includes(
                                                    app.id,
                                                )}
                                                onchange={() =>
                                                    toggleReady(app.id)}
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
                        ? 'Unpublish consultation session'
                        : 'Cancel consultation session'}
                </Dialog.Title>
                <Dialog.Description>
                    {reasonMode === 'unpublish'
                        ? 'Returns the session to draft. Assignments stay; applicants may need a new publish notice later.'
                        : 'Terminal cancel. Frees active assignments and open reservations. Requires a reason.'}
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
                        : 's'} to another consultation session in this academic year.
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
