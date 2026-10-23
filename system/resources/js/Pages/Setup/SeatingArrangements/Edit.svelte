<script>
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
    import SeatingArrangementForm from './SeatingArrangementForm.svelte';

    let {
        arrangement,
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
        { label: arrangement?.name ?? 'Arrangement', href: `/setup/seating-arrangements/${arrangement?.id}/edit` },
        { label: 'Edit' },
    ];
</script>

<svelte:head><title>Edit {arrangement?.name ?? 'Seating Arrangement'} · Setup</title></svelte:head>

<StaffLayout {breadcrumbs}>
    <div class="min-w-0 space-y-6 p-4 sm:p-6">
        <PageHeaderBar
            title="Edit Seating Arrangement"
            description="Modify desk patterns and room assignments for future exam sessions without altering published manifests."
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
