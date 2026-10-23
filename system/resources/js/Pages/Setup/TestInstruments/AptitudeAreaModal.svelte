<script lang="ts">
    import { untrack } from 'svelte';
    import { compileFormula, evaluateFormula, formulaVariables } from '@/lib/raw-metric-formula';
    import { generateDecimalGridIntervals, intervalRowsWellFormed, nextDecimalGridValue, parseIntervalCsv, lookupIntervalScore, validateIntervalOverlapsAndGaps } from '@/lib/norm-conversion-table';
    import { evaluateMetricGraph, metricGraphLeafCodes, resolveMetricBounds } from '@/lib/metric-graph';
    import * as Dialog from '@/Components/ui/dialog';
    import { Settings } from 'lucide-svelte';
    import InfoPopover from '@/Components/kit/InfoPopover.svelte';
    import FormulaExpressionInput from '@/Components/Setup/FormulaExpressionInput.svelte';

    let {
        open = false,
        editingMetric = null,
        intervals = [],
        subtests = [],
        allMetrics = [],
        constructs = [],
        initialMode = 'editor',
        finalOnlyMode = false,
        onClose = () => {},
        onApply = () => {},
    } = $props();

    let mode = $state(initialMode);
    let moreActionsOpen = $state(false);
    let csvText = $state('');
    let csvPreview = $state(null);
    let draft = $state({});
    let table = $state([]);
    let simulatorValues = $state({});
    let noPaperDeclared = $state(false);
    const contractSources = ['import', 'manual', 'omr'];
    const contractResources = ['answer_sheet', 'booklet_form', 'key', 'physical_procedure'];
    function cloneInputContract(contract) {
        if (!contract || typeof contract !== 'object') return null;
        return {
            allowed_sources: [...(contract.allowed_sources ?? [])],
            required_resources: [...(contract.required_resources ?? [])],
            source_requirements: Object.fromEntries(Object.entries(contract.source_requirements ?? {}).map(([source, requirement]) => [
                source,
                { unit: requirement.unit, identity_requirements: [...(requirement.identity_requirements ?? [])] },
            ])),
        };
    }
    function metricUnit(metric = draft) {
        if (metric.rule_kind === 'recorded_measure' || (metric.rule_kind !== 'sum_raw_metrics' && metricType(metric) === 'direct' && subtests.find((item) => item.code === metric.subtest_code)?.response_mode === 'recorded_measure')) {
            return metric.unit ?? subtests.find((item) => item.code === metric.subtest_code)?.unit ?? null;
        }
        if (metric.rule_kind === 'sum_raw_metrics' || metricType(metric) === 'composite') {
            const inputs = (metric.input_codes ?? []).map((code) => allMetrics.find((item) => item.code === code));
            const units = inputs.map((item) => item?.unit ?? (item?.rule_kind === 'correct_item_count' ? 'count' : null));
            return units.length === inputs.length && units.every(Boolean) && new Set(units).size === 1 ? units[0] : null;
        }
        return 'count';
    }
    const contractIdentity = (resources) => ['administration', 'metric', ...resources].sort();
    function contractSelected(kind, value) {
        return (draft?.input_contract_json?.[kind] ?? []).includes(value);
    }

    function contractReady() {
        const contract = draft?.input_contract_json;
        return contract === null || contract === undefined || (
            Array.isArray(contract.allowed_sources)
            && contract.allowed_sources.length > 0
            && ((Array.isArray(contract.required_resources) && contract.required_resources.length > 0) || noPaperDeclared)
        );
    }

    function setContractChoice(kind, value, checked) {
        const current = cloneInputContract(draft.input_contract_json) ?? {
            allowed_sources: [], required_resources: [], source_requirements: {},
        };
        const values = new Set(current[kind]);
        if (checked) values.add(value);
        else values.delete(value);
        if (kind === 'required_resources') current.required_resources = [...values].sort();
        if (kind === 'allowed_sources') current.allowed_sources = [...values].sort();
        if (kind === 'allowed_sources' && value === 'omr' && checked) {
            current.required_resources = [...new Set([...current.required_resources, 'answer_sheet', 'booklet_form', 'key'])].sort();
        }
        if (current.allowed_sources.includes('omr')) {
            current.required_resources = [...new Set([...current.required_resources, 'answer_sheet', 'booklet_form', 'key'])].sort();
        }
        const unit = metricUnit();
        current.source_requirements = Object.fromEntries(current.allowed_sources.map((source) => [
            source,
            { unit, identity_requirements: contractIdentity(current.required_resources) },
        ]));
        draft = { ...draft, input_contract_json: current };
        if (kind === 'required_resources' && checked) noPaperDeclared = false;
    }

    function declareNoPaper() {
        const unit = metricUnit();
        const sources = ['import', 'manual'];
        draft = {
            ...draft,
            input_contract_json: {
                allowed_sources: sources,
                required_resources: [],
                source_requirements: Object.fromEntries(sources.map((source) => [
                    source, { unit, identity_requirements: ['administration', 'metric'] },
                ])),
            },
        };
        noPaperDeclared = true;
    }


    function cloneMetric(metric) {
        return {
            ...(metric ?? {}),
            input_contract_json: cloneInputContract(metric?.input_contract_json),
            rule_kind: metric?.rule_kind === 'external_final' ? 'external_final' : metricType(metric) === 'composite'
                ? (metric?.rule_kind === 'formula_raw_metrics' ? 'formula_raw_metrics' : 'sum_raw_metrics')
                : (metric?.rule_kind === 'recorded_measure' ? 'recorded_measure' : 'correct_item_count'),
            subtest_code: metric?.subtest_code ?? '',
            input_codes: [...(metric?.input_codes ?? [])],
            required_output_scales: [...(metric?.required_output_scales ?? ['percentile_rank'])],
            ...(metric?.item_codes !== undefined ? { item_codes: metric.item_codes == null ? null : [...metric.item_codes] } : {}),
            ...(metric?.formula_expression !== undefined ? { formula_expression: metric.formula_expression } : {}),
            ...(metric?.formula_ast_json !== undefined ? { formula_ast_json: metric.formula_ast_json } : {}),
            ...(metric?.precision_scale !== undefined ? { precision_scale: Number(metric.precision_scale) } : {}),
        };
    }

    function cloneIntervals(rows) {
        return (rows ?? []).map((row) => ({ ...row }));
    }

    let copiedNotice = $state('');
    let formulaError = $state('');

    function metricType(metric) {
        return metric?.rule_kind === 'sum_raw_metrics' || metric?.rule_kind === 'formula_raw_metrics' || metric?.input_codes?.length
            ? 'composite'
            : 'direct';
    }

    function componentCodes(metric) {
        return metric?.input_codes ?? [];
    }

    let hydratedKey = null;
    $effect(() => {
        if (!open) {
            hydratedKey = null;
            return;
        }
        const key = `${editingMetric?.code ?? 'new'}:${initialMode}`;
        if (key === hydratedKey) {
            return;
        }
        hydratedKey = key;
        untrack(() => {
            const nextDraft = cloneMetric(editingMetric);
            mode = initialMode;
            draft = nextDraft;
            noPaperDeclared = Boolean(
                nextDraft.input_contract_json
                && nextDraft.input_contract_json.allowed_sources.length > 0
                && nextDraft.input_contract_json.required_resources.length === 0,
            );
            table = cloneIntervals(intervals);
            csvText = '';
            csvPreview = null;
            simulatorValues = {};
            copiedNotice = '';
            moreActionsOpen = false;
            formulaError = '';
        });
    });

    function subtestName(subtest) {
        return subtest?.name || subtest?.display_name || subtest?.label || subtest?.code || 'Unnamed subtest';
    }


    function maxRaw(subtest) {
        const items = eligibleItems(subtest);
        if (draft?.subtest_code === subtest?.code && draft?.item_codes != null) {
            return draft.item_codes.length;
        }
        for (const value of [subtest?.max_raw_value, subtest?.max_raw_score]) {
            if (value !== null && value !== undefined && value !== '' && Number.isFinite(Number(value))) {
                return Number(value);
            }
        }
        return items.length;
    }
    function metricRawPrecisionScale() {
        return formulaMode() || draft?.rule_kind === 'recorded_measure'
            ? Number(draft?.precision_scale ?? 0)
            : 0;
    }

    function selectedMetrics() {
        const codes = metricType(draft) === 'composite' ? componentCodes(draft) : [];
        return codes.map((code) => allMetrics.find((metric) => metric.code === code)).filter(Boolean);
    }

    function maximumRaw() {
        const bounds = formulaRawBounds();
        return Number(bounds?.max ?? 0);
    }

    function formulaRawBounds() {
        if (metricType(draft) === 'direct') {
            const subtest = subtests.find((item) => item.code === draft.subtest_code);
            if (subtest?.response_mode === 'recorded_measure') {
                return {
                    min: String(draft.min_raw_value ?? subtest.min_raw_value ?? 0),
                    max: String(draft.max_raw_value ?? subtest.max_raw_value ?? 0),
                };
            }
            return { min: '0', max: String(maxRaw(subtest)) };
        }
        const graph = [...allMetrics.filter((metric) => metric.code !== draft?.code), draft];
        return resolveMetricBounds(draft.code, graph, subtests, Number(draft.precision_scale ?? 6));
    }

    function maxMetricRaw(metric) {
        return Number(metric?.max_raw_value ?? metric?.max_raw_score ?? 0);
    }

    function metricName(metric) {
        return metric?.name ?? metric?.code ?? 'Unnamed metric';
    }
    function dependsOn(metricCode, targetCode, visited = new Set()) {
        if (metricCode === targetCode) {
            return true;
        }
        if (visited.has(metricCode)) {
            return false;
        }
        visited.add(metricCode);
        const metric = allMetrics.find((item) => item.code === metricCode);
        return (metric?.input_codes ?? []).some((code) => dependsOn(code, targetCode, visited));
    }
    function eligibleItems(subtest) {
        return (subtest?.items ?? []).filter((item) =>
            item.is_scored !== false
            && item.group_type !== 'practice'
            && item.is_practice !== true
            && typeof item.item_code === 'string'
            && item.item_code.trim() !== '',
        );
    }

    function toggleItem(itemCode, checked) {
        const currentSubtest = subtests.find((subtest) => subtest.code === draft.subtest_code);
        const selected = new Set(draft.item_codes ?? eligibleItems(currentSubtest).map((item) => item.item_code));
        if (checked) selected.add(itemCode);
        else selected.delete(itemCode);
        draft = { ...draft, item_codes: [...selected] };
    }

    function formulaMode() {
        return draft.rule_kind === 'formula_raw_metrics';
    }

    function setFormulaMode(enabled) {
        formulaError = '';
        if (enabled) {
            draft = {
                ...draft,
                rule_kind: 'formula_raw_metrics',
                formula_expression: draft.formula_expression ?? componentCodes(draft).join(' + '),
            };
            return;
        }
        draft = { ...draft, rule_kind: 'sum_raw_metrics' };
    }

    function togglePercentileRequirement(checked) {
        const scales = new Set(draft.required_output_scales ?? ['percentile_rank']);
        if (checked) scales.add('percentile_rank');
        else scales.delete('percentile_rank');
        draft = { ...draft, required_output_scales: [...scales] };
    }

    function resetFormulaToSum() {
        draft = {
            ...draft,
            rule_kind: 'sum_raw_metrics',
            formula_expression: null,
            formula_ast_json: null,
            precision_scale: 0,
        };
        formulaError = '';
    }


    const formulaIssue = $derived.by(() => {
        if (!formulaMode()) return '';
        try {
            const inputs = componentCodes(draft);
            const used = formulaVariables(compileFormula(String(draft.formula_expression ?? ''), inputs));
            if (!used.length || used.length !== inputs.length || used.some((code) => !inputs.includes(code))) {
                return 'Use every checked input in the formula, or uncheck inputs you do not need.';
            }
            return formulaRawBounds() === null ? 'The formula has an invalid dependency or score range; check its inputs and divisors.' : '';
        } catch (error) {
            return error instanceof Error ? error.message : 'Enter a valid formula.';
        }
    });
    const formulaReady = $derived(formulaIssue === '');

    function validateFormula() {
        if (!formulaMode()) return true;
        try {
            const ast = compileFormula(String(draft.formula_expression ?? ''), componentCodes(draft));
            const used = formulaVariables(ast);
            if (!used.length || used.length !== componentCodes(draft).length || used.some((code) => !componentCodes(draft).includes(code))) {
                throw new Error('Use only checked raw metric inputs in the formula.');
            }
            draft = { ...draft, formula_ast_json: ast };
            formulaError = '';
            return true;
        } catch (error) {
            formulaError = error instanceof Error ? error.message : 'Enter a valid formula.';
            return false;
        }
    }



    function availableRawMetrics() {
        return allMetrics.filter((metric) =>
            metric.code !== draft?.code && !dependsOn(metric.code, draft?.code),
        );

    }
    function formulaInputCodes() {
        if (metricType(draft) === 'direct') return [draft.subtest_code].filter(Boolean);
        const graph = [...allMetrics.filter((metric) => metric.code !== draft?.code), draft];
        return [...new Set(selectedMetrics().flatMap((metric) => metricGraphLeafCodes(metric.code, graph)))].sort();
    }

    function toggleComponent(code, checked) {
        const next = new Set(componentCodes(draft));
        if (checked) {
            next.add(code);
        } else {
            next.delete(code);
        }
        draft = { ...draft, input_codes: [...next] };
    }

    function setType(type) {
        if (type === 'composite') {
            draft = { ...draft, rule_kind: formulaMode() ? 'formula_raw_metrics' : 'sum_raw_metrics', subtest_code: null };
            return;
        }
        const selectedSubtest = subtests.find((subtest) => subtest.code === draft.subtest_code);
        draft = {
            ...draft,
            rule_kind: selectedSubtest?.response_mode === 'recorded_measure' ? 'recorded_measure' : 'correct_item_count',
            input_codes: [],
            item_codes: null,
        };
    }

    function updateInterval(index, field, value) {
        table = table.map((row, rowIndex) => rowIndex === index
            ? { ...row, [field]: value === '' ? '' : field === 'output_value' ? Number(value) : String(value) }
            : row);
    }

    function addRow() {
        const scale = metricRawPrecisionScale();
        const last = table.at(-1);
        const start = last ? nextDecimalGridValue(last.max_score, scale) : String(formulaRawBounds()?.min ?? 0);
        table = [...table, { min_score: start ?? '', max_score: start ?? '', output_value: '' }];
    }

    function generateRows() {
        const bounds = formulaRawBounds();
        const rows = bounds
            ? generateDecimalGridIntervals(bounds.min, bounds.max, metricRawPrecisionScale(), 500)
            : null;
        if (!rows) {
            copiedNotice = 'This range exceeds the 500-row generation limit or is not on the declared decimal grid. Add conversion intervals manually.';
            return;
        }
        table = rows;
        copiedNotice = '';
    }

    function removeRow(index) {
        table = table.filter((_, rowIndex) => rowIndex !== index);
    }

    function previewCsv() {
        csvPreview = parseIntervalCsv(csvText);
    }

    function applyCsv() {
        if (!csvPreview) {
            previewCsv();
            return;
        }
        if (csvPreview.errors.length || csvPreview.intervals.length === 0) {
            return;
        }
        table = cloneIntervals(csvPreview.intervals);
        mode = 'editor';
        csvPreview = null;
    }

    async function copyTable() {
        const text = table.map((row) => `${row.min_score}\t${row.max_score}\t${row.output_value}`).join('\n');
        try {
            await navigator.clipboard.writeText(text);
            copiedNotice = 'Table copied as tab-delimited text.';
        } catch {
            copiedNotice = 'Clipboard access is unavailable in this browser.';
        }
        moreActionsOpen = false;
    }

    function close() {
        onClose();
    }

    function applyArea() {
        const type = metricType(draft);
        const selectedSubtest = subtests.find((subtest) => subtest.code === draft.subtest_code);
        if (!contractReady()) return;
        if (finalOnlyMode) {
            const normalizedCode = String(draft.code ?? '').trim().toUpperCase();
            const normalizedName = String(draft.name ?? '').trim();
            const constructId = Number(draft.construct_aptitude_area_id ?? 0);
            if (!normalizedCode || !normalizedName || constructId < 1) {
                formulaError = 'Enter a code, name, and one selected aptitude construct.';
                return;
            }
            const sources = ['import', 'manual'];
            onApply({
                ...draft,
                code: normalizedCode,
                name: normalizedName,
                rule_kind: 'external_final',
                construct_aptitude_area_id: constructId,
                unit: null,
                min_raw_value: null,
                max_raw_value: null,
                subtest_code: null,
                input_codes: [],
                item_codes: null,
                formula_expression: null,
                formula_ast_json: null,
                precision_scale: Number(draft.precision_scale ?? 0),
                is_required: true,
                required_output_scales: ['percentile_rank'],
                input_contract_json: {
                    allowed_sources: sources,
                    required_resources: [],
                    source_requirements: Object.fromEntries(sources.map((source) => [source, {
                        unit: 'percentile_rank',
                        identity_requirements: ['administration', 'metric'],
                    }])),
                },
            }, [], editingMetric?.code ?? null);
            return;
        }
        if (type === 'composite' && componentCodes(draft).length === 0) {
            formulaError = 'Select at least one raw metric input.';
            return;
        }
        if (type === 'direct' && selectedSubtest?.response_mode !== 'recorded_measure' && draft.item_codes != null) {
            const allowedItems = new Set(eligibleItems(selectedSubtest).map((item) => item.item_code));
            if (draft.item_codes.length === 0 || new Set(draft.item_codes).size !== draft.item_codes.length || draft.item_codes.some((code) => !allowedItems.has(code))) {
                formulaError = 'Select one or more eligible scored items from this subtest.';
                return;
            }
        }
        if (!validateFormula()) return;
        if (!intervalRowsWellFormed(table, metricRawPrecisionScale())) {
            formulaError = 'Enter valid raw bounds and percentile values before applying this draft.';
            return;
        }
        const codes = componentCodes(draft);
        const ruleKind = type === 'composite'
            ? (formulaMode() ? 'formula_raw_metrics' : 'sum_raw_metrics')
            : (selectedSubtest?.response_mode === 'recorded_measure' ? 'recorded_measure' : 'correct_item_count');
        const normalizedCode = String(draft.code ?? editingMetric?.code ?? '').trim().toUpperCase();
        const normalizedName = String(draft.name ?? editingMetric?.name ?? normalizedCode).trim();
        const updatedMetric = {
            ...draft,
            code: normalizedCode || draft.code,
            name: normalizedName || draft.name,
            input_contract_json: cloneInputContract(draft.input_contract_json),
            rule_kind: ruleKind,
            subtest_code: type === 'direct' ? draft.subtest_code : null,
            input_codes: type === 'composite' ? [...codes] : [],
            ...(type === 'direct' && draft.item_codes !== undefined ? { item_codes: draft.item_codes == null ? null : [...draft.item_codes] } : {}),
            ...(formulaMode() ? {
                formula_expression: String(draft.formula_expression ?? '').trim().startsWith('=') ? String(draft.formula_expression).trim() : `=${String(draft.formula_expression ?? '').trim()}`,
                formula_ast_json: draft.formula_ast_json,
                precision_scale: Number(draft.precision_scale ?? 0),
            } : {}),
            min_raw_value: formulaMode() ? String(formulaRawBounds()?.min ?? '0') : selectedSubtest?.response_mode === 'recorded_measure' ? draft.min_raw_value ?? selectedSubtest.min_raw_value ?? '0' : 0,
            max_raw_value: formulaMode() ? String(formulaRawBounds()?.max ?? '0') : selectedSubtest?.response_mode === 'recorded_measure' ? draft.max_raw_value ?? selectedSubtest.max_raw_value ?? maximumRaw() : maximumRaw(),
        };
        const unit = metricUnit(updatedMetric);
        if (unit) updatedMetric.unit = unit;
        if (updatedMetric.input_contract_json && unit) {
            updatedMetric.input_contract_json.source_requirements = Object.fromEntries(
                updatedMetric.input_contract_json.allowed_sources.map((source) => [source, {
                    unit,
                    identity_requirements: contractIdentity(updatedMetric.input_contract_json.required_resources),
                }]),
            );
        }
        onApply(updatedMetric, cloneIntervals(table), editingMetric?.code ?? null);
    }

    const selectedMetric = $derived(allMetrics.find((metric) => metric.code === editingMetric?.code));
    const metricTitle = $derived(draft?.name || selectedMetric?.name || editingMetric?.name || 'Aptitude Area');
    const formulaRawRange = $derived(formulaRawBounds());
    const coverage = $derived(validateIntervalOverlapsAndGaps(table, formulaRawRange?.max, {
        minAllowed: formulaRawRange?.min,
        precisionScale: metricRawPrecisionScale(),
    }));
    const coverageRequired = $derived((draft.required_output_scales ?? ['percentile_rank']).includes('percentile_rank') || table.length > 0);
    const coverageReady = $derived(intervalRowsWellFormed(table, metricRawPrecisionScale()));
    const simulationRaw = $derived.by(() => {
        const leafCodes = formulaInputCodes();
        if (leafCodes.some((code) => simulatorValues[code] === undefined || simulatorValues[code] === '')) return null;
        const rawValues = Object.fromEntries(leafCodes.map((code) => [code, String(simulatorValues[code])]));
        if (metricType(draft) === 'direct') return rawValues[draft.subtest_code] ?? null;
        const graph = [...allMetrics.filter((metric) => metric.code !== draft?.code), draft];
        const inputValues: Record<string, string> = {};
        for (const metric of selectedMetrics()) {
            const value = evaluateMetricGraph(metric.code, graph, rawValues);
            if (value === null) return null;
            inputValues[metric.code] = value;
        }
        try {
            if (formulaMode()) {
                const ast = compileFormula(String(draft.formula_expression ?? ''), componentCodes(draft));
                return evaluateFormula(ast, inputValues, Number(draft.precision_scale ?? 0));
            }
            return evaluateFormula({
                type: 'call',
                name: 'SUM',
                arguments: selectedMetrics().map((metric) => ({ type: 'variable', name: metric.code })),
            }, inputValues, 6);
        } catch {
            return null;
        }
    });
    const simulationOutput = $derived(simulationRaw === null ? null : lookupIntervalScore(simulationRaw, table));
    const csvResult = $derived(csvPreview);
