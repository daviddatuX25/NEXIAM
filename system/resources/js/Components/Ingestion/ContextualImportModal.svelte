<script>
    import { onDestroy, tick, untrack } from 'svelte';
    import * as Dialog from '@/Components/ui/dialog';
    import { Button } from '@/Components/ui/button';
    import { Badge } from '@/Components/ui/badge';
    import { FileUpload } from '@/Components/ui/file-upload';
    import { sound } from '@/lib/sound.js';
    import { router } from '@inertiajs/svelte';
    import ImportTemplateBuilder from '@/Components/Ingestion/ImportTemplateBuilder.svelte';
    import ImportWalkthrough from '@/Components/Ingestion/ImportWalkthrough.svelte';
    import {
        Upload,
        CheckCircle2,
        XCircle,
        AlertTriangle,
        FileSpreadsheet,
        Loader2,
        Users,
        BrainCircuit,
        Info,
    } from 'lucide-svelte';

    let {
        open = $bindable(false),
        importType = 'applicants_only', // 'applicants_only' | 'scores_only'
        academicCycle = null,
        scoreImportTargets = [],
        kitVersions = [],
        normVersions = [],
    } = $props();

    let selectedFile = $state([]);
    let analyzing = $state(false);
    let analysis = $state(null);
    let analyzeError = $state('');
    let submitting = $state(false);
    let submitError = $state('');
    let targetExamSessionId = $state('');
    let testKitVersionId = $state('');
    let normPackageVersionId = $state('');
    let templateBuilderOpen = $state(false);
    let returningFromTemplate = false;
    let restoreTemplateFocus = false;
    let builderOpenedContext = null;
    let builderReturnTimer = null;
    let analysisGeneration = 0;
    let analysisController = null;
    const contextualModes = $derived([importType]);


    const isApplicants = $derived(importType === 'applicants_only');

    const title = $derived(
        isApplicants ? 'Import Applicants' : 'Import Examinee Aptitude Scores',
    );
    const description = $derived(
        isApplicants
            ? 'Upload applicant spreadsheets, check the columns, then review saved rows before adding applicant records to the active academic cycle.'
            : 'Upload examinee scores, check the accepted score columns, then review saved rows before adding scores.',
    );
    const walkthroughContext = $derived({
        entityKey: `contextual-import-${importType}-${academicCycle?.id ?? 'none'}`,
        safetyKey: analysis?.schema_revision ?? (analyzing ? 'analysis-running' : analyzeError ? 'analysis-error' : 'current'),
        freshness: analyzing ? 'loading' : analyzeError ? 'error' : 'current',
        activePreset: importType,
        hasAcademicCycle: Boolean(academicCycle?.id),
        academicCycleLabel: academicCycle?.label ?? academicCycle?.staff_label ?? null,
        hasSelectedFile: Boolean(Array.isArray(selectedFile) ? selectedFile[0] : selectedFile),
        isAnalyzing: analyzing,
        hasAnalysisResult: Boolean(analysis),
        detectedSheetsCount: analysis?.sheets?.length ?? 0,
        recentBatchesCount: 0,
        pendingReviewCount: 0,
        builderOpen: false,
        hasTemplateSchema: false,
        hasFieldDetails: false,
        hasUploader: true,
        hasTemplateEntry: true,
    });


    $effect(() => {
        const file = Array.isArray(selectedFile)
            ? selectedFile[0]
            : selectedFile;
        if (file && (file.name || file.size)) {
            void untrack(() => runAnalysis(file));
        } else {
            invalidateAnalysis();
            analysis = null;
            analyzeError = '';
        }
    });

    function invalidateAnalysis() {
        analysisGeneration += 1;
        analysisController?.abort();
        analysisController = null;
        analyzing = false;
    }

    async function runAnalysis(file) {
        analysisController?.abort();
        const generation = ++analysisGeneration;
        const controller = new AbortController();
        analysisController = controller;
        if (!isApplicants && (!testKitVersionId || !targetExamSessionId)) {
            analysis = null;
            analyzing = false;
            analysisController = null;
            analyzeError =
                'Select an exact locked Test Kit version and completed target session before analyzing score columns.';
            return;
        }

        analyzing = true;
        analyzeError = '';
        analysis = null;

        try {
            const formData = new FormData();
            formData.append('file', file);
            formData.append('import_type', importType);
            if (academicCycle?.id) {
                formData.append('academic_cycle_id', academicCycle.id);
            }
            if (!isApplicants) {
                formData.append('test_kit_version_id', testKitVersionId);
                formData.append('target_exam_session_id', targetExamSessionId);
            }
            const xsrfToken = decodeURIComponent(
                document.cookie
                    .split('; ')
                    .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
                    ?.split('=')[1] || '',
            );

            const response = await fetch('/imports/analyze', {
                method: 'POST',
                body: formData,
                signal: controller.signal,
                headers: {
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': xsrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const data = await response.json().catch(() => ({}));
            if (generation !== analysisGeneration) return;

            if (!response.ok) {
                analyzeError =
                    data.error ||
                    data.message ||
                    'The spreadsheet format could not be verified.';
                return;
            }

            analysis = data;
            sound.tick();
        } catch (error) {
            if (error?.name !== 'AbortError' && generation === analysisGeneration) {
                analyzeError = 'Network error while analyzing spreadsheet.';
            }
        } finally {
            if (generation === analysisGeneration) {
                analyzing = false;
                analysisController = null;
            }
        }
    }
    function reanalyzeSelectedFile() {
        const file = Array.isArray(selectedFile)
            ? selectedFile[0]
            : selectedFile;
        if (!file || (!isApplicants && (!testKitVersionId || !targetExamSessionId))) {
            return;
        }
        void runAnalysis(file);
    }

    const isReadyToImport = $derived(
        analysis &&
            analysis.total_rows > 0 &&
            (isApplicants
                ? (analysis.missing_required?.length ?? 0) === 0
                : Boolean(targetExamSessionId && testKitVersionId)) &&
            !analyzeError &&
            !submitting,
    );

    function handleSubmit() {
        const file = Array.isArray(selectedFile)
            ? selectedFile[0]
            : selectedFile;
        if (!file || !isReadyToImport) return;

        submitting = true;
        submitError = '';
        const formData = new FormData();
        formData.append('file', file);
        formData.append('import_type', importType);
        if (academicCycle?.id) {
            formData.append('academic_cycle_id', academicCycle.id);
        }
        if (!isApplicants) {
            formData.append('target_exam_session_id', targetExamSessionId);
            formData.append('test_kit_version_id', testKitVersionId);
            if (normPackageVersionId) {
                formData.append('norm_package_version_id', normPackageVersionId);
            }
        }

        router.post('/imports/stage', formData, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                sound.success();
                invalidateAnalysis();
                open = false;
                selectedFile = [];
                analysis = null;
                targetExamSessionId = '';
                testKitVersionId = '';
                normPackageVersionId = '';
            },
            onError: (errors) => {
                sound.error();
                submitError =
                    Object.values(errors).flat().join(' ') ||
                    'Import failed. Check file rows and format.';
            },
            onFinish: () => {
                submitting = false;
            },
        });
    }

    function handleClose() {
        if (!submitting) {
            invalidateAnalysis();
            open = false;
            selectedFile = [];
            analysis = null;
            analyzeError = '';
            submitError = '';
            targetExamSessionId = '';
            testKitVersionId = '';
            normPackageVersionId = '';
        }
    }
    async function openTemplateBuilder() {
        if (submitting) return;
        returningFromTemplate = true;
        builderOpenedContext = {
            kit: String(testKitVersionId ?? ''),
            target: String(targetExamSessionId ?? ''),
        };
        open = false;
        await tick();
        templateBuilderOpen = true;
    }

    function closeTemplateBuilder() {
        if (!returningFromTemplate) return;
        const isApplicantImport = isApplicants;
        if (builderReturnTimer) window.clearTimeout(builderReturnTimer);
        builderReturnTimer = window.setTimeout(async () => {
            builderReturnTimer = null;
            const previousContext = builderOpenedContext;
            restoreTemplateFocus = true;
            open = true;
            await tick();
            returningFromTemplate = false;
            builderOpenedContext = null;
            document
                .querySelector('[data-context-template-trigger]')
                ?.focus();

            const contextChanged =
                previousContext &&
                (previousContext.kit !== String(testKitVersionId ?? '') ||
                    previousContext.target !== String(targetExamSessionId ?? ''));
            const file = Array.isArray(selectedFile)
                ? selectedFile[0]
                : selectedFile;
            if (!isApplicantImport && contextChanged && file) {
                void runAnalysis(file);
            }
        }, 180);
    }

    onDestroy(() => {
        invalidateAnalysis();
        if (builderReturnTimer) window.clearTimeout(builderReturnTimer);
    });
</script>

<Dialog.Root
    bind:open
    onOpenChange={(value) => {
        if (!value && !returningFromTemplate) handleClose();
    }}
>
    <Dialog.Content
        onOpenAutoFocus={(event) => {
            if (restoreTemplateFocus) {
                event.preventDefault();
                restoreTemplateFocus = false;
                document.querySelector('[data-context-template-trigger]')?.focus();
            }
        }}
        class="border-border bg-card flex max-h-[90vh] w-[94vw] max-w-4xl flex-col rounded-2xl border p-6 shadow-2xl sm:max-w-4xl sm:p-8"
    >
        <!-- Fixed Header -->
        <Dialog.Header
            class="border-border/60 shrink-0 space-y-1.5 border-b pb-4 text-left"
        >
            <div class="flex items-center gap-3">
                <div
                    class="bg-primary/10 text-primary flex size-10 shrink-0 items-center justify-center rounded-xl"
                >
                    {#if isApplicants}
                        <Users class="size-5" />
                    {:else}
                        <BrainCircuit class="size-5" />
                    {/if}
                </div>
                <div class="min-w-0 flex-1">
                    <Dialog.Title
                        class="text-foreground text-xl font-bold tracking-tight"
                    >
                        {title}
                    </Dialog.Title>
                    <Dialog.Description
                        class="text-muted-foreground mt-0.5 text-xs"
                    >
                        {description}
                    </Dialog.Description>
                </div>
                {#if academicCycle?.label}
                    <Badge
                        variant="outline"
                        class="shrink-0 font-mono text-xs font-normal"
                    >
                        {academicCycle.label}
                    </Badge>
                {/if}
            </div>
            <div class="flex flex-wrap items-center justify-end gap-2 pt-3">
                <ImportWalkthrough context={walkthroughContext} />
                <Button
                    type="button"
                    variant="outline"
                    class="gap-2"
                    onclick={openTemplateBuilder}
                    disabled={submitting}
                    data-tour="import-template-entry"
                    data-context-template-trigger
                >
                    <FileSpreadsheet class="size-4" aria-hidden="true" />
                    Build import template
                </Button>
            </div>
        </Dialog.Header>

        <!-- Scrollable Body -->
        <div
            class="grid flex-1 grid-cols-1 gap-6 overflow-y-auto py-4 pr-1 lg:grid-cols-12"
        >
            <!-- Left Column: File Upload & Analysis Status (7 cols) -->
            <div class="space-y-4 lg:col-span-7">
                {#if !isApplicants}
                    <div class="border-border/70 bg-background grid gap-3 rounded-xl border p-4 sm:grid-cols-2">
                        <label class="text-foreground space-y-1.5 text-xs font-semibold">
                            <span>Completed target session</span>
                            <select
                                bind:value={targetExamSessionId}
                                onchange={reanalyzeSelectedFile}
                                required
                                class="border-input bg-background min-h-10 w-full rounded-md border px-3 py-2 text-sm"
                            >
                                <option value="" disabled>Select a completed session</option>
                                {#each scoreImportTargets as target (target.id)}
                                    <option value={String(target.id)}>
                                        {target.label} · {target.kit_label}
                                    </option>
                                {/each}
                            </select>
                        </label>
                        <label class="text-foreground space-y-1.5 text-xs font-semibold">
                            <span>Exact locked Test Kit version</span>
                            <select
                                bind:value={testKitVersionId}
                                onchange={reanalyzeSelectedFile}
                                required
                                class="border-input bg-background min-h-10 w-full rounded-md border px-3 py-2 text-sm"
                            >
                                <option value="" disabled>Select a Test Kit version</option>
                                {#each kitVersions as kit (kit.id)}
                                    <option value={String(kit.id)}>
                                        {kit.display_name} ({kit.kit_code} v{kit.version_number})
                                    </option>
                                {/each}
                            </select>
                        </label>
                        <label class="text-foreground space-y-1.5 text-xs font-semibold sm:col-span-2">
                            <span>Norm package version <span class="text-muted-foreground font-normal">(optional; raw scores remain awaiting conversion when unset)</span></span>
                            <select
                                bind:value={normPackageVersionId}
                                class="border-input bg-background min-h-10 w-full rounded-md border px-3 py-2 text-sm"
                            >
                                <option value="">No norm selected</option>
                                {#each normVersions as norm (norm.id)}
                                    <option value={String(norm.id)}>{norm.label}</option>
                                {/each}
                            </select>
                        </label>
                    </div>
                {/if}
                <div data-tour="data-ingestion-uploader">
                    <FileUpload
                        label="Select spreadsheet file"
                        sublabel="CSV, XLSX, XLS, or TXT up to 25 MB"
                        accept=".csv,.xlsx,.xls,.txt"
                        maxSize="25MB"
                        bind:files={selectedFile}
                    />
                </div>

                {#if analyzing}
                    <div
                        class="border-border/70 bg-muted/40 text-muted-foreground flex items-center gap-2.5 rounded-xl border p-4 text-xs"
                    >
                        <Loader2
                            class="text-primary size-4 shrink-0 animate-spin"
                        />
                        <span class="font-medium"
                            >Analyzing headers, delimiters, and worksheet
                            structure...</span
                        >
                    </div>
                {/if}

                {#if analyzeError}
                    <div
                        class="border-destructive/30 bg-destructive/10 text-destructive flex items-start gap-3 rounded-xl border p-4 text-xs"
                    >
                        <AlertTriangle class="mt-0.5 size-4 shrink-0" />
                        <div>
                            <p class="font-bold">
                                Spreadsheet Validation Issue
                            </p>
                            <p class="mt-0.5 leading-relaxed">{analyzeError}</p>
                        </div>
                    </div>
                {/if}

                {#if submitError}
                    <div
                        class="border-destructive/30 bg-destructive/10 text-destructive flex items-start gap-3 rounded-xl border p-4 text-xs"
                    >
                        <XCircle class="mt-0.5 size-4 shrink-0" />
                        <div>
                            <p class="font-bold">Import Could Not Complete</p>
                            <p class="mt-0.5 leading-relaxed">{submitError}</p>
                        </div>
                    </div>
                {/if}

                {#if analysis && !analyzing}
                    <div
                        class="border-border/80 bg-background space-y-3 rounded-xl border p-4 shadow-xs"
                        data-tour="data-ingestion-preview"
                    >
                        <div
                            class="border-border/50 flex items-center justify-between border-b pb-2.5"
                        >
                            <span
                                class="text-foreground flex items-center gap-2 text-xs font-bold"
                            >
                                <FileSpreadsheet class="text-primary size-4" />
                                {analysis.file_name}
                            </span>
                            <div class="flex items-center gap-2">
                                <Badge
                                    variant="secondary"
                                    class="font-mono text-[11px]"
                                >
                                    {analysis.total_rows} row{analysis.total_rows ===
                                    1
                                        ? ''
                                        : 's'}
                                </Badge>
                                {#if analysis.sheets && analysis.sheets.length > 1}
                                    <Badge
                                        variant="outline"
                                        class="font-mono text-[10px]"
                                    >
                                        {analysis.sheets.length} sheets
                                    </Badge>
                                {/if}
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-2 text-xs">
                            {#each analysis.checks ?? [] as check (check.id ?? check.label)}
                                <div
                                    class="bg-muted/30 flex items-center gap-2 rounded-lg px-3 py-2"
                                >
                                    {#if check.status === 'pass'}
                                        <CheckCircle2
                                            class="size-4 shrink-0 text-emerald-600 dark:text-emerald-400"
                                        />
                                    {:else}
                                        <XCircle
                                            class="text-destructive size-4 shrink-0"
                                        />
                                    {/if}
                                    <div class="min-w-0 flex-1">
                                        <p
                                            class="text-foreground truncate text-[11px] font-semibold"
                                        >
                                            {check.label}
                                        </p>
                                        <p
                                            class="text-muted-foreground truncate text-[10px]"
                                        >
                                            {check.detail}
                                        </p>
                                    </div>
                                </div>
                            {/each}
                        </div>

                        {#if analysis.missing_required && analysis.missing_required.length > 0}
                            <div
                                class="rounded-lg border border-amber-500/30 bg-amber-500/10 p-3 text-xs text-amber-800 dark:text-amber-300"
                            >
                                <p class="font-bold">
                                    Missing required columns in spreadsheet:
                                </p>
                                <p class="mt-0.5 font-mono text-[11px]">
                                    {analysis.missing_required.join(', ')}
                                </p>
                            </div>
                        {/if}

                        {#if analysis.column_analysis && analysis.column_analysis.length > 0}
                            <div class="space-y-1 pt-1">
                                <p
                                    class="text-muted-foreground text-[11px] font-bold tracking-wider uppercase"
                                >
                                    Detected Column Mapping
                                </p>
                                <div
                                    class="border-border/60 bg-muted/20 divide-border/40 max-h-32 divide-y overflow-y-auto rounded-lg border p-2 text-[11px]"
                                >
                                    {#each analysis.column_analysis as col, index (`${col.raw}-${index}`)}
                                        <div
                                            class="flex items-center justify-between px-1 py-1"
                                        >
                                            <span
                                                class="text-foreground max-w-[140px] truncate font-mono"
                                                >{col.raw}</span
                                            >
                                            <span
                                                class="text-muted-foreground text-[10px]"
                                                >→</span
                                            >
                                            {#if col.status === 'aptitude'}
                                                <span
                                                    class="text-primary font-mono text-[10px] font-semibold"
                                                    >{col.matched_to}</span
                                                >
                                            {:else if col.status === 'required'}
                                                <span
                                                    class="font-mono text-[10px] font-semibold text-emerald-700 dark:text-emerald-400"
                                                    >{col.matched_to}</span
                                                >
                                            {:else if col.status === 'optional' || col.status === 'fuzzy'}
                                                <span
                                                    class="text-muted-foreground font-mono text-[10px]"
                                                    >{col.matched_to}</span
                                                >
                                            {:else}
                                                <span
                                                    class="text-muted-foreground/60 text-[10px]"
                                                    >ignored</span
                                                >
                                            {/if}
                                        </div>
                                    {/each}
                                </div>
                            </div>
                        {/if}
                    </div>
                {/if}
            </div>

            <!-- The builder is the authoritative, current schema reference. -->
            <div class="space-y-4 lg:col-span-5">
                <section class="border-border/80 bg-muted/30 space-y-3 rounded-xl border p-4">
                    <div class="text-foreground flex items-center gap-1.5 text-xs font-bold">
                        {#if isApplicants}
                            <Users class="text-primary size-3.5" aria-hidden="true" />
                            <span>Accepted applicant fields</span>
                        {:else}
                            <BrainCircuit class="text-primary size-4" aria-hidden="true" />
                            <span>Accepted score inputs</span>
                        {/if}
                    </div>
                    {#if isApplicants}
                        <p class="text-muted-foreground text-sm leading-relaxed">
                            Requiredness and available columns come from the current academic cycle’s intake policy. Disabled or retired fields are not offered.
                        </p>
                    {:else}
                        <p class="text-muted-foreground text-sm leading-relaxed">
                            Accepted score inputs come from the exact locked Test Kit version selected for this import. Computed outputs are not offered as writable columns.
                        </p>
                    {/if}
                    <div class="bg-muted/40 text-muted-foreground flex items-start gap-2 rounded-lg p-3 text-xs">
                        <Info class="text-primary mt-0.5 size-4 shrink-0" aria-hidden="true" />
                        <p>
                            Build an import template to inspect the live headers, required reasons, accepted formats, examples, aliases, and exact export order. This reference does not upload or stage rows.
                        </p>
                    </div>
                </section>
            </div>
        </div>

        <!-- Fixed Footer -->
        <Dialog.Footer
            class="border-border/60 flex shrink-0 items-center justify-between border-t pt-4 sm:justify-between"
        >
            <Button variant="ghost" onclick={handleClose} disabled={submitting}>
                Cancel
            </Button>

            <Button
                variant="default"
                onclick={handleSubmit}
                disabled={!isReadyToImport || submitting}
                size="lg"
                class="gap-2 px-6 font-bold"
            >
                {#if submitting}
                    <Loader2 class="size-4 animate-spin" />
                    <span>Saving rows for review…</span>
                {:else}
                    <Upload class="size-4" />
                    <span>{analysis ? `Review ${analysis.total_rows} rows` : 'Review rows'}</span>
                {/if}
            </Button>
        </Dialog.Footer>
    </Dialog.Content>
</Dialog.Root>
<ImportTemplateBuilder
    bind:open={templateBuilderOpen}
    mode={importType}
    allowedModes={contextualModes}
    lockMode={true}
    academicCycle={academicCycle}
    testKitVersions={kitVersions}
    targetExamSessions={scoreImportTargets}
    bind:testKitVersionId
    bind:targetExamSessionId
    onClose={closeTemplateBuilder}
/>
