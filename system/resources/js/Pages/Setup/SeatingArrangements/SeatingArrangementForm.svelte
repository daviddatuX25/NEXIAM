<script>
    import { router, Link, useForm } from '@inertiajs/svelte';
    import { Button } from '@/Components/ui/button';
    import { Input } from '@/Components/ui/input';
    import { Label } from '@/Components/ui/label';
    import InfoPopover from '@/Components/kit/InfoPopover.svelte';
    import SeatingArrangementPreview from './SeatingArrangementPreview.svelte';

    let {
        arrangement = null,
        rooms = [],
        patterns = [],
        availability_modes = [],
        referenced_facts = null,
        published_facts = null,
        conflict = null,
        back_href = '/setup/seating-arrangements',
    } = $props();

    const editing = Boolean(arrangement?.id);

    function roomIdsFor(value) {
        if (Array.isArray(value?.room_ids)) return value.room_ids.map((id) => String(id));
        if (Array.isArray(value?.rooms)) {
            return value.rooms
                .map((room) => (typeof room === 'object' ? room.id : room))
                .filter((id) => id !== null && id !== undefined)
                .map((id) => String(id));
        }
        return [];
    }

    const patternOptions = $derived(
        patterns.length > 0
            ? patterns
            : [
                  { value: 'uniform', label: 'Uniform A', description: 'Every desk receives the same distribution variant.' },
                  { value: 'checkerboard', label: 'Checkerboard A/B', description: 'Adjacent desks alternate A and B.' },
                  { value: 'latin_four', label: 'Latin four A–D', description: 'Four adjacent variants repeat in a deterministic cycle.' },
                  { value: 'linear_alternating', label: 'Linear alternating A/B', description: 'The row-major manifest alternates A and B.' },
              ],
    );

    const availabilityOptions = $derived(
        availability_modes.length > 0
            ? availability_modes
            : [
                  { value: 'all_rooms', label: 'All compatible rooms', description: 'Available to every active room with enough capacity.' },
                  { value: 'selected_rooms', label: 'Selected rooms only', description: 'Available only to the rooms you choose below.' },
              ],
    );

    const form = useForm({
        name: arrangement?.name ?? '',
        rows: arrangement?.rows ?? 5,
        columns: arrangement?.columns ?? 6,
        booklet_pattern: arrangement?.booklet_pattern ?? patternOptions[0]?.value ?? 'checkerboard',
        availability_mode: arrangement?.availability_mode ?? availabilityOptions[0]?.value ?? 'all_rooms',
        room_ids: roomIdsFor(arrangement),
        is_active: arrangement?.is_active ?? true,
        lock_version: arrangement?.lock_version ?? 1,
    });

    const deskCount = $derived(Math.max(0, Number(form.rows || 0) * Number(form.columns || 0)));
    const activeRooms = $derived((rooms ?? []).filter((room) => room.is_active !== false));
    const selectedRoomIds = $derived((form.room_ids ?? []).map((id) => String(id)));
    const selectedRooms = $derived(
        activeRooms.filter((room) => selectedRoomIds.includes(String(room.id))),
    );
    const incompatibleAllRooms = $derived(
        activeRooms.filter((room) => Number(room.capacity ?? 0) < deskCount),
    );
    const incompatibleSelectedRooms = $derived(
        selectedRooms.filter((room) => Number(room.capacity ?? 0) < deskCount),
    );
    const compatibleActiveRooms = $derived(
        activeRooms.filter((room) => Number(room.capacity ?? 0) >= deskCount),
    );
    const compatibleActiveRoomIds = $derived(
        compatibleActiveRooms.map((room) => String(room.id)),
    );
    const allCompatibleRoomsSelected = $derived(
        compatibleActiveRoomIds.length > 0 &&
            compatibleActiveRoomIds.every((id) => selectedRoomIds.includes(id)),
    );
    const missingSelectedRooms = $derived(
        selectedRoomIds.filter((id) => !activeRooms.some((room) => String(room.id) === id)),
    );
    const availabilityError = $derived(
        form.availability_mode === 'selected_rooms' && selectedRoomIds.length === 0
            ? 'Select at least one compatible room or choose all compatible rooms.'
            : null,
    );
    const serverError = $derived(
        form.errors?.availability_mode ||
            form.errors?.room_ids ||
            form.errors?.rows ||
            form.errors?.columns ||
            form.errors?.lock_version ||
            form.errors?.conflict ||
            null,
    );
    const hasReferenceFacts = $derived(Boolean(referenced_facts || published_facts));

    function roomLabel(room) {
        return room.label ?? room.name ?? room.code ?? `Room ${room.id}`;
    }

    function toggleRoom(roomId) {
        const id = String(roomId);
        form.room_ids = selectedRoomIds.includes(id)
            ? selectedRoomIds.filter((value) => value !== id)
            : [...selectedRoomIds, id];
    }

    function toggleAllCompatibleRooms() {
        if (allCompatibleRoomsSelected) {
            form.room_ids = selectedRoomIds.filter(
                (id) => !compatibleActiveRoomIds.includes(id),
            );
        } else {
            form.room_ids = [
                ...new Set([...selectedRoomIds, ...compatibleActiveRoomIds]),
            ];
        }
    }

    function clearRooms() {
        form.room_ids = [];
    }

    function setAvailability(value) {
        form.availability_mode = value;
        if (value === 'all_rooms') form.room_ids = [];
    }

    function submit(event) {
        event.preventDefault();
        if (form.availability_mode === 'selected_rooms' && selectedRoomIds.length === 0) return;
        const url = editing
            ? `/setup/seating-arrangements/${arrangement.id}`
            : '/setup/seating-arrangements';
        if (editing) form.put(url);
        else form.post(url);
    }
