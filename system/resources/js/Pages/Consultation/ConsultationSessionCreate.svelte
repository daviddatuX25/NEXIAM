<script>
    /**
     * Create consultation session — legacy list -> create/edit shape.
     * Explicit booking_mode radio pick (no silent default).
     */
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
    import { Button } from '@/Components/ui/button';
    import { Input } from '@/Components/ui/input';
    import { Label } from '@/Components/ui/label';
    import { Link, useForm } from '@inertiajs/svelte';

    let {
        cycles = [],
        rooms = [],
        default_cycle_id = null,
        booking_modes = [],
    } = $props();

    const form = useForm({
        academic_cycle_id: default_cycle_id ? String(default_cycle_id) : '',
        room_id: '',
        code: '',
        scheduled_at: '',
        booking_mode: '', // Explicit pick, no silent default
    });

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
        { label: 'Consultation', href: '/consultation' },
        { label: 'Session Scheduling', href: '/consultation/sessions' },
        { label: 'Create' },
    ];
</script>

<svelte:head><title>Create consultation session</title></svelte:head>

<StaffLayout {breadcrumbs}>
    <div class="min-w-0 space-y-6 p-4 sm:p-6">
        <PageHeaderBar
            title="Create consultation session"
            description="Set date, venue room, and booking mode. Explicitly choose between office-assigned seats or applicant self-booking."
        />

        <form
            class="border-border bg-card max-w-lg space-y-4 rounded-xl border p-6"
            onsubmit={submit}
        >
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
                    placeholder="e.g. CS-2026-AUG-A"
                    required
                />
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
                <Label
                    >Booking mode <span class="text-destructive">*</span></Label
                >
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
                                onchange={() =>
                                    (form.booking_mode = mode.value)}
                                class="accent-primary size-4"
                                required
                            />
                            <span class="text-sm font-medium">{mode.label}</span
                            >
                        </label>
                    {/each}
                </div>
                {#if form.errors.booking_mode}
                    <p class="text-destructive text-sm">
                        {form.errors.booking_mode}
                    </p>
                {/if}
            </div>

            {#if form.errors.schedule}
                <p class="text-destructive text-sm">{form.errors.schedule}</p>
            {/if}

            <div class="flex gap-2 pt-2">
                <Button
                    type="submit"
                    class="min-h-10"
                    disabled={form.processing}
                >
                    {form.processing ? 'Creating...' : 'Create session'}
                </Button>
                <Link href="/consultation/sessions">
                    <Button type="button" variant="outline" class="min-h-10"
                        >Cancel</Button
                    >
                </Link>
            </div>
        </form>
    </div>
</StaffLayout>
