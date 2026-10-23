<script>
    import { Button } from '@/Components/ui/button';
    import { Input } from '@/Components/ui/input';
    import { Label } from '@/Components/ui/label';
    import { Link, useForm } from '@inertiajs/svelte';
    import { ArrowLeft, Calculator, Layers, Plus, Sparkles, TableProperties, Trash2, Variable } from 'lucide-svelte';
    import InfoPopover from '@/Components/kit/InfoPopover.svelte';
    import { previewNormConversion } from '@/lib/assessment-score-preview';

    let {
        mode = 'create',
        package: normPackage = null,
        version = null,
        is_successor = false,
        request_id,
        test_instrument_id = null,
        instrument_id = null,
    } = $props();

    const editing = mode === 'edit';
    const cancelHref = editing
        ? `/setup/norm-packages/${normPackage.id}`
        : '/setup/norm-packages';
    const linkedInstrumentId =
        test_instrument_id ??
        instrument_id ??
        (typeof window !== 'undefined'
            ? new URLSearchParams(window.location.search).get('test_instrument_id') ??
              new URLSearchParams(window.location.search).get('instrument_id')
            : null);
    const instrumentHref = linkedInstrumentId
        ? `/setup/test-instruments/${linkedInstrumentId}`
        : null;
    const instrumentEditHref = linkedInstrumentId
        ? `/setup/test-instruments/${linkedInstrumentId}/edit`
        : null;
    let sampleRawScores = $state({});

    function parseSampleNumber(value) {
        if (typeof value !== 'string' || value.trim() === '') {
            return null;
        }

        const parsed = Number(value);
        return Number.isFinite(parsed) ? parsed : null;
    }

    function previewForTable(tableIndex) {
        const rawScore = parseSampleNumber(sampleRawScores[tableIndex] ?? '');
        if (rawScore === null) {
            return null;
        }

        const table = form.tables[tableIndex];
        return previewNormConversion(
            rawScore,
            table.intervals.map((interval) => ({
                min_score: parseSampleNumber(String(interval.min_score)) ?? Number.NaN,
                max_score: parseSampleNumber(String(interval.max_score)) ?? Number.NaN,
                output_value: parseSampleNumber(String(interval.output_value)) ?? Number.NaN,
            })),
        );
    }

    function matchingIntervalForTable(tableIndex) {
        const sampleScore = parseSampleNumber(sampleRawScores[tableIndex] ?? '');
        if (sampleScore === null) return null;
        const table = form.tables[tableIndex];
        return table.intervals.find((interval) => {
            const minimum = parseSampleNumber(String(interval.min_score));
            const maximum = parseSampleNumber(String(interval.max_score));
            return minimum !== null && maximum !== null && minimum <= maximum && sampleScore >= minimum && sampleScore <= maximum;
        }) ?? null;
    }

    const form = useForm({
        request_id,
        ...(editing && !is_successor
            ? { expected_revision: version.lock_version }
            : {}),
        code: normPackage?.code ?? '',
        name: version?.display_name ?? '',
        publisher_name: version?.publisher_name ?? '',
        form_code: version?.form_code ?? '',
        edition: version?.edition ?? '',
        language: version?.language ?? '',
        applicable_population: version?.applicable_population ?? '',
        source_reference: version?.source_reference ?? '',
        permitted_use_reference: version?.permitted_use_reference ?? '',
        tables: (version?.tables ?? []).map((table) => ({
            metric_code: table.metric_code,
            output_scale: table.output_scale,
            intervals: (table.intervals ?? []).map((interval) => ({
                min_score: interval.min_score,
                max_score: interval.max_score,
                output_value: interval.output_value,
            })),
        })),
    });

    const outputScales = [
        { value: 'percentile_rank', label: 'Converted / Percentile score (0–100)' },
    ];

    function addTable() {
        form.tables = [
            ...form.tables,
            { metric_code: '', output_scale: 'percentile_rank', intervals: [] },
        ];
    }

    function updateTable(tableIndex, field, value) {
        form.tables = form.tables.map((table, index) =>
            index === tableIndex ? { ...table, [field]: value } : table,
        );
    }

    function removeTable(tableIndex) {
        form.tables = form.tables.filter((_, index) => index !== tableIndex);
    }

    function addInterval(tableIndex) {
        form.tables = form.tables.map((table, index) =>
            index === tableIndex
                ? {
                      ...table,
                      intervals: [
                          ...table.intervals,
                          { min_score: '', max_score: '', output_value: '' },
                      ],
                  }
                : table,
        );
    }

    function updateInterval(tableIndex, intervalIndex, field, value) {
        form.tables = form.tables.map((table, index) =>
            index === tableIndex
                ? {
                      ...table,
                      intervals: table.intervals.map((interval, rowIndex) =>
                          rowIndex === intervalIndex
                              ? { ...interval, [field]: value }
                              : interval,
                      ),
                  }
                : table,
        );
    }

    function removeInterval(tableIndex, intervalIndex) {
        form.tables = form.tables.map((table, index) =>
            index === tableIndex
                ? {
                      ...table,
                      intervals: table.intervals.filter(
                          (_, rowIndex) => rowIndex !== intervalIndex,
                      ),
                  }
                : table,
        );
    }

    function submit(event) {
        event.preventDefault();
        if (editing) {
            form.put(`/setup/norm-packages/${normPackage.id}`);
        } else {
            form.post('/setup/norm-packages');
        }
    }
