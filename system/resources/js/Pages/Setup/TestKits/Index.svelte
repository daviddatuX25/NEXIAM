<script>
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
    import { Badge } from '@/Components/ui/badge';
    import { Button } from '@/Components/ui/button';
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import { Link } from '@inertiajs/svelte';
    import SwitchableListView from '@/Components/kit/SwitchableListView.svelte';
    import * as Table from '@/Components/ui/table';

    import { ArrowRight, Binary, BookOpen, FileSpreadsheet } from 'lucide-svelte';
    let { test_kits = [], actions = { create: '' } } = $props();
    let viewMode = $state('responsive');

    const breadcrumbs = [
        { label: 'Setup', href: '/setup' },
        { label: 'Test Kits' },
    ];

    function kitState(kit) {
        if (!kit.latest_version) {
            return kit.preparation?.state === 'preparing' ? 'Preparing' : 'Draft';
        }
        return ({
            draft: 'Draft',
            active: 'Active',
            history: 'History',
            revoked: 'Revoked',
        })[kit.latest_version.use_status] ?? kit.latest_version.status;
    }

    function statusVariant(status) {
        if (status === 'Active') return 'default';
        if (status === 'Revoked') return 'destructive';
        if (status === 'History') return 'secondary';
        return 'outline';
    }

    function scoringMethodLabel(method) {
        if (!method) return '—';
        return method === 'final_scores_only' ? 'Final scores only' : 'Raw scoring';
    }

    function omrReadinessLabel(readiness) {
        if (!readiness) return '—';
        if (readiness === 'not_applicable') return 'Not applicable';
        if (readiness === 'ready') return 'Ready';
        if (readiness === 'held') return 'Held';
        return 'Not assessed';
    }
</script>

<svelte:head><title>Test Kits · Exam Materials</title></svelte:head>