</script>

<div class="grid min-w-0 gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(22rem,0.8fr)]">
    <form
        class="border-border bg-card min-w-0 space-y-6 rounded-xl border p-5 sm:p-6"
        onsubmit={submit}
        aria-describedby={serverError || availabilityError ? 'arrangement-form-status' : undefined}
    >
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-1.5 sm:col-span-2">
                <Label for="arrangement-name">Arrangement name</Label>
                <Input
                    id="arrangement-name"
                    bind:value={form.name}
                    class="min-h-11"
                    maxlength="190"
                    placeholder="Main hall checkerboard"
                    required
                />
                {#if form.errors.name}<p class="text-destructive text-sm">{form.errors.name}</p>{/if}
            </div>
            <div class="space-y-1.5">
                <Label for="arrangement-rows">Rows</Label>
                <Input id="arrangement-rows" type="number" min="1" max="500" bind:value={form.rows} class="min-h-11" required />
                {#if form.errors.rows}<p class="text-destructive text-sm">{form.errors.rows}</p>{/if}
            </div>
            <div class="space-y-1.5">
                <Label for="arrangement-columns">Columns</Label>
                <Input id="arrangement-columns" type="number" min="1" max="500" bind:value={form.columns} class="min-h-11" required />
                {#if form.errors.columns}<p class="text-destructive text-sm">{form.errors.columns}</p>{/if}
            </div>
            <p class="text-muted-foreground text-xs sm:col-span-2">
                Total desks ({deskCount}) must not exceed the capacity of compatible rooms.
            </p>
        </div>

        <fieldset class="space-y-3">
            <legend class="flex items-center gap-1.5 text-sm font-medium">
                <span>Distribution pattern</span>
                <InfoPopover
                    title="Booklet variant patterns"
                    content="Controls how booklet variants (A, B, C, D) map across the desk grid during assigned-seating exams to reduce adjacent-seat copying."
                />
            </legend>
            <div class="grid gap-2 sm:grid-cols-2">
                {#each patternOptions as pattern (pattern.value)}
                    <label
                        class="border-border hover:bg-muted/50 flex min-h-14 cursor-pointer items-start gap-3 rounded-lg border px-4 py-3 transition-colors {form.booklet_pattern === pattern.value ? 'border-primary bg-primary/5 ring-primary ring-1' : ''}"
                    >
                        <input
                            type="radio"
                            name="booklet_pattern"
                            value={pattern.value}
                            checked={form.booklet_pattern === pattern.value}
                            onchange={() => (form.booklet_pattern = pattern.value)}
                            class="accent-primary mt-0.5 size-4"
                        />
                        <span>
                            <span class="block text-sm font-medium">{pattern.label}</span>
                            {#if pattern.description}<span class="text-muted-foreground mt-0.5 block text-xs">{pattern.description}</span>{/if}
                        </span>
                    </label>
                {/each}
            </div>
            {#if form.errors.booklet_pattern}<p class="text-destructive text-sm">{form.errors.booklet_pattern}</p>{/if}
        </fieldset>

        <fieldset class="space-y-3">
            <legend class="flex items-center gap-1.5 text-sm font-medium">
                <span>Room availability</span>
                <InfoPopover
                    title="Room capacity check"
                    content="Rooms whose physical capacity is smaller than this grid's total desk count (rows × columns) are automatically excluded from exam scheduling."
                />
            </legend>
            <div class="grid gap-2 sm:grid-cols-2">
                {#each availabilityOptions as option (option.value)}
                    <label
                        class="border-border hover:bg-muted/50 flex min-h-14 cursor-pointer items-start gap-3 rounded-lg border px-4 py-3 transition-colors {form.availability_mode === option.value ? 'border-primary bg-primary/5 ring-primary ring-1' : ''}"
                    >
                        <input
                            type="radio"
                            name="availability_mode"
                            value={option.value}
                            checked={form.availability_mode === option.value}
                            onchange={() => setAvailability(option.value)}
                            class="accent-primary mt-0.5 size-4"
                        />
                        <span>
                            <span class="block text-sm font-medium">{option.label}</span>
                            {#if option.description}<span class="text-muted-foreground mt-0.5 block text-xs">{option.description}</span>{/if}
                        </span>
                    </label>
                {/each}
            </div>
            {#if form.errors.availability_mode}<p class="text-destructive text-sm">{form.errors.availability_mode}</p>{/if}
        </fieldset>

        {#if form.availability_mode === 'selected_rooms'}
            <fieldset class="space-y-3" aria-describedby="selected-room-help">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <legend class="text-sm font-medium">Selected rooms</legend>
                        <p id="selected-room-help" class="text-muted-foreground mt-1 text-xs">
                            Only these rooms can use this arrangement. Each selected room must hold all {deskCount} desks.
                        </p>
                    </div>
                    {#if compatibleActiveRooms.length > 0}
                        <div class="flex items-center gap-3 text-xs shrink-0">
                            <button
                                type="button"
                                class="text-primary hover:underline font-medium cursor-pointer"
                                onclick={toggleAllCompatibleRooms}
                            >
                                {allCompatibleRoomsSelected ? 'Deselect compatible' : `Select all compatible (${compatibleActiveRooms.length})`}
                            </button>
                            {#if selectedRoomIds.length > 0}
                                <button
                                    type="button"
                                    class="text-muted-foreground hover:text-destructive hover:underline cursor-pointer"
                                    onclick={clearRooms}
                                >
                                    Clear
                                </button>
                            {/if}
                        </div>
                    {/if}
                </div>
                {#if activeRooms.length === 0}
                    <div class="border-border bg-muted/20 text-muted-foreground rounded-lg border border-dashed p-4 text-sm">
                        No active rooms are available yet. Create or activate a room before restricting applicability.
                    </div>
                {:else}
                    <div class="grid gap-2 sm:grid-cols-2">
                        {#each activeRooms as room (room.id)}
                            {@const incompatible = Number(room.capacity ?? 0) < deskCount}
                            <label class="border-border flex min-h-14 items-start gap-3 rounded-lg border px-4 py-3 {incompatible ? 'bg-amber-500/5' : 'hover:bg-muted/50'}">
                                <input
                                    type="checkbox"
                                    checked={selectedRoomIds.includes(String(room.id))}
                                    onchange={() => toggleRoom(room.id)}
                                    class="accent-primary mt-0.5 size-4"
                                    aria-label={`Use ${roomLabel(room)}`}
                                />
                                <span class="min-w-0">
                                    <span class="block truncate text-sm font-medium">{roomLabel(room)}</span>
                                    <span class="text-muted-foreground block text-xs">
                                        Capacity {room.capacity ?? '—'}
                                        {#if incompatible}<span class="text-amber-700 dark:text-amber-300"> · too small</span>{/if}
                                    </span>
                                </span>
                            </label>
                        {/each}
                    </div>
                {/if}
                {#if form.errors.room_ids}<p class="text-destructive text-sm">{form.errors.room_ids}</p>{/if}
            </fieldset>
        {/if}

        <div class="border-border bg-muted/20 rounded-lg border p-4" id="arrangement-form-status" aria-live="polite">
            <div class="flex items-start gap-3">
                <input id="arrangement-active" type="checkbox" bind:checked={form.is_active} class="accent-primary mt-0.5 size-4" />
                <label for="arrangement-active">
                    <span class="block text-sm font-medium">Active for scheduling</span>
                    <span class="text-muted-foreground mt-0.5 block text-xs">Inactive arrangements stay available for history but cannot be selected for a new session.</span>
                </label>
            </div>
        </div>

        {#if form.availability_mode === 'all_rooms' && incompatibleAllRooms.length > 0}
            <div class="border-amber-500/40 bg-amber-500/10 text-amber-950 dark:text-amber-100 rounded-lg border px-4 py-3 text-sm" role="status">
                <p class="font-medium">Some rooms are too small for this arrangement.</p>
                <p class="mt-1">It remains available to compatible rooms only. {incompatibleAllRooms.length} active {incompatibleAllRooms.length === 1 ? 'room is' : 'rooms are'} below the {deskCount}-desk capacity.</p>
            </div>
        {:else if form.availability_mode === 'selected_rooms' && incompatibleSelectedRooms.length > 0}
            <div class="border-destructive/40 bg-destructive/10 text-destructive rounded-lg border px-4 py-3 text-sm" role="alert">
                <p class="font-medium">Selected rooms cannot hold this arrangement.</p>
                <p class="mt-1">Choose different rooms or reduce the rows/columns before saving.</p>
            </div>
        {:else if form.availability_mode === 'selected_rooms' && missingSelectedRooms.length > 0}
            <div class="border-amber-500/40 bg-amber-500/10 text-amber-950 dark:text-amber-100 rounded-lg border px-4 py-3 text-sm" role="status">
                Some previously selected rooms are no longer available. Review the selection before saving.
            </div>
        {/if}

        {#if availabilityError || serverError}
            <div class="border-destructive/40 bg-destructive/10 text-destructive rounded-lg border px-4 py-3 text-sm" role="alert">
                {availabilityError ?? serverError}
            </div>
        {/if}

        {#if conflict || form.errors.lock_version || form.errors.conflict}
            <div class="border-amber-500/40 bg-amber-500/10 rounded-lg border px-4 py-3 text-sm" role="alert">
                <p class="font-medium">This arrangement changed in another session.</p>
                <p class="text-muted-foreground mt-1">Your save was not applied. Reload the current arrangement before making another edit.</p>
                <Button type="button" variant="outline" size="sm" class="mt-3 min-h-10" onclick={() => router.reload()}>
                    Reload current version
                </Button>
            </div>
        {/if}

        {#if hasReferenceFacts}
            <div class="border-border bg-muted/20 rounded-lg border px-4 py-3 text-sm">
                <p class="font-medium">Published use and defaults</p>
                <p class="text-muted-foreground mt-1">
                    {published_facts?.count ?? published_facts?.sessions_count ?? 0} published {((published_facts?.count ?? published_facts?.sessions_count ?? 0) === 1) ? 'session uses' : 'sessions use'} this arrangement.
                    {referenced_facts?.default_rooms_count ?? referenced_facts?.rooms_count ?? 0} room default{((referenced_facts?.default_rooms_count ?? referenced_facts?.rooms_count ?? 0) === 1) ? '' : 's'} reference it.
                </p>
            </div>
        {/if}

        <div class="flex flex-wrap gap-2 pt-1">
            <Button type="submit" disabled={form.processing || Boolean(availabilityError) || incompatibleSelectedRooms.length > 0} class="min-h-11">
                {form.processing ? 'Saving…' : editing ? 'Save arrangement' : 'Create arrangement'}
            </Button>
            <Link href={back_href}>
                <Button type="button" variant="outline" class="min-h-11">Cancel</Button>
            </Link>
        </div>
    </form>

    <div class="space-y-4 xl:sticky xl:top-24 xl:self-start">
        <SeatingArrangementPreview
            rows={form.rows}
            columns={form.columns}
            booklet_pattern={form.booklet_pattern}
        />
        <div class="border-border bg-card rounded-xl border p-5">
            <h2 class="font-semibold">Compatibility check</h2>
            <dl class="mt-3 grid gap-3 text-sm">
                <div class="flex items-center justify-between gap-4">
                    <dt class="text-muted-foreground">Desk count</dt>
                    <dd class="font-medium">{deskCount}</dd>
                </div>
                <div class="flex items-center justify-between gap-4">
                    <dt class="text-muted-foreground">Active rooms</dt>
                    <dd class="font-medium">{activeRooms.length}</dd>
                </div>
                <div class="flex items-center justify-between gap-4">
                    <dt class="text-muted-foreground">Compatible rooms</dt>
                    <dd class="font-medium">{Math.max(0, activeRooms.length - incompatibleAllRooms.length)}</dd>
                </div>
                {#if form.availability_mode === 'selected_rooms'}
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-muted-foreground">Selected rooms</dt>
                        <dd class="font-medium">{selectedRoomIds.length}</dd>
                    </div>
                {/if}
            </dl>
            <p class="text-muted-foreground mt-4 text-xs leading-relaxed">
                Compatibility is checked again by the server when this arrangement is saved, selected as a room default, or used by an exam session.
            </p>
        </div>
    </div>
</div>
