<script>
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
    import { Badge } from '@/Components/ui/badge';
    import { Button } from '@/Components/ui/button';
    import { Link, router } from '@inertiajs/svelte';
    import { DoorClosed, Grid3X3, MapPin, Pencil, Plus, Save } from 'lucide-svelte';

    let { room, enable_seating_arrangements = false, can_manage_seating_arrangements = false, space_kinds = [] } = $props();
    let defaultId = $state(
        String(
            room.default_seating_arrangement?.id ??
                room.default_seating_arrangement_id ??
                '',
        ),
    );
    let savingDefault = $state(false);

    const arrangements = $derived(
        room.seating_arrangements ?? room.applicable_seating_arrangements ?? [],
    );

    const breadcrumbs = [
        { label: 'Setup', href: '/setup' },
        { label: enable_seating_arrangements ? 'Rooms & Seating' : 'Campus Venues & Exam Rooms', href: '/setup/rooms' },
        { label: room.name },
    ];
    const photoUrl = $derived(
        room.photo_path
            ? (room.photo_path.startsWith('http') || room.photo_path.startsWith('/')
                ? room.photo_path
                : `/storage/${room.photo_path}`)
            : null
    );


    function label(pattern) {
        return (
            {
                uniform: 'Uniform A',
                checkerboard: 'Checkerboard A/B',
                latin_four: 'Latin four A–D',
                linear_alternating: 'Linear alternating A/B',
            }[pattern] ?? pattern ?? '—'
        );
    }

    function roomArrangementName(arrangement) {
        return arrangement.name ?? arrangement.label ?? `Arrangement ${arrangement.id}`;
    }

    function spaceKindLabel(spaceKind) {
        const found = space_kinds.find((k) => k.value === spaceKind);
        if (found) return found.label;
        return (
            {
                room: 'Exam Room',
                assembly_venue: 'Assembly Venue',
                service_point: 'Service Window',
                office: 'Office / Other Room',
            }[spaceKind] ?? 'Room'
        );
    }
    function saveDefault(event) {
        event.preventDefault();
        savingDefault = true;
        router.post(
            `/setup/rooms/${room.id}/default-seating-arrangement`,
            {
                seating_arrangement_id: defaultId || null,
                expected_version: room.version ?? undefined,
            },
            {
                preserveScroll: true,
                onFinish: () => (savingDefault = false),
            },
        );
    }

    function toggleRoom() {
        router.post(
            `/setup/rooms/${room.id}/${room.is_active ? 'deactivate' : 'activate'}`,
        );
    }
</script>

<svelte:head><title>{room.name} · Venue Preview · Setup</title></svelte:head>

