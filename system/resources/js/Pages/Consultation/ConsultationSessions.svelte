<script>
    /**
     * Consultation sessions Index — legacy list -> create/edit shape:
     * list + filters + Create CTA; row opens Show.
     */
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
    import SwitchableListView from '@/Components/kit/SwitchableListView.svelte';
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
        Eye,
        Lock,
        LockOpen,
        Pencil,
        Plus,
        Search,
        Send,
    } from 'lucide-svelte';

    let {
        page_state = 'ready',
        sessions = [],
        filters = {},
        statuses = [],
        cycles = [],
        booking_modes = [],
        errors = {},
    } = $props();

    let viewMode = $state('responsive');
    let filterSearch = $state(filters.search ?? '');
    let filterStatus = $state(filters.status ?? '');
    let filterCycleId = $state(
        filters.academic_cycle_id ? String(filters.academic_cycle_id) : '',
    );
    let debounceTimer;

    let cancelDialogOpen = $state(false);
    let cancelReason = $state('');
    let activeSession = $state(null);

    function applyFilters() {
        router.get(
            '/consultation/sessions',
            {
                search: filterSearch || undefined,
                status: filterStatus || undefined,
                academic_cycle_id: filterCycleId || undefined,
            },
            { preserveState: true },
        );
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
        { label: 'Consultation', href: '/consultation' },
        { label: 'Session Scheduling' },
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

<svelte:head><title>Consultation session scheduling</title></svelte:head>

<StaffLayout {breadcrumbs}>
    <div class="min-w-0 space-y-6 p-4 sm:p-6">
        <PageHeaderBar
            title="Consultation session scheduling"
            description="Plan consultation sessions: set room and date, assign applicants, and publish when ready."
        >
            {#snippet actions()}
                <div class="flex flex-wrap gap-2">
                    <Link href="/consultation/sessions/create">
                        <Button class="min-h-10 gap-2">
                            <Plus class="size-4" />
                            Create session
                        </Button>
                    </Link>
                </div>
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
                You do not have permission to manage consultation sessions.
            </p>
        {:else}
            <div class="flex flex-wrap items-end gap-3">
                <div>
                    <label
                        class="text-muted-foreground mb-1 block text-xs font-medium"
                        for="consultation-search">Search</label
                    >
                    <div class="relative">
                        <Search
                            class="text-muted-foreground pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2"
                        />
                        <Input
                            id="consultation-search"
                            type="search"
                            placeholder="Code or room..."
                            class="min-h-10 min-w-[180px] pl-8"
                            bind:value={filterSearch}
                            oninput={onSearchInput}
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
                            filterStatus = v ?? '';
                            applyFilters();
                        }}
                    >
                        <Select.Trigger
                            id="consultation-status"
                            class="min-h-10 w-[160px]"
                        >
                            {#if filterStatus}
                                {statusLabel(filterStatus)}
                            {:else}
                                <span class="text-muted-foreground"
                                    >All statuses</span
                                >
                            {/if}
                        </Select.Trigger>
                        <Select.Content>
                            <Select.Item value="" label="All statuses"
                                >All statuses</Select.Item
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
            </div>

            <SwitchableListView bind:viewMode overflow="auto">
                {#snippet table()}
                    <Table.Root class="w-full min-w-[820px] text-sm">
                        <Table.Header class="bg-muted/50">
                            <Table.Row>
                                <Table.Head>When</Table.Head>
                                <Table.Head>Room</Table.Head>
                                <Table.Head>Code</Table.Head>
                                <Table.Head>Status</Table.Head>
                                <Table.Head>Booking</Table.Head>
                                <Table.Head>Assigned</Table.Head>
                                <Table.Head class="text-center"
                                    >Actions</Table.Head
                                >
                            </Table.Row>
                        </Table.Header>
                        <Table.Body>
                            {#each sessions as session (session.id)}
                                <Table.Row
                                    class="hover:bg-muted/30 cursor-pointer"
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
                                    <Table.Cell
                                        >{formatScheduledAt(
                                            session.scheduled_at,
                                        )}</Table.Cell
                                    >
                                    <Table.Cell
                                        >{session.room?.name ??
                                            session.room?.code ??
                                            '—'}</Table.Cell
                                    >
                                    <Table.Cell
                                        class="font-medium tracking-tight"
                                        >{session.code}</Table.Cell
                                    >
                                    <Table.Cell>
                                        <Badge
                                            variant={statusVariant(
                                                session.status,
                                            )}
                                            >{statusLabel(
                                                session.status,
                                            )}</Badge
                                        >
                                    </Table.Cell>
                                    <Table.Cell>
                                        <Badge
                                            variant={session.booking_mode ===
                                            'open_for_reservation'
                                                ? 'default'
                                                : 'outline'}
                                        >
                                            {bookingModeLabel(
                                                session.booking_mode,
                                            )}
                                        </Badge>
                                    </Table.Cell>
                                    <Table.Cell>
                                        {session.assigned_count ??
                                            0}{#if session.room?.capacity}
                                            / {session.room.capacity}{/if}
                                    </Table.Cell>
                                    <Table.Cell
                                        class="text-center"
                                        data-action-cell
                                    >
                                        <div
                                            class="inline-flex flex-wrap justify-center gap-1"
                                        >
                                            <Link
                                                href={`/consultation/sessions/${session.id}`}
                                            >
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    class="h-8 px-2 text-xs font-semibold"
                                                >
                                                    <Eye
                                                        class="mr-1.5 size-3.5"
                                                    />
                                                    Show
                                                </Button>
                                            </Link>
                                            {#if session.status === 'draft'}
                                                <Link
                                                    href={`/consultation/sessions/${session.id}/edit`}
                                                >
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        class="h-8 px-2 text-xs font-semibold"
                                                    >
                                                        <Pencil
                                                            class="mr-1.5 size-3.5"
                                                        />
                                                        Edit
                                                    </Button>
                                                </Link>
                                                {#if (session.assigned_count ?? 0) > 0}
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        class="h-8 px-2 text-xs font-semibold"
                                                        onclick={() =>
                                                            publishSession(
                                                                session,
                                                            )}
                                                    >
                                                        <Send
                                                            class="mr-1.5 size-3.5"
                                                        />
                                                        Publish
                                                    </Button>
                                                {/if}
                                            {/if}
                                            {#if session.status !== 'completed' && session.status !== 'cancelled'}
                                                {#if session.booking_mode === 'open_for_reservation'}
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        class="h-8 px-2 text-xs font-semibold"
                                                        onclick={() =>
                                                            closeBooking(
                                                                session,
                                                            )}
                                                    >
                                                        <Lock
                                                            class="mr-1.5 size-3.5"
                                                        />
                                                        Close booking
                                                    </Button>
                                                {:else}
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        class="h-8 px-2 text-xs font-semibold"
                                                        onclick={() =>
                                                            openBooking(
                                                                session,
                                                            )}
                                                    >
                                                        <LockOpen
                                                            class="mr-1.5 size-3.5"
                                                        />
                                                        Open booking
                                                    </Button>
                                                {/if}
                                            {/if}
                                            {#if session.status === 'published' || session.status === 'in_progress'}
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    class="h-8 px-2 text-xs font-semibold"
                                                    onclick={() =>
                                                        completeSession(
                                                            session,
                                                        )}
                                                >
                                                    <CheckCircle
                                                        class="mr-1.5 size-3.5"
                                                    />
                                                    Complete
                                                </Button>
                                            {/if}
                                            {#if session.status !== 'completed' && session.status !== 'cancelled'}
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    class="text-destructive h-8 px-2 text-xs font-semibold"
                                                    onclick={() =>
                                                        promptCancel(session)}
                                                >
                                                    <Ban
                                                        class="mr-1.5 size-3.5"
                                                    />
                                                    Cancel
                                                </Button>
                                            {/if}
                                        </div>
                                    </Table.Cell>
                                </Table.Row>
                            {:else}
                                <Table.Row>
                                    <Table.Cell
                                        colspan={7}
                                        class="text-muted-foreground py-12 text-center"
                                    >
                                        No consultation sessions yet. Create one
                                        to get started.
                                    </Table.Cell>
                                </Table.Row>
                            {/each}
                        </Table.Body>
                    </Table.Root>
                {/snippet}

                {#snippet cards()}
                    {#if sessions.length > 0}
                        <ul
                            class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3"
                            role="list"
                        >
                            {#each sessions as session (session.id)}
                                <li
                                    class="border-border bg-card flex flex-col gap-3 rounded-xl border p-4 shadow-sm"
                                >
                                    <div
                                        class="flex items-start justify-between gap-2"
                                    >
                                        <div class="min-w-0">
                                            <p
                                                class="truncate font-semibold tracking-tight"
                                            >
                                                {session.code}
                                            </p>
                                            <p
                                                class="text-muted-foreground text-sm"
                                            >
                                                {formatScheduledAt(
                                                    session.scheduled_at,
                                                )}
                                            </p>
                                            <p
                                                class="text-muted-foreground text-sm"
                                            >
                                                {session.room?.name ??
                                                    session.room?.code ??
                                                    '—'}
                                            </p>
                                        </div>
                                        <div
                                            class="flex shrink-0 flex-col items-end gap-1"
                                        >
                                            <Badge
                                                variant={statusVariant(
                                                    session.status,
                                                )}
                                            >
                                                {statusLabel(session.status)}
                                            </Badge>
                                            <Badge
                                                variant={session.booking_mode ===
                                                'open_for_reservation'
                                                    ? 'default'
                                                    : 'outline'}
                                            >
                                                {bookingModeLabel(
                                                    session.booking_mode,
                                                )}
                                            </Badge>
                                        </div>
                                    </div>
                                    <p class="text-muted-foreground text-sm">
                                        Assigned {session.assigned_count ??
                                            0}{#if session.room?.capacity}
                                            / {session.room.capacity}{/if}
                                    </p>
                                    <div
                                        class="mt-auto flex flex-wrap gap-2 pt-2"
                                    >
                                        <Link
                                            href={`/consultation/sessions/${session.id}`}
                                            class="flex-1"
                                        >
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                class="min-h-10 w-full font-semibold"
                                            >
                                                <Eye class="mr-1.5 size-3.5" />
                                                Show
                                            </Button>
                                        </Link>
                                        {#if session.status === 'draft'}
                                            <Link
                                                href={`/consultation/sessions/${session.id}/edit`}
                                                class="flex-1"
                                            >
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    class="min-h-10 w-full font-semibold"
                                                >
                                                    <Pencil
                                                        class="mr-1.5 size-3.5"
                                                    />
                                                    Edit
                                                </Button>
                                            </Link>
                                        {/if}
                                    </div>
                                </li>
                            {/each}
                        </ul>
                    {:else}
                        <p class="text-muted-foreground py-12 text-center">
                            No consultation sessions yet. Create one to get
                            started.
                        </p>
                    {/if}
                {/snippet}
            </SwitchableListView>
        {/if}
    </div>

    <Dialog.Root bind:open={cancelDialogOpen}>
        <Dialog.Content class="sm:max-w-md">
            <Dialog.Header>
                <Dialog.Title>Cancel consultation session</Dialog.Title>
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
</StaffLayout>
