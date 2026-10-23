<script>
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
    import { Button } from '@/Components/ui/button';
    import { Link } from '@inertiajs/svelte';
    import NormPackageEditor from './NormPackageEditor.svelte';

    let { request_id } = $props();
    const breadcrumbs = [
        { label: 'Setup', href: '/setup' },
        { label: 'Norm packages', href: '/setup/norm-packages' },
        { label: 'Create draft' },
    ];
</script>

<svelte:head><title>Create norm package · Setup</title></svelte:head>

<StaffLayout {breadcrumbs}>
    <div class="min-w-0 space-y-6 p-4 sm:p-6">
        <PageHeaderBar
            title="Create norm package draft"
            description="Record package identity, source references, applicable population, and versioned conversion tables. Saving creates an unapproved draft."
        >
            {#snippet actions()}
                <Link href="/setup/norm-packages">
                    <Button variant="outline" class="min-h-10">Back to norm packages</Button>
                </Link>
            {/snippet}
        </PageHeaderBar>

        <NormPackageEditor mode="create" {request_id} />
    </div>
</StaffLayout>
