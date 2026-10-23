<script>
    import { Badge } from '@/Components/ui/badge';
    import { Button } from '@/Components/ui/button';
    import * as Dialog from '@/Components/ui/dialog';
    import { Input } from '@/Components/ui/input';
    import { Label } from '@/Components/ui/label';
    import InfoPopover from '@/Components/kit/InfoPopover.svelte';
    import { CheckCircle2, Plus, Table, Trash2, AlertTriangle, ClipboardCopy } from 'lucide-svelte';
    import { generateDecimalGridIntervals, intervalRowsWellFormed, nextDecimalGridValue, validateIntervalOverlapsAndGaps } from '@/lib/norm-conversion-table';
    import { compileFormula, formulaVariables } from '@/lib/raw-metric-formula';
    import { resolveMetricBounds } from '@/lib/metric-graph';
    import FormulaExpressionInput from '@/Components/Setup/FormulaExpressionInput.svelte';

    let {
        metrics = $bindable([]),
        tables = $bindable({}),
        subtests = [],
        constructs = [],
        readOnly = false,
        onDirty = () => {},
        finalOnlyMode = false,
    } = $props();

    let editorOpen = $state(false);
    let pasteOpen = $state(false);
    let draft = $state(null);
    let rows = $state([]);
    let pasteText = $state('');
    let pasteError = $state('');
    let formulaError = $state('');
    let noPaperDeclared = $state(false);

    const inputClass = 'min-h-10 w-full rounded-md border border-input bg-background px-3 text-sm';
    const metricType = (metric) => metric?.rule_kind === 'sum_raw_metrics' || metric?.rule_kind === 'formula_raw_metrics' || (metric?.input_codes ?? []).length ? 'composite' : 'direct';
    const formulaMode = (metric = draft) => metric?.rule_kind === 'formula_raw_metrics';
    const maxSubtest = (subtest) => Number(subtest?.max_raw_value ?? subtest?.max_raw_score ?? (subtest?.items ?? []).filter((item) => item.is_scored !== false).length ?? 0);
    const maxMetric = (metric) => Number(metric?.max_raw_value ?? metric?.max_raw_score ?? 0);
    const metricRawPrecisionScale = () => formulaMode() || draft?.rule_kind === 'recorded_measure' ? Number(draft?.precision_scale ?? 0) : 0;
    const inputMetrics = $derived((draft?.input_codes ?? []).map((code) => metrics.find((metric) => metric.code === code)).filter(Boolean));
    const formulaRange = $derived.by(() => {
        if (!draft) return { min: '0', max: '0' };
        if (metricType(draft) === 'direct') {
            const subtest = subtests.find((item) => item.code === draft.subtest_code);
            if (subtest?.response_mode === 'recorded_measure') {
                return {
                    min: String(draft.min_raw_value ?? subtest.min_raw_value ?? 0),
                    max: String(draft.max_raw_value ?? subtest.max_raw_value ?? 0),
                };
            }
            return { min: '0', max: String(draft.item_codes != null ? draft.item_codes.length : maxSubtest(subtest)) };
        }
        const graph = [...metrics.filter((metric) => metric.code !== draft.code), draft];
        return resolveMetricBounds(draft.code, graph, subtests, Number(draft.precision_scale ?? 6));
    });
    const ceiling = $derived(Number(formulaRange?.max ?? 0));
    const coverage = $derived(validateIntervalOverlapsAndGaps(rows, formulaRange?.max, {
        minAllowed: formulaRange?.min,
        precisionScale: metricRawPrecisionScale(),
    }));
    const coverageRequired = $derived((draft?.required_output_scales ?? ['percentile_rank']).includes('percentile_rank') || rows.length > 0);
    const coverageOk = $derived(!coverageRequired || coverage.valid);
    const rowsValid = $derived(intervalRowsWellFormed(rows, metricRawPrecisionScale()));
    const formulaIssue = $derived.by(() => {
        if (!formulaMode()) return '';
        try {
            const inputs = draft?.input_codes ?? [];
            const used = formulaVariables(compileFormula(String(draft?.formula_expression ?? ''), inputs));
            if (!used.length || used.length !== inputs.length || used.some((code) => !inputs.includes(code))) {
                return 'Use every checked input in the formula, or uncheck inputs you do not need.';
            }
            return formulaRange === null ? 'The formula has an invalid dependency or score range; check its inputs and divisors.' : '';
        } catch (error) {
            return error instanceof Error ? error.message : 'Enter a valid formula.';
        }
    });
    const formulaReady = $derived(formulaIssue === '');
    const itemScopeReady = $derived.by(() => {
        if (metricType(draft) !== 'direct' || draft?.item_codes == null) return true;
        const eligible = new Set(eligibleItems(subtests.find((item) => item.code === draft.subtest_code)).map((item) => item.item_code));
        return draft.item_codes.length > 0 && new Set(draft.item_codes).size === draft.item_codes.length && draft.item_codes.every((code) => eligible.has(code));
    });
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

    function metricUnit(metric) {
        if (metric.rule_kind === 'recorded_measure') {
            return metric.unit || subtests.find((item) => item.code === metric.subtest_code)?.unit || null;
        }
        if (metric.rule_kind === 'sum_raw_metrics' || metric.input_codes?.length) {
            const units = (metric.input_codes ?? []).map((code) => {
                const input = metrics.find((item) => item.code === code);
                return input?.unit ?? (input?.rule_kind === 'correct_item_count' ? 'count' : null);
            });
            return units.length === (metric.input_codes ?? []).length && units.every(Boolean) && new Set(units).size === 1 ? units[0] : null;
        }
        return 'count';
    }

    function contractSelected(kind, value) {
        return (draft?.input_contract_json?.[kind] ?? []).includes(value);
    }

    function contractReady(contract = draft?.input_contract_json) {
        return contract === null || contract === undefined || (
            contract.allowed_sources.length > 0
            && (contract.required_resources.length > 0 || noPaperDeclared)
        );
    }

    function setContractChoice(kind, value, checked) {
        const contract = cloneInputContract(draft.input_contract_json) ?? { allowed_sources: [], required_resources: [], source_requirements: {} };
        const values = new Set(contract[kind]);
        if (checked) values.add(value);
        else values.delete(value);
        if (kind === 'allowed_sources') contract.allowed_sources = [...values].sort();
        if (kind === 'required_resources') contract.required_resources = [...values].sort();
        if (contract.allowed_sources.includes('omr')) {
            contract.required_resources = [...new Set([...contract.required_resources, 'answer_sheet', 'booklet_form', 'key'])].sort();
        }
        const unit = metricUnit(draft);
        contract.source_requirements = Object.fromEntries(contract.allowed_sources.map((source) => [source, {
            unit,
            identity_requirements: ['administration', 'metric', ...contract.required_resources].sort(),
        }]));
        draft = { ...draft, input_contract_json: contract };
        if (kind === 'required_resources' && checked) noPaperDeclared = false;
    }

    function declareNoPaper() {
        const unit = metricUnit(draft);
        const sources = ['import', 'manual'];
        draft = {
            ...draft,
            input_contract_json: {
                allowed_sources: sources,
                required_resources: [],
                source_requirements: Object.fromEntries(sources.map((source) => [source, {
                    unit, identity_requirements: ['administration', 'metric'],
                }])),
            },
        };
        noPaperDeclared = true;
    }

    function startEdit(metric = null) {
        draft = metric ? {
            ...metric,
            original_code: metric.code,
            input_codes: [...(metric.input_codes ?? [])],
            ...(metric.item_codes !== undefined ? { item_codes: metric.item_codes == null ? null : [...metric.item_codes] } : {}),
            ...(metric.formula_expression !== undefined ? { formula_expression: metric.formula_expression } : {}),
            ...(metric.formula_ast_json !== undefined ? { formula_ast_json: metric.formula_ast_json } : {}),
            ...(metric.precision_scale !== undefined ? { precision_scale: Number(metric.precision_scale) } : {}),
            required_output_scales: [...(metric.required_output_scales ?? ['percentile_rank'])],
            input_contract_json: cloneInputContract(metric.input_contract_json),
        } : {
            code: '', name: '', construct_aptitude_area_id: null,
            ...(finalOnlyMode ? {
                rule_kind: 'external_final',
                unit: null,
                precision_scale: 2,
                is_required: true,
                required_output_scales: ['percentile_rank'],
                input_codes: [],
                input_contract_json: {
                    allowed_sources: ['import', 'manual'],
                    required_resources: [],
                    source_requirements: {
                        import: { unit: 'percentile_rank', identity_requirements: ['administration', 'metric'] },
                        manual: { unit: 'percentile_rank', identity_requirements: ['administration', 'metric'] },
                    },
                },
            } : {
                rule_kind: 'correct_item_count',
                subtest_code: '', input_codes: [], required_output_scales: ['percentile_rank'], is_required: true, input_contract_json: null,
            }),
        };
        noPaperDeclared = Boolean(
            draft.input_contract_json
            && draft.input_contract_json.allowed_sources.length > 0
            && draft.input_contract_json.required_resources.length === 0,
        );
        rows = (tables[draft.code] ?? []).map((row) => ({ ...row }));
        editorOpen = true;
        pasteOpen = false;
        pasteError = '';
        formulaError = '';
    }


    function closeEditor(open) {
        editorOpen = open;
        if (!open) {
            pasteOpen = false;
            pasteError = '';
        }
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
            subtest_code: draft.subtest_code ?? '',
            input_codes: [],
            item_codes: null,
        };
    }

    function eligibleItems(subtest) {
        return (subtest?.items ?? []).filter((item) =>
            item.is_scored !== false && item.group_type !== 'practice' && item.is_practice !== true
            && typeof item.item_code === 'string' && item.item_code.trim() !== '',
        );
    }

    function toggleItem(itemCode, checked) {
        const currentSubtest = subtests.find((subtest) => subtest.code === draft.subtest_code);
        const codes = new Set(draft.item_codes ?? eligibleItems(currentSubtest).map((item) => item.item_code));
        if (checked) codes.add(itemCode);
        else codes.delete(itemCode);
        draft = { ...draft, item_codes: [...codes] };
        onDirty(true);
    }

    function setFormulaMode(enabled) {
        formulaError = '';
        draft = enabled
            ? { ...draft, rule_kind: 'formula_raw_metrics', formula_expression: draft.formula_expression ?? (draft.input_codes ?? []).join(' + ') }
            : { ...draft, rule_kind: 'sum_raw_metrics' };
        onDirty(true);
    }

    function togglePercentileRequirement(checked) {
        const scales = new Set(draft.required_output_scales ?? ['percentile_rank']);
        if (checked) scales.add('percentile_rank');
        else scales.delete('percentile_rank');
        draft = { ...draft, required_output_scales: [...scales] };
        onDirty(true);
    }

    function resetFormulaToSum() {
        draft = { ...draft, rule_kind: 'sum_raw_metrics', formula_expression: null, formula_ast_json: null, precision_scale: 0 };
        formulaError = '';
        onDirty(true);
    }


    function updateRow(index, field, value) {
        rows = rows.map((row, rowIndex) => rowIndex === index
            ? { ...row, [field]: value === '' ? '' : field === 'output_value' ? Number(value) : String(value) }
            : row);
        pasteError = '';
        onDirty(true);
    }

    function addRow() {
        const scale = metricRawPrecisionScale();
        const last = rows.at(-1);
        const next = last ? nextDecimalGridValue(last.max_score, scale) : String(formulaRange?.min ?? 0);
        rows = [...rows, { min_score: next ?? '', max_score: next ?? '', output_value: '' }];
        onDirty(true);
    }

    function generateRows() {
        const generated = formulaRange
            ? generateDecimalGridIntervals(formulaRange.min, formulaRange.max, metricRawPrecisionScale(), 500)
            : null;
        if (!generated) {
            pasteError = 'This range exceeds the 500-row generation limit or is not on the declared decimal grid. Add intervals manually.';
            return;
        }
        rows = generated;
        pasteError = '';
        onDirty(true);
    }

    function parsePaste() {
        const parsed = pasteText.trim().split(/\r?\n/).filter(Boolean).map((line) => line.split(/[\t,]/).map((cell) => cell.trim()));
        const data = parsed.filter((cells, index) => !(index === 0 && /min|from/i.test(cells[0] ?? '')));
        const nextRows = data.map((cells) => ({ min_score: cells[0] ?? '', max_score: cells[1] ?? '', output_value: cells[2] ?? '' }));
        rows = nextRows;
        if (!nextRows.length || !nextRows.every((row) =>
            row.min_score !== '' && Number.isFinite(Number(row.min_score))
            && row.max_score !== '' && Number.isFinite(Number(row.max_score))
            && row.output_value !== '' && Number.isFinite(Number(row.output_value))
            && Number(row.output_value) >= 0 && Number(row.output_value) <= 100
        )) {
            pasteError = 'Paste rows must contain finite raw bounds on the declared decimal grid and percentile values from 0 to 100. Invalid rows are retained for correction.';
            onDirty(true);
            return;
        }
        pasteOpen = false;
        pasteError = '';
        onDirty(true);
    }

    async function copyRows() {
        await navigator.clipboard.writeText(rows.map((row) => `${row.min_score}\t${row.max_score}\t${row.output_value}`).join('\n'));
    }

    function saveMetric() {
        const code = String(draft.code ?? '').trim().toUpperCase();
        if (!code || !String(draft.name ?? '').trim()) return;
        if (pasteError) return;
        if (finalOnlyMode) {
            const oldCode = draft.original_code ?? null;
            if (!draft.construct_aptitude_area_id) {
                pasteError = 'Select an institutional construct for this final aptitude.';
                return;
            }
            if (metrics.some((item) => item.code === code && item.code !== oldCode)) {
                pasteError = 'That code is already used by another aptitude area.';
                return;
            }
            const sources = ['import', 'manual'];
            const metric = {
                ...draft,
                code,
                name: String(draft.name).trim(),
                rule_kind: 'external_final',
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
                construct_aptitude_area_id: Number(draft.construct_aptitude_area_id),
                input_contract_json: {
                    allowed_sources: sources,
                    required_resources: [],
                    source_requirements: Object.fromEntries(sources.map((source) => [source, {
                        unit: 'percentile_rank', identity_requirements: ['administration', 'metric'],
                    }])),
                },
            };
            metrics = oldCode
                ? metrics.map((item) => item.code === oldCode ? metric : item)
                : [...metrics, metric];
            const nextTables = { ...tables };
            if (oldCode && oldCode !== code) delete nextTables[oldCode];
            delete nextTables[code];
            tables = nextTables;
            editorOpen = false;
            onDirty(true);
            return;
        }
        if (!intervalRowsWellFormed(rows, metricRawPrecisionScale())) {
            pasteError = 'Enter valid raw bounds and percentile values before applying this draft.';
            return;
        }
        if (!contractReady()) {
            pasteError = 'Declare No Paper explicitly or select at least one required resource before applying this area.';
            return;
        }
        const type = metricType(draft);
        const selectedSubtest = subtests.find((item) => item.code === draft.subtest_code);
        if (type === 'direct' && selectedSubtest?.response_mode !== 'recorded_measure' && draft.item_codes != null) {
            const allowed = new Set(eligibleItems(selectedSubtest).map((item) => item.item_code));
            if (!draft.item_codes.length || new Set(draft.item_codes).size !== draft.item_codes.length || draft.item_codes.some((itemCode) => !allowed.has(itemCode))) {
                pasteError = 'Select one or more eligible scored item codes from this subtest.';
                return;
            }
        }
        let formulaAst = draft.formula_ast_json ?? null;
        if (formulaMode()) {
            try {
                formulaAst = compileFormula(String(draft.formula_expression ?? ''), draft.input_codes ?? []);
                if ((draft.input_codes ?? []).length === 0) throw new Error('Select at least one checked raw metric input.');
            } catch (error) {
                formulaError = error instanceof Error ? error.message : 'Enter a valid formula using checked inputs.';
                return;
            }
        }
        const oldCode = draft.original_code ?? null;
        const existing = metrics.find((item) => item.code === oldCode);
        if (metrics.some((item) => item.code === code && item.code !== oldCode)) {
            pasteError = 'That code is already used by another aptitude area.';
            return;
        }
        const ruleKind = type === 'composite' ? (formulaMode() ? 'formula_raw_metrics' : 'sum_raw_metrics') : draft.rule_kind === 'recorded_measure' ? 'recorded_measure' : 'correct_item_count';
        const unit = ruleKind === 'recorded_measure'
            ? draft.unit || selectedSubtest?.unit || null
            : ruleKind === 'sum_raw_metrics' || ruleKind === 'formula_raw_metrics' ? metricUnit(draft) : 'count';
        const inputContract = cloneInputContract(draft.input_contract_json);
        const metric = {
            ...draft,
            input_contract_json: inputContract,
            code,
            name: String(draft.name).trim(),
            rule_kind: ruleKind,
            subtest_code: type === 'direct' ? draft.subtest_code : null,
            unit,
            min_raw_value: formulaMode() || ruleKind === 'recorded_measure' ? String(formulaRange?.min ?? draft.min_raw_value ?? 0) : formulaRange?.min ?? 0,
            max_raw_value: formulaMode() || ruleKind === 'recorded_measure' ? String(formulaRange?.max ?? draft.max_raw_value ?? 0) : formulaRange?.max ?? 0,
            precision_scale: formulaMode() || ruleKind === 'recorded_measure' ? Number(draft.precision_scale ?? 0) : 0,
            is_required: draft.is_required ?? true,
            required_output_scales: draft.required_output_scales ?? ['percentile_rank'],
            procedure_reference: draft.procedure_reference ?? null,
            construct_aptitude_area_id: draft.construct_aptitude_area_id ? Number(draft.construct_aptitude_area_id) : null,
            input_codes: type === 'composite' ? [...(draft.input_codes ?? [])] : [],
            ...(type === 'direct' && draft.item_codes !== undefined ? { item_codes: draft.item_codes == null ? null : [...draft.item_codes] } : {}),
            ...(formulaMode() ? { formula_expression: String(draft.formula_expression ?? '').trim().startsWith('=') ? String(draft.formula_expression).trim() : `=${String(draft.formula_expression ?? '').trim()}`, formula_ast_json: formulaAst } : {}),
        };
        if (inputContract && unit) {
            inputContract.source_requirements = Object.fromEntries(inputContract.allowed_sources.map((source) => [source, {
                unit,
                identity_requirements: ['administration', 'metric', ...inputContract.required_resources].sort(),
            }]));
        }
        metrics = existing
            ? metrics.map((item) => item.code === oldCode ? metric : {
                ...item,
                input_codes: (item.input_codes ?? []).map((inputCode) => inputCode === oldCode ? code : inputCode),
            })
            : [...metrics, metric];
        if (oldCode && oldCode !== code) {
            const nextTables = { ...tables };
            if (Object.hasOwn(nextTables, oldCode)) {
                nextTables[code] = nextTables[oldCode];
                delete nextTables[oldCode];
            }
            tables = nextTables;
        }
        tables = { ...tables, [code]: rows.map((row) => ({ min_score: String(row.min_score), max_score: String(row.max_score), output_value: String(row.output_value) })) };
        editorOpen = false;
        onDirty(true);
    }

    function isReferencedMetric(code) {
        return metrics.some((metric) => (metric.input_codes ?? []).includes(code));
    }

    function removeMetric(code) {
        if (isReferencedMetric(code)) return;
        metrics = metrics.filter((metric) => metric.code !== code);
        const nextTables = { ...tables };
        delete nextTables[code];
        tables = nextTables;
        onDirty(true);
    }

    function formula(metric) {
        if (metric.rule_kind === 'external_final') return 'Externally supplied PR (0–100)';
        if (metric.rule_kind === 'formula_raw_metrics') return `= ${String(metric.formula_expression ?? 'Incomplete formula').replace(/^=\s*/, '')}`;
        if (metricType(metric) === 'composite') return `= ${(metric.input_codes ?? []).join(' + ') || 'No inputs'}`;
        const subtest = subtests.find((item) => item.code === metric.subtest_code);
        return `= ${subtest?.code ?? metric.subtest_code ?? 'No subtest'} Raw${metric.item_codes != null ? ` · ${metric.item_codes.length} selected items` : ''}`;
    }

    function constructName(metric) {
        const construct = constructs.find((item) => String(item.id ?? item.code) === String(metric.construct_aptitude_area_id ?? metric.construct_id ?? ''));
        return construct?.name ?? construct?.label ?? construct?.code ?? '';
    }
