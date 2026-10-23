<script>
    import { Button } from '@/Components/ui/button';
    import { Input } from '@/Components/ui/input';
    import { Label } from '@/Components/ui/label';
    import InfoPopover from '@/Components/kit/InfoPopover.svelte';
    import CoordinatePicker from '@/Components/Setup/CoordinatePicker.svelte';
    import RoomPhotoUploader from '@/Components/Setup/RoomPhotoUploader.svelte';
    import { Link, useForm } from '@inertiajs/svelte';
    import { LocateFixed, MapPin } from 'lucide-svelte';

    let { room = null, space_kinds = [] } = $props();
    let gpsAccuracy = $state(null);
    let locationError = $state('');
    const editing = Boolean(room);
    const fallbackSpaceKinds = [
        { value: 'room', label: 'Exam / Testing Room', short_label: 'Exam Room', description: 'Has desks & seating grid', allows_seating: true },
        { value: 'assembly_venue', label: 'Assembly Venue', short_label: 'Assembly Venue', description: 'Covered court, gym, hall', allows_seating: false },
        { value: 'service_point', label: 'Service Window / Counter', short_label: 'Service Window', description: 'Application counter, cashier', allows_seating: false },
        { value: 'office', label: 'Office / Other Room', short_label: 'Office / Other', description: 'Dean, dept, admin office', allows_seating: false },
    ];
    const effectiveSpaceKinds = $derived(space_kinds.length > 0 ? space_kinds : fallbackSpaceKinds);
    const form = useForm({
        code: room?.code ?? '',
        name: room?.name ?? '',
        space_kind: room?.space_kind ?? 'room',
        capacity: room?.capacity ?? 30,
        is_active: room?.is_active ?? true,
        building_name: room?.building_name ?? '',
        floor_level: room?.floor_level ?? '',
        directions_hint: room?.directions_hint ?? '',
        latitude: room?.latitude ?? null,
        longitude: room?.longitude ?? null,
    });

    function submit(event) {
        event.preventDefault();
        if (editing) form.put(`/setup/rooms/${room.id}`);
        else form.post('/setup/rooms');
    }

    function useCurrentLocation() {
        locationError = '';
        gpsAccuracy = null;
        if (!navigator.geolocation) {
            locationError = 'Location is not available in this browser.';
            return;
        }

        navigator.geolocation.getCurrentPosition(
            (position) => {
                form.latitude = Number(position.coords.latitude.toFixed(7));
                form.longitude = Number(position.coords.longitude.toFixed(7));
                gpsAccuracy = Math.round(position.coords.accuracy);
            },
            () => {
                locationError = 'Could not get your location. Check permission and try again.';
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 },
        );
    }
</script>

