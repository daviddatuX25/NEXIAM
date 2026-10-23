<script>
    /**
     * Create exam session — separate scheduling page with room-compatible seating selection.
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
        cycles = [],
        rooms = [],
        test_kits = [],
        seating_arrangements = [],
        arrangement_preview = [],
        default_cycle_id = null,
        session_code_suggestions = {},
        cycle_exam_notification_policy = null,
        enable_seating_arrangements = false,
        can_manage_test_kits = true,
    } = $props();

    const defaultDate = new Date();
    defaultDate.setDate(defaultDate.getDate() + 1);
    const formattedDefaultDate = `${defaultDate.getFullYear()}-${String(defaultDate.getMonth() + 1).padStart(2, '0')}-${String(defaultDate.getDate()).padStart(2, '0')}`;

    const defaultKitId =
        test_kits.find((kit) => kit.status === 'active')?.id ??
        test_kits[0]?.id ??
        '';

    const initialCycleId = default_cycle_id ? String(default_cycle_id) : '';
    const form = useForm({
        academic_cycle_id: initialCycleId,
        room_id: '',
        code: session_code_suggestions[initialCycleId] ?? '',
        date: formattedDefaultDate,
        start_time: '08:00',
        end_time: '09:30',
        test_kit_version_id: defaultKitId ? String(defaultKitId) : '',
        auto_assign_enabled: false,
        seating_mode: 'simple_capacity',
        seating_arrangement_id: '',
        use_cycle_notification_defaults: true,
        notification_policy: {
            publication_email_enabled: cycle_exam_notification_policy?.publication_email_enabled ?? true,
            reminder_offsets_minutes: cycle_exam_notification_policy?.reminder_offsets_minutes ?? [1440],
            reminder_email_enabled: cycle_exam_notification_policy?.reminder_email_enabled ?? true,
        },
    });
    let codeWasManuallyEdited = $state(false);

    $effect(() => {
        const cycleId = String(form.academic_cycle_id || '');
        if (!codeWasManuallyEdited) {
            form.code = session_code_suggestions[cycleId] ?? '';
        }
    });

    function markCodeAsManuallyEdited() {
        codeWasManuallyEdited = true;
    }

    let customizeNotifications = $state(false);

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
    function setSeatingMode(mode) {
        form.seating_mode = mode;
        if (mode !== 'assigned_seating') {
            form.seating_arrangement_id = '';

            return;
        }

        const defaultId =
            selectedRoom?.default_seating_arrangement_id ??
            selectedRoom?.default_seating_arrangement?.id;
        if (
            defaultId &&
            compatibleArrangements.some(
                (arrangement) => String(arrangement.id) === String(defaultId),
            )
        ) {
            form.seating_arrangement_id = String(defaultId);
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

    $effect(() => {
        const roomId = String(form.room_id || '');
        if (!enable_seating_arrangements || form.seating_mode !== 'assigned_seating') {
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

    function submit(event) {
        event.preventDefault();
        form.post('/exams/sessions');
    }

    const breadcrumbs = [
        { label: 'Exam Scheduling', href: '/exams/sessions' },
        { label: 'Create' },
    ];
</script>

<svelte:head><title>Create Exam Session</title></svelte:head>

<StaffLayout {breadcrumbs}>
    <div class="min-w-0 space-y-6 p-4 sm:p-6">
        <PageHeaderBar
            title="Create Exam Session"
            description="Configure venue, seating, and materials. New sessions save as Drafts until published from the Exam Scheduling list."
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
                                <option value="">Select academic year</option>
                                {#each cycles as cycle (cycle.id)}<option
                                        value={String(cycle.id)}
                                        >{cycle.label}{cycle.is_active
                                            ? ' (Active)'
                                            : ''}</option
                                    >{/each}
                            </select>
                            {#if form.errors.academic_cycle_id}<p
                                    class="text-destructive text-sm"
                                >
                                    {form.errors.academic_cycle_id}
                                </p>{/if}
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
                                {#each rooms as room (room.id)}<option
                                        value={String(room.id)}
                                        >{roomLabel(room)}{room.capacity
                                            ? ` · capacity ${room.capacity}`
                                            : ''}</option
                                    >{/each}
                            </select>
                            {#if form.errors.room_id}<p
                                    class="text-destructive text-sm"
                                >
                                    {form.errors.room_id}
                                </p>{/if}
                        </div>
                        {#if enable_seating_arrangements}
                            <div class="sm:col-span-2 space-y-2">
                                <div class="border-border bg-muted/20 flex flex-wrap items-center justify-between gap-3 rounded-lg border p-3">
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
                            </div>
                        {/if}
                        {#if form.errors.seating_mode}
                            <p class="text-destructive text-sm sm:col-span-2">{form.errors.seating_mode}</p>
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
                                    <option value="">Select arrangement</option>
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
                            {:else if test_kits.length === 0}
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
                                oninput={markCodeAsManuallyEdited}
                                placeholder="e.g. EX-2026-FRI-A"
                                required
                            />
                            <p class="text-muted-foreground text-xs">
                                Unique session identifier within the selected academic year.
                            </p>
                            {#if form.errors.code}<p
                                    class="text-destructive text-sm"
                                >
                                    {form.errors.code}
                                </p>{/if}
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
                            {#if form.errors.starts_at}<p
                                    class="text-destructive text-sm"
                                >
                                    {form.errors.starts_at}
                                </p>{/if}
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
                            {#if form.errors.effective_end_at}<p
                                    class="text-destructive text-sm"
                                >
                                    {form.errors.effective_end_at}
                                </p>{/if}
                        </div>
                    </div>
                    {#if form.errors.schedule}<p
                            class="text-destructive text-sm"
                        >
                            {form.errors.schedule}
                        </p>{/if}
                    {#if form.errors.material}<p
                            class="text-destructive text-sm"
                        >
                            {form.errors.material}
                        </p>{/if}

                    {#if form.booking_mode === 'staff_only'}
                        <div class="border-border bg-muted/20 flex flex-wrap items-center justify-between gap-4 rounded-xl border p-4 sm:p-5">
                            <div class="min-w-0 space-y-1">
                                <div class="flex items-center gap-1.5">
                                    <Label for="auto_assign_enabled" class="text-sm font-semibold cursor-pointer">
                                        Auto-Assign incoming applicants
                                    </Label>
                                    <InfoPopover
                                        title="When Auto-Assign runs"
                                        content="Once this draft session is created, newly accepted applicants are automatically placed into its remaining seats in chronological session order while it remains in Draft. Publishing locks the roster and turns Auto-Assign off."
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
                                    Configure applicant communication for this exam session.
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
                            Choose a room and compatible seating arrangement to preview the physical seats.
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
                    disabled={form.processing}
                >
                    {form.processing ? 'Creating…' : 'Create session'}
                </Button>
                <Link href="/exams/sessions"
                    ><Button type="button" variant="outline" class="min-h-11"
                        >Cancel</Button
                    ></Link
                >
            </div>
        </form>
    </div>
</StaffLayout>