</script>
<nav aria-label="Assessment setup stages" class="border-border bg-card flex flex-wrap gap-2 rounded-xl border p-2">
    {#if instrumentHref}
        <Link href={`${instrumentEditHref}?stage=subtests`} class="text-muted-foreground inline-flex min-h-11 items-center gap-2 rounded-lg px-3 text-sm font-medium hover:bg-muted hover:text-foreground">
            <ArrowLeft class="h-4 w-4" /><Layers class="h-4 w-4" />1. Subtests & Items
        </Link>
        <Link href={`${instrumentEditHref}?stage=formulas`} class="text-muted-foreground inline-flex min-h-11 items-center gap-2 rounded-lg px-3 text-sm font-medium hover:bg-muted hover:text-foreground">
            <Variable class="h-4 w-4" />2. Composite Formulas & Rules
        </Link>
    {/if}
    <span aria-current="step" class="bg-primary text-primary-foreground inline-flex min-h-11 items-center gap-2 rounded-lg px-3 text-sm font-medium">
        <TableProperties class="h-4 w-4" />3. Norm Conversion Tables
    </span>
    {#if instrumentHref}
        <Link href={instrumentHref} class="text-primary ml-auto inline-flex min-h-11 items-center gap-2 rounded-lg px-3 text-sm font-medium hover:underline">
            Return to instrument <ArrowLeft class="h-4 w-4" />
        </Link>
    {/if}
</nav>

{#if editing && normPackage.is_synthetic}
    <div class="rounded-xl border border-amber-500/40 bg-amber-500/10 p-4 text-sm" role="status">
        Synthetic · software only. This package cannot be used for institutional scoring or activation.
    </div>
{/if}

<form class="space-y-6" onsubmit={submit}>
    <section class="border-border bg-card space-y-5 rounded-xl border p-4 sm:p-6" aria-labelledby="identity-heading">
        <div>
            <h2 id="identity-heading" class="flex items-center gap-1.5 font-semibold">
                <span>Package and source identity</span>
                <InfoPopover
                    title="Norm package provenance"
                    content="Identifies the normative reference table package (code, form, edition, population, and source citation) used to convert raw metric scores into 0–100 percentile ranks."
                />
            </h2>
            <p class="text-muted-foreground mt-1 text-sm">
                Enter source references only. A package name or populated table does not establish scientific validity or applicability.
            </p>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-1.5">
                <Label for="norm-code">Package code</Label>
                <Input id="norm-code" bind:value={form.code} maxlength="64" class="min-h-10" disabled={editing} required />
                {#if form.errors.code}<p class="text-destructive text-sm" role="alert">{form.errors.code}</p>{/if}
            </div>
            <div class="space-y-1.5">
                <Label for="norm-name">Package name</Label>
                <Input id="norm-name" bind:value={form.name} maxlength="255" class="min-h-10" required />
                {#if form.errors.name}<p class="text-destructive text-sm" role="alert">{form.errors.name}</p>{/if}
            </div>
            <div class="space-y-1.5">
                <Label for="norm-publisher">Publisher</Label>
                <Input id="norm-publisher" bind:value={form.publisher_name} maxlength="255" class="min-h-10" />
                {#if form.errors.publisher_name}<p class="text-destructive text-sm" role="alert">{form.errors.publisher_name}</p>{/if}
            </div>
            <div class="space-y-1.5">
                <Label for="norm-form">Form code</Label>
                <Input id="norm-form" bind:value={form.form_code} maxlength="64" class="min-h-10" required />
                {#if form.errors.form_code}<p class="text-destructive text-sm" role="alert">{form.errors.form_code}</p>{/if}
            </div>
            <div class="space-y-1.5">
                <Label for="norm-edition">Edition</Label>
                <Input id="norm-edition" bind:value={form.edition} maxlength="128" class="min-h-10" required />
                {#if form.errors.edition}<p class="text-destructive text-sm" role="alert">{form.errors.edition}</p>{/if}
            </div>
            <div class="space-y-1.5">
                <Label for="norm-language">Language</Label>
                <Input id="norm-language" bind:value={form.language} maxlength="128" class="min-h-10" required />
                {#if form.errors.language}<p class="text-destructive text-sm" role="alert">{form.errors.language}</p>{/if}
            </div>
            <div class="space-y-1.5 sm:col-span-2">
                <Label for="norm-population">Applicable population</Label>
                <Input id="norm-population" bind:value={form.applicable_population} maxlength="255" class="min-h-10" />
                {#if form.errors.applicable_population}<p class="text-destructive text-sm" role="alert">{form.errors.applicable_population}</p>{/if}
            </div>
            <div class="space-y-1.5 sm:col-span-2">
                <Label for="norm-source-reference">Source reference</Label>
                <textarea id="norm-source-reference" bind:value={form.source_reference} rows="3" maxlength="2048" class="border-input bg-background placeholder:text-muted-foreground focus-visible:ring-ring w-full rounded-md border px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-2"></textarea>
                {#if form.errors.source_reference}<p class="text-destructive text-sm" role="alert">{form.errors.source_reference}</p>{/if}
            </div>
            <div class="space-y-1.5 sm:col-span-2">
                <Label for="norm-permitted-use">Permitted use reference</Label>
                <textarea id="norm-permitted-use" bind:value={form.permitted_use_reference} rows="3" maxlength="2048" class="border-input bg-background placeholder:text-muted-foreground focus-visible:ring-ring w-full rounded-md border px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-2"></textarea>
                {#if form.errors.permitted_use_reference}<p class="text-destructive text-sm" role="alert">{form.errors.permitted_use_reference}</p>{/if}
            </div>
        </div>
    </section>

    <section class="border-border bg-card space-y-5 rounded-xl border p-4 sm:p-6" aria-labelledby="tables-heading">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h2 id="tables-heading" class="flex items-center gap-1.5 font-semibold">
                    <span>Conversion tables</span>
                    <InfoPopover
                        title="Metric-to-Percentile tables"
                        content="Each table maps one raw metric code (e.g., VOCABULARY_RAW, GENERAL_ABILITY_RAW, MANUAL_DEXTERITY_RAW) from min..max raw score intervals to a 0–100 percentile rank."
                    />
                </h2>
                <p class="text-muted-foreground mt-1 text-sm">
                    Keep each raw metric and output scale explicit. Enter only source-defined intervals; do not infer missing values or endpoints.
                </p>
            </div>
            <Button type="button" variant="outline" class="min-h-10 gap-2" onclick={addTable}>
                <Plus class="h-4 w-4" />Add table
            </Button>
        </div>

        {#if form.errors.tables}<p class="text-destructive text-sm" role="alert">{form.errors.tables}</p>{/if}
        {#if form.tables.length === 0}
            <p class="border-border text-muted-foreground rounded-lg border border-dashed p-4 text-sm">
                No tables added. A draft can be saved without tables and remains unavailable for conversion and approval until its required tables are verified.
            </p>
        {/if}

        <div class="space-y-4">
            {#each form.tables as table, tableIndex (tableIndex)}
                <fieldset class="border-border min-w-0 space-y-4 rounded-lg border p-4">
                    <legend class="px-1 text-sm font-medium">Table {tableIndex + 1}</legend>
                    <div class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] sm:items-end">
                        <div class="space-y-1.5">
                            <div class="flex items-center gap-1.5">
                                <Label for={`metric-${tableIndex}`}>Raw metric code</Label>
                                <InfoPopover
                                    title="Target raw metric code"
                                    content="Must match the exact metric code declared in your Test Setup (e.g., VOCABULARY_RAW, COMPUTATION_RAW, SPATIAL_RAW, WORD_COMP_RAW, GENERAL_ABILITY_RAW, MANUAL_DEXTERITY_RAW)."
                                />
                            </div>
                            <Input id={`metric-${tableIndex}`} value={table.metric_code} maxlength="64" class="min-h-10" oninput={(event) => updateTable(tableIndex, 'metric_code', event.currentTarget.value)} />
                        </div>
                        <div class="space-y-1.5">
                            <Label for={`scale-${tableIndex}`}>Output scale</Label>
                            <select id={`scale-${tableIndex}`} value={table.output_scale} onchange={(event) => updateTable(tableIndex, 'output_scale', event.currentTarget.value)} class="border-input bg-background h-10 w-full rounded-md border px-3 text-sm">
                                {#each outputScales as scale (scale.value)}
                                    <option value={scale.value}>{scale.label}</option>
                                {/each}
                            </select>
                        </div>
                        <Button type="button" variant="ghost" class="min-h-10 gap-2 text-destructive hover:text-destructive" onclick={() => removeTable(tableIndex)}>
                            <Trash2 class="h-4 w-4" />Remove
                        </Button>
                    </div>
                    {#if form.errors[`tables.${tableIndex}.metric_code`]}<p class="text-destructive text-sm" role="alert">{form.errors[`tables.${tableIndex}.metric_code`]}</p>{/if}
                    {#if form.errors[`tables.${tableIndex}.output_scale`]}<p class="text-destructive text-sm" role="alert">{form.errors[`tables.${tableIndex}.output_scale`]}</p>{/if}

                    <div class="overflow-x-auto rounded-lg border border-border">
                        <table class="w-full min-w-150 text-sm">
                            <thead class="bg-muted/50 text-left">
                                <tr>
                                    <th class="px-3 py-2 font-medium">min</th>
                                    <th class="px-3 py-2 font-medium">max</th>
                                    <th class="px-3 py-2 font-medium">{table.output_scale}</th>
                                    <th class="w-20 px-3 py-2"><span class="sr-only">Remove row</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                {#each table.intervals as interval, intervalIndex (intervalIndex)}
                                    <tr class="border-t border-border">
                                        <td class="px-3 py-2">
                                            <Input type="number" step="1" value={interval.min_score} class="min-h-10 min-w-24" aria-label={`Minimum score, table ${tableIndex + 1}, row ${intervalIndex + 1}`} oninput={(event) => updateInterval(tableIndex, intervalIndex, 'min_score', event.currentTarget.value)} />
                                            {#if form.errors[`tables.${tableIndex}.intervals.${intervalIndex}.min_score`]}<p class="text-destructive mt-1 text-xs" role="alert">{form.errors[`tables.${tableIndex}.intervals.${intervalIndex}.min_score`]}</p>{/if}
                                        </td>
                                        <td class="px-3 py-2">
                                            <Input type="number" step="1" value={interval.max_score} class="min-h-10 min-w-24" aria-label={`Maximum score, table ${tableIndex + 1}, row ${intervalIndex + 1}`} oninput={(event) => updateInterval(tableIndex, intervalIndex, 'max_score', event.currentTarget.value)} />
                                            {#if form.errors[`tables.${tableIndex}.intervals.${intervalIndex}.max_score`]}<p class="text-destructive mt-1 text-xs" role="alert">{form.errors[`tables.${tableIndex}.intervals.${intervalIndex}.max_score`]}</p>{/if}
                                        </td>
                                        <td class="px-3 py-2">
                                            <Input value={interval.output_value} inputmode="decimal" maxlength="64" class="min-h-10 min-w-32" aria-label={`${table.output_scale} output, table ${tableIndex + 1}, row ${intervalIndex + 1}`} oninput={(event) => updateInterval(tableIndex, intervalIndex, 'output_value', event.currentTarget.value)} />
                                            {#if form.errors[`tables.${tableIndex}.intervals.${intervalIndex}.output_value`]}<p class="text-destructive mt-1 text-xs" role="alert">{form.errors[`tables.${tableIndex}.intervals.${intervalIndex}.output_value`]}</p>{/if}
                                        </td>
                                        <td class="px-3 py-2 text-right">
                                            <Button type="button" variant="ghost" size="icon" class="size-10 text-muted-foreground" aria-label={`Remove interval ${intervalIndex + 1}`} onclick={() => removeInterval(tableIndex, intervalIndex)}>
                                                <Trash2 class="h-4 w-4" />
                                            </Button>
                                        </td>
                                    </tr>
                                {:else}
                                    <tr><td colspan="4" class="text-muted-foreground px-3 py-4 text-center">No intervals. Add source-defined ranges when available.</td></tr>
                                {/each}
                            </tbody>
                        </table>
                    </div>
                    {#if form.errors[`tables.${tableIndex}.intervals`]}<p class="text-destructive text-sm" role="alert">{form.errors[`tables.${tableIndex}.intervals`]}</p>{/if}
                    <Button type="button" variant="outline" class="min-h-10 gap-2" onclick={() => addInterval(tableIndex)}>
                        <Plus class="h-4 w-4" />Add interval
                    </Button>
                    <section class="border-primary/20 from-primary/5 to-background space-y-4 rounded-xl border bg-gradient-to-br p-4" aria-label={`Interactive test simulator for table ${tableIndex + 1}`}>
                        <div class="flex items-start gap-3">
                            <div class="bg-primary/10 text-primary flex size-10 shrink-0 items-center justify-center rounded-xl">
                                <Calculator class="h-5 w-5" />
                            </div>
                            <div>
                                <h3 class="flex items-center gap-2 font-semibold">
                                    Interactive Test Simulator <Sparkles class="text-primary h-4 w-4" />
                                </h3>
                                <p class="text-muted-foreground mt-1 text-sm">
                                    Try a sample raw score against the interval rows above. This preview does not change or save the table.
                                </p>
                            </div>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)] sm:items-end">
                            <div class="space-y-1.5">
                                <Label for={`simulator-score-${tableIndex}`}>Sample raw score</Label>
                                <Input
                                    id={`simulator-score-${tableIndex}`}
                                    type="number"
                                    step="any"
                                    value={sampleRawScores[tableIndex] ?? ''}
                                    class="min-h-11"
                                    placeholder="e.g. 35"
                                    oninput={(event) => (sampleRawScores = { ...sampleRawScores, [tableIndex]: event.currentTarget.value })}
                                />
                            </div>
                            <div class="bg-background/80 rounded-lg border p-3" aria-live="polite" role="status">
                                <p class="text-muted-foreground text-xs font-medium uppercase tracking-wide">{table.output_scale} preview</p>
                                {#if previewForTable(tableIndex) !== null}
                                    <p class="mt-1 text-2xl font-semibold">{previewForTable(tableIndex)}</p>
                                    {#if matchingIntervalForTable(tableIndex)}
                                        <p class="text-muted-foreground mt-1 text-xs">
                                            Matched interval: {matchingIntervalForTable(tableIndex).min_score}–{matchingIntervalForTable(tableIndex).max_score}
                                        </p>
                                    {/if}
                                {:else if parseSampleNumber(sampleRawScores[tableIndex] ?? '') !== null}
                                    <p class="mt-1 text-sm font-medium">No matching interval</p>
                                    <p class="text-muted-foreground mt-1 text-xs">Add or correct the source-defined range that includes this score.</p>
                                {:else}
                                    <p class="text-muted-foreground mt-1 text-sm">Enter a valid raw score to preview its conversion.</p>
                                {/if}
                            </div>
                        </div>
                    </section>
                </fieldset>
            {/each}
        </div>
    </section>

    <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
        <Link href={cancelHref}>
            <Button type="button" variant="outline" class="min-h-11 w-full sm:w-auto">Cancel</Button>
        </Link>
        <Button type="submit" class="min-h-11" disabled={form.processing}>
            {form.processing ? 'Saving…' : is_successor ? 'Create successor draft' : 'Save draft'}
        </Button>
    </div>
</form>
