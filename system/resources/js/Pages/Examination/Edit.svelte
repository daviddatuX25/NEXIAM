<script>
    /**
     * Edit draft exam session — legacy Admin/TestScheduling/Edit shape (separate page).
     */
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
    import InfoPopover from '@/Components/kit/InfoPopover.svelte';
    import SeatingArrangementPreview from '@/Pages/Setup/SeatingArrangements/SeatingArrangementPreview.svelte';
    import { Button } from '@/Components/ui/button';
    import { Input } from '@/Components/ui/input';
    import { Label } from '@/Components/ui/label';
    import { Switch } from '@/Components/ui/switch';
    import { Link, useForm } from '@inertiajs/svelte';
    import { Bell } from 'lucide-svelte';

    let {
        session,
        cycles = [],
        rooms = [],
        test_kits = [],
        seating_arrangements = [],
        arrangement_preview = [],
        enable_seating_arrangements = false,
        cycle_exam_notification_policy = null,
        can_manage_test_kits = true,
    } = $props();

    const form = useForm({
        academic_cycle_id: String(session.academic_cycle_id ?? ''),
        room_id: String(session.room_id ?? ''),
        code: session.code ?? '',
        date: session.date ?? '',
        start_time: session.start_time ?? '',
        end_time: session.end_time ?? '',
        booking_mode: session.booking_mode ?? 'staff_only',
        auto_assign_enabled: Boolean(session.auto_assign_enabled ?? false),
        expected_version: session.version ?? 1,
        test_kit_version_id: session.test_kit_version_id ? String(session.test_kit_version_id) : '',
        seating_mode: session.seating_mode ?? '',
        seating_arrangement_id:
            session.seating_mode === null || session.seating_mode === undefined
                ? ''
                : session.seating_arrangement_id
                  ? String(session.seating_arrangement_id)
                  : '',
        confirm_clear_seating_arrangement: false,
        use_cycle_notification_defaults: session.notification_policy === null || session.notification_policy === undefined,
        notification_policy: {
            publication_email_enabled: session.notification_policy?.publication_email_enabled
                ?? cycle_exam_notification_policy?.publication_email_enabled
                ?? true,
            reminder_offsets_minutes: session.notification_policy?.reminder_offsets_minutes
                ?? cycle_exam_notification_policy?.reminder_offsets_minutes
                ?? [1440],
            reminder_email_enabled: session.notification_policy?.reminder_email_enabled
                ?? cycle_exam_notification_policy?.reminder_email_enabled
                ?? true,
        },
    });

    let customizeNotifications = $state(session.notification_policy !== null && session.notification_policy !== undefined);

    const reminderOptions = [
        { minutes: 4320, label: '72 hours before' },
        { minutes: 1440, label: '24 hours before' },
        { minutes: 120, label: '2 hours before' },
        { minutes: 60, label: '1 hour before' },
    ];

    function toggleReminder(minutes) {
        const selected = form.notification_policy.reminder_offsets_minutes;
        form.notification_policy.reminder_offsets_minutes = selected.includes(minutes)
            ? selected.filter((offset) => offset !== minutes)
            : [...selected, minutes].sort((left, right) => right - left);
    }

    $effect(() => {
        form.use_cycle_notification_defaults = !customizeNotifications;
    });

    let lastRoomId = $state(form.room_id);

    const selectedRoom = $derived(
        rooms.find((room) => String(room.id) === String(form.room_id)) ?? null,
    );
    const selectedKit = $derived(
        test_kits.find(
            (kit) => String(kit.id) === String(form.test_kit_version_id),
        ) ?? null,
    );


    const compatibleArrangements = $derived.by(() => {
        const roomId = String(form.room_id || '');
        return seating_arrangements.filter((arrangement) => {
            if (
                arrangement.is_active === false ||
                arrangement.is_compatible === false
            )
                return false;
            if (!roomId) return true;
            if (arrangement.room_id && String(arrangement.room_id) !== roomId)
                return false;
            const roomIds =
                arrangement.room_ids ??
                (arrangement.rooms ?? []).map((room) =>
                    typeof room === 'object' ? room.id : room,
                );
            if (
                arrangement.availability_mode === 'selected_rooms' &&
                Array.isArray(roomIds) &&
                roomIds.length > 0 &&
                !roomIds.map(String).includes(roomId)
            )
                return false;
            return true;
        });
    });

    const incompatibleArrangements = $derived(
        seating_arrangements.filter(
            (arrangement) =>
                arrangement.is_active !== false &&
                arrangement.is_compatible === false,
        ),
    );

    const selectedArrangement = $derived(
        seating_arrangements.find(
            (arrangement) =>
                String(arrangement.id) === String(form.seating_arrangement_id),
        ) ?? null,
    );

    const retainedArrangement = $derived(
        session.material?.seating_arrangement ?? null,
    );

    function setSeatingMode(mode) {
        form.seating_mode = mode;
        if (mode === 'simple_capacity') {
            form.seating_arrangement_id = '';
            form.confirm_clear_seating_arrangement = false;

            return;
        }

        form.confirm_clear_seating_arrangement = false;
        if (session.seating_mode === null || session.seating_mode === undefined) {
            form.seating_arrangement_id = '';

            return;
        }
        if (
            session.seating_mode === 'assigned_seating' &&
            session.seating_arrangement_id
        ) {
            form.seating_arrangement_id = String(session.seating_arrangement_id);

            return;
        }
        if (session.seating_mode === 'simple_capacity') {
            const defaultId =
                selectedRoom?.default_seating_arrangement_id ??
                selectedRoom?.default_seating_arrangement?.id;
            form.seating_arrangement_id =
                defaultId &&
                compatibleArrangements.some(
                    (arrangement) => String(arrangement.id) === String(defaultId),
                )
                    ? String(defaultId)
                    : '';
        }
    }

    const previewDesks = $derived.by(() => {
        if (
            selectedArrangement?.preview &&
            Array.isArray(selectedArrangement.preview)
        )
            return selectedArrangement.preview;
        if (
            selectedArrangement?.desks &&
            Array.isArray(selectedArrangement.desks)
        )
            return selectedArrangement.desks;
        if (Array.isArray(arrangement_preview)) return arrangement_preview;
        if (arrangement_preview && selectedArrangement?.id)
            return arrangement_preview[selectedArrangement.id] ?? [];
        return [];
    });

    const arrangementError = $derived(
        form.errors.seating_arrangement_id ||
            form.errors.arrangement ||
            form.errors.material ||
            null,
    );
    const clearConfirmationMissing = $derived(
        session.seating_arrangement_id !== null &&
            session.seating_arrangement_id !== undefined &&
            session.seating_mode !== 'simple_capacity' &&
            form.seating_mode === 'simple_capacity' &&
            !form.confirm_clear_seating_arrangement,
    );

    $effect(() => {
        const roomId = String(form.room_id || '');
        if (!enable_seating_arrangements) {
            lastRoomId = roomId;

            return;
        }
        if (form.seating_mode !== 'assigned_seating') {
            form.seating_arrangement_id = '';
            lastRoomId = roomId;

            return;
        }
        if (roomId === lastRoomId) return;
        lastRoomId = roomId;
        const room = rooms.find((candidate) => String(candidate.id) === roomId);
        const defaultId =
            room?.default_seating_arrangement_id ??
            room?.default_seating_arrangement?.id;
        if (
            defaultId &&
            compatibleArrangements.some(
                (arrangement) => String(arrangement.id) === String(defaultId),
            )
        ) {
            form.seating_arrangement_id = String(defaultId);
        } else if (
            !compatibleArrangements.some(
                (arrangement) =>
                    String(arrangement.id) ===
                    String(form.seating_arrangement_id),
            )
        ) {
            form.seating_arrangement_id = '';
        }
    });

    function roomLabel(room) {
        return room.label ?? room.name ?? room.code ?? `Room ${room.id}`;
    }

    function arrangementLabel(arrangement) {
        return (
            arrangement.label ??
            arrangement.name ??
            `Arrangement ${arrangement.id}`
        );
    }

    function submit(e) {
        e.preventDefault();
        form.put(`/exams/sessions/${session.id}`);
    }

    const breadcrumbs = [
        { label: 'Exam Scheduling', href: '/exams/sessions' },
        {
            label: session.code ?? 'Session',
            href: `/exams/sessions/${session.id}`,
        },
        { label: 'Edit' },
    ];
