<script>
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
    import RequirementForm from './RequirementForm.svelte';

    let { tracks = [], kinds = [], defaults = {} } = $props();
    const breadcrumbs = [
        { label: 'Setup', href: '/setup' },
        { label: 'Application Requirements', href: '/setup/requirements' },
        { label: 'Create document requirement' },
    ];
</script>

<svelte:head><title>Create document requirement · Setup</title></svelte:head>
<StaffLayout {breadcrumbs}>
    <div class="min-w-0 space-y-6 p-4 sm:p-6">
        <PageHeaderBar title="Create document requirement" />
        <RequirementForm {tracks} {kinds} {defaults} />
    </div>
</StaffLayout>
