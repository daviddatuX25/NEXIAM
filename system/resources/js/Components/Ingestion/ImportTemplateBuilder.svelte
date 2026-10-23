<script>
    import { onDestroy } from 'svelte';
    import * as Dialog from '@/Components/ui/dialog';
    import { Button } from '@/Components/ui/button';
    import ImportWalkthrough from '@/Components/Ingestion/ImportWalkthrough.svelte';
    import {
        Clipboard,
        ClipboardCheck,
        Download,
        FileSpreadsheet,
        LoaderCircle,
        LockKeyhole,
        RotateCcw,
        Search,
        X,
    } from 'lucide-svelte';

    let {
        open = $bindable(false),
        mode = $bindable('applicants_only'),
        allowedModes = ['applicants_only'],
        lockMode = false,
        academicCycle = null,
        testKitVersions = [],
        targetExamSessions = [],
        testKitVersionId = $bindable(''),
        targetExamSessionId = $bindable(''),
        onClose = () => {},
    } = $props();

    const modeOptions = [
        {
            value: 'applicants_only',
            label: 'Applicant admissions',
            description: 'Applicant identity and current intake fields.',
        },
        {
            value: 'scores_only',
            label: 'Exam scores',
            description:
                'Existing applicant references and accepted score inputs.',
        },
        {
            value: 'all_in_one',
            label: 'Combined: applicants + scores',
            description:
                'One applicant per row, with applicant and score fields.',
        },
    ];

    let schema = $state(null);
    let loading = $state(false);
    let status = $state('idle');
    let requestError = $state('');
    let staleNotice = $state('');
    let contractNeedsReview = $state(false);
    let search = $state('');
    let selectedHeaders = $state(new Set());
    let selectedFieldHeader = $state('');
    let copyMessage = $state('');
    let exportMessage = $state('');
    let downloading = $state(false);
    let retryVersion = $state(0);

    let requestGeneration = 0;
    let exportGeneration = 0;
    let activeContextKey = '';
    let loadedContextKey = '';
    let loadedRevision = '';
    let forceSchemaRefresh = false;
    let lastStartedRetryVersion = 0;
    let lastRequestFailed = false;
    let schemaController = null;
    let exportController = null;

    const cycleId = $derived(academicCycle?.id ?? null);
    const cycleLabel = $derived(
        academicCycle?.label ??
            academicCycle?.staff_label ??
            academicCycle?.name ??
            null,
    );
    const scoreBearing = $derived(
        mode === 'scores_only' || mode === 'all_in_one',
    );
    const availableKitVersions = $derived(
        mode === 'all_in_one'
            ? (testKitVersions ?? []).filter((kit) => kit.status === 'active')
            : (testKitVersions ?? []),
    );
    const selectedKit = $derived(
        availableKitVersions.find(
            (kit) => String(kit.id) === String(testKitVersionId),
        ) ?? null,
    );
    const allowedTargetSessions = $derived.by(() => {
        if (mode !== 'scores_only' || !testKitVersionId) return [];
        return (targetExamSessions ?? []).filter((session) =>
            session.status === undefined || session.status === 'completed'
                ? String(session.test_kit_version_id ?? '') ===
                  String(testKitVersionId)
                : false,
        );
    });
    const selectedTarget = $derived(
        allowedTargetSessions.find(
            (session) => String(session.id) === String(targetExamSessionId),
        ) ?? null,
    );
    const contextKey = $derived(
        [
            mode,
            cycleId ?? '',
            scoreBearing ? (testKitVersionId ?? '') : '',
            mode === 'scores_only' ? (targetExamSessionId ?? '') : '',
        ].join('|'),
    );
    const fields = $derived(Array.isArray(schema?.fields) ? schema.fields : []);
    const orderedSelectedFields = $derived.by(() =>
        fields.filter((field) => selectedHeaders.has(field.header)),
    );
    const selectedField = $derived(
        fields.find((field) => field.header === selectedFieldHeader) ??
            orderedSelectedFields[0] ??
            null,
    );
    const availableGroups = $derived.by(() => {
        const query = search.trim().toLocaleLowerCase();
        const groups = new Map();
        for (const field of fields) {
            const haystack = [
                field.header,
                field.label,
                field.group,
                field.description,
                field.source,
                ...(Array.isArray(field.aliases) ? field.aliases : []),
            ]
                .filter((value) => value !== null && value !== undefined)
                .join(' ')
                .toLocaleLowerCase();
            if (query && !haystack.includes(query)) continue;
            const group = field.group || 'Other fields';
            if (!groups.has(group)) groups.set(group, []);
            groups.get(group).push(field);
        }
        return Array.from(groups, ([name, groupFields]) => ({
            name,
            fields: groupFields,
        }));
    });
    const canChooseMode = $derived(!lockMode && allowedModes.length > 1);
    const isAuthorizedMode = $derived((allowedModes ?? []).includes(mode));
    const hasKitChoices = $derived(availableKitVersions.length > 0);
    const kitChoiceIsValid = $derived(
        !scoreBearing || (Boolean(testKitVersionId) && Boolean(selectedKit)),
    );
    const canCopyHeaders = $derived(
        open &&
            status === 'ready' &&
            !loading &&
            !requestError &&
            orderedSelectedFields.length > 0,
    );
    const canCopyField = $derived(
        open &&
            status === 'ready' &&
            !loading &&
            !requestError &&
            Boolean(schema),
    );
    const canDownload = $derived(
        canCopyHeaders && !downloading && !contractNeedsReview,
    );
    const contextReady = $derived(
        Boolean(cycleId) &&
            isAuthorizedMode &&
            (!scoreBearing || kitChoiceIsValid),
    );
    const walkthroughContext = $derived({
        entityKey: `template-${mode}-${cycleId ?? 'none'}`,
        safetyKey: schema?.revision ?? status,
        freshness: loading ? 'loading' : requestError ? 'error' : 'current',
        activePreset: mode,
        hasAcademicCycle: Boolean(cycleId),
        academicCycleLabel: cycleLabel,
        hasSelectedFile: false,
        isAnalyzing: false,
        hasAnalysisResult: false,
        detectedSheetsCount: 0,
        recentBatchesCount: 0,
        pendingReviewCount: 0,
        builderOpen: true,
        hasTemplateSchema: Boolean(schema && status === 'ready'),
        hasFieldDetails: Boolean(selectedField),
        hasUploader: false,
        hasTemplateEntry: false,
    });

    function responseMessage(data, fallback) {
        const messages = Object.values(data?.errors ?? {}).flat();
        return messages.length > 0
            ? messages.join(' ')
            : (data?.message ?? data?.error ?? fallback);
    }
    function acknowledgeRefreshedHeaders() {
        contractNeedsReview = false;
        staleNotice = '';
    }
    function defaultHeaderSelection(data) {
        const selected = new Set(data.default_headers);
        for (const field of data.fields) {
            if (field.required) selected.add(field.header);
        }
        return new Set(
            data.fields
                .filter((field) => selected.has(field.header))
                .map((field) => field.header),
        );
    }
    function currentHeaderSignature() {
        return (schema?.fields ?? [])
            .filter((field) => selectedHeaders.has(field.header))
            .map((field) => field.header)
            .join('\u0000');
    }

    function schemaUrl(context) {
        const params = new URLSearchParams();
        if (context.cycleId !== null && context.cycleId !== undefined) {
            params.set('academic_cycle_id', String(context.cycleId));
        }
        if (context.scoreBearing) {
            params.set(
                'test_kit_version_id',
                String(context.testKitVersionId ?? ''),
            );
        }
        if (context.mode === 'scores_only' && context.targetExamSessionId) {
            params.set(
                'target_exam_session_id',
                String(context.targetExamSessionId),
            );
        }
        return `/imports/templates/${encodeURIComponent(context.mode)}/schema?${params.toString()}`;
    }

    function invalidateExport() {
        exportGeneration += 1;
        exportController?.abort();
        exportController = null;
        downloading = false;
    }

    async function fetchSchema(
        context,
        generation,
        controller,
        preserveChoices,
    ) {
        loading = true;
        status = 'loading';
        requestError = '';
        copyMessage = '';
        exportMessage = '';
        invalidateExport();

        try {
            const response = await fetch(schemaUrl(context), {
                method: 'GET',
                signal: controller.signal,
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const data = await response.json().catch(() => ({}));
            if (
                generation !== requestGeneration ||
                context.key !== activeContextKey ||
                !open
            )
                return;

            if (!response.ok) {
                lastRequestFailed = true;
                if (response.status === 403) {
                    schema = null;
                    selectedHeaders = new Set();
                    loadedContextKey = '';
                    loadedRevision = '';
                    contractNeedsReview = false;
                    staleNotice = '';
                    status = 'denied';
                    requestError =
                        'You do not have permission to view this template schema.';
                } else {
                    status = response.status === 409 ? 'stale' : 'error';
                    if (response.status === 409) {
                        contractNeedsReview = true;
                        staleNotice =
                            'The template context changed. Refresh the schema before continuing.';
                    }
                    requestError = responseMessage(
                        data,
                        response.status === 409
                            ? 'The template context changed. Refresh the schema before continuing.'
                            : 'The template schema could not be loaded.',
                    );
                }
                return;
            }

            if (
                !data ||
                !Array.isArray(data.fields) ||
                !Array.isArray(data.default_headers) ||
                typeof data.revision !== 'string'
            ) {
                lastRequestFailed = true;
                status = 'error';
                requestError =
                    'The template schema response was incomplete. Retry to load it again.';
                return;
            }

            const hasSameContract =
                preserveChoices &&
                loadedContextKey === context.key &&
                loadedRevision === data.revision;
            const contractChanged =
                preserveChoices && loadedRevision !== data.revision;
            schema = data;
            if (contractChanged) {
                contractNeedsReview = true;
            }
            if (!hasSameContract) {
                selectedHeaders = defaultHeaderSelection(data);
                selectedFieldHeader =
                    data.fields.find((field) =>
                        selectedHeaders.has(field.header),
                    )?.header ??
                    data.fields[0]?.header ??
                    '';
            }
            if (contractNeedsReview) {
                staleNotice =
                    'The template contract changed. Review the refreshed headers before downloading again.';
            } else if (!preserveChoices) {
                staleNotice = '';
            }
            loadedContextKey = context.key;
            loadedRevision = data.revision;
            lastRequestFailed = false;
            status = 'ready';
            requestError = '';
        } catch (error) {
            if (
                error?.name === 'AbortError' ||
                generation !== requestGeneration
            )
                return;
            lastRequestFailed = true;
            status = 'error';
            requestError =
                'A connection problem prevented the template schema from loading. Retry when the connection is available.';
        } finally {
            if (generation === requestGeneration) loading = false;
        }
    }

    $effect(() => {
        const isOpen = open;
        const key = contextKey;
        const retry = retryVersion;
        if (!isOpen) return;

        const context = {
            key,
            mode,
            cycleId,
            scoreBearing,
            testKitVersionId,
            targetExamSessionId:
                mode === 'scores_only' ? targetExamSessionId : '',
            authorized: isAuthorizedMode,
            hasKitChoices,
            validKitChoice: kitChoiceIsValid,
            compatibleTarget: !targetExamSessionId || Boolean(selectedTarget),
        };

        if (key !== activeContextKey) {
            activeContextKey = key;
            loadedContextKey = '';
            loadedRevision = '';
            forceSchemaRefresh = false;
            lastRequestFailed = false;
            schema = null;
            selectedHeaders = new Set();
            selectedFieldHeader = '';
            staleNotice = '';
            contractNeedsReview = false;
            requestError = '';
            status = 'idle';
            search = '';
        }

        const generation = ++requestGeneration;
        schemaController?.abort();
        schemaController = new AbortController();
        const controller = schemaController;

        if (!context.cycleId) {
            schema = null;
            status = 'missing-cycle';
            loading = false;
            return () => controller.abort();
        }
        if (!context.authorized) {
            schema = null;
            status = 'denied';
            requestError =
                'You do not have permission to prepare this template mode.';
            loading = false;
            return () => controller.abort();
        }
        if (context.scoreBearing && !context.hasKitChoices) {
            schema = null;
            status = 'no-kit';
            loading = false;
            return () => controller.abort();
        }
        if (context.scoreBearing && !context.validKitChoice) {
            schema = null;
            status = 'needs-kit';
            loading = false;
            return () => controller.abort();
        }
        if (context.mode === 'scores_only' && !context.compatibleTarget) {
            schema = null;
            status = 'incompatible-target';
            loading = false;
            return () => controller.abort();
        }

        const forceRefresh =
            forceSchemaRefresh || retry !== lastStartedRetryVersion;
        forceSchemaRefresh = false;
        lastStartedRetryVersion = retry;
        if (
            loadedContextKey === key &&
            loadedRevision &&
            !forceRefresh &&
            !lastRequestFailed
        ) {
            status = 'ready';
            return () => controller.abort();
        }

        const preserveChoices =
            loadedContextKey === key && Boolean(loadedRevision);
        void fetchSchema(context, generation, controller, preserveChoices);
        return () => controller.abort();
    });

    function retrySchema() {
        requestError = '';
        status = 'idle';
        forceSchemaRefresh = true;
        retryVersion += 1;
    }

    function selectKit(event) {
        testKitVersionId = event.currentTarget.value;
        if (
            targetExamSessionId &&
            !targetExamSessions.some(
                (session) =>
                    String(session.id) === String(targetExamSessionId) &&
                    (session.status === undefined ||
                        session.status === 'completed') &&
                    String(session.test_kit_version_id ?? '') ===
                        String(testKitVersionId),
            )
        ) {
            targetExamSessionId = '';
        }
    }

    function toggleField(field) {
        if (field.required) return;
        const next = new Set(selectedHeaders);
        if (next.has(field.header)) {
            next.delete(field.header);
        } else {
            next.add(field.header);
        }
        selectedHeaders = next;
        selectedFieldHeader = field.header;
        if (!contractNeedsReview) staleNotice = '';
        copyMessage = '';
    }

    function removeField(field) {
        if (field.required) return;
        const next = new Set(selectedHeaders);
        next.delete(field.header);
        selectedHeaders = next;
        if (!contractNeedsReview) staleNotice = '';
    }

    function resetSelection() {
        if (!schema || status !== 'ready') return;
        selectedHeaders = defaultHeaderSelection(schema);
        selectedFieldHeader =
            schema.fields.find((field) => selectedHeaders.has(field.header))
                ?.header ??
            schema.fields[0]?.header ??
            '';
        if (!contractNeedsReview) staleNotice = '';
        copyMessage = '';
        exportMessage = '';
    }

    function setFieldDetails(field) {
        selectedFieldHeader = field.header;
    }

    async function copyText(text, label) {
        copyMessage = '';
        try {
            if (
                !navigator.clipboard ||
                typeof navigator.clipboard.writeText !== 'function'
            ) {
                throw new Error(
                    'Clipboard access is unavailable in this browser.',
                );
            }
            await navigator.clipboard.writeText(text);
            copyMessage = `${label} copied.`;
            if (!contractNeedsReview) staleNotice = '';
        } catch {
            copyMessage = `Could not copy ${label.toLocaleLowerCase()}. Allow clipboard access or select the visible header text and copy it manually.`;
        }
    }

    function copyHeader(field) {
        if (!canCopyField) return;
        void copyText(field.header, 'Header');
    }

    function copyAllHeaders() {
        if (!canCopyHeaders) return;
        void copyText(
            orderedSelectedFields.map((field) => field.header).join('\t'),
            'Headers',
        );
    }

    function csvQuery(headers, revision, context) {
        const params = new URLSearchParams();
        params.set('academic_cycle_id', String(context.cycleId));
        if (context.scoreBearing) {
            params.set('test_kit_version_id', String(context.testKitVersionId));
        }
        if (context.mode === 'scores_only' && context.targetExamSessionId) {
            params.set(
                'target_exam_session_id',
                String(context.targetExamSessionId),
            );
        }
        params.set('revision', revision);
        for (const header of headers) params.append('headers[]', header);
        return params.toString();
    }

    function startDownload(blob, context) {
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `nexiam-${context.mode}-${context.cycleId}-headers.csv`;
        link.hidden = true;
        document.body.append(link);
        link.click();
        link.remove();
        window.setTimeout(() => URL.revokeObjectURL(url), 1000);
    }

    async function downloadCsv() {
        if (!canDownload || !schema) return;
        downloading = true;
        exportMessage = '';
        const generation = ++exportGeneration;
        const context = {
            key: contextKey,
            mode,
            cycleId,
            scoreBearing,
            testKitVersionId,
            targetExamSessionId:
                mode === 'scores_only' ? targetExamSessionId : '',
            revision: schema.revision,
        };
        const currentHeaders = orderedSelectedFields.map(
            (field) => field.header,
        );
        const currentSignature = currentHeaders.join('\u0000');
        const controller = new AbortController();
        exportController = controller;
        const isCurrentRequest = () =>
            generation === exportGeneration &&
            context.key === activeContextKey &&
            context.revision === schema?.revision &&
            currentSignature === currentHeaderSignature() &&
            open;

        try {
            const response = await fetch(
                `/imports/templates/${encodeURIComponent(context.mode)}?${csvQuery(currentHeaders, context.revision, context)}`,
                {
                    method: 'GET',
                    signal: controller.signal,
                    headers: { Accept: 'application/json' },
                },
            );
            if (!isCurrentRequest()) return;

            if (!response.ok) {
                const data = await response.json().catch(() => ({}));
                if (!isCurrentRequest()) return;

                if (response.status === 409) {
                    contractNeedsReview = true;
                    staleNotice =
                        'The template contract changed. Review the refreshed headers before downloading again.';
                    status = 'stale';
                    forceSchemaRefresh = true;
                    retryVersion += 1;
                } else if (response.status === 403) {
                    schema = null;
                    selectedHeaders = new Set();
                    loadedContextKey = '';
                    loadedRevision = '';
                    contractNeedsReview = false;
                    staleNotice = '';
                    lastRequestFailed = true;
                    status = 'denied';
                    requestError =
                        'Your permission to view this template schema is no longer available.';
                } else {
                    exportMessage = responseMessage(
                        data,
                        'The server rejected this template request. Review the context and selected headers, then retry.',
                    );
                }
                return;
            }

            const blob = await response.blob();
            if (!isCurrentRequest()) return;

            startDownload(blob, context);
            exportMessage = 'CSV template downloaded.';
            staleNotice = '';
        } catch (error) {
            if (error?.name !== 'AbortError' && isCurrentRequest()) {
                exportMessage =
                    'The CSV template could not be prepared or downloaded. Check the connection and retry.';
            }
        } finally {
            if (generation === exportGeneration) {
                downloading = false;
                exportController = null;
            }
        }
    }

    function closeBuilder() {
        open = false;
        onClose();
    }

    function handleOpenChange(nextOpen) {
        if (!nextOpen) onClose();
    }

    function formatValue(value) {
        if (value === null || value === undefined || value === '')
            return 'Not provided by this setup';
        if (typeof value === 'string' || typeof value === 'number')
            return String(value);
        return JSON.stringify(value);
    }

    function describeState() {
        if (status === 'missing-cycle')
            return 'No current academic cycle is available. Contact an administrator before preparing a template.';
        if (status === 'no-kit')
            return 'No authorized locked Test Kit version is available for score-bearing templates. Ask an administrator to provide a compatible kit.';
        if (status === 'needs-kit')
            return 'Select an authorized locked Test Kit version to load its accepted score inputs.';
        if (status === 'incompatible-target')
            return 'The selected target session is not compatible with this Test Kit. Choose a compatible completed session or clear the target.';
        if (status === 'denied')
            return (
                requestError ||
                'You do not have permission to view this template schema.'
            );
        if (status === 'empty')
            return 'No supported fields are available for this context.';
        return '';
    }

    onDestroy(() => {
        schemaController?.abort();
        exportController?.abort();
    });
</script>

<Dialog.Root bind:open onOpenChange={handleOpenChange}>
    <Dialog.Content
        class="flex max-h-[92dvh] w-[calc(100vw-1rem)] max-w-6xl flex-col gap-0 overflow-hidden rounded-2xl p-0 motion-reduce:animate-none motion-reduce:transition-none sm:max-w-6xl"
        aria-describedby="import-template-description"
    >
        <Dialog.Header
            class="border-border shrink-0 space-y-3 border-b p-4 text-left sm:p-6"
        >
            <div class="flex flex-wrap items-start justify-between gap-3 pr-8">
                <div class="min-w-0">
                    <Dialog.Title
                        class="text-foreground text-xl font-bold tracking-tight sm:text-2xl"
                    >
                        Build import template
                    </Dialog.Title>
                    <Dialog.Description
                        id="import-template-description"
                        class="text-muted-foreground mt-1 max-w-2xl text-sm"
                    >
                        Choose the accepted columns for your spreadsheet.
                        Preparing a template does not upload rows or change
                        records.
                    </Dialog.Description>
                </div>
                <ImportWalkthrough context={walkthroughContext} />
            </div>

            <div
                class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[minmax(12rem,0.8fr)_minmax(12rem,1fr)_minmax(12rem,1fr)]"
                data-tour="import-template-context"
            >
                <label
                    class="text-foreground space-y-1.5 text-xs font-semibold"
                >
                    <span>Template type</span>
                    <select
                        bind:value={mode}
                        disabled={!canChooseMode || loading || downloading}
                        class="border-input bg-background min-h-10 w-full rounded-md border px-3 py-2 text-sm disabled:cursor-not-allowed disabled:opacity-60"
                        aria-label="Template type"
                    >
                        {#each modeOptions.filter( (option) => allowedModes.includes(option.value) ) as option (option.value)}
                            <option value={option.value}>{option.label}</option>
                        {/each}
                    </select>
                    {#if !canChooseMode}
                        <span class="text-muted-foreground block font-normal"
                            >{modeOptions.find(
                                (option) => option.value === mode,
                            )?.description ??
                                'This mode is set by the page you opened.'}</span
                        >
                    {/if}
                </label>

                <div
                    class="border-border bg-background flex min-h-10 items-center gap-2 rounded-md border px-3 py-2 text-sm"
                    aria-label="Current academic cycle"
                >
                    <FileSpreadsheet
                        class="text-primary size-4 shrink-0"
                        aria-hidden="true"
                    />
                    <span class="text-muted-foreground"
                        >Academic year / semester:</span
                    >
                    <strong class="min-w-0 truncate"
                        >{cycleLabel ?? 'Unavailable'}</strong
                    >
                </div>

                {#if scoreBearing}
                    <label
                        class="text-foreground space-y-1.5 text-xs font-semibold"
                    >
                        <span>Exact locked Test Kit version</span>
                        <select
                            value={testKitVersionId ?? ''}
                            onchange={selectKit}
                            disabled={!hasKitChoices || loading || downloading}
                            class="border-input bg-background min-h-10 w-full rounded-md border px-3 py-2 text-sm disabled:cursor-not-allowed disabled:opacity-60"
                            aria-label="Exact locked Test Kit version"
                        >
                            <option value="">Select a Test Kit version</option>
                            {#each availableKitVersions as kit (kit.id)}
                                <option value={String(kit.id)}>
                                    {kit.display_name ?? kit.name ?? 'Test Kit'} ({kit.kit_code ??
                                        kit.code ??
                                        'Kit'} v{kit.version_number ??
                                        kit.version ??
                                        '—'}){kit.status === 'retired'
                                        ? ' · retired version'
                                        : ''}
                                </option>
                            {/each}
                        </select>
                    </label>
                {/if}

                {#if mode === 'scores_only'}
                    <label
                        class="text-foreground space-y-1.5 text-xs font-semibold sm:col-span-2 lg:col-span-1"
                    >
                        <span
                            >Target exam session <span
                                class="text-muted-foreground font-normal"
                                >(optional context)</span
                            ></span
                        >
                        <select
                            bind:value={targetExamSessionId}
                            disabled={!testKitVersionId ||
                                loading ||
                                downloading ||
                                allowedTargetSessions.length === 0}
                            class="border-input bg-background min-h-10 w-full rounded-md border px-3 py-2 text-sm disabled:cursor-not-allowed disabled:opacity-60"
                            aria-label="Target exam session (optional)"
                        >
                            <option value="">No target session</option>
                            {#each allowedTargetSessions as session (session.id)}
                                <option value={String(session.id)}
                                    >{session.label ??
                                        session.code ??
                                        `Session ${session.id}`}</option
                                >
                            {/each}
                        </select>
                    </label>
                {/if}
            </div>
            {#if mode === 'all_in_one'}
                <p class="text-muted-foreground text-xs">
                    Combined templates put one applicant on each row. Preparing
                    the file does not grant permission to commit applicant and
                    score records together.
                </p>
            {/if}
        </Dialog.Header>

        <div class="min-h-0 flex-1 overflow-y-auto p-4 sm:p-6">
            {#if loading}
                <div
                    class="text-muted-foreground flex min-h-32 items-center justify-center gap-3 rounded-xl border border-dashed p-5 text-sm"
                    role="status"
                    aria-live="polite"
                >
                    <LoaderCircle
                        class="text-primary size-5 animate-spin"
                        aria-hidden="true"
                    />
                    Loading accepted fields for this context…
                </div>
            {:else if requestError}
                <div
                    class="border-destructive/30 bg-destructive/10 text-destructive space-y-3 rounded-xl border p-4 text-sm"
                    role="alert"
                >
                    <p class="font-semibold">{requestError}</p>
                    {#if status !== 'denied' && contextReady}
                        <Button
                            type="button"
                            variant="outline"
                            onclick={retrySchema}
                            disabled={loading}
                        >
                            <RotateCcw
                                class="mr-2 size-4"
                                aria-hidden="true"
                            />Retry schema
                        </Button>
                    {/if}
                </div>
            {:else if describeState()}
                <div
                    class="border-border bg-muted/20 text-muted-foreground rounded-xl border border-dashed p-5 text-sm"
                    role="status"
                >
                    {describeState()}
                </div>
            {:else if status === 'ready' && fields.length === 0}
                <div
                    class="border-border bg-muted/20 text-muted-foreground rounded-xl border border-dashed p-5 text-sm"
                    role="status"
                >
                    No supported fields are available for this context. Check
                    the current intake policy or selected Test Kit setup.
                </div>
            {:else if status === 'ready'}
                {#if staleNotice}
                    <div
                        class="mb-4 space-y-3 rounded-lg border border-amber-500/30 bg-amber-500/10 p-3 text-sm text-amber-900 dark:text-amber-200"
                        role="status"
                    >
                        <p>{staleNotice}</p>
                        {#if contractNeedsReview}
                            <Button
                                type="button"
                                variant="outline"
                                class="min-h-10"
                                onclick={acknowledgeRefreshedHeaders}
                            >
                                I reviewed the refreshed headers
                            </Button>
                        {/if}
                    </div>
                {/if}
                <div
                    class="grid min-w-0 grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1.15fr)_minmax(14rem,0.9fr)_minmax(15rem,0.95fr)]"
                >
                    <section
                        class="min-w-0 space-y-3"
                        aria-labelledby="template-fields-heading"
                        data-tour="import-template-fields"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <h2
                                id="template-fields-heading"
                                class="text-foreground text-sm font-semibold"
                            >
                                Available fields
                            </h2>
                            <span class="text-muted-foreground text-xs"
                                >{fields.length} supported</span
                            >
                        </div>
                        {#if schema?.is_final_only}
                            <p class="text-muted-foreground text-xs">
                                This final-only profile imports external percentile ranks and a separate source reference for every required metric. No norm package or local raw-score conversion is used.
                            </p>
                        {/if}
                        <label class="relative block">
                            <span class="sr-only">Search supported fields</span>
                            <Search
                                class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2"
                                aria-hidden="true"
                            />
                            <input
                                bind:value={search}
                                type="search"
                                placeholder="Search headers, names, or aliases"
                                class="border-input bg-background text-foreground placeholder:text-muted-foreground focus-visible:ring-ring min-h-10 w-full rounded-md border py-2 pr-3 pl-9 text-sm focus-visible:ring-2"
                                aria-label="Search supported fields"
                            />
                        </label>
                        <div
                            class="border-border max-h-72 min-h-40 space-y-4 overflow-y-auto rounded-xl border p-2 sm:max-h-[26rem]"
                        >
                            {#each availableGroups as group (group.name)}
                                <fieldset class="space-y-1">
                                    <legend
                                        class="text-muted-foreground px-2 pb-1 text-[11px] font-semibold tracking-wide uppercase"
                                        >{group.name}</legend
                                    >
                                    {#each group.fields as field (field.header)}
                                        <div
                                            class="hover:bg-muted/50 flex min-w-0 items-start gap-2 rounded-lg px-2 py-2 text-sm"
                                        >
                                            <input
                                                type="checkbox"
                                                checked={selectedHeaders.has(
                                                    field.header,
                                                )}
                                                disabled={field.required ||
                                                    loading ||
                                                    downloading ||
                                                    status !== 'ready'}
                                                onchange={() =>
                                                    toggleField(field)}
                                                class="border-input text-primary focus-visible:ring-ring accent-primary mt-0.5 size-4 shrink-0 rounded disabled:opacity-70"
                                                aria-label={`Include ${field.label ?? field.header}`}
                                                aria-describedby={field.required
                                                    ? `required-${field.header}`
                                                    : undefined}
                                            />
                                            <button
                                                type="button"
                                                class="focus-visible:ring-ring min-w-0 flex-1 rounded text-left focus-visible:ring-2"
                                                onclick={() =>
                                                    setFieldDetails(field)}
                                            >
                                                <span
                                                    class="text-foreground block font-medium break-words"
                                                    >{field.label ??
                                                        field.header}</span
                                                >
                                                <span
                                                    class="text-muted-foreground block font-mono text-xs break-all"
                                                    >{field.header}</span
                                                >
                                                {#if field.required}
                                                    <span
                                                        id={`required-${field.header}`}
                                                        class="text-primary mt-1 flex items-center gap-1 text-[11px]"
                                                    >
                                                        <LockKeyhole
                                                            class="size-3 shrink-0"
                                                            aria-hidden="true"
                                                        />Required · {field.requirement_reason ??
                                                            'Required by this import contract'}
                                                    </span>
                                                {/if}
                                            </button>
                                        </div>
                                    {/each}
                                </fieldset>
                            {:else}
                                <p class="text-muted-foreground p-4 text-sm">
                                    No supported fields match “{search}”.
                                    Selected columns are unchanged.
                                </p>
                            {/each}
                        </div>
                    </section>

                    <section
                        class="min-w-0 space-y-3"
                        aria-labelledby="selected-fields-heading"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <div>
                                <h2
                                    id="selected-fields-heading"
                                    class="text-foreground text-sm font-semibold"
                                >
                                    Selected columns
                                </h2>
                                <p class="text-muted-foreground text-xs">
                                    Fixed contract order · {orderedSelectedFields.length}
                                    selected
                                </p>
                            </div>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onclick={resetSelection}
                                disabled={loading || downloading}
                            >
                                <RotateCcw
                                    class="mr-1.5 size-3.5"
                                    aria-hidden="true"
                                />Reset
                            </Button>
                        </div>
                        <ol
                            class="border-border max-h-72 min-h-40 space-y-1 overflow-y-auto rounded-xl border p-2 sm:max-h-[26rem]"
                            aria-label="Selected headers in export order"
                        >
                            {#each orderedSelectedFields as field, index (field.header)}
                                <li
                                    class="bg-background border-border/60 flex min-w-0 items-center gap-2 rounded-lg border px-2 py-2 text-sm"
                                >
                                    <span
                                        class="text-muted-foreground w-6 shrink-0 text-right font-mono text-xs"
                                        >{index + 1}</span
                                    >
                                    <button
                                        type="button"
                                        class="text-foreground min-w-0 flex-1 truncate text-left font-mono text-xs hover:underline"
                                        onclick={() => setFieldDetails(field)}
                                        >{field.header}</button
                                    >
                                    {#if field.required}
                                        <span
                                            class="text-primary flex shrink-0 items-center gap-1 text-[10px]"
                                            aria-label={`Required: ${field.requirement_reason ?? 'required by this import contract'}`}
                                        >
                                            <LockKeyhole
                                                class="size-3"
                                                aria-hidden="true"
                                            /><span class="sr-only"
                                                >Required</span
                                            >
                                        </span>
                                    {:else}
                                        <button
                                            type="button"
                                            class="text-muted-foreground hover:text-destructive focus-visible:ring-ring shrink-0 rounded p-1 focus-visible:ring-2"
                                            aria-label={`Remove ${field.header}`}
                                            onclick={() => removeField(field)}
                                            disabled={loading || downloading}
                                        >
                                            <X
                                                class="size-4"
                                                aria-hidden="true"
                                            />
                                        </button>
                                    {/if}
                                </li>
                            {:else}
                                <li class="text-muted-foreground p-4 text-sm">
                                    Select supported optional fields to add them
                                    to this template.
                                </li>
                            {/each}
                        </ol>
                    </section>

                    <section
                        class="border-border bg-muted/20 min-w-0 space-y-3 rounded-xl border p-3 sm:p-4"
                        aria-labelledby="template-details-heading"
                        data-tour="import-template-details"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <h2
                                    id="template-details-heading"
                                    class="text-foreground text-sm font-semibold"
                                >
                                    Field details
                                </h2>
                                {#if selectedField}
                                    <p
                                        class="text-primary mt-1 font-mono text-xs break-all"
                                    >
                                        {selectedField.header}
                                    </p>
                                {/if}
                            </div>
                            {#if selectedField}
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onclick={() => copyHeader(selectedField)}
                                    disabled={!canCopyField}
                                >
                                    <Clipboard
                                        class="mr-1.5 size-3.5"
                                        aria-hidden="true"
                                    />Copy header
                                </Button>
                            {/if}
                        </div>
                        {#if selectedField}
                            <dl class="space-y-3 text-sm">
                                <div>
                                    <dt
                                        class="text-muted-foreground text-xs font-semibold"
                                    >
                                        Meaning
                                    </dt>
                                    <dd class="text-foreground mt-0.5">
                                        {selectedField.description ??
                                            'Not provided by this setup'}
                                    </dd>
                                </div>
                                <div>
                                    <dt
                                        class="text-muted-foreground text-xs font-semibold"
                                    >
                                        Where staff gets it
                                    </dt>
                                    <dd class="text-foreground mt-0.5">
                                        {selectedField.source ??
                                            'Not provided by this setup'}
                                    </dd>
                                </div>
                                <div>
                                    <dt
                                        class="text-muted-foreground text-xs font-semibold"
                                    >
                                        Accepted format
                                    </dt>
                                    <dd
                                        class="text-foreground mt-0.5 break-words"
                                    >
                                        {formatValue(selectedField.format)}
                                    </dd>
                                </div>
                                {#if selectedField.min !== undefined || selectedField.max !== undefined}
                                    <div>
                                        <dt
                                            class="text-muted-foreground text-xs font-semibold"
                                        >
                                            Limits
                                        </dt>
                                        <dd
                                            class="text-foreground mt-0.5 font-mono text-xs"
                                        >
                                            {selectedField.min !== undefined
                                                ? `Minimum ${selectedField.min}`
                                                : ''}{selectedField.min !==
                                                undefined &&
                                            selectedField.max !== undefined
                                                ? ' · '
                                                : ''}{selectedField.max !==
                                            undefined
                                                ? `Maximum ${selectedField.max}`
                                                : ''}
                                        </dd>
                                    </div>
                                {/if}
                                <div>
                                    <dt
                                        class="text-muted-foreground text-xs font-semibold"
                                    >
                                        Synthetic example
                                    </dt>
                                    <dd
                                        class="text-foreground mt-0.5 font-mono text-xs break-all"
                                    >
                                        {formatValue(selectedField.example)}
                                    </dd>
                                </div>
                                {#if selectedField.required}
                                    <div>
                                        <dt
                                            class="text-muted-foreground text-xs font-semibold"
                                        >
                                            Requirement
                                        </dt>
                                        <dd class="text-foreground mt-0.5">
                                            Required · {selectedField.requirement_reason ??
                                                'Required by this import contract'}
                                        </dd>
                                    </div>
                                {/if}
                                <div>
                                    <dt
                                        class="text-muted-foreground text-xs font-semibold"
                                    >
                                        Accepted alternate spellings
                                    </dt>
                                    <dd
                                        class="text-foreground mt-0.5 font-mono text-xs break-words"
                                    >
                                        {Array.isArray(selectedField.aliases) &&
                                        selectedField.aliases.length
                                            ? selectedField.aliases.join(', ')
                                            : 'None listed'}
                                    </dd>
                                </div>
                            </dl>
                        {:else}
                            <p class="text-muted-foreground text-sm">
                                Choose a field to see its format, source,
                                aliases, and example.
                            </p>
                        {/if}
                    </section>
                </div>

                <section
                    class="border-border mt-5 min-w-0 space-y-3 border-t pt-4"
                    aria-labelledby="template-preview-heading"
                    data-tour="import-template-preview"
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-3"
                    >
                        <div>
                            <h2
                                id="template-preview-heading"
                                class="text-foreground text-sm font-semibold"
                            >
                                Header row preview
                            </h2>
                            <p class="text-muted-foreground text-xs">
                                This order is used for copy and CSV download.
                            </p>
                        </div>
                    </div>
                    <textarea
                        readonly
                        wrap="off"
                        rows="2"
                        class="border-border bg-background text-foreground focus-visible:ring-ring block min-h-16 w-full resize-none overflow-x-auto rounded-lg border p-3 font-mono text-xs leading-6 focus-visible:ring-2"
                        aria-label="Scrollable ordered header preview"
                        >{orderedSelectedFields
                            .map((field) => field.header)
                            .join('\t')}</textarea
                    >
                    {#if copyMessage}<p
                            class="text-muted-foreground text-sm"
                            role="status"
                            aria-live="polite"
                        >
                            {copyMessage}
                        </p>{/if}
                    {#if exportMessage}<p
                            class="text-destructive text-sm"
                            role="alert"
                        >
                            {exportMessage}
                        </p>{/if}
                </section>
            {/if}
        </div>

        <Dialog.Footer
            class="border-border flex shrink-0 flex-col gap-3 border-t p-4 sm:flex-row sm:items-center sm:justify-between sm:px-6"
        >
            <p class="text-muted-foreground max-w-xl text-xs">
                The file contains headers only. Upload, analyze, stage, review,
                and confirm remain separate steps.
            </p>
            <div class="flex flex-wrap justify-end gap-2">
                <Button
                    type="button"
                    variant="outline"
                    class="min-h-10"
                    onclick={copyAllHeaders}
                    disabled={!canCopyHeaders}
                >
                    {#if copyMessage === 'Headers copied.'}<ClipboardCheck
                            class="mr-2 size-4"
                            aria-hidden="true"
                        />{:else}<Clipboard
                            class="mr-2 size-4"
                            aria-hidden="true"
                        />{/if}Copy all headers
                </Button>
                <Button
                    type="button"
                    class="min-h-10"
                    onclick={downloadCsv}
                    disabled={!canDownload}
                >
                    {#if downloading}<LoaderCircle
                            class="mr-2 size-4 animate-spin"
                            aria-hidden="true"
                        />Preparing CSV…{:else}<Download
                            class="mr-2 size-4"
                            aria-hidden="true"
                        />Download CSV{/if}
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    class="min-h-10"
                    onclick={closeBuilder}
                >
                    Done
                </Button>
            </div>
        </Dialog.Footer>
    </Dialog.Content>
</Dialog.Root>