</script>

<svelte:head><title>Edit Exam Session</title></svelte:head>

<StaffLayout {breadcrumbs}>
    <div class="min-w-0 space-y-6 p-4 sm:p-6">
        <PageHeaderBar
            title="Edit Exam Session"
            description="Update venue, seating, materials, and reminder settings for this exam session."
        />

        <form class="space-y-6" onsubmit={submit}>
            <div
                class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(22rem,0.8fr)]"
            >
                <div
                    class="border-border bg-card min-w-0 space-y-5 rounded-xl border p-5 sm:p-6"
                >
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-1.5 sm:col-span-2">
                            <Label for="academic_cycle_id">Academic year</Label>
                            <select
                                id="academic_cycle_id"
                                class="border-input bg-background flex min-h-11 w-full rounded-md border px-3 text-sm"
                                bind:value={form.academic_cycle_id}
                                required
                            >
                                {#each cycles as cycle}
                                    <option value={String(cycle.id)}
                                        >{cycle.label}</option
                                    >
                                {/each}
                            </select>
                        </div>

                        <div class="space-y-1.5 sm:col-span-2">
                            <Label for="room_id">Room</Label>
                            <select
                                id="room_id"
                                class="border-input bg-background flex min-h-11 w-full rounded-md border px-3 text-sm"
                                bind:value={form.room_id}
                                required
                            >
                                <option value="">Select room</option>
                                {#each rooms as room}
                                    <option value={String(room.id)}
                                        >{roomLabel(room)}{room.capacity
                                            ? ` · capacity ${room.capacity}`
                                            : ''}</option
                                    >
                                {/each}
                            </select>
                        </div>

                        {#if session.seating_mode === null || session.seating_mode === undefined}
                            <fieldset class="sm:col-span-2 space-y-3 rounded-lg border border-amber-500/40 bg-amber-500/5 p-4">
                                <legend class="px-1 text-sm font-semibold">Seating mode needs review</legend>
                                <p class="text-muted-foreground text-sm">
                                    This older draft has no recorded seating mode. Its saved arrangement alone does not establish physical-seat intent; choose how this draft should continue.
                                </p>
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <label class="border-border bg-card flex min-h-11 cursor-pointer items-center gap-2 rounded-md border px-3 text-sm">
                                        <input
                                            type="radio"
                                            name="seating_mode"
                                            checked={form.seating_mode === 'simple_capacity'}
                                            onchange={() => setSeatingMode('simple_capacity')}
                                            class="accent-primary"
                                        />
                                        <span>Use room capacity</span>
                                    </label>
                                    {#if enable_seating_arrangements}
                                        <label class="border-border bg-card flex min-h-11 cursor-pointer items-center gap-2 rounded-md border px-3 text-sm">
                                            <input
                                                type="radio"
                                                name="seating_mode"
                                                checked={form.seating_mode === 'assigned_seating'}
                                                onchange={() => setSeatingMode('assigned_seating')}
                                                class="accent-primary"
                                            />
                                            <span>Assign physical seats</span>
                                        </label>
                                    {:else}
                                        <p class="text-muted-foreground self-center text-xs">
                                            Assigned seating is unavailable for new session choices.
                                        </p>
                                    {/if}
                                </div>
                            </fieldset>
                        {:else if enable_seating_arrangements}
                            <div class="border-border bg-muted/20 flex flex-wrap items-center justify-between gap-3 rounded-lg border p-3 sm:col-span-2">
                                <div class="flex min-w-0 items-center gap-1.5">
                                    <p class="text-sm font-medium">Seating mode</p>
                                    <InfoPopover
                                        title="Simple capacity vs. Physical seats"
                                        content="When off (Simple capacity), the session tracks total headcount up to the room's capacity without assigning seat numbers. When on (Assign physical seats), the session locks a desk grid and distributes booklet variants (e.g., A/B) by seat."
                                    />
                                </div>
                                <label for="assign_physical_seats" class="flex min-h-11 shrink-0 cursor-pointer items-center gap-2 text-sm font-medium">
                                    <input
                                        id="assign_physical_seats"
                                        type="checkbox"
                                        role="switch"
                                        checked={form.seating_mode === 'assigned_seating'}
                                        aria-checked={form.seating_mode === 'assigned_seating'}
                                        class="accent-primary size-4 rounded focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                                        onchange={(event) => setSeatingMode(event.currentTarget.checked ? 'assigned_seating' : 'simple_capacity')}
                                    />
                                    <span>Assign physical seats</span>
                                </label>
                            </div>
                        {:else if session.seating_mode === 'assigned_seating'}
                            <div class="border-border bg-muted/20 flex flex-wrap items-center justify-between gap-3 rounded-lg border p-4 sm:col-span-2" role="status">
                                <div class="min-w-0 space-y-1">
                                    <p class="text-sm font-medium">Assigned seating retained on this draft</p>
                                    <p class="text-muted-foreground text-xs">
                                        Availability is off for new choices. The saved arrangement stays with this draft; re-enable assigned seating before publishing.
                                    </p>
                                    {#if retainedArrangement}
                                        <p class="text-xs font-medium">
                                            {retainedArrangement.name} · {retainedArrangement.desk_count ?? '—'} desks
                                        </p>
                                    {/if}
                                </div>
                                <Button type="button" variant="outline" class="min-h-10" onclick={() => setSeatingMode('simple_capacity')}>
                                    Use room capacity instead
                                </Button>
                            </div>
                        {/if}
                        {#if form.errors.seating_mode}
                            <p class="text-destructive text-sm sm:col-span-2">{form.errors.seating_mode}</p>
                        {/if}
                        {#if session.seating_arrangement_id && session.seating_mode !== 'simple_capacity' && form.seating_mode === 'simple_capacity'}
                            <label class="border-border bg-card flex items-start gap-3 rounded-lg border p-3 text-sm sm:col-span-2">
                                <input
                                    type="checkbox"
                                    bind:checked={form.confirm_clear_seating_arrangement}
                                    class="mt-0.5 size-4 accent-primary"
                                />
                                <span class="space-y-0.5">
                                    <span class="block font-medium">Confirm removing this draft's seating selection</span>
                                    <span class="text-muted-foreground block text-xs">
                                        This clears the arrangement from this draft only. The reusable room arrangement remains in setup.
                                    </span>
                                </span>
                            </label>
                        {/if}
                        {#if enable_seating_arrangements && form.seating_mode === 'assigned_seating'}
                            <div class="space-y-1.5 sm:col-span-2">
                                <div class="flex items-center gap-1.5">
                                    <Label for="seating_arrangement_id">Seating arrangement</Label>
                                    <InfoPopover
                                        title="Room compatibility filter"
                                        content="Only active arrangements permitted for the selected room whose total desks (rows × columns) fit within the room's capacity are listed."
                                    />
                                </div>
                                <select
                                    id="seating_arrangement_id"
                                    class="border-input bg-background flex min-h-11 w-full rounded-md border px-3 text-sm"
                                    bind:value={form.seating_arrangement_id}
                                >
                                    <option value="">Select later</option>
                                    {#each compatibleArrangements as arrangement (arrangement.id)}
                                        <option value={String(arrangement.id)}>
                                            {arrangementLabel(arrangement)} · {arrangement.desk_count ??
                                                (arrangement.rows ?? 0) * (arrangement.columns ?? 0)} desks
                                        </option>
                                    {/each}
                                </select>
                                {#if !form.room_id}
                                    <p class="text-muted-foreground text-xs">
                                        Select a room first to check applicability and capacity.
                                    </p>
                                {/if}
                                {#if form.room_id && compatibleArrangements.length === 0}
                                    <p class="text-destructive text-sm">No active seating arrangement is applicable to this room.</p>
                                {/if}
                                {#if arrangementError}
                                    <p class="text-destructive text-sm">{arrangementError}</p>
                                {/if}
                            </div>
                        {/if}

                        <div class="space-y-2 sm:col-span-2">
                            <Label for="test_kit_version_id">Test Kit</Label>
                            <select
                                id="test_kit_version_id"
                                class="border-input bg-background flex min-h-11 w-full min-w-0 rounded-md border px-3 text-sm"
                                bind:value={form.test_kit_version_id}
                            >
                                <option value="">Not attached</option>
                                {#if session.test_kit_version_id && !test_kits.some((kit) => String(kit.id) === String(session.test_kit_version_id))}
                                    <option value={String(session.test_kit_version_id)}>Current Test Kit · unavailable for new selection</option>
                                {/if}
                                {#each test_kits as kit (kit.id)}
                                    <option value={String(kit.id)}>{kit.label} · {kit.status}</option>
                                {/each}
                            </select>
                            {#if selectedKit}
                                <dl class="grid min-w-0 grid-cols-1 gap-2 text-sm sm:grid-cols-2">
                                    <div class="min-w-0"><dt class="text-xs text-muted-foreground">Booklet</dt><dd class="break-words">{selectedKit.booklet_label}</dd></div>
                                    <div class="min-w-0"><dt class="text-xs text-muted-foreground">Answer Sheet</dt><dd class="break-words">{selectedKit.answer_sheet_label}</dd></div>
                                    <div class="min-w-0 sm:col-span-2"><dt class="text-xs text-muted-foreground">Test Setup</dt><dd class="break-words">{selectedKit.profile_label}</dd></div>
                                </dl>
                            {:else if test_kits.length === 0 && !session.test_kit_version_id}
                                <span class="text-sm text-muted-foreground">No Test Kit versions available</span>
                            {/if}
                            {#if can_manage_test_kits}
                                <Link href="/setup/exam-materials/test-kits" class="inline-flex min-h-11 items-center text-sm font-medium underline underline-offset-4">Open Test Kits</Link>
                            {/if}
                            {#if form.errors.test_kit_version_id}
                                <p class="text-destructive text-sm">{form.errors.test_kit_version_id}</p>
                            {/if}
                        </div>

                        <div class="space-y-1.5 sm:col-span-2">
                            <Label for="code">Session code</Label>
                            <Input
                                id="code"
                                class="min-h-11"
                                bind:value={form.code}
                                required
                            />
                            <p class="text-muted-foreground text-xs">
                                Unique session identifier within the selected academic year.
                            </p>
                            {#if form.errors.code}
                                <p class="text-destructive text-sm">
                                    {form.errors.code}
                                </p>
                            {/if}
                        </div>

                        <div class="space-y-1.5 sm:col-span-2">
                            <Label for="date">Date</Label>
                            <Input
                                id="date"
                                type="date"
                                class="min-h-11"
                                bind:value={form.date}
                                required
                            />
                        </div>

                        <div class="space-y-1.5">
                            <Label for="start_time">Start time</Label>
                            <Input
                                id="start_time"
                                type="time"
                                class="min-h-11"
                                bind:value={form.start_time}
                                required
                            />
                        </div>

                        <div class="space-y-1.5">
                            <Label for="end_time">End time</Label>
                            <Input
                                id="end_time"
                                type="time"
                                class="min-h-11"
                                bind:value={form.end_time}
                                required
                            />
                        </div>
                    </div>

                    {#if form.errors.schedule}
                        <p class="text-destructive text-sm">
                            {form.errors.schedule}
                        </p>
                    {/if}
                    {#if form.errors.material}
                        <p class="text-destructive text-sm">
                            {form.errors.material}
                        </p>
                    {/if}

                    {#if form.booking_mode === 'staff_only'}
                        <div class="border-border bg-muted/20 flex flex-wrap items-center justify-between gap-4 rounded-xl border p-4 sm:p-5">
                            <div class="min-w-0 space-y-1">
                                <div class="flex items-center gap-1.5">
                                    <Label for="auto_assign_enabled" class="text-sm font-semibold cursor-pointer">
                                        Auto-Assign incoming applicants
                                    </Label>
                                    <InfoPopover
                                        title="When Auto-Assign runs"
                                        content="While this session is in Draft, newly accepted applicants are automatically placed into its remaining seats in chronological session order. Publishing locks the roster and turns Auto-Assign off."
                                    />
                                </div>
                                <p id="auto-assign-help" class="text-muted-foreground text-xs leading-relaxed">
                                    Automatically schedule newly accepted candidates into remaining seats.
                                </p>
                            </div>
                            <div class="flex items-center gap-3">
                                <Switch
                                    id="auto_assign_enabled"
                                    bind:checked={form.auto_assign_enabled}
                                    aria-describedby="auto-assign-help"
                                />
                                <span class="text-xs font-semibold w-7 text-right {form.auto_assign_enabled ? 'text-primary' : 'text-muted-foreground'}">
                                    {form.auto_assign_enabled ? 'ON' : 'OFF'}
                                </span>
                            </div>
                        </div>
                    {/if}

                    <!-- Schedule Notifications & Reminders Card -->
                    <div class="border-border bg-muted/20 min-w-0 space-y-4 rounded-xl border p-4 sm:p-5">
                        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-border/60 pb-3">
                            <div class="space-y-0.5">
                                <div class="flex items-center gap-1.5">
                                    <h3 class="text-sm font-semibold flex items-center gap-2">
                                        <Bell class="size-4 text-primary" />
                                        Schedule Notifications & Reminders
                                    </h3>
                                    <InfoPopover
                                        title="Portal notices vs. Email delivery"
                                        content="In-app portal schedule notices are always posted when a session is published. These settings control whether external email copies and automated countdown reminders are also sent before Start time."
                                    />
                                </div>
                                <p class="text-xs text-muted-foreground">
                                    Configure applicant communication for this draft exam session.
                                </p>
                            </div>
                            <label class="flex items-center gap-2 cursor-pointer text-xs font-medium">
                                <input
                                    type="checkbox"
                                    bind:checked={customizeNotifications}
                                    class="size-4 rounded border-border accent-primary"
                                />
                                Customize for this session
                            </label>
                        </div>

                        {#if !customizeNotifications}
                            <div class="rounded-lg bg-muted/40 p-3.5 text-xs space-y-1.5 border border-border/50">
                                <p class="font-medium text-foreground">Using Academic Year Defaults:</p>
                                <ul class="list-disc list-inside text-muted-foreground space-y-0.5">
                                    <li>Email on publish: {cycle_exam_notification_policy?.publication_email_enabled ?? true ? 'Enabled' : 'Disabled'}</li>
                                    <li>Reminders: {(cycle_exam_notification_policy?.reminder_offsets_minutes ?? [1440]).map((m) => m === 4320 ? '72h' : m === 1440 ? '24h' : m === 120 ? '2h' : '1h').join(', ')} before</li>
                                    <li>Reminder email copy: {cycle_exam_notification_policy?.reminder_email_enabled ?? true ? 'Enabled' : 'Disabled'}</li>
                                </ul>
                            </div>
                        {:else}
                            <div class="space-y-4 pt-1">
                                <label class="flex items-start gap-3 rounded-lg border border-border/70 p-3 cursor-pointer hover:bg-muted/30 transition-colors">
                                    <input
                                        type="checkbox"
                                        bind:checked={form.notification_policy.publication_email_enabled}
                                        class="mt-0.5 size-4 accent-primary"
                                    />
                                    <span class="space-y-0.5">
                                        <span class="block text-xs font-semibold">Email assigned candidates when published</span>
                                        <span class="block text-[11px] text-muted-foreground">Portal schedule notices remain permanent. Uncheck to omit external email delivery.</span>
                                    </span>
                                </label>

                                <fieldset class="space-y-2 rounded-lg border border-border/70 p-3">
                                    <legend class="px-1 text-xs font-semibold">Automated countdown reminders</legend>
                                    <div class="grid gap-2 sm:grid-cols-2">
                                        {#each reminderOptions as opt}
                                            <label class="flex items-center gap-2.5 text-xs cursor-pointer">
                                                <input
                                                    type="checkbox"
                                                    checked={form.notification_policy.reminder_offsets_minutes.includes(opt.minutes)}
                                                    onchange={() => toggleReminder(opt.minutes)}
                                                    class="size-4 rounded accent-primary"
                                                />
                                                <span>{opt.label}</span>
                                            </label>
                                        {/each}
                                    </div>
                                </fieldset>

                                <label class="flex items-start gap-3 rounded-lg border border-border/70 p-3 cursor-pointer hover:bg-muted/30 transition-colors">
                                    <input
                                        type="checkbox"
                                        bind:checked={form.notification_policy.reminder_email_enabled}
                                        class="mt-0.5 size-4 accent-primary"
                                    />
                                    <span class="space-y-0.5">
                                        <span class="block text-xs font-semibold">Send email copy with countdown reminders</span>
                                        <span class="block text-[11px] text-muted-foreground">Candidates will always receive in-app reminders; uncheck to omit email copy.</span>
                                    </span>
                                </label>
                            </div>
                        {/if}
                    </div>
                </div>

                <div class="space-y-4 xl:sticky xl:top-24 xl:self-start">
                    {#if form.seating_mode === 'simple_capacity'}
                        <div class="border-border bg-card rounded-xl border p-5 text-sm space-y-3">
                            <div>
                                <p class="font-medium">
                                    {selectedRoom ? roomLabel(selectedRoom) : 'Session summary'}
                                </p>
                                <p class="text-muted-foreground mt-1">
                                    {selectedRoom
                                        ? `Room capacity: ${selectedRoom.capacity ?? '—'} seats`
                                        : 'Select a room to view capacity.'}
                                </p>
                            </div>
                            <div class="flex items-center justify-between py-1.5 border-t border-border/50 text-xs">
                                <span class="text-muted-foreground">Auto-Assign</span>
                                <span class="font-medium {form.auto_assign_enabled ? 'text-primary font-semibold' : 'text-muted-foreground'}">
                                    {form.auto_assign_enabled ? 'Enabled' : 'Disabled'}
                                </span>
                            </div>
                        </div>
                    {:else if form.seating_mode === 'assigned_seating' && !enable_seating_arrangements}
                        <div class="border-border bg-muted/20 rounded-xl border p-5 text-sm" role="status">
                            <p class="font-medium">Assigned seating retained</p>
                            <p class="text-muted-foreground mt-1">
                                This draft keeps its saved physical-seat choice. Availability is off for new changes and publication.
                            </p>
                            {#if retainedArrangement}
                                <p class="mt-2 text-xs font-medium">{retainedArrangement.name}</p>
                            {/if}
                        </div>
                    {:else if form.seating_mode === 'assigned_seating' && selectedArrangement}
                        <SeatingArrangementPreview
                            rows={selectedArrangement.rows}
                            columns={selectedArrangement.columns}
                            booklet_pattern={selectedArrangement.booklet_pattern}
                            desks={previewDesks}
                            compact
                            title="Session seating preview"
                        />
                        <div class="border-border bg-card rounded-xl border p-5 text-sm space-y-3">
                            <div>
                                <p class="font-medium">{arrangementLabel(selectedArrangement)}</p>
                                <p class="text-muted-foreground mt-1">
                                    {selectedArrangement.rows} rows × {selectedArrangement.columns} columns · {selectedArrangement.desk_count ??
                                        (selectedArrangement.rows ?? 0) * (selectedArrangement.columns ?? 0)} desks
                                </p>
                                {#if selectedRoom}
                                    <p class="text-muted-foreground mt-1">Room capacity: {selectedRoom.capacity ?? '—'}</p>
                                {/if}
                            </div>
                            <div class="flex items-center justify-between py-1.5 border-t border-border/50 text-xs">
                                <span class="text-muted-foreground">Auto-Assign</span>
                                <span class="font-medium {form.auto_assign_enabled ? 'text-primary font-semibold' : 'text-muted-foreground'}">
                                    {form.auto_assign_enabled ? 'Enabled' : 'Disabled'}
                                </span>
                            </div>
                        </div>
                    {:else if form.seating_mode === 'assigned_seating'}
                        <div class="border-border bg-card text-muted-foreground rounded-xl border border-dashed p-6 text-center text-sm" role="status">
                            Choose a compatible arrangement to preview physical seats. The draft may be saved before the choice is complete.
                        </div>
                    {:else}
                        <div class="border-border bg-card text-muted-foreground rounded-xl border border-dashed p-6 text-center text-sm" role="status">
                            Choose how this draft uses the room before saving.
                        </div>
                    {/if}
                    {#if enable_seating_arrangements && form.seating_mode === 'assigned_seating' && incompatibleArrangements.length > 0}
                        <div
                            class="rounded-xl border border-amber-500/40 bg-amber-500/10 px-4 py-3 text-sm"
                            role="status"
                        >
                            {incompatibleArrangements.length} active {incompatibleArrangements.length ===
                            1
                                ? 'arrangement is'
                                : 'arrangements are'} incompatible with the selected
                            room and hidden from this list.
                        </div>
                    {/if}
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <Button
                    type="submit"
                    class="min-h-11"
                    disabled={form.processing || !form.seating_mode || clearConfirmationMissing}
                >
                    {form.processing ? 'Saving...' : 'Save'}
                </Button>
                <Link href={`/exams/sessions/${session.id}`}>
                    <Button type="button" variant="outline" class="min-h-11"
                        >Cancel</Button
                    >
                </Link>
            </div>
        </form>
    </div>
</StaffLayout>
