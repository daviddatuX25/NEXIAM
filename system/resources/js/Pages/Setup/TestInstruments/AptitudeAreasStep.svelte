<script>
    import { resolveMetricBounds } from '@/lib/metric-graph';
    import BentoCard from '@/Components/kit/BentoCard.svelte';
    import SwitchableListView from '@/Components/kit/SwitchableListView.svelte';
    import { Badge } from '@/Components/ui/badge';
    import * as Table from '@/Components/ui/table';

    let {
        metrics = $bindable([]),
        subtests = [],
        tables = $bindable({}),
        constructs = [],
        errors = {},
        finalOnlyMode = false,
        onOpenModal = () => {},
    } = $props();

    let searchQuery = $state('');
    let selectedStatus = $state('All');
    let viewMode = $state('table');
    $effect(() => {
        if (finalOnlyMode && !['All', 'Configured'].includes(selectedStatus)) selectedStatus = 'All';
    });

    let visibleMetrics = $derived(metrics.filter((metric) => {
        const query = searchQuery.trim().toLowerCase();
        const matchesSearch = !query || `${metric.name ?? ''} ${metric.code ?? ''}`.toLowerCase().includes(query);
        const status = areaStatus(metric);
        const matchesStatus = finalOnlyMode
            ? (selectedStatus === 'All' || status === 'Configured')
            : (selectedStatus === 'All' || selectedStatus === status);
        return matchesSearch && matchesStatus;
    }));

    function areaStatus(metric) {
        if (metric.rule_kind === 'external_final') return 'Configured';
        if (!metric.type && !metric.rule_kind && !metric.subtest_code && !metric.input_codes?.length) {
            return 'Needs Formula';
        }
        const percentileRequired = (metric.required_output_scales ?? ['percentile_rank']).includes('percentile_rank');
        return (tables[metric.code] ?? []).length || !percentileRequired ? 'Configured' : 'Needs Table';
    }

    function formulaFor(metric) {
        if (metric.rule_kind === 'external_final') return 'Externally supplied PR (0–100)';
        const bounds = resolveMetricBounds(metric.code, metrics, subtests);
        const range = bounds ? `${bounds.min}–${bounds.max}` : 'unavailable';
        if (metric.rule_kind === 'formula_raw_metrics') {
            return `= ${String(metric.formula_expression ?? 'Incomplete formula').replace(/^=\s*/, '')} (Range: ${range})`;
        }
        const components = metric.input_codes ?? [];
        if (components.length) {
            const names = components.map((code) => metrics.find((item) => item.code === code)?.name ?? code);
            return `= ${names.join(' + ')} (Range: ${range})`;
        }
        const subtest = subtests.find((item) => item.code === metric.subtest_code);
        if (metric.rule_kind === 'recorded_measure') {
            return subtest ? `Recorded measure · ${subtest.name ?? subtest.code}` : 'Recorded measure';
        }
        return subtest ? `= ${subtest.name ?? subtest.code} Raw${metric.item_codes != null ? ` · ${metric.item_codes.length} selected items` : ''} (${range})` : 'Not configured';
    }

    function maxMetric(metric) {
        return Number(metric?.max_raw_value ?? metric?.max_raw_score ?? 0);
    }

    function maxRaw(subtest) {
        if (!subtest) return 0;
        if (Number.isFinite(Number(subtest.max_raw_value))) return Number(subtest.max_raw_value);
        if (Number.isFinite(Number(subtest.max_raw_score))) return Number(subtest.max_raw_score);
        return (subtest.items ?? []).filter((item) => item.is_scored !== false).length;
    }

    function constructName(metric) {
        const construct = constructs.find(
            (item) => String(item.id ?? item.code) === String(metric.construct_aptitude_area_id ?? metric.construct_id ?? ''),
        );
        return construct?.name ?? construct?.label ?? construct?.code ?? '';
    }

    function bandsStats(metric) {
        if (metric.rule_kind === 'external_final') return { count: 0, rangeText: 'Not applicable', sampleText: '' };
        const rows = tables[metric.code] ?? [];
        if (!rows.length) {
            return { count: 0, rangeText: (metric.required_output_scales ?? ['percentile_rank']).includes('percentile_rank') ? 'Not configured' : 'Raw only', sampleText: '' };
        }
        if (rows.length <= 3) {
            return {
                count: rows.length,
                rangeText: rows
                    .map((row) => `${row.min_score ?? 0}–${row.max_score ?? '—'} → ${row.output_value ?? '—'} PR`)
                    .join(' · '),
                sampleText: '',
            };
        }
        const sorted = [...rows].sort((a, b) => Number(a.min_score ?? 0) - Number(b.min_score ?? 0));
        const first = sorted[0];
        const mid = sorted[Math.floor(sorted.length / 2)];
        const last = sorted[sorted.length - 1];
        return {
            count: rows.length,
            rangeText: `Raw ${first.min_score ?? 0}–${last.max_score ?? '—'} pts → ${first.output_value ?? '—'}–${last.output_value ?? '—'} PR`,
            sampleText: `e.g. ${first.min_score}→${first.output_value} PR, ${mid.min_score}→${mid.output_value} PR, ${last.max_score}→${last.output_value} PR`,
        };
    }
    function hasDependents(metric) {
        return metrics.some((item) => item.code !== metric.code && (item.input_codes ?? []).includes(metric.code));
    }

    function openEditor(metric) {
        onOpenModal(metric.code, 'edit');
    }

    async function copyTable(metric) {
        const rows = tables[metric.code] ?? [];
        const csv = rows.map((row) => Object.values(row).join(',')).join('\n');
        await navigator.clipboard?.writeText(csv);
    }

    function deleteArea(metric) {
        if (hasDependents(metric)) {
            return;
        }
        metrics = metrics.filter((item) => item.code !== metric.code);
        const nextTables = { ...tables };
        delete nextTables[metric.code];
        tables = nextTables;
    }

    function action(metric, mode) {
        if (mode === 'delete') {
            deleteArea(metric);
            return;
        }
        if (mode === 'copy') {
            copyTable(metric);
            return;
        }
        onOpenModal(metric.code, mode);
    }
