<script>
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
    import RoomForm from './RoomForm.svelte';
    let { space_kinds = [] } = $props();
    const breadcrumbs = [
        { label: 'Setup', href: '/setup' },
        { label: 'Rooms & Seating', href: '/setup/rooms' },
        { label: 'Add Room' },
    ];
</script>

<svelte:head><title>Add Room · Setup</title></svelte:head>
<StaffLayout {breadcrumbs}
    ><div class="min-w-0 space-y-6 p-4 sm:p-6">
        <PageHeaderBar
            title="Add Room"
            description="Register a physical testing venue or room with its designated space kind and examinee capacity."
        /><RoomForm {space_kinds} />
    </div></StaffLayout
>
