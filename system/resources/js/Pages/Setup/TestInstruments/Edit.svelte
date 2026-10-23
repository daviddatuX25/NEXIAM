<script>
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
    import { Button } from '@/Components/ui/button';
    import { Link } from '@inertiajs/svelte';
    import TestInstrumentEditor from './TestInstrumentEditor.svelte';

    let { instrument, version, request_id, constructs = [], kit_context = null } = $props();
    const breadcrumbs = $derived(
        kit_context
            ? [
                  { label: 'Setup', href: '/setup' },
                  { label: 'Test Kits', href: '/setup/exam-materials/test-kits' },
                  { label: kit_context.code, href: kit_context.back_url },
                  { label: 'Test Setup' },
                  { label: 'Edit profile' },
              ]
            : [
                  { label: 'Setup', href: '/setup' },
                  { label: 'Test instruments', href: '/setup/test-instruments' },
                  {
                      label: instrument.code,
                      href: `/setup/test-instruments/${instrument.id}`,
                  },
                  { label: 'Edit draft' },
              ],
    );
</script>

<svelte:head><title>Edit {instrument.code} draft · Setup</title></svelte:head>

<StaffLayout {breadcrumbs}>
    <div class="min-w-0 space-y-6 p-4 sm:p-6">
        <PageHeaderBar
            title="Edit instrument draft"
            description="Version {version.version_number} · revision {version.lock_version}. Approved versions are immutable; edits are saved to this draft only."
        >
            {#snippet actions()}
                <Link href={kit_context?.back_url ?? `/setup/test-instruments/${instrument.id}`}>
                    <Button variant="outline" class="min-h-10">
                        {kit_context ? `Back to ${kit_context.code}` : 'Back to profile'}
                    </Button>
                </Link>
            {/snippet}
        </PageHeaderBar>
        <TestInstrumentEditor
            mode="edit"
            {instrument}
            {version}
            {request_id}
            {constructs}
            {kit_context}
        />
    </div>
</StaffLayout>