<form onsubmit={submit} class="space-y-6">
    <div class="grid gap-6 lg:grid-cols-[minmax(0,1.2fr)_minmax(18rem,0.8fr)] max-w-6xl items-start">
        <div class="border-border bg-card space-y-5 rounded-xl border p-5 sm:p-6 shadow-xs">
    <div class="space-y-2">
        <div class="flex items-center gap-1.5">
            <Label>Space kind</Label>
            <InfoPopover
                title="Space kind behavior"
                content="Exam Rooms support physical desk grids and seating arrangements. Assembly Venues, Service Windows, and Offices track headcount capacity and bypass desk grids."
            />
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2 text-xs font-mono">
            {#each effectiveSpaceKinds as kind (kind.value)}
                <label class="border rounded-lg p-3 cursor-pointer transition-all flex flex-col justify-between {form.space_kind === kind.value ? 'border-primary bg-primary/10' : 'border-border bg-card'}">
                    <div class="flex items-center justify-between">
                        <span class="font-bold text-foreground">{kind.short_label || kind.label}</span>
                        <input type="radio" bind:group={form.space_kind} value={kind.value} class="accent-primary" />
                    </div>
                    <span class="text-[11px] text-muted-foreground mt-1">{kind.description}</span>
                </label>
            {/each}
        </div>
        {#if form.space_kind !== 'room'}
            <p class="text-[11px] text-muted-foreground font-mono">⚡ Seating arrangement grids are automatically bypassed for this space kind.</p>
        {/if}
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div class="space-y-1.5">
            <Label for="code">Room code</Label>
            <Input
                id="code"
                bind:value={form.code}
                disabled={editing}
                maxlength={32}
                placeholder="RM-102"
                class="min-h-11 font-mono"
                required
            />
            {#if !editing}
                <p class="text-muted-foreground text-xs">
                    Permanent venue code (cannot be changed after creation).
                </p>
            {/if}
            {#if form.errors.code}<p class="text-destructive text-sm">
                    {form.errors.code}
                </p>{/if}
        </div>
        <div class="space-y-1.5">
            <Label for="capacity">Capacity</Label>
            <Input
                id="capacity"
                type="number"
                min="1"
                max="500"
                bind:value={form.capacity}
                class="min-h-11"
                required
            />
            {#if form.errors.capacity}<p class="text-destructive text-sm">
                    {form.errors.capacity}
                </p>{/if}
        </div>
    </div>
    <div class="space-y-1.5">
        <Label for="name">Room name</Label>
        <Input
            id="name"
            bind:value={form.name}
            maxlength={255}
            placeholder="Main Hall 102"
            class="min-h-11"
            required
        />
        {#if form.errors.name}<p class="text-destructive text-sm">
                {form.errors.name}
            </p>{/if}
    </div>
    <div class="grid gap-4 sm:grid-cols-2">
        <div class="space-y-1.5">
            <Label for="building-name">Building</Label>
            <Input
                id="building-name"
                bind:value={form.building_name}
                maxlength="100"
                placeholder="Science Building"
                class="min-h-11"
            />
            {#if form.errors.building_name}<p class="text-destructive text-sm">{form.errors.building_name}</p>{/if}
        </div>
        <div class="space-y-1.5">
            <Label for="floor-level">Floor level</Label>
            <Input
                id="floor-level"
                bind:value={form.floor_level}
                maxlength="32"
                placeholder="2nd Floor"
                class="min-h-11"
            />
            {#if form.errors.floor_level}<p class="text-destructive text-sm">{form.errors.floor_level}</p>{/if}
        </div>
    </div>
    <div class="space-y-1.5">
        <Label for="directions-hint">Directions / landmark</Label>
        <textarea
            id="directions-hint"
            bind:value={form.directions_hint}
            maxlength="500"
            rows="3"
            placeholder="Near the north stairwell, across from the library."
            class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring w-full rounded-md border px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2"
        ></textarea>
        {#if form.errors.directions_hint}<p class="text-destructive text-sm">{form.errors.directions_hint}</p>{/if}
    </div>
    <section class="space-y-3 rounded-lg border border-border p-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <div class="flex items-center gap-1.5">
                    <h3 class="flex items-center gap-1.5 text-sm font-semibold text-foreground"><MapPin class="h-4 w-4 text-primary" />Entrance location</h3>
                    <InfoPopover
                        title="Campus Wayfinding pin"
                        content="Pins the physical doorway coordinates used by the applicant Campus Compass HUD for turn-by-turn venue guidance."
                    />
                </div>
                <p class="text-xs text-muted-foreground">Pin the venue doorway. Review GPS coordinates before saving.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                {#if gpsAccuracy !== null}
                    <span class="rounded-full bg-muted px-2.5 py-1 text-xs font-medium text-foreground">±{gpsAccuracy}m accuracy locked</span>
                {/if}
                <Button type="button" variant="outline" class="min-h-10 gap-1.5" onclick={useCurrentLocation}><LocateFixed class="h-4 w-4" />Use My Current Location</Button>
            </div>
        </div>
        {#if locationError}<p class="text-sm text-destructive" role="alert">{locationError}</p>{/if}
        {#if form.errors.latitude || form.errors.longitude}<p class="text-sm text-destructive">{form.errors.latitude ?? form.errors.longitude}</p>{/if}
        <CoordinatePicker bind:latitude={form.latitude} bind:longitude={form.longitude} />
    </section>
    {#if editing}
        <label
            class="border-border flex items-start gap-3 rounded-lg border p-4"
        >
            <input
                type="checkbox"
                bind:checked={form.is_active}
                class="accent-primary mt-1 h-4 w-4"
            />
            <span
                ><span class="block text-sm font-medium"
                    >Active for scheduling</span
                ><span class="text-muted-foreground block text-xs"
                    >Rooms with future sessions or incompatible defaults cannot be deactivated.</span
                ></span
            >
        </label>
        {#if form.errors.is_active}<p class="text-destructive text-sm">
                {form.errors.is_active}
            </p>{/if}
    {/if}
    <div class="flex flex-wrap gap-2 pt-1">
        <Button type="submit" disabled={form.processing} class="min-h-11"
            >{form.processing
                ? 'Saving…'
                : editing
                  ? 'Save room'
                  : 'Create room'}</Button
        >
        <Link href={editing ? `/setup/rooms/${room.id}` : '/setup/rooms'}>
            <Button type="button" variant="outline" class="min-h-11">Cancel</Button>
        </Link>
    </div>
    </div>

        <div class="border-border bg-card space-y-5 rounded-xl border p-5 sm:p-6 shadow-xs">
            <div class="space-y-1 border-b border-border pb-3">
                <h3 class="font-semibold text-sm text-foreground">Venue Specifications</h3>
                <p class="text-xs text-muted-foreground">Configuration and operational behavior</p>
            </div>

            <div class="space-y-3 text-sm">
                <div class="flex items-center justify-between py-1.5 border-b border-border/50">
                    <span class="text-muted-foreground">Space Kind</span>
                    <span class="font-medium text-foreground capitalize">{effectiveSpaceKinds.find((k) => k.value === form.space_kind)?.label ?? form.space_kind.replace('_', ' ')}</span>
                </div>
                <div class="flex items-center justify-between py-1.5 border-b border-border/50">
                    <span class="text-muted-foreground">Venue Code</span>
                    <span class="font-mono text-xs font-semibold text-foreground">{form.code || 'Pending'}</span>
                </div>
                <div class="flex items-center justify-between py-1.5 border-b border-border/50">
                    <span class="text-muted-foreground">Capacity</span>
                    <span class="font-medium text-foreground">{form.capacity} attendees</span>
                </div>
                <div class="flex items-center justify-between py-1.5 border-b border-border/50">
                    <span class="text-muted-foreground">Seating Layout</span>
                    <span class="font-medium text-foreground">{effectiveSpaceKinds.find((k) => k.value === form.space_kind)?.allows_seating ? 'Custom grid supported' : 'Bypassed (open seating)'}</span>
                </div>
            </div>

            <div class="rounded-lg border border-dashed border-border bg-muted/20 p-3.5 text-xs text-muted-foreground leading-relaxed">
                Venue capacity sets the hard upper ceiling during examination scheduling and applicant auto-distribution. Exam rooms support deterministic desk assignments, while assembly venues and service windows utilize bulk attendance without seating grids.
            </div>
        </div>

            <div class="border-t border-border pt-5">
                <RoomPhotoUploader
                    roomId={room?.id ?? null}
                    currentPhotoPath={room?.photo_path ?? null}
                />
            </div>
    </div>
</form>
