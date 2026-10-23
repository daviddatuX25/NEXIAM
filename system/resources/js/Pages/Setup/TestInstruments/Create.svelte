<script>
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
    import { Button } from '@/Components/ui/button';
    import { Link } from '@inertiajs/svelte';
    import TestInstrumentEditor from './TestInstrumentEditor.svelte';

    let { request_id, constructs = [], kit_context = null } = $props();
    const breadcrumbs = $derived(
        kit_context
            ? [
                  { label: 'Setup', href: '/setup' },
                  { label: 'Test Kits', href: '/setup/exam-materials/test-kits' },
                  { label: kit_context.code, href: kit_context.back_url },
                  { label: 'Test Setup' },
              ]
            : [
                  { label: 'Setup', href: '/setup' },
                  { label: 'Test instruments', href: '/setup/test-instruments' },
                  { label: 'Create draft' },
              ],
    );
</script>

<svelte:head><title>Create test instrument · Setup</title></svelte:head>

<StaffLayout {breadcrumbs}>
    <div class="min-w-0 space-y-6 p-4 sm:p-6">
        <PageHeaderBar
            title="Create instrument draft"
            description="Start a versioned form profile. Missing source evidence may remain incomplete in a draft; approval requires verified identity, scoring rules, and permitted-use references."
        >
            {#snippet actions()}
                <Link href={kit_context?.back_url ?? '/setup/test-instruments'}>
                    <Button variant="outline" class="min-h-10">
                        {kit_context ? `Back to ${kit_context.code}` : 'Back to instruments'}
                    </Button>
                </Link>
            {/snippet}
        </PageHeaderBar>
        <TestInstrumentEditor mode="create" {request_id} {constructs} {kit_context} />
    </div>
</StaffLayout>