</script>

<Dialog.Root {open} onOpenChange={(value) => { if (!value) close(); }}>
    <Dialog.Content class="flex max-h-[92dvh] w-[calc(100vw-2rem)] max-w-none flex-col overflow-hidden rounded-2xl border border-border bg-card p-0 shadow-2xl sm:w-[calc(100vw-2rem)] sm:max-w-none lg:w-[min(64rem,calc(100vw-4rem))]" showCloseButton={false}>
        <div class="flex shrink-0 items-start justify-between gap-4 border-b border-border/70 px-5 py-4 sm:px-6">
            <div class="min-w-0">
                <Dialog.Title class="text-lg font-semibold tracking-tight">{finalOnlyMode ? `Edit Final Aptitude: ${metricTitle}` : `Edit Aptitude Area: ${metricTitle}`}</Dialog.Title>
                <Dialog.Description class="mt-1 text-sm text-muted-foreground">{finalOnlyMode ? 'Define the construct, name, code, and precision for an externally supplied percentile-rank final.' : 'Configure the raw-score formula and its percentile conversion intervals.'}</Dialog.Description>
            </div>
            {#if !finalOnlyMode}
            <div class="relative shrink-0">
                <button type="button" class="flex min-h-10 items-center gap-2 rounded-md border border-border px-3 text-sm font-medium hover:bg-muted" aria-expanded={moreActionsOpen} onclick={() => (moreActionsOpen = !moreActionsOpen)}><Settings class="h-4 w-4" />More Actions</button>
                {#if moreActionsOpen}
                    <div class="absolute right-0 z-10 mt-2 w-48 rounded-lg border border-border bg-card p-1 shadow-lg" role="menu">
                        <button type="button" role="menuitem" class="block min-h-10 w-full rounded px-3 text-left text-sm hover:bg-muted" onclick={() => { mode = 'simulator'; moreActionsOpen = false; }}>Test Simulator</button>
                        <button type="button" role="menuitem" class="block min-h-10 w-full rounded px-3 text-left text-sm hover:bg-muted" onclick={() => { mode = 'csv'; moreActionsOpen = false; }}>Paste CSV</button>
                        <button type="button" role="menuitem" class="block min-h-10 w-full rounded px-3 text-left text-sm hover:bg-muted" onclick={copyTable}>Copy Table</button>
                    </div>
                {/if}
            </div>
            {/if}
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto px-5 py-5 sm:px-6">
            {#if mode === 'editor'}
                <div class="space-y-6">
                    <section class="grid gap-4 sm:grid-cols-2">
                        <label class="space-y-1.5 text-sm font-medium">
                            <span>Area Name</span>
                            <input
                                class="min-h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                                value={draft.name ?? ''}
                                placeholder="e.g., Vocabulary or General Ability"
                                oninput={(event) => (draft = { ...draft, name: event.currentTarget.value })}
                            />
                        </label>
                        <label class="space-y-1.5 text-sm font-medium">
                            <span>Area Code</span>
                            <input
                                class="min-h-10 w-full rounded-md border border-input bg-background px-3 font-mono text-sm uppercase"
                                value={draft.code ?? ''}
                                placeholder="e.g., VOCABULARY_RAW"
                                oninput={(event) => (draft = { ...draft, code: event.currentTarget.value.toUpperCase() })}
                            />
                        </label>
                    </section>

                    {#if finalOnlyMode}
                        <section class="space-y-4 rounded-lg border border-border/70 p-4">
                            <p class="text-sm text-muted-foreground">This aptitude area receives a required externally supplied percentile rank. Raw formulas, subtests, item scope, simulators, and norm tables do not apply.</p>
                            <label class="block max-w-md space-y-1.5 text-sm">Institutional construct
                                <select class="min-h-10 w-full rounded-md border border-input bg-background px-3" value={draft.construct_aptitude_area_id ?? ''} onchange={(event) => (draft = { ...draft, construct_aptitude_area_id: event.currentTarget.value ? Number(event.currentTarget.value) : null })}>
                                    <option value="">Select construct</option>
                                    {#each constructs as construct (construct.id)}<option value={construct.id}>{construct.name}</option>{/each}
                                </select>
                            </label>
                            <label class="block max-w-xs space-y-1.5 text-sm">PR decimal precision
                                <select class="min-h-10 w-full rounded-md border border-input bg-background px-3" value={draft.precision_scale ?? 2} onchange={(event) => (draft = { ...draft, precision_scale: Number(event.currentTarget.value) })}>
                                    {#each [0, 1, 2, 3, 4, 5, 6] as scale (scale)}<option value={scale}>{scale} {scale === 1 ? 'decimal place' : 'decimal places'}</option>{/each}
                                </select>
                            </label>
                            <label class="flex min-h-10 items-center gap-2 text-sm"><input type="checkbox" checked disabled />Required final score · allowed acquisition: manual and import</label>
                        </section>
                        {#if formulaError}<p class="text-sm text-destructive" role="alert">{formulaError}</p>{/if}
                    {:else}
                    <section class="space-y-3 rounded-lg border border-border/70 p-4" aria-labelledby="metric-input-contract">
                <h3 id="metric-input-contract" class="flex items-center gap-1.5 font-semibold">
                    <span>Metric input sources and materials</span>
                    <InfoPopover
                        title="Input sources & No-Paper declaration"
                        content="Click 'Declare No Paper (manual / import)' for direct manual scoring or CSV imports without physical sheets. Choose OMR and paper resources (answer sheet, booklet form, key) only when scanning bubble sheets."
                    />
                </h3>
                {#if draft.input_contract_json === null}
                    <p class="text-sm text-amber-700 dark:text-amber-300">Input requirements are unresolved. Choose No Paper or declare allowed sources and any required materials; unresolved is not treated as No Paper.</p>
                {/if}
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="min-h-9 rounded-md border px-3 text-sm" disabled={!metricUnit()} onclick={declareNoPaper}>Declare No Paper (manual / import)</button>
                    {#each contractSources as source (source)}
                        <label class="flex min-h-9 items-center gap-2 rounded-md border px-3 text-sm">
                            <input type="checkbox" checked={contractSelected('allowed_sources', source)} disabled={!metricUnit()} onchange={(event) => setContractChoice('allowed_sources', source, event.currentTarget.checked)} />
                            {source}
                        </label>
                    {/each}
                </div>
                <fieldset class="flex flex-wrap gap-2">
                    <legend class="mb-2 text-sm font-medium">Required resources (leave empty only for explicitly no-paper inputs)</legend>
                    {#each contractResources as resource (resource)}
                        <label class="flex min-h-9 items-center gap-2 rounded-md border px-3 text-sm">
                            <input type="checkbox" checked={contractSelected('required_resources', resource)} disabled={!metricUnit()} onchange={(event) => setContractChoice('required_resources', resource, event.currentTarget.checked)} />
                            {resource.replaceAll('_', ' ')}
                        </label>
                    {/each}
                </fieldset>
                <label class="block space-y-1.5 text-sm">
                    Metric procedure reference
                    <input class="min-h-10 w-full rounded-md border border-input bg-background px-3" value={draft.procedure_reference ?? ''} oninput={(event) => (draft = { ...draft, procedure_reference: event.currentTarget.value })} />
                </label>
                {#if !metricUnit()}<p class="text-xs text-amber-700 dark:text-amber-300">A resolved metric unit is required before declaring source requirements.</p>{/if}
                {#if draft.input_contract_json}
                    <p class="text-xs text-muted-foreground">Declared sources: {draft.input_contract_json.allowed_sources.join(', ') || 'none'} · resources: {draft.input_contract_json.required_resources.join(', ') || 'none'}</p>
                {#if !contractReady()}<p class="text-xs text-amber-700 dark:text-amber-300">Choose at least one resource for paper-backed inputs, or use Declare No Paper to explicitly leave resources empty.</p>{/if}
                {/if}
            </section>

                    <section class="space-y-4" aria-labelledby="aptitude-formula-heading">
                        <div>
                            <h2 id="aptitude-formula-heading" class="flex items-center gap-1.5 text-base font-semibold">
                                <span>Formula Builder</span>
                                <InfoPopover
                                    title="Direct Subtest vs. Composite Sum"
                                    content="Direct Subtest takes the raw count from one subtest. Composite Sum automatically adds two or more raw metrics from the same sitting (e.g., Vocabulary + Computation = General Ability)."
                                />
                            </h2>
                            <p class="mt-1 text-sm text-muted-foreground">Choose how this Aptitude Area gets its raw score.</p>
                        </div>
                        <fieldset class="flex flex-wrap gap-x-6 gap-y-3">
                            <legend class="sr-only">Formula type</legend>
                            <label class="flex min-h-10 items-center gap-2 text-sm"><input type="radio" name="aptitude-formula-type" checked={metricType(draft) === 'direct'} onchange={() => setType('direct')} />Direct Subtest</label>
                            <label class="flex min-h-10 items-center gap-2 text-sm"><input type="radio" name="aptitude-formula-type" checked={metricType(draft) === 'composite'} onchange={() => setType('composite')} />Composite Sum</label>
                        </fieldset>
                        {#if constructs.length > 0}
                            <div class="block max-w-md space-y-1.5 text-sm"><div class="flex items-center gap-1.5"><label for="modal-area-construct">Construct</label><InfoPopover title="Institutional aptitude construct" content={'Binds this metric to an institutional Aptitude Area so Result Sheet placeholders (e.g., {{general_ability}}, {{verbal_aptitude}}) and Decision Support benchmarks resolve automatically.'} /></div>
                                <select id="modal-area-construct" class="min-h-10 w-full rounded-md border border-input bg-background px-3" value={draft.construct_aptitude_area_id ?? ''} onchange={(event) => (draft = { ...draft, construct_aptitude_area_id: event.currentTarget.value || null })}>
                                    <option value="">No construct</option>
                                    {#each constructs as construct (construct.id ?? construct.code)}
                                        <option value={construct.id ?? construct.code}>{construct.name ?? construct.label ?? construct.code}</option>
                                    {/each}
                                </select>
                            </div>
                        {/if}
                        {#if metricType(draft) === 'direct'}
                            <label class="block max-w-md space-y-1.5 text-sm">Subtest
                                <select class="min-h-10 w-full rounded-md border border-input bg-background px-3" value={draft.subtest_code ?? ''} onchange={(event) => { const subtest = subtests.find((item) => item.code === event.currentTarget.value); draft = { ...draft, subtest_code: event.currentTarget.value, item_codes: null, rule_kind: subtest?.response_mode === 'recorded_measure' ? 'recorded_measure' : 'correct_item_count', min_raw_value: subtest?.min_raw_value ?? 0, max_raw_value: subtest?.max_raw_value ?? subtest?.max_raw_score ?? maxRaw(subtest), precision_scale: Number(draft.precision_scale ?? subtest?.precision_scale ?? 0) }; }}>
                                    <option value="">Select a subtest</option>
                                    {#each subtests as subtest (subtest.code)}
                                        <option value={subtest.code}>{subtestName(subtest)} · Max {maxRaw(subtest)}</option>
                                    {/each}
                                </select>
                            </label>
                            {@const selectedSubtest = subtests.find((item) => item.code === draft.subtest_code)}
                            {#if selectedSubtest?.response_mode === 'recorded_measure'}
                                <div class="grid gap-4 sm:grid-cols-3">
                                    <label class="space-y-1.5 text-sm">Minimum raw value<input class="min-h-10 w-full rounded-md border border-input bg-background px-3" type="number" step={10 ** -metricRawPrecisionScale()} value={draft.min_raw_value ?? selectedSubtest.min_raw_value ?? 0} oninput={(event) => (draft = { ...draft, min_raw_value: event.currentTarget.value })} /></label>
                                    <label class="space-y-1.5 text-sm">Maximum raw value<input class="min-h-10 w-full rounded-md border border-input bg-background px-3" type="number" step={10 ** -metricRawPrecisionScale()} value={draft.max_raw_value ?? selectedSubtest.max_raw_value ?? 0} oninput={(event) => (draft = { ...draft, max_raw_value: event.currentTarget.value })} /></label>
                                    <label class="space-y-1.5 text-sm">Raw precision<select class="min-h-10 w-full rounded-md border border-input bg-background px-3" value={draft.precision_scale ?? 0} onchange={(event) => (draft = { ...draft, precision_scale: Number(event.currentTarget.value) })}>{#each [0, 1, 2, 3, 4, 5, 6] as scale (scale)}<option value={scale}>{scale} decimal places</option>{/each}</select></label>
                                </div>
                            {/if}
                        {:else}
                            <fieldset class="space-y-2">
                                <legend class="mb-2 text-sm font-medium">Raw metric inputs to sum</legend>
                                {#each availableRawMetrics() as metric (metric.code)}
                                    <label class="flex min-h-9 items-center gap-2 text-sm"><input type="checkbox" checked={componentCodes(draft).includes(metric.code)} onchange={(event) => toggleComponent(metric.code, event.currentTarget.checked)} />{metricName(metric)} <span class="text-muted-foreground">({maxMetricRaw(metric)})</span></label>
                                {:else}
                                    <p class="text-sm text-muted-foreground">No other raw metric is available for a composite formula.</p>
                                {/each}
                            </fieldset>
                            <label class="flex min-h-10 items-center gap-2 rounded-md border border-border/70 px-3 text-sm">
                                <input type="checkbox" checked={formulaMode()} onchange={(event) => setFormulaMode(event.currentTarget.checked)} />
                                Use a custom formula
                            </label>
                            {#if formulaMode()}
                                <div class="space-y-3 rounded-lg border border-border/70 p-4">
                                    <div class="flex items-center justify-between gap-3">
                                        <label for="aptitude-custom-formula" class="text-sm font-medium">Formula expression</label>
                                        <button type="button" class="min-h-9 rounded-md border px-3 text-sm hover:bg-muted" onclick={resetFormulaToSum}>Reset to Sum</button>
                                    </div>
                                    <FormulaExpressionInput
                                        id="aptitude-custom-formula"
                                        value={draft.formula_expression ?? ''}
                                        variableCodes={selectedMetrics().map((metric) => metric.code)}
                                        ariaDescribedBy="aptitude-formula-help"
                                        textareaClass="min-h-24 w-full resize-y rounded-md border border-input bg-background px-3 py-2 font-mono text-sm"
                                        onValueChange={(expression) => { draft = { ...draft, formula_expression: expression, formula_ast_json: null }; formulaError = ''; }}
                                    />
                                    <p id="aptitude-formula-help" class="text-xs text-muted-foreground">ROUND(value, places) supports 0–6 places and rounds half away from zero. Functions are case-insensitive; type a prefix or press Ctrl+Space for suggestions, then use ↑/↓, Enter, or Escape.</p>
                                    <label class="block max-w-xs space-y-1.5 text-sm">Output precision
                                        <select class="min-h-10 w-full rounded-md border border-input bg-background px-3" value={draft.precision_scale ?? 0} onchange={(event) => (draft = { ...draft, precision_scale: Number(event.currentTarget.value) })}>
                                            {#each [0, 1, 2, 3, 4, 5, 6] as scale (scale)}<option value={scale}>{scale} {scale === 1 ? 'decimal place' : 'decimal places'}</option>{/each}
                                        </select>
                                    </label>
                                    {#if formulaError || formulaIssue}<p class="text-sm text-destructive" role="alert">{formulaError || formulaIssue}</p>{/if}
                                </div>
                            {/if}
                        {/if}
                        {#if metricType(draft) === 'direct'}
                            {@const selectedSubtest = subtests.find((subtest) => subtest.code === draft.subtest_code)}
                            {#if selectedSubtest && selectedSubtest.response_mode !== 'recorded_measure'}
                                {@const scoredItems = eligibleItems(selectedSubtest)}
                                <fieldset class="space-y-2 rounded-lg border border-border/70 p-4">
                                    <legend class="px-1 text-sm font-medium">Scored item scope</legend>
                                    <p class="text-xs text-muted-foreground">Leave all items selected to score the whole subtest. Choose exact keyed items for a subset; display numbers are not identifiers.</p>
                                    <div class="flex flex-wrap gap-2">
                                        <button type="button" class="min-h-9 rounded-md border px-3 text-sm hover:bg-muted" onclick={() => (draft = { ...draft, item_codes: null })}>All scored items</button>
                                        <button type="button" class="min-h-9 rounded-md border px-3 text-sm hover:bg-muted" onclick={() => (draft = { ...draft, item_codes: scoredItems.map((item) => item.item_code) })}>Select all items</button>
                                    </div>
                                    <div class="grid gap-2 sm:grid-cols-2">
                                        {#each scoredItems as item (item.item_code)}
                                            <label class="flex min-h-10 items-center gap-2 rounded-md border px-3 text-sm">
                                                <input type="checkbox" checked={draft.item_codes === null || draft.item_codes === undefined || draft.item_codes.includes(item.item_code)} onchange={(event) => toggleItem(item.item_code, event.currentTarget.checked)} />
                                                <span>{item.item_code}</span>
                                                <span class="text-muted-foreground">item {item.local_number ?? item.display_order ?? '—'}</span>
                                            </label>
                                        {:else}
                                            <p class="text-sm text-muted-foreground">No eligible keyed items in this subtest.</p>
                                        {/each}
                                    </div>
                                    {#if draft.item_codes !== null && draft.item_codes !== undefined && draft.item_codes.length === 0}
                                        <p class="text-sm text-destructive" role="alert">Select at least one scored item or choose all scored items.</p>
                                    {/if}
                                </fieldset>
                            {/if}
                        {/if}
                        <div class="rounded-lg border border-border bg-muted/30 px-4 py-3 text-sm" aria-live="polite">
                            {#if metricType(draft) === 'direct'}
                                {@const directSubtest = subtests.find((subtest) => subtest.code === draft.subtest_code)}
                                {#if directSubtest?.response_mode === 'recorded_measure'}
                                    Recorded measure · {subtestName(directSubtest)}
                                {:else}
                                    = {directSubtest ? subtestName(directSubtest) : 'Select a subtest'} (0–{directSubtest ? maxRaw(directSubtest) : 0})
                                {/if}
                            {:else}
                                {#if formulaMode()}
                                    = {String(draft.formula_expression || 'Enter a formula').replace(/^=\s*/, '')} · Range {formulaRawRange?.min ?? '—'}–{formulaRawRange?.max ?? '—'}
                                {:else}
                                    = {selectedMetrics().length ? selectedMetrics().map((metric) => `${metricName(metric)} (${maxMetricRaw(metric)})`).join(' + ') : 'Select raw metric inputs'} =&gt; Range: 0–{maximumRaw()}
                                {/if}
                            {/if}
                        </div>
                    </section>

                    <section class="space-y-4" aria-labelledby="conversion-table-heading">
                        <div>
                            <h2 id="conversion-table-heading" class="flex items-center gap-1.5 text-base font-semibold">
                                <span>Conversion Table</span>
                                <InfoPopover
                                    title="Raw-to-Percentile intervals"
                                    content="Intervals must cover the full supported raw-score range on its declared decimal grid, without gaps or overlaps. Generated rows are blank until staff enter norm values."
                                />
                            </h2>
                            <p class="mt-1 text-sm text-muted-foreground">Map each raw-score range to a percentile rank from 0 to 100.</p>
                            <label class="flex min-h-10 items-center gap-2 rounded-md border border-border/70 px-3 text-sm">
                                <input type="checkbox" checked={(draft.required_output_scales ?? ['percentile_rank']).includes('percentile_rank')} onchange={(event) => togglePercentileRequirement(event.currentTarget.checked)} />
                                Require percentile-rank conversion
                            </label>
                        </div>
                        <div class="overflow-x-auto rounded-lg border border-border">
                            <table class="w-full min-w-[620px] text-left text-sm">
                                <thead class="bg-muted/40 text-xs font-semibold tracking-wide text-muted-foreground"><tr><th class="px-3 py-3">RAW FROM</th><th class="px-3 py-3">RAW TO</th><th class="px-3 py-3">PERCENTILE RANK (0–100)</th><th class="px-3 py-3">ACTIONS</th></tr></thead>
                                <tbody class="divide-y divide-border">
                                    {#each table as row, index (`${index}-${row.min_score}-${row.max_score}`)}
                                        <tr>
                                            <td class="p-2"><input aria-label={`Row ${index + 1} raw from`} class="min-h-10 w-full rounded-md border border-input bg-background px-2" type="number" step={10 ** -Number(draft.precision_scale ?? 0)} value={row.min_score} onchange={(event) => updateInterval(index, 'min_score', event.currentTarget.value)} /></td>
                                            <td class="p-2"><input aria-label={`Row ${index + 1} raw to`} class="min-h-10 w-full rounded-md border border-input bg-background px-2" type="number" step={10 ** -Number(draft.precision_scale ?? 0)} value={row.max_score} onchange={(event) => updateInterval(index, 'max_score', event.currentTarget.value)} /></td>
                                            <td class="p-2"><input aria-label={`Row ${index + 1} percentile rank`} class="min-h-10 w-full rounded-md border border-input bg-background px-2" type="number" min="0" max="100" value={row.output_value} onchange={(event) => updateInterval(index, 'output_value', event.currentTarget.value)} /></td>
                                            <td class="p-2"><button type="button" class="min-h-10 rounded-md px-3 text-sm text-destructive hover:bg-destructive/10" onclick={() => removeRow(index)}>Remove</button></td>
                                        </tr>
                                    {:else}
                                        <tr><td colspan="4" class="px-3 py-6 text-center text-sm text-muted-foreground">No conversion intervals yet.</td></tr>
                                    {/each}
                                </tbody>
                            </table>
                        </div>
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="flex flex-wrap gap-2">
                                <button type="button" class="min-h-10 rounded-md border border-border px-4 text-sm font-medium hover:bg-muted" onclick={addRow}>+ Add Row</button>
                                <button type="button" class="min-h-10 rounded-md border border-border px-4 text-sm font-medium hover:bg-muted" onclick={generateRows}>Generate 0–{maximumRaw()}</button>
                            </div>
                            <p class="text-sm" class:text-destructive={coverageRequired && (coverage.hasOverlap || coverage.gaps.length > 0 || coverage.invalid)} role="status">
                                {#if !coverageRequired}Percentile conversion is not required.{:else}Coverage: {formulaRawRange?.min ?? '—'}–{formulaRawRange?.max ?? '—'} pts · {coverage.hasOverlap ? 'Overlaps found' : coverage.gaps.length ? `Gaps: ${coverage.gaps.join(', ')}` : coverage.invalid ? 'Invalid interval values' : 'No gaps or overlaps'}{/if}
                            </p>
                        </div>
                        {#if copiedNotice}<p class="text-sm text-muted-foreground" role="status">{copiedNotice}</p>{/if}
                        {#if formulaError}<p class="text-sm text-destructive" role="alert">{formulaError}</p>{/if}
                    </section>
                    {/if}
                </div>
            {:else if mode === 'simulator'}
                <div class="space-y-5">
                    <button type="button" class="min-h-10 rounded-md border border-border px-4 text-sm font-medium hover:bg-muted" onclick={() => (mode = 'editor')}>← Back to Formula &amp; Table</button>
                    <section class="space-y-4" aria-labelledby="simulator-heading">
                        <div><h2 id="simulator-heading" class="text-lg font-semibold">Test Simulator</h2><p class="mt-1 text-sm text-muted-foreground">Enter leaf raw scores to test the formula and its percentile-rank conversion.</p></div>
                        {#each formulaInputCodes() as code (code)}
                            {@const sourceMetric = allMetrics.find((metric) => metric.subtest_code === code || metric.code === code)}
                            {@const sourceSubtest = subtests.find((item) => item.code === code)}
                            {@const rawMaximum = sourceMetric ? maxMetricRaw(sourceMetric) : maxRaw(sourceSubtest)}
                            <label class="block max-w-xs space-y-1.5 text-sm">{sourceMetric ? metricName(sourceMetric) : subtestName(sourceSubtest)} <span class="text-muted-foreground">({sourceSubtest?.response_mode === 'recorded_measure' ? `${sourceSubtest.min_raw_value ?? 0}–${rawMaximum}` : `0–${rawMaximum}`})</span>
                                <input class="min-h-10 w-full rounded-md border border-input bg-background px-3" type="number" min={sourceSubtest?.min_raw_value ?? 0} max={rawMaximum} step={10 ** -Number(sourceMetric?.precision_scale ?? 0)} value={simulatorValues[code] ?? ''} oninput={(event) => (simulatorValues = { ...simulatorValues, [code]: event.currentTarget.value })} />
                            </label>
                        {:else}
                            <p class="rounded-lg border border-dashed border-border p-4 text-sm text-muted-foreground">Choose raw metric inputs before using the simulator.</p>
                        {/each}
                        <div class="rounded-xl border border-border bg-muted/30 p-4" aria-live="polite">
                            <p class="text-sm text-muted-foreground">Calculated raw score</p>
                            {#if simulationOutput === null}
                                <p class="mt-2 text-sm text-muted-foreground">No conversion interval matches this score.</p>
                            {:else}
                                <p class="mt-2 text-sm font-medium">Matched interval · Converted Percentile: {simulationOutput}th %</p>
                            {/if}
                        </div>
                    </section>
                </div>
            {:else}
                <div class="space-y-5">
                    <button type="button" class="min-h-10 rounded-md border border-border px-4 text-sm font-medium hover:bg-muted" onclick={() => { mode = 'editor'; csvPreview = null; }}>← Back to Formula &amp; Table</button>
                    <section class="space-y-4" aria-labelledby="csv-heading">
                        <div><h2 id="csv-heading" class="text-lg font-semibold">Paste Conversion Table CSV</h2><p class="mt-1 text-sm text-muted-foreground">Use one interval per line: RAW FROM, RAW TO, PERCENTILE RANK.</p></div>
                        <label class="sr-only" for="aptitude-area-csv">Conversion interval CSV</label>
                        <textarea id="aptitude-area-csv" class="min-h-48 w-full rounded-lg border border-input bg-background p-3 font-mono text-sm" placeholder={'0, 17, 10\n18, 35, 40'} bind:value={csvText}></textarea>
                        <button type="button" class="min-h-10 rounded-md border border-border px-4 text-sm font-medium hover:bg-muted" onclick={previewCsv}>Preview Intervals</button>
                        {#if csvResult}
                            {#if csvResult.errors.length}
                                <ul class="list-inside list-disc space-y-1 text-sm text-destructive" role="alert">{#each csvResult.errors as error (error)}<li>{error}</li>{/each}</ul>
                            {/if}
                            {#if csvResult.intervals.length}
                                <div class="overflow-x-auto rounded-lg border border-border">
                                    <table class="w-full text-left text-sm"><thead class="bg-muted/40"><tr><th class="p-2">RAW FROM</th><th class="p-2">RAW TO</th><th class="p-2">PERCENTILE</th></tr></thead><tbody class="divide-y divide-border">{#each csvResult.intervals as row, index (`${index}-${row.min_score}-${row.max_score}`)}<tr><td class="p-2">{row.min_score}</td><td class="p-2">{row.max_score}</td><td class="p-2">{row.output_value}</td></tr>{/each}</tbody></table>
                                </div>
                                <button type="button" class="min-h-10 rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground disabled:opacity-50" disabled={csvResult.errors.length > 0} onclick={applyCsv}>Apply to Table</button>
                            {:else if !csvResult.errors.length}
                                <p class="text-sm text-muted-foreground">No interval rows found. Add data and preview again.</p>
                            {/if}
                        {/if}
                    </section>
                </div>
            {/if}
        </div>

        <Dialog.Footer class="shrink-0 justify-end border-t border-border/70 px-5 py-4 sm:px-6">
            <button type="button" class="min-h-10 rounded-md border border-border px-4 text-sm font-medium hover:bg-muted" onclick={close}>Cancel</button>
            <button type="button" class="min-h-10 rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground hover:opacity-90 disabled:opacity-50" disabled={finalOnlyMode ? (!String(draft.code ?? '').trim() || !String(draft.name ?? '').trim() || !draft.construct_aptitude_area_id) : (!contractReady() || !coverageReady || !formulaReady)} onclick={applyArea}>Apply Area</button>
        </Dialog.Footer>
    </Dialog.Content>
</Dialog.Root>
