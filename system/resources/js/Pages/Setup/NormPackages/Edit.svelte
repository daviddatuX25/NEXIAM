<script>
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
    import { Button } from '@/Components/ui/button';
    import { Link } from '@inertiajs/svelte';
    import NormPackageEditor from './NormPackageEditor.svelte';

    let { package: normPackage, version, is_successor = false, request_id } = $props();
    const breadcrumbs = [
        { label: 'Setup', href: '/setup' },
        { label: 'Norm packages', href: '/setup/norm-packages' },
        { label: normPackage.code, href: `/setup/norm-packages/${normPackage.id}` },
        { label: is_successor ? 'Create successor draft' : 'Edit draft' },
    ];
</script>

<svelte:head><title>{is_successor ? 'Create successor' : 'Edit draft'} · {normPackage.code} · Setup</title></svelte:head>

<StaffLayout {breadcrumbs}>
    <div class="min-w-0 space-y-6 p-4 sm:p-6">
        <PageHeaderBar
            title={is_successor ? 'Create successor draft' : 'Edit norm package draft'}
            description={is_successor
                ? `Based on version ${version.version_number}. Saving creates a new draft; approved and retired versions remain unchanged.`
                : `Version ${version.version_number} · revision ${version.lock_version}. Only this draft is editable.`}
        >
            {#snippet actions()}
                <Link href={`/setup/norm-packages/${normPackage.id}`}>
                    <Button variant="outline" class="min-h-10">Back to package</Button>
                </Link>
            {/snippet}
        </PageHeaderBar>

        <NormPackageEditor
            mode="edit"
            package={normPackage}
            {version}
            {is_successor}
            {request_id}
        />
    </div>
</StaffLayout>
