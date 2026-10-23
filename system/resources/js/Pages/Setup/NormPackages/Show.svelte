<script>
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
    import { Badge } from '@/Components/ui/badge';
    import { Button } from '@/Components/ui/button';
    import { Input } from '@/Components/ui/input';
    import { Label } from '@/Components/ui/label';
    import * as Table from '@/Components/ui/table';
    import { Link, useForm } from '@inertiajs/svelte';
    import { Archive, ArrowLeft, Pencil, ShieldCheck } from 'lucide-svelte';
    import { onMount } from 'svelte';

    let { norm_package, request_id } = $props();
    let fromKit = $state(null);
    let fromVersion = $state(null);
    onMount(() => {
        const params = new URLSearchParams(window.location.search);
        fromKit = params.get('from_kit');
        fromVersion = params.get('version');
    });
    const breadcrumbs = [
        { label: 'Setup', href: '/setup' },
        { label: 'Norm packages', href: '/setup/norm-packages' },
        { label: norm_package.code },
    ];

    const approvalForm = useForm({
        request_id,
        expected_revision: null,
        source_review_attestation: false,
    });
    const retirementForm = useForm({
        request_id,
        expected_revision: null,
        reason: '',
    });

    function statusLabel(status) {
        if (status === 'approved') return 'Approved';
        if (status === 'draft') return 'Draft';
        return 'Retired';
    }

    function statusVariant(status) {
        if (status === 'approved') return 'default';
        if (status === 'draft') return 'secondary';
        return 'outline';
    }

    function groupIntervals(tables = []) {
        const metrics = new Map();

        for (const table of tables) {
            if (!metrics.has(table.metric_code)) {
                metrics.set(table.metric_code, new Map());
            }

            const intervals = metrics.get(table.metric_code);
            for (const interval of table.intervals_json ?? []) {
                const key = `${interval.min_score}:${interval.max_score}`;
                const row = intervals.get(key) ?? {
                    min: interval.min_score,
                    max: interval.max_score,
                    percentile_rank: null,
                };
                if (table.output_scale === 'percentile_rank') {
                    row.percentile_rank = interval.output_value;
                }
                intervals.set(key, row);
            }
        }

        return [...metrics.entries()].map(([metric_code, intervals]) => ({
            metric_code,
            rows: [...intervals.values()].sort((left, right) => left.min - right.min),
        }));
    }

    function approve(version) {
        approvalForm.expected_revision = version.lock_version;
        approvalForm.post(
            `/setup/norm-packages/${norm_package.id}/versions/${version.id}/approve`,
        );
    }

    function retire(event, version) {
        event.preventDefault();
        retirementForm.expected_revision = version.lock_version;
        retirementForm.post(
            `/setup/norm-packages/${norm_package.id}/versions/${version.id}/retire`,
            { preserveScroll: true, preserveState: 'errors' },
        );
    }
</script>

<svelte:head><title>{norm_package.display_name} · Norm packages</title></svelte:head>

