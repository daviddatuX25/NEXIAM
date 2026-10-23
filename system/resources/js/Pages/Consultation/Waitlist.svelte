<script>
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
    import WaitlistPool from '@/Components/scheduling/WaitlistPool.svelte';
    import { Button } from '@/Components/ui/button';
    import { Link, router } from '@inertiajs/svelte';
    import { ArrowLeft } from 'lucide-svelte';

    let {
        waitlist = null,
        programs = [],
        sessions = [],
        can_manage = false,
        active_cycle_id = null,
    } = $props();

    const sessionIndexUrl = () =>
        typeof route === 'function'
            ? route('consultation.sessions.index', { academic_cycle_id: active_cycle_id || undefined })
            : (active_cycle_id ? `/consultation/sessions?academic_cycle_id=${active_cycle_id}` : '/consultation/sessions');

    function handleWaitlistFilterChange(nextFilters) {
        const url = typeof route === 'function' ? route('consultation.sessions.waitlist') : '/consultation/sessions/waitlist';
        router.get(
            url,
            {
                academic_cycle_id: active_cycle_id || undefined,
                ...nextFilters,
            },
            { preserveState: true, preserveScroll: true },
        );
    }

    const breadcrumbs = [
        { label: 'Release Day Scheduling', href: typeof route === 'function' ? route('consultation.sessions.index') : '/consultation/sessions' },
        { label: 'Applicant Waitlist / Pool' },
    ];
</script>

<svelte:head>
    <title>Applicant Waitlist &amp; Pool · Session Scheduling</title>
</svelte:head>

<StaffLayout {breadcrumbs}>
    <div class="min-w-0 space-y-6 p-4 sm:p-6">
        <PageHeaderBar
            title="Applicant Waitlist & Pool"
            description="Applicants ready for a Release Day appointment or blocked by upstream stages."
        >
            {#snippet actions()}
                <Link href={sessionIndexUrl()}>
                    <Button variant="outline" class="min-h-10 gap-2">
                        <ArrowLeft class="size-4" />
                        Back to Sessions
                    </Button>
                </Link>
            {/snippet}
        </PageHeaderBar>

        <WaitlistPool
            domain="consultation"
            {waitlist}
            {programs}
            {sessions}
            canManage={can_manage}
            onFilterChange={handleWaitlistFilterChange}
        />
    </div>
</StaffLayout>
