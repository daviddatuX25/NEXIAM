<script>
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
    import SeatingArrangementForm from './SeatingArrangementForm.svelte';

    let {
        arrangement = null,
        rooms = [],
        patterns = [],
        availability_modes = [],
        preview = [],
        referenced_facts = null,
        published_facts = null,
        conflict = null,
    } = $props();

    const breadcrumbs = [
        { label: 'Setup', href: '/setup' },
        { label: 'Seating Arrangements', href: '/setup/seating-arrangements' },
        { label: 'Create' },
    ];
</script>

<svelte:head><title>Create Seating Arrangement · Setup</title></svelte:head>

<StaffLayout {breadcrumbs}>
    <div class="min-w-0 space-y-6 p-4 sm:p-6">
        <PageHeaderBar
            title="Create Seating Arrangement"
            description="Design a reusable grid-based desk pattern and assign it to compatible examination rooms."
        />
        <SeatingArrangementForm
            {arrangement}
            {rooms}
            {patterns}
            {availability_modes}
            {preview}
            {referenced_facts}
            {published_facts}
            {conflict}
        />
    </div>
</StaffLayout>