<StaffLayout {breadcrumbs}>
    <div class="min-w-0 space-y-6 p-4 sm:p-6">
        <PageHeaderBar
            title={norm_package.display_name}
            description={`${norm_package.code}${norm_package.version_number ? ` · Version ${norm_package.version_number}` : ''} · Confidential norm package`}
        >
            {#snippet actions()}
                {#if fromKit}
                    <a
                        href={`/setup/exam-materials/test-kits/${fromKit}/${fromVersion}`}
                    >
                        <Button variant="outline" size="sm" class="gap-1.5">
                            <ArrowLeft class="size-4" /> Back to Test Kit
                        </Button>
                    </a>
                {/if}
                <Link href="/setup/norm-packages">
                    <Button variant="outline" class="min-h-10 gap-2">
                        <ArrowLeft class="h-4 w-4" />Back to packages
                    </Button>
                </Link>
                <Link href={`/setup/norm-packages/${norm_package.id}/edit`}>
                    <Button variant="outline" class="min-h-10 gap-2">
                        <Pencil class="h-4 w-4" />{norm_package.status === 'draft' ? 'Edit draft' : 'Create successor draft'}
                    </Button>
                </Link>
            {/snippet}
        </PageHeaderBar>

        {#if norm_package.is_synthetic}
            <div class="rounded-xl border border-amber-500/40 bg-amber-500/10 p-4 text-sm" role="status">
                Synthetic · software only. This package is not eligible for institutional assessment, conversion, finalization, or release.
            </div>
        {/if}

        <section class="border-border bg-card rounded-xl border p-4 sm:p-6" aria-labelledby="package-details-heading">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 id="package-details-heading" class="font-semibold">Package details</h2>
                <Badge variant={statusVariant(norm_package.status)}>{statusLabel(norm_package.status)}</Badge>
            </div>
            <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2 xl:grid-cols-3">
                <div>
                    <dt class="text-muted-foreground">Code</dt>
                    <dd class="mt-1 font-mono font-medium">{norm_package.code}</dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">Publisher</dt>
                    <dd class="mt-1 font-medium">{norm_package.publisher_name || '—'}</dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">Form code</dt>
                    <dd class="mt-1 font-medium">{norm_package.form_code || '—'}</dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">Edition</dt>
                    <dd class="mt-1 font-medium">{norm_package.edition || '—'}</dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">Language</dt>
                    <dd class="mt-1 font-medium">{norm_package.language || '—'}</dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">Applicable population</dt>
                    <dd class="mt-1 font-medium">{norm_package.applicable_population || '—'}</dd>
                </div>
                <div class="sm:col-span-2 xl:col-span-3">
                    <dt class="text-muted-foreground">Source reference</dt>
                    <dd class="mt-1 whitespace-pre-wrap break-words">{norm_package.source_reference || 'Not recorded'}</dd>
                </div>
                <div class="sm:col-span-2 xl:col-span-3">
                    <dt class="text-muted-foreground">Permitted use reference</dt>
                    <dd class="mt-1 whitespace-pre-wrap break-words">{norm_package.permitted_use_reference || 'Not recorded'}</dd>
                </div>
            </dl>
        </section>

        <section class="space-y-4" aria-labelledby="versions-heading">
            <div>
                <h2 id="versions-heading" class="text-lg font-semibold">Versions and conversion tables</h2>
                <p class="text-muted-foreground mt-1 text-sm">
                    Approved versions are immutable. A later edit creates a successor draft; accepted conversions retain their original version and table identities.
                </p>
            </div>

            {#each norm_package.versions as version (version.id)}
                <article class="border-border bg-card min-w-0 space-y-5 rounded-xl border p-4 sm:p-6">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <h3 class="font-semibold">Version {version.version_number}: {version.display_name}</h3>
                            <p class="text-muted-foreground mt-1 text-sm">
                                {version.publisher_name || 'Publisher not recorded'}
                                {version.form_code ? ` · Form ${version.form_code}` : ''}
                                {version.edition ? ` · ${version.edition}` : ''}
                                {version.language ? ` · ${version.language}` : ''}
                            </p>
                        </div>
                        <Badge variant={statusVariant(version.status)}>{statusLabel(version.status)}</Badge>
                    </div>

                    <dl class="grid gap-3 text-sm sm:grid-cols-2 xl:grid-cols-3">
                        <div>
                            <dt class="text-muted-foreground">Applicable population</dt>
                            <dd class="mt-1">{version.applicable_population || '—'}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">Source review</dt>
                            <dd class="mt-1">{version.source_reviewed_at || 'Not reviewed'}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">Approved</dt>
                            <dd class="mt-1">{version.approved_at || 'Not approved'}</dd>
                        </div>
                        {#if version.retired_at}
                            <div>
                                <dt class="text-muted-foreground">Retired</dt>
                                <dd class="mt-1">{version.retired_at}</dd>
                            </div>
                        {/if}
                        <div class="sm:col-span-2 xl:col-span-3">
                            <dt class="text-muted-foreground">Source and permitted use</dt>
                            <dd class="mt-1 whitespace-pre-wrap break-words">{version.source_reference || 'Source not recorded'}</dd>
                            <dd class="text-muted-foreground mt-1 whitespace-pre-wrap break-words">{version.permitted_use_reference || 'Permitted use not recorded'}</dd>
                        </div>
                    </dl>

                    {#if version.tables.length === 0}
                        <p class="border-border text-muted-foreground rounded-lg border border-dashed p-4 text-sm">
                            No conversion tables are recorded in this version.
                        </p>
                    {:else}
                        <div class="space-y-4">
                            {#each groupIntervals(version.tables) as metric (metric.metric_code)}
                                <div class="min-w-0">
                                    <h4 class="mb-2 text-sm font-semibold">Raw metric: <span class="font-mono">{metric.metric_code}</span></h4>
                                    <div class="overflow-x-auto rounded-lg border border-border">
                                        <Table.Root class="min-w-160">
                                            <Table.Header>
                                                <Table.Row>
                                                    <Table.Head>min</Table.Head>
                                                    <Table.Head>max</Table.Head>
                                                    <Table.Head>Percentile rank</Table.Head>
                                                </Table.Row>
                                            </Table.Header>
                                            <Table.Body>
                                                {#each metric.rows as row, rowIndex (`${row.min}:${row.max}:${rowIndex}`)}
                                                    <Table.Row>
                                                        <Table.Cell>{row.min}</Table.Cell>
                                                        <Table.Cell>{row.max}</Table.Cell>
                                                        <Table.Cell>{row.percentile_rank ?? '—'}</Table.Cell>
                                                        <Table.Cell>{row.percentile_rank ?? '—'}</Table.Cell>
                                                    </Table.Row>
                                                {/each}
                                            </Table.Body>
                                        </Table.Root>
                                    </div>
                                </div>
                            {/each}
                        </div>
                    {/if}

                    {#if version.status === 'draft'}
                        <div class="border-border bg-muted/20 space-y-3 rounded-lg border p-4">
                            <label class="flex items-start gap-3 text-sm">
                                <input
                                    type="checkbox"
                                    bind:checked={approvalForm.source_review_attestation}
                                    class="accent-primary mt-1 size-4"
                                />
                                <span>
                                    I affirm that I reviewed and verified this version's source, permitted use, form, applicability, and conversion table content before approval.
                                </span>
                            </label>
                            {#if approvalForm.errors.source_review_attestation}<p class="text-destructive text-sm" role="alert">{approvalForm.errors.source_review_attestation}</p>{/if}
                            {#if approvalForm.errors.tables}<p class="text-destructive text-sm" role="alert">{approvalForm.errors.tables}</p>{/if}
                            {#if approvalForm.errors.expected_revision}<p class="text-destructive text-sm" role="alert">{approvalForm.errors.expected_revision}</p>{/if}
                            {#if approvalForm.errors.version}<p class="text-destructive text-sm" role="alert">{approvalForm.errors.version}</p>{/if}
                            <Button
                                class="min-h-10 gap-2"
                                disabled={approvalForm.processing || !approvalForm.source_review_attestation}
                                onclick={() => approve(version)}
                            >
                                <ShieldCheck class="h-4 w-4" />Approve version
                            </Button>
                        </div>
                    {:else if version.status === 'approved'}
                        <form class="grid gap-3 border-t border-border pt-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end" onsubmit={(event) => retire(event, version)}>
                            <div class="space-y-1.5">
                                <Label for={`retire-reason-${version.id}`}>Retirement reason</Label>
                                <Input id={`retire-reason-${version.id}`} bind:value={retirementForm.reason} maxlength="500" required />
                                {#if retirementForm.errors.reason}<p class="text-destructive text-sm" role="alert">{retirementForm.errors.reason}</p>{/if}
                                {#if retirementForm.errors.expected_revision}<p class="text-destructive text-sm" role="alert">{retirementForm.errors.expected_revision}</p>{/if}
                                {#if retirementForm.errors.version}<p class="text-destructive text-sm" role="alert">{retirementForm.errors.version}</p>{/if}
                            </div>
                            <Button type="submit" variant="destructive" class="min-h-10 gap-2" disabled={retirementForm.processing}>
                                <Archive class="h-4 w-4" />Retire version
                            </Button>
                        </form>
                    {/if}
                </article>
            {:else}
                <div class="border-border text-muted-foreground rounded-xl border border-dashed p-8 text-center text-sm">
                    This package has no version history.
                </div>
            {/each}
        </section>

        <div class="border-border bg-muted/20 text-muted-foreground rounded-xl border p-4 text-sm">
            Norm package tables are restricted to authorized Guidance catalog staff. Scorer-facing selection and accepted outputs must not expose these table rows.
        </div>
    </div>
</StaffLayout>