<StaffLayout {breadcrumbs}>
    <div class="min-w-0 space-y-6 p-4 sm:p-6" data-tour="test-kits-list">
        {#snippet headerActions()}
            <Link href={actions.create}>
                <Button class="min-h-10">Create Test Kit</Button>
            </Link>
        {/snippet}
        <PageHeaderBar title="Test Kits" actions={headerActions} />


        {#if test_kits.length === 0}
            <div class="border-y border-border/70 py-8 text-center">
                <p class="font-medium text-foreground">No Test Kits yet</p>
                <p class="mt-1 text-sm text-muted-foreground">Create a Test Kit to get started.</p>
            </div>
        {:else}
            <SwitchableListView bind:viewMode cardsInset={false}>
                {#snippet table()}
                    <Table.Root class="w-full min-w-[860px] text-sm">
                        <Table.Header class="bg-muted/40 border-b border-border">
                            <Table.Row>
                                <Table.Head>Code</Table.Head>
                                <Table.Head>Name</Table.Head>
                                <Table.Head>Version</Table.Head>
                                <Table.Head>Scoring method</Table.Head>
                                <Table.Head>OMR readiness</Table.Head>
                                <Table.Head>Status</Table.Head>
                                <Table.Head class="pr-4 text-right">Actions</Table.Head>
                            </Table.Row>
                        </Table.Header>
                        <Table.Body>
                            {#each test_kits as kit (kit.id)}
                                {@const state = kitState(kit)}
                                <Table.Row class="border-b border-border/40 hover:bg-muted/30" data-tour="test-kit-row">
                                    <Table.Cell class="font-mono text-xs">{kit.code}</Table.Cell>
                                    <Table.Cell class="font-medium">{kit.name}</Table.Cell>
                                    <Table.Cell>
                                        {#if kit.latest_version}
                                            v{kit.latest_version.version_number}
                                        {:else}
                                            <span class="text-muted-foreground">—</span>
                                        {/if}
                                    </Table.Cell>
                                    <Table.Cell>{scoringMethodLabel(kit.latest_version?.scoring_method)}</Table.Cell>
                                    <Table.Cell>{omrReadinessLabel(kit.latest_version?.omr_readiness)}</Table.Cell>
                                    <Table.Cell><Badge variant={statusVariant(state)}>{state}</Badge></Table.Cell>
                                    <Table.Cell class="pr-4 text-right">
                                        <div class="inline-flex items-center justify-end gap-3">
                                            <Link
                                                href={kit.test_setup_url || `${kit.url}/test-setup?test_kit_id=${kit.id}`}
                                                class="text-primary text-xs font-medium underline underline-offset-4"
                                            >
                                                Test Setup
                                            </Link>
                                            <Link href={kit.url}><Button variant="outline" size="sm">Open Kit</Button></Link>
                                        </div>
                                    </Table.Cell>
                                </Table.Row>
                            {/each}
                        </Table.Body>
                    </Table.Root>
                {/snippet}
                {#snippet cards()}
                    <ul class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3" role="list">
                        {#each test_kits as kit (kit.id)}
                            {@const state = kitState(kit)}
                            <li class="flex min-w-0 flex-col gap-3 rounded-xl border border-border bg-card p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="font-mono text-xs text-muted-foreground">{kit.code}</p>
                                        <h2 class="mt-1 truncate font-medium text-foreground">{kit.name}</h2>
                                    </div>
                                    <Badge variant={statusVariant(state)} class="shrink-0">{state}</Badge>
                                </div>
                                <p class="text-sm text-muted-foreground">
                                    {kit.latest_version ? `Version ${kit.latest_version.version_number}` : 'No assembled version'}
                                </p>
                                <p class="text-sm text-muted-foreground">
                                    Scoring method: {scoringMethodLabel(kit.latest_version?.scoring_method)}
                                </p>
                                <p class="text-sm text-muted-foreground">
                                    OMR readiness: {omrReadinessLabel(kit.latest_version?.omr_readiness)}
                                </p>
                                <div class="mt-auto flex flex-col gap-2">
                                    <Link href={kit.url}>
                                        <Button variant="outline" size="sm" class="min-h-10 w-full">Open Kit</Button>
                                    </Link>
                                    <Link
                                        href={kit.test_setup_url || `${kit.url}/test-setup?test_kit_id=${kit.id}`}
                                        class="text-primary text-center text-xs font-medium underline underline-offset-4"
                                    >
                                        Configure Test Setup →
                                    </Link>
                                </div>
                            </li>
                        {/each}
                    </ul>
                {/snippet}
            </SwitchableListView>
        {/if}

        <section
            class="border-border/70 space-y-4 border-t pt-6"
            aria-labelledby="independent-exam-materials-heading"
        >
            <div>
                <h2 id="independent-exam-materials-heading" class="text-base font-semibold">
                    Independent Exam Materials &amp; Scoring Rules
                </h2>
                <p class="text-muted-foreground text-sm">
                    Manage standalone test setups, answer sheets, and question booklets independently of any Test Kit.
                </p>
            </div>

            <nav
                aria-label="Independent exam materials and test setup"
                class="grid grid-cols-1 gap-4 md:grid-cols-3"
            >
                <article class="border-border/70 bg-card flex min-w-0 flex-col rounded-2xl border p-4 shadow-xs">
                    <div class="flex items-start justify-between gap-3">
                        <span class="bg-primary/10 text-primary flex size-10 shrink-0 items-center justify-center rounded-xl">
                            <Binary class="size-5" aria-hidden="true" />
                        </span>
                        <Badge variant="secondary">Resource Library</Badge>
                    </div>
                    <div class="mt-4 flex-1 space-y-1.5">
                        <h3 class="font-semibold">Test Setup &amp; Scoring Rules</h3>
                        <p class="text-muted-foreground text-sm">
                            Configure standalone test instruments, subtest sections, raw-score formulas, and percentile conversion tables.
                        </p>
                    </div>
                    <div class="border-border/60 mt-4 border-t pt-3">
                        <Link href={actions.test_setup || '/setup/test-instruments'} class="block">
                            <Button variant="outline" size="sm" class="min-h-10 w-full justify-between">
                                <span>Manage Test Setup &amp; Scoring Rules</span>
                                <ArrowRight class="size-4" aria-hidden="true" />
                            </Button>
                        </Link>
                    </div>
                </article>

                {#if actions.answer_sheets !== null}
                    <article class="border-border/70 bg-card flex min-w-0 flex-col rounded-2xl border p-4 shadow-xs">
                        <div class="flex items-start justify-between gap-3">
                            <span class="bg-primary/10 text-primary flex size-10 shrink-0 items-center justify-center rounded-xl">
                                <FileSpreadsheet class="size-5" aria-hidden="true" />
                            </span>
                            <Badge variant="secondary">Resource Library</Badge>
                        </div>
                        <div class="mt-4 flex-1 space-y-1.5">
                            <h3 class="font-semibold">Answer Sheets</h3>
                            <p class="text-muted-foreground text-sm">
                                Register, upload, and calibrate OMR bubble-sheet templates for examination scoring.
                            </p>
                        </div>
                        <div class="border-border/60 mt-4 border-t pt-3">
                            <Link href={actions.answer_sheets || '/setup/exam-materials/answer-sheets'} class="block">
                                <Button variant="outline" size="sm" class="min-h-10 w-full justify-between">
                                    <span>Manage Answer Sheets</span>
                                    <ArrowRight class="size-4" aria-hidden="true" />
                                </Button>
                            </Link>
                        </div>
                    </article>
                {/if}

                {#if actions.booklets !== null}
                    <article class="border-border/70 bg-card flex min-w-0 flex-col rounded-2xl border p-4 shadow-xs">
                        <div class="flex items-start justify-between gap-3">
                            <span class="bg-primary/10 text-primary flex size-10 shrink-0 items-center justify-center rounded-xl">
                                <BookOpen class="size-5" aria-hidden="true" />
                            </span>
                            <Badge variant="secondary">Resource Library</Badge>
                        </div>
                        <div class="mt-4 flex-1 space-y-1.5">
                            <h3 class="font-semibold">Question Booklets</h3>
                            <p class="text-muted-foreground text-sm">
                                Manage examination booklet keys, question sets, and versioned booklet materials.
                            </p>
                        </div>
                        <div class="border-border/60 mt-4 border-t pt-3">
                            <Link href={actions.booklets || '/setup/exam-materials/booklets'} class="block">
                                <Button variant="outline" size="sm" class="min-h-10 w-full justify-between">
                                    <span>Manage Question Booklets</span>
                                    <ArrowRight class="size-4" aria-hidden="true" />
                                </Button>
                            </Link>
                        </div>
                    </article>
                {/if}
            </nav>
        </section>
    </div>
</StaffLayout>
