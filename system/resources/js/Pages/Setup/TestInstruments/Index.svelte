<script>
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
    import { Badge } from '@/Components/ui/badge';
    import { Button } from '@/Components/ui/button';
    import * as Table from '@/Components/ui/table';
    import { Link } from '@inertiajs/svelte';
    import { FilePlus2, ScrollText } from 'lucide-svelte';

    let { page_state = 'ready', instruments = [] } = $props();

    const breadcrumbs = [
        { label: 'Setup', href: '/setup' },
        { label: 'Test instruments' },
    ];

    function statusLabel(status) {
        return status === 'approved'
            ? 'Approved'
            : status === 'draft'
              ? 'Draft'
              : 'No approved version';
    }

    function normLabel(readiness) {
        if (readiness === 'configured') return 'Conversion tables ready';
        if (readiness === 'not_applicable') return 'Not applicable';
        return readiness === 'missing_package'
            ? 'Norm package missing'
            : 'Not configured';
    }

    function scoringMethodLabel(instrument) {
        if (instrument.profile_metadata?.is_final_only) {
            return 'Aptitude final scores only · Percentile rank';
        }
        return instrument.raw_readiness === 'approved'
            ? 'Profile approved'
            : `${instrument.subtest_count} test ${instrument.subtest_count === 1 ? 'section' : 'sections'} · ${instrument.required_metric_count} required ${instrument.required_metric_count === 1 ? 'metric' : 'metrics'}`;
    }
</script>

<svelte:head><title>Test instruments · Setup</title></svelte:head>

