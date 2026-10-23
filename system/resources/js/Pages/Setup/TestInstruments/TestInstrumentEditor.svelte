<script>
    import { Link, router, useForm } from '@inertiajs/svelte';
    import { Button } from '@/Components/ui/button';
    import { Input } from '@/Components/ui/input';
    import { Label } from '@/Components/ui/label';
    import { Layers } from 'lucide-svelte';
    import InfoPopover from '@/Components/kit/InfoPopover.svelte';
    import AptitudeAreasStep from './AptitudeAreasStep.svelte';
    import AptitudeAreaModal from './AptitudeAreaModal.svelte';
    import SubtestSectionsStep from './SubtestSectionsStep.svelte';
    import * as Dialog from '@/Components/ui/dialog';


    let {
        mode,
        instrument = {},
        version = null,
        request_id = '',
        constructs = [],
        answer_sheet_connections = {},
        saveUrl = null,
        cancelUrl = null,
        kit_context = null,
        onSave = null,
        onDirtyChange = () => {},
        preparationId = null,
        instrumentId = instrument.id ?? null,
        normPackageId = null,
        activeTab = null,
        showSectionNavigation = true,
        saveLabel = 'Save Changes',
    } = $props();
    let discardOpen = $state(false);
    let discardDestination = $state(null);
    const editing = mode === 'edit';
    const initialProfile = {
        code: '',
        display_name: '',
        publisher_name: '',
        form_code: '',
        edition: '',
        language: '',
        source_reference: '',
        permitted_use_reference: '',
        ...version?.profile,
        material_requirement:
            version?.profile?.material_requirement ?? 'unresolved',
        administration_procedure_reference:
            version?.profile?.administration_procedure_reference ?? '',
    };
    const form = useForm({
        request_id,
        ...(editing ? { expected_revision: version.lock_version } : {}),
        profile: { ...initialProfile },
        subtests: (version?.subtests ?? []).map((subtest) => ({
            ...subtest,
            items: (subtest.items ?? []).map((item) => ({
                ...item,
                option_tokens: [...(item.option_tokens ?? [])],
            })),
        })),
        metrics: (version?.metrics ?? []).map((metric) => ({
            ...metric,
            input_codes: [...(metric.input_codes ?? [])],
            ...(metric.item_codes !== undefined ? { item_codes: metric.item_codes == null ? null : [...metric.item_codes] } : {}),
            ...(metric.formula_expression !== undefined ? { formula_expression: metric.formula_expression } : {}),
            ...(metric.formula_ast_json !== undefined ? { formula_ast_json: metric.formula_ast_json } : {}),
            ...(metric.precision_scale !== undefined ? { precision_scale: Number(metric.precision_scale) } : {}),
            required_output_scales: [...(metric.required_output_scales ?? [])],
            input_contract_json: cloneInputContract(metric.input_contract_json),
        })),
        tables: Object.fromEntries(
            (version?.tables ?? []).map((table) => [table.metric_code, table.intervals ?? []]),
        ),
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
    const tableScales = Object.fromEntries(
        (version?.tables ?? []).map((table) => [table.metric_code, table.output_scale]),
    );
    const inputContractSourceCodes = ['import', 'manual', 'omr'];
    const inputContractResourceCodes = ['answer_sheet', 'booklet_form', 'key', 'physical_procedure'];
    function materialRequirementProjection(metrics) {
        const declaredMetrics = (metrics ?? []).filter((metric) => metric?.input_contract_json && typeof metric.input_contract_json === 'object');
        if (!declaredMetrics.length) {
            return form.profile.material_requirement ?? 'unresolved';
        }
        if ((metrics ?? []).some((metric) => (
            !metric.input_contract_json
            || !Array.isArray(metric.input_contract_json.allowed_sources)
            || metric.input_contract_json.allowed_sources.length === 0
            || metric.input_contract_json.allowed_sources.some((source) => !inputContractSourceCodes.includes(source))
            || !Array.isArray(metric.input_contract_json.required_resources)
            || metric.input_contract_json.required_resources.some((resource) => !inputContractResourceCodes.includes(resource))
        ))) return 'unresolved';
        return metrics.some((metric) => metric.input_contract_json.required_resources.length > 0)
            ? 'paper_backed'
            : 'no_paper';
    }
    const initialFinalOnly = (version?.metrics ?? []).length > 0 && (version?.metrics ?? []).every((metric) => metric.rule_kind === 'external_final');
    let definitionKind = $state(initialFinalOnly ? 'final_only' : 'raw');
    let definitionSwitchNotice = $state('');
    let currentTab = $state(activeTab ?? (initialFinalOnly ? 'aptitudes' : 'subtests'));
    let openMetricCode = $state(null);
    let areaModalMode = $state('editor');
    let newMetric = $state(null);
    let editingMetric = $derived(
        form.metrics.find((metric) => metric.code === openMetricCode) ?? null,
    );

    $effect(() => {
        onDirtyChange(form.isDirty || openMetricCode !== null);
    });

    $effect(() => {
        if (activeTab) {
            currentTab = activeTab;
        }
    });
    function setDefinitionKind(nextKind) {
        const hasExistingDefinition = form.subtests.length > 0 || form.metrics.length > 0;
        const existingKind = form.metrics.length > 0 && form.metrics.every((metric) => metric.rule_kind === 'external_final') ? 'final_only' : 'raw';
        if (hasExistingDefinition && nextKind !== existingKind) {
            definitionSwitchNotice = 'This saved draft contains a different scoring definition. Create a new Test Setup or an explicit successor; the current draft was not changed.';
            return;
        }
        definitionKind = nextKind;
        definitionSwitchNotice = '';
        currentTab = nextKind === 'final_only' ? 'aptitudes' : 'subtests';
    }

    function openAreaModal(code, mode = 'editor') {
        newMetric = null;
        if (mode === 'add') {
            const existingCodes = new Set(form.metrics.map((metric) => metric.code));
            let nextNumber = 1;
            while (existingCodes.has(`AREA_${nextNumber}`)) {
                nextNumber++;
            }
            const sources = ['import', 'manual'];
            newMetric = {
                code: `AREA_${nextNumber}`,
                name: `Aptitude Area ${nextNumber}`,
                rule_kind: definitionKind === 'final_only' ? 'external_final' : 'correct_item_count',
                ...(definitionKind === 'final_only' ? {
                    unit: null,
                    precision_scale: 2,
                    is_required: true,
                    min_raw_value: null,
                    max_raw_value: null,
                    input_contract_json: {
                        allowed_sources: sources,
                        required_resources: [],
                        source_requirements: Object.fromEntries(sources.map((source) => [source, {
                            unit: 'percentile_rank', identity_requirements: ['administration', 'metric'],
                        }])),
                    },
                } : { input_contract_json: null }),
                subtest_code: '',
                input_codes: [],
                required_output_scales: ['percentile_rank'],
            };
            openMetricCode = newMetric.code;
        } else {
            openMetricCode = code;
        }
        areaModalMode = mode === 'simulator' || mode === 'csv' ? mode : 'editor';
    }

    function applyArea(metric, intervals, originalCode = null) {
        const targetCode = originalCode ?? metric.code;
        const exists = form.metrics.some((item) => item.code === targetCode || item.code === metric.code);
        form.metrics = exists
            ? form.metrics.map((item) => (item.code === targetCode || item.code === metric.code) ? metric : {
                ...item,
                input_codes: (item.input_codes ?? []).map((code) => code === targetCode ? metric.code : code),
            })
            : [...form.metrics, metric];
        const nextTables = { ...form.tables };
        if (originalCode && originalCode !== metric.code) {
            delete nextTables[originalCode];
        }
        nextTables[metric.code] = intervals;
        form.tables = nextTables;
        newMetric = null;
        openMetricCode = null;
    }

    function submit(event) {
        event?.preventDefault();
        form.profile = {
            ...form.profile,
            material_requirement: materialRequirementProjection(form.metrics),
        };
        const instrumentPayload = {
            id: instrument.id ?? null,
            version_id: version?.id ?? null,
            expected_revision: editing ? version.lock_version : null,
            profile: {
                ...form.profile,
                material_requirement: materialRequirementProjection(form.metrics),
            },
            subtests: form.subtests.map((subtest) => ({
                ...subtest,
                items: (subtest.items ?? []).map((item) => ({
                    ...item,
                    option_tokens: [...(item.option_tokens ?? [])],
                })),
            })),
            metrics: form.metrics.map((metric) => {
                const payloadMetric = { ...metric };
                delete payloadMetric.formula_algorithm_identity;
                return {
                    ...payloadMetric,
                    input_codes: [...(metric.input_codes ?? [])],
                    required_output_scales: [...(metric.required_output_scales ?? [])],
                    input_contract_json: cloneInputContract(metric.input_contract_json),
                };
            }),
        };

        if (onSave) {
            const normTables = Object.entries(form.tables)
                .filter(([, intervals]) => intervals.length > 0)
                .map(([metric_code, intervals]) => ({
                    metric_code,
                    output_scale: tableScales[metric_code] ?? 'percentile_rank',
                    intervals: intervals.map((interval) => ({
                        min_score: interval.min_score,
                        max_score: interval.max_score,
                        output_value: String(interval.output_value),
                    })),
                }))
                .filter((table) =>
                    table.output_scale === 'percentile_rank'
                    && form.metrics.find((metric) => metric.code === table.metric_code)
                        ?.required_output_scales?.includes('percentile_rank'),
                );
            Promise.resolve(onSave(instrumentPayload, normTables)).then((saved) => {
                if (saved) {
                    form.defaults();
                    form.reset();
                    onDirtyChange(false);
                }
            });
            return;
        }

        const url = saveUrl
            ? contextualUrl(saveUrl)
            : editing
              ? `/setup/test-instruments/${instrument.id}/versions/${version.id}`
              : '/setup/test-instruments';
        const method = editing ? 'put' : 'post';
        form.transform((data) => ({
            ...data,
            metrics: data.metrics.map((metric) => {
                const payloadMetric = { ...metric };
                delete payloadMetric.formula_algorithm_identity;
                return payloadMetric;
            }),
        }))[method](url);
    }

    function contextualUrl(url) {
        if (!kit_context && !preparationId && !normPackageId) {
            return url;
        }
        const context = kit_context ?? {
            test_kit_id: null,
            preparation_id: preparationId,
            test_kit_version_id: null,
        };
        const separator = url.includes('?') ? '&' : '?';
        const query = new URLSearchParams({
            ...(context.test_kit_id ? { test_kit_id: String(context.test_kit_id) } : {}),
            ...(context.preparation_id ? { preparation_id: String(context.preparation_id) } : {}),
            ...(context.test_kit_version_id ? { test_kit_version_id: String(context.test_kit_version_id) } : {}),
            ...(instrumentId ? { test_instrument_id: String(instrumentId) } : {}),
            ...(normPackageId ? { norm_package_id: String(normPackageId) } : {}),
        });
        return `${url}${separator}${query.toString()}`;
    }

    function requestCancel(event) {
        const destination = cancelUrl ?? (editing
            ? `/setup/test-instruments/${instrument.id}`
            : '/setup/test-instruments');
        if (!form.isDirty) {
            return;
        }
        event.preventDefault();
        discardDestination = contextualUrl(destination);
        discardOpen = true;
    }

    function discardChanges() {
        if (discardDestination) {
            router.visit(discardDestination);
        }
        discardOpen = false;
    }

</script>

<form class="space-y-6" onsubmit={submit}>
    {#if showSectionNavigation}
    <nav aria-label="Test instrument sections" class="border-border bg-card flex flex-wrap gap-2 rounded-xl border p-2">
        {#if definitionKind !== 'final_only'}
        <button
            type="button"
            aria-current={currentTab === 'subtests' ? 'page' : undefined}
            onclick={() => (currentTab = 'subtests')}
            class={`flex min-h-11 items-center gap-2 rounded-lg px-3 text-sm font-medium transition-colors ${currentTab === 'subtests' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted hover:text-foreground'}`}
        >
            <Layers class="h-4 w-4" />
            <span>Subtests ({form.subtests.length})</span>
        </button>
        {/if}
        <button
            type="button"
            aria-current={currentTab === 'aptitudes' ? 'page' : undefined}
            onclick={() => (currentTab = 'aptitudes')}
            class={`flex min-h-11 items-center gap-2 rounded-lg px-3 text-sm font-medium transition-colors ${currentTab === 'aptitudes' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted hover:text-foreground'}`}
        >
            <span>Aptitude Areas ({form.metrics.length})</span>
        </button>
    </nav>
    {/if}
    <section class="rounded-xl border border-border/70 bg-card p-4">
        <label class="flex flex-wrap items-center gap-3 text-sm font-medium">
            Scoring definition
            <select class="min-h-10 rounded-md border border-input bg-background px-3" value={definitionKind} onchange={(event) => setDefinitionKind(event.currentTarget.value)}>
                <option value="raw">Calculate from raw inputs</option>
                <option value="final_only">External aptitude final scores (PR)</option>
            </select>
        </label>
        {#if definitionSwitchNotice}<p class="mt-2 text-sm text-amber-700 dark:text-amber-300" role="status">{definitionSwitchNotice}</p>{/if}
    </section>
    <details class="rounded-lg border border-border/70 bg-muted/20 p-4">
        <summary class="cursor-pointer font-medium text-sm text-muted-foreground hover:text-foreground">Show Test Details & Source (Edition, Language, Procedure)</summary>
    <section
        class="border-border bg-card space-y-5 rounded-xl border p-4 sm:p-6"
        aria-labelledby="identity-heading"
    >
        <div>
            <h2 id="identity-heading" class="flex items-center gap-1.5 font-semibold">
                <span>Instrument and form identity</span>
                <InfoPopover
                    title="Test Setup metadata & provenance"
                    content="Records the official instrument code, edition, source citation, and administration procedure required before approving a Test Setup version."
                />
            </h2>
            <p class="text-muted-foreground mt-1 text-sm">
                Enter source references only. Do not paste question text, keys,
                or norm-table values here.
            </p>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-1.5">
                <Label for="instrument-code">Instrument code</Label>
                <Input
                    id="instrument-code"
                    bind:value={form.profile.code}
                    maxlength="64"
                    class="min-h-10"
                    disabled={editing}
                    required
                />
                {#if form.errors['profile.code']}<p
                        class="text-destructive text-sm"
                        role="alert"
                    >
                        {form.errors['profile.code']}
                    </p>{/if}
            </div>
            <div class="space-y-1.5">
                <Label for="profile-name">Display name</Label>
                <Input
                    id="profile-name"
                    bind:value={form.profile.display_name}
                    maxlength="255"
                    class="min-h-10"
                    required
                />
                {#if form.errors['profile.display_name']}<p
                        class="text-destructive text-sm"
                        role="alert"
                    >
                        {form.errors['profile.display_name']}
                    </p>{/if}
            </div>
            <div class="space-y-1.5">
                <Label for="publisher-name">Publisher / source owner</Label>
                <Input
                    id="publisher-name"
                    bind:value={form.profile.publisher_name}
                    maxlength="255"
                    class="min-h-10"
                />
            </div>
            <div class="space-y-1.5">
                <Label for="form-code">Form code</Label>
                <Input
                    id="form-code"
                    bind:value={form.profile.form_code}
                    maxlength="64"
                    class="min-h-10"
                />
            </div>
            <div class="space-y-1.5">
                <Label for="edition">Edition</Label>
                <Input
                    id="edition"
                    bind:value={form.profile.edition}
                    maxlength="128"
                    class="min-h-10"
                />
            </div>
            <div class="space-y-1.5">
                <Label for="language">Language</Label>
                <Input
                    id="language"
                    bind:value={form.profile.language}
                    maxlength="32"
                    class="min-h-10"
                />
            </div>
            <div class="space-y-1.5 sm:col-span-2">
                <Label for="source-reference">Official source reference</Label>
                <Input
                    id="source-reference"
                    bind:value={form.profile.source_reference}
                    maxlength="500"
                    class="min-h-10"
                    placeholder="Manual title, edition, page or authorized catalog reference"
                />
                <p class="text-muted-foreground text-xs">
                    Reference the source; do not upload or reproduce licensed
                    material.
                </p>
                {#if form.errors['profile.source_reference']}<p
                        class="text-destructive text-sm"
                        role="alert"
                    >
                        {form.errors['profile.source_reference']}
                    </p>{/if}
            </div>
            <div class="space-y-1.5 sm:col-span-2">
                <Label for="permitted-use-reference"
                    >Permitted-use / digitization reference</Label
                >
                <Input
                    id="permitted-use-reference"
                    bind:value={form.profile.permitted_use_reference}
                    maxlength="500"
                    class="min-h-10"
                    placeholder="Authority or permission reference"
                />
                {#if form.errors['profile.permitted_use_reference']}<p
                        class="text-destructive text-sm"
                        role="alert"
                    >
                        {form.errors['profile.permitted_use_reference']}
                    </p>{/if}
            </div>
            <div class="space-y-1.5">
                <div class="flex items-center gap-1.5">
                    <Label for="material-requirement">Material requirement</Label>
                    <InfoPopover
                        title="Derived material requirement"
                        content="Derived from metric declarations when metrics declare input contracts, or set directly before adding metrics: No Paper for scoring-only setups, Paper-backed when an Answer Sheet or Booklet is required."
                    />
                </div>
                <select
                    id="material-requirement"
                    aria-label="Material requirement"
                    value={materialRequirementProjection(form.metrics)}
                    onchange={(event) => { form.profile = { ...form.profile, material_requirement: event.currentTarget.value }; }}
                    class="border-input bg-background min-h-10 w-full rounded-md border px-3 text-sm"
                >
                    <option value="unresolved">Unresolved</option>
                    <option value="paper_backed">Paper-backed resources declared</option>
                    <option value="no_paper">No Paper declared for every metric</option>
                </select>
                <p class="text-muted-foreground text-xs">
                    Declaring No Paper indicates a direct-manual or import scoring procedure and does not create a schedule-free route.
                </p>
                {#if form.errors['profile.material_requirement']}<p
                        class="text-destructive text-sm"
                        role="alert"
                    >
                        {form.errors['profile.material_requirement']}
                    </p>{/if}
            </div>
            <div class="space-y-1.5 sm:col-span-2">
                <Label for="administration-procedure-reference">
                    Administration procedure reference
                </Label>
                <Input
                    id="administration-procedure-reference"
                    bind:value={form.profile.administration_procedure_reference}
                    maxlength="500"
                    class="min-h-10"
                    placeholder="Approved administration procedure or source reference"
                />
                <p class="text-muted-foreground text-xs">
                    Required before approval; drafts may remain incomplete.
                </p>
                {#if form.errors['profile.administration_procedure_reference']}<p
                        class="text-destructive text-sm"
                        role="alert"
                    >
                        {form.errors['profile.administration_procedure_reference']}
                    </p>{/if}
            </div>
        </div>
    </section>
    </details>
    {#if currentTab === 'subtests' && definitionKind !== 'final_only'}
    <SubtestSectionsStep
        bind:subtests={form.subtests}
        metrics={form.metrics}
        connections={answer_sheet_connections ?? {}}
        errors={form.errors}
    />

    {/if}

    {#if currentTab === 'aptitudes'}
        <AptitudeAreasStep
            bind:metrics={form.metrics}
            bind:tables={form.tables}
            subtests={form.subtests}
            constructs={constructs}
            finalOnlyMode={definitionKind === 'final_only'}
            errors={form.errors}
            onOpenModal={openAreaModal}
        />
    {/if}

    <AptitudeAreaModal
        open={openMetricCode !== null}
        editingMetric={newMetric ?? editingMetric}
        intervals={form.tables[openMetricCode] ?? []}
        subtests={form.subtests}
        allMetrics={form.metrics}
        constructs={constructs}
        finalOnlyMode={definitionKind === 'final_only'}
        initialMode={areaModalMode}
        onClose={() => { openMetricCode = null; newMetric = null; }}
        onApply={applyArea}
    />


    {#if Object.keys(form.errors).length > 0}
        <div
            class="border-destructive/40 bg-destructive/5 rounded-lg border p-4 text-sm"
            role="alert"
        >
            <h2 class="font-semibold">Review the highlighted profile fields</h2>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                {#each Object.entries(form.errors) as [field, error] (field)}<li
                    >
                        {error}
                    </li>{/each}
            </ul>
        </div>
    {/if}

    <footer class="sticky bottom-0 z-20 flex justify-end gap-2 border-t bg-background/95 p-3 backdrop-blur">
        <Link
            href={contextualUrl(cancelUrl ?? (editing
                ? `/setup/test-instruments/${instrument.id}`
                : '/setup/test-instruments'))}
            onclick={requestCancel}
        >
            <Button type="button" variant="outline" class="min-h-11" disabled={form.processing}>
                Cancel
            </Button>
        </Link>
        <Button type="button" class="min-h-11" disabled={form.processing} onclick={submit}>
            {form.processing ? 'Saving…' : saveLabel}
        </Button>
    </footer>

    <Dialog.Root open={discardOpen} onOpenChange={(open) => (discardOpen = open)}>
        <Dialog.Content>
            <Dialog.Header>
                <Dialog.Title>Discard unsaved changes?</Dialog.Title>
                <Dialog.Description>
                    Your instrument edits have not been saved. Leave this page and discard them?
                </Dialog.Description>
            </Dialog.Header>
            <Dialog.Footer>
                <button type="button" class="min-h-10 rounded-md border px-4" onclick={() => (discardOpen = false)}>Keep editing</button>
                <button type="button" class="min-h-10 rounded-md bg-destructive px-4 text-destructive-foreground" onclick={discardChanges}>Discard changes</button>
            </Dialog.Footer>
        </Dialog.Content>
    </Dialog.Root>
</form>
