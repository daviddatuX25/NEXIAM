<script>
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import { untrack } from 'svelte';
    import { Link, router, usePage } from '@inertiajs/svelte';
    import { FileUpload } from '@/Components/ui/file-upload';
    import { Button } from '@/Components/ui/button';
    import ImportTemplateBuilder from '@/Components/Ingestion/ImportTemplateBuilder.svelte';
    import ImportWalkthrough from '@/Components/Ingestion/ImportWalkthrough.svelte';
    import {
        Upload,
        FileSpreadsheet,
        Clock3,
        History,
        ChevronDown,
        RefreshCw,
        AlertCircle,
        CheckCircle2,
        Sparkles,
    } from 'lucide-svelte';

    let {
        preset = null,
        academic_cycle = null,
        active_sessions = [],
        test_kit_versions = [],
        norm_versions = [],
        recent_batches = [],
        pending_batches = null,
        pending_approval_count = 0,
        awaiting_registrar_count = 0,
        awaiting_registrar_batches = null,
        capabilities = {},
        office_view_mode = null,
        view = null,
        specialized_imports = [],
    } = $props();
    const page = usePage();

    const operationCapabilities = {
        applicants_only: 'registrar.applications.import',
        scores_only: 'guidance.scoring.import',
        all_in_one: 'guidance.scoring.import',
        multi_session: 'guidance.exam.schedule',
    };
    const modeNames = {
        applicants_only: 'Applicant intake',
        scores_only: 'Exam scores',
        all_in_one: 'Combined roster (advanced)',
        multi_session: 'Exam sessions',
    };
    const modeDescriptions = {
        applicants_only:
            'Add applicant and admission details for the current academic cycle.',
        scores_only:
            'Import scores for applicants who already have an exam record.',
        all_in_one:
            'For Guidance staff importing applicant and assessment data together.',
        multi_session:
            'Import an exam roster arranged across separate spreadsheet tabs and assign each tab to an exam session.',
    };

    let selectedFile = $state(null);
    let analyzing = $state(false);
    let staging = $state(false);
    let analysis = $state(null);
    let analyzeError = $state('');
    let stageError = $state('');
    let manualMode = $state('');
    let templateBuilderOpen = $state(false);
    let templateMode = $state('applicants_only');
    const tab = $derived.by(() => {
        const explicitView = ['upload', 'pending', 'history'].includes(view)
            ? view
            : null;
        const queryView = new URL(
            page.url,
            'http://localhost',
        ).searchParams.get('view');
        if (explicitView) return explicitView;
        if (['upload', 'pending', 'history'].includes(queryView))
            return queryView;
        return office_view_mode === 'registrar' &&
            Number(pending_approval_count) > 0
            ? 'pending'
            : 'upload';
    });
    let enabledSheets = $state({});
    let sheetSessionMap = $state({});
    let selectedExamSessionId = $state('');
    let selectedKitVersionId = $state('');
    let selectedNormVersionId = $state('');
    let analysisGeneration = 0;
    let analysisController = null;
    let analysisFile = null;
    let resolvedAnalysisContextKey = $state('');
    let lastAttemptedAnalysisContextKey = $state('');
    let rejectionBatch = $state(null);
    let rejectionReason = $state('');

    function can(mode) {
        const capability = operationCapabilities[mode];
        if (!capability) return false;
        if (Array.isArray(capabilities))
            return capabilities.includes(capability);
        const capabilityByMode = {
            applicants_only: 'can_import_applications',
            scores_only: 'can_import_scores',
            all_in_one: 'can_all_in_one',
            multi_session: 'can_import_exam_sessions',
        };
        return Boolean(
            capabilities?.[capabilityByMode[mode]] ??
            capabilities?.[capability] ??
            capabilities?.[mode],
        );
    }

    const allowedModes = $derived(
        [
            'applicants_only',
            'scores_only',
            'all_in_one',
            'multi_session',
        ].filter(can),
    );
    const templateModes = $derived(
        ['applicants_only', 'scores_only', 'all_in_one'].filter((mode) =>
            allowedModes.includes(mode),
        ),
    );
    const suggestedMode = $derived(analysis?.suggested_import_type ?? null);
    const suggestedAllowed = $derived(
        Boolean(suggestedMode && allowedModes.includes(suggestedMode)),
    );
    const selectedMode = $derived(
        manualMode || (suggestedAllowed ? suggestedMode : ''),
    );
    const templateType = $derived(
        ['applicants_only', 'scores_only', 'all_in_one'].includes(selectedMode)
            ? selectedMode
            : can('applicants_only')
              ? 'applicants_only'
              : can('scores_only')
                ? 'scores_only'
                : can('all_in_one')
                  ? 'all_in_one'
                  : null,
    );
    const walkthroughContext = $derived({
        entityKey: `imports-${tab}-${academic_cycle?.id ?? 'none'}`,
        safetyKey: analysis?.schema_revision ?? `${selectedMode || templateType || 'none'}-${analyzing ? 'analysis' : 'idle'}`,
        freshness: analyzing ? 'loading' : analyzeError ? 'error' : 'current',
        activePreset: selectedMode || templateType || 'applicants_only',
        hasAcademicCycle: Boolean(academic_cycle?.id),
        academicCycleLabel: academic_cycle?.label ?? academic_cycle?.name ?? null,
        hasSelectedFile: Boolean(Array.isArray(selectedFile) ? selectedFile[0] : selectedFile),
        isAnalyzing: analyzing,
        hasAnalysisResult: Boolean(analysis),
        detectedSheetsCount: analysis?.sheets?.length ?? 0,
        recentBatchesCount: recent_batches?.length ?? 0,
        pendingReviewCount: Number(pending_approval_count) + Number(awaiting_registrar_count),
        builderOpen: false,
        hasTemplateSchema: false,
        hasFieldDetails: false,
        hasUploader: tab === 'upload',
        hasTemplateEntry: tab === 'upload' && Boolean(templateType),
    });
    function openTemplateBuilder() {
        templateMode = templateModes.includes(selectedMode)
            ? selectedMode
            : templateType ?? templateModes[0] ?? 'applicants_only';
        templateBuilderOpen = true;
    }
    const completedSessions = $derived(active_sessions.filter((session) => session.status === 'completed'));
    const selectedScoreSession = $derived(
        completedSessions.find((session) => String(session.id) === String(selectedExamSessionId)) ?? null,
    );
    const selectableKitVersions = $derived(
        selectedMode === 'scores_only' && selectedScoreSession?.test_kit_version_id
            ? test_kit_versions.filter((kit) => Number(kit.id) === Number(selectedScoreSession.test_kit_version_id))
            : test_kit_versions.filter((kit) => kit.status === 'active'),
    );
    const selectedKitIsAvailable = $derived(
        selectableKitVersions.some(
            (kit) => String(kit.id) === String(selectedKitVersionId),
        ),
    );
    const selectedKit = $derived(
        test_kit_versions.find((kit) => String(kit.id) === String(selectedKitVersionId)) ?? null,
    );
    const selectedKitIsFinalOnly = $derived(selectedKit?.is_final_only === true);
    $effect(() => {
        if (selectedKitIsFinalOnly && selectedNormVersionId) {
            selectedNormVersionId = '';
        }
    });

    const analysisContextReady = $derived(
        Boolean(academic_cycle?.id) &&
            (selectedMode === 'applicants_only' ||
                selectedMode === 'multi_session' ||
                (selectedMode === 'all_in_one' && selectedKitIsAvailable) ||
                (selectedMode === 'scores_only' &&
                    selectedKitIsAvailable &&
                    Boolean(selectedExamSessionId))),
    );
    const analysisContextKey = $derived(
        [
            academic_cycle?.id ?? '',
            analysisContextReady ? selectedMode : 'unconfigured',
            analysisContextReady &&
            ['scores_only', 'all_in_one'].includes(selectedMode)
                ? String(selectedKitVersionId)
                : '',
            analysisContextReady && selectedMode === 'scores_only'
                ? String(selectedExamSessionId)
                : '',
        ].join('|'),
    );
    const analysisMatchesContext = $derived(
        Boolean(
            analysis &&
                analysisContextReady &&
                resolvedAnalysisContextKey === analysisContextKey,
        ),
    );
    const needsExplicitKit = $derived(['scores_only', 'all_in_one'].includes(selectedMode));
    const missingKitSelection = $derived(needsExplicitKit && !selectedKitVersionId);
    const missingScoreSession = $derived(selectedMode === 'scores_only' && !selectedExamSessionId);
    const missingSessionAssignment = $derived(
        selectedMode === 'multi_session' &&
            Object.keys(enabledSheets).some(
                (name) => enabledSheets[name] && !sheetSessionMap[name],
            ),
    );
    const noEnabledSheets = $derived(
        selectedMode === 'multi_session' &&
            !Object.keys(enabledSheets).some((name) => enabledSheets[name]),
    );
    const canStage = $derived(
        Boolean(
            selectedFile &&
                academic_cycle?.id &&
                selectedMode &&
                analysisMatchesContext &&
                !analyzing &&
                !analyzeError &&
                !staging &&
                !missingSessionAssignment &&
                !missingScoreSession &&
                !missingKitSelection &&
                !noEnabledSheets,
        ),
    );
    const registrarApprovalInbox = $derived(office_view_mode === 'registrar');
    const pendingQueue = $derived(
        registrarApprovalInbox ? pending_batches : awaiting_registrar_batches,
    );
    const pendingRows = $derived(
        Array.isArray(pendingQueue) ? pendingQueue : (pendingQueue?.data ?? []),
    );
    const pendingPageLinks = $derived(
        Array.isArray(pendingQueue?.links) ? pendingQueue.links : [],
    );
    const pendingCount = $derived(
        Number(
            registrarApprovalInbox
                ? pending_approval_count
                : awaiting_registrar_count,
        ) || 0,
    );

    $effect(() => {
        if (
            preset &&
            preset !== 'all_in_one' &&
            allowedModes.includes(preset) &&
            !analysis
        )
            manualMode = preset;
    });

    $effect(() => {
        const file = Array.isArray(selectedFile)
            ? selectedFile[0]
            : selectedFile;
        if (!file || !(file.name || file.size)) {
            analysisFile = null;
            invalidateAnalysis();
            analysis = null;
            analyzeError = '';
            resolvedAnalysisContextKey = '';
            lastAttemptedAnalysisContextKey = '';
            manualMode =
                preset &&
                preset !== 'all_in_one' &&
                allowedModes.includes(preset)
                    ? preset
                    : '';
            return;
        }

        if (analysisFile !== file) {
            invalidateAnalysis();
            analysisFile = file;
            analysis = null;
            analyzeError = '';
            resolvedAnalysisContextKey = '';
            lastAttemptedAnalysisContextKey = '';
        }

        const contextKey = analysisContextKey;
        if (
            lastAttemptedAnalysisContextKey &&
            lastAttemptedAnalysisContextKey !== contextKey
        ) {
            if (analyzing) invalidateAnalysis();
            analyzeError = '';
        }
        if (analyzing || analyzeError) return;
        if (
            !analysis ||
            (analysisContextReady && resolvedAnalysisContextKey !== contextKey)
        ) {
            void untrack(() => runAnalysis(file));
        }
    });

    function invalidateAnalysis() {
        analysisGeneration += 1;
        analysisController?.abort();
        analysisController = null;
        analyzing = false;
    }

    async function runAnalysis(fileData) {
        analysisController?.abort();
        const generation = ++analysisGeneration;
        const controller = new AbortController();
        analysisController = controller;
        const actualFile = Array.isArray(fileData) ? fileData[0] : fileData;
        if (!actualFile) {
            analyzing = false;
            analysisController = null;
            return;
        }

        const requestContextKey = analysisContextKey;
        const requestMode = analysisContextReady ? selectedMode : '';
        const kitVersionId = String(selectedKitVersionId ?? '');
        const examSessionId = String(selectedExamSessionId ?? '');
        lastAttemptedAnalysisContextKey = requestContextKey;
        analyzing = true;
        analyzeError = '';

        try {
            const body = new FormData();
            body.append('file', actualFile);
            if (academic_cycle?.id) body.append('academic_cycle_id', academic_cycle.id);
            if (requestMode) {
                body.append('import_type', requestMode);
                if (requestMode === 'scores_only' || requestMode === 'all_in_one') {
                    body.append('test_kit_version_id', kitVersionId);
                    if (requestMode === 'scores_only') {
                        body.append('target_exam_session_id', examSessionId);
                    }
                }
            }
            const token = decodeURIComponent(
                document.cookie
                    .split('; ')
                    .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
                    ?.split('=')[1] ?? '',
            );
            const response = await fetch('/imports/analyze', {
                method: 'POST',
                body,
                signal: controller.signal,
                headers: {
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': token,
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const data = await response.json().catch(() => ({}));
            if (generation !== analysisGeneration) return;
            if (!response.ok) {
                analyzeError =
                    data.error ??
                    data.message ??
                    'We could not inspect this file. Check the format and try again.';
                resolvedAnalysisContextKey = '';
                return;
            }
            enabledSheets = Object.fromEntries(
                (data.sheets ?? []).map((sheet) => [
                    sheet.name,
                    sheet.row_count > 0,
                ]),
            );
            sheetSessionMap = Object.fromEntries(
                (data.sheets ?? []).map((sheet) => [sheet.name, '']),
            );
            resolvedAnalysisContextKey = requestContextKey;
            analysis = data;
        } catch (error) {
            if (error?.name !== 'AbortError' && generation === analysisGeneration) {
                analyzeError =
                    'A connection problem prevented file analysis. Try again.';
                resolvedAnalysisContextKey = '';
            }
        } finally {
            if (generation === analysisGeneration) {
                analyzing = false;
                analysisController = null;
            }
        }
    }

    function stageForReview(event) {
        event.preventDefault();
        const actualFile = Array.isArray(selectedFile)
            ? selectedFile[0]
            : selectedFile;
        if (
            !actualFile ||
            !academic_cycle?.id ||
            !selectedMode ||
            !analysisContextReady ||
            !analysisMatchesContext ||
            analyzing ||
            analyzeError ||
            missingSessionAssignment ||
            missingScoreSession ||
            missingKitSelection ||
            noEnabledSheets
        ) {
            return;
        }
        staging = true;
        const body = new FormData();
        body.append('file', actualFile);
        body.append('import_type', selectedMode);
        body.append('academic_cycle_id', academic_cycle.id);
        if (selectedMode === 'scores_only' || selectedMode === 'all_in_one') {
            body.append('test_kit_version_id', selectedKitVersionId);
            if (selectedMode === 'scores_only') {
                body.append('target_exam_session_id', selectedExamSessionId);
            }
            if (selectedNormVersionId && !selectedKitIsFinalOnly) {
                body.append('norm_package_version_id', selectedNormVersionId);
            }
        }
        Object.keys(enabledSheets)
            .filter((name) => enabledSheets[name])
            .forEach((name, index) =>
                body.append(`enabled_sheets[${index}]`, name),
            );
        Object.entries(sheetSessionMap).forEach(([name, sessionId]) => {
            if (sessionId)
                body.append(`sheet_to_session_map[${name}]`, sessionId);
        });
        stageError = '';
        router.post('/imports/stage', body, {
            forceFormData: true,
            onError: (errors) => {
                stageError =
                    Object.values(errors).flat().join(' ') ||
                    'We could not save this spreadsheet for review. Check the file and try again.';
            },
            onFinish: () => {
                staging = false;
            },
        });
    }

    function paginatorLabel(label) {
        return String(label ?? '')
            .replaceAll('&laquo;', '‹')
            .replaceAll('&raquo;', '›')
            .replace(/<[^>]*>/g, '');
    }

    function formatDate(value) {
        if (!value) return '—';
        const date = new Date(value);
        return Number.isNaN(date.valueOf()) ? value : date.toLocaleString();
    }

    function batchLabel(batch) {
        return (
            batch.import_type_label ??
            modeNames[batch.import_type] ??
            batch.import_type ??
            'Import batch'
        );
    }
    function statusLabel(batch) {
        const labels = {
            staged: 'Saved for review',
            pending_approval: 'Awaiting Registrar approval',
            awaiting_guidance: 'Ready for Guidance',
            completed: 'Completed',
            rejected: 'Rejected',
            failed: 'Needs attention',
        };
        return (
            batch.status_label ??
            labels[batch.status] ??
            batch.status ??
            'Saved'
        );
    }

    const breadcrumbs = [
        { label: 'System', href: '/setup' },
        { label: 'Import Hub' },
    ];
</script>

<svelte:head><title>Import Hub · NEXIAM</title></svelte:head>

<StaffLayout {breadcrumbs}>
    <main class="mx-auto max-w-6xl min-w-0 space-y-6 p-4 sm:p-6">
        <header class="space-y-2">
            <p class="text-primary text-sm font-semibold">Data imports</p>
            <h1 class="text-foreground text-2xl font-bold tracking-tight">
                Import Hub
            </h1>
            <p class="text-muted-foreground max-w-3xl text-sm">
                Upload a spreadsheet and review its rows before anything changes
                in your records. You can find saved batches and approval work
                here anytime.
            </p>
            <div class="pt-1">
                <ImportWalkthrough context={walkthroughContext} />
            </div>
            {#if academic_cycle}
                <p
                    class="border-border bg-card text-foreground inline-flex items-center gap-2 rounded-lg border px-3 py-2 text-sm"
                >
                    <FileSpreadsheet class="text-primary size-4" /> Academic cycle:
                    <strong
                        >{academic_cycle.label ??
                            academic_cycle.name ??
                            'Current cycle'}</strong
                    >
                </p>
            {:else}
                <p
                    class="rounded-lg border border-amber-500/30 bg-amber-500/10 p-3 text-sm text-amber-800 dark:text-amber-200"
                >
                    No academic cycle is available. Contact your administrator
                    before importing.
                </p>
            {/if}
        </header>

        <nav
            aria-label="Import views"
            class="border-border flex flex-wrap gap-2 border-b"
        >
            <Link
                href="/imports?view=upload"
                class="rounded-t-lg px-4 py-3 text-sm font-semibold {tab ===
                'upload'
                    ? 'border-primary text-foreground border-b-2'
                    : 'text-muted-foreground hover:text-foreground'}"
                aria-current={tab === 'upload' ? 'page' : undefined}
                >Upload</Link
            >
            <Link
                href="/imports?view=pending"
                class="rounded-t-lg px-4 py-3 text-sm font-semibold {tab ===
                'pending'
                    ? 'border-primary text-foreground border-b-2'
                    : 'text-muted-foreground hover:text-foreground'}"
                aria-current={tab === 'pending' ? 'page' : undefined}
                >Pending Approval <span
                    class="bg-muted ml-1 rounded-full px-2 py-0.5 text-xs"
                    >{pendingCount}</span
                ></Link
            >
            <Link
                href="/imports?view=history"
                class="rounded-t-lg px-4 py-3 text-sm font-semibold {tab ===
                'history'
                    ? 'border-primary text-foreground border-b-2'
                    : 'text-muted-foreground hover:text-foreground'}"
                aria-current={tab === 'history' ? 'page' : undefined}
                >Batch History</Link
            >
            {#if pendingCount > 0}<span
                    class="ml-auto self-center rounded-full border border-amber-500/30 bg-amber-500/10 px-3 py-1 text-xs font-semibold text-amber-800 dark:text-amber-200"
                    >{pendingCount}
                    {registrarApprovalInbox
                        ? 'need your review'
                        : 'awaiting Registrar approval'}</span
                >{/if}
        </nav>

        {#if tab === 'upload'}
            <section
                class="border-border bg-card space-y-5 rounded-2xl border p-4 shadow-sm sm:p-6"
                aria-labelledby="upload-heading"
                data-tour="data-ingestion-uploader"
            >
                <div>
                    <h2 id="upload-heading" class="text-lg font-bold">
                        Start with your spreadsheet
                    </h2>
                    <p class="text-muted-foreground mt-1 text-sm">
                        Auto-detect looks at the workbook and recommends a
                        suitable import. Its suggestion is guidance, not
                        approval.
                    </p>
                </div>
                <FileUpload
                    label="Choose or drop a spreadsheet"
                    accept=".csv,.xlsx,.xls,.txt"
                    maxSize="25MB"
                    bind:files={selectedFile}
                />
                {#if analyzing}<p
                        role="status"
                        class="bg-muted/50 text-muted-foreground flex items-center gap-2 rounded-lg p-3 text-sm"
                    >
                        <RefreshCw class="size-4 animate-spin" />Checking
                        workbook structure…
                    </p>{/if}
                {#if analyzeError}<p
                        role="alert"
                        class="border-destructive/30 bg-destructive/10 text-destructive flex items-start gap-2 rounded-lg border p-3 text-sm"
                    >
                        <AlertCircle
                            class="mt-0.5 size-4 shrink-0"
                        />{analyzeError}
                    </p>{/if}

                {#if analysis && !analyzing}
                    <div
                        class="border-border bg-muted/20 space-y-4 rounded-xl border p-4"
                        data-tour="data-ingestion-preview"
                    >
                        <div
                            class="flex flex-wrap items-start justify-between gap-3"
                        >
                            <div>
                                <h3 class="font-semibold">
                                    File check complete
                                </h3>
                                <p class="text-muted-foreground mt-1 text-sm">
                                    {analysis.sheets?.length ?? 0} sheets · {analysis.total_rows ??
                                        '—'} data rows found. No applicant row details
                                    are shown until you open the saved review.
                                </p>
                            </div>
                            <span
                                class="border-primary/25 bg-primary/5 text-primary inline-flex items-center gap-1 rounded-full border px-3 py-1 text-xs font-semibold"
                                ><Sparkles class="size-3.5" /> Advisory recommendation</span
                            >
                        </div>
                        {#if suggestedMode}
                            <div
                                class="border-primary/20 bg-primary/5 rounded-lg border p-3 text-sm"
                            >
                                <strong
                                    >Suggested: {modeNames[suggestedMode] ??
                                        'Review the workbook'}</strong
                                >
                                <p class="text-muted-foreground mt-1">
                                    {analysis.detection_reason ??
                                        'Based on the workbook structure. Confirm the right operation before continuing.'}
                                </p>
                                {#if !suggestedAllowed}<p
                                        class="mt-2 font-medium text-amber-800 dark:text-amber-200"
                                    >
                                        This operation is not available to your
                                        account. Choose an authorized option
                                        below or ask the appropriate office for
                                        help.
                                    </p>{/if}
                            </div>
                        {:else}
                            <p
                                class="rounded-lg border border-amber-500/30 bg-amber-500/10 p-3 text-sm text-amber-900 dark:text-amber-100"
                            >
                                We could not confidently identify this workbook.
                                Choose an operation you are authorized to
                                perform; if you are unsure, ask your office
                                before continuing.
                            </p>
                        {/if}
                        {#if analysis.sheets?.length > 1 || selectedMode === 'multi_session'}
                            <fieldset class="space-y-2">
                                <legend class="text-sm font-semibold"
                                    >Sheets included</legend
                                >
                                {#each analysis.sheets as sheet, index (sheet.name)}
                                    <div
                                        class="border-border bg-background flex flex-wrap items-center gap-3 rounded-lg border p-3"
                                    >
                                        <input
                                            id={`sheet-${index}`}
                                            type="checkbox"
                                            bind:checked={
                                                enabledSheets[sheet.name]
                                            }
                                            class="accent-primary size-4"
                                        />
                                        <label
                                            for={`sheet-${index}`}
                                            class="min-w-0 flex-1 text-sm"
                                            ><strong>{sheet.name}</strong><span
                                                class="text-muted-foreground ml-2"
                                                >{sheet.row_count} rows</span
                                            ></label
                                        >
                                        {#if selectedMode === 'multi_session' && enabledSheets[sheet.name]}<label
                                                class="sr-only"
                                                for={`session-${index}`}
                                                >Session for {sheet.name}</label
                                            ><select
                                                id={`session-${index}`}
                                                bind:value={
                                                    sheetSessionMap[sheet.name]
                                                }
                                                class="border-input bg-background rounded-md border px-2 py-1.5 text-sm"
                                                ><option value=""
                                                    >Choose an existing session</option
                                                >{#each active_sessions as session (session.id)}<option
                                                        value={session.id}
                                                        >{session.code}
                                                        {session.room_name
                                                            ? `(${session.room_name})`
                                                            : ''}</option
                                                    >{/each}</select
                                            >{/if}
                                    </div>
                                {/each}
                            </fieldset>
                        {/if}
                        <fieldset class="space-y-2">
                            <legend class="text-sm font-semibold"
                                >Choose what this file is for</legend
                            >
                            <p class="text-muted-foreground text-xs">
                                {manualMode
                                    ? 'Manual choice selected.'
                                    : 'Auto-detect is active. Choose a mode yourself if you need to override the recommendation.'}
                                Only operations you are allowed to perform are listed.
                                A recommendation never changes your choice without
                                confirmation.
                            </p>
                            {#if allowedModes.length === 0}<p
                                    class="border-border text-muted-foreground rounded-lg border p-3 text-sm"
                                >
                                    No import operation is available to your
                                    account. Contact your Registrar or Guidance
                                    office.
                                </p>{/if}
                            {#each allowedModes as mode (mode)}<label
                                    class="border-border bg-background hover:border-primary/40 flex cursor-pointer items-start gap-3 rounded-lg border p-3"
                                    ><input
                                        type="radio"
                                        name="import-mode"
                                        value={mode}
                                        bind:group={manualMode}
                                        class="accent-primary mt-1"
                                    /><span
                                        ><strong class="text-sm"
                                            >{modeNames[mode]}</strong
                                        ><span
                                            class="text-muted-foreground mt-1 block text-xs"
                                            >{modeDescriptions[
                                                mode
                                            ]}{suggestedMode === mode
                                                ? ' Recommended for this file.'
                                                : ''}</span
                                        ></span
                                    ></label
                                >{/each}
                        </fieldset>
                        {#if needsExplicitKit}
                            <div class="border-border bg-background grid gap-3 rounded-xl border p-4 sm:grid-cols-2">
                                {#if selectedMode === 'scores_only'}
                                    <label class="space-y-1.5 text-xs font-semibold text-foreground">
                                        <span>Completed target session</span>
                                        <select
                                            bind:value={selectedExamSessionId}
                                            class="border-input bg-background min-h-10 w-full rounded-md border px-3 py-2 text-sm"
                                        >
                                            <option value="" disabled>Select a completed session</option>
                                            {#each completedSessions as session (session.id)}
                                                <option value={String(session.id)}>
                                                    {session.code} · {session.kit_label}
                                                </option>
                                            {/each}
                                        </select>
                                    </label>
                                {/if}
                                <label class="space-y-1.5 text-xs font-semibold text-foreground">
                                    <span>Exact locked Test Kit version</span>
                                    <select
                                        bind:value={selectedKitVersionId}
                                        class="border-input bg-background min-h-10 w-full rounded-md border px-3 py-2 text-sm"
                                    >
                                        <option value="" disabled>Select a Test Kit version</option>
                                        {#each selectableKitVersions as kit (kit.id)}
                                            <option value={String(kit.id)}>
                                                {kit.display_name} ({kit.kit_code} v{kit.version_number}) · {kit.profile_name}{kit.is_final_only
                                                    ? ' · Final-only percentile ranks'
                                                    : ''}
                                            </option>
                                        {/each}
                                    </select>
                                </label>
                                {#if selectedKitIsFinalOnly}
                                    <p class="text-muted-foreground text-xs sm:col-span-2">
                                        This exact profile accepts external percentile ranks with a separate source reference for each metric. No norm package is used.
                                    </p>
                                {:else}
                                    <label class="space-y-1.5 text-xs font-semibold text-foreground sm:col-span-2">
                                        <span>Norm package version <span class="font-normal text-muted-foreground">(optional)</span></span>
                                        <select
                                            bind:value={selectedNormVersionId}
                                            class="border-input bg-background min-h-10 w-full rounded-md border px-3 py-2 text-sm"
                                        >
                                            <option value="">No norm selected — raw scores remain awaiting conversion</option>
                                            {#each norm_versions as norm (norm.id)}
                                                <option value={String(norm.id)}>{norm.label}</option>
                                            {/each}
                                        </select>
                                    </label>
                                {/if}
                            </div>
                        {/if}
                        {#if missingScoreSession}
                            <p role="status" class="rounded-lg border border-amber-500/30 bg-amber-500/10 p-3 text-sm text-amber-900 dark:text-amber-100">
                                Select the exact completed target session before staging score rows.
                            </p>
                        {:else if missingKitSelection}
                            <p role="status" class="rounded-lg border border-amber-500/30 bg-amber-500/10 p-3 text-sm text-amber-900 dark:text-amber-100">
                                Select the exact locked Test Kit version before staging this score import.
                            </p>
                        {:else if selectedMode === 'multi_session' && missingSessionAssignment}
                            <p role="status" class="rounded-lg border border-amber-500/30 bg-amber-500/10 p-3 text-sm text-amber-900 dark:text-amber-100">
                                Choose an existing exam session for every included sheet before saving this roster.
                            </p>
                        {:else if selectedMode === 'multi_session' && noEnabledSheets}
                            <p role="status" class="rounded-lg border border-amber-500/30 bg-amber-500/10 p-3 text-sm text-amber-900 dark:text-amber-100">
                                Select at least one data sheet to continue.
                            </p>
                        {/if}
                        {#if analysis.checks?.length}<div class="space-y-2">
                                <h4 class="text-sm font-semibold">
                                    File checks
                                </h4>
                                {#each analysis.checks as check (check.label)}<p
                                        class="flex gap-2 text-sm {check.status ===
                                        'fail'
                                            ? 'text-destructive'
                                            : 'text-muted-foreground'}"
                                    >
                                        {#if check.status === 'pass'}<CheckCircle2
                                                class="size-4 shrink-0 text-emerald-600"
                                            />{:else}<AlertCircle
                                                class="size-4 shrink-0 text-amber-600"
                                            />{/if}<span
                                            ><strong>{check.label}</strong> — {check.detail}</span
                                        >
                                    </p>{/each}
                            </div>{/if}
                        <div
                            class="border-border flex flex-col gap-3 border-t pt-4 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <p class="text-muted-foreground text-sm">
                                Upload only saves a draft. You will review the
                                saved rows before any records are processed.
                            </p>
                            <Button
                                type="button"
                                disabled={!canStage}
                                onclick={stageForReview}
                                class="gap-2"
                                >{#if staging}<RefreshCw
                                        class="size-4 animate-spin"
                                    />Saving draft…{:else}<Upload
                                        class="size-4"
                                    />Stage for review{/if}</Button
                            >
                            {#if stageError}<p
                                    role="alert"
                                    class="border-destructive/30 bg-destructive/10 text-destructive rounded-lg border p-3 text-sm"
                                >
                                    {stageError}
                                </p>{/if}
                        </div>
                    </div>
                {/if}
                {#if templateType}
                    <Button
                        type="button"
                        variant="outline"
                        class="gap-2"
                        onclick={openTemplateBuilder}
                        data-tour="import-template-entry"
                    >
                        <FileSpreadsheet class="size-4" aria-hidden="true" />
                        Build import template
                    </Button>
                {/if}
            </section>

            <details class="border-border bg-card rounded-xl border">
                <summary
                    class="flex cursor-pointer list-none items-center justify-between p-4 font-semibold"
                    >Other imports <ChevronDown class="size-4" /></summary
                >
                <div
                    class="border-border grid gap-3 border-t p-4 sm:grid-cols-2"
                >
                    {#each specialized_imports ?? [] as item (item.key ?? item.href ?? item.url)}<Link
                            href={item.href ?? item.url}
                            class="border-border hover:border-primary/40 hover:bg-muted/30 rounded-xl border p-4 transition"
                            ><h3 class="font-semibold">{item.label}</h3>
                            <p class="text-muted-foreground mt-1 text-sm">
                                {item.description ?? `Open ${item.label}`}
                            </p>
                            <p class="text-muted-foreground mt-3 text-xs">
                                Accepted formats: {(item.formats ?? []).join(
                                    ', ',
                                ) || 'See destination'}
                            </p></Link
                        >{:else}<p class="text-muted-foreground text-sm">
                            No other import tools are available for your
                            account.
                        </p>{/each}
                </div>
            </details>
        {:else if tab === 'pending'}
            <section class="space-y-4" aria-labelledby="pending-heading">
                <div>
                    <h2 id="pending-heading" class="text-lg font-bold">
                        {registrarApprovalInbox
                            ? 'Pending Approval'
                            : 'Awaiting Registrar approval'}
                    </h2>
                    <p class="text-muted-foreground text-sm">
                        {registrarApprovalInbox
                            ? 'Review applicant information and row checks before giving consent; exam scores are not part of Registrar review.'
                            : 'These batches are waiting for Registrar consent. Once approved, Guidance can continue the exam-session and score workflow.'}
                    </p>
                </div>
                {#if pendingRows.length === 0}<div
                        class="border-border rounded-xl border border-dashed p-8 text-center"
                    >
                        <Clock3 class="text-muted-foreground mx-auto size-8" />
                        <h3 class="mt-3 font-semibold">
                            {registrarApprovalInbox
                                ? 'Nothing needs your approval'
                                : 'No batches awaiting Registrar approval'}
                        </h3>
                        <p class="text-muted-foreground mt-1 text-sm">
                            {registrarApprovalInbox
                                ? 'New batches needing consent will appear here.'
                                : 'Your batches awaiting consent will appear here.'}
                        </p>
                    </div>
                {:else}<div class="grid gap-3">
                        {#each pendingRows as batch (batch.id)}<article
                                class="border-border bg-card flex flex-col gap-4 rounded-xl border p-4 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div>
                                    <h3 class="font-semibold">
                                        {batchLabel(batch)}
                                    </h3>
                                    <p
                                        class="text-muted-foreground mt-1 text-sm"
                                    >
                                        {batch.academic_cycle?.label ??
                                            batch.academic_cycle_label ??
                                            academic_cycle?.label ??
                                            'Academic cycle'} · {batch.total_rows ??
                                            batch.row_count ??
                                            '—'} rows · {formatDate(
                                            batch.created_at,
                                        )}
                                    </p>
                                    <p
                                        class="text-muted-foreground mt-1 text-xs font-medium"
                                    >
                                        {registrarApprovalInbox
                                            ? 'Needs your review'
                                            : 'Awaiting Registrar approval'}
                                    </p>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <Link
                                        href={`/imports/${batch.id}/review`}
                                        class="bg-primary text-primary-foreground inline-flex min-h-10 items-center rounded-md px-4 text-sm font-semibold"
                                        >{registrarApprovalInbox
                                            ? 'Review applicants'
                                            : 'Track batch'}</Link
                                    >{#if registrarApprovalInbox}<Button
                                            variant="outline"
                                            onclick={() => {
                                                rejectionBatch = batch;
                                                rejectionReason = '';
                                            }}>Reject batch</Button
                                        >{/if}
                                </div>
                            </article>{/each}
                    </div>
                    {#if pendingPageLinks.length > 0}
                        <nav
                            aria-label="Pending approval pages"
                            class="flex flex-wrap gap-2"
                        >
                            {#each pendingPageLinks as pageLink, index (`pending-${index}`)}
                                {#if pageLink.url}
                                    <Link
                                        href={pageLink.url}
                                        preserveState={true}
                                        preserveScroll={true}
                                        aria-current={pageLink.active
                                            ? 'page'
                                            : undefined}
                                        class="border-border inline-flex min-h-10 items-center rounded-md border px-3 text-sm {pageLink.active
                                            ? 'bg-primary text-primary-foreground'
                                            : 'hover:bg-muted'}"
                                        >{paginatorLabel(pageLink.label)}</Link
                                    >
                                {/if}
                            {/each}
                        </nav>
                    {/if}
                {/if}
            </section>
        {:else}
            <section class="space-y-4" aria-labelledby="history-heading">
                <div>
                    <h2 id="history-heading" class="text-lg font-bold">
                        Batch History
                    </h2>
                    <p class="text-muted-foreground text-sm">
                        Recent saved imports and their current status. Pending
                        approval work is listed separately.
                    </p>
                </div>
                {#if recent_batches.length === 0}<div
                        class="border-border rounded-xl border border-dashed p-8 text-center"
                    >
                        <History class="text-muted-foreground mx-auto size-8" />
                        <h3 class="mt-3 font-semibold">No recent batches</h3>
                        <p class="text-muted-foreground mt-1 text-sm">
                            After a spreadsheet is saved, its tracking link will
                            appear here.
                        </p>
                    </div>
                {:else}<div
                        class="border-border overflow-x-auto rounded-xl border"
                    >
                        <table class="w-full min-w-144 text-left text-sm">
                            <thead class="bg-muted/50 text-muted-foreground"
                                ><tr
                                    ><th class="p-3 font-semibold">Import</th
                                    ><th class="p-3 font-semibold"
                                        >Academic cycle</th
                                    ><th class="p-3 font-semibold">Rows</th><th
                                        class="p-3 font-semibold">Status</th
                                    ><th class="p-3 font-semibold">Saved</th><th
                                        class="p-3"
                                        ><span class="sr-only"
                                            >Batch action</span
                                        ></th
                                    ></tr
                                ></thead
                            ><tbody class="divide-border divide-y"
                                >{#each recent_batches as batch (batch.id)}<tr
                                        ><td class="p-3 font-medium"
                                            >{batchLabel(batch)}</td
                                        ><td class="text-muted-foreground p-3"
                                            >{batch.academic_cycle?.label ??
                                                batch.academic_cycle_label ??
                                                '—'}</td
                                        ><td class="p-3"
                                            >{batch.total_rows ??
                                                batch.row_count ??
                                                '—'}</td
                                        ><td class="p-3"
                                            >{statusLabel(batch)}</td
                                        ><td class="text-muted-foreground p-3"
                                            >{formatDate(batch.created_at)}</td
                                        ><td class="p-3"
                                            ><Link
                                                href={`/imports/${batch.id}/review`}
                                                class="text-primary font-semibold hover:underline"
                                                >Open review</Link
                                            ></td
                                        ></tr
                                    >{/each}</tbody
                            >
                        </table>
                    </div>{/if}
            </section>
        {/if}
    </main>
    <ImportTemplateBuilder
        bind:open={templateBuilderOpen}
        bind:mode={templateMode}
        allowedModes={templateModes}
        academicCycle={academic_cycle}
        testKitVersions={test_kit_versions}
        targetExamSessions={active_sessions}
        bind:testKitVersionId={selectedKitVersionId}
        bind:targetExamSessionId={selectedExamSessionId}
    />
    {#if rejectionBatch}
        <div
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
            role="presentation"
        >
            <section
                role="dialog"
                aria-modal="true"
                aria-labelledby="reject-title"
                class="border-border bg-card w-full max-w-lg space-y-4 rounded-xl border p-5 shadow-xl"
            >
                <h2 id="reject-title" class="text-lg font-bold">
                    Reject this batch?
                </h2>
                <p class="text-muted-foreground text-sm">
                    Give a reason so the sending office knows what needs
                    attention.
                </p>
                <label for="reject-reason" class="block text-sm font-medium"
                    >Reason for rejection</label
                >
                <textarea
                    id="reject-reason"
                    bind:value={rejectionReason}
                    rows="4"
                    class="border-input bg-background w-full rounded-md border p-3 text-sm"
                    required></textarea>
                <div class="flex justify-end gap-2">
                    <Button
                        variant="outline"
                        onclick={() => {
                            rejectionBatch = null;
                        }}>Cancel</Button
                    >
                    <Button
                        variant="destructive"
                        disabled={!rejectionReason.trim()}
                        onclick={() => {
                            router.post(
                                `/imports/${rejectionBatch.id}/reject`,
                                { reason: rejectionReason.trim() },
                                {
                                    onSuccess: () => {
                                        rejectionBatch = null;
                                        rejectionReason = '';
                                    },
                                },
                            );
                        }}>Reject batch</Button
                    >
                </div>
            </section>
        </div>
    {/if}
</StaffLayout>