<StaffLayout {breadcrumbs}>
    <div class="min-w-0 space-y-6 p-4 sm:p-6">
        <PageHeaderBar
            title="Test Setup & Scoring Rules"
            description="Manage versioned test setups, subtest sections, raw-score formulas, and percentile conversion tables."
        >
            {#snippet actions()}
                <Link href="/setup/test-instruments/create">
                    <Button class="min-h-10 gap-2"
                        ><FilePlus2 class="h-4 w-4" />Create Test Setup</Button
                    >
                </Link>
            {/snippet}
        </PageHeaderBar>

        {#if page_state === 'empty_scope' || instruments.length === 0}
            <section
                class="craft-card-depth border-border/70 rounded-xl border border-dashed px-6 py-14 text-center"
                aria-labelledby="empty-instruments-heading"
            >
                <ScrollText
                    class="text-muted-foreground mx-auto h-8 w-8"
                    aria-hidden="true"
                />
                <h2 id="empty-instruments-heading" class="mt-4 font-semibold">
                    No instrument profiles yet
                </h2>
                <p class="text-muted-foreground mx-auto mt-1 max-w-xl text-sm">
                    Create a draft profile to define a form's sections and raw
                    measures. Unverified sources remain drafts and cannot be
                    assigned to an assessment.
                </p>
                <Link
                    href="/setup/test-instruments/create"
                    class="mt-5 inline-flex"
                >
                    <Button class="min-h-11 gap-2"
                        ><FilePlus2 class="h-4 w-4" />Create first draft</Button
                    >
                </Link>
            </section>
        {:else}
            <div
                class="craft-card-depth border-border/70 hidden overflow-x-auto rounded-xl border md:block"
            >
                <Table.Root class="min-w-270">
                    <Table.Header>
                        <Table.Row>
                            <Table.Head>Instrument / form</Table.Head>
                            <Table.Head>Version</Table.Head>
                            <Table.Head>Status</Table.Head>
                            <Table.Head>Source review</Table.Head>
                            <Table.Head>Scoring method</Table.Head>
                            <Table.Head>Norm conversion</Table.Head>
                            <Table.Head class="text-right">Action</Table.Head>
                        </Table.Row>
                    </Table.Header>
                    <Table.Body>
                        {#each instruments as instrument (instrument.id)}
                            <Table.Row>
                                <Table.Cell>
                                    <p class="font-medium">
                                        {instrument.display_name}
                                    </p>
                                    <p
                                        class="text-muted-foreground font-mono text-xs"
                                    >
                                        {instrument.code}{instrument.form_code
                                            ? ` · ${instrument.form_code}`
                                            : ''}
                                    </p>
                                    {#if instrument.is_synthetic}
                                        <Badge variant="secondary" class="mt-1"
                                            >Synthetic · software only</Badge
                                        >
                                    {/if}
                                </Table.Cell>
                                <Table.Cell
                                    >{instrument.version_number ??
                                        '—'}</Table.Cell
                                >
                                <Table.Cell
                                    ><Badge
                                        variant={instrument.status ===
                                        'approved'
                                            ? 'default'
                                            : 'secondary'}
                                        >{statusLabel(instrument.status)}</Badge
                                    ></Table.Cell
                                >
                                <Table.Cell>
                                    <Badge
                                        variant={instrument.source_readiness ===
                                        'reviewed'
                                            ? 'default'
                                            : 'outline'}
                                    >
                                        {instrument.source_readiness ===
                                        'reviewed'
                                            ? 'Reviewed'
                                            : 'Source hold'}
                                    </Badge>
                                </Table.Cell>
                                <Table.Cell>
                                    <Badge
                                        variant={instrument.profile_metadata?.is_final_only || instrument.raw_readiness === 'approved'
                                            ? 'default'
                                            : 'outline'}
                                    >
                                        {scoringMethodLabel(instrument)}
                                    </Badge>
                                </Table.Cell>
                                <Table.Cell>
                                    <Badge variant={instrument.norm_readiness === 'configured' ? 'default' : 'outline'}
                                        >{normLabel(
                                            instrument.norm_readiness,
                                        )}</Badge
                                    >
                                </Table.Cell>
                                <Table.Cell class="text-right">
                                    <div class="flex justify-end gap-2">
                                        {#if instrument.status === 'draft'}
                                            <Link href={`/setup/test-instruments/${instrument.id}/edit`}>
                                                <Button size="sm">Edit Draft</Button>
                                            </Link>
                                        {/if}
                                        <Link
                                            href={`/setup/test-instruments/${instrument.id}`}
                                        >
                                            <Button size="sm" variant="outline"
                                                >{instrument.status === 'draft' ? 'View' : 'Open Setup'}</Button
                                            >
                                        </Link>
                                    </div>
                                </Table.Cell>
                            </Table.Row>
                        {/each}
                    </Table.Body>
                </Table.Root>
            </div>

            <ul class="grid grid-cols-1 gap-3 md:hidden" role="list">
                {#each instruments as instrument (instrument.id)}
                    <li class="border-border bg-card min-w-0 rounded-xl border p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h2 class="font-semibold">
                                    {instrument.display_name}
                                </h2>
                                <p
                                    class="text-muted-foreground font-mono text-xs"
                                >
                                    {instrument.code}{instrument.form_code
                                        ? ` · ${instrument.form_code}`
                                        : ''}
                                </p>
                            </div>
                            <Badge
                                variant={instrument.status === 'approved'
                                    ? 'default'
                                    : 'secondary'}
                                >{statusLabel(instrument.status)}</Badge
                            >
                        </div>
                        {#if instrument.is_synthetic}
                            <Badge variant="secondary" class="mt-3"
                                >Synthetic · software only</Badge
                            >
                        {/if}
                        <dl
                            class="mt-4 grid grid-cols-1 gap-3 text-sm sm:grid-cols-3"
                        >
                            <div>
                                <dt class="text-muted-foreground">
                                    Source review
                                </dt>
                                <dd class="font-medium">
                                    {instrument.source_readiness === 'reviewed'
                                        ? 'Reviewed'
                                        : 'Source hold'}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-muted-foreground">
                                    {instrument.profile_metadata?.is_final_only ? 'Scoring method' : 'Raw scoring'}
                                </dt>
                                <dd class="font-medium">
                                    {scoringMethodLabel(instrument)}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-muted-foreground">
                                    Norm conversion
                                </dt>
                                <dd class="font-medium">
                                    {normLabel(instrument.norm_readiness)}
                                </dd>
                            </div>
                        </dl>
                        <div class="mt-4 grid grid-cols-2 gap-2">
                            {#if instrument.status === 'draft'}
                                <Link
                                    href={`/setup/test-instruments/${instrument.id}/edit`}
                                    class="block"
                                >
                                    <Button size="sm" class="min-h-11 w-full"
                                        >Edit Draft</Button
                                    >
                                </Link>
                            {/if}
                            <Link
                                href={`/setup/test-instruments/${instrument.id}`}
                                class="block"
                            >
                                <Button
                                    size="sm"
                                    variant="outline"
                                    class="min-h-11 w-full">{instrument.status === 'draft' ? 'View' : 'Open Setup'}</Button
                                >
                            </Link>
                        </div>
                    </li>
                {/each}
            </ul>
        {/if}
    </div>
</StaffLayout>
