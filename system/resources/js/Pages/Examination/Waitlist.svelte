<script>
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
    import WaitlistPool from '@/Components/scheduling/WaitlistPool.svelte';
    import CohortAutoDistributeModal from '@/Components/scheduling/CohortAutoDistributeModal.svelte';
    import { Button } from '@/Components/ui/button';
    import { Link, router } from '@inertiajs/svelte';
    import { ArrowLeft } from 'lucide-svelte';

    let {
        waitlist = null,
        programs = [],
        sessions = [],
        can_manage = false,
        active_cycle_id = null,
        active_cycle = null,
        cycles = [],
        distribution = {},
    } = $props();

    let autoDistributeOpen = $state(false);

    const sessionIndexUrl = () =>
        typeof route === 'function'
            ? route('exams.sessions.index', { academic_cycle_id: active_cycle_id || undefined })
            : (active_cycle_id ? `/exams/sessions?academic_cycle_id=${active_cycle_id}` : '/exams/sessions');

    function handleWaitlistFilterChange(nextFilters) {
        const url = typeof route === 'function' ? route('exams.sessions.waitlist') : '/exams/sessions/waitlist';
        router.get(
            url,
            {
                academic_cycle_id: active_cycle_id || undefined,
                ...nextFilters,
            },
            { preserveState: true, preserveScroll: true },
        );
    }

    function openAutoDistributeModal() {
        autoDistributeOpen = true;
    }

    const breadcrumbs = [
        { label: 'Exam Scheduling', href: typeof route === 'function' ? route('exams.sessions.index') : '/exams/sessions' },
        { label: 'Applicant Waitlist / Pool' },
    ];
</script>

<svelte:head>
    <title>Applicant Waitlist &amp; Pool · Exam Scheduling</title>
</svelte:head>

<StaffLayout {breadcrumbs}>
    <div class="min-w-0 space-y-6 p-4 sm:p-6">
        <PageHeaderBar
            title="Applicant Waitlist & Pool"
            description="Applicants awaiting examination session allocation or blocked by intake prerequisites."
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
            domain="exam"
            {waitlist}
            {programs}
            {sessions}
            canManage={can_manage}
            onFilterChange={handleWaitlistFilterChange}
            onOpenAutoDistribute={openAutoDistributeModal}
        />
    </div>

    {#if can_manage}
        <CohortAutoDistributeModal
            bind:open={autoDistributeOpen}
            domain="exam"
            academicCycleId={active_cycle_id}
            academicCycleName={active_cycle?.name ?? active_cycle?.label ?? 'Current Academic Cycle'}
            {cycles}
            {sessions}
            {distribution}
        />
    {/if}
</StaffLayout>