<StaffLayout {breadcrumbs}>
    <div class="min-w-0 space-y-6 p-4 sm:p-6">
        <PageHeaderBar
            title={room.name}
            description={`${spaceKindLabel(room.space_kind)} · Room ${room.code} · Capacity: ${room.capacity} attendees`}
        >
            {#snippet actions()}
                <div class="flex flex-wrap items-center gap-2">
                    <Link href={`/setup/rooms/${room.id}/edit`}>
                        <Button class="min-h-11 gap-2 font-semibold">
                            <Pencil class="h-4 w-4" />Edit room
                        </Button>
                    </Link>
                    <Button variant="outline" class="min-h-11" onclick={toggleRoom}>
                        {room.is_active ? 'Deactivate' : 'Activate'}
                    </Button>
                    {#if enable_seating_arrangements && can_manage_seating_arrangements}
                        <Link href="/setup/seating-arrangements/create">
                            <Button variant="outline" class="min-h-11 gap-2">
                                <Plus class="h-4 w-4" />Create arrangement
                            </Button>
                        </Link>
                    {/if}
                </div>
            {/snippet}
        </PageHeaderBar>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1.2fr)_minmax(18rem,0.8fr)] max-w-6xl items-start">
            <div class="space-y-6">
                <section
                    class="border-border bg-card space-y-4 rounded-xl border p-5 sm:p-6 shadow-xs"
                    aria-labelledby="venue-specs-title"
                >
                    <div class="flex items-center justify-between border-b border-border pb-3">
                        <div>
                            <h2 id="venue-specs-title" class="font-semibold text-base text-foreground">
                                Venue Specifications
                            </h2>
                            <p class="text-xs text-muted-foreground mt-0.5">Configuration and operational details</p>
                        </div>
                        <Badge variant={room.is_active ? 'default' : 'secondary'}>
                            {room.is_active ? 'Active for scheduling' : 'Inactive'}
                        </Badge>
                    </div>

                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                        <div class="space-y-1">
                            <dt class="text-xs text-muted-foreground font-medium">Space Kind</dt>
                            <dd class="font-medium text-foreground">{spaceKindLabel(room.space_kind)}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-xs text-muted-foreground font-medium">Venue Code</dt>
                            <dd class="font-mono text-xs font-semibold text-foreground">{room.code}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-xs text-muted-foreground font-medium">Capacity</dt>
                            <dd class="font-medium text-foreground">{room.capacity} attendees</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-xs text-muted-foreground font-medium">Seating Layout</dt>
                            <dd class="font-medium text-foreground">
                                {room.space_kind === 'room' ? 'Custom grid supported' : 'Bypassed (open seating)'}
                            </dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-xs text-muted-foreground font-medium">Building</dt>
                            <dd class="font-medium text-foreground">{room.building_name || '—'}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-xs text-muted-foreground font-medium">Floor Level</dt>
                            <dd class="font-medium text-foreground">{room.floor_level || '—'}</dd>
                        </div>
                    </dl>

                    {#if room.directions_hint}
                        <div class="border-t border-border/60 pt-3 space-y-1.5">
                            <span class="text-xs text-muted-foreground font-medium">Directions / Landmark Cues</span>
                            <p class="text-sm text-foreground/90 leading-relaxed bg-muted/20 p-3 rounded-lg border border-border/40">
                                {room.directions_hint}
                            </p>
                        </div>
                    {/if}
                </section>

                <section
                    class="border-border bg-card space-y-3 rounded-xl border p-5 shadow-xs"
                    aria-labelledby="entrance-location-title"
                >
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <MapPin class="h-4 w-4 text-primary" />
                            <h3 id="entrance-location-title" class="font-semibold text-sm text-foreground">
                                Entrance Doorway Location
                            </h3>
                        </div>
                        {#if room.latitude != null && room.longitude != null}
                            <span class="rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 px-2.5 py-0.5 text-xs font-medium">
                                GPS Pin Active
                            </span>
                        {:else}
                            <span class="rounded-full bg-muted text-muted-foreground px-2.5 py-0.5 text-xs font-medium">
                                No GPS Coordinates
                            </span>
                        {/if}
                    </div>

                    {#if room.latitude != null && room.longitude != null}
                        <div class="grid grid-cols-2 gap-3 text-xs font-mono bg-muted/30 p-3 rounded-lg border border-border/40">
                            <div>
                                <span class="text-muted-foreground block text-[11px]">Latitude</span>
                                <span class="font-semibold">{Number(room.latitude).toFixed(7)}°</span>
                            </div>
                            <div>
                                <span class="text-muted-foreground block text-[11px]">Longitude</span>
                                <span class="font-semibold">{Number(room.longitude).toFixed(7)}°</span>
                            </div>
                        </div>
                        <p class="text-xs text-muted-foreground">
                            This pin marks the physical entrance doorway for applicant wayfinding via Campus Compass.
                        </p>
                    {:else}
                        <p class="text-xs text-muted-foreground">
                            No GPS coordinates are pinned yet. Edit this room to set the entrance doorway marker using OpenStreetMap or your current device GPS.
                        </p>
                    {/if}
                </section>
            </div>

            <div class="border-border bg-card space-y-4 rounded-xl border p-5 sm:p-6 shadow-xs">
                <div class="space-y-1 border-b border-border pb-3">
                    <div class="flex items-center gap-2">
                        <DoorClosed class="h-4 w-4 text-primary" />
                        <h3 class="font-semibold text-sm text-foreground">Entrance Door Photo</h3>
                    </div>
                    <p class="text-xs text-muted-foreground">Wayfinding visual recognition preview</p>
                </div>

                {#if photoUrl}
                    <div class="overflow-hidden rounded-lg border border-border bg-muted/20">
                        <div class="aspect-[3/4] w-full max-w-[260px] mx-auto overflow-hidden">
                            <img
                                src={photoUrl}
                                alt={`Entrance to ${room.name}`}
                                class="h-full w-full object-cover object-top"
                            />
                        </div>
                        <div class="p-2.5 text-center bg-card border-t border-border/60">
                            <p class="text-xs text-muted-foreground">
                                Uncropped 3:4 door plaque preview as seen by applicants during arrival.
                            </p>
                        </div>
                    </div>
                {:else}
                    <div class="flex flex-col items-center justify-center p-8 rounded-lg border border-dashed border-border text-center bg-muted/10">
                        <DoorClosed class="h-10 w-10 text-muted-foreground/50 mb-2" />
                        <p class="text-xs font-medium text-foreground">No entrance photo uploaded</p>
                        <p class="text-[11px] text-muted-foreground mt-1 max-w-[200px]">
                            Upload a 3:4 doorway photo in Edit mode to help applicants visually identify the venue door.
                        </p>
                        <Link href={`/setup/rooms/${room.id}/edit`} class="mt-3">
                            <Button variant="outline" size="sm" class="text-xs min-h-8">
                                Upload photo
                            </Button>
                        </Link>
                    </div>
                {/if}
            </div>
        </div>

        {#if enable_seating_arrangements && can_manage_seating_arrangements}
        <section class="border-border bg-card rounded-xl border p-5" aria-labelledby="default-arrangement-title">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 id="default-arrangement-title" class="font-semibold">Room default arrangement</h2>
                    <p class="text-muted-foreground mt-1 text-sm">
                        The default is preselected when staff create a session in this room. It must stay active, applicable, and within capacity.
                    </p>
                </div>
                <Badge variant={defaultId ? 'default' : 'secondary'}>
                    {defaultId ? 'Configured' : 'Not configured'}
                </Badge>
            </div>
            <form class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end" onsubmit={saveDefault}>
                <div class="min-w-0 flex-1 space-y-1.5">
                    <label for="room-default-arrangement" class="text-sm font-medium">Default seating arrangement</label>
                    <select
                        id="room-default-arrangement"
                        bind:value={defaultId}
                        class="border-input bg-background min-h-11 w-full rounded-md border px-3 text-sm"
                    >
                        <option value="">No default</option>
                        {#each arrangements as arrangement (arrangement.id)}
                            <option value={String(arrangement.id)} disabled={arrangement.is_active === false || arrangement.is_compatible === false}>
                                {roomArrangementName(arrangement)} · {arrangement.rows ?? '—'}×{arrangement.columns ?? '—'}
                                {arrangement.is_compatible === false ? ' · incompatible' : ''}
                            </option>
                        {/each}
                    </select>
                </div>
                <Button type="submit" class="min-h-11 gap-2" disabled={savingDefault || !room.is_active}>
                    <Save class="h-4 w-4" />{savingDefault ? 'Saving…' : 'Save default'}
                </Button>
            </form>
            {#if !room.is_active}
                <p class="text-muted-foreground mt-3 text-xs">Activate this room before assigning a default.</p>
            {/if}
        </section>

        <section class="space-y-3" aria-labelledby="arrangements-title">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 id="arrangements-title" class="text-lg font-semibold">Applicable arrangements</h2>
                    <p class="text-muted-foreground text-sm">
                        Reusable definitions available to this room. Session seats are materialized only when a session is published.
                    </p>
                </div>
                <Link href="/setup/seating-arrangements">
                    <Button variant="outline" class="min-h-10">Browse catalog</Button>
                </Link>
            </div>
            {#if arrangements.length === 0}
                <div class="border-border bg-card rounded-xl border border-dashed px-6 py-12 text-center">
                    <Grid3X3 class="text-muted-foreground mx-auto h-8 w-8" />
                    <h3 class="mt-4 font-semibold">No applicable arrangements</h3>
                    <p class="text-muted-foreground mt-1 text-sm">
                        Create an all-room arrangement or select this room when creating a restricted arrangement.
                    </p>
                    <Link href="/setup/seating-arrangements/create" class="mt-5 inline-flex">
                        <Button class="min-h-11 gap-2"><Plus class="h-4 w-4" />Create arrangement</Button>
                    </Link>
                </div>
            {:else}
                <ul class="grid gap-3 lg:grid-cols-2" role="list">
                    {#each arrangements as arrangement (arrangement.id)}
                        <li class="border-border bg-card rounded-xl border p-5 {arrangement.is_active === false ? 'opacity-70' : ''}">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <h3 class="truncate font-semibold">{roomArrangementName(arrangement)}</h3>
                                    <p class="text-muted-foreground mt-1 text-sm">
                                        {arrangement.rows ?? '—'} rows × {arrangement.columns ?? '—'} columns
                                        · {arrangement.desk_count ?? ((arrangement.rows ?? 0) * (arrangement.columns ?? 0))} seats
                                    </p>
                                </div>
                                <Badge variant={arrangement.is_active === false ? 'secondary' : 'outline'}>
                                    {arrangement.is_active === false ? 'Inactive' : 'Available'}
                                </Badge>
                            </div>
                            <div class="mt-4 flex items-center justify-between gap-3">
                                <p class="text-sm">
                                    <span class="text-muted-foreground">Pattern:</span>
                                    {label(arrangement.booklet_pattern)}
                                </p>
                                <Link href={`/setup/seating-arrangements/${arrangement.id}/edit`}>
                                    <Button size="sm" variant="outline">Edit arrangement</Button>
                                </Link>
                            </div>
                        </li>
                    {/each}
                </ul>
            {/if}
        </section>
        {/if}
    </div>
</StaffLayout>

