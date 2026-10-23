<script>
    /**
     * Create consultation session — legacy list -> create/edit shape.
     * Explicit booking_mode radio pick (no silent default).
     */
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
    import InfoPopover from '@/Components/kit/InfoPopover.svelte';
    import { Button } from '@/Components/ui/button';
    import { Input } from '@/Components/ui/input';
    import { Label } from '@/Components/ui/label';
    import { Switch } from '@/Components/ui/switch';
    import { Link, useForm } from '@inertiajs/svelte';

    let {
        cycles = [],
        rooms = [],
        default_cycle_id = null,
        session_code_suggestions = {},
        booking_modes = [],
    } = $props();

    const initialCycleId = default_cycle_id ? String(default_cycle_id) : '';
    const form = useForm({
        academic_cycle_id: initialCycleId,
        room_id: '',
        code: session_code_suggestions[initialCycleId] ?? '',
        scheduled_at: '',
        booking_mode: 'staff_only',
        auto_assign_enabled: false,
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

    function submit(e) {
        e.preventDefault();
        form.post('/consultation/sessions');
    }

    const bookingModesList = $derived(
        booking_modes && booking_modes.length > 0
            ? booking_modes
            : [
                  { value: 'staff_only', label: 'Staff-only (office assigns)' },
                  {
                      value: 'open_for_reservation',
                      label: 'Self-booking (students reserve)',
                  },
              ],
    );

    const breadcrumbs = [
        { label: 'Release Day', href: '/consultation' },
        { label: 'Release Day Scheduling', href: '/consultation/sessions' },
        { label: 'Create' },
    ];
    const selectedCycle = $derived(
        cycles.find((cycle) => String(cycle.id) === String(form.academic_cycle_id)) ?? null,
    );
    const selectedRoom = $derived(
        rooms.find((room) => String(room.id) === String(form.room_id)) ?? null,
    );
</script>

<svelte:head><title>Schedule Release Day · NEXIAM</title></svelte:head>

<StaffLayout {breadcrumbs}>
    <div class="min-w-0 space-y-6 p-4 sm:p-6">
        <PageHeaderBar
            title="Schedule Release Day"
            description="Set the date, time, venue, and capacity for a Release Day session."
        />

        <form onsubmit={submit} class="space-y-6">
            <div class="grid gap-6 lg:grid-cols-[minmax(0,1.2fr)_minmax(18rem,0.8fr)] max-w-6xl items-start">
                <div class="border-border bg-card space-y-4 rounded-xl border p-6 shadow-xs">
            <div class="space-y-1.5">
                <Label for="academic_cycle_id">Academic year</Label>
                <select
                    id="academic_cycle_id"
                    class="border-input bg-background flex min-h-10 w-full rounded-md border px-3 text-sm"
                    bind:value={form.academic_cycle_id}
                    required
                >
                    <option value="">Select academic year</option>
                    {#each cycles as cycle (cycle.id)}
                        <option value={String(cycle.id)}
                            >{cycle.label}{cycle.is_active
                                ? ' (Active)'
                                : ''}</option
                        >
                    {/each}
                </select>
                {#if form.errors.academic_cycle_id}
                    <p class="text-destructive text-sm">
                        {form.errors.academic_cycle_id}
                    </p>
                {/if}
            </div>

            <div class="space-y-1.5">
                <Label for="room_id">Room</Label>
                <select
                    id="room_id"
                    class="border-input bg-background flex min-h-10 w-full rounded-md border px-3 text-sm"
                    bind:value={form.room_id}
                    required
                >
                    <option value="">Select room</option>
                    {#each rooms as room (room.id)}
                        <option value={String(room.id)}>
                            {room.label}{room.capacity
                                ? ` (Capacity: ${room.capacity})`
                                : ''}
                        </option>
                    {/each}
                </select>
                {#if form.errors.room_id}
                    <p class="text-destructive text-sm">
                        {form.errors.room_id}
                    </p>
                {/if}
            </div>

            <div class="space-y-1.5">
                <Label for="code">Session code</Label>
                <Input
                    id="code"
                    class="min-h-10"
                    bind:value={form.code}
                    oninput={markCodeAsManuallyEdited}
                    placeholder="e.g. Release-01"
                    required
                />
                <p class="text-muted-foreground text-xs">
                    Unique Release Day session code within the selected academic year.
                </p>
                {#if form.errors.code}
                    <p class="text-destructive text-sm">{form.errors.code}</p>
                {/if}
            </div>

            <div class="space-y-1.5">
                <Label for="scheduled_at">Scheduled date & time</Label>
                <Input
                    id="scheduled_at"
                    type="datetime-local"
                    class="min-h-10"
                    bind:value={form.scheduled_at}
                    required
                />
                {#if form.errors.scheduled_at}
                    <p class="text-destructive text-sm">
                        {form.errors.scheduled_at}
                    </p>
                {/if}
            </div>

            <div class="space-y-2">
                <div class="flex items-center gap-1.5">
                    <Label
                        >Booking mode <span class="text-destructive">*</span></Label
                    >
                    <InfoPopover
                        title="Staff assignment vs. Self-booking"
                        content="Staff-only restricts slot assignment to Guidance staff. Self-booking opens this Release Day session for result-ready applicants to reserve their own appointment slot."
                    />
                </div>
                <p class="text-muted-foreground text-xs">
                    Choose whether staff assigns applicants or students
                    self-book.
                </p>
                <div class="space-y-2 pt-1">
                    {#each bookingModesList as mode (mode.value)}
                        <label
                            class="border-border bg-card hover:bg-muted/50 flex min-h-11 cursor-pointer items-center gap-3 rounded-lg border px-4 py-2.5 transition-colors {form.booking_mode ===
                            mode.value
                                ? 'border-primary bg-primary/5 ring-primary ring-1'
                                : ''}"
                        >
                            <input
                                type="radio"
                                name="booking_mode"
                                value={mode.value}
                                checked={form.booking_mode === mode.value}
                                onchange={() => (form.booking_mode = mode.value)}
                                class="accent-primary size-4"
                                required
                            />
                            <span class="text-sm font-medium">{mode.label}</span>
                        </label>
                    {/each}
                </div>
                {#if form.errors.booking_mode}
                    <p class="text-destructive text-sm">
                        {form.errors.booking_mode}
                    </p>
                {/if}
            </div>

            {#if form.booking_mode === 'staff_only'}
                <div class="border-border bg-muted/20 flex flex-wrap items-center justify-between gap-4 rounded-xl border p-4 sm:p-5">
                    <div class="min-w-0 space-y-1">
                        <div class="flex items-center gap-1.5">
                            <Label for="auto_assign_enabled" class="text-sm font-semibold cursor-pointer">
                                Auto-Assign incoming examinees
                            </Label>
                            <InfoPopover
                                title="When Auto-Assign runs"
                                content="Once this draft Release Day session is created, examinees whose exam scores are finalized are automatically placed into its remaining seats while the session remains in Draft."
                            />
                        </div>
                        <p id="auto-assign-help" class="text-muted-foreground text-xs leading-relaxed">
                            Automatically schedule newly result-ready examinees into remaining seats.
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

            {#if form.errors.schedule}
                <p class="text-destructive text-sm">{form.errors.schedule}</p>
            {/if}

            <div class="flex gap-2 pt-2">
                <Button
                    type="submit"
                    class="min-h-10"
                    disabled={form.processing}
                >
                    {form.processing ? 'Saving...' : 'Save draft'}
                </Button>
                <Link href="/consultation/sessions">
                    <Button type="button" variant="outline" class="min-h-10"
                        >Cancel</Button
                    >
                </Link>
                    </div>
                </div>

                <div class="border-border bg-card space-y-5 rounded-xl border p-6 shadow-xs">
                    <div class="space-y-1 border-b border-border pb-3">
                        <h3 class="font-semibold text-sm text-foreground">Session Configuration</h3>
                        <p class="text-xs text-muted-foreground">Live summary of session venue and schedule</p>
                    </div>

                    <div class="space-y-3 text-sm">
                        <div class="flex items-center justify-between py-1.5 border-b border-border/50">
                            <span class="text-muted-foreground">Academic Year</span>
                            <span class="font-medium text-foreground text-right">{selectedCycle?.label ?? 'None selected'}</span>
                        </div>
                        <div class="flex items-center justify-between py-1.5 border-b border-border/50">
                            <span class="text-muted-foreground">Venue</span>
                            <span class="font-medium text-foreground text-right">{selectedRoom?.label ?? selectedRoom?.name ?? 'None selected'}</span>
                        </div>
                        {#if selectedRoom?.capacity}
                            <div class="flex items-center justify-between py-1.5 border-b border-border/50">
                                <span class="text-muted-foreground">Room Capacity</span>
                                <span class="font-medium text-foreground text-right">{selectedRoom.capacity} seats</span>
                            </div>
                        {/if}
                        <div class="flex items-center justify-between py-1.5 border-b border-border/50">
                            <span class="text-muted-foreground">Session Code</span>
                            <span class="font-mono text-xs font-semibold text-foreground">{form.code || 'Pending entry'}</span>
                        </div>
                        <div class="flex items-center justify-between py-1.5 border-b border-border/50">
                            <span class="text-muted-foreground">Schedule</span>
                            <span class="font-medium text-foreground text-right">{form.scheduled_at ? new Date(form.scheduled_at).toLocaleString() : 'Not scheduled'}</span>
                        </div>
                        <div class="flex items-center justify-between py-1.5 border-b border-border/50">
                            <span class="text-muted-foreground">Auto-Assign</span>
                            <span class="font-medium text-xs {form.auto_assign_enabled ? 'text-primary font-semibold' : 'text-muted-foreground'}">
                                {form.auto_assign_enabled ? 'Enabled' : 'Disabled'}
                            </span>
                        </div>
                    </div>

                    <div class="rounded-lg border border-dashed border-border bg-muted/20 p-3.5 text-xs text-muted-foreground leading-relaxed">
                        Release Day sessions provide academic advising and record official Course Endorsements for eligible applicants.
                    </div>
                </div>
            </div>
        </form>
    </div>
</StaffLayout>
