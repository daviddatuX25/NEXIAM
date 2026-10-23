<script>
    import { router } from '@inertiajs/svelte';
    import { ArrowLeft, Save } from 'lucide-svelte';
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
    import InfoPopover from '@/Components/kit/InfoPopover.svelte';
    import { Button } from '@/Components/ui/button';
    import * as Dialog from '@/Components/ui/dialog';
    import SubtestsTableCard from '@/Components/Setup/SubtestsTableCard.svelte';
    import AptitudeAreasTableCard from '@/Components/Setup/AptitudeAreasTableCard.svelte';

    let {
        test_kit,
        preparation,
        read_only = false,
        test_setup = {},
        instrument_candidates = [],
        norm_candidates = [],
        constructs = [],
        kit_context,
        actions = {},
    } = $props();

    const instrument = $derived(test_setup.instrument ?? null);
    const norm = $derived(test_setup.norm ?? null);
    let subtests = $state((instrument?.subtests ?? []).map((subtest) => ({
        ...subtest,
        items: (subtest.items ?? []).map((item) => ({ ...item, option_tokens: [...(item.option_tokens ?? [])] })),
    })));
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
    function cloneMetrics(raw) {
        return (raw ?? []).map((metric) => ({
            ...metric,
            input_codes: [...(metric.input_codes ?? [])],
            required_output_scales: [...(metric.required_output_scales ?? [])],
            input_contract_json: cloneInputContract(metric.input_contract_json),
            ...(metric.item_codes !== undefined ? { item_codes: metric.item_codes == null ? null : [...metric.item_codes] } : {}),
            ...(metric.formula_expression !== undefined ? { formula_expression: metric.formula_expression } : {}),
            ...(metric.formula_ast_json !== undefined ? { formula_ast_json: metric.formula_ast_json } : {}),
            ...(metric.precision_scale !== undefined ? { precision_scale: Number(metric.precision_scale) } : {}),
        }));
    }
    let metrics = $state(cloneMetrics(instrument?.metrics));
    const initialFinalOnly = Boolean(instrument?.profile_metadata?.is_final_only) || (metrics.length > 0 && metrics.every((metric) => metric.rule_kind === 'external_final'));
    let definitionKind = $state(initialFinalOnly ? 'final_only' : 'raw');
    let definitionSwitchNotice = $state('');
    const isFinalOnly = $derived(definitionKind === 'final_only');
    function setDefinitionKind(nextKind) {
        const hasExistingDefinition = subtests.length > 0 || metrics.length > 0;
        if (hasExistingDefinition && nextKind !== definitionKind) {
            definitionSwitchNotice = 'This draft already contains a scoring definition. Create a new Test Setup or explicit successor to change kind; the current definition was not changed.';
            return;
        }
        definitionKind = nextKind;
        definitionSwitchNotice = '';
    }
    function initTables(raw) {
        if (!Array.isArray(raw)) return raw ?? {};
        return Object.fromEntries(raw.map((table) => [table.metric_code, (table.intervals ?? []).map((row) => ({ ...row }))]));
    }
    let tables = $state(initTables(norm?.tables));
    let showMetadata = $state(false);
    let instrumentProfile = $state({ material_requirement: 'unresolved', ...(instrument?.profile ?? {}) });
    let normPackage = $state({ ...(norm?.package ?? {}) });
    const instrumentRevision = () => `${instrument?.id ?? ''}:${instrument?.version_id ?? ''}:${instrument?.lock_version ?? ''}`;
    const normRevision = () => `${norm?.package_id ?? ''}:${norm?.version_id ?? ''}:${norm?.lock_version ?? ''}`;
    const preparationRevision = () => `${preparation?.id ?? ''}:${preparation?.revision ?? ''}`;
    let hydratedInstrumentRevision = $state(instrumentRevision());
    let hydratedNormRevision = $state(normRevision());
    let hydratedPreparationRevision = $state(preparationRevision());
    let acknowledgedSave = $state(false);
    $effect(() => {
        const nextInstrumentRevision = instrumentRevision();
        const nextNormRevision = normRevision();
        const nextPreparationRevision = preparationRevision();
        const instrumentChanged = nextInstrumentRevision !== hydratedInstrumentRevision;
        const normChanged = nextNormRevision !== hydratedNormRevision;
        const preparationChanged = nextPreparationRevision !== hydratedPreparationRevision;
        if ((!instrumentChanged && !normChanged && !preparationChanged && !acknowledgedSave) || (isDirty && !acknowledgedSave)) return;

        if (instrumentChanged || preparationChanged || acknowledgedSave) {
            subtests = (instrument?.subtests ?? []).map((subtest) => ({
                ...subtest,
                items: (subtest.items ?? []).map((item) => ({ ...item, option_tokens: [...(item.option_tokens ?? [])] })),
            }));
            metrics = cloneMetrics(instrument?.metrics);
            if (instrumentChanged || acknowledgedSave) instrumentProfile = { material_requirement: 'unresolved', ...(instrument?.profile ?? {}) };
        }
        if (normChanged || preparationChanged || acknowledgedSave) {
            tables = initTables(norm?.tables);
            if (normChanged || acknowledgedSave) normPackage = { ...(norm?.package ?? {}) };
        }
        hydratedInstrumentRevision = nextInstrumentRevision;
        hydratedNormRevision = nextNormRevision;
        hydratedPreparationRevision = nextPreparationRevision;
        if (acknowledgedSave) acknowledgedSave = false;
    });
    let instrumentDirty = $state(false);
    let tablesDirty = $state(false);
    let normDirty = $state(false);
    let saving = $state(false);
    let discardOpen = $state(false);
    let saveError = $state('');
    let selectedInstrument = $state('');
    let selectedNorm = $state('');
    let pendingVisit = $state(null);
    let allowNextVisit = false;

    const isDirty = $derived(instrumentDirty || tablesDirty || normDirty);
    const breadcrumbs = $derived(
        kit_context
            ? [
                  { label: 'Setup', href: '/setup' },
                  { label: 'Test Kits', href: actions.back ?? '/setup/exam-materials/test-kits' },
                  { label: kit_context.code ?? test_kit?.name ?? 'Test Kit', href: actions.back },
                  { label: 'Test Setup' },
              ]
            : test_kit?.name
              ? [
                    { label: 'Setup', href: '/setup' },
                    { label: 'Test Kits', href: actions.back ?? '/setup/exam-materials/test-kits' },
                    { label: test_kit.name, href: actions.back },
                    { label: 'Test Setup' },
                ]
              : [
                    { label: 'Setup', href: '/setup' },
                    { label: 'Test instruments', href: '/setup/test-instruments' },
                    { label: instrument?.profile?.display_name ?? instrument?.profile?.code ?? 'Test instrument', href: `/setup/test-instruments/${instrument?.id ?? ''}` },
                    { label: 'Test Setup' },
                ],
    );

    $effect(() => {
        if (!isDirty || typeof window === 'undefined') return;
        const guard = (event) => {
            event.preventDefault();
            event.returnValue = '';
        };
        window.addEventListener('beforeunload', guard);
        const removeBeforeVisit = router.on('before', (event) => {
            const visit = event.detail.visit;
            if (visit.method === 'get' && Array.isArray(visit.only) && visit.only.length > 0
                && visit.only.every((key) => key === 'notifications' || key === 'navigation_badges')) {
                const target = new URL(visit.url, window.location.href);
                if (target.origin === window.location.origin
                    && target.pathname === window.location.pathname
                    && target.search === window.location.search) return;
            }
            if (!isDirty || allowNextVisit) {
                allowNextVisit = false;
                return;
            }
            event.preventDefault();
            pendingVisit = event.detail.visit;
            discardOpen = true;
        });
        return () => {
            window.removeEventListener('beforeunload', guard);
            removeBeforeVisit();
        };
    });

    function idempotencyKey() {
        if (typeof globalThis.crypto?.randomUUID === 'function') {
            return globalThis.crypto.randomUUID();
        }

        const bytes = new Uint8Array(16);
        globalThis.crypto.getRandomValues(bytes);
        bytes[6] = (bytes[6] & 0x0f) | 0x40;
        bytes[8] = (bytes[8] & 0x3f) | 0x80;
        const hex = [...bytes].map((value) => value.toString(16).padStart(2, '0')).join('');
        return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
    }

    function requestBack(event) {
        if (!isDirty) return;
        event.preventDefault();
        pendingVisit = { url: kit_context?.back_url ?? actions.back, method: 'get' };
        discardOpen = true;
    }

    function discardAndReturn() {
        if (pendingVisit?.url) {
            allowNextVisit = true;
            const visitOptions = pendingVisit.options ?? {};
            router.visit(pendingVisit.url, {
                ...visitOptions,
                method: pendingVisit.method ?? 'get',
                data: pendingVisit.data ?? {},
                preserveScroll: pendingVisit.preserveScroll ?? false,
                preserveState: pendingVisit.preserveState ?? false,
                replace: pendingVisit.replace ?? false,
            });
        } else {
            window.location.assign(kit_context?.back_url ?? actions.back);
        }
        pendingVisit = null;
        discardOpen = false;
    }

    function keepEditing() {
        discardOpen = false;
        pendingVisit = null;
        selectedInstrument = '';
        selectedNorm = '';
    }

    function sendSelection(payload) {
        const options = {
            preserveScroll: true,
            onError: (errors) => {
                saveError = Object.values(errors).join(' ');
            },
            onSuccess: () => {
                saveError = '';
                selectedInstrument = '';
                selectedNorm = '';
            },
        };
        if (isDirty) {
            pendingVisit = {
                url: actions.save_preparation,
                method: 'put',
                data: payload,
                preserveScroll: true,
                options,
            };
            discardOpen = true;
            return;
        }
        allowNextVisit = true;
        router.put(actions.save_preparation, payload, options);
    }

    function selectSource(kind, value) {
        const selectionKey = kind === 'instrument' ? 'test_instrument_version_id' : 'norm_package_version_id';
        const selections = preparation?.selections;
        if (!preparation || !selections || !actions.save_preparation) {
            saveError = 'The server did not provide the exact preparation selection context. No source was changed.';
            return;
        }
        const sourceId = value === 'new' || value === '' ? null : Number(value);
        sendSelection({
            preparation_id: preparation.id,
            expected_revision: preparation.revision,
            idempotency_key: idempotencyKey(),
            test_instrument_version_id: selections.test_instrument_version_id ?? null,
            norm_package_version_id: selections.norm_package_version_id ?? null,
            answer_sheet_draft_id: selections.answer_sheet_draft_id ?? null,
            template_version_id: selections.template_version_id ?? null,
            booklet_version_id: selections.booklet_version_id ?? null,
            booklet_set_id: selections.booklet_set_id ?? null,
            [selectionKey]: sourceId,
        });
    }

    function saveTestSetup() {
        saveError = '';
        if ((!preparation && test_kit) || !actions.save) {
            saveError = 'The current preparation context is incomplete. Reload before saving.';
            return;
        }

        saving = true;
        allowNextVisit = true;
        const profileFields = ['code', 'display_name', 'publisher_name', 'form_code', 'edition', 'language', 'source_reference', 'permitted_use_reference', 'material_requirement', 'administration_procedure_reference'];
        const normPackageFields = ['code', 'display_name', 'publisher_name', 'form_code', 'edition', 'language', 'applicable_population', 'source_reference', 'permitted_use_reference'];
        const payload = {
            preparation_id: preparation?.id ?? null,
            expected_preparation_revision: preparation?.revision ?? null,
            idempotency_key: idempotencyKey(),
            instrument: {
                id: instrument?.id ?? null,
                version_id: instrument?.version_id ?? null,
                expected_revision: instrument?.lock_version ?? null,
                profile: Object.fromEntries(profileFields.map((field) => [
                    field,
                    field === 'material_requirement' && isFinalOnly
                        ? 'no_paper'
                        : instrumentProfile[field] ?? null,
                ])),
                subtests: subtests.map((subtest, index) => ({
                    code: subtest.code,
                    name: subtest.name,
                    display_order: index + 1,
                    response_mode: subtest.response_mode,
                    item_order_policy: subtest.item_order_policy ?? 'fixed',
                    items: (subtest.items ?? []).map((item, itemIndex) => ({
                        item_code: item.item_code,
                        local_number: String(item.local_number ?? item.display_order ?? itemIndex + 1),
                        display_order: itemIndex + 1,
                        is_scored: item.is_scored !== false,
                        option_tokens: Array.isArray(item.option_tokens) ? item.option_tokens : [],
                    })),
                })),
                metrics: metrics.map((metric) => ({
                    code: metric.code,
                    name: metric.name,
                    rule_kind: metric.rule_kind,
                    subtest_code: metric.subtest_code ?? null,
                    unit: metric.unit ?? null,
                    min_raw_value: metric.min_raw_value ?? null,
                    max_raw_value: metric.max_raw_value ?? null,
                    precision_scale: metric.precision_scale ?? null,
                    is_required: metric.is_required ?? true,
                    required_output_scales: metric.required_output_scales ?? [],
                    procedure_reference: metric.procedure_reference ?? null,
                    construct_aptitude_area_id: metric.construct_aptitude_area_id ?? null,
                    input_codes: [...(metric.input_codes ?? [])],
                    input_contract_json: cloneInputContract(metric.input_contract_json),
                    ...(metric.item_codes !== undefined ? { item_codes: metric.item_codes == null ? null : [...metric.item_codes] } : {}),
                    ...(metric.formula_expression !== undefined ? { formula_expression: metric.formula_expression } : {}),
                    ...(metric.formula_ast_json !== undefined ? { formula_ast_json: metric.formula_ast_json } : {}),
                })),
            },
            norm: isFinalOnly ? null : {
                package_id: norm?.package_id ?? null,
                version_id: norm?.version_id ?? null,
                expected_revision: norm?.lock_version ?? null,
                package: Object.fromEntries(normPackageFields.map((field) => [field, normPackage[field] ?? null])),
                tables: (Array.isArray(tables) ? tables : Object.entries(tables)
                    .filter(([, intervals]) => intervals.length > 0)
                    .map(([metric_code, intervals]) => ({
                        metric_code,
                        output_scale: 'percentile_rank',
                        intervals,
                    })))
                    .filter((table) => (table.intervals ?? []).length > 0)
                    .map((table) => ({
                        metric_code: table.metric_code,
                        output_scale: table.output_scale,
                        intervals: (table.intervals ?? []).map((row) => ({
                            min_score: row.min_score === '' ? '' : String(row.min_score),
                            max_score: row.max_score === '' ? '' : String(row.max_score),
                            output_value: String(row.output_value ?? ''),
                        })),
                    })),
            },
        };
        router.put(actions.save, payload, {
            preserveScroll: true,
            onError: (errors) => {
                saveError = Object.values(errors).join(' ');
                saving = false;
                allowNextVisit = false;
            },
            onSuccess: () => {
                saveError = '';
                saving = false;
                acknowledgedSave = true;
                instrumentDirty = false;
                tablesDirty = false;
                normDirty = false;
            },
            onFinish: () => {
                saving = false;
            },
        });
    }

    function createInstrumentSuccessor() {
        if (!actions.instrument_successor) return;
        router.post(actions.instrument_successor, {
            request_id: idempotencyKey(),
            expected_revision: instrument?.lock_version ?? instrument?.version_number ?? 1,
        }, { preserveScroll: true });
    }
