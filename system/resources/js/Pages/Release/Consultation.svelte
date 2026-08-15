<script>
    /* global route */
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import E2PageState from '@/Pages/Admission/E2PageState.svelte';
    import { Button } from '@/Components/ui/button';
    import { Link } from '@inertiajs/svelte';
    import { Calendar } from 'lucide-svelte';

    let { page_state = 'ready', attempts = [], can_plan = false } = $props();

    const sessionsUrl =
        typeof route === 'function'
            ? route('consultation.sessions.index')
            : '/consultation/sessions';
</script>

<svelte:head><title>Consultation</title></svelte:head>

<StaffLayout breadcrumbs={[{ label: 'Consultation' }]}>
    <div class="p-6">
        {#if can_plan}
            <div class="mb-6 flex items-center justify-end">
                <Link href={sessionsUrl}>
                    <Button
                        variant="outline"
                        class="min-h-10 gap-2 font-semibold"
                    >
                        <Calendar class="size-4" />
                        Manage consultation sessions
                    </Button>
                </Link>
            </div>
        {/if}
        <E2PageState state={page_state} />
        <ul>
            {#each attempts as a (a.id)}<li>{a.reference_code}</li>{/each}
        </ul>
    </div>
</StaffLayout>
