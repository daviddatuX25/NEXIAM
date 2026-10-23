<script>
    /**
     * Exam session Show — legacy Admin/TestScheduling/Show shape:
     * details, assigned roster, available assign picker, publish from show.
     */
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
    import PrintDocumentsSlot from '@/Components/DocumentTemplate/PrintDocumentsSlot.svelte';
    import ApplicantActionsMenu from '@/Components/Admission/ApplicantActionsMenu.svelte';
    import { Badge } from '@/Components/ui/badge';
    import { Button } from '@/Components/ui/button';
    import * as Dialog from '@/Components/ui/dialog';
    import { Input } from '@/Components/ui/input';
    import * as Table from '@/Components/ui/table';
    import { Textarea } from '@/Components/ui/textarea';
    import AvatarBadge from '@/Components/kit/AvatarBadge.svelte';
    import SortableTableHead from '@/Components/kit/SortableTableHead.svelte';
    import SortDropdown from '@/Components/kit/SortDropdown.svelte';
    import BulkActionBar from '@/Components/kit/BulkActionBar.svelte';
    import { sortRosterRows, nextColumnSort, ariaSortDirection } from '@/lib/roster-sort';
    import { tick } from 'svelte';
    import { applySearchFocus } from '@/lib/search-focus';
    import { Link, router } from '@inertiajs/svelte';
    import {
        Bell,
        Ban,
        Lock,
        LockOpen,
        Pencil,
        Search,
        Send,
        Undo2,
        UserMinus,
        UserPlus,
        ArrowRightLeft,
        Activity,
        Calendar,
        Clock,
        Building2,
        Users,
        GraduationCap,
        BookOpen,
        LayoutGrid,
        List,
    } from 'lucide-svelte';
    import { formatDate, formatDateTime, formatTime, formatTimeRange } from '@/lib/date-utils.js';
    import {
        bookingUnlocked,
        handleEasterEggClick,
    } from '@/lib/booking-visibility';
    let {
        session,
        assigned_applicants = [],
        available_applicants = [],
        reassign_targets = [],
        publish_block_reason = null,
        is_publishable = false,
        can_manage_test_kits = true,
        can_manage_booklets = true,
        is_physical_seating = false,
        snapshot_interpretation = null,
        focus_assignment_id = null,
    } = $props();
    const focusedApplicant = $derived(assigned_applicants.find((row) => String(row.id) === String(focus_assignment_id)) ?? null);

    let selectedAvailable = $state([]);
    let searchAvailable = $state('');
    let searchAssigned = $state('');
    let assignedSort = $state('');
    let assignedSortDir = $state('asc');
    let assignedViewMode = $state('table');
    let availableSort = $state('');
    let availableSortDir = $state('asc');
    let availableViewMode = $state('table');
    let reasonOpen = $state(false);
    let reasonMode = $state('unpublish'); // unpublish | cancel
    let reason = $state('');
    let selectedAssigned = $state([]);
    let reassignOpen = $state(false);
    let reassignTargetId = $state('');
    let reassignReason = $state('');
    let reassigning = $state(false);
    let interpretationMode = $state('');
    let interpretationReason = $state('');
    let interpretationProvenance = $state('');
    let resolvingInterpretation = $state(false);
    let interpretationErrors = $state({});
    const requiresSeatingInterpretation = $derived(
        ['published', 'in_progress', 'completed'].includes(session.status) &&
            snapshot_interpretation !== null &&
            !['simple_capacity', 'assigned_seating'].includes(
                snapshot_interpretation.seating_mode,
            ),
    );

    function resolveSeatingInterpretation() {
        if (!requiresSeatingInterpretation || resolvingInterpretation) return;
        resolvingInterpretation = true;
        interpretationErrors = {};
        router.post(
            `/exams/sessions/${session.id}/snapshots/${snapshot_interpretation.id}/interpretation`,
            {
                expected_version: snapshot_interpretation.revision,
                seating_mode: interpretationMode,
                reason: interpretationReason,
                provenance_notes: interpretationProvenance,
            },
            {
                preserveScroll: true,
                onError: (errors) => {
                    interpretationErrors = errors;
                },
                onSuccess: () => {
                    interpretationMode = '';
                    interpretationReason = '';
                    interpretationProvenance = '';
                },
                onFinish: () => {
                    resolvingInterpretation = false;
                },
            },
        );
    }

    const filteredAvailable = $derived(
        available_applicants.filter((app) => {
            const term = searchAvailable.toLowerCase();
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
    const assignedSortOptions = $derived([
        { value: 'reference_number', label: 'Reference' },
        { value: 'name', label: 'Name' },
        ...(is_physical_seating ? [{ value: 'seat_label', label: 'Seat' }] : []),
        { value: 'source', label: 'Source' },
    ]);
    const availableSortOptions = [
        { value: 'reference_number', label: 'Reference' },
        { value: 'name', label: 'Name' },
    ];
    const sortedFilteredAvailable = $derived(
        sortRosterRows(filteredAvailable, availableSort, availableSortDir),
    );
    const sortedFilteredAssigned = $derived(
        sortRosterRows(filteredAssigned, assignedSort, assignedSortDir),
    );

    // Pagination for Available Applicants (10 per page)
    let availablePage = $state(1);
    const availablePageSize = 10;
    const totalAvailablePages = $derived(
        Math.max(1, Math.ceil(sortedFilteredAvailable.length / availablePageSize)),
    );
    const pagedAvailable = $derived(
        sortedFilteredAvailable.slice(
            (availablePage - 1) * availablePageSize,
            availablePage * availablePageSize,
        ),
    );
    const isAllAvailableSelected = $derived(
        pagedAvailable.length > 0 &&
            pagedAvailable.every((app) => selectedAvailable.includes(app.id)),
    );
    const isSomeAvailableSelected = $derived(
        !isAllAvailableSelected &&
            pagedAvailable.some((app) => selectedAvailable.includes(app.id)),
    );
    function toggleAllAvailable() {
        if (isAllAvailableSelected) {
            const pageIds = pagedAvailable.map((a) => a.id);
            selectedAvailable = selectedAvailable.filter(
                (id) => !pageIds.includes(id),
            );
        } else {
            const newIds = pagedAvailable
                .map((a) => a.id)
                .filter((id) => !selectedAvailable.includes(id));
            selectedAvailable = [...selectedAvailable, ...newIds];
        }
    }

    let assignedPage = $state(1);
    let focusedAssignmentId = null;
    const assignedPageSize = 10;
    const totalAssignedPages = $derived(
        Math.max(1, Math.ceil(sortedFilteredAssigned.length / assignedPageSize)),
    );
    const pagedAssigned = $derived(
        sortedFilteredAssigned.slice(
            (assignedPage - 1) * assignedPageSize,
            assignedPage * assignedPageSize,
        ),
    );
    const isAllAssignedSelected = $derived(
        pagedAssigned.length > 0 &&
            pagedAssigned.every((app) => selectedAssigned.includes(app.id)),
    );
    const isSomeAssignedSelected = $derived(
        !isAllAssignedSelected &&
            pagedAssigned.some((app) => selectedAssigned.includes(app.id)),
    );
    function toggleAllAssigned() {
        if (isAllAssignedSelected) {
            const pageIds = pagedAssigned.map((a) => a.id);
            selectedAssigned = selectedAssigned.filter(
                (id) => !pageIds.includes(id),
            );
        } else {
            const newIds = pagedAssigned
                .map((a) => a.id)
                .filter((id) => !selectedAssigned.includes(id));
            selectedAssigned = [...selectedAssigned, ...newIds];
        }
    }

    $effect(() => {
        if (searchAvailable !== undefined) {
            availablePage = 1;
        }
    });
    $effect(() => {
        if (searchAssigned !== undefined) {
            assignedPage = 1;
        }
    });
    $effect(() => {
        const targetId = Number(focus_assignment_id);
        if (
            !Number.isInteger(targetId) ||
            targetId <= 0 ||
            focusedAssignmentId === targetId
        ) {
            return;
        }
        void (async () => {
            await tick();
            const targetIndex = assigned_applicants.findIndex(
                (applicant) => Number(applicant.id) === targetId,
            );
            if (
                targetIndex === -1 ||
                Number(focus_assignment_id) !== targetId
            ) {
                return;
            }
            assignedPage = Math.floor(targetIndex / assignedPageSize) + 1;
            await tick();
            if (Number(focus_assignment_id) === targetId) {
                applySearchFocus(`scheduling-assignment-${targetId}`);
                focusedAssignmentId = targetId;
            }
        })();
    });

    function toggleAvailable(id) {
        selectedAvailable = selectedAvailable.includes(id)
            ? selectedAvailable.filter((x) => x !== id)
            : [...selectedAvailable, id];
    }
    function handleAssignedColumnSort(columnKey) {
        const next = nextColumnSort(assignedSort, assignedSortDir, columnKey);
        assignedSort = next.sort;
        assignedSortDir = next.dir;
    }

    function handleAvailableColumnSort(columnKey) {
        const next = nextColumnSort(availableSort, availableSortDir, columnKey);
        availableSort = next.sort;
        availableSortDir = next.dir;
    }

    function initialsAvatarClass(sex) {
        if (sex === 'male') return '[&>span]:!bg-sky-500/15 [&>span]:!text-sky-700 dark:[&>span]:!text-sky-300 [&>span]:!border-sky-500/25';
        if (sex === 'female') return '[&>span]:!bg-rose-500/15 [&>span]:!text-rose-700 dark:[&>span]:!text-rose-300 [&>span]:!border-rose-500/25';
        return '[&>span]:!bg-muted [&>span]:!text-muted-foreground [&>span]:!border-border';
    }

    function assignSelected() {
        if (selectedAvailable.length === 0) return;
        const selectedApplicantIds = [...selectedAvailable];

        const endpoint =
            typeof route === 'function'
                ? route('exams.sessions.bulk-assign', session.id)
                : `/exams/sessions/${session.id}/bulk-assign`;

        router.post(
            endpoint,
            { admission_attempt_ids: selectedApplicantIds },
            {
                preserveScroll: true,
                onSuccess: () => {
                    selectedAvailable = [];
                },
            },
        );
    }

    function removeAssigned(assignmentId) {
        router.post(
            `/exams/sessions/${session.id}/assignments/${assignmentId}/remove`,
            {
                reason: 'Removed from session show',
            },
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
            `/exams/sessions/${session.id}/reassign`,
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
        reassign_targets.filter((t) => !t.full),
    );

    function publish() {
        router.post(`/exams/sessions/${session.id}/publish`, {
            expected_version: session.version,
        });
    }

    function openReservation() {
        router.post(`/exams/sessions/${session.id}/open-reservation`, {
            expected_version: session.version,
        });
    }

    function closeReservation() {
        router.post(`/exams/sessions/${session.id}/close-reservation`, {
            expected_version: session.version,
        });
    }

    function bookingModeLabel(mode) {
        if (mode === 'open_for_reservation') return 'Open for reservation';
        return 'Staff only';
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
                ? `/exams/sessions/${session.id}/unpublish`
                : `/exams/sessions/${session.id}/cancel`;
        router.post(
            path,
            {
                expected_version: session.version,
                reason,
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

    const breadcrumbs = $derived([
        { label: 'Exam Scheduling', href: '/exams/sessions' },
        { label: session?.code ?? 'Session' },
    ]);
</script>

<svelte:head><title>{session?.code ?? 'Exam session'}</title></svelte:head>

<StaffLayout {breadcrumbs}>
    <div class="min-w-0 space-y-6 p-4 sm:p-6">
        <div onclick={handleEasterEggClick} role="presentation">
            <PageHeaderBar
                title={session.code}
                description="View session details, assign applicants, and publish the schedule."
            >
                {#snippet actions()}
                        {#if focusedApplicant?.admission_attempt_id}
                            <ApplicantActionsMenu attemptId={focusedApplicant.admission_attempt_id} context={{ exam_session_id: session.id }} />
                        {/if}
                    <div class="flex flex-wrap gap-2">
                        <PrintDocumentsSlot slotKey="exam_session_show" sessionId={session?.id ?? null} />
                        {#if session.status === 'published' || session.status === 'in_progress'}
                            <Link
                                href={`/exams/sessions/${session.id}/monitoring`}
                            >
                                <Button
                                    variant="outline"
                                    class="min-h-10 gap-2"
                                >
                                    <Activity class="size-4" />
                                    Go to monitoring
                                </Button>
                            </Link>
                        {/if}
                        {#if session.status === 'draft'}
                            <Link href={`/exams/sessions/${session.id}/edit`}>
                                <Button
                                    variant="outline"
                                    class="min-h-10 gap-2"
                                >
                                    <Pencil class="size-4" />
                                    Edit session
                                </Button>
                            </Link>
                            <Button
                                class="min-h-10 gap-2"
                                disabled={!is_publishable}
                                title={publish_block_reason ||
                                    'Publish session'}
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
                        {#if session.status === 'draft'}
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
        </div>

        {#if publish_block_reason && session.status === 'draft'}
            <div
                class="rounded-md border border-amber-500/40 bg-amber-500/10 px-4 py-3 text-sm"
            >
                Cannot publish yet: {publish_block_reason}
            </div>
        {/if}

        {#if requiresSeatingInterpretation}
            <section
                class="bg-card space-y-4 rounded-xl border border-amber-500/40 p-6 shadow-xs"
                aria-labelledby="seating-interpretation-heading"
            >
                <div class="space-y-1">
                    <h2
                        id="seating-interpretation-heading"
                        class="text-base font-semibold"
                    >
                        Seating mode interpretation required
                    </h2>
                    <p class="text-muted-foreground text-sm">
                        This historical snapshot does not establish whether
                        applicants had assigned physical seats. Resolve its
                        interpretation using retained publication or creation
                        evidence, not today's institution settings or room
                        layout.
                    </p>
                    <p class="text-muted-foreground text-xs">
                        Snapshot #{snapshot_interpretation.id} · Revision {snapshot_interpretation.revision}
                    </p>
                </div>
                <form
                    class="space-y-4"
                    onsubmit={(event) => {
                        event.preventDefault();
                        resolveSeatingInterpretation();
                    }}
                >
                    <div class="space-y-2">
                        <label
                            for="interpretation-mode"
                            class="text-sm font-medium">Seating mode</label
                        >
                        <select
                            id="interpretation-mode"
                            bind:value={interpretationMode}
                            required
                            disabled={resolvingInterpretation}
                            class="border-input bg-background h-10 w-full rounded-md border px-3 text-sm"
                        >
                            <option value="" disabled
                                >Select the evidenced seating mode</option
                            >
                            <option value="simple_capacity"
                                >Simple capacity (no assigned physical seats)</option
                            >
                            <option value="assigned_seating"
                                >Assigned seating (physical seats assigned)</option
                            >
                        </select>
                        {#if interpretationErrors.seating_mode}<p
                                class="text-destructive text-sm"
                            >
                                {interpretationErrors.seating_mode}
                            </p>{/if}
                    </div>
                    <div class="space-y-2">
                        <label
                            for="interpretation-reason"
                            class="text-sm font-medium">Resolution reason</label
                        >
                        <Textarea
                            id="interpretation-reason"
                            bind:value={interpretationReason}
                            required
                            minlength={5}
                            disabled={resolvingInterpretation}
                            placeholder="Explain why the retained evidence establishes this seating mode."
                        />
                        {#if interpretationErrors.reason}<p
                                class="text-destructive text-sm"
                            >
                                {interpretationErrors.reason}
                            </p>{/if}
                    </div>
                    <div class="space-y-2">
                        <label
                            for="interpretation-provenance"
                            class="text-sm font-medium"
                            >Provenance references</label
                        >
                        <Textarea
                            id="interpretation-provenance"
                            bind:value={interpretationProvenance}
                            required
                            disabled={resolvingInterpretation}
                            placeholder="Identify the publication record, creation record, or migration evidence and its concrete reference."
                        />
                        {#if interpretationErrors.provenance_notes}<p
                                class="text-destructive text-sm"
                            >
                                {interpretationErrors.provenance_notes}
                            </p>{/if}
                    </div>
                    {#if interpretationErrors.expected_version}<p
                            role="alert"
                            class="text-destructive text-sm"
                        >
                            {interpretationErrors.expected_version}
                        </p>{/if}
                    {#if interpretationErrors.interpretation}<p
                            role="alert"
                            class="text-destructive text-sm"
                        >
                            {interpretationErrors.interpretation}
                        </p>{/if}
                    <Button
                        type="submit"
                        disabled={resolvingInterpretation ||
                            !interpretationMode ||
                            interpretationReason.trim().length < 5 ||
                            !interpretationProvenance.trim()}
                    >
                        {resolvingInterpretation
                            ? 'Resolving interpretation…'
                            : 'Resolve seating interpretation'}
                    </Button>
                </form>
            </section>
        {/if}

        <div class="border-border bg-card rounded-xl border p-6 shadow-xs">
            <dl class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                <div class="space-y-1 font-sans">
                    <dt
                        class="text-muted-foreground text-xs font-medium tracking-wider uppercase"
                    >
                        Date & Time
                    </dt>
                    <dd class="space-y-0.5 pt-0.5">
                        <div
                            class="text-foreground flex items-center gap-1.5 text-sm font-medium"
                        >
                            <Calendar class="text-primary size-4 shrink-0" />
                            <span>{formatDate(session.date, 'long')}</span>
                        </div>
                        {#if session.start_time}
                            <div
                                class="text-muted-foreground flex items-center gap-1.5 font-mono text-xs"
                            >
                                <Clock
                                    class="text-muted-foreground size-3.5 shrink-0"
                                />
                                <span
                                    >{session.started_at
                                        ? `Scheduled ${formatTime(session.start_time)}`
                                        : formatTimeRange(
                                              session.start_time,
                                              session.end_time,
                                          )}</span
                                >
                            </div>
                        {/if}
                        {#if session.started_at || session.closed_at}
                            <div
                                class="border-border/60 text-muted-foreground space-y-0.5 border-t pt-1 font-mono text-xs"
                            >
                                {#if session.started_at}
                                    <div>
                                        Started {formatDateTime(
                                            session.started_at,
                                        )}
                                    </div>
                                {/if}
                                {#if session.status === 'in_progress' && session.effective_end_at}
                                    <div>
                                        Expected end {formatDateTime(
                                            session.effective_end_at,
                                        )}
                                    </div>
                                {/if}
                                {#if session.closed_at}
                                    <div>
                                        Closed {formatDateTime(
                                            session.closed_at,
                                        )}
                                    </div>
                                {/if}
                            </div>
                        {/if}
                    </dd>
                </div>
                <div class="space-y-1 font-sans">
                    <dt
                        class="text-muted-foreground text-xs font-medium tracking-wider uppercase"
                    >
                        Room & Venue
                    </dt>
                    <dd class="space-y-0.5 pt-0.5">
                        <div
                            class="text-foreground flex items-center gap-1.5 text-sm font-medium"
                        >
                            <Building2 class="text-primary size-4 shrink-0" />
                            <span
                                >{session.room?.name ??
                                    session.room?.code ??
                                    '—'}</span
                            >
                        </div>
                        <div
                            class="text-muted-foreground flex items-center gap-1.5 font-mono text-xs"
                        >
                            <Users
                                class="text-muted-foreground size-3.5 shrink-0"
                            />
                            <span
                                >Capacity: {session.room?.capacity ?? '—'} Seats</span
                            >
                        </div>
                    </dd>
                </div>
                <div class="space-y-1 font-sans">
                    <dt
                        class="text-muted-foreground text-xs font-medium tracking-wider uppercase"
                    >
                        Academic Year
                    </dt>
                    <dd class="pt-0.5">
                        <div
                            class="text-foreground flex items-center gap-1.5 text-sm font-medium"
                        >
                            <GraduationCap
                                class="text-primary size-4 shrink-0"
                            />
                            <span>{session.academic_cycle?.label ?? '—'}</span>
                        </div>
                        <div class="flex items-center gap-1.5 pt-1">
                            <Badge
                                variant={statusVariant(session.status)}
                                class="font-mono text-[10px] font-bold uppercase"
                                >{session.status}</Badge
                            >
                            {#if session.booking_mode === 'open_for_reservation' || $bookingUnlocked}
                                <Badge
                                    variant={session.booking_mode ===
                                    'open_for_reservation'
                                        ? 'default'
                                        : 'outline'}
                                    class="text-[10px]"
                                >
                                    {bookingModeLabel(session.booking_mode)}
                                </Badge>
                            {/if}
                        </div>
                    </dd>
                </div>
                <div class="space-y-1 font-mono">
                    <dt
                        class="text-muted-foreground text-xs font-medium tracking-wider uppercase"
                    >
                        Roster Allocation
                    </dt>
                    <dd class="space-y-1.5 pt-0.5">
                        <div class="flex justify-between text-xs font-medium">
                            <span class="text-foreground"
                                >{assigned_applicants.length} assigned</span
                            >
                            <span class="text-muted-foreground"
                                >{session.room?.capacity
                                    ? Math.round(
                                          (assigned_applicants.length /
                                              session.room.capacity) *
                                              100,
                                      )
                                    : 0}% filled</span
                            >
                        </div>
                        <div
                            class="bg-muted h-2 w-full overflow-hidden rounded-full"
                        >
                            <div
                                class="from-primary h-2 rounded-full bg-gradient-to-r to-emerald-400 transition-all duration-300"
                                style="width: {session.room?.capacity
                                    ? Math.min(
                                          100,
                                          Math.round(
                                              (assigned_applicants.length /
                                                  session.room.capacity) *
                                                  100,
                                          ),
                                      )
                                    : 0}%"
                            ></div>
                        </div>
                    </dd>
                </div>
            </dl>

            {#if session.effective_notification_policy}
                <div
                    class="border-border/60 mt-4 flex flex-wrap items-center justify-between gap-3 border-t pt-4 text-xs"
                >
                    <div class="text-muted-foreground flex items-center gap-2">
                        <Bell class="text-primary size-3.5 shrink-0" />
                        <span class="text-foreground font-medium"
                            >Communication:</span
                        >
                        <span>
                            Publish email {session.effective_notification_policy
                                .publication_email_enabled
                                ? 'enabled'
                                : 'disabled'} · Reminders: {(
                                session.effective_notification_policy
                                    .reminder_offsets_minutes ?? []
                            )
                                .map((m) =>
                                    m === 4320
                                        ? '72h'
                                        : m === 1440
                                          ? '24h'
                                          : m === 120
                                            ? '2h'
                                            : '1h',
                                )
                                .join(', ') || 'None'}
                            ({session.effective_notification_policy
                                .reminder_email_enabled
                                ? 'Email + In-App'
                                : 'In-App only'})
                        </span>
                    </div>
                    <div>
                        {#if session.notification_policy}
                            <Badge
                                variant="outline"
                                class="font-mono text-[10px]"
                                >Custom session policy</Badge
                            >
                        {:else}
                            <Badge
                                variant="secondary"
                                class="font-mono text-[10px]"
                                >Academic year defaults</Badge
                            >
                        {/if}
                    </div>
                </div>
            {/if}

            <!-- Minimized Exam Materials Strip (Compact, non-distracting secondary info) -->
            {#if session.material?.test_kit || session.material?.booklet || session.material?.seating_arrangement || session.material?.snapshot_id}
                <div
                    class="border-border/60 mt-4 flex flex-wrap items-center justify-between gap-3 border-t pt-4 font-mono text-xs"
                >
                    <div
                        class="text-muted-foreground flex flex-wrap items-center gap-4"
                    >
                        {#if session.material?.test_kit}
                            <div class="flex items-center gap-1.5">
                                <span class="text-muted-foreground">Test Kit:</span>
                                <span class="text-foreground font-bold">
                                    {session.material.test_kit.name ?? session.material.test_kit.code} v{session.material.test_kit.version_number}
                                </span>
                            </div>
                        {/if}
                        <div class="flex items-center gap-1.5">
                            <BookOpen
                                class="text-muted-foreground size-3.5 shrink-0"
                            />
                            <span class="text-muted-foreground">Booklet:</span>
                            <span class="text-foreground font-bold"
                                >{session.material?.booklet?.label ?? '—'}</span
                            >
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span class="text-muted-foreground">Sheet:</span>
                            <span class="text-foreground font-medium"
                                >{session.material?.booklet?.answer_sheet ??
                                    '—'}</span
                            >
                        </div>
                        {#if is_physical_seating && session.material?.seating_arrangement}
                            <div class="flex items-center gap-1.5">
                                <span class="text-muted-foreground"
                                    >Seating:</span
                                >
                                <span class="text-foreground font-medium"
                                    >{session.material.seating_arrangement
                                        .name}</span
                                >
                            </div>
                        {/if}
                        {#if snapshot_interpretation}
                            <div class="flex items-center gap-1.5">
                                <span class="text-muted-foreground"
                                    >Snapshot seating mode:</span
                                >
                                <span class="text-foreground font-medium">
                                    {#if snapshot_interpretation.seating_mode === 'simple_capacity'}
                                        Simple capacity (no assigned physical
                                        seats)
                                    {:else if snapshot_interpretation.seating_mode === 'assigned_seating'}
                                        Assigned physical seats
                                    {:else}
                                        Unknown (interpretation required)
                                    {/if}
                                </span>
                            </div>
                        {/if}
                        {#if session.material?.snapshot_id}
                            <span
                                class="bg-muted text-muted-foreground border-border rounded border px-2 py-0.5 text-[10px] font-semibold"
                            >
                                Snapshot Frozen
                            </span>
                        {/if}
                    </div>
                    {#if session.material?.test_kit?.test_kit_id && can_manage_test_kits}
                        <Link
                            href={`/setup/exam-materials/test-kits/${session.material.test_kit.test_kit_id}`}
                            class="text-primary flex items-center gap-1 font-sans text-[11px] font-medium hover:underline"
                        >
                            <span>View Test Kit</span> &rarr;
                        </Link>
                    {:else if session.material?.booklet && can_manage_booklets}
                        <Link
                            href={`/setup/exam-materials/booklets/${session.material.booklet.id}`}
                            class="text-primary flex items-center gap-1 font-sans text-[11px] font-medium hover:underline"
                        >
                            <span>View Booklet</span> &rarr;
                        </Link>
                    {/if}
                </div>
            {/if}
        </div>

        <!-- Assigned Applicants Section -->
        <div class="border-border bg-card rounded-xl border p-6 shadow-xs">
            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h2
                        class="text-foreground text-base font-semibold tracking-tight"
                    >
                        Assigned applicants ({assigned_applicants.length})
                    </h2>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    {#if ['published', 'in_progress'].includes(session.status)}
                        <Link href={`/exams/sessions/${session.id}/monitoring`}>
                            <Button variant="outline" size="sm" class="h-9 gap-1.5 text-xs">
                                <ArrowRightLeft class="size-3.5" />
                                Accommodate / Transfer on Monitoring
                            </Button>
                        </Link>
                    {/if}
                    {#if session.status !== 'completed' && session.status !== 'cancelled'}
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
                    {/if}
                </div>
            </div>

            {#if assigned_applicants.length > 0}
                <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="relative min-w-0 flex-1 max-w-sm">
                        <Search
                            class="text-muted-foreground pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2"
                        />
                        <Input
                            type="search"
                            placeholder="Search assigned by name or reference..."
                            class="h-9 pl-8 font-mono text-xs"
                            bind:value={searchAssigned}
                        />
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <SortDropdown
                            options={assignedSortOptions}
                            sort={assignedSort || 'reference_number'}
                            dir={assignedSortDir}
                            label="Sort assigned applicants"
                            onchange={({ sort, dir }) => { assignedSort = sort; assignedSortDir = dir; }}
                        />
                        <div class="inline-flex rounded-lg border border-border bg-muted/40 p-0.5" role="group" aria-label="Assigned view mode">
                            <button
                                type="button"
                                class="rounded-md px-2.5 py-1 text-xs font-medium {assignedViewMode === 'table' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground'}"
                                onclick={() => (assignedViewMode = 'table')}
                            >
                                <List class="mr-1 inline size-3.5" /> Table
                            </button>
                            <button
                                type="button"
                                class="rounded-md px-2.5 py-1 text-xs font-medium {assignedViewMode === 'cards' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground'}"
                                onclick={() => (assignedViewMode = 'cards')}
                            >
                                <LayoutGrid class="mr-1 inline size-3.5" /> Cards
                            </button>
                        </div>
                    </div>
                </div>

                {#if session.status !== 'completed' && session.status !== 'cancelled'}
                    <div class="mt-3">
                        <BulkActionBar count={selectedAssigned.length} onClear={() => (selectedAssigned = [])}>
                            {#snippet actions()}
                                <Button
                                    size="sm"
                                    variant="outline"
                                    class="h-8 gap-1.5 text-xs"
                                    disabled={openReassignTargets.length === 0}
                                    onclick={() => openReassign(selectedAssigned)}
                                >
                                    <ArrowRightLeft class="size-3.5" />
                                    Reassign selected ({selectedAssigned.length})
                                </Button>
                            {/snippet}
                        </BulkActionBar>
                    </div>
                {/if}

                {#if assignedViewMode === 'cards'}
                    <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        {#each pagedAssigned as row (row.id)}
                            <article
                                id={'scheduling-assignment-' + row.id}
                                class="space-y-3 rounded-xl border border-border bg-card p-4"
                            >
                                <div class="flex items-start justify-between gap-2.5">
                                    <div class="flex min-w-0 items-center gap-2.5">
                                        {#if session.status !== 'completed' && session.status !== 'cancelled'}
                                            <input
                                                type="checkbox"
                                                class="accent-primary size-4 shrink-0 cursor-pointer rounded"
                                                checked={selectedAssigned.includes(row.id)}
                                                onchange={() => toggleAssigned(row.id)}
                                                aria-label={`Select ${row.name}`}
                                            />
                                        {/if}
                                        <AvatarBadge name={row.name} size="sm" class={initialsAvatarClass(row.sex)} />
                                        <div class="min-w-0">
                                            <div class="truncate font-semibold text-foreground">{row.name}</div>
                                            <div class="truncate font-mono text-xs text-muted-foreground">{row.reference_number}</div>
                                        </div>
                                    </div>
                                    <Badge variant="outline" class="text-[10px]">{row.source}</Badge>
                                </div>
                                {#if is_physical_seating}
                                    <div class="text-xs text-muted-foreground">
                                        Seat: <span class="font-mono font-semibold text-foreground">{row.seat_label ?? '—'}</span>
                                    </div>
                                {/if}
                                {#if session.status !== 'completed' && session.status !== 'cancelled'}
                                    <div class="flex items-center justify-end gap-1.5 border-t border-border/60 pt-2.5">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            class="h-8 px-2 text-xs"
                                            disabled={openReassignTargets.length === 0}
                                            onclick={() => openReassign([row.id])}
                                        >
                                            <ArrowRightLeft class="mr-1.5 size-3.5" /> Reassign
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            class="text-destructive hover:bg-destructive/10 h-8 px-2 text-xs"
                                            onclick={() => removeAssigned(row.id)}
                                        >
                                            <UserMinus class="mr-1.5 size-3.5" /> Remove
                                        </Button>
                                    </div>
                                {/if}
                            </article>
                        {/each}
                    </div>
                {:else}
                    <div class="mt-4 overflow-x-auto">
                        <Table.Root>
                            <Table.Header class="bg-muted/50">
                                <Table.Row>
                                    <Table.Head class="w-12">
                                        {#if session.status !== 'completed' && session.status !== 'cancelled' && pagedAssigned.length > 0}
                                            <input
                                                type="checkbox"
                                                class="accent-primary size-4 cursor-pointer rounded"
                                                checked={isAllAssignedSelected}
                                                indeterminate={isSomeAssignedSelected}
                                                onchange={toggleAllAssigned}
                                                aria-label="Select all assigned applicants on this page"
                                                title="Select all"
                                            />
                                        {/if}
                                    </Table.Head>
                                    <SortableTableHead
                                        label="Reference"
                                        direction={ariaSortDirection(assignedSort, assignedSortDir, 'reference_number')}
                                        onclick={() => handleAssignedColumnSort('reference_number')}
                                    />
                                    <SortableTableHead
                                        label="Name"
                                        direction={ariaSortDirection(assignedSort, assignedSortDir, 'name')}
                                        onclick={() => handleAssignedColumnSort('name')}
                                    />
                                    {#if is_physical_seating}
                                        <SortableTableHead
                                            label="Seat"
                                            direction={ariaSortDirection(assignedSort, assignedSortDir, 'seat_label')}
                                            onclick={() => handleAssignedColumnSort('seat_label')}
                                        />
                                    {/if}
                                    <SortableTableHead
                                        label="Source"
                                        direction={ariaSortDirection(assignedSort, assignedSortDir, 'source')}
                                        onclick={() => handleAssignedColumnSort('source')}
                                    />
                                    <Table.Head class="text-center">Actions</Table.Head>
                                </Table.Row>
                            </Table.Header>
                            <Table.Body>
                                {#each pagedAssigned as row (row.id)}
                                    <Table.Row
                                        id={'scheduling-assignment-' + row.id}
                                        class="hover:bg-muted/30 transition-colors"
                                    >
                                        <Table.Cell>
                                            {#if session.status !== 'completed' && session.status !== 'cancelled'}
                                                <input
                                                    type="checkbox"
                                                    class="accent-primary size-4 cursor-pointer rounded"
                                                    checked={selectedAssigned.includes(
                                                        row.id,
                                                    )}
                                                    onchange={() =>
                                                        toggleAssigned(row.id)}
                                                    aria-label={`Select ${row.name}`}
                                                />
                                            {/if}
                                        </Table.Cell>
                                        <Table.Cell
                                            class="text-foreground font-mono text-xs font-semibold"
                                            >{row.reference_number}</Table.Cell
                                        >
                                        <Table.Cell class="text-foreground font-medium">
                                            <div class="flex items-center gap-2.5">
                                                <AvatarBadge name={row.name} size="sm" class={initialsAvatarClass(row.sex)} />
                                                <span>{row.name}</span>
                                            </div>
                                        </Table.Cell>
                                        {#if is_physical_seating}
                                            <Table.Cell
                                                class="text-muted-foreground font-mono text-xs"
                                                >{row.seat_label ?? '—'}</Table.Cell
                                            >
                                        {/if}
                                        <Table.Cell
                                            class="text-muted-foreground text-xs"
                                            >{row.source}</Table.Cell
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
                                                        class="text-destructive hover:bg-destructive/10 h-8 px-2 text-xs"
                                                        onclick={() =>
                                                            removeAssigned(row.id)}
                                                    >
                                                        <UserMinus
                                                            class="mr-1.5 size-3.5"
                                                        />
                                                        Remove
                                                    </Button>
                                                </div>
                                            {/if}
                                        </Table.Cell>
                                    </Table.Row>
                                {/each}
                            </Table.Body>
                        </Table.Root>
                    </div>
                {/if}

                {#if totalAssignedPages > 1}
                    <div
                        class="border-border/60 mt-4 flex items-center justify-between border-t pt-3 font-mono text-xs"
                    >
                        <span class="text-muted-foreground">
                            Showing {(assignedPage - 1) * assignedPageSize +
                                1}–{Math.min(
                                assignedPage * assignedPageSize,
                                filteredAssigned.length,
                            )} of {filteredAssigned.length}
                        </span>
                        <div class="flex items-center gap-1.5">
                            <Button
                                variant="outline"
                                size="sm"
                                class="h-8 px-2.5 text-xs"
                                disabled={assignedPage <= 1}
                                onclick={() =>
                                    (assignedPage = Math.max(
                                        1,
                                        assignedPage - 1,
                                    ))}
                            >
                                Previous
                            </Button>
                            <span class="text-muted-foreground px-2"
                                >{assignedPage} / {totalAssignedPages}</span
                            >
                            <Button
                                variant="outline"
                                size="sm"
                                class="h-8 px-2.5 text-xs"
                                disabled={assignedPage >= totalAssignedPages}
                                onclick={() =>
                                    (assignedPage = Math.min(
                                        totalAssignedPages,
                                        assignedPage + 1,
                                    ))}
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

        <!-- Available Applicants Section -->
        {#if session.status !== 'completed' && session.status !== 'cancelled'}
            <div class="border-border bg-card rounded-xl border p-6 shadow-xs">
                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <h2
                            class="text-foreground text-base font-semibold tracking-tight"
                        >
                            Available applicants ({filteredAvailable.length})
                        </h2>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        {#if selectedAvailable.length > 0}
                            <span class="text-muted-foreground text-xs"
                                >Selected {selectedAvailable.length} applicant(s)</span
                            >
                            <Button
                                variant="ghost"
                                size="sm"
                                class="text-destructive hover:bg-destructive/10 h-8 px-2 text-xs"
                                onclick={() => (selectedAvailable = [])}
                            >
                                Clear selection
                            </Button>
                        {/if}
                        <Button
                            class="min-h-10 gap-2 font-mono text-xs"
                            disabled={selectedAvailable.length === 0}
                            onclick={assignSelected}
                        >
                            <UserPlus class="size-4" />
                            Assign selected ({selectedAvailable.length})
                        </Button>
                    </div>
                </div>

                {#if available_applicants.length > 0}
                    <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="relative min-w-0 flex-1 max-w-sm">
                            <Search
                                class="text-muted-foreground pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2"
                            />
                            <Input
                                type="search"
                                placeholder="Search available by name, reference, email..."
                                class="h-9 pl-8 font-mono text-xs"
                                bind:value={searchAvailable}
                            />
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <SortDropdown
                                options={availableSortOptions}
                                sort={availableSort || 'reference_number'}
                                dir={availableSortDir}
                                label="Sort available applicants"
                                onchange={({ sort, dir }) => { availableSort = sort; availableSortDir = dir; }}
                            />
                            <div class="inline-flex rounded-lg border border-border bg-muted/40 p-0.5" role="group" aria-label="Available view mode">
                                <button
                                    type="button"
                                    class="rounded-md px-2.5 py-1 text-xs font-medium {availableViewMode === 'table' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground'}"
                                    onclick={() => (availableViewMode = 'table')}
                                >
                                    <List class="mr-1 inline size-3.5" /> Table
                                </button>
                                <button
                                    type="button"
                                    class="rounded-md px-2.5 py-1 text-xs font-medium {availableViewMode === 'cards' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground'}"
                                    onclick={() => (availableViewMode = 'cards')}
                                >
                                    <LayoutGrid class="mr-1 inline size-3.5" /> Cards
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="mt-3">
                        <BulkActionBar count={selectedAvailable.length} onClear={() => (selectedAvailable = [])}>
                            {#snippet actions()}
                                <Button
                                    size="sm"
                                    class="h-8 gap-1.5 text-xs"
                                    onclick={assignSelected}
                                >
                                    <UserPlus class="size-3.5" />
                                    Assign selected ({selectedAvailable.length})
                                </Button>
                            {/snippet}
                        </BulkActionBar>
                    </div>

                    {#if availableViewMode === 'cards'}
                        <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                            {#each pagedAvailable as app (app.id)}
                                <article class="flex items-center justify-between gap-3 rounded-xl border border-border bg-card p-3.5">
                                    <label class="flex min-w-0 flex-1 cursor-pointer items-center gap-2.5">
                                        <input
                                            type="checkbox"
                                            class="accent-primary size-4 shrink-0 cursor-pointer rounded"
                                            checked={selectedAvailable.includes(app.id)}
                                            onchange={() => toggleAvailable(app.id)}
                                            aria-label={`Select ${app.name}`}
                                        />
                                        <AvatarBadge name={app.name} size="sm" class={initialsAvatarClass(app.sex)} />
                                        <div class="min-w-0">
                                            <div class="truncate font-semibold text-foreground">{app.name}</div>
                                            <div class="truncate font-mono text-xs text-muted-foreground">{app.reference_number}</div>
                                        </div>
                                    </label>
                                </article>
                            {/each}
                        </div>
                    {:else}
                        <div class="mt-4 overflow-x-auto">
                            <Table.Root>
                                <Table.Header class="bg-muted/50">
                                    <Table.Row>
                                        <Table.Head class="w-12">
                                            {#if pagedAvailable.length > 0}
                                                <input
                                                    type="checkbox"
                                                    class="accent-primary size-4 cursor-pointer rounded"
                                                    checked={isAllAvailableSelected}
                                                    indeterminate={isSomeAvailableSelected}
                                                    onchange={toggleAllAvailable}
                                                    aria-label="Select all available applicants on this page"
                                                    title="Select all"
                                                />
                                            {/if}
                                        </Table.Head>
                                        <SortableTableHead
                                            label="Reference"
                                            direction={ariaSortDirection(availableSort, availableSortDir, 'reference_number')}
                                            onclick={() => handleAvailableColumnSort('reference_number')}
                                        />
                                        <SortableTableHead
                                            label="Name"
                                            direction={ariaSortDirection(availableSort, availableSortDir, 'name')}
                                            onclick={() => handleAvailableColumnSort('name')}
                                        />
                                    </Table.Row>
                                </Table.Header>
                                <Table.Body>
                                    {#each pagedAvailable as app (app.id)}
                                        <Table.Row
                                            class="hover:bg-muted/30 transition-colors"
                                        >
                                            <Table.Cell>
                                                <input
                                                    type="checkbox"
                                                    class="accent-primary size-4 cursor-pointer rounded"
                                                    checked={selectedAvailable.includes(
                                                        app.id,
                                                    )}
                                                    onchange={() =>
                                                        toggleAvailable(app.id)}
                                                    aria-label={`Select ${app.name}`}
                                                />
                                            </Table.Cell>
                                            <Table.Cell
                                                class="text-foreground font-mono text-xs font-semibold"
                                                >{app.reference_number}</Table.Cell
                                            >
                                            <Table.Cell class="text-foreground font-medium">
                                                <div class="flex items-center gap-2.5">
                                                    <AvatarBadge name={app.name} size="sm" class={initialsAvatarClass(app.sex)} />
                                                    <span>{app.name}</span>
                                                </div>
                                            </Table.Cell>
                                        </Table.Row>
                                    {/each}
                                </Table.Body>
                            </Table.Root>
                        </div>
                    {/if}
                    {#if totalAvailablePages > 1}
                        <div
                            class="border-border/60 mt-4 flex items-center justify-between border-t pt-3 font-mono text-xs"
                        >
                            <span class="text-muted-foreground">
                                Showing {(availablePage - 1) *
                                    availablePageSize +
                                    1}–{Math.min(
                                    availablePage * availablePageSize,
                                    filteredAvailable.length,
                                )} of {filteredAvailable.length}
                            </span>
                            <div class="flex items-center gap-1.5">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    class="h-8 px-2.5 text-xs"
                                    disabled={availablePage <= 1}
                                    onclick={() =>
                                        (availablePage = Math.max(
                                            1,
                                            availablePage - 1,
                                        ))}
                                >
                                    Previous
                                </Button>
                                <span class="text-muted-foreground px-2"
                                    >{availablePage} / {totalAvailablePages}</span
                                >
                                <Button
                                    variant="outline"
                                    size="sm"
                                    class="h-8 px-2.5 text-xs"
                                    disabled={availablePage >=
                                        totalAvailablePages}
                                    onclick={() =>
                                        (availablePage = Math.min(
                                            totalAvailablePages,
                                            availablePage + 1,
                                        ))}
                                >
                                    Next
                                </Button>
                            </div>
                        </div>
                    {/if}
                {:else}
                    <p class="text-muted-foreground mt-4 text-sm">
                        No eligible applicants available to assign.
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
                        ? 'Unpublish session'
                        : 'Cancel session'}
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
                    disabled={!reason.trim()}
                    onclick={submitReason}>Confirm</Button
                >
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
                        : 's'} to another session in this academic year. Applicant
                    notify on reassignment is not wired yet.
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
                            <option value={target.id}>
                                {target.code} · {formatDate(target.date)} · {formatTime(target.start_time)} · {target.room ?? 'Room'} · {target.remaining}
                                seats left
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