</script>

<StaffLayout {breadcrumbs}>
    <div class="mx-auto min-w-0 max-w-7xl space-y-6 p-4 sm:p-6">
        <PageHeaderBar
            title={test_kit?.name ? `Test Setup: ${test_kit.name}` : `Test Setup: ${instrument?.profile?.display_name ?? 'Instrument'}`}
            description={isFinalOnly ? 'Manage named aptitude constructs with externally supplied percentile-rank finals.' : 'Manage subtest inventory, raw scoring rules, and percentile conversion tables.'}
            actions={headerActions}
        />

        {#snippet headerActions()}
            <Button variant="outline" href={kit_context?.back_url ?? actions.back ?? '/setup/test-instruments'} onclick={requestBack} class="gap-2">
                <ArrowLeft class="size-4" />
                {test_kit?.name ? 'Back to Kit' : 'Back to Instruments'}
            </Button>
            {#if (preparation || !test_kit) && !read_only && actions.save}
                <Button onclick={saveTestSetup} disabled={saving} class="gap-2">
                    <Save class="size-4" />
                    {saving ? 'Saving…' : 'Save Test Setup'}
                </Button>
            {/if}
        {/snippet}
        <nav aria-label="Test Setup sections" class="flex flex-wrap gap-2 rounded-xl bg-muted/50 p-1">
            {#if !isFinalOnly}<a href="#subtests" class="min-h-10 rounded-lg bg-background px-4 py-2 text-sm font-medium shadow-sm">Subtests ({subtests.length})</a>{/if}
            <a href="#aptitude-areas" class="min-h-10 rounded-lg px-4 py-2 text-sm font-medium transition-colors hover:bg-background/70">Aptitude Scoring ({metrics.length})</a>
        </nav>
        <section class="rounded-xl border border-border/70 bg-card p-4">
            <label class="flex flex-wrap items-center gap-3 text-sm font-medium">Scoring definition
                <select class="min-h-10 rounded-md border border-input bg-background px-3" value={definitionKind} onchange={(event) => setDefinitionKind(event.currentTarget.value)}>
                    <option value="raw">Calculate from raw inputs</option>
                    <option value="final_only">External aptitude final scores (PR)</option>
                </select>
            </label>
            {#if definitionSwitchNotice}<p class="mt-2 text-sm text-amber-700 dark:text-amber-300" role="status">{definitionSwitchNotice}</p>{/if}
        </section>

        {#if saveError}
            <p class="rounded-lg border border-destructive/40 bg-destructive/5 p-3 text-sm text-destructive" role="alert">{saveError}</p>
        {/if}

        <div class="flex flex-wrap items-center gap-2 rounded-xl border border-border/60 bg-card/60 p-3.5 text-sm shadow-xs backdrop-blur-sm" role="status">
            <span class="font-medium">Status:</span>
            <span class="rounded-full border border-amber-500/20 bg-amber-500/10 px-2.5 py-1 text-xs font-semibold tracking-wide text-amber-700 dark:text-amber-300">DRAFT PREPARATION</span>
            <span aria-hidden="true" class="text-muted-foreground">·</span>
            <span class="text-muted-foreground">Source: {instrument ? instrument.profile.display_name : 'New'}</span>
        </div>

        {#if !preparation && test_kit}
            <section class="rounded-xl border border-dashed p-6" aria-labelledby="sealed-setup-heading">
                <h2 id="sealed-setup-heading" class="font-semibold">Sealed kit setup</h2>
                <p class="mt-2 text-sm text-muted-foreground">This view shows the exact material context retained for this kit version. It does not open or create a draft.</p>
                {#if instrument}
                    <p class="mt-3 text-sm">Instrument: {instrument.profile.display_name} · version {instrument.version_number} · {instrument.status}</p>
                {:else}
                    <p class="mt-3 text-sm">No instrument snapshot is available in this sealed context.</p>
                {/if}
                {#if norm}
                    <p class="mt-1 text-sm">Norm source: {norm.package.display_name} · {norm.package.applicable_population || 'Population not specified'}</p>
                {:else}
                    <p class="mt-1 text-sm">No norm snapshot is available in this sealed context.</p>
                {/if}
                {#if actions.instrument_successor}
                    <Button class="mt-4" onclick={createInstrumentSuccessor}>Create instrument successor</Button>
                {/if}
                {#if actions.norm_successor}
                    <a class="mt-4 inline-flex min-h-10 items-center rounded-md border px-4 text-sm font-medium" href={actions.norm_successor}>Create norm successor</a>
                {/if}
            </section>
        {:else}
            {#if read_only}
                <section class="rounded-xl border border-amber-500/30 bg-amber-500/5 p-4" aria-live="polite">
                    <h2 class="font-semibold">Read-only source</h2>
                    <p class="mt-1 text-sm text-muted-foreground">An approved instrument or norm remains unchanged. You can still select an alternative source for this open preparation.</p>
                </section>
            {/if}
            <section aria-labelledby="norm-source-heading">
                <button type="button" class="text-sm font-medium hover:underline mb-2" onclick={() => (showMetadata = !showMetadata)}>Show Test Details &amp; Source</button>
                <details open={showMetadata} class="group rounded-xl border border-border/70 bg-card/60 p-4 shadow-xs backdrop-blur-sm transition-all">
                    <summary class="flex cursor-pointer list-none items-center justify-between select-none text-sm font-semibold [&::-webkit-details-marker]:hidden">
                        <span class="inline-flex items-center gap-1.5">
                            <span>Details</span>
                            <InfoPopover
                                title="Instrument & Norm source identity"
                                content="Defines the code, display name, target population, and source citation for the Test Setup and its paired Norm Package."
                            />
                        </span>
                        <span aria-hidden="true" class="size-2 rotate-45 border-b-2 border-r-2 border-muted-foreground/70 transition-transform group-open:-rotate-[135deg]"></span>
                    </summary>
                        <div class="mt-3 grid gap-4 md:grid-cols-2">
                            <label class="space-y-1.5 text-sm font-medium">Instrument code
                                <input id="instrument-code" aria-label="Instrument code" class="min-h-10 w-full rounded-md border border-input bg-background px-3" bind:value={instrumentProfile.code} oninput={() => (instrumentDirty = true)} />
                            </label>
                            <label class="space-y-1.5 text-sm font-medium">Display name
                                <input id="instrument-display-name" aria-label="Display name" class="min-h-10 w-full rounded-md border border-input bg-background px-3" bind:value={instrumentProfile.display_name} oninput={() => (instrumentDirty = true)} />
                            </label>
                            <label class="space-y-1.5 text-sm font-medium">Publisher / source owner
                                <input aria-label="Publisher / source owner" class="min-h-10 w-full rounded-md border border-input bg-background px-3" bind:value={instrumentProfile.publisher_name} oninput={() => (instrumentDirty = true)} />
                            </label>
                            <label class="space-y-1.5 text-sm font-medium">Form code
                                <input aria-label="Form code" class="min-h-10 w-full rounded-md border border-input bg-background px-3" bind:value={instrumentProfile.form_code} oninput={() => (instrumentDirty = true)} />
                            </label>
                            <label class="space-y-1.5 text-sm font-medium">Edition
                                <input aria-label="Edition" class="min-h-10 w-full rounded-md border border-input bg-background px-3" bind:value={instrumentProfile.edition} oninput={() => (instrumentDirty = true)} />
                            </label>
                            <label class="space-y-1.5 text-sm font-medium">Language
                                <input aria-label="Language" class="min-h-10 w-full rounded-md border border-input bg-background px-3" bind:value={instrumentProfile.language} oninput={() => (instrumentDirty = true)} />
                            </label>
                            <label class="space-y-1.5 text-sm font-medium md:col-span-2">Official source reference
                                <input aria-label="Official source reference" class="min-h-10 w-full rounded-md border border-input bg-background px-3" bind:value={instrumentProfile.source_reference} oninput={() => (instrumentDirty = true)} />
                            </label>
                            <label class="space-y-1.5 text-sm font-medium md:col-span-2">Permitted-use reference
                                <input aria-label="Permitted-use reference" class="min-h-10 w-full rounded-md border border-input bg-background px-3" bind:value={instrumentProfile.permitted_use_reference} oninput={() => (instrumentDirty = true)} />
                            </label>
                            <label class="space-y-1.5 text-sm font-medium md:col-span-2">Administration procedure reference
                                <input aria-label="Administration procedure reference" class="min-h-10 w-full rounded-md border border-input bg-background px-3" bind:value={instrumentProfile.administration_procedure_reference} oninput={() => (instrumentDirty = true)} />
                            </label>
                            {#if !isFinalOnly}
                            <label class="space-y-1.5 text-sm font-medium">Norm package code
                                <input id="norm-package-code" aria-label="Norm package code" class="min-h-10 w-full rounded-md border border-input bg-background px-3" bind:value={normPackage.code} oninput={() => (normDirty = true)} />
                            </label>
                            <label class="space-y-1.5 text-sm font-medium">Norm package name
                                <input id="norm-package-name" aria-label="Norm package name" class="min-h-10 w-full rounded-md border border-input bg-background px-3" bind:value={normPackage.display_name} oninput={() => (normDirty = true)} />
                            </label>
                            <label class="space-y-1.5 text-sm font-medium">Applicable population
                                <input id="applicable-population" aria-label="Applicable population" class="min-h-10 w-full rounded-md border border-input bg-background px-3" bind:value={normPackage.applicable_population} oninput={() => (normDirty = true)} />
                            </label>
                            <label class="space-y-1.5 text-sm font-medium">Norm source reference
                                <input id="norm-source-reference" aria-label="Norm source reference" class="min-h-10 w-full rounded-md border border-input bg-background px-3" bind:value={normPackage.source_reference} oninput={() => (normDirty = true)} />
                            </label>
                            {/if}
                        </div>
                </details>
            </section>

            <details class="group rounded-xl border border-border/70 bg-card/60 p-4 shadow-xs backdrop-blur-sm transition-all" aria-label="Preparation sources">
                <summary class="flex cursor-pointer list-none items-center justify-between select-none text-sm font-semibold [&::-webkit-details-marker]:hidden">
                    Source · {instrument ? instrument.profile.display_name : 'No instrument selected'} · {norm ? norm.package.display_name : 'No norm selected'}
                    <span aria-hidden="true" class="size-2 rotate-45 border-b-2 border-r-2 border-muted-foreground/70 transition-transform group-open:-rotate-[135deg]"></span>
                </summary>
                <div class="mt-3 grid gap-4 md:grid-cols-2">
                    <div class="space-y-1.5 text-sm font-medium">
                        <div class="flex items-center gap-1.5">
                            <label for="setup-instrument-source">Test instrument source</label>
                            <InfoPopover
                                title="Reuse or create Test Setup"
                                content="Select an existing approved Test Setup from the institutional catalog or start a new editable draft."
                            />
                        </div>
                        <select id="setup-instrument-source" aria-label="Test instrument source" class="min-h-10 w-full rounded-md border border-input bg-background px-3" bind:value={selectedInstrument} onchange={(event) => selectSource('instrument', event.currentTarget.value)} disabled={!actions.save_preparation}>
                            <option value="">Current selection: {instrument ? `${instrument.profile.display_name} · v${instrument.version_number}` : 'No source selected'}</option>
                            <option value="new">Create a new instrument draft</option>
                            {#each instrument_candidates as candidate (candidate.version_id)}
                                <option value={String(candidate.version_id)}>{candidate.display_name} · {candidate.code} · v{candidate.version_number} · {candidate.status}</option>
                            {/each}
                        </select>
                    </div>
                    {#if !isFinalOnly}
                    <div class="space-y-1.5 text-sm font-medium">
                        <div class="flex items-center gap-1.5">
                            <label for="setup-norm-source">Norm source</label>
                            <InfoPopover
                                title="Percentile conversion package"
                                content="Select the approved Norm Package whose raw-to-percentile tables convert examinee raw scores into 0–100 percentile ranks."
                            />
                        </div>
                        <select id="setup-norm-source" aria-label="Norm source" class="min-h-10 w-full rounded-md border border-input bg-background px-3" bind:value={selectedNorm} onchange={(event) => selectSource('norm', event.currentTarget.value)} disabled={!actions.save_preparation}>
                            <option value="">Current selection: {norm ? `${norm.package.display_name} · v${norm.version_number}` : 'No source selected'}</option>
                            <option value="new">Create a new norm draft</option>
                            {#each norm_candidates as candidate (candidate.version_id)}
                                <option value={String(candidate.version_id)}>{candidate.display_name} · {candidate.code} · v{candidate.version_number} · {candidate.status}</option>
                            {/each}
                        </select>
                    </div>
                    {/if}
                </div>
            </details>

            <div class="grid min-w-0 gap-6">
                {#if !isFinalOnly}<SubtestsTableCard bind:subtests bind:metrics readOnly={read_only} onDirty={() => (instrumentDirty = true)} />{/if}
                <AptitudeAreasTableCard bind:metrics bind:tables {subtests} {constructs} finalOnlyMode={isFinalOnly} readOnly={read_only} onDirty={() => (tablesDirty = true)} />
            </div>
        {/if}

        <Dialog.Root open={discardOpen} onOpenChange={(open) => (discardOpen = open)}>
            <Dialog.Content>
                <Dialog.Header>
                    <Dialog.Title>Discard unsaved Test Setup changes?</Dialog.Title>
                    <Dialog.Description>Your instrument, formula, table, and norm-source edits have not been saved.</Dialog.Description>
                </Dialog.Header>
                <Dialog.Footer>
                    <button type="button" class="min-h-10 rounded-md border px-4" onclick={keepEditing}>Keep editing</button>
                    <button type="button" class="min-h-10 rounded-md bg-destructive px-4 text-destructive-foreground" onclick={discardAndReturn}>Discard changes</button>
                </Dialog.Footer>
            </Dialog.Content>
        </Dialog.Root>
    </div>
</StaffLayout>