</script>

<section class="space-y-4" aria-label="Aptitude areas">
    <div class="flex flex-wrap items-center gap-3">
        <input class="border-input bg-background flex-1 min-w-[280px] h-10 rounded-md border px-3 text-sm" type="search" bind:value={searchQuery} placeholder="Search aptitude areas" aria-label="Search aptitude areas" />
        <select class="border-input bg-background h-10 rounded-md border px-3 text-sm" bind:value={selectedStatus} aria-label="Filter by status">
            <option>All</option><option>Configured</option>
            {#if !finalOnlyMode}<option>Needs Table</option><option>Needs Formula</option>{/if}
        </select>
        <button type="button" class="bg-primary text-primary-foreground min-h-10 rounded-md px-4 text-sm font-medium" onclick={() => onOpenModal(null, 'add')}>+ Add Aptitude Area</button>
    </div>
    {#if errors.metrics}<p class="text-destructive text-sm" role="alert">{errors.metrics}</p>{/if}
    <SwitchableListView bind:viewMode cardsInset={false}>
        {#snippet table()}
            <Table.Root class="w-full min-w-[900px] text-sm">
                <Table.Header class="bg-muted/40"><Table.Row>
                    <Table.Head>APTITUDE / CODE</Table.Head><Table.Head>{finalOnlyMode ? 'FINAL SCORE CONTRACT' : 'CALCULATION FORMULA'}</Table.Head><Table.Head>{finalOnlyMode ? 'PR PRECISION / SOURCE' : 'CONVERSION BANDS'}</Table.Head><Table.Head>STATUS</Table.Head><Table.Head>ACTIONS</Table.Head>
                </Table.Row></Table.Header>
                <Table.Body>
                    {#each visibleMetrics as metric (metric.code)}
                        {@const status = areaStatus(metric)}
                        {@const stats = bandsStats(metric)}
                        {@const construct = constructName(metric)}
                        <Table.Row class="transition-colors hover:bg-muted/30">
                            <Table.Cell>
                                <div class="font-medium">{metric.name}</div>
                                <div class="mt-0.5 flex flex-wrap items-center gap-1.5">
                                    <span class="text-muted-foreground font-mono text-xs">{metric.code}</span>
                                    {#if construct}
                                        <Badge variant="outline" class="text-[11px]">{construct}</Badge>
                                    {/if}
                                </div>
                            </Table.Cell>
                            <Table.Cell>
                                <span class="bg-muted/60 text-foreground/90 inline-block rounded-md px-2 py-0.5 font-mono text-xs">
                                    {formulaFor(metric)}
                                </span>
                            </Table.Cell>
                            <Table.Cell>
                                {#if finalOnlyMode}
                                    <span class="text-xs font-medium">Required percentile rank · {metric.precision_scale ?? 0} decimal places · manual / import</span>
                                {:else if stats.count === 0}
                                    <span class="text-muted-foreground text-xs">{stats.rangeText}</span>
                                {:else}
                                    <div class="flex flex-wrap items-center gap-2">
                                        <Badge class="border-emerald-500/20 bg-emerald-500/10 font-medium text-emerald-700 dark:text-emerald-300">
                                            {stats.count} {stats.count === 1 ? 'row' : 'rows'}
                                        </Badge>
                                        <span class="text-xs font-medium">{stats.rangeText}</span>
                                    </div>
                                    {#if stats.sampleText}
                                        <p class="text-muted-foreground mt-0.5 font-mono text-[11px]">{stats.sampleText}</p>
                                    {/if}
                                {/if}
                            </Table.Cell>
                            <Table.Cell><Badge variant={status === 'Configured' ? 'default' : 'outline'}>{status}</Badge></Table.Cell>
                            <Table.Cell><div class="flex items-center gap-2"><button type="button" class="text-primary text-sm font-medium hover:underline" onclick={() => openEditor(metric)}>Edit Area</button><details class="relative"><summary class="cursor-pointer list-none whitespace-nowrap rounded-md border px-3 py-1.5 text-xs">Actions ▾</summary><div class="bg-popover border-border absolute right-0 z-20 mt-1 grid min-w-40 rounded-md border p-1 shadow-md">{#if !finalOnlyMode}<button class="rounded px-3 py-2 text-left text-sm hover:bg-muted" type="button" onclick={() => action(metric, 'copy')}>Copy Table</button>{/if}<button class="text-destructive rounded px-3 py-2 text-left text-sm hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50" type="button" disabled={hasDependents(metric)} title={hasDependents(metric) ? 'This area is an input to another raw metric.' : undefined} onclick={() => action(metric, 'delete')}>Delete Area</button></div></details></div></Table.Cell>
                        </Table.Row>
                    {:else}
                        <Table.Row><Table.Cell colspan={5} class="text-muted-foreground py-8 text-center">No aptitude areas found.</Table.Cell></Table.Row>
                    {/each}
                </Table.Body>
            </Table.Root>
        {/snippet}
        {#snippet cards()}
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                {#each visibleMetrics as metric (metric.code)}
                    {@const status = areaStatus(metric)}
                    {@const stats = bandsStats(metric)}
                    {@const construct = constructName(metric)}
                    <BentoCard class="flex flex-col justify-between gap-3 p-4">
                        <div class="space-y-2.5">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <h3 class="truncate font-semibold">{metric.name}</h3>
                                    <div class="mt-0.5 flex flex-wrap items-center gap-1.5">
                                        <p class="text-muted-foreground font-mono text-xs">{metric.code}</p>
                                        {#if construct}
                                            <Badge variant="outline" class="text-[11px]">{construct}</Badge>
                                        {/if}
                                    </div>
                                </div>
                                <Badge variant={status === 'Configured' ? 'default' : 'outline'}>{status}</Badge>
                            </div>
                            <div>
                                <span class="bg-muted/60 text-foreground/90 inline-block rounded-md px-2 py-0.5 font-mono text-xs">
                                    {formulaFor(metric)}
                                </span>
                            </div>
                            <div class="border-border/60 bg-muted/20 rounded-lg border px-3 py-2 text-xs">
                                {#if finalOnlyMode}
                                    <span class="font-medium">Required final PR · {metric.precision_scale ?? 0} decimal places · manual / import</span>
                                {:else if stats.count === 0}
                                    <span class="text-muted-foreground">{stats.rangeText}</span>
                                {:else}
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <span class="font-medium">{stats.rangeText}</span>
                                        <Badge class="border-emerald-500/20 bg-emerald-500/10 font-medium text-emerald-700 dark:text-emerald-300">
                                            {stats.count} rows
                                        </Badge>
                                    </div>
                                    {#if stats.sampleText}
                                        <p class="text-muted-foreground mt-1 font-mono text-[11px]">{stats.sampleText}</p>
                                    {/if}
                                {/if}
                            </div>
                        </div>
                        <div class="border-border/60 flex items-center justify-between border-t pt-2.5"><button type="button" class="text-primary text-sm font-medium hover:underline" onclick={() => openEditor(metric)}>Edit Area</button><details class="relative"><summary class="cursor-pointer list-none rounded-md border px-3 py-1.5 text-xs">Actions ▾</summary><div class="bg-popover border-border absolute right-0 z-20 mt-1 grid min-w-40 rounded-md border p-1 shadow-md">{#if !finalOnlyMode}<button class="rounded px-3 py-2 text-left text-sm hover:bg-muted" type="button" onclick={() => action(metric, 'copy')}>Copy Table</button>{/if}<button class="text-destructive rounded px-3 py-2 text-left text-sm hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50" type="button" disabled={hasDependents(metric)} title={hasDependents(metric) ? 'This area is an input to another raw metric.' : undefined} onclick={() => action(metric, 'delete')}>Delete Area</button></div></details></div>
                    </BentoCard>
                {:else}
                    <p class="text-muted-foreground col-span-full py-8 text-center text-sm">No aptitude areas found.</p>
                {/each}
            </div>
        {/snippet}
    </SwitchableListView>
</section>