</script>


<section id="aptitude-areas" class="rounded-xl border border-border bg-card p-5 sm:p-6 shadow-xs space-y-4">
    <header class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex min-w-0 items-center gap-2"><Table class="size-5 text-primary" /><h2 class="text-base font-semibold">{finalOnlyMode ? 'Aptitude Final Scores' : 'Aptitude Areas &amp; Conversion Tables'}</h2><InfoPopover title={finalOnlyMode ? 'Externally supplied percentile ranks' : 'Raw formulas & percentile norms'} content={finalOnlyMode ? 'Defines the six required aptitude constructs and their externally supplied percentile-rank values. No raw formula or local norm table is authored here.' : 'Defines how each Aptitude Area is calculated (either directly from 1 subtest or as a Composite Sum of multiple raw metrics), links it to an institutional Construct for result sheets and benchmarks, and maps raw scores to 0–100 percentiles.'} /><Badge variant="secondary">{metrics.length}</Badge></div>
        <Button type="button" class="min-h-10 gap-2" disabled={readOnly} onclick={() => startEdit()}><Plus class="size-4" />Add Area</Button>
    </header>
    {#if metrics.length === 0}
        <p class="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">No aptitude areas have been added.</p>
    {:else}
        <div class="overflow-x-auto rounded-lg border">
            <table aria-label="Aptitude areas" class="w-full min-w-[760px] text-left text-sm">
                <thead class="bg-muted/40 text-xs text-muted-foreground"><tr><th class="p-3">Code</th><th class="p-3">Construct / Name</th><th class="p-3">{finalOnlyMode ? 'Final score contract' : 'Raw Score Formula'}</th>{#if !finalOnlyMode}<th class="p-3">Ceiling</th>{/if}<th class="p-3">{finalOnlyMode ? 'Precision' : 'Norms Table'}</th><th class="p-3">Actions</th></tr></thead>
                <tbody class="divide-y">
                    {#each metrics as metric (metric.code)}
                        {@const rowCount = (tables[metric.code] ?? []).length}
                        <tr class="transition-colors hover:bg-muted/40"><td class="p-3 font-mono">{metric.code}</td><td class="p-3"><div class="font-medium">{metric.name}</div>{#if constructName(metric)}<Badge variant="outline" class="mt-1">{constructName(metric)}</Badge>{/if}</td><td class="p-3"><span class="inline-block rounded-md bg-muted/60 px-2 py-0.5 font-mono text-xs text-foreground/90">{formula(metric)}</span></td>{#if !finalOnlyMode}<td class="p-3">{metric.max_raw_value ?? metric.max_raw_score ?? 0} pts</td>{/if}<td class="p-3">{#if finalOnlyMode}<Badge variant="secondary">{metric.precision_scale ?? 0} decimal places · PR</Badge>{:else if rowCount}<Badge class="border-emerald-500/20 bg-emerald-500/10 font-medium text-emerald-700 dark:text-emerald-300">{rowCount} rows</Badge>{:else if !(metric.required_output_scales ?? ['percentile_rank']).includes('percentile_rank')}<Badge variant="secondary">Raw only</Badge>{:else}<Badge class="border-amber-500/20 bg-amber-500/10 font-medium text-amber-700 dark:text-amber-300">Needs Table</Badge>{/if}</td><td class="p-3"><div class="flex gap-2">{#if readOnly && rowCount > 0}<Button type="button" variant="outline" size="sm" onclick={() => startEdit(metric)}>View Table</Button>{/if}<Button type="button" variant="outline" size="sm" aria-label="Edit Area" disabled={readOnly} onclick={() => startEdit(metric)}>Edit Area</Button><Button type="button" variant="ghost" size="icon" aria-label={`Delete ${metric.name}`} disabled={readOnly || isReferencedMetric(metric.code)} title={isReferencedMetric(metric.code) ? 'Another metric depends on this area' : `Delete ${metric.name}`} onclick={() => removeMetric(metric.code)}><Trash2 class="size-4 text-destructive" /></Button></div></td></tr>
                    {/each}
                </tbody>
            </table>
        </div>
    {/if}
</section>

<Dialog.Root open={editorOpen} onOpenChange={closeEditor}>
    <Dialog.Content class="flex max-h-[90dvh] w-[calc(100vw-2rem)] max-w-none flex-col overflow-hidden rounded-2xl border bg-card p-0 shadow-2xl sm:w-[calc(100vw-2rem)] sm:max-w-none lg:w-[min(64rem,calc(100vw-4rem))]">
        <div class="shrink-0 border-b px-4 py-4 pr-12 sm:px-6 sm:pr-14"><Dialog.Title class="break-words text-lg font-semibold">{finalOnlyMode ? (draft?.code ? `Edit Final Aptitude: ${draft.name || draft.code}` : 'Add Final Aptitude') : (draft?.code ? `Edit Aptitude Area: ${draft.name || draft.code}` : 'Add Aptitude Area')}</Dialog.Title><Dialog.Description class="mt-1 text-sm text-muted-foreground">{finalOnlyMode ? 'Define a required externally supplied percentile-rank final for one institutional construct.' : 'Configure the raw-score formula and its percentile conversion intervals.'}</Dialog.Description></div>
        <div class="min-h-0 flex-1 space-y-6 overflow-y-auto px-4 py-5 sm:px-6">
            <section class="grid gap-4 sm:grid-cols-3">
                <div class="space-y-2"><Label for="area-code">Code</Label><Input id="area-code" value={draft?.code ?? ''} oninput={(event) => { draft = { ...draft, code: event.currentTarget.value.toUpperCase() }; pasteError = ''; }} disabled={readOnly} /></div>
                <div class="space-y-2"><Label for="area-name">Name</Label><Input id="area-name" value={draft?.name ?? ''} oninput={(event) => { draft = { ...draft, name: event.currentTarget.value }; pasteError = ''; }} disabled={readOnly} /></div>
                <div class="space-y-2 text-sm"><div class="flex items-center gap-1.5"><label for="area-construct">Construct</label><InfoPopover title="Institutional aptitude construct" content="Links this metric to one of the 6 institutional Aptitude Areas (General Ability, Verbal, Numerical, Spatial, Perceptual, Manual Dexterity) so Result Sheet placeholders and Decision Support benchmarks populate automatically." /></div><select id="area-construct" class={inputClass} value={draft?.construct_aptitude_area_id ?? ''} disabled={readOnly} onchange={(event) => (draft = { ...draft, construct_aptitude_area_id: event.currentTarget.value || null })}><option value="">No construct</option>{#each constructs as construct (construct.id ?? construct.code)}<option value={construct.id ?? construct.code}>{construct.name ?? construct.label ?? construct.code}</option>{/each}</select></div>
            </section>
            {#if finalOnlyMode}
                <section class="space-y-4 rounded-lg border border-border/70 p-4">
                    <p class="text-sm text-muted-foreground">This aptitude area receives an externally supplied percentile-rank final. Raw formulas, subtests, items, and conversion tables do not apply.</p>
                    <label class="block max-w-xs space-y-1 text-sm">PR decimal precision<select class={inputClass} disabled={readOnly} value={draft.precision_scale ?? 2} onchange={(event) => (draft = { ...draft, precision_scale: Number(event.currentTarget.value) })}>{#each [0, 1, 2, 3, 4, 5, 6] as scale (scale)}<option value={scale}>{scale} decimal places</option>{/each}</select></label>
                    <label class="flex min-h-10 items-center gap-2 text-sm"><input type="checkbox" checked disabled />Required final score · allowed acquisition: manual and import</label>
                </section>
            {:else}
            <fieldset class="space-y-3"><legend class="inline-flex items-center gap-1.5 font-semibold"><span>Formula Selector</span><InfoPopover title="Direct Subtest vs. Composite Sum" content="Direct Subtest Raw takes one subtest score. Composite Sum adds selected raw metrics from the same sitting; a custom formula can weight or combine those checked inputs." /></legend>
                <label class="flex items-center gap-2 text-sm"><input type="radio" name="area-formula" checked={metricType(draft) === 'direct'} disabled={readOnly} onchange={() => setType('direct')} />Direct Subtest Raw</label>
                <label class="flex items-center gap-2 text-sm"><input type="radio" name="area-formula" checked={metricType(draft) === 'composite'} disabled={readOnly} onchange={() => setType('composite')} />Composite Sum</label>
                {#if metricType(draft) === 'direct'}{@const directSubtest = subtests.find((subtest) => subtest.code === draft.subtest_code)}<label class="block max-w-md space-y-2 text-sm">Subtest<select class={inputClass} value={draft.subtest_code ?? ''} disabled={readOnly} onchange={(event) => { const subtest = subtests.find((item) => item.code === event.currentTarget.value); draft = { ...draft, subtest_code: event.currentTarget.value, rule_kind: subtest?.response_mode === 'recorded_measure' ? 'recorded_measure' : 'correct_item_count', item_codes: null, min_raw_value: subtest?.min_raw_value ?? 0, max_raw_value: subtest?.max_raw_value ?? subtest?.max_raw_score ?? maxSubtest(subtest), precision_scale: Number(draft.precision_scale ?? subtest?.precision_scale ?? 0) }; }}><option value="">Select a subtest</option>{#each subtests as subtest (subtest.code)}<option value={subtest.code}>{subtest.name ?? subtest.display_name ?? subtest.code} · Max {maxSubtest(subtest)}</option>{/each}</select></label>{#if directSubtest?.response_mode === 'recorded_measure'}<div class="grid gap-3 sm:grid-cols-3"><label class="space-y-1 text-sm">Minimum raw value<Input type="number" step={10 ** -metricRawPrecisionScale()} value={draft.min_raw_value ?? directSubtest.min_raw_value ?? 0} disabled={readOnly} oninput={(event) => (draft = { ...draft, min_raw_value: event.currentTarget.value })} /></label><label class="space-y-1 text-sm">Maximum raw value<Input type="number" step={10 ** -metricRawPrecisionScale()} value={draft.max_raw_value ?? directSubtest.max_raw_value ?? 0} disabled={readOnly} oninput={(event) => (draft = { ...draft, max_raw_value: event.currentTarget.value })} /></label><label class="space-y-1 text-sm">Raw precision<select class={inputClass} value={draft.precision_scale ?? 0} disabled={readOnly} onchange={(event) => (draft = { ...draft, precision_scale: Number(event.currentTarget.value) })}>{#each [0, 1, 2, 3, 4, 5, 6] as scale (scale)}<option value={scale}>{scale} decimal places</option>{/each}</select></label></div>{/if}{:else}<fieldset class="space-y-2"><legend class="text-sm font-medium">Available raw metrics</legend>{#each metrics.filter((metric) => metric.code !== (draft.original_code ?? draft.code)) as metric (metric.code)}<label class="flex items-center gap-2 text-sm"><input type="checkbox" checked={(draft.input_codes ?? []).includes(metric.code)} disabled={readOnly} onchange={(event) => { const codes = new Set(draft.input_codes ?? []); event.currentTarget.checked ? codes.add(metric.code) : codes.delete(metric.code); draft = { ...draft, input_codes: [...codes] }; }} />{metric.name} <span class="text-muted-foreground">({maxMetric(metric)})</span></label>{/each}</fieldset>{/if}
                <p class="rounded-lg border bg-muted/30 px-4 py-3 font-mono text-sm" aria-live="polite">{metricType(draft) === 'direct' ? `= ${subtests.find((item) => item.code === draft.subtest_code)?.code ?? 'Select subtest'} Raw` : `= ${inputMetrics.map((item) => item.code).join(' + ') || 'Select inputs'}`} =&gt; Range: {formulaRange?.min ?? '—'}–{formulaRange?.max ?? '—'} pts</p>
            </fieldset>
            {#if metricType(draft) === 'direct'}
                {@const selectedSubtest = subtests.find((item) => item.code === draft.subtest_code)}
                {#if selectedSubtest?.response_mode !== 'recorded_measure'}
                    {@const keyedItems = eligibleItems(selectedSubtest)}
                    <fieldset class="space-y-2 rounded-lg border p-4">
                        <legend class="px-1 text-sm font-medium">Scored item scope</legend>
                        <p class="text-xs text-muted-foreground">Select stable item codes from this subtest. Leave all checked for the whole subtest.</p>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" class="min-h-9 rounded-md border px-3 text-sm" disabled={readOnly} onclick={() => (draft = { ...draft, item_codes: null })}>All scored items</button>
                        </div>
                        <div class="grid gap-2 sm:grid-cols-2">
                            {#each keyedItems as item (item.item_code)}
                                <label class="flex min-h-10 items-center gap-2 rounded-md border px-3 text-sm">
                                    <input type="checkbox" disabled={readOnly} checked={draft.item_codes == null || draft.item_codes.includes(item.item_code)} onchange={(event) => toggleItem(item.item_code, event.currentTarget.checked)} />
                                    {item.item_code} <span class="text-muted-foreground">item {item.local_number ?? item.display_order ?? '—'}</span>
                                </label>
                            {:else}<p class="text-sm text-muted-foreground">No eligible keyed items in this subtest.</p>{/each}
                        </div>
                    </fieldset>
                {/if}
            {:else}
                <label class="flex min-h-10 items-center gap-2 rounded-md border px-3 text-sm">
                    <input type="checkbox" checked={formulaMode()} disabled={readOnly} onchange={(event) => setFormulaMode(event.currentTarget.checked)} />
                    Use a custom formula
                </label>
                {#if formulaMode()}
                    <section class="space-y-3 rounded-lg border p-4">
                        <div class="flex items-center justify-between gap-2"><label for="area-custom-formula" class="text-sm font-medium">Formula expression</label><button type="button" class="min-h-9 rounded-md border px-3 text-sm" disabled={readOnly} onclick={resetFormulaToSum}>Reset to Sum</button></div>
                        <FormulaExpressionInput
                            id="area-custom-formula"
                            value={draft.formula_expression ?? ''}
                            variableCodes={inputMetrics.map((metric) => metric.code)}
                            disabled={readOnly}
                            ariaDescribedBy="area-formula-help"
                            onValueChange={(expression) => { draft = { ...draft, formula_expression: expression, formula_ast_json: null }; formulaError = ''; }}
                        />
                        <p id="area-formula-help" class="text-xs text-muted-foreground">ROUND(value, places) uses 0–6 places and rounds half away from zero. Type a prefix or press Ctrl+Space for suggestions; use ↑/↓, Enter, or Escape.</p>
                        <label class="block max-w-xs space-y-1 text-sm">Output precision<select class={inputClass} disabled={readOnly} value={draft.precision_scale ?? 0} onchange={(event) => (draft = { ...draft, precision_scale: Number(event.currentTarget.value) })}>{#each [0, 1, 2, 3, 4, 5, 6] as scale (scale)}<option value={scale}>{scale} decimal places</option>{/each}</select></label>
                        {#if formulaError || formulaIssue}<p class="text-sm text-destructive" role="alert">{formulaError || formulaIssue}</p>{/if}
                    </section>
                {/if}
            {/if}
            <label class="block space-y-1.5 text-sm">Metric procedure reference
                <Input value={draft.procedure_reference ?? ''} disabled={readOnly} oninput={(event) => (draft = { ...draft, procedure_reference: event.currentTarget.value })} />
            </label>
            <section class="space-y-3 rounded-lg border border-border/70 p-4" aria-labelledby="metric-input-contract-heading">
                <h3 id="metric-input-contract-heading" class="flex items-center gap-1.5 font-semibold"><span>Metric input sources and materials</span><InfoPopover title="Input sources & No-Paper mode" content="Click 'Declare No Paper (manual / import)' when scores are entered manually or imported via CSV without requiring a physical Answer Sheet or Question Booklet. Select OMR and paper resources only when scanning bubble sheets." /></h3>
                {#if draft.input_contract_json === null}
                    <p class="text-sm text-amber-700 dark:text-amber-300">Input requirements are unresolved. Choose No Paper or declare sources and required materials.</p>
                {/if}
                <div class="flex flex-wrap gap-2">
                    <Button type="button" variant="outline" size="sm" disabled={readOnly || !metricUnit(draft)} onclick={declareNoPaper}>Declare No Paper (manual / import)</Button>
                    {#each ['import', 'manual', 'omr'] as source (source)}
                        <label class="flex min-h-9 items-center gap-2 rounded-md border px-3 text-sm">
                            <input type="checkbox" checked={contractSelected('allowed_sources', source)} disabled={readOnly || !metricUnit(draft)} onchange={(event) => setContractChoice('allowed_sources', source, event.currentTarget.checked)} />
                            {source}
                        </label>
                    {/each}
                </div>
                <fieldset class="flex flex-wrap gap-2">
                    <legend class="mb-2 text-sm font-medium">Required resources</legend>
                    {#each ['answer_sheet', 'booklet_form', 'key', 'physical_procedure'] as resource (resource)}
                        <label class="flex min-h-9 items-center gap-2 rounded-md border px-3 text-sm">
                            <input type="checkbox" checked={contractSelected('required_resources', resource)} disabled={readOnly || !metricUnit(draft)} onchange={(event) => setContractChoice('required_resources', resource, event.currentTarget.checked)} />
                            {resource.replaceAll('_', ' ')}
                        </label>
                    {/each}
                </fieldset>
                {#if !metricUnit(draft)}<p class="text-xs text-amber-700 dark:text-amber-300">A resolved metric unit is required before declaring source requirements.</p>{/if}
                {#if draft.input_contract_json}<p class="text-xs text-muted-foreground">Declared sources: {draft.input_contract_json.allowed_sources.join(', ') || 'none'} · resources: {draft.input_contract_json.required_resources.join(', ') || 'none'}</p>{/if}
            </section>
            <section class="space-y-3"><div class="flex flex-wrap items-center justify-between gap-2"><div class="min-w-0"><h3 class="flex items-center gap-1.5 font-semibold"><span>Percentile Conversion Table (0–100)</span><InfoPopover title="Contiguous raw-to-percentile intervals" content="Maps every raw score from 0 up to the formula's maximum ceiling into a 0–100 percentile rank with no gaps or overlaps. Use 'Magic: Generate 0-Max' or 'Paste TSV/Excel' for rapid entry." /></h3><p class="text-sm text-muted-foreground">Map each raw-score interval to a percentile rank.</p></div><div class="flex flex-wrap gap-2"><Button type="button" variant="outline" size="sm" disabled={readOnly} onclick={addRow}><Plus class="size-4" />Add Row</Button><Button type="button" variant="outline" size="sm" disabled={readOnly} onclick={generateRows}>Magic: Generate 0-Max</Button><Button type="button" variant="outline" size="sm" onclick={copyRows}><ClipboardCopy class="size-4" />Copy Table</Button><Button type="button" variant="outline" size="sm" disabled={readOnly} onclick={() => (pasteOpen = !pasteOpen)}>Paste TSV/Excel</Button></div></div>
                <label class="flex min-h-10 items-center gap-2 rounded-md border px-3 text-sm">
                    <input type="checkbox" checked={(draft.required_output_scales ?? ['percentile_rank']).includes('percentile_rank')} disabled={readOnly} onchange={(event) => togglePercentileRequirement(event.currentTarget.checked)} />
                    Require percentile-rank conversion
                </label>
                {#if pasteOpen}<div class="space-y-2"><label class="sr-only" for="interval-paste">Paste TSV or CSV intervals</label><textarea id="interval-paste" class="min-h-24 w-full rounded-md border bg-background p-3 font-mono text-sm" bind:value={pasteText} placeholder="min&#9;max&#9;output" /><Button type="button" size="sm" onclick={parsePaste}>Import Rows</Button>{#if pasteError}<p role="alert" class="text-sm text-destructive">{pasteError}</p>{/if}</div>{/if}
                {#if pasteError && !pasteOpen}<p role="alert" class="text-sm text-destructive">{pasteError}</p>{/if}
                <div class="overflow-x-auto rounded-lg border"><table class="w-full min-w-[620px] text-left text-sm"><thead class="bg-muted/40 text-xs text-muted-foreground"><tr><th class="p-3">Raw From</th><th class="p-3">Raw To</th><th class="p-3">Percentile Rank (0–100)</th><th class="p-3">Remove</th></tr></thead><tbody class="divide-y">{#each rows as row, index (`${index}-${row.min_score}-${row.max_score}`)}<tr><td class="p-2"><Input aria-label={`Row ${index + 1} raw from`} type="number" step={10 ** -metricRawPrecisionScale()} value={row.min_score} disabled={readOnly} onchange={(event) => updateRow(index, 'min_score', event.currentTarget.value)} /></td><td class="p-2"><Input aria-label={`Row ${index + 1} raw to`} type="number" step={10 ** -metricRawPrecisionScale()} value={row.max_score} disabled={readOnly} onchange={(event) => updateRow(index, 'max_score', event.currentTarget.value)} /></td><td class="p-2"><Input aria-label={`Row ${index + 1} percentile rank`} type="number" min="0" max="100" value={row.output_value} disabled={readOnly} onchange={(event) => updateRow(index, 'output_value', event.currentTarget.value)} /></td><td class="p-2"><Button type="button" variant="ghost" size="sm" disabled={readOnly} onclick={() => { rows = rows.filter((_, rowIndex) => rowIndex !== index); onDirty(true); }}>Remove</Button></td></tr>{:else}<tr><td colspan="4" class="p-5 text-center text-muted-foreground">No conversion rows.</td></tr>{/each}</tbody></table></div>
                <p class="flex items-center gap-2 text-sm" role="status">{#if !coverageRequired}<CheckCircle2 class="size-4 text-green-600" />Percentile conversion is not required.{:else if coverageOk}<CheckCircle2 class="size-4 text-green-600" />Coverage: {formulaRange?.min ?? '—'} to {formulaRange?.max ?? '—'} pts · Contiguous · No gaps or overlaps{:else}<AlertTriangle class="size-4 text-amber-600" />Coverage needs review: expected contiguous intervals from {formulaRange?.min ?? '—'} to {formulaRange?.max ?? '—'} with percentile values from 0 to 100.{/if}</p>
            </section>
            {/if}
        </div>
        <Dialog.Footer class="mx-0 mb-0 shrink-0 flex-wrap justify-end gap-2 border-t px-4 py-3 sm:px-6 sm:py-4"><Button type="button" variant="outline" onclick={() => closeEditor(false)}>Cancel</Button><Button type="button" disabled={readOnly || !contractReady() || !draft?.code?.trim() || !draft?.name?.trim() || !rowsValid || !formulaReady || !itemScopeReady} onclick={saveMetric}>Apply Area</Button></Dialog.Footer>
    </Dialog.Content>
</Dialog.Root>
