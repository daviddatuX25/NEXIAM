<script>
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
    import RequirementForm from './RequirementForm.svelte';

    let { requirement, tracks = [], kinds = [] } = $props();
    const breadcrumbs = [
        { label: 'Setup', href: '/setup' },
        { label: 'Application Requirements', href: '/setup/requirements' },
        { label: 'Edit document requirement' },
    ];
</script>

<svelte:head><title>Edit document requirement · Setup</title></svelte:head>
<StaffLayout {breadcrumbs}>
    <div class="min-w-0 space-y-6 p-4 sm:p-6">
        <PageHeaderBar title="Edit document requirement" />
        <RequirementForm {requirement} {tracks} {kinds} />
    </div>
</StaffLayout>
