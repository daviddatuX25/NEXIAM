<script>
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
    import RoomForm from './RoomForm.svelte';
    let { room, space_kinds = [] } = $props();
    const breadcrumbs = [
        { label: 'Setup', href: '/setup' },
        { label: 'Rooms & Seating', href: '/setup/rooms' },
        { label: room.name, href: `/setup/rooms/${room.id}` },
        { label: 'Edit' },
    ];
</script>

<svelte:head><title>Edit {room.name} · Setup</title></svelte:head>
<StaffLayout {breadcrumbs}
    ><div class="min-w-0 space-y-6 p-4 sm:p-6">
        <PageHeaderBar
            title="Edit Room"
            description="Update testing venue details, room code, and overall examinee seating capacity."
        /><RoomForm {room} {space_kinds} />
    </div></StaffLayout
>
